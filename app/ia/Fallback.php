<?php
/**
 * Fallback — respondedor local (modo sem chave da DeepSeek)
 *
 * Nao e IA: e uma maquina de estados que reconhece a intencao pela
 * frase do visitante. Existe para que a apresentacao funcione sem
 * chave, sem custo e sem internet, sempre com a agenda real.
 *
 * O modo local e o modo DeepSeek dividem as mesmas acoes: ambos
 * passam por Tools.php, que valida tudo antes de gravar.
 */

declare(strict_types=1);

final class Fallback
{
    /** estado inicial da conversa */
    public static function estadoInicial(): array
    {
        return [
            'etapa'      => 'inicio',
            'nome'       => '',
            'telefone'   => '',
            'servico'    => '',
            'data'       => '',
            'hora'       => '',
            'slots'      => [],
            'mostrou'    => false,
            'turnos'     => 0,
        ];
    }

    /**
     * @return array{ok:bool, texto:string}
     */
    public static function responder(string $mensagem, array &$estado): array
    {
        $texto = trim($mensagem);
        $norm = Knowledge::normalizar($texto);
        $estado['turnos'] = (int) ($estado['turnos'] ?? 0) + 1;

        // seguranca: a pessoa quer falar com gente
        if (self::querPessoa($norm)) {
            $estado['etapa'] = 'inicio';
            return ['ok' => true, 'texto' => 'Claro 😊 Se preferir falar direto com o Dr. Maycon, '
                . 'o WhatsApp é ' . Knowledge::TELEFONE . '. Prefere me contar o que precisa?'];
        }

        // Se a conversa estava no meio de um fluxo e a pessoa mudou de
        // assunto para uma duvida, responde a duvida e recomeça.
        // O cancelamento entra aqui tambem: sem isso, uma pergunta no
        // meio da conversa seria lida como nome e apagaria a consulta.
        $esperando = ['aguardando_nome', 'aguardando_dia', 'aguardando_hora', 'aguardando_confirmacao',
            'aguardando_nome_cancelamento', 'confirmar_cancelamento'];
        if (in_array($estado['etapa'] ?? 'inicio', $esperando, true)
            && !self::cabeNoFluxo($texto, $norm, $estado)
            && self::ehDuvidaDeConhecimento($norm)) {
            $estado = array_merge(Fallback::estadoInicial(), ['turnos' => (int) $estado['turnos']]);
        }

        // etapas que esperam uma resposta especifica
        switch ($estado['etapa'] ?? 'inicio') {
            case 'aguardando_nome':
                return self::receberNome($texto, $norm, $estado);
            case 'aguardando_dia':
                return self::receberDia($texto, $norm, $estado);
            case 'aguardando_hora':
                return self::receberHora($texto, $norm, $estado);
            case 'aguardando_confirmacao':
                return self::receberConfirmacao($norm, $estado);
            case 'aguardando_nome_cancelamento':
                return self::receberNomeCancelamento($texto, $norm, $estado);
            case 'confirmar_cancelamento':
                return self::confirmarCancelamento($norm, $estado);
        }

        // --------- intenções gerais ---------
        // A ordem importa: preço vem antes de tratamento, e horário de
        // funcionamento vem antes de "quero marcar".

        if (self::querPreco($norm)) {
            return ['ok' => true, 'texto' => 'Cada caso tem um valor próprio, então esse valor sai '
                . 'depois da avaliação, para o Dr. Maycon entender o seu caso. '
                . 'Quer marcar uma avaliação?'];
        }

        if (self::ehSaudacao($norm)) {
            $estado['etapa'] = 'inicio';
            return ['ok' => true, 'texto' => 'Olá 😊 Você é o Dr. Maycon Gomes. '
                . 'Ainda fica alguma dúvida ou prefere marcar uma avaliação?'];
        }

        if (self::querCancelar($norm) || self::querRemarcar($norm)) {
            $estado['etapa'] = 'aguardando_nome_cancelamento';
            $estado['acao_cancelamento'] = self::querRemarcar($norm) ? 'remarcar' : 'cancelar';
            return ['ok' => true, 'texto' => 'Consigo resolver. Qual é o seu nome, por favor?'];
        }

        // A pessoa ja fez a avaliacao: o procedimento ja foi definido
        // com o Dr. Maycon, entao o caminho e falar direto com ele.
        if (self::jaFezAvaliacao($norm)) {
            $estado['etapa'] = 'inicio';
            return ['ok' => true, 'texto' => 'Perfeito! Nesse caso fale direto com o Dr. Maycon, '
                . 'que já conhece o seu caso. O WhatsApp é ' . Knowledge::TELEFONE . '.'];
        }

        // A pessoa recusa a avaliacao e pede o procedimento direto.
        // O procedimento so pode ser definido depois dela.
        if (self::querAgendarDireto($norm)) {
            $estado['etapa'] = 'inicio';
            return ['ok' => true, 'texto' => 'Entendo 😊 Mas o procedimento é definido com o '
                . 'Dr. Maycon depois de uma avaliação. Posso marcar a avaliação?'];
        }

        // verbo de agendamento tem prioridade sobre o FAQ:
        // "quero marcar uma avaliação" e um pedido, nao uma duvida.
        if (self::querAgendar($norm)) {
            $estado['etapa'] = 'aguardando_nome';
            $estado['servico'] = '';
            return ['ok' => true, 'texto' => 'Ótimo 😊 Para marcar, qual é o seu nome?'];
        }

        // dúvida sobre um tratamento específico
        $tratamento = Knowledge::tratamentoPorNome($norm);
        if ($tratamento !== null) {
            // a descrição dos alinhadores já fala em avaliação:
            // repetir o argumento só estica a resposta.
            $fecho = str_contains($tratamento['descricao'], 'avaliação')
                ? ' Quer marcar?'
                : ' Para definir o que você precisa, o próximo passo é uma avaliação. Quer marcar?';

            return ['ok' => true, 'texto' => $tratamento['descricao'] . $fecho];
        }

        // dúvida que está no FAQ do site
        $faq = Knowledge::faqPorTexto($norm);
        if ($faq !== null) {
            return ['ok' => true, 'texto' => $faq['resposta']
                . ' Posso ajudar com mais alguma coisa?'];
        }

        // horário de funcionamento, endereço, telefone
        if (self::querHorarioDeAtendimento($norm)) {
            $dias = Knowledge::textoDias();
            return ['ok' => true, 'texto' => ($dias !== '' ? 'O atendimento é ' . mb_strtolower($dias) . '.' : 'O atendimento é de segunda a sábado.')
                . ' Quer que eu veja os horários disponíveis para você?'];
        }

        if (str_contains($norm, 'endereco') || str_contains($norm, 'onde fica') || str_contains($norm, 'localiz')) {
            return ['ok' => true, 'texto' => 'O consultório fica na ' . Knowledge::dados()['endereco']
                . ', ' . Knowledge::dados()['cidade'] . '. Quer marcar uma avaliação?'];
        }

        if (str_contains($norm, 'telefone') || str_contains($norm, 'contato') || str_contains($norm, 'whatsapp')) {
            return ['ok' => true, 'texto' => 'O WhatsApp é ' . Knowledge::TELEFONE . '. '
                . 'Posso marcar sua avaliação por aqui mesmo, se preferir 😊'];
        }

        // --------- sem intencao reconhecida ---------
        if ($estado['turnos'] === 1) {
            return ['ok' => true, 'texto' => 'Oi 😊 Posso tirar dúvidas sobre os tratamentos e '
                . 'marcar sua avaliação. O que você gostaria de saber?'];
        }

        return ['ok' => true, 'texto' => 'Essa eu prefiro encaminhar para o Dr. Maycon, para ele '
            . 'responder com certeza. O WhatsApp é ' . Knowledge::TELEFONE . '. '
            . 'Ou, se preferir, posso marcar sua avaliação agora.'];
    }

