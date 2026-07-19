<?php
/**
 * COMMANDER V10.3 - Funções Auxiliares
 * 
 * INSTRUÇÕES: Crie este arquivo na raiz do COMMANDER
 */

// Previne acesso direto
if (!defined('COMMANDER_ACCESS')) {
    http_response_code(403);
    exit('Acesso negado');
}

/**
 * ============================================
 * FUNÇÕES DE ARQUIVOS E DADOS
 * ============================================
 */

/**
 * Lê arquivo JSON com tratamento de erros
 */
function readJsonFile($file, $default = []) {
    if (!file_exists($file)) {
        return $default;
    }
    
    $content = @file_get_contents($file);
    if ($content === false) {
        return $default;
    }
    
    $data = json_decode($content, true);
    return $data !== null ? $data : $default;
}

/**
 * Escreve arquivo JSON com lock
 */
function writeJsonFile($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($file, $json, LOCK_EX) !== false;
}

/**
 * Adiciona item a arquivo JSON
 */
function appendToJsonFile($file, $item, $maxItems = 10000) {
    $data = readJsonFile($file, []);
    
    array_unshift($data, $item);
    
    // Limita tamanho do array
    if (count($data) > $maxItems) {
        $data = array_slice($data, 0, $maxItems);
    }
    
    return writeJsonFile($file, $data);
}

/**
 * ============================================
 * LISTA GLOBAL DE BOTS APRENDIDOS
 * ============================================
 * Quando um bot é detectado, seu fingerprint (IP + UA hash) é salvo.
 * Nas próximas visitas, é identificado imediatamente sem re-análise.
 */

// Garante que DATA_DIR existe antes de usar
if (!defined('DATA_DIR')) {
    define('DATA_DIR', __DIR__ . '/data/');
}

if (!defined('FILE_LEARNED_BOTS')) {
    define('FILE_LEARNED_BOTS', DATA_DIR . 'learned_bots.json');
}

// Armazenamento de bots em SQLite (retencao infinita, sem travar).
// Se SQLite nao existir no servidor, cai automaticamente no JSON abaixo.
if (file_exists(__DIR__ . '/bots-storage.php')) {
    require_once __DIR__ . '/bots-storage.php';
}

/**
 * Verifica se o visitante já está na lista de bots aprendidos
 */
function isLearnedBot($ip, $userAgent) {
    // SQLite primeiro (retencao infinita, busca instantanea)
    if (function_exists('botStorageIsLearnedBot')) {
        $res = botStorageIsLearnedBot($ip, $userAgent);
        if ($res !== null) {
            return $res;
        }
    }

    // Fallback: JSON antigo (so roda se SQLite indisponivel)
    $fingerprint = md5($ip . '|' . $userAgent);
    $ipHash = md5($ip);
    
    $learnedBots = readJsonFile(FILE_LEARNED_BOTS, []);
    
    // Verifica por fingerprint exato (IP + UA)
    if (isset($learnedBots['fingerprints'][$fingerprint])) {
        return [
            'isBot' => true,
            'reason' => 'Bot aprendido: ' . ($learnedBots['fingerprints'][$fingerprint]['reason'] ?? 'detectado anteriormente'),
            'learned_at' => $learnedBots['fingerprints'][$fingerprint]['date'] ?? ''
        ];
    }
    
    // Verifica por IP (se IP foi marcado como bot multiplas vezes)
    if (isset($learnedBots['ips'][$ipHash]) && $learnedBots['ips'][$ipHash]['count'] >= 3) {
        return [
            'isBot' => true,
            'reason' => 'IP bloqueado: multiplas deteccoes (' . $learnedBots['ips'][$ipHash]['count'] . ')',
            'learned_at' => $learnedBots['ips'][$ipHash]['last_seen'] ?? ''
        ];
    }
    
    return ['isBot' => false];
}

/**
 * Adiciona bot à lista de aprendidos (global - para todas as campanhas)
 */
function addLearnedBot($ip, $userAgent, $reason, $campaignId = '') {
    // SQLite primeiro (retencao infinita, escrita segura)
    if (function_exists('botStorageAddLearnedBot')) {
        $res = botStorageAddLearnedBot($ip, $userAgent, $reason, $campaignId);
        if ($res !== null) {
            return $res;
        }
    }

    // Fallback: JSON antigo (so roda se SQLite indisponivel)
    $fingerprint = md5($ip . '|' . $userAgent);
    $ipHash = md5($ip);
    $date = date('Y-m-d H:i:s');
    
    $learnedBots = readJsonFile(FILE_LEARNED_BOTS, [
        'fingerprints' => [],
        'ips' => [],
        'stats' => ['total' => 0, 'last_updated' => '']
    ]);
    
    // Adiciona fingerprint
    if (!isset($learnedBots['fingerprints'][$fingerprint])) {
        $learnedBots['fingerprints'][$fingerprint] = [
            'reason' => $reason,
            'date' => $date,
            'campaign' => $campaignId,
            'ip_masked' => substr($ip, 0, strrpos($ip, '.')) . '.xxx'
        ];
        $learnedBots['stats']['total']++;
    }
    
    // Incrementa contador do IP
    if (!isset($learnedBots['ips'][$ipHash])) {
        $learnedBots['ips'][$ipHash] = ['count' => 0, 'first_seen' => $date];
    }
    $learnedBots['ips'][$ipHash]['count']++;
    $learnedBots['ips'][$ipHash]['last_seen'] = $date;
    $learnedBots['ips'][$ipHash]['last_reason'] = $reason;
    
    $learnedBots['stats']['last_updated'] = $date;
    
    // RETENCAO INFINITA: nao corta mais a lista (nunca apaga bots).
    // O ideal e usar SQLite (bots-storage.php); este JSON e apenas fallback.
    
    writeJsonFile(FILE_LEARNED_BOTS, $learnedBots);
    return true;
}

/**
 * Retorna estatísticas dos bots aprendidos
 */
function getLearnedBotsStats() {
    // SQLite primeiro
    if (function_exists('botStorageStats')) {
        $res = botStorageStats();
        if ($res !== null) {
            return $res;
        }
    }

    // Fallback: JSON antigo
    $learnedBots = readJsonFile(FILE_LEARNED_BOTS, []);
    return [
        'total_fingerprints' => count($learnedBots['fingerprints'] ?? []),
        'total_ips' => count($learnedBots['ips'] ?? []),
        'last_updated' => $learnedBots['stats']['last_updated'] ?? '-'
    ];
}

/**
 * Limpa lista de bots aprendidos (admin only)
 */
function clearLearnedBots() {
    // SQLite primeiro
    if (function_exists('botStorageClear')) {
        $res = botStorageClear();
        if ($res !== null) {
            // tambem zera o JSON antigo para manter consistencia
            writeJsonFile(FILE_LEARNED_BOTS, [
                'fingerprints' => [],
                'ips' => [],
                'stats' => ['total' => 0, 'last_updated' => date('Y-m-d H:i:s') . ' (limpo)']
            ]);
            return $res;
        }
    }

    // Fallback: JSON antigo
    return writeJsonFile(FILE_LEARNED_BOTS, [
        'fingerprints' => [],
        'ips' => [],
        'stats' => ['total' => 0, 'last_updated' => date('Y-m-d H:i:s') . ' (limpo)']
    ]);
}

/**
 * ============================================
 * GEOLOCALIZAÇÃO DE IP
 * ============================================
 */

// Garante que CACHE_DIR existe
if (!defined('CACHE_DIR')) {
    define('CACHE_DIR', __DIR__ . '/cache/');
}

/**
 * Retorna o codigo do pais do IP (ex: BR, US, PT)
 * 
 * METODOS SUPORTADOS (em ordem de prioridade):
 * 1. Cloudflare (header CF-IPCountry) - GRATIS, SEM LIMITE, INSTANTANEO
 * 2. Vercel (header X-Vercel-IP-Country) - GRATIS se hospedado na Vercel
 * 3. Servidor com mod_geoip - Header GEOIP_COUNTRY_CODE
 * 4. MaxMind GeoLite2 (banco local)
 * 5. APIs externas (fallback)
 * 
 * RECOMENDACAO: Use Cloudflare (gratis) - nao precisa configurar nada!
 */
function getIPCountry($ip) {
    if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') {
        return 'BR'; // localhost = Brasil por padrao
    }

    // =====================================================
    // CORRECAO PAIS (3): Headers de geo (CF-IPCountry etc.) so valem se o IP
    // consultado for o IP do REQUEST ATUAL.
    // Quando a api.php e chamada via cURL (servidor->servidor) pelo tracker,
    // os headers do _SERVER refletem o pais do SERVIDOR que chamou (VPS),
    // e nao o do visitante. Logo, so confiamos nos headers quando o $ip
    // recebido bate com o IP de quem fez a requisicao atual.
    // =====================================================
    $requestIP = $_SERVER['REMOTE_ADDR'] ?? '';
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP'] as $h) {
        if (!empty($_SERVER[$h])) {
            $cand = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($cand, FILTER_VALIDATE_IP)) {
                $requestIP = $cand;
                break;
            }
        }
    }
    $useHeaders = ($ip === $requestIP);

    // =====================================================
    // METODO 1: CLOUDFLARE (GRATIS - SEM LIMITE - INSTANTANEO)
    // Se voce usa Cloudflare, ele ja envia o pais automaticamente!
    // Nao precisa configurar nada, so ativar o proxy (nuvem laranja)
    // =====================================================
    if ($useHeaders && !empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        $country = strtoupper($_SERVER['HTTP_CF_IPCOUNTRY']);
        if (strlen($country) === 2 && $country !== 'XX') {
            return $country;
        }
    }
    
    // =====================================================
    // METODO 2: VERCEL (GRATIS - se hospedado na Vercel)
    // =====================================================
    if ($useHeaders && !empty($_SERVER['HTTP_X_VERCEL_IP_COUNTRY'])) {
        $country = strtoupper($_SERVER['HTTP_X_VERCEL_IP_COUNTRY']);
        if (strlen($country) === 2) {
            return $country;
        }
    }
    
    // =====================================================
    // METODO 3: SERVIDOR COM MOD_GEOIP (Apache/Nginx)
    // Alguns servidores ja tem modulo GeoIP instalado
    // =====================================================
    $serverGeoHeaders = [
        'GEOIP_COUNTRY_CODE',
        'HTTP_X_COUNTRY_CODE', 
        'HTTP_X_GEO_COUNTRY',
        'HTTP_CF_IPCOUNTRY',
        'HTTP_X_REAL_COUNTRY'
    ];
    if ($useHeaders) {
        foreach ($serverGeoHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $country = strtoupper($_SERVER[$header]);
                if (strlen($country) === 2 && $country !== 'XX') {
                    return $country;
                }
            }
        }
    }

    // =====================================================
    // CACHE - Verifica se ja temos o pais em cache
    // =====================================================
    $cacheDir = defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/cache/';
    $cacheFile = $cacheDir . 'country_' . md5($ip) . '.json';
    
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (isset($cached['expires']) && $cached['expires'] > time()) {
            return $cached['country'] ?? 'XX';
        }
    }

    $country = 'XX';

    // =====================================================
    // METODO 4: MaxMind GeoLite2 (BANCO LOCAL - SEM LIMITES)
    // Baixe gratis em: https://dev.maxmind.com/geoip/geolite2-free-geolocation-data
    // =====================================================
    $geoipDb = __DIR__ . '/GeoLite2-Country.mmdb';
    
    if ($country === 'XX' && file_exists($geoipDb)) {
        if (function_exists('maxminddb_open')) {
            $reader = @maxminddb_open($geoipDb);
            if ($reader) {
                $record = @maxminddb_get($reader, $ip);
                if ($record && isset($record['country']['iso_code'])) {
                    $country = strtoupper($record['country']['iso_code']);
                }
                maxminddb_close($reader);
            }
        } elseif ($country === 'XX') {
            $country = readGeoLite2Pure($geoipDb, $ip);
        }
    }

    // =====================================================
    // METODO 5: ipinfo.io (50.000/mes GRATIS com token)
    // =====================================================
    if ($country === 'XX' && defined('GEOIP_TOKEN') && !empty(GEOIP_TOKEN)) {
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $response = @file_get_contents("https://ipinfo.io/{$ip}/country?token=" . GEOIP_TOKEN, false, $ctx);
        if ($response && strlen(trim($response)) === 2) {
            $country = strtoupper(trim($response));
        }
    }

    // =====================================================
    // METODO 6: ip-api.com (45 req/min - FALLBACK)
    // =====================================================
    if ($country === 'XX') {
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=countryCode", false, $ctx);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['countryCode']) && strlen($data['countryCode']) === 2) {
                $country = strtoupper($data['countryCode']);
            }
        }
    }

    // =====================================================
    // METODO 7: ipapi.co (1.000/dia - FALLBACK EXTRA)
    // =====================================================
    if ($country === 'XX') {
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $response = @file_get_contents("https://ipapi.co/{$ip}/country/", false, $ctx);
        if ($response && strlen(trim($response)) === 2 && strpos($response, 'error') === false) {
            $country = strtoupper(trim($response));
        }
    }

    // Salva no cache (24h)
    if ($country !== 'XX') {
        @file_put_contents($cacheFile, json_encode([
            'country' => $country,
            'expires' => time() + 86400
        ]));
    }

    return $country;
}

/**
 * Leitor PHP puro para GeoLite2 MMDB
 * Funciona sem extensao maxminddb e sem Composer
 * Compativel com qualquer servidor PHP 7.0+
 */
function readGeoLite2Pure($dbFile, $ip) {
    try {
        // Se a classe do Composer existir, usa ela (mais rapida)
        if (class_exists('GeoIp2\Database\Reader')) {
            $reader = new \GeoIp2\Database\Reader($dbFile);
            $record = $reader->country($ip);
            return $record->country->isoCode ?? 'XX';
        }
        
        // Leitor PHP puro - funciona em qualquer servidor
        $reader = new MaxMindDBReaderPure($dbFile);
        $record = $reader->get($ip);
        
        if ($record && isset($record['country']['iso_code'])) {
            return strtoupper($record['country']['iso_code']);
        }
        
        return 'XX';
        
    } catch (Exception $e) {
        return 'XX';
    }
}

/**
 * Classe leitora de MMDB pura em PHP
 * Nao requer extensoes ou Composer
 */
class MaxMindDBReaderPure {
    private $fileHandle;
    private $metadata;
    private $decoder;
    private $ipV4Start;
    private $DATA_SECTION_SEPARATOR_SIZE = 16;
    
    public function __construct($database) {
        if (!is_readable($database)) {
            throw new Exception("Database file not found: $database");
        }
        
        $this->fileHandle = @fopen($database, 'rb');
        if (!$this->fileHandle) {
            throw new Exception("Cannot open database file");
        }
        
        $this->metadata = $this->findMetadata();
        if (!$this->metadata) {
            throw new Exception("Invalid MMDB file");
        }
        
        $this->decoder = new MaxMindDecoderPure($this->fileHandle, $this->metadata['search_tree_size'] + $this->DATA_SECTION_SEPARATOR_SIZE);
    }
    
    public function get($ipAddress) {
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            return null;
        }
        
        $pointer = $this->findAddressInTree($ipAddress);
        if ($pointer === 0) {
            return null;
        }
        
        return $this->resolveDataPointer($pointer);
    }
    
    private function findMetadata() {
        $metadataMarker = "\xab\xcd\xefMaxMind.com";
        
        fseek($this->fileHandle, -128 * 1024, SEEK_END);
        $fileContent = fread($this->fileHandle, 128 * 1024);
        
        $markerPos = strrpos($fileContent, $metadataMarker);
        if ($markerPos === false) {
            return null;
        }
        
        $metadataStart = $markerPos + strlen($metadataMarker);
        $metadataBytes = substr($fileContent, $metadataStart);
        
        fseek($this->fileHandle, 0, SEEK_END);
        $fileSize = ftell($this->fileHandle);
        
        $metadataDecoder = new MaxMindDecoderPure($this->fileHandle, $fileSize - strlen($fileContent) + $metadataStart);
        list($metadata, ) = $metadataDecoder->decode(0);
        
        $metadata['search_tree_size'] = ($metadata['record_size'] * 2 / 8) * $metadata['node_count'];
        
        return $metadata;
    }
    
    private function findAddressInTree($ipAddress) {
        $rawAddress = inet_pton($ipAddress);
        $isIpV4 = strlen($rawAddress) === 4;
        
        $bitCount = strlen($rawAddress) * 8;
        $node = $this->startNode($bitCount);
        
        for ($i = 0; $i < $bitCount; $i++) {
            $bit = 1 & (ord($rawAddress[(int)($i / 8)]) >> (7 - ($i % 8)));
            $node = $this->readNode($node, $bit);
            
            if ($node >= $this->metadata['node_count']) {
                break;
            }
        }
        
        if ($node === $this->metadata['node_count']) {
            return 0;
        }
        
        return $node;
    }
    
    private function startNode($bitCount) {
        if ($bitCount === 128 || $this->metadata['ip_version'] === 4) {
            return 0;
        }
        
        if (!isset($this->ipV4Start)) {
            $node = 0;
            for ($i = 0; $i < 96 && $node < $this->metadata['node_count']; $i++) {
                $node = $this->readNode($node, 0);
            }
            $this->ipV4Start = $node;
        }
        
        return $this->ipV4Start;
    }
    
    private function readNode($nodeNumber, $index) {
        $recordSize = $this->metadata['record_size'];
        $nodeSize = $recordSize / 4;
        $position = $nodeNumber * $nodeSize;
        
        fseek($this->fileHandle, $position);
        $bytes = fread($this->fileHandle, $nodeSize);
        
        if ($recordSize === 24) {
            if ($index === 0) {
                return unpack('N', "\x00" . substr($bytes, 0, 3))[1];
            }
            return unpack('N', "\x00" . substr($bytes, 3, 3))[1];
        } elseif ($recordSize === 28) {
            if ($index === 0) {
                $middle = ord($bytes[3]) >> 4;
                return unpack('N', chr($middle) . substr($bytes, 0, 3))[1];
            }
            $middle = ord($bytes[3]) & 0x0F;
            return unpack('N', chr($middle) . substr($bytes, 4, 3))[1];
        } elseif ($recordSize === 32) {
            if ($index === 0) {
                return unpack('N', substr($bytes, 0, 4))[1];
            }
            return unpack('N', substr($bytes, 4, 4))[1];
        }
        
        return 0;
    }
    
    private function resolveDataPointer($pointer) {
        $offset = ($pointer - $this->metadata['node_count']) + $this->metadata['search_tree_size'];
        list($data, ) = $this->decoder->decode($offset);
        return $data;
    }
    
    public function __destruct() {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
        }
    }
}

