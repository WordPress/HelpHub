<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access.');

# Converted to job_options: yes
# Converted to array options: yes

if (version_compare(phpversion(), '5.3.3', '>=') && (!defined('UPDRAFTPLUS_CLOUDFILES_USEOLDSDK') || UPDRAFTPLUS_CLOUDFILES_USEOLDSDK != true)) {
	require_once(UPDRAFTPLUS_DIR.'/methods/cloudfiles-new.php');
	class UpdraftPlus_BackupModule_cloudfiles extends UpdraftPlus_BackupModule_cloudfiles_opencloudsdk { }
} else {
	class UpdraftPlus_BackupModule_cloudfiles extends UpdraftPlus_BackupModule_cloudfiles_oldsdk { }
}

# Migrate options to new-style storage - Dec 2013
if (!is_array(UpdraftPlus_Options::get_updraft_option('updraft_cloudfiles')) && '' != UpdraftPlus_Options::get_updraft_option('updraft_cloudfiles_user', '')) {
	$opts = array(
		'user' => UpdraftPlus_Options::get_updraft_option('updraft_cloudfiles_user'),
		'apikey' => UpdraftPlus_Options::get_updraft_option('updraft_cloudfiles_apikey'),
		'path' => UpdraftPlus_Options::get_updraft_option('updraft_cloudfiles_path'),
		'authurl' => UpdraftPlus_Options::get_updraft_option('updraft_cloudfiles_authurl'),
		'region' => UpdraftPlus_Options::get_updraft_option('updraft_cloudfiles_region')
	);
	UpdraftPlus_Options::update_updraft_option('updraft_cloudfiles', $opts);
	UpdraftPlus_Options::delete_updraft_option('updraft_cloudfiles_user');
	UpdraftPlus_Options::delete_updraft_option('updraft_cloudfiles_apikey');
	UpdraftPlus_Options::delete_updraft_option('updraft_cloudfiles_path');
	UpdraftPlus_Options::delete_updraft_option('updraft_cloudfiles_authurl');
	UpdraftPlus_Options::delete_updraft_option('updraft_cloudfiles_region');
}

# Old SDK
class UpdraftPlus_BackupModule_cloudfiles_oldsdk {

	private $cloudfiles_object;

	// This function does not catch any exceptions - that should be done by the caller
	private function getCF($user, $apikey, $authurl, $useservercerts = false) {
		
		global $updraftplus;

		if (!class_exists('UpdraftPlus_CF_Authentication')) require_once(UPDRAFTPLUS_DIR.'/includes/cloudfiles/cloudfiles.php');

		if (!defined('UPDRAFTPLUS_SSL_DISABLEVERIFY')) define('UPDRAFTPLUS_SSL_DISABLEVERIFY', UpdraftPlus_Options::get_updraft_option('updraft_ssl_disableverify'));

		$auth = new UpdraftPlus_CF_Authentication($user, trim($apikey), NULL, $authurl);

		$updraftplus->log("Cloud Files authentication URL: $authurl");

		$auth->authenticate();

		$conn = new UpdraftPlus_CF_Connection($auth);

		if (!$useservercerts) $conn->ssl_use_cabundle(UPDRAFTPLUS_DIR.'/includes/cacert.pem');

		return $conn;

	}

	public function get_credentials() {
		return array('updraft_cloudfiles');
	}

	public function get_opts() {
		global $updraftplus;
		$opts = $updraftplus->get_job_option('updraft_cloudfiles');
		if (!is_array($opts)) $opts = array('user' => '', 'authurl' => 'https://auth.api.rackspacecloud.com', 'apikey' => '', 'path' => '');
		if (empty($opts['authurl'])) $opts['authurl'] = 'https://auth.api.rackspacecloud.com';
		if (empty($opts['region'])) $opts['region'] = null;
		return $opts;
	}