    // ===================================================================
    //  etapas
    // ===================================================================

    private static function receberNome(string $texto, string $norm, array &$estado): array
    {
        if (self::ehSaudacao($norm)) {
            return ['ok' => true, 'texto' => 'Me diz seu nome para eu reservar 😊'];
        }

        if (self::ehSim($norm)) {
            return ['ok' => true, 'texto' => 'Me diz seu nome, por favor 😊'];
        }

        // o visitante mandou um dia/horário junto do nome
        $nome = self::extrairNome($texto);
        if ($nome === null) {
            return ['ok' => true, 'texto' => 'Como posso te chamar?'];
        }

        $estado['nome'] = $nome;

        // se veio um telefone junto, aproveita
        $tel = self::extrairTelefone($texto);
        if ($tel !== null) {
            $estado['telefone'] = $tel;
        }

        // a pessoa já disse o serviço na mesma frase
        $servico = Knowledge::tratamentoPorNome($norm);
        if ($servico !== null) {
            $estado['servico'] = $servico['titulo'];
        }

        $estado['etapa'] = 'aguardando_dia';

        // se a frase já trouxe um dia, tenta resolver agora
        $dia = self::interpretarDia($norm);
        if ($dia !== null) {
            return self::montarDisponibilidade($dia, $estado);
        }

        return ['ok' => true, 'texto' => 'Obrigada, ' . $nome . '! Para que dia você quer marcar?'];
    }

