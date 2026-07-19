<?php
/**
 * COMMANDER V3 - WEBHOOK RECEIVER
 * Recebe webhooks dos gateways de pagamento
 * 
 * URL para configurar no gateway: https://seudominio.com/COMMANDERV3/webhook.php?gateway=NOME_DO_GATEWAY
 * 
 * Gateways suportados:
 * - Hotmart
 * - Kiwify
 * - Monetizze
 * - PerfectPay
 * - Eduzz
 * - Braip
 * - Ticto
 * - IronPay
 * - BlackCat
 * - SkalePay
 * - OtimizePagamentos
 * - VirtualPay
 * - FastSoft
 * - Plumify
 * - Bynet (TechByNet)
 */

// Permite requisicoes de qualquer origem (webhooks)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type, X-Hotmart-Hottok, X-Kiwify-Signature');

// Para requisicoes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Define acesso
define('COMMANDER_ACCESS', true);

// Diretorios
define('DATA_DIR', __DIR__ . '/data/');

// Inclui funcoes auxiliares (necessario para pixels e outras funcoes)
if (file_exists(__DIR__ . '/functions.php')) {
    require_once __DIR__ . '/functions.php';
}

// Garante que diretorios existem
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!is_dir(DATA_DIR . 'transactions/')) mkdir(DATA_DIR . 'transactions/', 0755, true);
if (!is_dir(DATA_DIR . 'webhook_logs/')) mkdir(DATA_DIR . 'webhook_logs/', 0755, true);

// Arquivos
define('FILE_TRANSACTIONS', DATA_DIR . 'transactions.json');
define('FILE_GATEWAYS', DATA_DIR . 'gateways.json');
define('FILE_CAMPAIGNS', DATA_DIR . 'campaigns.json');

/**
 * Funcoes auxiliares (apenas se nao existirem - podem vir do functions.php)
 */
if (!function_exists('readJsonFile')) {
    function readJsonFile($file, $default = []) {
        if (!file_exists($file)) return $default;
        $content = @file_get_contents($file);
        if ($content === false) return $default;
        $data = json_decode($content, true);
        return $data !== null ? $data : $default;
    }
}

if (!function_exists('writeJsonFile')) {
    function writeJsonFile($file, $data) {
        $dir = dirname($file);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
    }
}

