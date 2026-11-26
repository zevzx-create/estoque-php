<?php
define('ESTOQUE_APP',true);
date_default_timezone_set('America/Sao_Paulo');
require __DIR__.'/funcoes.php';
require __DIR__.'/conexao.php';
require __DIR__.'/historico.php';
require __DIR__.'/crud.php';

if (!is_file(__DIR__.'/config.php')) {
    http_response_code(503);
    exit('Configure config.php antes de acessar o sistema.');
}
$config=require __DIR__.'/config.php';
$db=conectar($config);
require __DIR__.'/login.php';
$usuario=autenticar($db,$config);
$uid=(int)$usuario['id'];
require __DIR__.'/dashboard.php';
require __DIR__.'/produtos.php';
require __DIR__.'/categorias.php';
require __DIR__.'/fornecedores.php';
require __DIR__.'/locais.php';
$tables=['produtos'=>'Produtos','categorias'=>'Categorias','fornecedores'=>'Fornecedores','locais'=>'Locais'];
$p=$_GET['p']??'dashboard';
if (!is_string($p)||(!isset($tables[$p])&&!in_array($p,['dashboard','historico'],true))) $p='dashboard';
if (isset($tables[$p])) processarCrud($db,$p,$uid);
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
<span class="conta"><?=h($usuario['nome'])?> · <?=h($usuario['email'])?></span>
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
<?php
switch($p) {
    case 'dashboard': exibirDashboard($db,$uid); break;
    case 'produtos': paginaProdutos($db,$tables,$uid); break;
    case 'categorias': paginaCategorias($db,$tables,$uid); break;
    case 'fornecedores': paginaFornecedores($db,$tables,$uid); break;
    case 'locais': paginaLocais($db,$tables,$uid); break;
    case 'historico': exibirHistorico($db,$tables,$uid); break;
}
?>
</main>
</body>
</html>
