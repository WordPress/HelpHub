<?php
/**
 * Lightweight abstraction layer for common simple database routines
 *
 * Standard: PSR-2
 * @link http://www.php-fig.org/psr/psr-2
 *
 * @package Duplicator
 * @subpackage classes/utilities
 * @copyright (c) 2017, Snapcreek LLC
 * @since 1.1.32
 *
 */

// Exit if accessed directly
if (!defined('DUPLICATOR_VERSION')) {
    exit;
}

class DUP_DB extends wpdb
{

    /**
     * Get the requested MySQL system variable
     *
     * @param string $name The database variable name to lookup
     *
     * @return string the server variable to query for
     */
    public static function getVariable($name)
    {
        global $wpdb;
        if (strlen($name)) {
            $row = $wpdb->get_row("SHOW VARIABLES LIKE '{$name}'", ARRAY_N);
            return isset($row[1]) ? $row[1] : null;
        } else {
            return null;
        }
    }

    /**
     * Gets the MySQL database version number
     *
     * @param bool $full    True:  Gets the full version
     *                      False: Gets only the numeric portion i.e. 5.5.6 or 10.1.2 (for MariaDB)
     *
     * @return false|string 0 on failure, version number on success
     */
    public static function getVersion($full = false)
    {
		global $wpdb;

        if ($full) {
            $version = self::getVariable('version');
        } else {
            $version = preg_replace('/[^0-9.].*/', '', self::getVariable('version'));
        }

		//Fall-back for servers that have restricted SQL for SHOW statement
		if (empty($version)) {
			$version = $wpdb->db_version();
		}

        return empty($version) ? 0 : $version;
    }


    /**
     * Returns the mysqldump path if the server is enabled to execute it
     * @return boolean|string
     */
    public static function getMySqlDumpPath()
    {

        //Is shell_exec possible
        if (!DUP_Util::hasShellExec()) {
            return false;
        }

        $custom_mysqldump_path = DUP_Settings::Get('package_mysqldump_path');
        $custom_mysqldump_path = (strlen($custom_mysqldump_path)) ? $custom_mysqldump_path : '';

        //Common Windows Paths
        if (DUP_Util::isWindows()) {
            $paths = array(
                $custom_mysqldump_path,
                'C:/xampp/mysql/bin/mysqldump.exe',
                'C:/Program Files/xampp/mysql/bin/mysqldump',
                'C:/Program Files/MySQL/MySQL Server 6.0/bin/mysqldump',
                'C:/Program Files/MySQL/MySQL Server 5.5/bin/mysqldump',
                'C:/Program Files/MySQL/MySQL Server 5.4/bin/mysqldump',
                'C:/Program Files/MySQL/MySQL Server 5.1/bin/mysqldump',
                'C:/Program Files/MySQL/MySQL Server 5.0/bin/mysqldump',
            );

            //Common Linux Paths
        } else {
            $path1     = '';
            $path2     = '';
            $mysqldump = `which mysqldump`;
            if (@is_executable($mysqldump)) $path1     = (!empty($mysqldump)) ? $mysqldump : '';

            $mysqldump = dirname(`which mysql`)."/mysqldump";
            if (@is_executable($mysqldump)) $path2     = (!empty($mysqldump)) ? $mysqldump : '';

            $paths = array(
                $custom_mysqldump_path,
                $path1,
                $path2,
                '/usr/local/bin/mysqldump',
                '/usr/local/mysql/bin/mysqldump',
                '/usr/mysql/bin/mysqldump',
                '/usr/bin/mysqldump',
                '/opt/local/lib/mysql6/bin/mysqldump',
                '/opt/local/lib/mysql5/bin/mysqldump',
                '/opt/local/lib/mysql4/bin/mysqldump',
            );
        }

        // Find the one which works
        foreach ($paths as $path) {
            if (@is_executable($path)) return $path;
        }

        return false;
    }

}