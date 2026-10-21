<?php
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respostaJson(['erro' => 'Método não permitido'], 405);
}

$dados = corpoRequisicao();
$codigoEmpresa = strtoupper(trim($dados['codigo_empresa'] ?? ''));
$login = limparTexto($dados['login'] ?? '');
$senha = (string)($dados['senha'] ?? '');

if ($codigoEmpresa === '' || $login === '' || $senha === '') {
    respostaJson(['erro' => 'Informe o código da empresa, e-mail e senha'], 400);
}

// Mensagem de erro SEMPRE igual, não importa qual das três coisas
// falhou (código não existe, e-mail não existe, senha errada) - assim
// ninguém descobre por tentativa e erro quais códigos de empresa são
// válidos.
$mensagemErroGenerica = 'Código da empresa, e-mail ou senha inválidos';

// 1) Resolve o código pro banco da empresa, consultando o banco MESTRE.
$pdoMestre = conectarBancoMestre();
$stmt = $pdoMestre->prepare('SELECT ID_EMPRESA, NOME, CAMINHO_BANCO FROM EMPRESAS WHERE CODIGO = ? AND ATIVA = 1');
$stmt->execute([$codigoEmpresa]);
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empresa) {
    sleep(1);
    respostaJson(['erro' => $mensagemErroGenerica], 401);
}

// 2) Com o banco da empresa em mãos, valida o usuário nela.
$pdoEmpresa = conectarBancoPorCaminho($empresa['CAMINHO_BANCO']);
$stmt = $pdoEmpresa->prepare('SELECT ID_USUARIO, NOME, SENHA, ATIVO FROM USUARIOS WHERE LOGIN = ?');
$stmt->execute([$login]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario || (int)$usuario['ATIVO'] !== 1 || !password_verify($senha, $usuario['SENHA'])) {
    // Atraso proposital: dificulta ataques de força bruta automatizados
    // (tentar milhares de senhas por segundo). Não substitui um bloqueio
    // por tentativas (rate limiting de verdade), mas já eleva bastante
    // o custo de um ataque só com essa linha.
    sleep(1);
    respostaJson(['erro' => $mensagemErroGenerica], 401);
}

// Regenera o ID de sessão no login para evitar fixação de sessão.
session_regenerate_id(true);
$_SESSION['usuario_id'] = (int)$usuario['ID_USUARIO'];
$_SESSION['usuario_nome'] = $usuario['NOME'];
$_SESSION['empresa_id'] = (int)$empresa['ID_EMPRESA'];
$_SESSION['empresa_nome'] = $empresa['NOME'];
$_SESSION['empresa_caminho_banco'] = $empresa['CAMINHO_BANCO'];

respostaJson([
    'sucesso' => true,
    'usuario' => [
        'id' => (int)$usuario['ID_USUARIO'],
        'nome' => $usuario['NOME'],
    ],
]);
