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

// Hamburger / vertical dropdown menu.
(function () {
    'use strict';

    var toggle = document.getElementById('menu-toggle');
    var menu = document.getElementById('site-menu');
    if (!toggle || !menu) {
        return;
    }
    var icon = toggle.querySelector('i.bi');

    function setIcon(name) {
        if (icon) { icon.className = 'bi ' + name; }
    }
    function open() {
        menu.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close menu');
        setIcon('bi-x-lg');
    }
    function close() {
        menu.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open menu');
        setIcon('bi-list');
    }

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        if (menu.hidden) { open(); } else { close(); }
    });

    // Close on outside click.
    document.addEventListener('click', function (e) {
        if (menu.hidden) { return; }
        if (!menu.contains(e.target) && !toggle.contains(e.target)) {
            close();
        }
    });

    // Close on Escape.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !menu.hidden) {
            close();
            toggle.focus();
        }
    });

    // Close after following a link.
    menu.addEventListener('click', function (e) {
        if (e.target.closest('a')) { close(); }
    });
})();
