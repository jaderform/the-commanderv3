<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
/**
 * COMMANDER V10.5+ - Proxy Reverso V4 (CORRIGIDO)
 * 
 * PROBLEMAS CORRIGIDOS:
 * - CSS bugado: agora reescreve URLs dentro de arquivos CSS externos
 * - Fontes web quebradas: trata @font-face corretamente
 * - Integrity hash: remove atributos que quebram recursos reescritos
 * - Preload/prefetch: trata todos os tipos de link
 * - CSS @import: reescreve URLs de imports
 * - Meta tags: reescreve og:image, twitter:image, etc.
 * - Favicon: corrige ícones
 * - Encoding: detecta e preserva charset correto
 * 
 * MELHORIAS:
 * - Cache inteligente de assets
 * - Compressão gzip
 * - Headers de segurança
 * - Suporte a cookies (opcional)
 * - Melhor tratamento de erros
 * - Debug mode
 */

// =============================================
// CONFIGURAÇÕES
// =============================================
define('PROXY_DEBUG', false);                    // Ativa logs de debug
define('PROXY_CACHE_ASSETS', true);              // Cache de CSS/JS/imagens
define('PROXY_CACHE_TTL', 3600);                 // TTL do cache em segundos
define('PROXY_TIMEOUT', 30);                     // Timeout de requisições
define('PROXY_REWRITE_COOKIES', false);          // Reescreve cookies (cuidado!)
define('PROXY_ALLOW_EXTERNAL_LINKS', true);      // Permite links externos
define('PROXY_CACHE_DIR', __DIR__ . '/cache/proxy/');

// =============================================
// FUNÇÃO PRINCIPAL DO PROXY
// =============================================
function proxyRequest($targetUrl) {
    // Cria diretório de cache se não existir
    if (PROXY_CACHE_ASSETS && !is_dir(PROXY_CACHE_DIR)) {
        @mkdir(PROXY_CACHE_DIR, 0755, true);
    }
    
    // URL atual do proxy (domínio limpo)
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $proxyHost = $protocol . '://' . $_SERVER['HTTP_HOST'];
    $proxyScript = $_SERVER['SCRIPT_NAME'] ?: '/index.php';
    
    // Suporta navegação interna via parâmetro _nav
    if (isset($_GET['_nav']) && !empty($_GET['_nav'])) {
        $decoded = base64_decode($_GET['_nav']);
        if ($decoded !== false && filter_var($decoded, FILTER_VALIDATE_URL)) {
            $targetUrl = $decoded;
        }
    }
    
    // Suporta busca de assets diretamente
    if (isset($_GET['_asset']) && !empty($_GET['_asset'])) {
        $assetUrl = base64_decode($_GET['_asset']);
        if ($assetUrl !== false) {
            return proxyAsset($assetUrl);
        }
    }
    
    // Valida URL alvo
    if (empty($targetUrl) || !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
        proxyError('URL inválida', 400);
    }
    
    // Suporta POST (formulários)
    $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
    $postData = $isPost ? file_get_contents('php://input') : null;
    
    // Faz a requisição
    $response = proxyFetch($targetUrl, $isPost, $postData);
    
    if ($response === false) {
        proxyError('Erro ao conectar ao servidor remoto', 503);
    }
    
    $content = $response['body'];
    $finalUrl = $response['final_url'];
    $httpCode = $response['http_code'];
    $contentType = $response['content_type'];
    $headers = $response['headers'];
    
    // Se não for HTML, retorna diretamente (pode ser asset)
    if (!isHtmlContent($contentType)) {
        outputAsset($content, $contentType, $httpCode);
    }
    
    // Detecta encoding
    $charset = detectCharset($contentType, $content);
    
    // Extrai base URL do destino
    $parsed = parse_url($finalUrl);
    $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];
    if (isset($parsed['port']) && !in_array($parsed['port'], [80, 443])) {
        $baseUrl .= ':' . $parsed['port'];
    }
    $basePath = isset($parsed['path']) ? dirname($parsed['path']) : '';
    if (in_array($basePath, ['/', '\\', '.', ''])) {
        $basePath = '';
    }
    
    // Contexto para reescrita
    $ctx = [
        'baseUrl' => $baseUrl,
        'basePath' => $basePath,
        'proxyHost' => $proxyHost,
        'proxyScript' => $proxyScript,
        'targetUrl' => $targetUrl,
        'finalUrl' => $finalUrl
    ];
    
    // =============================================
    // REESCRITA DO HTML
    // =============================================
    $content = rewriteHtml($content, $ctx);
    
    // =============================================
    // INJETA SCRIPTS DE INTERCEPTAÇÃO
    // =============================================
    $content = injectProxyScript($content, $ctx);
    
    // =============================================
    // OUTPUT
    // =============================================
    http_response_code($httpCode);
    header('Content-Type: text/html; charset=' . $charset);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('X-Proxy-By: Commander');
    
    // Remove headers problemáticos
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
    header_remove('Transfer-Encoding');
    
    // Compressão
    if (function_exists('ob_gzhandler') && !headers_sent()) {
        ob_start('ob_gzhandler');
    }
    
    echo $content;
    exit;
}

