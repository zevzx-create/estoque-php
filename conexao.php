<?php
if (!defined('ESTOQUE_APP')) { http_response_code(403); exit; }
function conectar($config) {
    try {
        return new PDO('mysql:host='.$config['host'].';dbname='.$config['database'].';charset=utf8mb4',
            $config['user'],$config['password'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false]);
    } catch (Throwable $e) {
        http_response_code(503);
        exit('Não foi possível conectar ao banco. Confira config.php.');
    }
}
