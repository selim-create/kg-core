(function () {
    'use strict';
    document.addEventListener('change', function (event) {
        if (!event.target.matches('[data-kg-safety-confirm]')) return;
        var root = event.target.closest('[data-kg-safety-review]');
        if (root) root.querySelector('[data-kg-safety-submit]').disabled = !event.target.checked;
    });
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-kg-safety-submit]');
        if (!button || typeof kgRecipeSafety === 'undefined') return;
        var root = button.closest('[data-kg-safety-review]');
        var status = root.querySelector('[data-kg-safety-result]');
        if (!root.querySelector('[data-kg-safety-confirm]').checked) return;
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
        }).then(function (response) { return response.json(); })
          .then(function (json) {
              if (!json.success) throw new Error((json.data && json.data.message) || 'Onay kaydedilemedi.');
              status.textContent = json.data.message;
          })
          .catch(function (err) {
              status.textContent = err.message || 'Bağlantı hatası.';
              button.disabled = false;
          });
    });
}());
