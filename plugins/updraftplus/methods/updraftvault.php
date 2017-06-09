<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed.');

require_once(UPDRAFTPLUS_DIR.'/methods/s3.php');

class UpdraftPlus_BackupModule_updraftvault extends UpdraftPlus_BackupModule_s3 {

	private $vault_mothership = 'https://vault.updraftplus.com/plugin-info/';

	private $vault_config;

	// This function makes testing easier, rather than having to change the URLs in multiple places
	private function get_url($which_page = false) {
		$base = (defined('UPDRAFTPLUS_VAULT_SHOP_BASE')) ? UPDRAFTPLUS_VAULT_SHOP_BASE : 'https://updraftplus.com/shop/';
		switch ($which_page) {
			case 'get_more_quota';
				return $base.'product-category/updraftplus-vault/';
				break;
			case 'more_vault_info_faqs';
				return 'https://updraftplus.com/support/updraftplus-vault-faqs/';
				break;
			case 'more_vault_info_landing';
				return 'https://updraftplus.com/landing/vault';
				break;
			case 'vault_forgotten_credentials_links';
				return 'https://updraftplus.com/my-account/lost-password/';
				break;
			default:
				return $base;
				break;
		}
	}

	public function get_opts() {
		global $updraftplus;
		$opts = $updraftplus->get_job_option('updraft_updraftvault');
		if (!is_array($opts)) $opts = array('token' => '', 'email' => '', 'quota' => -1);
		return $opts;
	}

	public function get_credentials() {
		return array('updraft_updraftvault');
	}

	protected function vault_set_config($config) {
		$config['whoweare'] = 'Updraft Vault';
		$config['whoweare_long'] = __('Updraft Vault', 'updraftplus');
		$config['key'] = 'updraftvault';
		$this->vault_config = $config;
	}

