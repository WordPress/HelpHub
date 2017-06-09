<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed');

/*
Methods to define when extending this class (can use $this->storage and $this->options where relevant):
do_bootstrap($possible_options_array) # Return a WP_Error object if something goes wrong
do_upload($file, $sourcefile) # Return true/false
do_listfiles($match)
do_delete($file) - return true/false
do_download($file, $fullpath, $start_offset) - return true/false
do_config_print()
do_credentials_test_parameters() - return an array: keys = required _POST parameters; values = description of each
do_credentials_test($testfile, $posted_settings) - return true/false
do_credentials_test_deletefile($testfile, $posted_settings)
*/

# Uses job options: Yes
# Uses single-array storage: Yes

class UpdraftPlus_RemoteStorage_Addons_Base {

	protected $method;
	protected $description;
	protected $storage;
	protected $options;
	private $chunked;

	public function __construct($method, $description, $chunked = true, $test_button = true) {

		$this->method = $method;
		$this->description = $description;
		$this->chunked = $chunked;
		$this->test_button = $test_button;

		add_action('updraft_'.$method."_config_javascript", array($this, 'config_javascript'));
		add_action('updraft_'.$method."_credentials_test", array($this, 'credentials_test'));
		add_filter('updraft_'.$method."_upload_files", array($this, 'upload_files'), 10, 2);
		add_filter('updraft_'.$method."_delete_files", array($this, 'delete_files'), 10, 3);
		add_filter('updraft_'.$method."_download_file", array($this, 'download_file'), 10, 2);
		add_filter('updraft_'.$method."_config_print", array($this, 'config_print'));
		add_filter('updraft_'.$method."_listfiles", array($this, 'listfiles'), 10, 2);
	}

	protected function required_configuration_keys() {
	}

	public function upload_files($ret, $backup_array) {

		global $updraftplus;

		$this->options = $this->get_opts();

		if (!$this->options_exist($this->options)) {
			$updraftplus->log('No '.$this->method.' settings were found');
			$updraftplus->log(sprintf(__('No %s settings were found','updraftplus'), $this->description), 'error');
			return false;
		}

		$storage = $this->bootstrap();
		if (is_wp_error($storage)) return $updraftplus->log_wp_error($storage, false, true);

		$this->storage = $storage;

		$updraft_dir = trailingslashit($updraftplus->backups_dir_location());

		foreach ($backup_array as $file) {
			$updraftplus->log($this->method." upload ".((!empty($this->options['ownername'])) ? '(account owner: '.$this->options['ownername'].')' : '').": attempt: $file");
			try {
				if ($this->do_upload($file, $updraft_dir.$file)) {
					$updraftplus->uploaded_file($file);
				} else {
					$any_failures = true;
					$updraftplus->log('ERROR: '.$this->method.': Failed to upload file: '.$file);
					$updraftplus->log(__('Error','updraftplus').': '.$this->description.': '.sprintf(__('Failed to upload %s','updraftplus'),$file), 'error');
				}
			} catch (Exception $e) {
				$any_failures = true;
				$updraftplus->log('ERROR ('.get_class($e).'): '.$this->method.": $file: Failed to upload file: ".$e->getMessage().' (code: '.$e->getCode().', line: '.$e->getLine().', file: '.$e->getFile().')');
				$updraftplus->log(__('Error','updraftplus').': '.$this->description.': '.sprintf(__('Failed to upload %s','updraftplus'), $file), 'error');
			}
		}

		return (!empty($any_failures)) ? null : true;

	}

	public function listfiles($x, $match = 'backup_') {

		try {

			if (!method_exists($this, 'do_listfiles')) {
				return new WP_Error('no_listing', 'This remote storage method does not support file listing');
			}

			$this->options = $this->get_opts();
			if (!$this->options_exist($this->options)) return new WP_Error('no_settings', sprintf(__('No %s settings were found','updraftplus'), $this->description));

			$this->storage = $this->bootstrap();
			if (is_wp_error($this->storage)) return $this->storage;

			return $this->do_listfiles($match);
		} catch (Exception $e) {
			global $updraftplus;
			$updraftplus->log('ERROR: '.$this->method.": $file: Failed to list files: ".$e->getMessage().' (code: '.$e->getCode().', line: '.$e->getLine().', file: '.$e->getFile().')');
			return new WP_Error('list_failed', $this->description.': '.__('failed to list files', 'updraftplus'));
		}

	}

	public function delete_files($ret, $files, $ignore_it = false) {

		global $updraftplus;

		if (is_string($files)) $files = array($files);

		if (empty($files)) return true;
		if (!method_exists($this, 'do_delete')) {
			$updraftplus->log($this->method.": Delete failed: this storage method does not allow deletions");
			return false;
		}

		if (empty($this->storage)) {

			$this->options = $this->get_opts();
			if (!$this->options_exist($this->options)) {
				$updraftplus->log('No '.$this->method.' settings were found');
				$updraftplus->log(sprintf(__('No %s settings were found','updraftplus'), $this->description), 'error');
				return false;
			}

			$this->storage = $this->bootstrap();
			if (is_wp_error($this->storage)) return $this->storage;

		}

		$ret = true;

		foreach ($files as $file) {
			$updraftplus->log($this->method.": Delete remote: $file");
			try {
				if (!$this->do_delete($file)) {
					$ret = false;
					$updraftplus->log($this->method.": Delete failed");
				} else {
					$updraftplus->log($this->method.": $file: Delete succeeded");
				}
			} catch (Exception $e) {
				$updraftplus->log('ERROR: '.$this->method.": $file: Failed to delete file: ".$e->getMessage().' (code: '.$e->getCode().', line: '.$e->getLine().', file: '.$e->getFile().')');
				$ret = false;
			}
		}
		
		return $ret;
		
	}

