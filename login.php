<?php
if (!defined('ESTOQUE_APP')) { http_response_code(403); exit; }
session_set_cookie_params([
    'lifetime'=>0,'path'=>'/',
    'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off',
    'httponly'=>true,'samesite'=>'Lax'
]);
ini_set('session.use_strict_mode','1');
session_start();
header('Cache-Control: no-store');
if (!is_file(__DIR__.'/config.php')) {
    http_response_code(503);
    exit('Copie config.example.php para config.php e configure o banco e a senha administrativa.');
}
$config=require __DIR__.'/config.php';
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));

function limitarLogin($limpar=false) {
    $file=@fopen(__DIR__.'/.login-attempts.php','c+');
    if (!$file) return false;
    $prefix="<?php exit; ?>\n";
    try {
        if (!flock($file,LOCK_EX)) return false;
        rewind($file);
        $raw=stream_get_contents($file);
        if ($raw!==''&&strpos($raw,$prefix)!==0) return false;
        $data=$raw===''?[]:json_decode(substr($raw,strlen($prefix)),true);
        if (!is_array($data)) return false;
        $now=time();
        foreach ($data as $key=>$entry) {
            if (($entry['until']??0)<=$now) unset($data[$key]);
        }
        $ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');
        $entry=$data[$ip]??['count'=>0,'until'=>$now+900];
        if ($limpar) unset($data[$ip]);
        else {
            if ($entry['count']>=5) return false;
            $entry['count']++;
            $data[$ip]=$entry;
        }
        $payload=$prefix.json_encode($data,JSON_THROW_ON_ERROR);
        rewind($file);
        if (!ftruncate($file,0)||fwrite($file,$payload)!==strlen($payload)||!fflush($file)) return false;
        return true;
    } catch (Throwable $e) {
        return false;
    } finally {
        flock($file,LOCK_UN);
        fclose($file);
    }
}
function migrarSenha(&$config,$senha) {
    $temp=null;
    try {
        $novo=$config;
        $novo['admin_password_hash']=password_hash($senha,PASSWORD_DEFAULT);
        unset($novo['admin_password']);
        $temp=__DIR__.'/.config-'.bin2hex(random_bytes(12)).'.php';
        $conteudo="<?php\nreturn ".var_export($novo,true).";\n";
        if (@file_put_contents($temp,$conteudo)!==strlen($conteudo)) return false;
        @chmod($temp,0600);
        if (!@rename($temp,__DIR__.'/config.php')) return false;
        $config=$novo;
        if (function_exists('opcache_invalidate')) @opcache_invalidate(__DIR__.'/config.php',true);
        return true;
    } catch (Throwable $e) {
        return false;
    } finally {
        if ($temp!==null&&is_file($temp)) @unlink($temp);
    }
}
if ($_SERVER['REQUEST_METHOD']==='POST'&&
    (!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))) {
    http_response_code(403);
    exit('Solicitação inválida.');
}
if (isset($_POST['sair'])) {
    $_SESSION=[];
    $params=session_get_cookie_params();
    setcookie(session_name(),'',time()-42000,$params['path'],$params['domain'],$params['secure'],$params['httponly']);
    session_destroy();
    redirect();
}
if (isset($_POST['login'])) {
    $senha=is_string($_POST['senha']??null)?$_POST['senha']:'';
    if (!limitarLogin()) {
        $erro='Limite de tentativas atingido ou proteção indisponível. Tente novamente em 15 minutos.';
    } else {
        $hash=$config['admin_password_hash']??'';
        $antiga=$config['admin_password']??'';
        $legado=$hash===''&&is_string($antiga)&&$antiga!==''&&$antiga!=='TROQUE_POR_UMA_SENHA_FORTE';
        $valida=$senha!==''&&($legado?hash_equals($antiga,$senha):password_verify($senha,$hash));
        if ($valida) {
            limitarLogin(true);
            session_regenerate_id(true);
            $_SESSION['admin']=true;
            $_SESSION['csrf']=bin2hex(random_bytes(32));
            if ($legado&&!migrarSenha($config,$senha)) $_SESSION['msg']='Login realizado. Não foi possível converter a senha antiga em hash. Atualize config.php manualmente.';
            redirect();
        }
        $erro='Senha inválida ou não configurada.';
    }
}
if (empty($_SESSION['admin'])) {
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Estoque Fácil — Login</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="login">
<section>
<h1>Estoque Fácil — Administração</h1>
<p><?=h($erro??'Entre para continuar.')?></p>
<form method="post">
<?=token()?>
<label>Senha<input type="password" name="senha" autocomplete="current-password" required></label>
<button name="login">Entrar</button>
</form>
</section>
</main>
</body>
</html>
<?php
    exit;
}
