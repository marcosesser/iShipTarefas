/**
 * Lógica do painel de suporte: carrega a sessão, lista as empresas
 * cadastradas e permite criar uma empresa nova ou ativar/inativar uma
 * existente.
 */

const Estado = { usuarioLogado: null, empresas: [] };

document.addEventListener('DOMContentLoaded', iniciar);

async function iniciar() {
    try {
        const sessao = await Api.get('api/sessao.php');
        Estado.usuarioLogado = sessao.usuario;
    } catch (erro) {
        window.location.href = 'index.html';
        return;
    }

    document.getElementById('nomeSuporte').textContent = Estado.usuarioLogado.nome;
    document.getElementById('avatarSuporte').textContent = iniciais(Estado.usuarioLogado.nome);

    configurarEventos();
    await carregarEmpresas();
}

function configurarEventos() {
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

    document.getElementById('btnCopiarLinkGerado').addEventListener('click', () => {
        copiarTexto(document.getElementById('linkGeradoTexto').value);
    });

    document.getElementById('btnNovaEmpresa').addEventListener('click', () => {
        document.getElementById('formNovaEmpresa').reset();
        document.getElementById('dialogNovaEmpresa').showModal();
    });

    document.getElementById('formNovaEmpresa').addEventListener('submit', async (evento) => {
        evento.preventDefault();

        const nome_empresa = document.getElementById('novaEmpresaNome').value.trim();
        const nome_admin = document.getElementById('novaEmpresaNomeAdmin').value.trim();
        const login_admin = document.getElementById('novaEmpresaLoginAdmin').value.trim();
        const senha_admin = document.getElementById('novaEmpresaSenhaAdmin').value;

        try {
            const resultado = await Api.post('api/empresas.php', { nome_empresa, nome_admin, login_admin, senha_admin });
            document.getElementById('dialogNovaEmpresa').close();
            document.getElementById('codigoGeradoTexto').textContent = resultado.codigo;
            document.getElementById('linkGeradoTexto').value = resultado.link_acesso;
            document.getElementById('dialogCodigoGerado').showModal();
            await carregarEmpresas();
        } catch (erro) {
            Toast.erro(erro.message);
        }
    });
}

async function carregarEmpresas() {
    try {
        Estado.empresas = await Api.get('api/empresas.php');
        renderizarTabelaEmpresas();
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

function renderizarTabelaEmpresas() {
    const corpo = document.getElementById('corpoTabelaEmpresas');

    if (Estado.empresas.length === 0) {
        corpo.innerHTML = '<tr><td colspan="5" class="estado-vazio">Nenhuma empresa cadastrada ainda.</td></tr>';
        return;
    }

    corpo.innerHTML = Estado.empresas.map((e) => {
        const ativa = !!parseInt(e.ATIVA, 10);
        return `
            <tr>
                <td>${escaparHtml(e.NOME)}</td>
                <td class="codigo-empresa">${escaparHtml(e.CODIGO)}</td>
                <td><span class="etiqueta-status ${ativa ? 'ativa' : 'inativa'}">${ativa ? 'Ativa' : 'Inativa'}</span></td>
                <td>${formatarData(e.DATA_CADASTRO)}</td>
                <td style="white-space:nowrap;">
                    <button type="button" class="botao botao-secundario" data-copiar-link="${escaparHtml(e.LINK_ACESSO)}"
                        style="padding:6px 14px; font-size:0.82rem; margin-right:6px;">
                        Copiar link
                    </button>
                    <button type="button" class="botao ${ativa ? 'botao-perigo' : 'botao-secundario'}"
                        data-alternar="${e.ID_EMPRESA}" data-ativa="${ativa ? '1' : '0'}"
                        style="padding:6px 14px; font-size:0.82rem; white-space:nowrap;">
                        ${ativa ? 'Inativar' : 'Ativar'}
                    </button>
                </td>
            </tr>
        `;
    }).join('');

    corpo.querySelectorAll('[data-copiar-link]').forEach((btn) => {
        btn.addEventListener('click', () => copiarTexto(btn.dataset.copiarLink));
    });

    corpo.querySelectorAll('[data-alternar]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const id = parseInt(btn.dataset.alternar, 10);
            const estaAtiva = btn.dataset.ativa === '1';
            const mensagem = estaAtiva
                ? 'Inativar esta empresa? Os usuários dela não vão mais conseguir entrar no sistema.'
                : 'Reativar esta empresa?';

            if (!confirm(mensagem)) return;

            try {
                await Api.put('api/empresas.php', { id, ativa: !estaAtiva });
                Toast.sucesso(estaAtiva ? 'Empresa inativada.' : 'Empresa ativada.');
                await carregarEmpresas();
            } catch (erro) {
                Toast.erro(erro.message);
            }
        });
    });
}

/**
 * Copia um texto pra área de transferência. navigator.clipboard só
 * funciona em HTTPS ou localhost - se não estiver disponível, cai num
 * método antigo (campo temporário + execCommand) pra não deixar de funcionar.
 */
async function copiarTexto(texto) {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(texto);
        } else {
            const temporario = document.createElement('textarea');
            temporario.value = texto;
            temporario.style.position = 'fixed';
            temporario.style.opacity = '0';
            document.body.appendChild(temporario);
            temporario.select();
            document.execCommand('copy');
            temporario.remove();
        }
        Toast.sucesso('Link copiado.');
    } catch (erro) {
        Toast.erro('Não foi possível copiar - selecione e copie o link manualmente.');
    }
}
