<?php
/*
  Copyright 2011-17  snapcreek.com

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 3, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
  GPL v3 https://www.gnu.org/licenses/gpl-3.0.en.html
 */

if (file_exists('dtoken.php')) {
    //This is most likely inside the snapshot folder.
    
    //DOWNLOAD ONLY: (Only enable download from within the snapshot directory)
    if (isset($_GET['get']) && isset($_GET['file'])) {
        //Clean the input, strip out anything not alpha-numeric or "_.", so restricts
        //only downloading files in same folder, and removes risk of allowing directory
        //separators in other charsets (vulnerability in older IIS servers), also
        //strips out anything that might cause it to use an alternate stream since
        //that would require :// near the front.
    	$filename = preg_replace('/[^a-zA-Z0-9_.]*/','',$_GET['file']);
    	if (strlen($filename) && file_exists($filename) && (strstr($filename, '_installer.php'))) {
            //Attempt to push the file to the browser
    	    header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=installer.php');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filename));
            @ob_clean();
            @flush();
            if (@readfile($filename) == false) {
                $data = file_get_contents($filename);
                if ($data == false) {
                    die("Unable to read installer file.  The server currently has readfile and file_get_contents disabled on this server.  Please contact your server admin to remove this restriction");
                } else {
                    print $data;
                }
            }
        } else {
            header("HTTP/1.1 404 Not Found", true, 404);
            header("Status: 404 Not Found");
        }
    }
	//Prevent Access from rovers or direct browsing in snapshop directory, or when
    //requesting to download a file, should not go past this point.
    exit;
}

/* ==============================================================================================
ADVANCED FEATURES - Allows admins to perform aditional logic on the import.

$GLOBALS['REPLACE_LIST']
	Add additional search and replace items to step 2 for the serialize engine.  
	Place directly below $GLOBALS['REPLACE_LIST'] variable below your items
	EXAMPLE:
		array_push($GLOBALS['REPLACE_LIST'], array('search' => 'https://oldurl/',  'replace' => 'https://newurl/'));
		array_push($GLOBALS['REPLACE_LIST'], array('search' => 'ftps://oldurl/',   'replace' => 'ftps://newurl/'));
  ================================================================================================= */

// Some machines don’t have this set so just do it here.
date_default_timezone_set('UTC'); 

//COMPARE VALUES
$GLOBALS['DUPX_DEBUG']		= false;
$GLOBALS['FW_CREATED']		= '%fwrite_created%';
$GLOBALS['FW_VERSION_DUP']	= '%fwrite_version_dup%';
$GLOBALS['FW_VERSION_WP']	= '%fwrite_version_wp%';
$GLOBALS['FW_VERSION_DB']	= '%fwrite_version_db%';
$GLOBALS['FW_VERSION_PHP']	= '%fwrite_version_php%';
$GLOBALS['FW_VERSION_OS']	= '%fwrite_version_os%';
//GENERAL
$GLOBALS['FW_TABLEPREFIX']		= '%fwrite_wp_tableprefix%';
$GLOBALS['FW_URL_OLD']			= '%fwrite_url_old%';
$GLOBALS['FW_URL_NEW']			= '%fwrite_url_new%';
$GLOBALS['FW_PACKAGE_NAME']		= '%fwrite_archive_name%';
$GLOBALS['FW_PACKAGE_NOTES']	= '%fwrite_package_notes%';
$GLOBALS['FW_SECURE_NAME']		= '%fwrite_secure_name%';
$GLOBALS['FW_DBHOST']			= '%fwrite_dbhost%';
$GLOBALS['FW_DBHOST']			= empty($GLOBALS['FW_DBHOST']) ? 'localhost' : $GLOBALS['FW_DBHOST'];
$GLOBALS['FW_DBPORT']			= '%fwrite_dbport%';
$GLOBALS['FW_DBPORT']			= empty($GLOBALS['FW_DBPORT']) ? 3306 : $GLOBALS['FW_DBPORT'];
$GLOBALS['FW_DBNAME']			= '%fwrite_dbname%';
$GLOBALS['FW_DBUSER']			= '%fwrite_dbuser%';
$GLOBALS['FW_DBPASS']			= '%fwrite_dbpass%';
$GLOBALS['FW_SSL_ADMIN']		= '%fwrite_ssl_admin%';
$GLOBALS['FW_SSL_LOGIN']		= '%fwrite_ssl_login%';
$GLOBALS['FW_CACHE_WP']			= '%fwrite_cache_wp%';
$GLOBALS['FW_CACHE_PATH']		= '%fwrite_cache_path%';
$GLOBALS['FW_BLOGNAME']			= '%fwrite_blogname%';
$GLOBALS['FW_WPROOT']			= '%fwrite_wproot%';
$GLOBALS['FW_WPLOGIN_URL']		= '%fwrite_wplogin_url%';
$GLOBALS['FW_OPTS_DELETE']		= json_decode("%fwrite_opts_delete%", true);
$GLOBALS['FW_DUPLICATOR_VERSION'] = '%fwrite_duplicator_version%';
$GLOBALS['FW_ARCHIVE_ONLYDB']	= '%fwrite_archive_onlydb%';

