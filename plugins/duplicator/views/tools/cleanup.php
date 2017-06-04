<?php
    $nonce = wp_create_nonce('duplicator_cleanup_page');
	$ajax_nonce	= wp_create_nonce('DUP_CTRL_Tools_deleteInstallerFiles');
	$_GET['action'] = isset($_GET['action']) ? $_GET['action'] : 'display';
	
	if (isset($_GET['action'])) {
		if (($_GET['action'] == 'installer') || ($_GET['action'] == 'legacy') || ($_GET['action'] == 'tmp-cache')) {
			$verify_nonce = $_REQUEST['_wpnonce'];
			if (!wp_verify_nonce($verify_nonce, 'duplicator_cleanup_page')) {
				exit; // Get out of here bad nounce!
			}
		}
	}

	switch ($_GET['action']) {            
		case 'installer' :     
			$action_response = __('Installer file cleanup ran!', 'duplicator');
			$css_hide_msg = 'div.error {display:none}';
			break;		
		case 'legacy': 
			DUP_Settings::LegacyClean();			
			$action_response = __('Legacy data removed.', 'duplicator');
			break;
		case 'tmp-cache': 
			DUP_Package::tempFileCleanup(true);
			$action_response = __('Build cache removed.', 'duplicator');
			break;		
	} 

	$installer_files = DUP_Server::getInstallerFiles();
	$package_name = (isset($_GET['package'])) ?  esc_html($_GET['package']) : '';
	$package_path = (isset($_GET['package'])) ?  DUPLICATOR_WPROOTPATH . esc_html($_GET['package']) : '';

	$txt_found		 = __('File Found', 'duplicator');
	$txt_removed	 = __('File Removed', 'duplicator');
	$txt_archive_msg = __("<b>Archive File:</b> The archive file has a unique hashed name when downloaded.  Leaving the archive file on your server does not impose a security"
						. " risk if the file was not renamed.  It is still recommended to remove the archive file after install,"
						. " especially if it was renamed.", 'duplicator');
?>

