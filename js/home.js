/**
 * Lógica da tela inicial: carrega dados da API, monta o board estilo
 * kanban, cuida do drag-and-drop dos cards entre TAGs e controla os
 * modais de cadastro de usuário, TAG, tarefa e o relatório.
 */

const Estado = {
    usuarioLogado: null,
    empresaAtiva: '',
    tags: [],
    tarefas: [],
    usuarios: [],
    tarefaEmEdicaoId: null,
    tarefaEmEdicaoArquivada: false,
    souCriadorDaTarefaAberta: true, // false quando um vinculado (não-criador) abre uma tarefa alheia
    eventoEmEdicaoId: null,
    anexosParaEnviar: [], // arquivos escolhidos ao CRIAR uma tarefa, enviados só depois de salvar
};

const ICONE_ANEXO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.5l-8.5 8.5a4 4 0 0 1-5.7-5.7L15 7a2.5 2.5 0 0 1 3.5 3.5L10 19"/></svg>';
const ICONE_EVENTO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v12H8l-4 4V4z"/></svg>';

document.addEventListener('DOMContentLoaded', iniciar);

/** Ponto de entrada: confirma sessão, carrega dados e liga os eventos. */
async function iniciar() {
    try {
        const sessao = await Api.get('api/sessao.php');
        Estado.usuarioLogado = sessao.usuario;
        Estado.empresaAtiva = sessao.empresa || '';
    } catch (erro) {
        window.location.href = 'index.html';
        return;
    }

    document.getElementById('nomeUsuarioLogado').textContent = Estado.usuarioLogado.nome;
    document.getElementById('avatarUsuarioLogado').textContent = iniciais(Estado.usuarioLogado.nome);
    document.getElementById('nomeEmpresaAtiva').textContent = Estado.empresaAtiva;
    document.getElementById('dataHoje').textContent = new Date().toLocaleDateString('pt-BR', {
        weekday: 'long', day: '2-digit', month: 'long',
    });

    configurarEventosGerais();
    configurarModalUsuario();
    configurarModalTag();
    configurarModalTarefa();
    configurarModalRelatorio();
    configurarModalArquivadas();

    await carregarUsuarios();
    await carregarTagsETarefas();
}

/* ============================================================
   CARREGAMENTO DE DADOS
   ============================================================ */

