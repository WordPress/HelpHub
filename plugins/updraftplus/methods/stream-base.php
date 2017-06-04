<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed');

class UpdraftPlus_AddonStorage_viastream {

	public function __construct($method, $desc) {
		$this->method = $method;
		$this->desc = $desc;
		add_action('updraft_'.$method.'_credentials_test', array($this, 'credentials_test'));
		add_filter('updraft_'.$method.'_upload_files', array($this, 'upload_files'), 10, 2);
		add_filter('updraft_'.$method.'_delete_files', array($this, 'delete_files'), 10, 3);
		add_filter('updraft_'.$method.'_download_file', array($this, 'download_file'), 10, 2);
		add_filter('updraft_'.$method.'_config_print', array($this, 'config_print'));
		add_filter('updraft_'.$method.'_listfiles', array($this, 'listfiles'), 10, 2);
	}

	public function delete_files($ret, $files, $storage_arr = false) {

		global $updraftplus;

		if (is_string($files)) $files = array($files);

		if ($storage_arr) {
			$url = $storage_arr['url'];
		} else {
			$this->bootstrap();
			$options = UpdraftPlus_Options::get_updraft_option('updraft_'.$this->method.'_settings');
			if (!array($options) || !isset($options['url'])) {
				$updraftplus->log('No '.$this->desc.' settings were found');
				$updraftplus->log(sprintf(__('No %s settings were found','updraftplus'), $this->desc), 'error');
				return false;
			}
			$url = untrailingslashit($options['url']);
		}

		$logurl = preg_replace('/:([^\@:]*)\@/', ':(password)@', $url);

		foreach ($files as $file) {
			$updraftplus->log($this->desc.": Delete remote: $logurl/$file");
			if (!unlink("$url/$file")) {
				$updraftplus->log($this->desc.": Delete failed");
			}
		}
		
	}

	public function chunked_upload($file, $url) {

		global $updraftplus;

		$orig_file_size = filesize($file);

		$start_offset = 0;
		if (is_file($url)) {
			$url_size = filesize($url);
			if ($url_size == $orig_file_size) {
				$updraftplus->log($this->desc.": This file has already been successfully uploaded");
				return true;
			} elseif ($url_size > $orig_file_size) {
				$updraftplus->log($this->desc.": A larger file than expected ($url_size > $orig_file_size) already exists");
				return false;
			}
			$updraftplus->log($this->desc.": $url_size bytes already uploaded; resuming");
			$start_offset = $url_size;
		}

		$chunks = floor($orig_file_size / 2097152);
		// There will be a remnant unless the file size was exactly on a 5MB boundary
		if ($orig_file_size % 2097152 > 0 ) $chunks++;

		if (!$fh = fopen($url, 'a')) {
			$updraftplus->log($this->desc.': Failed to open remote file');
			return false;
		}
		if (!$rh = fopen($file, 'rb')) {
			$updraftplus->log($this->desc.': Failed to open local file');
			return false;
		}

		# A hack, to pass information to a modified version of the PEAR library
		if ('webdav' == $this->method) {
			global $updraftplus_webdav_filepath;
			$updraftplus_webdav_filepath = $file;
		}

		$last_time = time();
		for ($i = 1 ; $i <= $chunks; $i++) {

			$chunk_start = ($i-1)*2097152;
			$chunk_end = min($i*2097152-1, $orig_file_size);

			if ($start_offset > $chunk_end) {
				$updraftplus->log($this->desc.": Chunk $i: Already uploaded");
			} else {

				fseek($fh, $chunk_start);
				fseek($rh, $chunk_start);

				$bytes_left = $chunk_end - $chunk_start;
				while ($bytes_left > 0) {
					if ($buf = fread($rh, 131072)) {
						if (fwrite($fh, $buf, strlen($buf))) {
							$bytes_left = $bytes_left - strlen($buf);
							if (time()-$last_time > 15) { $last_time = time(); touch($file); }
						} else {
							$updraftplus->log($this->desc.': '.sprintf(__("Chunk %s: A %s error occurred",'updraftplus'),$i,'write'), 'error');
							return false;
						}
					} else {
						$updraftplus->log($this->desc.': '.sprintf(__("Chunk %s: A %s error occurred",'updraftplus'),$i,'read'), 'error');
						return false;
					}
				}
			}

			$updraftplus->record_uploaded_chunk(round(100*$i/$chunks,1), "$i", $file);

		}

		// N.B. fclose() always returns true for stream wrappers - stream wrappers' return values are ignored - http://php.net/manual/en/streamwrapper.stream-close.php (29-Jan-2015)
		try {
			if (!fclose($fh)) {
				$updraftplus->log($this->desc.': Upload failed (fclose error)');
				$updraftplus->log($this->desc.' '.__('Upload failed', 'updraftplus'), 'error');
				return false;
			}
		} catch (Exception $e) {
			$updraftplus->log($this->desc.': Upload failed (fclose exception; class='.get_class($e).'): '.$e->getMessage());
			$updraftplus->log($this->desc.' '.__('Upload failed', 'updraftplus'), 'error');
			return false;
		}
		fclose($rh);

		return true;

	}

	public function listfiles($x, $match = 'backup_') {

		$storage = $this->bootstrap();
		if (is_wp_error($storage)) return $storage;

		$options = UpdraftPlus_Options::get_updraft_option('updraft_'.$this->method.'_settings');
		if (!array($options) || empty($options['url'])) return new WP_Error('no_settings', sprintf(__('No %s settings were found','updraftplus'), $this->desc));

		$url = trailingslashit($options['url']);

		if (false == ($handle = opendir($url))) return new WP_Error('no_access', sprintf('Failed to gain %s access', $this->desc));

		$results = array();

		while (false !== ($entry = readdir($handle))) {
			if (is_file($url.$entry) && 0 === strpos($entry, $match)) {
				$results[] = array('name' => $entry, 'size' => filesize($url.$entry));
			}
		}

		return $results;

	}

