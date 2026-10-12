<?php
/**
 * admin/agendamento.php — cria, edita e remarca um agendamento
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/exigir-login.php';
require_once dirname(__DIR__) . '/app/views/Views.php';
require_once dirname(__DIR__) . '/app/agenda/Agenda.php';

$id = (int) Helpers::e('id', 0);
$atual = $id > 0 ? Agenda::agendamento($id) : null;

$data = (string) Helpers::e('data', Helpers::hoje());
$hora = (string) Helpers::e('hora', '');
$erro = null;

$valores = [
    'paciente_id' => (int) Helpers::e('paciente_id', 0),
    'servico_id'  => (int) Helpers::e('servico_id', 0),
    'data'        => $data,
    'hora'        => $hora,
    'observacoes' => (string) Helpers::e('observacoes', ''),
    'excepcional' => Helpers::e('excepcional') !== null,
];

if ($atual !== null) {
    $valores = [
        'paciente_id' => (int) $atual['paciente_id'],
        'servico_id'  => (int) $atual['servico_id'],
        'data'        => (string) $atual['data'],
        'hora'        => substr((string) $atual['hora_inicio'], 0, 5),
        'observacoes' => (string) ($atual['observacoes'] ?? ''),
        'excepcional' => str_contains((string) ($atual['observacoes'] ?? ''), 'Fora do expediente'),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Helpers::exigirCsrf();

    $acao = (string) Helpers::post('acao', 'salvar');
    $excepcional = Helpers::post('excepcional') !== null;

    // cancelar, a partir desta tela
    if ($acao === 'cancelar' && $id > 0) {
        $resultado = Agenda::cancelar($id);

        if ($resultado['ok']) {
            Session::flash('Agendamento cancelado.', 'ok');
        } else {
            Session::flash($resultado['erro'] ?? 'Não foi possível cancelar.', 'erro');
        }

        Helpers::redirecionar(Helpers::baseUrl() . '/admin/?data=' . urlencode((string) Helpers::post('data', $data)));
    }

    $servicoId = (int) Helpers::post('servico_id', 0);
    $servico = Agenda::servico($servicoId);

    if ($servico === null) {
        $erro = 'Escolha um serviço.';
    } else {
        $hora = (string) Helpers::post('hora', '');

        if ($hora === '') {
            $erro = 'Escolha o horário.';
        } elseif ($acao === 'remarcar' && $id > 0) {
            $resultado = Agenda::remarcar(
                $id,
                (string) Helpers::post('data', ''),
                $hora . ':00',
                $excepcional
            );
        } else {
            $nome = (string) Helpers::post('nome', '');
            $telefone = (string) Helpers::post('telefone', '');

            $resultado = Agenda::criar(
                nomePaciente: $nome,
                servicoId: $servicoId,
                dataIso: (string) Helpers::post('data', ''),
                horaInicio: $hora . ':00',
                telefone: $telefone !== '' ? $telefone : null,
                origem: 'admin',
                observacoes: (string) Helpers::post('observacoes', ''),
                excepcional: $excepcional
            );
        }

        if (isset($resultado['ok']) && $resultado['ok']) {
            Session::flash(
                ($acao === 'remarcar' ? 'Agendamento remarcado.' : 'Agendamento criado.')
                . ($excepcional ? ' (fora do expediente)' : '')
            );
            Helpers::redirecionar(Helpers::baseUrl() . '/admin/?data=' . urlencode((string) Helpers::post('data', '')));
        }

        $erro = $resultado['erro'] ?? 'Não foi possível salvar.';
    }

    $valores = [
        'paciente_id' => (int) Helpers::post('paciente_id', 0),
        'servico_id'  => $servicoId,
        'data'        => (string) Helpers::post('data', $data),
        'hora'        => (string) Helpers::post('hora', ''),
        'observacoes' => (string) Helpers::post('observacoes', ''),
        'excepcional' => $excepcional,
    ];
}

$servicos = Agenda::servicosAtivos();
$pacientes = Database::todos('SELECT id, nome, telefone FROM pacientes ORDER BY nome LIMIT 200');

$slots = [];
if (!$valores['excepcional'] && Helpers::dataValida($valores['data']) && $valores['servico_id'] > 0) {
    $servico = Agenda::servico($valores['servico_id']);
    if ($servico !== null) {
        $disponibilidade = Agenda::disponibilidade($valores['data'], (int) $servico['duracao_minutos']);
        $slots = $disponibilidade['slots'];

        // ao remarcar, o horario atual precisa aparecer na lista
        if ($atual !== null && $valores['hora'] !== '' && !in_array($valores['hora'] . ':00', $slots, true)) {
            $slots[] = $valores['hora'] . ':00';
            sort($slots);
        }
    }
}

Views::inicio($atual !== null ? 'Editar agendamento' : 'Novo agendamento', 'agenda');

echo '<div class="adm-container adm-container--estreito">';
Views::pagina(
    $atual !== null ? 'Editar agendamento' : 'Novo agendamento',
    $atual !== null
        ? $atual['paciente_nome'] . ' · ' . $atual['servico_nome'] . ' · ' . Helpers::dataBr($atual['data'])
        : 'O paciente é criado automaticamente ao salvar.'
);

Views::feedback(null, $erro);

echo '<form method="post" class="adm-form adm-form--card">';
echo '  <input type="hidden" name="csrf" value="' . Helpers::escapar(Helpers::csrfToken()) . '">';

if ($atual !== null) {
    echo '  <input type="hidden" name="id" value="' . (int) $atual['id'] . '">';
    echo '  <input type="hidden" name="paciente_id" value="' . (int) $atual['paciente_id'] . '">';
    echo '  <div class="adm-linha"><label class="adm-campo"><span>Paciente</span>'
       . '<input type="text" value="' . Helpers::escapar($atual['paciente_nome']) . '" disabled></label></div>';
} else {
    echo '  <div class="adm-linha adm-linha--2">';
    echo '    <label class="adm-campo"><span>Nome do paciente *</span>';
    echo '      <input type="text" name="nome" required value="'
       . Helpers::escapar($pacientes !== [] && $valores['paciente_id'] > 0
           ? (string) (Database::valor('SELECT nome FROM pacientes WHERE id = ?', [$valores['paciente_id']], '')
           )
           : '')
       . '" autocomplete="name"></label>';
    echo '    <label class="adm-campo"><span>Telefone</span>';
    echo '      <input type="tel" name="telefone" placeholder="(69) 99999-0000" autocomplete="tel"></label>';
    echo '  </div>';

    echo '  <details class="adm-detalhes"><summary>Escolher um paciente já cadastrado</summary>';
    echo '    <label class="adm-campo"><span>Paciente</span><select name="paciente_id">';
    echo '      <option value="0">— Novo paciente —</option>';
    foreach ($pacientes as $p) {
        $sel = ((int) $p['id'] === $valores['paciente_id']) ? ' selected' : '';
        echo '<option value="' . (int) $p['id'] . '"' . $sel . '>' . Helpers::escapar($p['nome']) . '</option>';
    }
    echo '    </select></label>';
    echo '  </details>';
}

echo '  <div class="adm-linha adm-linha--2">';
echo '    <label class="adm-campo"><span>Serviço *</span><select name="servico_id" required>';
echo '      <option value="">— Escolha —</option>';
foreach ($servicos as $s) {
    $sel = ((int) $s['id'] === $valores['servico_id']) ? ' selected' : '';
    echo '<option value="' . (int) $s['id'] . '"' . $sel . '>'
       . Helpers::escapar($s['nome']) . ' (' . (int) $s['duracao_minutos'] . ' min)</option>';
}
echo '    </select></label>';
echo '    <label class="adm-campo"><span>Data *</span>';
echo '      <input type="date" name="data" required value="' . Helpers::escapar($valores['data']) . '"></label>';
echo '  </div>';

echo '  <label class="adm-check adm-check--destaque">';
echo '    <input type="checkbox" name="excepcional" value="1" id="adm-excepcional"'
   . ($valores['excepcional'] ? ' checked' : '') . ' onchange="alternarExcepcional()">';
echo '    <span class="adm-check-texto">';
echo '      <strong>Atendimento fora do expediente</strong>';
echo '      <small class="adm-dica">Use quando precisar agendar em outro dia, fora do horário ou bem perto da data. '
   . 'O sistema continua impedindo dois atendimentos no mesmo horário.</small>';
echo '    </span>';
echo '  </label>';

echo '  <label class="adm-campo" id="adm-bloco-horario">';
echo '    <span>Hora *</span>';
echo '    <select name="hora" id="adm-hora-lista"'
   . ($valores['excepcional'] ? ' disabled' : '') . '>';

if (!$valores['excepcional']) {
    echo '      <option value="">— Escolha —</option>';
    $horaAtual = $valores['hora'] !== '' ? $valores['hora'] . ':00' : '';

    foreach ($slots as $slot) {
        $sel = ($slot === $horaAtual) ? ' selected' : '';
        echo '      <option value="' . Helpers::escapar(substr($slot, 0, 5)) . '"' . $sel . '>'
           . Helpers::escapar(Helpers::horaBr($slot)) . '</option>';
    }
}

echo '    </select>';
echo '    <input type="time" name="hora" id="adm-hora-livre" step="300"'
   . ' value="' . Helpers::escapar($valores['hora']) . '"'
   . ($valores['excepcional'] ? '' : ' disabled') . '>';
echo '    <small class="adm-dica" id="adm-dica-hora">'
   . ($valores['excepcional']
       ? 'Escolha qualquer hora. A duração do serviço define até que horas vai.'
       : 'Horários livres para o serviço e a data escolhidos, calculados pelo sistema.')
   . '</small>';
echo '  </label>';

echo '  <label class="adm-campo"><span>Observações</span>';
echo '    <textarea name="observacoes" rows="2">' . Helpers::escapar($valores['observacoes']) . '</textarea></label>';

echo '  <div class="adm-acoes">';

// A acao vai no proprio botao, em vez de input escondido.
// Nao pode existir um <form> aninhado aqui: o navegador ignora a
// tag do form interno, os inputs dele entram no form principal e o
// "cancelar" acabava chegando junto do "remarcar" — o agendamento
// era cancelado em vez de remarcado.
echo '    <button type="submit" name="acao"'
   . ' value="' . ($atual !== null ? 'remarcar' : 'salvar') . '"'
   . ' class="adm-btn adm-btn--primario">'
   . ($atual !== null ? 'Remarcar' : 'Criar agendamento') . '</button>';
echo '    <a class="adm-btn adm-btn--sutil" href="' . Helpers::baseUrl() . '/admin/">Cancelar</a>';

if ($atual !== null && $atual['status'] !== 'cancelado') {
    echo '    <button type="submit" name="acao" value="cancelar"'
       . ' class="adm-btn adm-btn--perigo adm-btn--pequeno"'
       . ' onclick="return confirm(\'Cancelar este atendimento?\')">Cancelar atendimento</button>';
}

echo '  </div>';
echo '</form>';

if (!$valores['excepcional'] && $slots === [] && $valores['servico_id'] > 0 && Helpers::dataValida($valores['data'])) {
    echo '<div class="adm-msg adm-msg--aviso">Não há horários livres para este serviço nesta data. '
       . 'Se o atendimento for excepcional, marque a opção acima para agendar assim mesmo.</div>';
}

echo '<p class="adm-nota">O sistema impede dois agendamentos no mesmo horário, mesmo que duas pessoas '
   . 'tentem marcar ao mesmo tempo — inclusive na modalidade fora do expediente.</p>';

echo '<script>';
echo 'function alternarExcepcional() {';
echo '  var marcado = document.getElementById("adm-excepcional").checked;';
echo '  var lista = document.getElementById("adm-hora-lista");';
echo '  var livre = document.getElementById("adm-hora-livre");';
echo '  var dica = document.getElementById("adm-dica-hora");';
echo '  lista.disabled = marcado; livre.disabled = !marcado;';
echo '  dica.textContent = marcado';
echo '    ? "Escolha qualquer hora. A duração do serviço define até que horas vai."';
echo '    : "Horários livres para o serviço e a data escolhidos, calculados pelo sistema.";';
echo '  if (!marcado && livre.value) {';
echo '    var existe = Array.prototype.some.call(lista.options, function (o) { return o.value === livre.value; });';
echo '    if (!existe && lista.options.length > 0) {';
echo '      var opt = document.createElement("option");';
echo '      opt.value = livre.value; opt.textContent = livre.value + " (horário atual)";';
echo '      lista.appendChild(opt);';
echo '    }';
echo '    lista.value = livre.value;';
echo '  }';
echo '  if (marcado) livre.value = livre.value || lista.value;';
echo '  else livre.value = "";';
echo '}';
echo '</script>';

echo '</div>';

Views::fim();