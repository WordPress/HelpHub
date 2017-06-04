<?php
//POST PARAMS
$_POST['dbaction']			= isset($_POST['dbaction']) ? $_POST['dbaction'] : 'create';
$_POST['dbnbsp']			= (isset($_POST['dbnbsp']) && $_POST['dbnbsp'] == '1') ? true : false;
$_POST['ssl_admin']			= (isset($_POST['ssl_admin']))  ? true : false;
$_POST['ssl_login']			= (isset($_POST['ssl_login']))  ? true : false;
$_POST['cache_wp']			= (isset($_POST['cache_wp']))   ? true : false;
$_POST['cache_path']		= (isset($_POST['cache_path'])) ? true : false;
$_POST['archive_name']		= isset($_POST['archive_name']) ? $_POST['archive_name'] : null;

//LOGGING
$POST_LOG = $_POST;
unset($POST_LOG['dbpass']);
ksort($POST_LOG);

//PAGE VARS
$date_time      = @date('h:i:s');
$root_path		= DUPX_U::setSafePath($GLOBALS['CURRENT_ROOT_PATH']);
$ajax2_start	= DUPX_U::getMicrotime();
$JSON = array();
$JSON['pass'] = 0;

/** JSON RESPONSE: Most sites have warnings turned off by default, but if they're turned on the warnings
cause errors in the JSON data Here we hide the status so warning level is reset at it at the end*/
$ajax1_error_level = error_reporting();
error_reporting(E_ERROR);

//====================================================================================================
//DATABASE TEST CONNECTION
//====================================================================================================
if (isset($_GET['dbtest']))
{
	$html     = "";
	$baseport =  parse_url($_POST['dbhost'], PHP_URL_PORT);
	$dbConn   = DUPX_DB::connect($_POST['dbhost'], $_POST['dbuser'], $_POST['dbpass'], null, $_POST['dbport']);
	$dbErr	  = mysqli_connect_error();

	$dbFound  = mysqli_select_db($dbConn, $_POST['dbname']);
	$port_view = (is_int($baseport) || substr($_POST['dbhost'], -1) == ":") ? "Port=[Set in Host]" : "Port={$_POST['dbport']}";

	$tstSrv   = ($dbConn)  ? "<div class='dupx-pass'>Success</div>" : "<div class='dupx-fail'>Fail</div>";
	$tstDB    = ($dbFound) ? "<div class='dupx-pass'>Success</div>" : "<div class='dupx-fail'>Fail</div>";

    $dbversion_info         = DUPX_DB::getServerInfo($dbConn);
    $dbversion_info         = empty($dbversion_info) ? 'no connection' : $dbversion_info;
    $dbversion_info_fail    = version_compare(DUPX_DB::getVersion($dbConn), '5.5.3') < 0;

    $dbversion_compat       = DUPX_DB::getVersion($dbConn);
	$dbversion_compat       = empty($dbversion_compat) ? 'no connection' : $dbversion_compat;
    $dbversion_compat_fail  = version_compare($dbversion_compat, $GLOBALS['FW_VERSION_DB']) < 0;

    $tstInfo = ($dbversion_info_fail)
		? "<div class='dupx-notice'>{$dbversion_info}</div>"
        : "<div class='dupx-pass'>{$dbversion_info}</div>";

	$tstCompat = ($dbversion_compat_fail)
		? "<div class='dupx-notice'>This Server: [{$dbversion_compat}] -- Package Server: [{$GLOBALS['FW_VERSION_DB']}]</div>"
		: "<div class='dupx-pass'>This Server: [{$dbversion_compat}] -- Package Server: [{$GLOBALS['FW_VERSION_DB']}]</div>";

	$html	 .= <<<DATA
	<div class='s2-db-test'>
		<small>
			Using Connection String:<br/>
			Host={$_POST['dbhost']}; Database={$_POST['dbname']}; Uid={$_POST['dbuser']}; Pwd={$_POST['dbpass']}; {$port_view}
		</small>
		<table class='s2-db-test-dtls'>
			<tr>
				<td>Host:</td>
				<td>{$tstSrv}</td>
			</tr>
			<tr>
				<td>Database:</td>
				<td>{$tstDB}</td>
			</tr>
			<tr>
				<td>Version:</td>
				<td>{$tstInfo}</td>
			</tr>
            <tr>
				<td>Compatibility:</td>
				<td>{$tstCompat}</td>
			</tr>
		</table>
DATA;

	//--------------------------------
	//WARNING: DB has tables with create option
	if ($_POST['dbaction'] == 'create')
	{
		$tblcount = DUPX_DB::countTables($dbConn, $_POST['dbname']);
		$html .= ($tblcount > 0)
			? "<div class='warn-msg'><b>WARNING:</b> " . sprintf(ERR_DBEMPTY, $_POST['dbname'], $tblcount) . "</div>"
			: '';
	}

	//WARNNG: Input has utf8
	$dbConnItems = array($_POST['dbhost'], $_POST['dbuser'], $_POST['dbname'],$_POST['dbpass']);
	$dbUTF8_tst  = false;
	foreach ($dbConnItems as $value)
	{
		if (DUPX_U::isNonASCII($value)) {
			$dbUTF8_tst = true;
			break;
		}
	}

    //WARNING: UTF8 Data in Connection String
	$html .=  (! $dbConn && $dbUTF8_tst)
		? "<div class='warn-msg'><b>WARNING:</b> " . ERR_TESTDB_UTF8 .  "</div>"
		: '';

	//NOTICE: Version Too Low
	$html .=  ($dbversion_info_fail)
		? "<div class='warn-msg'><b>NOTICE:</b> " . ERR_TESTDB_VERSION_INFO . "</div>"
		: '';

    //NOTICE: Version Incompatibility
	$html .=  ($dbversion_compat_fail)
		? "<div class='warn-msg'><b>NOTICE:</b> " . ERR_TESTDB_VERSION_COMPAT . "</div>"
		: '';

	$html .= "</div>";
	die($html);
}

