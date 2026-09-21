<?php
require_once __DIR__.'/../../includes/bootstrap.php';require_admin();$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();foreach(['nome_loja','whatsapp','email_contato','chave_pix','endereco_retirada','pedido_minimo','validade_padrao_dias'] as $k){$v=$_POST[$k]??'';$st=db()->prepare("INSERT INTO configuracoes(chave,valor,tipo,publico) VALUES(?,?,'texto',0) ON DUPLICATE KEY UPDATE valor=VALUES(valor),atualizado_em=NOW()");$st->execute([$k,$v]);}$msg='Configurações salvas.';}
$vals=[];foreach(['nome_loja','whatsapp','email_contato','chave_pix','endereco_retirada','pedido_minimo','validade_padrao_dias'] as $k)$vals[$k]=setting($k,'');
$title='Configurações';include __DIR__.'/../../includes/admin_header.php';
?>
<?php if($msg):?><div class="alert success"><?=$msg?></div><?php endif;?><div class="card"><form method="post"><?=csrf_field()?><div class="grid-2"><?php foreach($vals as $k=>$v):?><label><?=e(ucwords(str_replace('_',' ',$k)))?><input name="<?=e($k)?>" value="<?=e($v)?>"></label><?php endforeach;?></div><button class="btn primary">Salvar</button></form><hr><h3>Logo</h3><p>Para trocar a identidade visual, substitua somente <code>/assets/img/SELO_PNG_SF_DDC.png</code>. Todas as páginas apontam para esse mesmo arquivo.</p></div>
<?php include __DIR__.'/../../includes/admin_footer.php'; ?>
