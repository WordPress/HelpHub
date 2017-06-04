jQuery(document).ready(function($){


	// Instantiates the variable that holds the media library frame.
	var gallery_data_frame;

	// Runs when the image button is clicked.
	jQuery( '.postbox' ).on( 'click', '.helphub-upload', function( event ) {

		// Prevents the default action from occuring.
		event.preventDefault();

		// store button object
		$button = $(this);

		// If the frame already exists, re-open it.
		if ( gallery_data_frame ) {
			gallery_data_frame.open();
			return;
		}

		title = $button.data( 'title' ) ? $button.data( 'title' ) : helphub_admin.default_title;
		button = $button.data( 'button' ) ? $button.data( 'button' ) : helphub_admin.default_button;
		library = $button.data( 'library' ) ? $button.data( 'library' ) : '';

		// Sets up the media library frame
		gallery_data_frame = wp.media.frames.gallery_data_frame = wp.media({
			title: title,
			button: { text: button },
			library: { type: library }
		});

		// Runs when an image is selected.
		gallery_data_frame.on( 'select', function(){

			// Grabs the attachment selection and creates a JSON representation of the model.
			var media_attachment = gallery_data_frame.state().get( 'selection' ).first().toJSON();

			// Sends the attachment URL to our custom image input field.
			$button.prev( 'input.helphub-upload-field' ).val( media_attachment.url );

		});

		// Opens the media library frame.
		gallery_data_frame.open();
	});

	if ( $( 'input[type="date"]' ).hasClass( 'helphub-meta-date' ) ) {
		$( '.helphub-meta-date' ).datepicker({
			changeMonth: 	true,
			changeYear:		true,
			formatDate:		'MM, dd, yy'
		});
	} // bust cache

});