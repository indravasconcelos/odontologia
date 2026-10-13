<?php
/**
 * Helpers — funcoes utilitarias usadas pelo painel e pela API
 */

declare(strict_types=1);

final class Helpers
{
    public static function e(string $chave, mixed $padrao = null): mixed
    {
        $valor = $_GET[$chave] ?? $_POST[$chave] ?? $padrao;
        return is_string($valor) ? trim($valor) : $valor;
    }

    public static function post(string $chave, mixed $padrao = null): mixed
    {
        $valor = $_POST[$chave] ?? $padrao;
        return is_string($valor) ? trim($valor) : $valor;
    }

    public static function redirecionar(string $url): never
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Location: ' . $url);
            exit;
        }
        echo 'Redirecionando para ' . $url . PHP_EOL;
        exit;
    }

    public static function json(mixed $dados, int $status = 200): never
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function escapar(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** URL absoluta da aplicacao, sem barra final */
    public static function baseUrl(): string
    {
        $base = (string) Env::get('APP_URL', '');
        if ($base !== '') {
            return rtrim($base, '/');
        }

        // detecta a URL atual quando APP_URL nao esta configurado
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $esquema = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $raiz = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');

        return $esquema . '://' . $host . $raiz;
    }

    /**
     * Caminho de um diretorio interno do sistema, criando se faltar.
     *
     * Usa a pasta "logs" da raiz do projeto, que existe tanto no
     * ambiente local quanto na hospedagem. Se nao der para escrever
     * (permissao do servidor), cai no diretorio temporario do PHP
     * para que o sistema nunca pare de funcionar por causa de log.
     *
     * @param string $sub opcional, ex: 'rate' ou 'backup'
     */
    public static function diretorioInterno(string $sub = ''): string
    {
        $base = APP_RAIZ . '/logs';
        if ($sub !== '') {
            $base .= '/' . trim($sub, '/');
        }

        if (!is_dir($base)) {
            @mkdir($base, 0775, true);
        }

        if (is_dir($base) && is_writable($base)) {
            return $base;
        }

        // fallback: diretorio temporario do proprio PHP
        $temporario = rtrim(sys_get_temp_dir(), '/\\') . '/odontologia';
        if (!is_dir($temporario)) {
            @mkdir($temporario, 0775, true);
        }

        return is_dir($temporario) ? $temporario : sys_get_temp_dir();
    }

    public static function dataBr(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '—';
        }
        $ts = strtotime($iso);
        return $ts ? date('d/m/Y', $ts) : '—';
    }

    public static function horaBr(?string $hora): string
    {
        if ($hora === null || $hora === '') {
            return '—';
        }
        $ts = strtotime($hora);
        return $ts ? date('H:i', $ts) : '—';
    }

    public static function dataHoraBr(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '—';
        }
        $ts = strtotime($iso);
        return $ts ? date('d/m/Y às H:i', $ts) : '—';
    }

    public static function diaSemana(int $dia): string
    {
        $dias = [
            0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta',
            4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado',
        ];
        return $dias[$dia] ?? '—';
    }

    /** "2026-10-09" -> "sexta-feira, 9 de outubro" */
    public static function dataExtenso(string $dataIso): string
    {
        $ts = strtotime($dataIso);
        if (!$ts) {
            return $dataIso;
        }

        $dias = ['Sunday' => 'domingo', 'Monday' => 'segunda-feira', 'Tuesday' => 'terça-feira',
            'Wednesday' => 'quarta-feira', 'Thursday' => 'quinta-feira', 'Friday' => 'sexta-feira',
            'Saturday' => 'sábado'];

        $meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho',
            'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

        $diaSemana = $dias[date('l', $ts)] ?? date('l', $ts);
        $mes = $meses[(int) date('n', $ts)] ?? date('F', $ts);

        return $diaSemana . ', ' . (int) date('j', $ts) . ' de ' . $mes;
    }

    public static function minutosParaHora(int $minutos): string
    {
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }

    public static function horaParaMinutos(string $hora): int
    {
        $partes = explode(':', $hora);
        return ((int) ($partes[0] ?? 0)) * 60 + (int) ($partes[1] ?? 0);
    }

    public static function somaMinutos(string $hora, int $minutos): string
    {
        $total = self::horaParaMinutos($hora) + $minutos;
        return sprintf('%02d:%02d:00', intdiv($total, 60), $total % 60);
    }

    /** token CSRF da sessao */
    public static function csrfToken(): string
    {
        Session::iniciar();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfValido(?string $token): bool
    {
        Session::iniciar();
        $guardado = $_SESSION['csrf'] ?? '';
        return is_string($token) && $guardado !== '' && hash_equals($guardado, $token);
    }

    public static function exigirCsrf(): void
    {
        $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::csrfValido(is_string($token) ? $token : null)) {
            if (self::ehJson()) {
                self::json(['ok' => false, 'erro' => 'Sessão expirada. Atualize a página.'], 419);
            }
            self::redirecionar('../index.php?erro=sessao');
        }
    }

    public static function ehJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /** telefone brasileiro apenas com digitos */
    public static function telefone(string $telefone): string
    {
        return preg_replace('/\D/', '', $telefone) ?? '';
    }

    public static function telefoneValido(string $telefone): bool
    {
        $d = self::telefone($telefone);
        return strlen($d) >= 10 && strlen($d) <= 13;
    }

    /**
     * Telefone no formato que o WhatsApp aceita em wa.me: 55 + DDD + número.
     *
     * Aceita o número como está salvo no banco (só dígitos, sem DDI), com
     * pontuação, com +55 ou com 0055. Devolve null quando não dá para
     * montar um número correto — prefiro não abrir link errado.
     */
    public static function whatsappNumero(?string $telefone): ?string
    {
        $d = self::telefone((string) $telefone);

        if ($d === '') {
            return null;
        }

        // 0055... (discado internacional)
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        }

        // Com DDI 55 na frente só sobra o telefone se o compramento fechar.
        $comDdi = str_starts_with($d, '55')
            && (strlen($d) === 12 || strlen($d) === 13);

        if ($comDdi) {
            $ddd   = substr($d, 2, 2);
            $local = substr($d, 4);
        } else {
            $ddd   = substr($d, 0, 2);
            $local = substr($d, 2);
        }

        $dddN = (int) $ddd;

        // DDD brasileiro vai de 11 a 99, sempre com 2 dígitos.
        if (strlen($ddd) !== 2 || $dddN < 11 || $dddN > 99) {
            return null;
        }

        // 8 dígitos = fixo (2 a 5 no 1º), 9 = celular (9 no 1º).
        $prefixo = (int) substr($local, 0, 1);
        if (strlen($local) === 8) {
            $aceito = $prefixo >= 2 && $prefixo <= 5;
        } elseif (strlen($local) === 9) {
            $aceito = $prefixo === 9;
        } else {
            $aceito = false;
        }

        if (!$aceito) {
            return null;
        }

        return '55' . $ddd . $local;
    }

    /**
     * Link wa.me com a mensagem já codificada. Devolve null quando o
     * telefone não é aproveitável.
     */
    public static function whatsappLink(?string $telefone, string $mensagem): ?string
    {
        $numero = self::whatsappNumero($telefone);

        if ($numero === null) {
            return null;
        }

        return 'https://wa.me/' . $numero . '?text=' . rawurlencode($mensagem);
    }

    public static function emailValido(string $email): bool
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function dataValida(string $data): bool
    {
        $ts = strtotime($data);
        return $ts !== false && date('Y-m-d', $ts) === $data;
    }

    public static function hoje(): string
    {
        return date('Y-m-d');
    }
}