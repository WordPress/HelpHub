<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access.');

require_once(UPDRAFTPLUS_DIR.'/methods/viaaddon-base.php');

class UpdraftPlus_BackupModule_copycom extends UpdraftPlus_BackupModule_ViaAddon {
	public function __construct() {
		parent::__construct('copycom', 'Copy.Com', '5.2.4', 'copycom.png');
	}
}