    private static function receberDia(string $texto, string $norm, array &$estado): array
    {
        if (self::ehSim($norm)) {
            return ['ok' => true, 'texto' => 'Para que dia você quer marcar?'];
        }

        $dia = self::interpretarDia($norm);

        if ($dia === null) {
            return ['ok' => true, 'texto' => 'Não entendi o dia 😅 Pode falar assim: "sexta", '
                . '"amanhã" ou "15/10"?'];
        }

        return self::montarDisponibilidade($dia, $estado);
    }

    private static function receberHora(string $texto, string $norm, array &$estado): array
    {
        $slots = $estado['slots'] ?? [];

        // escolheu um horário da lista?
        $escolhido = self::escolherSlotDaLista($norm, $slots);
        if ($escolhido !== null) {
            return self::confirmar($escolhido, $estado);
        }

        // "depois das 14", "à tarde", "pela manhã"
        $hora = self::interpretarHora($norm, $slots);

        if ($hora === null) {
            $lista = self::formatarSlots($slots);
            return ['ok' => true, 'texto' => 'Não encontrei esse horário 😅 O que tenho é ' . $lista . '. Qual fica melhor?'];
        }

        return self::confirmar($hora, $estado);
    }

    /**
     * Casa a frase do visitante com um horário da lista, sem aceitar
     * números soltos: "09:30", "9:30", "9h30" e "9h".
     */
    private static function escolherSlotDaLista(string $norm, array $slots): ?string
    {
        foreach ($slots as $slot) {
            $hora = date('H:i', (int) strtotime($slot));
            $hh = (int) substr($hora, 0, 2);
            $mm = substr($hora, 3, 2);

            // 09:30 · 9:30 · 09h30
            if (preg_match('/(^|\s)' . $hh . '\s*[:h]\s*0?' . $mm . '($|\s)/', $norm) === 1) {
                return $hora;
            }

            // 9h
            if (preg_match('/(^|\s)' . $hh . '\s*h($|\s)/', $norm) === 1) {
                return $hora;
            }
        }

        return null;
    }

    private static function receberConfirmacao(string $norm, array &$estado): array
    {
        // quando o fluxo veio de "remarcar", NUNCA cria um agendamento novo:
        // move o que já existe, para o paciente não ficar com dois horários
        $remarcando = ($estado['acao_cancelamento'] ?? '') === 'remarcar'
            && !empty($estado['ag_cancelamento']);

        if (self::ehSim($norm)) {
            if ($remarcando) {
                $resultado = Tools::executar('remarcar_agendamento', [
                    'nome' => (string) ($estado['nome_cancelamento'] ?? ''),
                    'data' => (string) $estado['data'],
                    'hora' => (string) $estado['hora'],
                ], $estado);

                AgenteLog::registrar('remarcar_agendamento', ['local'], $resultado, (string) ($estado['nome'] ?? ''));

                if (!$resultado['ok']) {
                    $estado['etapa'] = 'confirmar_cancelamento';
                    return ['ok' => true, 'texto' => 'Não consegui remarcar: ' . $resultado['texto']
                        . ' Seu horário antigo continua valendo. Quer tentar outro dia?'];
                }

                $estado['etapa'] = 'concluido';

                return ['ok' => true, 'texto' => 'Pronto 😊 Remarquei para '
                    . Helpers::dataExtenso((string) $estado['data']) . ' às ' . $estado['hora']
                    . '. Qualquer coisa, é só me chamar.'];
            }

            $resultado = Tools::executar('criar_agendamento', [
                'nome'     => (string) $estado['nome'],
                'telefone' => (string) ($estado['telefone'] ?? ''),
                'servico'  => (string) ($estado['servico'] ?? ''),
                'data'     => (string) $estado['data'],
                'hora'     => (string) $estado['hora'],
            ], $estado);

            AgenteLog::registrar('criar_agendamento', ['local'], $resultado, (string) ($estado['nome'] ?? ''));

            if (!$resultado['ok']) {
                $estado['etapa'] = 'aguardando_dia';
                return ['ok' => true, 'texto' => 'Deu um problema aqui: ' . $resultado['texto']
                    . ' Posso tentar outro horário?'];
            }

            $estado['etapa'] = 'concluido';

            $diaTexto = Helpers::dataExtenso((string) $estado['data']);
            $servico = self::nomeServico($estado);

            return ['ok' => true, 'texto' => self::resumoMarcacao(
                (string) $estado['nome'],
                (string) $estado['data'],
                (string) $estado['hora'],
                $servico
            )];
        }

        if (self::ehNao($norm)) {
            $estado['etapa'] = 'aguardando_hora';
            $estado['hora'] = '';
            return ['ok' => true, 'texto' => 'Sem problema. Os horários de ' . Helpers::dataExtenso((string) $estado['data'])
                . ' são ' . self::formatarSlots($estado['slots'] ?? []) . '. Qual você prefere?'];
        }

        if ($remarcando) {
            return ['ok' => true, 'texto' => 'Só pra confirmar: remarco para '
                . Helpers::dataExtenso((string) $estado['data']) . ' às ' . $estado['hora']
                . ', com ' . mb_strtolower((string) ($estado['servico_agendamento'] ?? 'avaliação'))
                . '? Responda sim ou não 😊'];
        }

        // qualquer outra coisa: volta a perguntar
        return ['ok' => true, 'texto' => 'Só pra confirmar: ' . Helpers::dataExtenso((string) $estado['data'])
            . ' às ' . $estado['hora'] . ', com ' . mb_strtolower(self::nomeServico($estado))
            . '? Responda sim ou não 😊'];
    }

