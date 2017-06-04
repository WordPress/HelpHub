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
 * Class User_Options
 *
 * Controls storage and access of user options
 * After setting new values, update function has to be called to save to database
 *
 * TODO - FUTURE :
 *  - user wordpress *_user_setting ? currently problems with cookie/headers_sent
 *  - cleanup on uninstall ( multisite installations different options tables )
 *
 * Bugs :
 *  - User_Options on MU installations stored in each wp_*_options table
 *
 */
class User_Options
{
	/**
	 * Unique string to identify option keys ( appends to user id )
	 */
	const KEYS_IDENTIFIER = '_log-viewer_settings';

	/**
	 * Line output order of files : First-In-First-Out
	 */
	const LINEOUTPUTORDER_FIFO = 0;

	/**
	 * Line output order of files : First-In-Last-Out
	 */
	const LINEOUTPUTORDER_FILO = 1;

	/**
	 * Options keys : autorefresh
	 */
	const KEYS_AUTOREFRESH          = 'autorefresh';

	/**
	 * Options keys : interval of autorefresh
	 */
	const KEYS_AUTOREFRESHINTERVALL = 'arintervall';

	/**
	 * Options keys : order of line output
	 */
	const KEYS_LINEOUTPUTORDER      = 'lineoutputorder';

	/**
	 * Options keys : options version
	 */
	const KEYS_OPTIONSVERSION       = 'version';

	/**
	 * Buffered/current options
	 *
	 * @var array|bool
	 */
	private static $_options = false;

	/**
	 * Default values of options
	 *
	 * @var array
	 */
	private static $_defaultOptions = array(
		self::KEYS_AUTOREFRESH => 1, self::KEYS_AUTOREFRESHINTERVALL => 15, self::KEYS_LINEOUTPUTORDER => self::LINEOUTPUTORDER_FIFO, self::KEYS_OPTIONSVERSION => '14.05.04-1559',
	);

	/**
	 * Returns value of Autorefresh; 1 if enabled, 0 if disabled
	 *
	 * @return int
	 */
	public static function getAutoRefresh()
	{
		if( !self::$_options ) {
			self::_loadUserOptions();
		}

		return self::$_options[self::KEYS_AUTOREFRESH];
	}

	/**
	 * Sets value of autorefresh; true for enabled, false for disabled
	 *
	 * @param bool $enabled
	 */
	public static function setAutoRefresh( $enabled = true )
	{
		if( !self::$_options ) {
			self::_loadUserOptions();
		}

		self::$_options[self::KEYS_AUTOREFRESH] = $enabled ? 1 : 0;
	}

	/**
	 * Returns interval of autorefresh
	 *
	 * @return int
	 */
	public static function getAutoRefreshIntervall()
	{
		if( !self::$_options ) {
			self::_loadUserOptions();
		}

		return self::$_options[self::KEYS_AUTOREFRESHINTERVALL];
	}

	/**
	 * Returns order of line output
	 *
	 * @see Lineoutput constants
	 *
	 * @return int
	 */
	public static function getLineOutputOrder()
	{
		if( !self::$_options ) {
			self::_loadUserOptions();
		}

		return self::$_options[self::KEYS_LINEOUTPUTORDER];
	}

	/**
	 * Handles loading of options out of database
	 *
	 * TODO - FUTURE :
	 *  - TODO better management of updated options, currently only overwriting old settings
	 */
	private static function _loadUserOptions()
	{
		/*
		 * usage of user setting functions
		 * currently problems with headers sent
		 *
		 * $us = get_user_setting( 'log-viewer', false );
		if( ! $us ) {
			set_user_setting( 'log-viewer', self::$_defaultOptions );
			$us = get_user_setting( 'log-viewer', false );
		}
		if( !$us ) {
			error_log( 'cant load/set user settings' );
		} else {
			var_dump( $us );
		}
		*/

		if( !is_user_logged_in() ) {
			self::$_options = self::$_defaultOptions;

			return;
		}

		$user     = wp_get_current_user();
		$key      = sprintf( "%s_log-viewer_settings", $user->ID );
		$settings = get_option( $key, false );

		if( false === $settings ) {
			add_option( $key, self::$_defaultOptions );
			$settings = self::$_defaultOptions;
		} elseif( !is_array( $settings ) || !array_key_exists( self::KEYS_OPTIONSVERSION, $settings ) ) {
			update_option( $key, self::$_defaultOptions );
			$settings = self::$_defaultOptions;
		}

		self::$_options = $settings;
	}

	/**
	 * Returns array of current values
	 *
	 * @return array|bool
	 */
	public static function toArray()
	{
		if( !self::$_options ) {
			self::_loadUserOptions();
		}

		return self::$_options;
	}

	/**
	 * Saves newOptions to database and sets/caches current options for direct usage
	 * respects multiuser
	 *
	 * @param array $newOptions
	 */
	public static function updateUserOptions( $newOptions = array() )
	{
		if( !is_user_logged_in() ) {
			return;
		}

		if( empty( $newOptions ) ) {
			$newOptions = self::toArray();
		} else {
			$newOptions = wp_parse_args( $newOptions, self::toArray() );
		}

		$user       = wp_get_current_user();
		$key              = sprintf( "%s%s", $user->ID, self::KEYS_IDENTIFIER );
		$oldOptions = get_option( $key, false );

		if( $newOptions != $oldOptions ) {
			update_option( $key, $newOptions );
			self::$_options = $newOptions;
		}
	}
}
