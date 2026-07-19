<?php
/**
 * Logout Simplificado
 */

session_start();

// Destroi tudo
$_SESSION = [];
session_destroy();

// Redireciona para login
header('Location: login.php');
exit;
