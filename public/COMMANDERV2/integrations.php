<?php
/**
 * COMMANDER V10.3 - Integrações com Plataformas de Ads
 * 
 * Gerencia integrações com TikTok Ads, Google Ads, Facebook Ads, etc.
 * 
 * INSTRUÇÕES: Substitua o arquivo integrations.php existente por este
 */

// Define constante de acesso
define('COMMANDER_ACCESS', true);

// Carrega dependências
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/functions.php';

/**
 * Classe base para integrações
 */
abstract class AdsIntegration {
    
    protected $credentials = [];
    protected $lastError = '';
    
    abstract public function authenticate();
    abstract public function pauseCampaign($campaignId);
    abstract public function activateCampaign($campaignId);
    abstract public function getCampaigns();
    abstract public function getCampaignStats($campaignId);
    
    public function getLastError() {
        return $this->lastError;
    }
    
    protected function setError($message) {
        $this->lastError = $message;
        debugLog('Integration Error', ['message' => $message, 'class' => get_class($this)]);
    }
    
    protected function httpRequest($url, $method = 'GET', $data = null, $headers = []) {
        $ch = curl_init();
        
        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        $headers = array_merge($defaultHeaders, $headers);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            $this->setError("CURL Error: {$error}");
            return null;
        }
        
        return [
            'code' => $httpCode,
            'body' => json_decode($response, true) ?: $response
        ];
    }
}

/**
 * Integração com TikTok Ads
 */
class TikTokAdsIntegration extends AdsIntegration {
    
    private $baseUrl = 'https://business-api.tiktok.com/open_api';
    private $apiVersion = 'v1.3';
    
    public function __construct($accessToken, $advertiserId) {
        $this->credentials = [
            'access_token' => $accessToken,
            'advertiser_id' => $advertiserId
        ];
    }
    
    public function authenticate() {
        $response = $this->makeRequest('/oauth2/advertiser/get/', 'GET', [
            'app_id' => $this->credentials['app_id'] ?? '',
            'secret' => $this->credentials['secret'] ?? ''
        ]);
        
        return $response && isset($response['data']['list']);
    }
    
    public function pauseCampaign($campaignId) {
        return $this->updateCampaignStatus($campaignId, 'DISABLE');
    }
    
    public function activateCampaign($campaignId) {
        return $this->updateCampaignStatus($campaignId, 'ENABLE');
    }
    
    private function updateCampaignStatus($campaignId, $status) {
        $response = $this->makeRequest('/campaign/update/status/', 'POST', [
            'advertiser_id' => $this->credentials['advertiser_id'],
            'campaign_ids' => [$campaignId],
            'operation_status' => $status
        ]);
        
        if (!$response || ($response['code'] ?? -1) !== 0) {
            $this->setError($response['message'] ?? 'Failed to update campaign status');
            return false;
        }
        
        return true;
    }
    
    public function getCampaigns() {
        $response = $this->makeRequest('/campaign/get/', 'GET', [
            'advertiser_id' => $this->credentials['advertiser_id'],
            'page_size' => 100
        ]);
        
        if (!$response || !isset($response['data']['list'])) {
            return [];
        }
        
        return array_map(function($campaign) {
            return [
                'id' => $campaign['campaign_id'],
                'name' => $campaign['campaign_name'],
                'status' => $campaign['operation_status'],
                'budget' => $campaign['budget'] ?? 0,
                'objective' => $campaign['objective_type'] ?? ''
            ];
        }, $response['data']['list']);
    }
    
    public function getCampaignStats($campaignId) {
        $response = $this->makeRequest('/report/integrated/get/', 'GET', [
            'advertiser_id' => $this->credentials['advertiser_id'],
            'report_type' => 'BASIC',
            'dimensions' => ['campaign_id'],
            'data_level' => 'AUCTION_CAMPAIGN',
            'start_date' => date('Y-m-d', strtotime('-7 days')),
            'end_date' => date('Y-m-d'),
            'filters' => [
                ['field_name' => 'campaign_id', 'filter_type' => 'IN', 'filter_value' => json_encode([$campaignId])]
            ],
            'metrics' => ['spend', 'impressions', 'clicks', 'conversion', 'cost_per_conversion']
        ]);
        
        if (!$response || !isset($response['data']['list'][0])) {
            return null;
        }
        
        $stats = $response['data']['list'][0]['metrics'];
        return [
            'spend' => (float) ($stats['spend'] ?? 0),
            'impressions' => (int) ($stats['impressions'] ?? 0),
            'clicks' => (int) ($stats['clicks'] ?? 0),
            'conversions' => (int) ($stats['conversion'] ?? 0),
            'cpa' => (float) ($stats['cost_per_conversion'] ?? 0)
        ];
    }
    
