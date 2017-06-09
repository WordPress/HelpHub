<?php

interface WSAL_Adapters_ActiveRecordInterface {
	
	public function IsInstalled();
	public function Install();
	public function Uninstall();
	public function Load($cond = '%d', $args = array(1));
	public function Save($activeRecord);
	public function Delete($activeRecord);
	public function LoadMulti($cond, $args = array());
	public function LoadAndCallForEach($callback, $cond = '%d', $args = array(1));
	public function Count($cond = '%d', $args = array(1));
	public function LoadMultiQuery($query, $args = array());
}
