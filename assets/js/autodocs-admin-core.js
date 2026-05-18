(function (w) {
    'use strict';

    var A = (w.AutoDocsAdmin = w.AutoDocsAdmin || {});

    A.qs = function (sel, root) {
        return (root || document).querySelector(sel);
    };

    A.qsa = function (sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    };

    A.trim = function (s) {
        return (s == null ? '' : String(s)).trim();
    };

    A.on = function (root, event, selector, handler) {
        root.addEventListener(event, function (e) {
            var t = e.target.closest(selector);
            if (t && root.contains(t)) {
                handler.call(t, e, t);
            }
        });
    };

    A.postFormUrlEncoded = function (url, data) {
        var body = new URLSearchParams();
        Object.keys(data).forEach(function (k) {
            if (data[k] !== undefined && data[k] !== null) {
                body.append(k, data[k]);
            }
        });
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (r) {
            return r.json();
        });
    };

    A.postFormData = function (url, fd) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        }).then(function (r) {
            return r.json();
        });
    };

    A.el = function (tag, attrs, children) {
        var e = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'class') {
                    e.className = attrs[k];
                } else if (k === 'text') {
                    e.textContent = attrs[k];
                } else if (k === 'html') {
                    e.innerHTML = attrs[k];
                } else if (k === 'for') {
                    e.htmlFor = attrs[k];
                } else if (
                    k === 'type' ||
                    k === 'name' ||
                    k === 'value' ||
                    k === 'id' ||
                    k === 'href' ||
                    k === 'target' ||
                    k === 'rel' ||
                    k === 'src' ||
                    k === 'alt' ||
                    k === 'rows' ||
                    k === 'multiple' ||
                    k === 'size' ||
                    k === 'checked' ||
                    k === 'disabled' ||
                    k === 'hidden' ||
                    k === 'role' ||
                    k === 'aria-selected' ||
                    k === 'scope' ||
                    k === 'width' ||
                    k === 'height'
                ) {
                    e[k] = attrs[k];
                } else if (k.indexOf('data-') === 0) {
                    e.setAttribute(k, attrs[k]);
                } else {
                    e.setAttribute(k, attrs[k]);
                }
            });
        }
        (children || []).forEach(function (c) {
            if (c == null) {
                return;
            }
            if (typeof c === 'string' || typeof c === 'number') {
                e.appendChild(document.createTextNode(String(c)));
            } else {
                e.appendChild(c);
            }
        });
        return e;
    };

    A.showEl = function (node, show) {
        if (!node) {
            return;
        }
        node.style.display = show ? '' : 'none';
        if (show) {
            node.removeAttribute('hidden');
        } else {
            node.setAttribute('hidden', 'hidden');
        }
    };

    A.isVisible = function (node) {
        return node && node.style.display !== 'none' && !node.hasAttribute('hidden');
    };

    A.getMultiSelectValues = function (select) {
        return Array.prototype.map.call(select.selectedOptions, function (o) {
            return o.value;
        });
    };

    /**
     * @param {HTMLSelectElement} select
     * @param {HTMLInputElement} customInput
     * @param {string} customOptionValue
     * @param {string} defField
     * @param {string} defCustom
     * @returns {boolean}
     */
    A.applyAcfBodyFieldDefault = function (select, customInput, customOptionValue, defField, defCustom) {
        if (!select) {
            return false;
        }
        defField = defField == null ? '' : String(defField);
        defCustom = defCustom == null ? '' : String(defCustom);
        if (defField === '' && defCustom === '') {
            return false;
        }
        var i;
        var opts = select.options;
        for (i = 0; i < opts.length; i++) {
            if (opts[i].value === defField) {
                select.value = defField;
                if (customInput) {
                    customInput.value = defField === customOptionValue ? defCustom : '';
                }
                return true;
            }
        }
        if (defCustom !== '') {
            for (i = 0; i < opts.length; i++) {
                if (opts[i].getAttribute('data-field-name') === defCustom) {
                    select.value = opts[i].value;
                    if (customInput) {
                        customInput.value = '';
                    }
                    return true;
                }
            }
        }
        if (defField === customOptionValue || defCustom !== '') {
            for (i = 0; i < opts.length; i++) {
                if (opts[i].value === customOptionValue) {
                    select.value = customOptionValue;
                    if (customInput) {
                        customInput.value = defCustom !== '' ? defCustom : defField;
                    }
                    return true;
                }
            }
        }
        return false;
    };

    /**
     * @param {HTMLSelectElement} select
     * @param {HTMLInputElement|null} customInput
     * @param {string} customOptionValue
     * @param {string} postType
     * @param {string} serverDef
     * @param {string} serverCustom
     * @returns {boolean}
     */
    A.applyAcfDefaultForPostType = function (select, customInput, customOptionValue, postType, serverDef, serverCustom) {
        if (A.applyAcfBodyFieldDefault(select, customInput, customOptionValue, serverDef || '', serverCustom || '')) {
            return true;
        }
        var map =
            typeof AutoDocsPublisher !== 'undefined' && AutoDocsPublisher.acfImportDefaults
                ? AutoDocsPublisher.acfImportDefaults
                : {};
        var target = map[postType] || '';
        if (!target) {
            return false;
        }
        return A.applyAcfBodyFieldDefault(select, customInput, customOptionValue, '', target);
    };

    A.appendAcfBodySelectOption = function (select, row, groupLabelFallback) {
        var grp = row.group != null && row.group !== '' ? String(row.group) : groupLabelFallback;
        var og = null;
        var children = select.children;
        var i;
        for (i = 0; i < children.length; i++) {
            if (children[i].tagName === 'OPTGROUP' && children[i].label === grp) {
                og = children[i];
                break;
            }
        }
        if (!og) {
            og = document.createElement('optgroup');
            og.label = grp;
            select.appendChild(og);
        }
        var opt = document.createElement('option');
        opt.value = String(row.value);
        opt.textContent = String(row.label);
        if (row.name) {
            opt.setAttribute('data-field-name', String(row.name));
        }
        og.appendChild(opt);
    };

    A.domReady = function (fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    };

    A.articleListsBootstrapped = false;
})(window);
