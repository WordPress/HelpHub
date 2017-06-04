<?php

if (!defined ('UPDRAFTPLUS_DIR')) die('No direct access allowed');

// Admin-area code lives here. This gets called in admin_menu, earlier than admin_init

global $updraftplus_admin;
if (!is_a($updraftplus_admin, 'UpdraftPlus_Admin')) $updraftplus_admin = new UpdraftPlus_Admin();

class UpdraftPlus_Admin {

	public $logged = array();

	public function __construct() {
		$this->admin_init();
	}

	private function setup_all_admin_notices_global($service){
		if ('googledrive' === $service || (is_array($service) && in_array('googledrive', $service))) {
			$opts = UpdraftPlus_Options::get_updraft_option('updraft_googledrive');
			if (empty($opts)) {
				$clientid = UpdraftPlus_Options::get_updraft_option('updraft_googledrive_clientid', '');
				$token = UpdraftPlus_Options::get_updraft_option('updraft_googledrive_token', '');
			} else {
				$clientid = $opts['clientid'];
				$token = (empty($opts['token'])) ? '' : $opts['token'];
			}
			if (!empty($clientid) && empty($token)) add_action('all_admin_notices', array($this,'show_admin_warning_googledrive'));
		}
		if ('googlecloud' === $service || (is_array($service) && in_array('googlecloud', $service))) {
			$opts = UpdraftPlus_Options::get_updraft_option('updraft_googlecloud');
			if (!empty($opts)) {
				$clientid = $opts['clientid'];
				$token = (empty($opts['token'])) ? '' : $opts['token'];
			}
			if (!empty($clientid) && empty($token)) add_action('all_admin_notices', array($this,'show_admin_warning_googlecloud'));
		}
		if ('dropbox' === $service || (is_array($service) && in_array('dropbox', $service))) {
			$opts = UpdraftPlus_Options::get_updraft_option('updraft_dropbox');
			if (empty($opts['tk_request_token'])) {
				add_action('all_admin_notices', array($this,'show_admin_warning_dropbox') );
			}
		}
		if ('bitcasa' === $service || (is_array($service) && in_array('bitcasa', $service))) {
			$opts = UpdraftPlus_Options::get_updraft_option('updraft_bitcasa');
			if (!empty($opts['clientid']) && !empty($opts['secret']) && empty($opts['token'])) add_action('all_admin_notices', array($this,'show_admin_warning_bitcasa') );
		}
		if ('copycom' === $service || (is_array($service) && in_array('copycom', $service))) {
			$opts = UpdraftPlus_Options::get_updraft_option('updraft_copycom');
			if (!empty($opts['clientid']) && !empty($opts['secret']) && empty($opts['token'])) add_action('all_admin_notices', array($this,'show_admin_warning_copycom') );
		}
		if ('onedrive' === $service || (is_array($service) && in_array('onedrive', $service))) {
			$opts = UpdraftPlus_Options::get_updraft_option('updraft_onedrive');
			if (!empty($opts['clientid']) && !empty($opts['secret']) && empty($opts['refresh_token'])) add_action('all_admin_notices', array($this,'show_admin_warning_onedrive') );
		}

		if ('updraftvault' === $service || (is_array($service) && in_array('updraftvault', $service))) {
			$vault_settings = UpdraftPlus_Options::get_updraft_option('updraft_updraftvault');
			$connected = (is_array($vault_settings) && !empty($vault_settings['token']) && !empty($vault_settings['email'])) ? true : false;
			if (!$connected) add_action('all_admin_notices', array($this,'show_admin_warning_updraftvault') );
		}

		if ($this->disk_space_check(1048576*35) === false) add_action('all_admin_notices', array($this, 'show_admin_warning_diskspace'));
	}
	
	private function setup_all_admin_notices_udonly($service, $override = false){
		global $wp_version;

		if (UpdraftPlus_Options::user_can_manage() && defined('DISABLE_WP_CRON') && DISABLE_WP_CRON == true) {
			add_action('all_admin_notices', array($this, 'show_admin_warning_disabledcron'));
		}

		if (UpdraftPlus_Options::get_updraft_option('updraft_debug_mode')) {
			@ini_set('display_errors',1);
			@error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
			add_action('all_admin_notices', array($this, 'show_admin_debug_warning'));
		}

		if (null === UpdraftPlus_Options::get_updraft_option('updraft_interval')) {
			add_action('all_admin_notices', array($this, 'show_admin_nosettings_warning'));
			$this->no_settings_warning = true;
		}

		# Avoid false positives, by attempting to raise the limit (as happens when we actually do a backup)
		@set_time_limit(UPDRAFTPLUS_SET_TIME_LIMIT);
		$max_execution_time = (int)@ini_get('max_execution_time');
		if ($max_execution_time>0 && $max_execution_time<20) {
			add_action('all_admin_notices', array($this, 'show_admin_warning_execution_time'));
		}

		// LiteSpeed has a generic problem with terminating cron jobs
		if (isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false) {
			if (!is_file(ABSPATH.'.htaccess') || !preg_match('/noabort/i', file_get_contents(ABSPATH.'.htaccess'))) {
				add_action('all_admin_notices', array($this, 'show_admin_warning_litespeed'));
			}
		}

		if (version_compare($wp_version, '3.2', '<')) add_action('all_admin_notices', array($this, 'show_admin_warning_wordpressversion'));
	}
	
	/*
	private function reset_all_updraft_admin_notices() {
	
		$actions_to_remove = array('show_admin_warning_googledrive', 'show_admin_warning_googlecloud', 'show_admin_warning_dropbox', 'show_admin_warning_bitcasa', 'show_admin_warning_copycom', 'show_admin_warning_onedrive', 'show_admin_warning_updraftvault', 'show_admin_warning_diskspace', 'show_admin_warning_disabledcron', 'show_admin_debug_warning', 'show_admin_warning_execution_time', 'show_admin_warning_litespeed', 'show_admin_warning_wordpressversion');
	
		foreach ($actions_to_remove as $action) {
			remove_action('all_admin_notices', $action);
		}

	}
	*/
	
	//Used to output the information for the next scheduled backup
	//**// moved to function for the ajax saves
	private function next_scheduled_backups_output() {
		// UNIX timestamp
		$next_scheduled_backup = wp_next_scheduled('updraft_backup');
		if ($next_scheduled_backup) {
			// Convert to GMT
			$next_scheduled_backup_gmt = gmdate('Y-m-d H:i:s', $next_scheduled_backup);
			// Convert to blog time zone
			$next_scheduled_backup = get_date_from_gmt($next_scheduled_backup_gmt, 'D, F j, Y H:i');
// 			$next_scheduled_backup = date_i18n('D, F j, Y H:i', $next_scheduled_backup);
		} else {
			$next_scheduled_backup = __('Nothing currently scheduled', 'updraftplus');
			$files_not_scheduled = true;
		}
		
		$next_scheduled_backup_database = wp_next_scheduled('updraft_backup_database');
		if (UpdraftPlus_Options::get_updraft_option('updraft_interval_database',UpdraftPlus_Options::get_updraft_option('updraft_interval')) == UpdraftPlus_Options::get_updraft_option('updraft_interval')) {
			if (isset($files_not_scheduled)) {
				$next_scheduled_backup_database = $next_scheduled_backup;
				$database_not_scheduled = true;
			} else {
				$next_scheduled_backup_database = __("At the same time as the files backup", 'updraftplus');
				$next_scheduled_backup_database_same_time = true;
			}
		} else {
			if ($next_scheduled_backup_database) {
				// Convert to GMT
				$next_scheduled_backup_database_gmt = gmdate('Y-m-d H:i:s', $next_scheduled_backup_database);
				// Convert to blog time zone
				$next_scheduled_backup_database = get_date_from_gmt($next_scheduled_backup_database_gmt, 'D, F j, Y H:i');
// 				$next_scheduled_backup_database = date_i18n('D, F j, Y H:i', $next_scheduled_backup_database);
			} else {
				$next_scheduled_backup_database = __('Nothing currently scheduled', 'updraftplus');
				$database_not_scheduled = true;
			}
		}
		?>
		<tr>
		<?php if (isset($files_not_scheduled) && isset($database_not_scheduled)) { ?>
			<td colspan="2" class="not-scheduled"><?php _e('Nothing currently scheduled','updraftplus'); ?></td>
		<?php } else { ?>
			<td class="updraft_scheduled"><?php echo empty($next_scheduled_backup_database_same_time) ? __('Files','updraftplus') : __('Files and database', 'updraftplus'); ?>:</td><td class="updraft_all-files"><?php echo $next_scheduled_backup; ?></td>
			</tr>
			<?php if (empty($next_scheduled_backup_database_same_time)) { ?>
				<tr>
					<td class="updraft_scheduled"><?php _e('Database','updraftplus');?>: </td><td class="updraft_all-files"><?php echo $next_scheduled_backup_database; ?></td>
				</tr>
			<?php } ?>
		<?php
		}
	}
	
	private function admin_init() {

		add_action('core_upgrade_preamble', array($this, 'core_upgrade_preamble'));
		add_action('admin_action_upgrade-plugin', array($this, 'admin_action_upgrade_pluginortheme'));
		add_action('admin_action_upgrade-theme', array($this, 'admin_action_upgrade_pluginortheme'));

		add_action('admin_head', array($this,'admin_head'));
		add_filter((is_multisite() ? 'network_admin_' : '').'plugin_action_links', array($this, 'plugin_action_links'), 10, 2);
		add_action('wp_ajax_updraft_download_backup', array($this, 'updraft_download_backup'));
		add_action('wp_ajax_updraft_ajax', array($this, 'updraft_ajax_handler'));
		add_action('wp_ajax_updraft_ajaxrestore', array($this, 'updraft_ajaxrestore'));
		add_action('wp_ajax_nopriv_updraft_ajaxrestore', array($this, 'updraft_ajaxrestore'));
		
		add_action('wp_ajax_plupload_action', array($this, 'plupload_action'));
		add_action('wp_ajax_plupload_action2', array($this, 'plupload_action2'));

		add_action('wp_before_admin_bar_render', array($this, 'wp_before_admin_bar_render'));

		// Add a new Ajax action for saving settings
		add_action('wp_ajax_updraft_savesettings', array($this, 'updraft_ajax_savesettings'));
		
		global $updraftplus, $wp_version, $pagenow;
		add_filter('updraftplus_dirlist_others', array($updraftplus, 'backup_others_dirlist'));
		add_filter('updraftplus_dirlist_uploads', array($updraftplus, 'backup_uploads_dirlist'));

		// First, the checks that are on all (admin) pages:

		$service = UpdraftPlus_Options::get_updraft_option('updraft_service');

		if (UpdraftPlus_Options::user_can_manage()) {

			$this->print_restore_in_progress_box_if_needed();

			// Main dashboard page advert
			// Since our nonce is printed, make sure they have sufficient credentials
			if (!file_exists(UPDRAFTPLUS_DIR.'/udaddons') && $pagenow == 'index.php' && current_user_can('update_plugins')) {

				$dismissed_until = UpdraftPlus_Options::get_updraft_option('updraftplus_dismisseddashnotice', 0);
				
				$backup_dir = $updraftplus->backups_dir_location();
				// N.B. Not an exact proxy for the installed time; they may have tweaked the expert option to move the directory
				$installed = @filemtime($backup_dir.'/index.html');
				$installed_for = time() - $installed;

				if (($installed && time() > $dismissed_until && $installed_for > 28*86400  && !defined('UPDRAFTPLUS_NOADS_B')) || (defined('UPDRAFTPLUS_FORCE_DASHNOTICE') && UPDRAFTPLUS_FORCE_DASHNOTICE)) {
					add_action('all_admin_notices', array($this, 'show_admin_notice_upgradead') );
				}
			}
			
			//Moved out for use with Ajax saving
			$this->setup_all_admin_notices_global($service);
		}

		// Next, the actions that only come on the UpdraftPlus page
		if ($pagenow != UpdraftPlus_Options::admin_page() || empty($_REQUEST['page']) || 'updraftplus' != $_REQUEST['page']) return;
		$this->setup_all_admin_notices_udonly($service);
		
		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'), 99999);
	}

	public function updraft_ajaxrestore() {
// TODO: All needs testing with restricted filesystem permissions. Those credentials need to be POST-ed too - currently not.
// TODO
// error_log(serialize($_POST));

		if (empty($_POST['subaction']) || 'restore' != $_POST['subaction']) {
			echo json_encode(array('e' => 'Illegitimate data sent (0)'));
			die();
		}

		if (empty($_POST['restorenonce'])) {
			echo json_encode(array('e' => 'Illegitimate data sent (1)'));
			die();
		}

		$restore_nonce = (string)$_POST['restorenonce'];

		if (empty($_POST['ajaxauth'])) {
			echo json_encode(array('e' => 'Illegitimate data sent (2)'));
			die();
		}

		global $updraftplus;

		$ajax_auth = get_site_option('updraft_ajax_restore_'.$restore_nonce);

		if (!$ajax_auth) {
			echo json_encode(array('e' => 'Illegitimate data sent (3)'));
			die();
		}

		if (!preg_match('/^([0-9a-f]+):(\d+)/i', $ajax_auth, $matches)) {
			echo json_encode(array('e' => 'Illegitimate data sent (4)'));
			die();
		}

		$nonce_time = $matches[2];
		$auth_code_sent = $matches[1];
		if (time() > $nonce_time + 600) {
			echo json_encode(array('e' => 'Illegitimate data sent (5)'));
			die();
		}

// TODO: Deactivate the auth code whilst the operation is underway

		$last_one = empty($_POST['lastone']) ? false : true;

		@set_time_limit(UPDRAFTPLUS_SET_TIME_LIMIT);

		$updraftplus->backup_time_nonce($restore_nonce);
		$updraftplus->logfile_open($restore_nonce);

		$timestamp = empty($_POST['timestamp']) ? false : (int)$_POST['timestamp'];
		$multisite = empty($_POST['multisite']) ? false : (bool)$_POST['multisite'];
		$created_by_version = empty($_POST['created_by_version']) ? false : (int)$_POST['created_by_version'];

		// TODO: We need to know about first_one (not yet sent), as well as last_one

		// TODO: Verify the values of these
		$type = empty($_POST['type']) ? false : (int)$_POST['type'];
		$backupfile = empty($_POST['backupfile']) ? false : (string)$_POST['backupfile'];

		$updraftplus->log("Deferred restore resumption: $type: $backupfile (timestamp=$timestamp, last_one=$last_one)");



		$backupable_entities = $updraftplus->get_backupable_file_entities(true);

		if (!isset($backupable_entities[$type])) {
			echo json_encode(array('e' => 'Illegitimate data sent (6 - no such entity)', 'data' => $type));
			die();
		}


		if ($last_one) {
			// Remove the auth nonce from the DB to prevent abuse
			delete_site_option('updraft_ajax_restore_'.$restore_nonce);
		} else {
			// Reset the counter after a successful operation
			update_site_option('updraft_ajax_restore_'.$restore_nonce, $auth_code_sent.':'.time());
		}

		echo json_encode(array('e' => 'TODO', 'd' => $_POST));
		die;
	}

	public function wp_before_admin_bar_render() {
		global $wp_admin_bar;
		
		if (!UpdraftPlus_Options::user_can_manage()) return;
		if (defined('UPDRAFTPLUS_ADMINBAR_DISABLE') && UPDRAFTPLUS_ADMINBAR_DISABLE) return;

		if (false == apply_filters('updraftplus_settings_page_render', true)) return;

		$option_location = UpdraftPlus_Options::admin_page_url();
		
		$args = array(
			'id' => 'updraft_admin_node',
			'title' => 'UpdraftPlus'
		);
		$wp_admin_bar->add_node($args);
		
		$args = array(
			'id' => 'updraft_admin_node_status',
			'title' => __('Current Status', 'updraftplus').' / '.__('Backup Now', 'updraftplus'),
			'parent' => 'updraft_admin_node',
			'href' => $option_location.'?page=updraftplus&tab=status'
		);
		$wp_admin_bar->add_node($args);
		
		$args = array(
			'id' => 'updraft_admin_node_backups',
			'title' => __('Existing Backups', 'updraftplus'),
			'parent' => 'updraft_admin_node',
			'href' => $option_location.'?page=updraftplus&tab=backups'
		);
		$wp_admin_bar->add_node($args);
		
		$args = array(
			'id' => 'updraft_admin_node_settings',
			'title' => __('Settings', 'updraftplus'),
			'parent' => 'updraft_admin_node',
			'href' => $option_location.'?page=updraftplus&tab=settings'
		);
		$wp_admin_bar->add_node($args);
		
		$args = array(
			'id' => 'updraft_admin_node_expert_content',
			'title' => __('Advanced Tools', 'updraftplus'),
			'parent' => 'updraft_admin_node',
			'href' => $option_location.'?page=updraftplus&tab=expert'
		);
		$wp_admin_bar->add_node($args);
		
		$args = array(
			'id' => 'updraft_admin_node_addons',
			'title' => __('Extensions', 'updraftplus'),
			'parent' => 'updraft_admin_node',
			'href' => $option_location.'?page=updraftplus&tab=addons'
		);
		$wp_admin_bar->add_node($args);
		
		global $updraftplus;
		if (!$updraftplus->have_addons) {
			$args = array(
				'id' => 'updraft_admin_node_premium',
				'title' => 'UpdraftPlus Premium',
				'parent' => 'updraft_admin_node',
				'href' => 'https://updraftplus.com/shop/updraftplus-premium/'
			);
			$wp_admin_bar->add_node($args);
		}
	}

// 	// Defeat other plugins/themes which dump their jQuery UI CSS onto our settings page
// 	public function style_loader_tag($link, $handle) {
// 		if ('jquery-ui' != $handle || false === strpos) return $link;
// 		return "<link rel='stylesheet' id='$handle-css' $title href='$href' type='text/css' media='$media' />\n";
// 	}

	public function show_admin_notice_upgradead() {
		?>
		<div id="updraft-dashnotice" class="updated">
			<div style="float:right;"><a href="#" onclick="jQuery('#updraft-dashnotice').slideUp(); jQuery.post(ajaxurl, {action: 'updraft_ajax', subaction: 'dismissdashnotice', nonce: '<?php echo wp_create_nonce('updraftplus-credentialtest-nonce');?>' });"><?php echo sprintf(__('Dismiss (for %s months)', 'updraftplus'), 12); ?></a></div>

			<h3 class="thank-you"><?php _e('Thank you for backing up with UpdraftPlus!', 'updraftplus');?></h3>
			
			<a href="https://updraftplus.com/"><img class="udp-logo" alt="UpdraftPlus" src="<?php echo UPDRAFTPLUS_URL.'/images/ud-logo-150.png' ?>"></a>

			<?php
				echo '<p><strong>'.__('Free Newsletter', 'updraftplus').'</strong> <br>'.__('UpdraftPlus news, high-quality training materials for WordPress developers and site-owners, and general WordPress news. You can de-subscribe at any time.', 'updraftplus').' <a href="https://updraftplus.com/newsletter-signup">'.__('Follow this link to sign up.', 'updraftplus').'</a></p>';

				echo '<p><strong>'.__('UpdraftPlus Premium', 'updraftplus').'</strong> <br>'.__('For personal support, the ability to copy sites, more storage destinations, encrypted backups for security, multiple backup destinations, better reporting, no adverts and plenty more, take a look at the premium version of UpdraftPlus - the world’s most popular backup plugin.', 'updraftplus').' <a href="https://updraftplus.com/comparison-updraftplus-free-updraftplus-premium/">'.__('Compare with the free version', 'updraftplus').'</a> / <a href="https://updraftplus.com/shop/updraftplus-premium/">'.__('Go to the shop.', 'updraftplus').'</a></p>';

				echo '<p><strong>'.__('More Quality Plugins', 'updraftplus').'</strong> <br> <a href="https://wordpress.org/plugins/two-factor-authentication/">'.__('Free two-factor security plugin', 'updraftplus').'</a> | <a href="https://www.simbahosting.co.uk/s3/shop/">'.__('Premium WooCommerce plugins', 'updraftplus').'</a></p>';
			?>

			<div class="dismiss-dash-notice"><a href="#" onclick="jQuery('#updraft-dashnotice').slideUp(); jQuery.post(ajaxurl, {action: 'updraft_ajax', subaction: 'dismissdashnotice', nonce: '<?php echo wp_create_nonce('updraftplus-credentialtest-nonce');?>' });"><?php echo sprintf(__('Dismiss (for %s months)', 'updraftplus'), 12); ?></a></div>
		</div>  
		<?php
	}

	private function ensure_sufficient_jquery_and_enqueue() {
		global $updraftplus, $wp_version;
		
		$enqueue_version = @constant('WP_DEBUG') ? $updraftplus->version.'-'.time() : $updraftplus->version;
		
		if (version_compare($wp_version, '3.3', '<')) {
			// Require a newer jQuery (3.2.1 has 1.6.1, so we go for something not too much newer). We use .on() in a way that is incompatible with < 1.7
			wp_deregister_script('jquery');
			wp_register_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js', false, '1.7.2', false);
			wp_enqueue_script('jquery');
			// No plupload until 3.3
			wp_enqueue_script('updraftplus-admin-ui', UPDRAFTPLUS_URL.'/includes/updraft-admin-ui.js', array('jquery', 'jquery-ui-dialog'), $enqueue_version, true);
		} else {
			wp_enqueue_script('updraftplus-admin-ui', UPDRAFTPLUS_URL.'/includes/updraft-admin-ui.js', array('jquery', 'jquery-ui-dialog', 'plupload-all'), $enqueue_version);
		}
		
	}

	// This is also called directly from the auto-backup add-on
	public function admin_enqueue_scripts() {

		global $updraftplus, $wp_locale;

		// Defeat other plugins/themes which dump their jQuery UI CSS onto our settings page
		wp_deregister_style('jquery-ui');
		wp_enqueue_style('jquery-ui', UPDRAFTPLUS_URL.'/includes/jquery-ui.custom.css', array(), '1.11.4');
		
		$our_version = @constant('SCRIPT_DEBUG') ? $updraftplus->version.'.'.time() : $updraftplus->version;
		
		wp_enqueue_style('updraft-admin-css', UPDRAFTPLUS_URL.'/css/admin.css', array(), $our_version);
// 		add_filter('style_loader_tag', array($this, 'style_loader_tag'), 10, 2);

		$this->ensure_sufficient_jquery_and_enqueue();
		
		wp_enqueue_script('jquery-blockui', UPDRAFTPLUS_URL.'/includes/jquery.blockUI.js', array('jquery'), '2.70.0');
	
		wp_enqueue_script('jquery-labelauty', UPDRAFTPLUS_URL.'/includes/labelauty/jquery-labelauty.js', array('jquery'), '20150925');
		wp_enqueue_style('jquery-labelauty', UPDRAFTPLUS_URL.'/includes/labelauty/jquery-labelauty.css', array(), '20150925'); 

		do_action('updraftplus_admin_enqueue_scripts');
		
		$day_selector = '';
		for ($day_index = 0; $day_index <= 6; $day_index++) {
// 			$selected = ($opt == $day_index) ? 'selected="selected"' : '';
			$selected = '';
			$day_selector .= "\n\t<option value='" . $day_index . "' $selected>" . $wp_locale->get_weekday($day_index) . '</option>';
		}

		$mday_selector = '';
		for ($mday_index = 1; $mday_index <= 28; $mday_index++) {
// 			$selected = ($opt == $mday_index) ? 'selected="selected"' : '';
			$selected = '';
			$mday_selector .= "\n\t<option value='" . $mday_index . "' $selected>" . $mday_index . '</option>';
		}

		wp_localize_script('updraftplus-admin-ui', 'updraftlion', array(
			'sendonlyonwarnings' => __('Send a report only when there are warnings/errors', 'updraftplus'),
			'wholebackup' => __('When the Email storage method is enabled, also send the entire backup', 'updraftplus'),
			'emailsizelimits' => esc_attr(sprintf(__('Be aware that mail servers tend to have size limits; typically around %s Mb; backups larger than any limits will likely not arrive.','updraftplus'), '10-20')),
			'rescanning' => __('Rescanning (looking for backups that you have uploaded manually into the internal backup store)...','updraftplus'),
			'rescanningremote' => __('Rescanning remote and local storage for backup sets...','updraftplus'),
			'enteremailhere' => esc_attr(__('To send to more than one address, separate each address with a comma.', 'updraftplus')),
			'excludedeverything' => __('If you exclude both the database and the files, then you have excluded everything!', 'updraftplus'),
			'nofileschosen' => __('You have chosen to backup files, but no file entities have been selected', 'updraftplus'),
			'restoreproceeding' => __('The restore operation has begun. Do not press stop or close your browser until it reports itself as having finished.', 'updraftplus'),
			'unexpectedresponse' => __('Unexpected response:','updraftplus'),
			'servererrorcode' => __('The web server returned an error code (try again, or check your web server logs)', 'updraftplus'),
			'newuserpass' => __("The new user's RackSpace console password is (this will not be shown again):", 'updraftplus'),
			'trying' => __('Trying...', 'updraftplus'),
			'fetching' => __('Fetching...', 'updraftplus'),
			'calculating' => __('calculating...','updraftplus'),
			'begunlooking' => __('Begun looking for this entity','updraftplus'),
			'stilldownloading' => __('Some files are still downloading or being processed - please wait.', 'updraftplus'),
			'processing' => __('Processing files - please wait...', 'updraftplus'),
			'emptyresponse' => __('Error: the server sent an empty response.', 'updraftplus'),
			'warnings' => __('Warnings:','updraftplus'),
			'errors' => __('Errors:','updraftplus'),
			'jsonnotunderstood' => __('Error: the server sent us a response which we did not understand.', 'updraftplus'),
			'errordata' => __('Error data:', 'updraftplus'),
			'error' => __('Error:','updraftplus'),
			'errornocolon' => __('Error','updraftplus'),
			'fileready' => __('File ready.','updraftplus'),
			'youshould' => __('You should:','updraftplus'),
			'deletefromserver' => __('Delete from your web server','updraftplus'),
			'downloadtocomputer' => __('Download to your computer','updraftplus'),
			'andthen' => __('and then, if you wish,', 'updraftplus'),
			'notunderstood' => __('Download error: the server sent us a response which we did not understand.', 'updraftplus'),
			'requeststart' => __('Requesting start of backup...', 'updraftplus'),
			'phpinfo' => __('PHP information', 'updraftplus'),
			'delete_old_dirs' => __('Delete Old Directories', 'updraftplus'),
			'raw' => __('Raw backup history', 'updraftplus'),
			'notarchive' => __('This file does not appear to be an UpdraftPlus backup archive (such files are .zip or .gz files which have a name like: backup_(time)_(site name)_(code)_(type).(zip|gz)).', 'updraftplus').' '.__('However, UpdraftPlus archives are standard zip/SQL files - so if you are sure that your file has the right format, then you can rename it to match that pattern.','updraftplus'),
			'notarchive2' => '<p>'.__('This file does not appear to be an UpdraftPlus backup archive (such files are .zip or .gz files which have a name like: backup_(time)_(site name)_(code)_(type).(zip|gz)).', 'updraftplus').'</p> '.apply_filters('updraftplus_if_foreign_then_premium_message', '<p><a href="https://updraftplus.com/shop/updraftplus-premium/">'.__('If this is a backup created by a different backup plugin, then UpdraftPlus Premium may be able to help you.', 'updraftplus').'</a></p>'),
			'makesure' => __('(make sure that you were trying to upload a zip file previously created by UpdraftPlus)','updraftplus'),
			'uploaderror' => __('Upload error:','updraftplus'),
			'notdba' => __('This file does not appear to be an UpdraftPlus encrypted database archive (such files are .gz.crypt files which have a name like: backup_(time)_(site name)_(code)_db.crypt.gz).','updraftplus'),
			'uploaderr' => __('Upload error', 'updraftplus'),
			'followlink' => __('Follow this link to attempt decryption and download the database file to your computer.','updraftplus'),
			'thiskey' => __('This decryption key will be attempted:','updraftplus'),
			'unknownresp' => __('Unknown server response:','updraftplus'),
			'ukrespstatus' => __('Unknown server response status:','updraftplus'),
			'uploaded' => __('The file was uploaded.','updraftplus'),
			'backupnow' => __('Backup Now', 'updraftplus'),
			'cancel' => __('Cancel', 'updraftplus'),
			'deletebutton' => __('Delete', 'updraftplus'),
			'createbutton' => __('Create', 'updraftplus'),
			'youdidnotselectany' => __('You did not select any components to restore. Please select at least one, and then try again.', 'updraftplus'),
			'proceedwithupdate' => __('Proceed with update', 'updraftplus'),
			'close' => __('Close', 'updraftplus'),
			'restore' => __('Restore', 'updraftplus'),
			'downloadlogfile' => __('Download log file', 'updraftplus'),
			'automaticbackupbeforeupdate' => __('Automatic backup before update', 'updraftplus'),
			'unsavedsettings' => __('You have made changes to your settings, and not saved.', 'updraftplus'),
			'saving' => __('Saving...', 'updraftplus'),
			'connect' => __('Connect', 'updraftplus'),
			'connecting' => __('Connecting...', 'updraftplus'),
			'disconnect' => __('Disconnect', 'updraftplus'),
			'disconnecting' => __('Disconnecting...', 'updraftplus'),
			'counting' => __('Counting...', 'updraftplus'),
			'updatequotacount' => __('Update quota count', 'updraftplus'),
			'addingsite' => __('Adding...', 'updraftplus'),
			'addsite' => __('Add site', 'updraftplus'),
// 			'resetting' => __('Resetting...', 'updraftplus'),
			'creating' => __('Creating...', 'updraftplus'),
			'sendtosite' => __('Send to site:', 'updraftplus'),
			'checkrpcsetup' => sprintf(__('You should check that the remote site is online, not firewalled, does not have security modules that may be blocking access, has UpdraftPlus version %s or later active and that the keys have been entered correctly.', 'updraftplus'), '2.10.3'),
			'pleasenamekey' => __('Please give this key a name (e.g. indicate the site it is for):', 'updraftplus'),
			'key' => __('Key', 'updraftplus'),
			'nokeynamegiven' => sprintf(__("Failure: No %s was given.",'updraftplus'), __('key name','updraftplus')),
			'deleting' => __('Deleting...', 'updraftplus'),
			'enter_mothership_url' => __('Please enter a valid URL', 'updraftplus'),
			'delete_response_not_understood' => __("We requested to delete the file, but could not understand the server's response", 'updraftplus'),
			'testingconnection' => __('Testing connection...', 'updraftplus'),
			'send' => __('Send', 'updraftplus'),
			'migratemodalheight' => class_exists('UpdraftPlus_Addons_Migrator') ? 555 : 300,
			'migratemodalwidth' => class_exists('UpdraftPlus_Addons_Migrator') ? 770 : 500,
			'download' => _x('Download', '(verb)', 'updraftplus'),
			'unsavedsettingsbackup' => __('You have made changes to your settings, and not saved.', 'updraftplus')."\n".__('You should save your changes to ensure that they are used for making your backup.','updraftplus'),
			'dayselector' => $day_selector,
			'mdayselector' => $mday_selector,
			'day' => __('day', 'updraftplus'),
			'inthemonth' => __('in the month', 'updraftplus'),
			'days' => __('day(s)', 'updraftplus'),
			'hours' => __('hour(s)', 'updraftplus'),
			'weeks' => __('week(s)', 'updraftplus'),
			'forbackupsolderthan' => __('For backups older than', 'updraftplus'),
			'ud_url' => UPDRAFTPLUS_URL,
			'processing' => __('Processing...', 'updraftplus'),
			'pleasefillinrequired' => __('Please fill in the required information.', 'updraftplus'),
			'test_settings' => __('Test %s Settings', 'updraftplus'),
			'testing_settings' => __('Testing %s Settings...', 'updraftplus'),
			'settings_test_result' => __('%s settings test result:', 'updraftplus'),
			'nothing_yet_logged' => __('Nothing yet logged', 'updraftplus'),
		) );
	}

	// Despite the name, this fires irrespective of what capabilities the user has (even none - so be careful)
	public function core_upgrade_preamble() {
		
		// They need to be able to perform backups, and to perform updates
		if (!UpdraftPlus_Options::user_can_manage() || (!current_user_can('update_core') && !current_user_can('update_plugins') && !current_user_can('update_themes'))) return;

		if (!class_exists('UpdraftPlus_Addon_Autobackup')) {
			if (defined('UPDRAFTPLUS_NOADS_B')) return;
			$dismissed_until = UpdraftPlus_Options::get_updraft_option('updraftplus_dismissedautobackup', 0);
			if ($dismissed_until > time()) return;
		}
		
		?>
		<div id="updraft-autobackup" class="updated autobackup">
			<?php if (!class_exists('UpdraftPlus_Addon_Autobackup')) { ?>
			<div style="float:right;"><a href="#" onclick="jQuery('#updraft-autobackup').slideUp(); jQuery.post(ajaxurl, {action: 'updraft_ajax', subaction: 'dismissautobackup', nonce: '<?php echo wp_create_nonce('updraftplus-credentialtest-nonce');?>' });"><?php echo sprintf(__('Dismiss (for %s weeks)', 'updraftplus'), 12); ?></a></div> <?php } ?>
			<h3 style="margin-top: 2px;"><?php _e('Be safe with an automatic backup','updraftplus');?></h3>
			<?php echo apply_filters('updraftplus_autobackup_blurb', $this->autobackup_ad_content()); ?>
		</div>
		<script>
		jQuery(document).ready(function() {
			jQuery('#updraft-autobackup').appendTo('.wrap p:first');
		});
		</script>
		<?php
	}
	
