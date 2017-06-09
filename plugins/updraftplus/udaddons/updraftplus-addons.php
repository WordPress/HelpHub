<?php
/*
Obtain and manage extra features for UpdraftPlus Backup

This plugin communicates with the mothership via encrypted HTTPS and falls back to HTTP if this fails; even over HTTP, nothing of significant value is thereby put at risk - see https://updraftplus.com/faqs/tell-me-about-my-updraftplus-com-account/
However, if your organisation has strong requirements for use of SSL, then add the following line to your wp-config.php to forbid any non-https communications:

define('UPDRAFTPLUS_ADDONS_SSL', true);

This plugin:
- over-rides the update mechanism for the UpdraftPlus plugin, so that we can get them from our site
- shows the user his installed and available add-ons
- also over-rides its own update mechanism

This directory should not be added to the wordpress.org SVN
*/

define('UDADDONS2_DIR', dirname(realpath(__FILE__)));
define('UDADDONS2_URL', UPDRAFTPLUS_URL.'/udaddons');
define('UDADDONS2_SLUG', 'updraftplus-addons');
//define('UDADDONS2_PAGESLUG', 'updraftplus-addons2');
define('UDADDONS2_PAGESLUG', 'updraftplus');

$udaddons2_mothership = (defined('UPDRAFTPLUS_ADDONS_SSL') && UPDRAFTPLUS_ADDONS_SSL) ? 'https://' : 'http://';

$udaddons2_mothership = (defined('UPDRAFTPLUS_ADDONS_TESTING') && UPDRAFTPLUS_ADDONS_TESTING && isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == 'localhost') ? 'http://localhost/ud' : $udaddons2_mothership;

$udaddons2_mothership .= (defined('UDADDONS2_TEST_MOTHERSHIP') && defined('UPDRAFTPLUS_ADDONS_TESTING') && UPDRAFTPLUS_ADDONS_TESTING && isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == 'localhost') ? UDADDONS2_TEST_MOTHERSHIP : 'updraftplus.com';

$updraftplus_addons2 = new UpdraftPlusAddons2('updraftplus', $udaddons2_mothership);
#$updraftplus_addons->debug = true;

class UpdraftPlusAddons2 {

	public $slug;
	public $url;
	public $debug = false;

	public $user_addons;
	public $user_support;

	public $available_addons;
	public $remote_addons;

	public $plug_updatechecker;

	private $admin_notices = array();

	private $saved_site_id;

	public function __construct($slug, $url) {
		$this->slug = $slug;
		$this->url = $url;

		// This needs to exact match PluginUpdateChecker's view
		$this->plugin_file = plugin_basename($this->slug.'/'.$this->slug.'.php');

		add_action('updraftplus_restore_db_pre', array($this, 'updraftplus_restore_db_pre'));
		add_action('updraftplus_restored_db_is_migration', array($this, 'updraftplus_restored_db_is_migration'));

		add_action('updraftplus_showrawinfo', array($this, 'updraftplus_showrawinfo'));

		add_action((is_multisite() && class_exists('UpdraftPlusAddOn_MultiSite')) ? 'network_admin_menu' : 'admin_menu', array($this, 'admin_menu'));

		add_action('wp_ajax_udaddons_claimaddon', array($this, 'ajax_udaddons_claimaddon'));

		if (class_exists('UpdraftPlusAddons')) return;

		# Prevent updates from wordpress.org showing in all circumstances. Run with lower than default priority, to allow later processes to add something.
		add_filter('site_transient_update_plugins', array($this, 'site_transient_update_plugins'), 9);

		// Over-ride update mechanism for the plugin
		if (is_readable(UDADDONS2_DIR.'/plugin-updates/plugin-update-checker.php')) {

			require_once(UDADDONS2_DIR.'/plugin-updates/plugin-update-checker.php');

			$options = $this->get_option(UDADDONS2_SLUG.'_options');
			$email = isset($options['email']) ? $options['email'] : '';
			if ($email) {

				add_filter('puc_check_now-'.$this->slug, array($this, 'puc_check_now'), 10, 3);
				add_filter('puc_retain_fields-'.$this->slug, array($this, 'puc_retain_fields'));
				add_filter('puc_request_info_options-'.$this->slug, array($this, 'puc_request_info_options'));
				// Run after the PluginUpdateChcker has done its stuff
				add_filter('site_transient_update_plugins', array($this, 'possibly_inject_translations'), 11);

				$plug_updatechecker = new PluginUpdateChecker_3_0($this->url."/plugin-info/", WP_PLUGIN_DIR.'/'.$this->slug.'/'.$this->slug.'.php', $this->slug, 24);
				$plug_updatechecker->addQueryArgFilter(array($this, 'updater_queryargs_plugin'));
				if ($this->debug) $plug_updatechecker->debugMode = true;
				$this->plug_updatechecker = $plug_updatechecker;
			}
		}
	}

