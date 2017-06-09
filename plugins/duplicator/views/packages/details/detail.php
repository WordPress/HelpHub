<?php
$view_state = DUP_UI_ViewState::getArray();
$ui_css_general = (isset($view_state['dup-package-dtl-general-panel']) && $view_state['dup-package-dtl-general-panel']) ? 'display:block' : 'display:none';
$ui_css_storage = (isset($view_state['dup-package-dtl-storage-panel']) && $view_state['dup-package-dtl-storage-panel']) ? 'display:block' : 'display:none';
$ui_css_archive = (isset($view_state['dup-package-dtl-archive-panel']) && $view_state['dup-package-dtl-archive-panel']) ? 'display:block' : 'display:none';
$ui_css_install = (isset($view_state['dup-package-dtl-install-panel']) && $view_state['dup-package-dtl-install-panel']) ? 'display:block' : 'display:none';

$link_sql			= "{$package->StoreURL}{$package->NameHash}_database.sql";
$link_archive 		= "{$package->StoreURL}{$package->NameHash}_archive.zip";
$link_installer		= "{$package->StoreURL}{$package->NameHash}_installer.php?get=1&file={$package->NameHash}_installer.php";
$link_log			= "{$package->StoreURL}{$package->NameHash}.log";
$link_scan			= "{$package->StoreURL}{$package->NameHash}_scan.json";

$debug_on	     = DUP_Settings::Get('package_debug');
$mysqldump_on	 = DUP_Settings::Get('package_mysqldump') && DUP_DB::getMySqlDumpPath();
$mysqlcompat_on  = isset($Package->Database->Compatible) && strlen($Package->Database->Compatible);
$mysqlcompat_on  = ($mysqldump_on && $mysqlcompat_on) ? true : false;
$dbbuild_mode    = ($mysqldump_on) ? 'mysqldump' : 'PHP';
?>

