<?php

/* =========================================================
   DOCES DO CASAL
   ADMINISTRAÇÃO DE PEDIDOS
   + PIX
   + FIDELIDADE
   + ITENS/SABORES DO PEDIDO
   ========================================================= */

require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = require_admin();


/* =========================================================
   ALTERAÇÃO DO STATUS DO PEDIDO
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $id =
        (int)(
            $_POST['id']
            ?? 0
        );

    $acao =
        $_POST['acao']
        ?? 'status_pedido';



    /* =====================================================
       EXCLUSÃO SEGURA DO PEDIDO
       - somente pedidos cancelados podem ser excluídos
       - ao cancelar, o estoque já é devolvido pela regra atual
       ===================================================== */

    if ($acao === 'excluir_pedido') {

        try {

            db()->beginTransaction();

            $st = db()->prepare(
                "
                SELECT
                    id,
                    codigo,
                    status
                FROM pedidos
                WHERE id = ?
                FOR UPDATE
                "
            );

            $st->execute([$id]);
            $pedidoExcluir = $st->fetch();

            if (!$pedidoExcluir) {
                throw new RuntimeException('Pedido não encontrado.');
            }

            if ($pedidoExcluir['status'] !== 'cancelado') {
                throw new RuntimeException(
                    'Por segurança, cancele o pedido antes de excluí-lo.'
                );
            }

            // Remove movimentações de fidelidade ligadas ao pedido,
            // evitando deixar pontos de um pedido apagado.
            db()->prepare(
                "DELETE FROM fidelidade_movimentacoes WHERE pedido_id = ?"
            )->execute([$id]);

            // pedido_itens e histórico possuem ON DELETE CASCADE.
            db()->prepare(
                "DELETE FROM pedidos WHERE id = ?"
            )->execute([$id]);

            db()->commit();

            audit(
                'pedido_excluido',
                'pedidos',
                $id,
                'Pedido ' . ($pedidoExcluir['codigo'] ?: ('#' . $id)) . ' excluído pelo administrador'
            );

            $_SESSION['admin_flash_success'] = 'Pedido excluído com sucesso.';

        } catch (Throwable $e) {

            if (db()->inTransaction()) {
                db()->rollBack();
            }

            $_SESSION['admin_flash_error'] = $e->getMessage();
        }

        header('Location:/admin/pedidos/');
        exit;
    }


    /* =====================================================
       CONFIRMAÇÃO MANUAL DO PIX
       ===================================================== */

    if ($acao === 'confirmar_pix') {

        try {

            db()->beginTransaction();

            $st =
                db()->prepare(
                    "
                    SELECT
                        id,
                        codigo,
                        forma_pagamento,
                        status_pagamento

                    FROM pedidos

                    WHERE id = ?

                    FOR UPDATE
                    "
                );

            $st->execute([
                $id
            ]);

            $pedido =
                $st->fetch();


            if (
                !$pedido
                ||
                $pedido['forma_pagamento'] !== 'pix'
            ) {

                throw new RuntimeException(
                    'Pedido Pix não encontrado.'
                );
            }


            if (
                $pedido['status_pagamento']
                !==
                'pago'
            ) {

                db()->prepare(
                    "
                    UPDATE pedidos

                    SET
                        status_pagamento = 'pago',
                        pago_em = NOW(),
                        atualizado_em = NOW()

                    WHERE id = ?
                    "
                )->execute([
                    $id
                ]);
            }


            db()->commit();


            audit(
                'pix_confirmado_admin',
                'pedidos',
                $id,
                'Pagamento Pix confirmado manualmente'
            );


        } catch (Throwable $e) {

            if (
                db()->inTransaction()
            ) {

                db()->rollBack();

            }

        }


        header(
            'Location:/admin/pedidos/'
        );

        exit;
    }



    /* =====================================================
       ALTERAÇÃO DO STATUS DO PEDIDO
       ===================================================== */

    $status =
        $_POST['status']
        ?? '';


    $allowed = [
        'aguardando',
        'confirmado',
        'preparando',
        'pronto',
        'saiu_entrega',
        'concluido',
        'cancelado'
    ];


    if (
        $acao === 'status_pedido'
        &&
        in_array(
            $status,
            $allowed,
            true
        )
    ) {

        try {

            db()->beginTransaction();


            $st =
                db()->prepare(
                    "
                    SELECT
                        id,
                        codigo,
                        usuario_id,
                        status,
                        status_pagamento,
                        total

                    FROM pedidos

                    WHERE id = ?

                    FOR UPDATE
                    "
                );


            $st->execute([
                $id
            ]);


            $pedido =
                $st->fetch();


            if (!$pedido) {

                throw new RuntimeException(
                    'Pedido não encontrado.'
                );

            }


            $old =
                $pedido['status'];


            // Ao cancelar manualmente, devolve o estoque uma unica vez.
            if (
                $status === 'cancelado'
                && $old !== 'cancelado'
            ) {
                devolver_estoque_pedido(
                    $id,
                    (int)$admin['id']
                );
            }


            if ($status === 'cancelado') {

                db()->prepare(
                    "
                    UPDATE pedidos
                    SET
                        status = 'cancelado',
                        status_pagamento =
                            CASE
                                WHEN status_pagamento = 'pago'
                                    THEN status_pagamento
                                ELSE 'cancelado'
                            END,
                        atualizado_em = NOW()
                    WHERE id = ?
                    "
                )->execute([
                    $id
                ]);

            } else {

                db()->prepare(
                    "
                    UPDATE pedidos
                    SET
                        status = ?,
                        atualizado_em = NOW()
                    WHERE id = ?
                    "
                )->execute([
                    $status,
                    $id
                ]);
            }


            db()->prepare(
                "
                INSERT INTO pedido_status_historico
                (
                    pedido_id,
                    status_anterior,
                    status_novo,
                    usuario_id
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?
                )
                "
            )->execute([
                $id,
                $old,
                $status,
                $admin['id']
            ]);



            /* =================================================
               FIDELIDADE
               ================================================= */

            if ($status === 'concluido') {

                $check =
                    db()->prepare(
                        "
                        SELECT id

                        FROM fidelidade_movimentacoes

                        WHERE pedido_id = ?
                          AND tipo = 'credito'

                        LIMIT 1
                        "
                    );


                $check->execute([
                    $id
                ]);


                $jaPontuou =
                    $check->fetchColumn();


                if (!$jaPontuou) {

                    $pontos =
                        (int)floor(
                            (float)$pedido['total']
                        );


                    if ($pontos > 0) {

                        $codigoPedido =
                            $pedido['codigo']
                            ?: '#'
                            . $pedido['id'];


                        $descricao =
                            'Pontos da compra '
                            . $codigoPedido;


                        $fidelidade =
                            db()->prepare(
                                "
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
                                    ?,
                                    'credito',
                                    ?,
                                    ?
                                )
                                "
                            );


                        $fidelidade->execute([
                            $pedido['usuario_id'],
                            $pedido['id'],
                            $pontos,
                            $descricao
                        ]);


                        audit(
                            'fidelidade_credito',
                            'fidelidade_movimentacoes',
                            (int)db()->lastInsertId(),
                            $pontos
                            . ' pontos - '
                            . $codigoPedido
                        );
                    }
                }
            }


            db()->commit();


            audit(
                'status_pedido',
                'pedidos',
                $id,
                $old
                . ' > '
                . $status
            );


        } catch (Throwable $e) {

            if (
                db()->inTransaction()
            ) {

                db()->rollBack();

            }

        }

    }


    header(
        'Location:/admin/pedidos/'
    );

    exit;
}



