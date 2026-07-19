<?php
/**
 * PROXY UNIVERSAL - Baseado na V1 que funciona 100%
 * 
 * Abordagem simples:
 * 1. Busca a pagina via cURL
 * 2. Adiciona <base href> para CSS/imagens/JS
 * 3. Injeta script para navegacao interna pelo proxy
 * 
 * ATUALIZADO: Agora mantem navegacao dentro do proxy
 */

error_reporting(0);
ini_set('display_errors', 0);

// Polyfill para str_ends_with (PHP < 8)
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

/**
 * Funcao principal - PROXY SIMPLES (igual V1)
 */
function proxyRequest($targetUrl = null) {
    
    // Navegacao interna
    if (isset($_GET['_nav'])) {
        $targetUrl = base64_decode($_GET['_nav']);
    }
    
    // URL principal
    if (!$targetUrl && isset($_GET['url'])) {
        $targetUrl = $_GET['url'];
    }
    
    if (empty($targetUrl)) {
        http_response_code(400);
        die('URL nao especificada');
    }
    
    // Garante protocolo
    if (!preg_match('/^https?:\/\//', $targetUrl)) {
        $targetUrl = 'https://' . $targetUrl;
    }
    
    // Busca pagina com cURL simples (igual V1)
    $html = proxyPageSimple($targetUrl);
    
    if ($html === false) {
        http_response_code(502);
        die('Erro ao carregar pagina');
    }
    
    // Envia
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}

/**
 * PROXY SIMPLES - Baseado na V1 que funciona 100%
 * Apenas <base href> + script de redirecionamento
 */
function proxyPageSimple($url, $path = '', $query_string = '') {
    // Constroi a URL final
    $final_url = rtrim($url, '/');
    
    if (!empty($path)) {
        $final_url .= '/' . ltrim($path, '/');
    }
    
    if (!empty($query_string)) {
        $separator = (strpos($final_url, '?') !== false) ? '&' : '?';
        $final_url .= $separator . $query_string;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $final_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_ENCODING       => "",
        CURLOPT_USERAGENT      => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Linux; Android 10; Mobile) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control: no-cache'
        ]
    ]);
    
    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final_url_resolved = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    if ($http_code >= 200 && $http_code < 400 && !empty($html)) {
        $base_url_info = parse_url($final_url_resolved);
        $base_origin = $base_url_info['scheme'] . '://' . $base_url_info['host'];
        $base_host = $base_url_info['host'];

        // URL base do proxy para navegacao interna
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $proxyHost = $protocol . '://' . $_SERVER['HTTP_HOST'];
        $proxyBase = $proxyHost . $_SERVER['SCRIPT_NAME'] . '?_nav=';

        // Script para navegacao DENTRO do proxy
        // Quando clicar em links internos, navega pelo proxy
        $redirect_script = '
        <script>
        (function() {
            var BASE_ORIGIN = ' . json_encode($base_origin) . ';
            var BASE_HOST = ' . json_encode($base_host) . ';
            var PROXY_BASE = ' . json_encode($proxyBase) . ';
            
            function resolveUrl(href) {
                if (!href) return null;
                if (/^(javascript:|mailto:|tel:|#|data:|blob:)/i.test(href)) return null;
                if (/^https?:\/\//i.test(href)) return href;
                if (href.indexOf("//") === 0) return "https:" + href;
                if (href.indexOf("/") === 0) return BASE_ORIGIN + href;
                return BASE_ORIGIN + "/" + href.replace(/^\.\//, "");
            }
            
            function isInternal(url) {
                if (!url) return false;
                try {
                    var parsed = new URL(url);
                    return parsed.host === BASE_HOST || parsed.host.endsWith("." + BASE_HOST);
                } catch(e) { return false; }
            }
            
            document.addEventListener("click", function(e) {
                var target = e.target.closest("a");
                if (!target || !target.href) return;
                
                var href = target.getAttribute("href");
                var fullUrl = resolveUrl(href);
                
                if (!fullUrl) return;
                
                // Se link interno, navega pelo proxy
                if (isInternal(fullUrl)) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.location.href = PROXY_BASE + btoa(fullUrl);
                    return false;
                }
                
                // Link externo - abre em nova aba
                target.setAttribute("target", "_blank");
            }, true);
            
            // Intercepta forms
            document.addEventListener("submit", function(e) {
                var form = e.target;
                if (form.tagName !== "FORM") return;
                
                var action = form.getAttribute("action") || "/";
                var method = (form.getAttribute("method") || "GET").toUpperCase();
                var fullUrl = resolveUrl(action);
                
                if (!fullUrl) fullUrl = BASE_ORIGIN + "/";
                
                if (isInternal(fullUrl) && method === "GET") {
                    e.preventDefault();
                    var formData = new FormData(form);
                    var params = new URLSearchParams(formData).toString();
                    var sep = fullUrl.indexOf("?") !== -1 ? "&" : "?";
                    var finalUrl = fullUrl + (params ? sep + params : "");
                    window.location.href = PROXY_BASE + btoa(finalUrl);
                    return false;
                }
            }, true);
        })();
        </script>
        ';

        // Insere script antes do </body>
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $redirect_script . '</body>', $html);
        } else {
            $html .= $redirect_script;
        }

        // Adiciona <base href> se nao existir (para CSS/imagens/JS)
        if (stripos($html, '<base') === false) {
            $html = preg_replace(
                '/(<head[^>]*>)/i',
                '$1<base href="' . $base_origin . '/">',
                $html,
                1
            );
        }
        
        return $html;
    }
    
    return false;
}

