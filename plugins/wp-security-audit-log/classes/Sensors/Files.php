<?php

class WSAL_Sensors_Files extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('add_attachment', array($this, 'EventFileUploaded'));
		add_action('delete_attachment', array($this, 'EventFileUploadedDeleted'));
		add_action('admin_init', array($this, 'EventAdminInit'));
	}
	
	protected $IsFileUploaded = false;
	
	public function EventFileUploaded($attachmentID){
		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
		if($action != 'upload-theme' && $action != 'upload-plugin'){
			$file = get_attached_file($attachmentID);
			$this->plugin->alerts->Trigger(2010, array(
				'AttachmentID' => $attachmentID,
				'FileName' => basename($file),
				'FilePath' => dirname($file),
			));
		}
		$this->IsFileUploaded = true;
	}
	
	public function EventFileUploadedDeleted($attachmentID){
		if($this->IsFileUploaded)return;
        $file = get_attached_file($attachmentID);
		$this->plugin->alerts->Trigger(2011, array(
			'AttachmentID' => $attachmentID,
			'FileName' => basename($file),
			'FilePath' => dirname($file),
		));
	}
	
	public function EventAdminInit(){
		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
		$is_theme_editor = basename($_SERVER['SCRIPT_NAME']) == 'theme-editor.php';
		$is_plugin_editor = basename($_SERVER['SCRIPT_NAME']) == 'plugin-editor.php';
		
		if($is_theme_editor && $action == 'update'){
			$this->plugin->alerts->Trigger(2046, array(
				'File' => $_REQUEST['file'],
				'Theme' => $_REQUEST['theme'],
			));
		}
		
		if($is_plugin_editor && $action == 'update'){
			$this->plugin->alerts->Trigger(2051, array(
				'File' => $_REQUEST['file'],
				'Plugin' => $_REQUEST['plugin'],
			));
		}
	}	
}