function logWebhook($gateway, $data, $result) {
    $logFile = DATA_DIR . 'webhook_logs/' . date('Y-m-d') . '.json';
    $logs = readJsonFile($logFile, []);
    $logs[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'gateway' => $gateway,
        'data' => $data,
        'result' => $result,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    // Limita a 1000 logs por dia
    if (count($logs) > 1000) $logs = array_slice($logs, -1000);
    writeJsonFile($logFile, $logs);
}

/**
 * Mapeia status dos gateways para status padronizado
 */
function normalizeStatus($gateway, $status) {
    $statusMap = [
        'hotmart' => [
            'approved' => 'paid',
            'completed' => 'paid',
            'billet_printed' => 'pending',
            'waiting_payment' => 'pending',
            'pending' => 'pending',
            'refunded' => 'refunded',
            'chargeback' => 'chargeback',
            'cancelled' => 'cancelled',
            'expired' => 'cancelled',
            'dispute' => 'chargeback',
            'blocked' => 'cancelled'
        ],
        'kiwify' => [
            'paid' => 'paid',
            'approved' => 'paid',
            'waiting_payment' => 'pending',
            'pending' => 'pending',
            'refunded' => 'refunded',
            'chargedback' => 'chargeback',
            'cancelled' => 'cancelled'
        ],
        'monetizze' => [
            'Finalizada' => 'paid',
            'Completa' => 'paid',
            'Aguardando pagamento' => 'pending',
            'Em recuperacao' => 'pending',
            'Cancelada' => 'cancelled',
            'Reembolsada' => 'refunded',
            'Devolvida' => 'refunded',
            'Chargeback' => 'chargeback'
        ],
        'perfectpay' => [
            'approved' => 'paid',
            'paid' => 'paid',
            'pending' => 'pending',
            'waiting' => 'pending',
            'refunded' => 'refunded',
            'chargeback' => 'chargeback',
            'cancelled' => 'cancelled'
        ],
        'eduzz' => [
            'paid' => 'paid',
            'waiting_payment' => 'pending',
            'refunded' => 'refunded',
            'chargeback' => 'chargeback',
            'cancelled' => 'cancelled'
        ],
        'braip' => [
            'approved' => 'paid',
            'paid' => 'paid',
            'pending' => 'pending',
            'refunded' => 'refunded',
            'chargeback' => 'chargeback'
        ],
        'ticto' => [
            'paid' => 'paid',
            'approved' => 'paid',
            'pending' => 'pending',
            'refunded' => 'refunded',
            'chargeback' => 'chargeback'
        ],
        'ironpay' => [
            'paid' => 'paid',
            'approved' => 'paid',
            'pending' => 'pending',
            'waiting_payment' => 'pending',
            'refunded' => 'refunded',
            'chargeback' => 'chargeback',
            'cancelled' => 'cancelled',
            'expired' => 'cancelled'
        ],
        'blackcat' => [
            'paid' => 'paid',
            'pending' => 'pending',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'chargeback' => 'chargeback',
            'failed' => 'cancelled',
            'processing' => 'pending'
        ],
        'skalepay' => [
            'paid' => 'paid',
            'approved' => 'paid',
            'pending' => 'pending',
            'waiting_payment' => 'pending',
            'refunded' => 'refunded',
            'chargeback' => 'chargeback',
            'cancelled' => 'cancelled'
        ],
        'otimizepagamentos' => [
            'paid' => 'paid',
            'approved' => 'paid',
            'pending' => 'pending',
            'waiting_payment' => 'pending',
            'refunded' => 'refunded',
            'chargeback' => 'chargeback',
            'cancelled' => 'cancelled'
        ],
        'virtualpay' => [
            'paid' => 'paid',
            'in_process' => 'pending',
            'pending' => 'pending',
            'in_analysis' => 'pending',
            'refund' => 'refunded',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
            'expired' => 'cancelled',
            'denied' => 'cancelled'
        ],
        'fastsoft' => [
            'paid' => 'paid',
            'approved' => 'paid',
            'pending' => 'pending',
            'processing' => 'pending',
            'refunded' => 'refunded',
            'chargeback' => 'chargeback',
            'cancelled' => 'cancelled'
        ],
        'plumify' => [
            'paid' => 'paid',
            'approved' => 'paid',
            'pending' => 'pending',
            'waiting_payment' => 'pending',
            'refunded' => 'refunded',
            'chargeback' => 'chargeback',
            'cancelled' => 'cancelled',
            'expired' => 'cancelled'
        ],
        'bynet' => [
            'paid' => 'paid',
            'approved' => 'paid',
            'completed' => 'paid',
            'pending' => 'pending',
            'processing' => 'pending',
            'refunded' => 'refunded',
            'chargeback' => 'chargeback',
            'cancelled' => 'cancelled',
            'failed' => 'cancelled'
        ]
    ];
    
    $status = strtolower(trim($status));
    return $statusMap[$gateway][$status] ?? $statusMap[$gateway][ucfirst($status)] ?? 'unknown';
}

/**
 * Extrai dados do webhook baseado no gateway
 */
function parseWebhookData($gateway, $payload) {
    $data = [
        'transaction_id' => '',
        'status' => '',
        'status_raw' => '',
        'amount' => 0,
        'currency' => 'BRL',
        'product_id' => '',
        'product_name' => '',
        'buyer_email' => '',
        'buyer_name' => '',
        'payment_method' => '',
        'utm_source' => '',
        'utm_campaign' => '',
        'utm_medium' => '',
        'utm_content' => '',
        'utm_term' => '',
        'src' => '',
        'sck' => ''
    ];
    
    switch ($gateway) {
        case 'hotmart':
            $data['transaction_id'] = $payload['data']['purchase']['transaction'] ?? $payload['transaction'] ?? '';
            $data['status_raw'] = $payload['data']['purchase']['status'] ?? $payload['status'] ?? '';
            $data['amount'] = floatval($payload['data']['purchase']['price']['value'] ?? $payload['price'] ?? 0);
            $data['currency'] = $payload['data']['purchase']['price']['currency_code'] ?? 'BRL';
            $data['product_id'] = $payload['data']['product']['id'] ?? $payload['prod'] ?? '';
            $data['product_name'] = $payload['data']['product']['name'] ?? $payload['prod_name'] ?? '';
            $data['buyer_email'] = $payload['data']['buyer']['email'] ?? $payload['email'] ?? '';
            $data['buyer_name'] = $payload['data']['buyer']['name'] ?? $payload['name'] ?? '';
            $data['payment_method'] = $payload['data']['purchase']['payment']['type'] ?? '';
            // UTMs da Hotmart
            $tracking = $payload['data']['purchase']['tracking'] ?? [];
            $data['utm_source'] = $tracking['source'] ?? $payload['utm_source'] ?? '';
            $data['utm_campaign'] = $tracking['utm_campaign'] ?? $payload['utm_campaign'] ?? '';
            $data['utm_medium'] = $tracking['utm_medium'] ?? $payload['utm_medium'] ?? '';
            $data['utm_content'] = $tracking['utm_content'] ?? $payload['utm_content'] ?? '';
            $data['src'] = $tracking['src'] ?? $payload['src'] ?? '';
            $data['sck'] = $tracking['sck'] ?? $payload['sck'] ?? '';
            break;
            
        case 'kiwify':
            $data['transaction_id'] = $payload['order_id'] ?? $payload['subscription_id'] ?? '';
            $data['status_raw'] = $payload['order_status'] ?? '';
            $data['amount'] = floatval($payload['product_price'] ?? $payload['Charges'][0]['amount'] ?? 0) / 100;
            $data['product_id'] = $payload['product_id'] ?? '';
            $data['product_name'] = $payload['product_name'] ?? '';
            $data['buyer_email'] = $payload['Customer']['email'] ?? $payload['customer_email'] ?? '';
            $data['buyer_name'] = $payload['Customer']['full_name'] ?? $payload['customer_name'] ?? '';
            $data['payment_method'] = $payload['payment_method'] ?? '';
            // UTMs Kiwify
            $data['utm_source'] = $payload['TrackingParameters']['utm_source'] ?? '';
            $data['utm_campaign'] = $payload['TrackingParameters']['utm_campaign'] ?? '';
            $data['utm_medium'] = $payload['TrackingParameters']['utm_medium'] ?? '';
            $data['utm_content'] = $payload['TrackingParameters']['utm_content'] ?? '';
            $data['src'] = $payload['TrackingParameters']['src'] ?? '';
            $data['sck'] = $payload['TrackingParameters']['sck'] ?? '';
            break;
            
        case 'monetizze':
            $data['transaction_id'] = $payload['venda']['codigo'] ?? '';
            $data['status_raw'] = $payload['venda']['status'] ?? '';
            $data['amount'] = floatval($payload['venda']['valorLiquido'] ?? $payload['venda']['valor'] ?? 0);
            $data['product_id'] = $payload['produto']['codigo'] ?? '';
            $data['product_name'] = $payload['produto']['nome'] ?? '';
            $data['buyer_email'] = $payload['comprador']['email'] ?? '';
            $data['buyer_name'] = $payload['comprador']['nome'] ?? '';
            $data['payment_method'] = $payload['venda']['formaPagamento'] ?? '';
            // UTMs Monetizze
            $data['utm_source'] = $payload['venda']['utm_source'] ?? '';
            $data['utm_campaign'] = $payload['venda']['utm_campaign'] ?? '';
            $data['src'] = $payload['venda']['src'] ?? '';
            break;
            
        case 'perfectpay':
            $data['transaction_id'] = $payload['sale_code'] ?? $payload['code'] ?? '';
            $data['status_raw'] = $payload['sale_status'] ?? $payload['status'] ?? '';
            $data['amount'] = floatval($payload['sale_amount'] ?? $payload['amount'] ?? 0);
            $data['product_id'] = $payload['product_code'] ?? '';
            $data['product_name'] = $payload['product_name'] ?? '';
            $data['buyer_email'] = $payload['customer']['email'] ?? '';
            $data['buyer_name'] = $payload['customer']['name'] ?? '';
            $data['payment_method'] = $payload['payment_type'] ?? '';
            // UTMs PerfectPay
            $data['utm_source'] = $payload['utm_source'] ?? '';
            $data['utm_campaign'] = $payload['utm_campaign'] ?? '';
            $data['src'] = $payload['src'] ?? '';
            $data['sck'] = $payload['sck'] ?? '';
            break;
            
        case 'eduzz':
            $data['transaction_id'] = $payload['trans_cod'] ?? $payload['sale_id'] ?? '';
            $data['status_raw'] = $payload['trans_status'] ?? '';
            $data['amount'] = floatval($payload['trans_value'] ?? 0);
            $data['product_id'] = $payload['product_id'] ?? '';
            $data['product_name'] = $payload['product_name'] ?? '';
            $data['buyer_email'] = $payload['cus_email'] ?? '';
            $data['buyer_name'] = $payload['cus_name'] ?? '';
            $data['payment_method'] = $payload['trans_paymenttype'] ?? '';
            // UTMs Eduzz
            $data['utm_source'] = $payload['utm_source'] ?? '';
            $data['utm_campaign'] = $payload['utm_campaign'] ?? '';
            $data['src'] = $payload['tracker'] ?? '';
            break;
            
        case 'braip':
            $data['transaction_id'] = $payload['transaction_id'] ?? '';
            $data['status_raw'] = $payload['status'] ?? '';
            $data['amount'] = floatval($payload['amount'] ?? 0);
            $data['product_id'] = $payload['product_id'] ?? '';
            $data['product_name'] = $payload['product_name'] ?? '';
            $data['buyer_email'] = $payload['customer']['email'] ?? '';
            $data['buyer_name'] = $payload['customer']['name'] ?? '';
            // UTMs Braip
            $data['utm_source'] = $payload['utm_source'] ?? '';
            $data['utm_campaign'] = $payload['utm_campaign'] ?? '';
            break;
            
        case 'ticto':
            $data['transaction_id'] = $payload['transaction_code'] ?? '';
            $data['status_raw'] = $payload['status'] ?? '';
            $data['amount'] = floatval($payload['value'] ?? 0);
            $data['product_id'] = $payload['product_id'] ?? '';
            $data['product_name'] = $payload['product_name'] ?? '';
            $data['buyer_email'] = $payload['buyer_email'] ?? '';
            $data['buyer_name'] = $payload['buyer_name'] ?? '';
            // UTMs Ticto
            $data['utm_source'] = $payload['utm_source'] ?? '';
            $data['utm_campaign'] = $payload['utm_campaign'] ?? '';
            break;
            
        case 'ironpay':
            // IronPay - Payload de postback
            $data['transaction_id'] = $payload['transaction_hash'] ?? $payload['transactionId'] ?? '';
            $data['status_raw'] = $payload['status'] ?? '';
            $data['amount'] = floatval($payload['amount'] ?? 0) / 100; // Centavos para reais
            $data['product_id'] = $payload['product_id'] ?? '';
            $data['product_name'] = $payload['product_name'] ?? '';
            $data['buyer_email'] = $payload['customer']['email'] ?? $payload['email'] ?? '';
            $data['buyer_name'] = $payload['customer']['name'] ?? $payload['name'] ?? '';
            $data['payment_method'] = $payload['payment_method'] ?? '';
            // UTMs IronPay
            $data['utm_source'] = $payload['utm_source'] ?? '';
            $data['utm_campaign'] = $payload['utm_campaign'] ?? '';
            $data['utm_medium'] = $payload['utm_medium'] ?? '';
            $data['utm_content'] = $payload['utm_content'] ?? '';
            break;
            
        case 'blackcat':
            // BlackCat - Eventos de webhook (transaction.paid, transaction.created, etc)
            $data['transaction_id'] = $payload['transactionId'] ?? '';
            $data['status_raw'] = $payload['status'] ?? '';
            $data['amount'] = floatval($payload['amount'] ?? 0) / 100; // Centavos para reais
            $data['product_id'] = $payload['productId'] ?? '';
            $data['product_name'] = $payload['items'][0]['title'] ?? '';
            $data['buyer_email'] = $payload['customer']['email'] ?? '';
            $data['buyer_name'] = $payload['customer']['name'] ?? '';
            $data['payment_method'] = $payload['paymentMethod'] ?? '';
            // UTMs BlackCat (vem no objeto utm)
            $utm = $payload['utm'] ?? [];
            $data['utm_source'] = $utm['utm_source'] ?? $payload['utm_source'] ?? '';
            $data['utm_campaign'] = $utm['utm_campaign'] ?? $payload['utm_campaign'] ?? '';
            $data['utm_medium'] = $utm['utm_medium'] ?? $payload['utm_medium'] ?? '';
            $data['utm_content'] = $utm['utm_content'] ?? $payload['utm_content'] ?? '';
            $data['utm_term'] = $utm['utm_term'] ?? $payload['utm_term'] ?? '';
            break;
            
        case 'skalepay':
            // SkalePay - Segue padrao similar a Pagar.me
            $data['transaction_id'] = $payload['id'] ?? $payload['transaction_id'] ?? '';
            $data['status_raw'] = $payload['status'] ?? $payload['current_status'] ?? '';
            $data['amount'] = floatval($payload['amount'] ?? $payload['paid_amount'] ?? 0) / 100;
            $data['product_id'] = $payload['metadata']['product_id'] ?? '';
            $data['product_name'] = $payload['metadata']['product_name'] ?? $payload['items'][0]['title'] ?? '';
            $data['buyer_email'] = $payload['customer']['email'] ?? '';
            $data['buyer_name'] = $payload['customer']['name'] ?? '';
            $data['payment_method'] = $payload['payment_method'] ?? '';
            // UTMs SkalePay
            $metadata = $payload['metadata'] ?? [];
            $data['utm_source'] = $metadata['utm_source'] ?? '';
            $data['utm_campaign'] = $metadata['utm_campaign'] ?? '';
            $data['utm_medium'] = $metadata['utm_medium'] ?? '';
            break;
            
        case 'otimizepagamentos':
            // Otimize Pagamentos - Similar ao SkalePay (mesma base)
            $data['transaction_id'] = $payload['id'] ?? $payload['transaction_id'] ?? '';
            $data['status_raw'] = $payload['status'] ?? $payload['current_status'] ?? '';
            $data['amount'] = floatval($payload['amount'] ?? $payload['paid_amount'] ?? 0) / 100;
            $data['product_id'] = $payload['metadata']['product_id'] ?? '';
            $data['product_name'] = $payload['metadata']['product_name'] ?? $payload['items'][0]['title'] ?? '';
            $data['buyer_email'] = $payload['customer']['email'] ?? '';
            $data['buyer_name'] = $payload['customer']['name'] ?? '';
            $data['payment_method'] = $payload['payment_method'] ?? '';
            // UTMs
            $metadata = $payload['metadata'] ?? [];
            $data['utm_source'] = $metadata['utm_source'] ?? '';
            $data['utm_campaign'] = $metadata['utm_campaign'] ?? '';
            break;
            
        case 'virtualpay':
            // VirtualPay - Webhook transaction.updated
            $data['transaction_id'] = $payload['uuid'] ?? $payload['transaction_uuid'] ?? '';
            $data['status_raw'] = $payload['status'] ?? '';
            $data['amount'] = floatval($payload['amount'] ?? 0); // Ja vem em reais
            $data['product_id'] = $payload['product_id'] ?? $payload['metadata']['product_id'] ?? '';
            $data['product_name'] = $payload['description'] ?? $payload['metadata']['product_name'] ?? '';
            $data['buyer_email'] = $payload['payer']['email'] ?? $payload['customer_email'] ?? '';
            $data['buyer_name'] = $payload['payer']['name'] ?? $payload['customer_name'] ?? '';
            $data['payment_method'] = $payload['type'] ?? ''; // deposit, withdraw, charge
            // UTMs VirtualPay
            $metadata = $payload['metadata'] ?? [];
            $data['utm_source'] = $metadata['utm_source'] ?? '';
            $data['utm_campaign'] = $metadata['utm_campaign'] ?? '';
            break;
            
        case 'fastsoft':
            // FastSoft Brasil
            $data['transaction_id'] = $payload['transaction_id'] ?? $payload['id'] ?? '';
            $data['status_raw'] = $payload['status'] ?? '';
            $data['amount'] = floatval($payload['amount'] ?? $payload['value'] ?? 0);
            $data['product_id'] = $payload['product_id'] ?? '';
            $data['product_name'] = $payload['product_name'] ?? $payload['description'] ?? '';
            $data['buyer_email'] = $payload['customer']['email'] ?? $payload['buyer_email'] ?? '';
            $data['buyer_name'] = $payload['customer']['name'] ?? $payload['buyer_name'] ?? '';
            $data['payment_method'] = $payload['payment_method'] ?? $payload['payment_type'] ?? '';
            // UTMs
            $data['utm_source'] = $payload['utm_source'] ?? $payload['metadata']['utm_source'] ?? '';
            $data['utm_campaign'] = $payload['utm_campaign'] ?? $payload['metadata']['utm_campaign'] ?? '';
            break;
            
        case 'plumify':
            // Plumify - Similar ao IronPay (mesma estrutura de docs)
            $data['transaction_id'] = $payload['transaction_hash'] ?? $payload['transactionId'] ?? '';
            $data['status_raw'] = $payload['status'] ?? '';
            $data['amount'] = floatval($payload['amount'] ?? 0) / 100; // Centavos
            $data['product_id'] = $payload['product_id'] ?? '';
            $data['product_name'] = $payload['product_name'] ?? '';
            $data['buyer_email'] = $payload['customer']['email'] ?? $payload['email'] ?? '';
            $data['buyer_name'] = $payload['customer']['name'] ?? $payload['name'] ?? '';
            $data['payment_method'] = $payload['payment_method'] ?? '';
            // UTMs
            $data['utm_source'] = $payload['utm_source'] ?? '';
            $data['utm_campaign'] = $payload['utm_campaign'] ?? '';
            $data['utm_medium'] = $payload['utm_medium'] ?? '';
            break;
            
        case 'bynet':
            // Bynet (TechByNet)
            $data['transaction_id'] = $payload['transaction_id'] ?? $payload['id'] ?? $payload['uuid'] ?? '';
            $data['status_raw'] = $payload['status'] ?? '';
            $data['amount'] = floatval($payload['amount'] ?? $payload['value'] ?? 0);
            $data['product_id'] = $payload['product_id'] ?? $payload['product']['id'] ?? '';
            $data['product_name'] = $payload['product_name'] ?? $payload['product']['name'] ?? '';
            $data['buyer_email'] = $payload['customer']['email'] ?? $payload['buyer']['email'] ?? '';
            $data['buyer_name'] = $payload['customer']['name'] ?? $payload['buyer']['name'] ?? '';
            $data['payment_method'] = $payload['payment_method'] ?? $payload['payment_type'] ?? '';
            // UTMs Bynet
            $data['utm_source'] = $payload['utm_source'] ?? $payload['tracking']['utm_source'] ?? '';
            $data['utm_campaign'] = $payload['utm_campaign'] ?? $payload['tracking']['utm_campaign'] ?? '';
            $data['utm_medium'] = $payload['utm_medium'] ?? $payload['tracking']['utm_medium'] ?? '';
            break;
    }
    
    // Normaliza status
    $data['status'] = normalizeStatus($gateway, $data['status_raw']);
    
    return $data;
}

/**
 * Encontra a campanha pelo UTM ou src
 */
function findCampaignByUtm($utmSource, $utmCampaign, $src, $sck) {
    $campaigns = readJsonFile(FILE_CAMPAIGNS, []);
    
    foreach ($campaigns as $campaign) {
        // Verifica se algum UTM bate com o slug ou nome da campanha
        $slug = strtolower($campaign['slug'] ?? '');
        $name = strtolower($campaign['name'] ?? '');
        
        $utmSource = strtolower($utmSource);
        $utmCampaign = strtolower($utmCampaign);
        $src = strtolower($src);
        $sck = strtolower($sck);
        
        if (
            ($utmSource && ($utmSource === $slug || strpos($utmSource, $slug) !== false)) ||
            ($utmCampaign && ($utmCampaign === $slug || strpos($utmCampaign, $slug) !== false)) ||
            ($src && ($src === $slug || strpos($src, $slug) !== false)) ||
            ($sck && ($sck === $slug || strpos($sck, $slug) !== false)) ||
            ($utmSource && strpos($name, $utmSource) !== false) ||
            ($utmCampaign && strpos($name, $utmCampaign) !== false)
        ) {
            return $campaign;
        }
    }
    
    return null;
}

/**
 * Salva ou atualiza transacao
 */
function saveWebhookTransaction($gateway, $data, $payload) {
    $transactions = readJsonFile(FILE_TRANSACTIONS, []);
    
    // Procura transacao existente
    $existingIndex = null;
    foreach ($transactions as $index => $trans) {
        if ($trans['transaction_id'] === $data['transaction_id'] && $trans['gateway'] === $gateway) {
            $existingIndex = $index;
            break;
        }
    }
    
    // Encontra campanha relacionada
    $campaign = findCampaignByUtm(
        $data['utm_source'],
        $data['utm_campaign'],
        $data['src'],
        $data['sck']
    );
    
    $transaction = [
        'id' => $existingIndex !== null ? $transactions[$existingIndex]['id'] : uniqid('trans_'),
        'gateway' => $gateway,
        'transaction_id' => $data['transaction_id'],
        'status' => $data['status'],
        'status_raw' => $data['status_raw'],
        'amount' => $data['amount'],
        'currency' => $data['currency'],
        'product_id' => $data['product_id'],
        'product_name' => $data['product_name'],
        'buyer_email' => $data['buyer_email'],
        'buyer_name' => $data['buyer_name'],
        'payment_method' => $data['payment_method'],
        'utm_source' => $data['utm_source'],
        'utm_campaign' => $data['utm_campaign'],
        'utm_medium' => $data['utm_medium'],
        'utm_content' => $data['utm_content'],
        'utm_term' => $data['utm_term'],
        'src' => $data['src'],
        'sck' => $data['sck'],
        'campaign_slug' => $campaign['slug'] ?? '',
        'campaign_name' => $campaign['name'] ?? '',
        'user_id' => $campaign['user_id'] ?? '',
        'created_at' => $existingIndex !== null ? $transactions[$existingIndex]['created_at'] : date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'raw_payload' => $payload
    ];
    
    if ($existingIndex !== null) {
        $transactions[$existingIndex] = $transaction;
    } else {
        array_unshift($transactions, $transaction);
    }
    
    // Limita a 50000 transacoes
    if (count($transactions) > 50000) {
        $transactions = array_slice($transactions, 0, 50000);
    }
    
    writeJsonFile(FILE_TRANSACTIONS, $transactions);
    
    return $transaction;
}

/**
 * Valida token do gateway (opcional mas recomendado)
 */
function validateGatewayToken($gateway) {
    $gateways = readJsonFile(FILE_GATEWAYS, []);
    
    if (!isset($gateways[$gateway]) || empty($gateways[$gateway]['token'])) {
        return true; // Se nao tem token configurado, aceita qualquer webhook
    }
    
    $token = $gateways[$gateway]['token'];
    
    switch ($gateway) {
        case 'hotmart':
            $hottok = $_SERVER['HTTP_X_HOTMART_HOTTOK'] ?? '';
            return $hottok === $token;
            
        case 'kiwify':
            $signature = $_SERVER['HTTP_X_KIWIFY_SIGNATURE'] ?? '';
            return $signature === $token;
            
        default:
            // Para outros gateways, verifica token na query string ou header
            $receivedToken = $_GET['token'] ?? $_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? '';
            return $receivedToken === $token;
    }
}

// ===========================================
// PROCESSAMENTO DO WEBHOOK
// ===========================================

// Detecta gateway pelo parametro ou header
$gateway = strtolower($_GET['gateway'] ?? '');

// Auto-detecta gateway se nao especificado
if (empty($gateway)) {
    if (!empty($_SERVER['HTTP_X_HOTMART_HOTTOK'])) {
        $gateway = 'hotmart';
    } elseif (!empty($_SERVER['HTTP_X_KIWIFY_SIGNATURE'])) {
        $gateway = 'kiwify';
    }
}

// Gateways suportados
$supportedGateways = [
    'hotmart', 'kiwify', 'monetizze', 'perfectpay', 'eduzz', 'braip', 'ticto',
    'ironpay', 'blackcat', 'skalepay', 'otimizepagamentos', 'virtualpay', 'fastsoft', 'plumify', 'bynet'
];

if (!in_array($gateway, $supportedGateways)) {
    logWebhook('unknown', ['gateway' => $gateway], 'gateway_not_supported');
    http_response_code(400);
    echo json_encode(['error' => 'Gateway nao suportado. Use: ' . implode(', ', $supportedGateways)]);
    exit;
}

// Le payload
$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload, true);

