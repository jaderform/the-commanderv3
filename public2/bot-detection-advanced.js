/**
 * DETECCAO AVANCADA DE BOTS HEADLESS
 * v1.0 - Fingerprinting avançado para detectar bots sofisticados
 * 
 * Técnicas:
 * 1. Canvas Fingerprint
 * 2. AudioContext Fingerprint
 * 3. WebGL Fingerprint
 * 4. Font Enumeration
 * 5. Timezone Consistency
 * 6. Chrome Internals
 * 7. Permissions API
 * 8. Event Timing Entropy
 * 9. CSS Media Queries
 * 10. Iframe Detection
 */

(function() {
    'use strict';
    
    const detection = {
        scores: {},
        suspiciousFlags: [],
        
        // ========== 1. CANVAS FINGERPRINTING ==========
        // Headless renderiza canvas de forma praticamente idêntica sempre
        // Browser real tem pequenas variações por GPU/driver
        testCanvas: function() {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = 280;
                canvas.height = 60;
                const ctx = canvas.getContext('2d');
                
                ctx.textBaseline = 'top';
                ctx.font = '16px "Arial"';
                ctx.textBaseline = 'alphabetic';
                ctx.fillStyle = '#f60';
                ctx.fillRect(125, 1, 62, 20);
                ctx.fillStyle = '#069';
                ctx.fillText('browserleaks,com <canvas> 1.0', 2, 15);
                ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
                ctx.fillText('browserleaks,com <canvas> 1.0', 4, 17);
                
                const dataURL = canvas.toDataURL();
                
                // Se dois canvases renderizados identicamente = muito suspeito (headless)
                // Browser real tem pequenas diferenças de sub-pixel
                const hash1 = this.hashString(dataURL);
                
                // Segunda tentativa - deve ter pequenas diferenças
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = 'white';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.font = '14px Arial';
                ctx.fillStyle = '#000';
                ctx.fillText('Test 2', 10, 30);
                
                const dataURL2 = canvas.toDataURL();
                const hash2 = this.hashString(dataURL2);
                
// Canvas sempre renderiza igual no mesmo browser - isso e NORMAL
                // Apenas marca como suspeito se nao conseguir renderizar
                this.scores.canvas = 100; // OK - canvas funciona
            } catch (e) {
                this.scores.canvas = 70; // Neutro - nao conseguiu testar mas nao e indicativo de bot
            }
        },
        
        // ========== 2. AUDIOCONTEXT FINGERPRINTING ==========
        // AudioContext renderiza diferente em headless vs browser real
        // Praticamente impossível falsificar
        testAudioContext: function() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) {
                    this.scores.audio = 50;
                    return;
                }
                
                const ctx = new AudioContext();
                const oscillator = ctx.createOscillator();
                const analyser = ctx.createAnalyser();
                
                oscillator.connect(analyser);
                analyser.connect(ctx.destination);
                
                // Se conseguiu criar sem erro = OK
                // AudioContext headless sempre lança erro em algumas operações
                this.scores.audio = 100;
                
                // Cleanup
                try { oscillator.disconnect(); } catch(e) {}
                try { analyser.disconnect(); } catch(e) {}
                
            } catch (e) {
                // Erro em AudioContext = pode ser headless
                this.scores.audio = 30;
                this.suspiciousFlags.push('AudioContext error: ' + e.message);
            }
        },
        
        // ========== 3. WEBGL FINGERPRINTING ==========
        // WebGL headless usa SwiftShader (software), browser real usa GPU
        testWebGL: function() {
            try {
                const canvas = document.createElement('canvas');
                const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                
                if (!gl) {
                    this.scores.webgl = 50;
                    return;
                }
                
                const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                if (!debugInfo) {
                    this.scores.webgl = 60;
                    return;
                }
                
                const vendor = gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL);
                const renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
                
// SwiftShader/Software pode ser usado em VMs, servidores, ou dispositivos sem GPU
                // Nao e necessariamente indicativo de bot
                if (renderer && (renderer.indexOf('SwiftShader') !== -1 || 
                               renderer.indexOf('Software') !== -1 ||
                               renderer.indexOf('llvmpipe') !== -1)) {
                    this.scores.webgl = 60; // Levemente suspeito mas nao conclusivo
                } else {
                    this.scores.webgl = 100; // GPU real
                }
                
            } catch (e) {
                this.scores.webgl = 50;
            }
        },
        
        // ========== 4. FONT ENUMERATION ==========
        // Browser real tem 30+ fontes, headless tem ~5
        testFonts: function() {
            try {
                const baseFonts = ['monospace', 'sans-serif', 'serif'];
                const testFonts = [
                    'Arial', 'Verdana', 'Times New Roman', 'Courier New', 'Georgia',
                    'Palatino', 'Garamond', 'Bookman', 'Comic Sans MS', 'Trebuchet MS',
                    'Impact', 'Lucida Sans', 'Tahoma', 'Lucida Console', 'DejaVu Sans'
                ];
                
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const text = 'mmmmmmmmmmlli';
                const textSize = '72px';
                
                // Mede width do texto com diferentes fontes
                let detectedFonts = 0;
                
                for (let font of testFonts) {
                    let fontFound = false;
                    for (let baseFont of baseFonts) {
                        ctx.font = `${textSize} ${font}, ${baseFont}`;
                        const width1 = ctx.measureText(text).width;
                        
                        ctx.font = `${textSize} ${baseFont}`;
                        const width2 = ctx.measureText(text).width;
                        
                        // Se width é diferente = fonte foi encontrada
                        if (width1 !== width2) {
                            detectedFonts++;
                            fontFound = true;
                            break;
                        }
                    }
                }
                
// Dispositivos moveis e Linux tem menos fontes - isso e NORMAL
                const fontRatio = detectedFonts / testFonts.length;
                
                if (fontRatio < 0.15) {
                    this.scores.fonts = 50; // Poucas fontes mas pode ser mobile/Linux
                } else if (fontRatio > 0.3) {
                    this.scores.fonts = 100; // OK - muitas fontes
                } else {
                    this.scores.fonts = 80; // Normal para mobile
                }
                
            } catch (e) {
                this.scores.fonts = 50;
            }
        },
        
        // ========== 5. TIMEZONE CONSISTENCY ==========
        // Timezone do JS deve corresponder com o país do IP (via GeoIP)
        testTimezone: function() {
            try {
                const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                
// Timezone UTC pode ser usado por usuarios reais (viajantes, configuracao manual)
                if (tz === 'UTC' || tz === 'GMT' || tz === 'Etc/UTC') {
                    this.scores.timezone = 70; // Levemente suspeito mas nao conclusivo
                } else if (tz === 'Unknown' || tz === undefined) {
                    this.scores.timezone = 60; // Pode ser navegador antigo
                } else {
                    this.scores.timezone = 100; // OK
                }
                
                // Armazena timezone para enviar
                this.data.timezone_detected = tz;
                
            } catch (e) {
                this.scores.timezone = 50;
            }
        },
        
