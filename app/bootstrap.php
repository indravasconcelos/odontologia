<?php
/**
 * bootstrap — carregamento central da aplicacao
 * Toda pagina (site, api, auth e admin) comeca por este arquivo.
 */

declare(strict_types=1);

if (!defined('APP_INICIADO')) {
    define('APP_INICIADO', true);
    define('APP_RAIZ', dirname(__DIR__));

    require_once APP_RAIZ . '/app/Env.php';
    require_once APP_RAIZ . '/app/Database.php';
    require_once APP_RAIZ . '/app/Session.php';
    require_once APP_RAIZ . '/app/Helpers.php';
    require_once APP_RAIZ . '/app/RateLimit.php';
    require_once APP_RAIZ . '/app/Auth.php';
    require_once APP_RAIZ . '/app/Mailer.php';

    // utf-8 em todas as saidas
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    // ---------------------------------------------------
    //  Erros nunca aparecem na pagina do visitante.
    //  Detalhe de SQL, caminho de arquivo ou credencial
    //  ficariam expostos se o servidor exibisse avisos.
    //  No terminal (CLI) os erros continuam aparecendo,
    //  que e o que precisamos para depurar.
    // ---------------------------------------------------
    if (PHP_SAPI === 'cli') {
        ini_set('display_errors', '1');
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        ini_set('log_errors', '1');
    }
}