    public function getAdGroups($campaignId) {
        $response = $this->makeRequest('/adgroup/get/', 'GET', [
            'advertiser_id' => $this->credentials['advertiser_id'],
            'campaign_ids' => [$campaignId],
            'page_size' => 100
        ]);
        
        if (!$response || !isset($response['data']['list'])) {
            return [];
        }
        
        return $response['data']['list'];
    }
    
    public function pauseAdGroup($adGroupId) {
        return $this->updateAdGroupStatus($adGroupId, 'DISABLE');
    }
    
    public function activateAdGroup($adGroupId) {
        return $this->updateAdGroupStatus($adGroupId, 'ENABLE');
    }
    
    private function updateAdGroupStatus($adGroupId, $status) {
        $response = $this->makeRequest('/adgroup/update/status/', 'POST', [
            'advertiser_id' => $this->credentials['advertiser_id'],
            'adgroup_ids' => [$adGroupId],
            'operation_status' => $status
        ]);
        
        return $response && ($response['code'] ?? -1) === 0;
    }
    
    private function makeRequest($endpoint, $method = 'GET', $data = []) {
        $url = "{$this->baseUrl}/{$this->apiVersion}{$endpoint}";
        
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            $data = null;
        }
        
        $headers = [
            "Access-Token: {$this->credentials['access_token']}"
        ];
        
        $response = $this->httpRequest($url, $method, $data, $headers);
        
        return $response ? $response['body'] : null;
    }
}

/**
 * Integração com Google Ads
 */
class GoogleAdsIntegration extends AdsIntegration {
    
    private $baseUrl = 'https://googleads.googleapis.com';
    private $apiVersion = 'v14';
    
    public function __construct($refreshToken, $clientId, $clientSecret, $customerId) {
        $this->credentials = [
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'customer_id' => str_replace('-', '', $customerId)
        ];
    }
    
    public function authenticate() {
        return $this->getAccessToken() !== null;
    }
    
    private function getAccessToken() {
        static $accessToken = null;
        static $tokenExpiry = 0;
        
        if ($accessToken && time() < $tokenExpiry) {
            return $accessToken;
        }
        
        $response = $this->httpRequest('https://oauth2.googleapis.com/token', 'POST', http_build_query([
            'client_id' => $this->credentials['client_id'],
            'client_secret' => $this->credentials['client_secret'],
            'refresh_token' => $this->credentials['refresh_token'],
            'grant_type' => 'refresh_token'
        ]), ['Content-Type: application/x-www-form-urlencoded']);
        
        if (!$response || !isset($response['body']['access_token'])) {
            $this->setError('Failed to get access token');
            return null;
        }
        
        $accessToken = $response['body']['access_token'];
        $tokenExpiry = time() + ($response['body']['expires_in'] ?? 3600) - 60;
        
        return $accessToken;
    }
    
    public function pauseCampaign($campaignId) {
        return $this->updateCampaignStatus($campaignId, 'PAUSED');
    }
    
    public function activateCampaign($campaignId) {
        return $this->updateCampaignStatus($campaignId, 'ENABLED');
    }
    
    private function updateCampaignStatus($campaignId, $status) {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $customerId = $this->credentials['customer_id'];
        $resourceName = "customers/{$customerId}/campaigns/{$campaignId}";
        
        $body = [
            'operations' => [
                [
                    'update' => [
                        'resourceName' => $resourceName,
                        'status' => $status
                    ],
                    'updateMask' => 'status'
                ]
            ]
        ];
        
        $response = $this->makeRequest("/customers/{$customerId}/campaigns:mutate", 'POST', $body);
        
        return $response && !isset($response['error']);
    }
    