<style>
	/*COMMON*/
	div.toggle-box {float:right; margin: 5px 5px 5px 0}
	div.dup-box {margin-top: 15px; font-size:14px; clear: both}
	table.dup-dtl-data-tbl {width:100%}
	table.dup-dtl-data-tbl tr {vertical-align: top}
	table.dup-dtl-data-tbl tr:first-child td {margin:0; padding-top:0 !important;}
	table.dup-dtl-data-tbl td {padding:0 6px 0 0; padding-top:10px !important;}
	table.dup-dtl-data-tbl td:first-child {font-weight: bold; width:150px}
	table.dup-sub-list td:first-child {white-space: nowrap; vertical-align: middle; width: 70px !important;}
	table.dup-sub-list td {white-space: nowrap; vertical-align:top; padding:0 !important; font-size:12px}
	div.dup-box-panel-hdr {font-size:14px; display:block; border-bottom: 1px dotted #efefef; margin:5px 0 5px 0; font-weight: bold; padding: 0 0 5px 0}
	tr.sub-item td:first-child {padding:0 0 0 40px}
	tr.sub-item td {font-size: 12px}
	tr.sub-item-disabled td {color:gray}
	
	/*STORAGE*/
	div.dup-store-pro {font-size:12px; font-style:italic;}
	div.dup-store-pro img {height:14px; width:14px; vertical-align: text-top}
	div.dup-store-pro a {text-decoration: underline}
	
	/*GENERAL*/
	div#dup-name-info, div#dup-version-info {display: none; font-size:11px; line-height:20px; margin:4px 0 0 0}
	div#dup-downloads-area {padding: 5px 0 5px 0; }
	div#dup-downloads-msg {margin-bottom:-5px; font-style: italic}
</style>

<?php if ($package_id == 0) :?>
	<div class="error below-h2"><p><?php _e('Invlaid Package ID request.  Please try again!', 'duplicator'); ?></p></div>
<?php endif; ?>
	
<div class="toggle-box">
	<a href="javascript:void(0)" onclick="Duplicator.Pack.OpenAll()">[open all]</a> &nbsp; 
	<a href="javascript:void(0)" onclick="Duplicator.Pack.CloseAll()">[close all]</a>
</div>
	
<!-- ===============================
GENERAL -->
<div class="dup-box">
<div class="dup-box-title">
	<i class="fa fa-archive"></i> <?php _e('General', 'duplicator') ?>
	<div class="dup-box-arrow"></div>
</div>			
<div class="dup-box-panel" id="dup-package-dtl-general-panel" style="<?php echo $ui_css_general ?>">
	<table class='dup-dtl-data-tbl'>
		<tr>
			<td><?php _e('Name', 'duplicator') ?>:</td>
			<td>
				<a href="javascript:void(0);" onclick="jQuery('#dup-name-info').toggle()"><?php echo $package->Name ?></a> 
				<div id="dup-name-info">
					<b><?php _e('ID', 'duplicator') ?>:</b> <?php echo $package->ID ?><br/>
					<b><?php _e('Hash', 'duplicator') ?>:</b> <?php echo $package->Hash ?><br/>
					<b><?php _e('Full Name', 'duplicator') ?>:</b> <?php echo $package->NameHash ?><br/>
				</div>
			</td>
		</tr>
		<tr>
			<td><?php _e('Notes', 'duplicator') ?>:</td>
			<td><?php echo strlen($package->Notes) ? $package->Notes : __('- no notes -', 'duplicator') ?></td>
		</tr>
		<tr>
			<td><?php _e('Versions', 'duplicator') ?>:</td>
			<td>
				<a href="javascript:void(0);" onclick="jQuery('#dup-version-info').toggle()"><?php echo $package->Version ?></a> 
				<div id="dup-version-info">
					<b><?php _e('WordPress', 'duplicator') ?>:</b> <?php echo strlen($package->VersionWP) ? $package->VersionWP : __('- unknown -', 'duplicator') ?><br/>
					<b><?php _e('PHP', 'duplicator') ?>:</b> <?php echo strlen($package->VersionPHP) ? $package->VersionPHP : __('- unknown -', 'duplicator') ?><br/>
                    <b><?php _e('Mysql', 'duplicator') ?>:</b> 
                    <?php echo strlen($package->VersionDB) ? $package->VersionDB : __('- unknown -', 'duplicator') ?> |
                    <?php echo strlen($package->Database->Comments) ? $package->Database->Comments : __('- unknown -', 'duplicator') ?><br/>
				</div>
			</td>
		</tr>
		<tr>
			<td><?php _e('Runtime', 'duplicator') ?>:</td>
			<td><?php echo strlen($package->Runtime) ? $package->Runtime : __("error running", 'duplicator'); ?></td>
		</tr>
		<tr>
			<td><?php _e('Status', 'duplicator') ?>:</td>
			<td><?php echo ($package->Status >= 100) ? __('completed', 'duplicator')  : __('in-complete', 'duplicator') ?></td>
		</tr>
		<tr>
			<td><?php _e('User', 'duplicator') ?>:</td>
			<td><?php echo strlen($package->WPUser) ? $package->WPUser : __('- unknown -', 'duplicator') ?></td>
		</tr>		
		<tr>
			<td><?php _e('Files', 'duplicator') ?>: </td>
			<td>
				<div id="dup-downloads-area">
					<?php if  (!$err_found) :?>
					
						<button class="button" onclick="Duplicator.Pack.DownloadFile('<?php echo $link_installer; ?>', this);return false;"><i class="fa fa-bolt"></i> Installer</button>						
						<button class="button" onclick="Duplicator.Pack.DownloadFile('<?php echo $link_archive; ?>', this);return false;"><i class="fa fa-file-archive-o"></i> Archive - <?php echo $package->ZipSize ?></button>
						<button class="button" onclick="Duplicator.Pack.DownloadFile('<?php echo $link_sql; ?>', this);return false;"><i class="fa fa-table"></i> &nbsp; SQL - <?php echo DUP_Util::byteSize($package->Database->Size)  ?></button>
						<button class="button" onclick="Duplicator.Pack.DownloadFile('<?php echo $link_log; ?>', this);return false;"><i class="fa fa-list-alt"></i> &nbsp; Log </button>
						<button class="button" onclick="Duplicator.Pack.ShowLinksDialog(<?php echo "'{$link_sql}','{$link_archive}','{$link_installer}','{$link_log}'" ;?>);" class="thickbox"><i class="fa fa-lock"></i> &nbsp; <?php _e("Share", 'duplicator')?></button>
					<?php else: ?>
							<button class="button" onclick="Duplicator.Pack.DownloadFile('<?php echo $link_log; ?>', this);return false;"><i class="fa fa-list-alt"></i> &nbsp; Log </button>
					<?php endif; ?>
				</div>		
				<?php if (!$err_found) :?>
				<table class="dup-sub-list">
					<tr>
						<td><?php _e('Archive', 'duplicator') ?>: </td>
						<td><a href="<?php echo $link_archive ?>" target="_blank"><?php echo $package->Archive->File ?></a></td>
					</tr>
					<tr>
						<td><?php _e('Installer', 'duplicator') ?>: </td>
						<td><a href="<?php echo $link_installer ?>" target="_blank"><?php echo $package->Installer->File ?></a></td>
					</tr>
					<tr>
						<td><?php _e('Database', 'duplicator') ?>: </td>
						<td><a href="<?php echo $link_sql ?>" target="_blank"><?php echo $package->Database->File ?></a></td>
					</tr>
				</table>
				<?php endif; ?>
			</td>
		</tr>	
	</table>
</div>
</div>

<!-- ==========================================
DIALOG: QUICK PATH -->
<?php add_thickbox(); ?>
<div id="dup-dlg-quick-path" title="<?php _e('Download Links', 'duplicator'); ?>" style="display:none">
	<p>
		<i class="fa fa-lock"></i>
		<?php _e("The following links contain sensitive data.  Please share with caution!", 'duplicator');	?>
	</p>
	
	<div style="padding: 0px 15px 15px 15px;">
		<a href="javascript:void(0)" style="display:inline-block; text-align:right" onclick="Duplicator.Pack.GetLinksText()">[Select All]</a> <br/>
		<textarea id="dup-dlg-quick-path-data" style='border:1px solid silver; border-radius:3px; width:99%; height:225px; font-size:11px'></textarea><br/>
		<i style='font-size:11px'><?php _e("The database SQL script is a quick link to your database backup script.  An exact copy is also stored in the package.", 'duplicator'); ?></i>
	</div>
</div>

<!-- ===============================
STORAGE -->
<?php 
	$css_file_filter_on = $package->Archive->FilterOn == 1  ? '' : 'sub-item-disabled';
	$css_db_filter_on   = $package->Database->FilterOn == 1 ? '' : 'sub-item-disabled';
?>
<div class="dup-box">
<div class="dup-box-title">
	<i class="fa fa-database"></i> <?php _e('Storage', 'duplicator') ?>
	<div class="dup-box-arrow"></div>
</div>			
<div class="dup-box-panel" id="dup-package-dtl-storage-panel" style="<?php echo $ui_css_storage ?>">
	<table class="widefat package-tbl">
		<thead>
			<tr>
				<th style='width:150px'><?php _e('Name', 'duplicator') ?></th>
				<th style='width:100px'><?php _e('Type', 'duplicator') ?></th>
				<th style="white-space: nowrap"><?php _e('Location', 'duplicator') ?></th>
			</tr>
		</thead>
			<tbody>
				<tr class="package-row">
					<td><i class="fa fa-server"></i>&nbsp;<?php  _e('Default', 'duplicator');?></td>
					<td><?php _e("Local", 'duplicator'); ?></td>
					<td><?php echo DUPLICATOR_SSDIR_PATH; ?></td>				
				</tr>
				<tr>
					<td colspan="4">
						<div class="dup-store-pro"> 
							<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/amazon-64.png" /> 
							<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/dropbox-64.png" /> 
							<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/google_drive_64px.png" /> 
							<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/ftp-64.png" /> 
							<?php echo sprintf(__('%1$s, %2$s, %3$s, %4$s and more storage options available in', 'duplicator'), 'Amazon', 'Dropbox', 'Google Drive', 'FTP'); ?>
                            <a href="https://snapcreek.com/duplicator/?utm_source=duplicator_free&utm_medium=wordpress_plugin&utm_content=free_storage_detail&utm_campaign=duplicator_pro" target="_blank"><?php _e('Professional', 'duplicator');?></a> 
							 <i class="fa fa-lightbulb-o" 
								data-tooltip-title="<?php _e('Additional Storage:', 'duplicator'); ?>" 
								data-tooltip="<?php _e('Professional allows you to create a package and then store it at a custom location on this server or to a cloud '
										. 'based location such as Google Drive, Amazon, Dropbox or FTP.', 'duplicator'); ?>">
							 </i>
                        </div>                            
					</td>
				</tr>
			</tbody>
	</table>
