<?php

/**
 * No Search Results Feedback Part
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<div class="bbp-template-notice">
	<ul>
		<li><?php esc_html_e( 'Oh, bother! No search results were found here.', 'bbpress' ); ?></li>
	</ul>
</div>
