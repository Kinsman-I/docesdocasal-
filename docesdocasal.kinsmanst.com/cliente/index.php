<?php

/* =========================================================
   DOCES DO CASAL
   Área do cliente / Programa de fidelidade
   ========================================================= */

require_once __DIR__ . '/../includes/bootstrap.php';

$u = require_login();

$msg = '';
$error = '';


/* =========================================================
   CONFIGURAÇÃO DO PROGRAMA
   =========================================================
   Futuramente podemos colocar isso no Admin.
   ========================================================= */

$metaBrownie = 100;


/* =========================================================
   RESGATAR BROWNIE GRÁTIS
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'resgatar_brownie') {

        try {

            db()->beginTransaction();


            /* =============================================
               RECALCULA SALDO DENTRO DA TRANSAÇÃO
               ============================================= */

            $st = db()->prepare("
                SELECT
                    COALESCE(
                        SUM(
                            CASE

                                WHEN tipo = 'credito'
                                    THEN pontos

                                WHEN tipo = 'debito'
                                    THEN -pontos

                                WHEN tipo = 'ajuste'
                                    THEN pontos

                                ELSE 0

                            END
                        ),
                        0
                    )
                FROM fidelidade_movimentacoes
                WHERE usuario_id = ?
            ");

            $st->execute([
                $u['id']
            ]);

            $saldoAtual = (int) $st->fetchColumn();


            /* =============================================
               VALIDA SE TEM PONTOS
               ============================================= */

            if ($saldoAtual < $metaBrownie) {

                throw new RuntimeException(
                    'Você ainda não possui pontos suficientes para resgatar o brownie.'
                );

            }


            /* =============================================
               REGISTRA O RESGATE
               ============================================= */

            $st = db()->prepare("
                INSERT INTO fidelidade_movimentacoes
                (
                    usuario_id,
                    pedido_id,
                    tipo,
                    pontos,
                    descricao
                )
                VALUES
                (
                    ?,
                    NULL,
                    'debito',
                    ?,
                    ?
                )
            ");

            $st->execute([
                $u['id'],
                $metaBrownie,
                'Resgate de brownie grátis'
            ]);


            db()->commit();


            $msg = 'Seu brownie grátis foi resgatado! Apresente o resgate ao realizar seu próximo pedido.';


        } catch (Throwable $e) {

            if (db()->inTransaction()) {
                db()->rollBack();
            }

            $error = $e->getMessage();

        }

    }

}


/* =========================================================
   PEDIDOS DO CLIENTE
   ========================================================= */

