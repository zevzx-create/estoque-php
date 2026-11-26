<?php
if (!defined('ESTOQUE_APP')) { http_response_code(403); exit; }
function paginaProdutos(PDO $db,array $tables,int $uid): void {
    exibirCrud($db,'produtos',$tables,$uid);
}
