/**
 * KG Recipe AI Enrichment
 * Handles AI-powered recipe enrichment modal and AJAX calls
 */

(function($) {
    'use strict';
    
    // Show modal
    window.kgShowRecipeEnrichModal = function() {
        $('#kg-recipe-enrich-modal').fadeIn(200);
    };
    
    // Hide modal
    function hideModal() {
        $('#kg-recipe-enrich-modal').fadeOut(200);
        resetModal();
    }
    
    // Reset modal to initial state
    function resetModal() {
        $('#kg-recipe-enrich-progress').hide();
        $('#kg-recipe-enrich-result').hide().removeClass('success error');
        $('.kg-progress-fill').css('width', '0%');
        $('#kg-recipe-enrich-start').prop('disabled', false).text('Zenginleştir');
    }
    
    // Update progress bar
    function updateProgress(percent, text) {
        $('.kg-progress-fill').css('width', percent + '%');
        $('.kg-progress-text').text(text);
    }
    
    // Show result message
    function showResult(message, isSuccess) {
        $('#kg-recipe-enrich-result')
            .removeClass('success error')
            .addClass(isSuccess ? 'success' : 'error')
            .html(message)
            .fadeIn(200);
    }
    
    $(document).ready(function() {
        // Cancel button
        $('#kg-recipe-enrich-cancel').on('click', function() {
            hideModal();
        });
        
        // Close modal on overlay click
        $('.kg-enrich-modal-overlay').on('click', function() {
            hideModal();
        });
        
        // Start enrichment
        $('#kg-recipe-enrich-start').on('click', function() {
            var btn = $(this);
            var fillEmptyOnly = $('#kg_recipe_enrich_fill_empty').is(':checked');
            
            // Disable button
            btn.prop('disabled', true).text('İşleniyor...');
            
            // Show progress
            $('#kg-recipe-enrich-progress').show();
            updateProgress(10, 'Tarif verileri toplanıyor...');
            
            // Make AJAX call
            $.ajax({
                url: kgRecipeEnrich.ajaxurl,
                type: 'POST',
                data: {
                    action: 'kg_ai_enrich_recipe',
                    nonce: kgRecipeEnrich.nonce,
                    post_id: kgRecipeEnrich.post_id,
                    fill_empty_only: fillEmptyOnly ? 'true' : 'false'
                },
                beforeSend: function() {
                    updateProgress(30, 'AI ile içerik oluşturuluyor...');
                },
                success: function(response) {
                    updateProgress(90, 'Veriler kaydediliyor...');
                    
                    setTimeout(function() {
                        updateProgress(100, 'Tamamlandı!');
                        
                        if (response && response.success) {
                            showResult('<strong>✓ Başarılı!</strong><br>' + (response.data.message || 'Tarif zenginleştirildi.'), true);
                            
                            // Reload page after 2 seconds
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            var errorMsg = (response && response.data) ? response.data : 'Bilinmeyen hata oluştu.';
                            showResult('<strong>✗ Hata!</strong><br>' + errorMsg, false);
                            btn.prop('disabled', false).text('Zenginleştir');
                        }
                    }, 500);
                },
                error: function(xhr, status, error) {
                    updateProgress(0, 'Hata oluştu');
                    
                    var errorMsg = 'Bağlantı hatası: ' + error;
                    if (xhr && xhr.responseText) {
                        try {
                            var resp = JSON.parse(xhr.responseText);
                            if (resp.data) {
                                errorMsg = resp.data;
                            }
                        } catch (parseError) {
                            if (xhr.responseText.length < 200) {
                                errorMsg = xhr.responseText;
                            }
                        }
                    }
                    
                    showResult('<strong>✗ Hata!</strong><br>' + errorMsg, false);
                    btn.prop('disabled', false).text('Zenginleştir');
                }
            });
        });
    });
})(jQuery);