$st = db()->prepare("
    SELECT
        id,
        codigo,
        status,
        forma_pagamento,
        status_pagamento,
        total,
        criado_em

    FROM pedidos

    WHERE usuario_id = ?

    ORDER BY criado_em DESC

    LIMIT 10
");

$st->execute([
    $u['id']
]);

$orders = $st->fetchAll();


/* =========================================================
   SALDO DE PONTOS
   =========================================================
   credito = soma
   debito  = subtrai
   ajuste  = utiliza o valor gravado
   ========================================================= */

$st = db()->prepare("
    SELECT
        COALESCE(
            SUM(
                CASE

                    WHEN tipo = 'credito'
                        THEN pontos

                    WHEN tipo = 'debito'
                        THEN -pontos

                    WHEN tipo = 'ajuste'
                        THEN pontos

                    ELSE 0

                END
            ),
            0
        )

    FROM fidelidade_movimentacoes

    WHERE usuario_id = ?
");

$st->execute([
    $u['id']
]);

$points = max(
    0,
    (int) $st->fetchColumn()
);


/* =========================================================
   HISTÓRICO DE PONTOS
   ========================================================= */

$st = db()->prepare("
    SELECT
        id,
        pedido_id,
        tipo,
        pontos,
        descricao,
        criado_em

    FROM fidelidade_movimentacoes

    WHERE usuario_id = ?

    ORDER BY criado_em DESC

    LIMIT 15
");

$st->execute([
    $u['id']
]);

$movimentacoes = $st->fetchAll();


/* =========================================================
   CÁLCULOS DA RECOMPENSA
   ========================================================= */

$temRecompensa = $points >= $metaBrownie;

$progresso = min(
    100,
    ($points / $metaBrownie) * 100
);

$faltam = max(
    0,
    $metaBrownie - $points
);


/*
 * Quantidade de brownies que o saldo permitiria resgatar.
 * Exemplo:
 * 250 pontos = 2 brownies disponíveis.
 */
$recompensasDisponiveis = floor(
    $points / $metaBrownie
);


/* =========================================================
   PÁGINA
   ========================================================= */

$title = 'Meu Casal';

include __DIR__ . '/../includes/header.php';

?>


<style>

/* =========================================================
   MEU CASAL
   ========================================================= */

.meu-casal{
    padding:70px 0 90px;
    background:#FFF8F0;
}


.meu-casal-header{
    margin-bottom:35px;
}


.meu-casal-header h1{
    margin:5px 0 8px;

    font-family:
        Georgia,
        "Times New Roman",
        serif;

    font-size:clamp(
        42px,
        5vw,
        62px
    );

    font-weight:500;

    color:#4A2718;
}


.meu-casal-header p{
    margin:0;

    color:#80665c;

    font-size:17px;
}


/* =========================================================
   GRID PRINCIPAL
   ========================================================= */

.loyalty-dashboard{
    display:grid;

    grid-template-columns:
        1.15fr
        .85fr;

    gap:24px;

    align-items:stretch;
}


/* =========================================================
   CARTÃO DE FIDELIDADE
   ========================================================= */

.loyalty-premium-card{
    position:relative;

    overflow:hidden;

    min-height:420px;

    padding:38px;

    border-radius:32px;

    background:
        linear-gradient(
            145deg,
            #4A2718,
            #6B3826
        );

    color:#fff;

    box-shadow:
        0 25px 60px
        rgba(74,39,24,.18);
}


/* Detalhe decorativo */
.loyalty-premium-card::before{
    content:"";

    position:absolute;

    width:320px;
    height:320px;

    right:-130px;
    top:-130px;

    border-radius:50%;

    background:
        rgba(201,143,130,.20);
}


.loyalty-premium-card::after{
    content:"♡";

    position:absolute;

    right:35px;
    bottom:20px;

    font-family:Georgia,serif;

    font-size:115px;

    color:
        rgba(255,255,255,.05);
}


/* =========================================================
   TOPO DO CARTÃO
   ========================================================= */

.loyalty-card-top{
    position:relative;

    z-index:2;

    display:flex;

    justify-content:space-between;

    gap:20px;

    align-items:flex-start;
}


.loyalty-brand{
    font-family:
        Georgia,
        serif;

    font-size:24px;
}


.loyalty-chip{
    padding:7px 13px;

    border-radius:999px;

    background:
        rgba(255,255,255,.12);

    font-size:11px;

    letter-spacing:.12em;

    font-weight:700;
}


/* =========================================================
   PONTOS
   ========================================================= */

.points-area{
    position:relative;

    z-index:2;

    margin-top:55px;
}


.points-area small{
    display:block;

    color:#E9CFC6;

    font-size:13px;

    letter-spacing:.08em;

    text-transform:uppercase;
}


.points-number{
    display:flex;

    align-items:baseline;

    gap:8px;

    margin-top:4px;
}


.points-number strong{
    font-family:
        Georgia,
        serif;

    font-size:76px;

    font-weight:500;

    line-height:1;
}


.points-number span{
    color:#E9CFC6;

    font-size:17px;
}


/* =========================================================
   PROGRESSO
   ========================================================= */

.loyalty-progress-area{
    position:relative;

    z-index:2;

    margin-top:35px;
}


.loyalty-progress-label{
    display:flex;

    justify-content:space-between;

    gap:20px;

    margin-bottom:10px;

    color:#F2DCD4;

    font-size:13px;
}


.loyalty-progress{
    height:13px;

    overflow:hidden;

    border-radius:999px;

    background:
        rgba(255,255,255,.15);
}


.loyalty-progress span{
    display:block;

    height:100%;

    width:0;

    border-radius:999px;

    background:
        linear-gradient(
            90deg,
            #E9B7AE,
            #FFF0EB
        );

    transition:
        width 1.2s ease;
}


/* =========================================================
   TEXTO ABAIXO DA BARRA
   ========================================================= */

.loyalty-message{
    position:relative;

    z-index:2;

    margin-top:18px;

    font-size:15px;

    line-height:1.6;

    color:#F6E8E3;
}


/* =========================================================
   RECOMPENSA
   ========================================================= */

.reward-card{
    position:relative;

    display:flex;

    flex-direction:column;

    justify-content:center;

    padding:34px;

    border:

        1px solid
        #E8D4CC;

    border-radius:32px;

    background:#fff;

    box-shadow:
        0 20px 50px
        rgba(74,39,24,.08);
}


.reward-icon{
    display:grid;

    place-items:center;

    width:72px;
    height:72px;

    border-radius:50%;

    background:#F7E3E0;

    font-size:32px;

    margin-bottom:20px;
}


.reward-card h2{
    margin:0;

    font-family:
        Georgia,
        serif;

    font-size:34px;

    font-weight:500;

    color:#4A2718;
}


.reward-card p{
    color:#80665c;

    line-height:1.7;

    margin-bottom:25px;
}


/* =========================================================
   RECOMPENSA DISPONÍVEL
   ========================================================= */

.reward-ready{
    border-color:#C98F82;

    background:
        linear-gradient(
            145deg,
            #fff,
            #FFF3F0
        );
}


.reward-ready h2{
    color:#9C5D52;
}


.reward-count{
    display:inline-flex;

    width:max-content;

    margin-bottom:16px;

    padding:7px 12px;

    border-radius:999px;

    background:#F4DBD6;

    color:#8F5047;

    font-size:12px;

    font-weight:800;
}


/* =========================================================
   BOTÃO RESGATE
   ========================================================= */

.reward-button{
    width:100%;

    min-height:55px;

    border:0;

    border-radius:999px;

    background:#4A2718;

    color:#fff;

    font-weight:800;

    font-size:16px;

    cursor:pointer;

    transition:.2s;
}


.reward-button:hover{
    transform:translateY(-2px);

    background:#B77A50;
}


/* =========================================================
   BLOCO DE INFORMAÇÕES
   ========================================================= */

.customer-sections{
    display:grid;

    grid-template-columns:
        1fr
        1fr;

    gap:24px;

    margin-top:35px;
}


.customer-box{
    background:#fff;

    border:
        1px solid
        #E8D4CC;

    border-radius:26px;

    padding:26px;

    box-shadow:
        0 12px 35px
        rgba(74,39,24,.05);
}


.customer-box h2{
    margin-top:0;

    font-family:Georgia,serif;

    font-weight:500;

    color:#4A2718;
}

.payment-status-link{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    padding:7px 11px;
    border-radius:999px;
    background:#FFF1E8;
    color:#8F5047;
    text-decoration:none;
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
}

.payment-status-link:hover,
.payment-status-link:focus{
    background:#F4DBD6;
    color:#4A2718;
}

.payment-status-note{
    display:block;
    margin-top:6px;
    color:#9A8177;
    font-size:12px;
}


/* =========================================================
   HISTÓRICO DE PONTOS
   ========================================================= */

.point-history-item{
    display:flex;

    justify-content:space-between;

    gap:20px;

    padding:14px 0;

    border-bottom:
        1px solid
        #F0E3DE;
}


.point-history-item:last-child{
    border-bottom:0;
}


.point-history-text strong{
    display:block;

    color:#4A2718;
}


.point-history-text small{
    display:block;

    margin-top:4px;

    color:#9A8177;
}


.point-value{
    font-weight:800;

    white-space:nowrap;
}


.point-value.credit{
    color:#2F7D4A;
}


.point-value.debit{
    color:#A2392F;
}


.point-value.adjust{
    color:#B77A50;
}


/* =========================================================
   ALERTAS
   ========================================================= */

.loyalty-alert{
    margin-bottom:25px;

    padding:15px 18px;

    border-radius:15px;

    line-height:1.5;
}


.loyalty-alert.success{
    background:#E8F4EC;

    color:#25603A;
}


.loyalty-alert.error{
    background:#F8DEDB;

    color:#7D2119;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media(max-width:900px){

    .loyalty-dashboard{
        grid-template-columns:1fr;
    }


    .customer-sections{
        grid-template-columns:1fr;
    }

}


@media(max-width:620px){

    .meu-casal{
        padding:50px 0 70px;
    }


    .loyalty-premium-card{
        min-height:390px;

        padding:26px;
    }


    .points-number strong{
        font-size:62px;
    }


    .reward-card{
        padding:27px;
    }

}

</style>



<section class="meu-casal">

<div class="container">


    <!-- =====================================================
         CABEÇALHO
         ===================================================== -->

    <div class="meu-casal-header">

        <span class="eyebrow">
            MEU CASAL
        </span>

        <h1>
            Olá, <?=e($u['nome'])?>
        </h1>

        <p>
            Seus pedidos, pontos e recompensas
            em um só lugar.
        </p>

    </div>


    <!-- =====================================================
         MENSAGENS
         ===================================================== -->

    <?php if ($msg): ?>

        <div class="loyalty-alert success">

            <?=e($msg)?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="loyalty-alert error">

            <?=e($error)?>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         FIDELIDADE
         ===================================================== -->

    <div class="loyalty-dashboard">


        <!-- =================================================
             CARTÃO
             ================================================= -->

        <div class="loyalty-premium-card">


            <div class="loyalty-card-top">

                <div class="loyalty-brand">
                    Doces do Casal
                </div>


                <span class="loyalty-chip">
                    MEU CASAL
                </span>

            </div>


            <!-- PONTOS -->

            <div class="points-area">

                <small>
                    Seus pontos
                </small>


                <div class="points-number">

                    <strong>
                        <?=$points?>
                    </strong>

                    <span>
                        pontos
                    </span>

                </div>

            </div>


            <!-- PROGRESSO -->

            <div class="loyalty-progress-area">


                <div class="loyalty-progress-label">

                    <span>
                        Próximo brownie grátis
                    </span>

                    <strong>
                        <?=$points?>
                        /
                        <?=$metaBrownie?>
                    </strong>

                </div>


                <div class="loyalty-progress">

                    <span
                        id="loyaltyProgress"
                        data-progress="<?=number_format(
                            $progresso,
                            2,
                            '.',
                            ''
                        )?>"
                    ></span>

                </div>


                <div class="loyalty-message">


                    <?php if ($temRecompensa): ?>

                        Você já alcançou a meta.
                        Seu brownie está esperando por você. ♡

                    <?php else: ?>

                        Faltam

                        <strong>
                            <?=$faltam?> pontos
                        </strong>

                        para você ganhar
                        seu próximo brownie.

                    <?php endif; ?>


                </div>

            </div>

        </div>



        <!-- =================================================
             RECOMPENSA
             ================================================= -->

        <div
            class="reward-card
            <?=$temRecompensa ? 'reward-ready' : ''?>"
        >


            <div class="reward-icon">

                <?=$temRecompensa ? '🍫' : '♡'?>

            </div>


            <?php if ($temRecompensa): ?>


                <span class="reward-count">

                    <?=$recompensasDisponiveis?>

                    <?=

                        $recompensasDisponiveis === 1
                            ? 'recompensa disponível'
                            : 'recompensas disponíveis'

                    ?>

                </span>


                <h2>
                    Você ganhou um brownie!
                </h2>


                <p>

                    Você completou

                    <strong>
                        <?=$metaBrownie?> pontos
                    </strong>

                    e já pode resgatar
                    um brownie grátis.

                </p>


                <form method="post">

                    <?=csrf_field()?>


                    <input
                        type="hidden"
                        name="acao"
                        value="resgatar_brownie"
                    >


                    <button
                        type="submit"
                        class="reward-button"
                        onclick="
                            return confirm(
                                'Deseja usar <?= $metaBrownie ?> pontos para resgatar um brownie grátis?'
                            )
                        "
                    >
                        Resgatar meu brownie
                    </button>

                </form>


            <?php else: ?>


                <h2>
                    Seu próximo mimo está chegando
                </h2>


                <p>

                    Continue acumulando pontos.

                    Quando chegar a

                    <strong>
                        <?=$metaBrownie?>
                    </strong>

                    você ganha um brownie por nossa conta.

                </p>


                <a
                    href="/#cardapio"
                    class="btn outline full"
                >
                    Escolher meus doces
                </a>


            <?php endif; ?>


        </div>

    </div>



    <!-- =====================================================
         PEDIDOS + HISTÓRICO
         ===================================================== -->

    <div class="customer-sections">


        <!-- =================================================
             PEDIDOS
             ================================================= -->

        <div class="customer-box">

            <span class="eyebrow">
                MEUS PEDIDOS
            </span>

            <h2>
                Últimos pedidos
            </h2>


            <?php if ($orders): ?>


                <div class="table-wrap">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Pedido
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Total
                                </th>

                                <th>
                                    Data
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($orders as $o): ?>

                            <?php
                            $statusPagamento = strtolower(
                                (string)($o['status_pagamento'] ?? '')
                            );

                            $formaPagamento = strtolower(
                                (string)($o['forma_pagamento'] ?? '')
                            );

                            $pixPendente =
                                $formaPagamento === 'pix'
                                && in_array(
                                    $statusPagamento,
                                    [
                                        'pendente',
                                        'aguardando_confirmacao'
                                    ],
                                    true
                                )
                                && $o['status'] !== 'cancelado';
                            ?>


                            <tr>

                                <td>

                                    <?=e(
                                        $o['codigo']
                                        ?: '#' . $o['id']
                                    )?>

                                </td>


                                <td>

                                    <?php if ($pixPendente): ?>

                                        <a
                                            class="payment-status-link"
                                            href="/cliente/pagamento.php?id=<?=(int)$o['id']?>"
                                        >
                                            Aguardando pagamento
                                        </a>

                                        <small class="payment-status-note">
                                            Clique para voltar ao Pix
                                        </small>

                                    <?php else: ?>

                                        <span class="badge">

                                            <?=e($o['status'])?>

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?=money(
                                        $o['total']
                                    )?>

                                </td>


                                <td>

                                    <?=date(
                                        'd/m/Y',
                                        strtotime(
                                            $o['criado_em']
                                        )
                                    )?>

                                </td>

                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <p>
                    Você ainda não realizou nenhum pedido.
                </p>


                <a
                    class="btn primary"
                    href="/#cardapio"
                >
                    Fazer meu primeiro pedido
                </a>


            <?php endif; ?>


        </div>



        <!-- =================================================
             HISTÓRICO DOS PONTOS
             ================================================= -->

        <div class="customer-box">

            <span class="eyebrow">
                FIDELIDADE
            </span>

            <h2>
                Histórico de pontos
            </h2>


            <?php if ($movimentacoes): ?>


                <?php foreach ($movimentacoes as $m): ?>


                    <div class="point-history-item">


                        <div class="point-history-text">

                            <strong>

                                <?=e(
                                    $m['descricao']
                                    ?: 'Movimentação de pontos'
                                )?>

                            </strong>


                            <small>

                                <?=date(
                                    'd/m/Y H:i',
                                    strtotime(
                                        $m['criado_em']
                                    )
                                )?>

                            </small>

                        </div>


                        <?php

                        $classePonto =
                            $m['tipo'] === 'credito'
                                ? 'credit'
                                : (
                                    $m['tipo'] === 'debito'
                                        ? 'debit'
                                        : 'adjust'
                                );

                        $sinal =
                            $m['tipo'] === 'debito'
                                ? '-'
                                : '+';

                        ?>


                        <span
                            class="
                                point-value
                                <?=$classePonto?>
                            "
                        >

                            <?=$sinal?>

                            <?=abs(
                                (int)$m['pontos']
                            )?>

                        </span>


                    </div>


                <?php endforeach; ?>


            <?php else: ?>


                <p>
                    Seus pontos aparecerão aqui
                    depois das suas compras.
                </p>


            <?php endif; ?>


        </div>

    </div>



</div>

</section>



<!-- =========================================================
     ANIMAÇÃO DA BARRA
     ========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function(){

        const progress =
            document.getElementById(
                'loyaltyProgress'
            );

        if(progress){

            const value =
                parseFloat(
                    progress.dataset.progress
                    || 0
                );

            /*
             * Pequeno atraso para a animação
             * aparecer ao entrar na página.
             */
            setTimeout(
                function(){

                    progress.style.width =
                        Math.min(
                            100,
                            value
                        )
                        + '%';

                },
                250
            );

        }

    }
);

</script>


<?php

include __DIR__ . '/../includes/footer.php';

?>
