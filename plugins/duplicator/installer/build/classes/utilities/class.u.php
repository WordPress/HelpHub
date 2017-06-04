<?php
/**
 * Various Static Utility methods for working with the installer
 *
 * Standard: PSR-2
 * @link http://www.php-fig.org/psr/psr-2 Full Documentation
 *
 * @package SC\DUPX\U
 *
 */
class DUPX_U
{

    /**
     * Adds a slash to the end of a file or directory path
     *
     * @param string $path		A path
     *
     * @return string The orginal $path with a with '/' added to the end.
     */
    public static function addSlash($path)
    {
        $last_char = substr($path, strlen($path) - 1, 1);
        if ($last_char != '/') {
            $path .= '/';
        }
        return $path;
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
        return sprintf("%.4f sec.", abs($end - $start));
    }

    /**
     * Convert all applicable characters to HTML entities
     *
     * @param string $string    String that needs conversion
     * @param bool $echo        Echo or return as a variable
     *
     * @return string    Escaped string.
     */
    public static function escapeHTML($string = '', $echo = false)
    {
        $output = htmlentities($string, ENT_QUOTES, 'UTF-8');
        if ($echo) {
            echo $output;
        } else {
            return $output;
        }
    }

    /**
     *  Returns 256 spaces
     *
     *  PHP_SAPI for fcgi requires a data flush of at least 256
     *  bytes every 40 seconds or else it forces a script hault
     *
     * @return string A series of 256 spaces ' '
     */
    public static function fcgiFlush()
    {
        echo(str_repeat(' ', 256));
        @flush();
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
     *  Returns the active plugins for the WordPress website in the package
     *
     *  @param  obj    $dbh	 A database connection handle
	 *
     *  @return array  $list A list of active plugins
     */
    public static function getActivePlugins($dbh)
    {
        $query = @mysqli_query($dbh, "SELECT option_value FROM `{$GLOBALS['FW_TABLEPREFIX']}options` WHERE option_name = 'active_plugins' ");
        if ($query) {
            $row         = @mysqli_fetch_array($query);
            $all_plugins = unserialize($row[0]);
            if (is_array($all_plugins)) {
                return $all_plugins;
            }
        }
        return array();
    }

    /**
     *  Check to see if the internet is accessable
     *
     *  Note: fsocketopen on windows doesn't seem to honor $timeout setting.
     *
     *  @param string $url		A url e.g without prefix "ajax.googleapis.com"
     *  @param string $port		A valid port number
     *
     *  @return bool	Returns true PHP can request the URL
     */
    public static function isURLActive($url, $port, $timeout = 5)
    {
        if (function_exists('fsockopen')) {
            @ini_set("default_socket_timeout", 5);
            $port      = isset($port) && is_integer($port) ? $port : 80;
            $connected = @fsockopen($url, $port, $errno, $errstr, $timeout); //website and port
            if ($connected) {
                @fclose($connected);
                return true;
            }
            return false;
        } else {
            return false;
        }
    }

    /**
     * Does a string have non ascii characters
     *
     * @param string $string Any string blob
     *
     * @return bool Returns true if any non ascii character is found in the blob
     */
    public static function isNonASCII($string)
    {
        return preg_match('/[^\x20-\x7f]/', $string);
    }


	/**
     * Is the string json
     *
     * @param string $string Any string blob
     *
     * @return bool Returns true if the string is json encoded
     */
    public static function isJSON($string)
    {
        return is_string($string) && is_array(json_decode($string, true)) ? true : false;
    }

    /**
     *  The characters that are special in the replacement value of preg_replace are not the
     *  same characters that are special in the pattern.  Allows for '$' to be safely passed.
     *
     *  @param string $str		The string to replace on
     */
    public static function pregReplacementQuote($str)
    {
        return preg_replace('/(\$|\\\\)(?=\d)/', '\\\\\1', $str);
    }

    /**
     * Display human readable byte sizes
     *
     * @param string $size	The size in bytes
     *
     * @return string Human readable bytes such as 50MB, 1GB
     */
    public static function readableByteSize($size)
    {
        try {
            $units = array('B', 'KB', 'MB', 'GB', 'TB');
            for ($i = 0; $size >= 1024 && $i < 4; $i++)
                $size /= 1024;
            return round($size, 2).$units[$i];
        } catch (Exception $e) {
            return "n/a";
        }
    }

    /**
     * Converts shorthand memory notation value to bytes
     *
     * @param $val Memory size shorthand notation string such as 10M, 1G
     *
     * @returns int The byte representation of the shortand $val
     */
    public static function getBytes($val)
    {
        $val  = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        switch ($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
                break;
            default :
                $val = null;
        }
        return $val;
    }

    /**
     *  Makes path safe for any OS for PHP
     *
     *  Paths should ALWAYS READ be "/"
     * 		uni:  /home/path/file.xt
     * 		win:  D:/home/path/file.txt
     *
     *  @param string $path		The path to make safe
     *
     *  @return string The orginal $path with a with all slashes facing '/'.
     */
    public static function setSafePath($path)
    {
        return str_replace("\\", "/", $path);
    }

    /**
     *  Looks for a list of strings in a string and returns each list item that is found
     *
     *  @param array  $list		An array of strings to search for
     *  @param string $haystack	The string blob to search through
     *
     *  @return array An array of strings from the $list array fround in the $haystack
     */
    public static function getListValues($list, $haystack)
    {
        $found = array();
        foreach ($list as $var) {
            if (strstr($haystack, $var) !== false) {
                array_push($found, $var);
            }
        }
        return $found;
    }

    /**
     *  Makes path unsafe for any OS for PHP used primarly to show default
     *  Winodws OS path standard
     *
     *  @param string $path		The path to make unsafe
     *
     *  @return string The orginal $path with a with all slashes facing '\'.
     */
    public static function unsetSafePath($path)
    {
        return str_replace("/", "\\", $path);
    }

}
?>