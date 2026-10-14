<?php
/**
 * admin/index.php — visao do dia
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/exigir-login.php';
require_once dirname(__DIR__) . '/app/views/Views.php';
require_once dirname(__DIR__) . '/app/agenda/Agenda.php';

$data = (string) Helpers::e('data', Helpers::hoje());
if (!Helpers::dataValida($data)) {
    $data = Helpers::hoje();
}

$amanha = date('Y-m-d', strtotime('+1 day', strtotime($data)));
$ontem = date('Y-m-d', strtotime('-1 day', strtotime($data)));

$flash = Session::pegaFlash();

// "Excluir" da agenda: o agendamento sai do dia, mas o registro fica
// no banco com status cancelado, para não perder o histórico.
$excluir = (int) Helpers::e('excluir', 0);
if ($excluir > 0) {
    Helpers::exigirCsrf();

    $resultado = Agenda::cancelar($excluir);

    if ($resultado['ok']) {
        Session::flash('Agendamento excluído da agenda. O histórico foi mantido.', 'ok');
    } else {
        Session::flash($resultado['erro'] ?? 'Não foi possível excluir.', 'erro');
    }

    Helpers::redirecionar(Helpers::baseUrl() . '/admin/?data=' . urlencode($data));
}

// marcar como atendido
$statusPedido = (string) Helpers::e('status', '');
$idStatus = (int) Helpers::e('id', 0);

if ($idStatus > 0 && in_array($statusPedido, ['concluido', 'confirmado'], true)) {
    Helpers::exigirCsrf();

    $ag = Agenda::agendamento($idStatus);

    if ($ag === null) {
        Session::flash('Agendamento não encontrado.', 'erro');
    } elseif ($ag['status'] === 'cancelado') {
        Session::flash('Não é possível alterar um agendamento cancelado.', 'erro');
    } else {
        Database::query('UPDATE agendamentos SET status = ? WHERE id = ?', [$statusPedido, $idStatus]);

        $rotulo = match ($statusPedido) {
            'concluido' => 'marcado como atendido',
            default     => 'marcado como confirmado',
        };

        Session::flash('Agendamento ' . $rotulo . '.', 'ok');
    }

    Helpers::redirecionar(Helpers::baseUrl() . '/admin/?data=' . urlencode($data));
}

$agendamentos = Agenda::doDia($data);

$totalDia = count($agendamentos);
$totalConfirmados = count(array_filter($agendamentos, static fn ($a) => $a['status'] === 'confirmado'));
$totalPendentes = count(array_filter($agendamentos, static fn ($a) => $a['status'] === 'pendente'));
$totalConcluidos = count(array_filter($agendamentos, static fn ($a) => $a['status'] === 'concluido'));
$totalCancelados = count(array_filter($agendamentos, static fn ($a) => $a['status'] === 'cancelado'));

Views::inicio('Agenda do dia', 'agenda');

echo '<div class="adm-container adm-container--agenda">';

if ($flash !== null) {
    Views::feedback(
        $flash['tipo'] === 'ok' ? $flash['mensagem'] : null,
        $flash['tipo'] === 'ok' ? null : $flash['mensagem']
    );
}

/* ---------------- topo: dia + navegação ---------------- */
echo '<section class="adm-agenda-topo">';

echo '  <div class="adm-agenda-ident">';
echo '    <span class="adm-agenda-etiqueta">' . Views::icone('calendario', 14) . 'Agenda do dia</span>';
echo '    <h1>' . Helpers::escapar(ucfirst(Helpers::dataExtenso($data))) . '</h1>';
echo '    <p class="adm-agenda-sub">' . ($totalDia === 0
    ? 'Nenhum agendamento neste dia.'
    : $totalConfirmados . ' confirmado(s) de ' . $totalDia . ' no total.') . '</p>';
echo '  </div>';

echo '  <form method="get" class="adm-agenda-nav">';
echo '    <a class="adm-btn adm-btn--sutil' . ($data === Helpers::hoje() ? ' is-ativo' : '') . '"'
   . ' href="?data=' . Helpers::hoje() . '">Hoje</a>';
echo '    <div class="adm-agenda-setas">';
echo '      <a class="adm-agenda-seta" href="?data=' . $ontem . '" title="Dia anterior" aria-label="Dia anterior">'
   . Views::icone('esquerda', 16) . '</a>';
echo '      <input type="date" name="data" value="' . Helpers::escapar($data) . '" class="adm-agenda-data" aria-label="Escolher a data">';
echo '      <a class="adm-agenda-seta" href="?data=' . $amanha . '" title="Próximo dia" aria-label="Próximo dia">'
   . Views::icone('direita', 16) . '</a>';
