<?php

/**
 * Class used to update and edit web server configuration files
 *
 * Standard: PSR-2
 * @link http://www.php-fig.org/psr/psr-2 Full Documentation
 *
 * @package SC\DUPX\ServerConfig
 *
 */
class DUPX_ServerConfig
{

	/**
	 *  Clear .htaccess and web.config files and backup
	 *
	 *  @return null
	 */
	public static function reset()
	{


		DUPX_Log::info("\nWEB SERVER CONFIGURATION FILE RESET:");
		$timeStamp = date("ymdHis");

		//Apache
		@copy('.htaccess', ".htaccess.{$timeStamp}.orig");
		@unlink('.htaccess');
		@file_put_contents('.htaccess', "#Reset by Duplicator Installer.  Original can be found in .htaccess.{$timeStamp}.orig");

		//IIS
		@copy('web.config', "web.config.{$timeStamp}.orig");
		@unlink('web.config');
		@file_put_contents('web.config', "<!-- Reset by Duplicator Installer.  Original can be found in web.config.{$timeStamp}.orig -->");

		//.user.ini - For WordFence
		@copy('.user.ini', ".user.ini.{$timeStamp}.orig");
		@unlink('.user.ini');

		DUPX_Log::info("- Backup of .htaccess/web.config made to *.{$timeStamp}.orig");
		DUPX_Log::info("- Reset of .htaccess/web.config files");
		
		
		@chmod('.htaccess', 0644);
	}

	/**
	 *  Resets the .htaccess file to a very slimed down version with new paths
	 *
	 *  @return null
	 */
	public static function setup($dbh)
	{
		if (!isset($_POST['url_new'])) {
			return;
		}

		DUPX_Log::info("\nWEB SERVER CONFIGURATION FILE BASIC SETUP:");
		$currdata	 = parse_url($_POST['url_old']);
		$newdata	 = parse_url($_POST['url_new']);
		$currpath	 = DUPX_U::addSlash(isset($currdata['path']) ? $currdata['path'] : "");
		$newpath	 = DUPX_U::addSlash(isset($newdata['path']) ? $newdata['path'] : "");
		$timestamp   = date("Y-m-d H:i:s");
		$update_msg  = "# This file was updated by Duplicator on {$timestamp}. See .htaccess.orig for the original .htaccess file.\n";
		$update_msg .= "# Please note that other plugins and resources write to this file. If the time-stamp above is different\n";
		$update_msg .= "# than the current time-stamp on the file system then another resource has updated this file.\n";

		$empty_htaccess	 = false;
		$query_result	 = @mysqli_query($dbh, "SELECT option_value FROM `{$GLOBALS['FW_TABLEPREFIX']}options` WHERE option_name = 'permalink_structure' ");

		//If the permalink is set to Plain then don't update the rewrite rules
		if ($query_result) {
			$row = @mysqli_fetch_array($query_result);
			if ($row != null) {
				$permalink_structure = trim($row[0]);
				$empty_htaccess		 = empty($permalink_structure);
			}
		}

		if ($empty_htaccess) {
			$tmp_htaccess = "{$update_msg}";
			DUPX_Log::info("- No permalink structures set creating blank .htaccess file.");
		} else {
			$tmp_htaccess = <<<HTACCESS
{$update_msg}
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase {$newpath}
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . {$newpath}index.php [L]
</IfModule>
# END WordPress
HTACCESS;
				DUPX_Log::info("- Preparing .htaccess file with basic setup.");
			}

		file_put_contents('.htaccess', $tmp_htaccess);
		@chmod('.htaccess', 0644);
		DUPX_Log::info("Basic .htaccess file edit complete.  If using IIS web.config this process will need to be done manually.");

	}
}
?>