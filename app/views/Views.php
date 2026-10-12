<?php
/**
 * Views — modelos do painel, com o mesmo visual do site
 * (navy, turquesa, Sora + Inter) reaproveitando css/base.css
 */

declare(strict_types=1);

final class Views
{
    /** aviso do modo demonstracao: some quando a senha for trocada */
    public static function senhaDemoAtiva(): bool
    {
        if (!Session::logado()) {
            return false;
        }

        try {
            $hash = Database::valor('SELECT senha_hash FROM usuarios WHERE id = ?', [Session::usuarioId()]);
            if (!is_string($hash) || $hash === '') {
                return false;
            }
            return password_verify('Demo@2026', $hash);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Pendencias que so importam depois que o site esta no ar.
     *
     * Cada item devolve um aviso curto. Chamar no painel serve como
     * conferncia: enquanto algo aparecer aqui, o site ainda nao esta
     * pronto para receber paciente de verdade.
     *
     * @return array<int, string>
     */
    public static function pendenciasPublicacao(): array
    {
        $avisos = [];

        if (self::senhaDemoAtiva()) {
            $avisos[] = '<strong>Senha de demonstração.</strong> Antes de publicar, troque a senha em <a href="'
                . Helpers::baseUrl() . '/admin/conta.php">Minha conta</a>.';
        }

        // o token do instalador nao deve existir mais em producao
        $token = trim((string) Env::get('INSTALAR_TOKEN', ''));
        if ($token !== '') {
            $avisos[] = 'O <code>INSTALAR_TOKEN</code> ainda está no <code>.env</code>. Apague essa linha.';
        }

        // se o instalador continua no servidor, avisa para apagar o arquivo
        if (is_file(APP_RAIZ . '/database/instalar.php')) {
            $avisos[] = 'O arquivo <code>database/instalar.php</code> ainda está no servidor. Apague-o.';
        }

        // sem HTTPS os dados trafegam sem criptografia
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        if (!$https) {
            $avisos[] = 'O site não está em HTTPS. Ative o certificado SSL no painel da hospedagem.';
        }

        return $avisos;
    }

    public static function inicio(string $titulo, string $ativo = ''): void
    {
        $usuario = Auth::usuario() ?? ['nome' => '—'];
        $iniciais = mb_strtoupper(mb_substr((string) $usuario['nome'], 0, 1));
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= Helpers::escapar($titulo) ?> · Painel</title>
  <link rel="icon" type="image/svg+xml" href="<?= Helpers::baseUrl() ?>/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= Helpers::baseUrl() ?>/css/base.css">
  <link rel="stylesheet" href="<?= Helpers::baseUrl() ?>/css/admin.css">
</head>
<body class="admin-body">
  <header class="adm-topo">
    <div class="adm-topo-inner">
      <a class="adm-marca" href="<?= Helpers::baseUrl() ?>/admin/">
        <span class="adm-logo" aria-hidden="true"><img src="<?= Helpers::baseUrl() ?>/assets/logo.jpg" alt=""></span>
        <span>
          <strong>Dr. Maycon Gomes</strong>
          <small>Painel da agenda</small>
        </span>
      </a>

      <nav class="adm-nav" aria-label="Menu do painel">
        <a href="<?= Helpers::baseUrl() ?>/admin/" class="<?= $ativo === 'agenda' ? 'is-ativo' : '' ?>">Agenda</a>
        <a href="<?= Helpers::baseUrl() ?>/admin/servicos.php" class="<?= $ativo === 'servicos' ? 'is-ativo' : '' ?>">Serviços</a>
        <a href="<?= Helpers::baseUrl() ?>/admin/horarios.php" class="<?= $ativo === 'horarios' ? 'is-ativo' : '' ?>">Horários</a>
        <a href="<?= Helpers::baseUrl() ?>/admin/bloqueios.php" class="<?= $ativo === 'bloqueios' ? 'is-ativo' : '' ?>">Bloqueios</a>
        <a href="<?= Helpers::baseUrl() ?>/admin/pacientes.php" class="<?= $ativo === 'pacientes' ? 'is-ativo' : '' ?>">Pacientes</a>
        <a href="<?= Helpers::baseUrl() ?>/admin/conta.php" class="<?= $ativo === 'conta' ? 'is-ativo' : '' ?>">Conta</a>
      </nav>

      <div class="adm-usuario">
        <span class="adm-avatar" aria-hidden="true"><?= Helpers::escapar($iniciais) ?></span>
        <a class="adm-sair" href="<?= Helpers::baseUrl() ?>/auth/sair.php">Sair</a>
      </div>
    </div>
  </header>

  <main class="adm-main">
    <?php foreach (self::pendenciasPublicacao() as $aviso): ?>
      <div class="adm-alerta" role="alert">
        <?= $aviso ?>
      </div>
    <?php endforeach; ?>
    <?php
    }

    public static function fim(): void
    {
        ?>
  </main>

  <footer class="adm-rodape">
    <p>© <?= date('Y') ?> Dr. Maycon Gomes · Ortodontia / Clínico Geral · CRO/RO 3795</p>
  </footer>
</body>
</html>
        <?php
    }

    /** bloco de mensagens de feedback */
    public static function feedback(?string $ok = null, ?string $erro = null): void
    {
        if ($ok !== null && $ok !== '') {
            echo '<div class="adm-msg adm-msg--ok" role="status">' . Helpers::escapar($ok) . '</div>';
        }
        if ($erro !== null && $erro !== '') {
            echo '<div class="adm-msg adm-msg--erro" role="alert">' . Helpers::escapar($erro) . '</div>';
        }
    }

    public static function pagina(string $titulo, string $subtitulo = ''): void
    {
        ?>
    <div class="adm-cabecalho">
      <h1><?= Helpers::escapar($titulo) ?></h1>
      <?php if ($subtitulo !== ''): ?>
        <p><?= Helpers::escapar($subtitulo) ?></p>
      <?php endif; ?>
    </div>
        <?php
    }
}