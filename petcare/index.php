<?php
require __DIR__.'/funcoes.php';
$metodo=$_SERVER['REQUEST_METHOD'];
$base='/petcare';
$caminho=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($caminho===$base || strpos($caminho,$base.'/')===0) $caminho=substr($caminho,strlen($base));
$caminho=rtrim($caminho,'/') ?: '/';
try {
    // Identifica somente as rotas previstas antes de abrir a conexão.
    $rota = null; $id = null;
    if (in_array($caminho,['/login','/logout'],true) && $metodo==='POST') $rota=substr($caminho,1);
    elseif ($caminho==='/tutores' && in_array($metodo,['GET','POST'],true)) $rota='tutores';
    elseif ($caminho==='/pets' && in_array($metodo,['GET','POST'],true)) $rota='pets';
    elseif (preg_match('#^/tutores/(\d+)/pets$#',$caminho,$m) && $metodo==='GET') { $rota='pets_tutor'; $id=$m[1]; }
    elseif (preg_match('#^/(tutores|pets)/(\d+)$#',$caminho,$m) && in_array($metodo,['GET','PUT','DELETE'],true)) { $rota=$m[1].'_id'; $id=$m[2]; }
    if (!$rota) erro(404,'Rota não encontrada');
    $pdo=conectar();
    if ($metodo!=='GET' && $rota!=='login') $token=exigirToken($pdo);
    if ($rota==='login') {
        $d=corpo(); $email=texto($d,'email'); $senha=$d['senha']??null;
        $erros=[];
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $erros[]='E-mail inválido';
        if (!is_string($senha) || $senha==='') $erros[]='Senha obrigatória';
        if ($erros) erro(422,$erros);
        $u=executar($pdo,'SELECT id, senha_hash FROM usuarios WHERE email = ?',[$email])->fetch();
        if (!$u || !password_verify($senha,$u['senha_hash'])) erro(401,'E-mail ou senha inválidos');
        $token=bin2hex(random_bytes(32));
        executar($pdo,'INSERT INTO tokens (usuario_id,token,expira_em) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 2 HOUR))',[$u['id'],$token]);
        $expira=executar($pdo,'SELECT expira_em FROM tokens WHERE token = ?',[$token])->fetchColumn();
        responder(200,['token'=>$token,'tipo'=>'Bearer','expira_em'=>$expira]);
    }
    if ($rota==='logout') { executar($pdo,'DELETE FROM tokens WHERE token = ?',[$token]); responder(204); }
    if ($rota==='tutores') {
        if ($metodo==='GET') { $lista=executar($pdo,'SELECT * FROM tutores ORDER BY id')->fetchAll(); responder(200,['total'=>count($lista),'tutores'=>$lista]); }
        executar($pdo,'INSERT INTO tutores (nome,telefone,email) VALUES (?,?,?)',validarTutor(corpo()));
        responder(201,['id'=>(int)$pdo->lastInsertId()]);
    }
    if ($rota==='tutores_id' || $rota==='pets_tutor') {
        $tutor=buscar($pdo,'SELECT * FROM tutores WHERE id = ?',$id,'Tutor não encontrado');
        if ($rota==='pets_tutor') { $lista=executar($pdo,'SELECT * FROM pets WHERE tutor_id = ? ORDER BY id',[$id])->fetchAll(); responder(200,['total'=>count($lista),'pets'=>$lista]); }
        if ($metodo==='GET') responder(200,$tutor);
        if ($metodo==='DELETE') { executar($pdo,'DELETE FROM tutores WHERE id = ?',[$id]); responder(204); }
        $dados=validarTutor(corpo()); $dados[]=$id;
        executar($pdo,'UPDATE tutores SET nome=?, telefone=?, email=? WHERE id=?',$dados);
        responder(200,buscar($pdo,'SELECT * FROM tutores WHERE id = ?',$id,'Tutor não encontrado'));
    }
    if ($rota==='pets') {
        if ($metodo==='GET') {
            $lista=isset($_GET['especie']) ? executar($pdo,'SELECT * FROM pets WHERE especie = ? ORDER BY id',[is_string($_GET['especie'])?$_GET['especie']:''])->fetchAll() : executar($pdo,'SELECT * FROM pets ORDER BY id')->fetchAll();
            responder(200,['total'=>count($lista),'pets'=>$lista]);
        }
        executar($pdo,'INSERT INTO pets (nome,especie,raca,data_nascimento,peso_kg,tutor_id) VALUES (?,?,?,?,?,?)',validarPet($pdo,corpo()));
        responder(201,['id'=>(int)$pdo->lastInsertId()]);
    }
    if ($rota==='pets_id') {
        $sql='SELECT p.*, t.nome AS tutor_nome FROM pets p JOIN tutores t ON t.id=p.tutor_id WHERE p.id=?';
        $pet=buscar($pdo,$sql,$id,'Pet não encontrado');
        if ($metodo==='GET') responder(200,$pet);
        if ($metodo==='DELETE') { executar($pdo,'DELETE FROM pets WHERE id = ?',[$id]); responder(204); }
        $dados=validarPet($pdo,corpo()); $dados[]=$id;
        executar($pdo,'UPDATE pets SET nome=?, especie=?, raca=?, data_nascimento=?, peso_kg=?, tutor_id=? WHERE id=?',$dados);
        responder(200,buscar($pdo,$sql,$id,'Pet não encontrado'));
    }
} catch (PDOException $e) {
    $codigo=$e->errorInfo[1]??0;
    if ($codigo==1062) erro(409,'E-mail já cadastrado');
    if ($codigo==1451) erro(409,'Tutor possui pets cadastrados');
    if ($codigo==1452) erro(422,['Tutor inexistente']);
    error_log($e->getMessage()); erro(500,'Erro interno do banco de dados');
} catch (Throwable $e) { error_log($e->getMessage()); erro(500,'Erro interno'); }
