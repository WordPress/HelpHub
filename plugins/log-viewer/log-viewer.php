<?php
/**
 * @package   log-viewer
 * @author    Markus Fischbacher <fischbacher.markus@gmail.com>
 * @license   GPL-2.0+
 * @link      http://wordpress.org/extend/plugins/log-viewer/
 * @copyright 2013 Markus Fischbacher
 *
 * @wordpress-plugin
 * Plugin Name:       Log Viewer
 * Plugin URI:        http://wordpress.org/extend/plugins/log-viewer/
 * Description:       This plugin provides an easy way to view log files directly in the admin panel.
 * Version:           14.05.04
 * Tag:               14.05.04
 * Timestamp:         14.05.04-1559
 * Author:            Markus Fischbacher
 * Author URI:        https://plus.google.com/+MarkusFischbacher
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if( !defined( 'WPINC' ) ) {
	die;
}

if( !defined( 'ENABLE_DEBUGBAR_INTEGRATION' ) ) {
	define( 'ENABLE_DEBUGBAR_INTEGRATION', true );
}

if( defined( 'ENABLE_DEBUGBAR_INTEGRATION' ) && ENABLE_DEBUGBAR_INTEGRATION == true ) {
	require_once plugin_dir_path( __FILE__ ) . '/includes/class-debugbar-integration.php';
	add_filter( 'debug_bar_panels', array( 'Log_Viewer_DebugBar_Integration', 'integrate_debugbar' ) );
}

/*----------------------------------------------------------------------------*
 * Dashboard and Administrative Functionality
 *----------------------------------------------------------------------------*/

if( is_admin() && ( !defined( 'DOING_AJAX' ) || !DOING_AJAX ) ) {

	//lvlog( sprintf( 'loading admin functions ( is_admin = %s )', is_admin()) );

	require_once( plugin_dir_path( __FILE__ ) . '/admin/class-log-viewer-admin.php' );
	add_action( 'plugins_loaded', array( 'Log_Viewer_Admin', 'get_instance' ) );

}
