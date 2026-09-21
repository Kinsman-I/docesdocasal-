<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = require_admin();

$msg = '';
$error = '';


/* =========================================================
   PRODUTO SELECIONADO
   ========================================================= */

$produtoId =
    (int)(
        $_GET['produto']
        ?? $_POST['produto_id']
        ?? 0
    );


/* =========================================================
   ADICIONAR ITEM À FICHA
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();


    $acao =
        $_POST['acao']
        ?? 'adicionar';


    /* =====================================================
       ADICIONAR ITEM
       ===================================================== */

    if ($acao === 'adicionar') {

        $produtoId =
            (int)(
                $_POST['produto_id']
                ?? 0
            );


        $itemId =
            (int)(
                $_POST['item_estoque_id']
                ?? 0
            );


        $quantidade =
            (float)str_replace(
                ',',
                '.',
                $_POST['quantidade']
                ?? '0'
            );


        $perda =
            (float)str_replace(
                ',',
                '.',
                $_POST['perda_percentual']
                ?? '0'
            );


        if ($produtoId <= 0) {

            $error =
                'Selecione um produto.';

        } elseif ($itemId <= 0) {

            $error =
                'Selecione um item de estoque.';

        } elseif ($quantidade <= 0) {

            $error =
                'Informe uma quantidade maior que zero.';

        } elseif ($perda < 0) {

            $error =
                'A perda percentual não pode ser negativa.';

        } else {

            try {

                /*
                 * Verifica se esse item já existe
                 * na ficha técnica do produto.
                 */

                $check =
                    db()->prepare(
                        "
                        SELECT id
                        FROM receitas_produto
                        WHERE produto_id = ?
                          AND item_estoque_id = ?
                        LIMIT 1
                        "
                    );


                $check->execute([
                    $produtoId,
                    $itemId
                ]);


                $existente =
                    $check->fetchColumn();


                if ($existente) {

                    /*
                     * Atualiza se já existir.
                     */

                    db()->prepare(
                        "
                        UPDATE receitas_produto
                        SET
                            quantidade = ?,
                            perda_percentual = ?
                        WHERE id = ?
                        "
                    )->execute([
                        $quantidade,
                        $perda,
                        $existente
                    ]);


                    $msg =
                        'Item atualizado na ficha técnica.';


                    audit(
                        'ficha_tecnica_atualizada',
                        'receitas_produto',
                        (int)$existente,
                        'Produto ' . $produtoId
                    );


                } else {

                    /*
                     * Adiciona novo item.
                     */

                    $st =
                        db()->prepare(
                            "
                            INSERT INTO receitas_produto
                            (
                                produto_id,
                                item_estoque_id,
                                quantidade,
                                perda_percentual
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?
                            )
                            "
                        );


                    $st->execute([
                        $produtoId,
                        $itemId,
                        $quantidade,
                        $perda
                    ]);


                    $novoId =
                        (int)db()->lastInsertId();


                    $msg =
                        'Item adicionado à ficha técnica.';


                    audit(
                        'ficha_tecnica_item_adicionado',
                        'receitas_produto',
                        $novoId,
                        'Produto ' . $produtoId
                    );

                }


            } catch (Throwable $e) {

                $error =
                    'Não foi possível salvar o item na ficha técnica.';

            }

        }

    }


    /* =====================================================
       REMOVER ITEM
       ===================================================== */

    if ($acao === 'remover') {

        $produtoId =
            (int)(
                $_POST['produto_id']
                ?? 0
            );


        $receitaId =
            (int)(
                $_POST['receita_id']
                ?? 0
            );


        if (
            $produtoId > 0
            && $receitaId > 0
        ) {

            try {

                db()->prepare(
                    "
                    DELETE FROM receitas_produto
                    WHERE id = ?
                      AND produto_id = ?
                    "
                )->execute([
                    $receitaId,
                    $produtoId
                ]);


                $msg =
                    'Item removido da ficha técnica.';


                audit(
                    'ficha_tecnica_item_removido',
                    'receitas_produto',
                    $receitaId,
                    'Produto ' . $produtoId
                );


            } catch (Throwable $e) {

                $error =
                    'Não foi possível remover o item.';

            }

        }

    }

}


