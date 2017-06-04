(function($) {

var el = {},
	iframe = {},
	run, mode, nonce;

run = function() {
	var input = mode.inputs[ mode.mode ];

	if ( ! input )
		return;

	// Trigger CodeMirror save if editor exists.
	if ( mode.editors[ mode.mode ] )
		mode.editors[ mode.mode ].save();

	$.post( ajaxurl, {
		action: 'debug_bar_console',
		mode:   mode.mode,
		data:   input.val(),
		nonce:  nonce
	}, function( data ) {
		iframe.body.html( data );
	});
	return false;
};


mode =  {
	mode: 'php',
	tabs: {},
	inputs: {},
	change: function( to ) {
		if ( to == mode.mode )
			return;

		if ( mode.tabs[ mode.mode ] )
			mode.tabs[ mode.mode ].removeClass( 'debug-bar-console-tab-active' );

		el.form.removeClass( 'debug-bar-console-mode-' + mode.mode );
		el.form.addClass( 'debug-bar-console-mode-' + to );
		mode.mode = to;

		if ( mode.tabs[ mode.mode ] )
			mode.tabs[ mode.mode ].addClass( 'debug-bar-console-tab-active' );

		mode.maybeInitEditor();
	},
	editors: {},
	codemirror: {
		modes: {
			php: 'application/x-httpd-php',
			sql: 'text/x-mysql'
		},
		defaults: {
			lineNumbers: true,
			lineWrapping: true,
			matchBrackets: true,
			indentUnit: 4,
			indentWithTabs: true,
			enterMode: 'keep',
			onKeyEvent: function(i, e) {
				// Hook into shift-enter
				if ( e.keyCode == 13 && e.shiftKey ) {
					e.stop();
					run();
					return true;
				}
			}
		}
	},
	maybeInitEditor: function() {
		var args, editor;

		// Check if the current mode is defined and has an input.
		if ( ! mode.codemirror.modes[ mode.mode ] || ! mode.inputs[ mode.mode ] )
			return;

		// Initialize the mode if necessary.
		if ( ! mode.editors[ mode.mode ] ) {
			args = $.extend( {}, mode.codemirror.defaults, { mode: mode.codemirror.modes[ mode.mode ] });
			mode.editors[ mode.mode ] = CodeMirror.fromTextArea( mode.inputs[ mode.mode ][0], args );

			// Trigger show on the form to ensure CodeMirror is running properly.
			el.form.show();
		}

		editor = mode.editors[ mode.mode ];
		editor.focus();
		editor.setCursor( editor.lineCount() );
	}
};

$(document).ready( function(){
	// Generate elements
	$.extend( el, {
		form:   $('#debug-bar-console'),
		submit: $('#debug-bar-console-submit a'),
		output: $('#debug-bar-console-output')
	});
	el.wrap = el.form.parent();

	nonce = $('#_wpnonce_debug_bar_console').val();

	iframe.css = $('#debug-bar-console-iframe-css').val();

	// Generate iframe variables
	iframe.container = $('iframe', el.output);
	iframe.contents  = iframe.container.contents();
	iframe.document  = iframe.contents[0];
	iframe.body      = $( iframe.document.body );

	// Add CSS to iframe
	$('head', iframe.contents).append('<link type="text/css" href="' + iframe.css + '" rel="stylesheet" />');

	// Bind run click handler
	el.submit.click( run );

	// Bind default loading
	el.wrap.bind( 'debug-bar-show', function() {
		mode.maybeInitEditor();
	});

	// Bind tab switching
	$('.debug-bar-console-tab').each( function() {
		var t = $(this),
			slug = t.find('input').val(),
			input;

		if ( slug ) {
			mode.tabs[ slug ] = t;
			t.data( 'console-tab', slug );
			input = $( '#debug-bar-console-input-' + slug );
			if ( input.length )
				mode.inputs[ slug ] = input;
		}

	}).click( function() {
		mode.change( $(this).data( 'console-tab' ) );
	});
});

})(jQuery);