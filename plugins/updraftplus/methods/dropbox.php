<?php

// https://www.dropbox.com/developers/apply?cont=/developers/apps

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed.');

# Converted to job_options: yes
# Converted to array options: yes

# Migrate options to new-style storage - May 2014
# appkey, secret, folder, updraft_dropboxtk_request_token, updraft_dropboxtk_access_token
if (!is_array(UpdraftPlus_Options::get_updraft_option('updraft_dropbox'))) {
	$opts = array(
		'appkey' => UpdraftPlus_Options::get_updraft_option('updraft_dropbox_appkey'),
		'secret' => UpdraftPlus_Options::get_updraft_option('updraft_dropbox_secret'),
		'folder' => UpdraftPlus_Options::get_updraft_option('updraft_dropbox_folder'),
		'tk_request_token' => UpdraftPlus_Options::get_updraft_option('updraft_dropboxtk_request_token'),
		'tk_access_token' => UpdraftPlus_Options::get_updraft_option('updraft_dropboxtk_access_token'),
	);
	if (serialize($opts) != 'a:5:{s:6:"appkey";N;s:6:"secret";N;s:6:"folder";N;s:16:"tk_request_token";N;s:15:"tk_access_token";N;}') {
		UpdraftPlus_Options::update_updraft_option('updraft_dropbox', $opts);
		UpdraftPlus_Options::delete_updraft_option('updraft_dropbox_appkey');
		UpdraftPlus_Options::delete_updraft_option('updraft_dropbox_secret');
		UpdraftPlus_Options::delete_updraft_option('updraft_dropbox_folder');
		UpdraftPlus_Options::delete_updraft_option('updraft_dropboxtk_request_token');
		UpdraftPlus_Options::delete_updraft_option('updraft_dropboxtk_access_token');
	}
}

class UpdraftPlus_BackupModule_dropbox {

	private $current_file_hash;
	private $current_file_size;
	private $dropbox_object;
	private $uploaded_offset;
	private $upload_tick;

	public function chunked_callback($offset, $uploadid, $fullpath = false) {
		global $updraftplus;

		// Update upload ID
		$updraftplus->jobdata_set('updraf_dbid_'.$this->current_file_hash, $uploadid);
		$updraftplus->jobdata_set('updraf_dbof_'.$this->current_file_hash, $offset);

		$time_now = microtime(true);
		
		$time_since_last_tick = $time_now - $this->upload_tick;
		$data_since_last_tick = $offset - $this->uploaded_offset;
		
		$this->upload_tick = $time_now;
		$this->uploaded_offset = $offset;
		
		$chunk_size = $updraftplus->jobdata_get('dropbox_chunk_size', 1048576);
		// Don't go beyond 10MB, or change the chunk size after the last segment
		if ($chunk_size < 10485760 && $this->current_file_size > 0 && $offset < $this->current_file_size) {
			$job_run_time = $time_now - $updraftplus->job_time_ms;
			if ($time_since_last_tick < 10) {
				$upload_rate = $data_since_last_tick / max($time_since_last_tick, 1);
				$upload_secs = min(floor($job_run_time), 10);
				if ($job_run_time < 15) $upload_secs = max(6, $job_run_time*0.6);
				$new_chunk = max(min($upload_secs * $upload_rate * 0.9, 10485760), 1048576);
				$new_chunk = $new_chunk - ($new_chunk % 524288);
				$chunk_size = (int)$new_chunk;
				$this->dropbox_object->setChunkSize($chunk_size);
				$updraftplus->jobdata_set('dropbox_chunk_size', $chunk_size);
			}
		}
		
		if ($this->current_file_size > 0) {
			$percent = round(100*($offset/$this->current_file_size),1);
			$updraftplus->record_uploaded_chunk($percent, "$uploadid, $offset, ".round($chunk_size/1024, 1)." KB", $fullpath);
		} else {
			$updraftplus->log("Dropbox: Chunked Upload: $offset bytes uploaded");
			// This act is done by record_uploaded_chunk, and helps prevent overlapping runs
			touch($fullpath);
		}
	}

