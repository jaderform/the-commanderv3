<?php
/**
 * Sistema de Login Simplificado v2.1
 * Compativel com estrutura de dados existente
 */

// Mostra erros para debug (remova em producao)
// error_reporting(E_ALL); ini_set('display_errors', 1);

session_start();

// ============================================
// CONFIGURACOES
// ============================================

define('ARQUIVO_USUARIOS', __DIR__ . '/usuarios.json');
define('ARQUIVO_SESSOES', __DIR__ . '/sessoes_ativas.json');

// ============================================
// FUNCOES
// ============================================

function carregarJSON($arquivo) {
    if (!file_exists($arquivo)) {
        file_put_contents($arquivo, '{}');
        return [];
    }
    $conteudo = @file_get_contents($arquivo);
    if (empty($conteudo)) {
        return [];
    }
    $dados = @json_decode($conteudo, true);
    return is_array($dados) ? $dados : [];
}

function salvarJSON($arquivo, $dados) {
    file_put_contents($arquivo, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function obterIP() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function gerarToken() {
    return bin2hex(random_bytes(32));
}

// ============================================
// VERIFICA SE JA ESTA LOGADO
// ============================================

if (isset($_SESSION['logado']) && $_SESSION['logado'] === true && isset($_SESSION['usuario_email'])) {
    // Ja esta logado, redireciona para pagina de selecao
    header('Location: home.php');
    exit;
}

// ============================================
// PROCESSA LOGIN
// ============================================

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    
    if (empty($email) || empty($senha)) {
        $erro = 'Preencha todos os campos.';
    } else {
        $usuarios = carregarJSON(ARQUIVO_USUARIOS);
        
        // Busca usuario (email eh a chave do JSON)
        $usuarioEncontrado = null;
        $emailEncontrado = null;
        
        foreach ($usuarios as $emailKey => $dados) {
            if (strtolower($emailKey) === strtolower($email)) {
                $usuarioEncontrado = $dados;
                $emailEncontrado = $emailKey;
                break;
            }
        }
        
        if (!$usuarioEncontrado) {
            $erro = 'Email ou senha incorretos.';
        } elseif ($usuarioEncontrado['bloqueado'] ?? false) {
            $erro = 'Esta conta esta bloqueada.';
        } elseif ($senha !== $usuarioEncontrado['senha']) {
            // Senha em texto puro (compatibilidade)
            $erro = 'Email ou senha incorretos.';
        } else {
            // Login OK!
            $_SESSION['logado'] = true;
            $_SESSION['usuario_email'] = $emailEncontrado;
            $_SESSION['usuario_nome'] = $usuarioEncontrado['nome'] ?? $emailEncontrado;
            $_SESSION['eh_admin'] = $usuarioEncontrado['eh_admin'] ?? false;
            $_SESSION['utm_cloaker_ativo'] = $usuarioEncontrado['utm_cloaker_ativo'] ?? false;
            $_SESSION['ip'] = obterIP();
            $_SESSION['login_time'] = time();
            
            // Salva sessao ativa
            $sessoes = carregarJSON(ARQUIVO_SESSOES);
            $token = gerarToken();
            $sessoes[$emailEncontrado] = $token;
            salvarJSON(ARQUIVO_SESSOES, $sessoes);
            
            // Redireciona para pagina de selecao
            header('Location: home.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #000000;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-box {
            background: #0a0a0a;
            border: 1px solid rgba(255, 0, 80, 0.3);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 0 40px rgba(255, 0, 80, 0.1);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            font-size: 26px;
            background: linear-gradient(135deg, #ff0050, #00f2ea);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #fff;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px;
            background: #121212;
            border: 1px solid #252525;
            border-radius: 10px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #ff0050;
            box-shadow: 0 0 0 3px rgba(255, 0, 80, 0.2);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: #ff0050;
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #e6004a;
            box-shadow: 0 0 20px rgba(255, 0, 80, 0.4);
        }
        
        .erro {
            background: rgba(255, 0, 80, 0.1);
            border: 1px solid rgba(255, 0, 80, 0.3);
            color: #ff6b8a;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">
            <h1>COMMANDER</h1>
        </div>
        
        <?php if ($erro): ?>
            <div class="erro"><?php echo htmlspecialchars($erro); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required autofocus placeholder="seu@email.com">
            </div>
            
            <div class="form-group">
                <label>Senha</label>
                <input type="password" name="senha" required placeholder="Sua senha">
            </div>
            
            <button type="submit" class="btn">Entrar</button>
        </form>
    </div>
</body>
</html>