	protected function get_config() {

		global $updraftplus;

		// Have we already done this?
		if (!empty($this->vault_config)) return $this->vault_config;

		// Stored in the job?
		if ($job_config = $updraftplus->jobdata_get('updraftvault_config')) {
			if (!empty($job_config) && is_array($job_config)) {
				$this->vault_config = $job_config;
				return $job_config;
			}
		}

		// Pass back empty settings, if nothing better can be found - this ensures that the error eventually is raised in the right place
		$config = array('accesskey' => '', 'secretkey' => '', 'path' => '');
		$config['whoweare'] = 'Updraft Vault';
		$config['whoweare_long'] = __('Updraft Vault', 'updraftplus');
		$config['key'] = 'updraftvault';

		// Get the stored options
		$opts = $this->get_opts();

		if (!is_array($opts) || empty($opts['token']) || empty($opts['email'])) {
			// Not connected
			$updraftplus->log("UpdraftPlus Vault: this site has not been connected - check your settings");
			$this->vault_config = $config;
			$updraftplus->jobdata_set('updraftvault_config', $config);
			return $config;
		}

		$site_id = $updraftplus->siteid();

		$updraftplus->log("UpdraftPlus Vault: requesting access details (sid=$site_id, email=".$opts['email'].")");

		// Request the credentials using our token
		$post_body = array(
			'e' => (string)$opts['email'],
			'sid' => $site_id,
			'token' => (string)$opts['token'],
			'su' => base64_encode(home_url())
		);

		if (!empty($this->vault_in_config_print)) {
			// In this case, all that the get_config() is being done for is to get the quota info. Send back the cached quota info instead (rather than have an HTTP trip every time the settings page is loaded). The config will get updated whenever there's a backup, or the user presses the link to update.
			$getconfig = get_transient('udvault_last_config');
		}

		// Use SSL to prevent snooping
		if (empty($getconfig) || !is_array($getconfig)) {
			$getconfig = wp_remote_post($this->vault_mothership.'/?udm_action=vault_getconfig', array(
				'timeout' => 25,
				'body' => $post_body,
			));
		}
		
		$details_retrieved = false;

		if (!is_wp_error($getconfig) && false != $getconfig && isset($getconfig['body'])) {

			$response_code = wp_remote_retrieve_response_code($getconfig);
		
			if ($response_code >= 200 && $response_code < 300) {
				$response = json_decode(wp_remote_retrieve_body($getconfig), true);

				if (is_array($response) && isset($response['user_messages']) && is_array($response['user_messages'])) {
					foreach ($response['user_messages'] as $message) {
						if (!is_array($message)) continue;
						$msg_txt = $this->vault_translate_remote_message($message['message'], $message['code']);
						$updraftplus->log($msg_txt, $message['level'], $message['code']);
					}
				}

				if (is_array($response) && isset($response['accesskey']) && isset($response['secretkey']) && isset($response['path'])) {
					$details_retrieved = true;
					$opts['last_config']['accesskey'] = $response['accesskey'];
					$opts['last_config']['secretkey'] = $response['secretkey'];
					$opts['last_config']['path'] = $response['path'];
					unset($opts['last_config']['quota_root']);
					if (!empty($response['quota_root'])) {
						$opts['last_config']['quota_root'] = $response['quota_root'];
						$config['quota_root'] = $response['quota_root'];
						$opts['quota_root'] = $response['quota_root'];
					}
					$opts['last_config']['time'] = time();
					// This is just a cache of the most recent setting
					if (isset($response['quota'])) {
						$opts['quota'] = $response['quota'];
						$config['quota'] = $response['quota'];
					}
					UpdraftPlus_Options::update_updraft_option('updraft_updraftvault', $opts);
					$config['accesskey'] = $response['accesskey'];
					$config['secretkey'] = $response['secretkey'];
					$config['path'] = $response['path'];
				} elseif (is_array($response) && isset($response['result']) && ('token_unknown' == $response['result'] || 'site_duplicated' == $response['result'])) {
					$updraftplus->log("This site appears to not be connected to UpdraftPlus Vault");
					$config['accesskey'] = '';
					$config['secretkey'] = '';
					$config['path'] = '';
					unset($config['quota']);
					if (!empty($response['message'])) $config['error_message'] = $response['message'];
					$details_retrieved = true;
				} else {
					if (is_array($response) && !empty($response['result'])) {
						$msg = "UpdraftVault response code: ".$response['result'];
						if (!empty($response['code'])) $msg .= " (".$response['code'].")";
						if (!empty($response['message'])) $msg .= " (".$response['message'].")";
						if (!empty($response['data'])) $msg .= " (".json_encode($response['data']).")";
						$updraftplus->log($msg);
// 						if ('token_unknown' == $response['result']) {
// 						} elseif ('db_error' == $response['result']) {
// 						} elseif ('url_error' == $response['result']) {
// 						}
					} else {
						$updraftplus->log("Received response, but it was not in the expected format: ".substr(wp_remote_retrieve_body($getconfig), 0, 100).' ...');
					}
				}
			} else {
				$updraftplus->log("Unexpected HTTP response code (please try again later): ".$response_code);
			}
		} elseif (is_wp_error($getconfig)) {
			$updraftplus->log_wp_error($getconfig);
		} else {
			if (!isset($getconfig['accesskey'])) {
				$updraftplus->log("Vault: wp_remote_post returned a result that was not understood (".gettype($getconfig).")");
			}
		}

		if (!$details_retrieved) {
			// Don't log anything yet, as this will replace the most recently logged message in the main panel
			if (!empty($opts['last_config']) && is_array($opts['last_config'])) {
				$last_config = $opts['last_config'];
				if (!empty($last_config['time']) && is_numeric($last_config['time']) && $last_config['time'] > time() - 86400*15) {
					if ($updraftplus->backup_time) $updraftplus->log("UpdraftPlus Vault: failed to retrieve access details from updraftplus.com: will attempt to use most recently stored configuration");
					if (!empty($last_config['accesskey'])) $config['accesskey'] = $last_config['accesskey'];
					if (!empty($last_config['secretkey'])) $config['secretkey'] = $last_config['secretkey'];
					if (isset($last_config['path'])) $config['path'] = $last_config['path'];
					if (isset($opts['quota'])) $config['quota'] = $opts['quota'];
				} else {
					if ($updraftplus->backup_time) $updraftplus->log("UpdraftPlus Vault: failed to retrieve access details from updraftplus.com: no recently stored configuration was found to use instead");
				}
			}
		}

		$config['server_side_encryption'] = 'AES256';

		$this->vault_config = $config;
		$updraftplus->jobdata_set('updraftvault_config', $config);
		set_transient('udvault_last_config', $config, 86400*7);
		return $config;
	}

