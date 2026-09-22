<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = require_admin();

$msg = '';
$error = '';
$editando = null;


/* =========================================================
   POST
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();


    $acao =
        $_POST['acao']
        ?? 'salvar';


    $id =
        (int)(
            $_POST['id']
            ?? 0
        );


    /* =====================================================
       SALVAR
       ===================================================== */

    if ($acao === 'salvar') {

        $nome =
            trim(
                $_POST['nome']
                ?? ''
            );


        $natureza =
            $_POST['natureza']
            ?? 'saida';


        if ($nome === '') {

            $error =
                'Informe o nome da categoria.';

        } elseif (
            !in_array(
                $natureza,
                [
                    'entrada',
                    'saida'
                ],
                true
            )
        ) {

            $error =
                'Natureza inválida.';

        }


        if (!$error) {

            try {

                if ($id > 0) {

                    db()->prepare(
                        "
                        UPDATE categorias_financeiras

                        SET
                            nome = ?,
                            natureza = ?

                        WHERE id = ?
                        "
                    )->execute([
                        $nome,
                        $natureza,
                        $id
                    ]);


                    audit(
                        'categoria_financeira_editada',
                        'categorias_financeiras',
                        $id,
                        $nome
                    );


                    $msg =
                        'Categoria atualizada.';


                } else {

                    $st =
                        db()->prepare(
                            "
                            INSERT INTO categorias_financeiras
                            (
                                nome,
                                natureza,
                                ativo
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                1
                            )
                            "
                        );


                    $st->execute([
                        $nome,
                        $natureza
                    ]);


                    $novoId =
                        (int)db()->lastInsertId();


                    audit(
                        'categoria_financeira_criada',
                        'categorias_financeiras',
                        $novoId,
                        $nome
                    );


                    $msg =
                        'Categoria criada.';

                }


            } catch (Throwable $e) {

                $error =
                    'Não foi possível salvar a categoria.';

            }

        }

    }


    /* =====================================================
       STATUS
       ===================================================== */

    if (
        $acao === 'status'
        &&
        $id > 0
    ) {

        $ativo =
            (int)(
                $_POST['ativo']
                ?? 0
            );


        db()->prepare(
            "
            UPDATE categorias_financeiras
            SET ativo = ?
            WHERE id = ?
            "
        )->execute([
            $ativo,
            $id
        ]);


        $msg =
            $ativo
                ? 'Categoria ativada.'
                : 'Categoria desativada.';

    }

}


/* =========================================================
   EDIÇÃO
   ========================================================= */

if (!empty($_GET['editar'])) {

    $idEditar =
        (int)$_GET['editar'];


    $st =
        db()->prepare(
            "
            SELECT *
            FROM categorias_financeiras
            WHERE id = ?
            LIMIT 1
            "
        );


    $st->execute([
        $idEditar
    ]);


    $editando =
        $st->fetch();

}


/* =========================================================
   LISTAGEM
   ========================================================= */

$rows =
    db()->query(
        "
        SELECT *
        FROM categorias_financeiras
        ORDER BY
            natureza,
            ativo DESC,
            nome
        "
    )->fetchAll();


$title =
    'Categorias Financeiras';


include __DIR__
    . '/../../includes/admin_header.php';

?>


<style>

.finance-tabs{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-bottom:24px;
}

.finance-tab{
    padding:9px 14px;
    border:1px solid var(--line);
    border-radius:999px;
    background:#fff;
    font-weight:700;
    font-size:13px;
}

.finance-tab.active{
    background:var(--brown);
    color:#fff;
}


.category-layout{
    display:grid;
    grid-template-columns:360px 1fr;
    gap:20px;
    align-items:start;
}


.category-entry{
    color:var(--success);
    font-weight:800;
}


.category-exit{
    color:var(--danger);
    font-weight:800;
}


.category-actions{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}


.category-actions form{
    margin:0;
}


@media(max-width:850px){

    .category-layout{
        grid-template-columns:1fr;
    }

}

</style>


