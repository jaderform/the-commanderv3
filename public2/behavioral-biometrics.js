/**
 * COMMANDER - Behavioral Biometrics Engine v2.0
 * Sistema avancado de analise comportamental para deteccao de bots
 * 
 * Analisa:
 * - Movimentos do mouse (trajetoria, velocidade, aceleracao, curvatura)
 * - Padroes de digitacao (ritmo, tempo entre teclas, pressao)
 * - Comportamento de scroll (velocidade, padroes, momentum)
 * - Interacoes touch (pressao, area, gestos)
 * - Sensores do dispositivo (giroscopio, acelerometro)
 * - Timing de interacoes (hesitacao, tempo de reacao)
 */

(function() {
    'use strict';
    
    window.BehavioralBiometrics = {
        
        // ==========================================
        // CONFIGURACAO
        // ==========================================
        config: {
            sampleRate: 50,           // Coletar dados a cada 50ms
            minSamples: 20,           // Minimo de amostras para analise
            maxSamples: 500,          // Maximo de amostras armazenadas
            analysisDelay: 2000,      // Analisar apos 2 segundos
            debug: false
        },
        
        // ==========================================
        // ARMAZENAMENTO DE DADOS
        // ==========================================
        data: {
            // Mouse
            mousePositions: [],       // {x, y, t}
            mouseClicks: [],          // {x, y, t, button}
            mouseVelocities: [],      // Velocidades calculadas
            mouseAccelerations: [],   // Aceleracoes calculadas
            mouseCurvatures: [],      // Curvaturas da trajetoria
            mouseAngles: [],          // Angulos de movimento
            
            // Teclado
            keyPresses: [],           // {key, downTime, upTime, duration}
            keyIntervals: [],         // Tempo entre teclas
            keyHoldTimes: [],         // Tempo segurando tecla
            
            // Scroll
            scrollEvents: [],         // {y, t, delta}
            scrollVelocities: [],     // Velocidades de scroll
            scrollPatterns: [],       // Padroes detectados
            
            // Touch
            touchEvents: [],          // {x, y, t, force, radiusX, radiusY}
            touchGestures: [],        // Gestos detectados
            touchPressures: [],       // Variacoes de pressao
            
            // Sensores
            deviceMotion: [],         // Acelerometro
            deviceOrientation: [],    // Giroscopio
            
            // Timing
            firstInteraction: null,
            lastInteraction: null,
            interactionGaps: [],      // Tempo entre interacoes
            hesitations: [],          // Momentos de hesitacao antes de acao
            
            // Focus
            focusChanges: [],         // Mudancas de foco
            visibilityChanges: [],    // Tab visivel/oculta
            
            // Analise
            scores: {},
            flags: [],
            humanProbability: 50
        },
        
        // ==========================================
        // INICIALIZACAO
        // ==========================================
        init: function() {
            this.startTime = Date.now();
            this.bindEvents();
            this.startSensorCollection();
            
            // Analise periodica
            setTimeout(() => this.analyze(), this.config.analysisDelay);
            setInterval(() => this.analyze(), 5000);
            
            if (this.config.debug) {
                console.log('[Biometrics] Initialized');
            }
        },
        
        bindEvents: function() {
            // Mouse events
            document.addEventListener('mousemove', (e) => this.onMouseMove(e), { passive: true });
            document.addEventListener('mousedown', (e) => this.onMouseDown(e), { passive: true });
            document.addEventListener('mouseup', (e) => this.onMouseUp(e), { passive: true });
            document.addEventListener('click', (e) => this.onClick(e), { passive: true });
            
            // Keyboard events
            document.addEventListener('keydown', (e) => this.onKeyDown(e), { passive: true });
            document.addEventListener('keyup', (e) => this.onKeyUp(e), { passive: true });
            
            // Scroll events
            document.addEventListener('scroll', (e) => this.onScroll(e), { passive: true });
            document.addEventListener('wheel', (e) => this.onWheel(e), { passive: true });
            
            // Touch events
            document.addEventListener('touchstart', (e) => this.onTouchStart(e), { passive: true });
            document.addEventListener('touchmove', (e) => this.onTouchMove(e), { passive: true });
            document.addEventListener('touchend', (e) => this.onTouchEnd(e), { passive: true });
            
            // Focus events
            document.addEventListener('visibilitychange', () => this.onVisibilityChange());
            window.addEventListener('focus', () => this.onFocusChange('focus'));
            window.addEventListener('blur', () => this.onFocusChange('blur'));
        },
        
        startSensorCollection: function() {
            // Acelerometro e Giroscopio
            if (window.DeviceMotionEvent) {
                window.addEventListener('devicemotion', (e) => this.onDeviceMotion(e), { passive: true });
            }
            if (window.DeviceOrientationEvent) {
                window.addEventListener('deviceorientation', (e) => this.onDeviceOrientation(e), { passive: true });
            }
        },
        
        // ==========================================
        // COLETA DE DADOS - MOUSE
        // ==========================================
        onMouseMove: function(e) {
            const now = Date.now();
            const pos = { x: e.clientX, y: e.clientY, t: now };
            
            this.recordInteraction(now);
            
            // Limita amostras
            if (this.data.mousePositions.length >= this.config.maxSamples) {
                this.data.mousePositions.shift();
            }
            
            // Calcula velocidade e aceleracao
            if (this.data.mousePositions.length > 0) {
                const last = this.data.mousePositions[this.data.mousePositions.length - 1];
                const dt = (now - last.t) / 1000; // segundos
                
                if (dt > 0 && dt < 1) { // Ignora gaps muito grandes
                    const dx = e.clientX - last.x;
                    const dy = e.clientY - last.y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    const velocity = distance / dt;
                    
                    this.data.mouseVelocities.push(velocity);
                    if (this.data.mouseVelocities.length > this.config.maxSamples) {
                        this.data.mouseVelocities.shift();
                    }
                    
                    // Aceleracao
                    if (this.data.mouseVelocities.length > 1) {
                        const lastVel = this.data.mouseVelocities[this.data.mouseVelocities.length - 2];
                        const acceleration = (velocity - lastVel) / dt;
                        this.data.mouseAccelerations.push(acceleration);
                        if (this.data.mouseAccelerations.length > this.config.maxSamples) {
                            this.data.mouseAccelerations.shift();
                        }
                    }
                    
                    // Angulo de movimento
                    const angle = Math.atan2(dy, dx) * (180 / Math.PI);
                    this.data.mouseAngles.push(angle);
                    if (this.data.mouseAngles.length > this.config.maxSamples) {
                        this.data.mouseAngles.shift();
                    }
                    
                    // Curvatura (mudanca de direcao)
                    if (this.data.mouseAngles.length > 1) {
                        const lastAngle = this.data.mouseAngles[this.data.mouseAngles.length - 2];
                        let curvature = Math.abs(angle - lastAngle);
                        if (curvature > 180) curvature = 360 - curvature;
                        this.data.mouseCurvatures.push(curvature);
                        if (this.data.mouseCurvatures.length > this.config.maxSamples) {
                            this.data.mouseCurvatures.shift();
                        }
                    }
                }
            }
            
            this.data.mousePositions.push(pos);
        },
        
        onMouseDown: function(e) {
            this.recordInteraction(Date.now());
            this.pendingClick = { x: e.clientX, y: e.clientY, t: Date.now(), button: e.button };
        },
        
        onMouseUp: function(e) {
            if (this.pendingClick) {
                const duration = Date.now() - this.pendingClick.t;
                this.pendingClick.duration = duration;
            }
        },
        
        onClick: function(e) {
            const now = Date.now();
            this.recordInteraction(now);
            
            // Detecta hesitacao (pausa antes do clique)
            if (this.data.mousePositions.length > 5) {
                const recentPositions = this.data.mousePositions.slice(-5);
                const lastMovement = recentPositions[recentPositions.length - 1].t;
                const hesitation = now - lastMovement;
                if (hesitation > 100 && hesitation < 2000) {
                    this.data.hesitations.push(hesitation);
                }
            }
            
            this.data.mouseClicks.push({
                x: e.clientX,
                y: e.clientY,
                t: now,
                button: e.button
            });
        },
        
        // ==========================================
        // COLETA DE DADOS - TECLADO
        // ==========================================
        onKeyDown: function(e) {
            const now = Date.now();
            this.recordInteraction(now);
            
            // Registra tecla pressionada
            if (!this.pendingKeys) this.pendingKeys = {};
            if (!this.pendingKeys[e.code]) {
                this.pendingKeys[e.code] = {
                    key: e.key,
                    code: e.code,
                    downTime: now
                };
            }
            
            // Intervalo entre teclas
            if (this.lastKeyTime) {
                const interval = now - this.lastKeyTime;
                if (interval > 0 && interval < 2000) {
                    this.data.keyIntervals.push(interval);
                    if (this.data.keyIntervals.length > this.config.maxSamples) {
                        this.data.keyIntervals.shift();
                    }
                }
            }
            this.lastKeyTime = now;
        },
        
        onKeyUp: function(e) {
            const now = Date.now();
            
            if (this.pendingKeys && this.pendingKeys[e.code]) {
                const keyData = this.pendingKeys[e.code];
                const duration = now - keyData.downTime;
                
                this.data.keyPresses.push({
                    key: keyData.key,
                    code: keyData.code,
                    downTime: keyData.downTime,
                    upTime: now,
                    duration: duration
                });
                
                this.data.keyHoldTimes.push(duration);
                if (this.data.keyHoldTimes.length > this.config.maxSamples) {
                    this.data.keyHoldTimes.shift();
                }
                
                delete this.pendingKeys[e.code];
            }
        },
        
        // ==========================================
        // COLETA DE DADOS - SCROLL
        // ==========================================
        onScroll: function(e) {
            const now = Date.now();
            this.recordInteraction(now);
            
            const scrollY = window.scrollY || document.documentElement.scrollTop;
            
            if (this.lastScrollY !== undefined) {
                const delta = scrollY - this.lastScrollY;
                const dt = (now - this.lastScrollTime) / 1000;
                
                if (dt > 0 && dt < 1) {
                    const velocity = Math.abs(delta) / dt;
                    this.data.scrollVelocities.push(velocity);
                    if (this.data.scrollVelocities.length > this.config.maxSamples) {
                        this.data.scrollVelocities.shift();
                    }
                }
                
                this.data.scrollEvents.push({ y: scrollY, t: now, delta: delta });
                if (this.data.scrollEvents.length > this.config.maxSamples) {
                    this.data.scrollEvents.shift();
                }
            }
            
            this.lastScrollY = scrollY;
            this.lastScrollTime = now;
        },
        
        onWheel: function(e) {
            // Detecta scroll com wheel (mais preciso)
            const pattern = {
                deltaX: e.deltaX,
                deltaY: e.deltaY,
                deltaMode: e.deltaMode,
                t: Date.now()
            };
            this.data.scrollPatterns.push(pattern);
            if (this.data.scrollPatterns.length > this.config.maxSamples) {
                this.data.scrollPatterns.shift();
            }
        },
        
        // ==========================================
        // COLETA DE DADOS - TOUCH
        // ==========================================
        onTouchStart: function(e) {
            const now = Date.now();
            this.recordInteraction(now);
            
            for (let touch of e.touches) {
                const touchData = {
                    x: touch.clientX,
                    y: touch.clientY,
                    t: now,
                    force: touch.force || 0,
                    radiusX: touch.radiusX || 0,
                    radiusY: touch.radiusY || 0,
                    identifier: touch.identifier,
                    type: 'start'
                };
                
                this.data.touchEvents.push(touchData);
                
                if (touch.force > 0) {
                    this.data.touchPressures.push(touch.force);
                }
            }
            
            this.pendingTouch = { startTime: now, startX: e.touches[0].clientX, startY: e.touches[0].clientY };
        },
        
        onTouchMove: function(e) {
            const now = Date.now();
            
            for (let touch of e.touches) {
                if (touch.force > 0) {
                    this.data.touchPressures.push(touch.force);
                    if (this.data.touchPressures.length > this.config.maxSamples) {
                        this.data.touchPressures.shift();
                    }
                }
            }
        },
        
        onTouchEnd: function(e) {
            const now = Date.now();
            
            if (this.pendingTouch) {
                const duration = now - this.pendingTouch.startTime;
                const touch = e.changedTouches[0];
                const dx = touch.clientX - this.pendingTouch.startX;
                const dy = touch.clientY - this.pendingTouch.startY;
                const distance = Math.sqrt(dx * dx + dy * dy);
                
                // Detecta tipo de gesto
                let gestureType = 'tap';
                if (distance > 50) {
                    if (Math.abs(dx) > Math.abs(dy)) {
                        gestureType = dx > 0 ? 'swipe_right' : 'swipe_left';
                    } else {
                        gestureType = dy > 0 ? 'swipe_down' : 'swipe_up';
                    }
                } else if (duration > 500) {
                    gestureType = 'long_press';
                }
                
                this.data.touchGestures.push({
                    type: gestureType,
                    duration: duration,
                    distance: distance,
                    t: now
                });
            }
        },
        
        // ==========================================
        // COLETA DE DADOS - SENSORES
        // ==========================================
        onDeviceMotion: function(e) {
            if (!e.accelerationIncludingGravity) return;
            
            const motion = {
                x: e.accelerationIncludingGravity.x || 0,
                y: e.accelerationIncludingGravity.y || 0,
                z: e.accelerationIncludingGravity.z || 0,
                t: Date.now()
            };
            
            this.data.deviceMotion.push(motion);
            if (this.data.deviceMotion.length > this.config.maxSamples) {
                this.data.deviceMotion.shift();
            }
        },
        
        onDeviceOrientation: function(e) {
            const orientation = {
                alpha: e.alpha || 0, // Rotacao em Z (bussola)
                beta: e.beta || 0,   // Rotacao em X (frente/tras)
                gamma: e.gamma || 0, // Rotacao em Y (esquerda/direita)
                t: Date.now()
            };
            
            this.data.deviceOrientation.push(orientation);
            if (this.data.deviceOrientation.length > this.config.maxSamples) {
                this.data.deviceOrientation.shift();
            }
        },
        
        // ==========================================
        // COLETA DE DADOS - FOCUS/VISIBILITY
        // ==========================================
        onVisibilityChange: function() {
            this.data.visibilityChanges.push({
                visible: !document.hidden,
                t: Date.now()
            });
        },
        
        onFocusChange: function(type) {
            this.data.focusChanges.push({
                type: type,
                t: Date.now()
            });
        },
        
        // ==========================================
        // UTILIDADES
        // ==========================================
        recordInteraction: function(timestamp) {
            if (!this.data.firstInteraction) {
                this.data.firstInteraction = timestamp;
            }
            
            if (this.data.lastInteraction) {
                const gap = timestamp - this.data.lastInteraction;
                if (gap > 100 && gap < 30000) {
                    this.data.interactionGaps.push(gap);
                    if (this.data.interactionGaps.length > this.config.maxSamples) {
                        this.data.interactionGaps.shift();
                    }
                }
            }
            
            this.data.lastInteraction = timestamp;
        },
        
        calculateStats: function(arr) {
            if (!arr || arr.length === 0) {
                return { mean: 0, std: 0, min: 0, max: 0, variance: 0 };
            }
            
            const n = arr.length;
            const mean = arr.reduce((a, b) => a + b, 0) / n;
            const variance = arr.reduce((a, b) => a + Math.pow(b - mean, 2), 0) / n;
            const std = Math.sqrt(variance);
            const min = Math.min(...arr);
            const max = Math.max(...arr);
            
            return { mean, std, min, max, variance, count: n };
        },
        
        // ==========================================
        // ANALISE COMPORTAMENTAL
        // ==========================================
        analyze: function() {
            const scores = {};
            const flags = [];
            
            // 1. Analise de movimento do mouse
            scores.mouse = this.analyzeMouseBehavior();
            
            // 2. Analise de teclado
            scores.keyboard = this.analyzeKeyboardBehavior();
            
            // 3. Analise de scroll
            scores.scroll = this.analyzeScrollBehavior();
            
            // 4. Analise de touch
            scores.touch = this.analyzeTouchBehavior();
            
            // 5. Analise de sensores
            scores.sensors = this.analyzeSensorBehavior();
            
            // 6. Analise de timing
            scores.timing = this.analyzeTimingBehavior();
            
            // 7. Analise de consistencia
            scores.consistency = this.analyzeConsistency();
            
            // Coleta flags
            Object.keys(scores).forEach(key => {
                if (scores[key].flags) {
                    flags.push(...scores[key].flags);
                }
            });
            
            // Calcula probabilidade de ser humano
            const validScores = Object.values(scores)
                .map(s => s.score)
                .filter(s => s !== null && s !== undefined);
            
            const humanProbability = validScores.length > 0
                ? Math.round(validScores.reduce((a, b) => a + b, 0) / validScores.length)
                : 50;
            
            this.data.scores = scores;
            this.data.flags = flags;
            this.data.humanProbability = humanProbability;
            
            if (this.config.debug) {
                console.log('[Biometrics] Analysis:', { scores, flags, humanProbability });
            }
            
            return { scores, flags, humanProbability };
        },
        
        analyzeMouseBehavior: function() {
            const result = { score: null, flags: [], details: {} };
            
            // Precisa de dados suficientes
            if (this.data.mousePositions.length < this.config.minSamples) {
                return result;
            }
            
            // Estatisticas de velocidade
            const velStats = this.calculateStats(this.data.mouseVelocities);
            result.details.velocity = velStats;
            
            // Estatisticas de aceleracao
            const accStats = this.calculateStats(this.data.mouseAccelerations);
            result.details.acceleration = accStats;
            
            // Estatisticas de curvatura
            const curveStats = this.calculateStats(this.data.mouseCurvatures);
            result.details.curvature = curveStats;
            
            let score = 100;
            
            // TESTE 1: Variacao de velocidade
            // Humanos tem variacao natural, bots sao muito constantes
            if (velStats.std < 50) {
                score -= 20;
                result.flags.push('mouse_velocity_too_constant');
            }
            
            // TESTE 2: Movimentos em linha reta
            // Humanos fazem curvas, bots vao em linha reta
            if (curveStats.mean < 5) {
                score -= 25;
                result.flags.push('mouse_too_straight');
            }
            
            // TESTE 3: Velocidade inumana
            // Velocidade media muito alta = bot
            if (velStats.mean > 5000) {
                score -= 30;
                result.flags.push('mouse_velocity_inhuman');
            }
            
            // TESTE 4: Sem aceleracao/desaceleracao natural
            // Humanos aceleram e desaceleram, bots mantem velocidade constante
            if (accStats.std < 100) {
                score -= 15;
                result.flags.push('mouse_no_natural_acceleration');
            }
            
            // TESTE 5: Padroes repetitivos
            // Verifica se angulos se repetem demais
            const angleStats = this.calculateStats(this.data.mouseAngles);
            if (angleStats.std < 10) {
                score -= 15;
                result.flags.push('mouse_repetitive_pattern');
            }
            
            // BONUS: Hesitacao antes de cliques (muito humano)
            if (this.data.hesitations.length > 0) {
                const hesStats = this.calculateStats(this.data.hesitations);
                if (hesStats.mean > 200 && hesStats.mean < 1000) {
                    score += 10; // Bonus por comportamento humano
                }
            }
            
            result.score = Math.max(0, Math.min(100, score));
            return result;
        },
        
        analyzeKeyboardBehavior: function() {
            const result = { score: null, flags: [], details: {} };
            
            if (this.data.keyPresses.length < 5) {
                return result;
            }
            
            // Estatisticas de intervalo entre teclas
            const intervalStats = this.calculateStats(this.data.keyIntervals);
            result.details.intervals = intervalStats;
            
            // Estatisticas de tempo segurando tecla
            const holdStats = this.calculateStats(this.data.keyHoldTimes);
            result.details.holdTimes = holdStats;
            
            let score = 100;
            
            // TESTE 1: Intervalo entre teclas muito constante
            // Humanos tem ritmo variavel, bots sao mecanicos
            if (intervalStats.std < 20) {
                score -= 30;
                result.flags.push('keyboard_rhythm_too_constant');
            }
            
            // TESTE 2: Velocidade de digitacao inumana
            // < 50ms entre teclas = muito rapido para humano
            if (intervalStats.mean < 50) {
                score -= 35;
                result.flags.push('keyboard_speed_inhuman');
            }
            
            // TESTE 3: Tempo de hold muito constante
            // Humanos variam o tempo que seguram cada tecla
            if (holdStats.std < 10) {
                score -= 20;
                result.flags.push('keyboard_hold_too_constant');
            }
            
            // TESTE 4: Tempo de hold muito curto
            // Humanos seguram tecla por pelo menos 50-100ms
            if (holdStats.mean < 30) {
                score -= 25;
                result.flags.push('keyboard_hold_too_short');
            }
            
            result.score = Math.max(0, Math.min(100, score));
            return result;
        },
        
        analyzeScrollBehavior: function() {
            const result = { score: null, flags: [], details: {} };
            
            if (this.data.scrollEvents.length < 5) {
                return result;
            }
            
            // Estatisticas de velocidade de scroll
            const velStats = this.calculateStats(this.data.scrollVelocities);
            result.details.velocity = velStats;
            
            let score = 100;
            
            // TESTE 1: Scroll muito constante
            // Humanos variam a velocidade, bots scrollam uniformemente
            if (velStats.std < 100) {
                score -= 20;
                result.flags.push('scroll_too_constant');
            }
            
            // TESTE 2: Velocidade inumana
            if (velStats.mean > 10000) {
                score -= 30;
                result.flags.push('scroll_speed_inhuman');
            }
            
            // TESTE 3: Scroll instantaneo (sem momentum)
            // Em mobile, scroll tem momentum natural
            if (this.data.scrollPatterns.length > 0) {
                const deltas = this.data.scrollPatterns.map(p => Math.abs(p.deltaY));
                const deltaStats = this.calculateStats(deltas);
                if (deltaStats.std < 5) {
                    score -= 15;
                    result.flags.push('scroll_no_momentum');
                }
            }
            
            result.score = Math.max(0, Math.min(100, score));
            return result;
        },
        
        analyzeTouchBehavior: function() {
            const result = { score: null, flags: [], details: {} };
            
            if (this.data.touchEvents.length < 3) {
                return result;
            }
            
            let score = 100;
            
            // TESTE 1: Pressao do toque
            // Humanos variam a pressao, bots tem pressao constante (ou zero)
            if (this.data.touchPressures.length > 0) {
                const pressureStats = this.calculateStats(this.data.touchPressures);
                result.details.pressure = pressureStats;
                
                if (pressureStats.std < 0.05) {
                    score -= 20;
                    result.flags.push('touch_pressure_constant');
                }
                
                // Pressao sempre 0 ou sempre 1 = suspeito
                if (pressureStats.mean === 0 || pressureStats.mean === 1) {
                    score -= 15;
                    result.flags.push('touch_pressure_artificial');
                }
            }
            
            // TESTE 2: Area de toque
            // Dedos humanos tem area variavel
            const radii = this.data.touchEvents
                .filter(t => t.radiusX > 0)
                .map(t => t.radiusX);
            
            if (radii.length > 0) {
                const radiusStats = this.calculateStats(radii);
                result.details.radius = radiusStats;
                
                if (radiusStats.std < 1) {
                    score -= 15;
                    result.flags.push('touch_radius_constant');
                }
            }
            
            // TESTE 3: Gestos naturais
            // Humanos fazem gestos variados
            if (this.data.touchGestures.length > 0) {
                const gestureTypes = [...new Set(this.data.touchGestures.map(g => g.type))];
                result.details.gestureVariety = gestureTypes.length;
                
                // Apenas taps = pode ser bot
                if (gestureTypes.length === 1 && gestureTypes[0] === 'tap') {
                    score -= 10;
                    result.flags.push('touch_only_taps');
                }
            }
            
            result.score = Math.max(0, Math.min(100, score));
            return result;
        },
        
        analyzeSensorBehavior: function() {
            const result = { score: null, flags: [], details: {} };
            
            // Analisa acelerometro
            if (this.data.deviceMotion.length > 10) {
                const xValues = this.data.deviceMotion.map(m => m.x);
                const yValues = this.data.deviceMotion.map(m => m.y);
                const zValues = this.data.deviceMotion.map(m => m.z);
                
                const xStats = this.calculateStats(xValues);
                const yStats = this.calculateStats(yValues);
                const zStats = this.calculateStats(zValues);
                
                result.details.motion = { x: xStats, y: yStats, z: zStats };
                
                let score = 100;
                
                // Sem variacao = dispositivo parado ou emulado
                if (xStats.std < 0.1 && yStats.std < 0.1 && zStats.std < 0.1) {
                    score -= 25;
                    result.flags.push('sensor_motion_static');
                }
                
                // Valores sempre zero = emulador
                if (xStats.mean === 0 && yStats.mean === 0 && zStats.mean === 0) {
                    score -= 40;
                    result.flags.push('sensor_motion_zero');
                }
                
                result.score = Math.max(0, Math.min(100, score));
            }
            
            // Analisa giroscopio
            if (this.data.deviceOrientation.length > 10) {
                const alphaValues = this.data.deviceOrientation.map(o => o.alpha);
                const betaValues = this.data.deviceOrientation.map(o => o.beta);
                const gammaValues = this.data.deviceOrientation.map(o => o.gamma);
                
                const alphaStats = this.calculateStats(alphaValues);
                const betaStats = this.calculateStats(betaValues);
                const gammaStats = this.calculateStats(gammaValues);
                
                result.details.orientation = { alpha: alphaStats, beta: betaStats, gamma: gammaStats };
                
                if (result.score === null) result.score = 100;
                
                // Sem variacao de orientacao = emulador ou desktop
                if (alphaStats.std < 0.5 && betaStats.std < 0.5 && gammaStats.std < 0.5) {
                    result.score -= 20;
                    result.flags.push('sensor_orientation_static');
                }
            }
            
            return result;
        },
        
        analyzeTimingBehavior: function() {
            const result = { score: null, flags: [], details: {} };
            
            if (this.data.interactionGaps.length < 5) {
                return result;
            }
            
            const gapStats = this.calculateStats(this.data.interactionGaps);
            result.details.gaps = gapStats;
            
            let score = 100;
            
            // TESTE 1: Gaps muito regulares
            // Humanos tem pausas irregulares
            if (gapStats.std < 100) {
                score -= 25;
                result.flags.push('timing_too_regular');
            }
            
            // TESTE 2: Interacoes muito rapidas sem pausa
            // Humanos pausam para pensar/ler
            if (gapStats.mean < 100) {
                score -= 20;
                result.flags.push('timing_no_pauses');
            }
            
            // TESTE 3: Tempo desde primeira interacao
            const totalTime = Date.now() - this.startTime;
            result.details.totalTime = totalTime;
            
            // Se tem muita interacao em pouco tempo = suspeito
            const interactionCount = this.data.mousePositions.length + 
                                    this.data.keyPresses.length + 
                                    this.data.touchEvents.length;
            
            if (totalTime < 5000 && interactionCount > 100) {
                score -= 20;
                result.flags.push('timing_too_fast');
            }
            
            result.score = Math.max(0, Math.min(100, score));
            return result;
        },
        
        analyzeConsistency: function() {
            const result = { score: 100, flags: [], details: {} };
            
            // Verifica se ha dados de mouse OU touch (nao ambos em grande quantidade)
            const hasMouseData = this.data.mousePositions.length > 20;
            const hasTouchData = this.data.touchEvents.length > 10;
            
            result.details.hasMouseData = hasMouseData;
            result.details.hasTouchData = hasTouchData;
            
            // Desktop com muitos touch events = suspeito (emulador)
            if (hasMouseData && hasTouchData) {
                // Pode ser touchscreen laptop, mas vale verificar
                if (this.data.mousePositions.length > 50 && this.data.touchEvents.length > 50) {
                    result.score -= 10;
                    result.flags.push('mixed_input_devices');
                }
            }
            
            // Nenhuma interacao = muito suspeito
            if (!hasMouseData && !hasTouchData && this.data.keyPresses.length === 0) {
                const totalTime = Date.now() - this.startTime;
                if (totalTime > 5000) {
                    result.score -= 40;
                    result.flags.push('no_interaction_detected');
                }
            }
            
            return result;
        },
        
        // ==========================================
        // API PUBLICA
        // ==========================================
        getResults: function() {
            this.analyze();
            
            return {
                humanProbability: this.data.humanProbability,
                scores: this.data.scores,
                flags: this.data.flags,
                summary: {
                    mouseMovements: this.data.mousePositions.length,
                    keyPresses: this.data.keyPresses.length,
                    scrollEvents: this.data.scrollEvents.length,
                    touchEvents: this.data.touchEvents.length,
                    sensorReadings: this.data.deviceMotion.length + this.data.deviceOrientation.length,
                    totalTime: Date.now() - this.startTime
                }
            };
        },
        
        getHumanScore: function() {
            this.analyze();
            return this.data.humanProbability;
        },
        
        getFlags: function() {
            return this.data.flags;
        },
        
        isLikelyHuman: function(threshold = 60) {
            return this.getHumanScore() >= threshold;
        },
        
        isLikelyBot: function(threshold = 40) {
            return this.getHumanScore() < threshold;
        }
    };
    
    // Auto-inicializa quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.BehavioralBiometrics.init();
        });
    } else {
        window.BehavioralBiometrics.init();
    }
    
})();