	public function upload_files($ret, $backup_array) {

		global $updraftplus;

		$storage = $this->bootstrap();

		if (is_wp_error($storage)) {
			foreach ($storage->get_error_messages() as $key => $msg) {
				$updraftplus->log($msg);
				$updraftplus->log($msg, 'error');
			}
			return false;
		}

		$options = UpdraftPlus_Options::get_updraft_option('updraft_'.$this->method.'_settings');
		if (!array($options) || !isset($options['url'])) {
			$updraftplus->log('No '.$this->desc.' settings were found');
			$updraftplus->log(sprintf(__('No %s settings were found','updraftplus'), $this->desc), 'error');
			return false;
		}

		$any_failures = false;

		$updraft_dir = untrailingslashit($updraftplus->backups_dir_location());
		$url = untrailingslashit($options['url']);

		foreach ($backup_array as $file) {
			$updraftplus->log($this->desc." upload: attempt: $file");
			if ($this->chunked_upload($updraft_dir.'/'.$file, $url.'/'.$file)) {
				$updraftplus->uploaded_file($file);
			} else {
				$any_failures = true;
				$updraftplus->log('ERROR: '.$this->desc.': Failed to upload file: '.$file);
				$updraftplus->log(__('Error','updraftplus').': '.$this->desc.': '.sprintf(__('Failed to upload to %s','updraftplus'),$file), 'error');
			}
		}

		return ($any_failures) ? null : array('url' => $url);

	}

	public function config_print() {

		$options = UpdraftPlus_Options::get_updraft_option('updraft_'.$this->method.'_settings');
		$url = isset($options['url']) ? htmlspecialchars($options['url']) : '';

		?>
			<tr class="updraftplusmethod <?php echo $this->method;?>">
				<td></td>
				<td><em><?php printf(__('%s is a great choice, because UpdraftPlus supports chunked uploads - no matter how big your site is, UpdraftPlus can upload it a little at a time, and not get thwarted by timeouts.','updraftplus'), $this->desc);?></em></td>
			</tr>

			<?php $this->config_print_middlesection($url); ?>

			<tr class="updraftplusmethod <?php echo $this->method;?>">
			<th></th>
			<td><p><button id="updraft-<?php echo $this->method;?>-test" type="button" class="button-primary updraft-test-button" data-method="<?php echo $this->method;?>" data-method_label="<?php esc_attr_e($this->desc);?>"><?php printf(__('Test %s Settings','updraftplus'), $this->desc);?></button></p></td>
			</tr>

		<?php

	}

	public function download_file($ret, $files) {

		global $updraftplus;

		if (is_string($files)) $files = array($files);

		$storage = $this->bootstrap();
		if (is_wp_error($storage)) {
			foreach ($storage->get_error_messages() as $key => $msg) {
				$updraftplus->log($msg);
				$updraftplus->log($msg, 'error');
			}
			return false;
		}

		$options = UpdraftPlus_Options::get_updraft_option('updraft_'.$this->method.'_settings');

		if (!array($options) || !isset($options['url'])) {
			$updraftplus->log('No '.$this->desc.' settings were found');
			$updraftplus->log(sprintf(__('No %s settings were found','updraftplus'), $this->desc), 'error');
			return false;
		}

		$ret = true;
		foreach ($files as $file) {

			$fullpath = $updraftplus->backups_dir_location().'/'.$file;
			$url = untrailingslashit($options['url']).'/'.$file;

			$start_offset =  (file_exists($fullpath)) ? filesize($fullpath): 0;

			if (@filesize($url) == $start_offset) { $ret = false; continue; }

			if (!$fh = fopen($fullpath, 'a')) {
				$updraftplus->log($this->desc.": Error opening local file: Failed to download: $file");
				$updraftplus->log("$file: ".sprintf(__("%s Error",'updraftplus'), $this->desc).": ".__('Error opening local file: Failed to download','updraftplus'), 'error');
				$ret = false;
				continue;
			}

			if (!$rh = fopen($url, 'rb')) {
				$updraftplus->log($this->desc.": Error opening remote file: Failed to download: $file");
				$updraftplus->log("$file: ".sprintf(__("%s Error",'updraftplus'), $this->desc).": ".__('Error opening remote file: Failed to download','updraftplus'), 'error');
				$ret = false;
				continue;
			}

			if ($start_offset) {
				fseek($fh, $start_offset);
				fseek($rh, $start_offset);
			}

			while (!feof($rh) && $buf = fread($rh, 262144)) {
				if (!fwrite($fh, $buf, strlen($buf))) {
					$updraftplus->log($this->desc." Error: Local write failed: Failed to download: $file");
					$updraftplus->log("$file: ".sprintf(__("%s Error",'updraftplus'), $this->desc).": ".__('Local write failed: Failed to download','updraftplus'), 'error');
					$ret = false;
					continue;
				}
			}
		}

		return $ret;

	}

	public function credentials_test_go($url) {

		$storage = $this->bootstrap();

		if (is_wp_error($storage) || $storage !== true) {
			echo __("Failed",'updraftplus').": ";
			foreach ($storage->get_error_messages() as $key => $msg) {
				echo "$msg\n";
			}
			return;
		}

		$x = @mkdir($url);

		$testfile = $url.'/'.md5(time().rand());
		if (file_put_contents($testfile, 'test')) {
			_e("Success",'updraftplus');
			@unlink($testfile);
		} else {
			_e("Failed: We were not able to place a file in that directory - please check your credentials.",'updraftplus');
		}

		return;
	}
}
