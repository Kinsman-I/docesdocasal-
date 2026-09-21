<?php

require_once __DIR__ . '/../../includes/bootstrap.php';

$admin = require_admin();

$error = '';
$msg = '';

$produtoId = (int)($_POST['produto_id'] ?? $_GET['produto'] ?? 0);

$quantidadePlanejada =
    max(
        0,
        (int)($_POST['quantidade_planejada'] ?? 0)
    );

$quantidadeProduzida =
    max(
        0,
        (int)($_POST['quantidade_produzida'] ?? $quantidadePlanejada)
    );

$dataProducao =
    $_POST['data_producao']
    ?? date('Y-m-d');

$dataValidade =
    $_POST['data_validade']
    ?? date(
        'Y-m-d',
        strtotime('+7 days')
    );

$lote =
    trim(
        $_POST['lote']
        ?? ''
    );

$observacao =
    trim(
        $_POST['observacao']
        ?? ''
    );

$acao =
    $_POST['acao']
    ?? '';


/* =========================================================
   VERIFICA SE O BANCO POSSUI itens_estoque.produto_id
   ========================================================= */

$temProdutoEstoque = false;

try {

    $st =
        db()->query(
            "
            SELECT COUNT(*)

            FROM information_schema.COLUMNS

            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'itens_estoque'
              AND COLUMN_NAME = 'produto_id'
            "
        );

    $temProdutoEstoque =
        (bool)$st->fetchColumn();

} catch (Throwable $e) {

    $temProdutoEstoque = false;

}


/* =========================================================
   PRODUTOS QUE POSSUEM FICHA TÉCNICA
   ========================================================= */

$produtos =
    db()->query(
        "
        SELECT
            p.id,
            p.nome,
            p.preco_venda,
            p.custo_estimado,

            COUNT(r.id) AS itens_ficha

        FROM produtos p

        INNER JOIN receitas_produto r
            ON r.produto_id = p.id

        WHERE p.disponivel = 1

        GROUP BY
            p.id,
            p.nome,
            p.preco_venda,
            p.custo_estimado

        ORDER BY p.nome
        "
    )->fetchAll();


/* =========================================================
   PRODUTO SELECIONADO
   ========================================================= */

$produto = null;

if ($produtoId > 0) {

    $st =
        db()->prepare(
            "
            SELECT
                id,
                nome,
                preco_venda,
                custo_estimado

            FROM produtos

            WHERE id = ?

            LIMIT 1
            "
        );

    $st->execute([
        $produtoId
    ]);

    $produto =
        $st->fetch();

}


/* =========================================================
   GERA LOTE AUTOMÁTICO
   ========================================================= */

if (
    $produto
    && $lote === ''
) {

    $sigla =
        strtoupper(
            preg_replace(
                '/[^A-Z0-9]/',
                '',
                substr(
                    $produto['nome'],
                    0,
                    3
                )
            )
        );

    if ($sigla === '') {
        $sigla = 'PRD';
    }

    $lote =
        date(
            'ymd'
        )
        . '-'
        . $sigla
        . '-'
        . date(
            'His'
        );

}


/* =========================================================
   CARREGA FICHA TÉCNICA
   ========================================================= */

$ficha = [];

if ($produtoId > 0) {

    $st =
        db()->prepare(
            "
            SELECT
                r.id AS receita_id,
                r.quantidade,
                r.perda_percentual,

                i.id AS item_id,
                i.nome AS item_nome,
                i.tipo,
                i.estoque_atual,
                i.estoque_minimo,
                i.custo_medio,

                u.sigla

            FROM receitas_produto r

            INNER JOIN itens_estoque i
                ON i.id = r.item_estoque_id

            INNER JOIN unidades_medida u
                ON u.id = i.unidade_id

            WHERE r.produto_id = ?
              AND i.ativo = 1

            ORDER BY
                i.tipo,
                i.nome
            "
        );

    $st->execute([
        $produtoId
    ]);

    $ficha =
        $st->fetchAll();

}


/* =========================================================
   SIMULAÇÃO DA PRODUÇÃO
   ========================================================= */

$necessidades = [];

$custoPrevisto = 0;

$temFalta = false;


