<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No access.');

// This files is included during plugins_loaded

// Load the listener class that we rely on to pick up messages
if (!class_exists('UpdraftPlus_UpdraftCentral_Listener')) require_once('listener.php');

class UpdraftPlus_UpdraftCentral_Main {

	public function __construct() {

		// Add the section to the 'advanced tools' page
		add_action('updraftplus_debugtools_dashboard', array($this, 'debugtools_dashboard'), 20);
		add_action('udrpc_log', array($this, 'udrpc_log'), 10, 3);
		
		add_action('wp_ajax_updraftcentral_receivepublickey', array($this, 'wp_ajax_updraftcentral_receivepublickey')); 
		add_action('wp_ajax_nopriv_updraftcentral_receivepublickey', array($this, 'wp_ajax_updraftcentral_receivepublickey')); 
	
		$command_classes = apply_filters('updraftplus_remotecontrol_command_classes', array(
			'core' => 'UpdraftCentral_Core_Commands',
			'updraftplus' => 'UpdraftPlus_RemoteControl_Commands',
			'updates' => 'UpdraftCentral_Updates_Commands',
		));
	
		// Remote control keys
		// These are different from the remote send keys, which are set up in the Migrator add-on
		$our_keys = UpdraftPlus_Options::get_updraft_option('updraft_central_localkeys');
		if (is_array($our_keys) && !empty($our_keys)) {	
			$remote_control = new UpdraftPlus_UpdraftCentral_Listener($our_keys, $command_classes);
		}

	}
	
	public function wp_ajax_updraftcentral_receivepublickey() {
	
		// The actual nonce check is done in the method below
		if (empty($_GET['_wpnonce']) || empty($_GET['public_key']) || !isset($_GET['updraft_key_index'])) die;
		
		$result = $this->receive_public_key();
		if (!is_array($result) || empty($result['responsetype'])) die;
		
		echo '<html><head><title>UpdraftCentral</title></head><body><h1>'.__('UpdraftCentral Connection', 'updraftplus').'</h1><h2>'.htmlspecialchars(network_site_url()).'</h2><p>';
		
		if ('ok' == $result['responsetype']) {
			echo __('An UpdraftCentral connection has been made successfully.', 'updraftplus');
		} else {
			echo '<strong>'.__('A new UpdraftCentral connection has not been made.', 'updraftplus').'</strong><br>';
			switch($result['code']) {
				case 'unknown_key':
				echo __('The key referred to was unknown.', 'updraftplus');
				break;
				case 'not_logged_in';
				
// 				$the_url = admin_url('admin-ajax.php').'?action=updraftcentral_receivepublickey&_wpnonce='.urlencode($_GET['_wpnonce']).'&updraft_key_index='.urlencode($_GET['updraft_key_index']).'&public_key='.urlencode($_GET['public_key']);
				
				echo __('You are not logged into this WordPress site in your web browser.', 'updraftplus').' '.__('You must visit this URL in the same browser and login session as you created the key in.', 'updraftplus');
				
				break;
				case 'nonce_failure';
				
				echo 'Security check. ';
				
				_e('You must visit this link in the same browser and login session as you created the key in.', 'updraftplus');
				
				break;
				case 'already_have';
				echo __('This connection appears to already have been made.', 'updraftplus');
				break;
				default:
				echo htmlspecialchars(print_r($result, true));
				break;
			}
		}
		
		echo '</p><p><a href="#" onclick="window.close();">'.__('Close...', 'updraftplus').'</a></p>';
		die;
	}
	
	private function receive_public_key() {
		
		if (!is_user_logged_in()) {
			return array('responsetype' => 'error', 'code' => 'not_logged_in');
		}
		
		if (!wp_verify_nonce($_GET['_wpnonce'], 'updraftcentral_receivepublickey')) return array('responsetype' => 'error', 'code' => 'nonce_failure');
		
		$updraft_key_index = $_GET['updraft_key_index'];

		$our_keys = UpdraftPlus_Options::get_updraft_option('updraft_central_localkeys');
		if (!is_array($our_keys)) $our_keys = array();
		
		if (!isset($our_keys[$updraft_key_index])) {
			return array('responsetype' => 'error', 'code' => 'unknown_key');
		}

		if (!empty($our_keys[$updraft_key_index]['publickey_remote'])) {
			return array('responsetype' => 'error', 'code' => 'already_have');
		}
		
		$our_keys[$updraft_key_index]['publickey_remote'] = base64_decode($_GET['public_key']);
		UpdraftPlus_Options::update_updraft_option('updraft_central_localkeys', $our_keys);
		
		return array('responsetype' => 'ok', 'code' => 'ok');
	}
	
