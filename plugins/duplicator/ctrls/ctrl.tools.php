<?php
if ( ! defined('DUPLICATOR_VERSION') ) exit; // Exit if accessed directly

require_once(DUPLICATOR_PLUGIN_PATH . '/ctrls/ctrl.base.php'); 
require_once(DUPLICATOR_PLUGIN_PATH . '/classes/utilities/class.u.scancheck.php');

/**
 * Controller for Tools 
 * @package Dupicator\ctrls
 */
class DUP_CTRL_Tools extends DUP_CTRL_Base
{	 
	/**
     *  Init this instance of the object
     */
	function __construct() 
	{
		add_action('wp_ajax_DUP_CTRL_Tools_runScanValidator', array($this, 'runScanValidator'));
		add_action('wp_ajax_DUP_CTRL_Tools_deleteInstallerFiles', array($this, 'deleteInstallerFiles'));
	}
	
	/** 
     * Calls the ScanValidator and returns a JSON result
	 * 
	 * @param string $_POST['scan-path']		The path to start scanning from, defaults to DUPLICATOR_WPROOTPATH
	 * @param bool   $_POST['scan-recursive']	Recursivly search the path
	 * 
	 * @notes: Testing = /wp-admin/admin-ajax.php?action=DUP_CTRL_Tools_runScanValidator
     */
	public function runScanValidator($post)
	{
        @set_time_limit(0);
		$post = $this->postParamMerge($post);
		check_ajax_referer($post['action'], 'nonce');
		
		$result = new DUP_CTRL_Result($this);
		 
		try 
		{
			//CONTROLLER LOGIC
			$path = isset($post['scan-path']) ? $post['scan-path'] : DUPLICATOR_WPROOTPATH;
			if (!is_dir($path)) {
				throw new Exception("Invalid directory provided '{$path}'!");
			}
			$scanner = new DUP_ScanCheck();
			$scanner->recursion = (isset($post['scan-recursive']) && $post['scan-recursive'] != 'false') ? true : false;
			$payload = $scanner->run($path);

			//RETURN RESULT
			$test = ($payload->fileCount > 0)
					? DUP_CTRL_Status::SUCCESS
					: DUP_CTRL_Status::FAILED;
			$result->process($payload, $test);
		} 
		catch (Exception $exc) 
		{
			$result->processError($exc);
		}
    }


	/**
     * Removed all reserved installer files names
	 *
	 * @param string $_POST['archive-name']		The name of the archive file used to create this site
	 *
	 * @notes: Testing = /wp-admin/admin-ajax.php?action=DUP_CTRL_Tools_deleteInstallerFiles
     */
	public function deleteInstallerFiles($post)
	{
		$post = $this->postParamMerge($post);
		check_ajax_referer($post['action'], 'nonce');
		$result = new DUP_CTRL_Result($this);
		try
		{
			//CONTROLLER LOGIC
			$installer_files = DUP_Server::getInstallerFiles();
			//array_push($installer_files, $package_path);
			foreach($installer_files as $file => $path) {
				if (! is_dir($path)) {
					@chmod($path, 0777);
					$status = (@unlink($path) === false) ? false : true;
					$payload[] = array(
						'file' => $path,
						'removed' => $status,
						'writable' => is_writable($path),
						'readable' => is_readable($path),
						'exists' => file_exists($path)
					);
				}
			}

			//RETURN RESULT
			$test = (in_array(true, $payload['exists']))
					? DUP_CTRL_Status::FAILED
					: DUP_CTRL_Status::SUCCESS;
			$result->process($payload, $test);
		}
		catch (Exception $exc)
		{
			$result->processError($exc);
		}
    }
	
}
