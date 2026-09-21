<?php
require_once __DIR__.'/../../includes/bootstrap.php';
$admin=require_admin();
$msg='';$error='';

function save_setting_value($k,$v){
  $st=db()->prepare("INSERT INTO configuracoes(chave,valor,tipo,publico,atualizado_em) VALUES(?,?,'texto',0,NOW()) ON DUPLICATE KEY UPDATE valor=VALUES(valor),atualizado_em=NOW()");
  $st->execute([$k,$v]);
}
function upload_img($file,$folder,$old=null){
  $maxW = $folder === 'campanhas' ? 1600 : 1800;
  $maxH = $folder === 'campanhas' ? 1200 : 1400;
  return salvar_imagem_otimizada($file,$folder,$old,$maxW,$maxH,82);
}


if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  try{
    $acao=$_POST['acao']??'';
    if($acao==='salvar_home'){
      $hero=upload_img($_FILES['hero_image']??[],'site',setting('hero_image',''));
      $casal=upload_img($_FILES['foto_casal']??[],'site',setting('foto_casal',''));
      save_setting_value('hero_image',$hero??'');
      save_setting_value('foto_casal',$casal??'');
      save_setting_value('titulo_historia',trim($_POST['titulo_historia']??''));
      save_setting_value('texto_historia',trim($_POST['texto_historia']??''));
      $msg='Home atualizada.';
    }
    if($acao==='nova'||$acao==='editar'){
      $id=(int)($_POST['id']??0);$titulo=trim($_POST['titulo']??'');
      if($titulo==='')throw new RuntimeException('Informe o título.');
      $sub=trim($_POST['subtitulo']??'');$tb=trim($_POST['texto_botao']??'');$lb=trim($_POST['link_botao']??'');
      $di=$_POST['data_inicio']?:null;$df=$_POST['data_fim']?:null;$ord=(int)($_POST['ordem']??0);$ativo=isset($_POST['ativo'])?1:0;
      if($acao==='editar'){
        $st=db()->prepare("SELECT * FROM campanhas WHERE id=?");$st->execute([$id]);$c=$st->fetch();if(!$c)throw new RuntimeException('Campanha não encontrada.');
        $img=upload_img($_FILES['imagem']??[],'campanhas',$c['imagem']);
        $st=db()->prepare("UPDATE campanhas SET titulo=?,subtitulo=?,imagem=?,texto_botao=?,link_botao=?,data_inicio=?,data_fim=?,ordem=?,ativo=?,atualizado_em=NOW() WHERE id=?");
        $st->execute([$titulo,$sub?:null,$img,$tb?:null,$lb?:null,$di,$df,$ord,$ativo,$id]);
      }else{
        $img=upload_img($_FILES['imagem']??[],'campanhas');
        if(!$img)throw new RuntimeException('Selecione a imagem.');
        $st=db()->prepare("INSERT INTO campanhas(titulo,subtitulo,imagem,texto_botao,link_botao,data_inicio,data_fim,ordem,ativo,atualizado_em) VALUES(?,?,?,?,?,?,?,?,?,NOW())");
        $st->execute([$titulo,$sub?:null,$img,$tb?:null,$lb?:null,$di,$df,$ord,$ativo]);
      }
      $msg='Campanha salva.';
    }
    if($acao==='toggle'){
      $id=(int)$_POST['id'];db()->prepare("UPDATE campanhas SET ativo=IF(ativo=1,0,1),atualizado_em=NOW() WHERE id=?")->execute([$id]);$msg='Status alterado.';
    }
    if($acao==='delete'){
      $id=(int)$_POST['id'];$st=db()->prepare("SELECT imagem FROM campanhas WHERE id=?");$st->execute([$id]);$img=$st->fetchColumn();
      db()->prepare("DELETE FROM campanhas WHERE id=?")->execute([$id]);
      remover_imagem_upload($img);
      $msg='Campanha excluída.';
    }
  }catch(Throwable $e){$error=$e->getMessage();}
}