	private function autobackup_ad_content(){
		global $updraftplus;
		$our_version = @constant('SCRIPT_DEBUG') ? $updraftplus->version.'.'.time() : $updraftplus->version;
		wp_enqueue_style('updraft-admin-css', UPDRAFTPLUS_URL.'/css/admin.css', array(), $our_version);
		
		$ret = '<div class="autobackup-description"><img class="autobackup-image" src="'.UPDRAFTPLUS_URL.'/images/automaticbackup.png" class="automation-icon"/>';
		$ret .= '<div class="advert-description">'.__('UpdraftPlus Premium can automatically take a backup of your plugins or themes and database before you update. <a href="https://updraftplus.com/shop/autobackup/" target="_blank">Be safe every time, without needing to remember - follow this link to learn more</a>', 'updraftplus').'</div></div>';
		$ret .= '<div class="advert-btn"><a href="https://updraftplus.com/shop/autobackup/" class="btn btn-get-started">'.__('Just this add-on', 'updraftplus').' <span class="circle-dblarrow">&raquo;</span></a></div>';
		$ret .= '<div class="advert-btn"><a href="https://updraftplus.com/shop/updraftplus-premium/" class="btn btn-get-started">'.__('Full Premium plugin', 'updraftplus').' <span class="circle-dblarrow">&raquo;</span></a></div>';
		return $ret;
	}

	public function admin_head() {

		global $pagenow;

		if ($pagenow != UpdraftPlus_Options::admin_page() || !isset($_REQUEST['page']) || 'updraftplus' != $_REQUEST['page'] || !UpdraftPlus_Options::user_can_manage()) return;

 		$chunk_size = min(wp_max_upload_size()-1024, 1048576*2);

		# The multiple_queues argument is ignored in plupload 2.x (WP3.9+) - http://make.wordpress.org/core/2014/04/11/plupload-2-x-in-wordpress-3-9/
		# max_file_size is also in filters as of plupload 2.x, but in its default position is still supported for backwards-compatibility. Likewise, our use of filters.extensions below is supported by a backwards-compatibility option (the current way is filters.mime-types.extensions

		$plupload_init = array(
			'runtimes' => 'html5,flash,silverlight,html4',
			'browse_button' => 'plupload-browse-button',
			'container' => 'plupload-upload-ui',
			'drop_element' => 'drag-drop-area',
			'file_data_name' => 'async-upload',
			'multiple_queues' => true,
			'max_file_size' => '100Gb',
			'chunk_size' => $chunk_size.'b',
			'url' => admin_url('admin-ajax.php', 'relative'),
			'multipart' => true,
			'multi_selection' => true,
			'urlstream_upload' => true,
			// additional post data to send to our ajax hook
			'multipart_params' => array(
				'_ajax_nonce' => wp_create_nonce('updraft-uploader'),
				'action' => 'plupload_action'
			)
		);
// 			'flash_swf_url' => includes_url('js/plupload/plupload.flash.swf'),
// 			'silverlight_xap_url' => includes_url('js/plupload/plupload.silverlight.xap'),

// We want to receive -db files also...
// 		if (1) {
// 			$plupload_init['filters'] = array(array('title' => __('Allowed Files'), 'extensions' => 'zip,tar,gz,bz2,crypt,sql,txt'));
// 		} else {
// 		}

		# WP 3.9 updated to plupload 2.0 - https://core.trac.wordpress.org/ticket/25663
		if (is_file(ABSPATH.WPINC.'/js/plupload/Moxie.swf')) {
			$plupload_init['flash_swf_url'] = includes_url('js/plupload/Moxie.swf');
		} else {
			$plupload_init['flash_swf_url'] = includes_url('js/plupload/plupload.flash.swf');
		}

		if (is_file(ABSPATH.WPINC.'/js/plupload/Moxie.xap')) {
			$plupload_init['silverlight_xap_url'] = includes_url('js/plupload/Moxie.xap');
		} else {
			$plupload_init['silverlight_xap_url'] = includes_url('js/plupload/plupload.silverlight.swf');
		}

		?><script type="text/javascript">
			var updraft_credentialtest_nonce='<?php echo wp_create_nonce('updraftplus-credentialtest-nonce');?>';
			var updraftplus_settings_nonce='<?php echo wp_create_nonce('updraftplus-settings-nonce');?>';
			var updraft_siteurl = '<?php echo esc_js(site_url('', 'relative'));?>';
			var updraft_plupload_config=<?php echo json_encode($plupload_init); ?>;
			var updraft_download_nonce='<?php echo wp_create_nonce('updraftplus_download');?>';
			var updraft_accept_archivename = <?php echo apply_filters('updraftplus_accept_archivename_js', "[]");?>;
			<?php
			$plupload_init['browse_button'] = 'plupload-browse-button2';
			$plupload_init['container'] = 'plupload-upload-ui2';
			$plupload_init['drop_element'] = 'drag-drop-area2';
			$plupload_init['multipart_params']['action'] = 'plupload_action2';
			$plupload_init['filters'] = array(array('title' => __('Allowed Files'), 'extensions' => 'crypt'));
			?>
			var updraft_plupload_config2=<?php echo json_encode($plupload_init); ?>;
			var updraft_downloader_nonce = '<?php wp_create_nonce("updraftplus_download"); ?>'
			<?php
				$overdue = $this->howmany_overdue_crons();
				if ($overdue >= 4) { ?>
			jQuery(document).ready(function(){
				setTimeout(function(){updraft_check_overduecrons();}, 11000);
				function updraft_check_overduecrons() {
					jQuery.get(ajaxurl, { action: 'updraft_ajax', subaction: 'checkoverduecrons', nonce: updraft_credentialtest_nonce }, function(data, response) {
						if ('success' == response) {
							try {
								resp = jQuery.parseJSON(data);
								if (resp.m) {
									jQuery('#updraft-insert-admin-warning').html(resp.m);
								}
							} catch(err) {
								console.log(data);
							}
						}
					});
				}
			});
			<?php } ?>
		</script>
		<?php

	}


	private function disk_space_check($space) {
		global $updraftplus;
		$updraft_dir = $updraftplus->backups_dir_location();
		$disk_free_space = @disk_free_space($updraft_dir);
		if ($disk_free_space == false) return -1;
		return ($disk_free_space > $space) ? true : false;
	}

	# Adds the settings link under the plugin on the plugin screen.
	public function plugin_action_links($links, $file) {
		if (is_array($links) && $file == 'updraftplus/updraftplus.php'){
			$settings_link = '<a href="'.UpdraftPlus_Options::admin_page_url().'?page=updraftplus">'.__("Settings", "updraftplus").'</a>';
			array_unshift($links, $settings_link);
// 			$settings_link = '<a href="http://david.dw-perspective.org.uk/donate">'.__("Donate","UpdraftPlus").'</a>';
// 			array_unshift($links, $settings_link);
			$settings_link = '<a href="https://updraftplus.com">'.__("Add-Ons / Pro Support","updraftplus").'</a>';
			array_unshift($links, $settings_link);
		}
		return $links;
	}

	public function admin_action_upgrade_pluginortheme() {

		if (isset($_GET['action']) && ($_GET['action'] == 'upgrade-plugin' || $_GET['action'] == 'upgrade-theme') && !class_exists('UpdraftPlus_Addon_Autobackup') && !defined('UPDRAFTPLUS_NOADS_B')) {

			if ($_GET['action'] == 'upgrade-plugin') {
				if (!current_user_can('update_plugins')) return;
			} else {
				if (!current_user_can('update_themes')) return;
			}

			$dismissed_until = UpdraftPlus_Options::get_updraft_option('updraftplus_dismissedautobackup', 0);
			if ($dismissed_until > time()) return;

			if ( 'upgrade-plugin' == $_GET['action'] ) {
				$title = __('Update Plugin');
				$parent_file = 'plugins.php';
				$submenu_file = 'plugins.php';
			} else {
				$title = __('Update Theme');
				$parent_file = 'themes.php';
				$submenu_file = 'themes.php';
			}

			require_once(ABSPATH.'wp-admin/admin-header.php');

			?>
			<div id="updraft-autobackup" class="updated" style="float:left; padding: 6px; margin:8px 0px;">
				<div style="float: right;"><a href="#" onclick="jQuery('#updraft-autobackup').slideUp(); jQuery.post(ajaxurl, {action: 'updraft_ajax', subaction: 'dismissautobackup', nonce: '<?php echo wp_create_nonce('updraftplus-credentialtest-nonce');?>' });"><?php echo sprintf(__('Dismiss (for %s weeks)', 'updraftplus'), 10); ?></a></div>
				<h3 style="margin-top: 0px;"><?php _e('Be safe with an automatic backup','updraftplus');?></h3>
				<p><?php echo $this->autobackup_ad_content(); ?></p>
			</div>
			<?php
		}
	}

	public function show_admin_warning($message, $class = "updated") {
		echo '<div class="updraftmessage '.$class.'">'."<p>$message</p></div>";
	}

	//
	public function show_admin_warning_unwritable(){
		$unwritable_mess = htmlspecialchars(__("The 'Backup Now' button is disabled as your backup directory is not writable (go to the 'Settings' tab and find the relevant option).", 'updraftplus'));
		$this->show_admin_warning($unwritable_mess, "error");
	}	
	
	public function show_admin_nosettings_warning() {
		$this->show_admin_warning('<strong>'.__('Welcome to UpdraftPlus!', 'updraftplus').'</strong> '.__('To make a backup, just press the Backup Now button.', 'updraftplus').' <a href="#" id="updraft-navtab-settings2">'.__('To change any of the default settings of what is backed up, to configure scheduled backups, to send your backups to remote storage (recommended), and more, go to the settings tab.', 'updraftplus').'</a>', 'updated notice is-dismissible');
	}

	public function show_admin_warning_execution_time() {
		$this->show_admin_warning('<strong>'.__('Warning','updraftplus').':</strong> '.sprintf(__('The amount of time allowed for WordPress plugins to run is very low (%s seconds) - you should increase it to avoid backup failures due to time-outs (consult your web hosting company for more help - it is the max_execution_time PHP setting; the recommended value is %s seconds or more)', 'updraftplus'), (int)@ini_get('max_execution_time'), 90));
	}

	public function show_admin_warning_disabledcron() {
		$this->show_admin_warning('<strong>'.__('Warning','updraftplus').':</strong> '.__('The scheduler is disabled in your WordPress install, via the DISABLE_WP_CRON setting. No backups can run (even &quot;Backup Now&quot;) unless either you have set up a facility to call the scheduler manually, or until it is enabled.','updraftplus').' <a href="https://updraftplus.com/faqs/my-scheduled-backups-and-pressing-backup-now-does-nothing-however-pressing-debug-backup-does-produce-a-backup/#disablewpcron">'.__('Go here for more information.','updraftplus').'</a>', 'updated updraftplus-disable-wp-cron-warning');
	}

	public function show_admin_warning_diskspace() {
		$this->show_admin_warning('<strong>'.__('Warning','updraftplus').':</strong> '.sprintf(__('You have less than %s of free disk space on the disk which UpdraftPlus is configured to use to create backups. UpdraftPlus could well run out of space. Contact your the operator of your server (e.g. your web hosting company) to resolve this issue.','updraftplus'),'35 MB'));
	}

	public function show_admin_warning_wordpressversion() {
		$this->show_admin_warning('<strong>'.__('Warning','updraftplus').':</strong> '.sprintf(__('UpdraftPlus does not officially support versions of WordPress before %s. It may work for you, but if it does not, then please be aware that no support is available until you upgrade WordPress.', 'updraftplus'), '3.2'));
	}

	public function show_admin_warning_litespeed() {
		$this->show_admin_warning('<strong>'.__('Warning','updraftplus').':</strong> '.sprintf(__('Your website is hosted using the %s web server.','updraftplus'),'LiteSpeed').' <a href="https://updraftplus.com/faqs/i-am-having-trouble-backing-up-and-my-web-hosting-company-uses-the-litespeed-webserver/">'.__('Please consult this FAQ if you have problems backing up.', 'updraftplus').'</a>');
	}

	public function show_admin_debug_warning() {
		$this->show_admin_warning('<strong>'.__('Notice','updraftplus').':</strong> '.__('UpdraftPlus\'s debug mode is on. You may see debugging notices on this page not just from UpdraftPlus, but from any other plugin installed. Please try to make sure that the notice you are seeing is from UpdraftPlus before you raise a support request.', 'updraftplus').'</a>');
	}

	public function show_admin_warning_overdue_crons($howmany) {
		$ret = '<div class="updraftmessage updated"><p>';
		$ret .= '<strong>'.__('Warning','updraftplus').':</strong> '.sprintf(__('WordPress has a number (%d) of scheduled tasks which are overdue. Unless this is a development site, this probably means that the scheduler in your WordPress install is not working.', 'updraftplus'), $howmany).' <a href="https://updraftplus.com/faqs/scheduler-wordpress-installation-working/">'.__('Read this page for a guide to possible causes and how to fix it.', 'updraftplus').'</a>';
		$ret .= '</p></div>';
		return $ret;
	}

	public function show_admin_warning_dropbox() {
		$this->show_admin_warning('<strong>'.__('UpdraftPlus notice:','updraftplus').'</strong> <a class="updraft_authlink" href="'.UpdraftPlus_Options::admin_page_url().'?page=updraftplus&action=updraftmethod-dropbox-auth&updraftplus_dropboxauth=doit">'.sprintf(__('Click here to authenticate your %s account (you will not be able to back up to %s without it).','updraftplus'), 'Dropbox', 'Dropbox').'</a>');
	}

	public function show_admin_warning_bitcasa() {
		$this->show_admin_warning('<strong>'.__('UpdraftPlus notice:','updraftplus').'</strong> <a class="updraft_authlink" href="'.UpdraftPlus_Options::admin_page_url().'?page=updraftplus&action=updraftmethod-bitcasa-auth&updraftplus_bitcasaauth=doit">'.sprintf(__('Click here to authenticate your %s account (you will not be able to back up to %s without it).','updraftplus'), 'Bitcasa', 'Bitcasa').'</a>');
	}

	public function show_admin_warning_copycom() {
		$this->show_admin_warning('<strong>'.__('UpdraftPlus notice:','updraftplus').'</strong> <a class="updraft_authlink" href="'.UpdraftPlus_Options::admin_page_url().'?page=updraftplus&action=updraftmethod-copycom-auth&updraftplus_copycomauth=doit">'.sprintf(__('Click here to authenticate your %s account (you will not be able to back up to %s without it).','updraftplus'), 'Copy.Com', 'Copy').'</a>');
	}

	public function show_admin_warning_onedrive() {
	$this->show_admin_warning('<strong>'.__('UpdraftPlus notice:','updraftplus').'</strong> <a class="updraft_authlink" href="'.UpdraftPlus_Options::admin_page_url().'?page=updraftplus&action=updraftmethod-onedrive-auth&updraftplus_onedriveauth=doit">'.sprintf(__('Click here to authenticate your %s account (you will not be able to back up to %s without it).','updraftplus'), 'OneDrive', 'OneDrive').'</a>');
	}

	public function show_admin_warning_updraftvault() {
		$this->show_admin_warning('<strong>'.__('UpdraftPlus notice:','updraftplus').'</strong> '.sprintf(__('%s has been chosen for remote storage, but you are not currently connected.', 'updraftplus'), 'UpdraftPlus Vault').' '.__('Go to the remote storage settings in order to connect.', 'updraftplus'));
	}

	public function show_admin_warning_googledrive() {
		$this->show_admin_warning('<strong>'.__('UpdraftPlus notice:','updraftplus').'</strong> <a class="updraft_authlink" href="'.UpdraftPlus_Options::admin_page_url().'?page=updraftplus&action=updraftmethod-googledrive-auth&updraftplus_googleauth=doit">'.sprintf(__('Click here to authenticate your %s account (you will not be able to back up to %s without it).','updraftplus'), 'Google Drive', 'Google Drive').'</a>');
	}

	public function show_admin_warning_googlecloud() {
		$this->show_admin_warning('<strong>'.__('UpdraftPlus notice:','updraftplus').'</strong> <a class="updraft_authlink" href="'.UpdraftPlus_Options::admin_page_url().'?page=updraftplus&action=updraftmethod-googlecloud-auth&updraftplus_googleauth=doit">'.sprintf(__('Click here to authenticate your %s account (you will not be able to back up to %s without it).','updraftplus'), 'Google Cloud', 'Google Cloud').'</a>');
	}

	// This options filter removes ABSPATH off the front of updraft_dir, if it is given absolutely and contained within it
	public function prune_updraft_dir_prefix($updraft_dir) {
		if ('/' == substr($updraft_dir, 0, 1) || "\\" == substr($updraft_dir, 0, 1) || preg_match('/^[a-zA-Z]:/', $updraft_dir)) {
			$wcd = trailingslashit(WP_CONTENT_DIR);
			if (strpos($updraft_dir, $wcd) === 0) {
				$updraft_dir = substr($updraft_dir, strlen($wcd));
			}
			# Legacy
// 			if (strpos($updraft_dir, ABSPATH) === 0) {
// 				$updraft_dir = substr($updraft_dir, strlen(ABSPATH));
// 			}
		}
		return $updraft_dir;
	}

	public function updraft_download_backup() {

		if (empty($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'updraftplus_download')) die;

		if (empty($_REQUEST['timestamp']) || !is_numeric($_REQUEST['timestamp']) || empty($_REQUEST['type'])) exit;

		$findex = empty($_REQUEST['findex']) ? 0 : (int)$_REQUEST['findex'];
		$stage = empty($_REQUEST['stage']) ? '' : $_REQUEST['stage'];

		// This call may not actually return, depending upon what mode it is called in
		echo json_encode($this->do_updraft_download_backup($findex, $_REQUEST['type'], $_REQUEST['timestamp'], $stage));
		
		die();
	}
	
	// This function may die(), depending on the request being made in $stage
	public function do_updraft_download_backup($findex, $type, $timestamp, $stage, $close_connection_callable = false) {
	
		@set_time_limit(UPDRAFTPLUS_SET_TIME_LIMIT);

		global $updraftplus;
		
		// This is a bit ugly; these variables get placed back into $_POST (where they may possibly have come from), so that UpdraftPlus::log() can detect exactly where to log the download status.
		$_POST['findex'] = $findex;
		$_POST['type'] = $type;
		$_POST['timestamp'] = $timestamp;

		// Check that it is a known entity type; if not, die
		if ('db' != substr($type, 0, 2)) {
			$backupable_entities = $updraftplus->get_backupable_file_entities(true);
			foreach ($backupable_entities as $t => $info) {
				if ($type == $t) $type_match = true;
			}
			if (empty($type_match)) return array('result' => 'error', 'code' => 'no_such_type');
		}

		// We already know that no possible entities have an MD5 clash (even after 2 characters)
		// Also, there's nothing enforcing a requirement that nonces are hexadecimal
		$job_nonce = dechex($timestamp).$findex.substr(md5($type), 0, 3);

		// You need a nonce before you can set job data. And we certainly don't yet have one.
		$updraftplus->backup_time_nonce($job_nonce);

		$debug_mode = UpdraftPlus_Options::get_updraft_option('updraft_debug_mode');

		// Set the job type before logging, as there can be different logging destinations
		$updraftplus->jobdata_set('job_type', 'download');
		$updraftplus->jobdata_set('job_time_ms', $updraftplus->job_time_ms);

		// Retrieve the information from our backup history
		$backup_history = $updraftplus->get_backup_history();
		// Base name
		$file = $backup_history[$timestamp][$type];

		// Deal with multi-archive sets
		if (is_array($file)) $file=$file[$findex];

		// Where it should end up being downloaded to
		$fullpath = $updraftplus->backups_dir_location().'/'.$file;

		if (2 == $stage) {
			$updraftplus->spool_file($fullpath);
			// Do not return - we do not want the caller to add any output
			die;
		}

		if ('delete' == $stage) {
			@unlink($fullpath);
			$updraftplus->log("The file has been deleted ($file)");
			return array('result' => 'deleted');
		}

		// TODO: FIXME: Failed downloads may leave log files forever (though they are small)
		if ($debug_mode) $updraftplus->logfile_open($updraftplus->nonce);

		set_error_handler(array($updraftplus, 'php_error'), E_ALL & ~E_STRICT);

		$updraftplus->log("Requested to obtain file: timestamp=$timestamp, type=$type, index=$findex");

		$itext = empty($findex) ? '' : $findex;
		$known_size = isset($backup_history[$timestamp][$type.$itext.'-size']) ? $backup_history[$timestamp][$type.$itext.'-size'] : 0;

		$services = (isset($backup_history[$timestamp]['service'])) ? $backup_history[$timestamp]['service'] : false;
		if (is_string($services)) $services = array($services);

		$updraftplus->jobdata_set('service', $services);

		// Fetch it from the cloud, if we have not already got it

		$needs_downloading = false;

		if (!file_exists($fullpath)) {
			//if the file doesn't exist and they're using one of the cloud options, fetch it down from the cloud.
			$needs_downloading = true;
			$updraftplus->log('File does not yet exist locally - needs downloading');
		} elseif ($known_size > 0 && filesize($fullpath) < $known_size) {
			$updraftplus->log("The file was found locally (".filesize($fullpath).") but did not match the size in the backup history ($known_size) - will resume downloading");
			$needs_downloading = true;
		} elseif ($known_size > 0) {
			$updraftplus->log('The file was found locally and matched the recorded size from the backup history ('.round($known_size/1024,1).' KB)');
		} else {
			$updraftplus->log('No file size was found recorded in the backup history. We will assume the local one is complete.');
			$known_size = filesize($fullpath);
		}

		// The AJAX responder that updates on progress wants to see this
		$updraftplus->jobdata_set('dlfile_'.$timestamp.'_'.$type.'_'.$findex, "downloading:$known_size:$fullpath");

		if ($needs_downloading) {

			$msg = array(
				'result' => 'needs_download'
			);
		
			if ($close_connection_callable && is_callable($close_connection_callable)) {
				call_user_func($close_connection_callable, $msg);
			} else {
				$updraftplus->close_browser_connection(json_encode($msg));
			}	
			
			$is_downloaded = false;
			add_action('http_request_args', array($updraftplus, 'modify_http_options'));
			foreach ($services as $service) {
				if ($is_downloaded) continue;
				$download = $this->download_file($file, $service);
				if (is_readable($fullpath) && $download !== false) {
					clearstatcache();
					$updraftplus->log('Remote fetch was successful (file size: '.round(filesize($fullpath)/1024,1).' KB)');
					$is_downloaded = true;
				} else {
					clearstatcache();
					if (0 === @filesize($fullpath)) @unlink($fullpath);
					$updraftplus->log('Remote fetch failed');
				}
			}
			remove_action('http_request_args', array($updraftplus, 'modify_http_options'));
		}

		// Now, be ready to spool the thing to the browser
		if (is_file($fullpath) && is_readable($fullpath)) {

			// That message is then picked up by the AJAX listener
			$updraftplus->jobdata_set('dlfile_'.$timestamp.'_'.$type.'_'.$findex, 'downloaded:'.filesize($fullpath).":$fullpath");

			$result = 'downloaded';
			
		} else {

			$updraftplus->jobdata_set('dlfile_'.$timestamp.'_'.$type.'_'.$findex, 'failed');
			$updraftplus->jobdata_set('dlerrors_'.$timestamp.'_'.$type.'_'.$findex, $updraftplus->errors);
			$updraftplus->log('Remote fetch failed. File '.$fullpath.' did not exist or was unreadable. If you delete local backups then remote retrieval may have failed.');
			
			$result = 'download_failed';
		}

		restore_error_handler();

		@fclose($updraftplus->logfile_handle);
		if (!$debug_mode) @unlink($updraftplus->logfile_name);

		// The browser connection was possibly already closed, but not necessarily
		return array('result' => $result);

	}

	# Pass only a single service, as a string, into this function
	private function download_file($file, $service) {

		global $updraftplus;

		@set_time_limit(UPDRAFTPLUS_SET_TIME_LIMIT);

		$updraftplus->log("Requested file from remote service: $service: $file");

		$method_include = UPDRAFTPLUS_DIR.'/methods/'.$service.'.php';
		if (file_exists($method_include)) require_once($method_include);

		$objname = "UpdraftPlus_BackupModule_${service}";
		if (method_exists($objname, "download")) {
			$remote_obj = new $objname;
			return $remote_obj->download($file);
		} else {
			$updraftplus->log("Automatic backup restoration is not available with the method: $service.");
			$updraftplus->log("$file: ".sprintf(__("The backup archive for this file could not be found. The remote storage method in use (%s) does not allow us to retrieve files. To perform any restoration using UpdraftPlus, you will need to obtain a copy of this file and place it inside UpdraftPlus's working folder", 'updraftplus'), $service)." (".$this->prune_updraft_dir_prefix($updraftplus->backups_dir_location()).")", 'error');
			return false;
		}

	}