    public function getCampaigns() {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [];
        }
        
        $query = "SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type 
                  FROM campaign 
                  WHERE campaign.status != 'REMOVED'
                  ORDER BY campaign.name";
        
        $response = $this->makeRequest("/customers/{$this->credentials['customer_id']}/googleAds:search", 'POST', [
            'query' => $query
        ]);
        
        if (!$response || !isset($response['results'])) {
            return [];
        }
        
        return array_map(function($result) {
            return [
                'id' => $result['campaign']['id'],
                'name' => $result['campaign']['name'],
                'status' => $result['campaign']['status'],
                'type' => $result['campaign']['advertisingChannelType'] ?? ''
            ];
        }, $response['results']);
    }
    
    public function getCampaignStats($campaignId) {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return null;
        }
        
        $query = "SELECT metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.conversions, metrics.cost_per_conversion
                  FROM campaign
                  WHERE campaign.id = {$campaignId}
                  AND segments.date DURING LAST_7_DAYS";
        
        $response = $this->makeRequest("/customers/{$this->credentials['customer_id']}/googleAds:search", 'POST', [
            'query' => $query
        ]);
        
        if (!$response || !isset($response['results'][0])) {
            return null;
        }
        
        $metrics = $response['results'][0]['metrics'];
        return [
            'spend' => (float) ($metrics['costMicros'] ?? 0) / 1000000,
            'impressions' => (int) ($metrics['impressions'] ?? 0),
            'clicks' => (int) ($metrics['clicks'] ?? 0),
            'conversions' => (float) ($metrics['conversions'] ?? 0),
            'cpa' => (float) ($metrics['costPerConversion'] ?? 0) / 1000000
        ];
    }
    
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return null;
        }
        
        $url = "{$this->baseUrl}/{$this->apiVersion}{$endpoint}";
        
        $headers = [
            "Authorization: Bearer {$accessToken}",
            "developer-token: " . ($this->credentials['developer_token'] ?? ''),
            "login-customer-id: {$this->credentials['customer_id']}"
        ];
        
        $response = $this->httpRequest($url, $method, $data, $headers);
        
        return $response ? $response['body'] : null;
    }
}

/**
 * Integração com Facebook/Meta Ads
 */
class FacebookAdsIntegration extends AdsIntegration {
    
    private $baseUrl = 'https://graph.facebook.com';
    private $apiVersion = 'v18.0';
    
    public function __construct($accessToken, $adAccountId) {
        $this->credentials = [
            'access_token' => $accessToken,
            'ad_account_id' => $adAccountId
        ];
    }
    
    public function authenticate() {
        $response = $this->makeRequest('/me', 'GET');
        return $response && isset($response['id']);
    }
    
    public function pauseCampaign($campaignId) {
        return $this->updateCampaignStatus($campaignId, 'PAUSED');
    }
    
    public function activateCampaign($campaignId) {
        return $this->updateCampaignStatus($campaignId, 'ACTIVE');
    }
    
    private function updateCampaignStatus($campaignId, $status) {
        $response = $this->makeRequest("/{$campaignId}", 'POST', [
            'status' => $status
        ]);
        
        return $response && ($response['success'] ?? false);
    }
    
    public function getCampaigns() {
        $accountId = $this->credentials['ad_account_id'];
        if (strpos($accountId, 'act_') !== 0) {
            $accountId = "act_{$accountId}";
        }
        
        $response = $this->makeRequest("/{$accountId}/campaigns", 'GET', [
            'fields' => 'id,name,status,objective,daily_budget,lifetime_budget',
            'limit' => 100
        ]);
        
        if (!$response || !isset($response['data'])) {
            return [];
        }
        
        return array_map(function($campaign) {
            return [
                'id' => $campaign['id'],
                'name' => $campaign['name'],
                'status' => $campaign['status'],
                'objective' => $campaign['objective'] ?? '',
                'budget' => $campaign['daily_budget'] ?? $campaign['lifetime_budget'] ?? 0
            ];
        }, $response['data']);
    }
    