if (
    $produto
    && $quantidadePlanejada > 0
    && $ficha
) {

    foreach ($ficha as $item) {

        /*
         * Quantidade base por unidade
         */
        $base =
            (float)$item['quantidade']
            *
            $quantidadePlanejada;


        /*
         * Acrescenta perda técnica
         */
        $comPerda =
            $base
            *
            (
                1
                +
                (
                    (float)$item['perda_percentual']
                    / 100
                )
            );


        $estoque =
            (float)$item['estoque_atual'];


        $falta =
            max(
                0,
                $comPerda - $estoque
            );


        $apos =
            $estoque - $comPerda;


        $custo =
            $comPerda
            *
            (float)$item['custo_medio'];


        $custoPrevisto +=
            $custo;


        if ($falta > 0) {
            $temFalta = true;
        }


        $necessidades[] = [

            'item_id' =>
                (int)$item['item_id'],

            'nome' =>
                $item['item_nome'],

            'tipo' =>
                $item['tipo'],

            'sigla' =>
                $item['sigla'],

            'necessario' =>
                $comPerda,

            'estoque' =>
                $estoque,

            'apos' =>
                $apos,

            'falta' =>
                $falta,

            'custo_unitario' =>
                (float)$item['custo_medio'],

            'custo_total' =>
                $custo,

            'perda' =>
                (float)$item['perda_percentual']

        ];

    }

}


