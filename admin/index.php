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

// cancelar um agendamento
$cancelar = (int) Helpers::e('cancelar', 0);
if ($cancelar > 0) {
    Helpers::exigirCsrf();

    $resultado = Agenda::cancelar($cancelar);

    if ($resultado['ok']) {
        Session::flash('Agendamento cancelado.', 'ok');
    } else {
        Session::flash($resultado['erro'] ?? 'Não foi possível cancelar.', 'erro');
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
        echo '  <td><span class="adm-status ' . $classeStatus . '">' . Helpers::escapar(ucfirst($a['status'])) . '</span></td>';
        echo '  <td><span class="adm-origem">' . Helpers::escapar($a['origem']) . '</span></td>';
        echo '  <td class="adm-col-acoes">';

        if ($a['status'] !== 'cancelado') {
            echo '<a class="adm-acao" href="agendamento.php?id=' . $idAg . '">Remarcar</a>';

            if ($a['status'] !== 'concluido') {
                echo '<form method="post" class="adm-inline">'
                   . '<input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">'
                   . '<input type="hidden" name="id" value="' . $idAg . '">'
                   . '<input type="hidden" name="status" value="concluido">'
                   . '<button class="adm-acao adm-acao--ok" type="submit" title="Marcar como atendido">Atendido</button>'
                   . '</form>';
            }

            echo '<form method="post" class="adm-inline" onsubmit="return confirm(\'Cancelar este agendamento?\')">'
               . '<input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">'
               . '<input type="hidden" name="cancelar" value="' . $idAg . '">'
               . '<button class="adm-acao adm-acao--perigo" type="submit">Cancelar</button>'
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