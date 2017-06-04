<?php

/* For all copyright, version, etc. information, please see the main plugin file */

# Gets invoked during admin_menu

# http://codex.wordpress.org/Creating_Options_Pages

if (!defined('ABSPATH')) die('No direct access allowed');

class UpdraftPlusAddOns_Options2 {

	public $slug;
	public $title;
	public $mother;

	# Object with at least get_option(), update_option() and addons_admin_url() methods
	private $options;

	public function __construct($slug, $title, $mother) {

		$this->slug = $slug;
		$this->title = $title;
		$this->mother = $mother;

		# We are called in admin_menu
		# $this->options_menu();
		
		## New actions to output the tab title and content
// 		add_action('updraftplus_settings_afternavtabs', array($this, 'settings_afternavtabs'));
		add_filter('updraftplus_addonstab_content', array($this, 'updraftplus_addonstab_content'));
		
 		add_action('admin_init', array($this, 'show_admin_notices'));
		add_action('admin_init', array($this, 'options_init'));
		register_activation_hook(UDADDONS2_SLUG, array($this, 'options_setdefaults'));

		add_filter((is_multisite() ? 'network_admin_' : '').'plugin_action_links', array($this, 'action_links'), 10, 2 );
		
		global $updraftplus_addons2;
		$this->options = $updraftplus_addons2;

	}

// 	public function settings_afternavtabs() {
// 		$addon_tabflag = (isset($_REQUEST['tab']) && 'addons' == $_REQUEST['tab']);
// 		echo '<a class="nav-tab '.($addon_tabflag ? 'nav-tab-active' : '').'" id="updraft-navtab-addons" href="#updraft-navtab-addons-content">'.__('Add-ons', 'updraftplus').'</a>';
// 	}

	public function updraftplus_addonstab_content() {
		ob_start();
		$this->options_printpage();
		return ob_get_clean();
	}

// 	public function settings_finish() {
// 		$addon_tabflag = (isset($_REQUEST['tab']) && 'addons' == $_REQUEST['tab']);
// 		echo '<div id="updraft-navtab-addons-content" style="'.(($addon_tabflag) ? '' : 'display:none;').'">';
// 		$this->options_printpage();
// 		echo '</div>';
// 	}

	public function show_admin_notices() {
		global $pagenow, $updraftplus;

		if (apply_filters('updraftplus_settings_page_render', true)) {

			if (((is_multisite() && $pagenow == 'settings.php') || (!is_multisite() && $pagenow == 'options-general.php')) && version_compare(phpversion(), '5.2.0', '<') && isset($_REQUEST['page']) && $_REQUEST['page'] == UDADDONS2_PAGESLUG) {
				add_action('all_admin_notices', array($this,'show_admin_warning_php') );
			}

			$options = $this->options->get_option(UDADDONS2_SLUG.'_options');
			if (empty($options['email']) && UpdraftPlus_Options::user_can_manage() && isset($_REQUEST['page']) && 'updraftplus' == $_REQUEST['page']) {
				add_action('all_admin_notices', array($this,'show_admin_warning_notconnected') );
			}

		}

		if ((is_multisite() && $pagenow == 'settings.php') || (!is_multisite() && $pagenow == 'options-general.php') && isset($_REQUEST['page']) && ($_REQUEST['page'] == UDADDONS2_PAGESLUG || $_REQUEST['page'] == $this->slug)) {
			$updates_available = get_site_transient('update_plugins');
			global $updraftplus_addons2;
			if (is_object($updates_available) && isset($updates_available->response) && isset($updraftplus_addons2->plug_updatechecker) && isset($updraftplus_addons2->plug_updatechecker->pluginFile) && isset($updates_available->response[$updraftplus_addons2->plug_updatechecker->pluginFile])) {
				$file = $updraftplus_addons2->plug_updatechecker->pluginFile;
				$this->plugin_update_url = wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&updraftplus_noautobackup=1&plugin=').$file, 'upgrade-plugin_'.$file);
				add_action('all_admin_notices', array($this, 'show_admin_warning_update'));
			}
		}
	}

