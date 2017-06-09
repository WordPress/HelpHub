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
 * Class Files_View_Page
 *
 * Provides controller functionality for the Files View page
 */
class Files_View_Page
{
	/**
	 * @var string
	 */
	private static $_KEYS_FILEACTION_SUBMIT = 'fileactions';

	/**
	 * @var string
	 */
	private static $_KEYS_FILEACTION_ACTION = 'action';

	/**
	 * @var string
	 */
	private static $_KEYS_FILEACTION_SCROLLTO = 'scrollto';

	/**
	 * @var string
	 */
	private static $_KEYS_FILEACTION_FILE = 'file';

	/**
	 * @var string
	 */
	private static $_KEYS_FILEACTIONS_DUMP = 'dump';

	/**
	 * @var string
	 */
	private static $_KEYS_FILEACTIONS_EMPTY = 'empty';

	/**
	 * @var string
	 */
	private static $_KEYS_FILEACTIONS_BREAK = 'break';

	/**
	 * @var string
	 */
	private static $_KEYS_VIEWFIELDS_SUBMIT = 'viewfields';

	/**
	 * @var string
	 */
	public static $ACTIONS_VIEWOPTIONS_CHANGED = 'ViewOptions_Changed';

	/**
	 * @var bool
	 */
	private $_currentFile = false;

	/**
	 * The hook name for this page
	 *
	 * @var string
	 */
	public $_hook_name = null;

	/**
	 * @var WP_Screen
	 */
	protected $_wpScreen;

	/**
	 * The slug name for the parent menu
	 *
	 * @see http://codex.wordpress.org/Function_Reference/add_submenu_page#Parameters
	 *
	 * @var string
	 */
	private static $_parent_slug = 'tools.php';

	/**
	 * The text to be displayed in the title tags of the page when the menu is selected
	 *
	 * @var string
	 */
	private $_page_title = 'Files View';

	/**
	 * The text to be used for the menu
	 *
	 * @var string
	 */
	private $_menu_title = 'Log Viewer';

	/**
	 * The capability required for this menu to be displayed to the user.
	 *
	 * @see http://codex.wordpress.org/Roles_and_Capabilities
	 *
	 * @var string
	 */
	private $_capability = 'edit_plugins';

	/**
	 * The slug name to refer to this menu by
	 *
	 * @var string
	 */
	private static  $_menu_slug = 'log_viewer_files_view';

	/**
	 * Filename for the view template. Prefixed with view_path of class constructor.
	 *
	 * @var string
	 */
	private $_view_file = 'files-view.php';

	/**
	 * Fetches a WP_Screen object for this page.
	 *
	 * @see http://codex.wordpress.org/Class_Reference/WP_Screen
	 *
	 * @return WP_Screen
	 */
	public function getWPScreen()
	{
		if( !$this->_wpScreen ) {
			$this->_wpScreen = WP_Screen::get( $this->_hook_name );
		}

		return $this->_wpScreen;
	}

	/**
	 * Returns the admin_url for this page.
	 *
	 * @see http://codex.wordpress.org/Function_Reference/admin_url
	 *
	 * @return string|void
	 */
	public static function getPageUrl()
	{
		$url = admin_url( self::$_parent_slug, 'admin' );
		$url .= "?page=" . self::$_menu_slug;

		return $url;
	}

	/**
	 * Constructor for this class.
	 *
	 * @param string $view_path View templates directory
	 */
	public function __construct( $view_path = '' )
	{
		$this->_hook_name = add_submenu_page( self::$_parent_slug, $this->_page_title, $this->_menu_title, $this->_capability, self::$_menu_slug, array( $this, 'view_page' ) );

		$this->_view_file = realpath( $view_path . DIRECTORY_SEPARATOR . $this->_view_file );

		// saves user options on changed settings
		add_action( self::$ACTIONS_VIEWOPTIONS_CHANGED, array( 'User_Options', 'updateUserOptions' ) );
	}

	/**
	 * Returns current/active filename or false if none active or
	 * reads file request parameter to set the current file
	 *
	 * @return bool|string
	 */
	public function getCurrentFile()
	{
		if( false == $this->_currentFile ) {
			$files = Log_Viewer_Admin::getFiles();
			if( empty( $files ) ) {
				return false;
			}

			if( isset( $_REQUEST['file'] ) ) {
				$file = stripslashes( $_REQUEST['file'] );
			} else {
				$file = $files[0];
			}

			validate_file_to_edit( $file, $files );
			$this->_currentFile = $file;
		}

		return $this->_currentFile;
	}

	/**
	 * Returns content of current file
	 *
	 * @return string
	 */
	public function getCurrentFileContent()
	{
		if( !$this->getCurrentFile() ) {
			return '';
		}

		if( User_Options::LINEOUTPUTORDER_FILO == User_Options::getLineOutputOrder() ) {
			$content = implode( array_reverse( file( Log_Viewer_Admin::transformFilePath( $this->getCurrentFile() ) ) ) );
		} else {
			$content = file_get_contents( Log_Viewer_Admin::transformFilePath( $this->getCurrentFile() ), false );
		}

		return $content;
	}

