<?php
if ( ! defined( 'DUPLICATOR_VERSION' ) ) exit; // Exit if accessed directly


class DUP_Settings
{
	
	const OPT_SETTINGS = 'duplicator_settings';

	public static $Data;
	public static $Version = DUPLICATOR_VERSION;

	/**
	*  Class used to manage all the settings for the plugin
	*/
	static function init() {
		self::$Data = get_option(self::OPT_SETTINGS);

		//when the plugin updated, this will be true
		if (empty(self::$Data) || self::$Version > self::$Data['version']){
			self::SetDefaults();
		}
	}

	/**
	*  Find the setting value
	*  @param string $key	The name of the key to find
	*  @return The value stored in the key returns null if key does not exist
	*/
	public static function Get($key = '') {
		$result = null;
		if (isset(self::$Data[$key])) {
			$result = self::$Data[$key]; 
		} else {
			$defaults = self::GetAllDefaults();
			if (isset($defaults[$key])) {
				$result = $defaults[$key];
			} 
		}
		return $result;
	}

	/**
	*  Set the settings value in memory only
	*  @param string $key		The name of the key to find
	*  @param string $value		The value to set
	*  remarks:	 The Save() method must be called to write the Settings object to the DB
	*/
	public static function Set($key = '', $value) {
		if (isset(self::$Data[$key])) {
			self::$Data[$key] = ($value == null) ? '' : $value;
		} elseif (!empty($key)) {
			self::$Data[$key] = ($value == null) ? '' : $value;
		}
	}

	/**
	*  Saves all the setting values to the database
	*  @return True if option value has changed, false if not or if update failed.
	*/
	public static function Save() {
		return update_option(self::OPT_SETTINGS, self::$Data);
	}

	/**
	*  Deletes all the setting values to the database
	*  @return True if option value has changed, false if not or if update failed.
	*/
	public static function Delete() {
		return delete_option(self::OPT_SETTINGS);
	}

	/**
	*  Sets the defaults if they have not been set
	*  @return True if option value has changed, false if not or if update failed.
	*/
	public static function SetDefaults() {
		$defaults = self::GetAllDefaults();
		self::$Data = $defaults;
		return self::Save();
	}
	
	/**
	*  LegacyClean: Cleans up legacy data
	*/
	public static function LegacyClean() {
		global $wpdb;

		//PRE 5.0
		$table = $wpdb->prefix."duplicator";
		$wpdb->query("DROP TABLE IF EXISTS $table");
		delete_option('duplicator_pack_passcount'); 
		delete_option('duplicator_add1_passcount'); 
		delete_option('duplicator_add1_clicked'); 
		delete_option('duplicator_options'); 
		
		//PRE 5.n
		//Next version here if needed
	}
	
	/**
	*  DeleteWPOption: Cleans up legacy data
	*/
	public static function DeleteWPOption($optionName) {
		
		if ( in_array($optionName, $GLOBALS['DUPLICATOR_OPTS_DELETE']) ) {
			return delete_option($optionName); 
		}
		return false;
	}
	
	
	public static function GetAllDefaults() {	
		$default = array();
		$default['version'] = self::$Version;

		//Flag used to remove the wp_options value duplicator_settings which are all the settings in this class
		$default['uninstall_settings']	= isset(self::$Data['uninstall_settings']) ? self::$Data['uninstall_settings'] : true;
		//Flag used to remove entire wp-snapshot directory
		$default['uninstall_files']		= isset(self::$Data['uninstall_files'])  ? self::$Data['uninstall_files']  : true;
		//Flag used to remove all tables
		$default['uninstall_tables']	= isset(self::$Data['uninstall_tables']) ? self::$Data['uninstall_tables'] : true;
		
		//Flag used to show debug info
		$default['package_debug']			 = isset(self::$Data['package_debug']) ? self::$Data['package_debug'] : false;
		//Flag used to enable mysqldump
		$default['package_mysqldump']		 = isset(self::$Data['package_mysqldump']) ? self::$Data['package_mysqldump'] : false;
		//Optional mysqldump search path
		$default['package_mysqldump_path']	 = isset(self::$Data['package_mysqldump_path']) ? self::$Data['package_mysqldump_path'] : '';
		//Optional mysql limit size
		$default['package_phpdump_qrylimit'] = isset(self::$Data['package_phpdump_qrylimit']) ? self::$Data['package_phpdump_qrylimit'] : "100";
		//Optional mysqldump search path
		$default['package_zip_flush']		 = isset(self::$Data['package_zip_flush']) ? self::$Data['package_zip_flush'] : false;
		
		//Flag for .htaccess file
		$default['storage_htaccess_off'] = isset(self::$Data['storage_htaccess_off']) ? self::$Data['storage_htaccess_off'] : false;
		
		return $default;
	}
}

//Init Class
DUP_Settings::init();

?>