/* =========================================================
   PRODUTOS
   ========================================================= */

$produtos =
    db()->query(
        "
        SELECT
            id,
            nome,
            preco_venda,
            custo_estimado,
            disponivel

        FROM produtos

        ORDER BY nome
        "
    )->fetchAll();



/* =========================================================
   PRODUTO ATUAL
   ========================================================= */

$produtoAtual =
    null;


if ($produtoId > 0) {

    $st =
        db()->prepare(
            "
            SELECT *
            FROM produtos
            WHERE id = ?
            LIMIT 1
            "
        );


    $st->execute([
        $produtoId
    ]);


    $produtoAtual =
        $st->fetch();

}


/* =========================================================
   ITENS DISPONÍVEIS PARA RECEITA
   ========================================================= */

$itensEstoque =
    db()->query(
        "
        SELECT
            i.id,
            i.nome,
            i.tipo,
            i.estoque_atual,
            i.custo_medio,
            u.sigla,
            u.nome AS unidade_nome

        FROM itens_estoque i

        JOIN unidades_medida u
            ON u.id = i.unidade_id

        WHERE i.ativo = 1
          AND i.tipo IN (
              'ingrediente',
              'embalagem',
              'outro'
          )

        ORDER BY
            i.tipo,
            i.nome
        "
    )->fetchAll();



/* =========================================================
   FICHA TÉCNICA ATUAL
   ========================================================= */

$ficha =
    [];


if ($produtoAtual) {

    $st =
        db()->prepare(
            "
            SELECT
                r.id,
                r.quantidade,
                r.perda_percentual,

                i.id AS item_id,
                i.nome AS item_nome,
                i.tipo,
                i.estoque_atual,
                i.custo_medio,

                u.sigla

            FROM receitas_produto r

            JOIN itens_estoque i
                ON i.id = r.item_estoque_id

            JOIN unidades_medida u
                ON u.id = i.unidade_id

            WHERE r.produto_id = ?

            ORDER BY
                i.tipo,
                i.nome
            "
        );


    $st->execute([
        $produtoId
    ]);


    $ficha =
        $st->fetchAll();

}


/* =========================================================
   CUSTO ESTIMADO DA FICHA
   ========================================================= */

$custoFicha =
    0;


foreach ($ficha as $item) {

    $quantidadeComPerda =
        (float)$item['quantidade']
        *
        (
            1
            +
            (
                (float)$item['perda_percentual']
                / 100
            )
        );


    $custoFicha +=
        $quantidadeComPerda
        *
        (float)$item['custo_medio'];

}


/* =========================================================
   PÁGINA
   ========================================================= */

$title =
    'Fichas Técnicas';


include __DIR__
    . '/../../includes/admin_header.php';

?>


<style>

.production-tabs{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-bottom:24px;
}

.production-tab{
    padding:9px 14px;
    border:1px solid var(--line);
    border-radius:999px;
    background:#fff;
    font-weight:700;
    font-size:13px;
}

.production-tab.active{
    background:var(--brown);
    color:#fff;
}


.recipe-layout{
    display:grid;
    grid-template-columns:320px 1fr;
    gap:20px;
}


.recipe-products{
    background:#fff;
    border:1px solid var(--line);
    border-radius:20px;
    padding:16px;
    height:max-content;
}


.recipe-product{
    display:block;
    padding:12px;
    border-radius:12px;
    border:1px solid transparent;
    margin-bottom:6px;
}


.recipe-product:hover{
    background:var(--cream);
}


.recipe-product.active{
    background:var(--cream);
    border-color:var(--line);
}


.recipe-product strong{
    display:block;
}


.recipe-product small{
    color:var(--muted);
}


.recipe-main{
    min-width:0;
}


.recipe-summary{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:12px;
    margin-bottom:18px;
}


.recipe-summary-card{
    background:#fff;
    border:1px solid var(--line);
    border-radius:16px;
    padding:16px;
}


.recipe-summary-card small{
    display:block;
    color:var(--muted);
    margin-bottom:5px;
}


.recipe-summary-card strong{
    font-size:20px;
}


.recipe-form{
    margin-bottom:18px;
}


.recipe-type{
    text-transform:capitalize;
}


.recipe-stock-low{
    color:var(--danger);
    font-weight:700;
}


