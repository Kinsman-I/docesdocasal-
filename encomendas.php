<?php
require_once __DIR__.'/includes/bootstrap.php';

$u = require_login();

/*
 * Recarrega os dados do cliente diretamente do banco para a encomenda.
 * Assim nome, telefone e e-mail ficam sempre vinculados à conta logada.
 */
$stUsuario = db()->prepare(
    "SELECT id, nome, telefone, email
     FROM usuarios
     WHERE id = ?
       AND ativo = 1
     LIMIT 1"
);
$stUsuario->execute([(int)$u['id']]);
$dadosConta = $stUsuario->fetch();

if (!$dadosConta) {
    logout_user();
    header('Location:/auth/login.php?next=' . urlencode('/encomendas.php'));
    exit;
}

$u = array_merge($u, $dadosConta);
$msg = '';
$error = '';

$dataMinimaPagina = date('Y-m-d', strtotime('+2 days'));

$products = db()->query("
    SELECT id, nome
    FROM produtos
    WHERE disponivel = 1
    ORDER BY nome
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $nome = trim($_POST['nome'] ?? '');
    $telefone = preg_replace('/\D+/', '', $_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $dataEvento = $_POST['data_evento'] ?? '';
    $dataMinima = date('Y-m-d', strtotime('+2 days'));
    $tipoEvento = trim($_POST['tipo_evento'] ?? '');
    $tipoEntrega = $_POST['tipo_entrega'] ?? 'retirada';
    $cep = preg_replace('/\D+/', '', $_POST['cep'] ?? '');
    $logradouro = trim($_POST['logradouro'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $uf = strtoupper(trim($_POST['uf'] ?? ''));
    $referencia = trim($_POST['referencia'] ?? '');
    $observacao = trim($_POST['observacao'] ?? '');
    $sabores = $_POST['sabores'] ?? [];

    if ($nome === '') {
        $error = 'Informe seu nome.';
    } elseif (strlen($telefone) < 10) {
        $error = 'Informe um telefone válido.';
    } elseif (!$dataEvento || strtotime($dataEvento) === false) {
        $error = 'Informe a data desejada.';
    } elseif ($dataEvento < $dataMinima) {
        $error = 'Precisamos de pelo menos 48 horas para preparar sua encomenda.';
    } elseif (!in_array($tipoEntrega, ['retirada','entrega'], true)) {
        $error = 'Tipo de recebimento inválido.';
    } elseif (
        $tipoEntrega === 'entrega'
        && (
            strlen($cep) !== 8
            || $logradouro === ''
            || $numero === ''
            || $bairro === ''
            || $cidade === ''
            || strlen($uf) !== 2
        )
    ) {
        $error = 'Preencha corretamente o endereço de entrega.';
    }

    $produtosMap = [];
    foreach ($products as $p) {
        $produtosMap[(int)$p['id']] = $p['nome'];
    }

    $itens = [];
    $quantidadeTotal = 0;

    foreach ($sabores as $produtoId => $quantidade) {
        $produtoId = (int)$produtoId;
        $quantidade = max(0, min(9999, (int)$quantidade));

        if ($quantidade <= 0 || !isset($produtosMap[$produtoId])) {
            continue;
        }

        $itens[] = [
            'produto_id' => $produtoId,
            'produto_nome' => $produtosMap[$produtoId],
            'quantidade' => $quantidade
        ];

        $quantidadeTotal += $quantidade;
    }

    if (!$error && !$itens) {
        $error = 'Informe a quantidade de pelo menos um sabor.';
    }

    if (!$error) {
        try {
            db()->beginTransaction();

            $st = db()->prepare("
                INSERT INTO encomendas
                (
                    usuario_id,
                    nome,
                    telefone,
                    email,
                    data_evento,
                    tipo_evento,
                    tipo_entrega,
                    cidade,
                    bairro,
                    observacao,
                    quantidade_total,
                    status
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'nova'
                )
            ");

            $observacaoFinal = $observacao;

            if ($tipoEntrega === 'entrega') {
                $enderecoTexto =
                    'Endereço: '
                    . $logradouro
                    . ', ' . $numero
                    . ($complemento ? ' - ' . $complemento : '')
                    . ' - ' . $bairro
                    . ' - ' . $cidade . '/' . $uf
                    . ' - CEP ' . $cep
                    . ($referencia ? ' - Ref.: ' . $referencia : '');

                $observacaoFinal =
                    trim(
                        ($observacaoFinal ? $observacaoFinal . "\n\n" : '')
                        . $enderecoTexto
                    );
            }

            $st->execute([
                $u['id'] ?? null,
                $nome,
                $telefone,
                $email ?: null,
                $dataEvento,
                $tipoEvento ?: null,
                $tipoEntrega,
                $cidade ?: null,
                $bairro ?: null,
                $observacaoFinal ?: null,
                $quantidadeTotal
            ]);

            $encomendaId = (int)db()->lastInsertId();

            $itemSt = db()->prepare("
                INSERT INTO encomenda_itens
                (
                    encomenda_id,
                    produto_id,
                    produto_nome,
                    quantidade
                )
                VALUES (?, ?, ?, ?)
            ");

            foreach ($itens as $item) {
                $itemSt->execute([
                    $encomendaId,
                    $item['produto_id'],
                    $item['produto_nome'],
                    $item['quantidade']
                ]);
            }

            db()->commit();

            audit(
                'encomenda_criada',
                'encomendas',
                $encomendaId,
                $quantidadeTotal . ' unidades'
            );

            $msg = 'Recebemos sua solicitação de encomenda #' . str_pad((string)$encomendaId, 5, '0', STR_PAD_LEFT) . '. Vamos analisar os detalhes antes de confirmar valores e disponibilidade.';

        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $error = 'Não foi possível enviar a encomenda agora. Tente novamente.';
        }
    }
}

$title = 'Encomendas | Doces do Casal';
include __DIR__.'/includes/header.php';
?>

<section class="ddc-order-hero">
    <div class="container">
        <span class="eyebrow">ENCOMENDAS</span>
        <h1>Monte sua encomenda</h1>
        <p>Escolha a data, sabores e quantidades. Retornamos após analisar disponibilidade e entrega.</p>
    </div>
</section>

<section class="section ddc-order-section">
    <div class="container">
        <?php if ($msg): ?>
            <div class="ddc-success"><?=e($msg)?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="ddc-error"><?=e($error)?></div>
        <?php endif; ?>

        <div class="ddc-account-strip">
            <div>
                <strong>Encomenda vinculada à sua conta</strong>
                <small>
                    Seus dados já foram carregados automaticamente. Você só precisa escolher data, recebimento, sabores e quantidades.
                </small>
            </div>
            <a href="/cliente/" class="ddc-account-link">Minha conta</a>
        </div>

        <div class="ddc-form-card ddc-order-card">
            <form method="post">
                <?=csrf_field()?>

                <div class="ddc-order-step">
                    <div class="ddc-order-step-title">
                        <span>1</span>
                        <div>
                            <strong>Seus dados</strong>
                            <small>Dados carregados da sua conta. Você pode ajustá-los somente para esta solicitação.</small>
                        </div>
                    </div>

                    <div class="ddc-form-grid ddc-order-grid">
                    <label>
                        Nome
                        <input
                            name="nome"
                            autocomplete="name"
                            required
                            value="<?=e($_POST['nome'] ?? ($u['nome'] ?? ''))?>"
                        >
                    </label>

                    <label>
                        WhatsApp / telefone
                        <input
                            name="telefone"
                            inputmode="tel"
                            autocomplete="tel"
                            required
                            value="<?=e($_POST['telefone'] ?? ($u['telefone'] ?? ''))?>"
                        >
                    </label>

                    <label>
                        E-mail
                        <input
                            name="email"
                            type="email"
                            autocomplete="email"
                            value="<?=e($_POST['email'] ?? ($u['email'] ?? ''))?>"
                        >
                    </label>

                    </div>
                </div>

                <div class="ddc-order-step">
                    <div class="ddc-order-step-title">
                        <span>2</span>
                        <div>
                            <strong>Data e recebimento</strong>
                            <small>Precisamos de no mínimo 48 horas para preparo.</small>
                        </div>
                    </div>

                    <div class="ddc-form-grid ddc-order-grid">
                    <label>
                        Data desejada
                        <input
                            name="data_evento"
                            type="date"
                            min="<?=e($dataMinimaPagina)?>"
                            required
                            value="<?=e($_POST['data_evento'] ?? '')?>"
                        >
                    </label>

                    <label>
                        Ocasião
                        <select name="tipo_evento">
                            <?php
                            $eventos = [
                                '' => 'Selecione (opcional)',
                                'Aniversário' => 'Aniversário',
                                'Casamento' => 'Casamento',
                                'Empresa' => 'Evento corporativo',
                                'Presente' => 'Presente',
                                'Festa' => 'Festa / confraternização',
                                'Outro' => 'Outro'
                            ];
                            foreach ($eventos as $value => $label):
                            ?>
                                <option
                                    value="<?=e($value)?>"
                                    <?=($_POST['tipo_evento'] ?? '') === $value ? 'selected' : ''?>
                                >
                                    <?=e($label)?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Recebimento
                        <select name="tipo_entrega" id="tipoEntregaEncomenda">
                            <option value="retirada">Retirada</option>
                            <option value="entrega" <?=($_POST['tipo_entrega'] ?? '') === 'entrega' ? 'selected' : ''?>>Entrega</option>
                        </select>
                    </label>

                    <div
                        id="enderecoEntregaEncomenda"
                        class="span-2 ddc-delivery-box"
                        style="display:none"
                    >
                        <div class="ddc-form-grid">
                            <label class="ddc-field-cep">
                                CEP
                                <input
                                    name="cep"
                                    id="cepEncomenda"
                                    maxlength="9"
                                    inputmode="numeric"
                                    autocomplete="postal-code"
                                    value="<?=e($_POST['cep'] ?? '')?>"
                                    placeholder="00000-000"
                                >
                                <small
                                    id="cepStatusEncomenda"
                                    style="display:block;margin-top:6px;color:var(--muted);font-weight:400"
                                >
                                    Informe o CEP para buscar o endereço.
                                </small>
                            </label>

                            <label class="ddc-field-numero">
                                Número
                                <input
                                    name="numero"
                                    id="numeroEncomenda"
                                    value="<?=e($_POST['numero'] ?? '')?>"
                                    placeholder="Ex.: 123"
                                >
                            </label>

                            <label class="ddc-field-rua">
                                Rua / Avenida
                                <input
                                    name="logradouro"
                                    id="logradouroEncomenda"
                                    value="<?=e($_POST['logradouro'] ?? '')?>"
                                    placeholder="Rua ou avenida"
                                >
                            </label>

                            <label class="ddc-field-bairro">
                                Bairro
                                <input
                                    name="bairro"
                                    id="bairroEncomenda"
                                    value="<?=e($_POST['bairro'] ?? '')?>"
                                    placeholder="Bairro"
                                >
                            </label>

                            <label class="ddc-field-cidade">
                                Cidade
                                <input
                                    name="cidade"
                                    id="cidadeEncomenda"
                                    value="<?=e($_POST['cidade'] ?? '')?>"
                                    placeholder="Cidade"
                                >
                            </label>

                            <label class="ddc-field-uf">
                                UF
                                <input
                                    name="uf"
                                    id="ufEncomenda"
                                    maxlength="2"
                                    value="<?=e($_POST['uf'] ?? '')?>"
                                    placeholder="MG"
                                >
                            </label>

                            <label class="ddc-field-complemento">
                                Complemento
                                <input
                                    name="complemento"
                                    id="complementoEncomenda"
                                    value="<?=e($_POST['complemento'] ?? '')?>"
                                    placeholder="Apto, bloco, casa..."
                                >
                            </label>

                            <label class="ddc-field-referencia">
                                Ponto de referência
                                <input
                                    name="referencia"
                                    id="referenciaEncomenda"
                                    value="<?=e($_POST['referencia'] ?? '')?>"
                                    placeholder="Ex.: próximo à praça, portão preto..."
                                >
                            </label>
                        </div>
                    </div>
                </div>

                <div class="ddc-order-step">
                    <div class="ddc-order-step-title">
                        <span>3</span>
                        <div>
                            <strong>Sabores e quantidades</strong>
                            <small>Informe somente os sabores desejados.</small>
                        </div>
                    </div>

                    <div class="ddc-order-flavors">
                        <?php foreach ($products as $p): ?>
                            <label class="ddc-order-flavor-row">
                                <span><?=e($p['nome'])?></span>
                                <input
                                    type="number"
                                    min="0"
                                    max="9999"
                                    name="sabores[<?=$p['id']?>]"
                                    value="<?=e((string)($_POST['sabores'][$p['id']] ?? 0))?>"
                                    aria-label="Quantidade de <?=e($p['nome'])?>"
                                >
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ddc-order-step">
                    <div class="ddc-order-step-title">
                        <span>4</span>
                        <div>
                            <strong>Observações</strong>
                            <small>Opcional — informe detalhes importantes do pedido.</small>
                        </div>
                    </div>

                <label class="ddc-order-observacao">
                    Observações
                    <textarea
                        name="observacao"
                        placeholder="Embalagem especial, mensagem, horário, detalhes da ocasião, restrições ou qualquer informação importante."
                    ><?=e($_POST['observacao'] ?? '')?></textarea>
                </label>
                </div>

                <div class="ddc-order-submit">
                    <div>
                        <strong>Pronto para enviar?</strong>
                        <small>A encomenda será analisada antes da confirmação.</small>
                    </div>
                    <button class="btn primary" type="submit">
                        Enviar solicitação
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>



<style>

.ddc-account-strip{
    max-width:1080px;
    margin:0 auto 14px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    padding:14px 16px;
    border:1px solid var(--line);
    border-radius:16px;
    background:var(--cream);
}
.ddc-account-strip strong{
    display:block;
    color:var(--brown);
    font-size:14px;
}
.ddc-account-strip small{
    display:block;
    margin-top:3px;
    color:var(--muted);
    line-height:1.4;
}
.ddc-account-link{
    flex:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:40px;
    padding:8px 13px;
    border:1px solid var(--line);
    border-radius:12px;
    background:#fff;
    color:var(--brown);
    font-weight:800;
    text-decoration:none;
}
@media (max-width:620px){
    .ddc-account-strip{
        align-items:flex-start;
        flex-direction:column;
    }
    .ddc-account-link{
        width:100%;
    }
}

.ddc-order-hero{
    padding:34px 0 18px;
    border-bottom:1px solid var(--line);
}
.ddc-order-hero .eyebrow{margin-bottom:8px}
.ddc-order-hero h1{
    margin:0 0 8px;
    font-size:clamp(30px,4vw,44px);
    letter-spacing:-.03em;
}
.ddc-order-hero p{
    margin:0;
    max-width:760px;
    color:var(--muted);
    line-height:1.5;
}
.ddc-order-section{padding-top:22px;padding-bottom:48px}
.ddc-order-card{
    max-width:1080px;
    margin:0 auto;
    padding:0;
    overflow:hidden;
}
.ddc-order-step{
    padding:22px 24px;
    border-bottom:1px solid var(--line);
}
.ddc-order-step-title{
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:16px;
}
.ddc-order-step-title>span{
    width:30px;
    height:30px;
    border-radius:50%;
    display:grid;
    place-items:center;
    background:var(--cream);
    color:var(--brown);
    font-weight:800;
    flex:none;
}
.ddc-order-step-title strong{
    display:block;
    font-size:17px;
}
.ddc-order-step-title small{
    display:block;
    margin-top:2px;
    color:var(--muted);
    font-weight:400;
}
.ddc-order-grid{
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px 14px;
}
.ddc-order-grid label{
    margin:0;
}
.ddc-order-grid input,
.ddc-order-grid select,
.ddc-order-grid textarea{
    margin-top:6px;
    padding:10px 12px;
    min-height:44px;
}
.ddc-order-flavors{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
}
.ddc-order-flavor-row{
    display:grid;
    grid-template-columns:1fr 74px;
    align-items:center;
    gap:8px;
    border:1px solid var(--line);
    background:var(--bg);
    border-radius:14px;
    padding:10px 12px;
    margin:0!important;
}
.ddc-order-flavor-row span{
    font-weight:700;
    font-size:14px;
}
.ddc-order-flavor-row input{
    margin:0!important;
    min-height:38px!important;
    padding:7px 8px!important;
    text-align:center;
}
.ddc-order-observacao{
    margin:0!important;
}
.ddc-order-observacao textarea{
    min-height:88px!important;
    margin-top:6px!important;
}
.ddc-order-submit{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:18px;
    padding:18px 24px;
    background:var(--cream);
}
.ddc-order-submit strong{display:block}
.ddc-order-submit small{
    display:block;
    color:var(--muted);
    margin-top:2px;
}
.ddc-order-submit .btn{min-width:190px}
#enderecoEntregaEncomenda{
    margin-top:8px;
}
.ddc-delivery-box{
    padding:14px;
    border:1px solid var(--line);
    border-radius:16px;
    background:var(--bg);
}
#enderecoEntregaEncomenda .ddc-form-grid{
    display:grid;
    grid-template-columns:1.1fr .55fr 1.3fr .9fr;
    gap:10px 12px;
}
#enderecoEntregaEncomenda label{
    margin:0;
}
#enderecoEntregaEncomenda input{
    margin-top:5px;
    min-height:40px;
    padding:8px 10px;
}
.ddc-field-cep{grid-column:span 2}
.ddc-field-numero{grid-column:span 1}
.ddc-field-uf{grid-column:span 1}
.ddc-field-rua{grid-column:span 3}
.ddc-field-bairro{grid-column:span 1}
.ddc-field-cidade{grid-column:span 2}
.ddc-field-complemento{grid-column:span 2}
.ddc-field-referencia{grid-column:1/-1}
#cepStatusEncomenda{
    font-size:11px;
    margin-top:4px!important;
}
@media(max-width:850px){
    #enderecoEntregaEncomenda .ddc-form-grid{
        grid-template-columns:1fr 1fr;
    }
    .ddc-field-cep,
    .ddc-field-numero,
    .ddc-field-uf,
    .ddc-field-rua,
    .ddc-field-bairro,
    .ddc-field-cidade,
    .ddc-field-complemento{
        grid-column:span 1;
    }
    .ddc-field-referencia{grid-column:1/-1}
}
@media(max-width:560px){
    #enderecoEntregaEncomenda .ddc-form-grid{
        grid-template-columns:1fr;
    }
    .ddc-field-referencia{grid-column:span 1}
}