/* =========================================================
   CONFIRMAR PRODUÇÃO
   ========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $acao === 'confirmar'
) {

    verify_csrf();


    if (!$produto) {

        $error =
            'Produto não encontrado.';

    } elseif (!$ficha) {

        $error =
            'Este produto não possui ficha técnica.';

    } elseif ($quantidadePlanejada <= 0) {

        $error =
            'Informe a quantidade preparada.';

    } elseif ($quantidadeProduzida <= 0) {

        $error =
            'Informe quantas unidades foram produzidas.';

    } elseif ($quantidadeProduzida > $quantidadePlanejada) {

        $error =
            'A quantidade produzida não pode ser maior que a quantidade preparada.';

    } elseif ($temFalta) {

        $error =
            'Não há estoque suficiente para confirmar esta produção.';

    } elseif ($dataValidade < $dataProducao) {

        $error =
            'A validade não pode ser anterior à data de produção.';

    }


    if (!$error) {

        try {

            db()->beginTransaction();


            /* =================================================
               TRAVA E CONFERE ESTOQUE NOVAMENTE
               Evita duas produções consumirem o mesmo saldo
               simultaneamente.
               ================================================= */

            foreach ($necessidades as $n) {

                $lock =
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

                $lock->execute([
                    $n['item_id']
                ]);

                $saldoAtual =
                    $lock->fetch();


                if (!$saldoAtual) {

                    throw new RuntimeException(
                        'Item de estoque não encontrado.'
                    );
                }


                if (
                    (float)$saldoAtual['estoque_atual']
                    <
                    (float)$n['necessario']
                ) {

                    throw new RuntimeException(
                        'Estoque insuficiente para '
                        . $n['nome']
                    );
                }

            }


            /* =================================================
               REGISTRA PRODUÇÃO
               ================================================= */

            $prod =
                db()->prepare(
                    "
                    INSERT INTO producoes
                    (
                        produto_id,
                        lote,
                        quantidade_planejada,
                        quantidade_produzida,
                        data_producao,
                        data_validade,
                        status,
                        observacao,
                        responsavel_id
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'finalizada',
                        ?,
                        ?
                    )
                    "
                );


            $prod->execute([

                $produtoId,

                $lote,

                $quantidadePlanejada,

                $quantidadeProduzida,

                $dataProducao,

                $dataValidade,

                $observacao ?: null,

                $admin['id']

            ]);


            $producaoId =
                (int)db()->lastInsertId();


            /* =================================================
               CONSUMO DOS MATERIAIS
               ================================================= */

            $custoReal =
                0;


            foreach ($necessidades as $n) {


                /*
                 * Busca novamente o custo médio atual
                 * já com o registro travado.
                 */

                $st =
                    db()->prepare(
                        "
                        SELECT custo_medio

                        FROM itens_estoque

                        WHERE id = ?

                        LIMIT 1
                        "
                    );


                $st->execute([
                    $n['item_id']
                ]);


                $custoUnitario =
                    (float)$st->fetchColumn();


                $custoTotal =
                    $n['necessario']
                    *
                    $custoUnitario;


                $custoReal +=
                    $custoTotal;


                /* ---------------------------------------------
                   PRODUÇÃO CONSUMO
                   --------------------------------------------- */

                db()->prepare(
                    "
                    INSERT INTO producao_consumos
                    (
                        producao_id,
                        item_estoque_id,
                        quantidade_prevista,
                        quantidade_consumida,
                        custo_unitario,
                        custo_total
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                    "
                )->execute([

                    $producaoId,

                    $n['item_id'],

                    $n['necessario'],

                    $n['necessario'],

                    $custoUnitario,

                    $custoTotal

                ]);


                /* ---------------------------------------------
                   BAIXA DO ESTOQUE
                   --------------------------------------------- */

                db()->prepare(
                    "
                    UPDATE itens_estoque

                    SET
                        estoque_atual =
                            estoque_atual - ?,

                        atualizado_em =
                            NOW()

                    WHERE id = ?
                    "
                )->execute([

                    $n['necessario'],

                    $n['item_id']

                ]);


                /* ---------------------------------------------
                   MOVIMENTAÇÃO
                   --------------------------------------------- */

                db()->prepare(
                    "
                    INSERT INTO estoque_movimentacoes
                    (
                        item_estoque_id,
                        tipo,
                        quantidade,
                        custo_unitario,
                        referencia_tipo,
                        referencia_id,
                        observacao,
                        usuario_id
                    )
                    VALUES
                    (
                        ?,
                        'saida',
                        ?,
                        ?,
                        'producao',
                        ?,
                        ?,
                        ?
                    )
                    "
                )->execute([

                    $n['item_id'],

                    $n['necessario'],

                    $custoUnitario,

                    $producaoId,

                    'Produção '
                    . $produto['nome']
                    . ' - Lote '
                    . $lote,

                    $admin['id']

                ]);

            }


            /* =================================================
               CUSTO UNITÁRIO REAL
               ================================================= */

            $custoUnitarioProduto =
                $quantidadeProduzida > 0
                    ? $custoReal
                        / $quantidadeProduzida
                    : 0;


            /* =================================================
               ATUALIZA CUSTO DO PRODUTO
               ================================================= */

            db()->prepare(
                "
                UPDATE produtos

                SET
                    custo_estimado = ?,
                    atualizado_em = NOW()

                WHERE id = ?
                "
            )->execute([

                $custoUnitarioProduto,

                $produtoId

            ]);


            /* =================================================
               ENTRADA DO PRODUTO ACABADO
               ================================================= */

            if ($temProdutoEstoque) {

                $st =
                    db()->prepare(
                        "
                        SELECT id

                        FROM itens_estoque

                        WHERE produto_id = ?
                          AND tipo = 'produto_pronto'
                          AND ativo = 1

                        LIMIT 1

                        FOR UPDATE
                        "
                    );


                $st->execute([
                    $produtoId
                ]);


                $itemProdutoPronto =
                    $st->fetchColumn();


                if ($itemProdutoPronto) {


                    /*
                     * Atualiza saldo e custo.
                     */

                    db()->prepare(
                        "
                        UPDATE itens_estoque

                        SET
                            estoque_atual =
                                estoque_atual + ?,

                            custo_medio = ?,

                            atualizado_em =
                                NOW()

                        WHERE id = ?
                        "
                    )->execute([

                        $quantidadeProduzida,

                        $custoUnitarioProduto,

                        $itemProdutoPronto

                    ]);


                    /*
                     * Entrada no histórico.
                     */

                    db()->prepare(
                        "
                        INSERT INTO estoque_movimentacoes
                        (
                            item_estoque_id,
                            tipo,
                            quantidade,
                            custo_unitario,
                            referencia_tipo,
                            referencia_id,
                            observacao,
                            usuario_id
                        )
                        VALUES
                        (
                            ?,
                            'entrada',
                            ?,
                            ?,
                            'producao',
                            ?,
                            ?,
                            ?
                        )
                        "
                    )->execute([

                        $itemProdutoPronto,

                        $quantidadeProduzida,

                        $custoUnitarioProduto,

                        $producaoId,

                        'Entrada produção - lote '
                        . $lote,

                        $admin['id']

                    ]);

                }

            }


            db()->commit();


            audit(
                'producao_finalizada',
                'producoes',
                $producaoId,
                $produto['nome']
                . ' - '
                . $quantidadeProduzida
                . ' unidades - lote '
                . $lote
            );


            header(
                'Location:/admin/producao/?sucesso='
                . $producaoId
            );

            exit;


        } catch (Throwable $e) {

            if (
                db()->inTransaction()
            ) {

                db()->rollBack();

            }


            $error =
                'Não foi possível confirmar a produção: '
                . $e->getMessage();

        }

    }

}