/**
 * Decodificador de dados MMDB
 */
class MaxMindDecoderPure {
    private $fileHandle;
    private $pointerBase;
    
    const TYPES = [
        0 => 'extended',
        1 => 'pointer',
        2 => 'utf8_string',
        3 => 'double',
        4 => 'bytes',
        5 => 'uint16',
        6 => 'uint32',
        7 => 'map',
        8 => 'int32',
        9 => 'uint64',
        10 => 'uint128',
        11 => 'array',
        14 => 'boolean',
        15 => 'float'
    ];
    
    public function __construct($fileHandle, $pointerBase = 0) {
        $this->fileHandle = $fileHandle;
        $this->pointerBase = $pointerBase;
    }
    
    public function decode($offset) {
        fseek($this->fileHandle, $this->pointerBase + $offset);
        
        $ctrlByte = ord(fread($this->fileHandle, 1));
        $type = $ctrlByte >> 5;
        
        if ($type === 0) {
            $nextByte = ord(fread($this->fileHandle, 1));
            $type = $nextByte + 7;
        }
        
        if ($type === 1) {
            return $this->decodePointer($ctrlByte, $offset);
        }
        
        $size = $ctrlByte & 0x1f;
        if ($size >= 29) {
            $bytesToRead = $size - 28;
            $bytes = fread($this->fileHandle, $bytesToRead);
            if ($size === 29) {
                $size = 29 + ord($bytes);
            } elseif ($size === 30) {
                $size = 285 + unpack('n', $bytes)[1];
            } else {
                $size = 65821 + unpack('N', "\x00" . $bytes)[1];
            }
        }
        
        $newOffset = ftell($this->fileHandle) - $this->pointerBase + $size;
        
        return [$this->decodeByType($type, $size), $newOffset];
    }
    
    private function decodePointer($ctrlByte, $offset) {
        $pointerSize = (($ctrlByte >> 3) & 0x3) + 1;
        $base = $ctrlByte & 0x7;
        
        $bytes = fread($this->fileHandle, $pointerSize);
        $packed = str_pad($bytes, 4, "\x00", STR_PAD_LEFT);
        $pointer = unpack('N', $packed)[1];
        
        if ($pointerSize === 1) {
            $pointer += ($base << 8);
        } elseif ($pointerSize === 2) {
            $pointer += ($base << 16) + 2048;
        } elseif ($pointerSize === 3) {
            $pointer += ($base << 24) + 526336;
        }
        
        $currentPos = ftell($this->fileHandle);
        list($data, ) = $this->decode($pointer);
        
        return [$data, $currentPos - $this->pointerBase];
    }
    
    private function decodeByType($type, $size) {
        switch ($type) {
            case 2: // utf8_string
                return $size > 0 ? fread($this->fileHandle, $size) : '';
            
            case 3: // double
                $bytes = fread($this->fileHandle, 8);
                return unpack('E', $bytes)[1];
            
            case 4: // bytes
                return $size > 0 ? fread($this->fileHandle, $size) : '';
            
            case 5: // uint16
            case 6: // uint32
            case 9: // uint64
            case 10: // uint128
                return $this->decodeUint($size);
            
            case 7: // map
                return $this->decodeMap($size);
            
            case 8: // int32
                $bytes = fread($this->fileHandle, $size);
                $packed = str_pad($bytes, 4, "\x00", STR_PAD_LEFT);
                return unpack('N', $packed)[1];
            
            case 11: // array
                return $this->decodeArray($size);
            
            case 14: // boolean
                return $size !== 0;
            
            case 15: // float
                $bytes = fread($this->fileHandle, 4);
                return unpack('G', $bytes)[1];
            
            default:
                return null;
        }
    }
    
    private function decodeUint($size) {
        if ($size === 0) return 0;
        $bytes = fread($this->fileHandle, $size);
        $packed = str_pad($bytes, 4, "\x00", STR_PAD_LEFT);
        return unpack('N', $packed)[1];
    }
    
    private function decodeMap($size) {
        $map = [];
        for ($i = 0; $i < $size; $i++) {
            list($key, ) = $this->decode(ftell($this->fileHandle) - $this->pointerBase);
            list($value, ) = $this->decode(ftell($this->fileHandle) - $this->pointerBase);
            $map[$key] = $value;
        }
        return $map;
    }
    
    private function decodeArray($size) {
        $array = [];
        for ($i = 0; $i < $size; $i++) {
            list($value, ) = $this->decode(ftell($this->fileHandle) - $this->pointerBase);
            $array[] = $value;
        }
        return $array;
    }
}

/**
 * Baixa/atualiza o banco de dados GeoLite2 automaticamente
 * 
 * COMO USAR:
 * 1. Crie conta gratis em: https://www.maxmind.com/en/geolite2/signup
 * 2. Gere uma License Key em: Account > Manage License Keys
 * 3. Adicione no config.php: define('MAXMIND_LICENSE_KEY', 'sua_key');
 * 4. Chame esta funcao 1x por mes (via cron ou manualmente)
 * 
 * CRON SUGERIDO (1x por semana):
 * 0 3 * * 0 php -r "require 'functions.php'; updateGeoLite2Database();"
 */
function updateGeoLite2Database() {
    if (!defined('MAXMIND_LICENSE_KEY') || empty(MAXMIND_LICENSE_KEY)) {
        return [
            'success' => false, 
            'error' => 'MAXMIND_LICENSE_KEY nao configurada no config.php'
        ];
    }
    
    $licenseKey = MAXMIND_LICENSE_KEY;
    $dbPath = __DIR__ . '/GeoLite2-Country.mmdb';
    $tempPath = __DIR__ . '/geoip_temp.tar.gz';
    
    // URL oficial de download do MaxMind
    $url = "https://download.maxmind.com/app/geoip_download?" . http_build_query([
        'edition_id' => 'GeoLite2-Country',
        'license_key' => $licenseKey,
        'suffix' => 'tar.gz'
    ]);
    
    // Baixa o arquivo
    $ctx = stream_context_create(['http' => ['timeout' => 60]]);
    $content = @file_get_contents($url, false, $ctx);
    
    if (!$content || strlen($content) < 10000) {
        return ['success' => false, 'error' => 'Falha ao baixar arquivo do MaxMind'];
    }
    
    // Salva temporariamente
    file_put_contents($tempPath, $content);
    
    // Extrai o .mmdb do tar.gz
    try {
        $phar = new PharData($tempPath);
        
        // Encontra o arquivo .mmdb dentro do tar
        foreach (new RecursiveIteratorIterator($phar) as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'mmdb') {
                // Copia o .mmdb para o destino
                copy($file->getPathname(), $dbPath);
                break;
            }
        }
        
        // Remove arquivo temporario
        @unlink($tempPath);
        
        // Verifica se o arquivo foi criado
        if (file_exists($dbPath) && filesize($dbPath) > 100000) {
            return [
                'success' => true, 
                'path' => $dbPath, 
                'size' => filesize($dbPath),
                'size_mb' => round(filesize($dbPath) / 1024 / 1024, 2) . ' MB'
            ];
        }
        
        return ['success' => false, 'error' => 'Arquivo extraido mas parece invalido'];
        
    } catch (Exception $e) {
        @unlink($tempPath);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * ============================================
 * LOGS DE BOTS
 * ============================================
 */

if (!defined('FILE_BOT_LOGS')) {
    define('FILE_BOT_LOGS', DATA_DIR . 'bot_logs.json');
}

/**
 * Registra log de bot detectado
 * Esta função é chamada quando um visitante é identificado como bot
 */
function logBot($data) {
    $logs = readJsonFile(FILE_BOT_LOGS, []);
    
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $data['ip'] ?? '',
        'user_agent' => substr($data['user_agent'] ?? '', 0, 300),
        'reason' => $data['reason'] ?? 'Desconhecido',
        'campaign' => $data['campaign'] ?? '',
        'campaign_id' => $data['campaign_id'] ?? $data['campaign'] ?? '',
        'platform' => $data['platform'] ?? '',
        'referer' => $data['referer'] ?? ''
    ];
    
    // Adiciona no inicio do array
    array_unshift($logs, $entry);
    
    // Limita a 1000 entradas
    if (count($logs) > 1000) {
        $logs = array_slice($logs, 0, 1000);
    }
    
    writeJsonFile(FILE_BOT_LOGS, $logs);
    return true;
}

/**
 * Retorna logs de bots
 */
function getBotLogs($limit = 100, $offset = 0) {
    $logs = readJsonFile(FILE_BOT_LOGS, []);
    return array_slice($logs, $offset, $limit);
}

/**
 * ============================================
 * FUNÇÕES DE CAMPANHAS
 * ============================================
 */

/**
 * Obtém todas as campanhas
 */
function getCampaigns() {
    return readJsonFile(FILE_CAMPAIGNS, []);
}

/**
 * Obtém campanha por ID
 */
function getCampaignById($id) {
    $campaigns = getCampaigns();
    foreach ($campaigns as $campaign) {
        if ($campaign['id'] === $id) {
            return $campaign;
        }
    }
    return null;
}

/**
 * Obtém campanha por slug
 */
function getCampaignBySlug($slug) {
    $campaigns = getCampaigns();
    foreach ($campaigns as $campaign) {
        if ($campaign['slug'] === $slug) {
            return $campaign;
        }
    }
    return null;
}

/**
 * Salva campanha
 */
function saveCampaign($campaign) {
    $campaigns = getCampaigns();
    $found = false;
    
    foreach ($campaigns as $key => $c) {
        if ($c['id'] === $campaign['id']) {
            $campaigns[$key] = $campaign;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $campaigns[] = $campaign;
    }
    
    return writeJsonFile(FILE_CAMPAIGNS, $campaigns);
}

/**
 * Deleta campanha
 */
function deleteCampaign($id) {
    $campaigns = getCampaigns();
    $campaigns = array_filter($campaigns, function($c) use ($id) {
        return $c['id'] !== $id;
    });
    
    return writeJsonFile(FILE_CAMPAIGNS, array_values($campaigns));
}

/**
 * Gera ID único para campanha
 */
function generateCampaignId() {
    return 'camp_' . bin2hex(random_bytes(8));
}

/**
 * ============================================
 * FUNÇÕES DE ESTATÍSTICAS
 * ============================================
 */

/**
 * Obtém estatísticas
 */
function getStats() {
    return readJsonFile(FILE_STATS, [
        'total_clicks' => 0,
        'total_blocks' => 0,
        'total_passes' => 0,
        'by_campaign' => [],
        'by_date' => [],
        'by_platform' => []
    ]);
}

/**
 * Retorna estatisticas LOCAIS de uma campanha especifica
 * IMPORTANTE: Esta funcao retorna cliques registrados no cloaker,
 * NAO os cliques da API do TikTok/Google/Facebook.
 * Usada para funcao de Aquecimento (warm-up).
 * 
 * @param string $campaignId ID da campanha
 * @return array Estatisticas da campanha
 */
function getCampaignStats($campaignId) {
    $stats = getStats();
    
    // Retorna stats da campanha se existir
    if (isset($stats['by_campaign'][$campaignId])) {
        return $stats['by_campaign'][$campaignId];
    }
    
    // Retorna valores padrao se campanha nao tiver stats ainda
    return [
        'clicks' => 0,
        'blocks' => 0,
        'passes' => 0,
        'conversions' => 0,
        'revenue' => 0
    ];
}

/**
 * Incrementa estatística
 */
function incrementStat($key, $subkey = null, $amount = 1) {
    $stats = getStats();
    
    if ($subkey !== null) {
        if (!isset($stats[$key])) {
            $stats[$key] = [];
        }
        if (!isset($stats[$key][$subkey])) {
            $stats[$key][$subkey] = 0;
        }
        $stats[$key][$subkey] += $amount;
    } else {
        if (!isset($stats[$key])) {
            $stats[$key] = 0;
        }
        $stats[$key] += $amount;
    }
    
    return writeJsonFile(FILE_STATS, $stats);
}

/**
 * Registra clique
 */
function recordClick($campaignId, $isBot = false, $platform = 'unknown') {
    $stats = getStats();
    $today = date('Y-m-d');
    
    // Total
    $stats['total_clicks'] = ($stats['total_clicks'] ?? 0) + 1;
    
    if ($isBot) {
        $stats['total_blocks'] = ($stats['total_blocks'] ?? 0) + 1;
    } else {
        $stats['total_passes'] = ($stats['total_passes'] ?? 0) + 1;
    }
    
    // Por campanha
    if (!isset($stats['by_campaign'][$campaignId])) {
        $stats['by_campaign'][$campaignId] = [
            'clicks' => 0, 'blocks' => 0, 'passes' => 0, 'conversions' => 0, 'revenue' => 0
        ];
    }
    $stats['by_campaign'][$campaignId]['clicks']++;
    $stats['by_campaign'][$campaignId][$isBot ? 'blocks' : 'passes']++;
    
    // Por data
    if (!isset($stats['by_date'][$today])) {
        $stats['by_date'][$today] = ['clicks' => 0, 'blocks' => 0, 'passes' => 0];
    }
    $stats['by_date'][$today]['clicks']++;
    $stats['by_date'][$today][$isBot ? 'blocks' : 'passes']++;
    
    // Por plataforma
    if (!isset($stats['by_platform'][$platform])) {
        $stats['by_platform'][$platform] = ['clicks' => 0, 'blocks' => 0, 'passes' => 0];
    }
    $stats['by_platform'][$platform]['clicks']++;
    $stats['by_platform'][$platform][$isBot ? 'blocks' : 'passes']++;
    
    // Limita histórico de datas (últimos 90 dias)
    $stats['by_date'] = array_slice($stats['by_date'], -90, 90, true);
    
    return writeJsonFile(FILE_STATS, $stats);
}

/**
 * Registra conversão
 */
function recordConversion($campaignId, $value = 0, $type = 'sale') {
    $stats = getStats();
    
    if (!isset($stats['by_campaign'][$campaignId])) {
        $stats['by_campaign'][$campaignId] = [
            'clicks' => 0, 'blocks' => 0, 'passes' => 0, 'conversions' => 0, 'revenue' => 0
        ];
    }
    
    $stats['by_campaign'][$campaignId]['conversions']++;
    $stats['by_campaign'][$campaignId]['revenue'] += $value;
    
    return writeJsonFile(FILE_STATS, $stats);
}

/**
 * ============================================
 * FUNÇÕES DE LOG
 * ============================================
 */

/**
 * Registra log de acesso
 */
function logAccess($data) {
    if (!LOG_ALL_REQUESTS && !DEBUG_MODE) {
        return true;
    }
    
    $entry = array_merge([
        'timestamp' => date('Y-m-d H:i:s')
    ], $data);
    
    return appendToJsonFile(FILE_ACCESS_LOG, $entry, 10000);
}

/**
 * Limpa logs antigos
 */
function cleanOldLogs($days = 30) {
    $cutoff = date('Y-m-d', strtotime("-{$days} days"));
    
    // Limpa bot log
    $botLogs = readJsonFile(FILE_BOT_LOG, []);
    $botLogs = array_filter($botLogs, function($log) use ($cutoff) {
        return ($log['date'] ?? $log['timestamp'] ?? '') >= $cutoff;
    });
    writeJsonFile(FILE_BOT_LOG, array_values($botLogs));
    
    // Limpa access log
    $accessLogs = readJsonFile(FILE_ACCESS_LOG, []);
    $accessLogs = array_filter($accessLogs, function($log) use ($cutoff) {
        return substr($log['timestamp'] ?? '', 0, 10) >= $cutoff;
    });
    writeJsonFile(FILE_ACCESS_LOG, array_values($accessLogs));
    
    return true;
}

/**
 * ============================================
 * FUNÇÕES DE IP
 * ============================================
 */

/**
 * Obtém IPs bloqueados
 */
function getBlockedIPs() {
    return readJsonFile(FILE_BLOCKED_IPS, []);
}

/**
 * Adiciona IP à lista de bloqueio
 */
function blockIP($ip, $reason = 'Manual') {
    $blocked = getBlockedIPs();
    
    // Verifica se já está bloqueado
    foreach ($blocked as $entry) {
        if (is_array($entry) && $entry['ip'] === $ip) {
            return true;
        }
        if (is_string($entry) && $entry === $ip) {
            return true;
        }
    }
    
    $blocked[] = [
        'ip' => $ip,
        'reason' => $reason,
        'date' => date('Y-m-d H:i:s')
    ];
    
    return writeJsonFile(FILE_BLOCKED_IPS, $blocked);
}

/**
 * Remove IP da lista de bloqueio
 */
function unblockIP($ip) {
    $blocked = getBlockedIPs();
    $blocked = array_filter($blocked, function($entry) use ($ip) {
        if (is_array($entry)) {
            return $entry['ip'] !== $ip;
        }
        return $entry !== $ip;
    });
    
    return writeJsonFile(FILE_BLOCKED_IPS, array_values($blocked));
}

/**
 * Verifica se IP está bloqueado
 */
function isIPBlocked($ip) {
    $blocked = getBlockedIPs();
    
    foreach ($blocked as $entry) {
        $blockedIP = is_array($entry) ? $entry['ip'] : $entry;
        
        // Suporta ranges CIDR
        if (strpos($blockedIP, '/') !== false) {
            if (ipInRange($ip, $blockedIP)) {
                return true;
            }
        } elseif ($blockedIP === $ip) {
            return true;
        }
    }
    
    return false;
}

/**
 * Obtém IPs da whitelist
 */
function getWhitelistIPs() {
    return readJsonFile(FILE_WHITELIST_IPS, []);
}

/**
 * Adiciona IP à whitelist
 */
function whitelistIP($ip, $note = '') {
    $whitelist = getWhitelistIPs();
    
    // Verifica se já está na whitelist
    foreach ($whitelist as $entry) {
        $whiteIP = is_array($entry) ? $entry['ip'] : $entry;
        if ($whiteIP === $ip) {
            return true;
        }
    }
    
    $whitelist[] = [
        'ip' => $ip,
        'note' => $note,
        'date' => date('Y-m-d H:i:s')
    ];
    
    return writeJsonFile(FILE_WHITELIST_IPS, $whitelist);
}

/**
 * Remove IP da whitelist
 */
function removeFromWhitelist($ip) {
    $whitelist = getWhitelistIPs();
    $whitelist = array_filter($whitelist, function($entry) use ($ip) {
        $whiteIP = is_array($entry) ? $entry['ip'] : $entry;
        return $whiteIP !== $ip;
    });
    
    return writeJsonFile(FILE_WHITELIST_IPS, array_values($whitelist));
}

/**
 * Verifica se IP está na whitelist
 */
function isIPWhitelisted($ip) {
    $whitelist = getWhitelistIPs();
    
    foreach ($whitelist as $entry) {
        $whiteIP = is_array($entry) ? $entry['ip'] : $entry;
        if ($whiteIP === $ip) {
            return true;
        }
    }
    
    return false;
}

/**
 * Verifica se IP está em range CIDR
 */
function ipInRange($ip, $cidr) {
    list($subnet, $mask) = explode('/', $cidr);
    
    $ip = ip2long($ip);
    $subnet = ip2long($subnet);
    $mask = -1 << (32 - $mask);
    
    $subnet &= $mask;
    
    return ($ip & $mask) === $subnet;
}

/**
 * ============================================
 * FUNÇÕES DE DETECÇÃO DE BOT
 * ============================================
 */

/**
 * Lista de ranges de IP de datacenters conhecidos (bots vem daqui)
 * Inclui: Google, Facebook, TikTok, AWS, Azure, Cloudflare Workers, etc.
 */
function getDatacenterIPRanges() {
    return [
        // Google (Googlebot, Google Ads Review)
        '66.249.64.0/19', '66.249.80.0/20', '64.233.160.0/19', '72.14.192.0/18',
        '209.85.128.0/17', '216.239.32.0/19', '216.58.192.0/19', '172.217.0.0/16',
        '108.177.0.0/17', '142.250.0.0/15', '34.64.0.0/10', '35.190.0.0/17',
        
        // Facebook / Meta (facebookexternalhit)
        '31.13.24.0/21', '31.13.64.0/18', '45.64.40.0/22', '66.220.144.0/20',
        '69.63.176.0/20', '69.171.224.0/19', '74.119.76.0/22', '103.4.96.0/22',
        '129.134.0.0/16', '157.240.0.0/16', '173.252.64.0/18', '179.60.192.0/22',
        '185.60.216.0/22', '204.15.20.0/22',
        
        // TikTok / ByteDance
        '34.64.0.0/10', '34.96.0.0/12', '35.186.0.0/16', '35.190.0.0/16',
        '130.211.0.0/16', '104.196.0.0/14', '162.216.148.0/22', '192.133.76.0/22',
        
        // AWS (EC2, Lambda - bots automatizados)
        '3.0.0.0/8', '13.32.0.0/12', '18.64.0.0/10', '34.192.0.0/10',
        '35.152.0.0/13', '44.192.0.0/10', '52.0.0.0/10', '54.64.0.0/10',
        '99.77.128.0/17', '184.72.0.0/13',
        
        // Azure
        '13.64.0.0/11', '20.0.0.0/8', '40.64.0.0/10', '52.224.0.0/11',
        '65.52.0.0/14', '70.37.0.0/16', '104.40.0.0/13', '137.116.0.0/15',
        '168.61.0.0/16', '191.232.0.0/13',
        
        // Cloudflare Workers
        '104.16.0.0/12', '172.64.0.0/13', '173.245.48.0/20', '103.21.244.0/22',
        '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
        '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        
        // DigitalOcean
        '104.131.0.0/16', '159.65.0.0/16', '165.22.0.0/16', '167.99.0.0/16',
        '178.62.0.0/15', '188.166.0.0/16', '206.189.0.0/16', '68.183.0.0/16',
        
        // Hetzner
        '78.46.0.0/15', '88.198.0.0/16', '138.201.0.0/16', '144.76.0.0/16',
        '148.251.0.0/16', '176.9.0.0/16', '178.63.0.0/16', '188.40.0.0/16',
        
        // OVH
        '51.38.0.0/16', '51.68.0.0/16', '51.75.0.0/16', '51.77.0.0/16',
        '51.79.0.0/16', '51.89.0.0/16', '51.91.0.0/16', '54.36.0.0/16',
        
        // Vultr
        '45.32.0.0/16', '45.63.0.0/16', '45.76.0.0/16', '45.77.0.0/16',
        '66.42.0.0/16', '108.61.0.0/16', '149.28.0.0/16', '207.148.0.0/16',
        
        // Linode
        '45.33.0.0/16', '45.56.0.0/16', '45.79.0.0/16', '50.116.0.0/16',
        '66.175.208.0/20', '69.164.192.0/18', '72.14.176.0/20', '74.207.224.0/19',
        
        // Google Cloud Platform
        '34.80.0.0/12', '34.96.0.0/12', '34.104.0.0/13', '34.124.0.0/14',
        '35.184.0.0/13', '35.192.0.0/11', '104.196.0.0/14', '107.167.160.0/19',
    ];
}

/**
 * Verifica se IP é de datacenter
 */
function isDatacenterIP($ip) {
    // IPv6 - alguns podem ser de datacenter mas é mais difícil detectar
    if (strpos($ip, ':') !== false) {
        return false; // Assume que IPv6 residencial é ok
    }
    
    $ipLong = ip2long($ip);
    if ($ipLong === false) {
        return false;
    }
    
    foreach (getDatacenterIPRanges() as $cidr) {
        if (strpos($cidr, '/') === false) continue;
        
        list($subnet, $mask) = explode('/', $cidr);
        $subnetLong = ip2long($subnet);
        
        if ($subnetLong === false) continue;
        
        $maskLong = -1 << (32 - (int)$mask);
        $subnetLong &= $maskLong;
        
        if (($ipLong & $maskLong) === $subnetLong) {
            return true;
        }
    }
    
    return false;
}

/**
 * Verifica ASN do IP (para detectar hosting/datacenter)
 */
function getIPASN($ip) {
    $cacheFile = CACHE_DIR . 'asn_' . md5($ip) . '.json';
    
    // Cache de 24h
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (isset($cached['expires']) && $cached['expires'] > time()) {
            return $cached['asn'] ?? null;
        }
    }
    
    // Consulta API gratuita de ASN
    $context = stream_context_create(['http' => ['timeout' => 2]]);
    $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=as,org,hosting", false, $context);
    
    if ($response) {
        $data = json_decode($response, true);
        $result = [
            'asn' => $data['as'] ?? null,
            'org' => $data['org'] ?? null,
            'hosting' => $data['hosting'] ?? false,
            'expires' => time() + 86400
        ];
        @file_put_contents($cacheFile, json_encode($result));
        return $result;
    }
    
    return null;
}

