jQuery(document).ready(function($) {
    'use strict';

    // Toggle sponsor fields visibility based on checkbox
    $('#kg_is_sponsored').on('change', function() {
        if ($(this).is(':checked')) {
            $('#kg-sponsor-fields').slideDown();
        } else {
            $('#kg-sponsor-fields').slideUp();
        }
    });

    // Upload logo button click handler
    $('.kg-upload-logo').on('click', function(e) {
        e.preventDefault();

        var button = $(this);
        var targetId = button.data('target');
        var previewId = targetId + '_preview';

        // Create a new media uploader instance for each button click
        var mediaUploader = wp.media({
            title: 'Logo Seçin',
            button: {
                text: 'Logo Kullan'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });

        // When an image is selected
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            
            // Set the attachment ID
            $('#' + targetId).val(attachment.id);
            
            // Update preview
            var imgHtml = '<img src="' + attachment.url + '" style="max-width: 200px; height: auto;">';
            $('#' + previewId).html(imgHtml);
            
            // Show remove button
            button.siblings('.kg-remove-logo').show();
        });

        // Open the media uploader
        mediaUploader.open();
    });

    // Remove logo button click handler
    $('.kg-remove-logo').on('click', function(e) {
        e.preventDefault();

        var button = $(this);
        var targetId = button.data('target');
        var previewId = targetId + '_preview';

        // Clear the attachment ID
        $('#' + targetId).val('');
        
        // Clear preview
        $('#' + previewId).html('');
        
        // Hide remove button
        button.hide();
    });
});
