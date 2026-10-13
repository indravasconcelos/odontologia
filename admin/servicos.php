<?php
/**
 * admin/servicos.php — servicos e suas duracoes
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/exigir-login.php';
require_once dirname(__DIR__) . '/app/views/Views.php';
require_once dirname(__DIR__) . '/app/agenda/Agenda.php';

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

// abre o formulario de novo serviço apos um erro, para o dentista
// nao perder o que ja digitou. O redirect do POST recarrega a pagina,
// entao o parametro so existe quando algo deu errado.
$abrirNovo = Helpers::e('novo', '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Helpers::exigirCsrf();

    $acao = (string) Helpers::post('acao', '');

    try {
        if ($acao === 'excluir') {
            $idExcluir = (int) Helpers::post('id', 0);
            $servicoExcluir = Agenda::servico($idExcluir);

            if ($servicoExcluir === null) {
                Session::flash('Serviço não encontrado.', 'erro');
            } else {
                // A tabela de agendamentos tem FOREIGN KEY ... ON DELETE RESTRICT:
                // um serviço já usado em alguma consulta não pode sair do banco.
                // Checamos antes para dar uma resposta em português, em vez de
                // estourar um erro técnico de SQL na frente do dentista.
                $emUso = (int) Database::valor(
                    'SELECT COUNT(*) FROM agendamentos WHERE servico_id = ?',
                    [$idExcluir],
                    0
                );

                if ($emUso > 0) {
                    Session::flash(
                        '"' . $servicoExcluir['nome'] . '" tem '
                        . ($emUso === 1 ? '1 consulta' : $emUso . ' consultas')
                        . ' na agenda. Desative em vez de excluir, para não perder o histórico.',
                        'erro'
                    );
                } else {
                    Database::query('DELETE FROM servicos WHERE id = ?', [$idExcluir]);
                    Session::flash('"' . $servicoExcluir['nome'] . '" excluído.');
                }
            }
        } elseif ($acao === 'salvar') {
            $id = (int) Helpers::post('id', 0);
            $nome = trim((string) Helpers::post('nome', ''));
            $duracao = (int) Helpers::post('duracao_minutos', 30);

            if (mb_strlen($nome) < 2) {
                throw new RuntimeException('Informe o nome do serviço.');
            }
            if ($duracao < 5 || $duracao > 480) {
                throw new RuntimeException('A duração deve ficar entre 5 e 480 minutos.');
            }

            // nome repetido: o banco tem UNIQUE e rebentaria com um erro
            // técnico. Checamos antes para falar em português.
            $jaExiste = Database::valor(
                'SELECT id FROM servicos WHERE nome = ? AND id <> ? LIMIT 1',
                [$nome, $id],
                null
            );
            if ($jaExiste !== null) {
                throw new RuntimeException('Já existe um serviço com esse nome.');
            }

            if ($id > 0) {
                // "ativo" nao entra aqui de proposito: quem liga e desliga
                // e o botao Ativar/Desativar, para nao haver dois controles
                // fazendo a mesma coisa na mesma tela.
                $anterior = Agenda::servico($id);
                $duracaoAntiga = $anterior !== null ? (int) $anterior['duracao_minutos'] : $duracao;

                Database::query(
                    'UPDATE servicos SET nome = ?, duracao_minutos = ? WHERE id = ?',
                    [$nome, $duracao, $id]
                );

                $msg = '"' . $nome . '" atualizado.';

                // A duracao nova vale para os horarios ainda nao
                // acontecidos: sem este passo, um agendamento ja marcado
                // das 17:00 continuaria terminando 17:30 e a agenda
                // ofereceria 17:30 como livre, em cima de outra reserva.
                if ($duracao !== $duracaoAntiga) {
                    $recalculo = Agenda::recalcularDuracoes($id, $duracao);

                    if ($recalculo['atualizados'] > 0) {
                        $n = $recalculo['atualizados'];
                        $msg .= ' ' . ($n === 1
                            ? '1 horário já marcado foi ajustado para a nova duração.'
                            : $n . ' horários já marcados foram ajustados para a nova duração.');
                    }

                    if ($recalculo['mantidos'] > 0) {
                        $n = $recalculo['mantidos'];
                        $msg .= ' ' . ($n === 1
                            ? '1 horário ficou como estava por bater em outro.'
                            : $n . ' horários ficaram como estavam por bater em outro.');
                    }
                }

                Session::flash($msg);
            } else {
                // ativo = 1 vem do padrao da tabela
                Database::query(
                    'INSERT INTO servicos (nome, duracao_minutos) VALUES (?, ?)',
                    [$nome, $duracao]
                );
                Session::flash('"' . $nome . '" criado.');
            }
        } elseif ($acao === 'alternar') {
            Database::query(
                'UPDATE servicos SET ativo = 1 - ativo WHERE id = ?',
                [(int) Helpers::post('id', 0)]
            );
            Session::flash('Status do serviço alterado.');
        }
    } catch (Throwable $e) {
        Session::flash($e->getMessage(), 'erro');

        // se era um servico novo que deu erro, reabre o formulario
        if ((int) Helpers::post('id', 0) === 0) {
            Helpers::redirecionar(Helpers::baseUrl() . '/admin/servicos.php?novo=1');
        }
    }

    Helpers::redirecionar(Helpers::baseUrl() . '/admin/servicos.php');
}

$servicos = Database::todos('SELECT id, nome, duracao_minutos, ativo FROM servicos ORDER BY ativo DESC, nome');
$csrf = Helpers::csrfToken();

Views::inicio('Serviços', 'servicos');

echo '<div class="adm-container">';
Views::pagina('Serviços', 'Altere o nome ou a duração e clique em Salvar. A duração é quanto tempo fica reservado na agenda.');
Views::feedback($ok, $erro);

// ---- lista: cada linha ja vem com os campos para editar ----
echo '<div class="adm-tabela-wrap">';
echo '<table class="adm-tabela adm-tabela--editavel adm-tabela--servicos">';
echo '<thead><tr><th>Serviço</th><th>Duração</th><th>Situação</th><th></th></tr></thead>';
echo '<tbody>';

foreach ($servicos as $s) {
    $id = (int) $s['id'];
    $formId = 'servico-' . $id;

    echo '<tr class="' . ($s['ativo'] ? '' : 'adm-linha-fria') . '">';

    echo '  <td><input type="text" name="nome" form="' . $formId . '" required class="adm-input adm-input--cheio"'
        . ' value="' . Helpers::escapar($s['nome']) . '"></td>';

    echo '  <td><input type="number" name="duracao_minutos" form="' . $formId . '"'
        . ' min="5" max="480" step="5" class="adm-input adm-input--duracao"'
        . ' value="' . (int) $s['duracao_minutos'] . '"> <span class="adm-unidade">min</span></td>';

    echo '  <td><span class="adm-status ' . ($s['ativo'] ? 'is-confirmado' : 'is-cancelado') . '">'
        . ($s['ativo'] ? 'Ativo' : 'Inativo') . '</span></td>';

    echo '  <td class="adm-col-acoes">';
    echo '    <form id="' . $formId . '" method="post" class="adm-inline">';
    echo '      <input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">';
    echo '      <input type="hidden" name="acao" value="salvar">';
    echo '      <input type="hidden" name="id" value="' . $id . '">';
    echo '      <button class="adm-btn adm-btn--primario adm-btn--pequeno" type="submit">Salvar</button>';
    echo '    </form>';
    echo '    <form method="post" class="adm-inline">';
    echo '      <input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">';
    echo '      <input type="hidden" name="acao" value="alternar">';
    echo '      <input type="hidden" name="id" value="' . $id . '">';
    echo '      <button class="adm-btn adm-btn--sutil adm-btn--pequeno" type="submit">'
        . ($s['ativo'] ? 'Desativar' : 'Ativar') . '</button>';
    echo '    </form>';

    // Excluir: só some de verdade se o serviço nunca foi usado em
    // nenhuma consulta. O banco bloqueia a remoção nesse caso, e o
    // sistema explica isso em português em vez de mostrar erro de SQL.
    $confirmarExcluir = 'Excluir "' . addslashes($s['nome']) . '" definitivamente?';
    echo '    <form method="post" class="adm-inline"'
        . ' onsubmit="return confirm(\'' . $confirmarExcluir . '\')">';
    echo '      <input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">';
    echo '      <input type="hidden" name="acao" value="excluir">';
    echo '      <input type="hidden" name="id" value="' . $id . '">';
    echo '      <button class="adm-btn adm-btn--perigo adm-btn--pequeno" type="submit">Excluir</button>';
    echo '    </form>';
    echo '  </td>';

    echo '</tr>';
}

echo '</tbody></table></div>';

// ---- incluir um novo serviço ----
// O formulario so aparece depois de clicar no botao: sem o botao, a tela
// fica limpa com a lista; com o botao, o formulario aparece abaixo.
// <details> faz isso sem JavaScript e volta a fechar sozinho apos salvar,
// porque a pagina e recarregada pelo redirect do POST.
echo '<details class="adm-card adm-card--novo adm-card--novo-servico"'
    . ($abrirNovo ? ' open' : '') . '>';
echo '<summary class="adm-btn adm-btn--primario adm-novo-resumo">+ Adicionar serviço</summary>';

// conteúdo do card
echo '<div class="adm-novo-conteudo">';
echo '  <h2 class="adm-subtitulo">Adicionar um serviço</h2>';
echo '  <p class="adm-nota">O serviço entra ativo e passa a aparecer nas opções de marcação.</p>';
echo '  <form method="post" class="adm-form adm-form--servico">';
echo '    <input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">';
echo '    <input type="hidden" name="acao" value="salvar">';
echo '    <label class="adm-campo adm-campo--nome"><span>Nome do serviço</span>'
    . '<input type="text" name="nome" required placeholder="Ex.: Consulta de retorno"></label>';
echo '    <label class="adm-campo adm-campo--duracao"><span>Duração</span>'
    . '<span class="adm-com-duracao">'
    . '<input type="number" name="duracao_minutos" value="30" min="5" max="480" step="5" required>'
    . '<span class="adm-unidade">min</span></span></label>';
echo '    <div class="adm-form-acoes">';
echo '      <button type="submit" class="adm-btn adm-btn--primario">Salvar serviço</button>';
echo '      <a class="adm-btn adm-btn--sutil" href="' . Helpers::baseUrl() . '/admin/servicos.php">Cancelar</a>';
echo '    </div>';
echo '  </form>';
echo '</div>';
echo '</details>';

echo '</div>';

Views::fim();