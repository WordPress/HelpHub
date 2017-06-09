<?php

global $wpdb;

//POST BACK
$action_updated = null;
if (isset($_POST['action']))
{
    $action_result = DUP_Settings::DeleteWPOption($_POST['action']);
    switch ($_POST['action'])
    {
        case 'duplicator_package_active' : $action_response = __('Package settings have been reset.', 'duplicator');
            break;
    }
}

DUP_Util::initSnapshotDirectory();

$Package = DUP_Package::getActive();
$dup_tests = array();
$dup_tests = DUP_Server::getRequirements();
$default_name = DUP_Package::getDefaultName();

//View State
$ctrl_ui = new DUP_CTRL_UI();
$ctrl_ui->setResponseType('PHP');
$data = $ctrl_ui->GetViewStateList();

$ui_css_storage = (isset($data->payload['dup-pack-storage-panel']) && $data->payload['dup-pack-storage-panel']) ? 'display:block' : 'display:none';
$ui_css_archive = (isset($data->payload['dup-pack-archive-panel']) && $data->payload['dup-pack-archive-panel']) ? 'display:block' : 'display:none';
$ui_css_installer = (isset($data->payload['dup-pack-installer-panel']) && $data->payload['dup-pack-installer-panel']) ? 'display:block' : 'display:none';
$dup_intaller_files = implode(", ", array_keys(DUP_Server::getInstallerFiles()));
$dbbuild_mode = (DUP_Settings::Get('package_mysqldump') && DUP_DB::getMySqlDumpPath()) ? 'mysqldump' : 'PHP';

?>

