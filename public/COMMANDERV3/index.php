<?php
/**
 * COMMANDER V10.3 - Dashboard Principal
 * 
 * Painel administrativo para gerenciamento de campanhas de cloaking
 * 
 * INSTRUÇÕES: Substitua o arquivo index.php existente por este
 */

// ============================================
// ROTEADOR DE CLOAKING POR DOMINIO (estilo White Rabbit)
// ============================================
// Se o acesso chegar por um DOMINIO DE CAMPANHA apontado para o COMMANDER
// (via CNAME ou registro A), executamos o cloaking direto e encerramos ANTES
// de carregar o painel/login. Assim o mesmo COMMANDER serve:
//   - o painel, quando acessado pelo dominio do proprio COMMANDER;
//   - a campanha (Safe/Offer), quando acessado por um dominio de campanha.
// Precisa rodar antes do proteger.php para nao redirecionar visitantes ao login.

/**
 * Normaliza um dominio: remove protocolo, path, porta, "www." e caracteres invalidos.
 */
function commanderNormalizeDomain($domain) {
    $d = strtolower(trim((string) $domain));
    $d = preg_replace('#^https?://#', '', $d);
    $d = preg_replace('#/.*$#', '', $d);
    $d = preg_replace('/:\d+$/', '', $d);
    $d = preg_replace('/^www\./', '', $d);
    $d = preg_replace('/[^a-z0-9.\-]/', '', $d);
    return $d;
}

/**
 * Se o Host atual for um dominio configurado em alguma campanha, roda o
 * motor de cloaking e encerra a execucao.
 */
function commanderMaybeRouteCampaignDomain() {
    $host = commanderNormalizeDomain($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') return;

    // Ping de verificacao: se o dominio for acessado com ?__cmdr_ping=1, o
    // COMMANDER responde com uma assinatura. Se o painel conseguir ler essa
    // assinatura ao consultar o dominio, e prova de que o trafego chega ate
    // aqui (mesmo passando pela Cloudflare com proxy laranja ativo).
    if (isset($_GET['__cmdr_ping'])) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo 'COMMANDER-LIVE:' . $host;
        exit;
    }

    $campaignsFile = __DIR__ . '/data/campaigns.json';
    if (!is_file($campaignsFile)) return;

    $all = json_decode((string) @file_get_contents($campaignsFile), true);
    if (!is_array($all)) return;

    foreach ($all as $c) {
        $cd = commanderNormalizeDomain($c['domain'] ?? '');
        if ($cd !== '' && $cd === $host) {
            $GLOBALS['__CLOAK_CAMPAIGN'] = $c;
            require __DIR__ . '/cloak-engine.php';
            exit;
        }
    }
}
commanderMaybeRouteCampaignDomain();

/**
 * Verifica, via HTTP, se o dominio da campanha realmente chega ate o COMMANDER.
 *
 * Faz uma requisicao para https://{dominio}/?__cmdr_ping=1 e confere se a
 * resposta contem a assinatura "COMMANDER-LIVE". Esse metodo funciona mesmo
 * com a Cloudflare na frente (proxy laranja), porque o teste segue o mesmo
 * caminho do visitante real ate o servidor de origem.
 *
 * Retorna:
 *  - 'connected'  : assinatura recebida (dominio ativo e apontando certo)
 *  - 'pending'    : dominio responde, mas ainda nao chega ao COMMANDER
 *  - 'error'      : dominio nao resolve / sem resposta
 * Em 'resolved' indica se esta protegido pela Cloudflare.
 */
function commanderCheckDomainStatus($domain, $commanderHost = '', $serverIp = '') {
    $domain = commanderNormalizeDomain($domain);
    $result = ['status' => 'pending', 'resolved' => ''];
    if ($domain === '') { $result['status'] = 'error'; return $result; }

    // Se o dominio nem resolve no DNS, e erro direto.
    $ip = @gethostbyname($domain);
    if (!$ip || $ip === $domain) {
        $result['status'] = 'error';
        return $result;
    }

    if (!function_exists('curl_init')) {
        // Sem cURL nao da pra testar via HTTP; assume pendente.
        $result['resolved'] = $ip;
        return $result;
    }

    // Tenta HTTPS e, em fallback, HTTP.
    foreach (['https', 'http'] as $scheme) {
        $ch = curl_init($scheme . '://' . $domain . '/?__cmdr_ping=1');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'COMMANDER-DomainCheck/1.0',
        ]);
        $raw = curl_exec($ch);
        $err = curl_errno($ch);
        curl_close($ch);

        if ($err !== 0 || $raw === false) continue;

        // Detecta Cloudflare pelos headers da resposta.
        $viaCloudflare = (stripos($raw, 'server: cloudflare') !== false)
            || (stripos($raw, 'cf-ray:') !== false);

        if (strpos($raw, 'COMMANDER-LIVE') !== false) {
            $result['status'] = 'connected';
            $result['resolved'] = $viaCloudflare ? 'Cloudflare (protegido)' : 'Direto';
            return $result;
        }

        // Respondeu algo, mas nao e o COMMANDER: aponta pra outro lugar.
        $result['status'] = 'pending';
        $result['resolved'] = $viaCloudflare ? 'Cloudflare (aguardando origem)' : 'Aponta para outro servidor';
    }

    return $result;
}

// ============================================
// REGISTRO DE DOMINIOS (data/domains.json)
// ============================================
// Os dominios sao entidades proprias: o usuario cadastra e verifica na aba
// Dominios, e o formulario de campanha oferece apenas os que estao ativos.

function commanderDomainsFile() {
    return __DIR__ . '/data/domains.json';
}

