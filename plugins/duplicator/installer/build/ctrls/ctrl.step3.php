<?php
// Exit if accessed directly from admin
if (function_exists('duplicator_secure_check')) {
	duplicator_secure_check();
}

/** JSON RESPONSE: Most sites have warnings turned off by default, but if they're turned on the warnings
cause errors in the JSON data Here we hide the status so warning level is reset at it at the end*/
$ajax2_error_level = error_reporting();
error_reporting(E_ERROR);

//====================================================================================================
//DATABASE UPDATES
//====================================================================================================

$ajax2_start = DUPX_U::getMicrotime();

//MYSQL CONNECTION
$dbh = DUPX_DB::connect($_POST['dbhost'], $_POST['dbuser'], html_entity_decode($_POST['dbpass']), $_POST['dbname'], $_POST['dbport']);
$charset_server = @mysqli_character_set_name($dbh);
@mysqli_query($dbh, "SET wait_timeout = {$GLOBALS['DB_MAX_TIME']}");
DUPX_DB::setCharset($dbh, $_POST['dbcharset'], $_POST['dbcollate']);

//POST PARAMS
$_POST['blogname']		= mysqli_real_escape_string($dbh, $_POST['blogname']);
$_POST['postguid']		= isset($_POST['postguid']) && $_POST['postguid'] == 1 ? 1 : 0;
$_POST['fullsearch']	= isset($_POST['fullsearch']) && $_POST['fullsearch'] == 1 ? 1 : 0;
$_POST['urlextended']	= isset($_POST['urlextended']) && $_POST['urlextended'] == 1 ? 1 : 0;
$_POST['path_old']		= isset($_POST['path_old']) ? trim($_POST['path_old']) : null;
$_POST['path_new']		= isset($_POST['path_new']) ? trim($_POST['path_new']) : null;
$_POST['siteurl']		= isset($_POST['siteurl']) ? rtrim(trim($_POST['siteurl']), '/') : null;
$_POST['tables']		= isset($_POST['tables']) && is_array($_POST['tables']) ? array_map('stripcslashes', $_POST['tables']) : array();
$_POST['url_old']		= isset($_POST['url_old']) ? trim($_POST['url_old']) : null;
$_POST['url_new']		= isset($_POST['url_new']) ? rtrim(trim($_POST['url_new']), '/') : null;

//LOGGING
$POST_LOG = $_POST;
unset($POST_LOG['tables']);
unset($POST_LOG['plugins']);
unset($POST_LOG['dbpass']);
ksort($POST_LOG);

$date = @date('h:i:s');
$charset_client = @mysqli_character_set_name($dbh);

$log = <<<LOG
\n\n********************************************************************************
* DUPLICATOR-LITE: INSTALL-LOG
* STEP-3 START @ {$date}
* NOTICE: Do NOT post to public sites or forums
********************************************************************************
CHARSET SERVER:\t{$charset_server}
CHARSET CLIENT:\t{$charset_client}
LOG;
DUPX_Log::info($log);

//Detailed logging
$log  = "--------------------------------------\n";
$log .= "POST DATA\n";
$log .= "--------------------------------------\n";
$log .= print_r($POST_LOG, true);		
$log .= "--------------------------------------\n";
$log .= "SCANNED TABLES\n";
$log .= "--------------------------------------\n";
$log .= (isset($_POST['tables']) && count($_POST['tables'] > 0)) 
		? print_r($_POST['tables'], true) 
		: 'No tables selected to update';
$log .= "--------------------------------------\n";
$log .= "KEEP PLUGINS ACTIVE\n";
$log .= "--------------------------------------\n";
$log .= (isset($_POST['plugins']) && count($_POST['plugins'] > 0)) 
		? print_r($_POST['plugins'], true) 
		: 'No plugins selected for activation';
DUPX_Log::info($log, 2);

//UPDATE SETTINGS
$blog_name   = $_POST['blogname'];
$plugin_list = (isset($_POST['plugins'])) ? $_POST['plugins'] : array();
// Force Duplicator active so we the security cleanup will be available
if (!in_array('duplicator/duplicator.php', $plugin_list)) {
	$plugin_list[] = 'duplicator/duplicator.php';
}
$serial_plugin_list	 = @serialize($plugin_list);

mysqli_query($dbh, "UPDATE `{$GLOBALS['FW_TABLEPREFIX']}options` SET option_value = '{$blog_name}' WHERE option_name = 'blogname' ");
mysqli_query($dbh, "UPDATE `{$GLOBALS['FW_TABLEPREFIX']}options` SET option_value = '{$serial_plugin_list}'  WHERE option_name = 'active_plugins' ");