	function show_admin_warning($message, $class = "updated") {
		echo '<div class="'.$class.'">'."<p>$message</p></div>";
	}

	public function show_admin_warning_update() {
		$this->show_admin_warning('<a id="updraftaddons_updatewarning" href="'.$this->plugin_update_url.'">'.__('An update is available for UpdraftPlus - please follow this link to get it.', 'updraftplus').'</a>');
	}

	public function show_admin_warning_notconnected() {
		global $updraftplus;
		if (0 == $updraftplus->have_addons) {
			$this->show_admin_warning(__('You have not yet connected with your UpdraftPlus.Com account, to enable you to list your purchased add-ons.', 'updraftplus').' '.__('You need to connect to receive future updates to UpdraftPlus.', 'updraftplus').' <a href="'.$this->options->addons_admin_url().'">'.__('Go here to connect.','updraftplus').'</a>');
		} else {
			$this->show_admin_warning(__('You have not yet connected with your UpdraftPlus.Com account.', 'updraftplus').' '.__('You need to connect to receive future updates to UpdraftPlus.', 'updraftplus').' <a href="'.$this->options->addons_admin_url().'">'.__('Go here to connect.','updraftplus').'</a>');
		}
	}

	public function show_admin_warning_noupdraftplus() {
		if (is_file(WP_PLUGIN_DIR.'/updraftplus/updraftplus.php')) {
			global $pagenow;
			$msg = __('UpdraftPlus is not yet activated.', 'updraftplus');
			if ($pagenow != 'plugins.php') $msg .= ' <a href="plugins.php">'.__('Go here to activate it.', 'updraftplus').'</a>';
			$this->show_admin_warning($msg);
		} else {
			$warning = __('UpdraftPlus is not yet installed.','updraftplus').' <a href="'.$this->mother.'/download/">'.__('Go here to begin installing it.', 'updraftplus').'</a>';
			if (file_exists(WP_PLUGIN_DIR.'/updraft')) $warning .= ' '.__('You do seem to have the obsolete Updraft plugin installed - perhaps you got them confused?', 'updraftplus');
			$this->show_admin_warning($warning);
		}
	}

	public function show_admin_warning_php() {
		$this->show_admin_warning(sprintf(__('Your web server\'s version of PHP is too old ('.phpversion().') - UpdraftPlus expects at least %s. You can try it, but don\'t be surprised if it does not work. To fix this problem, contact your web hosting company', 'updraftplus'), '5.2.4'), 'error');
	}

// No longer in use after switch to tab in the settings page
// 	public function options_menu() {
// 		# http://codex.wordpress.org/Function_Reference/add_options_page
// 		if (is_multisite()) {
// 			if (is_super_admin()) add_submenu_page('settings.php', $this->title, $this->title, 'manage_options', UDADDONS2_PAGESLUG, array($this, 'options_printpage'));
// 		} else {
// 			add_options_page($this->title, $this->title, 'manage_options', UDADDONS2_PAGESLUG, array($this, 'options_printpage'));
// 		}
// 	}

	# Registered under admin_init
	public function options_init() {

		# Register a new set of options, named $slug_options, stored in the database entry $slug_options
		# We register and use the printing facilities for multisite too

		register_setting( UDADDONS2_SLUG.'_options', UDADDONS2_SLUG.'_options' , array($this, 'options_validate') );

		add_settings_section ( UDADDONS2_SLUG.'_options', __('Connect with your UpdraftPlus.Com account', 'updraftplus'), array($this, 'options_header') , UDADDONS2_SLUG);

		add_settings_field ( UDADDONS2_SLUG.'_options_email', __('Email', 'updraftplus'), array($this, 'options_email'), UDADDONS2_SLUG , UDADDONS2_SLUG.'_options' );

		add_settings_field ( UDADDONS2_SLUG.'_options_password', __('Password', 'updraftplus'), array($this, 'options_password'), UDADDONS2_SLUG , UDADDONS2_SLUG.'_options' );

		if (is_multisite() && (isset($_POST['action']) && $_POST['action'] == 'update') && !empty($_POST['updraftplus-addons_options'])) {
			$this->update_wpmu_options();
		}



	}