function commanderLoadDomains() {
    $file = commanderDomainsFile();
    if (!is_file($file)) return [];
    $data = json_decode((string) @file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function commanderSaveDomains($domains) {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return @file_put_contents(
        commanderDomainsFile(),
        json_encode(array_values($domains), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    ) !== false;
}

// ============================================
// INTEGRAÇÃO COM SISTEMA DE LOGIN DA RAIZ
// ============================================

// Inclui o sistema de protecao da raiz (pasta public, um nivel acima).
// Isso verifica se o usuario esta logado, se o dispositivo eh autorizado, etc.
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
// Force White - gestao da lista manual de bots -> white page
if (file_exists(__DIR__ . '/force-white.php')) {
    require_once __DIR__ . '/force-white.php';
}

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

// Usado pelo front-end (JS) para saber quem esta logado.
$loggedUser = [
    'username' => $usuarioLogado['username'] ?? ($usuarioLogado['email'] ?? 'default'),
    'is_admin' => $isAdmin,
];

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
                'domain' => commanderNormalizeDomain($_POST['domain'] ?? ''),
                'white_url' => $security->sanitizeInput($_POST['white_url'] ?? '', 'url'),
                'black_url' => $security->sanitizeInput($_POST['black_url'] ?? '', 'url'),
                'white_method' => $_POST['white_method'] ?? 'redirect',
                'black_method' => $_POST['black_method'] ?? 'redirect',
                'platform' => $_POST['platform'] ?? 'other',
                'status' => $_POST['status'] ?? 'active',
                'warmup_clicks' => max(0, (int)($_POST['warmup_clicks'] ?? 0)),
                'allowed_countries' => $_POST['allowed_countries'] ?? '',
                'verify_language' => $_POST['verify_language'] ?? 'auto',
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
            // Gera/atualiza o tracker "live" usado pelo dominio da campanha
            commanderWriteLiveTracker($campaignData);
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
                    if ($campaign) commanderDeleteLiveTracker($campaign);
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
            
        case 'add_domain':
            $newDomain = commanderNormalizeDomain($_POST['domain'] ?? '');
            if ($newDomain === '' || strpos($newDomain, '.') === false) {
                jsonResponse(['error' => 'Dominio invalido'], 400);
            }
            $registry = commanderLoadDomains();
            // Evita duplicados para o mesmo usuario
            foreach ($registry as $d) {
                if (($d['domain'] ?? '') === $newDomain && ($d['user_id'] ?? 'default') === $currentUserId) {
                    jsonResponse(['error' => 'Este dominio ja foi adicionado'], 409);
                }
            }
            $registry[] = [
                'id'         => uniqid('dom_', true),
                'user_id'    => $currentUserId,
                'domain'     => $newDomain,
                'status'     => 'pending',
                'resolved'   => '',
                'created_at' => date('c'),
                'last_check' => null,
            ];
            commanderSaveDomains($registry);
            jsonResponse(['success' => true, 'domain' => $newDomain]);
            break;

        case 'delete_domain':
            $domId = $_POST['domain_id'] ?? '';
            $registry = commanderLoadDomains();
            $registry = array_values(array_filter($registry, function($d) use ($domId, $currentUserId, $isAdmin) {
                if (($d['id'] ?? '') !== $domId) return true;
                // Só remove se pertencer ao usuario (ou admin)
                return !($isAdmin || ($d['user_id'] ?? 'default') === $currentUserId);
            }));
            commanderSaveDomains($registry);
            jsonResponse(['success' => true]);
            break;

        case 'verify_domain':
        case 'get_domains':
            $commanderHost = commanderNormalizeDomain($_SERVER['HTTP_HOST'] ?? '');
            $serverIp = $_SERVER['SERVER_ADDR'] ?? gethostbyname($commanderHost);

            $registry = commanderLoadDomains();
            $targetId = $_POST['domain_id'] ?? '';  // usado por verify_domain (opcional)
            // Mapa dominio -> nome da campanha que o usa
            $domainToCampaign = [];
            foreach (getCampaigns() as $c) {
                $cd = commanderNormalizeDomain($c['domain'] ?? '');
                if ($cd !== '') $domainToCampaign[$cd] = $c['name'] ?? '';
            }

            $out = [];
            $changed = false;
            foreach ($registry as &$d) {
                // Filtra por usuario
                if (!$isAdmin && ($d['user_id'] ?? 'default') !== $currentUserId) continue;

                // Rechecagem: sempre em get_domains; em verify_domain só o alvo (se informado)
                $shouldCheck = ($action === 'get_domains') || ($targetId === '' || ($d['id'] ?? '') === $targetId);
                if ($shouldCheck) {
                    $status = commanderCheckDomainStatus($d['domain'], $commanderHost, $serverIp);
                    $d['status'] = $status['status'];
                    $d['resolved'] = $status['resolved'];
                    $d['last_check'] = date('c');
                    $changed = true;
                }

                $dom = commanderNormalizeDomain($d['domain']);
                $out[] = [
                    'id'          => $d['id'] ?? '',
                    'domain'      => $dom,
                    'status'      => $d['status'] ?? 'pending',
                    'resolved'    => $d['resolved'] ?? '',
                    'last_check'  => $d['last_check'] ?? null,
                    'used_by'     => $domainToCampaign[$dom] ?? '',
                ];
            }
            unset($d);
            if ($changed) commanderSaveDomains($registry);

            jsonResponse([
                'domains' => $out,
                'commander_host' => $commanderHost,
                'server_ip' => $serverIp,
            ]);
            break;

        case 'get_active_domains':
            // Lista rapida (status em cache, sem rechecar) para o dropdown da campanha
            $registry = commanderLoadDomains();
            $active = [];
            foreach ($registry as $d) {
                if (!$isAdmin && ($d['user_id'] ?? 'default') !== $currentUserId) continue;
                if (($d['status'] ?? '') === 'connected') {
                    $active[] = commanderNormalizeDomain($d['domain']);
                }
            }
            jsonResponse(['domains' => $active]);
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
            
        // ===== FORCE WHITE (lista manual de bots -> white page) =====
        case 'get_force_white':
            if (!function_exists('getForceWhiteEntries')) {
                jsonResponse(['error' => 'Modulo force-white.php nao instalado'], 500);
            }
            $stats = function_exists('getLearnedBotsStats') ? getLearnedBotsStats() : null;
            jsonResponse([
                'entries'    => getForceWhiteEntries(),
                'bot_stats'  => $stats,
                'engine'     => (function_exists('botStorageStats') && botStorageStats() !== null) ? 'SQLite' : 'JSON'
            ]);
            break;
            
        case 'add_force_white':
            if (!function_exists('addForceWhiteEntry')) {
                jsonResponse(['error' => 'Modulo force-white.php nao instalado'], 500);
            }
            $raw = $_POST['entry'] ?? '';
            if (trim($raw) === '') {
                jsonResponse(['error' => 'Informe ao menos um IP ou faixa CIDR'], 400);
            }
            $result = addForceWhiteEntry($raw);
            if ($result['added'] === 0 && !empty($result['invalid'])) {
                jsonResponse(['error' => 'Entrada(s) invalida(s): ' . implode(', ', $result['invalid'])], 400);
            }
            jsonResponse([
                'success' => true,
                'added'   => $result['added'],
                'invalid' => $result['invalid'],
                'entries' => getForceWhiteEntries()
            ]);
            break;
            
        case 'remove_force_white':
            if (!function_exists('removeForceWhiteEntry')) {
                jsonResponse(['error' => 'Modulo force-white.php nao instalado'], 500);
            }
            $entry = trim($_POST['entry'] ?? '');
            if ($entry === '') {
                jsonResponse(['error' => 'Entrada invalida'], 400);
            }
            removeForceWhiteEntry($entry);
            jsonResponse(['success' => true, 'entries' => getForceWhiteEntries()]);
            break;
            
        case 'clean_logs':
            $days = (int) ($_POST['days'] ?? 30);
            cleanOldLogs($days);
            jsonResponse(['success' => true]);
            break;
            
        // ============================================
        // V3 - GATEWAYS E TRANSACOES
        // ============================================
        
        case 'get_gateways':
            $supported = getSupportedGateways();
            $configured = getUserGateways($currentUserId);
            jsonResponse([
                'supported' => $supported,
                'configured' => $configured
            ]);
            break;
            
        case 'save_gateway':
            $gatewayId = $security->sanitizeInput($_POST['gateway_id'] ?? '', 'alphanumeric');
            
            if (empty($gatewayId)) {
                jsonResponse(['error' => 'gateway_id obrigatorio', 'success' => false], 400);
            }
            
            $config = [
                'enabled' => true,
                'token' => $security->sanitizeInput($_POST['token'] ?? '', 'string'),
                'webhook_secret' => $security->sanitizeInput($_POST['webhook_secret'] ?? '', 'string')
            ];
            
            $result = saveUserGateway($currentUserId, $gatewayId, $config);
            $webhookUrl = generateWebhookUrl($gatewayId, $currentUserId);
            
            jsonResponse([
                'success' => $result,
                'webhook_url' => $webhookUrl,
                'user_id' => $currentUserId,
                'gateway_id' => $gatewayId
            ]);
            break;
            
        case 'remove_gateway':
            $gatewayId = $security->sanitizeInput($_POST['gateway_id'] ?? '', 'alphanumeric');
            
            if (empty($gatewayId)) {
                jsonResponse(['error' => 'gateway_id obrigatorio'], 400);
            }
            
            $result = removeUserGateway($currentUserId, $gatewayId);
            jsonResponse(['success' => $result]);
            break;
            
        case 'get_transactions':
            $gateway = $_POST['gateway'] ?? '';
            $status = $_POST['status'] ?? '';
            $dateFrom = $_POST['date_from'] ?? '';
            $dateTo = $_POST['date_to'] ?? '';
            $limit = (int)($_POST['limit'] ?? 100);
            
            $transactions = getTransactions([
                'user_id' => $isAdmin ? null : $currentUserId,
                'gateway' => $gateway,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'limit' => $limit
            ]);
            
            jsonResponse(['transactions' => $transactions]);
            break;
            
        case 'get_revenue':
            // Verifica se o Modo Demo esta ativo (somente admin)
            $fakeMode = getFakeModeData();
            if ($isAdmin && !empty($fakeMode['enabled'])) {
                // Retorna dados fake para o admin
                $fakeRevenue = buildFakeRevenue($fakeMode);
                jsonResponse(['revenue' => $fakeRevenue, 'demo_mode' => true]);
                break;
            }
            
            $gateway = $_POST['gateway'] ?? '';
            $dateFrom = $_POST['date_from'] ?? '';
            $dateTo = $_POST['date_to'] ?? '';
            
            $revenue = getRevenue([
                'user_id' => $isAdmin ? null : $currentUserId,
                'gateway' => $gateway,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);
            
            jsonResponse(['revenue' => $revenue]);
            break;
            
        // ============================================
        // PIXELS - Configuracao de conversao
        // ============================================
        
        case 'get_pixels':
            $pixels = getUserPixels($currentUserId);
            jsonResponse(['pixels' => $pixels]);
            break;
            
        case 'save_pixels':
            $pixelsJson = $_POST['pixels'] ?? '{}';
            $pixels = json_decode($pixelsJson, true);
            
            if (!is_array($pixels)) {
                jsonResponse(['error' => 'Formato invalido'], 400);
            }
            
            $result = saveUserPixels($currentUserId, $pixels);
            jsonResponse(['success' => $result]);
            break;
            
        case 'test_pixel':
            $platform = $security->sanitizeInput($_POST['platform'] ?? '', 'alphanumeric');
            
            if (empty($platform)) {
                jsonResponse(['error' => 'Plataforma nao especificada'], 400);
            }
            
            $result = testPixelFire($currentUserId, $platform);
            jsonResponse($result);
            break;
            
        default:
            jsonResponse(['error' => 'Acao invalida'], 400);
    }
    exit;
}

// ============================================
// FUNÇÕES DE GERAÇÃO DE CÓDIGO
// ============================================

// ============================================
// FUNCOES DE GERACAO DE CODIGO - V3 COMPLETO
// ============================================

function generateTrackerCode($campaign) {
    // Gera URL correta da API - sempre inclui /COMMANDERV3/
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // Pega o path atual e garante que inclui COMMANDERV3
    $currentPath = $_SERVER['REQUEST_URI'];
    
    // Se estiver em /COMMANDERV3/ ou /COMMANDERV3/index.php, usa esse path
    if (preg_match('#(/COMMANDERV3)#i', $currentPath, $matches)) {
        $basePath = $matches[1];
    } else {
        // Fallback - assume que esta na pasta COMMANDERV3
        $basePath = '/COMMANDERV3';
    }
    
    $apiUrl = $protocol . '://' . $host . $basePath . '/api.php';
    
    $slug = $campaign['slug'];
    $campaignName = addslashes($campaign['name']);
    $geradoEm = date('Y-m-d H:i:s');
    $whiteUrl = addslashes($campaign['white_url'] ?? '');
    $blackUrl = addslashes($campaign['black_url'] ?? '');
    $whiteMethod = $campaign['white_method'] ?? 'redirect';
    $blackMethod = $campaign['black_method'] ?? 'proxy';
    $verifyLanguage = $campaign['verify_language'] ?? 'auto';
    
    $code = <<<'TRACKER_CODE'
<?php
/**
 * COMMANDER V11 - Cloaking Profissional
 * Campanha: {{CAMPAIGN_NAME}}
 * Gerado em: {{GERADO_EM}}
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
$API_URL = '{{API_URL}}';
$CAMPAIGN_SLUG = '{{SLUG}}';
$WHITE_URL = '{{WHITE_URL}}';
$BLACK_URL = '{{BLACK_URL}}';
$WHITE_METHOD = '{{WHITE_METHOD}}';
$BLACK_METHOD = '{{BLACK_METHOD}}';
$VERIFY_LANGUAGE = '{{VERIFY_LANGUAGE}}';
$DEBUG_MODE = isset($_GET['debug']) && $_GET['debug'] === '1';

// ==========================================
// FASE 0: NAVEGACAO INTERNA DO PROXY COM SISTEMA DE PROFUNDIDADE
// ==========================================
// _bd=1 (primeiro clique): continua no proxy, vai para segunda pagina
// _bd>=2 (segundo clique): redireciona DIRETO para o site black real
if (isset($_GET['_nav']) && !empty($_GET['_nav'])) {
    $navUrl = base64_decode($_GET['_nav']);
    $depth = isset($_GET['_bd']) ? intval($_GET['_bd']) : 1;

    if ($navUrl && filter_var($navUrl, FILTER_VALIDATE_URL)) {
        if ($depth >= 2) {
            // Segundo clique: redireciona DIRETO para o site black real (sai do proxy)
            header('Location: ' . $navUrl);
            exit;
        }
        // Primeiro clique: continua no proxy com depth incrementado
        proxyRequest($navUrl, true, $depth + 1);
        exit;
    }
}

// Assets continuam via proxy
if (isset($_GET['_asset'])) {
    proxyRequest($WHITE_URL);
    exit;
}

// ==========================================
// FASE 1: CONSULTA INICIAL A API (sem dados JS)
// ==========================================
// Primeiro consulta a API para saber se vai para WHITE ou BLACK
// Se WHITE: vai direto SEM tela de verificacao (bot nao ve nada suspeito)
// Se BLACK: mostra tela de verificacao e coleta dados JS

$visitorIP = getVisitorIP();
$visitorUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

// UTMs e IDs de clique
$utmSource = $_GET['utm_source'] ?? '';
$utmMedium = $_GET['utm_medium'] ?? '';
$utmCampaign = $_GET['utm_campaign'] ?? '';
$utmContent = $_GET['utm_content'] ?? '';
$utmTerm = $_GET['utm_term'] ?? '';
$ttclid = $_GET['ttclid'] ?? '';
$gclid = $_GET['gclid'] ?? '';
$fbclid = $_GET['fbclid'] ?? '';

// Se ainda NAO passou pela FASE 1 (sem POST), faz consulta inicial
if (!isset($_POST['_detected'])) {
    
    // Consulta API SEM dados de deteccao JS (consulta inicial)
    $initialResponse = checkWithAPI($API_URL, [
        'action' => 'check',
        'campaign' => $CAMPAIGN_SLUG,
        'ip' => $visitorIP,
        'ua' => $visitorUA,
        'referer' => $referer,
        // PAIS: a pagina publicada fica atras do Cloudflare, entao ve o pais REAL do visitante
        'country' => getVisitorCountry(),
        'utm_source' => $utmSource,
        'utm_medium' => $utmMedium,
        'utm_campaign' => $utmCampaign,
        'utm_content' => $utmContent,
        'utm_term' => $utmTerm,
        'ttclid' => $ttclid,
        'gclid' => $gclid,
        'fbclid' => $fbclid,
        'initial_check' => '1'
    ]);
    
    // Se API diz WHITE: vai direto SEM tela de verificacao
    if ($initialResponse && isset($initialResponse['action']) && $initialResponse['action'] === 'white') {
        $method = $WHITE_METHOD;
        switch ($method) {
            case 'proxy':
                proxyRequest($WHITE_URL);
                break;
            case 'iframe':
            case 'shadow':
                echo '<!DOCTYPE html><html><head>';
                echo '<meta charset="UTF-8">';
                echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
                echo '<title>Carregando...</title>';
                echo '<style>*{margin:0;padding:0}html,body,iframe{width:100%;height:100%;border:none;overflow:hidden}</style>';
                echo '</head><body>';
                echo '<iframe src="' . htmlspecialchars($WHITE_URL) . '" frameborder="0" allowfullscreen></iframe>';
                echo '<script>if(window.history&&window.history.replaceState){window.history.replaceState({},document.title,window.location.pathname);}</script>';
                echo '</body></html>';
                break;
            case 'redirect':
            default:
                // Usa pagina intermediaria para limpar URL antes de redirecionar
                $cleanUrl = strtok($_SERVER['REQUEST_URI'], '?');
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $cleanUrl;
                echo '<!DOCTYPE html><html><head>';
                echo '<meta charset="UTF-8">';
                echo '<title>Redirecionando...</title>';
                echo '</head><body>';
                echo '<script>';
                echo 'if(window.history&&window.history.replaceState){window.history.replaceState({},document.title,"' . $baseUrl . '");}';
                echo 'window.location.replace("' . htmlspecialchars($WHITE_URL) . '");';
                echo '</script>';
                echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($WHITE_URL) . '"></noscript>';
                echo '</body></html>';
                break;
        }
        exit;
    }
    
    // Se API diz BLACK (ou nao respondeu): mostra FASE 1 de verificacao
    $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
                  . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    // ==========================================
    // I18N: idioma da tela de verificacao (definido na campanha)
    // $VERIFY_LANGUAGE = 'auto' -> detecta pelo Accept-Language do visitante
    // Qualquer outro valor (pt, en, es, ...) -> fixa o idioma escolhido
    // ==========================================
    $verifyI18n = [
        'pt' => ['Verificando se o site e seguro', 'Isso levara apenas alguns segundos...', 'Aguarde...'],
        'en' => ['Checking if the site is secure', 'This will only take a few seconds...', 'Please wait...'],
        'es' => ['Verificando si el sitio es seguro', 'Esto solo tomara unos segundos...', 'Espere...'],
        'fr' => ['Verification de la securite du site', 'Cela ne prendra que quelques secondes...', 'Veuillez patienter...'],
        'de' => ['Uberprufung der Sicherheit der Website', 'Dies dauert nur einige Sekunden...', 'Bitte warten...'],
        'it' => ['Verifica della sicurezza del sito', 'Ci vorranno solo pochi secondi...', 'Attendere...'],
        'nl' => ['Controleren of de site veilig is', 'Dit duurt slechts enkele seconden...', 'Even geduld...'],
        'ru' => ['Проверка безопасности сайта', 'Это займет всего несколько секунд...', 'Пожалуйста, подождите...'],
        'tr' => ['Sitenin guvenli olup olmadigi kontrol ediliyor', 'Bu yalnizca birkac saniye surecektir...', 'Lutfen bekleyin...'],
        'ar' => ['جار التحقق من امان الموقع', 'لن يستغرق هذا سوى بضع ثوان...', 'يرجى الانتظار...'],
        'ja' => ['サイトの安全性を確認しています', 'これには数秒しかかかりません...', 'お待ちください...'],
        'zh' => ['正在检查网站是否安全', '这只需要几秒钟...', '请稍候...'],
    ];

    $verifyLang = $VERIFY_LANGUAGE ?: 'auto';
    $verifyAuto = ($verifyLang === 'auto');
    if ($verifyAuto) {
        // Detecta pelo header Accept-Language; cai para ingles se nao reconhecer
        $verifyLang = 'en';
        if (preg_match('/([a-zA-Z]{2})/', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', $lm)) {
            $code = strtolower($lm[1]);
            if (isset($verifyI18n[$code])) { $verifyLang = $code; }
        }
    } elseif (!isset($verifyI18n[$verifyLang])) {
        $verifyLang = 'en';
    }
    $vt = $verifyI18n[$verifyLang];
    $vDir = ($verifyLang === 'ar') ? ' dir="rtl"' : '';
    
    // Pagina de carregamento estilo Cloudflare - so aparece para quem vai para BLACK
    echo '<!DOCTYPE html>
<html lang="' . htmlspecialchars($verifyLang) . '"' . $vDir . '><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . htmlspecialchars($vt[2]) . '</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{width:100%;height:100%;background:#ffffff}
</style>
</head><body>
<input type="hidden" id="vAuto" value="' . ($verifyAuto ? '1' : '0') . '">
<form id="f" method="POST" action="' . htmlspecialchars($currentUrl) . '">
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
<input type="hidden" name="canvas_fp" id="cfp">
<input type="hidden" name="audio_fp" id="afp">
<input type="hidden" name="webgl_fp" id="wfp">
<input type="hidden" name="emulator_score" id="ems">
<input type="hidden" name="client_country" id="cc">
<input type="hidden" name="client_languages" id="cl">
</form>
<script>
(function(){
    // I18N (modo automatico): ajusta o texto pelo idioma REAL do navegador.
    // So roda quando a campanha esta em "auto"; idioma fixo nao e sobrescrito.
    if(document.getElementById("vAuto")&&document.getElementById("vAuto").value==="1"){
        var I18N={
            "pt":["Verificando se o site e seguro","Isso levara apenas alguns segundos..."],
            "en":["Checking if the site is secure","This will only take a few seconds..."],
            "es":["Verificando si el sitio es seguro","Esto solo tomara unos segundos..."],
            "fr":["Verification de la securite du site","Cela ne prendra que quelques secondes..."],
            "de":["Uberprufung der Sicherheit der Website","Dies dauert nur einige Sekunden..."],
            "it":["Verifica della sicurezza del sito","Ci vorranno solo pochi secondi..."],
            "nl":["Controleren of de site veilig is","Dit duurt slechts enkele seconden..."],
            "ru":["Проверка безопасности сайта","Это займет всего несколько секунд..."],
            "tr":["Sitenin guvenli olup olmadigi kontrol ediliyor","Bu yalnizca birkac saniye surecektir..."],
            "ar":["جار التحقق من امان الموق��","لن يستغرق هذا سوى بضع ثوان..."],
            "ja":["サイトの安全性を確認しています","これには数秒しかかかりません..."],
            "zh":["正在检查网站是否安全","这只需要几秒钟..."]
        };
        try{
            var lng=(navigator.language||(navigator.languages&&navigator.languages[0])||"en").toLowerCase().slice(0,2);
            var t=I18N[lng];
            if(t){
                var tEl=document.getElementById("vTitle"),sEl=document.getElementById("vSub");
                if(tEl)tEl.textContent=t[0];
                if(sEl)sEl.textContent=t[1];
                document.documentElement.lang=lng;
                document.documentElement.dir=(lng==="ar")?"rtl":"ltr";
            }
        }catch(e){}
    }
    var f=document.getElementById("f");
    var flags=[];
    var emulatorScore=0;
    
    // ========== CANVAS FINGERPRINT ==========
    function getCanvasFP(){
        try{
            var c=document.createElement("canvas");
            c.width=200;c.height=50;
            var ctx=c.getContext("2d");
            ctx.textBaseline="top";
            ctx.font="14px Arial";
            ctx.fillStyle="#f60";
            ctx.fillRect(0,0,100,50);
            ctx.fillStyle="#069";
            ctx.fillText("Cwm fjord",2,2);
            ctx.fillStyle="rgba(102,204,0,0.7)";
            ctx.fillText("vex quiz",4,17);
            var d=c.toDataURL();
            // Hash simples
            var h=0;for(var i=0;i<d.length;i++){h=((h<<5)-h)+d.charCodeAt(i);h=h&h;}
            return Math.abs(h).toString(16);
        }catch(e){return "err";}
    }
    
    // ========== AUDIO FINGERPRINT ==========
    function getAudioFP(cb){
        try{
            var AC=window.AudioContext||window.webkitAudioContext;
            if(!AC){cb("unsupported");return;}
            var ctx=new AC();
            var osc=ctx.createOscillator();
            var analyser=ctx.createAnalyser();
            var gain=ctx.createGain();
            var processor=ctx.createScriptProcessor(4096,1,1);
            
            gain.gain.value=0;
            osc.type="triangle";
            osc.frequency.value=10000;
            
            osc.connect(analyser);
            analyser.connect(processor);
            processor.connect(gain);
            gain.connect(ctx.destination);
            
            var fp=[];
            processor.onaudioprocess=function(e){
                var d=new Float32Array(analyser.frequencyBinCount);
                analyser.getFloatFrequencyData(d);
                for(var i=0;i<10;i++)fp.push(d[i]||0);
                osc.disconnect();processor.disconnect();gain.disconnect();ctx.close();
                var h=0;for(var j=0;j<fp.length;j++){h=((h<<5)-h)+Math.round(fp[j]*1000);h=h&h;}
                cb(Math.abs(h).toString(16));
            };
            osc.start(0);
        }catch(e){cb("err");}
    }
    
    // ========== WEBGL FINGERPRINT ==========
    function getWebGLFP(){
        try{
            var c=document.createElement("canvas");
            var gl=c.getContext("webgl")||c.getContext("experimental-webgl");
            if(!gl)return "unsupported";
            var dbg=gl.getExtension("WEBGL_debug_renderer_info");
            var vendor=dbg?gl.getParameter(dbg.UNMASKED_VENDOR_WEBGL):"";
            var renderer=dbg?gl.getParameter(dbg.UNMASKED_RENDERER_WEBGL):"";
            return vendor.substring(0,20)+"|"+renderer.substring(0,30);
        }catch(e){return "err";}
    }
    
    // ========== DETECCAO DE EMULADOR ==========
    function detectEmulator(){
        var score=0;
        
        // 1. Canvas vazio ou uniforme (emulador nao renderiza corretamente)
        try{
            var c=document.createElement("canvas");
            c.width=16;c.height=16;
            var ctx=c.getContext("2d");
            ctx.fillStyle="#f00";ctx.fillRect(0,0,8,8);
            ctx.fillStyle="#0f0";ctx.fillRect(8,0,8,8);
            ctx.fillStyle="#00f";ctx.fillRect(0,8,8,8);
            ctx.fillStyle="#ff0";ctx.fillRect(8,8,8,8);
            var d=ctx.getImageData(0,0,16,16).data;
            var unique=new Set();for(var i=0;i<d.length;i+=4)unique.add(d[i]+","+d[i+1]+","+d[i+2]);
            if(unique.size<4){score+=30;flags.push("canvas_uniform");}
        }catch(e){}
        
        // 2. Touch events inconsistentes
        if(("ontouchstart" in window)&&navigator.maxTouchPoints===0){score+=20;flags.push("touch_inconsistent");}
        
        // 3. DeviceMemory muito baixa ou alta (emuladores tem valores estranhos)
        var mem=navigator.deviceMemory||0;
        if(mem>0&&(mem<0.5||mem>64)){score+=15;flags.push("memory_odd");}
        
        // 4. Hardware concurrency estranha
        var cores=navigator.hardwareConcurrency||0;
        if(cores>0&&(cores===1||cores>32)){score+=10;flags.push("cores_odd");}
        
        // 5. Screen dimensions suspeitas (emuladores usam tamanhos padrao)
        var w=screen.width,h=screen.height;
        var emulatorSizes=["360x640","375x667","414x896","360x740","412x915","393x873"];
        if(emulatorSizes.indexOf(w+"x"+h)>-1){score+=5;flags.push("common_emulator_size");}
        
        // 6. WebGL renderer suspeito
        var wgl=getWebGLFP();
        if(wgl.indexOf("SwiftShader")>-1||wgl.indexOf("llvmpipe")>-1||wgl.indexOf("VirtualBox")>-1){
            score+=40;flags.push("software_renderer");
        }
        
        // 7. Battery API (emuladores geralmente nao tem)
        if(!navigator.getBattery){score+=5;flags.push("no_battery");}
        
        // 8. Automation flags
        if(navigator.webdriver){score+=50;flags.push("webdriver");}
        if(window.callPhantom||window._phantom){score+=50;flags.push("phantom");}
        if(window.__nightmare){score+=50;flags.push("nightmare");}
        if(document.documentElement.getAttribute("webdriver")){score+=50;flags.push("webdriver_attr");}
        
        return score;
    }
    
    // ========== COLETA E ENVIO ==========
    var canvasFP=getCanvasFP();
    var webglFP=getWebGLFP();
    emulatorScore=detectEmulator();

    // DETECCAO DE PAIS GLOBAL: deriva o pais do navegador real do visitante (timezone IANA).
    // Serve de fallback caso os headers de geo do servidor falhem.
    function detectClientCountry(){
        try{
            try{var loc=new Intl.Locale(navigator.language||"");if(loc&&loc.maximize){var r=loc.maximize().region;if(r&&r.length===2)return r.toUpperCase();}}catch(e){}
            var tz=(Intl.DateTimeFormat().resolvedOptions().timeZone||"").trim();
            var TZ={"America/Sao_Paulo":"BR","America/Bahia":"BR","America/Fortaleza":"BR","America/Recife":"BR","America/Manaus":"BR","America/Belem":"BR","America/Cuiaba":"BR","America/Campo_Grande":"BR","America/Porto_Velho":"BR","America/Rio_Branco":"BR","America/Maceio":"BR","America/Araguaina":"BR","America/Noronha":"BR","America/Argentina/Buenos_Aires":"AR","America/Santiago":"CL","America/Bogota":"CO","America/Lima":"PE","America/Caracas":"VE","America/La_Paz":"BO","America/Asuncion":"PY","America/Montevideo":"UY","America/Guayaquil":"EC","America/New_York":"US","America/Chicago":"US","America/Denver":"US","America/Phoenix":"US","America/Los_Angeles":"US","America/Anchorage":"US","Pacific/Honolulu":"US","America/Toronto":"CA","America/Vancouver":"CA","America/Mexico_City":"MX","America/Tijuana":"MX","America/Guatemala":"GT","America/Costa_Rica":"CR","America/Panama":"PA","America/Santo_Domingo":"DO","America/Havana":"CU","America/Puerto_Rico":"PR","Europe/Lisbon":"PT","Atlantic/Madeira":"PT","Atlantic/Azores":"PT","Europe/Madrid":"ES","Atlantic/Canary":"ES","Europe/London":"GB","Europe/Dublin":"IE","Europe/Paris":"FR","Europe/Berlin":"DE","Europe/Rome":"IT","Europe/Amsterdam":"NL","Europe/Brussels":"BE","Europe/Zurich":"CH","Europe/Vienna":"AT","Europe/Warsaw":"PL","Europe/Prague":"CZ","Europe/Budapest":"HU","Europe/Bucharest":"RO","Europe/Athens":"GR","Europe/Stockholm":"SE","Europe/Oslo":"NO","Europe/Copenhagen":"DK","Europe/Helsinki":"FI","Europe/Moscow":"RU","Europe/Kiev":"UA","Europe/Istanbul":"TR","Asia/Tokyo":"JP","Asia/Shanghai":"CN","Asia/Hong_Kong":"HK","Asia/Singapore":"SG","Asia/Seoul":"KR","Asia/Kolkata":"IN","Asia/Calcutta":"IN","Asia/Bangkok":"TH","Asia/Jakarta":"ID","Asia/Manila":"PH","Asia/Ho_Chi_Minh":"VN","Asia/Taipei":"TW","Asia/Kuala_Lumpur":"MY","Asia/Dubai":"AE","Asia/Riyadh":"SA","Asia/Jerusalem":"IL","Asia/Tehran":"IR","Asia/Karachi":"PK","Australia/Sydney":"AU","Australia/Melbourne":"AU","Australia/Perth":"AU","Australia/Brisbane":"AU","Pacific/Auckland":"NZ","Africa/Johannesburg":"ZA","Africa/Lagos":"NG","Africa/Cairo":"EG","Africa/Nairobi":"KE","Africa/Casablanca":"MA","Africa/Algiers":"DZ","Africa/Luanda":"AO","Africa/Maputo":"MZ"};
            if(tz&&TZ[tz])return TZ[tz];
            var lang=(navigator.language||(navigator.languages&&navigator.languages[0])||"");var m=lang.match(/[-_]([A-Za-z]{2})$/);if(m)return m[1].toUpperCase();
        }catch(e){}
        return "";
    }
    var clientCountry=detectClientCountry();
    var clientLangs=navigator.languages?navigator.languages.join(","):navigator.language||"";

    getAudioFP(function(audioFP){
        f.wd.value=navigator.webdriver?"1":"0";
        f.pc.value=navigator.plugins?navigator.plugins.length:0;
        f.lg.value=navigator.languages?navigator.languages.join(","):navigator.language||"";
        f.sw.value=screen.width||0;
        f.sh.value=screen.height||0;
        f.tz.value=Intl.DateTimeFormat().resolvedOptions().timeZone||"";
        f.ts.value=("ontouchstart"in window||navigator.maxTouchPoints>0)?"1":"0";
        f.pf.value=navigator.platform||"";
        f.abs.value=emulatorScore;
        f.abf.value=flags.join("|");
        f.cfp.value=canvasFP;
        f.afp.value=audioFP;
        f.wfp.value=webglFP;
        f.ems.value=emulatorScore;
        f.cc.value=clientCountry;
        f.cl.value=clientLangs;
        f.submit();
    });

    // Fallback: se audio demorar muito, envia sem
    setTimeout(function(){
        if(!f._submitted){f._submitted=true;f.cc.value=clientCountry;f.cl.value=clientLangs;f.submit();}
    },2000);
})();
</script>
<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($WHITE_URL) . '"></noscript>
</body></html>';
    exit;
}

// ==========================================
// FASE 2: PROCESSAMENTO COM DADOS DE DETECCAO JS (so chega aqui se vai para BLACK)
// ==========================================

// Dados de deteccao enviados pelo JS via POST
$webdriver = $_POST['webdriver'] ?? '';
$pluginsCount = $_POST['plugins_count'] ?? -1;
$languages = $_POST['languages'] ?? '';
$screenWidth = $_POST['screen_width'] ?? 0;
$screenHeight = $_POST['screen_height'] ?? 0;
$timezone = $_POST['timezone'] ?? '';
$touchSupport = $_POST['touch_support'] ?? '';
$platform = $_POST['platform'] ?? '';

// Dados de deteccao avancada (score de bot headless)
$advancedBotScore = $_POST['advanced_bot_score'] ?? '';
$advancedBotFlags = $_POST['advanced_bot_flags'] ?? '';

// Dados de fingerprinting
$canvasFP = $_POST['canvas_fp'] ?? '';
$audioFP = $_POST['audio_fp'] ?? '';
$webglFP = $_POST['webgl_fp'] ?? '';
$emulatorScore = $_POST['emulator_score'] ?? '';

// Pais resolvido: headers de geo (Cloudflare) + deteccao do navegador (timezone) como fallback global
$clientCountry = $_POST['client_country'] ?? '';
$clientLanguages = $_POST['client_languages'] ?? '';
$visitorCountry = getVisitorCountry($clientCountry, $clientLanguages);

// Envia para API com todos os dados de deteccao
$response = checkWithAPI($API_URL, [
    'action' => 'check',
    'campaign' => $CAMPAIGN_SLUG,
    'ip' => $visitorIP,
    'ua' => $visitorUA,
    'referer' => $referer,
    // PAIS: resolvido por headers de geo (Cloudflare) + deteccao do navegador
    'country' => $visitorCountry,
    'utm_source' => $utmSource,
    'utm_medium' => $utmMedium,
    'utm_campaign' => $utmCampaign,
    'utm_content' => $utmContent,
    'utm_term' => $utmTerm,
    'ttclid' => $ttclid,
    'gclid' => $gclid,
    'fbclid' => $fbclid,
    // Dados de deteccao basica
    'webdriver' => $webdriver,
    'plugins_count' => $pluginsCount,
    'languages' => $languages,
    'screen_width' => $screenWidth,
    'screen_height' => $screenHeight,
    'timezone' => $timezone,
    'touch_support' => $touchSupport,
    'platform' => $platform,
    // Dados de deteccao AVANCADA
    'advanced_bot_score' => $advancedBotScore,
    'advanced_bot_flags' => $advancedBotFlags,
    // Fingerprinting
    'canvas_fp' => $canvasFP,
    'audio_fp' => $audioFP,
    'webgl_fp' => $webglFP,
    'emulator_score' => $emulatorScore
]);

if ($DEBUG_MODE) {
    echo '<pre>DEBUG Response: '; print_r($response); echo '</pre>';
    exit;
}

// Processa resposta
if ($response && isset($response['action'])) {
    
    // Define URL e método de redirecionamento
    if ($response['action'] === 'black') {
        $targetUrl = $BLACK_URL;
        $method = $BLACK_METHOD;
        $isBlack = true;
    } else {
        $targetUrl = $WHITE_URL;
        $method = $WHITE_METHOD;
        $isBlack = false;
    }
    
    // Aplica o metodo de redirecionamento
    switch ($method) {
        case 'proxy':
            // Proxy puro - codigo fonte = alvo, visual = alvo
            proxyRequest($targetUrl);
            break;
            
        case 'iframe':
            // MODO STEALTH via iframe: codigo fonte WHITE, visual BLACK
            if ($isBlack) {
                proxyRequestStealth($WHITE_URL, $BLACK_URL);
            } else {
                // Para WHITE, iframe normal
                echo '<!DOCTYPE html><html><head>';
                echo '<meta charset="UTF-8">';
                echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
                echo '<title>Carregando...</title>';
                echo '<style>*{margin:0;padding:0}html,body,iframe{width:100%;height:100%;border:none;overflow:hidden}</style>';
                echo '</head><body>';
                echo '<iframe src="' . htmlspecialchars($targetUrl) . '" frameborder="0" allowfullscreen></iframe>';
                echo '</body></html>';
                exit;
            }
            break;
            
        case 'shadow':
            // MODO STEALTH via Shadow DOM: codigo fonte WHITE, visual BLACK
            if ($isBlack) {
                proxyRequestShadow($WHITE_URL, $BLACK_URL);
            } else {
                // Para WHITE, proxy normal
                proxyRequest($targetUrl);
            }
            break;
            
        case 'redirect':
        default:
            // Redirect HTTP 302 (padrao)
            header('Location: ' . $targetUrl);
            exit;
    }
    
} else {
    // Erro na API - vai para white page por seguranca
    header('Location: ' . $WHITE_URL);
    exit;
}

// ============================================
// FUNCOES
// ============================================

function getVisitorIP() {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function getVisitorCountry($clientCountry = '', $clientLanguages = '') {
    // METODO 1: Cloudflare (a pagina publicada fica atras do CF e ve o IP REAL do visitante)
    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        $c = strtoupper(trim($_SERVER['HTTP_CF_IPCOUNTRY']));
        if (strlen($c) === 2 && $c !== 'XX' && $c !== 'T1') { return $c; }
    }
    // METODO 2: Vercel
    if (!empty($_SERVER['HTTP_X_VERCEL_IP_COUNTRY'])) {
        $c = strtoupper(trim($_SERVER['HTTP_X_VERCEL_IP_COUNTRY']));
        if (strlen($c) === 2) { return $c; }
    }
    // METODO 3: outros headers de geo do servidor
    foreach (['GEOIP_COUNTRY_CODE','HTTP_X_COUNTRY_CODE','HTTP_X_GEO_COUNTRY','HTTP_X_REAL_COUNTRY'] as $h) {
        if (!empty($_SERVER[$h])) {
            $c = strtoupper(trim($_SERVER[$h]));
            if (strlen($c) === 2 && $c !== 'XX') { return $c; }
        }
    }
    // METODO 4 (GLOBAL): pais detectado pelo navegador do visitante (timezone) na FASE 2
    // Serve de fallback caso o header de geo do Cloudflare nao chegue.
    $clientCountry = strtoupper(trim($clientCountry));
    if (preg_match('/^[A-Z]{2}$/', $clientCountry) && $clientCountry !== 'XX') { return $clientCountry; }
    // METODO 5: idioma do navegador com regiao (ex: pt-BR -> BR)
    if (!empty($clientLanguages)) {
        foreach (explode(',', $clientLanguages) as $lang) {
            if (preg_match('/[-_]([A-Za-z]{2})$/', trim($lang), $m)) { return strtoupper($m[1]); }
        }
    }
    return 'XX';
}

function checkWithAPI($url, $data) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json']
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

/**
 * Proxy Reverso V5 - COM PROTECAO DE BLACK PAGE
 * - Busca CSS externo e reescreve URLs internas (fontes, @import, url())
 * - Remove integrity/crossorigin que quebram recursos reescritos
 * - Suporta fontes CORS e cache de assets
 * - Intercepta fetch/XHR para requisicoes AJAX
 * - NOVO: Injeta Guardian na black page para detectar bots durante navegacao
 * 
 * @param string $targetUrl URL de destino
 * @param bool $isBlackPage Se true, injeta Guardian para protecao extra
 * @param string $whiteUrl URL da white page para redirecionamento se bot detectado
 */
function proxyRequest($targetUrl, $isBlackPage = false, $depth = 1, $whiteUrl = '') {
    // URL atual do proxy (dominio limpo)
    $proxyHost = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    
    // Suporta navegacao interna via parametro _nav
    // Quando usuario clica em links dentro da pagina proxeada
    if (isset($_GET['_nav']) && !empty($_GET['_nav'])) {
        $targetUrl = base64_decode($_GET['_nav']);
    }
    
    // ===========================================
    // BUSCA ASSETS (CSS, JS, FONTES, IMAGENS)
    // ===========================================
    if (isset($_GET['_asset']) && !empty($_GET['_asset'])) {
        $assetUrl = base64_decode($_GET['_asset']);
        
        $ch = curl_init($assetUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0',
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
                'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8'
            ]
        ]);
        
        $assetContent = curl_exec($ch);
        $assetHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $assetContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
        curl_close($ch);
        
        if ($assetContent === false) {
            header('HTTP/1.1 502 Bad Gateway');
            exit;
        }
        
        // Se for CSS, reescreve URLs internas
        if (stripos($assetContentType, 'text/css') !== false || preg_match('/\.css(\?|$)/i', $assetUrl)) {
            $assetParsed = parse_url($assetUrl);
            $assetBaseUrl = $assetParsed['scheme'] . '://' . $assetParsed['host'];
            $assetBasePath = isset($assetParsed['path']) ? dirname($assetParsed['path']) : '';
            if ($assetBasePath === '/' || $assetBasePath === '.' || $assetBasePath === '\\') $assetBasePath = '';
            
            // Reescreve url() dentro do CSS
            $assetContent = preg_replace_callback(
                '/url\s*\(\s*[\"\']?([^\"\'\)]+)[\"\']?\s*\)/i',
                function($m) use ($assetBaseUrl, $assetBasePath, $proxyHost) {
                    $url = trim($m[1]);
                    if (preg_match('/^(data:|#)/i', $url)) return $m[0];
                    // Converte para absoluta
                    if (preg_match('/^https?:\/\//i', $url)) {
                        $absolute = $url;
                    } elseif (strpos($url, '//') === 0) {
                        $absolute = 'https:' . $url;
                    } elseif (strpos($url, '/') === 0) {
                        $absolute = $assetBaseUrl . $url;
                    } else {
                        $absolute = $assetBaseUrl . $assetBasePath . '/' . $url;
                    }
                    // Passa pelo proxy para fontes e imagens
                    return 'url("' . $proxyHost . '/?_asset=' . base64_encode($absolute) . '")';
                },
                $assetContent
            );
            
            // Reescreve @import
            $assetContent = preg_replace_callback(
                '/@import\s+[\"\']([^\"\']+)[\"\'];?/i',
                function($m) use ($assetBaseUrl, $assetBasePath, $proxyHost) {
                    $url = trim($m[1]);
                    if (preg_match('/^https?:\/\//i', $url)) {
                        $absolute = $url;
                    } elseif (strpos($url, '//') === 0) {
                        $absolute = 'https:' . $url;
                    } elseif (strpos($url, '/') === 0) {
                        $absolute = $assetBaseUrl . $url;
                    } else {
                        $absolute = $assetBaseUrl . $assetBasePath . '/' . $url;
                    }
                    return '@import "' . $proxyHost . '/?_asset=' . base64_encode($absolute) . '";';
                },
                $assetContent
            );
            
            $assetContentType = 'text/css; charset=UTF-8';
        }
        
        // Headers
        header('HTTP/1.1 ' . $assetHttpCode);
        header('Content-Type: ' . $assetContentType);
        header('Cache-Control: public, max-age=86400');
        header('Access-Control-Allow-Origin: *');
        
        echo $assetContent;
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
    $isNavigationPost = isset($_GET['_nav']) && $_SERVER['REQUEST_METHOD'] === 'POST';
    $isDetectionPost = isset($_POST['_detected']);
    
    // So faz POST real se for navegacao interna (formulario na black page), nao deteccao
    $isPost = $isNavigationPost && !$isDetectionPost;
    $postData = $isPost ? file_get_contents('php://input') : null;
    
    $ch = curl_init($targetUrl);
    $curlOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0',
        CURLOPT_HTTPHEADER => [
            'X-Forwarded-For: ' . getVisitorIP(),
            'X-Forwarded-Proto: https',
            'Referer: ' . $targetUrl,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8'
        ]
    ];
    
    if ($isPost) {
        $curlOpts[CURLOPT_POST] = true;
        $curlOpts[CURLOPT_POSTFIELDS] = $postData;
        $curlOpts[CURLOPT_HTTPHEADER][] = 'Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'application/x-www-form-urlencoded');
    }
    
    curl_setopt_array($ch, $curlOpts);
    
    $response = curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    if ($response === false) {
        header('HTTP/1.1 503 Service Unavailable');
        echo 'Erro ao conectar ao servidor remoto';
        exit;
    }
    
    // Se nao for HTML, retorna diretamente
    if (stripos($contentType, 'text/html') === false && stripos($contentType, 'application/xhtml') === false) {
        header('HTTP/1.1 ' . $httpCode);
        header('Content-Type: ' . $contentType);
        header('Cache-Control: public, max-age=86400');
        echo $response;
        exit;
    }
    
    // Extrai base URL do destino
    $parsed = parse_url($finalUrl);
    $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];
    $basePath = isset($parsed['path']) ? dirname($parsed['path']) : '';
    if ($basePath === '/' || $basePath === '\\' || $basePath === '.') {
        $basePath = '';
    }
    
    // Funcao para converter URL relativa em absoluta
    $makeAbsolute = function($url) use ($baseUrl, $basePath) {
        $url = trim($url);
        if (preg_match('/^https?:\/\//i', $url)) return $url;
        if (preg_match('/^(data:|javascript:|mailto:|tel:|#)/i', $url)) return $url;
        if (strpos($url, '//') === 0) return 'https:' . $url;
        if (strpos($url, '/') === 0) return $baseUrl . $url;
        return $baseUrl . $basePath . '/' . $url;
    };
    
    // Funcao para criar link do proxy (URL limpa) com depth
    $makeProxyLink = function($url) use ($proxyHost, $makeAbsolute, $depth) {
        $url = trim($url);
        if (preg_match('/^(javascript:|mailto:|tel:|#|data:)/i', $url)) {
            return $url;
        }
        $absolute = $makeAbsolute($url);
        return $proxyHost . '/?_nav=' . base64_encode($absolute) . '&_bd=' . $depth;
    };
    
    // Funcao para criar URL de asset via proxy
    $makeAssetProxy = function($url) use ($proxyHost, $makeAbsolute) {
        $url = trim($url);
        if (preg_match('/^(data:|javascript:|mailto:|tel:|#)/i', $url)) {
            return $url;
        }
        $absolute = $makeAbsolute($url);
        return $proxyHost . '/?_asset=' . base64_encode($absolute);
    };
    
    // =============================================
    // REMOVE INTEGRITY E CROSSORIGIN (quebram CSS/JS reescritos)
    // =============================================
    $response = preg_replace('/\s+integrity=[\"\'][^\"\']*[\"\']/i', '', $response);
    $response = preg_replace('/\s+crossorigin(?:=[\"\'][^\"\']*[\"\'"])?/i', '', $response);
    
    // =============================================
    // REESCREVE CSS VIA PROXY (para corrigir fontes e @import)
    // =============================================
    $response = preg_replace_callback(
        '/<link([^>]*)(href=[\"\']([^\"\']+)[\"\'])([^>]*)>/is',
        function($m) use ($makeAssetProxy) {
            $attrs = $m[1] . $m[4];
            // Verifica se eh stylesheet
            if (stripos($attrs, 'stylesheet') !== false || preg_match('/\.css(\?|$)/i', $m[3])) {
                return '<link' . $m[1] . 'href="' . $makeAssetProxy($m[3]) . '"' . $m[4] . '>';
            }
            // Outros links (favicon, preload, etc) - URL absoluta direta
            return '<link' . $m[1] . 'href="' . $makeAssetProxy($m[3]) . '"' . $m[4] . '>';
        },
        $response
    );
    
    // Scripts: <script src="...">
    $response = preg_replace_callback(
        '/<script([^>]*)src=[\"\']([^\"\']+)[\"\']([^>]*)>/is',
        function($m) use ($makeAbsolute) {
            return '<script' . $m[1] . 'src="' . $makeAbsolute($m[2]) . '"' . $m[3] . '>';
        },
        $response
    );
    
    // Imagens: <img src="...">
    $response = preg_replace_callback(
        '/<img([^>]*)src=[\"\']([^\"\']+)[\"\']([^>]*)>/is',
        function($m) use ($makeAbsolute) {
            return '<img' . $m[1] . 'src="' . $makeAbsolute($m[2]) . '"' . $m[3] . '>';
        },
        $response
    );
    
    // Videos/Audio: <video src="...">, <source src="...">, <audio src="...">
    $response = preg_replace_callback(
        '/<(video|source|audio)([^>]*)src=[\"\']([^\"\']+)[\"\']([^>]*)>/is',
        function($m) use ($makeAbsolute) {
            return '<' . $m[1] . $m[2] . 'src="' . $makeAbsolute($m[3]) . '"' . $m[4] . '>';
        },
        $response
    );
    
    // Poster de video
    $response = preg_replace_callback(
        '/poster=[\"\']([^\"\']+)[\"\']/is',
        function($m) use ($makeAbsolute) {
            return 'poster="' . $makeAbsolute($m[1]) . '"';
        },
        $response
    );
    
    // Background em style inline: url(...)
    $response = preg_replace_callback(
        '/(style=[\"\'][^\"\']*?)url\s*\(\s*[\"\']?([^\"\'\)]+)[\"\']?\s*\)/is',
        function($m) use ($makeAbsolute) {
            $url = trim($m[2]);
            if (preg_match('/^(data:|#)/i', $url)) return $m[0];
            return $m[1] . 'url("' . $makeAbsolute($url) . '")';
        },
        $response
    );
    
    // Srcset
    $response = preg_replace_callback(
        '/srcset=[\"\']([^\"\']+)[\"\']/is',
        function($m) use ($makeAbsolute) {
            $srcset = $m[1];
            $parts = preg_split('/\s*,\s*/', $srcset);
            $newParts = [];
            foreach ($parts as $part) {
                if (preg_match('/^(.+?)(\s+\d+[wx])?$/i', trim($part), $pm)) {
                    $newParts[] = $makeAbsolute($pm[1]) . ($pm[2] ?? '');
                }
            }
            return 'srcset="' . implode(', ', $newParts) . '"';
        },
        $response
    );
    
    // Data-src, data-bg, etc (lazy loading)
    $response = preg_replace_callback(
        '/data-(?:src|bg|background|lazy-src|original|image)=[\"\']([^\"\']+)[\"\']/is',
        function($m) use ($makeAbsolute) {
            return 'data-src="' . $makeAbsolute($m[1]) . '"';
        },
        $response
    );
    
    // =============================================
    // REESCREVE LINKS <a href> PARA PROXY (URL LIMPA)
    // =============================================
    $response = preg_replace_callback(
        '/<a([^>]*)href=[\"\']([^\"\']+)[\"\']([^>]*)>/is',
        function($m) use ($makeProxyLink, $baseUrl) {
            $href = trim($m[2]);
            // Links externos (outros dominios) - abre direto
            if (preg_match('/^https?:\/\//i', $href) && strpos($href, $baseUrl) !== 0) {
                return $m[0];
            }
            return '<a' . $m[1] . 'href="' . $makeProxyLink($href) . '"' . $m[3] . '>';
        },
        $response
    );
    
    // =============================================
    // REESCREVE FORMULARIOS PARA PROXY
    // =============================================
    $response = preg_replace_callback(
        '/<form([^>]*)action=[\"\']([^\"\']*)[\"\']([^>]*)>/is',
        function($m) use ($makeProxyLink) {
            $action = trim($m[2]);
            return '<form' . $m[1] . 'action="' . $makeProxyLink($action) . '"' . $m[3] . '>';
        },
        $response
    );
    
    // =============================================
    // ADICIONA TAG <BASE> PARA RESOLVER RESTANTES
    // =============================================
    $baseTag = '<base href="' . $baseUrl . $basePath . '/">';
    if (stripos($response, '<head') !== false && stripos($response, '<base') === false) {
        $response = preg_replace('/(<head[^>]*>)/i', '$1' . "\n" . $baseTag, $response, 1);
    }
    
    // =============================================
    // SCRIPT PARA INTERCEPTAR NAVEGACAO DINAMICA
    // depth=1: primeiro clique continua via proxy
    // depth>=2: segundo clique redireciona DIRETO para site real
    // =============================================
    $shouldRedirectDirect = ($depth >= 2) ? 'true' : 'false';
    $currentUrl = $targetUrl;
    
    $proxyScript = '<script>
    (function(){
        var proxyBase = "' . $proxyHost . '/?_nav=";
        var assetBase = "' . $proxyHost . '/?_asset=";
        var targetBase = "' . $baseUrl . '";
        var currentUrl = "' . addslashes($currentUrl) . '";
        var depthSuffix = "&_bd=' . ($depth + 1) . '";
        var shouldRedirectDirect = ' . $shouldRedirectDirect . ';
        
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
            if (/^https?:\/\//i.test(href) && href.indexOf(targetBase) !== 0) return;
            
            var absolute = href;
            if (!/^https?:\/\//i.test(href)) {
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
            if (!/^https?:\/\//i.test(action)) {
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
        
        // Intercepta fetch e XHR para APIs internas
        var originalFetch = window.fetch;
        window.fetch = function(url, options) {
            if (typeof url === "string" && !url.startsWith("data:") && !url.startsWith("blob:")) {
                if (url.indexOf("_nav=") === -1 && url.indexOf("_asset=") === -1) {
                    var absolute = url;
                    if (!/^https?:\/\//i.test(url)) {
                        if (url.charAt(0) === "/") {
                            absolute = targetBase + url;
                        } else {
                            absolute = targetBase + "/" + url;
                        }
                    }
                    // Apenas redireciona se for do mesmo dominio
                    if (absolute.indexOf(targetBase) === 0) {
                        url = assetBase + btoa(absolute);
                    }
                }
            }
            return originalFetch.call(this, url, options);
        };
        
        // Corrige historico do navegador
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, "' . $proxyHost . '/");
        }
        
        // IMPORTANTE: Intercepta TODA navegacao para redirecionar ao site real
        // Isso inclui pushState, location.href, location.assign, location.replace
        
        // Funcao para redirecionar para URL real
        function goToReal(url) {
            if (!url || typeof url !== "string") return false;
            var realUrl = url;
            if (url.indexOf("http") !== 0) {
                realUrl = targetBase + (url.charAt(0) === "/" ? url : "/" + url);
            }
            // Usa o metodo original para evitar loop
            Object.getOwnPropertyDescriptor(window, "location").set.call(window, realUrl);
            return true;
        }
        
        // Intercepta pushState
        var _origPush = history.pushState;
        history.pushState = function(state, title, url) {
            if (goToReal(url)) return;
            return _origPush.apply(this, arguments);
        };
        
        // Intercepta replaceState
        var _origReplace = history.replaceState;
        history.replaceState = function(state, title, url) {
            if (url && typeof url === "string" && url.indexOf("http") === 0 && url.indexOf(location.origin) !== 0) {
                goToReal(url);
                return;
            }
            return _origReplace.apply(this, arguments);
        };
        
        // Intercepta location.assign
        var _origAssign = location.assign;
        location.assign = function(url) {
            goToReal(url);
        };
        
        // Intercepta location.replace  
        var _origLocReplace = location.replace;
        location.replace = function(url) {
            goToReal(url);
        };
        
        // Intercepta mudancas em location.href via defineProperty
        // Isso captura quando o codigo faz: location.href = "url" ou window.location = "url"
        var currentHref = location.href;
        try {
            var locDescriptor = Object.getOwnPropertyDescriptor(window, "location");
            // Nao podemos redefinir location diretamente, mas podemos interceptar via Proxy no futuro
            // Por enquanto, usamos um monitor via interval
            setInterval(function() {
                if (location.href !== currentHref && location.href.indexOf("_nav=") === -1 && location.href.indexOf("_asset=") === -1) {
                    // A URL mudou sem usar _nav - pode ser navegacao JS
                    // Nao fazemos nada aqui pois ja estamos em nova pagina
                }
                currentHref = location.href;
            }, 100);
        } catch(e) {}
    })();
    </script>';
    
    // =============================================
    // GUARDIAN DESABILITADO PARA BLACK PAGE
    // A verificacao ja foi feita na FASE 1 (deteccao JS)
    // Manter Guardian ativo causava falsos positivos em ambientes de dev
    // =============================================
    $guardianScript = '';
    
    // Injeta scripts antes de </body>
    $allScripts = $guardianScript . $proxyScript;
    if (stripos($response, '</body>') !== false) {
        $response = str_ireplace('</body>', $allScripts . '</body>', $response);
    } else {
        $response .= $allScripts;
    }
    
    // =============================================
    // HEADERS
    // =============================================
    header('HTTP/1.1 ' . $httpCode);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    header_remove('Set-Cookie');
    header_remove('Transfer-Encoding');
    
    echo $response;
    exit;
}

// ============================================
// FUNCAO STEALTH: Codigo fonte = WHITE, Visual = BLACK
// Bot ve WHITE no View Source, humano ve BLACK visualmente
// ============================================
function proxyRequestStealth($whiteUrl, $blackUrl) {
    global $DEBUG_MODE;
    
    // Busca conteudo da WHITE (isso e o que o bot vera no View Source)
    $ch = curl_init($whiteUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8'
        ]
    ]);
    $whiteHtml = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($whiteHtml === false || empty($whiteHtml)) {
        // Fallback: redireciona para white
        header('Location: ' . $whiteUrl);
        exit;
    }
    
    // CSS do overlay de carregamento
    $stealthCss = '