	public function backup($backup_array) {

		global $updraftplus, $updraftplus_backup;

		$opts = $this->get_opts();

		$updraft_dir = $updraftplus->backups_dir_location().'/';

// 		if (preg_match("#^([^/]+)/(.*)$#", $path, $bmatches)) {
// 			$container = $bmatches[1];
// 			$path = $bmatches[2];
// 		} else {
// 			$container = $path;
// 			$path = "";
// 		}
		$container = $opts['path'];

		try {
			$conn = $this->getCF($opts['user'], $opts['apikey'], $opts['authurl'], UpdraftPlus_Options::get_updraft_option('updraft_ssl_useservercerts'));
			$container_object = $conn->create_container($container);
		} catch(AuthenticationException $e) {
			$updraftplus->log('Cloud Files authentication failed ('.$e->getMessage().')');
			$updraftplus->log(sprintf(__('%s authentication failed','updraftplus'),'Cloud Files').' ('.$e->getMessage().')', 'error');
			return false;
		} catch(NoSuchAccountException $s) {
			$updraftplus->log('Cloud Files authentication failed ('.$e->getMessage().')');
			$updraftplus->log(sprintf(__('%s authentication failed','updraftplus'),'Cloud Files').' ('.$e->getMessage().')', 'error');
			return false;
		} catch (Exception $e) {
			$updraftplus->log('Cloud Files error - failed to create and access the container ('.$e->getMessage().')');
			$updraftplus->log(__('Cloud Files error - failed to create and access the container', 'updraftplus').' ('.$e->getMessage().')', 'error');
			return false;
		}

		$chunk_size = 5*1024*1024;

		foreach($backup_array as $key => $file) {

			$fullpath = $updraft_dir.$file;
			$orig_file_size = filesize($fullpath);

// 			$cfpath = ($path == '') ? $file : "$path/$file";
// 			$chunk_path = ($path == '') ? "chunk-do-not-delete-$file" : "$path/chunk-do-not-delete-$file";
			$cfpath = $file;
			$chunk_path = "chunk-do-not-delete-$file";

			try {
				$object = new UpdraftPlus_CF_Object($container_object, $cfpath);
				$object->content_type = "application/zip";

				$uploaded_size = (isset($object->content_length)) ? $object->content_length : 0;

				if ($uploaded_size <= $orig_file_size) {

					$fp = @fopen($fullpath, "rb");
					if (!$fp) {
						$updraftplus->log("Cloud Files: failed to open file: $fullpath");
						$updraftplus->log("$file: ".sprintf(__('%s Error: Failed to open local file','updraftplus'),'Cloud Files'), 'error');
						return false;
					}

					$chunks = floor($orig_file_size / $chunk_size);
					// There will be a remnant unless the file size was exactly on a 5MB boundary
					if ($orig_file_size % $chunk_size > 0 ) $chunks++;

					$updraftplus->log("Cloud Files upload: $file (chunks: $chunks) -> cloudfiles://$container/$cfpath ($uploaded_size)");

					if ($chunks < 2) {
						try {
							$object->load_from_filename($fullpath);
							$updraftplus->log("Cloud Files regular upload: success");
							$updraftplus->uploaded_file($file);
						} catch (Exception $e) {
							$updraftplus->log("Cloud Files regular upload: failed ($file) (".$e->getMessage().")");
							$updraftplus->log("$file: ".sprintf(__('%s Error: Failed to upload','updraftplus'),'Cloud Files'), 'error');
						}
					} else {
						$errors_so_far = 0;
						for ($i = 1 ; $i <= $chunks; $i++) {
							$upload_start = ($i-1)*$chunk_size;
							// The file size -1 equals the byte offset of the final byte
							$upload_end = min($i*$chunk_size-1, $orig_file_size-1);
							$upload_remotepath = $chunk_path."_$i";
							// Don't forget the +1; otherwise the last byte is omitted
							$upload_size = $upload_end - $upload_start + 1;
							$chunk_object = new UpdraftPlus_CF_Object($container_object, $upload_remotepath);
							$chunk_object->content_type = "application/zip";
							// Without this, some versions of Curl add Expect: 100-continue, which results in Curl then giving this back: curl error: 55) select/poll returned error
							// Didn't make the difference - instead we just check below for actual success even when Curl reports an error
							// $chunk_object->headers = array('Expect' => '');

							$remote_size = (isset($chunk_object->content_length)) ? $chunk_object->content_length : 0;

							if ($remote_size >= $upload_size) {
								$updraftplus->log("Cloud Files: Chunk $i ($upload_start - $upload_end): already uploaded");
							} else {
								$updraftplus->log("Cloud Files: Chunk $i ($upload_start - $upload_end): begin upload");
								// Upload the chunk
								fseek($fp, $upload_start);
								try {
									$chunk_object->write($fp, $upload_size, false);
									$updraftplus->record_uploaded_chunk(round(100*$i/$chunks,1), $i, $fullpath);
								} catch (Exception $e) {
									$updraftplus->log("Cloud Files chunk upload: error: ($file / $i) (".$e->getMessage().")");
									// Experience shows that Curl sometimes returns a select/poll error (curl error 55) even when everything succeeded. Google seems to indicate that this is a known bug.
									
									$chunk_object = new UpdraftPlus_CF_Object($container_object, $upload_remotepath);
									$chunk_object->content_type = "application/zip";
									$remote_size = (isset($chunk_object->content_length)) ? $chunk_object->content_length : 0;
									
									if ($remote_size >= $upload_size) {

										$updraftplus->log("$file: Chunk now exists; ignoring error (presuming it was an apparently known curl bug)");

									} else {

										$updraftplus->log("$file: ".sprintf(__('%s Error: Failed to upload','updraftplus'),'Cloud Files'), 'error');
										$errors_so_far++;
										if ($errors_so_far >=3 ) return false;

									}

								}
							}
						}
						if ($errors_so_far) return false;
						// All chunks are uploaded - now upload the manifest
						
						try {
							$object->manifest = $container."/".$chunk_path."_";
							// Put a zero-length file
							$object->write("", 0, false);
							$object->sync_manifest();
							$updraftplus->log("Cloud Files upload: success");
							$updraftplus->uploaded_file($file);
// 						} catch (InvalidResponseException $e) {
						} catch (Exception $e) {
							$updraftplus->log('Cloud Files error - failed to re-assemble chunks ('.$e->getMessage().')');
							$updraftplus->log(sprintf(__('%s error - failed to re-assemble chunks', 'updraftplus'),'Cloud Files').' ('.$e->getMessage().')', 'error');
							return false;
						}
					}
				}

			} catch (Exception $e) {
				$updraftplus->log(__('Cloud Files error - failed to upload file', 'updraftplus').' ('.$e->getMessage().')');
				$updraftplus->log(sprintf(__('%s error - failed to upload file', 'updraftplus'),'Cloud Files').' ('.$e->getMessage().')', 'error');
				return false;
			}

		}

		return array('cloudfiles_object' => $container_object, 'cloudfiles_orig_path' => $opts['path'], 'cloudfiles_container' => $container);

	}