	public function options_setdefaults() {
		$tmp = $this->options->get_option(UDADDONS2_SLUG.'_options');
		if (!is_array($tmp)) {
			$arr = array(
				"email" => "",
				"password" => ""
			);
			$this->options->update_option(UDADDONS2_SLUG.'_options', $arr);
		}
	}

	# Various functions for outputing each of the options fields
	public function options_email() {
		$options = $this->options->get_option(UDADDONS2_SLUG.'_options');
		?>
		<label for="<?php echo UDADDONS2_SLUG ?>_options_email">
		<input id="<?php echo UDADDONS2_SLUG ?>_options_email" type="text" size="36" name="<?php echo UDADDONS2_SLUG ?>_options[email]" value="<?php print htmlspecialchars($options['email']); ?>" />
		<br /><a href="<?php echo $this->mother ?>/my-account/"><?php _e("Not yet got an account (it's free)? Go get one!", 'updraftplus'); ?></a>
		</label>
		<?php
	}

	public function options_password() {
		$options = $this->options->get_option(UDADDONS2_SLUG.'_options');
		$password = isset($options['password']) ? $options['password'] : '';
		?>
		<label for="<?php echo UDADDONS2_SLUG ?>_options_password">
		<input id="<?php echo UDADDONS2_SLUG ?>_options_password" type="password" size="36" name="<?php echo UDADDONS2_SLUG ?>_options[password]" value="<?php print htmlspecialchars($password); ?>" />
		<br /><a href="<?php echo $this->mother ?>/my-account/?action=lostpassword"><?php _e('Forgotten your details?', 'updraftplus'); ?></a>
		</label>
		<?php
	}

	public function options_header() {
		//settings_errors();
	}

	# This function is registered via register_setting. It is intended to return sanitised output, and can optionally call add_settings_error to whinge about anything faulty
	public function options_validate($input) {
		# See: http://codex.wordpress.org/Function_Reference/add_settings_error

		// When the options are re-saved, clear any previous cache of the connection status
		$ehash = substr(md5($input['email']), 0, 23);
		delete_site_transient('udaddons_connect_'.$ehash);

	// 	add_settings_error( UDADDONS2_SLUG."_options", UDADDONS2_SLUG."_options_nodb", "Whinge, whinge", "error" );

		return $input;
	}

	// Return an array of errors (if any);
	public function update_wpmu_options() {
		if ( !UpdraftPlus_Options::user_can_manage() ) return;
		$options = $this->options->get_option(UDADDONS2_SLUG.'_options');
		if (!is_array($options)) $options=array();

		foreach ($_POST as $key => $value) {
			if ('updraftplus-addons_options' == $key && is_array($value) && isset($value['email']) && isset($value['password'])) {
				$options['email'] = $value['email'];
				$options['password'] = $value['password'];
			}
		}

		$options = $this->options_validate($options);

		$this->options->update_option(UDADDONS2_SLUG.'_options', $options);
	}

