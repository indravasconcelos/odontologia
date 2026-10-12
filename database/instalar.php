<?php
/**
 * instalar — cria as tabelas, importa os dados iniciais e o usuario administrativo
 *
 * ---------------------------------------------------------------------
 *  LOCAL (servidor portatil)
 *      php database/instalar.php
 *      ou simplesmente "iniciar-demo.bat"
 *
 *  HOSTINGER
 *      Pelo TERMINAL do hPanel (SSH), nao pela web:
 *
 *          cd ~/public_html
 *          php database/instalar.php
 *
 *      O caminho pela web (/database/instalar.php) NAO funciona: o
 *      .htaccess bloqueia a pasta database/ com um 404 de proposito,
 *      justamente para nobody rodar este script pela internet.
 *
 *  Depois de instalar
 *      Apague o arquivo database/instalar.php do servidor.
 *      O marcador database/.instalado impede que ele volte a rodar,
 *      mesmo se o arquivo continuar la por engano.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$silencioso = in_array('--silencioso', $argv ?? [], true);
$tokenRecebido = $_GET['token'] ?? '';
$tokenEsperado = (string) Env::get('INSTALAR_TOKEN', '');

$viaTerminal = PHP_SAPI === 'cli';
$podeExecutar = $viaTerminal || ($tokenEsperado !== '' && hash_equals($tokenEsperado, $tokenRecebido));

// --forcar ignora o marcador .instalado. Serve para o ambiente local,
// onde o iniciar-demo.bat reinstala a cada start.
// Nao use na hospedagem.
$forcar = in_array('--forcar', $argv ?? [], true) || ($_GET['forcar'] ?? '') === '1';

$raiz = dirname(__DIR__);
$marcador = $raiz . '/database/.instalado';

/**
 * O sistema ja foi instalado neste servidor?
 *
 * Este era o ponto fraco: o arquivo .instalado era escrito no fim da
 * instalacao, mas nunca era lido — o script aceitaria rodar de novo
 * quantas vezes alguem quiser, bastando ter o INSTALAR_TOKEN.
 */
function jaInstalado(string $marcador): bool
{
    if (!is_file($marcador)) {
        return false;
    }

    // leitura simples: se o arquivo existe e tem conteudo, esta instalado
    return trim((string) @file_get_contents($marcador)) !== '';
}

function saida(string $mensagem, bool $silencioso): void
{
    if (!$silencioso) {
        echo $mensagem . PHP_EOL;
    }
}

/** executa um arquivo .sql separando por ; */
function executarSql(string $arquivo): int
{
    if (!is_readable($arquivo)) {
        throw new RuntimeException('Arquivo não encontrado: ' . basename($arquivo));
    }

    $sql = file_get_contents($arquivo);
    if ($sql === false) {
        throw new RuntimeException('Não consegui ler: ' . basename($arquivo));
    }

    // remove comentarios de linha
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

    $total = 0;
    foreach (explode(';', $sql) as $comando) {
        $comando = trim($comando);
        if ($comando === '') {
            continue;
        }
        Database::conexao()->exec($comando);
        $total++;
    }

    return $total;
}

if (!$podeExecutar) {
    if ($viaTerminal) {
        saida('Execute pelo terminal ou informe o token.', $silencioso);
        exit(1);
    }

    http_response_code(403);
    exit('Acesso negado.');
}

// -------------------------------------------------------------------
// 0. Ja foi instalado? Encerra sem tocar em nada.
// -------------------------------------------------------------------
if (!$forcar && jaInstalado($marcador)) {
    saida('O sistema ja foi instalado neste servidor.', $silencioso);
    saida('Apague database/instalar.php se nao for mais usar.', $silencioso);
    exit(0);
}

// -------------------------------------------------------------------
// 1. Estrutura
// -------------------------------------------------------------------

try {
    $n = executarSql($raiz . '/database/estrutura.sql');
    saida('Estrutura criada (' . $n . ' comandos).', $silencioso);
} catch (Throwable $e) {
    saida('ERRO na estrutura: ' . $e->getMessage(), $silencioso);
    exit(1);
}

// -------------------------------------------------------------------
// 2. Servicos e horarios
// -------------------------------------------------------------------
try {
    executarSql($raiz . '/database/dados-iniciais.sql');
    saida('Serviços e horários cadastrados.', $silencioso);
} catch (Throwable $e) {
    saida('AVISO nos dados iniciais: ' . $e->getMessage(), $silencioso);
}

// -------------------------------------------------------------------
// 3. Usuario administrativo
// -------------------------------------------------------------------
$emailAdmin = (string) Env::get('ADMIN_EMAIL', 'admin@odontologia.local');
$senhaAdmin = (string) Env::get('ADMIN_SENHA', 'Demo@2026');
$nomeAdmin = (string) Env::get('ADMIN_NOME', 'Dr. Maycon Gomes');

$existente = Database::primeiro('SELECT id FROM usuarios WHERE email = ? LIMIT 1', [$emailAdmin]);

if ($existente === null) {
    Database::query(
        'INSERT INTO usuarios (nome, email, senha_hash, tipo, ativo) VALUES (?, ?, ?, "admin", 1)',
        [$nomeAdmin, $emailAdmin, password_hash($senhaAdmin, PASSWORD_DEFAULT)]
    );
    saida('Usuário admin criado: ' . $emailAdmin, $silencioso);
} else {
    saida('Usuário admin já existe: ' . $emailAdmin, $silencioso);
}

// -------------------------------------------------------------------
// 4. Resumo
// -------------------------------------------------------------------
$contagens = [
    'serviços'     => (int) Database::valor('SELECT COUNT(*) FROM servicos', [], 0),
    'horários'     => (int) Database::valor('SELECT COUNT(*) FROM horarios', [], 0),
    'agendamentos' => (int) Database::valor('SELECT COUNT(*) FROM agendamentos', [], 0),
];

if (!$silencioso) {
    echo PHP_EOL . '  Pronto para usar.' . PHP_EOL;
    echo '  Serviços: ' . $contagens['serviços']
        . ' · Horários: ' . $contagens['horários']
        . ' · Agendamentos: ' . $contagens['agendamentos'] . PHP_EOL;
    echo '  Acesso: ' . $emailAdmin . PHP_EOL . PHP_EOL;
}

//Marca que a instalacao terminou. Escrito tanto pelo terminal quanto
//pela web: e este marcador que impede o script de rodar de novo.
if (@file_put_contents($marcador, date('c') . PHP_EOL) !== false) {
    @chmod($marcador, 0640);
}

exit(0);