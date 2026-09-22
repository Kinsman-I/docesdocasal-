<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    header('Location:/cliente/');
    exit;
}

$error = '';
$success = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$registro = null;

function buscar_token_recuperacao(string $token): ?array
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return null;
    }

    $hash = hash('sha256', $token);

    $st = db()->prepare("
        SELECT
            r.id,
            r.usuario_id,
            r.expira_em,
            r.usado_em,
            u.nome,
            u.email
        FROM recuperacao_senha r
        JOIN usuarios u
          ON u.id = r.usuario_id
        WHERE r.token_hash = ?
          AND r.usado_em IS NULL
          AND r.expira_em >= NOW()
          AND u.ativo = 1
        LIMIT 1
    ");
    $st->execute([$hash]);

    $row = $st->fetch();

    return $row ?: null;
}

try {
    $registro = buscar_token_recuperacao($token);
} catch (Throwable $e) {
    $registro = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $senha = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar_senha'] ?? '';

    if (!$registro) {
        $error = 'Este link é inválido, expirou ou já foi utilizado.';
    } elseif (!senha_forte($senha)) {
        $error = senha_forte_mensagem();
    } elseif ($senha !== $confirmar) {
        $error = 'As senhas informadas não são iguais.';
    } else {
        try {
            db()->beginTransaction();

            $hashToken = hash('sha256', $token);

            $lock = db()->prepare("
                SELECT id, usuario_id
                FROM recuperacao_senha
                WHERE token_hash = ?
                  AND usado_em IS NULL
                  AND expira_em >= NOW()
                FOR UPDATE
            ");
            $lock->execute([$hashToken]);
            $valido = $lock->fetch();

            if (!$valido) {
                throw new RuntimeException('Token inválido ou expirado.');
            }

            db()->prepare("
                UPDATE usuarios
                SET senha_hash = ?
                WHERE id = ?
            ")->execute([
                password_hash($senha, PASSWORD_DEFAULT),
                (int)$valido['usuario_id']
            ]);

            db()->prepare("
                UPDATE recuperacao_senha
                SET usado_em = NOW()
                WHERE id = ?
            ")->execute([
                (int)$valido['id']
            ]);

            /*
             * Invalida qualquer outro link pendente do mesmo usuário.
             */
            db()->prepare("
                UPDATE recuperacao_senha
                SET usado_em = COALESCE(usado_em, NOW())
                WHERE usuario_id = ?
            ")->execute([
                (int)$valido['usuario_id']
            ]);

            db()->commit();

            audit(
                'senha_redefinida',
                'usuarios',
                (int)$valido['usuario_id'],
                'Senha redefinida por link de recuperação'
            );

            $success = 'Senha alterada com sucesso. Agora você já pode entrar com a nova senha.';
            $registro = null;
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            $error = 'Não foi possível alterar a senha. Solicite um novo link e tente novamente.';
        }
    }
}

$title = 'Criar nova senha';
include __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
    <span class="eyebrow">MEU CASAL</span>

    <h1>Nova senha</h1>

    <?php if ($error): ?>
        <div class="alert error"><?=e($error)?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?=e($success)?></div>

        <p>
            <a class="btn primary full" href="/auth/login.php">
                Entrar na minha conta
            </a>
        </p>

    <?php elseif (!$registro): ?>

        <div class="alert error">
            Este link é inválido, expirou ou já foi utilizado.
        </div>

        <p>
            <a class="btn primary full" href="/auth/forgot.php">
                Solicitar novo link
            </a>
        </p>

    <?php else: ?>

        <p class="reset-intro">
            Crie uma nova senha para sua conta.
        </p>

        <form method="post">
            <?=csrf_field()?>

            <input
                type="hidden"
                name="token"
                value="<?=e($token)?>"
            >

            <label>
                Nova senha

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
                    ><span class="password-icon password-icon-closed" aria-hidden="true">
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
                </span></button>
                </div>
            </label>

            <div class="password-rules">
                <strong>Sua senha precisa ter:</strong>
                <div class="password-rule" data-password-rule="len">Pelo menos 8 caracteres</div>
                <div class="password-rule" data-password-rule="upper">Uma letra maiúscula</div>
                <div class="password-rule" data-password-rule="lower">Uma letra minúscula</div>
                <div class="password-rule" data-password-rule="num">Um número</div>
                <div class="password-rule" data-password-rule="sym">Um caractere especial</div>
            </div>

            <label>
                Confirmar nova senha

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
                    ><span class="password-icon password-icon-closed" aria-hidden="true">
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
                </span></button>
                </div>
            </label>

            <button class="btn primary full" type="submit">
                Alterar senha
            </button>
        </form>

    <?php endif; ?>
</div>

<style>
.reset-intro{color:var(--muted);line-height:1.6}
.password-field-wrap{position:relative}
.password-field-wrap input{padding-right:52px}
.password-toggle{
    position:absolute;right:10px;top:50%;transform:translateY(-50%);
    width:38px;height:38px;border:0;border-radius:10px;background:transparent;
    color:var(--muted);cursor:pointer;display:grid;place-items:center;font-size:18px
}
.password-toggle:hover{background:var(--cream);color:var(--brown)}
.password-rules{
    margin-top:10px;padding:14px 15px;border:1px solid var(--line);
    border-radius:14px;background:#fffaf6
}
.password-rules strong{display:block;margin-bottom:8px;color:var(--brown)}
.password-rule{
    display:flex;gap:8px;align-items:center;margin:5px 0;
    color:var(--muted);font-size:13px
}
.password-rule::before{content:"○";color:#b6988c;font-weight:800}
.password-rule.ok{color:var(--success)}
.password-rule.ok::before{content:"✓";color:var(--success)}
</style>

<script>
(function(){
    const senha = document.getElementById('senha');

    if(senha){
        const tests = {
            len:value => value.length >= 8,
            upper:value => /[A-Z]/.test(value),
            lower:value => /[a-z]/.test(value),
            num:value => /[0-9]/.test(value),
            sym:value => /[^A-Za-z0-9]/.test(value)
        };

        const update = () => {
            Object.entries(tests).forEach(([key,test]) => {
                const el = document.querySelector('[data-password-rule="'+key+'"]');
                if(el) el.classList.toggle('ok', test(senha.value));
            });
        };

        senha.addEventListener('input', update);
        update();
    }

    document.querySelectorAll('[data-password-toggle]').forEach(function(button){
        button.addEventListener('click',function(){
            const input = document.getElementById(button.getAttribute('data-password-toggle'));
            if(!input) return;

            const mostrando = input.type === 'text';
            input.type = mostrando ? 'password' : 'text';
            button.setAttribute('aria-label', mostrando ? 'Mostrar senha' : 'Ocultar senha');
            button.setAttribute('aria-pressed', mostrando ? 'false' : 'true');
        });
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
