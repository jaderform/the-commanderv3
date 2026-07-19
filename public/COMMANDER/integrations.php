<?php
/**
 * ============================================
 * integrations.php - COMMANDER INTEGRACOES V9.0
 * ============================================
 * 
 * Funcoes para integrar com APIs de plataformas de anuncios
 * - TikTok Ads API
 * - Google Ads API
 * - Facebook Ads API
 */

/**
 * Pausa ou ativa uma campanha em uma plataforma
 * 
 * @param string $platform - tiktok, google, facebook
 * @param string $campaign_id - ID da campanha na plataforma
 * @param string $status - ON ou OFF
 * @param string $api_token - Token de acesso da API
 * @param string $account_id - ID da conta de anuncios
 * @return array ['success' => bool, 'message' => string]
 */
function toggleCampaign($platform, $campaign_id, $status, $api_token, $account_id = '') {
    $platform = strtolower($platform);
    $status = strtoupper($status);
    
    if (empty($campaign_id) || empty($api_token)) {
        return ['success' => false, 'message' => 'Campaign ID e Token sao obrigatorios'];
    }
    
    switch ($platform) {
        case 'tiktok':
            return toggleTikTokCampaign($campaign_id, $status, $api_token, $account_id);
        case 'google':
            return toggleGoogleCampaign($campaign_id, $status, $api_token, $account_id);
        case 'facebook':
        case 'fb':
            return toggleFacebookCampaign($campaign_id, $status, $api_token, $account_id);
        default:
            return ['success' => false, 'message' => 'Plataforma nao suportada: ' . $platform];
    }
}

/**
 * TikTok Ads API - Pausa/Ativa campanha
 */
function toggleTikTokCampaign($campaign_id, $status, $access_token, $advertiser_id) {
    if (empty($advertiser_id)) {
        return ['success' => false, 'message' => 'Advertiser ID e obrigatorio para TikTok'];
    }
    
    $api_url = 'https://business-api.tiktok.com/open_api/v1.3/campaign/update/status/';
    
    // TikTok usa ENABLE e DISABLE
    $tiktok_status = ($status === 'ON') ? 'ENABLE' : 'DISABLE';
    
    $data = [
        'advertiser_id' => $advertiser_id,
        'campaign_ids' => [$campaign_id],
        'operation_status' => $tiktok_status
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Access-Token: ' . $access_token
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'Erro cURL: ' . $error];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['code']) && $result['code'] == 0) {
        return [
            'success' => true, 
            'message' => 'Campanha TikTok ' . ($status === 'ON' ? 'ativada' : 'pausada') . ' com sucesso'
        ];
    }
    
    return [
        'success' => false, 
        'message' => 'Erro TikTok API: ' . ($result['message'] ?? 'Resposta desconhecida'),
        'response' => $result
    ];
}

/**
 * Google Ads API - Pausa/Ativa campanha
 * Nota: Google Ads API e mais complexa e requer OAuth2
 */