/* =========================================================
   LISTAGEM DOS PEDIDOS + ITENS/SABORES
   ========================================================= */

$rows =
    db()->query(
        "
        SELECT
            p.*,

            u.nome AS cliente,
            u.telefone,

            (
                SELECT GROUP_CONCAT(
                    CONCAT(
                        pi.quantidade,
                        'x ',
                        pi.produto_nome
                    )
                    ORDER BY pi.id
                    SEPARATOR ' • '
                )

                FROM pedido_itens pi

                WHERE pi.pedido_id = p.id

            ) AS itens_pedido

        FROM pedidos p

        JOIN usuarios u
            ON u.id = p.usuario_id

        ORDER BY
            p.criado_em DESC

        LIMIT 100
        "
    )->fetchAll();



/* =========================================================
   CABEÇALHO ADMIN
   ========================================================= */

$title =
    'Pedidos';


include __DIR__
    . '/../../includes/admin_header.php';

$flashSuccess = $_SESSION['admin_flash_success'] ?? '';
$flashError = $_SESSION['admin_flash_error'] ?? '';
unset($_SESSION['admin_flash_success'], $_SESSION['admin_flash_error']);

?>

<?php if ($flashSuccess): ?>
<div class="alert success"><?=e($flashSuccess)?></div>
<?php endif; ?>