echo '    </div>';
echo '    <button type="submit" class="adm-btn adm-btn--primario">Ver agenda</button>';
echo '    <a class="adm-btn adm-btn--novo adm-btn--agenda-novo" href="agendamento.php?data=' . Helpers::escapar($data) . '">'
   . Views::icone('mais', 16) . 'Novo agendamento</a>';
echo '  </form>';

echo '</section>';

/* ---------------- resumo do dia ---------------- */
echo '<div class="adm-agenda-stats">';

$resumo = [
    ['total', 'Agendamentos', $totalDia, 'pessoa', 'Neste dia'],
    ['pendente', 'Aguardando', $totalPendentes, 'relogio', 'Falta confirmar a ida'],
    ['confirmado', 'Confirmados', $totalConfirmados, 'check-circ', 'Presença garantida'],
    ['concluido', 'Atendidos', $totalConcluidos, 'check', 'Consulta já realizada'],
];

foreach ($resumo as [$tom, $rotulo, $valor, $desenho, $dica]) {
    echo '<div class="adm-stat adm-stat--' . $tom . '">';
    echo '  <div class="adm-stat-topo">';
    echo '    <span class="adm-stat-rotulo">' . Helpers::escapar($rotulo) . '</span>';
    echo '    <span class="adm-stat-icone">' . Views::icone($desenho, 16) . '</span>';
    echo '  </div>';
    echo '  <strong class="adm-stat-valor">' . $valor . '</strong>';
    echo '  <span class="adm-stat-dica">' . Helpers::escapar($dica) . '</span>';
    echo '</div>';
}

echo '</div>';

