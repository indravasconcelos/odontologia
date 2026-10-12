<?php
/**
 * AgenteLog — registro das acoes do assistente
 *
 * Tudo que o agente faz fica gravado em logs/agente.log.
 * Serve para conferir, depois da apresentação, se algo saiu do
 * previsto — e para auditar qualquer agendamento feito pelo chat.
 *
 * A pasta logs/ fica bloqueada pelo .htaccess: nunca e servida pela web.
 */

declare(strict_types=1);

final class AgenteLog
{
    /**
     * Conectivos que fazem parte do sobrenome e nao precisam ser mascarados.
     * Nomes brasileiros usam muito "da Silva", "dos Santos", "e Silva".
     */
    private const CONECTIVOS = ['de', 'da', 'do', 'das', 'dos', 'e'];

    /**
     * @param string $nomePaciente nome informado pelo visitante, usado para
     *                             mascarar o sobrenome antes de gravar
     */
    public static function registrar(
        string $acao,
        array $args,
        array $resultado,
        string $nomePaciente = ''
    ): void {
        $linha = sprintf(
            "[%s] acao=%s args=%s ok=%s resultado=%s",
            date('d/m/Y H:i:s'),
            $acao,
            self::encurtar(json_encode($args, JSON_UNESCAPED_UNICODE) ?: '', $nomePaciente),
            ($resultado['ok'] ?? false) ? 'sim' : 'nao',
            self::encurtar((string) ($resultado['texto'] ?? ''), $nomePaciente)
        );

        self::escrever($linha . PHP_EOL);
    }

    public static function mensagem(string $texto, string $nomePaciente = ''): void
    {
        $linha = self::encurtar($texto, $nomePaciente, 1000);
        self::escrever('[' . date('d/m/Y H:i:s') . '] ' . $linha . PHP_EOL);
    }

    /**
     * Mascara o sobrenome, mantendo o primeiro nome.
     *
     *   "Renata Souza"        -> "Renata S***"
     *   "Joao da Silva"       -> "Joao da S***"
     *   "Ana Souza Lima"      -> "Ana S*** L***"
     *   "Renata"              -> "Renata"  (nao ha sobrenome)
     *
     * O primeiro nome continua legivel para dar rastreio, mas o sobrenome
     * — que tambem e dado pessoal — nunca e gravado em texto puro.
     */
    public static function mascaraNome(string $nome): string
    {
        $nome = trim(preg_replace('/\s+/', ' ', $nome) ?? $nome);

        if ($nome === '') {
            return '';
        }

        $partes = explode(' ', $nome);

        // apenas primeiro nome: nao ha o que mascarar
        if (count($partes) < 2) {
            return $nome;
        }

        $primeiro = array_shift($partes);
        $saida = [$primeiro];

        foreach ($partes as $parte) {
            if (in_array(mb_strtolower($parte), self::CONECTIVOS, true)) {
                $saida[] = mb_strtolower($parte);
                continue;
            }

            $saida[] = mb_substr($parte, 0, 1) . '***';
        }

        return implode(' ', $saida);
    }

    /**
     * Troca o nome completo pela versao mascarada dentro de um texto.
     *
     * A troca e feita por palavra, do mais longo para o mais curto, para
     * que o nome com sobrenome seja substituido antes do primeiro nome
     * sozinho (que nao tem como ser mascarado).
     */
    private static function substituirNome(string $texto, string $nomePaciente): string
    {
        $nomePaciente = trim($nomePaciente);

        if ($texto === '' || $nomePaciente === '') {
            return $texto;
        }

        $mascarado = self::mascaraNome($nomePaciente);

        // so vale a pena se mudou de verdade (havia sobrenome)
        if ($mascarado === $nomePaciente) {
            return $texto;
        }

        // Case-insensitive, e tambem sem acento, porque a mensagem pode
        // ter passado por mb_strtolower e perdido a forma original
        $variantes = [$nomePaciente, $mascarado, mb_strtolower($nomePaciente)];
        foreach ($variantes as $v) {
            $texto = str_ireplace($v, $mascarado, $texto);
        }

        return $texto;
    }

    private static function encurtar(string $texto, string $nomePaciente = '', int $limite = 220): string
    {
        $texto = self::substituirNome($texto, $nomePaciente);
        $texto = preg_replace('/\s+/', ' ', trim($texto)) ?? $texto;

        return mb_strlen($texto) > $limite ? mb_substr($texto, 0, $limite) . '…' : $texto;
    }

    private static function escrever(string $linha): void
    {
        // pasta "logs" da raiz: existe na hospedagem e no ambiente local
        $dir = Helpers::diretorioInterno();

        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }

        @file_put_contents($dir . '/agente.log', $linha, FILE_APPEND | LOCK_EX);
    }
}