	public function get_credentials() {
		return array('updraft_dropbox');
	}

	public function get_opts() {
		global $updraftplus;
		$opts = $updraftplus->get_job_option('updraft_dropbox');
		if (!is_array($opts)) $opts = array();
		if (!isset($opts['folder'])) $opts['folder'] = '';
		return $opts;
	}

	public function backup($backup_array) {

		global $updraftplus, $updraftplus_backup;
		$updraftplus->log("Dropbox: begin cloud upload");

		$opts = $this->get_opts();

		if (empty($opts['tk_request_token'])) {
			$updraftplus->log('You do not appear to be authenticated with Dropbox (1)');
			$updraftplus->log(__('You do not appear to be authenticated with Dropbox','updraftplus'), 'error');
			return false;
		}
		
		$chunk_size = $updraftplus->jobdata_get('dropbox_chunk_size', 1048576);

		try {
			$dropbox = $this->bootstrap();
			if (false === $dropbox) throw new Exception(__('You do not appear to be authenticated with Dropbox', 'updraftplus'));
			$updraftplus->log("Dropbox: access gained; setting chunk size to: ".round($chunk_size/1024, 1)." KB");
			$dropbox->setChunkSize($chunk_size);
		} catch (Exception $e) {
			$updraftplus->log('Dropbox error when trying to gain access: '.$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
			$updraftplus->log(sprintf(__('Dropbox error: %s (see log file for more)','updraftplus'), $e->getMessage()), 'error');
			return false;
		}

		$updraft_dir = $updraftplus->backups_dir_location();
		$dropbox_folder = trailingslashit($opts['folder']);

		foreach ($backup_array as $file) {

			$available_quota = -1;

			// If we experience any failures collecting account info, then carry on anyway
			try {

				$accountInfo = $dropbox->accountInfo();

				if ($accountInfo['code'] != "200") {
					$message = "Dropbox account/info did not return HTTP 200; returned: ". $accountInfo['code'];
				} elseif (!isset($accountInfo['body'])) {
					$message = "Dropbox account/info did not return the expected data";
				} else {
					$body = $accountInfo['body'];
					if (!isset($body->quota_info)) {
						$message = "Dropbox account/info did not return the expected data";
					} else {
						$quota_info = $body->quota_info;
						$total_quota = $quota_info->quota;
						$normal_quota = $quota_info->normal;
						$shared_quota = $quota_info->shared;
						$available_quota = $total_quota - ($normal_quota + $shared_quota);
						$message = "Dropbox quota usage: normal=".round($normal_quota/1048576,1)." MB, shared=".round($shared_quota/1048576,1)." MB, total=".round($total_quota/1048576,1)." MB, available=".round($available_quota/1048576,1)." MB";
					}
				}
				$updraftplus->log($message);
			} catch (Exception $e) {
				$updraftplus->log("Dropbox error: exception (".get_class($e).") occurred whilst getting account info: ".$e->getMessage());
				//$updraftplus->log(sprintf(__("%s error: %s", 'updraftplus'), 'Dropbox', $e->getMessage()).' ('.$e->getCode().')', 'warning', md5($e->getMessage()));
			}

			$file_success = 1;

			$hash = md5($file);
			$this->current_file_hash = $hash;

			$filesize = filesize($updraft_dir.'/'.$file);
			$this->current_file_size = $filesize;

			// Into KB
			$filesize = $filesize/1024;
			$microtime = microtime(true);

			if ($upload_id = $updraftplus->jobdata_get('updraf_dbid_'.$hash)) {
				# Resume
				$offset =  $updraftplus->jobdata_get('updraf_dbof_'.$hash);
				$updraftplus->log("This is a resumption: $offset bytes had already been uploaded");
			} else {
				$offset = 0;
				$upload_id = null;
			}

			// We don't actually abort now - there's no harm in letting it try and then fail
			if ($available_quota != -1 && $available_quota < ($filesize-$offset)) {
				$updraftplus->log("File upload expected to fail: file data remaining to upload ($file) size is ".($filesize-$offset)." b (overall file size; $filesize b), whereas available quota is only $available_quota b");
				$updraftplus->log(sprintf(__("Account full: your %s account has only %d bytes left, but the file to be uploaded has %d bytes remaining (total size: %d bytes)",'updraftplus'),'Dropbox', $available_quota, $filesize-$offset, $filesize), 'error');
			}

			// Old-style, single file put: $put = $dropbox->putFile($updraft_dir.'/'.$file, $dropbox_folder.$file);

			$ufile = apply_filters('updraftplus_dropbox_modpath', $file);

			$updraftplus->log("Dropbox: Attempt to upload: $file to: $ufile");

			$this->upload_tick = microtime(true);
			$this->uploaded_offset = $offset;

			try {
				$response = $dropbox->chunkedUpload($updraft_dir.'/'.$file, '', $ufile, true, $offset, $upload_id, array($this, 'chunked_callback'));
				if (empty($response['code']) || "200" != $response['code']) {
					$updraftplus->log('Unexpected HTTP code returned from Dropbox: '.$response['code']." (".serialize($response).")");
					if ($response['code'] >= 400) {
						$updraftplus->log('Dropbox '.sprintf(__('error: failed to upload file to %s (see log file for more)','updraftplus'), $file), 'error');
					} else {
						$updraftplus->log(sprintf(__('%s did not return the expected response - check your log file for more details', 'updraftplus'), 'Dropbox'), 'warning');
					}
				}
			} catch (Exception $e) {
				$updraftplus->log("Dropbox chunked upload exception (".get_class($e)."): ".$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
				if (preg_match("/Submitted input out of alignment: got \[(\d+)\] expected \[(\d+)\]/i", $e->getMessage(), $matches)) {
					// Try the indicated offset
					$we_tried = $matches[1];
					$dropbox_wanted = $matches[2];
					$updraftplus->log("Dropbox not yet aligned: tried=$we_tried, wanted=$dropbox_wanted; will attempt recovery");
					$this->uploaded_offset = $dropbox_wanted;
					try {
						$dropbox->chunkedUpload($updraft_dir.'/'.$file, '', $ufile, true, $dropbox_wanted, $upload_id, array($this, 'chunked_callback'));
					} catch (Exception $e) {
						$msg = $e->getMessage();
						if (preg_match('/Upload with upload_id .* already completed/', $msg)) {
							$updraftplus->log('Dropbox returned an error, but apparently indicating previous success: '.$msg);
						} else {
							$updraftplus->log('Dropbox error: '.$msg.' (line: '.$e->getLine().', file: '.$e->getFile().')');
							$updraftplus->log('Dropbox '.sprintf(__('error: failed to upload file to %s (see log file for more)','updraftplus'), $ufile), 'error');
							$file_success = 0;
							if (strpos($msg, 'select/poll returned error') !== false && $this->upload_tick > 0 && time() - $this->upload_tick > 800) {
								$updraftplus->reschedule(60);
								$updraftplus->log("Select/poll returned after a long time: scheduling a resumption and terminating for now");
								$updraftplus->record_still_alive();
								die;
							}
						}
					}
				} else {
					$msg = $e->getMessage();
					if (preg_match('/Upload with upload_id .* already completed/', $msg)) {
						$updraftplus->log('Dropbox returned an error, but apparently indicating previous success: '.$msg);
					} else {
						$updraftplus->log('Dropbox error: '.$msg);
						$updraftplus->log('Dropbox '.sprintf(__('error: failed to upload file to %s (see log file for more)','updraftplus'), $ufile), 'error');
						$file_success = 0;
						if (strpos($msg, 'select/poll returned error') !== false && $this->upload_tick > 0 && time() - $this->upload_tick > 800) {
							$updraftplus->reschedule(60);
							$updraftplus->log("Select/poll returned after a long time: scheduling a resumption and terminating for now");
							$updraftplus->record_still_alive();
							die;
						}
					}
				}
			}
			if ($file_success) {
				$updraftplus->uploaded_file($file);
				$microtime_elapsed = microtime(true)-$microtime;
				$speedps = $filesize/$microtime_elapsed;
				$speed = sprintf("%.2d",$filesize)." KB in ".sprintf("%.2d",$microtime_elapsed)."s (".sprintf("%.2d", $speedps)." KB/s)";
				$updraftplus->log("Dropbox: File upload success (".$file."): $speed");
				$updraftplus->jobdata_delete('updraft_duido_'.$hash);
				$updraftplus->jobdata_delete('updraft_duidi_'.$hash);
			}

		}

		return null;

	}

	# $match: a substring to require (tested via strpos() !== false)
	public function listfiles($match = 'backup_') {

		$opts = $this->get_opts();

		if (empty($opts['tk_access_token'])) return new WP_Error('no_settings', __('No settings were found', 'updraftplus').' (dropbox)');

		global $updraftplus;
		try {
			$dropbox = $this->bootstrap();
		} catch (Exception $e) {
			$updraftplus->log('Dropbox access error: '.$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
			return new WP_Error('access_error', $e->getMessage());
		}

		$searchpath = '/'.untrailingslashit(apply_filters('updraftplus_dropbox_modpath', ''));

		try {
			$search = $dropbox->search($match, $searchpath);
		} catch (Exception $e) {
			$updraftplus->log('Dropbox error: '.$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
			return new WP_Error('search_error', $e->getMessage());
		}

		if (empty($search['code']) || 200 != $search['code']) return new WP_Error('response_error', sprintf(__('%s returned an unexpected HTTP response: %s', 'updraftplus'), 'Dropbox', $search['code']), $search['body']);

		if (empty($search['body']) || !is_array($search['body'])) return array();

		$results = array();

		foreach ($search['body'] as $item) {
			if (!is_object($item)) continue;

			if ((!isset($item->bytes) || $item->bytes > 0) && empty($item->is_dir) && !empty($item->path) && 0 === strpos($item->path, $searchpath)) {

				$path = substr($item->path, strlen($searchpath));
				if ('/' == substr($path, 0, 1)) $path=substr($path, 1);

				# Ones in subfolders are not wanted
				if (false !== strpos($path, '/')) continue;

				$result = array('name' => $path);
				if (!empty($item->bytes)) $result['size'] = $item->bytes;

				$results[] = $result;

			}

		}

		return $results;
	}

	public function defaults() {
		return apply_filters('updraftplus_dropbox_defaults', array('Z3Q3ZmkwbnplNHA0Zzlx', 'bTY0bm9iNmY4eWhjODRt'));
	}

	public function delete($files, $data = null, $sizeinfo = array()) {

		global $updraftplus;
		if (is_string($files)) $files=array($files);

		$opts = $this->get_opts();

		if (empty($opts['tk_request_token'])) {
			$updraftplus->log('You do not appear to be authenticated with Dropbox (3)');
			$updraftplus->log(sprintf(__('You do not appear to be authenticated with %s (whilst deleting)', 'updraftplus'), 'Dropbox'), 'warning');
			return false;
		}

		try {
			$dropbox = $this->bootstrap();
		} catch (Exception $e) {
			$updraftplus->log('Dropbox error: '.$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
			$updraftplus->log(sprintf(__('Failed to access %s when deleting (see log file for more)', 'updraftplus'), 'Dropbox'), 'warning');
			return false;
		}
		if (false === $dropbox) return false;

		foreach ($files as $file) {
			$ufile = apply_filters('updraftplus_dropbox_modpath', $file);
			$updraftplus->log("Dropbox: request deletion: $ufile");

			try {
				$dropbox->delete($ufile);
				$file_success = 1;
			} catch (Exception $e) {
				$updraftplus->log('Dropbox error: '.$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
			}

			if (isset($file_success)) {
				$updraftplus->log('Dropbox: delete succeeded');
			} else {
				return false;
			}
		}

	}

	public function download($file) {

		global $updraftplus;

		$opts = $this->get_opts();

		if (empty($opts['tk_request_token'])) {
			$updraftplus->log('You do not appear to be authenticated with Dropbox (4)');
			$updraftplus->log(sprintf(__('You do not appear to be authenticated with %s','updraftplus'), 'Dropbox'), 'error');
			return false;
		}

		try {
			$dropbox = $this->bootstrap();
		} catch (Exception $e) {
			$updraftplus->log('Dropbox error: '.$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
			$updraftplus->log('Dropbox error: '.$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')', 'error');
			return false;
		}
		if (false === $dropbox) return false;

		$updraft_dir = $updraftplus->backups_dir_location();
		$microtime = microtime(true);

		$try_the_other_one = false;

		$ufile = apply_filters('updraftplus_dropbox_modpath', $file);

		try {
			$get = $dropbox->getFile($ufile, $updraft_dir.'/'.$file, null, true);
		} catch (Exception $e) {
			// TODO: Remove this October 2013 (we stored in the wrong place for a while...)
			$try_the_other_one = true;
			$possible_error = $e->getMessage();
			$updraftplus->log('Dropbox error: '.$e);
			$get = false;
		}

		// TODO: Remove this October 2013 (we stored files in the wrong place for a while...)
		if ($try_the_other_one) {
			$dropbox_folder = trailingslashit($opts['folder']);
			try {
				$get = $dropbox->getFile($dropbox_folder.'/'.$file, $updraft_dir.'/'.$file, null, true);
				if (isset($get['response']['body'])) {
					$updraftplus->log("Dropbox: downloaded ".round(strlen($get['response']['body'])/1024,1).' KB');
				}
			}  catch (Exception $e) {
				$updraftplus->log($possible_error, 'error');
				$updraftplus->log($e->getMessage(), 'error');
				$get = false;
			}
		}

		return $get;

	}

	public function config_print() {
		$opts = $this->get_opts();
		?>
			<tr class="updraftplusmethod dropbox">
				<td></td>
				<td>
				<img alt="<?php _e(sprintf(__('%s logo', 'updraftplus'), 'Dropbox')); ?>" src="<?php echo UPDRAFTPLUS_URL.'/images/dropbox-logo.png' ?>">
				<p><em><?php printf(__('%s is a great choice, because UpdraftPlus supports chunked uploads - no matter how big your site is, UpdraftPlus can upload it a little at a time, and not get thwarted by timeouts.','updraftplus'),'Dropbox');?></em></p>
				</td>
			</tr>

			<tr class="updraftplusmethod dropbox">
			<th></th>
			<td>
			<?php
			// Check requirements.
			global $updraftplus_admin;

			$updraftplus_admin->curl_check('Dropbox', false, 'dropbox');
			?>
			</td>
			</tr>

			<?php

				$defmsg = '<tr class="updraftplusmethod dropbox"><td></td><td><strong>'.__('Need to use sub-folders?','updraftplus').'</strong> '.__('Backups are saved in','updraftplus').' apps/UpdraftPlus. '.__('If you back up several sites into the same Dropbox and want to organise with sub-folders, then ','updraftplus').'<a href="https://updraftplus.com/shop/">'.__("there's an add-on for that.",'updraftplus').'</a></td></tr>';

				echo apply_filters('updraftplus_dropbox_extra_config', $defmsg); ?>

			<tr class="updraftplusmethod dropbox">
				<th><?php echo sprintf(__('Authenticate with %s', 'updraftplus'), __('Dropbox', 'updraftplus'));?>:</th>
				<td><p><?php $rt = (empty($opts['tk_request_token'])) ? '' : $opts['tk_request_token']; if (!empty($rt)) echo "<strong>".__('(You appear to be already authenticated).','updraftplus')."</strong>"; ?> <a class="updraft_authlink" href="<?php echo UpdraftPlus_Options::admin_page_url();?>?page=updraftplus&action=updraftmethod-dropbox-auth&updraftplus_dropboxauth=doit"><?php echo sprintf(__('<strong>After</strong> you have saved your settings (by clicking \'Save Changes\' below), then come back here once and click this link to complete authentication with %s.','updraftplus'), __('Dropbox', 'updraftplus'));?></a>
				</p>
				<?php
					if (!empty($rt)) {
						$ownername = empty($opts['ownername']) ? '' : $opts['ownername'];
						if (!empty($ownername)) {
							echo '<br>'.sprintf(__("Account holder's name: %s.", 'updraftplus'), htmlspecialchars($opts['ownername'])).' ';
						}
					}
				?>
				</td>
			</tr>

			<?php
			// Legacy: only show this next setting to old users who had a setting stored
			if (!empty($opts['appkey']) || (defined('UPDRAFTPLUS_CUSTOM_DROPBOX_APP') && UPDRAFTPLUS_CUSTOM_DROPBOX_APP)) {

				$appkey = empty($opts['appkey']) ? '' : $opts['appkey'];
				$secret = empty($opts['secret']) ? '' : $opts['secret'];

			?>

				<tr class="updraftplusmethod dropbox">
					<th>Your Dropbox App Key:</th>
					<td><input type="text" autocomplete="off" style="width:332px" id="updraft_dropbox_appkey" name="updraft_dropbox[appkey]" value="<?php echo esc_attr($appkey) ?>" /></td>
				</tr>
				<tr class="updraftplusmethod dropbox">
					<th>Your Dropbox App Secret:</th>
					<td><input type="text" style="width:332px" id="updraft_dropbox_secret" name="updraft_dropbox[secret]" value="<?php echo esc_attr($secret); ?>" /></td>
				</tr>

			<?php } ?>

		<?php
	}

	public function action_auth() {
		if ( isset( $_GET['oauth_token'] ) ) {
			$this->auth_token();
		} elseif (isset($_GET['updraftplus_dropboxauth'])) {
			// Clear out the existing credentials
			if ('doit' == $_GET['updraftplus_dropboxauth']) {
				$opts = $this->get_opts();
				$opts['tk_request_token'] = '';
				$opts['tk_access_token'] = '';
				$opts['ownername'] = '';
				UpdraftPlus_Options::update_updraft_option('updraft_dropbox', $opts);
			}
			try {
				$this->auth_request();
			} catch (Exception $e) {
				global $updraftplus;
				$updraftplus->log(sprintf(__("%s error: %s", 'updraftplus'), sprintf(__("%s authentication", 'updraftplus'), 'Dropbox'), $e->getMessage()), 'error');
			}
		}
	}

	public function show_authed_admin_warning() {
		global $updraftplus_admin, $updraftplus;

		$dropbox = $this->bootstrap();
		if (false === $dropbox) return false;

		try {
			$accountInfo = $dropbox->accountInfo();
		} catch (Exception $e) {
			$accountinfo_err = sprintf(__("%s error: %s", 'updraftplus'), 'Dropbox', $e->getMessage()).' ('.$e->getCode().')';
		}

		$message = "<strong>".__('Success:','updraftplus').'</strong> '.sprintf(__('you have authenticated your %s account','updraftplus'),'Dropbox');
		# We log, because otherwise people get confused by the most recent log message of 'Parameter not found: oauth_token' and raise support requests
		$updraftplus->log(__('Success:','updraftplus').' '.sprintf(__('you have authenticated your %s account','updraftplus'),'Dropbox'));

		if (empty($accountInfo['code']) || "200" != $accountInfo['code']) {
			$message .= " (".__('though part of the returned information was not as expected - your mileage may vary','updraftplus').")". $accountInfo['code'];
			if (!empty($accountinfo_err)) $message .= "<br>".htmlspecialchars($accountinfo_err);
		} else {
			$body = $accountInfo['body'];
			$message .= ". <br>".sprintf(__('Your %s account name: %s','updraftplus'),'Dropbox', htmlspecialchars($body->display_name));
			$opts = $this->get_opts();
			$opts['ownername'] = '';
			if (!empty($body->display_name)) $opts['ownername'] = $body->display_name;
			UpdraftPlus_Options::update_updraft_option('updraft_dropbox', $opts);

			try {
				$quota_info = $body->quota_info;
				$total_quota = max($quota_info->quota, 1);
				$normal_quota = $quota_info->normal;
				$shared_quota = $quota_info->shared;
				$available_quota =$total_quota - ($normal_quota + $shared_quota);
				$used_perc = round(($normal_quota + $shared_quota)*100/$total_quota, 1);
				$message .= ' <br>'.sprintf(__('Your %s quota usage: %s %% used, %s available','updraftplus'), 'Dropbox', $used_perc, round($available_quota/1048576, 1).' MB');
			} catch (Exception $e) {
			}

		}
		$updraftplus_admin->show_admin_warning($message);

	}

	public function auth_token() {
// 		$opts = $this->get_opts();
// 		$previous_token = empty($opts['tk_request_token']) ? '' : $opts['tk_request_token'];
		$this->bootstrap();
		$opts = $this->get_opts();
		$new_token = empty($opts['tk_request_token']) ? '' : $opts['tk_request_token'];
		if ($new_token) {
			add_action('all_admin_notices', array($this, 'show_authed_admin_warning') );
		}
	}

	// Acquire single-use authorization code
	public function auth_request() {
		$this->bootstrap();
	}

	// This basically reproduces the relevant bits of bootstrap.php from the SDK
	public function bootstrap() {

		if (!empty($this->dropbox_object) && !is_wp_error($this->dropbox_object)) return $this->dropbox_object;

		require_once(UPDRAFTPLUS_DIR.'/includes/Dropbox/API.php');
		require_once(UPDRAFTPLUS_DIR.'/includes/Dropbox/Exception.php');
		require_once(UPDRAFTPLUS_DIR.'/includes/Dropbox/OAuth/Consumer/ConsumerAbstract.php');
		require_once(UPDRAFTPLUS_DIR.'/includes/Dropbox/OAuth/Storage/StorageInterface.php');
		require_once(UPDRAFTPLUS_DIR.'/includes/Dropbox/OAuth/Storage/Encrypter.php');
		require_once(UPDRAFTPLUS_DIR.'/includes/Dropbox/OAuth/Storage/WordPress.php');
		require_once(UPDRAFTPLUS_DIR.'/includes/Dropbox/OAuth/Consumer/Curl.php');
//		require_once(UPDRAFTPLUS_DIR.'/includes/Dropbox/OAuth/Consumer/WordPress.php');

		$opts = $this->get_opts();

		$key = (empty($opts['secret'])) ? '' : $opts['secret'];
		$sec = (empty($opts['appkey'])) ? '' : $opts['appkey'];

		// Set the callback URL
		$callback = UpdraftPlus_Options::admin_page_url().'?page=updraftplus&action=updraftmethod-dropbox-auth';

		// Instantiate the Encrypter and storage objects
		$encrypter = new Dropbox_Encrypter('ThisOneDoesNotMatterBeyondLength');

		// Instantiate the storage
		$storage = new Dropbox_WordPress($encrypter, "tk_", 'updraft_dropbox');

//		WordPress consumer does not yet work
//		$OAuth = new Dropbox_ConsumerWordPress($sec, $key, $storage, $callback);

		// Get the DropBox API access details
		list($d2, $d1) = $this->defaults();
		if (empty($sec)) { $sec = base64_decode($d1); }; if (empty($key)) { $key = base64_decode($d2); }
		$root = 'sandbox';
		if ('dropbox:' == substr($sec, 0, 8)) {
			$sec = substr($sec, 8);
			$root = 'dropbox';
		}

		try {
			$OAuth = new Dropbox_Curl($sec, $key, $storage, $callback);
		} catch (Exception $e) {
			global $updraftplus;
			$updraftplus->log("Dropbox Curl error: ".$e->getMessage());
			$updraftplus->log(sprintf(__("%s error: %s", 'updraftplus'), "Dropbox/Curl", $e->getMessage().' ('.get_class($e).') (line: '.$e->getLine().', file: '.$e->getFile()).')', 'error');
			return false;
		}

		$this->dropbox_object = new UpdraftPlus_Dropbox_API($OAuth, $root);

		return $this->dropbox_object;
	}

}
