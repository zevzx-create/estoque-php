<?php
define('ESTOQUE_APP',true);
date_default_timezone_set('America/Sao_Paulo');
require __DIR__.'/funcoes.php';
require __DIR__.'/login.php';
require __DIR__.'/conexao.php';
require __DIR__.'/historico.php';
require __DIR__.'/crud.php';

$db=conectar($config);
$tables=['produtos'=>'Produtos','categorias'=>'Categorias','fornecedores'=>'Fornecedores','locais'=>'Locais'];
$p=$_GET['p']??'dashboard';
if (!is_string($p)||(!isset($tables[$p])&&!in_array($p,['dashboard','historico'],true))) $p='dashboard';
if (isset($tables[$p])) processarCrud($db,$p);
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Estoque Fácil</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<nav>
<strong>Estoque Fácil</strong>
<a href="?p=dashboard">Dashboard</a>
<?php foreach($tables as $k=>$v): ?>
<a href="?p=<?=h($k)?>"><?=h($v)?></a>
<?php endforeach; ?>
<a href="?p=historico">Histórico</a>
<form method="post"><?=token()?><button name="sair">Sair</button></form>
</nav>
<main>
<?php if(isset($_SESSION['msg'])): ?>
<p class="msg"><?=h($_SESSION['msg'])?></p>
<?php unset($_SESSION['msg']); endif; ?>
<?php if(isset($tables[$p])||$p==='dashboard') require __DIR__.'/'.$p.'.php'; else exibirHistorico($db,$tables); ?>
</main>
</body>
</html>