//===============================
//ERROR MESSAGES
//===============================
//ERR_MAKELOG
($GLOBALS['LOG_FILE_HANDLE'] != false) or DUPX_Log::error(ERR_MAKELOG);

//ERR_MYSQLI_SUPPORT
function_exists('mysqli_connect') or DUPX_Log::error(ERR_MYSQLI_SUPPORT);

//ERR_DBCONNECT
$dbh = DUPX_DB::connect($_POST['dbhost'], $_POST['dbuser'], $_POST['dbpass'], null, $_POST['dbport']);
@mysqli_query($dbh, "SET wait_timeout = {$GLOBALS['DB_MAX_TIME']}");
($dbh) or DUPX_Log::error(ERR_DBCONNECT . mysqli_connect_error());
if ($_POST['dbaction'] == 'empty') {
	mysqli_select_db($dbh, $_POST['dbname']) or DUPX_Log::error(sprintf(ERR_DBCREATE, $_POST['dbname']));
}
//ERR_DBEMPTY
if ($_POST['dbaction'] == 'create' ) {
	$tblcount = DUPX_DB::countTables($dbh, $_POST['dbname']);
	if ($tblcount > 0) {
		DUPX_Log::error(sprintf(ERR_DBEMPTY, $_POST['dbname'], $tblcount));
	}
}

$log = <<<LOG
\n\n********************************************************************************
* DUPLICATOR-LITE: INSTALL-LOG
* STEP-2 START @ {$date_time}
* NOTICE: Do NOT post to public sites or forums
********************************************************************************
LOG;
DUPX_Log::info($log);

$log  = "--------------------------------------\n";
$log .= "POST DATA\n";
$log .= "--------------------------------------\n";
$log .= print_r($POST_LOG, true);
DUPX_Log::info($log, 2);


//====================================================================================================
//DATABASE ROUTINES
//====================================================================================================
$log = '';
$faq_url = $GLOBALS['FAQ_URL'];
$db_file_size = filesize('database.sql');
$php_mem = $GLOBALS['PHP_MEMORY_LIMIT'];
$php_mem_range = DUPX_U::getBytes($GLOBALS['PHP_MEMORY_LIMIT']);
$php_mem_range = $php_mem_range == null ?  0 : $php_mem_range - 5000000; //5 MB Buffer