/**
 * Verifica se IP é de hosting/datacenter via ASN
 */
function isHostingIP($ip) {
    $asn = getIPASN($ip);
    if ($asn && isset($asn['hosting']) && $asn['hosting'] === true) {
        return true;
    }
    return false;
}

/**
 * Lista de User-Agents de bots conhecidos
 */
function getBotUserAgents() {
    return [
        // Crawlers principais
        'googlebot', 'bingbot', 'yandexbot', 'baiduspider', 'duckduckbot',
        'slurp', 'sogou', 'exabot', 'facebot', 'facebookexternalhit',
        
        // Bots de ads
        'adsbot', 'mediapartners-google', 'googleadsense', 'adidxbot',
        'bingpreview', 'msnbot',
        
        // Social media
        'twitterbot', 'linkedinbot', 'pinterest', 'whatsapp', 'telegrambot',
        'slackbot', 'discordbot', 'redditbot', 'tumblr',
        
        // SEO e análise
        'semrushbot', 'ahrefsbot', 'mj12bot', 'dotbot', 'rogerbot',
        'screaming frog', 'seokicks', 'sistrix', 'blexbot',
        
        // Segurança
        'nessus', 'nikto', 'sqlmap', 'openvas', 'w3af',
        'acunetix', 'netsparker', 'qualys', 'burp',
        
        // Outros bots
        'bot', 'spider', 'crawler', 'scraper', 'curl', 'wget', 'python',
        'java', 'perl', 'ruby', 'go-http', 'apache-httpclient',
        'httpclient', 'okhttp', 'axios', 'node-fetch', 'headless',
        'phantom', 'selenium', 'puppeteer', 'playwright', 'webdriver',
        
        // CDNs e proxies
        'cloudflare', 'fastly', 'akamai', 'cloudfront',
        
        // Monitoramento
        'uptimerobot', 'pingdom', 'statuscake', 'site24x7', 'newrelic',
        'datadog', 'gtmetrix', 'pagespeed',
        
        // Arquivamento
        'archive.org_bot', 'wayback', 'ia_archiver',
        
        // Preview
        'preview', 'thumbnail', 'snapshot', 'prerender'
    ];
}

/**
 * Detecta se é bot pelo User-Agent
 */
function isBotByUserAgent($userAgent) {
    if (empty($userAgent)) {
        return BLOCK_EMPTY_UA;
    }
    
    if (strlen($userAgent) < MIN_UA_LENGTH) {
        return true;
    }
    
    $userAgentLower = strtolower($userAgent);
    
    foreach (getBotUserAgents() as $bot) {
        if (strpos($userAgentLower, $bot) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Detecta bot de forma completa
 */
function detectBot($ip, $userAgent) {
    // 1. Verifica whitelist primeiro
    if (isIPWhitelisted($ip)) {
        return ['isBot' => false, 'reason' => 'Whitelist'];
    }
    
    // 2. Verifica blacklist
    if (isIPBlocked($ip)) {
        return ['isBot' => true, 'reason' => 'IP bloqueado'];
    }
    
    // 3. Verifica User-Agent
    if (isBotByUserAgent($userAgent)) {
        return ['isBot' => true, 'reason' => 'User-Agent de bot'];
    }
    
    // 4. Verifica geolocalização
    if (GEO_BLOCKING_ENABLED && !isFromBrazil($ip)) {
        return ['isBot' => true, 'reason' => 'Fora do Brasil'];
    }
    
    return ['isBot' => false, 'reason' => ''];
}

/**
 * ============================================
 * FUNÇÕES UTILITÁRIAS
 * ============================================
 */

/**
 * Formata número para exibição
 */
function formatNumber($number) {
    if ($number >= 1000000) {
        return number_format($number / 1000000, 1) . 'M';
    }
    if ($number >= 1000) {
        return number_format($number / 1000, 1) . 'K';
    }
    return number_format($number);
}

/**
 * Formata valor monetário
 */
function formatMoney($value, $currency = 'R$') {
    return $currency . ' ' . number_format($value, 2, ',', '.');
}

/**
 * Formata data relativa
 */
function timeAgo($timestamp) {
    if (is_string($timestamp)) {
        $timestamp = strtotime($timestamp);
    }
    
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'agora';
    }
    if ($diff < 3600) {
        $min = floor($diff / 60);
        return "{$min} min atrás";
    }
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "{$hours}h atrás";
    }
    if ($diff < 604800) {
        $days = floor($diff / 86400);
        return "{$days}d atrás";
    }
    
    return date('d/m/Y', $timestamp);
}

/**
 * Gera slug único
 */
function generateSlug($text, $existingSlugs = []) {
    // Remove acentos
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    
    // Converte para minúsculas e substitui espaços
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text)));
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Garante unicidade
    $originalSlug = $slug;
    $counter = 1;
    
    while (in_array($slug, $existingSlugs)) {
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

/**
 * Obtem plataforma pelo UTM source OU pelos parametros automaticos (gclid, fbclid, ttclid, etc)
 * Melhoria V10.7: Detecta automaticamente mesmo sem utm_source configurado
 * 
 * @param string $utmSource Valor do utm_source
 * @return string Nome da plataforma
 */
function detectPlatform($utmSource = '') {
    $utmSource = strtolower($utmSource ?? '');
    
    // 1. Primeiro tenta detectar pelo utm_source
    $platforms = [
        'tiktok' => ['tiktok', 'tt', 'bytedance'],
        'facebook' => ['facebook', 'fb', 'meta', 'instagram', 'ig'],
        'google' => ['google', 'gads', 'adwords', 'youtube', 'yt'],
        'kwai' => ['kwai', 'kuaishou'],
        'taboola' => ['taboola'],
        'outbrain' => ['outbrain'],
        'twitter' => ['twitter', 'x.com'],
        'linkedin' => ['linkedin'],
        'pinterest' => ['pinterest'],
        'snapchat' => ['snapchat', 'snap']
    ];
    
    foreach ($platforms as $platform => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($utmSource, $keyword) !== false) {
                return $platform;
            }
        }
    }
    
    // 2. Se nao encontrou pelo utm_source, tenta pelos parametros automaticos das plataformas
    // Esses parametros sao adicionados automaticamente pelas plataformas de ads
    $clickParams = [
        'gclid' => 'google',      // Google Ads
        'gbraid' => 'google',     // Google Ads (iOS)
        'wbraid' => 'google',     // Google Ads (web-to-app)
        'gad_source' => 'google', // Google Ads source
        'fbclid' => 'facebook',   // Facebook/Meta
        'ttclid' => 'tiktok',     // TikTok
        'twclid' => 'twitter',    // Twitter/X
        'li_fat_id' => 'linkedin', // LinkedIn
        'msclkid' => 'bing',      // Microsoft/Bing Ads
        'kwai_click_id' => 'kwai', // Kwai
        'ScCid' => 'snapchat',    // Snapchat
        'epik' => 'pinterest',    // Pinterest
        'ob_click_id' => 'outbrain', // Outbrain
        'tblci' => 'taboola',     // Taboola
    ];
    
    foreach ($clickParams as $param => $platform) {
        // Verifica em $_GET, $_POST e $_REQUEST
        if (!empty($_GET[$param]) || !empty($_POST[$param]) || !empty($_REQUEST[$param])) {
            return $platform;
        }
    }
    
    // 3. Tenta detectar pelo Referer
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referer)) {
        $refererPatterns = [
            'google' => ['google.com', 'google.com.br', 'googleapis.com'],
            'facebook' => ['facebook.com', 'fb.com', 'instagram.com', 'fb.me'],
            'tiktok' => ['tiktok.com', 'tiktokv.com', 'bytedance.com'],
            'twitter' => ['twitter.com', 'x.com', 't.co'],
            'linkedin' => ['linkedin.com'],
            'pinterest' => ['pinterest.com'],
            'kwai' => ['kwai.com', 'kwaipro.com'],
            'snapchat' => ['snapchat.com', 'snap.com'],
            'taboola' => ['taboola.com'],
            'outbrain' => ['outbrain.com'],
            'bing' => ['bing.com']
        ];
        
        foreach ($refererPatterns as $platform => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($referer, $pattern) !== false) {
                    return $platform;
                }
            }
        }
    }
    
    return 'other';
}

/**
 * Valida URL
 */
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Obtém domínio de URL
 */
function getDomainFromUrl($url) {
    $parsed = parse_url($url);
    return $parsed['host'] ?? '';
}

/**
 * Responde com JSON
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Debug log (apenas em modo debug)
 */
function debugLog($message, $data = null) {
    if (!DEBUG_MODE) {
        return;
    }
    
    $logFile = LOGS_DIR . 'debug_' . date('Y-m-d') . '.log';
    $entry = '[' . date('H:i:s') . '] ' . $message;
    
    if ($data !== null) {
        $entry .= ' ' . json_encode($data);
    }
    
    file_put_contents($logFile, $entry . PHP_EOL, FILE_APPEND);
}

// ============================================

/**
 * Obtem dados para grafico de cliques por dia
 */
function getChartDataByDay($days = 30) {
    $stats = getStats();
    $byDate = $stats['by_date'] ?? [];
    
    $result = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $data = $byDate[$date] ?? ['clicks' => 0, 'blocks' => 0, 'passes' => 0];
        $result[] = [
            'date' => $date,
            'label' => date('d/m', strtotime($date)),
            'clicks' => $data['clicks'],
            'blocks' => $data['blocks'],
            'passes' => $data['passes']
        ];
    }
    
    return $result;
}

/**
 * Obtem dados para grafico de cliques por hora
 */
function getChartDataByHour() {
    $hourlyStats = readJsonFile(FILE_HOURLY_STATS, []);
    $today = date('Y-m-d');
    
    $result = [];
    for ($h = 0; $h < 24; $h++) {
        $hour = str_pad($h, 2, '0', STR_PAD_LEFT);
        $key = $today . '_' . $hour;
        $data = $hourlyStats[$key] ?? ['clicks' => 0, 'blocks' => 0, 'passes' => 0];
        $result[] = [
            'hour' => $hour . ':00',
            'clicks' => $data['clicks'],
            'blocks' => $data['blocks'],
            'passes' => $data['passes']
        ];
    }
    
    return $result;
}

/**
 * Registra clique por hora (para mapa de calor)
 */
function recordHourlyClick($isBot = false) {
    $hourlyStats = readJsonFile(FILE_HOURLY_STATS, []);
    $key = date('Y-m-d_H');
    
    if (!isset($hourlyStats[$key])) {
        $hourlyStats[$key] = ['clicks' => 0, 'blocks' => 0, 'passes' => 0];
    }
    
    $hourlyStats[$key]['clicks']++;
    $hourlyStats[$key][$isBot ? 'blocks' : 'passes']++;
    
    // Limpa dados antigos (mais de 7 dias)
    $cutoff = date('Y-m-d', strtotime('-7 days'));
    foreach (array_keys($hourlyStats) as $k) {
        if (substr($k, 0, 10) < $cutoff) {
            unset($hourlyStats[$k]);
        }
    }
    
    return writeJsonFile(FILE_HOURLY_STATS, $hourlyStats);
}

/**
 * Obtem dados para grafico de pizza (bots vs humanos)
 */
function getChartDataBotVsHuman() {
    $stats = getStats();
    return [
        ['label' => 'Humanos', 'value' => $stats['total_passes'] ?? 0, 'color' => '#22c55e'],
        ['label' => 'Bots', 'value' => $stats['total_blocks'] ?? 0, 'color' => '#ef4444']
    ];
}

/**
 * Obtem dados para grafico por plataforma
 */
function getChartDataByPlatform() {
    $stats = getStats();
    $byPlatform = $stats['by_platform'] ?? [];
    
    $colors = [
        'tiktok' => '#000000',
        'facebook' => '#1877f2',
        'google' => '#4285f4',
        'kwai' => '#ff5722',
        'other' => '#6b7280'
    ];
    
    $result = [];
    foreach ($byPlatform as $platform => $data) {
        $result[] = [
            'label' => ucfirst($platform),
            'clicks' => $data['clicks'] ?? 0,
            'passes' => $data['passes'] ?? 0,
            'blocks' => $data['blocks'] ?? 0,
            'color' => $colors[$platform] ?? '#6b7280'
        ];
    }
    
    // Ordena por cliques
    usort($result, function($a, $b) {
        return $b['clicks'] - $a['clicks'];
    });
    
    return $result;
}

/**
 * Obtem mapa de calor (heatmap) por dia/hora
 */
