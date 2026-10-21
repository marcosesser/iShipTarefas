<?php
require_once __DIR__ . '/includes/funcoes.php';

// includes/funcoes.php define Content-Type: application/json (usado
// pelos endpoints da API) - aqui servimos HTML, então sobrescrevo.
header('Content-Type: text/html; charset=utf-8');

// Barreira do lado do servidor: sem sessão de suporte válida, nem
// chega a baixar o HTML.
if (!suporteLogado()) {
    header('Location: index.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel de Suporte · Gerenciador de Tarefas</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=2">
</head>
<body>

<div class="app">
    <aside class="sidebar">
        <div class="logo-sistema">
            <div class="marca">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 7h18M4 7v12a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V7" stroke="white" stroke-width="1.8" stroke-opacity="0.9"/>
                    <path d="M3 7l1.5-3.5A1 1 0 0 1 5.4 3h13.2a1 1 0 0 1 .9.6L21 7" stroke="white" stroke-width="1.8" stroke-opacity="0.9"/>
                </svg>
            </div>
            <span>Suporte</span>
        </div>

        <div class="sidebar-rodape">
            <div class="avatar" id="avatarSuporte">--</div>
            <div class="info-usuario">
                <div class="nome-usuario" id="nomeSuporte">Carregando...</div>
                <button type="button" id="btnSair">Sair</button>
            </div>
        </div>
    </aside>

    <main class="conteudo">
        <div class="cabecalho-conteudo">
            <h1>Empresas cadastradas</h1>
            <button type="button" class="botao botao-primario" id="btnNovaEmpresa">+ Cadastrar Empresa</button>
        </div>

        <table class="tabela-empresas">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Código</th>
                    <th>Status</th>
                    <th>Cadastrada em</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="corpoTabelaEmpresas"></tbody>
        </table>
    </main>
</div>


<!-- ===================== MODAL: NOVA EMPRESA ===================== -->
<dialog id="dialogNovaEmpresa">
    <form method="dialog" id="formNovaEmpresa">
        <div class="modal-cabecalho">
            <h2>Cadastrar Empresa</h2>
            <button type="button" class="botao-fechar" data-fechar-dialog="dialogNovaEmpresa">✕</button>
        </div>
        <div class="modal-corpo">
            <div class="campo">
                <label for="novaEmpresaNome">Nome da empresa</label>
                <input type="text" id="novaEmpresaNome" required>
            </div>
            <div class="campo">
                <label for="novaEmpresaNomeAdmin">Nome do responsável</label>
                <input type="text" id="novaEmpresaNomeAdmin" required>
            </div>
            <div class="campo">
                <label for="novaEmpresaLoginAdmin">E-mail do responsável</label>
                <input type="email" id="novaEmpresaLoginAdmin" required>
            </div>
            <div class="campo" style="margin-bottom:0;">
                <label for="novaEmpresaSenhaAdmin">Senha inicial</label>
                <input type="password" id="novaEmpresaSenhaAdmin" minlength="6" required>
                <p class="texto-ajuda">Mínimo de 6 caracteres. Passe essa senha pro responsável junto com o código gerado.</p>
            </div>
        </div>
        <div class="modal-rodape">
            <button type="button" class="botao botao-secundario" data-fechar-dialog="dialogNovaEmpresa">Cancelar</button>
            <button type="submit" class="botao botao-primario">Cadastrar</button>
        </div>
    </form>
</dialog>


<!-- ===================== MODAL: CÓDIGO GERADO ===================== -->
<dialog id="dialogCodigoGerado">
    <div class="modal-cabecalho">
        <h2>Empresa cadastrada!</h2>
    </div>
    <div class="modal-corpo">
        <p class="texto-ajuda">Esse é o código da empresa. O jeito mais prático de repassar é o link abaixo: ao abrir, o código já vem preenchido no login (o cliente só digita e-mail e senha).</p>
        <div class="codigo-gerado" id="codigoGeradoTexto"></div>
        <div class="campo" style="margin-top:16px; margin-bottom:0;">
            <label for="linkGeradoTexto">Link de acesso</label>
            <input type="text" id="linkGeradoTexto" readonly>
        </div>
    </div>
    <div class="modal-rodape">
        <button type="button" class="botao botao-secundario" id="btnCopiarLinkGerado">Copiar link</button>
        <button type="button" class="botao botao-primario" data-fechar-dialog="dialogCodigoGerado">Fechar</button>
    </div>
</dialog>


<script src="js/api.js?v=2"></script>
<script src="js/painel.js?v=2"></script>
</body>
</html>
