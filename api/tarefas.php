<?php
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../config/conexao.php';

exigirLogin();
$pdo = conectarBanco();
$metodo = $_SERVER['REQUEST_METHOD'];
$usuarioId = idUsuarioLogado();

switch ($metodo) {

    case 'GET':
        if (isset($_GET['id'])) {
            buscarDetalheTarefa($pdo, (int)$_GET['id'], $usuarioId);
        } elseif (isset($_GET['arquivadas'])) {
            listarTarefasArquivadas($pdo, $usuarioId);
        } else {
            listarTarefas($pdo, $usuarioId);
        }
        break;

    case 'POST':
        criarTarefa($pdo, $usuarioId);
        break;

    case 'PUT':
        if (($_GET['acao'] ?? '') === 'arquivar') {
            arquivarTarefa($pdo, $usuarioId);
        } else {
            atualizarTarefa($pdo, $usuarioId);
        }
        break;

    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            respostaJson(['erro' => 'Informe o ID da tarefa'], 400);
        }
        exigirAcessoTarefa($pdo, $id, $usuarioId);
        $stmt = $pdo->prepare('UPDATE TAREFA SET ATIVO = 0 WHERE ID_TAREFA = ?');
        $stmt->execute([$id]);
        respostaJson(['sucesso' => true]);
        break;

    default:
        respostaJson(['erro' => 'Método não permitido'], 405);
}


/**
 * Lista as tarefas visíveis para o usuário logado, já agrupáveis
 * por ID_TAG no frontend para montar as colunas do board.
 *
 * Regra de visibilidade: uma tarefa sem nenhum usuário vinculado é
 * pública (todo mundo vê); com usuários vinculados, só quem está
 * vinculado enxerga o card.
 */
function listarTarefas(PDO $pdo, int $usuarioId): void
{
    $sql = '
        SELECT
            t.ID_TAREFA,
            t.ID_TAG,
            t.DESCRICAO,
            t.ORDEM,
            t.DATA_CADASTRO,
            (SELECT COUNT(*) FROM TAREFA_ANEXO ta WHERE ta.ID_TAREFA = t.ID_TAREFA) AS QTD_ANEXOS,
            (SELECT COUNT(*) FROM TAREFA_EVENTOS te WHERE te.ID_TAREFA = t.ID_TAREFA) AS QTD_EVENTOS
        FROM TAREFA t
        WHERE t.ATIVO = 1
        AND t.ARQUIVADO = 0
        AND (
            NOT EXISTS (SELECT 1 FROM TAREFA_USUARIOS tu WHERE tu.ID_TAREFA = t.ID_TAREFA)
            OR EXISTS (SELECT 1 FROM TAREFA_USUARIOS tu WHERE tu.ID_TAREFA = t.ID_TAREFA AND tu.ID_USUARIOS = ?)
        )
        ORDER BY t.ID_TAG, t.ORDEM
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuarioId]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    anexarUsuariosVinculados($pdo, $tarefas);

    respostaJson($tarefas);
}

/**
 * Lista as tarefas arquivadas visíveis para o usuário logado (mesma
 * regra de visibilidade do board), usada pela tela "Tarefas Arquivadas".
 */