    private static function receberNomeCancelamento(string $texto, string $norm, array &$estado): array
    {
        $nome = self::extrairNome($texto);

        if ($nome === null) {
            return ['ok' => true, 'texto' => 'Qual é o seu nome, por favor?'];
        }

        $ag = Agenda::proximoDoPaciente($nome);

        if ($ag === null) {
            $estado['etapa'] = 'inicio';
            return ['ok' => true, 'texto' => 'Não encontrei nenhum horário marcado para ' . $nome . '. '
                . 'Quer marcar uma nova avaliação?'];
        }

        $estado['nome_cancelamento'] = $nome;
        $estado['ag_cancelamento'] = (int) $ag['id'];
        $estado['servico_agendamento'] = (string) $ag['servico_nome'];
        $estado['duracao_agendamento'] = (int) $ag['duracao_minutos'];

        // mantem a intencao original: "preciso remarcar" + nome continua remarcar
        if (self::querRemarcar($norm)) {
            $estado['acao_cancelamento'] = 'remarcar';
        } elseif (self::querCancelar($norm)) {
            $estado['acao_cancelamento'] = 'cancelar';
        } elseif (($estado['acao_cancelamento'] ?? '') === '') {
            $estado['acao_cancelamento'] = 'remarcar';
        }

        $estado['etapa'] = 'confirmar_cancelamento';

        $diaTexto = Helpers::dataExtenso((string) $ag['data']);
        $hora = date('H:i', (int) strtotime((string) $ag['hora_inicio']));

        if ($estado['acao_cancelamento'] === 'remarcar') {
            return ['ok' => true, 'texto' => 'Você tem um horário de ' . $ag['servico_nome']
                . ' marcado para ' . $diaTexto . ' às ' . $hora . '. Para que dia você quer mudar?'];
        }

        return ['ok' => true, 'texto' => 'Você tem um horário de ' . $ag['servico_nome']
            . ' marcado para ' . $diaTexto . ' às ' . $hora . '. Quer que eu cancele? Responda sim ou não.'];
    }

