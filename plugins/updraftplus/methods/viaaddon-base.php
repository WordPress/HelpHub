<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed.');

class UpdraftPlus_BackupModule_ViaAddon {

	private $method;
	private $description;

	public function __construct($method, $description, $required_php = false, $image = null) {
		$this->method = $method;
		$this->description = $description;
		$this->required_php = $required_php;
		$this->image = $image;
		$this->error_msg = 'This remote storage method ('.$this->description.') requires PHP '.$this->required_php.' or later';
		$this->error_msg_trans = sprintf(__('This remote storage method (%s) requires PHP %s or later.', 'updraftplus'), $this->description, $this->required_php);
	}

	public function action_handler($action = '') {
		return apply_filters('updraft_'.$this->method.'_action_'.$action, null);
	}

	public function backup($backup_array) {

		global $updraftplus;

		if (!class_exists('UpdraftPlus_Addons_RemoteStorage_'.$this->method)) {
			$updraftplus->log("You do not have the UpdraftPlus ".$this->method.' add-on installed - get it from https://updraftplus.com/shop/');
			$updraftplus->log(sprintf(__('You do not have the UpdraftPlus %s add-on installed - get it from %s','updraftplus'), $this->description ,'https://updraftplus.com/shop/'), 'error', 'missingaddon-'.$this->method);
			return false;
		}

		return apply_filters('updraft_'.$this->method.'_upload_files', null, $backup_array);

	}

	public function delete($files, $method_obj = false, $sizeinfo = array()) {

		global $updraftplus;

		if (!class_exists('UpdraftPlus_Addons_RemoteStorage_'.$this->method)) {
			$updraftplus->log('You do not have the UpdraftPlus '.$this->method.' add-on installed - get it from https://updraftplus.com/shop/');
			$updraftplus->log(sprintf(__('You do not have the UpdraftPlus %s add-on installed - get it from %s','updraftplus'), $this->description, 'https://updraftplus.com/shop/'), 'error', 'missingaddon-'.$this->method);
			return false;
		}

		return apply_filters('updraft_'.$this->method.'_delete_files', false, $files, $method_obj, $sizeinfo);

	}

	public function listfiles($match = 'backup_') {
		return apply_filters('updraft_'.$this->method.'_listfiles', new WP_Error('no_addon', sprintf(__('You do not have the UpdraftPlus %s add-on installed - get it from %s','updraftplus'), $this->description, 'https://updraftplus.com/shop/')), $match);
	}

	// download method: takes a file name (base name), and removes it from the cloud storage
	public function download($file) {

		global $updraftplus;

		if (!class_exists('UpdraftPlus_Addons_RemoteStorage_'.$this->method)) {
			$updraftplus->log('You do not have the UpdraftPlus '.$this->method.' add-on installed - get it from https://updraftplus.com/shop/');
			$updraftplus->log(sprintf(__('You do not have the UpdraftPlus %s add-on installed - get it from %s','updraftplus'), $this->description, 'https://updraftplus.com/shop/'), 'error', 'missingaddon-'.$this->method);
			return false;
		}

		return apply_filters('updraft_'.$this->method.'_download_file', false, $file);

	}

	public function config_print() {

		$link = sprintf(__('%s support is available as an add-on','updraftplus'), $this->description).' - <a href="https://updraftplus.com/shop/'.$this->method.'/">'.__('follow this link to get it','updraftplus');

		$default = '
		<tr class="updraftplusmethod '.$this->method.'">
			<th>'.$this->description.':</th>
			<td>'.((!empty($this->image)) ? '<p><img src="'.UPDRAFTPLUS_URL.'/images/'.$this->image.'"></p>' : '').$link.'</a></td>
			</tr>';

		if (version_compare(phpversion(), $this->required_php, '<')) {
			$default .= '<tr class="updraftplusmethod '.$this->method.'">
			<th></th>
			<td>
				<em>
					'.htmlspecialchars($this->error_msg_trans).'
					'.htmlspecialchars(__('You will need to ask your web hosting company to upgrade.', 'updraftplus')).'
					'.sprintf(__('Your %s version: %s.', 'updraftplus'), 'PHP', phpversion()).'
				</em>
			</td>
			</tr>';
		}

		echo apply_filters('updraft_'.$this->method.'_config_print', $default);
	}

	public function config_print_javascript_onready() {
		do_action('updraft_'.$this->method.'_config_javascript');
	}

	public function credentials_test($posted_settings) {
		do_action('updraft_'.$this->method.'_credentials_test', $posted_settings);
	}

}
