(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Upload avatar button
        $('#kg-upload-avatar').on('click', function(e) {
            e.preventDefault();
            
            var frame = wp.media({
                title: 'Profil Fotoğrafı Seç',
                button: { text: 'Fotoğrafı Kullan' },
                multiple: false,
                library: { type: 'image' }
            });
            
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                var imageUrl = attachment.sizes && attachment.sizes.thumbnail 
                    ? attachment.sizes.thumbnail.url 
                    : attachment.url;
                    
                $('#kg_avatar_id').val(attachment.id);
                $('#kg-avatar-preview').html('<img src="' + imageUrl + '" style="max-width: 150px; height: auto; border-radius: 50%;">');
                $('#kg-remove-avatar').show();
            });
            
            frame.open();
        });
        
        // Remove avatar button
        $('#kg-remove-avatar').on('click', function(e) {
            e.preventDefault();
            $('#kg_avatar_id').val('');
            $('#kg-avatar-preview').html('');
            $(this).hide();
        });
    });
})(jQuery);
