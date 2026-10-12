<?php
/**
 * RateLimit — bloqueio por tentativas, resistente a troca de sessao
 *
 * Por que existe
 * --------------
 * A versao anterior guardava o contador de tentativas dentro da sessao
 * ( $_SESSION ). Isso nao protege de verdade: um atacante que simplesmente
 * nao envie o cookie de sessao recebe uma sessao nova a cada pedido e
 * zera a contagem, tentando senhas infinitas sem nunca ser bloqueado.
 *
 * Aqui o contador vive em um arquivo em logs/rate/, no servidor. Ele:
 *   - sobrevive a troca de sessao e a reinicio do PHP;
 *   - e compartilhado por todas as sessoes do mesmo IP;
 *   - usa somente REMOTE_ADDR (nada de cabecalho enviado pelo cliente,
 *     que seria facil de forjar para escapar do bloqueio);
 *   - guarda o IP apenas como hash, sem deixar o endereco no nome do arquivo.
 *
 * Se o servidor nao permitir escrever, volta para a sessao. Assim nunca
 * trava o acesso por causa de log — apenas perde a protecao extra.
 */

declare(strict_types=1);

final class RateLimit
{
    /** @var int contador interno, usado para limpar arquivos velhos de vez em quando */
    private static int $limpezas = 0;

    /**
     * @return array{n:int, ate:int}
     */
    public static function estado(string $chave): array
    {
        $vazio = ['n' => 0, 'ate' => 0];

        $arquivo = self::arquivo($chave);
        if ($arquivo === null) {
            // sem acesso a disco: usa a sessao como reforco
            Session::iniciar();
            $dados = $_SESSION['rate_' . $chave] ?? null;
            return is_array($dados) ? ['n' => (int) ($dados['n'] ?? 0), 'ate' => (int) ($dados['ate'] ?? 0)] : $vazio;
        }

        if (!is_readable($arquivo)) {
            return $vazio;
        }

        $conteudo = @file_get_contents($arquivo);
        if ($conteudo === false) {
            return $vazio;
        }

        $dados = json_decode($conteudo, true);
        if (!is_array($dados)) {
            return $vazio;
        }

        return ['n' => (int) ($dados['n'] ?? 0), 'ate' => (int) ($dados['ate'] ?? 0)];
    }

    /** O IP (ou outro identificador) ja passou do limite? */
    public static function excedeu(string $chave, int $max, int $minutos): bool
    {
        $estado = self::estado($chave);

        if ($estado['ate'] < time()) {
            return false; // o bloqueio expirou
        }

        return $estado['n'] >= $max;
    }

    /** Registra uma falha e renova o bloqueio. */
    public static function falhou(string $chave, int $max, int $minutos): void
    {
        $estado = self::estado($chave);
        $agora = time();

        // se o bloqueio anterior ja venceu, a contagem recomeca
        $contagem = ($estado['ate'] >= $agora ? $estado['n'] : 0) + 1;

        self::gravar($chave, ['n' => $contagem, 'ate' => $agora + $minutos * 60]);
        self::limparExpirados();
    }

    /** Limpa o contador depois de um acerto. */
    public static function limpou(string $chave): void
    {
        $arquivo = self::arquivo($chave);

        if ($arquivo !== null && is_file($arquivo)) {
            @unlink($arquivo);
        }

        Session::iniciar();
        unset($_SESSION['rate_' . $chave]);
    }

    /** Identificador do visitante, sem gravar o IP em texto puro. */
    public static function ipChave(): string
    {
        // apenas REMOTE_ADDR: cabecalhos como X-Forwarded-For podem ser forjados
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'local');

        return hash('sha256', $ip);
    }

    // ---------------- interno ----------------

    private static function gravar(string $chave, array $dados): void
    {
        $arquivo = self::arquivo($chave);
        $json = json_encode($dados);

        if ($arquivo === null || $json === false) {
            // sem disco: guarda na sessao para nao perder a contagem
            Session::iniciar();
            $_SESSION['rate_' . $chave] = $dados;
            return;
        }

        @file_put_contents($arquivo, $json, LOCK_EX);
        @chmod($arquivo, 0640);
    }

    private static function arquivo(string $chave): ?string
    {
        if (!preg_match('/^[a-f0-9]{16,64}$/', $chave)) {
            return null; // chave inesperada: nao cria arquivo com nome estranho
        }

        $dir = Helpers::diretorioInterno('rate');

        if ($dir === sys_get_temp_dir()) {
            return null; // disco indisponivel
        }

        return $dir . '/' . $chave . '.json';
    }

    /** Apaga contadores expirados, para a pasta nao crescer sem parar. */
    private static function limparExpirados(): void
    {
        self::$limpezas++;

        // a cada 20 falhas, olha os arquivos uma vez
        if (self::$limpezas % 20 !== 1) {
            return;
        }

        $dir = Helpers::diretorioInterno('rate');
        if ($dir === sys_get_temp_dir()) {
            return;
        }

        $arquivos = @glob($dir . '/*.json');
        if ($arquivos === false) {
            return;
        }

        $agora = time();

        foreach ($arquivos as $arquivo) {
            $conteudo = @file_get_contents($arquivo);
            $ate = 0;

            if (is_string($conteudo)) {
                $dados = json_decode($conteudo, true);
                $ate = is_array($dados) ? (int) ($dados['ate'] ?? 0) : 0;
            }

            if ($ate < $agora) {
                @unlink($arquivo);
            }
        }
    }
}