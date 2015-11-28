// Foundation JavaScript
// Documentation can be found at: http://foundation.zurb.com/docs
(function($) { 
    $(document).foundation();
    
    var alt_value = $('.field_image img').attr('alt'); 
    $('.node-article .field_image img').after('<span class="caption">' + alt_value + '</span>');
    
}(jQuery));