/**
 * Busca URL
 */
function fetchUrl($url, $timeout = 15) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8',
        ],
    ]);
    
    $content = curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    if ($httpCode >= 400 || $content === false) {
        return false;
    }
    
    return [
        'content' => $content,
        'final_url' => $finalUrl
    ];
}

/**
 * Busca e injeta CSS inline no HTML
 * Substitui <link rel="stylesheet"> por <style>...conteudo do CSS...</style>
 * Busca de QUALQUER dominio (CDNs, Google Fonts, etc)
 */
function injectCssInline($html, $siteBase, $sitePath) {
    // Encontra TODOS os <link> que podem ser CSS
    // Padroes: rel="stylesheet", href="*.css", type="text/css"
    preg_match_all('/<link[^>]*>/i', $html, $allLinks);
    
    $cssToInject = '';
    $processedUrls = [];
    
    foreach ($allLinks[0] as $linkTag) {
        // Verifica se e CSS
        $isStylesheet = preg_match('/rel=["\']stylesheet["\']/i', $linkTag);
        $hasCssExtension = preg_match('/href=["\'][^"\']*\.css/i', $linkTag);
        $hasCssType = preg_match('/type=["\']text\/css["\']/i', $linkTag);
        
        if (!$isStylesheet && !$hasCssExtension && !$hasCssType) {
            continue;
        }
        
        // Extrai href
        if (preg_match('/href=["\']([^"\']+)["\']/i', $linkTag, $hrefMatch)) {
            $cssUrl = $hrefMatch[1];
            
            // Converte para URL absoluta
            $absoluteCssUrl = toAbsolute($cssUrl, $siteBase, $sitePath);
            
            // Evita duplicatas
            if (isset($processedUrls[$absoluteCssUrl])) {
                $html = str_replace($linkTag, '', $html);
                continue;
            }
            $processedUrls[$absoluteCssUrl] = true;
            
            // Busca conteudo do CSS
            $cssContent = fetchCss($absoluteCssUrl);
            
            if ($cssContent !== false && !empty(trim($cssContent))) {
                // Processa @import dentro do CSS (busca recursivamente)
                $cssContent = processImports($cssContent, $absoluteCssUrl, $processedUrls);
                
                // Reescreve URLs dentro do CSS (url(...))
                $cssContent = rewriteCssUrls($cssContent, $absoluteCssUrl, $siteBase);
                
                // Adiciona ao CSS acumulado
                $cssToInject .= "\n/* CSS: {$cssUrl} */\n" . $cssContent . "\n";
                
                // Remove o <link> original do HTML
                $html = str_replace($linkTag, '', $html);
            }
        }
    }
    
    // Tambem processa <style> tags que podem ter @import
    $html = preg_replace_callback(
        '/<style([^>]*)>(.*?)<\/style>/is',
        function($m) use ($siteBase, $sitePath, &$processedUrls) {
            $styleContent = $m[2];
            $styleContent = processImports($styleContent, $siteBase . '/', $processedUrls);
            $styleContent = rewriteCssUrls($styleContent, $siteBase . '/', $siteBase);
            return '<style' . $m[1] . '>' . $styleContent . '</style>';
        },
        $html
    );
    
    // Injeta todo o CSS como um unico <style>
    if (!empty($cssToInject)) {
        $styleTag = '<style type="text/css">' . $cssToInject . '</style>';
        
        // Insere ANTES de qualquer <style> existente ou antes do </head>
        if (preg_match('/<style/i', $html)) {
            $html = preg_replace('/<style/i', $styleTag . '<style', $html, 1);
        } elseif (stripos($html, '</head>') !== false) {
            $html = str_ireplace('</head>', $styleTag . '</head>', $html);
        } else {
            $html = $styleTag . $html;
        }
    }
    
    return $html;
}

