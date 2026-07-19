<?php
/**
 * Middleware de Protecao Simplificado
 * Inclua no topo de paginas protegidas: require_once __DIR__ . '/proteger.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se esta logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: /login.php');
    exit;
}

// Verifica timeout da sessao (2 horas)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 7200) {
    session_destroy();
    header('Location: /login.php?erro=sessao_expirada');
    exit;
}

// Disponibiliza dados do usuario logado
$GLOBALS['usuario_logado'] = [
    'email' => $_SESSION['usuario_email'] ?? '',
    'nome' => $_SESSION['usuario_nome'] ?? '',
    'eh_admin' => $_SESSION['eh_admin'] ?? false,
    'utm_cloaker_ativo' => $_SESSION['utm_cloaker_ativo'] ?? false
];
