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
    });
})(window);
