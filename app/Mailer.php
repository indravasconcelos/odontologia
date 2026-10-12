<?php
/**
 * Mailer — envio de e-mail
 *
 * Usa a funcao mail() do PHP, que na Hostinger ja funciona com o
 * e-mail da conta. Quando SMTP_HOST estiver preenchido no .env,
 * o envio e feito por SMTP autenticado.
 *
 * Se o envio falhar (SMTP indisponivel, limite do servidor), a
 * mensagem e gravada em logs/mail.log para voce conferir o que deu
 * errado. A pasta logs/ fica bloqueada pelo .htaccess.
 */

declare(strict_types=1);

final class Mailer
{
    public static function enviar(string $para, string $assunto, string $corpoHtml, string $corpoTexto = ''): bool
    {
        $deEmail = (string) Env::get('MAIL_FROM', Env::get('EMAIL_USER', 'nao-responda@localhost'));
        $deNome = (string) Env::get('MAIL_FROM_NAME', 'Dr. Maycon Gomes');

        if (Env::get('SMTP_HOST') !== null && Env::get('SMTP_HOST') !== '') {
            return self::viaSmtp($para, $assunto, $corpoHtml, $corpoTexto, $deEmail, $deNome);
        }

        return self::viaMail($para, $assunto, $corpoHtml, $corpoTexto, $deEmail, $deNome);
    }

    private static function viaMail(
        string $para,
        string $assunto,
        string $html,
        string $texto,
        string $deEmail,
        string $deNome
    ): bool {
        $cabecalho = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: =?UTF-8?B?' . base64_encode($deNome) . '?= <' . $deEmail . '>',
            'Reply-To: ' . $deEmail,
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        $ok = @mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $html, implode("\r\n", $cabecalho));

        if (!$ok) {
            self::registrarFallback($para, $assunto, $texto !== '' ? $texto : strip_tags($html));
        }

        return $ok;
    }

    private static function viaSmtp(
        string $para,
        string $assunto,
        string $html,
        string $texto,
        string $deEmail,
        string $deNome
    ): bool {
        $host = (string) Env::get('SMTP_HOST');
        $porta = Env::int('SMTP_PORT', 587);
        $usuario = (string) Env::get('SMTP_USER', Env::get('EMAIL_USER', ''));
        $senha = (string) Env::get('SMTP_PASSWORD', Env::get('EMAIL_PASSWORD', ''));
        $cripto = strtolower((string) Env::get('SMTP_SECURE', 'tls'));

        $destino = ($cripto === 'ssl' ? 'ssl://' : '') . $host . ':' . $porta;
        $erro = '';
        $con = @stream_socket_client($destino, $errno, $errstr, 15);

        if ($con === false) {
            self::registrarFallback($para, $assunto, "SMTP indisponivel ($errstr). Link:\n" . $texto);
            return false;
        }

        stream_set_timeout($con, 15);

        $esperar = function (string $esperado) use ($con, &$erro): bool {
            $linha = '';
            $tempo = time() + 10;
            while (time() < $tempo) {
                $l = fgets($con, 515);
                if ($l === false) {
                    break;
                }
                $linha .= $l;
                if (strlen($l) < 4 || $l[3] === ' ') {
                    break;
                }
            }
            if (stripos($linha, $esperado) === false) {
                $erro = trim($linha);
                return false;
            }
            return true;
        };

        $ok = $esperar('220');
        if ($ok) {
            fwrite($con, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n");
            $ok = $esperar('250');
        }
        if ($ok && $cripto === 'tls') {
            fwrite($con, "STARTTLS\r\n");
            $ok = $esperar('220');
            if ($ok) {
                if (!stream_socket_enable_crypto($con, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    $ok = false;
                    $erro = 'TLS nao negociado';
                }
                if ($ok) {
                    fwrite($con, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n");
                    $ok = $esperar('250');
                }
            }
        }
        if ($ok && $usuario !== '') {
            fwrite($con, "AUTH LOGIN\r\n");
            $ok = $esperar('334');
            if ($ok) {
                fwrite($con, base64_encode($usuario) . "\r\n");
                $ok = $esperar('334');
            }
            if ($ok) {
                fwrite($con, base64_encode($senha) . "\r\n");
                $ok = $esperar('235');
            }
        }
        if ($ok) {
            fwrite($con, "MAIL FROM:<{$deEmail}>\r\n");
            $ok = $esperar('250');
        }
        if ($ok) {
            fwrite($con, "RCPT TO:<{$para}>\r\n");
            $ok = $esperar('250');
        }
        if ($ok) {
            fwrite($con, "DATA\r\n");
            $ok = $esperar('354');
        }

        if ($ok) {
            $corpo = 'From: =?UTF-8?B?' . base64_encode($deNome) . '?= <' . $deEmail . ">\r\n"
                . 'To: <' . $para . ">\r\n"
                . 'Subject: =?UTF-8?B?' . base64_encode($assunto) . "?=\r\n"
                . 'Date: ' . date('r') . "\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
                . str_replace("\n.", "\n..", $html) . "\r\n.";

            fwrite($con, $corpo . "\r\n");
            $ok = $esperar('250');
        }

        fwrite($con, "QUIT\r\n");
        fclose($con);

        if (!$ok) {
            self::registrarFallback($para, $assunto, "SMTP falhou ($erro). Link:\n" . $texto);
        }

        return $ok;
    }

    /** guarda o conteudo do e-mail quando o envio falha */
    private static function registrarFallback(string $para, string $assunto, string $texto): void
    {
        $dir = Helpers::diretorioInterno();

        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }

        @file_put_contents(
            $dir . '/mail.log',
            "===== " . date('d/m/Y H:i:s') . " =====\nPara: {$para}\nAssunto: {$assunto}\n\n{$texto}\n\n",
            FILE_APPEND | LOCK_EX
        );
    }

    public static function templateRecuperacao(string $nome, string $link): string
    {
        $nomeS = Helpers::escapar($nome);
        $linkS = Helpers::escapar($link);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><meta charset="utf-8"></head>
        <body style="margin:0;padding:24px;background:#f4f8fb;font-family:Segoe UI,Roboto,sans-serif;color:#16283a">
          <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:18px;overflow:hidden;border:1px solid rgba(7,27,51,.09)">
            <div style="background:#071b33;padding:24px 28px">
              <strong style="color:#fff;font-size:18px">Dr. Maycon Gomes</strong>
              <div style="color:#b9c9d9;font-size:13px;margin-top:2px">Ortodontia / Clínico Geral · CRO/RO 3795</div>
            </div>
            <div style="padding:28px">
              <p style="margin:0 0 16px">Olá, {$nomeS}!</p>
              <p style="margin:0 0 16px;line-height:1.6">Recebemos um pedido para redefinir a senha do seu acesso à agenda.</p>
              <p style="margin:0 0 24px">
                <a href="{$linkS}" style="display:inline-block;background:linear-gradient(120deg,#14d6c4,#2e7be8);color:#04212f;text-decoration:none;font-weight:700;padding:13px 22px;border-radius:12px">Criar nova senha</a>
              </p>
              <p style="margin:0 0 8px;color:#5a6b7d;font-size:14px;line-height:1.6">
                O link é válido por 60 minutos e funciona uma única vez.
                Se não foi você, pode ignorar este e-mail.
              </p>
              <p style="margin:0;color:#7a8ca0;font-size:13px;word-break:break-all">
                Se o botão não abrir, copie o endereço: {$linkS}
              </p>
            </div>
          </div>
        </body>
        </html>
        HTML;
    }
}