	public function updraft_ajax_handler() {

		global $updraftplus;

		$nonce = (empty($_REQUEST['nonce'])) ? "" : $_REQUEST['nonce'];

		if (!wp_verify_nonce($nonce, 'updraftplus-credentialtest-nonce') || empty($_REQUEST['subaction'])) die('Security check');

		// Mitigation in case the nonce leaked to an unauthorised user
		if (isset($_REQUEST['subaction']) && 'dismissautobackup' == $_REQUEST['subaction']) {
			if (!current_user_can('update_plugins') && !current_user_can('update_themes')) return;
		} elseif (isset($_REQUEST['subaction']) && ('dismissexpiry' == $_REQUEST['subaction'] || 'dismissdashnotice' == $_REQUEST['subaction'])) {
			if (!current_user_can('update_plugins')) return;
		} else {
			if (!UpdraftPlus_Options::user_can_manage()) return;
		}

		// Some of this checks that _REQUEST['subaction'] is set, which is redundant (done already in the nonce check)
		/*
		// This one is no longer used anywhere
		if (isset($_REQUEST['subaction']) && 'lastlog' == $_REQUEST['subaction']) {
		
			$last_message = UpdraftPlus_Options::get_updraft_option('updraft_lastmessage');
		
			echo htmlspecialchars( '('.__('Nothing yet logged', 'updraftplus').')'));
			
		} else
		*/
		if ('forcescheduledresumption' == $_REQUEST['subaction'] && !empty($_REQUEST['resumption']) && !empty($_REQUEST['job_id']) && is_numeric($_REQUEST['resumption'])) {
			// Casting $resumption to int is absolutely necessary, as the WP cron system uses a hashed serialisation of the parameters for identifying jobs. Different type => different hash => does not match
			$resumption = (int)$_REQUEST['resumption'];
			$job_id = $_REQUEST['job_id'];
			$get_cron = $this->get_cron($job_id);
			if (!is_array($get_cron)) {
				echo json_encode(array('r' => false));
			} else {
				$updraftplus->log("Forcing resumption: job id=$job_id, resumption=$resumption");
				$time = $get_cron[0];
// 				wp_unschedule_event($time, 'updraft_backup_resume', array($resumption, $job_id));
				wp_clear_scheduled_hook('updraft_backup_resume', array($resumption, $job_id));
				$updraftplus->close_browser_connection(json_encode(array('r' => true)));
				$updraftplus->jobdata_set_from_array($get_cron[1]);
				$updraftplus->backup_resume($resumption, $job_id);
			}
		} elseif (isset($_GET['subaction']) && 'activejobs_list' == $_GET['subaction']) {
		
			echo json_encode($this->get_activejobs_list($_GET));

		} elseif (isset($_REQUEST['subaction']) && 'updraftcentral_delete_key' == $_REQUEST['subaction'] && isset($_REQUEST['key_id'])) {

			global $updraftplus_updraftcentral_main;
			if (!is_a($updraftplus_updraftcentral_main, 'UpdraftPlus_UpdraftCentral_Main')) {
				echo json_encode(array('error' => 'UpdraftPlus_UpdraftCentral_Main object not found'));
				die;
			}
			
			echo json_encode($updraftplus_updraftcentral_main->delete_key($_REQUEST['key_id']));
			die;
			
		} elseif (isset($_REQUEST['subaction']) && ('updraftcentral_create_key' == $_REQUEST['subaction'] || 'updraftcentral_get_log' == $_REQUEST['subaction'])) {
		
			global $updraftplus_updraftcentral_main;
			if (!is_a($updraftplus_updraftcentral_main, 'UpdraftPlus_UpdraftCentral_Main')) {
				echo json_encode(array('error' => 'UpdraftPlus_UpdraftCentral_Main object not found'));
				die;
			}
			
			$call_method = substr($_REQUEST['subaction'], 15);
			
			echo json_encode(call_user_func(array($updraftplus_updraftcentral_main, $call_method), $_REQUEST));
			
			die;
			
		} elseif (isset($_REQUEST['subaction']) && 'callwpaction' == $_REQUEST['subaction'] && !empty($_REQUEST['wpaction'])) {

			ob_start();

			$res = '<em>Request received: </em>';

			if (preg_match('/^([^:]+)+:(.*)$/', stripslashes($_REQUEST['wpaction']), $matches)) {
				$action = $matches[1];
				if (null === ($args = json_decode($matches[2], true))) {
					$res .= "The parameters (should be JSON) could not be decoded";
					$action = false;
				} else {
					$res .= "Will despatch action: ".htmlspecialchars($action).", parameters: ".htmlspecialchars(implode(',', $args));
				}
			} else {
				$action = $_REQUEST['wpaction'];
				$res .= "Will despatch action: ".htmlspecialchars($action).", no parameters";
			}

			echo json_encode(array('r' => $res));
			$ret = ob_get_clean();
			$updraftplus->close_browser_connection($ret);
			if (!empty($action)) {
				if (!empty($args)) {
					do_action_ref_array($action, $args);
				} else {
					do_action($action);
				}
			}
			die;
		} elseif (isset($_REQUEST['subaction']) && 'whichdownloadsneeded' == $_REQUEST['subaction'] && is_array($_REQUEST['downloads']) && isset($_REQUEST['timestamp']) && is_numeric($_REQUEST['timestamp'])) {
			// The purpose of this is to look at the list of indicated downloads, and indicate which are not already fully downloaded. i.e. Which need further action.
			$send_back = array();

			$backup = $updraftplus->get_backup_history($_REQUEST['timestamp']);
			$updraft_dir = $updraftplus->backups_dir_location();
			$backupable_entities = $updraftplus->get_backupable_file_entities();

			if (empty($backup)) {
				echo json_encode(array('result' => 'asyouwere'));
			} else {
				foreach ($_REQUEST['downloads'] as $i => $download) {
					if (is_array($download) && 2 == count($download) && isset($download[0]) && isset($download[1])) {
						$entity = $download[0];
						if (('db' == $entity || isset($backupable_entities[$entity])) && isset($backup[$entity])) {
							$indexes = explode(',', $download[1]);
							$retain_string = '';
							foreach ($indexes as $index) {
								$retain = true; // default
								$findex = (0 == $index) ? '' : (string)$index;
								$files = $backup[$entity];
								if (!is_array($files)) $files = array($files);
								$size_key = $entity.$findex.'-size';
								if (isset($files[$index]) && isset($backup[$size_key])) {
									$file = $updraft_dir.'/'.$files[$index];
									if (file_exists($file) && filesize($file) >= $backup[$size_key]) {
										$retain = false;
									}
								}
								if ($retain) {
									$retain_string .= ('' === $retain_string) ? $index : ','.$index;
									$send_back[$i][0] = $entity;
									$send_back[$i][1] = $retain_string;
								}
							}
						} else {
							$send_back[$i][0] = $entity;
							$send_back[$i][1] = $download[$i][1];
						}
					} else {
						// Format not understood. Just send it back as-is.
						$send_back[$i] = $download[$i];
					}
				}
				// Finally, renumber the keys (to usual PHP style - 0, 1, ...). Otherwise, in order to preserve the indexes, json_encode() will create an object instead of an array in the case where $send_back only has one element (and is indexed with an index > 0)
				$send_back = array_values($send_back);
				echo json_encode(array('downloads' => $send_back));
			}
		} elseif (isset($_REQUEST['subaction']) && 'httpget' == $_REQUEST['subaction']) {
			if (empty($_REQUEST['uri'])) {
				echo json_encode(array('r' => ''));
				die;
			}
			$uri = $_REQUEST['uri'];
			if (!empty($_REQUEST['curl'])) {
				if (!function_exists('curl_exec')) {
					echo json_encode(array('e' => 'No Curl installed'));
					die;
				}
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $uri);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_FAILONERROR, true);
				curl_setopt($ch, CURLOPT_HEADER, true);
				curl_setopt($ch, CURLOPT_VERBOSE, true);
				curl_setopt($ch, CURLOPT_STDERR, $output=fopen('php://temp', "w+"));
				$response = curl_exec($ch);
				$error = curl_error($ch);
				$getinfo = curl_getinfo($ch);
				curl_close($ch);
				$resp = array();
				if (false === $response) {
					$resp['e'] = htmlspecialchars($error);
					# json_encode(array('e' => htmlspecialchars($error)));
				}
				$resp['r'] = (empty($response)) ? '' : htmlspecialchars(substr($response, 0, 2048));
				rewind($output);
				$verb = stream_get_contents($output);
				if (!empty($verb)) $resp['r'] = htmlspecialchars($verb)."\n\n".$resp['r'];
				echo json_encode($resp);
// 				echo json_encode(array('r' => htmlspecialchars(substr($response, 0, 2048))));
			} else {
				$response = wp_remote_get($uri, array('timeout' => 10));
				if (is_wp_error($response)) {
					echo json_encode(array('e' => htmlspecialchars($response->get_error_message())));
					die;
				}
				echo json_encode(array('r' => wp_remote_retrieve_response_code($response).': '.htmlspecialchars(substr(wp_remote_retrieve_body($response), 0, 2048))));
			}
			die;
		} elseif (isset($_REQUEST['subaction']) && 'dismissautobackup' == $_REQUEST['subaction']) {
			UpdraftPlus_Options::update_updraft_option('updraftplus_dismissedautobackup', time() + 84*86400);
		} elseif (isset($_REQUEST['subaction']) && 'set_autobackup_default' == $_REQUEST['subaction']) {
			// This option when set should have integers, not bools
			$default = empty($_REQUEST['default']) ? 0 : 1;
			UpdraftPlus_Options::update_updraft_option('updraft_autobackup_default', $default);
		} elseif (isset($_REQUEST['subaction']) && 'dismissexpiry' == $_REQUEST['subaction']) {
			UpdraftPlus_Options::update_updraft_option('updraftplus_dismissedexpiry', time() + 14*86400);
		} elseif (isset($_REQUEST['subaction']) && 'dismissdashnotice' == $_REQUEST['subaction']) {
			UpdraftPlus_Options::update_updraft_option('updraftplus_dismisseddashnotice', time() + 366*86400);
		} elseif (isset($_REQUEST['subaction']) && 'poplog' == $_REQUEST['subaction']){ 

			echo json_encode($this->fetch_log($_REQUEST['backup_nonce']));

		} elseif (isset($_REQUEST['subaction']) && 'restore_alldownloaded' == $_REQUEST['subaction'] && isset($_REQUEST['restoreopts']) && isset($_REQUEST['timestamp'])) {

			$backups = $updraftplus->get_backup_history();
			$updraft_dir = $updraftplus->backups_dir_location();

			$timestamp = (int)$_REQUEST['timestamp'];
			if (!isset($backups[$timestamp])) {
				echo json_encode(array('m' => '', 'w' => '', 'e' => __('No such backup set exists', 'updraftplus')));
				die;
			}

			$mess = array();
			parse_str(stripslashes($_REQUEST['restoreopts']), $res);

			if (isset($res['updraft_restore'])) {

				set_error_handler(array($this, 'get_php_errors'), E_ALL & ~E_STRICT);

				$elements = array_flip($res['updraft_restore']);

				$warn = array(); $err = array();

				@set_time_limit(UPDRAFTPLUS_SET_TIME_LIMIT);
				$max_execution_time = (int)@ini_get('max_execution_time');

				if ($max_execution_time>0 && $max_execution_time<61) {
					$warn[] = sprintf(__('The PHP setup on this webserver allows only %s seconds for PHP to run, and does not allow this limit to be raised. If you have a lot of data to import, and if the restore operation times out, then you will need to ask your web hosting company for ways to raise this limit (or attempt the restoration piece-by-piece).', 'updraftplus'), $max_execution_time);
				}

				if (isset($backups[$timestamp]['native']) && false == $backups[$timestamp]['native']) {
					$warn[] = __('This backup set was not known by UpdraftPlus to be created by the current WordPress installation, but was either found in remote storage, or was sent from a remote site.', 'updraftplus').' '.__('You should make sure that this really is a backup set intended for use on this website, before you restore (rather than a backup set of an unrelated website).', 'updraftplus');
				}

				if (isset($elements['db'])) {
				
					// Analyse the header of the database file + display results
					list ($mess2, $warn2, $err2, $info) = $updraftplus->analyse_db_file($timestamp, $res);
					$mess = array_merge($mess, $mess2);
					$warn = array_merge($warn, $warn2);
					$err = array_merge($err, $err2);
					foreach ($backups[$timestamp] as $bid => $bval) {
						if ('db' != $bid && 'db' == substr($bid, 0, 2) && '-size' != substr($bid, -5, 5)) {
							$warn[] = __('Only the WordPress database can be restored; you will need to deal with the external database manually.', 'updraftplus');
							break;
						}
					}
				}

				$backupable_entities = $updraftplus->get_backupable_file_entities(true, true);
				$backupable_plus_db = $backupable_entities;
				$backupable_plus_db['db'] = array('path' => 'path-unused', 'description' => __('Database', 'updraftplus'));

				if (!empty($backups[$timestamp]['meta_foreign'])) {
					$foreign_known = apply_filters('updraftplus_accept_archivename', array());
					if (!is_array($foreign_known) || empty($foreign_known[$backups[$timestamp]['meta_foreign']])) {
						$err[] = sprintf(__('Backup created by unknown source (%s) - cannot be restored.', 'updraftplus'), $backups[$timestamp]['meta_foreign']);
					} else {
						// For some reason, on PHP 5.5 passing by reference in a single array stopped working with apply_filters_ref_array (though not with do_action_ref_array).
						$backupable_plus_db = apply_filters_ref_array("updraftplus_importforeign_backupable_plus_db", array($backupable_plus_db, array($foreign_known[$backups[$timestamp]['meta_foreign']], &$mess, &$warn, &$err)));
					}
				}

				foreach ($backupable_plus_db as $type => $entity_info) {
					if (!isset($elements[$type])) continue;
					$whatwegot = $backups[$timestamp][$type];
					if (is_string($whatwegot)) $whatwegot = array($whatwegot);
					$expected_index = 0;
					$missing = '';
					ksort($whatwegot);
					$outof = false;
					foreach ($whatwegot as $index => $file) {
						if (preg_match('/\d+of(\d+)\.zip/', $file, $omatch)) { $outof = max($matches[1], 1); }
						if ($index != $expected_index) {
							$missing .= ($missing == '') ? (1+$expected_index) : ",".(1+$expected_index);
						}
						if (!file_exists($updraft_dir.'/'.$file)) {
							$err[] = sprintf(__('File not found (you need to upload it): %s', 'updraftplus'), $updraft_dir.'/'.$file);
						} elseif (filesize($updraft_dir.'/'.$file) == 0) {
							$err[] = sprintf(__('File was found, but is zero-sized (you need to re-upload it): %s', 'updraftplus'), $file);
						} else {
							$itext = (0 == $index) ? '' : $index;
							if (!empty($backups[$timestamp][$type.$itext.'-size']) && $backups[$timestamp][$type.$itext.'-size'] != filesize($updraft_dir.'/'.$file)) {
								if (empty($warn['doublecompressfixed'])) {
									$warn[] = sprintf(__('File (%s) was found, but has a different size (%s) from what was expected (%s) - it may be corrupt.', 'updraftplus'), $file, filesize($updraft_dir.'/'.$file), $backups[$timestamp][$type.$itext.'-size']);
								}
							}
							do_action_ref_array("updraftplus_checkzip_$type", array($updraft_dir.'/'.$file, &$mess, &$warn, &$err));
						}
						$expected_index++;
					}
					do_action_ref_array("updraftplus_checkzip_end_$type", array(&$mess, &$warn, &$err));
					// Detect missing archives where they are missing from the end of the set
					if ($outof>0 && $expected_index < $outof) {
						for ($j = $expected_index; $j<$outof; $j++) {
							$missing .= ($missing == '') ? (1+$j) : ",".(1+$j);
						}
					}
					if ('' != $missing) {
						$warn[] = sprintf(__("This multi-archive backup set appears to have the following archives missing: %s", 'updraftplus'), $missing.' ('.$entity_info['description'].')');
					}
				}

				if (0 == count($err) && 0 == count($warn)) {
					$mess_first = __('The backup archive files have been successfully processed. Now press Restore again to proceed.', 'updraftplus');
				} elseif (0 == count($err)) {
					$mess_first = __('The backup archive files have been processed, but with some warnings. If all is well, then now press Restore again to proceed. Otherwise, cancel and correct any problems first.', 'updraftplus');
				} else {
					$mess_first = __('The backup archive files have been processed, but with some errors. You will need to cancel and correct any problems before retrying.', 'updraftplus');
				}

				if (count($this->logged) >0) {
					foreach ($this->logged as $lwarn) $warn[] = $lwarn;
				}
				restore_error_handler();

				// Get the info if it hasn't already come from the DB scan
				if (!isset($info) || !is_array($info)) $info = array();

				// Not all chracters can be json-encoded, and we don't need this potentially-arbitrary user-supplied info.
				unset($info['label']);

				if (!isset($info['created_by_version']) && !empty($backups[$timestamp]['created_by_version'])) $info['created_by_version'] = $backups[$timestamp]['created_by_version'];

				if (!isset($info['multisite']) && !empty($backups[$timestamp]['is_multisite'])) $info['multisite'] = $backups[$timestamp]['is_multisite'];
				
				do_action_ref_array('updraftplus_restore_all_downloaded_postscan', array($backups, $timestamp, $elements, &$info, &$mess, &$warn, &$err));

				echo json_encode(array('m' => '<p>'.$mess_first.'</p>'.implode('<br>', $mess), 'w' => implode('<br>', $warn), 'e' => implode('<br>', $err), 'i' => json_encode($info)));
			}
		} elseif ('sid_reset' == $_REQUEST['subaction']) {

			delete_site_option('updraftplus-addons_siteid');
			echo json_encode(array('newsid' => $updraftplus->siteid()));

		} elseif (('vault_connect' == $_REQUEST['subaction'] && isset($_REQUEST['email']) && isset($_REQUEST['pass'])) || 'vault_disconnect' == $_REQUEST['subaction'] || 'vault_recountquota' == $_REQUEST['subaction']) {

			require_once(UPDRAFTPLUS_DIR.'/methods/updraftvault.php');
			$vault = new UpdraftPlus_BackupModule_updraftvault();
			call_user_func(array($vault, 'ajax_'.$_REQUEST['subaction']));

		} elseif (isset($_POST['backup_timestamp']) && 'deleteset' == $_REQUEST['subaction']) {

			echo json_encode($this->delete_set($_POST));

		} elseif ('rawbackuphistory' == $_REQUEST['subaction']) {

			echo '<h3 id="ud-debuginfo-rawbackups">'.__('Known backups (raw)', 'updraftplus').'</h3><pre>';
			var_dump($updraftplus->get_backup_history());
			echo '</pre>';

			echo '<h3 id="ud-debuginfo-files">Files</h3><pre>';
			$updraft_dir = $updraftplus->backups_dir_location();
			$raw_output = array();
			$d = dir($updraft_dir);
			while (false !== ($entry = $d->read())) {
				$fp = $updraft_dir.'/'.$entry;
				$mtime = filemtime($fp);
				if (is_dir($fp)) {
					$size = '       d';
				} elseif (is_link($fp)) {
					$size = '       l';
				} elseif (is_file($fp)) {
					$size = sprintf("%8.1f", round(filesize($fp)/1024, 1)).' '.gmdate('r', $mtime);
				} else {
					$size = '       ?';
				}
				if (preg_match('/^log\.(.*)\.txt$/', $entry, $lmatch)) $entry = '<a target="_top" href="?action=downloadlog&page=updraftplus&updraftplus_backup_nonce='.htmlspecialchars($lmatch[1]).'">'.$entry.'</a>';
				$raw_output[$mtime] = empty($raw_output[$mtime]) ? sprintf("%s %s\n", $size, $entry) : $raw_output[$mtime].sprintf("%s %s\n", $size, $entry);
			}
			@$d->close();
			krsort($raw_output, SORT_NUMERIC);
			foreach ($raw_output as $line) echo $line;
			echo '</pre>';

			echo '<h3 id="ud-debuginfo-options">'.__('Options (raw)', 'updraftplus').'</h3>';
			$opts = $updraftplus->get_settings_keys();
			asort($opts);
			// <tr><th>'.__('Key','updraftplus').'</th><th>'.__('Value','updraftplus').'</th></tr>
			echo '<table><thead></thead><tbody>';
			foreach ($opts as $opt) {
				echo '<tr><td>'.htmlspecialchars($opt).'</td><td>'.htmlspecialchars(print_r(UpdraftPlus_Options::get_updraft_option($opt), true)).'</td>';
			}
			echo '</tbody></table>';

			do_action('updraftplus_showrawinfo');

			
		} elseif ('countbackups' == $_REQUEST['subaction']) {
			$backup_history = UpdraftPlus_Options::get_updraft_option('updraft_backup_history');
			$backup_history = (is_array($backup_history))?$backup_history:array();
			#echo sprintf(__('%d set(s) available', 'updraftplus'), count($backup_history));
			echo __('Existing Backups', 'updraftplus').' ('.count($backup_history).')';
		} elseif ('ping' == $_REQUEST['subaction']) {
			// The purpose of this is to detect brokenness caused by extra line feeds in plugins/themes - before it breaks other AJAX operations and leads to support requests
			echo 'pong';
		} elseif ('checkoverduecrons' == $_REQUEST['subaction']) {
			$how_many_overdue = $this->howmany_overdue_crons();
			if ($how_many_overdue >= 4) echo json_encode(array('m' => $this->show_admin_warning_overdue_crons($how_many_overdue)));
		} elseif ('delete_old_dirs' == $_REQUEST['subaction']) {
			$this->delete_old_dirs_go(false);
		} elseif ('phpinfo' == $_REQUEST['subaction']) {
			phpinfo(INFO_ALL ^ (INFO_CREDITS | INFO_LICENSE));

			echo '<h3 id="ud-debuginfo-constants">'.__('Constants', 'updraftplus').'</h3>';
			$opts = @get_defined_constants();
			ksort($opts);
			// <tr><th>'.__('Key','updraftplus').'</th><th>'.__('Value','updraftplus').'</th></tr>
			echo '<table><thead></thead><tbody>';
			foreach ($opts as $key => $opt) {
				echo '<tr><td>'.htmlspecialchars($key).'</td><td>'.htmlspecialchars(print_r($opt, true)).'</td>';
			}
			echo '</tbody></table>';

		} elseif ('doaction' == $_REQUEST['subaction'] && !empty($_REQUEST['subsubaction']) && 'updraft_' == substr($_REQUEST['subsubaction'], 0, 8)) {
			do_action($_REQUEST['subsubaction']);
		} elseif ('backupnow' == $_REQUEST['subaction']) {

			$this->request_backupnow($_REQUEST);
		
			# Old-style: schedule an event in 5 seconds time. This has the advantage of testing out the scheduler, and alerting the user if it doesn't work... but has the disadvantage of not working in that case.
			# I don't think the </div>s should be here - in case this is ever re-activated
// 			if (wp_schedule_single_event(time()+5, $event, array($backupnow_nocloud)) === false) {
// 				$updraftplus->log("A backup run failed to schedule");
// 				echo __("Failed.", 'updraftplus');
// 			} else {
// 				echo htmlspecialchars(__('OK. You should soon see activity in the "Last log message" field below.','updraftplus'))." <a href=\"https://updraftplus.com/faqs/my-scheduled-backups-and-pressing-backup-now-does-nothing-however-pressing-debug-backup-does-produce-a-backup/\"><br>".__('Nothing happening? Follow this link for help.','updraftplus')."</a>";
// 				$updraftplus->log("A backup run has been scheduled");
// 			}

		} elseif (isset($_GET['subaction']) && 'lastbackup' == $_GET['subaction']) {
			echo $this->last_backup_html();
		} elseif (isset($_GET['subaction']) && 'activejobs_delete' == $_GET['subaction'] && isset($_GET['jobid'])) {

			echo json_encode($this->activejobs_delete((string)$_GET['jobid']));

		} elseif (isset($_GET['subaction']) && 'diskspaceused' == $_GET['subaction'] && isset($_GET['entity'])) {
			$entity = $_GET['entity'];
			// This can count either the size of the Updraft directory, or of the data to be backed up
			echo $this->get_disk_space_used($entity);
		} elseif (isset($_GET['subaction']) && 'historystatus' == $_GET['subaction']) {
			$remotescan = !empty($_GET['remotescan']);
			$rescan = ($remotescan || !empty($_GET['rescan']));
			
			$history_status = $this->get_history_status($rescan, $remotescan);
			echo @json_encode($history_status);

		} elseif (isset($_POST['subaction']) && $_POST['subaction'] == 'credentials_test') {
		
			$this->do_credentials_test($_POST);
			die;

		}
		die;

	}
	
	// This echoes output; so, you will need to do output buffering if you want to capture it
	public function do_credentials_test($test_settings) {
	
		$method = (!empty($test_settings['method']) && preg_match("/^[a-z0-9]+$/", $test_settings['method'])) ? $test_settings['method'] : "";
		
		$objname = "UpdraftPlus_BackupModule_$method";
		
		$this->logged = array();
		# TODO: Add action for WP HTTP SSL stuff
		set_error_handler(array($this, 'get_php_errors'), E_ALL & ~E_STRICT);
		
		if (!class_exists($objname)) include_once(UPDRAFTPLUS_DIR."/methods/$method.php");

		# TODO: Add action for WP HTTP SSL stuff
		if (method_exists($objname, "credentials_test")) {
			$obj = new $objname;
			$obj->credentials_test($test_settings);
		}
		
		if (count($this->logged) >0) {
			echo "\n\n".__('Messages:', 'updraftplus')."\n";
			foreach ($this->logged as $err) {
				echo "* $err\n";
			}
		}
		restore_error_handler();
	}
	
	// Relevant options (array keys): backup_timestamp, delete_remote, 
	public function delete_set($opts) {
		
		global $updraftplus;
		
		$backups = $updraftplus->get_backup_history();
		$timestamps = (string)$opts['backup_timestamp'];

		$timestamps = explode(',', $timestamps);
		$delete_remote = empty($opts['delete_remote']) ? false : true;

		// You need a nonce before you can set job data. And we certainly don't yet have one.
		$updraftplus->backup_time_nonce();
		// Set the job type before logging, as there can be different logging destinations
		$updraftplus->jobdata_set('job_type', 'delete');
		$updraftplus->jobdata_set('job_time_ms', $updraftplus->job_time_ms);

		if (UpdraftPlus_Options::get_updraft_option('updraft_debug_mode')) {
			$updraftplus->logfile_open($updraftplus->nonce);
			set_error_handler(array($updraftplus, 'php_error'), E_ALL & ~E_STRICT);
		}

		$updraft_dir = $updraftplus->backups_dir_location();
		$backupable_entities = $updraftplus->get_backupable_file_entities(true, true);

		$local_deleted = 0;
		$remote_deleted = 0;
		$sets_removed = 0;

		foreach ($timestamps as $i => $timestamp) {

			if (!isset($backups[$timestamp])) {
				echo json_encode(array('result' => 'error', 'message' => __('Backup set not found', 'updraftplus')));
				die;
			}

			$nonce = isset($backups[$timestamp]['nonce']) ? $backups[$timestamp]['nonce'] : '';

			$delete_from_service = array();

			if ($delete_remote) {
				// Locate backup set
				if (isset($backups[$timestamp]['service'])) {
					$services = is_string($backups[$timestamp]['service']) ? array($backups[$timestamp]['service']) : $backups[$timestamp]['service'];
					if (is_array($services)) {
						foreach ($services as $service) {
							if ($service && $service != 'none' && $service != 'email') $delete_from_service[] = $service;
						}
					}
				}
			}

			$files_to_delete = array();
			foreach ($backupable_entities as $key => $ent) {
				if (isset($backups[$timestamp][$key])) {
					$files_to_delete[$key] = $backups[$timestamp][$key];
				}
			}
			// Delete DB
			if (isset($backups[$timestamp]['db'])) $files_to_delete['db'] = $backups[$timestamp]['db'];

			// Also delete the log
			if ($nonce && !UpdraftPlus_Options::get_updraft_option('updraft_debug_mode')) {
				$files_to_delete['log'] = "log.$nonce.txt";
			}

			unset($backups[$timestamp]);
			$sets_removed++;
			UpdraftPlus_Options::update_updraft_option('updraft_backup_history', $backups);

			add_action('http_request_args', array($updraftplus, 'modify_http_options'));
			foreach ($files_to_delete as $key => $files) {
				# Local deletion
				if (is_string($files)) $files=array($files);
				foreach ($files as $file) {
					if (is_file($updraft_dir.'/'.$file)) {
						if (@unlink($updraft_dir.'/'.$file)) $local_deleted++;
					}
				}
				if ('log' != $key && count($delete_from_service) > 0) {
					foreach ($delete_from_service as $service) {
						if ('email' == $service) continue;
						if (file_exists(UPDRAFTPLUS_DIR."/methods/$service.php")) require_once(UPDRAFTPLUS_DIR."/methods/$service.php");
						$objname = "UpdraftPlus_BackupModule_".$service;
						$deleted = -1;
						if (class_exists($objname)) {
							# TODO: Re-use the object (i.e. prevent repeated connection setup/teardown)
							$remote_obj = new $objname;
							$deleted = $remote_obj->delete($files);
						}
						if ($deleted === -1) {
							//echo __('Did not know how to delete from this cloud service.', 'updraftplus');
						} elseif ($deleted !== false) {
							$remote_deleted = $remote_deleted + count($files);
						} else {
							// Do nothing
						}
					}
				}
			}
			remove_action('http_request_args', array($updraftplus, 'modify_http_options'));
		}


		$message = sprintf(__('Backup sets removed: %d', 'updraftplus'),$sets_removed)."\n";

		$message .= sprintf(__('Local archives deleted: %d', 'updraftplus'),$local_deleted)."\n";
		$message .= sprintf(__('Remote archives deleted: %d', 'updraftplus'),$remote_deleted)."\n";

		$updraftplus->log("Local archives deleted: ".$local_deleted);
		$updraftplus->log("Remote archives deleted: ".$remote_deleted);

		if (UpdraftPlus_Options::get_updraft_option('updraft_debug_mode')) {
			restore_error_handler();
		}
		
		return array('result' => 'success', 'message' => $message, 'removed' => array('sets' => $sets_removed, 'local' => $local_deleted, 'remote' => $remote_deleted));

	}

	public function get_history_status($rescan, $remotescan) {
	
		global $updraftplus;
	
		if ($rescan) $messages = $updraftplus->rebuild_backup_history($remotescan);

		$backup_history = UpdraftPlus_Options::get_updraft_option('updraft_backup_history');
		$backup_history = (is_array($backup_history)) ? $backup_history : array();
		$output = $this->existing_backup_table($backup_history);

		if (!empty($messages) && is_array($messages)) {
			$noutput = '<div style="margin-left: 100px; margin-top: 10px;"><ul style="list-style: disc inside;">';
			foreach ($messages as $msg) {
				$noutput .= '<li>'.(($msg['desc']) ? $msg['desc'].': ' : '').'<em>'.$msg['message'].'</em></li>';
			}
			$noutput .= '</ul></div>';
			$output = $noutput.$output;
		}
		
		$logs_exist = (false !== strpos($output, 'downloadlog'));
		if (!$logs_exist) {
			list($mod_time, $log_file, $nonce) = $updraftplus->last_modified_log();
			if ($mod_time) $logs_exist = true;
		}
		
		return apply_filters('updraftplus_get_history_status_result', array(
			'n' => sprintf(__('Existing Backups', 'updraftplus').' (%d)', count($backup_history)),
			't' => $output,
			'cksum' => md5($output),
			'logs_exist' => $logs_exist,
		));
	}
	
	public function get_disk_space_used($entity) {
		global $updraftplus;
		if ('updraft' == $entity) {
			return $this->recursive_directory_size($updraftplus->backups_dir_location());
		} else {
			$backupable_entities = $updraftplus->get_backupable_file_entities(true, false);
			if ('all' == $entity) {
				$total_size = 0;
				foreach ($backupable_entities as $entity => $data) {
					# Might be an array
					$basedir = $backupable_entities[$entity];
					$dirs = apply_filters('updraftplus_dirlist_'.$entity, $basedir);
					$size = $this->recursive_directory_size($dirs, $updraftplus->get_exclude($entity), $basedir, 'numeric');
					if (is_numeric($size) && $size>0) $total_size += $size;
				}
				return $updraftplus->convert_numeric_size_to_text($total_size);
			} elseif (!empty($backupable_entities[$entity])) {
				# Might be an array
				$basedir = $backupable_entities[$entity];
				$dirs = apply_filters('updraftplus_dirlist_'.$entity, $basedir);
				return $this->recursive_directory_size($dirs, $updraftplus->get_exclude($entity), $basedir);
			}
		}
		return __('Error', 'updraftplus');
	}
	
	public function activejobs_delete($job_id) {
			
		if (preg_match("/^[0-9a-f]{12}$/", $job_id)) {
		
			global $updraftplus;
			$cron = get_option('cron');
			$found_it = false;
		
			$updraft_dir = $updraftplus->backups_dir_location();
			if (file_exists($updraft_dir.'/log.'.$job_id.'.txt')) touch($updraft_dir.'/deleteflag-'.$job_id.'.txt');
			
			foreach ($cron as $time => $job) {
				if (isset($job['updraft_backup_resume'])) {
					foreach ($job['updraft_backup_resume'] as $hook => $info) {
						if (isset($info['args'][1]) && $info['args'][1] == $job_id) {
							$args = $cron[$time]['updraft_backup_resume'][$hook]['args'];
							wp_unschedule_event($time, 'updraft_backup_resume', $args);
							if (!$found_it) return array('ok' => 'Y', 'c' => 'deleted', 'm' => __('Job deleted', 'updraftplus'));
							$found_it = true;
						}
					}
				}
			}
		}

		if (!$found_it) return array('ok' => 'N', 'c' => 'not_found', 'm' => __('Could not find that job - perhaps it has already finished?', 'updraftplus'));

	}

	// Input: an array of items
	// Each item is in the format: <base>,<timestamp>,<type>(,<findex>)
	// The 'base' is not for us: we just pass it straight back
	public function get_download_statuses($downloaders) {
		global $updraftplus;
		$download_status = array();
		foreach ($downloaders as $downloader) {
			# prefix, timestamp, entity, index
			if (preg_match('/^([^,]+),(\d+),([-a-z]+|db[0-9]+),(\d+)$/', $downloader, $matches)) {
				$findex = (empty($matches[4])) ? '0' : $matches[4];
				$updraftplus->nonce = dechex($matches[2]).$findex.substr(md5($matches[3]), 0, 3);
				$updraftplus->jobdata_reset();
				$status = $this->download_status($matches[2], $matches[3], $matches[4]);
				if (is_array($status)) {
					$status['base'] = $matches[1];
					$status['timestamp'] = $matches[2];
					$status['what'] = $matches[3];
					$status['findex'] = $findex;
					$download_status[] = $status;
				}
			}
		}
		return $download_status;
	}
	
	public function get_activejobs_list($request) {
	
		global $updraftplus;
		
		$download_status = empty($request['downloaders']) ? array(): $this->get_download_statuses(explode(':', $request['downloaders']));

		if (!empty($request['oneshot'])) {
			$job_id = get_site_option('updraft_oneshotnonce', false);
			// print_active_job() for one-shot jobs that aren't in cron
			$active_jobs = (false === $job_id) ? '' : $this->print_active_job($job_id, true);
		} elseif (!empty($request['thisjobonly'])) {
			// print_active_jobs() is for resumable jobs where we want the cron info to be included in the output
			$active_jobs = $this->print_active_jobs($request['thisjobonly']);
		} else {
			$active_jobs = $this->print_active_jobs();
		}

		$logupdate_array = array();
		if (!empty($request['log_fetch'])) {
			if (isset($request['log_nonce'])) {
				$log_nonce = $request['log_nonce'];
				$log_pointer = isset($request['log_pointer']) ? absint($request['log_pointer']) : 0;
				$logupdate_array = $this->fetch_log($log_nonce, $log_pointer);
			}
		}

		return array(
			// We allow the front-end to decide what to do if there's nothing logged - we used to (up to 1.11.29) send a pre-defined message
			'l' => htmlspecialchars(UpdraftPlus_Options::get_updraft_option('updraft_lastmessage', '')),
			'j' => $active_jobs,
			'ds' => $download_status,
			'u' => $logupdate_array
		);
	
	}
	
	public function request_backupnow($request, $close_connection_callable = false) {
		global $updraftplus;
		
		$backupnow_nocloud = (empty($request['backupnow_nocloud'])) ? false : true;
		$event = (!empty($request['backupnow_nofiles'])) ? 'updraft_backupnow_backup_database' : ((!empty($request['backupnow_nodb'])) ? 'updraft_backupnow_backup' : 'updraft_backupnow_backup_all');

		// The call to backup_time_nonce() allows us to know the nonce in advance, and return it
		$nonce = $updraftplus->backup_time_nonce();

		$msg = array(
			'nonce' => $nonce,
			'm' => '<strong>'.__('Start backup', 'updraftplus').':</strong> '.htmlspecialchars(__('OK. You should soon see activity in the "Last log message" field below.','updraftplus'))
		);
		
		if ($close_connection_callable && is_callable($close_connection_callable)) {
			call_user_func($close_connection_callable, $msg);
		} else {
			$updraftplus->close_browser_connection(json_encode($msg));
		}

		$options = array('nocloud' => $backupnow_nocloud, 'use_nonce' => $nonce);
		if (!empty($request['onlythisfileentity']) && is_string($request['onlythisfileentity'])) {
			// Something to see in the 'last log' field when it first appears, before the backup actually starts
			$updraftplus->log(__('Start backup','updraftplus'));
			$options['restrict_files_to_override'] = explode(',', $request['onlythisfileentity']);
		}

		if (!empty($request['extradata'])) {
			$options['extradata'] = $request['extradata'];
		}

		do_action($event, apply_filters('updraft_backupnow_options', $options, $request));
	}
	
	public function fetch_log($backup_nonce, $log_pointer=0) {
		global $updraftplus;
 
		if (empty($backup_nonce)) {
			list($mod_time, $log_file, $nonce) = $updraftplus->last_modified_log();
		} else {
			$nonce = $backup_nonce;
		}

		if (!preg_match('/^[0-9a-f]+$/', $nonce)) die('Security check');
		
		$log_content = '';
		$new_pointer = $log_pointer;
		
		if (!empty($nonce)) {
			$updraft_dir = $updraftplus->backups_dir_location();
			
			$potential_log_file = $updraft_dir."/log.".$nonce.".txt";

			if (is_readable($potential_log_file)){
				
				$templog_array = array();
				$log_file = fopen($potential_log_file, "r");
				if ($log_pointer > 0) fseek($log_file, $log_pointer);
				
				while (($buffer = fgets($log_file, 4096)) !== false) {
					$templog_array[] = $buffer;
				}
				if (!feof($log_file)) {
					$templog_array[] = __('Error: unexpected file read fail', 'updraftplus');
				}
				
				$new_pointer = ftell($log_file);
				$log_content = implode("", $templog_array);
				
			} else {
				$log_content .= __('The log file could not be read.','updraftplus');
			}

		} else {
			$log_content .= __('The log file could not be read.','updraftplus');
		}
		
		$ret_array = array(
			'html' => $log_content,
			'nonce' => $nonce,
			'pointer' => $new_pointer
		);
		
		return $ret_array;
	}

	public function howmany_overdue_crons() {
		$how_many_overdue = 0;
		if (function_exists('_get_cron_array') || (is_file(ABSPATH.WPINC.'/cron.php') && include_once(ABSPATH.WPINC.'/cron.php') && function_exists('_get_cron_array'))) {
			$crons = _get_cron_array();
			if (is_array($crons)) {
				$timenow = time();
				foreach ($crons as $jt => $job) {
					if ($jt < $timenow) {
						$how_many_overdue++;
					}
				}
			}
		}
		return $how_many_overdue;
	}

	public function get_php_errors($errno, $errstr, $errfile, $errline) {
		global $updraftplus;
		if (0 == error_reporting()) return true;
		$logline = $updraftplus->php_error_to_logline($errno, $errstr, $errfile, $errline);
		if (false !== $logline) $this->logged[] = $logline;
		# Don't pass it up the chain (since it's going to be output to the user always)
		return true;
	}

	private function download_status($timestamp, $type, $findex) {
		global $updraftplus;
		$response = array( 'm' => $updraftplus->jobdata_get('dlmessage_'.$timestamp.'_'.$type.'_'.$findex).'<br>' );
		if ($file = $updraftplus->jobdata_get('dlfile_'.$timestamp.'_'.$type.'_'.$findex)) {
			if ('failed' == $file) {
				$response['e'] = __('Download failed', 'updraftplus').'<br>';
				$response['failed'] = true;
				$errs = $updraftplus->jobdata_get('dlerrors_'.$timestamp.'_'.$type.'_'.$findex);
				if (is_array($errs) && !empty($errs)) {
					$response['e'] .= '<ul class="disc">';
					foreach ($errs as $err) {
						if (is_array($err)) {
							$response['e'] .= '<li>'.htmlspecialchars($err['message']).'</li>';
						} else {
							$response['e'] .= '<li>'.htmlspecialchars($err).'</li>';
						}
					}
					$response['e'] .= '</ul>';
				}
			} elseif (preg_match('/^downloaded:(\d+):(.*)$/', $file, $matches) && file_exists($matches[2])) {
				$response['p'] = 100;
				$response['f'] = $matches[2];
				$response['s'] = (int)$matches[1];
				$response['t'] = (int)$matches[1];
				$response['m'] = __('File ready.', 'updraftplus');
			} elseif (preg_match('/^downloading:(\d+):(.*)$/', $file, $matches) && file_exists($matches[2])) {
				// Convert to bytes
				$response['f'] = $matches[2];
				$total_size = (int)max($matches[1], 1);
				$cur_size = filesize($matches[2]);
				$response['s'] = $cur_size;
				$file_age = time() - filemtime($matches[2]);
				if ($file_age > 20) $response['a'] = time() - filemtime($matches[2]);
				$response['t'] = $total_size;
				$response['m'] .= __("Download in progress", 'updraftplus').' ('.round($cur_size/1024).' / '.round(($total_size/1024)).' KB)';
				$response['p'] = round(100*$cur_size/$total_size);
			} else {
				$response['m'] .= __('No local copy present.', 'updraftplus');
				$response['p'] = 0;
				$response['s'] = 0;
				$response['t'] = 1;
			}
		}
		return $response;
	}

	public function upload_dir($uploads) {
		global $updraftplus;
		$updraft_dir = $updraftplus->backups_dir_location();
		if (is_writable($updraft_dir)) $uploads['path'] = $updraft_dir;
		return $uploads;
	}

	// We do actually want to over-write
	public function unique_filename_callback($dir, $name, $ext) {
		return $name.$ext;
	}

	public function sanitize_file_name($filename) {
		// WordPress 3.4.2 on multisite (at least) adds in an unwanted underscore
		return preg_replace('/-db(.*)\.gz_\.crypt$/', '-db$1.gz.crypt', $filename);
	}

	public function plupload_action() {
		// check ajax nonce

		global $updraftplus;
		@set_time_limit(UPDRAFTPLUS_SET_TIME_LIMIT);

		if (!UpdraftPlus_Options::user_can_manage()) exit;
		check_ajax_referer('updraft-uploader');

		$updraft_dir = $updraftplus->backups_dir_location();
		if (!@$updraftplus->really_is_writable($updraft_dir)) {
			echo json_encode(array('e' => sprintf(__("Backup directory (%s) is not writable, or does not exist.", 'updraftplus'), $updraft_dir).' '.__('You will find more information about this in the Settings section.', 'updraftplus')));
			exit;
		}

		add_filter('upload_dir', array($this, 'upload_dir'));
		add_filter('sanitize_file_name', array($this, 'sanitize_file_name'));
		// handle file upload

		$farray = array('test_form' => true, 'action' => 'plupload_action');

		$farray['test_type'] = false;
		$farray['ext'] = 'x-gzip';
		$farray['type'] = 'application/octet-stream';

		if (!isset($_POST['chunks'])) {
			$farray['unique_filename_callback'] = array($this, 'unique_filename_callback');
		}

		$status = wp_handle_upload(
			$_FILES['async-upload'],
			$farray
		);
		remove_filter('upload_dir', array($this, 'upload_dir'));
		remove_filter('sanitize_file_name', array($this, 'sanitize_file_name'));

		if (isset($status['error'])) {
			echo json_encode(array('e' => $status['error']));
			exit;
		}

		// If this was the chunk, then we should instead be concatenating onto the final file
		if (isset($_POST['chunks']) && isset($_POST['chunk']) && preg_match('/^[0-9]+$/',$_POST['chunk'])) {
			$final_file = basename($_POST['name']);
			if (!rename($status['file'], $updraft_dir.'/'.$final_file.'.'.$_POST['chunk'].'.zip.tmp')) {
				@unlink($status['file']);
				echo json_encode(array('e' => sprintf(__('Error: %s', 'updraftplus'), __('This file could not be uploaded', 'updraftplus'))));
				exit;
			}
			$status['file'] = $updraft_dir.'/'.$final_file.'.'.$_POST['chunk'].'.zip.tmp';

			// Final chunk? If so, then stich it all back together
			if ($_POST['chunk'] == $_POST['chunks']-1) {
				if ($wh = fopen($updraft_dir.'/'.$final_file, 'wb')) {
					for ($i=0 ; $i<$_POST['chunks']; $i++) {
						$rf = $updraft_dir.'/'.$final_file.'.'.$i.'.zip.tmp';
						if ($rh = fopen($rf, 'rb')) {
							while ($line = fread($rh, 32768)) fwrite($wh, $line);
							fclose($rh);
							@unlink($rf);
						}
					}
					fclose($wh);
					$status['file'] = $updraft_dir.'/'.$final_file;
					if ('.tar' == substr($final_file, -4, 4)) {
						if (file_exists($status['file'].'.gz')) unlink($status['file'].'.gz');
						if (file_exists($status['file'].'.bz2')) unlink($status['file'].'.bz2');
					} elseif ('.tar.gz' == substr($final_file, -7, 7)) {
						if (file_exists(substr($status['file'], 0, strlen($status['file'])-3))) unlink(substr($status['file'], 0, strlen($status['file'])-3));
						if (file_exists(substr($status['file'], 0, strlen($status['file'])-3).'.bz2')) unlink(substr($status['file'], 0, strlen($status['file'])-3).'.bz2');
					} elseif ('.tar.bz2' == substr($final_file, -8, 8)) {
						if (file_exists(substr($status['file'], 0, strlen($status['file'])-4))) unlink(substr($status['file'], 0, strlen($status['file'])-4));
						if (file_exists(substr($status['file'], 0, strlen($status['file'])-4).'.gz')) unlink(substr($status['file'], 0, strlen($status['file'])-3).'.gz');
					}
				}
			}

		}

		$response = array();
		if (!isset($_POST['chunks']) || (isset($_POST['chunk']) && $_POST['chunk'] == $_POST['chunks']-1)) {
			$file = basename($status['file']);
			if (!preg_match('/^log\.[a-f0-9]{12}\.txt/i', $file) && !preg_match('/^backup_([\-0-9]{15})_.*_([0-9a-f]{12})-([\-a-z]+)([0-9]+)?(\.(zip|gz|gz\.crypt))?$/i', $file, $matches)) {
				$accept = apply_filters('updraftplus_accept_archivename', array());
				if (is_array($accept)) {
					foreach ($accept as $acc) {
						if (preg_match('/'.$acc['pattern'].'/i', $file)) $accepted = $acc['desc'];
					}
				}
				if (!empty($accepted)) {
					$response['dm'] = sprintf(__('This backup was created by %s, and can be imported.', 'updraftplus'), $accepted);
				} else {
					@unlink($status['file']);
					echo json_encode(array('e' => sprintf(__('Error: %s', 'updraftplus'),__('Bad filename format - this does not look like a file created by UpdraftPlus','updraftplus'))));
					exit;
				}
			} else {
				$backupable_entities = $updraftplus->get_backupable_file_entities(true);
				$type = isset($matches[3]) ? $matches[3] : '';
				if (!preg_match('/^log\.[a-f0-9]{12}\.txt/', $file) && 'db' != $type && !isset($backupable_entities[$type])) {
					@unlink($status['file']);
					echo json_encode(array('e' => sprintf(__('Error: %s', 'updraftplus'),sprintf(__('This looks like a file created by UpdraftPlus, but this install does not know about this type of object: %s. Perhaps you need to install an add-on?','updraftplus'), htmlspecialchars($type)))));
					exit;
				}
			}
		}

		// send the uploaded file url in response
		$response['m'] = $status['url'];
		echo json_encode($response);
		exit;
	}

	# Database decrypter
	public function plupload_action2() {

		@set_time_limit(UPDRAFTPLUS_SET_TIME_LIMIT);
		global $updraftplus;

		if (!UpdraftPlus_Options::user_can_manage()) exit;
		check_ajax_referer('updraft-uploader');

		$updraft_dir = $updraftplus->backups_dir_location();
		if (!is_writable($updraft_dir)) exit;

		add_filter('upload_dir', array($this, 'upload_dir'));
		add_filter('sanitize_file_name', array($this, 'sanitize_file_name'));
		// handle file upload

		$farray = array( 'test_form' => true, 'action' => 'plupload_action2' );

		$farray['test_type'] = false;
		$farray['ext'] = 'crypt';
		$farray['type'] = 'application/octet-stream';

		if (isset($_POST['chunks'])) {
// 			$farray['ext'] = 'zip';
// 			$farray['type'] = 'application/zip';
		} else {
			$farray['unique_filename_callback'] = array($this, 'unique_filename_callback');
		}

		$status = wp_handle_upload(
			$_FILES['async-upload'],
			$farray
		);
		remove_filter('upload_dir', array($this, 'upload_dir'));
		remove_filter('sanitize_file_name', array($this, 'sanitize_file_name'));

		if (isset($status['error'])) {
			echo 'ERROR:'.$status['error'];
			exit;
		}

		// If this was the chunk, then we should instead be concatenating onto the final file
		if (isset($_POST['chunks']) && isset($_POST['chunk']) && preg_match('/^[0-9]+$/',$_POST['chunk'])) {
			$final_file = basename($_POST['name']);
			rename($status['file'], $updraft_dir.'/'.$final_file.'.'.$_POST['chunk'].'.zip.tmp');
			$status['file'] = $updraft_dir.'/'.$final_file.'.'.$_POST['chunk'].'.zip.tmp';

			// Final chunk? If so, then stich it all back together
			if ($_POST['chunk'] == $_POST['chunks']-1) {
				if ($wh = fopen($updraft_dir.'/'.$final_file, 'wb')) {
					for ($i=0 ; $i<$_POST['chunks']; $i++) {
						$rf = $updraft_dir.'/'.$final_file.'.'.$i.'.zip.tmp';
						if ($rh = fopen($rf, 'rb')) {
							while ($line = fread($rh, 32768)) fwrite($wh, $line);
							fclose($rh);
							@unlink($rf);
						}
					}
					fclose($wh);
					$status['file'] = $updraft_dir.'/'.$final_file;
				}
			}

		}

		if (!isset($_POST['chunks']) || (isset($_POST['chunk']) && $_POST['chunk'] == $_POST['chunks']-1)) {
			$file = basename($status['file']);
			if (!preg_match('/^backup_([\-0-9]{15})_.*_([0-9a-f]{12})-db([0-9]+)?\.(gz\.crypt)$/i', $file)) {

				@unlink($status['file']);
				echo 'ERROR:'.__('Bad filename format - this does not look like an encrypted database file created by UpdraftPlus','updraftplus');

				exit;
			}
		}

		// send the uploaded file url in response
// 		echo 'OK:'.$status['url'];
		echo 'OK:'.$file;
		exit;
	}

	public function settings_header() {
		global $updraftplus;
		?>

	<div class="wrap" id="updraft-wrap">
		<h1><?php echo $updraftplus->plugin_title; ?></h1>

		<a href="https://updraftplus.com">UpdraftPlus.Com</a> | 
		<?php if (!defined('UPDRAFTPLUS_NOADS_B')) { ?><a href="https://updraftplus.com/shop/updraftplus-premium/"><?php _e("Premium",'updraftplus');?></a> | <?php } ?>
		<a href="https://updraftplus.com/news/"><?php _e('News','updraftplus');?></a>  | 
		<a href="https://twitter.com/updraftplus"><?php _e('Twitter', 'updraftplus');?></a> | 
		<a href="https://updraftplus.com/support/"><?php _e("Support",'updraftplus');?></a> | 
		<?php if (!is_file(UPDRAFTPLUS_DIR.'/udaddons/updraftplus-addons.php')) { ?><a href="https://updraftplus.com/newsletter-signup"><?php _e("Newsletter sign-up", 'updraftplus');?></a> | <?php } ?>
		<a href="http://david.dw-perspective.org.uk"><?php _e("Lead developer's homepage",'updraftplus');?></a> | 
		<a href="https://updraftplus.com/support/frequently-asked-questions/"><?php _e('FAQs', 'updraftplus'); ?></a> | <a href="https://www.simbahosting.co.uk/s3/shop/"><?php _e('More plugins', 'updraftplus');?></a> - <?php _e('Version','updraftplus');?>: <?php echo $updraftplus->version; ?>
		<br>
		<?php
	}

	public function settings_output() {

		if (false == ($render = apply_filters('updraftplus_settings_page_render', true))) {
			do_action('updraftplus_settings_page_render_abort', $render);
			return;
		}

		do_action('updraftplus_settings_page_init');

		global $updraftplus;

		/*
		we use request here because the initial restore is triggered by a POSTed form. we then may need to obtain credentials 
		for the WP_Filesystem. to do this WP outputs a form, but we don't pass our parameters via that. So the values are 
		passed back in as GET parameters.
		*/
		
		if (isset($_REQUEST['action']) && (($_REQUEST['action'] == 'updraft_restore' && isset($_REQUEST['backup_timestamp'])) || ('updraft_restore_continue' == $_REQUEST['action'] && !empty($_REQUEST['restoreid'])))) {

			$is_continuation = ('updraft_restore_continue' == $_REQUEST['action']) ? true : false;

			if ($is_continuation) {
				$restore_in_progress = get_site_option('updraft_restore_in_progress');
				if ($restore_in_progress != $_REQUEST['restoreid']) {
					$abort_restore_already = true;
					$updraftplus->log(__('Sufficient information about the in-progress restoration operation could not be found.', 'updraftplus').' (restoreid_mismatch)', 'error', 'restoreid_mismatch');
				} else {

					$restore_jobdata = $updraftplus->jobdata_getarray($restore_in_progress);
					if (is_array($restore_jobdata) && isset($restore_jobdata['job_type']) && 'restore' == $restore_jobdata['job_type'] && isset($restore_jobdata['second_loop_entities']) && !empty($restore_jobdata['second_loop_entities']) && isset($restore_jobdata['job_time_ms']) && isset($restore_jobdata['backup_timestamp'])) {
						$backup_timestamp = $restore_jobdata['backup_timestamp'];
						$continuation_data = $restore_jobdata;
					} else {
						$abort_restore_already = true;
						$updraftplus->log(__('Sufficient information about the in-progress restoration operation could not be found.', 'updraftplus').' (restoreid_nojobdata)', 'error', 'restoreid_nojobdata');
					}
				}

			} else {
				$backup_timestamp = $_REQUEST['backup_timestamp'];
				$continuation_data = null;
			}

			if (empty($abort_restore_already)) {
				$backup_success = $this->restore_backup($backup_timestamp, $continuation_data);
			} else {
				$backup_success = false;
			}

			if (empty($updraftplus->errors) && $backup_success === true) {
// TODO: Deal with the case of some of the work having been deferred
				// If we restored the database, then that will have out-of-date information which may confuse the user - so automatically re-scan for them.
				$updraftplus->rebuild_backup_history();
				echo '<p><strong>';
				$updraftplus->log_e('Restore successful!');
				echo '</strong></p>';
				$updraftplus->log("Restore successful");
				$s_val = 1;
				if (!empty($this->entities_to_restore) && is_array($this->entities_to_restore)) {
					foreach ($this->entities_to_restore as $k => $v) {
						if ('db' != $v) $s_val = 2;
					}
				}
				$pval = ($updraftplus->have_addons) ? 1 : 0;
				echo '<strong>'.__('Actions','updraftplus').':</strong> <a href="'.UpdraftPlus_Options::admin_page_url().'?page=updraftplus&updraft_restore_success='.$s_val.'&pval='.$pval.'">'.__('Return to UpdraftPlus Configuration','updraftplus').'</a>';
				return;
			} elseif (is_wp_error($backup_success)) {
				echo '<p>';
				$updraftplus->log_e('Restore failed...');
				echo '</p>';
				$updraftplus->log_wp_error($backup_success);
				$updraftplus->log("Restore failed");
				$updraftplus->list_errors();
				echo '<strong>'.__('Actions','updraftplus').':</strong> <a href="'.UpdraftPlus_Options::admin_page_url().'?page=updraftplus">'.__('Return to UpdraftPlus Configuration','updraftplus').'</a>';
				return;
			} elseif (false === $backup_success) {
				# This means, "not yet - but stay on the page because we may be able to do it later, e.g. if the user types in the requested information"
				echo '<p>';
				$updraftplus->log_e('Restore failed...');
				echo '</p>';
				$updraftplus->log("Restore failed");
				$updraftplus->list_errors();
				echo '<strong>'.__('Actions','updraftplus').':</strong> <a href="'.UpdraftPlus_Options::admin_page_url().'?page=updraftplus">'.__('Return to UpdraftPlus Configuration','updraftplus').'</a>';
				return;
			}
		}

		if (isset($_REQUEST['action']) && 'updraft_delete_old_dirs' == $_REQUEST['action']) {
			$nonce = (empty($_REQUEST['_wpnonce'])) ? "" : $_REQUEST['_wpnonce'];
			if (!wp_verify_nonce($nonce, 'updraftplus-credentialtest-nonce')) die('Security check');
			$this->delete_old_dirs_go();
			return;
		}

		if (!empty($_REQUEST['action']) && 'updraftplus_broadcastaction' == $_REQUEST['action'] && !empty($_REQUEST['subaction'])) {
			$nonce = (empty($_REQUEST['nonce'])) ? "" : $_REQUEST['nonce'];
			if (!wp_verify_nonce($nonce, 'updraftplus-credentialtest-nonce')) die('Security check');
			do_action($_REQUEST['subaction']);
			return;
		}

		if (isset($_GET['error'])) {
			// This is used by Microsoft OneDrive authorisation failures (May 15). I am not sure what may have been using the 'error' GET parameter otherwise - but it is harmless.
			if (!empty($_GET['error_description'])) {
				$this->show_admin_warning(htmlspecialchars($_GET['error_description']).' ('.htmlspecialchars($_GET['error']).')', 'error');
			} else {
				$this->show_admin_warning(htmlspecialchars($_GET['error']), 'error');
			}
		}

		if (isset($_GET['message'])) $this->show_admin_warning(htmlspecialchars($_GET['message']));

		if (isset($_GET['action']) && $_GET['action'] == 'updraft_create_backup_dir' && isset($_GET['nonce']) && wp_verify_nonce($_GET['nonce'], 'create_backup_dir')) {
			$created = $this->create_backup_dir();
			if (is_wp_error($created)) {
				echo '<p>'.__('Backup directory could not be created', 'updraftplus').'...<br/>';
				echo '<ul class="disc">';
				foreach ($created->get_error_messages() as $key => $msg) {
					echo '<li>'.htmlspecialchars($msg).'</li>';
				}
				echo '</ul></p>';
			} elseif ($created !== false) {
				echo '<p>'.__('Backup directory successfully created.', 'updraftplus').'</p><br/>';
			}
			echo '<b>'.__('Actions','updraftplus').':</b> <a href="'.UpdraftPlus_Options::admin_page_url().'?page=updraftplus">'.__('Return to UpdraftPlus Configuration', 'updraftplus').'</a>';
			return;
		}

		echo '<div id="updraft_backup_started" class="updated updraft-hidden" style="display:none;"></div>';

		if (isset($_POST['action']) && 'updraft_backup_debug_all' == $_POST['action']) {
			$updraftplus->boot_backup(true,true);
		} elseif (isset($_POST['action']) && 'updraft_backup_debug_db' == $_POST['action']) {
			$updraftplus->boot_backup(false, true, false, true);
		} elseif (isset($_POST['action']) && 'updraft_wipesettings' == $_POST['action']) {
			$settings = $updraftplus->get_settings_keys();
			foreach ($settings as $s) UpdraftPlus_Options::delete_updraft_option($s);

			// These aren't in get_settings_keys() because they are always in the options table, regardless of context
			global $wpdb;
			$wpdb->query("DELETE FROM $wpdb->options WHERE ( option_name LIKE 'updraftplus_unlocked_%' OR option_name LIKE 'updraftplus_locked_%' OR option_name LIKE 'updraftplus_last_lock_time_%' OR option_name LIKE 'updraftplus_semaphore_%' OR option_name LIKE 'updraft_jobdata_%' OR option_name LIKE 'updraft_last_scheduled_%' )");

			$site_options = array('updraft_oneshotnonce');
			foreach ($site_options as $s) delete_site_option($s);

			$this->show_admin_warning(__("Your settings have been wiped.", 'updraftplus'));
		}

		// This opens a div
		$this->settings_header();
		?>

			<div id="updraft-hidethis">
			<p>
			<strong><?php _e('Warning:', 'updraftplus'); ?> <?php _e("If you can still read these words after the page finishes loading, then there is a JavaScript or jQuery problem in the site.", 'updraftplus'); ?></strong>

			<?php if (false !== strpos(basename(UPDRAFTPLUS_URL), ' ')) { ?>
				<strong><?php _e('The UpdraftPlus directory in wp-content/plugins has white-space in it; WordPress does not like this. You should rename the directory to wp-content/plugins/updraftplus to fix this problem.', 'updraftplus');?></strong>
			<?php } else { ?>
				<a href="https://updraftplus.com/do-you-have-a-javascript-or-jquery-error/"><?php _e('Go here for more information.', 'updraftplus'); ?></a>
			<?php } ?>
			</p>
			</div>

			<?php

			$include_deleteform_div = true;

			// Opens a div, which needs closing later
			if (isset($_GET['updraft_restore_success'])) {
				$success_advert = (isset($_GET['pval']) && 0 == $_GET['pval'] && !$updraftplus->have_addons) ? '<p>'.__('For even more features and personal support, check out ','updraftplus').'<strong><a href="https://updraftplus.com/shop/updraftplus-premium/" target="_blank">UpdraftPlus Premium</a>.</strong></p>' : "";

				echo "<div class=\"updated backup-restored\"><span><strong>".__('Your backup has been restored.','updraftplus').'</strong></span><br>';
				// Unnecessary - will be advised of this below
// 				if (2 == $_GET['updraft_restore_success']) echo ' '.__('Your old (themes, uploads, plugins, whatever) directories have been retained with "-old" appended to their name. Remove them when you are satisfied that the backup worked properly.');
				echo $success_advert;
				$include_deleteform_div = false;

			}

// 			$this->print_restore_in_progress_box_if_needed();

			if ($this->scan_old_dirs(true)) $this->print_delete_old_dirs_form(true, $include_deleteform_div);

			// Close the div opened by the earlier section
			if (isset($_GET['updraft_restore_success'])) echo '</div>';

			$ws_advert = $updraftplus->wordshell_random_advert(1);
			if ($ws_advert && empty($success_advert) && empty($this->no_settings_warning)) { echo '<div class="updated ws_advert" style="clear:left;">'.$ws_advert.'</div>'; }

			if (!$updraftplus->memory_check(64)) {?>
				<div class="updated memory-limit"><?php _e("Your PHP memory limit (set by your web hosting company) is very low. UpdraftPlus attempted to raise it but was unsuccessful. This plugin may struggle with a memory limit of less than 64 Mb  - especially if you have very large files uploaded (though on the other hand, many sites will be successful with a 32Mb limit - your experience may vary).",'updraftplus');?> <?php _e('Current limit is:','updraftplus');?> <?php echo $updraftplus->memory_check_current(); ?> MB</div>
			<?php
			}


			if (!empty($updraftplus->errors)) {
				echo '<div class="error updraft_list_errors">';
				$updraftplus->list_errors();
				echo '</div>';
			}

			$backup_history = UpdraftPlus_Options::get_updraft_option('updraft_backup_history');
			if (empty($backup_history)) {
				$updraftplus->rebuild_backup_history();
				$backup_history = UpdraftPlus_Options::get_updraft_option('updraft_backup_history');
			}
			$backup_history = is_array($backup_history) ? $backup_history : array();
			?>

		<h2 class="nav-tab-wrapper">
		<?php
		$tabflag = 1;
		if (isset($_REQUEST['tab'])){
			switch($_REQUEST['tab']) {
				case 'status': $tabflag = 1; break;
				case 'backups': $tabflag = 2; break;
				case 'settings': $tabflag = 3; break;
				case 'expert': $tabflag = 4; break;
				case 'addons': $tabflag = 5; break;
				default : $tabflag = 1;
			}
		}

		?>
		<a class="nav-tab <?php if (1 == $tabflag) echo 'nav-tab-active'; ?>" id="updraft-navtab-status" href="#updraft-navtab-status-content" ><?php _e('Current Status', 'updraftplus');?>             </span></a>
		<a class="nav-tab <?php if (2 == $tabflag) echo 'nav-tab-active'; ?>" id="updraft-navtab-backups" href="#updraft-navtab-backups-contents" ><?php echo __('Existing Backups', 'updraftplus').' ('.count($backup_history).')';?>           </span></a>
		<a class="nav-tab <?php if (3 == $tabflag) echo 'nav-tab-active'; ?>" id="updraft-navtab-settings" href="#updraft-navtab-settings-content"><?php _e('Settings', 'updraftplus');?>                </span></a>
		<a class="nav-tab<?php if (4 == $tabflag) echo ' nav-tab-active'; ?>" id="updraft-navtab-expert" href="#updraft-navtab-expert-content"><?php _e('Advanced Tools', 'updraftplus');?>              </span></a>
		<a class="nav-tab<?php if (5 == $tabflag) echo ' nav-tab-active'; ?>" id="updraft-navtab-addons" href="#updraft-navtab-addons-content"><?php _e('Premium / Extensions', 'updraftplus');?>                </span></a>
 		<?php //do_action('updraftplus_settings_afternavtabs'); ?> 
		</h2>

		<?php
			$updraft_dir = $updraftplus->backups_dir_location();
			$backup_disabled = ($updraftplus->really_is_writable($updraft_dir)) ? '' : 'disabled="disabled"';
		?>
		
		<div id="updraft-poplog" >
			<pre id="updraft-poplog-content"></pre>
		</div>
		
		<div id="updraft-navtab-status-content" class="<?php if (1 != $tabflag) echo 'updraft-hidden'; ?>" style="<?php if (1 != $tabflag) echo 'display:none;'; ?>">

			<div id="updraft-insert-admin-warning"></div>

			<table class="form-table" style="float:left; clear:both;">
				<noscript>
				<tr>
					<th><?php _e('JavaScript warning','updraftplus');?>:</th>
					<td style="color:red"><?php _e('This admin interface uses JavaScript heavily. You either need to activate it within your browser, or to use a JavaScript-capable browser.','updraftplus');?></td>
				</tr>
				</noscript>

				<tr>
					<th></th>
					<td>

					<?php 
						if ($backup_disabled) {
							$unwritable_mess = htmlspecialchars(__("The 'Backup Now' button is disabled as your backup directory is not writable (go to the 'Settings' tab and find the relevant option).", 'updraftplus'));
							$this->show_admin_warning($unwritable_mess, "error");
						}
					?>
					<button id="updraft-backupnow-button" type="button" <?php echo $backup_disabled ?> class="updraft-bigbutton button-primary" <?php if ($backup_disabled) echo 'title="'.esc_attr(__('This button is disabled because your backup directory is not writable (see the settings).', 'updraftplus')).'" ';?> onclick="updraft_backup_dialog_open();"><?php _e('Backup Now', 'updraftplus');?></button>

					<button type="button" class="updraft-bigbutton button-primary" onclick="updraft_openrestorepanel();">
						<?php _e('Restore','updraftplus');?>
					</button>

					<button type="button" class="updraft-bigbutton button-primary" onclick="updraft_migrate_dialog_open();"><?php _e('Clone/Migrate','updraftplus');?></button>

					</td>
				</tr>

				<?php
					$last_backup_html = $this->last_backup_html(); 
					$current_time = get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'D, F j, Y H:i');