<style id="__stealth_css">
#__stealth_overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #0f0f1a 100%);
    z-index: 2147483647;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
#__stealth_overlay .loader {
    width: 50px; height: 50px;
    border: 3px solid rgba(59, 130, 246, 0.2);
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: __stealth_spin 0.8s linear infinite;
}
#__stealth_overlay .msg {
    color: #e2e8f0;
    margin-top: 20px;
    font-size: 16px;
    font-weight: 500;
}
@keyframes __stealth_spin { to { transform: rotate(360deg); } }
#__stealth_frame {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    width: 100%; height: 100%;
    margin: 0; padding: 0;
    z-index: 2147483646;
    border: none;
    display: none;
}
html, body { margin: 0 !important; padding: 0 !important; overflow: hidden !important; }
</style>';

    // HTML do overlay + iframe da BLACK
    $stealthHtml = '
<div id="__stealth_overlay">
    <div class="loader"></div>
    <div class="msg">Carregando...</div>
</div>
<iframe id="__stealth_frame" src="' . htmlspecialchars($blackUrl) . '" allowfullscreen allow="payment; clipboard-write" scrolling="yes"></iframe>
<script>
(function(){
    var overlay = document.getElementById("__stealth_overlay");
    var frame = document.getElementById("__stealth_frame");
    
    // Quando iframe carregar, esconde overlay e mostra frame
    frame.onload = function() {
        setTimeout(function() {
            overlay.style.display = "none";
            frame.style.display = "block";
        }, 100);
    };
    
    // Fallback: se nao carregar em 3s, esconde overlay
    setTimeout(function() {
        overlay.style.display = "none";
        frame.style.display = "block";
    }, 3000);
    
    // Limpa URL na barra de enderecos
    if (window.history && window.history.replaceState) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
})();
</script>';

    // Injeta CSS no <head>
    if (stripos($whiteHtml, '</head>') !== false) {
        $whiteHtml = str_ireplace('</head>', $stealthCss . '</head>', $whiteHtml);
    } else {
        $whiteHtml = $stealthCss . $whiteHtml;
    }
    
    // Injeta overlay e iframe antes de </body>
    if (stripos($whiteHtml, '</body>') !== false) {
        $whiteHtml = str_ireplace('</body>', $stealthHtml . '</body>', $whiteHtml);
    } else {
        $whiteHtml .= $stealthHtml;
    }
    
    // DEBUG
    if (isset($_GET['v0debug'])) {
        echo '<div style="background:#0f0;color:#000;padding:10px;position:fixed;top:0;left:0;z-index:999999999;font-family:monospace;font-size:12px;">';
        echo '<strong>[STEALTH MODE]</strong><br>';
        echo 'Codigo fonte: WHITE (' . htmlspecialchars($whiteUrl) . ')<br>';
        echo 'Visual: BLACK (' . htmlspecialchars($blackUrl) . ')<br>';
        echo 'Bot ve: WHITE | Humano ve: BLACK';
        echo '</div>';
    }
    
    // Headers
    header('HTTP/1.1 ' . $httpCode);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    echo $whiteHtml;
    exit;
}

