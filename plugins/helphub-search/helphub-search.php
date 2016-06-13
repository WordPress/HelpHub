<?php
/**
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
 * @package helphub-search
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

add_action( 'wp_enqueue_scripts' , array( 'HelpHub_Search', 'enqueue_scripts' ) );

/**
 * Main Search Class for HelpHub
 */
class HelpHub_Search {

	/**
	 * Instance
	 *
	 * @var null
	 */
	public static $_instance = null;

	/**
	 * Construct Method
	 */
	function __construct() {

		require_once( plugin_dir_path( __FILE__ ) . 'includes/functions.php' );
		require_once( plugin_dir_path( __FILE__ ) . 'includes/filters.php' );

	}

	/**
	 * Enqueue Styles and Scripts
	 *
	 * @return void
	 */
	static function enqueue_scripts() {
		wp_enqueue_script( 'jquery-ui-autocomplete' );
		wp_enqueue_script( 'helphub-search-suggest-ajax-url' );
		wp_enqueue_script( 'helphub-search-suggest', plugins_url( '/assets/helphub.js' , __FILE__ ), array( 'jquery' ) );

		wp_localize_script( 'helphub-search-suggest', 'helphub_object',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);

		wp_enqueue_style( 'helphub-suggesed-search', plugins_url( '/assets/helphub.css' , __FILE__ ) );
	}

	/**
	 * Populate the instance if the plugin for extendability
	 *
	 * @return object plugin instance
	 */
	static function _instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}

/**
 * Load an instance of the search functionality
 */
function _hh_search() {
	return HelpHub_Search::_instance();
}
add_action( 'init', '_hh_search' );
