<?php
/*
 * Plugin Name: HelpHub Search
 * Description: Extends WordPress's default search, provide auto suggestion and ability to highlight result results based on search terms.
 * Version:     1.0.0
 * Author:      justingreerbbi
 * Author URI:  https://wordpress.org
 * License:     GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Text Domain: helphub
 * Domain Path: /languages/
 *
 * Developer Objectives:
 *
 * HelpHub Search should extend WordPress's default search functionality to provide awesomness 
 * a.k.a tailor search results for HelpHub.
 *
 * Goal: Provide a non intrusive auto complete or suggestion drop down in search input
 * Goal: Dig deeper into the heart and get better tailored results on search submission. 
 * Goal: IMPORTANT.... Provide a reason able throttle to prevent abuse. The search after all will be the work horse.
 *
 * Note: To keep things simple and light weight on the front-end, the auto complete feature should only search
 * article titles and maybe excerpts. 
 *
 * Want: Depending on the CPT, category and tag structure, it would be nice to auto suggest defined groups in a
 * nice drop down.
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

add_action('wp_enqueue_scripts', array('HelpHub_Search','enqueue_scripts') );

/**
 * Main Search Class for HelpHub
 * 
 */
class HelpHub_Search {

	/** Server Instance */
	public static $_instance = null;

	/**
	 * Constructor
	 */
	function __construct(){
		
		require_once( plugin_dir_path( __FILE__ ) . 'includes/functions.php' );
		require_once( plugin_dir_path( __FILE__ ) . 'includes/filters.php' );

		// Include certain files only if the call is AJAX
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {}
	}

	/**
	 * Enqueue Styles and Scripts
	 * @return [type] [description]
	 */
	static function enqueue_scripts(){
		wp_enqueue_script( 'suggest' );
		wp_enqueue_script( 'helphub-search-suggest-ajax-url' );
		wp_enqueue_script( 'helphub-search-suggest', plugins_url( '/assets/helphub.js' , __FILE__ ), array( 'jquery' ) );

		wp_localize_script( 'helphub-search-suggest', 'helphub_object', 
			array( 
				'ajax_url' => admin_url( 'admin-ajax.php' )
			) 
		);

		wp_enqueue_style( 'helphub-suggesed-search', plugins_url( '/assets/helphub.css' , __FILE__ ) );
	}

	/**
	 * Populate the instance if the plugin for extendability
	 * @return object plugin instance
	 */
	static function _instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}

// Start HelpHub Search Class
function _HH_Search() {
	return HelpHub_Search::_instance();
}
add_action('init', '_HH_Search');
//$GLOBAL['HH_SEARCH'] = _HH_Search();