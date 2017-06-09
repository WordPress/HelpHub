<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No access.');

/*
	- A container for RPC commands (core UpdraftCentral commands). Commands map exactly onto method names (and hence this class should not implement anything else, beyond the constructor, and private methods)
	- Return format is array('response' => (string - a code), 'data' => (mixed));
	
	RPC commands are not allowed to begin with an underscore. So, any private methods can be prefixed with an underscore.
	
*/

if (!class_exists('UpdraftCentral_Commands')) require_once('commands.php');

class UpdraftCentral_Core_Commands extends UpdraftCentral_Commands {

	public function get_login_url($redirect_to, $extra_info) {
		if (is_array($extra_info) && !empty($extra_info['user_id']) && is_numeric($extra_info['user_id'])) {
		
			$user_id = $extra_info['user_id'];
		
			if (false == ($login_key = $this->_get_autologin_key($user_id))) return $this->_generic_error_response('user_key_failure');
		
			// Default value
			$redirect_url = network_admin_url();
			if (is_array($redirect_to) && !empty($redirect_to['module'])) {
				switch ($redirect_to['module']) {
					case 'updraftplus';
						if ('initiate_restore' == $redirect_to['action'] && class_exists('UpdraftPlus_Options')) {
							$redirect_url = UpdraftPlus_Options::admin_page_url().'?page=updraftplus&udaction=initiate_restore&entities='.urlencode($redirect_to['data']['entities']).'&showdata='.urlencode($redirect_to['data']['showdata']).'&backup_timestamp='.(int)$redirect_to['data']['backup_timestamp'];
						} elseif ('download_file' == $redirect_to['action']) {
							$findex = empty($redirect_to['data']['findex']) ? 0 : (int)$redirect_to['data']['findex'];
							// e.g. ?udcentral_action=dl&action=updraftplus_spool_file&backup_timestamp=1455101696&findex=0&what=plugins
							$redirect_url = site_url().'?udcentral_action=spool_file&action=updraftplus_spool_file&findex='.$findex.'&what='.urlencode($redirect_to['data']['what']).'&backup_timestamp='.(int)$redirect_to['data']['backup_timestamp'];
						}
					break;
					case 'direct_url':
						$redirect_url = $redirect_to['url'];
					break;
				}
			}
			
			$login_key = apply_filters('updraftplus_remotecontrol_login_key', array(
				'key' => $login_key,
				'created' => time(),
				'redirect_url' => $redirect_url
			), $redirect_to, $extra_info);
			
			// Over-write any previous value - only one can be valid at a time)
			update_user_meta($user_id, 'updraftcentral_login_key', $login_key);
		
			return $this->_response(array(
				'login_url' => network_site_url('?udcentral_action=login&login_id='.$user_id.'&login_key='.$login_key['key'])
			));

		} else {
			return $this->_generic_error_response('user_unknown');
		}
	}
	
	public function phpinfo() {
	
		$phpinfo = $this->_get_phpinfo_array();
		
		if (!empty($phpinfo)){
			return $this->_response($phpinfo);
		}
		
		return $this->_generic_error_response('phpinfo_fail');

	}
	
	// https://secure.php.net/phpinfo
	private function _get_phpinfo_array() {
		ob_start();
		phpinfo(11);
		$phpinfo = array('phpinfo' => array());

		if (preg_match_all('#(?:<h2>(?:<a name=".*?">)?(.*?)(?:</a>)?</h2>)|(?:<tr(?: class=".*?")?><t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>(?:<t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>(?:<t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>)?)?</tr>)#s', ob_get_clean(), $matches, PREG_SET_ORDER)){
			foreach($matches as $match){
			if(strlen($match[1])){
				$phpinfo[$match[1]] = array();
			}elseif(isset($match[3])){
			$keys1 = array_keys($phpinfo);
			$phpinfo[end($keys1)][$match[2]] = isset($match[4]) ? array($match[3], $match[4]) : $match[3];
			} else {
				$keys1 = array_keys($phpinfo);
				$phpinfo[end($keys1)][] = $match[2];     
			
			}
		
			}
			return $phpinfo;
		}

		return false;
		
	}
		
	// This is intended to be short-lived. Hence, there's no intention other than that it is random and only used once - only the most recent one is valid.
	public function _get_autologin_key($user_id) {
		$secure_auth_key = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : hash('sha256', DB_PASSWORD).'_'.rand(0, 999999999);
		if (!defined('SECURE_AUTH_KEY')) return false;
		$hash_it = $user_id.'_'.microtime(true).'_'.rand(0, 999999999).'_'.$secure_auth_key;
		$hash = hash('sha256', $hash_it);
		return $hash;
	}
	
	public function site_info() {

		global $wpdb;
		@include(ABSPATH.WPINC.'/version.php');

		$ud_version = is_a($this->ud, 'UpdraftPlus') ? $this->ud->version : 'none';
		
		return $this->_response(array(
			'versions' => array(
				'ud' => $ud_version,
				'php' => PHP_VERSION,
				'wp' => $wp_version,
				'mysql' => $wpdb->db_version(),
				'udrpc_php' => $this->rc->udrpc_version,
			),
			'bloginfo' => array(
				'url' => network_site_url(),
				'name' => get_bloginfo('name'),
			)
		));
	}
	
}
