<?php
/**
 * COMMANDER - Sistema de autenticacao STANDALONE
 *
 * Substitui a dependencia do antigo "proteger.php" do site-mae.
 * Totalmente autossuficiente: nao depende de config.php/functions.php
 * (por isso pode ser carregado bem no inicio do index.php).
 *
 * Guarda os usuarios em data/users.json. No primeiro acesso, a tela de
 * login pede para criar a conta de administrador.
 */

if (defined('COMMANDER_AUTH_LOADED')) {
    return;
}
define('COMMANDER_AUTH_LOADED', true);

// ------------------------------------------------------------------
// Sessao (cookie seguro)
// ------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);
    session_name('COMMANDER_SESS');
    session_start();
}

// ------------------------------------------------------------------
// Caminhos
// ------------------------------------------------------------------
if (!defined('AUTH_DATA_DIR')) {
    define('AUTH_DATA_DIR', __DIR__ . '/data/');
}
if (!defined('AUTH_USERS_FILE')) {
    define('AUTH_USERS_FILE', AUTH_DATA_DIR . 'users.json');
}

function authEnsureDataDir() {
    if (!is_dir(AUTH_DATA_DIR)) {
        @mkdir(AUTH_DATA_DIR, 0755, true);
    }
}

// ------------------------------------------------------------------
// Persistencia de usuarios
// ------------------------------------------------------------------
function authLoadUsers() {
    if (!is_file(AUTH_USERS_FILE)) return [];
    $data = json_decode((string) @file_get_contents(AUTH_USERS_FILE), true);
    return is_array($data) ? $data : [];
}

function authSaveUsers($users) {
    authEnsureDataDir();
    return @file_put_contents(
        AUTH_USERS_FILE,
        json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

function authNormalizeUsername($u) {
    return strtolower(trim((string) $u));
}

function authFindUser($username) {
    $username = authNormalizeUsername($username);
    foreach (authLoadUsers() as $u) {
        if (authNormalizeUsername($u['username'] ?? '') === $username) {
            return $u;
        }
    }
    return null;
}

/**
 * Cria um novo usuario. Retorna [ok(bool), erro(string)].
 */
function authCreateUser($username, $password, $isAdmin = false, $nome = '', $email = '') {
    $username = authNormalizeUsername($username);
    if ($username === '' || strlen($username) < 3) {
        return [false, 'O usuario precisa ter ao menos 3 caracteres.'];
    }
    if (strlen((string) $password) < 6) {
        return [false, 'A senha precisa ter ao menos 6 caracteres.'];
    }
    if (authFindUser($username)) {
        return [false, 'Ja existe um usuario com esse nome.'];
    }
    $users = authLoadUsers();
    $users[] = [
        'id'                => uniqid('usr_', true),
        'username'          => $username,
        'nome'              => $nome !== '' ? $nome : $username,
        'email'             => $email !== '' ? $email : ($username . '@commander.local'),
        'password_hash'     => password_hash($password, PASSWORD_DEFAULT),
        'is_admin'          => (bool) $isAdmin,
        'utm_cloaker_ativo' => true,
        'created_at'        => date('c'),
    ];
    authSaveUsers($users);
    return [true, ''];
}

/**
 * Valida credenciais. Retorna o usuario (array) ou null.
 */
function authVerify($username, $password) {
    $user = authFindUser($username);
    if (!$user) return null;
    if (!password_verify((string) $password, $user['password_hash'] ?? '')) return null;
    return $user;
}

// ------------------------------------------------------------------
// Sessao do usuario
// ------------------------------------------------------------------
function authLogin($user) {
    $_SESSION['commander_user'] = [
        'id'                => $user['id'] ?? '',
        'username'          => $user['username'] ?? '',
        'nome'              => $user['nome'] ?? $user['username'] ?? '',
        'email'             => $user['email'] ?? '',
        'is_admin'          => (bool) ($user['is_admin'] ?? false),
        'utm_cloaker_ativo' => (bool) ($user['utm_cloaker_ativo'] ?? true),
        'login_at'          => time(),
    ];
    session_regenerate_id(true);
}

function authLogout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function authCurrentUser() {
    return $_SESSION['commander_user'] ?? null;
}

function authIsLoggedIn() {
    return authCurrentUser() !== null;
}

function authHasUsers() {
    return count(authLoadUsers()) > 0;
}