function getHeatmapData() {
    $hourlyStats = readJsonFile(FILE_HOURLY_STATS, []);
    
    $heatmap = [];
    for ($d = 6; $d >= 0; $d--) {
        $date = date('Y-m-d', strtotime("-{$d} days"));
        $dayName = date('D', strtotime($date));
        
        $dayData = [];
        for ($h = 0; $h < 24; $h++) {
            $hour = str_pad($h, 2, '0', STR_PAD_LEFT);
            $key = $date . '_' . $hour;
            $dayData[$hour] = $hourlyStats[$key]['clicks'] ?? 0;
        }
        
        $heatmap[] = [
            'day' => $dayName,
            'date' => $date,
            'hours' => $dayData
        ];
    }
    
    return $heatmap;
}

// ============================================
// V10.4 - FUNCOES DE WEBHOOKS
// ============================================

/**
 * Envia notificacao para Telegram
 */
function sendTelegramNotification($message) {
    if (!WEBHOOK_TELEGRAM_ENABLED) {
        return false;
    }
    
    $botToken = WEBHOOK_TELEGRAM_BOT_TOKEN;
    $chatId = WEBHOOK_TELEGRAM_CHAT_ID;
    
    if (empty($botToken) || empty($chatId)) {
        return false;
    }
    
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data),
            'timeout' => 5
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    return $result !== false;
}

/**
 * Envia notificacao para Discord
 */
function sendDiscordNotification($message, $title = 'COMMANDER Alert') {
    if (!WEBHOOK_DISCORD_ENABLED) {
        return false;
    }
    
    $webhookUrl = WEBHOOK_DISCORD_URL;
    
    if (empty($webhookUrl)) {
        return false;
    }
    
    $data = [
        'embeds' => [[
            'title' => $title,
            'description' => $message,
            'color' => 15158332, // Vermelho
            'timestamp' => date('c')
        ]]
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data),
            'timeout' => 5
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($webhookUrl, false, $context);
    
    return $result !== false;
}

/**
 * Verifica e envia alerta de taxa de bloqueio alta
 */
function checkAndAlertBlockRate() {
    $stats = getStats();
    $totalClicks = $stats['total_clicks'] ?? 0;
    $totalBlocks = $stats['total_blocks'] ?? 0;
    
    if ($totalClicks < 100) {
        return; // Muito poucos cliques para avaliar
    }
    
    $blockRate = ($totalBlocks / $totalClicks) * 100;
    
    if ($blockRate > WEBHOOK_ALERT_THRESHOLD) {
        $message = "ALERTA: Taxa de bloqueio alta!\n\n";
        $message .= "Taxa atual: " . number_format($blockRate, 1) . "%\n";
        $message .= "Total cliques: {$totalClicks}\n";
        $message .= "Total bloqueados: {$totalBlocks}\n";
        $message .= "Threshold: " . WEBHOOK_ALERT_THRESHOLD . "%";
        
        // Evita spam - verifica ultimo alerta
        $lastAlertFile = CACHE_DIR . 'last_block_alert.txt';
        $lastAlert = file_exists($lastAlertFile) ? (int)file_get_contents($lastAlertFile) : 0;
        
        if (time() - $lastAlert > 3600) { // Maximo 1 alerta por hora
            sendTelegramNotification($message);
            sendDiscordNotification($message, 'Taxa de Bloqueio Alta');
            file_put_contents($lastAlertFile, time());
        }
    }
}

// ============================================
// V10.4 - ROTACAO DE URLS / A/B TESTING
// ============================================

/**
 * Obtem configuracao de rotacao de URLs de uma campanha
 */
function getUrlRotationConfig($campaignId) {
    $rotations = readJsonFile(FILE_URL_ROTATION, []);
    return $rotations[$campaignId] ?? null;
}

/**
 * Salva configuracao de rotacao de URLs
 */
function saveUrlRotationConfig($campaignId, $config) {
    $rotations = readJsonFile(FILE_URL_ROTATION, []);
    $rotations[$campaignId] = $config;
    return writeJsonFile(FILE_URL_ROTATION, $rotations);
}

/**
 * Seleciona URL baseado na rotacao configurada
 */
function selectRotatedUrl($campaignId, $urls) {
    if (!URL_ROTATION_ENABLED || empty($urls) || count($urls) < 2) {
        return $urls[0] ?? null;
    }
    
    $config = getUrlRotationConfig($campaignId);
    $mode = $config['mode'] ?? URL_ROTATION_MODE;
    
    switch ($mode) {
        case 'weight':
            return selectUrlByWeight($urls, $config['weights'] ?? []);
        
        case 'sequential':
            return selectUrlSequential($campaignId, $urls);
        
        case 'random':
        default:
            return $urls[array_rand($urls)];
    }
}

/**
 * Seleciona URL por peso
 */
function selectUrlByWeight($urls, $weights) {
    $totalWeight = array_sum($weights);
    if ($totalWeight <= 0) {
        return $urls[0];
    }
    
    $random = mt_rand(1, $totalWeight);
    $current = 0;
    
    foreach ($urls as $index => $url) {
        $current += $weights[$index] ?? 0;
        if ($random <= $current) {
            return $url;
        }
    }
    
    return $urls[0];
}

/**
 * Seleciona URL sequencial
 */
function selectUrlSequential($campaignId, $urls) {
    $counterFile = CACHE_DIR . 'url_counter_' . md5($campaignId) . '.txt';
    $counter = file_exists($counterFile) ? (int)file_get_contents($counterFile) : 0;
    
    $selectedIndex = $counter % count($urls);
    file_put_contents($counterFile, $counter + 1);
    
    return $urls[$selectedIndex];
}

/**
 * Registra clique em URL rotacionada (para estatisticas A/B)
 */
function recordRotatedUrlClick($campaignId, $urlIndex) {
    $config = getUrlRotationConfig($campaignId);
    if (!$config) {
        $config = ['stats' => []];
    }
    
    if (!isset($config['stats'][$urlIndex])) {
        $config['stats'][$urlIndex] = ['clicks' => 0, 'conversions' => 0];
    }
    
    $config['stats'][$urlIndex]['clicks']++;
    saveUrlRotationConfig($campaignId, $config);
}

/**
 * Registra conversao em URL rotacionada
 */
function recordRotatedUrlConversion($campaignId, $urlIndex) {
    $config = getUrlRotationConfig($campaignId);
    if (!$config || !isset($config['stats'][$urlIndex])) {
        return;
    }
    
    $config['stats'][$urlIndex]['conversions']++;
    saveUrlRotationConfig($campaignId, $config);
}

// ============================================
// V10.4 - PAUSA AUTOMATICA DE CAMPANHAS
// ============================================

/**
 * Verifica se campanha deve ser pausada automaticamente
 */
function checkAutoPause($campaignId) {
    if (!AUTO_PAUSE_ENABLED) {
        return false;
    }
    
    $stats = getStats();
    $campaignStats = $stats['by_campaign'][$campaignId] ?? null;
    
    if (!$campaignStats) {
        return false;
    }
    
    $clicks = $campaignStats['clicks'] ?? 0;
    $blocks = $campaignStats['blocks'] ?? 0;
    
    // Precisa de minimo de cliques
    if ($clicks < AUTO_PAUSE_MIN_CLICKS) {
        return false;
    }
    
    $blockRate = ($blocks / $clicks) * 100;
    
    if ($blockRate >= AUTO_PAUSE_BLOCK_THRESHOLD) {
        // Dispara pausa automatica
        $campaign = getCampaignById($campaignId);
        if ($campaign) {
            // Envia alerta
            $message = "PAUSA AUTOMATICA ATIVADA\n\n";
            $message .= "Campanha: " . ($campaign['name'] ?? $campaignId) . "\n";
            $message .= "Taxa de bloqueio: " . number_format($blockRate, 1) . "%\n";
            $message .= "Cliques: {$clicks}\n";
            $message .= "Bloqueados: {$blocks}";
            
            sendTelegramNotification($message);
            sendDiscordNotification($message, 'Pausa Automatica');
            
            return true;
        }
    }
    
    return false;
}

/**
 * Obtem resumo para dashboard
 */
function getDashboardSummary() {
    $stats = getStats();
    $today = date('Y-m-d');
    $todayStats = $stats['by_date'][$today] ?? ['clicks' => 0, 'blocks' => 0, 'passes' => 0];
    
    $totalClicks = $stats['total_clicks'] ?? 0;
    $totalBlocks = $stats['total_blocks'] ?? 0;
    $totalPasses = $stats['total_passes'] ?? 0;
    
    $blockRate = $totalClicks > 0 ? ($totalBlocks / $totalClicks) * 100 : 0;
    $passRate = $totalClicks > 0 ? ($totalPasses / $totalClicks) * 100 : 0;
    
    return [
        'total' => [
            'clicks' => $totalClicks,
            'blocks' => $totalBlocks,
            'passes' => $totalPasses,
            'block_rate' => round($blockRate, 1),
            'pass_rate' => round($passRate, 1)
        ],
        'today' => [
            'clicks' => $todayStats['clicks'],
            'blocks' => $todayStats['blocks'],
            'passes' => $todayStats['passes']
        ],
        'campaigns' => count(getCampaigns()),
        'blocked_ips' => count(getBlockedIPs())
    ];
}

// ============================================
// V10.5 - STEALTH MODE (TECNICAS AVANCADAS)
// ============================================

/**
 * Verifica se visitante passou pelo "Behavioral Gate"
 * Só libera black page após comportamento humano comprovado
 */
function checkBehavioralGate($visitorId, $campaignId) {
    $gateFile = CACHE_DIR . 'gate_' . md5($visitorId . $campaignId) . '.json';
    
    if (!file_exists($gateFile)) {
        return ['passed' => false, 'stage' => 0, 'reason' => 'first_visit'];
    }
    
    $data = json_decode(file_get_contents($gateFile), true);
    
    // Criterios para passar o gate (use constantes do config se disponiveis)
    $minScrolls = defined('BEHAVIORAL_MIN_SCROLLS') ? BEHAVIORAL_MIN_SCROLLS : 2;
    $minTimeOnPage = defined('BEHAVIORAL_MIN_TIME_MS') ? BEHAVIORAL_MIN_TIME_MS : 3000; // 3 segundos
    $minMouseMoves = defined('BEHAVIORAL_MIN_MOUSE_MOVES') ? BEHAVIORAL_MIN_MOUSE_MOVES : 5;
    $minVisits = defined('BEHAVIORAL_MIN_VISITS') ? BEHAVIORAL_MIN_VISITS : 1; // 1 visita ja basta
    
    // Verifica se passou - precisa de scrolls OU mouse E tempo minimo
    $hasInteraction = (
        ($data['scrolls'] ?? 0) >= $minScrolls ||
        ($data['mouse_moves'] ?? 0) >= $minMouseMoves
    );
    
    $hasTime = ($data['time_on_page'] ?? 0) >= $minTimeOnPage;
    $hasVisits = ($data['visits'] ?? 0) >= $minVisits;
    
    $passed = $hasInteraction && $hasTime && $hasVisits;
    
    return [
        'passed' => $passed,
        'stage' => $data['visits'] ?? 1,
        'data' => $data,
        'debug' => [
            'scrolls' => $data['scrolls'] ?? 0,
            'mouse' => $data['mouse_moves'] ?? 0,
            'time' => $data['time_on_page'] ?? 0,
            'visits' => $data['visits'] ?? 0,
            'required' => [
                'scrolls_or_mouse' => $minScrolls . ' scrolls OR ' . $minMouseMoves . ' mouse',
                'time' => $minTimeOnPage . 'ms',
                'visits' => $minVisits
            ]
        ]
    ];
}

/**
 * Atualiza dados comportamentais do visitante
 */
function updateBehavioralData($visitorId, $campaignId, $behaviorData) {
    $gateFile = CACHE_DIR . 'gate_' . md5($visitorId . $campaignId) . '.json';
    
    $data = ['visits' => 0, 'scrolls' => 0, 'time_on_page' => 0, 'mouse_moves' => 0, 'first_seen' => time()];
    
    if (file_exists($gateFile)) {
        $data = json_decode(file_get_contents($gateFile), true) ?: $data;
    }
    
    // Acumula dados
    $data['visits'] = ($data['visits'] ?? 0) + 1;
    $data['scrolls'] = max($data['scrolls'] ?? 0, $behaviorData['scrolls'] ?? 0);
    $data['time_on_page'] = max($data['time_on_page'] ?? 0, $behaviorData['time_on_page'] ?? 0);
    $data['mouse_moves'] = max($data['mouse_moves'] ?? 0, $behaviorData['mouse_moves'] ?? 0);
    $data['last_seen'] = time();
    
    file_put_contents($gateFile, json_encode($data));
    
    return $data;
}

/**
 * Gera ID unico do visitante baseado em fingerprint leve
 * Nao usa cookies - usa dados do request
 */
function generateVisitorId() {
    $components = [
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    
    return md5(implode('|', $components));
}

/**
 * Delayed Transform - Muda a pagina gradualmente
 * Retorna qual "versao" da pagina mostrar
 */
function getPageVersion($visitorId, $campaignId, $isBot) {
    // Se for bot, sempre versao 0 (white pura)
    if ($isBot) {
        return ['version' => 0, 'type' => 'white_full'];
    }
    
    $gate = checkBehavioralGate($visitorId, $campaignId);
    
    // Primeira visita - white page
    if ($gate['stage'] === 0 || $gate['stage'] === 1) {
        return ['version' => 0, 'type' => 'white_full', 'message' => 'Primeira visita'];
    }
    
    // Segunda visita - white com "teaser"
    if ($gate['stage'] === 2 && !$gate['passed']) {
        return ['version' => 1, 'type' => 'white_teaser', 'message' => 'Mostrando teaser'];
    }
    
    // Passou no gate comportamental - black page
    if ($gate['passed']) {
        return ['version' => 2, 'type' => 'black_full', 'message' => 'Gate passed'];
    }
    
    // Ainda nao passou - continua white com teaser
    return ['version' => 1, 'type' => 'white_teaser', 'message' => 'Aguardando comportamento'];
}

/**
 * Coleta fingerprint de revisores conhecidos
 * Armazena para bloquear padroes similares
 */
function collectReviewerFingerprint($fingerprint, $indicators) {
    $fpFile = DATA_DIR . 'reviewer_fingerprints.json';
    $fps = readJsonFile($fpFile, []);
    
    // Indicadores de que e revisor:
    // - Tempo muito curto na pagina
    // - Sem interacao de mouse
    // - Padrao de navegacao suspeito
    
    $score = 0;
    if (($indicators['time_on_page'] ?? 0) < 3000) $score += 30;
    if (empty($indicators['mouse_moves'])) $score += 25;
    if (empty($indicators['scrolls'])) $score += 20;
    if (!empty($indicators['from_ads_panel'])) $score += 40;
    
    // Se score alto, provavelmente e revisor
    if ($score >= 50) {
        $fps[$fingerprint] = [
            'first_seen' => time(),
            'score' => $score,
            'indicators' => $indicators
        ];
        
        // Limita tamanho
        if (count($fps) > 1000) {
            $fps = array_slice($fps, -500, null, true);
        }
        
        writeJsonFile($fpFile, $fps);
        return true;
    }
    
    return false;
}

/**
 * Verifica se fingerprint e similar a revisores conhecidos
 */
function isSimilarToReviewer($fingerprint) {
    $fpFile = DATA_DIR . 'reviewer_fingerprints.json';
    $fps = readJsonFile($fpFile, []);
    
    // Match exato
    if (isset($fps[$fingerprint])) {
        return true;
    }
    
    // Match parcial (primeiros 8 chars do hash)
    $prefix = substr($fingerprint, 0, 8);
    foreach (array_keys($fps) as $known) {
        if (strpos($known, $prefix) === 0) {
            return true;
        }
    }
    
    return false;
}

/**
 * Time-based cloaking
 * Revisores trabalham em horario comercial
 */
function isReviewerWorkingHours() {
    $hour = (int)date('G');
    $dayOfWeek = (int)date('N'); // 1=segunda, 7=domingo
    
    // Horario comercial: segunda a sexta, 9h-18h (horario de Brasilia)
    // Nesses horarios, ser mais conservador
    if ($dayOfWeek <= 5 && $hour >= 9 && $hour <= 18) {
        return true;
    }
    
    return false;
}

/**
 * Calcula nivel de risco do visitante
 * Quanto maior, mais chance de ser revisor
 */
function calculateVisitorRiskScore($data) {
    $score = 0;
    
    // Horario comercial +15
    if (isReviewerWorkingHours()) {
        $score += 15;
    }
    
    // Primeira visita +20
    if (($data['visits'] ?? 1) <= 1) {
        $score += 20;
    }
    
    // Sem mouse movement +25
    if (($data['mouse_moves'] ?? 0) < 5) {
        $score += 25;
    }
    
    // Tempo muito curto +30
    if (($data['time_on_page'] ?? 0) < 3000) {
        $score += 30;
    }
    
    // Referrer suspeito +20
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($ref, 'business.facebook') !== false || 
        strpos($ref, 'ads.tiktok') !== false ||
        strpos($ref, 'ads.google') !== false) {
        $score += 20;
    }
    
    // Similar a revisor conhecido +40
    if (!empty($data['fingerprint']) && isSimilarToReviewer($data['fingerprint'])) {
        $score += 40;
    }
    
    return min($score, 100);
}

/**
 * Decisao final: mostrar black ou white?
 * Combina todas as tecnicas
 */
function stealthDecision($visitorId, $campaignId, $isBot, $advancedData = []) {
    // Bot detectado = sempre white
    if ($isBot) {
        return [
            'show' => 'white',
            'reason' => 'bot_detected',
            'risk_score' => 100
        ];
    }
    
    // Calcula risco
    $riskScore = calculateVisitorRiskScore($advancedData);
    
    // Risco muito alto = white
    if ($riskScore >= 70) {
        return [
            'show' => 'white',
            'reason' => 'high_risk_score',
            'risk_score' => $riskScore
        ];
    }
    
    // Verifica gate comportamental
    $gate = checkBehavioralGate($visitorId, $campaignId);
    
    // Nao passou no gate = white (ou teaser se config permitir)
    if (!$gate['passed']) {
        $showTeaser = ($gate['stage'] >= 2 && $riskScore < 40);
        return [
            'show' => $showTeaser ? 'white_teaser' : 'white',
            'reason' => 'gate_not_passed',
            'risk_score' => $riskScore,
            'gate_stage' => $gate['stage']
        ];
    }
    
    // Passou em tudo = black
    return [
        'show' => 'black',
        'reason' => 'all_checks_passed',
        'risk_score' => $riskScore,
        'gate_stage' => $gate['stage']
    ];
}

