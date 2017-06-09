<?php
if ( ! defined('DUPLICATOR_VERSION') ) exit; // Exit if accessed directly

/**
 * Helper Class for logging
 * @package Dupicator\classes
 */
class DUP_Log {
	
	/**
	 * The file handle used to write to the log file
	 * @var file resource 
	 */
	private static $logFileHandle;
	
	/**
	 *  Open a log file connection for writing
	 *  @param string $name Name of the log file to create
	 */
	static public function Open($name) {
		if (! isset($name)) throw new Exception("A name value is required to open a file log.");
		self::$logFileHandle = @fopen(DUPLICATOR_SSDIR_PATH . "/{$name}.log", "c+");	
	}
	
	/**
	 *  Close the log file connection
	 */
	static public function Close() {
		 @fclose(self::$logFileHandle);
	}
	
	/**
	 *  General information logging
	 *  @param string $msg	The message to log
	 * 
	 *  REPLACE TO DEBUG: Memory consuption as script runs	
	 *	$results = DUP_Util::byteSize(memory_get_peak_usage(true)) . "\t" . $msg;
	 *	@fwrite(self::$logFileHandle, "{$results} \n"); 
	 */
	static public function Info($msg) {
		@fwrite(self::$logFileHandle, "{$msg} \n"); 
		//$results = DUP_Util::byteSize(memory_get_usage(true)) . "\t" . $msg;
		//@fwrite(self::$logFileHandle, "{$results} \n"); 
	}
	
	/**
	*  Called when an error is detected and no further processing should occur
	*  @param string $msg The message to log
	*  @param string $details Additional details to help resolve the issue if possible
	*/
	static public function Error($msg, $detail) {
		
		$source = self::getStack(debug_backtrace());
		
		$err_msg  = "\n==================================================================================\n";
		$err_msg .= "DUPLICATOR ERROR\n";
		$err_msg .= "Please try again! If the error persists see the Duplicator 'Help' menu.\n";
		$err_msg .= "---------------------------------------------------------------------------------\n";
		$err_msg .= "MESSAGE:\n\t{$msg}\n";
		if (strlen($detail)) {
			$err_msg .= "DETAILS:\n\t{$detail}\n";
		}
		$err_msg .= "TRACE:\n{$source}";
		$err_msg .= "==================================================================================\n\n";
		@fwrite(self::$logFileHandle, "{$err_msg}"); 
		die("DUPLICATOR ERROR: Please see the 'Package Log' file link below.");
	}
	
	
	/** 
	 * The current strack trace of a PHP call
	 * @param $stacktrace The current debug stack
	 * @return string 
	 */ 
    public static function getStack($stacktrace) {
        $output = "";
        $i = 1;
        foreach($stacktrace as $node) {
            $output .= "\t $i. ".basename($node['file']) ." : " .$node['function'] ." (" .$node['line'].")\n";
            $i++;
        }
		return $output;
    } 

}
?>