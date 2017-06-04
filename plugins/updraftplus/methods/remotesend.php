<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed');

/*
do_bootstrap($possible_options_array, $connect = true) # Return a WP_Error object if something goes wrong
do_upload($file) # Return true/false
do_listfiles($match)
do_delete($file) - return true/false
do_download($file, $fullpath, $start_offset) - return true/false
do_config_print()
do_config_javascript()
do_credentials_test_parameters() - return an array: keys = required _POST parameters; values = description of each
do_credentials_test($testfile) - return true/false
do_credentials_test_deletefile($testfile)
*/

// TODO: Need to deal with the issue of squillions of downloaders showing in a restore operation. Best way would be to never open the downloaders at all - make an AJAX call to see which are actually needed. (Failing that, a back-off mechanism).

if (!class_exists('UpdraftPlus_BackupModule_ViaAddon')) require_once(UPDRAFTPLUS_DIR.'/methods/viaaddon-base.php');
class UpdraftPlus_BackupModule_remotesend extends UpdraftPlus_BackupModule_ViaAddon {
	public function __construct() {
		parent::__construct('remotesend', 'Remote send', '5.2.4');
	}
}

if (!class_exists('UpdraftPlus_RemoteStorage_Addons_Base')) require_once(UPDRAFTPLUS_DIR.'/methods/addon-base.php');
class UpdraftPlus_Addons_RemoteStorage_remotesend extends UpdraftPlus_RemoteStorage_Addons_Base {

	private $default_chunk_size;

	public function __construct() {
		// 2MB. After being b64-encoded twice, this is ~ 3.7MB = 113 seconds on 32KB/s uplink
		$this->default_chunk_size = (defined('UPDRAFTPLUS_REMOTESEND_DEFAULT_CHUNK_BYTES') && is_numeric(UPDRAFTPLUS_REMOTESEND_DEFAULT_CHUNK_BYTES) && UPDRAFTPLUS_REMOTESEND_DEFAULT_CHUNK_BYTES >= 16384) ? UPDRAFTPLUS_REMOTESEND_DEFAULT_CHUNK_BYTES : 2097152;

		# 3rd parameter: chunking? 4th: Test button?
		parent::__construct('remotesend', 'Remote send', false, false);
	}
	
	public function get_credentials() {
		return array('updraft_ssl_disableverify', 'updraft_ssl_nossl', 'updraft_ssl_useservercerts');
	}