// ============================================
// V10.6 - MELHORIAS DE DETECCAO ANTI-BOT
// ============================================

/**
 * Lista EXPANDIDA de User-Agents de bots conhecidos
 * Inclui: Puppeteer, Playwright, Selenium, PhantomJS, Headless browsers, etc.
 */
function getBotUserAgentsExpanded() {
    return array_merge(getBotUserAgents(), [
        // Headless browsers
        'headlesschrome', 'headless', 'phantomjs', 'slimerjs', 'splash',
        'htmlunit', 'zombie.js', 'rhino', 'nashorn',
        
        // Automation frameworks
        'puppeteer', 'playwright', 'selenium', 'webdriver', 'chromedriver',
        'geckodriver', 'safaridriver', 'edgedriver', 'operadriver',
        'cypress', 'nightwatch', 'testcafe', 'protractor', 'webdriverio',
        'capybara', 'watir', 'mechanize',
        
        // HTTP clients/libraries
        'python-requests', 'python-urllib', 'go-http-client', 'guzzlehttp',
        'node-fetch', 'axios', 'got ', 'superagent', 'request/',
        'httpx', 'aiohttp', 'httplib', 'requests/', 'urllib/',
        'libwww-perl', 'lwp-', 'www-mechanize', 'mechanize/',
        'httpclient', 'apache-httpclient', 'okhttp', 'retrofit',
        'jersey/', 'resttemplate', 'feign/', 'unirest',
        
        // Scrapers conhecidos
        'scrapy', 'beautifulsoup', 'colly', 'goquery',
        'cheerio', 'jsdom', 'x-ray', 'osmosis',
        'simplehtmldom', 'goutte', 'php-curl', 'php/',
        
        // Cloud functions / serverless
        'vercel/', 'netlify/', 'cloudflare-worker', 'aws-lambda',
        'google-cloud-functions', 'azure-functions',
        
        // Anti-detect browsers (suspeitos)
        'multilogin', 'gologin', 'dolphin', 'indigo',
        'adspower', 'vmlogin', 'lalicat', 'undetected',
        
        // Outros bots especificos
        'facebookexternalhit/1.1', 'facebookexternalhit/1.0',
        'facebookcatalog', 'facebookplatform', 'facebookmessenger',
        'tiktokbot', 'bytespider', 'bytedance',
        'linkedinbot/1.0', 'slackbot-linkexpanding',
        
        // Keywords genericos
        'robot', 'fetch', 'scan', 'probe', 'crawl', 'check',
        'monitor', 'validate', 'analyzer', 'audit',
        'inspector', 'scanner', 'checker', 'verifier'
    ]);
}

/**
 * Detecta User-Agent de bot com lista expandida
 */
function isBotByUserAgentExpanded($userAgent) {
    if (empty($userAgent)) {
        return defined('BLOCK_EMPTY_UA') && BLOCK_EMPTY_UA;
    }
    
    $minLength = defined('MIN_UA_LENGTH') ? MIN_UA_LENGTH : 30;
    if (strlen($userAgent) < $minLength) {
        return true;
    }
    
    $userAgentLower = strtolower($userAgent);
    
    foreach (getBotUserAgentsExpanded() as $bot) {
        if (strpos($userAgentLower, strtolower($bot)) !== false) {
            return true;
        }
    }
    
    // Detecta padrao de versao muito limpo (ex: "Chrome/90.0.0.0")
    // Browsers reais tem build numbers mais complexos
    if (preg_match('/Chrome\/\d+\.0\.0\.0/i', $userAgent) && 
        !preg_match('/Chrome\/\d+\.\d+\.\d+\.\d+/i', $userAgent)) {
        return true;
    }
    
    return false;
}

/**
 * Detecta VPN/Proxy por headers HTTP
 * Muitos proxies adicionam headers reveladoras
 */
function detectVPNProxy() {
    $suspiciousHeaders = [
        'HTTP_VIA',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'HTTP_X_PROXY_CONNECTION',
        'HTTP_PROXY_CONNECTION',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_CF_CONNECTING_IP', // Cloudflare (pode ser legitimo)
        'HTTP_TRUE_CLIENT_IP',
        'HTTP_X_ORIGINATING_IP',
        'HTTP_X_REMOTE_IP',
        'HTTP_X_REMOTE_ADDR'
    ];
    
    $detected = [];
    foreach ($suspiciousHeaders as $header) {
        if (!empty($_SERVER[$header])) {
            $detected[$header] = $_SERVER[$header];
        }
    }
    
    // Detecta discrepancia entre IPs
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    
    if (!empty($forwardedFor) && !empty($remoteAddr)) {
        $firstForwarded = explode(',', $forwardedFor)[0];
        if (trim($firstForwarded) !== $remoteAddr) {
            $detected['ip_mismatch'] = true;
        }
    }
    
    return [
        'is_proxy' => count($detected) > 2,
        'headers' => $detected
    ];
}

/**
 * Analisa headers HTTP em busca de anomalias
 * Bots frequentemente tem headers ausentes ou fora de ordem
 */
function analyzeHTTPHeaders() {
    $score = 0;
    $issues = [];
    
    // Header Accept ausente ou anomalo
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (empty($accept)) {
        $score += 20;
        $issues[] = 'no_accept_header';
    } elseif ($accept === '*/*') {
        $score += 10;
        $issues[] = 'generic_accept';
    }
    
    // Accept-Language ausente
    if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $score += 15;
        $issues[] = 'no_accept_language';
    }
    
    // Accept-Encoding ausente
    if (empty($_SERVER['HTTP_ACCEPT_ENCODING'])) {
        $score += 10;
        $issues[] = 'no_accept_encoding';
    }
    
    // Connection header ausente (browsers sempre enviam)
    if (empty($_SERVER['HTTP_CONNECTION'])) {
        $score += 10;
        $issues[] = 'no_connection_header';
    }
    
    // Sec-Fetch headers (Chrome/Firefox modernos sempre enviam)
    $hasSecFetch = !empty($_SERVER['HTTP_SEC_FETCH_SITE']) ||
                   !empty($_SERVER['HTTP_SEC_FETCH_MODE']) ||
                   !empty($_SERVER['HTTP_SEC_FETCH_DEST']);
    
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isModernBrowser = preg_match('/Chrome\/[89]\d|Chrome\/1[0-2]\d|Firefox\/[89]\d|Firefox\/1[0-2]\d/i', $ua);
    
    if ($isModernBrowser && !$hasSecFetch) {
        $score += 25;
        $issues[] = 'missing_sec_fetch_headers';
    }
    
    // Sec-CH-UA headers (Chrome 89+)
    $isChrome89Plus = preg_match('/Chrome\/([89]\d|1[0-2]\d)/i', $ua);
    if ($isChrome89Plus && empty($_SERVER['HTTP_SEC_CH_UA'])) {
        $score += 20;
        $issues[] = 'missing_client_hints';
    }
    
    // Upgrade-Insecure-Requests (browsers enviam em HTTPS)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if ($isHttps && empty($_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'])) {
        $score += 5;
        $issues[] = 'no_upgrade_insecure';
    }
    
    return [
        'score' => $score,
        'issues' => $issues,
        'is_suspicious' => $score >= 30
    ];
}

/**
 * Sistema de scoring multi-camada para deteccao de bots
 * Combina multiplos sinais para decisao final
 */
function calculateBotScore($ip, $userAgent, $behaviorData = []) {
    $score = 0;
    $reasons = [];
    
    // 1. User-Agent Analysis (0-50 pontos)
    if (isBotByUserAgentExpanded($userAgent)) {
        $score += 50;
        $reasons[] = 'bot_user_agent';
    }
    
    // 2. IP Analysis (0-40 pontos)
    if (isDatacenterIP($ip)) {
        $score += 30;
        $reasons[] = 'datacenter_ip';
    }
    if (isHostingIP($ip)) {
        $score += 35;
        $reasons[] = 'hosting_ip';
    }
    
    // 3. HTTP Headers (0-30 pontos)
    $headerAnalysis = analyzeHTTPHeaders();
    $score += min($headerAnalysis['score'], 30);
    if ($headerAnalysis['is_suspicious']) {
        $reasons[] = 'suspicious_headers';
    }
    
    // 4. VPN/Proxy Detection (0-20 pontos)
    $vpnCheck = detectVPNProxy();
    if ($vpnCheck['is_proxy']) {
        $score += 20;
        $reasons[] = 'vpn_proxy_detected';
    }
    
    // 5. Behavioral Analysis (0-40 pontos)
    if (!empty($behaviorData)) {
        // Webdriver detectado
        if (!empty($behaviorData['webdriver'])) {
            $score += 50;
            $reasons[] = 'webdriver_detected';
        }
        
        // Sem plugins
        if (isset($behaviorData['plugins']) && $behaviorData['plugins'] == 0) {
            $score += 15;
            $reasons[] = 'no_plugins';
        }
        
        // Canvas bloqueado
        if (!empty($behaviorData['canvas_blocked'])) {
            $score += 20;
            $reasons[] = 'canvas_blocked';
        }
        
        // Eventos untrusted
        if (!empty($behaviorData['untrusted_events']) && $behaviorData['untrusted_events'] > 3) {
            $score += 25;
            $reasons[] = 'untrusted_events';
        }
        
        // Mouse em linha reta
        if (!empty($behaviorData['straight_mouse'])) {
            $score += 25;
            $reasons[] = 'straight_mouse_movement';
        }
        
        // Velocidade impossivel
        if (!empty($behaviorData['impossible_speed'])) {
            $score += 30;
            $reasons[] = 'impossible_click_speed';
        }
        
        // Automation detectada
        if (!empty($behaviorData['automation_detected'])) {
            $score += 50;
            $reasons[] = 'automation_framework';
        }
        
        // Honeypot triggered
        if (!empty($behaviorData['honeypot_triggered'])) {
            $score += 50;
            $reasons[] = 'honeypot_triggered';
        }
    }
    
    // 6. Timing Analysis (0-25 pontos)
    if (isReviewerWorkingHours()) {
        $score += 15;
        $reasons[] = 'reviewer_hours';
    }
    
    // 7. Referrer Analysis (0-20 pontos)
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $adsReferers = ['business.facebook', 'ads.tiktok', 'ads.google', 'adsmanager', 'business.tiktok'];
    foreach ($adsReferers as $adsRef) {
        if (stripos($ref, $adsRef) !== false) {
            $score += 20;
            $reasons[] = 'ads_panel_referer';
            break;
        }
    }
    
    // 8. Learned Bots (0-100 pontos)
    $learned = isLearnedBot($ip, $userAgent);
    if ($learned['isBot']) {
        $score += 100;
        $reasons[] = 'learned_bot';
    }
    
    return [
        'score' => min($score, 200),
        'is_bot' => $score >= 50,
        'confidence' => min(($score / 100) * 100, 100),
        'reasons' => $reasons
    ];
}

/**
 * Sistema de Honeypot
 * Cria links/campos invisiveis que so bots acessam
 */
