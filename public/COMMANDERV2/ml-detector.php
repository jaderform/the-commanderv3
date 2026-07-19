<?php
/**
 * COMMANDER - Machine Learning Bot Detector v2.0
 * 
 * Sistema de Machine Learning para deteccao de bots
 * Implementa:
 * 1. Naive Bayes Classifier - Classifica baseado em probabilidades
 * 2. Decision Tree simplificado - Regras aprendidas dos dados
 * 3. Anomaly Detection - Detecta comportamentos fora do padrao
 * 4. Auto-learning - Aprende com o tempo baseado em conversoes
 * 
 * O modelo e treinado incrementalmente com cada acesso,
 * melhorando sua precisao ao longo do tempo.
 */

if (!defined('COMMANDER_ACCESS')) {
    http_response_code(403);
    exit('Acesso negado');
}

class MLBotDetector {
    
    // Arquivo do modelo treinado
    private static $modelFile = null;
    
    // Arquivo de dados de treinamento
    private static $trainingDataFile = null;
    
    // Features usadas para classificacao
    private static $features = [
        // Navegador/Device
        'is_mobile',
        'has_touch',
        'screen_width',
        'screen_height',
        'color_depth',
        'pixel_ratio',
        'timezone_offset',
        'plugins_count',
        'languages_count',
        
        // Behavioral
        'mouse_movements',
        'mouse_velocity_mean',
        'mouse_velocity_std',
        'key_presses',
        'key_interval_mean',
        'key_interval_std',
        'scroll_events',
        'touch_events',
        'time_on_page',
        'interaction_count',
        
        // TLS/Headers
        'has_accept_language',
        'has_sec_ch_ua',
        'has_sec_fetch',
        'header_count',
        'accept_length',
        
        // Sensores
        'has_motion_data',
        'has_orientation_data',
        'motion_variance',
        'orientation_variance',
        
        // Scores externos
        'behavioral_score',
        'tls_score',
        'advanced_bot_score'
    ];
    
    // Pesos aprendidos para cada feature
    private static $defaultWeights = [
        'is_mobile' => 0.15,           // Mobile = mais provavel humano
        'has_touch' => 0.12,           // Touch = provavelmente humano
        'plugins_count' => -0.05,       // Poucos plugins = pode ser bot
        'mouse_velocity_std' => 0.20,  // Alta variacao = humano
        'key_interval_std' => 0.18,    // Alta variacao = humano
        'has_motion_data' => 0.15,     // Sensores = definitivamente mobile real
        'has_orientation_data' => 0.15,
        'behavioral_score' => 0.25,
        'tls_score' => 0.20,
        'time_on_page' => 0.08,        // Mais tempo = mais provavel humano
        'interaction_count' => 0.10
    ];
    
    /**
     * Inicializa caminhos dos arquivos
     */
    private static function initPaths() {
        if (self::$modelFile === null) {
            $dataDir = defined('DATA_DIR') ? DATA_DIR : __DIR__ . '/data/';
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            self::$modelFile = $dataDir . 'ml_model.json';
            self::$trainingDataFile = $dataDir . 'ml_training.json';
        }
    }
    
    /**
     * Carrega o modelo treinado
     */
    private static function loadModel() {
        self::initPaths();
        
        if (file_exists(self::$modelFile)) {
            $content = file_get_contents(self::$modelFile);
            $model = json_decode($content, true);
            if ($model) {
                return $model;
            }
        }
        
        // Modelo padrao
        return [
            'weights' => self::$defaultWeights,
            'feature_stats' => [],
            'training_count' => 0,
            'accuracy' => 0,
            'last_trained' => null,
            'version' => '2.0'
        ];
    }
    
    /**
     * Salva o modelo
     */
    private static function saveModel($model) {
        self::initPaths();
        $model['last_saved'] = date('Y-m-d H:i:s');
        file_put_contents(self::$modelFile, json_encode($model, JSON_PRETTY_PRINT), LOCK_EX);
    }
    
