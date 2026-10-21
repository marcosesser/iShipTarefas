(function () {
    const formulario = document.getElementById('formLogin');
    const mensagemErro = document.getElementById('mensagemErro');
    const botaoEntrar = document.getElementById('botaoEntrar');
    const campoCodigo = document.getElementById('codigoEmpresa');
    const campoLogin = document.getElementById('login');

    const CHAVE_CODIGO_SALVO = 'gertarefas_codigo_empresa';

    // Se já existir uma sessão válida, pula direto para a tela inicial.
    Api.get('api/sessao.php')
        .then(() => { window.location.href = 'home.php'; })
        .catch(() => { /* sem sessão ativa, permanece no login */ });

    // Pré-preenche o código: primeiro tenta pela URL (?empresa=CODIGO,
    // útil pra empresa distribuir um link já pronto pro time todo),
    // senão usa o último código lembrado neste navegador.
    preencherCodigoEmpresa();

    // Converte pra maiúsculo enquanto digita - o campo já é visualmente
    // maiúsculo (CSS), isso garante que o valor de verdade também seja.
    campoCodigo.addEventListener('input', () => {
        const posicao = campoCodigo.selectionStart;
        campoCodigo.value = campoCodigo.value.toUpperCase();
        campoCodigo.setSelectionRange(posicao, posicao);
    });

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        esconderErro();

        const codigoEmpresa = campoCodigo.value.trim().toUpperCase();
        const login = campoLogin.value.trim();
        const senha = document.getElementById('senha').value;

        if (!codigoEmpresa || !login || !senha) {
            mostrarErro('Preencha o código da empresa, e-mail e senha.');
            return;
        }

        botaoEntrar.disabled = true;
        botaoEntrar.textContent = 'Entrando...';

        try {
            await Api.post('api/login.php', { codigo_empresa: codigoEmpresa, login, senha });
            salvarCodigoLembrado(codigoEmpresa);
            window.location.href = 'home.php';
        } catch (erro) {
            mostrarErro(erro.message);
            botaoEntrar.disabled = false;
            botaoEntrar.textContent = 'Entrar';
        }
    });

    /** Pré-preenche o código da empresa a partir da URL ou do que foi lembrado da última vez. */
    function preencherCodigoEmpresa() {
        const parametros = new URLSearchParams(window.location.search);
        const codigoDaUrl = (parametros.get('empresa') || '').trim().toUpperCase();

        const codigo = codigoDaUrl || lerCodigoLembrado();

        if (codigo) {
            campoCodigo.value = codigo;
            // Já preenchido - manda o foco pro próximo campo, em vez de
            // deixar o cursor parado num campo que a pessoa não precisa mexer.
            campoLogin.focus();
        }

        // Limpa o "?empresa=..." da barra de endereço depois de usar - só
        // organização visual (o código não é informação sensível, mas não
        // precisa ficar poluindo a URL depois de já ter servido pra isso).
        if (codigoDaUrl) {
            window.history.replaceState({}, '', window.location.pathname);
        }
    }

    /** localStorage pode estar indisponível (modo privado, navegador antigo) - nunca deixa isso quebrar o login. */
    function lerCodigoLembrado() {
        try {
            return (localStorage.getItem(CHAVE_CODIGO_SALVO) || '').toUpperCase();
        } catch (e) {
            return '';
        }
    }

    function salvarCodigoLembrado(codigo) {
        try {
            localStorage.setItem(CHAVE_CODIGO_SALVO, codigo);
        } catch (e) {
            // sem localStorage disponível, sem problema - só não lembra da próxima vez
        }
    }

    function mostrarErro(msg) {
        mensagemErro.textContent = msg;
        mensagemErro.classList.add('visivel');
    }

    function esconderErro() {
        mensagemErro.classList.remove('visivel');
    }
})();
