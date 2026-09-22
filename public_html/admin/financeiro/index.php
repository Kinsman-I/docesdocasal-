<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = require_admin();


/* =========================================================
   PERÍODO
   ========================================================= */

$mes =
    $_GET['mes']
    ?? date('Y-m');


if (
    !preg_match(
        '/^\d{4}-\d{2}$/',
        $mes
    )
) {
    $mes = date('Y-m');
}


$inicioMes =
    $mes . '-01';


$fimMes =
    date(
        'Y-m-t',
        strtotime(
            $inicioMes
        )
    );


/* =========================================================
   INDICADORES
   ========================================================= */

$st =
    db()->prepare(
        "
        SELECT

            COALESCE(
                SUM(
                    CASE
                        WHEN tipo = 'entrada'
                         AND status = 'pago'
                         AND data_pagamento BETWEEN ? AND ?
                        THEN valor
                        ELSE 0
                    END
                ),
                0
            ) AS receitas,

            COALESCE(
                SUM(
                    CASE
                        WHEN tipo = 'saida'
                         AND status = 'pago'
                         AND data_pagamento BETWEEN ? AND ?
                        THEN valor
                        ELSE 0
                    END
                ),
                0
            ) AS despesas,

            COALESCE(
                SUM(
                    CASE
                        WHEN tipo = 'entrada'
                         AND status <> 'pago'
                         AND data_competencia BETWEEN ? AND ?
                        THEN valor
                        ELSE 0
                    END
                ),
                0
            ) AS receber,

            COALESCE(
                SUM(
                    CASE
                        WHEN tipo = 'saida'
                         AND status <> 'pago'
                         AND data_competencia BETWEEN ? AND ?
                        THEN valor
                        ELSE 0
                    END
                ),
                0
            ) AS pagar

        FROM lancamentos_financeiros
        "
    );


$st->execute([

    $inicioMes,
    $fimMes,

    $inicioMes,
    $fimMes,

    $inicioMes,
    $fimMes,

    $inicioMes,
    $fimMes

]);


$indicadores =
    $st->fetch();


$receitas =
    (float)$indicadores['receitas'];


$despesas =
    (float)$indicadores['despesas'];


$resultado =
    $receitas
    -
    $despesas;


/* =========================================================
   SALDO GERAL
   ========================================================= */

$saldoGeral =
    (float)db()->query(
        "
        SELECT
            COALESCE(
                SUM(
                    CASE
                        WHEN tipo = 'entrada'
                        THEN valor
                        ELSE -valor
                    END
                ),
                0
            )

        FROM lancamentos_financeiros

        WHERE status = 'pago'
        "
    )->fetchColumn();


/* =========================================================
   ÚLTIMOS LANÇAMENTOS
   ========================================================= */

$ultimos =
    db()->query(
        "
        SELECT
            l.*,
            c.nome AS categoria,
            ct.nome AS conta

        FROM lancamentos_financeiros l

        LEFT JOIN categorias_financeiras c
            ON c.id = l.categoria_id

        JOIN contas_financeiras ct
            ON ct.id = l.conta_id

        ORDER BY
            l.data_competencia DESC,
            l.id DESC

        LIMIT 12
        "
    )->fetchAll();


/* =========================================================
   FORMAS DE PAGAMENTO
   ========================================================= */

$st =
    db()->prepare(
        "
        SELECT
            forma_pagamento,
            SUM(valor) AS total

        FROM lancamentos_financeiros

        WHERE tipo = 'entrada'
          AND status = 'pago'
          AND data_pagamento BETWEEN ? AND ?

        GROUP BY forma_pagamento

        ORDER BY total DESC
        "
    );


$st->execute([
    $inicioMes,
    $fimMes
]);


$formas =
    $st->fetchAll();


$title =
    'Dashboard Financeiro';


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


.finance-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
    flex-wrap:wrap;
    margin-bottom:20px;
}


.finance-metrics{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:14px;
    margin-bottom:24px;
}


.finance-metric{
    background:#fff;
    border:1px solid var(--line);
    border-radius:18px;
    padding:20px;
}


.finance-metric small{
    display:block;
    color:var(--muted);
    margin-bottom:6px;
}


.finance-metric strong{
    display:block;
    font-size:24px;
    color:var(--brown);
}


.finance-grid{
    display:grid;
    grid-template-columns:minmax(0,2fr) minmax(280px,1fr);
    gap:20px;
}


.finance-entry{
    color:var(--success);
    font-weight:800;
}


.finance-exit{
    color:var(--danger);
    font-weight:800;
}