async function carregarUsuarios() {
    try {
        Estado.usuarios = await Api.get('api/usuarios.php');
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

async function carregarTagsETarefas() {
    try {
        const [tags, tarefas] = await Promise.all([
            Api.get('api/tags.php'),
            Api.get('api/tarefas.php'),
        ]);
        Estado.tags = tags;
        Estado.tarefas = tarefas;
        renderizarBoard();
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

/* ============================================================
   RENDERIZAÇÃO DO BOARD
   ============================================================ */

function renderizarBoard() {
    const board = document.getElementById('board');
    board.innerHTML = '';

    if (Estado.tags.length === 0) {
        board.innerHTML = '<div class="estado-vazio">Nenhuma TAG cadastrada ainda. Use "Cadastrar TAG" no menu lateral para criar a primeira coluna.</div>';
        return;
    }

    Estado.tags.forEach((tag, indice) => board.appendChild(criarColuna(tag, indice)));
}

function criarColuna(tag, indice) {
    const paleta = `paleta-${indice % 6}`;
    const tarefasDaTag = Estado.tarefas.filter((t) => t.ID_TAG === tag.ID_TAG);

    const coluna = document.createElement('div');
    coluna.className = `coluna ${paleta}`;
    coluna.dataset.idTag = tag.ID_TAG;
    coluna.innerHTML = `
        <div class="coluna-cabecalho">
            <span class="barra-cor"></span>
            <h2 title="Clique para editar a TAG">${escaparHtml(tag.DESCRICAO)}</h2>
            <span class="contador">${tarefasDaTag.length}</span>
        </div>
        <div class="lista-cards" data-id-tag="${tag.ID_TAG}"></div>
        <div class="coluna-adicionar">
            <button type="button" class="link-adicionar-card">+ Adicionar tarefa</button>
        </div>
    `;

    const listaCards = coluna.querySelector('.lista-cards');
    tarefasDaTag.forEach((tarefa) => listaCards.appendChild(criarCard(tarefa)));
    configurarDropZone(listaCards);

    coluna.querySelector('.coluna-cabecalho h2').addEventListener('click', () => abrirModalTag(tag));
    coluna.querySelector('.link-adicionar-card').addEventListener('click', () => abrirModalNovaTarefa(tag.ID_TAG));

    return coluna;
}

function criarCard(tarefa) {
    const card = document.createElement('div');
    card.className = 'card-tarefa';
    card.draggable = true;
    card.dataset.idTarefa = tarefa.ID_TAREFA;

    const avatares = (tarefa.USUARIOS || [])
        .slice(0, 4)
        .map((u) => `<div class="avatar avatar-pequeno" title="${escaparHtml(u.nome)}">${iniciais(u.nome)}</div>`)
        .join('');

    const indicadores = [
        tarefa.QTD_ANEXOS > 0 ? `<span>${ICONE_ANEXO}${tarefa.QTD_ANEXOS}</span>` : '',
        tarefa.QTD_EVENTOS > 0 ? `<span>${ICONE_EVENTO}${tarefa.QTD_EVENTOS}</span>` : '',
    ].join('');

    card.innerHTML = `
        <div class="descricao-card">${escaparHtml(tarefa.DESCRICAO)}</div>
        <div class="card-rodape">
            <div class="card-avatares">${avatares}</div>
            <div class="card-indicadores">${indicadores}</div>
        </div>
    `;

    card.addEventListener('click', () => abrirModalEdicaoTarefa(tarefa.ID_TAREFA));
    card.addEventListener('dragstart', (e) => {
        card.classList.add('arrastando');
        e.dataTransfer.setData('text/plain', String(tarefa.ID_TAREFA));
        e.dataTransfer.effectAllowed = 'move';
    });
    card.addEventListener('dragend', () => card.classList.remove('arrastando'));

    return card;
}

/* ============================================================
   DRAG AND DROP (arrastar card entre TAGs)
   ============================================================ */

function configurarDropZone(listaCards) {
    listaCards.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        listaCards.classList.add('arrastando-sobre');
    });

    listaCards.addEventListener('dragleave', () => {
        listaCards.classList.remove('arrastando-sobre');
    });

    listaCards.addEventListener('drop', async (e) => {
        e.preventDefault();
        listaCards.classList.remove('arrastando-sobre');

        const idTarefa = parseInt(e.dataTransfer.getData('text/plain'), 10);
        const novaIdTag = parseInt(listaCards.dataset.idTag, 10);
        const tarefa = Estado.tarefas.find((t) => t.ID_TAREFA === idTarefa);

        if (!tarefa || tarefa.ID_TAG === novaIdTag) return;

        try {
            // Manda só o que muda: o backend preserva descrição/observação/vínculos existentes.
            await Api.put('api/tarefas.php', { id: idTarefa, id_tag: novaIdTag });
            tarefa.ID_TAG = novaIdTag;
            renderizarBoard();
        } catch (erro) {
            Toast.erro(erro.message);
        }
    });
}

/* ============================================================
   UTILITÁRIOS DE MODAL
   ============================================================ */

function configurarEventosGerais() {
    document.querySelectorAll('[data-fechar-dialog]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById(btn.dataset.fecharDialog).close();
        });
    });

    document.getElementById('btnSair').addEventListener('click', async () => {
        try {
            await Api.post('api/logout.php', {});
        } finally {
            window.location.href = 'index.html';
        }
    });

    // Recalcula a altura de qualquer modal aberto quando a janela for
    // redimensionada (ex.: usuário maximiza/restaura a janela do navegador).
    window.addEventListener('resize', () => {
        document.querySelectorAll('dialog[open]').forEach(ajustarAlturaModal);
    });
}

/**
 * Abre um <dialog> e, em seguida, mede em PIXELS reais (não vh, não
 * flex, não grid) o espaço disponível na tela e o quanto o cabeçalho +
 * rodapé já ocupam, travando o corpo do modal (.modal-corpo) num
 * max-height exato para o que sobra, com scroll interno garantido.
 *
 * Isso existe porque tanto Flexbox quanto Grid (com max-height:88vh)
 * deveriam resolver isso sozinhos "no papel", mas na prática o
 * comportamento variou entre navegadores/ambientes. Medir e travar em
 * pixels via JS não depende de nenhuma dessas engines - é aritmética
 * simples com valores já renderizados, então não tem como falhar.
 */
function abrirModal(dialog) {
    dialog.showModal();
    ajustarAlturaModal(dialog);
}

function ajustarAlturaModal(dialog) {
    const MARGEM_TELA = 32; // respiro entre o modal e a borda da janela
    const ALTURA_MINIMA_CORPO = 120; // nunca deixa o corpo menor que isso, mesmo em telas bem pequenas

    const cabecalho = dialog.querySelector('.modal-cabecalho');
    const corpo = dialog.querySelector('.modal-corpo');
    const rodape = dialog.querySelector('.modal-rodape');
    if (!cabecalho || !corpo || !rodape) return;

    const alturaDisponivelTotal = window.innerHeight - MARGEM_TELA;
    dialog.style.maxHeight = alturaDisponivelTotal + 'px';

    const alturaCabecalho = cabecalho.offsetHeight;
    const alturaRodape = rodape.offsetHeight;
    const alturaCorpo = Math.max(alturaDisponivelTotal - alturaCabecalho - alturaRodape, ALTURA_MINIMA_CORPO);

    corpo.style.maxHeight = alturaCorpo + 'px';
    corpo.style.overflowY = 'auto';
}

/** Preenche um <select> com as TAGs cadastradas. */
function popularSelectTags(select, idSelecionado, incluirTodas = false) {
    const opcoes = Estado.tags.map(
        (tag) => `<option value="${tag.ID_TAG}" ${tag.ID_TAG === idSelecionado ? 'selected' : ''}>${escaparHtml(tag.DESCRICAO)}</option>`
    );
    if (incluirTodas) opcoes.unshift('<option value="">Todas</option>');
    select.innerHTML = opcoes.join('');
}

