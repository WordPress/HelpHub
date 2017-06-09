<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No access.');

/*
	- A container for all the RPC commands implemented. Commands map exactly onto method names (and hence this class should not implement anything else, beyond the constructor, and private methods)
	- Return format is array('response' => (string - a code), 'data' => (mixed));
	
	RPC commands are not allowed to begin with an underscore. So, any private methods can be prefixed with an underscore.
	
*/

if (!class_exists('UpdraftCentral_Commands')) require_once('commands.php');

class UpdraftPlus_RemoteControl_Commands extends UpdraftCentral_Commands {

	public function get_download_status($items) {
		if (false === ($updraftplus_admin = $this->_load_ud_admin())) return $this->_generic_error_response('no_updraftplus');
		
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');
	
		if (!is_array($items)) $items = array();

		return $this->_response($updraftplus_admin->get_download_statuses($items));
	
	}
	
	public function downloader($downloader_params) {

		if (false === ($updraftplus_admin = $this->_load_ud_admin())) return $this->_generic_error_response('no_updraftplus');
		
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');
	
		$findex = $downloader_params['findex'];
		$type = $downloader_params['type'];
		$timestamp = $downloader_params['timestamp'];
		// Valid stages: 2='spool the data'|'delete'='delete local copy'|anything else='make sure it is present'
		$stage = empty($downloader_params['stage']) ? false : $downloader_params['stage'];
	
		// This may, or may not, return, depending upon whether the files are already downloaded
		// The response is usually an array with key 'result', and values deleted|downloaded|needs_download|download_failed
		$response = $updraftplus_admin->do_updraft_download_backup($findex, $type, $timestamp, $stage, array($this, '_updraftplus_background_operation_started'));
	
		if (is_array($response)) {
			$response['request'] = $downloader_params;
		}
	
		return $this->_response($response);
	}
	
	public function delete_downloaded($set_info) {
		$set_info['stage'] = 'delete';
		return $this->downloader($set_info);
	}
	
	public function backup_progress($params) {
	
		if (false === ($updraftplus_admin = $this->_load_ud_admin())) return $this->_generic_error_response('no_updraftplus');
		
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');
		
		$request = array(
			'thisjobonly' => $params['job_id']
		);
		
		$activejobs_list = $updraftplus_admin->get_activejobs_list($request);
		
		return $this->_response($activejobs_list);
	
	}
	
	public function backupnow($params) {
		
		if (false === ($updraftplus_admin = $this->_load_ud_admin())) return $this->_generic_error_response('no_updraftplus');
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');

		$updraftplus_admin->request_backupnow($params, array($this, '_updraftplus_background_operation_started'));
		
		// Control returns when the backup finished; but, the browser connection should have been closed before
		die;
	}
	
	public function _updraftplus_background_operation_started($msg) {

		// Under-the-hood hackery to allow the browser connection to be closed, and the backup/download to continue
		
		$rpc_response = $this->rc->return_rpc_message($this->_response($msg));
		
		$data = isset($rpc_response['data']) ? $rpc_response['data'] : null;

		$ud_rpc = $this->rc->get_current_udrpc();
		
		$encoded = json_encode($ud_rpc->create_message($rpc_response['response'], $data, true));
		
		$this->_load_ud()->close_browser_connection($encoded);

	}
	
	private function _load_ud() {
		global $updraftplus;
		return is_a($updraftplus, 'UpdraftPlus') ? $updraftplus : false;
	}
	
	private function _load_ud_admin() {
		if (!defined('UPDRAFTPLUS_DIR') || !is_file(UPDRAFTPLUS_DIR.'/admin.php')) return false;
		require_once(UPDRAFTPLUS_DIR.'/admin.php');
		global $updraftplus_admin;
		return $updraftplus_admin;
	}
	
	public function get_log($job_id) {
	
		if (false === ($updraftplus = $this->_load_ud())) return $this->_generic_error_response('no_updraftplus');
	
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');
		
		if (!preg_match("/^[0-9a-f]{12}$/", $job_id)) return $this->_generic_error_response('updraftplus_permission_invalid_jobid');
		
		$updraft_dir = $updraftplus->backups_dir_location();
		$log_file = $updraft_dir.'/log.'.$job_id.'.txt';
		
		if (is_readable($log_file)) {
			return $this->_response(array('log' => file_get_contents($log_file)));
		} else {
			return $this->_generic_error_response('updraftplus_unreadable_log');
		}
	
	}
	
	public function activejobs_delete($job_id) {
	
		if (false === ($updraftplus_admin = $this->_load_ud_admin())) return $this->_generic_error_response('no_updraftplus');
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');

		$delete = $updraftplus_admin->activejobs_delete((string)$job_id);
		return $this->_response($delete);

	}
	
	public function deleteset($what) {
	
		if (false === ($updraftplus_admin = $this->_load_ud_admin()) || false === ($updraftplus = $this->_load_ud())) return $this->_generic_error_response('no_updraftplus');
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');
	
 		$results = $updraftplus_admin->delete_set($what);
	
		$get_history_opts = isset($what['get_history_opts']) ? $what['get_history_opts'] : array();
	
		$history = $updraftplus_admin->settings_downloading_and_restoring(UpdraftPlus_Options::get_updraft_option('updraft_backup_history'), true, $get_history_opts);

		$results['history'] = $history;
	
		return $this->_response($results);
	
	}
	
	public function rescan($what) {

		if (false === ($updraftplus_admin = $this->_load_ud_admin()) || false === ($updraftplus = $this->_load_ud())) return $this->_generic_error_response('no_updraftplus');
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');
		
		$remotescan = ('remotescan' == $what);
		$rescan = ($remotescan || 'rescan' == $what);
		
		$history_status = $updraftplus_admin->get_history_status($rescan, $remotescan);

		return $this->_response($history_status);
		
	}
	
