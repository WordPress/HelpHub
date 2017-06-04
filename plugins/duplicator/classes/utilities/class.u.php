<?php
/**
 * Recursivly scans a directory and finds all sym-links and unreadable files
 *
 * Standard: PSR-2
 * @link http://www.php-fig.org/psr/psr-2
 *
 * @package Duplicator
 * @subpackage classes/utilites
 * @copyright (c) 2017, Snapcreek LLC
 * @since 1.1.0
 *
 * @todo Refactor out IO methods into class.io.php file
 */

// Exit if accessed directly
if (!defined('DUPLICATOR_VERSION')) {
    exit;
}

class DUP_Util
{
    /**
     * Is PHP 5.2.9 or better running
     */
    public static $on_php_529_plus;

    /**
     * Is PHP 5.3 or better running
     */
    public static $on_php_53_plus;

    /**
     * Is PHP 5.4 or better running
     */
    public static $on_php_54_plus;

    /**
     *  Inited on load (see end of file)
     */
    public static function init()
    {
        self::$on_php_529_plus = version_compare(PHP_VERSION, '5.2.9') >= 0;
        self::$on_php_53_plus  = version_compare(PHP_VERSION, '5.3.0') >= 0;
        self::$on_php_54_plus  = version_compare(PHP_VERSION, '5.4.0') >= 0;
    }

    /**
     * PHP_SAPI for fcgi requires a data flush of at least 256
     * bytes every 40 seconds or else it forces a script hault
     *
     * @return string A series of 256 space characters
     */
    public static function fcgiFlush()
    {
        echo(str_repeat(' ', 300));
        @flush();
    }

    /**
     * Returns the wp-snapshot url
     *
     * @return string The full url of the duplicators snapshot storage directory
     */
    public static function snapshotURL()
    {
        return get_site_url(null, '', is_ssl() ? 'https' : 'http').'/'.DUPLICATOR_SSDIR_NAME.'/';
    }

    /**
     * Returns the last N lines of a file. Equivelent to tail command
     *
     * @param string $filepath The full path to the file to be tailed
     * @param int $lines The number of lines to return with each tail call
     *
     * @return string The last N parts of the file
     */
    public static function tailFile($filepath, $lines = 2)
    {

        // Open file
        $f = @fopen($filepath, "rb");
        if ($f === false) return false;

        // Sets buffer size
        $buffer = 256;

        // Jump to last character
        fseek($f, -1, SEEK_END);

        // Read it and adjust line number if necessary
        // (Otherwise the result would be wrong if file doesn't end with a blank line)
        if (fread($f, 1) != "\n") $lines -= 1;

        // Start reading
        $output = '';
        $chunk  = '';

        // While we would like more
        while (ftell($f) > 0 && $lines >= 0) {
            // Figure out how far back we should jump
            $seek   = min(ftell($f), $buffer);
            // Do the jump (backwards, relative to where we are)
            fseek($f, -$seek, SEEK_CUR);
            // Read a chunk and prepend it to our output
            $output = ($chunk  = fread($f, $seek)).$output;
            // Jump back to where we started reading
            fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);
            // Decrease our line counter
            $lines -= substr_count($chunk, "\n");
        }

