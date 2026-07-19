<?php
session_start();
error_reporting(0);

// --- INTEGRAÇÃO DE SEGURANÇA COM RAIZ ---
// Compativel com ambas as variaveis de sessao (email ou usuario_email)
$email_sessao = $_SESSION['usuario_email'] ?? $_SESSION['email'] ?? null;

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true || empty($email_sessao)) {
    header("Location: ../login.php"); 
    exit;
}

// Busca o arquivo de usuários na pasta acima (raiz)
$users_file_root = '../usuarios.json'; 
$usuarios_db = file_exists($users_file_root) ? json_decode(file_get_contents($users_file_root), true) : [];
$email_atual = $email_sessao;

// Verifica permissão COMMANDER (utm_cloaker_ativo)
if (!isset($usuarios_db[$email_atual]) || 
    !isset($usuarios_db[$email_atual]['utm_cloaker_ativo']) || 
    $usuarios_db[$email_atual]['utm_cloaker_ativo'] !== true) {
    
    $eh_admin = isset($usuarios_db[$email_atual]['eh_admin']) && $usuarios_db[$email_atual]['eh_admin'] === true;
    if (!$eh_admin) {
        // Se não for admin e não tiver permissão, bloqueia
        die("<div style='font-family:sans-serif;text-align:center;padding:50px;background:#000;color:#fff;height:100vh;'>
             <h2 style='color:#fe2c55'>Acesso Negado</h2>
             <p>Seu plano não inclui acesso ao Commander (Cloaker/UTM).</p>
             <a href='../login.php' style='color:#fff'>Voltar</a></div>");
    }
}

$_SESSION['user'] = $email_atual; 
if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = substr(md5($email_atual . 'salt_v7_commander'), 0, 8);
}
// --- FIM DA INTEGRAÇÃO ---

if(file_exists('integrations.php')) { require 'integrations.php'; }

$links_file      = 'links_clientes.json'; 
$stats_file      = 'stats.json'; 
$blacklist_file = 'blocked_ips.json'; 
$whitelist_file = 'whitelist.json'; 
$clicks_file    = 'clicks.json';

// Cria arquivos se não existirem
if(!file_exists($links_file)) file_put_contents($links_file, '{}');
if(!file_exists($clicks_file)) file_put_contents($clicks_file, '{}');
if(!file_exists($whitelist_file)) file_put_contents($whitelist_file, '[]');

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
$base_url = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';
$api_url = $base_url . "api.php";

// FUNÇÕES: Links salvos em um array, identificados pelo token_xgo
function getLinksDB() { 
    global $links_file; 
    $db = json_decode(file_get_contents($links_file), true) ?? []; 
    return is_array($db) ? $db : [];
}
function saveLinksDB($data) { 
    global $links_file; 
    file_put_contents($links_file, json_encode($data, JSON_PRETTY_PRINT)); 
}
function getStats($user) { 
    global $stats_file; 
    $d = file_exists($stats_file) ? json_decode(file_get_contents($stats_file), true) : [];
    return $d[$user] ?? ['black'=>0, 'white'=>0, 'bots'=>[]];
}

if (isset($_GET['logout'])) { session_destroy(); header("Location: ../login.php"); exit; }

