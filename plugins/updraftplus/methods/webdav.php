<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed.');

require_once(UPDRAFTPLUS_DIR.'/methods/viaaddon-base.php');

class UpdraftPlus_BackupModule_webdav extends UpdraftPlus_BackupModule_ViaAddon {
	public function __construct() {
		parent::__construct('webdav', 'WebDAV');
	}
}