	public function possibly_inject_translations($updates) {
	
		$slug = $this->slug;
	
		$checker = is_object($this->plug_updatechecker) ? get_site_option($this->plug_updatechecker->optionName, null) : null;

		if (!isset($checker->update->translations) || !is_array($checker->update->translations)) return $updates;
		
		foreach ($checker->update->translations as $translation) {

			// Remove any existing updates indicated for this slug/language - i.e. we want it over-ridden with the result from the server
			if (isset($updates->translations) && is_array($updates->translations)) {
				foreach ($updates->translations as $k => $translation) {
					if ($translation['type'] == 'plugin' && $translation['slug'] == $slug) unset($updates->translations[$k]);
				}
			}
			
			// Add our translation onto the array
			$translation_array = (array)$translation;
			$translation_array['type'] = 'plugin';
			$translation_array['slug'] = $slug;
			
			$updates->translations[] = $translation_array;
			
		}
		
		return $updates;
	}
	
	public function puc_request_info_options($opts) {
		if (is_array($opts)) $opts['timeout'] = 10;
		return $opts;
	}

	public function updraftplus_showrawinfo() {
		echo "<p>Updates URL: ".htmlspecialchars($this->url)."</p>";
	}

	public function updraftplus_restore_db_pre() {
		$this->saved_site_id = $this->siteid();
	}

	public function updraftplus_restored_db_is_migration() {
		global $wpdb;
		$option = UDADDONS2_SLUG.'_siteid';
		if (!empty($this->saved_site_id)) {
			global $updraftplus;
			$updraftplus->log("Restored pre-migration site ID for this installation");
			delete_site_option($option);
			add_site_option($option, $this->saved_site_id);
		}
	}

	public function puc_retain_fields($f) {
		$retain_these = array('x-spm-yourversion-tested', 'x-spm-yourversion-tested', 'x-spm-expiry', 'translations');
		foreach ($retain_these as $retain) {
			if (!in_array($retain, $f)) $f[] = $retain;
		}
		return $f;
	}

	public function admin_notices() {
		foreach ($this->admin_notices as $key => $notice) {
			$notice = '<span style="font-size: 115%;">'.$notice.'</span>';
			if (is_numeric($key)) {
				$this->show_admin_warning($notice);
			} else {
				$this->show_admin_warning($notice, 'error updraftupdatesnotice updraftupdatesnotice-'.$key);
			}
		}
	}

