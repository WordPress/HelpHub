<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access.');

if (version_compare(phpversion(), '5.3.3', '>=')) {
	require_once(UPDRAFTPLUS_DIR.'/methods/viaaddon-base.php');
	class UpdraftPlus_BackupModule_onedrive extends UpdraftPlus_BackupModule_ViaAddon {
		public function __construct() {
			parent::__construct('onedrive', 'Microsoft OneDrive', '5.3.3', 'onedrive.png');
		}
	}
} else {
	require_once(UPDRAFTPLUS_DIR.'/methods/insufficient.php');
	class UpdraftPlus_BackupModule_onedrive extends UpdraftPlus_BackupModule_insufficientphp {
		public function __construct() {
			parent::__construct('onedrive', 'Microsoft OneDrive', '5.3.3', 'onedrive.png');
		}
	}
}