.finance-method{
    display:flex;
    justify-content:space-between;
    gap:15px;
    padding:10px 0;
    border-bottom:1px solid var(--line);
}


@media(max-width:1100px){

    .finance-metrics{
        grid-template-columns:repeat(2,1fr);
    }

}


@media(max-width:850px){

    .finance-grid{
        grid-template-columns:1fr;
    }

}


@media(max-width:600px){

    .finance-metrics{
        grid-template-columns:1fr;
    }

}

</style>


<div class="finance-tabs">

    <a
        class="finance-tab active"
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
        class="finance-tab"
        href="/admin/financeiro/categorias.php"
    >
        Categorias
    </a>

</div>


<div class="finance-head">

    <div>

        <span class="eyebrow">
            FINANCEIRO
        </span>

        <h2>
            Visão financeira
        </h2>

    </div>


    <form method="get">

        <label>

            Mês

            <input
                type="month"
                name="mes"
                value="<?=e($mes)?>"
                onchange="this.form.submit()"
            >

        </label>

    </form>

</div>


<div class="finance-metrics">


    <div class="finance-metric">

        <small>
            Receitas do mês
        </small>

        <strong>
            <?=money($receitas)?>
        </strong>

    </div>


    <div class="finance-metric">

        <small>
            Despesas do mês
        </small>

        <strong>
            <?=money($despesas)?>
        </strong>

    </div>


    <div class="finance-metric">

        <small>
            Resultado
        </small>

        <strong>
            <?=money($resultado)?>
        </strong>

    </div>


    <div class="finance-metric">

        <small>
            A receber
        </small>

        <strong>
            <?=money($indicadores['receber'])?>
        </strong>

    </div>


    <div class="finance-metric">

        <small>
            Saldo geral
        </small>

        <strong>
            <?=money($saldoGeral)?>
        </strong>

    </div>


</div>


<div class="finance-grid">


    <section>

        <div class="card">

            <span class="eyebrow">
                MOVIMENTAÇÕES
            </span>

            <h3>
                Últimos lançamentos
            </h3>


            <div class="table-wrap">


                <table>


                    <thead>

                        <tr>

                            <th>
                                Data
                            </th>

                            <th>
                                Descrição
                            </th>

                            <th>
                                Categoria
                            </th>

                            <th>
                                Conta
                            </th>

                            <th>
                                Valor
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $ultimos
                        as $l
                    ): ?>


                        <tr>


                            <td>

                                <?=date(
                                    'd/m/Y',
                                    strtotime(
                                        $l['data_competencia']
                                    )
                                )?>

                            </td>


                            <td>

                                <strong>
                                    <?=e($l['descricao'])?>
                                </strong>

                                <br>

                                <small>
                                    <?=e($l['origem'])?>
                                </small>

                            </td>


                            <td>

                                <?=e(
                                    $l['categoria']
                                    ?: 'Sem categoria'
                                )?>

                            </td>


                            <td>

                                <?=e($l['conta'])?>

                            </td>


                            <td>

                                <span
                                    class="
                                        <?=$l['tipo'] === 'entrada'
                                            ? 'finance-entry'
                                            : 'finance-exit'
                                        ?>
                                    "
                                >

                                    <?=$l['tipo'] === 'entrada'
                                        ? '+'
                                        : '-'
                                    ?>

                                    <?=money($l['valor'])?>

                                </span>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    <?php if (!$ultimos): ?>


                        <tr>

                            <td
                                colspan="5"
                                style="text-align:center;padding:30px"
                            >

                                Nenhum lançamento registrado.

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>


                </table>


            </div>


            <a
                href="/admin/financeiro/lancamentos.php"
                class="btn outline"
                style="margin-top:14px"
            >
                Ver todos os lançamentos
            </a>

        </div>

    </section>



    <aside>


        <div class="card">

            <span class="eyebrow">
                RECEBIMENTOS
            </span>

            <h3>
                Por forma de pagamento
            </h3>


            <?php foreach (
                $formas
                as $forma
            ): ?>


                <div class="finance-method">

                    <span>
                        <?=e(
                            ucfirst(
                                $forma['forma_pagamento']
                            )
                        )?>
                    </span>

                    <strong>
                        <?=money($forma['total'])?>
                    </strong>

                </div>


            <?php endforeach; ?>


            <?php if (!$formas): ?>

                <p style="color:var(--muted)">
                    Nenhum recebimento no período.
                </p>

            <?php endif; ?>

        </div>

    </aside>


</div>


<?php

include __DIR__
    . '/../../includes/admin_footer.php';

?>