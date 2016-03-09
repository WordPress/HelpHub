/**
* Dynamic functionality for voting on articles
*
*/
( function( $ ) {
    function click() {
        $( '.helphub-voting a' ).on( 'click', function(e) {
            e.preventDefault();
            var item = $(this),
                input = $('#vote-input');
                vote = item.attr('data-vote');

            input.val(vote);

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

    $( '#commentform' ).on( 'submit', function(e) {
        e.preventDefault();
        var form = $(this),
            data = form.serialize(),
            url = form.attr('action');
        
        form.css('opacity', 0.5);

        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            success: function(data, status) {
              form.hide();
              $('#feedback-thanks').text('Thanks for your feedback!');
            }
        })

    })

} )( jQuery );