/**
 * Processa @import dentro do CSS recursivamente
 */
function processImports($css, $cssBaseUrl, &$processedUrls) {
    // Encontra @import
    preg_match_all('/@import\s+(?:url\(["\']?([^)"\']+)["\']?\)|["\']([^"\']+)["\'])\s*;?/i', $css, $imports, PREG_SET_ORDER);
    
    foreach ($imports as $import) {
        $importUrl = $import[1] ?: $import[2];
        
        // Converte para absoluta
        if (!preg_match('/^https?:\/\//', $importUrl)) {
            if (strpos($importUrl, '//') === 0) {
                $importUrl = 'https:' . $importUrl;
            } elseif (strpos($importUrl, '/') === 0) {
                $parsed = parse_url($cssBaseUrl);
                $importUrl = $parsed['scheme'] . '://' . $parsed['host'] . $importUrl;
            } else {
                $importUrl = dirname($cssBaseUrl) . '/' . $importUrl;
            }
        }
        
        // Evita loops infinitos
        if (isset($processedUrls[$importUrl])) {
            $css = str_replace($import[0], '', $css);
            continue;
        }
        $processedUrls[$importUrl] = true;
        
        // Busca CSS importado
        $importedCss = fetchCss($importUrl);
        
        if ($importedCss !== false) {
            // Processa imports recursivamente
            $importedCss = processImports($importedCss, $importUrl, $processedUrls);
            // Reescreve URLs
            $importedCss = rewriteCssUrls($importedCss, $importUrl, dirname($cssBaseUrl));
            // Substitui @import pelo conteudo
            $css = str_replace($import[0], "\n/* @import {$importUrl} */\n" . $importedCss . "\n", $css);
        } else {
            // Remove @import que falhou
            $css = str_replace($import[0], '', $css);
        }
    }
    
    return $css;
}

/**
 * Busca conteudo de um arquivo CSS
 */
function fetchCss($url) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/css,*/*;q=0.1',
        ],
    ]);
    
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    if ($httpCode >= 400 || $content === false) {
        return false;
    }
    
    return $content;
}

/**
 * Reescreve URLs dentro do CSS para absolutas
 */
function rewriteCssUrls($css, $cssUrl, $siteBase) {
    // Base do arquivo CSS
    $cssBase = dirname($cssUrl);
    
    // Reescreve url(...)
    $css = preg_replace_callback(
        '/url\(["\']?([^)"\']+)["\']?\)/i',
        function($m) use ($cssBase, $siteBase) {
            $url = trim($m[1]);
            
            // Ignora data: URLs
            if (strpos($url, 'data:') === 0) {
                return $m[0];
            }
            
            // Converte para absoluta
            if (preg_match('/^https?:\/\//', $url)) {
                return $m[0]; // Ja e absoluta
            }
            if (strpos($url, '//') === 0) {
                return 'url(https:' . $url . ')';
            }
            if (strpos($url, '/') === 0) {
                // Absoluta do root
                $parsed = parse_url($cssBase);
                $base = $parsed['scheme'] . '://' . $parsed['host'];
                return 'url(' . $base . $url . ')';
            }
            
            // Relativa ao CSS
            return 'url(' . $cssBase . '/' . $url . ')';
        },
        $css
    );
    
    // Reescreve @import
    $css = preg_replace_callback(
        '/@import\s+["\']([^"\']+)["\']|@import\s+url\(["\']?([^)"\']+)["\']?\)/i',
        function($m) use ($cssBase, $siteBase) {
            $url = $m[1] ?: $m[2];
            
            if (preg_match('/^https?:\/\//', $url)) {
                return $m[0];
            }
            if (strpos($url, '//') === 0) {
                return '@import url(https:' . $url . ')';
            }
            if (strpos($url, '/') === 0) {
                $parsed = parse_url($cssBase);
                $base = $parsed['scheme'] . '://' . $parsed['host'];
                return '@import url(' . $base . $url . ')';
            }
            
            return '@import url(' . $cssBase . '/' . $url . ')';
        },
        $css
    );
    
    return $css;
}

