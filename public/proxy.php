<?php
/**
 * PROXY REVERSO GLOBAL V5.1
 * 
 * Proxy completo que carrega a pagina E todos os assets (CSS, JS, imagens)
 * atraves do seu proprio dominio, evitando problemas de CORS.
 * 
 * USO: proxy.php?url=https://site.com/pagina
 * DEBUG: proxy.php?url=https://site.com/pagina&debug=1
 */

// Modo debug - mostra URLs reescritas
$DEBUG = isset($_GET['debug']) && $_GET['debug'] == '1';

error_reporting($DEBUG ? E_ALL : 0);
set_time_limit(300);
ini_set('memory_limit', '256M');

// ============================================================================
// CONFIGURACAO
// ============================================================================

// Dominio do seu proxy (sera usado para reescrever URLs)
$my_domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
             . '://' . $_SERVER['HTTP_HOST'];
$proxy_script = $my_domain . '/proxy.php?url=';

// ============================================================================
// FUNCOES AUXILIARES
// ============================================================================

/**
 * Faz requisicao HTTP com cURL
 */
function fetchUrl($url) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Linux; Android 10; SM-G981B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.162 Mobile Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control: no-cache',
            'Referer: ' . $url
        ],
        CURLOPT_HEADER => true
    ]);
    
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? '';
    
    curl_close($ch);
    
    if ($response === false) {
        return ['error' => 'Falha na requisicao', 'code' => 500];
    }
    
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    return [
        'body' => $body,
        'headers' => $headers,
        'content_type' => $content_type,
        'final_url' => $final_url,
        'code' => $http_code
    ];
}

/**
 * Converte URL relativa em absoluta
 */
function makeAbsolute($relative_url, $base_url) {
    // Limpa espacos
    $relative_url = trim($relative_url);
    
    // Ja e absoluta
    if (preg_match('/^https?:\/\//i', $relative_url)) {
        return $relative_url;
    }
    
    // Data URL ou javascript
    if (preg_match('/^(data:|javascript:|mailto:|tel:|#|about:)/i', $relative_url)) {
        return $relative_url;
    }
    
    // Protocolo relativo
    if (strpos($relative_url, '//') === 0) {
        $parsed = parse_url($base_url);
        return ($parsed['scheme'] ?? 'https') . ':' . $relative_url;
    }
    
    // Parse base URL
    $parsed = parse_url($base_url);
    $scheme = $parsed['scheme'] ?? 'https';
    $host = $parsed['host'] ?? '';
    $base_path = $parsed['path'] ?? '/';
    
    // URL absoluta do servidor (comeca com /)
    if (strpos($relative_url, '/') === 0) {
        return $scheme . '://' . $host . $relative_url;
    }
    
    // URL relativa - resolve em relacao ao diretorio base
    // Se base_path termina com / ou e so /, o diretorio e o proprio path
    if (substr($base_path, -1) === '/' || $base_path === '/') {
        $base_dir = rtrim($base_path, '/');
    } else {
        // Se aponta para um arquivo, pega o diretorio
        $base_dir = dirname($base_path);
    }
    
    // Normaliza
    if ($base_dir === '\\' || $base_dir === '.' || $base_dir === '/') {
        $base_dir = '';
    }
    
    // Remove ./ do inicio
    $relative_url = preg_replace('/^\.\//', '', $relative_url);
    
    // Processa ../
    while (strpos($relative_url, '../') === 0) {
        $relative_url = substr($relative_url, 3);
        $base_dir = dirname($base_dir);
        if ($base_dir === '\\' || $base_dir === '.' || $base_dir === '/') {
            $base_dir = '';
        }
    }
    
    // Monta URL final
    if ($base_dir === '') {
        return $scheme . '://' . $host . '/' . $relative_url;
    }
    return $scheme . '://' . $host . $base_dir . '/' . $relative_url;
}

/**
 * Cria URL do proxy
 */
function proxyUrl($absolute_url, $proxy_script) {
    if (preg_match('/^(data:|javascript:|mailto:|tel:|#|about:)/i', $absolute_url)) {
        return $absolute_url;
    }
    return $proxy_script . rawurlencode($absolute_url);
}

/**
 * Detecta Content-Type pela extensao
 */
