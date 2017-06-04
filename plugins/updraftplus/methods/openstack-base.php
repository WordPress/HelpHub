<?php

class UpdraftPlus_BackupModule_openstack_base {

	protected $chunk_size = 5242880;

	protected $client;
	protected $method;
	protected $desc;
	protected $long_desc;
	protected $img_url;

	public function __construct($method, $desc, $long_desc = null, $img_url = '') {
		$this->method = $method;
		$this->desc = $desc;
		$this->long_desc = (is_string($long_desc)) ? $long_desc : $desc;
		$this->img_url = $img_url;
	}

	public function backup($backup_array) {

		global $updraftplus, $updraftplus_backup;

		$opts = $this->get_opts();

		$this->container = $opts['path'];

		try {
			$service = $this->get_service($opts, UpdraftPlus_Options::get_updraft_option('updraft_ssl_useservercerts'), UpdraftPlus_Options::get_updraft_option('updraft_ssl_disableverify'));
		} catch(AuthenticationError $e) {
			$updraftplus->log($this->desc.' authentication failed ('.$e->getMessage().')');
			$updraftplus->log(sprintf(__('%s authentication failed', 'updraftplus'), $this->desc).' ('.$e->getMessage().')', 'error');
			return false;
		} catch (Exception $e) {
			$updraftplus->log($this->desc.' error - failed to access the container ('.$e->getMessage().') (line: '.$e->getLine().', file: '.$e->getFile().')');
			$updraftplus->log(sprintf(__('%s error - failed to access the container', 'updraftplus'), $this->desc).' ('.$e->getMessage().')', 'error');
			return false;
		}
		# Get the container
		try {
			$this->container_object = $service->getContainer($this->container);
		} catch (Exception $e) {
			$updraftplus->log('Could not access '.$this->desc.' container ('.get_class($e).', '.$e->getMessage().') (line: '.$e->getLine().', file: '.$e->getFile().')');
			$updraftplus->log(sprintf(__('Could not access %s container', 'updraftplus'), $this->desc).' ('.get_class($e).', '.$e->getMessage().')', 'error');
			return false;
		}

		foreach ($backup_array as $key => $file) {

			# First, see the object's existing size (if any)
			$uploaded_size = $this->get_remote_size($file);

			try {
				if (1 === $updraftplus->chunked_upload($this, $file, $this->method."://".$this->container."/$file", $this->desc, $this->chunk_size, $uploaded_size)) {
					try {
						if (false !== ($data = fopen($updraftplus->backups_dir_location().'/'.$file, 'r+'))) {
							$this->container_object->uploadObject($file, $data);
							$updraftplus->log($this->desc." regular upload: success");
							$updraftplus->uploaded_file($file);
						} else {
							throw new Exception('uploadObject failed: fopen failed');
						}
					} catch (Exception $e) {
						$this->log("$logname regular upload: failed ($file) (".$e->getMessage().")");
						$this->log("$file: ".sprintf(__('%s Error: Failed to upload','updraftplus'),$logname), 'error');
					}
				}
			} catch (Exception $e) {
				$updraftplus->log($this->desc.' error - failed to upload file'.' ('.$e->getMessage().') (line: '.$e->getLine().', file: '.$e->getFile().')');
				$updraftplus->log(sprintf(__('%s error - failed to upload file', 'updraftplus'), $this->desc).' ('.$e->getMessage().')', 'error');
				return false;
			}
		}

		return array('object' => $this->container_object, 'orig_path' => $opts['path'], 'container' => $this->container);

	}

	private function get_remote_size($file) {
		try {
			$response = $this->container_object->getClient()->head($this->container_object->getUrl($file))->send();
			$response_object = $this->container_object->dataObject()->populateFromResponse($response)->setName($file);
			return $response_object->getContentLength();
		} catch (Exception $e) {
			# Allow caller to distinguish between zero-sized and not-found
			return false;
		}
	}

