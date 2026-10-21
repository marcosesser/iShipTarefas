<?php
/**
 * Configuração de conexão com o Firebird - projeto de SUPORTE.
 *
 * Este projeto é separado do sistema principal (deploy independente,
 * pode até ficar numa URL/servidor diferente), mas conecta nos MESMOS
 * arquivos de banco: o MESTRE.FDB (lista de empresas) e, na hora de
 * cadastrar uma empresa nova, o banco MODELO.FDB (usado como molde) e
 * a pasta onde ficam os bancos de cada empresa.
 *
 * Ajuste os caminhos abaixo para bater com o que está configurado no
 * config/conexao.php do projeto principal.
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'SYSDBA');
define('DB_PASS', 'masterkey');
define('DB_CHARSET', 'UTF8');

// Mesmo banco mestre do projeto principal.
define('DB_PATH_MESTRE', 'C:\\dados\\MESTRE.FDB');

// Banco "molde": criado uma vez rodando database/schema.sql (o mesmo
// script usado pelo projeto principal), sem nenhum dado dentro. Ao
// cadastrar uma empresa nova, este arquivo é apenas COPIADO para um
// caminho novo - não precisa de permissão de CREATE DATABASE no Firebird.
define('DB_PATH_MODELO', 'C:\\dados\\MODELO.FDB');

// Endereço do SISTEMA PRINCIPAL (a tela de login que o cliente usa).
// Serve pra o painel montar o link pronto de cada empresa, no formato
// <endereço>/?empresa=CODIGO - ao abrir esse link, o código já vem
// preenchido no login. Ajuste conforme onde o sistema principal roda.
define('URL_SISTEMA_PRINCIPAL', 'http://localhost/gerenciador-tarefas/');

// Pasta onde os bancos de cada empresa são criados (uma cópia do
// MODELO.FDB por empresa, nomeada com o código gerado).
define('PASTA_BANCOS_EMPRESAS', 'C:\\dados\\bancos_empresas\\');

/**
 * Abre uma conexão PDO para o caminho de banco informado.
 */
function conectarBancoPorCaminho(string $caminho): PDO
{
    $dsn = 'firebird:dbname=' . DB_HOST . ':' . $caminho . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Falha ao conectar no Firebird (' . $caminho . '): ' . $e->getMessage());
        respostaJson(['erro' => 'Falha na conexão com o banco de dados'], 500);
    }
}

/** Conexão com o banco mestre (lista de empresas). */
function conectarBancoMestre(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = conectarBancoPorCaminho(DB_PATH_MESTRE);
    }
    return $pdo;
}
