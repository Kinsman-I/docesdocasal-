<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = require_admin();


$id =
    (int)(
        $_GET['id']
        ?? 0
    );


/* =========================================================
   ITENS
   ========================================================= */

$itens =
    db()->query(
        "
        SELECT
            i.id,
            i.nome,
            i.estoque_atual,
            u.sigla

        FROM itens_estoque i

        JOIN unidades_medida u
            ON u.id = i.unidade_id

        ORDER BY i.nome
        "
    )->fetchAll();


$item =
    null;


if ($id > 0) {

    $st =
        db()->prepare(
            "
            SELECT
                i.*,
                u.sigla

            FROM itens_estoque i

            JOIN unidades_medida u
                ON u.id = i.unidade_id

            WHERE i.id = ?

            LIMIT 1
            "
        );


    $st->execute([
        $id
    ]);


    $item =
        $st->fetch();

}


/* =========================================================
   MOVIMENTAÇÕES
   ========================================================= */

$movimentacoes = [];


if ($item) {

    $st =
        db()->prepare(
            "
            SELECT
                m.*

            FROM estoque_movimentacoes m

            WHERE m.item_estoque_id = ?

            ORDER BY
                m.id DESC

            LIMIT 200
            "
        );


    $st->execute([
        $id
    ]);


    $movimentacoes =
        $st->fetchAll();

}


/* =========================================================
   SOMATÓRIOS
   ========================================================= */

$totalEntradas = 0;

$totalSaidas = 0;


foreach (
    $movimentacoes
    as $mov
) {

    if (
        $mov['tipo'] === 'entrada'
    ) {

        $totalEntradas +=
            (float)$mov['quantidade'];

    }


    if (
        $mov['tipo'] === 'saida'
    ) {

        $totalSaidas +=
            (float)$mov['quantidade'];

    }

}


$title =
    'Movimentações de Estoque';


include __DIR__
    . '/../../includes/admin_header.php';

?>


<style>

.movement-metrics{
    display:grid;
    grid-template-columns:
        repeat(3,1fr);
    gap:14px;
    margin-bottom:18px;
}

.movement-card{
    background:#fff;
    border:1px solid var(--line);
    border-radius:16px;
    padding:18px;
}

.movement-card small{
    display:block;
    color:var(--muted);
}

.movement-card strong{
    display:block;
    font-size:24px;
    margin-top:5px;
}

.movement-entry{
    color:var(--success);
    font-weight:800;
}

.movement-exit{
    color:var(--danger);
    font-weight:800;
}

.movement-reference{
    font-size:12px;
    color:var(--muted);
}

@media(max-width:700px){

    .movement-metrics{
        grid-template-columns:1fr;
    }

}

</style>


<div class="card">

    <span class="eyebrow">
        RASTREABILIDADE
    </span>


    <h2>
        Movimentações de estoque
    </h2>


    <form method="get">

        <label>

            Item

            <select
                name="id"
                onchange="this.form.submit()"
            >

                <option value="">
                    Selecione
                </option>


                <?php foreach (
                    $itens
                    as $i
                ): ?>

                    <option
                        value="<?=$i['id']?>"

                        <?=$id === (int)$i['id']
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?=e($i['nome'])?>

                    </option>

                <?php endforeach; ?>

            </select>

        </label>

    </form>

</div>



<?php if ($item): ?>


<div style="margin-top:20px">


    <span class="eyebrow">
        <?=e($item['nome'])?>
    </span>


    <h2>
        Histórico
    </h2>


    <div class="movement-metrics">


        <div class="movement-card">

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

                <?=e($item['sigla'])?>

            </strong>

        </div>


        <div class="movement-card">

            <small>
                Total de entradas
            </small>

            <strong class="movement-entry">

                +

                <?=number_format(
                    $totalEntradas,
                    3,
                    ',',
                    '.'
                )?>

                <?=e($item['sigla'])?>

            </strong>

        </div>


        <div class="movement-card">

            <small>
                Total de saídas
            </small>

            <strong class="movement-exit">

                -

                <?=number_format(
                    $totalSaidas,
                    3,
                    ',',
                    '.'
                )?>

                <?=e($item['sigla'])?>

            </strong>

        </div>


    </div>



    <div class="table-wrap">


        <table>


            <thead>

                <tr>

                    <th>
                        Data
                    </th>

                    <th>
                        Tipo
                    </th>

                    <th>
                        Quantidade
                    </th>

                    <th>
                        Custo
                    </th>

                    <th>
                        Origem
                    </th>

                    <th>
                        Referência
                    </th>

                    <th>
                        Observação
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php foreach (
                $movimentacoes
                as $m
            ): ?>


                <tr>


                    <td>

                        <?=date(
                            'd/m/Y H:i',
                            strtotime(
                                $m['criado_em']
                            )
                        )?>

                    </td>


                    <td>


                        <?php if (
                            $m['tipo'] === 'entrada'
                        ): ?>

                            <span class="movement-entry">
                                ENTRADA
                            </span>

                        <?php else: ?>

                            <span class="movement-exit">
                                SAÍDA
                            </span>

                        <?php endif; ?>


                    </td>


                    <td>

                        <strong>

                            <?=$m['tipo'] === 'entrada'
                                ? '+'
                                : '-'
                            ?>

                            <?=number_format(
                                (float)$m['quantidade'],
                                3,
                                ',',
                                '.'
                            )?>

                            <?=e($item['sigla'])?>

                        </strong>

                    </td>


                    <td>

                        <?php if (
                            $m['custo_unitario'] !== null
                        ): ?>

                            <?=money(
                                $m['custo_unitario']
                            )?>

                        <?php else: ?>

                            —

                        <?php endif; ?>

                    </td>


                    <td>

                        <span class="badge">

                            <?=e(
                                ucfirst(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $m['referencia_tipo']
                                        ?? 'manual'
                                    )
                                )
                            )?>

                        </span>

                    </td>


                    <td>

                        <?php if (
                            !empty(
                                $m['referencia_id']
                            )
                        ): ?>

                            #<?=$m['referencia_id']?>

                        <?php else: ?>

                            —

                        <?php endif; ?>

                    </td>


                    <td>

                        <?=e(
                            $m['observacao']
                            ?? ''
                        )?>

                    </td>


                </tr>


            <?php endforeach; ?>


            <?php if (!$movimentacoes): ?>


                <tr>

                    <td
                        colspan="7"
                        style="
                            padding:30px;
                            text-align:center;
                        "
                    >

                        Nenhuma movimentação registrada.

                    </td>

                </tr>


            <?php endif; ?>


            </tbody>


        </table>


    </div>


</div>


<?php endif; ?>


<?php

include __DIR__
    . '/../../includes/admin_footer.php';

?>