@media(max-width:850px){
    .ddc-order-flavors{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:680px){
    .ddc-order-step{padding:18px}
    .ddc-order-grid{grid-template-columns:1fr}
    .ddc-order-flavors{grid-template-columns:1fr}
    .ddc-order-submit{
        align-items:stretch;
        flex-direction:column;
        padding:16px 18px;
    }
    .ddc-order-submit .btn{width:100%;min-width:0}
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tipoEntrega = document.getElementById('tipoEntregaEncomenda');
    const endereco = document.getElementById('enderecoEntregaEncomenda');

    const cepInput = document.getElementById('cepEncomenda');
    const cepStatus = document.getElementById('cepStatusEncomenda');
    const numeroInput = document.getElementById('numeroEncomenda');
    const logradouroInput = document.getElementById('logradouroEncomenda');
    const bairroInput = document.getElementById('bairroEncomenda');
    const cidadeInput = document.getElementById('cidadeEncomenda');
    const ufInput = document.getElementById('ufEncomenda');

    function atualizarEnderecoEntrega() {
        const entrega = tipoEntrega && tipoEntrega.value === 'entrega';

        if (endereco) {
            endereco.style.display = entrega ? 'block' : 'none';
        }

        [
            cepInput,
            numeroInput,
            logradouroInput,
            bairroInput,
            cidadeInput,
            ufInput
        ].forEach(function (campo) {
            if (campo) {
                campo.required = entrega;
            }
        });
    }

    function setCepStatus(texto, tipo) {
        if (!cepStatus) return;

        cepStatus.textContent = texto;

        cepStatus.style.color =
            tipo === 'success'
                ? 'var(--success)'
                : (
                    tipo === 'error'
                        ? 'var(--danger)'
                        : 'var(--muted)'
                );
    }

    async function buscarCep() {
        if (!cepInput) return;

        const cep = cepInput.value.replace(/\D+/g, '');

        if (cep.length !== 8) {
            setCepStatus('Informe um CEP com 8 números.', 'error');
            return;
        }

        setCepStatus('Buscando endereço...', '');

        try {
            const response = await fetch(
                'https://viacep.com.br/ws/' + cep + '/json/'
            );

            if (!response.ok) {
                throw new Error('Falha na consulta');
            }

            const data = await response.json();

            if (data.erro) {
                setCepStatus('CEP não encontrado. Preencha manualmente.', 'error');
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

            setCepStatus('Endereço encontrado. Complete o número.', 'success');

            if (numeroInput) {
                numeroInput.focus();
            }

        } catch (error) {
            setCepStatus(
                'Não foi possível consultar o CEP agora. Preencha o endereço manualmente.',
                'error'
            );
        }
    }

    if (tipoEntrega) {
        tipoEntrega.addEventListener('change', atualizarEnderecoEntrega);
        atualizarEnderecoEntrega();
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

        cepInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                buscarCep();
            }
        });
    }
});
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
