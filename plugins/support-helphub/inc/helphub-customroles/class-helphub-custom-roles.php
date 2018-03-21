<?php
/**
 * This adds custom roles for the HelpHub project.
 * Author: carl-alberto
 *
 * @package HelpHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Roles class.
 */
class HelpHub_Custom_Roles {


	/**
	 * The single instance of HelpHub_Custom_Roles.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null;

	/**
	 * Settings class object
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = null;

	/**
	 * The version number.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version;

	/**
	 * The token.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token;

	/**
	 * The main plugin file.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_url;

	/**
	 * Suffix for Javascripts.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $script_suffix;

	/**
	 * Custom roles Constructor.
	 *
	 * @param string $file    filename.
	 * @param string $version version.
	 */
	public function __construct( $file = '', $version = '1.0.0' ) {
		$this->_version = $version;
		$this->_token   = 'helphub_custom_roles';

		$this->file = $file;
		$this->dir  = dirname( $this->file );

		$this->add_helphub_customrole();

		$this->load_plugin_textdomain();

		add_action( 'admin_init', array( $this, 'hh_restrict_admin_pages' ), 0 );

		add_action( 'init', array( $this, 'load_localisation' ), 0 );
	} // End __construct ()

	/**
	 * This will add restriction to the custom rule.
	 */
	public function hh_restrict_admin_pages() {
		$user_roles = wp_get_current_user()->roles;

		if ( in_array( 'helphub_editor', $user_roles, true ) ) {
			global $pagenow;
			$restricted_pages = array(
				'themes.php',
			);
			if ( in_array( $pagenow, $restricted_pages, true ) ) {
				wp_safe_redirect( admin_url( '/' ) );
				exit;
			}
		}
	}

	/**
	 * Load plugin localisation
	 *
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_localisation() {
		load_plugin_textdomain( 'helphub-custom-roles', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 *
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain() {
		$domain = 'helphub-custom-roles';

		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain ()

	/**
	 * Main HelpHub_Custom_Roles Instance
	 *
	 * Ensures only one instance of HelpHub_Custom_Roles is loaded or can be loaded.
	 *
	 * @param string $file    Filename of site.
	 * @param string $version Version number.
	 * @since 1.0.0
	 * @static
	 * @see HelpHub_Custom_Roles()
	 * @return Main HelpHub_Custom_Roles instance
	 */
	public static function instance( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}
		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Sorry, this is not allowed.', 'wporg-forums' ) ), esc_html( $this->_version ) );
	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Sorry, this is not allowed.', 'wporg-forums' ) ), esc_html( $this->_version ) );
	} // End __wakeup ()

	/**
	 * Log the plugin version number.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	private function _log_version_number() {
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

	/**
	 * Adds a HelpHub custom role.
	 */
	public function add_helphub_customrole() {

		// Load users library.
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		get_editable_roles();
		$role = 'helphub_editor';

		// Check if the custom role is already added.
		global $wp_roles;
		$default_editorroles = $wp_roles->get_role( 'editor' );
		if ( empty( $GLOBALS['wp_roles']->is_role( $role ) ) ) {
			$wp_roles->add_role( $role, __( 'HelpHub Editor', 'wporg-forums' ), $default_editorroles->capabilities );

			$wp_roles->add_cap( $role, 'edit_theme_options' );
		}
	}
}

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