function listarTarefasArquivadas(PDO $pdo, int $usuarioId): void
{
    $sql = '
        SELECT
            t.ID_TAREFA,
            t.ID_TAG,
            t.DESCRICAO,
            t.DATA_CADASTRO,
            t.DATA_ARQUIVAMENTO,
            tag.DESCRICAO AS NOME_TAG
        FROM TAREFA t
        JOIN TAG tag ON tag.ID_TAG = t.ID_TAG
        WHERE t.ATIVO = 1
        AND t.ARQUIVADO = 1
        AND (
            NOT EXISTS (SELECT 1 FROM TAREFA_USUARIOS tu WHERE tu.ID_TAREFA = t.ID_TAREFA)
            OR EXISTS (SELECT 1 FROM TAREFA_USUARIOS tu WHERE tu.ID_TAREFA = t.ID_TAREFA AND tu.ID_USUARIOS = ?)
        )
        ORDER BY t.DATA_ARQUIVAMENTO DESC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuarioId]);
    respostaJson($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Preenche $tarefas (por referência) com a lista de usuários
 * vinculados de cada uma, em uma única query extra (evita N+1).
 */
function anexarUsuariosVinculados(PDO $pdo, array &$tarefas): void
{
    foreach ($tarefas as &$t) {
        $t['USUARIOS'] = [];
    }
    unset($t);

    $ids = array_column($tarefas, 'ID_TAREFA');
    if (count($ids) === 0) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "
        SELECT tu.ID_TAREFA, u.ID_USUARIO, u.NOME
        FROM TAREFA_USUARIOS tu
        JOIN USUARIOS u ON u.ID_USUARIO = tu.ID_USUARIOS
        WHERE tu.ID_TAREFA IN ($placeholders)
        ORDER BY u.NOME
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    $vinculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $porTarefa = [];
    foreach ($vinculos as $v) {
        $porTarefa[$v['ID_TAREFA']][] = ['id' => (int)$v['ID_USUARIO'], 'nome' => $v['NOME']];
    }

    foreach ($tarefas as &$t) {
        $t['USUARIOS'] = $porTarefa[$t['ID_TAREFA']] ?? [];
    }
}

/**
 * Detalhe completo de uma tarefa: dados básicos + usuários
 * vinculados + anexos + eventos. Usado ao abrir o card.
 */
function buscarDetalheTarefa(PDO $pdo, int $id, int $usuarioId): void
{
    exigirAcessoTarefa($pdo, $id, $usuarioId);

    $stmt = $pdo->prepare('
        SELECT t.ID_TAREFA, t.ID_TAG, t.DESCRICAO, t.OBSERVACAO, t.DATA_CADASTRO,
               t.ARQUIVADO, t.DATA_ARQUIVAMENTO,
               t.ID_USUARIO_CADASTRO, u.NOME AS NOME_USUARIO_CADASTRO
        FROM TAREFA t
        LEFT JOIN USUARIOS u ON u.ID_USUARIO = t.ID_USUARIO_CADASTRO
        WHERE t.ID_TAREFA = ? AND t.ATIVO = 1
    ');
    $stmt->execute([$id]);
    $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tarefa) {
        respostaJson(['erro' => 'Tarefa não encontrada'], 404);
    }

    $stmt = $pdo->prepare('
        SELECT u.ID_USUARIO, u.NOME
        FROM TAREFA_USUARIOS tu
        JOIN USUARIOS u ON u.ID_USUARIO = tu.ID_USUARIOS
        WHERE tu.ID_TAREFA = ?
        ORDER BY u.NOME
    ');
    $stmt->execute([$id]);
    // Formato padronizado {id, nome} - o mesmo usado em listarTarefas() -
    // para a API devolver sempre a mesma "forma" de usuário vinculado,
    // não importa por qual endpoint ela veio.
    $tarefa['USUARIOS'] = array_map(
        fn($u) => ['id' => (int)$u['ID_USUARIO'], 'nome' => $u['NOME']],
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

    $stmt = $pdo->prepare('
        SELECT ID_TAREFA_ANEXO, DESCRICAO, ANEXO, DATA_CADASTRO
        FROM TAREFA_ANEXO
        WHERE ID_TAREFA = ?
        ORDER BY DATA_CADASTRO DESC
    ');
    $stmt->execute([$id]);
    $tarefa['ANEXOS'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('
        SELECT te.ID_TAREFA_EVENTOS, te.DESCRICAO, te.OBSERVACAO, te.DATA_CADASTRO,
               te.ID_USUARIO, u.NOME AS NOME_USUARIO
        FROM TAREFA_EVENTOS te
        LEFT JOIN USUARIOS u ON u.ID_USUARIO = te.ID_USUARIO
        WHERE te.ID_TAREFA = ?
        ORDER BY te.DATA_CADASTRO DESC
    ');
    $stmt->execute([$id]);
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    anexarAnexosDosEventos($pdo, $eventos);
    $tarefa['EVENTOS'] = $eventos;

    $stmt = $pdo->prepare('
        SELECT th.ID_TAREFA_HISTORICO, th.ACAO, th.DESCRICAO, th.VALOR_ANTERIOR,
               th.VALOR_NOVO, th.DATA_CADASTRO, u.NOME AS NOME_USUARIO
        FROM TAREFA_HISTORICO th
        LEFT JOIN USUARIOS u ON u.ID_USUARIO = th.ID_USUARIO
        WHERE th.ID_TAREFA = ?
        ORDER BY th.DATA_CADASTRO DESC
    ');
    $stmt->execute([$id]);
    $tarefa['HISTORICO'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respostaJson($tarefa);
}

/**
 * Preenche $eventos (por referência) com os anexos de cada um
 * (tabela TAREFA_EVENTOS_ANEXO), em uma única query extra.
 */
function anexarAnexosDosEventos(PDO $pdo, array &$eventos): void
{
    foreach ($eventos as &$e) {
        $e['ANEXOS'] = [];
    }
    unset($e);

    $ids = array_column($eventos, 'ID_TAREFA_EVENTOS');
    if (count($ids) === 0) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "
        SELECT ID_TAREFA_EVENTOS_ANEXO, ID_TAREFA_EVENTOS, DESCRICAO, ANEXO, DATA_CADASTRO
        FROM TAREFA_EVENTOS_ANEXO
        WHERE ID_TAREFA_EVENTOS IN ($placeholders)
        ORDER BY DATA_CADASTRO DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    $anexos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $porEvento = [];
    foreach ($anexos as $a) {
        $porEvento[$a['ID_TAREFA_EVENTOS']][] = $a;
    }

    foreach ($eventos as &$e) {
        $e['ANEXOS'] = $porEvento[$e['ID_TAREFA_EVENTOS']] ?? [];
    }
}

/**
 * Cria uma nova tarefa e já grava os usuários vinculados a ela,
 * tudo em uma transação (ou grava tudo, ou nada).
 */
function criarTarefa(PDO $pdo, int $usuarioId): void
{
    $dados      = corpoRequisicao();
    $idTag      = (int)($dados['id_tag'] ?? 0);
    $descricao  = limparTexto($dados['descricao'] ?? '');
    $observacao = (string)($dados['observacao'] ?? '');
    $usuarios   = $dados['usuarios'] ?? []; // array de IDs

    if ($idTag <= 0 || $descricao === '') {
        respostaJson(['erro' => 'Informe a tag e a descrição da tarefa'], 400);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('
            SELECT COALESCE(MAX(ORDEM), 0) + 1 FROM TAREFA WHERE ID_TAG = ? AND ATIVO = 1
        ');
        $stmt->execute([$idTag]);
        $proximaOrdem = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare('
            INSERT INTO TAREFA (ID_TAG, DESCRICAO, OBSERVACAO, ORDEM, ID_USUARIO_CADASTRO)
            VALUES (?, ?, ?, ?, ?)
            RETURNING ID_TAREFA
        ');
        $stmt->execute([$idTag, $descricao, $observacao, $proximaOrdem, $usuarioId]);
        $novaTarefaId = (int)$stmt->fetchColumn();

        vincularUsuarios($pdo, $novaTarefaId, $usuarios);
        registrarHistorico($pdo, $novaTarefaId, $usuarioId, 'CRIACAO', 'Tarefa criada');

        $pdo->commit();
        respostaJson(['sucesso' => true, 'id' => $novaTarefaId], 201);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respostaJson(['erro' => 'Não foi possível salvar a tarefa: ' . $e->getMessage()], 500);
    }
}

/**
 * Atualiza uma tarefa existente de forma PARCIAL: qualquer campo que
 * não vier no corpo da requisição mantém o valor atual. Isso é o que
 * permite o drag-and-drop mandar só {id, id_tag} para mover um card
 * de coluna sem apagar descrição/observação já cadastradas.
 */
function atualizarTarefa(PDO $pdo, int $usuarioId): void
{
    $dados = corpoRequisicao();
    $id = (int)($dados['id'] ?? 0);

    if ($id <= 0) {
        respostaJson(['erro' => 'Informe o ID da tarefa'], 400);
    }

    try {
        $pdo->beginTransaction();

        // exigirAcessoTarefa faz um SELECT - por isso só pode rodar
        // DEPOIS do beginTransaction(): o PDO_FIREBIRD abre uma transação
        // implícita no primeiro SELECT da conexão, e chamar
        // beginTransaction() explicitamente depois disso gera o erro
        // "There is already an active transaction".
        exigirAcessoTarefa($pdo, $id, $usuarioId);

        // Esta consulta também precisa estar dentro da transação, pelo
        // mesmo motivo.
        $stmt = $pdo->prepare('
            SELECT ID_TAG, DESCRICAO, OBSERVACAO, ORDEM, ID_USUARIO_CADASTRO
            FROM TAREFA WHERE ID_TAREFA = ? AND ATIVO = 1
        ');
        $stmt->execute([$id]);
        $atual = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$atual) {
            $pdo->rollBack();
            respostaJson(['erro' => 'Tarefa não encontrada'], 404);
        }

        // Só quem criou a tarefa pode alterar descrição/observação/vínculos
        // de usuário. Mover entre TAGs (drag-and-drop, só id_tag no corpo)
        // continua liberado pra qualquer vinculado - não é "editar conteúdo".
        $alterandoConteudo = array_key_exists('descricao', $dados)
            || array_key_exists('observacao', $dados)
            || array_key_exists('usuarios', $dados);

        if ($alterandoConteudo && (int)$atual['ID_USUARIO_CADASTRO'] !== $usuarioId) {
            $pdo->rollBack();
            respostaJson(['erro' => 'Só quem criou a tarefa pode editar estes dados.'], 403);
        }

        $idTagNovo  = array_key_exists('id_tag', $dados) ? (int)$dados['id_tag'] : (int)$atual['ID_TAG'];
        $descricao  = array_key_exists('descricao', $dados) ? limparTexto($dados['descricao']) : $atual['DESCRICAO'];
        $observacao = array_key_exists('observacao', $dados) ? (string)$dados['observacao'] : $atual['OBSERVACAO'];
        $usuarios   = $dados['usuarios'] ?? null; // null = não mexe nos vínculos

        if ($idTagNovo <= 0 || $descricao === '') {
            $pdo->rollBack();
            respostaJson(['erro' => 'A tag e a descrição não podem ficar vazias'], 400);
        }

        if (array_key_exists('ordem', $dados)) {
            $ordem = (int)$dados['ordem'];
        } elseif ($idTagNovo !== (int)$atual['ID_TAG']) {
            // Mudou de coluna sem ordem explícita (drag-and-drop simples): vai para o final da nova coluna.
            $stmt = $pdo->prepare('
                SELECT COALESCE(MAX(ORDEM), 0) + 1 FROM TAREFA WHERE ID_TAG = ? AND ATIVO = 1 AND ID_TAREFA <> ?
            ');
            $stmt->execute([$idTagNovo, $id]);
            $ordem = (int)$stmt->fetchColumn();
        } else {
            $ordem = (int)$atual['ORDEM'];
        }

        $stmt = $pdo->prepare('
            UPDATE TAREFA
            SET ID_TAG = ?, DESCRICAO = ?, OBSERVACAO = ?, ORDEM = ?
            WHERE ID_TAREFA = ?
        ');
        $stmt->execute([$idTagNovo, $descricao, $observacao, $ordem, $id]);

        // Movimentação entre tags: registra de onde veio e pra onde foi.
        if ($idTagNovo !== (int)$atual['ID_TAG']) {
            $stmt = $pdo->prepare('SELECT DESCRICAO FROM TAG WHERE ID_TAG = ?');
            $stmt->execute([(int)$atual['ID_TAG']]);
            $nomeTagAntiga = $stmt->fetchColumn() ?: 'desconhecida';

            $stmt->execute([$idTagNovo]);
            $nomeTagNova = $stmt->fetchColumn() ?: 'desconhecida';

            registrarHistorico(
                $pdo,
                $id,
                $usuarioId,
                'MOVIMENTACAO',
                "Movida de \"{$nomeTagAntiga}\" para \"{$nomeTagNova}\"",
                $nomeTagAntiga,
                $nomeTagNova
            );
        }

        // Edição de conteúdo: só registra se descrição OU observação
        // realmente mudaram (evita poluir o histórico quando o PUT é
        // só o drag-and-drop, que não altera nenhum dos dois).
        if ($descricao !== $atual['DESCRICAO'] || $observacao !== (string)$atual['OBSERVACAO']) {
            registrarHistorico($pdo, $id, $usuarioId, 'EDICAO', 'Descrição/observação da tarefa editada');
        }

        if (is_array($usuarios)) {
            $stmt = $pdo->prepare('DELETE FROM TAREFA_USUARIOS WHERE ID_TAREFA = ?');
            $stmt->execute([$id]);
            vincularUsuarios($pdo, $id, $usuarios);
        }

        $pdo->commit();
        respostaJson(['sucesso' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respostaJson(['erro' => 'Não foi possível atualizar a tarefa: ' . $e->getMessage()], 500);
    }
}

/**
 * Arquiva ou restaura uma tarefa. Diferente do DELETE (soft-delete,
 * ATIVO=0, some de tudo), arquivar só tira a tarefa do board - ela
 * continua existindo e aparecendo nos relatórios, e pode ser
 * restaurada a qualquer momento.
 */
function arquivarTarefa(PDO $pdo, int $usuarioId): void
{
    $dados = corpoRequisicao();
    $id = (int)($dados['id'] ?? 0);
    $arquivar = (bool)($dados['arquivado'] ?? true);

    if ($id <= 0) {
        respostaJson(['erro' => 'Informe o ID da tarefa'], 400);
    }

    try {
        $pdo->beginTransaction();

        // Mesmo motivo do atualizarTarefa: exigirAcessoTarefa faz um
        // SELECT, então só pode rodar DEPOIS do beginTransaction().
        exigirAcessoTarefa($pdo, $id, $usuarioId);

        $stmt = $pdo->prepare('
            UPDATE TAREFA
            SET ARQUIVADO = ?, DATA_ARQUIVAMENTO = ?
            WHERE ID_TAREFA = ?
        ');
        $stmt->execute([$arquivar ? 1 : 0, $arquivar ? date('Y-m-d H:i:s') : null, $id]);

        registrarHistorico(
            $pdo,
            $id,
            $usuarioId,
            $arquivar ? 'ARQUIVAMENTO' : 'RESTAURACAO',
            $arquivar ? 'Tarefa arquivada' : 'Tarefa restaurada'
        );

        $pdo->commit();
        respostaJson(['sucesso' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respostaJson(['erro' => 'Não foi possível arquivar/restaurar a tarefa: ' . $e->getMessage()], 500);
    }
}

/** Insere os vínculos de TAREFA_USUARIOS para os IDs informados. */
function vincularUsuarios(PDO $pdo, int $idTarefa, array $usuarios): void
{
    if (count($usuarios) === 0) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO TAREFA_USUARIOS (ID_TAREFA, ID_USUARIOS) VALUES (?, ?)');
    foreach ($usuarios as $idUsuario) {
        $idUsuario = (int)$idUsuario;
        if ($idUsuario > 0) {
            $stmt->execute([$idTarefa, $idUsuario]);
        }
    }
}
