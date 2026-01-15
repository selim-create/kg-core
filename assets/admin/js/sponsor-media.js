jQuery(document).ready(function($) {
    'use strict';

    // Toggle sponsor fields visibility based on checkbox (for posts)
    $('#kg_is_sponsored').on('change', function() {
        if ($(this).is(':checked')) {
            $('#kg-sponsor-fields').slideDown();
        } else {
            $('#kg-sponsor-fields').slideUp();
        }
    });

    // Toggle sponsor fields visibility based on checkbox (for tools)
    $('#kg_tool_is_sponsored').on('change', function() {
        if ($(this).is(':checked')) {
            $('#kg-tool-sponsor-fields').slideDown();
        } else {
            $('#kg-tool-sponsor-fields').slideUp();
        }
    });

    // WordPress Media Uploader - Store instances by target ID
    var mediaUploaderInstances = {};

    // Upload logo button click handler
    $('.kg-upload-logo').on('click', function(e) {
        e.preventDefault();

        var button = $(this);
        var targetId = button.data('target');
        var previewId = targetId + '_preview';

        // Get or create media uploader instance for this target
        if (!mediaUploaderInstances[targetId]) {
            mediaUploaderInstances[targetId] = wp.media({
                title: 'Logo Seçin',
                button: {
                    text: 'Logo Kullan'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            // Configure selection handler for this instance
            mediaUploaderInstances[targetId].on('select', function() {
                var attachment = mediaUploaderInstances[targetId].state().get('selection').first().toJSON();
                
                // Set the attachment ID
                $('#' + targetId).val(attachment.id);
                
                // Update preview
                var imgHtml = '<img src="' + attachment.url + '" style="max-width: 200px; height: auto;">';
                $('#' + previewId).html(imgHtml);
                
                // Show remove button
                $('[data-target="' + targetId + '"].kg-remove-logo').show();
            });
        }

        // Open the media uploader
        mediaUploaderInstances[targetId].open();
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