/* ============================================================
   MODAL: USUÁRIO
   ============================================================ */

function configurarModalUsuario() {
    document.getElementById('btnNovoUsuario').addEventListener('click', () => {
        document.getElementById('formUsuario').reset();
        abrirModal(document.getElementById('dialogUsuario'));
    });

    document.getElementById('formUsuario').addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const nome = document.getElementById('usuarioNome').value.trim();
        const login = document.getElementById('usuarioLogin').value.trim();
        const senha = document.getElementById('usuarioSenha').value;

        try {
            await Api.post('api/usuarios.php', { nome, login, senha });
            Toast.sucesso('Usuário cadastrado com sucesso.');
            document.getElementById('dialogUsuario').close();
            await carregarUsuarios();
        } catch (erro) {
            Toast.erro(erro.message);
        }
    });
}

/* ============================================================
   MODAL: TAG
   ============================================================ */

function configurarModalTag() {
    document.getElementById('btnNovaTag').addEventListener('click', () => abrirModalTag(null));

    document.getElementById('formTag').addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const id = document.getElementById('tagId').value;
        const descricao = document.getElementById('tagDescricao').value.trim();
        const ordem = parseInt(document.getElementById('tagOrdem').value, 10);

        try {
            if (id) {
                await Api.put('api/tags.php', { id: parseInt(id, 10), descricao, ordem });
                Toast.sucesso('TAG atualizada.');
            } else {
                await Api.post('api/tags.php', { descricao, ordem });
                Toast.sucesso('TAG cadastrada.');
            }
            document.getElementById('dialogTag').close();
            await carregarTagsETarefas();
        } catch (erro) {
            Toast.erro(erro.message);
        }
    });
}

function abrirModalTag(tag) {
    document.getElementById('tituloModalTag').textContent = tag ? 'Editar TAG' : 'Cadastrar TAG';
    document.getElementById('tagId').value = tag ? tag.ID_TAG : '';
    document.getElementById('tagDescricao').value = tag ? tag.DESCRICAO : '';
    document.getElementById('tagOrdem').value = tag ? tag.ORDEM : proximaOrdemSugerida();
    abrirModal(document.getElementById('dialogTag'));
}

function proximaOrdemSugerida() {
    if (Estado.tags.length === 0) return 1;
    return Math.max(...Estado.tags.map((t) => t.ORDEM)) + 1;
}

/* ============================================================
   MODAL: TAREFA (criar / editar / anexos / eventos)
   ============================================================ */

function configurarModalTarefa() {
    document.getElementById('btnNovaTarefa').addEventListener('click', () => abrirModalNovaTarefa(null));

    document.getElementById('formTarefa').addEventListener('submit', async (evento) => {
        evento.preventDefault();

        const idTag = parseInt(document.getElementById('tarefaTag').value, 10);
        const descricao = document.getElementById('tarefaDescricao').value.trim();
        const observacao = document.getElementById('tarefaObservacao').value.trim();
        const usuarios = Array.from(document.querySelectorAll('#listaUsuariosVinculo input:checked'))
            .map((cb) => parseInt(cb.value, 10));

        if (!idTag || !descricao) {
            Toast.erro('Selecione a TAG e informe a descrição da tarefa.');
            return;
        }

        const payload = { id_tag: idTag, descricao, observacao, usuarios };

        try {
            if (Estado.tarefaEmEdicaoId) {
                payload.id = Estado.tarefaEmEdicaoId;
                await Api.put('api/tarefas.php', payload);
                Toast.sucesso('Tarefa atualizada.');
            } else {
                const resultado = await Api.post('api/tarefas.php', payload);
                Toast.sucesso('Tarefa cadastrada.');
                await enviarAnexosPendentes(resultado.id);
            }
            document.getElementById('dialogTarefa').close();
            await carregarTagsETarefas();
        } catch (erro) {
            Toast.erro(erro.message);
        }
    });

    document.getElementById('inputAnexosCriacao').addEventListener('change', (e) => {
        const novosArquivos = Array.from(e.target.files || []);
        Estado.anexosParaEnviar = [...Estado.anexosParaEnviar, ...novosArquivos];
        e.target.value = ''; // permite selecionar de novo o mesmo arquivo, se remover e quiser readicionar
        renderizarAnexosParaEnviar();
    });

    document.getElementById('btnAdicionarEvento').addEventListener('click', salvarEventoTarefa);
    document.getElementById('btnCancelarEdicaoEvento').addEventListener('click', cancelarEdicaoEvento);
    document.getElementById('btnArquivarTarefa').addEventListener('click', alternarArquivamentoTarefa);
}

