<?php
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respostaJson(['erro' => 'Método não permitido'], 405);
}

$dados = corpoRequisicao();
$login = limparTexto($dados['login'] ?? '');
$senha = (string)($dados['senha'] ?? '');

if ($login === '' || $senha === '') {
    respostaJson(['erro' => 'Informe e-mail e senha'], 400);
}

$pdo = conectarBancoMestre();
$stmt = $pdo->prepare('SELECT ID_SUPORTE_USUARIO, NOME, SENHA, ATIVO FROM SUPORTE_USUARIOS WHERE LOGIN = ?');
$stmt->execute([$login]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario || (int)$usuario['ATIVO'] !== 1 || !password_verify($senha, $usuario['SENHA'])) {
    sleep(1); // mesmo atraso contra força bruta usado no projeto principal
    respostaJson(['erro' => 'E-mail ou senha inválidos'], 401);
}

session_regenerate_id(true);
$_SESSION['suporte_id'] = (int)$usuario['ID_SUPORTE_USUARIO'];
$_SESSION['suporte_nome'] = $usuario['NOME'];

respostaJson([
    'sucesso' => true,
    'usuario' => [
        'id' => (int)$usuario['ID_SUPORTE_USUARIO'],
        'nome' => $usuario['NOME'],
    ],
]);
