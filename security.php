<?php
/**
 * COMMANDER V10.3 - Módulo de Segurança
 * 
 * INSTRUÇÕES: Crie este arquivo na raiz do COMMANDER
 */

// Previne acesso direto
if (!defined('COMMANDER_ACCESS')) {
    http_response_code(403);
    exit('Acesso negado');
}

/**
 * Classe de Segurança do COMMANDER
 */
class CommanderSecurity {
    
    private static $instance = null;
    private $rateLimitCache = [];
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inicializa headers de segurança
     */
    public function initSecurityHeaders() {
        // Previne clickjacking
        header('X-Frame-Options: DENY');
        
        // Previne XSS
        header('X-XSS-Protection: 1; mode=block');
        
        // Previne MIME sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self'");
        
        // Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
    
    /**
     * Sanitiza entrada de dados
     */
    public function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return $this->sanitizeInput($item, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'int':
                return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            
            case 'float':
                return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            
            case 'html':
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            
            case 'alphanumeric':
                return preg_replace('/[^a-zA-Z0-9_-]/', '', $input);
            
            case 'string':
            default:
                $input = trim($input);
                $input = stripslashes($input);
                $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
                return $input;
        }
    }
    
    /**
     * Valida token CSRF
     */
    public function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Rate Limiting
     */
    public function checkRateLimit($ip, $maxRequests = null, $window = null) {
        $maxRequests = $maxRequests ?? RATE_LIMIT_REQUESTS;
        $window = $window ?? RATE_LIMIT_WINDOW;
        
        $cacheFile = CACHE_DIR . 'rate_limit_' . md5($ip) . '.json';
        $now = time();
        
        $data = ['requests' => [], 'blocked_until' => 0];
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true) ?: $data;
        }
        
        // Verifica se está bloqueado
        if ($data['blocked_until'] > $now) {
            return false;
        }
        
        // Remove requisições antigas
        $data['requests'] = array_filter($data['requests'], function($time) use ($now, $window) {
            return ($now - $time) < $window;
        });
        
        // Verifica limite
        if (count($data['requests']) >= $maxRequests) {
            $data['blocked_until'] = $now + ($window * 2);
            file_put_contents($cacheFile, json_encode($data));
            return false;
        }
        
        // Adiciona requisição atual
        $data['requests'][] = $now;
        file_put_contents($cacheFile, json_encode($data));
        
        return true;
    }
    
    /**
     * Verifica tentativas de login
     */
    public function checkLoginAttempts($ip) {
        $attempts = $this->getLoginAttempts($ip);
        
        if ($attempts['count'] >= MAX_LOGIN_ATTEMPTS) {
            $timeSinceLastAttempt = time() - $attempts['last_attempt'];
            
            if ($timeSinceLastAttempt < LOGIN_BLOCK_TIME) {
                return [
                    'allowed' => false,
                    'wait_time' => LOGIN_BLOCK_TIME - $timeSinceLastAttempt
                ];
            } else {
                // Reset após tempo de bloqueio
                $this->resetLoginAttempts($ip);
            }
        }
        
        return ['allowed' => true, 'attempts_left' => MAX_LOGIN_ATTEMPTS - $attempts['count']];
    }
    
    public function recordLoginAttempt($ip, $success = false) {
        $data = $this->getAllLoginAttempts();
        
        if ($success) {
            unset($data[$ip]);
        } else {
            if (!isset($data[$ip])) {
                $data[$ip] = ['count' => 0, 'last_attempt' => 0];
            }
            $data[$ip]['count']++;
            $data[$ip]['last_attempt'] = time();
        }
        
        file_put_contents(FILE_LOGIN_ATTEMPTS, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    private function getLoginAttempts($ip) {
        $data = $this->getAllLoginAttempts();
        return $data[$ip] ?? ['count' => 0, 'last_attempt' => 0];
    }
    
    private function getAllLoginAttempts() {
        if (file_exists(FILE_LOGIN_ATTEMPTS)) {
            return json_decode(file_get_contents(FILE_LOGIN_ATTEMPTS), true) ?: [];
        }
        return [];
    }
    
    private function resetLoginAttempts($ip) {
        $data = $this->getAllLoginAttempts();
        unset($data[$ip]);
        file_put_contents(FILE_LOGIN_ATTEMPTS, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    /**
     * Validação de IP
     */
    public function isValidIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    public function isPrivateIP($ip) {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    
    /**
     * Obtém IP real do visitante
     */
    public function getRealIP() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED_FOR',      // Proxies em geral
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if ($this->isValidIP($ip) && !$this->isPrivateIP($ip)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Verifica se requisição é AJAX
     */
    public function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Hash seguro de senha
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Gera token seguro
     */
    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Criptografa dados sensíveis
     */
    public function encrypt($data, $key = null) {
        $key = $key ?? API_TOKEN;
        $key = hash('sha256', $key, true);
        $iv = random_bytes(16);
        
        $encrypted = openssl_encrypt(
            json_encode($data),
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return base64_encode($iv . $encrypted);
    }
    
    public function decrypt($data, $key = null) {
        $key = $key ?? API_TOKEN;
        $key = hash('sha256', $key, true);
        
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return json_decode($decrypted, true);
    }
    
    /**
     * Log de segurança
     */
    public function logSecurityEvent($event, $details = []) {
        $logFile = LOGS_DIR . 'security_' . date('Y-m') . '.log';
        
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $this->getRealIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'details' => $details
        ];
        
        file_put_contents(
            $logFile,
            json_encode($entry) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
    
    /**
     * Verifica honeypot (campo oculto para bots)
     */
    public function checkHoneypot($fieldName = 'website') {
        return empty($_POST[$fieldName]);
    }
    
    /**
     * Limpa cache de rate limiting antigos
     */
    public function cleanupRateLimitCache() {
        $files = glob(CACHE_DIR . 'rate_limit_*.json');
        $now = time();
        
        foreach ($files as $file) {
            if ($now - filemtime($file) > 3600) {
                unlink($file);
            }
        }
    }

    // ============================================
    // V10.4 - DETECCAO AVANCADA DE BOTS
    // ============================================
    
    /**
     * Gera token para JS Challenge
     */
    public function generateJSChallengeToken() {
        $token = bin2hex(random_bytes(16));
        $hash = hash('sha256', $token . date('Y-m-d-H'));
        
        // Salva token temporario
        $cacheFile = CACHE_DIR . 'js_challenge_' . md5($this->getRealIP()) . '.json';
        file_put_contents($cacheFile, json_encode([
            'token' => $token,
            'hash' => $hash,
            'created' => time(),
            'expires' => time() + 300 // 5 minutos
        ]));
        
        return ['token' => $token, 'hash' => $hash];
    }
    
    /**
     * Valida resposta do JS Challenge
     */
    public function validateJSChallenge($response) {
        $cacheFile = CACHE_DIR . 'js_challenge_' . md5($this->getRealIP()) . '.json';
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($cacheFile), true);
        
        if (!$data || $data['expires'] < time()) {
            @unlink($cacheFile);
            return false;
        }
        
        $expectedResponse = hash('sha256', $data['token'] . $data['hash']);
        $isValid = hash_equals($expectedResponse, $response);
        
        if ($isValid) {
            @unlink($cacheFile);
        }
        
        return $isValid;
    }
    
    /**
     * Detecta bot de forma avancada (V10.4)
     */
    public function detectAdvancedBot($advancedData = []) {
        $reasons = [];
        $score = 0;
        
        // 1. JS Challenge (peso 40)
        if (JS_CHALLENGE_ENABLED) {
            $jsChallenge = $advancedData['js_challenge'] ?? null;
            if (empty($jsChallenge)) {
                $reasons[] = 'JS Challenge nao respondido';
                $score += 40;
            } elseif (!$this->validateJSChallenge($jsChallenge)) {
                $reasons[] = 'JS Challenge invalido';
                $score += 40;
            }
        }
        
        // 2. Tempo de carregamento (peso 20)
        $loadTime = $advancedData['load_time'] ?? 0;
        if ($loadTime > 0 && $loadTime < MIN_LOAD_TIME_MS) {
            $reasons[] = 'Tempo de carregamento muito rapido: ' . $loadTime . 'ms';
            $score += 20;
        }
        
        // 3. Deteccao de Mouse/Touch (peso 15)
        if (MOUSE_DETECTION_ENABLED) {
            $hasInteraction = $advancedData['has_mouse'] ?? $advancedData['has_touch'] ?? false;
            if (!$hasInteraction && ($advancedData['time_on_page'] ?? 0) > 2000) {
                $reasons[] = 'Sem interacao de mouse/touch';
                $score += 15;
            }
        }
        
        // 4. WebDriver detectado (peso 50)
        if (!empty($advancedData['webdriver'])) {
            $reasons[] = 'WebDriver detectado';
            $score += 50;
        }
        
        // 5. Inconsistencia de timezone (peso 10)
        $browserTz = $advancedData['timezone'] ?? '';
        $expectedTz = ['America/Sao_Paulo', 'America/Fortaleza', 'America/Recife', 'America/Bahia', 
                       'America/Belem', 'America/Manaus', 'America/Cuiaba', 'America/Porto_Velho',
                       'America/Rio_Branco', 'America/Noronha'];
        if (!empty($browserTz) && !in_array($browserTz, $expectedTz)) {
            $reasons[] = 'Timezone suspeito: ' . $browserTz;
            $score += 10;
        }
        
        // 6. Fingerprint duplicado com comportamento diferente (peso 25)
        if (FINGERPRINT_ENABLED && !empty($advancedData['fingerprint'])) {
            $fpFile = CACHE_DIR . 'fp_' . md5($advancedData['fingerprint']) . '.json';
            if (file_exists($fpFile)) {
                $fpData = json_decode(file_get_contents($fpFile), true);
                // Se mesmo fingerprint mas IPs muito diferentes
                if (isset($fpData['ips']) && count($fpData['ips']) > 5) {
                    $reasons[] = 'Fingerprint usado por muitos IPs';
                    $score += 25;
                }
            }
        }
        
        // 7. Plugins suspeitos (peso 15)
        $plugins = $advancedData['plugins'] ?? [];
        if (is_array($plugins) && count($plugins) === 0) {
            // Navegador sem plugins pode ser headless
            $reasons[] = 'Nenhum plugin detectado';
            $score += 15;
        }
        
        // 8. Canvas fingerprint anomalo (peso 20)
        if (isset($advancedData['canvas_hash']) && $advancedData['canvas_hash'] === 'blocked') {
            $reasons[] = 'Canvas bloqueado (anti-fingerprint)';
            $score += 20;
        }
        
        // Decisao: score >= 40 = bot
        $isBot = $score >= 40;
        
        // Log de deteccao avancada
        if ($isBot || DEBUG_MODE) {
            $this->logAdvancedDetection([
                'ip' => $this->getRealIP(),
                'score' => $score,
                'reasons' => $reasons,
                'data' => $advancedData,
                'result' => $isBot ? 'blocked' : 'passed'
            ]);
        }
        
        return [
            'isBot' => $isBot,
            'score' => $score,
            'reasons' => $reasons
        ];
    }
    
    /**
     * Log de deteccao avancada
     */
    private function logAdvancedDetection($data) {
        $logFile = LOGS_DIR . 'advanced_detection_' . date('Y-m') . '.log';
        $entry = '[' . date('Y-m-d H:i:s') . '] ' . json_encode($data);
        file_put_contents($logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Salva fingerprint para analise
     */
    public function saveFingerprint($fingerprint, $ip) {
        if (!FINGERPRINT_ENABLED || empty($fingerprint)) {
            return;
        }
        
        $fpFile = CACHE_DIR . 'fp_' . md5($fingerprint) . '.json';
        $data = ['ips' => [], 'first_seen' => time(), 'last_seen' => time()];
        
        if (file_exists($fpFile)) {
            $data = json_decode(file_get_contents($fpFile), true) ?: $data;
        }
        
        if (!in_array($ip, $data['ips'])) {
            $data['ips'][] = $ip;
        }
        $data['last_seen'] = time();
        
        // Limita array de IPs
        if (count($data['ips']) > 100) {
            $data['ips'] = array_slice($data['ips'], -100);
        }
        
        file_put_contents($fpFile, json_encode($data));
    }
}

// Instância global
$security = CommanderSecurity::getInstance();


// ============================================
// V10.6 - MELHORIAS DE SEGURANCA ANTI-BOT
// ============================================

/**
 * Classe de Deteccao Avancada de Bots
 * Complementa CommanderSecurity com tecnicas modernas
 */
class AdvancedBotDetector {
    
    private static $instance = null;
    private $scores = [];
    private $config = [
        // Pesos para cada tipo de deteccao
        'weights' => [
            'webdriver' => 50,
            'headless' => 40,
            'automation' => 50,
            'untrusted_events' => 5,  // por evento
            'straight_mouse' => 25,
            'impossible_speed' => 30,
            'honeypot' => 50,
            'devtools' => 20,
            'no_plugins' => 15,
            'canvas_blocked' => 20,
            'timing_anomaly' => 25,
            'reviewer_hours' => 15,
            'ads_referer' => 20,
            'datacenter_ip' => 30,
            'vpn_proxy' => 20,
            'suspicious_headers' => 25
        ],
        // Threshold para considerar bot
        'bot_threshold' => 50,
        // Threshold para revisor
        'reviewer_threshold' => 40
    ];
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Analisa dados comportamentais do JavaScript
     */
    public function analyzeBehavior($data) {
        $score = 0;
        $detections = [];
        
        // Webdriver detectado
        if (!empty($data['webdriver'])) {
            $score += $this->config['weights']['webdriver'];
            $detections[] = 'webdriver';
        }
        
        // Headless browser
        if (!empty($data['headless'])) {
            $score += $this->config['weights']['headless'];
            $detections[] = 'headless';
        }
        
        // Automation frameworks
        if (!empty($data['automation'])) {
            $score += $this->config['weights']['automation'];
            $detections[] = 'automation:' . $data['automation'];
        }
        
        // Eventos nao confiaveis (programaticos)
        $untrustedCount = $data['untrusted_events'] ?? 0;
        if ($untrustedCount > 0) {
            $score += min($untrustedCount * $this->config['weights']['untrusted_events'], 25);
            $detections[] = "untrusted_events:{$untrustedCount}";
        }
        
        // Mouse em linha reta (bot)
        if (!empty($data['straight_mouse'])) {
            $score += $this->config['weights']['straight_mouse'];
            $detections[] = 'straight_mouse';
        }
        
        // Velocidade impossivel entre cliques
        if (!empty($data['impossible_speed'])) {
            $score += $this->config['weights']['impossible_speed'];
            $detections[] = 'impossible_speed';
        }
        
        // Honeypot acionado
        if (!empty($data['honeypot'])) {
            $score += $this->config['weights']['honeypot'];
            $detections[] = 'honeypot';
        }
        
        // DevTools aberto
        if (!empty($data['devtools_open'])) {
            $score += $this->config['weights']['devtools'];
            $detections[] = 'devtools';
        }
        
        // Sem plugins
        if (isset($data['plugins']) && $data['plugins'] === 0) {
            $score += $this->config['weights']['no_plugins'];
            $detections[] = 'no_plugins';
        }
        
        // Canvas bloqueado
        if (!empty($data['canvas_blocked'])) {
            $score += $this->config['weights']['canvas_blocked'];
            $detections[] = 'canvas_blocked';
        }
        
        // Timing muito regular (anomalo)
        if (!empty($data['timing_anomaly'])) {
            $score += $this->config['weights']['timing_anomaly'];
            $detections[] = 'timing_anomaly';
        }
        
        return [
            'score' => $score,
            'detections' => $detections,
            'is_bot' => $score >= $this->config['bot_threshold']
        ];
    }
    
    /**
     * Analisa request HTTP
     */
    public function analyzeRequest() {
        $score = 0;
        $detections = [];
        
        // Horario comercial (revisores)
        $hour = (int)date('G');
        $dayOfWeek = (int)date('N');
        if ($dayOfWeek <= 5 && $hour >= 9 && $hour <= 18) {
            $score += $this->config['weights']['reviewer_hours'];
            $detections[] = 'reviewer_hours';
        }
        
        // Referer de painel de ads
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $adsPatterns = ['business.facebook', 'ads.tiktok', 'ads.google', 'adsmanager', 'business.tiktok'];
        foreach ($adsPatterns as $pattern) {
            if (stripos($ref, $pattern) !== false) {
                $score += $this->config['weights']['ads_referer'];
                $detections[] = 'ads_referer:' . $pattern;
                break;
            }
        }
        
        // Headers suspeitos
        $headerScore = $this->checkHeaders();
        if ($headerScore['score'] > 0) {
            $score += min($headerScore['score'], $this->config['weights']['suspicious_headers']);
            $detections = array_merge($detections, $headerScore['issues']);
        }
        
        return [
            'score' => $score,
            'detections' => $detections
        ];
    }
    
    /**
     * Verifica headers HTTP
     */
    private function checkHeaders() {
        $score = 0;
        $issues = [];
        
        // Accept header
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (empty($accept)) {
            $score += 15;
            $issues[] = 'no_accept';
        } elseif ($accept === '*/*') {
            $score += 8;
            $issues[] = 'generic_accept';
        }
        
        // Accept-Language
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $score += 12;
            $issues[] = 'no_lang';
        }
        
        // Sec-Fetch headers (Chrome moderno)
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (preg_match('/Chrome\/([89]\d|1\d{2})/', $ua)) {
            if (empty($_SERVER['HTTP_SEC_FETCH_SITE'])) {
                $score += 20;
                $issues[] = 'no_sec_fetch';
            }
        }
        
        return ['score' => $score, 'issues' => $issues];
    }
    
    /**
     * Analise completa combinada
     */
    public function fullAnalysis($behaviorData = []) {
        // Analisa comportamento JS
        $behavior = $this->analyzeBehavior($behaviorData);
        
        // Analisa request HTTP
        $request = $this->analyzeRequest();
        
        // Combina scores
        $totalScore = $behavior['score'] + $request['score'];
        $allDetections = array_merge($behavior['detections'], $request['detections']);
        
        return [
            'score' => $totalScore,
            'is_bot' => $totalScore >= $this->config['bot_threshold'],
            'confidence' => min(($totalScore / 100) * 100, 100),
            'detections' => $allDetections,
            'behavior_score' => $behavior['score'],
            'request_score' => $request['score']
        ];
    }
    
    /**
     * Verifica se deve bloquear
     */
    public function shouldBlock($behaviorData = []) {
        $analysis = $this->fullAnalysis($behaviorData);
        
        if ($analysis['is_bot']) {
            return [
                'block' => true,
                'reason' => implode(', ', array_slice($analysis['detections'], 0, 3)),
                'score' => $analysis['score'],
                'confidence' => $analysis['confidence']
            ];
        }
        
        return ['block' => false, 'score' => $analysis['score']];
    }
}

/**
 * Classe para Proof of Work Challenge
 */
class ProofOfWorkChallenge {
    
    private $cacheDir;
    private $defaultDifficulty = 4;
    
    public function __construct($cacheDir = null) {
        $this->cacheDir = $cacheDir ?: (defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/cache/');
    }
    
    /**
     * Gera novo desafio
     */
    public function generate($difficulty = null) {
        $difficulty = $difficulty ?? $this->defaultDifficulty;
        $challenge = bin2hex(random_bytes(16));
        $timestamp = time();
        
        $this->saveChallenge($challenge, [
            'difficulty' => $difficulty,
            'created' => $timestamp,
            'expires' => $timestamp + 300 // 5 minutos
        ]);
        
        return [
            'challenge' => $challenge,
            'difficulty' => $difficulty,
            'target' => str_repeat('0', $difficulty)
        ];
    }
    
    /**
     * Verifica solucao
     */
    public function verify($challenge, $nonce) {
        $challenges = $this->loadChallenges();
        
        if (!isset($challenges[$challenge])) {
            return ['valid' => false, 'error' => 'invalid_challenge'];
        }
        
        $data = $challenges[$challenge];
        
        if ($data['expires'] < time()) {
            $this->removeChallenge($challenge);
            return ['valid' => false, 'error' => 'expired'];
        }
        
        $hash = hash('sha256', $challenge . $nonce);
        $target = str_repeat('0', $data['difficulty']);
        
        if (substr($hash, 0, $data['difficulty']) === $target) {
            $this->removeChallenge($challenge);
            return ['valid' => true, 'hash' => $hash];
        }
        
        return ['valid' => false, 'error' => 'wrong_nonce'];
    }
    
    private function getChallengeFile() {
        return $this->cacheDir . 'pow_challenges.json';
    }
    
    private function loadChallenges() {
        $file = $this->getChallengeFile();
        if (!file_exists($file)) return [];
        return json_decode(file_get_contents($file), true) ?: [];
    }
    
    private function saveChallenge($id, $data) {
        $challenges = $this->loadChallenges();
        // Limpa expirados
        $challenges = array_filter($challenges, fn($c) => $c['expires'] > time());
        $challenges[$id] = $data;
        file_put_contents($this->getChallengeFile(), json_encode($challenges));
    }
    
    private function removeChallenge($id) {
        $challenges = $this->loadChallenges();
        unset($challenges[$id]);
        file_put_contents($this->getChallengeFile(), json_encode($challenges));
    }
}

/**
 * Sistema de Honeypot
 */
class HoneypotSystem {
    
    private $cacheDir;
    
    public function __construct($cacheDir = null) {
        $this->cacheDir = $cacheDir ?: (defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/cache/');
    }
    
    /**
     * Gera HTML do honeypot
     */
    public function generateHTML($campaignId = '') {
        $linkToken = bin2hex(random_bytes(8));
        $fieldToken = bin2hex(random_bytes(8));
        
        $this->saveToken($linkToken, 'link', $campaignId);
        $this->saveToken($fieldToken, 'field', $campaignId);
        
        // CSS inline para esconder de humanos mas nao de bots
        $html = '
<!-- hp -->
<a href="/?_hp=' . $linkToken . '" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:auto;" tabindex="-1" aria-hidden="true">Click for discount</a>
<div style="position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden;">
<input type="text" name="' . $fieldToken . '" value="" autocomplete="off" tabindex="-1" />
</div>
';
        return $html;
    }
    
    /**
     * Verifica se honeypot foi acionado
     */
    public function check() {
        // Link honeypot
        if (!empty($_GET['_hp'])) {
            if ($this->isValidToken($_GET['_hp'])) {
                return ['triggered' => true, 'type' => 'link', 'token' => $_GET['_hp']];
            }
        }
        
        // Field honeypot
        $tokens = $this->loadTokens();
        foreach ($_POST as $key => $value) {
            if (isset($tokens[$key]) && $tokens[$key]['type'] === 'field' && !empty($value)) {
                return ['triggered' => true, 'type' => 'field', 'token' => $key];
            }
        }
        
        return ['triggered' => false];
    }
    
    private function getTokenFile() {
        return $this->cacheDir . 'honeypot_tokens.json';
    }
    
    private function loadTokens() {
        $file = $this->getTokenFile();
        if (!file_exists($file)) return [];
        $tokens = json_decode(file_get_contents($file), true) ?: [];
        // Filtra expirados
        return array_filter($tokens, fn($t) => $t['created'] > time() - 86400);
    }
    
    private function saveToken($token, $type, $campaignId) {
        $tokens = $this->loadTokens();
        $tokens[$token] = [
            'type' => $type,
            'campaign' => $campaignId,
            'created' => time()
        ];
        file_put_contents($this->getTokenFile(), json_encode($tokens));
    }
    
    private function isValidToken($token) {
        $tokens = $this->loadTokens();
        return isset($tokens[$token]);
    }
}

// Instancias globais das novas classes
$advancedDetector = AdvancedBotDetector::getInstance();
$powChallenge = new ProofOfWorkChallenge();
$honeypot = new HoneypotSystem();
