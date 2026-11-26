<?php
if (!defined('ESTOQUE_APP')) { http_response_code(403); exit; }
function exibirDashboard(PDO $db,int $uid) {

$s=one($db,'SELECT COUNT(*) produtos,COALESCE(SUM(quantidade),0) unidades,COALESCE(SUM(preco*quantidade),0) valor,COALESCE(SUM(quantidade<=5),0) baixo FROM produtos WHERE usuario_id=?',[$uid]);
$metrics=['Produtos'=>$s['produtos'],'Unidades'=>$s['unidades'],'Valor do estoque'=>'R$ '.number_format($s['valor'],2,',','.'),'Estoque baixo'=>$s['baixo']];
foreach(['categorias','fornecedores','locais','historico'] as $t)$metrics[ucfirst($t)]=one($db,"SELECT COUNT(*) n FROM $t WHERE usuario_id=?",[$uid])['n'];
?>
<h1>Painel administrativo</h1>
<div class="cards">
<?php foreach($metrics as $k=>$v):?>
<div class="card"><?=h($k)?>
<strong><?=h($v)?>
</strong>
</div>
<?php endforeach;?>
</div>
<section>
<h2>Estoque por categoria</h2>
<?php foreach(rows($db,'SELECT c.nome,COALESCE(SUM(p.quantidade),0) total FROM categorias c LEFT JOIN produtos p ON p.categoria_id=c.id AND p.usuario_id=c.usuario_id WHERE c.usuario_id=? GROUP BY c.id,c.nome',[$uid]) as $r):?><?=h($r['nome'])?> — <?=h($r['total'])?>
<div class="track">
<div class="bar" style="width:<?=100*$r['total']/max(1,$s['unidades'])?>%">
</div>
</div>
<?php endforeach;?>
</section>
<section>
<h2>Alertas de estoque (até 5 unidades)</h2>
<?php foreach(rows($db,'SELECT nome,quantidade FROM produtos WHERE usuario_id=? AND quantidade<=5 ORDER BY quantidade',[$uid]) as $r):?>
<p><?=h($r['nome'])?> — <?=h($r['quantidade'])?> unidades</p>
<?php endforeach;?>
</section>
<section>
<h2>Operações registradas</h2>
<?php foreach(rows($db,'SELECT operacao,COUNT(*) n FROM historico WHERE usuario_id=? GROUP BY operacao',[$uid]) as $r):?>
<p><?=h($r['operacao'])?>: <?=h($r['n'])?>
</p>
<?php endforeach;?>
<a class="btn" href="?p=historico">Ver histórico completo</a>
</section>


<?php
}