	protected function get_opts() {
		global $updraftplus;
		$opts = $updraftplus->get_job_option('updraft_'.$this->method);
		return (is_array($opts)) ? $opts : array();
	}

	public function get_credentials() {
		return array('updraft_'.$this->method, 'updraft_ssl_disableverify', 'updraft_ssl_nossl', 'updraft_ssl_useservercerts');
	}

	public function download_file($ret, $files) {

		global $updraftplus;

		if (is_string($files)) $files = array($files);

		if (empty($files)) return true;
		if (!method_exists($this, 'do_download')) {
			$updraftplus->log($this->method.": Download failed: this storage method does not allow downloading");
			$updraftplus->log($this->description.': '.__('This storage method does not allow downloading','updraftplus'), 'error');
			return false;
		}

		$this->options = $this->get_opts();
		if (!$this->options_exist($this->options)) {
			$updraftplus->log('No '.$this->method.' settings were found');
			$updraftplus->log(sprintf(__('No %s settings were found','updraftplus'), $this->description), 'error');
			return false;
		}

		try {
			$this->storage = $this->bootstrap();
			if (is_wp_error($this->storage)) return $updraftplus->log_wp_error($this->storage, false, true);
		} catch (Exception $e) {
			$ret = false;
			$updraftplus->log('ERROR: '.$this->method.": $files[0]: Failed to download file: ".$e->getMessage().' (code: '.$e->getCode().', line: '.$e->getLine().', file: '.$e->getFile().')');
			$updraftplus->log(__('Error','updraftplus').': '.$this->description.': '.sprintf(__('Failed to download %s','updraftplus'), $files[0]), 'error');
		}

		$ret = true;
		$updraft_dir = untrailingslashit($updraftplus->backups_dir_location());

		foreach ($files as $file) {
			try {
				$fullpath = $updraft_dir.'/'.$file;
				$start_offset =  file_exists($fullpath) ? filesize($fullpath): 0;

				if (false == ($this->do_download($file, $fullpath, $start_offset))) {
					$ret = false;
					$updraftplus->log($this->method." error: failed to download: $file");
					$updraftplus->log("$file: ".sprintf(__("%s Error",'updraftplus'), $this->description).": ".__('Failed to download','updraftplus'), 'error');
				}

			} catch (Exception $e) {
				$ret = false;
				$updraftplus->log('ERROR: '.$this->method.": $file: Failed to download file: ".$e->getMessage().' (code: '.$e->getCode().', line: '.$e->getLine().', file: '.$e->getFile().')');
				$updraftplus->log(__('Error','updraftplus').': '.$this->description.': '.sprintf(__('Failed to download %s','updraftplus'), $file), 'error');
			}
		}

		return $ret;
	}

	public function config_print() {

		$this->options = $this->get_opts();
		$method = $this->method;

		if ($this->chunked) {
		?>
			<tr class="updraftplusmethod <?php echo $method; ?>">
				<td></td>
				<td><p><em><?php printf(__('%s is a great choice, because UpdraftPlus supports chunked uploads - no matter how big your site is, UpdraftPlus can upload it a little at a time, and not get thwarted by timeouts.','updraftplus'), $this->description);?></em></p></td>
			</tr>
		<?php
		}
			if (method_exists($this, 'do_config_print')) $this->do_config_print($this->options);

			if (!$this->test_button || (method_exists($this, 'should_print_test_button') && !$this->should_print_test_button())) return;

		?>

		<tr class="updraftplusmethod <?php echo $method;?>">
		<th></th>
		<td><p><button id="updraft-<?php echo $method;?>-test" type="button" class="button-primary updraft-test-button" data-method="<?php echo $method;?>" data-method_label="<?php esc_attr_e($this->description);?>"><?php printf(__('Test %s Settings','updraftplus'), $this->description);?></button></p></td>
		</tr>

		<?php

	}

	public function config_javascript() {
		$this->do_config_javascript();
	}
	
	protected function do_config_javascript() {
	}
	
	protected function options_exist($opts) {
		if (is_array($opts) && !empty($opts)) return true;
		return false;
	}

	public function bootstrap($opts = false, $connect = true) {
		if (false === $opts) $opts = $this->options;
		#  Be careful of checking empty($opts) here - some storage methods may have no options until the OAuth token has been obtained
		if ($connect && !$this->options_exist($opts)) return new WP_Error('no_settings', sprintf(__('No %s settings were found', 'updraftplus'), $this->description));
		if (!empty($this->storage) && !is_wp_error($this->storage)) return $this->storage;
		return $this->do_bootstrap($opts, $connect);
	}

	public function credentials_test($posted_settings) {
	
		global $updraftplus;

		$required_test_parameters = $this->do_credentials_test_parameters();

		foreach ($required_test_parameters as $param => $descrip) {
			if (empty($posted_settings[$param])) {
				printf(__("Failure: No %s was given.",'updraftplus'), $descrip);
				return;
			}
		}

		$this->storage = $this->bootstrap($posted_settings);
		if (is_wp_error($this->storage)) {
			echo __("Failed", 'updraftplus').": ";
			foreach ($this->storage->get_error_messages() as $key => $msg) { echo "$msg\n"; }
			return;
		}

		$testfile = md5(time().rand()).'.txt';
		if ($this->do_credentials_test($testfile, $posted_settings)) {
			_e('Success', 'updraftplus');
			$this->do_credentials_test_deletefile($testfile, $posted_settings);
		} else {
			_e("Failed: We were not able to place a file in that directory - please check your credentials.", 'updraftplus');
		}

	}

}
