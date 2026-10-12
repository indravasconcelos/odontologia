<?php
/**
 * admin/conta.php — dados do usuario e troca de senha
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/exigir-login.php';
require_once dirname(__DIR__) . '/app/views/Views.php';

$erro = null;
$ok = null;
$flash = Session::pegaFlash();

if ($flash !== null) {
    if ($flash['tipo'] === 'ok') {
        $ok = $flash['mensagem'];
    } else {
        $erro = $flash['mensagem'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Helpers::exigirCsrf();

    $acao = (string) Helpers::post('acao', '');
    $usuarioId = (int) Session::usuarioId();

    try {
        if ($acao === 'senha') {
            $resultado = Auth::trocarSenha(
                $usuarioId,
                (string) Helpers::post('atual', ''),
                (string) Helpers::post('nova', '')
            );

            if (!$resultado['ok']) {
                throw new RuntimeException($resultado['erro'] ?? 'Não foi possível trocar a senha.');
            }

            Session::flash('Senha alterada com sucesso.');
            Helpers::redirecionar(Helpers::baseUrl() . '/admin/conta.php');
        }

        if ($acao === 'perfil') {
            $nome = trim((string) Helpers::post('nome', ''));
            $email = trim((string) Helpers::post('email', ''));

            if (mb_strlen($nome) < 2) {
                throw new RuntimeException('Informe seu nome.');
            }
            if (!Helpers::emailValido($email)) {
                throw new RuntimeException('E-mail inválido.');
            }

            $outroComMesmoEmail = Database::valor(
                'SELECT COUNT(*) FROM usuarios WHERE email = ? AND id <> ?',
                [$email, $usuarioId],
                0
            );

            if ((int) $outroComMesmoEmail > 0) {
                throw new RuntimeException('Este e-mail já pertence a outro usuário.');
            }

            Database::query(
                'UPDATE usuarios SET nome = ?, email = ? WHERE id = ?',
                [$nome, $email, $usuarioId]
            );

            Session::flash('Dados atualizados.');
            Helpers::redirecionar(Helpers::baseUrl() . '/admin/conta.php');
        }
    } catch (Throwable $e) {
        Session::flash($e->getMessage(), 'erro');
    }

    Helpers::redirecionar(Helpers::baseUrl() . '/admin/conta.php');
}

$usuario = Auth::usuario() ?? ['nome' => '', 'email' => '', 'tipo' => 'admin', 'criado_em' => null];
$demo = Views::senhaDemoAtiva();
$iaModo = (string) (Env::get('AI_MODO', 'auto'));
$iaAtiva = $iaModo !== 'local' && (string) Env::get('DEEPSEEK_API_KEY') !== '';

Views::inicio('Minha conta', 'conta');

echo '<div class="adm-container adm-container--estreito">';
Views::pagina('Minha conta', 'Acesso ao painel da agenda.');
Views::feedback($ok, $erro);

if ($demo) {
    echo '<div class="adm-alerta adm-alerta--forte" role="alert">';
    echo '  <strong>Atenção: a senha ainda é a de demonstração.</strong>';
    echo '  Troque abaixo antes de publicar o site. O aviso some assim que a senha mudar.';
    echo '</div>';
}

echo '<form method="post" class="adm-form adm-form--card">';
echo '  <h2 class="adm-subtitulo">Dados de acesso</h2>';
echo '  <input type="hidden" name="csrf" value="' . Helpers::escapar(Helpers::csrfToken()) . '">';
echo '  <input type="hidden" name="acao" value="perfil">';
echo '  <label class="adm-campo"><span>Nome</span><input type="text" name="nome" required value="'
   . Helpers::escapar($usuario['nome']) . '"></label>';
echo '  <label class="adm-campo"><span>E-mail</span><input type="email" name="email" required value="'
   . Helpers::escapar($usuario['email']) . '"></label>';
echo '  <p class="adm-nota">Perfil: ' . Helpers::escapar(ucfirst($usuario['tipo']))
   . ' · conta criada em ' . Helpers::escapar(Helpers::dataBr($usuario['criado_em'])) . '</p>';
echo '  <button type="submit" class="adm-btn adm-btn--primario">Salvar dados</button>';
echo '</form>';

echo '<form method="post" class="adm-form adm-form--card">';
echo '  <h2 class="adm-subtitulo">Trocar senha</h2>';
echo '  <input type="hidden" name="csrf" value="' . Helpers::escapar(Helpers::csrfToken()) . '">';
echo '  <input type="hidden" name="acao" value="senha">';
echo '  <label class="adm-campo"><span>Senha atual</span><input type="password" name="atual" required autocomplete="current-password"></label>';
echo '  <label class="adm-campo"><span>Nova senha</span><input type="password" name="nova" required minlength="8" autocomplete="new-password">';
echo '    <small class="adm-dica">Pelo menos 8 caracteres.</small></label>';
echo '  <button type="submit" class="adm-btn adm-btn--primario">Trocar senha</button>';
echo '</form>';

echo '<div class="adm-card">';
echo '  <h2 class="adm-subtitulo">Agente de IA</h2>';
echo '  <p class="adm-nota">Modo no .env: <strong>' . Helpers::escapar($iaModo) . '</strong></p>';
echo '  <p class="adm-nota">Estado atual: <strong>'
   . ($iaAtiva ? 'usando a DeepSeek' : 'usando o modo local da demonstração') . '</strong></p>';
echo '  <p class="adm-nota">A chave da API nunca fica visível no site: ela é lida apenas pelo PHP, no servidor.</p>';
echo '</div>';

echo '</div>';

Views::fim();