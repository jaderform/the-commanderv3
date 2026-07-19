<?php
/**
 * COMMANDER V10.3 - API de Cloaking
 * 
 * Endpoint principal para verificação de tráfego
 * 
 * INSTRUÇÕES: Substitua o arquivo api.php existente por este
 */

// Define constante de acesso
define('COMMANDER_ACCESS', true);

// Carrega dependências
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/functions.php';

// Carrega novos sistemas avancados (se existirem)
if (file_exists(__DIR__ . '/tls-fingerprint.php')) {
    require_once __DIR__ . '/tls-fingerprint.php';
}
if (file_exists(__DIR__ . '/ml-detector.php')) {
    require_once __DIR__ . '/ml-detector.php';
}

// Inicializa segurança
$security = CommanderSecurity::getInstance();

// Headers de segurança
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// CORS para requisições do tracker
$allowedOrigins = ['*']; // Em produção, especifique domínios permitidos
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: {$origin}");
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Token, X-Requested-With');

// Trata preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// =============================================
// IFRAME (STEALTH) - Codigo mostra WHITE, visual mostra BLACK
// Chamado via GET ?action=iframe&white=URL&black=URL
// Bot ve HTML da white, humano ve black via Shadow DOM
// =============================================
if (isset($_GET['action']) && ($_GET['action'] === 'iframe' || $_GET['action'] === 'stealth')) {
    $stealthFile = __DIR__ . '/proxy-stealth.php';
    
    if (!file_exists($stealthFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'Proxy iframe nao disponivel']);
        exit;
    }
    
    require_once $stealthFile;
    
    $whiteUrl = $_GET['white'] ?? '';
    $blackUrl = $_GET['black'] ?? '';
    
    if (empty($whiteUrl) || empty($blackUrl)) {
        http_response_code(400);
        echo json_encode(['error' => 'URLs white e black sao obrigatorias']);
        exit;
    }
    
    proxyStealthRequest($whiteUrl, $blackUrl);
}

// =============================================
// IFRAME PROXY - Serve conteudo para ser carregado em iframe
// Remove X-Frame-Options que bloqueiam iframe
// Chamado via GET ?action=iframeproxy&url=...
// =============================================
if (isset($_GET['action']) && $_GET['action'] === 'iframeproxy' && isset($_GET['url'])) {
    $proxyFile = __DIR__ . '/proxy-universal.php';
    
    if (!file_exists($proxyFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'Proxy nao disponivel']);
        exit;
    }
    
    require_once $proxyFile;
    
    $targetUrl = filter_var($_GET['url'], FILTER_VALIDATE_URL);
    if (!$targetUrl) {
        http_response_code(400);
        echo json_encode(['error' => 'URL invalida']);
        exit;
    }
    
    serveForIframe($targetUrl);
}

// =============================================
// PROXY UNIVERSAL - Funciona com qualquer site
// Chamado via GET ?action=proxy&url=... ou ?action=proxy&_asset=... ou ?action=proxy&_nav=...
// =============================================
if (isset($_GET['action']) && $_GET['action'] === 'proxy') {
    // Carrega proxy universal (prioridade) ou fallback para v4
    $proxyFile = __DIR__ . '/proxy-universal.php';
    if (!file_exists($proxyFile)) {
        $proxyFile = __DIR__ . '/proxy-v4.php';
    }
    
    if (!file_exists($proxyFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'Proxy nao disponivel']);
        exit;
    }
    
    require_once $proxyFile;
    
    // Modo iframe (mais compativel)
    if (isset($_GET['iframe'])) {
        proxyRequest(null);
    }
    // Asset (CSS, JS, imagem, fonte)
    elseif (isset($_GET['_asset'])) {
        proxyRequest(null);
    }
    // Navegacao interna
    elseif (isset($_GET['_nav'])) {
        proxyRequest(null);
    }
    // URL principal
    elseif (isset($_GET['url'])) {
        $proxyUrl = filter_var($_GET['url'], FILTER_VALIDATE_URL);
        if (!$proxyUrl) {
            http_response_code(400);
            echo json_encode(['error' => 'URL invalida']);
            exit;
        }
        proxyRequest($proxyUrl);
    }
    else {
        http_response_code(400);
        echo json_encode(['error' => 'URL nao especificada']);
        exit;
    }
}

