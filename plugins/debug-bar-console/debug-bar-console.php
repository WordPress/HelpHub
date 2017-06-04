<?php
/*
 Plugin Name: Debug Bar Console
 Plugin URI: http://wordpress.org/extend/plugins/debug-bar-console/
 Description: Adds a PHP/SQL console to the debug bar. Requires the debug bar plugin.
 Author: koopersmith
 Version: 0.3
 Author URI: http://darylkoop.com/
 */

add_filter('debug_bar_panels', 'debug_bar_console_panel');
function debug_bar_console_panel( $panels ) {
	require_once 'class-debug-bar-console.php';
	$panels[] = new Debug_Bar_Console();
	return $panels;
}

add_action('debug_bar_enqueue_scripts', 'debug_bar_console_scripts');
function debug_bar_console_scripts() {
	$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

	// Codemirror
	wp_enqueue_style( 'debug-bar-codemirror', plugins_url( "codemirror/lib/codemirror.css", __FILE__ ), array(), '2.22' );
	wp_enqueue_script( 'debug-bar-codemirror', plugins_url( "codemirror/debug-bar-codemirror.js", __FILE__ ), array(), '2.22' );

	wp_enqueue_style( 'debug-bar-console', plugins_url( "css/debug-bar-console$suffix.css", __FILE__ ), array( 'debug-bar', 'debug-bar-codemirror' ), '20120317' );
	wp_enqueue_script( 'debug-bar-console', plugins_url( "js/debug-bar-console$suffix.js", __FILE__ ), array( 'debug-bar', 'debug-bar-codemirror' ), '20120317' );
}

