<?php
if (!defined('ESTOQUE_APP')) { http_response_code(403); exit; }
function paginaFornecedores(PDO $db,array $tables,int $uid): void {
    exibirCrud($db,'fornecedores',$tables,$uid);
}