	public function admin_menu() {
		global $pagenow;

		# Do we want to display a notice about the upcoming or past expiry of their UpdraftPlus subscription?
		if (!empty($this->plug_updatechecker) && !empty($this->plug_updatechecker->optionName) && current_user_can('update_plugins')) {
			#(!is_multisite() && 'options-general.php' == $pagenow) || (is_multisite() && 'settings.php' == $pagenow) ||
			if ('plugins.php' == $pagenow || 'update-core.php' == $pagenow || (('options-general.php' == $pagenow || 'admin.php' == $pagenow) && !empty($_REQUEST['page']) && 'updraftplus' == $_REQUEST['page'])) {
				$do_expiry_check = true;
				$dismiss = '';
			} elseif (is_admin()) {
				$dismissed_until = UpdraftPlus_Options::get_updraft_option('updraftplus_dismissedexpiry', 0);
				if ($dismissed_until <= time()) {
					$do_expiry_check = true;
					$dismiss = '<div style="float:right; position: relative; top:-24px;" class="ud-expiry-dismiss"><a href="#" onclick="jQuery(\'.ud-expiry-dismiss\').parent().slideUp(); jQuery.post(ajaxurl, {action: \'updraft_ajax\', subaction: \'dismissexpiry\', nonce: \''.wp_create_nonce('updraftplus-credentialtest-nonce').'\' });">'.sprintf(__('Dismiss from main dashboard (for %s weeks)', 'updraftplus'), 2).'</a></div>';
				}
			}
		}

		$oval = is_object($this->plug_updatechecker) ? get_site_option($this->plug_updatechecker->optionName, null) : null;
		$updateskey = 'x-spm-expiry';
		$supportkey = 'x-spm-support-expiry';

		$yourversionkey = 'x-spm-yourversion-tested';

		if (is_object($oval) && !empty($oval->update) && is_object($oval->update) && !empty($oval->update->$yourversionkey) && UpdraftPlus_Options::user_can_manage() && (!defined('UPDRAFTPLUS_DISABLECOMPATNOTICE') || true != UPDRAFTPLUS_DISABLECOMPATNOTICE)) {

			// Prevent false-positives
			if (file_exists(UPDRAFTPLUS_DIR.'/readme.txt') && $fp = fopen(UPDRAFTPLUS_DIR.'/readme.txt', 'r')) {
				$file_data = fread($fp, 1024);
				if (preg_match("/Tested up to: (\d+(\.\d+)+)/", $file_data, $matches)) {
					$readme_says = $matches[1];
				}
				fclose($fp);
			}

			global $wp_version;
			include(ABSPATH.WPINC.'/version.php');
			$compare_wp_version = (preg_match('/^(\d+\.\d+)\..*$/', $wp_version, $wmatches)) ? $wmatches[1] : $wp_version;
			$compare_tested_version = $oval->update->$yourversionkey;
			if (!empty($readme_says) && version_compare($readme_says, $compare_tested_version, '>')) $compare_tested_version = $readme_says;
			if (version_compare($compare_wp_version, $compare_tested_version, '>')) {
				$this->admin_notices['yourversiontested'] = '<strong>'.__('Warning','updraftplus').':</strong> '.sprintf(__('The installed version of UpdraftPlus Backup/Restore has not been tested on your version of WordPress (%s).', 'updraftplus'), $wp_version).' '.sprintf(__('It has been tested up to version %s.', 'updraftplus'), $compare_tested_version).' <a href="https://updraftplus.com/seeing-warning-versions-wordpress-updraftplus-tested/">'.__('You should update UpdraftPlus to make sure that you have a version that has been tested for compatibility.', 'updraftplus').'</a>';
			}
		}

		if (!empty($do_expiry_check) && is_object($oval) && !empty($oval->update) && is_object($oval->update) && !empty($oval->update->$updateskey)) {
			if (preg_match('/(^|)expired_?(\d+)?(,|$)/', $oval->update->$updateskey, $matches)) {
				if (empty($matches[2])) {
					$this->admin_notices['updatesexpired'] = __('Your paid access to UpdraftPlus updates for this site has expired. You will no longer receive updates to UpdraftPlus.', 'updraftplus').' <a href="https://updraftplus.com/renewing-updraftplus-purchase/">'.__('To regain access to updates (including future features and compatibility with future WordPress releases) and support, please renew.', 'updraftplus').'</a>'.$dismiss;
				} else {
					$this->admin_notices['updatesexpired'] = sprintf(__('Your paid access to UpdraftPlus updates for %s add-ons on this site has expired.', 'updraftplus'), $matches[2]).' <a href="https://updraftplus.com/renewing-updraftplus-purchase/">'.__('To regain access to updates (including future features and compatibility with future WordPress releases) and support, please renew.', 'updraftplus').'</a>'.$dismiss;
				}
			}
			if (preg_match('/(^|,)soonpartial_(\d+)_(\d+)($|,)/', $oval->update->$updateskey, $matches)) {
				$this->admin_notices['updatesexpiringsoon'] = sprintf(__('Your paid access to UpdraftPlus updates for %s of the %s add-ons on this site will soon expire.', 'updraftplus'), $matches[2], $matches[3]).' <a href="https://updraftplus.com/renewing-updraftplus-purchase/">'.__('To retain your access, and maintain access to updates (including future features and compatibility with future WordPress releases) and support, please renew.', 'updraftplus').'</a>'.$dismiss;
			} elseif (preg_match('/(^|,)soon($|,)/', $oval->update->$updateskey)) {
				$this->admin_notices['updatesexpiringsoon'] = __('Your paid access to UpdraftPlus updates for this site will soon expire.', 'updraftplus').' <a href="https://updraftplus.com/renewing-updraftplus-purchase/">'.__('To retain your access, and maintain access to updates (including future features and compatibility with future WordPress releases) and support, please renew.', 'updraftplus').'</a>'.$dismiss;
			}
		} elseif (!empty($do_expiry_check) && is_object($oval) && !empty($oval->update) && is_object($oval->update) && !empty($oval->update->$supportkey)) {
			if ('expired' == $oval->update->$supportkey) {
				$this->admin_notices['supportexpired'] = __('Your paid access to UpdraftPlus support has expired.','updraftplus').' <a href="https://updraftplus.com/renewing-updraftplus-purchase/">'.__('To regain your access, please renew.', 'updraftplus').'</a>'.$dismiss;
			} elseif ('soon' == $oval->update->$supportkey) {
				$this->admin_notices['supportsoonexpiring'] = __('Your paid access to UpdraftPlus support will soon expire.','updraftplus').' <a href="https://updraftplus.com/renewing-updraftplus-purchase/">'.__('To maintain your access to support, please renew.', 'updraftplus').'</a>'.$dismiss;
			}
		}
		add_action('all_admin_notices', array($this, 'admin_notices'));

		if (!function_exists('is_plugin_active')) require_once(ABSPATH.'wp-admin/includes/plugin.php');
		if (is_plugin_active('updraftplus-addons/updraftplus-addons.php')) {
			deactivate_plugins('updraftplus-addons/updraftplus-addons.php');
			if (('options-general.php' == $pagenow || 'settings.php' == $pagenow) && !empty($_REQUEST['page']) && 'updraftplus-addons' == $_REQUEST['page']) {
				wp_redirect($this->addons_admin_url());
				exit;
			}
			// Do nothing more this time to avoid duplication
			return;
		} elseif (is_dir(WP_PLUGIN_DIR.'/updraftplus-addons') && current_user_can('delete_plugins')) {
			# Exists, but not active - nag them
			if ((!is_multisite() && 'options-general.php' == $pagenow) || (is_multisite() && 'settings.php' == $pagenow) || 'plugins.php' == $pagenow) add_action('all_admin_notices', array($this, 'deinstall_udaddons'));
		} 

		if (class_exists('UpdraftPlusAddons')) return;

		// Refresh, if specifically requested
		if (('options-general.php' == $pagenow) || (is_multisite() && 'settings.php' == $pagenow) && isset($_GET['udm_refresh'])) {
			if ($this->plug_updatechecker) $this->plug_updatechecker->checkForUpdates();
		}

		require_once(UDADDONS2_DIR.'/options.php');
		$this->options = new UpdraftPlusAddOns_Options2($this->slug, __('UpdraftPlus Addons', 'updraftplus'), $this->url);

	}