	public function do_upload($file, $from) {

		global $updraftplus;
		$opts = $this->options;
		
		try {
			$service = $this->bootstrap();
			if (is_wp_error($service)) throw new Exception($service->get_error_message());
			if (!is_object($service)) throw new Exception("RPC service error");
		} catch (Exception $e) {
			$message = $e->getMessage().' ('.get_class($e).') (line: '.$e->getLine().', file: '.$e->getFile().')';
			$updraftplus->log("RPC service error: ".$message);
			$updraftplus->log($message, 'error');
			return false;
		}
		
		$filesize = filesize($from);
		$this->remotesend_file_size = $filesize;

		// See what the sending side currently has. This also serves as a ping. For that reason, we don't try/catch - we let them be caught at the next level up.

		$get_remote_size = $this->send_message('get_file_status', $file, 30);
		
		if (is_wp_error($get_remote_size)) {
			throw new Exception($get_remote_size->get_error_message().' ('.$get_remote_size->get_error_code().')');
		}

		if (!is_array($get_remote_size) || empty($get_remote_size['response'])) throw new Exception(__('Unexpected response:', 'updraftplus').' '.serialize($get_remote_size));

		if ('error' == $get_remote_size['response']) {
			$msg = $get_remote_size['data'];
			// Could interpret the codes to get more interesting messages directly to the user
			throw new Exception(__('Error:', 'updraftplus').' '.$msg);
		}

		if (empty($get_remote_size['data']) || !isset($get_remote_size['data']['size']) || 'file_status' != $get_remote_size['response']) throw new Exception(__('Unexpected response:', 'updraftplus').' '.serialize($get_remote_size));

		// Possible statuses: 0=temporary file (or not present), 1=file
		if (empty($get_remote_size['data'])) {
			$remote_size = 0;
			$remote_status = 0;
		} else {
			$remote_size = (int)$get_remote_size['data']['size'];
			$remote_status = $get_remote_size['data']['status'];
		}

		$updraftplus->log("$file: existing size: ".$remote_size);
		
		// Perhaps it already exists? (if we didn't get the final confirmation)
		if ($remote_size >= $filesize && $remote_status) {
			$updraftplus->log("$file: already uploaded");
			return true;
		}

		// Length = 44 (max = 45)
		$this->remote_sent_defchunk_transient = 'ud_rsenddck_'.md5($opts['name_indicator']);

		$default_chunk_size = $this->default_chunk_size;

		if (false !== ($saved_default_chunk_size = get_transient($this->remote_sent_defchunk_transient)) && is_numeric($saved_default_chunk_size) && $saved_default_chunk_size > 16384) {
			// Don't go lower than 256KB for the *default*. (The job size can go lower).
			$default_chunk_size = max($saved_default_chunk_size, 262144);
		}

		$this->remotesend_use_chunk_size = $updraftplus->jobdata_get('remotesend_chunksize', $default_chunk_size);

		if (0 == $remote_size && $this->remotesend_use_chunk_size == $this->default_chunk_size && $updraftplus->current_resumption - max($updraftplus->jobdata_get('uploaded_lastreset'), 1) > 1) {
			$new_chunk_size = floor($this->remotesend_use_chunk_size / 2);
			$updraftplus->log("No uploading activity has been detected for a while; reducing chunk size in case a timeout was occurring. New chunk size: ".$new_chunk_size);
			$this->remotesend_set_new_chunk_size($new_chunk_size);
		}

		try {
			if (false != ($handle = fopen($from, 'rb'))) {

				$this->remotesend_uploaded_size = $remote_size;
				$ret = $updraftplus->chunked_upload($this, $file, $this->method."://".trailingslashit($opts['url']).$file, $this->description, $this->remotesend_use_chunk_size, $remote_size, true);

				fclose($handle);

				return $ret;
			} else {
				throw new Exception("Failed to open file for reading: $from");
			}
		} catch (Exception $e) {
			$updraftplus->log($this->description." upload: error (".get_class($e)."): ($file) (".$e->getMessage().") (line: ".$e->getLine().', file: '.$e->getFile().')');
			if (!empty($this->remotesend_chunked_wp_error) && is_wp_error($this->remotesend_chunked_wp_error)) {
				$updraftplus->log("Exception data: ".base64_encode(serialize($this->remotesend_chunked_wp_error->get_error_data())));
			}
			return false;
		}
		
		return true;
	}

