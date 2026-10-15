/**
 * KidsGourmet editorial sidebar for the recipe block editor.
 * Taxonomy updates use Gutenberg's post edits, not custom AJAX save endpoints.
 * The original WordPress document sidebar is always available.
 */
(function (wp) {
    'use strict';

    if (!wp || !(wp.editPost && wp.editPost.PluginDocumentSettingPanel) && !(wp.editor && wp.editor.PluginDocumentSettingPanel) ||
        !wp.plugins || !wp.data || !wp.element || !wp.components ||
        typeof kgRecipeWorkflow === 'undefined') {
        return;
    }

    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useSelect = wp.data.useSelect;
    var PluginDocumentSettingPanel = (wp.editPost && wp.editPost.PluginDocumentSettingPanel) || (wp.editor && wp.editor.PluginDocumentSettingPanel);
    var PanelBody = wp.components.PanelBody;
    var CheckboxControl = wp.components.CheckboxControl;
    var Button = wp.components.Button;
    var Spinner = wp.components.Spinner;
    if (!PluginDocumentSettingPanel) return;
    var cfg = kgRecipeWorkflow;
    var groups = cfg.taxonomies || [];

    function openWordPressSettings() {
        var store = wp.data.dispatch('core/edit-post');
        if (store && typeof store.openGeneralSidebar === 'function') {
            store.openGeneralSidebar('edit-post/document');
        } else {
            var ui = wp.data.dispatch('core/interface');
            if (ui && ui.enableComplementaryArea) {
                ui.enableComplementaryArea('core', 'edit-post/document');
            }
        }
    }

    function normalizeIDs(values) {
        return Array.isArray(values) ? values.map(Number).filter(function (v) {
            return Number.isFinite(v) && v > 0;
        }) : [];
    }

    function TermPicker(props) {
        var group = props.group;
        var state = useState('');
        var search = state[0];
        var setSearch = state[1];
        var more = useState(false);
        var showMore = more[0];
        var setShowMore = more[1];

        var selected = useSelect(function (select) {
            var editor = select('core/editor');
            return normalizeIDs(editor ? editor.getEditedPostAttribute(group.key) : []);
        }, [group.key]);

        var terms = useSelect(function (select) {
            var core = select('core');
            if (!core || typeof core.getEntityRecords !== 'function') return null;
            var query = {
                per_page: 100,
                hide_empty: false,
                orderby: 'name',
                order: 'asc'
            };
            if (search.trim()) query.search = search.trim();
            return core.getEntityRecords('taxonomy', group.slug, query);
        }, [group.slug, search]);

        var chosen = useSelect(function (select) {
            var core = select('core');
            return selected.map(function (id) {
                var current = Array.isArray(terms) ? terms.find(function (t) {
                    return Number(t.id) === id;
                }) : null;
                if (!current && core && typeof core.getEntityRecord === 'function') {
                    current = core.getEntityRecord('taxonomy', group.slug, id);
                }
                return {id: id, name: current ? current.name : '#' + id};
            });
        }, [group.slug, selected.join(','), terms]);

        function apply(id, checked) {
            var next = checked ?
                (selected.indexOf(id) === -1 ? selected.concat([id]) : selected) :
                selected.filter(function (value) { return value !== id; });
            var update = {};
            update[group.key] = next;
            wp.data.dispatch('core/editor').editPost(update);
        }

        var list = Array.isArray(terms) ? terms.slice() : [];
        if (group.slug === 'age-group') {
            var order = ['0-6-ay-sadece-sut', '6-8-ay-baslangic', '9-11-ay-kesif', '12-24-ay-gecis', '2-yas-ve-uzeri'];
            list.sort(function (a, b) {
                var ai = order.indexOf(a.slug);
                var bi = order.indexOf(b.slug);
                return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
            });
        }
        var limited = !search.trim() && !showMore && list.length > 8 && group.slug !== 'age-group';
        var visible = limited ? list.slice(0, 8) : list;

        return el('div', {className: 'kg-wf-term-picker'},
            el('div', {className: 'kg-wf-picked'},
                selected.length ?
                    chosen.map(function (term) {
                        return el('button', {
                            key: term.id,
                            className: 'kg-wf-chip',
                            type: 'button',
                            onClick: function () { apply(term.id, false); },
                            title: 'Seçimi kaldır: ' + term.name
                        }, term.name, el('span', {'aria-hidden': true}, '×'));
                    }) :
                    el('span', {className: 'kg-wf-placeholder'}, 'Henüz seçim yapılmadı')
            ),
            group.slug !== 'age-group' ? el('label', {className: 'kg-wf-search-label'},
                el('span', {className: 'screen-reader-text'}, group.label + ' ara'),
                el('input', {
                    type: 'search',
                    value: search,
                    placeholder: group.label + ' ara...',
                    onChange: function (event) { setSearch(event.target.value); }
                })
            ) : null,
            terms === null || terms === undefined ?
                el('div', {className: 'kg-wf-loading'}, el(Spinner, {}), ' Seçenekler yükleniyor...') :
                el('div', {className: 'kg-wf-terms'},
                    visible.length ?
                        visible.map(function (term) {
                            return el(CheckboxControl, {
                                key: term.id,
                                label: term.name,
                                checked: selected.indexOf(Number(term.id)) !== -1,
                                onChange: function (checked) { apply(Number(term.id), checked); }
                            });
                        }) :
                        el('span', {className: 'kg-wf-placeholder'}, 'Aramaya uygun seçenek bulunamadı.'),
                    limited ? el(Button, {
                        variant: 'tertiary',
                        className: 'kg-wf-more',
                        onClick: function () { setShowMore(true); }
                    }, 'Tüm seçenekleri göster (' + list.length + ')') : null
                ),
            el('p', {className: 'kg-wf-helper'}, 'Seçimleri kaydetmek için sağ üstteki Güncelle / Yayınla düğmesini kullan.')
        );
    }

    function SafetyControl(props) {
        var state = useState({warning: cfg.warning, approved: !!cfg.approved, last_publication: null});
        var review = state[0];
        var setReview = state[1];
        var accepted = review.approved;
        var setAccepted = function (value) { setReview(function (prev) { return Object.assign({}, prev, {approved: value}); }); };
        var checkbox = useState(false);
        var agreed = checkbox[0];
        var setAgreed = checkbox[1];
        var waiting = useState(false);
        var busy = waiting[0];
        var setBusy = waiting[1];
        var messageState = useState('');
        var message = messageState[0];
        var setMessage = messageState[1];

        wp.element.useEffect(function () {
            if (props.isDirty || !cfg.postId) return;
            var params = new URLSearchParams({action: 'kg_recipe_review_status', post_id: String(cfg.postId), nonce: cfg.reviewNonce});
            fetch(cfg.ajaxUrl, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'}, body: params.toString()})
                .then(function (r) { return r.json(); })
                .then(function (r) { if (r.success) setReview(r.data); });
        }, [props.isDirty]);

        function approve() {
            if (!agreed || busy || accepted || props.isDirty || !cfg.postId) return;
            setBusy(true);
            setMessage('');
            var params = new URLSearchParams({
                action: 'kg_recipe_acknowledge_warning',
                post_id: String(cfg.postId),
                nonce: cfg.reviewNonce
            });
            fetch(cfg.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                body: params.toString()
            }).then(function (r) { return r.json(); })
                .then(function (r) {
                    if (!r.success) {
                        throw new Error((r.data && r.data.message) || 'Onay kaydedilemedi.');
                    }
                    setAccepted(true);
                    setMessage('Editoryal onay kaydedildi. Yayınlama ayrı bir işlemdir.');
                }).catch(function (err) {
                    setMessage(err.message || 'Bağlantı hatası, tekrar deneyin.');
                }).finally(function () { setBusy(false); });
        }

        return el('div', {className: 'kg-wf-safety'},
            review.warning ?
                el('div', {className: 'kg-wf-alert', role: 'alert'},
                    el('strong', null, 'Beslenme uyarısı'),
                    el('p', null, review.warning)
                ) :
                el('div', {className: 'kg-wf-neutral'},
                    el('strong', null, 'Kayıtlı verilerde bilinen bir uyarı yok'),
                    el('p', null, 'Bu sonuç bir tıbbi uygunluk onayı değildir.')
                ),
            el('p', {className: 'kg-wf-helper'}, 'Değerlendirme kaydedilmiş tarif üzerinden yapılır.'),
            review.warning && cfg.canPublish ?
                accepted ?
                    el('div', {className: 'kg-wf-accepted'}, '✓ Bu sürüm için editör onayı kayıtlı.') :
                    el('div', null,
                        props.isDirty ?
                            el('p', {className: 'kg-wf-unsaved'}, 'Onaydan önce değişiklikleri kaydetmelisin.') : null,
                        el(CheckboxControl, {
                            label: 'Uyarıyı okudum, editoryal kararı onaylıyorum.',
                            checked: agreed,
                            onChange: setAgreed,
                            disabled: busy || props.isDirty || !cfg.postId
                        }),
                        el(Button, {
                            variant: 'secondary',
                            disabled: !agreed || busy || props.isDirty || !cfg.postId,
                            isBusy: busy,
                            onClick: approve
                        }, busy ? 'Kaydediliyor...' : 'Editör Onayını Kaydet')
                    ) :
                null,
            message ? el('p', {className: 'kg-wf-feedback', role: 'status'}, message) : null,
            review.last_publication ? el('p', {className: 'kg-wf-helper'}, 'Son riskli yayın: ' + (review.last_publication.published_at_utc || '') + ' UTC · ' + (review.last_publication.explicitly_acknowledged ? 'Açık onaylı' : 'Açık onaysız')) : null,
            el('p', {className: 'kg-wf-helper'}, 'Yetkili editör uyarıya rağmen yayınlayabilir. Açık onay bilgisi ayrıca kayıt altına alınır.')
        );
    }

    function WorkflowSidebar() {
        var editorState = useSelect(function (select) {
            var editor = select('core/editor');
            return {
                status: editor ? editor.getEditedPostAttribute('status') : 'draft',
                dirty: editor && typeof editor.isEditedPostDirty === 'function' ?
                    editor.isEditedPostDirty() : false
            };
        }, []);

        var labels = {
            publish: 'Yayında', future: 'Zamanlandı',
            pending: 'Onay Bekliyor', private: 'Özel', draft: 'Taslak', auto_draft: 'Yeni Tarif'
        };
        var statusLabel = labels[editorState.status] || 'Tarif';
        var groupMap = {};
        groups.forEach(function (g) { groupMap[g.slug] = g; });
        var order = [
            {slug: 'age-group', open: true, index: '01'},
            {slug: 'allergen', open: true, index: '02'},
            {slug: 'meal-type', open: false, index: '03'},
            {slug: 'diet-type', open: false, index: '04'},
            {slug: 'special-condition', open: false, index: '05'},
            {slug: 'community_circle', open: false, index: '06'}
        ];

        function openAI() {
            if (typeof window.kgShowRecipeEnrichModal === 'function') {
                window.kgShowRecipeEnrichModal();
            } else {
                openWordPressSettings();
            }
        }

        return el(wp.element.Fragment, null,
        el(PluginDocumentSettingPanel, {name: 'kg-recipe-overview', title: 'KidsGourmet · Yayın Masası', className: 'kg-workflow-sidebar'},
        el('div', {className: 'kg-wf-top'},
            el('span', {className: 'kg-wf-brand'}, 'KIDSGOURMET', el('span', null, ' / YAYIN MASASI')),
            el('h2', null, 'Tarif Kontrolü'),
            el('p', null, 'Yayın bilgileri, yaş grupları ve güvenlik bir arada.'),
            el('div', {className: 'kg-wf-state'},
                el('span', {className: 'kg-wf-status'}, el('i', {className: 'kg-wf-status-dot'}), statusLabel),
                editorState.dirty ?
                    el('span', {className: 'kg-wf-dirty'}, 'Kaydedilmemiş değişiklikler') :
                    el('span', {className: 'kg-wf-clean'}, 'Değişiklikler kaydedildi')
            )
        )),
        el(PluginDocumentSettingPanel, {name: 'kg-recipe-taxonomy', title: 'Tarif Sınıflandırması', className: 'kg-workflow-sidebar'},
        el('div', {className: 'kg-wf-section-caption'}, 'TARİF SINIFLANDIRMASI'),
        order.filter(function (g) { return !!groupMap[g.slug]; }).map(function (g) {
            var tax = groupMap[g.slug];
            return el(PanelBody, {
                key: tax.slug,
                title: tax.label,
                initialOpen: g.open,
                className: 'kg-wf-panel kg-wf-panel-' + tax.slug
            }, el(TermPicker, {group: tax}));
        }),
        ),
        el(PluginDocumentSettingPanel, {name: 'kg-recipe-tools', title: 'Beslenme Güvenliği ve AI', className: 'kg-workflow-sidebar'},
        el('div', {className: 'kg-wf-section-caption kg-wf-section-caption--second'}, 'YAYIN ARAÇLARI'),
        el(PanelBody, {title: 'Beslenme Güvenliği', initialOpen: !!cfg.warning, className: 'kg-wf-panel'},
            el(SafetyControl, {isDirty: editorState.dirty})
        ),
        el(PanelBody, {title: 'Yapay Zekâ Desteği', initialOpen: false, className: 'kg-wf-panel'},
            el('div', {className: 'kg-wf-ai'},
                el('p', null, 'Eksik tarif bilgilerini AI ile tamamlamak veya yeniden oluşturmak için.'),
                el(Button, {variant: 'secondary', onClick: openAI}, 'AI ile Zenginleştir'),
                el('small', null, 'Değişiklikleri onaylamadan önce içeriği ve yaş uygunluğunu kontrol et.')
            )
        ),
        ));
    }

    wp.plugins.registerPlugin('kg-recipe-workflow', {render: WorkflowSidebar});

})(window.wp);
