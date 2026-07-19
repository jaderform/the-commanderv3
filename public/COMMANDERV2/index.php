<?php
/**
 * COMMANDER V10.3 - Dashboard Principal
 * 
 * Painel administrativo para gerenciamento de campanhas de cloaking
 * 
 * INSTRUÇÕES: Substitua o arquivo index.php existente por este
 */

// ============================================
// INTEGRAÇÃO COM SISTEMA DE LOGIN DA RAIZ
// ============================================

// Inclui o sistema de protecao da raiz
// Isso verifica se o usuario esta logado, se o dispositivo eh autorizado, etc.
// Caminho para o proteger.php na raiz (public_html)
require_once dirname(__DIR__) . '/proteger.php'; // ../proteger.php

// Agora temos acesso a $GLOBALS['usuario_logado'] com:
// - email, nome, eh_admin, utm_cloaker_ativo, expira_em

// Verifica se o usuario tem permissao para usar o COMMANDER
$usuario = $GLOBALS['usuario_logado'] ?? null;

if (!$usuario) {
    header('Location: /login.php?erro=1');
    exit;
}

// Verifica se tem permissao UTM/Cloaker (exceto admin)
if (!$usuario['eh_admin'] && !$usuario['utm_cloaker_ativo']) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Acesso Negado</title></head><body style="font-family:Inter,sans-serif;background:#0a0a0f;color:#fff;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;"><div style="text-align:center;"><h1 style="color:#ef4444;">Acesso Negado</h1><p>Voce nao tem permissao para acessar o COMMANDER.</p><p>Contate o administrador para ativar as funcoes PRO.</p><a href="/logout.php" style="color:#667eea;">Sair</a></div></body></html>';
    exit;
}

// Define constante de acesso
define('COMMANDER_ACCESS', true);

// Carrega dependências do COMMANDER
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/functions.php';

// Inicializa segurança do COMMANDER
$security = CommanderSecurity::getInstance();
$security->initSecurityHeaders();

// Usuario ja esta autenticado pelo proteger.php
$isLoggedIn = true;
$loginError = '';

// Obtém dados do usuário logado para filtrar campanhas
// Usa a variavel $usuario que já foi verificada acima (linha 23)
$usuarioLogado = $usuario;
$currentUserId = $usuarioLogado['email'] ?? 'default';
$isAdmin = $usuarioLogado['eh_admin'] ?? false;

// Processa logout - redireciona para logout da raiz
if (isset($_GET['logout'])) {
    header('Location: /logout.php');
    exit;
}

// Gera token CSRF
$csrfToken = $security->generateCSRFToken();

// ============================================
// AUTO-WHITELIST DO IP DO ADMIN PARA TESTES
// ============================================
// Quando o admin acessa o painel, seu IP é automaticamente adicionado à whitelist mascarado
// Assim ele consegue testar o cloaker normalmente
$adminIP = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if ($adminIP && strpos($adminIP, ',') !== false) {
    $adminIP = trim(explode(',', $adminIP)[0]);
}
if ($adminIP) {
    // Máscara o IP mostrando apenas os últimos 4 caracteres
    $lastChars = substr($adminIP, -4);
    $maskedIP = str_repeat('x', strlen($adminIP) - 4) . $lastChars;
    whitelistIP($adminIP, 'Admin Test - ' . $maskedIP);
}

// ============================================
// PROCESSAMENTO DE AÇÕES (AJAX)
// ============================================
// As funcoes de Analytics estao em functions.php
// ============================================

// ============================================
// PROCESSAMENTO DE ACOES AJAX
// ============================================

