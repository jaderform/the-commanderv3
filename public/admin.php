<?php
/**
 * Painel Administrativo v2.0
 * 
 * Gerenciamento de:
 * - Usuarios
 * - Dispositivos autorizados
 * - IPs bloqueados
 * - Sessoes ativas
 * - Strikes
 */

require_once __DIR__ . '/proteger.php';

// Verifica se eh admin
$usuario = $GLOBALS['usuario_logado'];
$ehAdmin = $usuario['eh_admin'] ?? false;

// Arquivos de dados
define('ARQ_USUARIOS', __DIR__ . '/usuarios.json');
define('ARQ_SESSOES', __DIR__ . '/sessoes_ativas.json');
define('ARQ_STRIKES', __DIR__ . '/contador_strikes.json');
define('ARQ_DISPOSITIVOS', __DIR__ . '/dispositivos_autorizados.json');
define('ARQ_IPS_BLOQUEADOS', __DIR__ . '/ips_bloqueados.json');

// V2 - Campanhas do COMMANDER V2 (somente leitura)
define('ARQ_CAMPANHAS_V2', __DIR__ . '/COMMANDERV2/data/campaigns.json');

// Avisos do sistema (mensagens do admin para usuarios)
define('ARQ_AVISOS', __DIR__ . '/avisos_sistema.json');

// V3 - Modo Demo/Fake para screenshots
define('ARQ_FAKE_MODE', __DIR__ . '/COMMANDERV3/data/fake_mode.json');

// Funcoes auxiliares
function admin_carregarJSON($arquivo) {
    if (!file_exists($arquivo)) return [];
    return json_decode(file_get_contents($arquivo), true) ?? [];
}

function admin_salvarJSON($arquivo, $dados) {
    file_put_contents($arquivo, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// Processa acoes
$mensagem = '';
$tipoMensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ehAdmin) {
    $acao = $_POST['acao'] ?? '';
    
    switch ($acao) {
        case 'desbloquear_ip':
            $ip = $_POST['ip'] ?? '';
            if ($ip) {
                $bloqueados = admin_carregarJSON(ARQ_IPS_BLOQUEADOS);
                if (isset($bloqueados[$ip])) {
                    unset($bloqueados[$ip]);
                    admin_salvarJSON(ARQ_IPS_BLOQUEADOS, $bloqueados);
                    $mensagem = "IP {$ip} desbloqueado com sucesso.";
                    $tipoMensagem = 'success';
                }
            }
            break;
            
        case 'bloquear_ip':
            $ip = $_POST['ip'] ?? '';
            $motivo = $_POST['motivo'] ?? 'Bloqueio manual';
            $permanente = isset($_POST['permanente']);
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
                $bloqueados = admin_carregarJSON(ARQ_IPS_BLOQUEADOS);
                $bloqueados[$ip] = [
                    'motivo' => $motivo,
                    'bloqueado_em' => time(),
                    'expira_em' => $permanente ? PHP_INT_MAX : time() + 86400,
                    'permanente' => $permanente,
                    'bloqueado_por' => $usuario['email']
                ];
                admin_salvarJSON(ARQ_IPS_BLOQUEADOS, $bloqueados);
                $mensagem = "IP {$ip} bloqueado com sucesso.";
                $tipoMensagem = 'success';
            }
            break;
            
        case 'autorizar_dispositivo':
            $email = $_POST['email'] ?? '';
            $fingerprint = $_POST['fingerprint'] ?? '';
            if ($email && $fingerprint) {
                $dispositivos = admin_carregarJSON(ARQ_DISPOSITIVOS);
                if (isset($dispositivos[$email])) {
                    foreach ($dispositivos[$email] as &$d) {
                        if ($d['fingerprint'] === $fingerprint) {
                            $d['ativo'] = true;
                            break;
                        }
                    }
                    admin_salvarJSON(ARQ_DISPOSITIVOS, $dispositivos);
                    $mensagem = "Dispositivo autorizado com sucesso.";
                    $tipoMensagem = 'success';
                }
            }
            break;
            
        case 'remover_dispositivo':
            $email = $_POST['email'] ?? '';
            $fingerprint = $_POST['fingerprint'] ?? '';
            if ($email && $fingerprint) {
                $dispositivos = admin_carregarJSON(ARQ_DISPOSITIVOS);
                if (isset($dispositivos[$email])) {
                    $dispositivos[$email] = array_filter($dispositivos[$email], function($d) use ($fingerprint) {
                        return $d['fingerprint'] !== $fingerprint;
                    });
                    $dispositivos[$email] = array_values($dispositivos[$email]);
                    admin_salvarJSON(ARQ_DISPOSITIVOS, $dispositivos);
                    $mensagem = "Dispositivo removido com sucesso.";
                    $tipoMensagem = 'success';
                }
            }
            break;
            
        case 'encerrar_sessao':
            $token = $_POST['token'] ?? '';
            if ($token) {
                $sessoes = admin_carregarJSON(ARQ_SESSOES);
                if (isset($sessoes[$token])) {
                    unset($sessoes[$token]);
                    admin_salvarJSON(ARQ_SESSOES, $sessoes);
                    $mensagem = "Sessao encerrada com sucesso.";
                    $tipoMensagem = 'success';
                }
            }
            break;
            
        case 'limpar_strikes':
            $email = $_POST['email'] ?? '';
            if ($email) {
                $strikes = admin_carregarJSON(ARQ_STRIKES);
                if (isset($strikes[$email])) {
                    unset($strikes[$email]);
                    admin_salvarJSON(ARQ_STRIKES, $strikes);
                    $mensagem = "Strikes de {$email} limpos com sucesso.";
                    $tipoMensagem = 'success';
                }
            }
            break;
            
        case 'criar_usuario':
            $novoEmail = filter_var($_POST['novo_email'] ?? '', FILTER_SANITIZE_EMAIL);
            $novoNome = htmlspecialchars($_POST['novo_nome'] ?? '');
            $novaSenha = $_POST['nova_senha'] ?? '';
            $novoAdmin = isset($_POST['novo_admin']);
            $novoCloaker = isset($_POST['novo_cloaker']);
            $novoDias = max(1, min(365, (int)($_POST['novo_dias'] ?? 30)));
            $novoExpira = date('Y-m-d', strtotime("+{$novoDias} days"));
            
            if ($novoEmail && $novaSenha) {
                $usuarios = admin_carregarJSON(ARQ_USUARIOS);
                
                // Verifica se ja existe (email como chave)
                $emailLower = strtolower($novoEmail);
                $existe = false;
                foreach ($usuarios as $emailKey => $dados) {
                    if (strtolower($emailKey) === $emailLower) {
                        $existe = true;
                        break;
                    }
                }
                
                if ($existe) {
                    $mensagem = "Usuario ja existe.";
                    $tipoMensagem = 'error';
                } else {
                    // Usa email como chave do JSON
                    $usuarios[$novoEmail] = [
                        'nome' => $novoNome ?: $novoEmail,
                        'senha' => $novaSenha, // Mantém em texto puro para compatibilidade
                        'eh_admin' => $novoAdmin,
                        'utm_cloaker_ativo' => $novoCloaker,
                        'expira_em' => $novoExpira,
                        'bloqueado' => false,
                        'criado_em' => date('Y-m-d H:i:s'),
                        'criado_por' => $usuario['email']
                    ];
                    admin_salvarJSON(ARQ_USUARIOS, $usuarios);
                    $mensagem = "Usuario criado com sucesso.";
                    $tipoMensagem = 'success';
                }
            }
            break;
            
        case 'editar_usuario':
            $editEmail = $_POST['edit_email'] ?? '';
            $editNome = htmlspecialchars($_POST['edit_nome'] ?? '');
            $editSenha = $_POST['edit_senha'] ?? '';
            $editAdmin = isset($_POST['edit_admin']);
            $editCloaker = isset($_POST['edit_cloaker']);
            $editExpira = $_POST['edit_expira'] ?? '';
            $editBloqueado = isset($_POST['edit_bloqueado']);
            
            if ($editEmail) {
                $usuarios = admin_carregarJSON(ARQ_USUARIOS);
                
                // Busca pelo email como chave
                foreach ($usuarios as $emailKey => &$dados) {
                    if (strtolower($emailKey) === strtolower($editEmail)) {
                        if ($editNome) $dados['nome'] = $editNome;
                        if ($editSenha) $dados['senha'] = $editSenha; // Texto puro
                        if ($editExpira) $dados['expira_em'] = $editExpira;
                        $dados['eh_admin'] = $editAdmin;
                        $dados['utm_cloaker_ativo'] = $editCloaker;
                        $dados['bloqueado'] = $editBloqueado;
                        break;
                    }
                }
                admin_salvarJSON(ARQ_USUARIOS, $usuarios);
                $mensagem = "Usuario atualizado com sucesso.";
                $tipoMensagem = 'success';
            }
            break;
            
        case 'excluir_usuario':
            $delEmail = $_POST['del_email'] ?? '';
            if ($delEmail && $delEmail !== $usuario['email']) {
                $usuarios = admin_carregarJSON(ARQ_USUARIOS);
                $usuarios = array_filter($usuarios, function($u) use ($delEmail) {
                    return strtolower($u['email']) !== strtolower($delEmail);
                });
                admin_salvarJSON(ARQ_USUARIOS, array_values($usuarios));
                $mensagem = "Usuario excluido com sucesso.";
                $tipoMensagem = 'success';
            }
            break;
            
        case 'criar_aviso':
            $avisoTitulo = htmlspecialchars($_POST['aviso_titulo'] ?? '');
            $avisoConteudo = htmlspecialchars($_POST['aviso_conteudo'] ?? '');
            $avisoTipo = $_POST['aviso_tipo'] ?? 'info';
            $avisoAtivo = isset($_POST['aviso_ativo']);
            
            if ($avisoTitulo && $avisoConteudo) {
                $avisos = admin_carregarJSON(ARQ_AVISOS);
                $avisos[] = [
                    'id' => uniqid('aviso_'),
                    'titulo' => $avisoTitulo,
                    'conteudo' => $avisoConteudo,
                    'tipo' => $avisoTipo,
                    'ativo' => $avisoAtivo,
                    'criado_em' => date('Y-m-d H:i:s'),
                    'criado_por' => $usuario['email']
                ];
                admin_salvarJSON(ARQ_AVISOS, $avisos);
                $mensagem = "Aviso criado com sucesso.";
                $tipoMensagem = 'success';
            }
            break;
            
        case 'editar_aviso':
            $avisoId = $_POST['aviso_id'] ?? '';
            $avisoTitulo = htmlspecialchars($_POST['aviso_titulo'] ?? '');
            $avisoConteudo = htmlspecialchars($_POST['aviso_conteudo'] ?? '');
            $avisoTipo = $_POST['aviso_tipo'] ?? 'info';
            $avisoAtivo = isset($_POST['aviso_ativo']);
            
            if ($avisoId) {
                $avisos = admin_carregarJSON(ARQ_AVISOS);
                foreach ($avisos as &$av) {
                    if ($av['id'] === $avisoId) {
                        $av['titulo'] = $avisoTitulo;
                        $av['conteudo'] = $avisoConteudo;
                        $av['tipo'] = $avisoTipo;
                        $av['ativo'] = $avisoAtivo;
                        $av['editado_em'] = date('Y-m-d H:i:s');
                        break;
                    }
                }
                admin_salvarJSON(ARQ_AVISOS, $avisos);
                $mensagem = "Aviso atualizado com sucesso.";
                $tipoMensagem = 'success';
            }
            break;
            
        case 'excluir_aviso':
            $avisoId = $_POST['aviso_id'] ?? '';
            if ($avisoId) {
                $avisos = admin_carregarJSON(ARQ_AVISOS);
                $avisos = array_filter($avisos, function($av) use ($avisoId) {
                    return $av['id'] !== $avisoId;
                });
                admin_salvarJSON(ARQ_AVISOS, array_values($avisos));
                $mensagem = "Aviso excluido com sucesso.";
                $tipoMensagem = 'success';
            }
            break;
            
        // ============================================
        // MODO FAKE/DEMO - Apenas para Admin
        // ============================================
        
        case 'salvar_fake_mode':
            // Pega os campos base
            $paidCount = intval($_POST['fake_paid_count'] ?? 0);
            $pendingCount = intval($_POST['fake_pending_count'] ?? 0);
            $refundedCount = intval($_POST['fake_refunded_count'] ?? 0);
            $avgTicket = floatval($_POST['fake_avg_ticket'] ?? 0);
            $conversionRate = floatval($_POST['fake_conversion_rate'] ?? 0);
            
            // Calcula totais automaticamente
            $totalPaid = $paidCount * $avgTicket;
            $totalPending = $pendingCount * $avgTicket;
            $totalRefunded = $refundedCount * $avgTicket;
            
            // Processa campanhas customizadas
            $campNames = $_POST['camp_name'] ?? [];
            $campPercents = $_POST['camp_percent'] ?? [];
            
            $campanhas = [];
            $campaignsConfig = [];
            for ($i = 0; $i < count($campNames); $i++) {
                $name = trim($campNames[$i]);
                $percent = intval($campPercents[$i] ?? 0);
                
                if (!empty($name) && $percent > 0) {
                    $campaignsConfig[] = ['name' => $name, 'percent' => $percent];
                    
                    $campPaidCount = round($paidCount * $percent / 100);
                    $campPendingCount = round($pendingCount * $percent / 100);
                    $campRefundedCount = round($refundedCount * $percent / 100);
                    
                    $campanhas[] = [
                        'name' => $name,
                        'paid' => $campPaidCount * $avgTicket,
                        'pending' => $campPendingCount * $avgTicket,
                        'refunded' => $campRefundedCount * $avgTicket,
                        'paid_count' => $campPaidCount,
                        'pending_count' => $campPendingCount,
                        'refunded_count' => $campRefundedCount
                    ];
                }
            }
            
            // Processa gateways customizados
            $gwNames = $_POST['gw_name'] ?? [];
            $gwPercents = $_POST['gw_percent'] ?? [];
            
            $gatewayData = [];
            $gatewaysConfig = [];
            for ($i = 0; $i < count($gwNames); $i++) {
                $name = $gwNames[$i];
                $percent = intval($gwPercents[$i] ?? 0);
                
                if (!empty($name) && $percent > 0) {
                    $gatewaysConfig[] = ['name' => $name, 'percent' => $percent];
                    
                    $gatewayData[$name] = [
                        'paid' => round($totalPaid * $percent / 100, 2),
                        'pending' => round($totalPending * $percent / 100, 2),
                        'refunded' => round($totalRefunded * $percent / 100, 2),
                        'total' => round(($totalPaid + $totalPending) * $percent / 100, 2),
                        'count' => round(($paidCount + $pendingCount) * $percent / 100),
                        'paid_count' => round($paidCount * $percent / 100),
                        'pending_count' => round($pendingCount * $percent / 100),
                        'refunded_count' => round($refundedCount * $percent / 100)
                    ];
                }
            }
            
            $fakeData = [
                'enabled' => isset($_POST['fake_enabled']),
                'updated_at' => date('Y-m-d H:i:s'),
                'campaigns_config' => $campaignsConfig,
                'gateways_config' => $gatewaysConfig,
                'metrics' => [
                    'total_paid' => $totalPaid,
                    'total_pending' => $totalPending,
                    'total_refunded' => $totalRefunded,
                    'paid_count' => $paidCount,
                    'pending_count' => $pendingCount,
                    'refunded_count' => $refundedCount,
                    'avg_ticket' => $avgTicket,
                    'conversion_rate' => $conversionRate
                ],
                'campaigns' => $campanhas,
                'by_gateway' => $gatewayData
            ];
            
            // Garante que o diretorio existe
            $dir = dirname(ARQ_FAKE_MODE);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            admin_salvarJSON(ARQ_FAKE_MODE, $fakeData);
            $mensagem = "Modo Demo " . ($fakeData['enabled'] ? "ATIVADO" : "desativado") . " com sucesso!";
            $tipoMensagem = 'success';
            break;
            
        case 'desativar_fake_mode':
            $fakeData = admin_carregarJSON(ARQ_FAKE_MODE);
            $fakeData['enabled'] = false;
            admin_salvarJSON(ARQ_FAKE_MODE, $fakeData);
            $mensagem = "Modo Demo desativado. Dados reais serao exibidos.";
            $tipoMensagem = 'success';
            break;
    }
}