$log  = "--------------------------------------\n";
$log .= "SERIALIZER ENGINE\n";
$log .= "[*] scan every column\n";
$log .= "[~] scan only text columns\n";
$log .= "[^] no searchable columns\n";
$log .= "--------------------------------------";
DUPX_Log::info($log);

$url_old_json = str_replace('"', "", json_encode($_POST['url_old']));
$url_new_json = str_replace('"', "", json_encode($_POST['url_new']));
$path_old_json = str_replace('"', "", json_encode($_POST['path_old']));
$path_new_json = str_replace('"', "", json_encode($_POST['path_new']));

array_push($GLOBALS['REPLACE_LIST'], 
		array('search' => $_POST['url_old'],			 'replace' => $_POST['url_new']), 
		array('search' => $_POST['path_old'],			 'replace' => $_POST['path_new']), 
		array('search' => $url_old_json,				 'replace' => $url_new_json), 
		array('search' => $path_old_json,				 'replace' => $path_new_json), 	
		array('search' => urlencode($_POST['path_old']), 'replace' => urlencode($_POST['path_new'])), 
		array('search' => urlencode($_POST['url_old']),  'replace' => urlencode($_POST['url_new'])),
		array('search' => rtrim(DUPX_U::unsetSafePath($_POST['path_old']), '\\'), 'replace' => rtrim($_POST['path_new'], '/'))
);

//URL EXTENDED
if ($_POST['urlextended']) {
	
	//RAW '//' 
	$url_old_raw = str_ireplace(array('http://', 'https://'), '//', $_POST['url_old']);
	$url_new_raw = str_ireplace(array('http://', 'https://'), '//', $_POST['url_new']);
	$url_old_raw_json = str_replace('"',  "", json_encode($url_old_raw));
	$url_new_raw_json = str_replace('"',  "", json_encode($url_new_raw));

	//INVERSE: Apply a search for the inverse of the orginal
	if (stristr($_POST['url_old'], 'http:')) {
		//Search for https urls
		$url_old_diff = str_ireplace('http:', 'https:', $_POST['url_old']);
		$url_new_diff = str_ireplace('http:', 'https:', $_POST['url_new']);
		$url_old_diff_json = str_replace('"',  "", json_encode($url_old_diff));
		$url_new_diff_json = str_replace('"',  "", json_encode($url_new_diff));
		
	} else {
		//Search for http urls
		$url_old_diff = str_ireplace('https:', 'http:', $_POST['url_old']);
		$url_new_diff = str_ireplace('https:', 'http:', $_POST['url_new']);
		$url_old_diff_json = str_replace('"',  "", json_encode($url_old_diff));
		$url_new_diff_json = str_replace('"',  "", json_encode($url_new_diff));
	}

	array_push($GLOBALS['REPLACE_LIST'],
			//RAW
			array('search' => $url_old_raw,			 	 	 'replace' => $url_new_raw),
			array('search' => $url_old_raw_json,			 'replace' => $url_new_raw_json),
			array('search' => urlencode($url_old_raw), 		 'replace' => urlencode($url_new_raw)),

			//INVERSE
			array('search' => $url_old_diff,			 	 'replace' => $url_new_diff),
			array('search' => $url_old_diff_json,			 'replace' => $url_new_diff_json),
			array('search' => urlencode($url_old_diff),  	 'replace' => urlencode($url_new_diff))
	);
}


//Remove trailing slashes
function _dupx_array_rtrim(&$value) {
    $value = rtrim($value, '\/');
}
array_walk_recursive($GLOBALS['REPLACE_LIST'], _dupx_array_rtrim);

@mysqli_autocommit($dbh, false);
$report = DUPX_UpdateEngine::load($dbh, $GLOBALS['REPLACE_LIST'], $_POST['tables'], $_POST['fullsearch']);
@mysqli_commit($dbh);
@mysqli_autocommit($dbh, true);


//BUILD JSON RESPONSE
$JSON = array();
$JSON['step2'] = json_decode(urldecode($_POST['json']));
$JSON['step3'] = $report;
$JSON['step3']['warn_all'] = 0;
$JSON['step3']['warnlist'] = array();

DUPX_UpdateEngine::logStats($report);
DUPX_UpdateEngine::logErrors($report);

//Reset the postguid data
if ($_POST['postguid']) {
	mysqli_query($dbh, "UPDATE `{$GLOBALS['FW_TABLEPREFIX']}posts` SET guid = REPLACE(guid, '{$_POST['url_new']}', '{$_POST['url_old']}')");
	$update_guid = @mysqli_affected_rows($dbh) or 0;
	DUPX_Log::info("Reverted '{$update_guid}' post guid columns back to '{$_POST['url_old']}'");
}