function generateHoneypotHTML($campaignId = '') {
    $tokens = [
        bin2hex(random_bytes(8)),
        bin2hex(random_bytes(8))
    ];
    
    // Salva tokens validos
    $hpFile = (defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/cache/') . 'honeypot_tokens.json';
    $validTokens = [];
    if (file_exists($hpFile)) {
        $validTokens = json_decode(file_get_contents($hpFile), true) ?: [];
    }
    $validTokens[$tokens[0]] = ['created' => time(), 'type' => 'link', 'campaign' => $campaignId];
    $validTokens[$tokens[1]] = ['created' => time(), 'type' => 'field', 'campaign' => $campaignId];
    
    // Limpa tokens antigos (> 24h)
    $validTokens = array_filter($validTokens, function($t) {
        return $t['created'] > time() - 86400;
    });
    
    file_put_contents($hpFile, json_encode($validTokens));
    
    $html = '
<!-- Honeypot - invisible to humans -->
<a href="/?_hp=' . $tokens[0] . '" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:auto;" tabindex="-1" aria-hidden="true">Special Offer Click Here</a>
<form style="position:absolute;left:-9999px;top:-9999px;opacity:0;">
    <input type="text" name="' . $tokens[1] . '" value="" autocomplete="off" tabindex="-1" aria-hidden="true" />
</form>
';
    
    return $html;
}

/**
 * Verifica se honeypot foi acionado
 */
function checkHoneypot() {
    $hpFile = (defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/cache/') . 'honeypot_tokens.json';
    
    if (!file_exists($hpFile)) {
        return ['triggered' => false];
    }
    
    $validTokens = json_decode(file_get_contents($hpFile), true) ?: [];
    
    // Verifica link honeypot
    if (!empty($_GET['_hp'])) {
        $token = $_GET['_hp'];
        if (isset($validTokens[$token])) {
            return [
                'triggered' => true,
                'type' => 'link',
                'campaign' => $validTokens[$token]['campaign'] ?? ''
            ];
        }
    }
    
    // Verifica campos honeypot (qualquer campo preenchido que seja token)
    foreach ($_POST as $key => $value) {
        if (isset($validTokens[$key]) && !empty($value)) {
            return [
                'triggered' => true,
                'type' => 'field',
                'campaign' => $validTokens[$key]['campaign'] ?? ''
            ];
        }
    }
    
    return ['triggered' => false];
}

/**
 * Detecta comportamento de revisor de ads
 * Padroes tipicos de revisores humanos das plataformas
 */
function detectReviewerBehavior($behaviorData) {
    $score = 0;
    $indicators = [];
    
    // Tempo muito curto na pagina (< 5 segundos)
    if (($behaviorData['time_on_page'] ?? 0) < 5000) {
        $score += 25;
        $indicators[] = 'very_short_visit';
    }
    
    // Sem scroll
    if (($behaviorData['scrolls'] ?? 0) === 0) {
        $score += 20;
        $indicators[] = 'no_scroll';
    }
    
    // Sem movimento de mouse significativo
    if (($behaviorData['mouse_moves'] ?? 0) < 3) {
        $score += 20;
        $indicators[] = 'minimal_mouse';
    }
    
    // Horario comercial
    if (isReviewerWorkingHours()) {
        $score += 15;
        $indicators[] = 'business_hours';
    }
    
    // Veio de painel de ads
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if (preg_match('/(business\.facebook|ads\.tiktok|ads\.google|adsmanager)/i', $ref)) {
        $score += 30;
        $indicators[] = 'from_ads_panel';
    }
    
    // Acesso direto (sem referer) em campanha de ads
    if (empty($ref) && !empty($_GET['utm_source'])) {
        $score += 10;
        $indicators[] = 'direct_with_utm';
    }
    
    // Timezone corporativo (US timezones durante business hours)
    $tz = $behaviorData['timezone'] ?? '';
    if (preg_match('/America\/(New_York|Los_Angeles|Chicago|Phoenix)/i', $tz)) {
        if (isReviewerWorkingHours()) {
            $score += 10;
            $indicators[] = 'us_timezone_business';
        }
    }
    
    return [
        'is_reviewer' => $score >= 50,
        'score' => $score,
        'indicators' => $indicators
    ];
}

/**
 * Proof of Work Challenge
 * Forca o cliente a resolver um desafio computacional
 */
function generatePowChallenge($difficulty = 4) {
    $challenge = bin2hex(random_bytes(16));
    $timestamp = time();
    
    // Salva desafio valido
    $powFile = (defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/cache/') . 'pow_challenges.json';
    $challenges = [];
    if (file_exists($powFile)) {
        $challenges = json_decode(file_get_contents($powFile), true) ?: [];
    }
    
    // Limpa desafios expirados (> 5 min)
    $challenges = array_filter($challenges, function($c) {
        return $c['expires'] > time();
    });
    
    $challenges[$challenge] = [
        'difficulty' => $difficulty,
        'created' => $timestamp,
        'expires' => $timestamp + 300
    ];
    
    file_put_contents($powFile, json_encode($challenges));
    
    return [
        'challenge' => $challenge,
        'difficulty' => $difficulty,
        'target' => str_repeat('0', $difficulty)
    ];
}

/**
 * Verifica solucao do Proof of Work
 */
function verifyPowSolution($challenge, $nonce) {
    $powFile = (defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/cache/') . 'pow_challenges.json';
    
    if (!file_exists($powFile)) {
        return ['valid' => false, 'reason' => 'no_challenges'];
    }
    
    $challenges = json_decode(file_get_contents($powFile), true) ?: [];
    
    if (!isset($challenges[$challenge])) {
        return ['valid' => false, 'reason' => 'invalid_challenge'];
    }
    
    $data = $challenges[$challenge];
    
    if ($data['expires'] < time()) {
        return ['valid' => false, 'reason' => 'expired'];
    }
    
    // Verifica hash
    $hash = hash('sha256', $challenge . $nonce);
    $target = str_repeat('0', $data['difficulty']);
    
    if (substr($hash, 0, $data['difficulty']) === $target) {
        // Remove challenge usado
        unset($challenges[$challenge]);
        file_put_contents($powFile, json_encode($challenges));
        
        return ['valid' => true, 'hash' => $hash];
    }
    
    return ['valid' => false, 'reason' => 'wrong_nonce'];
}

/**
 * Decisao final AVANCADA: mostrar black ou white?
 * Versao melhorada com todas as tecnicas
 */
function advancedStealthDecision($visitorId, $campaignId, $behaviorData = []) {
    $ip = getVisitorIP();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // 1. Verifica honeypot
    $honeypot = checkHoneypot();
    if ($honeypot['triggered']) {
        addLearnedBot($ip, $ua, 'honeypot_triggered', $campaignId);
        return [
            'show' => 'white',
            'reason' => 'honeypot_triggered',
            'confidence' => 100
        ];
    }
    
    // 2. Calcula score de bot
    $botScore = calculateBotScore($ip, $ua, $behaviorData);
    
    if ($botScore['is_bot']) {
        addLearnedBot($ip, $ua, implode(', ', $botScore['reasons']), $campaignId);
        return [
            'show' => 'white',
            'reason' => implode(', ', $botScore['reasons']),
            'confidence' => $botScore['confidence'],
            'score' => $botScore['score']
        ];
    }
    
    // 3. Detecta comportamento de revisor
    $reviewer = detectReviewerBehavior($behaviorData);
    if ($reviewer['is_reviewer']) {
        collectReviewerFingerprint(md5($ip . $ua), $reviewer['indicators']);
        return [
            'show' => 'white',
            'reason' => 'reviewer_behavior',
            'confidence' => min($reviewer['score'], 100),
            'indicators' => $reviewer['indicators']
        ];
    }
    
    // 4. Verifica gate comportamental
    $gate = checkBehavioralGate($visitorId, $campaignId);
    
    if (!$gate['passed']) {
        return [
            'show' => 'white',
            'reason' => 'gate_not_passed',
            'stage' => $gate['stage'],
            'confidence' => 30
        ];
    }
    
    // 5. Passou em todos os testes
    return [
        'show' => 'black',
        'reason' => 'all_checks_passed',
        'confidence' => max(0, 100 - $botScore['score']),
        'score' => $botScore['score']
    ];
}

// ============================================
// V10.7 - reCAPTCHA v3, ML e TLS FINGERPRINT
// ============================================

/**
 * Verifica reCAPTCHA v3
 * 
 * @param string $token Token do reCAPTCHA do frontend
 * @param string $secretKey Chave secreta do reCAPTCHA
 * @param float $minScore Score minimo para passar (0.0 - 1.0)
 * @return array Resultado da verificacao
 */
function verifyRecaptchaV3($token, $secretKey, $minScore = 0.5) {
    if (empty($token) || empty($secretKey)) {
        return ['success' => false, 'error' => 'missing_params'];
    }
    
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => getVisitorIP()
    ];
    
    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 5
        ]
    ];
    
    $context = stream_context_create($options);
    
    try {
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            // Fallback com cURL
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query($data),
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => true
                ]);
                $response = curl_exec($ch);
                curl_close($ch);
            }
        }
        
        if ($response === false) {
            return ['success' => false, 'error' => 'request_failed'];
        }
        
        $result = json_decode($response, true);
        
        if (!$result) {
            return ['success' => false, 'error' => 'invalid_response'];
        }
        
        $score = $result['score'] ?? 0;
        $isBot = $score < $minScore;
        
        return [
            'success' => $result['success'] ?? false,
            'score' => $score,
            'action' => $result['action'] ?? '',
            'is_bot' => $isBot,
            'min_score' => $minScore,
            'hostname' => $result['hostname'] ?? '',
            'error_codes' => $result['error-codes'] ?? []
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Gera script do reCAPTCHA v3 para injetar na pagina
 */
function generateRecaptchaScript($siteKey, $action = 'pageview') {
    if (empty($siteKey)) return '';
    
    return '
<script src="https://www.google.com/recaptcha/api.js?render=' . htmlspecialchars($siteKey) . '"></script>
<script>
grecaptcha.ready(function() {
    grecaptcha.execute("' . htmlspecialchars($siteKey) . '", {action: "' . htmlspecialchars($action) . '"}).then(function(token) {
        // Envia token para validacao
        var xhr = new XMLHttpRequest();
        xhr.open("POST", window.location.href, true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.send("_recaptcha_token=" + encodeURIComponent(token));
    });
});
</script>';
}

// MLBotDetector removido - usar ml-detector.php separado

/**
 * TLS Fingerprinting Avancado
 * Analisa headers HTTP para criar fingerprint do cliente
 */
class TLSFingerprinter {
    
    private $knownBotFingerprints = [];
    private $fingerprintsFile;
    
    public function __construct($cacheDir = null) {
        $cacheDir = $cacheDir ?: (defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/cache/');
        $this->fingerprintsFile = $cacheDir . 'tls_fingerprints.json';
        $this->loadFingerprints();
        
        // Fingerprints conhecidos de bots
        $this->knownBotFingerprints = [
            // Puppeteer
            'puppeteer_default' => ['no_accept_language', 'minimal_headers'],
            // Selenium
            'selenium_default' => ['webdriver_header', 'missing_sec_fetch'],
            // Python requests
            'python_requests' => ['python_ua', 'no_accept_encoding'],
            // cURL
            'curl_default' => ['curl_ua', 'minimal_headers'],
            // Golang
            'golang_http' => ['go_ua', 'no_cookies']
        ];
    }
    
    private function loadFingerprints() {
        if (file_exists($this->fingerprintsFile)) {
            $data = json_decode(file_get_contents($this->fingerprintsFile), true);
            if (isset($data['bot_fps'])) {
                $this->knownBotFingerprints = array_merge(
                    $this->knownBotFingerprints, 
                    $data['bot_fps']
                );
            }
        }
    }
    
    /**
     * Gera fingerprint do request atual
     */
    public function generateFingerprint() {
        $headers = $this->getRelevantHeaders();
        
        $fp = [
            'accept_order' => $this->getHeaderOrder(['HTTP_ACCEPT', 'HTTP_ACCEPT_LANGUAGE', 'HTTP_ACCEPT_ENCODING']),
            'has_sec_fetch' => !empty($_SERVER['HTTP_SEC_FETCH_SITE']),
            'has_client_hints' => !empty($_SERVER['HTTP_SEC_CH_UA']),
            'has_dnt' => !empty($_SERVER['HTTP_DNT']),
            'has_upgrade_insecure' => !empty($_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS']),
            'connection_type' => $_SERVER['HTTP_CONNECTION'] ?? 'none',
            'encoding_support' => $this->parseAcceptEncoding($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''),
            'language_count' => count(explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')),
            'ua_length' => strlen($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'header_count' => count($headers)
        ];
        
        return [
            'hash' => md5(json_encode($fp)),
            'features' => $fp
        ];
    }
    
    /**
     * Analisa fingerprint e retorna score de suspeita
     */
    public function analyze() {
        $fp = $this->generateFingerprint();
        $score = 0;
        $issues = [];
        
        // Sem Sec-Fetch headers (Chrome moderno sempre envia)
        if (!$fp['features']['has_sec_fetch']) {
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if (preg_match('/Chrome\/([89]\d|1\d{2})/', $ua)) {
                $score += 25;
                $issues[] = 'missing_sec_fetch';
            }
        }
        
        // Sem Client Hints (Chrome 89+)
        if (!$fp['features']['has_client_hints']) {
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if (preg_match('/Chrome\/(89|9\d|1\d{2})/', $ua)) {
                $score += 20;
                $issues[] = 'missing_client_hints';
            }
        }
        
        // Poucos headers
        if ($fp['features']['header_count'] < 5) {
            $score += 15;
            $issues[] = 'few_headers';
        }
        
        // UA muito curto
        if ($fp['features']['ua_length'] < 50) {
            $score += 10;
            $issues[] = 'short_ua';
        }
        
        // Sem Accept-Language ou muito simples
        if ($fp['features']['language_count'] < 2) {
            $score += 10;
            $issues[] = 'simple_language';
        }
        
        // Sem suporte a compressao moderna
        $encodings = $fp['features']['encoding_support'];
        if (!in_array('gzip', $encodings) && !in_array('br', $encodings)) {
            $score += 15;
            $issues[] = 'no_compression';
        }
        
        // Verifica contra fingerprints de bots conhecidos
        if ($this->matchesKnownBot($fp['features'])) {
            $score += 40;
            $issues[] = 'known_bot_fingerprint';
        }
        
        return [
            'fingerprint' => $fp['hash'],
            'score' => $score,
            'is_suspicious' => $score >= 30,
            'issues' => $issues
        ];
    }
    
    /**
     * Salva fingerprint de bot para aprendizado
     */
    public function learnBotFingerprint($fingerprint, $reason) {
        $data = [];
        if (file_exists($this->fingerprintsFile)) {
            $data = json_decode(file_get_contents($this->fingerprintsFile), true) ?: [];
        }
        
        if (!isset($data['bot_fps'])) {
            $data['bot_fps'] = [];
        }
        
        $data['bot_fps'][$fingerprint] = [
            'reason' => $reason,
            'added' => time()
        ];
        
        file_put_contents($this->fingerprintsFile, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    private function getRelevantHeaders() {
        $relevant = [];
        $prefixes = ['HTTP_ACCEPT', 'HTTP_SEC', 'HTTP_UPGRADE', 'HTTP_CONNECTION', 'HTTP_DNT'];
        
        foreach ($_SERVER as $key => $value) {
            foreach ($prefixes as $prefix) {
                if (strpos($key, $prefix) === 0) {
                    $relevant[$key] = $value;
                    break;
                }
            }
        }
        
        return $relevant;
    }
    
    private function getHeaderOrder($headers) {
        $order = [];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $order[] = $h;
            }
        }
        return implode(',', $order);
    }
    
    private function parseAcceptEncoding($encoding) {
        return array_map('trim', explode(',', $encoding));
    }
    
    private function matchesKnownBot($features) {
        // Logica simplificada - em producao seria mais complexa
        if ($features['header_count'] < 4 && !$features['has_sec_fetch']) {
            return true;
        }
        return false;
    }
}

// Instancias globais (classes carregadas de arquivos separados)
$mlDetector = class_exists('MLBotDetector') ? new MLBotDetector() : null;
$tlsFingerprinter = class_exists('TLSFingerprinter') ? new TLSFingerprinter() : null;

/**
 * Decisao ULTRA AVANCADA com reCAPTCHA, ML e TLS
 */
function ultraStealthDecision($visitorId, $campaignId, $campaign, $behaviorData = []) {
    $ip = getVisitorIP();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    global $mlDetector, $tlsFingerprinter;
    
    $score = 0;
    $reasons = [];
    
    // 1. Verificacoes basicas (funcao existente)
    $basicCheck = advancedStealthDecision($visitorId, $campaignId, $behaviorData);
    
    if ($basicCheck['show'] === 'white') {
        return $basicCheck;
    }
    
    // 2. reCAPTCHA v3 (se habilitado)
    if (!empty($campaign['recaptcha_enabled']) && $campaign['recaptcha_enabled'] !== '0') {
        $recaptchaToken = $_POST['_recaptcha_token'] ?? $_GET['_recaptcha_token'] ?? '';
        
        if (!empty($recaptchaToken) && !empty($campaign['recaptcha_secret_key'])) {
            $minScore = floatval($campaign['recaptcha_min_score'] ?? 0.5);
            $recaptchaResult = verifyRecaptchaV3($recaptchaToken, $campaign['recaptcha_secret_key'], $minScore);
            
            if ($recaptchaResult['success'] && $recaptchaResult['is_bot']) {
                return [
                    'show' => 'white',
                    'reason' => 'recaptcha_low_score',
                    'score' => $recaptchaResult['score'],
                    'confidence' => 90
                ];
            }
        }
    }
    
    // 3. Machine Learning (se habilitado e classe disponivel)
    if (!empty($campaign['ml_enabled']) && $campaign['ml_enabled'] !== '0' && $mlDetector !== null) {
        $features = [
            'user_agent' => $ua,
            'screen' => $behaviorData['screen'] ?? '',
            'timezone' => $behaviorData['timezone'] ?? '',
            'plugins' => $behaviorData['plugins'] ?? 0,
            'languages' => $behaviorData['languages'] ?? 0
        ];
        
        $mlPrediction = $mlDetector->predict($features);
        
        if ($mlPrediction['is_bot'] && $mlPrediction['confidence'] > 0.7) {
            return [
                'show' => 'white',
                'reason' => 'ml_detected_bot',
                'probability' => $mlPrediction['probability'],
                'confidence' => $mlPrediction['confidence'] * 100
            ];
        }
    }
    
    // 4. TLS Fingerprinting (se habilitado e classe disponivel)
    if (!empty($campaign['tls_fp_enabled']) && $campaign['tls_fp_enabled'] !== '0' && $tlsFingerprinter !== null) {
        $tlsAnalysis = $tlsFingerprinter->analyze();
        
        if ($tlsAnalysis['is_suspicious'] && $tlsAnalysis['score'] >= 40) {
            return [
                'show' => 'white',
                'reason' => 'tls_fingerprint_suspicious',
                'tls_score' => $tlsAnalysis['score'],
                'issues' => $tlsAnalysis['issues'],
                'confidence' => min($tlsAnalysis['score'], 100)
            ];
        }
    }
    
    // Passou em todos os testes
    return [
        'show' => 'black',
        'reason' => 'all_advanced_checks_passed',
        'confidence' => 95
    ];
}

/**
 * ============================================
 * FUNCOES DE ANALYTICS (Logs de Acesso)
 * ============================================
 */

// Diretorio para salvar logs de acesso
if (!defined('ACCESS_LOGS_DIR')) {
    define('ACCESS_LOGS_DIR', DATA_DIR . 'access_logs/');
}

// Garante que o diretorio existe
if (!is_dir(ACCESS_LOGS_DIR)) {
    @mkdir(ACCESS_LOGS_DIR, 0755, true);
}

/**
 * Salva log de acesso
 */
function saveAccessLog($logData) {
    $date = date('Y-m-d');
    $filename = ACCESS_LOGS_DIR . $date . '.json';
    
    $logEntry = [
        'timestamp' => $logData['timestamp'] ?? date('Y-m-d H:i:s'),
        'campaign_slug' => $logData['campaign_slug'] ?? '',
        'ip_masked' => $logData['ip_masked'] ?? '',
        'user_agent' => substr($logData['user_agent'] ?? '', 0, 255),
        'referer' => substr($logData['referer'] ?? '', 0, 500),
        'result' => $logData['result'] ?? 'unknown',
        'reason' => $logData['reason'] ?? '',
        'country' => $logData['country'] ?? '',
        'device' => $logData['device'] ?? '',
        'bot_score' => (int)($logData['bot_score'] ?? 0),
        'bot_flags' => $logData['bot_flags'] ?? '',
        'url' => substr($logData['url'] ?? '', 0, 500)
    ];
    
    // Append ao arquivo do dia
    $existingLogs = [];
    if (file_exists($filename)) {
        $content = file_get_contents($filename);
        $existingLogs = json_decode($content, true) ?: [];
    }
    
    $existingLogs[] = $logEntry;
    
    // Limita a 10000 logs por dia para nao sobrecarregar
    if (count($existingLogs) > 10000) {
        $existingLogs = array_slice($existingLogs, -10000);
    }
    
    file_put_contents($filename, json_encode($existingLogs, JSON_PRETTY_PRINT), LOCK_EX);
    return true;
}

/**
 * Busca logs de acesso
 */
function getAccessLogs($campaignSlug = '', $dateFrom = '', $dateTo = '', $limit = 500) {
    $logs = [];
    
    $from = $dateFrom ? new DateTime($dateFrom) : new DateTime('-7 days');
    $to = $dateTo ? new DateTime($dateTo) : new DateTime();
    $to->modify('+1 day'); // Inclui o dia final
    
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($from, $interval, $to);
    
    foreach ($period as $date) {
        $filename = ACCESS_LOGS_DIR . $date->format('Y-m-d') . '.json';
        if (file_exists($filename)) {
            $content = file_get_contents($filename);
            $dayLogs = json_decode($content, true) ?: [];
            
            // Filtra por campanha se especificado
            if ($campaignSlug) {
                $dayLogs = array_filter($dayLogs, function($log) use ($campaignSlug) {
                    return ($log['campaign_slug'] ?? '') === $campaignSlug;
                });
            }
            
            $logs = array_merge($logs, $dayLogs);
        }
    }
    
    // Ordena por timestamp decrescente (mais recentes primeiro)
    usort($logs, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    return array_slice($logs, 0, $limit);
}

/**
 * Gera analytics agregados
 */
function getAnalytics($campaignSlug = '', $dateFrom = '', $dateTo = '', $isAdmin = false, $currentUserId = '') {
    $logs = getAccessLogs($campaignSlug, $dateFrom, $dateTo, 10000);
    
    // Filtra por campanhas do usuario se nao for admin
    if (!$isAdmin && !$campaignSlug) {
        $userCampaigns = array_filter(getCampaigns(), function($c) use ($currentUserId) {
            return ($c['user_id'] ?? 'default') === $currentUserId;
        });
        $userSlugs = array_column($userCampaigns, 'slug');
        
        $logs = array_filter($logs, function($log) use ($userSlugs) {
            return in_array($log['campaign_slug'] ?? '', $userSlugs);
        });
    }
    
    // Inicializa contadores
    $analytics = [
        'total' => count($logs),
        'by_result' => ['white' => 0, 'black' => 0, 'blocked' => 0, 'schedule_pause' => 0],
        'by_reason' => [],
        'by_country' => [],
        'by_device' => [],
        'by_hour' => array_fill(0, 24, 0),
        'by_date' => [],
        'by_campaign' => [],
        'avg_bot_score' => 0,
        'top_bot_flags' => []
    ];
    
    $totalBotScore = 0;
    $botFlagCounts = [];
    
    foreach ($logs as $log) {
        // Por resultado
        $result = $log['result'] ?? 'unknown';
        if (isset($analytics['by_result'][$result])) {
            $analytics['by_result'][$result]++;
        }
        
        // Por motivo
        $reason = $log['reason'] ?? 'unknown';
        $analytics['by_reason'][$reason] = ($analytics['by_reason'][$reason] ?? 0) + 1;
        
        // Por pais
        $country = $log['country'] ?: 'Desconhecido';
        $analytics['by_country'][$country] = ($analytics['by_country'][$country] ?? 0) + 1;
        
        // Por dispositivo
        $device = $log['device'] ?: 'Desconhecido';
        $analytics['by_device'][$device] = ($analytics['by_device'][$device] ?? 0) + 1;
        
        // Por hora
        $hour = (int) date('G', strtotime($log['timestamp']));
        $analytics['by_hour'][$hour]++;
        
        // Por data
        $date = date('Y-m-d', strtotime($log['timestamp']));
        if (!isset($analytics['by_date'][$date])) {
            $analytics['by_date'][$date] = ['white' => 0, 'black' => 0, 'total' => 0];
        }
        $analytics['by_date'][$date]['total']++;
        if ($result === 'white' || $result === 'schedule_pause') {
            $analytics['by_date'][$date]['white']++;
        } else {
            $analytics['by_date'][$date]['black']++;
        }
        
        // Por campanha
        $slug = $log['campaign_slug'] ?? 'unknown';
        if (!isset($analytics['by_campaign'][$slug])) {
            $analytics['by_campaign'][$slug] = ['white' => 0, 'black' => 0, 'total' => 0];
        }
        $analytics['by_campaign'][$slug]['total']++;
        if ($result === 'white' || $result === 'schedule_pause') {
            $analytics['by_campaign'][$slug]['white']++;
        } else {
            $analytics['by_campaign'][$slug]['black']++;
        }
        
        // Bot score
        $totalBotScore += (int)($log['bot_score'] ?? 0);
        
        // Bot flags
        $flags = $log['bot_flags'] ?? '';
        if ($flags) {
            foreach (explode(',', $flags) as $flag) {
                $flag = trim($flag);
                if ($flag) {
                    $botFlagCounts[$flag] = ($botFlagCounts[$flag] ?? 0) + 1;
                }
            }
        }
    }
    
    // Calcula media de bot score
    $analytics['avg_bot_score'] = $analytics['total'] > 0 ? round($totalBotScore / $analytics['total'], 1) : 0;
    
    // Top bot flags
    arsort($botFlagCounts);
    $analytics['top_bot_flags'] = array_slice($botFlagCounts, 0, 10, true);
    
    // Ordena por_country e by_device por quantidade
    arsort($analytics['by_country']);
    arsort($analytics['by_device']);
    $analytics['by_country'] = array_slice($analytics['by_country'], 0, 10, true);
    $analytics['by_device'] = array_slice($analytics['by_device'], 0, 5, true);
    
    // Ordena by_date por data
    ksort($analytics['by_date']);
    
    return $analytics;
}

/**
 * ============================================
 * PROTECAO CONTRA REPLAY ATTACKS
 * ============================================
 * Gera e valida tokens unicos para cada sessao
 * Impede que bots repitam requisicoes capturadas
 */

// Diretorio para tokens
if (!defined('TOKENS_DIR')) {
    define('TOKENS_DIR', DATA_DIR . 'tokens/');
}

// Garante que o diretorio existe
if (!is_dir(TOKENS_DIR)) {
    @mkdir(TOKENS_DIR, 0755, true);
}

/**
 * Gera token unico para protecao contra replay attack
 * @param string $campaignSlug Slug da campanha
 * @param int $ttl Tempo de vida do token em segundos (padrao: 5 minutos)
 * @return array Token e dados associados
 */
function generateReplayToken($campaignSlug, $ttl = 300) {
    // Gera token aleatorio seguro
    $token = bin2hex(random_bytes(32));
    
    // Dados do token
    $tokenData = [
        'token' => $token,
        'campaign' => $campaignSlug,
        'created_at' => time(),
        'expires_at' => time() + $ttl,
        'ip_hash' => md5($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        'ua_hash' => md5($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
        'used' => false
    ];
    
    // Salva token em arquivo
    $tokenFile = TOKENS_DIR . $token . '.json';
    file_put_contents($tokenFile, json_encode($tokenData), LOCK_EX);
    
    // Limpa tokens expirados periodicamente (1% de chance a cada requisicao)
    if (rand(1, 100) === 1) {
        cleanExpiredTokens();
    }
    
    return $tokenData;
}

/**
 * Valida token de replay attack
 * @param string $token Token a ser validado
 * @param bool $markAsUsed Se deve marcar como usado (padrao: true)
 * @return array Resultado da validacao
 */
function validateReplayToken($token, $markAsUsed = true) {
    if (empty($token) || strlen($token) !== 64) {
        return [
            'valid' => false,
            'reason' => 'invalid_token_format'
        ];
    }
    
    $tokenFile = TOKENS_DIR . $token . '.json';
    
    // Token nao existe
    if (!file_exists($tokenFile)) {
        return [
            'valid' => false,
            'reason' => 'token_not_found'
        ];
    }
    
    $tokenData = json_decode(file_get_contents($tokenFile), true);
    
    if (!$tokenData) {
        @unlink($tokenFile);
        return [
            'valid' => false,
            'reason' => 'token_corrupted'
        ];
    }
    
    // Token expirado
    if (time() > $tokenData['expires_at']) {
        @unlink($tokenFile);
        return [
            'valid' => false,
            'reason' => 'token_expired'
        ];
    }
    
    // Token ja foi usado (replay attack detectado!)
    if ($tokenData['used']) {
        @unlink($tokenFile);
        return [
            'valid' => false,
            'reason' => 'token_already_used',
            'is_replay_attack' => true
        ];
    }
    
    // Verifica se IP/UA mudou (possivel ataque)
    $currentIpHash = md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $currentUaHash = md5($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    
    if ($tokenData['ip_hash'] !== $currentIpHash || $tokenData['ua_hash'] !== $currentUaHash) {
        return [
            'valid' => false,
            'reason' => 'token_fingerprint_mismatch',
            'is_replay_attack' => true
        ];
    }
    
    // Token valido - marca como usado
    if ($markAsUsed) {
        $tokenData['used'] = true;
        $tokenData['used_at'] = time();
        file_put_contents($tokenFile, json_encode($tokenData), LOCK_EX);
        
        // Agenda exclusao do arquivo apos uso
        // (deixa por mais 60s para logs, depois limpa)
        $tokenData['delete_after'] = time() + 60;
        file_put_contents($tokenFile, json_encode($tokenData), LOCK_EX);
    }
    
    return [
        'valid' => true,
        'campaign' => $tokenData['campaign'],
        'created_at' => $tokenData['created_at'],
        'age' => time() - $tokenData['created_at']
    ];
}

/**
 * Limpa tokens expirados
 */
function cleanExpiredTokens() {
    if (!is_dir(TOKENS_DIR)) {
        return;
    }
    
    $files = glob(TOKENS_DIR . '*.json');
    $now = time();
    $cleaned = 0;
    
    foreach ($files as $file) {
        $tokenData = @json_decode(@file_get_contents($file), true);
        
        // Remove se expirado ou marcado para exclusao
        if (!$tokenData || 
            $now > ($tokenData['expires_at'] ?? 0) || 
            ($tokenData['delete_after'] ?? 0) > 0 && $now > $tokenData['delete_after']) {
            @unlink($file);
            $cleaned++;
        }
    }
    
    return $cleaned;
}

/**
 * Retorna estatisticas de tokens
 */
function getTokenStats() {
    if (!is_dir(TOKENS_DIR)) {
        return ['active' => 0, 'total_files' => 0];
    }
    
    $files = glob(TOKENS_DIR . '*.json');
    $now = time();
    $active = 0;
    
    foreach ($files as $file) {
        $tokenData = @json_decode(@file_get_contents($file), true);
        if ($tokenData && !$tokenData['used'] && $now < ($tokenData['expires_at'] ?? 0)) {
            $active++;
        }
    }
    
    return [
        'active' => $active,
        'total_files' => count($files)
    ];
}

/**
 * ============================================
 * COMMANDER V3 - TRANSACOES E GATEWAYS
 * Sistema de rastreamento de vendas
 * ============================================
 */

// Arquivos de dados V3
if (!defined('TRANSACTIONS_FILE')) {
    define('TRANSACTIONS_FILE', DATA_DIR . 'transactions.json');
}
if (!defined('GATEWAYS_FILE')) {
    define('GATEWAYS_FILE', DATA_DIR . 'gateways.json');
}

/**
 * Salva uma transacao
 */
function saveTransaction($transaction) {
    $transactions = readJsonFile(TRANSACTIONS_FILE, []);
    
    // Verifica se ja existe (evita duplicatas)
    $existingIndex = null;
    foreach ($transactions as $index => $t) {
        if ($t['transaction_id'] === $transaction['transaction_id'] && 
            $t['gateway'] === $transaction['gateway']) {
            $existingIndex = $index;
            break;
        }
    }
    
    if ($existingIndex !== null) {
        // Atualiza transacao existente
        $transactions[$existingIndex] = array_merge($transactions[$existingIndex], $transaction);
        $transactions[$existingIndex]['updated_at'] = date('Y-m-d H:i:s');
    } else {
        // Nova transacao
        $transaction['created_at'] = date('Y-m-d H:i:s');
        array_unshift($transactions, $transaction);
    }
    
    // Limita a 50000 transacoes
    if (count($transactions) > 50000) {
        $transactions = array_slice($transactions, 0, 50000);
    }
    
    return writeJsonFile(TRANSACTIONS_FILE, $transactions);
}

/**
 * Busca transacoes com filtros
 */
function getTransactions($filters = []) {
    $transactions = readJsonFile(TRANSACTIONS_FILE, []);
    
    // Filtro por status
    if (!empty($filters['status'])) {
        $transactions = array_filter($transactions, function($t) use ($filters) {
            return ($t['status'] ?? '') === $filters['status'];
        });
    }
    
    // Filtro por gateway
    if (!empty($filters['gateway'])) {
        $transactions = array_filter($transactions, function($t) use ($filters) {
            return ($t['gateway'] ?? '') === $filters['gateway'];
        });
    }
    
    // Filtro por campanha
    if (!empty($filters['campaign_slug'])) {
        $transactions = array_filter($transactions, function($t) use ($filters) {
            return ($t['campaign_slug'] ?? '') === $filters['campaign_slug'];
        });
    }
    
    // Filtro por usuario
    if (!empty($filters['user_id'])) {
        // Busca campanhas do usuario
        $userCampaigns = array_filter(getCampaigns(), function($c) use ($filters) {
            return ($c['user_id'] ?? 'default') === $filters['user_id'];
        });
        $userSlugs = array_column($userCampaigns, 'slug');
        
        $transactions = array_filter($transactions, function($t) use ($userSlugs) {
            return in_array($t['campaign_slug'] ?? '', $userSlugs);
        });
    }
    
    // Filtro por data
    if (!empty($filters['date_from'])) {
        $dateFrom = strtotime($filters['date_from']);
        $transactions = array_filter($transactions, function($t) use ($dateFrom) {
            return strtotime($t['created_at'] ?? '2000-01-01') >= $dateFrom;
        });
    }
    
    if (!empty($filters['date_to'])) {
        $dateTo = strtotime($filters['date_to'] . ' 23:59:59');
        $transactions = array_filter($transactions, function($t) use ($dateTo) {
            return strtotime($t['created_at'] ?? '2099-01-01') <= $dateTo;
        });
    }
    
    // Limite
    $limit = $filters['limit'] ?? 500;
    
    return array_values(array_slice($transactions, 0, $limit));
}

/**
 * Alias para calculateRevenue (compatibilidade)
 */
function getRevenue($filters = []) {
    return calculateRevenue($filters);
}

/**
 * Calcula faturamento
 */
function calculateRevenue($filters = []) {
    $transactions = getTransactions(array_merge($filters, ['limit' => 50000]));
    
    $revenue = [
        'total' => 0,
        'total_paid' => 0,
        'total_pending' => 0,
        'total_refunded' => 0,
        'paid' => 0,
        'pending' => 0,
        'refunded' => 0,
        'chargeback' => 0,
        'count' => [
            'total' => count($transactions),
            'paid' => 0,
            'pending' => 0,
            'refunded' => 0,
            'chargeback' => 0
        ],
        'by_gateway' => [],
        'by_campaign' => [],
        'by_date' => [],
        'conversion_rate' => 0,
        'avg_ticket' => 0
    ];
    
    foreach ($transactions as $t) {
        $amount = (float)($t['amount'] ?? 0);
        $status = $t['status'] ?? 'pending';
        $gateway = $t['gateway'] ?? 'unknown';
        $campaign = $t['campaign_slug'] ?? 'sem_campanha';
        $date = date('Y-m-d', strtotime($t['created_at'] ?? 'now'));
        
        // Por status
        if ($status === 'paid' || $status === 'approved') {
            $revenue['paid'] += $amount;
            $revenue['total_paid'] += $amount;
            $revenue['count']['paid']++;
        } elseif ($status === 'pending' || $status === 'waiting_payment') {
            $revenue['pending'] += $amount;
            $revenue['total_pending'] += $amount;
            $revenue['count']['pending']++;
        } elseif ($status === 'refunded') {
            $revenue['refunded'] += $amount;
            $revenue['total_refunded'] += $amount;
            $revenue['count']['refunded']++;
        } elseif ($status === 'chargeback') {
            $revenue['chargeback'] += $amount;
            $revenue['count']['chargeback']++;
        }
        
        $revenue['total'] += $amount;
        
        // Por gateway
        if (!isset($revenue['by_gateway'][$gateway])) {
            $revenue['by_gateway'][$gateway] = [
                'paid' => 0, 'pending' => 0, 'refunded' => 0, 'total' => 0, 'count' => 0,
                'paid_count' => 0, 'pending_count' => 0, 'refunded_count' => 0
            ];
        }
        $revenue['by_gateway'][$gateway]['total'] += $amount;
        $revenue['by_gateway'][$gateway]['count']++;
        if ($status === 'paid' || $status === 'approved') {
            $revenue['by_gateway'][$gateway]['paid'] += $amount;
            $revenue['by_gateway'][$gateway]['paid_count']++;
        } elseif ($status === 'refunded') {
            $revenue['by_gateway'][$gateway]['refunded'] += $amount;
            $revenue['by_gateway'][$gateway]['refunded_count']++;
        } else {
            $revenue['by_gateway'][$gateway]['pending'] += $amount;
            $revenue['by_gateway'][$gateway]['pending_count']++;
        }
        
        // Por campanha - com contagens detalhadas
        if (!isset($revenue['by_campaign'][$campaign])) {
            $revenue['by_campaign'][$campaign] = [
                'paid' => 0, 'pending' => 0, 'refunded' => 0, 'total' => 0, 'count' => 0,
                'paid_count' => 0, 'pending_count' => 0, 'refunded_count' => 0
            ];
        }
        $revenue['by_campaign'][$campaign]['total'] += $amount;
        $revenue['by_campaign'][$campaign]['count']++;
        if ($status === 'paid' || $status === 'approved') {
            $revenue['by_campaign'][$campaign]['paid'] += $amount;
            $revenue['by_campaign'][$campaign]['paid_count']++;
        } elseif ($status === 'refunded') {
            $revenue['by_campaign'][$campaign]['refunded'] += $amount;
            $revenue['by_campaign'][$campaign]['refunded_count']++;
        } else {
            $revenue['by_campaign'][$campaign]['pending'] += $amount;
            $revenue['by_campaign'][$campaign]['pending_count']++;
        }
        
        // Por data
        if (!isset($revenue['by_date'][$date])) {
            $revenue['by_date'][$date] = ['paid' => 0, 'pending' => 0, 'total' => 0];
        }
        $revenue['by_date'][$date]['total'] += $amount;
        if ($status === 'paid' || $status === 'approved') {
            $revenue['by_date'][$date]['paid'] += $amount;
        } else {
            $revenue['by_date'][$date]['pending'] += $amount;
        }
    }
    
    // Ordena por data
    ksort($revenue['by_date']);
    
    // Calcula taxa de conversao e ticket medio
    $totalCount = $revenue['count']['total'];
    $paidCount = $revenue['count']['paid'];
    
    if ($totalCount > 0) {
        $revenue['conversion_rate'] = round(($paidCount / $totalCount) * 100, 1);
    }
    
    if ($paidCount > 0) {
        $revenue['avg_ticket'] = round($revenue['paid'] / $paidCount, 2);
    }
    
    return $revenue;
}

/**
 * ============================================
 * GATEWAYS DE PAGAMENTO
 * ============================================
 */

/**
 * Lista todos os gateways suportados
 */
function getSupportedGateways() {
    return [
        'hotmart' => [
            'name' => 'Hotmart',
            'color' => '#F04E23',
            'statuses' => [
                'approved' => 'paid',
                'completed' => 'paid',
                'billet_printed' => 'pending',
                'waiting_payment' => 'pending',
                'refunded' => 'refunded',
                'chargeback' => 'chargeback',
                'cancelled' => 'cancelled',
                'expired' => 'cancelled'
            ]
        ],
        'kiwify' => [
            'name' => 'Kiwify',
            'color' => '#00D4AA',
            'statuses' => [
                'paid' => 'paid',
                'waiting_payment' => 'pending',
                'refused' => 'cancelled',
                'refunded' => 'refunded',
                'chargedback' => 'chargeback'
            ]
        ],
        'monetizze' => [
            'name' => 'Monetizze',
            'color' => '#FF6B00',
            'statuses' => [
                'Finalizada' => 'paid',
                'Aguardando pagamento' => 'pending',
                'Completa' => 'paid',
                'Reembolsada' => 'refunded',
                'Cancelada' => 'cancelled'
            ]
        ],
        'perfectpay' => [
            'name' => 'PerfectPay',
            'color' => '#7C3AED',
            'statuses' => [
                'approved' => 'paid',
                'pending' => 'pending',
                'refunded' => 'refunded',
                'chargeback' => 'chargeback'
            ]
        ],
        'eduzz' => [
            'name' => 'Eduzz',
            'color' => '#1E40AF',
            'statuses' => [
                'Pago' => 'paid',
                'Aguardando Pagamento' => 'pending',
                'Reembolsado' => 'refunded',
                'Cancelado' => 'cancelled'
            ]
        ],
        'braip' => [
            'name' => 'Braip',
            'color' => '#059669',
            'statuses' => [
                'approved' => 'paid',
                'pending' => 'pending',
                'refunded' => 'refunded',
                'chargeback' => 'chargeback'
            ]
        ],
        'ticto' => [
            'name' => 'Ticto',
            'color' => '#DC2626',
            'statuses' => [
                'paid' => 'paid',
                'pending' => 'pending',
                'refunded' => 'refunded'
            ]
        ],
        'pepper' => [
            'name' => 'Pepper',
            'color' => '#EA580C',
            'statuses' => [
                'approved' => 'paid',
                'pending' => 'pending',
                'refunded' => 'refunded',
                'chargeback' => 'chargeback'
            ]
        ],
        'doppus' => [
            'name' => 'Doppus',
            'color' => '#0891B2',
            'statuses' => [
                'paid' => 'paid',
                'pending' => 'pending',
                'refunded' => 'refunded'
            ]
        ],
        'greenn' => [
            'name' => 'Greenn',
            'color' => '#16A34A',
            'statuses' => [
                'paid' => 'paid',
                'pending' => 'pending',
                'refunded' => 'refunded'
            ]
        ],
        'ironpay' => [
            'name' => 'IronPay',
            'color' => '#3B82F6',
            'statuses' => [
                'paid' => 'paid',
                'approved' => 'paid',
                'pending' => 'pending',
                'refunded' => 'refunded',
                'chargeback' => 'chargeback'
            ]
        ],
        'blackcat' => [
            'name' => 'BlackCat',
            'color' => '#1F2937',
            'statuses' => [
                'paid' => 'paid',
                'pending' => 'pending',
                'cancelled' => 'cancelled',
                'refunded' => 'refunded',
                'chargeback' => 'chargeback'
            ]
        ],
        'skalepay' => [
            'name' => 'SkalePay',
            'color' => '#8B5CF6',
            'statuses' => [
                'paid' => 'paid',
                'pending' => 'pending',
                'refunded' => 'refunded',
                'chargeback' => 'chargeback'
            ]
        ],
        'otimizepagamentos' => [
            'name' => 'Otimize Pagamentos',
            'color' => '#F97316',
            'statuses' => [
                'paid' => 'paid',
                'pending' => 'pending',
                'refunded' => 'refunded',
                'chargeback' => 'chargeback'
            ]
        ],
        'virtualpay' => [
            'name' => 'VirtualPay',
            'color' => '#06B6D4',
            'statuses' => [
                'paid' => 'paid',
                'pending' => 'pending',
                'in_process' => 'pending',
                'refund' => 'refunded',
                'cancelled' => 'cancelled'
            ]
        ],
        'fastsoft' => [
            'name' => 'FastSoft',
            'color' => '#EF4444',
            'statuses' => [
                'paid' => 'paid',
                'pending' => 'pending',
                'refunded' => 'refunded',
                'chargeback' => 'chargeback'
            ]
        ],
        'plumify' => [
            'name' => 'Plumify',
            'color' => '#A855F7',
            'statuses' => [
                'paid' => 'paid',
                'pending' => 'pending',
                'refunded' => 'refunded',
                'chargeback' => 'chargeback'
            ]
        ],
        'bynet' => [
            'name' => 'Bynet',
            'color' => '#10B981',
            'statuses' => [
                'paid' => 'paid',
                'completed' => 'paid',
                'pending' => 'pending',
                'refunded' => 'refunded',
                'chargeback' => 'chargeback'
            ]
        ]
    ];
}

/**
 * Salva configuracao de gateway do usuario
 */
function saveUserGateway($userId, $gatewayId, $config) {
    // Garante que o arquivo existe
    if (!file_exists(GATEWAYS_FILE)) {
        $dir = dirname(GATEWAYS_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(GATEWAYS_FILE, '{}');
    }
    
    $gateways = readJsonFile(GATEWAYS_FILE, []);
    
    // Garante que e um array
    if (!is_array($gateways)) {
        $gateways = [];
    }
    
    if (!isset($gateways[$userId])) {
        $gateways[$userId] = [];
    }
    
    $gateways[$userId][$gatewayId] = [
        'gateway' => $gatewayId,
        'enabled' => $config['enabled'] ?? true,
        'token' => $config['token'] ?? '',
        'webhook_secret' => $config['webhook_secret'] ?? '',
        'created_at' => $gateways[$userId][$gatewayId]['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $result = writeJsonFile(GATEWAYS_FILE, $gateways);
    
    return $result;
}

/**
 * Busca gateways configurados do usuario
 */
function getUserGateways($userId) {
    $gateways = readJsonFile(GATEWAYS_FILE, []);
    return $gateways[$userId] ?? [];
}

/**
 * Remove gateway do usuario
 */
function removeUserGateway($userId, $gatewayId) {
    $gateways = readJsonFile(GATEWAYS_FILE, []);
    
    if (isset($gateways[$userId][$gatewayId])) {
        unset($gateways[$userId][$gatewayId]);
        return writeJsonFile(GATEWAYS_FILE, $gateways);
    }
    
    return true;
}

/**
 * Gera URL do webhook para o gateway
 */
function generateWebhookUrl($gatewayId, $userId) {
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $secret = md5($userId . $gatewayId . 'commander_v3_secret');
    
    return $baseUrl . '/COMMANDERV3/webhook.php?gateway=' . $gatewayId . '&token=' . $secret;
}

/**
 * Vincula transacao a campanha via UTM
 */
function linkTransactionToCampaign($transaction) {
    // Tenta encontrar campanha pelo UTM source/campaign
    $utmSource = $transaction['utm_source'] ?? '';
    $utmCampaign = $transaction['utm_campaign'] ?? '';
    $email = $transaction['customer_email'] ?? '';
    
    $campaigns = getCampaigns();
    
    // Primeiro tenta pelo UTM
    if ($utmCampaign) {
        foreach ($campaigns as $c) {
            if (stripos($c['slug'] ?? '', $utmCampaign) !== false ||
                stripos($c['name'] ?? '', $utmCampaign) !== false) {
                return $c['slug'];
            }
        }
    }
    
    // Tenta pelo source
    if ($utmSource) {
        foreach ($campaigns as $c) {
            if (stripos($c['slug'] ?? '', $utmSource) !== false) {
                return $c['slug'];
            }
        }
    }
    
    // Se nao encontrou, tenta pelo produto
    $productName = $transaction['product_name'] ?? '';
    if ($productName) {
        foreach ($campaigns as $c) {
            if (stripos($c['name'] ?? '', $productName) !== false) {
                return $c['slug'];
            }
        }
    }
    
    return 'unlinked';
}

/**
 * Normaliza status do gateway para status interno
 */
function normalizeTransactionStatus($gateway, $originalStatus) {
    $gateways = getSupportedGateways();
    
    if (!isset($gateways[$gateway])) {
        return 'pending';
    }
    
    $statusMap = $gateways[$gateway]['statuses'];
    
    return $statusMap[$originalStatus] ?? 'pending';
}

/**
 * Retorna estatisticas de vendas para dashboard
 */
function getSalesStats($userId = null, $isAdmin = false) {
    $filters = [];
    
    if (!$isAdmin && $userId) {
        $filters['user_id'] = $userId;
    }
    
    // Hoje
    $filters['date_from'] = date('Y-m-d');
    $filters['date_to'] = date('Y-m-d');
    $todayRevenue = calculateRevenue($filters);
    
    // Ultimos 7 dias
    $filters['date_from'] = date('Y-m-d', strtotime('-7 days'));
    $weekRevenue = calculateRevenue($filters);
    
    // Ultimos 30 dias
    $filters['date_from'] = date('Y-m-d', strtotime('-30 days'));
    $monthRevenue = calculateRevenue($filters);
    
    // Total geral
    unset($filters['date_from'], $filters['date_to']);
    $totalRevenue = calculateRevenue($filters);
    
    return [
        'today' => $todayRevenue,
        'week' => $weekRevenue,
        'month' => $monthRevenue,
        'total' => $totalRevenue
    ];
}

// ============================================
// PIXELS - Conversao de Anuncios
// ============================================

if (!defined('PIXELS_FILE')) {
    define('PIXELS_FILE', DATA_DIR . 'pixels.json');
}

/**
 * Retorna configuracoes de pixels do usuario
 */
function getUserPixels($userId) {
    if (!file_exists(PIXELS_FILE)) {
        file_put_contents(PIXELS_FILE, '{}');
        return [];
    }
    
    $allPixels = readJsonFile(PIXELS_FILE, []);
    return $allPixels[$userId] ?? [];
}

/**
 * Salva configuracoes de pixels do usuario
 */
function saveUserPixels($userId, $pixels) {
    if (!file_exists(PIXELS_FILE)) {
        $dir = dirname(PIXELS_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(PIXELS_FILE, '{}');
    }
    
    $allPixels = readJsonFile(PIXELS_FILE, []);
    $allPixels[$userId] = $pixels;
    
    return writeJsonFile(PIXELS_FILE, $allPixels);
}

/**
 * Dispara pixel de conversao para uma transacao
 */
function fireConversionPixels($userId, $transaction) {
    $pixels = getUserPixels($userId);
    $results = [];
    
    // Facebook CAPI
    if (!empty($pixels['facebook']['enabled']) && !empty($pixels['facebook']['id']) && !empty($pixels['facebook']['token'])) {
        $results['facebook'] = fireFacebookCAPI($pixels['facebook'], $transaction);
    }
    
    // Google Ads Offline Conversion
    if (!empty($pixels['google']['enabled']) && !empty($pixels['google']['id'])) {
        $results['google'] = fireGoogleConversion($pixels['google'], $transaction);
    }
    
    // TikTok Events API
    if (!empty($pixels['tiktok']['enabled']) && !empty($pixels['tiktok']['id']) && !empty($pixels['tiktok']['token'])) {
        $results['tiktok'] = fireTikTokEvent($pixels['tiktok'], $transaction);
    }
    
    // Taboola
    if (!empty($pixels['taboola']['enabled']) && !empty($pixels['taboola']['id'])) {
        $results['taboola'] = fireTaboolaConversion($pixels['taboola'], $transaction);
    }
    
    // Outbrain
    if (!empty($pixels['outbrain']['enabled']) && !empty($pixels['outbrain']['id'])) {
        $results['outbrain'] = fireOutbrainConversion($pixels['outbrain'], $transaction);
    }
    
    // Kwai
    if (!empty($pixels['kwai']['enabled']) && !empty($pixels['kwai']['id'])) {
        $results['kwai'] = fireKwaiConversion($pixels['kwai'], $transaction);
    }
    
    return $results;
}

/**
 * Facebook Conversions API
 */
function fireFacebookCAPI($config, $transaction) {
    $pixelId = $config['id'];
    $accessToken = $config['token'];
    $eventName = $config['event'] ?? 'Purchase';
    
    $eventTime = time();
    $eventId = 'ev_' . $transaction['transaction_id'] . '_' . $eventTime;
    
    $userData = [];
    if (!empty($transaction['buyer_email'])) {
        $userData['em'] = [hash('sha256', strtolower(trim($transaction['buyer_email'])))];
    }
    
    $data = [
        'data' => [
            [
                'event_name' => $eventName,
                'event_time' => $eventTime,
                'event_id' => $eventId,
                'event_source_url' => $transaction['source_url'] ?? '',
                'action_source' => 'website',
                'user_data' => $userData,
                'custom_data' => [
                    'currency' => 'BRL',
                    'value' => floatval($transaction['amount'] ?? 0),
                    'content_name' => $transaction['product_name'] ?? '',
                    'content_ids' => [$transaction['product_id'] ?? ''],
                    'content_type' => 'product'
                ]
            ]
        ],
        'access_token' => $accessToken
    ];
    
    $url = "https://graph.facebook.com/v18.0/{$pixelId}/events";
    
    return sendPixelRequest($url, $data);
}

/**
 * TikTok Events API
 */
function fireTikTokEvent($config, $transaction) {
    $pixelId = $config['id'];
    $accessToken = $config['token'];
    $eventName = $config['event'] ?? 'CompletePayment';
    
    $data = [
        'pixel_code' => $pixelId,
        'event' => $eventName,
        'event_id' => 'ev_' . $transaction['transaction_id'] . '_' . time(),
        'timestamp' => date('c'),
        'context' => [
            'user' => [
                'email' => !empty($transaction['buyer_email']) ? hash('sha256', strtolower(trim($transaction['buyer_email']))) : ''
            ]
        ],
        'properties' => [
            'currency' => 'BRL',
            'value' => floatval($transaction['amount'] ?? 0),
            'contents' => [
                [
                    'content_id' => $transaction['product_id'] ?? '',
                    'content_name' => $transaction['product_name'] ?? '',
                    'content_type' => 'product',
                    'quantity' => 1,
                    'price' => floatval($transaction['amount'] ?? 0)
                ]
            ]
        ]
    ];
    
    $url = "https://business-api.tiktok.com/open_api/v1.3/pixel/track/";
    
    $headers = [
        'Content-Type: application/json',
        'Access-Token: ' . $accessToken
    ];
    
    return sendPixelRequest($url, $data, $headers);
}

/**
 * Google Ads Offline Conversion (via Measurement Protocol)
 */
function fireGoogleConversion($config, $transaction) {
    // Google requer autenticacao OAuth para conversoes offline
    // Aqui usamos o Measurement Protocol simplificado
    $conversionId = str_replace('AW-', '', $config['id']);
    $conversionLabel = $config['label'] ?? '';
    
    // Measurement Protocol GA4
    $url = "https://www.google-analytics.com/mp/collect?measurement_id=G-XXXXXXX&api_secret=";
    
    // Para conversoes reais, seria necessario implementar a Google Ads API
    // Por simplicidade, retornamos sucesso se os dados estao configurados
    return [
        'success' => true,
        'message' => 'Google Ads configurado (requer integracao completa via Google Ads API)',
        'conversion_id' => $conversionId,
        'conversion_label' => $conversionLabel
    ];
}

/**
 * Taboola Conversion
 */
function fireTaboolaConversion($config, $transaction) {
    $accountId = $config['id'];
    $eventName = $config['event'] ?? 'purchase';
    
    $data = [
        'account_id' => $accountId,
        'event_name' => $eventName,
        'revenue' => floatval($transaction['amount'] ?? 0),
        'currency' => 'BRL',
        'order_id' => $transaction['transaction_id'] ?? ''
    ];
    
    $url = "https://trc.taboola.com/actions-handler/log/3/s2s-action";
    
    return sendPixelRequest($url, $data);
}

/**
 * Outbrain Conversion
 */
function fireOutbrainConversion($config, $transaction) {
    $marketerId = $config['id'];
    $eventName = $config['event'] ?? 'purchase';
    
    $data = [
        'marketerId' => $marketerId,
        'eventType' => $eventName,
        'currency' => 'BRL',
        'value' => floatval($transaction['amount'] ?? 0),
        'orderId' => $transaction['transaction_id'] ?? ''
    ];
    
    $url = "https://tr.outbrain.com/unifiedPixel";
    
    return sendPixelRequest($url, $data);
}

/**
 * Kwai Conversion
 */
function fireKwaiConversion($config, $transaction) {
    $pixelId = $config['id'];
    $accessToken = $config['token'] ?? '';
    
    $data = [
        'pixel_id' => $pixelId,
        'event_type' => 'PURCHASE',
        'event_time' => time(),
        'event_id' => 'ev_' . $transaction['transaction_id'],
        'value' => floatval($transaction['amount'] ?? 0),
        'currency' => 'BRL'
    ];
    
    $url = "https://ads-api.kwai.com/rest/n/openapi/pixel/track";
    
    $headers = [
        'Content-Type: application/json',
        'Access-Token: ' . $accessToken
    ];
    
    return sendPixelRequest($url, $data, $headers);
}

/**
 * Envia requisicao para pixel
 */
function sendPixelRequest($url, $data, $headers = []) {
    $ch = curl_init();
    
    $defaultHeaders = ['Content-Type: application/json'];
    $allHeaders = array_merge($defaultHeaders, $headers);
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $allHeaders,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'response' => json_decode($response, true) ?? $response
    ];
}

/**
 * Testa disparo de pixel
 */
function testPixelFire($userId, $platform) {
    $pixels = getUserPixels($userId);
    
    if (empty($pixels[$platform])) {
        return ['success' => false, 'error' => 'Pixel nao configurado'];
    }
    
    if (empty($pixels[$platform]['enabled'])) {
        return ['success' => false, 'error' => 'Pixel nao esta ativo'];
    }
    
    // Transacao de teste
    $testTransaction = [
        'transaction_id' => 'TEST_' . time(),
        'amount' => 1.00,
        'product_name' => 'Produto de Teste',
        'product_id' => 'TEST_PRODUCT',
        'buyer_email' => 'teste@exemplo.com',
        'source_url' => 'https://exemplo.com/checkout'
    ];
    
    switch ($platform) {
        case 'facebook':
            return fireFacebookCAPI($pixels['facebook'], $testTransaction);
        case 'tiktok':
            return fireTikTokEvent($pixels['tiktok'], $testTransaction);
        case 'google':
            return fireGoogleConversion($pixels['google'], $testTransaction);
        case 'taboola':
            return fireTaboolaConversion($pixels['taboola'], $testTransaction);
        case 'outbrain':
            return fireOutbrainConversion($pixels['outbrain'], $testTransaction);
        case 'kwai':
            return fireKwaiConversion($pixels['kwai'], $testTransaction);
        default:
            return ['success' => false, 'error' => 'Plataforma desconhecida'];
    }
}

// ============================================
// MODO DEMO/FAKE - Para Screenshots
// ============================================

if (!defined('FAKE_MODE_FILE')) {
    define('FAKE_MODE_FILE', DATA_DIR . 'fake_mode.json');
}

/**
 * Retorna dados do modo fake
 */
function getFakeModeData() {
    if (!file_exists(FAKE_MODE_FILE)) {
        return ['enabled' => false];
    }
    return readJsonFile(FAKE_MODE_FILE, ['enabled' => false]);
}

/**
 * Verifica se modo fake esta ativo
 */
function isFakeModeEnabled() {
    $data = getFakeModeData();
    return !empty($data['enabled']);
}

/**
 * Constroi dados de revenue fake para o painel
 */
function buildFakeRevenue($fakeMode) {
    $metrics = $fakeMode['metrics'] ?? [];
    $campaigns = $fakeMode['campaigns'] ?? [];
    
    // Monta estrutura de revenue compativel com o painel
    $revenue = [
        'total' => ($metrics['total_paid'] ?? 0) + ($metrics['total_pending'] ?? 0),
        'total_paid' => $metrics['total_paid'] ?? 0,
        'total_pending' => $metrics['total_pending'] ?? 0,
        'total_refunded' => $metrics['total_refunded'] ?? 0,
        'paid' => $metrics['total_paid'] ?? 0,
        'pending' => $metrics['total_pending'] ?? 0,
        'refunded' => $metrics['total_refunded'] ?? 0,
        'chargeback' => 0,
        'count' => [
            'total' => ($metrics['paid_count'] ?? 0) + ($metrics['pending_count'] ?? 0),
            'paid' => $metrics['paid_count'] ?? 0,
            'pending' => $metrics['pending_count'] ?? 0,
            'refunded' => $metrics['refunded_count'] ?? 0,
            'chargeback' => 0
        ],
        'conversion_rate' => $metrics['conversion_rate'] ?? 0,
        'avg_ticket' => $metrics['avg_ticket'] ?? 0,
        'by_gateway' => $fakeMode['by_gateway'] ?? [],
        'by_campaign' => [],
        'by_date' => []
    ];
    
    // Adiciona campanhas fake
    foreach ($campaigns as $camp) {
        $name = $camp['name'] ?? 'campanha-' . uniqid();
        $revenue['by_campaign'][$name] = [
            'paid' => $camp['paid'] ?? 0,
            'pending' => $camp['pending'] ?? 0,
            'refunded' => $camp['refunded'] ?? 0,
            'total' => ($camp['paid'] ?? 0) + ($camp['pending'] ?? 0),
            'count' => ($camp['paid_count'] ?? 0) + ($camp['pending_count'] ?? 0),
            'paid_count' => $camp['paid_count'] ?? 0,
            'pending_count' => $camp['pending_count'] ?? 0,
            'refunded_count' => $camp['refunded_count'] ?? 0
        ];
    }
    
    // Gera dados por data (ultimos 7 dias)
    $totalPaid = $metrics['total_paid'] ?? 0;
    $totalPending = $metrics['total_pending'] ?? 0;
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        // Distribuicao variada para parecer real
        $factor = 0.1 + (mt_rand(0, 30) / 100);
        $revenue['by_date'][$date] = [
            'paid' => round($totalPaid * $factor / 7, 2),
            'pending' => round($totalPending * $factor / 7, 2),
            'total' => round(($totalPaid + $totalPending) * $factor / 7, 2)
        ];
    }
    
    return $revenue;
}
