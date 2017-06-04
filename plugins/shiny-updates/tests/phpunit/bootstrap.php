<?php
/**
 * Bootstraps unit tests.
 */

/**
 * Determines where the WordPress test suite lives.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress/tests/phpunit';
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load plugin.
 */
function _manually_load_shiny_updates() {
	require_once dirname( dirname( __DIR__ ) ) . '/shiny-updates.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_shiny_updates' );

require $_tests_dir . '/includes/bootstrap.php';
