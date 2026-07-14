/*!
 * CsMfbSwal – Bundled modal library for Merchant Feed Booster
 * SweetAlert2-compatible API · No external dependencies
 */
(function (global) {
    'use strict';

    /* ── SVG icon templates ────────────────────────────────────────────── */
    var ICONS = {
        success: '<svg class="cs-sw-svg" viewBox="0 0 56 56"><circle class="cs-sw-ring cs-sw-ring--success" cx="28" cy="28" r="26" fill="none" stroke-width="2"/><polyline class="cs-sw-mark cs-sw-mark--success" fill="none" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="15,28 23,37 41,19"/></svg>',
        error:   '<svg class="cs-sw-svg" viewBox="0 0 56 56"><circle class="cs-sw-ring cs-sw-ring--error" cx="28" cy="28" r="26" fill="none" stroke-width="2"/><line class="cs-sw-mark cs-sw-mark--error" x1="18" y1="18" x2="38" y2="38" stroke-width="3" stroke-linecap="round"/><line class="cs-sw-mark cs-sw-mark--error" x1="38" y1="18" x2="18" y2="38" stroke-width="3" stroke-linecap="round"/></svg>',
        warning: '<svg class="cs-sw-svg" viewBox="0 0 56 56"><circle class="cs-sw-ring cs-sw-ring--warning" cx="28" cy="28" r="26" fill="none" stroke-width="2"/><line class="cs-sw-mark cs-sw-mark--warning" x1="28" y1="16" x2="28" y2="32" stroke-width="3" stroke-linecap="round"/><circle cx="28" cy="40" r="3" class="cs-sw-dot cs-sw-dot--warning"/></svg>',
        info:    '<svg class="cs-sw-svg" viewBox="0 0 56 56"><circle class="cs-sw-ring cs-sw-ring--info" cx="28" cy="28" r="26" fill="none" stroke-width="2"/><line class="cs-sw-mark cs-sw-mark--info" x1="28" y1="24" x2="28" y2="40" stroke-width="3" stroke-linecap="round"/><circle cx="28" cy="16" r="3" class="cs-sw-dot cs-sw-dot--info"/></svg>',
        question:'<svg class="cs-sw-svg" viewBox="0 0 56 56"><circle class="cs-sw-ring cs-sw-ring--question" cx="28" cy="28" r="26" fill="none" stroke-width="2"/><text class="cs-sw-question" x="28" y="38" text-anchor="middle" font-size="28" font-weight="700">?</text></svg>',
        loading: '<div class="cs-sw-spinner"><div></div><div></div><div></div><div></div></div>',
    };

    var activeModal = null;

    function el(tag, cls, inner) {
        var node = document.createElement(tag);
        if (cls)   node.className = cls;
        if (inner !== undefined) node.innerHTML = inner;
        return node;
    }

    function esc(str) {
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function dismiss() {
        if (!activeModal) return;
        var m = activeModal;
        activeModal = null;
        m.backdrop.classList.remove('cs-sw-backdrop--in');
        m.popup.classList.remove('cs-sw-popup--in');
        setTimeout(function () { m.backdrop.remove(); }, 320);
        m.resolve({ isConfirmed: false, isDismissed: true });
        document.removeEventListener('keydown', m.keyHandler);
    }

    var CsMfbSwal = {

        fire: function (opts) {
            opts = opts || {};
            if (activeModal) dismiss();

            return new Promise(function (resolve) {
                var title            = opts.title || '';
                var text             = opts.text  || '';
                var html             = opts.html  || '';
                var icon             = opts.icon  || '';
                var confirmText      = opts.confirmButtonText !== undefined ? opts.confirmButtonText : 'OK';
                var cancelText       = opts.cancelButtonText  !== undefined ? opts.cancelButtonText  : 'Cancel';
                var showCancel       = !!opts.showCancelButton;
                var showConfirm      = opts.showConfirmButton !== false;
                var allowOutside     = opts.allowOutsideClick !== false;
                var timer            = opts.timer || 0;

                /* Build popup */
                var backdrop = el('div', 'cs-sw-backdrop');
                var popup    = el('div', 'cs-sw-popup');

                if (icon && ICONS[icon]) {
                    var iconWrap = el('div', 'cs-sw-icon-wrap cs-sw-icon--' + icon, ICONS[icon]);
                    popup.appendChild(iconWrap);
                }
                if (title) popup.appendChild(el('h2', 'cs-sw-title', esc(title)));
                var body = html || (text ? esc(text) : '');
                if (body)  popup.appendChild(el('div', 'cs-sw-body', body));

                var actions = el('div', 'cs-sw-actions');
                var btnConfirm = null, btnCancel = null;

                if (showCancel) {
                    btnCancel  = el('button', 'cs-sw-btn cs-sw-btn--cancel',  esc(cancelText));
                    btnCancel.type = 'button';
                    actions.appendChild(btnCancel);
                }
                if (showConfirm) {
                    btnConfirm = el('button', 'cs-sw-btn cs-sw-btn--confirm', esc(confirmText));
                    btnConfirm.type = 'button';
                    actions.appendChild(btnConfirm);
                }
                if (showCancel || showConfirm) popup.appendChild(actions);

                backdrop.appendChild(popup);
                document.body.appendChild(backdrop);

                /* Animate in */
                requestAnimationFrame(function () {
                    backdrop.classList.add('cs-sw-backdrop--in');
                    popup.classList.add('cs-sw-popup--in');
                });

                /* Done helper */
                function done(confirmed) {
                    if (!activeModal) return;
                    activeModal = null;
                    backdrop.classList.remove('cs-sw-backdrop--in');
                    popup.classList.remove('cs-sw-popup--in');
                    setTimeout(function () { backdrop.remove(); }, 320);
                    document.removeEventListener('keydown', keyHandler);
                    resolve({ isConfirmed: confirmed, isDismissed: !confirmed, value: confirmed });
                }

                /* Key handler */
                function keyHandler(e) {
                    if (e.key === 'Escape' && allowOutside) done(false);
                    if (e.key === 'Enter'  && showConfirm)  done(true);
                }
                document.addEventListener('keydown', keyHandler);

                activeModal = { backdrop: backdrop, popup: popup, resolve: resolve, keyHandler: keyHandler };

                if (btnConfirm) btnConfirm.addEventListener('click', function () { done(true);  });
                if (btnCancel)  btnCancel.addEventListener('click',  function () { done(false); });
                if (allowOutside) {
                    backdrop.addEventListener('click', function (e) { if (e.target === backdrop) done(false); });
                }
                if (timer > 0) setTimeout(function () { done(false); }, timer);
            });
        },

        close: function () { dismiss(); },
    };

    global.CsMfbSwal = CsMfbSwal;

})(window);