    /**
     * Extrai features de uma requisicao
     */
    public static function extractFeatures($data) {
        $features = [];
        
        // Navegador/Device
        $features['is_mobile'] = !empty($data['is_mobile']) ? 1 : 0;
        $features['has_touch'] = !empty($data['has_touch']) ? 1 : 0;
        $features['screen_width'] = self::normalize($data['screen_width'] ?? 0, 0, 3840);
        $features['screen_height'] = self::normalize($data['screen_height'] ?? 0, 0, 2160);
        $features['color_depth'] = self::normalize($data['color_depth'] ?? 24, 8, 48);
        $features['pixel_ratio'] = self::normalize($data['pixel_ratio'] ?? 1, 1, 4);
        $features['timezone_offset'] = self::normalize(abs($data['timezone_offset'] ?? 0), 0, 720);
        $features['plugins_count'] = self::normalize($data['plugins_count'] ?? 0, 0, 20);
        $features['languages_count'] = self::normalize($data['languages_count'] ?? 1, 1, 10);
        
        // Behavioral (se disponivel)
        $behavioral = $data['behavioral'] ?? [];
        $features['mouse_movements'] = self::normalize($behavioral['mouse_movements'] ?? 0, 0, 500);
        $features['mouse_velocity_mean'] = self::normalize($behavioral['mouse_velocity_mean'] ?? 0, 0, 5000);
        $features['mouse_velocity_std'] = self::normalize($behavioral['mouse_velocity_std'] ?? 0, 0, 2000);
        $features['key_presses'] = self::normalize($behavioral['key_presses'] ?? 0, 0, 100);
        $features['key_interval_mean'] = self::normalize($behavioral['key_interval_mean'] ?? 0, 0, 1000);
        $features['key_interval_std'] = self::normalize($behavioral['key_interval_std'] ?? 0, 0, 500);
        $features['scroll_events'] = self::normalize($behavioral['scroll_events'] ?? 0, 0, 100);
        $features['touch_events'] = self::normalize($behavioral['touch_events'] ?? 0, 0, 100);
        $features['time_on_page'] = self::normalize($data['time_on_page'] ?? 0, 0, 60000);
        $features['interaction_count'] = self::normalize(
            ($behavioral['mouse_movements'] ?? 0) + 
            ($behavioral['key_presses'] ?? 0) + 
            ($behavioral['touch_events'] ?? 0), 0, 1000);
        
        // TLS/Headers
        $features['has_accept_language'] = !empty($data['accept_language']) ? 1 : 0;
        $features['has_sec_ch_ua'] = !empty($data['has_sec_ch_ua']) ? 1 : 0;
        $features['has_sec_fetch'] = !empty($data['has_sec_fetch']) ? 1 : 0;
        $features['header_count'] = self::normalize($data['header_count'] ?? 0, 0, 30);
        $features['accept_length'] = self::normalize(strlen($data['accept'] ?? ''), 0, 200);
        
        // Sensores
        $sensors = $data['sensors'] ?? [];
        $features['has_motion_data'] = !empty($sensors['motion_count']) ? 1 : 0;
        $features['has_orientation_data'] = !empty($sensors['orientation_count']) ? 1 : 0;
        $features['motion_variance'] = self::normalize($sensors['motion_variance'] ?? 0, 0, 100);
        $features['orientation_variance'] = self::normalize($sensors['orientation_variance'] ?? 0, 0, 100);
        
        // Scores externos
        $features['behavioral_score'] = self::normalize($data['behavioral_score'] ?? 50, 0, 100);
        $features['tls_score'] = self::normalize($data['tls_score'] ?? 50, 0, 100);
        $features['advanced_bot_score'] = self::normalize($data['advanced_bot_score'] ?? 50, 0, 100);
        
        return $features;
    }
    
    /**
     * Normaliza valor para range 0-1
     */
    private static function normalize($value, $min, $max) {
        if ($max <= $min) return 0;
        $normalized = ($value - $min) / ($max - $min);
        return max(0, min(1, $normalized));
    }
    