.recipe-stock-ok{
    color:var(--success);
    font-weight:700;
}


.recipe-remove{
    border:0;
    background:transparent;
    color:var(--danger);
    cursor:pointer;
    font-weight:700;
}


@media(max-width:900px){

    .recipe-layout{
        grid-template-columns:1fr;
    }

    .recipe-summary{
        grid-template-columns:1fr;
    }

}

</style>



<!-- =========================================================
     ABAS DE PRODUÇÃO
     ========================================================= -->

<div class="production-tabs">

    <a
        class="production-tab"
        href="/admin/producao/"
    >
        Ordens de Produção
    </a>

    <a
        class="production-tab"
        href="/admin/producao/nova.php"
    >
        Nova OP
    </a>

    <a
        class="production-tab active"
        href="/admin/producao/fichas.php"
    >
        Fichas Técnicas
    </a>

    <a
        class="production-tab"
        href="/admin/producao/consumos.php"
    >
        Consumos
    </a>

    <a
        class="production-tab"
        href="/admin/producao/etiquetas.php"
    >
        Etiquetas
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



<div class="recipe-layout">


    <!-- =====================================================
         PRODUTOS
         ===================================================== -->

    <aside class="recipe-products">


        <span class="eyebrow">
            PRODUTOS
        </span>


        <h3>
            Fichas técnicas
        </h3>


        <?php foreach ($produtos as $produto): ?>


            <a
                class="
                    recipe-product
                    <?=$produtoId === (int)$produto['id']
                        ? 'active'
                        : ''
                    ?>
                "
                href="?produto=<?=$produto['id']?>"
            >

                <strong>
                    <?=e($produto['nome'])?>
                </strong>


                <small>

                    Venda:
                    <?=money(
                        $produto['preco_venda']
                    )?>

                </small>

            </a>


        <?php endforeach; ?>


        <?php if (!$produtos): ?>

            <p>
                Nenhum produto cadastrado.
            </p>

        <?php endif; ?>


    </aside>



    <!-- =====================================================
         FICHA
         ===================================================== -->

    <section class="recipe-main">


        <?php if (!$produtoAtual): ?>


            <div class="card">

                <span class="eyebrow">
                    FICHA TÉCNICA
                </span>

                <h2>
                    Selecione um produto
                </h2>

                <p>
                    Escolha um produto ao lado para cadastrar
                    os ingredientes e embalagens consumidos
                    por unidade produzida.
                </p>

            </div>


        <?php else: ?>


            <!-- =============================================
                 RESUMO
                 ============================================= -->

            <div class="recipe-summary">


                <div class="recipe-summary-card">

                    <small>
                        Produto
                    </small>

                    <strong>
                        <?=e($produtoAtual['nome'])?>
                    </strong>

                </div>


                <div class="recipe-summary-card">

                    <small>
                        Itens na ficha
                    </small>

                    <strong>
                        <?=count($ficha)?>
                    </strong>

                </div>


                <div class="recipe-summary-card">

                    <small>
                        Custo estimado / unidade
                    </small>

                    <strong>
                        <?=money($custoFicha)?>
                    </strong>

                </div>


            </div>



            <!-- =============================================
                 ADICIONAR ITEM
                 ============================================= -->

            <div class="card recipe-form">


                <span class="eyebrow">
                    ADICIONAR ITEM
                </span>


                <h3>
                    Ingrediente ou embalagem
                </h3>


                <form method="post">


                    <?=csrf_field()?>


                    <input
                        type="hidden"
                        name="acao"
                        value="adicionar"
                    >


                    <input
                        type="hidden"
                        name="produto_id"
                        value="<?=$produtoId?>"
                    >


                    <div class="grid-2">


                        <label>

                            Item

                            <select
                                name="item_estoque_id"
                                required
                            >

                                <option value="">
                                    Selecione
                                </option>


                                <?php

                                $tipoAnterior =
                                    '';

                                ?>


                                <?php foreach (
                                    $itensEstoque
                                    as $item
                                ): ?>


                                    <?php

                                    if (
                                        $tipoAnterior
                                        !==
                                        $item['tipo']
                                    ) {

                                        if (
                                            $tipoAnterior
                                            !== ''
                                        ) {

                                            echo
                                                '</optgroup>';

                                        }


                                        echo
                                            '<optgroup label="'
                                            .
                                            e(
                                                ucfirst(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $item['tipo']
                                                    )
                                                )
                                            )
                                            .
                                            '">';


                                        $tipoAnterior =
                                            $item['tipo'];

                                    }

                                    ?>


                                    <option
                                        value="<?=$item['id']?>"
                                    >

                                        <?=e($item['nome'])?>

                                        —

                                        <?=number_format(
                                            (float)$item['estoque_atual'],
                                            3,
                                            ',',
                                            '.'
                                        )?>

                                        <?=e($item['sigla'])?>

                                    </option>


                                <?php endforeach; ?>


                                <?php

                                if (
                                    $tipoAnterior
                                    !== ''
                                ) {

                                    echo
                                        '</optgroup>';

                                }

                                ?>


                            </select>

                        </label>



                        <label>

                            Quantidade por unidade produzida

                            <input
                                name="quantidade"
                                type="number"
                                step=".0001"
                                min=".0001"
                                placeholder="Ex.: 0,060"
                                required
                            >

                        </label>



                        <label>

                            Perda %

                            <input
                                name="perda_percentual"
                                type="number"
                                step=".001"
                                min="0"
                                value="0"
                            >

                        </label>


                    </div>


                    <button
                        class="btn primary"
                        type="submit"
                    >

                        Adicionar à ficha

                    </button>


                </form>


            </div>



            <!-- =============================================
                 ITENS DA FICHA
                 ============================================= -->

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
                                Quantidade / unidade
                            </th>

                            <th>
                                Perda
                            </th>

                            <th>
                                Estoque atual
                            </th>

                            <th>
                                Custo
                            </th>

                            <th>
                                Ação
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $ficha
                        as $item
                    ): ?>


                        <?php

                        $quantidadeComPerda =
                            (float)$item['quantidade']
                            *
                            (
                                1
                                +
                                (
                                    (float)$item['perda_percentual']
                                    / 100
                                )
                            );


                        $custoItem =
                            $quantidadeComPerda
                            *
                            (float)$item['custo_medio'];

                        ?>


                        <tr>


                            <td>

                                <strong>

                                    <?=e(
                                        $item['item_nome']
                                    )?>

                                </strong>

                            </td>



                            <td class="recipe-type">

                                <?=e(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $item['tipo']
                                    )
                                )?>

                            </td>



                            <td>

                                <?=number_format(
                                    (float)$item['quantidade'],
                                    4,
                                    ',',
                                    '.'
                                )?>

                                <?=e($item['sigla'])?>

                            </td>



                            <td>

                                <?=number_format(
                                    (float)$item['perda_percentual'],
                                    2,
                                    ',',
                                    '.'
                                )?>

                                %

                            </td>



                            <td>

                                <?=number_format(
                                    (float)$item['estoque_atual'],
                                    3,
                                    ',',
                                    '.'
                                )?>

                                <?=e($item['sigla'])?>

                            </td>



                            <td>

                                <?=money(
                                    $custoItem
                                )?>

                            </td>



                            <td>

                                <form
                                    method="post"
                                    onsubmit="
                                        return confirm(
                                            'Remover este item da ficha técnica?'
                                        );
                                    "
                                >

                                    <?=csrf_field()?>


                                    <input
                                        type="hidden"
                                        name="acao"
                                        value="remover"
                                    >


                                    <input
                                        type="hidden"
                                        name="produto_id"
                                        value="<?=$produtoId?>"
                                    >


                                    <input
                                        type="hidden"
                                        name="receita_id"
                                        value="<?=$item['id']?>"
                                    >


                                    <button
                                        class="recipe-remove"
                                        type="submit"
                                    >

                                        Remover

                                    </button>

                                </form>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    <?php if (!$ficha): ?>


                        <tr>

                            <td
                                colspan="7"
                                style="
                                    text-align:center;
                                    padding:30px;
                                "
                            >

                                Este produto ainda não possui
                                ficha técnica cadastrada.

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>


                </table>


            </div>


        <?php endif; ?>


    </section>


</div>


<?php

include __DIR__
    . '/../../includes/admin_footer.php';

?>