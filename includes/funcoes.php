<?php
/**
 * Funções auxiliares usadas por todos os endpoints da API.
 * Este arquivo já inicia a sessão e define o cabeçalho JSON padrão,
 * então basta dar require_once nele em cada arquivo de api/.
 */

// Nome de cookie PRÓPRIO deste projeto. Sem isso, se o Painel de
// Suporte (projeto separado) rodar no mesmo domínio (ex.: os dois em
// subpastas de localhost), os dois usariam o cookie padrão PHPSESSID
// e acabariam compartilhando a MESMA sessão por baixo dos panos -
// sair de um poderia derrubar a sessão do outro.
session_name('GERTAREFAS_SESSAO');

// Configura o cookie de sessão de forma mais segura ANTES de iniciar a
// sessão: HttpOnly impede que JavaScript leia o cookie (mitiga roubo de
// sessão via XSS), SameSite=Lax barra o envio do cookie em requisições
// disparadas por outros sites (mitiga CSRF), e Secure liga sozinho assim
// que o site estiver rodando em HTTPS (evita quebrar o login enquanto
// ainda estiver em HTTP puro).
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

/** Usuário está com sessão ativa (login + empresa resolvida)? */
function usuarioLogado(): bool
{
    return isset($_SESSION['usuario_id'], $_SESSION['empresa_caminho_banco']);
}

/** ID do usuário logado (ou null se não estiver logado). */
function idUsuarioLogado(): ?int
{
    return $_SESSION['usuario_id'] ?? null;
}

/** Bloqueia o acesso e encerra a requisição se não houver login. */
function exigirLogin(): void
{
    if (!usuarioLogado()) {
        respostaJson(['erro' => 'Sessão expirada, faça login novamente'], 401);
    }
}

/**
 * Verifica se o usuário logado pode ver/alterar a tarefa informada,
 * seguindo a MESMA regra de visibilidade usada no board (pública se
 * não houver ninguém vinculado, restrita a quem está vinculado caso
 * haja). Sem essa checagem, qualquer usuário autenticado conseguiria
 * ler ou editar tarefas de terceiros só adivinhando o ID (IDOR) -
 * use isso em TODO endpoint que recebe um id_tarefa vindo do cliente.
 */
function usuarioPodeAcessarTarefa(PDO $pdo, int $idTarefa, int $usuarioId): bool
{
    $stmt = $pdo->prepare('
        SELECT 1
        FROM TAREFA t
        WHERE t.ID_TAREFA = ?
        AND t.ATIVO = 1
        AND (
            NOT EXISTS (SELECT 1 FROM TAREFA_USUARIOS tu WHERE tu.ID_TAREFA = t.ID_TAREFA)
            OR EXISTS (SELECT 1 FROM TAREFA_USUARIOS tu WHERE tu.ID_TAREFA = t.ID_TAREFA AND tu.ID_USUARIOS = ?)
        )
    ');
    $stmt->execute([$idTarefa, $usuarioId]);
    return (bool)$stmt->fetchColumn();
}

/** Encerra a requisição com 404 quando o usuário não pode acessar a tarefa (sem revelar se ela existe). */
function exigirAcessoTarefa(PDO $pdo, int $idTarefa, int $usuarioId): void
{
    if (!usuarioPodeAcessarTarefa($pdo, $idTarefa, $usuarioId)) {
        // Seguro para chamar de dentro de uma transação: desfaz antes
        // de responder, já que respostaJson() encerra o script com exit.
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respostaJson(['erro' => 'Tarefa não encontrada'], 404);
    }
}

/**
 * Resolve a qual tarefa um evento (TAREFA_EVENTOS) pertence e garante
 * que o usuário logado tem acesso a ela. Usado por api/eventos.php e
 * api/eventos_anexo.php, que recebem um id_evento e não um id_tarefa
 * diretamente.
 */
function exigirAcessoAoEvento(PDO $pdo, int $idEvento, int $usuarioId): int
{
    $stmt = $pdo->prepare('SELECT ID_TAREFA FROM TAREFA_EVENTOS WHERE ID_TAREFA_EVENTOS = ?');
    $stmt->execute([$idEvento]);
    $idTarefa = $stmt->fetchColumn();

    if ($idTarefa === false) {
        respostaJson(['erro' => 'Evento não encontrado'], 404);
    }
    exigirAcessoTarefa($pdo, (int)$idTarefa, $usuarioId);
    return (int)$idTarefa;
}

/**
 * Regra de permissão: só quem CRIOU a tarefa edita descrição/observação/
 * usuários vinculados e apaga anexos da tarefa. Qualquer vinculado
 * continua podendo mover o card entre TAGs e arquivar/restaurar - isso
 * não conta como "editar conteúdo".
 */
function usuarioECriadorDaTarefa(PDO $pdo, int $idTarefa, int $usuarioId): bool
{
    $stmt = $pdo->prepare('SELECT ID_USUARIO_CADASTRO FROM TAREFA WHERE ID_TAREFA = ?');
    $stmt->execute([$idTarefa]);
    $criador = $stmt->fetchColumn();

    return $criador !== false && (int)$criador === $usuarioId;
}

/** Encerra a requisição com 403 se o usuário logado não for quem criou a tarefa. */
function exigirCriadorDaTarefa(PDO $pdo, int $idTarefa, int $usuarioId): void
{
    if (!usuarioECriadorDaTarefa($pdo, $idTarefa, $usuarioId)) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respostaJson(['erro' => 'Só quem criou a tarefa pode fazer isso.'], 403);
    }
}