	public function get_settings($options) {
		if (false === ($updraftplus_admin = $this->_load_ud_admin()) || false === ($updraftplus = $this->_load_ud())) return $this->_generic_error_response('no_updraftplus');
		
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');
		
		ob_start();
		$updraftplus_admin->settings_formcontents($options);
		$output = ob_get_contents();
		ob_end_clean();
		
		return $this->_response(array(
			'settings' => $output,
			'meta' => apply_filters('updraftplus_get_settings_meta', array()),
		));
		
	}
	
	public function test_storage_settings($test_data) {
	
		if (false === ($updraftplus_admin = $this->_load_ud_admin()) || false === ($updraftplus = $this->_load_ud())) return $this->_generic_error_response('no_updraftplus');
		
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');
	
		ob_start();
		$updraftplus_admin->do_credentials_test($test_data);
		$output = ob_get_contents();
		ob_end_clean();
	
		return $this->_response(array(
			'output' => $output,
		));
	
	}
	
	public function extradb_testconnection($info) {
	
		if (false === ($updraftplus_admin = $this->_load_ud_admin()) || false === ($updraftplus = $this->_load_ud())) return $this->_generic_error_response('no_updraftplus');
		
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');
	
		$results = apply_filters('updraft_extradb_testconnection_go', array(), $info);
	
		return $this->_response($results);
	
	}
	
	private function _get_vault() {
		require_once(UPDRAFTPLUS_DIR.'/methods/updraftvault.php');
		$vault = new UpdraftPlus_BackupModule_updraftvault();
		return $vault;
	}
	
	public function vault_connect($credentials) {
	
		if (false === ($updraftplus_admin = $this->_load_ud_admin()) || false === ($updraftplus = $this->_load_ud())) return $this->_generic_error_response('no_updraftplus');
		
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');

		$results = $this->_get_vault()->ajax_vault_connect(false, $credentials);
	
		return $this->_response($results);
	
	}
	
	public function vault_disconnect() {
	
		if (false === ($updraftplus_admin = $this->_load_ud_admin()) || false === ($updraftplus = $this->_load_ud())) return $this->_generic_error_response('no_updraftplus');
		
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');
	
		$results = (array)$this->_get_vault()->ajax_vault_disconnect(false);

		return $this->_response($results);
	
	}
	
	public function vault_recount_quota() {
		if (false === ($updraftplus_admin = $this->_load_ud_admin()) || false === ($updraftplus = $this->_load_ud())) return $this->_generic_error_response('no_updraftplus');
		
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');
	
		$results = $this->_get_vault()->ajax_vault_recountquota(false);
	
		return $this->_response($results);
	}
	
	public function save_settings($settings) {
	
		if (false === ($updraftplus_admin = $this->_load_ud_admin()) || false === ($updraftplus = $this->_load_ud())) return $this->_generic_error_response('no_updraftplus');
		
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');
	
		if (empty($settings) || !is_string($settings)) return $this->_generic_error_response('invalid_settings');

		parse_str($settings, $settings_as_array);
		
		$results = $updraftplus_admin->save_settings($settings_as_array);

		return $this->_response($results);
	
	}
	
	public function s3_newuser($data) {
		if (false === ($updraftplus_admin = $this->_load_ud_admin()) || false === ($updraftplus = $this->_load_ud())) return $this->_generic_error_response('no_updraftplus');
		
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');
		$results = apply_filters('updraft_s3_newuser_go', array(), $data);
		
		return $this->_response($results);
	}
	
	public function get_fragment($fragment) {
	
		if (false === ($updraftplus_admin = $this->_load_ud_admin()) || false === ($updraftplus = $this->_load_ud())) return $this->_generic_error_response('no_updraftplus');
		
		if (!UpdraftPlus_Options::user_can_manage()) return $this->_generic_error_response('updraftplus_permission_denied');

		if (is_array($fragment)) {
			$data = $fragment['data'];
			$fragment = $fragment['fragment'];
		}
		
		$error = false;
		switch ($fragment) {
			case 's3_new_api_user_form':
				ob_start();
				do_action('updraft_s3_print_new_api_user_form', false);
				$output = ob_get_contents();
				ob_end_clean();
				break;
			case 'backupnow_modal_contents':
				$updraft_dir = $updraftplus->backups_dir_location();
				if (!$updraftplus->really_is_writable($updraft_dir)) {
					$output = array('error' => true, 'html' => __("The 'Backup Now' button is disabled as your backup directory is not writable (go to the 'Settings' tab and find the relevant option).", 'updraftplus'));
				} else {
					$output = array('html' => $updraftplus_admin->backupnow_modal_contents());
				}
			break;
			case 'panel_download_and_restore':
				$backup_history = UpdraftPlus_Options::get_updraft_option('updraft_backup_history');
				if (empty($backup_history)) {
					$updraftplus->rebuild_backup_history();
					$backup_history = UpdraftPlus_Options::get_updraft_option('updraft_backup_history');
				}
				$backup_history = is_array($backup_history) ? $backup_history : array();
				
				$output = $updraftplus_admin->settings_downloading_and_restoring($backup_history, true, $data);
			break;
			case 'disk_usage':
				$output =  $updraftplus_admin->get_disk_space_used($data);
			break;
			default:
			// We just return a code - translation is done on the other side
			$output = 'ud_get_fragment_could_not_return';
			$error = true;
			break;
		}
		
		if (empty($error)) {
			return $this->_response(array(
				'output' => $output,
			));
		} else {
			return $this->_generic_error_response('get_fragment_error', $output);
		}
		
	}
		
}