/**
 * Converte URL relativa para absoluta
 */
function toAbsolute($url, $siteBase, $sitePath = '') {
    $url = trim($url);
    
    if (preg_match('/^https?:\/\//', $url)) {
        return $url;
    }
    if (strpos($url, '//') === 0) {
        return 'https:' . $url;
    }
    if (strpos($url, '/') === 0) {
        return $siteBase . $url;
    }
    
    // Relativa
    $base = rtrim($siteBase . $sitePath, '/');
    return $base . '/' . ltrim($url, '/');
}

/**
 * Reescreve links de navegacao
 */
function rewriteNavLinks($html, $siteBase, $proxyBase) {
    return preg_replace_callback(
        '/<a\s([^>]*)href=["\']([^"\']+)["\']([^>]*)>/i',
        function($m) use ($siteBase, $proxyBase) {
            $href = $m[2];
            
            // Ignora links especiais
            if (preg_match('/^(javascript:|mailto:|tel:|#|data:)/i', $href)) {
                return $m[0];
            }
            
            // Converte para absoluta
            $absoluteUrl = toAbsolute($href, $siteBase);
            
            // Se for do mesmo dominio, passa pelo proxy
            $parsed = parse_url($siteBase);
            $siteHost = $parsed['host'];
            $linkParsed = parse_url($absoluteUrl);
            $linkHost = $linkParsed['host'] ?? '';
            
            if ($linkHost === $siteHost) {
                $proxyUrl = $proxyBase . '&_nav=' . base64_encode($absoluteUrl);
                return '<a ' . $m[1] . 'href="' . $proxyUrl . '"' . $m[3] . '>';
            }
            
            return $m[0];
        },
        $html
    );
}

/**
 * Reescreve forms
 */
function rewriteForms($html, $siteBase, $proxyBase) {
    return preg_replace_callback(
        '/<form\s([^>]*)action=["\']([^"\']*)["\']([^>]*)>/i',
        function($m) use ($siteBase, $proxyBase) {
            $action = $m[2] ?: '/';
            $absoluteUrl = toAbsolute($action, $siteBase);
            
            $parsed = parse_url($siteBase);
            $siteHost = $parsed['host'];
            $actionParsed = parse_url($absoluteUrl);
            $actionHost = $actionParsed['host'] ?? '';
            
            if ($actionHost === $siteHost) {
                $proxyUrl = $proxyBase . '&_nav=' . base64_encode($absoluteUrl);
                return '<form ' . $m[1] . 'action="' . $proxyUrl . '"' . $m[3] . '>';
            }
            
            return $m[0];
        },
        $html
    );
}

/**
 * Injeta script de navegacao
 */
