<?php
/**
 * Gemini — cliente da API (TESTE)
 *
 * Espelho do DeepSeek.php, com a mesma assinatura publica:
 *   conversar(array $historico, array &$estado): array{ok, texto, erro?}
 *
 * Apenas INTERPRETA e CONVERSA. Toda acao da agenda passa por
 * Tools::executar(), que valida e grava. Este arquivo nao escreve
 * SQL, nao consulta a agenda e nao invenca horario.
 *
 * A chave NUNCA aparece no site. Ela e lida do .env, no servidor.
 * Formato da API do Gemini (Google AI Studio / Generative Language):
 *   POST {base}/models/{model}:generateContent
 *
 * Para voltar a DeepSeek, troque AI_MODO no .env. Este arquivo fica
 * inerte enquanto AI_MODO nao for "gemini".
 */

declare(strict_types=1);

final class Gemini
{
    private const MAX_RODADAS = 4;

    /** Consultas de agenda aceitas por turno do modelo. Acima disso, o
     *  Gemini dispara uma consulta por dia e a conversa vira "não há
     *  horário" até estourar o limite de rodadas. */
    private const CONSULTAS_POR_RODADA = 2;

    /* A resposta chega entre 2 e 4 segundos. Com 25s, uma chamada
       travada deixava o visitante esperando meio minuto antes de cair
       no respondedor local. 12s ainda sobra folga para o pior caso
       normal e corta a espera na hora em que a API esta fora do ar. */
    private const TIMEOUT = 12;

    /** Quantas vezes ainda pode repetir a chamada numa falha de rede. */
    private static $tentativas = 0;

    private static function apiKey(): string
    {
        return trim((string) Env::get('GEMINI_API_KEY', ''));
    }

    private static function baseUrl(): string
    {
        $base = trim((string) Env::get('GEMINI_BASE_URL', ''));
        if ($base === '') {
            $base = 'https://generativelanguage.googleapis.com/v1beta';
        }
        return rtrim($base, '/');
    }

    private static function model(): string
    {
        $model = trim((string) Env::get('GEMINI_MODEL', ''));
        return $model !== '' ? $model : 'gemini-flash-lite-latest';
    }

