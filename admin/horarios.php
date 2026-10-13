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

// reabre o formulario de nova faixa depois de um erro, para o dentista
// nao perder o que ja digitou. O redirect do POST recarrega a pagina,
// entao o parametro so existe quando algo deu errado.
$abrirNovo = Helpers::e('novo', '') === '1';

/**
 * "08:30" em minutos, ou null quando nao e um horario completo.
 *
 * Helpers::horaParaMinutos('') devolve 0 em vez de avisar, e uma hora
 * vazia salvaria como 00:00:00 sem passar por aqui.
 */
$minutosDe = static function (string $hora): ?int {
    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $hora) !== 1) {
        return null;
    }
    return ((int) substr($hora, 0, 2)) * 60 + (int) substr($hora, 3, 2);
};

// A lista de dias e usada duas vezes (na lista e no formulario novo).
// Montar uma vez evita os dois blocos sairem de sincronia.
$opcoesDias = '';
for ($d = 0; $d <= 6; $d++) {
    $opcoesDias .= '<option value="' . $d . '">' . Helpers::escapar(Helpers::diaSemana($d)) . '</option>';
}

$selectDias = static function (int $selecionado) use ($opcoesDias): string {
    if ($selecionado < 0 || $selecionado > 6) {
        return $opcoesDias;
    }
    return str_replace(
        '<option value="' . $selecionado . '">',
        '<option value="' . $selecionado . '" selected>',
        $opcoesDias
    );
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Helpers::exigirCsrf();

    $acao = (string) Helpers::post('acao', '');
    $id = (int) Helpers::post('id', 0);

    try {
        if ($acao === 'excluir') {
            // agendamentos guardam a data e a hora da consulta, nao uma
            // referencia a faixa: apagar a faixa nao altera o historico.
            Database::query('DELETE FROM horarios WHERE id = ?', [$id]);
            Session::flash('Horário removido.');
        } elseif ($acao === 'alternar') {
            Database::query('UPDATE horarios SET ativo = 1 - ativo WHERE id = ?', [$id]);
            Session::flash('Situação alterada.');
        } elseif ($acao === 'salvar') {
            $dia = (int) Helpers::post('dia_semana', -1);
            $inicio = trim((string) Helpers::post('hora_inicio', ''));
            $fim = trim((string) Helpers::post('hora_fim', ''));

            if ($dia < 0 || $dia > 6) {
                throw new RuntimeException('Escolha um dia da semana.');
            }

            $mi = $minutosDe($inicio);
            $mf = $minutosDe($fim);

            if ($mi === null || $mf === null) {
                throw new RuntimeException('Preencha o horário de início e de fim.');
            }

            if ($mf <= $mi) {
                throw new RuntimeException('O horário final precisa ser depois do inicial.');
            }

            // o banco tem UNIQUE (dia_semana, hora_inicio, hora_fim): uma
            // faixa repetida estouraria um erro tecnico de SQL. Checamos
            // antes para falar em portugues, como em servicos.
            $repetida = Database::valor(
                'SELECT id FROM horarios
                 WHERE dia_semana = ? AND hora_inicio = ? AND hora_fim = ? AND id <> ?
                 LIMIT 1',
                [$dia, $inicio . ':00', $fim . ':00', $id],
                null
            );

            if ($repetida !== null) {
                throw new RuntimeException(
                    'Essa faixa já está cadastrada para ' . Helpers::diaSemana($dia) . '.'
                );
            }

            // Duas faixas ativas se cruzando no mesmo dia fariam o assistente
            // oferecer horario em cima de horario. Só importa quando a faixa
            // fica ligada: uma faixa desativada nao chega ao assistente.
            $vaiFicarAtiva = $id > 0
                ? (int) Database::valor('SELECT ativo FROM horarios WHERE id = ?', [$id], 0)
                : 1; // faixa nova nasce ativa

            if ($vaiFicarAtiva === 1) {
                $cruzada = (int) Database::valor(
                    'SELECT COUNT(*) FROM horarios
                     WHERE dia_semana = ? AND ativo = 1 AND id <> ?
                       AND hora_inicio < ? AND hora_fim > ?',
                    [$dia, $id, $fim . ':00', $inicio . ':00'],
                    0
                );

                if ($cruzada > 0) {
                    throw new RuntimeException(
                        'Essa faixa se cruza com outra já ativa em ' . Helpers::diaSemana($dia)
                        . '. Ajuste os horários para que uma comece depois que a outra termina.'
                    );
                }
            }

            $descricao = Helpers::diaSemana($dia) . ', ' . $inicio . ' às ' . $fim;

            if ($id > 0) {
                // "ativo" nao entra aqui de proposito: quem liga e desliga e o
                // botao Ativar/Desativar, para nao haver dois controles
                // fazendo a mesma coisa na mesma tela (regra de servicos.php).
                Database::query(
                    'UPDATE horarios SET dia_semana = ?, hora_inicio = ?, hora_fim = ? WHERE id = ?',
                    [$dia, $inicio . ':00', $fim . ':00', $id]
                );
                Session::flash($descricao . ' atualizado.');
            } else {
                Database::query(
                    'INSERT INTO horarios (dia_semana, hora_inicio, hora_fim, ativo) VALUES (?, ?, ?, 1)',
                    [$dia, $inicio . ':00', $fim . ':00']
                );
                Session::flash($descricao . ' adicionado.');
            }
        }
    } catch (Throwable $e) {
        Session::flash($e->getMessage(), 'erro');

        // se era uma faixa nova que deu erro, reabre o formulario
        if ((int) Helpers::post('id', 0) === 0) {
            Helpers::redirecionar(Helpers::baseUrl() . '/admin/horarios.php?novo=1');
        }
    }

    Helpers::redirecionar(Helpers::baseUrl() . '/admin/horarios.php');
}

