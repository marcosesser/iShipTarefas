<?php
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../config/conexao.php';

exigirLogin();
$pdo = conectarBanco();
$usuarioId = idUsuarioLogado();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respostaJson(['erro' => 'Método não permitido'], 405);
}

$idTag      = (int)($_GET['id_tag'] ?? 0);
$idUsuario  = (int)($_GET['id_usuario'] ?? 0);
$dataInicio = $_GET['data_inicio'] ?? '';
$dataFim    = $_GET['data_fim'] ?? '';
$status     = $_GET['status'] ?? 'todas'; // 'ativas' | 'arquivadas' | 'todas'
$busca      = trim($_GET['busca'] ?? '');

// Regra de visibilidade OBRIGATÓRIA - a mesma do board: tarefa sem
// nenhum usuário vinculado é pública, com vínculo só quem está
// vinculado vê. Sem isso o relatório mostrava tudo pra todo mundo,
// vazando tarefas restritas de outros usuários.
$condicoes = [
    't.ATIVO = 1',
    '(
        NOT EXISTS (SELECT 1 FROM TAREFA_USUARIOS tu WHERE tu.ID_TAREFA = t.ID_TAREFA)
        OR EXISTS (SELECT 1 FROM TAREFA_USUARIOS tu WHERE tu.ID_TAREFA = t.ID_TAREFA AND tu.ID_USUARIOS = ?)
    )',
];
$parametros = [$usuarioId];

if ($status === 'ativas') {
    $condicoes[] = 't.ARQUIVADO = 0';
} elseif ($status === 'arquivadas') {
    $condicoes[] = 't.ARQUIVADO = 1';
}
// 'todas' não filtra por ARQUIVADO - mostra as duas situações juntas.

if ($idTag > 0) {
    $condicoes[] = 't.ID_TAG = ?';
    $parametros[] = $idTag;
}

if ($dataInicio !== '') {
    $condicoes[] = 't.DATA_CADASTRO >= ?';
    $parametros[] = $dataInicio . ' 00:00:00';
}

if ($dataFim !== '') {
    $condicoes[] = 't.DATA_CADASTRO <= ?';
    $parametros[] = $dataFim . ' 23:59:59';
}

if ($idUsuario > 0) {
    $condicoes[] = 'EXISTS (SELECT 1 FROM TAREFA_USUARIOS tu WHERE tu.ID_TAREFA = t.ID_TAREFA AND tu.ID_USUARIOS = ?)';
    $parametros[] = $idUsuario;
}

// Busca por texto: olha na descrição/observação da própria tarefa E na
// descrição/observação de qualquer evento dela - CONTAINING no Firebird
// já é case-insensitive, então não precisa de UPPER/LOWER manual.
if ($busca !== '') {
    $condicoes[] = '(
        t.DESCRICAO CONTAINING ?
        OR t.OBSERVACAO CONTAINING ?
        OR EXISTS (
            SELECT 1 FROM TAREFA_EVENTOS te2
            WHERE te2.ID_TAREFA = t.ID_TAREFA
            AND (te2.DESCRICAO CONTAINING ? OR te2.OBSERVACAO CONTAINING ?)
        )
    )';
    $parametros[] = $busca;
    $parametros[] = $busca;
    $parametros[] = $busca;
    $parametros[] = $busca;
}

// Contagem total (mesmos filtros, sem paginação) - usada pro frontend
// saber quantas páginas existem.
$sqlContagem = 'SELECT COUNT(*) FROM TAREFA t WHERE ' . implode(' AND ', $condicoes);
$stmt = $pdo->prepare($sqlContagem);
$stmt->execute($parametros);
$total = (int)$stmt->fetchColumn();

// Paginação: por_pagina só aceita 10/20/50 (whitelist), então é seguro
// interpolar direto no FIRST/SKIP sem bind - já são inteiros validados.
$porPagina = (int)($_GET['por_pagina'] ?? 20);
if (!in_array($porPagina, [10, 20, 50], true)) {
    $porPagina = 20;
}
$totalPaginas = $porPagina > 0 ? max(1, (int)ceil($total / $porPagina)) : 1;
$pagina = max(1, min((int)($_GET['pagina'] ?? 1), $totalPaginas));
$offset = ($pagina - 1) * $porPagina;

$sql = "
    SELECT FIRST $porPagina SKIP $offset
        t.ID_TAREFA,
        t.DESCRICAO,
        t.DATA_CADASTRO,
        t.ARQUIVADO,
        t.DATA_ARQUIVAMENTO,
        tag.DESCRICAO AS NOME_TAG,
        uc.NOME AS NOME_USUARIO_CADASTRO,
        (SELECT COUNT(*) FROM TAREFA_ANEXO ta WHERE ta.ID_TAREFA = t.ID_TAREFA) AS QTD_ANEXOS,
        (SELECT COUNT(*) FROM TAREFA_EVENTOS te WHERE te.ID_TAREFA = t.ID_TAREFA) AS QTD_EVENTOS
    FROM TAREFA t
    JOIN TAG tag ON tag.ID_TAG = t.ID_TAG
    LEFT JOIN USUARIOS uc ON uc.ID_USUARIO = t.ID_USUARIO_CADASTRO
    WHERE " . implode(' AND ', $condicoes) . '
    ORDER BY t.DATA_CADASTRO DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Anexa os usuários vinculados de cada tarefa do relatório.
$ids = array_column($tarefas, 'ID_TAREFA');
if (count($ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT tu.ID_TAREFA, u.NOME
        FROM TAREFA_USUARIOS tu
        JOIN USUARIOS u ON u.ID_USUARIO = tu.ID_USUARIOS
        WHERE tu.ID_TAREFA IN ($placeholders)
        ORDER BY u.NOME
    ");
    $stmt->execute($ids);
    $vinculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $porTarefa = [];
    foreach ($vinculos as $v) {
        $porTarefa[$v['ID_TAREFA']][] = $v['NOME'];
    }
    foreach ($tarefas as &$t) {
        $t['USUARIOS'] = $porTarefa[$t['ID_TAREFA']] ?? [];
    }
    unset($t);
}

respostaJson([
    'tarefas' => $tarefas,
    'total' => $total,
    'pagina' => $pagina,
    'por_pagina' => $porPagina,
    'total_paginas' => $totalPaginas,
]);