    public static function disponivel(): bool
    {
        if (self::apiKey() === '') {
            return false;
        }
        return function_exists('curl_init');
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

        // uma nova repetição liberada para cada mensagem do visitante
        self::$tentativas = 2;

        // Conteudo da conversa, no formato do Gemini.
        // O system prompt vai em "systemInstruction", separado.
        $conteudo = [];

        foreach (array_slice($historico, -12) as $m) {
            $papel = ($m['role'] ?? 'user') === 'user' ? 'user' : 'model';
            $texto = trim((string) ($m['content'] ?? ''));
            if ($texto === '') {
                continue;
            }
            $conteudo[] = ['role' => $papel, 'parts' => [['text' => $texto]]];
        }

        for ($rodada = 0; $rodada < self::MAX_RODADAS; $rodada++) {
            $resposta = self::chamar($conteudo);

            if (!$resposta['ok']) {
                return ['ok' => false, 'texto' => '', 'erro' => (string) $resposta['erro']];
            }

            $candidato = $resposta['candidato'] ?? null;
            $partes = is_array($candidato) ? (array) ($candidato['content']['parts'] ?? []) : [];

            if ($partes === []) {
                return ['ok' => false, 'texto' => '', 'erro' => 'resposta inesperada da IA'];
            }

            $pedidos = [];
            $textoRetorno = '';

            foreach ($partes as $parte) {
                if (!is_array($parte)) {
                    continue;
                }
                /* Raciocínio interno do modelo (partes "thought"). Vai para
                   o histórico, porque a assinatura precisa voltar intacta,
                   mas nunca para o visitante. */
                if (!empty($parte['thought'])) {
                    continue;
                }
                // o modelo pediu uma acao da agenda
                if (!empty($parte['functionCall']) && is_array($parte['functionCall'])) {
                    $pedidos[] = $parte['functionCall'];
                } elseif (isset($parte['text'])) {
                    $textoRetorno .= (string) $parte['text'];
                }
            }

            if ($pedidos !== []) {
                // Repoe o turno do modelo com as parts ORIGINAIS, na
                // ordem em que vieram. A API do Gemini exige que a
                // thoughtSignature de cada part seja devolvida intacta;
                // reconstruir as parts quebra a cadeia e gera HTTP 400.
                $conteudo[] = ['role' => 'model', 'parts' => $partes];

                $executadas = 0;

                foreach ($pedidos as $p) {
                    $fn = (string) ($p['name'] ?? '');
                    $args = (array) ($p['args'] ?? []);

                    /* O modelo às vezes dispara várias consultas de uma vez
                       (uma por dia, até bater o limite de rodadas e a
                       conversa virar "não há horário"). Duas por turno é o
                       suficiente; o resto volta como recado, sem tocar no
                       banco. */
                    if ($executadas >= self::CONSULTAS_POR_RODADA) {
                        $resultado = [
                            'ok' => false,
                            'texto' => 'Uma consulta por mensagem: consulte uma data por vez e fale com a pessoa antes de consultar outra.',
                        ];
                    } else {
                        $resultado = Tools::executar($fn, $args, $estado);
                        AgenteLog::registrar($fn, $args, $resultado);
                        $executadas++;
                    }

                    $conteudo[] = [
                        'role'  => 'user',
                        'parts' => [[
                            'functionResponse' => [
                                'name'     => $fn,
                                'response' => [
                                    'ok'        => $resultado['ok'],
                                    'resultado' => $resultado['texto'],
                                ],
                            ],
                        ]],
                    ];
                }

                continue;
            }

            $texto = trim($textoRetorno);

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

    /**
     * Converte as definicoes da Tools (formato OpenAI) para o formato
     * do Gemini: achata {type:function, function:{...}} em {name,...}.
     */
    private static function definicoesGemini(): array
    {
        $saida = [];

        foreach (Tools::definicoes() as $d) {
            $fn = (array) ($d['function'] ?? []);
            if (($fn['name'] ?? '') === '') {
                continue;
            }
            $saida[] = [
                'name'        => (string) $fn['name'],
                'description' => (string) ($fn['description'] ?? ''),
                'parameters'  => (array) ($fn['parameters'] ?? []),
            ];
        }

        return $saida;
    }

    /** @return array{ok:bool, candidato?:array, erro?:string} */
    private static function chamar(array $conteudo): array
    {
        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => Prompt::sistema()]],
            ],
            'contents' => $conteudo,
            'tools'    => [
                ['functionDeclarations' => self::definicoesGemini()],
            ],
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 900,
                /* Sem isso, os modelos 2.5 gastam boa parte do orçamento de
                   tokens pensando antes de responder e a frase chega
                   cortada no meio ("Qual ."). */
                'thinkingConfig'  => ['thinkingBudget' => 0],
            ],
        ];

        $url = self::baseUrl() . '/models/' . rawurlencode(self::model()) . ':generateContent';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . self::apiKey(),
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $corpo = curl_exec($ch);
        $erro = curl_error($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = ($corpo !== false && $corpo !== '') ? json_decode((string) $corpo, true) : null;

        /* 429 (cota) e 5xx sao passageiros: o log encheu de "a IA
           respondeu com erro (429)" em sequência. Uma segunda tentativa
           com espera resolve a maioria; a terceira cobre o 503. */
        if ($codigo === 429 || $codigo >= 500) {
            if (self::$tentativas-- > 0) {
                usleep(600000);
                return self::chamar($conteudo);
            }
        }

        if ($corpo === false || $corpo === '') {
            /* A rede daqui para o Google derruba conexoes de vez em
               quando: o mesmo payload de 7 tokens responde em 2s numa
               hora e trava 45s na outra. Uma segunda tentativa resolve a
               maioria dos sustos, e como a conexao morreu cedo (nao
               deu 12s de espera), repetir nao agrava a demora. */
            if (self::$tentativas-- > 0) {
                usleep(250000);
                return self::chamar($conteudo);
            }

            return ['ok' => false, 'erro' => 'conexao falhou: ' . $erro];
        }

        if ($codigo !== 200 || !is_array($json)) {
            // registra o erro sem expor a chave
            $detalhe = is_array($json)
                ? (string) ($json['error']['message'] ?? $corpo)
                : $corpo;
            error_log('[Gemini] HTTP ' . $codigo . ': ' . substr($detalhe, 0, 300));
            return ['ok' => false, 'erro' => 'a IA respondeu com erro (' . $codigo . ')'];
        }

        $candidato = $json['candidates'][0] ?? null;

        if (is_array($candidato) && ($candidato['finishReason'] ?? '') === 'MAX_TOKENS') {
            error_log('[Gemini] resposta cortada por maxOutputTokens: ' . substr((string) ($candidato['content']['parts'][0]['text'] ?? ''), 0, 200));
        }

        return ['ok' => true, 'candidato' => $candidato];
    }
}

