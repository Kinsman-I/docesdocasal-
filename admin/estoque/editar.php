<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = require_admin();

$msg = '';
$error = '';

$id =
    (int)(
        $_GET['id']
        ?? $_POST['id']
        ?? 0
    );


if ($id <= 0) {

    header(
        'Location:/admin/estoque/'
    );

    exit;
}


$tiposPermitidos = [
    'ingrediente',
    'embalagem',
    'produto_pronto',
    'outro'
];


/* =========================================================
   CARREGA ITEM
   ========================================================= */

$st =
    db()->prepare(
        "
        SELECT *
        FROM itens_estoque
        WHERE id = ?
        LIMIT 1
        "
    );


$st->execute([
    $id
]);


$item =
    $st->fetch();


if (!$item) {

    http_response_code(404);

    exit(
        'Item de estoque não encontrado.'
    );
}


/* =========================================================
   SALVAR ALTERAÇÕES
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();


    $nome =
        trim(
            $_POST['nome']
            ?? ''
        );


    $tipo =
        $_POST['tipo']
        ?? 'ingrediente';


    $unidadeId =
        (int)(
            $_POST['unidade_id']
            ?? 0
        );


    $estoqueMinimo =
        (float)str_replace(
            ',',
            '.',
            $_POST['estoque_minimo']
            ?? '0'
        );


    $custoMedio =
        (float)str_replace(
            ',',
            '.',
            $_POST['custo_medio']
            ?? '0'
        );


    $produtoId =
        !empty(
            $_POST['produto_id']
        )
            ? (int)$_POST['produto_id']
            : null;


    /* =====================================================
       VALIDAÇÕES
       ===================================================== */

    if ($nome === '') {

        $error =
            'Informe o nome do item.';

    } elseif (
        !in_array(
            $tipo,
            $tiposPermitidos,
            true
        )
    ) {

        $error =
            'Tipo inválido.';

    } elseif ($unidadeId <= 0) {

        $error =
            'Selecione uma unidade válida.';

    } elseif ($estoqueMinimo < 0) {

        $error =
            'O estoque mínimo não pode ser negativo.';

    } elseif ($custoMedio < 0) {

        $error =
            'O custo médio não pode ser negativo.';

    }


    if (
        $tipo !== 'produto_pronto'
    ) {

        $produtoId =
            null;

    }


    if (!$error) {

        try {

            db()->prepare(
                "
                UPDATE itens_estoque

                SET
                    nome = ?,
                    tipo = ?,
                    produto_id = ?,
                    unidade_id = ?,
                    estoque_minimo = ?,
                    custo_medio = ?,
                    atualizado_em = NOW()

                WHERE id = ?
                "
            )->execute([

                $nome,

                $tipo,

                $produtoId,

                $unidadeId,

                $estoqueMinimo,

                $custoMedio,

                $id

            ]);


            audit(
                'estoque_item_editado',
                'itens_estoque',
                $id,
                $nome
            );


            $msg =
                'Item atualizado com sucesso.';


            /* Recarrega */
            $st->execute([
                $id
            ]);

            $item =
                $st->fetch();


        } catch (Throwable $e) {

            $error =
                'Não foi possível atualizar o item.';

        }

    }

}


/* =========================================================
   UNIDADES
   ========================================================= */

$units =
    db()->query(
        "
        SELECT *
        FROM unidades_medida
        ORDER BY nome
        "
    )->fetchAll();


/* =========================================================
   PRODUTOS
   ========================================================= */

$produtos =
    db()->query(
        "
        SELECT
            id,
            nome

        FROM produtos

        WHERE disponivel = 1

        ORDER BY nome
        "
    )->fetchAll();


$title =
    'Editar item';


include __DIR__
    . '/../../includes/admin_header.php';

?>


<style>

.stock-edit-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
    margin-bottom:20px;
}

.current-stock{
    padding:18px;
    border-radius:16px;
    background:var(--cream);
    margin-bottom:20px;
}

.current-stock strong{
    display:block;
    font-size:30px;
    color:var(--brown);
}