	public function addons_admin_url() {
		return UpdraftPlus_Options::admin_page().'?page='.UDADDONS2_PAGESLUG.'&tab=addons';
	}

	// Funnelling through here a) allows for future flexibility and b) allows us to migrate elegantly from the previous non-MU-friendly setup
	public function get_option($option) {
		$val = get_site_option($option);
		# On multisite, migrate options into the site options
		if (false === $val && is_multisite()) {
			$blog_id = get_current_blog_id();
			if ($blog_id>1) {
				$val = get_option($option);
				if ($val !== false) {
					delete_option($option);
					update_site_option($option, $val);
					return $val;
				}
			}
			# $val is still false
			switch_to_blog(1);
			$val = get_option($option);
			if ($val !== false) {
				delete_option($option);
				update_site_option($option, $val);
			}
			restore_current_blog();
		}
		return $val;
	}

	public function update_option($option, $val) {
		return update_site_option($option, $val);
	}

	public function deinstall_udaddons() {
		$del = '<a href="' . wp_nonce_url('plugins.php?action=delete-selected&amp;checked[]=updraftplus-addons/updraftplus-addons.php&amp;plugin_status=all&amp;paged=1&amp;s=', 'bulk-plugins') . '" title="' . esc_attr__('Delete plugin') . '" class="delete">' . 'delete the UpdraftPlus Addons Manager plugin' . '</a>';
		$this->show_admin_warning('You can '.$del.' - it is obsolete (all of its functions, including your add-ons, are now included in the main UpdraftPlus plugin obtained from updraftplus.com).');
	}

	public function show_admin_warning($message, $class = "updated") {
		echo '<div class="updraftmessage '.$class.'">'."<p>$message</p></div>";
	}

	// Remove any existing updates detected
	public function site_transient_update_plugins($updates) {
		if (!is_object($updates) || empty($this->plugin_file)) return $updates;
		if (isset($updates, $updates->response, $updates->response[$this->plugin_file]))
			unset($updates->response[$this->plugin_file]);
		return $updates;
	}