// 					$current_time = date_i18n('D, F j, Y H:i');

				?>

				<script>var lastbackup_laststatus = '<?php echo esc_js($last_backup_html);?>';</script>

				<tr>
					<th><span title="<?php esc_attr_e("All the times shown in this section are using WordPress's configured time zone, which you can set in Settings -> General", 'updraftplus'); ?>"><?php _e('Next scheduled backups', 'updraftplus');?>:<br>
					<span style="font-weight:normal;"><em><?php _e('Now', 'updraftplus');?>: <?php echo $current_time; ?></span></span></em></th>
					<td>
						<table id="next-backup-table-inner" class="next-backup">
							<?php $this->next_scheduled_backups_output(); ?>
						</table>
					</td>
				</tr>
				
				<tr>
					<th><?php _e('Last backup job run:','updraftplus');?></th>
					<td id="updraft_last_backup"><?php echo $last_backup_html ?></td>
				</tr>
			</table>

			<br style="clear:both;" />

			<?php $this->render_active_jobs_and_log_table(); ?>

			<div id="updraft-migrate-modal" title="<?php _e('Migrate Site', 'updraftplus'); ?>" style="display:none;">
				<?php
					if (class_exists('UpdraftPlus_Addons_Migrator')) {
						do_action('updraftplus_migrate_modal_output');
					} else {
						echo '<p id="updraft_migrate_modal_main">'.__('Do you want to migrate or clone/duplicate a site?', 'updraftplus').'</p><p>'.__('Then, try out our "Migrator" add-on. After using it once, you\'ll have saved the purchase price compared to the time needed to copy a site by hand.', 'updraftplus').'</p><p><a href="https://updraftplus.com/landing/migrator">'.__('Get it here.', 'updraftplus').'</a></p>';
					}
				?>
			</div>

			<div id="updraft-iframe-modal">
				<div id="updraft-iframe-modal-innards">
				</div>
			</div>

			<div id="updraft-backupnow-modal" title="UpdraftPlus - <?php _e('Perform a one-time backup', 'updraftplus'); ?>">