	// Action parameters, from udrpc: $message, $level, $this->key_name_indicator, $this->debug, $this
	public function udrpc_log($message, $level, $key_name_indicator) {
		$udrpc_log = get_site_option('updraftcentral_client_log');
		if (!is_array($udrpc_log)) $udrpc_log = array();
		
		$new_item = array(
			'time' => time(),
			'level' => $level,
			'message' => $message,
			'key_name_indicator' => $key_name_indicator
		);
		
		if (!empty($_SERVER['REMOTE_ADDR'])) {
			$new_item['remote_ip'] = $_SERVER['REMOTE_ADDR'];
		}
		if (!empty($_SERVER['HTTP_USER_AGENT'])) {
			$new_item['http_user_agent'] = $_SERVER['HTTP_USER_AGENT'];
		}
		if (!empty($_SERVER['HTTP_X_SECONDARY_USER_AGENT'])) {
			$new_item['http_secondary_user_agent'] = $_SERVER['HTTP_X_SECONDARY_USER_AGENT'];
		}
		
		$udrpc_log[] = $new_item;
		
		if (count($udrpc_log) > 50) array_shift($udrpc_log);
		
		update_site_option('updraftcentral_client_log', $udrpc_log);
	}
	
	public function delete_key($key_id) {
		$our_keys = UpdraftPlus_Options::get_updraft_option('updraft_central_localkeys');
		if (!is_array($our_keys)) $our_keys = array();
		if (isset($our_keys[$key_id])) {
			unset($our_keys[$key_id]);
			UpdraftPlus_Options::update_updraft_option('updraft_central_localkeys', $our_keys);
		}
		return array('deleted' => 1, 'keys_table' => $this->get_keys_table());
	}
	
	public function get_log($params) {
	
		$udrpc_log = get_site_option('updraftcentral_client_log');
		if (!is_array($udrpc_log)) $udrpc_log = array();
		
		$log_contents = '';
		
		// Events are appended to the array in the order they happen. So, reversing the order gets them into most-recent-first order.
		rsort($udrpc_log);
		
		if (empty($udrpc_log)) {
			$log_contents = '<em>'.__('(Nothing yet logged)', 'updraftplus').'</em>';
		}
		
		foreach ($udrpc_log as $m) {
		
			// Skip invalid data
			if (!isset($m['time'])) continue;

			$time = gmdate('Y-m-d H:i:s O', $m['time']);
			// $level is not used yet. We could put the message in different colours for different levels, if/when it becomes used.
			
			$key_name_indicator = empty($m['key_name_indicator']) ? '' : $m['key_name_indicator'];
			
			$log_contents .= '<span title="'.esc_attr(print_r($m, true)).'">'."$time ";
			
			if (!empty($m['remote_ip'])) $log_contents .= '['.htmlspecialchars($m['remote_ip']).'] ';
			
			$log_contents .= "[".htmlspecialchars($key_name_indicator)."] ".htmlspecialchars($m['message'])."</span>\n";
		}
		
		return array('log_contents' => $log_contents);
	
	}
	
	public function create_key($params) {
		// Use the site URL - this means that if the site URL changes, communication ends; which is the case anyway
		$user = wp_get_current_user();
		
		$where_send = empty($params['where_send']) ? '' : (string)$params['where_send'];
		
		if ($where_send != '__updraftpluscom') {
			$purl = parse_url($where_send);
			if (empty($purl) || !array($purl) || empty($purl['scheme']) || empty($purl['host'])) return array('error' => __('An invalid URL was entered', 'updraftplus'));
		}

		$flags = defined('ENT_HTML5') ? ENT_QUOTES | ENT_HTML5 : ENT_QUOTES;
		
		$extra_info = array(
			'user_id' => $user->ID,
			'user_login' => $user->user_login,
			'ms_id' => get_current_blog_id(),
			'site_title' => html_entity_decode(get_bloginfo('name'), $flags),
		);

		if ($where_send) {
			$extra_info['mothership'] = $where_send;
			if (!empty($params['mothership_firewalled'])) {
				$extra_info['mothership_firewalled'] = true;
			}
		}

		if (!empty($params['key_description'])) {
			$extra_info['name'] = (string)$params['key_description'];
		}

		$key_size = (empty($params['key_size']) || !is_numeric($params['key_size']) || $params['key_size'] < 512) ? 2048 : (int)$params['key_size'];
		
		$extra_info['key_size'] = $key_size;
		
		$created = $this->create_remote_control_key(false, $extra_info, $where_send);

		if (is_array($created)) {
			$created['keys_table'] = $this->get_keys_table();
		}
		
		return $created;
		die;
	}