/* =========================================================
   PRODUÇÃO CONCLUÍDA
   ========================================================= */

$sucessoId =
    (int)(
        $_GET['sucesso']
        ?? 0
    );


if ($sucessoId > 0) {

    $msg =
        'Produção registrada com sucesso. Estoque atualizado.';

}


/* =========================================================
   HISTÓRICO RECENTE
   ========================================================= */

$historico =
    db()->query(
        "
        SELECT
            pr.id,
            pr.lote,
            pr.quantidade_planejada,
            pr.quantidade_produzida,
            pr.data_producao,
            pr.data_validade,
            pr.status,

            p.nome AS produto_nome

        FROM producoes pr

        INNER JOIN produtos p
            ON p.id = pr.produto_id

        ORDER BY
            pr.id DESC

        LIMIT 10
        "
    )->fetchAll();


$title =
    'Produção';


include __DIR__
    . '/../../includes/admin_header.php';

?>


<style>

.production-tabs{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-bottom:24px;
}

.production-tab{
    padding:9px 14px;
    border:1px solid var(--line);
    border-radius:999px;
    background:#fff;
    font-weight:700;
    font-size:13px;
}

.production-tab.active{
    background:var(--brown);
    color:#fff;
}


/* =========================================================
   LAYOUT
   ========================================================= */

.production-grid{
    display:grid;
    grid-template-columns:
        minmax(0,1fr)
        360px;
    gap:20px;
    align-items:start;
}


.production-summary{
    display:grid;
    grid-template-columns:
        repeat(3,1fr);
    gap:12px;
    margin:18px 0;
}


.production-metric{
    padding:16px;
    border-radius:16px;
    background:var(--cream);
}


.production-metric small{
    display:block;
    color:var(--muted);
    margin-bottom:5px;
}


.production-metric strong{
    font-size:20px;
}


/* =========================================================
   MATERIAIS
   ========================================================= */

.material-ok{
    color:var(--success);
    font-weight:800;
}


.material-error{
    color:var(--danger);
    font-weight:800;
}


.production-warning{
    padding:16px;
    border-radius:15px;
    background:#f8dedb;
    color:#7d2119;
    margin-top:16px;
}


.production-ready{
    padding:16px;
    border-radius:15px;
    background:#e3f2e8;
    color:#205b35;
    margin-top:16px;
}