function toggleGoogleCampaign($campaign_id, $status, $access_token, $customer_id) {
    if (empty($customer_id)) {
        return ['success' => false, 'message' => 'Customer ID e obrigatorio para Google Ads'];
    }
    
    // Remove hifens do customer_id se houver
    $customer_id = str_replace('-', '', $customer_id);
    
    $api_url = "https://googleads.googleapis.com/v14/customers/{$customer_id}/campaigns/{$campaign_id}:mutate";
    
    // Google usa ENABLED e PAUSED
    $google_status = ($status === 'ON') ? 'ENABLED' : 'PAUSED';
    
    $data = [
        'operations' => [
            [
                'updateMask' => 'status',
                'update' => [
                    'resourceName' => "customers/{$customer_id}/campaigns/{$campaign_id}",
                    'status' => $google_status
                ]
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token,
            'developer-token: YOUR_DEVELOPER_TOKEN' // Precisa do developer token
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'Erro cURL: ' . $error];
    }
    
    if ($http_code >= 200 && $http_code < 300) {
        return [
            'success' => true, 
            'message' => 'Campanha Google Ads ' . ($status === 'ON' ? 'ativada' : 'pausada') . ' com sucesso'
        ];
    }
    
    $result = json_decode($response, true);
    return [
        'success' => false, 
        'message' => 'Erro Google Ads API (HTTP ' . $http_code . '): ' . ($result['error']['message'] ?? 'Resposta desconhecida'),
        'response' => $result
    ];
}

/**
 * Facebook/Meta Ads API - Pausa/Ativa campanha
 */
function toggleFacebookCampaign($campaign_id, $status, $access_token, $account_id = '') {
    $api_version = 'v18.0';
    $api_url = "https://graph.facebook.com/{$api_version}/{$campaign_id}";
    
    // Facebook usa ACTIVE e PAUSED
    $fb_status = ($status === 'ON') ? 'ACTIVE' : 'PAUSED';
    
    $data = [
        'status' => $fb_status,
        'access_token' => $access_token
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'Erro cURL: ' . $error];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['success']) && $result['success'] === true) {
        return [
            'success' => true, 
            'message' => 'Campanha Facebook ' . ($status === 'ON' ? 'ativada' : 'pausada') . ' com sucesso'
        ];
    }
    
    return [
        'success' => false, 
        'message' => 'Erro Facebook API: ' . ($result['error']['message'] ?? 'Resposta desconhecida'),
        'response' => $result
    ];
}

/**
 * Obtem estatisticas de uma campanha
 */
function getCampaignStats($platform, $campaign_id, $access_token, $account_id = '') {
    $platform = strtolower($platform);
    
    switch ($platform) {
        case 'tiktok':
            return getTikTokCampaignStats($campaign_id, $access_token, $account_id);
        case 'facebook':
        case 'fb':
            return getFacebookCampaignStats($campaign_id, $access_token);
        default:
            return ['success' => false, 'message' => 'Estatisticas nao disponiveis para: ' . $platform];
    }
}

/**
 * TikTok - Estatisticas da campanha
 */
function getTikTokCampaignStats($campaign_id, $access_token, $advertiser_id) {
    if (empty($advertiser_id)) {
        return ['success' => false, 'message' => 'Advertiser ID obrigatorio'];
    }
    
    $api_url = 'https://business-api.tiktok.com/open_api/v1.3/report/integrated/get/';
    
    $data = [
        'advertiser_id' => $advertiser_id,
        'report_type' => 'BASIC',
        'dimensions' => ['campaign_id'],
        'filters' => [
            ['field_name' => 'campaign_id', 'filter_type' => 'IN', 'filter_value' => json_encode([$campaign_id])]
        ],
        'metrics' => ['spend', 'impressions', 'clicks', 'ctr', 'cpc', 'conversions', 'conversion_rate'],
        'data_level' => 'AUCTION_CAMPAIGN',
        'start_date' => date('Y-m-d', strtotime('-7 days')),
        'end_date' => date('Y-m-d')
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Access-Token: ' . $access_token
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['code']) && $result['code'] == 0) {
        return ['success' => true, 'data' => $result['data']];
    }
    
    return ['success' => false, 'message' => $result['message'] ?? 'Erro desconhecido'];
}

/**
 * Facebook - Estatisticas da campanha
 */
function getFacebookCampaignStats($campaign_id, $access_token) {
    $api_version = 'v18.0';
    $api_url = "https://graph.facebook.com/{$api_version}/{$campaign_id}/insights";
    
    $params = [
        'access_token' => $access_token,
        'fields' => 'spend,impressions,clicks,ctr,cpc,conversions,cost_per_conversion',
        'date_preset' => 'last_7d'
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url . '?' . http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['data'])) {
        return ['success' => true, 'data' => $result['data']];
    }
    
    return ['success' => false, 'message' => $result['error']['message'] ?? 'Erro desconhecido'];
}
