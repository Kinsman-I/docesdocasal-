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

    $nome =
        trim(
            $_POST['nome']
            ?? ''
        );

    $telefone =
        preg_replace(
            '/\D+/',
            '',
            $_POST['telefone']
            ?? ''
        );

    $email =
        trim(
            strtolower(
                $_POST['email']
                ?? ''
            )
        );

    $senha =
        $_POST['senha']
        ?? '';

    $confirmarSenha =
        $_POST['confirmar_senha']
        ?? '';

    $turnstileToken =
        $_POST['cf-turnstile-response']
        ?? '';


    /* =====================================================
       VALIDAÇÃO
       ===================================================== */

    if (
        !validar_turnstile(
            $turnstileToken
        )
    ) {

        $error =
            turnstile_configurado()
                ? 'Não foi possível validar a verificação de segurança. Tente novamente.'
                : 'A proteção anti-bot ainda não foi configurada. Informe as chaves do Cloudflare Turnstile no app/config.php.';

    } elseif (
        strlen($nome) < 2
        || strlen($telefone) < 10
    ) {

        $error =
            'Preencha nome e telefone corretamente.';

    } elseif (
        $email !== ''
        && !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            'Informe um e-mail válido.';

    } elseif (
        !senha_forte(
            $senha
        )
    ) {

        $error =
            senha_forte_mensagem();

    } elseif (
        $senha !==
        $confirmarSenha
    ) {

        $error =
            'As senhas informadas não são iguais.';

    } else {

        try {

            $st =
                db()->prepare(
                    "INSERT INTO usuarios
                    (
                        nome,
                        telefone,
                        email,
                        senha_hash,
                        tipo
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        'cliente'
                    )"
                );

            $st->execute([
                $nome,
                $telefone,
                $email ?: null,
                password_hash(
                    $senha,
                    PASSWORD_DEFAULT
                )
            ]);

            $id =
                (int)db()->lastInsertId();

            $u =
                db()->prepare(
                    "SELECT *
                     FROM usuarios
                     WHERE id = ?"
                );

            $u->execute([
                $id
            ]);

            login_user(
                $u->fetch()
            );

            audit(
                'cadastro',
                'usuarios',
                $id
            );

            $next = safe_next_url(
                $_GET['next'] ?? '',
                '/cliente/'
            );

            header(
                'Location:' . $next
            );

            exit;

        } catch (PDOException $e) {

            /*
             * Mantém mensagem genérica.
             */
            $error =
                'Não foi possível criar a conta. Verifique os dados informados ou tente novamente.';
        }
    }
}


$title = 'Criar conta';

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
        Criar conta
    </h1>


    <?php if ($error): ?>

        <div class="alert error">
            <?=e($error)?>
        </div>

    <?php endif; ?>


    <form method="post">

        <?=csrf_field()?>


        <label>

            Nome

            <input
                name="nome"
                autocomplete="name"
                required
                value="<?=e($_POST['nome'] ?? '')?>"
            >

        </label>


        <label>

            WhatsApp

            <input
                name="telefone"
                inputmode="tel"
                autocomplete="tel"
                required
                value="<?=e($_POST['telefone'] ?? '')?>"
            >

        </label>


        <label>

            E-mail

            <input
                type="email"
                name="email"
                autocomplete="email"
                value="<?=e($_POST['email'] ?? '')?>"
            >

        </label>


        <label>

            Senha

            <div class="password-field-wrap">

                <input
                    id="senha"
                    type="password"
                    name="senha"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <button
                    type="button"
                    class="password-toggle"
                    data-password-toggle="senha"
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


        <div class="password-rules">

            <strong>
                Sua senha precisa ter:
            </strong>

            <div
                class="password-rule"
                data-password-rule="len"
            >
                Pelo menos 8 caracteres
            </div>

            <div
                class="password-rule"
                data-password-rule="upper"
            >
                Uma letra maiúscula
            </div>

            <div
                class="password-rule"
                data-password-rule="lower"
            >
                Uma letra minúscula
            </div>

            <div
                class="password-rule"
                data-password-rule="num"
            >
                Um número
            </div>

            <div
                class="password-rule"
                data-password-rule="sym"
            >
                Um caractere especial
            </div>

        </div>


        <label>

            Confirmar senha

            <div class="password-field-wrap">

                <input
                    id="confirmar-senha"
                    type="password"
                    name="confirmar_senha"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <button
                    type="button"
                    class="password-toggle"
                    data-password-toggle="confirmar-senha"
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
            Criar minha conta
        </button>

    </form>


    <p>

        <a href="/auth/login.php<?=isset($_GET['next']) ? '?next='.urlencode(safe_next_url($_GET['next'], '/cliente/')) : ''?>">
            Já tenho conta
        </a>

    </p>

</div>


<style>
.password-rules{
    margin-top:10px;
    padding:14px 15px;
    border:1px solid var(--line);
    border-radius:14px;
    background:#fffaf6;
}
.password-rules strong{
    display:block;
    margin-bottom:8px;
    color:var(--brown);
}
.password-rule{
    display:flex;
    gap:8px;
    align-items:center;
    margin:5px 0;
    color:var(--muted);
    font-size:13px;
}
.password-rule::before{
    content:"○";
    color:#b6988c;
    font-weight:800;
}
.password-rule.ok{
    color:var(--success);
}
.password-rule.ok::before{
    content:"✓";
    color:var(--success);
}
.turnstile-box{
    margin:20px 0;
}
</style>

<script>
function initPasswordMeter(inputId){
    const input = document.getElementById(inputId);
    if(!input) return;

    const tests = {
        len:  value => value.length >= 8,
        upper:value => /[A-Z]/.test(value),
        lower:value => /[a-z]/.test(value),
        num:  value => /[0-9]/.test(value),
        sym:  value => /[^A-Za-z0-9]/.test(value)
    };

    const update = () => {
        const value = input.value;

        Object.entries(tests).forEach(([key,test]) => {
            const el = document.querySelector('[data-password-rule="'+key+'"]');
            if(el){
                el.classList.toggle('ok', test(value));
            }
        });
    };

    input.addEventListener('input', update);
    update();
}

document.addEventListener('DOMContentLoaded', function(){
    initPasswordMeter('senha');
});
</script>


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
