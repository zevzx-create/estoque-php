<?php
if (!defined('ESTOQUE_APP')) { http_response_code(403); exit; }
function h($v) {
    return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}
function token() {
    return '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">';
}
function redirect($p='dashboard') {
    header('Location: index.php?p='.urlencode($p));
    exit;
}
function rows($db,$sql,$args=[]) {
    $s=$db->prepare($sql);
    $s->execute($args);
    return $s->fetchAll();
}
function one($db,$sql,$args=[]) {
    return rows($db,$sql,$args)[0]??null;
}
