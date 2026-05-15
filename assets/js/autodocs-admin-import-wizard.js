(function (w) {
    'use strict';

    var A = (w.AutoDocsAdmin = w.AutoDocsAdmin || {});

    A.createImportWizard = function () {
        var root = A.qs('#autodocs-import-wizard');
        if (!root) {
            return { open: function () {} };
        }

        var state = {
            step: 1,
            bucketKey: 'new',
            articles: [],
            selected: {},
            activeId: null,
            prepared: {},
            previewTab: 'content',
            search: ''
        };

        var els = {
            root: root,
            lastSync: A.qs('#autodocs-wizard-last-sync'),
            selectList: A.qs('#autodocs-wizard-select-list'),
            selectAll: A.qs('#autodocs-wizard-select-all-page'),
            search: A.qs('#autodocs-wizard-search'),
            selectedNav: A.qs('#autodocs-wizard-selected-nav'),
            selectedCount: A.qs('#autodocs-wizard-selected-count'),
            previewTitle: A.qs('#autodocs-wizard-preview-title'),
            previewPath: A.qs('#autodocs-wizard-preview-path'),
            contentHtml: A.qs('#autodocs-wizard-content-html'),
            docStats: A.qs('#autodocs-wizard-doc-stats'),
            metaDl: A.qs('#autodocs-wizard-meta-dl'),
            imagesPanel: A.qs('#autodocs-wizard-images-panel'),
            driveDl: A.qs('#autodocs-wizard-drive-dl'),
            metaNotice: A.qs('#autodocs-wizard-meta-notice'),
            options: A.qs('#autodocs-wizard-options'),
            confirm: A.qs('#autodocs-wizard-confirm-summary'),
            progress: A.qs('#autodocs-wizard-import-progress'),
            footerCount: A.qs('#autodocs-wizard-footer-count'),
            btnNext: A.qs('#autodocs-wizard-btn-next'),
            btnBack: A.qs('#autodocs-wizard-btn-back'),
            btnPrev: A.qs('#autodocs-wizard-prev-article'),
            btnNextArt: A.qs('#autodocs-wizard-next-article')
        };

        function t(key, fallback) {
            return A.publisherI18n(key, fallback);
        }

        function selectedList() {
            return state.articles.filter(function (a) {
                return !!state.selected[a.folder_id];
            });
        }

        function articleById(id) {
            for (var i = 0; i < state.articles.length; i++) {
                if (state.articles[i].folder_id === id) {
                    return state.articles[i];
                }
            }
            return null;
        }

        function setStep(step) {
            state.step = step;
            A.qsa('[data-wizard-step]', root).forEach(function (sec) {
                var n = parseInt(sec.getAttribute('data-wizard-step'), 10);
                var on = n === step;
                sec.classList.toggle('is-active', on);
                sec.hidden = !on;
            });
            A.qsa('[data-wizard-step-indicator]', root).forEach(function (li) {
                var n = parseInt(li.getAttribute('data-wizard-step-indicator'), 10);
                li.classList.remove('is-active', 'is-done');
                if (n < step) {
                    li.classList.add('is-done');
                } else if (n === step) {
                    li.classList.add('is-active');
                }
            });
            if (els.btnBack) {
                els.btnBack.hidden = step <= 1;
            }
            if (els.btnNext) {
                if (step === 1) {
                    els.btnNext.textContent = t('wizardContinue', 'Continue to Preview');
                } else if (step === 2) {
                    els.btnNext.textContent = t('wizardStepConfirm', 'Confirm & Import');
                } else {
                    var n = selectedList().length;
                    els.btnNext.textContent =
                        n === 1
                            ? t('wizardImportOne', 'Import Article')
                            : t('wizardImportN', 'Import %s Articles').replace('%s', String(n));
                }
            }
            updateFooterCount();
            if (step === 2) {
                renderSelectedNav();
                var list = selectedList();
                if (list.length && !state.activeId) {
                    state.activeId = list[0].folder_id;
                }
                if (state.activeId) {
                    loadPreview(state.activeId);
                }
                renderOptionsOnce();
            }
            if (step === 3) {
                renderConfirm();
            }
        }

        function updateFooterCount() {
            var sel = selectedList().length;
            var total = state.articles.length;
            if (els.footerCount) {
                els.footerCount.textContent = t('wizardOfSelected', '%1$s of %2$s selected')
                    .replace('%1$s', String(sel))
                    .replace('%2$s', String(total));
            }
        }

        function renderSelectList() {
            if (!els.selectList) {
                return;
            }
            els.selectList.innerHTML = '';
            var q = state.search.toLowerCase();
            var shown = 0;
            state.articles.forEach(function (a) {
                var title = (a.doc_name || a.folder_name || '').toLowerCase();
                var path = (a.path_label || a.folder_name || '').toLowerCase();
                if (q && title.indexOf(q) === -1 && path.indexOf(q) === -1) {
                    return;
                }
                shown++;
                var row = A.el('label', { class: 'autodocs-import-wizard__select-row' });
                var cb = A.el('input', { type: 'checkbox' });
                cb.checked = !!state.selected[a.folder_id];
                cb.addEventListener('change', function () {
                    if (cb.checked) {
                        state.selected[a.folder_id] = true;
                    } else {
                        delete state.selected[a.folder_id];
                    }
                    updateFooterCount();
                });
                row.appendChild(cb);
                var body = A.el('div', { class: 'autodocs-import-wizard__select-row-body' });
                body.appendChild(A.el('strong', { text: a.doc_name || a.folder_name || '—' }));
                body.appendChild(A.el('span', { class: 'description', text: a.path_label || a.folder_name || '' }));
                row.appendChild(body);
                els.selectList.appendChild(row);
            });
            if (!shown) {
                els.selectList.appendChild(A.el('p', { class: 'description', text: t('noArticleFolders', 'No article folders in this bucket.') }));
            }
            if (els.selectAll) {
                var allOnPage = state.articles.length > 0;
                state.articles.forEach(function (a) {
                    if (!state.selected[a.folder_id]) {
                        allOnPage = false;
                    }
                });
                els.selectAll.checked = allOnPage && state.articles.length > 0;
            }
        }

        function renderSelectedNav() {
            if (!els.selectedNav) {
                return;
            }
            els.selectedNav.innerHTML = '';
            var list = selectedList();
            if (els.selectedCount) {
                els.selectedCount.textContent = t('wizardOfSelected', '%1$s of %2$s selected')
                    .replace('%1$s', String(list.length))
                    .replace('%2$s', String(state.articles.length));
            }
            list.forEach(function (a) {
                var li = A.el('li');
                var btn = A.el('button', {
                    type: 'button',
                    class: 'autodocs-import-wizard__selected-item' + (a.folder_id === state.activeId ? ' is-active' : ''),
                    text: a.doc_name || a.folder_name || '—'
                });
                btn.addEventListener('click', function () {
                    state.activeId = a.folder_id;
                    renderSelectedNav();
                    loadPreview(a.folder_id);
                });
                li.appendChild(btn);
                els.selectedNav.appendChild(li);
            });
        }

        function setPreviewTab(tab) {
            state.previewTab = tab;
            A.qsa('[data-wizard-preview-tab]', root).forEach(function (btn) {
                var on = btn.getAttribute('data-wizard-preview-tab') === tab;
                btn.classList.toggle('is-active', on);
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            A.qsa('[data-wizard-preview-panel]', root).forEach(function (panel) {
                var on = panel.getAttribute('data-wizard-preview-panel') === tab;
                panel.classList.toggle('is-active', on);
                panel.hidden = !on;
            });
        }

        function renderPreview(data, article) {
            if (els.previewTitle) {
                els.previewTitle.textContent = (data.defaults && data.defaults.post_title) || article.doc_name || '';
            }
            if (els.previewPath) {
                els.previewPath.textContent = article.path_label || article.folder_name || '';
            }
            if (els.metaNotice) {
                if (data.has_meta_block) {
                    els.metaNotice.hidden = false;
                    els.metaNotice.textContent = t(
                        'wizardMetaDetected',
                        'META detected in document. Title, excerpt, categories and other settings will be imported from the document\'s META block.'
                    );
                } else {
                    els.metaNotice.hidden = true;
                }
            }
            if (els.contentHtml) {
                els.contentHtml.innerHTML = data.content_html_preview || '<p class="description">' + t('contentPreview', 'Content preview') + '</p>';
            }
            if (els.docStats && data.doc_stats) {
                var s = data.doc_stats;
                els.docStats.innerHTML = '';
                [
                    ['Type', s.type],
                    ['Modified', s.modified_formatted || s.modified],
                    ['Size', A.formatBytes(s.size)],
                    ['Words', String(s.word_count || 0)],
                    ['Images', String(s.image_count || 0)]
                ].forEach(function (pair) {
                    var dt = A.el('dt', { text: pair[0] });
                    var dd = A.el('dd', { text: pair[1] || '—' });
                    els.docStats.appendChild(dt);
                    els.docStats.appendChild(dd);
                });
            }
            if (els.metaDl) {
                els.metaDl.innerHTML = '';
                var meta = data.meta_block || {};
                var keys = data.meta_keys && data.meta_keys.length ? data.meta_keys : Object.keys(meta);
                if (!keys.length) {
                    els.metaDl.appendChild(A.el('p', { class: 'description', text: t('wizardNoMeta', 'No META block found in this document.') }));
                } else {
                    keys.forEach(function (k) {
                        els.metaDl.appendChild(A.el('dt', { text: k }));
                        els.metaDl.appendChild(A.el('dd', { text: String(meta[k]) }));
                    });
                }
            }
            if (els.imagesPanel) {
                els.imagesPanel.innerHTML = '';
                var fp = data.featured_preview;
                if (fp && fp.thumbnail_url) {
                    var fig = A.el('figure', { class: 'autodocs-import-wizard__feat' });
                    fig.appendChild(A.el('img', { src: fp.thumbnail_url, alt: fp.name || '' }));
                    fig.appendChild(A.el('figcaption', { text: fp.name || '' }));
                    els.imagesPanel.appendChild(fig);
                }
                (data.drive_files || []).forEach(function (f) {
                    var row = A.el('div', { class: 'autodocs-import-wizard__file-row' });
                    row.appendChild(A.el('span', { text: f.name }));
                    if (f.web_view_link) {
                        row.appendChild(A.el('a', { href: f.web_view_link, target: '_blank', rel: 'noopener', text: t('openInDrive', 'Open in Drive') }));
                    }
                    els.imagesPanel.appendChild(row);
                });
                if (!els.imagesPanel.childElementCount) {
                    els.imagesPanel.appendChild(A.el('p', { class: 'description', text: t('wizardNoFiles', 'No additional files in this folder.') }));
                }
            }
            if (els.driveDl) {
                els.driveDl.innerHTML = '';
                els.driveDl.appendChild(A.el('dt', { text: 'Folder' }));
                els.driveDl.appendChild(A.el('dd', { text: data.folder_name || article.folder_name || '' }));
                if (article.web_view_link) {
                    els.driveDl.appendChild(A.el('dt', { text: 'Drive' }));
                    var dd = A.el('dd');
                    dd.appendChild(A.el('a', { href: article.web_view_link, target: '_blank', rel: 'noopener', text: t('openInDrive', 'Open in Drive') }));
                    els.driveDl.appendChild(dd);
                }
            }
        }

        function loadPreview(folderId) {
            var article = articleById(folderId);
            if (!article || typeof AutoDocsPublisher === 'undefined') {
                return;
            }
            if (state.prepared[folderId]) {
                renderPreview(state.prepared[folderId], article);
                return;
            }
            if (els.contentHtml) {
                els.contentHtml.innerHTML = '<p class="description">' + t('preparingImport', 'Loading import options…') + '</p>';
            }
            var bk = state.bucketKey === 'modified' ? 'synced' : state.bucketKey;
            A.postFormUrlEncoded(AutoDocsPublisher.ajaxUrl, {
                action: 'autodocs_prepare_import_new',
                nonce: AutoDocsPublisher.importNonce,
                folder_id: folderId,
                bucket_key: bk
            })
                .then(function (res) {
                    if (!res || !res.success) {
                        return;
                    }
                    state.prepared[folderId] = res.data;
                    renderPreview(res.data, article);
                })
                .catch(function () {});
        }

        var optionsBuilt = false;
        var optionControls = {};

        function renderOptionsOnce() {
            if (optionsBuilt || !els.options) {
                return;
            }
            optionsBuilt = true;
            var wrap = els.options;
            wrap.innerHTML = '';

            function field(label, node) {
                var f = A.el('div', { class: 'autodocs-import-wizard__option-field' });
                f.appendChild(A.el('label', { text: label }));
                f.appendChild(node);
                wrap.appendChild(f);
                return node;
            }

            var ptype = A.el('select');
            field(t('postType', 'Post type'), ptype);
            optionControls.post_type = ptype;

            var pstatus = A.el('select');
            ['draft', 'publish', 'pending', 'private'].forEach(function (s) {
                pstatus.appendChild(A.el('option', { value: s, text: s }));
            });
            field(t('postStatus', 'Status'), pstatus);
            optionControls.post_status = pstatus;

            var author = A.el('select');
            (AutoDocsPublisher.authors || []).forEach(function (u) {
                var opt = A.el('option', { value: String(u.id), text: u.name });
                if (String(u.id) === String(AutoDocsPublisher.currentUserId)) {
                    opt.selected = true;
                }
                author.appendChild(opt);
            });
            field(t('wizardAuthor', 'Author'), author);
            optionControls.post_author = author;

            var catMode = A.el('select');
            catMode.appendChild(A.el('option', { value: 'doc', text: t('wizardFromMeta', 'From document (META)') }));
            catMode.appendChild(A.el('option', { value: 'manual', text: t('wizardManualCategories', 'Choose WordPress categories') }));
            field(t('wizardCategoryAssignment', 'Category assignment'), catMode);
            optionControls.categories_mode = catMode;

            var tagMode = A.el('select');
            tagMode.appendChild(A.el('option', { value: 'doc', text: t('wizardFromMeta', 'From document (META)') }));
            tagMode.appendChild(A.el('option', { value: 'manual', text: t('wizardManualTags', 'Enter tags manually') }));
            field(t('wizardTagsAssignment', 'Tags assignment'), tagMode);
            optionControls.tags_mode = tagMode;

            var tags = A.el('input', { type: 'text', class: 'large-text' });
            field(t('tagsLabel', 'Tags'), tags);
            optionControls.tags = tags;

            var excerpt = A.el('label', { class: 'autodocs-import-wizard__check' });
            var excerptCb = A.el('input', { type: 'checkbox' });
            excerptCb.checked = true;
            excerpt.appendChild(excerptCb);
            excerpt.appendChild(document.createTextNode(' ' + t('wizardImportExcerpt', 'Import excerpt')));
            wrap.appendChild(excerpt);
            optionControls.use_doc_excerpt = excerptCb;

            var move = A.el('label', { class: 'autodocs-import-wizard__check' });
            var moveCb = A.el('input', { type: 'checkbox' });
            moveCb.checked = state.bucketKey === 'new';
            move.appendChild(moveCb);
            move.appendChild(document.createTextNode(' ' + t('wizardMoveSynced', 'Move folder to Synced after import')));
            wrap.appendChild(move);
            optionControls.move_to_synced = moveCb;
        }

        function collectOptions() {
            return {
                post_type: optionControls.post_type ? optionControls.post_type.value : 'post',
                post_status: optionControls.post_status ? optionControls.post_status.value : 'draft',
                post_author: optionControls.post_author ? optionControls.post_author.value : '',
                categories_mode: optionControls.categories_mode ? optionControls.categories_mode.value : 'doc',
                tags_mode: optionControls.tags_mode ? optionControls.tags_mode.value : 'doc',
                tags: optionControls.tags ? optionControls.tags.value : '',
                use_doc_excerpt: optionControls.use_doc_excerpt ? optionControls.use_doc_excerpt.checked : true,
                move_to_synced: optionControls.move_to_synced ? optionControls.move_to_synced.checked : false,
                acf_body_field: '',
                acf_body_field_custom: ''
            };
        }

        function renderConfirm() {
            if (!els.confirm) {
                return;
            }
            var opts = collectOptions();
            var list = selectedList();
            els.confirm.innerHTML = '';
            var ul = A.el('ul', { class: 'autodocs-import-wizard__confirm-list' });
            list.forEach(function (a) {
                ul.appendChild(A.el('li', { text: a.doc_name || a.folder_name }));
            });
            els.confirm.appendChild(ul);
            els.confirm.appendChild(
                A.el('p', {
                    class: 'description',
                    text:
                        t('postType', 'Post type') +
                        ': ' +
                        opts.post_type +
                        ' · ' +
                        t('postStatus', 'Status') +
                        ': ' +
                        opts.post_status
                })
            );
        }

        function runImport() {
            var list = selectedList();
            if (!list.length || typeof AutoDocsPublisher === 'undefined') {
                return;
            }
            var opts = collectOptions();
            var ids = list.map(function (a) {
                return a.folder_id;
            });
            if (els.btnNext) {
                els.btnNext.disabled = true;
            }
            if (els.progress) {
                els.progress.hidden = false;
                els.progress.textContent = t('wizardImporting', 'Importing…');
            }
            A.postFormUrlEncoded(AutoDocsPublisher.ajaxUrl, {
                action: 'autodocs_bulk_import_folders',
                nonce: AutoDocsPublisher.importNonce,
                folder_ids: JSON.stringify(ids),
                bucket_key: state.bucketKey === 'modified' ? 'synced' : state.bucketKey,
                post_type: opts.post_type,
                post_status: opts.post_status,
                post_author: opts.post_author,
                categories_mode: opts.categories_mode,
                tags_mode: opts.tags_mode,
                tags: opts.tags,
                use_doc_excerpt: opts.use_doc_excerpt ? '1' : '',
                move_to_synced: opts.move_to_synced ? '1' : '',
                acf_body_field: opts.acf_body_field,
                acf_body_field_custom: opts.acf_body_field_custom
            })
                .then(function (res) {
                    if (!res || !res.success) {
                        if (els.progress) {
                            els.progress.textContent = t('importFailed', 'Import failed.');
                        }
                        return;
                    }
                    var d = res.data || {};
                    if (els.progress) {
                        els.progress.textContent =
                            t('wizardDone', 'Import complete.') +
                            ' ' +
                            String(d.imported || 0) +
                            ' OK, ' +
                            String(d.failed || 0) +
                            ' failed.';
                    }
                    if (typeof w.autodocsBucketUiRefreshArticles === 'function') {
                        w.autodocsBucketUiRefreshArticles();
                    }
                    A.refreshDashboardSidebar(true);
                })
                .catch(function () {
                    if (els.progress) {
                        els.progress.textContent = t('importFailed', 'Import failed.');
                    }
                })
                .finally(function () {
                    if (els.btnNext) {
                        els.btnNext.disabled = false;
                    }
                });
        }

        function open(opts) {
            opts = opts || {};
            state.bucketKey = opts.bucketKey || 'new';
            state.articles = opts.articles || [];
            state.selected = {};
            state.prepared = {};
            state.activeId = null;
            state.search = '';
            state.step = 1;
            optionsBuilt = false;
            if (opts.preselect && opts.preselect.length) {
                opts.preselect.forEach(function (id) {
                    state.selected[id] = true;
                });
            }
            if (opts.singleFolderId) {
                state.selected[opts.singleFolderId] = true;
            }
            if (els.lastSync) {
                var ls = A.qs('#autodocs-sidebar-last-sync');
                els.lastSync.textContent = ls && ls.textContent ? ls.textContent : '';
            }
            root.hidden = false;
            root.setAttribute('aria-hidden', 'false');
            document.body.classList.add('autodocs-import-wizard-open');
            renderSelectList();
            setStep(opts.startStep && opts.startStep === 2 && selectedList().length ? 2 : 1);
        }

        function close() {
            root.hidden = true;
            root.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('autodocs-import-wizard-open');
        }

        A.qsa('[data-autodocs-wizard-close]', root).forEach(function (btn) {
            btn.addEventListener('click', close);
        });

        if (els.search) {
            els.search.addEventListener('input', function () {
                state.search = els.search.value || '';
                renderSelectList();
            });
        }

        if (els.selectAll) {
            els.selectAll.addEventListener('change', function () {
                state.articles.forEach(function (a) {
                    if (els.selectAll.checked) {
                        state.selected[a.folder_id] = true;
                    } else {
                        delete state.selected[a.folder_id];
                    }
                });
                renderSelectList();
                updateFooterCount();
            });
        }

        A.qs('#autodocs-wizard-clear-selected', root).addEventListener('click', function () {
            state.selected = {};
            state.activeId = null;
            renderSelectList();
            renderSelectedNav();
            updateFooterCount();
        });

        A.qsa('[data-wizard-preview-tab]', root).forEach(function (btn) {
            btn.addEventListener('click', function () {
                setPreviewTab(btn.getAttribute('data-wizard-preview-tab'));
            });
        });

        if (els.btnNext) {
            els.btnNext.addEventListener('click', function () {
                if (state.step === 1) {
                    if (!selectedList().length) {
                        window.alert(t('wizardSelectOne', 'Select at least one article.'));
                        return;
                    }
                    state.activeId = selectedList()[0].folder_id;
                    setStep(2);
                    return;
                }
                if (state.step === 2) {
                    setStep(3);
                    return;
                }
                runImport();
            });
        }

        if (els.btnBack) {
            els.btnBack.addEventListener('click', function () {
                if (state.step > 1) {
                    setStep(state.step - 1);
                }
            });
        }

        if (els.btnPrev) {
            els.btnPrev.addEventListener('click', function () {
                var list = selectedList();
                var idx = 0;
                for (var i = 0; i < list.length; i++) {
                    if (list[i].folder_id === state.activeId) {
                        idx = i;
                        break;
                    }
                }
                idx = (idx - 1 + list.length) % list.length;
                state.activeId = list[idx].folder_id;
                renderSelectedNav();
                loadPreview(state.activeId);
            });
        }

        if (els.btnNextArt) {
            els.btnNextArt.addEventListener('click', function () {
                var list = selectedList();
                var idx = 0;
                for (var i = 0; i < list.length; i++) {
                    if (list[i].folder_id === state.activeId) {
                        idx = i;
                        break;
                    }
                }
                idx = (idx + 1) % list.length;
                state.activeId = list[idx].folder_id;
                renderSelectedNav();
                loadPreview(state.activeId);
            });
        }

        return { open: open, close: close };
    };

    A.publisherI18n = function (key, fallback) {
        if (typeof AutoDocsPublisher !== 'undefined' && AutoDocsPublisher.i18n && AutoDocsPublisher.i18n[key]) {
            return AutoDocsPublisher.i18n[key];
        }
        return fallback;
    };
})(window);
