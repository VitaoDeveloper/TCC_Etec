(function () {
    'use strict';

    // =====================================================================
    // Theme visual module: sequence-triggered decorative effects.
    //
    // Watches a short, unadvertised key sequence typed anywhere outside
    // form fields and plays a brief decorative blink effect across the
    // viewport. No UI hints surface the module. Honors prefers-reduced-motion.
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

    var buffer = '';
    var seqStart = 0;
    var resetTimer = null;

    var basePath = document.body.getAttribute('data-base-path') || '';

    function isTypingTarget(el) {
        return el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable);
    }

    function resetSequence() {
        buffer = '';
        seqStart = 0;
        if (resetTimer) {
            clearTimeout(resetTimer);
            resetTimer = null;
        }
    }

    function reducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

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

    function playEffect() {
        var calm = reducedMotion();
        var shields = [];
        var done = false;

        function activeCount() {
            var n = 0;
            for (var i = 0; i < shields.length; i++) {
                if (document.body.contains(shields[i])) {
                    n++;
                }
            }
            return n;
        }

        function spawn() {
            if (done || activeCount() >= MAX_ON_SCREEN) {
                return;
            }
            shields.push(spawnShield(calm));
            if (calm) {
                return;
            }
            setTimeout(function () {
                if (!done) {
                    spawn();
                }
            }, MIN_INTERVAL_MS + Math.random() * (MAX_INTERVAL_MS - MIN_INTERVAL_MS));
        }

        if (calm) {
            // reduced motion: a few shields with a slow fade, no rapid blinking
            for (var i = 0; i < 4; i++) {
                setTimeout(function () { spawn(); }, i * 700);
            }
        } else {
            spawn();
        }

        setTimeout(function () {
            done = true;
            for (var j = 0; j < shields.length; j++) {
                if (document.body.contains(shields[j])) {
                    shields[j].remove();
                }
            }
        }, EFFECT_MS + 1200);
    }

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
            setTimeout(playEffect, 0);
            return;
        }

        if (resetTimer) {
            clearTimeout(resetTimer);
        }
        resetTimer = setTimeout(resetSequence, WINDOW_MS);
    });
})();