<?php

/**
 * Lightweight abstraction layer for common simple server based routines
 *
 * Standard: PSR-2
 * @link http://www.php-fig.org/psr/psr-2 Full Documentation
 *
 * @package SC\DUPX\Server
 *
 */
class DUPX_Server
{
	/**
	 * Returns true if safe mode is enabled
	 */
	public static $php_safe_mode_on = false;

	/**
	 * The servers current PHP version
	 */
	public static $php_version = 0;

	/**
	 * The minimum PHP version the installer will support
	 */
	public static $php_version_min = "5.2.7";

	/**
	 * Is the current servers version of PHP safe to use with the installer
	 */
	public static $php_version_safe = false;

	/**
	 *  Used to init the staic properties
	 */
	public static function init()
	{
		self::$php_safe_mode_on	 = in_array(strtolower(@ini_get('safe_mode')), array('on', 'yes', 'true', 1, "1"));
		self::$php_version		 = phpversion();
		self::$php_version_safe	 = (version_compare(phpversion(), self::$php_version_min) >= 0);
	}

	/**
	 *  Is the directory provided writable by PHP
	 *
	 * 	@param string $path A physical directory path
	 *
	 *  @return bool Returns true if PHP can write to the path provided
	 */
	public static function isDirWritable($path)
	{
		if (!@is_writeable($path)) return false;

		if (is_dir($path)) {
			if ($dh = @opendir($path)) {
				closedir($dh);
			} else {
				return false;
			}
		}
		return true;
	}

	/**
	 *  Can this server process in shell_exec mode
	 *
	 *  @return bool	Returns true is the server can run shell_exec commands
	 */
	public static function hasShellExec()
	{
		if (array_intersect(array('shell_exec', 'escapeshellarg', 'escapeshellcmd', 'extension_loaded'), array_map('trim', explode(',', @ini_get('disable_functions'))))) return false;

		//Suhosin: http://www.hardened-php.net/suhosin/
		//Will cause PHP to silently fail.
		if (extension_loaded('suhosin')) return false;

		// Can we issue a simple echo command?
		if (!@shell_exec('echo duplicator')) return false;

		return true;
	}

	/**
	 *  Returns the path where the zip command can be called on this server
	 *
	 *  @return string	The path to where the zip command can be processed
	 */
	public static function getUnzipPath()
	{
		$filepath = null;
		if (self::hasShellExec()) {
			if (shell_exec('hash unzip 2>&1') == NULL) {
				$filepath = 'unzip';
			} else {
				$try_paths = array(
					'/usr/bin/unzip',
					'/opt/local/bin/unzip');
				foreach ($try_paths as $path) {
					if (file_exists($path)) {
						$filepath = $path;
						break;
					}
				}
			}
		}
		return $filepath;
	}


	/**
     *  A safe method used to copy larger files
     *
     *  @param string $source		The path to the file being copied
     *  @param string $destination	The path to the file being made
	 *
	 *	@return bool	True if the file was copied 
     */
    public static function copyFile($source, $destination)
    {
		try {
			$sp = fopen($source, 'r');
			$op = fopen($destination, 'w');

			while (!feof($sp)) {
				$buffer = fread($sp, 512);  // use a buffer of 512 bytes
				fwrite($op, $buffer);
			}
			// close handles
			fclose($op);
			fclose($sp);
			return true;

		} catch (Exception $ex) {
			return false;
		}
    }


	/**
     *  Returns an array of zip files found in the current executing directory
     *
     *  @return array of zip files
     */
    public static function getZipFiles()
    {
        $files = array();
        foreach (glob("*.zip") as $name) {
            if (file_exists($name)) {
                $files[] = $name;
            }
        }

        if (count($files) > 0) {
            return $files;
        }

        //FALL BACK: Windows XP has bug with glob,
        //add secondary check for PHP lameness
        if ($dh = opendir('.')) {
            while (false !== ($name = readdir($dh))) {
                $ext = substr($name, strrpos($name, '.') + 1);
                if (in_array($ext, array("zip"))) {
                    $files[] = $name;
                }
            }
            closedir($dh);
        }

        return $files;
    }
}
//INIT Class Properties
DUPX_Server::init();
?>