/**
 * KG Core - Recipe MetaBox Admin JavaScript
 * Handles repeater fields, drag-and-drop, and autocomplete
 */

(function($) {
    'use strict';

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initIngredientRepeater();
        initInstructionRepeater();
        initSubstituteRepeater();
        initIngredientAutocomplete();
    });

    /**
     * Ingredient Repeater
     */
    function initIngredientRepeater() {
        const $container = $('#kg-ingredients-repeater');
        if (!$container.length) return;

        const $items = $container.find('.kg-repeater-items');
        const $addBtn = $container.find('.kg-add-item');
        let itemCounter = $items.find('.kg-repeater-item').length;

        // Make sortable
        $items.sortable({
            handle: '.kg-drag-handle',
            placeholder: 'ui-sortable-placeholder',
            axis: 'y',
            opacity: 0.8,
            cursor: 'move'
        });

        // Add new item
        $addBtn.on('click', function(e) {
            e.preventDefault();
            const index = 'new_' + Date.now() + '_' + (++itemCounter);
            const template = getIngredientTemplate(index);
            $items.append(template);
            initIngredientAutocomplete();
        });

        // Remove item
        $container.on('click', '.kg-remove-item', function(e) {
            e.preventDefault();
            $(this).closest('.kg-repeater-item').fadeOut(300, function() {
                $(this).remove();
            });
        });
    }

    /**
     * Instruction Repeater
     */
    function initInstructionRepeater() {
        const $container = $('#kg-instructions-repeater');
        if (!$container.length) return;

        const $items = $container.find('.kg-repeater-items');
        const $addBtn = $container.find('.kg-add-item');
        let itemCounter = $items.find('.kg-repeater-item').length;

        // Make sortable
        $items.sortable({
            handle: '.kg-drag-handle',
            placeholder: 'ui-sortable-placeholder',
            axis: 'y',
            opacity: 0.8,
            cursor: 'move',
            update: function() {
                updateInstructionNumbers();
            }
        });

        // Add new item
        $addBtn.on('click', function(e) {
            e.preventDefault();
            const index = 'new_' + Date.now() + '_' + (++itemCounter);
            const stepNumber = $items.find('.kg-repeater-item').length + 1;
            const template = getInstructionTemplate(index, stepNumber);
            $items.append(template);
            updateInstructionNumbers();
        });

        // Remove item
        $container.on('click', '.kg-remove-item', function(e) {
            e.preventDefault();
            $(this).closest('.kg-repeater-item').fadeOut(300, function() {
                $(this).remove();
                updateInstructionNumbers();
            });
        });

        // Initial numbering
        updateInstructionNumbers();
    }

    /**
     * Substitute Repeater
     */
    function initSubstituteRepeater() {
        const $container = $('#kg-substitutes-repeater');
        if (!$container.length) return;

        const $items = $container.find('.kg-repeater-items');
        const $addBtn = $container.find('.kg-add-item');
        let itemCounter = $items.find('.kg-repeater-item').length;

        // Make sortable
        $items.sortable({
            handle: '.kg-drag-handle',
            placeholder: 'ui-sortable-placeholder',
            axis: 'y',
            opacity: 0.8,
            cursor: 'move'
        });

        // Add new item
        $addBtn.on('click', function(e) {
            e.preventDefault();
            const index = 'new_' + Date.now() + '_' + (++itemCounter);
            const template = getSubstituteTemplate(index);
            $items.append(template);
        });

        // Remove item
        $container.on('click', '.kg-remove-item', function(e) {
            e.preventDefault();
            $(this).closest('.kg-repeater-item').fadeOut(300, function() {
                $(this).remove();
            });
        });
    }

    /**
     * Ingredient Autocomplete
     */
    function initIngredientAutocomplete() {
        const $inputs = $('.kg-ingredient-name');
        
        $inputs.each(function() {
            const $input = $(this);
            const $wrap = $input.closest('.kg-autocomplete-wrap');
            let $results = $wrap.find('.kg-autocomplete-results');
            
            // Create results container if it doesn't exist
            if (!$results.length) {
                $results = $('<div class="kg-autocomplete-results"></div>');
                $wrap.append($results);
            }

            let searchTimeout;
            let selectedIndex = -1;

            // Handle input
            $input.on('input', function() {
                const query = $(this).val().trim();
                
                clearTimeout(searchTimeout);
                
                if (query.length < 2) {
                    $results.removeClass('active').empty();
                    return;
                }

                searchTimeout = setTimeout(function() {
                    searchIngredients(query, $results, $input);
                }, 200);
            });

            // Handle keyboard navigation
            $input.on('keydown', function(e) {
                const $items = $results.find('.kg-autocomplete-item');
                
                if (!$items.length) return;

                // Down arrow
                if (e.keyCode === 40) {
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, $items.length - 1);
                    updateSelection($items, selectedIndex);
                }
                // Up arrow
                else if (e.keyCode === 38) {
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, -1);
                    updateSelection($items, selectedIndex);
                }
                // Enter
                else if (e.keyCode === 13 && selectedIndex >= 0) {
                    e.preventDefault();
                    $items.eq(selectedIndex).click();
                }
                // Escape
                else if (e.keyCode === 27) {
                    $results.removeClass('active').empty();
                    selectedIndex = -1;
                }
            });

            // Handle click outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest($wrap).length) {
                    $results.removeClass('active').empty();
                    selectedIndex = -1;
                }
            });
        });
    }

    /**
     * Search ingredients via AJAX
     */
    function searchIngredients(query, $results, $input) {
        $results.html('<div class="kg-autocomplete-loading">Aranıyor...</div>').addClass('active');

        $.ajax({
            url: kgMetaBox.ajaxUrl,
            type: 'GET',
            data: {
                action: 'kg_search_ingredients',
                nonce: kgMetaBox.nonce,
                q: query
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    let html = '';
                    response.data.forEach(function(item) {
                        html += '<div class="kg-autocomplete-item" data-id="' + item.id + '" data-name="' + item.name + '">' + item.name + '</div>';
                    });
                    $results.html(html).addClass('active');
                    
                    // Handle item click
                    $results.find('.kg-autocomplete-item').on('click', function() {
                        const name = $(this).data('name');
                        const id = $(this).data('id');
                        
                        $input.val(name);
                        $input.closest('.kg-ingredient-row').find('.kg-ingredient-id').val(id);
                        $results.removeClass('active').empty();
                    });
                } else {
                    $results.html('<div class="kg-autocomplete-loading">Sonuç bulunamadı</div>');
                }
            },
            error: function() {
                $results.html('<div class="kg-autocomplete-loading">Hata oluştu</div>');
            }
        });
    }

    /**
     * Update autocomplete selection
     */
    function updateSelection($items, index) {
        $items.removeClass('selected');
        if (index >= 0) {
            $items.eq(index).addClass('selected');
        }
    }

    /**
     * Update instruction step numbers
     */
    function updateInstructionNumbers() {
        $('#kg-instructions-repeater .kg-repeater-item').each(function(index) {
            $(this).find('.kg-instruction-header').text('Adım ' + (index + 1));
            $(this).find('.kg-instruction-id').val(index + 1);
        });
    }

    /**
     * Get ingredient template HTML
     */
    function getIngredientTemplate(index) {
        return `
            <div class="kg-repeater-item">
                <div class="kg-drag-handle"></div>
                <button type="button" class="kg-remove-item" title="Kaldır">×</button>
                <div class="kg-item-content">
                    <div class="kg-ingredient-row">
                        <div>
                            <label>Miktar</label>
                            <input type="text" name="kg_ingredients[${index}][amount]" class="kg-ingredient-amount" placeholder="2">
                        </div>
                        <div>
                            <label>Birim</label>
                            <select name="kg_ingredients[${index}][unit]" class="kg-ingredient-unit">
                                <option value="adet">Adet</option>
                                <option value="su bardağı">Su Bardağı</option>
                                <option value="yemek kaşığı">Yemek Kaşığı</option>
                                <option value="çay kaşığı">Çay Kaşığı</option>
                                <option value="gram">Gram</option>
                                <option value="ml">ML</option>
                                <option value="kg">KG</option>
                                <option value="litre">Litre</option>
                                <option value="tutam">Tutam</option>
                            </select>
                        </div>
                        <div class="kg-autocomplete-wrap">
                            <label>Malzeme Adı</label>
                            <input type="text" name="kg_ingredients[${index}][name]" class="kg-ingredient-name" placeholder="Un" autocomplete="off">
                            <input type="hidden" name="kg_ingredients[${index}][ingredient_id]" class="kg-ingredient-id" value="">
                        </div>
                    </div>
                    <div class="kg-ingredient-note-row" style="margin-top: 8px;">
                        <label>Not <small>(opsiyonel - kullanıcıya gösterilecek ipucu)</small></label>
                        <input type="text" name="kg_ingredients[${index}][note]" class="kg-ingredient-note" placeholder="Örn: Oda sıcaklığında olmalı, taze sıkılmış tercih edin" style="width: 100%;">
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Get instruction template HTML
     */
    function getInstructionTemplate(index, stepNumber) {
        return `
            <div class="kg-repeater-item">
                <div class="kg-drag-handle"></div>
                <button type="button" class="kg-remove-item" title="Kaldır">×</button>
                <div class="kg-item-content">
                    <div class="kg-instruction-header">Adım ${stepNumber}</div>
                    <div class="kg-instruction-fields">
                        <input type="hidden" name="kg_instructions[${index}][id]" class="kg-instruction-id" value="${stepNumber}">
                        <div>
                            <label>Adım Başlığı</label>
                            <input type="text" name="kg_instructions[${index}][title]" class="kg-instruction-title" placeholder="Malzemeleri hazırlayın">
                        </div>
                        <div>
                            <label>Açıklama</label>
                            <textarea name="kg_instructions[${index}][text]" class="kg-instruction-text" placeholder="Havuçları yıkayıp soyun, küçük küpler halinde doğrayın..." rows="3"></textarea>
                        </div>
                        <div>
                            <label>Püf Noktası <small>(opsiyonel)</small></label>
                            <input type="text" name="kg_instructions[${index}][tip]" class="kg-instruction-tip" placeholder="İnce rendeleyin">
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Get substitute template HTML
     */
    function getSubstituteTemplate(index) {
        return `
            <div class="kg-repeater-item">
                <div class="kg-drag-handle"></div>
                <button type="button" class="kg-remove-item" title="Kaldır">×</button>
                <div class="kg-item-content">
                    <div class="kg-substitute-row">
                        <div>
                            <label>Orijinal Malzeme</label>
                            <input type="text" name="kg_substitutes[${index}][original]" class="kg-substitute-original" placeholder="Süt">
                        </div>
                        <div>
                            <label>İkame Malzeme</label>
                            <input type="text" name="kg_substitutes[${index}][substitute]" class="kg-substitute-substitute" placeholder="Badem sütü">
                        </div>
                        <div>
                            <label>Not <small>(opsiyonel)</small></label>
                            <input type="text" name="kg_substitutes[${index}][note]" class="kg-substitute-note" placeholder="Laktoz intoleransı için">
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

})(jQuery);
