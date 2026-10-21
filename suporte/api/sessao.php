<?php
require_once __DIR__ . '/../includes/funcoes.php';

if (!suporteLogado()) {
    respostaJson(['logado' => false], 401);
}

respostaJson([
    'logado' => true,
    'usuario' => [
        'id' => idSuporteLogado(),
        'nome' => $_SESSION['suporte_nome'],
    ],
]);