	# This is the function outputting the HTML for our options page
	public function options_printpage() {
		if (!UpdraftPlus_Options::user_can_manage()) wp_die( __('You do not have sufficient permissions to access this page.') );

		$options = $this->options->get_option(UDADDONS2_SLUG.'_options');
		$user_and_pass_at_top = (empty($options['email'])) ? true : false;

		$title = htmlspecialchars($this->title);
		$mother = $this->mother;

		echo <<<ENDHERE
	<div class="wrap">
		
ENDHERE;

		$enter_credentials_begin = UpdraftPlus_Options::options_form_begin('', false);

		if (is_multisite()) $enter_credentials_begin .= '<input type="hidden" name="action" value="update">';

		$interested = htmlspecialchars(__('Interested in knowing about your UpdraftPlus.Com password security? Read about it here.', 'updraftplus'));

		$connect = htmlspecialchars(__('Connect', 'updraftplus'));

		$enter_credentials_end = <<<ENDHERE
			<p style="margin-left: 258px;">
				<input id="ud_connectsubmit" type="submit" class="button-primary" value="$connect" />
			</p>
			<p style="margin-left: 258px; font-size: 70%"><em><a href="https://updraftplus.com/faqs/tell-me-about-my-updraftplus-com-account/">$interested</a></em></p>
		</form>
ENDHERE;

		global $updraftplus_addons2;
// 		$this->connected = (!empty($options['email']) && !empty($options['password'])) ? $updraftplus_addons2->connection_status() : false;
		$this->connected = !empty($options['email']) ? $updraftplus_addons2->connection_status() : false;

		if (true !== $this->connected) {
			if (is_wp_error($this->connected)) {
				$connection_errors = array();
				foreach ($this->connected->get_error_messages() as $key => $msg) {
					$connection_errors[] = $msg;
				}
			} else {
				if (!empty($options['email']) && !empty($options['password'])) $connection_errors = array(__('An unknown error occurred when trying to connect to UpdraftPlus.Com', 'updraftplus'));
			}
			$this->connected = false;
		}

		if ($this->connected) {
			echo '<p style="clear: both; float: left;">'.__('You are presently <strong>connected</strong> to an UpdraftPlus.Com account.', 'updraftplus');
			echo ' <a href="#" onclick="jQuery(\'#ud_connectsubmit\').click();">'.__('If you bought new add-ons, then follow this link to refresh your connection', 'updraftplus').'</a>.';
			if (!empty($options['password'])) echo ' '.__("Note that after you have claimed your add-ons, you can remove your password (but not the email address) from the settings below, without affecting this site's access to updates.", 'updraftplus');
		} else {

// 			$oval = is_object($this->plug_updatechecker) ? get_site_option($this->plug_updatechecker->optionName, null) : null;
// 			// Detect the case where the password has been removed
// 			if (is_object($oval) && !empty($oval->lastCheck) && time()-$oval->lastCheck < 86400*8) {
// 			} else {

			echo "<p>".__('You are presently <strong>not connected</strong> to an UpdraftPlus.Com account.', 'updraftplus');

// 			}

		}

		echo '</p>';

		if (isset($connection_errors)) {
			echo '<div class="error"><p><strong>'.__('Errors occurred when trying to connect to UpdraftPlus.Com:','updraftplus').'</strong></p><ul>';
			foreach ($connection_errors as $err) {
				echo '<li style="list-style:disc inside;">'.$err.'</li>';
			}
			echo '</ul></div>';
		}

		$sid = $updraftplus_addons2->siteid();

		$home_url = home_url();

		// Enumerate possible unclaimed/re-claimable purchases, and what should be active on this site
		$unclaimed_available = array();
		$assigned = array();
		$have_all = false;
		if ($this->connected && isset($updraftplus_addons2->user_addons) && is_array($updraftplus_addons2->user_addons)) {
			foreach ($updraftplus_addons2->user_addons as $akey => $addon) {
				// Keys: site, sitedescription, key, status
				if (isset($addon['status']) && 'active' == $addon['status'] && isset($addon['site']) && ('unclaimed' == $addon['site'] || 'unlimited' == $addon['site'])) {
					$key = $addon['key'];
					$unclaimed_available[$key] = array('eid' => $akey, 'status' => 'available');
				} elseif (isset($addon['status']) && 'active' == $addon['status'] && isset($addon['site']) && $addon['site'] == $sid) {
					$key = $addon['key'];
					$assigned[$key] = $akey;
					if ('all' == $key) $have_all=true;
				} elseif (isset($addon['sitedescription']) && ($this->normalise_url($home_url) === $this->normalise_url($addon['sitedescription']) || 0 === strpos($addon['sitedescription'], $home_url.' - '))) {
					# Is assigned to a site with the same URL as this one - allow a reclaim
					$key = $addon['key'];
					$unclaimed_available[$key] = array('eid' => $akey, 'status' => 'reclaimable');
				}
			}
		}

		if (!$this->connected) $this->show_credentials_form($enter_credentials_begin, $enter_credentials_end);

		$email = isset($options['email']) ? $options['email'] : '';
		$pass = isset($options['password']) ? base64_encode($options['password']) : '';
		$sn = base64_encode(get_bloginfo('name'));
		$su = base64_encode($home_url);
		$ourpageslug = UDADDONS2_PAGESLUG;
		$mother = $this->mother;

		//$href = (is_multisite()) ? 'settings.php' : 'options-general.php';
		$href = UpdraftPlus_Options::admin_page_url();

		if (count($unclaimed_available) > 0) {
			$nonce = wp_create_nonce('udmanager-nonce');
			$pleasewait = htmlspecialchars(__('Please wait whilst we make the claim...', 'updraftplus'));
			$notgranted = esc_js(__('Claim not granted - perhaps you have already used this purchase somewhere else, or your paid period for downloading from updraftplus.com has expired?', 'updraftplus'));
			$notgrantedlogin = esc_js(__('Claim not granted - your account login details were wrong', 'updraftplus'));
			$ukresponse = esc_js(__('An unknown response was received. Response was:', 'updraftplus'));
			echo <<<ENDHERE
		<div id="udm_pleasewait" class="updated" style="border: 1px solid; padding: 10px; margin-top: 10px; margin-bottom: 10px; clear: both; float: left; display:none;"><strong>$pleasewait</strong></div>
		<script type="text/javascript">
			function udm_claim(key) {
				var data = {
						action: 'udaddons_claimaddon',
						nonce: '$nonce',
						key: key
				};
				jQuery('#udm_pleasewait').fadeIn();
				jQuery.post(ajaxurl, data, function(response) {
					if ('ERR' == response) {
						alert("$notgranted");
					} else if (response == 'OK') {
						window.location.href = '$href?page=$ourpageslug&udm_refresh=1&udm_clearcred=1&tab=addons';
					} else if (response == 'BADAUTH') {
						alert("$notgrantedlogin");
					} else {
						alert("$ukresponse "+response);
					}
					jQuery('#udm_pleasewait').fadeOut();
				});
			}
		</script>
ENDHERE;
		}

		$this->update_js = '';

		echo '<h3 style="clear:left; margin-top: 10px;">'.__('UpdraftPlus Addons', 'updraftplus').'</h3><div>';

		$addons = $updraftplus_addons2->get_available_addons();

		$this->plugin_update_url = 'update-core.php';
		# Can we get a direct update URL?
		$updates_available = get_site_transient('update_plugins');

		if (is_object($updates_available) && isset($updates_available->response) && isset($updraftplus_addons2->plug_updatechecker) && isset($updraftplus_addons2->plug_updatechecker->pluginFile) && isset($updates_available->response[$updraftplus_addons2->plug_updatechecker->pluginFile])) {
			$file = $updraftplus_addons2->plug_updatechecker->pluginFile;
			$this->plugin_update_url = wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&updraftplus_noautobackup=1&plugin=').$file, 'upgrade-plugin_'.$file);
			$this->update_js = '<script>jQuery(document).ready(function() { jQuery(\'#updraftaddons_updatewarning\').html(\''.esc_js(__('An update containing your addons is available for UpdraftPlus - please follow this link to get it.', 'updraftplus')).'\') });</script>';
			
		}

		$first = '';
		$second = '';
		$third = '';

		if (is_array($addons)) {
			foreach ($addons as $key => $addon) {
				extract($addon);
				if (empty($addon['latestversion'])) $latestversion = false;
				if (empty($addon['installedversion'])) $installedversion = false;
				if (empty($addon['installed']) && $installedversion == false) $installed = false;
				$unclaimed = (isset($unclaimed_available[$key])) ? $unclaimed_available[$key] : false;
				$is_assigned = (isset($assigned[$key])) ? $assigned[$key] : false;
				$box = $this->addonbox($key, $name, $shopurl, $description, trim($installedversion), trim($latestversion), $installed, $unclaimed, $is_assigned, $have_all);
				if ($is_assigned) {
					$first .= $box;
				} elseif (!empty($unclaimed)) {
					$second .= $box;
				} else {
					$third .= $box;
				}
			}
		} else {
			echo "<em>".__('An error occurred when trying to retrieve your add-ons.', 'updraftplus')."</em>";
		}

		echo $first.$second.$third;

echo <<<ENDHERE
		</div>
ENDHERE;

		echo $this->update_js;

		// TODO: Show their support package, if any - ?
		if (is_array($updraftplus_addons2->user_support)) {
			// Keys: 
		}

		echo '<h3>'.__('UpdraftPlus Support', 'updraftplus').'</h3>
<ul>
<li style="list-style:disc inside;">'.__('Need to get support?','updraftplus').' <a href="'.$mother.'/support/">'.__('Go here', 'updraftplus')."</a>.</li>
</ul>";

		if ($this->connected) {
			echo "<hr>";
			$this->show_credentials_form($enter_credentials_begin, $enter_credentials_end);
		}

		echo '</div>';

	}