function getContentType($url, $default = 'application/octet-stream') {
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    
    $types = [
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'otf' => 'font/otf',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'wav' => 'audio/wav',
        'pdf' => 'application/pdf'
    ];
    
    return $types[$ext] ?? $default;
}

// ============================================================================
// PROCESSAMENTO PRINCIPAL
// ============================================================================

// Obtem URL
$url = isset($_GET['url']) ? trim($_GET['url']) : '';

if (empty($url)) {
    http_response_code(400);
    die('Erro: URL nao fornecida. Use: proxy.php?url=https://site.com');
}

// Decodifica URL
$url = rawurldecode($url);

// Valida URL
if (!preg_match('/^https?:\/\//i', $url)) {
    http_response_code(400);
    die('Erro: URL deve comecar com http:// ou https://');
}

// Busca conteudo
$result = fetchUrl($url);

if (isset($result['error'])) {
    http_response_code($result['code']);
    die('Erro ao buscar URL: ' . $result['error']);
}

$body = $result['body'];
$content_type = $result['content_type'];
$final_url = $result['final_url'];

// Determina tipo de conteudo
$detected_type = getContentType($url, $content_type);
if (empty($content_type) || $content_type === 'application/octet-stream') {
    $content_type = $detected_type;
}

// ============================================================================
// ASSETS (NAO-HTML): CSS, JS, IMAGENS, FONTES, VIDEO
// ============================================================================

$is_html = stripos($content_type, 'text/html') !== false || 
           stripos($content_type, 'application/xhtml') !== false;

if (!$is_html) {
    // Headers
    header('Content-Type: ' . $content_type);
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=86400');
    
    // Se for CSS, reescreve url() dentro dele
    if (stripos($content_type, 'text/css') !== false || getContentType($url) === 'text/css') {
        $body = preg_replace_callback(
            '/url\s*\(\s*["\']?([^"\')]+)["\']?\s*\)/i',
            function($matches) use ($final_url, $proxy_script) {
                $asset_url = trim($matches[1]);
                if (preg_match('/^(data:|#)/i', $asset_url)) {
                    return $matches[0];
                }
                $absolute = makeAbsolute($asset_url, $final_url);
                return 'url("' . proxyUrl($absolute, $proxy_script) . '")';
            },
            $body
        );
    }
    
    echo $body;
    exit;
}

// ============================================================================
// HTML: REESCREVE TODAS AS URLs PARA PASSAR PELO PROXY
// ============================================================================

header('Content-Type: text/html; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

// Funcoes de callback para reescrita
$rewriteUrl = function($url_attr) use ($final_url, $proxy_script) {
    $absolute = makeAbsolute($url_attr, $final_url);
    return proxyUrl($absolute, $proxy_script);
};

// Remove tag <base> existente (pode causar problemas)
$body = preg_replace('/<base[^>]*>/i', '', $body);

// 1. LINK HREF (CSS, favicon, fonts, preload)
$body = preg_replace_callback(
    '/<link([^>]*)href\s*=\s*["\']([^"\']+)["\']([^>]*)>/i',
    function($m) use ($rewriteUrl) {
        return '<link' . $m[1] . 'href="' . $rewriteUrl($m[2]) . '"' . $m[3] . '>';
    },
    $body
);

// 2. SCRIPT SRC
$body = preg_replace_callback(
    '/<script([^>]*)src\s*=\s*["\']([^"\']+)["\']([^>]*)>/i',
    function($m) use ($rewriteUrl) {
        return '<script' . $m[1] . 'src="' . $rewriteUrl($m[2]) . '"' . $m[3] . '>';
    },
    $body
);

// 3. IMG SRC
$body = preg_replace_callback(
    '/<img([^>]*)src\s*=\s*["\']([^"\']+)["\']([^>]*)>/i',
    function($m) use ($rewriteUrl) {
        return '<img' . $m[1] . 'src="' . $rewriteUrl($m[2]) . '"' . $m[3] . '>';
    },
    $body
);

// 4. IMG SRCSET
$body = preg_replace_callback(
    '/srcset\s*=\s*["\']([^"\']+)["\']/',
    function($m) use ($rewriteUrl) {
        $srcset = $m[1];
        $parts = preg_split('/\s*,\s*/', $srcset);
        $newParts = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^(\S+)(\s+.+)?$/', $part, $match)) {
                $url = $match[1];
                $descriptor = $match[2] ?? '';
                $newParts[] = $rewriteUrl($url) . $descriptor;
            }
        }
        return 'srcset="' . implode(', ', $newParts) . '"';
    },
    $body
);

