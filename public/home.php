<?php
/**
 * Pagina Inicial - Selecao de Sistema
 */

require_once __DIR__ . '/proteger.php';

$usuario = $GLOBALS['usuario_logado'] ?? null;
$ehAdmin = $usuario['eh_admin'] ?? false;
$nome = $usuario['nome'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COMMANDER - Selecione o Sistema</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #000000;
            min-height: 100vh;
            padding: 40px 20px;
            color: #fff;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 50px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 0, 80, 0.2);
        }
        
        .header h1 {
            font-size: 28px;
            background: linear-gradient(135deg, #ff0050, #00f2ea);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            color: #888;
            font-size: 14px;
        }
        
        .user-info strong {
            color: #fff;
        }
        
        .btn-logout {
            background: rgba(255, 0, 80, 0.1);
            border: 1px solid rgba(255, 0, 80, 0.3);
            color: #ff0050;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-logout:hover {
            background: rgba(255, 0, 80, 0.2);
            box-shadow: 0 0 20px rgba(255, 0, 80, 0.3);
        }
        
        .btn-admin {
            background: rgba(0, 242, 234, 0.1);
            border: 1px solid rgba(0, 242, 234, 0.3);
            color: #00f2ea;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-admin:hover {
            background: rgba(0, 242, 234, 0.2);
            box-shadow: 0 0 20px rgba(0, 242, 234, 0.3);
        }
        
        .welcome {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .welcome h2 {
            font-size: 24px;
            color: #fff;
            margin-bottom: 10px;
        }
        
        .welcome p {
            color: #888;
        }
        
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .card {
            background: #0a0a0a;
            border: 1px solid #1a1a1a;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }
        
        .card-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 25px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card-icon svg {
            width: 40px;
            height: 40px;
        }
        
        .card h3 {
            font-size: 22px;
            color: #fff;
            margin-bottom: 15px;
        }
        
        .card p {
            color: #888;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        .card-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-stable {
            background: rgba(0, 242, 234, 0.1);
            color: #00f2ea;
            border: 1px solid rgba(0, 242, 234, 0.3);
        }
        
        .badge-new {
            background: rgba(255, 0, 80, 0.1);
            color: #ff0050;
            border: 1px solid rgba(255, 0, 80, 0.3);
        }
        
        .card-v1 {
            border-color: rgba(0, 242, 234, 0.2);
        }
        
        .card-v1:hover {
            border-color: #00f2ea;
            box-shadow: 0 20px 40px rgba(0, 242, 234, 0.15);
        }
        
        .card-v1 .card-icon {
            background: linear-gradient(135deg, rgba(0, 242, 234, 0.2), rgba(0, 242, 234, 0.1));
        }
        
        .card-v1 .card-icon svg {
            stroke: #00f2ea;
        }
        
        .card-v2 {
            border-color: rgba(255, 0, 80, 0.2);
        }
        
        .card-v2:hover {
            border-color: #ff0050;
            box-shadow: 0 20px 40px rgba(255, 0, 80, 0.15);
        }
        
        .card-v2 .card-icon {
            background: linear-gradient(135deg, rgba(255, 0, 80, 0.2), rgba(255, 0, 80, 0.1));
        }
        
        .card-v2 .card-icon svg {
            stroke: #ff0050;
        }
        
        .card-v3 {
            border-color: rgba(255, 204, 0, 0.2);
        }
        
        .card-v3:hover {
            border-color: #ffcc00;
            box-shadow: 0 20px 40px rgba(255, 204, 0, 0.15);
        }
        
        .card-v3 .card-icon {
            background: linear-gradient(135deg, rgba(255, 204, 0, 0.2), rgba(255, 204, 0, 0.1));
        }
        
        .card-v3 .card-icon svg {
            stroke: #ffcc00;
        }
        
        .badge-pro {
            background: rgba(255, 204, 0, 0.1);
            color: #ffcc00;
            border: 1px solid rgba(255, 204, 0, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>COMMANDER</h1>
            <div class="header-right">
                <div class="user-info">
                    Ola, <strong><?php echo htmlspecialchars($nome); ?></strong>
                </div>
                <?php if ($ehAdmin): ?>
                    <a href="admin.php" class="btn-admin">Painel Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="btn-logout">Sair</a>
            </div>
        </div>
        
        <div class="welcome">
            <h2>Selecione o Sistema</h2>
            <p>Escolha qual versao do COMMANDER deseja utilizar</p>
        </div>
        
        <div class="cards">
            <!-- COMMANDER V1 -->
            <a href="COMMANDER/" class="card card-v1">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                </div>
                <h3>COMMANDER V1</h3>
                <p>Versao original do sistema. Estavel e testada em producao. Use se ja tem campanhas configuradas.</p>
                <span class="card-badge badge-stable">Estavel</span>
            </a>
            
            <!-- COMMANDER V2 -->
            <a href="COMMANDERV2/" class="card card-v2">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                    </svg>
                </div>
                <h3>COMMANDER V2</h3>
                <p>Nova versao com interface moderna, mais seguranca e recursos avancados de integracao.</p>
                <span class="card-badge badge-new">Nova Versao</span>
            </a>
            
            <!-- COMMANDER V3 -->
            <a href="COMMANDERV3/" class="card card-v3">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                    </svg>
                </div>
                <h3>COMMANDER V3</h3>
                <p>Versao completa com rastreamento de vendas, integracao com gateways (Hotmart, Kiwify, etc) e dashboard de faturamento.</p>
                <span class="card-badge badge-pro">PRO - Vendas</span>
            </a>
        </div>
    </div>
</body>
</html>
