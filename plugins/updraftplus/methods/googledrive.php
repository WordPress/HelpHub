<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed.');

# Converted to job_options: yes
# Converted to array options: yes

# Migrate options to new-style storage - Apr 2014
# clientid, secret, remotepath
if (!is_array(UpdraftPlus_Options::get_updraft_option('updraft_googledrive')) && '' != UpdraftPlus_Options::get_updraft_option('updraft_googledrive_clientid', '')) {
	$opts = array(
		'clientid' => UpdraftPlus_Options::get_updraft_option('updraft_googledrive_clientid'),
		'secret' => UpdraftPlus_Options::get_updraft_option('updraft_googledrive_secret'),
		'parentid' => UpdraftPlus_Options::get_updraft_option('updraft_googledrive_remotepath'),
		'token' => UpdraftPlus_Options::get_updraft_option('updraft_googledrive_token')
	);
	$tmp = UpdraftPlus_Options::get_updraft_option('updraftplus_tmp_access_token');
	if (!empty($tmp)) $opts['tmp_access_token'] = $tmp;
	UpdraftPlus_Options::update_updraft_option('updraft_googledrive', $opts);
	UpdraftPlus_Options::delete_updraft_option('updraft_googledrive_clientid');
	UpdraftPlus_Options::delete_updraft_option('updraft_googledrive_secret');
	UpdraftPlus_Options::delete_updraft_option('updraft_googledrive_remotepath');
	UpdraftPlus_Options::delete_updraft_option('updraft_googledrive_token');
	UpdraftPlus_Options::delete_updraft_option('updraftplus_tmp_access_token');
}

class UpdraftPlus_BackupModule_googledrive {

	private $service;
	private $client;
	private $ids_from_paths;

	public function action_auth() {
		if (isset($_GET['state'])) {
			if ('success' == $_GET['state']) add_action('all_admin_notices', array($this, 'show_authed_admin_success'));
			elseif ('token' == $_GET['state']) $this->gdrive_auth_token();
			elseif ('revoke' == $_GET['state']) $this->gdrive_auth_revoke();
		} elseif (isset($_GET['updraftplus_googleauth'])) {
			$this->gdrive_auth_request();
		}
	}

	public function get_credentials() {
		return array('updraft_googledrive');
	}

	public static function get_opts() {
		# parentid is deprecated since April 2014; it should not be in the default options (its presence is used to detect an upgraded-from-previous-SDK situation). For the same reason, 'folder' is also unset; which enables us to know whether new-style settings have ever been set.
		global $updraftplus;
		$opts = $updraftplus->get_job_option('updraft_googledrive');
		if (!is_array($opts)) $opts = array('clientid' => '', 'secret' => '');
		return $opts;
	}

	private function root_id() {
		if (empty($this->root_id)) $this->root_id = $this->service->about->get()->getRootFolderId();
		return $this->root_id;
	}

