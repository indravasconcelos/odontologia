<?php
/**
 * Env — leitura das variaveis de ambiente (.env)
 *
 * Ordem de precedencia:
 *   1. Variavel real do servidor (Hostinger, Apache, Docker, CI)
 *   2. Arquivo .env na raiz do projeto
 *
 * Nenhuma credencial fica no codigo.
 */

declare(strict_types=1);

final class Env
{
    private static array $cache = [];
    private static bool $carregado = false;

    public static function get(string $chave, ?string $padrao = null): ?string
    {
        $chave = strtoupper($chave);

        if (array_key_exists($chave, self::$cache)) {
            return self::$cache[$chave];
        }

        $valor = self::doServidor($chave);

        if ($valor === null) {
            self::carregarArquivo();
            $valor = self::$cache[$chave] ?? null;
        }

        self::$cache[$chave] = $valor;

        return $valor ?? $padrao;
    }

    public static function bool(string $chave, bool $padrao = false): bool
    {
        $valor = self::get($chave);
        if ($valor === null || $valor === '') {
            return $padrao;
        }
        return in_array(strtolower($valor), ['1', 'true', 'sim', 'on', 'yes'], true);
    }

    public static function int(string $chave, int $padrao = 0): int
    {
        $valor = self::get($chave);
        return ($valor === null || $valor === '') ? $padrao : (int) $valor;
    }

    private static function doServidor(string $chave): ?string
    {
        $valor = getenv($chave);
        if ($valor === false || $valor === '') {
            if (isset($_ENV[$chave]) && $_ENV[$chave] !== '') {
                return (string) $_ENV[$chave];
            }
            if (isset($_SERVER[$chave]) && $_SERVER[$chave] !== '') {
                return (string) $_SERVER[$chave];
            }
            return null;
        }
        return (string) $valor;
    }

    private static function carregarArquivo(): void
    {
        if (self::$carregado) {
            return;
        }
        self::$carregado = true;

        $caminho = dirname(__DIR__) . '/.env';
        if (!is_readable($caminho)) {
            return;
        }

        $linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($linhas === false) {
            return;
        }

        foreach ($linhas as $linha) {
            $linha = trim($linha);

            if ($linha === '' || $linha[0] === '#') {
                continue;
            }
            if (str_starts_with($linha, 'export ')) {
                $linha = substr($linha, 7);
            }
            $pos = strpos($linha, '=');
            if ($pos === false) {
                continue;
            }

            $chave = strtoupper(trim(substr($linha, 0, $pos)));
            $valor = trim(substr($linha, $pos + 1));

            // remove aspas envolventes, se houver
            $tam = strlen($valor);
            if ($tam >= 2) {
                $primeiro = $valor[0];
                $ultimo = $valor[$tam - 1];
                if (($primeiro === '"' && $ultimo === '"') || ($primeiro === "'" && $ultimo === "'")) {
                    $valor = substr($valor, 1, -1);
                }
            }

            // valor vazio entre aspas continua sendo string vazia
            if (!array_key_exists($chave, self::$cache)) {
                self::$cache[$chave] = $valor;
            }
        }
    }
}