/**
 * Regra de permissão: só quem escreveu um evento (comentário) pode
 * editá-lo, excluí-lo ou anexar arquivo nele depois. Os demais
 * usuários vinculados à tarefa continuam podendo VER o evento e seu
 * anexo, só não podem mexer no que não é deles.
 */
function exigirAutorDoEvento(PDO $pdo, int $idEvento, int $usuarioId): void
{
    $stmt = $pdo->prepare('SELECT ID_USUARIO FROM TAREFA_EVENTOS WHERE ID_TAREFA_EVENTOS = ?');
    $stmt->execute([$idEvento]);
    $autor = $stmt->fetchColumn();

    if ($autor === false || (int)$autor !== $usuarioId) {
        respostaJson(['erro' => 'Só quem criou o evento pode fazer isso.'], 403);
    }
}

/**
 * Registra uma entrada automática no histórico/auditoria da tarefa.
 * Diferente de TAREFA_EVENTOS (comentários que o usuário escreve e
 * pode editar), isto é gerado pelo próprio backend e nunca é editado
 * ou apagado pela tela - é o registro de fato do que aconteceu.
 *
 * @param string $acao 'CRIACAO' | 'MOVIMENTACAO' | 'EDICAO' | 'ARQUIVAMENTO' | 'RESTAURACAO'
 */
function registrarHistorico(
    PDO $pdo,
    int $idTarefa,
    ?int $usuarioId,
    string $acao,
    string $descricao,
    ?string $valorAnterior = null,
    ?string $valorNovo = null
): void {
    $stmt = $pdo->prepare('
        INSERT INTO TAREFA_HISTORICO (ID_TAREFA, ID_USUARIO, ACAO, DESCRICAO, VALOR_ANTERIOR, VALOR_NOVO)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$idTarefa, $usuarioId, $acao, $descricao, $valorAnterior, $valorNovo]);
}

/** Envia uma resposta JSON padronizada e encerra o script. */
function respostaJson($dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Lê e decodifica o corpo JSON de uma requisição (POST/PUT). */
function corpoRequisicao(): array
{
    $json = file_get_contents('php://input');
    $dados = json_decode($json, true);
    return is_array($dados) ? $dados : [];
}

/** Remove tags e espaços extras de uma string vinda do usuário. */
function limparTexto(?string $valor): string
{
    return trim(strip_tags((string)$valor));
}

/**
 * Subpasta isolada por empresa dentro de uploads/ - mesmo que duas
 * empresas rodem no mesmo servidor/deploy, os anexos de uma nunca
 * ficam na mesma pasta física da outra (evita qualquer chance de um
 * nome de arquivo cruzar entre empresas).
 */
function subpastaEmpresa(): string
{
    $empresaId = (int)($_SESSION['empresa_id'] ?? 0);
    return 'empresa_' . $empresaId;
}