<!--				<p>
					<?php _e("To proceed, press 'Backup Now'. Then, watch the 'Last Log Message' field for activity.", 'updraftplus');?>
				</p>-->

			<?php echo $this->backupnow_modal_contents(); ?>
			</div>

			<?php
			if (is_multisite() && !file_exists(UPDRAFTPLUS_DIR.'/addons/multisite.php')) {
				?>
				<h2>UpdraftPlus <?php _e('Multisite','updraftplus');?></h2>
				<table>
				<tr>
				<td>
				<p class="multisite-advert-width"><?php echo __('Do you need WordPress Multisite support?','updraftplus').' <a href="https://updraftplus.com/shop/updraftplus-premium/">'. __('Please check out UpdraftPlus Premium, or the stand-alone Multisite add-on.','updraftplus');?></a>.</p>
				</td>
				</tr>
				</table>
			<?php } ?>
			
		</div>
		
		
		
		<div id="updraft-navtab-backups-content" <?php if (2 != $tabflag) echo 'class="updraft-hidden"'; ?> style="<?php if (2 != $tabflag) echo 'display:none;'; ?>">
			<?php
				$is_opera = (false !== strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') || false !== strpos($_SERVER['HTTP_USER_AGENT'], 'OPR/'));
				$tmp_opts = array('include_opera_warning' => $is_opera);
				$this->settings_downloading_and_restoring($backup_history, false, $tmp_opts);
				$this->settings_delete_and_restore_modals();
			?>
		</div>

		<div id="updraft-navtab-settings-content" <?php if (3 != $tabflag) echo 'class="updraft-hidden"'; ?> style="<?php if (3 != $tabflag) echo 'display:none;'; ?>">
			<h2 class="updraft_settings_sectionheading"><?php _e('Backup Contents And Schedule','updraftplus');?></h2>
			<?php UpdraftPlus_Options::options_form_begin(); ?>
				<?php $this->settings_formcontents(); ?>
			</form>
		</div>

		<div id="updraft-navtab-expert-content"<?php if (4 != $tabflag) echo ' class="updraft-hidden"'; ?> style="<?php if (4 != $tabflag) echo 'display:none;'; ?>">
			<?php $this->settings_expertsettings($backup_disabled); ?>
		</div>

		<div id="updraft-navtab-addons-content"<?php if (5 != $tabflag) echo ' class="updraft-hidden"'; ?> style="<?php if (5 != $tabflag) echo 'display:none;'; ?>">

		<?php
			$tick = UPDRAFTPLUS_URL.'/images/updraft_tick.png';
			$cross = UPDRAFTPLUS_URL.'/images/updraft_cross.png';
			$freev = UPDRAFTPLUS_URL.'/images/updraft_freev.png';
			$premv = UPDRAFTPLUS_URL.'/images/updraft_premv.png';
			
			ob_start();
			?>
			<div>
				<h2>UpdraftPlus Premium</h2>
				<p>
					<span class="premium-upgrade-prompt"><?php _e('You are currently using the free version of UpdraftPlus from wordpress.org.', 'updraftplus');?> <a href="https://updraftplus.com/support/installing-updraftplus-premium-your-add-on/"><br><?php echo __('If you have made a purchase from UpdraftPlus.Com, then follow this link to the instructions to install your purchase.', 'updraftplus').' '.__('The first step is to de-install the free version.', 'updraftplus')?></a></span>
					<ul class="updraft_premium_description_list">
						<li><a href="https://updraftplus.com/shop/updraftplus-premium/"><strong><?php _e('Get UpdraftPlus Premium', 'updraftplus');?></strong></a></li>
						<li><a href="https://updraftplus.com/updraftplus-full-feature-list/"><?php _e('Full feature list', 'updraftplus');?></a></li>
						<li><a href="https://updraftplus.com/faq-category/general-and-pre-sales-questions/"><?php _e('Pre-sales FAQs', 'updraftplus');?></a></li>
						<li class="last"><a href="https://updraftplus.com/ask-a-pre-sales-question/"><?php _e('Ask a pre-sales question', 'updraftplus');?></a> - <a href="https://updraftplus.com/support/"><?php _e('Support', 'updraftplus');?></a></li>
					</ul> 
				</p>
			</div>
			<div>
				<table class="updraft_feat_table">
					<tr>
						<th class="updraft_feat_th" style="text-align:left;"></th>
						<th class="updraft_feat_th"><img src="<?php echo $freev;?>" height="120"></th>
						<th class="updraft_feat_th" style='background-color:#DF6926;'><a href="https://updraftplus.com/shop/updraftplus-premium/"><img src="<?php echo $premv;?>"  height="120"></a></th>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Get it from', 'updraftplus');?></td>
						<td class="updraft_tick_cell" style="vertical-align:top; line-height: 120%; margin-top:6px; padding-top:6px;">WordPress.Org</td>
						<td class="updraft_tick_cell" style="padding: 6px; line-height: 120%;">
							UpdraftPlus.Com<br>
							<a href="https://updraftplus.com/shop/updraftplus-premium/"><strong><?php _e('Buy It Now!', 'updraftplus');?></strong></a><br>
							</td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Backup WordPress files and database', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php echo sprintf(__('Translated into over %s languages', 'updraftplus'), 16);?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Restore from backup', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Backup to remote storage', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Dropbox, Google Drive, FTP, S3, Rackspace, Email', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('WebDAV, Copy.Com, SFTP/SCP, encrypted FTP', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $cross;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Microsoft OneDrive, Microsoft Azure, Google Cloud Storage', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $cross;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Free 1GB for UpdraftPlus Vault', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $cross;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Backup extra files and databases', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $cross;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Migrate / clone (i.e. copy) websites', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $cross;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Basic email reporting', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Advanced reporting features', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $cross;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Automatic backup when updating WP/plugins/themes', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $cross;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Send backups to multiple remote destinations', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $cross;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Database encryption', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $cross;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Restore backups from other plugins', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $cross;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('No advertising links on UpdraftPlus settings page', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $cross;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Scheduled backups', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Fix backup time', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $cross;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Network/Multisite support', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $cross;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Lock settings access', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $cross;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
					<tr>
						<td class="updraft_feature_cell"><?php _e('Personal support', 'updraftplus');?></td>
						<td class="updraft_tick_cell"><img src="<?php echo $cross;?>"></td>
						<td class="updraft_tick_cell"><img src="<?php echo $tick;?>"></td>
					</tr>
				</table>
				</div>
			<?php
			
			echo apply_filters('updraftplus_addonstab_content', ob_get_clean());

			// Close addons tab
			echo '</div>';

		// settings_header() opens a div
		echo '</div>';
	}

	private function print_restore_in_progress_box_if_needed() {
		$restore_in_progress = get_site_option('updraft_restore_in_progress');
		if (!empty($restore_in_progress)) {
			global $updraftplus;
			$restore_jobdata = $updraftplus->jobdata_getarray($restore_in_progress);
			if (is_array($restore_jobdata) && !empty($restore_jobdata)) {
				// Only print if within the last 24 hours; and only after 2 minutes
				if (isset($restore_jobdata['job_type']) && 'restore' == $restore_jobdata['job_type'] && isset($restore_jobdata['second_loop_entities']) && !empty($restore_jobdata['second_loop_entities']) && isset($restore_jobdata['job_time_ms']) && (time() - $restore_jobdata['job_time_ms'] > 120 || (defined('UPDRAFTPLUS_RESTORE_PROGRESS_ALWAYS_SHOW') && UPDRAFTPLUS_RESTORE_PROGRESS_ALWAYS_SHOW)) && time() - $restore_jobdata['job_time_ms'] < 86400 && (empty($_REQUEST['action']) || ('updraft_restore' != $_REQUEST['action'] && 'updraft_restore_continue' != $_REQUEST['action']))) {
					$restore_jobdata['jobid'] = $restore_in_progress;
					$this->restore_in_progress_jobdata = $restore_jobdata;
					add_action('all_admin_notices', array($this, 'show_admin_restore_in_progress_notice') );
				}
			}
		}
	}

	public function show_admin_restore_in_progress_notice() {
	
		if (isset($_REQUEST['action']) && 'updraft_restore_abort' == $_REQUEST['action'] && !empty($_REQUEST['restoreid'])) {
			delete_site_option('updraft_restore_in_progress');
			return;
		}
	
		$restore_jobdata = $this->restore_in_progress_jobdata;
		$seconds_ago = time() - (int)$restore_jobdata['job_time_ms'];
		$minutes_ago = floor($seconds_ago/60);
		$seconds_ago = $seconds_ago - $minutes_ago*60;
		$time_ago = sprintf(__("%s minutes, %s seconds", 'updraftplus'), $minutes_ago, $seconds_ago);
		?><div class="updated show_admin_restore_in_progress_notice">
			<span class="unfinished-restoration"><strong><?php echo 'UpdraftPlus: '.__('Unfinished restoration', 'updraftplus'); ?> </strong></span><br>
			<p><?php printf(__('You have an unfinished restoration operation, begun %s ago.', 'updraftplus'), $time_ago);?></p>
		<form method="post" action="<?php echo UpdraftPlus_Options::admin_page_url().'?page=updraftplus'; ?>">
			<?php wp_nonce_field('updraftplus-credentialtest-nonce'); ?>
			<input id="updraft_restore_continue_action" type="hidden" name="action" value="updraft_restore_continue">
			<input type="hidden" name="restoreid" value="<?php echo $restore_jobdata['jobid'];?>" value="<?php echo esc_attr($restore_jobdata['jobid']);?>">
			<button onclick="jQuery('#updraft_restore_continue_action').val('updraft_restore_continue'); jQuery(this).parent('form').submit();" type="submit" class="button-primary"><?php _e('Continue restoration', 'updraftplus'); ?></button>
			<button onclick="jQuery('#updraft_restore_continue_action').val('updraft_restore_abort'); jQuery(this).parent('form').submit();" class="button-secondary"><?php _e('Dismiss', 'updraftplus');?></button>
		</form><?php
		echo "</div>";

	}

	public function backupnow_modal_contents() {
	
		$ret = $this->backup_now_widgetry();

// 		$ret .= '<p>'.__('Does nothing happen when you attempt backups?','updraftplus').' <a href="https://updraftplus.com/faqs/my-scheduled-backups-and-pressing-backup-now-does-nothing-however-pressing-debug-backup-does-produce-a-backup/">'.__('Go here for help.', 'updraftplus').'</a></p>';

		return $ret;
	}
	
	private function backup_now_widgetry() {

		$ret = '';

		$ret .= '<p>
			<input type="checkbox" id="backupnow_includedb" checked="checked"> <label for="backupnow_includedb">'.__("Include the database in the backup", 'updraftplus').'</label><br>';
			
		$ret .= '<input type="checkbox" id="backupnow_includefiles" checked="checked"> <label for="backupnow_includefiles">'.__("Include any files in the backup", 'updraftplus').'</label> (<a href="#" id="backupnow_includefiles_showmoreoptions">...</a>)<br>';

		$ret .= '<div id="backupnow_includefiles_moreoptions" class="updraft-hidden" style="display:none;"><em>'.__('Your saved settings also affect what is backed up - e.g. files excluded.', 'updraftplus').'</em><br>'.$this->files_selector_widgetry('backupnow_files_', false, 'sometimes').'</div>';
		
		$ret .= '<span id="backupnow_remote_container">'.$this->backup_now_remote_message().'</span>';

		$ret .= '</p>';

		$ret .= apply_filters('updraft_backupnow_modal_afteroptions', '', '');

		return $ret;
	}

	// Also used by the auto-backups add-on
	public function render_active_jobs_and_log_table($wide_format = false, $print_active_jobs = true) {
		?>
			<table class="form-table" id="updraft_activejobs_table">

				<?php $active_jobs = ($print_active_jobs) ? $this->print_active_jobs() : '';?>
				<tr id="updraft_activejobsrow" class="<?php
					if (!$active_jobs && !$wide_format) { echo 'hidden'; }
					if ($wide_format) { echo ".minimum-height"; }
				?>">
					<?php if ($wide_format) { ?>
						<td id="updraft_activejobs" colspan="2">
							<?php echo $active_jobs;?>
						</td>
					<?php } else { ?>
						<th><?php _e('Backups in progress:', 'updraftplus');?></th>
						<td id="updraft_activejobs"><?php echo $active_jobs;?></td>
					<?php } ?>
				</tr>

				<tr id="updraft_lastlogmessagerow">
					<?php if ($wide_format) {
						// Hide for now - too ugly
						?>
						<td colspan="2" class="last-message"><strong><?php _e('Last log message','updraftplus');?>:</strong><br>
							<span id="updraft_lastlogcontainer"><?php echo htmlspecialchars(UpdraftPlus_Options::get_updraft_option('updraft_lastmessage', __('(Nothing yet logged)','updraftplus'))); ?></span><br>
							<?php $this->most_recently_modified_log_link(); ?>
						</td>
					<?php } else { ?>
						<th><?php _e('Last log message','updraftplus');?>:</th>
						<td>
							<span id="updraft_lastlogcontainer"><?php echo htmlspecialchars(UpdraftPlus_Options::get_updraft_option('updraft_lastmessage', __('(Nothing yet logged)','updraftplus'))); ?></span><br>
							<?php $this->most_recently_modified_log_link(); ?>
						</td>
					<?php } ?>
				</tr>

				<?php
				# Currently disabled - not sure who we want to show this to
				if (1==0 && !defined('UPDRAFTPLUS_NOADS_B')) {
					$feed = $updraftplus->get_updraftplus_rssfeed();
					if (is_a($feed, 'SimplePie')) {
						echo '<tr><th style="vertical-align:top;">'.__('Latest UpdraftPlus.com news:', 'updraftplus').'</th><td class="updraft_simplepie">';
						echo '<ul class="disc;">';
						foreach ($feed->get_items(0, 5) as $item) {
							echo '<li>';
							echo '<a href="'.esc_attr($item->get_permalink()).'">';
							echo htmlspecialchars($item->get_title());
							# D, F j, Y H:i
							echo "</a> (".htmlspecialchars($item->get_date('j F Y')).")";
							echo '</li>';
						}
						echo '</ul></td></tr>';
					}
				}
			?>
			</table>
		<?php
	}

	private function most_recently_modified_log_link() {

		global $updraftplus;
		list($mod_time, $log_file, $nonce) = $updraftplus->last_modified_log();
		
		?>
			<a href="?page=updraftplus&amp;action=downloadlatestmodlog&amp;wpnonce=<?php echo wp_create_nonce('updraftplus_download') ?>" <?php if (!$mod_time) echo 'style="display:none;"'; ?> class="updraft-log-link" onclick="event.preventDefault(); updraft_popuplog('');"><?php _e('Download most recently modified log file', 'updraftplus');?></a>
		<?php
	}
	
	public function settings_downloading_and_restoring($backup_history = array(), $return_result = false, $options = array()) {
		global $updraftplus;
		if ($return_result) ob_start();
		
		$default_options = array(
			'include_uploader' => true,
			'include_opera_warning' => false,
			'will_immediately_calculate_disk_space' => true,
			'include_whitespace_warning' => true,
			'include_header' => false,
		);
		
		foreach ($default_options as $k => $v) {
			if (!isset($options[$k])) $options[$k] = $v;
		}

		if (false === $backup_history) $backup_history = UpdraftPlus_Options::get_updraft_option('updraft_backup_history');
		if (!is_array($backup_history)) $backup_history=array();
		
		if (!empty($options['include_header'])) echo '<h2>'.__('Existing Backups', 'updraftplus').' ('.count($backup_history).')</h2>';
		
		?>
		<div class="download-backups form-table">
			<?php /* echo '<h2>'.__('Existing Backups: Downloading And Restoring', 'updraftplus').'</h2>'; */ ?>
			<?php if (!empty($options['include_whitespace_warning'])) { ?>
				<p class="ud-whitespace-warning updraft-hidden" style="display:none;">
					<?php echo '<strong>'.__('Warning','updraftplus').':</strong> '.__('Your WordPress installation has a problem with outputting extra whitespace. This can corrupt backups that you download from here.','updraftplus').' <a href="https://updraftplus.com/problems-with-extra-white-space/">'.__('Please consult this FAQ for help on what to do about it.', 'updraftplus').'</a>';?>
				</p>
			<?php } ?>

			<ul>
				<li title="<?php esc_attr_e('This is a count of the contents of your Updraft directory', 'updraftplus');?>"><strong><?php _e('Web-server disk space in use by UpdraftPlus', 'updraftplus');?>:</strong> <span class="updraft_diskspaceused"><em><?php echo empty($options['will_immediately_calculate_disk_space']) ? '' : __('calculating...', 'updraftplus'); ?></em></span> <a class="updraft_diskspaceused_update" href="#"><?php echo empty($options['will_immediately_calculate_disk_space']) ? __('calculate', 'updraftplus') : __('refresh','updraftplus');?></a></li>

				<li>
					<strong><?php _e('More tasks:', 'updraftplus');?></strong>
					<?php if (!empty($options['include_uploader'])) { ?><a class="updraft_uploader_toggle" href="#"><?php _e('Upload backup files', 'updraftplus');?></a> | <?php } ?>
					<a href="#" class="updraft_rescan_local" title="<?php echo __('Press here to look inside your UpdraftPlus directory (in your web hosting space) for any new backup sets that you have uploaded.', 'updraftplus').' '.__('The location of this directory is set in the expert settings, in the Settings tab.', 'updraftplus'); ?>"><?php _e('Rescan local folder for new backup sets', 'updraftplus');?></a>
					| <a href="#" class="updraft_rescan_remote" title="<?php _e('Press here to look inside your remote storage methods for any existing backup sets (from any site, if they are stored in the same folder).', 'updraftplus'); ?>"><?php _e('Rescan remote storage','updraftplus');?></a>
				</li>
				<?php if (!empty($options['include_opera_warning'])) { ?>
					<li><strong><?php _e('Opera web browser', 'updraftplus');?>:</strong> <?php _e('If you are using this, then turn Turbo/Road mode off.', 'updraftplus');?></li>
				<?php } ?>

			</ul>

			<?php if (!empty($options['include_uploader'])) { ?>
			
				<div id="updraft-plupload-modal" style="display:none;" title="<?php _e('UpdraftPlus - Upload backup files','updraftplus'); ?>">
					<p class="upload"><em><?php _e("Upload files into UpdraftPlus." ,'updraftplus');?> <?php echo htmlspecialchars(__('Or, you can place them manually into your UpdraftPlus directory (usually wp-content/updraft), e.g. via FTP, and then use the "rescan" link above.', 'updraftplus'));?></em></p>
					<?php
					global $wp_version;
					if (version_compare($wp_version, '3.3', '<')) {
						echo '<em>'.sprintf(__('This feature requires %s version %s or later', 'updraftplus'), 'WordPress', '3.3').'</em>';
					} else {
						?>
						<div id="plupload-upload-ui">
							<div id="drag-drop-area">
								<div class="drag-drop-inside">
								<p class="drag-drop-info"><?php _e('Drop backup files here', 'updraftplus'); ?></p>
								<p><?php _ex('or', 'Uploader: Drop backup files here - or - Select Files'); ?></p>
								<p class="drag-drop-buttons"><input id="plupload-browse-button" type="button" value="<?php esc_attr_e('Select Files'); ?>" class="button" /></p>
								</div>
							</div>
							<div id="filelist">
							</div>
						</div>
						<?php 
					}
					?>
				</div>
			<?php } ?>

			<div class="ud_downloadstatus"></div>
			<div class="updraft_existing_backups">
				<?php echo $this->existing_backup_table($backup_history); ?>
			</div>

		</div>
		<?php
		if ($return_result) return ob_get_clean();
	}

	public function settings_delete_and_restore_modals($return_result = false) {
		global $updraftplus;
		if ($return_result) ob_start();
		?>
		
		<div id="ud_massactions" class="updraft-hidden" style="display:none;">
			<strong><?php _e('Actions upon selected backups', 'updraftplus');?></strong> <br>
			<div class="updraftplus-remove" style="float: left;"><a href="#" onclick="updraft_deleteallselected(); return false;"><?php _e('Delete', 'updraftplus');?></a></div>
			<div class="updraft-viewlogdiv"><a href="#" onclick="jQuery('#updraft-navtab-backups-content .updraft_existing_backups .updraft_existing_backups_row').addClass('backuprowselected'); return false;"><?php _e('Select all', 'updraftplus');?></a></div>
			<div class="updraft-viewlogdiv"><a href="#" onclick="jQuery('#updraft-navtab-backups-content .updraft_existing_backups .updraft_existing_backups_row').removeClass('backuprowselected'); jQuery('#ud_massactions').hide(); return false;"><?php _e('Deselect', 'updraftplus');?></a></div>
		</div>
		
		<div id="updraft-message-modal" title="UpdraftPlus">
			<div id="updraft-message-modal-innards">
			</div>
		</div>

		<div id="updraft-delete-modal" title="<?php _e('Delete backup set', 'updraftplus');?>">
			<form id="updraft_delete_form" method="post">
				<p id="updraft_delete_question_singular">
					<?php echo sprintf(__('Are you sure that you wish to remove %s from UpdraftPlus?', 'updraftplus'), __('this backup set', 'updraftplus')); ?>
				</p>
				<p id="updraft_delete_question_plural" class="updraft-hidden" style="display:none;">
					<?php echo sprintf(__('Are you sure that you wish to remove %s from UpdraftPlus?', 'updraftplus'), __('these backup sets', 'updraftplus')); ?>
				</p>
				<fieldset>
					<input type="hidden" name="nonce" value="<?php echo wp_create_nonce('updraftplus-credentialtest-nonce');?>">
					<input type="hidden" name="action" value="updraft_ajax">
					<input type="hidden" name="subaction" value="deleteset">
					<input type="hidden" name="backup_timestamp" value="0" id="updraft_delete_timestamp">
					<input type="hidden" name="backup_nonce" value="0" id="updraft_delete_nonce">
					<div id="updraft-delete-remote-section"><input checked="checked" type="checkbox" name="delete_remote" id="updraft_delete_remote" value="1"> <label for="updraft_delete_remote"><?php _e('Also delete from remote storage', 'updraftplus');?></label><br>
						<p id="updraft-delete-waitwarning" class="updraft-hidden" style="display:none;"><em><?php _e('Deleting... please allow time for the communications with the remote storage to complete.', 'updraftplus');?></em></p>
					</div>
				</fieldset>
			</form>
		</div>

		<div id="updraft-restore-modal" title="UpdraftPlus - <?php _e('Restore backup','updraftplus');?>">
			<p><strong><?php _e('Restore backup from','updraftplus');?>:</strong> <span class="updraft_restore_date"></span></p>

			<div id="updraft-restore-modal-stage2">

				<p><strong><?php _e('Retrieving (if necessary) and preparing backup files...', 'updraftplus');?></strong></p>
				<div id="ud_downloadstatus2"></div>

				<div id="updraft-restore-modal-stage2a"></div>
				
			</div>

			<div id="updraft-restore-modal-stage1">
				<p><?php _e("Restoring will replace this site's themes, plugins, uploads, database and/or other content directories (according to what is contained in the backup set, and your selection).",'updraftplus');?> <?php _e('Choose the components to restore','updraftplus');?>:</p>
				<form id="updraft_restore_form" method="post">
					<fieldset>
						<input type="hidden" name="action" value="updraft_restore">
						<input type="hidden" name="backup_timestamp" value="0" id="updraft_restore_timestamp">
						<input type="hidden" name="meta_foreign" value="0" id="updraft_restore_meta_foreign">
						<input type="hidden" name="updraft_restorer_backup_info" value="" id="updraft_restorer_backup_info">
						<input type="hidden" name="updraft_restorer_restore_options" value="" id="updraft_restorer_restore_options">
						<?php

						# The 'off' check is for badly configured setups - http://wordpress.org/support/topic/plugin-wp-super-cache-warning-php-safe-mode-enabled-but-safe-mode-is-off
						if ($updraftplus->detect_safe_mode()) {
							echo "<p><em>".__("Your web server has PHP's so-called safe_mode active.", 'updraftplus').' '.__('This makes time-outs much more likely. You are recommended to turn safe_mode off, or to restore only one entity at a time, <a href="https://updraftplus.com/faqs/i-want-to-restore-but-have-either-cannot-or-have-failed-to-do-so-from-the-wp-admin-console/">or to restore manually</a>.', 'updraftplus')."</em></p><br/>";
						}

							$backupable_entities = $updraftplus->get_backupable_file_entities(true, true);
							foreach ($backupable_entities as $type => $info) {
								if (!isset($info['restorable']) || $info['restorable'] == true) {
									echo '<div><input id="updraft_restore_'.$type.'" type="checkbox" name="updraft_restore[]" value="'.$type.'"> <label id="updraft_restore_label_'.$type.'" for="updraft_restore_'.$type.'">'.$info['description'].'</label><br>';

									do_action("updraftplus_restore_form_$type");

									echo '</div>';
								} else {
									$sdescrip = isset($info['shortdescription']) ? $info['shortdescription'] : $info['description'];
									echo "<div class=\"cannot-restore\"><em>".htmlspecialchars(sprintf(__('The following entity cannot be restored automatically: "%s".', 'updraftplus'), $sdescrip))." ".__('You will need to restore it manually.', 'updraftplus')."</em><br>".'<input id="updraft_restore_'.$type.'" type="hidden" name="updraft_restore[]" value="'.$type.'">';
									echo '</div>';
								}
							}
						?>
						<div><input id="updraft_restore_db" type="checkbox" name="updraft_restore[]" value="db"> <label for="updraft_restore_db"><?php _e('Database','updraftplus'); ?></label><br>

							<div id="updraft_restorer_dboptions" class="updraft-hidden" style="display:none;"><h4><?php echo sprintf(__('%s restoration options:','updraftplus'),__('Database','updraftplus')); ?></h4>

							<?php

							do_action("updraftplus_restore_form_db");

							if (!class_exists('UpdraftPlus_Addons_Migrator')) {

								echo '<a href="https://updraftplus.com/faqs/tell-me-more-about-the-search-and-replace-site-location-in-the-database-option/">'.__('You can search and replace your database (for migrating a website to a new location/URL) with the Migrator add-on - follow this link for more information','updraftplus').'</a>';

							}

							?>

							</div>

						</div>
					</fieldset>
				</form>
				<p><em><a href="https://updraftplus.com/faqs/what-should-i-understand-before-undertaking-a-restoration/" target="_blank"><?php _e('Do read this helpful article of useful things to know before restoring.','updraftplus');?></a></em></p>
			</div>
		</div>

		<?php
		if ($return_result) return ob_get_clean();
	}

	public function settings_debugrow($head, $content) {
		echo "<tr class=\"updraft_debugrow\"><th>$head</th><td>$content</td></tr>";
	}

	private function settings_expertsettings($backup_disabled) {
		global $updraftplus, $wpdb;
		$backupable_entities = $updraftplus->get_backupable_file_entities(true, true);
		?>
			<div class="expertmode">
				<p><em><?php _e('Unless you have a problem, you can completely ignore everything here.', 'updraftplus');?></em></p>
				<table>
				<?php

				// It appears (Mar 2015) that some mod_security distributions block the output of the string el6.x86_64 in PHP output, on the silly assumption that only hackers are interested in knowing what environment PHP is running on.
// 				$uname_info = @php_uname();
				$uname_info = @php_uname('s').' '.@php_uname('n').' ';

				$release_name = @php_uname('r');
				if (preg_match('/^(.*)\.(x86_64|[3456]86)$/', $release_name, $matches)) {
					$release_name = $matches[1].' ';
				} else {
					$release_name = '';
				}

				// In case someone does something similar with just the processor type string
				$mtype = @php_uname('m');
				if ('x86_64' == $mtype) {
					$mtype = '64-bit';
				} elseif (preg_match('/^i([3456]86)$/', $mtype, $matches)) {
					$mtype = $matches[1];
				}

				$uname_info .= $release_name.$mtype.' '.@php_uname('v');

				$this->settings_debugrow(__('Web server:','updraftplus'), htmlspecialchars($_SERVER["SERVER_SOFTWARE"]).' ('.htmlspecialchars($uname_info).')');

				$this->settings_debugrow('ABSPATH:', htmlspecialchars(ABSPATH));
				$this->settings_debugrow('WP_CONTENT_DIR:', htmlspecialchars(WP_CONTENT_DIR));
				$this->settings_debugrow('WP_PLUGIN_DIR:', htmlspecialchars(WP_PLUGIN_DIR));
				$this->settings_debugrow('Table prefix:', htmlspecialchars($updraftplus->get_table_prefix()));

				$peak_memory_usage = memory_get_peak_usage(true)/1024/1024;
				$memory_usage = memory_get_usage(true)/1024/1024;
				$this->settings_debugrow(__('Peak memory usage','updraftplus').':', $peak_memory_usage.' MB');
				$this->settings_debugrow(__('Current memory usage','updraftplus').':', $memory_usage.' MB');
				$this->settings_debugrow(__('Memory limit', 'updraftplus').':', htmlspecialchars(ini_get('memory_limit')));
				$this->settings_debugrow(sprintf(__('%s version:','updraftplus'), 'PHP'), htmlspecialchars(phpversion()).' - <a href="admin-ajax.php?page=updraftplus&action=updraft_ajax&subaction=phpinfo&nonce='.wp_create_nonce('updraftplus-credentialtest-nonce').'" id="updraftplus-phpinfo">'.__('show PHP information (phpinfo)', 'updraftplus').'</a>');
				$this->settings_debugrow(sprintf(__('%s version:','updraftplus'), 'MySQL'), htmlspecialchars($wpdb->db_version()));
				if (function_exists('curl_version') && function_exists('curl_exec')) {
					$cv = curl_version();
					$cvs = $cv['version'].' / SSL: '.$cv['ssl_version'].' / libz: '.$cv['libz_version'];
				} else {
					$cvs = __('Not installed', 'updraftplus').' ('.__('required for some remote storage providers', 'updraftplus').')';
				}
				$this->settings_debugrow(sprintf(__('%s version:', 'updraftplus'), 'Curl'), htmlspecialchars($cvs));
				$this->settings_debugrow(sprintf(__('%s version:', 'updraftplus'), 'OpenSSL'), defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : '-');
				$this->settings_debugrow('MCrypt:', function_exists('mcrypt_encrypt') ? __('Yes') : __('No'));
				
				if (version_compare(phpversion(), '5.2.0', '>=') && extension_loaded('zip')) {
					$ziparchive_exists = __('Yes', 'updraftplus');
				} else {
					# First do class_exists, because method_exists still sometimes segfaults due to a rare PHP bug
					$ziparchive_exists = (class_exists('ZipArchive') && method_exists('ZipArchive', 'addFile')) ? __('Yes', 'updraftplus') : __('No', 'updraftplus');
				}
				$this->settings_debugrow('ZipArchive::addFile:', $ziparchive_exists);
				$binzip = $updraftplus->find_working_bin_zip(false, false);
				$this->settings_debugrow(__('zip executable found:', 'updraftplus'), ((is_string($binzip)) ? __('Yes').': '.$binzip : __('No')));
				$hosting_bytes_free = $updraftplus->get_hosting_disk_quota_free();
				if (is_array($hosting_bytes_free)) {
					$perc = round(100*$hosting_bytes_free[1]/(max($hosting_bytes_free[2], 1)), 1);
					$this->settings_debugrow(__('Free disk space in account:', 'updraftplus'), sprintf(__('%s (%s used)', 'updraftplus'), round($hosting_bytes_free[3]/1048576, 1)." MB", "$perc %"));
				}
				
				$this->settings_debugrow(__('Plugins for debugging:', 'updraftplus'),'<a href="'.wp_nonce_url(self_admin_url('update.php?action=install-plugin&updraftplus_noautobackup=1&plugin=wp-crontrol'), 'install-plugin_wp-crontrol').'">WP Crontrol</a> | <a href="'.wp_nonce_url(self_admin_url('update.php?action=install-plugin&updraftplus_noautobackup=1&plugin=sql-executioner'), 'install-plugin_sql-executioner').'">SQL Executioner</a> | <a href="'.wp_nonce_url(self_admin_url('update.php?action=install-plugin&updraftplus_noautobackup=1&plugin=advanced-code-editor'), 'install-plugin_advanced-code-editor').'">Advanced Code Editor</a> '.(current_user_can('edit_plugins') ? '<a href="'.self_admin_url('plugin-editor.php?file=updraftplus/updraftplus.php').'">(edit UpdraftPlus)</a>' : '').' | <a href="'.wp_nonce_url(self_admin_url('update.php?action=install-plugin&updraftplus_noautobackup=1&plugin=wp-filemanager'), 'install-plugin_wp-filemanager').'">WP Filemanager</a>');

				$this->settings_debugrow("HTTP Get: ", '<input id="updraftplus_httpget_uri" type="text" class="call-action"> <a href="#" id="updraftplus_httpget_go">'.__('Fetch', 'updraftplus').'</a> <a href="#" id="updraftplus_httpget_gocurl">'.__('Fetch', 'updraftplus').' (Curl)</a><p id="updraftplus_httpget_results"></p>');

				$this->settings_debugrow(__("Call WordPress action:", 'updraftplus'), '<input id="updraftplus_callwpaction" type="text" class="call-action"> <a href="#" id="updraftplus_callwpaction_go">'.__('Call', 'updraftplus').'</a><div id="updraftplus_callwpaction_results"></div>');

				$this->settings_debugrow('Site ID:', '(used to identify any Vault connections) <span id="updraft_show_sid">'.htmlspecialchars($updraftplus->siteid()).'</span> - <a href="#" id="updraft_reset_sid">'.__('reset', 'updraftplus')."</a>");
				
				$this->settings_debugrow('', '<a href="admin-ajax.php?page=updraftplus&action=updraft_ajax&subaction=backuphistoryraw&nonce='.wp_create_nonce('updraftplus-credentialtest-nonce').'" id="updraftplus-rawbackuphistory">'.__('Show raw backup and file list', 'updraftplus').'</a>');

				echo '</table>';

				do_action('updraftplus_debugtools_dashboard');

				if (!class_exists('UpdraftPlus_Addon_LockAdmin')) {
					echo '<p class="updraftplus-lock-advert"><a href="https://updraftplus.com/shop/updraftplus-premium/"><em>'.__('For the ability to lock access to UpdraftPlus settings with a password, upgrade to UpdraftPlus Premium.', 'updraftplus').'</em></a></p>';
				}

				echo '<h3>'.__('Total (uncompressed) on-disk data:','updraftplus').'</h3>';
				echo '<p class="uncompressed-data"><em>'.__('N.B. This count is based upon what was, or was not, excluded the last time you saved the options.', 'updraftplus').'</em></p><table>';

				foreach ($backupable_entities as $key => $info) {

					$sdescrip = preg_replace('/ \(.*\)$/', '', $info['description']);
					if (strlen($sdescrip) > 20 && isset($info['shortdescription'])) $sdescrip = $info['shortdescription'];

// 					echo '<div style="clear: left;float:left; width:150px;">'.ucfirst($sdescrip).':</strong></div><div style="float:left;"><span id="updraft_diskspaceused_'.$key.'"><em></em></span> <a href="#" onclick="updraftplus_diskspace_entity(\''.$key.'\'); return false;">'.__('count','updraftplus').'</a></div>';
					$this->settings_debugrow(ucfirst($sdescrip).':', '<span id="updraft_diskspaceused_'.$key.'"><em></em></span> <a href="#" onclick="updraftplus_diskspace_entity(\''.$key.'\'); return false;">'.__('count','updraftplus').'</a>');
				}

				?>

				</table>
				<p class="immediate-run"><?php _e('The buttons below will immediately execute a backup run, independently of WordPress\'s scheduler. If these work whilst your scheduled backups do absolutely nothing (i.e. not even produce a log file), then it means that your scheduler is broken.','updraftplus');?> <a href="https://updraftplus.com/faqs/my-scheduled-backups-and-pressing-backup-now-does-nothing-however-pressing-debug-backup-does-produce-a-backup/"><?php _e('Go here for more information.', 'updraftplus'); ?></a></p>

				<table border="0" class="debug-table">
				<tbody>
				<tr>
				<td>
				<form method="post" action="<?php echo esc_url(add_query_arg(array('error' => false, 'updraft_restore_success' => false, 'action' => false, 'page' => 'updraftplus'))); ?>">
					<input type="hidden" name="action" value="updraft_backup_debug_all" />
					<p><input type="submit" class="button-primary" <?php echo $backup_disabled ?> value="<?php _e('Debug Full Backup','updraftplus');?>" onclick="return(confirm('<?php echo htmlspecialchars(__('This will cause an immediate backup. The page will stall loading until it finishes (ie, unscheduled).','updraftplus'));?>'))" /></p>
				</form>
				</td><td>
				<form method="post" action="<?php echo esc_url(add_query_arg(array('error' => false, 'updraft_restore_success' => false, 'action' => false, 'page' => 'updraftplus'))); ?>">
					<input type="hidden" name="action" value="updraft_backup_debug_db" />
					<p><input type="submit" class="button-primary" <?php echo $backup_disabled ?> value="<?php _e('Debug Database Backup','updraftplus');?>" onclick="return(confirm('<?php echo htmlspecialchars(__('This will cause an immediate DB backup. The page will stall loading until it finishes (ie, unscheduled). The backup may well run out of time; really this button is only helpful for checking that the backup is able to get through the initial stages, or for small WordPress sites..','updraftplus'));?>'))" /></p>
				</form>
				</td>
				</tr>
				</tbody>
				</table>
				<h3><?php _e('Wipe settings', 'updraftplus');?></h3>
				<p class="max-width-600"><?php echo __('This button will delete all UpdraftPlus settings and progress information for in-progress backups (but not any of your existing backups from your cloud storage).', 'updraftplus').' '.__('You will then need to enter all your settings again. You can also do this before deactivating/deinstalling UpdraftPlus if you wish.','updraftplus');?></p>
				<form method="post" action="<?php echo esc_url(add_query_arg(array('error' => false, 'updraft_restore_success' => false, 'action' => false, 'page' => 'updraftplus'))); ?>">
					<input type="hidden" name="action" value="updraft_wipesettings" />
					<p><input type="submit" class="button-primary" value="<?php _e('Wipe settings','updraftplus'); ?>" onclick="return(confirm('<?php echo esc_js(__('This will delete all your UpdraftPlus settings - are you sure you want to do this?', 'updraftplus'));?>'))" /></p>
				</form>
			</div>
		<?php
	}

	private function print_delete_old_dirs_form($include_blurb = true, $include_div = true) {
		if ($include_blurb) { 
			if ($include_div) {
				echo '<div id="updraft_delete_old_dirs_pagediv" class="updated delete-old-directories">';
			}
			echo '<p>'.__('Your WordPress install has old directories from its state before you restored/migrated (technical information: these are suffixed with -old). You should press this button to delete them as soon as you have verified that the restoration worked.','updraftplus').'</p>';
		}
		?>
		<form method="post" onsubmit="return updraft_delete_old_dirs();" action="<?php echo esc_url(add_query_arg(array('error' => false, 'updraft_restore_success' => false, 'action' => false, 'page' => 'updraftplus'))); ?>">
			<?php wp_nonce_field('updraftplus-credentialtest-nonce'); ?>
			<input type="hidden" name="action" value="updraft_delete_old_dirs">
			<input type="submit" class="button-primary" value="<?php echo esc_attr(__('Delete Old Directories', 'updraftplus'));?>"  />
		</form>
		<?php
		if ($include_blurb && $include_div) echo '</div>';
	}

	private function get_cron($job_id = false) {

		$cron = get_option('cron');
		if (!is_array($cron)) $cron = array();
		if (false === $job_id) return $cron;

		foreach ($cron as $time => $job) {
			if (isset($job['updraft_backup_resume'])) {
				foreach ($job['updraft_backup_resume'] as $hook => $info) {
					if (isset($info['args'][1]) && $job_id == $info['args'][1]) {
						global $updraftplus;
						$jobdata = $updraftplus->jobdata_getarray($job_id);
						return (!is_array($jobdata)) ? false : array($time, $jobdata);
					}
				}
			}
		}
	}

	// A value for $this_job_only also causes something to always be returned (to allow detection of the job having started on the front-end)
	private function print_active_jobs($this_job_only = false) {
		$cron = $this->get_cron();
// 		$found_jobs = 0;
		$ret = '';

		foreach ($cron as $time => $job) {
			if (isset($job['updraft_backup_resume'])) {
				foreach ($job['updraft_backup_resume'] as $hook => $info) {
					if (isset($info['args'][1])) {
// 						$found_jobs++;
						$job_id = $info['args'][1];
						if (false === $this_job_only || $job_id == $this_job_only) {
							$ret .= $this->print_active_job($job_id, false, $time, $info['args'][0]);
						}
					}
				}
			}
		}

		// A value for $this_job_only implies that output is required
		if (false !== $this_job_only && !$ret) {
			$ret = $this->print_active_job($this_job_only);
			if ('' == $ret) {
				// The presence of the exact ID matters to the front-end - indicates that the backup job has at least begun
				$ret = '<div class="active-jobs updraft_finished" id="updraft-jobid-'.$this_job_only.'"><em>'.__('The backup has finished running', 'updraftplus').'</em> - <a class="updraft-log-link" data-jobid="'.$this_job_only.'">'.__('View Log', 'updraftplus').'</a></div>';
			}
		}

// 		if (0 == $found_jobs) $ret .= '<p><em>'.__('(None)', 'updraftplus').'</em></p>';
		return $ret;
	}

	private function print_active_job($job_id, $is_oneshot = false, $time = false, $next_resumption = false) {

		$ret = '';

		global $updraftplus;
		$jobdata = $updraftplus->jobdata_getarray($job_id);

		if (false == apply_filters('updraftplus_print_active_job_continue', true, $is_oneshot, $next_resumption, $jobdata)) return '';

		#if (!is_array($jobdata)) $jobdata = array();
		if (!isset($jobdata['backup_time'])) return '';

		$backupable_entities = $updraftplus->get_backupable_file_entities(true, true);

		$began_at = (isset($jobdata['backup_time'])) ? get_date_from_gmt(gmdate('Y-m-d H:i:s', (int)$jobdata['backup_time']), 'D, F j, Y H:i') : '?';

		$jobstatus = empty($jobdata['jobstatus']) ? 'unknown' : $jobdata['jobstatus'];
		$stage = 0;
		switch ($jobstatus) {
			# Stage 0
			case 'begun':
			$curstage = __('Backup begun', 'updraftplus');
			break;
			# Stage 1
			case 'filescreating':
			$stage = 1;
			$curstage = __('Creating file backup zips', 'updraftplus');
			if (!empty($jobdata['filecreating_substatus']) && isset($backupable_entities[$jobdata['filecreating_substatus']['e']]['description'])) {
			
				$sdescrip = preg_replace('/ \(.*\)$/', '', $backupable_entities[$jobdata['filecreating_substatus']['e']]['description']);
				if (strlen($sdescrip) > 20 && isset($jobdata['filecreating_substatus']['e']) && is_array($jobdata['filecreating_substatus']['e']) && isset($backupable_entities[$jobdata['filecreating_substatus']['e']]['shortdescription'])) $sdescrip = $backupable_entities[$jobdata['filecreating_substatus']['e']]['shortdescription'];
				$curstage .= ' ('.$sdescrip.')';
				if (isset($jobdata['filecreating_substatus']['i']) && isset($jobdata['filecreating_substatus']['t'])) {
					$stage = min(2, 1 + ($jobdata['filecreating_substatus']['i']/max($jobdata['filecreating_substatus']['t'],1)));
				}
			}
			break;
			case 'filescreated':
			$stage = 2;
			$curstage = __('Created file backup zips', 'updraftplus');
			break;

			# Stage 4
			case 'clouduploading':
			$stage = 4;
			$curstage = __('Uploading files to remote storage', 'updraftplus');
			if (isset($jobdata['uploading_substatus']['t']) && isset($jobdata['uploading_substatus']['i'])) {
				$t = max((int)$jobdata['uploading_substatus']['t'], 1);
				$i = min($jobdata['uploading_substatus']['i']/$t, 1);
				$p = min($jobdata['uploading_substatus']['p'], 1);
				$pd = $i + $p/$t;
				$stage = 4 + $pd;
				$curstage .= ' '.sprintf(__('(%s%%, file %s of %s)', 'updraftplus'), floor(100*$pd), $jobdata['uploading_substatus']['i']+1, $t);
			}
			break;
			case 'pruning':
			$stage = 5;
			$curstage = __('Pruning old backup sets', 'updraftplus');
			break;
			case 'resumingforerrors':
			$stage = -1;
			$curstage = __('Waiting until scheduled time to retry because of errors', 'updraftplus');
			break;
			# Stage 6
			case 'finished':
			$stage = 6;
			$curstage = __('Backup finished', 'updraftplus');
			break;
			default:

			# Database creation and encryption occupies the space from 2 to 4. Databases are created then encrypted, then the next databae is created/encrypted, etc.
			if ('dbcreated' == substr($jobstatus, 0, 9)) {
				$jobstatus = 'dbcreated';
				$whichdb = substr($jobstatus, 9);
				if (!is_numeric($whichdb)) $whichdb = 0;
				$howmanydbs = max((empty($jobdata['backup_database']) || !is_array($jobdata['backup_database'])) ? 1 : count($jobdata['backup_database']), 1);
				$perdbspace = 2/$howmanydbs;

				$stage = min(4, 2 + ($whichdb+2)*$perdbspace);

				$curstage = __('Created database backup', 'updraftplus');

			} elseif ('dbcreating' == substr($jobstatus, 0, 10)) {
				$whichdb = substr($jobstatus, 10);
				if (!is_numeric($whichdb)) $whichdb = 0;
				$howmanydbs = (empty($jobdata['backup_database']) || !is_array($jobdata['backup_database'])) ? 1 : count($jobdata['backup_database']);
				$perdbspace = 2/$howmanydbs;
				$jobstatus = 'dbcreating';

				$stage = min(4, 2 + $whichdb*$perdbspace);

				$curstage = __('Creating database backup', 'updraftplus');
				if (!empty($jobdata['dbcreating_substatus']['t'])) {
					$curstage .= ' ('.sprintf(__('table: %s', 'updraftplus'), $jobdata['dbcreating_substatus']['t']).')';
					if (!empty($jobdata['dbcreating_substatus']['i']) && !empty($jobdata['dbcreating_substatus']['a'])) {
						$substage = max(0.001, ($jobdata['dbcreating_substatus']['i'] / max($jobdata['dbcreating_substatus']['a'],1)));
						$stage += $substage * $perdbspace * 0.5;
					}
				}
			} elseif ('dbencrypting' == substr($jobstatus, 0, 12)) {
				$whichdb = substr($jobstatus, 12);
				if (!is_numeric($whichdb)) $whichdb = 0;
				$howmanydbs = (empty($jobdata['backup_database']) || !is_array($jobdata['backup_database'])) ? 1 : count($jobdata['backup_database']);
				$perdbspace = 2/$howmanydbs;
				$stage = min(4, 2 + $whichdb*$perdbspace + $perdbspace*0.5);
				$jobstatus = 'dbencrypting';
				$curstage = __('Encrypting database', 'updraftplus');
			} elseif ('dbencrypted' == substr($jobstatus, 0, 11)) {
				$whichdb = substr($jobstatus, 11);
				if (!is_numeric($whichdb)) $whichdb = 0;
				$howmanydbs = (empty($jobdata['backup_database']) || !is_array($jobdata['backup_database'])) ? 1 : count($jobdata['backup_database']);
				$jobstatus = 'dbencrypted';
				$perdbspace = 2/$howmanydbs;
				$stage = min(4, 2 + $whichdb*$perdbspace + $perdbspace);
				$curstage = __('Encrypted database', 'updraftplus');
			} else {
				$curstage = __('Unknown', 'updraftplus');
			}
		}

		$runs_started = (empty($jobdata['runs_started'])) ? array() : $jobdata['runs_started'];
		$time_passed = (empty($jobdata['run_times'])) ? array() : $jobdata['run_times'];
		$last_checkin_ago = -1;
		if (is_array($time_passed)) {
			foreach ($time_passed as $run => $passed) {
				if (isset($runs_started[$run])) {
					$time_ago = microtime(true) - ($runs_started[$run] + $time_passed[$run]);
					if ($time_ago < $last_checkin_ago || $last_checkin_ago == -1) $last_checkin_ago = $time_ago;
				}
			}
		}

		$next_res_after = (int)$time-time();
		$next_res_txt = ($is_oneshot) ? '' : ' - '.sprintf(__("next resumption: %d (after %ss)", 'updraftplus'), $next_resumption, $next_res_after). ' ';
		$last_activity_txt = ($last_checkin_ago >= 0) ? ' - '.sprintf(__('last activity: %ss ago', 'updraftplus'), floor($last_checkin_ago)).' ' : '';

		if (($last_checkin_ago < 50 && $next_res_after>30) || $is_oneshot) {
			$show_inline_info = $last_activity_txt;
			$title_info = $next_res_txt;
		} else {
			$show_inline_info = $next_res_txt;
			$title_info = $last_activity_txt;
		}

		// Existence of the 'updraft-jobid-(id)' id is checked for in other places, so do not modify this
		$ret .= '<div class="job-id" id="updraft-jobid-'.$job_id.'"><span class="updraft_jobtimings next-resumption';

		if (!empty($jobdata['is_autobackup'])) $ret .= ' isautobackup';

		$ret .= '" data-jobid="'.$job_id.'" data-lastactivity="'.(int)$last_checkin_ago.'" data-nextresumption="'.$next_resumption.'" data-nextresumptionafter="'.$next_res_after.'" title="'.esc_attr(sprintf(__('Job ID: %s', 'updraftplus'), $job_id)).$title_info.'">'.$began_at.'</span> ';

		$ret .= $show_inline_info;
		$ret .= '- <a data-jobid="'.$job_id.'" href="'.UpdraftPlus_Options::admin_page_url().'?page=updraftplus&action=downloadlog&updraftplus_backup_nonce='.$job_id.'" class="updraft-log-link">'.__('show log', 'updraftplus').'</a>';

		if (!$is_oneshot) $ret .=' - <a href="#" data-jobid="'.$job_id.'" title="'.esc_attr(__('Note: the progress bar below is based on stages, NOT time. Do not stop the backup simply because it seems to have remained in the same place for a while - that is normal.', 'updraftplus')).'" class="updraft_jobinfo_delete">'.__('stop', 'updraftplus').'</a>';

		$ret .= apply_filters('updraft_printjob_beforewarnings', '', $jobdata, $job_id);

		if (!empty($jobdata['warnings']) && is_array($jobdata['warnings'])) {
			$ret .= '<ul class="disc">';
			foreach ($jobdata['warnings'] as $warning) {
				$ret .= '<li>'.sprintf(__('Warning: %s', 'updraftplus'), make_clickable(htmlspecialchars($warning))).'</li>';
			}
			$ret .= '</ul>';
		}

		$ret .= '<div class="curstage">';
		$ret .= htmlspecialchars($curstage);
		$ret .= '<div class="updraft_percentage" style="height: 100%; width:'.(($stage>0) ? (ceil((100/6)*$stage)) : '0').'%"></div>';
		$ret .= '</div></div>';

		$ret .= '</div>';

		return $ret;

	}

	private function delete_old_dirs_go($show_return = true) {
		echo ($show_return) ? '<h1>UpdraftPlus - '.__('Remove old directories', 'updraftplus').'</h1>' : '<h2>'.__('Remove old directories', 'updraftplus').'</h2>';

		if ($this->delete_old_dirs()) {
			echo '<p>'.__('Old directories successfully removed.','updraftplus').'</p><br/>';
		} else {
			echo '<p>',__('Old directory removal failed for some reason. You may want to do this manually.','updraftplus').'</p><br/>';
		}
		if ($show_return) echo '<b>'.__('Actions','updraftplus').':</b> <a href="'.UpdraftPlus_Options::admin_page_url().'?page=updraftplus">'.__('Return to UpdraftPlus Configuration','updraftplus').'</a>';
	}

	//deletes the -old directories that are created when a backup is restored.
	private function delete_old_dirs() {
		global $wp_filesystem, $updraftplus;
		$credentials = request_filesystem_credentials(wp_nonce_url(UpdraftPlus_Options::admin_page_url()."?page=updraftplus&action=updraft_delete_old_dirs", 'updraftplus-credentialtest-nonce')); 
		WP_Filesystem($credentials);
		if ($wp_filesystem->errors->get_error_code()) { 
			foreach ($wp_filesystem->errors->get_error_messages() as $message)
				show_message($message); 
			exit; 
		}
		// From WP_CONTENT_DIR - which contains 'themes'
		$ret = $this->delete_old_dirs_dir($wp_filesystem->wp_content_dir());

		$updraft_dir = $updraftplus->backups_dir_location();
		if ($updraft_dir) {
			$ret4 = ($updraft_dir) ? $this->delete_old_dirs_dir($updraft_dir, false) : true;
		} else {
			$ret4 = true;
		}

// 		$ret2 = $this->delete_old_dirs_dir($wp_filesystem->abspath());
		$plugs = untrailingslashit($wp_filesystem->wp_plugins_dir());
		if ($wp_filesystem->is_dir($plugs.'-old')) {
			print "<strong>".__('Delete','updraftplus').": </strong>plugins-old: ";
			if (!$wp_filesystem->delete($plugs.'-old', true)) {
				$ret3 = false;
				print "<strong>".__('Failed', 'updraftplus')."</strong><br>";
			} else {
				$ret3 = true;
				print "<strong>".__('OK', 'updraftplus')."</strong><br>";
			}
		} else {
			$ret3 = true;
		}

		return $ret && $ret3 && $ret4;
	}

	private function delete_old_dirs_dir($dir, $wpfs = true) {

		$dir = trailingslashit($dir);

		global $wp_filesystem, $updraftplus;

		if ($wpfs) {
			$list = $wp_filesystem->dirlist($dir);
		} else {
			$list = scandir($dir);
		}
		if (!is_array($list)) return false;

		$ret = true;
		foreach ($list as $item) {
			$name = (is_array($item)) ? $item['name'] : $item;
			if ("-old" == substr($name, -4, 4)) {
				//recursively delete
				print "<strong>".__('Delete','updraftplus').": </strong>".htmlspecialchars($name).": ";

				if ($wpfs) {
					if (!$wp_filesystem->delete($dir.$name, true)) {
						$ret = false;
						echo "<strong>".__('Failed', 'updraftplus')."</strong><br>";
					} else {
						echo "<strong>".__('OK', 'updraftplus')."</strong><br>";
					}
				} else {
					if ($updraftplus->remove_local_directory($dir.$name)) {
						echo "<strong>".__('OK', 'updraftplus')."</strong><br>";
					} else {
						$ret = false;
						echo "<strong>".__('Failed', 'updraftplus')."</strong><br>";
					}
				}
			}
		}
		return $ret;
	}

	// The aim is to get a directory that is writable by the webserver, because that's the only way we can create zip files
	private function create_backup_dir() {

		global $wp_filesystem, $updraftplus;

		if (false === ($credentials = request_filesystem_credentials(UpdraftPlus_Options::admin_page().'?page=updraftplus&action=updraft_create_backup_dir&nonce='.wp_create_nonce('create_backup_dir')))) {
			return false;
		}

		if ( ! WP_Filesystem($credentials) ) {
			// our credentials were no good, ask the user for them again
			request_filesystem_credentials(UpdraftPlus_Options::admin_page().'?page=updraftplus&action=updraft_create_backup_dir&nonce='.wp_create_nonce('create_backup_dir'), '', true);
			return false;
		}

		$updraft_dir = $updraftplus->backups_dir_location();

		$default_backup_dir = $wp_filesystem->find_folder(dirname($updraft_dir)).basename($updraft_dir);

		$updraft_dir = ($updraft_dir) ? $wp_filesystem->find_folder(dirname($updraft_dir)).basename($updraft_dir) : $default_backup_dir;

		if (!$wp_filesystem->is_dir($default_backup_dir) && !$wp_filesystem->mkdir($default_backup_dir, 0775)) {
			$wperr = new WP_Error;
			if ( $wp_filesystem->errors->get_error_code() ) { 
				foreach ( $wp_filesystem->errors->get_error_messages() as $message ) {
					$wperr->add('mkdir_error', $message);
				}
				return $wperr; 
			} else {
				return new WP_Error('mkdir_error', __('The request to the filesystem to create the directory failed.', 'updraftplus'));
			}
		}

		if ($wp_filesystem->is_dir($default_backup_dir)) {

			if ($updraftplus->really_is_writable($updraft_dir)) return true;

			@$wp_filesystem->chmod($default_backup_dir, 0775);
			if ($updraftplus->really_is_writable($updraft_dir)) return true;

			@$wp_filesystem->chmod($default_backup_dir, 0777);

			if ($updraftplus->really_is_writable($updraft_dir)) {
				echo '<p>'.__('The folder was created, but we had to change its file permissions to 777 (world-writable) to be able to write to it. You should check with your hosting provider that this will not cause any problems', 'updraftplus').'</p>';
				return true;
			} else {
				@$wp_filesystem->chmod($default_backup_dir, 0775);
				$show_dir = (0 === strpos($default_backup_dir, ABSPATH)) ? substr($default_backup_dir, strlen(ABSPATH)) : $default_backup_dir;
				return new WP_Error('writable_error', __('The folder exists, but your webserver does not have permission to write to it.', 'updraftplus').' '.__('You will need to consult with your web hosting provider to find out how to set permissions for a WordPress plugin to write to the directory.', 'updraftplus').' ('.$show_dir.')');
			}
		}

		return true;
	}

	//scans the content dir to see if any -old dirs are present
	private function scan_old_dirs($print_as_comment = false) {
		global $updraftplus;
		$dirs = scandir(untrailingslashit(WP_CONTENT_DIR));
		if (!is_array($dirs)) $dirs = array();
		$dirs_u = @scandir($updraftplus->backups_dir_location());
		if (!is_array($dirs_u)) $dirs_u = array();
		foreach (array_merge($dirs, $dirs_u) as $dir) {
			if (preg_match('/-old$/', $dir)) {
				if ($print_as_comment) echo '<!--'.htmlspecialchars($dir).'-->';
				return true;
			}
		}
		# No need to scan ABSPATH - we don't backup there
		if (is_dir(untrailingslashit(WP_PLUGIN_DIR).'-old')) {
			if ($print_as_comment) echo '<!--'.htmlspecialchars(untrailingslashit(WP_PLUGIN_DIR).'-old').'-->';
			return true;
		}
		return false;
	}

	public function storagemethod_row($method, $header, $contents) {
		?>
			<tr class="updraftplusmethod <?php echo $method;?>">
				<th><?php echo $header;?></th>
				<td><?php echo $contents;?></td>
			</tr>
		<?php
	}

	private function last_backup_html() {

		global $updraftplus;

		$updraft_last_backup = UpdraftPlus_Options::get_updraft_option('updraft_last_backup');

		if ($updraft_last_backup) {

			// Convert to GMT, then to blog time
			$backup_time = (int)$updraft_last_backup['backup_time'];

			$print_time = get_date_from_gmt(gmdate('Y-m-d H:i:s', $backup_time), 'D, F j, Y H:i');
// 			$print_time = date_i18n('D, F j, Y H:i', $backup_time);

			if (empty($updraft_last_backup['backup_time_incremental'])) {
				$last_backup_text = "<span style=\"color:".(($updraft_last_backup['success']) ? 'green' : 'black').";\">".$print_time.'</span>';
			} else {
				$inc_time = get_date_from_gmt(gmdate('Y-m-d H:i:s', $updraft_last_backup['backup_time_incremental']), 'D, F j, Y H:i');
// 				$inc_time = date_i18n('D, F j, Y H:i', $updraft_last_backup['backup_time_incremental']);
				$last_backup_text = "<span style=\"color:".(($updraft_last_backup['success']) ? 'green' : 'black').";\">$inc_time</span> (".sprintf(__('incremental backup; base backup: %s', 'updraftplus'), $print_time).')';
			}

			$last_backup_text .= '<br>';

			// Show errors + warnings
			if (is_array($updraft_last_backup['errors'])) {
				foreach ($updraft_last_backup['errors'] as $err) {
					$level = (is_array($err)) ? $err['level'] : 'error';
					$message = (is_array($err)) ? $err['message'] : $err;
					$last_backup_text .= ('warning' == $level) ? "<span style=\"color:orange;\">" : "<span style=\"color:red;\">";
					if ('warning' == $level) {
						$message = sprintf(__("Warning: %s", 'updraftplus'), make_clickable(htmlspecialchars($message)));
					} else {
						$message = htmlspecialchars($message);
					}
					$last_backup_text .= $message;
					$last_backup_text .= '</span><br>';
				}
			}

			// Link log
			if (!empty($updraft_last_backup['backup_nonce'])) {
				$updraft_dir = $updraftplus->backups_dir_location();

				$potential_log_file = $updraft_dir."/log.".$updraft_last_backup['backup_nonce'].".txt";
				if (is_readable($potential_log_file)) $last_backup_text .= "<a href=\"?page=updraftplus&action=downloadlog&updraftplus_backup_nonce=".$updraft_last_backup['backup_nonce']."\" class=\"updraft-log-link\" onclick=\"event.preventDefault(); updraft_popuplog('".$updraft_last_backup['backup_nonce']."');\">".__('Download log file','updraftplus')."</a>";
			}

		} else {
			$last_backup_text =  "<span style=\"color:blue;\">".__('No backup has been completed','updraftplus')."</span>";
		}

		return $last_backup_text;

	}

	public function get_intervals() {
		return apply_filters('updraftplus_backup_intervals', array(
			'manual' => _x("Manual", 'i.e. Non-automatic', 'updraftplus'),
			'every4hours' => sprintf(__("Every %s hours", 'updraftplus'), '4'),
			'every8hours' => sprintf(__("Every %s hours", 'updraftplus'), '8'),
			'twicedaily' => sprintf(__("Every %s hours", 'updraftplus'), '12'),
			'daily' => __("Daily", 'updraftplus'),
			'weekly' => __("Weekly", 'updraftplus'),
			'fortnightly' => __("Fortnightly", 'updraftplus'),
			'monthly' => __("Monthly", 'updraftplus')
		));
	}
	
	private function really_writable_message($really_is_writable, $updraft_dir){
		if ($really_is_writable) {
			$dir_info = '<span style="color:green;">'.__('Backup directory specified is writable, which is good.','updraftplus').'</span>';
		} else {
			$dir_info = '<span style="color:red;">';
			if (!is_dir($updraft_dir)) {
				$dir_info .= __('Backup directory specified does <b>not</b> exist.','updraftplus');
			} else {
				$dir_info .= __('Backup directory specified exists, but is <b>not</b> writable.','updraftplus');
			}
			$dir_info .= '<span class="updraft-directory-not-writable-blurb"><span class="directory-permissions"><a class="updraft_create_backup_dir" href="'.UpdraftPlus_Options::admin_page_url().'?page=updraftplus&action=updraft_create_backup_dir&nonce='.wp_create_nonce('create_backup_dir').'">'.__('Click here to attempt to create the directory and set the permissions','updraftplus').'</a></span>, '.__('or, to reset this option','updraftplus').' <a href="#" class="updraft_backup_dir_reset">'.__('click here','updraftplus').'</a>. '.__('If that is unsuccessful check the permissions on your server or change it to another directory that is writable by your web server process.','updraftplus').'</span>';
		}
		return $dir_info;
	}

	public function settings_formcontents($options = array()) {

		global $updraftplus;

		$updraft_dir = $updraftplus->backups_dir_location();
		$really_is_writable = $updraftplus->really_is_writable($updraft_dir);

		$default_options = array(
			'include_database_decrypter' => true,
			'include_adverts' => true,
			'include_save_button' => true
		);
		foreach ($default_options as $k => $v) {
			if (!isset($options[$k])) $options[$k] = $v;
		}

		?>
			<table class="form-table">
			<tr>
				<th><?php _e('Files backup schedule','updraftplus'); ?>:</th>
				<td>
					<div style="float:left; clear:both;">
						<select class="updraft_interval" name="updraft_interval">
						<?php
						$intervals = $this->get_intervals();
						$selected_interval = UpdraftPlus_Options::get_updraft_option('updraft_interval', 'manual');
						foreach ($intervals as $cronsched => $descrip) {
							echo "<option value=\"$cronsched\" ";
							if ($cronsched == $selected_interval) echo 'selected="selected"';
							echo ">".htmlspecialchars($descrip)."</option>\n";
						}
						?>
						</select> <span class="updraft_files_timings"><?php echo apply_filters('updraftplus_schedule_showfileopts', '<input type="hidden" name="updraftplus_starttime_files" value="">', $selected_interval); ?></span>
					

						<?php

							$updraft_retain = max((int)UpdraftPlus_Options::get_updraft_option('updraft_retain', 2), 1);

							$retain_files_config = __('and retain this many scheduled backups', 'updraftplus').': <input type="number" min="1" step="1" name="updraft_retain" value="'.$updraft_retain.'" class="retain-files" />';

// 							echo apply_filters('updraftplus_retain_files_intervalline', $retain_files_config, $updraft_retain);
							echo $retain_files_config;

						?>
					</div>
					<?php do_action('updraftplus_after_filesconfig'); ?>
				</td>
			</tr>

			<?php if (defined('UPDRAFTPLUS_EXPERIMENTAL') && UPDRAFTPLUS_EXPERIMENTAL) { ?>
			<tr class="updraft_incremental_row">
				<th><?php _e('Incremental file backup schedule', 'updraftplus'); ?>:</th>
				<td>
					<?php do_action('updraftplus_incremental_cell', $selected_interval); ?>
					<a href="https://updraftplus.com/support/tell-me-more-about-incremental-backups/"><em><?php _e('Tell me more about incremental backups', 'updraftplus'); ?><em></a>
					</td>
			</tr>
			<?php } ?>

			<?php apply_filters('updraftplus_after_file_intervals', false, $selected_interval); ?>
			<tr>
				<th><?php _e('Database backup schedule','updraftplus'); ?>:</th>
				<td>
				<div style="float:left; clear:both;">
					<select class="updraft_interval_database" name="updraft_interval_database">
					<?php
					$selected_interval_db = UpdraftPlus_Options::get_updraft_option('updraft_interval_database', UpdraftPlus_Options::get_updraft_option('updraft_interval'));
					foreach ($intervals as $cronsched => $descrip) {
						echo "<option value=\"$cronsched\" ";
						if ($cronsched == $selected_interval_db) echo 'selected="selected"';
						echo ">$descrip</option>\n";
					}
					?>
					</select> <span class="updraft_same_schedules_message"><?php echo apply_filters('updraftplus_schedule_sametimemsg', '');?></span><span class="updraft_db_timings"><?php echo apply_filters('updraftplus_schedule_showdbopts', '<input type="hidden" name="updraftplus_starttime_db" value="">', $selected_interval_db); ?></span>

					<?php
						$updraft_retain_db = max((int)UpdraftPlus_Options::get_updraft_option('updraft_retain_db', $updraft_retain), 1);
						$retain_dbs_config = __('and retain this many scheduled backups', 'updraftplus').': <input type="number" min="1" step="1" name="updraft_retain_db" value="'.$updraft_retain_db.'" class="retain-files" />';

// 						echo apply_filters('updraftplus_retain_db_intervalline', $retain_dbs_config, $updraft_retain_db);
						echo $retain_dbs_config;
					?>
				</div>
				<?php do_action('updraftplus_after_dbconfig'); ?>
			</td>
			</tr>
			<tr class="backup-interval-description">
				<th></th>
				<td><div>
				<?php
					echo apply_filters('updraftplus_fixtime_ftinfo', '<p>'.__('To fix the time at which a backup should take place,','updraftplus').' ('.__('e.g. if your server is busy at day and you want to run overnight','updraftplus').'), '.__('or to configure more complex schedules', 'updraftplus').', <a href="https://updraftplus.com/shop/updraftplus-premium/">'.htmlspecialchars(__('use UpdraftPlus Premium', 'updraftplus')).'</a></p>'); 
				?>
				</div></td>
			</tr>
			</table>

			<h2 class="updraft_settings_sectionheading"><?php _e('Sending Your Backup To Remote Storage','updraftplus');?></h2>

			<?php
				$debug_mode = UpdraftPlus_Options::get_updraft_option('updraft_debug_mode') ? 'checked="checked"' : "";
				$active_service = UpdraftPlus_Options::get_updraft_option('updraft_service');
			?>

			<table class="form-table width-900">
			<tr>
				<th><?php
					echo __('Choose your remote storage','updraftplus').'<br>'.apply_filters('updraftplus_after_remote_storage_heading_message', '<em>'.__('(tap on an icon to select or unselect)', 'updraftplus').'</em>');
				?>:</th>
				<td>
				<div id="remote-storage-container">
				<?php
					if (is_array($active_service)) $active_service = $updraftplus->just_one($active_service);
					
					//Change this to give a class that we can exclude
					$multi = apply_filters('updraftplus_storage_printoptions_multi', '');
					
					foreach($updraftplus->backup_methods as $method => $description) {
						echo "<input name=\"updraft_service[]\" class=\"updraft_servicecheckbox $method $multi\" id=\"updraft_servicecheckbox_$method\" type=\"checkbox\" value=\"$method\"";
						if ($active_service === $method || (is_array($active_service) && in_array($method, $active_service))) echo ' checked="checked"';
						echo " data-labelauty=\"".esc_attr($description)."\">";
					}
				?>
				
				
				<?php 
					if (false === apply_filters('updraftplus_storage_printoptions', false, $active_service)) {
						
						echo '</div>';
						echo '<p><a href="https://updraftplus.com/shop/morestorage/">'.htmlspecialchars(__('You can send a backup to more than one destination with an add-on.','updraftplus')).'</a></p>';
						echo '</td></tr>';
				}
					?>
				
					
					<tr class="updraftplusmethod none ud_nostorage" style="display:none;">
						<td></td>
						<td><em><?php echo htmlspecialchars(__('If you choose no remote storage, then the backups remain on the web-server. This is not recommended (unless you plan to manually copy them to your computer), as losing the web-server would mean losing both your website and the backups in one event.', 'updraftplus'));?></em></td>
					</tr>

					<?php
						$method_objects = array();
						foreach ($updraftplus->backup_methods as $method => $description) {
							do_action('updraftplus_config_print_before_storage', $method);
							require_once(UPDRAFTPLUS_DIR.'/methods/'.$method.'.php');
							$call_method = 'UpdraftPlus_BackupModule_'.$method;
							$method_objects[$method] = new $call_method;
							$method_objects[$method]->config_print();
							do_action('updraftplus_config_print_after_storage', $method);
						}
					?>

			</table>

			<hr style="width:900px; float:left;">
			
			<h2 class="updraft_settings_sectionheading"><?php _e('File Options', 'updraftplus');?></h2>

			<table class="form-table" >
				<tr>
					<th><?php _e('Include in files backup', 'updraftplus');?>:</th>
					<td>
						<?php echo $this->files_selector_widgetry(); ?>
						<p><?php echo apply_filters('updraftplus_admin_directories_description', __('The above directories are everything, except for WordPress core itself which you can download afresh from WordPress.org.', 'updraftplus').' <a href="https://updraftplus.com/shop/">'.htmlspecialchars(__('See also the "More Files" add-on from our shop.', 'updraftplus')).'</a>'); ?></p>
					</td>
				</tr>
			</table>

			<h2 class="updraft_settings_sectionheading"><?php _e('Database Options','updraftplus');?></h2>

			<table class="form-table width-900">

			<tr>
				<th><?php _e('Database encryption phrase', 'updraftplus');?>:</th>

				<td>
				<?php
					echo apply_filters('updraft_database_encryption_config', '<a href="https://updraftplus.com/shop/updraftplus-premium/">'.__("Don't want to be spied on? UpdraftPlus Premium can encrypt your database backup.", 'updraftplus').'</a> '.__('It can also backup external databases.', 'updraftplus'));
				?>
				</td>
			</tr>
			
			<?php if (!empty($options['include_database_decrypter'])) { ?>
			
			<tr class="backup-crypt-description">
				<td></td>

				<td>

				<a href="#" class="updraft_show_decryption_widget"><?php _e('You can manually decrypt an encrypted database here.','updraftplus');?></a>

				<div id="updraft-manualdecrypt-modal" class="updraft-hidden" style="display:none;">
					<p><h3><?php _e("Manually decrypt a database backup file" ,'updraftplus');?></h3></p>

					<?php
					global $wp_version;
					if (version_compare($wp_version, '3.3', '<')) {
						echo '<em>'.sprintf(__('This feature requires %s version %s or later', 'updraftplus'), 'WordPress', '3.3').'</em>';
					} else {
					?>

					<div id="plupload-upload-ui2">
						<div id="drag-drop-area2">
							<div class="drag-drop-inside">
								<p class="drag-drop-info"><?php _e('Drop encrypted database files (db.gz.crypt files) here to upload them for decryption', 'updraftplus'); ?></p>
								<p><?php _ex('or', 'Uploader: Drop db.gz.crypt files here to upload them for decryption - or - Select Files', 'updraftplus'); ?></p>
								<p class="drag-drop-buttons"><input id="plupload-browse-button2" type="button" value="<?php esc_attr_e('Select Files', 'updraftplus'); ?>" class="button" /></p>
								<p style="margin-top: 18px;"><?php _e('First, enter the decryption key','updraftplus')?>: <input id="updraftplus_db_decrypt" type="text" size="12"></input></p>
							</div>
						</div>
						<div id="filelist2">
						</div>
					</div>

					<?php } ?>

				</div>


				</td>
			</tr>
			
			<?php } ?>

			<?php
				#'<a href="https://updraftplus.com/shop/updraftplus-premium/">'.__("This feature is part of UpdraftPlus Premium.", 'updraftplus').'</a>'
				$moredbs_config = apply_filters('updraft_database_moredbs_config', false);
				if (!empty($moredbs_config)) {
			?>

			<tr>
				<th><?php _e('Back up more databases', 'updraftplus');?>:</th>
				<td><?php echo $moredbs_config; ?>
				</td>
			</tr>

			<?php } ?>

			</table>

			<h2 class="updraft_settings_sectionheading"><?php _e('Reporting','updraftplus');?></h2>

			<table class="form-table" style="width:900px;">

			<?php
				$report_rows = apply_filters('updraftplus_report_form', false);
				if (is_string($report_rows)) {
					echo $report_rows;
				} else {
			?>

			<tr>
				<th><?php _e('Email', 'updraftplus'); ?>:</th>
				<td>
					<?php
						$updraft_email = UpdraftPlus_Options::get_updraft_option('updraft_email');
					?>
					<input type="checkbox" id="updraft_email" name="updraft_email" value="<?php esc_attr_e(get_bloginfo('admin_email')); ?>"<?php if (!empty($updraft_email)) echo ' checked="checked"';?> > <br><label for="updraft_email"><?php echo __("Check this box to have a basic report sent to", 'updraftplus').' <a href="'.admin_url('options-general.php').'">'.__("your site's admin address", 'updraftplus').'</a> ('.htmlspecialchars(get_bloginfo('admin_email')).")."; ?></label>
					<?php
						if (!class_exists('UpdraftPlus_Addon_Reporting')) echo '<a href="https://updraftplus.com/shop/reporting/">'.__('For more reporting features, use the Reporting add-on.', 'updraftplus').'</a>';
					?>
				</td>
			</tr>

			<?php } ?>

			</table>

			<script type="text/javascript">
			/* <![CDATA[ */
			<?php echo $this->get_settings_js($method_objects, $really_is_writable, $updraft_dir); ?>
			/* ]]> */
			</script>
			<table class="form-table width-900">
			<tr>
				<td colspan="2"><h2 class="updraft_settings_sectionheading"><?php _e('Advanced / Debugging Settings','updraftplus'); ?></h2></td>
			</tr>

			<tr>
				<th><?php _e('Expert settings','updraftplus');?>:</th>
				<td><a class="enableexpertmode" href="#enableexpertmode"><?php _e('Show expert settings','updraftplus');?></a> - <?php _e("click this to show some further options; don't bother with this unless you have a problem or are curious.",'updraftplus');?> <?php do_action('updraftplus_expertsettingsdescription'); ?></td>
			</tr>
			<?php
			$delete_local = UpdraftPlus_Options::get_updraft_option('updraft_delete_local', 1);
			$split_every_mb = UpdraftPlus_Options::get_updraft_option('updraft_split_every', 400);
			if (!is_numeric($split_every_mb)) $split_every_mb = 400;
			if ($split_every_mb < UPDRAFTPLUS_SPLIT_MIN) $split_every_mb = UPDRAFTPLUS_SPLIT_MIN;
			?>

			<tr class="expertmode updraft-hidden" style="display:none;">
				<th><?php _e('Debug mode','updraftplus');?>:</th>
				<td><input type="checkbox" id="updraft_debug_mode" name="updraft_debug_mode" value="1" <?php echo $debug_mode; ?> /> <br><label for="updraft_debug_mode"><?php _e('Check this to receive more information and emails on the backup process - useful if something is going wrong.','updraftplus');?> <?php _e('This will also cause debugging output from all plugins to be shown upon this screen - please do not be surprised to see these.', 'updraftplus');?></label></td>
			</tr>

			<tr class="expertmode updraft-hidden" style="display:none;">
				<th><?php _e('Split archives every:','updraftplus');?></th>
				<td><input type="text" name="updraft_split_every" class="updraft_split_every" value="<?php echo $split_every_mb ?>" size="5" /> MB<br><?php echo sprintf(__('UpdraftPlus will split up backup archives when they exceed this file size. The default value is %s megabytes. Be careful to leave some margin if your web-server has a hard size limit (e.g. the 2 GB / 2048 MB limit on some 32-bit servers/file systems).','updraftplus'), 400); ?></td>
			</tr>

			<tr class="deletelocal expertmode updraft-hidden" style="display:none;">
				<th><?php _e('Delete local backup','updraftplus');?>:</th>
				<td><input type="checkbox" id="updraft_delete_local" name="updraft_delete_local" value="1" <?php if ($delete_local) echo 'checked="checked"'; ?>> <br><label for="updraft_delete_local"><?php _e('Check this to delete any superfluous backup files from your server after the backup run finishes (i.e. if you uncheck, then any files despatched remotely will also remain locally, and any files being kept locally will not be subject to the retention limits).','updraftplus');?></label></td>
			</tr>

			<tr class="expertmode backupdirrow updraft-hidden" style="display:none;">
				<th><?php _e('Backup directory','updraftplus');?>:</th>
				<td><input type="text" name="updraft_dir" id="updraft_dir" style="width:525px" value="<?php echo htmlspecialchars($this->prune_updraft_dir_prefix($updraft_dir)); ?>" /></td>
			</tr>
			<tr class="expertmode backupdirrow updraft-hidden" style="display:none;">
				<td></td>
				<td>
					<span id="updraft_writable_mess">
						<?php
						$dir_info = $this->really_writable_message($really_is_writable, $updraft_dir);
						echo $dir_info;
						?>
					</span>
						<?php
						 echo __("This is where UpdraftPlus will write the zip files it creates initially.  This directory must be writable by your web server. It is relative to your content directory (which by default is called wp-content).", 'updraftplus').' '.__("<b>Do not</b> place it inside your uploads or plugins directory, as that will cause recursion (backups of backups of backups of...).", 'updraftplus');
						 ?>
					</td>
			</tr>

			<tr class="expertmode updraft-hidden" style="display:none;">
				<th><?php _e("Use the server's SSL certificates", 'updraftplus');?>:</th>
				<td><input data-updraft_settings_test="useservercerts" type="checkbox" id="updraft_ssl_useservercerts" name="updraft_ssl_useservercerts" value="1" <?php if (UpdraftPlus_Options::get_updraft_option('updraft_ssl_useservercerts')) echo 'checked="checked"'; ?>> <br><label for="updraft_ssl_useservercerts"><?php _e('By default UpdraftPlus uses its own store of SSL certificates to verify the identity of remote sites (i.e. to make sure it is talking to the real Dropbox, Amazon S3, etc., and not an attacker). We keep these up to date. However, if you get an SSL error, then choosing this option (which causes UpdraftPlus to use your web server\'s collection instead) may help.','updraftplus');?></label></td>
			</tr>

			<tr class="expertmode updraft-hidden" style="display:none;">
				<th><?php _e('Do not verify SSL certificates','updraftplus');?>:</th>
				<td><input data-updraft_settings_test="disableverify" type="checkbox" id="updraft_ssl_disableverify" name="updraft_ssl_disableverify" value="1" <?php if (UpdraftPlus_Options::get_updraft_option('updraft_ssl_disableverify')) echo 'checked="checked"'; ?>> <br><label for="updraft_ssl_disableverify"><?php _e('Choosing this option lowers your security by stopping UpdraftPlus from verifying the identity of encrypted sites that it connects to (e.g. Dropbox, Google Drive). It means that UpdraftPlus will be using SSL only for encryption of traffic, and not for authentication.','updraftplus');?> <?php _e('Note that not all cloud backup methods are necessarily using SSL authentication.', 'updraftplus');?></label></td>
			</tr>

			<tr class="expertmode updraft-hidden" style="display:none;">
				<th><?php _e('Disable SSL entirely where possible', 'updraftplus');?>:</th>
				<td><input data-updraft_settings_test="nossl" type="checkbox" id="updraft_ssl_nossl" name="updraft_ssl_nossl" value="1" <?php if (UpdraftPlus_Options::get_updraft_option('updraft_ssl_nossl')) echo 'checked="checked"'; ?>> <br><label for="updraft_ssl_nossl"><?php _e('Choosing this option lowers your security by stopping UpdraftPlus from using SSL for authentication and encrypted transport at all, where possible. Note that some cloud storage providers do not allow this (e.g. Dropbox), so with those providers this setting will have no effect.','updraftplus');?> <a href="https://updraftplus.com/faqs/i-get-ssl-certificate-errors-when-backing-up-andor-restoring/"><?php _e('See this FAQ also.', 'updraftplus');?></a></label></td>
			</tr>

			<?php do_action('updraftplus_configprint_expertoptions'); ?>

			<tr>
			<td></td>
			<td>
				<?php
					$ws_ad = empty($options['include_adverts']) ? false : $updraftplus->wordshell_random_advert(1);
					if ($ws_ad) {
					?>
					<p class="wordshell-advert">
						<?php echo $ws_ad; ?>
					</p>
					<?php } ?>
				</td>
			</tr>
			<?php if (!empty($options['include_save_button'])) { ?>
			<tr>
				<td></td>
				<td>
					<input type="hidden" name="action" value="update" />
					<input type="submit" class="button-primary" id="updraftplus-settings-save" value="<?php _e('Save Changes','updraftplus');?>" />
				</td>
			</tr>
			<?php } ?>
		</table>
		<?php
	}

	private function get_settings_js($method_objects, $really_is_writable, $updraft_dir) {

		global $updraftplus;
		
		ob_start();
		?>
		jQuery(document).ready(function() {
			<?php
				if (!$really_is_writable) echo "jQuery('.backupdirrow').show();\n";
			?>
			<?php
				if (!empty($active_service)) {
					if (is_array($active_service)) {
						foreach ($active_service as $serv) {
							echo "jQuery('.${serv}').show();\n";
						}
					} else {
						echo "jQuery('.${active_service}').show();\n";
					}
				} else {
					echo "jQuery('.none').show();\n";
				}
				foreach ($updraftplus->backup_methods as $method => $description) {
					// already done: require_once(UPDRAFTPLUS_DIR.'/methods/'.$method.'.php');
					$call_method = "UpdraftPlus_BackupModule_$method";
					if (method_exists($call_method, 'config_print_javascript_onready')) {
						$method_objects[$method]->config_print_javascript_onready();
					}
				}
			?>
		});
		<?php
		$ret = ob_get_contents();
		ob_end_clean();
		return $ret;
	}
	
	// $include_more can be (bool) or (string)"sometimes"
	public function files_selector_widgetry($prefix = '', $show_exclusion_options = true, $include_more = true) {

		$ret = '';

		global $updraftplus;
		$backupable_entities = $updraftplus->get_backupable_file_entities(true, true);
		# The true (default value if non-existent) here has the effect of forcing a default of on.
		$include_more_paths = UpdraftPlus_Options::get_updraft_option('updraft_include_more_path');
		foreach ($backupable_entities as $key => $info) {
			$included = (UpdraftPlus_Options::get_updraft_option("updraft_include_$key", apply_filters("updraftplus_defaultoption_include_".$key, true))) ? 'checked="checked"' : "";
			if ('others' == $key || 'uploads' == $key) {

				$data_toggle_exclude_field = $show_exclusion_options ? 'data-toggle_exclude_field="'.$key.'"' : '';
			
				$ret .= '<input class="updraft_include_entity" id="'.$prefix.'updraft_include_'.$key.'" '.$data_toggle_exclude_field.' type="checkbox" name="updraft_include_'.$key.'" value="1" '.$included.'> <label '.(('others' == $key) ? 'title="'.sprintf(__('Your wp-content directory server path: %s', 'updraftplus'), WP_CONTENT_DIR).'" ' : '').' for="'.$prefix.'updraft_include_'.$key.'">'.(('others' == $key) ? __('Any other directories found inside wp-content', 'updraftplus') : htmlspecialchars($info['description'])).'</label><br>';
				
				if ($show_exclusion_options) {
					$include_exclude = UpdraftPlus_Options::get_updraft_option('updraft_include_'.$key.'_exclude', ('others' == $key) ? UPDRAFT_DEFAULT_OTHERS_EXCLUDE : UPDRAFT_DEFAULT_UPLOADS_EXCLUDE);

					$display = ($included) ? '' : 'class="updraft-hidden" style="display:none;"';

					$ret .= "<div id=\"".$prefix."updraft_include_".$key."_exclude\" $display>";

					$ret .= '<label for="'.$prefix.'updraft_include_'.$key.'_exclude">'.__('Exclude these:', 'updraftplus').'</label>';

					$ret .= '<input title="'.__('If entering multiple files/directories, then separate them with commas. For entities at the top level, you can use a * at the start or end of the entry as a wildcard.', 'updraftplus').'" type="text" id="'.$prefix.'updraft_include_'.$key.'_exclude" name="updraft_include_'.$key.'_exclude" size="54" value="'.htmlspecialchars($include_exclude).'" />';

					$ret .= '<br></div>';
				}

			} else {

				if ($key != 'more' || true === $include_more || ('sometimes' === $include_more && !empty($include_more_paths))) {
				
					$data_toggle_exclude_field = $show_exclusion_options ? 'data-toggle_exclude_field="'.$key.'"' : '';
				
					$ret .= "<input class=\"updraft_include_entity\" $data_toggle_exclude_field id=\"".$prefix."updraft_include_$key\" type=\"checkbox\" name=\"updraft_include_$key\" value=\"1\" $included /><label for=\"".$prefix."updraft_include_$key\"".((isset($info['htmltitle'])) ? ' title="'.htmlspecialchars($info['htmltitle']).'"' : '')."> ".htmlspecialchars($info['description']);

					$ret .= "</label><br>";
					$ret .= apply_filters("updraftplus_config_option_include_$key", '', $prefix);
				}
			}
		}

		return $ret;
	}

	public function show_double_warning($text, $extraclass = '', $echo = true) {

		$ret = "<div class=\"error updraftplusmethod $extraclass\"><p>$text</p></div>";
		$ret .= "<p class=\"double-warning\">$text</p>";

		if ($echo) echo $ret;
		return $ret;

	}

	public function optionfilter_split_every($value) {
		$value = absint($value);
		if ($value < UPDRAFTPLUS_SPLIT_MIN) $value = UPDRAFTPLUS_SPLIT_MIN;
		return $value;
	}

	public function curl_check($service, $has_fallback = false, $extraclass = '', $echo = true) {

		$ret = '';

		// Check requirements
		if (!function_exists("curl_init") || !function_exists('curl_exec')) {
		
			$ret .= $this->show_double_warning('<strong>'.__('Warning','updraftplus').':</strong> '.sprintf(__("Your web server's PHP installation does not included a <strong>required</strong> (for %s) module (%s). Please contact your web hosting provider's support and ask for them to enable it.", 'updraftplus'), $service, 'Curl').' ', $extraclass, false);

		} else {
			$curl_version = curl_version();
			$curl_ssl_supported= ($curl_version['features'] & CURL_VERSION_SSL);
			if (!$curl_ssl_supported) {
				if ($has_fallback) {
					$ret .= '<p><strong>'.__('Warning','updraftplus').':</strong> '.sprintf(__("Your web server's PHP/Curl installation does not support https access. Communications with %s will be unencrypted. ask your web host to install Curl/SSL in order to gain the ability for encryption (via an add-on).",'updraftplus'),$service).'</p>';
				} else {
					$ret .= $this->show_double_warning('<p><strong>'.__('Warning','updraftplus').':</strong> '.sprintf(__("Your web server's PHP/Curl installation does not support https access. We cannot access %s without this support. Please contact your web hosting provider's support. %s <strong>requires</strong> Curl+https. Please do not file any support requests; there is no alternative.",'updraftplus'),$service).'</p>', $extraclass, false);
				}
			} else {
				$ret .= '<p><em>'.sprintf(__("Good news: Your site's communications with %s can be encrypted. If you see any errors to do with encryption, then look in the 'Expert Settings' for more help.", 'updraftplus'),$service).'</em></p>';
			}
		}
		if ($echo) {
			echo $ret;
		} else {
			return $ret;
		}
	}

	# If $basedirs is passed as an array, then $directorieses must be too
	private function recursive_directory_size($directorieses, $exclude = array(), $basedirs = '', $format='text') {

		$size = 0;

		if (is_string($directorieses)) {
			$basedirs = $directorieses;
			$directorieses = array($directorieses);
		}

		if (is_string($basedirs)) $basedirs = array($basedirs);

		foreach ($directorieses as $ind => $directories) {
			if (!is_array($directories)) $directories=array($directories);

			$basedir = empty($basedirs[$ind]) ? $basedirs[0] : $basedirs[$ind];

			foreach ($directories as $dir) {
				if (is_file($dir)) {
					$size += @filesize($dir);
				} else {
					$suffix = ('' != $basedir) ? ((0 === strpos($dir, $basedir.'/')) ? substr($dir, 1+strlen($basedir)) : '') : '';
					$size += $this->recursive_directory_size_raw($basedir, $exclude, $suffix);
				}
			}

		}

		if ('numeric' == $format) return $size;

		global $updraftplus;
		return $updraftplus->convert_numeric_size_to_text($size);

	}

	private function recursive_directory_size_raw($prefix_directory, &$exclude = array(), $suffix_directory = '') {

		$directory = $prefix_directory.('' == $suffix_directory ? '' : '/'.$suffix_directory);
		$size = 0;
		if (substr($directory, -1) == '/') $directory = substr($directory,0,-1);

		if (!file_exists($directory) || !is_dir($directory) || !is_readable($directory)) return -1;
		if (file_exists($directory.'/.donotbackup')) return 0;

		if ($handle = opendir($directory)) {
			while (($file = readdir($handle)) !== false) {
				if ($file != '.' && $file != '..') {
					$spath = ('' == $suffix_directory) ? $file : $suffix_directory.'/'.$file;
					if (false !== ($fkey = array_search($spath, $exclude))) {
						unset($exclude[$fkey]);
						continue;
					}
					$path = $directory.'/'.$file;
					if (is_file($path)) {
						$size += filesize($path);
					} elseif (is_dir($path)) {
						$handlesize = $this->recursive_directory_size_raw($prefix_directory, $exclude, $suffix_directory.('' == $suffix_directory ? '' : '/').$file);
						if ($handlesize >= 0) { $size += $handlesize; }# else { return -1; }
					}
				}
			}
			closedir($handle);
		}

		return $size;

	}

	private function raw_backup_info($backup_history, $key, $nonce) {

		global $updraftplus;

		$backup = $backup_history[$key];

		$pretty_date = get_date_from_gmt(gmdate('Y-m-d H:i:s', (int)$key), 'M d, Y G:i');

		$rawbackup = "<h2 title=\"$key\">$pretty_date</h2>";

		if (!empty($backup['label'])) $rawbackup .= '<span class="raw-backup-info">'.$backup['label'].'</span>';

		$rawbackup .= '<hr><p>';

		$backupable_entities = $updraftplus->get_backupable_file_entities(true, true);

		if (!empty($nonce)) {
			$jd = $updraftplus->jobdata_getarray($nonce);
		} else {
			$jd = array();
		}

		foreach ($backupable_entities as $type => $info) {
			if (!isset($backup[$type])) continue;

			$rawbackup .= $updraftplus->printfile($info['description'], $backup, $type, array('sha1'), $jd, true);

// 			$rawbackup .= '<h3>'.$info['description'].'</h3>';
// 			$files = is_string($backup[$type]) ? array($backup[$type]) : $backup[$type];
// 			foreach ($files as $index => $file) {
// 				$rawbackup .= $file.'<br>';
// 			}
		}

		$total_size = 0;
		foreach ($backup as $ekey => $files) {
			if ('db' == strtolower(substr($ekey, 0, 2)) && '-size' != substr($ekey, -5, 5)) {
				$rawbackup .= $updraftplus->printfile(__('Database', 'updraftplus'), $backup, $ekey, array('sha1'), $jd, true);
			}
			if (!isset($backupable_entities[$ekey]) && ('db' != substr($ekey, 0, 2) || '-size' == substr($ekey, -5, 5))) continue;
			if (is_string($files)) $files = array($files);
			foreach ($files as $findex => $file) {
				$size_key = (0 == $findex) ? $ekey.'-size' : $ekey.$findex.'-size';
				$total_size = (false === $total_size || !isset($backup[$size_key]) || !is_numeric($backup[$size_key])) ? false : $total_size + $backup[$size_key];
			}
		}

		$services = empty($backup['service']) ? array('none') : $backup['service'];
		if (!is_array($services)) $services = array('none');

		$rawbackup .= '<strong>'.__('Uploaded to:', 'updraftplus').'</strong> ';

		$show_services = '';
		foreach ($services as $serv) {
			if ('none' == $serv || '' == $serv) {
				$add_none = true;
			} elseif (isset($updraftplus->backup_methods[$serv])) {
				$show_services .= ($show_services) ? ', '.$updraftplus->backup_methods[$serv] : $updraftplus->backup_methods[$serv];
			} else {
				$show_services .= ($show_services) ? ', '.$serv : $serv;
			}
		}
		if ('' == $show_services && $add_none) $show_services .= __('None', 'updraftplus');

		$rawbackup .= $show_services;

		if ($total_size !== false) {
			$rawbackup .= '</p><strong>'.__('Total backup size:', 'updraftplus').'</strong> '.$updraftplus->convert_numeric_size_to_text($total_size).'<p>';
		}
		

		
		$rawbackup .= '</p><hr><p><pre>'.print_r($backup, true).'</p></pre>';

		if (!empty($jd) && is_array($jd)) {
			$rawbackup .= '<p><pre>'.print_r($jd, true).'</pre></p>';
		}

		return esc_attr($rawbackup);
	}

	private function existing_backup_table($backup_history = false) {

		global $updraftplus;

		if (false === $backup_history) $backup_history = UpdraftPlus_Options::get_updraft_option('updraft_backup_history');
		if (!is_array($backup_history)) $backup_history=array();
		if (empty($backup_history)) return "<p><em>".__('You have not yet made any backups.', 'updraftplus')."</em></p>";

		$updraft_dir = $updraftplus->backups_dir_location();
		$backupable_entities = $updraftplus->get_backupable_file_entities(true, true);

		$accept = apply_filters('updraftplus_accept_archivename', array());
		if (!is_array($accept)) $accept = array();

		$ret = '<table class="existing-backups-table">';

		//".__('Actions', 'updraftplus')."
		$ret .= "<thead>
			<tr style=\"margin-bottom: 4px;\">
				<th class=\"backup-date\">".__('Backup date', 'updraftplus')."</th>
				<th class=\"backup-data\">".__('Backup data (click to download)', 'updraftplus')."</th>
				<th class=\"updraft_backup_actions\">".__('Actions', 'updraftplus')."</th>
			</tr>
			<tr style=\"height:2px; padding:1px; margin:0px;\">
				<td colspan=\"4\" style=\"margin:0; padding:0\"><div style=\"height: 2px; background-color:#888888;\">&nbsp;</div></td>
			</tr>
		</thead>
		<tbody>";
// 		$ret .= "<thead>
// 		</thead>
// 		<tbody>";

		krsort($backup_history);
		foreach ($backup_history as $key => $backup) {

			$remote_sent = (!empty($backup['service']) && ((is_array($backup['service']) && in_array('remotesend', $backup['service'])) || 'remotesend' === $backup['service'])) ? true : false;

			# https://core.trac.wordpress.org/ticket/25331 explains why the following line is wrong
			# $pretty_date = date_i18n('Y-m-d G:i',$key);
			// Convert to blog time zone
// 			$pretty_date = get_date_from_gmt(gmdate('Y-m-d H:i:s', (int)$key), 'Y-m-d G:i');
			$pretty_date = get_date_from_gmt(gmdate('Y-m-d H:i:s', (int)$key), 'M d, Y G:i');

			$esc_pretty_date = esc_attr($pretty_date);
			$entities = '';

			$non = $backup['nonce'];
			$rawbackup = $this->raw_backup_info($backup_history, $key, $non);

			$jobdata = $updraftplus->jobdata_getarray($non);

			$delete_button = $this->delete_button($key, $non, $backup);

			$date_label = $this->date_label($pretty_date, $key, $backup, $jobdata, $non);

			$log_button = $this->log_button($backup);

			// Remote backups with no log result in useless empty rows
			// However, not showing anything messes up the "Existing Backups (14)" display, until we tweak that code to count differently
// 			if ($remote_sent && !$log_button) continue;

			$ret .= <<<ENDHERE
			<tr class="updraft_existing_backups_row updraft_existing_backups_row_$key" data-key="$key" data-nonce="$non">

			<td class="updraft_existingbackup_date " data-rawbackup="$rawbackup">
				$date_label
			</td>
ENDHERE;

			$ret .= "<td>";

			if ($remote_sent) {

				$ret .= __('Backup sent to remote site - not available for download.', 'updraftplus');
				if (!empty($backup['remotesend_url'])) $ret .= '<br>'.__('Site', 'updraftplus').': '.htmlspecialchars($backup['remotesend_url']);

			} else {

				if (empty($backup['meta_foreign']) || !empty($accept[$backup['meta_foreign']]['separatedb'])) {

					if (isset($backup['db'])) {
						$entities .= '/db=0/';

						// Set a flag according to whether or not $backup['db'] ends in .crypt, then pick this up in the display of the decrypt field.
						$db = is_array($backup['db']) ? $backup['db'][0] : $backup['db'];
						if ($updraftplus->is_db_encrypted($db)) $entities .= '/dbcrypted=1/';

						$ret .= $this->download_db_button('db', $key, $esc_pretty_date, $backup, $accept);
					} else {
	// 					$ret .= sprintf(_x('(No %s)','Message shown when no such object is available','updraftplus'), __('database', 'updraftplus'));
					}

					# External databases
					foreach ($backup as $bkey => $binfo) {
						if ('db' == $bkey || 'db' != substr($bkey, 0, 2) || '-size' == substr($bkey, -5, 5)) continue;
						$ret .= $this->download_db_button($bkey, $key, $esc_pretty_date, $backup);
					}

				} else {
					# Foreign without separate db
					$entities = '/db=0/meta_foreign=1/';
				}

				if (!empty($backup['meta_foreign']) && !empty($accept[$backup['meta_foreign']]) && !empty($accept[$backup['meta_foreign']]['separatedb'])) {
					$entities .= '/meta_foreign=2/';
				}

				$download_buttons = $this->download_buttons($backup, $key, $accept, $entities, $esc_pretty_date);

				$ret .= $download_buttons;

	// 			$ret .="</td>";

				// No logs expected for foreign backups
				if (empty($backup['meta_foreign'])) {
	// 				$ret .= '<td>'.$this->log_button($backup)."</td>";
				}
			}

			$ret .= "</td>";

			$ret .= '<td class="before-restore-button">';
			$ret .= $this->restore_button($backup, $key, $pretty_date, $entities);
			$ret .= $delete_button;
			if (empty($backup['meta_foreign'])) $ret .= $log_button;
			$ret .= '</td>';

			$ret .= '</tr>';

			$ret .= "<tr style=\"height:2px; padding:1px; margin:0px;\"><td colspan=\"4\" style=\"margin:0; padding:0\"><div style=\"height: 2px; background-color:#aaaaaa;\">&nbsp;</div></td></tr>";

		}

		$ret .= '</tbody></table>';
		return $ret;
	}

	private function download_db_button($bkey, $key, $esc_pretty_date, $backup, $accept = array()) {

		if (!empty($backup['meta_foreign']) && isset($accept[$backup['meta_foreign']])) {
			$desc_source = $accept[$backup['meta_foreign']]['desc'];
		} else {
			$desc_source = __('unknown source', 'updraftplus');
		}

		$ret = '';

		if ('db' == $bkey) {
			$dbt = empty($backup['meta_foreign']) ? esc_attr(__('Database','updraftplus')) : esc_attr(sprintf(__('Database (created by %s)', 'updraftplus'), $desc_source));
		} else {
			$dbt = __('External database','updraftplus').' ('.substr($bkey, 2).')';
		}

		$ret .= $this->download_button($bkey, $key, 0, null, '', $dbt, $esc_pretty_date, '0');
		
		return $ret;
	}

	// Go through each of the file entities
	private function download_buttons($backup, $key, $accept, &$entities, $esc_pretty_date) {
		global $updraftplus;
		$ret = '';
		$backupable_entities = $updraftplus->get_backupable_file_entities(true, true);

// 		$colspan = 1;
// 		if (!empty($backup['meta_foreign'])) {
// 			$colspan = 2;
// 			if (empty($accept[$backup['meta_foreign']]['separatedb'])) $colspan++;
// 		}
// 		$ret .= (1 == $colspan) ? '<td>' : '<td colspan="'.$colspan.'">';

		$first_entity = true;

		foreach ($backupable_entities as $type => $info) {
			if (!empty($backup['meta_foreign']) && 'wpcore' != $type) continue;
// 			$colspan = 1;
// 			if (!empty($backup['meta_foreign'])) {
// 				$colspan = (1+count($backupable_entities));
// 				if (empty($accept[$backup['meta_foreign']]['separatedb'])) $colspan++;
// 			}
// 			$ret .= (1 == $colspan) ? '<td>' : '<td colspan="'.$colspan.'">';
			$ide = '';
			if ('wpcore' == $type) $wpcore_restore_descrip = $info['description'];
			if (empty($backup['meta_foreign'])) {
				$sdescrip = preg_replace('/ \(.*\)$/', '', $info['description']);
				if (strlen($sdescrip) > 20 && isset($info['shortdescription'])) $sdescrip = $info['shortdescription'];
			} else {
				$info['description'] = 'WordPress';

				if (isset($accept[$backup['meta_foreign']])) {
					$desc_source = $accept[$backup['meta_foreign']]['desc'];
					$ide .= sprintf(__('Backup created by: %s.', 'updraftplus'), $accept[$backup['meta_foreign']]['desc']).' ';
				} else {
					$desc_source = __('unknown source', 'updraftplus');
					$ide .= __('Backup created by unknown source (%s) - cannot be restored.', 'updraftplus').' ';
				}

				$sdescrip = (empty($accept[$backup['meta_foreign']]['separatedb'])) ? sprintf(__('Files and database WordPress backup (created by %s)', 'updraftplus'), $desc_source) : sprintf(__('Files backup (created by %s)', 'updraftplus'), $desc_source);
				if ('wpcore' == $type) $wpcore_restore_descrip = $sdescrip;
			}
			if (isset($backup[$type])) {
				if (!is_array($backup[$type])) $backup[$type]=array($backup[$type]);
				$howmanyinset = count($backup[$type]);
				$expected_index = 0;
				$index_missing = false;
				$set_contents = '';
				$entities .= "/$type=";
				$whatfiles = $backup[$type];
				ksort($whatfiles);
				foreach ($whatfiles as $findex => $bfile) {
					$set_contents .= ($set_contents == '') ? $findex : ",$findex";
					if ($findex != $expected_index) $index_missing = true;
					$expected_index++;
				}
				$entities .= $set_contents.'/';
				if (!empty($backup['meta_foreign'])) {
					$entities .= '/plugins=0//themes=0//uploads=0//others=0/';
				}
				$first_printed = true;
				foreach ($whatfiles as $findex => $bfile) {
					$ide .= __('Press here to download', 'updraftplus').' '.strtolower($info['description']);
					$pdescrip = ($findex > 0) ? $sdescrip.' ('.($findex+1).')' : $sdescrip;
					if (!$first_printed) {
						$ret .= '<div class="updraft-hidden" style="display:none;">';
					}
					if (count($backup[$type]) >0) {
						$ide .= ' '.sprintf(__('(%d archive(s) in set).', 'updraftplus'), $howmanyinset);
					}
					if ($index_missing) {
						$ide .= ' '.__('You appear to be missing one or more archives from this multi-archive set.', 'updraftplus');
					}

					if (!$first_entity) {
// 						$ret .= ', ';
					} else {
						$first_entity = false;
					} 

					$ret .= $this->download_button($type, $key, $findex, $info, $ide, $pdescrip, $esc_pretty_date, $set_contents);

					if (!$first_printed) {
						$ret .= '</div>';
					} else {
						$first_printed = false;
					}
				}
			} else {
// 				$ret .= sprintf(_x('(No %s)','Message shown when no such object is available','updraftplus'), preg_replace('/\s\(.{12,}\)/', '', strtolower($sdescrip)));
			}
// 			$ret .= '</td>';
		}
// 			$ret .= '</td>';
		return $ret;
	}

	public function date_label($pretty_date, $key, $backup, $jobdata, $nonce, $simple_format = false) {
// 		$ret = apply_filters('updraftplus_showbackup_date', '<strong>'.$pretty_date.'</strong>', $backup, $jobdata, (int)$key);
		$ret = apply_filters('updraftplus_showbackup_date', $pretty_date, $backup, $jobdata, (int)$key, $simple_format);
		if (is_array($jobdata) && !empty($jobdata['resume_interval']) && (empty($jobdata['jobstatus']) || 'finished' != $jobdata['jobstatus'])) {
			if ($simple_format) {
				$ret .= ' '.__('(Not finished)', 'updraftplus');
			} else {
				$ret .= apply_filters('updraftplus_msg_unfinishedbackup', "<br><span title=\"".esc_attr(__('If you are seeing more backups than you expect, then it is probably because the deletion of old backup sets does not happen until a fresh backup completes.', 'updraftplus'))."\">".__('(Not finished)', 'updraftplus').'</span>', $jobdata, $nonce);
			}
		}
		return $ret;
	}

	private function download_button($type, $backup_timestamp, $findex, $info, $ide, $pdescrip, $esc_pretty_date, $set_contents) {
	
		$ret = '';

		$wp_nonce = wp_create_nonce('updraftplus_download');
		
		// updraft_downloader(base, backup_timestamp, what, whicharea, set_contents, prettydate, async)
		$ret .= '<button data-wp_nonce="'.esc_attr($wp_nonce).'" data-backup_timestamp="'.esc_attr($backup_timestamp).'" data-what="'.esc_attr($type).'" data-set_contents="'.esc_attr($set_contents).'" data-prettydate="'.esc_attr($esc_pretty_date).'" type="button" class="updraft_download_button '."uddownloadform_${type}_${backup_timestamp}_${findex}".'" title="'.$ide.'">'.$pdescrip.'</button>';
		// onclick="'."return updraft_downloader('uddlstatus_', '$backup_timestamp', '$type', '.ud_downloadstatus', '$set_contents', '$esc_pretty_date', true)".'"
				
				
		// Pre 1.11.24
// 		$nonce_field = wp_nonce_field('updraftplus_download', '_wpnonce', true, false);
// 		$ret .= <<<ENDHERE
// 				<form class="uddownloadform_${type}_${backup_timestamp}_${findex}" action="admin-ajax.php" onsubmit="return updraft_downloader('uddlstatus_', '$backup_timestamp', '$type', '.ud_downloadstatus', '$set_contents', '$esc_pretty_date', true)" method="post">
// 					$nonce_field
// 					<input type="hidden" name="action" value="updraft_download_backup" />
// 					<input type="hidden" name="type" value="$type" />
// 					<input type="hidden" name="timestamp" value="$backup_timestamp" />
// 					<input type="hidden" name="findex" value="$findex" />
// 					<input type="submit" class="updraft-backupentitybutton" title="$ide" value="$pdescrip" />
// 				</form>
// 			</div>
// ENDHERE;
		return $ret;
	}

	private function restore_button($backup, $key, $pretty_date, $entities = '') {
		$ret = '<div class="restore-button">';

		if ($entities) {
			$show_data = $pretty_date;
			if (isset($backup['native']) && false == $backup['native']) {
				$show_data .= ' '.__('(backup set imported from remote location)', 'updraftplus');
			}

			$ret .= '<button data-showdata="'.esc_attr($show_data).'" data-backup_timestamp="'.$key.'" data-entities="'.esc_attr($entities).'" title="'.__('After pressing this button, you will be given the option to choose which components you wish to restore','updraftplus').'" type="button" style="float:left; clear:none;" class="button-primary choose-components-button">'.__('Restore', 'updraftplus').'</button>';
		}
		$ret .= "</div>\n";
		return $ret;
	}

	private function delete_button($key, $nonce, $backup) {
		$sval = ((isset($backup['service']) && $backup['service'] != 'email' && $backup['service'] != 'none')) ? '1' : '0';
		return '<div class="updraftplus-remove" style="float: left; clear: none;" data-hasremote="'.$sval.'">
					<a data-hasremote="'.$sval.'" data-nonce="'.$nonce.'" data-key="'.$key.'" class="no-decoration updraft-delete-link" href="#" title="'.esc_attr(__('Delete this backup set', 'updraftplus')).'">'.__('Delete', 'updraftplus').'</a>
				</div>';
	}

	private function log_button($backup) {
		global $updraftplus;
		$updraft_dir = $updraftplus->backups_dir_location();
		$ret = '';
		if (isset($backup['nonce']) && preg_match("/^[0-9a-f]{12}$/",$backup['nonce']) && is_readable($updraft_dir.'/log.'.$backup['nonce'].'.txt')) {
			$nval = $backup['nonce'];
// 			$lt = esc_attr(__('View Log','updraftplus'));
			$lt = __('View Log', 'updraftplus');
			$url = esc_attr(UpdraftPlus_Options::admin_page()."?page=updraftplus&action=downloadlog&amp;updraftplus_backup_nonce=$nval");
			$ret .= <<<ENDHERE
				<div style="clear:none;" class="updraft-viewlogdiv">
					<a class="no-decoration updraft-log-link" href="$url" data-jobid="$nval">
						$lt
					</a>
					<!--
					<form action="$url" method="get">
						<input type="hidden" name="action" value="downloadlog" />
						<input type="hidden" name="page" value="updraftplus" />
						<input type="hidden" name="updraftplus_backup_nonce" value="$nval" />
						<input type="submit" value="$lt" class="updraft-log-link" onclick="event.preventDefault(); updraft_popuplog('$nval');" />
					</form>
					-->
				</div>
ENDHERE;
			return $ret;
		} else {
// 			return str_replace(' ', '&nbsp;', '('.__('No backup log)', 'updraftplus').')');
		}
	}

	// Return values: false = 'not yet' (not necessarily terminal); WP_Error = terminal failure; true = success
	private function restore_backup($timestamp, $continuation_data = null) {

		@set_time_limit(UPDRAFTPLUS_SET_TIME_LIMIT);

		global $wp_filesystem, $updraftplus;
		$backup_history = UpdraftPlus_Options::get_updraft_option('updraft_backup_history');
		if (!isset($backup_history[$timestamp]) || !is_array($backup_history[$timestamp])) {
			echo '<p>'.__('This backup does not exist in the backup history - restoration aborted. Timestamp:','updraftplus')." $timestamp</p><br/>";
			return new WP_Error('does_not_exist', __('Backup does not exist in the backup history', 'updraftplus'));
		}

		// request_filesystem_credentials passes on fields just via hidden name/value pairs.
		// Build array of parameters to be passed via this
		$extra_fields = array();
		if (isset($_POST['updraft_restore']) && is_array($_POST['updraft_restore'])) {
			foreach ($_POST['updraft_restore'] as $entity) {
				$_POST['updraft_restore_'.$entity] = 1;
				$extra_fields[] = 'updraft_restore_'.$entity;
			}
		}

		if (is_array($continuation_data)) {
			foreach ($continuation_data['second_loop_entities'] as $type => $files) {
				$_POST['updraft_restore_'.$type] = 1;
				if (!in_array('updraft_restore_'.$type, $extra_fields)) $extra_fields[] = 'updraft_restore_'.$type;
			}
			if (!empty($continuation_data['restore_options'])) $restore_options = $continuation_data['restore_options'];
		}

		// Now make sure that updraft_restorer_ option fields get passed along to request_filesystem_credentials
		foreach ($_POST as $key => $value) {
			if (0 === strpos($key, 'updraft_restorer_')) $extra_fields[] = $key;
		}

		$credentials = request_filesystem_credentials(UpdraftPlus_Options::admin_page()."?page=updraftplus&action=updraft_restore&backup_timestamp=$timestamp", '', false, false, $extra_fields);
		WP_Filesystem($credentials);
		if ( $wp_filesystem->errors->get_error_code() ) { 
			echo '<p><em><a href="https://updraftplus.com/faqs/asked-ftp-details-upon-restorationmigration-updates/">'.__('Why am I seeing this?', 'updraftplus').'</a></em></p>';
			foreach ( $wp_filesystem->errors->get_error_messages() as $message ) show_message($message); 
			exit;
		}

		// If we make it this far then WP_Filesystem has been instantiated and is functional

		# Set up logging
		$updraftplus->backup_time_nonce();
		$updraftplus->jobdata_set('job_type', 'restore');
		$updraftplus->jobdata_set('job_time_ms', $updraftplus->job_time_ms);
		$updraftplus->logfile_open($updraftplus->nonce);

		# Provide download link for the log file

		# TODO: Automatic purging of old log files
		# TODO: Provide option to auto-email the log file

		echo '<h1>'.__('UpdraftPlus Restoration: Progress', 'updraftplus').'</h1><div id="updraft-restore-progress">';

		$this->show_admin_warning('<a target="_blank" href="?action=downloadlog&page=updraftplus&updraftplus_backup_nonce='.htmlspecialchars($updraftplus->nonce).'">'.__('Follow this link to download the log file for this restoration (needed for any support requests).', 'updraftplus').'</a>');

		$updraft_dir = trailingslashit($updraftplus->backups_dir_location());
		$foreign_known = apply_filters('updraftplus_accept_archivename', array());

		$service = (isset($backup_history[$timestamp]['service'])) ? $backup_history[$timestamp]['service'] : false;
		if (!is_array($service)) $service = array($service);

		// Now, need to turn any updraft_restore_<entity> fields (that came from a potential WP_Filesystem form) back into parts of the _POST array (which we want to use)
		if (empty($_POST['updraft_restore']) || (!is_array($_POST['updraft_restore']))) $_POST['updraft_restore'] = array();

		$backup_set = $backup_history[$timestamp];
		$entities_to_restore = array();
		foreach ($_POST['updraft_restore'] as $entity) {
			if (empty($backup_set['meta_foreign'])) {
				$entities_to_restore[$entity] = $entity;
			} else {
				if ('db' == $entity && !empty($foreign_known[$backup_set['meta_foreign']]) && !empty($foreign_known[$backup_set['meta_foreign']]['separatedb'])) {
					$entities_to_restore[$entity] = 'db';
				} else {
					$entities_to_restore[$entity] = 'wpcore';
				}
			}
		}

		foreach ($_POST as $key => $value) {
			if (0 === strpos($key, 'updraft_restore_')) {
				$nkey = substr($key, 16);
				if (!isset($entities_to_restore[$nkey])) {
					$_POST['updraft_restore'][] = $nkey;
					if (empty($backup_set['meta_foreign'])) {
						$entities_to_restore[$nkey] = $nkey;
					} else {
						if ('db' == $entity && !empty($foreign_known[$backup_set['meta_foreign']]['separatedb'])) {
							$entities_to_restore[$nkey] = 'db';
						} else {
							$entities_to_restore[$nkey] = 'wpcore';
						}
					}
				}
			}
		}

		if (0 == count($_POST['updraft_restore'])) {
			echo '<p>'.__('ABORT: Could not find the information on which entities to restore.', 'updraftplus').'</p>';
			echo '<p>'.__('If making a request for support, please include this information:','updraftplus').' '.count($_POST).' : '.htmlspecialchars(serialize($_POST)).'</p>';
			return new WP_Error('missing_info', 'Backup information not found');
		}

		$this->entities_to_restore = $entities_to_restore;

		set_error_handler(array($updraftplus, 'php_error'), E_ALL & ~E_STRICT);

		/*
		$_POST['updraft_restore'] is typically something like: array( 0=>'db', 1=>'plugins', 2=>'themes'), etc.
		i.e. array ( 'db', 'plugins', themes')
		*/

		if (empty($restore_options)) {
			// Gather the restore optons into one place - code after here should read the options, and not the HTTP layer
			$restore_options = array();
			if (!empty($_POST['updraft_restorer_restore_options'])) {
				parse_str(stripslashes($_POST['updraft_restorer_restore_options']), $restore_options);
			}
			$restore_options['updraft_restorer_replacesiteurl'] = empty($_POST['updraft_restorer_replacesiteurl']) ? false : true;
			$restore_options['updraft_encryptionphrase'] = empty($_POST['updraft_encryptionphrase']) ? '' : (string)stripslashes($_POST['updraft_encryptionphrase']);
			$restore_options['updraft_restorer_wpcore_includewpconfig'] = empty($_POST['updraft_restorer_wpcore_includewpconfig']) ? false : true;
			$updraftplus->jobdata_set('restore_options', $restore_options);
		}
		
		// Restore in the most helpful order
		uksort($backup_set, array($this, 'sort_restoration_entities'));
		
		// Now log
		$copy_restore_options = $restore_options;
		if (!empty($copy_restore_options['updraft_encryptionphrase'])) $copy_restore_options['updraft_encryptionphrase'] = '***';
		$updraftplus->log("Restore job started. Entities to restore: ".implode(', ', array_flip($entities_to_restore)).'. Restore options: '.json_encode($copy_restore_options));
		
		$backup_set['timestamp'] = $timestamp;

		$backupable_entities = $updraftplus->get_backupable_file_entities(true, true);

		// Allow add-ons to adjust the restore directory (but only in the case of restore - otherwise, they could just use the filter built into UpdraftPlus::get_backupable_file_entities)
		$backupable_entities = apply_filters('updraft_backupable_file_entities_on_restore', $backupable_entities, $restore_options, $backup_set);
		
		// We use a single object for each entity, because we want to store information about the backup set
		require_once(UPDRAFTPLUS_DIR.'/restorer.php');

		global $updraftplus_restorer;
		
		$updraftplus_restorer = new Updraft_Restorer(new Updraft_Restorer_Skin, $backup_set, false, $restore_options);

		$second_loop = array();

		echo "<h2>".__('Final checks', 'updraftplus').'</h2>';

		if (empty($backup_set['meta_foreign'])) {
			$entities_to_download = $entities_to_restore;
		} else {
			if (!empty($foreign_known[$backup_set['meta_foreign']]['separatedb'])) {
				$entities_to_download = array();
				if (in_array('db', $entities_to_restore)) {
					$entities_to_download['db'] = 1;
				}
				if (count($entities_to_restore) > 1 || !in_array('db', $entities_to_restore)) {
					$entities_to_download['wpcore'] = 1;
				}
			} else {
				$entities_to_download = array('wpcore' => 1);
			}
		}

		// First loop: make sure that files are present + readable; and populate array for second loop
		foreach ($backup_set as $type => $files) {
			// All restorable entities must be given explicitly, as we can store other arbitrary data in the history array
			if (!isset($backupable_entities[$type]) && 'db' != $type) continue;
			if (isset($backupable_entities[$type]['restorable']) && $backupable_entities[$type]['restorable'] == false) continue;

			if (!isset($entities_to_download[$type])) continue;
			if ('wpcore' == $type && is_multisite() && 0 === $updraftplus_restorer->ud_backup_is_multisite) {
				echo "<p>$type: <strong>";
				echo __('Skipping restoration of WordPress core when importing a single site into a multisite installation. If you had anything necessary in your WordPress directory then you will need to re-add it manually from the zip file.', 'updraftplus');
				#TODO
				#$updraftplus->log_e('Skipping restoration of WordPress core when importing a single site into a multisite installation. If you had anything necessary in your WordPress directory then you will need to re-add it manually from the zip file.');
				echo "</strong></p>";
				continue;
			}

			if (is_string($files)) $files=array($files);

			foreach ($files as $ind => $file) {

				$fullpath = $updraft_dir.$file;
				echo sprintf(__("Looking for %s archive: file name: %s", 'updraftplus'), $type, htmlspecialchars($file))."<br>";

				if (is_array($continuation_data) && isset($continuation_data['second_loop_entities'][$type]) && !in_array($file, $continuation_data['second_loop_entities'][$type])) {
					echo __('Skipping: this archive was already restored.', 'updraftplus')."<br>";
					// Set the marker so that the existing directory isn't moved out of the way
					$updraftplus_restorer->been_restored[$type] = true;
					continue;
				}

				add_action('http_request_args', array($updraftplus, 'modify_http_options'));
				foreach ($service as $serv) {
					if (!is_readable($fullpath)) {
						$sd = (empty($updraftplus->backup_methods[$serv])) ? $serv : $updraftplus->backup_methods[$serv];
						echo __("File is not locally present - needs retrieving from remote storage",'updraftplus')." ($sd)";
						$this->download_file($file, $serv);
						echo ": ";
						if (!is_readable($fullpath)) {
							echo __("Error", 'updraftplus');
						} else {
							echo __("OK", 'updraftplus');
						}
						echo '<br>';
					}
				}
				remove_action('http_request_args', array($updraftplus, 'modify_http_options'));

				$index = ($ind == 0) ? '' : $ind;
				// If a file size is stored in the backup data, then verify correctness of the local file
				if (isset($backup_history[$timestamp][$type.$index.'-size'])) {
					$fs = $backup_history[$timestamp][$type.$index.'-size'];
					echo __("Archive is expected to be size:",'updraftplus')." ".round($fs/1024, 1)." KB: ";
					$as = @filesize($fullpath);
					if ($as == $fs) {
						echo __('OK','updraftplus').'<br>';
					} else {
						echo "<strong>".__('Error:','updraftplus')."</strong> ".__('file is size:', 'updraftplus')." ".round($as/1024)." ($fs, $as)<br>";
					}
				} else {
					echo __("The backup records do not contain information about the proper size of this file.",'updraftplus')."<br>";
				}
				if (!is_readable($fullpath)) {
					echo __('Could not find one of the files for restoration', 'updraftplus')." ($file)<br>";
					$updraftplus->log("$file: ".__('Could not find one of the files for restoration', 'updraftplus'), 'error');
					echo '</div>';
					restore_error_handler();
					return false;
				}
			}

			if (empty($updraftplus_restorer->ud_foreign)) {
				$types = array($type);
			} else {
				if ('db' != $type || empty($foreign_known[$updraftplus_restorer->ud_foreign]['separatedb'])) {
					$types = array('wpcore');
				} else {
					$types = array('db');
				}
			}

			foreach ($types as $check_type) {
				$info = (isset($backupable_entities[$check_type])) ? $backupable_entities[$check_type] : array();
				$val = $updraftplus_restorer->pre_restore_backup($files, $check_type, $info, $continuation_data);
				if (is_wp_error($val)) {
					$updraftplus->log_wp_error($val);
					foreach ($val->get_error_messages() as $msg) {
						echo '<strong>'.__('Error:',  'updraftplus').'</strong> '.htmlspecialchars($msg).'<br>';
					}
					foreach ($val->get_error_codes() as $code) {
						if ('already_exists' == $code) $this->print_delete_old_dirs_form(false);
					}
					echo '</div>'; //close the updraft_restore_progress div even if we error
					restore_error_handler();
					return $val;
				} elseif (false === $val) {
					echo '</div>'; //close the updraft_restore_progress div even if we error
					restore_error_handler();
					return false;
				}
			}

			foreach ($entities_to_restore as $entity => $via) {
				if ($via == $type) {
					if ('wpcore' == $via && 'db' == $entity && count($files) > 1) {
						$second_loop[$entity] = apply_filters('updraftplus_select_wpcore_file_with_db', $files, $updraftplus_restorer->ud_foreign);
					} else {
						$second_loop[$entity] = $files;
					}
				}
			}
		
		}

		$updraftplus_restorer->delete = (UpdraftPlus_Options::get_updraft_option('updraft_delete_local')) ? true : false;
		if ('none' === $service || 'email' === $service || empty($service) || (is_array($service) && 1 == count($service) && (in_array('none', $service) || in_array('', $service) || in_array('email', $service))) || !empty($updraftplus_restorer->ud_foreign)) {
			if ($updraftplus_restorer->delete) $updraftplus->log_e('Will not delete any archives after unpacking them, because there was no cloud storage for this backup');
			$updraftplus_restorer->delete = false;
		}

		if (!empty($updraftplus_restorer->ud_foreign)) $updraftplus->log("Foreign backup; created by: ".$updraftplus_restorer->ud_foreign);

		// Second loop: now actually do the restoration
		uksort($second_loop, array($this, 'sort_restoration_entities'));

		// If continuing, then prune those already done
		if (is_array($continuation_data)) {
			foreach ($second_loop as $type => $files) {
				if (isset($continuation_data['second_loop_entities'][$type])) $second_loop[$type] = $continuation_data['second_loop_entities'][$type];
			}
		}

		$updraftplus->jobdata_set('second_loop_entities', $second_loop);
		$updraftplus->jobdata_set('backup_timestamp', $timestamp);
		// use a site option, as otherwise on multisite when all the array of options is updated via UpdraftPlus_Options::update_site_option(), it will over-write any restored UD options from the backup
		update_site_option('updraft_restore_in_progress', $updraftplus->nonce);

		foreach ($second_loop as $type => $files) {
			# Types: uploads, themes, plugins, others, db
			$info = (isset($backupable_entities[$type])) ? $backupable_entities[$type] : array();

			echo ('db' == $type) ? "<h2>".__('Database','updraftplus')."</h2>" : "<h2>".$info['description']."</h2>";
			$updraftplus->log("Entity: ".$type);

			if (is_string($files)) $files = array($files);
			foreach ($files as $fkey => $file) {
				$last_one = (1 == count($second_loop) && 1 == count($files));

				$val = $updraftplus_restorer->restore_backup($file, $type, $info, $last_one);

				if (is_wp_error($val)) {
					$codes = $val->get_error_codes();
					if (is_array($codes) && in_array('not_found', $codes) && !empty($updraftplus_restorer->ud_foreign) && apply_filters('updraftplus_foreign_allow_missing_entity', false, $type, $updraftplus_restorer->ud_foreign)) {
						$updraftplus->log("Entity to move not found in this zip - but this is possible with this foreign backup type");
					} else {
				
						$updraftplus->log_e($val);
						foreach ($val->get_error_messages() as $msg) {
							echo '<strong>'.__('Error message',  'updraftplus').':</strong> '.htmlspecialchars($msg).'<br>';
						}
						$codes = $val->get_error_codes();
						if (is_array($codes)) {
							foreach ($codes as $code) {
								$data = $val->get_error_data($code);
								if (!empty($data)) {
									$pdata = (is_string($data)) ? $data : serialize($data);
									echo '<strong>'.__('Error data:', 'updraftplus').'</strong> '.htmlspecialchars($pdata).'<br>';
									if (false !== strpos($pdata, 'PCLZIP_ERR_BAD_FORMAT (-10)')) {
										echo '<a href="https://updraftplus.com/faqs/error-message-pclzip_err_bad_format-10-invalid-archive-structure-mean/"><strong>'.__('Please consult this FAQ for help on what to do about it.', 'updraftplus').'</strong></a><br>';
									}
								}
							}
						}
						echo '</div>'; //close the updraft_restore_progress div even if we error
						restore_error_handler();
						return $val;
					}
				} elseif (false === $val) {
					echo '</div>'; //close the updraft_restore_progress div even if we error
					restore_error_handler();
					return false;
				}
				unset($files[$fkey]);
				$second_loop[$type] = $files;
				$updraftplus->jobdata_set('second_loop_entities', $second_loop);
				$updraftplus->jobdata_set('backup_timestamp', $timestamp);

				do_action('updraft_restored_archive', $file, $type, $val, $fkey, $timestamp);

			}
			unset($second_loop[$type]);
			update_site_option('updraft_restore_in_progress', $updraftplus->nonce);
			$updraftplus->jobdata_set('second_loop_entities', $second_loop);
			$updraftplus->jobdata_set('backup_timestamp', $timestamp);
		}

		// All done - remove
		delete_site_option('updraft_restore_in_progress');

		foreach (array('template', 'stylesheet', 'template_root', 'stylesheet_root') as $opt) {
			add_filter('pre_option_'.$opt, array($this, 'option_filter_'.$opt));
		}

		# Clear any cached pages after the restore
		$updraftplus_restorer->clear_cache();

		if (!function_exists('validate_current_theme')) require_once(ABSPATH.WPINC.'/themes');

		# Have seen a case where the current theme in the DB began with a capital, but not on disk - and this breaks migrating from Windows to a case-sensitive system
		$template = get_option('template');
		if (!empty($template) && $template != WP_DEFAULT_THEME && $template != strtolower($template)) {

			$theme_root = get_theme_root($template);
			$theme_root2 = get_theme_root(strtolower($template));

			if (!file_exists("$theme_root/$template/style.css") && file_exists("$theme_root/".strtolower($template)."/style.css")) {
				$updraftplus->log_e("Theme directory (%s) not found, but lower-case version exists; updating database option accordingly", $template);
				update_option('template', strtolower($template));
			}

		}

		if (!validate_current_theme()) {
			echo '<strong>';
			$updraftplus->log_e("The current theme was not found; to prevent this stopping the site from loading, your theme has been reverted to the default theme");
			echo '</strong>';
		}

		echo '</div>'; //close the updraft_restore_progress div

		restore_error_handler();
		return true;
	}

	public function option_filter_template($val) { global $updraftplus; return $updraftplus->option_filter_get('template'); }

	public function option_filter_stylesheet($val) { global $updraftplus; return $updraftplus->option_filter_get('stylesheet'); }

	public function option_filter_template_root($val) { global $updraftplus; return $updraftplus->option_filter_get('template_root'); }

	public function option_filter_stylesheet_root($val) { global $updraftplus; return $updraftplus->option_filter_get('stylesheet_root'); }

	public function sort_restoration_entities($a, $b) {
		if ($a == $b) return 0;
		// Put the database first
		// Put wpcore after plugins/uploads/themes (needed for restores of foreign all-in-one formats)
		if ('db' == $a || 'wpcore' == $b) return -1;
		if ('db' == $b || 'wpcore' == $a) return 1;
		// After wpcore, next last is others
		if ('others' == $b) return -1;
		if ('others' == $a) return 1;
		// And then uploads - this is only because we want to make sure uploads is after plugins, so that we know before we get to the uploads whether the version of UD which might have to unpack them can do this new-style or not.
		if ('uploads' == $b) return -1;
		if ('uploads' == $a) return 1;
		return strcmp($a, $b);
	}

	public function return_array($input) {
		if (!is_array($input)) $input = array();
		return $input;
	}
	
	public function updraft_ajax_savesettings() {
		global $updraftplus;
		
		if (empty($_POST) || empty($_POST['subaction']) || 'savesettings' != $_POST['subaction'] || !isset($_POST['nonce']) || !is_user_logged_in() || !UpdraftPlus_Options::user_can_manage() || !wp_verify_nonce($_POST['nonce'], 'updraftplus-settings-nonce')) die('Security check');
		
		if (empty($_POST['settings']) || !is_string($_POST['settings'])) die('Invalid data');

		parse_str(stripslashes($_POST['settings']), $posted_settings);
		// We now have $posted_settings as an array
		
		echo json_encode($this->save_settings($posted_settings));

		die;
	}
	
	private function backup_now_remote_message() {
		global $updraftplus;
		
		$service = $updraftplus->just_one(UpdraftPlus_Options::get_updraft_option('updraft_service'));
		if (is_string($service)) $service = array($service);
		if (!is_array($service)) $service = array();

		$no_remote_configured = (empty($service) || array('none') === $service || array('') === $service) ? true : false;

		if ($no_remote_configured) {
			return '<input type="checkbox" disabled="disabled" id="backupnow_includecloud"> <em>'.sprintf(__("Backup won't be sent to any remote storage - none has been saved in the %s", 'updraftplus'), '<a href="'.UpdraftPlus_Options::admin_page_url().'?page=updraftplus&amp;tab=settings" id="updraft_backupnow_gotosettings">'.__('settings', 'updraftplus')).'</a>. '.__('Not got any remote storage?', 'updraftplus').' <a href="https://updraftplus.com/landing/vault">'.__("Check out UpdraftPlus Vault.", 'updraftplus').'</a></em>';
		} else {
			return '<input type="checkbox" id="backupnow_includecloud" checked="checked"> <label for="backupnow_includecloud">'.__("Send this backup to remote storage", 'updraftplus').'</label>';
		}
	}
       
	public function save_settings($settings) {
	
		global $updraftplus;
	
		// Make sure that settings filters are registered
		UpdraftPlus_Options::admin_init();
	
		$return_array = array('saved' => true);
		
		$add_to_post_keys = array('updraft_interval', 'updraft_interval_database', 'updraft_starttime_files', 'updraft_starttime_db', 'updraft_startday_files', 'updraft_startday_db');
		
		//If database and files are on same schedule, override the db day/time settings
		if (isset($settings['updraft_interval_database']) && isset($settings['updraft_interval_database']) && $settings['updraft_interval_database'] == $settings['updraft_interval'] && isset($settings['updraft_starttime_files'])) {
			$settings['updraft_starttime_db'] = $settings['updraft_starttime_files'];
			$settings['updraft_startday_db'] = $settings['updraft_startday_files'];
		}	
		foreach ($add_to_post_keys as $key) {
			// For add-ons that look at $_POST to find saved settings, add the relevant keys to $_POST so that they find them there
			if (isset($settings[$key])) {
				$_POST[$key] = $settings[$key];
			}
		}
		
		// Wipe the extra retention rules, as they are not saved correctly if the last one is deleted
		UpdraftPlus_Options::update_updraft_option('updraft_retain_extrarules', array());
		UpdraftPlus_Options::update_updraft_option('updraft_email', array());
		UpdraftPlus_Options::update_updraft_option('updraft_report_warningsonly', array());
		UpdraftPlus_Options::update_updraft_option('updraft_report_wholebackup', array());
		UpdraftPlus_Options::update_updraft_option('updraft_extradbs', array());
		UpdraftPlus_Options::update_updraft_option('updraft_include_more_path', array());
		
		$relevant_keys = $updraftplus->get_settings_keys();
		
		if (method_exists('UpdraftPlus_Options', 'mass_options_update')) {
			$original_settings = $settings;
			$settings = UpdraftPlus_Options::mass_options_update($settings);
			$mass_updated = true;
		}
		
		foreach ($settings as $key => $value) {
// 			$exclude_keys = array('option_page', 'action', '_wpnonce', '_wp_http_referer');
							
// 			if (!in_array($key, $exclude_keys)) {
			if (in_array($key, $relevant_keys)) {
				if ($key == "updraft_service" && is_array($value)){
					foreach ($value as $subkey => $subvalue){
						if ($subvalue == '0') unset($value[$subkey]);
					}
				}

				// This flag indicates that either the stored database option was changed, or that the supplied option was changed before being stored. It isn't comprehensive - it's only used to update some UI elements with invalid input.
				$updated = empty($mass_updated) ? (is_string($value) && $value != UpdraftPlus_Options::get_updraft_option($key)) : (is_string($value) && (!isset($original_settings[$key]) || $original_settings[$key] != $value));
				
				$db_updated = empty($mass_updated) ? UpdraftPlus_Options::update_updraft_option($key, $value) : true;
				
				// Add information on what has changed to array to loop through to update links etc.
				// Restricting to strings for now, to prevent any unintended leakage (since this is just used for UI updating)
				if ($updated) {
					$value = UpdraftPlus_Options::get_updraft_option($key);
					if (is_string($value)) $return_array['changed'][$key] = $value;
				}
			} else {
				// When last active, it was catching: option_page, action, _wpnonce, _wp_http_referer, updraft_s3_endpoint, updraft_dreamobjects_endpoint. The latter two are empty; probably don't need to be in the page at all.
				//error_log("Non-UD key when saving from POSTed data: ".$key);
			}
		}
		
		// Checking for various possible messages
		$updraft_dir = $updraftplus->backups_dir_location(false);
		$really_is_writable = $updraftplus->really_is_writable($updraft_dir);
		$dir_info = $this->really_writable_message($really_is_writable, $updraft_dir);
		$button_title = esc_attr(__('This button is disabled because your backup directory is not writable (see the settings).', 'updraftplus'));
		
		$return_array['backup_now_message'] = $this->backup_now_remote_message();
		
		$return_array['backup_dir'] = array('writable' => $really_is_writable, 'message' => $dir_info, 'button_title' => $button_title);
		
		//Because of the single AJAX call, we need to remove the existing UD messages from the 'all_admin_notices' action
		remove_all_actions('all_admin_notices');
		
		//Moving from 2 to 1 ajax call
		ob_start();

		$service = UpdraftPlus_Options::get_updraft_option('updraft_service');
		
		$this->setup_all_admin_notices_global($service);
		$this->setup_all_admin_notices_udonly($service);
		
		do_action('all_admin_notices');
		
		if (!$really_is_writable){ //Check if writable
			$this->show_admin_warning_unwritable();
		}
		
		if ($return_array['saved'] == true){ //
			$this->show_admin_warning(__('Your settings have been saved.', 'updraftplus'), 'updated fade');
		}
		
		$messages_output = ob_get_contents();
		
		ob_clean();
		
		// Backup schedule output
		$this->next_scheduled_backups_output();
		
		$scheduled_output = ob_get_clean();
		
		$return_array['messages'] = $messages_output;
		$return_array['scheduled'] = $scheduled_output;
		
		//*** Add the updated options to the return message, so we can update on screen ***\\
		
		
		return $return_array;
		
	}
	
}