function abrirModalNovaTarefa(idTagPreSelecionada) {
    Estado.tarefaEmEdicaoId = null;
    Estado.tarefaEmEdicaoArquivada = false;
    Estado.souCriadorDaTarefaAberta = true; // quem está criando é sempre o dono
    Estado.anexosParaEnviar = [];
    document.getElementById('tituloModalTarefa').firstChild.textContent = 'Cadastrar Tarefa ';
    document.getElementById('etiquetaArquivada').hidden = true;
    document.getElementById('tarefaId').value = '';
    document.getElementById('tarefaDescricao').value = '';
    document.getElementById('tarefaObservacao').value = '';
    popularSelectTags(document.getElementById('tarefaTag'), idTagPreSelecionada);
    popularChecklistUsuarios([]);
    aplicarPermissaoDeEdicao(true);

    // Anexos: na criação já aparece, mas no modo de selecionar arquivos
    // (envio real só acontece depois de salvar a tarefa).
    document.getElementById('secaoAnexos').hidden = false;
    document.getElementById('areaNovoAnexoCriacao').hidden = false;
    document.getElementById('inputAnexosCriacao').value = '';
    renderizarAnexosParaEnviar();

    // Linha do tempo só existe depois que a tarefa já tem ID salvo.
    document.getElementById('secaoEventos').hidden = true;
    document.getElementById('btnArquivarTarefa').hidden = true;
    cancelarEdicaoEvento();

    abrirModal(document.getElementById('dialogTarefa'));
}

async function abrirModalEdicaoTarefa(idTarefa) {
    try {
        const tarefa = await Api.get(`api/tarefas.php?id=${idTarefa}`);

        Estado.tarefaEmEdicaoId = tarefa.ID_TAREFA;
        Estado.tarefaEmEdicaoArquivada = !!parseInt(tarefa.ARQUIVADO, 10);
        Estado.souCriadorDaTarefaAberta = Estado.usuarioLogado.id === parseInt(tarefa.ID_USUARIO_CADASTRO, 10);
        Estado.anexosParaEnviar = [];

        document.getElementById('tituloModalTarefa').firstChild.textContent = 'Editar Tarefa ';
        document.getElementById('etiquetaArquivada').hidden = !Estado.tarefaEmEdicaoArquivada;
        document.getElementById('tarefaId').value = tarefa.ID_TAREFA;
        document.getElementById('tarefaDescricao').value = tarefa.DESCRICAO;
        document.getElementById('tarefaObservacao').value = tarefa.OBSERVACAO || '';
        popularSelectTags(document.getElementById('tarefaTag'), tarefa.ID_TAG);
        popularChecklistUsuarios((tarefa.USUARIOS || []).map((u) => u.id));
        aplicarPermissaoDeEdicao(Estado.souCriadorDaTarefaAberta);

        // Anexos: só visualização na edição - pra adicionar novo, é via Eventos.
        // O botão de excluir cada anexo também só aparece pra quem criou a tarefa.
        document.getElementById('secaoAnexos').hidden = false;
        document.getElementById('areaNovoAnexoCriacao').hidden = true;
        renderizarAnexos(tarefa.ANEXOS || []);

        renderizarLinhaDoTempo(tarefa.EVENTOS || [], tarefa.HISTORICO || []);
        cancelarEdicaoEvento();
        document.getElementById('secaoEventos').hidden = false;

        // Mover entre colunas e arquivar continuam liberados pra qualquer
        // vinculado - não são considerados "editar conteúdo".
        const btnArquivar = document.getElementById('btnArquivarTarefa');
        btnArquivar.hidden = false;
        btnArquivar.textContent = Estado.tarefaEmEdicaoArquivada ? 'Restaurar' : 'Arquivar';
        btnArquivar.classList.toggle('botao-perigo', !Estado.tarefaEmEdicaoArquivada);
        btnArquivar.classList.toggle('botao-secundario', Estado.tarefaEmEdicaoArquivada);

        abrirModal(document.getElementById('dialogTarefa'));
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

/**
 * Habilita/desabilita os campos de conteúdo da tarefa (TAG, descrição,
 * observação, usuários vinculados) e o botão de salvar, conforme a
 * regra: só quem criou a tarefa edita esses dados. Mover entre colunas
 * (drag-and-drop) e arquivar continuam liberados a qualquer vinculado,
 * então não são afetados aqui.
 */
function aplicarPermissaoDeEdicao(podeEditar) {
    document.getElementById('avisoSomenteLeitura').hidden = podeEditar;
    document.getElementById('tarefaTag').disabled = !podeEditar;
    document.getElementById('tarefaDescricao').disabled = !podeEditar;
    document.getElementById('tarefaObservacao').disabled = !podeEditar;
    document.querySelectorAll('#listaUsuariosVinculo input[type="checkbox"]').forEach((cb) => {
        cb.disabled = !podeEditar;
    });
    document.querySelector('#formTarefa button[type="submit"]').hidden = !podeEditar;
}

/** Arquiva ou restaura a tarefa que está sendo editada no momento. */
async function alternarArquivamentoTarefa() {
    if (!Estado.tarefaEmEdicaoId) return;

    const vaiArquivar = !Estado.tarefaEmEdicaoArquivada;
    const mensagem = vaiArquivar
        ? 'Arquivar esta tarefa? Ela sai do quadro, mas continua nos relatórios e pode ser restaurada depois.'
        : 'Restaurar esta tarefa para o quadro?';

    if (!confirm(mensagem)) return;

    try {
        await Api.put(`api/tarefas.php?acao=arquivar`, { id: Estado.tarefaEmEdicaoId, arquivado: vaiArquivar });
        Toast.sucesso(vaiArquivar ? 'Tarefa arquivada.' : 'Tarefa restaurada.');
        document.getElementById('dialogTarefa').close();
        await carregarTagsETarefas();
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

function popularChecklistUsuarios(idsMarcados) {
    const container = document.getElementById('listaUsuariosVinculo');

    if (Estado.usuarios.length === 0) {
        container.innerHTML = '<p class="texto-ajuda">Nenhum usuário cadastrado ainda.</p>';
        return;
    }

    container.innerHTML = Estado.usuarios.map((u) => `
        <label class="item-checkbox">
            <input type="checkbox" value="${u.ID_USUARIO}" ${idsMarcados.includes(u.ID_USUARIO) ? 'checked' : ''}>
            ${escaparHtml(u.NOME)}
        </label>
    `).join('');
}

function renderizarAnexos(anexos) {
    const container = document.getElementById('listaAnexos');

    if (anexos.length === 0) {
        container.innerHTML = '<p class="texto-ajuda">Nenhum anexo ainda.</p>';
        return;
    }

    // Excluir anexo é restrito a quem criou a tarefa - os demais vinculados
    // só visualizam.
    container.innerHTML = anexos.map((a) => `
        <div class="item-anexo">
            ${ICONE_ANEXO}
            <a href="${encodeURI(a.ANEXO)}" target="_blank" rel="noopener">${escaparHtml(a.DESCRICAO)}</a>
            ${Estado.souCriadorDaTarefaAberta ? `<button type="button" data-id="${a.ID_TAREFA_ANEXO}" title="Remover anexo">✕</button>` : ''}
        </div>
    `).join('');

    container.querySelectorAll('button[data-id]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            try {
                await Api.excluir(`api/anexos.php?id=${btn.dataset.id}`);
                Toast.sucesso('Anexo removido.');
                const tarefa = await Api.get(`api/tarefas.php?id=${Estado.tarefaEmEdicaoId}`);
                renderizarAnexos(tarefa.ANEXOS || []);
                await carregarTagsETarefas();
            } catch (erro) {
                Toast.erro(erro.message);
            }
        });
    });
}

