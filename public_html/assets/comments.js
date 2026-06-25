(function () {
    'use strict';

    var form = document.querySelector('.comment-form');
    if (!form) {
        return;
    }

    var parentIdInput = document.getElementById('parent-id');
    var context = document.getElementById('reply-context');

    document.querySelectorAll('.comment-reply').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var parentId = btn.getAttribute('data-parent');
            var name = btn.getAttribute('data-name');
            if (parentIdInput) {
                parentIdInput.value = parentId;
            }
            if (context) {
                context.innerHTML = 'Replying to <strong>' + (name || '') + '</strong>. ';
                var cancel = document.createElement('button');
                cancel.type = 'button';
                cancel.className = 'link-button';
                cancel.textContent = 'Cancel reply';
                cancel.addEventListener('click', function () {
                    parentIdInput.value = '';
                    context.hidden = true;
                    context.innerHTML = '';
                });
                context.appendChild(cancel);
                context.hidden = false;
            }
            var textarea = form.querySelector('textarea');
            if (textarea) {
                textarea.focus();
            }
        });
    });
})();
