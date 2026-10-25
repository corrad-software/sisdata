/*
 * Portal Hartanah Wakaf Selangor — small progressive enhancements:
 *  - mobile menu toggle (aria-expanded);
 *  - focus the result / error summary after a submission;
 *  - "Simpan draf": form drafts kept ONLY in this browser (localStorage),
 *    never files or the CAPTCHA, cleared after a successful submission
 *    (SRS-PWS-07 draft before submission; SRS-PWS-20 no server-side copy).
 */
(function () {
    'use strict';

    var toggle = document.querySelector('.hws-menu-toggle');
    var nav = document.getElementById('hws-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
            nav.classList.toggle('is-open', !open);
        });
    }

    var result = document.getElementById('hws-hasil');
    if (result) {
        result.focus();
        if (result.querySelector('.hws-notice--success')) {
            try {
                Object.keys(window.localStorage).forEach(function (k) {
                    if (k.indexOf('hws-draf-') === 0) window.localStorage.removeItem(k);
                });
            } catch (e) { /* storage unavailable */ }
        }
    }

    var SKIP = /^(hws_|_hws|_wp|action$|lampiran|fail)/;
    document.querySelectorAll('form[data-hws-draf]').forEach(function (form) {
        var key = 'hws-draf-' + form.getAttribute('data-hws-draf');
        var btn = form.querySelector('[data-hws-simpan-draf]');
        var status = form.querySelector('.hws-draf-status');
        var storage;
        try { storage = window.localStorage; storage.getItem(key); } catch (e) { storage = null; }
        if (!storage || !btn) return;
        btn.hidden = false;

        var saved = storage.getItem(key);
        if (saved) {
            try {
                var data = JSON.parse(saved);
                Object.keys(data).forEach(function (name) {
                    var el = form.elements.namedItem(name);
                    if (!el || el.value) return;
                    if (el.type === 'checkbox') el.checked = !!data[name]; else el.value = data[name];
                });
                if (status) status.textContent = document.documentElement.lang === 'en' ? 'Draft restored from this device.' : 'Draf dipulihkan daripada peranti ini.';
            } catch (e) { storage.removeItem(key); }
        }

        btn.addEventListener('click', function () {
            var data = {};
            Array.prototype.forEach.call(form.elements, function (el) {
                if (!el.name || SKIP.test(el.name) || el.type === 'file' || el.type === 'hidden' || el.type === 'submit') return;
                data[el.name] = el.type === 'checkbox' ? el.checked : el.value;
            });
            storage.setItem(key, JSON.stringify(data));
            if (status) status.textContent = document.documentElement.lang === 'en' ? 'Draft saved on this device only.' : 'Draf disimpan pada peranti ini sahaja.';
        });
    });
})();