function injectScript($html, $siteBase, $proxyBase) {
    $script = '<script>
(function() {
    var siteBase = ' . json_encode($siteBase) . ';
    var proxyBase = ' . json_encode($proxyBase) . ';
    var siteHost = new URL(siteBase).host;
    
    document.addEventListener("click", function(e) {
        var link = e.target.closest("a");
        if (!link) return;
        
        var href = link.getAttribute("href");
        if (!href || /^(javascript:|mailto:|tel:|#|data:)/i.test(href)) return;
        if (href.indexOf("_nav=") !== -1) return;
        
        try {
            var url = new URL(href, siteBase);
            if (url.host === siteHost) {
                e.preventDefault();
                window.location.href = proxyBase + "&_nav=" + btoa(url.href);
            }
        } catch(ex) {}
    }, true);
})();
</script>';

    if (stripos($html, '</body>') !== false) {
        return str_ireplace('</body>', $script . '</body>', $html);
    }
    return $html . $script;
}

/**
 * Serve conteudo para ser carregado em iframe
 * Fetch simples + base href + headers permissivos
 * ATUALIZADO: Reescreve links para passar pelo proxy
 */
function serveForIframe($targetUrl) {
    // Fetch simples sem modificacoes
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $targetUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Linux; Android 10; Mobile) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8',
        ],
    ]);
    
    $html = curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 400 || $html === false) {
        http_response_code(502);
        die('Erro ao carregar pagina');
    }
    
    // Extrai base do site
    $parsed = parse_url($finalUrl);
    $siteBase = $parsed['scheme'] . '://' . $parsed['host'];
    $siteHost = $parsed['host'];
    
    // URL base do proxy para navegacao interna
    // IMPORTANTE: Navega no PARENT (proxy-stealth), nao dentro do iframe
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $proxyHost = $protocol . '://' . $_SERVER['HTTP_HOST'];
    
    // Tenta pegar a URL do proxy-stealth dos parametros (passado pelo proxy-stealth)
    // Formato: api.php?action=iframeproxy&url=...&proxyBase=...
    $proxyBase = isset($_GET['proxyBase']) ? urldecode($_GET['proxyBase']) : '';
    
    // Se nao tiver, usa iframeproxy como fallback (navegacao dentro do iframe)
    if (empty($proxyBase)) {
        $proxyBase = $proxyHost . dirname($_SERVER['SCRIPT_NAME']) . '/api.php?action=iframeproxy&url=';
    }
    
    // Remove qualquer <base> existente e adiciona o nosso
    $html = preg_replace('/<base[^>]*>/i', '', $html);
    $baseTag = '<base href="' . $siteBase . '/">';
    
    if (preg_match('/<head[^>]*>/i', $html)) {
        $html = preg_replace('/<head([^>]*)>/i', '<head$1>' . $baseTag, $html, 1);
    } else {
        $html = '<head>' . $baseTag . '</head>' . $html;
    }
    
    // =============================================
    // REESCREVE LINKS PARA PASSAR PELO PROXY
    // =============================================
    
    // Funcao para converter URL relativa em absoluta
    $makeAbsolute = function($url) use ($siteBase) {
        $url = trim($url);
        if (empty($url)) return $url;
        if (preg_match('/^https?:\/\//i', $url)) return $url;
        if (preg_match('/^(javascript:|mailto:|tel:|#|data:|blob:)/i', $url)) return $url;
        if (strpos($url, '//') === 0) return 'https:' . $url;
        if (strpos($url, '/') === 0) return $siteBase . $url;
        return $siteBase . '/' . ltrim($url, './');
    };
    
    // Funcao para criar link via proxy
    // Se proxyBase aponta para proxy-stealth, usa _blacknav para indicar navegacao interna
    $useBlackNav = (strpos($proxyBase, 'white=') !== false);
    
    $makeProxy = function($url) use ($proxyBase, $makeAbsolute, $siteHost, $useBlackNav) {
        $url = trim($url);
        if (preg_match('/^(javascript:|mailto:|tel:|#|data:|blob:)/i', $url)) return $url;
        $absolute = $makeAbsolute($url);
        
        // Verifica se e link interno (mesmo host)
        $linkHost = parse_url($absolute, PHP_URL_HOST);
        if ($linkHost && $linkHost !== $siteHost && !str_ends_with($linkHost, '.' . $siteHost)) {
            return $absolute; // Link externo, nao passa pelo proxy
        }
        
        // Se usa proxy-stealth, adiciona _blacknav para indicar navegacao interna
        if ($useBlackNav) {
            // Remove o &black= final e adiciona &_blacknav=
            $baseUrl = preg_replace('/&black=$/', '', $proxyBase);
            return $baseUrl . '&_blacknav=' . base64_encode($absolute);
        }
        
        return $proxyBase . urlencode($absolute);
    };
    
    // Reescreve links <a href="...">
    // Se proxyBase aponta para proxy-stealth, adiciona target="_top" para sair do iframe
    $targetTop = (strpos($proxyBase, 'white=') !== false) ? ' target="_top"' : '';
    
    $html = preg_replace_callback(
        '/<a([^>]*)href=["\']([^"\']+)["\']([^>]*)>/is',
        function($m) use ($makeProxy, $targetTop, $proxyBase) {
            $newHref = $makeProxy($m[2]);
            // Adiciona target="_top" apenas se o link foi reescrito para o proxy-stealth
            $addTarget = (strpos($newHref, 'white=') !== false) ? $targetTop : '';
            // Remove target existente se vamos adicionar _top
            $attrs = $addTarget ? preg_replace('/\s*target=["\'][^"\']*["\']/i', '', $m[1] . $m[3]) : $m[1] . $m[3];
            return '<a' . $m[1] . 'href="' . $newHref . '"' . $addTarget . $m[3] . '>';
        },
        $html
    );
    
    // Reescreve forms <form action="...">
    $html = preg_replace_callback(
        '/<form([^>]*)action=["\']([^"\']*)["\']([^>]*)>/is',
        function($m) use ($makeProxy, $targetTop, $proxyBase) {
            $action = trim($m[2]) ?: '/';
            $newAction = $makeProxy($action);
            // Adiciona target="_top" se vai para proxy-stealth
            $addTarget = (strpos($newAction, 'white=') !== false) ? $targetTop : '';
            return '<form' . $m[1] . 'action="' . $newAction . '"' . $addTarget . $m[3] . '>';
        },
        $html
    );
    
    // =============================================
    // INCLUI TRACKER BEHAVIORAL (se existir)
    // O tracker ja tem postMessage integrado
    // =============================================
    
    $behaviorScript = '';
    $trackerPath = __DIR__ . '/tracker-behavioral.js';
    if (file_exists($trackerPath)) {
        $trackerContent = file_get_contents($trackerPath);
        $behaviorScript = '<script>' . $trackerContent . '</script>';
    }

    // Detecta se proxyBase aponta para o proxy-stealth (contem white=)
    $useTopNavigation = (strpos($proxyBase, 'white=') !== false);
    $useTopJs = $useTopNavigation ? 'true' : 'false';
    
    // Prepara proxyBase para JS - se usa proxy-stealth, remove &black= e usa _blacknav
    $jsProxyBase = $proxyBase;
    if ($useTopNavigation) {
        $jsProxyBase = preg_replace('/&black=$/', '&_blacknav=', $proxyBase);
    }
    
    // Injeta script para interceptar navegacao dinamica (JS)
    $navScript = '<script>
