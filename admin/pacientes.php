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
            $email = trim((string) Helpers::post('email', ''));

            if (mb_strlen($nome) < 2) {
                throw new RuntimeException('Informe o nome do paciente.');
            }
            if ($email !== '' && !Helpers::emailValido($email)) {
                throw new RuntimeException('E-mail inválido.');
            }

            Database::query(
                'UPDATE pacientes SET nome = ?, telefone = ?, email = ? WHERE id = ?',
                [
                    $nome,
                    $telefone !== '' ? Helpers::telefone($telefone) : null,
                    $email !== '' ? $email : null,
                    $id,
                ]
            );

            Session::flash('Dados do paciente atualizados.');
            Helpers::redirecionar(Helpers::baseUrl() . '/admin/pacientes.php?id=' . $id);
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
    echo '  <label class="adm-campo"><span>E-mail</span><input type="email" name="email" value="'
       . Helpers::escapar($paciente['email']) . '"></label>';
    echo '  <div class="adm-acoes">';
    echo '    <button type="submit" class="adm-btn adm-btn--primario">Salvar</button>';
    echo '    <a class="adm-btn adm-btn--sutil" href="' . Helpers::baseUrl() . '/admin/pacientes.php">Voltar</a>';
    echo '  </div>';
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
   . '<th>Paciente</th><th>Telefone</th><th>E-mail</th><th class="adm-col-acoes">Ações</th>'
   . '</tr></thead><tbody>';

if ($pacientes === []) {
    echo '<tr><td colspan="4">Nenhum paciente encontrado.</td></tr>';
}

foreach ($pacientes as $p) {
    echo '<tr>';
    echo '  <td><a href="pacientes.php?id=' . (int) $p['id'] . '"><strong>'
       . Helpers::escapar($p['nome']) . '</strong></a></td>';
    echo '  <td>' . Helpers::escapar($p['telefone'] ?: '—') . '</td>';
    echo '  <td>' . Helpers::escapar($p['email'] ?: '—') . '</td>';
    echo '  <td class="adm-col-acoes"><a class="adm-acao" href="pacientes.php?id=' . (int) $p['id'] . '">Abrir</a></td>';
    echo '</tr>';
}

echo '</tbody></table></div></div>';

Views::fim();