// ============================================
// FUNCAO SHADOW: Codigo fonte = WHITE, Visual = BLACK via Shadow DOM + iframe
// O iframe fica escondido dentro do Shadow DOM (mais dificil de detectar no inspect)
// ============================================
function proxyRequestShadow($whiteUrl, $blackUrl) {
    global $DEBUG_MODE;
    
    // Busca conteudo da WHITE (isso e o que o bot vera no View Source)
    $ch = curl_init($whiteUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8'
        ]
    ]);
    $whiteHtml = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($whiteHtml === false || empty($whiteHtml)) {
        header('Location: ' . $whiteUrl);
        exit;
    }
    
    // CSS para esconder conteudo original e centralizar iframe
    $shadowCss = '
<style id="__sd_css">
html, body { margin: 0 !important; padding: 0 !important; overflow: hidden !important; width: 100% !important; height: 100% !important; }
.__sd_hide { display: none !important; visibility: hidden !important; position: absolute !important; left: -9999px !important; }
#__sd_host {
    position: fixed !important;
    top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;
    width: 100% !important; height: 100% !important;
    margin: 0 !important; padding: 0 !important;
    z-index: 2147483647 !important;
    background: #fff !important;
    overflow: hidden !important;
}
#__sd_load {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    width: 100%; height: 100%;
    background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #0f0f1a 100%);
    z-index: 2147483648;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
#__sd_load .ld {
    width: 50px; height: 50px;
    border: 3px solid rgba(59, 130, 246, 0.2);
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: __sd_sp 0.8s linear infinite;
}
#__sd_load .mg { color: #e2e8f0; margin-top: 20px; font-size: 16px; }
@keyframes __sd_sp { to { transform: rotate(360deg); } }
</style>';

    // Script que cria Shadow DOM com iframe dentro
    $shadowScript = '
<div id="__sd_load"><div class="ld"></div><div class="mg">Carregando...</div></div>
<div id="__sd_host"></div>
<script>
(function(){
    var h = document.getElementById("__sd_host");
    var l = document.getElementById("__sd_load");
    var b = document.body;
    
    // Esconde conteudo original
    for (var i = 0; i < b.children.length; i++) {
        var e = b.children[i];
        if (e.id && e.id.indexOf("__sd") === 0) continue;
        e.classList.add("__sd_hide");
    }
    
    // Cria Shadow DOM fechado (nao aparece no inspect normal)
    var s = h.attachShadow ? h.attachShadow({mode: "closed"}) : null;
    
    if (s) {
        // Cria iframe dentro do Shadow DOM com estilo completo
        var f = document.createElement("iframe");
        f.src = "' . htmlspecialchars($blackUrl) . '";
        f.style.cssText = "position:absolute;top:0;left:0;width:100%;height:100%;border:none;margin:0;padding:0;display:block;overflow:hidden;";
        f.setAttribute("allowfullscreen", "true");
        f.setAttribute("allow", "payment; clipboard-write");
        f.setAttribute("scrolling", "yes");
        
        // Style root do Shadow DOM
        var style = document.createElement("style");
        style.textContent = ":host{display:block;position:fixed;top:0;left:0;right:0;bottom:0;width:100%;height:100%;margin:0;padding:0;overflow:hidden}";
        s.appendChild(style);
        
        f.onload = function() {
            setTimeout(function() { l.style.display = "none"; }, 100);
        };
        
        s.appendChild(f);
    } else {
        // Fallback sem Shadow DOM
        var f = document.createElement("iframe");
        f.src = "' . htmlspecialchars($blackUrl) . '";
        f.style.cssText = "position:fixed;top:0;left:0;right:0;bottom:0;width:100%;height:100%;border:none;margin:0;padding:0;z-index:2147483647;";
        f.onload = function() { l.style.display = "none"; };
        b.appendChild(f);
    }
    
    // Limpa URL
    if (window.history && window.history.replaceState) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    // Timeout fallback mais rapido
    setTimeout(function() { l.style.display = "none"; }, 3000);
})();
</script>';

    // Injeta CSS no <head>
    if (stripos($whiteHtml, '</head>') !== false) {
        $whiteHtml = str_ireplace('</head>', $shadowCss . '</head>', $whiteHtml);
    } else {
        $whiteHtml = $shadowCss . $whiteHtml;
    }
    
    // Injeta script antes de </body>
    if (stripos($whiteHtml, '</body>') !== false) {
        $whiteHtml = str_ireplace('</body>', $shadowScript . '</body>', $whiteHtml);
    } else {
        $whiteHtml .= $shadowScript;
    }
    
    // DEBUG
    if (isset($_GET['v0debug'])) {
        echo '<div style="background:#ff0;color:#000;padding:10px;position:fixed;top:0;left:0;z-index:999999999;font-family:monospace;font-size:12px;">';
        echo '<strong>[SHADOW MODE]</strong><br>';
        echo 'Codigo fonte: WHITE (' . htmlspecialchars($whiteUrl) . ')<br>';
        echo 'Visual: BLACK (' . htmlspecialchars($blackUrl) . ') via Shadow DOM + iframe<br>';
        echo 'Bot ve: WHITE | Humano ve: BLACK';
        echo '</div>';
    }
    
    // Headers
    header('HTTP/1.1 ' . $httpCode);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    echo $whiteHtml;
    exit;
}
TRACKER_CODE;

    // Substitui os placeholders
    $code = str_replace('{{API_URL}}', $apiUrl, $code);
    $code = str_replace('{{SLUG}}', $slug, $code);
    $code = str_replace('{{CAMPAIGN_NAME}}', $campaignName, $code);
    $code = str_replace('{{GERADO_EM}}', $geradoEm, $code);
    $code = str_replace('{{WHITE_URL}}', $whiteUrl, $code);
    $code = str_replace('{{BLACK_URL}}', $blackUrl, $code);
    $code = str_replace('{{WHITE_METHOD}}', $whiteMethod, $code);
    $code = str_replace('{{BLACK_METHOD}}', $blackMethod, $code);
    $code = str_replace('{{VERIFY_LANGUAGE}}', $verifyLanguage, $code);
    
    return $code;
}

/**
 * Gera/atualiza o "live tracker" da campanha (pasta /live/{slug}.php).
 * Esse arquivo e executado pelo cloak-engine.php quando o trafego chega
 * pelo dominio apontado para o COMMANDER. Reutiliza exatamente o mesmo
 * codigo do tracker de download, entao a protecao e identica.
 */
function commanderWriteLiveTracker($campaign) {
    $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $campaign['slug'] ?? '');
    if ($slug === '') return false;

    $liveDir = __DIR__ . '/live';
    if (!is_dir($liveDir)) {
        @mkdir($liveDir, 0755, true);
    }

    // Garante execucao normal de PHP dentro de /live e desativa listagem
    $htaccess = $liveDir . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "Options -Indexes\n");
    }

    $liveFile = $liveDir . '/' . $slug . '.php';
    $domain = commanderNormalizeDomain($campaign['domain'] ?? '');

    // Sem dominio configurado: remove o live tracker se existir
    if ($domain === '') {
        if (is_file($liveFile)) @unlink($liveFile);
        return false;
    }

    if (!function_exists('generateTrackerCode')) return false;
    $code = generateTrackerCode($campaign);
    return @file_put_contents($liveFile, $code) !== false;
}

/**
 * Remove o live tracker de uma campanha (usado ao excluir).
 */
