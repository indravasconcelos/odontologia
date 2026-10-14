<?php
/**
 * Views — modelos do painel, com o mesmo visual do site
 * (navy, turquesa, Sora + Inter) reaproveitando css/base.css
 */

declare(strict_types=1);

final class Views
{
    /** Ícones em traço do painel (24x24, mesmo desenho do site). */
    private const ICONES = [
        'calendario' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'esquerda'   => '<path d="m15 18-6-6 6-6"/>',
        'direita'    => '<path d="m9 18 6-6-6-6"/>',
        'relogio'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 1.8"/>',
        'pessoa'     => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
        'usuarios'   => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'check'      => '<path d="M20 6 9 17l-5-5"/>',
        'check-circ' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
        'telefone'   => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>',
        'busca'      => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
        'lapis'      => '<path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'lixeira'    => '<path d="M4 7h16M9.5 7V4.5h5V7M6.5 7l.9 12.1a2 2 0 0 0 2 1.9h5.2a2 2 0 0 0 2-1.9L17.5 7"/>',
        'mais'       => '<path d="M12 5v14M5 12h14"/>',
        'vazio'      => '<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 13h4l1.5 2.5h7L17 13h4"/>',
        'ajuda'      => '<circle cx="12" cy="12" r="9"/><path d="M9.8 9.3a2.3 2.3 0 1 1 3.3 2.1c-.7.3-1.1.9-1.1 1.6v.5"/><path d="M12 17h.01"/>',
        'alerta'     => '<path d="M12 9.5V14M12 17.5h.01"/><path d="M10.3 3.9 2.4 17.5A2 2 0 0 0 4.1 20.5h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>',
    ];

    /** Desenha um ícone do painel. */
    public static function icone(string $nome, int $tamanho = 16): string
    {
        $desenho = self::ICONES[$nome] ?? '';

        return '<svg viewBox="0 0 24 24" width="' . $tamanho . '" height="' . $tamanho . '" fill="none"'
            . ' stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"'
            . ' aria-hidden="true" focusable="false">' . $desenho . '</svg>';
    }

    /** senha padrao ainda no ar: some quando a senha for trocada */
    public static function senhaPadraoAtiva(): bool
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

        if (self::senhaPadraoAtiva()) {
            $avisos[] = '<strong>Senha padrão.</strong> Antes de publicar, troque a senha em <a href="'
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
        <span class="adm-logo" aria-hidden="true"><img src="<?= Helpers::baseUrl() ?>/assets/logo.svg" alt=""></span>
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