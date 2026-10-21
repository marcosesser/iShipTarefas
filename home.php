<?php
require_once __DIR__ . '/includes/funcoes.php';

// includes/funcoes.php define Content-Type: application/json (para os
// endpoints da API) - aqui servimos HTML, então sobrescrevo de volta.
header('Content-Type: text/html; charset=utf-8');

// Barreira do lado do servidor: sem sessão válida, nem chega a baixar
// o HTML/board. Acessar a URL direto (ex.: digitando /home.php na barra
// de endereços) sem estar logado agora redireciona antes de qualquer
// conteúdo ser enviado ao navegador.
if (!usuarioLogado()) {
    header('Location: index.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gerenciador de Tarefas</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=14">
</head>
<body>

<div class="app">

    <!-- ===================== MENU LATERAL ===================== -->
    <aside class="sidebar">
        <div class="logo-sistema">
            <div class="marca">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="4" width="6" height="16" rx="1.5" fill="white" fill-opacity="0.95"/>
                    <rect x="10.5" y="4" width="6" height="10" rx="1.5" fill="white" fill-opacity="0.7"/>
                    <rect x="18" y="4" width="3" height="13" rx="1.5" fill="white" fill-opacity="0.5"/>
                </svg>
            </div>
            <span>Painel</span>
        </div>

        <ul class="menu-lateral">
            <li>
                <button type="button" id="btnNovoUsuario">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c1.5-4 5-5.5 7.5-5.5s6 1.5 7.5 5.5"/></svg>
                    <span>Cadastrar Usuário</span>
                </button>
            </li>
            <li>
                <button type="button" id="btnNovaTag">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 4H6a2 2 0 0 0-2 2v5l9.5 9.5a2 2 0 0 0 2.8 0l5.2-5.2a2 2 0 0 0 0-2.8L11 4Z"/><circle cx="8" cy="8" r="1.4" fill="currentColor" stroke="none"/></svg>
                    <span>Cadastrar TAG</span>
                </button>
            </li>
            <li>
                <button type="button" id="btnNovaTarefa">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="3.5" width="16" height="17" rx="2"/><path d="M9 3.5V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v.5M9 12h6M9 16h6"/></svg>
                    <span>Cadastrar Tarefa</span>
                </button>
            </li>
            <li class="separador"></li>
            <li>
                <button type="button" id="btnRelatorio">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 20V10M12 20V4M19 20v-7"/></svg>
                    <span>Relatório de Tarefas</span>
                </button>
            </li>
            <li>
                <button type="button" id="btnArquivadas">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7h18M4 7v12a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V7M9 12h6"/><path d="M3 7l1.5-3.5A1 1 0 0 1 5.4 3h13.2a1 1 0 0 1 .9.6L21 7"/></svg>
                    <span>Tarefas Arquivadas</span>
                </button>
            </li>
        </ul>

        <div class="sidebar-rodape">
            <div class="avatar" id="avatarUsuarioLogado">--</div>
            <div class="info-usuario">
                <div class="nome-usuario" id="nomeUsuarioLogado">Carregando...</div>
                <div class="nome-empresa-ativa" id="nomeEmpresaAtiva"></div>
                <button type="button" id="btnSair">Sair</button>
            </div>
        </div>
    </aside>

    <!-- ===================== CONTEÚDO / BOARD ===================== -->
    <main class="conteudo">
        <div class="cabecalho-conteudo">
            <h1>Minhas tarefas</h1>
            <span class="data-hoje" id="dataHoje"></span>
        </div>

        <div class="board" id="board">
            <!-- Colunas geradas dinamicamente pelo home.js -->
        </div>
    </main>
</div>


<!-- ===================== MODAL: NOVO USUÁRIO ===================== -->
<dialog id="dialogUsuario">
    <form method="dialog" id="formUsuario">
        <div class="modal-cabecalho">
            <h2>Cadastrar Usuário</h2>
            <button type="button" class="botao-fechar" data-fechar-dialog="dialogUsuario">✕</button>
        </div>
        <div class="modal-corpo">
            <div class="campo">
                <label for="usuarioNome">Nome</label>
                <input type="text" id="usuarioNome" required>
            </div>
            <div class="campo">
                <label for="usuarioLogin">Login (e-mail)</label>
                <input type="email" id="usuarioLogin" required>
            </div>
            <div class="campo">
                <label for="usuarioSenha">Senha</label>
                <input type="password" id="usuarioSenha" minlength="6" required>
                <p class="texto-ajuda">Mínimo de 6 caracteres.</p>
            </div>
        </div>
        <div class="modal-rodape">
            <button type="button" class="botao botao-secundario" data-fechar-dialog="dialogUsuario">Cancelar</button>
            <button type="submit" class="botao botao-primario">Salvar</button>
        </div>
    </form>
</dialog>


<!-- ===================== MODAL: NOVA TAG ===================== -->
<dialog id="dialogTag">
    <form method="dialog" id="formTag">
        <div class="modal-cabecalho">
            <h2 id="tituloModalTag">Cadastrar TAG</h2>
            <button type="button" class="botao-fechar" data-fechar-dialog="dialogTag">✕</button>
        </div>
        <div class="modal-corpo">
            <input type="hidden" id="tagId">
            <div class="campo">
                <label for="tagDescricao">Descrição</label>
                <input type="text" id="tagDescricao" required>
            </div>
            <div class="campo">
                <label for="tagOrdem">Ordem de exibição</label>
                <input type="number" id="tagOrdem" min="1" required>
                <p class="texto-ajuda">Define a posição da coluna na tela inicial. Não pode repetir uma ordem já usada.</p>
            </div>
        </div>
        <div class="modal-rodape">
            <button type="button" class="botao botao-secundario" data-fechar-dialog="dialogTag">Cancelar</button>
            <button type="submit" class="botao botao-primario">Salvar</button>
        </div>
    </form>
</dialog>


<!-- ===================== MODAL: TAREFA (criar / editar / detalhe) ===================== -->
<dialog id="dialogTarefa" class="modal-largo">
    <form method="dialog" id="formTarefa">
        <div class="modal-cabecalho">
            <h2 id="tituloModalTarefa">Cadastrar Tarefa <span class="etiqueta-arquivada" id="etiquetaArquivada" hidden>Arquivada</span></h2>
            <button type="button" class="botao-fechar" data-fechar-dialog="dialogTarefa">✕</button>
        </div>
        <div class="modal-corpo">
            <input type="hidden" id="tarefaId">

            <div class="bloco-dados-tarefa">
                <p class="aviso-somente-leitura" id="avisoSomenteLeitura" hidden>Só quem criou esta tarefa pode editar estes dados - você pode visualizar, mover entre colunas e adicionar eventos.</p>

                <div class="campo">
                    <label for="tarefaTag">TAG</label>
                    <select id="tarefaTag" required></select>
                </div>

                <div class="campo">
                    <label for="tarefaDescricao">Descrição</label>
                    <input type="text" id="tarefaDescricao" required>
                </div>

                <div class="campo">
                    <label for="tarefaObservacao">Observação</label>
                    <textarea id="tarefaObservacao"></textarea>
                </div>

                <div class="campo" style="margin-bottom:0;">
                    <label>Usuários vinculados</label>
                    <div class="lista-checkbox" id="listaUsuariosVinculo"></div>
                    <p class="texto-ajuda">Se nenhum usuário for marcado, a tarefa fica visível para todos.</p>
                </div>
            </div>

            <div class="secao-modal" id="secaoAnexos" hidden>
                <h3>Anexos</h3>
                <div class="lista-anexos" id="listaAnexos"></div>

                <!-- Só aparece cadastrando uma tarefa nova: dá pra selecionar
                     vários arquivos de uma vez, enviados assim que salvar.
                     Na edição, anexos novos só entram via Eventos. -->
                <div id="areaNovoAnexoCriacao" hidden>
                    <div class="linha-upload">
                        <input type="file" id="inputAnexosCriacao" multiple>
                    </div>
                    <p class="texto-ajuda">Pode selecionar vários arquivos de uma vez - são enviados assim que a tarefa for salva.</p>
                </div>
            </div>

            <div class="secao-modal" id="secaoEventos" hidden>
                <h3>Linha do Tempo</h3>
                <p class="texto-ajuda" style="margin-top:-6px;margin-bottom:10px;">Ações automáticas do sistema (criação, movimentação, arquivamento) aparecem junto com os comentários que você adicionar.</p>
                <div class="lista-eventos" id="listaEventos"></div>

                <div class="caixa-novo-evento">
                    <h4 id="tituloNovoEvento">Adicionar evento</h4>
                    <input type="hidden" id="eventoEmEdicaoId">
                    <div class="campo">
                        <input type="text" id="eventoDescricao" placeholder="Descrição do evento">
                    </div>
                    <div class="campo">
                        <textarea id="eventoObservacao" placeholder="Observação (opcional)" style="min-height:56px;"></textarea>
                    </div>
                    <div class="linha-upload" style="margin-top:0;">
                        <input type="file" id="inputAnexoEvento" multiple title="Anexo(s) opcional(is) para este evento">
                    </div>
                    <div style="display:flex; gap:8px; margin-top:10px;">
                        <button type="button" class="botao botao-secundario" id="btnAdicionarEvento">Adicionar evento</button>
                        <button type="button" class="botao botao-texto" id="btnCancelarEdicaoEvento" hidden>Cancelar edição</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-rodape">
            <button type="button" class="botao botao-perigo" id="btnArquivarTarefa" hidden>Arquivar</button>
            <span style="flex:1;"></span>
            <button type="button" class="botao botao-secundario" data-fechar-dialog="dialogTarefa">Cancelar</button>
            <button type="submit" class="botao botao-primario">Salvar Tarefa</button>
        </div>
    </form>
</dialog>


<!-- ===================== MODAL: TAREFAS ARQUIVADAS ===================== -->
<dialog id="dialogArquivadas" class="modal-largo">
    <div class="modal-cabecalho">
        <h2>Tarefas Arquivadas</h2>
        <button type="button" class="botao-fechar" data-fechar-dialog="dialogArquivadas">✕</button>
    </div>
    <div class="modal-corpo">
        <div class="lista-anexos" id="listaArquivadas"></div>
    </div>
    <div class="modal-rodape">
        <button type="button" class="botao botao-secundario" data-fechar-dialog="dialogArquivadas">Fechar</button>
    </div>
</dialog>


<!-- ===================== MODAL: RELATÓRIO ===================== -->
<dialog id="dialogRelatorio" class="modal-extra-largo">
    <div class="modal-cabecalho">
        <h2>Relatório de Tarefas</h2>
        <button type="button" class="botao-fechar" data-fechar-dialog="dialogRelatorio">✕</button>
    </div>
    <div class="modal-corpo">
        <div class="campo">
            <label for="filtroBusca">Buscar por texto</label>
            <input type="text" id="filtroBusca" placeholder="Procura na descrição, na observação e nos eventos da tarefa...">
        </div>

        <div class="filtros-relatorio">
            <div class="campo" style="margin-bottom:0;">
                <label for="filtroStatus">Status</label>
                <select id="filtroStatus">
                    <option value="todas">Todas</option>
                    <option value="ativas">Somente ativas</option>
                    <option value="arquivadas">Somente arquivadas</option>
                </select>
            </div>
            <div class="campo" style="margin-bottom:0;">
                <label for="filtroTag">TAG</label>
                <select id="filtroTag"><option value="">Todas</option></select>
            </div>
            <div class="campo" style="margin-bottom:0;">
                <label for="filtroUsuario">Usuário</label>
                <select id="filtroUsuario"><option value="">Todos</option></select>
            </div>
            <div class="campo" style="margin-bottom:0;">
                <label for="filtroDataInicio">De</label>
                <input type="date" id="filtroDataInicio">
            </div>
            <div class="campo" style="margin-bottom:0;">
                <label for="filtroDataFim">Até</label>
                <input type="date" id="filtroDataFim">
            </div>
            <div class="campo" style="margin-bottom:0;">
                <label for="filtroPorPagina">Por página</label>
                <select id="filtroPorPagina">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
        <button type="button" class="botao botao-primario" id="btnFiltrarRelatorio" style="margin-bottom:18px;">Filtrar</button>

        <table class="tabela-relatorio">
            <thead>
                <tr>
                    <th>Tarefa</th>
                    <th>Status</th>
                    <th>TAG</th>
                    <th>Usuários</th>
                    <th>Cadastrado por</th>
                    <th>Data</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="corpoTabelaRelatorio"></tbody>
        </table>

        <div class="paginacao-relatorio">
            <button type="button" class="botao botao-secundario" id="btnPaginaAnterior">← Anterior</button>
            <span id="infoPaginacao">Página 1 de 1</span>
            <button type="button" class="botao botao-secundario" id="btnProximaPagina">Próxima →</button>
        </div>
    </div>
    <div class="modal-rodape">
        <button type="button" class="botao botao-secundario" data-fechar-dialog="dialogRelatorio">Fechar</button>
    </div>
</dialog>


<script src="js/api.js?v=14"></script>
<script src="js/home.js?v=14"></script>
</body>
</html>
