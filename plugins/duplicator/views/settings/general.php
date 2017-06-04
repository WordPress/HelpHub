<?php
global $wp_version;
global $wpdb;

$action_updated = null;
$action_response = __("General Settings Saved", 'duplicator');

//SAVE RESULTS
if (isset($_POST['action']) && $_POST['action'] == 'save') {
	
	//Nonce Check
	if (! isset( $_POST['dup_settings_save_nonce_field'] ) || ! wp_verify_nonce( $_POST['dup_settings_save_nonce_field'], 'dup_settings_save' ) ) 
	{
		die('Invalid token permissions to perform this request.');
	}
	
    DUP_Settings::Set('uninstall_settings', isset($_POST['uninstall_settings']) ? "1" : "0");
    DUP_Settings::Set('uninstall_files', isset($_POST['uninstall_files']) ? "1" : "0");
    DUP_Settings::Set('uninstall_tables', isset($_POST['uninstall_tables']) ? "1" : "0");
    DUP_Settings::Set('storage_htaccess_off', isset($_POST['storage_htaccess_off']) ? "1" : "0");

    DUP_Settings::Set('wpfront_integrate', isset($_POST['wpfront_integrate']) ? "1" : "0");
	DUP_Settings::Set('package_debug', isset($_POST['package_debug']) ? "1" : "0");
    
    $action_updated = DUP_Settings::Save();
    DUP_Util::initSnapshotDirectory();
}

$uninstall_settings = DUP_Settings::Get('uninstall_settings');
$uninstall_files = DUP_Settings::Get('uninstall_files');
$uninstall_tables = DUP_Settings::Get('uninstall_tables');
$storage_htaccess_off = DUP_Settings::Get('storage_htaccess_off');

$wpfront_integrate = DUP_Settings::Get('wpfront_integrate');
$wpfront_ready = apply_filters('wpfront_user_role_editor_duplicator_integration_ready', false);
$package_debug = DUP_Settings::Get('package_debug');

?>

<style>
    form#dup-settings-form input[type=text] {width: 400px; }
    div.dup-feature-found {padding:3px; border:1px solid silver; background: #f7fcfe; border-radius: 3px; width:400px; font-size: 12px}
    div.dup-feature-notfound {padding:5px; border:1px solid silver; background: #fcf3ef; border-radius: 3px; width:500px; font-size: 13px; line-height: 18px}
</style>

<form id="dup-settings-form" action="<?php echo admin_url('admin.php?page=duplicator-settings&tab=general'); ?>" method="post">

    <?php wp_nonce_field('dup_settings_save', 'dup_settings_save_nonce_field'); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="page"   value="duplicator-settings">

    <?php if ($action_updated) : ?>
        <div id="message" class="updated below-h2"><p><?php echo $action_response; ?></p></div>
    <?php endif; ?>	


    <h3 class="title"><?php _e("Plugin", 'duplicator') ?> </h3>
    <hr size="1" />
    <table class="form-table">
        <tr valign="top">
            <th scope="row"><label><?php _e("Version", 'duplicator'); ?></label></th>
            <td><?php echo DUPLICATOR_VERSION ?></td>
        </tr>	
        <tr valign="top">
            <th scope="row"><label><?php _e("Uninstall", 'duplicator'); ?></label></th>
            <td>
                <input type="checkbox" name="uninstall_settings" id="uninstall_settings" <?php echo ($uninstall_settings) ? 'checked="checked"' : ''; ?> /> 
                <label for="uninstall_settings"><?php _e("Delete Plugin Settings", 'duplicator') ?> </label><br/>

                <input type="checkbox" name="uninstall_files" id="uninstall_files" <?php echo ($uninstall_files) ? 'checked="checked"' : ''; ?> /> 
                <label for="uninstall_files"><?php _e("Delete Entire Storage Directory", 'duplicator') ?></label><br/>

            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php _e("Storage", 'duplicator'); ?></label></th>
            <td>
                <?php _e("Full Path", 'duplicator'); ?>: 
                <?php echo DUP_Util::safePath(DUPLICATOR_SSDIR_PATH); ?><br/><br/>
                <input type="checkbox" name="storage_htaccess_off" id="storage_htaccess_off" <?php echo ($storage_htaccess_off) ? 'checked="checked"' : ''; ?> /> 
                <label for="storage_htaccess_off"><?php _e("Disable .htaccess File In Storage Directory", 'duplicator') ?> </label>
                <p class="description">
                    <?php _e("Disable if issues occur when downloading installer/archive files.", 'duplicator'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label><?php _e("Custom Roles", 'duplicator'); ?></label></th>
            <td>
                <input type="checkbox" name="wpfront_integrate" id="wpfront_integrate" <?php echo ($wpfront_integrate) ? 'checked="checked"' : ''; ?> <?php echo $wpfront_ready ? '' : 'disabled'; ?> />
                <label for="wpfront_integrate"><?php _e("Enable User Role Editor Plugin Integration", 'duplicator'); ?></label>
					<p class="description">
						<?php printf('%s <a href="https://wordpress.org/plugins/wpfront-user-role-editor/" target="_blank">%s</a> %s'
									 . ' <a href="https://wpfront.com/user-role-editor-pro/?ref=3" target="_blank">%s</a> %s '
									 . ' <a href="https://wpfront.com/integrations/duplicator-integration/" target="_blank">%s</a>',
								__('The User Role Editor Plugin', 'duplicator'),
								__('Free', 'duplicator'),
								__('or', 'duplicator'),
								__('Professional', 'duplicator'),
								__('must be installed to use', 'duplicator'),
								__('this feature.', 'duplicator')
								);
						?>
					</p>
            </td>
        </tr>
    </table>


    <h3 class="title"><?php _e("Debug", 'duplicator') ?> </h3>
    <hr size="1" />
    <table class="form-table">
        <tr>
            <th scope="row"><label><?php _e("Debugging", 'duplicator'); ?></label></th>
            <td>
                <input type="checkbox" name="package_debug" id="package_debug" <?php echo ($package_debug) ? 'checked="checked"' : ''; ?> />
                <label for="package_debug"><?php _e("Enable debug options throughout user interface", 'duplicator'); ?></label>
				<p class="description"><?php  _e("Refresh page after saving to show/hide Debug menu", 'duplicator'); ?></p>
            </td>
        </tr>
    </table><br/>

    <p class="submit" style="margin: 20px 0px 0xp 5px;">
		<br/>
		<input type="submit" name="submit" id="submit" class="button-primary" value="<?php _e("Save General Settings", 'duplicator') ?>" style="display: inline-block;" />
	</p>
	
</form>

<script>
jQuery(document).ready(function($) 
{
	$('#package_ui_created').val(<?php echo $package_ui_created ?> );
});
</script>