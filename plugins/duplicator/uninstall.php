<?php
/**
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
        exit;
}
require_once 'define.php';
require_once 'classes/class.settings.php';
require_once 'classes/utilities/class.u.php';

global $wpdb;
$DUP_Settings = new DUP_Settings();

$table_name = $wpdb->prefix . "duplicator_packages";
$wpdb->query("DROP TABLE `{$table_name}`");

delete_option('duplicator_version_plugin');

//Remvoe entire wp-snapshots directory
if (DUP_Settings::Get('uninstall_files')) {

	$ssdir = DUP_Util::safePath(DUPLICATOR_SSDIR_PATH);
	$ssdir_tmp = DUP_Util::safePath(DUPLICATOR_SSDIR_PATH_TMP);

	//Sanity check for strange setup
	$check = glob("{$ssdir}/wp-config.php");
	if (count($check) == 0) {

		//PHP sanity check
		foreach (glob("{$ssdir}/*_database.sql") as $file) {
			if (strstr($file, '_database.sql'))
				@unlink("{$file}");
		}
		foreach (glob("{$ssdir}/*_installer.php") as $file) {
			if (strstr($file, '_installer.php'))
				@unlink("{$file}");
		}
		foreach (glob("{$ssdir}/*_archive.zip") as $file) {
			if (strstr($file, '_archive.zip')) 
				@unlink("{$file}");
		}
		foreach (glob("{$ssdir}/*_scan.json") as $file) {
			if (strstr($file, '_scan.json'))
				@unlink("{$file}");
		}
		foreach (glob("{$ssdir}/*.log") as $file) {
			if (strstr($file, '.log')) 
				@unlink("{$file}");
		}

		//Check for core files and only continue removing data if the snapshots directory
		//has not been edited by 3rd party sources, this helps to keep the system stable
		$files = glob("{$ssdir}/*");
		if (is_array($files) && count($files) < 6) {
			$defaults = array("{$ssdir}/index.php", "{$ssdir}/robots.txt", "{$ssdir}/dtoken.php");
			$compare = array_diff($defaults, $files);
			
			//There might be a .htaccess file or index.php/html etc.
			if (count($compare) < 3) {
				foreach ($defaults as $file) {
					@unlink("{$file}");
				}
				@unlink("{$ssdir}/.htaccess");
				@rmdir($ssdir_tmp);
				@rmdir($ssdir);
			}
		} 
	}
}

//Remove all Settings
if (DUP_Settings::Get('uninstall_settings')) {
	DUP_Settings::Delete();
	delete_option('duplicator_ui_view_state');
	delete_option('duplicator_package_active');
}
?>