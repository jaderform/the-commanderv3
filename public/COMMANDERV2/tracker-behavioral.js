/**
 * COMMANDER V10.6 - Behavioral Tracker JavaScript AVANCADO
 * Coleta dados de comportamento humano e detecta bots/automacao
 * 
 * DETECCOES:
 * - Webdriver, Puppeteer, Playwright, Selenium, PhantomJS
 * - Eventos isTrusted (mouse real vs programatico)
 * - Movimento de mouse em linha reta (bot)
 * - Velocidade impossivel entre cliques
 * - DevTools aberto (revisor)
 * - WebGL e Audio fingerprint
 * - Honeypot invisivel
 * - Proof of Work challenge
 */

(function() {
    'use strict';
    
    // ============================================
    // CONFIGURACAO
    // ============================================
    const CONFIG = {
        apiEndpoint: '/COMMANDERV2/api.php',
        sendInterval: 3000,
        debugMode: false,
        // NOVO: Envia dados via postMessage para o parent (proxy)
        enablePostMessage: true,
        postMessageInterval: 2000
    };
    
    // ============================================
    // DETECCAO DE IFRAME (para postMessage)
    // ============================================
    const isInIframe = (function() {
        try {
            return window.self !== window.top;
        } catch(e) {
            return true; // Se der erro, provavelmente esta em iframe cross-origin
        }
    })();
    
    // ============================================
    // GERACAO DE ID
    // ============================================
    function generateVisitorId() {
        const ua = navigator.userAgent;
        const lang = navigator.language;
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        const screen = window.screen.width + 'x' + window.screen.height;
        const data = ua + '|' + lang + '|' + tz + '|' + screen;
        
        // Hash simples
        let hash = 0;
        for (let i = 0; i < data.length; i++) {
            const char = data.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return 'v_' + Math.abs(hash).toString(36) + '_' + Date.now().toString(36);
    }
    
    // ============================================
    // TRACKER PRINCIPAL
    // ============================================
    const tracker = {
        visitorId: generateVisitorId(),
        startTime: Date.now(),
        
        // Comportamento basico
        scrollCount: 0,
        maxScroll: 0,
        mouseMoveCount: 0,
        mouseActive: false,
        lastMouseMove: 0,
        timeOnPage: 0,
        
        // Deteccao avancada
        untrustedEvents: 0,
        clickTimes: [],
        mousePositions: [],
        straightLineScore: 0,
        impossibleSpeed: false,
        
        // Fingerprints
        canvasHash: '',
        webglHash: '',
        audioHash: '',
        
        // Deteccao de automacao
        automationDetected: null,
        webdriver: false,
        headless: false,
        devtoolsOpen: false,
        
        // Eventos
        events: [],
        
        // NOVO: Score de bot para postMessage
        botScore: 0
    };
    
    // ============================================
    // CALCULO DE BOT SCORE (para postMessage)
    // NOTA: Apenas deteccoes CRITICAS aumentam score
    // Comportamentos normais nao devem penalizar usuario
    // ============================================
    function calculateBotScore() {
        let score = 0;
        
        // 1. Webdriver detectado = CRITICO (automacao certa)
        if (tracker.webdriver) score += 100;
        
        // 2. Headless detectado = CRITICO
        if (tracker.headless) score += 80;
        
        // 3. Automacao detectada = CRITICO
        if (tracker.automationDetected) score += 100;
        
        // 4. Eventos nao confiveis (isTrusted = false) = Suspeito
        // Apenas se tiver MUITOS eventos nao confiaveis
        if (tracker.untrustedEvents > 5) score += 30;
        
        // 5. Movimento de mouse em linha reta = Suspeito (mas pode ser touchpad)
        if (tracker.straightLineScore > 5) score += 15;
        
        // 6. Velocidade impossivel de clique
        if (tracker.impossibleSpeed) score += 20;
        
        // 7. DevTools aberto = NAO penaliza (pode ser desenvolvedor legitimo)
        // if (tracker.devtoolsOpen) score += 0;
        
        // 8. Sem movimento de mouse = NAO penaliza no score
        // (muitos usuarios mobile nao movem mouse)
        // Isso e monitorado separadamente
        
        // 9. Canvas bloqueado = NAO penaliza (extensoes de privacidade sao comuns)
        // if (tracker.canvasBlocked) score += 0;
        
        // 10. Sem plugins = NAO penaliza (navegadores modernos limitam isso)
        // if (navigator.plugins.length === 0) score += 0;
        
        tracker.botScore = score;
        return score;
    }
    
    // ============================================
    // DETECCAO DE AUTOMACAO
    // ============================================
    function detectAutomation() {
        const detected = {
            webdriver: false,
            headless: false,
            automation: null,
            phantomjs: false,
            nightmare: false,
            puppeteer: false,
            playwright: false,
            selenium: false
        };
        
        // 1. navigator.webdriver (Chrome, Firefox)
        if (navigator.webdriver === true) {
            detected.webdriver = true;
        }
        
        // 2. Headless Chrome
        if (/HeadlessChrome/i.test(navigator.userAgent)) {
            detected.headless = true;
        }
        
        // 3. PhantomJS
        if (window.callPhantom || window._phantom) {
            detected.phantomjs = true;
            detected.automation = 'phantomjs';
        }
        
        // 4. Nightmare.js
        if (window.__nightmare) {
            detected.nightmare = true;
            detected.automation = 'nightmare';
        }
        
        // 5. Puppeteer
        if (window.__puppeteer_evaluation_script__ || 
            window.puppeteer ||
            navigator.userAgent.includes('pptr')) {
            detected.puppeteer = true;
            detected.automation = 'puppeteer';
        }
        
        // 6. Playwright
        if (window.playwright || window.__playwright) {
            detected.playwright = true;
            detected.automation = 'playwright';
        }
        
        // 7. Selenium
        if (window.__selenium_unwrapped ||
            window.__webdriver_evaluate ||
            window.__driver_evaluate ||
            window.__webdriver_script_function ||
            window.__webdriver_script_func ||
            window.__webdriver_script_fn ||
            window.$cdc_asdjflasutopfhvcZLmcfl_ ||
            window.$chrome_asyncScriptInfo ||
            document.__selenium_unwrapped ||
            document.__webdriver_evaluate ||
            document.__driver_evaluate) {
            detected.selenium = true;
            detected.automation = 'selenium';
        }
        
        // 8. CasperJS
        if (window.emit || window.callCasper) {
            detected.automation = 'casperjs';
        }
        
        // 9. Node.js environment
        if (window.Buffer || window.global || window.process) {
            detected.automation = 'nodejs';
        }
        
        // 10. Electron (pode ser legitimo, mas suspeito)
        if (navigator.userAgent.includes('Electron')) {
            detected.automation = 'electron';
        }
        
        // 11. Chrome DevTools Protocol
        if (window.cdc_adoQpoasnfa76pfcZLmcfl_Symbol ||
            window.cdc_adoQpoasnfa76pfcZLmcfl_Array ||
            window.cdc_adoQpoasnfa76pfcZLmcfl_Promise) {
            detected.automation = 'cdp';
        }
        
        // 12. Verifica plugins (headless nao tem)
        if (navigator.plugins.length === 0 && !isMobile()) {
            detected.headless = true;
        }
        
        // 13. Verifica languages (headless pode nao ter)
        if (!navigator.languages || navigator.languages.length === 0) {
            detected.headless = true;
        }
        
        // 14. WebGL renderer (headless usa SwiftShader)
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (gl) {
                const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                if (debugInfo) {
                    const renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
                    if (/swiftshader|llvmpipe|softpipe/i.test(renderer)) {
                        detected.headless = true;
                    }
                }
            }
        } catch(e) {}
        
        return detected;
    }
    
    function isMobile() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }
    
    // ============================================
    // DETECCAO DE DEVTOOLS
    // ============================================
    function detectDevTools() {
        const threshold = 160;
        
        // Metodo 1: Diferenca de tamanho da janela
        const widthThreshold = window.outerWidth - window.innerWidth > threshold;
        const heightThreshold = window.outerHeight - window.innerHeight > threshold;
        
        if (widthThreshold || heightThreshold) {
            return true;
        }
        
        // Metodo 2: Tempo de console.log (DevTools faz demorar)
        const start = performance.now();
        console.log('%c', 'font-size:0;padding:0;margin:0;');
        console.clear();
        const end = performance.now();
        
        if (end - start > 100) {
            return true;
        }
        
        return false;
    }
    
    // ============================================
    // FINGERPRINTS
    // ============================================
    function generateCanvasFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            canvas.width = 200;
            canvas.height = 50;
            const ctx = canvas.getContext('2d');
            
            // Texto com fonte especifica
            ctx.textBaseline = 'alphabetic';
            ctx.font = '14px Arial';
            ctx.fillStyle = '#f60';
            ctx.fillRect(100, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText('CMDR Fingerprint', 2, 15);
            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.fillText('CMDR Fingerprint', 4, 17);
            
            // Adiciona elementos graficos
            ctx.beginPath();
            ctx.arc(50, 25, 10, 0, Math.PI * 2);
            ctx.fill();
            
            const dataUrl = canvas.toDataURL();
            
            // Verifica se canvas foi bloqueado
            if (dataUrl === 'data:,') {
                return { hash: 'blocked', blocked: true };
            }
            
            // Hash simples do dataUrl
            let hash = 0;
            for (let i = 0; i < dataUrl.length; i++) {
                const char = dataUrl.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            
            return { hash: Math.abs(hash).toString(36), blocked: false };
        } catch(e) {
            return { hash: 'error', blocked: true };
        }
    }
    
    function generateWebGLFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            
            if (!gl) {
                return { hash: 'no_webgl', blocked: true };
            }
            
            const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
            let data = '';
            
            if (debugInfo) {
                data += gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL) + '|';
                data += gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
            }
            
            data += '|' + gl.getParameter(gl.VERSION);
            data += '|' + gl.getParameter(gl.SHADING_LANGUAGE_VERSION);
            
            // Hash
            let hash = 0;
            for (let i = 0; i < data.length; i++) {
                hash = ((hash << 5) - hash) + data.charCodeAt(i);
                hash = hash & hash;
            }
            
            return { hash: Math.abs(hash).toString(36), blocked: false, data: data };
        } catch(e) {
            return { hash: 'error', blocked: true };
        }
    }
    
    function generateAudioFingerprint() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) {
                return { hash: 'no_audio', blocked: true };
            }
            
            const context = new AudioContext();
            const oscillator = context.createOscillator();
            const analyser = context.createAnalyser();
            const gain = context.createGain();
            const scriptProcessor = context.createScriptProcessor(4096, 1, 1);
            
            gain.gain.value = 0; // Silencioso
            oscillator.type = 'triangle';
            oscillator.frequency.value = 10000;
            
            oscillator.connect(analyser);
            analyser.connect(scriptProcessor);
            scriptProcessor.connect(gain);
            gain.connect(context.destination);
            
            oscillator.start(0);
            
            const fingerprint = new Float32Array(analyser.frequencyBinCount);
            analyser.getFloatFrequencyData(fingerprint);
            
            oscillator.stop();
            context.close();
            
            // Hash dos primeiros valores
            let hash = 0;
            for (let i = 0; i < Math.min(fingerprint.length, 100); i++) {
                hash = ((hash << 5) - hash) + Math.abs(fingerprint[i]);
                hash = hash & hash;
            }
            
            return { hash: Math.abs(hash).toString(36), blocked: false };
        } catch(e) {
            return { hash: 'error', blocked: true };
        }
    }
    
    // ============================================
    // ANALISE DE COMPORTAMENTO
    // ============================================
    
    // Detecta se evento e real ou programatico
    function checkEventTrusted(e) {
        if (e.isTrusted === false) {
            tracker.untrustedEvents++;
            tracker.events.push({
                type: 'untrusted_event',
                eventType: e.type,
                at: Date.now() - tracker.startTime
            });
        }
    }
    
    // Detecta movimento de mouse em linha reta (bot)
    function analyzeMounseMovement(x, y) {
        tracker.mousePositions.push({ x, y, t: Date.now() });
        
        // Mantem apenas ultimas 20 posicoes
        if (tracker.mousePositions.length > 20) {
            tracker.mousePositions.shift();
        }
        
        // Precisa de pelo menos 10 pontos
        if (tracker.mousePositions.length < 10) return;
        
        // Calcula variancia da direcao
        let directions = [];
        for (let i = 1; i < tracker.mousePositions.length; i++) {
            const dx = tracker.mousePositions[i].x - tracker.mousePositions[i-1].x;
            const dy = tracker.mousePositions[i].y - tracker.mousePositions[i-1].y;
            if (dx !== 0 || dy !== 0) {
                directions.push(Math.atan2(dy, dx));
            }
        }
        
        if (directions.length < 5) return;
        
        // Calcula variancia
        const mean = directions.reduce((a, b) => a + b, 0) / directions.length;
        const variance = directions.reduce((sum, d) => sum + Math.pow(d - mean, 2), 0) / directions.length;
        
        // Variancia muito baixa = movimento em linha reta
        if (variance < 0.01) {
            tracker.straightLineScore++;
        }
    }
    
    // Detecta velocidade impossivel entre cliques
    function checkClickSpeed() {
        const now = Date.now();
        tracker.clickTimes.push(now);
        
        // Mantem ultimos 5 cliques
        if (tracker.clickTimes.length > 5) {
            tracker.clickTimes.shift();
        }
        
        // Precisa de pelo menos 2 cliques
        if (tracker.clickTimes.length < 2) return;
        
        // Verifica intervalo entre ultimos 2 cliques
        const interval = tracker.clickTimes[tracker.clickTimes.length - 1] - 
                         tracker.clickTimes[tracker.clickTimes.length - 2];
        
        // Humano: >= 100ms entre cliques
        // Bot: pode ser < 50ms
        if (interval < 100) {
            tracker.impossibleSpeed = true;
            tracker.events.push({
                type: 'impossible_click_speed',
                interval: interval,
                at: Date.now() - tracker.startTime
            });
        }
    }
    
    // ============================================
    // EVENT LISTENERS
    // ============================================
    
    // Mouse move
    document.addEventListener('mousemove', function(e) {
        checkEventTrusted(e);
        
        const now = Date.now();
        if (now - tracker.lastMouseMove > 50) { // Debounce 50ms
            tracker.mouseMoveCount++;
            tracker.lastMouseMove = now;
            tracker.mouseActive = true;
            
            analyzeMounseMovement(e.clientX, e.clientY);
        }
    }, { passive: true });
    
    // Click
    document.addEventListener('click', function(e) {
        checkEventTrusted(e);
        checkClickSpeed();
    }, { passive: true });
    
    // Touch
    document.addEventListener('touchstart', function(e) {
        checkEventTrusted(e);
        tracker.mouseActive = true;
    }, { passive: true });
    
    // Scroll
    window.addEventListener('scroll', function(e) {
        const scrollY = window.scrollY || document.documentElement.scrollTop;
        const pageHeight = document.documentElement.scrollHeight - window.innerHeight;
        
        if (scrollY > tracker.maxScroll) {
            tracker.maxScroll = scrollY;
        }
        
        if (scrollY > (window.innerHeight * 0.3)) {
            if (tracker.scrollCount === 0) {
                tracker.events.push({ type: 'first_scroll', at: Date.now() - tracker.startTime });
            }
            tracker.scrollCount = Math.floor(scrollY / window.innerHeight);
        }
    }, { passive: true });
    
    // Keyboard
    document.addEventListener('keydown', function(e) {
        checkEventTrusted(e);
    }, { passive: true });
    
    // ============================================
    // PROOF OF WORK (opcional)
    // ============================================
    function solveProofOfWork(challenge, difficulty) {
        const target = '0'.repeat(difficulty);
        let nonce = 0;
        const maxIterations = 1000000;
        
        while (nonce < maxIterations) {
            const hash = simpleHash(challenge + nonce);
            if (hash.startsWith(target)) {
                return { nonce, hash, solved: true };
            }
            nonce++;
        }
        
        return { nonce: -1, solved: false };
    }
    
    function simpleHash(str) {
        // Hash simples para PoW (em producao usar SHA256)
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return Math.abs(hash).toString(16).padStart(8, '0');
    }
    
    // ============================================
    // INICIALIZACAO
    // ============================================
    function init() {
        // Detecta automacao
        const automation = detectAutomation();
        tracker.automationDetected = automation.automation;
        tracker.webdriver = automation.webdriver;
        tracker.headless = automation.headless;
        
        // Gera fingerprints
        const canvas = generateCanvasFingerprint();
        tracker.canvasHash = canvas.hash;
        tracker.canvasBlocked = canvas.blocked;
        
        const webgl = generateWebGLFingerprint();
        tracker.webglHash = webgl.hash;
        
        // Audio fingerprint (async, nao bloqueia)
        setTimeout(function() {
            const audio = generateAudioFingerprint();
            tracker.audioHash = audio.hash;
        }, 100);
        
        // Verifica DevTools periodicamente
        setInterval(function() {
            tracker.devtoolsOpen = detectDevTools();
        }, 2000);
        
        // Atualiza tempo na pagina
        setInterval(function() {
            tracker.timeOnPage = Date.now() - tracker.startTime;
        }, 1000);
        
        // Envia dados periodicamente para API
        setInterval(sendBehaviorData, CONFIG.sendInterval);
        
        // NOVO: Envia dados via postMessage para o parent (proxy)
        if (CONFIG.enablePostMessage && isInIframe) {
            setInterval(sendToParent, CONFIG.postMessageInterval);
            
            // Envia imediatamente apos 1 segundo (para deteccao rapida)
            setTimeout(sendToParent, 1000);
            
            // Envia apos interacoes importantes
            document.addEventListener('click', function() {
                setTimeout(sendToParent, 100);
            }, { passive: true });
            
            if (CONFIG.debugMode) {
                console.log('[CMDR] PostMessage habilitado - rodando em iframe');
            }
        }
        
        // Envia antes de sair
        window.addEventListener('beforeunload', function() {
            sendBehaviorData();
            if (isInIframe) sendToParent();
        });
        
        // Salva visitor ID
        try {
            sessionStorage.setItem('commander_visitor_id', tracker.visitorId);
        } catch(e) {}
        
        if (CONFIG.debugMode) {
            console.log('[CMDR] Tracker initialized', tracker);
        }
    }
    
    // ============================================
    // ENVIO DE DADOS VIA POSTMESSAGE (para o proxy/parent)
    // ============================================
    function sendToParent() {
        if (!CONFIG.enablePostMessage || !isInIframe) return;
        
        // Calcula score atualizado
        const score = calculateBotScore();
        
        // Monta relatorio
        const report = {
            type: '__BEHAVIOR_REPORT__',
            score: score,
            details: {
                webdriver: tracker.webdriver ? 50 : 0,
                headless: tracker.headless ? 40 : 0,
                automation: tracker.automationDetected ? 60 : 0,
                untrusted: tracker.untrustedEvents * 10,
                straightLine: tracker.straightLineScore > 3 ? 30 : 0,
                impossibleSpeed: tracker.impossibleSpeed ? 25 : 0,
                devtools: tracker.devtoolsOpen ? 15 : 0,
                noMouse: (tracker.timeOnPage > 5000 && tracker.mouseMoveCount < 3) ? 20 : 0
            },
            dataPoints: {
                mouse: tracker.mouseMoveCount,
                scroll: tracker.scrollCount,
                clicks: tracker.clickTimes.length,
                untrustedEvents: tracker.untrustedEvents
            },
            flags: {
                webdriver: tracker.webdriver,
                headless: tracker.headless,
                automation: tracker.automationDetected,
                devtoolsOpen: tracker.devtoolsOpen,
                straightMouse: tracker.straightLineScore > 3,
                impossibleSpeed: tracker.impossibleSpeed
            },
            fingerprints: {
                canvas: tracker.canvasHash,
                webgl: tracker.webglHash,
                audio: tracker.audioHash
            },
            elapsed: tracker.timeOnPage,
            visitorId: tracker.visitorId
        };
        
        // Envia para o parent (proxy)
        try {
            window.parent.postMessage(report, '*');
            
            if (CONFIG.debugMode) {
                console.log('[CMDR] PostMessage enviado:', report);
            }
        } catch(e) {
            if (CONFIG.debugMode) {
                console.log('[CMDR] Erro ao enviar postMessage:', e);
            }
        }
    }
    
    // ============================================
    // ENVIO DE DADOS PARA API
    // ============================================
    function sendBehaviorData() {
        const data = new FormData();
        
        // Dados basicos
        data.append('action', 'update_behavior');
        data.append('visitor_id', tracker.visitorId);
        data.append('campaign', getCampaignId());
        data.append('time_on_page', tracker.timeOnPage);
        
        // Comportamento
        data.append('scrolls', tracker.scrollCount);
        data.append('max_scroll', tracker.maxScroll);
        data.append('mouse_moves', tracker.mouseMoveCount);
        data.append('has_mouse', tracker.mouseActive ? '1' : '0');
        
        // Deteccao de automacao
        data.append('webdriver', tracker.webdriver ? '1' : '0');
        data.append('headless', tracker.headless ? '1' : '0');
        data.append('automation', tracker.automationDetected || '');
        
        // Eventos suspeitos
        data.append('untrusted_events', tracker.untrustedEvents);
        data.append('straight_mouse', tracker.straightLineScore > 3 ? '1' : '0');
        data.append('impossible_speed', tracker.impossibleSpeed ? '1' : '0');
        
        // DevTools
        data.append('devtools_open', tracker.devtoolsOpen ? '1' : '0');
        
        // Fingerprints
        data.append('canvas_hash', tracker.canvasHash);
        data.append('canvas_blocked', tracker.canvasBlocked ? '1' : '0');
        data.append('webgl_hash', tracker.webglHash);
        data.append('audio_hash', tracker.audioHash || '');
        
        // Sistema
        data.append('plugins', navigator.plugins.length);
        data.append('languages', (navigator.languages || []).length);
        data.append('timezone', Intl.DateTimeFormat().resolvedOptions().timeZone);
        data.append('screen', window.screen.width + 'x' + window.screen.height);
        data.append('touch', 'ontouchstart' in window ? '1' : '0');
        
        // Envia
        fetch(CONFIG.apiEndpoint, {
            method: 'POST',
            body: data,
            keepalive: true
        }).catch(function() {});
    }
    
    function getCampaignId() {
        const params = new URLSearchParams(window.location.search);
        return params.get('c') || params.get('campaign') || 'default';
    }
    
    // ============================================
    // INICIA
    // ============================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Exporta para uso global
    window.CommanderTracker = {
        tracker: tracker,
        solvePoW: solveProofOfWork,
        getFingerprints: function() {
            return {
                canvas: tracker.canvasHash,
                webgl: tracker.webglHash,
                audio: tracker.audioHash
            };
        }
    };
    
})();