<style>
    /* -----------------------------
    REQUIREMENTS*/
    div.dup-sys-section {margin:1px 0px 5px 0px}
    div.dup-sys-title {display:inline-block; width:250px; padding:1px; }
    div.dup-sys-title div {display:inline-block;float:right; }
    div.dup-sys-info {display:none; max-width: 98%; margin:4px 4px 12px 4px}	
    div.dup-sys-pass {display:inline-block; color:green;}
    div.dup-sys-fail {display:inline-block; color:#AF0000;}
    div.dup-sys-contact {padding:5px 0px 0px 10px; font-size:11px; font-style:italic}
    span.dup-toggle {float:left; margin:0 2px 2px 0; }
    table.dup-sys-info-results td:first-child {width:200px}
</style>

<!-- ============================
TOOL BAR: STEPS -->
<table id="dup-toolbar">
    <tr valign="top">
        <td style="white-space: nowrap">
            <div id="dup-wiz">
                <div id="dup-wiz-steps">
                    <div class="active-step"><a>1-<?php _e('Setup', 'duplicator'); ?></a></div>
                    <div><a>2-<?php _e('Scan', 'duplicator'); ?> </a></div>
                    <div><a>3-<?php _e('Build', 'duplicator'); ?> </a></div>
                </div>
                <div id="dup-wiz-title">
					<?php _e('Step 1: Package Setup', 'duplicator'); ?>
                </div> 
            </div>	
        </td>
        <td>
            <a id="dup-pro-create-new"  href="?page=duplicator" class="add-new-h2"><i class="fa fa-archive"></i> <?php _e("All Packages", 'duplicator'); ?></a>
			<span> <?php _e("Create New", 'duplicator'); ?></span>
        </td>
    </tr>
</table>	
<hr class="dup-toolbar-line">

<?php if (!empty($action_response)) : ?>
    <div id="message" class="updated below-h2"><p><?php echo $action_response; ?></p></div>
<?php endif; ?>	

<!-- ============================
SYSTEM REQUIREMENTS -->
<div class="dup-box dup-box-fancy">
    <div class="dup-box-title dup-box-title-fancy">
        <?php
			_e("Requirements:", 'duplicator');
			echo ($dup_tests['Success']) ? ' <div class="dup-sys-pass">Pass</div>' : ' <div class="dup-sys-fail">Fail</div>';
        ?>
        <div class="dup-box-arrow"></div>
    </div>

    <div class="dup-box-panel" style="<?php echo ($dup_tests['Success']) ? 'display:none' : ''; ?>">

        <div class="dup-sys-section">
            <i><?php _e("System requirements must pass for the Duplicator to work properly.  Click each link for details.", 'duplicator'); ?></i>
        </div>

        <!-- PHP SUPPORT -->
        <div class='dup-sys-req'>
            <div class='dup-sys-title'>
                <a><?php _e('PHP Support', 'duplicator'); ?></a>
                <div><?php echo $dup_tests['PHP']['ALL']; ?></div>
            </div>
            <div class="dup-sys-info dup-info-box">
                <table class="dup-sys-info-results">
                    <tr>
                        <td><?php printf("%s [%s]", __("PHP Version", 'duplicator'), phpversion()); ?></td>
                        <td><?php echo $dup_tests['PHP']['VERSION'] ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Zip Archive Enabled', 'duplicator'); ?></td>
                        <td><?php echo $dup_tests['PHP']['ZIP'] ?></td>
                    </tr>					
                    <tr>
                        <td><?php _e('Safe Mode Off', 'duplicator'); ?></td>
                        <td><?php echo $dup_tests['PHP']['SAFE_MODE'] ?></td>
                    </tr>					
                    <tr>
                        <td><?php _e('Function', 'duplicator'); ?> <a href="http://php.net/manual/en/function.file-get-contents.php" target="_blank">file_get_contents</a></td>
                        <td><?php echo $dup_tests['PHP']['FUNC_1'] ?></td>
                    </tr>					
                    <tr>
                        <td><?php _e('Function', 'duplicator'); ?> <a href="http://php.net/manual/en/function.file-put-contents.php" target="_blank">file_put_contents</a></td>
                        <td><?php echo $dup_tests['PHP']['FUNC_2'] ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Function', 'duplicator'); ?> <a href="http://php.net/manual/en/mbstring.installation.php" target="_blank">mb_strlen</a></td>
                        <td><?php echo $dup_tests['PHP']['FUNC_3'] ?></td>
                    </tr>					
                </table>
                <small>
					<?php _e("PHP versions 5.2.9+ or higher is required.  For compression to work the ZipArchive extension for PHP is required. Safe Mode should be set to 'Off' in you php.ini file and is deprecated as of PHP 5.3.0.  For any issues in this section please contact your hosting provider or server administrator.  For additional information see our online documentation.", 'duplicator'); ?>
                </small>
            </div>
        </div>		

        <!-- PERMISSIONS -->
        <div class='dup-sys-req'>
            <div class='dup-sys-title'>
                <a><?php _e('Required Paths', 'duplicator'); ?></a> <div><?php echo $dup_tests['IO']['ALL']; ?></div>
            </div>
            <div class="dup-sys-info dup-info-box">
				<?php
				printf("<b>%s</b> &nbsp; [%s] <br/>", $dup_tests['IO']['SSDIR'], DUPLICATOR_SSDIR_PATH);
				printf("<b>%s</b> &nbsp; [%s] <br/>", $dup_tests['IO']['SSTMP'], DUPLICATOR_SSDIR_PATH_TMP);
				?>
				<br/>
				<div style="font-size:11px">
					<?php 
						_e("If Duplicator does not have enough permissions then you will need to manually create the paths above. &nbsp; ", 'duplicator'); 
						if ($dup_tests['IO']['WPROOT'] == 'Fail')
						{
							echo sprintf( __('The root WordPress path [%s] is currently not writable by PHP.', 'duplicator'), 	DUPLICATOR_WPROOTPATH);
						}
					?>
				</div>
            </div>
        </div>

        <!-- SERVER SUPPORT -->
        <div class='dup-sys-req'>
            <div class='dup-sys-title'>
                <a><?php _e('Server Support', 'duplicator'); ?></a>
                <div><?php echo $dup_tests['SRV']['ALL']; ?></div>
            </div>
            <div class="dup-sys-info dup-info-box">
                <table class="dup-sys-info-results">
                    <tr>
                        <td><?php printf("%s [%s]", __("MySQL Version", 'duplicator'), DUP_DB::getVersion()); ?></td>
                        <td><?php echo $dup_tests['SRV']['MYSQL_VER'] ?></td>
                    </tr>
                    <tr>
                        <td><?php printf("%s", __("MySQLi Support", 'duplicator')); ?></td>
                        <td><?php echo $dup_tests['SRV']['MYSQLi'] ?></td>
                    </tr>
                </table>
                <small>
                    <?php
                    _e("MySQL version 5.0+ or better is required and the PHP MySQLi extension (note the trailing 'i') is also required.  Contact your server administrator and request that mysqli extension and MySQL Server 5.0+ be installed.", 'duplicator');
                    echo "&nbsp;<i><a href='http://php.net/manual/en/mysqli.installation.php' target='_blank'>[" . __('more info', 'duplicator') . "]</a></i>";
                    ?>										
                </small>
            </div>
        </div>

        <!-- RESERVED FILES -->
        <div class='dup-sys-req'>
            <div class='dup-sys-title'>
                <a><?php _e('Reserved Files', 'duplicator'); ?></a> <div><?php echo $dup_tests['RES']['INSTALL']; ?></div>
            </div>
            <div class="dup-sys-info dup-info-box">
                <?php if ($dup_tests['RES']['INSTALL'] == 'Pass') : ?>
                        <?php 
							_e("None of the reserved files where found from a previous install.  This means you are clear to create a new package.", 'duplicator');
							echo "  [{$dup_intaller_files}]";
						?>
                    <?php else: 
                        $duplicator_nonce = wp_create_nonce('duplicator_cleanup_page');
                    ?> 
                    <form method="post" action="admin.php?page=duplicator-tools&tab=cleanup&action=installer&_wpnonce=<?php echo $duplicator_nonce; ?>">
						<b><?php _e('WordPress Root Path:', 'duplicator'); ?></b>  <?php echo DUPLICATOR_WPROOTPATH; ?><br/>
						<?php _e("A reserved file(s) was found in the WordPress root directory. Reserved file names include [{$dup_intaller_files}].  To archive your data correctly please remove any of these files from your WordPress root directory.  Then try creating your package again.", 'duplicator'); ?>
                        <br/><input type='submit' class='button button-small' value='<?php _e('Remove Files Now', 'duplicator') ?>' style='font-size:10px; margin-top:5px;' />
                    </form>
				<?php endif; ?>
            </div>
        </div>

        <!-- ONLINE SUPPORT -->
        <div class="dup-sys-contact">
            <?php
            printf("%s <a href='admin.php?page=duplicator-help'>[%s]</a>", __("For additional help please see the ", 'duplicator'), __("help page", 'duplicator'));
            ?>
        </div>

    </div>
</div><br/>


<!-- ============================
FORM PACKAGE OPTIONS -->
<div style="padding:5px 5px 2px 5px">
	<?php include('new1.inc.form.php'); ?>
</div>

<script>
jQuery(document).ready(function ($) 
{
	//Init: Toogle for system requirment detial links
	$('.dup-sys-title a').each(function () {
		$(this).attr('href', 'javascript:void(0)');
		$(this).click({selector: '.dup-sys-info'}, Duplicator.Pack.ToggleSystemDetails);
		$(this).prepend("<span class='ui-icon ui-icon-triangle-1-e dup-toggle' />");
	});

	//Init: Color code Pass/Fail/Warn items
	$('.dup-sys-title div').each(function () {
		$(this).addClass(($(this).text() == 'Pass') ? 'dup-sys-pass' : 'dup-sys-fail');
	});
});
</script>