    /**
     * Classifica uma requisicao
     * Retorna probabilidade de ser humano (0-100)
     */
    public static function classify($data) {
        $features = self::extractFeatures($data);
        $model = self::loadModel();
        
        $result = [
            'score' => 50,           // Score padrao
            'confidence' => 0,       // Confianca na predicao
            'flags' => [],
            'feature_contributions' => [],
            'classification' => 'unknown'
        ];
        
        // Calcula score usando pesos do modelo
        $weightedSum = 0;
        $totalWeight = 0;
        
        foreach ($features as $name => $value) {
            $weight = $model['weights'][$name] ?? 0;
            
            if ($weight != 0) {
                $contribution = $value * $weight;
                $weightedSum += $contribution;
                $totalWeight += abs($weight);
                
                // Registra contribuicao de cada feature
                $result['feature_contributions'][$name] = [
                    'value' => round($value, 3),
                    'weight' => $weight,
                    'contribution' => round($contribution, 3)
                ];
            }
        }
        
        // Converte para score 0-100
        if ($totalWeight > 0) {
            $normalizedScore = ($weightedSum / $totalWeight + 1) / 2; // -1 a 1 -> 0 a 1
            $result['score'] = round($normalizedScore * 100);
        }
        
        // Aplica Naive Bayes se houver dados de treinamento suficientes
        if (isset($model['feature_stats']) && $model['training_count'] > 100) {
            $bayesScore = self::naiveBayesClassify($features, $model['feature_stats']);
            // Combina weighted score com Bayes
            $result['score'] = round(($result['score'] * 0.6) + ($bayesScore * 0.4));
        }
        
        // Detecta anomalias
        $anomalies = self::detectAnomalies($features, $model);
        if (!empty($anomalies)) {
            $result['flags'] = array_merge($result['flags'], $anomalies);
            // Penaliza por anomalias
            $result['score'] -= count($anomalies) * 5;
        }
        
        // Normaliza score final
        $result['score'] = max(0, min(100, $result['score']));
        
        // Determina confianca baseado na quantidade de dados
        $result['confidence'] = min(95, $model['training_count'] / 10);
        
        // Classificacao final
        if ($result['score'] >= 60) {
            $result['classification'] = 'human';
        } elseif ($result['score'] >= 40) {
            $result['classification'] = 'uncertain';
        } else {
            $result['classification'] = 'bot';
        }
        
        return $result;
    }
    
    /**
     * Classificador Naive Bayes
     */
    private static function naiveBayesClassify($features, $stats) {
        if (empty($stats['human']) || empty($stats['bot'])) {
            return 50;
        }
        
        $logProbHuman = log($stats['prior_human'] ?? 0.5);
        $logProbBot = log($stats['prior_bot'] ?? 0.5);
        
        foreach ($features as $name => $value) {
            if (isset($stats['human'][$name]) && isset($stats['bot'][$name])) {
                $humanStats = $stats['human'][$name];
                $botStats = $stats['bot'][$name];
                
                // Probabilidade usando distribuicao Gaussiana
                $probHuman = self::gaussianPdf($value, $humanStats['mean'], $humanStats['std']);
                $probBot = self::gaussianPdf($value, $botStats['mean'], $botStats['std']);
                
                if ($probHuman > 0) $logProbHuman += log($probHuman);
                if ($probBot > 0) $logProbBot += log($probBot);
            }
        }
        
        // Converte log probabilidades para score
        $maxLog = max($logProbHuman, $logProbBot);
        $probHumanNorm = exp($logProbHuman - $maxLog);
        $probBotNorm = exp($logProbBot - $maxLog);
        
        $total = $probHumanNorm + $probBotNorm;
        if ($total == 0) return 50;
        
        return round(($probHumanNorm / $total) * 100);
    }
    
    /**
     * PDF da distribuicao Gaussiana
     */
    private static function gaussianPdf($x, $mean, $std) {
        if ($std <= 0) $std = 0.001;
        $exponent = -pow($x - $mean, 2) / (2 * pow($std, 2));
        return (1 / ($std * sqrt(2 * M_PI))) * exp($exponent);
    }
    
