(function (w) {
    'use strict';

    var A = (w.AutoDocsAdmin = w.AutoDocsAdmin || {});

    A.createBucketUi = function () {
        var bucketUi = {
            i18n: typeof AutoDocsPublisher !== 'undefined' && AutoDocsPublisher.i18n ? AutoDocsPublisher.i18n : {},
            bucketSelectSelectors: ['#autodocs-bucket-new', '#autodocs-bucket-synced'],
            articlePanelRows: [
                { key: 'new', panel: A.qs('#autodocs-article-list-new') },
                { key: 'synced', panel: A.qs('#autodocs-article-list-synced') },
                { key: 'modified', panel: A.qs('#autodocs-article-list-modified'), listMode: 'modified' }
            ],

            folderIdForArticleKey: function (key) {
                if (key === 'new') {
                    var n = A.qs('#autodocs-bucket-new');
                    return n ? A.trim(n.value) : '';
                }
                var s = A.qs('#autodocs-bucket-synced');
                return s ? A.trim(s.value) : '';
            },

            t: function (key, fallback) {
                return this.i18n[key] || fallback;
            },

            renderImportForm: function (panel, data) {
                A.renderImportForm(panel, data, this.t.bind(this));
            },

            updateBucketsVisibility: function (hasRoot) {
                var wrap = A.qs('#autodocs-drive-buckets-wrap');
                var hint = A.qs('#autodocs-buckets-hidden-hint');
                if (wrap) {
                    A.showEl(wrap, !!hasRoot);
                }
                if (hint) {
                    A.showEl(hint, !hasRoot);
                }
            },

            clearBucketSelects: function () {
                this.bucketSelectSelectors.forEach(function (sel) {
                    var s = A.qs(sel);
                    if (!s) {
                        return;
                    }
                    var first = s.querySelector('option:first-child');
                    s.innerHTML = '';
                    if (first) {
                        s.appendChild(first.cloneNode(true));
                    }
                });
            },

            clearArticlePanels: function () {
                this.articlePanelRows.forEach(function (row) {
                    if (row.panel) {
                        row.panel.innerHTML = '';
                    }
                });
            },

            fillBucketSelectsFromFolders: function (folders) {
                this.bucketSelectSelectors.forEach(function (sel) {
                    var s = A.qs(sel);
                    if (!s) {
                        return;
                    }
                    var saved = s.getAttribute('data-autodocs-saved') || '';
                    var currentVal = s.value || '';
                    var first = s.querySelector('option:first-child');
                    var firstClone = first ? first.cloneNode(true) : A.el('option', { value: '', text: '—' });
                    s.innerHTML = '';
                    s.appendChild(firstClone);
                    folders.forEach(function (f) {
                        s.appendChild(A.el('option', { value: f.id, text: f.name || f.id }));
                    });
                    var ids = folders.map(function (f) {
                        return f.id;
                    });
                    var pick = '';
                    if (currentVal && ids.indexOf(currentVal) !== -1) {
                        pick = currentVal;
                    } else if (saved && ids.indexOf(saved) !== -1) {
                        pick = saved;
                    }
                    s.value = pick;
                });
            },

            loadRootChildren: function () {
                var ui = this;
                if (typeof AutoDocsPublisher === 'undefined' || !AutoDocsPublisher.ajaxUrl) {
                    return;
                }
                var rootInput = A.qs('#autodocs-working-folder-id');
                var root = rootInput ? A.trim(rootInput.value) : '';
                var msg = A.qs('#autodocs-bucket-msg');

                ui.updateBucketsVisibility(!!root);
                if (msg) {
                    msg.textContent = '';
                }

                if (!root) {
                    ui.clearBucketSelects();
                    ui.clearArticlePanels();
                    return;
                }

                A.postFormUrlEncoded(AutoDocsPublisher.ajaxUrl, {
                    action: 'autodocs_list_drive_folders',
                    nonce: AutoDocsPublisher.nonce,
                    parent_id: root
                })
                    .then(function (response) {
                        if (!response || !response.success) {
                            var err =
                                response && response.data && response.data.message
                                    ? response.data.message
                                    : ui.t('folderListError', 'Could not load folders.');
                            if (msg) {
                                msg.textContent = err;
                            }
                            return;
                        }
                        var folders = response.data && response.data.folders ? response.data.folders : [];
                        if (!folders.length) {
                            if (msg) {
                                msg.textContent = ui.t('noFolders', 'No subfolders here.');
                            }
                            ui.clearBucketSelects();
                            return;
                        }
                        ui.fillBucketSelectsFromFolders(folders);
                        if (!A.articleListsBootstrapped) {
                            A.articleListsBootstrapped = true;
                            ui.refreshAllArticlePanels();
                        }
                    })
                    .catch(function () {
                        if (msg) {
                            msg.textContent = ui.t('folderListError', 'Could not load folders.');
                        }
                    });
            },

            refreshArticlePanel: function (panel, folderId, bucketKey, listMode) {
                var ui = this;
                listMode = listMode || '';
                if (!panel) {
                    return;
                }
                panel.innerHTML = '';
                panel.appendChild(
                    A.el('p', { class: 'description', text: ui.t('loadingArticleFolders', 'Loading article folders…') })
                );

                if (!folderId) {
                    panel.innerHTML = '';
                    panel.appendChild(
                        A.el('p', {
                            class: 'description',
                            text: ui.t('articlesTabPickBuckets', 'Choose bucket folders on the Drive & folders tab to list articles here.')
                        })
                    );
                    return;
                }

                var selId = bucketKey === 'new' ? 'autodocs-bucket-new' : 'autodocs-bucket-synced';
                var sel = A.qs('#' + selId);
                var opt = sel ? sel.options[sel.selectedIndex] : null;
                var bucketLabel = opt && opt.value ? opt.text || '' : '';

                var postData = {
                    action: 'autodocs_list_bucket_articles',
                    nonce: AutoDocsPublisher.nonce,
                    bucket_id: folderId,
                    bucket_label: bucketLabel
                };
                if (listMode === 'modified') {
                    postData.list_mode = 'modified';
                }

                A.postFormUrlEncoded(AutoDocsPublisher.ajaxUrl, postData)
                    .then(function (response) {
                        panel.innerHTML = '';
                        if (!response || !response.success) {
                            var err =
                                response && response.data && response.data.message
                                    ? response.data.message
                                    : ui.t('folderListError', 'Could not load folders.');
                            panel.appendChild(A.el('p', { class: 'description', text: err }));
                            return;
                        }
                        var articles = response.data && response.data.articles ? response.data.articles : [];
                        if (!articles.length) {
                            panel.appendChild(
                                A.el('p', {
                                    class: 'description',
                                    text: ui.t('noArticleFolders', 'No article folders in this bucket.')
                                })
                            );
                            return;
                        }

                        panel.appendChild(
                            A.el('p', {
                                class: 'description',
                                text: ui.t(
                                    'articleFoldersHint',
                                    'Subfolders here are treated as articles (each should contain a Google Doc and optional featured image).'
                                )
                            })
                        );

                        var table = A.el('table', { class: 'widefat striped autodocs-articles-table' });
                        var trh = A.el('tr');
                        ['article', 'categoryColumn', 'lastSynced', 'lastModified', 'size', 'actions'].forEach(function (colKey) {
                            var labels = {
                                article: ui.t('article', 'Article'),
                                categoryColumn: ui.t('categoryColumn', 'Categories (from doc)'),
                                lastSynced: ui.t('lastSynced', 'Last synced'),
                                lastModified: ui.t('lastModified', 'Doc modified'),
                                size: ui.t('size', 'Size'),
                                actions: ui.t('actions', 'Actions')
                            };
                            trh.appendChild(A.el('th', { scope: 'col', text: labels[colKey] }));
                        });
                        table.appendChild(A.el('thead', null, [trh]));

                        var tb = A.el('tbody');
                        articles.forEach(function (a) {
                            var tr = A.el('tr', { class: 'autodocs-articles-table__row' });
                            var artTd = A.el('td', { class: 'autodocs-articles-table__td-article' });
                            if (a.thumbnail_url) {
                                artTd.appendChild(
                                    A.el('img', {
                                        class: 'autodocs-articles-table__thumb',
                                        alt: '',
                                        width: '40',
                                        height: '40',
                                        src: a.thumbnail_url
                                    })
                                );
                            }
                            var stack = A.el('div', { class: 'autodocs-articles-table__article-stack' });
                            stack.appendChild(A.el('div', { class: 'autodocs-articles-table__doc-name', text: a.doc_name || '—' }));
                            stack.appendChild(A.el('div', { class: 'autodocs-articles-table__folder-name', text: a.folder_name || '' }));
                            artTd.appendChild(stack);
                            tr.appendChild(artTd);
                            tr.appendChild(A.el('td', { text: a.categories_display || '—' }));
                            var syncLabel = a.last_synced_formatted || '';
                            if (!syncLabel && a.modified) {
                                syncLabel = A.formatIsoDate(a.modified);
                            }
                            tr.appendChild(A.el('td', { text: syncLabel || '—' }));
                            tr.appendChild(A.el('td', { text: A.formatIsoDate(a.modified) }));
                            tr.appendChild(A.el('td', { text: A.formatBytes(a.size) }));
                            var actTd = A.el('td', { class: 'autodocs-articles-table__td-actions' });
                            if (a.web_view_link) {
                                actTd.appendChild(
                                    A.el('a', {
                                        class: 'button button-small',
                                        target: '_blank',
                                        rel: 'noopener noreferrer',
                                        href: a.web_view_link,
                                        text: ui.t('openInDrive', 'Open in Drive')
                                    })
                                );
                                actTd.appendChild(document.createTextNode(' '));
                            }
                            var imp = A.el('button', {
                                type: 'button',
                                class: 'button button-small button-primary autodocs-article-import',
                                text: ui.t('import', 'Import')
                            });
                            var importBucketKey = listMode === 'modified' || bucketKey === 'modified' ? 'synced' : bucketKey;
                            imp.setAttribute('data-folder-id', a.folder_id || '');
                            imp.setAttribute('data-bucket-key', importBucketKey);
                            actTd.appendChild(imp);
                            tr.appendChild(actTd);
                            tb.appendChild(tr);
                        });
                        table.appendChild(tb);
                        panel.appendChild(table);
                    })
                    .catch(function () {
                        panel.innerHTML = '';
                        panel.appendChild(A.el('p', { class: 'description', text: ui.t('folderListError', 'Could not load folders.') }));
                    });
            },

            refreshAllArticlePanels: function () {
                var ui = this;
                this.articlePanelRows.forEach(function (row) {
                    var id = ui.folderIdForArticleKey(row.key);
                    ui.refreshArticlePanel(row.panel, id, row.key, row.listMode || '');
                });
            }
        };
        return bucketUi;
    };
})(window);
