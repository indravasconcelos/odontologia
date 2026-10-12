<?php
/**
 * auth/exigir-login.php
 * Inclua no topo de CADA pagina interna do /admin.
 * Sem sessao valida, manda para o login.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!Session::logado()) {
    if (Helpers::ehJson()) {
        Helpers::json(['ok' => false, 'erro' => 'Sessão expirada. Entre novamente.'], 401);
    }

    $destino = $_SERVER['REQUEST_URI'] ?? '/admin/';
    Session::guardarDestino($destino);

    Helpers::redirecionar(Helpers::baseUrl() . '/auth/login.php?expirada=1');
}

$usuarioLogado = Auth::usuario();

if ($usuarioLogado === null || !$usuarioLogado['ativo']) {
    Session::logout();
    Helpers::redirecionar(Helpers::baseUrl() . '/auth/login.php');
}