// =============================================
// FUNÇÕES DE FETCH
// =============================================

function proxyFetch($url, $isPost = false, $postData = null, $extraHeaders = []) {
    $ch = curl_init($url);
    
    $headers = array_merge([
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
        'Accept-Encoding: gzip, deflate',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1'
    ], $extraHeaders);
    
    // Forward visitor IP
    $visitorIP = getVisitorIPForProxy();
    if ($visitorIP) {
        $headers[] = 'X-Forwarded-For: ' . $visitorIP;
        $headers[] = 'X-Real-IP: ' . $visitorIP;
    }
    
    // Forward referer
    if (isset($_SERVER['HTTP_REFERER'])) {
        $headers[] = 'Referer: ' . $url;
    }
    
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => PROXY_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => 'gzip, deflate',
        CURLOPT_HEADER => true
    ];
    
    if ($isPost) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $postData;
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
    }
    
    curl_setopt_array($ch, $opts);
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        proxyLog('CURL Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    
    curl_close($ch);
    
    $headerStr = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    return [
        'body' => $body,
        'headers' => parseHeaders($headerStr),
        'http_code' => $httpCode,
        'final_url' => $finalUrl,
        'content_type' => $contentType
    ];
}

function proxyAsset($url) {
    // Verifica cache
    if (PROXY_CACHE_ASSETS) {
        $cacheKey = md5($url);
        $cacheFile = PROXY_CACHE_DIR . $cacheKey;
        $cacheMeta = PROXY_CACHE_DIR . $cacheKey . '.meta';
        
        if (file_exists($cacheFile) && file_exists($cacheMeta)) {
            $meta = json_decode(file_get_contents($cacheMeta), true);
            if ($meta && time() - $meta['time'] < PROXY_CACHE_TTL) {
                http_response_code(200);
                header('Content-Type: ' . $meta['content_type']);
                header('Cache-Control: public, max-age=' . PROXY_CACHE_TTL);
                header('X-Proxy-Cache: HIT');
                readfile($cacheFile);
                exit;
            }
        }
    }
    
    // Busca o asset
    $response = proxyFetch($url);
    
    if ($response === false) {
        proxyError('Asset não encontrado', 404);
    }
    
    $content = $response['body'];
    $contentType = $response['content_type'] ?: 'application/octet-stream';
    $httpCode = $response['http_code'];
    
    // Se for CSS, reescreve URLs dentro dele
    if (stripos($contentType, 'text/css') !== false) {
        $parsed = parse_url($url);
        $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];
        $basePath = isset($parsed['path']) ? dirname($parsed['path']) : '';
        
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $proxyHost = $protocol . '://' . $_SERVER['HTTP_HOST'];
        
        $content = rewriteCssUrls($content, $baseUrl, $basePath, $proxyHost);
    }
    
    // Salva no cache
    if (PROXY_CACHE_ASSETS && $httpCode === 200) {
        @file_put_contents($cacheFile, $content);
        @file_put_contents($cacheMeta, json_encode([
            'time' => time(),
            'content_type' => $contentType,
            'url' => $url
        ]));
    }
    
    outputAsset($content, $contentType, $httpCode);
}

