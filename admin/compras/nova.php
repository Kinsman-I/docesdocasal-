<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = require_admin();

$msg = '';
$error = '';

$compraId = (int)($_GET['id'] ?? 0);


/* =========================================================
   FUNÇÃO NUMÉRICA
   ========================================================= */

function compra_numero($valor): float
{
    $valor = trim((string)$valor);

    if ($valor === '') {
        return 0;
    }

    $valor = str_replace(',', '.', $valor);

    return (float)$valor;
}


/* =========================================================
   AÇÕES
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $acao = $_POST['acao'] ?? 'salvar';


    /* =====================================================
       CRIAR COMPRA
       ===================================================== */

    if ($acao === 'salvar') {

        $fornecedorId =
            !empty($_POST['fornecedor_id'])
                ? (int)$_POST['fornecedor_id']
                : null;

        $documento =
            trim($_POST['numero_documento'] ?? '');

        $dataCompra =
            $_POST['data_compra'] ?? date('Y-m-d');

        $frete =
            max(0, compra_numero($_POST['frete'] ?? 0));

        $desconto =
            max(0, compra_numero($_POST['desconto'] ?? 0));

        $observacao =
            trim($_POST['observacao'] ?? '');

        $itens =
            $_POST['itens'] ?? [];


        $itensValidos = [];
        $subtotal = 0;


        foreach ($itens as $item) {

            $itemId =
                (int)($item['item_id'] ?? 0);

            $quantidade =
                compra_numero(
                    $item['quantidade'] ?? 0
                );

            $valorUnitario =
                compra_numero(
                    $item['valor_unitario'] ?? 0
                );


            if (
                $itemId <= 0
                ||
                $quantidade <= 0
            ) {
                continue;
            }


            $valorTotal =
                $quantidade
                *
                $valorUnitario;


            $subtotal +=
                $valorTotal;


            $itensValidos[] = [
                'item_id' => $itemId,
                'quantidade' => $quantidade,
                'valor_unitario' => $valorUnitario,
                'valor_total' => $valorTotal
            ];
        }


        if (!$itensValidos) {

            $error =
                'Adicione pelo menos um item à compra.';

        }


        if (!$error) {

            $total =
                max(
                    0,
                    $subtotal
                    +
                    $frete
                    -
                    $desconto
                );


            try {

                db()->beginTransaction();


                $st = db()->prepare("
                    INSERT INTO compras
                    (
                        fornecedor_id,
                        numero_documento,
                        status,
                        subtotal,
                        frete,
                        desconto,
                        total,
                        data_compra,
                        observacao,
                        usuario_id
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        'aberta',
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");


                $st->execute([
                    $fornecedorId,
                    $documento ?: null,
                    $subtotal,
                    $frete,
                    $desconto,
                    $total,
                    $dataCompra,
                    $observacao ?: null,
                    $admin['id']
                ]);


                $novoId =
                    (int)db()->lastInsertId();


                $itemInsert =
                    db()->prepare("
                        INSERT INTO compra_itens
                        (
                            compra_id,
                            item_estoque_id,
                            quantidade,
                            valor_unitario,
                            valor_total
                        )
                        VALUES (?, ?, ?, ?, ?)
                    ");


                foreach ($itensValidos as $item) {

                    $itemInsert->execute([
                        $novoId,
                        $item['item_id'],
                        $item['quantidade'],
                        $item['valor_unitario'],
                        $item['valor_total']
                    ]);
                }


                db()->commit();


                audit(
                    'compra_criada',
                    'compras',
                    $novoId,
                    'Compra criada'
                );


                header(
                    'Location:/admin/compras/nova.php?id='
                    . $novoId
                    . '&criada=1'
                );

                exit;


            } catch (Throwable $e) {

                if (db()->inTransaction()) {
                    db()->rollBack();
                }


                $error =
                    'Não foi possível cadastrar a compra.';
            }
        }
    }


    /* =====================================================
       RECEBER COMPRA
       ===================================================== */

    if (
        $acao === 'receber'
        &&
        $compraId > 0
    ) {

        try {

            db()->beginTransaction();


            /*
             * Bloqueia a compra durante o recebimento.
             * Evita entrada duplicada.
             */
            $st = db()->prepare("
                SELECT *
                FROM compras
                WHERE id = ?
                FOR UPDATE
            ");

            $st->execute([$compraId]);

            $compra =
                $st->fetch();


            if (!$compra) {

                throw new RuntimeException(
                    'Compra não encontrada.'
                );
            }


            if (
                $compra['status']
                !==
                'aberta'
            ) {

                throw new RuntimeException(
                    'Esta compra não está aberta.'
                );
            }


            $st = db()->prepare("
                SELECT
                    ci.*,
                    i.estoque_atual,
                    i.custo_medio

                FROM compra_itens ci

                JOIN itens_estoque i
                    ON i.id = ci.item_estoque_id

                WHERE ci.compra_id = ?
            ");

            $st->execute([$compraId]);

            $itensCompra =
                $st->fetchAll();


            if (!$itensCompra) {

                throw new RuntimeException(
                    'Compra sem itens.'
                );
            }


            foreach ($itensCompra as $item) {

                $estoqueAnterior =
                    (float)$item['estoque_atual'];

                $custoAnterior =
                    (float)$item['custo_medio'];

                $quantidadeEntrada =
                    (float)$item['quantidade'];

                $custoEntrada =
                    (float)$item['valor_unitario'];


                $novoEstoque =
                    $estoqueAnterior
                    +
                    $quantidadeEntrada;


                /*
                 * CUSTO MÉDIO PONDERADO
                 */
                if ($novoEstoque > 0) {

                    $novoCusto =
                        (
                            (
                                $estoqueAnterior
                                *
                                $custoAnterior
                            )
                            +
                            (
                                $quantidadeEntrada
                                *
                                $custoEntrada
                            )
                        )
                        /
                        $novoEstoque;

                } else {

                    $novoCusto =
                        $custoEntrada;
                }


                db()->prepare("
                    UPDATE itens_estoque
                    SET
                        estoque_atual = ?,
                        custo_medio = ?,
                        atualizado_em = NOW()
                    WHERE id = ?
                ")->execute([
                    $novoEstoque,
                    $novoCusto,
                    $item['item_estoque_id']
                ]);


                db()->prepare("
                    INSERT INTO estoque_movimentacoes
                    (
                        item_estoque_id,
                        tipo,
                        quantidade,
                        custo_unitario,
                        referencia_tipo,
                        referencia_id,
                        observacao,
                        usuario_id
                    )
                    VALUES
                    (
                        ?,
                        'entrada',
                        ?,
                        ?,
                        'compra',
                        ?,
                        'Recebimento de compra',
                        ?
                    )
                ")->execute([
                    $item['item_estoque_id'],
                    $quantidadeEntrada,
                    $custoEntrada,
                    $compraId,
                    $admin['id']
                ]);
            }


            db()->prepare("
                UPDATE compras
                SET
                    status = 'recebida',
                    data_recebimento = CURDATE()
                WHERE id = ?
            ")->execute([
                $compraId
            ]);


            db()->commit();


            audit(
                'compra_recebida',
                'compras',
                $compraId,
                'Entrada automática no estoque'
            );


            header(
                'Location:/admin/compras/nova.php?id='
                . $compraId
                . '&recebida=1'
            );

            exit;


        } catch (Throwable $e) {

            if (db()->inTransaction()) {
                db()->rollBack();
            }


            $error =
                $e->getMessage();
        }
    }


    /* =====================================================
       CANCELAR
       ===================================================== */

    if (
        $acao === 'cancelar'
        &&
        $compraId > 0
    ) {

        $st = db()->prepare("
            UPDATE compras
            SET status = 'cancelada'
            WHERE id = ?
              AND status = 'aberta'
        ");

        $st->execute([
            $compraId
        ]);


        audit(
            'compra_cancelada',
            'compras',
            $compraId
        );


        header(
            'Location:/admin/compras/nova.php?id='
            . $compraId
        );

        exit;
    }
}


/* =========================================================
   MENSAGENS
   ========================================================= */

if (!empty($_GET['criada'])) {
    $msg = 'Compra criada com sucesso.';
}

if (!empty($_GET['recebida'])) {
    $msg = 'Compra recebida e estoque atualizado com sucesso.';
}


/* =========================================================
   FORNECEDORES
   ========================================================= */

$fornecedores =
    db()->query("
        SELECT
            id,
            nome

        FROM fornecedores

        WHERE ativo = 1

        ORDER BY nome
    ")->fetchAll();


/* =========================================================
   ITENS DE ESTOQUE
   ========================================================= */

$estoque =
    db()->query("
        SELECT
            i.id,
            i.nome,
            i.tipo,
            i.estoque_atual,
            i.custo_medio,
            u.sigla

        FROM itens_estoque i

        JOIN unidades_medida u
            ON u.id = i.unidade_id

        WHERE i.ativo = 1

        ORDER BY i.nome
    ")->fetchAll();


/* =========================================================
   COMPRA EXISTENTE
   ========================================================= */

$compra = null;
$itensCompra = [];


if ($compraId > 0) {

    $st = db()->prepare("
        SELECT
            c.*,
            f.nome AS fornecedor_nome

        FROM compras c

        LEFT JOIN fornecedores f
            ON f.id = c.fornecedor_id

        WHERE c.id = ?

        LIMIT 1
    ");

    $st->execute([
        $compraId
    ]);

    $compra =
        $st->fetch();


    if ($compra) {

        $st = db()->prepare("
            SELECT
                ci.*,
                i.nome,
                u.sigla

            FROM compra_itens ci

            JOIN itens_estoque i
                ON i.id = ci.item_estoque_id

            JOIN unidades_medida u
                ON u.id = i.unidade_id

            WHERE ci.compra_id = ?

            ORDER BY ci.id
        ");

        $st->execute([
            $compraId
        ]);

        $itensCompra =
            $st->fetchAll();
    }
}


/* =========================================================
   PÁGINA
   ========================================================= */

$title =
    $compra
        ? 'Compra #' . str_pad(
            (string)$compra['id'],
            5,
            '0',
            STR_PAD_LEFT
        )
        : 'Nova compra';


include __DIR__
    . '/../../includes/admin_header.php';

?>


<style>

.purchase-form-header{
    display:flex;
    justify-content:space-between;
    gap:16px;
    align-items:center;
    flex-wrap:wrap;
    margin-bottom:20px;
}

.purchase-items{
    margin-top:20px;
}

.purchase-item-row{
    display:grid;
    grid-template-columns:2fr 1fr 1fr auto;
    gap:10px;
    align-items:end;
    margin-bottom:10px;
}

.purchase-summary{
    max-width:430px;
    margin-left:auto;
    margin-top:20px;
    padding:20px;
    background:var(--cream);
    border-radius:18px;
}

.purchase-summary div{
    display:flex;
    justify-content:space-between;
    margin:8px 0;
}

.purchase-summary .total{
    border-top:1px solid var(--line);
    padding-top:12px;
    font-size:20px;
}

.purchase-status-big{
    font-weight:800;
    text-transform:uppercase;
}

.purchase-final-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:20px;
}

@media(max-width:700px){

    .purchase-item-row{
        grid-template-columns:1fr;
        padding:15px;
        border:1px solid var(--line);
        border-radius:14px;
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



<?php if ($compra): ?>


<!-- =========================================================
     VISUALIZAÇÃO
     ========================================================= -->

<div class="purchase-form-header">

    <div>

        <span class="eyebrow">
            COMPRA
        </span>

        <h2>
            #<?=str_pad(
                (string)$compra['id'],
                5,
                '0',
                STR_PAD_LEFT
            )?>
        </h2>

    </div>


    <a
        href="/admin/compras/"
        class="btn outline"
    >
        Voltar
    </a>

</div>


<div class="card">

    <div class="grid-2">

        <div>
            <small>Fornecedor</small>
            <br>
            <strong>
                <?=e($compra['fornecedor_nome'] ?: 'Não informado')?>
            </strong>
        </div>


        <div>
            <small>Status</small>
            <br>
            <strong class="purchase-status-big">
                <?=e($compra['status'])?>
            </strong>
        </div>


        <div>
            <small>Documento</small>
            <br>
            <strong>
                <?=e($compra['numero_documento'] ?: '—')?>
            </strong>
        </div>


        <div>
            <small>Data da compra</small>
            <br>
            <strong>
                <?=date(
                    'd/m/Y',
                    strtotime($compra['data_compra'])
                )?>
            </strong>
        </div>

    </div>

</div>


<div class="table-wrap" style="margin-top:20px">

<table>

<thead>

<tr>
    <th>Item</th>
    <th>Quantidade</th>
    <th>Valor unitário</th>
    <th>Total</th>
</tr>

</thead>

<tbody>

<?php foreach ($itensCompra as $item): ?>

<tr>

    <td>
        <strong><?=e($item['nome'])?></strong>
    </td>

    <td>
        <?=number_format(
            (float)$item['quantidade'],
            3,
            ',',
            '.'
        )?>

        <?=e($item['sigla'])?>
    </td>

    <td>
        <?=money($item['valor_unitario'])?>
    </td>

    <td>
        <strong>
            <?=money($item['valor_total'])?>
        </strong>
    </td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<div class="purchase-summary">

    <div>
        <span>Subtotal</span>
        <strong><?=money($compra['subtotal'])?></strong>
    </div>

    <div>
        <span>Frete</span>
        <strong><?=money($compra['frete'])?></strong>
    </div>

    <div>
        <span>Desconto</span>
        <strong>- <?=money($compra['desconto'])?></strong>
    </div>

    <div class="total">
        <span>Total</span>
        <strong><?=money($compra['total'])?></strong>
    </div>

</div>


<?php if ($compra['observacao']): ?>

<div class="card" style="margin-top:20px">

    <strong>Observação</strong>

    <p>
        <?=nl2br(e($compra['observacao']))?>
    </p>

</div>

<?php endif; ?>


<?php if ($compra['status'] === 'aberta'): ?>

<div class="purchase-final-actions">


    <form method="post">

        <?=csrf_field()?>

        <input
            type="hidden"
            name="acao"
            value="receber"
        >

        <button
            class="btn primary"
            onclick="return confirm('Confirmar recebimento? O estoque será atualizado automaticamente.')"
        >
            Receber compra
        </button>

    </form>


    <form method="post">

        <?=csrf_field()?>

        <input
            type="hidden"
            name="acao"
            value="cancelar"
        >

        <button
            class="btn outline"
            onclick="return confirm('Deseja cancelar esta compra?')"
        >
            Cancelar compra
        </button>

    </form>


</div>

<?php endif; ?>


<?php else: ?>


<!-- =========================================================
     NOVA COMPRA
     ========================================================= -->

<div class="purchase-form-header">

    <div>

        <span class="eyebrow">
            COMPRAS
        </span>

        <h2>
            Nova compra
        </h2>

    </div>

    <a
        href="/admin/compras/"
        class="btn outline"
    >
        Voltar
    </a>

</div>


<form method="post" id="purchaseForm">

<?=csrf_field()?>

<input
    type="hidden"
    name="acao"
    value="salvar"
>


<div class="card">

    <div class="grid-2">


        <label>

            Fornecedor

            <select name="fornecedor_id">

                <option value="">
                    Selecione
                </option>

                <?php foreach ($fornecedores as $f): ?>

                    <option value="<?=$f['id']?>">
                        <?=e($f['nome'])?>
                    </option>

                <?php endforeach; ?>

            </select>

        </label>


        <label>

            Documento / NF

            <input
                name="numero_documento"
                placeholder="Opcional"
            >

        </label>


        <label>

            Data da compra

            <input
                type="date"
                name="data_compra"
                value="<?=date('Y-m-d')?>"
                required
            >

        </label>


        <label>

            Frete

            <input
                type="number"
                name="frete"
                id="frete"
                step=".01"
                min="0"
                value="0"
            >

        </label>


        <label>

            Desconto

            <input
                type="number"
                name="desconto"
                id="desconto"
                step=".01"
                min="0"
                value="0"
            >

        </label>


    </div>


    <label>

        Observação

        <textarea
            name="observacao"
            placeholder="Observações sobre a compra..."
        ></textarea>

    </label>

</div>



<div class="card purchase-items">

    <span class="eyebrow">
        ITENS
    </span>

    <h3>
        Itens da compra
    </h3>


    <div id="purchaseItems"></div>


    <button
        type="button"
        class="btn outline"
        id="addPurchaseItem"
    >
        + Adicionar item
    </button>

</div>



<div class="purchase-summary">

    <div>
        <span>Subtotal</span>
        <strong id="purchaseSubtotal">
            R$ 0,00
        </strong>
    </div>

    <div>
        <span>Frete</span>
        <strong id="purchaseFrete">
            R$ 0,00
        </strong>
    </div>

    <div>
        <span>Desconto</span>
        <strong id="purchaseDesconto">
            R$ 0,00
        </strong>
    </div>

    <div class="total">
        <span>Total</span>
        <strong id="purchaseTotal">
            R$ 0,00
        </strong>
    </div>

</div>


<button
    class="btn primary"
    style="margin-top:20px"
>
    Salvar compra
</button>


</form>



<template id="purchaseItemTemplate">

<div class="purchase-item-row">

    <label>

        Item

        <select
            class="purchase-item-select"
        >

            <option value="">
                Selecione
            </option>

            <?php foreach ($estoque as $item): ?>

                <option
                    value="<?=$item['id']?>"
                    data-custo="<?=e($item['custo_medio'])?>"
                >

                    <?=e($item['nome'])?>

                    (<?=e($item['sigla'])?>)

                </option>

            <?php endforeach; ?>

        </select>

    </label>


    <label>

        Quantidade

        <input
            type="number"
            class="purchase-qty"
            step=".001"
            min=".001"
            value="1"
        >

    </label>


    <label>

        Valor unitário

        <input
            type="number"
            class="purchase-price"
            step=".0001"
            min="0"
            value="0"
        >

    </label>


    <button
        type="button"
        class="btn outline purchase-remove"
    >
        Remover
    </button>

</div>

</template>



<script>

document.addEventListener('DOMContentLoaded', function(){

    const container =
        document.getElementById('purchaseItems');

    const template =
        document.getElementById('purchaseItemTemplate');

    const addButton =
        document.getElementById('addPurchaseItem');

    const frete =
        document.getElementById('frete');

    const desconto =
        document.getElementById('desconto');


    let indice = 0;


    function dinheiro(valor){

        return Number(valor || 0).toLocaleString(
            'pt-BR',
            {
                style:'currency',
                currency:'BRL'
            }
        );
    }


    function atualizarNomes(){

        const linhas =
            container.querySelectorAll(
                '.purchase-item-row'
            );


        linhas.forEach(
            function(linha, index){

                linha.querySelector(
                    '.purchase-item-select'
                ).name =
                    `itens[${index}][item_id]`;


                linha.querySelector(
                    '.purchase-qty'
                ).name =
                    `itens[${index}][quantidade]`;


                linha.querySelector(
                    '.purchase-price'
                ).name =
                    `itens[${index}][valor_unitario]`;

            }
        );

    }


    function calcular(){

        let subtotal = 0;


        container
            .querySelectorAll(
                '.purchase-item-row'
            )
            .forEach(
                function(linha){

                    const qtd =
                        Number(
                            linha.querySelector(
                                '.purchase-qty'
                            ).value
                            || 0
                        );


                    const preco =
                        Number(
                            linha.querySelector(
                                '.purchase-price'
                            ).value
                            || 0
                        );


                    subtotal +=
                        qtd * preco;

                }
            );


        const valorFrete =
            Number(frete.value || 0);


        const valorDesconto =
            Number(desconto.value || 0);


        const total =
            Math.max(
                0,
                subtotal
                +
                valorFrete
                -
                valorDesconto
            );


        document.getElementById(
            'purchaseSubtotal'
        ).textContent =
            dinheiro(subtotal);


        document.getElementById(
            'purchaseFrete'
        ).textContent =
            dinheiro(valorFrete);


        document.getElementById(
            'purchaseDesconto'
        ).textContent =
            dinheiro(valorDesconto);


        document.getElementById(
            'purchaseTotal'
        ).textContent =
            dinheiro(total);

    }


    function adicionarItem(){

        const fragment =
            template.content.cloneNode(true);


        const linha =
            fragment.querySelector(
                '.purchase-item-row'
            );


        const select =
            linha.querySelector(
                '.purchase-item-select'
            );


        const quantidade =
            linha.querySelector(
                '.purchase-qty'
            );


        const preco =
            linha.querySelector(
                '.purchase-price'
            );


        select.addEventListener(
            'change',
            function(){

                const option =
                    this.options[
                        this.selectedIndex
                    ];


                if (
                    option
                    &&
                    option.dataset.custo
                ) {

                    preco.value =
                        Number(
                            option.dataset.custo
                        ).toFixed(4);

                }


                calcular();

            }
        );


        quantidade.addEventListener(
            'input',
            calcular
        );


        preco.addEventListener(
            'input',
            calcular
        );


        linha
            .querySelector(
                '.purchase-remove'
            )
            .addEventListener(
                'click',
                function(){

                    linha.remove();

                    atualizarNomes();

                    calcular();

                }
            );


        container.appendChild(
            fragment
        );


        atualizarNomes();

        calcular();

        indice++;

    }


    addButton.addEventListener(
        'click',
        adicionarItem
    );


    frete.addEventListener(
        'input',
        calcular
    );


    desconto.addEventListener(
        'input',
        calcular
    );


    adicionarItem();

});

</script>


<?php endif; ?>


<?php

include __DIR__
    . '/../../includes/admin_footer.php';

?>