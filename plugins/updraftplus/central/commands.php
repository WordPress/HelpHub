<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No access.');

/*
	- A container for all the RPC commands implemented. Commands map exactly onto method names (and hence this class should not implement anything else, beyond the constructor, and private methods)
	- Return format is array('response' => (string - a code), 'data' => (mixed));
	
	RPC commands are not allowed to begin with an underscore. So, any private methods can be prefixed with an underscore.
	
*/

abstract class UpdraftCentral_Commands {

	protected $rc;
	protected $ud;

	public function __construct($rc) {
		$this->rc = $rc;
		global $updraftplus;
		$this->ud = $updraftplus;
	}

	final protected function _admin_include() {
		$files = func_get_args();
		foreach ($files as $file) {
			require_once(ABSPATH.'/wp-admin/includes/'.$file);
		}
	}
	
	final protected function _frontend_include() {
		$files = func_get_args();
		foreach ($files as $file) {
			require_once(ABSPATH.WPINC.'/'.$file);
		}
	}
	
	final protected function _response($data = null, $code = 'rpcok') {
		return apply_filters('updraftplus_remotecontrol_response', array(
			'response' => $code,
			'data' => $data
		), $data, $code);
	}
	
	final protected function _generic_error_response($code = 'central_unspecified', $data = null) {
		return $this->_response(
			array(
				'code' => $code,
				'data' => $data
			),
			'rpcerror'
		);
	}
	
}
