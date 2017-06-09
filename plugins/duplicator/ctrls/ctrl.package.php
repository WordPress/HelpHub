<?php

/**
 *  DUPLICATOR_PACKAGE_SCAN
 *  Returns a json scan report object which contains data about the system
 *  
 *  @return json   json report object
 *  @example	   to test: /wp-admin/admin-ajax.php?action=duplicator_package_scan
 */
function duplicator_package_scan() {
	
	header('Content-Type: application/json;');
	DUP_Util::hasCapability('export');
	
	@set_time_limit(0);
	$errLevel = error_reporting();
	error_reporting(E_ERROR);
	DUP_Util::initSnapshotDirectory();
	
	$Package = DUP_Package::getActive();
	$report = $Package->runScanner();
	
	$Package->saveActiveItem('ScanFile', $Package->ScanFile);
	$json_response = json_encode($report);
	
	DUP_Package::tempFileCleanup();
	error_reporting($errLevel);
    die($json_response);
}

/**
 *  duplicator_package_build
 *  Returns the package result status
 *  
 *  @return json   json object of package results
 */
function duplicator_package_build() {
	
	DUP_Util::hasCapability('export');
	
	check_ajax_referer( 'dup_package_build', 'nonce');
	
	header('Content-Type: application/json');
	
	@set_time_limit(0);
	$errLevel = error_reporting();
	error_reporting(E_ERROR);
	DUP_Util::initSnapshotDirectory();

	$Package = DUP_Package::getActive();
	
	if (!is_readable(DUPLICATOR_SSDIR_PATH_TMP . "/{$Package->ScanFile}")) {
		die("The scan result file was not found.  Please run the scan step before building the package.");
	}
	
	$Package->runBuild();
	
	//JSON:Debug Response
	//Pass = 1, Warn = 2, Fail = 3
	$json = array();
	$json['Status']   = 1;
	$json['Package']  = $Package;
	$json['Runtime']  = $Package->Runtime;
	$json['ExeSize']  = $Package->ExeSize;
	$json['ZipSize']  = $Package->ZipSize;
	$json_response = json_encode($json);
	
	error_reporting($errLevel);
    die($json_response);
}

/**
 *  DUPLICATOR_PACKAGE_DELETE
 *  Deletes the files and database record entries
 *
 *  @return json   A json message about the action.  
 *				   Use console.log to debug from client
 */
function duplicator_package_delete() {
	
    DUP_Util::hasCapability('export');    
    check_ajax_referer( 'package_list', 'nonce' );
    
    try {
		global $wpdb;
		$json		= array();
		$post		= stripslashes_deep($_POST);
		$tblName	= $wpdb->prefix . 'duplicator_packages';
		$postIDs	= isset($post['duplicator_delid']) ? $post['duplicator_delid'] : null;
		$list		= explode(",", $postIDs);
		$delCount	= 0;
		
        if ($postIDs != null) {
            
            foreach ($list as $id) {
				
				$getResult = $wpdb->get_results($wpdb->prepare("SELECT name, hash FROM `{$tblName}` WHERE id = %d", $id), ARRAY_A);
				
				if ($getResult) {
					$row		=  $getResult[0];
					$nameHash	= "{$row['name']}_{$row['hash']}";
					$delResult	= $wpdb->query($wpdb->prepare( "DELETE FROM `{$tblName}` WHERE id = %d", $id ));
					if ($delResult != 0) {
						//Perms
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP . "/{$nameHash}_archive.zip"), 0644);
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP . "/{$nameHash}_database.sql"), 0644);
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP . "/{$nameHash}_installer.php"), 0644);						
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_archive.zip"), 0644);
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_database.sql"), 0644);
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_installer.php"), 0644);
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_scan.json"), 0644);
						@chmod(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}.log"), 0644);
						//Remove
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP . "/{$nameHash}_archive.zip"));
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP . "/{$nameHash}_database.sql"));
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP . "/{$nameHash}_installer.php"));
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_archive.zip"));
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_database.sql"));
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_installer.php"));
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}_scan.json"));
						@unlink(DUP_Util::safePath(DUPLICATOR_SSDIR_PATH . "/{$nameHash}.log"));
						//Unfinished Zip files
						$tmpZip = DUPLICATOR_SSDIR_PATH_TMP . "/{$nameHash}_archive.zip.*";
						if ($tmpZip !== false) {
							array_map('unlink', glob($tmpZip));
						}
						$delCount++;
					} 
				}
            }
        }

    } catch (Exception $e) {
		$json['error'] = "{$e}";
        die(json_encode($json));
    }
	
	$json['ids'] = "{$postIDs}";
	$json['removed'] = $delCount;
    die(json_encode($json));
}

//DO NOT ADD A CARRIAGE RETURN BEYOND THIS POINT (headers issue)!!
?>