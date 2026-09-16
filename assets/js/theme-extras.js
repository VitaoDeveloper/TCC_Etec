(function () {
    'use strict';

    // =====================================================================
    // Theme visual module: sequence-triggered decorative effects.
    //
    // Watches a short, unadvertised key sequence typed anywhere outside
    // form fields and plays a brief monochrome overlay + accent-mark
    // mosaic across the viewport. No UI hints surface the module.
    // =====================================================================

    var SEQUENCE = 'timao';
    var WINDOW_MS = 3000;       // tempo total permitido para digitar a sequência
    var OVERLAY_MS = 4000;      // duração do overlay pulsante
    var MOSAIC_COUNT = 14;      // quantidade de ícones no mosaico

    var buffer = '';
    var seqStart = 0;
    var resetTimer = null;

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

    function playOverlay() {
        document.body.classList.add('tx-fx');

        var icons = [];
        for (var i = 0; i < MOSAIC_COUNT; i++) {
            var el = document.createElement('div');
            el.className = 'tx-fx-icon';
            el.style.top = (4 + Math.random() * 84) + 'vh';
            el.style.width = (26 + Math.random() * 26) + 'px';
            el.style.animationDuration = (1.5 + Math.random() * 1.4).toFixed(2) + 's';
            el.style.animationDelay = (Math.random() * 0.9).toFixed(2) + 's';
            el.style.opacity = (0.4 + Math.random() * 0.6).toFixed(2);
            document.body.appendChild(el);
            icons.push(el);
        }

        var toast = document.createElement('div');
        toast.className = 'tx-fx-toast';
        toast.textContent = '\u26AB\u26AA Tim\u00E3o!';
        document.body.appendChild(toast);

        setTimeout(function () {
            toast.remove();
            for (var j = 0; j < icons.length; j++) {
                icons[j].remove();
            }
            document.body.classList.remove('tx-fx');
        }, OVERLAY_MS);
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
            setTimeout(playOverlay, 0);
            return;
        }

        if (resetTimer) {
            clearTimeout(resetTimer);
        }
        resetTimer = setTimeout(resetSequence, WINDOW_MS);
    });
})();