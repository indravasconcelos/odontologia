<?php
/**
 * zerar-demo — remove os dados de demonstracao
 *
 * Mantem usuarios, servicos e horarios.
 * Apaga agendamentos, bloqueios e pacientes ficticios.
 *
 * Local: php database/zerar-demo.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este arquivo e executado pelo terminal.');
}

$pdo = Database::conexao();

$pdo->exec('DELETE FROM agendamentos');
$pdo->exec('DELETE FROM bloqueios');

// remove somente os pacientes que nao tem mais agendamento
$pdo->exec('DELETE FROM pacientes WHERE id NOT IN (SELECT DISTINCT paciente_id FROM agendamentos)');

$restantes = [
    'agendamentos' => (int) Database::valor('SELECT COUNT(*) FROM agendamentos', [], 0),
    'bloqueios'    => (int) Database::valor('SELECT COUNT(*) FROM bloqueios', [], 0),
    'pacientes'    => (int) Database::valor('SELECT COUNT(*) FROM pacientes', [], 0),
    'servicos'     => (int) Database::valor('SELECT COUNT(*) FROM servicos', [], 0),
];

echo PHP_EOL . '  Dados de demonstração removidos.' . PHP_EOL;
echo '  Agendamentos: ' . $restantes['agendamentos'] . PHP_EOL;
echo '  Bloqueios: ' . $restantes['bloqueios'] . PHP_EOL;
echo '  Pacientes: ' . $restantes['pacientes'] . PHP_EOL;
echo '  Serviços mantidos: ' . $restantes['servicos'] . PHP_EOL . PHP_EOL;

exit(0);