	public function listfiles($match = 'backup_') {

		$opts = $this->get_opts();
		$container = $opts['path'];

		if (empty($opts['user']) || empty($opts['apikey'])) new WP_Error('no_settings', __('No settings were found','updraftplus'));

		try {
			$conn = $this->getCF($opts['user'], $opts['apikey'], $opts['authurl'], UpdraftPlus_Options::get_updraft_option('updraft_ssl_useservercerts'));
			$container_object = $conn->create_container($container);
		} catch(Exception $e) {
			return new WP_Error('no_access', sprintf(__('%s authentication failed','updraftplus'),'Cloud Files').' ('.$e->getMessage().')');
		}

		$results = array();

		try {
			$objects = $container_object->list_objects(0, NULL, $match);
			foreach ($objects as $name) {
				$result = array('name' => $name);
				try {
					$object = new UpdraftPlus_CF_Object($container_object, $name, true);
					if ($object->content_length == 0) {
						$result = false;
					} else {
						$result['size'] = $object->content_length;
					}
				} catch (Exception $e) {
				}
				if (is_array($result)) $results[] = $result;
			}
		} catch (Exception $e) {
			return new WP_Error('cf_error', 'Cloud Files error ('.$e->getMessage().')');
		}

		return $results;

	}

	public function delete($files, $cloudfilesarr = false, $sizeinfo = array()) {

		global $updraftplus;
		if (is_string($files)) $files=array($files);

		if ($cloudfilesarr) {
			$container_object = $cloudfilesarr['cloudfiles_object'];
			$container = $cloudfilesarr['cloudfiles_container'];
			$path = $cloudfilesarr['cloudfiles_orig_path'];
		} else {
			try {
				$opts = $this->get_opts();
				$container = $opts['path'];
				$conn = $this->getCF($opts['user'], $opts['apikey'], $opts['authurl'], UpdraftPlus_Options::get_updraft_option('updraft_ssl_useservercerts'));
				$container_object = $conn->create_container($container);
			} catch(Exception $e) {
				$updraftplus->log('Cloud Files authentication failed ('.$e->getMessage().')');
				$updraftplus->log(sprintf(__('%s authentication failed','updraftplus'), 'Cloud Files').' ('.$e->getMessage().')', 'error');
				return false;
			}
		}

// 		$fpath = ($path == '') ? $file : "$path/$file";

		$ret = true;
		foreach ($files as $file) {

			$fpath = $file;

			$updraftplus->log("Cloud Files: Delete remote: container=$container, path=$fpath");

			// We need to search for chunks
			//$chunk_path = ($path == '') ? "chunk-do-not-delete-$file_" : "$path/chunk-do-not-delete-$file_";
			$chunk_path = "chunk-do-not-delete-$file";

			try {
				$objects = $container_object->list_objects(0, NULL, $chunk_path.'_');
				foreach ($objects as $chunk) {
					$updraftplus->log('Cloud Files: Chunk to delete: '.$chunk);
					$container_object->delete_object($chunk);
					$updraftplus->log('Cloud Files: Chunk deleted: '.$chunk);
				}
			} catch (Exception $e) {
				$updraftplus->log('Cloud Files chunk delete failed: '.$e->getMessage());
			}

			try {
				$container_object->delete_object($fpath);
				$updraftplus->log('Cloud Files: Deleted: '.$fpath);
			} catch (Exception $e) {
				$updraftplus->log('Cloud Files delete failed: '.$e->getMessage());
				$ret = false;
			}
		}
		return $ret;
	}

