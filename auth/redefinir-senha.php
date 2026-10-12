<?php
/**
 * auth/redefinir-senha.php — passo 2: cria a nova senha
 *
 * O token e valido por 60 minutos e some depois de usado.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/views/Views.php';

$token = (string) Helpers::e('token', '');
$dados = $token !== '' ? Auth::tokenValido($token) : null;

$erro = null;
$feito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dados !== null) {
    Helpers::exigirCsrf();

    $nova = (string) Helpers::post('senha', '');
    $confirmar = (string) Helpers::post('confirmar', '');

    if ($nova === '') {
        $erro = 'Informe a nova senha.';
    } elseif ($nova !== $confirmar) {
        $erro = 'As senhas não conferem.';
    } else {
        $resultado = Auth::usarToken($token, $nova);

        if ($resultado['ok']) {
            $feito = true;
        } else {
            $erro = $resultado['erro'] ?? 'Não foi possível trocar a senha.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Criar nova senha · Painel</title>
  <link rel="icon" type="image/svg+xml" href="<?= Helpers::baseUrl() ?>/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= Helpers::baseUrl() ?>/css/base.css">
  <link rel="stylesheet" href="<?= Helpers::baseUrl() ?>/css/admin.css">
</head>
<body class="adm-body adm-body--login">
  <main class="adm-login">
    <div class="adm-login-card">
      <a class="adm-login-marca" href="<?= Helpers::baseUrl() ?>/">
        <span class="adm-logo" aria-hidden="true"><img src="<?= Helpers::baseUrl() ?>/assets/logo.jpg" alt=""></span>
        <span>
          <strong>Dr. Maycon Gomes</strong>
          <small>Ortodontia / Clínico Geral · CRO/RO 3795</small>
        </span>
      </a>

      <?php if ($feito): ?>
        <h1>Senha criada</h1>
        <p class="adm-login-sub">Agora você pode entrar com a nova senha.</p>
        <a class="adm-btn adm-btn--primario adm-btn--bloco" href="<?= Helpers::baseUrl() ?>/auth/login.php">Ir para o login</a>
      <?php elseif ($dados === null): ?>
        <h1>Link inválido</h1>
        <p class="adm-login-sub">Este link expirou, já foi usado ou não é válido.</p>
        <p class="adm-login-esqueci">
          <a href="<?= Helpers::baseUrl() ?>/auth/esqueci-senha.php">Pedir um novo link</a>
        </p>
      <?php else: ?>
        <h1>Criar nova senha</h1>
        <p class="adm-login-sub">Olá, <?= Helpers::escapar($dados['nome']) ?>. Escolha uma nova senha.</p>

        <?php Views::feedback(null, $erro); ?>

        <form method="post" action="" class="adm-form">
          <input type="hidden" name="csrf" value="<?= Helpers::escapar(Helpers::csrfToken()) ?>">

          <label class="adm-campo">
            <span>Nova senha</span>
            <input type="password" name="senha" required minlength="8" autocomplete="new-password" autofocus>
            <small class="adm-dica">Pelo menos 8 caracteres.</small>
          </label>

          <label class="adm-campo">
            <span>Repetir a senha</span>
            <input type="password" name="confirmar" required minlength="8" autocomplete="new-password">
          </label>

          <button type="submit" class="adm-btn adm-btn--primario">Salvar nova senha</button>
        </form>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>