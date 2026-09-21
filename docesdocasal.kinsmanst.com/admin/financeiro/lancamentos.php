<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = require_admin();

$msg = '';
$error = '';


/* =========================================================
   POST
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();


    $tipo =
        $_POST['tipo']
        ?? 'saida';


    $valor =
        (float)str_replace(
            ',',
            '.',
            $_POST['valor']
            ?? '0'
        );


    $descricao =
        trim(
            $_POST['descricao']
            ?? ''
        );


    $conta =
        (int)(
            $_POST['conta_id']
            ?? 0
        );


    $cat =
        (int)(
            $_POST['categoria_id']
            ?? 0
        );


    $forma =
        $_POST['forma_pagamento']
        ?? 'outro';


    $status =
        $_POST['status']
        ?? 'pago';


    $dataCompetencia =
        $_POST['data_competencia']
        ?? date('Y-m-d');


    $dataPagamento =
        $_POST['data_pagamento']
        ?? '';


    if (
        !in_array(
            $tipo,
            [
                'entrada',
                'saida'
            ],
            true
        )
    ) {

        $error =
            'Tipo de lançamento inválido.';

    } elseif ($valor <= 0) {

        $error =
            'Informe um valor maior que zero.';

    } elseif ($descricao === '') {

        $error =
            'Informe uma descrição.';

    } elseif ($conta <= 0) {

        $error =
            'Selecione uma conta financeira.';

    }


    if (!$error) {

        try {

            $st =
                db()->prepare(
                    "
                    INSERT INTO lancamentos_financeiros
                    (
                        conta_id,
                        categoria_id,
                        tipo,
                        origem,
                        descricao,
                        valor,
                        data_competencia,
                        data_pagamento,
                        status,
                        forma_pagamento,
                        usuario_id
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        'manual',
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                    "
                );


            $st->execute([

                $conta,

                $cat ?: null,

                $tipo,

                $descricao,

                $valor,

                $dataCompetencia,

                $status === 'pago'
                    ? (
                        $dataPagamento
                        ?: date('Y-m-d')
                    )
                    : null,

                $status,

                $forma,

                $admin['id']

            ]);


            $id =
                (int)db()->lastInsertId();


            audit(
                'financeiro_lancamento_criado',
                'lancamentos_financeiros',
                $id,
                $descricao
            );


            $msg =
                'Lançamento salvo com sucesso.';


        } catch (Throwable $e) {

            $error =
                'Não foi possível salvar o lançamento.';

        }

    }

}


/* =========================================================
   CONTAS
   ========================================================= */

$accounts =
    db()->query(
        "
        SELECT *
        FROM contas_financeiras
        WHERE ativo = 1
        ORDER BY nome
        "
    )->fetchAll();


/* =========================================================
   CATEGORIAS
   ========================================================= */

$cats =
    db()->query(
        "
        SELECT *
        FROM categorias_financeiras
        WHERE ativo = 1
        ORDER BY natureza, nome
        "
    )->fetchAll();


/* =========================================================
   FILTROS
   ========================================================= */

$tipoFiltro =
    $_GET['tipo']
    ?? '';


$where =
    "
    WHERE 1 = 1
    ";


$params = [];


if (
    in_array(
        $tipoFiltro,
        [
            'entrada',
            'saida'
        ],
        true
    )
) {

    $where .=
        "
        AND l.tipo = ?
        ";

    $params[] =
        $tipoFiltro;

}


/* =========================================================
   LISTAGEM
   ========================================================= */

$sql =
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

    $where

    ORDER BY
        l.data_competencia DESC,
        l.id DESC

    LIMIT 300
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


$title =
    'Lançamentos Financeiros';


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


.launch-layout{
    display:grid;
    grid-template-columns:380px 1fr;
    gap:20px;
    align-items:start;
}


.launch-entry{
    color:var(--success);
    font-weight:800;
}


.launch-exit{
    color:var(--danger);
    font-weight:800;
}


.launch-filters{
    display:flex;
    gap:8px;
    margin-bottom:15px;
}


.launch-filter{
    padding:7px 11px;
    border:1px solid var(--line);
    border-radius:999px;
    background:#fff;
    font-size:12px;
}


.launch-filter.active{
    background:var(--brown);
    color:#fff;
}