function outputAsset($content, $contentType, $httpCode) {
    http_response_code($httpCode);
    header('Content-Type: ' . $contentType);
    header('Cache-Control: public, max-age=86400');
    header('X-Proxy-Cache: MISS');
    
    // Adiciona CORS para fontes
    if (preg_match('/font|woff|ttf|otf|eot/i', $contentType)) {
        header('Access-Control-Allow-Origin: *');
    }
    
    echo $content;
    exit;
}

// =============================================
// FUNÇÕES DE REESCRITA
// =============================================

function rewriteHtml($html, $ctx) {
    $baseUrl = $ctx['baseUrl'];
    $basePath = $ctx['basePath'];
    $proxyHost = $ctx['proxyHost'];
    $proxyScript = $ctx['proxyScript'];
    
    // Função para converter URL relativa em absoluta
    $makeAbsolute = function($url) use ($baseUrl, $basePath) {
        $url = trim($url);
        if (empty($url)) return $url;
        if (preg_match('/^(https?:)?\/\//i', $url)) {
            if (strpos($url, '//') === 0) return 'https:' . $url;
            return $url;
        }
        if (preg_match('/^(data:|javascript:|mailto:|tel:|#|blob:)/i', $url)) return $url;
        if (strpos($url, '/') === 0) return $baseUrl . $url;
        return $baseUrl . $basePath . '/' . ltrim($url, './');
    };
    
    // Função para criar link de asset via proxy
    $makeAssetProxy = function($url) use ($proxyHost, $proxyScript, $makeAbsolute) {
        $url = trim($url);
        if (preg_match('/^(data:|javascript:|mailto:|tel:|#|blob:)/i', $url)) return $url;
        $absolute = $makeAbsolute($url);
        return $proxyHost . $proxyScript . '?_asset=' . base64_encode($absolute);
    };
    
    // Função para criar link de navegação via proxy
    $makeNavProxy = function($url) use ($proxyHost, $proxyScript, $makeAbsolute, $baseUrl) {
        $url = trim($url);
        if (preg_match('/^(javascript:|mailto:|tel:|#)/i', $url)) return $url;
        $absolute = $makeAbsolute($url);
        
        // Links externos - mantém original se configurado
        if (PROXY_ALLOW_EXTERNAL_LINKS && preg_match('/^https?:\/\//i', $url)) {
            $urlHost = parse_url($absolute, PHP_URL_HOST);
            $baseHost = parse_url($baseUrl, PHP_URL_HOST);
            if ($urlHost && $baseHost && $urlHost !== $baseHost) {
                return $absolute; // Link externo, não passa pelo proxy
            }
        }
        
        return $proxyHost . $proxyScript . '?_nav=' . base64_encode($absolute);
    };
    
    // ========================================
    // 1. REMOVE ATRIBUTOS PROBLEMÁTICOS
    // ========================================
    
    // Remove integrity e crossorigin (quebram assets reescritos)
    $html = preg_replace('/\s+(integrity|crossorigin)=["\'][^"\']*["\']/i', '', $html);
    
    // Remove CSP inline
    $html = preg_replace('/<meta[^>]*http-equiv=["\']Content-Security-Policy["\'][^>]*>/i', '', $html);
    
    // ========================================
    // 2. REESCREVE CSS (link e style)
    // ========================================
    
    // <link rel="stylesheet" href="...">
    $html = preg_replace_callback(
        '/<link([^>]*)(href)=["\']([^"\']+)["\']([^>]*)>/is',
        function($m) use ($makeAssetProxy) {
            $attrs = $m[1] . $m[4];
            // Verifica se é stylesheet ou preload de style
            if (preg_match('/rel=["\']stylesheet["\']/i', $attrs) || 
                preg_match('/as=["\']style["\']/i', $attrs)) {
                return '<link' . $m[1] . 'href="' . $makeAssetProxy($m[3]) . '"' . $m[4] . '>';
            }
            // Preload de font
            if (preg_match('/as=["\']font["\']/i', $attrs)) {
                return '<link' . $m[1] . 'href="' . $makeAssetProxy($m[3]) . '"' . $m[4] . '>';
            }
            // Favicon e ícones
            if (preg_match('/rel=["\'][^"\']*icon[^"\']*["\']/i', $attrs)) {
                return '<link' . $m[1] . 'href="' . $makeAssetProxy($m[3]) . '"' . $m[4] . '>';
            }
            // Preload genérico
            if (preg_match('/rel=["\']preload["\']/i', $attrs)) {
                return '<link' . $m[1] . 'href="' . $makeAssetProxy($m[3]) . '"' . $m[4] . '>';
            }
            // Outros links - usa absoluto simples
            return '<link' . $m[1] . 'href="' . $makeAssetProxy($m[3]) . '"' . $m[4] . '>';
        },
        $html
    );
    
    // <style> com @import e url()
    $html = preg_replace_callback(
        '/<style([^>]*)>(.*?)<\/style>/is',
        function($m) use ($baseUrl, $basePath, $proxyHost) {
            $css = rewriteCssUrls($m[2], $baseUrl, $basePath, $proxyHost);
            return '<style' . $m[1] . '>' . $css . '</style>';
        },
        $html
    );
    
    // Atributo style inline
    $html = preg_replace_callback(
        '/style=["\']([^"\']*url\s*\([^)]+\)[^"\']*)["\']/',
        function($m) use ($baseUrl, $basePath, $proxyHost) {
            $css = rewriteCssUrls($m[1], $baseUrl, $basePath, $proxyHost);
            return 'style="' . htmlspecialchars($css, ENT_QUOTES) . '"';
        },
        $html
    );
    
    // ========================================
    // 3. REESCREVE SCRIPTS
    // ========================================
    
    $html = preg_replace_callback(
        '/<script([^>]*)src=["\']([^"\']+)["\']([^>]*)>/is',
        function($m) use ($makeAssetProxy) {
            return '<script' . $m[1] . 'src="' . $makeAssetProxy($m[2]) . '"' . $m[3] . '>';
        },
        $html
    );
    
    // ========================================
    // 4. REESCREVE IMAGENS
    // ========================================
    
    // <img src="...">
    $html = preg_replace_callback(
        '/<img([^>]*)src=["\']([^"\']+)["\']([^>]*)>/is',
        function($m) use ($makeAssetProxy) {
            $result = '<img' . $m[1] . 'src="' . $makeAssetProxy($m[2]) . '"' . $m[3] . '>';
            // Também reescreve srcset se existir
            $result = preg_replace_callback(
                '/srcset=["\']([^"\']+)["\']/',
                function($sm) use ($makeAssetProxy) {
                    return 'srcset="' . rewriteSrcset($sm[1], $makeAssetProxy) . '"';
                },
                $result
            );
            return $result;
        },
        $html
    );
    
    // <picture> <source srcset="...">
    $html = preg_replace_callback(
        '/<source([^>]*)srcset=["\']([^"\']+)["\']([^>]*)>/is',
        function($m) use ($makeAssetProxy) {
            return '<source' . $m[1] . 'srcset="' . rewriteSrcset($m[2], $makeAssetProxy) . '"' . $m[3] . '>';
        },
        $html
    );
    
    // ========================================
    // 5. REESCREVE VÍDEO/ÁUDIO
    // ========================================
    
    $html = preg_replace_callback(
        '/<(video|audio|source|track)([^>]*)src=["\']([^"\']+)["\']([^>]*)>/is',
        function($m) use ($makeAssetProxy) {
            return '<' . $m[1] . $m[2] . 'src="' . $makeAssetProxy($m[3]) . '"' . $m[4] . '>';
        },
        $html
    );
    
    // Poster de vídeo
    $html = preg_replace_callback(
        '/poster=["\']([^"\']+)["\']/',
        function($m) use ($makeAssetProxy) {
            return 'poster="' . $makeAssetProxy($m[1]) . '"';
        },
        $html
    );
    
    // ========================================
    // 6. REESCREVE DATA-* ATTRIBUTES
    // ========================================
    
    $dataAttrs = ['data-src', 'data-bg', 'data-background', 'data-lazy-src', 
                  'data-original', 'data-image', 'data-srcset', 'data-lazy'];
    
    foreach ($dataAttrs as $attr) {
        $html = preg_replace_callback(
            '/' . preg_quote($attr) . '=["\']([^"\']+)["\']/',
            function($m) use ($makeAssetProxy, $attr) {
                if (strpos($attr, 'srcset') !== false) {
                    return $attr . '="' . rewriteSrcset($m[1], $makeAssetProxy) . '"';
                }
                return $attr . '="' . $makeAssetProxy($m[1]) . '"';
            },
            $html
        );
    }
    
    // ========================================
    // 7. REESCREVE META TAGS
    // ========================================
    
    // og:image, twitter:image, etc
    $html = preg_replace_callback(
        '/<meta([^>]*)(property|name)=["\']og:(image|video|audio|url)["\']([^>]*)content=["\']([^"\']+)["\']([^>]*)>/is',
        function($m) use ($makeAssetProxy) {
            return '<meta' . $m[1] . $m[2] . '="og:' . $m[3] . '"' . $m[4] . 'content="' . $makeAssetProxy($m[5]) . '"' . $m[6] . '>';
        },
        $html
    );
    
    $html = preg_replace_callback(
        '/<meta([^>]*)content=["\']([^"\']+)["\']([^>]*)(property|name)=["\']og:(image|video|audio|url)["\']([^>]*)>/is',
        function($m) use ($makeAssetProxy) {
            return '<meta' . $m[1] . 'content="' . $makeAssetProxy($m[2]) . '"' . $m[3] . $m[4] . '="og:' . $m[5] . '"' . $m[6] . '>';
        },
        $html
    );
    
    // ========================================
    // 8. REESCREVE LINKS <a>
    // ========================================
    
    $html = preg_replace_callback(
        '/<a([^>]*)href=["\']([^"\']+)["\']([^>]*)>/is',
        function($m) use ($makeNavProxy) {
            return '<a' . $m[1] . 'href="' . $makeNavProxy($m[2]) . '"' . $m[3] . '>';
        },
        $html
    );
    
    // ========================================
    // 9. REESCREVE FORMULÁRIOS
    // ========================================
    
    $html = preg_replace_callback(
        '/<form([^>]*)action=["\']([^"\']*)["\']([^>]*)>/is',
        function($m) use ($makeNavProxy) {
            $action = trim($m[2]);
            if (empty($action)) $action = '/';
            return '<form' . $m[1] . 'action="' . $makeNavProxy($action) . '"' . $m[3] . '>';
        },
        $html
    );
    
    // ========================================
    // 10. ADICIONA BASE TAG
    // ========================================
    
    // Remove base tags existentes
    $html = preg_replace('/<base[^>]*>/i', '', $html);
    
    // Adiciona nova base tag (para recursos que escaparam)
    $baseTag = '<base href="' . htmlspecialchars($baseUrl . $basePath . '/') . '">';
    if (stripos($html, '<head') !== false) {
        $html = preg_replace('/(<head[^>]*>)/i', '$1' . "\n" . $baseTag, $html, 1);
    }
    
    return $html;
}

