<?php
require_once __DIR__.'/../includes/bootstrap.php';

$u = require_login();

$error = '';
$success = '';
$successPedidoId = 0;
$successTotal = 0.0;
$successPagamento = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $items = json_decode($_POST['cart_json'] ?? '[]', true);

    $tipo = $_POST['tipo_entrega'] ?? 'retirada';
    $pag  = $_POST['forma_pagamento'] ?? 'pix';

    $pagamentosPermitidos = ['pix', 'dinheiro'];
    if (!in_array($pag, $pagamentosPermitidos, true)) {
        $pag = 'pix';
    }
    $obs  = trim($_POST['observacao'] ?? '');

    $enderecoId = null;

    if (!$items || !is_array($items)) {
        $error = 'Carrinho vazio.';
    }

    /* =========================================================
       ENDERECO PARA ENTREGA
       ========================================================= */
    if (!$error && $tipo === 'entrega') {

        $cep = preg_replace('/\D+/', '', $_POST['cep'] ?? '');
        $logradouro = trim($_POST['logradouro'] ?? '');
        $numero = trim($_POST['numero'] ?? '');
        $complemento = trim($_POST['complemento'] ?? '');
        $bairro = trim($_POST['bairro'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $uf = strtoupper(trim($_POST['uf'] ?? ''));
        $referencia = trim($_POST['referencia'] ?? '');

        if (
            strlen($cep) !== 8 ||
            $logradouro === '' ||
            $numero === '' ||
            $bairro === '' ||
            $cidade === '' ||
            strlen($uf) !== 2
        ) {
            $error = 'Preencha corretamente os dados de entrega.';
        }
    }

    if (!$error) {

        $ids = array_values(array_unique(
            array_map(
                fn($x) => (int)($x['id'] ?? 0),
                $items
            )
        ));

        $ids = array_values(array_filter($ids));

        if (!$ids) {
            $error = 'Nenhum produto valido no carrinho.';
        }
    }

    if (!$error) {

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $st = db()->prepare("
            SELECT
                p.id,
                p.nome,
                p.preco_venda,
                p.custo_estimado,
                p.controla_estoque,
                ie.id AS item_estoque_id,
                ie.estoque_atual,
                ie.custo_medio AS estoque_custo_medio
            FROM produtos p
            LEFT JOIN itens_estoque ie
              ON ie.produto_id = p.id
             AND ie.tipo = 'produto_pronto'
             AND ie.ativo = 1
            WHERE p.disponivel = 1
              AND p.id IN ($placeholders)
        ");

        $st->execute($ids);

        $products = [];

        foreach ($st->fetchAll() as $p) {
            $products[(int)$p['id']] = $p;
        }

        $subtotal = 0;
        $quantidadesPorProduto = [];

        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            $quantidade = max(1, min(99, (int)($item['qty'] ?? 1)));

            if (!isset($products[$id])) {
                continue;
            }

            $quantidadesPorProduto[$id] =
                ($quantidadesPorProduto[$id] ?? 0) + $quantidade;
        }

        $normalizados = [];

        foreach ($quantidadesPorProduto as $id => $quantidade) {
            $produto = $products[$id];

            if (!empty($produto['controla_estoque']) && empty($produto['item_estoque_id'])) {
                $error =
                    'Estoque indisponivel para ' . $produto['nome']
                    . '. Avise a loja para revisar o cadastro.';
                break;
            }

            if (!empty($produto['controla_estoque'])) {
                $disponivel = (float)$produto['estoque_atual'];

                if ($quantidade > $disponivel) {
                    $error =
                        'Estoque insuficiente para ' . $produto['nome']
                        . '. Disponivel: '
                        . number_format($disponivel, 0, ',', '.')
                        . ' unidade(s).';
                    break;
                }
            }

            $linha = (float)$produto['preco_venda'] * $quantidade;
            $subtotal += $linha;

            $normalizados[] = [
                $produto,
                $quantidade,
                $linha
            ];
        }

        if (!$error && !$normalizados) {
            $error = 'Nenhum produto valido no carrinho.';
        }
    }

    if (!$error) {

        /*
         * Por enquanto usamos a taxa-base de R$ 5,00.
         * Depois conectaremos a tabela regioes_entrega.
         */
        $taxa = $tipo === 'entrega' ? 5.00 : 0.00;
        $total = $subtotal + $taxa;

        db()->beginTransaction();

        try {

            /* =================================================
               CONFERE E RESERVA O ESTOQUE
               A validacao e repetida com FOR UPDATE para impedir
               duas compras simultaneas do mesmo saldo.
               ================================================= */
            foreach ($normalizados as [$produto, $quantidade, $linha]) {

                if (empty($produto['controla_estoque'])) {
                    continue;
                }

                if (empty($produto['item_estoque_id'])) {
                    throw new RuntimeException(
                        'Estoque indisponivel para ' . $produto['nome'] . '.'
                    );
                }

                $estoqueSt = db()->prepare("
                    SELECT
                        id,
                        estoque_atual,
                        custo_medio
                    FROM itens_estoque
                    WHERE id = ?
                      AND ativo = 1
                    FOR UPDATE
                ");

                $estoqueSt->execute([
                    $produto['item_estoque_id']
                ]);

                $estoque = $estoqueSt->fetch();

                if (
                    !$estoque
                    || (float)$estoque['estoque_atual'] < $quantidade
                ) {
                    throw new RuntimeException(
                        'Estoque insuficiente para ' . $produto['nome'] . '.'
                    );
                }
            }

            /* =================================================
               GRAVA / REUTILIZA ENDERECO
               ================================================= */
            if ($tipo === 'entrega') {

                /*
                 * Evita criar o mesmo endereco repetidamente
                 * para o mesmo cliente.
                 */
                $buscarEndereco = db()->prepare("
                    SELECT id
                    FROM enderecos
                    WHERE usuario_id = ?
                      AND cep = ?
                      AND logradouro = ?
                      AND numero = ?
                      AND bairro = ?
                    LIMIT 1
                ");

                $buscarEndereco->execute([
                    $u['id'],
                    $cep,
                    $logradouro,
                    $numero,
                    $bairro
                ]);

                $enderecoId = $buscarEndereco->fetchColumn();

                if (!$enderecoId) {

                    $novoEndereco = db()->prepare("
                        INSERT INTO enderecos
                        (
                            usuario_id,
                            apelido,
                            cep,
                            logradouro,
                            numero,
                            complemento,
                            bairro,
                            cidade,
                            uf,
                            referencia,
                            principal
                        )
                        VALUES
                        (
                            ?,
                            'Entrega',
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            0
                        )
                    ");

                    $novoEndereco->execute([
                        $u['id'],
                        $cep,
                        $logradouro,
                        $numero,
                        $complemento ?: null,
                        $bairro,
                        $cidade,
                        $uf,
                        $referencia ?: null
                    ]);

                    $enderecoId = (int)db()->lastInsertId();
                }
            }

            /* =================================================
               CRIA PEDIDO
               ================================================= */
            $pedido = db()->prepare("
                INSERT INTO pedidos
                (
                    usuario_id,
                    endereco_id,
                    status,
                    tipo_entrega,
                    forma_pagamento,
                    status_pagamento,
                    subtotal,
                    taxa_entrega,
                    total,
                    observacao_cliente
                )
                VALUES
                (
                    ?,
                    ?,
                    'aguardando',
                    ?,
                    ?,
                    'pendente',
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $pedido->execute([
                $u['id'],
                $enderecoId,
                $tipo,
                $pag,
                $subtotal,
                $taxa,
                $total,
                $obs ?: null
            ]);

            $pedidoId = (int)db()->lastInsertId();

            $codigo = 'BDC-' . str_pad(
                (string)$pedidoId,
                6,
                '0',
                STR_PAD_LEFT
            );

            db()->prepare("
                UPDATE pedidos
                SET codigo = ?
                WHERE id = ?
            ")->execute([
                $codigo,
                $pedidoId
            ]);

            /* =================================================
               ITENS DO PEDIDO
               ================================================= */
            $itemPedido = db()->prepare("
                INSERT INTO pedido_itens
                (
                    pedido_id,
                    produto_id,
                    produto_nome,
                    quantidade,
                    valor_unitario,
                    custo_unitario,
                    valor_total
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($normalizados as [$produto, $quantidade, $linha]) {

                $itemPedido->execute([
                    $pedidoId,
                    $produto['id'],
                    $produto['nome'],
                    $quantidade,
                    $produto['preco_venda'],
                    $produto['custo_estimado'],
                    $linha
                ]);

                if (!empty($produto['controla_estoque'])) {
                    db()->prepare("
                        UPDATE itens_estoque
                        SET estoque_atual = estoque_atual - ?
                        WHERE id = ?
                    ")->execute([
                        $quantidade,
                        $produto['item_estoque_id']
                    ]);

                    db()->prepare("
                        INSERT INTO estoque_movimentacoes
                        (
                            item_estoque_id,
                            tipo,
                            quantidade,
                            custo_unitario,
                            referencia_tipo,
                            observacao,
                            usuario_id
                        )
                        VALUES
                        (
                            ?,
                            'saida',
                            ?,
                            ?,
                            'pedido',
                            ?,
                            ?
                        )
                    ")->execute([
                        $produto['item_estoque_id'],
                        $quantidade,
                        $produto['estoque_custo_medio'] ?? 0,
                        'Pedido ' . $codigo,
                        $u['id']
                    ]);
                }
            }

            /* Historico inicial */
            db()->prepare("
                INSERT INTO pedido_status_historico
                (
                    pedido_id,
                    status_novo,
                    usuario_id
                )
                VALUES
                (
                    ?,
                    'aguardando',
                    ?
                )
            ")->execute([
                $pedidoId,
                $u['id']
            ]);

            db()->commit();

            audit(
                'pedido_criado',
                'pedidos',
                $pedidoId
            );

            $success = $codigo;
            $successPedidoId = $pedidoId;
            $successTotal = (float)$total;
            $successPagamento = $pag;

        } catch (Throwable $e) {

            if (db()->inTransaction()) {
                db()->rollBack();
            }

            $error = 'Nao foi possivel criar o pedido.';
        }
    }
}

$title = 'Finalizar pedido';

include __DIR__.'/../includes/header.php';
?>

<style>

/* =========================================================
   CHECKOUT
   ========================================================= */

.checkout-address{
    margin-top:18px;
    padding-top:18px;
    border-top:1px solid var(--line);
}

.checkout-address.hidden{
    display:none;
}

.address-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:0 14px;
}

.address-grid .span-2{
    grid-column:1 / -1;
}

.cep-wrap{
    position:relative;
}

.cep-status{
    display:block;
    margin-top:6px;
    font-size:12px;
    color:var(--muted);
}

.cep-status.success{
    color:var(--success);
}

.cep-status.error{
    color:var(--danger);
}

.checkout-resumo{
    margin:18px 0;
    padding:15px;
    border-radius:14px;
    background:var(--cream);
}

.checkout-resumo div{
    display:flex;
    justify-content:space-between;
    gap:20px;
    margin:6px 0;
}

.checkout-resumo .total{
    border-top:1px solid var(--line);
    padding-top:10px;
    margin-top:10px;
    font-size:19px;
}

@media(max-width:620px){
    .address-grid{
        grid-template-columns:1fr;
    }

    .address-grid .span-2{
        grid-column:auto;
    }
}

.pix-payment-card{
    margin-top:18px;
    padding:24px;
    border:1px solid var(--line);
    border-radius:22px;
    background:#fff;
    text-align:center;
    box-shadow:var(--shadow);
}

.pix-payment-card h2{
    margin:4px 0 8px;
}

.pix-order{
    color:var(--muted);
    margin:0;
}

.pix-total{
    font-size:34px;
    font-weight:800;
    color:var(--brown);
    margin:12px 0 18px;
}

.pix-qr{
    width:256px;
    min-height:256px;
    margin:0 auto 16px;
    padding:10px;
    background:#fff;
    border:1px solid var(--line);
    border-radius:18px;
    display:grid;
    place-items:center;
}

.pix-qr img,
.pix-qr canvas{
    max-width:100%;
    height:auto;
}

.pix-qr-error{
    padding:14px;
    color:var(--danger);
    font-size:14px;
    line-height:1.45;
}

.pix-help{
    color:var(--muted);
    line-height:1.55;
}

.pix-copy-label{
    text-align:left;
}

#pixPayload{
    font-family:ui-monospace,SFMono-Regular,Consolas,monospace;
    font-size:12px;
    resize:none;
}

.pix-confirm-form{
    margin-top:10px;
}

.pix-expiration{
    margin-top:14px;
    padding:12px;
    border-radius:12px;
    background:var(--cream);
    color:var(--brown);
}

.pix-expiration strong{
    font-size:20px;
    margin-left:6px;
}

.pix-warning{
    display:block;
    margin-top:14px;
    color:var(--muted);
    line-height:1.5;
}

</style>


<div class="form-card">

    <h1>Finalizar pedido</h1>

    <?php if ($success): ?>

        <?php
        $pixPayload = '';

        if ($successPagamento === 'pix') {
            try {
                /*
                 * TXID curto e rastreavel.
                 * O pagamento continua sendo confirmado manualmente.
                 */
                $pixPayload = pix_copia_cola(
                    $successTotal,
                    'BDC' . $successPedidoId
                );
            } catch (Throwable $e) {
                $pixPayload = '';
            }
        }
        ?>

        <div class="alert success">
            Pedido
            <strong><?= e($success) ?></strong>
            criado com sucesso!
        </div>

        <?php if ($successPagamento === 'pix'): ?>

            <div class="pix-payment-card">

                <span class="eyebrow">
                    PAGAMENTO VIA PIX
                </span>

                <h2>
                    Agora e so fazer o Pix
                </h2>

                <p class="pix-order">
                    Pedido
                    <strong><?=e($success)?></strong>
                </p>

                <div class="pix-total">
                    <?=money($successTotal)?>
                </div>

                <?php if ($pixPayload): ?>

                    <div
                        id="pixQrCode"
                        class="pix-qr"
                        aria-label="QR Code Pix"
                    ></div>

                    <p class="pix-help">
                        Abra o Nubank ou outro banco e escaneie o QR Code.
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
                            value="<?=$successPedidoId?>"
                        >

                        <button
                            class="btn primary full"
                            type="submit"
                        >
                            Ja fiz o pagamento
                        </button>
                    </form>


                    <div class="pix-expiration">
                        Reserva do pedido:
                        <strong id="pixCountdown">10:00</strong>
                    </div>

                    <script>
                    (function(){
                        const countdown = document.getElementById('pixCountdown');
                        if (!countdown) return;

                        const expiresAt = Date.now() + (10 * 60 * 1000);

                        function tick(){
                            const left = Math.max(0, expiresAt - Date.now());
                            const totalSeconds = Math.ceil(left / 1000);
                            const minutes = Math.floor(totalSeconds / 60);
                            const seconds = totalSeconds % 60;

                            countdown.textContent =
                                String(minutes).padStart(2, '0')
                                + ':'
                                + String(seconds).padStart(2, '0');

                            if (left <= 0) {
                                countdown.textContent = '00:00';
                                window.location.reload();
                                return;
                            }

                            setTimeout(tick, 1000);
                        }

                        tick();
                    })();
                    </script>

                    <small class="pix-warning">
                        O pedido ficara aguardando conferencia.
                        A confirmacao e feita pela Doces do Casal apos
                        verificar o recebimento no Nubank.
                    </small>

                <?php else: ?>

                    <div class="alert error">
                        O pedido foi criado, mas a chave Pix ainda nao esta configurada.
                        Voce pode acompanhar o pedido e combinar o pagamento com a loja.
                    </div>

                <?php endif; ?>

            </div>

        <?php else: ?>

            <a
                class="btn primary full"
                href="/cliente/"
            >
                Acompanhar pedido
            </a>

        <?php endif; ?>

        <script>
            localStorage.removeItem('bdc_cart');
        </script>

        <?php if ($successPagamento === 'pix' && $pixPayload): ?>

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

        <?php endif; ?>

    <?php else: ?>

        <?php if ($error): ?>

            <div class="alert error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form
            method="post"
            id="checkoutReal"
        >

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="cart_json"
                id="cartJson"
            >


            <!-- ===============================================
                 TIPO DE RECEBIMENTO
                 =============================================== -->

            <label>

                Recebimento

                <select
                    name="tipo_entrega"
                    id="tipoEntrega"
                >

                    <option value="retirada">
                        Retirada
                    </option>

                    <option value="entrega">
                        Entrega
                    </option>

                </select>

            </label>


            <!-- ===============================================
                 ENDERECO
                 So aparece quando o cliente escolhe Entrega
                 =============================================== -->

            <div
                id="enderecoEntrega"
                class="checkout-address hidden"
            >

                <span class="eyebrow">
                    ENDERECO DE ENTREGA
                </span>

                <div class="address-grid">

                    <label class="cep-wrap">

                        CEP

                        <input
                            type="text"
                            name="cep"
                            id="cep"
                            maxlength="9"
                            inputmode="numeric"
                            placeholder="00000-000"
                            autocomplete="postal-code"
                        >

                        <small
                            id="cepStatus"
                            class="cep-status"
                        >
                            Informe o CEP para buscar o endereco.
                        </small>

                    </label>


                    <label>

                        Numero

                        <input
                            type="text"
                            name="numero"
                            id="numero"
                            placeholder="Ex.: 123"
                            autocomplete="address-line2"
                        >

                    </label>


                    <label class="span-2">

                        Rua / Avenida

                        <input
                            type="text"
                            name="logradouro"
                            id="logradouro"
                            placeholder="Rua ou avenida"
                            autocomplete="address-line1"
                        >

                    </label>


                    <label>

                        Bairro

                        <input
                            type="text"
                            name="bairro"
                            id="bairro"
                            placeholder="Bairro"
                        >

                    </label>


                    <label>

                        Complemento

                        <input
                            type="text"
                            name="complemento"
                            id="complemento"
                            placeholder="Apto, bloco, casa..."
                        >

                    </label>


                    <label>

                        Cidade

                        <input
                            type="text"
                            name="cidade"
                            id="cidade"
                            placeholder="Cidade"
                            autocomplete="address-level2"
                        >

                    </label>


                    <label>

                        UF

                        <input
                            type="text"
                            name="uf"
                            id="uf"
                            maxlength="2"
                            placeholder="MG"
                            autocomplete="address-level1"
                        >

                    </label>


                    <label class="span-2">

                        Referencia

                        <input
                            type="text"
                            name="referencia"
                            id="referencia"
                            placeholder="Ponto de referencia"
                        >

                    </label>

                </div>

            </div>


            <!-- ===============================================
                 FORMA DE PAGAMENTO
                 =============================================== -->

            <label>

                Forma de pagamento

                <select
                    name="forma_pagamento"
                    id="formaPagamento"
                >

                    <option value="pix">
                        Pix
                    </option>

                    <option value="dinheiro">
                        Dinheiro
                    </option>

                </select>

            </label>


            <label>

                Observacao

                <textarea
                    name="observacao"
                    rows="3"
                    placeholder="Alguma observacao para o pedido?"
                ></textarea>

            </label>


            <div class="checkout-resumo">

                <div>
                    <span>Subtotal</span>
                    <strong id="checkoutSubtotal">R$ 0,00</strong>
                </div>

                <div>
                    <span>Entrega</span>
                    <strong id="checkoutTaxa">R$ 0,00</strong>
                </div>

                <div class="total">
                    <span>Total</span>
                    <strong id="checkoutTotal">R$ 0,00</strong>
                </div>

            </div>


            <button
                class="btn primary full"
                type="submit"
                id="checkoutSubmit"
            >
                Finalizar pedido
            </button>

        </form>

    <?php endif; ?>

</div>


<?php if (!$success): ?>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('checkoutReal');
    const cartJson = document.getElementById('cartJson');
    const tipoEntrega = document.getElementById('tipoEntrega');
    const enderecoEntrega = document.getElementById('enderecoEntrega');
    const subtotalEl = document.getElementById('checkoutSubtotal');
    const taxaEl = document.getElementById('checkoutTaxa');
    const totalEl = document.getElementById('checkoutTotal');
    const checkoutSubmit = document.getElementById('checkoutSubmit');
    const cepInput = document.getElementById('cep');
    const cepStatus = document.getElementById('cepStatus');
    const logradouroInput = document.getElementById('logradouro');
    const bairroInput = document.getElementById('bairro');
    const cidadeInput = document.getElementById('cidade');
    const ufInput = document.getElementById('uf');
    const numeroInput = document.getElementById('numero');

    const cart = JSON.parse(localStorage.getItem('bdc_cart') || '[]');

    function money(value) {
        return Number(value || 0).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
    }

    function cartSubtotal() {
        return cart.reduce(function (total, item) {
            const preco = Number(item.price || item.preco || item.preco_venda || 0);
            const qty = Math.max(1, Number(item.qty || item.quantidade || 1));

            return total + (preco * qty);
        }, 0);
    }

    function updateAddressVisibility() {
        if (!tipoEntrega || !enderecoEntrega) {
            return;
        }

        if (tipoEntrega.value === 'entrega') {
            enderecoEntrega.classList.remove('hidden');
        } else {
            enderecoEntrega.classList.add('hidden');
        }
    }

    function updateTotals() {
        const subtotal = cartSubtotal();
        const taxa = tipoEntrega && tipoEntrega.value === 'entrega' ? 5.00 : 0.00;
        const total = subtotal + taxa;

        if (subtotalEl) {
            subtotalEl.textContent = money(subtotal);
        }

        if (taxaEl) {
            taxaEl.textContent = money(taxa);
        }

        if (totalEl) {
            totalEl.textContent = money(total);
        }
    }

    function setCepStatus(message, type) {
        if (!cepStatus) {
            return;
        }

        cepStatus.textContent = message;
        cepStatus.classList.remove('success', 'error');

        if (type) {
            cepStatus.classList.add(type);
        }
    }

    async function buscarCep() {
        if (!cepInput) {
            return;
        }

        const cep = cepInput.value.replace(/\D+/g, '');

        if (cep.length !== 8) {
            setCepStatus('Informe o CEP para buscar o endereco.', '');
            return;
        }

        setCepStatus('Buscando endereco...', '');

        try {
            const response = await fetch('https://viacep.com.br/ws/' + cep + '/json/');
            const data = await response.json();

            if (data.erro) {
                setCepStatus('CEP nao encontrado. Preencha o endereco manualmente.', 'error');
                return;
            }

            if (logradouroInput) {
                logradouroInput.value = data.logradouro || '';
            }

            if (bairroInput) {
                bairroInput.value = data.bairro || '';
            }

            if (cidadeInput) {
                cidadeInput.value = data.localidade || '';
            }

            if (ufInput) {
                ufInput.value = data.uf || '';
            }

            setCepStatus('Endereco encontrado.', 'success');

            if (numeroInput) {
                numeroInput.focus();
            }

        } catch (error) {
            setCepStatus('Nao foi possivel buscar o CEP agora.', 'error');
        }
    }

    if (!cart.length) {
        if (checkoutSubmit) {
            checkoutSubmit.disabled = true;
        }

        if (form) {
            form.insertAdjacentHTML(
                'beforebegin',
                '<div class="alert error">Seu carrinho esta vazio.</div>'
            );
        }
    }

    if (cartJson) {
        cartJson.value = JSON.stringify(cart);
    }

    updateAddressVisibility();
    updateTotals();

    if (tipoEntrega) {
        tipoEntrega.addEventListener('change', function () {
            updateAddressVisibility();
            updateTotals();
        });
    }

    if (cepInput) {
        cepInput.addEventListener('input', function () {
            let value = cepInput.value.replace(/\D+/g, '').slice(0, 8);

            if (value.length > 5) {
                value = value.slice(0, 5) + '-' + value.slice(5);
            }

            cepInput.value = value;
        });

        cepInput.addEventListener('blur', buscarCep);
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            if (!cart.length) {
                event.preventDefault();
                alert('Carrinho vazio.');
                return;
            }

            if (cartJson) {
                cartJson.value = JSON.stringify(cart);
            }
        });
    }

});
</script>

<?php endif; ?>

<?php
include __DIR__.'/../includes/footer.php';
