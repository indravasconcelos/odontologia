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

        // O paciente pode perguntar qualquer coisa a qualquer momento,
        // inclusive no meio do agendamento. Respondemos a dúvida, mas
        // NÃO zeramos o estado: apagar nome e dia por causa de uma
        // pergunta honesta obrigava o paciente a começar tudo de novo.
        $etapaAntes = (string) ($estado['etapa'] ?? 'inicio');
        $esperando = ['aguardando_nome', 'aguardando_dia', 'aguardando_hora', 'aguardando_confirmacao',
            'aguardando_nome_cancelamento', 'confirmar_cancelamento'];

        // Uma pergunta de verdade no meio do fluxo também conta, mesmo
        // sem palavra-chave: responder "não encontrei esse horário" para
        // "você faz cirurgia plástica?" seria ignorar o que a pessoa
        // perguntou. O cabeNoFluxo já protege as respostas legítimas
        // ("sexta?", "09:30?", "sim?").
        $ehPergunta = str_contains($texto, '?');

        $duvidaNoMeio = in_array($etapaAntes, $esperando, true)
            && !self::cabeNoFluxo($texto, $norm, $estado)
            && (self::ehDuvidaDeConhecimento($norm) || $ehPergunta);

        // etapas que esperam uma resposta especifica. Se a pessoa
        // perguntou uma duvida, elas ficam de fora: "quanto custa
        // clareamento?" no meio do agendamento e uma pergunta de
        // verdade, e o switch de "dia" responderia "não entendi o dia".
        if (!$duvidaNoMeio) {
            switch ($etapaAntes) {
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
        }

        $resposta = self::intencaoGeral($texto, $norm, $estado);

        // Quem perguntou preço, endereço ou horário quer informação, não
        // agendamento. Empurrar "Quer marcar uma avaliação?" no fim de
        // toda resposta é forçar o que a pessoa não pediu — e no meio do
        // fluxo ainda sai junto com a próxima pergunta, o que vira
        // duas seguidas. O convite só cabe na saudação inicial.
        $resposta['texto'] = self::semConviteDeAgendamento($resposta['texto']);

        // Só lembra o ponto quando a resposta NÃO é em si a próxima
        // pergunta do fluxo. "Quero marcar" no meio da conversa já
        // pede o nome; repetir o lembrete faria duas seguidas.
        $assumiuFluxo = self::querAgendar($norm)
            || self::querCancelar($norm)
            || self::querRemarcar($norm);

        if ($duvidaNoMeio && !$assumiuFluxo) {
            $estado['etapa'] = $etapaAntes;
            $resposta['texto'] = rtrim($resposta['texto']) . "\n\n" . self::lembreteDoFluxo($estado);
        }

        return $resposta;
    }

    /**
     * Lembra ao paciente onde a conversa tinha parado, depois de uma
     * dúvida no meio do agendamento. Usa só o que já foi combinado.
     */
    private static function lembreteDoFluxo(array $estado): string
    {
        // Só a próxima pergunta. Sem "Voltando ao agendamento": a
        // resposta à dúvida já veio, e repetir o caminho percorrido é
        // o resumo que o paciente não pediu.
        return match ((string) ($estado['etapa'] ?? 'inicio')) {
            'aguardando_nome' => 'Me diz seu nome, sobrenome e telefone, por favor.',

            'aguardando_dia' => 'Para que dia você quer marcar?',

            'aguardando_hora' => 'Os horários de '
                . Helpers::dataExtenso((string) ($estado['data'] ?? '')) . ' são '
                . self::formatarSlots((array) ($estado['slots'] ?? [])) . '. Qual fica melhor?',

            'aguardando_confirmacao' => 'Posso confirmar sua '
                . mb_strtolower(self::nomeServico($estado)) . ' para '
                . Helpers::dataExtenso((string) ($estado['data'] ?? '')) . ' às '
                . (string) ($estado['hora'] ?? '') . '?',

            'aguardando_nome_cancelamento' => 'Qual é o seu nome?',

            'confirmar_cancelamento' => 'Confirma o cancelamento? Responda sim ou não.',

            default => 'Posso te ajudar com mais alguma coisa?',
        };
    }

    /**
     * Corta o convite de agendamento no fim de uma resposta. Depois que
     * o agendamento já foi concluído, esse convite é ruído — a pessoa
     * só fez uma pergunta avulsa.
     */
    private static function semConviteDeAgendamento(string $texto): string
    {
        $finais = [
            ' Quer marcar uma avaliação?',
            ' Posso ajudar com mais alguma coisa?',
            ' Quer que eu veja os horários disponíveis para você?',
            ' Se quiser, já marco sua avaliação por aqui.',
            ' Se preferir, marco uma avaliação por aqui mesmo.',
            ' Se quiser, já marco sua avaliação por aqui mesmo.',
        ];

        foreach ($finais as $fim) {
            $texto = str_replace($fim, '', $texto);
        }

        return rtrim($texto);
    }

    /**
     * Intenções gerais, quando a conversa não está esperando uma
     * resposta específica. A ordem importa: preço vem antes de
     * tratamento, e horário de funcionamento antes de "quero marcar".
     */
    private static function intencaoGeral(string $texto, string $norm, array &$estado): array
    {
        // Resolvido uma vez, no topo, porque três intenções precisam
        // saber se a pessoa citou um tratamento: "o tratamento de canal
        // dói?" e uma duvida sobre o procedimento, nao uma reclamacao
        // de dor — e responder dor no lugar da descricao seria perder
        // a unica informacao que o site consegue dar.
        $tratamento = Knowledge::tratamentoPorNome($norm);

        // Rota "Tenho dúvidas" do botão de abertura. É um convite a
        // perguntar, não uma pergunta: só encaminha, não zera nada que
        // já esteja em andamento e não insiste em agendar. Comparação
        // exata para não sequestrar uma dúvida de verdade como
        // "tenho dúvidas sobre aparelho".
        if (self::querTirarDuvida($norm)) {
            return ['ok' => true, 'texto' => 'Pode perguntar, fico à disposição 😊 '
                . 'Se preferir, também consigo agendar sua avaliação.'];
        }

        if (self::querPreco($norm)) {
            return ['ok' => true, 'texto' => 'Cada caso tem um valor próprio, então esse valor sai '
                . 'depois da avaliação, para o Dr. Maycon entender o seu caso. '
                . 'Quer marcar uma avaliação?'];
        }

        // "Olá! Quero marcar minha avaliação" começa com saudação, mas
        // o que a pessoa fez foi pedir horário. A saudação só manda
        // quando não vem pedido junto — é o caso dos botões do site.
        if (self::ehSaudacao($norm) && !self::querAgendar($norm)) {
            $estado['etapa'] = 'inicio';
            return ['ok' => true, 'texto' => 'Olá 😊 Gostaria de mais informações '
                . 'ou quer agendar uma avaliação?'];
        }

        // Dor é a urgência mais comum de quem escreve. Nada no site
        // autoriza a prometer atendimento imediato, então o caminho
        // honesto é o WhatsApp, que é o canal direto do Dr. Maycon.
        // Só quando a pessoa não citou um tratamento: se citou, ela
        // quer saber sobre o procedimento, e a resposta dele vem depois.
        if (self::temDor($norm) && $tratamento === null) {
            $estado['etapa'] = 'inicio';
            return ['ok' => true, 'texto' => 'Entendi, e quando é dor o mais rápido é falar '
                . 'com o Dr. Maycon para ele avaliar a sua situação. O WhatsApp é '
                . Knowledge::TELEFONE . '. Se preferir, marco uma avaliação por aqui mesmo.'];
        }

        // Pagamento e convenio vem ANTES de "quanto custa": perguntar
        // "aceita parcelar?" nao e perguntar preco, e a resposta de
        // preco deixaria a pessoa sem o que ela quis saber.
        if (self::querConvenio($norm)) {
            $estado['etapa'] = 'inicio';
            return ['ok' => true, 'texto' => 'Sobre convênio eu não tenho essa informação aqui 😕 '
                . 'O Dr. Maycon confirma isso com você. Quer marcar uma avaliação?'];
        }

        if (self::querFormaDePagamento($norm)) {
            $estado['etapa'] = 'inicio';
            return ['ok' => true, 'texto' => 'As formas de pagamento quem confirma é o Dr. Maycon, '
                . 'assim ele explica tudo certinho junto com o seu caso. '
                . 'Quer marcar uma avaliação?'];
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
            return ['ok' => true, 'texto' => 'Claro 😊 Me diga seu nome, sobrenome e telefone, por favor.'];
        }

        // "quanto tempo dura o clareamento?" — o prazo precisa entrar
        // ANTES do match de tratamento, senao o nome do procedimento
        // desviava a resposta para a descricao dele e a pessoa nao
        // recebia a informacao que tinha pedido.
        $duracao = self::querDuracao($norm);
        if ($duracao !== null) {
            return ['ok' => true, 'texto' => $duracao['resposta']
                . ' Posso marcar a avaliação para o Dr. Maycon avaliar o seu caso?'];
        }

        // Quem ja usa aparelho e escreve sobre aperto, arame solto ou
        // quebra procura socorro, nao a descricao do tratamento e
        // tampouco a indicacao. Nada disso esta no site, entao a
        // resposta honesta e encaminhar. Antes da checagem de crianca:
        // "meu filho quebrou o aparelho" e urgencia, nao indicacao.
        if (self::temProblemaNoAparelho($norm)) {
            $estado['etapa'] = 'inicio';
            return ['ok' => true, 'texto' => 'Entendi. Nesses casos o Dr. Maycon precisa ver '
                . 'o que está acontecendo para dizer o que fazer. O WhatsApp é '
                . Knowledge::TELEFONE . '. Se preferir, marco uma avaliação por aqui.'];
        }

        // "meu filho de 8 anos precisa de aparelho" — com crianca na
        // frase a duvida e de indicacao, nao de descricao. A FAQ fala
        // da fase de desenvolvimento; a descricao do tratamento nao.
        if (self::falaDeCrianca($norm)) {
            $faqCrianca = Knowledge::faqPorChave('indicacao');
            if ($faqCrianca !== null) {
                return ['ok' => true, 'texto' => $faqCrianca['resposta']
                    . ' Quer marcar a avaliação?'];
            }
        }

        // "atende sábado?", "abrem domingo?" — resposta vem da tabela
        // horarios, a mesma fonte do site, para nao divergir do rodapé.
        $diaPerguntado = self::querDiaDaSemana($norm);
        if ($diaPerguntado !== null) {
            $textoDias = mb_strtolower(Knowledge::textoDias());
            $nomeDia = mb_strtolower($diaPerguntado['nome']);
            $estado['etapa'] = 'inicio';

            if (in_array($diaPerguntado['nome'], Knowledge::diasDeAtendimento(), true)) {
                return ['ok' => true, 'texto' => 'Sim, atendo ' . $nomeDia . ' 😊 '
                    . 'O atendimento é ' . $textoDias . '. Quer que eu veja os horários livres?'];
            }

            return ['ok' => true, 'texto' => 'Não atendo ' . $nomeDia . '. '
                . 'O atendimento é ' . $textoDias . '. Quer marcar em um desses dias?'];
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
        // Cortesia pura ("obrigado", "até mais") não é pergunta: citar
        // como se fosse dúvida soaria deslocado.
        if (self::ehCortesia($norm)) {
            $estado['etapa'] = 'inicio';
            return ['ok' => true, 'texto' => 'Por nada 😊 Fico por aqui. '
                . 'Quer marcar sua avaliação?'];
        }

        // Nao adianta repetir uma saudacao: a pessoa fez uma pergunta de
        // verdade e merece ver a propria pergunta de volta. O que nao
        // existe no site vai para o Dr. Maycon, sem inventar resposta.
        $assunto = self::assunto($texto);

        return ['ok' => true, 'texto' => $assunto === ''
            ? 'Posso tirar dúvidas sobre os tratamentos e marcar sua avaliação. '
                . 'O que você gostaria de saber?'
            : 'Entendi, você perguntou sobre "' . $assunto . '". Essa informação eu não '
                . 'tenho aqui, então prefiro confirmar com o Dr. Maycon para não te '
                . 'passar nada errado — o WhatsApp é ' . Knowledge::TELEFONE . '. '
                . 'Se quiser, já marco sua avaliação por aqui.'];
    }

    // ===================================================================
    //  etapas
    // ===================================================================

    private static function receberNome(string $texto, string $norm, array &$estado): array
    {
        if (self::ehSaudacao($norm) || self::ehSim($norm)) {
            return ['ok' => true, 'texto' => 'Me diz seu nome, sobrenome e telefone, por favor 😊'];
        }

        $nome = self::extrairNome($texto);
        if ($nome === null) {
            return ['ok' => true, 'texto' => 'Como posso te chamar? Preciso do nome e sobrenome.'];
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

        // se ela já disse o dia, respeita o que ela pediu
        $dia = self::interpretarDia($norm);
        if ($dia !== null) {
            return self::montarDisponibilidade($dia, $estado);
        }

        // sem dia dito: a agenda manda. Nada de perguntar "que dia?".
        return self::oferecerProximoHorario($estado);
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
     * números soltos: "09:30", "9:30", "09h30" e "9h".
     *
     * O 0? é obrigatório dos dois lados: a própria lista sai como
     * "09:30h", então quem digita exatamente o que viu precisa casar.
     */
    private static function escolherSlotDaLista(string $norm, array $slots): ?string
    {
        foreach ($slots as $slot) {
            $hora = date('H:i', (int) strtotime($slot));
            $hh = (int) substr($hora, 0, 2);
            $mm = substr($hora, 3, 2);

            // 09:30 · 9:30 · 09h30
            if (preg_match('/(^|\s)0?' . $hh . '\s*[:h]\s*0?' . $mm . '($|\s)/', $norm) === 1) {
                return $hora;
            }

            // 9h · 09h
            if (preg_match('/(^|\s)0?' . $hh . '\s*h($|\s)/', $norm) === 1) {
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
            // não reabre a lista do dia: anda para a próxima vaga.
            return self::proximoSlot($estado);
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
            return ['ok' => true, 'texto' => 'Não encontrei nenhum horário marcado para '
                . self::primeiroNome($nome) . '. '
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

    /**
     * A pessoa pediu pra marcar e ainda não escolheu nada: quem dita o
     * horário é a agenda, não o paciente. Procura a primeira vaga a
     * partir de hoje e oferece. Nada aqui é inventado — tudo vem de
     * Agenda::disponibilidade(), que já desconta o que está ocupado.
     */
    private static function oferecerProximoHorario(array &$estado): array
    {
        $hoje = date('Y-m-d');

        for ($i = 0; $i < 21; $i++) {
            $dia = date('Y-m-d', strtotime('+' . $i . ' day', strtotime($hoje)));

            $resultado = Tools::executar('consultar_disponibilidade', [
                'data'    => $dia,
                'servico' => (string) ($estado['servico'] ?? ''),
            ], $estado);

            if ($resultado['ok'] && ($estado['slots'] ?? []) !== []) {
                return self::oferecerSlotAtual($estado);
            }
        }

        $estado['etapa'] = 'inicio';
        return ['ok' => true, 'texto' => 'Olha, nos próximos dias a agenda está fechada 😕 '
            . 'O Dr. Maycon consegue te encaixar. O WhatsApp é ' . Knowledge::TELEFONE . '.'];
    }

    /**
     * Oferece o horário em que a lista está parada (estado['indiceSlot']).
     */
    private static function oferecerSlotAtual(array &$estado): array
    {
        $slots = $estado['slots'] ?? [];
        $i = (int) ($estado['indiceSlot'] ?? 0);

        if ($slots === [] || !isset($slots[$i])) {
            return self::proximoSlot($estado);
        }

        $hora = date('H:i', (int) strtotime((string) $slots[$i]));

        $estado['hora'] = $hora;
        $estado['indiceSlot'] = $i;
        $estado['etapa'] = 'aguardando_confirmacao';

        $nome = self::primeiroNome((string) ($estado['nome'] ?? ''));

        return ['ok' => true, 'texto' => ($nome !== '' ? $nome . ', ' : '')
            . 'na agenda encontrei ' . Helpers::dataExtenso((string) $estado['data'])
            . ' às ' . $hora . '. Posso agendar aí?'];
    }

    /**
     * Anda para a próxima vaga: o resto do dia, e se acabar, o próximo
     * dia em que a agenda abre.
     */
    private static function proximoSlot(array &$estado): array
    {
        $slots = $estado['slots'] ?? [];
        $i = (int) ($estado['indiceSlot'] ?? 0) + 1;

        if (isset($slots[$i])) {
            $estado['indiceSlot'] = $i;
            return self::oferecerSlotAtual($estado);
        }

        $de = (string) ($estado['data'] ?? date('Y-m-d'));

        for ($d = 1; $d <= 21; $d++) {
            $dia = date('Y-m-d', strtotime('+' . $d . ' day', strtotime($de)));

            $resultado = Tools::executar('consultar_disponibilidade', [
                'data'    => $dia,
                'servico' => (string) ($estado['servico'] ?? ''),
            ], $estado);

            if ($resultado['ok'] && ($estado['slots'] ?? []) !== []) {
                $estado['indiceSlot'] = 0;
                return self::oferecerSlotAtual($estado);
            }
        }

        $estado['etapa'] = 'inicio';
        return ['ok' => true, 'texto' => 'Essa foi a última vaga que encontrei por aqui 😕 '
            . 'O Dr. Maycon consegue te encaixar. O WhatsApp é ' . Knowledge::TELEFONE . '.'];
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

    /**
     * Dor, inchaco, urgencia. Nao existe promessa possivel aqui: o site
     * nao fala de atendimento de urgencia, entao a resposta so oferece
     * o caminho mais rapido sem garantir hora.
     */
    private static function temDor(string $norm): bool
    {
        foreach (['dor', 'doendo', 'doer', 'doi', 'inchac', 'inchad', 'incha ',
            'abscesso', 'inflam', 'nao aguento', 'emergencia', 'urgencia',
            'sensibilidade'] as $c) {
            if (str_contains($norm, $c)) {
                return true;
            }
        }
        return false;
    }

    /** convenio e plano de saude: o site nao informa nada sobre isso */
    private static function querConvenio(string $norm): bool
    {
        foreach (['convenio', 'plano de saude', 'plano odontologico', 'iodonto'] as $c) {
            if (str_contains($norm, $c)) {
                return true;
            }
        }
        return false;
    }

    /** formas de pagamento: tambem nao existe no site */
    private static function querFormaDePagamento(string $norm): bool
    {
        foreach (['forma de pagamento', 'formas de pagamento', 'cartao', 'pix',
            'dinheiro', 'no debito', 'no credito', 'parcelam', 'parcela',
            'parcelamento', 'divide'] as $c) {
            if (str_contains($norm, $c)) {
                return true;
            }
        }
        return false;
    }

    /** crianca ou idade na frase: a duvida muda de descricao para indicacao */
    private static function falaDeCrianca(string $norm): bool
    {
        // exige numero antes de "anos": sozinho, "faz anos que" nao fala de crianca
        if (preg_match('/\b\d{1,2}\s*anos\b/', $norm) === 1) {
            return true;
        }

        foreach (['filho', 'filha', 'crianca', 'menino', 'menina', 'bebe', 'guri',
            'meu filho', 'minha filha'] as $c) {
            if (str_contains($norm, $c)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Aperto, arame solto, quebra: socorro de quem ja usa aparelho.
     *
     * Exige a palavra "aparelho" na frase. Sem essa checagem,
     * "quebrei meu dente" caia aqui e perdia a descricao de prótese,
     * que era a resposta que a pessoa queria.
     */
    private static function temProblemaNoAparelho(string $norm): bool
    {
        $situacoes = ['manutencao', 'aperto', 'apertar', 'apertou', 'quebrou', 'quebrei',
            'quebrado', 'descolou', 'soltou', 'solta', 'machuc', 'bati', 'caiu',
            'arame', 'pinca', 'grudou', 'fio', 'sem o aparelho', 'sem o tratamento'];

        $temSituacao = false;
        foreach ($situacoes as $c) {
            if (str_contains($norm, $c)) {
                $temSituacao = true;
                break;
            }
        }

        if (!$temSituacao) {
            return false;
        }

        foreach (['aparelho', 'aparelhos', 'braces', 'ortodontico', 'fio'] as $a) {
            if (str_contains($norm, $a)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pergunta de prazo. Devolve a entrada do FAQ quando a frase pede
     * por tempo — assim o texto continua vindo de um lugar so.
     *
     * @return array{chaves:array,pergunta:string,resposta:string}|null
     */
    private static function querDuracao(string $norm): ?array
    {
        $chaves = ['quanto tempo', 'demora', 'prazo', 'duracao', 'tempo de tratamento',
            'quantas sessoes', 'quantas secoes', 'quantas consultas', 'quanto tempo leva'];

        $bateu = false;
        foreach ($chaves as $c) {
            if (str_contains($norm, $c)) {
                $bateu = true;
                break;
            }
        }

        if (!$bateu) {
            return null;
        }

        foreach (Knowledge::faq() as $f) {
            if (in_array('quanto tempo', (array) $f['chaves'], true)) {
                return $f;
            }
        }

        return null;
    }

    /**
     * "atende sabado?", "abrem domingo?" — pergunta por um dia especifico.
     *
     * @return array{num:int,nome:string}|null
     */
    private static function querDiaDaSemana(string $norm): ?array
    {
        $dias = [
            'domingo' => 0,
            'segunda'  => 1,
            'terca'    => 2,
            'quarta'   => 3,
            'quinta'   => 4,
            'sexta'    => 5,
            'sabado'   => 6,
        ];

        foreach ($dias as $nome => $num) {
            if (str_contains($norm, $nome)) {
                return ['num' => $num, 'nome' => Helpers::diaSemana($num)];
            }
        }

        return null;
    }

    /** cortesia que nao é pergunta nem resposta de fluxo */
    private static function ehCortesia(string $norm): bool
    {
        $chaves = ['obrigado', 'obrigada', 'valeu', 'vlw', 'agradeco', 'agradecida',
            'de nada', 'tchau', 'ate mais', 'flw'];

        return in_array(trim($norm), $chaves, true);
    }

    /**
     * Resume a pergunta em poucas palavras, para a resposta poder citar
     * o que a pessoa perguntou. Sem isso, "tem estacionamento?" era
     * respondido com uma saudacao — nada a ver com a pergunta.
     */
    private static function assunto(string $texto): string
    {
        $limpo = trim((string) preg_replace('/[?!.]+$/u', '', trim($texto)));
        $limpo = trim((string) preg_replace('/\s+/u', ' ', $limpo));

        if (mb_strlen($limpo) > 46) {
            $limpo = mb_substr($limpo, 0, 46);
            $corte = mb_strrpos($limpo, ' ');
            if ($corte !== false && $corte > 16) {
                $limpo = mb_substr($limpo, 0, $corte);
            }
            $limpo .= '…';
        }

        return $limpo;
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

    /**
     * O paciente apertou o botão "Tenho dúvidas" da abertura (ou
     * escreveu algo equivalente, curto e solto). Comparação exata:
     * uma dúvida de verdade como "tenho dúvidas sobre aparelho" tem
     * de ir para a base de conhecimento, não para cá.
     */
    private static function querTirarDuvida(string $norm): bool
    {
        return in_array($norm, [
            'duvida', 'duvidas', 'tenho duvida', 'tenho duvidas',
            'tenho uma duvida', 'tenho umas duvidas', 'uma duvida',
            'quero tirar duvida', 'tirar uma duvida', 'tenho perguntas',
            'tenho uma pergunta', 'perguntas',
        ], true);
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
            || self::temDor($norm)
            || self::querConvenio($norm)
            || self::querFormaDePagamento($norm)
            || Knowledge::tratamentoPorNome($norm) !== null
            || Knowledge::faqPorTexto($norm) !== null
            || self::querHorarioDeAtendimento($norm)
            || self::querDiaDaSemana($norm) !== null
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

    /**
     * Só o primeiro nome, para a conversa. A assistente não repete o
     * nome inteiro nem o telefone: chama pelo primeiro nome e segue.
     * O nome completo continua no cadastro, não aqui.
     */
    private static function primeiroNome(string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '') {
            return '';
        }
        $partes = explode(' ', $nome);
        return (string) $partes[0];
    }

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

        // Corta no que não é nome. Sem isto, "Meu nome é Ana Teste,
        // telefone 69 99123-4567" virava o nome inteiro do paciente.
        // O possessivo entra junto: "renata souza, meu telefone é..."
        // tem de parar no "meu", senão o nome vira "Renata Souza, Meu".
        $limpa = preg_replace(
            '/\b(meu|minha|seu|sua)?\s*(telefone|celular|whats(app)?|fone|n[úu]mero)\b.*$/iu',
            '',
            $limpa
        ) ?? $limpa;

        // telefone solto no fim, mesmo sem a palavra "telefone". Aceita
        // colado na vírgula e sem DDD: "indra lumia,99245052" — o
        // regex antigo pedia DDD + 8 dígitos e deixava passar.
        $limpa = preg_replace('/[\s,;(\-]*\+?\d[\d\s.\-()]{5,}\d\s*$/u', '', $limpa) ?? $limpa;

        $limpa = preg_replace('/\s*(e sou|meu nome [eé]|me chamo|sou o|sou a)\s*/iu', ' ', $limpa) ?? $limpa;

        // artigo antes do nome: "é a Maria" vira "Maria". O \b é
        // obrigatório: sem ele, "é a" casa dentro de "é Ana" e a
        // primeira letra do nome some.
        $limpa = preg_replace('/\b([ée]) (o|a|os|as)\s+/iu', ' ', $limpa) ?? $limpa;

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

        // "João Pedro da Silva" é nome legítimo: artigo de sobrenome
        // não pode derrubar o cadastro. Contamos só as palavras de
        // verdade e deixamos "de/da/do/dos/das" passarem no meio.
        $reais = array_values(array_filter($palavras, static function (string $p): bool {
            return !in_array(mb_strtolower($p), ['de', 'do', 'da', 'dos', 'das', 'e'], true);
        }));

        if ($reais === []) {
            return null;
        }

        foreach ($reais as $p) {
            if (mb_strlen($p) < 2) {
                return null;
            }
        }

        // Uma palavra de verdade só também é nome: "Renata", "Lu",
        // "João". O que não pode é engolir "sexta" ou "amanhã" como
        // nome de paciente.
        if (count($reais) === 1
            && in_array(mb_strtolower($reais[0]), self::naoEhNome(), true)) {
            return null;
        }

        $titulo = mb_convert_case($limpa, MB_CASE_TITLE, 'UTF-8');

        // Artigo de sobrenome não capitaliza: "João Pedro da Silva",
        // não "João Pedro Da Silva". Só a primeira palavra muda.
        $partes = explode(' ', $titulo);
        $artigos = ['da', 'de', 'do', 'das', 'dos', 'e'];

        foreach ($partes as $i => $p) {
            if ($i > 0 && in_array(mb_strtolower($p), $artigos, true)) {
                $partes[$i] = mb_strtolower($p, 'UTF-8');
            }
        }

        return implode(' ', $partes);
    }

    /**
     * Palavras que podem chegar quando o agente pede o nome, mas não
     * são nome: dias, respostas e assuntos do consultório.
     *
     * @return array<int, string>
     */
    private static function naoEhNome(): array
    {
        return ['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo',
            'amanha', 'hoje', 'ontem', 'depois', 'antes', 'semana',
            'sim', 'nao', 'obrigado', 'obrigada', 'valeu', 'ok', 'tchau',
            'agendar', 'marcar', 'avaliacao', 'cancelar', 'remarcar',
            'preco', 'valor', 'horario', 'horarios', 'endereco', 'telefone',
            'whatsapp', 'convenio', 'pagamento', 'clinica', 'consultorio'];
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