$horarios = Database::todos('SELECT * FROM horarios ORDER BY dia_semana, hora_inicio');
$csrf = Helpers::csrfToken();

Views::inicio('Horários', 'horarios');

echo '<div class="adm-container">';
Views::pagina(
    'Horários de atendimento',
    'Altere direto na lista e clique em Salvar. É dentro destas faixas que o assistente oferece horários.'
);
Views::feedback($ok, $erro);

// ---- lista: cada linha ja vem com os campos para editar ----
echo '<div class="adm-tabela-wrap">';
echo '<table class="adm-tabela adm-tabela--editavel adm-tabela--horarios">';
echo '<thead><tr><th>Dia</th><th>De</th><th>Até</th><th>Situação</th><th class="adm-col-acoes">Ações</th></tr></thead>';
echo '<tbody>';

if ($horarios === []) {
    echo '<tr><td colspan="5">Nenhum horário cadastrado ainda. Use o botão abaixo para criar o primeiro.</td></tr>';
}

foreach ($horarios as $h) {
    $idH = (int) $h['id'];
    $formId = 'horario-' . $idH;
    $inicioBr = substr((string) $h['hora_inicio'], 0, 5);
    $fimBr = substr((string) $h['hora_fim'], 0, 5);

    echo '<tr class="' . ($h['ativo'] ? '' : 'adm-linha-fria') . '">';

    echo '  <td><select name="dia_semana" form="' . $formId . '" class="adm-input adm-input--cheio">'
        . $selectDias((int) $h['dia_semana'])
        . '</select></td>';

    echo '  <td><input type="time" name="hora_inicio" form="' . $formId . '" required'
        . ' class="adm-input adm-input--hora" value="' . Helpers::escapar($inicioBr) . '"></td>';

    echo '  <td><input type="time" name="hora_fim" form="' . $formId . '" required'
        . ' class="adm-input adm-input--hora" value="' . Helpers::escapar($fimBr) . '"></td>';

    echo '  <td><span class="adm-status ' . ($h['ativo'] ? 'is-confirmado' : 'is-cancelado') . '">'
        . ($h['ativo'] ? 'Ativo' : 'Inativo') . '</span></td>';

    echo '  <td class="adm-col-acoes">';

    echo '    <form id="' . $formId . '" method="post" class="adm-inline">';
    echo '      <input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">';
    echo '      <input type="hidden" name="acao" value="salvar">';
    echo '      <input type="hidden" name="id" value="' . $idH . '">';
    echo '      <button class="adm-btn adm-btn--primario adm-btn--pequeno" type="submit">Salvar</button>';
    echo '    </form>';

    echo '    <form method="post" class="adm-inline">';
    echo '      <input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">';
    echo '      <input type="hidden" name="acao" value="alternar">';
    echo '      <input type="hidden" name="id" value="' . $idH . '">';
    echo '      <button class="adm-btn adm-btn--sutil adm-btn--pequeno" type="submit">'
        . ($h['ativo'] ? 'Desativar' : 'Ativar') . '</button>';
    echo '    </form>';

    $confirmar = 'Remover ' . addslashes(Helpers::diaSemana((int) $h['dia_semana']) . ' das ' . $inicioBr . ' as ' . $fimBr) . '?';
    echo '    <form method="post" class="adm-inline" onsubmit="return confirm(\'' . $confirmar . '\')">';
    echo '      <input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">';
    echo '      <input type="hidden" name="acao" value="excluir">';
    echo '      <input type="hidden" name="id" value="' . $idH . '">';
    echo '      <button class="adm-btn adm-btn--perigo adm-btn--pequeno" type="submit">Remover</button>';
    echo '    </form>';

    echo '  </td>';
    echo '</tr>';
}

