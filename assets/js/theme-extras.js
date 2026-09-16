(function () {
    'use strict';

    // =====================================================================
    // Theme visual module: sequence-triggered decorative effects.
    //
    // Watches a short, unadvertised key sequence typed anywhere outside
    // form fields and plays a brief decorative blink effect across the
    // viewport, plus a corner status bar while active. Honors
    // prefers-reduced-motion.
    // =====================================================================

    var SEQUENCE = 'timao';
    var WINDOW_MS = 3000;        // total time allowed to type the sequence
    var EFFECT_MS = 6000;        // total span of the effect
    var MAX_ON_SCREEN = 6;       // concurrent shields cap
    var MIN_INTERVAL_MS = 150;   // spawn interval range
    var MAX_INTERVAL_MS = 450;
    var SHIELD_MIN_W = 40;       // shield width range (px)
    var SHIELD_MAX_W = 110;
    var EDGE_MARGIN = 12;        // keep shields clear of viewport borders
    var ASPECT_RATIO = 1.3;      // approx h/w of the accent-mark asset
    var MAX_ANIM_MS = 2600;      // longest shield animation, used for timing
    var RUNS_KEY = 'tx_fx_runs'; // activation counter storage key

    var buffer = '';
    var seqStart = 0;
    var resetTimer = null;

    var basePath = document.body.getAttribute('data-base-path') || '';

    // Preload the asset early so the first blink never renders empty.
    var preloader = new Image();
    preloader.src = basePath + 'assets/img/theme/accent-mark.png';

    var state = {
        active: false,
        calm: false,
        shields: [],
        fillEl: null,
        statusEl: null,
        spawnTimer: null,
        endTimer: null,
        endAt: 0,
        runs: 0
    };

    // =====================================================================
    // Storage helpers (fallback to in-memory counter when unavailable)
    // =====================================================================

    function loadRuns() {
        try {
            var n = parseInt(localStorage.getItem(RUNS_KEY) || '0', 10);
            return (isFinite(n) && n > 0) ? n : 0;
        } catch (e) {
            return 0;
        }
    }

    function saveRuns() {
        try {
            localStorage.setItem(RUNS_KEY, String(state.runs));
        } catch (e) {
            // keep in-memory count only
        }
    }

    // =====================================================================
    // Environment helpers
    // =====================================================================

    function isTypingTarget(el) {
        return el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable);
    }

    function reducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
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
    // Status bar
    // =====================================================================

    function barLabel() {
        return '⚫⚪ MODO TIMÃO ATIVO';
    }

    function buildBar(effectMs, label) {
        var bar = document.createElement('div');
        bar.className = 'tx-fx-status';

        var text = document.createElement('div');
        text.className = 'tx-fx-status-text';
        text.textContent = label;
        bar.appendChild(text);

        var track = document.createElement('div');
        track.className = 'tx-fx-status-track';
        var fill = document.createElement('div');
        fill.className = 'tx-fx-status-fill';
        fill.style.animationDuration = effectMs + 'ms';
        track.appendChild(fill);
        bar.appendChild(track);

        state.fillEl = fill;
        state.statusEl = bar;
        document.body.appendChild(bar);
        return bar;
    }

    function removeBar() {
        var el = state.statusEl;
        if (!el) {
            return;
        }
        state.statusEl = null;
        el.style.transition = 'opacity 0.25s ease';
        el.style.opacity = '0';
        setTimeout(function () {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }, 260);
    }

    // =====================================================================
    // Shield effect
    // =====================================================================

    function spawnShield(calm) {
        var w = SHIELD_MIN_W + Math.random() * (SHIELD_MAX_W - SHIELD_MIN_W);
        var h = w * ASPECT_RATIO;
        var maxX = Math.max(EDGE_MARGIN, window.innerWidth - EDGE_MARGIN - w);
        var maxY = Math.max(EDGE_MARGIN, window.innerHeight - EDGE_MARGIN - h);

        var el = document.createElement('img');
        el.className = 'tx-fx-shield' + (calm ? ' tx-fx-shield--calm' : '');
        el.src = basePath + 'assets/img/theme/accent-mark.png';
        el.alt = '';
        el.style.width = w.toFixed(0) + 'px';
        el.style.left = (EDGE_MARGIN + Math.random() * (maxX - EDGE_MARGIN)).toFixed(0) + 'px';
        el.style.top = (EDGE_MARGIN + Math.random() * (maxY - EDGE_MARGIN)).toFixed(0) + 'px';
        el.style.transform = 'rotate(' + ((Math.random() * 30) - 15).toFixed(1) + 'deg)';
        if (!calm) {
            el.style.animationDuration = (1.1 + Math.random() * 1.1).toFixed(2) + 's';
        }
        document.body.appendChild(el);
        return el;
    }

    function pruneShields() {
        state.shields = state.shields.filter(function (el) {
            return el.parentNode;
        });
    }

    function spawn() {
        pruneShields();
        if (state.shields.length >= MAX_ON_SCREEN) {
            return;
        }
        if (Date.now() + MAX_ANIM_MS > state.endAt) {
            return;
        }
        state.shields.push(spawnShield(state.calm));
    }

    function tick() {
        if (!state.active) {
            return;
        }
        spawn();
        if (Date.now() + MAX_ANIM_MS > state.endAt) {
            state.spawnTimer = null;
            return;
        }
        state.spawnTimer = setTimeout(tick, MIN_INTERVAL_MS + Math.random() * (MAX_INTERVAL_MS - MIN_INTERVAL_MS));
    }

    function startCalmBurst() {
        var count = 0;
        function one() {
            if (!state.active || count >= 4) {
                return;
            }
            spawn();
            count++;
            setTimeout(one, 700);
        }
        one();
    }

    function finishEffect() {
        if (state.spawnTimer) {
            clearTimeout(state.spawnTimer);
            state.spawnTimer = null;
        }
        if (state.endTimer) {
            clearTimeout(state.endTimer);
            state.endTimer = null;
        }
        for (var i = 0; i < state.shields.length; i++) {
            var el = state.shields[i];
            if (el && el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }
        state.shields = [];
        removeBar();
        state.active = false;
    }

    function startEffect() {
        if (state.active) {
            finishEffect();
        }
        state.active = true;
        state.calm = reducedMotion();
        state.endAt = Date.now() + EFFECT_MS;

        state.runs += 1;
        saveRuns();

        buildBar(EFFECT_MS, barLabel() + ' · ativação #' + state.runs);

        if (state.calm) {
            setTimeout(startCalmBurst, 0);
        } else {
            setTimeout(tick, 0);
        }

        state.endTimer = setTimeout(finishEffect, EFFECT_MS + MAX_ANIM_MS + 300);
    }

    // =====================================================================
    // Listeners
    // =====================================================================

    document.addEventListener('keydown', function (e) {
        if (e.metaKey || e.ctrlKey || e.altKey) {
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
            setTimeout(startEffect, 0);
            return;
        }

        if (resetTimer) {
            clearTimeout(resetTimer);
        }
        resetTimer = setTimeout(resetSequence, WINDOW_MS);
    });

    // =====================================================================
    // Init
    // =====================================================================

    state.runs = loadRuns();
})();