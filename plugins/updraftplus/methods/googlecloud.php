<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access.');

if (class_exists('UpdraftPlus_BackupModule_googlecloud')) return;

if (version_compare(PHP_VERSION, '5.2.4', '>=')) {
	require_once(UPDRAFTPLUS_DIR.'/methods/viaaddon-base.php');
	class UpdraftPlus_BackupModule_googlecloud extends UpdraftPlus_BackupModule_ViaAddon {
		public function __construct() {
			parent::__construct('googlecloud', 'Google Cloud', '5.2.4', 'googlecloud.png');
		}
	}
} else {
	require_once(UPDRAFTPLUS_DIR.'/methods/insufficient.php');
	class UpdraftPlus_BackupModule_googlecloud extends UpdraftPlus_BackupModule_insufficientphp {
		public function __construct() {
			parent::__construct('googlecloud', 'Google Cloud', '5.2.4', 'googlecloud.png');
		}
	}
}