<style>
	<?php echo isset($css_hide_msg) ? $css_hide_msg : ''; ?>
	div.success {color:#4A8254}
	div.failed {color:red}
	table.dup-reset-opts td:first-child {font-weight: bold}
	table.dup-reset-opts td {padding:10px}
	form#dup-settings-form {padding: 0px 10px 0px 10px}
	button.dup-fixed-btn {min-width: 150px; text-align: center}
	div#dup-tools-delete-moreinfo {display: none; padding: 5px 0 0 20px; border:1px solid silver; background-color: #fff; border-radius: 5px; padding:10px; margin:5px; width:750px }
</style>

<form id="dup-settings-form" action="?page=duplicator-tools&tab=cleanup" method="post">
	
	<?php if ($_GET['action'] != 'display')  :	?>
		<div id="message" class="updated below-h2">
			<p><b><?php echo $action_response; ?></b></p>
			<?php if ( $_GET['action'] == 'installer') :  ?>
				<?php	
					$html = "";

					//REMOVE CORE INSTALLER FILES
					$installer_files = DUP_Server::getInstallerFiles();
					foreach ($installer_files as $file => $path) {
						@unlink($path);
						echo (file_exists($path)) 
							? "<div class='failed'><i class='fa fa-exclamation-triangle'></i> {$txt_found} - {$path}  </div>"
							: "<div class='success'> <i class='fa fa-check'></i> {$txt_removed} - {$path}	</div>";
					}

					//No way to know exact name of archive file except from installer.
					//The only place where the package can be removed is from installer
					//So just show a message if removing from plugin.
					if (file_exists($package_path)) {
						$path_parts	 = pathinfo($package_name);
						$path_parts	 = (isset($path_parts['extension'])) ? $path_parts['extension'] : '';
						if ($path_parts == "zip" && !is_dir($package_path)) {
							$html .= (@unlink($package_path))
										? "<div class='success'><i class='fa fa-check'></i> {$txt_removed} - {$package_path}</div>"
										: "<div class='failed'><i class='fa fa-exclamation-triangle'></i> {$txt_found} - {$package_path}</div>";
						} 
					}

					echo $html;
				 ?><br/>

				<div style="font-style: italic; max-width:900px">
					<b><?php _e('Security Notes', 'duplicator')?>:</b>
					<?php _e('If the installer files do not successfully get removed with this action, then they WILL need to be removed manually through your hosts control panel,  '
						 . ' file system or FTP.  Please remove all installer files listed above to avoid leaving open security issues on your server.', 'duplicator')?>
					<br/><br/>
					<?php echo $txt_archive_msg; ?>
					<br/><br/>
				</div>
			
			<?php endif; ?>
		</div>
	<?php endif; ?>	
	

	<h2><?php _e('Data Cleanup', 'duplicator')?></h2><hr size="1"/>
	<table class="dup-reset-opts">
		<tr style="vertical-align:text-top">
			<td>
				<button id="dup-remove-installer-files-btn" type="button" class="button button-small dup-fixed-btn" onclick="Duplicator.Tools.deleteInstallerFiles();">
					<?php _e("Remove Installation Files", 'duplicator'); ?>
				</button>
			</td>
			<td>
				<?php _e("Removes all reserved installer files.", 'duplicator'); ?>
				<a href="javascript:void(0)" onclick="jQuery('#dup-tools-delete-moreinfo').toggle()">[<?php _e("more info", 'duplicator'); ?>]</a><br/>

				<div id="dup-tools-delete-moreinfo">
					<?php
						_e("Clicking on the 'Remove Installation Files' button will remove the files used by Duplicator to install this site.  "
						. "These files should not be left on production systems for security reasons.", 'duplicator');
						echo "<br/><br/>";
						
						foreach($installer_files as $file => $path) {
							echo (file_exists($path)) 
								? "<div class='failed'><i class='fa fa-exclamation-triangle'></i> {$txt_found} - {$file}</div>"
								: "<div class='success'><i class='fa fa-check'></i> {$txt_removed} - {$file}</div>";
						}
						echo "<br/>";
						echo $txt_archive_msg;
					?>
				</div>
			</td>
		</tr>
		<tr>
			<td>
				<button type="button" class="button button-small dup-fixed-btn" onclick="Duplicator.Tools.ConfirmClearBuildCache()">
					<?php _e("Clear Build Cache", 'duplicator'); ?>
				</button>
			</td>
			<td><?php _e("Removes all build data from:", 'duplicator'); ?> [<?php echo DUPLICATOR_SSDIR_PATH_TMP ?>].</td>
		</tr>	
	</table>
</form>

<!-- ================
THICK-BOX DIALOG: -->
<?php	
	$confirm2 = new DUP_UI_Dialog();
	$confirm2->title			= __('Clear Build Cache?', 'duplicator');
	$confirm2->message			= __('This process will remove all build cache files.  Be sure no packages are currently building or else they will be cancelled.', 'duplicator');
	$confirm2->jscallback		= 'Duplicator.Tools.ClearBuildCache()';
	$confirm2->initConfirm();
?>

<script>
Duplicator.Tools.deleteInstallerFiles = function()
{
	var data = {
		action: 'DUP_CTRL_Tools_deleteInstallerFiles',
		nonce: '<?php echo $ajax_nonce; ?>',
		'archive-name':  '<?php echo $package_name; ?>'
	};

	jQuery.ajax({
		type: "POST",
		url: ajaxurl,
		dataType: "json",
		data: data,
		complete: function() {
		<?php
			$url = "?page=duplicator-tools&tab=cleanup&action=installer&_wpnonce={$nonce}&package={$package_name}";
			echo "window.location = '{$url}';";
		?>
		},
		error: function(data) {console.log(data)},
		done: function(data) {console.log(data)}
	});
}

jQuery(document).ready(function($) 
{
   	Duplicator.Tools.ConfirmClearBuildCache = function () 
	{
		 <?php $confirm2->showConfirm(); ?>
	}
	
	Duplicator.Tools.ClearBuildCache = function () 
	{
		window.location = '?page=duplicator-tools&tab=cleanup&action=tmp-cache&_wpnonce=<?php echo $nonce; ?>';
	}
});
</script>