	public function listfiles($match = 'backup_') {
		$opts = $this->get_opts();
		$container = $opts['path'];
		$path = $container;

		if (empty($opts['user']) || (empty($opts['apikey']) && empty($opts['password']))) return new WP_Error('no_settings', __('No settings were found','updraftplus'));

		try {
			$service = $this->get_service($opts, UpdraftPlus_Options::get_updraft_option('updraft_ssl_useservercerts'), UpdraftPlus_Options::get_updraft_option('updraft_ssl_disableverify'));
		} catch (Exception $e) {
			return new WP_Error('no_access', sprintf(__('%s error - failed to access the container', 'updraftplus'), $this->desc).' ('.$e->getMessage().')');
		}

		# Get the container
		try {
			$this->container_object = $service->getContainer($container);
		} catch (Exception $e) {
			return new WP_Error('no_access', sprintf(__('%s error - failed to access the container', 'updraftplus'), $this->desc).' ('.$e->getMessage().')');
		}

		$results = array();
		try {
			$objects = $this->container_object->objectList(array('prefix' => $match));
			$index = 0;
			while (false !== ($file = $objects->offsetGet($index)) && !empty($file)) {
				try {
					if ((is_object($file) && !empty($file->name))) {
						$result = array('name' => $file->name);
						# Rackspace returns the size of a manifested file properly; other OpenStack implementations may not
						if (!empty($file->bytes)) {
							$result['size'] = $file->bytes;
						} else {
							$size = $this->get_remote_size($file->name);
							if (false !== $size && $size > 0) $result['size'] = $size;
						}
						$results[] = $result;
					}
				} catch (Exception $e) {
				}
				$index++;
			}
		} catch (Exception $e) {
		}

		return $results;
	}

	public function chunked_upload_finish($file) {

		$chunk_path = 'chunk-do-not-delete-'.$file;
		try {

			$headers = array(
				'Content-Length'    => 0,
				'X-Object-Manifest' => sprintf('%s/%s', 
					$this->container,
					$chunk_path.'_'
				)
			);
			
			$url = $this->container_object->getUrl($file);
			$this->container_object->getClient()->put($url, $headers)->send();
			return true;

		} catch (Exception $e) {
			global $updraftplus;
			$updraftplus->log("Error when sending manifest (".get_class($e)."): ".$e->getMessage());
			return false;
		}
	}

	public function chunked_upload($file, $fp, $i, $upload_size, $upload_start, $upload_end) {

		global $updraftplus;

		$upload_remotepath = 'chunk-do-not-delete-'.$file.'_'.$i;

		$remote_size = $this->get_remote_size($upload_remotepath);

		// Without this, some versions of Curl add Expect: 100-continue, which results in Curl then giving this back: curl error: 55) select/poll returned error
		// Didn't make the difference - instead we just check below for actual success even when Curl reports an error
		// $chunk_object->headers = array('Expect' => '');

		if ($remote_size >= $upload_size) {
			$updraftplus->log($this->desc.": Chunk $i ($upload_start - $upload_end): already uploaded");
		} else {
			$updraftplus->log($this->desc.": Chunk $i ($upload_start - $upload_end): begin upload");
			// Upload the chunk
			try {
				$data = fread($fp, $upload_size);
				$this->container_object->uploadObject($upload_remotepath, $data);
			} catch (Exception $e) {
				$updraftplus->log($this->desc." chunk upload: error: ($file / $i) (".$e->getMessage().") (line: ".$e->getLine().', file: '.$e->getFile().')');
				// Experience shows that Curl sometimes returns a select/poll error (curl error 55) even when everything succeeded. Google seems to indicate that this is a known bug.
				
				$remote_size = $this->get_remote_size($upload_remotepath);

				if ($remote_size >= $upload_size) {
					$updraftplus->log("$file: Chunk now exists; ignoring error (presuming it was an apparently known curl bug)");
				} else {
					$updraftplus->log("$file: ".sprintf(__('%s Error: Failed to upload','updraftplus'), $this->desc), 'error');
					return false;
				}
			}
		}
		return true;
	}


