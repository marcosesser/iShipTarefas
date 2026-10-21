<?php
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../config/conexao.php';

exigirLogin();
$pdo = conectarBanco();
$metodo = $_SERVER['REQUEST_METHOD'];
$usuarioId = idUsuarioLogado();

$pastaUploadEventos = __DIR__ . '/../uploads/' . subpastaEmpresa() . '/eventos/';
const EXTENSOES_PERMITIDAS_EVT = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];
const TAMANHO_MAXIMO_EVT = 10 * 1024 * 1024; // 10 MB

switch ($metodo) {

    case 'POST':
        $idEvento  = (int)($_POST['id_evento'] ?? 0);
        $descricao = limparTexto($_POST['descricao'] ?? '');

        if ($idEvento <= 0) {
            respostaJson(['erro' => 'Informe o evento ao qual o anexo pertence'], 400);
        }
        exigirAcessoAoEvento($pdo, $idEvento, $usuarioId);
        exigirAutorDoEvento($pdo, $idEvento, $usuarioId);

        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            respostaJson(['erro' => 'Envie um arquivo válido'], 400);
        }

        $arquivo = $_FILES['arquivo'];

        if ($arquivo['size'] > TAMANHO_MAXIMO_EVT) {
            respostaJson(['erro' => 'O arquivo excede o tamanho máximo de 10 MB'], 400);
        }

        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        if (!in_array($extensao, EXTENSOES_PERMITIDAS_EVT, true)) {
            respostaJson(['erro' => 'Tipo de arquivo não permitido: .' . $extensao], 400);
        }

        if (!is_dir($pastaUploadEventos)) {
            mkdir($pastaUploadEventos, 0775, true);
        }

        $nomeArmazenado = uniqid('evt_anexo_', true) . '.' . $extensao;
        $caminhoDestino = $pastaUploadEventos . $nomeArmazenado;

        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoDestino)) {
            respostaJson(['erro' => 'Falha ao salvar o arquivo no servidor'], 500);
        }

        $caminhoRelativo = 'uploads/' . subpastaEmpresa() . '/eventos/' . $nomeArmazenado;
        $descricaoFinal = $descricao !== '' ? $descricao : $arquivo['name'];

        $stmt = $pdo->prepare('
            INSERT INTO TAREFA_EVENTOS_ANEXO (ID_TAREFA_EVENTOS, DESCRICAO, ANEXO)
            VALUES (?, ?, ?)
            RETURNING ID_TAREFA_EVENTOS_ANEXO
        ');
        $stmt->execute([$idEvento, $descricaoFinal, $caminhoRelativo]);
        $novoId = $stmt->fetchColumn();

        respostaJson(['sucesso' => true, 'id' => (int)$novoId, 'caminho' => $caminhoRelativo], 201);
        break;

    case 'GET':
        $idEvento = (int)($_GET['id_evento'] ?? 0);
        if ($idEvento <= 0) {
            respostaJson(['erro' => 'Informe o evento'], 400);
        }
        exigirAcessoAoEvento($pdo, $idEvento, $usuarioId);

        $stmt = $pdo->prepare('
            SELECT ID_TAREFA_EVENTOS_ANEXO, DESCRICAO, ANEXO, DATA_CADASTRO
            FROM TAREFA_EVENTOS_ANEXO
            WHERE ID_TAREFA_EVENTOS = ?
            ORDER BY DATA_CADASTRO DESC
        ');
        $stmt->execute([$idEvento]);
        respostaJson($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    default:
        respostaJson(['erro' => 'Método não permitido'], 405);
}
