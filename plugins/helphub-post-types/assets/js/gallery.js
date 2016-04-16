/**
 * Created by User on 29/12/2015.
 */
jQuery(document).ready(function($){
    // Uploading files
    var helphub_gallery_frame;
    var $gallery_container 	= $( '#helphub_images_container')
    var $image_gallery_ids 	= $( '#helphub_image_gallery' );
    var $gallery_images 	= $gallery_container.find( 'ul.product_images' );
    var $gallery_ul			= $gallery_container.find( 'ul li.image' );

    jQuery( '.add_helphub_images' ).on( 'click', 'a', function( event ) {

        var $el 			= $(this);
        var attachment_ids 	= $image_gallery_ids.val();

        event.preventDefault();
        //event.stopPropagation();
        //event.stopImmediatePropagation();

        // If the media frame already exists, reopen it.
        if ( helphub_gallery_frame ) {
            helphub_gallery_frame.open();
            return;
        }

        // Create the media frame.
        helphub_gallery_frame = wp.media.frames.downloadable_file = wp.media({
            // Set the title of the modal.
            title: helphub_helphub_gallery.gallery_title,
            button: {
                text: helphub_helphub_gallery.gallery_button,
            },
            multiple: true
        });

        // When an image is selected, run a callback.
        helphub_gallery_frame.on( 'select', function() {

            var selection = helphub_gallery_frame.state().get( 'selection' );

            selection.map( function( attachment ) {

                attachment = attachment.toJSON();

                if ( attachment.id ) {
                    attachment_ids = attachment_ids ? attachment_ids + "," + attachment.id : attachment.id;

                    $gallery_images.append('\
                            <li class="image" data-attachment_id="' + attachment.id + '">\
                                <img src="' + attachment.sizes.thumbnail.url + '" />\
                                    <ul class="actions">\
                                        <li><a href="#" class="delete" title="'+ helphub_helphub_gallery.delete_image +'">&times;</a></li>\
                                    </ul>\
                                </li>');
                }

            } );

            $image_gallery_ids.val( attachment_ids );
        });

        // Finally, open the modal.
        helphub_gallery_frame.open();
    });

    // Image ordering
    $gallery_images.sortable({
        items: 'li.image',
        cursor: 'move',
        scrollSensitivity:40,
        forcePlaceholderSize: true,
        forceHelperSize: false,
        helper: 'clone',
        opacity: 0.65,
        placeholder: 'helphub-metabox-sortable-placeholder',
        start:function(event,ui){
            ui.item.css( 'background-color','#f6f6f6' );
        },
        stop:function(event,ui){
            ui.item.removeAttr( 'style' );
        },
        update: function(event, ui) {
            var attachment_ids = '';
            $gallery_container.find( 'ul li.image' ).css( 'cursor','default' ).each(function() {
                var attachment_id = jQuery(this).attr( 'data-attachment_id' );
                attachment_ids = attachment_ids + attachment_id + ',';
            });
            $image_gallery_ids.val( attachment_ids );
        }
    });
    // Remove images
    $gallery_container.on( 'click', 'a.delete', function() {
        $(this).closest( 'li.image' ).remove();

        var attachment_ids = '';

        $gallery_ul.css( 'cursor','default' ).each(function() {
            var attachment_id = jQuery(this).attr( 'data-attachment_id' );
            attachment_ids = attachment_ids + attachment_id + ',';
        });

        $image_gallery_ids.val( attachment_ids );

        return false;
    } );
} );