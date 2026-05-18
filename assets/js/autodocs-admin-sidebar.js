(function (w) {
    'use strict';

    var A = (w.AutoDocsAdmin = w.AutoDocsAdmin || {});

    A.refreshDashboardSidebar = function (includeCounts) {
        if (typeof AutoDocsDashboard === 'undefined' || !AutoDocsDashboard.ajaxUrl) {
            return;
        }
        var dbi = AutoDocsDashboard.i18n || {};
        function t(k, fb) {
            return dbi[k] || fb;
        }
        var payload = {
            action: 'autodocs_sidebar_snapshot',
            nonce: AutoDocsDashboard.nonce
        };
        if (includeCounts) {
            payload.include_counts = '1';
        }
        if (includeCounts) {
            payload.include_recent = '1';
        }
        A.postFormUrlEncoded(AutoDocsDashboard.ajaxUrl, payload).then(function (res) {
            if (!res || !res.success || !res.data) {
                return;
            }
            var d = res.data;
            var statusEl = A.qs('#autodocs-sidebar-status');
            var lastSyncEl = A.qs('#autodocs-sidebar-last-sync');
            if (statusEl) {
                statusEl.classList.remove('autodocs-sidebar__pill', 'autodocs-sidebar__pill--ok');
            }
            if (lastSyncEl) {
                lastSyncEl.textContent = '—';
            }
            if (!d.connected) {
                if (statusEl) {
                    statusEl.textContent = t('notConnected', 'Not connected');
                }
                var emailEl = A.qs('#autodocs-sidebar-email');
                if (emailEl) {
                    emailEl.textContent = '—';
                }
                return;
            }
            if (d.needs_reconnect) {
                if (statusEl) {
                    statusEl.textContent = t('notConnected', 'Reconnect required');
                }
                var emailEl2 = A.qs('#autodocs-sidebar-email');
                if (emailEl2) {
                    emailEl2.textContent = t(
                        'reconnectHint',
                        'Disconnect Google under Settings, then connect again.'
                    );
                }
                return;
            }
            if (statusEl) {
                statusEl.classList.add('autodocs-sidebar__pill', 'autodocs-sidebar__pill--ok');
                statusEl.textContent = t('connected', 'Connected');
            }
            var emailEl3 = A.qs('#autodocs-sidebar-email');
            if (emailEl3) {
                emailEl3.textContent = d.email || '—';
            }
            if (lastSyncEl) {
                lastSyncEl.textContent = d.last_sync_formatted || '—';
            }
            if (includeCounts && d.counts) {
                A.qsa('[data-autodocs-count="new"]').forEach(function (node) {
                    node.textContent = d.counts.new != null ? String(d.counts.new) : '—';
                });
                A.qsa('[data-autodocs-count="synced"]').forEach(function (node) {
                    node.textContent = d.counts.synced != null ? String(d.counts.synced) : '—';
                });
                A.qsa('[data-autodocs-count="modified"]').forEach(function (node) {
                    node.textContent = d.counts.modified != null ? String(d.counts.modified) : '—';
                });
                if (d.counts.total != null) {
                    A.qsa('[data-autodocs-count="total"]').forEach(function (node) {
                        node.textContent = String(d.counts.total);
                    });
                }
            }
            if (d.recent_syncs) {
                A.renderRecentSyncsList(d.recent_syncs);
            }
        });
    };

    A.renderRecentSyncsList = function (items) {
        var list = A.qs('#autodocs-recent-syncs-list');
        if (!list) {
            return;
        }
        list.innerHTML = '';
        if (!items || !items.length) {
            list.appendChild(
                A.el('li', {
                    class: 'description',
                    text: (AutoDocsDashboard.i18n && AutoDocsDashboard.i18n.noRecentSyncs) || 'No synced posts yet.'
                })
            );
            return;
        }
        var seenIds = {};
        items.forEach(function (row) {
            var postId = row.post_id ? parseInt(row.post_id, 10) : 0;
            if (postId > 0) {
                if (seenIds[postId]) {
                    return;
                }
                seenIds[postId] = true;
            }
            var li = A.el('li', { class: 'autodocs-recent-syncs__item' });
            if (postId > 0) {
                li.setAttribute('data-post-id', String(postId));
            }
            var main = A.el('span', { class: 'autodocs-recent-syncs__main' });
            if (row.edit_url) {
                main.appendChild(A.el('a', { href: row.edit_url, text: row.title || '(no title)' }));
            } else {
                main.appendChild(A.el('span', { text: row.title || '(no title)' }));
            }
            if (row.subtitle) {
                main.appendChild(A.el('span', { class: 'autodocs-recent-syncs__subtitle', text: row.subtitle }));
            }
            li.appendChild(main);
            var meta = A.el('span', { class: 'autodocs-recent-syncs__meta' });
            if (row.sync_source_label) {
                var srcClass = 'autodocs-recent-syncs__source';
                if (row.sync_source) {
                    srcClass += ' autodocs-recent-syncs__source--' + row.sync_source;
                }
                meta.appendChild(A.el('span', { class: srcClass, text: row.sync_source_label }));
            }
            if (row.last_synced_formatted) {
                meta.appendChild(
                    A.el('span', { class: 'autodocs-recent-syncs__time', text: row.last_synced_formatted })
                );
            }
            if (meta.childNodes.length) {
                li.appendChild(meta);
            }
            list.appendChild(li);
        });
    };

    A.loadDriveRootMetaIfNeeded = function () {
        if (typeof AutoDocsPublisher === 'undefined' || !AutoDocsPublisher.ajaxUrl) {
            return;
        }
        var idInput = A.qs('#autodocs-working-folder-id');
        var nameInput = A.qs('#autodocs-working-folder-name');
        if (!idInput) {
            return;
        }
        var id = A.trim(idInput.value);
        var name = nameInput ? A.trim(nameInput.value) : '';
        if (!id || name) {
            return;
        }
        A.postFormUrlEncoded(AutoDocsPublisher.ajaxUrl, {
            action: 'autodocs_drive_item_meta',
            nonce: AutoDocsPublisher.nonce,
            file_id: id
        }).then(function (res) {
            if (!res || !res.success || !res.data) {
                return;
            }
            var n = res.data.name || '';
            if (n && nameInput) {
                nameInput.value = n;
            }
            var rootName = A.qs('#autodocs-drive-root-name');
            if (rootName) {
                rootName.textContent = n || id;
            }
            var rootIdDisp = A.qs('#autodocs-drive-root-id-display');
            if (rootIdDisp) {
                rootIdDisp.textContent = id;
            }
            var rootPath = A.qs('#autodocs-drive-root-path');
            if (rootPath) {
                rootPath.textContent = n ? 'Drive / ' + n : 'Drive / —';
            }
        });
    };
})(window);
