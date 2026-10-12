<?php
/**
 * DeepSeek — cliente da API
 *
 * A chave NUNCA aparece no site. Ela é lida do .env, no servidor.
 * Formato compativel com a API da OpenAI:
 *   POST {base_url}/chat/completions
 *
 * Suporta function calling, com um maximo de rodadas por mensagem.
 */

declare(strict_types=1);

final class DeepSeek
{
    private const MAX_RODADAS = 4;
    private const TIMEOUT = 25;

    private static function apiKey(): string
    {
        return trim((string) Env::get('DEEPSEEK_API_KEY', ''));
    }

    public static function disponivel(): bool
    {
        if (self::apiKey() === '') {
            return false;
        }
        return self::curl() !== null;
    }

    private static function baseUrl(): string
    {
        return rtrim((string) Env::get('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'), '/');
    }

    private static function model(): string
    {
        $model = trim((string) Env::get('DEEPSEEK_MODEL', ''));
        return $model !== '' ? $model : 'deepseek-chat';
    }

    private static function curl()
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        return curl_init();
    }

    /**
     * Executa a conversa com as ferramentas da agenda.
     *
     * @param array $historico  mensagens [{role, content}]
     * @param array $estado     estado da conversa (slots, data, servico...)
     * @return array{ok:bool, texto:string, erro?:string}
     */
    public static function conversar(array $historico, array &$estado): array
    {
        if (!self::disponivel()) {
            return ['ok' => false, 'texto' => '', 'erro' => 'sem chave ou sem curl'];
        }

        $mensagens = [];
        $mensagens[] = ['role' => 'system', 'content' => Prompt::sistema()];

        foreach (array_slice($historico, -12) as $m) {
            $conteudo = (string) ($m['content'] ?? '');
            if (trim($conteudo) === '') {
                continue;
            }
            $mensagens[] = ['role' => $m['role'] === 'user' ? 'user' : 'assistant', 'content' => $conteudo];
        }

        for ($rodada = 0; $rodada < self::MAX_RODADAS; $rodada++) {
            $resposta = self::chamar($mensagens);

            if (!$resposta['ok']) {
                return ['ok' => false, 'texto' => '', 'erro' => (string) $resposta['erro']];
            }

            $escolha = $resposta['choices'][0]['message'] ?? null;
            if (!is_array($escolha)) {
                return ['ok' => false, 'texto' => '', 'erro' => 'resposta inesperada da IA'];
            }

            // a IA pediu alguma acao da agenda
            if (!empty($escolha['tool_calls']) && is_array($escolha['tool_calls'])) {
                $mensagens[] = [
                    'role'      => 'assistant',
                    'content'   => $escolha['content'] ?? null,
                    'tool_calls' => $escolha['tool_calls'],
                ];

                foreach ($escolha['tool_calls'] as $chamada) {
                    $fn = (string) ($chamada['function']['name'] ?? '');
                    $id = (string) ($chamada['id'] ?? '');
                    $args = json_decode((string) ($chamada['function']['arguments'] ?? '{}'), true);
                    $args = is_array($args) ? $args : [];

                    $resultado = Tools::executar($fn, $args, $estado);

                    AgenteLog::registrar($fn, $args, $resultado);

                    $mensagens[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $id,
                        'content'      => json_encode(
                            ['ok' => $resultado['ok'], 'resultado' => $resultado['texto']],
                            JSON_UNESCAPED_UNICODE
                        ),
                    ];
                }

                continue;
            }

            $texto = trim((string) ($escolha['content'] ?? ''));

            return [
                'ok'   => $texto !== '',
                'texto' => $texto,
                'erro' => $texto === '' ? 'resposta vazia da IA' : '',
            ];
        }

        return [
            'ok'    => false,
            'texto' => '',
            'erro'  => 'limite de rodadas de acao excedido',
        ];
    }

    /** @return array{ok:bool, choices?:array, erro?:string} */
    private static function chamar(array $mensagens): array
    {
        $payload = [
            'model'    => self::model(),
            'messages' => $mensagens,
            'tools'    => Tools::definicoes(),
            'tool_choice' => 'auto',
            'temperature'  => 0.4,
            'max_tokens'   => 400,
            'stream'       => false,
        ];

        $ch = curl_init(self::baseUrl() . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . self::apiKey(),
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $corpo = curl_exec($ch);
        $erro = curl_error($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($corpo === false || $corpo === '') {
            return ['ok' => false, 'erro' => 'conexao falhou: ' . $erro];
        }

        $json = json_decode((string) $corpo, true);

        if ($codigo !== 200 || !is_array($json)) {
            // registra o erro sem expor a chave
            $detalhe = is_array($json) ? (string) ($json['error']['message'] ?? $corpo) : $corpo;
            error_log('[DeepSeek] HTTP ' . $codigo . ': ' . substr($detalhe, 0, 300));
            return ['ok' => false, 'erro' => 'a IA respondeu com erro (' . $codigo . ')'];
        }

        return ['ok' => true, 'choices' => $json['choices'] ?? []];
    }
}