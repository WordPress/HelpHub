<?php
/**
 * Plugin Name: Shiny Updates
 * Plugin URI: https://github.com/obenland/shiny-updates
 * Description: A smoother experience for managing plugins and themes.
 * Author: the WordPress team
 * Author URI: https://github.com/obenland/shiny-updates
 * Version: 3-20160927
 * License: GPL2
 *
 * @package Shiny_Updates
 */

/**
 * Initializes the plugin.
 *
 * @codeCoverageIgnore
 */
function su_init() {
	require_once( dirname( __FILE__ ) . '/src/functions.php' );
	require_once( dirname( __FILE__ ) . '/src/ajax-actions.php' );
	require_once( dirname( __FILE__ ) . '/src/default-filters.php' );
}

function su_requirements_notice() {
	$plugin_file = plugin_basename( __FILE__ );
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<?php printf(
				__( 'Please note: Shiny Updates v3 requires WordPress 4.6-alpha-37714 or higher. <a href="%s">Deactivate plugin</a>.' ),
				wp_nonce_url(
					add_query_arg(
						array(
							'action'        => 'deactivate',
							'plugin'        => $plugin_file,
							'plugin_status' => 'all',
						),
						admin_url( 'plugins.php' )
					),
					'deactivate-plugin_' . $plugin_file
				)
			); ?>
		</p>
	</div>
	<?php
}

if ( version_compare( $GLOBALS['wp_version'], '4.6-alpha-37714', '<' ) ) {
	add_action( 'admin_notices', 'su_requirements_notice' );
} else {
	add_action( 'plugins_loaded', 'su_init' );
}

