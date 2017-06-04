<?php

/**
 * Lightweight abstraction layer for common simple database routines
 *
 * Standard: PSR-2
 * @link http://www.php-fig.org/psr/psr-2 Full Documentation
 *
 * @package SC\DUPX\DB
 *
 */
class DUPX_DB
{

	/**
	 * MySQL connection wrapper with support for port
	 *
	 * @param string    $host       The server host name
	 * @param string    $username   The server DB user name
	 * @param string    $password   The server DB password
	 * @param string    $dbname     The server DB name
	 * @param int       $port       The server DB port
	 *
	 * @return database connection handle
	 */
	public static function connect($host, $username, $password, $dbname = '', $port = null)
	{
		//sock connections
		if ('sock' === substr($host, -4)) {
			$url_parts	 = parse_url($host);
			$dbh		 = @mysqli_connect('localhost', $username, $password, $dbname, null, $url_parts['path']);
		} else {
			$dbh = @mysqli_connect($host, $username, $password, $dbname, $port);
		}
		return $dbh;
	}

	/**
	 *  Count the tables in a given database
	 *
	 * @param obj    $dbh       A valid database link handle
	 * @param string $dbname    Database to count tables in
	 *
	 * @return int  The number of tables in the database
	 */
	public static function countTables($dbh, $dbname)
	{
		$res = mysqli_query($dbh, "SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = '{$dbname}' ");
		$row = mysqli_fetch_row($res);
		return is_null($row) ? 0 : $row[0];
	}

	/**
	 * Returns the number of rows in a table
	 *
	 * @param obj    $dbh   A valid database link handle
	 * @param string $name	A valid table name
	 */
	public static function countTableRows($dbh, $name)
	{
		$total = mysqli_query($dbh, "SELECT COUNT(*) FROM `$name`");
		if ($total) {
			$total = @mysqli_fetch_array($total);
			return $total[0];
		} else {
			return 0;
		}
	}

	/**
	 * Returns the tables for a database as an array
	 *
	 * @param obj $dbh   A valid database link handle
	 *
	 * @return array  A list of all table names
	 */
	public static function getTables($dbh)
	{
		$query = @mysqli_query($dbh, 'SHOW TABLES');
		if ($query) {
			while ($table = @mysqli_fetch_array($query)) {
				$all_tables[] = $table[0];
			}
			if (isset($all_tables) && is_array($all_tables)) {
				return $all_tables;
			}
		}
		return array();
	}

	/**
	 * Get the requested MySQL system variable
	 *
	 * @param obj    $dbh   A valid database link handle
	 * @param string $name  The database variable name to lookup
	 *
	 * @return string the server variable to query for
	 */
	public static function getVariable($dbh, $name)
	{
		$result	 = @mysqli_query($dbh, "SHOW VARIABLES LIKE '{$name}'");
		$row	 = @mysqli_fetch_array($result);
		@mysqli_free_result($result);
		return isset($row[1]) ? $row[1] : null;
	}

	/**
	 * Gets the MySQL database version number
	 *
	 * @param obj    $dbh   A valid database link handle
	 * @param bool   $full  True:  Gets the full version
	 *                      False: Gets only the numeric portion i.e. 5.5.6 or 10.1.2 (for MariaDB)
	 *
	 * @return false|string 0 on failure, version number on success
	 */
	public static function getVersion($dbh, $full = false)
	{
		if ($full) {
			$version = self::getVariable($dbh, 'version');
		} else {
			$version = preg_replace('/[^0-9.].*/', '', self::getVariable($dbh, 'version'));
		}

		$version = is_null($version) ? null : $version;
		return empty($version) ? 0 : $version;
	}

	/**
	 * Returns a more detailed string about the msyql server version
	 * For example on some systems the result is 5.5.5-10.1.21-MariaDB
	 * this format is helpful for providing the user a full overview
	 *
	 * @param conn $dbh Database connection handle
	 *
	 * @return string The full details of mysql
	 */
	public static function getServerInfo($dbh)
	{
		return mysqli_get_server_info($dbh);
	}

	/**
	 * Determine if a MySQL database supports a particular feature
	 *
	 * @param conn $dbh Database connection handle
	 * @param string $feature the feature to check for
	 *
	 * @return bool
	 */
	public static function hasAbility($dbh, $feature)
	{
		$version = self::getVersion($dbh);

		switch (strtolower($feature)) {
			case 'collation' :
			case 'group_concat' :
			case 'subqueries' :
				return version_compare($version, '4.1', '>=');
			case 'set_charset' :
				return version_compare($version, '5.0.7', '>=');
		};
		return false;
	}

	/**
	 * Sets the MySQL connection's character set.
	 *
	 * @param resource $dbh     The resource given by mysqli_connect
	 * @param string   $charset The character set (optional)
	 * @param string   $collate The collation (optional)
	 *
	 * @return bool True on success
	 */
	public static function setCharset($dbh, $charset = null, $collate = null)
	{
		$charset = (!isset($charset) ) ? $GLOBALS['DBCHARSET_DEFAULT'] : $charset;
		$collate = (!isset($collate) ) ? $GLOBALS['DBCOLLATE_DEFAULT'] : $collate;

		if (self::hasAbility($dbh, 'collation') && !empty($charset)) {
			if (function_exists('mysqli_set_charset') && self::hasAbility($dbh, 'set_charset')) {
				return mysqli_set_charset($dbh, $charset);
			} else {
				$sql = " SET NAMES {$charset}";
				if (!empty($collate)) $sql .= " COLLATE {$collate}";
				return mysqli_query($dbh, $sql);
			}
		}
	}
}
?>