<?php if ($flashError): ?>
<div class="alert error"><?=e($flashError)?></div>
<?php endif; ?>


<style>

/* =========================================================
   ITENS DO PEDIDO
   ========================================================= */

.order-items{
    display:flex;
    flex-direction:column;
    gap:6px;
    min-width:170px;
}


.order-item{
    width:max-content;
    max-width:230px;

    padding:6px 10px;

    border-radius:10px;

    background:var(--cream);

    color:var(--brown);

    font-size:12px;

    font-weight:700;
}


.order-items-empty{
    color:var(--muted);
    font-size:12px;
}


/* =========================================================
   TABELA
   ========================================================= */

.order-table td{
    vertical-align:top;
}

</style>



<!-- =========================================================
     TABELA DE PEDIDOS
     ========================================================= -->

<div class="table-wrap">

<table class="order-table">

<thead>

<tr>

    <th>
        Pedido
    </th>

    <th>
        Cliente
    </th>

    <th>
        Itens do pedido
    </th>

    <th>
        Pagamento
    </th>

    <th>
        Status pagamento
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


<?php foreach ($rows as $r): ?>


<tr>


    <!-- =====================================================
         PEDIDO
         ===================================================== -->

    <td>

        <strong>

            <?=e(
                $r['codigo']
                ?: '#'
                . $r['id']
            )?>

        </strong>


        <br>


        <small>

            <?=date(
                'd/m/Y H:i',
                strtotime(
                    $r['criado_em']
                )
            )?>

        </small>

    </td>



    <!-- =====================================================
         CLIENTE
         ===================================================== -->

    <td>

        <strong>
            <?=e($r['cliente'])?>
        </strong>

        <br>

        <small>
            <?=e($r['telefone'])?>
        </small>

    </td>



    <!-- =====================================================
         ITENS / SABORES
         ===================================================== -->

    <td>


        <?php if (
            !empty(
                $r['itens_pedido']
            )
        ): ?>


            <?php

            $itensPedido =
                explode(
                    ' • ',
                    $r['itens_pedido']
                );

            ?>


            <div class="order-items">


                <?php foreach (
                    $itensPedido
                    as $itemPedido
                ): ?>


                    <div class="order-item">

                        <?=e($itemPedido)?>

                    </div>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <span class="order-items-empty">

                Nenhum item encontrado

            </span>


        <?php endif; ?>


    </td>



    <!-- =====================================================
         PAGAMENTO
         ===================================================== -->

    <td>

        <?=e(
            ucfirst(
                $r['forma_pagamento']
            )
        )?>

    </td>



    <!-- =====================================================
         STATUS DO PAGAMENTO
         ===================================================== -->

    <td>


        <?php

        $pagamentoLabels = [

            'pendente'
                => 'Aguardando pagamento',

            'aguardando_confirmacao'
                => 'Cliente informou o Pix',

            'pago'
                => 'Pago',

            'cancelado'
                => 'Cancelado'

        ];


        $statusPagamento =
            $r['status_pagamento']
            ?? 'pendente';

        ?>


        <span class="badge">

            <?=e(
                $pagamentoLabels[
                    $statusPagamento
                ]
                ??
                $statusPagamento
            )?>

        </span>



        <?php if (
            $r['forma_pagamento'] === 'pix'
            &&
            $statusPagamento !== 'pago'
        ): ?>


            <form
                method="post"
                style="margin-top:8px"
                onsubmit="
                    return confirm(
                        'Confirmar que este Pix foi recebido no Nubank?'
                    );
                "
            >


                <?=csrf_field()?>


                <input
                    type="hidden"
                    name="id"
                    value="<?=$r['id']?>"
                >


                <input
                    type="hidden"
                    name="acao"
                    value="confirmar_pix"
                >


                <button
                    type="submit"
                    class="btn primary"
                    style="
                        padding:8px 10px;
                        font-size:12px;
                    "
                >

                    Confirmar Pix

                </button>


            </form>


        <?php elseif (
            $statusPagamento === 'pago'
            &&
            !empty(
                $r['pago_em']
            )
        ): ?>


            <br>


            <small>

                <?=date(
                    'd/m/Y H:i',
                    strtotime(
                        $r['pago_em']
                    )
                )?>

            </small>


        <?php endif; ?>


    </td>



    <!-- =====================================================
         TOTAL
         ===================================================== -->

    <td>

        <strong>

            <?=money(
                $r['total']
            )?>

        </strong>

    </td>



    <!-- =====================================================
         STATUS DO PEDIDO
         ===================================================== -->

    <td>


        <form method="post">


            <?=csrf_field()?>


            <input
                type="hidden"
                name="id"
                value="<?=$r['id']?>"
            >


            <input
                type="hidden"
                name="acao"
                value="status_pedido"
            >


            <select
                name="status"
                onchange="
                    this.form.submit()
                "
            >


                <?php

                $statuses = [
                    'aguardando',
                    'confirmado',
                    'preparando',
                    'pronto',
                    'saiu_entrega',
                    'concluido',
                    'cancelado'
                ];


                $labels = [

                    'aguardando'
                        => 'Aguardando',

                    'confirmado'
                        => 'Confirmado',

                    'preparando'
                        => 'Preparando',

                    'pronto'
                        => 'Pronto',

                    'saiu_entrega'
                        => 'Saiu para entrega',

                    'concluido'
                        => 'Concluído',

                    'cancelado'
                        => 'Cancelado'

                ];

                ?>


                <?php foreach (
                    $statuses
                    as $s
                ): ?>


                    <option
                        value="<?=$s?>"

                        <?=$s === $r['status']
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?=e(
                            $labels[$s]
                            ?? $s
                        )?>

                    </option>


                <?php endforeach; ?>


            </select>


        </form>


    </td>

    <!-- =====================================================
         AÇÕES
         ===================================================== -->

    <td>
        <div class="order-actions">
            <?php if ($r['status'] === 'cancelado'): ?>
                <form
                    method="post"
                    onsubmit="return confirm('Excluir definitivamente este pedido? Esta ação não poderá ser desfeita.');"
                >
                    <?=csrf_field()?>
                    <input type="hidden" name="id" value="<?=$r['id']?>">
                    <input type="hidden" name="acao" value="excluir_pedido">

                    <button type="submit" class="btn-danger">
                        Excluir pedido
                    </button>
                </form>
            <?php else: ?>
                <span class="order-delete-note">
                    Para excluir, altere primeiro o status para <strong>Cancelado</strong>.
                </span>
            <?php endif; ?>
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
            padding:35px;
        "
    >

        Nenhum pedido encontrado.

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