	public function delete($files, $data = false, $sizeinfo = array()) {

		global $updraftplus;
		if (is_string($files)) $files = array($files);

		if (is_array($data)) {
			$container_object = $data['object'];
			$container = $data['container'];
			$path = $data['orig_path'];
		} else {
			$opts = $this->get_opts();
			$container = $opts['path'];
			$path = $container;
			try {
				$service = $this->get_service($opts, UpdraftPlus_Options::get_updraft_option('updraft_ssl_useservercerts'), UpdraftPlus_Options::get_updraft_option('updraft_ssl_disableverify'));
			} catch(AuthenticationError $e) {
				$updraftplus->log($this->desc.' authentication failed ('.$e->getMessage().')');
				$updraftplus->log(sprintf(__('%s authentication failed', 'updraftplus'), $this->desc).' ('.$e->getMessage().')', 'error');
				return false;
			} catch (Exception $e) {
				$updraftplus->log($this->desc.' error - failed to access the container ('.$e->getMessage().')');
				$updraftplus->log(sprintf(__('%s error - failed to access the container', 'updraftplus'), $this->desc).' ('.$e->getMessage().')', 'error');
				return false;
			}
			# Get the container
			try {
				$container_object = $service->getContainer($container);
			} catch (Exception $e) {
				$updraftplus->log('Could not access '.$this->desc.' container ('.get_class($e).', '.$e->getMessage().')');
				$updraftplus->log(sprintf(__('Could not access %s container', 'updraftplus'), $this->desc).' ('.get_class($e).', '.$e->getMessage().')', 'error');
				return false;
			}

		}

		$ret = true;
		foreach ($files as $file) {

			$updraftplus->log($this->desc.": Delete remote: container=$container, path=$file");

			// We need to search for chunks
			$chunk_path = "chunk-do-not-delete-".$file;

			try {
				$objects = $container_object->objectList(array('prefix' => $chunk_path));
				$index = 0;
				while (false !== ($chunk = $objects->offsetGet($index)) && !empty($chunk)) {
					try {
						$name = $chunk->name;
						$container_object->dataObject()->setName($name)->delete();
						$updraftplus->log($this->desc.': Chunk deleted: '.$name);
					} catch (Exception $e) {
						$updraftplus->log($this->desc." chunk delete failed: $name: ".$e->getMessage());
					}
					$index++;
				}
			} catch (Exception $e) {
				$updraftplus->log($this->desc.' chunk delete failed: '.$e->getMessage());
			}

			# Finally, delete the object itself
			try {
				$container_object->dataObject()->setName($file)->delete();
				$updraftplus->log($this->desc.': Deleted: '.$file);
			} catch (Exception $e) {
				$updraftplus->log($this->desc.' delete failed: '.$e->getMessage());
				$ret = false;
			}
		}
		return $ret;
	}

	public function download($file) {

		global $updraftplus;

		$opts = $this->get_opts();

		try {
			$service = $this->get_service($opts, UpdraftPlus_Options::get_updraft_option('updraft_ssl_useservercerts'), UpdraftPlus_Options::get_updraft_option('updraft_ssl_disableverify'));
		} catch(AuthenticationError $e) {
			$updraftplus->log($this->desc.' authentication failed ('.$e->getMessage().')');
			$updraftplus->log(sprintf(__('%s authentication failed', 'updraftplus'), $this->desc).' ('.$e->getMessage().')', 'error');
			return false;
		} catch (Exception $e) {
			$updraftplus->log($this->desc.' error - failed to access the container ('.$e->getMessage().')');
			$updraftplus->log(sprintf(__('%s error - failed to access the container', 'updraftplus'), $this->desc).' ('.$e->getMessage().')', 'error');
			return false;
		}

		$container = untrailingslashit($opts['path']);
		$updraftplus->log($this->desc." download: ".$this->method."://$container/$file");

		# Get the container
		try {
			$this->container_object = $service->getContainer($container);
		} catch (Exception $e) {
			$updraftplus->log('Could not access '.$this->desc.' container ('.get_class($e).', '.$e->getMessage().')');
			$updraftplus->log(sprintf(__('Could not access %s container', 'updraftplus'), $this->desc).' ('.get_class($e).', '.$e->getMessage().')', 'error');
			return false;
		}

		# Get information about the object within the container
		$remote_size = $this->get_remote_size($file);
		if (false === $remote_size) {
			$updraftplus->log('Could not access '.$this->desc.' object');
			$updraftplus->log(sprintf(__('The %s object was not found', 'updraftplus'), $this->desc), 'error');
			return false;
		}

		return (!is_bool($remote_size)) ? $updraftplus->chunked_download($file, $this, $remote_size, true, $this->container_object) : false;

	}

	public function chunked_download($file, $headers, $container_object) {
		try {
			$dl = $container_object->getObject($file, $headers);
		} catch (Exception $e) {
			global $updraftplus;
			$updraftplus->log("$file: Failed to download (".$e->getMessage().")");
			$updraftplus->log("$file: ".sprintf(__("%s Error",'updraftplus'), $this->desc).": ".__('Error downloading remote file: Failed to download'.' ('.$e->getMessage().")",'updraftplus'), 'error');
			return false;
		}
		return $dl->getContent();
	}