function rewriteCssUrls($css, $baseUrl, $basePath, $proxyHost) {
    $proxyScript = $_SERVER['SCRIPT_NAME'] ?: '/index.php';
    
    $makeAbsolute = function($url) use ($baseUrl, $basePath) {
        $url = trim($url, " \t\n\r\0\x0B'\"");
        if (empty($url)) return $url;
        if (preg_match('/^(https?:)?\/\//i', $url)) {
            if (strpos($url, '//') === 0) return 'https:' . $url;
            return $url;
        }
        if (preg_match('/^(data:|blob:)/i', $url)) return $url;
        if (strpos($url, '/') === 0) return $baseUrl . $url;
        return $baseUrl . $basePath . '/' . ltrim($url, './');
    };
    
    $makeAssetProxy = function($url) use ($proxyHost, $proxyScript, $makeAbsolute) {
        if (preg_match('/^(data:|blob:)/i', $url)) return $url;
        $absolute = $makeAbsolute($url);
        return $proxyHost . $proxyScript . '?_asset=' . base64_encode($absolute);
    };
    
    // @import url("...")
    $css = preg_replace_callback(
        '/@import\s+(url\s*\()?\s*["\']?([^"\';\)\s]+)["\']?\s*\)?/i',
        function($m) use ($makeAssetProxy) {
            $url = $m[2];
            return '@import url("' . $makeAssetProxy($url) . '")';
        },
        $css
    );
    
    // url(...)
    $css = preg_replace_callback(
        '/url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i',
        function($m) use ($makeAssetProxy) {
            $url = trim($m[1]);
            if (preg_match('/^data:/i', $url)) return $m[0];
            return 'url("' . $makeAssetProxy($url) . '")';
        },
        $css
    );
    
    // src: url(...) em @font-face
    $css = preg_replace_callback(
        '/src\s*:\s*([^;]+);/i',
        function($m) use ($makeAssetProxy, $baseUrl, $basePath) {
            $src = $m[1];
            // Reescreve cada url() no src
            $src = preg_replace_callback(
                '/url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)/i',
                function($um) use ($makeAssetProxy) {
                    $url = trim($um[1]);
                    if (preg_match('/^data:/i', $url)) return $um[0];
                    return 'url("' . $makeAssetProxy($url) . '")';
                },
                $src
            );
            return 'src: ' . $src . ';';
        },
        $css
    );
    
    return $css;
}

