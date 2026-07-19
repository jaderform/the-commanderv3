<?php
/**
 * COMMANDER - TLS/JA3 Fingerprint Engine v2.0
 * 
 * Sistema de fingerprinting baseado em caracteristicas TLS/HTTP
 * Cada navegador/cliente tem uma "assinatura" unica baseada em:
 * - Ordem e valores dos headers HTTP
 * - Cipher suites suportadas
 * - Extensoes TLS
 * - Versao TLS
 * - Compressao aceita
 * 
 * JA3 = MD5(SSLVersion,Ciphers,Extensions,EllipticCurves,EllipticCurveFormats)
 * JA3S = Fingerprint do servidor (resposta)
 * 
 * Como PHP nao tem acesso direto ao handshake TLS, usamos:
 * 1. HTTP Header fingerprinting (ordem e valores)
 * 2. Accept-* header analysis
 * 3. Deteccao de anomalias de headers
 * 4. Comparacao com database de fingerprints conhecidos
 */

if (!defined('COMMANDER_ACCESS')) {
    http_response_code(403);
    exit('Acesso negado');
}

class TLSFingerprint {
    
    // Database de fingerprints conhecidos de bots
    private static $knownBotFingerprints = [
        // Headless Chrome
        'chrome_headless_1' => [
            'accept_language' => '',
            'accept_encoding' => 'gzip, deflate',
            'connection' => 'keep-alive',
            'has_dnt' => false,
            'has_upgrade_insecure' => false
        ],
        // Puppeteer default
        'puppeteer_default' => [
            'accept' => '*/*',
            'accept_language' => '',
            'has_sec_ch_ua' => false
        ],
        // Selenium default
        'selenium_default' => [
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'has_sec_fetch' => false
        ],
        // Python requests
        'python_requests' => [
            'user_agent_contains' => 'python-requests',
            'accept' => '*/*',
            'accept_encoding' => 'gzip, deflate'
        ],
        // cURL default
        'curl_default' => [
            'user_agent_contains' => 'curl/',
            'accept' => '*/*'
        ],
        // Go http client
        'go_http' => [
            'user_agent_contains' => 'Go-http-client',
            'accept_encoding' => 'gzip'
        ],
        // Java HttpClient
        'java_http' => [
            'user_agent_contains' => 'Java/',
            'accept' => 'text/html, image/gif, image/jpeg, *; q=.2, */*; q=.2'
        ],
        // PHP cURL
        'php_curl' => [
            'user_agent_contains' => 'PHP/',
            'accept' => ''
        ],
        // Scrapy
        'scrapy' => [
            'user_agent_contains' => 'Scrapy',
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
        ]
    ];
    
    // Ordem esperada de headers para navegadores reais
    private static $expectedHeaderOrder = [
        'chrome' => ['Host', 'Connection', 'sec-ch-ua', 'sec-ch-ua-mobile', 'sec-ch-ua-platform', 'Upgrade-Insecure-Requests', 'User-Agent', 'Accept', 'Sec-Fetch-Site', 'Sec-Fetch-Mode', 'Sec-Fetch-User', 'Sec-Fetch-Dest', 'Accept-Encoding', 'Accept-Language'],
        'firefox' => ['Host', 'User-Agent', 'Accept', 'Accept-Language', 'Accept-Encoding', 'Connection', 'Upgrade-Insecure-Requests', 'Sec-Fetch-Dest', 'Sec-Fetch-Mode', 'Sec-Fetch-Site', 'Sec-Fetch-User'],
        'safari' => ['Host', 'Accept', 'Accept-Language', 'Accept-Encoding', 'Connection', 'User-Agent'],
        'edge' => ['Host', 'Connection', 'sec-ch-ua', 'sec-ch-ua-mobile', 'sec-ch-ua-platform', 'Upgrade-Insecure-Requests', 'User-Agent', 'Accept', 'Sec-Fetch-Site', 'Sec-Fetch-Mode', 'Sec-Fetch-User', 'Sec-Fetch-Dest', 'Accept-Encoding', 'Accept-Language']
    ];
    