	public function credentials_test_go($opts, $path, $useservercerts, $disableverify) {

		if (preg_match("#^([^/]+)/(.*)$#", $path, $bmatches)) {
			$container = $bmatches[1];
			$path = $bmatches[2];
		} else {
			$container = $path;
			$path = '';
		}

		if (empty($container)) {
			_e('Failure: No container details were given.' ,'updraftplus');
			return;
		}

		try {
			$service = $this->get_service($opts, $useservercerts, $disableverify);
		} catch(Guzzle\Http\Exception\ClientErrorResponseException $e) {
			$response = $e->getResponse();
			$code = $response->getStatusCode();
			$reason = $response->getReasonPhrase();
			if (401 == $code && 'Unauthorized' == $reason) {
				echo __('Authorisation failed (check your credentials)', 'updraftplus');
			} else {
				echo __('Authorisation failed (check your credentials)', 'updraftplus')." ($code:$reason)";
			}
			return;
		} catch(AuthenticationError $e) {
			echo sprintf(__('%s authentication failed', 'updraftplus'), $this->desc).' ('.$e->getMessage().')';
			return;
		} catch (Exception $e) {
			echo sprintf(__('%s authentication failed', 'updraftplus'), $this->desc).' ('.get_class($e).', '.$e->getMessage().')';
			return;
		}

		try {
			$container_object = $service->getContainer($container);
		} catch(Guzzle\Http\Exception\ClientErrorResponseException $e) {
			$response = $e->getResponse();
			$code = $response->getStatusCode();
			$reason = $response->getReasonPhrase();
			if (404 == $code) {
				$container_object = $service->createContainer($container);
			} else {
				echo __('Authorisation failed (check your credentials)', 'updraftplus')." ($code:$reason)";
				return;
			}
		} catch (Exception $e) {
			echo sprintf(__('%s authentication failed', 'updraftplus'), $this->desc).' ('.get_class($e).', '.$e->getMessage().')';
			return;
		}

		if (!is_a($container_object, 'OpenCloud\ObjectStore\Resource\Container') && !is_a($container_object, 'Container')) {
			echo sprintf(__('%s authentication failed', 'updraftplus'), $this->desc).' ('.get_class($container_object).')';
			return;
		}

		$try_file = md5(rand()).'.txt';

		try {
			$object = $container_object->uploadObject($try_file, 'UpdraftPlus test file', array('content-type' => 'text/plain'));
		} catch (Exception $e) {
			echo sprintf(__('%s error - we accessed the container, but failed to create a file within it', 'updraftplus'), $this->desc).' ('.get_class($e).', '.$e->getMessage().')';
			if (!empty($this->region)) echo ' '.sprintf(__('Region: %s', 'updraftplus'), $this->region);
			return;
		}

		echo __('Success', 'updraftplus').": ".__('We accessed the container, and were able to create files within it.', 'updraftplus');
		if (!empty($this->region)) echo ' '.sprintf(__('Region: %s', 'updraftplus'), $this->region);

		try {
			if (!empty($object)) {
				# One OpenStack server we tested on did not delete unless we slept... some kind of race condition at their end
				sleep(1);
				$object->delete();
			}
		} catch (Exception $e) {
		}

	}

	public function config_print_middlesection() {
	}

	public function config_print() {

		?>
		<tr class="updraftplusmethod <?php echo $this->method;?>">
			<td></td>
			<td>
				<?php
					if (!empty($this->img_url)) { ?>
					<img alt="<?php echo $this->long_desc;?>" src="<?php echo UPDRAFTPLUS_URL.$this->img_url ?>">
				<?php } ?>
				<p><em><?php printf(__('%s is a great choice, because UpdraftPlus supports chunked uploads - no matter how big your site is, UpdraftPlus can upload it a little at a time, and not get thwarted by timeouts.','updraftplus'),$this->long_desc);?></em></p></td>
		</tr>

		<tr class="updraftplusmethod <?php echo $this->method;?>">
			<th></th>
			<td>
			<?php
			// Check requirements.
			global $updraftplus_admin;
			if (!function_exists('mb_substr')) {
				$updraftplus_admin->show_double_warning('<strong>'.__('Warning','updraftplus').':</strong> '.sprintf(__('Your web server\'s PHP installation does not included a required module (%s). Please contact your web hosting provider\'s support.', 'updraftplus'), 'mbstring').' '.sprintf(__("UpdraftPlus's %s module <strong>requires</strong> %s. Please do not file any support requests; there is no alternative.",'updraftplus'), $this->desc, 'mbstring'), $this->method);
			}
			$updraftplus_admin->curl_check($this->long_desc, false, $this->method);
			?>
			</td>
		</tr>

		<?php $this->config_print_middlesection(); ?>

		<tr class="updraftplusmethod <?php echo $this->method;?>">
		<th></th>
		<td><p><button id="updraft-<?php echo $this->method;?>-test" type="button" data-method="<?php echo $this->method;?>" class="button-primary updraft-test-button" data-method_label="<?php esc_attr_e($this->desc);?>"><?php echo sprintf(__('Test %s Settings','updraftplus'), $this->desc);?></button></p></td>
		</tr>
	<?php
	}

}
