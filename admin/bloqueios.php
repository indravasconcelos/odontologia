<?php
/**
 * admin/bloqueios.php — datas e faixas indisponiveis
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
            Database::query('DELETE FROM bloqueios WHERE id = ?', [(int) Helpers::post('id', 0)]);
            Session::flash('Bloqueio removido.');
        } elseif ($acao === 'salvar') {
            $data = (string) Helpers::post('data', '');
            $inicio = (string) Helpers::post('hora_inicio', '');
            $fim = (string) Helpers::post('hora_fim', '');
            $motivo = trim((string) Helpers::post('motivo', ''));

            if (!Helpers::dataValida($data)) {
                throw new RuntimeException('Escolha uma data válida.');
            }

            $mi = Helpers::horaParaMinutos($inicio);
            $mf = Helpers::horaParaMinutos($fim);

            if ($mf <= $mi) {
                throw new RuntimeException('O horário final precisa ser depois do inicial.');
            }

            $id = (int) Helpers::post('id', 0);

            if ($id > 0) {
                Database::query(
                    'UPDATE bloqueios SET data = ?, hora_inicio = ?, hora_fim = ?, motivo = ? WHERE id = ?',
                    [$data, $inicio . ':00', $fim . ':00', $motivo, $id]
                );
                Session::flash('Bloqueio atualizado.');
            } else {
                Database::query(
                    'INSERT INTO bloqueios (data, hora_inicio, hora_fim, motivo) VALUES (?, ?, ?, ?)',
                    [$data, $inicio . ':00', $fim . ':00', $motivo]
                );
                Session::flash('Bloqueio criado.');
            }
        }
    } catch (Throwable $e) {
        Session::flash($e->getMessage(), 'erro');
    }

    Helpers::redirecionar(Helpers::baseUrl() . '/admin/bloqueios.php');
}

$bloqueios = Database::todos(
    'SELECT * FROM bloqueios WHERE data >= CURDATE() - INTERVAL 7 DAY
      ORDER BY data, hora_inicio'
);

Views::inicio('Bloqueios', 'bloqueios');

echo '<div class="adm-container">';
Views::pagina('Bloqueios', 'Feriadas, almoços, cursos. Esses horários deixam de ser oferecidos ao visitante.');
Views::feedback($ok, $erro);

echo '<div class="adm-tabela-wrap">';
echo '<table class="adm-tabela">';
echo '<thead><tr><th>Data</th><th>De</th><th>Até</th><th>Motivo</th><th class="adm-col-acoes">Ações</th></tr></thead>';
echo '<tbody>';

if ($bloqueios === []) {
    echo '<tr><td colspan="5">Nenhum bloqueio cadastrado.</td></tr>';
}

foreach ($bloqueios as $b) {
    $passou = $b['data'] < date('Y-m-d');

    echo '<tr' . ($passou ? ' class="adm-linha-fria"' : '') . '>';
    echo '  <td><strong>' . Helpers::escapar(Helpers::dataBr($b['data'])) . '</strong>'
       . '<span class="adm-sub">' . Helpers::escapar(ucfirst(Helpers::dataExtenso($b['data']))) . '</span></td>';
    echo '  <td>' . Helpers::escapar(Helpers::horaBr($b['hora_inicio'])) . '</td>';
    echo '  <td>' . Helpers::escapar(Helpers::horaBr($b['hora_fim'])) . '</td>';
    echo '  <td>' . Helpers::escapar($b['motivo']) . '</td>';
    echo '  <td class="adm-col-acoes">';
    echo '    <a class="adm-acao" href="#editar-' . (int) $b['id'] . '">Editar</a>';
    echo '    <form method="post" class="adm-inline" onsubmit="return confirm(\'Remover este bloqueio?\')">';
    echo '      <input type="hidden" name="csrf" value="' . Helpers::escapar(Helpers::csrfToken()) . '">';
    echo '      <input type="hidden" name="acao" value="excluir">';
    echo '      <input type="hidden" name="id" value="' . (int) $b['id'] . '">';
    echo '      <button class="adm-acao adm-acao--perigo" type="submit">Remover</button>';
    echo '    </form>';
    echo '  </td>';
    echo '</tr>';

    echo '<tr class="adm-linha-form" id="editar-' . (int) $b['id'] . '">';
    echo '  <td colspan="5"><form method="post" class="adm-form adm-form--linha">';
    echo '    <input type="hidden" name="csrf" value="' . Helpers::escapar(Helpers::csrfToken()) . '">';
    echo '    <input type="hidden" name="acao" value="salvar">';
    echo '    <input type="hidden" name="id" value="' . (int) $b['id'] . '">';
    echo '    <input type="date" name="data" value="' . Helpers::escapar($b['data']) . '" required>';
    echo '    <input type="time" name="hora_inicio" value="' . Helpers::escapar(substr($b['hora_inicio'], 0, 5)) . '" required>';
    echo '    <input type="time" name="hora_fim" value="' . Helpers::escapar(substr($b['hora_fim'], 0, 5)) . '" required>';
    echo '    <input type="text" name="motivo" value="' . Helpers::escapar($b['motivo']) . '" placeholder="Motivo">';
    echo '    <button type="submit" class="adm-btn adm-btn--primario adm-btn--pequeno">Salvar</button>';
    echo '  </form></td>';
    echo '</tr>';
}

echo '</tbody></table></div>';

echo '<details class="adm-card adm-card--novo">';
echo '<summary>+ Novo bloqueio</summary>';
echo '<form method="post" class="adm-form">';
echo '  <input type="hidden" name="csrf" value="' . Helpers::escapar(Helpers::csrfToken()) . '">';
echo '  <input type="hidden" name="acao" value="salvar">';
echo '  <div class="adm-linha adm-linha--3">';
echo '    <label class="adm-campo"><span>Data *</span><input type="date" name="data" value="' . Helpers::escapar(Helpers::hoje()) . '" required></label>';
echo '    <label class="adm-campo"><span>De *</span><input type="time" name="hora_inicio" value="12:00" required></label>';
echo '    <label class="adm-campo"><span>Até *</span><input type="time" name="hora_fim" value="13:00" required></label>';
echo '  </div>';
echo '  <label class="adm-campo"><span>Motivo</span><input type="text" name="motivo" placeholder="Almoço, feriado, curso..."></label>';
echo '  <button type="submit" class="adm-btn adm-btn--primario">Criar bloqueio</button>';
echo '</form>';
echo '</details>';

echo '</div>';

Views::fim();