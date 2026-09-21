<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = require_admin();

$msg = '';
$error = '';
$editando = null;


/* =========================================================
   AÇÕES
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $acao = $_POST['acao'] ?? 'salvar';
    $id = (int)($_POST['id'] ?? 0);


    /* =====================================================
       SALVAR / EDITAR
       ===================================================== */

    if ($acao === 'salvar') {

        $nome = trim($_POST['nome'] ?? '');
        $documento = trim($_POST['documento'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contato = trim($_POST['contato'] ?? '');
        $observacao = trim($_POST['observacao'] ?? '');

        if ($nome === '') {

            $error = 'Informe o nome do fornecedor.';

        } else {

            try {

                if ($id > 0) {

                    $st = db()->prepare("
                        UPDATE fornecedores
                        SET
                            nome = ?,
                            documento = ?,
                            telefone = ?,
                            email = ?,
                            contato = ?,
                            observacao = ?
                        WHERE id = ?
                    ");

                    $st->execute([
                        $nome,
                        $documento ?: null,
                        $telefone ?: null,
                        $email ?: null,
                        $contato ?: null,
                        $observacao ?: null,
                        $id
                    ]);

                    audit(
                        'fornecedor_editado',
                        'fornecedores',
                        $id,
                        $nome
                    );

                    $msg = 'Fornecedor atualizado com sucesso.';

                } else {

                    $st = db()->prepare("
                        INSERT INTO fornecedores
                        (
                            nome,
                            documento,
                            telefone,
                            email,
                            contato,
                            observacao,
                            ativo
                        )
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                    ");

                    $st->execute([
                        $nome,
                        $documento ?: null,
                        $telefone ?: null,
                        $email ?: null,
                        $contato ?: null,
                        $observacao ?: null
                    ]);

                    $novoId = (int)db()->lastInsertId();

                    audit(
                        'fornecedor_criado',
                        'fornecedores',
                        $novoId,
                        $nome
                    );

                    $msg = 'Fornecedor cadastrado com sucesso.';
                }

            } catch (Throwable $e) {

                $error = 'Não foi possível salvar o fornecedor.';
            }
        }
    }


    /* =====================================================
       ATIVAR / DESATIVAR
       ===================================================== */

    if ($acao === 'status' && $id > 0) {

        $ativo = (int)($_POST['ativo'] ?? 0);

        db()->prepare("
            UPDATE fornecedores
            SET ativo = ?
            WHERE id = ?
        ")->execute([
            $ativo,
            $id
        ]);

        $msg = $ativo
            ? 'Fornecedor ativado.'
            : 'Fornecedor desativado.';
    }
}


/* =========================================================
   EDIÇÃO
   ========================================================= */

if (!empty($_GET['editar'])) {

    $idEditar = (int)$_GET['editar'];

    $st = db()->prepare("
        SELECT *
        FROM fornecedores
        WHERE id = ?
        LIMIT 1
    ");

    $st->execute([$idEditar]);

    $editando = $st->fetch();
}


/* =========================================================
   LISTAGEM
   ========================================================= */

$fornecedores = db()->query("
    SELECT *
    FROM fornecedores
    ORDER BY ativo DESC, nome
")->fetchAll();


$title = 'Fornecedores';

include __DIR__ . '/../../includes/admin_header.php';

?>

<style>

.supplier-layout{
    display:grid;
    grid-template-columns:380px 1fr;
    gap:20px;
    align-items:start;
}

.supplier-status{
    font-size:12px;
    font-weight:800;
}

.supplier-status.ativo{
    color:var(--success);
}

.supplier-status.inativo{
    color:var(--danger);
}

.supplier-actions{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}

.supplier-actions form{
    margin:0;
}

@media(max-width:900px){
    .supplier-layout{
        grid-template-columns:1fr;
    }
}

</style>


<?php if ($msg): ?>
    <div class="alert success"><?=e($msg)?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert error"><?=e($error)?></div>
<?php endif; ?>


<div class="supplier-layout">


    <div class="card">

        <span class="eyebrow">
            CADASTRO
        </span>

        <h3>
            <?=$editando ? 'Editar fornecedor' : 'Novo fornecedor'?>
        </h3>

        <form method="post">

            <?=csrf_field()?>

            <input
                type="hidden"
                name="acao"
                value="salvar"
            >

            <input
                type="hidden"
                name="id"
                value="<?=e($editando['id'] ?? '')?>"
            >


            <label>
                Nome

                <input
                    name="nome"
                    required
                    value="<?=e($editando['nome'] ?? '')?>"
                >
            </label>


            <label>
                CPF / CNPJ

                <input
                    name="documento"
                    value="<?=e($editando['documento'] ?? '')?>"
                >
            </label>


            <label>
                Telefone

                <input
                    name="telefone"
                    value="<?=e($editando['telefone'] ?? '')?>"
                >
            </label>


            <label>
                E-mail

                <input
                    type="email"
                    name="email"
                    value="<?=e($editando['email'] ?? '')?>"
                >
            </label>


            <label>
                Pessoa de contato

                <input
                    name="contato"
                    value="<?=e($editando['contato'] ?? '')?>"
                >
            </label>


            <label>
                Observação

                <textarea name="observacao"><?=e($editando['observacao'] ?? '')?></textarea>
            </label>


            <button class="btn primary full">
                <?=$editando ? 'Salvar alterações' : 'Cadastrar fornecedor'?>
            </button>


            <?php if ($editando): ?>

                <a
                    href="/admin/compras/fornecedores.php"
                    class="btn outline full"
                    style="margin-top:8px"
                >
                    Cancelar edição
                </a>

            <?php endif; ?>

        </form>

    </div>



    <div>

        <span class="eyebrow">
            FORNECEDORES
        </span>

        <h2>
            Fornecedores cadastrados
        </h2>


        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Fornecedor</th>
                        <th>Documento</th>
                        <th>Contato</th>
                        <th>Situação</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($fornecedores as $f): ?>

                    <tr>

                        <td>
                            <strong><?=e($f['nome'])?></strong>

                            <?php if ($f['email']): ?>
                                <br>
                                <small><?=e($f['email'])?></small>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?=e($f['documento'] ?: '—')?>
                        </td>

                        <td>
                            <?=e($f['telefone'] ?: '—')?>

                            <?php if ($f['contato']): ?>
                                <br>
                                <small><?=e($f['contato'])?></small>
                            <?php endif; ?>
                        </td>

                        <td>

                            <span class="supplier-status <?=$f['ativo'] ? 'ativo' : 'inativo'?>">

                                <?=$f['ativo'] ? 'ATIVO' : 'INATIVO'?>

                            </span>

                        </td>

                        <td>

                            <div class="supplier-actions">

                                <a
                                    class="btn outline"
                                    href="?editar=<?=$f['id']?>"
                                >
                                    Editar
                                </a>

                                <form method="post">

                                    <?=csrf_field()?>

                                    <input
                                        type="hidden"
                                        name="acao"
                                        value="status"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?=$f['id']?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="ativo"
                                        value="<?=$f['ativo'] ? 0 : 1?>"
                                    >

                                    <button class="btn outline">
                                        <?=$f['ativo'] ? 'Desativar' : 'Ativar'?>
                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>


                <?php if (!$fornecedores): ?>

                    <tr>
                        <td colspan="5" style="text-align:center;padding:30px">
                            Nenhum fornecedor cadastrado.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<?php

include __DIR__ . '/../../includes/admin_footer.php';

?>