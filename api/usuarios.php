<?php
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../config/conexao.php';

exigirLogin();
$pdo = conectarBanco();
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {

    case 'GET':
        // Lista todos os usuários ativos (usada para preencher o
        // multi-select de "vincular usuários" na tela de tarefa).
        $stmt = $pdo->query('
            SELECT ID_USUARIO, NOME, LOGIN, DATA_CADASTRO
            FROM USUARIOS
            WHERE ATIVO = 1
            ORDER BY NOME
        ');
        respostaJson($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'POST':
        $dados = corpoRequisicao();
        $nome  = limparTexto($dados['nome'] ?? '');
        $login = limparTexto($dados['login'] ?? '');
        $senha = (string)($dados['senha'] ?? '');

        if ($nome === '' || $login === '' || $senha === '') {
            respostaJson(['erro' => 'Preencha nome, e-mail e senha'], 400);
        }

        if (!filter_var($login, FILTER_VALIDATE_EMAIL)) {
            respostaJson(['erro' => 'Informe um e-mail válido'], 400);
        }

        if (strlen($senha) < 6) {
            respostaJson(['erro' => 'A senha deve ter ao menos 6 caracteres'], 400);
        }

        $stmt = $pdo->prepare('SELECT ID_USUARIO FROM USUARIOS WHERE LOGIN = ?');
        $stmt->execute([$login]);
        if ($stmt->fetch()) {
            respostaJson(['erro' => 'Já existe um usuário cadastrado com esse e-mail'], 409);
        }

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO USUARIOS (NOME, LOGIN, SENHA) VALUES (?, ?, ?) RETURNING ID_USUARIO');
        $stmt->execute([$nome, $login, $senhaHash]);
        $novoId = $stmt->fetchColumn();

        respostaJson(['sucesso' => true, 'id' => (int)$novoId], 201);
        break;

    case 'PUT':
        $dados = corpoRequisicao();
        $id    = (int)($dados['id'] ?? 0);
        $nome  = limparTexto($dados['nome'] ?? '');
        $login = limparTexto($dados['login'] ?? '');
        $senha = (string)($dados['senha'] ?? ''); // opcional na edição

        if ($id <= 0 || $nome === '' || $login === '') {
            respostaJson(['erro' => 'Dados incompletos para atualização'], 400);
        }

        if (!filter_var($login, FILTER_VALIDATE_EMAIL)) {
            respostaJson(['erro' => 'Informe um e-mail válido'], 400);
        }

        $stmt = $pdo->prepare('SELECT ID_USUARIO FROM USUARIOS WHERE LOGIN = ? AND ID_USUARIO <> ?');
        $stmt->execute([$login, $id]);
        if ($stmt->fetch()) {
            respostaJson(['erro' => 'Já existe outro usuário com esse e-mail'], 409);
        }

        if ($senha !== '') {
            if (strlen($senha) < 6) {
                respostaJson(['erro' => 'A senha deve ter ao menos 6 caracteres'], 400);
            }
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE USUARIOS SET NOME = ?, LOGIN = ?, SENHA = ? WHERE ID_USUARIO = ?');
            $stmt->execute([$nome, $login, $senhaHash, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE USUARIOS SET NOME = ?, LOGIN = ? WHERE ID_USUARIO = ?');
            $stmt->execute([$nome, $login, $id]);
        }

        respostaJson(['sucesso' => true]);
        break;

    case 'DELETE':
        // Soft delete: mantém o histórico de tarefas/eventos do usuário.
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            respostaJson(['erro' => 'Informe o ID do usuário'], 400);
        }

        $stmt = $pdo->prepare('UPDATE USUARIOS SET ATIVO = 0 WHERE ID_USUARIO = ?');
        $stmt->execute([$id]);

        respostaJson(['sucesso' => true]);
        break;

    default:
        respostaJson(['erro' => 'Método não permitido'], 405);
}
