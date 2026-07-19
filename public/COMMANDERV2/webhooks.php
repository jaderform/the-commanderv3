<?php
/**
 * COMMANDER V10.4 - Gerenciador de Webhooks
 *
 * Arquivo NOVO criado na v10.4.
 * Expõe endpoints para criar, listar, testar e excluir webhooks
 * a partir do painel administrativo.
 *
 * Todos os endpoints exigem autenticação via token de API.
 *
 * Plataformas suportadas: telegram, discord
 * Eventos suportados: bot_detected, block_alert, conversion, pause_campaign
 */

define('COMMANDER_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$security = CommanderSecurity::getInstance();

// ── Autenticação ───────────────────────────────────────────────────────────
$token = $_SERVER['HTTP_X_API_TOKEN'] ?? ($_POST['api_token'] ?? $_GET['api_token'] ?? '');
if (!hash_equals(API_TOKEN, $token)) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

if (!$security->checkRateLimit($security->getRealIP(), 30, 60)) {
    jsonResponse(['error' => 'Rate limit exceeded'], 429);
}

// ── Roteamento ─────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$action = $security->sanitizeInput($_GET['action'] ?? '', 'alphanumeric');

switch ("{$method}:{$action}") {

    case 'GET:list':
        listWebhooks();
        break;

    case 'POST:create':
        createWebhook();
        break;

    case 'POST:test':
        testWebhook();
        break;

    case 'POST:delete':
    case 'DELETE:delete':
        deleteWebhookById();
        break;

    case 'POST:toggle':
        toggleWebhook();
        break;

    case 'GET:events':
        // Retorna os eventos disponíveis para configuração
        jsonResponse(['events' => [
            ['id' => 'bot_detected',   'label' => 'Bot detectado',                  'description' => 'Dispara a cada bot identificado pelo sistema'],
            ['id' => 'block_alert',    'label' => 'Alerta de taxa de bloqueio',      'description' => 'Dispara quando a taxa de bloqueio ultrapassar ' . BLOCK_RATE_ALERT_THRESHOLD . '%'],
            ['id' => 'conversion',     'label' => 'Nova conversão registrada',       'description' => 'Dispara quando uma conversão é registrada via API'],
            ['id' => 'pause_campaign', 'label' => 'Campanha pausada automaticamente','description' => 'Dispara quando o sistema pausa uma campanha por excesso de bots'],
        ]]);
        break;

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

// ══════════════════════════════════════════════════════════════════════════════
// HANDLERS
// ══════════════════════════════════════════════════════════════════════════════

function listWebhooks() {
    $webhooks = getWebhooks();

    // Mascara dados sensíveis na listagem
    $safe = array_map(function($wh) {
        if (!empty($wh['bot_token'])) {
            $tok = $wh['bot_token'];
            $wh['bot_token'] = substr($tok, 0, 6) . str_repeat('*', max(0, strlen($tok) - 10)) . substr($tok, -4);
        }
        return $wh;
    }, $webhooks);

    jsonResponse(['status' => 'success', 'webhooks' => array_values($safe), 'total' => count($safe)]);
}

function createWebhook() {
    global $security;

    $body = getJsonBody();

    $platform = $security->sanitizeInput($body['platform'] ?? '', 'alphanumeric');
    $events   = $body['events']  ?? [];
    $label    = $security->sanitizeInput($body['label'] ?? '', 'string');
    $enabled  = (bool)($body['enabled'] ?? true);

    $validPlatforms = ['telegram', 'discord'];
    $validEvents    = ['bot_detected', 'block_alert', 'conversion', 'pause_campaign'];

    if (!in_array($platform, $validPlatforms)) {
        jsonResponse(['error' => 'Plataforma inválida. Use: ' . implode(', ', $validPlatforms)], 400);
    }

    if (empty($events) || !is_array($events)) {
        jsonResponse(['error' => 'Selecione ao menos um evento'], 400);
    }

    $events = array_values(array_filter($events, fn($e) => in_array($e, $validEvents)));

    $webhook = [
        'id'       => 'wh_' . bin2hex(random_bytes(6)),
        'platform' => $platform,
        'label'    => $label ?: ucfirst($platform) . ' Webhook',
        'events'   => $events,
        'enabled'  => $enabled,
        'created'  => date('Y-m-d H:i:s'),
    ];

    if ($platform === 'telegram') {
        $botToken = $security->sanitizeInput($body['bot_token'] ?? '', 'string');
        $chatId   = $security->sanitizeInput($body['chat_id']   ?? '', 'string');

        if (empty($botToken) || empty($chatId)) {
            jsonResponse(['error' => 'bot_token e chat_id são obrigatórios para Telegram'], 400);
        }

        $webhook['bot_token'] = $botToken;
        $webhook['chat_id']   = $chatId;

    } elseif ($platform === 'discord') {
        $url = $security->sanitizeInput($body['url'] ?? '', 'url');

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            jsonResponse(['error' => 'URL do Discord Webhook inválida'], 400);
        }

        if (strpos($url, 'discord.com/api/webhooks/') === false) {
            jsonResponse(['error' => 'URL não parece ser um webhook do Discord válido'], 400);
        }

        $webhook['url'] = $url;
    }

    if (!saveWebhook($webhook)) {
        jsonResponse(['error' => 'Erro ao salvar webhook'], 500);
    }

    jsonResponse(['status' => 'success', 'webhook' => $webhook, 'message' => 'Webhook criado com sucesso']);
}

function testWebhook() {
    global $security;

    $body = getJsonBody();
    $id   = $security->sanitizeInput($body['id'] ?? '', 'alphanumeric');

    if (empty($id)) {
        jsonResponse(['error' => 'ID do webhook é obrigatório'], 400);
    }

    $wh = null;
    foreach (getWebhooks() as $w) {
        if ($w['id'] === $id) { $wh = $w; break; }
    }

    if (!$wh) {
        jsonResponse(['error' => 'Webhook não encontrado'], 404);
    }

    $testMsg = '[COMMANDER V10.4] Teste de conexao bem-sucedido! Webhook configurado corretamente em ' . date('d/m/Y H:i:s');
    $success = false;

    if ($wh['platform'] === 'telegram') {
        $success = sendTelegramMessage($wh['bot_token'] ?? '', $wh['chat_id'] ?? '', $testMsg);
    } elseif ($wh['platform'] === 'discord') {
        $success = sendDiscordMessage($wh['url'] ?? '', $testMsg);
    }

    if ($success) {
        jsonResponse(['status' => 'success', 'message' => 'Mensagem de teste enviada com sucesso']);
    } else {
        jsonResponse(['status' => 'error', 'message' => 'Falha ao enviar mensagem. Verifique as credenciais.'], 422);
    }
}

function deleteWebhookById() {
    global $security;

    $body = getJsonBody();
    $id   = $security->sanitizeInput(($body['id'] ?? $_GET['id'] ?? ''), 'alphanumeric');

    if (empty($id)) {
        jsonResponse(['error' => 'ID é obrigatório'], 400);
    }

    deleteWebhook($id);
    jsonResponse(['status' => 'success', 'message' => 'Webhook removido']);
}

function toggleWebhook() {
    global $security;

    $body    = getJsonBody();
    $id      = $security->sanitizeInput($body['id'] ?? '', 'alphanumeric');
    $enabled = (bool)($body['enabled'] ?? false);

    if (empty($id)) {
        jsonResponse(['error' => 'ID é obrigatório'], 400);
    }

    $webhooks = getWebhooks();
    $found    = false;

    foreach ($webhooks as &$wh) {
        if ($wh['id'] === $id) {
            $wh['enabled'] = $enabled;
            $found = true;
            break;
        }
    }

    if (!$found) {
        jsonResponse(['error' => 'Webhook não encontrado'], 404);
    }

    writeJsonFile(FILE_WEBHOOKS, $webhooks);
    jsonResponse(['status' => 'success', 'enabled' => $enabled]);
}

// ── Utilitário ─────────────────────────────────────────────────────────────

function getJsonBody() {
    $raw  = file_get_contents('php://input');
    $json = !empty($raw) ? (json_decode($raw, true) ?: []) : [];
    return array_merge($_POST, $json);
}
