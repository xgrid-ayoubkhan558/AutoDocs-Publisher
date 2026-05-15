(function (w) {
    'use strict';

    var A = (w.AutoDocsAdmin = w.AutoDocsAdmin || {});

    A.domReady(function () {
        var settingsWrap = A.qs('.autodocs-settings');
        var bucketUi = A.createBucketUi();
        var drivePicker = A.createDrivePicker();

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

        document.addEventListener('click', function (e) {
            var syncBtn = e.target.closest('#autodocs-sync-now');
            if (!syncBtn || typeof AutoDocsPublisher === 'undefined' || !AutoDocsPublisher.ajaxUrl) {
                return;
            }
            e.preventDefault();
            var resultEl = A.qs('#autodocs-sync-result');
            syncBtn.disabled = true;
            if (resultEl) {
                resultEl.textContent = 'Syncing...';
            }
            A.postFormUrlEncoded(AutoDocsPublisher.ajaxUrl, {
                action: 'autodocs_sync_now',
                nonce: AutoDocsPublisher.nonce
            })
                .then(function (response) {
                    if (!response.success) {
                        if (resultEl) {
                            resultEl.textContent = 'Sync failed.';
                        }
                        return;
                    }
                    var data = response.data;
                    if (resultEl) {
                        resultEl.textContent =
                            'Created: ' +
                            data.created +
                            ', Updated: ' +
                            data.updated +
                            ', Skipped: ' +
                            data.skipped +
                            ', Missing: ' +
                            data.missing;
                    }
                    A.refreshDashboardSidebar(true);
                    if (typeof w.autodocsBucketUiRefreshArticles === 'function') {
                        w.autodocsBucketUiRefreshArticles();
                    }
                })
                .catch(function () {
                    if (resultEl) {
                        resultEl.textContent = 'Sync failed.';
                    }
                })
                .finally(function () {
                    syncBtn.disabled = false;
                });
        });

        function runTestConnection(btn) {
            if (typeof AutoDocsPublisher === 'undefined' || !AutoDocsPublisher.ajaxUrl) {
                return;
            }
            var resultEl = A.qs('#autodocs-sync-result');
            if (btn) {
                btn.disabled = true;
            }
            if (resultEl) {
                resultEl.textContent = 'Testing connection...';
            }
            A.postFormUrlEncoded(AutoDocsPublisher.ajaxUrl, {
                action: 'autodocs_list_drive_folders',
                nonce: AutoDocsPublisher.nonce,
                parent_id: 'root'
            })
                .then(function (response) {
                    if (response && response.success) {
                        if (resultEl) {
                            resultEl.textContent = 'Connection OK.';
                        }
                        return A.postFormUrlEncoded(AutoDocsPublisher.ajaxUrl, {
                            action: 'autodocs_refresh_statuses',
                            nonce: AutoDocsPublisher.nonce
                        }).then(function () {
                            A.refreshDashboardSidebar(true);
                        });
                    }
                    var msg =
                        response && response.data && response.data.message ? response.data.message : 'Connection failed.';
                    if (resultEl) {
                        resultEl.textContent = msg;
                    }
                })
                .catch(function () {
                    if (resultEl) {
                        resultEl.textContent = 'Connection failed.';
                    }
                })
                .finally(function () {
                    if (btn) {
                        btn.disabled = false;
                    }
                });
        }

        document.addEventListener('click', function (e) {
            var t = e.target.closest('#autodocs-footer-test-connection, .autodocs-action-test-connection');
            if (t) {
                runTestConnection(t);
            }
        });

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

            function prepareImportAside(folderId, bucketKey) {
                var body = A.qs('#autodocs-import-aside-body');
                var empty = A.qs('#autodocs-import-aside-empty');
                if (!folderId || typeof AutoDocsPublisher === 'undefined' || !AutoDocsPublisher.importNonce || !body) {
                    return;
                }
                if (empty) {
                    empty.style.display = 'none';
                }
                body.removeAttribute('hidden');
                A.showEl(body, true);
                body.innerHTML = '';
                body.appendChild(A.el('p', { class: 'description', text: bucketUi.t('preparingImport', 'Loading import options…') }));

                A.postFormUrlEncoded(AutoDocsPublisher.ajaxUrl, {
                    action: 'autodocs_prepare_import_new',
                    nonce: AutoDocsPublisher.importNonce,
                    folder_id: folderId,
                    bucket_key: bucketKey || 'new'
                })
                    .then(function (response) {
                        if (!response || !response.success) {
                            var msg =
                                response && response.data && response.data.message
                                    ? response.data.message
                                    : bucketUi.t('importFailed', 'Import failed.');
                            body.innerHTML = '';
                            body.appendChild(A.el('p', { class: 'notice notice-error', text: msg }));
                            return;
                        }
                        bucketUi.renderImportForm(body, response.data);
                    })
                    .catch(function () {
                        body.innerHTML = '';
                        body.appendChild(
                            A.el('p', { class: 'notice notice-error', text: bucketUi.t('importFailed', 'Import failed.') })
                        );
                    });
            }

            A.on(settingsWrap, 'click', '.autodocs-article-import', function (e, btn) {
                prepareImportAside(btn.getAttribute('data-folder-id'), btn.getAttribute('data-bucket-key') || 'new');
            });

            A.on(settingsWrap, 'click', '.autodocs-import-cancel', function () {
                var body = A.qs('#autodocs-import-aside-body');
                if (body) {
                    body.innerHTML = '';
                    body.setAttribute('hidden', 'hidden');
                    body.style.display = 'none';
                }
                var emptyEl = A.qs('#autodocs-import-aside-empty');
                if (emptyEl) {
                    emptyEl.style.display = '';
                }
            });

            A.on(settingsWrap, 'click', '.autodocs-import-save', function (e, saveBtn) {
                var els = saveBtn._autodocsImportEls;
                var folderId = saveBtn.dataset.folderId;
                var bucketKey = saveBtn.dataset.bucketKey || 'new';
                var panel = saveBtn.closest('#autodocs-import-aside-body');
                if (!els || !folderId || typeof AutoDocsPublisher === 'undefined' || !AutoDocsPublisher.importNonce || !panel) {
                    return;
                }

                saveBtn.disabled = true;
                A.qsa('.notice', panel).forEach(function (n) {
                    n.remove();
                });

                var fd = new FormData();
                fd.append('action', 'autodocs_import_new_folder');
                fd.append('nonce', AutoDocsPublisher.importNonce);
                fd.append('folder_id', folderId);
                fd.append('bucket_key', bucketKey);
                fd.append('post_title', els.post_title.value || '');
                fd.append('post_name', els.post_name.value || '');
                fd.append('post_type', els.post_type.value || '');
                fd.append('post_status', els.post_status.value || '');
                fd.append('post_excerpt', els.post_excerpt.value || '');
                fd.append('acf_body_field', els.acf_body_field ? els.acf_body_field.value || '' : '');
                fd.append('acf_body_field_custom', els.acf_body_field_custom ? els.acf_body_field_custom.value || '' : '');
                fd.append('tags', els.tags.value || '');
                fd.append('categories_mode', els.catModeMan.checked ? 'manual' : 'doc');
                fd.append('tags_mode', els.tagModeMan.checked ? 'manual' : 'doc');

                var catVal = A.getMultiSelectValues(els.categories);
                if (catVal && catVal.length) {
                    catVal.forEach(function (cid) {
                        fd.append('categories[]', cid);
                    });
                }

                if (els.move) {
                    fd.append('move_to_synced', els.move.checked ? '1' : '');
                }

                A.postFormData(AutoDocsPublisher.ajaxUrl, fd)
                    .then(function (response) {
                        if (!response || !response.success) {
                            var msg =
                                response && response.data && response.data.message
                                    ? response.data.message
                                    : bucketUi.t('importFailed', 'Import failed.');
                            panel.insertBefore(A.el('p', { class: 'notice notice-error', text: msg }), panel.firstChild);
                            return;
                        }
                        var url = response.data && response.data.edit_url;
                        var okMsg = bucketUi.t('importSuccess', 'Post saved.');
                        var box = A.el('p', { class: 'notice notice-success', text: okMsg });
                        if (url) {
                            box.appendChild(document.createTextNode(' '));
                            box.appendChild(A.el('a', { href: url, text: bucketUi.t('editPost', 'Edit post') }));
                        }
                        panel.innerHTML = '';
                        panel.appendChild(box);
                        if (typeof w.autodocsBucketUiRefreshArticles === 'function') {
                            w.autodocsBucketUiRefreshArticles();
                        }
                        A.refreshDashboardSidebar(true);
                    })
                    .catch(function () {
                        panel.insertBefore(
                            A.el('p', { class: 'notice notice-error', text: bucketUi.t('importFailed', 'Import failed.') }),
                            panel.firstChild
                        );
                    })
                    .finally(function () {
                        saveBtn.disabled = false;
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
