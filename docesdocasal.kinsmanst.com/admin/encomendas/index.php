<?php
require_once __DIR__.'/../../includes/bootstrap.php';

$admin = require_admin();
$msg = '';
$error = '';

$statusPermitidos = [
    'nova',
    'em_analise',
    'orcamento_enviado',
    'aprovada',
    'recusada',
    'concluida',
    'cancelada'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $acao = trim((string)($_POST['acao'] ?? 'atualizar_status'));

    /*
     * Exclusão protegida:
     * somente uma encomenda já cancelada pode ser apagada.
     * Como encomenda_itens não possui FK com ON DELETE CASCADE,
     * os itens são removidos manualmente na mesma transação.
     */
    if ($acao === 'excluir') {
        if ($id <= 0) {
            $error = 'Encomenda inválida.';
        } else {
            try {
                db()->beginTransaction();

                $st = db()->prepare("
                    SELECT id, status, nome
                    FROM encomendas
                    WHERE id = ?
                    FOR UPDATE
                ");
                $st->execute([$id]);
                $encomendaExcluir = $st->fetch();

                if (!$encomendaExcluir) {
                    throw new RuntimeException('Encomenda não encontrada.');
                }

                if ($encomendaExcluir['status'] !== 'cancelada') {
                    throw new RuntimeException(
                        'Por segurança, altere a encomenda para Cancelada antes de excluí-la.'
                    );
                }

                db()->prepare(
                    "DELETE FROM encomenda_itens WHERE encomenda_id = ?"
                )->execute([$id]);

                db()->prepare(
                    "DELETE FROM encomendas WHERE id = ?"
                )->execute([$id]);

                db()->commit();

                audit(
                    'encomenda_excluida',
                    'encomendas',
                    $id,
                    'Encomenda #' . $id . ' excluída pelo administrador'
                );

                $msg = 'Encomenda excluída com sucesso.';
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    } else {
        $status = $_POST['status'] ?? '';

        if ($id > 0 && in_array($status, $statusPermitidos, true)) {
            db()->prepare("
                UPDATE encomendas
                SET status = ?, atualizado_em = NOW()
                WHERE id = ?
            ")->execute([$status, $id]);

            audit(
                'encomenda_status',
                'encomendas',
                $id,
                $status
            );

            $msg = 'Status atualizado.';
        }
    }
}

$filtro = $_GET['status'] ?? '';

$where = 'WHERE 1=1';
$params = [];

if (in_array($filtro, $statusPermitidos, true)) {
    $where .= ' AND e.status = ?';
    $params[] = $filtro;
}

$st = db()->prepare("
    SELECT
        e.*,
        (
            SELECT GROUP_CONCAT(
                CONCAT(ei.quantidade, 'x ', ei.produto_nome)
                ORDER BY ei.id
                SEPARATOR ' • '
            )
            FROM encomenda_itens ei
            WHERE ei.encomenda_id = e.id
        ) AS itens
    FROM encomendas e
    $where
    ORDER BY e.criado_em DESC
    LIMIT 200
");
$st->execute($params);
$rows = $st->fetchAll();

$title = 'Encomendas';
include __DIR__.'/../../includes/admin_header.php';
?>

<style id="encomendas-mobile-fix">
@media (max-width: 620px){
    .encomendas-toolbar{align-items:stretch!important}
    .encomendas-toolbar form{width:100%}
    .encomendas-toolbar label{display:block}
}
.encomenda-actions{
    display:flex;
    flex-direction:column;
    gap:7px;
    min-width:125px;
}
.encomenda-delete-btn{
    min-height:40px;
    padding:8px 11px;
    border:1px solid #f0b7b3;
    border-radius:12px;
    background:#fff5f4;
    color:#9f241d;
    font-weight:800;
    cursor:pointer;
}
.encomenda-delete-btn:hover{
    background:#ffe7e4;
}
.encomenda-delete-note{
    display:block;
    max-width:155px;
    color:#7d6258;
    font-size:10px;
    line-height:1.3;
}
</style>

<?php if ($msg): ?><div class="alert success"><?=e($msg)?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>

<div class="encomendas-toolbar" style="display:flex;justify-content:space-between;gap:15px;align-items:end;flex-wrap:wrap;margin-bottom:20px">
    <div>
        <span class="eyebrow">ENCOMENDAS</span>
        <h2 style="margin:0">Solicitações recebidas</h2>
    </div>

    <form method="get">
        <label>
            Status
            <select name="status" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php foreach ($statusPermitidos as $s): ?>
                    <option value="<?=$s?>" <?=$filtro === $s ? 'selected' : ''?>>
                        <?=e(ucfirst(str_replace('_',' ',$s)))?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
</div>

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>Encomenda</th>
            <th>Cliente</th>
            <th>Data</th>
            <th>Itens</th>
            <th>Recebimento</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>

    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td>
                <strong>#<?=str_pad((string)$r['id'],5,'0',STR_PAD_LEFT)?></strong>
                <br>
                <small><?=date('d/m/Y H:i', strtotime($r['criado_em']))?></small>
            </td>

            <td>
                <strong><?=e($r['nome'])?></strong>
                <br>
                <small><?=e($r['telefone'])?></small>
                <?php if ($r['email']): ?><br><small><?=e($r['email'])?></small><?php endif; ?>
            </td>

            <td>
                <strong><?=date('d/m/Y', strtotime($r['data_evento']))?></strong>
                <?php if ($r['tipo_evento']): ?><br><small><?=e($r['tipo_evento'])?></small><?php endif; ?>
            </td>

            <td style="max-width:320px">
                <?=e($r['itens'] ?: '—')?>
                <br>
                <small>Total: <?=(int)$r['quantidade_total']?> un.</small>
                <?php if ($r['observacao']): ?>
                    <details style="margin-top:6px">
                        <summary>Observações</summary>
                        <small><?=nl2br(e($r['observacao']))?></small>
                    </details>
                <?php endif; ?>
            </td>

            <td>
                <?=e(ucfirst($r['tipo_entrega']))?>
                <?php if ($r['cidade'] || $r['bairro']): ?>
                    <br><small><?=e(trim(($r['bairro'] ?: '') . ' ' . ($r['cidade'] ?: '')))?></small>
                <?php endif; ?>
            </td>

            <td>
                <form method="post">
                    <?=csrf_field()?>
                    <input type="hidden" name="id" value="<?=$r['id']?>">
                    <input type="hidden" name="acao" value="atualizar_status">
                    <select name="status" onchange="this.form.submit()">
                        <?php foreach ($statusPermitidos as $s): ?>
                            <option value="<?=$s?>" <?=$r['status'] === $s ? 'selected' : ''?>>
                                <?=e(ucfirst(str_replace('_',' ',$s)))?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </td>

            <td>
                <div class="encomenda-actions">
                    <?php if ($r['status'] === 'cancelada'): ?>
                        <form
                            method="post"
                            onsubmit="return confirm('Excluir definitivamente esta encomenda? Esta ação não poderá ser desfeita.');"
                        >
                            <?=csrf_field()?>
                            <input type="hidden" name="id" value="<?=$r['id']?>">
                            <input type="hidden" name="acao" value="excluir">

                            <button type="submit" class="encomenda-delete-btn">
                                Excluir encomenda
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="encomenda-delete-note">
                            Para excluir, altere primeiro o status para <strong>Cancelada</strong>.
                        </span>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>

    <?php if (!$rows): ?>
        <tr><td colspan="7" style="text-align:center;padding:35px">Nenhuma encomenda encontrada.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<?php include __DIR__.'/../../includes/admin_footer.php'; ?>