	public function download($file) {

		global $updraftplus;
		$updraft_dir = $updraftplus->backups_dir_location();

		$opts = $this->get_opts();

		try {
			$conn = $this->getCF($opts['user'], $opts['apikey'], $opts['authurl'], UpdraftPlus_Options::get_updraft_option('updraft_ssl_useservercerts'));
		} catch(AuthenticationException $e) {
			$updraftplus->log('Cloud Files authentication failed ('.$e->getMessage().')');
			$updraftplus->log(sprintf(__('%s authentication failed','updraftplus'), 'Cloud Files').' ('.$e->getMessage().')', 'error');
			return false;
		} catch(NoSuchAccountException $s) {
			$updraftplus->log('Cloud Files authentication failed ('.$e->getMessage().')');
			$updraftplus->log(sprintf(__('%s authentication failed','updraftplus'), 'Cloud Files').' ('.$e->getMessage().')', 'error');
			return false;
		} catch (Exception $e) {
			$updraftplus->log('Cloud Files error - failed to create and access the container ('.$e->getMessage().')');
			$updraftplus->log(__('Cloud Files error - failed to create and access the container', 'updraftplus').' ('.$e->getMessage().')', 'error');
			return false;
		}

		$path = untrailingslashit($opts['path']);

// 		if (preg_match("#^([^/]+)/(.*)$#", $path, $bmatches)) {
// 			$container = $bmatches[1];
// 			$path = $bmatches[2];
// 		} else {
// 			$container = $path;
// 			$path = "";
// 		}
		$container = $path;

		try {
			$container_object = $conn->create_container($container);
		} catch(Exception $e) {
			$updraftplus->log('Cloud Files error - failed to create and access the container ('.$e->getMessage().')');
			$updraftplus->log(__('Cloud Files error - failed to create and access the container','updraftplus').' ('.$e->getMessage().')', 'error');
			return false;
		}

// 		$path = ($path == '') ? $file : "$path/$file";
		$path = $file;

		$updraftplus->log("Cloud Files download: cloudfiles://$container/$path");

		try {
			// The third parameter causes an exception to be thrown if the object does not exist remotely
			$object = new UpdraftPlus_CF_Object($container_object, $path, true);
			
			$fullpath = $updraft_dir.'/'.$file;

			$start_offset =  (file_exists($fullpath)) ? filesize($fullpath): 0;

			// Get file size from remote - see if we've already finished

			$remote_size = $object->content_length;

			if ($start_offset >= $remote_size) {
				$updraftplus->log("Cloud Files: file is already completely downloaded ($start_offset/$remote_size)");
				return true;
			}

			// Some more remains to download - so let's do it
			if (!$fh = fopen($fullpath, 'a')) {
				$updraftplus->log("Cloud Files: Error opening local file: $fullpath");
				$updraftplus->log(sprintf("$file: ".__("%s Error",'updraftplus'),'Cloud Files').": ".__('Error opening local file: Failed to download','updraftplus'), 'error');
				return false;
			}

			$headers = array();
			// If resuming, then move to the end of the file
			if ($start_offset) {
				$updraftplus->log("Cloud Files: local file is already partially downloaded ($start_offset/$remote_size)");
				fseek($fh, $start_offset);
				$headers['Range'] = "bytes=$start_offset-";
			}

			// Now send the request itself
			try {
				$object->stream($fh, $headers);
			} catch (Exception $e) {
				$updraftplus->log("Cloud Files: Failed to download: $file (".$e->getMessage().")");
				$updraftplus->log("$file: ".sprintf(__("%s Error",'updraftplus'), 'Cloud Files').": ".__('Error downloading remote file: Failed to download','updraftplus').' ('.$e->getMessage().")", 'error');
				return false;
			}
			
			// All-in-one-go method:
			// $object->save_to_filename($fullpath);

		} catch (NoSuchObjectException $e) {
			$updraftplus->log('Cloud Files error - no such file exists at Cloud Files ('.$e->getMessage().')');
			$updraftplus->log(sprintf(__('Error - no such file exists at %s','updraftplus'),'Cloud Files').' ('.$e->getMessage().')', 'error');
			return false;
		} catch(Exception $e) {
			$updraftplus->log('Cloud Files error - failed to download the file ('.$e->getMessage().')');
			$updraftplus->log(sprintf(__('Error - failed to download the file from %s','updraftplus'),'Cloud Files').' ('.$e->getMessage().')' ,'error');
			return false;
		}

		return true;

	}