</div>
</div>


<!-- ===============================
ARCHIVE -->
<?php 
	$css_file_filter_on = $package->Archive->FilterOn == 1  ? '' : 'sub-item-disabled';
	$css_db_filter_on   = $package->Database->FilterOn == 1 ? '' : 'sub-item-disabled';
?>
<div class="dup-box">
<div class="dup-box-title">
	<i class="fa fa-file-archive-o"></i> <?php _e('Archive', 'duplicator') ?>
	<div class="dup-box-arrow"></div>
</div>			
<div class="dup-box-panel" id="dup-package-dtl-archive-panel" style="<?php echo $ui_css_archive ?>">

	<!-- FILES -->
	<div class="dup-box-panel-hdr"><i class="fa fa-files-o"></i> <?php _e('FILES', 'duplicator'); ?></div>
	<table class='dup-dtl-data-tbl'>
		<tr>
			<td><?php _e('Build Mode', 'duplicator') ?>: </td>
			<td><?php _e('ZipArchive', 'duplicator'); ?></td>
		</tr>			

		<?php if ($package->Archive->ExportOnlyDB) : ?>
			<tr>
				<td><?php _e('Database Mode', 'duplicator') ?>: </td>
				<td>
					<?php
						_e('Archive Database Only Enabled', 'duplicator')
					?>
				</td>
			</tr>
		<?php else : ?>
			<tr>
				<td><?php _e('Filters', 'duplicator') ?>: </td>
				<td><?php echo $package->Archive->FilterOn == 1 ? 'On' : 'Off'; ?></td>
			</tr>
			<tr class="sub-item <?php echo $css_file_filter_on ?>">
				<td><?php _e('Directories', 'duplicator') ?>: </td>
				<td>
					<?php
						echo strlen($package->Archive->FilterDirs)
							? str_replace(';', '<br/>', $package->Archive->FilterDirs)
							: __('- no filters -', 'duplicator');
					?>
				</td>
			</tr>
			<tr class="sub-item <?php echo $css_file_filter_on ?>">
				<td><?php _e('Extensions', 'duplicator') ?>: </td>
				<td>
					<?php
						echo isset($package->Archive->FilterExts) && strlen($package->Archive->FilterExts)
							? $package->Archive->FilterExts
							: __('- no filters -', 'duplicator');
					?>
				</td>
			</tr>
			<tr class="sub-item <?php echo $css_file_filter_on ?>">
				<td><?php _e('Files', 'duplicator') ?>: </td>
				<td>
					<i>
						<?php _e('Available in', 'duplicator') ?>
						<a href="https://snapcreek.com/duplicator/?utm_source=duplicator_free&utm_medium=wordpress_plugin&utm_content=free_file_filters&utm_campaign=duplicator_pro" target="_blank"><?php _e('Professional', 'duplicator');?></a>
					</i>
					<i class="fa fa-lightbulb-o"
					   data-tooltip-title="<?php _e('File Filters:', 'duplicator'); ?>"
					   data-tooltip="<?php _e('File filters allows you to select individual files and add them to an exclusion list that will filter them from the package.', 'duplicator'); ?>">
					</i>
				</td>
			</tr>
		<?php endif; ?>
	</table><br/>

	<!-- DATABASE -->
	<div class="dup-box-panel-hdr"><i class="fa fa-table"></i> <?php _e('DATABASE', 'duplicator'); ?></div>
	<table class='dup-dtl-data-tbl'>
		<tr>
			<td><?php _e('Type', 'duplicator') ?>: </td>
			<td><?php echo $package->Database->Type ?></td>
		</tr>
		<tr>
			<td><?php _e('Build Mode', 'duplicator') ?>: </td>
			<td>
				<a href="?page=duplicator-settings" target="_blank"><?php echo $dbbuild_mode; ?></a>
				<?php if ($mysqlcompat_on) : ?>
					<br/>
					<small style="font-style:italic; color:maroon">
						<i class="fa fa-exclamation-circle"></i> <?php _e('MySQL Compatibility Mode Enabled', 'duplicator'); ?>
						<a href="https://dev.mysql.com/doc/refman/5.7/en/mysqldump.html#option_mysqldump_compatible" target="_blank">[<?php _e('details', 'duplicator'); ?>]</a>
					</small>										
				<?php endif; ?>
			</td>
		</tr>			
		<tr>
			<td><?php _e('Filters', 'duplicator') ?>: </td>
			<td><?php echo $package->Database->FilterOn == 1 ? 'On' : 'Off'; ?></td>
		</tr>
		<tr class="sub-item <?php echo $css_db_filter_on ?>">
			<td><?php _e('Tables', 'duplicator') ?>: </td>
			<td>
				<?php 
					echo isset($package->Database->FilterTables) && strlen($package->Database->FilterTables) 
						? str_replace(',', '&nbsp;|&nbsp;', $package->Database->FilterTables)
						: __('- no filters -', 'duplicator');	
				?>
			</td>
		</tr>			
	</table>		