if ($isLoggedIn && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    // Valida CSRF
    if (!$security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['error' => 'Token invalido'], 403);
    }
    
    $action = $_POST['ajax_action'];
    
    switch ($action) {
        case 'save_campaign':
            $campaignData = [
                'id' => $_POST['campaign_id'] ?: generateCampaignId(),
                'user_id' => $currentUserId, // Associa campanha ao usuario
                'name' => $security->sanitizeInput($_POST['name'] ?? '', 'string'),
                'slug' => $security->sanitizeInput($_POST['slug'] ?? '', 'alphanumeric'),
                'white_url' => $security->sanitizeInput($_POST['white_url'] ?? '', 'url'),
                'black_url' => $security->sanitizeInput($_POST['black_url'] ?? '', 'url'),
                'white_method' => $_POST['white_method'] ?? 'redirect',
                'black_method' => $_POST['black_method'] ?? 'redirect',
                'platform' => $_POST['platform'] ?? 'other',
                'status' => $_POST['status'] ?? 'active',
                'warmup_clicks' => max(0, (int)($_POST['warmup_clicks'] ?? 0)),
                'allowed_countries' => $_POST['allowed_countries'] ?? '',
                // Horario de Pausa
                'schedule_enabled' => isset($_POST['schedule_enabled']) ? '1' : '0',
                'schedule_start' => $_POST['schedule_start'] ?? '23:30',
                'schedule_end' => $_POST['schedule_end'] ?? '06:00',
                'schedule_timezone' => $_POST['schedule_timezone'] ?? 'America/Sao_Paulo',
                'created_at' => $_POST['campaign_id'] ? null : date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Mantém created_at e user_id se for edição
            if ($_POST['campaign_id']) {
                $existing = getCampaignById($_POST['campaign_id']);
                if ($existing) {
                    $campaignData['created_at'] = $existing['created_at'];
                    $campaignData['user_id'] = $existing['user_id'] ?? $currentUserId;
                    
                    // Verifica se o usuario tem permissao para editar
                    if (!$isAdmin && ($existing['user_id'] ?? '') !== $currentUserId) {
                        jsonResponse(['error' => 'Sem permissao para editar esta campanha'], 403);
                    }
                }
            }
            
            if (empty($campaignData['name'])) {
                jsonResponse(['error' => 'Nome obrigatorio'], 400);
            }
            
            // Gera slug se vazio
            if (empty($campaignData['slug'])) {
                $existingSlugs = array_column(getCampaigns(), 'slug');
                $campaignData['slug'] = generateSlug($campaignData['name'], $existingSlugs);
            }
            
            saveCampaign($campaignData);
            jsonResponse(['success' => true, 'campaign' => $campaignData]);
            break;
            
        case 'delete_campaign':
            $id = $_POST['campaign_id'] ?? '';
            if ($id) {
                // Verifica permissao
                $campaign = getCampaignById($id);
                if ($campaign && !$isAdmin && ($campaign['user_id'] ?? '') !== $currentUserId) {
                    jsonResponse(['error' => 'Sem permissao para deletar esta campanha'], 403);
                }
                if (deleteCampaign($id)) {
                    jsonResponse(['success' => true]);
                }
            }
            jsonResponse(['error' => 'Falha ao deletar'], 400);
            break;
            
        case 'get_campaigns':
            $allCampaigns = getCampaigns();
            // Admin ve todas as campanhas, usuarios normais so as suas
            if ($isAdmin) {
                $userCampaigns = $allCampaigns;
            } else {
                $userCampaigns = array_filter($allCampaigns, function($c) use ($currentUserId) {
                    return ($c['user_id'] ?? 'default') === $currentUserId;
                });
                $userCampaigns = array_values($userCampaigns); // Reindexar
            }
            jsonResponse(['campaigns' => $userCampaigns]);
            break;
            
        case 'get_stats':
            $stats = getStats();
            // Filtra stats por campanhas do usuario (se nao for admin)
            if (!$isAdmin) {
                $userCampaignIds = array_column(array_filter(getCampaigns(), function($c) use ($currentUserId) {
                    return ($c['user_id'] ?? 'default') === $currentUserId;
                }), 'id');
                
                // Se usuario nao tem campanhas, retorna tudo zerado
                if (empty($userCampaignIds)) {
                    $stats = [
                        'total_clicks' => 0,
                        'total_blocks' => 0,
                        'total_passes' => 0,
                        'by_campaign' => [],
                        'by_date' => [],
                        'by_platform' => []
                    ];
                } else {
                    // Filtra by_campaign e recalcula totais
                    $filteredByCampaign = [];
                    $totalClicks = 0;
                    $totalBlocks = 0;
                    $totalPasses = 0;
                    
                    foreach ($stats['by_campaign'] ?? [] as $campId => $campStats) {
                        if (in_array($campId, $userCampaignIds)) {
                            $filteredByCampaign[$campId] = $campStats;
                            $totalClicks += $campStats['clicks'] ?? 0;
                            $totalBlocks += $campStats['blocks'] ?? 0;
                            $totalPasses += $campStats['passes'] ?? 0;
                        }
                    }
                    
                    $stats['by_campaign'] = $filteredByCampaign;
                    $stats['total_clicks'] = $totalClicks;
                    $stats['total_blocks'] = $totalBlocks;
                    $stats['total_passes'] = $totalPasses;
                    
                    // Zera by_date e by_platform para usuarios (dados globais)
                    // Idealmente deveria recalcular, mas requer mudanca na estrutura de dados
                    $stats['by_date'] = [];
                    $stats['by_platform'] = [];
                }
            }
            jsonResponse(['stats' => $stats]);
            break;
            
        case 'get_bot_logs':
            $limit = (int) ($_POST['limit'] ?? 100);
            $offset = (int) ($_POST['offset'] ?? 0);
            $logs = getBotLogs($limit * 2, $offset); // Pega mais pra filtrar
            
            // Filtra logs por campanhas do usuario (se nao for admin)
            if (!$isAdmin) {
                $userCampaignIds = array_column(array_filter(getCampaigns(), function($c) use ($currentUserId) {
                    return ($c['user_id'] ?? 'default') === $currentUserId;
                }), 'id');
                $logs = array_filter($logs, function($log) use ($userCampaignIds) {
                    return in_array($log['campaign_id'] ?? '', $userCampaignIds);
                });
                $logs = array_slice(array_values($logs), 0, $limit);
            }
            jsonResponse(['logs' => $logs]);
            break;
            
        case 'get_access_logs':
            // Busca logs de acesso para analytics
            $campaignSlug = $_POST['campaign_slug'] ?? '';
            $dateFrom = $_POST['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
            $dateTo = $_POST['date_to'] ?? date('Y-m-d');
            $limit = (int) ($_POST['limit'] ?? 500);
            
            $logs = getAccessLogs($campaignSlug, $dateFrom, $dateTo, $limit);
            
            // Filtra por campanhas do usuario
            if (!$isAdmin && $campaignSlug) {
                $campaign = getCampaignBySlug($campaignSlug);
                if ($campaign && ($campaign['user_id'] ?? '') !== $currentUserId) {
                    jsonResponse(['error' => 'Sem permissao'], 403);
                }
            }
            
            jsonResponse(['logs' => $logs]);
            break;
            
        case 'get_analytics':
            // Retorna analytics agregados
            $campaignSlug = $_POST['campaign_slug'] ?? '';
            $dateFrom = $_POST['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
            $dateTo = $_POST['date_to'] ?? date('Y-m-d');
            
            // Verifica permissao
            if (!$isAdmin && $campaignSlug) {
                $campaign = getCampaignBySlug($campaignSlug);
                if ($campaign && ($campaign['user_id'] ?? '') !== $currentUserId) {
                    jsonResponse(['error' => 'Sem permissao'], 403);
                }
            }
            
            $analytics = getAnalytics($campaignSlug, $dateFrom, $dateTo, $isAdmin, $currentUserId);
            jsonResponse(['analytics' => $analytics]);
            break;
            
        case 'save_access_log':
            // Recebe log de acesso do tracker (endpoint publico via curl)
            // Nota: Este endpoint tambem pode ser chamado via API externa
            $logData = json_decode(file_get_contents('php://input'), true);
            if (!$logData) {
                $logData = $_POST;
            }
            
            if (empty($logData['campaign_slug'])) {
                jsonResponse(['error' => 'campaign_slug obrigatorio'], 400);
            }
            
            saveAccessLog($logData);
            jsonResponse(['success' => true]);
            break;
            
        case 'block_ip':
            $ip = $security->sanitizeInput($_POST['ip'] ?? '', 'string');
            $reason = $security->sanitizeInput($_POST['reason'] ?? 'Manual', 'string');
            
            if (!$security->isValidIP($ip)) {
                jsonResponse(['error' => 'IP invalido'], 400);
            }
            
            blockIP($ip, $reason);
            jsonResponse(['success' => true]);
            break;
            
        case 'unblock_ip':
            $ip = $_POST['ip'] ?? '';
            unblockIP($ip);
            jsonResponse(['success' => true]);
            break;
            
        case 'whitelist_ip':
            $ip = $security->sanitizeInput($_POST['ip'] ?? '', 'string');
            $note = $security->sanitizeInput($_POST['note'] ?? '', 'string');
            
            if (!$security->isValidIP($ip)) {
                jsonResponse(['error' => 'IP invalido'], 400);
            }
            
            whitelistIP($ip, $note);
            jsonResponse(['success' => true]);
            break;
            
        case 'remove_whitelist':
            $ip = $_POST['ip'] ?? '';
            removeFromWhitelist($ip);
            jsonResponse(['success' => true]);
            break;
            
        case 'get_blocked_ips':
            jsonResponse(['ips' => getBlockedIPs()]);
            break;
            
        case 'get_whitelist_ips':
            jsonResponse(['ips' => getWhitelistIPs()]);
            break;
            
        case 'clean_logs':
            $days = (int) ($_POST['days'] ?? 30);
            cleanOldLogs($days);
            jsonResponse(['success' => true]);
            break;
            
        case 'download_tracker':
            $campaignId = $_POST['campaign_id'] ?? '';
            $campaign = getCampaignById($campaignId);
            
            if (!$campaign) {
                jsonResponse(['error' => 'Campanha nao encontrada'], 404);
            }
            
            // Verifica permissao
            if (!$isAdmin && ($campaign['user_id'] ?? '') !== $currentUserId) {
                jsonResponse(['error' => 'Sem permissao para baixar esta campanha'], 403);
            }
            
            $trackerCode = generateTrackerCode($campaign);
            jsonResponse(['code' => $trackerCode, 'filename' => 'index.php']);
            break;
            
        case 'download_htaccess':
            $htaccessCode = generateHtaccessCode();
            jsonResponse(['code' => $htaccessCode, 'filename' => '.htaccess']);
            break;
            
        default:
            jsonResponse(['error' => 'Acao invalida'], 400);
    }
    exit;
}

// ============================================
// FUNÇÕES DE GERAÇÃO DE CÓDIGO
// ============================================

function generateTrackerCode($campaign) {
    // Gera URL correta da API - sempre inclui /COMMANDERV2/
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // Pega o path atual e garante que inclui COMMANDERV2
    $currentPath = $_SERVER['REQUEST_URI'];
    
    // Se estiver em /COMMANDERV2/ ou /COMMANDERV2/index.php, usa esse path
    if (preg_match('#(/COMMANDERV2)#i', $currentPath, $matches)) {
        $basePath = $matches[1];
    } else {
        // Fallback - assume que esta na pasta COMMANDERV2
        $basePath = '/COMMANDERV2';
    }
    
    $apiUrl = $protocol . '://' . $host . $basePath . '/api.php';
    
    $slug = $campaign['slug'];
    $campaignName = addslashes($campaign['name']);
    $geradoEm = date('Y-m-d H:i:s');
    $whiteUrl = addslashes($campaign['white_url'] ?? '');
    $blackUrl = addslashes($campaign['black_url'] ?? '');
    
    // Horario de Pausa
    $scheduleEnabled = ($campaign['schedule_enabled'] ?? '0') === '1' ? 'true' : 'false';
    $scheduleStart = $campaign['schedule_start'] ?? '23:30';
    $scheduleEnd = $campaign['schedule_end'] ?? '06:00';
    $scheduleTimezone = $campaign['schedule_timezone'] ?? 'America/Sao_Paulo';
    
    return <<<PHP
<?php
/**
 * COMMANDER V11 - Cloaking Profissional
 * Campanha: {$campaignName}
 * Gerado em: {$geradoEm}
 * 
 * NOTA 10 - Sistema multi-camada para Google, TikTok e Facebook
 * 
 * INSTRUCOES:
 * 1. Faca upload deste arquivo (index.php) para a raiz do seu dominio
 * 2. Faca upload do .htaccess junto
 * 3. Configure a URL do anuncio para: https://seudominio.com/?utm_source=...
 * 
 * OPCIONAL (Deteccao Avancada):
 * - Faca upload do arquivo bot-detection-advanced.js para a raiz tambem
 * - Esse arquivo melhora a deteccao de bots headless, mas NAO E OBRIGATORIO
 */

// ==========================================
// CONFIGURACAO V11 - CLOAKING PROFISSIONAL
// ==========================================
\$API_URL = '{$apiUrl}';
\$CAMPAIGN_SLUG = '{$slug}';
\$WHITE_URL = '{$whiteUrl}';
\$BLACK_URL = '{$blackUrl}';
\$DEBUG_MODE = isset(\$_GET['debug']) && \$_GET['debug'] === '1';

// Configuracao de Horario de Pausa
\$SCHEDULE_ENABLED = {$scheduleEnabled};
\$SCHEDULE_START = '{$scheduleStart}';
\$SCHEDULE_END = '{$scheduleEnd}';
\$SCHEDULE_TIMEZONE = '{$scheduleTimezone}';

// ==========================================
// FUNCAO: Registra log de acesso para analytics
// ==========================================
function logAccess(\$result, \$reason = '', \$extraData = []) {
    global \$CAMPAIGN_SLUG, \$API_URL;
    
    \$logData = [
        'campaign_slug' => \$CAMPAIGN_SLUG,
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => \$_SERVER['HTTP_CF_CONNECTING_IP'] ?? \$_SERVER['HTTP_X_REAL_IP'] ?? \$_SERVER['HTTP_X_FORWARDED_FOR'] ?? \$_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => \$_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'referer' => \$_SERVER['HTTP_REFERER'] ?? '',
        'result' => \$result, // 'white', 'black', 'blocked', 'schedule_pause'
        'reason' => \$reason,
        'country' => \$extraData['country'] ?? '',
        'device' => \$extraData['device'] ?? '',
        'bot_score' => \$extraData['bot_score'] ?? 0,
        'bot_flags' => \$extraData['bot_flags'] ?? '',
        'url' => (isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . \$_SERVER['HTTP_HOST'] . \$_SERVER['REQUEST_URI']
    ];
    
    // Mascara o IP (mostra apenas ultimos 4 caracteres)
    if (\$logData['ip'] !== 'unknown') {
        \$logData['ip_masked'] = str_repeat('*', max(0, strlen(\$logData['ip']) - 4)) . substr(\$logData['ip'], -4);
    }
    
    // Envia log para API (async, nao bloqueia)
    try {
        \$ch = curl_init(\$API_URL . '?action=log');
        curl_setopt_array(\$ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(\$logData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 1, // Timeout curto para nao bloquear
            CURLOPT_CONNECTTIMEOUT => 1
        ]);
        curl_exec(\$ch);
        curl_close(\$ch);
    } catch (Exception \$e) {
        // Silenciosamente ignora erros de log
    }
    
    // Tambem salva localmente em arquivo (backup)
    \$logFile = __DIR__ . '/logs/' . date('Y-m-d') . '.log';
    \$logDir = dirname(\$logFile);
    if (!is_dir(\$logDir)) {
        @mkdir(\$logDir, 0755, true);
    }
    @file_put_contents(\$logFile, json_encode(\$logData) . "\\n", FILE_APPEND | LOCK_EX);
}

// ==========================================
// PROTECAO CONTRA REPLAY ATTACKS
// ==========================================
define('TOKENS_DIR', __DIR__ . '/tokens/');
if (!is_dir(TOKENS_DIR)) @mkdir(TOKENS_DIR, 0755, true);

function generateReplayToken(\$ttl = 300) {
    global \$CAMPAIGN_SLUG;
    \$token = bin2hex(random_bytes(32));
    \$tokenData = [
        'token' => \$token,
        'campaign' => \$CAMPAIGN_SLUG,
        'created_at' => time(),
        'expires_at' => time() + \$ttl,
        'ip_hash' => md5(\$_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        'ua_hash' => md5(\$_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
        'used' => false
    ];
    file_put_contents(TOKENS_DIR . \$token . '.json', json_encode(\$tokenData), LOCK_EX);
    if (rand(1, 100) === 1) cleanExpiredTokens();
    return \$tokenData;
}

function validateReplayToken(\$token, \$markAsUsed = true) {
    if (empty(\$token) || strlen(\$token) !== 64) return ['valid' => false, 'reason' => 'invalid_format'];
    \$tokenFile = TOKENS_DIR . \$token . '.json';
    if (!file_exists(\$tokenFile)) return ['valid' => false, 'reason' => 'not_found'];
    \$data = json_decode(file_get_contents(\$tokenFile), true);
    if (!\$data) { @unlink(\$tokenFile); return ['valid' => false, 'reason' => 'corrupted']; }
    if (time() > \$data['expires_at']) { @unlink(\$tokenFile); return ['valid' => false, 'reason' => 'expired']; }
    if (\$data['used']) { 
        @unlink(\$tokenFile); 
        logAccess('white', 'replay_attack_detected', ['is_replay' => true]);
        return ['valid' => false, 'reason' => 'already_used', 'is_replay_attack' => true]; 
    }
    \$ipHash = md5(\$_SERVER['REMOTE_ADDR'] ?? 'unknown');
    \$uaHash = md5(\$_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    if (\$data['ip_hash'] !== \$ipHash || \$data['ua_hash'] !== \$uaHash) {
        logAccess('white', 'replay_fingerprint_mismatch', ['is_replay' => true]);
        return ['valid' => false, 'reason' => 'fingerprint_mismatch', 'is_replay_attack' => true];
    }
    if (\$markAsUsed) {
        \$data['used'] = true;
        \$data['used_at'] = time();
        \$data['delete_after'] = time() + 60;
        file_put_contents(\$tokenFile, json_encode(\$data), LOCK_EX);
    }
    return ['valid' => true, 'campaign' => \$data['campaign'], 'age' => time() - \$data['created_at']];
}

function cleanExpiredTokens() {
    if (!is_dir(TOKENS_DIR)) return;
    foreach (glob(TOKENS_DIR . '*.json') as \$f) {
        \$d = @json_decode(@file_get_contents(\$f), true);
        if (!\$d || time() > (\$d['expires_at'] ?? 0) || ((\$d['delete_after'] ?? 0) > 0 && time() > \$d['delete_after'])) @unlink(\$f);
    }
}

// ==========================================
// FUNCAO: Verifica se esta no horario de pausa
// ==========================================
function isSchedulePaused() {
    global \$SCHEDULE_ENABLED, \$SCHEDULE_START, \$SCHEDULE_END, \$SCHEDULE_TIMEZONE;
    
    if (!\$SCHEDULE_ENABLED) {
        return false;
    }
    
    try {
        \$tz = new DateTimeZone(\$SCHEDULE_TIMEZONE);
        \$now = new DateTime('now', \$tz);
        \$currentTime = \$now->format('H:i');
        
        \$start = \$SCHEDULE_START;
        \$end = \$SCHEDULE_END;
        
        // Se o horario de fim e menor que o inicio, significa que atravessa a meia-noite
        // Exemplo: 23:30 ate 06:00
        if (\$end < \$start) {
            // Estamos no periodo de pausa se:
            // - Horario atual >= inicio (ex: 23:30, 23:45, 00:00...)
            // - OU horario atual < fim (ex: 00:00, 05:00, 05:59...)
            return (\$currentTime >= \$start || \$currentTime < \$end);
        } else {
            // Periodo normal (ex: 10:00 ate 18:00)
            return (\$currentTime >= \$start && \$currentTime < \$end);
        }
    } catch (Exception \$e) {
        return false;
    }
}

// ==========================================
// VERIFICACAO DE REPLAY TOKEN (ANTES DE TUDO)
// ==========================================
if (isset(\$_GET['_rt']) && !empty(\$_GET['_rt'])) {
    \$replayResult = validateReplayToken(\$_GET['_rt']);
    
    if (!\$replayResult['valid']) {
        // Token invalido - possivel replay attack
        logAccess('white', 'replay_attack_' . (\$replayResult['reason'] ?? 'unknown'), [
            'is_replay_attack' => \$replayResult['is_replay_attack'] ?? false
        ]);
        
        // Vai para WHITE
        header('Location: ' . \$WHITE_URL);
        exit;
    }
}

// ==========================================
// VERIFICACAO DE HORARIO DE PAUSA (ANTES DE TUDO)
// ==========================================
// Se estiver no horario de pausa, vai DIRETO para WHITE (sem verificacao)
if (isSchedulePaused()) {
    // Registra log
    logAccess('white', 'schedule_pause', ['reason_detail' => 'Horario de pausa ativo']);
    // Redireciona direto para WHITE sem passar pela API
    header('Location: ' . \$WHITE_URL);
    exit;
}

// ==========================================
// FASE 0: NAVEGACAO INTERNA DO PROXY COM SISTEMA DE PROFUNDIDADE
// ==========================================
// _bd=1 (primeiro clique): continua no proxy, vai para segunda pagina
// _bd>=2 (segundo clique): redireciona DIRETO para o site black real
if (isset(\$_GET['_nav']) && !empty(\$_GET['_nav'])) {
    \$navUrl = base64_decode(\$_GET['_nav']);
    \$depth = isset(\$_GET['_bd']) ? intval(\$_GET['_bd']) : 1;

    if (\$navUrl && filter_var(\$navUrl, FILTER_VALIDATE_URL)) {
        if (\$depth >= 2) {
            // Segundo clique: redireciona DIRETO para o site black real (sai do proxy)
            header('Location: ' . \$navUrl);
            exit;
        }
        // Primeiro clique: continua no proxy com depth incrementado
        proxyRequest(\$navUrl, true, \$depth + 1);
        exit;
    }
}

// Assets continuam via proxy normalmente
if (isset(\$_GET['_asset'])) {
    proxyRequest(\$WHITE_URL);
    exit;
}

// ==========================================
// FASE 1: DETECCAO JS (se ainda nao foi feita)
// ==========================================
// Se nao tem os dados de deteccao, mostra pagina de carregamento
// que coleta dados do navegador e reenvia via POST (URL limpa)
if (!isset(\$_POST['_detected'])) {
    \$currentUrl = (isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
                  . '://' . \$_SERVER['HTTP_HOST'] . \$_SERVER['REQUEST_URI'];
    
    // Pagina de carregamento estilo Cloudflare - simples e confiavel
    echo '<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Aguarde...</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f5f5f5;min-height:100vh;display:flex;justify-content:center;align-items:center}
.box{background:#fff;border-radius:4px;padding:30px 40px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,0.1);max-width:340px;width:90%}
.spinner{width:32px;height:32px;border:3px solid #e5e5e5;border-top-color:#f6821f;border-radius:50%;margin:0 auto 16px;animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.title{font-size:15px;color:#333;font-weight:500;margin-bottom:6px}
.sub{font-size:13px;color:#666}
</style>
</head><body>
<div class="box">
<div class="spinner"></div>
<div class="title">Verificando se o site é seguro</div>
<div class="sub">Isso levara apenas alguns segundos...</div>
</div>
<form id="f" method="POST" action="' . htmlspecialchars(\$currentUrl) . '">
<input type="hidden" name="_detected" value="1">
<input type="hidden" name="webdriver" id="wd">
<input type="hidden" name="plugins_count" id="pc">
<input type="hidden" name="languages" id="lg">
<input type="hidden" name="screen_width" id="sw">
<input type="hidden" name="screen_height" id="sh">
<input type="hidden" name="timezone" id="tz">
<input type="hidden" name="touch_support" id="ts">
<input type="hidden" name="platform" id="pf">
<input type="hidden" name="advanced_bot_score" id="abs">
<input type="hidden" name="advanced_bot_flags" id="abf">
</form>
<script src="/bot-detection-advanced.js" onerror=""></script>
<script>
(function(){
    // Envia formulario imediatamente com dados basicos
    // Deteccao avancada eh OPCIONAL - se arquivo nao existir, continua normalmente
    setTimeout(function(){
        var f=document.getElementById("f");
        f.wd.value=navigator.webdriver||window.navigator.webdriver?"1":"0";
        f.pc.value=navigator.plugins?navigator.plugins.length:0;
        f.lg.value=navigator.languages?navigator.languages.join(","):navigator.language||"";
        f.sw.value=screen.width||0;
        f.sh.value=screen.height||0;
        f.tz.value=Intl.DateTimeFormat().resolvedOptions().timeZone||"";
        f.ts.value=("ontouchstart"in window||navigator.maxTouchPoints>0)?"1":"0";
        f.pf.value=navigator.platform||"";
        
        // Deteccao avancada (se disponivel)
        if(window.advancedBotDetection){
            f.abs.value=window.advancedBotDetection.score||50;
            f.abf.value=window.advancedBotDetection.flags?window.advancedBotDetection.flags.join("|"):"";
        }
        f.submit();
    }, 150); // Pequeno delay para JS carregar
})();
</script>
<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars(\$WHITE_URL) . '"></noscript>
</body></html>';
    exit;
}

// ==========================================
// FASE 2: PROCESSAMENTO COM DADOS DE DETECCAO
// ==========================================

// Coleta dados do visitante
\$visitorIP = getVisitorIP();
\$visitorUA = \$_SERVER['HTTP_USER_AGENT'] ?? '';
\$referer = \$_SERVER['HTTP_REFERER'] ?? '';

// Detecta pais do visitante (Cloudflare, MaxMind, ou fallback)
\$visitorCountry = '';
if (!empty(\$_SERVER['HTTP_CF_IPCOUNTRY'])) {
    \$visitorCountry = \$_SERVER['HTTP_CF_IPCOUNTRY']; // Cloudflare
} elseif (!empty(\$_SERVER['HTTP_X_COUNTRY_CODE'])) {
    \$visitorCountry = \$_SERVER['HTTP_X_COUNTRY_CODE']; // Alguns proxies
} elseif (!empty(\$_SERVER['GEOIP_COUNTRY_CODE'])) {
    \$visitorCountry = \$_SERVER['GEOIP_COUNTRY_CODE']; // MaxMind GeoIP
}

// Detecta dispositivo basico
\$deviceType = 'Desktop';
if (preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', \$visitorUA)) {
    \$deviceType = preg_match('/iPad|Tablet/i', \$visitorUA) ? 'Tablet' : 'Mobile';
}

// UTMs
\$utmSource = \$_GET['utm_source'] ?? '';
\$utmMedium = \$_GET['utm_medium'] ?? '';
\$utmCampaign = \$_GET['utm_campaign'] ?? '';
\$utmContent = \$_GET['utm_content'] ?? '';
\$utmTerm = \$_GET['utm_term'] ?? '';

// Dados adicionais de plataformas
\$ttclid = \$_GET['ttclid'] ?? '';
\$gclid = \$_GET['gclid'] ?? '';
\$fbclid = \$_GET['fbclid'] ?? '';

// Dados de deteccao enviados pelo JS via POST
\$webdriver = \$_POST['webdriver'] ?? '';
\$pluginsCount = \$_POST['plugins_count'] ?? -1;
\$languages = \$_POST['languages'] ?? '';
\$screenWidth = \$_POST['screen_width'] ?? 0;
\$screenHeight = \$_POST['screen_height'] ?? 0;
\$timezone = \$_POST['timezone'] ?? '';
\$touchSupport = \$_POST['touch_support'] ?? '';
\$platform = \$_POST['platform'] ?? '';

// Dados de deteccao avancada (score de bot headless)
\$advancedBotScore = \$_POST['advanced_bot_score'] ?? '';
\$advancedBotFlags = \$_POST['advanced_bot_flags'] ?? '';

// Envia para API com todos os dados de deteccao
\$response = checkWithAPI(\$API_URL, [
    'action' => 'check',
    'campaign' => \$CAMPAIGN_SLUG,
    'ip' => \$visitorIP,
    'ua' => \$visitorUA,
    'referer' => \$referer,
    'utm_source' => \$utmSource,
    'utm_medium' => \$utmMedium,
    'utm_campaign' => \$utmCampaign,
    'utm_content' => \$utmContent,
    'utm_term' => \$utmTerm,
    'ttclid' => \$ttclid,
    'gclid' => \$gclid,
    'fbclid' => \$fbclid,
    // Dados de deteccao basica
    'webdriver' => \$webdriver,
    'plugins_count' => \$pluginsCount,
    'languages' => \$languages,
    'screen_width' => \$screenWidth,
    'screen_height' => \$screenHeight,
    'timezone' => \$timezone,
    'touch_support' => \$touchSupport,
    'platform' => \$platform,
    // Dados de deteccao AVANCADA (10 tecnicas)
    'advanced_bot_score' => \$advancedBotScore,
    'advanced_bot_flags' => \$advancedBotFlags
]);

if (\$DEBUG_MODE) {
    echo '<pre>DEBUG Response: '; print_r(\$response); echo '</pre>';
    exit;
}

// Processa resposta
if (\$response && isset(\$response['action'])) {
    
    // Registra log de acesso
    \$logResult = \$response['action']; // 'black' ou 'white'
    \$logReason = \$response['reason'] ?? (\$response['action'] === 'black' ? 'human_verified' : 'bot_detected');
    logAccess(\$logResult, \$logReason, [
        'country' => \$visitorCountry,
        'device' => \$deviceType,
        'bot_score' => \$advancedBotScore,
        'bot_flags' => \$advancedBotFlags
    ]);
    
    // Define URL e método de redirecionamento
    // CORRECAO V11.1: Garante que a URL correta seja usada para cada action
    if (\$response['action'] === 'black') {
        // Para BLACK: pega a URL da black page da resposta da API
        // Prioridade: url > redirect > fallback para BLACK_URL local
        \$targetUrl = \$response['url'] ?? \$response['redirect'] ?? \$BLACK_URL;
        \$method = \$response['method'] ?? 'redirect'; // redirect, proxy, meta_refresh, iframe
        
        // PROTECAO REPLAY ATTACK: Gera token unico para esta sessao
        \$replayToken = generateReplayToken(\$CAMPAIGN_SLUG);
        // Token pode ser usado em requisicoes subsequentes para validar
        
    } else {
        // Para WHITE: pega a URL da white page da resposta da API
        \$targetUrl = \$response['url'] ?? \$WHITE_URL;
        \$method = \$response['method'] ?? \$response['white_method'] ?? 'redirect';
    }
    
    // DEBUG: descomente para verificar a URL e action
    // error_log("COMMANDER DEBUG - action: " . \$response['action'] . " | method: " . \$method . " | targetUrl: " . \$targetUrl);
    
    // Aplica o metodo de redirecionamento
    switch (\$method) {
        case 'proxy':
            // CORRECAO V11.2: Proxy reverso com sistema de profundidade
            // Para BLACK: depth=1 (primeiro clique vai para segunda pagina)
            // Para WHITE: sem depth (nao usa o sistema de profundidade)
            if (empty(\$targetUrl)) {
                \$targetUrl = (\$response['action'] === 'black') ? \$BLACK_URL : \$WHITE_URL;
            }
            \$isBlack = (\$response['action'] === 'black');
            proxyRequest(\$targetUrl, \$isBlack, 1); // depth=1 para primeira pagina black
            break;
            
        case 'meta_refresh':
            // Meta refresh - redirecionamento via HTML
            echo '<!DOCTYPE html><html><head>';
            echo '<meta http-equiv=\"refresh\" content=\"0; url=' . htmlspecialchars(\$targetUrl) . '\">';
            echo '<script>window.location.href=\"' . htmlspecialchars(\$targetUrl) . '\";</script>';
            echo '</head><body></body></html>';
            exit;
            
        case 'redirect':
        default:
            // Redirect HTTP 302 (padrao)
            header('Location: ' . \$targetUrl);
            exit;
    }
    
} else {
    // Erro na API - vai para white page por seguranca
    header('Location: ' . \$WHITE_URL);
    exit;
}

// ============================================
// FUNCOES
// ============================================

function getVisitorIP() {
    \$headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach (\$headers as \$header) {
        if (!empty(\$_SERVER[\$header])) {
            \$ips = explode(',', \$_SERVER[\$header]);
            \$ip = trim(\$ips[0]);
            if (filter_var(\$ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return \$ip;
            }
        }
    }
    return \$_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function checkWithAPI(\$url, \$data) {
    \$ch = curl_init(\$url);
    curl_setopt_array(\$ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(\$data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json']
    ]);
    \$response = curl_exec(\$ch);
    curl_close(\$ch);
    return json_decode(\$response, true);
}

/**
 * Proxy Reverso V5 - COM PROTECAO DE BLACK PAGE
 * - Busca CSS externo e reescreve URLs internas (fontes, @import, url())
 * - Remove integrity/crossorigin que quebram recursos reescritos
 * - Suporta fontes CORS e cache de assets
 * - Intercepta fetch/XHR para requisicoes AJAX
 * - NOVO: Injeta Guardian na black page para detectar bots durante navegacao
 * 
 * @param string \$targetUrl URL de destino
 * @param bool \$isBlackPage Se true, injeta Guardian para protecao extra
 * @param string \$whiteUrl URL da white page para redirecionamento se bot detectado
 */
function proxyRequest(\$targetUrl, \$isBlackPage = false, \$depth = 1, \$whiteUrl = '') {
    // URL atual do proxy (dominio limpo)
    \$proxyHost = (isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . \$_SERVER['HTTP_HOST'];
    
    // Suporta navegacao interna via parametro _nav
    // Quando usuario clica em links dentro da pagina proxeada
    if (isset(\$_GET['_nav']) && !empty(\$_GET['_nav'])) {
        \$targetUrl = base64_decode(\$_GET['_nav']);
    }
    
    // ===========================================
    // BUSCA ASSETS (CSS, JS, FONTES, IMAGENS)
    // ===========================================
    if (isset(\$_GET['_asset']) && !empty(\$_GET['_asset'])) {
        \$assetUrl = base64_decode(\$_GET['_asset']);
        
        \$ch = curl_init(\$assetUrl);
        curl_setopt_array(\$ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => \$_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0',
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
                'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8'
            ]
        ]);
        
        \$assetContent = curl_exec(\$ch);
        \$assetHttpCode = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
        \$assetContentType = curl_getinfo(\$ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
        curl_close(\$ch);
        
        if (\$assetContent === false) {
            header('HTTP/1.1 502 Bad Gateway');
            exit;
        }
        
        // Se for CSS, reescreve URLs internas
        if (stripos(\$assetContentType, 'text/css') !== false || preg_match('/\\.css(\\?|$)/i', \$assetUrl)) {
            \$assetParsed = parse_url(\$assetUrl);
            \$assetBaseUrl = \$assetParsed['scheme'] . '://' . \$assetParsed['host'];
            \$assetBasePath = isset(\$assetParsed['path']) ? dirname(\$assetParsed['path']) : '';
            if (\$assetBasePath === '/' || \$assetBasePath === '.' || \$assetBasePath === '\\\\') \$assetBasePath = '';
            
            // Reescreve url() dentro do CSS
            \$assetContent = preg_replace_callback(
                '/url\\s*\\(\\s*[\"\\']?([^\"\\'\\)]+)[\"\\']?\\s*\\)/i',
                function(\$m) use (\$assetBaseUrl, \$assetBasePath, \$proxyHost) {
                    \$url = trim(\$m[1]);
                    if (preg_match('/^(data:|#)/i', \$url)) return \$m[0];
                    // Converte para absoluta
                    if (preg_match('/^https?:\\/\\//i', \$url)) {
                        \$absolute = \$url;
                    } elseif (strpos(\$url, '//') === 0) {
                        \$absolute = 'https:' . \$url;
                    } elseif (strpos(\$url, '/') === 0) {
                        \$absolute = \$assetBaseUrl . \$url;
                    } else {
                        \$absolute = \$assetBaseUrl . \$assetBasePath . '/' . \$url;
                    }
                    // Passa pelo proxy para fontes e imagens
                    return 'url("' . \$proxyHost . '/?_asset=' . base64_encode(\$absolute) . '")';
                },
                \$assetContent
            );
            
            // Reescreve @import
            \$assetContent = preg_replace_callback(
                '/@import\\s+[\"\\']([^\"\\']+)[\"\\'];?/i',
                function(\$m) use (\$assetBaseUrl, \$assetBasePath, \$proxyHost) {
                    \$url = trim(\$m[1]);
                    if (preg_match('/^https?:\\/\\//i', \$url)) {
                        \$absolute = \$url;
                    } elseif (strpos(\$url, '//') === 0) {
                        \$absolute = 'https:' . \$url;
                    } elseif (strpos(\$url, '/') === 0) {
                        \$absolute = \$assetBaseUrl . \$url;
                    } else {
                        \$absolute = \$assetBaseUrl . \$assetBasePath . '/' . \$url;
                    }
                    return '@import "' . \$proxyHost . '/?_asset=' . base64_encode(\$absolute) . '";';
                },
                \$assetContent
            );
            
            \$assetContentType = 'text/css; charset=UTF-8';
        }
        
        // Headers
        header('HTTP/1.1 ' . \$assetHttpCode);
        header('Content-Type: ' . \$assetContentType);
        header('Cache-Control: public, max-age=86400');
        header('Access-Control-Allow-Origin: *');
        
        echo \$assetContent;
        exit;
    }
    
    // ===========================================
    // BUSCA PAGINA HTML PRINCIPAL
    // ===========================================
    // CORRECAO V11.2: Verifica se o POST e da navegacao interna (formulario na pagina proxeada)
    // ou se e da fase de deteccao JS. No segundo caso, devemos fazer GET para nao enviar dados errados.
    // 
    // O POST da deteccao JS contem: _detected=1, webdriver=0, plugins_count=X, etc.
    // Esses dados NAO devem ser enviados para a BLACK page!
    //
    // Identificamos POST de deteccao se:
    // 1. Nao tem _nav na URL (navegacao interna sempre usa _nav)
    // 2. Tem _detected no POST
    \$isNavigationPost = isset(\$_GET['_nav']) && \$_SERVER['REQUEST_METHOD'] === 'POST';
    \$isDetectionPost = isset(\$_POST['_detected']);
    
    // So faz POST real se for navegacao interna (formulario na black page), nao deteccao
    \$isPost = \$isNavigationPost && !\$isDetectionPost;
    \$postData = \$isPost ? file_get_contents('php://input') : null;
    
    \$ch = curl_init(\$targetUrl);
    \$curlOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => \$_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0',
        CURLOPT_HTTPHEADER => [
            'X-Forwarded-For: ' . getVisitorIP(),
            'X-Forwarded-Proto: https',
            'Referer: ' . \$targetUrl,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8'
        ]
    ];
    
    if (\$isPost) {
        \$curlOpts[CURLOPT_POST] = true;
        \$curlOpts[CURLOPT_POSTFIELDS] = \$postData;
        \$curlOpts[CURLOPT_HTTPHEADER][] = 'Content-Type: ' . (\$_SERVER['CONTENT_TYPE'] ?? 'application/x-www-form-urlencoded');
    }
    
    curl_setopt_array(\$ch, \$curlOpts);
    
    \$response = curl_exec(\$ch);
    \$finalUrl = curl_getinfo(\$ch, CURLINFO_EFFECTIVE_URL);
    \$httpCode = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
    \$contentType = curl_getinfo(\$ch, CURLINFO_CONTENT_TYPE);
    curl_close(\$ch);
    
    if (\$response === false) {
        header('HTTP/1.1 503 Service Unavailable');
        echo 'Erro ao conectar ao servidor remoto';
        exit;
    }
    
    // Se nao for HTML, retorna diretamente
    if (stripos(\$contentType, 'text/html') === false && stripos(\$contentType, 'application/xhtml') === false) {
        header('HTTP/1.1 ' . \$httpCode);
        header('Content-Type: ' . \$contentType);
        header('Cache-Control: public, max-age=86400');
        echo \$response;
        exit;
    }
    
    // Extrai base URL do destino
    \$parsed = parse_url(\$finalUrl);
    \$baseUrl = \$parsed['scheme'] . '://' . \$parsed['host'];
    \$basePath = isset(\$parsed['path']) ? dirname(\$parsed['path']) : '';
    if (\$basePath === '/' || \$basePath === '\\\\' || \$basePath === '.') {
        \$basePath = '';
    }
    
    // Funcao para converter URL relativa em absoluta
    \$makeAbsolute = function(\$url) use (\$baseUrl, \$basePath) {
        \$url = trim(\$url);
        if (preg_match('/^https?:\\/\\//i', \$url)) return \$url;
        if (preg_match('/^(data:|javascript:|mailto:|tel:|#)/i', \$url)) return \$url;
        if (strpos(\$url, '//') === 0) return 'https:' . \$url;
        if (strpos(\$url, '/') === 0) return \$baseUrl . \$url;
        return \$baseUrl . \$basePath . '/' . \$url;
    };
    
    // Funcao para criar link do proxy (URL limpa) com depth
    \$makeProxyLink = function(\$url) use (\$proxyHost, \$makeAbsolute, \$depth) {
        \$url = trim(\$url);
        if (preg_match('/^(javascript:|mailto:|tel:|#|data:)/i', \$url)) {
            return \$url;
        }
        \$absolute = \$makeAbsolute(\$url);
        return \$proxyHost . '/?_nav=' . base64_encode(\$absolute) . '&_bd=' . \$depth;
    };
    
    // Funcao para criar URL de asset via proxy
    \$makeAssetProxy = function(\$url) use (\$proxyHost, \$makeAbsolute) {
        \$url = trim(\$url);
        if (preg_match('/^(data:|javascript:|mailto:|tel:|#)/i', \$url)) {
            return \$url;
        }
        \$absolute = \$makeAbsolute(\$url);
        return \$proxyHost . '/?_asset=' . base64_encode(\$absolute);
    };
    
    // =============================================
    // REMOVE INTEGRITY E CROSSORIGIN (quebram CSS/JS reescritos)
    // =============================================
    \$response = preg_replace('/\\s+integrity=[\"\\'][^\"\\']*[\"\\']/i', '', \$response);
    \$response = preg_replace('/\\s+crossorigin(?:=[\"\\'][^\"\\']*[\"\\'"])?/i', '', \$response);
    
    // =============================================
    // REESCREVE CSS VIA PROXY (para corrigir fontes e @import)
    // =============================================
    \$response = preg_replace_callback(
        '/<link([^>]*)(href=[\"\\']([^\"\\']+)[\"\\'])([^>]*)>/is',
        function(\$m) use (\$makeAssetProxy) {
            \$attrs = \$m[1] . \$m[4];
            // Verifica se eh stylesheet
            if (stripos(\$attrs, 'stylesheet') !== false || preg_match('/\\.css(\\?|$)/i', \$m[3])) {
                return '<link' . \$m[1] . 'href="' . \$makeAssetProxy(\$m[3]) . '"' . \$m[4] . '>';
            }
            // Outros links (favicon, preload, etc) - URL absoluta direta
            return '<link' . \$m[1] . 'href="' . \$makeAssetProxy(\$m[3]) . '"' . \$m[4] . '>';
        },
        \$response
    );
    
    // Scripts: <script src="...">
    \$response = preg_replace_callback(
        '/<script([^>]*)src=[\"\\']([^\"\\']+)[\"\\']([^>]*)>/is',
        function(\$m) use (\$makeAbsolute) {
            return '<script' . \$m[1] . 'src="' . \$makeAbsolute(\$m[2]) . '"' . \$m[3] . '>';
        },
        \$response
    );
    
    // Imagens: <img src="...">
    \$response = preg_replace_callback(
        '/<img([^>]*)src=[\"\\']([^\"\\']+)[\"\\']([^>]*)>/is',
        function(\$m) use (\$makeAbsolute) {
            return '<img' . \$m[1] . 'src="' . \$makeAbsolute(\$m[2]) . '"' . \$m[3] . '>';
        },
        \$response
    );
    
    // Videos/Audio: <video src="...">, <source src="...">, <audio src="...">
    \$response = preg_replace_callback(
        '/<(video|source|audio)([^>]*)src=[\"\\']([^\"\\']+)[\"\\']([^>]*)>/is',
        function(\$m) use (\$makeAbsolute) {
            return '<' . \$m[1] . \$m[2] . 'src="' . \$makeAbsolute(\$m[3]) . '"' . \$m[4] . '>';
        },
        \$response
    );
    
    // Poster de video
    \$response = preg_replace_callback(
        '/poster=[\"\\']([^\"\\']+)[\"\\']/is',
        function(\$m) use (\$makeAbsolute) {
            return 'poster="' . \$makeAbsolute(\$m[1]) . '"';
        },
        \$response
    );
    
    // Background em style inline: url(...)
    \$response = preg_replace_callback(
        '/(style=[\"\\'][^\"\\']*?)url\\s*\\(\\s*[\"\\']?([^\"\\'\\)]+)[\"\\']?\\s*\\)/is',
        function(\$m) use (\$makeAbsolute) {
            \$url = trim(\$m[2]);
            if (preg_match('/^(data:|#)/i', \$url)) return \$m[0];
            return \$m[1] . 'url("' . \$makeAbsolute(\$url) . '")';
        },
        \$response
    );
    
    // Srcset
    \$response = preg_replace_callback(
        '/srcset=[\"\\']([^\"\\']+)[\"\\']/is',
        function(\$m) use (\$makeAbsolute) {
            \$srcset = \$m[1];
            \$parts = preg_split('/\\s*,\\s*/', \$srcset);
            \$newParts = [];
            foreach (\$parts as \$part) {
                if (preg_match('/^(.+?)(\\s+\\d+[wx])?\$/i', trim(\$part), \$pm)) {
                    \$newParts[] = \$makeAbsolute(\$pm[1]) . (\$pm[2] ?? '');
                }
            }
            return 'srcset="' . implode(', ', \$newParts) . '"';
        },
        \$response
    );
    
    // Data-src, data-bg, etc (lazy loading)
    \$response = preg_replace_callback(
        '/data-(?:src|bg|background|lazy-src|original|image)=[\"\\']([^\"\\']+)[\"\\']/is',
        function(\$m) use (\$makeAbsolute) {
            return 'data-src="' . \$makeAbsolute(\$m[1]) . '"';
        },
        \$response
    );
    
    // =============================================
    // REESCREVE LINKS <a href> PARA PROXY (URL LIMPA)
    // =============================================
    \$response = preg_replace_callback(
        '/<a([^>]*)href=[\"\\']([^\"\\']+)[\"\\']([^>]*)>/is',
        function(\$m) use (\$makeProxyLink, \$baseUrl) {
            \$href = trim(\$m[2]);
            // Links externos (outros dominios) - abre direto
            if (preg_match('/^https?:\\/\\//i', \$href) && strpos(\$href, \$baseUrl) !== 0) {
                return \$m[0];
            }
            return '<a' . \$m[1] . 'href="' . \$makeProxyLink(\$href) . '"' . \$m[3] . '>';
        },
        \$response
    );
    
    // =============================================
    // REESCREVE FORMULARIOS PARA PROXY
    // =============================================
    \$response = preg_replace_callback(
        '/<form([^>]*)action=[\"\\']([^\"\\']*)[\"\\']([^>]*)>/is',
        function(\$m) use (\$makeProxyLink) {
            \$action = trim(\$m[2]);
            return '<form' . \$m[1] . 'action="' . \$makeProxyLink(\$action) . '"' . \$m[3] . '>';
        },
        \$response
    );
    
    // =============================================
    // ADICIONA TAG <BASE> PARA RESOLVER RESTANTES
    // =============================================
    \$baseTag = '<base href="' . \$baseUrl . \$basePath . '/">';
    if (stripos(\$response, '<head') !== false && stripos(\$response, '<base') === false) {
        \$response = preg_replace('/(<head[^>]*>)/i', '\$1' . "\\n" . \$baseTag, \$response, 1);
    }
    
    // =============================================
    // SCRIPT PARA INTERCEPTAR NAVEGACAO DINAMICA
    // depth=1: primeiro clique continua via proxy
    // depth>=2: segundo clique redireciona DIRETO para site real
    // =============================================
    \$shouldRedirectDirect = (\$depth >= 2) ? 'true' : 'false';
    \$currentUrl = \$targetUrl;
    
    \$proxyScript = '<script>
    (function(){
        var proxyBase = "' . \$proxyHost . '/?_nav=";
        var assetBase = "' . \$proxyHost . '/?_asset=";
        var targetBase = "' . \$baseUrl . '";
        var currentUrl = "' . addslashes(\$currentUrl) . '";
        var depthSuffix = "&_bd=' . (\$depth + 1) . '";
        var shouldRedirectDirect = ' . \$shouldRedirectDirect . ';
        
        // Intercepta cliques em links E botoes
        document.addEventListener("click", function(e) {
            // Verifica se clicou em botao
            var btn = e.target.closest("button, input[type=submit], input[type=button], [role=button]");
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                if (shouldRedirectDirect) {
                    // depth>=2: redireciona DIRETO para o site real
                    window.location.href = currentUrl;
                } else {
                    // depth=1: continua via proxy com depth incrementado
                    window.location.href = proxyBase + btoa(currentUrl) + depthSuffix;
                }
                return false;
            }
            
            // Verifica se clicou em link
            var link = e.target.closest("a");
            if (!link) return;
            
            var href = link.getAttribute("href");
            if (!href) return;
            
            if (/^(javascript:|mailto:|tel:|#|data:)/i.test(href)) return;
            if (href.indexOf("_nav=") !== -1) return;
            if (/^https?:\\/\\//i.test(href) && href.indexOf(targetBase) !== 0) return;
            
            var absolute = href;
            if (!/^https?:\\/\\//i.test(href)) {
                if (href.charAt(0) === "/") {
                    absolute = targetBase + href;
                } else {
                    absolute = targetBase + "/" + href;
                }
            }
            
            e.preventDefault();
            if (shouldRedirectDirect) {
                // depth>=2: redireciona DIRETO para o site real
                window.location.href = absolute;
            } else {
                // depth=1: continua via proxy com depth incrementado
                window.location.href = proxyBase + btoa(absolute) + depthSuffix;
            }
        }, true);
        
        // Intercepta envio de formularios
        document.addEventListener("submit", function(e) {
            var form = e.target;
            var action = form.getAttribute("action") || window.location.pathname;
            
            if (action.indexOf("_nav=") !== -1) return;
            
            var absolute = action;
            if (!/^https?:\\/\\//i.test(action)) {
                if (action.charAt(0) === "/") {
                    absolute = targetBase + action;
                } else {
                    absolute = targetBase + "/" + action;
                }
            }
            
            if (shouldRedirectDirect) {
                // depth>=2: form vai direto para site real
                form.setAttribute("action", absolute);
            } else {
                // depth=1: form continua via proxy
                form.setAttribute("action", proxyBase + btoa(absolute) + depthSuffix);
            }
        }, true);
        
        // Intercepta fetch e XHR para APIs internas (apenas quando nao redireciona direto)
        if (!shouldRedirectDirect) {
            var originalFetch = window.fetch;
            window.fetch = function(url, options) {
                if (typeof url === "string" && !url.startsWith("data:") && !url.startsWith("blob:")) {
                    if (url.indexOf("_nav=") === -1 && url.indexOf("_asset=") === -1) {
                        var absolute = url;
                        if (!/^https?:\\/\\//i.test(url)) {
                            if (url.charAt(0) === "/") {
                                absolute = targetBase + url;
                            } else {
                                absolute = targetBase + "/" + url;
                            }
                        }
                        if (absolute.indexOf(targetBase) === 0) {
                            url = assetBase + btoa(absolute);
                        }
                    }
                }
                return originalFetch.call(this, url, options);
            };
        }
        
        // Corrige historico do navegador
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, "' . \$proxyHost . '/");
        }
    })();
    </script>';
    
    // =============================================
    // GUARDIAN - PROTECAO EXTRA PARA BLACK PAGE
    // Se detectar bot durante navegacao, redireciona para white
    // =============================================
    \$guardianScript = '';
    if (\$isBlackPage && !empty(\$whiteUrl)) {
        \$guardianScript = '<script>
(function(){
    "use strict";
    var W="' . addslashes(\$whiteUrl) . '",S=0,T=Date.now(),R=false;
    function redir(r){if(R)return;R=true;try{location.replace(W)}catch(e){location.href=W}}
    function add(p,r){S+=p;if(Date.now()-T>2000&&S>=40)redir(r)}
    
    // Webdriver
    if(navigator.webdriver===true)add(50,"webdriver");
    
    // Headless
    if(/HeadlessChrome/i.test(navigator.userAgent))add(40,"headless");
    
    // Automation
    if(window.__puppeteer_evaluation_script__||window.puppeteer||window.__playwright||window.__selenium_unwrapped||window.__webdriver_evaluate||window.callPhantom||window._phantom||window.__nightmare)add(50,"automation");
    
    // Plugins
    if(!/Android|iPhone|iPad/i.test(navigator.userAgent)&&navigator.plugins.length===0)add(20,"no_plugins");
    
    // Languages
    if(!navigator.languages||navigator.languages.length===0)add(15,"no_lang");
    
    // SwiftShader
    try{var c=document.createElement("canvas"),g=c.getContext("webgl");if(g){var d=g.getExtension("WEBGL_debug_renderer_info");if(d&&/swiftshader|llvmpipe/i.test(g.getParameter(d.UNMASKED_RENDERER_WEBGL)))add(40,"fake_gpu")}}catch(e){}
    
    // Untrusted events
    var uc=0;
    function chk(e){if(e.isTrusted===false){uc++;if(uc>=3)add(25,"untrusted")}}
    document.addEventListener("mousemove",chk,{passive:true});
    document.addEventListener("click",chk,{passive:true});
    
    // Click speed
    var ct=[];
    document.addEventListener("click",function(){
        var n=Date.now();ct.push(n);if(ct.length>5)ct.shift();
        if(ct.length>=2&&ct[ct.length-1]-ct[ct.length-2]<80)add(35,"fast_click");
    },{passive:true});
    
    // Honeypot
    setTimeout(function(){
        var a=document.createElement("a");a.href="#hp";a.style.cssText="position:fixed;left:-9999px;opacity:0;pointer-events:auto";
        a.onclick=function(e){e.preventDefault();add(50,"honeypot")};document.body.appendChild(a);
    },500);
    
    // Final check
    setTimeout(function(){if(S>=40)redir("final")},5000);
})();
</script>';
    }
    
    // Injeta scripts antes de </body>
    \$allScripts = \$guardianScript . \$proxyScript;
    if (stripos(\$response, '</body>') !== false) {
        \$response = str_ireplace('</body>', \$allScripts . '</body>', \$response);
    } else {
        \$response .= \$allScripts;
    }
    
    // =============================================
    // HEADERS
    // =============================================
    header('HTTP/1.1 ' . \$httpCode);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    header_remove('Set-Cookie');
    header_remove('Transfer-Encoding');
    
    echo \$response;
    exit;
}
PHP;
}

function generateHtaccessCode() {
    return <<<HTACCESS
# COMMANDER V10.3 - Tracker .htaccess
RewriteEngine On
RewriteBase /

# Força HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Remove trailing slash
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} (.+)/$
RewriteRule ^ %1 [L,R=301]

# REGRA PRINCIPAL - Roteia TUDO para index.php (exceto arquivos existentes)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Bloqueia acesso a arquivos sensíveis
<FilesMatch "\.(json|log|bak|config|sql)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Protege diretórios
Options -Indexes

# Headers de segurança
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Compressão
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css application/javascript application/json
</IfModule>

# Cache de arquivos estáticos
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/webp "access plus 1 month"
    ExpiresByType text/css "access plus 1 week"
    ExpiresByType application/javascript "access plus 1 week"
</IfModule>
HTACCESS;
}

// ============================================
// INTERFACE HTML
// ============================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>COMMANDER V<?php echo COMMANDER_VERSION; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #ff0050;
            --primary-dark: #d90045;
            --primary-light: #ff3371;
            --secondary: #00f2ea;
            --success: #00d4aa;
            --warning: #ffcc00;
            --danger: #ff0050;
            --info: #00f2ea;
            --dark: #121212;
            --darker: #0a0a0a;
            --darkest: #000000;
            --light: #ffffff;
            --muted: #888888;
            --surface: #1a1a1a;
            --overlay: #252525;
            --primary-alpha: rgba(255, 0, 80, 0.15);
            --secondary-alpha: rgba(0, 242, 234, 0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--darkest);
            color: var(--light);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        /* Login Page */
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            background: linear-gradient(135deg, var(--darkest) 0%, var(--darker) 100%);
        }
        
        .login-box {
            background: var(--dark);
            padding: 40px;
            border-radius: 16px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid var(--surface);
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .login-logo span {
            font-size: 12px;
            color: var(--muted);
            display: block;
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--light);
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: var(--surface);
            border: 1px solid var(--overlay);
            border-radius: 8px;
            color: var(--light);
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 0, 80, 0.25);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 0, 80, 0.4);
        }
        
        .btn-secondary {
            background: var(--surface);
            color: var(--light);
        }
        
        .btn-secondary:hover {
            background: var(--overlay);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }
        
        /* Dashboard Layout */
        .dashboard {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--darker);
            border-right: 1px solid var(--surface);
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--surface);
            margin-bottom: 20px;
        }
        
        .sidebar-header h1 {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .sidebar-header span {
            font-size: 11px;
            color: var(--muted);
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-item {
            margin: 4px 12px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--muted);
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background: var(--surface);
            color: var(--light);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, rgba(255, 0, 80, 0.2) 0%, rgba(255, 0, 80, 0.1) 100%);
            color: var(--primary);
            border-left: 3px solid var(--primary);
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--light);
        }
        
        .page-subtitle {
            font-size: 14px;
            color: var(--muted);
            margin-top: 5px;
        }
        
        /* Cards */
        .card {
            background: var(--dark);
            border: 1px solid var(--surface);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: 0 0 20px rgba(255, 0, 80, 0.1);
        }
        
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--surface);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--light);
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--dark);
            border: 1px solid var(--surface);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .stat-icon.purple { background: rgba(255, 0, 80, 0.15); color: #ff0050; }
        .stat-icon.green { background: rgba(0, 212, 170, 0.15); color: #00d4aa; }
        .stat-icon.red { background: rgba(255, 0, 80, 0.15); color: #ff0050; }
        .stat-icon.blue { background: rgba(0, 242, 234, 0.15); color: #00f2ea; }
        .stat-icon.yellow { background: rgba(255, 204, 0, 0.15); color: #ffcc00; }
        
        .stat-info h3 {
            font-size: 24px;
            font-weight: 700;
            color: var(--light);
            margin-bottom: 4px;
        }
        
        .stat-info p {
            font-size: 13px;
            color: var(--muted);
        }
        
        /* Table */
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--surface);
        }
        
        th {
            font-weight: 600;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            font-size: 14px;
            color: var(--light);
        }
        
        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }
        
        /* Badge */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .badge-info { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        
        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        
        .modal {
            background: var(--dark);
            border: 1px solid var(--surface);
            border-radius: 16px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.9);
            transition: all 0.3s ease;
        }
        
        .modal-overlay.show .modal {
            transform: scale(1);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--surface);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--muted);
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }
        
        .modal-close:hover {
            color: var(--light);
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--surface);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        /* Code Block */
        .code-block {
            background: var(--darkest);
            border: 1px solid var(--surface);
            border-radius: 8px;
            padding: 16px;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 13px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 400px;
            overflow-y: auto;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            gap: 4px;
            border-bottom: 1px solid var(--surface);
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 12px 20px;
            background: none;
            border: none;
            color: var(--muted);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
        }
        
        .tab:hover {
            color: var(--light);
        }
        
        .tab.active {
            color: var(--primary);
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Grid */
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        /* Actions */
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: none;
            background: var(--surface);
            color: var(--muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .action-btn:hover {
            background: var(--overlay);
            color: var(--light);
        }
        
        .action-btn.danger:hover {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            font-size: 18px;
            color: var(--light);
            margin-bottom: 8px;
        }
        
        /* Toast */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .toast {
            background: var(--dark);
            border: 1px solid var(--surface);
            border-radius: 8px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .toast.success { border-left: 3px solid var(--success); }
        .toast.error { border-left: 3px solid var(--danger); }
        .toast.warning { border-left: 3px solid var(--warning); }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
        
        /* Loading */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--surface);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Select */
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236c7086' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }
        
        /* Platform badges */
        .platform-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .platform-tiktok { background: rgba(0, 0, 0, 0.3); color: #ff0050; }
        .platform-facebook { background: rgba(24, 119, 242, 0.15); color: #1877f2; }
        .platform-google { background: rgba(66, 133, 244, 0.15); color: #4285f4; }
        .platform-kwai { background: rgba(255, 136, 0, 0.15); color: #ff8800; }
        .platform-other { background: var(--surface); color: var(--muted); }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
<!-- LOGIN PAGE -->
<div class="login-container">
    <div class="login-box">
        <div class="login-logo">
            <h1>COMMANDER</h1>
            <span>v<?php echo COMMANDER_VERSION; ?> - Sistema de Cloaking</span>
        </div>
        
        <?php if ($loginError): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($loginError); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <div class="form-group">
                <label for="password">Senha de Acesso</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Digite sua senha" required autofocus>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- DASHBOARD -->
<div class="dashboard">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1>COMMANDER</h1>
            <span>v<?php echo COMMANDER_VERSION; ?></span>
        </div>
        
        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="#dashboard" class="nav-link active" data-page="dashboard">
                        <i class="fas fa-chart-line"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#campaigns" class="nav-link" data-page="campaigns">
                        <i class="fas fa-bullhorn"></i> Campanhas
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#analytics" class="nav-link" data-page="analytics">
                        <i class="fas fa-chart-bar"></i> Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#logs" class="nav-link" data-page="logs">
                        <i class="fas fa-list-alt"></i> Logs de Bots
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#ips" class="nav-link" data-page="ips">
                        <i class="fas fa-shield-alt"></i> Gerenciar IPs
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#downloads" class="nav-link" data-page="downloads">
                        <i class="fas fa-download"></i> Downloads
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?logout=1" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </li>
            </ul>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div id="toast-container" class="toast-container"></div>
        
        <!-- Avisos do Sistema -->
        <?php
        // Arquivo de avisos fica na raiz (um nivel acima do COMMANDERV2)
        $arquivoAvisos = dirname(__DIR__) . '/avisos_sistema.json';
        $avisosAtivos = [];
        if (file_exists($arquivoAvisos)) {
            $todosAvisos = json_decode(file_get_contents($arquivoAvisos), true) ?: [];
            $avisosAtivos = array_filter($todosAvisos, function($a) { return $a['ativo'] ?? false; });
        }
        if (!empty($avisosAtivos)):
        ?>
        <div id="avisos-sistema" style="padding: 20px 20px 0 20px;">
            <?php foreach (array_reverse($avisosAtivos) as $aviso): 
                $cores = [
                    'info' => ['bg' => 'rgba(0, 242, 234, 0.1)', 'border' => 'rgba(0, 242, 234, 0.3)', 'text' => '#00f2ea', 'icon' => 'fa-info-circle'],
                    'success' => ['bg' => 'rgba(0, 212, 170, 0.1)', 'border' => 'rgba(0, 212, 170, 0.3)', 'text' => '#00d4aa', 'icon' => 'fa-check-circle'],
                    'warning' => ['bg' => 'rgba(255, 204, 0, 0.1)', 'border' => 'rgba(255, 204, 0, 0.3)', 'text' => '#ffcc00', 'icon' => 'fa-exclamation-triangle'],
                    'danger' => ['bg' => 'rgba(255, 0, 80, 0.1)', 'border' => 'rgba(255, 0, 80, 0.3)', 'text' => '#ff0050', 'icon' => 'fa-exclamation-circle']
                ];
                $cor = $cores[$aviso['tipo']] ?? $cores['info'];
            ?>
            <div class="aviso-item" style="background: <?php echo $cor['bg']; ?>; border: 1px solid <?php echo $cor['border']; ?>; border-radius: 12px; padding: 16px 20px; margin-bottom: 12px; display: flex; gap: 16px; align-items: flex-start;">
                <i class="fas <?php echo $cor['icon']; ?>" style="color: <?php echo $cor['text']; ?>; font-size: 20px; margin-top: 2px;"></i>
                <div style="flex: 1;">
                    <h4 style="color: <?php echo $cor['text']; ?>; margin: 0 0 6px 0; font-size: 15px; font-weight: 600;"><?php echo htmlspecialchars($aviso['titulo']); ?></h4>
                    <p style="color: var(--light); margin: 0; font-size: 14px; line-height: 1.5; white-space: pre-wrap;"><?php echo htmlspecialchars($aviso['conteudo']); ?></p>
                    <span style="color: var(--muted); font-size: 11px; margin-top: 8px; display: block;"><?php echo date('d/m/Y', strtotime($aviso['criado_em'])); ?></span>
                </div>
                <button onclick="this.closest('.aviso-item').style.display='none'" style="background: none; border: none; color: var(--muted); cursor: pointer; font-size: 18px; padding: 0; line-height: 1;">&times;</button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Dashboard Page -->
        <div id="page-dashboard" class="page active">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Dashboard</h1>
                    <p class="page-subtitle">Visao geral do sistema</p>
                </div>
                <button class="btn btn-secondary btn-sm" onclick="loadStats()">
                    <i class="fas fa-sync-alt"></i> Atualizar
                </button>
            </div>
            
            <div class="stats-grid" id="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-mouse-pointer"></i></div>
                    <div class="stat-info">
                        <h3 id="stat-clicks">0</h3>
                        <p>Total de Cliques</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <h3 id="stat-passes">0</h3>
                        <p>Passes (Humanos)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red"><i class="fas fa-ban"></i></div>
                    <div class="stat-info">
                        <h3 id="stat-blocks">0</h3>
                        <p>Bloqueios (Bots)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-percentage"></i></div>
                    <div class="stat-info">
                        <h3 id="stat-rate">0%</h3>
                        <p>Taxa de Bloqueio</p>
                    </div>
                </div>
            </div>
            
            <div class="grid-2">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Por Plataforma</h3>
                    </div>
                    <div class="card-body">
                        <div id="platform-stats">
                            <div class="empty-state">
                                <i class="fas fa-chart-pie"></i>
                                <p>Sem dados ainda</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Ultimos 7 Dias</h3>
                    </div>
                    <div class="card-body">
                        <div id="daily-stats">
                            <div class="empty-state">
                                <i class="fas fa-calendar-alt"></i>
                                <p>Sem dados ainda</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Campaigns Page -->
        <div id="page-campaigns" class="page" style="display:none;">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Campanhas</h1>
                    <p class="page-subtitle">Gerencie suas campanhas de cloaking</p>
                </div>
                <button class="btn btn-primary btn-sm" onclick="openCampaignModal()">
                    <i class="fas fa-plus"></i> Nova Campanha
                </button>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Slug</th>
                                    <th>Plataforma</th>
                                    <th>Status</th>
                                    <th>Cliques</th>
                                    <th>Passes</th>
                                    <th>Bloqueios</th>
                                    <th>Acoes</th>
                                </tr>
                            </thead>
                            <tbody id="campaigns-table">
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <i class="fas fa-bullhorn"></i>
                                            <h3>Nenhuma campanha</h3>
                                            <p>Crie sua primeira campanha para comecar</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Analytics Page -->
        <div id="page-analytics" class="page" style="display:none;">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Analytics</h1>
                    <p class="page-subtitle">Metricas detalhadas de acessos e bloqueios</p>
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <select id="analytics-campaign" class="form-control" style="width:200px;" onchange="loadAnalytics()">
                        <option value="">Todas campanhas</option>
                    </select>
                    <input type="date" id="analytics-from" class="form-control" style="width:150px;" onchange="loadAnalytics()">
                    <span style="color:var(--muted);">ate</span>
                    <input type="date" id="analytics-to" class="form-control" style="width:150px;" onchange="loadAnalytics()">
                    <button class="btn btn-secondary btn-sm" onclick="loadAnalytics()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid" id="analytics-stats">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-eye"></i></div>
                    <div class="stat-info">
                        <h3 id="analytics-total">0</h3>
                        <p>Total de Acessos</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
                    <div class="stat-info">
                        <h3 id="analytics-black">0</h3>
                        <p>Humanos (Black)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red"><i class="fas fa-robot"></i></div>
                    <div class="stat-info">
                        <h3 id="analytics-white">0</h3>
                        <p>Bots (White)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-clock"></i></div>
                    <div class="stat-info">
                        <h3 id="analytics-paused">0</h3>
                        <p>Pausa Agendada</p>
                    </div>
                </div>
            </div>
            
            <div class="grid-2">
                <!-- Grafico por Data -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-area" style="margin-right:8px;color:var(--primary);"></i>Acessos por Data</h3>
                    </div>
                    <div class="card-body">
                        <div id="analytics-by-date">
                            <div class="empty-state">
                                <i class="fas fa-chart-line"></i>
                                <p>Carregando...</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Por Hora -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-clock" style="margin-right:8px;color:var(--info);"></i>Acessos por Hora</h3>
                    </div>
                    <div class="card-body">
                        <div id="analytics-by-hour">
                            <div class="empty-state">
                                <i class="fas fa-clock"></i>
                                <p>Carregando...</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Por Pais -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-globe" style="margin-right:8px;color:var(--success);"></i>Top Paises</h3>
                    </div>
                    <div class="card-body">
                        <div id="analytics-by-country">
                            <div class="empty-state">
                                <i class="fas fa-globe"></i>
                                <p>Carregando...</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Por Motivo -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-tag" style="margin-right:8px;color:var(--warning);"></i>Motivos de Bloqueio</h3>
                    </div>
                    <div class="card-body">
                        <div id="analytics-by-reason">
                            <div class="empty-state">
                                <i class="fas fa-tag"></i>
                                <p>Carregando...</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bot Flags -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-bug" style="margin-right:8px;color:var(--danger);"></i>Top Bot Flags</h3>
                    </div>
                    <div class="card-body">
                        <div id="analytics-bot-flags">
                            <div class="empty-state">
                                <i class="fas fa-bug"></i>
                                <p>Carregando...</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Por Campanha -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-bullhorn" style="margin-right:8px;color:var(--primary);"></i>Por Campanha</h3>
                    </div>
                    <div class="card-body">
                        <div id="analytics-by-campaign">
                            <div class="empty-state">
                                <i class="fas fa-bullhorn"></i>
                                <p>Carregando...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabela de Logs Recentes -->
            <div class="card" style="margin-top:20px;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list" style="margin-right:8px;"></i>Acessos Recentes</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>IP</th>
                                    <th>Resultado</th>
                                    <th>Motivo</th>
                                    <th>Pais</th>
                                    <th>Campanha</th>
                                    <th>Bot Score</th>
                                </tr>
                            </thead>
                            <tbody id="analytics-logs-table">
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="fas fa-history"></i>
                                            <p>Carregando logs...</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Logs Page -->
        <div id="page-logs" class="page" style="display:none;">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Logs de Bots</h1>
                    <p class="page-subtitle">Registro de trafego bloqueado</p>
                </div>
                <button class="btn btn-danger btn-sm" onclick="cleanLogs()">
                    <i class="fas fa-trash"></i> Limpar Logs Antigos
                </button>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>IP</th>
                                    <th>Motivo</th>
                                    <th>Campanha</th>
                                    <th>Plataforma</th>
                                    <th>Acoes</th>
                                </tr>
                            </thead>
                            <tbody id="logs-table">
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="fas fa-robot"></i>
                                            <h3>Nenhum log</h3>
                                            <p>Logs de bots bloqueados aparecerao aqui</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- IPs Page -->
        <div id="page-ips" class="page" style="display:none;">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Gerenciar IPs</h1>
                    <p class="page-subtitle">Whitelist e Blacklist de IPs</p>
                </div>
            </div>
            
            <div class="grid-2">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-check-circle" style="color:var(--success);margin-right:8px;"></i>Whitelist</h3>
                        <button class="btn btn-success btn-sm" onclick="openWhitelistModal()">
                            <i class="fas fa-plus"></i> Adicionar
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="whitelist-table"></div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-ban" style="color:var(--danger);margin-right:8px;"></i>Blacklist</h3>
                        <button class="btn btn-danger btn-sm" onclick="openBlockModal()">
                            <i class="fas fa-plus"></i> Bloquear IP
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="blacklist-table"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Downloads Page -->
        <div id="page-downloads" class="page" style="display:none;">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Downloads e Configuracao</h1>
                    <p class="page-subtitle">Baixe os arquivos e configure seus anuncios</p>
                </div>
            </div>
            
            <div class="grid-2">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-code" style="margin-right:8px;color:var(--primary);"></i>Tracker (index.php)</h3>
                    </div>
                    <div class="card-body">
                        <p style="color:var(--muted);margin-bottom:16px;">
                            Selecione uma campanha para gerar o tracker personalizado.
                        </p>
                        <div class="form-group">
                            <select id="download-campaign" class="form-control">
                                <option value="">Selecione uma campanha...</option>
                            </select>
                        </div>
                        <button class="btn btn-primary" onclick="downloadTracker()">
                            <i class="fas fa-download"></i> Baixar Tracker
                        </button>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-cog" style="margin-right:8px;color:var(--warning);"></i>.htaccess</h3>
                    </div>
                    <div class="card-body">
                        <p style="color:var(--muted);margin-bottom:16px;">
                            Arquivo de configuracao Apache para o dominio do tracker.
                        </p>
                        <button class="btn btn-primary" onclick="downloadHtaccess()">
                            <i class="fas fa-download"></i> Baixar .htaccess
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Instrucoes de Instalacao -->
            <div class="card" style="margin-top:20px;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-book" style="margin-right:8px;color:var(--success);"></i>Como Instalar o Tracker</h3>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;">
                        <div style="padding:15px;background:var(--surface);border-radius:8px;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                                <span style="background:var(--primary);color:white;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;">1</span>
                                <strong>Baixe os arquivos</strong>
                            </div>
                            <p style="color:var(--muted);font-size:14px;">Selecione sua campanha e baixe o <code>index.php</code> e o <code>.htaccess</code></p>
                        </div>
                        <div style="padding:15px;background:var(--surface);border-radius:8px;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                                <span style="background:var(--primary);color:white;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;">2</span>
                                <strong>Faca upload</strong>
                            </div>
                            <p style="color:var(--muted);font-size:14px;">Envie ambos os arquivos para a <strong>raiz</strong> do seu dominio de tracking via FTP ou Gerenciador de Arquivos</p>
                        </div>
                        <div style="padding:15px;background:var(--surface);border-radius:8px;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                                <span style="background:var(--primary);color:white;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;">3</span>
                                <strong>Configure o anuncio</strong>
                            </div>
                            <p style="color:var(--muted);font-size:14px;">Use a URL com os parametros UTM conforme a plataforma (veja abaixo)</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Parametros UTM por Plataforma -->
            <div class="card" style="margin-top:20px;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-link" style="margin-right:8px;color:var(--secondary);"></i>Gerador de URL com Parametros UTM</h3>
                </div>
                <div class="card-body">
                    <!-- Campo de dominio -->
                    <div style="margin-bottom:25px;padding:20px;background:linear-gradient(135deg,var(--primary-alpha) 0%,var(--secondary-alpha) 100%);border-radius:12px;border:1px solid var(--primary);">
                        <label style="display:block;margin-bottom:10px;font-weight:600;color:var(--light);">
                            <i class="fas fa-globe" style="margin-right:8px;"></i>Digite seu dominio de tracking:
                        </label>
                        <input type="text" id="utm-domain" class="form-control" placeholder="meusite.com.br" style="font-size:18px;padding:15px;background:var(--darker);border:2px solid var(--primary);">
                        <p style="color:var(--muted);font-size:12px;margin-top:8px;">
                            <i class="fas fa-info-circle" style="margin-right:5px;"></i>As URLs abaixo serao atualizadas automaticamente enquanto voce digita
                        </p>
                    </div>
                    
                    <!-- TikTok -->
                    <div style="margin-bottom:20px;padding:15px;background:var(--surface);border-radius:8px;border-left:4px solid #ff0050;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                            <span class="platform-badge platform-tiktok">TikTok Ads</span>
                            <span style="color:var(--muted);font-size:12px;">Cole em: URL de destino</span>
                            <button class="btn btn-sm" style="background:var(--overlay);color:var(--light);margin-left:auto;" onclick="copyUTM('tiktok')">
                                <i class="fas fa-copy"></i> Copiar
                            </button>
                        </div>
                        <code id="utm-tiktok" class="utm-code" style="display:block;background:var(--darker);padding:12px;border-radius:6px;font-size:13px;word-break:break-all;color:var(--success);">https://seudominio.com?utm_source=tiktok&utm_medium=cpc&utm_campaign=__CAMPAIGN_NAME__&utm_content=__AID__&ttclid=__TTCLID__</code>
                        <p style="color:var(--muted);font-size:12px;margin-top:8px;">
                            <strong>Macros:</strong> __CAMPAIGN_NAME__ = Nome da campanha | __AID__ = ID do anuncio | __TTCLID__ = Click ID do TikTok
                        </p>
                    </div>
                    
                    <!-- Facebook/Meta -->
                    <div style="margin-bottom:20px;padding:15px;background:var(--surface);border-radius:8px;border-left:4px solid #1877f2;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                            <span class="platform-badge platform-facebook">Facebook/Meta</span>
                            <span style="color:var(--muted);font-size:12px;">Cole em: URL do site</span>
                            <button class="btn btn-sm" style="background:var(--overlay);color:var(--light);margin-left:auto;" onclick="copyUTM('facebook')">
                                <i class="fas fa-copy"></i> Copiar
                            </button>
                        </div>
                        <code id="utm-facebook" class="utm-code" style="display:block;background:var(--darker);padding:12px;border-radius:6px;font-size:13px;word-break:break-all;color:var(--success);">https://seudominio.com?utm_source=facebook&utm_medium=cpc&utm_campaign={{campaign.name}}&utm_content={{ad.name}}&utm_term={{adset.name}}</code>
                        <p style="color:var(--muted);font-size:12px;margin-top:8px;">
                            <strong>Macros:</strong> {{campaign.name}} = Nome da campanha | {{ad.name}} = Nome do anuncio | {{adset.name}} = Nome do conjunto
                        </p>
                    </div>
                    
                    <!-- Google Ads -->
                    <div style="margin-bottom:20px;padding:15px;background:var(--surface);border-radius:8px;border-left:4px solid #4285f4;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:15px;">
                            <span class="platform-badge platform-google">Google Ads</span>
                            <span style="background:var(--success);color:white;padding:2px 8px;border-radius:4px;font-size:11px;">RECOMENDADO PARA CLOAKER</span>
                        </div>
                        
                        <!-- URL Final do Google -->
                        <div style="margin-bottom:15px;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:5px;">
                                <label style="color:var(--muted);font-size:12px;">
                                    <strong style="color:var(--success);">1. URL Final</strong> (cole no campo "URL final" do anuncio)
                                </label>
                                <button class="btn btn-sm" style="background:var(--overlay);color:var(--light);margin-left:auto;" onclick="copyUTM('google-final')">
                                    <i class="fas fa-copy"></i> Copiar
                                </button>
                            </div>
                            <code id="utm-google-final" class="utm-code" style="display:block;background:var(--darker);padding:12px;border-radius:6px;font-size:13px;word-break:break-all;color:var(--success);">https://seudominio.com</code>
                        </div>
                        
                        <!-- Sufixo da URL Final (RECOMENDADO) -->
                        <div style="padding:15px;background:linear-gradient(135deg,rgba(16,185,129,0.1) 0%,rgba(16,185,129,0.05) 100%);border-radius:8px;border:1px solid var(--success);">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                                <label style="color:var(--light);font-size:13px;">
                                    <strong style="color:var(--success);">2. Sufixo da URL Final</strong> (Configuracoes > Opcoes de URL da campanha)
                                </label>
                                <button class="btn btn-sm" style="background:var(--success);color:white;margin-left:auto;" onclick="copyUTM('google-suffix')">
                                    <i class="fas fa-copy"></i> Copiar
                                </button>
                            </div>
                            <code id="utm-google-suffix" style="display:block;background:var(--darker);padding:12px;border-radius:6px;font-size:13px;word-break:break-all;color:var(--success);">utm_source=google&utm_medium=cpc&utm_campaign={campaignid}&utm_content={creative}&utm_term={keyword}&gclid={gclid}</code>
                            <p style="color:var(--success);font-size:11px;margin-top:8px;margin-bottom:0;">
                                <i class="fas fa-check-circle" style="margin-right:5px;"></i>Recomendado para cloaker - nao gera redirect adicional
                            </p>
                        </div>
                        
                        <p style="color:var(--muted);font-size:12px;margin-top:12px;">
                            <strong>Macros:</strong> {campaignid} = ID da campanha | {creative} = ID do criativo | {keyword} = Palavra-chave | {gclid} = Click ID
                        </p>
                        
                        <!-- Aviso sobre Modelo de Acompanhamento -->
                        <details style="margin-top:10px;">
                            <summary style="color:var(--muted);font-size:12px;cursor:pointer;">
                                <i class="fas fa-info-circle" style="margin-right:5px;"></i>Por que NAO usar Modelo de Acompanhamento?
                            </summary>
                            <div style="margin-top:10px;padding:10px;background:var(--overlay);border-radius:6px;font-size:12px;color:var(--muted);">
                                O <strong>Modelo de Acompanhamento</strong> usa <code>{lpurl}</code> que gera um redirect intermediario. 
                                Isso pode levantar suspeitas no Google porque ele ve o redirect acontecendo. 
                                O <strong>Sufixo da URL Final</strong> adiciona os parametros diretamente na URL, sem redirects extras, 
                                tornando seu tracking mais "limpo" e menos suspeito.
                            </div>
                        </details>
                    </div>
                    
                    <!-- Kwai -->
                    <div style="margin-bottom:20px;padding:15px;background:var(--surface);border-radius:8px;border-left:4px solid #ff9500;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                            <span class="platform-badge platform-kwai">Kwai Ads</span>
                            <span style="color:var(--muted);font-size:12px;">Cole em: URL de destino</span>
                            <button class="btn btn-sm" style="background:var(--overlay);color:var(--light);margin-left:auto;" onclick="copyUTM('kwai')">
                                <i class="fas fa-copy"></i> Copiar
                            </button>
                        </div>
                        <code id="utm-kwai" class="utm-code" style="display:block;background:var(--darker);padding:12px;border-radius:6px;font-size:13px;word-break:break-all;color:var(--success);">https://seudominio.com?utm_source=kwai&utm_medium=cpc&utm_campaign=__CAMPAIGN_NAME__&utm_content=__CREATIVE_ID__&kwclid=__CALLBACK__</code>
                        <p style="color:var(--muted);font-size:12px;margin-top:8px;">
                            <strong>Macros:</strong> __CAMPAIGN_NAME__ = Nome da campanha | __CREATIVE_ID__ = ID do criativo | __CALLBACK__ = Click ID
                        </p>
                    </div>
                    
                    <!-- Taboola -->
                    <div style="margin-bottom:20px;padding:15px;background:var(--surface);border-radius:8px;border-left:4px solid #0066ff;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                            <span class="platform-badge" style="background:#0066ff;">Taboola</span>
                            <span style="color:var(--muted);font-size:12px;">Cole em: URL de destino</span>
                            <button class="btn btn-sm" style="background:var(--overlay);color:var(--light);margin-left:auto;" onclick="copyUTM('taboola')">
                                <i class="fas fa-copy"></i> Copiar
                            </button>
                        </div>
                        <code id="utm-taboola" class="utm-code" style="display:block;background:var(--darker);padding:12px;border-radius:6px;font-size:13px;word-break:break-all;color:var(--success);">https://seudominio.com?utm_source=taboola&utm_medium=native&utm_campaign={campaign_name}&utm_content={title}&utm_term={site}</code>
                        <p style="color:var(--muted);font-size:12px;margin-top:8px;">
                            <strong>Macros:</strong> {campaign_name} = Nome da campanha | {title} = Titulo do anuncio | {site} = Site de publicacao
                        </p>
                    </div>
                    
                    <!-- Outbrain -->
                    <div style="padding:15px;background:var(--surface);border-radius:8px;border-left:4px solid #f26522;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                            <span class="platform-badge" style="background:#f26522;">Outbrain</span>
                            <span style="color:var(--muted);font-size:12px;">Cole em: URL de destino</span>
                            <button class="btn btn-sm" style="background:var(--overlay);color:var(--light);margin-left:auto;" onclick="copyUTM('outbrain')">
                                <i class="fas fa-copy"></i> Copiar
                            </button>
                        </div>
                        <code id="utm-outbrain" class="utm-code" style="display:block;background:var(--darker);padding:12px;border-radius:6px;font-size:13px;word-break:break-all;color:var(--success);">https://seudominio.com?utm_source=outbrain&utm_medium=native&utm_campaign=$campaign_name$&utm_content=$ad_title$&utm_term=$publisher_name$&obclid=$ob_click_id$</code>
                        <p style="color:var(--muted);font-size:12px;margin-top:8px;">
                            <strong>Macros:</strong> $campaign_name$ = Nome da campanha | $ad_title$ = Titulo do anuncio | $publisher_name$ = Site | $ob_click_id$ = Click ID
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Dicas Importantes -->
            <div class="card" style="margin-top:20px;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-lightbulb" style="margin-right:8px;color:var(--warning);"></i>Dicas Importantes</h3>
                </div>
                <div class="card-body">
                    <ul style="color:var(--muted);padding-left:20px;line-height:2;">
                        <li><strong style="color:var(--light);">Sempre teste primeiro:</strong> Adicione seu IP na Whitelist e teste se esta redirecionando corretamente</li>
                        <li><strong style="color:var(--light);">SSL obrigatorio:</strong> Use HTTPS no seu dominio de tracking para evitar bloqueios</li>
                        <li><strong style="color:var(--light);">White page valida:</strong> Use uma pagina real e relevante (blog, artigo) como white page</li>
                        <li><strong style="color:var(--light);">Nao edite o tracker:</strong> Nao modifique o index.php gerado, pois pode quebrar a conexao com a API</li>
                        <li><strong style="color:var(--light);">Monitore os logs:</strong> Verifique regularmente os logs de bots para ajustar a protecao</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Campaign Modal -->
<div id="campaign-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="campaign-modal-title">Nova Campanha</h3>
            <button class="modal-close" onclick="closeModal('campaign-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="campaign-form">
                <input type="hidden" name="ajax_action" value="save_campaign">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="campaign_id" id="campaign-id">
                
                <div class="form-group">
                    <label>Nome da Campanha *</label>
                    <input type="text" name="name" id="campaign-name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Slug (URL)</label>
                    <input type="text" name="slug" id="campaign-slug" class="form-control" placeholder="deixe vazio para gerar automaticamente">
                </div>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label>Plataforma</label>
                        <select name="platform" id="campaign-platform" class="form-control">
                            <option value="tiktok">TikTok Ads</option>
                            <option value="facebook">Facebook/Meta Ads</option>
                            <option value="google">Google Ads</option>
                            <option value="kwai">Kwai Ads</option>
                            <option value="other">Outra</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="campaign-status" class="form-control">
                            <option value="active">Ativo</option>
                            <option value="paused">Pausado</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>White Page URL (para bots)</label>
                    <input type="url" name="white_url" id="campaign-white-url" class="form-control" placeholder="https://exemplo.com/pagina-segura">
                </div>
                
                <div class="form-group">
                    <label>Metodo White Page</label>
                    <select name="white_method" id="campaign-white-method" class="form-control">
                        <option value="redirect">Redirect 302</option>
                        <option value="proxy">Proxy (mostra conteudo)</option>
                        <option value="iframe">iFrame</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Black Page URL (para humanos)</label>
                    <input type="url" name="black_url" id="campaign-black-url" class="form-control" placeholder="https://exemplo.com/oferta">
                </div>
                
                <div class="form-group">
                    <label>Metodo Black Page</label>
                    <select name="black_method" id="campaign-black-method" class="form-control">
                        <option value="redirect">Redirect 302</option>
                        <option value="proxy">Proxy (mostra conteudo)</option>
                        <option value="iframe">iFrame</option>
                    </select>
                </div>
                
                <div style="border-top:1px solid var(--surface);padding-top:20px;margin-top:4px;margin-bottom:4px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                        <i class="fas fa-globe" style="color:var(--primary);font-size:16px;"></i>
                        <label style="margin:0;font-weight:600;color:var(--primary);">Filtro de Paises</label>
                    </div>
                    <p style="color:var(--muted);font-size:13px;margin-bottom:14px;line-height:1.6;">
                        Selecione os paises permitidos para ver a <strong style="color:var(--light);">Black Page</strong>. Visitantes de outros paises serao enviados para a White Page. Deixe em branco para permitir todos os paises.
                    </p>
                    <div class="form-group" style="margin-bottom:8px;">
                        <label>Paises Permitidos (Black Page)</label>
                        <div style="display:flex;gap:8px;margin-bottom:8px;">
                            <select id="country-select" class="form-control" style="flex:1;">
                                <option value="">-- Selecionar pais --</option>
                                <optgroup label="America do Sul">
                                    <option value="BR">Brasil</option>
                                    <option value="AR">Argentina</option>
                                    <option value="CL">Chile</option>
                                    <option value="CO">Colombia</option>
                                    <option value="PE">Peru</option>
                                    <option value="UY">Uruguay</option>
                                    <option value="PY">Paraguai</option>
                                    <option value="BO">Bolivia</option>
                                    <option value="EC">Equador</option>
                                    <option value="VE">Venezuela</option>
                                </optgroup>
                                <optgroup label="America do Norte">
                                    <option value="US">Estados Unidos</option>
                                    <option value="CA">Canada</option>
                                    <option value="MX">Mexico</option>
                                </optgroup>
                                <optgroup label="Europa">
                                    <option value="PT">Portugal</option>
                                    <option value="ES">Espanha</option>
                                    <option value="FR">Franca</option>
                                    <option value="DE">Alemanha</option>
                                    <option value="IT">Italia</option>
                                    <option value="GB">Reino Unido</option>
                                    <option value="NL">Holanda</option>
                                    <option value="BE">Belgica</option>
                                    <option value="CH">Suica</option>
                                    <option value="AT">Austria</option>
                                    <option value="SE">Suecia</option>
                                    <option value="NO">Noruega</option>
                                    <option value="DK">Dinamarca</option>
                                    <option value="FI">Finlandia</option>
                                    <option value="PL">Polonia</option>
                                    <option value="RO">Romania</option>
                                    <option value="IE">Irlanda</option>
                                </optgroup>
                                <optgroup label="Asia / Oriente Medio">
                                    <option value="JP">Japao</option>
                                    <option value="CN">China</option>
                                    <option value="IN">India</option>
                                    <option value="KR">Coreia do Sul</option>
                                    <option value="AE">Emirados Arabes</option>
                                    <option value="SA">Arabia Saudita</option>
                                    <option value="TR">Turquia</option>
                                    <option value="IL">Israel</option>
                                    <option value="TH">Tailandia</option>
                                    <option value="ID">Indonesia</option>
                                    <option value="MY">Malasia</option>
                                    <option value="PH">Filipinas</option>
                                    <option value="SG">Singapura</option>
                                </optgroup>
                                <optgroup label="Africa / Oceania">
                                    <option value="AU">Australia</option>
                                    <option value="NZ">Nova Zelandia</option>
                                    <option value="ZA">Africa do Sul</option>
                                    <option value="NG">Nigeria</option>
                                    <option value="EG">Egito</option>
                                    <option value="MA">Marrocos</option>
                                    <option value="AO">Angola</option>
                                    <option value="MZ">Mocambique</option>
                                </optgroup>
                            </select>
                            <button type="button" class="btn btn-primary" onclick="addCountry()" style="white-space:nowrap;padding:0 16px;">
                                <i class="fas fa-plus"></i> Adicionar
                            </button>
                        </div>
                        <input type="hidden" name="allowed_countries" id="campaign-allowed-countries">
                        <div id="countries-tags" style="display:flex;flex-wrap:wrap;gap:6px;min-height:32px;padding:4px 0;"></div>
                        <small style="color:var(--muted);font-size:12px;">Vazio = todos os paises permitidos.</small>
                    </div>
                </div>

                <div style="border-top:1px solid var(--surface);padding-top:20px;margin-top:4px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                        <i class="fas fa-fire-alt" style="color:var(--warning);font-size:16px;"></i>
                        <label style="margin:0;font-weight:600;color:var(--warning);">Aquecimento (Warm-up)</label>
                    </div>
                    <p style="color:var(--muted);font-size:13px;margin-bottom:14px;line-height:1.6;">
                        Nos primeiros <strong style="color:var(--light);">N cliques</strong>, todos os visitantes sao enviados para a <strong style="color:var(--light);">White Page</strong> (cloaking desativado). Ideal para passar na revisao dos anuncios antes de ativar o cloaking.
                    </p>
                    <div class="grid-2">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Cliques de Aquecimento</label>
                            <input type="number" name="warmup_clicks" id="campaign-warmup-clicks" class="form-control" 
                                   min="0" max="500" value="0" placeholder="0 = desativado">
                            <small style="color:var(--muted);font-size:12px;">0 = cloaking sempre ativo. 10-200 recomendado.</small>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Cliques Atuais</label>
                            <input type="text" id="campaign-warmup-current" class="form-control" 
                                   readonly style="background:var(--darker);cursor:default;color:var(--muted);" value="-">
                            <small style="color:var(--muted);font-size:12px;">Cliques ja registrados nesta campanha.</small>
                        </div>
                    </div>
                </div>
                
                <div style="border-top:1px solid var(--surface);padding-top:20px;margin-top:20px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                        <i class="fas fa-clock" style="color:var(--info);font-size:16px;"></i>
                        <label style="margin:0;font-weight:600;color:var(--info);">Horario de Pausa (White Mode)</label>
                    </div>
                    <p style="color:var(--muted);font-size:13px;margin-bottom:14px;line-height:1.6;">
                        Durante este horario, <strong style="color:var(--light);">a verificacao de bot e desativada</strong> e todos os visitantes sao enviados para a <strong style="color:var(--light);">White Page</strong>. Ideal para horarios de revisao de anuncios.
                    </p>
                    <div class="grid-2">
                        <div class="form-group" style="margin-bottom:8px;">
                            <label style="display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" id="campaign-schedule-enabled" name="schedule_enabled" style="width:16px;height:16px;">
                                <span>Ativar horario de pausa</span>
                            </label>
                        </div>
                    </div>
                    <div id="schedule-fields" style="display:none;">
                        <div class="grid-2">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Inicio (pausar cloaking)</label>
                                <input type="time" name="schedule_start" id="campaign-schedule-start" class="form-control" value="23:30">
                                <small style="color:var(--muted);font-size:12px;">Horario para iniciar a pausa.</small>
                            </div>
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Fim (retomar cloaking)</label>
                                <input type="time" name="schedule_end" id="campaign-schedule-end" class="form-control" value="06:00">
                                <small style="color:var(--muted);font-size:12px;">Horario para retomar o cloaking.</small>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:8px;">
                            <label>Fuso Horario</label>
                            <select name="schedule_timezone" id="campaign-schedule-timezone" class="form-control">
                                <option value="America/Sao_Paulo">Brasilia (GMT-3)</option>
                                <option value="America/Manaus">Manaus (GMT-4)</option>
                                <option value="America/Fortaleza">Fernando de Noronha (GMT-2)</option>
                                <option value="America/New_York">Nova York (GMT-5)</option>
                                <option value="America/Los_Angeles">Los Angeles (GMT-8)</option>
                                <option value="Europe/London">Londres (GMT+0)</option>
                                <option value="Europe/Lisbon">Lisboa (GMT+0)</option>
                                <option value="UTC">UTC</option>
                            </select>
                            <small style="color:var(--muted);font-size:12px;">Fuso horario para calcular o horario de pausa.</small>
                        </div>
                        <div style="background:var(--surface);padding:12px;border-radius:8px;margin-top:8px;">
                            <p style="color:var(--info);font-size:13px;margin:0;">
                                <i class="fas fa-info-circle" style="margin-right:6px;"></i>
                                <strong>Exemplo:</strong> Se configurar 23:30 ate 06:00, o cloaking sera pausado as 23:30 e voltara as 06:00 do dia seguinte.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div style="border-top:1px solid var(--surface);padding-top:20px;margin-top:20px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                        <i class="fas fa-shield-alt" style="color:var(--success);font-size:16px;"></i>
                        <label style="margin:0;font-weight:600;color:var(--success);">Protecao Avancada</label>
                    </div>
                    <p style="color:var(--muted);font-size:13px;margin-bottom:14px;line-height:1.6;">
                        Camadas extras de protecao usando tecnologias do Google e Machine Learning.
                    </p>
                    
                    <div class="form-group" style="margin-bottom:15px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:var(--darker);border-radius:8px;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <i class="fas fa-robot" style="color:var(--info);font-size:20px;"></i>
                                <div>
                                    <div style="font-weight:600;color:var(--light);">reCAPTCHA v3 (Invisivel)</div>
                                    <div style="font-size:12px;color:var(--muted);">Deteccao de bots do Google - 100% invisivel para usuarios</div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="recaptcha_enabled" id="campaign-recaptcha-enabled">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div id="recaptcha-settings" style="display:none;padding:15px;background:var(--darker);border-radius:8px;margin-bottom:15px;">
                        <div class="grid-2">
                            <div class="form-group" style="margin-bottom:10px;">
                                <label>Site Key</label>
                                <input type="text" name="recaptcha_site_key" id="campaign-recaptcha-site-key" class="form-control" placeholder="6Lc...">
                            </div>
                            <div class="form-group" style="margin-bottom:10px;">
                                <label>Secret Key</label>
                                <input type="password" name="recaptcha_secret_key" id="campaign-recaptcha-secret-key" class="form-control" placeholder="6Lc...">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Score Minimo (0.0 - 1.0)</label>
                            <input type="number" name="recaptcha_min_score" id="campaign-recaptcha-min-score" class="form-control" 
                                   min="0" max="1" step="0.1" value="0.5" style="max-width:120px;">
                            <small style="color:var(--muted);font-size:12px;">Abaixo deste score = Bot (0.5 recomendado)</small>
                        </div>
                        <div style="margin-top:12px;padding:10px;background:var(--surface);border-radius:6px;">
                            <small style="color:var(--muted);line-height:1.6;">
                                <i class="fas fa-info-circle" style="color:var(--info);margin-right:6px;"></i>
                                Obtenha suas chaves em <a href="https://www.google.com/recaptcha/admin" target="_blank" style="color:var(--primary);">google.com/recaptcha/admin</a>. 
                                Selecione "reCAPTCHA v3" ao criar.
                            </small>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:15px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:var(--darker);border-radius:8px;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <i class="fas fa-brain" style="color:var(--warning);font-size:20px;"></i>
                                <div>
                                    <div style="font-weight:600;color:var(--light);">Machine Learning</div>
                                    <div style="font-size:12px;color:var(--muted);">Aprende padroes de bots automaticamente</div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="ml_enabled" id="campaign-ml-enabled" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:0;">
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:var(--darker);border-radius:8px;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <i class="fas fa-fingerprint" style="color:var(--danger);font-size:20px;"></i>
                                <div>
                                    <div style="font-weight:600;color:var(--light);">TLS Fingerprinting Avancado</div>
                                    <div style="font-size:12px;color:var(--muted);">Identifica bots pelo handshake SSL/TLS</div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="tls_fp_enabled" id="campaign-tls-fp-enabled" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('campaign-modal')">Cancelar</button>
            <button class="btn btn-primary" onclick="saveCampaign()">
                <i class="fas fa-save"></i> Salvar
            </button>
        </div>
    </div>
</div>

<!-- Whitelist Modal -->
<div id="whitelist-modal" class="modal-overlay">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <h3 class="modal-title">Adicionar a Whitelist</h3>
            <button class="modal-close" onclick="closeModal('whitelist-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="whitelist-form">
                <input type="hidden" name="ajax_action" value="whitelist_ip">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="form-group">
                    <label>Endereco IP *</label>
                    <input type="text" name="ip" id="whitelist-ip" class="form-control" placeholder="192.168.1.1" required>
                </div>
                
                <div class="form-group">
                    <label>Nota (opcional)</label>
                    <input type="text" name="note" id="whitelist-note" class="form-control" placeholder="Ex: Meu IP de teste">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('whitelist-modal')">Cancelar</button>
            <button class="btn btn-success" onclick="addToWhitelist()">
                <i class="fas fa-check"></i> Adicionar
            </button>
        </div>
    </div>
</div>

<!-- Block IP Modal -->
<div id="block-modal" class="modal-overlay">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <h3 class="modal-title">Bloquear IP</h3>
            <button class="modal-close" onclick="closeModal('block-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="block-form">
                <input type="hidden" name="ajax_action" value="block_ip">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="form-group">
                    <label>Endereco IP *</label>
                    <input type="text" name="ip" id="block-ip" class="form-control" placeholder="192.168.1.1" required>
                </div>
                
                <div class="form-group">
                    <label>Motivo</label>
                    <input type="text" name="reason" id="block-reason" class="form-control" placeholder="Ex: Bot detectado manualmente">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('block-modal')">Cancelar</button>
            <button class="btn btn-danger" onclick="blockIP()">
                <i class="fas fa-ban"></i> Bloquear
            </button>
        </div>
    </div>
</div>

<!-- Code Preview Modal -->
<div id="code-modal" class="modal-overlay">
    <div class="modal" style="max-width:800px;">
        <div class="modal-header">
            <h3 class="modal-title" id="code-modal-title">Codigo</h3>
            <button class="modal-close" onclick="closeModal('code-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <pre class="code-block" id="code-content"></pre>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('code-modal')">Fechar</button>
            <button class="btn btn-primary" onclick="copyCode()">
                <i class="fas fa-copy"></i> Copiar Codigo
            </button>
            <button class="btn btn-success" onclick="downloadCode()">
                <i class="fas fa-download"></i> Baixar Arquivo
            </button>
        </div>
    </div>
</div>

<script>
// CSRF Token
const csrfToken = '<?php echo $csrfToken; ?>';

// State
let campaigns = [];
let stats = {};
let currentCode = '';
let currentFilename = '';

// Page Navigation
document.querySelectorAll('.nav-link[data-page]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const page = this.dataset.page;
        
        // Update nav
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        
        // Show page
        document.querySelectorAll('.page').forEach(p => p.style.display = 'none');
        document.getElementById('page-' + page).style.display = 'block';
        
        // Load data
        if (page === 'dashboard') loadStats();
        if (page === 'campaigns') loadCampaigns();
        if (page === 'analytics') initAnalytics();
        if (page === 'logs') loadLogs();
        if (page === 'ips') loadIPs();
        if (page === 'downloads') loadCampaignsForDownload();
    });
});

// Toast
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'exclamation-circle') + '"></i>' + message;
    container.appendChild(toast);
    
    setTimeout(() => toast.remove(), 3000);
}

// Modal
function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

// API Call
async function apiCall(action, data = {}) {
    const formData = new FormData();
    formData.append('ajax_action', action);
    formData.append('csrf_token', csrfToken);
    
    for (const key in data) {
        formData.append(key, data[key]);
    }
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        showToast('Erro na requisicao', 'error');
        return null;
    }
}

// Stats
async function loadStats() {
    const result = await apiCall('get_stats');
    if (!result || !result.stats) return;
    
  stats = result.stats || {};
  
  // Garante valores numericos validos
  const totalClicks = parseInt(stats.total_clicks) || 0;
  const totalPasses = parseInt(stats.total_passes) || 0;
  const totalBlocks = parseInt(stats.total_blocks) || 0;
  
  document.getElementById('stat-clicks').textContent = formatNumber(totalClicks);
  document.getElementById('stat-passes').textContent = formatNumber(totalPasses);
  document.getElementById('stat-blocks').textContent = formatNumber(totalBlocks);
  
  // Calcula taxa de bloqueio com protecao contra NaN
  const rate = totalClicks > 0 ? ((totalBlocks / totalClicks) * 100).toFixed(1) : '0';
  document.getElementById('stat-rate').textContent = rate + '%';
    
    // Platform stats
    const platformHtml = [];
    const platforms = stats.by_platform || {};
    
    for (const [platform, data] of Object.entries(platforms)) {
        platformHtml.push(`
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--surface);">
                <span class="platform-badge platform-${platform}">${platform.charAt(0).toUpperCase() + platform.slice(1)}</span>
                <span style="color:var(--light)">${formatNumber(data.clicks || 0)} cliques</span>
            </div>
        `);
    }
    
    document.getElementById('platform-stats').innerHTML = platformHtml.length ? platformHtml.join('') : '<div class="empty-state"><i class="fas fa-chart-pie"></i><p>Sem dados ainda</p></div>';
    
    // Daily stats
    const dailyHtml = [];
    const dates = stats.by_date || {};
    const sortedDates = Object.keys(dates).sort().slice(-7);
    
    for (const date of sortedDates) {
        const data = dates[date];
        dailyHtml.push(`
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--surface);">
                <span style="color:var(--muted)">${formatDate(date)}</span>
                <span style="color:var(--light)">${formatNumber(data.clicks || 0)} cliques</span>
            </div>
        `);
    }
    
    document.getElementById('daily-stats').innerHTML = dailyHtml.length ? dailyHtml.join('') : '<div class="empty-state"><i class="fas fa-calendar-alt"></i><p>Sem dados ainda</p></div>';
}

// Analytics
async function loadAnalytics() {
    const campaignSlug = document.getElementById('analytics-campaign')?.value || '';
    const dateFrom = document.getElementById('analytics-from')?.value || '';
    const dateTo = document.getElementById('analytics-to')?.value || '';
    
    const result = await apiCall('get_analytics', {
        campaign_slug: campaignSlug,
        date_from: dateFrom,
        date_to: dateTo
    });
    
    if (!result || !result.analytics) return;
    
    const analytics = result.analytics;
    
    // Update stat cards
    document.getElementById('analytics-total').textContent = formatNumber(analytics.total || 0);
    document.getElementById('analytics-black').textContent = formatNumber(analytics.by_result?.black || 0);
    document.getElementById('analytics-white').textContent = formatNumber(analytics.by_result?.white || 0);
    document.getElementById('analytics-paused').textContent = formatNumber(analytics.by_result?.schedule_pause || 0);
    
    // By Date chart (simple bar visualization)
    const byDateHtml = [];
    const dates = analytics.by_date || {};
    const maxTotal = Math.max(...Object.values(dates).map(d => d.total || 0), 1);
    
    for (const [date, data] of Object.entries(dates)) {
        const blackWidth = Math.round((data.black / maxTotal) * 100);
        const whiteWidth = Math.round((data.white / maxTotal) * 100);
        byDateHtml.push(`
            <div style="margin-bottom:10px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="color:var(--muted);font-size:12px;">${formatDate(date)}</span>
                    <span style="color:var(--light);font-size:12px;">${data.total} acessos</span>
                </div>
                <div style="display:flex;height:8px;border-radius:4px;overflow:hidden;background:var(--surface);">
                    <div style="width:${blackWidth}%;background:var(--success);"></div>
                    <div style="width:${whiteWidth}%;background:var(--danger);"></div>
                </div>
            </div>
        `);
    }
    document.getElementById('analytics-by-date').innerHTML = byDateHtml.length ? byDateHtml.join('') : '<div class="empty-state"><i class="fas fa-chart-line"></i><p>Sem dados</p></div>';
    
    // By Hour
    const byHourHtml = [];
    const hours = analytics.by_hour || [];
    const maxHour = Math.max(...hours, 1);
    
    for (let i = 0; i < 24; i++) {
        const count = hours[i] || 0;
        const height = Math.round((count / maxHour) * 40);
        byHourHtml.push(`
            <div style="display:flex;flex-direction:column;align-items:center;flex:1;">
                <div style="width:100%;max-width:20px;height:${height}px;background:var(--primary);border-radius:2px;"></div>
                <span style="color:var(--muted);font-size:10px;margin-top:4px;">${String(i).padStart(2,'0')}</span>
            </div>
        `);
    }
    document.getElementById('analytics-by-hour').innerHTML = `<div style="display:flex;gap:2px;align-items:flex-end;height:60px;">${byHourHtml.join('')}</div>`;
    
    // By Country
    const byCountryHtml = [];
    for (const [country, count] of Object.entries(analytics.by_country || {})) {
        byCountryHtml.push(`
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--surface);">
                <span style="color:var(--light);">${country}</span>
                <span style="color:var(--muted);">${formatNumber(count)}</span>
            </div>
        `);
    }
    document.getElementById('analytics-by-country').innerHTML = byCountryHtml.length ? byCountryHtml.join('') : '<div class="empty-state"><i class="fas fa-globe"></i><p>Sem dados</p></div>';
    
    // By Reason
    const byReasonHtml = [];
    for (const [reason, count] of Object.entries(analytics.by_reason || {})) {
        const label = reason.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        byReasonHtml.push(`
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--surface);">
                <span style="color:var(--light);">${label}</span>
                <span style="color:var(--muted);">${formatNumber(count)}</span>
            </div>
        `);
    }
    document.getElementById('analytics-by-reason').innerHTML = byReasonHtml.length ? byReasonHtml.join('') : '<div class="empty-state"><i class="fas fa-tag"></i><p>Sem dados</p></div>';
    
    // Bot Flags
    const botFlagsHtml = [];
    for (const [flag, count] of Object.entries(analytics.top_bot_flags || {})) {
        botFlagsHtml.push(`
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--surface);">
                <span style="color:var(--warning);font-family:monospace;font-size:12px;">${flag}</span>
                <span style="color:var(--muted);">${formatNumber(count)}</span>
            </div>
        `);
    }
    document.getElementById('analytics-bot-flags').innerHTML = botFlagsHtml.length ? botFlagsHtml.join('') : '<div class="empty-state"><i class="fas fa-bug"></i><p>Sem dados</p></div>';
    
    // By Campaign
    const byCampaignHtml = [];
    for (const [slug, data] of Object.entries(analytics.by_campaign || {})) {
        const passRate = data.total > 0 ? Math.round((data.black / data.total) * 100) : 0;
        byCampaignHtml.push(`
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--surface);">
                <div>
                    <span style="color:var(--light);font-weight:500;">${slug}</span>
                    <span style="color:var(--muted);font-size:12px;margin-left:8px;">${data.total} acessos</span>
                </div>
                <div>
                    <span style="color:var(--success);margin-right:10px;">${data.black} humanos</span>
                    <span style="color:var(--danger);">${data.white} bots</span>
                </div>
            </div>
        `);
    }
    document.getElementById('analytics-by-campaign').innerHTML = byCampaignHtml.length ? byCampaignHtml.join('') : '<div class="empty-state"><i class="fas fa-bullhorn"></i><p>Sem dados</p></div>';
    
    // Load recent access logs
    loadAccessLogs(campaignSlug, dateFrom, dateTo);
}

// Load Access Logs for Analytics table
async function loadAccessLogs(campaignSlug, dateFrom, dateTo) {
    const result = await apiCall('get_access_logs', {
        campaign_slug: campaignSlug || '',
        date_from: dateFrom || '',
        date_to: dateTo || '',
        limit: 100
    });
    
    if (!result || !result.logs) return;
    
    const tbody = document.getElementById('analytics-logs-table');
    
    if (!result.logs.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><i class="fas fa-history"></i><p>Nenhum log encontrado</p></div></td></tr>';
        return;
    }
    
    const rows = result.logs.map(log => {
        const resultClass = log.result === 'black' ? 'success' : 'danger';
        const resultLabel = log.result === 'black' ? 'Humano' : (log.result === 'schedule_pause' ? 'Pausa' : 'Bot');
        return `
            <tr>
                <td style="white-space:nowrap;">${log.timestamp}</td>
                <td style="font-family:monospace;font-size:12px;">${log.ip_masked || '***'}</td>
                <td><span class="status-badge ${resultClass}">${resultLabel}</span></td>
                <td style="font-size:12px;">${log.reason || '-'}</td>
                <td>${log.country || '-'}</td>
                <td>${log.campaign_slug || '-'}</td>
                <td><span style="color:${log.bot_score > 50 ? 'var(--danger)' : 'var(--success)'}">${log.bot_score || 0}</span></td>
            </tr>
        `;
    });
    
    tbody.innerHTML = rows.join('');
}

// Initialize Analytics page
function initAnalytics() {
    // Set default dates (last 7 days)
    const today = new Date();
    const weekAgo = new Date(today);
    weekAgo.setDate(weekAgo.getDate() - 7);
    
    document.getElementById('analytics-from').value = weekAgo.toISOString().split('T')[0];
    document.getElementById('analytics-to').value = today.toISOString().split('T')[0];
    
    // Populate campaign selector
    if (campaigns && campaigns.length) {
        const select = document.getElementById('analytics-campaign');
        select.innerHTML = '<option value="">Todas campanhas</option>' + 
            campaigns.map(c => `<option value="${c.slug}">${c.name}</option>`).join('');
    }
    
    loadAnalytics();
}

// Campaigns
async function loadCampaigns() {
    const result = await apiCall('get_campaigns');
    if (!result || !result.campaigns) return;
    
    campaigns = result.campaigns;
    renderCampaigns();
}

function renderCampaigns() {
    const tbody = document.getElementById('campaigns-table');
    
    if (!campaigns.length) {
        tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state"><i class="fas fa-bullhorn"></i><h3>Nenhuma campanha</h3><p>Crie sua primeira campanha para comecar</p></div></td></tr>';
        return;
    }
    
    tbody.innerHTML = campaigns.map(c => {
        const campStats = stats.by_campaign?.[c.id] || {};
        const totalClicks = campStats.clicks || 0;
        const warmupClicks = parseInt(c.warmup_clicks || 0);
        
        // Badge de aquecimento
        let warmupBadge = '';
        if (warmupClicks > 0) {
            const remaining = Math.max(0, warmupClicks - totalClicks);
            if (remaining > 0) {
                warmupBadge = `<br><span style="font-size:11px;color:var(--warning);"><i class="fas fa-fire-alt"></i> Aquecendo: ${totalClicks}/${warmupClicks}</span>`;
            } else {
                warmupBadge = `<br><span style="font-size:11px;color:var(--success);"><i class="fas fa-shield-alt"></i> Cloaking ativo</span>`;
            }
        }
        
        return `
            <tr>
                <td><strong>${escapeHtml(c.name)}</strong>${warmupBadge}</td>
                <td><code style="color:var(--primary)">${escapeHtml(c.slug)}</code></td>
                <td><span class="platform-badge platform-${c.platform}">${c.platform}</span></td>
                <td><span class="badge badge-${c.status === 'active' ? 'success' : 'warning'}">${c.status === 'active' ? 'Ativo' : 'Pausado'}</span></td>
                <td>${formatNumber(totalClicks)}</td>
                <td>${formatNumber(campStats.passes || 0)}</td>
                <td>${formatNumber(campStats.blocks || 0)}</td>
                <td>
                    <div class="actions">
                        <button class="action-btn" onclick="editCampaign('${c.id}')" title="Editar"><i class="fas fa-edit"></i></button>
                        <button class="action-btn danger" onclick="deleteCampaign('${c.id}')" title="Excluir"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function openCampaignModal(campaign = null) {
    document.getElementById('campaign-modal-title').textContent = campaign ? 'Editar Campanha' : 'Nova Campanha';
    document.getElementById('campaign-id').value = campaign?.id || '';
    document.getElementById('campaign-name').value = campaign?.name || '';
    document.getElementById('campaign-slug').value = campaign?.slug || '';
    document.getElementById('campaign-platform').value = campaign?.platform || 'tiktok';
    document.getElementById('campaign-status').value = campaign?.status || 'active';
    document.getElementById('campaign-white-url').value = campaign?.white_url || '';
    document.getElementById('campaign-white-method').value = campaign?.white_method || 'redirect';
    document.getElementById('campaign-black-url').value = campaign?.black_url || '';
    document.getElementById('campaign-black-method').value = campaign?.black_method || 'redirect';
    document.getElementById('campaign-warmup-clicks').value = campaign?.warmup_clicks || 0;
    
    // Protecao Avancada
    document.getElementById('campaign-recaptcha-enabled').checked = campaign?.recaptcha_enabled === '1' || campaign?.recaptcha_enabled === true;
    document.getElementById('campaign-recaptcha-site-key').value = campaign?.recaptcha_site_key || '';
    document.getElementById('campaign-recaptcha-secret-key').value = campaign?.recaptcha_secret_key || '';
    document.getElementById('campaign-recaptcha-min-score').value = campaign?.recaptcha_min_score || '0.5';
    document.getElementById('campaign-ml-enabled').checked = campaign?.ml_enabled !== '0' && campaign?.ml_enabled !== false;
    document.getElementById('campaign-tls-fp-enabled').checked = campaign?.tls_fp_enabled !== '0' && campaign?.tls_fp_enabled !== false;
    
    // Toggle reCAPTCHA settings visibility
    toggleRecaptchaSettings();
    
    // Horario de Pausa
    const scheduleEnabled = campaign?.schedule_enabled === '1' || campaign?.schedule_enabled === true;
    document.getElementById('campaign-schedule-enabled').checked = scheduleEnabled;
    document.getElementById('campaign-schedule-start').value = campaign?.schedule_start || '23:30';
    document.getElementById('campaign-schedule-end').value = campaign?.schedule_end || '06:00';
    document.getElementById('campaign-schedule-timezone').value = campaign?.schedule_timezone || 'America/Sao_Paulo';
    toggleScheduleFields();

    // Paises permitidos
    const allowedCountries = campaign?.allowed_countries || '';
    document.getElementById('campaign-allowed-countries').value = allowedCountries;
    renderCountryTags(allowedCountries ? allowedCountries.split(',').filter(Boolean) : []);

    // Mostra cliques atuais e status de aquecimento
    const warmupClicks = parseInt(campaign?.warmup_clicks || 0);
    const campStats = stats.by_campaign?.[campaign?.id] || {};
    const totalClicks = campStats.clicks || 0;
    const currentEl = document.getElementById('campaign-warmup-current');
    if (campaign && warmupClicks > 0) {
        const remaining = Math.max(0, warmupClicks - totalClicks);
        if (remaining > 0) {
            currentEl.value = totalClicks + ' cliques (' + remaining + ' faltam para ativar)';
            currentEl.style.color = 'var(--warning)';
        } else {
            currentEl.value = totalClicks + ' cliques (cloaking ATIVO)';
            currentEl.style.color = 'var(--success)';
        }
    } else {
        currentEl.value = campaign ? (totalClicks + ' cliques') : '-';
        currentEl.style.color = 'var(--muted)';
    }
    
    openModal('campaign-modal');
}

function editCampaign(id) {
    const campaign = campaigns.find(c => c.id === id);
    if (campaign) openCampaignModal(campaign);
}

async function saveCampaign() {
    const form = document.getElementById('campaign-form');
    const data = Object.fromEntries(new FormData(form));
    
    const result = await apiCall('save_campaign', data);
    
    if (result?.success) {
        showToast('Campanha salva com sucesso!');
        closeModal('campaign-modal');
        loadCampaigns();
    } else {
        showToast(result?.error || 'Erro ao salvar', 'error');
    }
}

async function deleteCampaign(id) {
    if (!confirm('Tem certeza que deseja excluir esta campanha?')) return;
    
    const result = await apiCall('delete_campaign', { campaign_id: id });
    
    if (result?.success) {
        showToast('Campanha excluida!');
        loadCampaigns();
    } else {
        showToast('Erro ao excluir', 'error');
    }
}

// Logs
async function loadLogs() {
    const result = await apiCall('get_bot_logs', { limit: 100 });
    if (!result || !result.logs) return;
    
    const tbody = document.getElementById('logs-table');
    
    if (!result.logs.length) {
        tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state"><i class="fas fa-robot"></i><h3>Nenhum log</h3><p>Logs de bots bloqueados aparecerao aqui</p></div></td></tr>';
        return;
    }
    
    tbody.innerHTML = result.logs.map(log => `
        <tr>
            <td>${escapeHtml(log.timestamp)}</td>
            <td><code>${escapeHtml(log.ip)}</code></td>
            <td><span class="badge badge-danger">${escapeHtml(log.reason)}</span></td>
            <td>${escapeHtml(log.campaign || '-')}</td>
            <td><span class="platform-badge platform-${log.platform || 'other'}">${log.platform || 'unknown'}</span></td>
            <td>
                <div class="actions">
                    <button class="action-btn" onclick="whitelistFromLog('${log.ip}')" title="Adicionar a Whitelist"><i class="fas fa-check"></i></button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function cleanLogs() {
    if (!confirm('Limpar logs com mais de 30 dias?')) return;
    
    const result = await apiCall('clean_logs', { days: 30 });
    
    if (result?.success) {
        showToast('Logs antigos removidos!');
        loadLogs();
    }
}

function whitelistFromLog(ip) {
    document.getElementById('whitelist-ip').value = ip;
    document.getElementById('whitelist-note').value = 'Adicionado dos logs';
    openModal('whitelist-modal');
}

// IPs
async function loadIPs() {
    const [whiteResult, blackResult] = await Promise.all([
        apiCall('get_whitelist_ips'),
        apiCall('get_blocked_ips')
    ]);
    
    // Whitelist
    const whitelistDiv = document.getElementById('whitelist-table');
    const whitelist = whiteResult?.ips || [];
    
    if (!whitelist.length) {
        whitelistDiv.innerHTML = '<div class="empty-state"><i class="fas fa-list"></i><p>Nenhum IP na whitelist</p></div>';
    } else {
        whitelistDiv.innerHTML = whitelist.map(item => {
            const ip = typeof item === 'string' ? item : item.ip;
            const note = typeof item === 'object' ? item.note : '';
            return `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--surface);">
                    <div>
                        <code style="color:var(--success)">${escapeHtml(ip)}</code>
                        ${note ? `<span style="color:var(--muted);margin-left:10px;font-size:12px;">${escapeHtml(note)}</span>` : ''}
                    </div>
                    <button class="action-btn danger" onclick="removeWhitelist('${ip}')" title="Remover"><i class="fas fa-times"></i></button>
                </div>
            `;
        }).join('');
    }
    
    // Blacklist
    const blacklistDiv = document.getElementById('blacklist-table');
    const blacklist = blackResult?.ips || [];
    
    if (!blacklist.length) {
        blacklistDiv.innerHTML = '<div class="empty-state"><i class="fas fa-list"></i><p>Nenhum IP bloqueado</p></div>';
    } else {
        blacklistDiv.innerHTML = blacklist.map(item => {
            const ip = typeof item === 'string' ? item : item.ip;
            const reason = typeof item === 'object' ? item.reason : '';
            return `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--surface);">
                    <div>
                        <code style="color:var(--danger)">${escapeHtml(ip)}</code>
                        ${reason ? `<span style="color:var(--muted);margin-left:10px;font-size:12px;">${escapeHtml(reason)}</span>` : ''}
                    </div>
                    <button class="action-btn" onclick="unblockIP('${ip}')" title="Desbloquear"><i class="fas fa-unlock"></i></button>
                </div>
            `;
        }).join('');
    }
}

function openWhitelistModal() {
    document.getElementById('whitelist-ip').value = '';
    document.getElementById('whitelist-note').value = '';
    openModal('whitelist-modal');
}

function openBlockModal() {
    document.getElementById('block-ip').value = '';
    document.getElementById('block-reason').value = '';
    openModal('block-modal');
}

async function addToWhitelist() {
    const ip = document.getElementById('whitelist-ip').value;
    const note = document.getElementById('whitelist-note').value;
    
    const result = await apiCall('whitelist_ip', { ip, note });
    
    if (result?.success) {
        showToast('IP adicionado a whitelist!');
        closeModal('whitelist-modal');
        loadIPs();
    } else {
        showToast(result?.error || 'Erro ao adicionar', 'error');
    }
}

async function removeWhitelist(ip) {
    const result = await apiCall('remove_whitelist', { ip });
    
    if (result?.success) {
        showToast('IP removido da whitelist!');
        loadIPs();
    }
}

async function blockIP() {
    const ip = document.getElementById('block-ip').value;
    const reason = document.getElementById('block-reason').value;
    
    const result = await apiCall('block_ip', { ip, reason });
    
    if (result?.success) {
        showToast('IP bloqueado!');
        closeModal('block-modal');
        loadIPs();
    } else {
        showToast(result?.error || 'Erro ao bloquear', 'error');
    }
}

async function unblockIP(ip) {
    const result = await apiCall('unblock_ip', { ip });
    
    if (result?.success) {
        showToast('IP desbloqueado!');
        loadIPs();
    }
}

// Downloads
async function loadCampaignsForDownload() {
    const result = await apiCall('get_campaigns');
    if (!result || !result.campaigns) return;
    
    const select = document.getElementById('download-campaign');
    select.innerHTML = '<option value="">Selecione uma campanha...</option>' + 
        result.campaigns.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
}

async function downloadTracker() {
    const campaignId = document.getElementById('download-campaign').value;
    if (!campaignId) {
        showToast('Selecione uma campanha', 'warning');
        return;
    }
    
    const result = await apiCall('download_tracker', { campaign_id: campaignId });
    
    if (result?.code) {
        currentCode = result.code;
        currentFilename = result.filename;
        document.getElementById('code-modal-title').textContent = 'Tracker - ' + result.filename;
        document.getElementById('code-content').textContent = result.code;
        openModal('code-modal');
    } else {
        showToast('Erro ao gerar tracker', 'error');
    }
}

async function downloadHtaccess() {
    const result = await apiCall('download_htaccess');
    
    if (result?.code) {
        currentCode = result.code;
        currentFilename = result.filename;
        document.getElementById('code-modal-title').textContent = result.filename;
        document.getElementById('code-content').textContent = result.code;
        openModal('code-modal');
    }
}

function copyCode() {
    navigator.clipboard.writeText(currentCode).then(() => {
        showToast('Codigo copiado!');
    });
}

function downloadCode() {
    const blob = new Blob([currentCode], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = currentFilename;
    a.click();
    URL.revokeObjectURL(url);
    showToast('Download iniciado!');
}

// IPs
async function loadIPs() {
    const [whiteResult, blackResult] = await Promise.all([
        apiCall('get_whitelist_ips'),
        apiCall('get_blocked_ips')
    ]);
    
    // Whitelist
    const whitelistDiv = document.getElementById('whitelist-table');
    const whitelist = whiteResult?.ips || [];
    
    if (!whitelist.length) {
        whitelistDiv.innerHTML = '<div class="empty-state"><i class="fas fa-list"></i><p>Nenhum IP na whitelist</p></div>';
    } else {
        whitelistDiv.innerHTML = whitelist.map(item => {
            const ip = typeof item === 'string' ? item : item.ip;
            const note = typeof item === 'object' ? item.note : '';
            // Se a nota contém "Admin Test", mostra apenas a nota mascarada (sem o IP real)
            const displayIP = note && note.includes('Admin Test') ? note : escapeHtml(ip);
            const showNote = note && !note.includes('Admin Test') ? `<span style="color:var(--muted);margin-left:10px;font-size:12px;">${escapeHtml(note)}</span>` : '';
            return `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--surface);">
                    <div>
                        <code style="color:var(--success)">${displayIP}</code>
                        ${showNote}
                    </div>
                    <button class="action-btn danger" onclick="removeWhitelist('${ip}')" title="Remover"><i class="fas fa-times"></i></button>
                </div>
            `;
        }).join('');
    }
    
    // Blacklist
    const blacklistDiv = document.getElementById('blacklist-table');
    const blacklist = blackResult?.ips || [];
    
    if (!blacklist.length) {
        blacklistDiv.innerHTML = '<div class="empty-state"><i class="fas fa-list"></i><p>Nenhum IP bloqueado</p></div>';
    } else {
        blacklistDiv.innerHTML = blacklist.map(item => {
            const ip = typeof item === 'string' ? item : item.ip;
            const reason = typeof item === 'object' ? item.reason : '';
            return `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--surface);">
                    <div>
                        <code style="color:var(--danger)">${escapeHtml(ip)}</code>
                        ${reason ? `<span style="color:var(--muted);margin-left:10px;font-size:12px;">${escapeHtml(reason)}</span>` : ''}
                    </div>
                    <button class="action-btn" onclick="unblockIP('${ip}')" title="Desbloquear"><i class="fas fa-unlock"></i></button>
                </div>
            `;
        }).join('');
    }
}

// UTM Suffixes (apenas os parametros, dominio sera adicionado)
const utmSuffixes = {
    'tiktok': '?utm_source=tiktok&utm_medium=cpc&utm_campaign=__CAMPAIGN_NAME__&utm_content=__AID__&ttclid=__TTCLID__',
    'facebook': '?utm_source=facebook&utm_medium=cpc&utm_campaign={{campaign.name}}&utm_content={{ad.name}}&utm_term={{adset.name}}',
    'google-final': '',
    'kwai': '?utm_source=kwai&utm_medium=cpc&utm_campaign=__CAMPAIGN_NAME__&utm_content=__CREATIVE_ID__&kwclid=__CALLBACK__',
    'taboola': '?utm_source=taboola&utm_medium=native&utm_campaign={campaign_name}&utm_content={title}&utm_term={site}',
    'outbrain': '?utm_source=outbrain&utm_medium=native&utm_campaign=$campaign_name$&utm_content=$ad_title$&utm_term=$publisher_name$&obclid=$ob_click_id$'
};

// Atualiza todas as URLs em tempo real enquanto digita
function updateAllUTMs() {
    let domain = document.getElementById('utm-domain').value.trim();
    
    // Remove barra final e espacos
    domain = domain.replace(/\/+$/, '').trim();
    
    // Se vazio, mostra placeholder
    if (!domain) {
        domain = 'https://seudominio.com';
    }
    
    // Adiciona https se nao tiver protocolo
    if (domain && !domain.startsWith('http://') && !domain.startsWith('https://')) {
        domain = 'https://' + domain;
    }
    
    // Atualiza cada UTM (exceto google-tracking e google-suffix que nao usam dominio)
    for (const [platform, suffix] of Object.entries(utmSuffixes)) {
        const element = document.getElementById('utm-' + platform);
        if (element) {
            element.textContent = domain + suffix;
        }
    }
}

// Copy UTM URL
function copyUTM(platform) {
    const element = document.getElementById('utm-' + platform);
    if (element) {
        const text = element.textContent;
        
        // Verifica se ainda tem o placeholder
        if (text.includes('seudominio.com')) {
            showToast('Digite seu dominio primeiro!', 'warning');
            document.getElementById('utm-domain').focus();
            return;
        }
        
        navigator.clipboard.writeText(text).then(() => {
            showToast('URL copiada!');
        }).catch(() => {
            // Fallback
            const range = document.createRange();
            range.selectNode(element);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
            document.execCommand('copy');
            window.getSelection().removeAllRanges();
            showToast('URL copiada!');
        });
    }
}

// Toggle reCAPTCHA settings visibility
function toggleRecaptchaSettings() {
    const checkbox = document.getElementById('campaign-recaptcha-enabled');
    const settings = document.getElementById('recaptcha-settings');
    if (checkbox && settings) {
        settings.style.display = checkbox.checked ? 'block' : 'none';
    }
}

// Toggle Schedule fields visibility
function toggleScheduleFields() {
    const checkbox = document.getElementById('campaign-schedule-enabled');
    const fields = document.getElementById('schedule-fields');
    if (checkbox && fields) {
        fields.style.display = checkbox.checked ? 'block' : 'none';
    }
}

// Event listener para o checkbox de horario
document.addEventListener('DOMContentLoaded', function() {
    const scheduleCheckbox = document.getElementById('campaign-schedule-enabled');
    if (scheduleCheckbox) {
        scheduleCheckbox.addEventListener('change', toggleScheduleFields);
    }
});

// Inicializa eventos ao carregar pagina
document.addEventListener('DOMContentLoaded', function() {
    const domainInput = document.getElementById('utm-domain');
    if (domainInput) {
        // Atualiza em tempo real enquanto digita
        domainInput.addEventListener('input', updateAllUTMs);
        
        // Tambem atualiza ao perder foco
        domainInput.addEventListener('blur', updateAllUTMs);
    }
    
    // reCAPTCHA toggle
    const recaptchaCheckbox = document.getElementById('campaign-recaptcha-enabled');
    if (recaptchaCheckbox) {
        recaptchaCheckbox.addEventListener('change', toggleRecaptchaSettings);
    }
});

// Paises - nomes legíveis
const COUNTRY_NAMES = {
    BR:'Brasil',AR:'Argentina',CL:'Chile',CO:'Colombia',PE:'Peru',UY:'Uruguay',PY:'Paraguai',BO:'Bolivia',EC:'Equador',VE:'Venezuela',
    US:'EUA',CA:'Canada',MX:'Mexico',
    PT:'Portugal',ES:'Espanha',FR:'Franca',DE:'Alemanha',IT:'Italia',GB:'Reino Unido',NL:'Holanda',BE:'Belgica',CH:'Suica',AT:'Austria',SE:'Suecia',NO:'Noruega',DK:'Dinamarca',FI:'Finlandia',PL:'Polonia',RO:'Romania',IE:'Irlanda',
    JP:'Japao',CN:'China',IN:'India',KR:'Coreia do Sul',AE:'Emirados',SA:'Arabia Saudita',TR:'Turquia',IL:'Israel',TH:'Tailandia',ID:'Indonesia',MY:'Malasia',PH:'Filipinas',SG:'Singapura',
    AU:'Australia',NZ:'Nova Zelandia',ZA:'Africa do Sul',NG:'Nigeria',EG:'Egito',MA:'Marrocos',AO:'Angola',MZ:'Mocambique'
};

function getSelectedCountries() {
    const val = document.getElementById('campaign-allowed-countries').value;
    return val ? val.split(',').filter(Boolean) : [];
}

function renderCountryTags(countries) {
    const container = document.getElementById('countries-tags');
    container.innerHTML = countries.map(code => `
        <span style="display:inline-flex;align-items:center;gap:5px;background:var(--primary);color:#fff;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:500;">
            ${escapeHtml(COUNTRY_NAMES[code] || code)} (${escapeHtml(code)})
            <button type="button" onclick="removeCountry('${escapeHtml(code)}')" style="background:none;border:none;color:#fff;cursor:pointer;padding:0;font-size:14px;line-height:1;">&times;</button>
        </span>
    `).join('');
}

function addCountry() {
    const select = document.getElementById('country-select');
    const code = select.value;
    if (!code) return;
    const countries = getSelectedCountries();
    if (!countries.includes(code)) {
        countries.push(code);
        document.getElementById('campaign-allowed-countries').value = countries.join(',');
        renderCountryTags(countries);
    }
    select.value = '';
}

function removeCountry(code) {
    const countries = getSelectedCountries().filter(c => c !== code);
    document.getElementById('campaign-allowed-countries').value = countries.join(',');
    renderCountryTags(countries);
}

// Helpers
function formatNumber(num) {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
}

function formatDate(dateStr) {
    const [year, month, day] = dateStr.split('-');
    return `${day}/${month}`;
}

function escapeHtml(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Init
loadStats();
</script>

<?php endif; ?>

</body>
</html>
