<?php
require_once __DIR__ . '/../includes/funcoes.php';

if (!usuarioLogado()) {
    respostaJson(['logado' => false], 401);
}

respostaJson([
    'logado' => true,
    'usuario' => [
        'id' => idUsuarioLogado(),
        'nome' => $_SESSION['usuario_nome'],
    ],
    'empresa' => $_SESSION['empresa_nome'] ?? null,
]);