// 5. DATA-SRC, DATA-LAZY-SRC, DATA-ORIGINAL (lazy loading)
$lazy_attrs = ['data-src', 'data-lazy-src', 'data-original', 'data-bg', 'data-background', 'data-image', 'data-srcset'];
foreach ($lazy_attrs as $attr) {
    $body = preg_replace_callback(
        '/' . $attr . '\s*=\s*["\']([^"\']+)["\']/',
        function($m) use ($rewriteUrl, $attr) {
            return $attr . '="' . $rewriteUrl($m[1]) . '"';
        },
        $body
    );
}

// 6. VIDEO/AUDIO SRC
$body = preg_replace_callback(
    '/<(video|audio|source|embed)([^>]*)src\s*=\s*["\']([^"\']+)["\']([^>]*)>/i',
    function($m) use ($rewriteUrl) {
        return '<' . $m[1] . $m[2] . 'src="' . $rewriteUrl($m[3]) . '"' . $m[4] . '>';
    },
    $body
);

// 7. VIDEO POSTER
$body = preg_replace_callback(
    '/poster\s*=\s*["\']([^"\']+)["\']/',
    function($m) use ($rewriteUrl) {
        return 'poster="' . $rewriteUrl($m[1]) . '"';
    },
    $body
);

// 8. STYLE TAGS: url() dentro de <style>
$body = preg_replace_callback(
    '/<style([^>]*)>(.*?)<\/style>/is',
    function($m) use ($final_url, $proxy_script) {
        $css = $m[2];
        $css = preg_replace_callback(
            '/url\s*\(\s*["\']?([^"\')]+)["\']?\s*\)/i',
            function($match) use ($final_url, $proxy_script) {
                $asset_url = trim($match[1]);
                if (preg_match('/^(data:|#)/i', $asset_url)) {
                    return $match[0];
                }
                $absolute = makeAbsolute($asset_url, $final_url);
                return 'url("' . proxyUrl($absolute, $proxy_script) . '")';
            },
            $css
        );
        return '<style' . $m[1] . '>' . $css . '</style>';
    },
    $body
);

// 9. INLINE STYLE: style="background: url(...)"
$body = preg_replace_callback(
    '/style\s*=\s*["\']([^"\']*url\s*\([^)]+\)[^"\']*)["\']/',
    function($m) use ($final_url, $proxy_script) {
        $style = $m[1];
        $style = preg_replace_callback(
            '/url\s*\(\s*["\']?([^"\')]+)["\']?\s*\)/i',
            function($match) use ($final_url, $proxy_script) {
                $asset_url = trim($match[1]);
                if (preg_match('/^(data:|#)/i', $asset_url)) {
                    return $match[0];
                }
                $absolute = makeAbsolute($asset_url, $final_url);
                return 'url("' . proxyUrl($absolute, $proxy_script) . '")';
            },
            $style
        );
        return 'style="' . $style . '"';
    },
    $body
);

// 10. META OG:IMAGE e TWITTER:IMAGE
$body = preg_replace_callback(
    '/<meta([^>]*)(property|name)\s*=\s*["\'](og:image|twitter:image)["\']([^>]*)content\s*=\s*["\']([^"\']+)["\']([^>]*)>/i',
    function($m) use ($rewriteUrl) {
        return '<meta' . $m[1] . $m[2] . '="' . $m[3] . '"' . $m[4] . 'content="' . $rewriteUrl($m[5]) . '"' . $m[6] . '>';
    },
    $body
);

// 11. OBJECT DATA
$body = preg_replace_callback(
    '/<object([^>]*)data\s*=\s*["\']([^"\']+)["\']([^>]*)>/i',
    function($m) use ($rewriteUrl) {
        return '<object' . $m[1] . 'data="' . $rewriteUrl($m[2]) . '"' . $m[3] . '>';
    },
    $body
);