//Fatal Memory errors from file_get_contents is not catchable.
//Try to warn ahead of time with a buffer in memory difference
if ($db_file_size >= $php_mem_range  && $php_mem_range != 0)
{
	$db_file_size = DUPX_U::readableByteSize($db_file_size);
	$msg = "\nWARNING: The database script is '{$db_file_size}' in size.  The PHP memory allocation is set\n";
	$msg .= "at '{$php_mem}'.  There is a high possibility that the installer script will fail with\n";
	$msg .= "a memory allocation error when trying to load the database.sql file.  It is\n";
	$msg .= "recommended to increase the 'memory_limit' setting in the php.ini config file.\n";
	$msg .= "see: {$faq_url}#faq-trouble-056-q \n";
	DUPX_Log::info($msg);
}

@chmod("{$root_path}/database.sql", 0777);
$sql_file = file_get_contents('database.sql', true);

//ERROR: Reading database.sql file
if ($sql_file === FALSE || strlen($sql_file) < 10)
{
	$msg = "<b>Unable to read the database.sql file from the archive.  Please check these items:</b> <br/>";
	$msg .= "1. Validate permissions and/or group-owner rights on these items: <br/>";
	$msg .= " - File: database.sql <br/> - Directory: [{$root_path}] <br/>";
	$msg .= "<i>see: <a href='{$faq_url}#faq-trouble-055-q' target='_blank'>{$faq_url}#faq-trouble-055-q</a></i> <br/>";
	$msg .= "2. Validate the database.sql file exists and is in the root of the archive.zip file <br/>";
	$msg .= "<i>see: <a href='{$faq_url}#faq-installer-020-q' target='_blank'>{$faq_url}#faq-installer-020-q</a></i> <br/>";
	DUPX_Log::error($msg);
}

//Removes invalid space characters
//Complex Subject See: http://webcollab.sourceforge.net/unicode.html
if ($_POST['dbnbsp'])
{
	DUPX_Log::info("NOTICE: Ran fix non-breaking space characters\n");
	$sql_file = preg_replace('/\xC2\xA0/', ' ', $sql_file);
}

//Write new contents to install-data.sql
$sql_file_copy_status   = file_put_contents($GLOBALS['SQL_FILE_NAME'], $sql_file);
$sql_result_file_data	= explode(";\n", $sql_file);
$sql_result_file_length = count($sql_result_file_data);
$sql_result_file_path	= "{$root_path}/{$GLOBALS['SQL_FILE_NAME']}";
$sql_file = null;

//WARNING: Create installer-data.sql failed
if ($sql_file_copy_status === FALSE || filesize($sql_result_file_path) == 0 || !is_readable($sql_result_file_path))
{
	$sql_file_size = DUPX_U::readableByteSize(filesize('database.sql'));
	$msg  = "\nWARNING: Unable to properly copy database.sql ({$sql_file_size}) to {$GLOBALS['SQL_FILE_NAME']}.  Please check these items:\n";
	$msg .= "- Validate permissions and/or group-owner rights on database.sql and directory [{$root_path}] \n";
	$msg .= "- see: {$faq_url}#faq-trouble-055-q \n";
	DUPX_Log::info($msg);
}

//=================================
//START DB RUN
@mysqli_query($dbh, "SET wait_timeout = {$GLOBALS['DB_MAX_TIME']}");
@mysqli_query($dbh, "SET max_allowed_packet = {$GLOBALS['DB_MAX_PACKETS']}");
DUPX_DB::setCharset($dbh, $_POST['dbcharset'], $_POST['dbcollate']);

//Will set mode to null only for this db handle session
//sql_mode can cause db create issues on some systems
$qry_session_custom = true;
switch ($_POST['dbmysqlmode']) {
    case 'DISABLE':
        @mysqli_query($dbh, "SET SESSION sql_mode = ''");
        break;
    case 'CUSTOM':
		$dbmysqlmode_opts = $_POST['dbmysqlmode_opts'];
		$qry_session_custom = @mysqli_query($dbh, "SET SESSION sql_mode = '{$dbmysqlmode_opts}'");
        if ($qry_session_custom == false)
		{
			$sql_error = mysqli_error($dbh);
			$log  = "WARNING: A custom sql_mode setting issue has been detected:\n{$sql_error}.\n";
			$log .= "For more details visit: http://dev.mysql.com/doc/refman/5.7/en/sql-mode.html\n";
		}
        break;
}

