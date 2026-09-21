<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = require_admin();

$msg = '';
$error = '';


/* =========================================================
   MENSAGENS
   ========================================================= */

if (!empty($_GET['sucesso'])) {

    $msg =
        'Operação realizada com sucesso.';

}


/* =========================================================
   FILTRO
   ========================================================= */

$statusFiltro =
    $_GET['status']
    ?? '';


$statusPermitidos = [
    'aberta',
    'recebida',
    'cancelada'
];


$where =
    "
    WHERE 1 = 1
    ";


$params = [];


if (
    in_array(
        $statusFiltro,
        $statusPermitidos,
        true
    )
) {

    $where .=
        "
        AND c.status = ?
        ";

    $params[] =
        $statusFiltro;

}


/* =========================================================
   LISTAGEM
   ========================================================= */

$sql =
    "
    SELECT
        c.id,
        c.numero_documento,
        c.status,
        c.subtotal,
        c.frete,
        c.desconto,
        c.total,
        c.data_compra,
        c.data_recebimento,
        c.criado_em,

        f.nome AS fornecedor_nome,

        COUNT(ci.id) AS total_itens

    FROM compras c

    LEFT JOIN fornecedores f
        ON f.id = c.fornecedor_id

    LEFT JOIN compra_itens ci
        ON ci.compra_id = c.id

    $where

    GROUP BY
        c.id,
        c.numero_documento,
        c.status,
        c.subtotal,
        c.frete,
        c.desconto,
        c.total,
        c.data_compra,
        c.data_recebimento,
        c.criado_em,
        f.nome

    ORDER BY
        c.id DESC

    LIMIT 200
    ";


$st =
    db()->prepare(
        $sql
    );


$st->execute(
    $params
);


$compras =
    $st->fetchAll();



/* =========================================================
   INDICADORES
   ========================================================= */

$indicadores =
    db()->query(
        "
        SELECT

            COUNT(*) AS total,

            SUM(
                CASE
                    WHEN status = 'aberta'
                    THEN 1
                    ELSE 0
                END
            ) AS abertas,

            SUM(
                CASE
                    WHEN status = 'recebida'
                    THEN 1
                    ELSE 0
                END
            ) AS recebidas,

            COALESCE(
                SUM(
                    CASE
                        WHEN status = 'recebida'
                        THEN total
                        ELSE 0
                    END
                ),
                0
            ) AS valor_recebido

        FROM compras
        "
    )->fetch();



/* =========================================================
   PÁGINA
   ========================================================= */

$title =
    'Compras';


include __DIR__
    . '/../../includes/admin_header.php';

?>


<style>

/* =========================================================
   HEADER
   ========================================================= */

.purchase-header{
    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:18px;

    margin-bottom:24px;

    flex-wrap:wrap;
}


.purchase-header-actions{
    display:flex;

    gap:8px;

    flex-wrap:wrap;
}


/* =========================================================
   INDICADORES
   ========================================================= */

.purchase-metrics{
    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:14px;

    margin-bottom:24px;
}


.purchase-metric{
    background:#fff;

    border:
        1px solid
        var(--line);

    border-radius:18px;

    padding:20px;
}


.purchase-metric small{
    display:block;

    color:
        var(--muted);

    margin-bottom:6px;
}


.purchase-metric strong{
    display:block;

    font-size:25px;

    color:
        var(--brown);
}


/* =========================================================
   FILTROS
   ========================================================= */

.purchase-filters{
    display:flex;

    gap:8px;

    flex-wrap:wrap;

    margin-bottom:18px;
}


.purchase-filter{
    padding:
        8px
        12px;

    border:
        1px solid
        var(--line);

    border-radius:999px;

    background:#fff;

    font-size:13px;

    font-weight:700;
}


.purchase-filter.active{
    background:
        var(--brown);

    color:#fff;
}


/* =========================================================
   STATUS
   ========================================================= */

.purchase-status{
    display:inline-block;

    padding:
        6px
        9px;

    border-radius:999px;

    font-size:11px;

    font-weight:800;

    text-transform:uppercase;
}


.purchase-status.aberta{
    background:#fff3cf;
    color:#765500;
}


.purchase-status.recebida{
    background:#e2f3e8;
    color:#245d38;
}


.purchase-status.cancelada{
    background:#f7dedd;
    color:#8b2820;
}


/* =========================================================
   AÇÕES
   ========================================================= */

.purchase-actions{
    display:flex;

    gap:6px;

    flex-wrap:wrap;
}


.purchase-action{
    display:inline-block;

    padding:
        7px
        10px;

    border:
        1px solid
        var(--line);

    border-radius:9px;

    background:#fff;

    font-size:12px;

    font-weight:700;
}


.purchase-action:hover{
    background:
        var(--cream);
}


/* =========================================================
   EMPTY
   ========================================================= */

.purchase-empty{
    padding:50px 20px;

    text-align:center;

    color:
        var(--muted);
}


/* =========================================================
   MOBILE
   ========================================================= */

@media(max-width:950px){

    .purchase-metrics{
        grid-template-columns:
            repeat(2,1fr);
    }

}


@media(max-width:600px){

    .purchase-metrics{
        grid-template-columns:
            1fr;
    }

}

</style>



<!-- =========================================================
     MENSAGENS
     ========================================================= -->

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
     CABEÇALHO
     ========================================================= -->

