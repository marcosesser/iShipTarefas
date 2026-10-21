<?php
/**
 * Funções auxiliares do projeto de SUPORTE. Autenticação própria e
 * INDEPENDENTE da autenticação de usuários de empresa - mesmo que os
 * dois projetos rodem no mesmo servidor, uma sessão de suporte nunca
 * é confundida com uma sessão de empresa (chaves de sessão diferentes,
 * tabelas diferentes, nem o mesmo código de login.php).
 */

// Nome de cookie PRÓPRIO deste projeto - diferente do usado pelo
// sistema principal. Sem isso, os dois projetos rodando no mesmo
// domínio (ex.: subpastas de localhost) compartilhariam o cookie
// PHPSESSID padrão e, por baixo dos panos, a MESMA sessão.
session_name('SUPORTEADMIN_SESSAO');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

/** Suporte está com sessão ativa? */
function suporteLogado(): bool
{
    return isset($_SESSION['suporte_id']);
}

/** ID do usuário de suporte logado (ou null). */
function idSuporteLogado(): ?int
{
    return $_SESSION['suporte_id'] ?? null;
}

/** Bloqueia o acesso e encerra a requisição se não houver login de suporte. */
function exigirLoginSuporte(): void
{
    if (!suporteLogado()) {
        respostaJson(['erro' => 'Sessão expirada, faça login novamente'], 401);
    }
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
 * Gera um código de empresa novo: 6 caracteres, maiúsculo, sem os
 * caracteres que mais confundem visualmente (O/0, I/1). Confere no
 * banco mestre até achar um que não exista ainda.
 */
function gerarCodigoEmpresa(PDO $pdoMestre): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem O, I, 0, 1
    do {
        $codigo = '';
        for ($i = 0; $i < 6; $i++) {
            $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
        $stmt = $pdoMestre->prepare('SELECT 1 FROM EMPRESAS WHERE CODIGO = ?');
        $stmt->execute([$codigo]);
    } while ($stmt->fetch());

    return $codigo;
}