        // While we have too many lines
        // (Because of buffer size we might have read too many)
        while ($lines++ < 0) {
            // Find first newline and remove all text before that
            $output = substr($output, strpos($output, "\n") + 1);
        }
        fclose($f);
        return trim($output);
    }

    /**
     * Runs the APC cache to pre-cache the php files
     *
     * @returns bool True if all files where cached
     */
    public static function runAPC()
    {
        if (function_exists('apc_compile_file')) {
            $file01 = @apc_compile_file(DUPLICATOR_PLUGIN_PATH."duplicator.php");
            return ($file01);
        } else {
            return false;
        }
    }

    /**
     * Display human readable byte sizes
     *
     * @param int $size    The size in bytes
     *
     * @return string The size of bytes readable such as 100KB, 20MB, 1GB etc.
     */
    public static function byteSize($size)
    {
        try {
            $units = array('B', 'KB', 'MB', 'GB', 'TB');
            for ($i = 0; $size >= 1024 && $i < 4; $i++) {
                $size /= 1024;
            }
            return round($size, 2).$units[$i];
        } catch (Exception $e) {
            return "n/a";
        }
    }

    /**
     * Makes path safe for any OS
     *      Paths should ALWAYS READ be "/"
     *          uni: /home/path/file.xt
     *          win:  D:/home/path/file.txt
     *
     * @param string $path		The path to make safe
     *
     * @return string A path with all slashes facing "/"
     */
    public static function safePath($path)
    {
        return str_replace("\\", "/", $path);
    }

    /**
     * Get current microtime as a float.  Method is used for simple profiling
     *
     * @see elapsedTime
     *
     * @return  string   A float in the form "msec sec", where sec is the number of seconds since the Unix epoch
     */
    public static function getMicrotime()
    {
        return microtime(true);
    }

    /**
     * Append the value to the string if it doesn't already exist
     *
     * @param string $string The string to append to
     * @param string $value The string to append to the $string
     *
     * @return string Returns the string with the $value appended once
     */
    public static function appendOnce($string, $value)
    {
        return $string.(substr($string, -1) == $value ? '' : $value);
    }

    /**
     * Return a string with the elapsed time
     *
     * @see getMicrotime()
     *
     * @param mixed number $end     The final time in the sequence to measure
     * @param mixed number $start   The start time in the sequence to measure
     *
     * @return  string   The time elapsed from $start to $end
     */
    public static function elapsedTime($end, $start)
    {
        return sprintf("%.2f sec.", abs($end - $start));
    }

    /**
     * List all of the files of a path
     *
     * @param string $path The full path to a system directory
     *
     * @return array of all files in that path
     * 
     * Notes:
     * 	- Avoid using glob() as GLOB_BRACE is not an option on some operating systems
     * 	- Pre PHP 5.3 DirectoryIterator will crash on unreadable files
	 *  - Scandir will not crash on unreadable items, but will not return results
     */
    public static function listFiles($path = '.')
    {
		try {
			$files = array();
			foreach (new DirectoryIterator($path) as $file) {
				$files[] = str_replace("\\", '/', $file->getPathname());
			}
			return $files;

		} catch (Exception $exc) {

			$result = array();
			$files = @scandir($path);
			if (is_array($files)) {
				foreach ($files as $file) {
					$result[] = str_replace("\\", '/', $path) . $file;
				}
			}
			return $result;
		}
    }

    /**
     * List all of the directories of a path
     *
     * @param string $path The full path to a system directory
     *
     * @return array of all dirs in the $path
     */
    public static function listDirs($path = '.')
    {
        $dirs = array();

        foreach (new DirectoryIterator($path) as $file) {
            if ($file->isDir() && !$file->isDot()) {
                $dirs[] = DUP_Util::safePath($file->getPathname());
            }
        }
        return $dirs;
    }

    /**
     * Does the directory have content
     *
     * @param string $path The full path to a system directory
     *
     * @return bool Returns true if directory is empty
     */
    public static function isDirectoryEmpty($path)
    {
        if (!is_readable($path)) return NULL;
        return (count(scandir($path)) == 2);
    }

    /**
     * Size of the directory recursively in bytes
     *
     * @param string $path The full path to a system directory
     *
     * @return int Returns the size of the directory in bytes
     *
     */
    public static function getDirectorySize($path)
    {
        if (!file_exists($path)) return 0;
        if (is_file($path)) return filesize($path);

        $size = 0;
        $list = glob($path."/*");
        if (!empty($list)) {
            foreach ($list as $file)
                $size += self::getDirectorySize($file);
        }
        return $size;
    }

    /**
     * Can shell_exec be called on this server
     *
     * @return bool Returns true if shell_exec can be called on server
     *
     */
    public static function hasShellExec()
    {
        $cmds = array('shell_exec', 'escapeshellarg', 'escapeshellcmd', 'extension_loaded');

        //Function disabled at server level
        if (array_intersect($cmds, array_map('trim', explode(',', @ini_get('disable_functions'))))) return false;

        //Suhosin: http://www.hardened-php.net/suhosin/
        //Will cause PHP to silently fail
        if (extension_loaded('suhosin')) {
            $suhosin_ini = @ini_get("suhosin.executor.func.blacklist");
            if (array_intersect($cmds, array_map('trim', explode(',', $suhosin_ini)))) return false;
        }

        // Can we issue a simple echo command?
        if (!@shell_exec('echo duplicator')) return false;

        return true;
    }

    /**
     * Is the server running Windows operating system
     *
     * @return bool Returns true if operating system is Windows
     *
     */
    public static function isWindows()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return true;
        }
        return false;
    }

    /**
     * Does the current user have the capability
     *
     * @return null Dies if user doesn't have the correct capability
     */
    public static function hasCapability($permission = 'read')
    {
        $capability = $permission;
        $capability = apply_filters('wpfront_user_role_editor_duplicator_translate_capability', $capability);

        if (!current_user_can($capability)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'duplicator'));
            return;
        }
    }

    /**
     *  Gets the name of the owner of the current PHP script
     *
     * @return string The name of the owner of the current PHP script
     */
    public static function getCurrentUser()
    {
        $unreadable = 'Undetectable';
        if (function_exists('get_current_user') && is_callable('get_current_user')) {
            $user = get_current_user();
            return strlen($user) ? $user : $unreadable;
        }
        return $unreadable;
    }

    /**
     * Gets the owner of the PHP process
     *
     * @return string Gets the owner of the PHP process
     */
    public static function getProcessOwner()
    {
        $unreadable = 'Undetectable';
        $user       = '';
        try {
            if (function_exists('exec')) {
                $user = exec('whoami');
            }

            if (!strlen($user) && function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
                $user = posix_getpwuid(posix_geteuid());
                $user = $user['name'];
            }

            return strlen($user) ? $user : $unreadable;
        } catch (Exception $ex) {
            return $unreadable;
        }
    }

    /**
     * Creates the snapshot directory if it doesn't already exisit
     *
     * @return null
     */
    public static function initSnapshotDirectory()
    {
        $path_wproot = DUP_Util::safePath(DUPLICATOR_WPROOTPATH);
        $path_ssdir  = DUP_Util::safePath(DUPLICATOR_SSDIR_PATH);
        $path_plugin = DUP_Util::safePath(DUPLICATOR_PLUGIN_PATH);

        //--------------------------------
        //CHMOD DIRECTORY ACCESS
        //wordpress root directory
        @chmod($path_wproot, 0755);

        //snapshot directory
        @mkdir($path_ssdir, 0755);
        @chmod($path_ssdir, 0755);

        //snapshot tmp directory
        $path_ssdir_tmp = $path_ssdir.'/tmp';
        @mkdir($path_ssdir_tmp, 0755);
        @chmod($path_ssdir_tmp, 0755);

        //plugins dir/files
        @chmod($path_plugin.'files', 0755);

        //--------------------------------
        //FILE CREATION
        //SSDIR: Create Index File
        $ssfile = @fopen($path_ssdir.'/index.php', 'w');
        @fwrite($ssfile,
                '<?php error_reporting(0);  if (stristr(php_sapi_name(), "fcgi")) { $url  =  "http://" . $_SERVER["HTTP_HOST"]; header("Location: {$url}/404.html");} else { header("HTTP/1.1 404 Not Found", true, 404);} exit(); ?>');
        @fclose($ssfile);

        //SSDIR: Create token file in snapshot
        $tokenfile = @fopen($path_ssdir.'/dtoken.php', 'w');
        @fwrite($tokenfile,
                '<?php error_reporting(0);  if (stristr(php_sapi_name(), "fcgi")) { $url  =  "http://" . $_SERVER["HTTP_HOST"]; header("Location: {$url}/404.html");} else { header("HTTP/1.1 404 Not Found", true, 404);} exit(); ?>');
        @fclose($tokenfile);

        //SSDIR: Create .htaccess
        $storage_htaccess_off = DUP_Settings::Get('storage_htaccess_off');
        if ($storage_htaccess_off) {
            @unlink($path_ssdir.'/.htaccess');
        } else {
            $htfile   = @fopen($path_ssdir.'/.htaccess', 'w');
            $htoutput = "Options -Indexes";
            @fwrite($htfile, $htoutput);
            @fclose($htfile);
        }

        //SSDIR: Robots.txt file
        $robotfile = @fopen($path_ssdir.'/robots.txt', 'w');
        @fwrite($robotfile, "User-agent: * \nDisallow: /".DUPLICATOR_SSDIR_NAME.'/');
        @fclose($robotfile);

        //PLUG DIR: Create token file in plugin
        $tokenfile2 = @fopen($path_plugin.'installer/dtoken.php', 'w');
        @fwrite($tokenfile2,
                '<?php @error_reporting(0); @require_once("../../../../wp-admin/admin.php"); global $wp_query; $wp_query->set_404(); header("HTTP/1.1 404 Not Found", true, 404); header("Status: 404 Not Found"); @include(get_template_directory () . "/404.php"); ?>');
        @fclose($tokenfile2);
    }

    /**
     * Attempts to get the file zip path on a users system
     *
     * @return null
     */
    public static function getZipPath()
    {
        $filepath = null;

        if (self::hasShellExec()) {
            if (shell_exec('hash zip 2>&1') == NULL) {
                $filepath = 'zip';
            } else {
                $possible_paths = array(
                    '/usr/bin/zip',
                    '/opt/local/bin/zip'
                    //'C:/Program\ Files\ (x86)/GnuWin32/bin/zip.exe');
                );

                foreach ($possible_paths as $path) {
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
	 * Returns a GUIDv4 string
	 *
	 * Uses the best cryptographically secure method
	 * for all supported pltforms with fallback to an older,
	 * less secure version.
	 *
	 * @param bool $trim	Trim '}{' curly
	 * @param bool $nodash  Remove the dashes from the GUID
	 * @param bool $grail	Add a 'G' to the end for status
	 * 
	 * @return string
	 */
	public static function GUIDv4($trim = true, $nodash = true, $gtrail = true)
	{
		// Windows
		if (function_exists('com_create_guid') === true) {
			if ($trim === true) {
				$guidv4	 = trim(com_create_guid(), '{}');
			} else {
				$guidv4	 = com_create_guid();
			}

		//Linux
		} elseif (function_exists('openssl_random_pseudo_bytes') === true) {
			$data	 = openssl_random_pseudo_bytes(16);
			$data[6] = chr(ord($data[6]) & 0x0f | 0x40);	// set version to 0100
			$data[8] = chr(ord($data[8]) & 0x3f | 0x80);	// set bits 6-7 to 10
			$guidv4	 = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

		// Fallback (PHP 4.2+)
		} else {

			mt_srand((double) microtime() * 10000);
			$charid	 = strtolower(md5(uniqid(rand(), true)));
			$hyphen	 = chr(45);				  // "-"
			$lbrace	 = $trim ? "" : chr(123);	// "{"
			$rbrace	 = $trim ? "" : chr(125);	// "}"
			$guidv4	 = $lbrace.
				substr($charid, 0, 8).$hyphen.
				substr($charid, 8, 4).$hyphen.
				substr($charid, 12, 4).$hyphen.
				substr($charid, 16, 4).$hyphen.
				substr($charid, 20, 12).
				$rbrace;
		}

		if ($nodash) {
			$guidv4 = str_replace('-', '', $guidv4);
		}

		if ($gtrail) {
			$guidv4 = $guidv4.'G';
		}

		return $guidv4;
	}
}
DUP_Util::init();
?>