</div>
</div>


<!-- ===============================
INSTALLER -->
<div class="dup-box" style="margin-bottom: 50px">
<div class="dup-box-title">
	<i class="fa fa-bolt"></i> <?php _e('Installer', 'duplicator') ?>
	<div class="dup-box-arrow"></div>
</div>			
<div class="dup-box-panel" id="dup-package-dtl-install-panel" style="<?php echo $ui_css_install ?>">
	<table class='dup-dtl-data-tbl'>
		<tr>
			<td><?php _e('Host', 'duplicator') ?>:</td>
			<td><?php echo strlen($package->Installer->OptsDBHost) ? $package->Installer->OptsDBHost : __('- not set -', 'duplicator') ?></td>
		</tr>
		<tr>
			<td><?php _e('Database', 'duplicator') ?>:</td>
			<td><?php echo strlen($package->Installer->OptsDBName) ? $package->Installer->OptsDBName : __('- not set -', 'duplicator') ?></td>
		</tr>
		<tr>
			<td><?php _e('User', 'duplicator') ?>:</td>
			<td><?php echo strlen($package->Installer->OptsDBUser) ? $package->Installer->OptsDBUser : __('- not set -', 'duplicator') ?></td>
		</tr>	
		<tr>
			<td><?php _e('New URL', 'duplicator') ?>:</td>
			<td><?php echo strlen($package->Installer->OptsURLNew) ? $package->Installer->OptsURLNew : __('- not set -', 'duplicator') ?></td>
		</tr>
	</table>
