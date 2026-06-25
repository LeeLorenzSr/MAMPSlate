(function () {
    'use strict';

    var textarea = document.getElementById('body-markdown');
    if (!textarea) {
        return;
    }

    var previewBtn = document.getElementById('preview-btn');
    var editBtn = document.getElementById('edit-btn');
    var output = document.getElementById('preview-output');
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');
    var token = tokenMeta ? tokenMeta.getAttribute('content') : '';

    function getPreview() {
        previewBtn.disabled = true;
        var endpoint = previewBtn.getAttribute('data-preview-url') || '/admin/article-preview';
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ markdown: textarea.value, csrf_token: token })
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    output.innerHTML = data.html;
                    output.hidden = false;
                    textarea.hidden = true;
                    previewBtn.hidden = true;
                    editBtn.hidden = false;
                }
            })
            .catch(function () {})
            .finally(function () { previewBtn.disabled = false; });
    }

    if (previewBtn) {
        previewBtn.addEventListener('click', getPreview);
    }
    if (editBtn) {
        editBtn.addEventListener('click', function () {
            output.hidden = true;
            textarea.hidden = false;
            previewBtn.hidden = false;
            editBtn.hidden = true;
        });
    }

    document.querySelectorAll('.media-insert').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var md = btn.getAttribute('data-markdown') || '';
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            textarea.value = textarea.value.slice(0, start) + md + textarea.value.slice(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + md.length;
        });
    });
})();
