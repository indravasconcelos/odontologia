<?php
/**
 * auth/sair.php — encerra a sessao
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

Session::logout();

Helpers::redirecionar(Helpers::baseUrl() . '/auth/login.php?saiu=1');