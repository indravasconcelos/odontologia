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

/* Iniciais do paciente: usada no círculo da ficha e na lista. */
$iniciais = static function (?string $nome): string {
    $partes = preg_split('/\s+/', trim((string) $nome), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $primeira = mb_substr($partes[0] ?? '', 0, 1);
    $ultima = count($partes) > 1 ? mb_substr((string) end($partes), 0, 1) : '';

    return mb_strtoupper($primeira . $ultima) ?: '—';
};

if ($id > 0) {
    $paciente = Database::primeiro('SELECT * FROM pacientes WHERE id = ? LIMIT 1', [$id]);

    if ($paciente === null) {
        Helpers::redirecionar(Helpers::baseUrl() . '/admin/pacientes.php');
    }

    // Remarcar: pega o próximo agendamento que ainda está de pé e abre a
    // tela de edição. Sem agendamento, cai na tela de criar um novo já
    // com o paciente preenchido.
    $proximo = Database::primeiro(
        'SELECT id FROM agendamentos WHERE paciente_id = ? AND status <> \'cancelado\' '
        . 'ORDER BY data ASC, hora_inicio ASC LIMIT 1',
        [$id]
    );
    $urlRemarcar = $proximo !== null
        ? Helpers::baseUrl() . '/admin/agendamento.php?id=' . (int) $proximo['id']
        : Helpers::baseUrl() . '/admin/agendamento.php?paciente_id=' . $id;

    Views::inicio('Paciente', 'pacientes');

    echo '<div class="adm-container adm-container--estreito">';
    Views::feedback($ok, $erro);

    // topo: quem é o paciente
    echo '<a class="adm-voltar" href="' . Helpers::baseUrl() . '/admin/pacientes.php">'
       . Views::icone('esquerda', 15) . 'Voltar para a lista</a>';

    echo '<section class="adm-ficha-topo">';
    echo '  <span class="adm-ficha-avatar" aria-hidden="true">' . Helpers::escapar($iniciais($paciente['nome'])) . '</span>';
    echo '  <div class="adm-ficha-ident">';
    echo '    <span class="adm-agenda-etiqueta">' . Views::icone('pessoa', 14) . 'Ficha do paciente</span>';
    echo '    <h1>' . Helpers::escapar($paciente['nome']) . '</h1>';
    echo '    <p class="adm-agenda-sub">'
       . ($paciente['telefone'] !== null && $paciente['telefone'] !== ''
           ? Helpers::escapar($paciente['telefone'])
           : 'Sem telefone cadastrado — sem ele não dá para confirmar pelo WhatsApp.')
       . '</p>';
    echo '  </div>';
    echo '</section>';

    // dados
    echo '<form method="post" class="adm-form adm-card adm-ficha-form">';
    echo '  <input type="hidden" name="csrf" value="' . Helpers::escapar(Helpers::csrfToken()) . '">';
    echo '  <input type="hidden" name="acao" value="salvar">';
    echo '  <h2 class="adm-ficha-titulo">Dados cadastrados</h2>';
    echo '  <div class="adm-linha adm-linha--2">';
    echo '    <label class="adm-campo"><span>Nome</span><input type="text" name="nome" required value="'
       . Helpers::escapar($paciente['nome']) . '"></label>';
    echo '    <label class="adm-campo"><span>Telefone</span><input type="tel" name="telefone" value="'
       . Helpers::escapar($paciente['telefone']) . '">'
       . '<small class="adm-dica">Usado no lembrete de WhatsApp da agenda.</small></label>';
    echo '  </div>';
    echo '  <div class="adm-acoes">';
    echo '    <button type="submit" class="adm-btn adm-btn--primario">' . Views::icone('check', 15) . 'Salvar alterações</button>';
    echo '    <a class="adm-btn adm-btn--sutil" href="' . Helpers::escapar($urlRemarcar) . '">'
       . Views::icone('calendario', 15) . 'Remarcar consulta</a>';
    echo '    <a class="adm-btn adm-btn--sutil" href="' . Helpers::baseUrl() . '/admin/pacientes.php">Voltar</a>';
    echo '  </div>';
    echo '</form>';

    // exclusão fica isolada, para não parecer parte do salvar
    echo '<form method="post" class="adm-perigo"'
       . ' onsubmit="return confirm(\'Excluir "' . addslashes($paciente['nome']) . '" definitivamente?\')">';
    echo '  <input type="hidden" name="csrf" value="' . Helpers::escapar(Helpers::csrfToken()) . '">';
    echo '  <input type="hidden" name="acao" value="excluir">';
    echo '  <input type="hidden" name="id" value="' . $id . '">';
    echo '  <div class="adm-perigo-texto">';
    echo '    <strong>Excluir paciente</strong>';
    echo '    <span class="adm-dica">Some da lista e apaga o histórico. O sistema só libera quando não há '
       . 'nenhum agendamento em aberto — antes disso, cancele a consulta.</span>';
    echo '  </div>';
    echo '  <button type="submit" class="adm-btn adm-btn--perigo">' . Views::icone('lixeira', 15) . 'Excluir</button>';
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

// Próximo agendamento de cada paciente, numa consulta só: é o que a
// ação "Remarcar" precisa abrir. Sem agendamento, o link cai na tela
// de criar um novo já com o paciente preenchido.
$ids = array_map(static fn (array $x): int => (int) $x['id'], $pacientes);
$proximos = [];
if ($ids !== []) {
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    foreach (Database::todos(
        "SELECT a.id, a.paciente_id, a.data, a.hora_inicio FROM agendamentos a "
        . "WHERE a.paciente_id IN ($marcas) AND a.status <> 'cancelado' "
        . 'ORDER BY a.data ASC, a.hora_inicio ASC',
        $ids
    ) as $a) {
        $pid = (int) $a['paciente_id'];
        if (!isset($proximos[$pid])) {
            $proximos[$pid] = $a;
        }
    }
}

$totalPacientes = count($pacientes);
$totalComAgenda = count($proximos);
$totalSemTelefone = count(array_filter(
    $pacientes,
    static fn (array $p): bool => $p['telefone'] === null || $p['telefone'] === ''
));

Views::inicio('Pacientes', 'pacientes');

echo '<div class="adm-container adm-container--agenda">';
Views::feedback($ok, $erro);

/* ---------------- topo: busca ---------------- */
echo '<section class="adm-agenda-topo">';

echo '  <div class="adm-agenda-ident">';
echo '    <span class="adm-agenda-etiqueta">' . Views::icone('usuarios', 14) . 'Pacientes</span>';
echo '    <h1>' . ($busca !== '' ? 'Resultado da busca' : 'Todos os pacientes') . '</h1>';
echo '    <p class="adm-agenda-sub">Os pacientes são criados automaticamente quando alguém marca pelo site.</p>';
echo '  </div>';

echo '  <form method="get" class="adm-agenda-nav">';
echo '    <div class="adm-busca">';
echo '      ' . Views::icone('busca', 15);
echo '      <input type="search" name="q" value="' . Helpers::escapar($busca) . '"'
   . ' placeholder="Buscar por nome ou telefone" class="adm-busca-input" aria-label="Buscar paciente">';
echo '    </div>';
echo '    <button type="submit" class="adm-btn adm-btn--primario">Buscar</button>';
if ($busca !== '') {
    echo '    <a class="adm-btn adm-btn--sutil" href="pacientes.php">Limpar</a>';
}
echo '    <a class="adm-btn adm-btn--novo adm-btn--agenda-novo" href="agendamento.php">'
   . Views::icone('mais', 16) . 'Novo agendamento</a>';
echo '  </form>';

echo '</section>';

/* ---------------- resumo ---------------- */
echo '<div class="adm-agenda-stats">';

$resumo = [
    ['total', $busca !== '' ? 'Encontrados' : 'Cadastrados', $totalPacientes, 'usuarios', 'Nome e telefone'],
    ['confirmado', 'Com consulta marcada', $totalComAgenda, 'calendario', 'Podem ser remarcados'],
    ['pendente', 'Sem telefone', $totalSemTelefone, 'telefone', 'Precisam de cadastro'],
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

/* ---------------- lista ---------------- */
if ($pacientes === []) {
    echo '<div class="adm-vazio adm-vazio--agenda">';
    echo '  <span class="adm-vazio-icone" aria-hidden="true">' . Views::icone('busca', 26) . '</span>';
    echo '  <h2>' . ($busca !== '' ? 'Nenhum paciente encontrado' : 'Nenhum paciente cadastrado') . '</h2>';
    echo '  <p>' . ($busca !== ''
        ? 'Nada bate com essa busca. Tente outra parte do nome ou do telefone.'
        : 'Os pacientes entram sozinhos aqui quando alguém marca uma consulta pelo site.') . '</p>';
    echo '  <a class="adm-btn adm-btn--primario" href="agendamento.php">Criar agendamento</a>';
    echo '</div>';
} else {
    echo '<div class="adm-agenda-lista">';

    $csrf = Helpers::csrfToken();

    foreach ($pacientes as $p) {
        $pid = (int) $p['id'];
        $proximo = $proximos[$pid] ?? null;
        $urlRemarcar = $proximo !== null
            ? 'agendamento.php?id=' . (int) $proximo['id']
            : 'agendamento.php?paciente_id=' . $pid;

        echo '<article class="adm-ag adm-pac">';

        echo '  <span class="adm-pac-avatar" aria-hidden="true">' . Helpers::escapar($iniciais($p['nome'])) . '</span>';

        echo '  <div class="adm-ag-corpo">';
        echo '    <div class="adm-ag-linha">';
        echo '      <a class="adm-ag-nome" href="pacientes.php?id=' . $pid . '">' . Helpers::escapar($p['nome']) . '</a>';

        if ($p['telefone'] !== null && $p['telefone'] !== '') {
            echo '      <span class="adm-ag-item">' . Views::icone('telefone', 13) . Helpers::escapar($p['telefone']) . '</span>';
        } else {
            echo '      <span class="adm-pac-sem-telefone">Sem telefone</span>';
        }

        echo '    </div>';
        echo '    <p class="adm-ag-meta">';

        if ($proximo !== null) {
            echo '      <span class="adm-ag-item adm-ag-item--origem">' . Views::icone('calendario', 12) . 'Próxima: '
               . Helpers::escapar(Helpers::dataBr((string) $proximo['data'])) . ' às '
               . Helpers::escapar(Helpers::horaBr((string) $proximo['hora_inicio'])) . '</span>';
        } else {
            echo '      <span class="adm-ag-item adm-ag-item--origem">Sem consulta marcada</span>';
        }

        echo '    </p>';
        echo '  </div>';

        echo '  <div class="adm-ag-acoes">';
        echo '    <a class="adm-ag-btn" href="pacientes.php?id=' . $pid . '" title="Abrir a ficha e editar os dados">'
           . Views::icone('lapis', 15) . 'Abrir ficha</a>';
        echo '    <a class="adm-ag-btn adm-ag-btn--confirmar" href="' . Helpers::escapar($urlRemarcar) . '"'
           . ' title="' . ($proximo !== null
               ? 'Abre a consulta agendada para remarcar'
               : 'Abre um novo agendamento já com este paciente') . '">'
           . Views::icone('calendario', 15) . 'Remarcar</a>';
        echo '    <form method="post" class="adm-inline"'
           . ' onsubmit="return confirm(\'Excluir "' . addslashes((string) $p['nome']) . '" definitivamente?\')">';
        echo '      <input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">';
        echo '      <input type="hidden" name="acao" value="excluir">';
        echo '      <input type="hidden" name="id" value="' . $pid . '">';
        echo '      <button class="adm-ag-btn adm-ag-btn--perigo" type="submit" title="Excluir da lista">'
           . Views::icone('lixeira', 15) . 'Excluir</button>';
        echo '    </form>';
        echo '  </div>';

        echo '</article>';
    }

    echo '</div>';
}

/* ---------------- legenda ---------------- */
echo '<details class="adm-agenda-ajuda">';
echo '  <summary>' . Views::icone('ajuda', 15) . 'O que cada botão faz?</summary>';
echo '  <ul>';
echo '    <li><strong>Abrir ficha:</strong> edita nome e telefone do paciente.</li>';
echo '    <li><strong>Remarcar:</strong> abre a consulta já marcada. Sem nenhuma consulta em aberto, cai na tela de criar uma nova com o paciente preenchido.</li>';
echo '    <li><strong>Excluir:</strong> só é liberado quando não existe agendamento em aberto — é o que protege o histórico do consultório.</li>';
echo '  </ul>';
echo '</details>';

echo '<p class="adm-nota">Aparecem aqui os pacientes criados pelo site e pelo próprio painel.</p>';

echo '</div>';

Views::fim();