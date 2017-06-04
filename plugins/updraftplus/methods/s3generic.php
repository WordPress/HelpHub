<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed.');

require_once(UPDRAFTPLUS_DIR.'/methods/s3.php');

# Migrate options to new-style storage - Jan 2014
if (!is_array(UpdraftPlus_Options::get_updraft_option('updraft_s3generic')) && '' != UpdraftPlus_Options::get_updraft_option('updraft_s3generic_login', '')) {
	$opts = array(
		'accesskey' => UpdraftPlus_Options::get_updraft_option('updraft_s3generic_login'),
		'secretkey' => UpdraftPlus_Options::get_updraft_option('updraft_s3generic_pass'),
		'path' => UpdraftPlus_Options::get_updraft_option('updraft_s3generic_remote_path'),
		'endpoint' => UpdraftPlus_Options::get_updraft_option('updraft_s3generic_endpoint')
	);
	UpdraftPlus_Options::update_updraft_option('updraft_s3generic', $opts);
	UpdraftPlus_Options::delete_updraft_option('updraft_s3generic_login');
	UpdraftPlus_Options::delete_updraft_option('updraft_s3generic_pass');
	UpdraftPlus_Options::delete_updraft_option('updraft_s3generic_remote_path');
	UpdraftPlus_Options::delete_updraft_option('updraft_s3generic_endpoint');
}

class UpdraftPlus_BackupModule_s3generic extends UpdraftPlus_BackupModule_s3 {

	protected function set_region($obj, $region = '', $bucket_name = '') {
		$config = $this->get_config();
		$endpoint = ($region != '' && $region != 'n/a') ? $region : $config['endpoint'];
		global $updraftplus;
		if ($updraftplus->backup_time) $updraftplus->log("Set endpoint: $endpoint");
		$obj->setEndpoint($endpoint);
	}

	public function get_credentials() {
		return array('updraft_s3generic');
	}

	protected function get_config() {
		global $updraftplus;
		$opts = $updraftplus->get_job_option('updraft_s3generic');
		if (!is_array($opts)) $opts = array('accesskey' => '', 'secretkey' => '', 'path' => '');
		$opts['whoweare'] = 'S3';
		$opts['whoweare_long'] = __('S3 (Compatible)', 'updraftplus');
		$opts['key'] = 's3generic';
		return $opts;
	}

	public function config_print() {
		// 5th parameter = control panel URL
		// 6th = image HTML
		$this->config_print_engine('s3generic', 'S3', __('S3 (Compatible)', 'updraftplus'), 'S3', '', '', true);
	}

	public function credentials_test($posted_settings) {
		$this->credentials_test_engine($this->get_config(), $posted_settings);
	}

}
