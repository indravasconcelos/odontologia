<?php
/**
 * Session — sessao PHP segura (cookies HttpOnly, SameSite, expiracao)
 */

declare(strict_types=1);

final class Session
{
    private const CHAVE_ATIVO = 'usuario_id';
    private const CHAVE_MOMENTO = 'ultima_atividade';
    private const CHAVE_PENDENTE = 'destino';

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // No terminal (testes e scripts) nao ha cookie nem navegador:
        // iniciar sessao de verdade so produziria aviso do PHP, porque
        // ja houve saida no console. Um array vazio basta para os testes.
        if (PHP_SAPI === 'cli') {
            if (!isset($_SESSION) || !is_array($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

            $params = [
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => $https,
                'httponly' => true,
                'samesite' => 'Lax',
            ];
            session_set_cookie_params($params);
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
        }

        session_name('ODONTO_SESSAO');
        session_start();
    }

    public static function login(int $usuarioId): void
    {
        self::iniciar();
        session_regenerate_id(true);
        $_SESSION[self::CHAVE_ATIVO] = $usuarioId;
        $_SESSION[self::CHAVE_MOMENTO] = time();
        unset($_SESSION[self::CHAVE_PENDENTE]);
    }

    public static function logout(): void
    {
        self::iniciar();
        $_SESSION = [];

        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    public static function logado(): bool
    {
        self::iniciar();

        if (empty($_SESSION[self::CHAVE_ATIVO])) {
            return false;
        }

        $limite = Env::int('SESSAO_MINUTOS', 30) * 60;
        $ultima = (int) ($_SESSION[self::CHAVE_MOMENTO] ?? 0);

        if ($ultima > 0 && (time() - $ultima) > $limite) {
            self::logout();
            return false;
        }

        $_SESSION[self::CHAVE_MOMENTO] = time();
        return true;
    }

    public static function usuarioId(): ?int
    {
        return self::logado() ? (int) $_SESSION[self::CHAVE_ATIVO] : null;
    }

    public static function expirada(): bool
    {
        self::iniciar();
        return empty($_SESSION[self::CHAVE_ATIVO]);
    }

    /** guarda o destino para voltar depois do login */
    public static function guardarDestino(string $url): void
    {
        self::iniciar();
        $_SESSION[self::CHAVE_PENDENTE] = $url;
    }

    public static function destino(): ?string
    {
        self::iniciar();
        return $_SESSION[self::CHAVE_PENDENTE] ?? null;
    }

    /** mensagem de uma acao, mostrada uma unica vez */
    public static function flash(string $mensagem, string $tipo = 'ok'): void
    {
        self::iniciar();
        $_SESSION['flash'] = ['mensagem' => $mensagem, 'tipo' => $tipo];
    }

    /** @return array{mensagem:string,tipo:string}|null */
    public static function pegaFlash(): ?array
    {
        self::iniciar();
        $dados = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_array($dados) ? $dados : null;
    }
}