<?php

// Options handling
if (!defined('ABSPATH')) die ('No direct access allowed');

class UpdraftPlus_Options {

	public static function user_can_manage() {
		$user_can_manage = current_user_can(apply_filters('option_page_capability_updraft-options-group', 'manage_options'));
		// false: allows the filter to know that the request is not coming from the multisite add-on
		return apply_filters('updraft_user_can_manage', $user_can_manage, false);
	}

	public static function options_table() {
		return 'options';
	}

	public static function admin_page_url() {
		return admin_url('options-general.php');
	}

	public static function admin_page() {
		return 'options-general.php';
	}

	public static function get_updraft_option($option, $default = null) {
		return get_option($option, $default);
	}

	// The apparently unused parameter is used in the alternative class in the Multisite add-on
	public static function update_updraft_option($option, $value, $use_cache = true) {
		return update_option($option, $value);
	}

	public static function delete_updraft_option($option) {
		delete_option($option);
	}

	public static function add_admin_pages() {
		global $updraftplus_admin;
		add_submenu_page('options-general.php', 'UpdraftPlus', __('UpdraftPlus Backups','updraftplus'), apply_filters('option_page_capability_updraft-options-group', 'manage_options'), "updraftplus", array($updraftplus_admin, "settings_output"));
	}

	public static function options_form_begin($settings_fields = 'updraft-options-group', $allow_autocomplete = true, $get_params = array()) {
		global $pagenow;
		echo '<form method="post"';

		$page = '';
		if ('options-general.php' == $pagenow) $page="options.php";

		if (!empty($get_params)) {
			$page .= '?';
			$first_one = true;
			foreach ($get_params as $k => $v) {
				if ($first_one) {
					$first_one = false;
				} else {
					$page .= '&';
				}
				$page .= urlencode($k).'='.urlencode($v);
			}
		}

		if ($page) echo ' action="'.$page.'"';

		if (!$allow_autocomplete) echo ' autocomplete="off"';
		echo '>';
		if ($settings_fields) {
			// This is settings_fields('updraft-options-group'), but with the referer pruned
			echo "<input type='hidden' name='option_page' value='" . esc_attr('updraft-options-group') . "' />";
			echo '<input type="hidden" name="action" value="update" />';
			// $action = -1, $name = "_wpnonce", $referer = true , $echo = true 
			wp_nonce_field("updraft-options-group-options", '_wpnonce', false);

			$remove_query_args = array('state', 'action', 'updraftcopycomparms', 'oauth_verifier');

			// wp_unslash() does not exist until after WP 3.5
			if (function_exists('wp_unslash')) {
				$referer = wp_unslash( remove_query_arg( $remove_query_args, $_SERVER['REQUEST_URI']) );
			} else {
				$referer = stripslashes_deep( remove_query_arg( $remove_query_args, $_SERVER['REQUEST_URI']) );
			}

			// Add back the page parameter if it looks like we were on the settings page via an OAuth callback that has now had all parameters removed. This is likely unnecessarily conservative, but there's nothing requiring more than this at the current time.
			if (substr($referer, -19, 19) == 'options-general.php' && false !== strpos($_SERVER['REQUEST_URI'], '?')) $referer .= '?page=updraftplus';

			$referer_field = '<input type="hidden" name="_wp_http_referer" value="'. esc_attr($referer) . '" />';
			echo $referer_field;
		}
	}