//Set defaults in-case the variable could not be read
$dbvar_maxtime		= DUPX_DB::getVariable($dbh, 'wait_timeout');
$dbvar_maxpacks		= DUPX_DB::getVariable($dbh, 'max_allowed_packet');
$dbvar_sqlmode		= DUPX_DB::getVariable($dbh, 'sql_mode');
$dbvar_maxtime		= is_null($dbvar_maxtime) ? 300 : $dbvar_maxtime;
$dbvar_maxpacks		= is_null($dbvar_maxpacks) ? 1048576 : $dbvar_maxpacks;
$dbvar_sqlmode		= empty($dbvar_sqlmode) ? 'NOT_SET'  : $dbvar_sqlmode;
$dbvar_version		= DUPX_DB::getVersion($dbh);
$sql_file_size1		= DUPX_U::readableByteSize(@filesize("database.sql"));
$sql_file_size2		= DUPX_U::readableByteSize(@filesize("{$GLOBALS['SQL_FILE_NAME']}"));


DUPX_Log::info("--------------------------------------");
DUPX_Log::info("DATABASE ENVIRONMENT");
DUPX_Log::info("--------------------------------------");
DUPX_Log::info("MYSQL VERSION:\tThis Server: {$dbvar_version} -- Build Server: {$GLOBALS['FW_VERSION_DB']}");
DUPX_Log::info("FILE SIZE:\tdatabase.sql ({$sql_file_size1}) - installer-data.sql ({$sql_file_size2})");
DUPX_Log::info("TIMEOUT:\t{$dbvar_maxtime}");
DUPX_Log::info("MAXPACK:\t{$dbvar_maxpacks}");
DUPX_Log::info("SQLMODE:\t{$dbvar_sqlmode}");
DUPX_Log::info("NEW SQL FILE:\t[{$sql_result_file_path}]");

if ($qry_session_custom == false)
{
	DUPX_Log::info("\n{$log}\n");
}

//CREATE DB
switch ($_POST['dbaction']) {
	case "create":
		mysqli_query($dbh, "CREATE DATABASE IF NOT EXISTS `{$_POST['dbname']}`");
		mysqli_select_db($dbh, $_POST['dbname'])
		or DUPX_Log::error(sprintf(ERR_DBCONNECT_CREATE, $_POST['dbname']));
		break;
	case "empty":
		//DROP DB TABLES
		$drop_log = "Database already empty. Ready for install.";
		$sql = "SHOW TABLES FROM `{$_POST['dbname']}`";
		$found_tables = null;
		if ($result = mysqli_query($dbh, $sql)) {
			while ($row = mysqli_fetch_row($result)) {
				$found_tables[] = $row[0];
			}
			if (count($found_tables) > 0) {
				foreach ($found_tables as $table_name) {
					$sql = "DROP TABLE `{$_POST['dbname']}`.`{$table_name}`";
					if (!$result = mysqli_query($dbh, $sql)) {
						DUPX_Log::error(sprintf(ERR_DBTRYCLEAN, $_POST['dbname']));
					}
				}
			}
			$drop_log = count($found_tables);
		}
		break;
}


//WRITE DATA
DUPX_Log::info("--------------------------------------");
DUPX_Log::info("DATABASE RESULTS");
DUPX_Log::info("--------------------------------------");
$profile_start = DUPX_U::getMicrotime();
$fcgi_buffer_pool = 5000;
$fcgi_buffer_count = 0;
$dbquery_rows = 0;
$dbtable_rows = 1;
$dbquery_errs = 0;
$counter = 0;
@mysqli_autocommit($dbh, false);

