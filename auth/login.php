<?php

require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    $next = safe_next_url($_GET['next'] ?? '', '/cliente/');
    header('Location:' . $next);
    exit;
}

$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();


    /* =====================================================
       CLOUDFLARE TURNSTILE
       ===================================================== */

    $turnstileToken =
        $_POST['cf-turnstile-response']
        ?? '';

    if (
        !validar_turnstile(
            $turnstileToken
        )
    ) {

        $error =
            turnstile_configurado()
                ? 'Não foi possível validar a verificação de segurança. Tente novamente.'
                : 'A proteção anti-bot ainda não foi configurada. Informe as chaves do Cloudflare Turnstile no app/config.php.';

    } else {

        /* =================================================
           DADOS DO LOGIN
           ================================================= */

        $login =
            trim(
                strtolower(
                    $_POST['login']
                    ?? ''
                )
            );

        $phone =
            preg_replace(
                '/\D+/',
                '',
                $login
            );

        $pass =
            $_POST['senha']
            ?? '';


        /* =================================================
           BUSCA USUÁRIO
           ================================================= */

        $st =
            db()->prepare(
                "SELECT *
                 FROM usuarios
                 WHERE ativo = 1
                   AND (
                        telefone = ?
                        OR LOWER(email) = ?
                   )
                 LIMIT 1"
            );

        $st->execute([
            $phone,
            $login
        ]);

        $u =
            $st->fetch();


        /* =================================================
           VALIDA SENHA
           ================================================= */

        if (
            $u
            && password_verify(
                $pass,
                $u['senha_hash']
            )
        ) {

            db()->prepare(
                "UPDATE usuarios
                 SET ultimo_login_em = NOW()
                 WHERE id = ?"
            )->execute([
                $u['id']
            ]);

            login_user(
                $u
            );

            audit(
                'login',
                'usuarios',
                (int)$u['id']
            );

            $fallback =
                $u['tipo'] === 'admin'
                    ? '/admin/'
                    : '/cliente/';

            $next = safe_next_url(
                $_GET['next'] ?? '',
                $fallback
            );

            header(
                'Location:' . $next
            );

            exit;
        }

        /*
         * Mensagem genérica para não revelar se
         * determinado telefone/e-mail existe.
         */
        $error =
            'Usuário ou senha inválidos.';
    }
}


$title = 'Entrar';

include __DIR__ . '/../includes/header.php';

?>

<script
    src="https://challenges.cloudflare.com/turnstile/v0/api.js"
    async
    defer
></script>

<div class="form-card">

    <span class="eyebrow">
        MEU CASAL
    </span>

    <h1>
        Entrar
    </h1>

    <?php if ($error): ?>

        <div class="alert error">
            <?=e($error)?>
        </div>

    <?php endif; ?>


    <form method="post">

        <?=csrf_field()?>


        <label>

            Telefone ou e-mail

            <input
                name="login"
                autocomplete="username"
                required
            >

        </label>


        <label>

            Senha

            <div class="password-field-wrap">

                <input
                    id="login-senha"
                    type="password"
                    name="senha"
                    autocomplete="current-password"
                    required
                >

                <button
                    type="button"
                    class="password-toggle"
                    data-password-toggle="login-senha"
                    aria-label="Mostrar senha"
                    aria-pressed="false"
                    title="Mostrar ou ocultar senha"
                >
                    <span class="password-icon password-icon-closed" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3l18 18"></path>
                        <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
                        <path d="M9.9 4.24A10.9 10.9 0 0 1 12 4c5.5 0 9.5 5 9.5 5a15.4 15.4 0 0 1-2.1 2.7"></path>
                        <path d="M6.7 6.7C4.4 8.2 2.5 11 2.5 11s4 5 9.5 5a10.9 10.9 0 0 0 4.1-.8"></path>
                    </svg>
                </span>
                <span class="password-icon password-icon-open" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"></path>
                        <circle cx="12" cy="12" r="2.8"></circle>
                    </svg>
                </span>
                </button>

            </div>

        </label>


        <div class="turnstile-box">

            <div
                class="cf-turnstile"
                data-sitekey="<?=e(turnstile_site_key())?>"
                data-theme="light"
            ></div>

        </div>


        <button
            class="btn primary full"
            type="submit"
        >
            Entrar
        </button>

    </form>


    <p>

        <a href="/auth/forgot.php">
            Esqueci minha senha
        </a>

        ·

        <a href="/auth/register.php<?=isset($_GET['next']) ? '?next='.urlencode(safe_next_url($_GET['next'], '/cliente/')) : ''?>">
            Criar conta
        </a>

    </p>

</div>


<style>
.turnstile-box{
    margin:20px 0;
}
</style>


<style>
.password-field-wrap{
    position:relative;
}

.password-field-wrap input{
    padding-right:52px;
}

.password-toggle{
    position:absolute;
    right:10px;
    top:50%;
    transform:translateY(-50%);
    width:38px;
    height:38px;
    border:0;
    border-radius:10px;
    background:transparent;
    color:var(--muted);
    cursor:pointer;
    display:grid;
    place-items:center;
    font-size:18px;
    line-height:1;
}

.password-toggle:hover{
    background:var(--cream);
    color:var(--brown);
}

.password-toggle:focus{
    outline:2px solid var(--rose);
    outline-offset:2px;
}
</style>

<script>
function initPasswordVisibility(){
    document.querySelectorAll('[data-password-toggle]').forEach(function(button){
        button.addEventListener('click', function(){
            const targetId = button.getAttribute('data-password-toggle');
            const input = document.getElementById(targetId);

            if(!input) return;

            const showing = input.type === 'text';

            input.type = showing ? 'password' : 'text';
            button.setAttribute(
                'aria-label',
                showing ? 'Mostrar senha' : 'Ocultar senha'
            );
            button.setAttribute(
                'aria-pressed',
                showing ? 'false' : 'true'
            );
        });
    });
}

document.addEventListener('DOMContentLoaded', initPasswordVisibility);
</script>


<?php
include __DIR__ . '/../includes/footer.php';
?>
