<?php
if (!defined('ESTOQUE_APP')) { http_response_code(403); exit; }
function limitarTentativas(PDO $db,string $tipo,int $limite): bool {
    $chave=hash('sha256',$tipo.'|'.($_SERVER['REMOTE_ADDR']??'unknown'));
    $janela=intdiv(time(),900);
    $db->prepare('DELETE FROM tentativas_login WHERE janela < ?')->execute([$janela-1]);
    $db->prepare('INSERT INTO tentativas_login(chave,janela,tentativas) VALUES(?,?,1) ON DUPLICATE KEY UPDATE tentativas=LEAST(tentativas+1,1000000)')->execute([$chave,$janela]);
    $r=one($db,'SELECT tentativas FROM tentativas_login WHERE chave=? AND janela=?',[$chave,$janela]);
    return (int)$r['tentativas']<=$limite;
}
function senhaLegadaValida(array $config,string $senha): bool {
    if($senha==='') return false;
    $hash=$config['admin_password_hash']??'';
    if(is_string($hash)&&$hash!==''&&$hash!=='COLE_O_HASH_GERADO_COM_PASSWORD_HASH') return password_verify($senha,$hash);
    $antiga=$config['admin_password']??'';
    return is_string($antiga)&&$antiga!==''&&$antiga!=='TROQUE_POR_UMA_SENHA_FORTE'&&hash_equals($antiga,$senha);
}
function campoPost(string $nome): string {
    return is_string($_POST[$nome]??null)?$_POST[$nome]:'';
}
function autenticar(PDO $db,array $config): array {
    session_set_cookie_params([
        'lifetime'=>0,'path'=>'/',
        'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off',
        'httponly'=>true,'samesite'=>'Lax'
    ]);
    ini_set('session.use_strict_mode','1');
    session_start();
    header('Cache-Control: no-store');
    if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
    $cadastro=($_GET['p']??'')==='cadastro';
    $usuario=null;
    try {
        if(!empty($_SESSION['usuario_id'])) $usuario=one($db,'SELECT id,nome,email FROM usuarios WHERE id=?',[(int)$_SESSION['usuario_id']]);
        else one($db,'SELECT id FROM usuarios LIMIT 1');
    } catch(PDOException $e) {
        http_response_code(503);
        exit('O banco ainda não foi atualizado. Importe migracao_multiusuario.sql no phpMyAdmin.');
    }
    if(!$usuario) unset($_SESSION['usuario_id'],$_SESSION['admin']);
    if($_SERVER['REQUEST_METHOD']==='POST'&&(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))) {
        $_SESSION['msg']='O formulário expirou. Tente novamente.';
        $destino=$usuario?($_GET['p']??'dashboard'):($cadastro?'cadastro':'login');
        redirect(is_string($destino)?$destino:'dashboard');
    }
    if(isset($_POST['sair'])) {
        $_SESSION=[];
        $params=session_get_cookie_params();
        setcookie(session_name(),'',time()-42000,$params['path'],$params['domain'],$params['secure'],$params['httponly']);
        session_destroy();
        redirect('login');
    }
    if($usuario) return $usuario;
    $erro='';
    $email=strtolower(trim(campoPost('email')));
    $nome=trim(campoPost('nome'));
    $senha=campoPost('senha');
    $podeLegado=(!empty($config['admin_password'])||!empty($config['admin_password_hash']))&&!one($db,'SELECT id FROM usuarios WHERE legado=1');
    if($_SERVER['REQUEST_METHOD']==='POST'&&(isset($_POST['login'])||isset($_POST['cadastrar']))) {
        $cadastro=isset($_POST['cadastrar']);
        try {
            if(!limitarTentativas($db,$cadastro?'cadastro':'login',$cadastro?10:5)) throw new RuntimeException('Muitas tentativas. Aguarde até 15 minutos.');
            if($cadastro) {
                if($nome===''||(function_exists('mb_strlen')?mb_strlen($nome,'UTF-8'):strlen($nome))>150) throw new RuntimeException('Informe o nome do depósito.');
                if(strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe um e-mail válido.');
                if(strlen($senha)<8||strlen($senha)>72) throw new RuntimeException('A senha deve ter de 8 a 72 bytes.');
                if($senha!==campoPost('confirmar')) throw new RuntimeException('As senhas não coincidem.');
                $reivindicar=isset($_POST['importar_legado']);
                if($reivindicar&&(!$podeLegado||!senhaLegadaValida($config,campoPost('senha_antiga')))) throw new RuntimeException('Não foi possível validar a senha antiga do depósito.');
                $hash=password_hash($senha,PASSWORD_DEFAULT);
                $db->beginTransaction();
                $db->prepare('INSERT INTO usuarios(nome,email,senha_hash,legado) VALUES(?,?,?,?)')->execute([$nome,$email,$hash,$reivindicar?1:null]);
                $id=(int)$db->lastInsertId();
                if($reivindicar) {
                    foreach(['categorias','fornecedores','locais','produtos','historico'] as $tabela) {
                        $db->prepare("UPDATE $tabela SET usuario_id=? WHERE usuario_id IS NULL")->execute([$id]);
                    }
                }
                $db->commit();
            } else {
                $registro=one($db,'SELECT id,senha_hash FROM usuarios WHERE email=?',[$email]);
                $hash=$registro['senha_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
                if(strlen($senha)>72||!password_verify($senha,$hash)||!$registro) throw new RuntimeException('E-mail ou senha inválidos.');
                $id=(int)$registro['id'];
            }
            session_regenerate_id(true);
            $_SESSION=['usuario_id'=>$id,'csrf'=>bin2hex(random_bytes(32))];
            redirect();
        } catch(Throwable $e) {
            if($db->inTransaction()) $db->rollBack();
            $erro=$e instanceof PDOException?($cadastro?'Não foi possível cadastrar. O e-mail pode já estar em uso ou os dados antigos já foram transferidos.':'Não foi possível entrar. Tente novamente.'):($e instanceof RuntimeException?$e->getMessage():'Não foi possível concluir a operação.');
        }
    }
    exibirLogin($cadastro,$erro,$nome,$email,$podeLegado);
    exit;
}
function exibirLogin(bool $cadastro,string $erro,string $nome,string $email,bool $podeLegado): void {
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
<main class="login">
<section>
<h1>Estoque Fácil</h1>
<p>Gerencie o estoque do seu depósito.</p>
<div class="auth-tabs">
<a class="<?=!$cadastro?'ativo':''?>" href="?p=login">Entrar com e-mail</a>
<a class="<?=$cadastro?'ativo':''?>" href="?p=cadastro">Cadastrar e-mail</a>
</div>
<h2><?=$cadastro?'Criar conta':'Entrar'?></h2>
<?php if($erro!==''): ?><p class="msg"><?=h($erro)?></p><?php endif; ?>
<?php if(isset($_SESSION['msg'])): ?><p class="msg"><?=h($_SESSION['msg'])?></p><?php unset($_SESSION['msg']); endif; ?>
<form method="post" class="auth-form">
<?=token()?>
<?php if($cadastro): ?>
<label>Nome do depósito<input name="nome" maxlength="150" required value="<?=h($nome)?>"></label>
<?php endif; ?>
<label>E-mail<input type="email" name="email" maxlength="190" autocomplete="email" required value="<?=h($email)?>"></label>
<label>Senha<input type="password" name="senha" autocomplete="<?=$cadastro?'new-password':'current-password'?>" <?=$cadastro?'minlength="8"':''?> required></label>
<?php if($cadastro): ?>
<label>Confirmar senha<input type="password" name="confirmar" autocomplete="new-password" minlength="8" required></label>
<?php if($podeLegado): ?>
<details class="legado">
<summary>Já usava o Estoque Fácil antes?</summary>
<label class="check"><input type="checkbox" name="importar_legado" value="1"> Transferir o estoque antigo para esta conta</label>
<label>Senha administrativa antiga<input type="password" name="senha_antiga" autocomplete="off"></label>
</details>
<?php endif; ?>
<?php endif; ?>
<button name="<?=$cadastro?'cadastrar':'login'?>"><?=$cadastro?'Cadastrar':'Entrar'?></button>
</form>
</section>
</main>
</body>
</html>
<?php
}
