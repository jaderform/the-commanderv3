<?php
/**
 * ============================================
 * postback.php - COMMANDER POSTBACK V9.0
 * ============================================
 * 
 * Recebe notificacoes de VENDA (conversao)
 * Pode ser chamado via GET ou POST
 * 
 * Parametros:
 * - click_id: ID do click (obrigatorio)
 * - payout / revenue / value: Valor da venda (opcional, padrao: 0)
 * 
 * Exemplos de URL:
 * - GET: postback.php?click_id=clk_abc123&payout=97.00
 * - POST: click_id=clk_abc123&payout=97.00
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
$click_id = $_REQUEST['click_id'] ?? $_REQUEST['src'] ?? $_REQUEST['subid'] ?? $_REQUEST['transaction_id'] ?? '';
$payout   = $_REQUEST['payout'] ?? $_REQUEST['revenue'] ?? $_REQUEST['value'] ?? $_REQUEST['amount'] ?? 0;

// Limpa e valida
$click_id = trim($click_id);
$payout   = floatval(str_replace(',', '.', $payout));

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

// Atualiza como convertido
$clicks[$click_id]['converted'] = true;
$clicks[$click_id]['revenue'] = $payout;
$clicks[$click_id]['conversion_date'] = date('Y-m-d H:i:s');
$clicks[$click_id]['conversion_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';

// Salva
file_put_contents($clicks_file, json_encode($clicks, JSON_PRETTY_PRINT));

// ===============================
// RESPOSTA DE SUCESSO
// ===============================
echo json_encode([
    'success' => true,
    'message' => 'Conversao registrada com sucesso',
    'click_id' => $click_id,
    'revenue' => $payout,
    'user' => $clicks[$click_id]['user'] ?? '',
    'campaign' => $clicks[$click_id]['utm_campaign'] ?? '',
    'platform' => $clicks[$click_id]['platform'] ?? ''
]);
