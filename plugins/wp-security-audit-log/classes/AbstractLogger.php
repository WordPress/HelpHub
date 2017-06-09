<?php

abstract class WSAL_AbstractLogger {
	/**
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;

	public function __construct(WpSecurityAuditLog $plugin){
		$this->plugin = $plugin;
	}
	
	public abstract function Log($type, $data = array(), $date = null, $siteid = null, $migrated = false);
}