<?php
/**
 * ChatService — o cerebro por tras do /api/chat.php
 *
 * Escolhe o modo conforme o .env:
 *   desligado → só oferece atendimento humano
 *   local     → respondedor local (sem chave, sem internet)
 *   deepseek  → API da DeepSeek com function calling
 *   auto      → DeepSeek se houver chave, senão local
 *
 * O historico fica na sessao do visitante. No banco so entram os
 * agendamentos, como combinado.
 */

declare(strict_types=1);

final class ChatService
{
    private const MAX_MENSAGENS = 40;
    private const MAX_CARACTERES = 600;

    /** @return array{ok:bool, texto:string, modo:string} */
    public static function responder(string $mensagem): array
    {
        $modo = self::modo();

        if ($modo === 'desligado') {
            return [
                'ok'   => true,
                'texto' => 'Prefiro te encaminhar para o Dr. Maycon 😊 O WhatsApp é ' . Knowledge::TELEFONE . '.',
                'modo' => 'desligado',
            ];
        }

        if (trim($mensagem) === '') {
            return ['ok' => false, 'texto' => '', 'modo' => $modo];
        }

        if (mb_strlen($mensagem) > self::MAX_CARACTERES) {
            $mensagem = mb_substr($mensagem, 0, self::MAX_CARACTERES);
        }

        $estado = self::estado();
        $ag = Session::iniciar();
        $historico = $_SESSION['chat_historico'] ?? [];

        // anexa a mensagem do visitante antes de processar
        $historico[] = ['role' => 'user', 'content' => $mensagem];

        if ($modo === 'deepseek' && DeepSeek::disponivel()) {
            $resultado = DeepSeek::conversar($historico, $estado);

            if ($resultado['ok']) {
                self::gravar($historico, ['role' => 'assistant', 'content' => $resultado['texto']]);
                self::gravarEstado($estado);
                AgenteLog::mensagem(
                    'modo=deepseek resposta=' . self::resumir($resultado['texto']),
                    (string) ($estado['nome'] ?? '')
                );

                return ['ok' => true, 'texto' => $resultado['texto'], 'modo' => 'deepseek'];
            }

            // a IA falhou: o visitante nao fica sem resposta
            AgenteLog::mensagem(
                'deepseek falhou: ' . (string) $resultado['erro'],
                (string) ($estado['nome'] ?? '')
            );
        }

        $resultado = Fallback::responder($mensagem, $estado);

        self::gravar($historico, ['role' => 'assistant', 'content' => $resultado['texto']]);
        self::gravarEstado($estado);

        return ['ok' => true, 'texto' => $resultado['texto'], 'modo' => 'local'];
    }

    public static function modo(): string
    {
        $modo = strtolower(trim((string) Env::get('AI_MODO', 'auto')));
        $modo = strtr($modo, ['à' => 'a', 'ã' => 'a', 'á' => 'a']);

        $validos = ['auto', 'deepseek', 'local', 'desligado'];
        if (!in_array($modo, $validos, true)) {
            $modo = 'auto';
        }

        if ($modo === 'auto') {
            return DeepSeek::disponivel() ? 'deepseek' : 'local';
        }

        if ($modo === 'deepseek' && !DeepSeek::disponivel()) {
            return 'local';
        }

        return $modo;
    }

    /** primeira mensagem, ja com a pergunta de abertura */
    public static function saudacaoInicial(): string
    {
        $dias = Knowledge::textoDias();
        $extra = $dias !== '' ? ' O atendimento é ' . mb_strtolower($dias) . '.' : '';

        return 'Olá 😊 Eu sou a assistente do consultório do Dr. Maycon Gomes.'
            . $extra
            . ' Ainda fica alguma dúvida, ou você quer marcar uma avaliação?';
    }

    // ---------------- estado e historico ----------------

    private static function estado(): array
    {
        Session::iniciar();
        $estado = $_SESSION['chat_estado'] ?? null;

        if (!is_array($estado)) {
            $estado = Fallback::estadoInicial();
        }

        return array_merge(Fallback::estadoInicial(), $estado);
    }

    private static function gravarEstado(array $estado): void
    {
        Session::iniciar();
        $_SESSION['chat_estado'] = $estado;
    }

    private static function gravar(array $historico, array $nova): void
    {
        Session::iniciar();
        $historico[] = $nova;

        if (count($historico) > self::MAX_MENSAGENS) {
            $historico = array_slice($historico, -self::MAX_MENSAGENS);
        }

        $_SESSION['chat_historico'] = $historico;
    }

    public static function reiniciar(): void
    {
        Session::iniciar();
        unset($_SESSION['chat_historico'], $_SESSION['chat_estado']);
    }

    private static function resumir(string $texto, int $limite = 120): string
    {
        $texto = preg_replace('/\s+/', ' ', trim($texto)) ?? $texto;
        return mb_strlen($texto) > $limite ? mb_substr($texto, 0, $limite) . '…' : $texto;
    }
}