/**
 * Renderiza a lista de arquivos SELECIONADOS mas ainda não enviados
 * (só existe no modo "Cadastrar Tarefa" - o upload de verdade só
 * acontece depois que a tarefa é salva e ganha um ID).
 */
function renderizarAnexosParaEnviar() {
    const container = document.getElementById('listaAnexos');

    if (Estado.anexosParaEnviar.length === 0) {
        container.innerHTML = '<p class="texto-ajuda">Nenhum arquivo selecionado ainda.</p>';
        return;
    }

    container.innerHTML = Estado.anexosParaEnviar.map((arquivo, indice) => `
        <div class="item-anexo">
            ${ICONE_ANEXO}
            <span style="flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escaparHtml(arquivo.name)}</span>
            <button type="button" data-remover-pendente="${indice}" title="Remover da lista">✕</button>
        </div>
    `).join('');

    container.querySelectorAll('[data-remover-pendente]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const indice = parseInt(btn.dataset.removerPendente, 10);
            Estado.anexosParaEnviar.splice(indice, 1);
            renderizarAnexosParaEnviar();
        });
    });
}

/** Envia, um por um, os arquivos selecionados durante a criação, já vinculados à tarefa recém-criada. */
async function enviarAnexosPendentes(idTarefa) {
    if (Estado.anexosParaEnviar.length === 0) return;

    let falhas = 0;
    for (const arquivo of Estado.anexosParaEnviar) {
        try {
            const formData = new FormData();
            formData.append('arquivo', arquivo);
            formData.append('id_tarefa', idTarefa);
            await Api.upload('api/anexos.php', formData);
        } catch (erro) {
            falhas++;
        }
    }

    if (falhas > 0) {
        Toast.erro(`${falhas} de ${Estado.anexosParaEnviar.length} anexo(s) não puderam ser enviados. Abra a tarefa e tente novamente pelos Eventos.`);
    }

    Estado.anexosParaEnviar = [];
}

/**
 * Mescla os comentários do usuário (eventos) com o histórico
 * automático do sistema (criação, movimentação, arquivamento) numa
 * única linha do tempo ordenada por data, igual ao feed de atividade
 * do Trello. Entradas de histórico não têm botão de editar/excluir -
 * são um registro de auditoria, não um comentário do usuário.
 */
