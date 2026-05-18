(function (w) {
    'use strict';

    var A = (w.AutoDocsAdmin = w.AutoDocsAdmin || {});

    /**
     * @param {HTMLElement} panel
     * @param {object} data
     * @param {function(string, string): string} t
     */
    A.renderImportForm = function (panel, data, t) {
        var d = data.defaults || {};
        var bucketKey = data.bucket_key || 'new';
        panel.innerHTML = '';
        A.showEl(panel, true);

        var fp = data.featured_preview;
        if (fp && fp.thumbnail_url) {
            var fig = A.el('div', { class: 'autodocs-import-aside__thumb-wrap' });
            fig.appendChild(
                A.el('img', {
                    class: 'autodocs-import-aside__thumb',
                    alt: fp.name || t('previewThumb', 'Featured preview'),
                    src: fp.thumbnail_url
                })
            );
            panel.appendChild(fig);
        }

        if (data.existing_post_id) {
            panel.appendChild(
                A.el('p', {
                    class: 'description',
                    text: t('existingPostNotice', 'This folder is already linked to a post; saving will update that post.')
                })
            );
        }

        var meta = data.meta_block || {};
        var metaSkip = { categories: true, tags: true };
        var mkeys = data.meta_keys && data.meta_keys.length ? data.meta_keys : Object.keys(meta);
        var mkeysFiltered = mkeys.filter(function (k) {
            return !metaSkip[k] && Object.prototype.hasOwnProperty.call(meta, k);
        });
        if (mkeysFiltered.length) {
            panel.appendChild(A.el('p', { class: 'description', text: t('metaFromDoc', 'Meta from document') }));
            var dl = A.el('dl', { class: 'autodocs-meta-dl' });
            mkeysFiltered.forEach(function (k) {
                dl.appendChild(A.el('dt', { text: k }));
                dl.appendChild(A.el('dd', { text: String(meta[k]) }));
            });
            panel.appendChild(dl);
        }

        if (data.body_preview) {
            panel.appendChild(A.el('p', { class: 'description', text: t('contentPreview', 'Content preview') }));
            panel.appendChild(
                A.el('div', { class: 'autodocs-import-body-preview', text: String(data.body_preview) })
            );
        }

        var titleInput = A.el('input', { type: 'text', class: 'large-text', value: d.post_title || '' });
        panel.appendChild(
            A.el('div', { class: 'autodocs-import-field' }, [
                A.el('label', { text: t('postTitle', 'Post title') }),
                titleInput
            ])
        );

        var slugInput = A.el('input', { type: 'text', class: 'large-text code', value: d.post_name || '' });
        panel.appendChild(
            A.el('div', { class: 'autodocs-import-field' }, [
                A.el('label', { text: t('postSlug', 'URL slug') }),
                slugInput
            ])
        );

        var ptypeSelect = A.el('select');
        (data.post_types || []).forEach(function (pt) {
            var opt = A.el('option', { value: pt.name, text: pt.label || pt.name });
            if (pt.name === (d.post_type || 'post')) {
                opt.selected = true;
            }
            ptypeSelect.appendChild(opt);
        });
        panel.appendChild(
            A.el('div', { class: 'autodocs-import-field' }, [
                A.el('label', { text: t('postType', 'Post type') }),
                ptypeSelect
            ])
        );

        var cv = data.acf_select_custom_value || '__autodocs_custom__';
        var svLegacy = data.acf_select_site_default_value || '__autodocs_site_default__';
        var defRaw = d.acf_body_field != null ? String(d.acf_body_field) : '';
        if (defRaw === svLegacy) {
            defRaw = '';
        }
        var defAcf = defRaw;
        var defAcfCustom = d.acf_body_field_custom != null ? String(d.acf_body_field_custom) : '';

        var acfIdBase = 'autodocs-import-acf-' + String(data.folder_id || '').replace(/[^A-Za-z0-9_-]/g, '');
        var acfSelectId = acfIdBase + '-body-select';
        var acfCustomId = acfIdBase + '-body-custom';

        var acfSelect = A.el('select', { id: acfSelectId, class: 'large-text autodocs-import-acf-body-select' });
        var acfCustom = A.el('input', {
            type: 'text',
            class: 'large-text code autodocs-import-acf-body-custom',
            id: acfCustomId,
            value: defAcfCustom,
            autocomplete: 'off',
            placeholder: t('importAcfOtherPlaceholder', 'ACF field key or name')
        });

        function setAcfBodySelectValue(select, value) {
            var opts = select.querySelectorAll('option');
            var found = false;
            for (var i = 0; i < opts.length; i++) {
                opts[i].selected = opts[i].value === value;
                if (opts[i].selected) {
                    found = true;
                }
            }
            return found;
        }

        function refillAcfSelectOptions(choices) {
            acfSelect.innerHTML = '';
            acfSelect.appendChild(A.el('option', { value: '', text: t('importPostContentOnly', 'Post content (editor) only') }));
            var list = choices || [];
            if (list.length) {
                var currentOg = null;
                var groupLabelFallback = t('acfFieldGroup', 'Field group');
                list.forEach(function (row) {
                    var grp = row.group != null && row.group !== '' ? String(row.group) : groupLabelFallback;
                    if (!currentOg || currentOg.label !== grp) {
                        currentOg = document.createElement('optgroup');
                        currentOg.label = grp;
                        acfSelect.appendChild(currentOg);
                    }
                    currentOg.appendChild(A.el('option', { value: String(row.value), text: String(row.label) }));
                });
            }
            acfSelect.appendChild(A.el('option', { value: cv, text: t('importAcfOther', 'Other field key or name…') }));
        }

        function syncAcfCustomVisibility() {
            acfCustom.style.display = acfSelect.value === cv ? '' : 'none';
        }

        refillAcfSelectOptions(data.acf_body_field_choices || []);

        if (
            !A.applyAcfBodyFieldDefault(acfSelect, acfCustom, cv, defAcf, defAcfCustom)
        ) {
            if (!setAcfBodySelectValue(acfSelect, defAcf)) {
                setAcfBodySelectValue(acfSelect, '');
            }
        }
        syncAcfCustomVisibility();
        acfSelect.addEventListener('change', function () {
            if (acfSelect.value !== cv) {
                acfCustom.value = '';
            }
            syncAcfCustomVisibility();
        });

        function refreshAcfChoicesForPostType() {
            if (typeof AutoDocsPublisher === 'undefined' || !AutoDocsPublisher.ajaxUrl || !AutoDocsPublisher.importNonce) {
                return;
            }
            var pt = (ptypeSelect.value || 'post').trim() || 'post';
            acfSelect.disabled = true;
            A.postFormUrlEncoded(AutoDocsPublisher.ajaxUrl, {
                action: 'autodocs_import_acf_body_choices',
                nonce: AutoDocsPublisher.importNonce,
                post_type: pt
            })
                .then(function (res) {
                    if (!res || !res.success || !res.data) {
                        return;
                    }
                    if (typeof res.data.acf_select_custom_value === 'string' && res.data.acf_select_custom_value !== '') {
                        cv = res.data.acf_select_custom_value;
                    }
                    refillAcfSelectOptions(res.data.acf_body_field_choices || []);
                    A.applyAcfBodyFieldDefault(
                        acfSelect,
                        acfCustom,
                        cv,
                        res.data.default_acf_body_field || '',
                        res.data.default_acf_body_field_custom || ''
                    );
                    syncAcfCustomVisibility();
                })
                .catch(function () {})
                .finally(function () {
                    acfSelect.disabled = false;
                });
        }

        var acfPostTypeRefreshTimer = null;
        function scheduleAcfRefreshForPostType() {
            if (acfPostTypeRefreshTimer) {
                clearTimeout(acfPostTypeRefreshTimer);
            }
            acfPostTypeRefreshTimer = setTimeout(function () {
                acfPostTypeRefreshTimer = null;
                refreshAcfChoicesForPostType();
            }, 200);
        }
        ptypeSelect.addEventListener('change', scheduleAcfRefreshForPostType);
        ptypeSelect.addEventListener('input', scheduleAcfRefreshForPostType);

        var acfWrap = A.el('div', { class: 'autodocs-import-field autodocs-import-field--acf-body' });
        acfWrap.appendChild(A.el('label', { for: acfSelectId, text: t('importBodyTargetLabel', 'Imported HTML goes to') }));
        acfWrap.appendChild(acfSelect);
        acfWrap.appendChild(acfCustom);
        acfWrap.appendChild(
            A.el('p', {
                class: 'description',
                text: t(
                    'importAcfBodyHint',
                    'First option saves HTML to the post editor. Then choose an ACF WYSIWYG, textarea, or code field, or Other to type a field key.'
                )
            })
        );
        panel.appendChild(acfWrap);

        var statuses = ['draft', 'publish', 'pending', 'private', 'future'];
        var pstSelect = A.el('select');
        statuses.forEach(function (s) {
            var opt = A.el('option', { value: s, text: s });
            if (s === (d.post_status || 'draft')) {
                opt.selected = true;
            }
            pstSelect.appendChild(opt);
        });
        panel.appendChild(
            A.el('div', { class: 'autodocs-import-field' }, [
                A.el('label', { text: t('postStatus', 'Status') }),
                pstSelect
            ])
        );

        var exInput = A.el('textarea', { rows: '3', class: 'large-text', value: d.post_excerpt || '' });
        panel.appendChild(
            A.el('div', { class: 'autodocs-import-field' }, [
                A.el('label', { text: t('excerpt', 'Excerpt') }),
                exInput
            ])
        );

        var catMode = d.categories_mode === 'manual' ? 'manual' : 'doc';
        var catModeDoc = A.el('input', { type: 'radio', name: 'autodocs-cat-mode', value: 'doc' });
        var catModeMan = A.el('input', { type: 'radio', name: 'autodocs-cat-mode', value: 'manual' });
        if (catMode === 'manual') {
            catModeMan.checked = true;
        } else {
            catModeDoc.checked = true;
        }
        var catModeField = A.el('div', { class: 'autodocs-import-field autodocs-import-field--modes' });
        catModeField.appendChild(A.el('label', { text: t('categoriesLabel', 'Categories') }));
        var catRowDoc = A.el('label', { class: 'autodocs-import-mode-row' });
        catRowDoc.appendChild(catModeDoc);
        catRowDoc.appendChild(document.createTextNode(' ' + t('categoriesFromDoc', 'Use categories from document meta')));
        catModeField.appendChild(catRowDoc);
        var catRowMan = A.el('label', { class: 'autodocs-import-mode-row' });
        catRowMan.appendChild(catModeMan);
        catRowMan.appendChild(document.createTextNode(' ' + t('categoriesManual', 'Choose WordPress categories below')));
        catModeField.appendChild(catRowMan);
        panel.appendChild(catModeField);

        var docCats = data.doc_categories_preview && data.doc_categories_preview.length ? data.doc_categories_preview : [];
        var docCatPreviewWrap = A.el('div', { class: 'autodocs-import-doc-preview autodocs-import-doc-preview--cats' });
        docCatPreviewWrap.appendChild(
            A.el('p', { class: 'description autodocs-import-doc-preview__label', text: t('docCategoriesFromMeta', 'From document (will be applied on save)') })
        );
        var docCatChips = A.el('div', { class: 'autodocs-import-doc-preview__chips' });
        if (docCats.length) {
            docCats.forEach(function (label) {
                docCatChips.appendChild(A.el('span', { class: 'autodocs-import-doc-preview__chip', text: String(label) }));
            });
        } else {
            docCatChips.appendChild(
                A.el('span', { class: 'autodocs-import-doc-preview__empty', text: t('docMetaListEmpty', 'None listed in document meta for this field.') })
            );
        }
        docCatPreviewWrap.appendChild(docCatChips);
        panel.appendChild(docCatPreviewWrap);

        var catsSelect = A.el('select', { size: '8', class: 'autodocs-category-multiselect' });
        catsSelect.multiple = true;
        (data.categories || []).forEach(function (c) {
            var opt = A.el('option', { value: String(c.id), text: c.name });
            var defCats = d.categories || [];
            if (defCats.indexOf(c.id) !== -1) {
                opt.selected = true;
            }
            catsSelect.appendChild(opt);
        });
        var catWrap = A.el('div', { class: 'autodocs-import-field autodocs-import-field--manual-cats' });
        catWrap.appendChild(
            A.el('p', {
                class: 'description',
                text: t('categoriesHint', 'Hold Ctrl (Windows) or Command (Mac) to select multiple.')
            })
        );
        catWrap.appendChild(catsSelect);
        panel.appendChild(catWrap);
        A.showEl(catWrap, catMode === 'manual');

        function syncCatWrap() {
            var manual = catModeMan.checked;
            A.showEl(catWrap, manual);
            A.showEl(docCatPreviewWrap, !manual);
        }
        catModeDoc.addEventListener('change', syncCatWrap);
        catModeMan.addEventListener('change', syncCatWrap);

        var tagMode = d.tags_mode === 'manual' ? 'manual' : 'doc';
        var tagModeDoc = A.el('input', { type: 'radio', name: 'autodocs-tag-mode', value: 'doc' });
        var tagModeMan = A.el('input', { type: 'radio', name: 'autodocs-tag-mode', value: 'manual' });
        if (tagMode === 'manual') {
            tagModeMan.checked = true;
        } else {
            tagModeDoc.checked = true;
        }
        var tagModeField = A.el('div', { class: 'autodocs-import-field autodocs-import-field--modes' });
        tagModeField.appendChild(A.el('label', { text: t('tagsLabel', 'Tags') }));
        var tagRowDoc = A.el('label', { class: 'autodocs-import-mode-row' });
        tagRowDoc.appendChild(tagModeDoc);
        tagRowDoc.appendChild(document.createTextNode(' ' + t('tagsFromDoc', 'Use tags from document meta')));
        tagModeField.appendChild(tagRowDoc);
        var tagRowMan = A.el('label', { class: 'autodocs-import-mode-row' });
        tagRowMan.appendChild(tagModeMan);
        tagRowMan.appendChild(document.createTextNode(' ' + t('tagsManual', 'Enter tags manually (comma-separated)')));
        tagModeField.appendChild(tagRowMan);
        panel.appendChild(tagModeField);

        var docTags = data.doc_tags_preview && data.doc_tags_preview.length ? data.doc_tags_preview : [];
        var docTagPreviewWrap = A.el('div', { class: 'autodocs-import-doc-preview autodocs-import-doc-preview--tags' });
        docTagPreviewWrap.appendChild(
            A.el('p', { class: 'description autodocs-import-doc-preview__label', text: t('docTagsFromMeta', 'From document (will be applied on save)') })
        );
        var docTagChips = A.el('div', { class: 'autodocs-import-doc-preview__chips' });
        if (docTags.length) {
            docTags.forEach(function (label) {
                docTagChips.appendChild(A.el('span', { class: 'autodocs-import-doc-preview__chip', text: String(label) }));
            });
        } else {
            docTagChips.appendChild(
                A.el('span', { class: 'autodocs-import-doc-preview__empty', text: t('docMetaListEmpty', 'None listed in document meta for this field.') })
            );
        }
        docTagPreviewWrap.appendChild(docTagChips);
        panel.appendChild(docTagPreviewWrap);

        var tagsInput = A.el('input', { type: 'text', class: 'large-text', value: d.tags || '' });
        var tagWrap = A.el('div', { class: 'autodocs-import-field autodocs-import-field--manual-tags' });
        tagWrap.appendChild(tagsInput);
        panel.appendChild(tagWrap);
        A.showEl(tagWrap, tagMode === 'manual');

        function syncTagWrap() {
            var manual = tagModeMan.checked;
            A.showEl(tagWrap, manual);
            A.showEl(docTagPreviewWrap, !manual);
        }
        tagModeDoc.addEventListener('change', syncTagWrap);
        tagModeMan.addEventListener('change', syncTagWrap);

        var moveCheckbox = null;
        if (bucketKey === 'new') {
            var moveId = 'autodocs-move-synced-' + String(data.folder_id || '').replace(/[^A-Za-z0-9_-]/g, '');
            moveCheckbox = A.el('input', { type: 'checkbox', id: moveId });
            if (data.synced_bucket_set) {
                moveCheckbox.checked = true;
            }
            var moveLabel = A.el('label', { for: moveId, text: t('moveToSynced', 'Move folder to Synced in Drive after save') });
            var moveP = A.el('p', { class: 'autodocs-import-field' });
            moveP.appendChild(moveCheckbox);
            moveP.appendChild(document.createTextNode(' '));
            moveP.appendChild(moveLabel);
            panel.appendChild(moveP);
        }

        var acts = A.el('div', { class: 'autodocs-import-actions' });
        var saveBtn = A.el('button', {
            type: 'button',
            class: 'button button-primary autodocs-import-save',
            text: t('savePost', 'Save as WordPress post')
        });
        var cancelBtn = A.el('button', {
            type: 'button',
            class: 'button autodocs-import-cancel',
            text: t('cancel', 'Cancel')
        });
        acts.appendChild(saveBtn);
        acts.appendChild(cancelBtn);
        panel.appendChild(acts);

        syncCatWrap();
        syncTagWrap();

        saveBtn.dataset.folderId = data.folder_id || '';
        saveBtn.dataset.bucketKey = bucketKey;
        saveBtn._autodocsImportEls = {
            post_title: titleInput,
            post_name: slugInput,
            post_type: ptypeSelect,
            post_status: pstSelect,
            post_excerpt: exInput,
            acf_body_field: acfSelect,
            acf_body_field_custom: acfCustom,
            categories: catsSelect,
            tags: tagsInput,
            move: moveCheckbox,
            catModeDoc: catModeDoc,
            catModeMan: catModeMan,
            tagModeDoc: tagModeDoc,
            tagModeMan: tagModeMan
        };
    };
})(window);