function commanderDeleteLiveTracker($campaign) {
    $slug = is_array($campaign) ? ($campaign['slug'] ?? '') : (string) $campaign;
    $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
    if ($slug === '') return;
    $liveFile = __DIR__ . '/live/' . $slug . '.php';
    if (is_file($liveFile)) @unlink($liveFile);
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
                    <a href="#domains" class="nav-link" data-page="domains" style="color: #a855f7;">
                        <i class="fas fa-globe"></i> Domínios
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#analytics" class="nav-link" data-page="analytics">
                        <i class="fas fa-chart-bar"></i> Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#sales" class="nav-link" data-page="sales" style="color: #ffcc00;">
                        <i class="fas fa-dollar-sign"></i> Vendas
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#gateways" class="nav-link" data-page="gateways" style="color: #00d4aa;">
                        <i class="fas fa-plug"></i> Gateways
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#pixels" class="nav-link" data-page="pixels" style="color: #1877f2;">
                        <i class="fas fa-code"></i> Pixels
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
                    <a href="#forcewhite" class="nav-link" data-page="forcewhite" style="color: #ffcc00;">
                        <i class="fas fa-ghost"></i> Force White
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
        
        <!-- Domains Page -->
        <div id="page-domains" class="page" style="display:none;">
            <div class="page-header">
                <div>
                    <h1 class="page-title" style="color:#a855f7;"><i class="fas fa-globe" style="margin-right:10px;"></i>Domínios</h1>
                    <p class="page-subtitle">Cadastre seus domínios e aponte-os para o COMMANDER</p>
                </div>
                <div style="display:flex;gap:8px;">
                    <button class="btn btn-secondary btn-sm" onclick="openDomainHelpModal()">
                        <i class="fas fa-circle-question"></i> Como apontar
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="openAddDomainModal()">
                        <i class="fas fa-plus"></i> Adicionar domínio
                    </button>
                </div>
            </div>

            <div class="card" style="margin-bottom:20px;">
                <div class="card-body" style="display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
                    <div>
                        <div style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">Servidor do COMMANDER</div>
                        <div id="commander-host" style="font-size:16px;font-weight:600;color:var(--light);margin-top:4px;">-</div>
                    </div>
                    <div>
                        <div style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">IP do servidor (registro A)</div>
                        <div id="commander-ip" style="font-size:16px;font-weight:600;color:var(--light);margin-top:4px;">-</div>
                    </div>
                    <div style="flex:1;min-width:220px;font-size:13px;color:var(--muted);line-height:1.5;">
                        Clique em <strong>Adicionar domínio</strong>, aponte o registro A dele para o IP do servidor e clique em <strong>Verificar</strong>. Assim que ficar <strong>Ativo</strong>, ele aparece no dropdown ao criar/editar uma campanha.
                    </div>
                </div>
            </div>

            <!-- Primeiros passos -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list-check" style="margin-right:8px;color:#a855f7;"></i>Como colocar um domínio no ar (3 passos)</h3>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;">
                        <!-- Passo 1 -->
                        <div style="padding:16px;background:var(--surface);border-radius:10px;border-top:3px solid #a855f7;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                                <span style="background:#a855f7;color:#fff;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;">1</span>
                                <strong style="color:var(--light);">Aponte o DNS do domínio</strong>
                            </div>
                            <p style="color:var(--muted);font-size:13px;line-height:1.6;margin:0 0 10px;">
                                No provedor do domínio (Cloudflare, Hostinger, GoDaddy, Registro.br, etc.), crie um registro <strong>A</strong> apontando para o IP do servidor:
                            </p>
                            <div style="display:flex;align-items:center;gap:8px;background:var(--darker);border-radius:8px;padding:8px 10px;">
                                <span style="font-size:11px;color:var(--muted);">Tipo A</span>
                                <code id="steps-ip" style="flex:1;color:var(--primary);font-size:13px;">IP do servidor</code>
                                <button class="btn btn-sm" onclick="copyText(document.getElementById('steps-ip').textContent)"><i class="fas fa-copy"></i></button>
                            </div>
                            <p style="color:var(--muted);font-size:12px;line-height:1.5;margin:8px 0 0;">
                                Recomendado: use a <strong style="color:#f59e0b;">Cloudflare com a nuvem LARANJA (Proxied)</strong> &mdash; ela esconde o IP do servidor e protege suas campanhas.
                            </p>
                        </div>
                        <!-- Passo 2 -->
                        <div style="padding:16px;background:var(--surface);border-radius:10px;border-top:3px solid #3b82f6;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                                <span style="background:#3b82f6;color:#fff;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;">2</span>
                                <strong style="color:var(--light);">Pronto — ativa sozinho</strong>
                            </div>
                            <p style="color:var(--muted);font-size:13px;line-height:1.6;margin:0;">
                                Assim que o DNS propagar, o servidor <strong>reconhece o domínio e emite o SSL automaticamente</strong> na primeira visita. Você não cria pasta, não faz upload e não instala nada.
                            </p>
                        </div>
                        <!-- Passo 3 -->
                        <div style="padding:16px;background:var(--surface);border-radius:10px;border-top:3px solid var(--success);">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                                <span style="background:var(--success);color:#fff;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;">3</span>
                                <strong style="color:var(--light);">Escolha na campanha</strong>
                            </div>
                            <p style="color:var(--muted);font-size:13px;line-height:1.6;margin:0;">
                                Em <strong>Campanhas</strong>, selecione qual domínio essa campanha vai usar no campo <strong>Domínio da Campanha</strong> e salve. Volte aqui e clique em <strong>Verificar</strong> para confirmar o status.
                            </p>
                        </div>
                    </div>
                    <div style="margin-top:16px;padding:12px 14px;background:var(--overlay);border-radius:8px;font-size:12.5px;color:var(--muted);line-height:1.6;">
                        <i class="fas fa-circle-info" style="color:#a855f7;margin-right:6px;"></i>
                        Você pode apontar <strong>quantos domínios quiser</strong> para o servidor. As páginas Safe e Offer NÃO precisam de domínio &mdash; são só URLs digitadas no formulário da campanha.
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Domínio</th>
                                    <th>Usado por</th>
                                    <th>Conexão</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="domains-table">
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="fas fa-globe"></i>
                                            <h3>Nenhum domínio</h3>
                                            <p>Clique em "Adicionar domínio" para cadastrar o primeiro</p>
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
        
        <!-- COMMANDER V3: Sales Page -->
        <div id="page-sales" class="page" style="display:none;">
            <div class="page-header">
                <div>
                    <h1 class="page-title" style="color:#ffcc00;"><i class="fas fa-dollar-sign" style="margin-right:10px;"></i>Vendas</h1>
                    <p class="page-subtitle">Faturamento e transacoes dos gateways</p>
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <select id="sales-gateway" class="form-control" style="width:150px;" onchange="loadSales()">
                        <option value="">Todos Gateways</option>
                    </select>
                    <select id="sales-status" class="form-control" style="width:150px;" onchange="loadSales()">
                        <option value="">Todos Status</option>
                        <option value="paid">Pagas</option>
                        <option value="pending">Pendentes</option>
                        <option value="refunded">Reembolsadas</option>
                        <option value="chargeback">Chargeback</option>
                    </select>
                    <input type="date" id="sales-from" class="form-control" style="width:140px;" onchange="loadSales()">
                    <span style="color:var(--muted);">ate</span>
                    <input type="date" id="sales-to" class="form-control" style="width:140px;" onchange="loadSales()">
                    <button class="btn btn-secondary btn-sm" onclick="loadSales()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            
            <!-- Stats Cards Vendas -->
            <div class="stats-grid" id="sales-stats">
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(0,212,170,0.15);"><i class="fas fa-check-circle" style="color:#00d4aa;"></i></div>
                    <div class="stat-info">
                        <h3 id="sales-paid" style="color:#00d4aa;">R$ 0</h3>
                        <p>Pagas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(255,204,0,0.15);"><i class="fas fa-clock" style="color:#ffcc00;"></i></div>
                    <div class="stat-info">
                        <h3 id="sales-pending" style="color:#ffcc00;">R$ 0</h3>
                        <p>Pendentes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(255,0,80,0.15);"><i class="fas fa-undo" style="color:#ff0050;"></i></div>
                    <div class="stat-info">
                        <h3 id="sales-refunded" style="color:#ff0050;">R$ 0</h3>
                        <p>Reembolsos</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(168,85,247,0.15);"><i class="fas fa-shopping-cart" style="color:#a855f7;"></i></div>
                    <div class="stat-info">
                        <h3 id="sales-count">0</h3>
                        <p>Total Vendas</p>
                    </div>
                </div>
            </div>
            
            <!-- Metricas por Campanha (Principal) -->
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 class="card-title"><i class="fas fa-bullhorn" style="margin-right:8px;color:#ff0050;"></i>Metricas por Campanha</h3>
                    <div style="display:flex;gap:8px;">
                        <select id="campaign-metric-filter" class="form-control" style="width:180px;font-size:13px;" onchange="filterCampaignMetrics()">
                            <option value="all">Todas as Metricas</option>
                            <option value="paid">Apenas Pagas</option>
                            <option value="pending">Apenas Pendentes</option>
                            <option value="paid_pending">Pagas + Pendentes</option>
                        </select>
                    </div>
                </div>
                <div class="card-body" style="padding:0;">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr style="background:var(--surface);">
                                    <th style="padding:14px 16px;">Campanha</th>
                                    <th style="padding:14px 16px;text-align:center;color:#00d4aa;">Pagas</th>
                                    <th style="padding:14px 16px;text-align:center;color:#ffcc00;">Pendentes</th>
                                    <th style="padding:14px 16px;text-align:center;color:#ff0050;">Reembolsos</th>
                                    <th style="padding:14px 16px;text-align:right;">Total Bruto</th>
                                    <th style="padding:14px 16px;text-align:right;color:#00d4aa;">Total Liquido</th>
                                </tr>
                            </thead>
                            <tbody id="campaign-metrics-table">
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state"><i class="fas fa-bullhorn"></i><p>Carregando metricas...</p></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="grid-2" style="margin-top:20px;">
                <!-- Faturamento por Gateway -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-plug" style="margin-right:8px;color:#00d4aa;"></i>Por Gateway</h3>
                    </div>
                    <div class="card-body">
                        <div id="sales-by-gateway">
                            <div class="empty-state"><i class="fas fa-plug"></i><p>Carregando...</p></div>
                        </div>
                    </div>
                </div>
                
                <!-- Resumo Geral -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-pie" style="margin-right:8px;color:#a855f7;"></i>Resumo Geral</h3>
                    </div>
                    <div class="card-body">
                        <div id="sales-summary">
                            <div class="empty-state"><i class="fas fa-chart-pie"></i><p>Carregando...</p></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Grafico por Data -->
            <div class="card" style="margin-top:20px;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-area" style="margin-right:8px;color:#00f2ea;"></i>Faturamento por Data</h3>
                </div>
                <div class="card-body">
                    <div id="sales-by-date">
                        <div class="empty-state"><i class="fas fa-chart-line"></i><p>Carregando...</p></div>
                    </div>
                </div>
            </div>
            
            <!-- Tabela de Transacoes -->
            <div class="card" style="margin-top:20px;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list" style="margin-right:8px;"></i>Transacoes Recentes</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Gateway</th>
                                    <th>Produto</th>
                                    <th>Cliente</th>
                                    <th>Campanha</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="sales-table">
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state"><i class="fas fa-shopping-cart"></i><p>Carregando transacoes...</p></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- COMMANDER V3: Gateways Page -->
        <div id="page-gateways" class="page" style="display:none;">
            <div class="page-header">
                <div>
                    <h1 class="page-title" style="color:#00d4aa;"><i class="fas fa-plug" style="margin-right:10px;"></i>Configurar Gateways</h1>
                    <p class="page-subtitle">Conecte seu gateway de pagamento para rastrear vendas automaticamente</p>
                </div>
            </div>
            
            <!-- Selecionar Gateway -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list" style="margin-right:8px;color:#ffcc00;"></i>1. Selecione seu Gateway</h3>
                </div>
                <div class="card-body">
                    <select id="gateway-select" class="form-control" style="max-width:400px;font-size:16px;padding:12px;" onchange="showGatewayInstructions()">
                        <option value="">-- Escolha o gateway que voce usa --</option>
                        <optgroup label="Mais Populares">
                            <option value="hotmart">Hotmart</option>
                            <option value="kiwify">Kiwify</option>
                            <option value="monetizze">Monetizze</option>
                            <option value="eduzz">Eduzz</option>
                        </optgroup>
                        <optgroup label="Outros Gateways">
                            <option value="perfectpay">PerfectPay</option>
                            <option value="braip">Braip</option>
                            <option value="ticto">Ticto</option>
                            <option value="pepper">Pepper</option>
                            <option value="doppus">Doppus</option>
                            <option value="greenn">Greenn</option>
                        </optgroup>
                        <optgroup label="Gateways Alternativos">
                            <option value="ironpay">IronPay</option>
                            <option value="blackcat">BlackCat</option>
                            <option value="skalepay">SkalePay</option>
                            <option value="otimizepagamentos">Otimize Pagamentos</option>
                            <option value="virtualpay">VirtualPay</option>
                            <option value="fastsoft">FastSoft</option>
                            <option value="plumify">Plumify</option>
                            <option value="bynet">Bynet (TechByNet)</option>
                        </optgroup>
                    </select>
                </div>
            </div>
            
            <!-- Instrucoes do Gateway (aparecem ao selecionar) -->
            <div id="gateway-instructions" class="card" style="margin-top:20px;display:none;">
                <div class="card-header" style="background:linear-gradient(135deg, rgba(0,212,170,0.1), rgba(0,242,234,0.05));">
                    <h3 class="card-title"><i class="fas fa-cog" style="margin-right:8px;color:#00d4aa;"></i>2. Configure o Webhook no <span id="gateway-name-title">Gateway</span></h3>
                </div>
                <div class="card-body">
                    <!-- URL do Webhook -->
                    <div style="background:var(--surface);border:2px solid #00d4aa;border-radius:12px;padding:20px;margin-bottom:24px;">
                        <label style="display:block;margin-bottom:8px;color:#00d4aa;font-weight:600;"><i class="fas fa-link" style="margin-right:8px;"></i>URL do Webhook (copie esta URL)</label>
                        <div style="display:flex;gap:10px;">
                            <input type="text" id="gateway-webhook-url" class="form-control" readonly style="font-family:monospace;font-size:14px;background:var(--dark);border-color:#00d4aa;">
                            <button class="btn" style="background:#00d4aa;color:#000;font-weight:600;min-width:120px;" onclick="copyWebhookUrl()">
                                <i class="fas fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>
                    
                    <!-- Passo a passo especifico -->
                    <div id="gateway-steps" style="background:var(--surface);border-radius:12px;padding:20px;">
                        <!-- Conteudo dinamico baseado no gateway selecionado -->
                    </div>
                    
                    <!-- Evento a selecionar -->
                    <div style="background:rgba(255,204,0,0.1);border:1px solid rgba(255,204,0,0.3);border-radius:12px;padding:16px;margin-top:20px;">
                        <h4 style="color:#ffcc00;margin:0 0 12px 0;"><i class="fas fa-bell" style="margin-right:8px;"></i>Evento/Tipo de Webhook</h4>
                        <p id="gateway-event" style="color:var(--light);margin:0;font-size:15px;"></p>
                    </div>
                    
                    <!-- Botao Salvar -->
                    <div style="margin-top:24px;display:flex;gap:12px;">
                        <button class="btn btn-primary" style="flex:1;padding:14px;font-size:16px;" onclick="saveGatewayConfig()">
                            <i class="fas fa-check"></i> Ativar Gateway
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Gateways ja configurados -->
            <div class="card" style="margin-top:20px;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-check-circle" style="margin-right:8px;color:#00d4aa;"></i>Gateways Ativos</h3>
                </div>
                <div class="card-body">
                    <div id="configured-gateways">
                        <div class="empty-state"><i class="fas fa-plug"></i><p>Nenhum gateway configurado ainda</p></div>
                    </div>
                </div>
            </div>
            
            <!-- Dicas -->
            <div class="card" style="margin-top:20px;border-color:rgba(0,242,234,0.3);">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-lightbulb" style="margin-right:8px;color:#00f2ea;"></i>Dicas Importantes</h3>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;">
                        <div style="background:var(--surface);border-radius:8px;padding:16px;">
                            <h4 style="color:#ffcc00;margin:0 0 8px 0;font-size:14px;"><i class="fas fa-tag" style="margin-right:6px;"></i>Use UTMs nos Links</h4>
                            <p style="color:var(--muted);margin:0;font-size:13px;">Para vincular vendas as campanhas, seus links de anuncio devem conter utm_campaign ou utm_source com o nome/slug da campanha.</p>
                        </div>
                        <div style="background:var(--surface);border-radius:8px;padding:16px;">
                            <h4 style="color:#00d4aa;margin:0 0 8px 0;font-size:14px;"><i class="fas fa-sync" style="margin-right:6px;"></i>Atualizacao Automatica</h4>
                            <p style="color:var(--muted);margin:0;font-size:13px;">Quando uma venda for aprovada ou reembolsada, o gateway envia automaticamente para ca e atualiza o status.</p>
                        </div>
                        <div style="background:var(--surface);border-radius:8px;padding:16px;">
                            <h4 style="color:#ff0050;margin:0 0 8px 0;font-size:14px;"><i class="fas fa-shield-alt" style="margin-right:6px;"></i>Apenas Vendas Pagas</h4>
                            <p style="color:var(--muted);margin:0;font-size:13px;">No dashboard de Vendas, filtramos apenas as transacoes com status "pago/aprovado" para mostrar faturamento real.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pixels Page -->
        <div id="page-pixels" class="page" style="display:none;">
            <div class="page-header">
                <div>
                    <h1 class="page-title" style="color:#1877f2;"><i class="fas fa-code" style="margin-right:10px;"></i>Pixels de Conversao</h1>
                    <p class="page-subtitle">Configure pixels para enviar conversoes para as plataformas de anuncio</p>
                </div>
            </div>
            
            <!-- Explicacao -->
            <div class="card" style="border-color:rgba(24,119,242,0.3);margin-bottom:20px;">
                <div class="card-body" style="padding:20px;">
                    <div style="display:flex;align-items:flex-start;gap:16px;">
                        <div style="width:48px;height:48px;border-radius:12px;background:rgba(24,119,242,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-info-circle" style="color:#1877f2;font-size:24px;"></i>
                        </div>
                        <div>
                            <h4 style="margin:0 0 8px 0;color:var(--light);">Como funciona?</h4>
                            <p style="margin:0;color:var(--muted);line-height:1.6;">
                                Os pixels sao codigos que enviam dados de conversao (vendas) para as plataformas de anuncio (Facebook, Google, TikTok).
                                Isso permite que os algoritmos otimizem suas campanhas automaticamente, encontrando pessoas com maior probabilidade de comprar.
                            </p>
                            <div style="margin-top:12px;padding:12px;background:var(--surface);border-radius:8px;">
                                <p style="margin:0;color:#ffcc00;font-size:13px;"><i class="fas fa-exclamation-triangle" style="margin-right:6px;"></i>
                                    <strong>Importante:</strong> Os pixels configurados aqui serao disparados automaticamente quando uma venda for aprovada via webhook.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Grid de Pixels -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));gap:20px;">
                
                <!-- Facebook Pixel -->
                <div class="card" style="border-color:rgba(24,119,242,0.3);">
                    <div class="card-header" style="background:linear-gradient(135deg, rgba(24,119,242,0.1), transparent);">
                        <h3 class="card-title" style="display:flex;align-items:center;gap:10px;">
                            <div style="width:32px;height:32px;border-radius:8px;background:#1877f2;display:flex;align-items:center;justify-content:center;">
                                <i class="fab fa-facebook-f" style="color:#fff;"></i>
                            </div>
                            Facebook Pixel / CAPI
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Pixel ID</label>
                            <input type="text" id="pixel-facebook-id" class="form-control" placeholder="Ex: 123456789012345">
                        </div>
                        <div class="form-group">
                            <label>Access Token (CAPI)</label>
                            <input type="text" id="pixel-facebook-token" class="form-control" placeholder="Token da Conversions API">
                            <small style="color:var(--muted);">Obtenha no Gerenciador de Eventos > Configuracoes > Token de Acesso</small>
                        </div>
                        <div class="form-group">
                            <label>Evento de Conversao</label>
                            <select id="pixel-facebook-event" class="form-control">
                                <option value="Purchase">Purchase (Compra)</option>
                                <option value="Lead">Lead</option>
                                <option value="CompleteRegistration">CompleteRegistration</option>
                                <option value="InitiateCheckout">InitiateCheckout</option>
                            </select>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="pixel-facebook-enabled">
                            <label for="pixel-facebook-enabled">Ativar Facebook Pixel</label>
                        </div>
                    </div>
                </div>
                
                <!-- Google Ads -->
                <div class="card" style="border-color:rgba(234,67,53,0.3);">
                    <div class="card-header" style="background:linear-gradient(135deg, rgba(234,67,53,0.1), transparent);">
                        <h3 class="card-title" style="display:flex;align-items:center;gap:10px;">
                            <div style="width:32px;height:32px;border-radius:8px;background:#EA4335;display:flex;align-items:center;justify-content:center;">
                                <i class="fab fa-google" style="color:#fff;"></i>
                            </div>
                            Google Ads
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Conversion ID (AW-XXXXXXXX)</label>
                            <input type="text" id="pixel-google-id" class="form-control" placeholder="Ex: AW-123456789">
                        </div>
                        <div class="form-group">
                            <label>Conversion Label</label>
                            <input type="text" id="pixel-google-label" class="form-control" placeholder="Ex: AbC123xYz">
                            <small style="color:var(--muted);">Encontre em Google Ads > Ferramentas > Conversoes</small>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="pixel-google-enabled">
                            <label for="pixel-google-enabled">Ativar Google Ads Conversion</label>
                        </div>
                    </div>
                </div>
                
                <!-- TikTok Pixel -->
                <div class="card" style="border-color:rgba(0,0,0,0.3);">
                    <div class="card-header" style="background:linear-gradient(135deg, rgba(255,0,80,0.1), transparent);">
                        <h3 class="card-title" style="display:flex;align-items:center;gap:10px;">
                            <div style="width:32px;height:32px;border-radius:8px;background:#000;display:flex;align-items:center;justify-content:center;">
                                <i class="fab fa-tiktok" style="color:#fff;"></i>
                            </div>
                            TikTok Pixel
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Pixel ID</label>
                            <input type="text" id="pixel-tiktok-id" class="form-control" placeholder="Ex: CXXXXXXXXXXXXXXXXX">
                        </div>
                        <div class="form-group">
                            <label>Access Token (Events API)</label>
                            <input type="text" id="pixel-tiktok-token" class="form-control" placeholder="Token da Events API">
                            <small style="color:var(--muted);">Obtenha no TikTok Ads Manager > Eventos > Web Events > Configuracoes</small>
                        </div>
                        <div class="form-group">
                            <label>Evento de Conversao</label>
                            <select id="pixel-tiktok-event" class="form-control">
                                <option value="CompletePayment">CompletePayment (Pagamento)</option>
                                <option value="PlaceAnOrder">PlaceAnOrder (Pedido)</option>
                                <option value="Subscribe">Subscribe</option>
                                <option value="SubmitForm">SubmitForm</option>
                            </select>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="pixel-tiktok-enabled">
                            <label for="pixel-tiktok-enabled">Ativar TikTok Pixel</label>
                        </div>
                    </div>
                </div>
                
                <!-- Taboola Pixel -->
                <div class="card" style="border-color:rgba(0,100,210,0.3);">
                    <div class="card-header" style="background:linear-gradient(135deg, rgba(0,100,210,0.1), transparent);">
                        <h3 class="card-title" style="display:flex;align-items:center;gap:10px;">
                            <div style="width:32px;height:32px;border-radius:8px;background:#0064D2;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-bullhorn" style="color:#fff;font-size:14px;"></i>
                            </div>
                            Taboola
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Account ID</label>
                            <input type="text" id="pixel-taboola-id" class="form-control" placeholder="Ex: 1234567">
                        </div>
                        <div class="form-group">
                            <label>Evento de Conversao</label>
                            <input type="text" id="pixel-taboola-event" class="form-control" placeholder="Ex: purchase" value="purchase">
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="pixel-taboola-enabled">
                            <label for="pixel-taboola-enabled">Ativar Taboola Pixel</label>
                        </div>
                    </div>
                </div>
                
                <!-- Outbrain Pixel -->
                <div class="card" style="border-color:rgba(255,102,0,0.3);">
                    <div class="card-header" style="background:linear-gradient(135deg, rgba(255,102,0,0.1), transparent);">
                        <h3 class="card-title" style="display:flex;align-items:center;gap:10px;">
                            <div style="width:32px;height:32px;border-radius:8px;background:#FF6600;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-external-link-alt" style="color:#fff;font-size:14px;"></i>
                            </div>
                            Outbrain
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Marketer ID</label>
                            <input type="text" id="pixel-outbrain-id" class="form-control" placeholder="Ex: 00abc123def456">
                        </div>
                        <div class="form-group">
                            <label>Evento de Conversao</label>
                            <input type="text" id="pixel-outbrain-event" class="form-control" placeholder="Ex: purchase" value="purchase">
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="pixel-outbrain-enabled">
                            <label for="pixel-outbrain-enabled">Ativar Outbrain Pixel</label>
                        </div>
                    </div>
                </div>
                
                <!-- Kwai Pixel -->
                <div class="card" style="border-color:rgba(255,70,0,0.3);">
                    <div class="card-header" style="background:linear-gradient(135deg, rgba(255,70,0,0.1), transparent);">
                        <h3 class="card-title" style="display:flex;align-items:center;gap:10px;">
                            <div style="width:32px;height:32px;border-radius:8px;background:#FF4600;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-play" style="color:#fff;font-size:14px;"></i>
                            </div>
                            Kwai Ads
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Pixel ID</label>
                            <input type="text" id="pixel-kwai-id" class="form-control" placeholder="Ex: 123456789">
                        </div>
                        <div class="form-group">
                            <label>Access Token</label>
                            <input type="text" id="pixel-kwai-token" class="form-control" placeholder="Token da API">
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="pixel-kwai-enabled">
                            <label for="pixel-kwai-enabled">Ativar Kwai Pixel</label>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Botao Salvar -->
            <div style="margin-top:24px;display:flex;justify-content:flex-end;">
                <button class="btn btn-primary" style="padding:14px 32px;font-size:16px;" onclick="savePixels()">
                    <i class="fas fa-save" style="margin-right:8px;"></i>Salvar Configuracoes de Pixels
                </button>
            </div>
            
            <!-- Teste de Disparo -->
            <div class="card" style="margin-top:20px;border-color:rgba(255,204,0,0.3);">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-flask" style="margin-right:8px;color:#ffcc00;"></i>Testar Disparo de Pixel</h3>
                </div>
                <div class="card-body">
                    <p style="color:var(--muted);margin-bottom:16px;">Envie um evento de teste para verificar se os pixels estao funcionando corretamente.</p>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;">
                        <button class="btn btn-secondary" onclick="testPixel('facebook')"><i class="fab fa-facebook-f" style="margin-right:6px;"></i>Testar Facebook</button>
                        <button class="btn btn-secondary" onclick="testPixel('google')"><i class="fab fa-google" style="margin-right:6px;"></i>Testar Google</button>
                        <button class="btn btn-secondary" onclick="testPixel('tiktok')"><i class="fab fa-tiktok" style="margin-right:6px;"></i>Testar TikTok</button>
                        <button class="btn btn-secondary" onclick="testPixel('taboola')"><i class="fas fa-bullhorn" style="margin-right:6px;"></i>Testar Taboola</button>
                        <button class="btn btn-secondary" onclick="testPixel('outbrain')"><i class="fas fa-external-link-alt" style="margin-right:6px;"></i>Testar Outbrain</button>
                        <button class="btn btn-secondary" onclick="testPixel('kwai')"><i class="fas fa-play" style="margin-right:6px;"></i>Testar Kwai</button>
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
        
        <!-- Force White Page -->
        <div id="page-forcewhite" class="page" style="display:none;">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Force White</h1>
                    <p class="page-subtitle">IPs e faixas que vao SEMPRE para a White Page (lista manual de bots)</p>
                </div>
            </div>
            
            <!-- Contadores de bots armazenados -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px;">
                <div class="card">
                    <div class="card-body" style="display:flex;align-items:center;gap:14px;">
                        <i class="fas fa-database" style="font-size:28px;color:#ffcc00;"></i>
                        <div>
                            <div style="font-size:26px;font-weight:700;" id="bots-total-fp">0</div>
                            <div style="color:var(--muted);font-size:13px;">Bots armazenados</div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body" style="display:flex;align-items:center;gap:14px;">
                        <i class="fas fa-network-wired" style="font-size:28px;color:#00d4ff;"></i>
                        <div>
                            <div style="font-size:26px;font-weight:700;" id="bots-total-ips">0</div>
                            <div style="color:var(--muted);font-size:13px;">IPs unicos de bots</div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body" style="display:flex;align-items:center;gap:14px;">
                        <i class="fas fa-hdd" style="font-size:28px;color:#00ff88;"></i>
                        <div>
                            <div style="font-size:26px;font-weight:700;" id="bots-engine">-</div>
                            <div style="color:var(--muted);font-size:13px;">Armazenamento</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card" style="margin-bottom:20px;">
                <div class="card-body">
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                        <div style="flex:1;min-width:240px;">
                            <label class="form-label" for="forcewhite-input">Adicionar IP ou faixa CIDR</label>
                            <input type="text" id="forcewhite-input" class="form-control" placeholder="Ex: 45.61.137.162 ou 91.231.89.0/24">
                            <small style="color:var(--muted);">Pode colar varios de uma vez, separados por virgula, espaco ou quebra de linha.</small>
                        </div>
                        <button class="btn btn-primary" onclick="addForceWhite()">
                            <i class="fas fa-plus"></i> Adicionar
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-ghost" style="color:#ffcc00;margin-right:8px;"></i>Lista Force White (<span id="forcewhite-count">0</span>)</h3>
                </div>
                <div class="card-body">
                    <div id="forcewhite-table"></div>
                </div>
            </div>
        </div>
        
        <!-- Downloads Page -->
        <div id="page-downloads" class="page" style="display:none;">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Configuracao de Anuncios</h1>
                    <p class="page-subtitle">Gere as URLs com parametros UTM para suas campanhas</p>
                </div>
            </div>
            
            <!-- Aviso: nao precisa mais baixar tracker -->
            <div class="card" style="margin-bottom:20px;border-left:4px solid var(--success);">
                <div class="card-body" style="display:flex;gap:14px;align-items:flex-start;">
                    <i class="fas fa-circle-check" style="color:var(--success);font-size:22px;margin-top:2px;"></i>
                    <div>
                        <strong style="color:var(--light);">Nao e mais necessario baixar arquivos.</strong>
                        <p style="color:var(--muted);font-size:14px;margin-top:6px;line-height:1.6;margin-bottom:0;">
                            O cloaking agora roda direto no COMMANDER. Basta apontar o dominio da campanha para o servidor na aba
                            <a href="#domains" onclick="switchPage('domains')" style="color:var(--primary);font-weight:600;">Dominios</a>
                            e criar a campanha. O motor e gerado automaticamente ao salvar &mdash; sem upload de <code>index.php</code> ou <code>.htaccess</code>.
                        </p>
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
                            <i class="fas fa-globe" style="margin-right:8px;"></i>Digite o dominio da campanha:
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
                        <li><strong style="color:var(--light);">SSL automatico:</strong> O HTTPS do dominio de campanha e emitido sozinho na primeira visita &mdash; nao precisa configurar</li>
                        <li><strong style="color:var(--light);">White page valida:</strong> Use uma pagina real e relevante (blog, artigo) como white page</li>
                        <li><strong style="color:var(--light);">Cloudflare laranja (Proxied):</strong> Deixe o dominio da campanha com a nuvem LARANJA para esconder o IP do servidor e proteger a operacao</li>
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
                
                <div class="form-group">
                    <label>Domínio da Campanha</label>
                    <select name="domain" id="campaign-domain" class="form-control">
                        <option value="">Selecione um domínio ativo...</option>
                    </select>
                    <small style="display:block;margin-top:6px;color:var(--muted);font-size:12px;">
                        Aparecem aqui apenas os domínios <strong>ativos</strong> (apontados corretamente). Não vê o seu?
                        <a href="#" onclick="switchPage('domains');closeModal('campaign-modal');return false;" style="color:var(--primary);">Adicione e verifique em Domínios</a>.
                    </small>
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
                    <label>Metodo Safe Page</label>
                    <select name="white_method" id="campaign-white-method" class="form-control">
                        <option value="redirect">Redirect 302</option>
                        <option value="proxy">Proxy (mostra conteudo)</option>
                        <option value="iframe">iFrame (stealth)</option>
                        <option value="shadow">Nível Hard (Melhor Opção)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Offer Page URL</label>
                    <input type="url" name="black_url" id="campaign-black-url" class="form-control" placeholder="https://exemplo.com/oferta">
                </div>
                
                <div class="form-group">
                    <label>Metodo Black Page</label>
                    <select name="black_method" id="campaign-black-method" class="form-control">
                        <option value="redirect">Redirect 302</option>
                        <option value="proxy">Proxy (mostra conteudo)</option>
                        <option value="iframe">iFrame (stealth)</option>
                        <option value="shadow">Nível Hard (Melhor Opção)</option>
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

                    <div class="form-group" style="margin-top:16px;margin-bottom:0;">
                        <label><i class="fas fa-language" style="margin-right:6px;color:var(--primary);"></i>Idioma da tela de verificacao</label>
                        <select name="verify_language" id="campaign-verify-language" class="form-control">
                            <option value="auto">Automatico (idioma do visitante)</option>
                            <option value="pt">Portugues</option>
                            <option value="en">Ingles (English)</option>
                            <option value="es">Espanhol (Espanol)</option>
                            <option value="fr">Frances (Francais)</option>
                            <option value="de">Alemao (Deutsch)</option>
                            <option value="it">Italiano</option>
                            <option value="nl">Holandes (Nederlands)</option>
                            <option value="ru">Russo (Русский)</option>
                            <option value="tr">Turco (Turkce)</option>
                            <option value="ar">Arabe (ال��ربية)</option>
                            <option value="ja">Japones (日本語)</option>
                            <option value="zh">Chines (中文)</option>
                        </select>
                        <small style="color:var(--muted);font-size:12px;">Texto da tela "Verificando se o site e seguro". Use "Automatico" para detectar o idioma de cada visitante.</small>
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