$eid=(int)($_GET['editar']??0);$edit=null;
if($eid){$st=db()->prepare("SELECT * FROM campanhas WHERE id=?");$st->execute([$eid]);$edit=$st->fetch();}
$campanhas=db()->query("SELECT * FROM campanhas ORDER BY ordem,id DESC")->fetchAll();
$hero=setting('hero_image','');$casal=setting('foto_casal','');
$titulo=setting('titulo_historia','Nossa história começou com amor — e ganhou sabor.');
$texto=setting('texto_historia',"Tudo começou quando nos conhecemos e nos apaixonamos.\n\nCom o tempo, descobri que a Ana tinha um talento especial: ela fazia doces incríveis.\n\nFoi então que decidimos transformar esse amor em um sonho compartilhado e criamos a Doces do Casal.");
$title='Site / Campanhas';
include __DIR__.'/../../includes/admin_header.php';
?>
<style>
.site-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.site-grid .full{grid-column:1/-1}.preview{width:220px;height:160px;object-fit:cover;border-radius:14px;border:1px solid var(--line);display:block;margin:10px 0}.thumb{width:100px;height:70px;object-fit:cover;border-radius:10px}.upload{border:2px dashed var(--line);padding:16px;border-radius:16px;background:#fffaf6}.help{font-size:12px;color:var(--muted)}@media(max-width:800px){.site-grid{grid-template-columns:1fr}.site-grid .full{grid-column:auto}}
</style>
<?php if($msg):?><div class="alert success"><?=e($msg)?></div><?php endif;?>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>

<div class="card">
<h2>Home / Nossa História</h2>
<form method="post" enctype="multipart/form-data">
<?=csrf_field()?><input type="hidden" name="acao" value="salvar_home">
<div class="site-grid">
<div class="upload"><strong>Foto principal da Home</strong><?php if($hero):?><img class="preview" src="<?=e(imagem_thumbnail_url($hero))?>" loading="lazy" decoding="async"><?php endif;?><input type="file" name="hero_image" accept=".jpg,.jpeg,.png,.webp"><div class="help">Máx. 5 MB. Otimização e WEBP automáticos.</div></div>
<div class="upload"><strong>Foto do casal</strong><?php if($casal):?><img class="preview" src="<?=e(imagem_thumbnail_url($casal))?>" loading="lazy" decoding="async"><?php endif;?><input type="file" name="foto_casal" accept=".jpg,.jpeg,.png,.webp"></div>
<label class="full">Título da história<input name="titulo_historia" value="<?=e($titulo)?>"></label>
<label class="full">Texto da história<textarea name="texto_historia" style="min-height:260px"><?=e($texto)?></textarea></label>
</div>
<button class="btn primary">Salvar Home</button>
</form>
</div>

<div class="card" style="margin-top:18px">
<h2><?=$edit?'Editar campanha':'Nova campanha'?></h2>
<form method="post" enctype="multipart/form-data">
<?=csrf_field()?><input type="hidden" name="acao" value="<?=$edit?'editar':'nova'?>"><?php if($edit):?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif;?>
<div class="site-grid">
<label>Título<input name="titulo" required value="<?=e($edit['titulo']??'')?>"></label>
<label>Ordem<input type="number" name="ordem" value="<?=e((string)($edit['ordem']??0))?>"></label>
<label class="full">Subtítulo<input name="subtitulo" value="<?=e($edit['subtitulo']??'')?>"></label>
<label>Texto do botão<input name="texto_botao" value="<?=e($edit['texto_botao']??'')?>" placeholder="Ex.: Quero conhecer"></label>
<label>Link do botão<input name="link_botao" value="<?=e($edit['link_botao']??'')?>" placeholder="#cardapio"></label>
<label>Data inicial<input type="date" name="data_inicio" value="<?=e($edit['data_inicio']??'')?>"></label>
<label>Data final<input type="date" name="data_fim" value="<?=e($edit['data_fim']??'')?>"></label>
<div class="upload full"><strong>Imagem</strong><?php if($edit&&$edit['imagem']):?><img class="preview" src="<?=e(imagem_thumbnail_url($edit['imagem']))?>" loading="lazy" decoding="async"><?php endif;?><input type="file" name="imagem" <?=$edit?'':'required'?> accept=".jpg,.jpeg,.png,.webp"></div>
</div>
<label style="display:flex;gap:8px;align-items:center"><input style="width:auto;margin:0" type="checkbox" name="ativo" value="1" <?=(!$edit||$edit['ativo'])?'checked':''?>>Ativa</label>
<div class="actions"><button class="btn primary">Salvar campanha</button><?php if($edit):?><a class="btn outline" href="/admin/site/">Cancelar</a><?php endif;?></div>
</form>
</div>

<div class="table-wrap">
<table><thead><tr><th>Imagem</th><th>Campanha</th><th>Período</th><th>Status</th><th>Ações</th></tr></thead><tbody>
<?php foreach($campanhas as $c):?><tr>
<td><img class="thumb" src="<?=e(imagem_thumbnail_url($c['imagem']))?>" loading="lazy" decoding="async"></td>
<td><strong><?=e($c['titulo'])?></strong><br><small><?=e($c['subtitulo']??'')?></small></td>
<td><?=e($c['data_inicio']?:'Sem início')?> → <?=e($c['data_fim']?:'Sem fim')?></td>
<td><span class="badge"><?=$c['ativo']?'Ativa':'Inativa'?></span></td>
<td><div class="actions"><a class="btn outline" href="/admin/site/?editar=<?=$c['id']?>">Editar</a>
<form method="post" style="margin:0"><?=csrf_field()?><input type="hidden" name="acao" value="toggle"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn outline"><?=$c['ativo']?'Desativar':'Ativar'?></button></form>
<form method="post" style="margin:0" onsubmit="return confirm('Excluir esta campanha?')"><?=csrf_field()?><input type="hidden" name="acao" value="delete"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn danger">Excluir</button></form></div></td>
</tr><?php endforeach;?>
<?php if(!$campanhas):?><tr><td colspan="5">Nenhuma campanha cadastrada.</td></tr><?php endif;?>
</tbody></table>
</div>
<?php include __DIR__.'/../../includes/admin_footer.php'; ?>
