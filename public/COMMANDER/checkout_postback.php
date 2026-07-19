<?php
/**
 * ============================================
 * checkout_postback.php - COMMANDER CHECKOUT TRACKING V9.0
 * ============================================
 * 
 * Recebe notificacoes de INICIO DE CHECKOUT (IC)
 * Pode ser chamado via GET ou POST
 * 
 * Parametros:
 * - click_id: ID do click (obrigatorio)
 * 
 * Exemplo de URL:
 * - GET: checkout_postback.php?click_id=clk_abc123
 */

error_reporting(0);
ini_set('display_errors', 0);

// Permite CORS para chamadas externas
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ===============================
// ARQUIVOS DE DADOS
// ===============================
$clicks_file = __DIR__ . '/clicks.json';

if (!file_exists($clicks_file)) {
    echo json_encode(['success' => false, 'error' => 'Sistema nao inicializado']);
    exit;
}

// ===============================
// RECEBE PARAMETROS (GET ou POST)
// ===============================
$click_id = $_REQUEST['click_id'] ?? $_REQUEST['src'] ?? $_REQUEST['subid'] ?? '';

// Limpa e valida
$click_id = trim($click_id);

// ===============================
// VALIDA CLICK_ID
// ===============================
if (empty($click_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'click_id nao fornecido',
        'received' => $_REQUEST
    ]);
    exit;
}

// ===============================
// BUSCA E ATUALIZA CLICK
// ===============================
$clicks = json_decode(file_get_contents($clicks_file), true) ?? [];

if (!isset($clicks[$click_id])) {
    http_response_code(404);
    echo json_encode([
        'success' => false, 
        'error' => 'click_id nao encontrado',
        'click_id' => $click_id
    ]);
    exit;
}

// Verifica se ja foi marcado como checkout
if ($clicks[$click_id]['checkout_started'] ?? false) {
    echo json_encode([
        'success' => true,
        'message' => 'Checkout ja registrado anteriormente',
        'click_id' => $click_id
    ]);
    exit;
}

// Atualiza como checkout iniciado
$clicks[$click_id]['checkout_started'] = true;
$clicks[$click_id]['checkout_date'] = date('Y-m-d H:i:s');
$clicks[$click_id]['checkout_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';

// Salva
file_put_contents($clicks_file, json_encode($clicks, JSON_PRETTY_PRINT));

// ===============================
// RESPOSTA DE SUCESSO
// ===============================
echo json_encode([
    'success' => true,
    'message' => 'Inicio de checkout registrado com sucesso',
    'click_id' => $click_id,
    'user' => $clicks[$click_id]['user'] ?? '',
    'campaign' => $clicks[$click_id]['utm_campaign'] ?? '',
    'platform' => $clicks[$click_id]['platform'] ?? ''
]);
