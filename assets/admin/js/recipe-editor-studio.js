(function () {
    'use strict';
    function init(root) {
        if (!root || root.dataset.kgStudioReady === '1') return;
        root.dataset.kgStudioReady = '1';
        function refreshCounts() {
            [
                {name: 'ingredients', id: 'kg-ingredients-repeater'},
                {name: 'instructions', id: 'kg-instructions-repeater'}
            ].forEach(function (item) {
                var holder = root.querySelector('#' + item.id);
                var count = holder ? holder.querySelectorAll('.kg-repeater-items > .kg-repeater-item').length : 0;
                root.querySelectorAll('[data-kg-count="' + item.name + '"]').forEach(function (node) {
                    node.textContent = count > 0 ? String(count) : '';
                });
            });
        }
        root.querySelectorAll('.kg-editor-nav a[href^="#kg-recipe-"]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                var section = root.querySelector(link.getAttribute('href'));
                if (!section) return;
                event.preventDefault();
                section.open = true;
                section.scrollIntoView({behavior: 'smooth', block: 'start'});
            });
        });
        var repeaterArea = root.querySelector('#kg-ingredients-repeater');
        if (repeaterArea && typeof MutationObserver !== 'undefined') {
            var watcher = new MutationObserver(refreshCounts);
            watcher.observe(repeaterArea.querySelector('.kg-repeater-items'), {childList: true});
        }
        var steps = root.querySelector('#kg-instructions-repeater');
        if (steps && typeof MutationObserver !== 'undefined') {
            var stepsWatcher = new MutationObserver(refreshCounts);
            stepsWatcher.observe(steps.querySelector('.kg-repeater-items'), {childList: true});
        }
        root.addEventListener('click', function (event) {
            if (!event.target.closest('.kg-repeater .kg-add-item, .kg-repeater .kg-remove-item')) return;
            window.setTimeout(refreshCounts, 350);
        });
        refreshCounts();
    }
    function boot() {
        document.querySelectorAll('.kg-recipe-editor').forEach(init);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    // Gutenberg can remount the legacy meta box.
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(boot);
        observer.observe(document.body, {childList: true, subtree: true});
    }
}());