<!-- Domain Help Modal (estilo White Rabbit) -->
<div id="add-domain-modal" class="modal-overlay">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-globe" style="color:#a855f7;margin-right:8px;"></i>Adicionar domínio</h3>
            <button class="modal-close" onclick="closeModal('add-domain-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Domínio</label>
                <input type="text" id="add-domain-input" class="form-control" placeholder="ex: oferta1.com" autocomplete="off"
                       onkeydown="if(event.key==='Enter'&&!event.isComposing&&event.keyCode!==229){event.preventDefault();submitAddDomain();}">
                <small style="display:block;margin-top:8px;color:var(--muted);font-size:12px;line-height:1.6;">
                    Depois de adicionar, aponte o registro <strong>A</strong> dele para o IP
                    <code id="add-domain-ip" style="color:var(--primary);">IP do servidor</code>
                    e clique em <strong>Verificar</strong> na tabela. Recomendado usar a Cloudflare com a nuvem laranja (Proxied).
                </small>
            </div>
        </div>
        <div class="modal-footer" style="display:flex;gap:10px;justify-content:flex-end;padding:16px 20px;">
            <button class="btn btn-secondary" onclick="closeModal('add-domain-modal')">Cancelar</button>
            <button class="btn btn-primary" onclick="submitAddDomain()"><i class="fas fa-plus"></i> Adicionar</button>
        </div>
    </div>
</div>

<div id="domain-help-modal" class="modal-overlay">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-globe" style="color:#a855f7;margin-right:8px;"></i>Como apontar seu domínio</h3>
            <button class="modal-close" onclick="closeModal('domain-help-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--muted);font-size:14px;line-height:1.6;margin-bottom:20px;">
                Aponte o domínio para o servidor e ele fica ativo automaticamente &mdash; com SSL emitido na hora, sem criar pasta, sem upload e sem baixar nada.
            </p>

            <div style="display:flex;gap:12px;margin-bottom:18px;">
                <div style="width:26px;height:26px;border-radius:50%;background:#a855f7;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">1</div>
                <div>
                    <strong style="color:var(--light);">Acesse o painel de DNS do domínio.</strong>
                    <p style="color:var(--muted);font-size:13px;margin-top:4px;line-height:1.5;">No provedor onde o domínio está (Cloudflare, Hostinger, GoDaddy, Registro.br, etc.), abra "Registros DNS".</p>
                </div>
            </div>

            <div style="display:flex;gap:12px;margin-bottom:18px;">
                <div style="width:26px;height:26px;border-radius:50%;background:#a855f7;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">2</div>
                <div style="flex:1;">
                    <strong style="color:var(--light);">Crie um registro A com o IP do servidor.</strong>
                    <p style="color:var(--muted);font-size:13px;margin-top:4px;margin-bottom:10px;line-height:1.5;">
                        Aponte o domínio (ou subdomínio) para o IP abaixo. Recomendado: use a Cloudflare com a nuvem <strong style="color:#f59e0b;">LARANJA (Proxied)</strong> para esconder o IP do servidor.
                    </p>
                    <div style="display:flex;align-items:center;gap:8px;background:var(--darker);border-radius:8px;padding:10px;">
                        <span style="font-size:12px;color:var(--muted);width:60px;">Tipo A</span>
                        <code id="dns-a-value" style="flex:1;color:var(--primary);font-size:13px;">-</code>
                        <button class="btn btn-sm" onclick="copyText(document.getElementById('dns-a-value').textContent)"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:12px;margin-bottom:18px;">
                <div style="width:26px;height:26px;border-radius:50%;background:var(--success);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">3</div>
                <div>
                    <strong style="color:var(--light);">Selecione o domínio na campanha.</strong>
                    <p style="color:var(--muted);font-size:13px;margin-top:4px;line-height:1.5;">Em Campanhas, escolha esse domínio no campo <strong>Domínio da Campanha</strong> e salve. Depois volte aqui e clique em <strong>Verificar</strong> &mdash; quando o status ficar <span style="color:var(--success);">Conectado</span>, está no ar.</p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="closeModal('domain-help-modal')">Entendi</button>
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

// V3 - User Info
const currentUserId = '<?php echo htmlspecialchars($loggedUser['username'] ?? "default"); ?>';
const isAdmin = <?php echo ($loggedUser['is_admin'] ?? false) ? 'true' : 'false'; ?>;

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
        if (page === 'domains') loadDomains();
        if (page === 'analytics') initAnalytics();
        if (page === 'sales') initSales();
        if (page === 'gateways') initGateways();
        if (page === 'pixels') initPixels();
        if (page === 'logs') loadLogs();
        if (page === 'ips') loadIPs();
        if (page === 'forcewhite') loadForceWhite();
        if (page === 'downloads') updateAllUTMs();
    });
});

// Navega para uma pagina programaticamente (reutiliza o clique do menu)
function switchPage(page) {
    const link = document.querySelector('.nav-link[data-page="' + page + '"]');
    if (link) link.click();
}

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

// =============================================
// COMMANDER V3 - VENDAS E GATEWAYS
// =============================================

// Sales - Inicializa pagina de vendas
function initSales() {
    const today = new Date();
    const weekAgo = new Date(today);
    weekAgo.setDate(weekAgo.getDate() - 30);
    
    document.getElementById('sales-from').value = weekAgo.toISOString().split('T')[0];
    document.getElementById('sales-to').value = today.toISOString().split('T')[0];
    
    loadSales();
}

