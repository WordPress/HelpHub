<?php
/**
 * Plugin Name: Helphub Post Types
 * Plugin URI: http://www.wordpress.org
 * Description: This is what powers Post Types and Taxonomies.
 * Version: 1.3.0
 * Author: Jon Ang
 * Author URI: http://www.helphubcommunications.com/
 * Requires at least: 4.6.0
 * Tested up to: 4.0.0
 *
 * Text Domain: helphub
 * Domain Path: /languages/
 *
 * @package HelpHub_Post_Types
 * @category Core
 * @author Jon Ang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Returns the main instance of HelpHub_Post_Types to prevent the need to use globals.
 *
 * @since 1.0.0
 * @return object HelpHub_Post_Types
 */
function helphub_post_types() {
	return HelpHub_Post_Types::instance();
} // End HelpHub_Post_Types()

add_action( 'plugins_loaded', 'helphub_post_types' );

/**
 * Main HelpHub_Post_Types Class
 *
 * @class HelpHub_Post_Types
 * @version 1.0.0
 * @since 1.0.0
 * @package HelpHub_Post_Types
 * @author Jon Ang
 */
final class HelpHub_Post_Types {
	/**
	 * HelpHub_Post_Types The single instance of HelpHub_Post_Types.
	 *
	 * @var object
	 * @access private
	 * @since 1.0.0
	 */
	private static $_instance = null;

	/**
	 * The token.
	 *
	 * @var string
	 * @access public
	 * @since 1.0.0
	 */
	public $token;

	/**
	 * The version number.
	 *
	 * @var string
	 * @access public
	 * @since 1.0.0
	 */
	public $version;

	/**
	 * The plugin directory URL.
	 *
	 * @var string
	 * @access public
	 * @since 1.0.0
	 */
	public $plugin_url;

	/**
	 * The plugin directory path.
	 *
	 * @var string
	 * @access public
	 * @since 1.0.0
	 */
	public $plugin_path;

	/* Admin - Start */

	/**
	 * The admin object.
	 *
	 * @var object
	 * @access public
	 * @since 1.0.0
	 */
	public $admin;

	/**
	 * The settings object.
	 *
	 * @var object
	 * @access public
	 * @since 1.0.0
	 */
	public $settings;

	/* Admin - End */

	/* Post Types - Start */

	/**
	 * The post types we're registering.
	 *
	 * @var array
	 * @access public
	 * @since 1.0.0
	 */
	public $post_types = array();

	/* Post Types - End */

	/* Taxonomies - Start */

	/**
	 * The taxonomies we're registering.
	 *
	 * @var array
	 * @access public
	 * @since 1.0.0
	 */
	public $taxonomies = array();

	/* Taxonomies - End */


	/**
	 * Constructor function.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->token        = 'helphub';
		$this->plugin_url   = plugin_dir_url( __FILE__ );
		$this->plugin_path  = plugin_dir_path( __FILE__ );
		$this->version      = '1.0.0';

		/* Post Types - Start */
		require_once( 'classes/class-helphub-post-types-post-type.php' );
		require_once( 'classes/class-helphub-post-types-taxonomy.php' );

		// Register an example post type. To register other post types, duplicate this line.
		$this->post_types['post'] = new HelpHub_Post_Types_Post_Type(
			'post', __( 'Post', 'helphub' ), __( 'Posts', 'helphub' ), array(
				'menu_icon' => 'dashicons-post',
			)
		);
		$this->post_types['helphub_version'] = new HelpHub_Post_Types_Post_Type(
			'helphub_version', __( 'WordPress Version', 'helphub' ), __( 'WordPress Versions', 'helphub' ), array(
				'menu_icon' => 'dashicons-wordpress',
			)
		);

		/* Post Types - End */

		// Register an example taxonomy. To register more taxonomies, duplicate this line.
		$this->taxonomies['helphub_persona']  = new HelpHub_Post_Types_Taxonomy( 'post', 'helphub_persona', __( 'Persona', 'helphub' ), __( 'Personas', 'helphub' ) );
		$this->taxonomies['helphub_experience']   = new HelpHub_Post_Types_Taxonomy( 'post', 'helphub_experience', __( 'Experience', 'helphub' ), __( 'Experiences', 'helphub' ) );
		$this->taxonomies['helphub_major_release']   = new HelpHub_Post_Types_Taxonomy( 'helphub_version', 'helphub_major_release', __( 'Major Release', 'helphub' ), __( 'Major Releases', 'helphub' ) );

		register_activation_hook( __FILE__, array( $this, 'install' ) );

		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
	} // End __construct()

	/**
	 * Main HelpHub_Post_Types Instance
	 *
	 * Ensures only one instance of HelpHub_Post_Types is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see HelpHub_Post_Types()
	 * @return HelpHub_Post_Types instance
	 */
	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	} // End instance()

	/**
	 * Load the localisation file.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'helphub', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	} // End load_plugin_textdomain()

	/**
	 * Enqueue post type admin Styles.
	 *
	 * @access public
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_admin_styles() {
		global $pagenow;

		wp_enqueue_style( 'helphub-post-types-admin-style', $this->plugin_url . 'assets/css/admin.css', array(), '1.0.0' );

		if ( ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) ) :
			if ( array_key_exists( get_post_type(), $this->post_types ) ) :
				wp_enqueue_script( 'helphub-post-types-admin', $this->plugin_url . 'assets/js/admin.js', array( 'jquery' ), '1.0.1', true );
				wp_enqueue_script( 'helphub-post-types-gallery', $this->plugin_url . 'assets/js/gallery.js', array( 'jquery' ), '1.0.0', true );
				wp_enqueue_script( 'jquery-ui-datepicker' );
				wp_enqueue_style( 'jquery-ui-datepicker' );
			endif;
		endif;
		wp_localize_script(
			'helphub-post-types-admin', 'helphub_admin',
			array(
				'default_title'     => __( 'Upload', 'helphub' ),
				'default_button'    => __( 'Select this', 'helphub' ),
			)
		);

		wp_localize_script(
			'helphub-post-types-gallery', 'helphub_gallery',
			array(
				'gallery_title'     => __( 'Add Images to Product Gallery', 'helphub' ),
				'gallery_button'    => __( 'Add to gallery', 'helphub' ),
				'delete_image'      => __( 'Delete image', 'helphub' ),
			)
		);

	} // End enqueue_admin_styles()

	/**
	 * Cloning is forbidden.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'helphub' ), '1.0.0' );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'helphub' ), '1.0.0' );
	} // End __wakeup()

	/**
	 * Installation. Runs on activation.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	public function install() {
		$this->_log_version_number();
	} // End install()

	/**
	 * Log the plugin version number.
	 *
	 * @access private
	 * @since 1.0.0
	 */
	private function _log_version_number() {
		// Log the version number.
		update_option( $this->token . '-version', $this->version );
	} // End _log_version_number()
} // End Class