	// This may produce a URL that does not actually reference the same location; its purpose is to use in comparisons of two URLs that *both* go through this function, only
	private function normalise_url($url) {
		if (preg_match('/^(\S+) - /', ltrim($url), $matches)) $url = $matches[1];
		$parsed_descrip_url = parse_url($url);
		if (is_array($parsed_descrip_url) && isset($parsed_descrip_url['host'])) {
			if (preg_match('/^www\./i', $parsed_descrip_url['host'], $matches)) $parsed_descrip_url['host'] = substr($parsed_descrip_url['host'], 4);
			$normalised_descrip_url = 'http://'.strtolower($parsed_descrip_url['host']);
			if (!empty($parsed_descrip_url['port'])) $normalised_descrip_url .= ':'.$parsed_descrip_url['port'];
			if (!empty($parsed_descrip_url['path'])) $normalised_descrip_url .= untrailingslashit($parsed_descrip_url['path']);
		} else {
			$normalised_descrip_url = untrailingslashit($url);
		}
		return $normalised_descrip_url;
	}
	
	private function addonbox($key, $name, $shopurl, $description, $installedversion, $latestversion = false, $installed = false, $unclaimed = false, $is_assigned = false, $have_all = false) {
		$urlbase = UDADDONS2_URL;
		$mother = $this->mother;
		if ($installed || ($have_all && $key == 'all')) {
			$blurb="<p>";
			$preblurb="<div style=\"float:right;\"><img src=\"$urlbase/yes.png\" width=\"85\" height=\"98\" alt=\"".__("You've got it", 'updraftplus')."\"></div>";
			if ($key !='all') {
				$blurb .= sprintf(__('Your version: %s', 'updraftplus'), $installedversion);
				if (!empty($latestversion) && $latestversion == $installedversion) {
					$blurb .= " (".__('latest', 'updraftplus').')';
				} elseif (!empty($latestversion) && version_compare($latestversion, $installedversion, '>')) {
					$blurb.=" (".__('latest' ,'updraftplus').": $latestversion - <a href=\"".$this->plugin_update_url."\">update</a>)";
				} else {
					$blurb .= " ".__('(apparently a pre-release or withdrawn release)', 'updraftplus');
				}
			}
			$blurb.="</p>";
		} else {
			if ($have_all && $key != 'all') {
				$blurb='<p><strong>'.__('Available for this site (via your all-addons purchase)', 'updraftplus').' - <a href="'.$this->plugin_update_url.'">'.__('please follow this link to update the plugin in order to get it', 'updraftplus').'</a></strong></p>';
				$preblurb="";
			} elseif ($is_assigned) {
				$blurb='<p><strong>'.__('Assigned to this site', 'updraftplus').' - <a href="'.$this->plugin_update_url.'">'.__('please  follow this link to update the plugin in order to activate it','updraftplus').'</a></strong></p>';
				$preblurb="";
			} elseif (is_array($unclaimed)) {
				// Keys: eid = unique ID, status = available|reclaimable
				// Value of $unclaimed is a unique id, though we won't particularly use it
				global $updraftplus_addons2;
				$sid = $updraftplus_addons2->siteid();
				if (isset($unclaimed['status']) && 'reclaimable' == $unclaimed['status']) {
					$blurb='<p><strong>'.__('Available to claim on this site', 'updraftplus').' - <a href="#" onclick="udm_claim(\''.$key.'\');">'.__('activate it on this site','updraftplus').'</a></strong></p>';
				} else {
					$blurb='<p><strong>'.__('You have an inactive purchase', 'updraftplus').' - <a href="#" onclick="udm_claim(\''.$key.'\');">'.__('activate it on this site','updraftplus').'</a></strong></p>';
				}
					$preblurb="";
			} else {
				$blurb='<p><a href="'.$mother.$shopurl.'">'.__('Get it from the UpdraftPlus.Com Store','updraftplus').'</a>'.(($this->connected) ? '' : ' '.__('(or connect using the form on this page if you have already purchased it)', 'updraftplus')).'</p>';
				$preblurb="<div style=\"float:right;\"><a href=\"${mother}${shopurl}\"><img src=\"$urlbase/shopcart.png\" width=\"120\" height=\"98\" alt=\"".__('Buy It','updraftplus')."\"></a></div>";
			}
		}
		return <<<ENDHERE
			<div style="border: 1px solid; border-radius: 4px; padding: 0px 12px 0px; min-height: 110px; width: 680px; margin-bottom: 16px; background-color:#fff;">
			$preblurb
			<div style="width: 580px;"><h2 style="">$name</h2>
			$description<br>
			$blurb
			</div>
			</div>
ENDHERE;
	}

