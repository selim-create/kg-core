(function () {
    'use strict';

    // Gutenberg may re-render legacy meta boxes; do not retain stale nodes.
    function sync(root) {
        if (!root) return;
        var checkbox = root.querySelector('[data-kg-safety-confirm]');
        var button = root.querySelector('[data-kg-safety-submit]');
        if (!checkbox || !button || button.dataset.saving === '1' || button.dataset.saved === '1') return;
        button.disabled = !checkbox.checked;
    }

    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-kg-safety-confirm]')) {
            sync(event.target.closest('[data-kg-safety-review]'));
        }
    }, true);

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-kg-safety-submit]');
        if (!button || button.dataset.saving === '1' || button.dataset.saved === '1') return;
        var root = button.closest('[data-kg-safety-review]');
        var checkbox = root && root.querySelector('[data-kg-safety-confirm]');
        var status = root && root.querySelector('[data-kg-safety-result]');
        if (!checkbox || !checkbox.checked || !status) return;
        event.preventDefault();
        if (typeof kgRecipeSafety === 'undefined') {
            status.textContent = 'Onay hizmeti yüklenmedi. Sayfayı yenileyin.';
            return;
        }
        button.dataset.saving = '1';
        button.disabled = true;
        status.textContent = 'Editoryal onay kaydediliyor...';
        var body = new URLSearchParams({
            action: 'kg_recipe_acknowledge_warning',
            nonce: kgRecipeSafety.nonce,
            post_id: String(kgRecipeSafety.postId)
        });
        fetch(kgRecipeSafety.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: body.toString()
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            if (!json.success) throw new Error((json.data && json.data.message) || 'Onay kaydedilemedi.');
            button.dataset.saved = '1';
            status.textContent = json.data.message + ' Güvenlik uyarısı inceleme amacıyla görünmeye devam eder.';
            checkbox.disabled = true;
            button.textContent = 'Onay Kaydedildi';
        }).catch(function (error) {
            status.textContent = error.message || 'Bağlantı hatası.';
        }).finally(function () {
            delete button.dataset.saving;
            sync(root);
        });
    }, true);

    // Enable the button if Gutenberg restores a checked state on a rerender.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-kg-safety-review]').forEach(sync);
    });
}());
