<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
if(!is_file(__DIR__.'/config.php')){http_response_code(503);exit('Configure config.php conforme o README.');}
$c=require __DIR__.'/config.php';
if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
function token(){return '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">';}
function redirect($p='dashboard'){header('Location: index.php?p='.urlencode($p));exit;}
if($_SERVER['REQUEST_METHOD']==='POST'&&!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(403);exit('Solicitação inválida.');}
if(isset($_POST['login'])){
 if($c['admin_password']!=='TROQUE_POR_UMA_SENHA_FORTE'&&hash_equals($c['admin_password'],(string)($_POST['senha']??''))){session_regenerate_id(true);$_SESSION['admin']=true;redirect();}
 $erro='Senha inválida ou não configurada.';
}
if(isset($_POST['sair'])){session_destroy();redirect();}
if(empty($_SESSION['admin'])){?><!doctype html><html lang="pt-br"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="style.css"><main><section><h1>Estoque Fácil — Administração</h1><p><?=h($erro??'Entre para continuar.')?></p><form method="post"><?=token()?><label>Senha<input type="password" name="senha" required></label><button name="login">Entrar</button></form></section></main></html><?php exit;}
try{$db=new PDO('mysql:host='.$c['host'].';dbname='.$c['database'].';charset=utf8mb4',$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}catch(Throwable $e){http_response_code(503);exit('Não foi possível conectar ao banco. Confira config.php.');}
$tables=['produtos'=>'Produtos','categorias'=>'Categorias','fornecedores'=>'Fornecedores','locais'=>'Locais'];
$p=$_GET['p']??'dashboard';if(!isset($tables[$p])&&!in_array($p,['dashboard','historico'],true))$p='dashboard';
function rows($db,$sql,$args=[]){$s=$db->prepare($sql);$s->execute($args);return $s->fetchAll();}
function one($db,$sql,$args=[]){return rows($db,$sql,$args)[0]??null;}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($tables[$p])){
 try{
 $db->beginTransaction();
 $id=(int)($_POST['id']??0);$op=$_POST['op']??'';
 $before=$id?one($db,"SELECT * FROM $p WHERE id=? FOR UPDATE",[$id]):null;
 if($op==='excluir'){if(!$before)throw new RuntimeException('Registro não encontrado.');$db->prepare("DELETE FROM $p WHERE id=?")->execute([$id]);$after=null;$action='EXCLUIR';}
 elseif($op==='salvar'){
 $nome=trim($_POST['nome']??'');if($nome===''||strlen($nome)>150)throw new RuntimeException('Nome inválido.');
 $data=['nome'=>$nome];
 if($p==='produtos'){
 $preco=filter_var($_POST['preco']??null,FILTER_VALIDATE_FLOAT);$q=filter_var($_POST['quantidade']??null,FILTER_VALIDATE_INT);
 if($preco===false||$preco<0||$preco>99999999.99||$q===false||$q<0)throw new RuntimeException('Preço ou quantidade inválidos.');
 $data+=['preco'=>$preco,'quantidade'=>$q,'categoria_id'=>(int)($_POST['categoria_id']??0),'fornecedor_id'=>(int)($_POST['fornecedor_id']??0),'local_id'=>(int)($_POST['local_id']??0)];
 }
 $cols=array_keys($data);$vals=array_values($data);
 if($id){if(!$before)throw new RuntimeException('Registro não encontrado.');$set=implode(',',array_map(fn($k)=>"$k=?",$cols));$db->prepare("UPDATE $p SET $set WHERE id=?")->execute([...$vals,$id]);$action='ATUALIZAR';}
 else{$db->prepare("INSERT INTO $p (".implode(',',$cols).") VALUES (".implode(',',array_fill(0,count($cols),'?')).")")->execute($vals);$id=(int)$db->lastInsertId();$action='CRIAR';}
 $after=one($db,"SELECT * FROM $p WHERE id=?",[$id]);
 }else throw new RuntimeException('Operação inválida.');
 $db->prepare('INSERT INTO historico(tabela,registro_id,operacao,antes,depois) VALUES(?,?,?,?,?)')->execute([$p,$id,$action,$before===null?null:json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$after===null?null:json_encode($after,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
 $db->commit();$_SESSION['msg']='Operação registrada com sucesso.';
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$_SESSION['msg']=$e instanceof PDOException?'Não foi possível salvar. Verifique os dados e os relacionamentos.':$e->getMessage();}
 redirect($p);
}
?><!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Estoque Fácil</title><link rel="stylesheet" href="style.css"></head><body><nav><strong style="color:white">Estoque Fácil</strong><a href="?p=dashboard">Dashboard</a><?php foreach($tables as $k=>$v):?><a href="?p=<?=h($k)?>"><?=h($v)?></a><?php endforeach;?><a href="?p=historico">Histórico</a><form method="post"><?=token()?><button name="sair">Sair</button></form></nav><main>
<?php if(isset($_SESSION['msg'])):?><p class="msg"><?=h($_SESSION['msg'])?></p><?php unset($_SESSION['msg']);endif;?>
<?php if($p==='dashboard'):
$s=one($db,'SELECT COUNT(*) produtos,COALESCE(SUM(quantidade),0) unidades,COALESCE(SUM(preco*quantidade),0) valor,COALESCE(SUM(quantidade<=5),0) baixo FROM produtos');
$metrics=['Produtos'=>$s['produtos'],'Unidades'=>$s['unidades'],'Valor do estoque'=>'R$ '.number_format($s['valor'],2,',','.'),'Estoque baixo'=>$s['baixo']];
foreach(['categorias','fornecedores','locais','historico'] as $t)$metrics[ucfirst($t)]=one($db,"SELECT COUNT(*) n FROM $t")['n'];
?><h1>Painel administrativo</h1><div class="cards"><?php foreach($metrics as $k=>$v):?><div class="card"><?=h($k)?><strong><?=h($v)?></strong></div><?php endforeach;?></div><section><h2>Estoque por categoria</h2><?php foreach(rows($db,'SELECT c.nome,COALESCE(SUM(p.quantidade),0) total FROM categorias c LEFT JOIN produtos p ON p.categoria_id=c.id GROUP BY c.id,c.nome') as $r):?><?=h($r['nome'])?> — <?=h($r['total'])?><div class="track"><div class="bar" style="width:<?=100*$r['total']/max(1,$s['unidades'])?>%"></div></div><?php endforeach;?></section><section><h2>Alertas de estoque (até 5 unidades)</h2><?php foreach(rows($db,'SELECT nome,quantidade FROM produtos WHERE quantidade<=5 ORDER BY quantidade') as $r):?><p><?=h($r['nome'])?> — <?=h($r['quantidade'])?> unidades</p><?php endforeach;?></section><section><h2>Operações registradas</h2><?php foreach(rows($db,'SELECT operacao,COUNT(*) n FROM historico GROUP BY operacao') as $r):?><p><?=h($r['operacao'])?>: <?=h($r['n'])?></p><?php endforeach;?><a class="btn" href="?p=historico">Ver histórico completo</a></section>
<?php elseif($p==='historico'):
$where=[];$args=[];foreach(['tabela','operacao'] as $f)if(!empty($_GET[$f])){$where[]="$f=?";$args[]=$_GET[$f];}
if(!empty($_GET['busca'])){$where[]='(antes LIKE ? OR depois LIKE ? OR CAST(registro_id AS CHAR)=?)';$q=$_GET['busca'];array_push($args,"%$q%","%$q%",$q);}
$hist=rows($db,'SELECT * FROM historico'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY id DESC LIMIT 200',$args);
?><h1>Histórico</h1><section><form><input type="hidden" name="p" value="historico"><label>Tabela<select name="tabela"><option value="">Todas</option><?php foreach($tables as $k=>$v):?><option value="<?=h($k)?>" <?=($_GET['tabela']??'')===$k?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></label><label>Operação<select name="operacao"><option value="">Todas</option><?php foreach(['CRIAR','ATUALIZAR','EXCLUIR'] as $v):?><option <?=($_GET['operacao']??'')===$v?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></label><label>Pesquisa<input name="busca" value="<?=h($_GET['busca']??'')?>"></label><button>Filtrar</button></form></section><section class="scroll"><small>Até 200 registros mais recentes da consulta.</small><table><tr><th>Data (servidor)</th><th>Tabela</th><th>ID</th><th>Operação</th><th>Antes / depois</th></tr><?php foreach($hist as $r):?><tr><td><?=h($r['criado_em'])?></td><td><?=h($r['tabela'])?></td><td><?=h($r['registro_id'])?></td><td><?=h($r['operacao'])?></td><td><details><summary>Ver detalhes</summary><strong>Antes</strong><pre><?=h($r['antes']??'—')?></pre><strong>Depois</strong><pre><?=h($r['depois']??'—')?></pre></details></td></tr><?php endforeach;?></table></section>
<?php else:
$edit=isset($_GET['editar'])?one($db,"SELECT * FROM $p WHERE id=?",[(int)$_GET['editar']]):null;
$list=$p==='produtos'?rows($db,'SELECT p.*,c.nome categoria,f.nome fornecedor,l.nome local FROM produtos p JOIN categorias c ON c.id=p.categoria_id JOIN fornecedores f ON f.id=p.fornecedor_id JOIN locais l ON l.id=p.local_id ORDER BY p.id DESC'):rows($db,"SELECT * FROM $p ORDER BY id DESC");
?><h1><?=h($tables[$p])?></h1><section><h2><?=$edit?'Editar':'Cadastrar'?></h2><form method="post"><?=token()?><input type="hidden" name="id" value="<?=h($edit['id']??0)?>"><label>Nome<input name="nome" maxlength="150" required value="<?=h($edit['nome']??'')?>"></label><?php if($p==='produtos'):?><label>Preço<input type="number" min="0" max="99999999.99" step="0.01" name="preco" required value="<?=h($edit['preco']??'')?>"></label><label>Quantidade<input type="number" min="0" name="quantidade" required value="<?=h($edit['quantidade']??'')?>"></label><?php foreach(['categoria_id'=>'categorias','fornecedor_id'=>'fornecedores','local_id'=>'locais'] as $field=>$t):?><label><?=h($tables[$t])?><select required name="<?=h($field)?>"><option value="">Selecione</option><?php foreach(rows($db,"SELECT * FROM $t ORDER BY nome") as $r):?><option value="<?=h($r['id'])?>" <?=($edit[$field]??null)==$r['id']?'selected':''?>><?=h($r['nome'])?></option><?php endforeach;?></select></label><?php endforeach;endif;?><button name="op" value="salvar">Salvar</button><?php if($edit):?><a href="?p=<?=h($p)?>">Cancelar</a><?php endif;?></form></section><section class="scroll"><table><tr><th>ID</th><th>Nome</th><?php if($p==='produtos'):?><th>Preço</th><th>Quantidade</th><th>Categoria</th><th>Fornecedor</th><th>Local</th><?php endif;?><th>Ações</th></tr><?php foreach($list as $r):?><tr><td><?=h($r['id'])?></td><td><?=h($r['nome'])?></td><?php if($p==='produtos'):?><td>R$ <?=number_format($r['preco'],2,',','.')?></td><td><?=h($r['quantidade'])?></td><td><?=h($r['categoria'])?></td><td><?=h($r['fornecedor'])?></td><td><?=h($r['local'])?></td><?php endif;?><td><a class="btn" href="?p=<?=h($p)?>&editar=<?=h($r['id'])?>">Editar</a> <form method="post" onsubmit="return confirm('Excluir este registro?')"><?=token()?><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="danger" name="op" value="excluir">Excluir</button></form></td></tr><?php endforeach;?></table></section><?php endif;?></main></body></html>