	// We want to lessen the number of automatic checks if an update is already known to be available
	public function puc_check_now($shouldcheck, $lastcheck, $checkperiod) {
		global $wp_current_filter;
		if (true !== $shouldcheck || empty($this->plug_updatechecker) || 0 == $lastcheck || in_array('load-update-core.php', $wp_current_filter) || !defined('DOING_CRON')) return $shouldcheck;

		if (null === $this->plug_updatechecker->getUpdate()) return $shouldcheck;

		$days_since_check = max(round((time() - $lastcheck)/86400), 1);
		if ($days_since_check > 10000) return true;

		# Suppress checks on days 2, 4, 5, 7 and then every day except multiples of 7.
		if (2 == $days_since_check || 4 == $days_since_check || 5 == $days_since_check || 7 == $days_since_check || ($days_since_check >= 7 && $days_since_check % 7 != 0)) return false;

		return true;
	}

	private function claimaddon($key) {
		$options = $this->get_option(UDADDONS2_SLUG.'_options');

		// The 'password' encoded here is the updraftplus.com password. See here: https://updraftplus.com/faqs/tell-me-about-my-updraftplus-com-account/
		$post_array = array(
			'e' => $options['email'],
			'sid' => $this->siteid(),
			'sn' => base64_encode(get_bloginfo('name')),
			'su' => base64_encode(home_url()),
			'key' => $key
		);

		$result = wp_remote_post($this->url.'/plugin-info/?udm_action=claimaddon',
			array(
				'timeout' => 15,
				'body' => $post_array
			)
		);

		if (!is_wp_error($result) && isset($result['body']) && 'OK' == $result['body']) {
			// Remove the password from the stored settings, for security. We have confirmed that we can authenticate via token (SID) - which is valid for approx. a week (the user can re-enter the password if he needs to)
			unset($options['password']);
			$this->update_option(UDADDONS2_SLUG.'_options', $options);
		} else {

			// Try again with password (old-style protocol)
			// The 'password' encoded here is the updraftplus.com password. See here: https://updraftplus.com/faqs/tell-me-about-my-updraftplus-com-account/
			if (!empty($options['password'])) {
				$post_array['p'] = base64_encode($options['password']);

				$result = wp_remote_post($this->url.'/plugin-info/?udm_action=claimaddon',
					array(
						'timeout' => 15,
						'body' => $post_array
					)
				);
			}
		}

		return $result;

	}

	public function ajax_udaddons_claimaddon() {

		$nonce = (empty($_REQUEST['nonce'])) ? "" : $_REQUEST['nonce'];
		if (! wp_verify_nonce($nonce, 'udmanager-nonce') || empty($_POST['key'])) die('Security check');

		$result = $this->claimaddon($_POST['key']);
 
		if (is_wp_error($result)) {
			echo __('Errors occurred:','updraftplus').'<br>';
			show_message($result);
		} elseif (isset($result['body'])) {
			echo $result['body'];
		} else {
			echo __('Errors occurred:','updraftplus').' '.htmlspecialchars(serialize($result));
		}

		die;

	}

	public function siteid() {
		$sid = get_site_option(UDADDONS2_SLUG.'_siteid');
		if (!is_string($sid) || empty($sid)) {
			$sid = md5(rand().microtime(true).home_url());
			update_site_option(UDADDONS2_SLUG.'_siteid', $sid);
		}
		return $sid;
	}

	public function updater_queryargs_plugin($args) {
		if (!is_array($args)) return $args;

		$options = $this->get_option(UDADDONS2_SLUG.'_options');
		$email = isset($options['email']) ? $options['email'] : '';
		$args['udm_action'] = 'updateinfo';

		$args['sid'] = $this->siteid();
		$args['su'] = urlencode(base64_encode(home_url()));
		$args['sn'] = urlencode(base64_encode(get_bloginfo('name')));
		$args['slug'] = urlencode($this->slug);
		$args['e'] = urlencode($email);
// 		$password = isset($options['password']) ? $options['password'] : '';
// 		$args['p'] = urlencode(base64_encode($password));
		if (defined('UPDRAFTPLUS_ADDONS_SSL') && UPDRAFTPLUS_ADDONS_SSL == true) $args['ssl']=1;

		// Some information on the server calling. This can be used - e.g. if they have an old version of PHP/WordPress, then this may affect what update version they should be offered
		include(ABSPATH.WPINC.'/version.php');
		global $wp_version, $updraftplus;
		
		$sinfo = array(
			'wp' => $wp_version,
			'php' => phpversion(),
			'multi' => (is_multisite() ? 1 : 0),
			'mem' => ini_get('memory_limit'),
			'lang' => (string)get_locale()
		);
		
		// Since 3.7.0
		if (function_exists('wp_get_installed_translations')) {
			$all_translations = wp_get_installed_translations('plugins');
			if (isset($all_translations[$this->slug])) {
				foreach ($all_translations[$this->slug] as $locale => $tinfo) {
					$found_existing = false;
					if (isset($tinfo['PO-Revision-Date'])) {
						$found_existing = strtotime($tinfo['PO-Revision-Date']);
					}
					$sinfo['trans'][$locale]['d'] = $found_existing;
				}
			}
		}
		
		if (is_a($updraftplus, 'UpdraftPlus') && isset($updraftplus->version)) {
			$sinfo['ud'] = $updraftplus->version;
			if (class_exists('UpdraftPlus_Options')) $sinfo['service'] = json_encode(UpdraftPlus_Options::get_updraft_option('updraft_service'));
		}

		$args['si2'] = urlencode(base64_encode(json_encode($sinfo)));

		return $args;
	}

