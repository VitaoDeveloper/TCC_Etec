(function () {
    'use strict';

    // =====================================================================
    // Theme visual module: sequence-triggered decorative effects.
    //
    // Watches a short, unadvertised key sequence typed anywhere outside
    // form fields and plays a brief decorative blink effect across the
    // viewport. Honors prefers-reduced-motion and pauses when the tab is
    // hidden. Combines with the "Modo Realeza" royalty rain when it is
    // active. Also fires once on a fixed calendar date.
    // =====================================================================

    var SEQUENCE = 'timao';
    var WINDOW_MS = 3000;              // total time allowed to type the sequence
    var BASE_EFFECT_MS = 6000;         // normal effect span
    var COMBO_EFFECT_MS = 12000;       // combined-mode effect span
    var MAX_ON_SCREEN = 6;             // concurrent shields cap (normal)
    var COMBO_MAX_ON_SCREEN = 10;      // concurrent shields cap (combined mode)
    var MIN_INTERVAL_MS = 150;         // spawn interval range (normal)
    var MAX_INTERVAL_MS = 450;
    var COMBO_MIN_INTERVAL_MS = 90;    // spawn interval range (combined mode)
    var COMBO_MAX_INTERVAL_MS = 260;
    var SHIELD_MIN_W = 40;             // shield width range (px)
    var SHIELD_MAX_W = 110;
    var EDGE_MARGIN = 12;              // keep shields clear of viewport borders
    var ASPECT_RATIO = 1.3;            // approx h/w of the accent-mark asset
    var MAX_ANIM_MS_NORMAL = 2600;     // longest shield animation (blink)
    var MAX_ANIM_MS_CALM = 4600;       // longest shield animation (reduced motion)
    var CALM_SHIELD_COUNT = 4;         // shields spawned in reduced-motion mode
    var CALM_SPACING_MS = 700;
    var DATE_FLAG_MONTH = 8;           // 0-based month (September)
    var DATE_FLAG_DAY = 1;

    var buffer = '';
    var seqStart = 0;
    var resetTimer = null;

    var basePath = document.body.getAttribute('data-base-path') || '';

    var state = {
        active: false,
        combo: false,
        calm: false,
        shields: [],
        spawnTimer: null,
        endTimer: null,
        endAt: 0,
        effectMs: BASE_EFFECT_MS,
        maxOnScreen: MAX_ON_SCREEN,
        minInterval: MIN_INTERVAL_MS,
        maxInterval: MAX_INTERVAL_MS
    };

    // =====================================================================
    // Environment helpers
    // =====================================================================

    function reducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function tabHidden() {
        return document.hidden === true;
    }

    function isTypingTarget(el) {
        return el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable);
    }

    function royalEffectActive() {
        if (document.body.classList.contains('royal-glow')) {
            return true;
        }
        return document.querySelectorAll('.royal-crown').length > 0;
    }

    function resetSequence() {
        buffer = '';
        seqStart = 0;
        if (resetTimer) {
            clearTimeout(resetTimer);
            resetTimer = null;
        }
    }

    // =====================================================================
    // Asset preload (avoid empty first blink)
    // =====================================================================

    var preloader = new Image();
    preloader.src = basePath + 'assets/img/theme/accent-mark.png';

    // =====================================================================
    // Shield effect
    // =====================================================================

    function spawnShield() {
        var w = SHIELD_MIN_W + Math.random() * (SHIELD_MAX_W - SHIELD_MIN_W);
        var h = w * ASPECT_RATIO;
        var maxX = Math.max(EDGE_MARGIN, window.innerWidth - EDGE_MARGIN - w);
        var maxY = Math.max(EDGE_MARGIN, window.innerHeight - EDGE_MARGIN - h);

        var el = document.createElement('img');
        el.className = 'tx-fx-shield' + (state.calm ? ' tx-fx-shield--calm' : '');
        el.src = basePath + 'assets/img/theme/accent-mark.png';
        el.alt = '';
        el.style.width = w.toFixed(0) + 'px';
        el.style.left = (EDGE_MARGIN + Math.random() * (maxX - EDGE_MARGIN)).toFixed(0) + 'px';
        el.style.top = (EDGE_MARGIN + Math.random() * (maxY - EDGE_MARGIN)).toFixed(0) + 'px';
        el.style.transform = 'rotate(' + ((Math.random() * 30) - 15).toFixed(1) + 'deg)';
        if (!state.calm) {
            el.style.animationDuration = (1.1 + Math.random() * 1.1).toFixed(2) + 's';
        }
        return el;
    }

    function pruneShields() {
        state.shields = state.shields.filter(function (el) {
            return el && el.parentNode;
        });
    }

    function activeCount() {
        return state.shields.length;
    }

    function clearShields() {
        for (var i = 0; i < state.shields.length; i++) {
            var el = state.shields[i];
            if (el && el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }
        state.shields = [];
    }

    function clearTimers() {
        if (state.spawnTimer) {
            clearTimeout(state.spawnTimer);
            state.spawnTimer = null;
        }
        if (state.endTimer) {
            clearTimeout(state.endTimer);
            state.endTimer = null;
        }
    }

    function maxAnimMs() {
        return state.calm ? MAX_ANIM_MS_CALM : MAX_ANIM_MS_NORMAL;
    }

    function spawn() {
        pruneShields();
        if (tabHidden()) {
            return;
        }
        if (state.shields.length >= state.maxOnScreen) {
            return;
        }
        if (Date.now() + maxAnimMs() > state.endAt) {
            return;
        }

        var el = spawnShield();
        state.shields.push(el);
        el.addEventListener('animationend', function () {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
            pruneShields();
        }, false);
        document.body.appendChild(el);
    }

    function tick() {
        if (!state.active) {
            return;
        }
        spawn();
        if (tabHidden()) {
            return;
        }
        if (Date.now() + maxAnimMs() > state.endAt) {
            state.spawnTimer = null;
            return;
        }
        state.spawnTimer = setTimeout(tick, state.minInterval + Math.random() * (state.maxInterval - state.minInterval));
    }

    function startCalmBurst() {
        var count = 0;
        function one() {
            if (!state.active || count >= CALM_SHIELD_COUNT) {
                return;
            }
            spawn();
            count++;
            setTimeout(one, CALM_SPACING_MS);
        }
        one();
    }

    function finishEffect() {
        clearTimers();
        clearShields();
        state.active = false;
    }

    function startEffect(combo) {
        var now = Date.now();
        state.active = true;
        state.combo = combo;
        state.calm = reducedMotion();
        state.shields = [];
        state.effectMs = combo ? COMBO_EFFECT_MS : BASE_EFFECT_MS;
        state.maxOnScreen = combo ? COMBO_MAX_ON_SCREEN : MAX_ON_SCREEN;
        state.minInterval = combo ? COMBO_MIN_INTERVAL_MS : MIN_INTERVAL_MS;
        state.maxInterval = combo ? COMBO_MAX_INTERVAL_MS : MAX_INTERVAL_MS;
        state.endAt = now + state.effectMs;

        if (state.calm) {
            setTimeout(startCalmBurst, 0);
        } else {
            setTimeout(tick, 0);
        }

        state.endTimer = setTimeout(finishEffect, state.effectMs + maxAnimMs() + 300);
    }

    function renewEffect() {
        state.endAt = Date.now() + state.effectMs;

        if (state.spawnTimer) {
            clearTimeout(state.spawnTimer);
            state.spawnTimer = null;
        }
        if (state.endTimer) {
            clearTimeout(state.endTimer);
            state.endTimer = null;
        }
        if (state.calm) {
            setTimeout(startCalmBurst, 0);
        } else {
            setTimeout(tick, 0);
        }
        state.endTimer = setTimeout(finishEffect, state.effectMs + maxAnimMs() + 300);
    }

    // =====================================================================
    // Calendar flag (fires the effect once on a fixed date)
    // =====================================================================

    function todayIsFlagDate() {
        var d = new Date();
        return d.getMonth() === DATE_FLAG_MONTH && d.getDate() === DATE_FLAG_DAY;
    }

    // =====================================================================
    // Listeners
    // =====================================================================

    document.addEventListener('keydown', function (e) {
        if (e.metaKey || e.ctrlKey || e.altKey) {
            return;
        }
        if (e.key === 'Escape' || e.key === 'Esc') {
            if (state.active) {
                finishEffect();
            }
            return;
        }
        if (isTypingTarget(e.target)) {
            return;
        }

        var ch = String(e.key || '').toLowerCase();
        if (!/^[a-z]$/.test(ch)) {
            return;
        }

        if (buffer.length === 0) {
            seqStart = Date.now();
        } else if (Date.now() - seqStart > WINDOW_MS) {
            buffer = '';
            seqStart = Date.now();
        }

        buffer += ch;

        if (SEQUENCE.indexOf(buffer) !== 0) {
            resetSequence();
            return;
        }

        if (buffer === SEQUENCE) {
            resetSequence();
            if (state.active) {
                renewEffect();
            } else {
                startEffect(royalEffectActive());
            }
            return;
        }

        if (resetTimer) {
            clearTimeout(resetTimer);
        }
        resetTimer = setTimeout(resetSequence, WINDOW_MS);
    });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            if (state.active) {
                if (state.spawnTimer) {
                    clearTimeout(state.spawnTimer);
                    state.spawnTimer = null;
                }
                clearShields();
            }
        } else if (state.active) {
            if (state.calm) {
                setTimeout(startCalmBurst, 0);
            } else {
                setTimeout(tick, 0);
            }
        }
    });

    // =====================================================================
    // Init
    // =====================================================================

    if (todayIsFlagDate()) {
        startEffect(false);
    }
})();