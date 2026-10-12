<?php
/**
 * auth/sessao.php — entrega o token do chat
 *
 * O navegador pede este token uma vez e o usa no cabecalho
 * X-Chat-Token. Isso impede que outro site use a nossa API.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    Helpers::json(['ok' => false, 'erro' => 'Método não permitido.'], 405);
}

Helpers::json([
    'ok'    => true,
    'token' => hash('sha256', Helpers::csrfToken() . '|chat'),
]);