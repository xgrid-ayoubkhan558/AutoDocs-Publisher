(function (w) {
    'use strict';

    var A = (w.AutoDocsAdmin = w.AutoDocsAdmin || {});

    A.createDrivePicker = function () {
        return {
            stack: [],
            panel: A.qs('#autodocs-drive-folder-picker'),
            list: A.qs('#autodocs-drive-folder-list'),
            msg: A.qs('#autodocs-drive-folder-msg'),
            crumb: A.qs('#autodocs-drive-folder-crumb'),
            up: A.qs('#autodocs-drive-folder-up'),
            input: A.qs('#autodocs-working-folder-id'),
            i18n: typeof AutoDocsPublisher !== 'undefined' && AutoDocsPublisher.i18n ? AutoDocsPublisher.i18n : {},

            t: function (key, fallback) {
                return this.i18n[key] || fallback;
            },

            resetStack: function () {
                this.stack = [{ id: 'root', name: this.t('myDrive', 'My Drive') }];
            },

            currentParent: function () {
                return this.stack[this.stack.length - 1].id;
            },

            updateUpState: function () {
                if (this.up) {
                    this.up.disabled = this.stack.length <= 1;
                }
            },

            updateCrumb: function () {
                if (this.crumb) {
                    this.crumb.textContent = this.stack
                        .map(function (s) {
                            return s.name;
                        })
                        .join(' / ');
                }
            },

            show: function () {
                A.showEl(this.panel, true);
                this.resetStack();
                this.updateUpState();
                this.updateCrumb();
                this.load(this.currentParent());
            },

            hide: function () {
                A.showEl(this.panel, false);
            },

            load: function (parentId) {
                var self = this;
                if (this.list) {
                    this.list.innerHTML = '';
                }
                if (this.msg) {
                    this.msg.textContent = this.t('loadingFolders', 'Loading folders…');
                }
                A.postFormUrlEncoded(AutoDocsPublisher.ajaxUrl, {
                    action: 'autodocs_list_drive_folders',
                    nonce: AutoDocsPublisher.nonce,
                    parent_id: parentId
                })
                    .then(function (response) {
                        if (self.msg) {
                            self.msg.textContent = '';
                        }
                        if (!response || !response.success) {
                            var err =
                                response && response.data && response.data.message
                                    ? response.data.message
                                    : self.t('folderListError', 'Could not load folders.');
                            if (self.msg) {
                                self.msg.textContent = err;
                            }
                            return;
                        }
                        if (!response.data || !Array.isArray(response.data.folders)) {
                            if (self.msg) {
                                self.msg.textContent = self.t('folderListError', 'Could not load folders.');
                            }
                            return;
                        }
                        var folders = response.data.folders;
                        if (!folders.length) {
                            if (self.msg) {
                                self.msg.textContent = self.t('noFolders', 'No subfolders here.');
                            }
                            return;
                        }
                        var table = A.el('table', { class: 'autodocs-drive-folder-table' });
                        var thead = A.el('thead', null, [
                            A.el('tr', null, [A.el('th', { text: 'Folder' }), A.el('th', { text: '' })])
                        ]);
                        var tbody = A.el('tbody');
                        folders.forEach(function (f) {
                            var name = f.name || '';
                            var id = f.id || '';
                            var openBtn = A.el('button', {
                                type: 'button',
                                class: 'button button-small autodocs-drive-open',
                                text: self.t('open', 'Open')
                            });
                            openBtn.setAttribute('data-id', id);
                            openBtn.setAttribute('data-name', name);
                            var selBtn = A.el('button', {
                                type: 'button',
                                class: 'button button-small button-primary autodocs-drive-select',
                                text: self.t('selectFolder', 'Use as Drive root folder')
                            });
                            selBtn.setAttribute('data-id', id);
                            selBtn.setAttribute('data-name', name);
                            var actionsTd = A.el('td', { style: 'white-space:nowrap' });
                            actionsTd.appendChild(openBtn);
                            actionsTd.appendChild(document.createTextNode(' '));
                            actionsTd.appendChild(selBtn);
                            tbody.appendChild(A.el('tr', null, [A.el('td', { text: name }), actionsTd]));
                        });
                        table.appendChild(thead);
                        table.appendChild(tbody);
                        if (self.list) {
                            self.list.appendChild(table);
                        }
                    })
                    .catch(function () {
                        if (self.msg) {
                            self.msg.textContent = self.t('folderListError', 'Could not load folders.');
                        }
                    });
            },

            openFolder: function (id, name) {
                this.stack.push({ id: id, name: name || id });
                this.updateUpState();
                this.updateCrumb();
                this.load(id);
            },

            goUp: function () {
                if (this.stack.length <= 1) {
                    return;
                }
                this.stack.pop();
                this.updateUpState();
                this.updateCrumb();
                this.load(this.currentParent());
            }
        };
    };

    A.bindDrivePicker = function (drivePicker) {
        var browseBtn = A.qs('#autodocs-browse-drive-folders');
        if (browseBtn && drivePicker.panel) {
            browseBtn.addEventListener('click', function () {
                if (A.isVisible(drivePicker.panel)) {
                    drivePicker.hide();
                } else {
                    drivePicker.show();
                }
            });
        }
        if (drivePicker.up) {
            drivePicker.up.addEventListener('click', function () {
                drivePicker.goUp();
            });
        }
        if (drivePicker.list) {
            drivePicker.list.addEventListener('click', function (e) {
                var openB = e.target.closest('button.autodocs-drive-open');
                if (openB) {
                    var oid = openB.getAttribute('data-id');
                    var oname = openB.getAttribute('data-name') || '';
                    if (oid) {
                        drivePicker.openFolder(oid, oname);
                    }
                    return;
                }
                var selB = e.target.closest('button.autodocs-drive-select');
                if (selB) {
                    var sid = selB.getAttribute('data-id');
                    var sname = selB.getAttribute('data-name') || '';
                    if (sid && drivePicker.input) {
                        drivePicker.input.value = sid;
                        var wname = A.qs('#autodocs-working-folder-name');
                        if (wname) {
                            wname.value = sname;
                        }
                        var rootName = A.qs('#autodocs-drive-root-name');
                        if (rootName) {
                            rootName.textContent = sname || sid;
                        }
                        var rootIdDisp = A.qs('#autodocs-drive-root-id-display');
                        if (rootIdDisp) {
                            rootIdDisp.textContent = sid;
                        }
                        var rootPath = A.qs('#autodocs-drive-root-path');
                        if (rootPath) {
                            rootPath.textContent = sname ? 'Drive / ' + sname : 'Drive / —';
                        }
                        drivePicker.hide();
                        drivePicker.input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });
        }
    };
})(window);