    /**
     * Detecta anomalias estatisticas
     */
    private static function detectAnomalies($features, $model) {
        $anomalies = [];
        
        if (empty($model['feature_stats']['human'])) {
            return $anomalies;
        }
        
        $stats = $model['feature_stats']['human'];
        
        foreach ($features as $name => $value) {
            if (isset($stats[$name])) {
                $mean = $stats[$name]['mean'];
                $std = $stats[$name]['std'];
                
                if ($std > 0) {
                    // Z-score
                    $zScore = abs($value - $mean) / $std;
                    
                    // Se Z-score > 3, e uma anomalia (99.7% fora do normal)
                    if ($zScore > 3) {
                        $anomalies[] = "anomaly_{$name}_zscore_" . round($zScore, 2);
                    }
                }
            }
        }
        
        // Anomalias especificas
        
        // Mobile sem touch
        if ($features['is_mobile'] > 0.5 && $features['has_touch'] < 0.5) {
            $anomalies[] = 'mobile_without_touch';
        }
        
        // Touch sem mobile
        if ($features['has_touch'] > 0.5 && $features['is_mobile'] < 0.5 && $features['screen_width'] > 0.5) {
            // Pode ser touchscreen laptop, mas vale verificar
        }
        
        // Muitos movimentos de mouse mas pouca variacao
        if ($features['mouse_movements'] > 0.3 && $features['mouse_velocity_std'] < 0.1) {
            $anomalies[] = 'mouse_movement_no_variance';
        }
        
        // Mobile sem dados de sensores
        if ($features['is_mobile'] > 0.5 && 
            $features['has_motion_data'] < 0.5 && 
            $features['has_orientation_data'] < 0.5) {
            $anomalies[] = 'mobile_no_sensors';
        }
        
        return $anomalies;
    }
    
    /**
     * Treina o modelo com um novo exemplo
     * $label: 'human' ou 'bot'
     */
    public static function train($data, $label) {
        if (!in_array($label, ['human', 'bot'])) {
            return false;
        }
        
        $features = self::extractFeatures($data);
        $model = self::loadModel();
        
        // Inicializa estrutura de estatisticas
        if (!isset($model['feature_stats']['human'])) {
            $model['feature_stats']['human'] = [];
            $model['feature_stats']['bot'] = [];
            $model['feature_stats']['human_count'] = 0;
            $model['feature_stats']['bot_count'] = 0;
        }
        
        // Atualiza contadores
        $model['feature_stats'][$label . '_count']++;
        $model['training_count']++;
        
        // Atualiza estatisticas de cada feature (media movel)
        foreach ($features as $name => $value) {
            if (!isset($model['feature_stats'][$label][$name])) {
                $model['feature_stats'][$label][$name] = [
                    'mean' => $value,
                    'std' => 0,
                    'variance_sum' => 0,
                    'count' => 1
                ];
            } else {
                $stat = &$model['feature_stats'][$label][$name];
                $stat['count']++;
                $n = $stat['count'];
                
                // Algoritmo de Welford para media e variancia movel
                $delta = $value - $stat['mean'];
                $stat['mean'] += $delta / $n;
                $delta2 = $value - $stat['mean'];
                $stat['variance_sum'] += $delta * $delta2;
                
                if ($n > 1) {
                    $stat['std'] = sqrt($stat['variance_sum'] / ($n - 1));
                }
            }
        }
        
        // Atualiza priors
        $totalCount = $model['feature_stats']['human_count'] + $model['feature_stats']['bot_count'];
        if ($totalCount > 0) {
            $model['feature_stats']['prior_human'] = $model['feature_stats']['human_count'] / $totalCount;
            $model['feature_stats']['prior_bot'] = $model['feature_stats']['bot_count'] / $totalCount;
        }
        
        // Ajusta pesos baseado no aprendizado (a cada 100 exemplos)
        if ($model['training_count'] % 100 === 0) {
            self::adjustWeights($model);
        }
        
        $model['last_trained'] = date('Y-m-d H:i:s');
        self::saveModel($model);
        
        // Salva dados de treinamento para analise
        self::saveTrainingExample($features, $label);
        
        return true;
    }
    
