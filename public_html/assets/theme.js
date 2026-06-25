(function () {
    'use strict';

    var toggle = document.getElementById('theme-toggle');
    if (!toggle) {
        return;
    }

    function currentTheme() {
        return document.documentElement.dataset.theme || 'light';
    }

    toggle.addEventListener('click', function () {
        var next = currentTheme() === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        try {
            localStorage.setItem('theme', next);
        } catch (e) {}
    });
})();
