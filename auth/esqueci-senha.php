<?php
/**
 * auth/esqueci-senha.php — passo 1: o usuario informa o e-mail
 *
 * Por seguranca, a resposta e a mesma exista ou nao a conta.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/views/Views.php';

$enviado = false;
$linkGerado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Helpers::exigirCsrf();

    $email = (string) Helpers::post('email', '');

    if (!Helpers::emailValido($email)) {
        $enviado = true;
    } else {
        $link = Auth::gerarTokenRecuperacao($email);

        if ($link !== null) {
            Mailer::enviar(
                $email,
                'Redefinir sua senha — Dr. Maycon Gomes',
                Mailer::templateRecuperacao('tudo certo', $link)
            );

            // Em desenvolvimento, sem SMTP, mostramos o link na tela
            if (Env::get('SMTP_HOST') === null || Env::get('SMTP_HOST') === '') {
                $linkGerado = $link;
            }
        }

        $enviado = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Esqueci minha senha · Painel</title>
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
        <span class="adm-logo" aria-hidden="true"><img src="<?= Helpers::baseUrl() ?>/assets/logo.svg" alt=""></span>
        <span>
          <strong>Dr. Maycon Gomes</strong>
          <small>Ortodontia / Clínico Geral · CRO/RO 3795</small>
        </span>
      </a>

      <?php if ($enviado): ?>
        <h1>Verifique seu e-mail</h1>
        <p class="adm-login-sub">
          Se houver uma conta com esse e-mail, você vai receber um link para criar uma nova senha.
          O link vale por 60 minutos.
        </p>

        <?php if ($linkGerado !== null): ?>
          <div class="adm-msg adm-msg--aviso">
            <strong>Ambiente de teste:</strong> o e-mail não foi enviado porque o SMTP não está configurado.
            <br>Use este link como se tivesse recebido:
          </div>
          <p class="adm-link-teste"><a href="<?= Helpers::escapar($linkGerado) ?>"><?= Helpers::escapar($linkGerado) ?></a></p>
        <?php endif; ?>

        <p class="adm-login-volta"><a href="<?= Helpers::baseUrl() ?>/auth/login.php">← Voltar para o login</a></p>
      <?php else: ?>
        <h1>Esqueci minha senha</h1>
        <p class="adm-login-sub">Informe o e-mail do seu acesso e enviaremos um link de recuperação.</p>

        <form method="post" action="" class="adm-form">
          <input type="hidden" name="csrf" value="<?= Helpers::escapar(Helpers::csrfToken()) ?>">

          <label class="adm-campo">
            <span>E-mail</span>
            <input type="email" name="email" required autocomplete="username" autofocus>
          </label>

          <button type="submit" class="adm-btn adm-btn--primario">Enviar link</button>
        </form>

        <p class="adm-login-volta"><a href="<?= Helpers::baseUrl() ?>/auth/login.php">← Voltar para o login</a></p>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>