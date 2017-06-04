<?php

//POST PARAMS
$_POST['archive_name']		 = isset($_POST['archive_name']) ? $_POST['archive_name'] : null;
$_POST['archive_engine']	 = isset($_POST['archive_engine']) ? $_POST['archive_engine']  : 'manual';
$_POST['archive_filetime']	 = (isset($_POST['archive_filetime'])) ? $_POST['archive_filetime'] : 'current';

//LOGGING
$POST_LOG = $_POST;
unset($POST_LOG['dbpass']);
ksort($POST_LOG);

//PAGE VARS
$php_max_time   = @ini_get("max_execution_time");
$php_max_time   = ($php_max_time == 0) ? "[0] time limit restriction disabled" : "[{$php_max_time}] time limit restriction enabled";
$root_path		 = DUPX_U::setSafePath($GLOBALS['CURRENT_ROOT_PATH']);
$package_path	 = "{$root_path}/{$_POST['archive_name']}";
$ajax1_start	 = DUPX_U::getMicrotime();
$zip_support	 = class_exists('ZipArchive') ? 'Enabled' : 'Not Enabled';
$JSON			 = array();
$JSON['pass']	 = 0;

/** JSON RESPONSE: Most sites have warnings turned off by default, but if they're turned on the warnings
  cause errors in the JSON data Here we hide the status so warning level is reset at it at the end */
if (!headers_sent())  {
	header('Content-Type: application/json');
}
$ajax1_error_level = error_reporting();
error_reporting(E_ERROR);

//===============================
//ERROR MESSAGES
//===============================
($GLOBALS['LOG_FILE_HANDLE'] != false) or DUPX_Log::error(ERR_MAKELOG);

if (!$GLOBALS['FW_ARCHIVE_ONLYDB']) {
	//ERR_ZIPMANUAL
	if ($_POST['archive_engine'] == 'manual') {
		if (!file_exists("wp-config.php") && !file_exists("database.sql")) {
			DUPX_Log::error(ERR_ZIPMANUAL);
		}
	} else {
		//ERR_CONFIG_FOUND
		(!file_exists('wp-config.php'))
			or DUPX_Log::error(ERR_CONFIG_FOUND);
		//ERR_ZIPNOTFOUND
		(is_readable("{$package_path}"))
			or DUPX_Log::error(ERR_ZIPNOTFOUND);
	}
}



DUPX_Log::info("********************************************************************************");
DUPX_Log::info('* DUPLICATOR-LITE: INSTALL-LOG');
DUPX_Log::info("* VERSION: {$GLOBALS['FW_DUPLICATOR_VERSION']}");
DUPX_Log::info('* STEP-1 START @ '.@date('h:i:s'));
DUPX_Log::info('* NOTICE: Do NOT post this data to public sites or forums');
DUPX_Log::info("********************************************************************************");
DUPX_Log::info("PHP VERSION:\t".phpversion().' | SAPI: '.php_sapi_name());
DUPX_Log::info("PHP TIME LIMIT:\t{$php_max_time}");
DUPX_Log::info("PHP MEMORY:\t".$GLOBALS['PHP_MEMORY_LIMIT'].' | SUHOSIN: '.$GLOBALS['PHP_SUHOSIN_ON']);
DUPX_Log::info("SERVER:\t\t{$_SERVER['SERVER_SOFTWARE']}");
DUPX_Log::info("DOC ROOT:\t{$root_path}");
DUPX_Log::info("DOC ROOT 755:\t".var_export($GLOBALS['CHOWN_ROOT_PATH'], true));
DUPX_Log::info("LOG FILE 644:\t".var_export($GLOBALS['CHOWN_LOG_PATH'], true));
DUPX_Log::info("REQUEST URL:\t{$GLOBALS['URL_PATH']}");

$log = "--------------------------------------\n";
$log .= "POST DATA\n";
$log .= "--------------------------------------\n";
$log .= print_r($POST_LOG, true);
DUPX_Log::info($log, 2);

$log = "--------------------------------------\n";
$log .= "ARCHIVE EXTRACTION\n";
$log .= "--------------------------------------\n";
$log .= "NAME:\t{$_POST['archive_name']}\n";
$log .= "SIZE:\t".DUPX_U::readableByteSize(@filesize($_POST['archive_name']))."\n";
$log .= "ZIP:\t{$zip_support} (ZipArchive Support)";
DUPX_Log::info($log);


if ($_POST['archive_engine'] == 'manual') {
	DUPX_Log::info("\n** PACKAGE EXTRACTION IS IN MANUAL MODE ** \n");
} else {
	if ($GLOBALS['FW_PACKAGE_NAME'] != $_POST['archive_name']) {
		$log = "\n--------------------------------------\n";
		$log .= "WARNING: This package set may be incompatible!  \nBelow is a summary of the package this installer was built with and the package used. \n";
		$log .= "To guarantee accuracy the installer and archive should match. For details see the online FAQs.";
		$log .= "\nCREATED WITH:\t{$GLOBALS['FW_PACKAGE_NAME']} \nPROCESSED WITH:\t{$_POST['archive_name']}  \n";
		$log .= "--------------------------------------\n";
		DUPX_Log::info($log);
	}

	if (!class_exists('ZipArchive')) {
		DUPX_Log::info("ERROR: Stopping install process.  Trying to extract without ZipArchive module installed.  Please use the 'Manual Package extraction' mode to extract zip file.");
		DUPX_Log::error(ERR_ZIPARCHIVE);
	}

	$target	 = $root_path;
	$zip	 = new ZipArchive();
	if ($zip->open($_POST['archive_name']) === TRUE) {

		DUPX_Log::info("\n>>> START EXTRACTION:");
		if (!$zip->extractTo($target)) {
			DUPX_Log::error(ERR_ZIPEXTRACTION);
		}
		$log = print_r($zip, true);

		//Keep original timestamp on the file
		if ($_POST['archive_filetime'] == 'original') {
			$log .= "File timestamp is 'Original' mode.\n";
			for ($idx = 0; $s = $zip->statIndex($idx); $idx++) {
				touch($target.DIRECTORY_SEPARATOR.$s['name'], $s['mtime']);
			}
		} else {
			$now = date("Y-m-d H:i:s");
			$log .= "File timestamp is 'Current' mode: {$now}\n";
		}

		$close_response = $zip->close();
		$log .= "<<< EXTRACTION COMPLETE: " . var_export($close_response, true);
		DUPX_Log::info($log);
	} else {
		DUPX_Log::error(ERR_ZIPOPEN);
	}
}

//CONFIG FILE RESETS
DUPX_ServerConfig::reset();

//FINAL RESULTS
$ajax1_end	 = DUPX_U::getMicrotime();
$ajax1_sum	 = DUPX_U::elapsedTime($ajax1_end, $ajax1_start);
DUPX_Log::info("\nSTEP-1 COMPLETE @ " . @date('h:i:s') . " - RUNTIME: {$ajax1_sum}");


$JSON['pass'] = 1;
echo json_encode($JSON);
error_reporting($ajax1_error_level);
die('');
?>