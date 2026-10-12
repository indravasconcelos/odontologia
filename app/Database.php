<?php
/**
 * Database — conexao PDO unica (MySQL)
 * Compativel com Hostinger: le credenciais do .env, sem hardcode.
 */

declare(strict_types=1);

final class Database
{
    private static ?PDO $conexao = null;

    public static function conexao(): PDO
    {
        if (self::$conexao instanceof PDO) {
            return self::$conexao;
        }

        $host = Env::get('DB_HOST', '127.0.0.1');
        $porta = Env::int('DB_PORT', 3306);
        $nome = Env::get('DB_NAME', 'odontologia');
        $usuario = Env::get('DB_USER', 'root');
        $senha = Env::get('DB_PASSWORD', '');
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$porta};dbname={$nome};charset={$charset}";

        try {
            self::$conexao = new PDO($dsn, $usuario, $senha, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            // nao expõe credenciais nem detalhe técnico ao visitante
            error_log('[DB] falha ao conectar: ' . $e->getMessage());
            throw new RuntimeException('Não foi possível conectar ao banco de dados.');
        }

        return self::$conexao;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::conexao()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function todos(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function primeiro(string $sql, array $params = []): ?array
    {
        $linha = self::query($sql, $params)->fetch();
        return $linha === false ? null : $linha;
    }

    public static function valor(string $sql, array $params = [], mixed $padrao = null): mixed
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? $padrao : $v;
    }

    public static function inserir(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int) self::conexao()->lastInsertId();
    }

    public static function transacao(callable $callback): mixed
    {
        $pdo = self::conexao();
        $pdo->beginTransaction();
        try {
            $resultado = $callback($pdo);
            $pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function disponivel(): bool
    {
        try {
            self::conexao()->query('SELECT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}