//DATABASE SETUP: all time in seconds	
$GLOBALS['DB_MAX_TIME']		= 5000;
$GLOBALS['DB_MAX_PACKETS']	= 268435456;
$GLOBALS['DB_FCGI_FLUSH']	= false;
ini_set('mysql.connect_timeout', '5000');

//PHP SETUP: all time in seconds
ini_set('memory_limit', '2048M');
ini_set("max_execution_time", '5000');
ini_set("max_input_time", '5000');
ini_set('default_socket_timeout', '5000');
@set_time_limit(0);

$GLOBALS['DBCHARSET_DEFAULT'] = 'utf8';
$GLOBALS['DBCOLLATE_DEFAULT'] = 'utf8_general_ci';
$GLOBALS['FAQ_URL'] = 'https://snapcreek.com/duplicator/docs/faqs-tech';
$GLOBALS['NOW_DATE'] = @date("Y-m-d-H:i:s");
$GLOBALS['DB_RENAME_PREFIX'] = 'x-bak__';

//UPDATE TABLE SETTINGS
$GLOBALS['REPLACE_LIST'] = array();


/** ================================================================================================
  END ADVANCED FEATURES: Do not edit below here.
  =================================================================================================== */

//CONSTANTS
define("DUPLICATOR_INIT", 1); 
define("DUPLICATOR_SSDIR_NAME", 'wp-snapshots');  //This should match DUPLICATOR_SSDIR_NAME in duplicator.php

//SHARED POST PARMS
$_POST['action_step'] = isset($_POST['action_step']) ? $_POST['action_step'] : "1";

/** Host has several combinations :
localhost | localhost:55 | localhost: | http://localhost | http://localhost:55 */
$_POST['dbhost']	= isset($_POST['dbhost']) ? trim($_POST['dbhost']) : null;
$_POST['dbport']    = isset($_POST['dbport']) ? trim($_POST['dbport']) : 3306;
$_POST['dbuser']	= isset($_POST['dbuser']) ? trim($_POST['dbuser']) : null;
$_POST['dbpass']	= isset($_POST['dbpass']) ? trim($_POST['dbpass']) : null;
$_POST['dbname']	= isset($_POST['dbname']) ? trim($_POST['dbname']) : null;
$_POST['dbcharset'] = isset($_POST['dbcharset'])  ? trim($_POST['dbcharset']) : $GLOBALS['DBCHARSET_DEFAULT'];
$_POST['dbcollate'] = isset($_POST['dbcollate'])  ? trim($_POST['dbcollate']) : $GLOBALS['DBCOLLATE_DEFAULT'];

//GLOBALS
$GLOBALS['SQL_FILE_NAME']       = "installer-data.sql";
$GLOBALS['LOG_FILE_NAME']       = "installer-log.txt";
$GLOBALS['LOGGING']             = isset($_POST['logging']) ? $_POST['logging'] : 1;
$GLOBALS['CURRENT_ROOT_PATH']   = dirname(__FILE__);
$GLOBALS['CHOWN_ROOT_PATH']     = @chmod("{$GLOBALS['CURRENT_ROOT_PATH']}", 0755);
$GLOBALS['CHOWN_LOG_PATH']      = @chmod("{$GLOBALS['CURRENT_ROOT_PATH']}/{$GLOBALS['LOG_FILE_NAME']}", 0644);
$GLOBALS['URL_SSL']             = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == 'on') ? true : false;
$GLOBALS['URL_PATH']            = ($GLOBALS['URL_SSL']) ? "https://{$_SERVER['SERVER_NAME']}{$_SERVER['REQUEST_URI']}" : "http://{$_SERVER['SERVER_NAME']}{$_SERVER['REQUEST_URI']}";
$GLOBALS['PHP_MEMORY_LIMIT']    = ini_get('memory_limit') === false ? 'n/a' : ini_get('memory_limit');
$GLOBALS['PHP_SUHOSIN_ON']      = extension_loaded('suhosin') ? 'enabled' : 'disabled';
$GLOBALS['ARCHIVE_PATH']        = $GLOBALS['CURRENT_ROOT_PATH'] . '/' . $GLOBALS['FW_PACKAGE_NAME'];
$GLOBALS['ARCHIVE_PATH']        = str_replace("\\", "/", $GLOBALS['ARCHIVE_PATH']);

//Restart log if user starts from step 1
if ($_POST['action_step'] == 1 && ! isset($_GET['help'])) {
    $GLOBALS['LOG_FILE_HANDLE'] = @fopen($GLOBALS['LOG_FILE_NAME'], "w+");
} else {
    $GLOBALS['LOG_FILE_HANDLE'] = @fopen($GLOBALS['LOG_FILE_NAME'], "a+");
}
?>
@@CLASS.U.PHP@@
@@CLASS.SERVER.PHP@@
@@CLASS.DB.PHP@@
@@CLASS.LOGGING.PHP@@
@@CLASS.ENGINE.PHP@@
@@CLASS.CONF.WP.PHP@@
@@CLASS.CONF.SRV.PHP@@
<?php
if (isset($_POST['action_ajax'])) :

	//Alternative control switch structer will not work in this case
	//see: http://php.net/manual/en/control-structures.alternative-syntax.php
	//Some clients will create double spaces such as the FTP client which
	//will break example found online
	switch ($_POST['action_ajax']) :

		case "1": ?>@@CTRL.STEP1.PHP@@<?php break;

		case "2": ?>@@CTRL.STEP2.PHP@@<?php break;

		case "3": ?>@@CTRL.STEP3.PHP@@<?php break;

	endswitch;

    @fclose($GLOBALS["LOG_FILE_HANDLE"]);
    die("");