	// This function, if ever changed, should be kept in sync with the same function in udmanager.php
	// Returns either false or an array
	function get_addon_info($file) {
		if ($f = fopen($file, 'r')) {
			$key = "";
			$name = "";
			$description = "";
			$version = "";
			$shopurl = "";
			$latestchange = null;
			$lines_read = 0;
			while ($lines_read<10 && $line = @fgets($f)) {
				if ($key == "" && preg_match('/Addon: ([^:]+):(.*)$/i', $line, $lmatch)) {
					$key = $lmatch[1]; $name = $lmatch[2];
				} elseif ($description == "" && preg_match('/Description: (.*)$/i', $line, $lmatch)) {
					$description = $lmatch[1];
				} elseif ($version == "" && preg_match('/Version: (.*)$/i', $line, $lmatch)) {
					$version = $lmatch[1];
				} elseif ($shopurl == "" && preg_match('/Shop: (.*)$/i', $line, $lmatch)) {
					$shopurl = $lmatch[1];
				} elseif ("" == $latestchange && preg_match('/Latest Change: (.*)$/i', $line, $lmatch)) {
					$latestchange = $lmatch[1];
				}
				$lines_read++;
			}
			fclose($f);
			if ($key && $name && $description && $version) {
				return array('key' => $key, 'name' => $name, 'description' => $description, 'installedversion' => $version, 'shopurl' => $shopurl, 'latestchange' => $latestchange);
			}
		}
		return false;
	}

	private function get_default_addons() {
		return array(
			'noadverts' => array(
				'name' => 'Remove adverts',
				'description' => 'Removes all adverts from the control panel and emails',
				'shopurl' => '/shop/no-adverts/'
			),
			'all' => array (
				'name' => 'All addons',
				'description' => 'Access to all UpdraftPlus add-ons',
				'shopurl' => '/shop/updraftplus-premium/'
			),
			'multisite' => array(
				'name' => 'WordPress Network (multisite) support',
				'description' => 'Adds support for WordPress Network (multisite) installations, allowing secure backup by the super-admin only',
				'shopurl' => '/shop/network-multisite/'
			),
			'fixtime' => array (
				'name' => 'Fix Time',
				'description' => 'Allows you to specify the exact time at which backups will run',
				'shopurl' => '/shop/fix-time/'
			),
			'morefiles' => array (
				'name' => 'More Files',
				'description' => 'Allows you to back up WordPress core, and other files in your web space',
				'shopurl' => '/shop/more-files/'
			),
			'sftp' => array (
				'name' => 'SFTP and FTPS and SCP',
				'description' => 'Allows SFTP and SCP as a cloud backup method, and encrypted FTP',
				'shopurl' => '/shop/sftp/'
			),
			'dropbox-folders' => array (
				'name' => 'Dropbox Folders',
				'description' => 'Allows you to organise your backups into Dropbox sub-folders',
				'shopurl' => '/shop/dropbox-folders/'
			),
			'morestorage' => array (
				'name' => 'Multiple storage destinations',
				'description' => 'Allows you to send a single backup to multiple destinations (e.g. Dropbox and Google Drive and Amazon)',
				'shopurl' => '/shop/morestorage/'
			),
			'webdav' => array (
				'name' => 'WebDAV support',
				'description' => 'Allows you to use the WebDAV and encrypted WebDAV protocols for remote backups',
				'shopurl' => '/shop/webdav/'
			)
		);
	}