	/**
	 * Dumps the given file
	 * Respects is writeable
	 *
	 * @param $file
	 *
	 * @return bool|int
	 */
	private function _dumpFile( $file )
	{
		if( !is_writable( $file ) ) {
			return -1;
		}

		$result = unlink( $file );
		if( true == $result ) {
			$this->_currentFile = false;
		}

		return $result;
	}

	/**
	 * Empties the given file
	 * Respects is writeable
	 *
	 * @param $file
	 *
	 * @return bool|int
	 */
	private function _emptyFile( $file )
	{
		if( !is_writable( $file ) ) {
			return -1;
		}

		$handle = fopen( $file, 'w' );
		if( !$handle ) {
			return -2;
		}

		return fclose( $handle );
	}

	/**
	 * Appends break string to given file
	 * respects is writeable
	 *
	 * TODO - FUTURE :
	 *    - user defined break string
	 *
	 * @param $file
	 *
	 * @return bool|int
	 */
	private function _appendBreak( $file )
	{
		if( !is_writable( $file ) ) {
			return -1;
		}

		$handle = fopen( $file, 'a' );
		if( !$handle ) {
			return -2;
		}

		fwrite( $handle, '------------------------' );

		return fclose( $handle );
	}

	/**
	 * Handles file actions of page post
	 *
	 * TODO - FUTURE :
	 *    - adding update_recently_edited of wp core?
	 *    - reimplementing message handling
	 *
	 * @param $fileaction   action to handle
	 * @param $file         file to target
	 *
	 * @return $this|bool|int
	 */
	private function _handle_fileaction( $fileaction, $file )
	{
		$files = Log_Viewer_Admin::getFiles();
		validate_file_to_edit( $file, $files );

		$realfile = Log_Viewer_Admin::transformFilePath( $file );

		switch( $fileaction ) {
			case self::$_KEYS_FILEACTIONS_DUMP:
				$dumped = $this->_dumpFile( $realfile );

				// BUG: better redirect but not working cause already present output
				// wp_redirect( $this->getPageUrl() );
				//exit();
				// Workaround:
				unset( $_POST[self::$_KEYS_FILEACTION_ACTION], $_POST[self::$_KEYS_FILEACTION_FILE], $_POST[self::$_KEYS_FILEACTION_SUBMIT], $_POST[self::$_KEYS_FILEACTION_SCROLLTO], $_REQUEST['file'] );
				$this->_currentFile = false;

				break;

			case self::$_KEYS_FILEACTIONS_EMPTY:
				$handle = $this->_emptyFile( $realfile );

				return $handle;
				break;

			case self::$_KEYS_FILEACTIONS_BREAK:
				$handle = $this->_appendBreak( $realfile );

				return $handle;
				break;

			default:
				break;
		}

		return $this;
	}

	/**
	 * Handles page view with post actions and output
	 * Respects user options
	 *
	 * TODO - FUTURE :
	 *  - splitting functionality / event based
	 *
	 */
	public function view_page()
	{
		if( array_key_exists( self::$_KEYS_FILEACTION_SUBMIT, $_POST ) && check_admin_referer( 'actions_nonce', 'actions_nonce' ) ) {
			$file       = $_POST[self::$_KEYS_FILEACTION_FILE];
			$fileaction = $_POST[self::$_KEYS_FILEACTION_ACTION];

			$result = $this->_handle_fileaction( $fileaction, $file );

			// Bug: Workaround for wp_redirect not working
			unset( $file, $fileaction );
		}


		if( array_key_exists( self::$_KEYS_VIEWFIELDS_SUBMIT, $_POST ) && check_admin_referer( 'viewoptions_nonce', 'viewoptions_nonce' ) ) {
			$viewoptions = array(
				User_Options::KEYS_AUTOREFRESH => array_key_exists( User_Options::KEYS_AUTOREFRESH, $_POST ) ? 1 : 0,
			);
			if( array_key_exists( User_Options::KEYS_LINEOUTPUTORDER, $_POST ) ) {
				$viewoptions[User_Options::KEYS_LINEOUTPUTORDER] = (int)$_POST[User_Options::KEYS_LINEOUTPUTORDER];
			}

			do_action( self::$ACTIONS_VIEWOPTIONS_CHANGED, $viewoptions );
		}

		$files           = Log_Viewer_Admin::getFiles();
		$showEditSection = true;

		if( empty( $files ) ) {
			$showEditSection = false;
		}

		$realfile  = Log_Viewer_Admin::transformFilePath( $this->getCurrentFile() );
		$writeable = is_writeable( $realfile );

		if( isset( $file ) ) {
			var_dump( array( $realfile, $writeable ) );
			die();
		}

		if( !$writeable ) {
			$action = false;
		}

		include_once $this->_view_file;
	}

}