/** FINAL UPDATES: Must happen after the global replace to prevent double pathing
  http://xyz.com/abc01 will become http://xyz.com/abc0101  with trailing data */
mysqli_query($dbh, "UPDATE `{$GLOBALS['FW_TABLEPREFIX']}options` SET option_value = '{$_POST['url_new']}'  WHERE option_name = 'home' ");
mysqli_query($dbh, "UPDATE `{$GLOBALS['FW_TABLEPREFIX']}options` SET option_value = '{$_POST['siteurl']}'  WHERE option_name = 'siteurl' ");

//===============================================
//CONFIGURATION FILE UPDATES
//===============================================
DUPX_Log::info("\n====================================");
DUPX_Log::info('CONFIGURATION FILE UPDATES:');
DUPX_Log::info("====================================\n");
DUPX_WPConfig::updateStandard();
$config_file = DUPX_WPConfig::updateExtended();
DUPX_Log::info("UPDATED WP-CONFIG: {$root_path}/wp-config.php' (if present)");
DUPX_ServerConfig::setup($dbh);



//===============================================
//GENERAL UPDATES & CLEANUP
//===============================================
DUPX_Log::info("\n====================================");
DUPX_Log::info('GENERAL UPDATES & CLEANUP:');
DUPX_Log::info("====================================\n");

