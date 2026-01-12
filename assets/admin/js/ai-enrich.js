/**
 * AI Enrich functionality for ingredient pages
 */
(function($) {
    'use strict';
    
    window.kgShowEnrichModal = function() {
        $('#kg-enrich-modal').fadeIn(200);
        $('#kg-enrich-progress').hide();
        $('#kg-enrich-result').hide();
        $('#kg-enrich-start').prop('disabled', false);
    };
    
    function hideEnrichModal() {
        $('#kg-enrich-modal').fadeOut(200);
    }
    
    function showProgress(message) {
        $('#kg-enrich-progress').show();
        $('#kg-enrich-result').hide();
        $('.kg-progress-text').text(message);
        $('.kg-progress-fill').css('width', '50%');
        $('#kg-enrich-start').prop('disabled', true);
    }
    
    function showResult(message, isSuccess) {
        $('#kg-enrich-progress').hide();
        $('#kg-enrich-result')
            .removeClass('success error')
            .addClass(isSuccess ? 'success' : 'error')
            .html(message)
            .show();
        $('#kg-enrich-start').prop('disabled', false);
        
        if (isSuccess) {
            // Reload page after 2 seconds to show updated content
            setTimeout(function() {
                window.location.reload();
            }, 2000);
        }
    }
    
    $(document).ready(function() {
        // Cancel button
        $('#kg-enrich-cancel').on('click', function() {
            hideEnrichModal();
        });
        
        // Close on overlay click
        $('.kg-enrich-modal-overlay').on('click', function() {
            hideEnrichModal();
        });
        
        // Start enrichment
        $('#kg-enrich-start').on('click', function() {
            const overwrite = $('#kg_enrich_overwrite').is(':checked');
            const generateImage = $('#kg_enrich_generate_image').is(':checked');
            
            showProgress('AI ile içerik oluşturuluyor...');
            
            $.ajax({
                url: kgEnrich.ajaxurl,
                type: 'POST',
                data: {
                    action: 'kg_enrich_ingredient',
                    nonce: kgEnrich.nonce,
                    post_id: kgEnrich.post_id,
                    overwrite: overwrite.toString(),
                    generate_image: generateImage.toString()
                },
                success: function(response) {
                    if (response.success) {
                        showResult('✅ ' + response.data.message + '<br><small>Sayfa yenileniyor...</small>', true);
                    } else {
                        showResult('❌ Hata: ' + response.data, false);
                    }
                },
                error: function(xhr, status, error) {
                    showResult('❌ Bağlantı hatası: ' + error, false);
                }
            });
        });
    });
    
})(jQuery);
