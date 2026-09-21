<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = require_admin();

$msg = '';
$error = '';

$id =
    (int)(
        $_GET['id']
        ?? $_POST['id']
        ?? 0
    );


/* =========================================================
   TODOS OS ITENS
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

        WHERE i.ativo = 1

        ORDER BY i.nome
        "
    )->fetchAll();


$item =
    null;


/* =========================================================
   ITEM SELECIONADO
   ========================================================= */

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
   REALIZAR AJUSTE
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();


    $id =
        (int)(
            $_POST['id']
            ?? 0
        );


    $tipo =
        $_POST['tipo_ajuste']
        ?? 'entrada';


    $quantidade =
        (float)str_replace(
            ',',
            '.',
            $_POST['quantidade']
            ?? '0'
        );


    $motivo =
        trim(
            $_POST['motivo']
            ?? ''
        );


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
            'Tipo de ajuste inválido.';

    } elseif ($quantidade <= 0) {

        $error =
            'Informe uma quantidade maior que zero.';

    } elseif ($motivo === '') {

        $error =
            'Informe o motivo do ajuste.';

    }


    if (!$error) {

        try {

            db()->beginTransaction();


            /* =============================================
               TRAVA O ITEM
               ============================================= */

            $st =
                db()->prepare(
                    "
                    SELECT
                        estoque_atual,
                        custo_medio

                    FROM itens_estoque

                    WHERE id = ?

                    FOR UPDATE
                    "
                );


            $st->execute([
                $id
            ]);


            $saldo =
                $st->fetch();


            if (!$saldo) {

                throw new RuntimeException(
                    'Item não encontrado.'
                );

            }


            $saldoAtual =
                (float)$saldo['estoque_atual'];


            /* =============================================
               NÃO PERMITE SALDO NEGATIVO
               ============================================= */

            if (
                $tipo === 'saida'
                &&
                $quantidade > $saldoAtual
            ) {

                throw new RuntimeException(
                    'A saída é maior que o estoque disponível.'
                );

            }


            $novoSaldo =
                $tipo === 'entrada'
                    ? $saldoAtual + $quantidade
                    : $saldoAtual - $quantidade;


            /* =============================================
               ATUALIZA SALDO
               ============================================= */

            db()->prepare(
                "
                UPDATE itens_estoque

                SET
                    estoque_atual = ?,
                    atualizado_em = NOW()

                WHERE id = ?
                "
            )->execute([
                $novoSaldo,
                $id
            ]);


            /* =============================================
               MOVIMENTAÇÃO
               ============================================= */

            db()->prepare(
                "
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
                    ?,
                    ?,
                    ?,
                    'ajuste',
                    ?,
                    ?
                )
                "
            )->execute([

                $id,

                $tipo,

                $quantidade,

                (float)$saldo['custo_medio'],

                $motivo,

                $admin['id']

            ]);


            $movimentacaoId =
                (int)db()->lastInsertId();


            db()->commit();


            audit(
                'estoque_ajustado',
                'estoque_movimentacoes',
                $movimentacaoId,
                $tipo
                . ' '
                . $quantidade
                . ' - '
                . $motivo
            );


            $msg =
                'Estoque ajustado com sucesso.';


            /*
             * Recarrega o item
             */

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


        } catch (Throwable $e) {

            if (
                db()->inTransaction()
            ) {

                db()->rollBack();

            }


            $error =
                $e->getMessage();

        }

    }

}


$title =
    'Ajustar Estoque';


include __DIR__
    . '/../../includes/admin_header.php';

?>


<style>

.adjust-stock-value{
    padding:18px;
    background:var(--cream);
    border-radius:16px;
    margin-bottom:18px;
}

.adjust-stock-value strong{
    display:block;
    font-size:34px;
    color:var(--brown);
}

.adjust-preview{
    margin-top:14px;
    padding:14px;
    border-radius:14px;
    background:#fffaf6;
    border:1px solid var(--line);
}

</style>


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


<div class="card">

    <span class="eyebrow">
        AJUSTE DE ESTOQUE
    </span>


    <h2>
        Correção manual
    </h2>


    <p style="color:var(--muted)">

        Utilize esta função para inventário,
        perdas, acertos ou entradas não originadas
        de uma compra.

    </p>


    <form method="get">


        <label>

            Item

            <select
                name="id"
                onchange="this.form.submit()"
            >

                <option value="">
                    Selecione um item
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

                        —

                        <?=number_format(
                            (float)$i['estoque_atual'],
                            3,
                            ',',
                            '.'
                        )?>

                        <?=e($i['sigla'])?>

                    </option>

                <?php endforeach; ?>

            </select>

        </label>


    </form>

</div>


<?php if ($item): ?>


<div
    class="card"
    style="margin-top:18px"
>


    <div class="adjust-stock-value">

        <small>
            Saldo atual
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


    <form
        method="post"
        id="adjustForm"
    >

        <?=csrf_field()?>


        <input
            type="hidden"
            name="id"
            value="<?=$item['id']?>"
        >


        <div class="grid-2">


            <label>

                Tipo de ajuste

                <select
                    name="tipo_ajuste"
                    id="tipoAjuste"
                >

                    <option value="entrada">
                        Entrada
                    </option>

                    <option value="saida">
                        Saída
                    </option>

                </select>

            </label>


            <label>

                Quantidade

                <input
                    type="number"
                    step=".001"
                    min=".001"
                    name="quantidade"
                    id="quantidadeAjuste"
                    required
                >

            </label>


            <label
                style="grid-column:1 / -1"
            >

                Motivo

                <textarea
                    name="motivo"
                    required
                    placeholder="Ex.: Inventário físico, perda de ingrediente, correção de saldo..."
                ></textarea>

            </label>


        </div>


        <div
            class="adjust-preview"
            id="previewAjuste"
        >

            Informe a quantidade para visualizar
            o novo saldo.

        </div>


        <button
            class="btn primary"
            type="submit"
            style="margin-top:14px"
        >
            Confirmar ajuste
        </button>


    </form>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function(){

        const saldoAtual =
            <?=json_encode(
                (float)$item['estoque_atual']
            )?>;

        const unidade =
            <?=json_encode(
                $item['sigla']
            )?>;

        const tipo =
            document.getElementById(
                'tipoAjuste'
            );

        const quantidade =
            document.getElementById(
                'quantidadeAjuste'
            );

        const preview =
            document.getElementById(
                'previewAjuste'
            );


        function atualizarPreview(){

            const q =
                Number(
                    quantidade.value
                    || 0
                );


            const novo =
                tipo.value === 'entrada'
                    ? saldoAtual + q
                    : saldoAtual - q;


            preview.innerHTML =
                'Saldo atual: <strong>'
                + saldoAtual.toLocaleString(
                    'pt-BR',
                    {
                        minimumFractionDigits:3,
                        maximumFractionDigits:3
                    }
                )
                + ' '
                + unidade
                + '</strong>'
                + '<br>Novo saldo: <strong>'
                + novo.toLocaleString(
                    'pt-BR',
                    {
                        minimumFractionDigits:3,
                        maximumFractionDigits:3
                    }
                )
                + ' '
                + unidade
                + '</strong>';

        }


        tipo.addEventListener(
            'change',
            atualizarPreview
        );


        quantidade.addEventListener(
            'input',
            atualizarPreview
        );


    }
);

</script>


<?php endif; ?>


<?php

include __DIR__
    . '/../../includes/admin_footer.php';

?>