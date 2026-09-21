<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$u = require_login();

cancelar_pedidos_pix_expirados();

$pedidoId = (int)($_GET['id'] ?? 0);
$pedido = null;
$pixPayload = '';
$secondsLeft = 0;
$expiresAt = 0;
$estado = 'nao_encontrado';

if ($pedidoId > 0) {
    $st = db()->prepare("
        SELECT
            id,
            codigo,
            usuario_id,
            status,
            forma_pagamento,
            status_pagamento,
            total,
            criado_em
        FROM pedidos
        WHERE id = ?
          AND usuario_id = ?
        LIMIT 1
    ");

    $st->execute([
        $pedidoId,
        $u['id']
    ]);

    $pedido = $st->fetch();
}

if ($pedido) {
    $formaPagamento = strtolower((string)$pedido['forma_pagamento']);
    $statusPedido = strtolower((string)$pedido['status']);
    $statusPagamento = strtolower((string)$pedido['status_pagamento']);

    $expiresAt = strtotime($pedido['criado_em'] . ' +10 minutes');
    $secondsLeft = max(0, $expiresAt - time());

    if ($formaPagamento !== 'pix') {
        $estado = 'nao_pix';
    } elseif ($statusPagamento === 'pago') {
        $estado = 'pago';
    } elseif ($statusPedido === 'cancelado' || $statusPagamento === 'cancelado') {
        $estado = 'cancelado';
    } elseif ($secondsLeft <= 0) {
        $estado = 'expirado';
    } elseif (
        in_array(
            $statusPagamento,
            [
                'pendente',
                'aguardando_confirmacao'
            ],
            true
        )
    ) {
        $estado = 'pendente';

        try {
            $pixPayload = pix_copia_cola(
                (float)$pedido['total'],
                'BDC' . (int)$pedido['id']
            );
        } catch (Throwable $e) {
            $pixPayload = '';
        }
    } else {
        $estado = 'indisponivel';
    }
}

$title = 'Pagamento Pix';

include __DIR__ . '/../includes/header.php';

?>

<style>
.pix-page{
    padding:70px 0 90px;
    background:#FFF8F0;
}

.pix-page-card{
    max-width:680px;
    margin:0 auto;
    padding:34px;
    border:1px solid var(--line);
    border-radius:26px;
    background:#fff;
    box-shadow:var(--shadow);
}

.pix-page-card h1{
    margin:8px 0 12px;
    font-family:Georgia,"Times New Roman",serif;
    font-size:clamp(34px,5vw,48px);
    font-weight:500;
    color:var(--brown);
}

.pix-page-card p{
    color:var(--muted);
    line-height:1.6;
}

.pix-order{
    margin:18px 0 8px;
    color:var(--muted);
}

.pix-total{
    margin:8px 0 22px;
    font-family:Georgia,"Times New Roman",serif;
    font-size:38px;
    color:var(--brown);
}

.pix-qr{
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:260px;
    margin:20px auto;
    padding:18px;
    border:1px solid var(--line);
    border-radius:22px;
    background:#fff;
}

.pix-qr img,
.pix-qr canvas{
    max-width:230px;
    width:100%;
    height:auto;
}

.pix-qr-error{
    color:var(--danger);
    text-align:center;
    line-height:1.5;
}

.pix-copy-label{
    display:block;
    margin-top:18px;
    color:var(--brown);
    font-weight:800;
}

#pixPayload{
    width:100%;
    margin-top:8px;
    resize:vertical;
}

.pix-confirm-form{
    margin-top:12px;
}

.pix-expiration{
    margin-top:16px;
    padding:14px 16px;
    border-radius:16px;
    background:var(--cream);
    color:var(--brown);
    text-align:center;
}

.pix-expiration strong{
    font-size:20px;
}

.pix-actions{
    display:grid;
    gap:10px;
    margin-top:18px;
}

.pix-status-box{
    margin-top:20px;
}

@media(max-width:620px){
    .pix-page{
        padding:45px 0 70px;
    }

    .pix-page-card{
        padding:24px;
    }
}
</style>