</div>
</div>

<?php if ($debug_on) : ?>
	<div style="margin:0">
		<a href="javascript:void(0)" onclick="jQuery(this).parent().find('.dup-pack-debug').toggle()">[<?php _e('View Package Object', 'duplicator') ?>]</a><br/>
		<pre class="dup-pack-debug" style="display:none"><?php @print_r($package); ?> </pre>
	</div>
<?php endif; ?>	


<script>
jQuery(document).ready(function ($) 
{
	
	/*	Shows the 'Download Links' dialog
	 *	@param db		The path to the sql file
	 *	@param install	The path to the install file 
	 *	@param pack		The path to the package file */
	Duplicator.Pack.ShowLinksDialog = function(db, install, pack, log) 
	{
		var url = '#TB_inline?width=650&height=350&inlineId=dup-dlg-quick-path';
		tb_show("<?php _e('Package File Links', 'duplicator') ?>", url);
		
		var msg = <?php printf('"%s:\n" + db + "\n\n%s:\n" + install + "\n\n%s:\n" + pack + "\n\n%s:\n" + log;', 
			__("DATABASE",  'duplicator'), 
			__("PACKAGE", 'duplicator'), 
			__("INSTALLER",   'duplicator'),
			__("LOG", 'duplicator')); 
		?>
		$("#dup-dlg-quick-path-data").val(msg);
		return false;
	}
	
	//LOAD: 'Download Links' Dialog and other misc setup
	Duplicator.Pack.GetLinksText = function() {$('#dup-dlg-quick-path-data').select();};	

	/*	METHOD:  */
	Duplicator.Pack.OpenAll = function () {
		$("div.dup-box").each(function() {
			var panel_open = $(this).find('div.dup-box-panel').is(':visible');
			if (! panel_open)
				$( this ).find('div.dup-box-title').trigger("click");
		 });
	};

	/*	METHOD: */
	Duplicator.Pack.CloseAll = function () {
			$("div.dup-box").each(function() {
			var panel_open = $(this).find('div.dup-box-panel').is(':visible');
			if (panel_open)
				$( this ).find('div.dup-box-title').trigger("click");
		 });
	};
});
</script>