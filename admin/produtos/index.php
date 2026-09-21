<?php
require_once __DIR__.'/../../includes/bootstrap.php';
require_admin();

$msg=''; $error='';

function produto_upload_foto(array $file, ?string $atual=null): ?string {
    return salvar_imagem_otimizada(
        $file,
        'produtos',
        $atual,
        1400,
        1400,
        82
    );
}


if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        $acao = $_POST['acao'] ?? 'novo';

        if ($acao==='novo' || $acao==='editar') {
            $id = (int)($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $preco = (float)str_replace(',','.',$_POST['preco'] ?? '0');
            $custo = (float)str_replace(',','.',$_POST['custo_estimado'] ?? '0');
            $cat = (int)($_POST['categoria_id'] ?? 0);
            $desc = trim($_POST['descricao'] ?? '');
            $disponivel = isset($_POST['disponivel']) ? 1 : 0;
            $destaque = isset($_POST['destaque']) ? 1 : 0;

            if ($nome==='' || $preco<=0) throw new RuntimeException('Informe nome e preço válido.');

            if ($acao==='novo') {
                $imagem = produto_upload_foto($_FILES['imagem'] ?? []);
                $st=db()->prepare("INSERT INTO produtos(categoria_id,nome,descricao,preco_venda,custo_estimado,imagem,disponivel,destaque) VALUES(?,?,?,?,?,?,?,?)");
                $st->execute([$cat?:null,$nome,$desc,$preco,$custo,$imagem,$disponivel,$destaque]);
                $msg='Produto cadastrado com sucesso.';
            } else {
                $st=db()->prepare("SELECT * FROM produtos WHERE id=? LIMIT 1");
                $st->execute([$id]); $p=$st->fetch();
                if (!$p) throw new RuntimeException('Produto não encontrado.');

                $imagem = produto_upload_foto($_FILES['imagem'] ?? [], $p['imagem']);

                if (!empty($_POST['remover_imagem'])) {
                    remover_imagem_upload($imagem);
                    $imagem=null;
                }

                $st=db()->prepare("UPDATE produtos SET categoria_id=?,nome=?,descricao=?,preco_venda=?,custo_estimado=?,imagem=?,disponivel=?,destaque=?,atualizado_em=NOW() WHERE id=?");
                $st->execute([$cat?:null,$nome,$desc,$preco,$custo,$imagem,$disponivel,$destaque,$id]);
                $msg='Produto atualizado com sucesso.';
            }
        }

        if ($acao==='toggle') {
            $id=(int)$_POST['id'];
            db()->prepare("UPDATE produtos SET disponivel=IF(disponivel=1,0,1),atualizado_em=NOW() WHERE id=?")->execute([$id]);
            $msg='Disponibilidade alterada.';
        }
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}

$editarId=(int)($_GET['editar'] ?? 0);
$editando=null;
if ($editarId) {
    $st=db()->prepare("SELECT * FROM produtos WHERE id=? LIMIT 1");
    $st->execute([$editarId]);
    $editando=$st->fetch();
}

$cats=db()->query("SELECT id,nome FROM categorias WHERE ativo=1 ORDER BY ordem,nome")->fetchAll();
$rows=db()->query("SELECT p.*,c.nome categoria FROM produtos p LEFT JOIN categorias c ON c.id=p.categoria_id ORDER BY p.nome")->fetchAll();

