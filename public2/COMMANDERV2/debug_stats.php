<?php
/**
 * COMMANDER V2 - Debug de Estatisticas
 * 
 * INSTRUCOES:
 * 1. Suba este arquivo para /COMMANDERV2/
 * 2. Acesse: https://seudominio.com/COMMANDERV2/debug_stats.php
 * 3. Veja os dados de debug
 * 4. DELETE este arquivo apos usar!
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>COMMANDER V2 - Debug de Estatisticas</h1>";
echo "<style>body{font-family:monospace;background:#111;color:#0f0;padding:20px;} pre{background:#222;padding:15px;border-radius:8px;overflow:auto;} h2{color:#ff0050;margin-top:30px;} .error{color:#ff0;}</style>";

// Verifica pastas
echo "<h2>1. Verificando Pastas</h2>";
$dataDir = __DIR__ . '/data/';
$logsDir = __DIR__ . '/logs/';
$cacheDir = __DIR__ . '/cache/';

echo "<pre>";
echo "DATA_DIR: $dataDir - " . (is_dir($dataDir) ? "EXISTE" : "NAO EXISTE") . " - " . (is_writable($dataDir) ? "GRAVAVEL" : "NAO GRAVAVEL") . "\n";
echo "LOGS_DIR: $logsDir - " . (is_dir($logsDir) ? "EXISTE" : "NAO EXISTE") . " - " . (is_writable($logsDir) ? "GRAVAVEL" : "NAO GRAVAVEL") . "\n";
echo "CACHE_DIR: $cacheDir - " . (is_dir($cacheDir) ? "EXISTE" : "NAO EXISTE") . " - " . (is_writable($cacheDir) ? "GRAVAVEL" : "NAO GRAVAVEL") . "\n";
echo "</pre>";

// Lista arquivos em data/
echo "<h2>2. Arquivos em /data/</h2>";
echo "<pre>";
if (is_dir($dataDir)) {
    $files = scandir($dataDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $path = $dataDir . $file;
            $size = filesize($path);
            $modified = date('Y-m-d H:i:s', filemtime($path));
            echo "$file - $size bytes - $modified\n";
        }
    }
} else {
    echo "Pasta data/ nao existe!\n";
}
echo "</pre>";

// Conteudo de campaigns.json
echo "<h2>3. Campanhas Cadastradas (campaigns.json)</h2>";
echo "<pre>";
$campaignsFile = $dataDir . 'campaigns.json';
if (file_exists($campaignsFile)) {
    $campaigns = json_decode(file_get_contents($campaignsFile), true);
    if ($campaigns) {
        foreach ($campaigns as $c) {
            echo "ID: " . ($c['id'] ?? 'N/A') . "\n";
            echo "Nome: " . ($c['name'] ?? 'N/A') . "\n";
            echo "Slug: " . ($c['slug'] ?? 'N/A') . "\n";
            echo "Status: " . ($c['status'] ?? 'N/A') . "\n";
            echo "---\n";
        }
    } else {
        echo "Nenhuma campanha cadastrada ou arquivo vazio\n";
    }
} else {
    echo "Arquivo campaigns.json NAO EXISTE!\n";
}
echo "</pre>";

// Conteudo de stats.json
echo "<h2>4. Estatisticas (stats.json)</h2>";
echo "<pre>";
$statsFile = $dataDir . 'stats.json';
if (file_exists($statsFile)) {
    $stats = json_decode(file_get_contents($statsFile), true);
    echo "Conteudo completo:\n";
    print_r($stats);
} else {
    echo "Arquivo stats.json NAO EXISTE!\n";
}
echo "</pre>";

// Conteudo de bot_log.json
echo "<h2>5. Ultimos Logs de Bot (bot_log.json)</h2>";
echo "<pre>";
$botLogFile = $logsDir . 'bot_log.json';
if (file_exists($botLogFile)) {
    $logs = json_decode(file_get_contents($botLogFile), true);
    if ($logs) {
        $recent = array_slice($logs, 0, 10);
        foreach ($recent as $log) {
            echo date('d/m H:i', strtotime($log['timestamp'] ?? 'now')) . " - ";
            echo ($log['ip'] ?? 'N/A') . " - ";
            echo ($log['reason'] ?? 'N/A') . " - ";
            echo "Campanha: " . ($log['campaign'] ?? 'N/A') . "\n";
        }
    } else {
        echo "Nenhum log de bot\n";
    }
} else {
    echo "Arquivo bot_log.json NAO EXISTE!\n";
}
echo "</pre>";

// Teste de gravacao
echo "<h2>6. Teste de Gravacao</h2>";
echo "<pre>";
$testFile = $dataDir . 'test_write.txt';
$testContent = 'Teste em ' . date('Y-m-d H:i:s');
if (file_put_contents($testFile, $testContent)) {
    echo "SUCESSO: Conseguiu gravar em $testFile\n";
    unlink($testFile);
} else {
    echo "ERRO: NAO conseguiu gravar em $testFile\n";
}
echo "</pre>";

// Teste da API
echo "<h2>7. Teste da API</h2>";
echo "<pre>";
$apiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api.php';
echo "URL da API: $apiUrl\n\n";

// Tenta chamar a API
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['action' => 'health']),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "CURL Error: $error\n";
} else {
    echo "Resposta: $response\n";
}
echo "</pre>";

// Simula um clique
echo "<h2>8. Simulacao de Clique (teste)</h2>";
echo "<pre>";
if (!empty($campaigns[0]['slug'])) {
    $testSlug = $campaigns[0]['slug'];
    echo "Testando com campanha: $testSlug\n\n";
    
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'action' => 'check',
            'campaign' => $testSlug,
            'ip' => '177.100.200.50',
            'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'utm_source' => 'google',
            'utm_medium' => 'cpc'
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    if ($error) {
        echo "CURL Error: $error\n";
    } else {
        echo "Resposta: $response\n";
    }
    
    // Verifica se stats foram atualizados
    echo "\n--- Stats apos o teste ---\n";
    clearstatcache();
    if (file_exists($statsFile)) {
        $statsAfter = json_decode(file_get_contents($statsFile), true);
        print_r($statsAfter);
    }
} else {
    echo "Nenhuma campanha cadastrada para testar\n";
}
echo "</pre>";

echo "<hr><p style='color:#ff0050;'>IMPORTANTE: Delete este arquivo apos o debug!</p>";