<section class="pix-page">
    <div class="container">
        <div class="pix-page-card">
            <span class="eyebrow">
                PAGAMENTO VIA PIX
            </span>

            <?php if (!$pedido): ?>

                <h1>Pedido não encontrado</h1>

                <div class="alert error pix-status-box">
                    Não encontramos esse pedido na sua conta.
                </div>

                <div class="pix-actions">
                    <a class="btn primary full" href="/cliente/">
                        Voltar para meus pedidos
                    </a>
                </div>

            <?php elseif ($estado === 'nao_pix'): ?>

                <h1>Este pedido não é Pix</h1>

                <p class="pix-order">
                    Pedido
                    <strong><?=e($pedido['codigo'] ?: '#' . $pedido['id'])?></strong>
                </p>

                <div class="alert error pix-status-box">
                    Esse pedido foi criado com outra forma de pagamento.
                </div>

                <div class="pix-actions">
                    <a class="btn primary full" href="/cliente/">
                        Voltar para meus pedidos
                    </a>
                </div>

            <?php elseif ($estado === 'pago'): ?>

                <h1>Pagamento confirmado</h1>

                <p class="pix-order">
                    Pedido
                    <strong><?=e($pedido['codigo'] ?: '#' . $pedido['id'])?></strong>
                </p>

                <div class="alert success pix-status-box">
                    O pagamento deste pedido já foi confirmado.
                </div>

                <div class="pix-actions">
                    <a class="btn primary full" href="/cliente/">
                        Voltar para meus pedidos
                    </a>
                </div>

            <?php elseif ($estado === 'cancelado' || $estado === 'expirado'): ?>

                <h1>Pedido cancelado</h1>

                <p class="pix-order">
                    Pedido
                    <strong><?=e($pedido['codigo'] ?: '#' . $pedido['id'])?></strong>
                </p>

                <div class="alert error pix-status-box">
                    O prazo para pagamento Pix expirou ou o pedido foi cancelado.
                    Faça um novo pedido para reservar os produtos novamente.
                </div>

                <div class="pix-actions">
                    <a class="btn primary full" href="/#cardapio">
                        Fazer novo pedido
                    </a>
                    <a class="btn outline full" href="/cliente/">
                        Voltar para meus pedidos
                    </a>
                </div>

            <?php elseif ($estado === 'pendente'): ?>

                <h1>Retomar pagamento Pix</h1>

                <p class="pix-order">
                    Pedido
                    <strong><?=e($pedido['codigo'] ?: '#' . $pedido['id'])?></strong>
                </p>

                <div class="pix-total">
                    <?=money($pedido['total'])?>
                </div>

                <?php if ($pixPayload): ?>

                    <div
                        id="pixQrCode"
                        class="pix-qr"
                        aria-label="QR Code Pix"
                    ></div>

                    <p>
                        Abra o app do seu banco e escaneie o QR Code.
                        Se preferir, use o Pix Copia e Cola.
                    </p>

                    <label class="pix-copy-label">
                        Pix Copia e Cola

                        <textarea
                            id="pixPayload"
                            readonly
                            rows="4"
                        ><?=e($pixPayload)?></textarea>
                    </label>

                    <button
                        type="button"
                        class="btn outline full"
                        id="copyPixBtn"
                    >
                        Copiar codigo Pix
                    </button>

                    <form
                        method="post"
                        action="/cliente/pix-confirmar.php"
                        class="pix-confirm-form"
                    >
                        <?=csrf_field()?>

                        <input
                            type="hidden"
                            name="pedido_id"
                            value="<?=(int)$pedido['id']?>"
                        >

                        <button
                            class="btn primary full"
                            type="submit"
                        >
                            Ja fiz o pagamento
                        </button>
                    </form>

                    <div class="pix-expiration">
                        Tempo restante:
                        <strong
                            id="pixCountdown"
                            data-seconds-left="<?=(int)$secondsLeft?>"
                        >
                            --:--
                        </strong>
                    </div>

                    <?php if ($statusPagamento === 'aguardando_confirmacao'): ?>

                        <div class="alert success pix-status-box">
                            Você já avisou que fez o pagamento.
                            A Doces do Casal vai confirmar o recebimento.
                        </div>

                    <?php endif; ?>

                    <script>
                    window.bdcPixPayload = <?= json_encode(
                        $pixPayload,
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES |
                        JSON_HEX_TAG |
                        JSON_HEX_AMP |
                        JSON_HEX_APOS |
                        JSON_HEX_QUOT
                    ) ?>;

                    window.bdcRenderPixQrCode = function () {
                        function start() {
                            const qrContainer = document.getElementById('pixQrCode');
                            const copyButton = document.getElementById('copyPixBtn');
                            const payloadField = document.getElementById('pixPayload');
                            const countdown = document.getElementById('pixCountdown');

                            function showQrError(message) {
                                console.error(message);

                                if (!qrContainer) {
                                    return;
                                }

                                qrContainer.innerHTML =
                                    '<div class="pix-qr-error">' +
                                    'Nao foi possivel carregar o QR Code Pix.<br>' +
                                    'Use o Pix Copia e Cola abaixo.' +
                                    '</div>';
                            }

                            if (!qrContainer) {
                                console.error('Container #pixQrCode nao encontrado.');
                            } else if (typeof QRCode === 'undefined') {
                                showQrError(
                                    'QRCode.js nao foi carregado. Verifique se /assets/vendor/qrcodejs/qrcode.min.js existe no servidor.'
                                );
                            } else {
                                qrContainer.innerHTML = '';

                                new QRCode(qrContainer, {
                                    text: window.bdcPixPayload,
                                    width: 230,
                                    height: 230,
                                    correctLevel: QRCode.CorrectLevel.M
                                });
                            }

                            if (copyButton && payloadField) {
                                copyButton.addEventListener('click', async function () {
                                    try {
                                        await navigator.clipboard.writeText(payloadField.value);

                                        const textoOriginal = copyButton.textContent;
                                        copyButton.textContent = 'Codigo Pix copiado';

                                        setTimeout(function () {
                                            copyButton.textContent = textoOriginal;
                                        }, 2000);
                                    } catch (error) {
                                        payloadField.select();
                                        document.execCommand('copy');
                                        copyButton.textContent = 'Codigo Pix copiado';
                                    }
                                });
                            }

                            if (countdown) {
                                let secondsLeft = parseInt(
                                    countdown.dataset.secondsLeft || '0',
                                    10
                                );

                                function tick() {
                                    secondsLeft = Math.max(0, secondsLeft);

                                    const minutes = Math.floor(secondsLeft / 60);
                                    const seconds = secondsLeft % 60;

                                    countdown.textContent =
                                        String(minutes).padStart(2, '0')
                                        + ':'
                                        + String(seconds).padStart(2, '0');

                                    if (secondsLeft <= 0) {
                                        window.location.reload();
                                        return;
                                    }

                                    secondsLeft -= 1;
                                    setTimeout(tick, 1000);
                                }

                                tick();
                            }
                        }

                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', start);
                        } else {
                            start();
                        }
                    };

                    window.bdcPixQrCodeLoadError = function () {
                        function showMissingLibrary() {
                            const qrContainer = document.getElementById('pixQrCode');

                            console.error(
                                'QRCode.js nao carregou. Verifique se /assets/vendor/qrcodejs/qrcode.min.js existe no servidor.'
                            );

                            if (qrContainer) {
                                qrContainer.innerHTML =
                                    '<div class="pix-qr-error">' +
                                    'Nao foi possivel carregar o QR Code Pix.<br>' +
                                    'Use o Pix Copia e Cola abaixo.' +
                                    '</div>';
                            }
                        }

                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', showMissingLibrary);
                        } else {
                            showMissingLibrary();
                        }
                    };
                    </script>

                    <script
                        src="/assets/vendor/qrcodejs/qrcode.min.js"
                        onload="window.bdcRenderPixQrCode()"
                        onerror="window.bdcPixQrCodeLoadError()"
                    ></script>

                <?php else: ?>

                    <div class="alert error pix-status-box">
                        A chave Pix ainda não está configurada.
                        Acompanhe o pedido e combine o pagamento com a loja.
                    </div>

                    <div class="pix-actions">
                        <a class="btn primary full" href="/cliente/">
                            Voltar para meus pedidos
                        </a>
                    </div>

                <?php endif; ?>

            <?php else: ?>

                <h1>Pagamento indisponível</h1>

                <p class="pix-order">
                    Pedido
                    <strong><?=e($pedido['codigo'] ?: '#' . $pedido['id'])?></strong>
                </p>

                <div class="alert error pix-status-box">
                    Este pedido não está disponível para retomada de pagamento.
                </div>

                <div class="pix-actions">
                    <a class="btn primary full" href="/cliente/">
                        Voltar para meus pedidos
                    </a>
                </div>

            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