@media(max-width:900px){

    .launch-layout{
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
        class="finance-tab active"
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


<div class="launch-layout">


    <aside>


        <div class="card">


            <span class="eyebrow">
                NOVO LANÇAMENTO
            </span>


            <h3>
                Receita ou despesa
            </h3>


            <?php if (!$accounts): ?>


                <div class="alert">

                    Nenhuma conta financeira cadastrada.

                </div>


            <?php else: ?>


                <form method="post">

                    <?=csrf_field()?>


                    <label>

                        Tipo

                        <select name="tipo">

                            <option value="entrada">
                                Entrada
                            </option>

                            <option value="saida">
                                Saída
                            </option>

                        </select>

                    </label>


                    <label>

                        Descrição

                        <input
                            name="descricao"
                            required
                        >

                    </label>


                    <label>

                        Valor

                        <input
                            name="valor"
                            type="number"
                            step=".01"
                            min=".01"
                            required
                        >

                    </label>


                    <label>

                        Conta

                        <select
                            name="conta_id"
                            required
                        >

                            <?php foreach (
                                $accounts
                                as $a
                            ): ?>

                                <option value="<?=$a['id']?>">

                                    <?=e($a['nome'])?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </label>


                    <label>

                        Categoria

                        <select name="categoria_id">

                            <option value="">
                                Sem categoria
                            </option>

                            <?php foreach (
                                $cats
                                as $c
                            ): ?>

                                <option value="<?=$c['id']?>">

                                    <?=e(
                                        $c['natureza']
                                        . ' - '
                                        . $c['nome']
                                    )?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </label>


                    <label>

                        Forma de pagamento

                        <select name="forma_pagamento">

                            <option value="pix">
                                Pix
                            </option>

                            <option value="dinheiro">
                                Dinheiro
                            </option>

                            <option value="cartao">
                                Cartão
                            </option>

                            <option value="transferencia">
                                Transferência
                            </option>

                            <option value="outro">
                                Outro
                            </option>

                        </select>

                    </label>


                    <label>

                        Status

                        <select
                            name="status"
                            id="statusFinanceiro"
                        >

                            <option value="pago">
                                Pago
                            </option>

                            <option value="pendente">
                                Pendente
                            </option>

                        </select>

                    </label>


                    <label>

                        Competência

                        <input
                            type="date"
                            name="data_competencia"
                            value="<?=date('Y-m-d')?>"
                            required
                        >

                    </label>


                    <label id="dataPagamentoWrap">

                        Data do pagamento

                        <input
                            type="date"
                            name="data_pagamento"
                            value="<?=date('Y-m-d')?>"
                        >

                    </label>


                    <button
                        class="btn primary full"
                        type="submit"
                    >
                        Salvar lançamento
                    </button>

                </form>


            <?php endif; ?>


        </div>


    </aside>



    <section>


        <span class="eyebrow">
            MOVIMENTAÇÕES
        </span>


        <h2>
            Lançamentos
        </h2>


        <div class="launch-filters">


            <a
                href="/admin/financeiro/lancamentos.php"
                class="
                    launch-filter
                    <?=$tipoFiltro === ''
                        ? 'active'
                        : ''
                    ?>
                "
            >
                Todos
            </a>


            <a
                href="?tipo=entrada"
                class="
                    launch-filter
                    <?=$tipoFiltro === 'entrada'
                        ? 'active'
                        : ''
                    ?>
                "
            >
                Entradas
            </a>


            <a
                href="?tipo=saida"
                class="
                    launch-filter
                    <?=$tipoFiltro === 'saida'
                        ? 'active'
                        : ''
                    ?>
                "
            >
                Saídas
            </a>


        </div>


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
                            Status
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
                    $rows
                    as $r
                ): ?>


                    <tr>


                        <td>

                            <?=date(
                                'd/m/Y',
                                strtotime(
                                    $r['data_competencia']
                                )
                            )?>

                        </td>


                        <td>

                            <strong>
                                <?=e($r['descricao'])?>
                            </strong>

                            <br>

                            <small>
                                <?=e($r['origem'])?>
                            </small>

                        </td>


                        <td>

                            <?=e(
                                $r['categoria']
                                ?: 'Sem categoria'
                            )?>

                        </td>


                        <td>

                            <span class="badge">

                                <?=e($r['status'])?>

                            </span>

                        </td>


                        <td>

                            <?=e($r['conta'])?>

                        </td>


                        <td>

                            <span
                                class="
                                    <?=$r['tipo'] === 'entrada'
                                        ? 'launch-entry'
                                        : 'launch-exit'
                                    ?>
                                "
                            >

                                <?=$r['tipo'] === 'entrada'
                                    ? '+'
                                    : '-'
                                ?>

                                <?=money(
                                    $r['valor']
                                )?>

                            </span>

                        </td>


                    </tr>


                <?php endforeach; ?>


                <?php if (!$rows): ?>


                    <tr>

                        <td
                            colspan="6"
                            style="text-align:center;padding:30px"
                        >

                            Nenhum lançamento encontrado.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>


            </table>


        </div>


    </section>


</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function(){

        const status =
            document.getElementById(
                'statusFinanceiro'
            );

        const data =
            document.getElementById(
                'dataPagamentoWrap'
            );


        if (!status || !data) {
            return;
        }


        function controlar(){

            data.style.display =
                status.value === 'pago'
                    ? ''
                    : 'none';

        }


        status.addEventListener(
            'change',
            controlar
        );


        controlar();

    }
);

</script>


<?php

include __DIR__
    . '/../../includes/admin_footer.php';

?>