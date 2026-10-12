<?php
/**
 * seed-demo — pacientes e agendamentos de demonstracao
 *
 * Nomes e telefones FICTICIOS, para apresentar o sistema ao cliente.
 * As datas sao calculadas a partir de hoje, entao a agenda aparece
 * sempre preenchida, independentemente do dia da apresentacao.
 *
 * Local     : php database/seed-demo.php
 * Hostinger : nao use em producao
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/agenda/Agenda.php';

$silencioso = in_array('--silencioso', $argv ?? [], true);

function aviso(string $m, bool $silencioso): void
{
    if (!$silencioso) {
        echo $m . PHP_EOL;
    }
}

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este arquivo e executado pelo terminal.');
}

$pessoas = [
    ['Ana Paula Ribeiro',    '69988880001', 'ana.exemplo@email.com'],
    ['Carlos Eduardo Lima',  '69988880002', 'carlos.exemplo@email.com'],
    ['Juliana Marques',       '69988880003', 'juliana.exemplo@email.com'],
    ['Marcos Vinícius Alves', '69988880004', 'marcos.exemplo@email.com'],
    ['Beatriz Nogueira',      '69988880005', 'beatriz.exemplo@email.com'],
    ['Rafael Monteiro',       '69988880006', 'rafael.exemplo@email.com'],
];

// proximos dias uteis a partir de amanha
$diasUteis = [];
$cursor = strtotime('+1 day');
$limiteDias = Env::int('AGENDA_DIAS_A_FUTURO', 60);

while (count($diasUteis) < 20 && $cursor < strtotime('+' . $limiteDias . ' days')) {
    $dia = (int) date('w', $cursor); // 0 domingo, 6 sabado
    if ($dia !== 0) {
        $diasUteis[] = date('Y-m-d', $cursor);
    }
    $cursor = strtotime('+1 day', $cursor);
}

// Evita duplicar dados ao rodar o script mais de uma vez
$jaTem = (int) Database::valor('SELECT COUNT(*) FROM agendamentos', [], 0);
if ($jaTem > 0) {
    aviso('A agenda já tem ' . $jaTem . ' agendamento(s). Rode "zerar-demo.php" antes se quiser recomeçar.', $silencioso);
    exit(0);
}

$pacienteIds = [];

foreach ($pessoas as [$nome, $telefone, $email]) {
    $existente = Database::primeiro('SELECT id FROM pacientes WHERE nome = ? LIMIT 1', [$nome]);

    if ($existente !== null) {
        $pacienteIds[] = (int) $existente['id'];
        continue;
    }

    $pacienteIds[] = Database::inserir(
        'INSERT INTO pacientes (nome, telefone, email) VALUES (?, ?, ?)',
        [$nome, $telefone, $email]
    );
}

$servicos = Agenda::servicosAtivos();
$criados = 0;

foreach ($diasUteis as $indice => $data) {
    // um agendamento a cada dois dias uteis, com hora variando
    if ($indice % 2 !== 0) {
        continue;
    }

    $pacienteId = $pacienteIds[($indice / 2) % count($pacienteIds)];
    $servico = $servicos[$indice % count($servicos)];
    $hora = ($indice % 3 === 0) ? '08:30:00' : '14:00:00';

    $resultado = Agenda::criar(
        nomePaciente: (string) Database::valor('SELECT nome FROM pacientes WHERE id = ?', [$pacienteId], ''),
        servicoId: (int) $servico['id'],
        dataIso: $data,
        horaInicio: $hora,
        telefone: null,
        origem: 'admin',
        observacoes: 'Demonstração'
    );

    if ($resultado['ok']) {
        $criados++;
    } else {
        aviso('  aviso: ' . $data . ' ' . $hora . ' — ' . $resultado['erro'], $silencioso);
    }
}

// um bloqueio de exemplo para o cliente ver a funcionalidade
$diaBloqueio = $diasUteis[3] ?? date('Y-m-d', strtotime('+3 days'));
Database::query(
    'INSERT INTO bloqueios (data, hora_inicio, hora_fim, motivo) VALUES (?, ?, ?, ?)',
    [$diaBloqueio, '12:00:00', '13:30:00', 'Bloqueio de demonstração']
);

aviso('Demonstração criada: ' . $criados . ' agendamento(s), ' . count($pacienteIds) . ' paciente(s).', $silencioso);
aviso('Bloqueio de exemplo em ' . Helpers::dataBr($diaBloqueio) . ' das 12h às 13h30.', $silencioso);

exit(0);