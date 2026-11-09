(function () {
    'use strict';
    var overlay = document.getElementById('guest-terminal');
    if (!overlay) return;
    var win = overlay.querySelector('.terminal-window');
    var output = overlay.querySelector('.terminal-output');
    var form = overlay.querySelector('form');
    var input = form.querySelector('input');
    var prompt = form.querySelector('label');
    var status = overlay.querySelector('.terminal-status');
    var keys = overlay.querySelector('.terminal-keys');
    var closeButton = overlay.querySelector('.terminal-close');
    var standalone = overlay.dataset.standalone === 'yes';
    var mode = 'shell', busy = false, initialized = false, lines = [], history = [], cursor = 0, draft = '', previousFocus;

    function wrap(text) {
        var result = [];
        String(text).replace(/\r/g, '').replace(/[\x00-\x08\x0b-\x1f\x7f]/g, '').split('\n').forEach(function (line) {
            var row = '', width = 0;
            Array.from(line).forEach(function (ch) {
                var code = ch.codePointAt(0);
                var size = code >= 0x1100 && (code <= 0x115f || code >= 0x2e80) ? 2 : 1;
                if (width + size > 80) { result.push(row); row = ''; width = 0; }
                row += ch; width += size;
            });
            result.push(row);
        });
        return result;
    }
    function render() {
        // 23 output rows plus one input row: a fixed 80 x 24 character viewport.
        var visible = lines.slice(-23);
        while (visible.length < 23) visible.push('');
        output.textContent = visible.join('\n');
    }
    function append(text) { lines = lines.concat(wrap(text)).slice(-500); render(); }
    function close() {
        if (standalone) { window.location.assign('/'); return; }
        overlay.hidden = true;
        win.removeAttribute('aria-modal');
        document.querySelector('.page').inert = false;
        document.body.classList.remove('terminal-is-open');
        if (previousFocus) previousFocus.focus();
    }
    async function send(command, key, initial) {
        if (busy) return;
        busy = true; input.disabled = true;
        status.textContent = '연결 중…';
        if (!initial && mode === 'shell' && !key) append(prompt.textContent + ' ' + command);
        try {
            var body = new URLSearchParams({csrf: overlay.dataset.csrf, command: command || '', key: key || ''});
            var response = await fetch('/terminal-api.php', {method: 'POST', body: body, credentials: 'same-origin'});
            if (!response.ok) {
                if (response.status === 403) throw new Error('세션이 바뀌었어요. 페이지를 새로고침해 주세요.');
                if (response.status === 429) throw new Error('입력이 많아요. 1분 후 다시 시도해 주세요.');
                throw new Error('연결에 실패했어요. 다시 시도해 주세요.');
            }
            var data = await response.json();
            if (data.clear || data.screen) lines = [];
            if (data.output) append(data.output); else render();
            mode = data.mode; prompt.textContent = data.prompt;
            keys.hidden = mode !== 'tui';
            input.readOnly = mode === 'tui';
            input.setAttribute('aria-label', mode === 'tui' ? 'TUI 키 입력: 위아래, Enter, q' : '터미널 명령어');
            status.textContent = mode === 'tui' ? 'TUI 실행 중 · ↑/↓ 선택 · Enter 실행 · q 돌아가기' : 'guest · 세션 전용 홈 · help: 도움말 · ↑/↓: 기록';
            initialized = true;
            if (data.exit) close();
        } catch (error) {
            status.textContent = error.message;
            append('[connection] ' + error.message);
        } finally {
            busy = false; input.disabled = false;
            if (!overlay.hidden) input.focus();
        }
    }
    function open() {
        previousFocus = document.activeElement;
        overlay.hidden = false;
        if (!standalone) {
            document.body.classList.add('terminal-is-open');
            win.setAttribute('aria-modal', 'true');
            document.querySelector('.page').inert = true;
        }
        if (!initialized) send('', '', true); else input.focus();
    }
    document.querySelectorAll('[data-terminal-open]').forEach(function (link) {
        link.addEventListener('click', function (event) { event.preventDefault(); open(); });
    });
    closeButton.addEventListener('click', close);
    overlay.addEventListener('click', function (event) { if (event.target === overlay) close(); });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (busy) return;
        if (mode === 'tui') { send('', 'Enter'); return; }
        var command = input.value.trim();
        if (!command) return;
        history.push(command); history = history.slice(-100); cursor = history.length; draft = '';
        input.value = ''; send(command);
    });
    keys.querySelectorAll('button').forEach(function (button) {
        button.addEventListener('click', function () { send('', button.dataset.key); });
    });
    overlay.addEventListener('keydown', function (event) {
        if (event.isComposing) return;
        if (event.key === 'Tab' && !standalone) {
            var focusable = Array.from(win.querySelectorAll('button, input, [tabindex="0"]')).filter(function (el) { return !el.disabled && el.getClientRects().length; });
            var first = focusable[0], last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            return;
        }
        if (event.target !== input && event.key !== 'Escape') return;
        if (busy) return;
        if (mode === 'tui') {
            var key = event.ctrlKey && event.key.toLowerCase() === 'c' ? 'ControlC' : event.key;
            if (['ArrowUp', 'ArrowDown', 'j', 'k', 'Enter', 'q', 'Escape', 'ControlC'].indexOf(key) >= 0) {
                event.preventDefault(); send('', key);
            }
            return;
        }
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        else if (event.ctrlKey && event.key.toLowerCase() === 'c') { event.preventDefault(); append(prompt.textContent + ' ' + input.value + '^C'); input.value = ''; }
        else if (event.ctrlKey && event.key.toLowerCase() === 'l') { event.preventDefault(); lines = []; render(); }
        else if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
            event.preventDefault();
            if (cursor === history.length) draft = input.value;
            cursor = Math.max(0, Math.min(history.length, cursor + (event.key === 'ArrowUp' ? -1 : 1)));
            input.value = cursor === history.length ? draft : history[cursor];
        }
    });
    keys.hidden = true; render();
    if (standalone) open();
}());
