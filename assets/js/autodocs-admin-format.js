(function (w) {
    'use strict';

    var A = (w.AutoDocsAdmin = w.AutoDocsAdmin || {});

    A.formatBytes = function (n) {
        n = parseInt(n, 10) || 0;
        if (n <= 0) {
            return '—';
        }
        if (n < 1024) {
            return n + ' B';
        }
        if (n < 1048576) {
            return (n / 1024).toFixed(1) + ' KB';
        }
        return (n / 1048576).toFixed(1) + ' MB';
    };

    A.formatIsoDate = function (iso) {
        if (!iso) {
            return '—';
        }
        var d = new Date(iso);
        if (isNaN(d.getTime())) {
            return iso;
        }
        return d.toLocaleString();
    };
})(window);