function renderizarLinhaDoTempo(eventos, historico) {
    const container = document.getElementById('listaEventos');

    const itens = [
        ...eventos.map((e) => ({ tipo: 'evento', dado: e })),
        ...historico.map((h) => ({ tipo: 'historico', dado: h })),
    ].sort((a, b) => new Date(b.dado.DATA_CADASTRO.replace(' ', 'T')) - new Date(a.dado.DATA_CADASTRO.replace(' ', 'T')));

    if (itens.length === 0) {
        container.innerHTML = '<p class="texto-ajuda">Nenhuma atividade registrada ainda.</p>';
        return;
    }

    container.innerHTML = itens.map((item) => {
        if (item.tipo === 'historico') {
            const h = item.dado;
            return `
                <div class="item-evento item-historico">
                    <div class="evento-cabecalho">
                        <span class="evento-data">${escaparHtml(h.NOME_USUARIO || 'Sistema')} · ${formatarData(h.DATA_CADASTRO)}</span>
                    </div>
                    <div class="evento-descricao">${escaparHtml(h.DESCRICAO)}</div>
                </div>
            `;
        }

        const e = item.dado;
        const souAutor = Estado.usuarioLogado && parseInt(e.ID_USUARIO, 10) === Estado.usuarioLogado.id;
        const botoesEdicao = souAutor
            ? `<button type="button" class="botao-editar-evento" data-editar-evento="${e.ID_TAREFA_EVENTOS}">Editar</button>
               <button type="button" class="botao-editar-evento" data-excluir-evento="${e.ID_TAREFA_EVENTOS}">Excluir</button>`
            : '';
        return `
            <div class="item-evento">
                <div class="evento-cabecalho">
                    <span class="evento-data">${escaparHtml(e.NOME_USUARIO || 'Você')} · ${formatarData(e.DATA_CADASTRO)}</span>
                    ${botoesEdicao}
                </div>
                <div class="evento-descricao">${escaparHtml(e.DESCRICAO)}</div>
                ${e.OBSERVACAO ? `<div class="evento-observacao">${escaparHtml(e.OBSERVACAO)}</div>` : ''}
                ${renderizarAnexosDoEvento(e.ANEXOS || [])}
            </div>
        `;
    }).join('');

    container.querySelectorAll('[data-editar-evento]').forEach((btn) => {
        const evento = eventos.find((e) => String(e.ID_TAREFA_EVENTOS) === btn.dataset.editarEvento);
        if (evento) btn.addEventListener('click', () => iniciarEdicaoEvento(evento));
    });

    container.querySelectorAll('[data-excluir-evento]').forEach((btn) => {
        btn.addEventListener('click', () => excluirEvento(btn.dataset.excluirEvento));
    });
}

/** Monta o mini-bloco de anexos dentro de um card de evento (sem exclusão: eventos são histórico). */
function renderizarAnexosDoEvento(anexos) {
    if (anexos.length === 0) return '';
    const itens = anexos.map((a) => `
        <div class="item-anexo" style="background:transparent;padding:5px 0;">
            ${ICONE_ANEXO}
            <a href="${encodeURI(a.ANEXO)}" target="_blank" rel="noopener">${escaparHtml(a.DESCRICAO)}</a>
        </div>
    `).join('');
    return `<div style="margin-top:8px;">${itens}</div>`;
}

/** Preenche o formulário de evento com os dados existentes e entra em modo de edição. */
function iniciarEdicaoEvento(evento) {
    Estado.eventoEmEdicaoId = evento.ID_TAREFA_EVENTOS;
    document.getElementById('eventoEmEdicaoId').value = evento.ID_TAREFA_EVENTOS;
    document.getElementById('eventoDescricao').value = evento.DESCRICAO;
    document.getElementById('eventoObservacao').value = evento.OBSERVACAO || '';
    document.getElementById('btnAdicionarEvento').textContent = 'Salvar edição';
    document.getElementById('btnCancelarEdicaoEvento').hidden = false;
    document.getElementById('eventoDescricao').focus();
}

/** Sai do modo de edição de evento e limpa o formulário (usado após salvar, excluir, ou clicar em "Cancelar edição"). */
function cancelarEdicaoEvento() {
    Estado.eventoEmEdicaoId = null;
    document.getElementById('eventoEmEdicaoId').value = '';
    document.getElementById('eventoDescricao').value = '';
    document.getElementById('eventoObservacao').value = '';
    document.getElementById('inputAnexoEvento').value = '';
    document.getElementById('btnAdicionarEvento').textContent = 'Adicionar evento';
    document.getElementById('btnCancelarEdicaoEvento').hidden = true;
}

