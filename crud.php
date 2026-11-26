<?php
if (!defined('ESTOQUE_APP')) { http_response_code(403); exit; }
function processarCrud(PDO $db,string $p,int $uid) {
 if ($_SERVER['REQUEST_METHOD']!=='POST') return;
 try{
 $db->beginTransaction();
 $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
 $op=$_POST['op']??'';
 if($id===false) throw new RuntimeException('ID inválido.');
 $before=$id?one($db,"SELECT * FROM $p WHERE id=? AND usuario_id=? FOR UPDATE",[$id,$uid]):null;
 if($op==='excluir'){if(!$before)throw new RuntimeException('Registro não encontrado.');$db->prepare("DELETE FROM $p WHERE id=? AND usuario_id=?")->execute([$id,$uid]);$after=null;$action='EXCLUIR';}
 elseif($op==='salvar'){
 $nome=trim(is_string($_POST['nome']??null)?$_POST['nome']:'');if($nome===''||(function_exists('mb_strlen')?mb_strlen($nome,'UTF-8'):strlen($nome))>150)throw new RuntimeException('Nome inválido.');
 $data=['nome'=>$nome];
 if($p==='produtos'){
 $preco=filter_var($_POST['preco']??null,FILTER_VALIDATE_FLOAT);$q=filter_var($_POST['quantidade']??null,FILTER_VALIDATE_INT);
 if($preco===false||$preco<0||$preco>99999999.99||$q===false||$q<0||$q>4294967295)throw new RuntimeException('Preço ou quantidade inválidos.');
 $data+=['preco'=>$preco,'quantidade'=>$q];
 foreach(['categoria_id'=>'categorias','fornecedor_id'=>'fornecedores','local_id'=>'locais'] as $campo=>$tabela){
  $ref=filter_var($_POST[$campo]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if(!$ref||!one($db,"SELECT id FROM $tabela WHERE id=? AND usuario_id=? FOR UPDATE",[$ref,$uid])) throw new RuntimeException('Selecione registros do seu próprio estoque.');
  $data[$campo]=$ref;
 }
 }
 if(!$id) $data['usuario_id']=$uid;
 $cols=array_keys($data);$vals=array_values($data);
 if($id){if(!$before)throw new RuntimeException('Registro não encontrado.');$set=implode(',',array_map(fn($k)=>"$k=?",$cols));$db->prepare("UPDATE $p SET $set WHERE id=? AND usuario_id=?")->execute([...$vals,$id,$uid]);$action='ATUALIZAR';}
 else{$db->prepare("INSERT INTO $p (".implode(',',$cols).") VALUES (".implode(',',array_fill(0,count($cols),'?')).")")->execute($vals);$id=(int)$db->lastInsertId();$action='CRIAR';}
 $after=one($db,"SELECT * FROM $p WHERE id=? AND usuario_id=?",[$id,$uid]);
 }else throw new RuntimeException('Operação inválida.');
 registrarHistorico($db,$uid,$p,$id,$action,$before,$after);
 $db->commit();$_SESSION['msg']='Operação registrada com sucesso.';
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$_SESSION['msg']=$e instanceof RuntimeException && !($e instanceof PDOException)?$e->getMessage():'Não foi possível salvar. Verifique os dados e os relacionamentos.';}
 redirect($p);
}

function exibirCrud(PDO $db,string $p,array $tables,int $uid) {

$edit=isset($_GET['editar'])?one($db,"SELECT * FROM $p WHERE id=? AND usuario_id=?",[(int)$_GET['editar'],$uid]):null;
$list=$p==='produtos'?rows($db,'SELECT p.*,c.nome categoria,f.nome fornecedor,l.nome local FROM produtos p INNER JOIN categorias c ON c.id=p.categoria_id AND c.usuario_id=p.usuario_id INNER JOIN fornecedores f ON f.id=p.fornecedor_id AND f.usuario_id=p.usuario_id INNER JOIN locais l ON l.id=p.local_id AND l.usuario_id=p.usuario_id WHERE p.usuario_id=? ORDER BY p.id DESC',[$uid]):rows($db,"SELECT * FROM $p WHERE usuario_id=? ORDER BY id DESC",[$uid]);
?>
<h1><?=h($tables[$p])?>
</h1>
<section>
<h2><?=$edit?'Editar':'Cadastrar'?>
</h2>
<form method="post"><?=token()?>
<input type="hidden" name="id" value="<?=h($edit['id']??0)?>">
<label>Nome<input name="nome" maxlength="150" required value="<?=h($edit['nome']??'')?>">
</label>
<?php if($p==='produtos'):?>
<label>Preço<input type="number" min="0" max="99999999.99" step="0.01" name="preco" required value="<?=h($edit['preco']??'')?>">
</label>
<label>Quantidade<input type="number" min="0" name="quantidade" required value="<?=h($edit['quantidade']??'')?>">
</label>
<?php foreach(['categoria_id'=>'categorias','fornecedor_id'=>'fornecedores','local_id'=>'locais'] as $field=>$t):?>
<label><?=h($tables[$t])?>
<select required name="<?=h($field)?>">
<option value="">Selecione</option>
<?php foreach(rows($db,"SELECT * FROM $t WHERE usuario_id=? ORDER BY nome",[$uid]) as $r):?>
<option value="<?=h($r['id'])?>" <?=($edit[$field]??null)==$r['id']?'selected':''?>><?=h($r['nome'])?>
</option>
<?php endforeach;?>
</select>
</label>
<?php endforeach;endif;?>
<button name="op" value="salvar">Salvar</button>
<?php if($edit):?>
<a href="?p=<?=h($p)?>">Cancelar</a>
<?php endif;?>
</form>
</section>
<section class="scroll">
<table>
<tr>
<th>ID</th>
<th>Nome</th>
<?php if($p==='produtos'):?>
<th>Preço</th>
<th>Quantidade</th>
<th>Categoria</th>
<th>Fornecedor</th>
<th>Local</th>
<?php endif;?>
<th>Ações</th>
</tr>
<?php foreach($list as $r):?>
<tr>
<td><?=h($r['id'])?>
</td>
<td><?=h($r['nome'])?>
</td>
<?php if($p==='produtos'):?>
<td>R$ <?=number_format($r['preco'],2,',','.')?>
</td>
<td><?=h($r['quantidade'])?>
</td>
<td><?=h($r['categoria'])?>
</td>
<td><?=h($r['fornecedor'])?>
</td>
<td><?=h($r['local'])?>
</td>
<?php endif;?>
<td>
<a class="btn" href="?p=<?=h($p)?>&editar=<?=h($r['id'])?>">Editar</a> <form method="post" onsubmit="return confirm('Excluir este registro?')"><?=token()?>
<input type="hidden" name="id" value="<?=h($r['id'])?>">
<button class="danger" name="op" value="excluir">Excluir</button>
</form>
</td>
</tr>
<?php endforeach;?>
</table>
</section>
<?php
}