$title='Produtos';
include __DIR__.'/../../includes/admin_header.php';
?>
<style>
.produto-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px 20px}
.produto-form-grid .full-row{grid-column:1/-1}
.upload-box{border:2px dashed var(--line);border-radius:18px;padding:20px;background:#fffaf6}
.upload-preview{width:180px;height:150px;object-fit:cover;border-radius:16px;border:1px solid var(--line);display:block;margin:12px 0}
.produto-thumb{width:80px;height:68px;object-fit:cover;border-radius:12px;border:1px solid var(--line)}
.produto-sem-foto{width:80px;height:68px;border:1px dashed var(--line);border-radius:12px;display:grid;place-items:center;color:var(--muted);font-size:11px;text-align:center}
.produto-checks{display:flex;gap:18px;flex-wrap:wrap;margin:18px 0}
.produto-checks label{margin:0;display:flex;align-items:center;gap:8px}
.produto-checks input{width:auto;margin:0}
@media(max-width:800px){.produto-form-grid{grid-template-columns:1fr}.produto-form-grid .full-row{grid-column:auto}}
</style>

<?php if($msg):?><div class="alert success"><?=e($msg)?></div><?php endif;?>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>

<div class="card">
<h3><?=$editando?'Editar produto':'Novo produto'?></h3>

<form method="post" enctype="multipart/form-data">
<?=csrf_field()?>
<input type="hidden" name="acao" value="<?=$editando?'editar':'novo'?>">
<?php if($editando):?><input type="hidden" name="id" value="<?=$editando['id']?>"><?php endif;?>

<div class="produto-form-grid">
<label>Nome<input name="nome" required value="<?=e($editando['nome'] ?? '')?>"></label>
<label>Preço<input name="preco" type="number" min=".01" step=".01" required value="<?=e($editando['preco_venda'] ?? '')?>"></label>

<label>Categoria
<select name="categoria_id">
<option value="">Sem categoria</option>
<?php foreach($cats as $c):?>
<option value="<?=$c['id']?>" <?=($editando && (int)$editando['categoria_id']===(int)$c['id'])?'selected':''?>><?=e($c['nome'])?></option>
<?php endforeach;?>
</select>
</label>

<label>Custo estimado<input name="custo_estimado" type="number" min="0" step=".01" value="<?=e($editando['custo_estimado'] ?? '0.00')?>"></label>

<label class="full-row">Descrição<textarea name="descricao"><?=e($editando['descricao'] ?? '')?></textarea></label>

<div class="full-row upload-box">
<strong>Foto do produto</strong>

<?php if($editando && $editando['imagem']):?>
<img class="upload-preview" src="<?=e(imagem_thumbnail_url($editando['imagem']))?>" alt="" loading="lazy" decoding="async">
<label style="display:flex;align-items:center;gap:8px">
<input style="width:auto;margin:0" type="checkbox" name="remover_imagem" value="1">
Remover foto atual
</label>
<?php endif;?>

<input type="file" name="imagem" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
<small style="display:block;margin-top:8px;color:var(--muted)">JPG, PNG ou WEBP • máximo 5 MB. O sistema otimiza e converte automaticamente para WEBP.</small>
</div>
</div>

<div class="produto-checks">
<label><input type="checkbox" name="disponivel" value="1" <?=(!$editando || !empty($editando['disponivel']))?'checked':''?>>Disponível para venda</label>
<label><input type="checkbox" name="destaque" value="1" <?=($editando && !empty($editando['destaque']))?'checked':''?>>Produto em destaque</label>
</div>

<div class="actions">
<button class="btn primary"><?=$editando?'Salvar alterações':'Cadastrar produto'?></button>
<?php if($editando):?><a class="btn outline" href="/admin/produtos/">Cancelar</a><?php endif;?>
</div>
</form>
</div>

<div class="table-wrap">
<table>
<thead><tr><th>Foto</th><th>Produto</th><th>Categoria</th><th>Preço</th><th>Custo</th><th>Disponível</th><th>Ações</th></tr></thead>
<tbody>
<?php foreach($rows as $r):?>
<tr>
<td>
<?php if($r['imagem']):?>
<img class="produto-thumb" src="<?=e(imagem_thumbnail_url($r['imagem']))?>" alt="" loading="lazy" decoding="async">
<?php else:?><div class="produto-sem-foto">Sem<br>foto</div><?php endif;?>
</td>
<td><strong><?=e($r['nome'])?></strong><?php if($r['destaque']):?><br><span class="badge">Destaque</span><?php endif;?></td>
<td><?=e($r['categoria'] ?? '-')?></td>
<td><?=money($r['preco_venda'])?></td>
<td><?=money($r['custo_estimado'])?></td>
<td><span class="badge"><?=$r['disponivel']?'Ativo':'Indisponível'?></span></td>
<td>
<div class="actions">
<a class="btn outline" href="/admin/produtos/?editar=<?=$r['id']?>">Editar</a>
<form method="post" style="margin:0">
<?=csrf_field()?>
<input type="hidden" name="acao" value="toggle">
<input type="hidden" name="id" value="<?=$r['id']?>">
<button class="btn outline"><?=$r['disponivel']?'Desativar':'Ativar'?></button>
</form>
</div>
</td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>

<?php include __DIR__.'/../../includes/admin_footer.php'; ?>
