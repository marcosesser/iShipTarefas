<?php
/**
 * Configuração de conexão com o banco Firebird via PDO_FIREBIRD.
 *
 * IMPORTANTE: a extensão pdo_firebird precisa estar habilitada no
 * seu PHP (php.ini -> extension=pdo_firebird). Ela já vem junto do
 * PHP no Windows; no Linux costuma vir no pacote php-interbase ou
 * precisa ser compilada com --with-pdo-firebird.
 *
 * Sistema multi-empresa: cada empresa tem o SEU PRÓPRIO arquivo .FDB
 * (isolamento real - uma empresa não consegue nem acidentalmente
 * consultar dado de outra, porque a conexão nem chega perto do banco
 * dela). Existe também um banco MESTRE, separado, que só guarda a
 * lista de empresas (código -> caminho do banco). A equipe de suporte
 * fica num projeto totalmente à parte (veja /suporte-admin), que
 * também aponta pra esse mesmo MESTRE.FDB.
 *
 * Ajuste as constantes abaixo conforme o seu ambiente.
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'SYSDBA');
define('DB_PASS', 'masterkey');
define('DB_CHARSET', 'UTF8');

// Banco mestre: só a lista de empresas. Ajuste o caminho conforme
// onde você criou o MESTRE.FDB (veja database/mestre_schema.sql).
define('DB_PATH_MESTRE', 'C:\\dados\\MESTRE.FDB');

/**
 * Abre uma conexão PDO para o caminho de banco informado. Função de
 * baixo nível usada tanto por conectarBanco()/conectarBancoMestre()
 * quanto pelo projeto de suporte, que também precisa abrir bancos de
 * empresa (pra criar o primeiro usuário na hora do cadastro).
 */
function conectarBancoPorCaminho(string $caminho): PDO
{
    $dsn = 'firebird:dbname=' . DB_HOST . ':' . $caminho . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        // Detalhe completo só no log do servidor - não expor ao cliente
        // caminho de banco, usuário ou driver, que ajudariam um atacante.
        error_log('Falha ao conectar no Firebird (' . $caminho . '): ' . $e->getMessage());
        respostaJson(['erro' => 'Falha na conexão com o banco de dados'], 500);
    }
}

/** Conexão com o banco MESTRE (lista de empresas) - usada só no login, pra resolver o código digitado. */
function conectarBancoMestre(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = conectarBancoPorCaminho(DB_PATH_MESTRE);
    }
    return $pdo;
}

/**
 * Conexão com o banco DA EMPRESA logada - é essa que todo o resto do
 * sistema (tarefas, tags, usuários, anexos...) usa. O caminho vem da
 * sessão, guardado ali no momento do login (ver api/login.php).
 */
function conectarBanco(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $caminho = $_SESSION['empresa_caminho_banco'] ?? null;
        if (!$caminho) {
            respostaJson(['erro' => 'Sessão inválida, faça login novamente'], 401);
        }
        $pdo = conectarBancoPorCaminho($caminho);
    }

    return $pdo;
}