// =============================================
// LOG ENDPOINT - Recebe logs de acesso dos trackers
// Chamado via POST ?action=log ou /log
// =============================================
if ((isset($_GET['action']) && $_GET['action'] === 'log') || 
    (strpos($_SERVER['REQUEST_URI'] ?? '', '/log') !== false && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    
    // Recebe dados do log
    $logData = json_decode(file_get_contents('php://input'), true);
    if (!$logData) {
        $logData = $_POST;
    }
    
    // Valida dados minimos
    if (empty($logData['campaign_slug'])) {
        http_response_code(400);
        echo json_encode(['error' => 'campaign_slug obrigatorio']);
        exit;
    }
    
    // Salva o log usando a funcao do functions.php
    if (function_exists('saveAccessLog')) {
        saveAccessLog($logData);
        echo json_encode(['success' => true]);
    } else {
        // Fallback: salva em arquivo local
        $logDir = DATA_DIR . 'access_logs/';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $date = date('Y-m-d');
        $filename = $logDir . $date . '.json';
        
        $existingLogs = [];
        if (file_exists($filename)) {
            $existingLogs = json_decode(file_get_contents($filename), true) ?: [];
        }
        
        $existingLogs[] = $logData;
        file_put_contents($filename, json_encode($existingLogs, JSON_PRETTY_PRINT), LOCK_EX);
        
        echo json_encode(['success' => true]);
    }
    exit;
}

/**
 * Classe principal da API
 */
class CommanderAPI {
    
    private $security;
    private $requestData;
    private $clientIP;
    private $userAgent;
    
    public function __construct() {
        global $security;
        $this->security = $security;
        $this->clientIP = $this->security->getRealIP();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $this->requestData = $this->getRequestData();
    }
    
    /**
     * Processa a requisição
     */
    public function handle() {
        try {
            // =====================================================
            // NAVEGACAO INTERNA DO BLACK - PULA TODA VERIFICACAO
            // Quando usuario ja validado clica em link dentro do black,
            // _blacknav indica que nao precisa re-verificar
            // =====================================================
            if (isset($_GET['_blacknav']) && !empty($_GET['_blacknav'])) {
                return $this->handleInternalBlackNavigation();
            }
            
            // Rate limiting
            if (!$this->security->checkRateLimit($this->clientIP)) {
                $this->respond(['error' => 'Rate limit exceeded'], 429);
            }
            
            // Obtém ação
            $action = $this->requestData['action'] ?? 'check';
            
switch ($action) {
                case 'check':
                    return $this->checkTraffic();
                
                case 'conversion':
                    return $this->registerConversion();
                
                case 'pixel':
                    return $this->handlePixel();
                
                case 'health':
                    return $this->healthCheck();
                
                // V10.4 - Novas acoes
                case 'js_challenge':
                    return $this->getJSChallenge();
                
                case 'charts':
                    return $this->getChartData();
                
                case 'proxy':
                    return $this->handleProxy();
                
                case 'summary':
                    return $this->getDashboardData();
                
                case 'webhook_test':
                    return $this->testWebhook();
                
                // V10.5 - Behavioral tracking
                case 'update_behavior':
                    return $this->updateBehaviorData();
                
                // V11 - Machine Learning
                case 'ml_train_human':
                    return $this->mlTrainHuman();
                
                case 'ml_train_bot':
                    return $this->mlTrainBot();
                
                case 'ml_stats':
                    return $this->mlGetStats();
                
                case 'biometrics':
                    return $this->processBiometrics();
                
                default:
                    $this->respond(['error' => 'Invalid action'], 400);
            }
            
        } catch (Exception $e) {
            debugLog('API Error', ['message' => $e->getMessage()]);
            $this->respond(['error' => 'Internal error'], 500);
        }
    }
    
    /**
     * Navegacao interna do black - pula verificacao de bot
     * Usuario ja foi validado na primeira visita
     */
    private function handleInternalBlackNavigation() {
        $blackNav = $_GET['_blacknav'] ?? '';
        $whiteUrl = $_GET['white'] ?? '';
        
        // Decodifica URL do black
        $blackUrl = base64_decode($blackNav);
        
        if (empty($blackUrl) || !filter_var($blackUrl, FILTER_VALIDATE_URL)) {
            $this->respond(['error' => 'URL invalida'], 400);
            return;
        }
        
        // Se nao tem white, tenta pegar da campanha ou usa placeholder
        if (empty($whiteUrl)) {
            $whiteUrl = 'https://example.com';
        }
        
        // Carrega proxy-stealth e serve black diretamente
        $proxyFile = __DIR__ . '/proxy-stealth.php';
        if (file_exists($proxyFile)) {
            require_once $proxyFile;
            if (function_exists('serveBlackDirectly')) {
                serveBlackDirectly($whiteUrl, $blackUrl);
                exit;
            }
        }
        
        // Fallback: usa proxy-universal para servir iframe
        $proxyUniversal = __DIR__ . '/proxy-universal.php';
        if (file_exists($proxyUniversal)) {
            require_once $proxyUniversal;
            if (function_exists('serveForIframe')) {
                serveForIframe($blackUrl);
                exit;
            }
        }
        
        // Ultimo fallback: redirect direto
        header('Location: ' . $blackUrl);
        exit;
    }
    
    /**
     * Proxy reverso universal - usa proxy-v4.php
     */
    private function handleProxy() {
        $url = $this->sanitize($this->requestData['url'] ?? '');
        
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->respond(['error' => 'URL invalida'], 400);
        }
        
        // Carrega o proxy-v4.php
        $proxyFile = __DIR__ . '/proxy-v4.php';
        if (!file_exists($proxyFile)) {
            $this->respond(['error' => 'Proxy nao disponivel'], 500);
        }
        
        require_once $proxyFile;
        
        // Executa o proxy (isso vai dar output e exit)
        proxyRequest($url);
    }
    
    /**
     * Verifica tráfego (função principal de cloaking)
     */
    private function checkTraffic() {
        // Dados do tracker
        $visitorIP = $this->sanitize($this->requestData['ip'] ?? $this->clientIP);
        $visitorUA = $this->sanitize($this->requestData['ua'] ?? $this->userAgent);
        $campaignSlug = $this->sanitize($this->requestData['campaign'] ?? '');
        $utmSource = $this->sanitize($this->requestData['utm_source'] ?? '');
        $utmMedium = $this->sanitize($this->requestData['utm_medium'] ?? '');
        $utmCampaign = $this->sanitize($this->requestData['utm_campaign'] ?? '');
        $utmContent = $this->sanitize($this->requestData['utm_content'] ?? '');
        $utmTerm = $this->sanitize($this->requestData['utm_term'] ?? '');
        $referer = $this->sanitize($this->requestData['referer'] ?? '');
        $timestamp = time();
        
        // Busca campanha
        $campaign = null;
        $campaignId = null;
        if (!empty($campaignSlug)) {
            $campaign = getCampaignBySlug($campaignSlug);
            $campaignId = $campaign['id'] ?? $campaignSlug;
        }
        
        // =====================================================
        // VERIFICACAO DE ASSINATURA DO USUARIO
        // Se vencida ou bloqueada, cloaker para de funcionar
        // =====================================================
        if ($campaign && !empty($campaign['user_id'])) {
            $usuariosFile = dirname(__DIR__) . '/usuarios.json';
            if (file_exists($usuariosFile)) {
                $usuarios = json_decode(file_get_contents($usuariosFile), true) ?? [];
                $ownerId = $campaign['user_id'];
                $ownerData = null;
                
                // Busca usuario pelo ID/email
                foreach ($usuarios as $emailKey => $userData) {
                    if ($emailKey === $ownerId || ($userData['id'] ?? '') === $ownerId) {
                        $ownerData = $userData;
                        break;
                    }
                }
                
                if ($ownerData) {
                    // Verifica se esta bloqueado
                    if (!empty($ownerData['bloqueado']) && $ownerData['bloqueado'] === true) {
                        $this->respond([
                            'action' => 'white',
                            'reason' => 'subscription_blocked',
                            'message' => 'Usuario bloqueado'
                        ]);
                        return;
                    }
                    
                    // Verifica data de expiracao
                    if (!empty($ownerData['expira_em'])) {
                        $expiraEm = strtotime($ownerData['expira_em']);
                        if ($expiraEm !== false && $expiraEm < time()) {
                            $this->respond([
                                'action' => 'white',
                                'reason' => 'subscription_expired',
                                'message' => 'Assinatura vencida em ' . $ownerData['expira_em']
                            ]);
                            return;
                        }
                    }
                }
            }
        }
        
        // Detecta plataforma
        $platform = detectPlatform($utmSource);
        
        // Verifica se o IP está na whitelist (Admin/Testes) - vai direto pra black
        $isWhitelisted = false;
        $whitelistFile = DATA_DIR . 'whitelist_ips.json';
        if (file_exists($whitelistFile)) {
            $whitelistData = json_decode(file_get_contents($whitelistFile), true);
            if (is_array($whitelistData)) {
                // Match exato
                if (isset($whitelistData[$visitorIP])) {
                    $isWhitelisted = true;
                } else {
                    // Para IPv6, faz match dos primeiros 4 segmentos (prefixo da rede)
                    // Isso cobre casos onde o IP muda parcialmente (comum em IPv6)
                    if (strpos($visitorIP, ':') !== false) {
                        $visitorPrefix = implode(':', array_slice(explode(':', $visitorIP), 0, 4));
                        foreach (array_keys($whitelistData) as $whitelistedIP) {
                            if (strpos($whitelistedIP, ':') !== false) {
                                $whitePrefix = implode(':', array_slice(explode(':', $whitelistedIP), 0, 4));
                                if ($visitorPrefix === $whitePrefix) {
                                    $isWhitelisted = true;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // =====================================================
        // WARM-UP: Aquecimento da Campanha
        // =====================================================
        // Se a campanha tem cliques de aquecimento configurados
        // e ainda nao atingiu o limite, todos vao para WHITE
        // =====================================================
        $warmupClicks = (int)($campaign['warmup_clicks'] ?? 0);
        if ($warmupClicks > 0 && !$isWhitelisted) {
            $campStats = getCampaignStats($campaignId);
            $totalClicks = (int)($campStats['clicks'] ?? 0);
            if ($totalClicks < $warmupClicks) {
                // Ainda em aquecimento - manda todos para WHITE
                $remaining = $warmupClicks - $totalClicks;
                $this->logBotAccess($visitorIP, $visitorUA, "Aquecimento: {$totalClicks}/{$warmupClicks} cliques (faltam {$remaining})", $campaignId, $utmSource, $platform);
                
                // IMPORTANTE: Registra o clique para o contador de aquecimento funcionar!
                recordHourlyClick(false);
                recordClick($campaignId, false, $platform); // <-- CORRECAO: Conta clique na campanha
                
                $response = [
                    'action' => 'white',
                    'url' => $campaign['white_url'],
                    'method' => $campaign['white_method'] ?? 'redirect',
                    'warmup' => true,
                    'warmup_progress' => $totalClicks + 1, // +1 porque acabou de registrar
                    'warmup_total' => $warmupClicks
                ];
                jsonResponse($response);
            }
        }

        // =====================================================
        // FILTRO DE PAISES
        // =====================================================
        $allowedCountries = array_filter(explode(',', $campaign['allowed_countries'] ?? ''));
        if (!empty($allowedCountries) && !$isWhitelisted) {
            $visitorCountry = getIPCountry($visitorIP) ?? 'XX';
            if (!in_array(strtoupper($visitorCountry), array_map('strtoupper', $allowedCountries))) {
                $reason = "Pais bloqueado: {$visitorCountry} (permitidos: " . implode(',', $allowedCountries) . ")";
                
                // Registra no log para aparecer no painel
                logBot([
                    'ip' => $visitorIP,
                    'user_agent' => substr($visitorUA, 0, 300),
                    'reason' => $reason,
                    'campaign' => $campaignSlug,
                    'campaign_id' => $campaignId,
                    'platform' => $platform,
                    'referer' => ''
                ]);
                
                recordHourlyClick(true);
                jsonResponse([
                    'action' => 'white',
                    'url' => $campaign['white_url'],
                    'method' => $campaign['white_method'] ?? 'redirect',
                    'reason' => 'country_blocked',
                    'country' => $visitorCountry
                ]);
            }
        }

        // =====================================================
        // CLOAKING PROFISSIONAL V11 - NOTA 10
        // =====================================================
        // Sistema multi-camada para Google, TikTok e Facebook
        // =====================================================
        
        $isBot = false;
        $blockReason = '';
        $riskScore = 0;
        $riskFactors = [];
        
        // Verifica se veio de plataforma de ads (tem UTMs ou click IDs)
        $hasAdsParams = !empty($utmSource) || !empty($utmMedium) || 
                        !empty($this->requestData['ttclid']) || 
                        !empty($this->requestData['fbclid']) ||
                        !empty($this->requestData['gclid']);
        
        // Dados do cliente para analise
        $clientData = [
            'webdriver' => $this->requestData['webdriver'] ?? false,
            'plugins_count' => (int)($this->requestData['plugins_count'] ?? -1),
            'languages' => $this->requestData['languages'] ?? '',
            'screen_width' => (int)($this->requestData['screen_width'] ?? 0),
            'screen_height' => (int)($this->requestData['screen_height'] ?? 0),
            'timezone' => $this->requestData['timezone'] ?? '',
            'platform' => $this->requestData['platform'] ?? '',
            'touch_support' => $this->requestData['touch_support'] ?? false,
            'advanced_bot_score' => (int)($this->requestData['advanced_bot_score'] ?? 50),
            'advanced_bot_flags' => explode('|', $this->requestData['advanced_bot_flags'] ?? '')
        ];
        
        // ========== CAMADA 0: WHITELIST (PRIORIDADE MAXIMA) ==========
        if ($isWhitelisted) {
            $isBot = false;
            $blockReason = 'Whitelist - Admin/Teste';
        }
        
        // ========== CAMADA 0.5: DESKTOP = SEMPRE WHITE ==========
        // Revisores do Facebook/Google/TikTok usam desktop
        // Usuarios reais de anuncios em redes sociais usam celular
        // Isso reduz drasticamente os falsos positivos
        elseif (!$this->isMobileDevice($visitorUA)) {
            $isBot = true;
            $blockReason = 'Desktop detectado - enviado para WHITE (apenas mobile passa)';
            $riskScore = 100;
            $this->logBotAccess($visitorIP, $visitorUA, $blockReason, $campaignId, $utmSource, $platform);
        }
        
        // ========== CAMADA 1: BOT APRENDIDO (lista global) ==========
        // Verifica se já foi detectado como bot anteriormente
        elseif (function_exists('isLearnedBot')) {
            $learned = isLearnedBot($visitorIP, $visitorUA);
            if ($learned['isBot']) {
                $isBot = true;
                $blockReason = $learned['reason'];
                $riskScore = 100;
            }
        }
        
        // ========== CAMADA 2: USER-AGENT DE BOT CONHECIDO ==========
        if (!$isBot && !$isWhitelisted && isBotByUserAgent($visitorUA)) {
            $isBot = true;
            $blockReason = 'User-Agent de bot/crawler';
            $riskScore = 100;
            $this->logBotAccess($visitorIP, $visitorUA, $blockReason, $campaignId, $utmSource, $platform);
        }
        
        // ========== CAMADA 3: IP NA BLACKLIST ==========
        elseif (!$isBot && !$isWhitelisted && isIPBlocked($visitorIP)) {
            $isBot = true;
            $blockReason = 'IP bloqueado manualmente';
            $riskScore = 100;
        }
        
        // ========== CAMADA 4: TLS/HTTP FINGERPRINT ==========
        // SIMPLIFICADO: Apenas detecta bots CONHECIDOS (cURL, Python, Selenium)
        // NAO penaliza mais por headers faltantes ou diferentes
        $tlsAnalysis = ['score' => 100, 'flags' => [], 'details' => []]; // Default = OK
        if (!$isBot && !$isWhitelisted) {
            $tlsAnalysis = TLSFingerprint::analyze();
            
            // APENAS penaliza se detectou bot CONHECIDO (cURL, Python, etc)
            if (!empty($tlsAnalysis['details']['bot_match'])) {
                $isBot = true;
                $blockReason = 'Bot conhecido detectado: ' . $tlsAnalysis['details']['bot_match'];
                $riskScore = 100;
            }
            
            // Registra flags apenas para log (sem penalidade)
            foreach ($tlsAnalysis['flags'] as $flag) {
                $riskFactors[] = 'tls_' . $flag;
            }
        }
        
        // ========== CAMADA 5: ANALISE SIMPLES (apenas bots REAIS) ==========
        // SIMPLIFICADO: Apenas detecta bots CONFIRMADOS, sem penalidades por headers
        if (!$isBot && !$isWhitelisted && $hasAdsParams) {
            
            // 5.1 - WebDriver detectado = BOT CONFIRMADO
            if (!empty($clientData['webdriver']) && $clientData['webdriver'] !== 'false' && $clientData['webdriver'] !== '0') {
                $isBot = true;
                $blockReason = 'WebDriver detectado (Selenium/Puppeteer)';
                $riskScore = 100;
            }
            
            // 5.2 - IP de datacenter = BOT CONFIRMADO
            elseif (function_exists('isDatacenterIP') && isDatacenterIP($visitorIP)) {
                $isBot = true;
                $blockReason = 'IP de datacenter';
                $riskScore = 100;
            }
            
            // 5.3 - User-Agent mobile mas sem touch = SUSPEITO (mas nao bloqueia)
            $isMobileUA = preg_match('/Mobile|Android|iPhone|iPad/i', $visitorUA);
            if (!$isBot && $isMobileUA && empty($clientData['touch_support'])) {
                // Apenas registra, nao bloqueia
                $riskFactors[] = 'UA mobile sem touch (monitorando)';
            }
            
            // Se nao foi detectado como bot, PASSA
            if (!$isBot) {
                $blockReason = '[PASSOU] Score: ' . $riskScore;
            }
        }
        
        // ========== CAMADA 5: ACESSO DIRETO (SEM UTMs) ==========
        else {
            // SEM UTMs = SEMPRE vai para WHITE (pode ser revisor)
            // Apenas trafego com UTMs vai para BLACK
            $isBot = true;
            $blockReason = 'Acesso sem UTMs - enviado para WHITE';
            $riskScore = 100;
        }
        
        // ========== REGISTRO E METRICAS ==========
        $advancedData = [
            'risk_score' => $riskScore,
            'risk_factors' => $riskFactors,
            'client_data' => $clientData,
            'has_ads_params' => $hasAdsParams
        ];
        
        // Registra clique por hora
        recordHourlyClick($isBot);
        
        // Registra estatísticas
        $campaignId = $campaign['id'] ?? 'unknown';
        recordClick($campaignId, $isBot, $platform);
        
        // Log SEMPRE para debug (mesmo passes)
        logBot([
            'ip' => $visitorIP,
            'user_agent' => substr($visitorUA, 0, 500),
            'reason' => $isBot ? $blockReason : '[PASSOU] Score: ' . $riskScore,
            'campaign' => $campaignSlug,
            'campaign_id' => $campaignId,
            'platform' => $platform,
            'referer' => substr($referer, 0, 200)
        ]);
        
        // Log de bot se bloqueado
        if ($isBot) {
            
            // Adiciona IP à blacklist automaticamente se for padrão conhecido de bot
            if (in_array($blockReason, ['User-Agent de bot', 'Headless browser'])) {
                blockIP($visitorIP, $blockReason);
            }
        }
        
// Determina URLs de destino
        $whiteUrl = $campaign['white_url'] ?? '';
        $blackUrl = $campaign['black_url'] ?? '';
        
        // V10.4 - Rotacao de URLs (A/B Testing)
        if (!$isBot && URL_ROTATION_ENABLED && !empty($campaign['black_urls'])) {
            $blackUrls = $campaign['black_urls'];
            if (is_array($blackUrls) && count($blackUrls) > 1) {
                $selectedUrl = selectRotatedUrl($campaign['id'], $blackUrls);
                $urlIndex = array_search($selectedUrl, $blackUrls);
                $blackUrl = $selectedUrl;
                recordRotatedUrlClick($campaign['id'], $urlIndex);
            }
        }
        
        // V10.4 - Verifica pausa automatica
        if (!$isBot) {
            checkAutoPause($campaign['id'] ?? 'unknown');
        }
        
        // V10.4 - Verifica alerta de taxa de bloqueio
        checkAndAlertBlockRate();
        
        // Monta resposta
        // isBot = true  → visitante é bot/revisor → manda para WHITE (página segura)
        // isBot = false → visitante é humano real  → manda para BLACK (oferta real)
        $response = [
            'status' => 'success',
            'action' => $isBot ? 'white' : 'black',
            'blocked' => $isBot,
            'timestamp' => $timestamp,
            'request_id' => bin2hex(random_bytes(8))
        ];
        
        // URLs da campanha
        $whiteUrl = $campaign['white_url'] ?? '';
        $blackUrl = $campaign['black_url'] ?? '';
        
        // Adiciona URLs de destino - OBRIGATÓRIO ter a URL na resposta
        if ($isBot) {
            // Bot → white page (página segura/homepagem)
            $whiteMethod = $campaign['white_method'] ?? 'redirect';
            $response['redirect'] = $whiteUrl;
            $response['url'] = $whiteUrl;
            $response['method'] = $whiteMethod;
            
            // Se for proxy, fornece URL do proxy centralizado do COMMANDER
            if ($whiteMethod === 'proxy' && !empty($whiteUrl)) {
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $commanderHost = $protocol . '://' . $_SERVER['HTTP_HOST'];
                $commanderPath = dirname($_SERVER['SCRIPT_NAME']);
                $proxyUrl = $commanderHost . $commanderPath . '/api.php?action=proxy&url=' . urlencode($whiteUrl);
                $response['proxy_url'] = $proxyUrl;
            }
            
            // SEMPRE loga quando vai para white (para debug)
            $this->logBotAccess($visitorIP, $visitorUA, $blockReason, $campaignId, $utmSource, $platform);
        } else {
            // Humano → black page (oferta real) com UTMs preservados
            $finalBlackUrl = $blackUrl ?? '';
            if (!empty($finalBlackUrl) && !empty($utmSource)) {
                $finalBlackUrl = $this->appendUtmsToUrl($finalBlackUrl, [
                    'utm_source' => $utmSource,
                    'utm_medium' => $utmMedium,
                    'utm_campaign' => $utmCampaign,
                    'utm_content' => $utmContent,
                    'utm_term' => $utmTerm
                ]);
            }
            
            $blackMethod = $campaign['black_method'] ?? 'redirect';
            $response['redirect'] = $finalBlackUrl;
            $response['url'] = $finalBlackUrl;
            $response['method'] = $blackMethod;
            
            // Se for proxy, fornece URL do proxy centralizado do COMMANDER
            if ($blackMethod === 'proxy') {
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $commanderHost = $protocol . '://' . $_SERVER['HTTP_HOST'];
                $commanderPath = dirname($_SERVER['SCRIPT_NAME']);
                $proxyUrl = $commanderHost . $commanderPath . '/api.php?action=proxy&url=' . urlencode($finalBlackUrl);
                $response['proxy_url'] = $proxyUrl;
            }
            
            // Se for iframe (stealth), fornece URL com white e black
            // Bot ve HTML da white, humano ve black via Shadow DOM
            if ($blackMethod === 'iframe') {
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $commanderHost = $protocol . '://' . $_SERVER['HTTP_HOST'];
                $commanderPath = dirname($_SERVER['SCRIPT_NAME']);
                $iframeUrl = $commanderHost . $commanderPath . '/api.php?action=iframe'
                           . '&white=' . urlencode($whiteUrl)
                           . '&black=' . urlencode($finalBlackUrl);
                $response['iframe_url'] = $iframeUrl;
            }
        }
        
        // Adiciona informações extras em modo debug
        if (DEBUG_MODE) {
            $response['debug'] = [
                'visitor_ip' => $visitorIP,
                'is_bot' => $isBot,
                'reason' => $blockReason,
                'platform' => $platform,
                'campaign' => $campaignSlug
            ];
        }
        
        $this->respond($response);
    }
    
    /**
     * Registra conversão
     */
    private function registerConversion() {
        $campaignSlug = $this->sanitize($this->requestData['campaign'] ?? '');
        $value = (float) ($this->requestData['value'] ?? 0);
        $type = $this->sanitize($this->requestData['type'] ?? 'sale');
        $transactionId = $this->sanitize($this->requestData['transaction_id'] ?? '');
        
        if (empty($campaignSlug)) {
            $this->respond(['error' => 'Campaign required'], 400);
        }
        
        $campaign = getCampaignBySlug($campaignSlug);
        if (!$campaign) {
            $this->respond(['error' => 'Campaign not found'], 404);
        }
        
        recordConversion($campaign['id'], $value, $type);
        
        $this->respond([
            'status' => 'success',
            'message' => 'Conversion registered',
            'campaign' => $campaignSlug,
            'value' => $value,
            'type' => $type
        ]);
    }
    
    /**
     * Endpoint para pixel de tracking
     */
    private function handlePixel() {
        // Retorna imagem 1x1 transparente
        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        
        // GIF 1x1 transparente
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }
    
/**
     * Health check
     */
    private function healthCheck() {
        $this->respond([
            'status' => 'ok',
            'version' => COMMANDER_VERSION,
            'timestamp' => time()
        ]);
    }
    
    // ============================================
    // V10.4 - NOVOS METODOS
    // ============================================
    
    /**
     * Gera JS Challenge token
     */
    private function getJSChallenge() {
        $challenge = $this->security->generateJSChallengeToken();
        $this->respond([
            'status' => 'success',
            'challenge' => $challenge
        ]);
    }
    
    /**
     * Retorna dados para graficos
     */
    private function getChartData() {
        $type = $this->sanitize($this->requestData['type'] ?? 'all');
        
        $data = [];
        
        switch ($type) {
            case 'daily':
                $data = getChartDataByDay(CHARTS_DAYS_HISTORY);
                break;
            case 'hourly':
                $data = getChartDataByHour();
                break;
            case 'bot_vs_human':
                $data = getChartDataBotVsHuman();
                break;
            case 'platform':
                $data = getChartDataByPlatform();
                break;
            case 'heatmap':
                $data = getHeatmapData();
                break;
            case 'all':
            default:
                $data = [
                    'daily' => getChartDataByDay(CHARTS_DAYS_HISTORY),
                    'hourly' => getChartDataByHour(),
                    'bot_vs_human' => getChartDataBotVsHuman(),
                    'platform' => getChartDataByPlatform(),
                    'heatmap' => getHeatmapData()
                ];
        }
        
        $this->respond([
            'status' => 'success',
            'data' => $data
        ]);
    }
    
    /**
     * Retorna resumo do dashboard
     */
    private function getDashboardData() {
        $summary = getDashboardSummary();
        $this->respond([
            'status' => 'success',
            'data' => $summary
        ]);
    }
    
    /**
     * Testa webhook
     */
    private function testWebhook() {
        $type = $this->sanitize($this->requestData['type'] ?? 'telegram');
        $message = "Teste de webhook COMMANDER V10.4\nData: " . date('d/m/Y H:i:s');
        
        $result = false;
        
        if ($type === 'telegram') {
            $result = sendTelegramNotification($message);
        } elseif ($type === 'discord') {
            $result = sendDiscordNotification($message, 'Teste de Webhook');
        }
        
        $this->respond([
            'status' => $result ? 'success' : 'error',
            'message' => $result ? 'Webhook enviado com sucesso' : 'Falha ao enviar webhook'
        ]);
    }
    
    /**
     * V10.5 - Atualiza dados comportamentais do visitante
     */
    private function updateBehaviorData() {
        $visitorId = $this->sanitize($this->requestData['visitor_id'] ?? '');
        $campaignId = $this->sanitize($this->requestData['campaign'] ?? 'unknown');
        
        if (empty($visitorId)) {
            $this->respond(['error' => 'visitor_id required'], 400);
            return;
        }
        
        $behaviorData = [
            'scrolls' => (int)($this->requestData['scrolls'] ?? 0),
            'mouse_moves' => (int)($this->requestData['mouse_moves'] ?? 0),
            'time_on_page' => (int)($this->requestData['time_on_page'] ?? 0),
            'fingerprint' => $this->sanitize($this->requestData['fingerprint'] ?? ''),
            'has_mouse' => (bool)($this->requestData['has_mouse'] ?? false),
            'has_touch' => (bool)($this->requestData['has_touch'] ?? false),
            'webdriver' => (bool)($this->requestData['webdriver'] ?? false),
            'timezone' => $this->sanitize($this->requestData['timezone'] ?? ''),
            'plugins' => (int)($this->requestData['plugins'] ?? 0),
            'canvas_hash' => $this->sanitize($this->requestData['canvas_hash'] ?? '')
        ];
        
        // Atualiza dados comportamentais na funcao V10.5
        updateBehavioralData($visitorId, $campaignId, $behaviorData);
        
        // Coleta fingerprint de revisores suspeitos
        if (COLLECT_REVIEWER_FP && !empty($behaviorData['fingerprint'])) {
            collectReviewerFingerprint($behaviorData['fingerprint'], $behaviorData);
        }
        
        $this->respond([
            'status' => 'ok',
            'visitor_id' => $visitorId,
            'gate_status' => checkBehavioralGate($visitorId, $campaignId)
        ]);
    }
    
    /**
     * Verifica referer suspeito
     */
    private function isSuspiciousReferer($referer) {
        if (empty($referer)) {
            return false;
        }
        
        $suspicious = [
            'semrush.com', 'ahrefs.com', 'moz.com', 'majestic.com',
            'similarweb.com', 'spyfu.com', 'searchmetrics.com'
        ];
        
        $refererLower = strtolower($referer);
        foreach ($suspicious as $pattern) {
            if (strpos($refererLower, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verifica se é headless browser
     */
    private function isHeadlessBrowser($userAgent) {
        $headlessIndicators = [
            'headless', 'phantomjs', 'nightmare', 'selenium',
            'webdriver', 'puppeteer', 'playwright', 'cypress'
        ];
        
        $uaLower = strtolower($userAgent);
        foreach ($headlessIndicators as $indicator) {
            if (strpos($uaLower, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verifica se IP é de datacenter conhecido
     */
    private function isDatacenterIP($ip) {
        // Ranges de datacenters conhecidos (simplificado)
        $datacenterRanges = [
            '34.0.0.0/8',      // Google Cloud
            '35.0.0.0/8',      // Google Cloud
            '52.0.0.0/8',      // AWS
            '54.0.0.0/8',      // AWS
            '13.0.0.0/8',      // AWS/Azure
            '20.0.0.0/8',      // Azure
            '40.0.0.0/8',      // Azure
            '104.0.0.0/8',     // Azure/Cloudflare
            '157.0.0.0/8',     // Azure
            '199.0.0.0/8',     // DigitalOcean
            '159.65.0.0/16',   // DigitalOcean
            '167.99.0.0/16',   // DigitalOcean
            '206.189.0.0/16',  // DigitalOcean
        ];
        
        foreach ($datacenterRanges as $range) {
            if (ipInRange($ip, $range)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Loga acesso de bot e adiciona à lista de aprendidos
     */
    private function logBotAccess($ip, $userAgent, $reason, $campaignId = '', $utmSource = '', $platform = '') {
        // Registra no log de bots
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $ip,
            'ua' => substr($userAgent, 0, 200),
            'reason' => $reason,
            'campaign_id' => $campaignId,
            'utm_source' => $utmSource,
            'platform' => $platform
        ];
        
        appendToJsonFile(DATA_DIR . 'bot_logs.json', $logEntry, 5000);
        
        // Adiciona à lista de bots aprendidos (global)
        // Só adiciona se foi um bloqueio real de bot (não aquecimento, não país)
        if (strpos($reason, 'Aquecimento') === false && 
            strpos($reason, 'Pais bloqueado') === false &&
            strpos($reason, 'sem UTMs') === false) {
            addLearnedBot($ip, $userAgent, $reason, $campaignId);
        }
        
        return true;
    }

    /**
     * Adiciona UTMs à URL
     */
    private function appendUtmsToUrl($url, $utms) {
        $utms = array_filter($utms);
        
        if (empty($utms)) {
            return $url;
        }
        
        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        return $url . $separator . http_build_query($utms);
    }
    
    /**
     * Obtém dados da requisição
     */
    private function getRequestData() {
        $data = [];
        
        // GET params
        $data = array_merge($data, $_GET);
        
        // POST params
        $data = array_merge($data, $_POST);
        
        // JSON body
        $rawBody = file_get_contents('php://input');
        if (!empty($rawBody)) {
            $jsonData = json_decode($rawBody, true);
            if (is_array($jsonData)) {
                $data = array_merge($data, $jsonData);
            }
        }
        
        return $data;
    }
    
    /**
     * Sanitiza entrada
     */
    private function sanitize($value, $type = 'string') {
        if ($value === null) {
            return '';
        }
        
        // Remove null bytes
        $value = str_replace("\0", '', $value);
        
        switch ($type) {
            case 'ip':
                // Valida e sanitiza IP (v4 ou v6)
                $value = trim($value);
                if (filter_var($value, FILTER_VALIDATE_IP)) {
                    return $value;
                }
                // Tenta extrair IP de formatos como "ip, proxy_ip"
                if (strpos($value, ',') !== false) {
                    $parts = explode(',', $value);
                    $value = trim($parts[0]);
                    if (filter_var($value, FILTER_VALIDATE_IP)) {
                        return $value;
                    }
                }
                return '';
                
            case 'slug':
                // Apenas alfanumericos, hifen e underscore
                return preg_replace('/[^a-zA-Z0-9_-]/', '', $value);
                
            case 'url':
                // Valida URL
                $value = filter_var($value, FILTER_SANITIZE_URL);
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    return $value;
                }
                return '';
                
            case 'int':
                return (int) $value;
                
            case 'float':
                return (float) $value;
                
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                
            case 'email':
                return filter_var($value, FILTER_SANITIZE_EMAIL);
                
            case 'string':
            default:
                // Remove tags HTML e limita tamanho
                $value = strip_tags($value);
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                // Limita a 1000 caracteres por padrao
                return mb_substr($value, 0, 1000);
        }
    }
    
    /**
     * Valida dados de entrada com regras especificas
     */
    private function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // Campo obrigatorio
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = 'Campo obrigatorio';
                continue;
            }
            
            // Tamanho minimo
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[$field] = 'Minimo ' . $rule['min_length'] . ' caracteres';
            }
            
            // Tamanho maximo
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[$field] = 'Maximo ' . $rule['max_length'] . ' caracteres';
            }
            
            // Regex pattern
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $errors[$field] = $rule['pattern_message'] ?? 'Formato invalido';
            }
        }
        
        return $errors;
    }
    
    /**
     * Detecta se o dispositivo e mobile (celular/tablet)
     * IMPORTANTE: Apenas dispositivos mobile passam para BLACK
     * Desktop SEMPRE vai para WHITE (revisores usam desktop)
     */
    private function isMobileDevice($userAgent) {
        // Se MOBILE_ONLY_MODE estiver desabilitado, permite tudo
        if (defined('MOBILE_ONLY_MODE') && !MOBILE_ONLY_MODE) {
            return true; // Permite passar como se fosse mobile
        }
        
        if (empty($userAgent)) {
            return false; // Sem UA = suspeito, trata como desktop
        }
        
        // Verifica se e tablet
        $isTablet = (bool) preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $userAgent);
        
        // Se for tablet e ALLOW_TABLETS estiver desabilitado, trata como desktop
        if ($isTablet && defined('ALLOW_TABLETS') && !ALLOW_TABLETS) {
            return false;
        }
        
        // Padroes de dispositivos mobile (celulares)
        $mobilePatterns = [
            // Sistemas operacionais mobile
            '/Android.*Mobile/i',     // Android phone (nao tablet)
            '/iPhone/i',
            '/iPod/i',
            '/webOS/i',
            '/BlackBerry/i',
            '/IEMobile/i',
            '/Opera Mini/i',
            '/Opera Mobi/i',
            '/Windows Phone/i',
            '/Windows CE/i',
            '/Symbian/i',
            '/PalmOS/i',
            
            // Navegadores mobile especificos
            '/Mobile Safari/i',
            '/Chrome.*Mobile/i',
            '/Firefox.*Mobile/i',
            '/Samsung.*Browser.*Mobile/i',
            '/UCBrowser.*Mobile/i',
            '/MiuiBrowser/i',
            '/HuaweiBrowser/i',
            '/OppoBrowser/i',
            '/VivoBrowser/i',
            
            // Generico - deve ter "Mobile" explicito
            '/\bMobile\b/i'
        ];
        
        // Padroes de tablets (se permitido)
        $tabletPatterns = [
            '/iPad/i',
            '/Android(?!.*Mobile)/i',  // Android sem "Mobile" = tablet
            '/Tablet/i',
            '/PlayBook/i',
            '/Kindle/i',
            '/Silk/i'
        ];
        
        // Padroes que indicam desktop (prioridade alta)
        $desktopPatterns = [
            '/Windows NT/i',           // Windows desktop
            '/Macintosh/i',            // Mac
            '/X11.*Linux(?!.*Android)/i', // Linux desktop
            '/CrOS/i',                 // Chrome OS
        ];
        
        // Primeiro verifica se e claramente um desktop
        foreach ($desktopPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                // Excecoes: Android e Windows Phone sao mobile
                if (preg_match('/Android/i', $userAgent)) {
                    continue; // Pula, pode ser mobile
                }
                if (preg_match('/Windows Phone/i', $userAgent)) {
                    return true; // Windows Phone e mobile
                }
                return false; // E desktop
            }
        }
        
        // Verifica se e tablet (se permitido)
        if (defined('ALLOW_TABLETS') && ALLOW_TABLETS) {
            foreach ($tabletPatterns as $pattern) {
                if (preg_match($pattern, $userAgent)) {
                    return true; // Tablet permitido
                }
            }
        }
        
        // Verifica se e celular
        foreach ($mobilePatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true; // E mobile
            }
        }
        
        // Fallback: verifica screen width se disponivel
        $screenWidth = (int)($this->requestData['screen_width'] ?? 0);
        $maxMobileWidth = defined('MAX_MOBILE_SCREEN_WIDTH') ? MAX_MOBILE_SCREEN_WIDTH : 1024;
        
        if ($screenWidth > 0 && $screenWidth <= $maxMobileWidth) {
            return true; // Tela pequena = provavelmente mobile
        }
        
        // Se chegou aqui, assume desktop (mais seguro)
        return false;
    }
    
    // ==========================================
    // METODOS V11 - MACHINE LEARNING
    // ==========================================
    
    /**
     * Treina ML com exemplo de humano (chamado apos conversao)
     */
    private function mlTrainHuman() {
        $visitorData = $this->collectVisitorDataForML();
        
        $result = MLBotDetector::trainFromConversion($visitorData);
        
        $this->respond([
            'success' => $result,
            'message' => $result ? 'Modelo treinado com exemplo humano' : 'Erro ao treinar modelo'
        ]);
    }
    
    /**
     * Treina ML com exemplo de bot (chamado apos bloqueio confirmado)
     */
    private function mlTrainBot() {
        $visitorData = $this->collectVisitorDataForML();
        
        $result = MLBotDetector::trainFromBlock($visitorData);
        
        $this->respond([
            'success' => $result,
            'message' => $result ? 'Modelo treinado com exemplo bot' : 'Erro ao treinar modelo'
        ]);
    }
    
    /**
     * Retorna estatisticas do modelo ML
     */
    private function mlGetStats() {
        $stats = MLBotDetector::getModelStats();
        
        $this->respond([
            'success' => true,
            'stats' => $stats
        ]);
    }
    
    /**
     * Processa dados de behavioral biometrics
     */
    private function processBiometrics() {
        $biometricsData = $this->requestData['biometrics'] ?? [];
        
        if (empty($biometricsData)) {
            $this->respond(['error' => 'No biometrics data'], 400);
        }
        
        // Extrai metricas do behavioral biometrics
        $humanScore = $biometricsData['humanProbability'] ?? 50;
        $scores = $biometricsData['scores'] ?? [];
        $flags = $biometricsData['flags'] ?? [];
        $summary = $biometricsData['summary'] ?? [];
        
        // Analisa resultados
        $analysis = [
            'human_probability' => $humanScore,
            'is_likely_human' => $humanScore >= 60,
            'is_likely_bot' => $humanScore < 40,
            'confidence' => 'medium'
        ];
        
        // Calcula confianca baseado na quantidade de dados
        $totalInteractions = ($summary['mouseMovements'] ?? 0) + 
                            ($summary['keyPresses'] ?? 0) + 
                            ($summary['touchEvents'] ?? 0);
        
        if ($totalInteractions > 100) {
            $analysis['confidence'] = 'high';
        } elseif ($totalInteractions < 20) {
            $analysis['confidence'] = 'low';
        }
        
        // Flags criticas que indicam bot
        $criticalFlags = array_filter($flags, function($flag) {
            return strpos($flag, 'mouse_velocity_inhuman') !== false ||
                   strpos($flag, 'keyboard_speed_inhuman') !== false ||
                   strpos($flag, 'no_interaction_detected') !== false ||
                   strpos($flag, 'sensor_motion_zero') !== false;
        });
        
        if (!empty($criticalFlags)) {
            $analysis['critical_flags'] = $criticalFlags;
            $analysis['is_likely_bot'] = true;
        }
        
        $this->respond([
            'success' => true,
            'analysis' => $analysis,
            'raw_score' => $humanScore,
            'flags_count' => count($flags)
        ]);
    }
    
    /**
     * Coleta dados do visitante para treinamento ML
     */
    private function collectVisitorDataForML() {
        return [
            'is_mobile' => $this->isMobileDevice($this->userAgent),
            'has_touch' => !empty($this->requestData['touch_support']),
            'screen_width' => (int)($this->requestData['screen_width'] ?? 0),
            'screen_height' => (int)($this->requestData['screen_height'] ?? 0),
            'color_depth' => (int)($this->requestData['color_depth'] ?? 24),
            'pixel_ratio' => (float)($this->requestData['pixel_ratio'] ?? 1),
            'timezone_offset' => (int)($this->requestData['timezone_offset'] ?? 0),
            'plugins_count' => (int)($this->requestData['plugins_count'] ?? 0),
            'languages_count' => count(explode(',', $this->requestData['languages'] ?? '')),
            'behavioral' => [
                'mouse_movements' => (int)($this->requestData['mouse_movements'] ?? 0),
                'mouse_velocity_mean' => (float)($this->requestData['mouse_velocity_mean'] ?? 0),
                'mouse_velocity_std' => (float)($this->requestData['mouse_velocity_std'] ?? 0),
                'key_presses' => (int)($this->requestData['key_presses'] ?? 0),
                'key_interval_mean' => (float)($this->requestData['key_interval_mean'] ?? 0),
                'key_interval_std' => (float)($this->requestData['key_interval_std'] ?? 0),
                'scroll_events' => (int)($this->requestData['scroll_events'] ?? 0),
                'touch_events' => (int)($this->requestData['touch_events'] ?? 0),
            ],
            'sensors' => [
                'motion_count' => (int)($this->requestData['motion_count'] ?? 0),
                'orientation_count' => (int)($this->requestData['orientation_count'] ?? 0),
                'motion_variance' => (float)($this->requestData['motion_variance'] ?? 0),
                'orientation_variance' => (float)($this->requestData['orientation_variance'] ?? 0),
            ],
            'time_on_page' => (int)($this->requestData['time_on_page'] ?? 0),
            'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'accept' => $_SERVER['HTTP_ACCEPT'] ?? '',
            'has_sec_ch_ua' => !empty($_SERVER['HTTP_SEC_CH_UA']),
            'has_sec_fetch' => !empty($_SERVER['HTTP_SEC_FETCH_MODE']),
            'header_count' => count(getallheaders() ?: []),
            'behavioral_score' => (int)($this->requestData['behavioral_score'] ?? 50),
            'tls_score' => (int)($this->requestData['tls_score'] ?? 50),
            'advanced_bot_score' => (int)($this->requestData['advanced_bot_score'] ?? 50)
        ];
    }
    
    /**
     * Envia resposta JSON
     */
    private function respond($data, $statusCode = 200) {
        jsonResponse($data, $statusCode);
    }
}

// Executa API
$api = new CommanderAPI();
$api->handle();