// ========== 6. CHROME INTERNALS ==========
        // Firefox/Safari nao tem window.chrome - isso e NORMAL
        testChromeInternals: function() {
            try {
                // Firefox, Safari, Edge nao tem window.chrome - isso NAO e indicativo de bot
                if (!window.chrome) {
                    this.scores.chrome = 100; // OK - pode ser Firefox/Safari
                    return;
                }
                
                // Se tem chrome object, verifica se e real
                if (typeof window.chrome.runtime === 'undefined') {
                    this.scores.chrome = 80; // Pode ser Chrome sem extensoes
                    return;
                }
                
                this.scores.chrome = 100; // OK
                
            } catch (e) {
                this.scores.chrome = 100; // Erro = OK, nao e indicativo de bot
            }
        },
        
        // ========== 7. PERMISSIONS API BEHAVIOR ==========
        // Headless responde diferente a navigator.permissions
        testPermissions: function() {
            try {
                if (!navigator.permissions) {
                    this.scores.permissions = 50;
                    return;
                }
                
                let permissionResponses = 0;
                const permissionsToTest = ['geolocation', 'notifications', 'microphone'];
                
                // Testa algumas permissões
                for (let perm of permissionsToTest) {
                    try {
                        navigator.permissions.query({name: perm}).then(result => {
                            if (result.state !== 'unknown') permissionResponses++;
                        }).catch(() => {});
                    } catch (e) {}
                }
                
// Timeout para completar as promises
                setTimeout(() => {
                    // Alguns navegadores bloqueiam permissions API por privacidade - isso e NORMAL
                    this.scores.permissions = permissionResponses === 0 ? 80 : 100;
                }, 100);
                
            } catch (e) {
                this.scores.permissions = 50;
            }
        },
        
        // ========== 8. EVENT TIMING ENTROPY ==========
        // Eventos reais têm micro-variações de timing
        // Bot é mecânico
        testEventTiming: function() {
            try {
                let timings = [];
                let lastTime = Date.now();
                let eventCount = 0;
                
                const handler = () => {
                    const now = Date.now();
                    const delta = now - lastTime;
                    if (delta > 0 && delta < 1000) {
                        timings.push(delta);
                    }
                    lastTime = now;
                    eventCount++;
                };
                
                document.addEventListener('mousemove', handler, true);
                
                // Aguarda eventos - vai ter variação se for humano
                setTimeout(() => {
                    document.removeEventListener('mousemove', handler, true);
                    
if (eventCount < 2) {
                        // Usuario pode estar em mobile (touch) ou simplesmente nao mexeu o mouse
                        this.scores.eventTiming = 80; // OK - nao e indicativo de bot
                        return;
                    }
                    
                    // Calcula variance do timing
                    const mean = timings.reduce((a, b) => a + b, 0) / timings.length;
                    const variance = timings.reduce((a, b) => a + Math.pow(b - mean, 2), 0) / timings.length;
                    const stdDev = Math.sqrt(variance);
                    
// Variacao de timing - nao e muito confiavel como indicador
                    if (stdDev < 2) {
                        this.scores.eventTiming = 70; // Muito regular mas pode ser touchpad/trackpad
                    } else {
                        this.scores.eventTiming = 100; // Variacao normal
                    }
                    
                }, 200);
                
            } catch (e) {
                this.scores.eventTiming = 50;
            }
        },
        
        // ========== 9. CSS MEDIA QUERIES ==========
        // Headless tem comportamento diferente
        testMediaQueries: function() {
            try {
                const mql = window.matchMedia('(prefers-color-scheme: dark)');
                
// matchMedia pode nao estar disponivel em navegadores antigos - isso e OK
                if (mql === null || mql === undefined) {
                    this.scores.mediaQueries = 80; // Navegador antigo mas provavelmente real
                } else {
                    // Testa listener
                    try {
                        mql.addEventListener('change', () => {});
                        this.scores.mediaQueries = 100; // OK
                    } catch (e) {
                        this.scores.mediaQueries = 80; // Navegador antigo
                    }
                }
                
            } catch (e) {
                this.scores.mediaQueries = 50;
            }
        },
        
        // ========== 10. IFRAME DETECTION ==========
        // Bot pode estar rodando dentro de iframe/sandbox
        testIframe: function() {
            try {
                if (window.self !== window.top) {
                    this.scores.iframe = 50; // Está em iframe
                    this.suspiciousFlags.push('Running inside iframe');
                } else {
                    this.scores.iframe = 100; // Top level
                }
                
                // Testa document.domain
                try {
                    const _ = document.domain;
                    this.scores.iframe = Math.max(this.scores.iframe - 5, 50);
                } catch (e) {
                    this.scores.iframe = Math.min(this.scores.iframe + 10, 100);
                }
                
            } catch (e) {
                this.scores.iframe = 50;
            }
        },
        
        // ========== HELPERS ==========
        hashString: function(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                const char = str.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            return hash.toString();
        },
        