	private function get_local_addons($long_form = true) {

		$plugin_dir = WP_PLUGIN_DIR.'/'.$this->slug;

		if (!is_dir($plugin_dir.'/addons')) return array();
		$local_addons = array();
		if ($dir_handle = @opendir($plugin_dir.'/addons')) {
			while (false !== ($e = readdir($dir_handle))) {
				if (is_file($plugin_dir.'/addons/'.$e) && preg_match('/^(.*)\.php$/i', $e, $matches)) {
					$addon = $this->get_addon_info($plugin_dir.'/addons/'.$e);
					if (is_array($addon)) {
						$key = $addon['key'];
						if ($long_form) {
							$local_addons[$key] = $addon;
						} else {
							$local_addons[] = $key;
						}
					} 
				}
			}
		}

		return $local_addons;

	}

	public function get_available_addons() {

		// Cached in this object?
		if (is_array($this->available_addons)) return $this->available_addons;

		// The remote list may be cached in a site transient
		$potential_array = get_site_transient('upaddons_remote');
		if ($this->debug == false && is_array($potential_array) && isset($potential_array['all'])) {
			$this->remote_addons = $potential_array;
			$remote_addons = $potential_array;
		} else {

			$url = $this->url.'/plugin-info/?udm_action=listaddons';

			$result = wp_remote_get($url, array('timeout' => 15));
			
			if (!is_wp_error($result) & false !== $result) {
				$response = maybe_unserialize($result['body']);

				$remote_addons = array();

				if (is_array($response) && isset($response['addons'])) {
					$addons = $response['addons'];
					// One more sanity check
					if (isset($addons['all'])) {
						$remote_addons = $addons;
						// Cache it
						$this->remote_addons = $addons;
						set_site_transient('upaddons_remote', $addons, 14400);
					}
				}
			} else {
				// Populate with default
				$remote_addons = $this->get_default_addons();
			}

		}

		// Perhaps we have some installed that have been obsoleted/removed upstream

		$installed_addons = $this->get_local_addons();

		$all_addons = array();
		foreach ($remote_addons as $key => $addon) {
			// Can then get over-ridden
			$addon['installed'] = false;
			$all_addons[$key] = $addon;
		}

		foreach ($installed_addons as $key => $addon) {
			if (isset($all_addons[$key])) {
				// The remote info over-writes all else
				$newaddon = $all_addons[$key];
				$newaddon['installed'] = true;
				$newaddon['installedversion'] = $addon['installedversion'];
				
			} else {
				$newaddon = $addon;
				if (!empty($newaddon['installedversion'])) $newaddon['installed'] = true;
			}
			$all_addons[$key] = $newaddon;
		}

		$this->available_addons = $all_addons;
		return $all_addons;

	}

	// Returns either true or a WP_Error
	public function connection_status() {

		$options = $this->get_option(UDADDONS2_SLUG.'_options');

		$try_siteid = false;

		$oval = is_object($this->plug_updatechecker) ? get_site_option($this->plug_updatechecker->optionName, null) : null;
		// Detect the case where the password has been removed
		if (is_object($oval) && !empty($oval->lastCheck) && time()-$oval->lastCheck < 86400*8) {
			$try_siteid = $this->siteid();
		}

		// Username and password set up?
		if (empty($options['email']) || (empty($options['password']) && $try_siteid == false)) return new WP_Error('blank_details', __('You need to supply both an email address and a password', 'updraftplus'));

		// Hash will change if the account changes (password change is handled by the options filter)
		$ehash = substr(md5($options['email']), 0, 23);
		$trans = get_site_transient('udaddons_connect_'.$ehash);

		// In debug mode, we don't cache
		if ($this->debug !== true && !isset($_GET['udm_refresh']) && is_array($trans)) {
			if (isset($trans['myaddons']) && is_array($trans['myaddons'])) {
				$this->user_addons = $trans['myaddons'];
			}
			if (isset($trans['availableaddons']) && is_array($trans['availableaddons'])) {
				$this->remote_addons = $trans['availableaddons'];
			}
			if (isset($trans['support']) && is_array($trans['support'])) {
				$this->user_support = $trans['support'];
			}
			return true;
		}

		$password = isset($options['password']) ? $options['password'] : '';

		$connect = $this->connect($options['email'], $password);

		if (is_wp_error($connect)) return $connect;
		if (false === $connect) return new WP_Error('failed_connection', __('We failed to successfully connect to UpdraftPlus.Com', 'updraftplus'));

		if (!is_bool($connect)) return new WP_Error('bad_response', __('UpdraftPlus.Com responded, but we did not understand the response', 'updraftplus'));

		return true;

	}

