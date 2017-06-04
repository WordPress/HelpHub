<?php
//Prevent directly browsing to the file
if (function_exists('plugin_dir_url')) 
{		
    define('DUPLICATOR_VERSION',        '1.2.8');
    define('DUPLICATOR_HOMEPAGE',       'http://lifeinthegrid.com/labs/duplicator');
    define('DUPLICATOR_PLUGIN_URL',     plugin_dir_url(__FILE__));
	define('DUPLICATOR_SITE_URL',		get_site_url());
	
    /* Paths should ALWAYS read "/"
      uni: /home/path/file.txt
      win:  D:/home/path/file.txt
      SSDIR = SnapShot Directory */
    if (!defined('ABSPATH')) {
		define('ABSPATH', dirname(__FILE__));
    }
	
	//PATH CONSTANTS
	if (! defined('DUPLICATOR_WPROOTPATH')) {
		define('DUPLICATOR_WPROOTPATH', str_replace('\\', '/', ABSPATH));
	}
	define('DUPLICATOR_SSDIR_NAME',     'wp-snapshots');
	define('DUPLICATOR_PLUGIN_PATH',    str_replace("\\", "/", plugin_dir_path(__FILE__)));
	define('DUPLICATOR_SSDIR_PATH',     str_replace("\\", "/", DUPLICATOR_WPROOTPATH . DUPLICATOR_SSDIR_NAME));
	define('DUPLICATOR_SSDIR_PATH_TMP', DUPLICATOR_SSDIR_PATH . '/tmp');
	define('DUPLICATOR_SSDIR_URL',      DUPLICATOR_SITE_URL . "/" . DUPLICATOR_SSDIR_NAME);
    define('DUPLICATOR_INSTALL_PHP',    'installer.php');
	define('DUPLICATOR_INSTALL_BAK',    'installer-backup.php');
    define('DUPLICATOR_INSTALL_SQL',    'installer-data.sql');
    define('DUPLICATOR_INSTALL_LOG',    'installer-log.txt');
	define('DUPLICATOR_INSTALL_DB',     'database.sql');
	
	
	//GENERAL CONSTRAINTS
    define('DUPLICATOR_PHP_MAX_MEMORY',  '2048M');
    define('DUPLICATOR_DB_MAX_TIME',     5000);
	define('DUPLICATOR_DB_EOF_MARKER',   'DUPLICATOR_MYSQLDUMP_EOF');
	//SCANNER CONSTRAINTS 
	define('DUPLICATOR_SCAN_SITE',			157286400);	//150MB
	define('DUPLICATOR_SCAN_WARNFILESIZE',	3145728);	//3MB
	define('DUPLICATOR_SCAN_CACHESIZE',		524288);	//512K
	define('DUPLICATOR_SCAN_DB_ALL_ROWS',	1000000);	//1 rows million per DB
	define('DUPLICATOR_SCAN_DB_ALL_SIZE',	52428800);	//50MB DB
	define('DUPLICATOR_SCAN_DB_TBL_ROWS',	100000);    //100K rows per table
	define('DUPLICATOR_SCAN_DB_TBL_SIZE',	10485760);  //10MB Table
	define('DUPLICATOR_SCAN_TIMEOUT',		150);		//Seconds
	define('DUPLICATOR_SCAN_MIN_WP',		'3.9.0');
	
    $GLOBALS['DUPLICATOR_SERVER_LIST'] = array('Apache','LiteSpeed', 'Nginx', 'Lighttpd', 'IIS', 'WebServerX', 'uWSGI');
	$GLOBALS['DUPLICATOR_OPTS_DELETE'] = array('duplicator_ui_view_state', 'duplicator_package_active', 'duplicator_settings');
	
	/* Used to flush a response every N items. 
	 * Note: This value will cause the Zip file to double in size durning the creation process only*/
	define("DUPLICATOR_ZIP_FLUSH_TRIGGER", 1000);

} else {
    error_reporting(0);
    $port = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] != "off") ? "https://" : "http://";
    $url = $port . $_SERVER["HTTP_HOST"];
    header("HTTP/1.1 404 Not Found", true, 404);
    header("Status: 404 Not Found");
    exit();
}
?>