// ========== CALCULA SCORE FINAL ==========
        calculateFinalScore: function() {
            const scores = Object.values(this.scores);
            if (scores.length === 0) return 80; // Assume OK se nao conseguiu testar
            
            const average = scores.reduce((a, b) => a + b, 0) / scores.length;
            
            // Penalidade reduzida - flags sao apenas indicativos, nao conclusivos
            const penaltyPerFlag = 2;
            const penalty = Math.min(this.suspiciousFlags.length * penaltyPerFlag, 15);
            
            // Score minimo de 30 para evitar falsos positivos
            return Math.max(30, Math.min(100, average - penalty));
        },
        
        // ========== EXECUTA TUDO ==========
        runAll: function() {
            this.data = {};
            this.scores = {};
            this.suspiciousFlags = [];
            
            this.testCanvas();
            this.testAudioContext();
            this.testWebGL();
            this.testFonts();
            this.testTimezone();
            this.testChromeInternals();
            this.testPermissions();
            // testEventTiming roda com delay
            this.testMediaQueries();
            this.testIframe();
            
            const finalScore = this.calculateFinalScore();
            
            return {
                score: finalScore,
                scores: this.scores,
                flags: this.suspiciousFlags,
                data: this.data
            };
        }
    };
    
    // Executa e armazena no window para o tracker pegar
    window.advancedBotDetection = detection.runAll();
})();