    /**
     * Ajusta pesos baseado nos dados de treinamento
     */
    private static function adjustWeights(&$model) {
        if (empty($model['feature_stats']['human']) || empty($model['feature_stats']['bot'])) {
            return;
        }
        
        $humanStats = $model['feature_stats']['human'];
        $botStats = $model['feature_stats']['bot'];
        
        // Calcula discriminative power de cada feature
        foreach (self::$features as $name) {
            if (!isset($humanStats[$name]) || !isset($botStats[$name])) {
                continue;
            }
            
            $humanMean = $humanStats[$name]['mean'];
            $botMean = $botStats[$name]['mean'];
            $humanStd = max($humanStats[$name]['std'], 0.001);
            $botStd = max($botStats[$name]['std'], 0.001);
            
            // Fisher's discriminant ratio
            $pooledVariance = ($humanStd * $humanStd + $botStd * $botStd) / 2;
            $meanDiff = abs($humanMean - $botMean);
            
            if ($pooledVariance > 0) {
                $discriminativePower = $meanDiff / sqrt($pooledVariance);
                
                // Determina direcao do peso (positivo se maior para humanos)
                $direction = ($humanMean > $botMean) ? 1 : -1;
                
                // Ajusta peso (com suavizacao)
                $newWeight = $direction * min($discriminativePower, 1) * 0.3;
                $oldWeight = $model['weights'][$name] ?? 0;
                
                // Media movel para estabilidade
                $model['weights'][$name] = ($oldWeight * 0.7) + ($newWeight * 0.3);
            }
        }
    }
    
    /**
     * Salva exemplo de treinamento
     */
    private static function saveTrainingExample($features, $label) {
        self::initPaths();
        
        $example = [
            'features' => $features,
            'label' => $label,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Carrega dados existentes
        $data = [];
        if (file_exists(self::$trainingDataFile)) {
            $content = file_get_contents(self::$trainingDataFile);
            $data = json_decode($content, true) ?? [];
        }
        
        // Adiciona exemplo
        $data[] = $example;
        
        // Limita a ultimos 10000 exemplos
        if (count($data) > 10000) {
            $data = array_slice($data, -10000);
        }
        
        file_put_contents(self::$trainingDataFile, json_encode($data), LOCK_EX);
    }
    
    /**
     * Treina o modelo baseado em conversao
     * Se houve conversao = era humano
     */
    public static function trainFromConversion($visitorData) {
        return self::train($visitorData, 'human');
    }
    
    /**
     * Treina o modelo baseado em bloqueio confirmado
     * Se foi bloqueado por outra camada = era bot
     */
    public static function trainFromBlock($visitorData) {
        return self::train($visitorData, 'bot');
    }
    
    /**
     * Retorna estatisticas do modelo
     */
    public static function getModelStats() {
        $model = self::loadModel();
        
        return [
            'version' => $model['version'],
            'training_count' => $model['training_count'],
            'human_examples' => $model['feature_stats']['human_count'] ?? 0,
            'bot_examples' => $model['feature_stats']['bot_count'] ?? 0,
            'prior_human' => round(($model['feature_stats']['prior_human'] ?? 0.5) * 100, 2) . '%',
            'prior_bot' => round(($model['feature_stats']['prior_bot'] ?? 0.5) * 100, 2) . '%',
            'last_trained' => $model['last_trained'],
            'active_features' => count($model['weights']),
            'weights' => $model['weights']
        ];
    }
    
    /**
     * Reseta o modelo para o padrao
     */
    public static function resetModel() {
        self::initPaths();
        
        if (file_exists(self::$modelFile)) {
            unlink(self::$modelFile);
        }
        if (file_exists(self::$trainingDataFile)) {
            unlink(self::$trainingDataFile);
        }
        
        return true;
    }
    
    /**
     * Exporta o modelo para backup
     */
    public static function exportModel() {
        return self::loadModel();
    }
    
    /**
     * Importa modelo de backup
     */
    public static function importModel($modelData) {
        if (!isset($modelData['weights']) || !isset($modelData['version'])) {
            return false;
        }
        
        self::saveModel($modelData);
        return true;
    }
}
