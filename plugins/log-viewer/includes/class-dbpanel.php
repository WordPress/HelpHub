<?php

class Log_Viewer_DebugBar_Panel extends Debug_Bar_Panel {

	/**
	 * Filename for the view template. Prefixed with view_path of class constructor.
	 *
	 * @var string
	 */
	private $_view_file = 'debug-bar-panel.php';

	/**
	 * @var bool
	 */
	private $_currentFile = false;

	/**
	 * Returns current/active filename or false if none active or
	 * reads file request parameter to set the current file
	 *
	 * TODO : move to helper class?
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

			Log_Viewer_Admin::validate_file_to_edit( $file, $files );
			$this->_currentFile = $file;
		}

		return $this->_currentFile;
	}

	/**
	 * Returns content of current file
	 *
	 * TODO : move to helper class?
	 *
	 * @return string
	 */
	public function getCurrentFileContent()
	{
		if( !$this->getCurrentFile() ) {
			return '';
		}

		$content = file_get_contents( Log_Viewer_Admin::transformFilePath( $this->getCurrentFile() ), false );

		return $content;
	}

	function __construct( $view_path = '' ) {
		parent::Debug_Bar_Panel( 'Log Viewer' );

		$this->_view_file = realpath( $view_path . DIRECTORY_SEPARATOR . $this->_view_file );
	}

	function init() {
		return true;
	}

	function render() {

		require_once plugin_dir_path( __DIR__ ) . '/admin/includes/class-files-view-page.php';

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

		include $this->_view_file;

	}

}
