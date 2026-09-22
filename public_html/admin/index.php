<?php
require_once __DIR__.'/../includes/bootstrap.php';require_admin();
$today=db()->query("SELECT COUNT(*) pedidos,COALESCE(SUM(total),0) faturamento FROM pedidos WHERE status<>'cancelado' AND DATE(criado_em)=CURDATE()")->fetch();
$pending=(int)db()->query("SELECT COUNT(*) FROM pedidos WHERE status IN('aguardando','confirmado','preparando','pronto','saiu_entrega')")->fetchColumn();
$low=(int)db()->query("SELECT COUNT(*) FROM itens_estoque WHERE ativo=1 AND estoque_atual<=estoque_minimo")->fetchColumn();
$encomendasNovas=0;try{$encomendasNovas=(int)db()->query("SELECT COUNT(*) FROM encomendas WHERE status IN('nova','em_analise')")->fetchColumn();}catch(Throwable $e){$encomendasNovas=0;}
$recent=db()->query("SELECT p.id,p.codigo,p.status,p.total,p.criado_em,u.nome cliente FROM pedidos p JOIN usuarios u ON u.id=p.usuario_id ORDER BY p.criado_em DESC LIMIT 10")->fetchAll();
$title='Dashboard';include __DIR__.'/../includes/admin_header.php';
?>
<div class="admin-quick-actions">
  <a class="admin-quick-action" href="/admin/pedidos/"><span>▣</span><div><strong>Ver pedidos</strong><small>Acompanhar produção</small></div></a>
  <a class="admin-quick-action" href="/admin/encomendas/"><span>✦</span><div><strong>Encomendas<?=$encomendasNovas>0?' ('.$encomendasNovas.')':''?></strong><small>Solicitações recebidas</small></div></a>
  <a class="admin-quick-action" href="/admin/produtos/"><span>＋</span><div><strong>Novo produto</strong><small>Preço, foto e estoque</small></div></a>
  <a class="admin-quick-action" href="/admin/estoque/ajustes.php"><span>±</span><div><strong>Ajustar estoque</strong><small>Entrada ou saída</small></div></a>
</div>
<div class="metrics"><div class="metric"><small>Faturamento hoje</small><strong><?=money($today['faturamento'])?></strong></div><div class="metric"><small>Pedidos hoje</small><strong><?=$today['pedidos']?></strong></div><div class="metric"><small>Pedidos em aberto</small><strong><?=$pending?></strong></div><div class="metric"><small>Estoque baixo</small><strong><?=$low?></strong></div></div>
<div class="table-wrap"><table><thead><tr><th>Pedido</th><th>Cliente</th><th>Status</th><th>Total</th><th>Data</th></tr></thead><tbody><?php foreach($recent as $r):?><tr><td><?=e($r['codigo'])?></td><td><?=e($r['cliente'])?></td><td><span class="badge"><?=e($r['status'])?></span></td><td><?=money($r['total'])?></td><td><?=date('d/m H:i',strtotime($r['criado_em']))?></td></tr><?php endforeach;?></tbody></table></div>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
