/**
 * Dynamic functionality for voting on articles
 *
 */
( function( $ ) {
        $( '.helphub-voting a' ).on( 'click', function(e) {
                e.preventDefault();
                var item = $(this);
                $.post(ajaxurl, {
                                action:   'helphub_vote',
                                post:     $(this).attr('data-id'),
                                vote:     $(this).attr('data-vote'),
                                _wpnonce: $(this).parent().attr('data-nonce')
                        }, function(data) {
                                console.log(data);
                                if ("0" != data) {
                                        item.closest('.helphub-voting a').replaceWith(data);
                                }
                        }, "text"
                );
                return false;
        });
} )( jQuery );