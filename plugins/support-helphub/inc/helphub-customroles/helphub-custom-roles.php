<?php
/**
 * Plugin Name: HelpHub Custom Roles
 * Version: 1.0
 * Plugin URI: https://carl.alber2.com/
 * Description: This adds HelpHub custom roles.
 * Author: Carl Alberto
 * Author URI: https://carl.alber2.com/
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * Text Domain: wporg-forums
 * Domain Path: /lang/
 *
 * @package HelpHub
 * @author Carl Alberto
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load plugin class files.
require_once 'includes/class-helphub-custom-roles.php';

/**
 * Returns the main instance of HelpHub_Custom_Roles to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object HelpHub_Custom_Roles
 */
function helphub_custom_roles() {
	$instance = HelpHub_Custom_Roles::instance( __FILE__, '1.0.0' );
	return $instance;
}

helphub_custom_roles();
