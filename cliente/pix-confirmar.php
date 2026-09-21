<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$u = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location:/cliente/');
    exit;
}

verify_csrf();

$pedidoId =
    (int)(
        $_POST['pedido_id']
        ?? 0
    );

$st =
    db()->prepare(
        "SELECT
            id,
            codigo,
            forma_pagamento,
            status_pagamento
         FROM pedidos
         WHERE id = ?
           AND usuario_id = ?
         LIMIT 1"
    );

$st->execute([
    $pedidoId,
    $u['id']
]);

$pedido =
    $st->fetch();

if (
    !$pedido
    || $pedido['forma_pagamento'] !== 'pix'
) {

    flash(
        'erro',
        'Pedido Pix não encontrado.'
    );

    header('Location:/cliente/');
    exit;
}

/*
 * O cliente NÃO consegue marcar o pedido como pago.
 * Ele apenas informa que realizou o pagamento.
 */
if (
    $pedido['status_pagamento'] === 'pendente'
) {

    db()->prepare(
        "UPDATE pedidos
         SET
            status_pagamento = 'aguardando_confirmacao',
            atualizado_em = NOW()
         WHERE id = ?"
    )->execute([
        $pedidoId
    ]);

    audit(
        'pix_informado_cliente',
        'pedidos',
        $pedidoId,
        'Cliente informou pagamento Pix'
    );
}

flash(
    'success',
    'Recebemos seu aviso de pagamento. Agora vamos conferir o Pix.'
);

header('Location:/cliente/');
exit;
