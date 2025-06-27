jQuery(document).ready(function($) {
    // Toggle plugin status
    $('#acs-active-toggle').change(function() {
        var $toggle = $(this);
        var $slider = $toggle.siblings('.acs-toggle-slider');
        var isActive = $toggle.is(':checked') ? 1 : 0;
        
        $slider.css('opacity', '0.7');
        
        $.ajax({
            url: acsAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'acs_toggle_status',
                status: isActive,
                _wpnonce: acsAdmin.nonce,
                _wp_http_referer: acsAdmin.referer
            },
            success: function() {
                $slider.css('opacity', '1')
                       .css('background-color', isActive ? '#2271b1' : '#ccc');
            },
            error: function(xhr) {
                $toggle.prop('checked', !isActive);
                $slider.css('opacity', '1');
                console.error('Error:', xhr.responseText);
            }
        });
    });
    
    // Auto-resize textareas
    $('.acs-user-box textarea').each(function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    }).on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
});