endif;
?>
	
	
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="robots" content="noindex,nofollow">
	<title>Duplicator</title>
	@@INC.LIBS.CSS.PHP@@	
	@@INC.CSS.PHP@@	
	@@INC.LIBS.JS.PHP@@
	@@INC.JS.PHP@@
</head>
<body>

<div id="content">
<!-- =========================================
HEADER TEMPLATE: Common header on all steps -->
<table cellspacing="0" class="dupx-header">
    <tr>
        <td style="width:100%;">
            <div style="font-size:26px; padding:7px 0 7px 0">
                <!-- !!DO NOT CHANGE/EDIT OR REMOVE PRODUCT NAME!!
                If your interested in Private Label Rights please contact us at the URL below to discuss
                customizations to product labeling: http://snapcreek.com	-->
                &nbsp; Duplicator
            </div>
        </td>
        <td class="dupx-header-version">
            version: <?php echo $GLOBALS['FW_DUPLICATOR_VERSION'] ?><br/>
			&raquo; <a href="javascript:void(0)" onclick="DUPX.showServerInfo()">info</a>
			&raquo; <a href="?help=1" target="_blank">help</a>
        </td>
    </tr>
</table>

<?php if ($GLOBALS['FW_ARCHIVE_ONLYDB']) :?>
	<div style="position: relative">
		<div class="archive-onlydb">Database Only Mode</div>
	</div>
<?php endif; ?>

<!-- =========================================
FORM DATA: Data Steps -->
<div id="content-inner">
<?php

if (! isset($_GET['help'])) {
switch ($_POST['action_step']) {
	case "1" :
	?> @@VIEW.STEP1.PHP@@ <?php
	break;
	case "2" :
	?> @@VIEW.STEP2.PHP@@ <?php
	break;
	case "3" :
	?> @@VIEW.STEP3.PHP@@ <?php
	break;
	case "4" :
	?> @@VIEW.STEP4.PHP@@ <?php
	break;
}
} else {
	?> @@VIEW.HELP.PHP@@ <?php
}
	
?>
</div>
</div><br/>


<!-- CONFIRM DIALOG -->
<div id="dialog-server-info" style="display:none">
	<!-- DETAILS -->
	<div class="dlg-serv-info">
		<?php
			$ini_path 		= php_ini_loaded_file();
			$ini_max_time 	= ini_get('max_execution_time');
			$ini_memory 	= ini_get('memory_limit');
		?>
         <div class="hdr">Current Server</div>
		<label>Web Server:</label>  			<?php echo $_SERVER['SERVER_SOFTWARE']; ?><br/>
		<label>Operating System:</label>        <?php echo PHP_OS ?><br/>
        <label>PHP Version:</label>  			<?php echo DUPX_Server::$php_version; ?><br/>
		<label>PHP INI Path:</label> 			<?php echo empty($ini_path ) ? 'Unable to detect loaded php.ini file' : $ini_path; ?>	<br/>
		<label>PHP SAPI:</label>  				<?php echo php_sapi_name(); ?><br/>
		<label>PHP ZIP Archive:</label> 		<?php echo class_exists('ZipArchive') ? 'Is Installed' : 'Not Installed'; ?> <br/>
		<label>PHP max_execution_time:</label>  <?php echo $ini_max_time === false ? 'unable to find' : $ini_max_time; ?><br/>
		<label>PHP memory_limit:</label>  		<?php echo empty($ini_memory)      ? 'unable to find' : $ini_memory; ?><br/>

        <br/>
        <div class="hdr">Package Server</div>
		<div class="info-txt">The server where the package was created</div>
        <label>Plugin Version:</label>  		<?php echo $GLOBALS['FW_VERSION_DUP'] ?><br/>
        <label>WordPress Version:</label>  		<?php echo $GLOBALS['FW_VERSION_WP'] ?><br/>
        <label>PHP Version:</label>             <?php echo $GLOBALS['FW_VERSION_PHP'] ?><br/>
        <label>Database Version:</label>        <?php echo $GLOBALS['FW_VERSION_DB'] ?><br/>
        <label>Operating System:</label>        <?php echo $GLOBALS['FW_VERSION_OS'] ?><br/>
		<br/><br/>
	</div>
</div>

<script>
	/* Server Info Dialog*/
	DUPX.showServerInfo = function()
	{
		modal({
			type: 'alert',
			title: 'Server Information',
			text: $('#dialog-server-info').html()
		});
	}
</script>

</body>
</html>