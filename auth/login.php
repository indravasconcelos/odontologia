<?php
/**
 * auth/login.php — acesso ao painel
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/views/Views.php';

if (Session::logado()) {
    Helpers::redirecionar(Helpers::baseUrl() . '/admin/');
}

$erro = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Helpers::exigirCsrf();

    $email = (string) Helpers::post('email', '');
    $senha = (string) Helpers::post('senha', '');

    if ($senha === '') {
        $erro = 'Informe a senha.';
    } else {
        $resultado = Auth::tentar($email, $senha);

        if ($resultado['ok']) {
            $destino = Session::destino();
            Helpers::redirecionar($destino !== null ? $destino : Helpers::baseUrl() . '/admin/');
        }

        $erro = $resultado['erro'] ?? 'Não foi possível entrar.';
    }
}

$expirada = isset($_GET['expirada']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Entrar · Painel</title>
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

      <h1>Acessar o painel</h1>
      <p class="adm-login-sub">Área restrita do responsável pela agenda.</p>

      <?php if ($expirada): ?>
        <div class="adm-msg adm-msg--aviso">Sua sessão expirou por segurança. Entre novamente.</div>
      <?php endif; ?>

      <?php Views::feedback(null, $erro); ?>

      <form method="post" action="" class="adm-form">
        <input type="hidden" name="csrf" value="<?= Helpers::escapar(Helpers::csrfToken()) ?>">

        <label class="adm-campo">
          <span>E-mail</span>
          <input type="email" name="email" value="<?= Helpers::escapar($email) ?>" required autocomplete="username" autofocus>
        </label>

        <label class="adm-campo">
          <span>Senha</span>
          <input type="password" name="senha" required autocomplete="current-password">
        </label>

        <button type="submit" class="adm-btn adm-btn--primario">Entrar</button>
      </form>

      <a class="adm-login-esqueci" href="<?= Helpers::baseUrl() ?>/auth/esqueci-senha.php">Esqueci minha senha</a>

      <p class="adm-login-volta"><a href="<?= Helpers::baseUrl() ?>/">← Voltar ao site</a></p>
    </div>
  </main>
</body>
</html>