	public function id_from_path($path, $retry = true) {
		global $updraftplus;

		try {
			while ('/' == substr($path, 0, 1)) { $path = substr($path, 1); }

			$cache_key = (empty($path)) ? '/' : $path;
			if (!empty($this->ids_from_paths) && isset($this->ids_from_paths[$cache_key])) return $this->ids_from_paths[$cache_key];

			$current_parent = $this->root_id();
			$current_path = '/';

			if (!empty($path)) {
				foreach (explode('/', $path) as $element) {
					$found = false;
					$sub_items = $this->get_subitems($current_parent, 'dir', $element);

					foreach ($sub_items as $item) {
						try {
							if ($item->getTitle() == $element) {
								$found = true;
								$current_path .= $element.'/';
								$current_parent = $item->getId();
								break;
							}
						} catch (Exception $e) {
							$updraftplus->log("Google Drive id_from_path: exception: ".$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
						}
					}

					if (!$found) {
						$ref = new Google_Service_Drive_ParentReference;
						$ref->setId($current_parent);
						$dir = new Google_Service_Drive_DriveFile();
						$dir->setMimeType('application/vnd.google-apps.folder');
						$dir->setParents(array($ref));
						$dir->setTitle($element);
						$updraftplus->log("Google Drive: creating path: ".$current_path.$element);
						$dir = $this->service->files->insert(
							$dir,
							array('mimeType' => 'application/vnd.google-apps.folder')
						);
						$current_path .= $element.'/';
						$current_parent = $dir->getId();
					}
				}
			}

			if (empty($this->ids_from_paths)) $this->ids_from_paths = array();
			$this->ids_from_paths[$cache_key] = $current_parent;

			return $current_parent;

		} catch (Exception $e) {
			$msg = $e->getMessage();
			$updraftplus->log("Google Drive id_from_path failure: exception (".get_class($e)."): ".$msg.' (line: '.$e->getLine().', file: '.$e->getFile().')');
			if (is_a($e, 'Google_Service_Exception') && false !== strpos($msg, 'Invalid json in service response') && function_exists('mb_strpos')) {
				// Aug 2015: saw a case where the gzip-encoding was not removed from the result
				// https://stackoverflow.com/questions/10975775/how-to-determine-if-a-string-was-compressed
				$is_gzip = false !== mb_strpos($msg , "\x1f" . "\x8b" . "\x08");
				if ($is_gzip) $updraftplus->log("Error: Response appears to be gzip-encoded still; something is broken in the client HTTP stack, and you should define UPDRAFTPLUS_GOOGLEDRIVE_DISABLEGZIP as true in your wp-config.php to overcome this.");
			}
			# One retry
			return ($retry) ? $this->id_from_path($path, false) : false;
		}
	}

	private function get_parent_id($opts) {
		$filtered = apply_filters('updraftplus_googledrive_parent_id', false, $opts, $this->service, $this);
		if (!empty($filtered)) return $filtered;
		if (isset($opts['parentid'])) {
			if (empty($opts['parentid'])) {
				return $this->root_id();
			} else {
				$parent = (is_array($opts['parentid'])) ? $opts['parentid']['id'] : $opts['parentid'];
			}
		} else {
			$parent = $this->id_from_path('UpdraftPlus');
		}
		return (empty($parent)) ? $this->root_id() : $parent;
	}

	public function listfiles($match = 'backup_') {

		$opts = $this->get_opts();

		if (empty($opts['secret']) || empty($opts['clientid']) || empty($opts['clientid'])) return new WP_Error('no_settings', sprintf(__('No %s settings were found', 'updraftplus'), __('Google Drive','updraftplus')));

		$service = $this->bootstrap();
		if (is_wp_error($service) || false == $service) return $service;

		global $updraftplus;

		try {
			$parent_id = $this->get_parent_id($opts);
			$sub_items = $this->get_subitems($parent_id, 'file');
		} catch (Exception $e) {
			return new WP_Error(__('Google Drive list files: failed to access parent folder', 'updraftplus').":  ".$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
		}

		$results = array();

		foreach ($sub_items as $item) {
			$title = "(unknown)";
			try {
				$title = $item->getTitle();
				if (0 === strpos($title, $match)) {
					$results[] = array('name' => $title, 'size' => $item->getFileSize());
				}
			} catch (Exception $e) {
				$updraftplus->log("Google Drive delete: exception: ".$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
				$ret = false;
				continue;
			}
		}

		return $results;
	}

	// Get a Google account access token using the refresh token
	private function access_token($refresh_token, $client_id, $client_secret) {

		global $updraftplus;
		$updraftplus->log("Google Drive: requesting access token: client_id=$client_id");

		$query_body = array(
			'refresh_token' => $refresh_token,
			'client_id' => $client_id,
			'client_secret' => $client_secret,
			'grant_type' => 'refresh_token'
		);

		$result = wp_remote_post('https://accounts.google.com/o/oauth2/token',
			array(
				'timeout' => '20',
				'method' => 'POST',
				'body' => $query_body
			)
		);

		if (is_wp_error($result)) {
			$updraftplus->log("Google Drive error when requesting access token");
			foreach ($result->get_error_messages() as $msg) $updraftplus->log("Error message: $msg");
			return false;
		} else {
			$json_values = json_decode(wp_remote_retrieve_body($result), true);
			if ( isset( $json_values['access_token'] ) ) {
				$updraftplus->log("Google Drive: successfully obtained access token");
				return $json_values['access_token'];
			} else {
				$updraftplus->log("Google Drive error when requesting access token: response does not contain access_token. Response: ".(is_string($result['body']) ? str_replace("\n", '', $result['body']) : json_encode($result['body'])));
				return false;
			}
		}
	}

	private function redirect_uri() {
		return  UpdraftPlus_Options::admin_page_url().'?action=updraftmethod-googledrive-auth';
	}

	// Acquire single-use authorization code from Google OAuth 2.0
	public function gdrive_auth_request() {
		$opts = $this->get_opts();
		// First, revoke any existing token, since Google doesn't appear to like issuing new ones
		if (!empty($opts['token'])) $this->gdrive_auth_revoke();
		// We use 'force' here for the approval_prompt, not 'auto', as that deals better with messy situations where the user authenticated, then changed settings

		# We require access to all Google Drive files (not just ones created by this app - scope https://www.googleapis.com/auth/drive.file) - because we need to be able to re-scan storage for backups uploaded by other installs
		$params = array(
			'response_type' => 'code',
			'client_id' => $opts['clientid'],
			'redirect_uri' => $this->redirect_uri(),
			'scope' => 'https://www.googleapis.com/auth/drive',
			'state' => 'token',
			'access_type' => 'offline',
			'approval_prompt' => 'force'
		);
		if(headers_sent()) {
			global $updraftplus;
			$updraftplus->log(sprintf(__('The %s authentication could not go ahead, because something else on your site is breaking it. Try disabling your other plugins and switching to a default theme. (Specifically, you are looking for the component that sends output (most likely PHP warnings/errors) before the page begins. Turning off any debugging settings may also help).', ''), 'Google Drive'), 'error');
		} else {
			header('Location: https://accounts.google.com/o/oauth2/auth?'.http_build_query($params, null, '&'));
		}
	}

	// Revoke a Google account refresh token
	// Returns the parameter fed in, so can be used as a WordPress options filter
	// Can be called statically from UpdraftPlus::googledrive_clientid_checkchange()
	public static function gdrive_auth_revoke($unsetopt = true) {
		$opts = self::get_opts();
		$ignore = wp_remote_get('https://accounts.google.com/o/oauth2/revoke?token='.$opts['token']);
		if ($unsetopt) {
			$opts['token'] = '';
			unset($opts['ownername']);
			UpdraftPlus_Options::update_updraft_option('updraft_googledrive', $opts);
		}
	}

	// Get a Google account refresh token using the code received from gdrive_auth_request
	public function gdrive_auth_token() {
		$opts = $this->get_opts();
		if(isset($_GET['code'])) {
			$post_vars = array(
				'code' => $_GET['code'],
				'client_id' => $opts['clientid'],
				'client_secret' => $opts['secret'],
				'redirect_uri' => UpdraftPlus_Options::admin_page_url().'?action=updraftmethod-googledrive-auth',
				'grant_type' => 'authorization_code'
			);

			$result = wp_remote_post('https://accounts.google.com/o/oauth2/token', array('timeout' => 25, 'method' => 'POST', 'body' => $post_vars) );

			if (is_wp_error($result)) {
				$add_to_url = "Bad response when contacting Google: ";
				foreach ( $result->get_error_messages() as $message ) {
					global $updraftplus;
					$updraftplus->log("Google Drive authentication error: ".$message);
					$add_to_url .= $message.". ";
				}
				header('Location: '.UpdraftPlus_Options::admin_page_url().'?page=updraftplus&error='.urlencode($add_to_url));
			} else {
				$json_values = json_decode(wp_remote_retrieve_body($result), true);
				if (isset($json_values['refresh_token'])) {

					 // Save token
					$opts['token'] = $json_values['refresh_token'];
					UpdraftPlus_Options::update_updraft_option('updraft_googledrive', $opts);

					if (isset($json_values['access_token'])) {
						$opts['tmp_access_token'] = $json_values['access_token'];
						UpdraftPlus_Options::update_updraft_option('updraft_googledrive', $opts);
						// We do this to clear the GET parameters, otherwise WordPress sticks them in the _wp_referer in the form and brings them back, leading to confusion + errors
						header('Location: '.UpdraftPlus_Options::admin_page_url().'?action=updraftmethod-googledrive-auth&page=updraftplus&state=success');
					}

				} else {

					$msg = __('No refresh token was received from Google. This often means that you entered your client secret wrongly, or that you have not yet re-authenticated (below) since correcting it. Re-check it, then follow the link to authenticate again. Finally, if that does not work, then use expert mode to wipe all your settings, create a new Google client ID/secret, and start again.', 'updraftplus');

					if (isset($json_values['error'])) $msg .= ' '.sprintf(__('Error: %s', 'updraftplus'), $json_values['error']);

					header('Location: '.UpdraftPlus_Options::admin_page_url().'?page=updraftplus&error='.urlencode($msg));
				}
			}
		} else {
			header('Location: '.UpdraftPlus_Options::admin_page_url().'?page=updraftplus&error='.urlencode(__('Authorization failed', 'updraftplus')));
		}
	}

	public function show_authed_admin_success() {

		global $updraftplus_admin;

		$opts = $this->get_opts();

		if (empty($opts['tmp_access_token'])) return;
		$updraftplus_tmp_access_token = $opts['tmp_access_token'];

		$message = '';
		try {
			$service = $this->bootstrap($updraftplus_tmp_access_token);
			if (false != $service && !is_wp_error($service)) {

				$about = $service->about->get();
				$quota_total = max($about->getQuotaBytesTotal(), 1);
				$quota_used = $about->getQuotaBytesUsed();
				$username = $about->getName();
				$opts['ownername'] = $username;

				if (is_numeric($quota_total) && is_numeric($quota_used)) {
					$available_quota = $quota_total - $quota_used;
					$used_perc = round($quota_used*100/$quota_total, 1);
					$message .= sprintf(__('Your %s quota usage: %s %% used, %s available','updraftplus'), 'Google Drive', $used_perc, round($available_quota/1048576, 1).' MB');
				}
			}
		} catch (Exception $e) {
			if (is_a($e, 'Google_Service_Exception')) {
				$errs = $e->getErrors();
				$message .= __('However, subsequent access attempts failed:', 'updraftplus');
				if (is_array($errs)) {
					$message .= '<ul style="list-style: disc inside;">';
					foreach ($errs as $err) {
						$message .= '<li>';
						if (!empty($err['reason'])) $message .= '<strong>'.htmlspecialchars($err['reason']).':</strong> ';
						if (!empty($err['message'])) {
							$message .= htmlspecialchars($err['message']);
						} else {
							$message .= htmlspecialchars(serialize($err));
						}
						$message .= '</li>';
					}
					$message .= '</ul>';
				} else {
					$message .= htmlspecialchars(serialize($errs));
				}
			}
		}

		$updraftplus_admin->show_admin_warning(__('Success', 'updraftplus').': '.sprintf(__('you have authenticated your %s account.', 'updraftplus'),__('Google Drive','updraftplus')).' '.((!empty($username)) ? sprintf(__('Name: %s.', 'updraftplus'), $username).' ' : '').$message);

		unset($opts['tmp_access_token']);
		UpdraftPlus_Options::update_updraft_option('updraft_googledrive', $opts);

	}

	// This function just does the formalities, and off-loads the main work to upload_file
	public function backup($backup_array) {

		global $updraftplus, $updraftplus_backup;

		$service = $this->bootstrap();
		if (false == $service || is_wp_error($service)) return $service;

		$updraft_dir = trailingslashit($updraftplus->backups_dir_location());

		$opts = $this->get_opts();

		try {
			$parent_id = $this->get_parent_id($opts);
		} catch (Exception $e) {
			$updraftplus->log("Google Drive upload: failed to access parent folder: ".$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
			$updraftplus->log(sprintf(__('Failed to upload to %s','updraftplus'),__('Google Drive','updraftplus')).': '.__('failed to access parent folder', 'updraftplus').' ('.$e->getMessage().')', 'error');
			return false;
		}

		foreach ($backup_array as $file) {

			$available_quota = -1;

			try {
				$about = $service->about->get();
				$quota_total = max($about->getQuotaBytesTotal(), 1);
				$quota_used = $about->getQuotaBytesUsed();
				$available_quota = $quota_total - $quota_used;
				$message = "Google Drive quota usage: used=".round($quota_used/1048576,1)." MB, total=".round($quota_total/1048576,1)." MB, available=".round($available_quota/1048576,1)." MB";
				$updraftplus->log($message);
			} catch (Exception $e) {
				$updraftplus->log("Google Drive quota usage: failed to obtain this information: ".$e->getMessage());
			}

			$file_path = $updraft_dir.$file;
			$file_name = basename($file_path);
			$updraftplus->log("$file_name: Attempting to upload to Google Drive (into folder id: $parent_id)");

			$filesize = filesize($file_path);
			$already_failed = false;
			if ($available_quota != -1) {
				if ($filesize > $available_quota) {
					$already_failed = true;
					$updraftplus->log("File upload expected to fail: file ($file_name) size is $filesize b, whereas available quota is only $available_quota b");
					$updraftplus->log(sprintf(__("Account full: your %s account has only %d bytes left, but the file to be uploaded is %d bytes",'updraftplus'),__('Google Drive', 'updraftplus'), $available_quota, $filesize), +'error');
				}
			}

			if (!$already_failed && $filesize > 10737418240) {
				# 10GB
				$updraftplus->log("File upload expected to fail: file ($file_name) size is $filesize b (".round($filesize/1073741824, 4)." GB), whereas Google Drive's limit is 10GB (1073741824 bytes)");
				$updraftplus->log(sprintf(__("Upload expected to fail: the %s limit for any single file is %s, whereas this file is %s GB (%d bytes)",'updraftplus'),__('Google Drive', 'updraftplus'), '10GB (1073741824)', round($filesize/1073741824, 4), $filesize), 'warning');
			} 

			try {
				$timer_start = microtime(true);
				if ($this->upload_file($file_path, $parent_id)) {
					$updraftplus->log('OK: Archive ' . $file_name . ' uploaded to Google Drive in ' . ( round(microtime(true) - $timer_start, 2) ) . ' seconds');
					$updraftplus->uploaded_file($file);
				} else {
					$updraftplus->log("ERROR: $file_name: Failed to upload to Google Drive" );
					$updraftplus->log("$file_name: ".sprintf(__('Failed to upload to %s','updraftplus'),__('Google Drive','updraftplus')), 'error');
				}
			} catch (Exception $e) {
				$msg = $e->getMessage();
				$updraftplus->log("ERROR: Google Drive upload error: ".$msg.' (line: '.$e->getLine().', file: '.$e->getFile().')');
				if (false !== ($p = strpos($msg, 'The user has exceeded their Drive storage quota'))) {
					$updraftplus->log("$file_name: ".sprintf(__('Failed to upload to %s','updraftplus'),__('Google Drive','updraftplus')).': '.substr($msg, $p), 'error');
				} else {
					$updraftplus->log("$file_name: ".sprintf(__('Failed to upload to %s','updraftplus'),__('Google Drive','updraftplus')), 'error');
				}
				$this->client->setDefer(false);
			}
		}

		return null;
	}

	public function bootstrap($access_token = false) {

		global $updraftplus;

		if (!empty($this->service) && is_object($this->service) && is_a($this->service, 'Google_Service_Drive')) return $this->service;

		$opts = $this->get_opts();

		if (empty($access_token)) {
			if (empty($opts['token']) || empty($opts['clientid']) || empty($opts['secret'])) {
				$updraftplus->log('Google Drive: this account is not authorised');
				$updraftplus->log('Google Drive: '.__('Account is not authorized.', 'updraftplus'), 'error', 'googledrivenotauthed');
				return new WP_Error('not_authorized', __('Account is not authorized.', 'updraftplus'));
			}
		}

// 		$included_paths = explode(PATH_SEPARATOR, get_include_path());
// 		if (!in_array(UPDRAFTPLUS_DIR.'/includes', $included_paths)) {
// 			set_include_path(UPDRAFTPLUS_DIR.'/includes'.PATH_SEPARATOR.get_include_path());
// 		}

		
		$spl = spl_autoload_functions();
		if (is_array($spl)) {
			// Workaround for Google Drive CDN plugin's autoloader
			if (in_array('wpbgdc_autoloader', $spl)) spl_autoload_unregister('wpbgdc_autoloader');
			// http://www.wpdownloadmanager.com/download/google-drive-explorer/ - but also others, since this is the default function name used by the Google SDK
			if (in_array('google_api_php_client_autoload', $spl)) spl_autoload_unregister('google_api_php_client_autoload');
		}

/*
		if (!class_exists('Google_Config')) require_once 'Google/Config.php';
		if (!class_exists('Google_Client')) require_once 'Google/Client.php';
		if (!class_exists('Google_Service_Drive')) require_once 'Google/Service/Drive.php';
		if (!class_exists('Google_Http_Request')) require_once 'Google/Http/Request.php';
*/
		if ((!class_exists('Google_Config') || !class_exists('Google_Client') || !class_exists('Google_Service_Drive') || !class_exists('Google_Http_Request')) && !function_exists('google_api_php_client_autoload_updraftplus')) {
			require_once(UPDRAFTPLUS_DIR.'/includes/Google/autoload.php'); 
		}

		if (!class_exists('UpdraftPlus_Google_Http_MediaFileUpload')) {
			require_once(UPDRAFTPLUS_DIR.'/includes/google-extensions.php'); 
		}

		$config = new Google_Config();
		$config->setClassConfig('Google_IO_Abstract', 'request_timeout_seconds', 60);
		# In our testing, $service->about->get() fails if gzip is not disabled when using the stream wrapper
		if (!function_exists('curl_version') || !function_exists('curl_exec') || (defined('UPDRAFTPLUS_GOOGLEDRIVE_DISABLEGZIP') && UPDRAFTPLUS_GOOGLEDRIVE_DISABLEGZIP)) {
			$config->setClassConfig('Google_Http_Request', 'disable_gzip', true);
		}

		$client = new Google_Client($config);
		$client->setClientId($opts['clientid']);
		$client->setClientSecret($opts['secret']);
// 			$client->setUseObjects(true);

		if (empty($access_token)) {
			$access_token = $this->access_token($opts['token'], $opts['clientid'], $opts['secret']);
		}

		// Do we have an access token?
		if (empty($access_token) || is_wp_error($access_token)) {
			$updraftplus->log('ERROR: Have not yet obtained an access token from Google (has the user authorised?)');
			$updraftplus->log(__('Have not yet obtained an access token from Google - you need to authorise or re-authorise your connection to Google Drive.','updraftplus'), 'error');
			return $access_token;
		}

		$client->setAccessToken(json_encode(array(
			'access_token' => $access_token,
			'refresh_token' => $opts['token']
		)));

		$io = $client->getIo();
		$setopts = array();

		if (is_a($io, 'Google_IO_Curl')) {
			$setopts[CURLOPT_SSL_VERIFYPEER] = UpdraftPlus_Options::get_updraft_option('updraft_ssl_disableverify') ? false : true;
			if (!UpdraftPlus_Options::get_updraft_option('updraft_ssl_useservercerts')) $setopts[CURLOPT_CAINFO] = UPDRAFTPLUS_DIR.'/includes/cacert.pem';
			// Raise the timeout from the default of 15
			$setopts[CURLOPT_TIMEOUT] = 60;
			$setopts[CURLOPT_CONNECTTIMEOUT] = 15;
			if (defined('UPDRAFTPLUS_IPV4_ONLY') && UPDRAFTPLUS_IPV4_ONLY) $setopts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
		} elseif (is_a($io, 'Google_IO_Stream')) {
			$setopts['timeout'] = 60;
			# We had to modify the SDK to support this
			# https://wiki.php.net/rfc/tls-peer-verification - before PHP 5.6, there is no default CA file
			if (!UpdraftPlus_Options::get_updraft_option('updraft_ssl_useservercerts') || (version_compare(PHP_VERSION, '5.6.0', '<'))) $setopts['cafile'] = UPDRAFTPLUS_DIR.'/includes/cacert.pem';
			if (UpdraftPlus_Options::get_updraft_option('updraft_ssl_disableverify')) $setopts['disable_verify_peer'] = true;
		}

		$io->setOptions($setopts);

		$service = new Google_Service_Drive($client);
		$this->client = $client;
		$this->service = $service;

		try {
			# Get the folder name, if not previously known (this is for the legacy situation where an id, not a name, was stored)
			if (!empty($opts['parentid']) && (!is_array($opts['parentid']) || empty($opts['parentid']['name']))) {
				$rootid = $this->root_id();
				$title = '';
				$parentid = is_array($opts['parentid']) ? $opts['parentid']['id'] : $opts['parentid'];
				while ((!empty($parentid) && $parentid != $rootid)) {
					$resource = $service->files->get($parentid);
					$title = ($title) ? $resource->getTitle().'/'.$title : $resource->getTitle();
					$parents = $resource->getParents();
					if (is_array($parents) && count($parents)>0) {
						$parent = array_shift($parents);
						$parentid = is_a($parent, 'Google_Service_Drive_ParentReference') ? $parent->getId() : false;
					} else {
						$parentid = false;
					}
				}
				if (!empty($title)) {
					$opts['parentid'] = array(
						'id' => (is_array($opts['parentid']) ? $opts['parentid']['id'] : $opts['parentid']),
						'name' => $title
					);
					UpdraftPlus_Options::update_updraft_option('updraft_googledrive', $opts);
				}
			}
		} catch (Exception $e) {
			$updraftplus->log("Google Drive: failed to obtain name of parent folder: ".$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
		} 

		return $this->service;

	}

	// Returns array of Google_Service_Drive_DriveFile objects
	private function get_subitems($parent_id, $type = 'any', $match = 'backup_') {
		$q = '"'.$parent_id.'" in parents and trashed = false';
		if ('dir' == $type) {
			$q .= ' and mimeType = "application/vnd.google-apps.folder"';
		} elseif ('file' == $type) {
			$q .= ' and mimeType != "application/vnd.google-apps.folder"';
		}
		# We used to use 'contains' in both cases, but this exposed some bug that might be in the SDK or at the Google end - a result that matched for = was not returned with contains
		if (!empty($match)) {
			if ('backup_' == $match) {
				$q .= " and title contains '$match'";
			} else {
				$q .= " and title = '$match'";
			}
		}

		$result = array();
		$pageToken = NULL;

		do {
			try {
				// Default for maxResults is 100
				$parameters = array('q' => $q, 'maxResults' => 200);
				if ($pageToken) {
					$parameters['pageToken'] = $pageToken;
				}
				$files = $this->service->files->listFiles($parameters);

				$result = array_merge($result, $files->getItems());
				$pageToken = $files->getNextPageToken();
			} catch (Exception $e) {
				global $updraftplus;
				$updraftplus->log("Google Drive: get_subitems: An error occurred (will not fetch further): " . $e->getMessage());
				$pageToken = NULL;
			}
		} while ($pageToken);
		
		return $result;
    }

	public function delete($files, $data=null, $sizeinfo = array()) {

		if (is_string($files)) $files=array($files);

		$service = $this->bootstrap();
		if (is_wp_error($service) || false == $service) return $service;

		$opts = $this->get_opts();

		global $updraftplus;

		try {
			$parent_id = $this->get_parent_id($opts);
			$sub_items = $this->get_subitems($parent_id, 'file');
		} catch (Exception $e) {
			$updraftplus->log("Google Drive delete: failed to access parent folder: ".$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
			return false;
		}

		$ret = true;

		foreach ($sub_items as $item) {
			$title = "(unknown)";
			try {
				$title = $item->getTitle();
				if (in_array($title, $files)) {
					$service->files->delete($item->getId());
					$updraftplus->log("$title: Deletion successful");
					if(($key = array_search($title, $files)) !== false) {
						unset($files[$key]);
					}
				}
			} catch (Exception $e) {
				$updraftplus->log("Google Drive delete: exception: ".$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
				$ret = false;
				continue;
			}
		}

		foreach ($files as $file) {
			$updraftplus->log("$file: Deletion failed: file was not found");
		}

		return $ret;

	}

	private function upload_file($file, $parent_id, $try_again = true) {

		global $updraftplus;
		$opts = $this->get_opts();
		$basename = basename($file);

		$service = $this->service;
		$client = $this->client;

		# See: https://github.com/google/google-api-php-client/blob/master/examples/fileupload.php (at time of writing, only shows how to upload in chunks, not how to resume)

		$client->setDefer(true);

		$local_size = filesize($file);

		$gdfile = new Google_Service_Drive_DriveFile();
		$gdfile->title  = $basename;

		$ref = new Google_Service_Drive_ParentReference;
		$ref->setId($parent_id);
		$gdfile->setParents(array($ref));

		$size = 0;
		$request = $service->files->insert($gdfile);

		$chunk_bytes = 1048576;

		$hash = md5($file);
		$transkey = 'gdresume_'.$hash;
		// This is unset upon completion, so if it is set then we are resuming
		$possible_location = $updraftplus->jobdata_get($transkey);

		if (is_array($possible_location)) {

			$headers = array( 'content-range' => "bytes */".$local_size);

			$httpRequest = new Google_Http_Request(
				$possible_location[0],
				'PUT',
				$headers,
				''
			);
			$response = $this->client->getIo()->makeRequest($httpRequest);
			$can_resume = false;
			if (308 == $response->getResponseHttpCode()) {
				$range = $response->getResponseHeader('range');
				if (!empty($range) && preg_match('/bytes=0-(\d+)$/', $range, $matches)) {
					$can_resume = true;
					$possible_location[1] = $matches[1]+1;
					$updraftplus->log("$basename: upload already began; attempting to resume from byte ".$matches[1]);
				}
			}
			if (!$can_resume) {
				$updraftplus->log("$basename: upload already began; attempt to resume did not succeed (HTTP code: ".$response->getResponseHttpCode().")");
			}
		}

		// UpdraftPlus_Google_Http_MediaFileUpload extends Google_Http_MediaFileUpload, with a few extra methods to change private properties to public ones
		$media = new UpdraftPlus_Google_Http_MediaFileUpload(
			$client,
			$request,
			(('.zip' == substr($basename, -4, 4)) ? 'application/zip' : 'application/octet-stream'),
			null,
			true,
			$chunk_bytes
		);
		$media->setFileSize($local_size);

		if (!empty($possible_location)) {
// 			$media->resumeUri = $possible_location[0];
// 			$media->progress = $possible_location[1];
			$media->updraftplus_setResumeUri($possible_location[0]);
			$media->updraftplus_setProgress($possible_location[1]);
			$size = $possible_location[1];
		}
		if ($size >= $local_size) return true;

		$status = false;
		if (false == ($handle = fopen($file, 'rb'))) {
			$updraftplus->log("Google Drive: failed to open file: $basename");
			$updraftplus->log("$basename: ".sprintf(__('%s Error: Failed to open local file', 'updraftplus'),'Google Drive'), 'error');
			return false;
		}
		if ($size > 0 && 0 != fseek($handle, $size)) {
			$updraftplus->log("Google Drive: failed to fseek file: $basename, $size");
			$updraftplus->log("$basename (fseek): ".sprintf(__('%s Error: Failed to open local file', 'updraftplus'), 'Google Drive'), 'error');
			return false;
		}

		$pointer = $size;

		try {
			while (!$status && !feof($handle)) {
				$chunk = fread($handle, $chunk_bytes);
				# Error handling??
				$pointer += strlen($chunk);
				$status = $media->nextChunk($chunk);
				$updraftplus->jobdata_set($transkey, array($media->updraftplus_getResumeUri(), $media->getProgress()));
				$updraftplus->record_uploaded_chunk(round(100*$pointer/$local_size, 1), $media->getProgress(), $file);
			}
			
		} catch (Google_Service_Exception $e) {
			$updraftplus->log("ERROR: Google Drive upload error (".get_class($e)."): ".$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
			$client->setDefer(false);
			fclose($handle);
			$updraftplus->jobdata_delete($transkey);
			if (false == $try_again) throw($e);
			# Reset this counter to prevent the something_useful_happened condition's possibility being sent into the far future and potentially missed
			if ($updraftplus->current_resumption > 9) $updraftplus->jobdata_set('uploaded_lastreset', $updraftplus->current_resumption);
			return $this->upload_file($file, $parent_id, false);
		}

		// The final value of $status will be the data from the API for the object
		// that has been uploaded.
		$result = false;
		if ($status != false) $result = $status;

		fclose($handle);
		$client->setDefer(false);
		$updraftplus->jobdata_delete($transkey);

		return true;

	}

	public function download($file) {

		global $updraftplus;

		$service = $this->bootstrap();
		if (false == $service || is_wp_error($service)) return false;

		global $updraftplus;
		$opts = $this->get_opts();

		try {
			$parent_id = $this->get_parent_id($opts);
			#$gdparent = $service->files->get($parent_id);
			$sub_items = $this->get_subitems($parent_id, 'file');
		} catch (Exception $e) {
			$updraftplus->log("Google Drive delete: failed to access parent folder: ".$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
			return false;
		}

		$found = false;
		foreach ($sub_items as $item) {
			if ($found) continue;
			$title = "(unknown)";
			try {
				$title = $item->getTitle();
				if ($title == $file) {
					$gdfile = $item;
					$found = $item->getId();
					$size = $item->getFileSize();
				}
			} catch (Exception $e) {
				$updraftplus->log("Google Drive download: exception: ".$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
			}
		}

		if (false === $found) {
			$updraftplus->log("Google Drive download: failed: file not found");
			$updraftplus->log("$file: ".sprintf(__("%s Error",'updraftplus'), 'Google Drive').": ".__('File not found', 'updraftplus'), 'error');
			return false;
		}

		$download_to = $updraftplus->backups_dir_location().'/'.$file;

		$existing_size = (file_exists($download_to)) ? filesize($download_to) : 0;

		if ($existing_size >= $size) {
			$updraftplus->log('Google Drive download: was already downloaded ('.filesize($download_to)."/$size bytes)");
			return true;
		}

		# Chunk in units of 2MB
		$chunk_size = 2097152;

		try {
			while ($existing_size < $size) {

				$end = min($existing_size + $chunk_size, $size);

				if ($existing_size > 0) {
					$put_flag = FILE_APPEND;
					$headers = array('Range' => 'bytes='.$existing_size.'-'.$end);
				} else {
					$put_flag = null;
					$headers = ($end < $size) ? array('Range' => 'bytes=0-'.$end) : array();
				}

				$pstart = round(100*$existing_size/$size,1);
				$pend = round(100*$end/$size,1);
				$updraftplus->log("Requesting byte range: $existing_size - $end ($pstart - $pend %)");

				$request = $this->client->getAuth()->sign(new Google_Http_Request($gdfile->getDownloadUrl(), 'GET', $headers, null));
				$http_request = $this->client->getIo()->makeRequest($request);
				$http_response = $http_request->getResponseHttpCode();
				if (200 == $http_response || 206 == $http_response) {
					file_put_contents($download_to, $http_request->getResponseBody(), $put_flag);
				} else {
					$updraftplus->log("Google Drive download: failed: unexpected HTTP response code: ".$http_response);
					$updraftplus->log(sprintf(__("%s download: failed: file not found", 'updraftplus'), 'Google Drive'), 'error');
					return false;
				}

				clearstatcache();
				$new_size = filesize($download_to);
				if ($new_size > $existing_size) {
					$existing_size = $new_size;
				} else {
					throw new Exception('Failed to obtain any new data at size: '.$existing_size);
				}
			}
		} catch (Exception $e) {
			$updraftplus->log("Google Drive download: exception: ".$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
		}

		return true;
	}

	public function config_print() {
		$opts = $this->get_opts();
		?>
			<tr class="updraftplusmethod googledrive">
				<td></td>
				<td>
				<img src="https://developers.google.com/drive/images/drive_logo.png" alt="<?php _e('Google Drive','updraftplus');?>">
				<p><em><?php printf(__('%s is a great choice, because UpdraftPlus supports chunked uploads - no matter how big your site is, UpdraftPlus can upload it a little at a time, and not get thwarted by timeouts.','updraftplus'),'Google Drive');?></em></p>
				</td>
			</tr>

			<tr class="updraftplusmethod googledrive">
			<th></th>
			<td>
			<?php
				$admin_page_url = UpdraftPlus_Options::admin_page_url();
				# This is advisory - so the fact it doesn't match IPv6 addresses isn't important
				if (preg_match('#^(https?://(\d+)\.(\d+)\.(\d+)\.(\d+))/#', $admin_page_url, $matches)) {
					echo '<p><strong>'.htmlspecialchars(sprintf(__("%s does not allow authorisation of sites hosted on direct IP addresses. You will need to change your site's address (%s) before you can use %s for storage.", 'updraftplus'), __('Google Drive','updraftplus'), $matches[1], __('Google Drive','updraftplus'))).'</strong></p>';
				} else {
					?>

					<p><a href="https://updraftplus.com/support/configuring-google-drive-api-access-in-updraftplus/"><strong><?php _e('For longer help, including screenshots, follow this link. The description below is sufficient for more expert users.','updraftplus');?></strong></a></p>

					<p><a href="https://console.developers.google.com"><?php _e('Follow this link to your Google API Console, and there activate the Drive API and create a Client ID in the API Access section.','updraftplus');?></a> <?php _e("Select 'Web Application' as the application type.",'updraftplus');?></p><p><?php echo htmlspecialchars(__('You must add the following as the authorised redirect URI (under "More Options") when asked','updraftplus'));?>: <kbd><?php echo UpdraftPlus_Options::admin_page_url().'?action=updraftmethod-googledrive-auth'; ?></kbd> <?php _e('N.B. If you install UpdraftPlus on several WordPress sites, then you cannot re-use your project; you must create a new one from your Google API console for each site.','updraftplus');?>
					</p>
					<?php
				}
			?>

			</td>
			</tr>

			<tr class="updraftplusmethod googledrive">
				<th><?php echo __('Google Drive','updraftplus').' '.__('Client ID', 'updraftplus'); ?>:</th>
				<td><input type="text" autocomplete="off" style="width:442px" name="updraft_googledrive[clientid]" value="<?php echo htmlspecialchars($opts['clientid']) ?>" /><br><em><?php _e('If Google later shows you the message "invalid_client", then you did not enter a valid client ID here.','updraftplus');?></em></td>
			</tr>
			<tr class="updraftplusmethod googledrive">
				<th><?php echo __('Google Drive','updraftplus').' '.__('Client Secret', 'updraftplus'); ?>:</th>
				<td><input type="<?php echo apply_filters('updraftplus_admin_secret_field_type', 'password'); ?>" style="width:442px" name="updraft_googledrive[secret]" value="<?php echo htmlspecialchars($opts['secret']); ?>" /></td>
			</tr>

			<?php

			# Legacy configuration
			if (isset($opts['parentid'])) {
				$parentid = (is_array($opts['parentid'])) ? $opts['parentid']['id'] : $opts['parentid'];
				$showparent = (is_array($opts['parentid']) && !empty($opts['parentid']['name'])) ? $opts['parentid']['name'] : $parentid;
				$folder_opts = '<tr class="updraftplusmethod googledrive">
				<th>'.__('Google Drive','updraftplus').' '.__('Folder', 'updraftplus').':</th>
				<td><input type="hidden" name="updraft_googledrive[parentid][id]" value="'.htmlspecialchars($parentid).'">
				<input type="text" title="'.esc_attr($parentid).'" readonly="readonly" style="width:442px" value="'.htmlspecialchars($showparent).'">';
				if (!empty($parentid) && (!is_array($opts['parentid']) || empty($opts['parentid']['name']))) {
					$folder_opts .= '<em>'.__("<strong>This is NOT a folder name</strong>.",'updraftplus').' '.__('It is an ID number internal to Google Drive', 'updraftplus').'</em>';
				} else {
					$folder_opts .= '<input type="hidden" name="updraft_googledrive[parentid][name]" value="'.htmlspecialchars($opts['parentid']['name']).'">';
				}
			} else {
				$folder_opts = '<tr class="updraftplusmethod googledrive">
				<th>'.__('Google Drive','updraftplus').' '.__('Folder', 'updraftplus').':</th>
				<td><input type="text" readonly="readonly" style="width:442px" name="updraft_googledrive[folder]" value="UpdraftPlus" />';
			}
			$folder_opts .= '<br><em><a href="https://updraftplus.com/shop/updraftplus-premium/">'.__('To be able to set a custom folder name, use UpdraftPlus Premium.', 'updraftplus').'</em></a>';
			$folder_opts .= '</td></tr>';
			echo apply_filters('updraftplus_options_googledrive_others', $folder_opts, $opts);
			?>

			<tr class="updraftplusmethod googledrive">
				<th><?php _e('Authenticate with Google');?>:</th>
				<td><p><?php if (!empty($opts['token'])) echo __("<strong>(You appear to be already authenticated,</strong> though you can authenticate again to refresh your access if you've had a problem).", 'updraftplus'); ?>

				<?php
				if (!empty($opts['token']) && !empty($opts['ownername'])) {
					echo '<br>'.sprintf(__("Account holder's name: %s.", 'updraftplus'), htmlspecialchars($opts['ownername'])).' ';
				}
				?>
				</p>
				<p>

				<a class="updraft_authlink" href="<?php echo UpdraftPlus_Options::admin_page_url();?>?action=updraftmethod-googledrive-auth&page=updraftplus&updraftplus_googleauth=doit"><?php print __('<strong>After</strong> you have saved your settings (by clicking \'Save Changes\' below), then come back here once and click this link to complete authentication with Google.','updraftplus');?></a>
				</p>
				</td>
			</tr>
		<?php
	}	
}