	// Return: boolean|(int)1
	public function chunked_upload($file, $fp, $chunk_index, $upload_size, $upload_start, $upload_end) {

		// Already done? Return 1 (which means the same as true, but also "don't log it")
		// Condition used to be "$upload_start < $this->remotesend_uploaded_size" - but this assumed that the other side never failed after writing only some bytes to disk
		// $upload_end is the byte offset of the final byte. Therefore, add 1 onto it when comparing with a size.
		if ($upload_end+1 <= $this->remotesend_uploaded_size) return 1;

		global $updraftplus;

		$service = $this->storage;
		
		$chunk = fread($fp, $upload_size);

		if (false === $chunk) {
			$updraftplus->log($this->description." upload: $file: fread failure ($upload_start)");
			return false;
		}

		$try_again = false;

		$data = array('file' => $file, 'data' => base64_encode($chunk), 'start' => $upload_start);

		if ($upload_end+1 >= $this->remotesend_file_size) {
			$data['last_chunk'] = true;
			if ('' != ($label = $updraftplus->jobdata_get('label'))) $data['label'] = $label;
		}

		// ~ 3.7MB of data typically - timeout allows for 15.9KB/s
		try {
			$put_chunk = $this->send_message('send_chunk', $data, 240);
		} catch (Exception $e) {
			$try_again = true;
		}

		if ($try_again || is_wp_error($put_chunk)) {
			// 413 - Request entity too large
			// Don't go lower than 64KB chunks (i.e. 128KB/2)
			// Note that mod_security can be configured to 'helpfully' decides to replace HTTP error codes + messages with a simple serving up of the site home page, which means that we need to also guess about other reasons this condition may have occurred other than detecting via the direct 413 code. Of course, our search for wp-includes|wp-content|WordPress|/themes/ would be thwarted by someone who tries to hide their WP. The /themes/ is pretty hard to hide, as the theme directory is always <wp-content-dir>/themes - even if you moved your wp-content. The point though is just a 'best effort' - this doesn't have to be infallible.
			if (is_wp_error($put_chunk)) {
			
				$error_data = $put_chunk->get_error_data();
			
				$is_413 = ('unexpected_http_code' == $put_chunk->get_error_code() && (
						413 == $error_data
						|| (is_array($error_data) && !empty($error_data['response']['code']) && 413 == $error_data['response']['code'])
					)
				);
			
				if ($this->remotesend_use_chunk_size >= 131072 && ($is_413 || ('response_not_understood' == $put_chunk->get_error_code() && (strpos($error_data, 'wp-includes') !== false || strpos($error_data, 'wp-content') !== false || strpos($error_data, 'WordPress') !== false || strpos($put_chunk->get_error_data(), '/themes/') !== false)))) {
					if (1 == $chunk_index) {
						$new_chunk_size = floor($this->remotesend_use_chunk_size / 2);
						$this->remotesend_set_new_chunk_size($new_chunk_size);
						$log_msg = "Returned WP_Error: code=".$put_chunk->get_error_code();
						if ('unexpected_http_code' == $put_chunk->get_error_code()) $log_msg .= ' ('.$error_data.')';
						$log_msg .= " - reducing chunk size to: ".$new_chunk_size;
						$updraftplus->log($log_msg);
						return new WP_Error('reduce_chunk_size', 'HTTP 413 or possibly equivalent condition on first chunk - should reduce chunk size', $new_chunk_size);
					} elseif ($this->remotesend_use_chunk_size >= 131072 && $is_413) {
						// In this limited case, where we got a 413 but the chunk is not number 1, our algorithm/architecture doesn't allow us to just resume immediately with a new chunk size. However, we can just have UD reduce the chunk size on its next resumption.
						$new_chunk_size = floor($this->remotesend_use_chunk_size / 2);
						$this->remotesend_set_new_chunk_size($new_chunk_size);
						$log_msg = "Returned WP_Error: code=".$put_chunk->get_error_code();
						$log_msg .= " - reducing chunk size to: ".$new_chunk_size." and then scheduling resumption/aborting";
						$updraftplus->log($log_msg);
						$updraftplus->reschedule(50);
						$updraftplus->record_still_alive();
						die;
						
					}
				}
			}
			$put_chunk = $this->send_message('send_chunk', $data, 240);
		}

		if (is_wp_error($put_chunk)) {
			// The exception handler is within this class. So we can store the data.
			$this->remotesend_chunked_wp_error = $put_chunk;
			throw new Exception($put_chunk->get_error_message().' ('.$put_chunk->get_error_code().')');
		}

		if (!is_array($put_chunk) || empty($put_chunk['response'])) throw new Exception(__('Unexpected response:','updraftplus').' '.serialize($put_chunk));

		if ('error' == $put_chunk['response']) {
			$msg = $put_chunk['data'];
			// Could interpret the codes to get more interesting messages directly to the user
			// The textual prefixes here were added after 1.12.5 - hence optional when parsing
			if (preg_match('/^invalid_start_too_big:(start=)?(\d+),(existing_size=)?(\d+)/', $msg, $matches)) {
				$attempted_start = $matches[1];
				$existing_size = $matches[2];
				if ($existing_size < $this->remotesend_uploaded_size) {
					// The file on the remote system seems to have shrunk. Could be some load-balancing system with a distributed filesystem that is only eventually consistent.
					return new WP_Error('try_again', 'File on remote system is smaller than expected - perhaps an eventually-consistent filesystem (wait and retry)');
				}
			}
			throw new Exception(__('Error:','updraftplus').' '.$msg);
		}

		if ('file_status' != $put_chunk['response']) throw new Exception(__('Unexpected response:','updraftplus').' '.serialize($put_chunk));

		// Possible statuses: 0=temporary file (or not present), 1=file
		if (empty($put_chunk['data']) || !is_array($put_chunk['data'])) {
			$updraftplus->log("Unexpected response when putting chunk $chunk_index: ".serialize($put_chunk));
			return false;
		} else {
			$remote_size = (int)$put_chunk['data']['size'];
			$remote_status = $put_chunk['data']['status'];
			$this->remotesend_uploaded_size = $remote_size;
		}

		return true;

	}