// 12. IFRAME SRC (opcional, pode causar problemas)
// Descomente se necessario:
// $body = preg_replace_callback(
//     '/<iframe([^>]*)src\s*=\s*["\']([^"\']+)["\']([^>]*)>/i',
//     function($m) use ($rewriteUrl) {
//         return '<iframe' . $m[1] . 'src="' . $rewriteUrl($m[2]) . '"' . $m[3] . '>';
//     },
//     $body
// );

// 13. FORCA CARREGAMENTO DE IMAGENS LAZY
// Converte data-src para src se imagem nao tem src valido
$body = preg_replace_callback(
    '/<img([^>]*)>/i',
    function($m) use ($rewriteUrl) {
        $tag = $m[0];
        
        // Se tem data-src mas nao tem src (ou src e placeholder)
        if (preg_match('/data-src\s*=\s*["\']([^"\']+)["\']/', $tag, $dataSrc)) {
            // Verifica se ja tem src real
            if (!preg_match('/\ssrc\s*=\s*["\'](?!data:)[^"\']{5,}["\']/', $tag)) {
                // Remove src placeholder
                $tag = preg_replace('/\ssrc\s*=\s*["\'][^"\']*["\']/', '', $tag);
                // Adiciona src real
                $realSrc = $rewriteUrl($dataSrc[1]);
                $tag = str_replace('>', ' src="' . $realSrc . '">', $tag);
            }
        }
        
        return $tag;
    },
    $body
);

// 14. REMOVE LAZY LOADING QUE PODE IMPEDIR CARREGAMENTO
$body = str_replace('loading="lazy"', '', $body);
$body = str_replace("loading='lazy'", '', $body);

// 15. ADICIONA CSS PARA FORCAR EXIBICAO DE IMAGENS
$force_css = '
<style>
img { opacity: 1 !important; visibility: visible !important; }
img[data-src] { opacity: 1 !important; }
.lazy, .lazyload, .lazyloading { opacity: 1 !important; visibility: visible !important; }
[style*="background"] { background-size: cover !important; }
</style>
';

// Insere antes de </head>
if (stripos($body, '</head>') !== false) {
    $body = str_ireplace('</head>', $force_css . '</head>', $body);
} else {
    $body = $force_css . $body;
}

// DEBUG: Mostra informacoes sobre URLs reescritas
if ($DEBUG) {
    // Conta quantas URLs foram reescritas
    preg_match_all('/proxy\.php\?url=([^"\'>\s]+)/', $body, $proxy_urls);
    $url_count = count($proxy_urls[1]);
    
    $debug_info = '
    <div style="position:fixed;top:0;left:0;right:0;background:#000;color:#0f0;padding:20px;z-index:99999;font-family:monospace;font-size:12px;max-height:50vh;overflow:auto;">
        <h3 style="margin:0 0 10px;color:#0f0;">PROXY DEBUG V5.1</h3>
        <p><strong>URL Original:</strong> ' . htmlspecialchars($url) . '</p>
        <p><strong>URL Final:</strong> ' . htmlspecialchars($final_url) . '</p>
        <p><strong>Content-Type:</strong> ' . htmlspecialchars($content_type) . '</p>
        <p><strong>URLs Reescritas:</strong> ' . $url_count . '</p>
        <p><strong>Proxy Script:</strong> ' . htmlspecialchars($proxy_script) . '</p>
        <details>
            <summary style="cursor:pointer;color:#0ff;">Ver primeiras 20 URLs reescritas</summary>
            <ul style="max-height:200px;overflow:auto;">';
    
    $shown = 0;
    foreach ($proxy_urls[1] as $purl) {
        if ($shown++ >= 20) break;
        $decoded = urldecode($purl);
        $debug_info .= '<li style="word-break:break-all;">' . htmlspecialchars($decoded) . '</li>';
    }
    
    $debug_info .= '</ul></details>
        <button onclick="this.parentElement.style.display=\'none\'" style="margin-top:10px;padding:5px 15px;cursor:pointer;">Fechar Debug</button>
    </div>';
    
    $body = str_ireplace('<body', $debug_info . '<body', $body);
}

// Output
echo $body;