	public function config_print() {

		$opts = $this->get_opts();

		?>
		<tr class="updraftplusmethod cloudfiles">
			<td></td>
			<td><img alt="Rackspace Cloud Files" src="<?php echo UPDRAFTPLUS_URL.'/images/rackspacecloud-logo.png' ?>">
				<p><em><?php printf(__('%s is a great choice, because UpdraftPlus supports chunked uploads - no matter how big your site is, UpdraftPlus can upload it a little at a time, and not get thwarted by timeouts.','updraftplus'),'Rackspace Cloud Files');?></em></p></td>
		</tr>

		<tr class="updraftplusmethod cloudfiles">
			<th></th>
			<td>
			<?php
			// Check requirements.
			global $updraftplus_admin;
			if (!function_exists('mb_substr')) {
				$updraftplus_admin->show_double_warning('<strong>'.__('Warning','updraftplus').':</strong> '.sprintf(__('Your web server\'s PHP installation does not included a required module (%s). Please contact your web hosting provider\'s support.', 'updraftplus'), 'mbstring').' '.sprintf(__("UpdraftPlus's %s module <strong>requires</strong> %s. Please do not file any support requests; there is no alternative.",'updraftplus'),'Cloud Files', 'mbstring'), 'cloudfiles');
			}
			$updraftplus_admin->curl_check('Rackspace Cloud Files', false, 'cloudfiles');
			?>
			</td>
		</tr>

		<tr class="updraftplusmethod cloudfiles">
		<th></th>
			<td>
				<p><?php _e('Get your API key <a href="https://mycloud.rackspace.com/">from your Rackspace Cloud console</a> (read instructions <a href="http://www.rackspace.com/knowledge_center/article/rackspace-cloud-essentials-1-generating-your-api-key">here</a>), then pick a container name to use for storage. This container will be created for you if it does not already exist.','updraftplus');?> <a href="https://updraftplus.com/faqs/there-appear-to-be-lots-of-extra-files-in-my-rackspace-cloud-files-container/"><?php _e('Also, you should read this important FAQ.', 'updraftplus'); ?></a></p>
			</td>
		</tr>
		<tr class="updraftplusmethod cloudfiles">
			<th><?php _e('US or UK Cloud','updraftplus');?>:</th>
			<td>
				<select data-updraft_settings_test="authurl" id="updraft_cloudfiles_authurl" name="updraft_cloudfiles[authurl]">
					<option <?php if ($opts['authurl'] != 'https://lon.auth.api.rackspacecloud.com') echo 'selected="selected"'; ?> value="https://auth.api.rackspacecloud.com"><?php _e('US (default)','updraftplus'); ?></option>
					<option <?php if ($opts['authurl'] =='https://lon.auth.api.rackspacecloud.com') echo 'selected="selected"'; ?> value="https://lon.auth.api.rackspacecloud.com"><?php _e('UK', 'updraftplus'); ?></option>
				</select>
			</td>
		</tr>
		
		<input type="hidden" data-updraft_settings_test="region" name="updraft_cloudfiles[region]" value="">
		<?php /*
		// Can put a message here if someone asks why region storage is not available (only available on new SDK)
		<tr class="updraftplusmethod cloudfiles">
			<th><?php _e('Rackspace Storage Region','updraftplus');?>:</th>
			<td>
				
			</td>
		</tr> */ ?>

		<tr class="updraftplusmethod cloudfiles">
			<th><?php _e('Cloud Files username','updraftplus');?>:</th>
			<td><input data-updraft_settings_test="user" type="text" autocomplete="off" style="width: 282px" id="updraft_cloudfiles_user" name="updraft_cloudfiles[user]" value="<?php echo htmlspecialchars($opts['user']) ?>" /></td>
		</tr>
		<tr class="updraftplusmethod cloudfiles">
			<th><?php _e('Cloud Files API key','updraftplus');?>:</th>
			<td><input data-updraft_settings_test="apikey" type="<?php echo apply_filters('updraftplus_admin_secret_field_type', 'password'); ?>" autocomplete="off" style="width: 282px" id="updraft_cloudfiles_apikey" name="updraft_cloudfiles[apikey]" value="<?php echo htmlspecialchars(trim($opts['apikey'])); ?>" /></td>
		</tr>
		<tr class="updraftplusmethod cloudfiles">
			<th><?php echo apply_filters('updraftplus_cloudfiles_location_description',__('Cloud Files container','updraftplus'));?>:</th>
			<td><input data-updraft_settings_test="path" type="text" style="width: 282px" name="updraft_cloudfiles[path]" id="updraft_cloudfiles_path" value="<?php echo htmlspecialchars($opts['path']); ?>" /></td>
		</tr>

		<tr class="updraftplusmethod cloudfiles">
		<th></th>
		<td><p><button id="updraft-cloudfiles-test" type="button" class="button-primary updraft-test-button" data-method="cloudfiles" data-method_label="<?php esc_attr_e('Cloud Files'); ?>"><?php echo sprintf(__('Test %s Settings','updraftplus'),'Cloud Files');?></button></p></td>
		</tr>
	<?php
	}

