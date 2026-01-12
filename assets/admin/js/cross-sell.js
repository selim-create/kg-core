/**
 * KG Core - Cross-Sell JavaScript
 * Handles Tariften.com integration for cross-sell suggestions
 */

jQuery(document).ready(function($) {
    // Mod değişimi
    $('input[name="kg_cross_sell_mode"]').on('change', function() {
        var mode = $(this).val();
        if (mode === 'manual') {
            $('.kg-cross-sell-manual').show();
            $('.kg-cross-sell-auto').hide();
        } else {
            $('.kg-cross-sell-manual').hide();
            $('.kg-cross-sell-auto').show();
        }
    });
    
    // Öneri getir
    $('#kg_fetch_suggestions').on('click', function() {
        var ingredient = $('#kg_cross_sell_ingredient').val();
        var $container = $('#kg_suggestions_container');
        var $button = $(this);
        
        if (!ingredient) {
            alert('Lütfen bir malzeme seçin');
            return;
        }
        
        $button.prop('disabled', true).text('Yükleniyor...');
        $container.html('<p>🔄 Tariften.com\'dan öneriler getiriliyor...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'kg_fetch_tariften_suggestions',
                nonce: kg_cross_sell.nonce,
                ingredient: ingredient
            },
            success: function(response) {
                $button.prop('disabled', false).text('🔄 Öneri Getir');
                
                if (response.success && response.data.length > 0) {
                    var html = '<div class="kg-suggestions-list">';
                    response.data.forEach(function(recipe) {
                        html += '<div class="kg-suggestion-item" data-id="' + recipe.id + '" data-url="' + recipe.url + '" data-title="' + recipe.title + '" data-image="' + (recipe.image || '') + '">';
                        html += '<img src="' + (recipe.image || 'https://placehold.co/100x80') + '" alt="" style="width:80px;height:60px;object-fit:cover;border-radius:4px;">';
                        html += '<div class="kg-suggestion-info">';
                        html += '<strong>' + recipe.title + '</strong><br>';
                        html += '<small>' + (recipe.prep_time || '') + ' • ' + (recipe.difficulty || '') + '</small>';
                        html += '</div>';
                        html += '<button type="button" class="button kg-select-suggestion">✓ Seç</button>';
                        html += '</div>';
                    });
                    html += '</div>';
                    $container.html(html);
                } else {
                    $container.html('<p>❌ Bu malzeme için öneri bulunamadı.</p>');
                }
            },
            error: function() {
                $button.prop('disabled', false).text('🔄 Öneri Getir');
                $container.html('<p>❌ Bağlantı hatası. Lütfen tekrar deneyin.</p>');
            }
        });
    });
    
    // Öneri seç
    $(document).on('click', '.kg-select-suggestion', function() {
        var $item = $(this).closest('.kg-suggestion-item');
        
        $('#kg_cross_sell_selected_id').val($item.data('id'));
        $('#kg_cross_sell_selected_url').val($item.data('url'));
        $('#kg_cross_sell_selected_title').val($item.data('title'));
        $('#kg_cross_sell_selected_image').val($item.data('image'));
        
        $('.kg-suggestion-item').removeClass('selected');
        $item.addClass('selected');
        
        $(this).text('✓ Seçildi').prop('disabled', true);
        $('.kg-suggestion-item').not($item).find('.kg-select-suggestion').text('✓ Seç').prop('disabled', false);
    });
});
