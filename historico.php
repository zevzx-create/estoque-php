<?php
if (!defined('ESTOQUE_APP')) { http_response_code(403); exit; }
function registrarHistorico($db,$tabela,$id,$acao,$antes,$depois) {
    $db->prepare('INSERT INTO historico(tabela,registro_id,operacao,antes,depois) VALUES(?,?,?,?,?)')->execute([
        $tabela,$id,$acao,
        $antes===null?null:json_encode($antes,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        $depois===null?null:json_encode($depois,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)
    ]);
}
function exibirHistorico($db,$tables) {

$where=[];$args=[];foreach(['tabela','operacao'] as $f)if(is_string($_GET[$f]??null)&&$_GET[$f]!==''){$where[]="$f=?";$args[]=$_GET[$f];}
if(is_string($_GET['busca']??null)&&$_GET['busca']!==''){$where[]='(antes LIKE ? OR depois LIKE ? OR CAST(registro_id AS CHAR)=?)';$q=$_GET['busca'];array_push($args,"%$q%","%$q%",$q);}
$hist=rows($db,'SELECT * FROM historico'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY id DESC LIMIT 200',$args);
?>
<h1>Histórico</h1>
<section>
<form>
<input type="hidden" name="p" value="historico">
<label>Tabela<select name="tabela">
<option value="">Todas</option>
<?php foreach($tables as $k=>$v):?>
<option value="<?=h($k)?>" <?=($_GET['tabela']??'')===$k?'selected':''?>><?=h($v)?>
</option>
<?php endforeach;?>
</select>
</label>
<label>Operação<select name="operacao">
<option value="">Todas</option>
<?php foreach(['CRIAR','ATUALIZAR','EXCLUIR'] as $v):?>
<option <?=($_GET['operacao']??'')===$v?'selected':''?>><?=h($v)?>
</option>
<?php endforeach;?>
</select>
</label>
<label>Pesquisa<input name="busca" value="<?=h(is_string($_GET['busca']??null)?$_GET['busca']:'')?>">
</label>
<button>Filtrar</button>
</form>
</section>
<section class="scroll">
<small>Até 200 registros mais recentes da consulta.</small>
<table>
<tr>
<th>Data (servidor)</th>
<th>Tabela</th>
<th>ID</th>
<th>Operação</th>
<th>Antes / depois</th>
</tr>
<?php foreach($hist as $r):?>
<tr>
<td><?=h($r['criado_em'])?>
</td>
<td><?=h($r['tabela'])?>
</td>
<td><?=h($r['registro_id'])?>
</td>
<td><?=h($r['operacao'])?>
</td>
<td>
<details>
<summary>Ver detalhes</summary>
<strong>Antes</strong>
<pre><?=h($r['antes']??'—')?>
</pre>
<strong>Depois</strong>
<pre><?=h($r['depois']??'—')?>
</pre>
</details>
</td>
</tr>
<?php endforeach;?>
</table>
</section>

<?php
}