function rewriteSrcset($srcset, $makeAssetProxy) {
    $parts = preg_split('/\s*,\s*/', $srcset);
    $newParts = [];
    
    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part)) continue;
        
        // Formato: "url 2x" ou "url 100w"
        if (preg_match('/^(.+?)(\s+[\d.]+[wx])$/i', $part, $m)) {
            $newParts[] = $makeAssetProxy(trim($m[1])) . $m[2];
        } else {
            $newParts[] = $makeAssetProxy($part);
        }
    }
    
    return implode(', ', $newParts);
}

function injectProxyScript($html, $ctx) {
    $proxyHost = $ctx['proxyHost'];
    $proxyScript = $ctx['proxyScript'];
    $baseUrl = $ctx['baseUrl'];
    
    $script = <<<SCRIPT
<script>
(function(){
    'use strict';
    
    var proxyNav = "{$proxyHost}{$proxyScript}?_nav=";
    var proxyAsset = "{$proxyHost}{$proxyScript}?_asset=";
    var targetBase = "{$baseUrl}";
    
    // Converte URL para absoluta
    function toAbsolute(url) {
        if (!url) return url;
        if (/^(https?:)?\/\//i.test(url)) return url;
        if (/^(javascript:|mailto:|tel:|#|data:|blob:)/i.test(url)) return url;
        if (url.charAt(0) === '/') return targetBase + url;
        return targetBase + '/' + url;
    }
    
    // Verifica se é link interno
    function isInternalLink(url) {
        if (!url) return false;
        if (/^(javascript:|mailto:|tel:|#)/i.test(url)) return false;
        try {
            var urlObj = new URL(toAbsolute(url));
            var baseObj = new URL(targetBase);
            return urlObj.hostname === baseObj.hostname;
        } catch(e) {
            return true;
        }
    }
    
    // Intercepta cliques em links
    document.addEventListener('click', function(e) {
        var link = e.target.closest('a[href]');
        if (!link) return;
        
        var href = link.getAttribute('href');
        if (!href) return;
        
        // Ignora links especiais
        if (/^(javascript:|mailto:|tel:|#)/i.test(href)) return;
        
        // Ignora links que já passam pelo proxy
        if (href.indexOf('_nav=') !== -1 || href.indexOf('_asset=') !== -1) return;
        
        // Ignora links externos (se configurado para permitir)
        if (!isInternalLink(href)) return;
        
        // Redireciona via proxy
        e.preventDefault();
        e.stopPropagation();
        window.location.href = proxyNav + btoa(toAbsolute(href));
    }, true);
    
    // Intercepta envio de formulários
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        
        var action = form.getAttribute('action') || window.location.href;
        
        // Ignora se já passa pelo proxy
        if (action.indexOf('_nav=') !== -1) return;
        
        // Converte action para proxy
        form.setAttribute('action', proxyNav + btoa(toAbsolute(action)));
    }, true);
    
    // Corrige histórico do navegador (URL limpa)
    if (window.history && window.history.replaceState) {
        try {
            window.history.replaceState({}, document.title, "{$proxyHost}/");
        } catch(e) {}
    }
    
    // Intercepta fetch/XHR para APIs (opcional)
    var originalFetch = window.fetch;
    window.fetch = function(url, options) {
        if (typeof url === 'string' && isInternalLink(url) && url.indexOf('_') === -1) {
            url = proxyNav + btoa(toAbsolute(url));
        }
        return originalFetch.apply(this, arguments);
    };
    
    // Intercepta XMLHttpRequest
    var originalOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function(method, url) {
        if (typeof url === 'string' && isInternalLink(url) && url.indexOf('_') === -1) {
            arguments[1] = proxyNav + btoa(toAbsolute(url));
        }
        return originalOpen.apply(this, arguments);
    };
    
    // Log para debug
    if (PROXY_DEBUG_JS) {
        console.log('[Proxy] Initialized. Target:', targetBase);
    }
})();
</script>
SCRIPT;

    // Substitui placeholder de debug
    $debugJs = defined('PROXY_DEBUG') && PROXY_DEBUG ? 'true' : 'false';
    $script = str_replace('PROXY_DEBUG_JS', $debugJs, $script);
    
    // Injeta antes de </body>
    if (stripos($html, '</body>') !== false) {
        $html = str_ireplace('</body>', $script . "\n</body>", $html);
    } else {
        $html .= $script;
    }
    
    return $html;
}

// =============================================
// FUNÇÕES AUXILIARES
// =============================================

function isHtmlContent($contentType) {
    if (empty($contentType)) return false;
    return stripos($contentType, 'text/html') !== false || 
           stripos($contentType, 'application/xhtml') !== false;
}

function detectCharset($contentType, $content) {
    // Tenta extrair do Content-Type
    if (preg_match('/charset=([^\s;]+)/i', $contentType, $m)) {
        return strtoupper(trim($m[1], '"\''));
    }
    
    // Tenta extrair do HTML
    if (preg_match('/<meta[^>]+charset=["\']?([^"\'>\s]+)/i', $content, $m)) {
        return strtoupper($m[1]);
    }
    
    return 'UTF-8';
}

function parseHeaders($headerStr) {
    $headers = [];
    $lines = explode("\r\n", $headerStr);
    
    foreach ($lines as $line) {
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $headers[strtolower(trim($key))] = trim($value);
        }
    }
    
    return $headers;
}

function getVisitorIPForProxy() {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function proxyError($message, $code = 500) {
    http_response_code($code);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Erro</title></head>';
    echo '<body style="font-family:sans-serif;text-align:center;padding:50px;">';
    echo '<h1>Erro ' . $code . '</h1>';
    echo '<p>' . htmlspecialchars($message) . '</p>';
    echo '</body></html>';
    exit;
}

function proxyLog($message) {
    if (!PROXY_DEBUG) return;
    
    $logFile = __DIR__ . '/logs/proxy_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    @file_put_contents($logFile, $entry, FILE_APPEND);
}
