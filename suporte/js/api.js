/**
 * Helper central para conversar com a API PHP - mesma lógica usada no
 * projeto principal, copiada aqui porque os dois projetos não
 * compartilham arquivo nenhum entre si (são deploys independentes).
 */
const Api = {
    async chamar(url, opcoes = {}) {
        const resposta = await fetch(url, {
            credentials: 'include',
            headers: opcoes.semJson ? undefined : { 'Content-Type': 'application/json', ...(opcoes.headers || {}) },
            ...opcoes,
        });

        let dados = null;
        try {
            dados = await resposta.json();
        } catch (e) {
            dados = null;
        }

        if (!resposta.ok) {
            const mensagem = (dados && dados.erro) ? dados.erro : `Erro na requisição (${resposta.status})`;
            throw new Error(mensagem);
        }

        return dados;
    },

    get(url) {
        return this.chamar(url);
    },

    post(url, corpo) {
        return this.chamar(url, { method: 'POST', body: JSON.stringify(corpo) });
    },

    put(url, corpo) {
        return this.chamar(url, { method: 'PUT', body: JSON.stringify(corpo) });
    },
};

/** Sistema simples de notificações (toasts). */
const Toast = {
    container: null,

    garantirContainer() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
        return this.container;
    },

    mostrar(mensagem, tipo = 'info') {
        const container = this.garantirContainer();
        const toast = document.createElement('div');
        toast.className = `toast ${tipo}`;
        toast.textContent = mensagem;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 4200);
    },

    sucesso(msg) { this.mostrar(msg, 'sucesso'); },
    erro(msg) { this.mostrar(msg, 'erro'); },
};

/** Escapa HTML manualmente (nunca via innerHTML/textContent round-trip, que não escapa aspas). */
function escaparHtml(texto) {
    if (texto === null || texto === undefined) return '';
    return String(texto)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/** Gera as iniciais (até 2 letras) a partir de um nome completo. */
function iniciais(nome) {
    if (!nome) return '?';
    const partes = nome.trim().split(/\s+/);
    const primeira = partes[0]?.[0] || '';
    const ultima = partes.length > 1 ? partes[partes.length - 1][0] : '';
    return (primeira + ultima).toUpperCase();
}

/** Formata um TIMESTAMP do Firebird em dd/mm/aaaa hh:mm. */
function formatarData(valor) {
    if (!valor) return '';
    const data = new Date(valor.replace(' ', 'T'));
    if (isNaN(data.getTime())) return valor;
    const dd = String(data.getDate()).padStart(2, '0');
    const mm = String(data.getMonth() + 1).padStart(2, '0');
    const aaaa = data.getFullYear();
    const hh = String(data.getHours()).padStart(2, '0');
    const min = String(data.getMinutes()).padStart(2, '0');
    return `${dd}/${mm}/${aaaa} ${hh}:${min}`;
}
