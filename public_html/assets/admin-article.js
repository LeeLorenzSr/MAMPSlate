(function () {
    'use strict';

    var textarea = document.getElementById('body-markdown');
    if (!textarea) {
        return;
    }

    var editor = textarea.closest('.wysiwyg');
    var previewUrl = editor ? (editor.getAttribute('data-preview-url') || '/admin/article-preview') : '/admin/article-preview';
    var previewToggle = document.getElementById('preview-toggle');
    var previewPane = document.getElementById('preview-output');
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');
    var token = tokenMeta ? tokenMeta.getAttribute('content') : '';

    // ---- Selection helpers ----
    function wrap(before, after, placeholder) {
        after = after || before;
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        var selected = value.slice(start, end) || (placeholder || '');
        var replacement = before + selected + after;
        textarea.value = value.slice(0, start) + replacement + value.slice(end);
        textarea.focus();
        var pos = start + before.length;
        textarea.selectionStart = pos;
        textarea.selectionEnd = pos + selected.length;
        schedulePreview();
    }

    function prefixLine(prefix) {
        var start = textarea.selectionStart;
        var value = textarea.value;
        var lineStart = value.lastIndexOf('\n', start - 1) + 1;
        textarea.value = value.slice(0, lineStart) + prefix + value.slice(lineStart);
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = start + prefix.length;
        schedulePreview();
    }

    function insertBlock(text) {
        var start = textarea.selectionStart;
        var value = textarea.value;
        var needsNewline = (start > 0 && value[start - 1] !== '\n');
        var insert = (needsNewline ? '\n' : '') + text + '\n';
        textarea.value = value.slice(0, start) + insert + value.slice(start);
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = start + insert.length;
        schedulePreview();
    }

    function insertLink(isImage) {
        var url = window.prompt(isImage ? 'Image URL' : 'Link URL', 'https://');
        if (url === null) { return; }
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var selected = textarea.value.slice(start, end) || (isImage ? 'alt text' : 'link text');
        var md = isImage ? '![' + selected + '](' + url + ')' : '[' + selected + '](' + url + ')';
        textarea.value = textarea.value.slice(0, start) + md + textarea.value.slice(end);
        textarea.focus();
        textarea.selectionStart = start;
        textarea.selectionEnd = start + md.length;
        schedulePreview();
    }

    // ---- Toolbar ----
    var toolbar = editor ? editor.querySelectorAll('.editor-toolbar [data-md]') : [];
    Array.prototype.forEach.call(toolbar, function (btn) {
        btn.addEventListener('click', function () {
            switch (btn.getAttribute('data-md')) {
                case 'bold':   wrap('**', '**', 'bold text'); break;
                case 'italic': wrap('*', '*', 'italic text'); break;
                case 'code':   wrap('`', '`', 'code'); break;
                case 'h1':     prefixLine('# '); break;
                case 'h2':     prefixLine('## '); break;
                case 'quote':  prefixLine('> '); break;
                case 'ul':     prefixLine('- '); break;
                case 'ol':     prefixLine('1. '); break;
                case 'link':   insertLink(false); break;
                case 'image':  insertLink(true); break;
                case 'hr':     insertBlock('---'); break;
            }
        });
    });

    // Keyboard shortcuts: Ctrl+B, Ctrl+I, Ctrl+K.
    textarea.addEventListener('keydown', function (e) {
        if (!(e.ctrlKey || e.metaKey)) { return; }
        var k = (e.key || '').toLowerCase();
        if (k === 'b') { e.preventDefault(); wrap('**', '**', 'bold text'); }
        else if (k === 'i') { e.preventDefault(); wrap('*', '*', 'italic text'); }
        else if (k === 'k') { e.preventDefault(); insertLink(false); }
    });

    // ---- Live preview ----
    var previewTimer = null;
    var previewActive = false;

    function fetchPreview() {
        if (!previewPane) { return; }
        fetch(previewUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ markdown: textarea.value, csrf_token: token })
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.ok) { previewPane.innerHTML = data.html; }
            })
            .catch(function () {});
    }

    function schedulePreview() {
        if (!previewActive) { return; }
        clearTimeout(previewTimer);
        previewTimer = setTimeout(fetchPreview, 350);
    }

    if (previewToggle && previewPane && editor) {
        previewToggle.addEventListener('click', function () {
            previewActive = !previewActive;
            if (previewActive) {
                editor.classList.add('preview-on');
                previewPane.hidden = false;
                previewToggle.innerHTML = '<i class="bi bi-pencil"></i> Edit';
                fetchPreview();
            } else {
                editor.classList.remove('preview-on');
                previewPane.hidden = true;
                previewToggle.innerHTML = '<i class="bi bi-eye"></i> Preview';
            }
        });
        textarea.addEventListener('input', schedulePreview);
    }

    // ---- Media library insert buttons ----
    document.querySelectorAll('.media-insert').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var md = btn.getAttribute('data-markdown') || '';
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            textarea.value = textarea.value.slice(0, start) + md + textarea.value.slice(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + md.length;
            schedulePreview();
        });
    });
})();