    private static function confirmarCancelamento(string $norm, array &$estado): array
    {
        if ($estado['acao_cancelamento'] === 'remarcar') {
            $dia = self::interpretarDia($norm);

            if ($dia === null) {
                return ['ok' => true, 'texto' => 'Para que dia você quer mudar?'];
            }

            // usa o serviço do agendamento atual: a duracao define
            // quais horarios realmente cabem na nova data
            $servicoNome = (string) ($estado['servico_agendamento'] ?? '');
            if ($servicoNome === '') {
                $servicoNome = (string) ($estado['servico'] ?? '');
            }

            $resultado = Tools::executar('consultar_disponibilidade', [
                'data'    => $dia,
                'servico' => $servicoNome,
            ], $estado);

            AgenteLog::registrar('consultar_disponibilidade', ['remarcao'], $resultado, (string) ($estado['nome'] ?? ''));

            $estado['data'] = $dia;
            $estado['servico'] = $servicoNome;

            if (!$resultado['ok'] || ($estado['slots'] ?? []) === []) {
                $estado['etapa'] = 'confirmar_cancelamento';
                return ['ok' => true, 'texto' => $resultado['texto'] . ' Quer tentar outra data?'];
            }

            $estado['etapa'] = 'aguardando_hora';
            return ['ok' => true, 'texto' => 'Para ' . Helpers::dataExtenso($dia) . ' tenho '
                . self::formatarSlots($estado['slots']) . '. Qual fica melhor?'];
        }

        if (self::ehSim($norm)) {
            $resultado = Tools::executar('cancelar_agendamento', [
                'nome' => (string) ($estado['nome_cancelamento'] ?? ''),
            ], $estado);

            AgenteLog::registrar(
                    'cancelar_agendamento',
                    ['local'],
                    $resultado,
                    (string) ($estado['nome_cancelamento'] ?? $estado['nome'] ?? '')
                );

            if (!$resultado['ok']) {
                $estado['etapa'] = 'inicio';
                return ['ok' => true, 'texto' => $resultado['texto']];
            }

            // o horario foi cancelado; se a pessoa quiser remarcar,
            // ja deixamos o proximo passo pronto
            $estado['etapa'] = 'aguardando_nome_cancelamento';
            $estado['acao_cancelamento'] = 'remarcar';

            return ['ok' => true, 'texto' => 'Pronto, cancelei. Se quiser remarcar para outro dia, me diga seu nome.'];
        }

        $estado['etapa'] = 'inicio';

        return ['ok' => true, 'texto' => 'Tudo bem, deixei como está então. Precisa de mais alguma coisa?'];
    }

    // ===================================================================
    // helpers
    // ===================================================================

    /** nome do serviço em conversa: o que a pessoa pediu ou o da agenda */
    private static function nomeServico(array $estado): string
    {
        $pedido = trim((string) ($estado['servico'] ?? ''));
        if ($pedido !== '') {
            return $pedido;
        }

        return trim((string) ($estado['servico_nome'] ?? '')) ?: 'Avaliação';
    }

    private static function montarDisponibilidade(string $data, array &$estado): array
    {
        $servico = $estado['servico'] ?? '';
        $resultado = Tools::executar('consultar_disponibilidade', [
            'data'    => $data,
            'servico' => $servico,
        ], $estado);

        AgenteLog::registrar('consultar_disponibilidade', ['local'], $resultado, (string) ($estado['nome'] ?? ''));

        $estado['data'] = $data;

        if (!$resultado['ok'] || ($estado['slots'] ?? []) === []) {
            $estado['etapa'] = 'aguardando_dia';
            return ['ok' => true, 'texto' => $resultado['texto']
                . ' Quer que eu veja outro dia?'];
        }

        $estado['etapa'] = 'aguardando_hora';

        return ['ok' => true, 'texto' => 'Para ' . Helpers::dataExtenso($data) . ' tenho '
            . self::formatarSlots($estado['slots']) . '. Qual fica melhor pra você?'];
    }

    private static function confirmar(string $hora, array &$estado): array
    {
        $estado['hora'] = $hora;
        $estado['etapa'] = 'aguardando_confirmacao';

        $diaTexto = Helpers::dataExtenso((string) $estado['data']);
        $servico = self::nomeServico($estado);

        return ['ok' => true, 'texto' => 'Perfeito. Posso confirmar sua ' . mb_strtolower($servico)
            . ' para ' . $diaTexto . ' às ' . $hora . '?'];
    }

    private static function formatarSlots(array $slots): string
    {
        if ($slots === []) {
            return 'nenhum horário';
        }
        $lista = array_map(static fn (string $h): string => date('H:i', (int) strtotime($h)), $slots);
        if (count($lista) > 5) {
            $lista = array_slice($lista, 0, 5);
        }
        return implode('h, ', $lista) . 'h';
    }

    // --------- detecção de intenção ---------

    private static function ehSaudacao(string $norm): bool
    {
        foreach (['oi', 'ola', 'opa', 'e ai', 'bom dia', 'boa tarde', 'boa noite', 'tudo bem', 'bom tarde'] as $p) {
            if ($norm === $p || str_starts_with($norm, $p . ' ')) {
                return true;
            }
        }
        return false;
    }

    private static function ehSim(string $norm): bool
    {
        $sim = ['sim', 'pode', 'pode ser', 'isso', 'esse', 'essa', 'confirmo', 'confirma',
            'ok', 'okay', 'blz', 'beleza', 'certo', 'certa', 'isso ai', 'fechado', 'manda',
            'quero', 'quero sim', 'isso mesmo', 'perfeito'];
        return in_array($norm, $sim, true);
    }

