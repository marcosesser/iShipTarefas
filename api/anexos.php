<?php
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../config/conexao.php';

exigirLogin();
$pdo = conectarBanco();
$metodo = $_SERVER['REQUEST_METHOD'];
$usuarioId = idUsuarioLogado();

$pastaUpload = __DIR__ . '/../uploads/' . subpastaEmpresa() . '/tarefas/';
const EXTENSOES_PERMITIDAS = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];
const TAMANHO_MAXIMO = 10 * 1024 * 1024; // 10 MB

switch ($metodo) {

    case 'POST':
        $idTarefa = (int)($_POST['id_tarefa'] ?? 0);
        $descricao = limparTexto($_POST['descricao'] ?? '');

        if ($idTarefa <= 0) {
            respostaJson(['erro' => 'Informe a tarefa a qual o anexo pertence'], 400);
        }

        exigirAcessoTarefa($pdo, $idTarefa, $usuarioId);

        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            respostaJson(['erro' => 'Envie um arquivo válido'], 400);
        }

        $arquivo = $_FILES['arquivo'];

        if ($arquivo['size'] > TAMANHO_MAXIMO) {
            respostaJson(['erro' => 'O arquivo excede o tamanho máximo de 10 MB'], 400);
        }

        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        if (!in_array($extensao, EXTENSOES_PERMITIDAS, true)) {
            respostaJson(['erro' => 'Tipo de arquivo não permitido: .' . $extensao], 400);
        }

        if (!is_dir($pastaUpload)) {
            mkdir($pastaUpload, 0775, true);
        }

        $nomeArmazenado = uniqid('anexo_', true) . '.' . $extensao;
        $caminhoDestino = $pastaUpload . $nomeArmazenado;

        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoDestino)) {
            respostaJson(['erro' => 'Falha ao salvar o arquivo no servidor'], 500);
        }

        $caminhoRelativo = 'uploads/' . subpastaEmpresa() . '/tarefas/' . $nomeArmazenado;
        $descricaoFinal = $descricao !== '' ? $descricao : $arquivo['name'];

        $stmt = $pdo->prepare('
            INSERT INTO TAREFA_ANEXO (ID_TAREFA, DESCRICAO, ANEXO)
            VALUES (?, ?, ?)
            RETURNING ID_TAREFA_ANEXO
        ');
        $stmt->execute([$idTarefa, $descricaoFinal, $caminhoRelativo]);
        $novoId = $stmt->fetchColumn();

        respostaJson(['sucesso' => true, 'id' => (int)$novoId, 'caminho' => $caminhoRelativo], 201);
        break;

    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            respostaJson(['erro' => 'Informe o ID do anexo'], 400);
        }

        // Busca também o ID_TAREFA do anexo para validar que o usuário
        // logado tem acesso a essa tarefa antes de deixar excluir (IDOR).
        $stmt = $pdo->prepare('SELECT ID_TAREFA, ANEXO FROM TAREFA_ANEXO WHERE ID_TAREFA_ANEXO = ?');
        $stmt->execute([$id]);
        $anexo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($anexo) {
            exigirAcessoTarefa($pdo, (int)$anexo['ID_TAREFA'], $usuarioId);
            exigirCriadorDaTarefa($pdo, (int)$anexo['ID_TAREFA'], $usuarioId);

            $caminhoFisico = __DIR__ . '/../' . $anexo['ANEXO'];
            if (is_file($caminhoFisico)) {
                unlink($caminhoFisico);
            }
            $stmt = $pdo->prepare('DELETE FROM TAREFA_ANEXO WHERE ID_TAREFA_ANEXO = ?');
            $stmt->execute([$id]);
        }

        respostaJson(['sucesso' => true]);
        break;

    default:
        respostaJson(['erro' => 'Método não permitido'], 405);
}
