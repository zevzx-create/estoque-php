<?php
if (!defined('ESTOQUE_APP')) { http_response_code(403); exit; }
function paginaLocais(PDO $db,array $tables,int $uid): void {
    exibirCrud($db,'locais',$tables,$uid);
}