echo '</tbody></table></div>';

// ---- criar uma faixa nova ----
// O formulario so aparece depois de clicar no botao: sem o botao, a tela
// fica limpa com a lista; com o botao, o formulario aparece abaixo.
// <details> faz isso sem JavaScript e volta a fechar sozinho apos salvar,
// porque a pagina e recarregada pelo redirect do POST.
echo '<details class="adm-card adm-card--novo adm-card--novo-horario"'
    . ($abrirNovo ? ' open' : '') . '>';
echo '<summary class="adm-btn adm-btn--primario adm-novo-resumo">+ Adicionar horário</summary>';

echo '<div class="adm-novo-conteudo">';
echo '  <h2 class="adm-subtitulo">Adicionar uma faixa</h2>';
echo '  <p class="adm-nota">Use uma faixa por período: manhã e tarde são duas linhas diferentes. A faixa entra ativa.</p>';
echo '  <form method="post" class="adm-form">';
echo '    <input type="hidden" name="csrf" value="' . Helpers::escapar($csrf) . '">';
echo '    <input type="hidden" name="acao" value="salvar">';
echo '    <div class="adm-linha adm-linha--3">';
echo '      <label class="adm-campo"><span>Dia</span><select name="dia_semana">'
    . $opcoesDias . '</select></label>';
echo '      <label class="adm-campo"><span>De</span>'
    . '<input type="time" name="hora_inicio" value="08:00" required></label>';
echo '      <label class="adm-campo"><span>Até</span>'
    . '<input type="time" name="hora_fim" value="12:00" required></label>';
echo '    </div>';
echo '    <div class="adm-form-acoes">';
echo '      <button type="submit" class="adm-btn adm-btn--primario">Salvar horário</button>';
echo '      <a class="adm-btn adm-btn--sutil" href="' . Helpers::baseUrl() . '/admin/horarios.php">Cancelar</a>';
echo '    </div>';
echo '  </form>';
echo '</div>';
echo '</details>';

echo '<p class="adm-nota">O site informa "Segunda a sábado". Você pode ajustar livremente — '
   . 'o assistente sempre usa esta tabela como fonte da verdade.</p>';

echo '</div>';

Views::fim();
