<?php
/**
 * Agenda — regras de negocio
 *
 * IMPORTANTE
 * A disponibilidade e SEMPRE calculada aqui, no PHP.
 * O JavaScript do site apenas exibe o que este arquivo devolveu.
 * A validacao final acontece de novo, dentro da transacao,
 * imediatamente antes de gravar no MySQL.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

final class Agenda
{
    /** @return array<int,array{id:int,nome:string,duracao_minutos:int}> */
    public static function servicosAtivos(bool $somenteAgendaveis = true): array
    {
        $sql = 'SELECT id, nome, duracao_minutos
                  FROM servicos
                 WHERE ativo = 1'
            . ($somenteAgendaveis ? ' AND duracao_minutos > 0' : '')
                . ' ORDER BY nome';

        return Database::todos($sql);
    }

    public static function servico(int $id): ?array
    {
        return Database::primeiro(
            'SELECT id, nome, duracao_minutos, ativo FROM servicos WHERE id = ? LIMIT 1',
            [$id]
        );
    }

    /** servico pelo nome, usado pelo agente ao interpretar a conversa */
    public static function servicoPorNome(string $nome): ?array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return null;
        }

        $servicos = self::servicosAtivos(false);

        $alvo = self::normalizar($nome);
        foreach ($servicos as $s) {
            if (self::normalizar($s['nome']) === $alvo) {
                return $s;
            }
        }

        // correspondencia parcial: "aparelho" -> "aparelhos ortopedicos"
        $melhor = null;
        $maior = 0;
        foreach ($servicos as $s) {
            $alvoServico = self::normalizar($s['nome']);
            if ($alvoServico !== '' && (str_contains($alvoServico, $alvo) || str_contains($alvo, $alvoServico))) {
                $tamanho = min(mb_strlen($alvoServico), mb_strlen($alvo));
                if ($tamanho > $maior) {
                    $maior = $tamanho;
                    $melhor = $s;
                }
            }
        }

        return $melhor;
    }

    private static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e',
            'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c']);
        return preg_replace('/\s+/', ' ', $texto) ?? $texto;
    }

    public static function pacientePorNome(string $nome): ?array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return null;
        }
        return Database::primeiro(
            'SELECT id, nome, telefone, email FROM pacientes WHERE nome = ? LIMIT 1',
            [$nome]
        );
    }

    public static function buscarOuCriarPaciente(string $nome, ?string $telefone = null): array
    {
        $nome = trim($nome);
        $existente = self::pacientePorNome($nome);

        if ($existente !== null) {
            if ($telefone !== null && Helpers::telefoneValido($telefone)) {
                Database::query(
                    'UPDATE pacientes SET telefone = ? WHERE id = ? AND (telefone IS NULL OR telefone = ?)',
                    [Helpers::telefone($telefone), $existente['id'], Helpers::telefone($telefone)]
                );
                $existente['telefone'] = Helpers::telefone($telefone);
            }
            return ['id' => (int) $existente['id'], 'novo' => false];
        }

        $tel = ($telefone !== null && Helpers::telefoneValido($telefone)) ? Helpers::telefone($telefone) : null;

        $id = Database::inserir(
            'INSERT INTO pacientes (nome, telefone) VALUES (?, ?)',
            [$nome, $tel]
        );

        return ['id' => $id, 'novo' => true];
    }

    // ---------------------------------------------------------------
    //  DISPONIBILIDADE
    // ---------------------------------------------------------------

    /**
     * @return array{
     *   data:string, servico:array, dia_semana:int, faixas:array,
     *   slots:array<int,string>, horario_funciona:bool
     * }
     */
    public static function disponibilidade(string $dataIso, int $duracaoMinutos): array
    {
        $ts = strtotime($dataIso);
        if ($ts === false) {
            throw new InvalidArgumentException('Data inválida.');
        }

        $dataIso = date('Y-m-d', $ts);
        $diaSemana = (int) date('w', $ts);

        $faixas = Database::todos(
            'SELECT id, hora_inicio, hora_fim
               FROM horarios
              WHERE dia_semana = ? AND ativo = 1
              ORDER BY hora_inicio',
            [$diaSemana]
        );

        $resultado = [
            'data'            => $dataIso,
            'dia_semana'      => $diaSemana,
            'faixas'          => $faixas,
            'slots'           => [],
            'horario_funciona'=> count($faixas) > 0,
            'servico'         => [],
        ];

        if ($faixas === [] || $duracaoMinutos < 5) {
            return $resultado;
        }

        $agora = time();
        $antecedencia = Env::int('AGENDA_ANTECEDENCIA', 120);
        $limiteDias = Env::int('AGENDA_DIAS_A_FUTURO', 60);

        $meioDia = strtotime($dataIso . ' 12:00:00');
        if ($meioDia === false) {
            return $resultado;
        }
        if ($meioDia + 86400 < $agora) {
            return $resultado; // data passada
        }
        if ($meioDia - $agora > $limiteDias * 86400) {
            return $resultado; // longe demais
        }

        // minutos ja ocupados: bloqueios + agendamentos ativos
        $ocupados = self::minutosOcupados($dataIso);

        $slots = [];
        foreach ($faixas as $faixa) {
            $inicio = Helpers::horaParaMinutos($faixa['hora_inicio']);
            $fim = Helpers::horaParaMinutos($faixa['hora_fim']);

            for ($min = $inicio; $min + $duracaoMinutos <= $fim; $min += $duracaoMinutos) {
                $fimSlot = $min + $duracaoMinutos;

                if (self::intersecta($min, $fimSlot, $ocupados)) {
                    continue;
                }

                $tsSlot = strtotime($dataIso . ' ' . Helpers::minutosParaHora($min));
                if ($tsSlot === false) {
                    continue;
                }
                if ($tsSlot < ($agora + $antecedencia * 60)) {
                    continue;
                }

                $slots[] = Helpers::minutosParaHora($min) . ':00';
            }
        }

        // faixas sobrepostas (ex.: 08:00-12:00 e 09:00-11:00) geram os
        // mesmos horarios varias vezes: sem esta uniques, a lista do
        // painel mostrava 15:00, 16:00 repetidos.
        $slots = array_values(array_unique($slots));
        sort($slots);

        $resultado['slots'] = $slots;

        return $resultado;
    }

    /** @return array<int,array{ini:int,fim:int}> */
    private static function minutosOcupados(string $dataIso): array
    {
        $ocupados = [];

        $bloqueios = Database::todos(
            'SELECT hora_inicio, hora_fim FROM bloqueios WHERE data = ?',
            [$dataIso]
        );
        foreach ($bloqueios as $b) {
            $ocupados[] = [
                'ini' => Helpers::horaParaMinutos($b['hora_inicio']),
                'fim' => Helpers::horaParaMinutos($b['hora_fim']),
            ];
        }

        // REGRA DE CONFLITO (vale para os 4 pontos que checam ocupacao):
        //   - 'confirmado' bloqueia sempre;
        //   - 'concluido' bloqueia apenas se o atendimento ainda NAO aconteceu.
        //     Depois que a hora passou, o horario volta a ficar livre para o
        //     Dr. marcar outro paciente no mesmo horario sem poluir a agenda.
        //   - 'cancelado' e 'faltou' nunca bloqueiam.
        // Se status for apenas 'confirmado' ou 'concluido' com hora_fim
        // ainda no futuro, o registro ocupa o horario.
        $agendamentos = Database::todos(
            "SELECT hora_inicio, hora_fim
               FROM agendamentos
              WHERE data = ?
                AND (status = 'confirmado'
                     OR (status = 'concluido' AND TIMESTAMP(data, hora_fim) >= NOW()))",
            [$dataIso]
        );
        foreach ($agendamentos as $a) {
            $ocupados[] = [
                'ini' => Helpers::horaParaMinutos($a['hora_inicio']),
                'fim' => Helpers::horaParaMinutos($a['hora_fim']),
            ];
        }

        return $ocupados;
    }

    /** sobreposicao de intervalos [ini1,fim1) e [ini2,fim2) */
    public static function intersecta(int $ini1, int $fim1, array $intervalos): bool
    {
        foreach ($intervalos as $i) {
            if ($ini1 < $i['fim'] && $fim1 > $i['ini']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verificacao definitiva, feita dentro da transacao e logo antes
     * do INSERT. Barra a corrida entre dois pedidos simultaneos.
     *
     * $ignorarBloqueios = true (somente no modo excepcional do painel):
     * ignora a tabela de bloqueios, mas NUNCA permite dois atendimentos
     * no mesmo horario.
     */
    public static function slotLivre(string $dataIso, string $horaIni, string $horaFim, bool $ignorarBloqueios = false): bool
    {
        $pdo = Database::conexao();

        // trava as linhas do dia para serializar agendamentos simultaneos
        $pdo->prepare('SELECT id FROM agendamentos WHERE data = ? FOR UPDATE')->execute([$dataIso]);

        $ini = Helpers::horaParaMinutos($horaIni);
        $fim = Helpers::horaParaMinutos($horaFim);

        // mesma regra de conflito: 'concluido' so ocupa enquanto a hora nao passou
        $conflito = Database::valor(
            "SELECT COUNT(*) FROM agendamentos
              WHERE data = ?
                AND (status = 'confirmado'
                     OR (status = 'concluido' AND TIMESTAMP(data, hora_fim) >= NOW()))
                AND hora_inicio < ?
                AND hora_fim > ?",
            [$dataIso, $horaFim, $horaIni],
            0
        );

        if ((int) $conflito > 0) {
            return false;
        }

        if ($ignorarBloqueios) {
            return true;
        }

        $bloqueado = Database::valor(
            'SELECT COUNT(*) FROM bloqueios
              WHERE data = ?
                AND hora_inicio < ?
                AND hora_fim > ?',
            [$dataIso, $horaFim, $horaIni],
            0
        );

        return (int) $bloqueado === 0;
    }

    /** horario digitado pelo dentista: 00:00 ate 23:59 */
    public static function horaValida(string $hora): bool
    {
        return preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](:00)?$/', trim($hora)) === 1;
    }

    /** data real do calendario (existe) e nao esta no passado */
    public static function dataUtil(string $dataIso): bool
    {
        if (!Helpers::dataValida($dataIso)) {
            return false;
        }
        return $dataIso >= date('Y-m-d');
    }

    public static function dentroDoHorario(string $dataIso, string $horaIni, string $horaFim): bool
    {
        $diaSemana = (int) date('w', (int) strtotime($dataIso));

        $faixas = Database::todos(
            'SELECT hora_inicio, hora_fim FROM horarios WHERE dia_semana = ? AND ativo = 1',
            [$diaSemana]
        );

        $ini = Helpers::horaParaMinutos($horaIni);
        $fim = Helpers::horaParaMinutos($horaFim);

        foreach ($faixas as $f) {
            $fi = Helpers::horaParaMinutos($f['hora_inicio']);
            $ff = Helpers::horaParaMinutos($f['hora_fim']);
            if ($ini >= $fi && $fim <= $ff) {
                return true;
            }
        }

        return false;
    }

    // ---------------------------------------------------------------
    //  GRAVACAO
    // ---------------------------------------------------------------

    /**
     * Cria um agendamento.
     *
     * $excepcional = true, usado apenas pelo painel:
     *   ignora o expediente, a antecedencia minima e o limite de dias,
     *   permitindo agendar qualquer dia e fora do horário de atendimento.
     *   Mesmo assim nunca permite dois atendimentos no mesmo horário.
     *
     * @return array{ok:bool, erro?:string, id?:int}
     */
    public static function criar(
        string $nomePaciente,
        int $servicoId,
        string $dataIso,
        string $horaInicio,
        ?string $telefone = null,
        string $origem = 'admin',
        ?string $observacoes = null,
        bool $excepcional = false
    ): array {
        $nomePaciente = trim($nomePaciente);
        $servico = self::servico($servicoId);

        if (mb_strlen($nomePaciente) < 2) {
            return ['ok' => false, 'erro' => 'Informe o nome do paciente.'];
        }
        if ($servico === null || !$servico['ativo']) {
            return ['ok' => false, 'erro' => 'Serviço não encontrado.'];
        }
        if (!self::dataUtil($dataIso)) {
            return ['ok' => false, 'erro' => 'Escolha uma data válida, a partir de hoje.'];
        }
        if (!self::horaValida($horaInicio)) {
            return ['ok' => false, 'erro' => 'Horário inválido.'];
        }

        $duracao = (int) $servico['duracao_minutos'];
        $horaFim = Helpers::somaMinutos($horaInicio, $duracao);

        if (!$excepcional) {
            if (!self::dentroDoHorario($dataIso, $horaInicio, $horaFim)) {
                return ['ok' => false, 'erro' => 'Este horário está fora do expediente. Marque como atendimento fora do expediente para agender mesmo assim.'];
            }

            $tsSlot = strtotime($dataIso . ' ' . $horaInicio);
            if ($tsSlot === false) {
                return ['ok' => false, 'erro' => 'Horário inválido.'];
            }
            if ($tsSlot < time() + Env::int('AGENDA_ANTECEDENCIA', 120) * 60) {
                return ['ok' => false, 'erro' => 'Este horário está muito próximo. Escolha outro.'];
            }
            if ($tsSlot > time() + Env::int('AGENDA_DIAS_A_FUTURO', 60) * 86400) {
                return ['ok' => false, 'erro' => 'Esta data está longe demais. Escolha uma data próxima.'];
            }
        }

        try {
            $id = Database::transacao(static function (PDO $pdo) use (
                $nomePaciente,
                $servicoId,
                $dataIso,
                $horaInicio,
                $horaFim,
                $origem,
                $observacoes,
                $telefone,
                $excepcional
            ): int {
                // checagem definitiva com trava
                if (!self::slotLivre($dataIso, $horaInicio, $horaFim, $excepcional)) {
                    throw new RuntimeException('Este horário acabou de ser ocupado. Escolha outro.');
                }

                $paciente = self::buscarOuCriarPaciente($nomePaciente, $telefone);

                $obs = $observacoes;
                if ($excepcional) {
                    $obs = trim(($obs !== null ? $obs . ' | ' : '') . 'Fora do expediente');
                }

                $pdo->prepare(
                    'INSERT INTO agendamentos
                       (paciente_id, servico_id, data, hora_inicio, hora_fim, status, origem, observacoes)
                     VALUES (?, ?, ?, ?, ?, "confirmado", ?, ?)'
                )->execute([
                    $paciente['id'],
                    $servicoId,
                    $dataIso,
                    $horaInicio,
                    $horaFim,
                    $origem,
                    $obs,
                ]);

                return (int) $pdo->lastInsertId();
            });
        } catch (RuntimeException $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        } catch (Throwable $e) {
            error_log('[Agenda] erro ao criar: ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível salvar o agendamento. Tente novamente.'];
        }

        return ['ok' => true, 'id' => $id];
    }

    public static function cancelar(int $agendamentoId): array
    {
        $ag = self::agendamento($agendamentoId);

        if ($ag === null) {
            return ['ok' => false, 'erro' => 'Agendamento não encontrado.'];
        }
        if ($ag['status'] === 'cancelado') {
            return ['ok' => false, 'erro' => 'Este agendamento já está cancelado.'];
        }

        Database::query('UPDATE agendamentos SET status = "cancelado" WHERE id = ?', [$agendamentoId]);

        return ['ok' => true];
    }

    public static function remarcar(int $agendamentoId, string $dataIso, string $horaInicio, bool $excepcional = false): array
    {
        $ag = self::agendamento($agendamentoId);

        if ($ag === null) {
            return ['ok' => false, 'erro' => 'Agendamento não encontrado.'];
        }
        if ($ag['status'] === 'cancelado') {
            return ['ok' => false, 'erro' => 'Não é possível remarcar um agendamento cancelado.'];
        }
        if (!self::dataUtil($dataIso)) {
            return ['ok' => false, 'erro' => 'Escolha uma data válida, a partir de hoje.'];
        }
        if (!self::horaValida($horaInicio)) {
            return ['ok' => false, 'erro' => 'Horário inválido.'];
        }

        $servico = self::servico((int) $ag['servico_id']);
        if ($servico === null) {
            return ['ok' => false, 'erro' => 'Serviço não encontrado.'];
        }

        $duracao = (int) $servico['duracao_minutos'];
        $horaFim = Helpers::somaMinutos($horaInicio, $duracao);

        if (!$excepcional) {
            if (!self::dentroDoHorario($dataIso, $horaInicio, $horaFim)) {
                return ['ok' => false, 'erro' => 'Este horário está fora do expediente. Marque como atendimento fora do expediente para remarcar mesmo assim.'];
            }

            $tsSlot = strtotime($dataIso . ' ' . $horaInicio);
            if ($tsSlot === false) {
                return ['ok' => false, 'erro' => 'Horário inválido.'];
            }
            if ($tsSlot < time() + Env::int('AGENDA_ANTECEDENCIA', 120) * 60) {
                return ['ok' => false, 'erro' => 'Este horário está muito próximo. Escolha outro.'];
            }
            if ($tsSlot > time() + Env::int('AGENDA_DIAS_A_FUTURO', 60) * 86400) {
                return ['ok' => false, 'erro' => 'Esta data está longe demais.'];
            }
        }

        try {
            $ok = Database::transacao(static function (PDO $pdo) use ($agendamentoId, $dataIso, $horaInicio, $horaFim, $excepcional): bool {
                $pdo->prepare('SELECT id FROM agendamentos WHERE data = ? FOR UPDATE')->execute([$dataIso]);

                // mesma regra de conflito na remarcação: 'concluido' só
                // ocupa enquanto o atendimento ainda não aconteceu
                $conflito = Database::valor(
                    "SELECT COUNT(*) FROM agendamentos
                      WHERE data = ?
                        AND id <> ?
                        AND (status = 'confirmado'
                             OR (status = 'concluido' AND TIMESTAMP(data, hora_fim) >= NOW()))
                        AND hora_inicio < ?
                        AND hora_fim > ?",
                    [$dataIso, $agendamentoId, $horaFim, $horaInicio],
                    0
                );

                if ((int) $conflito > 0) {
                    throw new RuntimeException('Este horário já está ocupado. Escolha outro.');
                }

                if (!$excepcional) {
                    $bloqueio = Database::valor(
                        'SELECT COUNT(*) FROM bloqueios
                          WHERE data = ?
                            AND hora_inicio < ?
                            AND hora_fim > ?',
                        [$dataIso, $horaFim, $horaInicio],
                        0
                    );

                    if ((int) $bloqueio > 0) {
                        throw new RuntimeException('Este horário está bloqueado. Escolha outro.');
                    }
                }

                $pdo->prepare(
                    'UPDATE agendamentos SET data = ?, hora_inicio = ?, hora_fim = ?, atualizado_em = NOW()
                      WHERE id = ?'
                )->execute([$dataIso, $horaInicio, $horaFim, $agendamentoId]);

                return true;
            });
        } catch (RuntimeException $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        } catch (Throwable $e) {
            error_log('[Agenda] erro ao remarcar: ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível remarcar. Tente novamente.'];
        }

        return ['ok' => true];
    }

    public static function agendamento(int $id): ?array
    {
        return Database::primeiro(
            'SELECT a.*, p.nome AS paciente_nome, p.telefone AS paciente_telefone,
                    s.nome AS servico_nome, s.duracao_minutos
               FROM agendamentos a
               JOIN pacientes p ON p.id = a.paciente_id
               JOIN servicos  s ON s.id = a.servico_id
              WHERE a.id = ? LIMIT 1',
            [$id]
        );
    }

    /** agendamentos de um dia, ja com paciente e servico */
    public static function doDia(string $dataIso): array
    {
        return Database::todos(
            'SELECT a.id, a.paciente_id, a.servico_id, a.hora_inicio, a.hora_fim,
                    a.status, a.origem, a.observacoes,
                    p.nome AS paciente_nome, p.telefone AS paciente_telefone,
                    s.nome AS servico_nome, s.duracao_minutos
               FROM agendamentos a
               JOIN pacientes p ON p.id = a.paciente_id
               JOIN servicos  s ON s.id = a.servico_id
              WHERE a.data = ?
              ORDER BY a.hora_inicio',
            [$dataIso]
        );
    }

    /** agendamento ativo de um paciente, usado pelo agente para cancelar/remarcar */
    public static function proximoDoPaciente(string $nomePaciente): ?array
    {
        $paciente = self::pacientePorNome($nomePaciente);
        if ($paciente === null) {
            return null;
        }

        return Database::primeiro(
            "SELECT a.*, p.nome AS paciente_nome, p.telefone AS paciente_telefone,
                    s.nome AS servico_nome, s.duracao_minutos, s.ativo AS servico_ativo
               FROM agendamentos a
               JOIN pacientes p ON p.id = a.paciente_id
               JOIN servicos  s ON s.id = a.servico_id
              WHERE a.paciente_id = ?
                AND a.status = 'confirmado'
                AND a.data >= CURDATE()
              ORDER BY a.data, a.hora_inicio
              LIMIT 1",
            [$paciente['id']]
        );
    }
}