	// Returns either true (in which case the add-ons array is populated), or a WP_Error
	private function connect($email, $password) {

		// Used previous response, if available
		if (is_array($this->user_addons) && count($this->user_addons)>0) return true;

		// We sent the password in the clear; but then, so does the user every time they log in at updraftplus.com anyway, so there is no difference
		$url = $this->url.'/plugin-info/?udm_action=connect';

		// The 'password' encoded here is the updraftplus.com password. See here: https://updraftplus.com/faqs/tell-me-about-my-updraftplus-com-account/. Note - it may be empty if the user removed their password.
		$result = wp_remote_post($url,
			array(
				'timeout' => 15,
				'body' => array(
					'e' => $email,
					'p' => base64_encode($password),
					'sid' => $this->siteid(),
					'sn' => base64_encode(get_bloginfo('name')),
					'su' => base64_encode(home_url()),
					'f' => 2
				) 
			)
		);

		if (is_wp_error($result) || false === $result) return $result;

// 		$response = maybe_unserialize($result['body']);
		$response = json_decode($result['body'], true);

		if (!is_array($response) || !isset($response['updraftpluscom']) || !isset($response['loggedin'])){
			$ser_resp = htmlspecialchars(serialize($response));
			if (preg_match('/has banned your IP address \(([\.:0-9a-f]+)\)/', $response, $matches)){
				return new WP_Error('banned_ip', sprintf(__("UpdraftPlus.com has responded with 'Access Denied'.", 'updraftplus').'<br>'.__("It appears that your web server's IP Address (%s) is blocked.", 'updraftplus').' '.__('This most likely means that you share a webserver with a hacked website that has been used in previous attacks.', 'updraftplus').'<br> <a href="https://updraftplus.com/unblock-ip-address/" target="_blank">'.__('To remove the block, please go here.', 'updraftplus').'</a> ', $matches[1]));
			} else {
				return new WP_Error('unknown_response', sprintf(__('UpdraftPlus.Com returned a response which we could not understand (data: %s)', 'updraftplus'), $ser_resp));
			}
		}

// 		$installed_addons = $this->get_local_addons(false);
// 		if (count($installed_addons) > 15) $installed_addons = array('all');

		switch ($response['loggedin']) {
			case 'connected':
				if (isset($response['myaddons']) && is_array($response['myaddons'])) {
					$this->user_addons = $response['myaddons'];
				}
				if (isset($response['availableaddons']) && is_array($response['availableaddons'])) {
					$this->remote_addons = $response['availableaddons'];
					set_site_transient('upaddons_remote', $this->remote_addons, 14400);
				}
				if (isset($response['support']) && is_array($response['support'])) {
					$this->user_support = $response['support'];
				}
				$ehash = substr(md5($email),0,23);

				set_site_transient('udaddons_connect_'.$ehash, $response, 7200);

				// Now, trigger an update check, since things may have changed
				if ($this->plug_updatechecker) $this->plug_updatechecker->checkForUpdates();

				break;
			case 'authfailed':
				$ehash = substr(md5($email), 0, 23);
				delete_site_transient('udaddons_connect_'.$ehash);
				if (!empty($response['authproblem'])) {
					if ('invalidpassword' == $response['authproblem']) {
						$authfail_error = new WP_Error('authfailed', __('Your email address was valid, but your password was not recognised by UpdraftPlus.Com.', 'updraftplus').' <a href="?page=updraftplus&tab=addons">'.__('Go here to re-enter your password.', 'updraftplus').'</a>');
						$authfail_error->add('authfailed', __('If you have forgotten your password ', 'updraftplus').' <a href="https://updraftplus.com/my-account/lost-password/">'.__('go here to change your password on updraftplus.com.', 'updraftplus').'</a>');
						return $authfail_error;
					} elseif ('invaliduser' == $response['authproblem']) {
						return new WP_Error('authfailed', __('You entered an email address that was not recognised by UpdraftPlus.Com', 'updraftplus'));
					}
				}
				return new WP_Error('authfailed', __('Your email address and password were not recognised by UpdraftPlus.Com', 'updraftplus'));
				break;
			default:
				return new WP_Error('unknown_response', __('UpdraftPlus.Com returned a response, but we could not understand it', 'updraftplus'));
				break;
		}

		return true;

	}

}
