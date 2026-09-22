<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = require_admin();


/* =========================================================
   NECESSIDADES
   ========================================================= */

$rows = db()->query("
    SELECT
        i.id,
        i.nome,
        i.tipo,
        i.estoque_atual,
        i.estoque_minimo,
        i.custo_medio,
        u.sigla,

        CASE
            WHEN i.estoque_minimo > i.estoque_atual
            THEN i.estoque_minimo - i.estoque_atual
            ELSE 0
        END AS necessidade

    FROM itens_estoque i

    JOIN unidades_medida u
        ON u.id = i.unidade_id

    WHERE i.ativo = 1
      AND i.estoque_atual <= i.estoque_minimo

    ORDER BY
        necessidade DESC,
        i.nome
")->fetchAll();


$valorEstimado = 0;

foreach ($rows as $r) {

    $valorEstimado +=
        (float)$r['necessidade']
        *
        (float)$r['custo_medio'];
}


$title = 'Necessidade de compra';

include __DIR__ . '/../../includes/admin_header.php';

?>

<style>

.need-metrics{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:14px;
    margin-bottom:24px;
}

.need-card{
    background:#fff;
    border:1px solid var(--line);
    border-radius:18px;
    padding:20px;
}

.need-card small{
    display:block;
    color:var(--muted);
    margin-bottom:6px;
}

.need-card strong{
    font-size:25px;
    color:var(--brown);
}

.need-danger{
    font-weight:800;
    color:var(--danger);
}

@media(max-width:700px){

    .need-metrics{
        grid-template-columns:1fr;
    }
}

</style>


<div style="display:flex;justify-content:space-between;gap:15px;align-items:center;flex-wrap:wrap;margin-bottom:20px">

    <div>

        <span class="eyebrow">
            PLANEJAMENTO
        </span>

        <h2>
            Necessidade de compra
        </h2>

        <p style="color:var(--muted)">
            Itens que atingiram ou ficaram abaixo do estoque mínimo.
        </p>

    </div>


    <a
        href="/admin/compras/nova.php"
        class="btn primary"
    >
        + Nova compra
    </a>

</div>


<div class="need-metrics">

    <div class="need-card">

        <small>
            Itens para reposição
        </small>

        <strong>
            <?=count($rows)?>
        </strong>

    </div>


    <div class="need-card">

        <small>
            Valor estimado
        </small>

        <strong>
            <?=money($valorEstimado)?>
        </strong>

    </div>


    <div class="need-card">

        <small>
            Situação
        </small>

        <strong>
            <?=count($rows) ? 'Comprar' : 'Normal'?>
        </strong>

    </div>

</div>


<div class="table-wrap">

<table>

<thead>

<tr>
    <th>Item</th>
    <th>Tipo</th>
    <th>Estoque atual</th>
    <th>Mínimo</th>
    <th>Sugestão</th>
    <th>Custo estimado</th>
</tr>

</thead>

<tbody>


<?php foreach ($rows as $r): ?>

<tr>

    <td>
        <strong><?=e($r['nome'])?></strong>
    </td>

    <td>
        <?=e(str_replace('_', ' ', $r['tipo']))?>
    </td>

    <td class="need-danger">

        <?=number_format(
            (float)$r['estoque_atual'],
            3,
            ',',
            '.'
        )?>

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

        <strong>

            <?=number_format(
                (float)$r['necessidade'],
                3,
                ',',
                '.'
            )?>

            <?=e($r['sigla'])?>

        </strong>

    </td>

    <td>

        <?=money(
            (float)$r['necessidade']
            *
            (float)$r['custo_medio']
        )?>

    </td>

</tr>

<?php endforeach; ?>


<?php if (!$rows): ?>

<tr>

    <td colspan="6" style="text-align:center;padding:40px">

        <strong>
            Nenhuma necessidade de compra no momento.
        </strong>

        <br><br>

        Todos os itens estão acima do estoque mínimo.

    </td>

</tr>

<?php endif; ?>


</tbody>

</table>

</div>


<?php

include __DIR__ . '/../../includes/admin_footer.php';

?>