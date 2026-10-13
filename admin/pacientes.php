<?php
/**
 * admin/pacientes.php — lista e ficha do paciente
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/exigir-login.php';
require_once dirname(__DIR__) . '/app/views/Views.php';
require_once dirname(__DIR__) . '/app/agenda/Agenda.php';

$id = (int) Helpers::e('id', 0);
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
        if ($acao === 'salvar' && $id > 0) {
            $nome = trim((string) Helpers::post('nome', ''));
            $telefone = trim((string) Helpers::post('telefone', ''));

            if (mb_strlen($nome) < 2) {
                throw new RuntimeException('Informe o nome do paciente.');
            }

            Database::query(
                'UPDATE pacientes SET nome = ?, telefone = ? WHERE id = ?',
                [
                    $nome,
                    $telefone !== '' ? Helpers::telefone($telefone) : null,
                    $id,
                ]
            );

            Session::flash('Dados do paciente atualizados.');
            Helpers::redirecionar(Helpers::baseUrl() . '/admin/pacientes.php?id=' . $id);
        } elseif ($acao === 'excluir') {
            $idExcluir = (int) Helpers::post('id', 0);
            $pacExcluir = Database::primeiro(
                'SELECT nome FROM pacientes WHERE id = ? LIMIT 1',
                [$idExcluir]
            );

            if ($pacExcluir === null) {
                Session::flash('Paciente não encontrado.', 'erro');
            } else {
                // A FK de agendamentos.paciente_id é CASCADE, então um DELETE
                // aqui apagaria o histórico junto sem avisar. O bloqueio é da
                // aplicação, de propósito: consulta concluída é registro do
                // consultório, não lixo para limpar por engano.
                $naoCancelados = (int) Database::valor(
                    "SELECT COUNT(*) FROM agendamentos WHERE paciente_id = ? AND status <> 'cancelado'",
                    [$idExcluir],
                    0
                );

                if ($naoCancelados > 0) {
                    Session::flash(
                        '"' . $pacExcluir['nome'] . '" ainda tem '
                        . ($naoCancelados === 1 ? '1 agendamento' : $naoCancelados . ' agendamentos')
                        . ' na agenda. Cancele antes de excluir, para não perder o histórico.',
                        'erro'
                    );
                } else {
                    // Só cancelados sobraram, e a FK em cascade já os remove junto.
                    Database::query('DELETE FROM pacientes WHERE id = ?', [$idExcluir]);
                    Session::flash('"' . $pacExcluir['nome'] . '" excluído.');
                }
            }

            Helpers::redirecionar(Helpers::baseUrl() . '/admin/pacientes.php');
        }
    } catch (Throwable $e) {
        Session::flash($e->getMessage(), 'erro');
    }

    Helpers::redirecionar(Helpers::baseUrl() . '/admin/pacientes.php');
}

if ($id > 0) {
    $paciente = Database::primeiro('SELECT * FROM pacientes WHERE id = ? LIMIT 1', [$id]);

    if ($paciente === null) {
        Helpers::redirecionar(Helpers::baseUrl() . '/admin/pacientes.php');
    }

    Views::inicio('Paciente', 'pacientes');

    echo '<div class="adm-container adm-container--estreito">';
    Views::pagina($paciente['nome'], 'Ficha do paciente');
    Views::feedback($ok, $erro);

    echo '<form method="post" class="adm-form adm-form--card">';
    echo '  <input type="hidden" name="csrf" value="' . Helpers::escapar(Helpers::csrfToken()) . '">';
    echo '  <input type="hidden" name="acao" value="salvar">';
    echo '  <div class="adm-linha adm-linha--2">';
    echo '    <label class="adm-campo"><span>Nome</span><input type="text" name="nome" required value="'
       . Helpers::escapar($paciente['nome']) . '"></label>';
    echo '    <label class="adm-campo"><span>Telefone</span><input type="tel" name="telefone" value="'
       . Helpers::escapar($paciente['telefone']) . '"></label>';
    echo '  </div>';
    echo '  <div class="adm-acoes">';
    echo '    <button type="submit" class="adm-btn adm-btn--primario">Salvar</button>';

    // Remarcar: pega o próximo agendamento que ainda está de pé e abre a
    // tela de edição. Sem agendamento, cai na tela de criar um novo já
    // com o paciente preenchido.
    $proximo = Database::primeiro(
        "SELECT id FROM agendamentos WHERE paciente_id = ? AND status <> 'cancelado' "
        . 'ORDER BY data ASC, hora_inicio ASC LIMIT 1',
        [$id]
    );
    $urlRemarcar = $proximo !== null
        ? Helpers::baseUrl() . '/admin/agendamento.php?id=' . (int) $proximo['id']
        : Helpers::baseUrl() . '/admin/agendamento.php?paciente_id=' . $id;
    echo '    <a class="adm-btn adm-btn--pequeno" href="' . Helpers::escapar($urlRemarcar) . '">Remarcar</a>';

    echo '    <a class="adm-btn adm-btn--sutil" href="' . Helpers::baseUrl() . '/admin/pacientes.php">Voltar</a>';
    echo '  </div>';
    echo '</form>';

    echo '<form method="post" class="adm-form" style="margin-top:0.75rem"'
       . ' onsubmit="return confirm(\'Excluir "' . addslashes($paciente['nome'])
       . '" definitivamente?\')">';
    echo '  <input type="hidden" name="csrf" value="' . Helpers::escapar(Helpers::csrfToken()) . '">';
    echo '  <input type="hidden" name="acao" value="excluir">';
    echo '  <input type="hidden" name="id" value="' . $id . '">';
    echo '  <button type="submit" class="adm-btn adm-btn--perigo">Excluir paciente</button>';
    echo '</form>';

    echo '</div>';
    Views::fim();
    exit;
}

$busca = (string) Helpers::e('q', '');
$sql = 'SELECT p.* FROM pacientes p';
$params = [];

if ($busca !== '') {
    $sql .= ' WHERE p.nome LIKE ? OR p.telefone LIKE ?';
    $params = ['%' . $busca . '%', '%' . Helpers::telefone($busca) . '%'];
}

$sql .= ' ORDER BY p.nome LIMIT 300';

$pacientes = Database::todos($sql, $params);

Views::inicio('Pacientes', 'pacientes');

echo '<div class="adm-container">';
Views::pagina('Pacientes', 'Os pacientes são criados automaticamente quando alguém marca pelo site.');
Views::feedback($ok, $erro);

echo '<form method="get" class="adm-barra-datas">';
echo '  <input type="search" name="q" value="' . Helpers::escapar($busca) . '" placeholder="Buscar por nome ou telefone" class="adm-input">';
echo '  <button type="submit" class="adm-btn adm-btn--primario">Buscar</button>';
echo '  <a class="adm-btn adm-btn--novo" href="agendamento.php">+ Novo agendamento</a>';
echo '</form>';

echo '<div class="adm-tabela-wrap"><table class="adm-tabela"><thead><tr>'
   . '<th>Paciente</th><th>Telefone</th><th class="adm-col-acoes">Ações</th>'
   . '</tr></thead><tbody>';

if ($pacientes === []) {
    echo '<tr><td colspan="3">Nenhum paciente encontrado.</td></tr>';
}

// Próximo agendamento de cada paciente, numa consulta só: é o que a
// ação "Remarcar" precisa abrir. Sem agendamento, o link cai na tela
// de criar um novo já com o paciente preenchido.
$ids = array_map(static fn (array $x): int => (int) $x['id'], $pacientes);
$proximos = [];
if ($ids !== []) {
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    foreach (Database::todos(
        "SELECT a.id, a.paciente_id FROM agendamentos a "
        . "WHERE a.paciente_id IN ($marcas) AND a.status <> 'cancelado' "
        . 'ORDER BY a.data ASC, a.hora_inicio ASC',
        $ids
    ) as $a) {
        $pid = (int) $a['paciente_id'];
        if (!isset($proximos[$pid])) {
            $proximos[$pid] = (int) $a['id'];
        }
    }
}

foreach ($pacientes as $p) {
    $pid = (int) $p['id'];
    $urlRemarcar = isset($proximos[$pid])
        ? 'agendamento.php?id=' . $proximos[$pid]
        : 'agendamento.php?paciente_id=' . $pid;

    echo '<tr>';
    echo '  <td><a href="pacientes.php?id=' . $pid . '"><strong>'
       . Helpers::escapar($p['nome']) . '</strong></a></td>';
    echo '  <td>' . Helpers::escapar($p['telefone'] ?: '—') . '</td>';
    echo '  <td class="adm-col-acoes">'
       . '<a class="adm-acao" href="pacientes.php?id=' . $pid . '">Abrir</a>'
       . '<a class="adm-acao" href="' . Helpers::escapar($urlRemarcar) . '">Remarcar</a>';

    echo '  <form method="post" style="display:inline" '
       . 'onsubmit="return confirm(\'Excluir "' . addslashes((string) $p['nome']) . '" definitivamente?\')">';
    echo '    <input type="hidden" name="csrf" value="' . Helpers::escapar(Helpers::csrfToken()) . '">';
    echo '    <input type="hidden" name="acao" value="excluir">';
    echo '    <input type="hidden" name="id" value="' . $pid . '">';
    echo '    <button type="submit" class="adm-acao" style="background:none;border:0;padding:0;'
       . 'font:inherit;color:inherit;cursor:pointer;text-decoration:underline">Excluir</button>';
    echo '  </form>';

    echo '  </td>';
    echo '</tr>';
}

echo '</tbody></table></div></div>';

Views::fim();