    private static function ehNao(string $norm): bool
    {
        $nao = ['nao', 'negativo', 'deixa pra depois', 'depois eu aviso'];
        return in_array($norm, $nao, true)
            || str_starts_with($norm, 'nao ')
            || str_starts_with($norm, 'outro ')
            || str_starts_with($norm, 'outra ');
    }

    private static function querPessoa(string $norm): bool
    {
        $chaves = ['falar com o dr', 'falar com o dentista', 'falar com alguem', 'falar com alguem',
            'atendente', 'pessoa', 'humano', 'atendimento humano', 'quero falar com',
            'me liga', 'telefonar', 'ouvir o dr'];
        foreach ($chaves as $c) {
            if (str_contains($norm, $c)) {
                return true;
            }
        }
        return false;
    }

    private static function querPreco(string $norm): bool
    {
        $chaves = ['quanto custa', 'quanto custa', 'preco', 'preços', 'valor', 'valores',
            'quanto e', 'custa', 'cobrança', 'cobranca', 'parcela', 'parcelamento',
            'quanto pago', 'quanto vou pagar', 'gratuito', 'gratis'];
        foreach ($chaves as $c) {
            if (str_contains($norm, $c)) {
                return true;
            }
        }
        return false;
    }

    private static function querHorarioDeAtendimento(string $norm): bool
    {
        // "que horas vocês atendem", "qual o horário de funcionamento"
        $temHorario = str_contains($norm, 'horario') || str_contains($norm, 'horarios')
            || str_contains($norm, 'horas');
        if (!$temHorario) {
            return false;
        }

        $querMarcar = str_contains($norm, 'marcar') || str_contains($norm, 'agendar')
            || str_contains($norm, 'disponivel') || str_contains($norm, 'vaga');

        return !$querMarcar;
    }

    private static function querAgendar(string $norm): bool
    {
        $chaves = ['marcar', 'agendar', 'agendamento', 'agendada', 'consulta', 'avaliacao',
            'disponibilidade', 'disponiveis', 'vaga', 'reservar', 'quero ir', 'visita',
            'encaixe', 'horario livre', 'horarios livres', 'horarios disponiveis', 'marcar um horario'];
        foreach ($chaves as $c) {
            if (str_contains($norm, $c)) {
                return true;
            }
        }
        return false;
    }

    /**
     * A pessoa recusa a avaliação e pede o procedimento direto.
     * Chaveado pela recusa, não pelo pedido: "quero aparelho" sozinho
     * é dúvida normal e cai na resposta de tratamento.
     */
    private static function querAgendarDireto(string $norm): bool
    {
        $chaves = ['sem avaliacao', 'sem ser avaliado', 'sem ser avaliada',
            'nao quero avaliacao', 'nao precisa de avaliacao', 'nao precisa de avaliacao',
            'pular a avaliacao', 'dispensar a avaliacao', 'so o procedimento',
            'ja sei o que quero'];
        foreach ($chaves as $c) {
            if (str_contains($norm, $c)) {
                return true;
            }
        }
        return false;
    }

    /**
     * A pessoa já passou pela avaliação. Se ainda fala com a
     * assistente, o mais provável é ter perdido o contato: o
     * procedimento já foi definido, então vai para o WhatsApp.
     */
    private static function jaFezAvaliacao(string $norm): bool
    {
        $chaves = ['ja fiz avaliacao', 'ja fiz a avaliacao', 'ja fiz uma avaliacao',
            'ja fui avaliado', 'ja fui avaliada', 'ja fui avaliada aqui',
            'ja fiz consulta', 'ja fiz uma consulta', 'ja passei por avaliacao',
            'fiz avaliacao com o dr'];
        foreach ($chaves as $c) {
            if (str_contains($norm, $c)) {
                return true;
            }
        }
        return false;
    }

    /** a mensagem combina com o que a etapa atual espera? */
    private static function cabeNoFluxo(string $texto, string $norm, array $estado): bool
    {
        switch ($estado['etapa'] ?? '') {
            case 'aguardando_nome':
            case 'aguardando_nome_cancelamento':
                return self::extrairNome($texto) !== null || self::ehSim($norm) || self::ehSaudacao($norm);

            case 'aguardando_dia':
            case 'confirmar_cancelamento':
                return self::interpretarDia($norm) !== null || self::ehSim($norm) || self::ehNao($norm);

            case 'aguardando_hora':
                return self::interpretarHora($norm, (array) ($estado['slots'] ?? [])) !== null
                    || self::ehNao($norm)
                    || self::contemHorarioDaLista($norm, (array) ($estado['slots'] ?? []));

            case 'aguardando_confirmacao':
                return self::ehSim($norm) || self::ehNao($norm);
        }

        return true;
    }