	public function vault_translate_remote_message($message, $code) {
		switch ($code) {
			case 'premium_overdue':
			return __('Your UpdraftPlus Premium purchase is over a year ago. You should renew immediately to avoid losing the 12 months of free storage allowance that you get for being a current UpdraftPlus Premium customer.', 'updraftplus');
			break;
			case 'vault_subscription_overdue':
			return __('You have an UpdraftPlus Vault subscription with overdue payment. You are within the few days of grace period before it will be suspended, and you will lose your quota and access to data stored within it. Please renew as soon as possible!', 'updraftplus');
			break;
			case 'vault_subscription_suspended':
			return __("You have an UpdraftPlus Vault subscription that has not been renewed, and the grace period has expired. In a few days' time, your stored data will be permanently removed. If you do not wish this to happen, then you should renew as soon as possible.", 'updraftplus');
			// The following shouldn't be a possible response (the server can deal with duplicated sites with the same IDs) - but there's no harm leaving it in for now (Dec 2015)
			// This means that the site is accessing with a different home_url() than it was registered with.
			case 'site_duplicated':
			return __('No Vault connection was found for this site (has it moved?); please disconnect and re-connect.', 'updraftplus');
			break;
		}
		return $message;
	}

	public function config_print() {

		// Used to decide whether we can afford HTTP calls or not, or would prefer to rely on cached data
		$this->vault_in_config_print = true;

		$shop_url_base = $this->get_url();
		$get_more_quota = $this->get_url('get_more_quota');

		$vault_settings = UpdraftPlus_Options::get_updraft_option('updraft_updraftvault');
		$connected = (is_array($vault_settings) && !empty($vault_settings['token']) && !empty($vault_settings['email'])) ? true : false;
		?>

		<tr class="updraftplusmethod updraftvault">
			<th><img id="vaultlogo" src="<?php echo esc_attr(UPDRAFTPLUS_URL.'/images/updraftvault-150.png');?>" alt="UpdraftPlus Vault" width="150" height="116"></th>
			<td valign="top" id="updraftvault_settings_cell">
			<?php
				global $updraftplus_admin;
				if (!class_exists('SimpleXMLElement')) {
					
					$updraftplus_admin->show_double_warning('<strong>'.__('Warning', 'updraftplus').':</strong> '.sprintf(__("Your web server's PHP installation does not included a <strong>required</strong> (for %s) module (%s). Please contact your web hosting provider's support and ask for them to enable it.", 'updraftplus'), 'UpdraftPlus Vault', 'SimpleXMLElement'), 'updraftvault');
				}

				$updraftplus_admin->curl_check('UpdraftPlus Vault', false, 'updraftvault', true);
			?>
			
				<div id="updraftvault_settings_default"<?php if ($connected) echo ' style="display:none;" class="updraft-hidden"';?>>
					<p>
						<?php echo __('UpdraftPlus Vault brings you storage that is <strong>reliable, easy to use and a great price</strong>.', 'updraftplus').' '.__('Press a button to get started.', 'updraftplus');?>
					</p>
					<div class="vault_primary_option clear-left">
						<div><strong><?php _e('First time user?', 'updraftplus');?></strong></div>
						<button id="updraftvault_showoptions" class="button-primary"><?php _e('Show the options', 'updraftplus');?></button>
					</div>
					<div class="vault_primary_option">
						<div><strong><?php _e('Already purchased space?', 'updraftplus');?></strong></div>
						<button id="updraftvault_connect" class="button-primary"><?php _e('Connect', 'updraftplus');?></button>
					</div>
					<p>
						<em><?php _e("UpdraftPlus Vault is built on top of Amazon's world-leading data-centres, with redundant data storage to achieve 99.999999999% reliability.", 'updraftplus');?> <a target="_blank" href="<?php esc_attr_e($this->get_url('more_vault_info_landing')); ?>"><?php _e('Read more about it here.', 'updraftplus');?></a> <a target="_blank" href="<?php echo esc_attr($this->get_url('more_vault_info_faqs')); ?>"><?php _e('Read the FAQs here.', 'updraftplus');?></a></em>
					</p>
				</div>

				<div id="updraftvault_settings_showoptions" style="display:none;" class="updraft-hidden">
					<p>
						<?php echo __('UpdraftPlus Vault brings you storage that is <strong>reliable, easy to use and a great price</strong>.', 'updraftplus').' '.__('Press a button to get started.', 'updraftplus');?>
					</p>
					<div class="vault-purchase-option">
						<div class="vault-purchase-option-size">5 GB</div>
						<div class="vault-purchase-option-link"><a target="_blank" href="https://updraftplus.com/vault-5gb-quarterly"><?php printf(__('%s per quarter', 'updraftplus'), '$10'); ?></a></div>
						<div class="vault-purchase-option-or"><?php _e('or (annual discount)', 'updraftplus');?></div>
						<div class="vault-purchase-option-link"><a target="_blank" href="https://updraftplus.com/vault-5gb-annual"><?php printf(__('%s per year', 'updraftplus'), '$35'); ?></a></div>
					</div>
					<div class="vault-purchase-option">
						<div class="vault-purchase-option-size">15 GB</div>
						<div class="vault-purchase-option-link"><a target="_blank" href="https://updraftplus.com/vault-15gb-quarterly"><?php printf(__('%s per quarter', 'updraftplus'), '$20'); ?></a></div>
						<div class="vault-purchase-option-or"><?php _e('or (annual discount)', 'updraftplus');?></div>
						<div class="vault-purchase-option-link"><a target="_blank" href="https://updraftplus.com/vault-15gb-annual"><?php printf(__('%s per year', 'updraftplus'), '$70');?></a></div>
					</div>
					<div class="vault-purchase-option">
						<div class="vault-purchase-option-size">50 GB</div>
						<div class="vault-purchase-option-link"><a target="_blank" href="https://updraftplus.com/vault-50gb-quarterly"><?php printf(__('%s per quarter', 'updraftplus'), '$50'); ?></a></div>
						<div class="vault-purchase-option-or"><?php _e('or (annual discount)', 'updraftplus');?></div>
						<div class="vault-purchase-option-link"><a target="_blank" href="https://updraftplus.com/vault-50gb-annual"><?php printf(__('%s per year', 'updraftplus'), '$175');;?></a></div>
					</div>
					<p class="clear-left padding-top-20px">
						<?php echo __('Payments can be made in US dollars, euros or GB pounds sterling, via card or PayPal.', 'updraftplus').' '. __('Subscriptions can be cancelled at any time.', 'updraftplus');?>
					</p>
					<p class="clear-left padding-top-20px">
						<em><?php _e("UpdraftPlus Vault is built on top of Amazon's world-leading data-centres, with redundant data storage to achieve 99.999999999% reliability.", 'updraftplus');?> <a target="_blank" href="<?php echo esc_attr($this->get_url('more_vault_info_landing')); ?>"><?php _e('Read more about it here.', 'updraftplus');?></a> <a target="_blank" href="<?php echo esc_attr($this->get_url('more_vault_info_faqs')); ?>"><?php _e('Read the FAQs here.', 'updraftplus');?></a></em>
					</p>
					<p>
						<a href="#" class="updraftvault_backtostart"><?php _e('Back...', 'updraftplus');?></a>
					</p>
				</div>

				<div id="updraftvault_settings_connect" style="display:none;" class="updraft-hidden">
					<p><?php _e('Enter your UpdraftPlus.Com email / password here to connect:', 'updraftplus');?></p>
					<p>
						<input id="updraftvault_email" class="udignorechange" type="text" placeholder="<?php esc_attr_e(__('E-mail', 'updraftplus'));?>">
						<input id="updraftvault_pass" class="udignorechange" type="password" placeholder="<?php esc_attr_e(__('Password', 'updraftplus'));?>">
						<button id="updraftvault_connect_go" class="button-primary"><?php _e('Connect', 'updraftplus');?></button>
					</p>
					<p class="padding-top-14px">
						<em><?php echo __("Don't know your email address, or forgotten your password?", 'updraftplus').' <a href="'.esc_attr($this->get_url('vault_forgotten_credentials_links')).'">'.__('Go here for help', 'updraftplus').'</a>';?></em>
					</p>
					<p class="padding-top-14px">
						<em><a href="#" class="updraftvault_backtostart"><?php _e('Back...', 'updraftplus');?></a></em>
					</p>

				</div>

				<div id="updraftvault_settings_connected"<?php if (!$connected) echo ' style="display:none;" class="updraft-hidden"';?>>
					<?php echo $this->connected_html($vault_settings); ?>
				</div>

			</td>
		</tr>

		<?php
		$this->vault_in_config_print = false;

	}

