<?php
/**
 * api/chat.php — endpoint do chat
 *
 * Recebe {mensagem}, valida, chama o ChatService e devolve a resposta.
 * Nunca devolve credencial, chave de API ou erro interno.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/ia/Knowledge.php';
require_once dirname(__DIR__) . '/app/ia/Prompt.php';
require_once dirname(__DIR__) . '/app/ia/Tools.php';
require_once dirname(__DIR__) . '/app/ia/AgenteLog.php';
require_once dirname(__DIR__) . '/app/ia/DeepSeek.php';
require_once dirname(__DIR__) . '/app/ia/Fallback.php';
require_once dirname(__DIR__) . '/app/ia/ChatService.php';

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

// so aceita POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    Helpers::json(['ok' => false, 'erro' => 'Método não permitido.'], 405);
}

Session::iniciar();

// token do chat: impede que outro site use a nossa API
$tokenEsperado = hash('sha256', Helpers::csrfToken() . '|chat');
$tokenRecebido = (string) ($_SERVER['HTTP_X_CHAT_TOKEN'] ?? '');

if (!hash_equals($tokenEsperado, $tokenRecebido)) {
    Helpers::json(['ok' => false, 'erro' => 'Sessão inválida. Atualize a página.'], 419);
}

// limite de mensagens por minuto, por sessao
$agora = time();
$janela = $_SESSION['chat_limite'] ?? ['inicio' => $agora, 'n' => 0];

if ($janela['inicio'] < $agora - 60) {
    $janela = ['inicio' => $agora, 'n' => 0];
}

$janela['n']++;
$_SESSION['chat_limite'] = $janela;

if ($janela['n'] > 30) {
    Helpers::json([
        'ok'    => false,
        'erro'  => 'Você está enviando mensagens muito rápido. Aguarde um instante.',
    ], 429);
}

$entrada = json_decode((string) file_get_contents('php://input'), true);
$mensagem = is_array($entrada) ? (string) ($entrada['mensagem'] ?? '') : '';

if (trim($mensagem) === '') {
    Helpers::json([
        'ok'      => true,
        'resposta' => 'Pode escrever sua dúvida aqui 😊 Respondo o que souber e o resto confirmo com o Dr. Maycon.',
        'modo'    => ChatService::modo(),
    ]);
}

$resultado = ChatService::responder($mensagem);

Helpers::json([
    'ok'       => $resultado['ok'],
    'resposta' => $resultado['texto'],
    'modo'     => $resultado['modo'],
]);