    public function getCampaignStats($campaignId) {
        $response = $this->makeRequest("/{$campaignId}/insights", 'GET', [
            'fields' => 'spend,impressions,clicks,actions,cost_per_action_type',
            'date_preset' => 'last_7d'
        ]);
        
        if (!$response || !isset($response['data'][0])) {
            return null;
        }
        
        $data = $response['data'][0];
        
        // Extrai conversões do array de actions
        $conversions = 0;
        $cpa = 0;
        
        if (isset($data['actions'])) {
            foreach ($data['actions'] as $action) {
                if (in_array($action['action_type'], ['purchase', 'omni_purchase', 'offsite_conversion.fb_pixel_purchase'])) {
                    $conversions += (int) $action['value'];
                }
            }
        }
        
        if (isset($data['cost_per_action_type'])) {
            foreach ($data['cost_per_action_type'] as $action) {
                if (in_array($action['action_type'], ['purchase', 'omni_purchase'])) {
                    $cpa = (float) $action['value'];
                    break;
                }
            }
        }
        
        return [
            'spend' => (float) ($data['spend'] ?? 0),
            'impressions' => (int) ($data['impressions'] ?? 0),
            'clicks' => (int) ($data['clicks'] ?? 0),
            'conversions' => $conversions,
            'cpa' => $cpa
        ];
    }
    
    public function getAdSets($campaignId) {
        $response = $this->makeRequest("/{$campaignId}/adsets", 'GET', [
            'fields' => 'id,name,status,daily_budget,targeting',
            'limit' => 100
        ]);
        
        return $response['data'] ?? [];
    }
    
    public function pauseAdSet($adSetId) {
        $response = $this->makeRequest("/{$adSetId}", 'POST', ['status' => 'PAUSED']);
        return $response && ($response['success'] ?? false);
    }
    
    public function activateAdSet($adSetId) {
        $response = $this->makeRequest("/{$adSetId}", 'POST', ['status' => 'ACTIVE']);
        return $response && ($response['success'] ?? false);
    }
    
    private function makeRequest($endpoint, $method = 'GET', $data = []) {
        $url = "{$this->baseUrl}/{$this->apiVersion}{$endpoint}";
        
        // Adiciona access_token
        $data['access_token'] = $this->credentials['access_token'];
        
        if ($method === 'GET') {
            $url .= '?' . http_build_query($data);
            $data = null;
        }
        
        $response = $this->httpRequest($url, $method, $data);
        
        return $response ? $response['body'] : null;
    }
}

/**
 * Integração com Kwai Ads
 */
class KwaiAdsIntegration extends AdsIntegration {
    
    private $baseUrl = 'https://ad.kuaishou.com/rest/openapi';
    private $apiVersion = 'v1';
    
    public function __construct($accessToken, $advertiserId) {
        $this->credentials = [
            'access_token' => $accessToken,
            'advertiser_id' => $advertiserId
        ];
    }
    
    public function authenticate() {
        $response = $this->makeRequest('/advertiser/info', 'GET');
        return $response && isset($response['data']);
    }
    
    public function pauseCampaign($campaignId) {
        return $this->updateCampaignStatus($campaignId, 2); // 2 = PAUSED
    }
    
    public function activateCampaign($campaignId) {
        return $this->updateCampaignStatus($campaignId, 1); // 1 = ACTIVE
    }
    
    private function updateCampaignStatus($campaignId, $status) {
        $response = $this->makeRequest('/campaign/update', 'POST', [
            'advertiser_id' => $this->credentials['advertiser_id'],
            'campaign_id' => $campaignId,
            'put_status' => $status
        ]);
        
        return $response && ($response['code'] ?? -1) === 0;
    }
    
    public function getCampaigns() {
        $response = $this->makeRequest('/campaign/list', 'GET', [
            'advertiser_id' => $this->credentials['advertiser_id'],
            'page' => 1,
            'page_size' => 100
        ]);
        
        if (!$response || !isset($response['data']['list'])) {
            return [];
        }
        
        return array_map(function($campaign) {
            return [
                'id' => $campaign['campaign_id'],
                'name' => $campaign['campaign_name'],
                'status' => $campaign['put_status'] == 1 ? 'ACTIVE' : 'PAUSED',
                'budget' => $campaign['day_budget'] ?? 0
            ];
        }, $response['data']['list']);
    }
    