/* ---------------- lista do dia ---------------- */
if ($agendamentos === []) {
    echo '<div class="adm-vazio adm-vazio--agenda">';
    echo '  <span class="adm-vazio-icone" aria-hidden="true">' . Views::icone('vazio', 26) . '</span>';
    echo '  <h2>Nenhum agendamento para este dia</h2>';
    echo '  <p>O dia está livre. Crie um agendamento ou escolha outra data acima.</p>';
    echo '  <a class="adm-btn adm-btn--primario" href="agendamento.php?data=' . Helpers::escapar($data) . '">Criar o primeiro</a>';
    echo '</div>';
} else {
    echo '<div class="adm-agenda-lista">';

    $csrf = Helpers::csrfToken();

    foreach ($agendamentos as $a) {
        $idAg = (int) $a['id'];
        $status = (string) $a['status'];

        $classeStatus = match ($status) {
            'pendente'   => 'is-pendente',
            'confirmado' => 'is-confirmado',
            'cancelado'  => 'is-cancelado',
            'concluido'  => 'is-concluido',
            default      => '',
        };

        // Pendente ainda não tem nome na agenda: o Dr. vê o horário
        // reservado e decide se confirma. Só depois vira "Confirmado".
        $rotuloStatus = match ($status) {
            'pendente'   => 'Aguardando',
            'concluido'  => 'Atendido',
            'cancelado'  => 'Cancelado',
            'confirmado' => 'Confirmado',
            default      => ucfirst($status),
        };

        $rotuloOrigem = match ((string) $a['origem']) {
            'site'  => 'Site',
            'admin' => 'Painel',
            'ia'    => 'Agente IA',
            default => ucfirst((string) $a['origem']),
        };

        echo '<article class="adm-ag adm-ag--' . $classeStatus . '">';

        // horário
        echo '  <div class="adm-ag-hora">';
        echo '    <strong>' . Helpers::escapar(Helpers::horaBr($a['hora_inicio'])) . '</strong>';
        echo '    <span>até ' . Helpers::escapar(Helpers::horaBr($a['hora_fim'])) . '</span>';
        echo '  </div>';

        // quem é, o que é e de onde veio
        echo '  <div class="adm-ag-corpo">';
        echo '    <div class="adm-ag-linha">';
        echo '      <a class="adm-ag-nome" href="pacientes.php?id=' . (int) $a['paciente_id'] . '">'
           . Helpers::escapar($a['paciente_nome']) . '</a>';
        echo '      <span class="adm-status ' . $classeStatus . '">' . Helpers::escapar($rotuloStatus) . '</span>';
        echo '    </div>';
        echo '    <p class="adm-ag-meta">';
        echo '      <span class="adm-ag-servico">' . Helpers::escapar($a['servico_nome'])
           . ' · ' . (int) $a['duracao_minutos'] . ' min</span>';
        if ($a['paciente_telefone']) {
            echo '      <span class="adm-ag-item">' . Views::icone('telefone', 13) . Helpers::escapar($a['paciente_telefone']) . '</span>';
        }
        echo '      <span class="adm-ag-item adm-ag-item--origem">' . Helpers::escapar($rotuloOrigem) . '</span>';
        echo '    </p>';
        echo '  </div>';

        // ações
        echo '  <div class="adm-ag-acoes">';

        if ($status !== 'cancelado') {
            // Só abre o WhatsApp com a mensagem pronta — o Dr. Maycon envia
            // e o paciente responde. Nada aqui mexe no status da consulta.
            $mensagemWpp = 'Olá, ' . $a['paciente_nome'] . "! 😊\n\n"
                . 'Lembrete da sua consulta no consultório do Dr. Maycon Gomes:\n'
                . '📅 ' . Helpers::dataExtenso((string) $a['data']) . "\n"
                . '🕐 ' . Helpers::horaBr((string) $a['hora_inicio']) . "\n\n"
                . 'Você confirma a presença? Se precisar remarcar, é só me dizer por aqui mesmo.\n\n'
                . 'Obrigado!';

            $linkWpp = Helpers::whatsappLink(
                $a['paciente_telefone'] !== null ? (string) $a['paciente_telefone'] : '',
                $mensagemWpp
            );

            if ($linkWpp !== null) {
                echo '<a class="adm-ag-btn adm-ag-btn--wpp" href="' . Helpers::escapar($linkWpp) . '"'
                   . ' target="_blank" rel="noopener noreferrer"'
                   . ' title="Abre o WhatsApp com a mensagem pronta. A confirmação só conta quando o paciente responder.">'
                   . Views::icone('telefone', 15) . 'WhatsApp</a>';
            } else {
                echo '<span class="adm-ag-btn adm-ag-btn--inativo"'
                   . ' title="Sem telefone válido no cadastro deste paciente. Abra a ficha dele para corrigir.">'
                   . Views::icone('telefone', 15) . 'WhatsApp</span>';
            }

            // O Dr. confirma depois de ouvir o paciente (WhatsApp, telefone,
            // na recepção). Só aparece enquanto a ida ainda não foi confirmada.
            if ($status === 'pendente') {
                echo '<form method="post" class="adm-inline">'
                   . '<input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">'
                   . '<input type="hidden" name="id" value="' . $idAg . '">'
                   . '<input type="hidden" name="status" value="confirmado">'
                   . '<button class="adm-ag-btn adm-ag-btn--confirmar" type="submit"'
                   . ' title="Marca a ida do paciente como confirmada.">' . Views::icone('check', 15) . 'Confirmado</button>'
                   . '</form>';
            }

            echo '<form method="post" class="adm-inline">'
               . '<input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">'
               . '<input type="hidden" name="id" value="' . $idAg . '">'
               . '<input type="hidden" name="status" value="concluido">'
               . '<button class="adm-ag-btn adm-ag-btn--ok" type="submit" title="Marcar como atendido">'
               . Views::icone('check-circ', 15) . 'Atendido</button>'
               . '</form>';

            echo '<form method="post" class="adm-inline" onsubmit="return confirm(\'Excluir da agenda? O registro fica no histórico como cancelado.\')">'
               . '<input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">'
               . '<input type="hidden" name="excluir" value="' . $idAg . '">'
               . '<button class="adm-ag-btn adm-ag-btn--perigo" type="submit" title="Sai da agenda e fica no histórico como cancelado">'
               . Views::icone('lixeira', 15) . 'Excluir</button>'
               . '</form>';
        } else {
            echo '<span class="adm-ag-cancelado">Cancelado — só aparece no histórico</span>';
        }

        echo '  </div>';
        echo '</article>';
    }

    echo '</div>';
}

/* ---------------- legenda dos botões ---------------- */
echo '<details class="adm-agenda-ajuda">';
echo '  <summary>' . Views::icone('ajuda', 15) . 'O que cada status e cada botão fazem?</summary>';
echo '  <ul>';
echo '    <li><strong>Aguardando:</strong> o horário está reservado, mas a ida do paciente ainda não foi confirmada.</li>';
echo '    <li><strong>Confirmado:</strong> o paciente disse que vem (WhatsApp, telefone ou recepção). É o botão principal do dia.</li>';
echo '    <li><strong>Atendido:</strong> a consulta já aconteceu — use para fechar o atendimento.</li>';
echo '    <li><strong>WhatsApp:</strong> abre a conversa com o lembrete já escrito. Não muda o status sozinho — quem confirma é o paciente, respondendo por aqui.</li>';
echo '    <li><strong>Excluir:</strong> tira o atendimento deste dia e guarda o histórico.</li>';
echo '  </ul>';
echo '</details>';

echo '<p class="adm-nota">Aparecem aqui os agendamentos feitos pelo site (chat) e pelo próprio painel. '
   . ($totalCancelados > 0 ? $totalCancelados . ' cancelado(s) neste dia. ' : '') . '</p>';

echo '</div>';

Views::fim();