<div class="finance-tabs">

    <a
        class="finance-tab"
        href="/admin/financeiro/"
    >
        Dashboard
    </a>

    <a
        class="finance-tab"
        href="/admin/financeiro/lancamentos.php"
    >
        Lançamentos
    </a>

    <a
        class="finance-tab"
        href="/admin/financeiro/extrato.php"
    >
        Extrato bancário
    </a>

    <a
        class="finance-tab"
        href="/admin/financeiro/conciliacao.php"
    >
        Conciliação
    </a>

    <a
        class="finance-tab active"
        href="/admin/financeiro/categorias.php"
    >
        Categorias
    </a>

</div>


<?php if ($msg): ?>

    <div class="alert success">
        <?=e($msg)?>
    </div>

<?php endif; ?>


<?php if ($error): ?>

    <div class="alert error">
        <?=e($error)?>
    </div>

<?php endif; ?>


<div class="category-layout">


    <aside>


        <div class="card">


            <span class="eyebrow">
                CATEGORIA
            </span>


            <h3>

                <?=$editando
                    ? 'Editar categoria'
                    : 'Nova categoria'
                ?>

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
                    value="<?=e(
                        $editando['id']
                        ?? ''
                    )?>"
                >


                <label>

                    Natureza

                    <select name="natureza">

                        <option
                            value="entrada"

                            <?=(
                                $editando
                                &&
                                $editando['natureza'] === 'entrada'
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Receita
                        </option>


                        <option
                            value="saida"

                            <?=(
                                !$editando
                                ||
                                $editando['natureza'] === 'saida'
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Despesa
                        </option>

                    </select>

                </label>


                <label>

                    Nome

                    <input
                        name="nome"
                        required
                        value="<?=e(
                            $editando['nome']
                            ?? ''
                        )?>"
                    >

                </label>


                <button
                    class="btn primary full"
                    type="submit"
                >

                    <?=$editando
                        ? 'Salvar alterações'
                        : 'Criar categoria'
                    ?>

                </button>


                <?php if ($editando): ?>

                    <a
                        class="btn outline full"
                        style="margin-top:8px"
                        href="/admin/financeiro/categorias.php"
                    >
                        Cancelar edição
                    </a>

                <?php endif; ?>


            </form>


        </div>


    </aside>



    <section>


        <span class="eyebrow">
            ORGANIZAÇÃO
        </span>


        <h2>
            Categorias financeiras
        </h2>


        <div class="table-wrap">


            <table>


                <thead>

                    <tr>

                        <th>
                            Categoria
                        </th>

                        <th>
                            Natureza
                        </th>

                        <th>
                            Situação
                        </th>

                        <th>
                            Ações
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $rows
                    as $r
                ): ?>


                    <tr>


                        <td>

                            <strong>
                                <?=e($r['nome'])?>
                            </strong>

                        </td>


                        <td>

                            <?php if (
                                $r['natureza'] === 'entrada'
                            ): ?>

                                <span class="category-entry">
                                    RECEITA
                                </span>

                            <?php else: ?>

                                <span class="category-exit">
                                    DESPESA
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <span class="badge">

                                <?=$r['ativo']
                                    ? 'ATIVA'
                                    : 'INATIVA'
                                ?>

                            </span>

                        </td>


                        <td>


                            <div class="category-actions">


                                <a
                                    class="btn outline"
                                    href="?editar=<?=$r['id']?>"
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
                                        value="<?=$r['id']?>"
                                    >


                                    <input
                                        type="hidden"
                                        name="ativo"
                                        value="<?=$r['ativo'] ? 0 : 1?>"
                                    >


                                    <button
                                        class="btn outline"
                                        type="submit"
                                    >

                                        <?=$r['ativo']
                                            ? 'Desativar'
                                            : 'Ativar'
                                        ?>

                                    </button>

                                </form>


                            </div>


                        </td>


                    </tr>


                <?php endforeach; ?>


                <?php if (!$rows): ?>


                    <tr>

                        <td
                            colspan="4"
                            style="text-align:center;padding:30px"
                        >

                            Nenhuma categoria cadastrada.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>


            </table>


        </div>


    </section>


</div>


<?php

include __DIR__
    . '/../../includes/admin_footer.php';

?>