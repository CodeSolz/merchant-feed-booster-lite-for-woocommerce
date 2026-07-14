/* Merchant Feed Booster Lite – premium AJAX admin */
(function ($) {
    'use strict';

    var Swal    = window.CsMfbSwal;
    var restUrl = (window.csMfb && csMfb.restUrl) || '';
    var nonce   = (window.csMfb && csMfb.nonce)   || '';
    var scanTimer = null;

    /* ── REST fetch helper ────────────────────────────────────────── */
    function apiFetch(endpoint, method) {
        return fetch(restUrl + endpoint, {
            method:      method || 'GET',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce':   nonce,
                'Content-Type': 'application/json',
            },
        }).then(function (r) { return r.json(); });
    }

    /* ── Regenerate Feed ──────────────────────────────────────────── */
    function doRegenerate(triggerEl) {
        var reloadAfter = triggerEl && triggerEl.getAttribute('data-cs-reload-after') === '1';

        Swal.fire({
            title:             'Regenerate Feed?',
            text:              'This will rebuild your XML feed from all published products.',
            icon:              'question',
            showCancelButton:  true,
            confirmButtonText: 'Yes, regenerate',
            cancelButtonText:  'Cancel',
        }).then(function (res) {
            if (!res.isConfirmed) return;

            Swal.fire({
                title:             'Generating…',
                text:              'Please wait while the feed is rebuilt.',
                icon:              'loading',
                showConfirmButton: false,
                allowOutsideClick: false,
            });

            apiFetch('feed/regenerate', 'POST').then(function (data) {
                if (data && data.written !== undefined) {
                    var successOpts = {
                        title: 'Feed Regenerated!',
                        html:  '<strong>' + data.written + '</strong> products written to feed' +
                               (data.skipped_oos ? ' (' + data.skipped_oos + ' OOS skipped)' : '') + '.',
                        icon:  'success',
                    };
                    if (reloadAfter) {
                        successOpts.timer             = 1800;
                        successOpts.showConfirmButton = false;
                    }
                    Swal.fire(successOpts).then(function () {
                        if (reloadAfter) { location.reload(); }
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text:  (data && data.message) ? data.message : 'Feed regeneration failed.',
                        icon:  'error',
                    });
                }
            }).catch(function () {
                Swal.fire({ title: 'Network Error', text: 'Could not reach the server. Please try again.', icon: 'error' });
            });
        });
    }

    /* ── Clear Audit Cache ────────────────────────────────────────── */
    function doClearCache() {
        Swal.fire({
            title:             'Clear Audit Cache?',
            text:              'All cached health scores will be deleted. The next scan will re-score every product from scratch.',
            icon:              'warning',
            showCancelButton:  true,
            confirmButtonText: 'Yes, clear it',
            cancelButtonText:  'Cancel',
        }).then(function (res) {
            if (!res.isConfirmed) return;

            apiFetch('cache/clear', 'POST').then(function (data) {
                if (data && data.success) {
                    Swal.fire({
                        title:             'Cache Cleared!',
                        text:              'The next scan will rescore all products.',
                        icon:              'success',
                        timer:             1800,
                        showConfirmButton: false,
                    }).then(function () { location.reload(); });
                } else {
                    Swal.fire({ title: 'Error', text: 'Failed to clear cache.', icon: 'error' });
                }
            }).catch(function () {
                Swal.fire({ title: 'Network Error', text: 'Could not reach the server.', icon: 'error' });
            });
        });
    }

    /* ── Re-scan Products ─────────────────────────────────────────── */
    function doRescan() {
        var $wrap  = $('#cs-mfb-scan-progress');
        var $fill  = $('#cs-mfb-progress-fill');
        var $label = $('#cs-mfb-progress-label');
        var $btn   = $('[data-cs-action="rescan"]');

        if (scanTimer) return; // already running

        $btn.prop('disabled', true).text('Scanning…');
        $wrap.slideDown(200);

        apiFetch('scan/start', 'POST').then(function (data) {
            if (data && data.error) {
                $btn.prop('disabled', false).text('Re-scan Products');
                $wrap.slideUp();
                Swal.fire({ title: 'Error', text: data.error, icon: 'error' });
                return;
            }
            scanTimer = setInterval(pollProgress, 1500);
        }).catch(function () {
            $btn.prop('disabled', false).text('Re-scan Products');
            $wrap.slideUp();
            Swal.fire({ title: 'Network Error', text: 'Scan could not start.', icon: 'error' });
        });

        function pollProgress() {
            apiFetch('scan/progress', 'POST').then(function (data) {
                if (!data) return;
                var pct = data.pct || 0;
                $fill.css('width', Math.min(100, pct) + '%');
                $label.text('Scanned ' + (data.processed || 0) + ' of ' + (data.total || '?') + ' products…');

                if (data.status === 'done') {
                    clearInterval(scanTimer);
                    scanTimer = null;
                    $fill.css('width', '100%');
                    $label.text('Scan complete. Reloading…');
                    setTimeout(function () { location.reload(); }, 800);
                }
            });
        }
    }

    /* ── Character counters ───────────────────────────────────────── */
    function initCharCounters() {
        var inputs = document.querySelectorAll('[data-cs-maxlen]');
        for (var i = 0; i < inputs.length; i++) {
            (function (input) {
                var counterId = input.getAttribute('data-cs-counter');
                var maxLen    = parseInt(input.getAttribute('data-cs-maxlen'), 10) || 0;
                var counter   = counterId ? document.getElementById(counterId) : null;
                if (!counter) return;

                function update() {
                    var len  = input.value.length;
                    var unit = input.closest('[lang]') ? 'characters' : 'characters';
                    counter.textContent = len + ' / ' + maxLen + ' ' + unit;
                    counter.style.color = len > maxLen * 0.9 ? '#ef4444' : '';
                }

                input.addEventListener('input', update);
                update();
            }(inputs[i]));
        }
    }

    /* ── Schedule pill selector ───────────────────────────────────── */
    function initSchedulePills() {
        var pillGroups = document.querySelectorAll('[data-cs-pills]');
        for (var i = 0; i < pillGroups.length; i++) {
            (function (group) {
                var selectId    = group.getAttribute('data-cs-pills');
                var customWrap  = document.getElementById('cs-mfb-custom-freq-wrap');
                var hiddenInput = document.getElementById(selectId + '_hidden');
                var numInput    = document.getElementById('cs_mfb_refresh_custom_interval');
                var unitSelect  = document.getElementById('cs_mfb_refresh_custom_unit');
                var pills       = group.querySelectorAll('.cs-mfb-schedule-pill');

                function setAllInactive() {
                    for (var k = 0; k < pills.length; k++) {
                        pills[k].classList.remove('cs-mfb-schedule-pill--active');
                    }
                }

                for (var j = 0; j < pills.length; j++) {
                    pills[j].addEventListener('click', function () {
                        setAllInactive();
                        this.classList.add('cs-mfb-schedule-pill--active');

                        if (this.hasAttribute('data-cs-pill-custom')) {
                            // Show the custom interval picker
                            if (customWrap) customWrap.removeAttribute('hidden');
                            if (hiddenInput) {
                                hiddenInput.value = 'custom';
                                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        } else {
                            // Standard pill — update hidden freq input, hide picker
                            var val = this.getAttribute('data-cs-pill-val');
                            if (customWrap) customWrap.setAttribute('hidden', '');
                            if (hiddenInput) {
                                hiddenInput.value = val;
                                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        }
                    });
                }

                // Live preview hint when interval or unit changes
                function updateHint() {
                    if (!numInput || !unitSelect || !customWrap) return;
                    var n    = parseInt(numInput.value, 10) || 1;
                    var unit = unitSelect.options[unitSelect.selectedIndex].text.toLowerCase();
                    var hint = customWrap.querySelector('.cs-mfb-setting-hint');
                    if (hint) hint.textContent = 'Feed will regenerate every ' + n + ' ' + unit + '.';
                }
                if (numInput)   numInput.addEventListener('input', updateHint);
                if (unitSelect) unitSelect.addEventListener('change', updateHint);
            }(pillGroups[i]));
        }
    }

    /* ── Unsaved changes tracker ──────────────────────────────────── */
    var _settingsDirty   = false;
    var _savedAt         = null;

    function initUnsavedChanges() {
        var $form = $('#cs-mfb-settings-form');
        if (!$form.length) return;

        $form.on('change input', function () {
            _settingsDirty = true;
            $('#cs-mfb-unsaved-indicator').removeAttr('hidden');
            $('#cs-mfb-saved-time').attr('hidden', '');
        });
    }

    function markSaved() {
        _settingsDirty = false;
        _savedAt       = Date.now();
        $('#cs-mfb-unsaved-indicator').attr('hidden', '');
        var $savedTime = $('#cs-mfb-saved-time');
        $savedTime.removeAttr('hidden');
        $('#cs-mfb-saved-time-text').text('Saved just now');

        // Update relative timestamp every minute
        clearInterval(window._csMfbSavedTimer);
        window._csMfbSavedTimer = setInterval(function () {
            if (!_savedAt) return;
            var mins = Math.floor((Date.now() - _savedAt) / 60000);
            $('#cs-mfb-saved-time-text').text(mins < 1 ? 'Saved just now' : 'Last saved ' + mins + ' min' + (mins > 1 ? 's' : '') + ' ago');
        }, 30000);
    }

    /* ── Reset settings to defaults ──────────────────────────────── */
    function doResetSettings() {
        Swal.fire({
            title:             'Reset to Defaults?',
            text:              'All settings will be restored to their default values. This cannot be undone.',
            icon:              'warning',
            showCancelButton:  true,
            confirmButtonText: 'Yes, reset',
            cancelButtonText:  'Cancel',
        }).then(function (res) {
            if (!res.isConfirmed) return;

            Swal.fire({
                title:             'Resetting…',
                icon:              'loading',
                showConfirmButton: false,
                allowOutsideClick: false,
            });

            apiFetch('settings/reset', 'POST').then(function (data) {
                if (data && data.success) {
                    Swal.fire({
                        title:             'Settings Reset!',
                        text:              'Default settings have been restored.',
                        icon:              'success',
                        timer:             1600,
                        showConfirmButton: false,
                    }).then(function () { location.reload(); });
                } else {
                    Swal.fire({ title: 'Error', text: 'Failed to reset settings.', icon: 'error' });
                }
            }).catch(function () {
                Swal.fire({ title: 'Network Error', text: 'Could not reach the server.', icon: 'error' });
            });
        });
    }

    /* ── Settings form AJAX ───────────────────────────────────────── */
    function initSettingsForm() {
        var $form = $('#cs-mfb-settings-form');
        if (!$form.length) return;

        $form.on('submit', function (e) {
            e.preventDefault();

            var formData = new FormData(this);
            var $btn = $form.find('[type="submit"]');
            var origHtml = $btn.html();

            $btn.prop('disabled', true).text('Saving…');

            fetch($form.attr('action'), {
                method:      'POST',
                body:        formData,
                credentials: 'same-origin',
                redirect:    'follow',
            }).then(function (resp) {
                $btn.prop('disabled', false).html(origHtml);
                if (resp.ok) {
                    markSaved();
                    Swal.fire({
                        title:             'Settings Saved!',
                        icon:              'success',
                        timer:             1600,
                        showConfirmButton: false,
                    });
                } else {
                    Swal.fire({ title: 'Error', text: 'Could not save settings (HTTP ' + resp.status + ').', icon: 'error' });
                }
            }).catch(function () {
                $btn.prop('disabled', false).html(origHtml);
                Swal.fire({ title: 'Network Error', text: 'Could not save settings.', icon: 'error' });
            });
        });

        // Reset button
        $('#cs-mfb-reset-settings').on('click', function (e) {
            e.preventDefault();
            doResetSettings();
        });
    }

    /* ── Copy feed URL ────────────────────────────────────────────── */
    function initCopyUrl() {
        $(document).on('click', '[data-cs-copy-url]', function () {
            var text = $(this).closest('.cs-mfb-url-box').find('code').text().trim();
            if (!text || !navigator.clipboard) {
                Swal.fire({ title: 'Copy manually', html: '<code>' + text + '</code>', icon: 'info' });
                return;
            }
            navigator.clipboard.writeText(text).then(function () {
                Swal.fire({ title: 'Copied!', icon: 'success', timer: 1200, showConfirmButton: false });
            }).catch(function () {
                Swal.fire({ title: 'Could not copy', text: 'Please select and copy the URL manually.', icon: 'error' });
            });
        });
    }

    /* ── Accordion toggle ─────────────────────────────────────────── */
    function initAccordions() {
        $(document).on('click', '.cs-mfb-toggle-row', function () {
            var targetId = $(this).attr('aria-controls');
            var $row     = $('#' + targetId);
            var expanded = $(this).attr('aria-expanded') === 'true';

            if (expanded) {
                $row.attr('hidden', '');
                $(this).attr('aria-expanded', 'false').text('Details');
            } else {
                $row.removeAttr('hidden');
                $(this).attr('aria-expanded', 'true').text('Hide');
            }
        });
    }

    /* ── Action delegation ────────────────────────────────────────── */
    function initActions() {
        $(document).on('click', '[data-cs-action]', function (e) {
            e.preventDefault();
            var action = $(this).data('cs-action');
            if      (action === 'regenerate') doRegenerate(this);
            else if (action === 'clear-cache') doClearCache();
            else if (action === 'rescan')      doRescan();
        });
    }

    /* ── Gauge ring animation ─────────────────────────────────────── */
    function animateGauges() {
        var els = document.querySelectorAll('[data-cs-score]');
        for (var i = 0; i < els.length; i++) {
            (function (wrap) {
                var score = parseInt(wrap.getAttribute('data-cs-score'), 10) || 0;
                var fill  = wrap.querySelector('.cs-mfb-gauge-fill, .cs-mfb-hero-gauge-fill');
                if (!fill) return;
                fill.style.strokeDasharray = '0,100';
                setTimeout(function () {
                    fill.style.strokeDasharray = score + ',100';
                }, 380);
            }(els[i]));
        }
    }

    /* ── Settings sidebar tab switching ──────────────────────────── */
    function initSettingsTabs() {
        var navItems = document.querySelectorAll('[data-cs-settings-tab]');
        if (!navItems.length) return;

        for (var i = 0; i < navItems.length; i++) {
            navItems[i].addEventListener('click', function () {
                var tab = this.getAttribute('data-cs-settings-tab');

                for (var j = 0; j < navItems.length; j++) {
                    navItems[j].classList.remove('cs-mfb-settings-nav-item--active');
                }
                this.classList.add('cs-mfb-settings-nav-item--active');

                var panels = document.querySelectorAll('[data-cs-settings-section]');
                for (var k = 0; k < panels.length; k++) {
                    panels[k].hidden = panels[k].getAttribute('data-cs-settings-section') !== tab;
                }
            });
        }
    }

    /* ── Ring chart — center text swaps on hover ─────────────────── */
    function initPie3D() {
        var segs = document.querySelectorAll('.cs-mfb-ring-seg[data-label]');
        if (!segs.length) { return; }

        segs.forEach(function (seg) {
            var svg    = seg.closest('svg');
            var numEl  = svg && svg.querySelector('.cs-mfb-ring-num');
            var lblEl  = svg && svg.querySelector('.cs-mfb-ring-lbl');
            var defNum = numEl ? numEl.textContent : '';
            var defLbl = lblEl ? lblEl.textContent : '';

            seg.addEventListener('mouseenter', function () {
                if (numEl) numEl.textContent = seg.dataset.count;
                if (lblEl) lblEl.textContent = seg.dataset.label;
            });

            seg.addEventListener('mouseleave', function () {
                if (numEl) numEl.textContent = defNum;
                if (lblEl) lblEl.textContent = defLbl;
            });
        });
    }

    /* ── Boot ─────────────────────────────────────────────────────── */
    $(function () {
        if (!Swal) {
            window.console && console.warn('CsMfbSwal not loaded.');
            return;
        }
        animateGauges();
        initActions();
        initSettingsForm();
        initSettingsTabs();
        initAccordions();
        initCopyUrl();
        initCharCounters();
        initSchedulePills();
        initUnsavedChanges();
        initPie3D();
    });

}(jQuery));
