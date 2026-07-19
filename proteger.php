<?php
/**
 * COMMANDER - Protecao de acesso (standalone)
 *
 * Este arquivo mantem o MESMO contrato que o antigo proteger.php do site-mae:
 * ao final, deixa disponivel $GLOBALS['usuario_logado'] com os campos
 * email, nome, eh_admin, utm_cloaker_ativo e expira_em.
 *
 * Se nao houver usuario logado, redireciona para a tela de login.
 */

require_once __DIR__ . '/auth.php';

// Sem nenhum usuario cadastrado ainda -> primeiro acesso: ir para o setup.
if (!authHasUsers()) {
    header('Location: /login.php');
    exit;
}

// Precisa estar logado.
if (!authIsLoggedIn()) {
    header('Location: /login.php?erro=1');
    exit;
}

$u = authCurrentUser();

// Monta o formato esperado pelo restante do COMMANDER.
$GLOBALS['usuario_logado'] = [
    'email'             => $u['email'] ?? ($u['username'] ?? 'default'),
    'nome'              => $u['nome'] ?? ($u['username'] ?? ''),
    'eh_admin'          => (bool) ($u['is_admin'] ?? false),
    'utm_cloaker_ativo' => (bool) ($u['utm_cloaker_ativo'] ?? true),
    'expira_em'         => date('c', time() + 86400 * 365),
    // Campos usados pelo front-end (JS)
    'username'          => $u['username'] ?? 'default',
    'is_admin'          => (bool) ($u['is_admin'] ?? false),
];
