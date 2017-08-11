/**
 * File skip-link-focus-fix.js
 *
 * Helps with accessibility for keyboard only users.
 *
 * Learn more: https://git.io/vWdr2
 */
( function() {
	var IsWebkit = navigator.userAgent.toLowerCase().indexOf( 'webkit' ) > -1,
		IsOpera  = navigator.userAgent.toLowerCase().indexOf( 'opera' )  > -1,
		IsIe     = navigator.userAgent.toLowerCase().indexOf( 'msie' )   > -1;

	if ( ( IsWebkit || IsOpera || IsIe ) && document.getElementById && window.addEventListener ) {
		window.addEventListener( 'hashchange', function() {
			var id = location.hash.substring( 1 ),
				element;

			if ( ! ( /^[A-z0-9_-]+$/.test( id ) ) ) {
				return;
			}

			element = document.getElementById( id );

			if ( element ) {
				if ( ! ( /^(?:a|select|input|button|textarea)$/i.test( element.tagName ) ) ) {
					element.tabIndex = -1;
				}

				element.focus();
			}
		}, false );
	}
})();
