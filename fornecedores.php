<?php
if (!defined('ESTOQUE_APP')) { http_response_code(403); exit; }
exibirCrud($db,'fornecedores',$tables);
