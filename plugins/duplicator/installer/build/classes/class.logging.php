<?php

define('ERR_CONFIG_FOUND',		'A wp-config.php already exists in this location.  This error prevents users from accidentally overwriting the wrong directories contents.  You have three options: <ul><li>Empty this root directory except for the package and installer and try again.</li><li>Delete just the wp-config.php file and try again.  This will over-write all other files in the directory.</li><li>Check the "Manual package extraction" checkbox under advanced options to skip extraction</li></ul>');
define('ERR_ZIPNOTFOUND',		'The packaged zip file was not found. Be sure the zip package is in the same directory as the installer file and as the correct permissions.  If you are trying to reinstall a package you can copy the package from the "' . DUPLICATOR_SSDIR_NAME . '" directory back up to your root which is the same location as your installer.php file.');
define('ERR_ZIPOPEN',			'Failed to open zip archive file. Please be sure the archive is completely downloaded before running the installer. Try to extract the archive manually to make sure the file is not corrupted.');
define('ERR_ZIPEXTRACTION',		'Errors extracting zip file.  Portions or part of the zip archive did not extract correctly.    Try to extract the archive manually with a client side program like unzip/win-zip/winrar to make sure the file is not corrupted.  If the file extracts correctly then there is an invalid file or directory that PHP is unable to extract.  This can happen if your moving from one operating system to another where certain naming conventions work on one environment and not another. <br/><br/> Workarounds: <br/> 1. Create a new package and be sure to exclude any directories that have invalid names or files in them.   This warning will be displayed on the scan results under "Invalid Names". <br/> 2. Manually extract the zip file with a client side program.  Then under advanced options in step 1 of the installer check the "Manual package extraction" option and perform the install.');
define('ERR_ZIPMANUAL',			'When choosing manual package extraction, the contents of the package must already be extracted and the wp-config.php and database.sql files must be present in the same directory as the installer.php for the process to continue.  Please manually extract the package into the current directory before continuing in manual extraction mode.  Also validate that the wp-config.php and database.sql files are present.');
define('ERR_MAKELOG',			'PHP is having issues writing to the log file <b>' . DUPX_U::setSafePath($GLOBALS['CURRENT_ROOT_PATH']) . '\installer-log.txt .</b> In order for the Duplicator to proceed validate your owner/group and permission settings for PHP on this path. Try temporarily setting you permissions to 777 to see if the issue gets resolved.  If you are on a shared hosting environment please contact your hosting company and tell them you are getting errors writing files to the path above when using PHP.');
define('ERR_ZIPARCHIVE',		'In order to extract the archive.zip file the PHP ZipArchive module must be installed.  Please read the FAQ for more details.  You can still install this package but you will need to check the Manual package extraction checkbox found in the Advanced Options.  Please read the online user guide for details in performing a manual package extraction.');
define('ERR_MYSQLI_SUPPORT',	'In order to complete an install the mysqli extension for PHP is required. If you are on a hosted server please contact your host and request that mysqli be enabled.  For more information visit: http://php.net/manual/en/mysqli.installation.php');
define('ERR_DBCONNECT',			'DATABASE CONNECTION FAILED!<br/>');
define('ERR_DBCONNECT_CREATE',  'DATABASE CREATION FAILURE!<br/> Unable to create database "%s". Check to make sure the user has "Create" privileges.  Some hosts will restrict creation of a database only through the cpanel.  Try creating the database manually to proceed with installation.  If the database already exists then check the radio button labeled "Connect and Remove All Data" which will remove all existing tables.');
define('ERR_DBTRYCLEAN',		'DATABASE CREATION FAILURE!<br/> Unable to remove all tables from database "%s".<br/>  Please remove all tables from this database and try the installation again.');
define('ERR_DBCREATE',			'The database "%s" does not exists.<br/>  Change mode to create in order to create a new database.');
define('ERR_DBEMPTY',			'The database "%s" has "%s" tables.  The Duplicator only works with an EMPTY database.  Enable the action "Connect and Remove All Data" radio button to remove all tables and or create a new database. Some hosting providers do not allow table removal from scripts.  In this case you will need to login to your hosting providers control panel and remove the tables manually.  Please contact your hosting provider for further details.  Always backup all your data before proceeding!');
define('ERR_TESTDB_UTF8',		'UTF8 Characters were detected as part of the database connection string. If your connection fails be sure  to update the MySQL my.ini configuration file setting to support UTF8 characters by enabling this option [character_set_server=utf8] and restarting the database server.');
define('ERR_TESTDB_VERSION_INFO',	'The current version detected was released prior to MySQL 5.5.3 which had a release date of April 8th 2010.  WordPress 4.2 included support for utf8mb4 which is only supported in MySQL server 5.5.3+.  It is highly recommended to upgrade your version of MySQL server on this server to be more compatible with recent releases of WordPress and avoid issues with install errors.');
define('ERR_TESTDB_VERSION_COMPAT',	'In order to avoid database incompatibility issues make sure the database versions between the build and installer servers are as close as possible. If the package was created on a newer database version than where it is being installed then you might run into issues.<br/><br/> It is best to make sure the server where the installer is running has the same or higher version number than where it was built.  If the major and minor version are the same or close for example [5.7 to 5.6], then the migration should work without issues.  A version pair of [5.7 to 5.1] is more likely to cause issues unless you have a very simple setup.  If the versions are too far apart work with your hosting provider to upgrade the MySQL engine on this server.<br/><br/>   <b>MariaDB:</b> If see a version of 10.N.N then the database distribution is a MariaDB flavor of MySQL.   While the distributions are very close there are some subtle differences.   Some operating systems will report the version such as "5.5.5-10.1.21-MariaDB" showing the correlation of both.  Please visit the online <a href="https://mariadb.com/kb/en/mariadb/mariadb-vs-mysql-compatibility/" target="_blank">MariaDB versus MySQL - Compatibility</a> page for more details.<br/><br/> Please note these messages are simply notices.  It is highly recommended that you continue with the install process and closely monitor the installer-log.txt file along with the install report found on step 3 of the installer.  Be sure to look for any notices/warnings/errors in these locations to validate the install process did not detect any errors. If any issues are found please visit the FAQ pages and see the question <a href="https://snapcreek.com/duplicator/docs/faqs-tech/#faq-installer-260-q" target="_blank">What if I get database errors or general warnings on the install report?</a>.');


