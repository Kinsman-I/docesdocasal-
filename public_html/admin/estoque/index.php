<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = require_admin();

$msg = '';
$error = '';

$tiposPermitidos = [
    'ingrediente',
    'embalagem',
    'produto_pronto',
    'outro'
];


/* =========================================================
   AÇÃO
   ========================================================= */

$acao =
    $_POST['acao']
    ?? 'cadastrar';


/* =========================================================
   POST
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();


    /* =====================================================
       DESATIVAR ITEM
       ===================================================== */

    if ($acao === 'desativar') {

        $id =
            (int)(
                $_POST['id']
                ?? 0
            );

        if ($id <= 0) {

            $error =
                'Item inválido.';

        } else {

            try {

                db()->prepare(
                    "
                    UPDATE itens_estoque

                    SET
                        ativo = 0,
                        atualizado_em = NOW()

                    WHERE id = ?
                    "
                )->execute([
                    $id
                ]);


                audit(
                    'estoque_item_desativado',
                    'itens_estoque',
                    $id
                );


                $msg =
                    'Item desativado com sucesso.';


            } catch (Throwable $e) {

                $error =
                    'Não foi possível desativar o item.';

            }

        }

    }



    /* =====================================================
       EXCLUIR ITEM
       Somente se nunca foi utilizado.
       ===================================================== */

    elseif ($acao === 'excluir') {

        $id =
            (int)(
                $_POST['id']
                ?? 0
            );


        if ($id <= 0) {

            $error =
                'Item inválido.';

        } else {

            try {

                /*
                 * Movimentações
                 */
                $st =
                    db()->prepare(
                        "
                        SELECT COUNT(*)
                        FROM estoque_movimentacoes
                        WHERE item_estoque_id = ?
                        "
                    );

                $st->execute([
                    $id
                ]);

                $movimentacoes =
                    (int)$st->fetchColumn();


                /*
                 * Fichas técnicas
                 */
                $st =
                    db()->prepare(
                        "
                        SELECT COUNT(*)
                        FROM receitas_produto
                        WHERE item_estoque_id = ?
                        "
                    );

                $st->execute([
                    $id
                ]);

                $receitas =
                    (int)$st->fetchColumn();


                /*
                 * Compras
                 */
                $st =
                    db()->prepare(
                        "
                        SELECT COUNT(*)
                        FROM compra_itens
                        WHERE item_estoque_id = ?
                        "
                    );

                $st->execute([
                    $id
                ]);

                $compras =
                    (int)$st->fetchColumn();


                /*
                 * Produções
                 */
                $st =
                    db()->prepare(
                        "
                        SELECT COUNT(*)
                        FROM producao_consumos
                        WHERE item_estoque_id = ?
                        "
                    );

                $st->execute([
                    $id
                ]);

                $producoes =
                    (int)$st->fetchColumn();


                if (
                    $movimentacoes > 0
                    ||
                    $receitas > 0
                    ||
                    $compras > 0
                    ||
                    $producoes > 0
                ) {

                    $error =
                        'Este item já possui histórico e não pode ser excluído. Use a opção Desativar.';

                } else {

                    db()->prepare(
                        "
                        DELETE FROM itens_estoque
                        WHERE id = ?
                        "
                    )->execute([
                        $id
                    ]);


                    audit(
                        'estoque_item_excluido',
                        'itens_estoque',
                        $id
                    );


                    $msg =
                        'Item excluído com sucesso.';

                }


            } catch (Throwable $e) {

                $error =
                    'Não foi possível excluir o item.';

            }

        }

    }



    /* =====================================================
       CADASTRAR
       ===================================================== */

    elseif ($acao === 'cadastrar') {

        $nome =
            trim(
                $_POST['nome']
                ?? ''
            );


        $tipo =
            $_POST['tipo']
            ?? 'ingrediente';


        $un =
            (int)(
                $_POST['unidade_id']
                ?? 0
            );


        $q =
            (float)str_replace(
                ',',
                '.',
                $_POST['quantidade']
                ?? '0'
            );


        $min =
            (float)str_replace(
                ',',
                '.',
                $_POST['minimo']
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
            !empty($_POST['produto_id'])
                ? (int)$_POST['produto_id']
                : null;



        /* =================================================
           VALIDAÇÃO
           ================================================= */

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

        } elseif ($un <= 0) {

            $error =
                'Selecione uma unidade válida.';

        } elseif ($q < 0) {

            $error =
                'O estoque inicial não pode ser negativo.';

        } elseif ($min < 0) {

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


        if (
            !$error
            && $tipo === 'produto_pronto'
            && !$produtoId
        ) {

            $error =
                'Selecione o produto relacionado para controlar o estoque.';

        }


        if (
            !$error
            && $tipo === 'produto_pronto'
            && $produtoId
        ) {

            $duplicado =
                db()->prepare(
                    "
                    SELECT id
                    FROM itens_estoque
                    WHERE tipo = 'produto_pronto'
                      AND produto_id = ?
                      AND ativo = 1
                    LIMIT 1
                    "
                );

            $duplicado->execute([
                $produtoId
            ]);

            if ($duplicado->fetchColumn()) {

                $error =
                    'Este produto já possui um item de estoque ativo.';

            }

        }



        /* =================================================
           GRAVAÇÃO
           ================================================= */

        if (!$error) {

            try {

                db()->beginTransaction();


                $st =
                    db()->prepare(
                        "
                        INSERT INTO itens_estoque
                        (
                            nome,
                            tipo,
                            produto_id,
                            unidade_id,
                            estoque_atual,
                            estoque_minimo,
                            custo_medio,
                            ativo
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            1
                        )
                        "
                    );


                $st->execute([
                    $nome,
                    $tipo,
                    $produtoId,
                    $un,
                    $q,
                    $min,
                    $custoMedio
                ]);


                $id =
                    (int)db()->lastInsertId();


                if (
                    $tipo === 'produto_pronto'
                    && $produtoId
                ) {

                    db()->prepare(
                        "
                        UPDATE produtos
                        SET controla_estoque = 1
                        WHERE id = ?
                        "
                    )->execute([
                        $produtoId
                    ]);

                }



                /* =============================================
                   ESTOQUE INICIAL
                   ============================================= */

                if ($q > 0) {

                    db()->prepare(
                        "
                        INSERT INTO estoque_movimentacoes
                        (
                            item_estoque_id,
                            tipo,
                            quantidade,
                            custo_unitario,
                            referencia_tipo,
                            observacao,
                            usuario_id
                        )
                        VALUES
                        (
                            ?,
                            'entrada',
                            ?,
                            ?,
                            'ajuste',
                            'Estoque inicial',
                            ?
                        )
                        "
                    )->execute([
                        $id,
                        $q,
                        $custoMedio,
                        $admin['id']
                    ]);

                }


                db()->commit();


                audit(
                    'estoque_item_criado',
                    'itens_estoque',
                    $id,
                    $nome
                );


                $msg =
                    'Item criado com sucesso.';


            } catch (Throwable $e) {

                if (
                    db()->inTransaction()
                ) {

                    db()->rollBack();

                }


                $error =
                    'Não foi possível cadastrar o item.';

            }

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


/* =========================================================
   FILTROS
   ========================================================= */

$filtroTipo =
    $_GET['tipo']
    ?? '';


$where =
    "
    WHERE i.ativo = 1
    ";


$params = [];


if (
    in_array(
        $filtroTipo,
        $tiposPermitidos,
        true
    )
) {

    $where .=
        "
        AND i.tipo = ?
        ";

    $params[] =
        $filtroTipo;

}


/* =========================================================
   ESTOQUE
   ========================================================= */

$sql =
    "
    SELECT
        i.*,
        u.sigla,
        p.nome AS produto_nome

    FROM itens_estoque i

    JOIN unidades_medida u
        ON u.id = i.unidade_id

    LEFT JOIN produtos p
        ON p.id = i.produto_id

    $where

    ORDER BY
        i.tipo,
        i.nome
    ";


$st =
    db()->prepare(
        $sql
    );


$st->execute(
    $params
);


$rows =
    $st->fetchAll();


/* =========================================================
   INDICADORES
   ========================================================= */

$totalItens =
    count(
        $rows
    );


$estoqueBaixo =
    0;


$valorEstoque =
    0;


foreach ($rows as $r) {

    if (
        (float)$r['estoque_atual']
        <=
        (float)$r['estoque_minimo']
    ) {

        $estoqueBaixo++;

    }


    $valorEstoque +=
        (float)$r['estoque_atual']
        *
        (float)$r['custo_medio'];

}


/* =========================================================
   PÁGINA
   ========================================================= */

$title =
    'Estoque';


include __DIR__
    . '/../../includes/admin_header.php';

?>


<style>

.stock-metrics{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
    margin-bottom:24px;
}

.stock-card{
    background:#fff;
    border:1px solid var(--line);
    border-radius:18px;
    padding:20px;
}

.stock-card small{
    display:block;
    color:var(--muted);
    margin-bottom:6px;
}

.stock-card strong{
    display:block;
    font-size:26px;
    color:var(--brown);
}


/* =========================================================
   FILTROS
   ========================================================= */

.stock-filters{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-bottom:18px;
}

.stock-filter{
    padding:8px 12px;
    border:1px solid var(--line);
    border-radius:999px;
    background:#fff;
    font-size:13px;
}

.stock-filter.active{
    background:var(--brown);
    color:#fff;
}


/* =========================================================
   SITUAÇÃO
   ========================================================= */

.stock-low{
    color:var(--danger);
    font-weight:800;
}

.stock-ok{
    color:var(--success);
    font-weight:800;
}

.stock-type{
    text-transform:capitalize;
}

.stock-value{
    font-weight:700;
}


/* =========================================================
   AÇÕES
   ========================================================= */

.stock-actions{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}

.stock-action{
    display:inline-block;

    padding:7px 9px;

    border:1px solid var(--line);

    border-radius:9px;

    background:#fff;

    font-size:12px;

    font-weight:700;

    cursor:pointer;
}

.stock-action:hover{
    background:var(--cream);
}

.stock-action.danger{
    color:var(--danger);
}


/* =========================================================
   PRODUTO PRONTO
   ========================================================= */

#produtoProntoWrap.hidden{
    display:none;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media(max-width:900px){

    .stock-metrics{
        grid-template-columns:repeat(2,1fr);
    }

}

@media(max-width:600px){

    .stock-metrics{
        grid-template-columns:1fr;
    }

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



<!-- =========================================================
     INDICADORES
     ========================================================= -->

<div class="stock-metrics">


    <div class="stock-card">

        <small>
            Itens cadastrados
        </small>

        <strong>
            <?=$totalItens?>
        </strong>

    </div>


    <div class="stock-card">

        <small>
            Estoque baixo
        </small>

        <strong>
            <?=$estoqueBaixo?>
        </strong>

    </div>


    <div class="stock-card">

        <small>
            Valor em estoque
        </small>

        <strong>
            <?=money($valorEstoque)?>
        </strong>

    </div>


    <div class="stock-card">

        <small>
            Situação
        </small>

        <strong>

            <?=$estoqueBaixo > 0
                ? 'Atenção'
                : 'Normal'
            ?>

        </strong>

    </div>


</div>



<!-- =========================================================
     CADASTRO
     ========================================================= -->

<div class="card">


    <span class="eyebrow">
        CADASTRO
    </span>


    <h3>
        Novo item de estoque
    </h3>


    <form method="post">


        <?=csrf_field()?>


        <input
            type="hidden"
            name="acao"
            value="cadastrar"
        >


        <div class="grid-2">


            <label>

                Item

                <input
                    name="nome"
                    required
                    placeholder="Ex.: Chocolate meio amargo"
                >

            </label>


            <label>

                Tipo

                <select
                    name="tipo"
                    id="tipoItem"
                >

                    <option value="ingrediente">
                        Ingrediente
                    </option>

                    <option value="embalagem">
                        Embalagem
                    </option>

                    <option value="produto_pronto">
                        Produto pronto
                    </option>

                    <option value="outro">
                        Outro
                    </option>

                </select>

            </label>


            <label
                id="produtoProntoWrap"
                class="hidden"
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
                        >

                            <?=e($u['nome'])?>

                            (
                            <?=e($u['sigla'])?>
                            )

                        </option>

                    <?php endforeach; ?>

                </select>

            </label>


            <label>

                Estoque inicial

                <input
                    name="quantidade"
                    type="number"
                    step=".001"
                    min="0"
                    value="0"
                >

            </label>


            <label>

                Estoque mínimo

                <input
                    name="minimo"
                    type="number"
                    step=".001"
                    min="0"
                    value="0"
                >

            </label>


            <label>

                Custo médio

                <input
                    name="custo_medio"
                    type="number"
                    step=".0001"
                    min="0"
                    value="0"
                >

            </label>


        </div>


        <button
            class="btn primary"
            type="submit"
        >
            Cadastrar item
        </button>


    </form>


</div>



<!-- =========================================================
     FILTROS
     ========================================================= -->

<div style="margin-top:28px">


    <span class="eyebrow">
        ESTOQUE ATUAL
    </span>


    <h2>
        Visão geral
    </h2>


    <div class="stock-filters">


        <a
            class="
                stock-filter
                <?=$filtroTipo === ''
                    ? 'active'
                    : ''
                ?>
            "
            href="/admin/estoque/"
        >
            Todos
        </a>


        <a
            class="
                stock-filter
                <?=$filtroTipo === 'ingrediente'
                    ? 'active'
                    : ''
                ?>
            "
            href="?tipo=ingrediente"
        >
            Ingredientes
        </a>


        <a
            class="
                stock-filter
                <?=$filtroTipo === 'embalagem'
                    ? 'active'
                    : ''
                ?>
            "
            href="?tipo=embalagem"
        >
            Embalagens
        </a>


        <a
            class="
                stock-filter
                <?=$filtroTipo === 'produto_pronto'
                    ? 'active'
                    : ''
                ?>
            "
            href="?tipo=produto_pronto"
        >
            Produtos prontos
        </a>


        <a
            class="
                stock-filter
                <?=$filtroTipo === 'outro'
                    ? 'active'
                    : ''
                ?>
            "
            href="?tipo=outro"
        >
            Outros
        </a>


    </div>

</div>



<!-- =========================================================
     TABELA
     ========================================================= -->

<div class="table-wrap">


<table>


<thead>

<tr>

    <th>
        Item
    </th>

    <th>
        Tipo
    </th>

    <th>
        Estoque
    </th>

    <th>
        Mínimo
    </th>

    <th>
        Custo médio
    </th>

    <th>
        Valor estoque
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


<?php

$baixo =
    (float)$r['estoque_atual']
    <=
    (float)$r['estoque_minimo'];


$valorItem =
    (float)$r['estoque_atual']
    *
    (float)$r['custo_medio'];

?>


<tr>


    <td>

        <strong>
            <?=e($r['nome'])?>
        </strong>


        <?php if (
            !empty(
                $r['produto_nome']
            )
        ): ?>

            <br>

            <small>

                Produto:
                <?=e($r['produto_nome'])?>

            </small>

        <?php endif; ?>

    </td>


    <td class="stock-type">

        <?=e(
            str_replace(
                '_',
                ' ',
                $r['tipo']
            )
        )?>

    </td>


    <td>

        <strong>

            <?=number_format(
                (float)$r['estoque_atual'],
                3,
                ',',
                '.'
            )?>

        </strong>

        <?=e($r['sigla'])?>

    </td>


    <td>

        <?=number_format(
            (float)$r['estoque_minimo'],
            3,
            ',',
            '.'
        )?>

        <?=e($r['sigla'])?>

    </td>


    <td>

        <?=money(
            $r['custo_medio']
        )?>

        /

        <?=e($r['sigla'])?>

    </td>


    <td class="stock-value">

        <?=money(
            $valorItem
        )?>

    </td>


    <td>


        <?php if ($baixo): ?>

            <span
                class="
                    badge
                    stock-low
                "
            >
                REPOSIÇÃO
            </span>

        <?php else: ?>

            <span
                class="
                    badge
                    stock-ok
                "
            >
                OK
            </span>

        <?php endif; ?>


    </td>


    <!-- =================================================
         AÇÕES
         ================================================= -->

    <td>


        <div class="stock-actions">


            <a
                class="stock-action"
                href="/admin/estoque/editar.php?id=<?=$r['id']?>"
            >
                Editar
            </a>


            <a
                class="stock-action"
                href="/admin/estoque/ajustes.php?id=<?=$r['id']?>"
            >
                Ajustar
            </a>


            <a
                class="stock-action"
                href="/admin/estoque/movimentacoes.php?id=<?=$r['id']?>"
            >
                Histórico
            </a>


            <form
                method="post"
                onsubmit="
                    return confirm(
                        'Desativar este item? Ele deixará de aparecer nos cadastros, mas o histórico será preservado.'
                    );
                "
            >

                <?=csrf_field()?>


                <input
                    type="hidden"
                    name="acao"
                    value="desativar"
                >


                <input
                    type="hidden"
                    name="id"
                    value="<?=$r['id']?>"
                >


                <button
                    type="submit"
                    class="stock-action"
                >
                    Desativar
                </button>


            </form>


            <form
                method="post"
                onsubmit="
                    return confirm(
                        'Excluir definitivamente este item? Só será permitido se ele nunca tiver sido utilizado.'
                    );
                "
            >

                <?=csrf_field()?>


                <input
                    type="hidden"
                    name="acao"
                    value="excluir"
                >


                <input
                    type="hidden"
                    name="id"
                    value="<?=$r['id']?>"
                >


                <button
                    type="submit"
                    class="stock-action danger"
                >
                    Excluir
                </button>


            </form>


        </div>


    </td>


</tr>


<?php endforeach; ?>


<?php if (!$rows): ?>


<tr>

    <td
        colspan="8"
        style="
            text-align:center;
            padding:30px;
        "
    >

        Nenhum item encontrado.

    </td>

</tr>


<?php endif; ?>


</tbody>


</table>


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


        function controlarProduto(){

            produto.classList.toggle(
                'hidden',
                tipo.value
                !==
                'produto_pronto'
            );

        }


        tipo.addEventListener(
            'change',
            controlarProduto
        );


        controlarProduto();

    }
);

</script>


<?php

include __DIR__
    . '/../../includes/admin_footer.php';

?>