	private function connected_html($vault_settings = false) {
		if (!is_array($vault_settings)) {
			$vault_settings = UpdraftPlus_Options::get_updraft_option('updraft_updraftvault');
		}
		if (!is_array($vault_settings) || empty($vault_settings['token']) || empty($vault_settings['email'])) return '<p>'.__('You are <strong>not connected</strong> to UpdraftPlus Vault.', 'updraftplus').'</p>';

		$ret = '<p id="vault-is-connected">';
		
		$ret .= __('This site is <strong>connected</strong> to UpdraftPlus Vault.', 'updraftplus').' '.__("Well done - there's nothing more needed to set up.", 'updraftplus').'</p><p><strong>'.__('Vault owner', 'updraftplus').':</strong> '.htmlspecialchars($vault_settings['email']);

		$ret .= '<br><strong>'.__('Quota:', 'updraftplus').'</strong> ';
		if (!isset($vault_settings['quota']) || !is_numeric($vault_settings['quota']) || $vault_settings['quota'] < 0) {
			$ret .= __('Unknown', 'updraftplus');
		} else {
			$ret .= $this->s3_get_quota_info('text', $vault_settings['quota']);
		}
		$ret .= '</p>';
		
		$ret .= '<p><button id="updraftvault_disconnect" class="button-primary">'.__('Disconnect', 'updraftplus').'</button></p>';

		return $ret;
	}