	private function remotesend_set_new_chunk_size($new_chunk_size) {
		global $updraftplus;
		$this->remotesend_use_chunk_size = $new_chunk_size;
		$updraftplus->jobdata_set('remotesend_chunksize', $new_chunk_size);
		// Save, so that we don't have to cycle through the illegitimate/larger chunk sizes each time. Set the transient expiry to 120 days, in case they change hosting/configuration - so that we're not stuck on the lower size forever.
		set_transient($this->remote_sent_defchunk_transient, $new_chunk_size, 86400*120);
	}

	private function send_message($message, $data = null, $timeout = 30) {
		$response = $this->storage->send_message($message, $data, $timeout);
		if (is_array($response) && !empty($response['data']) && is_array($response['data']) && !empty($response['data']['php_events']) && !empty($response['data']['previous_data'])) {
			global $updraftplus;
			foreach ($response['data']['php_events'] as $logline) {
				$updraftplus->log("From remote side: ".$logline);
			}
			$response['data'] = $response['data']['previous_data'];
		}
		return $response;
	}

	public function do_bootstrap($opts, $connect = true) {
	
		global $updraftplus;
	
		if (!class_exists('UpdraftPlus_Remote_Communications')) require_once(apply_filters('updraftplus_class_udrpc_path', UPDRAFTPLUS_DIR.'/includes/class-udrpc.php', $updraftplus->version));

		$opts = $this->get_opts();

		try {
			$ud_rpc = new UpdraftPlus_Remote_Communications($opts['name_indicator']);
			// Enforce the legacy communications protocol (which is only suitable for when only one side only sends, and the other only receives - which is what we happen to do)
			$ud_rpc->set_message_format(1);
			$ud_rpc->set_key_local($opts['key']);
			$ud_rpc->set_destination_url($opts['url']);
			$ud_rpc->activate_replay_protection();
		} catch (Exception $e) {
			return new WP_Error('rpc_failure', "Commmunications failure: ".$e->getMessage().' (line: '.$e->getLine().', file: '.$e->getFile().')');
		}

		$this->storage = $ud_rpc;
		
		return $this->storage;
	}

	public function options_exist($opts) {
		if (is_array($opts) && !empty($opts['url']) && !empty($opts['name_indicator']) && !empty($opts['key'])) return true;
		return false;
	}

	public function get_opts() {
		global $updraftplus;
		$opts = $updraftplus->jobdata_get('remotesend_info');
		return (is_array($opts)) ? $opts : array();
	}

	// do_listfiles(), do_download(), do_delete() : the absence of any method here means that the parent will correctly throw an error
	
}

$updraftplus_addons_remotesend = new UpdraftPlus_Addons_RemoteStorage_remotesend;