    private static function contemHorarioDaLista(string $norm, array $slots): bool
    {
        return self::escolherSlotDaLista($norm, $slots) !== null;
    }

    /** a mensagem é uma dúvida sobre o consultório? */
    private static function ehDuvidaDeConhecimento(string $norm): bool
    {
return self::querPreco($norm)
  || self::jaFezAvaliacao($norm)
  || self::querAgendarDireto($norm)
  || Knowledge::tratamentoPorNome($norm) !== null
  || Knowledge::faqPorTexto($norm) !== null
  || self::querHorarioDeAtendimento($norm)
  || str_contains($norm, 'endereco')
  || str_contains($norm, 'onde fica')
  || str_contains($norm, 'telefone')
  || str_contains($norm, 'whatsapp');
    }

    private static function querCancelar(string $norm): bool
    {
        foreach (['cancelar', 'desistir', 'nao posso ir', 'nao vou', 'desmarcar'] as $c) {
            if (str_contains($norm, $c)) {
                return true;
            }
        }
        return false;
    }

    private static function querRemarcar(string $norm): bool
    {
        foreach (['remarcar', 'adiar', 'mudar', 'outro dia', 'outra data', 'antecipar', 'transferir'] as $c) {
            if (str_contains($norm, $c)) {
                return true;
            }
        }
        return false;
    }

    // --------- nome, data, hora ---------

    private static function extrairNome(string $texto): ?string
    {
        $texto = trim($texto);
        if ($texto === '' || mb_strlen($texto) > 60) {
            return null;
        }

        // tira saudações comuns do começo
        $limpa = preg_replace(
            '/^(oi|ol[áa]|opa|e a[ií]|bom dia|boa tarde|boa noite)\s*[!,.]?\s*/iu',
            '',
            $texto
        ) ?? $texto;

        $limpa = preg_replace('/\s*(e sou|meu nome e|me chamo|é o|é a)\s*/iu', ' ', $limpa) ?? $limpa;
        $limpa = preg_replace(
            '/\s*(quero|preciso|gostaria|queria|posso|posso marcar|agendar)\b.*$/iu',
            '',
            $limpa
        ) ?? $limpa;
        $limpa = preg_replace('/[^\p{L}\s\-]\s*$/u', '', $limpa) ?? $limpa;
        $limpa = trim(preg_replace('/\s+/', ' ', $limpa) ?? $limpa);

        if ($limpa === '') {
            return null;
        }

        $palavras = explode(' ', $limpa);
        if (count($palavras) < 2) {
            return null;
        }

        foreach ($palavras as $p) {
            if (mb_strlen($p) < 2 || in_array(mb_strtolower($p), ['de', 'do', 'da', 'que', 'pra', 'para'], true)) {
                return null;
            }
        }

        return mb_convert_case($limpa, MB_CASE_TITLE, 'UTF-8');
    }

    private static function extrairTelefone(string $texto): ?string
    {
        if (preg_match('/(\(?\d{2}\)?\s?9?\d{4}[-\s]?\d{4})/u', $texto, $m) === 1) {
            $digitos = Helpers::telefone($m[1]);
            return Helpers::telefoneValido($digitos) ? $digitos : null;
        }
        return null;
    }

