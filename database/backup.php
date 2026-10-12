<?php
/**
 * backup — gera uma copia de seguranca de todos os dados do sistema
 *
 * ---------------------------------------------------------------------
 *  LOCAL
 *      php database/backup.php
 *
 *  HOSTINGER (Terminal do hPanel)
 *      cd ~/public_html
 *      php database/backup.php
 *
 *  O arquivo sai em backups/ com o nome do dia e da hora, por exemplo:
 *      backups/backup-2026-10-08_1430.sql
 *
 *  A pasta backups/ tem .htaccess propio e nunca e servida pela web.
 *  As copias contem dados pessoais dos pacientes: baixe-as do servidor
 *  e guarde em local seguro, longe do site.
 *
 *  Para restaurar: phpMyAdmin -> selecione o banco -> Importar -> escolha o .sql
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

// Este arquivo mexe em todos os dados: so pelo terminal.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este arquivo e executado pelo terminal.');
}

$destino = dirname(__DIR__) . '/backups';

if (!is_dir($destino) && !@mkdir($destino, 0775, true)) {
    fwrite(STDERR, "ERRO: nao consegui criar a pasta backups/. Verifique a permissao.\n");
    exit(1);
}

$arquivo = $destino . '/backup-' . date('Y-m-d_His') . '.sql';

// -------------------------------------------------------------------
// 1. Cabecalho do arquivo
// -------------------------------------------------------------------
$conteudo = "-- ===================================================================\n";
$conteudo .= "--  BACKUP DO BANCO - Dr. Maycon Gomes\n";
$conteudo .= '--  Gerado em ' . date('d/m/Y H:i:s') . "\n";
$conteudo .= '--  Banco: ' . Env::get('DB_NAME', '') . "\n";
$conteudo .= "--  Arquivo gerado pelo sistema. Guarde em local seguro.\n";
$conteudo .= "-- ===================================================================\n\n";

$conteudo .= "SET NAMES utf8mb4;\n";
$conteudo .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

// -------------------------------------------------------------------
// 2. Estrutura e dados de cada tabela
// -------------------------------------------------------------------
$tabelas = Database::todos(
    'SELECT table_name AS nome, table_rows AS linhas
       FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_type = "BASE TABLE"
      ORDER BY table_name'
);

if ($tabelas === []) {
    fwrite(STDERR, "ERRO: nenhuma tabela encontrada. O banco esta vazio?\n");
    exit(1);
}

$totalRegistros = 0;

foreach ($tabelas as $tabela) {
    $nome = (string) $tabela['nome'];

    // nome vem do information_schema: valida antes de montar o comando
    if (!preg_match('/^[A-Za-z0-9_]+$/', $nome)) {
        continue;
    }

    $linhas = (int) $tabela['linhas'];

    $conteudo .= "-- -------------------------------------------------------------------\n";
    $conteudo .= "-- Tabela: {$nome}\n";
    $conteudo .= "-- -------------------------------------------------------------------\n\n";

    // estrutura (CREATE TABLE), exatamente como o MySQL a devolve
    $criar = Database::primeiro('SHOW CREATE TABLE `' . $nome . '`');
    if (is_array($criar) && isset($criar['Create Table'])) {
        $conteudo .= 'DROP TABLE IF EXISTS `' . $nome . '`;' . PHP_EOL;
        $conteudo .= (string) $criar['Create Table'] . ';' . PHP_EOL . PHP_EOL;
    }

    // dados
    if ($linhas === 0) {
        $conteudo .= '-- (sem registros)' . PHP_EOL . PHP_EOL;
        continue;
    }

    $dados = Database::todos('SELECT * FROM `' . $nome . '`');

    $conteudo .= 'INSERT INTO `' . $nome . '` VALUES' . PHP_EOL;

    $valores = [];
    foreach ($dados as $linha) {
        $campos = [];
        foreach ($linha as $valor) {
            $campos[] = $valor === null
                ? 'NULL'
                : Database::conexao()->quote((string) $valor);
        }
        $valores[] = '(' . implode(',', $campos) . ')';
    }

    $conteudo .= implode(',' . PHP_EOL, $valores) . ';' . PHP_EOL . PHP_EOL;
    $totalRegistros += count($dados);
}

$conteudo .= "SET FOREIGN_KEY_CHECKS = 1;\n";

// -------------------------------------------------------------------
// 3. Grava o arquivo
// -------------------------------------------------------------------
$bytes = @file_put_contents($arquivo, $conteudo, LOCK_EX);

if ($bytes === false) {
    fwrite(STDERR, "ERRO: nao consegui escrever o arquivo de backup.\n");
    exit(1);
}

@chmod($arquivo, 0640);

echo 'Backup concluido.' . PHP_EOL;
echo 'Arquivo : ' . basename($arquivo) . PHP_EOL;
echo 'Tabelas : ' . count($tabelas) . PHP_EOL;
echo 'Registros: ' . $totalRegistros . PHP_EOL;
echo 'Tamanho : ' . number_format($bytes / 1024, 1, ',', '.') . ' KB' . PHP_EOL;
echo PHP_EOL;
echo 'Guarde esta copia fora do servidor.' . PHP_EOL;

exit(0);