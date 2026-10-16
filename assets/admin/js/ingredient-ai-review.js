(function () {
    'use strict';
    var cfg = window.kgIngredientAIReview;
    if (!cfg) return;

    function init() {
    var root = document.getElementById('kg-ingredient-ai-review');
    if (!root || root.dataset.kgAiReady === '1') return;
    root.dataset.kgAiReady = '1';
    var complete = root.querySelector('[data-kg-ai-generate-complete]');
    var enrich = root.querySelector('[data-kg-ai-generate-enrich]');
    var coverage = cfg.coverage || null;
    var apply = root.querySelector('[data-kg-ai-apply]');
    var cancel = root.querySelector('[data-kg-ai-cancel]');
    var notice = root.querySelector('[data-kg-ai-message]');
    var preview = root.querySelector('[data-kg-ai-preview]');
    var rows = root.querySelector('[data-kg-ai-rows]');
    var count = root.querySelector('[data-kg-ai-count]');
    var token = '';
    var working = false;
    var publishConfirmed = false;
    var previewMode = 'complete';
    function renderCoverage(data) {
        if (!data) return;
        var headline = root.querySelector('[data-kg-ai-coverage-headline]');
        var summary = root.querySelector('[data-kg-ai-coverage-summary]');
        var score = root.querySelector('[data-kg-ai-coverage-score]');
        var progress = root.querySelector('[data-kg-ai-coverage-progress]');
        var groups = root.querySelector('[data-kg-ai-coverage-groups]');
        var toggle = root.querySelector('[data-kg-ai-coverage-toggle]');
        if (!headline || !groups) return;
        headline.textContent = data.missing + ' eksik alan';
        summary.textContent = data.filled + ' alan dolu · ' + data.total + ' alan kontrol edildi';
        var card = root.querySelector('[data-kg-ai-coverage]');
        if (card) {
            var pct = data.total ? Math.round(data.filled * 100 / data.total) : 0;
            card.classList.remove('kg-ai-review__coverage--critical', 'kg-ai-review__coverage--warning', 'kg-ai-review__coverage--good');
            card.classList.add(pct >= 86 ? 'kg-ai-review__coverage--good' :
                pct >= 70 ? 'kg-ai-review__coverage--warning' : 'kg-ai-review__coverage--critical');
        }
        score.textContent = data.total ? Math.round(data.filled * 100 / data.total) + '%' : '—';
        progress.style.width = (data.total ? Math.round(data.filled * 100 / data.total) : 0) + '%';
        toggle.textContent = data.missing ? 'Eksik alanları göster (' + data.missing + ')' : 'Kontrol edilen alanları göster';
        groups.textContent = '';
        (data.groups || []).forEach(function (group) {
            var block = node('div', 'kg-ai-review__coverage-group');
            block.appendChild(node('strong', '', group.title + ' · ' + group.filled + '/' + group.total));
            var list = node('ul', '');
            (group.items || []).forEach(function (item) {
                var entry = node('li', item.filled ? 'is-filled' : 'is-missing', (item.filled ? '✓ ' : '○ ') + item.label + (item.key === 'category' && !item.filled ? ' (AI otomatik seçecek)' : ''));
                list.appendChild(entry);
            });
            block.appendChild(list);
            groups.appendChild(block);
        });
    }
    renderCoverage(coverage);

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
        complete.disabled = value;
        enrich.disabled = value;
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
    function showSaved(result) {
        if (!result || !result.count) throw new Error('Sunucu kayıt doğrulaması eksik.');
        report(result.message + ' Sayfa yenileniyor...', false);
        window.location.reload();
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
            return response.text().then(function (body) {
                var json;
                try { json = JSON.parse(body); }
                catch (ignore) {
                    var reason = response.status === 502 || response.status === 504 ? 'Ağ geçidi zaman aşımı' :
                        response.status === 403 ? 'Sunucu güvenlik engeli' :
                        response.status === 500 ? 'Sunucuda PHP hatası' :
                        'Beklenmedik sunucu yanıtı';
                    throw new Error(reason + ' (HTTP ' + response.status + '). ' +
                        (body.trim() === '0' ? 'WordPress işlem tanımlayıcısı çalışmadı.' :
                            body.trim() === '-1' ? 'WordPress güvenlik doğrulaması başarısız.' :
                            'Yanıt JSON değil. Sunucu logunu ve Network yanıtını kontrol et.'));
                }
                return json;
            });
        }).then(function (json) {
            if (!json.success) {
                var message = json.data && (typeof json.data === 'string' ? json.data : json.data.message);
                throw new Error(message || 'AI işlemi tamamlanamadı.');
            }
            return json.data;
        });
    }

    function startPreview(mode) {
        if (working) return;
        if (cfg.isPublished) {
            if (!window.confirm('Bu malzeme yayında. AI önerileri tamamlandığında uygun alanlar otomatik olarak canlı içeriğe kaydedilecek. Devam edilsin mi?')) return;
            publishConfirmed = true;
        } else publishConfirmed = false;
        previewMode = mode;
        clear();
        busy(true, mode === 'complete' ? 'Eksik alanlar için AI önerileri hazırlanıyor...' : 'Zenginleştirme önerileri hazırlanıyor...');
        ajax('kg_ingredient_ai_review_preview', {mode: mode, confirm_published: publishConfirmed ? 'yes' : 'no'}).then(function (job) {
            if (!job.job_id) throw new Error('AI işi başlatılamadı: sunucu iş kimliği döndürmedi.');
            var started = Date.now();
            function poll() {
                if (Date.now() - started > 625000) throw new Error('OpenAI işlemi 10 dakikada tamamlanamadı. OpenAI API durumunu kontrol et.');
                return ajax('kg_ingredient_ai_review_status', {job_id: job.job_id}).then(function (result) {
                    if (result.status === 'done') return showSaved(result.result);
                    if (result.status === 'failed') throw new Error(result.message || 'AI sunucuda tamamlanamadı.');
                    report(result.message || 'AI önerileri hazırlanıyor...', false);
                    return new Promise(function (resolve) { setTimeout(resolve, 2300); }).then(poll);
                });
            }
            return poll();
        }).catch(function (error) {
            report(error.message, true);
        }).finally(function () { busy(false); });
    }
    complete.addEventListener('click', function () { startPreview('complete'); });
    enrich.addEventListener('click', function () { startPreview('enrich'); });
    rows.addEventListener('change', function (event) {
        if (event.target.matches('input[data-kg-ai-field]')) refresh();
    });
    cancel.addEventListener('click', clear);

    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
    // Gutenberg may move or remount legacy metaboxes after initial page load.
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(init);
        observer.observe(document.documentElement || document.body, {childList: true, subtree: true});
    }
}());