/** CREATE NEW USER LOGIC */
if (strlen($_POST['wp_username']) >= 4 && strlen($_POST['wp_password']) >= 6) {
	
	$newuser_check = mysqli_query($dbh, "SELECT COUNT(*) AS count FROM `{$GLOBALS['FW_TABLEPREFIX']}users` WHERE user_login = '{$_POST['wp_username']}' ");
	$newuser_row   = mysqli_fetch_row($newuser_check);
    $newuser_count = is_null($newuser_row) ? 0 : $newuser_row[0];
	
	if ($newuser_count == 0) {
	
		$newuser_datetime =	@date("Y-m-d H:i:s");
		$newuser_security = mysqli_real_escape_string($dbh, 'a:1:{s:13:"administrator";s:1:"1";}');

		$newuser_test1 = @mysqli_query($dbh, "INSERT INTO `{$GLOBALS['FW_TABLEPREFIX']}users` 
			(`user_login`, `user_pass`, `user_nicename`, `user_email`, `user_registered`, `user_activation_key`, `user_status`, `display_name`) 
			VALUES ('{$_POST['wp_username']}', MD5('{$_POST['wp_password']}'), '{$_POST['wp_username']}', '', '{$newuser_datetime}', '', '0', '{$_POST['wp_username']}')");

		$newuser_insert_id = mysqli_insert_id($dbh);

		$newuser_test2 = @mysqli_query($dbh, "INSERT INTO `{$GLOBALS['FW_TABLEPREFIX']}usermeta` 
				(`user_id`, `meta_key`, `meta_value`) VALUES ('{$newuser_insert_id}', '{$GLOBALS['FW_TABLEPREFIX']}capabilities', '{$newuser_security}')");

		$newuser_test3 = @mysqli_query($dbh, "INSERT INTO `{$GLOBALS['FW_TABLEPREFIX']}usermeta` 
				(`user_id`, `meta_key`, `meta_value`) VALUES ('{$newuser_insert_id}', '{$GLOBALS['FW_TABLEPREFIX']}user_level', '10')");
				
		//Misc Meta-Data Settings:
		@mysqli_query($dbh, "INSERT INTO `{$GLOBALS['FW_TABLEPREFIX']}usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES ('{$newuser_insert_id}', 'rich_editing', 'true')");
		@mysqli_query($dbh, "INSERT INTO `{$GLOBALS['FW_TABLEPREFIX']}usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES ('{$newuser_insert_id}', 'admin_color',  'fresh')");
		@mysqli_query($dbh, "INSERT INTO `{$GLOBALS['FW_TABLEPREFIX']}usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES ('{$newuser_insert_id}', 'nickname', '{$_POST['wp_username']}')");

		if ($newuser_test1 && $newuser_test2 && $newuser_test3) {
			DUPX_Log::info("NEW WP-ADMIN USER: New username '{$_POST['wp_username']}' was created successfully \n ");
		} else {
			$newuser_warnmsg = "NEW WP-ADMIN USER: Failed to create the user '{$_POST['wp_username']}' \n ";
			$JSON['step3']['warnlist'][] = $newuser_warnmsg;
			DUPX_Log::info($newuser_warnmsg);
		}			
	} 
	else {
		$newuser_warnmsg = "NEW WP-ADMIN USER: Username '{$_POST['wp_username']}' already exists in the database.  Unable to create new account \n";
		$JSON['step3']['warnlist'][] = $newuser_warnmsg;
		DUPX_Log::info($newuser_warnmsg);
	}
}

/** ==============================
 * MU Updates*/
$mu_newDomain = parse_url($_POST['url_new']);
$mu_oldDomain = parse_url($_POST['url_old']);
$mu_newDomainHost = $mu_newDomain['host'];
$mu_oldDomainHost = $mu_oldDomain['host'];
$mu_newUrlPath = parse_url($_POST['url_new'], PHP_URL_PATH);
$mu_oldUrlPath = parse_url($_POST['url_old'], PHP_URL_PATH);

//Force a path for PATH_CURRENT_SITE
$mu_newUrlPath = (empty($mu_newUrlPath) || ($mu_newUrlPath == '/')) ? '/'  : rtrim($mu_newUrlPath, '/') . '/';
$mu_oldUrlPath = (empty($mu_oldUrlPath) || ($mu_oldUrlPath == '/')) ? '/'  : rtrim($mu_oldUrlPath, '/') . '/';

$mu_updates = @mysqli_query($dbh, "UPDATE `{$GLOBALS['FW_TABLEPREFIX']}blogs` SET domain = '{$mu_newDomainHost}' WHERE domain = '{$mu_oldDomainHost}'");
if ($mu_updates) {
	DUPX_Log::info("Update MU table blogs: domain {$mu_newDomainHost} ");
	DUPX_Log::info("UPDATE `{$GLOBALS['FW_TABLEPREFIX']}blogs` SET domain = '{$mu_newDomainHost}' WHERE domain = '{$mu_oldDomainHost}'");
} 


//Create snapshots directory in order to
//compensate for permissions on some servers
if (!file_exists(DUPLICATOR_SSDIR_NAME)) {
	mkdir(DUPLICATOR_SSDIR_NAME, 0755);
	DUPX_Log::info("- Created directory ". DUPLICATOR_SSDIR_NAME);
}
$fp = fopen(DUPLICATOR_SSDIR_NAME . '/index.php', 'w');
fclose($fp);
DUPX_Log::info("- Created file ". DUPLICATOR_SSDIR_NAME . '/index.php');



//===============================================
//NOTICES TESTS
//===============================================
DUPX_Log::info("\n====================================");
DUPX_Log::info("NOTICES");
DUPX_Log::info("====================================\n");
$config_vars = array('WP_CONTENT_DIR', 'WP_CONTENT_URL', 'WPCACHEHOME', 'COOKIE_DOMAIN', 'WP_SITEURL', 'WP_HOME', 'WP_TEMP_DIR');
$config_found = DUPX_U::getListValues($config_vars, $config_file);

//Config File:
if (! empty($config_found)) {
	$msg  = "NOTICE: The wp-config.php has the following values set [" . implode(", ", $config_found) . "]. \n";
	$msg .= 'Please validate these values are correct in your wp-config.php file.  See the codex link for more details: https://codex.wordpress.org/Editing_wp-config.php';
	$JSON['step3']['warnlist'][] = $msg;
	DUPX_Log::info($msg);
}

//Database: 
$result = @mysqli_query($dbh, "SELECT option_value FROM `{$GLOBALS['FW_TABLEPREFIX']}options` WHERE option_name IN ('upload_url_path','upload_path')");
if ($result) {
	while ($row = mysqli_fetch_row($result)) {
		if (strlen($row[0])) {
			$msg  = "NOTICE: The media settings values in the table '{$GLOBALS['FW_TABLEPREFIX']}options' has at least one the following values ['upload_url_path','upload_path'] set. \n";
			$msg .= "Please validate these settings by logging into your wp-admin and going to Settings->Media area and validating the 'Uploading Files' section";
			$JSON['step3']['warnlist'][] = $msg;
			DUPX_Log::info($msg);
			break;
		}
	}
}

if (empty($JSON['step3']['warnlist'])) {
	DUPX_Log::info("No Notices Found\n");
}

$JSON['step3']['warn_all'] = empty($JSON['step3']['warnlist']) ? 0 : count($JSON['step3']['warnlist']);

mysqli_close($dbh);



$ajax2_end = DUPX_U::getMicrotime();
$ajax2_sum = DUPX_U::elapsedTime($ajax2_end, $ajax2_start);
DUPX_Log::info("\nSTEP 3 COMPLETE @ " . @date('h:i:s') . " - RUNTIME: {$ajax2_sum}\n\n");

$JSON['step3']['pass'] = 1;
error_reporting($ajax2_error_level);
die(json_encode($JSON));
?>