    /** interpreta "sexta", "amanha", "15/10", "proxima semana" */
    public static function interpretarDia(string $norm): ?string
    {
        $hoje = strtotime('today');

        if (str_contains($norm, 'amanha')) {
            $ts = strtotime('+1 day', $hoje);
            return date('Y-m-d', $ts);
        }

        if (str_contains($norm, 'depois de amanha')) {
            return date('Y-m-d', strtotime('+2 days', $hoje));
        }

        if (preg_match('/(\d{1,2})\/(\d{1,2})/', $norm, $m) === 1) {
            $ano = (int) date('Y');
            $data = sprintf('%04d-%02d-%02d', $ano, (int) $m[2], (int) $m[1]);
            if (Helpers::dataValida($data) && $data >= date('Y-m-d')) {
                return $data;
            }
        }

        if (preg_match('/dia (\d{1,2})/', $norm, $m) === 1) {
            $dia = (int) $m[1];
            $data = date('Y-m-d', strtotime('+' . $dia . ' days', $hoje));
            return $data;
        }

        // domingo entra de proposito: o sistema responde que nao ha
        // atendimento nesse dia, em vez de "nao entendi"
        // o strtotime do PHP so entende os nomes em ingles, entao
        // usamos o ingles apenas para o calculo
        $dias = [
            'domingo' => 'sunday', 'segunda' => 'monday', 'terca' => 'tuesday',
            'quarta' => 'wednesday', 'quinta' => 'thursday', 'sexta' => 'friday',
            'sabado' => 'saturday',
        ];

        foreach ($dias as $nome => $nomeIngles) {
            if (str_contains($norm, $nome)) {
                $ts = strtotime('next ' . $nomeIngles, $hoje);

                if ($ts === false) {
                    return null;
                }

                // se cair no proprio dia, joga para a semana seguinte
                if (date('Y-m-d', $ts) === date('Y-m-d', $hoje)) {
                    $ts = strtotime('+7 days', $ts);
                }

                return date('Y-m-d', $ts);
            }
        }

        if (str_contains($norm, 'proxima semana')) {
            return date('Y-m-d', strtotime('next monday', $hoje));
        }

        return null;
    }

    /** interpreta "16h", "as 16", "depois das 14", "a tarde" */
    public static function interpretarHora(string $norm, array $slots): ?string
    {
        if ($slots === []) {
            return null;
        }

        // horarios completos realmente livres, ex.: ["09:30", "10:00"]
        $livres = [];
        foreach ($slots as $slot) {
            $hora = date('H:i', (int) strtotime($slot));
            $livres[$hora] = (int) substr($hora, 0, 2);
        }
        ksort($livres);

        $filtrar = static function (int $de, int $ate) use ($livres): ?string {
            foreach ($livres as $hora => $hh) {
                if ($hh >= $de && $hh <= $ate) {
                    return $hora;
                }
            }
            return null;
        };

        if (str_contains($norm, 'manha')) {
            return $filtrar(0, 12);
        }

        if (str_contains($norm, 'tarde')) {
            return $filtrar(12, 18);
        }

        if (str_contains($norm, 'noite')) {
            return $filtrar(17, 23);
        }

        // "depois das 14" / "a partir das 15"
        if (preg_match('/(depois|a partir) d[ae]s (\d{1,2})/', $norm, $m) === 1) {
            return $filtrar((int) $m[2], 23);
        }

        // horario exato: precisa bater com um horario livre, minuto incluido
        $hora = Tools::normalizarHora($norm);
        if ($hora !== null) {
            if (isset($livres[$hora])) {
                return $hora;
            }

            // "10h" quando existe exatamente o 10:00 livre
            $soHora = sprintf('%s:00', $hora);
            if (isset($livres[$soHora])) {
                return $soHora;
            }

            return null;
        }

        return null;
    }

    /**
     * Resumo entregue ao paciente logo apos a marcacao.
     *
     * Reune o que ele precisa lembrar em um so lugar: quem ele e,
     * quando, o que, onde, e como mudar se precisar.
     * Todos os dados vem do que ja foi combinado na conversa e do
     * Knowledge — nada e inventado aqui.
     */
    private static function resumoMarcacao(string $nome, string $dataIso, string $hora, string $servico): string
    {
        $dados = Knowledge::dados();
        $endereco = trim((string) ($dados['endereco'] ?? '')) . ' — ' . (string) ($dados['cidade'] ?? '');

        $linhas = [];

        $linhas[] = '✅ ' . self::rotuloServico($servico) . ' agendada!';
        $linhas[] = '';
        $linhas[] = '👤 ' . $nome;
        $linhas[] = '📅 ' . Helpers::dataExtenso($dataIso);
        $linhas[] = '🕐 ' . $hora . ' · ' . self::rotuloServico($servico);

        if ($endereco !== '') {
            $linhas[] = '';
            $linhas[] = '📍 ' . $endereco;
        }

        $linhas[] = '';
        $linhas[] = 'Precisa remarcar ou cancelar? É só me chamar aqui mesmo,'
            . ' ou falar no WhatsApp ' . Knowledge::TELEFONE . '.';
        $linhas[] = '';
        $linhas[] = 'Até lá! 😊';

        return implode("\n", $linhas);
    }

    /** "Avaliação" vira "Avaliação" e "Avaliação inicial" vira "Avaliação inicial" */
    private static function rotuloServico(string $servico): string
    {
        $servico = trim($servico);

        if ($servico === '') {
            return 'Avaliação';
        }

        return mb_convert_case($servico, MB_CASE_TITLE, 'UTF-8');
    }
}