// Carrega dados para exibicao
$usuarios = admin_carregarJSON(ARQ_USUARIOS);
$sessoes = admin_carregarJSON(ARQ_SESSOES);
$strikes = admin_carregarJSON(ARQ_STRIKES);
$dispositivos = admin_carregarJSON(ARQ_DISPOSITIVOS);
$ipsBloqueados = admin_carregarJSON(ARQ_IPS_BLOQUEADOS);
$avisos = admin_carregarJSON(ARQ_AVISOS);

// Aba atual
$aba = $_GET['aba'] ?? 'painel';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Sistema</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0a0f;
            color: #fff;
            min-height: 100vh;
        }
        
        .header {
            background: rgba(26, 26, 46, 0.9);
            border-bottom: 1px solid rgba(102, 126, 234, 0.2);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .header-user {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .header-user span {
            color: #8892b0;
            font-size: 14px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        
        .btn-danger {
            background: #ef4444;
            color: #fff;
        }
        
        .btn-success {
            background: #22c55e;
            color: #fff;
        }
        
        .btn-secondary {
            background: rgba(102, 126, 234, 0.2);
            color: #667eea;
            border: 1px solid rgba(102, 126, 234, 0.3);
        }
        
        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }
        
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 12px 20px;
            background: rgba(26, 26, 46, 0.6);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 12px;
            color: #8892b0;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .tab:hover, .tab.active {
            background: rgba(102, 126, 234, 0.2);
            color: #fff;
            border-color: rgba(102, 126, 234, 0.4);
        }
        
        .card {
            background: rgba(26, 26, 46, 0.6);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .card h2 {
            font-size: 18px;
            margin-bottom: 16px;
            color: #ccd6f6;
        }
        
        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        th {
            color: #8892b0;
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        td {
            color: #ccd6f6;
            font-size: 14px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .badge-info { background: rgba(102, 126, 234, 0.2); color: #667eea; }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .form-group label {
            font-size: 13px;
            color: #8892b0;
        }
        
        .form-group input, .form-group select {
            padding: 10px 14px;
            background: rgba(10, 10, 15, 0.6);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .grid-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: rgba(26, 26, 46, 0.6);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 16px;
            padding: 20px;
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #8892b0;
            margin-bottom: 8px;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
        }
        
        .systems-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }
        
        .system-card {
            background: rgba(26, 26, 46, 0.8);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
        }
        
        .system-card:hover {
            border-color: rgba(102, 126, 234, 0.6);
            transform: translateY(-2px);
        }
        
        .system-card h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #fff;
        }
        
        .system-card p {
            color: #8892b0;
            font-size: 14px;
            margin-bottom: 16px;
        }
        
        .text-muted { color: #8892b0; }
        .text-small { font-size: 12px; }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 12px; }
            .tabs { justify-content: center; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Painel Administrativo</h1>
        <div class="header-user">
            <span>Ola, <?php echo htmlspecialchars($usuario['nome']); ?></span>
            <?php if ($ehAdmin): ?>
                <span class="badge badge-info">Admin</span>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-secondary">Sair</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipoMensagem; ?>"><?php echo $mensagem; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <a href="?aba=painel" class="tab <?php echo $aba === 'painel' ? 'active' : ''; ?>">Painel</a>
            <?php if ($ehAdmin): ?>
                <a href="?aba=usuarios" class="tab <?php echo $aba === 'usuarios' ? 'active' : ''; ?>">Usuarios</a>
                <a href="?aba=blackpages" class="tab <?php echo $aba === 'blackpages' ? 'active' : ''; ?>" style="border-color: rgba(239,68,68,0.4); <?php echo $aba === 'blackpages' ? 'background: rgba(239,68,68,0.15); color:#fff;' : 'color:#ef4444;'; ?>">Black Pages</a>
                <a href="?aba=dispositivos" class="tab <?php echo $aba === 'dispositivos' ? 'active' : ''; ?>">Dispositivos</a>
                <a href="?aba=ips" class="tab <?php echo $aba === 'ips' ? 'active' : ''; ?>">IPs Bloqueados</a>
                <a href="?aba=sessoes" class="tab <?php echo $aba === 'sessoes' ? 'active' : ''; ?>">Sessoes</a>
                <a href="?aba=strikes" class="tab <?php echo $aba === 'strikes' ? 'active' : ''; ?>">Strikes</a>
                <a href="?aba=avisos" class="tab <?php echo $aba === 'avisos' ? 'active' : ''; ?>" style="border-color: rgba(34,197,94,0.4); <?php echo $aba === 'avisos' ? 'background: rgba(34,197,94,0.15); color:#fff;' : 'color:#22c55e;'; ?>">Avisos</a>
                <a href="?aba=demo" class="tab <?php echo $aba === 'demo' ? 'active' : ''; ?>" style="border-color: rgba(255,0,80,0.4); <?php echo $aba === 'demo' ? 'background: linear-gradient(135deg, rgba(255,0,80,0.2), rgba(168,85,247,0.2)); color:#fff;' : 'color:#ff0050;'; ?>">
                    <span style="display:flex;align-items:center;gap:6px;">
                        Demo Mode
                        <?php 
                        $fakeMode = admin_carregarJSON(ARQ_FAKE_MODE);
                        if (!empty($fakeMode['enabled'])): 
                        ?>
                        <span style="width:8px;height:8px;border-radius:50%;background:#00ff88;animation:pulse 1s infinite;"></span>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endif; ?>
        </div>
        
        <?php if ($aba === 'painel'): ?>
            <!-- PAINEL PRINCIPAL -->
            <div class="grid-cards">
                <div class="stat-card">
                    <h3>Usuarios Ativos</h3>
                    <div class="value"><?php echo count($usuarios); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Sessoes Ativas</h3>
                    <div class="value"><?php echo count($sessoes); ?></div>
                </div>
                <div class="stat-card">
                    <h3>IPs Bloqueados</h3>
                    <div class="value"><?php echo count($ipsBloqueados); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Dispositivos</h3>
                    <div class="value"><?php 
                        $totalDisp = 0;
                        foreach ($dispositivos as $d) $totalDisp += count($d);
                        echo $totalDisp;
                    ?></div>
                </div>
            </div>
            
            <div class="card">
                <h2>Acesso aos Sistemas</h2>
                <div class="systems-grid">
                    <a href="COMMANDER/" class="system-card" style="text-decoration: none;">
                        <h3>COMMANDER V1</h3>
                        <p>Sistema original de cloaking UTM</p>
                        <span class="badge badge-warning">Legado</span>
                    </a>
                    <a href="COMMANDERV2/" class="system-card" style="text-decoration: none;">
                        <h3>COMMANDER V2</h3>
                        <p>Nova versao com recursos avancados</p>
                        <span class="badge badge-success">Novo</span>
                    </a>
                    <a href="COMMANDERV3/" class="system-card" style="text-decoration: none; border-color: rgba(255,0,80,0.4); background: linear-gradient(135deg, rgba(255,0,80,0.05), rgba(168,85,247,0.05));">
                        <h3 style="background: linear-gradient(135deg, #ff0050, #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">COMMANDER V3</h3>
                        <p>Metricas de vendas e faturamento</p>
                        <span class="badge" style="background: linear-gradient(135deg, #ff0050, #a855f7); color: #fff;">Premium</span>
                    </a>
                </div>
            </div>
            
        <?php elseif ($aba === 'usuarios' && $ehAdmin): ?>
            <!-- USUARIOS -->
            <div class="card">
                <h2>Criar Novo Usuario</h2>
                <form method="POST">
                    <input type="hidden" name="acao" value="criar_usuario">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="novo_email" required>
                        </div>
                        <div class="form-group">
                            <label>Nome</label>
                            <input type="text" name="novo_nome">
                        </div>
                        <div class="form-group">
                            <label>Senha</label>
                            <input type="password" name="nova_senha" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Dias de Acesso</label>
                            <input type="number" name="novo_dias" value="30" min="1" max="365" style="width:100px;">
                            <small style="color:#888;margin-left:8px;">A partir de hoje</small>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="novo_admin" id="novo_admin">
                            <label for="novo_admin">Administrador</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="novo_cloaker" id="novo_cloaker" checked>
                            <label for="novo_cloaker">Acesso UTM/Cloaker</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Criar Usuario</button>
                </form>
            </div>
            
            <div class="card">
                <h2>Usuarios Cadastrados</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Nome</th>
                            <th>Admin</th>
                            <th>Cloaker</th>
                            <th>Expira em</th>
                            <th>Status</th>
                            <th>Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $emailKey => $u): 
                            $expiraEm = $u['expira_em'] ?? null;
                            $expirado = $expiraEm && strtotime($expiraEm) < time();
                            $diasRestantes = $expiraEm ? max(0, floor((strtotime($expiraEm) - time()) / 86400)) : null;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($emailKey); ?></td>
                            <td><?php echo htmlspecialchars($u['nome'] ?? '-'); ?></td>
                            <td><?php echo ($u['eh_admin'] ?? false) ? '<span class="badge badge-info">Sim</span>' : '<span class="badge badge-secondary">Nao</span>'; ?></td>
                            <td><?php echo ($u['utm_cloaker_ativo'] ?? false) ? '<span class="badge badge-success">Ativo</span>' : '<span class="badge badge-danger">Inativo</span>'; ?></td>
                            <td>
                                <?php if ($expiraEm): ?>
                                    <span style="color:<?php echo $expirado ? '#ef4444' : ($diasRestantes <= 7 ? '#f59e0b' : '#10b981'); ?>;">
                                        <?php echo date('d/m/Y', strtotime($expiraEm)); ?>
                                        <?php if (!$expirado): ?>
                                            <small>(<?php echo $diasRestantes; ?> dias)</small>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Ilimitado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['bloqueado'] ?? false): ?>
                                    <span class="badge badge-danger">Bloqueado</span>
                                <?php elseif ($expirado): ?>
                                    <span class="badge badge-warning">Expirado</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Ativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($emailKey !== $usuario['email']): ?>
                                <button type="button" class="btn btn-secondary" onclick="editarUsuario('<?php echo htmlspecialchars($emailKey); ?>', '<?php echo htmlspecialchars(addslashes($u['nome'] ?? '')); ?>', <?php echo ($u['eh_admin'] ?? false) ? 'true' : 'false'; ?>, <?php echo ($u['utm_cloaker_ativo'] ?? false) ? 'true' : 'false'; ?>, '<?php echo $expiraEm ?? ''; ?>', <?php echo ($u['bloqueado'] ?? false) ? 'true' : 'false'; ?>)">Editar</button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="acao" value="excluir_usuario">
                                    <input type="hidden" name="del_email" value="<?php echo htmlspecialchars($emailKey); ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Excluir este usuario?')">Excluir</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($aba === 'dispositivos' && $ehAdmin): ?>
            <!-- DISPOSITIVOS -->
            <div class="card">
                <h2>Dispositivos Autorizados por Usuario</h2>
                <?php foreach ($dispositivos as $email => $disps): ?>
                    <h3 style="margin: 20px 0 10px; color: #667eea;"><?php echo htmlspecialchars($email); ?></h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>IP</th>
                                <th>User Agent</th>
                                <th>Status</th>
                                <th>Ultimo Acesso</th>
                                <th>Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disps as $d): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($d['nome'] ?? 'Sem nome'); ?></td>
                                <td><span class="text-muted"><?php echo htmlspecialchars($d['ip'] ?? '-'); ?></span></td>
                                <td class="text-small text-muted"><?php echo htmlspecialchars(substr($d['user_agent'] ?? '-', 0, 50)) . '...'; ?></td>
                                <td><?php echo ($d['ativo'] ?? true) ? '<span class="badge badge-success">Ativo</span>' : '<span class="badge badge-danger">Inativo</span>'; ?></td>
                                <td class="text-muted"><?php echo isset($d['ultimo_acesso']) ? date('d/m/Y H:i', $d['ultimo_acesso']) : '-'; ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="acao" value="remover_dispositivo">
                                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                        <input type="hidden" name="fingerprint" value="<?php echo htmlspecialchars($d['fingerprint']); ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Remover este dispositivo?')">Remover</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
                <?php if (empty($dispositivos)): ?>
                    <p class="text-muted">Nenhum dispositivo registrado.</p>
                <?php endif; ?>
            </div>
            
        <?php elseif ($aba === 'ips' && $ehAdmin): ?>
            <!-- IPS BLOQUEADOS -->
            <div class="card">
                <h2>Bloquear Novo IP</h2>
                <form method="POST">
                    <input type="hidden" name="acao" value="bloquear_ip">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Endereco IP</label>
                            <input type="text" name="ip" placeholder="192.168.1.1" required>
                        </div>
                        <div class="form-group">
                            <label>Motivo</label>
                            <input type="text" name="motivo" placeholder="Motivo do bloqueio">
                        </div>
                    </div>
                    <div class="checkbox-group" style="margin-bottom: 16px;">
                        <input type="checkbox" name="permanente" id="permanente">
                        <label for="permanente">Bloqueio Permanente</label>
                    </div>
                    <button type="submit" class="btn btn-danger">Bloquear IP</button>
                </form>
            </div>
            
            <div class="card">
                <h2>IPs Bloqueados</h2>
                <table>
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>Motivo</th>
                            <th>Bloqueado Em</th>
                            <th>Expira Em</th>
                            <th>Tipo</th>
                            <th>Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ipsBloqueados as $ip => $info): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($ip); ?></strong></td>
                            <td><?php echo htmlspecialchars($info['motivo'] ?? '-'); ?></td>
                            <td class="text-muted"><?php echo isset($info['bloqueado_em']) ? date('d/m/Y H:i', $info['bloqueado_em']) : '-'; ?></td>
                            <td class="text-muted"><?php echo ($info['permanente'] ?? false) ? 'Nunca' : (isset($info['expira_em']) ? date('d/m/Y H:i', $info['expira_em']) : '-'); ?></td>
                            <td><?php echo ($info['permanente'] ?? false) ? '<span class="badge badge-danger">Permanente</span>' : '<span class="badge badge-warning">Temporario</span>'; ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="acao" value="desbloquear_ip">
                                    <input type="hidden" name="ip" value="<?php echo htmlspecialchars($ip); ?>">
                                    <button type="submit" class="btn btn-success">Desbloquear</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($ipsBloqueados)): ?>
                    <p class="text-muted">Nenhum IP bloqueado.</p>
                <?php endif; ?>
            </div>
            
        <?php elseif ($aba === 'sessoes' && $ehAdmin): ?>
            <!-- SESSOES -->
            <div class="card">
                <h2>Sessoes Ativas</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>IP</th>
                            <th>Criada Em</th>
                            <th>Expira Em</th>
                            <th>Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessoes as $token => $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['email']); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($s['ip'] ?? '-'); ?></td>
                            <td class="text-muted"><?php echo isset($s['criado_em']) ? date('d/m/Y H:i', $s['criado_em']) : '-'; ?></td>
                            <td class="text-muted"><?php echo isset($s['expira_sessao']) ? date('d/m/Y H:i', $s['expira_sessao']) : '-'; ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="acao" value="encerrar_sessao">
                                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Encerrar esta sessao?')">Encerrar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($sessoes)): ?>
                    <p class="text-muted">Nenhuma sessao ativa.</p>
                <?php endif; ?>
            </div>
            
        <?php elseif ($aba === 'blackpages' && $ehAdmin): ?>
            <!-- BLACK PAGES -->
            <?php
                $campanhas_v2 = admin_carregarJSON(ARQ_CAMPANHAS_V2);
                $base_url_v2 = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                    . '://' . $_SERVER['HTTP_HOST'] . '/COMMANDERV2/';
                $total_campanhas = count($campanhas_v2);
                $ativas = array_filter($campanhas_v2, fn($c) => ($c['status'] ?? '') === 'active');
                $pausadas = array_filter($campanhas_v2, fn($c) => ($c['status'] ?? '') !== 'active');
            ?>
            <style>
                .bp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 24px; }
                .bp-stat { background: rgba(26,26,46,0.6); border: 1px solid rgba(239,68,68,0.2); border-radius: 16px; padding: 20px; }
                .bp-stat h3 { font-size: 13px; color: #8892b0; margin-bottom: 6px; }
                .bp-stat .val { font-size: 36px; font-weight: 700; color: #ef4444; }
                .bp-table td { vertical-align: middle; }
                .bp-url-box {
                    display: flex; align-items: center; gap: 8px;
                    background: rgba(10,10,15,0.7); border: 1px solid rgba(239,68,68,0.25);
                    border-radius: 8px; padding: 8px 12px;
                }
                .bp-url-box span { font-size: 13px; color: #fca5a5; flex: 1; word-break: break-all; font-family: monospace; }
                .copy-btn {
                    background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.35);
                    color: #ef4444; border-radius: 6px; padding: 4px 10px;
                    font-size: 12px; cursor: pointer; white-space: nowrap; transition: all 0.2s;
                }
                .copy-btn:hover { background: rgba(239,68,68,0.3); }
                .copy-btn.copied { background: rgba(34,197,94,0.2); border-color: rgba(34,197,94,0.4); color: #22c55e; }
                .platform-badge {
                    display: inline-flex; align-items: center; gap: 5px;
                    padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
                }
                .plat-tiktok { background: rgba(0,0,0,0.4); color: #fff; border: 1px solid #333; }
                .plat-facebook { background: rgba(24,119,242,0.2); color: #60a5fa; border: 1px solid rgba(24,119,242,0.3); }
                .plat-google { background: rgba(66,133,244,0.2); color: #93c5fd; border: 1px solid rgba(66,133,244,0.3); }
                .plat-kwai { background: rgba(255,87,34,0.2); color: #fda4af; border: 1px solid rgba(255,87,34,0.3); }
                .plat-other { background: rgba(102,126,234,0.15); color: #a5b4fc; border: 1px solid rgba(102,126,234,0.25); }
                .search-box { 
                    width: 100%; padding: 10px 14px; background: rgba(10,10,15,0.6);
                    border: 1px solid rgba(239,68,68,0.3); border-radius: 8px;
                    color: #fff; font-size: 14px; margin-bottom: 16px;
                }
                .search-box:focus { outline: none; border-color: #ef4444; }
                .no-campaigns { text-align: center; padding: 48px 0; color: #8892b0; }
                .no-campaigns p { font-size: 15px; margin-top: 8px; }
                .export-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }
                .method-badge { padding: 2px 8px; border-radius: 20px; font-size: 11px; background: rgba(102,126,234,0.15); color: #a5b4fc; border: 1px solid rgba(102,126,234,0.2); }
            </style>

            <div class="bp-grid">
                <div class="bp-stat">
                    <h3>Total de Campanhas</h3>
                    <div class="val"><?php echo $total_campanhas; ?></div>
                </div>
                <div class="bp-stat">
                    <h3>Campanhas Ativas</h3>
                    <div class="val" style="color:#22c55e;"><?php echo count($ativas); ?></div>
                </div>
                <div class="bp-stat">
                    <h3>Campanhas Pausadas</h3>
                    <div class="val" style="color:#f59e0b;"><?php echo count($pausadas); ?></div>
                </div>
                <div class="bp-stat">
                    <h3>Base URL do Sistema</h3>
                    <div style="font-size:12px; color:#8892b0; margin-top:8px; word-break:break-all; font-family:monospace;"><?php echo htmlspecialchars($base_url_v2); ?></div>
                </div>
            </div>

            <div class="card">
                <div class="export-bar">
                    <h2 style="margin:0;">Links das Black Pages</h2>
                    <button onclick="exportarLinks()" class="btn btn-secondary" style="border-color:rgba(239,68,68,0.4);color:#ef4444;">Exportar Todos (.txt)</button>
                </div>

                <input type="text" class="search-box" id="searchBP" placeholder="Buscar por nome, slug ou plataforma..." onkeyup="filtrarCampanhas()">

                <?php if (empty($campanhas_v2)): ?>
                    <div class="no-campaigns">
                        <svg width="48" height="48" fill="none" stroke="#8892b0" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/></svg>
                        <p>Nenhuma campanha cadastrada no COMMANDER V2.</p>
                    </div>
                <?php else: ?>
                    <div id="tabelaBP">
                    <?php foreach ($campanhas_v2 as $c):
                        $slug = $c['slug'] ?? $c['id'] ?? '';
                        $black_url_final = $c['black_url'] ?? '';
                        $link_campanha = $base_url_v2 . '?c=' . $slug;
                        $plataforma = strtolower($c['platform'] ?? 'other');
                        $status = $c['status'] ?? 'active';
                        $metodo = $c['black_method'] ?? 'redirect';
                        $nome = $c['name'] ?? 'Sem nome';
                        $criado = isset($c['created_at']) ? date('d/m/Y', strtotime($c['created_at'])) : '-';

                        $plat_class = match($plataforma) {
                            'tiktok' => 'plat-tiktok',
                            'facebook' => 'plat-facebook',
                            'google' => 'plat-google',
                            'kwai' => 'plat-kwai',
                            default => 'plat-other'
                        };
                    ?>
                    <div class="campanha-row" data-nome="<?php echo strtolower($nome); ?>" data-plat="<?php echo $plataforma; ?>" data-slug="<?php echo strtolower($slug); ?>" style="background: rgba(10,10,15,0.45); border: 1px solid rgba(239,68,68,0.12); border-radius: 12px; padding: 18px 20px; margin-bottom: 14px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <strong style="font-size:15px; color:#fff;"><?php echo htmlspecialchars($nome); ?></strong>
                                <span class="platform-badge <?php echo $plat_class; ?>"><?php echo ucfirst($plataforma); ?></span>
                                <span class="method-badge"><?php echo $metodo; ?></span>
                                <?php if ($status === 'active'): ?>
                                    <span class="badge badge-success">Ativa</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Pausada</span>
                                <?php endif; ?>
                            </div>
                            <span style="font-size:12px; color:#8892b0;">Criada em: <?php echo $criado; ?></span>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <div>
                                <div style="font-size:11px; color:#8892b0; margin-bottom:5px; text-transform:uppercase; letter-spacing:.05em;">Link da Campanha (entrada)</div>
                                <div class="bp-url-box">
                                    <span id="link-<?php echo htmlspecialchars($slug); ?>"><?php echo htmlspecialchars($link_campanha); ?></span>
                                    <button class="copy-btn" onclick="copiar('link-<?php echo htmlspecialchars($slug); ?>', this)">Copiar</button>
                                    <a href="<?php echo htmlspecialchars($link_campanha); ?>" target="_blank" style="font-size:12px; color:#667eea; white-space:nowrap;">Abrir</a>
                                </div>
                            </div>

                            <?php if (!empty($black_url_final)): ?>
                            <div>
                                <div style="font-size:11px; color:#ef4444; margin-bottom:5px; text-transform:uppercase; letter-spacing:.05em;">Black Page (destino)</div>
                                <div class="bp-url-box">
                                    <span id="black-<?php echo htmlspecialchars($slug); ?>"><?php echo htmlspecialchars($black_url_final); ?></span>
                                    <button class="copy-btn" onclick="copiar('black-<?php echo htmlspecialchars($slug); ?>', this)">Copiar</button>
                                    <a href="<?php echo htmlspecialchars($black_url_final); ?>" target="_blank" style="font-size:12px; color:#ef4444; white-space:nowrap;">Abrir</a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <script>
            function copiar(id, btn) {
                const texto = document.getElementById(id).textContent.trim();
                navigator.clipboard.writeText(texto).then(() => {
                    btn.textContent = 'Copiado!';
                    btn.classList.add('copied');
                    setTimeout(() => { btn.textContent = 'Copiar'; btn.classList.remove('copied'); }, 2000);
                });
            }

            function filtrarCampanhas() {
                const busca = document.getElementById('searchBP').value.toLowerCase();
                document.querySelectorAll('.campanha-row').forEach(row => {
                    const nome = row.dataset.nome || '';
                    const plat = row.dataset.plat || '';
                    const slug = row.dataset.slug || '';
                    row.style.display = (nome.includes(busca) || plat.includes(busca) || slug.includes(busca)) ? '' : 'none';
                });
            }

            function exportarLinks() {
                const linhas = [];
                document.querySelectorAll('.campanha-row').forEach(row => {
                    const nome = row.querySelector('strong').textContent.trim();
                    const spans = row.querySelectorAll('.bp-url-box span');
                    linhas.push('=== ' + nome + ' ===');
                    spans.forEach((s, i) => {
                        linhas.push((i === 0 ? 'Link: ' : 'Black: ') + s.textContent.trim());
                    });
                    linhas.push('');
                });
                const blob = new Blob([linhas.join('\n')], { type: 'text/plain' });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'black-pages-' + new Date().toISOString().slice(0,10) + '.txt';
                a.click();
            }
            </script>

        <?php elseif ($aba === 'strikes' && $ehAdmin): ?>
            <!-- STRIKES -->
            <div class="card">
                <h2>Historico de Strikes</h2>
                <?php foreach ($strikes as $email => $info): ?>
                    <div style="background: rgba(10,10,15,0.5); border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <strong><?php echo htmlspecialchars($email); ?></strong>
                            <span class="badge badge-danger"><?php echo $info['count']; ?> strikes</span>
                        </div>
                        <?php if (!empty($info['historico'])): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Motivo</th>
                                        <th>IP</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($info['historico'], -5) as $h): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($h['motivo'] ?? '-'); ?></td>
                                        <td class="text-muted"><?php echo htmlspecialchars($h['ip'] ?? '-'); ?></td>
                                        <td class="text-muted"><?php echo htmlspecialchars($h['data'] ?? '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        <form method="POST" style="margin-top: 12px;">
                            <input type="hidden" name="acao" value="limpar_strikes">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                            <button type="submit" class="btn btn-success">Limpar Strikes</button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($strikes)): ?>
                    <p class="text-muted">Nenhum strike registrado.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($aba === 'avisos' && $ehAdmin): ?>
            <!-- AVISOS DO SISTEMA -->
            <div class="card">
                <h2>Criar Novo Aviso</h2>
                <form method="POST">
                    <input type="hidden" name="acao" value="criar_aviso">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Titulo</label>
                            <input type="text" name="aviso_titulo" required placeholder="Ex: Nova funcionalidade disponivel!">
                        </div>
                        <div class="form-group">
                            <label>Tipo</label>
                            <select name="aviso_tipo">
                                <option value="info">Informacao (Azul)</option>
                                <option value="success">Sucesso (Verde)</option>
                                <option value="warning">Atencao (Amarelo)</option>
                                <option value="danger">Importante (Vermelho)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Conteudo da Mensagem</label>
                        <textarea name="aviso_conteudo" required rows="4" style="width:100%; padding:10px 14px; background:rgba(10,10,15,0.6); border:1px solid rgba(102,126,234,0.3); border-radius:8px; color:#fff; font-size:14px; resize:vertical;" placeholder="Escreva aqui o conteudo do aviso que sera exibido para todos os usuarios..."></textarea>
                    </div>
                    <div style="display:flex; align-items:center; gap:16px; margin-top:16px;">
                        <div class="checkbox-group">
                            <input type="checkbox" name="aviso_ativo" id="aviso_ativo" checked>
                            <label for="aviso_ativo">Ativo (visivel para usuarios)</label>
                        </div>
                        <button type="submit" class="btn btn-success">Criar Aviso</button>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <h2>Avisos Cadastrados (<?php echo count($avisos); ?>)</h2>
                <?php if (empty($avisos)): ?>
                    <p class="text-muted">Nenhum aviso cadastrado ainda.</p>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <?php foreach (array_reverse($avisos) as $aviso): ?>
                            <div style="background:rgba(10,10,15,0.6); border:1px solid rgba(102,126,234,0.2); border-radius:12px; padding:16px; <?php echo $aviso['ativo'] ? '' : 'opacity:0.5;'; ?>">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                                    <div>
                                        <span class="badge badge-<?php echo $aviso['tipo'] ?? 'info'; ?>" style="margin-right:8px;">
                                            <?php 
                                            $tipos = ['info' => 'Info', 'success' => 'Sucesso', 'warning' => 'Atencao', 'danger' => 'Importante'];
                                            echo $tipos[$aviso['tipo']] ?? 'Info';
                                            ?>
                                        </span>
                                        <?php if (!$aviso['ativo']): ?>
                                            <span class="badge" style="background:rgba(100,100,100,0.3); color:#888;">Inativo</span>
                                        <?php endif; ?>
                                        <h3 style="margin:8px 0 4px 0; color:#fff; font-size:16px;"><?php echo htmlspecialchars($aviso['titulo']); ?></h3>
                                        <p style="color:#8892b0; font-size:12px;">
                                            Criado em <?php echo date('d/m/Y H:i', strtotime($aviso['criado_em'])); ?>
                                            <?php if (!empty($aviso['editado_em'])): ?>
                                                | Editado em <?php echo date('d/m/Y H:i', strtotime($aviso['editado_em'])); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div style="display:flex; gap:8px;">
                                        <button onclick="editarAviso('<?php echo $aviso['id']; ?>', '<?php echo addslashes($aviso['titulo']); ?>', '<?php echo addslashes($aviso['conteudo']); ?>', '<?php echo $aviso['tipo']; ?>', <?php echo $aviso['ativo'] ? 'true' : 'false'; ?>)" class="btn btn-secondary" style="padding:6px 12px; font-size:12px;">Editar</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir este aviso?');">
                                            <input type="hidden" name="acao" value="excluir_aviso">
                                            <input type="hidden" name="aviso_id" value="<?php echo $aviso['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="padding:6px 12px; font-size:12px;">Excluir</button>
                                        </form>
                                    </div>
                                </div>
                                <p style="color:#ccd6f6; font-size:14px; line-height:1.6; white-space:pre-wrap;"><?php echo htmlspecialchars($aviso['conteudo']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
        <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($aba === 'demo' && $ehAdmin): ?>
        <!-- DEMO MODE - Metricas Fake -->
        <?php $fakeMode = admin_carregarJSON(ARQ_FAKE_MODE); ?>
        <div class="card" style="border: 2px solid rgba(255,0,80,0.3); background: linear-gradient(135deg, rgba(255,0,80,0.05), rgba(168,85,247,0.05)); max-width:100%; overflow-x:auto;">
            <div style="padding:24px;">
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:16px;margin-bottom:24px;">
                    <div style="width:50px;height:50px;border-radius:12px;background:linear-gradient(135deg,#ff0050,#a855f7);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <span style="font-size:24px;">&#128200;</span>
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <h2 style="margin:0;color:#fff;font-size:22px;">Metricas V3 - Modo Demonstracao</h2>
                        <p style="margin:4px 0 0 0;color:#8892b0;font-size:13px;">Configure numeros para demonstracao do sistema</p>
                    </div>
                    <?php if (!empty($fakeMode['enabled'])): ?>
                    <div style="padding:8px 16px;background:rgba(0,255,136,0.15);border:1px solid rgba(0,255,136,0.3);border-radius:8px;">
                        <span style="color:#00ff88;font-weight:600;font-size:13px;">ATIVO</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <form method="POST" id="demo-form">
                    <input type="hidden" name="acao" value="salvar_fake_mode">
                    
                    <!-- Toggle + Numeros Base -->
                    <div style="background:var(--surface, #1a1a2e);border-radius:12px;padding:20px;margin-bottom:20px;">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid rgba(255,255,255,0.1);">
                            <input type="checkbox" name="fake_enabled" id="fake_enabled" <?php echo !empty($fakeMode['enabled']) ? 'checked' : ''; ?> style="width:20px;height:20px;">
                            <label for="fake_enabled" style="font-size:16px;font-weight:600;color:#fff;cursor:pointer;">Ativar Modo Demonstracao</label>
                        </div>
                        
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;">
                            <div class="form-group" style="margin:0;">
                                <label style="color:#00d4aa;font-size:12px;font-weight:600;">Vendas Pagas</label>
                                <input type="number" name="fake_paid_count" id="fake_paid_count" class="form-control" value="<?php echo $fakeMode['metrics']['paid_count'] ?? 89; ?>" style="font-size:18px;font-weight:700;text-align:center;" oninput="calcularPreview()">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label style="color:#ffcc00;font-size:12px;font-weight:600;">Pendentes</label>
                                <input type="number" name="fake_pending_count" id="fake_pending_count" class="form-control" value="<?php echo $fakeMode['metrics']['pending_count'] ?? 12; ?>" style="font-size:18px;font-weight:700;text-align:center;" oninput="calcularPreview()">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label style="color:#ef4444;font-size:12px;font-weight:600;">Reembolsos</label>
                                <input type="number" name="fake_refunded_count" id="fake_refunded_count" class="form-control" value="<?php echo $fakeMode['metrics']['refunded_count'] ?? 0; ?>" style="font-size:18px;font-weight:700;text-align:center;" oninput="calcularPreview()">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label style="color:#a855f7;font-size:12px;font-weight:600;">Ticket (R$)</label>
                                <input type="number" step="0.01" name="fake_avg_ticket" id="fake_avg_ticket" class="form-control" value="<?php echo $fakeMode['metrics']['avg_ticket'] ?? 197; ?>" style="font-size:18px;font-weight:700;text-align:center;" oninput="calcularPreview()">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label style="color:#3b82f6;font-size:12px;font-weight:600;">Conversao (%)</label>
                                <input type="number" step="0.1" name="fake_conversion_rate" id="fake_conversion_rate" class="form-control" value="<?php echo $fakeMode['metrics']['conversion_rate'] ?? 87.3; ?>" style="font-size:18px;font-weight:700;text-align:center;">
                            </div>
                        </div>
                        
                        <!-- Preview dos Totais -->
                        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.1);">
                            <div style="text-align:center;">
                                <div id="preview-total-paid" style="font-size:20px;font-weight:700;color:#00d4aa;">R$ 0,00</div>
                                <div style="color:#8892b0;font-size:11px;">Total Pagas</div>
                            </div>
                            <div style="text-align:center;">
                                <div id="preview-total-pending" style="font-size:20px;font-weight:700;color:#ffcc00;">R$ 0,00</div>
                                <div style="color:#8892b0;font-size:11px;">Total Pendentes</div>
                            </div>
                            <div style="text-align:center;">
                                <div id="preview-total-refunded" style="font-size:20px;font-weight:700;color:#ef4444;">R$ 0,00</div>
                                <div style="color:#8892b0;font-size:11px;">Total Reembolsos</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- CAMPANHAS CUSTOMIZAVEIS -->
                    <div style="background:var(--surface, #1a1a2e);border-radius:12px;padding:20px;margin-bottom:20px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                            <h3 style="margin:0;color:#fff;font-size:16px;">Campanhas</h3>
                            <div style="display:flex;align-items:center;gap:12px;">
                                <span id="camp-total-percent" style="color:#00d4aa;font-size:13px;font-weight:600;">0%</span>
                                <button type="button" onclick="addCampaign()" class="btn btn-secondary" style="padding:6px 12px;font-size:12px;">+ Campanha</button>
                            </div>
                        </div>
                        
                        <div id="campaigns-list">
                            <?php 
                            $defaultCampaigns = [
                                ['name' => 'fb-lookalike-compradores', 'percent' => 45],
                                ['name' => 'tiktok-viral-vendas', 'percent' => 35],
                                ['name' => 'google-search-brand', 'percent' => 20]
                            ];
                            $campaigns = $fakeMode['campaigns_config'] ?? $defaultCampaigns;
                            foreach ($campaigns as $i => $camp): 
                            ?>
                            <div class="campaign-row" style="display:grid;grid-template-columns:1fr 80px 100px 100px auto;gap:8px;align-items:center;padding:10px;background:#0a0a0f;border-radius:8px;margin-bottom:8px;">
                                <input type="text" name="camp_name[]" class="form-control" value="<?php echo htmlspecialchars($camp['name']); ?>" placeholder="Nome da campanha" style="font-size:13px;">
                                <input type="number" name="camp_percent[]" class="form-control camp-percent" value="<?php echo $camp['percent']; ?>" min="1" max="100" style="font-size:13px;text-align:center;" oninput="updateCampTotals()">
                                <div style="text-align:center;">
                                    <span class="camp-paid-preview" style="color:#00d4aa;font-size:12px;font-weight:600;">0</span>
                                    <div style="color:#8892b0;font-size:10px;">pagas</div>
                                </div>
                                <div style="text-align:center;">
                                    <span class="camp-pending-preview" style="color:#ffcc00;font-size:12px;font-weight:600;">0</span>
                                    <div style="color:#8892b0;font-size:10px;">pend</div>
                                </div>
                                <button type="button" onclick="this.closest('.campaign-row').remove();updateCampTotals();" class="btn btn-danger" style="padding:6px 10px;font-size:11px;">X</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p style="margin:8px 0 0 0;color:#8892b0;font-size:11px;">As porcentagens devem somar 100%. Os valores serao distribuidos automaticamente.</p>
                    </div>
                    
                    <!-- GATEWAYS CUSTOMIZAVEIS -->
                    <div style="background:var(--surface, #1a1a2e);border-radius:12px;padding:20px;margin-bottom:20px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                            <h3 style="margin:0;color:#fff;font-size:16px;">Gateways de Pagamento</h3>
                            <div style="display:flex;align-items:center;gap:12px;">
                                <span id="gw-total-percent" style="color:#00d4aa;font-size:13px;font-weight:600;">0%</span>
                                <button type="button" onclick="addGateway()" class="btn btn-secondary" style="padding:6px 12px;font-size:12px;">+ Gateway</button>
                            </div>
                        </div>
                        
                        <div id="gateways-list">
                            <?php 
                            $defaultGateways = [
                                ['name' => 'kiwify', 'percent' => 60],
                                ['name' => 'hotmart', 'percent' => 40]
                            ];
                            $gatewaysConfig = $fakeMode['gateways_config'] ?? $defaultGateways;
                            foreach ($gatewaysConfig as $gw): 
                            ?>
                            <div class="gateway-row" style="display:grid;grid-template-columns:1fr 80px 100px 100px auto;gap:8px;align-items:center;padding:10px;background:#0a0a0f;border-radius:8px;margin-bottom:8px;">
                                <select name="gw_name[]" class="form-control" style="font-size:13px;">
                                    <option value="kiwify" <?php echo $gw['name']==='kiwify'?'selected':''; ?>>Kiwify</option>
                                    <option value="hotmart" <?php echo $gw['name']==='hotmart'?'selected':''; ?>>Hotmart</option>
                                    <option value="stripe" <?php echo $gw['name']==='stripe'?'selected':''; ?>>Stripe</option>
                                    <option value="payt" <?php echo $gw['name']==='payt'?'selected':''; ?>>Payt</option>
                                    <option value="perfectpay" <?php echo $gw['name']==='perfectpay'?'selected':''; ?>>PerfectPay</option>
                                    <option value="eduzz" <?php echo $gw['name']==='eduzz'?'selected':''; ?>>Eduzz</option>
                                    <option value="monetizze" <?php echo $gw['name']==='monetizze'?'selected':''; ?>>Monetizze</option>
                                    <option value="braip" <?php echo $gw['name']==='braip'?'selected':''; ?>>Braip</option>
                                    <option value="appmax" <?php echo $gw['name']==='appmax'?'selected':''; ?>>Appmax</option>
                                    <option value="greenn" <?php echo $gw['name']==='greenn'?'selected':''; ?>>Greenn</option>
                                    <option value="ticto" <?php echo $gw['name']==='ticto'?'selected':''; ?>>Ticto</option>
                                    <option value="pepper" <?php echo $gw['name']==='pepper'?'selected':''; ?>>Pepper</option>
                                    <option value="bynet" <?php echo $gw['name']==='bynet'?'selected':''; ?>>Bynet</option>
                                    <option value="blackcat" <?php echo $gw['name']==='blackcat'?'selected':''; ?>>BlackCat</option>
                                    <option value="cartpanda" <?php echo $gw['name']==='cartpanda'?'selected':''; ?>>CartPanda</option>
                                    <option value="yampi" <?php echo $gw['name']==='yampi'?'selected':''; ?>>Yampi</option>
                                    <option value="shopify" <?php echo $gw['name']==='shopify'?'selected':''; ?>>Shopify</option>
                                    <option value="nuvemshop" <?php echo $gw['name']==='nuvemshop'?'selected':''; ?>>Nuvemshop</option>
                                    <option value="doppus" <?php echo $gw['name']==='doppus'?'selected':''; ?>>Doppus</option>
                                    <option value="hubla" <?php echo $gw['name']==='hubla'?'selected':''; ?>>Hubla</option>
                                    <option value="lastlink" <?php echo $gw['name']==='lastlink'?'selected':''; ?>>Lastlink</option>
                                    <option value="guru" <?php echo $gw['name']==='guru'?'selected':''; ?>>Guru</option>
                                    <option value="pagbank" <?php echo $gw['name']==='pagbank'?'selected':''; ?>>PagBank</option>
                                    <option value="mercadopago" <?php echo $gw['name']==='mercadopago'?'selected':''; ?>>Mercado Pago</option>
                                    <option value="pagarme" <?php echo $gw['name']==='pagarme'?'selected':''; ?>>Pagar.me</option>
                                    <option value="asaas" <?php echo $gw['name']==='asaas'?'selected':''; ?>>Asaas</option>
                                    <option value="vindi" <?php echo $gw['name']==='vindi'?'selected':''; ?>>Vindi</option>
                                    <option value="iugu" <?php echo $gw['name']==='iugu'?'selected':''; ?>>Iugu</option>
                                    <option value="paypal" <?php echo $gw['name']==='paypal'?'selected':''; ?>>PayPal</option>
                                </select>
                                <input type="number" name="gw_percent[]" class="form-control gw-percent" value="<?php echo $gw['percent']; ?>" min="1" max="100" style="font-size:13px;text-align:center;" oninput="updateGwTotals()">
                                <div style="text-align:center;">
                                    <span class="gw-paid-preview" style="color:#00d4aa;font-size:12px;font-weight:600;">R$ 0</span>
                                    <div style="color:#8892b0;font-size:10px;">pagas</div>
                                </div>
                                <div style="text-align:center;">
                                    <span class="gw-pending-preview" style="color:#ffcc00;font-size:12px;font-weight:600;">R$ 0</span>
                                    <div style="color:#8892b0;font-size:10px;">pend</div>
                                </div>
                                <button type="button" onclick="this.closest('.gateway-row').remove();updateGwTotals();" class="btn btn-danger" style="padding:6px 10px;font-size:11px;">X</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p style="margin:8px 0 0 0;color:#8892b0;font-size:11px;">As porcentagens devem somar 100%. Os valores serao distribuidos automaticamente.</p>
                    </div>
                    
                    <!-- Botoes -->
                    <div style="display:flex;gap:12px;justify-content:flex-end;">
                        <?php if (!empty($fakeMode['enabled'])): ?>
                        <button type="submit" name="acao" value="desativar_fake_mode" class="btn btn-secondary" style="padding:12px 20px;">
                            Desativar
                        </button>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary" style="padding:12px 28px;background:linear-gradient(135deg,#ff0050,#a855f7);">
                            Salvar
                        </button>
                    </div>
                </form>
                
                <script>
                function addCampaign() {
                    const list = document.getElementById('campaigns-list');
                    const row = document.createElement('div');
                    row.className = 'campaign-row';
                    row.style.cssText = 'display:grid;grid-template-columns:1fr 80px 100px 100px auto;gap:8px;align-items:center;padding:10px;background:#0a0a0f;border-radius:8px;margin-bottom:8px;';
                    row.innerHTML = `
                        <input type="text" name="camp_name[]" class="form-control" placeholder="Nome da campanha" style="font-size:13px;">
                        <input type="number" name="camp_percent[]" class="form-control camp-percent" value="10" min="1" max="100" style="font-size:13px;text-align:center;" oninput="updateCampTotals()">
                        <div style="text-align:center;"><span class="camp-paid-preview" style="color:#00d4aa;font-size:12px;font-weight:600;">0</span><div style="color:#8892b0;font-size:10px;">pagas</div></div>
                        <div style="text-align:center;"><span class="camp-pending-preview" style="color:#ffcc00;font-size:12px;font-weight:600;">0</span><div style="color:#8892b0;font-size:10px;">pend</div></div>
                        <button type="button" onclick="this.closest('.campaign-row').remove();updateCampTotals();" class="btn btn-danger" style="padding:6px 10px;font-size:11px;">X</button>
                    `;
                    list.appendChild(row);
                    updateCampTotals();
                }
                
                function addGateway() {
                    const list = document.getElementById('gateways-list');
                    const row = document.createElement('div');
                    row.className = 'gateway-row';
                    row.style.cssText = 'display:grid;grid-template-columns:1fr 80px 100px 100px auto;gap:8px;align-items:center;padding:10px;background:#0a0a0f;border-radius:8px;margin-bottom:8px;';
                    row.innerHTML = `
                        <select name="gw_name[]" class="form-control" style="font-size:13px;">
                            <option value="kiwify">Kiwify</option>
                            <option value="hotmart">Hotmart</option>
                            <option value="stripe">Stripe</option>
                            <option value="payt">Payt</option>
                            <option value="perfectpay">PerfectPay</option>
                            <option value="eduzz">Eduzz</option>
                            <option value="monetizze">Monetizze</option>
                            <option value="braip">Braip</option>
                            <option value="appmax">Appmax</option>
                            <option value="greenn">Greenn</option>
                            <option value="ticto">Ticto</option>
                            <option value="pepper">Pepper</option>
                            <option value="bynet">Bynet</option>
                            <option value="blackcat">BlackCat</option>
                            <option value="cartpanda">CartPanda</option>
                            <option value="yampi">Yampi</option>
                            <option value="shopify">Shopify</option>
                            <option value="nuvemshop">Nuvemshop</option>
                            <option value="doppus">Doppus</option>
                            <option value="hubla">Hubla</option>
                            <option value="lastlink">Lastlink</option>
                            <option value="guru">Guru</option>
                            <option value="pagbank">PagBank</option>
                            <option value="mercadopago">Mercado Pago</option>
                            <option value="pagarme">Pagar.me</option>
                            <option value="asaas">Asaas</option>
                            <option value="vindi">Vindi</option>
                            <option value="iugu">Iugu</option>
                            <option value="paypal">PayPal</option>
                        </select>
                        <input type="number" name="gw_percent[]" class="form-control gw-percent" value="10" min="1" max="100" style="font-size:13px;text-align:center;" oninput="updateGwTotals()">
                        <div style="text-align:center;"><span class="gw-paid-preview" style="color:#00d4aa;font-size:12px;font-weight:600;">R$ 0</span><div style="color:#8892b0;font-size:10px;">pagas</div></div>
                        <div style="text-align:center;"><span class="gw-pending-preview" style="color:#ffcc00;font-size:12px;font-weight:600;">R$ 0</span><div style="color:#8892b0;font-size:10px;">pend</div></div>
                        <button type="button" onclick="this.closest('.gateway-row').remove();updateGwTotals();" class="btn btn-danger" style="padding:6px 10px;font-size:11px;">X</button>
                    `;
                    list.appendChild(row);
                    updateGwTotals();
                }
                
                function updateCampTotals() {
                    const paidCount = parseInt(document.getElementById('fake_paid_count').value) || 0;
                    const pendingCount = parseInt(document.getElementById('fake_pending_count').value) || 0;
                    
                    let total = 0;
                    document.querySelectorAll('.campaign-row').forEach(row => {
                        const percent = parseInt(row.querySelector('.camp-percent').value) || 0;
                        total += percent;
                        
                        const paid = Math.round(paidCount * percent / 100);
                        const pending = Math.round(pendingCount * percent / 100);
                        
                        row.querySelector('.camp-paid-preview').textContent = paid;
                        row.querySelector('.camp-pending-preview').textContent = pending;
                    });
                    
                    const el = document.getElementById('camp-total-percent');
                    el.textContent = total + '%';
                    el.style.color = total === 100 ? '#00d4aa' : '#ef4444';
                }
                
                function updateGwTotals() {
                    const paidCount = parseInt(document.getElementById('fake_paid_count').value) || 0;
                    const pendingCount = parseInt(document.getElementById('fake_pending_count').value) || 0;
                    const avgTicket = parseFloat(document.getElementById('fake_avg_ticket').value) || 0;
                    
                    const totalPaid = paidCount * avgTicket;
                    const totalPending = pendingCount * avgTicket;
                    
                    let total = 0;
                    document.querySelectorAll('.gateway-row').forEach(row => {
                        const percent = parseInt(row.querySelector('.gw-percent').value) || 0;
                        total += percent;
                        
                        const paid = totalPaid * percent / 100;
                        const pending = totalPending * percent / 100;
                        
                        row.querySelector('.gw-paid-preview').textContent = 'R$ ' + paid.toLocaleString('pt-BR', {maximumFractionDigits: 0});
                        row.querySelector('.gw-pending-preview').textContent = 'R$ ' + pending.toLocaleString('pt-BR', {maximumFractionDigits: 0});
                    });
                    
                    const el = document.getElementById('gw-total-percent');
                    el.textContent = total + '%';
                    el.style.color = total === 100 ? '#00d4aa' : '#ef4444';
                }
                
                function calcularPreview() {
                    const paidCount = parseInt(document.getElementById('fake_paid_count').value) || 0;
                    const pendingCount = parseInt(document.getElementById('fake_pending_count').value) || 0;
                    const refundedCount = parseInt(document.getElementById('fake_refunded_count').value) || 0;
                    const avgTicket = parseFloat(document.getElementById('fake_avg_ticket').value) || 0;
                    
                    const totalPaid = paidCount * avgTicket;
                    const totalPending = pendingCount * avgTicket;
                    const totalRefunded = refundedCount * avgTicket;
                    
                    const fmt = (v) => 'R$ ' + v.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    
                    document.getElementById('preview-total-paid').textContent = fmt(totalPaid);
                    document.getElementById('preview-total-pending').textContent = fmt(totalPending);
                    document.getElementById('preview-total-refunded').textContent = fmt(totalRefunded);
                    
                    updateCampTotals();
                    updateGwTotals();
                }
                
                // Calcula ao carregar
                document.addEventListener('DOMContentLoaded', calcularPreview);
                </script>
            </div>
        </div>
        
        <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        </style>
        <?php endif; ?>
        </div>
        
        <!-- Modal Editar Aviso -->
    <div id="modal-editar-aviso" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:#1a1a2e; border-radius:16px; padding:24px; max-width:600px; width:90%; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="margin:0; color:#fff;">Editar Aviso</h2>
                <button onclick="fecharModalAviso()" style="background:none; border:none; color:#888; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            <form method="POST" id="form-editar-aviso">
                <input type="hidden" name="acao" value="editar_aviso">
                <input type="hidden" name="aviso_id" id="edit_aviso_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Titulo</label>
                        <input type="text" name="aviso_titulo" id="edit_aviso_titulo" required>
                    </div>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="aviso_tipo" id="edit_aviso_tipo">
                            <option value="info">Informacao (Azul)</option>
                            <option value="success">Sucesso (Verde)</option>
                            <option value="warning">Atencao (Amarelo)</option>
                            <option value="danger">Importante (Vermelho)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Conteudo</label>
                    <textarea name="aviso_conteudo" id="edit_aviso_conteudo" required rows="5" style="width:100%; padding:10px 14px; background:rgba(10,10,15,0.6); border:1px solid rgba(102,126,234,0.3); border-radius:8px; color:#fff; font-size:14px; resize:vertical;"></textarea>
                </div>
                
                <div style="display:flex; align-items:center; gap:16px; margin-top:16px;">
                    <div class="checkbox-group">
                        <input type="checkbox" name="aviso_ativo" id="edit_aviso_ativo">
                        <label for="edit_aviso_ativo">Ativo</label>
                    </div>
                    <div style="margin-left:auto; display:flex; gap:12px;">
                        <button type="button" onclick="fecharModalAviso()" class="btn btn-secondary">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Usuario -->
    <div id="modal-editar-usuario" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.8); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:#1a1a2e; border-radius:16px; padding:24px; max-width:500px; width:90%; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="margin:0; color:#fff;">Editar Usuario</h2>
                <button onclick="fecharModalUsuario()" style="background:none; border:none; color:#888; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            <form method="POST" id="form-editar-usuario">
                <input type="hidden" name="acao" value="editar_usuario">
                <input type="hidden" name="edit_email" id="edit_email">
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="text" id="edit_email_display" class="form-control" readonly style="background:#0a0a0f; cursor:default;">
                </div>
                
                <div class="form-group">
                    <label>Nome</label>
                    <input type="text" name="edit_nome" id="edit_nome" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Nova Senha (deixe vazio para manter)</label>
                    <input type="password" name="edit_senha" id="edit_senha" class="form-control" placeholder="••••••••">
                </div>
                
                <div class="form-group">
                    <label>Data de Expiracao</label>
                    <input type="date" name="edit_expira" id="edit_expira" class="form-control">
                    <div style="margin-top:8px; display:flex; gap:8px;">
                        <button type="button" onclick="adicionarDias(7)" class="btn btn-secondary" style="padding:6px 12px; font-size:12px;">+7 dias</button>
                        <button type="button" onclick="adicionarDias(30)" class="btn btn-secondary" style="padding:6px 12px; font-size:12px;">+30 dias</button>
                        <button type="button" onclick="adicionarDias(90)" class="btn btn-secondary" style="padding:6px 12px; font-size:12px;">+90 dias</button>
                        <button type="button" onclick="adicionarDias(365)" class="btn btn-secondary" style="padding:6px 12px; font-size:12px;">+1 ano</button>
                    </div>
                </div>
                
                <div class="form-row" style="margin-bottom:16px;">
                    <div class="checkbox-group">
                        <input type="checkbox" name="edit_admin" id="edit_admin">
                        <label for="edit_admin">Administrador</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="edit_cloaker" id="edit_cloaker">
                        <label for="edit_cloaker">Acesso UTM/Cloaker</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="edit_bloqueado" id="edit_bloqueado">
                        <label for="edit_bloqueado" style="color:#ef4444;">Bloqueado</label>
                    </div>
                </div>
                
                <div style="display:flex; gap:12px; justify-content:flex-end;">
                    <button type="button" onclick="fecharModalUsuario()" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alteracoes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function editarUsuario(email, nome, ehAdmin, cloaker, expira, bloqueado) {
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_email_display').value = email;
        document.getElementById('edit_nome').value = nome;
        document.getElementById('edit_senha').value = '';
        document.getElementById('edit_admin').checked = ehAdmin;
        document.getElementById('edit_cloaker').checked = cloaker;
        document.getElementById('edit_bloqueado').checked = bloqueado;
        document.getElementById('edit_expira').value = expira || '';
        document.getElementById('modal-editar-usuario').style.display = 'flex';
    }
    
    function fecharModalUsuario() {
        document.getElementById('modal-editar-usuario').style.display = 'none';
    }
    
    function adicionarDias(dias) {
        const input = document.getElementById('edit_expira');
        const hoje = new Date();
        // Se ja tem uma data, adiciona a partir dela
        const base = input.value ? new Date(input.value) : hoje;
        base.setDate(base.getDate() + dias);
        input.value = base.toISOString().split('T')[0];
    }
    
    // Fechar modal ao clicar fora
    document.getElementById('modal-editar-usuario').addEventListener('click', function(e) {
        if (e.target === this) fecharModalUsuario();
    });
    
    // Funcoes para Avisos
    function editarAviso(id, titulo, conteudo, tipo, ativo) {
        document.getElementById('edit_aviso_id').value = id;
        document.getElementById('edit_aviso_titulo').value = titulo;
        document.getElementById('edit_aviso_conteudo').value = conteudo;
        document.getElementById('edit_aviso_tipo').value = tipo;
        document.getElementById('edit_aviso_ativo').checked = ativo;
        document.getElementById('modal-editar-aviso').style.display = 'flex';
    }
    
    function fecharModalAviso() {
        document.getElementById('modal-editar-aviso').style.display = 'none';
    }
    
    // Fechar modal aviso ao clicar fora
    if (document.getElementById('modal-editar-aviso')) {
        document.getElementById('modal-editar-aviso').addEventListener('click', function(e) {
            if (e.target === this) fecharModalAviso();
        });
    }
    </script>
</body>
</html>
