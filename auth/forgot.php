<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    header('Location:/cliente/');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim(strtolower($_POST['email'] ?? ''));
    $turnstileToken = $_POST['cf-turnstile-response'] ?? '';

    if (!validar_turnstile($turnstileToken)) {
        $error = turnstile_configurado()
            ? 'Não foi possível validar a verificação de segurança. Tente novamente.'
            : 'A proteção anti-bot ainda não foi configurada.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail válido.';
    } else {
        $success = 'Se este e-mail estiver cadastrado, você receberá as instruções para criar uma nova senha.';

        try {
            $st = db()->prepare("
                SELECT id, nome, email
                FROM usuarios
                WHERE ativo = 1
                  AND LOWER(email) = ?
                LIMIT 1
            ");
            $st->execute([$email]);
            $usuario = $st->fetch();

            if ($usuario) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);

                db()->prepare("
                    DELETE FROM recuperacao_senha
                    WHERE usuario_id = ?
                       OR expira_em < NOW()
                ")->execute([(int)$usuario['id']]);

                db()->prepare("
                    INSERT INTO recuperacao_senha
                    (
                        usuario_id,
                        token_hash,
                        expira_em,
                        criado_em
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        DATE_ADD(NOW(), INTERVAL 30 MINUTE),
                        NOW()
                    )
                ")->execute([
                    (int)$usuario['id'],
                    $tokenHash
                ]);

                $baseUrl = rtrim(
                    (string)($config['app']['url'] ?? 'https://docesdocasal.kinsmanst.com'),
                    '/'
                );

                $link = $baseUrl . '/auth/reset.php?token=' . urlencode($token);
                $logoUrl = $baseUrl . '/assets/img/SELO_PNG_SF_DDC.png';

                $nome = trim((string)$usuario['nome']);
                $nomeHtml = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
                $linkHtml = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
                $logoHtml = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');

                $assunto = 'Redefinição de senha - Doces do Casal';

                $mensagemHtml = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinição de senha</title>
</head>
<body style="margin:0;padding:0;background:#FFF8F0;font-family:Arial,Helvetica,sans-serif;color:#26150F;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width:100%;background:#FFF8F0;padding:36px 14px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width:100%;max-width:700px;background:#FFFFFF;border:1px solid #EADBD2;border-radius:20px;">
                <tr>
                    <td style="padding:38px 40px;">
                        <div style="text-align:center;margin:0 0 28px;">
                            <img src="' . $logoHtml . '" alt="Doces do Casal" width="130" style="display:inline-block;width:130px;max-width:130px;height:auto;border:0;outline:none;text-decoration:none;">
                        </div>

                        <h1 style="margin:0 0 28px;color:#4A2718;font-size:30px;line-height:1.2;">Doces do Casal</h1>

                        <p style="margin:0 0 18px;font-size:16px;line-height:1.6;">Olá, ' . $nomeHtml . '!</p>

                        <p style="margin:0 0 18px;font-size:16px;line-height:1.6;">
                            Recebemos uma solicitação para redefinir a senha da sua conta.
                        </p>

                        <div style="margin:34px 0;">
                            <a href="' . $linkHtml . '" style="display:inline-block;background:#4A2718;color:#FFFFFF;text-decoration:none;padding:16px 26px;border-radius:10px;font-size:15px;font-weight:bold;">
                                Criar nova senha
                            </a>
                        </div>

                        <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">
                            Este link é válido por <strong>30 minutos</strong> e pode ser utilizado apenas uma vez.
                        </p>

                        <p style="margin:26px 0 0;font-size:14px;line-height:1.6;color:#7D6258;">
                            Se você não solicitou a alteração, ignore este e-mail.
                        </p>

                        <p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#7D6258;word-break:break-all;">
                            Se o botão não funcionar, acesse:<br>
                            <a href="' . $linkHtml . '" style="color:#4A2718;text-decoration:underline;">' . $linkHtml . '</a>
                        </p>

                        <div style="margin-top:32px;padding-top:22px;border-top:1px solid #EADBD2;">
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#7D6258;">
                                Feito com amor. Feito a dois.<br>
                                <strong style="color:#4A2718;">Doces do Casal</strong>
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>';

                $remetente = trim(
                    (string)($config['mail']['from_email'] ?? 'nao-responda@docesdocasal.kinsmanst.com')
                );

                $nomeRemetente = trim(
                    (string)($config['mail']['from_name'] ?? 'Doces do Casal')
                );

                $headers = [
                    'From: ' . $nomeRemetente . ' <' . $remetente . '>',
                    'Reply-To: ' . $remetente,
                    'MIME-Version: 1.0',
                    'Content-Type: text/html; charset=UTF-8',
                    'X-Mailer: PHP/' . phpversion()
                ];

                $enviado = @mail(
                    (string)$usuario['email'],
                    $assunto,
                    $mensagemHtml,
                    implode("\r\n", $headers)
                );

                audit(
                    'recuperacao_senha_solicitada',
                    'usuarios',
                    (int)$usuario['id'],
                    $enviado ? 'E-mail HTML enviado' : 'Falha no envio do e-mail'
                );
            }
        } catch (Throwable $e) {
        }
    }
}

$title = 'Recuperar senha';
include __DIR__ . '/../includes/header.php';
?>
<script
    src="https://challenges.cloudflare.com/turnstile/v0/api.js"
    async
    defer
></script>

<div class="form-card">
    <span class="eyebrow">MEU CASAL</span>

    <h1>Recuperar senha</h1>

    <p class="reset-intro">
        Informe o e-mail cadastrado na sua conta. Enviaremos um link para você criar uma nova senha.
    </p>

    <?php if ($error): ?>
        <div class="alert error"><?=e($error)?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?=e($success)?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
        <form method="post">
            <?=csrf_field()?>

            <label>
                E-mail
                <input
                    type="email"
                    name="email"
                    autocomplete="email"
                    required
                    value="<?=e($_POST['email'] ?? '')?>"
                >
            </label>

            <div class="turnstile-box">
                <div
                    class="cf-turnstile"
                    data-sitekey="<?=e(turnstile_site_key())?>"
                    data-theme="light"
                ></div>
            </div>

            <button class="btn primary full" type="submit">
                Enviar link de recuperação
            </button>
        </form>
    <?php endif; ?>

    <p>
        <a href="/auth/login.php">Voltar para o login</a>
    </p>
</div>

<style>
.reset-intro{
    color:var(--muted);
    line-height:1.6;
    margin-bottom:18px;
}
.turnstile-box{
    margin:20px 0;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
