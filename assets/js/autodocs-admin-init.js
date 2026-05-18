(function (w) {
    'use strict';

    var A = (w.AutoDocsAdmin = w.AutoDocsAdmin || {});

    A.domReady(function () {
        var settingsWrap = A.qs('.autodocs-settings');
        var bucketUi = A.createBucketUi();
        var drivePicker = A.createDrivePicker();
        var importWizard = A.createImportWizard();
        w.autodocsImportWizard = importWizard;

        w.autodocsBucketUiRefreshArticles = function () {
            bucketUi.refreshAllArticlePanels(true);
        };

        if (settingsWrap) {
            settingsWrap.addEventListener('click', function (e) {
                var tabBtn = e.target.closest('.autodocs-nav-tabs .nav-tab');
                if (!tabBtn || !settingsWrap.contains(tabBtn)) {
                    return;
                }
                e.preventDefault();
                var tab = tabBtn.getAttribute('data-autodocs-tab');
                if (!tab) {
                    return;
                }
                A.qsa('.autodocs-nav-tabs .nav-tab', settingsWrap).forEach(function (btn) {
                    btn.classList.remove('nav-tab-active');
                    btn.setAttribute('aria-selected', 'false');
                });
                tabBtn.classList.add('nav-tab-active');
                tabBtn.setAttribute('aria-selected', 'true');
                A.qsa('.autodocs-tab-panel', settingsWrap).forEach(function (p) {
                    p.classList.remove('is-active');
                });
                var panel = A.qs('#autodocs-tab-panel-' + tab, settingsWrap);
                if (panel) {
                    panel.classList.add('is-active');
                }
                if (tab === 'articles') {
                    A.refreshDashboardSidebar(true);
                }
            });
        }

        A.bindDrivePicker(drivePicker);

        if (settingsWrap) {
            A.on(settingsWrap, 'click', '[data-autodocs-article-sub]', function (e, btn) {
                e.preventDefault();
                var sub = btn.getAttribute('data-autodocs-article-sub');
                if (!sub) {
                    return;
                }
                var wrap = A.qs('[data-autodocs-article-subtabs]');
                if (!wrap) {
                    return;
                }
                A.qsa('.autodocs-articles-subtabs__tab', wrap).forEach(function (t) {
                    t.classList.remove('is-active');
                    t.setAttribute('aria-selected', 'false');
                });
                btn.classList.add('is-active');
                btn.setAttribute('aria-selected', 'true');
                A.qsa('.autodocs-articles-subtabs__panel', wrap).forEach(function (p) {
                    p.classList.remove('is-active');
                    p.setAttribute('hidden', 'hidden');
                    p.style.display = 'none';
                });
                var subpanel = A.qs('#autodocs-article-subpanel-' + sub);
                if (subpanel) {
                    subpanel.classList.add('is-active');
                    subpanel.removeAttribute('hidden');
                    subpanel.style.display = '';
                }
            });

            A.on(settingsWrap, 'click', '.autodocs-articles-pagination__prev, .autodocs-articles-pagination__next', function (e, btn) {
                e.preventDefault();
                if (btn.disabled) {
                    return;
                }
                var bucketKey = btn.getAttribute('data-autodocs-article-bucket');
                var page = parseInt(btn.getAttribute('data-autodocs-article-page'), 10);
                if (!bucketKey || !page || page < 1) {
                    return;
                }
                bucketUi.refreshArticlePanelForKey(bucketKey, page);
            });

            A.on(settingsWrap, 'click', '.autodocs-bucket-card__change', function (e, btn) {
                e.preventDefault();
                var id = btn.getAttribute('data-autodocs-focus-select');
                if (!id) {
                    return;
                }
                var driveTab = A.qs('.autodocs-nav-tabs .nav-tab[data-autodocs-tab="drive"]', settingsWrap);
                if (driveTab) {
                    driveTab.click();
                }
                window.setTimeout(function () {
                    var elFocus = A.qs('#' + id);
                    if (!elFocus || typeof elFocus.focus !== 'function') {
                        return;
                    }
                    if (typeof elFocus.scrollIntoView === 'function') {
                        elFocus.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }
                    elFocus.focus({ preventScroll: true });
                }, 0);
            });

            function openImportWizard(opts) {
                opts = opts || {};
                var bucketKey = opts.bucketKey || bucketUi.activeArticleKey();
                var articles = bucketUi.getCachedArticles(bucketKey).slice();
                if (opts.articleStub && opts.articleStub.folder_id) {
                    var has = articles.some(function (a) {
                        return a.folder_id === opts.articleStub.folder_id;
                    });
                    if (!has) {
                        articles.push(opts.articleStub);
                    }
                }
                importWizard.open({
                    bucketKey: bucketKey,
                    articles: articles,
                    preselect: opts.preselect || [],
                    singleFolderId: opts.singleFolderId || '',
                    startStep: opts.startStep || 1
                });
            }

            var openWizardBtn = A.qs('#autodocs-open-import-wizard');
            if (openWizardBtn) {
                openWizardBtn.addEventListener('click', function () {
                    var pre = [];
                    A.qsa('.autodocs-article-row-check:checked', settingsWrap).forEach(function (cb) {
                        var fid = cb.getAttribute('data-folder-id');
                        if (fid) {
                            pre.push(fid);
                        }
                    });
                    openImportWizard({ preselect: pre });
                });
            }

            A.on(settingsWrap, 'click', '.autodocs-article-import', function (e, btn) {
                e.preventDefault();
                openImportWizard({
                    bucketKey: btn.getAttribute('data-bucket-key') || bucketUi.activeArticleKey(),
                    singleFolderId: btn.getAttribute('data-folder-id'),
                    articleStub: {
                        folder_id: btn.getAttribute('data-folder-id'),
                        doc_name: btn.getAttribute('data-doc-name') || '',
                        folder_name: btn.getAttribute('data-folder-name') || '',
                        path_label: btn.getAttribute('data-path-label') || ''
                    },
                    startStep: 2
                });
            });
        }

        var workingFolderId = A.qs('#autodocs-working-folder-id');
        if (workingFolderId) {
            var wfVal = A.trim(workingFolderId.value);
            bucketUi.updateBucketsVisibility(!!wfVal);

            workingFolderId.addEventListener('change', function () {
                var v = A.trim(workingFolderId.value);
                var idDisp = A.qs('#autodocs-drive-root-id-display');
                if (idDisp) {
                    idDisp.textContent = v || '—';
                }
                var nameInput = A.qs('#autodocs-working-folder-name');
                var nm = nameInput ? A.trim(nameInput.value) : '';
                var rootName = A.qs('#autodocs-drive-root-name');
                if (rootName) {
                    rootName.textContent = nm || v || '—';
                }
                var rootPath = A.qs('#autodocs-drive-root-path');
                if (rootPath) {
                    rootPath.textContent = nm ? 'Drive / ' + nm : 'Drive / —';
                }
                bucketUi.loadRootChildren();
                A.loadDriveRootMetaIfNeeded();
            });

            var refreshBtn = A.qs('#autodocs-refresh-article-lists');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function () {
                    bucketUi.panelPages = { new: 1, synced: 1, modified: 1 };
                    bucketUi.refreshAllArticlePanels(true);
                    A.refreshDashboardSidebar(true);
                });
            }

            var syncNowBtn = A.qs('#autodocs-sync-now');
            var syncNowStatus = A.qs('#autodocs-sync-now-status');
            if (syncNowBtn && typeof AutoDocsPublisher !== 'undefined') {
                syncNowBtn.addEventListener('click', function () {
                    syncNowBtn.disabled = true;
                    if (syncNowStatus) {
                        syncNowStatus.textContent =
                            (AutoDocsPublisher.i18n && AutoDocsPublisher.i18n.syncNowRunning) || 'Syncing…';
                    }
                    A.postFormUrlEncoded(AutoDocsPublisher.ajaxUrl, {
                        action: 'autodocs_sync_now',
                        nonce: AutoDocsPublisher.nonce
                    })
                        .then(function (res) {
                            if (!res || !res.success) {
                                if (syncNowStatus) {
                                    syncNowStatus.textContent =
                                        (res && res.data && res.data.message) ||
                                        (AutoDocsPublisher.i18n && AutoDocsPublisher.i18n.syncNowFailed) ||
                                        'Sync failed.';
                                }
                                return;
                            }
                            var d = res.data || {};
                            if (syncNowStatus) {
                                syncNowStatus.textContent =
                                    (AutoDocsPublisher.i18n && AutoDocsPublisher.i18n.syncNowDone) ||
                                    'Sync complete. Created: ' +
                                        String(d.created || 0) +
                                        ', updated: ' +
                                        String(d.updated || 0) +
                                        ', skipped: ' +
                                        String(d.skipped || 0);
                            }
                            bucketUi.panelPages = { new: 1, synced: 1, modified: 1 };
                            bucketUi.refreshAllArticlePanels(true);
                            A.refreshDashboardSidebar(true);
                        })
                        .catch(function () {
                            if (syncNowStatus) {
                                syncNowStatus.textContent =
                                    (AutoDocsPublisher.i18n && AutoDocsPublisher.i18n.syncNowFailed) || 'Sync failed.';
                            }
                        })
                        .finally(function () {
                            syncNowBtn.disabled = false;
                        });
                });
            }

            if (settingsWrap) {
                settingsWrap.addEventListener('change', function (e) {
                    var sel = e.target.closest('.autodocs-bucket-select');
                    if (!sel) {
                        return;
                    }
                    var key = sel.id === 'autodocs-bucket-new' ? 'new' : sel.id === 'autodocs-bucket-synced' ? 'synced' : '';
                    if (key) {
                        bucketUi.invalidateArticlePanels();
                        bucketUi.loadAllArticlePanels(true);
                    }
                });
            }

            bucketUi.loadRootChildren();
            A.loadDriveRootMetaIfNeeded();
            A.refreshDashboardSidebar(false);
            if (A.qs('#autodocs-tab-panel-articles.is-active')) {
                A.refreshDashboardSidebar(true);
            }
        }

        A.bindCronSettingsPreview();
    });

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
        var statusTimer = null;
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

        function tick() {
            if (computerNowEl) {
                computerNowEl.textContent = formatComputerNow();
            }
            applyNextRunState();
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
        statusTimer = setInterval(fetchStatus, 30000);

        [enabled, interval, timeInput].forEach(function (el) {
            if (el) {
                el.addEventListener('change', onUserChange);
                el.addEventListener('input', onUserChange);
            }
        });
        update();
    };
})(window);