	protected function s3_out_of_quota($total, $used, $needed) {
		global $updraftplus;
		$updraftplus->log("UpdraftPlus Vault Error: Quota exhausted (used=$used, total=$total, needed=$needed)");
		$updraftplus->log(sprintf(__('%s Error: you have insufficient storage quota available (%s) to upload this archive (%s).','updraftplus'), 'UpdraftPlus Vault', round(($total-$used)/1048576, 2).' MB', round($needed/1048576, 2).' MB').' '.__('You can get more quota here', 'updraftplus').': '.$this->get_url('get_more_quota'), 'error');
	}

	protected function s3_record_quota_info($quota_used, $quota) {

		$ret = __('Current use:', 'updraftplus').' '.round($quota_used / 1048576, 1).' / '.round($quota / 1048576, 1).' MB';
		$ret .= ' ('.sprintf('%.1f', 100*$quota_used / max($quota, 1)).' %)';

		$ret .= ' - <a href="'.esc_attr($this->get_url('get_more_quota')).'">'.__('Get more quota', 'updraftplus').'</a>';

		$ret_dashboard = $ret . ' - <a href="#" id="updraftvault_recountquota">'.__('Refresh current status', 'updraftplus').'</a>';

		set_transient('updraftvault_quota_text', $ret_dashboard, 86400*3);

	}

	public function s3_prune_retained_backups_finished() {
		$config = $this->get_config();
		$quota = $config['quota'];
		$quota_used = $this->s3_get_quota_info('numeric', $config['quota']);
		
		$ret = __('Current use:', 'updraftplus').' '.round($quota_used / 1048576, 1).' / '.round($quota / 1048576, 1).' MB';
		$ret .= ' ('.sprintf('%.1f', 100*$quota_used / max($quota, 1)).' %)';

		$ret_plain = $ret . ' - '.__('Get more quota', 'updraftplus').': '.$this->get_url('get_more_quota');

		$ret .= ' - <a href="'.esc_attr($this->get_url('get_more_quota')).'">'.__('Get more quota', 'updraftplus').'</a>';

		do_action('updraft_report_remotestorage_extrainfo', 'updraftvault', $ret, $ret_plain);
	}
	