// ===============================
// GERADOR DE DOWNLOADS V10.2
// ===============================
if (isset($_GET['download_file'])) {
    $me = $_SESSION['user'];
    $token_xgo = $_GET['xgo'];
    $file_type = $_GET['download_file'];

    // ===============================
    // DOWNLOAD: view_article.php (WHITE PAGE FALLBACK)
    // ===============================
    if ($file_type === 'article') {
        $title = "Guia de Ferramentas Profissionais | Tudo Material";
        $date = date('d/m/Y');
        $year = date('Y');
        $script = '<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background: #f8f9fa; }
        header { background: linear-gradient(135deg, #2c3e50, #34495e); color: white; padding: 2rem 1rem; text-align: center; }
        header h1 { font-size: 1.8rem; margin-bottom: 0.5rem; }
        header p { opacity: 0.9; font-size: 0.95rem; }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        article { background: white; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 2rem; }
        .article-img { width: 100%; height: 200px; background: linear-gradient(135deg, #e8e8e8, #f5f5f5); display: flex; align-items: center; justify-content: center; color: #999; font-size: 0.9rem; border-bottom: 1px solid #eee; }
        .article-content { padding: 1.5rem; }
        .article-content h2 { color: #e67e22; font-size: 1.4rem; margin-bottom: 1rem; }
        .article-content p { margin-bottom: 1rem; color: #555; }
        .article-meta { font-size: 0.8rem; color: #888; padding-top: 1rem; border-top: 1px solid #eee; }
        .sidebar { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
        .sidebar h3 { font-size: 1rem; margin-bottom: 1rem; color: #2c3e50; }
        .sidebar ul { list-style: none; }
        .sidebar li { padding: 0.5rem 0; border-bottom: 1px solid #f0f0f0; font-size: 0.9rem; }
        .sidebar li:last-child { border: none; }
        .sidebar a { color: #3498db; text-decoration: none; }
        footer { text-align: center; padding: 2rem; font-size: 0.8rem; color: #777; background: #2c3e50; color: #aaa; margin-top: 2rem; }
        @media (min-width: 768px) {
            .container { display: grid; grid-template-columns: 1fr 280px; gap: 2rem; }
        }
    </style>
</head>
<body>
    <header>
        <h1>Blog Tudo Material</h1>
        <p>Inovação e Qualidade em Ferramentas Profissionais</p>
    </header>
    <div class="container">
        <article>
            <div class="article-img">[ Imagem Técnica Ilustrativa ]</div>
            <div class="article-content">
                <h2>Como escolher a parafusadeira ideal para o seu projeto?</h2>
                <p>A escolha da ferramenta correta é o primeiro passo para um trabalho de excelência. Neste guia completo, vamos explorar os principais fatores que você deve considerar ao escolher uma parafusadeira.</p>
                <p>Primeiramente, avalie o tipo de trabalho que você realizará com mais frequência. Para trabalhos leves em casa, uma parafusadeira de 12V pode ser suficiente. Já para uso profissional, modelos de 18V ou 20V oferecem mais potência e durabilidade.</p>
                <p>Outro ponto importante é a ergonomia. Ferramentas bem equilibradas reduzem a fadiga durante o uso prolongado e aumentam a precisão do trabalho.</p>
                <p>Por fim, considere a marca e a disponibilidade de peças de reposição e assistência técnica na sua região.</p>
                <div class="article-meta">Atualizado em: ' . $date . ' | Categoria: Ferramentas Elétricas</div>
            </div>
        </article>
        <aside class="sidebar">
            <h3>Artigos Populares</h3>
            <ul>
                <li><a href="#">Os 10 melhores kits de ferramentas de 2024</a></li>
                <li><a href="#">Furadeira vs Parafusadeira: qual escolher?</a></li>
                <li><a href="#">Manutenção preventiva de ferramentas elétricas</a></li>
                <li><a href="#">Guia de segurança no uso de ferramentas</a></li>
            </ul>
        </aside>
    </div>
    <footer>
        <p>&copy; ' . $year . ' Tudo Material - Todos os direitos reservados</p>
    </footer>
</body>
</html>';
        $filename = "view_article.php";
    } 
// ===============================
// DOWNLOAD: .htaccess (CONFIGURAÇÃO DE NAVEGAÇÃO BLACK)
// ===============================
elseif ($file_type === 'config') {
    $script = 'RewriteEngine On

# 1. SEGURANÇA: Bloqueia acesso direto aos arquivos de configuração e dados
RewriteRule ^(links_clientes|stats|blocked_ips|whitelist|clicks)\.json$ - [F,L]
RewriteRule ^\.git - [F,L]
RewriteRule ^\.env - [F,L]

# 2. EXCEÇÃO: Permite que arquivos físicos (Imagens, CSS, JS) carreguem diretamente
# Se o arquivo ou pasta existir fisicamente, o servidor entrega ele sem passar pelo index.php
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# 3. ROTA PRINCIPAL: Envia qualquer outro caminho para o index.php (Cloaker/Tracker)
# O parâmetro ORIG_PATH permite que o index.php identifique qual página interna exibir
RewriteRule ^(.*)$ index.php [L,QSA,E=ORIG_PATH:$1]

# Headers para melhorar a compatibilidade de espelhamento (Proxy)
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
</IfModule>';
    $filename = ".htaccess";
}
    // ===============================
    // DOWNLOAD: index.php (TRACKER PRINCIPAL V10.2 - CORRIGIDO)
    // ===============================
    else {
        $e_api = base64_encode($api_url); 
        $k_user = base64_encode($me);
        
        $script = '<?php
/**
 * index.php - TRACKER COMMANDER V10.2
 * SISTEMA HIBRIDO: Include Local + Proxy Externo + Sessao
 * 
 * CORRECAO V10.2: Cliques na Black Page agora redirecionam para o site real
 */

session_start();
error_reporting(0);
ini_set(\'display_errors\', 0);

$e = "' . $e_api . '";
$k = "' . $k_user . '";
$t = "' . $token_xgo . '";

$local_black_folder = "oficial";
$required = [\'ttclid\',\'utm_source\',\'cck\',\'gclid\',\'utm_campaign\']; 

function hasAnyRequired($req) {
    foreach ($req as $p) {
        if (isset($_GET[$p]) && $_GET[$p] !== \'\') return true;
    }
    return false;
}

function serveLocalFile($file_path) {
    if (!file_exists($file_path)) return false;
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $mime_types = [
        \'html\' => \'text/html\', \'htm\' => \'text/html\', \'php\' => \'text/html\',
        \'css\' => \'text/css\', \'js\' => \'application/javascript\', \'json\' => \'application/json\',
        \'png\' => \'image/png\', \'jpg\' => \'image/jpeg\', \'jpeg\' => \'image/jpeg\',
        \'gif\' => \'image/gif\', \'svg\' => \'image/svg+xml\', \'ico\' => \'image/x-icon\',
        \'woff\' => \'font/woff\', \'woff2\' => \'font/woff2\', \'ttf\' => \'font/ttf\',
        \'eot\' => \'application/vnd.ms-fontobject\'
    ];
    if ($ext === \'php\') { include($file_path); return true; }
    $content_type = $mime_types[$ext] ?? \'application/octet-stream\';
    header("Content-Type: $content_type; charset=utf-8");
    readfile($file_path);
    return true;
}

function serveLocalPath($local_path, $qs = \'\') {
    $local_path = rtrim($local_path, \'/\');
    $try_files = [$local_path . \'/index.html\', $local_path . \'/index.php\', $local_path . \'.html\', $local_path . \'.php\', $local_path];
    foreach ($try_files as $file) { if (file_exists($file)) { return serveLocalFile($file); } }
    return false;
}

/**
 * FUNCAO CORRIGIDA V10.2
 * Proxy externo com redirecionamento real ao clicar
 */
function proxyExternal($url, $qs = "") {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url . ($qs ? (strpos($url, "?") !== false ? "&" : "?") . $qs : ""),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => $_SERVER["HTTP_USER_AGENT"] ?? "Mozilla/5.0"
    ]);
    $html = curl_exec($ch);
    $resolved_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    if ($html) {
        $base_origin = parse_url($resolved_url, PHP_URL_SCHEME) . "://" . parse_url($resolved_url, PHP_URL_HOST);
        
        // ===============================
        // SCRIPT CORRIGIDO V10.2
        // Intercepta TODOS os cliques e redireciona para o site real
        // ===============================
        $js_encoded = "document.addEventListener(\'click\', function(e) { 
            var t = e.target.closest(\'a, button, [onclick], input[type=submit], [role=button]\'); 
            if (t) { 
                e.preventDefault(); 
                e.stopPropagation(); 
                e.stopImmediatePropagation();
                
                if (t.tagName === \'A\' && t.getAttribute(\'href\')) { 
                    var href = t.getAttribute(\'href\');
                    if (href.startsWith(\'javascript:\') || href === \'#\') return;
                    
                    var finalUrl;
                    if (href.startsWith(\'http://\') || href.startsWith(\'https://\')) {
                        var tempUrl = new URL(href);
                        finalUrl = \'" . $base_origin . "\' + tempUrl.pathname + tempUrl.search + tempUrl.hash;
                    } else if (href.startsWith(\'/\')) {
                        finalUrl = \'" . $base_origin . "\' + href;
                    } else {
                        finalUrl = \'" . $base_origin . "/\' + href;
                    }
                    window.top.location.href = finalUrl;
                } else {
                    window.top.location.href = \'" . $base_origin . "\';
                }
            }
        }, true);
        
        document.addEventListener(\'submit\', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var form = e.target;
            var action = form.getAttribute(\'action\') || \'/\';
            if (action.startsWith(\'/\')) {
                window.top.location.href = \'" . $base_origin . "\' + action;
            } else if (action.startsWith(\'http\')) {
                var tempUrl = new URL(action);
                window.top.location.href = \'" . $base_origin . "\' + tempUrl.pathname;
            } else {
                window.top.location.href = \'" . $base_origin . "/\' + action;
            }
        }, true);";
        
        $jump_script = "<script type=\"text/javascript\">" . $js_encoded . "</script>";

        $html = str_replace(\'</body>\', $jump_script . \'</body>\', $html);
        if (stripos($html, "<base") === false) {
            $html = preg_replace("/(<head[^>]*>)/i", "$1<base href=\"" . $base_origin . "/\">", $html, 1);
        }
        echo $html; return true;
    }
    return false;
}

$ip = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"];
if (strpos($ip, ",") !== false) { $ips = explode(",", $ip); $ip = trim($ips[0]); }
$ua = $_SERVER[\'HTTP_USER_AGENT\'] ?? \'\';
$qs = $_SERVER[\'QUERY_STRING\'] ?? \'\';
$path = $_SERVER["REDIRECT_ORIG_PATH"] ?? $_SERVER["PATH_INFO"] ?? ltrim(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), "/");
if ($path === basename($_SERVER[\'SCRIPT_NAME\'])) $path = \'\';

if (isset($_SESSION[\'human_verified\']) && $_SESSION[\'human_verified\'] === true) {
    if (!empty($path)) {
        $try_paths = [$local_black_folder . \'/\' . $path, $local_black_folder . \'/\' . $path . \'.php\', $local_black_folder . \'/\' . $path . \'.html\'];
        foreach ($try_paths as $try_path) { if (file_exists($try_path) && is_file($try_path)) { serveLocalFile($try_path); exit; } }
    }
}

if (!hasAnyRequired($required) && !isset($_SESSION[\'human_verified\'])) {
    if (file_exists("view_article.php")) { include("view_article.php"); } else { header("Location: https://www.google.com"); }
    exit;
}

$api_url = base64_decode($e);
$license = base64_decode($k);
$post_data = http_build_query([\'license\' => $license, \'token_url\' => $t, \'ua\' => $ua, \'ip\' => $ip, \'qs\' => $qs, \'path\' => $path, \'ref_domain\' => $_SERVER[\'HTTP_HOST\'] ?? \'\']);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $api_url, CURLOPT_POST => 1, CURLOPT_POSTFIELDS => $post_data,
    CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ["X-Forwarded-For: $ip", "Content-Type: application/x-www-form-urlencoded"]
]);
$res = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200 && !empty($res)) {
    $data = json_decode($res, true);
    if ($data && isset($data[\'status\']) && $data[\'status\'] === \'ok\') {
        if (isset($data[\'action\']) && $data[\'action\'] === \'black\') { $_SESSION[\'human_verified\'] = true; $_SESSION[\'human_ip\'] = $ip; }
        if (!empty($data[\'local_path\'])) { if (serveLocalPath($data[\'local_path\'], $qs)) exit; }
        if (!empty($data[\'proxy_url\'])) { if (proxyExternal($data[\'proxy_url\'], $qs)) exit; }
    }
}

if (file_exists("view_article.php")) { include("view_article.php"); } else { header("Location: https://www.google.com"); }
exit;';
        $filename = "index.php";
    }

    header('Content-Type: application/octet-stream'); 
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo $script; 
    exit;
}

// ===============================
// AÇÕES POST
// ===============================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'client_save') {
    $me = $_SESSION['user'];
    $db = getLinksDB();
    
    $route_id = $_POST['route_id'] ?? '';
    $new_token = trim($_POST['token_xgo']);
    
    if (empty($new_token) || strlen($new_token) < 4) {
        $error = "Token XGO inválido.";
    } else {
        $is_new = empty($route_id);
        
        $token_exists = false;
        foreach ($db as $id => $route) {
            if ($route['user'] === $me && $route['token_xgo'] === $new_token && $id !== $route_id) {
                $token_exists = true;
                break;
            }
        }
        
        if ($token_exists && $is_new) {
             $error = "O Token XGO '$new_token' já está em uso em outra rota.";
        } else {
            $final_id = $is_new ? substr(md5($new_token . uniqid()), 0, 10) : $route_id;
            
            $db[$final_id] = [
                'id' => $final_id,
                'user' => $me,
                'white' => trim($_POST['white_page']),
                'black' => trim($_POST['black_page']),
                'token_xgo' => $new_token,
                'note' => trim($_POST['route_note'] ?? 'Rota Principal')
            ];
            
            saveLinksDB($db); 
            $msg = "Rota atualizada com sucesso! Use os botões abaixo para baixar o Tracker.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_route') {
    $route_id_to_del = $_POST['route_id_to_del'];
    $me = $_SESSION['user'];
    $db = getLinksDB();

    if (isset($db[$route_id_to_del]) && $db[$route_id_to_del]['user'] === $me) {
        $deleted_token = $db[$route_id_to_del]['token_xgo'];
        unset($db[$route_id_to_del]);
        saveLinksDB($db);
        $msg = "Rota com Token XGO '$deleted_token' excluída com sucesso!";
    } else {
        $error = "Rota não encontrada ou você não tem permissão para excluí-la.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_whitelist') {
    $ip_to_add = trim($_POST['test_ip']);
    if (!empty($ip_to_add)) {
        $whitelist = json_decode(file_get_contents($whitelist_file), true) ?? [];
        if (!in_array($ip_to_add, $whitelist)) {
            $whitelist[] = $ip_to_add;
            if(count($whitelist) > 20) $whitelist = array_slice($whitelist, -20);
            file_put_contents($whitelist_file, json_encode($whitelist));
            $msg = "IP $ip_to_add liberado! Agora você pode testar sem ser bloqueado.";
        } else {
            $msg = "Este IP já está liberado.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_campaign') {
    $camp_to_del = $_POST['campaign_name'];
    $me = $_SESSION['user'];
    
    if (!empty($camp_to_del)) {
        $clicks_db = json_decode(file_get_contents($clicks_file), true) ?? [];
        $new_clicks_db = [];
        $deleted_count = 0;

        $norm_del = strtolower(trim($camp_to_del));
        
        foreach ($clicks_db as $id => $click) {
            $click_camp = $click['utm_campaign'] ?: '(Sem UTM Campaign)';
            $norm_click = strtolower(trim($click_camp));

            $is_mine_or_legacy = !isset($click['user']) || ($click['user'] === $me);
            
            if ($is_mine_or_legacy && $norm_click === $norm_del) {
                $deleted_count++;
                continue;
            }
            $new_clicks_db[$id] = $click;
        }
        
        file_put_contents($clicks_file, json_encode($new_clicks_db, JSON_PRETTY_PRINT));
        $msg = "Campanha '$camp_to_del' excluída! ($deleted_count registros removidos).";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_campaign') {
    $plat = $_POST['plat']; $cid = $_POST['cid']; $status = $_POST['status']; 
    $token = $_POST['api_token']; $acc_id = $_POST['account_id'];
    if(function_exists('toggleCampaign')) {
        $res = toggleCampaign($plat, $cid, $status, $token, $acc_id);
        if ($res['success']) { $msg = "Sucesso: " . $res['message']; } else { $error = "Erro API: " . $res['message']; }
    } else { $error = "Arquivo integrations.php não encontrado."; }
}

$u = $_SESSION['user'];
$links_db_all = getLinksDB();
$my_routes = array_filter($links_db_all, fn($route) => $route['user'] === $u);
$stats = getStats($u);
$blocked_count = file_exists($blacklist_file) ? count(json_decode(file_get_contents($blacklist_file), true)) : 0;

$my_current_ip = $_SERVER['REMOTE_ADDR'];
$whitelist_arr = file_exists($whitelist_file) ? json_decode(file_get_contents($whitelist_file), true) : [];
$is_whitelisted = in_array($my_current_ip, $whitelist_arr);

$clicks_db_raw = file_exists($clicks_file) ? file_get_contents($clicks_file) : '[]';
$clicks_db = json_decode($clicks_db_raw, true);
if (!is_array($clicks_db)) $clicks_db = [];

$campaigns = [];
$metrics = ['clicks'=>0, 'sales'=>0, 'revenue'=>0, 'checkouts'=>0]; 

foreach ($clicks_db as $cid => $data) {
    if (isset($data['user']) && $data['user'] !== $u) continue; 
    
    if (!($data['is_bot'] ?? false)) {
        $camp_name = $data['utm_campaign'] ?: '(Sem UTM Campaign)';
        $plat_id = $data['campaign_id_platform'] ?? ''; 
        
        if (!isset($campaigns[$camp_name])) {
            $campaigns[$camp_name] = [
                'clicks' => 0, 
                'sales' => 0, 
                'revenue' => 0, 
                'platform' => $data['platform'] ?? 'outro', 
                'plat_id' => $plat_id, 
                'checkouts' => 0
            ]; 
        }
        
        $campaigns[$camp_name]['clicks']++;
        $metrics['clicks']++;
        
        if ($data['checkout_started'] ?? false) {
            $campaigns[$camp_name]['checkouts']++;
            $metrics['checkouts']++;
        }
        
        if ($data['converted'] ?? false) {
            $campaigns[$camp_name]['sales']++; $campaigns[$camp_name]['revenue'] += ($data['revenue'] ?? 0);
            $metrics['sales']++; $metrics['revenue'] += ($data['revenue'] ?? 0);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Commander Dashboard V10.2</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg: #000; --sidebar: #0f0f0f; --card: #121212; --border: #2f2f2f; --text: #fff; --muted: #888; --tt-pink: #FE2C55; --safe: #28c76f; --google: #4285F4; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 250px; background: var(--sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; }
        .brand { padding: 25px; font-size: 20px; font-weight: 800; border-bottom: 1px solid var(--border); } .brand span { color: var(--tt-pink); }
        .nav { flex: 1; padding: 20px; } .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: var(--muted); text-decoration: none; border-radius: 6px; margin-bottom: 5px; font-size: 14px; } .nav-item:hover, .nav-item.active { background: rgba(254,44,85,0.08); color: var(--tt-pink); }
        .btn-logout { color: var(--muted); text-decoration: none; padding: 20px; display: block; font-size: 14px; border-top: 1px solid var(--border); }
        .main { flex: 1; padding: 30px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-box { background: var(--card); border: 1px solid var(--border); padding: 20px; border-radius: 8px; position: relative; }
        .stat-value { font-size: 28px; font-weight: 800; margin: 10px 0; }
        .stat-label { font-size: 13px; color: var(--muted); text-transform: uppercase; }
        .st-pink { border-bottom: 3px solid var(--tt-pink); } .st-green { border-bottom: 3px solid var(--safe); } .st-white { border-bottom: 3px solid #888; }
        .st-blue { border-bottom: 3px solid #4285F4; } 
        .st-gold { border-bottom: 3px solid gold; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 25px; margin-bottom: 25px; }
        .card-head { font-size: 16px; font-weight: 600; margin-bottom: 20px; color: #fff; display: flex; justify-content: space-between; align-items: center; }
        table { width: 100%; border-collapse: collapse; } th { text-align: left; color: var(--muted); font-size: 12px; padding: 10px; border-bottom: 1px solid var(--border); } td { padding: 12px 10px; border-bottom: 1px solid var(--border); font-size: 13px; color:#ddd; }
        input, select { width: 100%; padding: 10px; background: #1a1a1a; border: 1px solid var(--border); color: #fff; border-radius: 6px; box-sizing: border-box; margin-bottom: 10px; }
        .btn { width: 100%; padding: 12px; background: var(--tt-pink); color: #fff; font-weight: 600; border: none; border-radius: 6px; cursor: pointer; } .btn:hover { background: #e0244a; }
        .btn-danger { background: #333; color: #aaa; border: none; padding: 6px 12px; font-size: 12px; border-radius: 4px; cursor: pointer; }
        .btn-pause { background: var(--tt-pink); color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; }
        .bot-reason { font-size: 11px; background: #2e1313; color: #ff4d4d; padding: 3px 8px; border-radius: 4px; border: 1px solid #4d1f1f; display: inline-block; }
        #apiModal { display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#1a1a1a; border:1px solid var(--tt-pink); padding:20px; z-index:100; width:300px; border-radius:8px; box-shadow: 0 0 20px rgba(0,0,0,0.8); }
        .overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:99; }
        .code-box { background: #000; padding: 10px; font-family: monospace; font-size: 11px; color: #0f0; border: 1px solid #333; margin-top: 5px; word-break: break-all; cursor: pointer; }
        .whitelist-active { border: 1px solid #28c76f; background: rgba(40, 199, 111, 0.1); color: #28c76f; padding: 8px; border-radius: 6px; font-size: 13px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .code-area-external { background: #0d1117; border: 1px solid #009688; padding: 12px; border-radius: 6px; font-family: 'Consolas', monospace; color: #c9d1d9; font-size: 11px; overflow-x: auto; white-space: pre; cursor: copy; }
        .info-box { background: rgba(0, 150, 136, 0.1); border: 1px solid #009688; border-radius: 6px; padding: 15px; margin-bottom: 20px; }
        .info-box h4 { color: #009688; margin: 0 0 10px 0; font-size: 14px; }
        .info-box p { color: #aaa; font-size: 12px; margin: 5px 0; }
        .info-box code { background: #000; padding: 2px 6px; border-radius: 3px; color: #0f0; }
        @media (max-width: 768px) { .sidebar { display: none; } .main { padding: 15px; } }
    </style>
</head>
<body>
<div class="sidebar">
        <div class="brand">COMMANDER <span>V10.2</span></div>
        <div class="nav">
            <a href="#" class="nav-item active"><i class="fa-solid fa-layer-group"></i> Dashboard</a>
            
            <?php if(isset($_SESSION['eh_admin']) && $_SESSION['eh_admin']): ?>
                <a href="../admin.php" class="nav-item">
                    <i class="fa-solid fa-user-shield"></i> Painel Admin
                </a>
            <?php endif; ?>
        </div>
        
        <a href="../logout.php" class="btn-logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Sair
        </a>
    </div>

    <div class="main">
        <div class="header"><h2>Ola, <?= ucfirst(explode('@',$u)[0]) ?></h2></div>
        <?php if(isset($msg)) echo "<div style='background:#132e22;color:#28c76f;padding:12px;border-radius:6px;margin-bottom:20px;border:1px solid #1f4d36'><i class='fa-solid fa-check'></i> $msg</div>"; ?>
        <?php if(isset($error)) echo "<div style='background:#2e1313;color:#ff4d4d;padding:12px;border-radius:6px;margin-bottom:20px;border:1px solid #4d1f1f'><i class='fa-solid fa-triangle-exclamation'></i> $error</div>"; ?>

        <div class="info-box">
            <h4><i class="fa-solid fa-shield-check"></i> Como Funciona o Commander V10.2</h4>
            <p><strong>BOT detectado</strong> -> Direcionado para <code>WHITE PAGE</code> (pagina segura)</p>
            <p><strong>HUMANO detectado</strong> -> Direcionado para <code>BLACK PAGE</code> (pagina de oferta)</p>
            <p><strong>NOVO V10.2:</strong> Ao clicar em qualquer botao/link na Black Page via proxy, usuario e redirecionado para o site real!</p>
        </div>

        <div class="stats-grid">
            <div class="stat-box st-pink"><div class="stat-label">Humanos (Black Page)</div><div class="stat-value"><?= number_format($stats['black']) ?></div></div>
            <div class="stat-box st-blue"><div class="stat-label">Bloqueados (White Page)</div><div class="stat-value"><?= number_format($stats['white']) ?></div></div>
            <div class="stat-box" style="border-bottom: 3px solid #FF8C00;"><div class="stat-label">Iniciacoes Checkout (IC)</div><div class="stat-value"><?= number_format($metrics['checkouts']) ?></div></div>
            <div class="stat-box st-gold"><div class="stat-label">Faturamento</div><div class="stat-value">R$ <?= number_format($metrics['revenue'], 2, ',', '.') ?></div></div>
        </div>

        <div class="card" style="border: 1px solid #28c76f;">
            <div class="card-head" style="color:#28c76f; margin-bottom: 10px;">
                <span><i class="fa-solid fa-shield-halved"></i> Liberar IP para Testes (Whitelist)</span>
            </div>
            <div style="font-size:13px; color:#888; margin-bottom:15px;">Adicione seu IP aqui para que o Cloaker NUNCA te bloqueie durante os testes.</div>
            
            <?php if($is_whitelisted): ?>
                <div class="whitelist-active"><i class="fa-solid fa-check-circle"></i> Seu IP atual (<?= $my_current_ip ?>) ja esta liberado! Voce pode acessar os links sem bloqueio.</div>
            <?php else: ?>
                <form method="POST" style="display:flex; gap:10px;">
                    <input type="hidden" name="action" value="save_whitelist">
                    <input type="text" name="test_ip" value="<?= $my_current_ip ?>" placeholder="Seu IP" style="flex:1; margin-bottom:0;" readonly>
                    <button class="btn" style="width:auto; background:#28c76f; margin-bottom:0;">LIBERAR MEU IP AGORA</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-head"><span><i class="fa-solid fa-chart-line"></i> Performance</span></div>
            <div style="overflow-x:auto">
                <table>
                    <thead><tr><th>Campanha</th><th>Plataforma</th><th>Cliques</th><th>IC</th><th>IC %</th><th>Vendas</th><th>Conv. %</th><th>Receita</th><th>Acao</th></tr></thead>
                    <tbody>
                        <?php foreach($campaigns as $name => $c): 
                            if ($c['clicks'] === 0) continue;
                        
                            $cr = $c['clicks'] > 0 ? round(($c['sales']/$c['clicks'])*100, 2) : 0; 
                            $ic_rate = $c['clicks'] > 0 ? round(($c['checkouts']/$c['clicks'])*100, 2) : 0;
                            $icon = ($c['platform']=='tiktok') ? '<i class="fa-brands fa-tiktok" style="color:var(--tt-pink)"></i>' : (($c['platform']=='google') ? '<i class="fa-brands fa-google" style="color:var(--google)"></i>' : '<i class="fa-solid fa-globe"></i>'); 
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($name) ?><br><small style="color:#555">ID: <?= $c['plat_id'] ?: 'N/A' ?></small></td>
                            <td><?= $icon ?> <?= ucfirst($c['platform']) ?></td>
                            <td><?= $c['clicks'] ?></td>
                            <td style="color: #FF8C00;"><?= $c['checkouts'] ?></td> <td><?= $ic_rate ?>%</td> <td><?= $c['sales'] ?></td>
                            <td><?= $cr ?>%</td>
                            <td style="color:var(--safe)">R$ <?= number_format($c['revenue'], 2, ',', '.') ?></td>
                            <td style="display:flex; gap:5px;">
                                <?php if(!empty($c['plat_id'])): ?><button class="btn-pause" onclick="openApiModal('<?= $c['platform'] ?>', '<?= htmlspecialchars($c['plat_id']) ?>', '<?= htmlspecialchars($name) ?>')">PAUSAR</button><?php else: ?><span style="font-size:10px; color:#555; align-self:center;">SEM ID</span><?php endif; ?>
                                <form method="POST" onsubmit="return confirm('Tem certeza que deseja apagar a campanha \'<?= htmlspecialchars($name) ?>\'? Isso removera todos os dados dela.')">
                                    <input type="hidden" name="action" value="delete_campaign">
                                    <input type="hidden" name="campaign_name" value="<?= htmlspecialchars($name) ?>">
                                    <button class="btn-danger" style="padding: 6px 10px; font-size: 10px;"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($campaigns)): ?><tr><td colspan="9" style="text-align:center; padding:20px;">Nenhuma campanha rastreada ainda.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if(!empty($stats['bots'])): ?>
        <div class="card">
             <div class="card-head" style="color:#ff4d4d; justify-content:space-between;">
                <span><i class="fa-solid fa-robot"></i> Auditoria de Bloqueios (Robos/White Page)</span>
            </div>
            <div style="overflow-x:auto; max-height:350px; overflow-y:auto;">
                <table>
                    <thead><tr><th>Data</th><th>IP</th><th>Motivo</th><th>User Agent (Nome do Bot)</th></tr></thead>
                    <tbody>
                        <?php foreach($stats['bots'] as $bot): ?>
                        <tr>
                            <td><?= $bot['date'] ?></td>
                            <td style="color:#ff4d4d; font-family:monospace"><?= $bot['ip'] ?></td>
                            <td><span class="bot-reason"><?= $bot['reason'] ?></span></td>
                            <td style="font-size:11px; color:#666;" title="<?= $bot['ua'] ?>"><?= substr($bot['ua'], 0, 80) ?>...</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-head" style="margin-bottom: 10px;"><i class="fa-solid fa-route"></i> Gerenciar Rotas (Multi-Dominio)</div>
            <p style="font-size: 13px; color: #888; margin-bottom: 20px;">Crie uma rota (Black/White Pages + Token XGO) para cada dominio que deseja testar.</p>

            <h4 style="color:var(--tt-pink); margin-top:0;">Configurar Nova Rota:</h4>
            <form method="POST">
                <input type="hidden" name="action" value="client_save">
                <input type="hidden" name="route_id" value=""> <label style="font-size:12px">Nome da Rota (Ex: Loja de Sapatos)</label><input type="text" name="route_note" value="" placeholder="Opcional: Nome para identificar a rota/dominio">
                <label style="color:#28c76f; font-size:12px">White Page (Pagina para BOTS - Segura)</label><input type="url" name="white_page" value="" required placeholder="https://seu-dominio.com/artigo-blog">
                <label style="color:var(--tt-pink); font-size:12px">Black Page (Pagina para HUMANOS - Oferta)</label><input type="url" name="black_page" value="" required placeholder="https://seu-dominio.com/oferta-produto">
                <label style="font-size:12px">Token XGO (Sera a Chave)</label><input type="text" name="token_xgo" value="<?= substr(md5(uniqid()), 0, 8) ?>" placeholder="Minimo 4 caracteres, deve ser UNICO" required>
                
                <button class="btn" style="margin-top:10px">SALVAR NOVA ROTA</button>
            </form>

            <?php if (!empty($my_routes)): ?>
            <h4 style="color:#fff; margin-top:25px;">Minhas Rotas Ativas (<?= count($my_routes) ?>):</h4>
            <div style="overflow-x:auto;">
                <table style="width:100%; font-size:12px;">
                    <thead><tr><th>Nota/Dominio</th><th>Token XGO</th><th>Black Page</th><th>Acoes</th></tr></thead>
                    <tbody>
                        <?php foreach($my_routes as $route): ?>
                        <tr>
                            <td style="color:#ddd; font-weight:bold;"><?= htmlspecialchars($route['note']) ?></td>
                            <td style="font-family:monospace; color:gold;"><?= $route['token_xgo'] ?></td>
                            <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--tt-pink);"><?= htmlspecialchars($route['black']) ?></td>
                            <td style="display:flex; gap:5px; flex-wrap:wrap;">
                                <a href="?download_file=index&xgo=<?= $route['token_xgo'] ?>" class="btn-pause" style="padding: 4px 8px; font-size: 10px; text-decoration: none; background: #009688;" title="Baixar Rastreador (Principal)">INDEX</a>
                                <a href="?download_file=config&xgo=<?= $route['token_xgo'] ?>" class="btn-pause" style="padding: 4px 8px; font-size: 10px; text-decoration: none; background: #333;" title="Baixar .htaccess">ROTA</a>
                                <a href="?download_file=article&xgo=<?= $route['token_xgo'] ?>" class="btn-pause" style="padding: 4px 8px; font-size: 10px; text-decoration: none; background: #e67e22;" title="Baixar Fallback (view_article.php)">ARTIGO</a>
                                <button class="btn-danger" style="padding: 4px 8px; font-size: 10px;" onclick="document.getElementById('edit_route_<?= $route['id'] ?>').style.display='table-row'">EDITAR</button>
                                <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir a rota \'<?= htmlspecialchars($route['note']) ?>\'? O Tracker ira parar de funcionar!')" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_route">
                                    <input type="hidden" name="route_id_to_del" value="<?= $route['id'] ?>">
                                    <button class="btn-danger" style="padding: 4px 8px; font-size: 10px;"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <tr style="display:none; background:#1e1e1e;" id="edit_route_<?= $route['id'] ?>">
                            <td colspan="4">
                                <form method="POST" style="padding:10px 0;">
                                    <input type="hidden" name="action" value="client_save">
                                    <input type="hidden" name="route_id" value="<?= $route['id'] ?>">
                                    <label style="font-size:12px; color:#aaa; display:block; margin-top:5px;">Nome</label><input type="text" name="route_note" value="<?= htmlspecialchars($route['note']) ?>">
                                    <label style="color:#28c76f; font-size:12px">White Page (BOTS)</label><input type="url" name="white_page" value="<?= htmlspecialchars($route['white']) ?>" required>
                                    <label style="color:var(--tt-pink); font-size:12px">Black Page (HUMANOS)</label><input type="url" name="black_page" value="<?= htmlspecialchars($route['black']) ?>" required>
                                    <label style="font-size:12px">Token XGO (NAO MUDE SE ESTIVER EM USO)</label><input type="text" name="token_xgo" value="<?= htmlspecialchars($route['token_xgo']) ?>" required>
                                    <button class="btn" style="margin-top:10px; width:100%; background:#28c76f;">SALVAR EDICAO</button>
                                    <button type="button" class="btn-danger" style="margin-top:5px; width:100%" onclick="document.getElementById('edit_route_<?= $route['id'] ?>').style.display='none'">CANCELAR</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-head" style="color:#009688;"><i class="fa-solid fa-code"></i> Codigo de Integracao de Vendas Externa</div>
            <p style="font-size: 13px; color: #888; margin-top: -10px;">Use este snippet PHP na **Pagina de Obrigado/Confirmacao de Pedido** de checkouts externos (Shopify, WooCommerce, etc.) para marcar a venda no Commander.</p>
            
            <label style="font-size: 12px; color: #009688; margin-bottom: 5px; display: block; font-weight: bold;">1. URL de Origem do Click ID (Onde o cliente recebeu o link):</label>
            <p style="font-size: 11px; background: #1a1a1a; padding: 10px; border-radius: 4px; color: #ffc107;">
                O cliente precisa ter chegado na sua Thank You Page com o parametro **?click_id=SEUID** na URL.
            </p>
            
            <label style="font-size: 12px; color: #009688; margin-bottom: 5px; display: block; font-weight: bold;">2. Codigo PHP para Inserir (Server-to-Server - S2S):</label>
            
            <div class="code-area-external" onclick="navigator.clipboard.writeText(this.innerText);alert('Copiado!')">
<?php 
$postback_template = '<?php
/* Commander External Sale Notifier - S2S */
error_reporting(0);

// --- 1. CONFIGURACAO (AJUSTE AQUI) ---
// O sistema DEVE capturar o click_id da URL e o valor da venda.
$click_id = $_GET[\'click_id\'] ?? null;
$sale_value = 97.00; // <<< AJUSTE: COLOQUE AQUI O VALOR DINAMICO DA VENDA (EX: $ORDER_TOTAL)
$postback_url = "' . $base_url . 'postback.php"; 

if (!empty($click_id)) {
    // 2. CHAMADA SERVER-TO-SERVER (Escondida do navegador)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $postback_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        \'click_id\' => $click_id,
        \'payout\' => $sale_value
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    curl_close($ch);
}
?>';
echo htmlspecialchars($postback_template);
?>
            </div>
            <p style="font-size: 11px; color: #009688; margin-top: 5px;">* Clique para copiar. Nao se esqueca de ajustar a variavel **$sale_value** para o valor real da sua venda no seu script!</p>
        </div>

        <div class="card">
            <div class="card-head"><i class="fa-solid fa-link"></i> Gerador de Link + UTM (Para Anuncio)</div>
            
            <div id="domain_container">
                <label style="font-size:12px; color:#25F4EE; margin-bottom:5px; display:block;">1. Dominio onde voce subiu o index.php (Tracker)</label>
                <input type="text" id="g_domain" placeholder="Ex: sualoja.com (sem https)" oninput="genLink()">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                <input type="text" id="g_camp" value="__CAMPAIGN_NAME__" placeholder="Nome Campanha" oninput="genLink()">
                <select id="g_source" onchange="toggleSourceFields()">
                    <option value="google">Google Ads</option>
                    <option value="tiktok">TikTok Ads</option>
                    <option value="outro">Outro</option>
                </select>
                <input type="text" id="g_id" value="{campaignid}" placeholder="ID da Campanha" oninput="genLink()">
                <input type="text" id="g_term" value="{keyword}" placeholder="Palavra-chave (Keyword)" oninput="genLink()">
            </div>

            <label style="margin-top:15px; display:block; font-size:12px;" id="label_tipo_link">Modelo de Acompanhamento (Google):</label>
            <div class="code-box" id="final_link" style="color: #4285F4; font-weight: bold;" onclick="navigator.clipboard.writeText(this.innerText);alert('Copiado!')">Gerando link...</div>
            <p id="google_tip" style="font-size: 10px; color: #888; margin-top: 5px;">No Google Ads, cole este link no campo <b>Modelo de Acompanhamento</b>.</p>
        </div>

        <div class="card">
            <div class="card-head"><i class="fa-solid fa-wrench"></i> Instalacao & Postback</div>
            <p style="font-size: 13px; color: #888; margin-top: -10px;">Para baixar o arquivo **index.php (Tracker)**, utilize o botao "INDEX" na tabela de **Gerenciar Rotas**.</p>
            <label style="font-size:12px; color:gold;">URL de Postback (Venda - S2S):</label>
            <div class="code-box" onclick="navigator.clipboard.writeText(this.innerText);alert('Copiado!')"><?= $base_url ?>postback.php?click_id={src}&payout={commission_value}</div>
             <label style="font-size:12px; color:#FF8C00; margin-top:15px;">URL de Postback (Inicio Checkout - S2S):</label>
            <div class="code-box" onclick="navigator.clipboard.writeText(this.innerText);alert('Copiado!')"><?= $base_url ?>checkout_postback.php?click_id={src}</div>
        </div>

    <div class="overlay" id="overlay"></div>
    <div id="apiModal">
        <h3 style="margin-top:0; color:var(--tt-pink)">Confirmar Acao</h3>
        <p style="font-size:13px; color:#ccc" id="modalText"></p>
        <form method="POST">
            <input type="hidden" name="action" value="toggle_campaign">
            <input type="hidden" name="plat" id="m_plat"><input type="hidden" name="cid" id="m_cid"><input type="hidden" name="status" value="OFF">
            <label style="font-size:11px">Access Token (API):</label><input type="text" name="api_token" placeholder="Cole o token aqui..." required>
            <div id="divAccId"><label style="font-size:11px">Advertiser ID:</label><input type="text" name="account_id" placeholder="ID da Conta"></div>
            <button class="btn" style="margin-top:10px">CONFIRMAR PAUSA</button>
            <button type="button" class="btn-danger" style="margin-top:5px; width:100%" onclick="closeModal()">CANCELAR</button>
        </form>
    </div>

    <script>
        function openApiModal(plat, cid, name) {
            document.getElementById('overlay').style.display = 'block';
            document.getElementById('apiModal').style.display = 'block';
            document.getElementById('m_plat').value = plat;
            document.getElementById('m_cid').value = cid;
            document.getElementById('modalText').innerText = "Pausar campanha: " + name + " (" + cid + ")?";
            document.getElementById('divAccId').style.display = 'block';
        }

        function closeModal() { 
            document.getElementById('overlay').style.display = 'none'; 
            document.getElementById('apiModal').style.display = 'none'; 
        }

        function toggleSourceFields() {
            let src = document.getElementById('g_source').value;
            let domainBox = document.getElementById('domain_container');
            let label = document.getElementById('label_tipo_link');
            let tip = document.getElementById('google_tip');
            let gId = document.getElementById('g_id');
            let gTerm = document.getElementById('g_term');

            if (src === 'google') {
                domainBox.style.display = 'none';
                label.innerText = "Modelo de Acompanhamento (Google Ads):";
                tip.style.display = 'block';
                gId.value = "{campaignid}";
                gTerm.value = "{keyword}";
            } else {
                domainBox.style.display = 'block';
                label.innerText = "Link Final para o Anuncio:";
                tip.style.display = 'none';
                if (src === 'tiktok') {
                    gId.value = "__CAMPAIGN_ID__";
                    gTerm.value = "__AID_NAME__";
                }
            }
            genLink();
        }

        function genLink() {
            let src = document.getElementById('g_source').value;
            let camp = document.getElementById('g_camp').value;
            let id = document.getElementById('g_id').value;
            let term = document.getElementById('g_term').value;
            let box = document.getElementById('final_link');

            if (src === 'google') {
                box.innerText = `{lpurl}?cck=${id}&utm_source=google&utm_term=${term}&gclid={gclid}`;
                box.style.color = "#4285F4";
            } else {
                let domain = document.getElementById('g_domain').value.trim().replace(/^https?:\/\//, '').replace(/\/$/, '');
                let baseUrl = domain ? "https://" + domain + "/" : "https://SEU_SITE.COM/";
                let ttclid = (src === 'tiktok') ? "&ttclid=__CLICKID__" : "";
                
                box.innerText = `${baseUrl}?utm_campaign=${encodeURIComponent(camp)}&utm_source=${src}&cck=${id}&utm_term=${term}${ttclid}`;
                box.style.color = "#FE2C55";
            }
        }

        window.onload = function() {
            toggleSourceFields();
        };
    </script>
</body>
</html>