.production-confirm{
    margin-top:18px;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media(max-width:900px){

    .production-grid{
        grid-template-columns:1fr;
    }

    .production-summary{
        grid-template-columns:1fr;
    }

}

</style>



<!-- =========================================================
     ABAS
     ========================================================= -->

<div class="production-tabs">

    <a
        class="production-tab active"
        href="/admin/producao/"
    >
        Produzir
    </a>

    <a
        class="production-tab"
        href="/admin/producao/fichas.php"
    >
        Fichas Técnicas
    </a>

    <a
        class="production-tab"
        href="/admin/producao/historico.php"
    >
        Histórico
    </a>

    <a
        class="production-tab"
        href="/admin/producao/etiquetas.php"
    >
        Etiquetas
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



<div class="production-grid">


<!-- =========================================================
     PRODUZIR
     ========================================================= -->

<section>


<div class="card">


    <span class="eyebrow">
        PRODUÇÃO
    </span>


    <h2>
        O que vamos produzir hoje?
    </h2>


    <p style="color:var(--muted)">

        Escolha o produto e informe a quantidade.
        O sistema calcula automaticamente os materiais,
        o estoque e o custo antes de movimentar qualquer item.

    </p>


    <form
        method="post"
        id="productionForm"
    >


        <?=csrf_field()?>


        <input
            type="hidden"
            name="acao"
            value="simular"
        >


        <div class="grid-2">


            <!-- PRODUTO -->

            <label>

                Produto

                <select
                    name="produto_id"
                    required
                >

                    <option value="">
                        Selecione
                    </option>


                    <?php foreach (
                        $produtos
                        as $p
                    ): ?>


                        <option
                            value="<?=$p['id']?>"

                            <?=
                                $produtoId === (int)$p['id']
                                    ? 'selected'
                                    : ''
                            ?>
                        >

                            <?=e($p['nome'])?>

                        </option>


                    <?php endforeach; ?>


                </select>

            </label>



            <!-- QUANTIDADE -->

            <label>

                Quantidade preparada

                <input
                    type="number"
                    name="quantidade_planejada"
                    min="1"
                    value="<?=$quantidadePlanejada ?: ''?>"
                    required
                >

            </label>



            <!-- PRODUZIDO -->

            <label>

                Quantidade realmente produzida

                <input
                    type="number"
                    name="quantidade_produzida"
                    min="1"
                    value="<?=
                        $quantidadeProduzida
                        ?: $quantidadePlanejada
                        ?: ''
                    ?>"
                >

                <small style="color:var(--muted)">
                    Se não houver perda, deixe igual à quantidade preparada.
                </small>

            </label>



            <!-- DATA -->

            <label>

                Data de produção

                <input
                    type="date"
                    name="data_producao"
                    value="<?=e($dataProducao)?>"
                    required
                >

            </label>



            <!-- VALIDADE -->

            <label>

                Validade

                <input
                    type="date"
                    name="data_validade"
                    value="<?=e($dataValidade)?>"
                    required
                >

            </label>



            <!-- LOTE -->

            <label>

                Lote

                <input
                    name="lote"
                    value="<?=e($lote)?>"
                    placeholder="Gerado automaticamente"
                >

            </label>



            <!-- OBSERVAÇÃO -->

            <label
                style="
                    grid-column:1 / -1;
                "
            >

                Observação

                <textarea
                    name="observacao"
                    placeholder="Ex.: produção para encomendas do final de semana"
                ><?=e($observacao)?></textarea>

            </label>


        </div>


        <button
            class="btn primary"
            type="submit"
        >
            Calcular produção
        </button>


    </form>


</div>



<!-- =========================================================
     SIMULAÇÃO
     ========================================================= -->

<?php if (
    $produto
    &&
    $quantidadePlanejada > 0
): ?>


<div
    class="card"
    style="margin-top:20px"
>


    <span class="eyebrow">
        NECESSIDADE DE MATERIAIS
    </span>


    <h2>

        <?=e($produto['nome'])?>

        —

        <?=$quantidadePlanejada?>

        unidades

    </h2>



    <!-- INDICADORES -->

    <div class="production-summary">


        <div class="production-metric">

            <small>
                Custo previsto
            </small>

            <strong>

                <?=money(
                    $custoPrevisto
                )?>

            </strong>

        </div>



        <div class="production-metric">

            <small>
                Custo por unidade
            </small>

            <strong>

                <?=money(
                    $quantidadePlanejada > 0
                        ?
                        $custoPrevisto
                        /
                        $quantidadePlanejada
                        :
                        0
                )?>

            </strong>

        </div>



        <div class="production-metric">

            <small>
                Situação
            </small>

            <strong>

                <?=$temFalta
                    ? 'Falta material'
                    : 'Pronto'
                ?>

            </strong>

        </div>


    </div>



    <!-- MATERIAIS -->

    <div class="table-wrap">


        <table>


            <thead>

                <tr>

                    <th>
                        Material
                    </th>

                    <th>
                        Necessário
                    </th>

                    <th>
                        Estoque
                    </th>

                    <th>
                        Após produção
                    </th>

                    <th>
                        Situação
                    </th>

                    <th>
                        Custo
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php foreach (
                $necessidades
                as $n
            ): ?>


                <tr>


                    <td>

                        <strong>

                            <?=e($n['nome'])?>

                        </strong>

                        <br>

                        <small>

                            <?=e(
                                ucfirst(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $n['tipo']
                                    )
                                )
                            )?>

                        </small>

                    </td>



                    <td>

                        <?=number_format(
                            $n['necessario'],
                            4,
                            ',',
                            '.'
                        )?>

                        <?=e($n['sigla'])?>

                    </td>



                    <td>

                        <?=number_format(
                            $n['estoque'],
                            4,
                            ',',
                            '.'
                        )?>

                        <?=e($n['sigla'])?>

                    </td>



                    <td>

                        <?php if (
                            $n['apos'] >= 0
                        ): ?>

                            <?=number_format(
                                $n['apos'],
                                4,
                                ',',
                                '.'
                            )?>

                            <?=e($n['sigla'])?>

                        <?php else: ?>

                            —

                        <?php endif; ?>

                    </td>



                    <td>


                        <?php if (
                            $n['falta'] > 0
                        ): ?>


                            <span class="material-error">

                                FALTA

                                <?=number_format(
                                    $n['falta'],
                                    4,
                                    ',',
                                    '.'
                                )?>

                                <?=e($n['sigla'])?>

                            </span>


                        <?php else: ?>


                            <span class="material-ok">

                                ✓ OK

                            </span>


                        <?php endif; ?>


                    </td>



                    <td>

                        <?=money(
                            $n['custo_total']
                        )?>

                    </td>


                </tr>


            <?php endforeach; ?>


            </tbody>


        </table>


    </div>



    <?php if ($temFalta): ?>


        <div class="production-warning">

            <strong>
                Não há estoque suficiente.
            </strong>

            <br>

            Os materiais em falta precisam ser comprados
            antes desta produção.

        </div>


        <a
            class="btn outline"
            style="margin-top:12px"
            href="/admin/compras/necessidades.php"
        >

            Ver necessidade de compra

        </a>


    <?php else: ?>


        <div class="production-ready">

            <strong>
                ✓ Material disponível
            </strong>

            <br>

            A produção pode ser confirmada.

        </div>



        <!-- =============================================
             CONFIRMAÇÃO
             ============================================= -->

        <form
            method="post"
            class="production-confirm"
            onsubmit="
                return confirm(
                    'Confirmar esta produção? O estoque dos ingredientes será baixado.'
                );
            "
        >


            <?=csrf_field()?>


            <input
                type="hidden"
                name="acao"
                value="confirmar"
            >


            <input
                type="hidden"
                name="produto_id"
                value="<?=$produtoId?>"
            >


            <input
                type="hidden"
                name="quantidade_planejada"
                value="<?=$quantidadePlanejada?>"
            >


            <input
                type="hidden"
                name="quantidade_produzida"
                value="<?=$quantidadeProduzida?>"
            >


            <input
                type="hidden"
                name="data_producao"
                value="<?=e($dataProducao)?>"
            >


            <input
                type="hidden"
                name="data_validade"
                value="<?=e($dataValidade)?>"
            >


            <input
                type="hidden"
                name="lote"
                value="<?=e($lote)?>"
            >


            <input
                type="hidden"
                name="observacao"
                value="<?=e($observacao)?>"
            >


            <button
                class="btn primary full"
                type="submit"
            >

                Confirmar produção

            </button>


        </form>


    <?php endif; ?>


</div>


<?php endif; ?>


</section>



<!-- =========================================================
     HISTÓRICO RÁPIDO
     ========================================================= -->

<aside>


<div class="card">


    <span class="eyebrow">
        ÚLTIMAS PRODUÇÕES
    </span>


    <h3>
        Histórico recente
    </h3>


    <?php foreach (
        $historico
        as $h
    ): ?>


        <div
            style="
                padding:12px 0;
                border-bottom:1px solid var(--line);
            "
        >


            <strong>

                <?=e(
                    $h['produto_nome']
                )?>

            </strong>


            <br>


            <small
                style="
                    color:var(--muted);
                "
            >

                Lote:

                <?=e($h['lote'])?>

            </small>


            <br>


            <span>

                <?=$h['quantidade_produzida']?>

                unidades

            </span>


            <br>


            <small>

                <?=date(
                    'd/m/Y',
                    strtotime(
                        $h['data_producao']
                    )
                )?>

            </small>


        </div>


    <?php endforeach; ?>


    <?php if (!$historico): ?>

        <p>
            Nenhuma produção registrada ainda.
        </p>

    <?php endif; ?>


</div>


</aside>


</div>


<?php

include __DIR__
    . '/../../includes/admin_footer.php';

?>