	// Valid formats: text|numeric
	// In numeric, returns an integer or false for an error (never returns an error)
	protected function s3_get_quota_info($format = 'numeric', $quota = 0) {
		$ret = '';

		if ($quota > 0) {

			if (!empty($this->vault_in_config_print) && 'text' == $format) {
				$quota_via_transient = get_transient('updraftvault_quota_text');
				if (is_string($quota) && $quota) return $quota;
			}

			try {
				
				$config = $this->get_config();

				if (empty($config['quota_root'])) {
					// This next line is wrong: it lists the files *in this site's sub-folder*, rather than the whole Vault
					$current_files = $this->listfiles('');
				} else {
					$current_files = $this->listfiles_with_path($config['quota_root'], '', true);
				}

			} catch (Exception $e) {
				global $updraftplus;
				$updraftplus->log("Listfiles failed during quota calculation: ".$e->getMessage());
				$current_files = new WP_Error('listfiles_exception', $e->getMessage().' ('.get_class($e).')');
			}

			$ret .= __('Current use:', 'updraftplus').' ';

			$counted = false;
			if (is_wp_error($current_files)) {
				$ret .= __('Error:', 'updraftplus').' '.$current_files->get_error_message().' ('.$current_files->get_error_code().')';
			} elseif (!is_array($current_files)) {
				$ret .= __('Unknown', 'updraftplus');
			} else {
				foreach ($current_files as $file) {
					$counted += $file['size'];
				}
				$ret .= round($counted / 1048576, 1);
				$ret .= ' / '.round($quota / 1048576, 1).' MB';
				$ret .= ' ('.sprintf('%.1f', 100*$counted / $quota).' %)';
			}
		} else {
			$ret .= '0';
		}

		$ret .= ' - <a href="'.esc_attr($this->get_url('get_more_quota')).'">'.__('Get more quota', 'updraftplus').'</a> - <a href="#" id="updraftvault_recountquota">'.__('Refresh current status', 'updraftplus').'</a>';

		if ('text' == $format) set_transient('updraftvault_quota_text', $ret, 86400*3);

		return ('text' == $format) ? $ret : $counted;
	}

	public function credentials_test($posted_settings) {
		$this->credentials_test_engine($this->get_config(), $posted_settings);
	}
	
	public function ajax_vault_recountquota($echo_results = true) {
		// Force the opts to be refreshed
		$config = $this->get_config();

		if (empty($config['accesskey']) && !empty($config['error_message'])) {
			$results = array('html' => htmlspecialchars($config['error_message']), 'connected' => 0);
		} else {
			// Now read the opts
			$opts = $this->get_opts();
			$results = array('html' => $this->connected_html($opts), 'connected' => 1);
		}
		if ($echo_results) {
			echo json_encode($results);
		} else {
			return $results;
		}
	}

	// This method also gets called directly, so don't add code that assumes that it's definitely an AJAX situation
	public function ajax_vault_disconnect($echo_results = true) {
		$vault_settings = UpdraftPlus_Options::get_updraft_option('updraft_updraftvault');
		UpdraftPlus_Options::update_updraft_option('updraft_updraftvault', array());
		global $updraftplus;

		delete_transient('udvault_last_config');
		delete_transient('updraftvault_quota_text');

		$response = array('disconnected' => 1, 'html' => $this->connected_html());
		
		if ($echo_results) {
			$updraftplus->close_browser_connection(json_encode($response));
		}

		// If $_POST['reset_hash'] is set, then we were alerted by updraftplus.com - no need to notify back
		if (is_array($vault_settings) && isset($vault_settings['email']) && empty($_POST['reset_hash'])) {
		
			$post_body = array(
				'e' => (string)$vault_settings['email'],
				'sid' => $updraftplus->siteid(),
				'su' => base64_encode(home_url())
			);

			if (!empty($vault_settings['token'])) $post_body['token'] = (string)$vault_settings['token'];

			// Use SSL to prevent snooping
			wp_remote_post($this->vault_mothership.'/?udm_action=vault_disconnect', array(
				'timeout' => 20,
				'body' => $post_body,
			));
		}
		
		return $response;
		
	}

