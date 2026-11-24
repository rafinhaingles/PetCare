<?php
function conectar() {
    return new PDO('mysql:host=localhost;port=3306;dbname=petcare;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
}
function responder($status, $dados = null) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    if ($status !== 204) echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function erro($status, $mensagem) { responder($status, ['detail' => $mensagem]); }
function executar($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
function corpo() {
    $dados = json_decode(file_get_contents('php://input'));
    if (json_last_error() !== JSON_ERROR_NONE) erro(400, 'Corpo não é JSON válido');
    if (!is_object($dados)) erro(422, ['O corpo deve ser um objeto JSON']);
    return (array) $dados;
}
function exigirToken($pdo) {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$header && function_exists('getallheaders')) {
        foreach (getallheaders() as $nome => $valor) if (strcasecmp($nome, 'Authorization') === 0) $header = $valor;
    }
    if (!$header) erro(401, 'Token não informado');
    if (!preg_match('/^Bearer ([a-f0-9]{64})$/i', $header, $m)) erro(401, 'Token inválido ou expirado');
    if (!executar($pdo, 'SELECT id FROM tokens WHERE token = ? AND expira_em > NOW()', [$m[1]])->fetch()) erro(401, 'Token inválido ou expirado');
    return $m[1];
}
function texto($d, $k) { return isset($d[$k]) && is_string($d[$k]) ? trim($d[$k]) : ''; }
function tamanho($s) { return function_exists('mb_strlen') ? mb_strlen($s) : preg_match_all('/./us', $s); }
function validarTutor($d) {
    $nome = texto($d,'nome'); $telefone = texto($d,'telefone'); $email = texto($d,'email'); $erros = [];
    if (tamanho($nome)<2 || tamanho($nome)>100) $erros[] = 'Nome deve ter entre 2 e 100 caracteres';
    if ($telefone === '' || tamanho($telefone)>30) $erros[] = 'Telefone obrigatório, até 30 caracteres';
    if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254) $erros[] = 'E-mail inválido';
    if ($erros) erro(422,$erros);
    return [$nome,$telefone,$email];
}
function validarPet($pdo,$d) {
    $nome=texto($d,'nome'); $especie=texto($d,'especie'); $raca=$d['raca']??null; $data=texto($d,'data_nascimento'); $peso=$d['peso_kg']??null; $tutor=$d['tutor_id']??null; $erros=[];
    if (tamanho($nome)<2 || tamanho($nome)>60) $erros[]='Nome deve ter entre 2 e 60 caracteres';
    if (!in_array($especie,['cachorro','gato','ave','roedor','outro'],true)) $erros[]='Espécie inválida';
    if ($raca!==null && (!is_string($raca) || tamanho($raca)>100)) $erros[]='Raça deve ser texto de até 100 caracteres ou nula';
    if (!is_numeric($peso) || $peso<=0 || $peso>200 || round((float)$peso,3)<=0) $erros[]='Peso deve ser maior que 0 e até 200 kg';
    $dt=DateTime::createFromFormat('!Y-m-d',$data);
    if (!$dt || $dt->format('Y-m-d')!==$data || $data>date('Y-m-d') || $data<'1000-01-01') $erros[]='Data de nascimento inválida ou futura (AAAA-MM-DD)';
    if ((!is_int($tutor) && !is_string($tutor)) || !filter_var($tutor,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) || !executar($pdo,'SELECT id FROM tutores WHERE id = ?',[$tutor])->fetch()) $erros[]='Tutor inexistente ou inválido';
    if ($erros) erro(422,$erros);
    return [$nome,$especie,$raca,$data,$peso,$tutor];
}
function buscar($pdo,$sql,$id,$mensagem) {
    $item=executar($pdo,$sql,[$id])->fetch();
    if (!$item) erro(404,$mensagem);
    return $item;
}
