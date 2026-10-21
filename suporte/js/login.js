(function () {
    const formulario = document.getElementById('formLogin');
    const mensagemErro = document.getElementById('mensagemErro');
    const botaoEntrar = document.getElementById('botaoEntrar');

    Api.get('api/sessao.php')
        .then(() => { window.location.href = 'painel.php'; })
        .catch(() => { /* sem sessão ativa, permanece no login */ });

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        esconderErro();

        const login = document.getElementById('login').value.trim();
        const senha = document.getElementById('senha').value;

        if (!login || !senha) {
            mostrarErro('Preencha e-mail e senha.');
            return;
        }

        botaoEntrar.disabled = true;
        botaoEntrar.textContent = 'Entrando...';

        try {
            await Api.post('api/login.php', { login, senha });
            window.location.href = 'painel.php';
        } catch (erro) {
            mostrarErro(erro.message);
            botaoEntrar.disabled = false;
            botaoEntrar.textContent = 'Entrar';
        }
    });

    function mostrarErro(msg) {
        mensagemErro.textContent = msg;
        mensagemErro.classList.add('visivel');
    }

    function esconderErro() {
        mensagemErro.classList.remove('visivel');
    }
})();
