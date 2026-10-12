<?php
/**
 * admin/horarios.php — expediente por dia da semana
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/exigir-login.php';
require_once dirname(__DIR__) . '/app/views/Views.php';

$erro = null;
$ok = null;
$flash = Session::pegaFlash();

if ($flash !== null) {
    if ($flash['tipo'] === 'ok') {
        $ok = $flash['mensagem'];
    } else {
        $erro = $flash['mensagem'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Helpers::exigirCsrf();

    $acao = (string) Helpers::post('acao', '');

    try {
        if ($acao === 'excluir') {
            Database::query('DELETE FROM horarios WHERE id = ?', [(int) Helpers::post('id', 0)]);
            Session::flash('Horário removido.');
        } elseif ($acao === 'salvar') {
            $id = (int) Helpers::post('id', 0);
            $dia = (int) Helpers::post('dia_semana', 0);
            $inicio = (string) Helpers::post('hora_inicio', '');
            $fim = (string) Helpers::post('hora_fim', '');
            $ativo = Helpers::post('ativo') !== null ? 1 : 0;

            if ($dia < 0 || $dia > 6) {
                throw new RuntimeException('Escolha um dia da semana.');
            }

            $mi = Helpers::horaParaMinutos($inicio);
            $mf = Helpers::horaParaMinutos($fim);

            if ($mi < 0 || $mf <= $mi || $mf > 1440) {
                throw new RuntimeException('O horário final precisa ser depois do inicial.');
            }

            if ($id > 0) {
                Database::query(
                    'UPDATE horarios SET dia_semana = ?, hora_inicio = ?, hora_fim = ?, ativo = ? WHERE id = ?',
                    [$dia, $inicio . ':00', $fim . ':00', $ativo, $id]
                );
                Session::flash('Horário atualizado.');
            } else {
                Database::query(
                    'INSERT INTO horarios (dia_semana, hora_inicio, hora_fim, ativo) VALUES (?, ?, ?, ?)',
                    [$dia, $inicio . ':00', $fim . ':00', $ativo]
                );
                Session::flash('Horário adicionado.');
            }
        }
    } catch (Throwable $e) {
        Session::flash($e->getMessage(), 'erro');
    }

    Helpers::redirecionar(Helpers::baseUrl() . '/admin/horarios.php');
}

$horarios = Database::todos('SELECT * FROM horarios ORDER BY dia_semana, hora_inicio');

Views::inicio('Horários', 'horarios');

echo '<div class="adm-container">';
Views::pagina('Horários de atendimento', 'É dentro destas faixas que o assistente oferece horários.');
Views::feedback($ok, $erro);

echo '<div class="adm-tabela-wrap">';
echo '<table class="adm-tabela">';
echo '<thead><tr><th>Dia</th><th>De</th><th>Até</th><th>Status</th><th class="adm-col-acoes">Ações</th></tr></thead>';
echo '<tbody>';

if ($horarios === []) {
    echo '<tr><td colspan="5">Nenhum horário cadastrado.</td></tr>';
}

foreach ($horarios as $h) {
    echo '<tr>';
    echo '  <td><strong>' . Helpers::escapar(Helpers::diaSemana((int) $h['dia_semana'])) . '</strong></td>';
    echo '  <td>' . Helpers::escapar(Helpers::horaBr($h['hora_inicio'])) . '</td>';
    echo '  <td>' . Helpers::escapar(Helpers::horaBr($h['hora_fim'])) . '</td>';
    echo '  <td><span class="adm-status ' . ($h['ativo'] ? 'is-confirmado' : 'is-cancelado') . '">'
       . ($h['ativo'] ? 'Ativo' : 'Inativo') . '</span></td>';
    echo '  <td class="adm-col-acoes">';
    echo '    <a class="adm-acao" href="#editar-' . (int) $h['id'] . '">Editar</a>';
    echo '    <form method="post" class="adm-inline" onsubmit="return confirm(\'Remover este horário?\')">';
    echo '      <input type="hidden" name="csrf" value="' . Helpers::escapar(Helpers::csrfToken()) . '">';
    echo '      <input type="hidden" name="acao" value="excluir">';
    echo '      <input type="hidden" name="id" value="' . (int) $h['id'] . '">';
    echo '      <button class="adm-acao adm-acao--perigo" type="submit">Remover</button>';
    echo '    </form>';
    echo '  </td>';
    echo '</tr>';

    echo '<tr class="adm-linha-form" id="editar-' . (int) $h['id'] . '">';
    echo '  <td colspan="5"><form method="post" class="adm-form adm-form--linha">';
    echo '    <input type="hidden" name="csrf" value="' . Helpers::escapar(Helpers::csrfToken()) . '">';
    echo '    <input type="hidden" name="acao" value="salvar">';
    echo '    <input type="hidden" name="id" value="' . (int) $h['id'] . '">';
    echo '    <select name="dia_semana">';
    for ($d = 0; $d <= 6; $d++) {
        $sel = ((int) $h['dia_semana'] === $d) ? ' selected' : '';
        echo '<option value="' . $d . '"' . $sel . '>' . Helpers::escapar(Helpers::diaSemana($d)) . '</option>';
    }
    echo '    </select>';
    echo '    <input type="time" name="hora_inicio" value="' . Helpers::escapar(substr($h['hora_inicio'], 0, 5)) . '" required>';
    echo '    <input type="time" name="hora_fim" value="' . Helpers::escapar(substr($h['hora_fim'], 0, 5)) . '" required>';
    echo '    <label class="adm-check"><input type="checkbox" name="ativo" value="1"'
       . ($h['ativo'] ? ' checked' : '') . '> ativo</label>';
    echo '    <button type="submit" class="adm-btn adm-btn--primario adm-btn--pequeno">Salvar</button>';
    echo '  </form></td>';
    echo '</tr>';
}

echo '</tbody></table></div>';

echo '<details class="adm-card adm-card--novo">';
echo '<summary>+ Novo horário</summary>';
echo '<form method="post" class="adm-form">';
echo '  <input type="hidden" name="csrf" value="' . Helpers::escapar(Helpers::csrfToken()) . '">';
echo '  <input type="hidden" name="acao" value="salvar">';
echo '  <div class="adm-linha adm-linha--3">';
echo '    <label class="adm-campo"><span>Dia</span><select name="dia_semana">';
for ($d = 1; $d <= 6; $d++) {
    echo '<option value="' . $d . '">' . Helpers::escapar(Helpers::diaSemana($d)) . '</option>';
}
echo '</select></label>';
echo '    <label class="adm-campo"><span>De</span><input type="time" name="hora_inicio" value="08:00" required></label>';
echo '    <label class="adm-campo"><span>Até</span><input type="time" name="hora_fim" value="12:00" required></label>';
echo '  </div>';
echo '  <label class="adm-check"><input type="checkbox" name="ativo" value="1" checked> ativo</label>';
echo '  <button type="submit" class="adm-btn adm-btn--primario">Adicionar</button>';
echo '</form>';
echo '</details>';

echo '<p class="adm-nota">O site informa "Segunda a sábado". Você pode ajustar livremente — '
   . 'o assistente sempre usa a tabela acima como fonte da verdade.</p>';

echo '</div>';

Views::fim();