	private function show_credentials_form($enter_credentials_begin, $enter_credentials_end) {

		echo $enter_credentials_begin;

		//We have to duplicate settings_fields() in order to set our referer
		//settings_fields(UDADDONS2_SLUG.'_options');

		$option_group = UDADDONS2_SLUG.'_options';
		echo "<input type='hidden' name='option_page' value='" . esc_attr($option_group) . "' />";
		echo '<input type="hidden" name="action" value="update" />';

		//wp_nonce_field("$option_group-options");

		// This one is used on multisite
		echo '<input type="hidden" name="tab" value="addons" />';

		$name = "_wpnonce";
		$action = esc_attr( $option_group."-options" );
		$nonce_field = '<input type="hidden" id="' . $name . '" name="' . $name . '" value="' . wp_create_nonce( $action ) . '" />';
	
		echo $nonce_field;

		if (function_exists('wp_unslash')) {
			$referer = esc_attr(wp_unslash( $_SERVER['REQUEST_URI'] ));
		} else {
			$referer = esc_attr(stripslashes_deep( $_SERVER['REQUEST_URI'] ));
		}

		// This one is used on single site installs
		if (false === strpos($referer, '?')) {
			$referer .= '?tab=addons';
		} else {
			$referer .= '&tab=addons';
		}

		echo '<input type="hidden" name="_wp_http_referer" value="'. $referer . '" />';
		// End of duplication of settings-fields()

		do_settings_sections(UDADDONS2_SLUG);
		echo $enter_credentials_end;
	}

	public function action_links($links, $file) {
		if ( $file == "updraftplus/updraftplus.php" ) {
			array_unshift( $links, '<a href="'.$this->options->addons_admin_url().'">'.__('Manage Addons', 'updraftplus').'</a>');
		}
		return $links;
	}

}

?>
