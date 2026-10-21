<?php
require_once __DIR__ . '/../includes/funcoes.php';

unset($_SESSION['suporte_id'], $_SESSION['suporte_nome']);

respostaJson(['sucesso' => true]);
