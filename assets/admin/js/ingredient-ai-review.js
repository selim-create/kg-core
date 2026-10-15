(function () {
    'use strict';
    var cfg = window.kgIngredientAIReview;
    if (!cfg) return;

    function init() {
    var root = document.getElementById('kg-ingredient-ai-review');
    if (!root || root.dataset.kgAiReady === '1') return;
    root.dataset.kgAiReady = '1';
    var generate = root.querySelector('[data-kg-ai-generate]');
    var apply = root.querySelector('[data-kg-ai-apply]');
    var cancel = root.querySelector('[data-kg-ai-cancel]');
    var notice = root.querySelector('[data-kg-ai-message]');
    var preview = root.querySelector('[data-kg-ai-preview]');
    var rows = root.querySelector('[data-kg-ai-rows]');
    var count = root.querySelector('[data-kg-ai-count]');
    var token = '';
    var working = false;

    function report(message, error) {
        notice.textContent = message || '';
        notice.classList.toggle('kg-ai-review__message--error', !!error);
    }
    function dirty() {
        try {
            var editor = window.wp && window.wp.data && window.wp.data.select('core/editor');
            if (editor && editor.isEditedPostDirty && editor.isEditedPostDirty()) return true;
        } catch (ignore) {}
        var fields = document.querySelectorAll('.kg-ingredient-studio input, .kg-ingredient-studio textarea, .kg-ingredient-studio select');
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if (field.type === 'checkbox' || field.type === 'radio') {
                if (field.checked !== field.defaultChecked) return true;
            } else if (field.tagName === 'SELECT') {
                for (var j = 0; j < field.options.length; j++) {
                    if (field.options[j].selected !== field.options[j].defaultSelected) return true;
                }
            } else if (field.value !== field.defaultValue) return true;
        }
        return false;
    }
    function selectedFields() {
        return Array.prototype.map.call(rows.querySelectorAll('input[data-kg-ai-field]:checked'), function (input) {
            return input.dataset.kgAiField;
        });
    }
    function refresh() {
        var n = selectedFields().length;
        count.textContent = n + ' / ' + rows.querySelectorAll('input[data-kg-ai-field]').length + ' alan seçildi';
        apply.disabled = working || n === 0;
    }
    function busy(value, message) {
        working = value;
        generate.disabled = value;
        apply.disabled = value || selectedFields().length === 0;
        if (message) report(message, false);
    }
    function node(tag, cls, value) {
        var item = document.createElement(tag);
        if (cls) item.className = cls;
        if (value !== undefined) item.textContent = String(value);
        return item;
    }
    function compare(container, title, value) {
        var box = node('div', 'kg-ai-review__value');
        box.appendChild(node('span', 'kg-ai-review__value-label', title));
        box.appendChild(node('div', 'kg-ai-review__value-text', value || '(Boş)'));
        container.appendChild(box);
    }
    function show(data) {
        token = data.token;
        rows.textContent = '';
        (data.fields || []).forEach(function (field) {
            var card = node('div', 'kg-ai-review__field');
            var header = node('label', 'kg-ai-review__field-label');
            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.dataset.kgAiField = field.key;
            checkbox.checked = !!field.empty;
            header.appendChild(checkbox);
            header.appendChild(node('strong', '', field.label));
            header.appendChild(node('span', 'kg-ai-review__field-status', field.empty ? 'Boş alan' : 'Mevcut değer var'));
            card.appendChild(header);
            var values = node('div', 'kg-ai-review__compare');
            compare(values, 'MEVCUT', field.current);
            compare(values, 'AI ÖNERİSİ', field.suggested);
            card.appendChild(values);
            rows.appendChild(card);
        });
        preview.hidden = false;
        refresh();
        report('AI önerileri hazır. Henüz hiçbir veri değişmedi.', false);
    }
    function clear() {
        token = '';
        rows.textContent = '';
        preview.hidden = true;
        report('', false);
    }
    function ajax(action, values) {
        var form = new URLSearchParams();
        form.append('action', action);
        form.append('post_id', String(cfg.postId));
        form.append('nonce', cfg.nonce);
        Object.keys(values || {}).forEach(function (key) {
            var value = values[key];
            if (Array.isArray(value)) value.forEach(function (v) { form.append(key + '[]', String(v)); });
            else form.append(key, String(value));
        });
        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: form.toString()
        }).then(function (response) {
            return response.json().catch(function () { throw new Error('Sunucu yanıtı okunamadı.'); });
        }).then(function (json) {
            if (!json.success) {
                var message = json.data && (typeof json.data === 'string' ? json.data : json.data.message);
                throw new Error(message || 'AI işlemi tamamlanamadı.');
            }
            return json.data;
        });
    }

    generate.addEventListener('click', function () {
        if (working) return;
        if (dirty()) {
            report('AI önerisinden önce kaydedilmemiş değişiklikleri kaydet.', true);
            return;
        }
        clear();
        busy(true, 'AI önerileri hazırlanıyor...');
        ajax('kg_ingredient_ai_review_preview', {}).then(show).catch(function (error) {
            report(error.message, true);
        }).finally(function () { busy(false); });
    });
    rows.addEventListener('change', function (event) {
        if (event.target.matches('input[data-kg-ai-field]')) refresh();
    });
    cancel.addEventListener('click', clear);
    apply.addEventListener('click', function () {
        if (working || !token) return;
        if (dirty()) {
            report('Onaydan önce kaydedilmemiş değişiklikleri kaydet ve öneriyi yenile.', true);
            return;
        }
        var fields = selectedFields();
        if (!fields.length) return;
        if (cfg.isPublished && !window.confirm('Bu malzeme yayında. Seçilen bilgiler canlı sayfada değişecek. Devam edilsin mi?')) return;
        busy(true, 'Seçilen alanlar kaydediliyor...');
        ajax('kg_ingredient_ai_review_apply', {
            token: token,
            fields: fields,
            confirm_published: cfg.isPublished ? 'yes' : 'no'
        }).then(function (data) {
            token = '';
            preview.hidden = true;
            report(data.message || 'AI önerileri kaydedildi. Sayfa yenileniyor...', false);
            window.location.reload();
        }).catch(function (error) {
            report(error.message, true);
        }).finally(function () { busy(false); });
    });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
    // Gutenberg may move or remount legacy metaboxes after initial page load.
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(init);
        observer.observe(document.documentElement || document.body, {childList: true, subtree: true});
    }
}());