// Carrega dados de vendas
async function loadSales() {
    const gateway = document.getElementById('sales-gateway')?.value || '';
    const status = document.getElementById('sales-status')?.value || '';
    const dateFrom = document.getElementById('sales-from')?.value || '';
    const dateTo = document.getElementById('sales-to')?.value || '';
    
    // Carrega faturamento
    const revenueResult = await apiCall('get_revenue', {
        gateway: gateway,
        date_from: dateFrom,
        date_to: dateTo,
        user_id: currentUserId,
        is_admin: isAdmin
    });
    
    if (revenueResult && revenueResult.revenue) {
        const r = revenueResult.revenue;
        
        document.getElementById('sales-paid').textContent = formatCurrency(r.paid || 0);
        document.getElementById('sales-pending').textContent = formatCurrency(r.pending || 0);
        document.getElementById('sales-refunded').textContent = formatCurrency(r.refunded || 0);
        document.getElementById('sales-count').textContent = formatNumber(r.count?.total || 0);
        
        // Por Gateway
        const byGatewayHtml = [];
        for (const [gw, data] of Object.entries(r.by_gateway || {})) {
            byGatewayHtml.push(`
                <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--surface);">
                    <div>
                        <span style="color:var(--light);font-weight:500;">${gw.toUpperCase()}</span>
                        <span style="color:var(--muted);font-size:12px;margin-left:8px;">${data.count} vendas</span>
                    </div>
                    <div>
                        <span style="color:#00d4aa;margin-right:15px;">${formatCurrency(data.paid)}</span>
                        <span style="color:#ffcc00;">${formatCurrency(data.pending)}</span>
                    </div>
                </div>
            `);
        }
        document.getElementById('sales-by-gateway').innerHTML = byGatewayHtml.length ? byGatewayHtml.join('') : '<div class="empty-state"><i class="fas fa-plug"></i><p>Sem dados de gateway</p></div>';
        
    // Por Campanha - Tabela detalhada com metricas
    const campaignMetricsHtml = [];
    const metricFilter = document.getElementById('campaign-metric-filter')?.value || 'all';
    
    for (const [camp, data] of Object.entries(r.by_campaign || {})) {
        const paidAmount = data.paid || 0;
        const pendingAmount = data.pending || 0;
        const refundedAmount = data.refunded || 0;
        const totalBruto = paidAmount + pendingAmount;
        const totalLiquido = paidAmount - refundedAmount;
        
        // Filtro de metricas
        let showRow = true;
        if (metricFilter === 'paid' && paidAmount <= 0) showRow = false;
        if (metricFilter === 'pending' && pendingAmount <= 0) showRow = false;
        if (metricFilter === 'paid_pending' && (paidAmount <= 0 && pendingAmount <= 0)) showRow = false;
        
        if (!showRow) continue;
        
        campaignMetricsHtml.push(`
            <tr style="border-bottom:1px solid var(--surface);">
                <td style="padding:14px 16px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:8px;height:8px;border-radius:50%;background:${totalLiquido > 0 ? '#00d4aa' : '#ff0050'};"></div>
                        <div>
                            <strong style="color:var(--light);">${camp}</strong>
                            <div style="font-size:12px;color:var(--muted);">${data.count || 0} transacoes</div>
                        </div>
                    </div>
                </td>
                <td style="padding:14px 16px;text-align:center;">
                    <span style="color:#00d4aa;font-weight:600;">${formatCurrency(paidAmount)}</span>
                    <div style="font-size:11px;color:var(--muted);">${data.paid_count || 0} vendas</div>
                </td>
                <td style="padding:14px 16px;text-align:center;">
                    <span style="color:#ffcc00;font-weight:600;">${formatCurrency(pendingAmount)}</span>
                    <div style="font-size:11px;color:var(--muted);">${data.pending_count || 0} pendentes</div>
                </td>
                <td style="padding:14px 16px;text-align:center;">
                    <span style="color:#ff0050;font-weight:600;">${formatCurrency(refundedAmount)}</span>
                    <div style="font-size:11px;color:var(--muted);">${data.refunded_count || 0} estornos</div>
                </td>
                <td style="padding:14px 16px;text-align:right;">
                    <span style="color:var(--light);font-weight:500;">${formatCurrency(totalBruto)}</span>
                </td>
                <td style="padding:14px 16px;text-align:right;">
                    <span style="color:#00d4aa;font-weight:700;font-size:16px;">${formatCurrency(totalLiquido)}</span>
                </td>
            </tr>
        `);
    }
    
    // Linha de totais
    if (campaignMetricsHtml.length > 0) {
        const totalPaid = Object.values(r.by_campaign || {}).reduce((sum, d) => sum + (d.paid || 0), 0);
        const totalPending = Object.values(r.by_campaign || {}).reduce((sum, d) => sum + (d.pending || 0), 0);
        const totalRefunded = Object.values(r.by_campaign || {}).reduce((sum, d) => sum + (d.refunded || 0), 0);
        
        campaignMetricsHtml.push(`
            <tr style="background:var(--surface);">
                <td style="padding:14px 16px;"><strong style="color:var(--light);">TOTAL GERAL</strong></td>
                <td style="padding:14px 16px;text-align:center;"><strong style="color:#00d4aa;">${formatCurrency(totalPaid)}</strong></td>
                <td style="padding:14px 16px;text-align:center;"><strong style="color:#ffcc00;">${formatCurrency(totalPending)}</strong></td>
                <td style="padding:14px 16px;text-align:center;"><strong style="color:#ff0050;">${formatCurrency(totalRefunded)}</strong></td>
                <td style="padding:14px 16px;text-align:right;"><strong style="color:var(--light);">${formatCurrency(totalPaid + totalPending)}</strong></td>
                <td style="padding:14px 16px;text-align:right;"><strong style="color:#00d4aa;font-size:18px;">${formatCurrency(totalPaid - totalRefunded)}</strong></td>
            </tr>
        `);
    }
    
    document.getElementById('campaign-metrics-table').innerHTML = campaignMetricsHtml.length ? campaignMetricsHtml.join('') : '<tr><td colspan="6"><div class="empty-state"><i class="fas fa-bullhorn"></i><p>Sem dados de campanha para o periodo selecionado</p></div></td></tr>';
    
    // Resumo geral
    const summaryHtml = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div style="background:rgba(0,212,170,0.1);border-radius:12px;padding:16px;text-align:center;">
                <div style="font-size:28px;font-weight:700;color:#00d4aa;">${formatCurrency(r.total_paid || 0)}</div>
                <div style="color:var(--muted);font-size:13px;margin-top:4px;">Total Pagas</div>
            </div>
            <div style="background:rgba(255,204,0,0.1);border-radius:12px;padding:16px;text-align:center;">
                <div style="font-size:28px;font-weight:700;color:#ffcc00;">${formatCurrency(r.total_pending || 0)}</div>
                <div style="color:var(--muted);font-size:13px;margin-top:4px;">Total Pendentes</div>
            </div>
        </div>
        <div style="margin-top:16px;padding:16px;background:var(--surface);border-radius:12px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                <span style="color:var(--muted);">Taxa de Conversao</span>
                <span style="color:var(--light);font-weight:600;">${r.conversion_rate || '0'}%</span>
            </div>
            <div style="display:flex;justify-content:space-between;">
                <span style="color:var(--muted);">Ticket Medio</span>
                <span style="color:var(--light);font-weight:600;">${formatCurrency(r.avg_ticket || 0)}</span>
            </div>
        </div>
    `;
    document.getElementById('sales-summary').innerHTML = summaryHtml;
        
        // Por Data (grafico de barras)
        const byDateHtml = [];
        const dates = r.by_date || {};
        const maxTotal = Math.max(...Object.values(dates).map(d => d.total || 0), 1);
        
        for (const [date, data] of Object.entries(dates)) {
            const paidWidth = Math.round((data.paid / maxTotal) * 100);
            const pendingWidth = Math.round((data.pending / maxTotal) * 100);
            byDateHtml.push(`
                <div style="margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                        <span style="color:var(--muted);font-size:12px;">${formatDate(date)}</span>
                        <span style="color:var(--light);font-size:12px;">${formatCurrency(data.total)}</span>
                    </div>
                    <div style="display:flex;height:8px;border-radius:4px;overflow:hidden;background:var(--surface);">
                        <div style="width:${paidWidth}%;background:#00d4aa;"></div>
                        <div style="width:${pendingWidth}%;background:#ffcc00;"></div>
                    </div>
                </div>
            `);
        }
        document.getElementById('sales-by-date').innerHTML = byDateHtml.length ? byDateHtml.join('') : '<div class="empty-state"><i class="fas fa-chart-line"></i><p>Sem dados</p></div>';
    }
    
    // Carrega transacoes
    const transResult = await apiCall('get_transactions', {
        gateway: gateway,
        status: status,
        date_from: dateFrom,
        date_to: dateTo,
        user_id: currentUserId,
        is_admin: isAdmin,
        limit: 100
    });
    
    if (transResult && transResult.transactions) {
        renderTransactions(transResult.transactions);
    }
}

// Renderiza tabela de transacoes
function renderTransactions(transactions) {
    const tbody = document.getElementById('sales-table');
    
    if (!transactions.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><i class="fas fa-shopping-cart"></i><p>Nenhuma transacao encontrada</p></div></td></tr>';
        return;
    }
    
    const statusColors = {
        paid: 'success',
        approved: 'success',
        pending: 'warning',
        waiting_payment: 'warning',
        refunded: 'danger',
        chargeback: 'danger',
        cancelled: 'secondary'
    };
    
    const statusLabels = {
        paid: 'Paga',
        approved: 'Aprovada',
        pending: 'Pendente',
        waiting_payment: 'Aguardando',
        refunded: 'Reembolso',
        chargeback: 'Chargeback',
        cancelled: 'Cancelada'
    };
    
    const rows = transactions.map(t => {
        const statusClass = statusColors[t.status] || 'secondary';
        const statusLabel = statusLabels[t.status] || t.status;
        return `
            <tr>
                <td style="white-space:nowrap;">${t.created_at || '-'}</td>
                <td><span class="badge" style="background:rgba(0,212,170,0.15);color:#00d4aa;">${(t.gateway || '-').toUpperCase()}</span></td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;">${t.product_name || '-'}</td>
                <td style="font-size:12px;">${t.customer_email || '-'}</td>
                <td>${t.campaign_slug || '<span style="color:var(--muted)">-</span>'}</td>
                <td style="font-weight:600;color:#00d4aa;">${formatCurrency(t.amount || 0)}</td>
                <td><span class="badge badge-${statusClass}">${statusLabel}</span></td>
            </tr>
        `;
    });
    
    tbody.innerHTML = rows.join('');
}

// Formata moeda
function formatCurrency(value) {
    return 'R$ ' + parseFloat(value || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// Gateways - Inicializa pagina
async function initGateways() {
    const result = await apiCall('get_gateways', {
        user_id: currentUserId
    });
    
    if (!result) return;
    
    const configured = result.configured || {};
    
    // Renderiza gateways configurados
    const configuredHtml = [];
    const gatewayColors = {
        hotmart: '#F04E23', kiwify: '#00D4AA', monetizze: '#FF6B00', perfectpay: '#7C3AED',
        eduzz: '#1E40AF', braip: '#059669', ticto: '#DC2626', pepper: '#EA580C',
        doppus: '#0891B2', greenn: '#16A34A', ironpay: '#3B82F6', blackcat: '#1F2937',
        skalepay: '#8B5CF6', otimizepagamentos: '#F97316', virtualpay: '#06B6D4',
        fastsoft: '#EF4444', plumify: '#A855F7', bynet: '#10B981'
    };
    const gatewayNames = {
        hotmart: 'Hotmart', kiwify: 'Kiwify', monetizze: 'Monetizze', perfectpay: 'PerfectPay',
        eduzz: 'Eduzz', braip: 'Braip', ticto: 'Ticto', pepper: 'Pepper',
        doppus: 'Doppus', greenn: 'Greenn', ironpay: 'IronPay', blackcat: 'BlackCat',
        skalepay: 'SkalePay', otimizepagamentos: 'Otimize Pagamentos', virtualpay: 'VirtualPay',
        fastsoft: 'FastSoft', plumify: 'Plumify', bynet: 'Bynet'
    };
    
    for (const [id, config] of Object.entries(configured)) {
        const color = gatewayColors[id] || '#666';
        const name = gatewayNames[id] || id;
        configuredHtml.push(`
            <div style="display:flex;align-items:center;justify-content:space-between;padding:16px;background:var(--surface);border-radius:12px;margin-bottom:12px;border-left:4px solid ${color};">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;border-radius:8px;background:${color}20;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-credit-card" style="color:${color};"></i>
                    </div>
                    <div>
                        <span style="color:var(--light);font-weight:600;font-size:16px;">${name}</span>
                        <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                            <span style="color:#00d4aa;font-size:12px;"><i class="fas fa-check-circle" style="margin-right:4px;"></i>Ativo</span>
                            <span style="color:var(--muted);font-size:11px;">desde ${config.created_at || '-'}</span>
                        </div>
                    </div>
                </div>
                <button class="btn btn-danger btn-sm" onclick="removeGateway('${id}')"><i class="fas fa-trash"></i> Remover</button>
            </div>
        `);
    }
    document.getElementById('configured-gateways').innerHTML = configuredHtml.length ? configuredHtml.join('') : '<div class="empty-state"><i class="fas fa-plug"></i><p>Nenhum gateway configurado ainda.<br>Selecione um gateway acima para comecar.</p></div>';
}

// Instrucoes especificas de cada gateway
const gatewayInstructions = {
    hotmart: {
        name: 'Hotmart',
        color: '#F04E23',
        event: 'Selecione: <strong>"Criar/Atualizar transacao"</strong> ou <strong>"PURCHASE"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#F04E23;"></i>Passo a Passo - Hotmart</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse <strong>app.hotmart.com</strong> e faca login</li>
                <li>Va em <strong>Ferramentas > Webhooks</strong> (ou Configuracoes > Webhooks)</li>
                <li>Clique em <strong>"Criar Webhook"</strong> ou <strong>"+ Novo"</strong></li>
                <li>Cole a <strong>URL acima</strong> no campo de URL</li>
                <li>Em "Eventos", marque: <strong>PURCHASE_COMPLETE</strong>, <strong>PURCHASE_BILLET_PRINTED</strong>, <strong>REFUND</strong>, <strong>CHARGEBACK</strong></li>
                <li>Clique em <strong>Salvar</strong></li>
            </ol>
            <div style="margin-top:16px;padding:12px;background:rgba(240,78,35,0.1);border-radius:8px;">
                <p style="margin:0;color:#F04E23;font-size:13px;"><i class="fas fa-info-circle" style="margin-right:6px;"></i>A Hotmart vai enviar um POST com os dados da venda assim que houver qualquer atualizacao.</p>
            </div>
        `
    },
    kiwify: {
        name: 'Kiwify',
        color: '#00D4AA',
        event: 'Selecione: <strong>"Todas as atualizacoes de pedido"</strong> ou <strong>"order_paid"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#00D4AA;"></i>Passo a Passo - Kiwify</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse <strong>dashboard.kiwify.com.br</strong></li>
                <li>Va em <strong>Configuracoes > Webhooks</strong></li>
                <li>Clique em <strong>"Adicionar Webhook"</strong></li>
                <li>Cole a <strong>URL acima</strong> no campo</li>
                <li>Marque os eventos: <strong>order_paid</strong>, <strong>order_refunded</strong>, <strong>order_chargedback</strong></li>
                <li>Clique em <strong>Salvar</strong></li>
            </ol>
            <div style="margin-top:16px;padding:12px;background:rgba(0,212,170,0.1);border-radius:8px;">
                <p style="margin:0;color:#00D4AA;font-size:13px;"><i class="fas fa-info-circle" style="margin-right:6px;"></i>A Kiwify envia automaticamente os UTMs capturados no checkout.</p>
            </div>
        `
    },
    monetizze: {
        name: 'Monetizze',
        color: '#FF6B00',
        event: 'Selecione: <strong>"Postback de Vendas"</strong> - Tipo: <strong>JSON</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#FF6B00;"></i>Passo a Passo - Monetizze</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse <strong>app.monetizze.com.br</strong></li>
                <li>Va em <strong>Minha Conta > Configuracoes > Postbacks</strong></li>
                <li>Clique em <strong>"Adicionar Postback"</strong></li>
                <li>Cole a <strong>URL acima</strong></li>
                <li>Selecione o formato: <strong>JSON</strong></li>
                <li>Marque os eventos de venda desejados</li>
                <li>Clique em <strong>Salvar</strong></li>
            </ol>
        `
    },
    eduzz: {
        name: 'Eduzz',
        color: '#1E40AF',
        event: 'Selecione: <strong>"Fatura paga"</strong>, <strong>"Reembolso"</strong>, <strong>"Chargeback"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#1E40AF;"></i>Passo a Passo - Eduzz</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse <strong>orbita.eduzz.com</strong></li>
                <li>Va em <strong>Configuracoes > Webhooks</strong></li>
                <li>Clique em <strong>"Novo Webhook"</strong></li>
                <li>Cole a <strong>URL acima</strong></li>
                <li>Selecione os eventos de pagamento</li>
                <li>Clique em <strong>Salvar</strong></li>
            </ol>
        `
    },
    perfectpay: {
        name: 'PerfectPay',
        color: '#7C3AED',
        event: 'Selecione: <strong>"Transacao Aprovada"</strong>, <strong>"Reembolso"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#7C3AED;"></i>Passo a Passo - PerfectPay</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse o <strong>painel PerfectPay</strong></li>
                <li>Va em <strong>Integracoes > Webhooks</strong></li>
                <li>Clique em <strong>"Adicionar"</strong></li>
                <li>Cole a <strong>URL acima</strong></li>
                <li>Selecione os eventos desejados</li>
                <li>Salve a configuracao</li>
            </ol>
        `
    },
    braip: {
        name: 'Braip',
        color: '#059669',
        event: 'Selecione: <strong>"Venda aprovada"</strong>, <strong>"Reembolso"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#059669;"></i>Passo a Passo - Braip</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse <strong>braip.com</strong></li>
                <li>Va em <strong>Configuracoes > Postbacks</strong></li>
                <li>Adicione um novo postback</li>
                <li>Cole a <strong>URL acima</strong></li>
                <li>Configure os eventos</li>
                <li>Salve</li>
            </ol>
        `
    },
    ticto: {
        name: 'Ticto',
        color: '#DC2626',
        event: 'Selecione: <strong>"Pagamento Confirmado"</strong>, <strong>"Estorno"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#DC2626;"></i>Passo a Passo - Ticto</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse o <strong>painel Ticto</strong></li>
                <li>Va em <strong>Integracoes > Webhooks</strong></li>
                <li>Crie um novo webhook</li>
                <li>Cole a <strong>URL acima</strong></li>
                <li>Selecione os eventos de pagamento</li>
                <li>Salve</li>
            </ol>
        `
    },
    ironpay: {
        name: 'IronPay',
        color: '#3B82F6',
        event: 'Selecione: <strong>"transaction.paid"</strong>, <strong>"transaction.refunded"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#3B82F6;"></i>Passo a Passo - IronPay</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse <strong>app.ironpayapp.com.br</strong></li>
                <li>Va em <strong>Configuracoes > Webhooks</strong></li>
                <li>Clique em <strong>"Novo Webhook"</strong></li>
                <li>Cole a <strong>URL acima</strong> no campo URL</li>
                <li>Selecione os eventos: <strong>transaction.paid</strong>, <strong>transaction.refunded</strong></li>
                <li>Salve a configuracao</li>
            </ol>
        `
    },
    blackcat: {
        name: 'BlackCat',
        color: '#1F2937',
        event: 'Selecione: <strong>"transaction.paid"</strong>, <strong>"transaction.refunded"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#6B7280;"></i>Passo a Passo - BlackCat</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse <strong>app.blackcatpay.com.br</strong></li>
                <li>Va em <strong>Desenvolvedores > Webhooks</strong></li>
                <li>Clique em <strong>"Criar Webhook"</strong></li>
                <li>Cole a <strong>URL acima</strong></li>
                <li>Marque: <strong>transaction.paid</strong>, <strong>transaction.created</strong>, <strong>transaction.refunded</strong></li>
                <li>Salve</li>
            </ol>
            <div style="margin-top:16px;padding:12px;background:rgba(107,114,128,0.1);border-radius:8px;">
                <p style="margin:0;color:#9CA3AF;font-size:13px;"><i class="fas fa-info-circle" style="margin-right:6px;"></i>O BlackCat envia UTMs automaticamente no objeto "utm" do payload.</p>
            </div>
        `
    },
    skalepay: {
        name: 'SkalePay',
        color: '#8B5CF6',
        event: 'Selecione: <strong>"Transacao Atualizada"</strong> ou <strong>"transaction.updated"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#8B5CF6;"></i>Passo a Passo - SkalePay</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse o <strong>painel SkalePay</strong></li>
                <li>Va em <strong>Configuracoes > Webhooks</strong></li>
                <li>Adicione um novo webhook</li>
                <li>Cole a <strong>URL acima</strong></li>
                <li>Selecione o evento de transacao</li>
                <li>Salve</li>
            </ol>
        `
    },
    otimizepagamentos: {
        name: 'Otimize Pagamentos',
        color: '#F97316',
        event: 'Selecione: <strong>"Notificacao de Transacao"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#F97316;"></i>Passo a Passo - Otimize Pagamentos</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse o <strong>painel Otimize</strong></li>
                <li>Va em <strong>API > Webhooks</strong></li>
                <li>Crie um novo webhook</li>
                <li>Cole a <strong>URL acima</strong></li>
                <li>Selecione os eventos de pagamento</li>
                <li>Salve</li>
            </ol>
        `
    },
    virtualpay: {
        name: 'VirtualPay',
        color: '#06B6D4',
        event: 'Selecione: <strong>"transaction.updated"</strong> ou <strong>"Atualizacao de Pagamento"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#06B6D4;"></i>Passo a Passo - VirtualPay</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse <strong>virtualpay.online</strong></li>
                <li>Va em <strong>Integracoes > Webhooks</strong></li>
                <li>Clique em <strong>"Novo"</strong></li>
                <li>Cole a <strong>URL acima</strong></li>
                <li>Selecione os eventos de transacao</li>
                <li>Salve</li>
            </ol>
        `
    },
    fastsoft: {
        name: 'FastSoft',
        color: '#EF4444',
        event: 'Selecione: <strong>"Pagamento Aprovado"</strong>, <strong>"Estorno"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#EF4444;"></i>Passo a Passo - FastSoft</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse <strong>app.fastsoftbrasil.com</strong></li>
                <li>Va em <strong>Configuracoes > Webhooks</strong></li>
                <li>Adicione um webhook</li>
                <li>Cole a <strong>URL acima</strong></li>
                <li>Selecione os eventos</li>
                <li>Salve</li>
            </ol>
        `
    },
    plumify: {
        name: 'Plumify',
        color: '#A855F7',
        event: 'Selecione: <strong>"transaction.paid"</strong>, <strong>"transaction.refunded"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#A855F7;"></i>Passo a Passo - Plumify</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse o <strong>painel Plumify</strong></li>
                <li>Va em <strong>Desenvolvedores > Webhooks</strong></li>
                <li>Crie um novo webhook</li>
                <li>Cole a <strong>URL acima</strong></li>
                <li>Marque os eventos de transacao</li>
                <li>Salve</li>
            </ol>
        `
    },
    bynet: {
        name: 'Bynet',
        color: '#10B981',
        event: 'Selecione: <strong>"Criar/Atualizar transacao"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#10B981;"></i>Passo a Passo - Bynet (TechByNet)</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse o <strong>painel da Bynet</strong></li>
                <li>Va em <strong>Webhooks</strong> e clique em <strong>"Editar Webhook"</strong> ou <strong>"Adicionar"</strong></li>
                <li>Cole a <strong>URL acima</strong> no campo de URL</li>
                <li>Em <strong>Tipo</strong>, selecione: <strong>"Criar/Atualizar transacao"</strong></li>
                <li>Clique em <strong>Salvar</strong></li>
            </ol>
            <div style="margin-top:16px;padding:12px;background:rgba(16,185,129,0.1);border-radius:8px;">
                <p style="margin:0;color:#10B981;font-size:13px;"><i class="fas fa-info-circle" style="margin-right:6px;"></i>Opcoes disponiveis: Criar/Atualizar transacao, Criar/Atualizar infracao, Criar/Atualizar transferencia. Escolha <strong>"Criar/Atualizar transacao"</strong> para rastrear vendas.</p>
            </div>
        `
    },
    pepper: {
        name: 'Pepper',
        color: '#EA580C',
        event: 'Selecione: <strong>"Venda Aprovada"</strong>, <strong>"Reembolso"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#EA580C;"></i>Passo a Passo - Pepper</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse o <strong>painel Pepper</strong></li>
                <li>Va em <strong>Configuracoes > Webhooks</strong></li>
                <li>Adicione um webhook</li>
                <li>Cole a <strong>URL acima</strong></li>
                <li>Selecione os eventos</li>
                <li>Salve</li>
            </ol>
        `
    },
    doppus: {
        name: 'Doppus',
        color: '#0891B2',
        event: 'Selecione: <strong>"Pagamento Confirmado"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#0891B2;"></i>Passo a Passo - Doppus</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse o <strong>painel Doppus</strong></li>
                <li>Va em <strong>Integracoes > Webhooks</strong></li>
                <li>Crie um webhook</li>
                <li>Cole a <strong>URL acima</strong></li>
                <li>Selecione os eventos</li>
                <li>Salve</li>
            </ol>
        `
    },
    greenn: {
        name: 'Greenn',
        color: '#16A34A',
        event: 'Selecione: <strong>"Pagamento Aprovado"</strong>, <strong>"Reembolso"</strong>',
        steps: `
            <h4 style="color:var(--light);margin:0 0 16px 0;"><i class="fas fa-list-ol" style="margin-right:8px;color:#16A34A;"></i>Passo a Passo - Greenn</h4>
            <ol style="color:var(--light);line-height:2.2;margin:0;padding-left:20px;">
                <li>Acesse o <strong>painel Greenn</strong></li>
                <li>Va em <strong>Configuracoes > Postbacks</strong></li>
                <li>Adicione um postback</li>
                <li>Cole a <strong>URL acima</strong></li>
                <li>Selecione os eventos</li>
                <li>Salve</li>
            </ol>
        `
    }
};