#produtoProntoWrap.hidden{
    display:none;
}

</style>


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


<div class="stock-edit-head">

    <div>

        <span class="eyebrow">
            ESTOQUE
        </span>

        <h2>
            <?=e($item['nome'])?>
        </h2>

    </div>


    <a
        class="btn outline"
        href="/admin/estoque/"
    >
        Voltar
    </a>

</div>


<div class="current-stock">

    <small>
        Estoque atual
    </small>

    <strong>

        <?=number_format(
            (float)$item['estoque_atual'],
            3,
            ',',
            '.'
        )?>

    </strong>

    <small>
        Para alterar a quantidade utilize
        <strong style="display:inline;font-size:inherit">
            Ajustar estoque
        </strong>.
    </small>

</div>


<div class="card">

    <span class="eyebrow">
        CADASTRO
    </span>

    <h3>
        Informações do item
    </h3>


    <form method="post">

        <?=csrf_field()?>


        <input
            type="hidden"
            name="id"
            value="<?=$id?>"
        >


        <div class="grid-2">


            <label>

                Nome

                <input
                    name="nome"
                    value="<?=e($item['nome'])?>"
                    required
                >

            </label>


            <label>

                Tipo

                <select
                    name="tipo"
                    id="tipoItem"
                >

                    <?php foreach (
                        $tiposPermitidos
                        as $tipo
                    ): ?>

                        <option
                            value="<?=$tipo?>"

                            <?=$item['tipo'] === $tipo
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?=e(
                                ucfirst(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $tipo
                                    )
                                )
                            )?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </label>


            <label
                id="produtoProntoWrap"

                class="
                    <?=$item['tipo'] === 'produto_pronto'
                        ? ''
                        : 'hidden'
                    ?>
                "
            >

                Produto relacionado

                <select name="produto_id">

                    <option value="">
                        Selecione
                    </option>


                    <?php foreach (
                        $produtos
                        as $p
                    ): ?>

                        <option
                            value="<?=$p['id']?>"

                            <?=(int)$item['produto_id'] === (int)$p['id']
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?=e($p['nome'])?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </label>


            <label>

                Unidade

                <select
                    name="unidade_id"
                    required
                >

                    <?php foreach (
                        $units
                        as $u
                    ): ?>

                        <option
                            value="<?=$u['id']?>"

                            <?=(int)$item['unidade_id'] === (int)$u['id']
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?=e($u['nome'])?>

                            (<?=e($u['sigla'])?>)

                        </option>

                    <?php endforeach; ?>

                </select>

            </label>


            <label>

                Estoque mínimo

                <input
                    type="number"
                    step=".001"
                    min="0"
                    name="estoque_minimo"

                    value="<?=
                        number_format(
                            (float)$item['estoque_minimo'],
                            3,
                            '.',
                            ''
                        )
                    ?>"
                >

            </label>


            <label>

                Custo médio

                <input
                    type="number"
                    step=".0001"
                    min="0"
                    name="custo_medio"

                    value="<?=
                        number_format(
                            (float)$item['custo_medio'],
                            4,
                            '.',
                            ''
                        )
                    ?>"
                >

            </label>


        </div>


        <div class="actions">

            <button
                class="btn primary"
                type="submit"
            >
                Salvar alterações
            </button>


            <a
                class="btn outline"
                href="/admin/estoque/ajustes.php?id=<?=$id?>"
            >
                Ajustar estoque
            </a>


            <a
                class="btn outline"
                href="/admin/estoque/movimentacoes.php?id=<?=$id?>"
            >
                Ver movimentações
            </a>

        </div>

    </form>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function(){

        const tipo =
            document.getElementById(
                'tipoItem'
            );

        const produto =
            document.getElementById(
                'produtoProntoWrap'
            );


        function atualizar(){

            produto.classList.toggle(
                'hidden',
                tipo.value !== 'produto_pronto'
            );

        }


        tipo.addEventListener(
            'change',
            atualizar
        );


        atualizar();

    }
);

</script>


<?php

include __DIR__
    . '/../../includes/admin_footer.php';

?>