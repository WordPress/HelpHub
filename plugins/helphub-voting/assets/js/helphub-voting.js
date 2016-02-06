/**
* Dynamic functionality for voting on articles
*
*/
( function( $ ) {
    function click() {
        $( '.helphub-voting a' ).on( 'click', function(e) {
            e.preventDefault();
            var item = $(this);
            $.post(ajaxurl, {
                action:   'helphub_vote',
                post:     $(this).attr('data-id'),
                vote:     $(this).attr('data-vote'),
                _wpnonce: $(this).parent().attr('data-nonce')
                }, function(data) {
                    if (data != '0') {
                        item.closest('.helphub-voting').replaceWith(data);
                        click();
                    }
                }, "text"
            );
        return false;
        });
    }

    click();

} )( jQuery );