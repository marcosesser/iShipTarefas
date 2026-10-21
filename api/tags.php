<?php
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../config/conexao.php';

exigirLogin();
$pdo = conectarBanco();
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {

    case 'GET':
        // Lista as tags ativas, ordenadas pela ORDEM configurada -
        // é essa ordem que define a sequência das colunas na tela inicial.
        $stmt = $pdo->query('
            SELECT ID_TAG, DESCRICAO, ORDEM
            FROM TAG
            WHERE ATIVO = 1
            ORDER BY ORDEM
        ');
        respostaJson($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'POST':
        $dados     = corpoRequisicao();
        $descricao = limparTexto($dados['descricao'] ?? '');
        $ordem     = (int)($dados['ordem'] ?? 0);

        if ($descricao === '' || $ordem <= 0) {
            respostaJson(['erro' => 'Informe a descrição e uma ordem válida (maior que zero)'], 400);
        }

        $stmt = $pdo->prepare('SELECT ID_TAG FROM TAG WHERE ORDEM = ? AND ATIVO = 1');
        $stmt->execute([$ordem]);
        if ($stmt->fetch()) {
            respostaJson(['erro' => 'Já existe uma tag cadastrada com essa ordem'], 409);
        }

        $stmt = $pdo->prepare('INSERT INTO TAG (DESCRICAO, ORDEM) VALUES (?, ?) RETURNING ID_TAG');
        $stmt->execute([$descricao, $ordem]);
        $novoId = $stmt->fetchColumn();

        respostaJson(['sucesso' => true, 'id' => (int)$novoId], 201);
        break;

    case 'PUT':
        $dados     = corpoRequisicao();
        $id        = (int)($dados['id'] ?? 0);
        $descricao = limparTexto($dados['descricao'] ?? '');
        $ordem     = (int)($dados['ordem'] ?? 0);

        if ($id <= 0 || $descricao === '' || $ordem <= 0) {
            respostaJson(['erro' => 'Dados incompletos para atualização'], 400);
        }

        $stmt = $pdo->prepare('SELECT ID_TAG FROM TAG WHERE ORDEM = ? AND ID_TAG <> ? AND ATIVO = 1');
        $stmt->execute([$ordem, $id]);
        if ($stmt->fetch()) {
            respostaJson(['erro' => 'Já existe outra tag cadastrada com essa ordem'], 409);
        }

        $stmt = $pdo->prepare('UPDATE TAG SET DESCRICAO = ?, ORDEM = ? WHERE ID_TAG = ?');
        $stmt->execute([$descricao, $ordem, $id]);

        respostaJson(['sucesso' => true]);
        break;

    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            respostaJson(['erro' => 'Informe o ID da tag'], 400);
        }

        $stmt = $pdo->prepare('UPDATE TAG SET ATIVO = 0 WHERE ID_TAG = ?');
        $stmt->execute([$id]);

        respostaJson(['sucesso' => true]);
        break;

    default:
        respostaJson(['erro' => 'Método não permitido'], 405);
}