	private function indicator_name_from_index($index) {
		return $index.'.central.updraftplus.com';
	}
	
	private function create_remote_control_key($index = false, $extra_info = array(), $post_it = false) {

		global $updraftplus;
	
		$our_keys = UpdraftPlus_Options::get_updraft_option('updraft_central_localkeys');
		if (!is_array($our_keys)) $our_keys = array();
		
		if (false === $index) {
			if (empty($our_keys)) {
				$index = 0;
			} else {
				$index = max(array_keys($our_keys))+1;
			}
		}
		
		$name_hash = $index;
		
		if (isset($our_keys[$name_hash])) {
			unset($our_keys[$name_hash]);
		}

		$indicator_name = $this->indicator_name_from_index($name_hash);

		$ud_rpc = $updraftplus->get_udrpc($indicator_name);

		$send_to_updraftpluscom = false;
		if ('__updraftpluscom' == $post_it) {
			$send_to_updraftpluscom = true;
			$post_it = defined('UPDRAFTPLUS_OVERRIDE_UDCOM_DESTINATION') ? UPDRAFTPLUS_OVERRIDE_UDCOM_DESTINATION : 'https://updraftplus.com/?updraftcentral_action=receive_key';
			$post_it_description = 'UpdraftPlus.Com';
		} else {
			$post_it_description = $post_it;
		}
		
		// Normally, key generation takes seconds, even on a slow machine. However, some Windows machines appear to have a setup in which it takes a minute or more. And then, if you're on a double-localhost setup on slow hardware - even worse. It doesn't hurt to just raise the maximum execution time.
		
		@set_time_limit(UPDRAFTPLUS_SET_TIME_LIMIT);
		
		$key_size = (empty($extra_info['key_size']) || !is_numeric($extra_info['key_size']) || $extra_info['key_size'] < 512) ? 2048 : (int)$extra_info['key_size'];
// 		unset($extra_info['key_size']);
		
		if (is_object($ud_rpc) && $ud_rpc->generate_new_keypair($key_size)) {
		
			if ($post_it && empty($extra_info['mothership_firewalled'])) {
			
				$p_url = parse_url($post_it);
				if (is_array($p_url) && !empty($p_url['user'])) {
					$http_username = $p_url['user'];
					$http_password = empty($p_url['pass']) ? '' : $p_url['pass'];
					$post_it = $p_url['scheme'].'://'.$p_url['host'];
					if (!empty($p_url['port'])) $post_it .= ':'.$p_url['port'];
					$post_it .= $p_url['path'];
					if (!empty($p_url['query'])) $post_it .= '?'.$p_url['query'];
				}
				
				$post_options = array(
					'timeout' => 90,
					'body' => array(
						'updraftcentral_action' => 'receive_key',
						'key' => $ud_rpc->get_key_remote()
					)
				);
				
				if (!empty($http_username)) {
					$post_options['headers'] = array(
						'Authorization' => 'Basic '.base64_encode($http_username.':'.$http_password)
					);
				}
			
				// This option allows the key to be sent to the other side via a known-secure channel (e.g. http over SSL), rather than potentially allowing it to travel over an unencrypted channel (e.g. http back to the user's browser). As such, if specified, it is compulsory for it to work.
				$sent_key = wp_remote_post(
					$post_it,
					$post_options
				);
				
				if (is_wp_error($sent_key) || empty($sent_key)) {
					$err_msg = sprintf(__('A key was created, but the attempt to register it with %s was unsuccessful - please try again later.', 'updraftplus'), (string)$post_it_description);
					if (is_wp_error($sent_key)) $err_msg .= ' '.$sent_key->get_error_message().' ('.$sent_key->get_error_code().')';
					return array(
						'r' => $err_msg
					);
				}
				
				$response = json_decode(wp_remote_retrieve_body($sent_key), true);

				if (!is_array($response) || !isset($response['key_id']) || !isset($response['key_public'])) {
					return array(
						'r' => sprintf(__('A key was created, but the attempt to register it with %s was unsuccessful - please try again later.', 'updraftplus'), (string)$post_it_description),
						'raw' => wp_remote_retrieve_body($sent_key)
					);
				}
				
				$key_hash = hash('sha256', $ud_rpc->get_key_remote());

				$local_bundle = $ud_rpc->get_portable_bundle('base64_with_count', $extra_info, array('key' => array('key_hash' => $key_hash, 'key_id' => $response['key_id'])));

			} elseif ($post_it) {
				// Don't send; instead, include in the bundle info that the mothership is firewalled; this will then tell the mothership to try the reverse connection instead

				if (is_array($extra_info)) {
					$extra_info['mothership_firewalled_callback_url'] = wp_nonce_url(admin_url('admin-ajax.php'), 'updraftcentral_receivepublickey');
					$extra_info['updraft_key_index'] = $index;
				}

				
				$local_bundle = $ud_rpc->get_portable_bundle('base64_with_count', $extra_info, array('key' => $ud_rpc->get_key_remote()));
			}
		

			if (isset($extra_info['name'])) {
				$name = (string)$extra_info['name'];
				unset($extra_info['name']);
			} else {
				$name = 'UpdraftCentral Remote Control';
			}
		
			$our_keys[$name_hash] = array(
				'name' => $name,
				'key' => $ud_rpc->get_key_local(),
				'extra_info' => $extra_info,
				'created' => time(),
			);
			// Store the other side's public key
			if (!empty($response) && is_array($response) && !empty($response['key_public'])) {
				$our_keys[$name_hash]['publickey_remote'] = $response['key_public'];
			}
			UpdraftPlus_Options::update_updraft_option('updraft_central_localkeys', $our_keys);

			return array(
				'bundle' => $local_bundle,
				'r' => __('Key created successfully.', 'updraftplus').' '.__('You must copy and paste this key now - it cannot be shown again.', 'updraftplus'),
// 				'selector' => $this->get_remotesites_selector(array()),
// 				'ourkeys' => $this->list_our_keys($our_keys),
			);
		}

		return false;

	}
	
