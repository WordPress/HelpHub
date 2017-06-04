<?php

class WSAL_Sensors_PhpErrors extends WSAL_AbstractSensor {
	
	protected $_avoid_error_recursion = false;
	
	protected $_error_types = array(
		0001 => array(1,4,16,64,256,4096),		// errors
		0002 => array(2,32,128,512),			// warnings
		0003 => array(8,1024,2048,8192,16384),	// notices
		0004 => array(),						// exceptions
		0005 => array(),						// shutdown
	);
	
	protected $_maybe_last_error = null;
	
	public function HookEvents() {
		if($this->plugin->settings->IsPhpErrorLoggingEnabled()){
			set_error_handler(array($this, 'EventError'), E_ALL);
			set_exception_handler(array($this, 'EventException'));
			register_shutdown_function(array($this, 'EventShutdown'));
		}
	}
	
	protected function GetErrorHash($code, $mesg, $file, $line){
		return md5(implode(':', func_get_args()));
	}
	
	public function EventError($errno, $errstr, $errfile = 'unknown', $errline = 0, $errcontext = array()){
		if($this->_avoid_error_recursion)return;
		
		$errbacktrace = 'No Backtrace';
		if($this->plugin->settings->IsBacktraceLoggingEnabled()){
			ob_start();
			debug_print_backtrace();
			$errbacktrace = ob_get_clean();
		}
		
		$data = array(
			'Code'    => $errno,
			'Message' => $errstr,
			'File'    => $errfile,
			'Line'    => $errline,
			'Context' => $errcontext,
			'Trace'   => $errbacktrace,
		);
		
		$type = 0002; // default (middle ground)
		foreach($this->_error_types as $temp => $codes){
			if(in_array($errno, $codes)){
				$type = $temp;
			}
		}
		
		$this->_maybe_last_error = $this->GetErrorHash($errno, $errstr, $errfile, $errline);
		
		$this->_avoid_error_recursion = true;
		$this->plugin->alerts->Trigger($type, $data);
		$this->_avoid_error_recursion = false;
	}
	
	public function EventException(Exception $ex){
		if($this->_avoid_error_recursion)return;
		
		$errbacktrace = 'No Backtrace';
		if($this->plugin->settings->IsBacktraceLoggingEnabled()){
			$errbacktrace = $ex->getTraceAsString();
		}
		
		$data = array(
			'Class'   => get_class($ex),
			'Code'    => $ex->getCode(),
			'Message' => $ex->getMessage(),
			'File'    => $ex->getFile(),
			'Line'    => $ex->getLine(),
			'Trace'   => $errbacktrace,
		);
		
		if(method_exists($ex, 'getContext'))
			$data['Context'] = $ex->getContext();
		
		$this->_avoid_error_recursion = true;
		$this->plugin->alerts->Trigger(0004, $data);
		$this->_avoid_error_recursion = false;
	}
	
	public function EventShutdown(){
		if($this->_avoid_error_recursion)return;
		
		if(!!($e = error_get_last()) && ($this->_maybe_last_error != $this->GetErrorHash($e['type'], $e['message'], $e['file'], $e['line']))){
			$data = array(
				'Code'    => $e['type'],
				'Message' => $e['message'],
				'File'    => $e['file'],
				'Line'    => $e['line'],
			);
			
			$this->_avoid_error_recursion = true;
			$this->plugin->alerts->Trigger(0005, $data);
			$this->_avoid_error_recursion = false;
		}
	}
	
}
