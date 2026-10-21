<?php
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../config/conexao.php';

exigirLogin();
$pdo = conectarBanco();
$metodo = $_SERVER['REQUEST_METHOD'];
$usuarioId = idUsuarioLogado();

switch ($metodo) {

    case 'GET':
        $idTarefa = (int)($_GET['id_tarefa'] ?? 0);
        if ($idTarefa <= 0) {
            respostaJson(['erro' => 'Informe a tarefa'], 400);
        }
        exigirAcessoTarefa($pdo, $idTarefa, $usuarioId);

        $stmt = $pdo->prepare('
            SELECT te.ID_TAREFA_EVENTOS, te.DESCRICAO, te.OBSERVACAO, te.DATA_CADASTRO,
                   te.ID_USUARIO, u.NOME AS NOME_USUARIO
            FROM TAREFA_EVENTOS te
            LEFT JOIN USUARIOS u ON u.ID_USUARIO = te.ID_USUARIO
            WHERE te.ID_TAREFA = ?
            ORDER BY te.DATA_CADASTRO DESC
        ');
        $stmt->execute([$idTarefa]);
        respostaJson($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'POST':
        $dados      = corpoRequisicao();
        $idTarefa   = (int)($dados['id_tarefa'] ?? 0);
        $descricao  = limparTexto($dados['descricao'] ?? '');
        $observacao = (string)($dados['observacao'] ?? '');

        if ($idTarefa <= 0 || $descricao === '') {
            respostaJson(['erro' => 'Informe a tarefa e a descrição do evento'], 400);
        }
        exigirAcessoTarefa($pdo, $idTarefa, $usuarioId);

        $stmt = $pdo->prepare('
            INSERT INTO TAREFA_EVENTOS (ID_TAREFA, DESCRICAO, OBSERVACAO, ID_USUARIO)
            VALUES (?, ?, ?, ?)
            RETURNING ID_TAREFA_EVENTOS
        ');
        $stmt->execute([$idTarefa, $descricao, $observacao, $usuarioId]);
        $novoId = $stmt->fetchColumn();

        respostaJson(['sucesso' => true, 'id' => (int)$novoId], 201);
        break;

    case 'PUT':
        $dados      = corpoRequisicao();
        $id         = (int)($dados['id'] ?? 0);
        $descricao  = limparTexto($dados['descricao'] ?? '');
        $observacao = (string)($dados['observacao'] ?? '');

        if ($id <= 0 || $descricao === '') {
            respostaJson(['erro' => 'Informe a descrição do evento'], 400);
        }

        exigirAcessoAoEvento($pdo, $id, $usuarioId);
        exigirAutorDoEvento($pdo, $id, $usuarioId);

        $stmt = $pdo->prepare('UPDATE TAREFA_EVENTOS SET DESCRICAO = ?, OBSERVACAO = ? WHERE ID_TAREFA_EVENTOS = ?');
        $stmt->execute([$descricao, $observacao, $id]);

        respostaJson(['sucesso' => true]);
        break;

    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            respostaJson(['erro' => 'Informe o ID do evento'], 400);
        }

        exigirAcessoAoEvento($pdo, $id, $usuarioId);
        exigirAutorDoEvento($pdo, $id, $usuarioId);

        $stmt = $pdo->prepare('DELETE FROM TAREFA_EVENTOS WHERE ID_TAREFA_EVENTOS = ?');
        $stmt->execute([$id]);

        respostaJson(['sucesso' => true]);
        break;

    default:
        respostaJson(['erro' => 'Método não permitido'], 405);
}
