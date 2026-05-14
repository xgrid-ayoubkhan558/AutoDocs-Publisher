(function (w) {
    'use strict';

    var A = (w.AutoDocsAdmin = w.AutoDocsAdmin || {});

    A.refreshDashboardSidebar = function () {
        if (typeof AutoDocsDashboard === 'undefined' || !AutoDocsDashboard.ajaxUrl) {
            return;
        }
        var dbi = AutoDocsDashboard.i18n || {};
        function t(k, fb) {
            return dbi[k] || fb;
        }
        A.postFormUrlEncoded(AutoDocsDashboard.ajaxUrl, {
            action: 'autodocs_sidebar_snapshot',
            nonce: AutoDocsDashboard.nonce
        }).then(function (res) {
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
                var foldersUl = A.qs('#autodocs-sidebar-folders');
                if (foldersUl) {
                    foldersUl.innerHTML =
                        '<li class="autodocs-sidebar__folder-placeholder">' +
                        t('setDriveRootFirst', 'Set a Drive root folder on the Drive & folders tab.') +
                        '</li>';
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
            var ul = A.qs('#autodocs-sidebar-folders');
            if (ul) {
                ul.innerHTML = '';
            }
            if (d.root && d.root.id && ul) {
                ul.appendChild(
                    A.el('li', { class: 'autodocs-sidebar__folder-root', text: d.root.name || d.root.id })
                );
                if (d.buckets) {
                    ['new', 'synced', 'modified'].forEach(function (key) {
                        var b = d.buckets[key];
                        if (b && b.id) {
                            var label = key.charAt(0).toUpperCase() + key.slice(1);
                            var bn = b.name || b.id;
                            var line = label + ': ' + bn;
                            if (key === 'modified' && b.note) {
                                line += ' — ' + b.note;
                            }
                            ul.appendChild(A.el('li', { class: 'autodocs-sidebar__folder-sub', text: line }));
                        }
                    });
                }
            } else if (ul) {
                ul.appendChild(
                    A.el('li', {
                        class: 'autodocs-sidebar__folder-placeholder',
                        text: t('setDriveRootFirst', 'Set a Drive root folder on the Drive & folders tab.')
                    })
                );
            }
            if (d.counts) {
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
