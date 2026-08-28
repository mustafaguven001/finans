(function () {
    'use strict';

    if (typeof ghQuoteList === 'undefined') {
        return;
    }

    var config = ghQuoteList;

    function updateHeaderCount() {
        var count = 0;
        try {
            var stored = sessionStorage.getItem(config.sessionKey);
            if (stored) {
                var items = JSON.parse(stored);
                count = Object.keys(items).length;
            }
        } catch (e) {}

        var badges = document.querySelectorAll('.gh-quote-list-count');
        badges.forEach(function (el) {
            el.textContent = count;
        });

        var headers = document.querySelectorAll('.gh-quote-list-header');
        headers.forEach(function (el) {
            el.textContent = config.i18n.header + ' (' + count + ')';
        });
    }

    function init() {
        updateHeaderCount();

        window.addEventListener('storage', function (e) {
            if (e.key === config.sessionKey) {
                updateHeaderCount();
            }
        });

        var observer = new MutationObserver(updateHeaderCount);
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
