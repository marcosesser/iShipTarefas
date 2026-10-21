<?php
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../config/conexao.php';

exigirLoginSuporte();
$pdoMestre = conectarBancoMestre();
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {

    case 'GET':
        listarEmpresas($pdoMestre);
        break;

    case 'POST':
        cadastrarEmpresa($pdoMestre);
        break;

    case 'PUT':
        alternarStatusEmpresa($pdoMestre);
        break;

    default:
        respostaJson(['erro' => 'Método não permitido'], 405);
}


/** Link de acesso pronto: abre o login do sistema principal já com o código da empresa preenchido. */
function montarLinkAcesso(string $codigo): string
{
    return rtrim(URL_SISTEMA_PRINCIPAL, '/') . '/?empresa=' . urlencode($codigo);
}

function listarEmpresas(PDO $pdoMestre): void
{
    $stmt = $pdoMestre->query('
        SELECT ID_EMPRESA, NOME, CODIGO, ATIVA, DATA_CADASTRO
        FROM EMPRESAS
        ORDER BY NOME
    ');
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($empresas as &$e) {
        $e['LINK_ACESSO'] = montarLinkAcesso($e['CODIGO']);
    }
    unset($e);

    respostaJson($empresas);
}

/**
 * Cadastra uma empresa nova: gera o código de acesso, copia o banco
 * MODELO.FDB pra um arquivo exclusivo dessa empresa, cria o primeiro
 * usuário (administrador) direto nesse banco novo, e só então registra
 * a empresa no banco mestre.
 */
function cadastrarEmpresa(PDO $pdoMestre): void
{
    $dados       = corpoRequisicao();
    $nomeEmpresa = limparTexto($dados['nome_empresa'] ?? '');
    $nomeAdmin   = limparTexto($dados['nome_admin'] ?? '');
    $loginAdmin  = limparTexto($dados['login_admin'] ?? '');
    $senhaAdmin  = (string)($dados['senha_admin'] ?? '');

    if ($nomeEmpresa === '' || $nomeAdmin === '' || $loginAdmin === '' || $senhaAdmin === '') {
        respostaJson(['erro' => 'Preencha todos os campos'], 400);
    }
    if (!filter_var($loginAdmin, FILTER_VALIDATE_EMAIL)) {
        respostaJson(['erro' => 'Informe um e-mail válido para o administrador'], 400);
    }
    if (strlen($senhaAdmin) < 6) {
        respostaJson(['erro' => 'A senha deve ter ao menos 6 caracteres'], 400);
    }

    if (!is_file(DB_PATH_MODELO)) {
        respostaJson(['erro' => 'Banco modelo (DB_PATH_MODELO) não encontrado no servidor - confira config/conexao.php'], 500);
    }

    $codigo = gerarCodigoEmpresa($pdoMestre);

    if (!is_dir(PASTA_BANCOS_EMPRESAS)) {
        mkdir(PASTA_BANCOS_EMPRESAS, 0770, true);
    }
    $caminhoNovoBanco = PASTA_BANCOS_EMPRESAS . $codigo . '.FDB';

    if (file_exists($caminhoNovoBanco)) {
        // Estatisticamente quase impossível (código já é único no banco
        // mestre), mas não custa checar antes de sobrescrever um arquivo.
        respostaJson(['erro' => 'Conflito ao gerar o banco da empresa - tente cadastrar de novo'], 500);
    }

    if (!copy(DB_PATH_MODELO, $caminhoNovoBanco)) {
        respostaJson(['erro' => 'Falha ao copiar o banco modelo para a empresa nova'], 500);
    }

    try {
        // Primeiro usuário (administrador) já direto no banco novo.
        $pdoEmpresa = conectarBancoPorCaminho($caminhoNovoBanco);
        $senhaHash = password_hash($senhaAdmin, PASSWORD_DEFAULT);
        $stmt = $pdoEmpresa->prepare('INSERT INTO USUARIOS (NOME, LOGIN, SENHA) VALUES (?, ?, ?)');
        $stmt->execute([$nomeAdmin, $loginAdmin, $senhaHash]);

        // Só registra a empresa no mestre depois que o banco dela e o
        // primeiro usuário já existem de verdade.
        $stmt = $pdoMestre->prepare('
            INSERT INTO EMPRESAS (NOME, CODIGO, CAMINHO_BANCO)
            VALUES (?, ?, ?)
            RETURNING ID_EMPRESA
        ');
        $stmt->execute([$nomeEmpresa, $codigo, $caminhoNovoBanco]);
        $novoId = $stmt->fetchColumn();

        respostaJson([
            'sucesso' => true,
            'id' => (int)$novoId,
            'codigo' => $codigo,
            'link_acesso' => montarLinkAcesso($codigo),
        ], 201);
    } catch (Exception $e) {
        // Se algo falhou depois de já ter copiado o arquivo, não deixa
        // um banco órfão (sem registro no mestre) para trás.
        if (file_exists($caminhoNovoBanco)) {
            unlink($caminhoNovoBanco);
        }
        respostaJson(['erro' => 'Falha ao provisionar a empresa: ' . $e->getMessage()], 500);
    }
}

function alternarStatusEmpresa(PDO $pdoMestre): void
{
    $dados = corpoRequisicao();
    $id = (int)($dados['id'] ?? 0);
    $ativa = (bool)($dados['ativa'] ?? true);

    if ($id <= 0) {
        respostaJson(['erro' => 'Informe o ID da empresa'], 400);
    }

    $stmt = $pdoMestre->prepare('UPDATE EMPRESAS SET ATIVA = ? WHERE ID_EMPRESA = ?');
    $stmt->execute([$ativa ? 1 : 0, $id]);

    respostaJson(['sucesso' => true]);
}