    public function getCampaignStats($campaignId) {
        $response = $this->makeRequest('/report/campaign', 'GET', [
            'advertiser_id' => $this->credentials['advertiser_id'],
            'campaign_id' => $campaignId,
            'start_date' => date('Y-m-d', strtotime('-7 days')),
            'end_date' => date('Y-m-d')
        ]);
        
        if (!$response || !isset($response['data'])) {
            return null;
        }
        
        $data = $response['data'];
        return [
            'spend' => (float) ($data['charge'] ?? 0) / 1000,
            'impressions' => (int) ($data['show'] ?? 0),
            'clicks' => (int) ($data['click'] ?? 0),
            'conversions' => (int) ($data['conversion'] ?? 0),
            'cpa' => (float) ($data['conversion_cost'] ?? 0) / 1000
        ];
    }
    
    private function makeRequest($endpoint, $method = 'GET', $data = []) {
        $url = "{$this->baseUrl}/{$this->apiVersion}{$endpoint}";
        
        $headers = [
            "Access-Token: {$this->credentials['access_token']}"
        ];
        
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            $data = null;
        }
        
        $response = $this->httpRequest($url, $method, $data, $headers);
        
        return $response ? $response['body'] : null;
    }
}

/**
 * Factory para criar integrações
 */
class IntegrationFactory {
    
    public static function create($platform, $credentials) {
        switch (strtolower($platform)) {
            case 'tiktok':
                return new TikTokAdsIntegration(
                    $credentials['access_token'],
                    $credentials['advertiser_id']
                );
            
            case 'google':
                return new GoogleAdsIntegration(
                    $credentials['refresh_token'],
                    $credentials['client_id'],
                    $credentials['client_secret'],
                    $credentials['customer_id']
                );
            
            case 'facebook':
            case 'meta':
                return new FacebookAdsIntegration(
                    $credentials['access_token'],
                    $credentials['ad_account_id']
                );
            
            case 'kwai':
                return new KwaiAdsIntegration(
                    $credentials['access_token'],
                    $credentials['advertiser_id']
                );
            
            default:
                throw new Exception("Platform not supported: {$platform}");
        }
    }
}

/**
 * Gerenciador de Integrações
 */
class IntegrationManager {
    
    private $integrations = [];
    
    public function __construct() {
        $this->loadIntegrations();
    }
    
    private function loadIntegrations() {
        $configFile = DATA_DIR . 'integrations.json';
        
        if (file_exists($configFile)) {
            $config = readJsonFile($configFile, []);
            
            foreach ($config as $name => $settings) {
                if ($settings['enabled'] ?? false) {
                    try {
                        $this->integrations[$name] = IntegrationFactory::create(
                            $settings['platform'],
                            $settings['credentials']
                        );
                    } catch (Exception $e) {
                        debugLog('Integration load error', ['name' => $name, 'error' => $e->getMessage()]);
                    }
                }
            }
        }
    }
    
    public function getIntegration($name) {
        return $this->integrations[$name] ?? null;
    }
    
    public function getAllIntegrations() {
        return $this->integrations;
    }
    
    public function pauseAllCampaigns($campaignIds = []) {
        $results = [];
        
        foreach ($this->integrations as $name => $integration) {
            foreach ($campaignIds as $campaignId) {
                $results[$name][$campaignId] = $integration->pauseCampaign($campaignId);
            }
        }
        
        return $results;
    }
    
    public function activateAllCampaigns($campaignIds = []) {
        $results = [];
        
        foreach ($this->integrations as $name => $integration) {
            foreach ($campaignIds as $campaignId) {
                $results[$name][$campaignId] = $integration->activateCampaign($campaignId);
            }
        }
        
        return $results;
    }
}

// Exporta classes para uso externo
if (!defined('INTEGRATIONS_LOADED')) {
    define('INTEGRATIONS_LOADED', true);
}