// Mostra instrucoes do gateway selecionado
async function showGatewayInstructions() {
    const select = document.getElementById('gateway-select');
    const gatewayId = select.value;
    const instructionsDiv = document.getElementById('gateway-instructions');
    
    if (!gatewayId) {
        instructionsDiv.style.display = 'none';
        return;
    }
    
    const gw = gatewayInstructions[gatewayId];
    if (!gw) {
        instructionsDiv.style.display = 'none';
        return;
    }
    
    // Gera URL do webhook
    const baseUrl = window.location.origin;
    const webhookUrl = baseUrl + '/COMMANDERV3/webhook.php?gateway=' + gatewayId;
    
    document.getElementById('gateway-webhook-url').value = webhookUrl;
    document.getElementById('gateway-name-title').textContent = gw.name;
    document.getElementById('gateway-name-title').style.color = gw.color;
    document.getElementById('gateway-steps').innerHTML = gw.steps;
    document.getElementById('gateway-event').innerHTML = gw.event;
    
    instructionsDiv.style.display = 'block';
    instructionsDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Copia URL do webhook
function copyWebhookUrl() {
    const input = document.getElementById('gateway-webhook-url');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        showToast('URL copiada! Cole no painel do gateway.', 'success');
    }).catch(() => {
        document.execCommand('copy');
        showToast('URL copiada!');
    });
}

// Salva configuracao do gateway
async function saveGatewayConfig() {
    const gatewayId = document.getElementById('gateway-select').value;
    
    if (!gatewayId) {
        showToast('Selecione um gateway primeiro', 'error');
        return;
    }
    
    const result = await apiCall('save_gateway', {
        gateway_id: gatewayId,
        enabled: true,
        user_id: currentUserId
    });
    
    if (result && result.success) {
        showToast('Gateway ' + gatewayInstructions[gatewayId].name + ' ativado com sucesso!', 'success');
        document.getElementById('gateway-select').value = '';
        document.getElementById('gateway-instructions').style.display = 'none';
        initGateways();
    } else {
        const errorMsg = result?.error || 'Erro desconhecido';
        console.log('[v0] Erro ao salvar gateway:', result);
        showToast('Erro ao salvar gateway: ' + errorMsg, 'error');
    }
}

// Remove gateway
async function removeGateway(gatewayId) {
    if (!confirm('Remover configuracao deste gateway?\n\nAs vendas ja registradas serao mantidas.')) return;
    
    const result = await apiCall('remove_gateway', {
        gateway_id: gatewayId,
        user_id: currentUserId
    });
    
    if (result && result.success) {
        showToast('Gateway removido!');
        initGateways();
    }
}

// ============================================
// PIXELS - Configuracao de conversao
// ============================================

async function initPixels() {
    const result = await apiCall('get_pixels', {
        user_id: currentUserId
    });
    
    if (!result || !result.pixels) return;
    
    const pixels = result.pixels;
    
    // Facebook
    if (pixels.facebook) {
        document.getElementById('pixel-facebook-id').value = pixels.facebook.id || '';
        document.getElementById('pixel-facebook-token').value = pixels.facebook.token || '';
        document.getElementById('pixel-facebook-event').value = pixels.facebook.event || 'Purchase';
        document.getElementById('pixel-facebook-enabled').checked = pixels.facebook.enabled || false;
    }
    
    // Google
    if (pixels.google) {
        document.getElementById('pixel-google-id').value = pixels.google.id || '';
        document.getElementById('pixel-google-label').value = pixels.google.label || '';
        document.getElementById('pixel-google-enabled').checked = pixels.google.enabled || false;
    }
    
    // TikTok
    if (pixels.tiktok) {
        document.getElementById('pixel-tiktok-id').value = pixels.tiktok.id || '';
        document.getElementById('pixel-tiktok-token').value = pixels.tiktok.token || '';
        document.getElementById('pixel-tiktok-event').value = pixels.tiktok.event || 'CompletePayment';
        document.getElementById('pixel-tiktok-enabled').checked = pixels.tiktok.enabled || false;
    }
    
    // Taboola
    if (pixels.taboola) {
        document.getElementById('pixel-taboola-id').value = pixels.taboola.id || '';
        document.getElementById('pixel-taboola-event').value = pixels.taboola.event || 'purchase';
        document.getElementById('pixel-taboola-enabled').checked = pixels.taboola.enabled || false;
    }
    
    // Outbrain
    if (pixels.outbrain) {
        document.getElementById('pixel-outbrain-id').value = pixels.outbrain.id || '';
        document.getElementById('pixel-outbrain-event').value = pixels.outbrain.event || 'purchase';
        document.getElementById('pixel-outbrain-enabled').checked = pixels.outbrain.enabled || false;
    }
    
    // Kwai
    if (pixels.kwai) {
        document.getElementById('pixel-kwai-id').value = pixels.kwai.id || '';
        document.getElementById('pixel-kwai-token').value = pixels.kwai.token || '';
        document.getElementById('pixel-kwai-enabled').checked = pixels.kwai.enabled || false;
    }
}

async function savePixels() {
    const pixels = {
        facebook: {
            id: document.getElementById('pixel-facebook-id').value.trim(),
            token: document.getElementById('pixel-facebook-token').value.trim(),
            event: document.getElementById('pixel-facebook-event').value,
            enabled: document.getElementById('pixel-facebook-enabled').checked
        },
        google: {
            id: document.getElementById('pixel-google-id').value.trim(),
            label: document.getElementById('pixel-google-label').value.trim(),
            enabled: document.getElementById('pixel-google-enabled').checked
        },
        tiktok: {
            id: document.getElementById('pixel-tiktok-id').value.trim(),
            token: document.getElementById('pixel-tiktok-token').value.trim(),
            event: document.getElementById('pixel-tiktok-event').value,
            enabled: document.getElementById('pixel-tiktok-enabled').checked
        },
        taboola: {
            id: document.getElementById('pixel-taboola-id').value.trim(),
            event: document.getElementById('pixel-taboola-event').value.trim(),
            enabled: document.getElementById('pixel-taboola-enabled').checked
        },
        outbrain: {
            id: document.getElementById('pixel-outbrain-id').value.trim(),
            event: document.getElementById('pixel-outbrain-event').value.trim(),
            enabled: document.getElementById('pixel-outbrain-enabled').checked
        },
        kwai: {
            id: document.getElementById('pixel-kwai-id').value.trim(),
            token: document.getElementById('pixel-kwai-token').value.trim(),
            enabled: document.getElementById('pixel-kwai-enabled').checked
        }
    };
    
    const result = await apiCall('save_pixels', {
        pixels: JSON.stringify(pixels),
        user_id: currentUserId
    });
    
    if (result && result.success) {
        showToast('Configuracoes de pixels salvas com sucesso!', 'success');
    } else {
        showToast('Erro ao salvar pixels: ' + (result?.error || 'Erro desconhecido'), 'error');
    }
}

async function testPixel(platform) {
    showToast('Enviando evento de teste para ' + platform + '...');
    
    const result = await apiCall('test_pixel', {
        platform: platform,
        user_id: currentUserId
    });
    
    if (result && result.success) {
        showToast('Evento de teste enviado para ' + platform + '! Verifique no gerenciador de eventos.', 'success');
    } else {
        showToast('Erro ao testar ' + platform + ': ' + (result?.error || 'Verifique as configuracoes'), 'error');
    }
}

// Filtrar metricas de campanha
function filterCampaignMetrics() {
    // Recarrega com o filtro selecionado
    loadSales();
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

// ===== Dominios =====
let domainsData = [];
let commanderHost = '';
let commanderIp = '';

async function loadDomains() {
    const result = await apiCall('get_domains');
    if (!result) return;
    domainsData = result.domains || [];
    commanderHost = result.commander_host || '';
    commanderIp = result.server_ip || '';

    document.getElementById('commander-host').textContent = commanderHost || '-';
    document.getElementById('commander-ip').textContent = commanderIp || '-';
    const dnsA = document.getElementById('dns-a-value');
    if (dnsA) dnsA.textContent = commanderIp || '(IP do servidor)';
    const stepsIp = document.getElementById('steps-ip');
    if (stepsIp) stepsIp.textContent = commanderIp || 'IP do servidor';

    renderDomains();
}

function renderDomains() {
    const tbody = document.getElementById('domains-table');

    if (!domainsData.length) {
        tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><i class="fas fa-globe"></i><h3>Nenhum domínio</h3><p>Clique em "Adicionar domínio" para cadastrar o primeiro</p></div></td></tr>';
        return;
    }

    const statusMap = {
        connected: '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Ativo</span>',
        pending:   '<span class="badge badge-warning"><i class="fas fa-clock"></i> Pendente</span>',
        error:     '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Não resolve</span>'
    };

    tbody.innerHTML = domainsData.map(d => {
        const statusBadge = statusMap[d.status] || statusMap.pending;
        const resolved = d.resolved ? escapeHtml(d.resolved) : '<span style="color:var(--muted);">-</span>';
        const usedBy = d.used_by
            ? escapeHtml(d.used_by)
            : '<span style="color:var(--muted);font-size:12px;">Nenhuma campanha</span>';
        return `
            <tr>
                <td><a href="https://${escapeHtml(d.domain)}" target="_blank" rel="noopener" style="color:var(--primary);font-weight:600;">${escapeHtml(d.domain)}</a></td>
                <td>${usedBy}</td>
                <td style="font-size:12px;color:var(--muted);">${resolved}</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="actions">
                        <button class="action-btn" onclick="verifyDomain('${escapeHtml(d.id)}')" title="Verificar"><i class="fas fa-rotate"></i></button>
                        <button class="action-btn" onclick="deleteDomain('${escapeHtml(d.id)}','${escapeHtml(d.domain)}')" title="Excluir"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// Abre o modal de adicionar dominio
function openAddDomainModal() {
    const input = document.getElementById('add-domain-input');
    if (input) input.value = '';
    const ip = document.getElementById('add-domain-ip');
    if (ip) ip.textContent = commanderIp || 'IP do servidor';
    openModal('add-domain-modal');
}

// Salva um novo dominio no registro
async function submitAddDomain() {
    const input = document.getElementById('add-domain-input');
    const domain = (input?.value || '').trim();
    if (!domain) { showToast('Digite um domínio', 'warning'); return; }

    const result = await apiCall('add_domain', { domain });
    if (result?.success) {
        showToast('Domínio adicionado! Aponte o DNS e clique em Verificar.', 'success');
        closeModal('add-domain-modal');
        loadDomains();
    } else {
        showToast(result?.error || 'Erro ao adicionar domínio', 'error');
    }
}

// Reverifica um dominio especifico
async function verifyDomain(id) {
    showToast('Verificando...', 'info');
    const result = await apiCall('verify_domain', { domain_id: id });
    if (result) {
        domainsData = result.domains || domainsData;
        renderDomains();
        const d = domainsData.find(x => x.id === id);
        if (d && d.status === 'connected') showToast('Domínio ativo!', 'success');
        else if (d && d.status === 'error') showToast('Ainda não resolve. Confira o DNS.', 'warning');
        else showToast('Ainda pendente. Aguarde a propagação do DNS.', 'warning');
    }
}

// Remove um dominio do registro
async function deleteDomain(id, domain) {
    if (!confirm('Excluir o domínio "' + domain + '"? As campanhas que o usam precisarão de outro domínio.')) return;
    const result = await apiCall('delete_domain', { domain_id: id });
    if (result?.success) {
        showToast('Domínio removido', 'success');
        loadDomains();
    } else {
        showToast('Erro ao remover', 'error');
    }
}

function openDomainHelpModal() {
  const dnsA = document.getElementById('dns-a-value');
  if (dnsA) dnsA.textContent = commanderIp || '(IP do servidor)';
  openModal('domain-help-modal');
  }

function copyText(text) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(
        () => showToast('Copiado!', 'success'),
        () => showToast('Não foi possível copiar', 'error')
    );
}

// Preenche o dropdown de dominios da campanha apenas com dominios ativos.
// Mantem o dominio atual da campanha selecionado mesmo se ainda nao estiver ativo.
async function populateCampaignDomains(selected) {
    const sel = document.getElementById('campaign-domain');
    if (!sel) return;
    sel.innerHTML = '<option value="">Selecione um domínio ativo...</option>';

    const result = await apiCall('get_active_domains');
    const domains = (result && result.domains) ? result.domains : [];

    domains.forEach(d => {
        const opt = document.createElement('option');
        opt.value = d;
        opt.textContent = d;
        sel.appendChild(opt);
    });

    // Se a campanha ja tem um dominio salvo que nao esta na lista de ativos,
    // adiciona ele assim mesmo (marcado) para nao perder o valor ao editar.
    if (selected && !domains.includes(selected)) {
        const opt = document.createElement('option');
        opt.value = selected;
        opt.textContent = selected + ' (inativo)';
        sel.appendChild(opt);
    }

    sel.value = selected || '';

    if (domains.length === 0 && !selected) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.disabled = true;
        opt.textContent = 'Nenhum domínio ativo — adicione em Domínios';
        sel.appendChild(opt);
    }
}

function openCampaignModal(campaign = null) {
    document.getElementById('campaign-modal-title').textContent = campaign ? 'Editar Campanha' : 'Nova Campanha';
    document.getElementById('campaign-id').value = campaign?.id || '';
    document.getElementById('campaign-name').value = campaign?.name || '';
    document.getElementById('campaign-slug').value = campaign?.slug || '';
    populateCampaignDomains(campaign?.domain || '');
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

    // Idioma da tela de verificacao
    document.getElementById('campaign-verify-language').value = campaign?.verify_language || 'auto';

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

// ===== FORCE WHITE (lista manual de bots -> white page) =====
async function loadForceWhite() {
    const result = await apiCall('get_force_white');
    const div = document.getElementById('forcewhite-table');
    const countEl = document.getElementById('forcewhite-count');
    if (!div) return;

    if (!result || result.error) {
        div.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>' + escapeHtml(result?.error || 'Erro ao carregar') + '</p></div>';
        if (countEl) countEl.textContent = '0';
        return;
    }

    // Contadores de bots armazenados (SQLite / JSON)
    const stats = result.bot_stats || {};
    const fmt = (n) => (n || 0).toLocaleString('pt-BR');
    const fpEl = document.getElementById('bots-total-fp');
    const ipsEl = document.getElementById('bots-total-ips');
    const engEl = document.getElementById('bots-engine');
    if (fpEl) fpEl.textContent = fmt(stats.total_fingerprints);
    if (ipsEl) ipsEl.textContent = fmt(stats.total_ips);
    if (engEl) engEl.textContent = result.engine || '-';

    const entries = result.entries || [];
    if (countEl) countEl.textContent = entries.length;

    if (!entries.length) {
        div.innerHTML = '<div class="empty-state"><i class="fas fa-list"></i><p>Nenhum IP/faixa na lista Force White</p></div>';
        return;
    }

    div.innerHTML = entries.map(entry => {
        const isCidr = entry.includes('/');
        return `
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--surface);">
                <div>
                    <code style="color:#ffcc00">${escapeHtml(entry)}</code>
                    ${isCidr ? '<span style="color:var(--muted);margin-left:10px;font-size:12px;">faixa CIDR</span>' : ''}
                </div>
                <button class="action-btn danger" onclick="removeForceWhite('${escapeHtml(entry)}')" title="Remover"><i class="fas fa-times"></i></button>
            </div>
        `;
    }).join('');
}

async function addForceWhite() {
    const input = document.getElementById('forcewhite-input');
    const raw = (input?.value || '').trim();
    if (!raw) {
        showToast('Informe ao menos um IP ou faixa CIDR', 'warning');
        return;
    }

    const result = await apiCall('add_force_white', { entry: raw });
    if (!result || result.error) {
        showToast(result?.error || 'Erro ao adicionar', 'error');
        return;
    }

    input.value = '';
    let msg = result.added + ' entrada(s) adicionada(s)';
    if (result.invalid && result.invalid.length) {
        msg += ' | ignoradas: ' + result.invalid.join(', ');
    }
    showToast(msg, 'success');
    loadForceWhite();
}

async function removeForceWhite(entry) {
    if (!confirm('Remover "' + entry + '" da lista Force White?')) return;
    const result = await apiCall('remove_force_white', { entry: entry });
    if (!result || result.error) {
        showToast(result?.error || 'Erro ao remover', 'error');
        return;
    }
    showToast('Removido da lista', 'success');
    loadForceWhite();
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
