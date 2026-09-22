<?php
require_once __DIR__.'/../../includes/bootstrap.php';require_admin();$id=(int)($_GET['id']??0);
$st=db()->prepare("SELECT e.* FROM etiquetas e WHERE e.producao_id=? ORDER BY e.numero_etiqueta");$st->execute([$id]);$rows=$st->fetchAll();
if(!$rows){exit('Etiquetas não encontradas.');}
db()->prepare("UPDATE etiquetas SET impressa=1,quantidade_impressoes=quantidade_impressoes+1,primeira_impressao_em=COALESCE(primeira_impressao_em,NOW()),ultima_impressao_em=NOW() WHERE producao_id=?")->execute([$id]);
?>
<!doctype html><html><head><meta charset="utf-8"><title>Etiquetas</title><style>
@page{size:60mm 40mm;margin:0}*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif}.label{width:60mm;height:40mm;padding:2.2mm;page-break-after:always;display:flex;gap:2mm;border:0}.content{flex:1}.logo{width:28mm;height:8mm;object-fit:contain;object-position:left}.name{font-size:12pt;font-weight:bold;margin:1mm 0}.meta{font-size:8.5pt;line-height:1.35}.qr{width:14mm;height:14mm;align-self:flex-end;border:1px solid #222;display:grid;place-items:center;font-size:6pt;text-align:center;padding:1mm}@media screen{body{background:#eee}.label{background:#fff;margin:8px auto;box-shadow:0 2px 10px #aaa}}
</style></head><body><?php foreach($rows as $e):?><div class="label"><div class="content"><img class="logo" src="/assets/img/SELO_PNG_SF_DDC.png"><div class="name"><?=e($e['produto_nome'])?></div><div class="meta">Lote: <b><?=e($e['lote'])?></b><br>Fab.: <?=date('d/m/Y',strtotime($e['data_producao']))?><br>Val.: <?=date('d/m/Y',strtotime($e['data_validade']))?><?php if($e['peso']):?><br>Peso: <?=e($e['peso'])?><?php endif;?></div></div><div class="qr">QR<br><?=e($e['codigo'])?></div></div><?php endforeach;?><script>window.onload=()=>window.print()</script></body></html>