	public function credentials_test($posted_settings) {

		if (empty($posted_settings['apikey'])) {
			printf(__("Failure: No %s was given.",'updraftplus'),__('API key','updraftplus'));
			return;
		}

		if (empty($posted_settings['user'])) {
			printf(__("Failure: No %s was given.",'updraftplus'),__('Username','updraftplus'));
			return;
		}

		$key = stripslashes($posted_settings['apikey']);
		$user = $posted_settings['user'];
		$path = $posted_settings['path'];
		$authurl = $posted_settings['authurl'];
		$useservercerts = $posted_settings['useservercerts'];
		$disableverify = $posted_settings['disableverify'];

		if (preg_match("#^([^/]+)/(.*)$#", $path, $bmatches)) {
			$container = $bmatches[1];
			$path = $bmatches[2];
		} else {
			$container = $path;
			$path = "";
		}

		if (empty($container)) {
			_e("Failure: No container details were given.",'updraftplus');
			return;
		}

		define('UPDRAFTPLUS_SSL_DISABLEVERIFY', $disableverify);

		try {
			$conn = $this->getCF($user, $key, $authurl, $useservercerts);
			$container_object = $conn->create_container($container);
		} catch(AuthenticationException $e) {
			echo __('Cloud Files authentication failed','updraftplus').' ('.$e->getMessage().')';
			return;
		} catch(NoSuchAccountException $s) {
			echo __('Cloud Files authentication failed','updraftplus').' ('.$e->getMessage().')';
			return;
		} catch (Exception $e) {
			echo __('Cloud Files authentication failed','updraftplus').' ('.$e->getMessage().')';
			return;
		}

		$try_file = md5(rand()).'.txt';

		try {
			$object = $container_object->create_object($try_file);
			$object->content_type = "text/plain";
			$object->write('UpdraftPlus test file');
		} catch (Exception $e) {
			echo __('Cloud Files error - we accessed the container, but failed to create a file within it', 'updraftplus').' ('.$e->getMessage().')';
			return;
		}

		echo __('Success','updraftplus').": ".__('We accessed the container, and were able to create files within it.','updraftplus');

		@$container_object->delete_object($try_file);
	}

}
