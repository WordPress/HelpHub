<?php
/*
UpdraftPlus Addon: dropbox-folders:Dropbox folders
Description: Allows Dropbox to use sub-folders - useful if you are backing up many sites into one Dropbox
Version: 1.3
Shop: /shop/dropbox-folders/
Latest Change: 1.9.14
*/

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed');

add_action('updraftplus_dropbox_extra_config', array('UpdraftPlus_Addon_DropboxFolders', 'config_print'));
add_filter('updraftplus_dropbox_modpath', array('UpdraftPlus_Addon_DropboxFolders', 'change_path'));

class UpdraftPlus_Addon_DropboxFolders {

	public static function config_print() {

		$opts = UpdraftPlus_Options::get_updraft_option('updraft_dropbox');

		$folder = empty($opts['folder']) ? '' : htmlspecialchars($opts['folder']);
		$key = empty($opts['appkey']) ? '' : $opts['appkey'];

		?>
		<tr class="updraftplusmethod dropbox">
			<th><?php _e('Store at','updraftplus');?>:</th>
			<td><?php if ('dropbox:' != substr($key, 0, 8)) echo 'apps/UpdraftPlus/';?><input type="text" style="width: 292px" id="updraft_dropbox_folder" name="updraft_dropbox[folder]" value="<?php echo $folder;?>" /></td>
		</tr>

		<?php

	}

	public static function change_path($file) {
		$opts = UpdraftPlus_Options::get_updraft_option('updraft_dropbox');
		$folder = empty($opts['folder']) ? '' : $opts['folder'];
		$dropbox_folder = trailingslashit($folder);
		return ($dropbox_folder == '/' || $dropbox_folder == './') ? $file : $dropbox_folder.$file;
	}

}