	// This is called from the UD admin object
	public function ajax_vault_connect($echo_results = true, $use_credentials = false) {
	
		if (empty($use_credentials)) $use_credentials = $_REQUEST;
	
		$connect = $this->vault_connect($use_credentials['email'], $use_credentials['pass']);
		if (true === $connect) {
			$response = array('connected' => true, 'html' => $this->connected_html(false));
		} else {
			$response = array(
				'e' => __('An unknown error occurred when trying to connect to UpdraftPlus.Com', 'updraftplus')
			);
			if (is_wp_error($connect)) {
				$response['e'] = $connect->get_error_message();
				$response['code'] = $connect->get_error_code();
				$response['data'] = serialize($connect->get_error_data());
			}
		}
		
		if ($echo_results) {
			echo json_encode($response);
		} else {
			return $response;
		}
	}

	// Returns either true (in which case the Vault token will be stored), or false|WP_Error
	private function vault_connect($email, $password) {

		// Username and password set up?
		if (empty($email) || empty($password)) return new WP_Error('blank_details', __('You need to supply both an email address and a password', 'updraftplus'));

		global $updraftplus;

		// Use SSL to prevent snooping
		$result = wp_remote_post($this->vault_mothership.'/?udm_action=vault_connect',
			array(
				'timeout' => 20,
				'body' => array(
					'e' => $email,
					'p' => base64_encode($password),
					'sid' => $updraftplus->siteid(),
					'su' => base64_encode(home_url())
				) 
			)
		);

		if (is_wp_error($result) || false === $result) return $result;

		$response = json_decode(wp_remote_retrieve_body($result), true);

		if (!is_array($response) || !isset($response['mothership']) || !isset($response['loggedin'])){

			if (preg_match('/has banned your IP address \(([\.:0-9a-f]+)\)/', $result['body'], $matches)){
				return new WP_Error('banned_ip', sprintf(__("UpdraftPlus.com has responded with 'Access Denied'.", 'updraftplus').'<br>'.__("It appears that your web server's IP Address (%s) is blocked.", 'updraftplus').' '.__('This most likely means that you share a webserver with a hacked website that has been used in previous attacks.', 'updraftplus').'<br> <a href="https://updraftplus.com/unblock-ip-address/" target="_blank">'.__('To remove the block, please go here.', 'updraftplus').'</a> ', $matches[1]));
			} else {
				return new WP_Error('unknown_response', sprintf(__('UpdraftPlus.Com returned a response which we could not understand (data: %s)', 'updraftplus'), wp_remote_retrieve_body($result)));
			}
		}

		switch ($response['loggedin']) {
			case 'connected':
				if (!empty($response['token'])) {
					// Store it
					$vault_settings = UpdraftPlus_Options::get_updraft_option('updraft_updraftvault');
					if (!is_array($vault_settings)) $vault_settings = array();
					$vault_settings['email'] = $email;
					$vault_settings['token'] = (string)$response['token'];
					$vault_settings['quota'] = -1;
					unset($vault_settings['last_config']);
					if (isset($response['quota'])) $vault_settings['quota'] = $response['quota'];
					UpdraftPlus_Options::update_updraft_option('updraft_updraftvault', $vault_settings);
					if (!empty($response['config']) && is_array($response['config'])) {
						if (!empty($response['config']['accesskey'])) {
							$this->vault_set_config($response['config']);
						} elseif (!empty($response['config']['result']) && ('token_unknown' == $response['config']['result'] || 'site_duplicated' == $response['config']['result'])) {
							return new WP_Error($response['config']['result'], $this->vault_translate_remote_message($response['config']['message'], $response['config']['result']));
						}
						// else... would also be an error condition, but not one known possible (and it will show a generic error anyway)
					}
				} elseif (isset($response['quota']) && !$response['quota']) {
					return new WP_Error('no_quota', __('You do not currently have any UpdraftPlus Vault quota', 'updraftplus'));
				} else {
					return new WP_Error('unknown_response', __('UpdraftPlus.Com returned a response, but we could not understand it', 'updraftplus'));
				}
				break;
			case 'authfailed':

				if (!empty($response['authproblem'])) {
					if ('invalidpassword' == $response['authproblem']) {
						$authfail_error = new WP_Error('authfailed', __('Your email address was valid, but your password was not recognised by UpdraftPlus.Com.', 'updraftplus').' <a href="https://updraftplus.com/my-account/lost-password/">'.__('If you have forgotten your password, then go here to change your password on updraftplus.com.', 'updraftplus').'</a>');
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