// Se nao for JSON, tenta parse de form-data
if ($payload === null && !empty($_POST)) {
    $payload = $_POST;
}

if (empty($payload)) {
    logWebhook($gateway, ['raw' => $rawPayload], 'empty_payload');
    http_response_code(400);
    echo json_encode(['error' => 'Payload vazio']);
    exit;
}

// Valida token (se configurado)
if (!validateGatewayToken($gateway)) {
    logWebhook($gateway, $payload, 'invalid_token');
    http_response_code(401);
    echo json_encode(['error' => 'Token invalido']);
    exit;
}

// Parseia dados do webhook
$data = parseWebhookData($gateway, $payload);

if (empty($data['transaction_id'])) {
    logWebhook($gateway, $payload, 'missing_transaction_id');
    http_response_code(400);
    echo json_encode(['error' => 'Transaction ID nao encontrado']);
    exit;
}

// Salva transacao
$transaction = saveWebhookTransaction($gateway, $data, $payload);

// Log do webhook
logWebhook($gateway, [
    'transaction_id' => $data['transaction_id'],
    'status' => $data['status'],
    'amount' => $data['amount'],
    'campaign' => $transaction['campaign_slug']
], 'success');

// Dispara pixels de conversao se a venda foi PAGA
$pixelResults = [];
if ($data['status'] === 'paid') {
    // Busca o usuario associado a campanha para disparar os pixels dele
    $userId = $transaction['user_id'] ?? 'default';
    
    // Verifica se a funcao existe (pode estar no functions.php)
    if (function_exists('fireConversionPixels')) {
        $pixelResults = fireConversionPixels($userId, [
            'transaction_id' => $data['transaction_id'],
            'amount' => $data['amount'],
            'product_name' => $data['product_name'],
            'product_id' => $data['product_id'],
            'buyer_email' => $data['buyer_email'],
            'source_url' => $data['utm_source'] ?? ''
        ]);
    }
}

// Resposta de sucesso
http_response_code(200);
echo json_encode([
    'success' => true,
    'transaction_id' => $data['transaction_id'],
    'status' => $data['status'],
    'campaign' => $transaction['campaign_slug'] ?: 'nao_vinculada',
    'pixels_fired' => !empty($pixelResults) ? array_keys($pixelResults) : []
]);