while ($counter < $sql_result_file_length) {

	$query_strlen = strlen(trim($sql_result_file_data[$counter]));

	if ($dbvar_maxpacks < $query_strlen) {

		DUPX_Log::info("**ERROR** Query size limit [length={$query_strlen}] [sql=" . substr($sql_result_file_data[$counter], 75) . "...]");
		$dbquery_errs++;

	} elseif ($query_strlen > 0) {

		@mysqli_free_result(@mysqli_query($dbh, ($sql_result_file_data[$counter])));
		$err = mysqli_error($dbh);

		//Check to make sure the connection is alive
		if (!empty($err)) {

			if (!mysqli_ping($dbh)) {
				mysqli_close($dbh);
				$dbh = DUPX_DB::connect($_POST['dbhost'], $_POST['dbuser'], $_POST['dbpass'], $_POST['dbname'], $_POST['dbport'] );
				// Reset session setup
				@mysqli_query($dbh, "SET wait_timeout = {$GLOBALS['DB_MAX_TIME']}");
				DUPX_DB::setCharset($dbh, $_POST['dbcharset'], $_POST['dbcollate']);
			}
			DUPX_Log::info("**ERROR** database error write '{$err}' - [sql=" . substr($sql_result_file_data[$counter], 0, 75) . "...]");
			$dbquery_errs++;

		//Buffer data to browser to keep connection open
		} else {
			if ($GLOBALS['DB_FCGI_FLUSH'] && $fcgi_buffer_count++ > $fcgi_buffer_pool) {
				$fcgi_buffer_count = 0;
				DUPX_U::fcgiFlush();
			}
			$dbquery_rows++;
		}
	}
	$counter++;
}
@mysqli_commit($dbh);
@mysqli_autocommit($dbh, true);

DUPX_Log::info("ERRORS FOUND:\t{$dbquery_errs}");
DUPX_Log::info("TABLES DROPPED:\t{$drop_log}");
DUPX_Log::info("QUERIES RAN:\t{$dbquery_rows}\n");

$dbtable_count = 0;
if ($result = mysqli_query($dbh, "SHOW TABLES")) {
	while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
		$table_rows = DUPX_DB::countTableRows($dbh, $row[0]);
		$dbtable_rows += $table_rows;
		DUPX_Log::info("{$row[0]}: ({$table_rows})");
		$dbtable_count++;
	}
	@mysqli_free_result($result);
}

if ($dbtable_count == 0) {
	DUPX_Log::error("No tables where created during step 2 of the install.  Please review the <a href='installer-log.txt' target='install_log'>installer-log.txt</a> file for
		ERROR messages.  You may have to manually run the installer-data.sql with a tool like phpmyadmin to validate the data input.  If you have enabled compatibility mode
		during the package creation process then the database server version your using may not be compatible with this script.\n");
}


//DATA CLEANUP: Perform Transient Cache Cleanup
//Remove all duplicator entries and record this one since this is a new install.
$dbdelete_count = 0;
@mysqli_query($dbh, "DELETE FROM `{$GLOBALS['FW_TABLEPREFIX']}duplicator_packages`");
$dbdelete_count1 = @mysqli_affected_rows($dbh) or 0;
@mysqli_query($dbh, "DELETE FROM `{$GLOBALS['FW_TABLEPREFIX']}options` WHERE `option_name` LIKE ('_transient%') OR `option_name` LIKE ('_site_transient%')");
$dbdelete_count2 = @mysqli_affected_rows($dbh) or 0;
$dbdelete_count = (abs($dbdelete_count1) + abs($dbdelete_count2));
DUPX_Log::info("\nRemoved '{$dbdelete_count}' cache/transient rows");
//Reset Duplicator Options
foreach ($GLOBALS['FW_OPTS_DELETE'] as $value) {
	mysqli_query($dbh, "DELETE FROM `{$GLOBALS['FW_TABLEPREFIX']}options` WHERE `option_name` = '{$value}'");
}

@mysqli_close($dbh);

//FINAL RESULTS
$profile_end	= DUPX_U::getMicrotime();
$ajax2_end		= DUPX_U::getMicrotime();
$ajax1_sum		= DUPX_U::elapsedTime($ajax2_end, $ajax2_start);
DUPX_Log::info("\nCREATE/INSTALL RUNTIME: " . DUPX_U::elapsedTime($profile_end, $profile_start));
DUPX_Log::info('STEP-2 COMPLETE @ ' . @date('h:i:s') . " - RUNTIME: {$ajax1_sum}");

$JSON['pass'] = 1;
$JSON['table_count'] = $dbtable_count;
$JSON['table_rows']  = $dbtable_rows;
$JSON['query_errs']  = $dbquery_errs;
echo json_encode($JSON);
error_reporting($ajax1_error_level);
die('');
?>