    // Accept values tipicos de navegadores reais
    private static $validAcceptValues = [
        'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7', // Chrome
        'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8', // Firefox
        'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', // Safari
        'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9' // Edge
    ];
    
    // Accept-Language patterns validos
    private static $validLanguagePatterns = [
        '/^[a-z]{2}(-[A-Z]{2})?(,[a-z]{2}(-[A-Z]{2})?;q=[0-9.]+)*$/', // pt-BR,pt;q=0.9,en;q=0.8
        '/^[a-z]{2}(-[a-z]{2})?(,[a-z]{2}(-[a-z]{2})?;q=[0-9.]+)*$/i' // Case insensitive
    ];
    
  /**
  * Coleta todos os headers HTTP da requisicao
  */
  public static function collectHeaders() {
  $headers = [];
  
  // Metodo 1: getallheaders() se disponivel
  if (function_exists('getallheaders')) {
  $headers = getallheaders() ?: [];
  }
  
  // Metodo 2: Extrai de $_SERVER (sempre faz como fallback)
  foreach ($_SERVER as $key => $value) {
  if (strpos($key, 'HTTP_') === 0) {
  $headerName = str_replace('_', '-', substr($key, 5));
  $headerName = ucwords(strtolower($headerName), '-');
  // So adiciona se ainda nao existir
  if (!isset($headers[$headerName])) {
  $headers[$headerName] = $value;
  }
  }
  }
  
  // Garante que User-Agent sempre esteja presente
  if (empty($headers['User-Agent']) && !empty($_SERVER['HTTP_USER_AGENT'])) {
  $headers['User-Agent'] = $_SERVER['HTTP_USER_AGENT'];
  }
  
  // Garante Accept-Language
  if (empty($headers['Accept-Language']) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
  $headers['Accept-Language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
  }
  
  return $headers;
  }
    
    /**
     * Gera fingerprint baseado nos headers HTTP
     */
    public static function generateFingerprint() {
        $headers = self::collectHeaders();
        
        // Componentes do fingerprint
        $components = [
            // 1. Ordem dos headers (hash)
            'header_order' => md5(implode(',', array_keys($headers))),
            
            // 2. Accept normalizado
            'accept' => md5($headers['Accept'] ?? ''),
            
            // 3. Accept-Language normalizado
            'accept_language' => md5($headers['Accept-Language'] ?? ''),
            
            // 4. Accept-Encoding normalizado
            'accept_encoding' => md5($headers['Accept-Encoding'] ?? ''),
            
            // 5. Presenca de headers de seguranca
            'has_sec_ch_ua' => isset($headers['Sec-Ch-Ua']) ? '1' : '0',
            'has_sec_fetch' => isset($headers['Sec-Fetch-Mode']) ? '1' : '0',
            'has_dnt' => isset($headers['Dnt']) ? '1' : '0',
            'has_upgrade_insecure' => isset($headers['Upgrade-Insecure-Requests']) ? '1' : '0',
            
            // 6. User-Agent hash (primeiros 50 chars para agrupar versoes similares)
            'ua_prefix' => md5(substr($headers['User-Agent'] ?? '', 0, 50)),
            
            // 7. Connection type
            'connection' => strtolower($headers['Connection'] ?? 'unknown')
        ];
        
        // Gera fingerprint final
        $fingerprint = md5(json_encode($components));
        
        return [
            'fingerprint' => $fingerprint,
            'components' => $components,
            'raw_headers' => $headers
        ];
    }
    
    /**
     * Analisa headers e retorna score de confianca (0-100)
     */
    public static function analyze() {
        $headers = self::collectHeaders();
        $result = [
            'score' => 100,
            'flags' => [],
            'details' => [],
            'fingerprint' => null
        ];
        
        // Gera fingerprint
        $fpData = self::generateFingerprint();
        $result['fingerprint'] = $fpData['fingerprint'];
        $result['details']['header_count'] = count($headers);
        
        // ========== TESTE 1: Headers essenciais ==========
        // SIMPLIFICADO: Apenas penaliza se User-Agent estiver REALMENTE vazio
        // Outros headers nao penalizam mais (muita variacao legitima)
        $userAgent = $headers['User-Agent'] ?? '';
        if (empty($userAgent)) {
            $result['score'] -= 15;
            $result['flags'][] = 'empty_user_agent';
        }
        $result['details']['user_agent'] = substr($userAgent, 0, 100);
        
        // ========== TESTE 2: Accept-Language ==========
        // NOTA: NAO penaliza mais - muitos navegadores mobile nao enviam
        $acceptLang = $headers['Accept-Language'] ?? '';
        $result['details']['accept_language'] = $acceptLang;
        
        // ========== TESTE 3: Sec-CH-UA (Client Hints) ==========
        // Navegadores modernos (Chrome 89+, Edge 89+) enviam Client Hints
        $userAgent = $headers['User-Agent'] ?? '';
        $isModernChrome = preg_match('/Chrome\/(\d+)/', $userAgent, $matches) && ($matches[1] ?? 0) >= 89;
        $isModernEdge = preg_match('/Edg\/(\d+)/', $userAgent, $matches) && ($matches[1] ?? 0) >= 89;
        
        // NAO penaliza mais - muitos navegadores nao enviam Client Hints
        $result['details']['has_client_hints'] = isset($headers['Sec-Ch-Ua']);
        $result['details']['has_client_hints'] = isset($headers['Sec-Ch-Ua']);
        
        // ========== TESTE 4: Sec-Fetch-* headers ==========
        // Navegadores modernos enviam Sec-Fetch headers
        $secFetchHeaders = ['Sec-Fetch-Mode', 'Sec-Fetch-Site', 'Sec-Fetch-Dest'];
        $missingSecFetch = 0;
        foreach ($secFetchHeaders as $header) {
            if (!isset($headers[$header])) {
                $missingSecFetch++;
            }
        }
        // NAO penaliza mais - Sec-Fetch headers podem ser removidos por proxies
        $result['details']['missing_sec_fetch'] = $missingSecFetch;
        $result['details']['sec_fetch_count'] = count($secFetchHeaders) - $missingSecFetch;
        
        // ========== TESTE 5: Ordem dos headers ==========
        $headerOrder = array_keys($headers);
        $browser = self::detectBrowserFromUA($userAgent);
        
        if ($browser && isset(self::$expectedHeaderOrder[$browser])) {
            $expectedOrder = self::$expectedHeaderOrder[$browser];
            $orderScore = self::compareHeaderOrder($headerOrder, $expectedOrder);
            $result['details']['header_order_score'] = $orderScore;
            
            // NAO penaliza mais - ordem de headers varia muito
            $result['details']['header_order_score'] = $orderScore;
        }
        
        // ========== TESTE 6: Accept value ==========
        // NOTA: Navegadores mobile e versoes diferentes tem Accept variados
        // Apenas registra Accept para log (sem penalidade)
        $acceptValue = $headers['Accept'] ?? '';
        $result['details']['accept_value'] = substr($acceptValue, 0, 100);
        
        // Apenas registra Accept-Encoding para log (sem penalidade)
        $acceptEncoding = $headers['Accept-Encoding'] ?? '';
        $result['details']['accept_encoding'] = $acceptEncoding;
        
        // ========== UNICO TESTE QUE PENALIZA: Bots conhecidos ==========
        // Este e o teste mais importante - detecta cURL, Python, Selenium, etc
        $botMatch = self::matchKnownBotFingerprint($headers);
        if ($botMatch) {
            $result['score'] -= 50; // Penalidade forte apenas para bots CONFIRMADOS
            $result['flags'][] = 'known_bot: ' . $botMatch;
            $result['details']['bot_match'] = $botMatch;
        }
        
        // Apenas registra headers de proxy para log (sem penalidade)
        $suspiciousHeaders = ['X-Forwarded-For', 'Via', 'Forwarded'];
        $proxyHeaders = [];
        foreach ($suspiciousHeaders as $header) {
            if (isset($headers[$header])) {
                $proxyHeaders[] = $header;
            }
        }
        if (!empty($proxyHeaders)) {
            $result['details']['proxy_headers'] = $proxyHeaders;
        }
        
        // Normaliza score (minimo 50 se nao for bot conhecido)
        if (!$botMatch) {
            $result['score'] = max(50, $result['score']);
        }
        $result['score'] = max(0, min(100, $result['score']));
        
        return $result;
    }
    
    /**
     * Detecta navegador baseado no User-Agent
     */
    private static function detectBrowserFromUA($ua) {
        if (empty($ua)) return null;
        
        if (strpos($ua, 'Edg/') !== false) return 'edge';
        if (strpos($ua, 'Chrome/') !== false) return 'chrome';
        if (strpos($ua, 'Firefox/') !== false) return 'firefox';
        if (strpos($ua, 'Safari/') !== false && strpos($ua, 'Chrome/') === false) return 'safari';
        
        return null;
    }
    
    /**
     * Compara ordem dos headers com ordem esperada
     */
    private static function compareHeaderOrder($actual, $expected) {
        $actualFiltered = array_filter($actual, function($h) use ($expected) {
            return in_array($h, $expected);
        });
        
        if (count($actualFiltered) === 0) return 50; // Sem headers para comparar
        
        // Calcula posicoes relativas
        $matches = 0;
        $total = 0;
        
        $actualIndexed = array_flip(array_values($actualFiltered));
        $expectedIndexed = array_flip($expected);
        
        foreach ($actualFiltered as $header) {
            if (isset($expectedIndexed[$header])) {
                $actualPos = $actualIndexed[$header];
                $expectedPos = $expectedIndexed[$header];
                
                // Headers proximos da posicao esperada ganham pontos
                $diff = abs($actualPos - $expectedPos);
                if ($diff <= 2) $matches++;
                
                $total++;
            }
        }
        
        return $total > 0 ? round(($matches / $total) * 100) : 50;
    }
    
    /**
     * Verifica se headers correspondem a um bot conhecido
     */
    private static function matchKnownBotFingerprint($headers) {
        $ua = $headers['User-Agent'] ?? '';
        $accept = $headers['Accept'] ?? '';
        $acceptLang = $headers['Accept-Language'] ?? '';
        $acceptEnc = $headers['Accept-Encoding'] ?? '';
        
        foreach (self::$knownBotFingerprints as $botName => $signature) {
            $matches = true;
            
            foreach ($signature as $key => $value) {
                switch ($key) {
                    case 'user_agent_contains':
                        if (stripos($ua, $value) === false) $matches = false;
                        break;
                    case 'accept':
                        if ($accept !== $value) $matches = false;
                        break;
                    case 'accept_language':
                        if ($acceptLang !== $value) $matches = false;
                        break;
                    case 'accept_encoding':
                        if ($acceptEnc !== $value) $matches = false;
                        break;
                    case 'has_sec_ch_ua':
                        if ($value !== isset($headers['Sec-Ch-Ua'])) $matches = false;
                        break;
                    case 'has_sec_fetch':
                        if ($value !== isset($headers['Sec-Fetch-Mode'])) $matches = false;
                        break;
                    case 'has_dnt':
                        if ($value !== isset($headers['Dnt'])) $matches = false;
                        break;
                    case 'has_upgrade_insecure':
                        if ($value !== isset($headers['Upgrade-Insecure-Requests'])) $matches = false;
                        break;
                }
                
                if (!$matches) break;
            }
            
            if ($matches) {
                return $botName;
            }
        }
        
        return null;
    }
    
    /**
     * Analisa User-Agent em busca de inconsistencias
     */
    private static function analyzeUserAgent($ua, $headers) {
        $result = ['flags' => [], 'details' => []];
        
        if (empty($ua)) {
            $result['flags'][] = 'empty_user_agent';
            return $result;
        }
        
        // Detecta inconsistencias comuns
        
        // 1. Chrome sem Sec-Ch-Ua (Chrome 89+)
        if (preg_match('/Chrome\/(\d+)/', $ua, $m) && intval($m[1]) >= 89) {
            if (!isset($headers['Sec-Ch-Ua'])) {
                // Pode ser headless Chrome
                $result['flags'][] = 'chrome_missing_sec_ch_ua';
            }
            
            // Verifica consistencia do Sec-Ch-Ua
            if (isset($headers['Sec-Ch-Ua'])) {
                $secChUa = $headers['Sec-Ch-Ua'];
                // Chrome version no Sec-Ch-Ua deve bater com User-Agent
                if (preg_match('/"Chromium";v="(\d+)"/', $secChUa, $m2)) {
                    $secChUaVersion = intval($m2[1]);
                    $uaVersion = intval($m[1]);
                    if (abs($secChUaVersion - $uaVersion) > 5) {
                        $result['flags'][] = 'ua_sec_ch_ua_version_mismatch';
                    }
                }
            }
        }
        
        // 2. UA diz mobile mas sem touch support indicators
        if (preg_match('/Mobile|Android|iPhone|iPad/i', $ua)) {
            // Em mobile, esperamos Sec-Ch-Ua-Mobile = ?1
            if (isset($headers['Sec-Ch-Ua-Mobile']) && $headers['Sec-Ch-Ua-Mobile'] === '?0') {
                $result['flags'][] = 'mobile_ua_but_sec_ch_ua_mobile_false';
            }
        }
        
        // 3. Detecta HeadlessChrome explicito
        if (stripos($ua, 'HeadlessChrome') !== false) {
            $result['flags'][] = 'headless_chrome_in_ua';
        }
        
        // 4. Detecta WebDriver
        if (stripos($ua, 'webdriver') !== false) {
            $result['flags'][] = 'webdriver_in_ua';
        }
        
        // 5. UA muito curto
        if (strlen($ua) < 50) {
            $result['flags'][] = 'user_agent_too_short';
        }
        
        // 6. UA sem versao de navegador
        if (!preg_match('/(Chrome|Firefox|Safari|Edg|Opera)\/[\d.]+/', $ua)) {
            $result['flags'][] = 'no_browser_version_in_ua';
        }
        
        // 7. Platform inconsistency
        if (isset($headers['Sec-Ch-Ua-Platform'])) {
            $platform = trim($headers['Sec-Ch-Ua-Platform'], '"');
            
            if ($platform === 'Windows' && strpos($ua, 'Windows') === false) {
                $result['flags'][] = 'platform_ua_mismatch';
            }
            if ($platform === 'macOS' && strpos($ua, 'Macintosh') === false) {
                $result['flags'][] = 'platform_ua_mismatch';
            }
            if ($platform === 'Linux' && strpos($ua, 'Linux') === false && strpos($ua, 'Android') === false) {
                $result['flags'][] = 'platform_ua_mismatch';
            }
        }
        
        $result['details']['ua_length'] = strlen($ua);
        $result['details']['browser'] = self::detectBrowserFromUA($ua);
        
        return $result;
    }
    
    /**
     * Verifica se o fingerprint e de um bot conhecido
     */
    public static function isKnownBot() {
        $analysis = self::analyze();
        return $analysis['score'] < 40 || !empty($analysis['details']['bot_match']);
    }
    
    /**
     * Retorna fingerprint para armazenamento/comparacao
     */
    public static function getFingerprint() {
        $data = self::generateFingerprint();
        return $data['fingerprint'];
    }
    
    /**
     * Compara fingerprint atual com um armazenado
     */
    public static function compareFingerprint($storedFingerprint) {
        $current = self::getFingerprint();
        return $current === $storedFingerprint;
    }
}
