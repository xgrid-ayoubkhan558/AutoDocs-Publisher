(function (w) {
    'use strict';

    var A = (w.AutoDocsAdmin = w.AutoDocsAdmin || {});

    /**
     * Live clocks, next/last run display, and AJAX preview for automatic sync settings.
     */
    A.bindCronSettingsPreview = function () {
        var section = A.qs('#autodocs-cron-settings');
        if (!section) {
            return;
        }

        var enabled = A.qs('#autodocs-cron-enabled');
        var interval = A.qs('#autodocs-cron-interval');
        var timeInput = A.qs('#autodocs-cron-time');
        var timeRow = A.qs('#autodocs-cron-time-row');
        var nextBlock = A.qs('#autodocs-cron-next-block');
        var nextRunSite = A.qs('#autodocs-cron-next-run-site');
        var nextRunLocal = A.qs('#autodocs-cron-next-run-local');
        var nextRunRelative = A.qs('#autodocs-cron-next-run-relative');
        var computerNowEl = A.qs('#autodocs-cron-computer-now');
        var siteNowEl = A.qs('#autodocs-cron-site-now');
        var footnote = A.qs('#autodocs-cron-footnote');
        var pub = typeof AutoDocsPublisher !== 'undefined' ? AutoDocsPublisher : {};
        var cron = pub.cron || {};
        var i18n = pub.i18n || {};
        var siteTz = section.getAttribute('data-timezone') || cron.siteTzIntl || 'UTC';
        var tzLabel = section.getAttribute('data-timezone-label') || cron.siteTzLabel || '';
        var previewTimer = null;
        var currentNextTs = parseInt(section.getAttribute('data-next-ts') || '0', 10);
        var lastRunSite = A.qs('#autodocs-cron-last-run-site');
        var lastRunLocal = A.qs('#autodocs-cron-last-run-local');
        var lastRunBlock = A.qs('#autodocs-cron-last-run-block');
        var lastRunNever = A.qs('#autodocs-cron-last-run-never');
        var lastRunTs = parseInt(section.getAttribute('data-last-ts') || '0', 10);
        var settingsDirty = false;

        function browserTimezoneLabel() {
            try {
                return Intl.DateTimeFormat().resolvedOptions().timeZone || '';
            } catch (err) {
                return '';
            }
        }

        function formatShort(ts, timeZone, zoneLabel) {
            try {
                var fmt = new Intl.DateTimeFormat(undefined, {
                    timeZone: timeZone,
                    dateStyle: 'medium',
                    timeStyle: 'short'
                }).format(new Date(ts * 1000));
                return zoneLabel ? fmt + ' (' + zoneLabel + ')' : fmt;
            } catch (err) {
                return new Date(ts * 1000).toLocaleString();
            }
        }

        function formatComputerNow() {
            return formatShort(Math.floor(Date.now() / 1000), undefined, browserTimezoneLabel());
        }

        function sprintfNum(tpl, n, n2) {
            if (!tpl) {
                return '';
            }
            var out = tpl;
            if (n2 !== undefined) {
                out = out.replace('%2$d', String(n2)).replace('%1$d', String(n));
            } else {
                out = out.replace('%1$d', String(n)).replace('%d', String(n));
            }
            return out;
        }

        function relativeUntil(ts) {
            var diff = ts - Math.floor(Date.now() / 1000);
            if (diff <= 0) {
                var ago = Math.abs(diff);
                if (ago < 60) {
                    return sprintfNum(i18n.cronAgoSeconds || '%d seconds ago', ago);
                }
                return sprintfNum(i18n.cronAgoMinutes || '%d minutes ago', Math.max(1, Math.round(ago / 60)));
            }
            if (diff < 60) {
                return diff < 30
                    ? i18n.cronInLessThanMinute || 'in less than a minute'
                    : sprintfNum(i18n.cronInSeconds || 'in %d seconds', diff);
            }
            if (diff < 3600) {
                return sprintfNum(i18n.cronInMinutes || 'in %d minutes', Math.max(1, Math.round(diff / 60)));
            }
            if (diff < 86400) {
                return sprintfNum(
                    i18n.cronInHoursMinutes || 'in %1$d hours %2$d minutes',
                    Math.floor(diff / 3600),
                    Math.round((diff % 3600) / 60)
                );
            }
            return sprintfNum(
                i18n.cronInDaysHours || 'in %1$d days %2$d hours',
                Math.floor(diff / 86400),
                Math.round((diff % 86400) / 3600)
            );
        }

        function applyLastRunState() {
            if (lastRunTs > 0) {
                if (lastRunBlock) {
                    lastRunBlock.hidden = false;
                }
                if (lastRunNever) {
                    lastRunNever.hidden = true;
                }
                if (lastRunSite) {
                    lastRunSite.textContent = formatShort(lastRunTs, siteTz, tzLabel);
                }
                if (lastRunLocal) {
                    lastRunLocal.textContent = formatShort(
                        lastRunTs,
                        browserTimezoneLabel() || undefined,
                        browserTimezoneLabel()
                    );
                }
            } else {
                if (lastRunBlock) {
                    lastRunBlock.hidden = true;
                }
                if (lastRunNever) {
                    lastRunNever.hidden = false;
                }
            }
        }

        function applyNextRunState() {
            if (!enabled || !enabled.checked) {
                if (nextBlock) {
                    nextBlock.hidden = true;
                }
                if (footnote) {
                    footnote.hidden = true;
                }
                return;
            }
            if (nextBlock) {
                nextBlock.hidden = false;
            }
            if (footnote) {
                footnote.hidden = false;
            }
            var rel = currentNextTs > 0 ? relativeUntil(currentNextTs) : '';
            if (nextRunRelative) {
                if (rel) {
                    nextRunRelative.hidden = false;
                    nextRunRelative.textContent = rel;
                } else {
                    nextRunRelative.hidden = true;
                    nextRunRelative.textContent = '';
                }
            }
            if (currentNextTs > 0) {
                if (nextRunSite) {
                    nextRunSite.textContent = formatShort(currentNextTs, siteTz, tzLabel);
                }
                if (nextRunLocal) {
                    nextRunLocal.textContent = formatShort(
                        currentNextTs,
                        browserTimezoneLabel() || undefined,
                        browserTimezoneLabel()
                    );
                }
            } else {
                if (nextRunSite) {
                    nextRunSite.textContent = '—';
                }
                if (nextRunLocal) {
                    nextRunLocal.textContent = '—';
                }
            }
        }

        function applyStatusData(data) {
            if (!data) {
                return;
            }
            if (data.next_run_ts) {
                currentNextTs = parseInt(data.next_run_ts, 10) || 0;
                section.setAttribute('data-next-ts', String(currentNextTs));
                settingsDirty = false;
            }
            if (data.last_run_ts) {
                lastRunTs = parseInt(data.last_run_ts, 10) || 0;
                section.setAttribute('data-last-ts', String(lastRunTs));
            }
            applyLastRunState();
            if (footnote && data.wp_cron_disabled) {
                footnote.textContent =
                    i18n.cronWpCronDisabled ||
                    'WP-Cron is disabled in wp-config.php. Use a server cron job calling wp-cron.php, or run Sync now manually.';
            }
        }

        function fetchStatus() {
            if (typeof AutoDocsPublisher === 'undefined' || !AutoDocsPublisher.ajaxUrl) {
                return;
            }
            if (!enabled || !enabled.checked) {
                return;
            }
            var body = new URLSearchParams();
            body.set('action', 'autodocs_cron_status');
            body.set('nonce', AutoDocsPublisher.nonce);

            return fetch(AutoDocsPublisher.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (json) {
                    if (!json || !json.success || !json.data) {
                        return;
                    }
                    applyStatusData(json.data);
                    applyNextRunState();
                })
                .catch(function () {});
        }

        function fetchPreview() {
            if (typeof AutoDocsPublisher === 'undefined' || !AutoDocsPublisher.ajaxUrl) {
                return;
            }
            if (!enabled || !enabled.checked) {
                return;
            }
            var body = new URLSearchParams();
            body.set('action', 'autodocs_cron_preview');
            body.set('nonce', AutoDocsPublisher.nonce);
            body.set('interval', interval ? interval.value : 'hourly');
            body.set('cron_time', timeInput ? timeInput.value : '03:00');
            body.set('enabled', '1');

            fetch(AutoDocsPublisher.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (json) {
                    if (!json || !json.success || !json.data) {
                        return;
                    }
                    settingsDirty = true;
                    if (json.data.next_run_ts) {
                        currentNextTs = parseInt(json.data.next_run_ts, 10) || 0;
                    }
                    if (siteNowEl && json.data.site_now) {
                        siteNowEl.textContent = json.data.site_now;
                    }
                    if (footnote) {
                        footnote.textContent =
                            i18n.cronNextRunEstimate || 'Save settings to apply interval changes.';
                    }
                    applyNextRunState();
                })
                .catch(function () {});
        }

        function update() {
            var iv = interval ? interval.value : 'hourly';
            if (timeRow) {
                timeRow.hidden = iv !== 'daily' && iv !== 'twicedaily';
            }
            if (!enabled || !enabled.checked) {
                applyNextRunState();
                return;
            }
            if (settingsDirty) {
                clearTimeout(previewTimer);
                previewTimer = setTimeout(fetchPreview, 200);
            }
        }

        function onUserChange() {
            settingsDirty = true;
            update();
        }

        function tick() {
            if (computerNowEl) {
                computerNowEl.textContent = formatComputerNow();
            }
            applyNextRunState();
            if (enabled && enabled.checked && currentNextTs > 0) {
                var diff = currentNextTs - Math.floor(Date.now() / 1000);
                if (diff <= 0 && !settingsDirty) {
                    fetchStatus();
                }
            }
        }

        applyLastRunState();
        tick();
        setInterval(tick, 1000);
        fetchStatus();
        setInterval(fetchStatus, 30000);

        [enabled, interval, timeInput].forEach(function (el) {
            if (el) {
                el.addEventListener('change', onUserChange);
                el.addEventListener('input', onUserChange);
            }
        });
        update();
    };
})(window);
