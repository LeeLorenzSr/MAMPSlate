(function () {
    'use strict';

    var modal = document.getElementById('auth-modal');
    if (!modal) {
        return;
    }

    var openBtns = document.querySelectorAll('.auth-trigger');
    var closeBtn = document.getElementById('auth-close');
    var form = document.getElementById('auth-form');
    var submitBtn = document.getElementById('auth-submit');
    var errorBox = document.getElementById('auth-error');
    var tabs = modal.querySelectorAll('.modal-tab');
    var panes = modal.querySelectorAll('.auth-pane');

    var activeTab = 'login';
    var authErrorMessages = {
        state: 'Sign-in session expired. Please try again.',
        oauth_failed: 'We could not complete sign-in with that provider. Please try again.',
        provider_denied: 'You cancelled the provider sign-in.',
        no_verified_email: 'No verified email was returned by the provider.',
        inactive: 'That account is inactive.'
    };

    function getToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function showModal(tab) {
        if (tab) {
            switchTab(tab);
        }
        clearError();
        modal.hidden = false;
        document.body.classList.add('modal-open');
    }

    function hideModal() {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
    }

    function switchTab(tab) {
        // Fall back to login if the requested tab is not present (e.g. signup disabled).
        var hasTab = Array.prototype.some.call(tabs, function (t) {
            return t.dataset.tab === tab;
        });
        if (!hasTab) {
            tab = 'login';
        }
        activeTab = tab;
        tabs.forEach(function (t) {
            t.classList.toggle('is-active', t.dataset.tab === tab);
        });
        panes.forEach(function (p) {
            var isActive = p.dataset.pane === tab;
            p.hidden = !isActive;
            // Disable inputs in inactive panes so they are not submitted.
            p.querySelectorAll('input').forEach(function (input) {
                input.disabled = !isActive;
            });
        });
        submitBtn.textContent = tab === 'signup' ? 'Create account' : 'Log in';
        clearError();
    }

    function showError(message) {
        errorBox.textContent = message;
        errorBox.hidden = false;
    }

    function clearError() {
        errorBox.textContent = '';
        errorBox.hidden = true;
    }

    openBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            showModal('login');
        });
    });
    if (closeBtn) {
        closeBtn.addEventListener('click', hideModal);
    }
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            hideModal();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) {
            hideModal();
        }
    });

    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            switchTab(t.dataset.tab);
        });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearError();

        var payload = {
            csrf_token: getToken()
        };
        new FormData(form).forEach(function (value, key) {
            payload[key] = value;
        });

        var endpoint = activeTab === 'signup' ? '/auth/signup' : '/auth/login';
        submitBtn.disabled = true;

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { status: res.status, data: data };
                });
            })
            .then(function (result) {
                if (result.data && result.data.ok) {
                    window.location.href = result.data.redirect || '/';
                    return;
                }
                showError((result.data && result.data.message) || 'Something went wrong. Please try again.');
            })
            .catch(function () {
                showError('Network error. Please try again.');
            })
            .finally(function () {
                submitBtn.disabled = false;
            });
    });

    // Auto-open the modal based on query string (e.g. after an OAuth redirect).
    var params = new URLSearchParams(window.location.search);
    var authError = params.get('auth_error');
    if (authError) {
        showModal('login');
        showError(authErrorMessages[authError] || 'Sign-in could not be completed.');
    } else if (params.get('auth') === 'signup') {
        showModal('signup');
    } else if (params.get('auth') === 'login') {
        showModal('login');
    }
})();
