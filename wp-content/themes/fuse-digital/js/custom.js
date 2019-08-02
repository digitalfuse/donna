jQuery(document).ready(function( $ ) {
	if ($('[class*=slide-]').hasClass('active-slide')) {
            $('.active-slide').addClass('fd-no-animation');
        } 
    $('#fd-prev').click(function() {
        if ($('[class*=slide-]').hasClass('active-slide')) {
            $('.active-slide').addClass('fd-no-animation');
        } 
    });
});

jQuery(document).ready(function( $ ) {
	if ($('[class*=slide-]').hasClass('active-slide')) {
            $('.active-slide').addClass('fd-no-animation');
        } 
    $('#fd-next').click(function() {
        if ($('[class*=slide-]').hasClass('active-slide')) {
            $('.active-slide').addClass('fd-no-animation');
        } 
    });
});
