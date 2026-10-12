<?php
/**
 * Tools — acoes que a IA pode pedir ao sistema
 *
 * A IA NUNCA acessa o banco e NUNCA escreve SQL.
 * Ela apenas pede estas acoes, e o PHP valida tudo antes de gravar.
 *
 * Disponibilidade, criacao, cancelamento e remarcaçao usam sempre a
 * Agenda.php, ou seja, as mesmas regras do painel.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/agenda/Agenda.php';

final class Tools
{
    /** descricao enviada para a IA no formato de function calling */
    public static function definicoes(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'consultar_disponibilidade',
                    'description' => 'Mostra os horários livres da agenda. Chame antes de oferecer horário ao visitante. NUNCA invente horários.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'data' => [
                                'type' => 'string',
                                'description' => 'Data no formato AAAA-MM-DD. Exemplo: 2026-10-15',
                            ],
                        ],
                        'required' => ['data'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'criar_agendamento',
                    'description' => 'Cria o agendamento da avaliação. Chame SOMENTE depois que o visitante confirmou o horário.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'nome' => ['type' => 'string', 'description' => 'Nome do paciente'],
                            'telefone' => ['type' => 'string', 'description' => 'Telefone, se o visitante informar'],
                            'data' => ['type' => 'string', 'description' => 'Data no formato AAAA-MM-DD'],
                            'hora' => ['type' => 'string', 'description' => 'Horário no formato HH:MM'],
                        ],
                        'required' => ['nome', 'data', 'hora'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'cancelar_agendamento',
                    'description' => 'Cancela um agendamento existente. Confirme o nome e a data com o visitante antes.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'nome' => ['type' => 'string', 'description' => 'Nome do paciente'],
                            'data' => ['type' => 'string', 'description' => 'Data do agendamento, AAAA-MM-DD'],
                        ],
                        'required' => ['nome'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'remarcar_agendamento',
                    'description' => 'Move um agendamento para outra data ou horário. Sempre chame consultar_disponibilidade antes.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'nome' => ['type' => 'string', 'description' => 'Nome do paciente'],
                            'data_antiga' => ['type' => 'string', 'description' => 'Data atual, AAAA-MM-DD'],
                            'data' => ['type' => 'string', 'description' => 'Nova data, AAAA-MM-DD'],
                            'hora' => ['type' => 'string', 'description' => 'Novo horário, HH:MM'],
                        ],
                        'required' => ['nome', 'data', 'hora'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Executa a acao pedida pela IA.
     * @return array{ok:bool, texto:string, dados?:array}
     */
    public static function executar(string $nome, array $args, array &$estado): array
    {
        return match ($nome) {
            'consultar_disponibilidade' => self::disponibilidade($args, $estado),
            'criar_agendamento'         => self::criar($args, $estado),
            'cancelar_agendamento'      => self::cancelar($args, $estado),
            'remarcar_agendamento'      => self::remarcar($args, $estado),
            default => ['ok' => false, 'texto' => 'Ação desconhecida.'],
        };
    }

    // -------------------------------------------------------------------

    private static function disponibilidade(array $args, array &$estado): array
    {
        $data = self::normalizarData((string) ($args['data'] ?? ''));
        if ($data === null) {
            return ['ok' => false, 'texto' => 'Data inválida. Pergunte o dia ao visitante.'];
        }

        $servico = self::servicoAvaliacao();
        $duracao = (int) $servico['duracao_minutos'];

        $d = Agenda::disponibilidade($data, $duracao);

        $estado['data'] = $data;
        $estado['servico_nome'] = $servico['nome'];
        $estado['servico_id'] = (int) $servico['id'];
        $estado['duracao'] = $duracao;

        if (!$d['horario_funciona']) {
            $estado['slots'] = [];
            return [
                'ok' => false,
                'texto' => 'Não há atendimento neste dia. Escolha outro dia.',
                'dados' => ['data' => $data, 'slots' => []],
            ];
        }

        if ($d['slots'] === []) {
            $estado['slots'] = [];
            return [
                'ok' => false,
                'texto' => 'Este dia está sem horários livres. Ofereça outro dia ao visitante.',
                'dados' => ['data' => $data, 'slots' => []],
            ];
        }

        $lista = implode(', ', array_map(
            static fn (string $h): string => date('H:i', (int) strtotime($h)),
            array_slice($d['slots'], 0, 6)
        ));

        // guarda os slots: a criacao so aceita um horario que veio daqui
        $estado['slots'] = $d['slots'];

        $diaTexto = Helpers::dataExtenso($data);
        $texto = 'Horários livres em ' . $diaTexto . ' para ' . $servico['nome'] . ': ' . $lista . '.';

        return [
            'ok' => true,
            'texto' => $texto,
            'dados' => ['data' => $data, 'slots' => $d['slots'], 'servico' => $servico['nome']],
        ];
    }

    private static function criar(array $args, array &$estado): array
    {
        $nome = trim((string) ($args['nome'] ?? ''));
        $data = self::normalizarData((string) ($args['data'] ?? ''));
        $hora = self::normalizarHora((string) ($args['hora'] ?? ''));

        if (mb_strlen($nome) < 2) {
            return ['ok' => false, 'texto' => 'Falta o nome do paciente.'];
        }
        if ($data === null) {
            return ['ok' => false, 'texto' => 'Data inválida.'];
        }
        if ($hora === null) {
            return ['ok' => false, 'texto' => 'Horário inválido.'];
        }

        // o horario precisa ter vindo da consulta real, nao da conversa
        $slots = $estado['slots'] ?? [];
        $chave = $hora . ':00';

        if ($slots === [] || !in_array($chave, $slots, true)) {
            return [
                'ok' => false,
                'texto' => 'Esse horário não está mais livre. Chame consultar_disponibilidade e ofereça outro.',
            ];
        }

        $servicoId = (int) ($estado['servico_id'] ?? 0);

        // Trava: o agente marca somente avaliação. O procedimento é
        // definido pelo Dr. Maycon depois da consulta, na mão dele.
        if ($servicoId <= 0) {
            $achado = Agenda::servicoPorNome('Avaliação');
            $servicoId = $achado !== null ? (int) $achado['id'] : 0;
        }

        if ($servicoId <= 0) {
            return ['ok' => false, 'texto' => 'Serviço não encontrado.'];
        }

        $servico = Agenda::servico($servicoId);
        if ($servico === null) {
            return ['ok' => false, 'texto' => 'Serviço não encontrado.'];
        }

        $telefone = trim((string) ($args['telefone'] ?? ''));

        $resultado = Agenda::criar(
            nomePaciente: $nome,
            servicoId: (int) $servico['id'],
            dataIso: $data,
            horaInicio: $chave,
            telefone: $telefone !== '' ? $telefone : null,
            origem: 'ia'
        );

        if (!$resultado['ok']) {
            return ['ok' => false, 'texto' => (string) $resultado['erro']];
        }

        $estado['slots'] = [];
        $estado['agendamento_id'] = (int) $resultado['id'];
        $estado['nome'] = $nome;

        $texto = 'Agendamento criado: ' . $nome . ', '
            . Helpers::dataExtenso($data) . ' às ' . date('H:i', (int) strtotime($chave))
            . ' para ' . $servico['nome'] . '.';

        return ['ok' => true, 'texto' => $texto, 'dados' => ['id' => (int) $resultado['id']]];
    }

    private static function cancelar(array $args, array &$estado): array
    {
        $nome = trim((string) ($args['nome'] ?? ''));

        if (mb_strlen($nome) < 2) {
            return ['ok' => false, 'texto' => 'Preciso do nome para localizar o agendamento.'];
        }

        $ag = Agenda::proximoDoPaciente($nome);

        if ($ag === null) {
            return ['ok' => false, 'texto' => 'Não encontrei agendamento ativo para ' . $nome . '.'];
        }

        $resultado = Agenda::cancelar((int) $ag['id']);

        if (!$resultado['ok']) {
            return ['ok' => false, 'texto' => (string) $resultado['erro']];
        }

        unset($estado['agendamento_id']);

        $texto = 'Agendamento cancelado: ' . Helpers::dataExtenso((string) $ag['data'])
            . ' às ' . date('H:i', (int) strtotime((string) $ag['hora_inicio'])) . '.';

        return ['ok' => true, 'texto' => $texto];
    }

    private static function remarcar(array $args, array &$estado): array
    {
        $nome = trim((string) ($args['nome'] ?? ''));
        $data = self::normalizarData((string) ($args['data'] ?? ''));
        $hora = self::normalizarHora((string) ($args['hora'] ?? ''));

        if (mb_strlen($nome) < 2) {
            return ['ok' => false, 'texto' => 'Preciso do nome para localizar o agendamento.'];
        }
        if ($data === null || $hora === null) {
            return ['ok' => false, 'texto' => 'Nova data ou horário inválido.'];
        }

        $ag = Agenda::proximoDoPaciente($nome);

        if ($ag === null) {
            return ['ok' => false, 'texto' => 'Não encontrei agendamento ativo para ' . $nome . '.'];
        }

        $resultado = Agenda::remarcar((int) $ag['id'], $data, $hora . ':00');

        if (!$resultado['ok']) {
            return ['ok' => false, 'texto' => (string) $resultado['erro']];
        }

        // o agendamento e o mesmo: nao pode existir nenhum outro
        // para o mesmo paciente na mesma data
        $outros = (int) Database::valor(
            'SELECT COUNT(*) FROM agendamentos
              WHERE paciente_id = ? AND data = ? AND id <> ?
                AND (status = "confirmado"
                     OR (status = "concluido" AND TIMESTAMP(data, hora_fim) >= NOW()))',
            [(int) $ag['paciente_id'], $data, (int) $ag['id']],
            0
        );

        if ($outros > 0) {
            // volta atrás para não deixar marcação duplicada
            Agenda::remarcar((int) $ag['id'], (string) $ag['data'], substr((string) $ag['hora_inicio'], 0, 5));

            return [
                'ok'    => false,
                'texto' => 'Você já tem outro horário marcado nessa data. Me diga qual deles deve mudar.',
            ];
        }

        $estado['ag_cancelamento'] = null;
        $estado['acao_cancelamento'] = '';

        $texto = 'Agendamento remarcado para ' . Helpers::dataExtenso($data) . ' às ' . $hora . '.';

        return ['ok' => true, 'texto' => $texto];
    }

    // -------------------------------------------------------------------

    /** O agente agenda apenas avaliação: não existe escolha de serviço aqui. */
    private static function servicoAvaliacao(): array
    {
        $avaliacao = Agenda::servicoPorNome('Avaliação');
        if ($avaliacao !== null) {
            return $avaliacao;
        }

        return ['id' => 1, 'nome' => 'Avaliação', 'duracao_minutos' => 30, 'ativo' => 1];
    }

    /** aceita 2026-10-15, 15/10/2026 e "sexta" não entra aqui */
    public static function normalizarData(string $texto): ?string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $texto, $m) === 1) {
            $data = "{$m[1]}-{$m[2]}-{$m[3]}";
            return Helpers::dataValida($data) ? $data : null;
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $texto, $m) === 1) {
            $data = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            return Helpers::dataValida($data) ? $data : null;
        }

        $ts = strtotime($texto);
        if ($ts !== false && preg_match('/[a-zA-Zà-ú]/', $texto) === 1) {
            return date('Y-m-d', $ts);
        }

        return null;
    }

    /** aceita 16, 16:00, 16h, "16:30" */
    public static function normalizarHora(string $texto): ?string
    {
        $texto = Knowledge::normalizar($texto);
        $texto = str_replace('h', '', $texto);
        $texto = trim($texto);

        if (preg_match('/^(\d{1,2})\s*[: ]\s*(\d{2})$/', $texto, $m) === 1) {
            $h = (int) $m[1];
            $mi = (int) $m[2];
            return ($h <= 23 && $mi <= 59) ? sprintf('%02d:%02d', $h, $mi) : null;
        }

        if (preg_match('/^(\d{1,2})$/', $texto, $m) === 1) {
            $h = (int) $m[1];
            return $h <= 23 ? sprintf('%02d:00', $h) : null;
        }

        return null;
    }
}