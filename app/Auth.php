<?php
/**
 * Auth — login, senha, tokens de recuperacao
 * Senhas sempre com password_hash() / password_verify().
 */

declare(strict_types=1);

final class Auth
{
    private const TENTATIVAS_MAX = 5;
    private const BLOQUEIO_MINUTOS = 15;
    private const TOKEN_VALIDADE_MINUTOS = 60;

    public static function usuario(): ?array
    {
        $id = Session::usuarioId();
        if ($id === null) {
            return null;
        }

        return Database::primeiro(
            'SELECT id, nome, email, tipo, ativo FROM usuarios WHERE id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * @return array{ok:bool, erro?:string}
     */
    public static function tentar(string $email, string $senha): array
    {
        $email = trim($email);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'erro' => 'Informe um e-mail válido.'];
        }

        if (self::excedeu($email)) {
            return ['ok' => false, 'erro' => 'Muitas tentativas. Tente de novo em alguns minutos.'];
        }

        $usuario = Database::primeiro(
            'SELECT id, nome, email, senha_hash, tipo, ativo FROM usuarios WHERE email = ? LIMIT 1',
            [$email]
        );

        // resposta generica: nao revela se o e-mail existe
        if ($usuario === null || !$usuario['ativo']) {
            self::registrarFalha($email);
            return ['ok' => false, 'erro' => 'E-mail ou senha incorretos.'];
        }

        if (!password_verify($senha, $usuario['senha_hash'])) {
            self::registrarFalha($email);
            return ['ok' => false, 'erro' => 'E-mail ou senha incorretos.'];
        }

        // rehash quando o algoritmo do PHP evolui
        if (password_needs_rehash($usuario['senha_hash'], PASSWORD_DEFAULT)) {
            Database::query(
                'UPDATE usuarios SET senha_hash = ? WHERE id = ?',
                [password_hash($senha, PASSWORD_DEFAULT), $usuario['id']]
            );
        }

        self::zerarFalhas($email);
        Session::login((int) $usuario['id']);

        return ['ok' => true];
    }

    public static function trocarSenha(int $usuarioId, string $atual, string $nova): array
    {
        $hash = Database::valor('SELECT senha_hash FROM usuarios WHERE id = ?', [$usuarioId]);

        if (!is_string($hash) || !password_verify($atual, $hash)) {
            return ['ok' => false, 'erro' => 'A senha atual não confere.'];
        }

        if (mb_strlen($nova) < 8) {
            return ['ok' => false, 'erro' => 'A nova senha precisa ter pelo menos 8 caracteres.'];
        }

        Database::query(
            'UPDATE usuarios SET senha_hash = ? WHERE id = ?',
            [password_hash($nova, PASSWORD_DEFAULT), $usuarioId]
        );

        // invalida qualquer recuperacao pendente
        Database::query(
            'UPDATE reset_tokens SET usado_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL',
            [$usuarioId]
        );

        return ['ok' => true];
    }

    // ---------------- recuperacao de senha ----------------

    /**
     * Gera o token e devolve a URL (o envio fica com o Mailer).
     * O token em si nunca e guardado: apenas o SHA-256.
     */
    public static function gerarTokenRecuperacao(string $email): ?string
    {
        $usuario = Database::primeiro(
            'SELECT id, nome, email FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1',
            [trim($email)]
        );

        if ($usuario === null) {
            return null;
        }

        // invalida pedidos anteriores em aberto
        Database::query(
            'UPDATE reset_tokens SET usado_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL',
            [$usuario['id']]
        );

        $token = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', time() + self::TOKEN_VALIDADE_MINUTOS * 60);

        Database::query(
            'INSERT INTO reset_tokens (usuario_id, token_hash, expira_em) VALUES (?, ?, ?)',
            [$usuario['id'], hash('sha256', $token), $expira]
        );

        $base = rtrim((string) Env::get('APP_URL', ''), '/');

        return $base . '/auth/redefinir-senha.php?token=' . $token;
    }

    public static function tokenValido(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $usuario = Database::primeiro(
            'SELECT rt.id AS token_id, rt.expira_em, u.id, u.nome, u.email
               FROM reset_tokens rt
               JOIN usuarios u ON u.id = rt.usuario_id
              WHERE rt.token_hash = ?
                AND rt.usado_em IS NULL
                AND rt.expira_em > NOW()
              LIMIT 1',
            [hash('sha256', $token)]
        );

        return $usuario;
    }

    public static function usarToken(string $token, string $novaSenha): array
    {
        $dados = self::tokenValido($token);

        if ($dados === null) {
            return ['ok' => false, 'erro' => 'Este link expirou ou já foi utilizado.'];
        }

        if (mb_strlen($novaSenha) < 8) {
            return ['ok' => false, 'erro' => 'A senha precisa ter pelo menos 8 caracteres.'];
        }

        Database::transacao(static function (PDO $pdo) use ($dados, $novaSenha, $token): void {
            $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                ->execute([password_hash($novaSenha, PASSWORD_DEFAULT), $dados['id']]);

            $pdo->prepare('UPDATE reset_tokens SET usado_em = NOW() WHERE token_hash = ?')
                ->execute([hash('sha256', $token)]);
        });

        return ['ok' => true];
    }

    // ---------------- limite de tentativas ----------------

/**
 * Chave de bloqueio: por IP e por e-mail.
 *
 * O IP protege o servidor contra forca bruta (5 tentativas).
 * O e-mail impede que alguem discoverta a senha de uma conta
 * especifica mudando de origem a cada tentativa. Usar os dois
 * significaria que o atacante ficaria preso aos dois: trocar de IP
 * mantem o bloqueio por e-mail, e trocar de e-mail mantem o do IP.
 */
private static function chaveIp(): string
    {
        return RateLimit::ipChave();
    }

    private static function chaveEmail(string $email): string
    {
        return hash('sha256', 'email:' . mb_strtolower(trim($email)));
    }

    private static function excedeu(string $email): bool
    {
        $minutos = self::BLOQUEIO_MINUTOS;

        return RateLimit::excedeu(self::chaveIp(), self::TENTATIVAS_MAX, $minutos)
            || RateLimit::excedeu(self::chaveEmail($email), self::TENTATIVAS_MAX, $minutos);
    }

    private static function registrarFalha(string $email): void
    {
        RateLimit::falhou(self::chaveIp(), self::TENTATIVAS_MAX, self::BLOQUEIO_MINUTOS);
        RateLimit::falhou(self::chaveEmail($email), self::TENTATIVAS_MAX, self::BLOQUEIO_MINUTOS);
    }

    private static function zerarFalhas(string $email): void
    {
        RateLimit::limpou(self::chaveIp());
        RateLimit::limpou(self::chaveEmail($email));
    }
}