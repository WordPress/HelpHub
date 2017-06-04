<?php
/**
 * Log Viewer
 *
 * @package   Log_Viewer_Admin
 * @author    Markus Fischbacher <fischbacher.markus@gmail.com>
 * @license   GPL-2.0+
 * @link      http://wordpress.org/extend/plugins/log-viewer/
 * @copyright 2013 Markus Fischbacher
 */

/**
 * Class Log_Viewer_Admin
 *
 * Main class for admin functionality
 */
class Log_Viewer_Admin
{

	/**
	 * Plugin version ( long with timestamp ).
	 *
	 * @since    13.11.10
	 *
	 * @var     string
	 */
	const VERSION = '14.05.04-1559';

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since    13.11.11
	 *
	 * @var     string
	 */
	const VERSION_SHORT = '14.05.04';

	/**
	 * Unique identifier for your plugin.
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since    13.11.10
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'log-viewer';

	/**
	 * Instance of this class.
	 *
	 * @since    13.11.10
	 *
	 * @var      Log_Viewer_Admin
	 */
	protected static $instance = null;

	/**
	 * Slug of the plugin screen.
	 *
	 * @since    13.11.10
	 *
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix = null;

	/**
	 *
	 *
	 * @since    13.11.10
	 *
	 * @var Files_View_Page
	 */
	private $_files_view_page = null;

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 *
	 * @since    13.11.10
	 */
	private function __construct()
	{
		if( !is_super_admin() ) {
			return;
		}

		// Add menu entries
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
	}

	/**
	 * Return an instance of this class or false on error.
	 *
	 * @since    13.11.10
	 *
	 * @return    bool|Log_Viewer_Admin    A single instance of this class.
	 */
	public static function get_instance()
	{
		if( !is_super_admin() ) {
			return false;
		}

		// If the single instance hasn't been set, set it now.
		if( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Make sure that the file that was requested to edit, is allowed to be edited
	 *
	 * Function will die if if you are not allowed to edit the file
	 *
	 * ToDo: pasted from wordpress core. other solution?
	 *
	 * @since 14.04.21
	 *
	 * @param        $file
	 * @param string $allowed_files
	 *
	 * @return mixed
	 */
	public static function validate_file_to_edit( $file, $allowed_files = '' ) {
		$code = validate_file( $file, $allowed_files );

		if (!$code )
			return $file;

		switch ( $code ) {
			case 1 :
				wp_die( __('Sorry, can&#8217;t edit files with &#8220;..&#8221; in the name. If you are trying to edit a file in your WordPress home directory, you can just type the name of the file in.' ));

			case 3 :
				wp_die( __('Sorry, that file cannot be edited.' ));
		}
	}

	/**
	 * Wrapper for getting Files View Page
	 *
	 * @since 14.04.21
	 *
	 * @return Files_View_Page
	 */
	public function get_Files_View_Page() {
		if( $this->_files_view_page === null ) {
			/**
			 * Add a tools page for viewing the log files
			 */
			require_once 'includes/class-user-options.php';
			require_once 'includes/class-files-view-page.php';
			$this->_files_view_page = new Files_View_Page( realpath( __DIR__ . DIRECTORY_SEPARATOR . 'views' ) );
		}

		return $this->_files_view_page;
	}

	/**
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @since    13.11.10
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles()
	{
		if( !isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_style( $this->plugin_slug . '-admin-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), Log_Viewer_Admin::VERSION );
		}
	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * @since    13.11.10
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts()
	{

		if( !isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery' ), Log_Viewer_Admin::VERSION );
		}

	}



	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    13.11.10
	 */
	public function add_plugin_admin_menu()
	{
		$this->get_Files_View_Page();
	}

	/**
	 * Returns an array with filenames relative to WP_CONTENT_DIR
	 *
	 * @since 30.11.2013
	 *
	 * @return array
	 */
	public static function getFiles()
	{
		// TODO - FUTURE - debug.log always 0

		$content_dir = realpath( WP_CONTENT_DIR );
		$path        = $content_dir . DIRECTORY_SEPARATOR . '*.log';
		$replace     = $content_dir . DIRECTORY_SEPARATOR;

		$files = array();

		foreach( array_reverse( glob( $path ) ) as $file ) {
			$files[] = str_replace( $replace, '', $file );
		}

		return $files;
	}

	/**
	 * Retruns the real path ( WP Content Dir ) of the file
	 *
	 * @param $file
	 *
	 * @return string
	 */
	public static function transformFilePath( $file )
	{
		$path = realpath( WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $file );

		return $path;
	}

}
