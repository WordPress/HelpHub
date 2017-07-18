<?php
/**
 * PHPUnit bootstrap file
 *
 * @package HelpHub
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_theme_and_plugin() {
	$theme_dir = dirname( dirname( __FILE__ ) ) . '/themes/helphub';
	$current_theme = basename( $theme_dir );

	register_theme_directory( dirname( $theme_dir ) );

	add_filter(
		'pre_option_template', function() use ( $current_theme ) {
			return $current_theme;
		}
	);

	add_filter(
		'pre_option_stylesheet', function() use ( $current_theme ) {
			return $current_theme;
		}
	);

	add_filter(
		'template_directory', function() use ( $theme_dir ) {
			return $theme_dir;
		}
	);

	require dirname( dirname( __FILE__ ) ) . '/plugins/helphub-post-types/helphub-post-types.php';
	require dirname( dirname( __FILE__ ) ) . '/plugins/helphub-read-time/helphub-read-time.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_theme_and_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