async function excluirEvento(id) {
    if (!confirm('Excluir este evento? Essa ação não pode ser desfeita.')) return;

    try {
        await Api.excluir(`api/eventos.php?id=${id}`);
        Toast.sucesso('Evento excluído.');
        if (Estado.eventoEmEdicaoId === parseInt(id, 10)) cancelarEdicaoEvento();
        const tarefa = await Api.get(`api/tarefas.php?id=${Estado.tarefaEmEdicaoId}`);
        renderizarLinhaDoTempo(tarefa.EVENTOS || [], tarefa.HISTORICO || []);
        await carregarTagsETarefas();
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

/** Cria um novo evento ou salva a edição de um existente, dependendo de Estado.eventoEmEdicaoId. */
async function salvarEventoTarefa() {
    const campoDescricao = document.getElementById('eventoDescricao');
    const campoObservacao = document.getElementById('eventoObservacao');
    const campoAnexo = document.getElementById('inputAnexoEvento');
    const descricao = campoDescricao.value.trim();
    const observacao = campoObservacao.value.trim();
    const arquivos = campoAnexo.files ? Array.from(campoAnexo.files) : [];

    if (!Estado.tarefaEmEdicaoId) {
        Toast.erro('Salve a tarefa antes de adicionar eventos.');
        return;
    }
    if (!descricao) {
        Toast.erro('Informe a descrição do evento.');
        return;
    }

    try {
        let idEvento;

        if (Estado.eventoEmEdicaoId) {
            await Api.put('api/eventos.php', { id: Estado.eventoEmEdicaoId, descricao, observacao });
            idEvento = Estado.eventoEmEdicaoId;
            Toast.sucesso('Evento atualizado.');
        } else {
            const resultado = await Api.post('api/eventos.php', { id_tarefa: Estado.tarefaEmEdicaoId, descricao, observacao });
            idEvento = resultado.id;
            Toast.sucesso('Evento adicionado.');
        }

        // Sobe, um por um, todos os arquivos selecionados, já vinculados ao evento (novo ou editado).
        if (arquivos.length > 0) {
            let falhas = 0;
            for (const arquivo of arquivos) {
                try {
                    const formData = new FormData();
                    formData.append('arquivo', arquivo);
                    formData.append('id_evento', idEvento);
                    await Api.upload('api/eventos_anexo.php', formData);
                } catch (erroAnexo) {
                    falhas++;
                }
            }
            if (falhas > 0) {
                Toast.erro(`${falhas} de ${arquivos.length} anexo(s) do evento não puderam ser enviados.`);
            }
        }

        cancelarEdicaoEvento();
        const tarefa = await Api.get(`api/tarefas.php?id=${Estado.tarefaEmEdicaoId}`);
        renderizarLinhaDoTempo(tarefa.EVENTOS || [], tarefa.HISTORICO || []);
        await carregarTagsETarefas();
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

/* ============================================================
   MODAL: RELATÓRIO
   ============================================================ */

let paginaAtualRelatorio = 1;

function configurarModalRelatorio() {
    document.getElementById('btnRelatorio').addEventListener('click', abrirModalRelatorio);
    document.getElementById('btnFiltrarRelatorio').addEventListener('click', () => filtrarRelatorio(1));
    document.getElementById('filtroBusca').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') filtrarRelatorio(1);
    });
    document.getElementById('filtroPorPagina').addEventListener('change', () => filtrarRelatorio(1));
    document.getElementById('btnPaginaAnterior').addEventListener('click', () => {
        if (paginaAtualRelatorio > 1) filtrarRelatorio(paginaAtualRelatorio - 1);
    });
    document.getElementById('btnProximaPagina').addEventListener('click', () => {
        filtrarRelatorio(paginaAtualRelatorio + 1);
    });
}

function abrirModalRelatorio() {
    popularSelectTags(document.getElementById('filtroTag'), null, true);

    const selectUsuario = document.getElementById('filtroUsuario');
    selectUsuario.innerHTML = '<option value="">Todos</option>' +
        Estado.usuarios.map((u) => `<option value="${u.ID_USUARIO}">${escaparHtml(u.NOME)}</option>`).join('');

    document.getElementById('filtroBusca').value = '';
    document.getElementById('filtroDataInicio').value = '';
    document.getElementById('filtroDataFim').value = '';
    document.getElementById('filtroPorPagina').value = '20';

    abrirModal(document.getElementById('dialogRelatorio'));
    filtrarRelatorio(1);
}

async function filtrarRelatorio(pagina) {
    paginaAtualRelatorio = pagina || 1;

    const params = new URLSearchParams();
    const busca = document.getElementById('filtroBusca').value.trim();
    const status = document.getElementById('filtroStatus').value;
    const idTag = document.getElementById('filtroTag').value;
    const idUsuario = document.getElementById('filtroUsuario').value;
    const dataInicio = document.getElementById('filtroDataInicio').value;
    const dataFim = document.getElementById('filtroDataFim').value;
    const porPagina = document.getElementById('filtroPorPagina').value;

    if (busca) params.set('busca', busca);
    if (status) params.set('status', status);
    if (idTag) params.set('id_tag', idTag);
    if (idUsuario) params.set('id_usuario', idUsuario);
    if (dataInicio) params.set('data_inicio', dataInicio);
    if (dataFim) params.set('data_fim', dataFim);
    params.set('por_pagina', porPagina);
    params.set('pagina', paginaAtualRelatorio);

    try {
        const resultado = await Api.get(`api/relatorio.php?${params.toString()}`);
        renderizarTabelaRelatorio(resultado.tarefas);
        atualizarPaginacaoRelatorio(resultado);
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

/** Atualiza o texto "Página X de Y" e habilita/desabilita os botões Anterior/Próxima. */
function atualizarPaginacaoRelatorio(resultado) {
    paginaAtualRelatorio = resultado.pagina;

    const info = document.getElementById('infoPaginacao');
    info.textContent = resultado.total === 0
        ? 'Nenhum resultado'
        : `Página ${resultado.pagina} de ${resultado.total_paginas} · ${resultado.total} no total`;

    document.getElementById('btnPaginaAnterior').disabled = resultado.pagina <= 1;
    document.getElementById('btnProximaPagina').disabled = resultado.pagina >= resultado.total_paginas;
}

function renderizarTabelaRelatorio(tarefas) {
    const corpo = document.getElementById('corpoTabelaRelatorio');

    if (tarefas.length === 0) {
        corpo.innerHTML = '<tr><td colspan="7" class="estado-vazio">Nenhuma tarefa encontrada com esses filtros.</td></tr>';
        return;
    }

    corpo.innerHTML = tarefas.map((t) => {
        const arquivada = !!parseInt(t.ARQUIVADO, 10);
        const statusHtml = arquivada
            ? `<span class="etiqueta-arquivada" style="margin-left:0;">Arquivada</span>`
            : `<span class="etiqueta-tag">Ativa</span>`;
        return `
            <tr>
                <td>${escaparHtml(t.DESCRICAO)}</td>
                <td>${statusHtml}</td>
                <td><span class="etiqueta-tag">${escaparHtml(t.NOME_TAG)}</span></td>
                <td>${(t.USUARIOS || []).map((n) => escaparHtml(n)).join(', ') || '—'}</td>
                <td>${escaparHtml(t.NOME_USUARIO_CADASTRO || '—')}</td>
                <td>${formatarData(t.DATA_CADASTRO)}</td>
                <td><button type="button" class="botao botao-secundario" data-abrir-relatorio="${t.ID_TAREFA}" style="padding:6px 14px; font-size:0.82rem; white-space:nowrap;">Ver</button></td>
            </tr>
        `;
    }).join('');

    corpo.querySelectorAll('[data-abrir-relatorio]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('dialogRelatorio').close();
            abrirModalEdicaoTarefa(parseInt(btn.dataset.abrirRelatorio, 10));
        });
    });
}

/* ============================================================
   MODAL: TAREFAS ARQUIVADAS
   ============================================================ */

function configurarModalArquivadas() {
    document.getElementById('btnArquivadas').addEventListener('click', abrirModalArquivadas);
}

async function abrirModalArquivadas() {
    abrirModal(document.getElementById('dialogArquivadas'));
    await carregarListaArquivadas();
}

async function carregarListaArquivadas() {
    const container = document.getElementById('listaArquivadas');
    try {
        const arquivadas = await Api.get('api/tarefas.php?arquivadas=1');
        renderizarListaArquivadas(arquivadas);
    } catch (erro) {
        Toast.erro(erro.message);
        container.innerHTML = '<p class="texto-ajuda">Não foi possível carregar as tarefas arquivadas.</p>';
    }
}

function renderizarListaArquivadas(arquivadas) {
    const container = document.getElementById('listaArquivadas');

    if (arquivadas.length === 0) {
        container.innerHTML = '<p class="texto-ajuda">Nenhuma tarefa arquivada no momento.</p>';
        return;
    }

    container.innerHTML = arquivadas.map((t) => `
        <div class="item-arquivada">
            <div class="info-arquivada">
                <div class="descricao-arquivada">${escaparHtml(t.DESCRICAO)}</div>
                <div class="meta-arquivada">${escaparHtml(t.NOME_TAG)} · arquivada em ${formatarData(t.DATA_ARQUIVAMENTO)}</div>
            </div>
            <button type="button" class="botao botao-secundario" data-abrir="${t.ID_TAREFA}">Ver</button>
            <button type="button" class="botao botao-primario" data-restaurar="${t.ID_TAREFA}">Restaurar</button>
        </div>
    `).join('');

    container.querySelectorAll('[data-abrir]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('dialogArquivadas').close();
            abrirModalEdicaoTarefa(parseInt(btn.dataset.abrir, 10));
        });
    });

    container.querySelectorAll('[data-restaurar]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            try {
                await Api.put('api/tarefas.php?acao=arquivar', { id: parseInt(btn.dataset.restaurar, 10), arquivado: false });
                Toast.sucesso('Tarefa restaurada para o quadro.');
                await carregarListaArquivadas();
                await carregarTagsETarefas();
            } catch (erro) {
                Toast.erro(erro.message);
            }
        });
    });
}

/* ============================================================
   UTILITÁRIO LOCAL
   ============================================================ */

/**
 * Escapa HTML manualmente (nunca via innerHTML/textContent round-trip,
 * que NÃO escapa aspas). Sem escapar aspas, um nome de usuário como
 * `x" onmouseover="alert(1)` quebraria para fora de atributos como
 * title="${...}" e injetaria HTML/JS arbitrário (XSS armazenado).
 */
function escaparHtml(texto) {
    if (texto === null || texto === undefined) return '';
    return String(texto)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