	public static function admin_init() {

		static $already_inited = false;
		if ($already_inited) return;
		
		$already_inited = true;
	
		// If being called outside of the admin context, this may not be loaded yet
		if (!function_exists('register_setting')) include_once(ABSPATH.'wp-admin/includes/plugin.php');
	
		global $updraftplus, $updraftplus_admin;
		register_setting('updraft-options-group', 'updraft_interval', array($updraftplus, 'schedule_backup') );
		register_setting('updraft-options-group', 'updraft_interval_database', array($updraftplus, 'schedule_backup_database') );
		register_setting('updraft-options-group', 'updraft_interval_increments');
		register_setting('updraft-options-group', 'updraft_retain', array($updraftplus, 'retain_range') );
		register_setting('updraft-options-group', 'updraft_retain_db', array($updraftplus, 'retain_range') );
		register_setting('updraft-options-group', 'updraft_retain_extrarules' );

		register_setting('updraft-options-group', 'updraft_encryptionphrase');
		register_setting('updraft-options-group', 'updraft_service', array($updraftplus, 'just_one'));

		register_setting('updraft-options-group', 'updraft_s3', array($updraftplus, 's3_sanitise'));
		register_setting('updraft-options-group', 'updraft_ftp', array($updraftplus, 'ftp_sanitise'));
		register_setting('updraft-options-group', 'updraft_dreamobjects');
		register_setting('updraft-options-group', 'updraft_s3generic');
		register_setting('updraft-options-group', 'updraft_cloudfiles');
		register_setting('updraft-options-group', 'updraft_bitcasa', array($updraftplus, 'bitcasa_checkchange'));
		register_setting('updraft-options-group', 'updraft_copycom', array($updraftplus, 'copycom_checkchange'));
		register_setting('updraft-options-group', 'updraft_openstack');
		register_setting('updraft-options-group', 'updraft_dropbox', array($updraftplus, 'dropbox_checkchange'));
		register_setting('updraft-options-group', 'updraft_googledrive', array($updraftplus, 'googledrive_checkchange'));
		register_setting('updraft-options-group', 'updraft_onedrive', array($updraftplus, 'onedrive_checkchange'));
		register_setting('updraft-options-group', 'updraft_azure', array($updraftplus, 'azure_checkchange'));
		register_setting('updraft-options-group', 'updraft_googlecloud', array($updraftplus, 'googlecloud_checkchange'));

		register_setting('updraft-options-group', 'updraft_sftp_settings');
		register_setting('updraft-options-group', 'updraft_webdav_settings', array($updraftplus, 'replace_http_with_webdav'));

		register_setting('updraft-options-group', 'updraft_ssl_nossl', 'absint');
		register_setting('updraft-options-group', 'updraft_log_syslog', 'absint');
		register_setting('updraft-options-group', 'updraft_ssl_useservercerts', 'absint');
		register_setting('updraft-options-group', 'updraft_ssl_disableverify', 'absint');

		register_setting('updraft-options-group', 'updraft_split_every', array($updraftplus_admin, 'optionfilter_split_every') );

		register_setting('updraft-options-group', 'updraft_dir', array($updraftplus_admin, 'prune_updraft_dir_prefix') );
		register_setting('updraft-options-group', 'updraft_email', array($updraftplus, 'just_one_email'));

		register_setting('updraft-options-group', 'updraft_report_warningsonly', array($updraftplus_admin, 'return_array'));
		register_setting('updraft-options-group', 'updraft_report_wholebackup', array($updraftplus_admin, 'return_array'));

		register_setting('updraft-options-group', 'updraft_autobackup_default', 'absint' );
		register_setting('updraft-options-group', 'updraft_delete_local', 'absint' );
		register_setting('updraft-options-group', 'updraft_debug_mode', 'absint' );
		register_setting('updraft-options-group', 'updraft_extradbs');
		register_setting('updraft-options-group', 'updraft_backupdb_nonwp', 'absint');

		register_setting('updraft-options-group', 'updraft_include_plugins', 'absint' );
		register_setting('updraft-options-group', 'updraft_include_themes', 'absint' );
		register_setting('updraft-options-group', 'updraft_include_uploads', 'absint' );
		register_setting('updraft-options-group', 'updraft_include_others', 'absint' );
		register_setting('updraft-options-group', 'updraft_include_wpcore', 'absint' );
		register_setting('updraft-options-group', 'updraft_include_wpcore_exclude', array($updraftplus, 'strip_dirslash'));
		register_setting('updraft-options-group', 'updraft_include_more', 'absint' );
		register_setting('updraft-options-group', 'updraft_include_more_path', array($updraftplus, 'remove_empties'));
		register_setting('updraft-options-group', 'updraft_include_uploads_exclude', array($updraftplus, 'strip_dirslash'));
		register_setting('updraft-options-group', 'updraft_include_others_exclude', array($updraftplus, 'strip_dirslash'));

		register_setting('updraft-options-group', 'updraft_starttime_files', array('UpdraftPlus_Options', 'hourminute') );
		register_setting('updraft-options-group', 'updraft_starttime_db', array('UpdraftPlus_Options', 'hourminute') );

		register_setting('updraft-options-group', 'updraft_startday_files', array('UpdraftPlus_Options', 'week_or_month_day') );
		register_setting('updraft-options-group', 'updraft_startday_db', array('UpdraftPlus_Options', 'week_or_month_day') );

		global $pagenow;
		if (is_multisite() && $pagenow == 'options-general.php' && isset($_REQUEST['page']) && 'updraftplus' == substr($_REQUEST['page'], 0, 11)) {
			add_action('all_admin_notices', array('UpdraftPlus_Options', 'show_admin_warning_multisite') );
		}
	}

	public static function hourminute($pot) {
		if (preg_match("/^([0-2]?[0-9]):([0-5][0-9])$/", $pot, $matches)) return sprintf("%02d:%s", $matches[1], $matches[2]);
		if ('' == $pot) return date('H:i', time()+300);
		return '00:00';
	}

	public static function week_or_month_day($pot) {
		$pot = absint($pot);
		return ($pot>28) ? 1 : $pot;
	}

	public static function show_admin_warning_multisite() {
		global $updraftplus_admin;
		$updraftplus_admin->show_admin_warning('<strong>'.__('UpdraftPlus warning:', 'updraftplus').'</strong> '.__('This is a WordPress multi-site (a.k.a. network) installation.', 'updraftplus').' <a href="https://updraftplus.com/shop/">'.__('WordPress Multisite is supported, with extra features, by UpdraftPlus Premium, or the Multisite add-on.', 'updraftplus').'</a> '.__('Without upgrading, UpdraftPlus allows <strong>every</strong> blog admin who can modify plugin settings to back up (and hence access the data, including passwords, from) and restore (including with customised modifications, e.g. changed passwords) <strong>the entire network</strong>.', 'updraftplus').' '.__('(This applies to all WordPress backup plugins unless they have been explicitly coded for multisite compatibility).', 'updraftplus'), 'error');
	}

}

add_action('admin_init', array('UpdraftPlus_Options', 'admin_init'));
add_action('admin_menu', array('UpdraftPlus_Options', 'add_admin_pages'));