/**
 * Class used to log information to the installer-log.txt file
 *
 * Standard: PSR-2
 * @link http://www.php-fig.org/psr/psr-2 Full Documentation
 *
 * @package SC\DUPX\Log
 *
 */
class DUPX_Log 
{

    /** 
     *  Used to write debug info to the text log file
     *
     *  @param string $msg		Any text data
     *  @param int $loglevel	Log level
     *
     *  @return string Write info to both the log and browser
     */
    public static function info($msg, $logging = 1)
	{
        if ($logging <= $GLOBALS["LOGGING"]) {
            @fwrite($GLOBALS["LOG_FILE_HANDLE"], "{$msg}\n");
        }
    }
	
    /** 
     *  Used to write errors to the text log file
     * 
     *  @param string $msg		Any text data
     *  @param int $loglevel	Log level
     * 
     *  @return string Write errors to both the log and browser
     */
    public static function error($msg)
	{
		$breaks = array("<br />","<br>","<br/>");  
		$log_msg = str_ireplace($breaks, "\r\n", $msg);
		$log_msg = strip_tags($log_msg);
		@fwrite($GLOBALS["LOG_FILE_HANDLE"], "\nINSTALLER ERROR:\n{$log_msg}\n");
		@fclose($GLOBALS["LOG_FILE_HANDLE"]);
        die("<div class='dupx-ui-error'><hr size='1' /><b style='color:#B80000;'>INSTALL ERROR!</b><br/>{$msg}</div>");
    }
}
?>