<div class="purchase-header">


    <div>

        <span class="eyebrow">
            COMPRAS
        </span>

        <h2>
            Controle de compras
        </h2>

        <p style="color:var(--muted)">

            Cadastre compras, acompanhe o recebimento
            e mantenha o estoque atualizado.

        </p>

    </div>



    <div class="purchase-header-actions">


        <a
            class="btn outline"
            href="/admin/compras/necessidades.php"
        >
            Necessidades
        </a>


        <a
            class="btn outline"
            href="/admin/compras/fornecedores.php"
        >
            Fornecedores
        </a>


        <a
            class="btn primary"
            href="/admin/compras/nova.php"
        >
            + Nova compra
        </a>


    </div>


</div>



<!-- =========================================================
     INDICADORES
     ========================================================= -->

<div class="purchase-metrics">


    <div class="purchase-metric">

        <small>
            Total de compras
        </small>

        <strong>
            <?=(int)$indicadores['total']?>
        </strong>

    </div>



    <div class="purchase-metric">

        <small>
            Compras abertas
        </small>

        <strong>
            <?=(int)$indicadores['abertas']?>
        </strong>

    </div>



    <div class="purchase-metric">

        <small>
            Compras recebidas
        </small>

        <strong>
            <?=(int)$indicadores['recebidas']?>
        </strong>

    </div>



    <div class="purchase-metric">

        <small>
            Valor recebido
        </small>

        <strong>
            <?=money(
                $indicadores['valor_recebido']
            )?>
        </strong>

    </div>


</div>



<!-- =========================================================
     FILTROS
     ========================================================= -->

<div class="purchase-filters">


    <a
        href="/admin/compras/"
        class="
            purchase-filter
            <?=$statusFiltro === ''
                ? 'active'
                : ''
            ?>
        "
    >
        Todas
    </a>


    <a
        href="?status=aberta"
        class="
            purchase-filter
            <?=$statusFiltro === 'aberta'
                ? 'active'
                : ''
            ?>
        "
    >
        Abertas
    </a>


    <a
        href="?status=recebida"
        class="
            purchase-filter
            <?=$statusFiltro === 'recebida'
                ? 'active'
                : ''
            ?>
        "
    >
        Recebidas
    </a>


    <a
        href="?status=cancelada"
        class="
            purchase-filter
            <?=$statusFiltro === 'cancelada'
                ? 'active'
                : ''
            ?>
        "
    >
        Canceladas
    </a>


</div>



<!-- =========================================================
     LISTAGEM
     ========================================================= -->

<div class="table-wrap">


<table>


<thead>


<tr>

    <th>
        Compra
    </th>

    <th>
        Fornecedor
    </th>

    <th>
        Documento
    </th>

    <th>
        Itens
    </th>

    <th>
        Data
    </th>

    <th>
        Total
    </th>

    <th>
        Status
    </th>

    <th>
        Ações
    </th>

</tr>


</thead>


<tbody>


<?php foreach (
    $compras
    as $c
): ?>


<tr>


    <!-- COMPRA -->

    <td>

        <strong>

            #<?=str_pad(
                (string)$c['id'],
                5,
                '0',
                STR_PAD_LEFT
            )?>

        </strong>

    </td>



    <!-- FORNECEDOR -->

    <td>

        <?php if (
            !empty(
                $c['fornecedor_nome']
            )
        ): ?>

            <?=e(
                $c['fornecedor_nome']
            )?>

        <?php else: ?>

            <span style="color:var(--muted)">
                Sem fornecedor
            </span>

        <?php endif; ?>

    </td>



    <!-- DOCUMENTO -->

    <td>

        <?php if (
            !empty(
                $c['numero_documento']
            )
        ): ?>

            <?=e(
                $c['numero_documento']
            )?>

        <?php else: ?>

            —

        <?php endif; ?>

    </td>



    <!-- ITENS -->

    <td>

        <?=number_format(
            (int)$c['total_itens'],
            0,
            ',',
            '.'
        )?>

    </td>



    <!-- DATA -->

    <td>

        <?=date(
            'd/m/Y',
            strtotime(
                $c['data_compra']
            )
        )?>


        <?php if (
            $c['status'] === 'recebida'
            &&
            !empty(
                $c['data_recebimento']
            )
        ): ?>

            <br>

            <small style="color:var(--muted)">

                Recebida:

                <?=date(
                    'd/m/Y',
                    strtotime(
                        $c['data_recebimento']
                    )
                )?>

            </small>

        <?php endif; ?>

    </td>



    <!-- TOTAL -->

    <td>

        <strong>

            <?=money(
                $c['total']
            )?>

        </strong>

    </td>



    <!-- STATUS -->

    <td>

        <span
            class="
                purchase-status
                <?=e($c['status'])?>
            "
        >

            <?=e($c['status'])?>

        </span>

    </td>



    <!-- AÇÕES -->

    <td>

        <div class="purchase-actions">


            <a
                class="purchase-action"
                href="/admin/compras/nova.php?id=<?=$c['id']?>"
            >
                Abrir
            </a>


            <?php if (
                $c['status'] === 'aberta'
            ): ?>

                <a
                    class="purchase-action"
                    href="/admin/compras/nova.php?id=<?=$c['id']?>"
                >
                    Editar
                </a>

            <?php endif; ?>


        </div>

    </td>


</tr>


<?php endforeach; ?>



<?php if (!$compras): ?>


<tr>

    <td
        colspan="8"
        class="purchase-empty"
    >

        <strong>
            Nenhuma compra encontrada.
        </strong>

        <br><br>

        Comece cadastrando sua primeira compra.

        <br><br>

        <a
            class="btn primary"
            href="/admin/compras/nova.php"
        >
            + Nova compra
        </a>

    </td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>



<?php

include __DIR__
    . '/../../includes/admin_footer.php';

?>