(function() {
    var PROXY_BASE = ' . json_encode($jsProxyBase) . ';
    var SITE_HOST = ' . json_encode($siteHost) . ';
    var USE_TOP = ' . $useTopJs . '; // Se true, navega no parent (proxy-stealth)
    var USE_BASE64 = ' . $useTopJs . '; // Se true, codifica URL em base64
    
    // Funcao para construir URL do proxy
    function buildProxyUrl(targetUrl) {
        if (USE_BASE64) {
            return PROXY_BASE + btoa(targetUrl);
        }
        return PROXY_BASE + encodeURIComponent(targetUrl);
    }
    
    // Funcao para navegar - usa top se estiver em iframe com proxy-stealth
    function navigateTo(targetUrl) {
        var url = buildProxyUrl(targetUrl);
        if (USE_TOP && window.top !== window.self) {
            window.top.location.href = url;
        } else {
            window.location.href = url;
        }
    }
    
    // Intercepta cliques em links (para links criados dinamicamente via JS)
    document.addEventListener("click", function(e) {
        var link = e.target.closest("a");
        if (!link) return;
        
        var href = link.getAttribute("href");
        if (!href) return;
        
        // Ignora links especiais
        if (/^(javascript:|mailto:|tel:|#|data:|blob:)/i.test(href)) return;
        
        // Ignora links que ja estao no proxy
        if (href.indexOf("action=iframeproxy") !== -1) return;
        if (href.indexOf("_blacknav=") !== -1) return;
        if (href.indexOf("white=") !== -1 && href.indexOf("black=") !== -1) return;
        
        try {
            var url = new URL(href, window.location.href);
            
            // Verifica se e link interno
            if (url.host === SITE_HOST || url.host.endsWith("." + SITE_HOST)) {
                e.preventDefault();
                e.stopPropagation();
                navigateTo(url.href);
                return false;
            }
        } catch(ex) {
            console.log("[Proxy] Erro ao processar link:", ex);
        }
    }, true);
    
    // Intercepta submissao de forms (para forms criados dinamicamente)
    document.addEventListener("submit", function(e) {
        var form = e.target;
        if (form.tagName !== "FORM") return;
        
        var action = form.getAttribute("action") || window.location.href;
        var method = (form.getAttribute("method") || "GET").toUpperCase();
        
        // Ignora se ja esta no proxy
        if (action.indexOf("action=iframeproxy") !== -1) return;
        if (action.indexOf("_blacknav=") !== -1) return;
        if (action.indexOf("white=") !== -1 && action.indexOf("black=") !== -1) return;
        
        try {
            var url = new URL(action, window.location.href);
            
            // Verifica se e interno
            if (url.host === SITE_HOST || url.host.endsWith("." + SITE_HOST)) {
                if (method === "GET") {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var formData = new FormData(form);
                    var params = new URLSearchParams(formData).toString();
                    var separator = url.href.indexOf("?") !== -1 ? "&" : "?";
                    var finalUrl = url.href + (params ? separator + params : "");
                    
                    navigateTo(finalUrl);
                    return false;
                }
                // Para POST, muda o action do form para navegar no top
                if (USE_TOP) {
                    form.setAttribute("target", "_top");
                }
                form.setAttribute("action", buildProxyUrl(url.href));
            }
        } catch(ex) {
            console.log("[Proxy] Erro ao processar form:", ex);
        }
    }, true);
    
    // Intercepta window.location changes
    var originalAssign = window.location.assign;
    var originalReplace = window.location.replace;
    
    function proxyNavigateLocation(url, method) {
        try {
            var parsed = new URL(url, window.location.href);
            if (parsed.host === SITE_HOST || parsed.host.endsWith("." + SITE_HOST)) {
                var proxyUrl = buildProxyUrl(parsed.href);
                // Usa top se configurado
                if (USE_TOP && window.top !== window.self) {
                    window.top.location.href = proxyUrl;
                } else {
                    method === "replace" ? originalReplace.call(window.location, proxyUrl) : originalAssign.call(window.location, proxyUrl);
                }
                return;
            }
        } catch(ex) {}
        method === "replace" ? originalReplace.call(window.location, url) : originalAssign.call(window.location, url);
    }
    
    window.location.assign = function(url) { proxyNavigateLocation(url, "assign"); };
    window.location.replace = function(url) { proxyNavigateLocation(url, "replace"); };
    
    // Intercepta history.pushState e replaceState
    // Se USE_TOP, navega no parent em vez de usar history
    var originalPushState = history.pushState;
    var originalReplaceState = history.replaceState;
    
    history.pushState = function(state, title, url) {
        if (url) {
            try {
                var parsed = new URL(url, window.location.href);
                if (parsed.host === SITE_HOST || parsed.host.endsWith("." + SITE_HOST)) {
                    if (USE_TOP && window.top !== window.self) {
                        // Navega no parent em vez de usar pushState
                        window.top.location.href = buildProxyUrl(parsed.href);
                        return;
                    }
                    url = buildProxyUrl(parsed.href);
                }
            } catch(ex) {}
        }
        return originalPushState.call(this, state, title, url);
    };
    
    history.replaceState = function(state, title, url) {
        if (url) {
            try {
                var parsed = new URL(url, window.location.href);
                if (parsed.host === SITE_HOST || parsed.host.endsWith("." + SITE_HOST)) {
                    if (USE_TOP && window.top !== window.self) {
                        // Navega no parent em vez de usar replaceState
                        window.top.location.href = buildProxyUrl(parsed.href);
                        return;
                    }
                    url = buildProxyUrl(parsed.href);
                }
            } catch(ex) {}
        }
        return originalReplaceState.call(this, state, title, url);
    };
})();
</script>';
    
    // Injeta scripts antes do </body>
    $allScripts = $behaviorScript . $navScript;
    
    if (stripos($html, '</body>') !== false) {
        $html = str_ireplace('</body>', $allScripts . '</body>', $html);
    } else {
        $html .= $allScripts;
    }
    
    // Headers permissivos para iframe
    header_remove('X-Frame-Options');
    header_remove('Content-Security-Policy');
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Frame-Options: ALLOWALL');
    header('Content-Security-Policy: frame-ancestors *');
    
    echo $html;
    exit;
}