	public function get_keys_table() {
	
		$ret = '';
	
		$ret .= '<table><thead><tr><th style="text-align:left;">'.__('Key description', 'updraftplus').'</th><th style="text-align:left;">'.__('Details', 'updraftplus').'</th></thead></tr><tbody>';
		
		$our_keys = UpdraftPlus_Options::get_updraft_option('updraft_central_localkeys');
		if (!is_array($our_keys)) $our_keys = array();

		if (empty($our_keys)) {
			$ret .= '<tr><td colspan="2"><em>'.__('No keys have yet been created.', 'updraftplus').'</em></td></tr>';
		}
		
		foreach ($our_keys as $i => $key) {
		
			if (empty($key['extra_info'])) continue;
			
			$user_id = $key['extra_info']['user_id'];
			
			if (!empty($key['extra_info']['mothership'])) {
			
				$mothership_url = $key['extra_info']['mothership'];
				
				if ('__updraftpluscom' == $mothership_url) {
					$reconstructed_url = 'https://updraftplus.com';
				} else {
					$purl = parse_url($mothership_url);
					$path = empty($purl['path']) ? '' : $purl['path'];
					
					$reconstructed_url = $purl['scheme'].'://'.$purl['host'].(!empty($purl['port']) ? ':'.$purl['port'] : '').$path;
				}
				
			} else {
				$reconstructed_url = __('Unknown', 'updraftplus');
			}
		
			$name = $key['name'];
			
			$user = get_user_by('id', $user_id);
			
			$user_display = is_a($user, 'WP_User') ? $user->user_login.' ('.$user->user_email.')' : __('Unknown', 'updraftplus');
			
			$ret .= '<tr class="updraft_debugrow"><td style="vertical-align:top;">'.htmlspecialchars($name).' ('.htmlspecialchars($i).')</td><td>'.__("Access this site as user:", 'updraftplus')." ".htmlspecialchars($user_display)."<br>".__('Public key was sent to:', 'updraftplus').' '.htmlspecialchars($reconstructed_url).'<br>';
			
			if (!empty($key['created'])) {
				$ret .= __('Created:', 'updraftplus').' '.date_i18n(get_option('date_format').' '.get_option('time_format'), $key['created']).'.';
				if (!empty($key['extra_info']['key_size'])) {
					$ret .= ' '.sprintf(__('Key size: %d bits', 'updraftplus'), $key['extra_info']['key_size']).'.';
				}
				$ret .= '<br>';
			}
			
			$ret .= '<a href="#" data-key_id="'.esc_attr($i).'" class="updraftcentral_key_delete">'.__('Delete...', 'updraftplus').'</a></td></tr>';
			
		}
		
		$ret .= '</tbody></table>';
		
		$ret .= '<h4>'.__('Create new key', 'updraftplus').'</h4><table style="width: 760px; table-layout:fixed;"><thead><tbody>';
		
		$ret .= '<tr class="updraft_debugrow"><th style="width: 20%;">'.__('Description', 'updraftplus').':</th><td style="width:80%;"><input id="updraftcentral_keycreate_description" type="text" size="20" placeholder="'.__('Enter any description', 'updraftplus').'" value=""></td></tr>';
		
		$ret .= '<tr class="updraft_debugrow"><th style="">'.__('Dashboard at', 'updraftplus').':</th><td style="width:80%;"><input checked="checked" type="radio" name="updraftcentral_mothership" id="updraftcentral_mothership_updraftpluscom"> <label for="updraftcentral_mothership_updraftpluscom">'.'UpdraftPlus.Com ('.__('i.e. you have an account there', 'updraftplus').')'.'</label><br><input type="radio" name="updraftcentral_mothership" id="updraftcentral_mothership_other"> <label for="updraftcentral_mothership_other">'.__('Other (please specify - i.e. the site where you have installed an UpdraftCentral dashboard)', 'updraftplus').'</label>:<br><input disabled="disabled" id="updraftcentral_keycreate_mothership" type="text" size="40" placeholder="'.__('URL of mothership', 'updraftplus').'" value=""><br><div id="updraftcentral_keycreate_mothership_firewalled_container"><input id="updraftcentral_keycreate_mothership_firewalled" type="checkbox"><label for="updraftcentral_keycreate_mothership_firewalled">'.__('Use the alternative method for making a connection with the dashboard.', 'updraftplus').' <a href="#" id="updraftcentral_keycreate_altmethod_moreinfo_get">'.__('More information...', 'updraftplus').'</a><span id="updraftcentral_keycreate_altmethod_moreinfo" style="display:none;">'.__('This is useful if the dashboard webserver cannot be contacted with incoming traffic by this website (for example, this is the case if this website is hosted on the public Internet, but the UpdraftCentral dashboard is on localhost, or on an Intranet, or if this website has an outgoing firewall), or if the dashboard website does not have a SSL certificate.').'</span></label></div></td></tr>';
		
		$ret .= '<tr class="updraft_debugrow"><th style=""></th><td style="width:80%;">'.__('Encryption key size:', 'updraftplus').' <select style="" id="updraftcentral_keycreate_keysize">
				<option value="512">'.sprintf(__('%s bits', 'updraftplus').' - '.__('easy to break, fastest', 'updraftplus'), '512').'</option>
				<option value="1024">'.sprintf(__('%s bits', 'updraftplus').' - '.__('faster (possibility for slow PHP installs)', 'updraftplus'), '1024').'</option>
				<option value="2048" selected="selected">'.sprintf(__('%s bytes', 'updraftplus').' - '.__('recommended', 'updraftplus'), '2048').'</option>
				<option value="4096">'.sprintf(__('%s bits', 'updraftplus').' - '.__('slower, strongest', 'updraftplus'), '4096').'</option>
			</select></td></tr>';
		
		$ret .= '<tr class="updraft_debugrow"><th style=""></th><td style="width:80%;"><button type="button" class="button button-primary" id="updraftcentral_keycreate_go">'.__('Create', 'updraftplus').'</button></td></tr>';
		
		$ret .= '</tbody></table>';
		
		$ret .= '<div id="updraftcentral_view_log_container"><h4>'.__('View recent UpdraftCentral log events', 'updraftplus').' - <a href="#" id="updraftcentral_view_log">'.__('fetch...', 'updraftplus').'</a></h4>';

		$ret .= '<pre id="updraftcentral_view_log_contents" style="padding: 0 4px;"></pre>';

		$ret .= '</div>';

		return $ret;
	}
	
	public function debugtools_dashboard() {
	
		echo '<h3>'.__('UpdraftCentral (Remote Control)', 'updraftplus').'</h3>';
		
		echo '<div id="updraftcentral_keys"><div id="updraftcentral_keys_content">'.$this->get_keys_table().'</div></div>';
		

	}
	
}

global $updraftplus_updraftcentral_main;
$updraftplus_updraftcentral_main = new UpdraftPlus_UpdraftCentral_Main();
