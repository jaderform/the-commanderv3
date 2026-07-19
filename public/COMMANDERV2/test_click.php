<?php
/**
 * COMMANDER V2 - Teste de Cliques
 * 
 * Simula cliques para testar se as estatisticas estao sendo registradas
 * 
 * INSTRUCOES:
 * 1. Suba este arquivo para /COMMANDERV2/
 * 2. Acesse: https://seudominio.com/COMMANDERV2/test_click.php?slug=SEU_SLUG
 * 3. Verifique se os cliques aparecem no painel
 * 4. DELETE este arquivo apos testar!
 */

header('Content-Type: text/html; charset=utf-8');

echo "<html><head><title>Teste de Cliques</title>";
echo "<style>body{font-family:Arial,sans-serif;background:#000;color:#fff;padding:40px;max-width:800px;margin:auto;}
.success{color:#00f2ea;background:rgba(0,242,234,0.1);padding:15px;border-radius:8px;margin:10px 0;}
.error{color:#ff0050;background:rgba(255,0,80,0.1);padding:15px;border-radius:8px;margin:10px 0;}
.info{color:#888;background:#111;padding:15px;border-radius:8px;margin:10px 0;}
pre{background:#111;padding:15px;border-radius:8px;overflow:auto;}
h1{color:#ff0050;}
a{color:#00f2ea;}
form{background:#111;padding:20px;border-radius:8px;margin:20px 0;}
input,select{padding:10px;margin:5px 0;width:100%;background:#222;border:1px solid #333;color:#fff;border-radius:4px;}
button{background:#ff0050;color:#fff;padding:12px 24px;border:none;border-radius:8px;cursor:pointer;font-size:16px;margin-top:10px;}
button:hover{background:#e6004a;}
</style></head><body>";

echo "<h1>COMMANDER V2 - Teste de Cliques</h1>";

// Carrega config
define('COMMANDER_ACCESS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Lista campanhas disponiveis
$campaigns = getCampaigns();

echo "<div class='info'>";
echo "<strong>Campanhas cadastradas:</strong><br><br>";
if (empty($campaigns)) {
    echo "Nenhuma campanha cadastrada!";
} else {
    foreach ($campaigns as $c) {
        echo "- <strong>{$c['name']}</strong> (slug: <code>{$c['slug']}</code>, id: <code>{$c['id']}</code>)<br>";
    }
}
echo "</div>";

// Formulario
echo "<form method='POST'>";
echo "<label>Selecione a campanha:</label>";
echo "<select name='slug' required>";
echo "<option value=''>-- Selecione --</option>";
foreach ($campaigns as $c) {
    $selected = ($_POST['slug'] ?? '') === $c['slug'] ? 'selected' : '';
    echo "<option value='{$c['slug']}' $selected>{$c['name']} ({$c['slug']})</option>";
}
echo "</select>";

echo "<label>Quantidade de cliques a simular:</label>";
echo "<input type='number' name='qty' value='5' min='1' max='100'>";

echo "<label>Tipo de trafego:</label>";
echo "<select name='type'>";
echo "<option value='human'>Humano (passa)</option>";
echo "<option value='bot'>Bot (bloqueia)</option>";
echo "<option value='mixed'>Misto (50/50)</option>";
echo "</select>";

echo "<button type='submit'>Simular Cliques</button>";
echo "</form>";

// Processa simulacao
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['slug'])) {
    $slug = $_POST['slug'];
    $qty = min(100, max(1, (int)$_POST['qty']));
    $type = $_POST['type'] ?? 'human';
    
    echo "<h2>Resultado da Simulacao</h2>";
    
    // Encontra campanha
    $campaign = getCampaignBySlug($slug);
    
    if (!$campaign) {
        echo "<div class='error'>Campanha com slug '$slug' nao encontrada!</div>";
    } else {
        echo "<div class='info'>Simulando $qty clique(s) para campanha: <strong>{$campaign['name']}</strong></div>";
        
        // Stats antes
        $statsBefore = getStats();
        $campStatsBefore = $statsBefore['by_campaign'][$campaign['id']] ?? ['clicks' => 0, 'passes' => 0, 'blocks' => 0];
        
        echo "<div class='info'><strong>Stats ANTES:</strong><br>";
        echo "Cliques: {$campStatsBefore['clicks']} | Passes: {$campStatsBefore['passes']} | Bloqueios: {$campStatsBefore['blocks']}</div>";
        
        // Simula cliques
        $successCount = 0;
        $platforms = ['google', 'facebook', 'tiktok', 'kwai'];
        
        for ($i = 0; $i < $qty; $i++) {
            $isBot = false;
            if ($type === 'bot') {
                $isBot = true;
            } elseif ($type === 'mixed') {
                $isBot = (rand(0, 1) === 1);
            }
            
            $platform = $platforms[array_rand($platforms)];
            $result = recordClick($campaign['id'], $isBot, $platform);
            
            if ($result) {
                $successCount++;
            }
        }
        
        // Stats depois
        clearstatcache();
        $statsAfter = getStats();
        $campStatsAfter = $statsAfter['by_campaign'][$campaign['id']] ?? ['clicks' => 0, 'passes' => 0, 'blocks' => 0];
        
        if ($successCount === $qty) {
            echo "<div class='success'><strong>SUCESSO!</strong> $successCount de $qty cliques registrados!</div>";
        } else {
            echo "<div class='error'><strong>PROBLEMA!</strong> Apenas $successCount de $qty cliques foram registrados.</div>";
        }
        
        echo "<div class='info'><strong>Stats DEPOIS:</strong><br>";
        echo "Cliques: {$campStatsAfter['clicks']} | Passes: {$campStatsAfter['passes']} | Bloqueios: {$campStatsAfter['blocks']}</div>";
        
        $diff = $campStatsAfter['clicks'] - $campStatsBefore['clicks'];
        if ($diff === $qty) {
            echo "<div class='success'>Diferenca correta: +$diff cliques</div>";
        } else {
            echo "<div class='error'>Diferenca incorreta: esperado +$qty, obtido +$diff</div>";
        }
        
        echo "<p><a href='index.php'>Voltar ao painel para verificar</a></p>";
    }
}

// Mostra stats atuais
echo "<h2>Stats Atuais (stats.json)</h2>";
echo "<pre>";
print_r(getStats());
echo "</pre>";

echo "<hr><p style='color:#ff0050;'><strong>IMPORTANTE:</strong> Delete este arquivo apos testar!</p>";
echo "</body></html>";
