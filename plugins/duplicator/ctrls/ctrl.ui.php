<?php
if ( ! defined('DUPLICATOR_VERSION') ) exit; // Exit if accessed directly

require_once(DUPLICATOR_PLUGIN_PATH . '/ctrls/ctrl.base.php'); 
require_once(DUPLICATOR_PLUGIN_PATH . '/classes/ui/class.ui.viewstate.php');

/**
 * Controller for Tools 
 * @package Dupicator\ctrls
 */
class DUP_CTRL_UI extends DUP_CTRL_Base
{	 
	
	function __construct() 
	{
		add_action('wp_ajax_DUP_CTRL_UI_SaveViewState',	      array($this,	  'SaveViewState'));
		add_action('wp_ajax_DUP_CTRL_UI_GetViewStateList',	  array($this,	  'GetViewStateList'));
	}


	/** 
     * Calls the SaveViewState and returns a JSON result
	 * 
	 * @param string $_POST['key']		A unique key that idetifies the state of the UI element
	 * @param bool   $_POST['value']	The value to store for the state of the UI element
	 * 
	 * @notes: Testing: See Testing Interface
	 * URL = /wp-admin/admin-ajax.php?action=DUP_CTRL_UI_SaveViewState
	 * 
	 * <code>
	 * //JavaScript Ajax Request
	 * Duplicator.UI.SaveViewState('dup-pack-archive-panel', 1);
	 * 
	 * //Call PHP Code
	 * $view_state       = DUP_UI_ViewState::getValue('dup-pack-archive-panel');
	 * $ui_css_archive   = ($view_state == 1)   ? 'display:block' : 'display:none';
	 * </code>
     */
	public function SaveViewState($post) 
	{
		$post = $this->postParamMerge($post);
		$result = new DUP_CTRL_Result($this);
	
		try 
		{
			//CONTROLLER LOGIC
			$post  = stripslashes_deep($_POST);
			$key   = esc_html($post['key']);
			$value = esc_html($post['value']);
			$success = DUP_UI_ViewState::save($key, $value);

			$payload = array();
			$payload['key']    = $key;
			$payload['value']  = $value;
			$payload['update-success'] = $success;
			
			//RETURN RESULT
			$test = ($success) 
					? DUP_CTRL_Status::SUCCESS
					: DUP_CTRL_Status::FAILED;
			return $result->process($payload, $test);
		} 
		catch (Exception $exc) 
		{
			$result->processError($exc);
		}
    }
	
	/** 
     * Returns a JSON list of all saved view state items
	 * 
	 * @notes: Testing: See Testing Interface
	 * URL = /wp-admin/admin-ajax.php?action=DUP_CTRL_UI_GetViewStateList
	 * 
	 * <code>
	 *	See SaveViewState()
	 * </code>
     */
	public function GetViewStateList() 
	{
		$result = new DUP_CTRL_Result($this);
		
		try 
		{
			//CONTROLLER LOGIC
			$payload = DUP_UI_ViewState::getArray();
			
			//RETURN RESULT
			$test = (count($payload)) 
					? DUP_CTRL_Status::SUCCESS
					: DUP_CTRL_Status::FAILED;
			return $result->process($payload, $test);
		} 
		catch (Exception $exc) 
		{
			$result->processError($exc);
		}
    }	
	
}
