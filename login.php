<?php
/**
 * COMMANDER - Tela de login / primeiro acesso (standalone)
 */

require_once __DIR__ . '/auth.php';

// Ja logado -> vai para o painel
if (authIsLoggedIn()) {
    header('Location: /');
    exit;
}

$firstRun = !authHasUsers();
$error = '';
$notice = '';

if (($_GET['erro'] ?? '') === '1') {
    $error = 'Sua sessao expirou ou o acesso e restrito. Faca login novamente.';
}

// -------- Rate limiting simples (por sessao) --------
$now = time();
$attempts = $_SESSION['login_attempts'] ?? 0;
$blockedUntil = $_SESSION['login_blocked_until'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($blockedUntil > $now) {
        $error = 'Muitas tentativas. Aguarde ' . ($blockedUntil - $now) . ' segundos.';
    } elseif ($firstRun) {
        // ---- Criar conta de administrador ----
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';
        if ($password !== $confirm) {
            $error = 'As senhas nao conferem.';
        } else {
            [$ok, $msg] = authCreateUser($username, $password, true, $username);
            if ($ok) {
                $user = authFindUser($username);
                authLogin($user);
                header('Location: /');
                exit;
            }
            $error = $msg;
        }
    } else {
        // ---- Login normal ----
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $user = authVerify($username, $password);
        if ($user) {
            unset($_SESSION['login_attempts'], $_SESSION['login_blocked_until']);
            authLogin($user);
            header('Location: /');
            exit;
        }
        $attempts++;
        $_SESSION['login_attempts'] = $attempts;
        if ($attempts >= 5) {
            $_SESSION['login_blocked_until'] = $now + 900; // 15 min
            $error = 'Muitas tentativas. Aguarde 15 minutos.';
        } else {
            $error = 'Usuario ou senha invalidos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $firstRun ? 'Criar conta' : 'Entrar'; ?> - COMMANDER</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0a0f;
            color: #f4f4f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            width: 100%;
            max-width: 400px;
            background: #14141b;
            border: 1px solid #26263a;
            border-radius: 16px;
            padding: 40px 32px;
        }
        .logo {
            text-align: center;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 1px;
            margin-bottom: 8px;
            color: #a855f7;
        }
        .subtitle {
            text-align: center;
            color: #a1a1aa;
            font-size: 14px;
            margin-bottom: 28px;
            line-height: 1.5;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #d4d4d8;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            background: #0a0a0f;
            border: 1px solid #33334a;
            border-radius: 10px;
            color: #f4f4f5;
            font-size: 15px;
            margin-bottom: 18px;
            outline: none;
        }
        input:focus { border-color: #a855f7; }
        button {
            width: 100%;
            padding: 13px;
            background: #a855f7;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }
        button:hover { background: #9333ea; }
        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 18px;
            line-height: 1.5;
        }
        .alert-error { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.35); color: #fca5a5; }
        .alert-info  { background: rgba(168,85,247,.12); border: 1px solid rgba(168,85,247,.35); color: #d8b4fe; }
        .hint { font-size: 12px; color: #71717a; margin-top: -8px; margin-bottom: 18px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">COMMANDER</div>
        <div class="subtitle">
            <?php echo $firstRun
                ? 'Primeiro acesso: crie sua conta de administrador.'
                : 'Entre com suas credenciais para acessar o painel.'; ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($firstRun && !$error): ?>
            <div class="alert alert-info">Escolha um usuario e uma senha forte. Voce usara isso para entrar no painel.</div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <label for="username">Usuario</label>
            <input type="text" id="username" name="username" required autofocus
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">

            <label for="password">Senha</label>
            <input type="password" id="password" name="password" required>

            <?php if ($firstRun): ?>
                <div class="hint">Minimo de 6 caracteres.</div>
                <label for="confirm">Confirmar senha</label>
                <input type="password" id="confirm" name="confirm" required>
            <?php endif; ?>

            <button type="submit"><?php echo $firstRun ? 'Criar conta e entrar' : 'Entrar'; ?></button>
        </form>
    </div>
</body>
</html>
