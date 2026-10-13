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

Views::inicio('Agenda do dia', 'agenda');

echo '<div class="adm-container">';

if ($flash !== null) {
    Views::feedback(
        $flash['tipo'] === 'ok' ? $flash['mensagem'] : null,
        $flash['tipo'] === 'ok' ? null : $flash['mensagem']
    );
}

Views::pagina(
    ucfirst(Helpers::dataExtenso($data)),
    $totalDia === 0
        ? 'Nenhum agendamento neste dia.'
        : $totalConfirmados . ' confirmado(s) de ' . $totalDia . ' no total.'
);

echo '<form method="get" class="adm-barra-datas">';
echo '  <a class="adm-btn adm-btn--sutil' . ($data === Helpers::hoje() ? ' is-ativo' : '') . '" href="?data=' . Helpers::hoje() . '">Hoje</a>';
echo '  <a class="adm-btn adm-btn--sutil" href="?data=' . $ontem . '">←</a>';
echo '  <input type="date" name="data" value="' . Helpers::escapar($data) . '" class="adm-input adm-input--data">';
echo '  <a class="adm-btn adm-btn--sutil" href="?data=' . $amanha . '">→</a>';
echo '  <button type="submit" class="adm-btn adm-btn--primario">Ver</button>';
echo '  <a class="adm-btn adm-btn--novo" href="agendamento.php?data=' . Helpers::escapar($data) . '">+ Novo agendamento</a>';
echo '</form>';

if ($agendamentos === []) {
    echo '<div class="adm-vazio">';
    echo '  <p>Nenhum agendamento para este dia.</p>';
    echo '  <a class="adm-btn adm-btn--primario" href="agendamento.php?data=' . Helpers::escapar($data) . '">Criar o primeiro</a>';
    echo '</div>';
} else {
    echo '<div class="adm-tabela-wrap">';
    echo '<table class="adm-tabela">';
    echo '<thead><tr><th>Hora</th><th>Paciente</th><th>Serviço</th><th>Status</th><th>Origem</th><th class="adm-col-acoes">Ações</th></tr></thead>';
    echo '<tbody>';

    $csrf = Helpers::csrfToken();
    $base = Helpers::baseUrl();

    foreach ($agendamentos as $a) {
        $idAg = (int) $a['id'];
        $classeStatus = match ($a['status']) {
            'pendente'   => 'is-pendente',
            'confirmado' => 'is-confirmado',
            'cancelado'  => 'is-cancelado',
            'concluido'  => 'is-concluido',
            default      => '',
        };

        echo '<tr>';
        echo '  <td class="adm-hora"><strong>' . Helpers::escapar(Helpers::horaBr($a['hora_inicio'])) . '</strong>'
           . '<span class="adm-hora-fim">até ' . Helpers::escapar(Helpers::horaBr($a['hora_fim'])) . '</span></td>';
        echo '  <td><a href="pacientes.php?id=' . (int) $a['paciente_id'] . '">' . Helpers::escapar($a['paciente_nome']) . '</a>'
           . ($a['paciente_telefone'] ? '<span class="adm-sub">' . Helpers::escapar($a['paciente_telefone']) . '</span>' : '') . '</td>';
        echo '  <td>' . Helpers::escapar($a['servico_nome']) . '<span class="adm-sub">' . (int) $a['duracao_minutos'] . ' min</span></td>';
        // Pendente ainda não tem nome na agenda: o Dr. vê o horário
        // reservado e decide se confirma. Só depois vira "Confirmado".
        $rotuloStatus = $a['status'] === 'pendente' ? '—' : ucfirst((string) $a['status']);
        echo '  <td><span class="adm-status ' . $classeStatus . '">' . Helpers::escapar($rotuloStatus) . '</span></td>';
        echo '  <td><span class="adm-origem">' . Helpers::escapar($a['origem']) . '</span></td>';
        echo '  <td class="adm-col-acoes">';

        if ($a['status'] !== 'cancelado') {
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
                echo '<a class="adm-acao adm-acao--wpp" href="' . Helpers::escapar($linkWpp) . '"'
                   . ' target="_blank" rel="noopener noreferrer"'
                   . ' title="Abre o WhatsApp com a mensagem pronta. A confirmação só conta quando o paciente responder.">'
                   . 'Confirmar pelo WhatsApp</a>';
            } else {
                echo '<span class="adm-acao adm-acao--inativo"'
                   . ' title="Sem telefone válido no cadastro deste paciente. Abra a ficha dele para corrigir.">'
                   . 'Confirmar pelo WhatsApp</span>';
            }

            // O Dr. confirma depois de ouvir o paciente (WhatsApp, telefone,
            // na recepção). Só aparece enquanto a ida ainda não foi confirmada.
            if ($a['status'] === 'pendente') {
                echo '<form method="post" class="adm-inline">'
                   . '<input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">'
                   . '<input type="hidden" name="id" value="' . $idAg . '">'
                   . '<input type="hidden" name="status" value="confirmado">'
                   . '<button class="adm-acao adm-acao--confirmar" type="submit"'
                   . ' title="Marca a ida do paciente como confirmada.">Confirmado</button>'
                   . '</form>';
            }

            echo '<form method="post" class="adm-inline">'
               . '<input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">'
               . '<input type="hidden" name="id" value="' . $idAg . '">'
               . '<input type="hidden" name="status" value="concluido">'
               . '<button class="adm-acao adm-acao--ok" type="submit" title="Marcar como atendido">Atendido</button>'
               . '</form>';

            echo '<form method="post" class="adm-inline" onsubmit="return confirm(\'Excluir da agenda? O registro fica no histórico como cancelado.\')">'
               . '<input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">'
               . '<input type="hidden" name="excluir" value="' . $idAg . '">'
               . '<button class="adm-acao adm-acao--perigo" type="submit">Excluir</button>'
               . '</form>';
        }

        echo '  </td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

echo '<p class="adm-nota">Aparecem aqui os agendamentos feitos pelo site (chat) e pelo próprio painel.</p>';
echo '</div>';

Views::fim();