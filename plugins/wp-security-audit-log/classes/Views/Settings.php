<?php
class WSAL_Views_Settings extends WSAL_AbstractView
{

    public $adapterMsg = '';
    
    public function __construct(WpSecurityAuditLog $plugin)
    {
        parent::__construct($plugin);

        add_action('wp_ajax_AjaxCheckSecurityToken', array($this, 'AjaxCheckSecurityToken'));
        add_action('wp_ajax_AjaxRunCleanup', array($this, 'AjaxRunCleanup'));
        add_action('wp_ajax_AjaxGetAllUsers', array($this, 'AjaxGetAllUsers'));
        add_action('wp_ajax_AjaxGetAllRoles', array($this, 'AjaxGetAllRoles'));
    }
    
    public function HasPluginShortcutLink(){
        return true;
    }
    
    public function GetTitle() {
        return __('Settings', 'wp-security-audit-log');
    }
    
    public function GetIcon() {
        return 'dashicons-admin-generic';
    }
    
    public function GetName() {
        return __('Settings', 'wp-security-audit-log');
    }
    
    public function GetWeight() {
        return 3;
    }
    
    protected function GetTokenType($token){
        $users = array();
        foreach (get_users('blog_id=0&fields[]=user_login') as $obj)
            $users[] = $obj->user_login;
        $roles = array_keys(get_editable_roles());
        
        if(in_array($token, $users))return 'user';
        if(in_array($token, $roles))return 'role';
        return 'other';
    }
    
    protected function Save()
    {
        check_admin_referer('wsal-settings');
        $this->_plugin->settings->SetPruningDateEnabled($_REQUEST['PruneBy'] == 'date');
        $this->_plugin->settings->SetPruningDate($_REQUEST['PruningDate']);
        $this->_plugin->settings->SetPruningLimitEnabled($_REQUEST['PruneBy'] == 'limit');
        $this->_plugin->settings->SetPruningLimit($_REQUEST['PruningLimit']);

        $this->_plugin->settings->SetFromEmail($_REQUEST['FromEmail']);
        $this->_plugin->settings->SetDisplayName($_REQUEST['DisplayName']);

        $this->_plugin->settings->SetWidgetsEnabled($_REQUEST['EnableDashboardWidgets']);
        $this->_plugin->settings->SetAllowedPluginViewers(isset($_REQUEST['Viewers']) ? $_REQUEST['Viewers'] : array());
        $this->_plugin->settings->SetAllowedPluginEditors(isset($_REQUEST['Editors']) ? $_REQUEST['Editors'] : array());

        $this->_plugin->settings->SetExcludedMonitoringUsers(isset($_REQUEST['ExUsers']) ? $_REQUEST['ExUsers'] : array());
        $this->_plugin->settings->SetExcludedMonitoringRoles(isset($_REQUEST['ExRoles']) ? $_REQUEST['ExRoles'] : array());
        $this->_plugin->settings->SetExcludedMonitoringCustom(isset($_REQUEST['Customs']) ? $_REQUEST['Customs'] : array());
        $this->_plugin->settings->SetExcludedMonitoringIP(isset($_REQUEST['IpAddrs']) ? $_REQUEST['IpAddrs'] : array());

        $this->_plugin->settings->SetRestrictAdmins(isset($_REQUEST['RestrictAdmins']));
        $this->_plugin->settings->SetRefreshAlertsEnabled($_REQUEST['EnableAuditViewRefresh']);
        $this->_plugin->settings->SetMainIPFromProxy(isset($_REQUEST['EnableProxyIpCapture']));
        $this->_plugin->settings->SetInternalIPsFiltering(isset($_REQUEST['EnableIpFiltering']));
        $this->_plugin->settings->SetIncognito(isset($_REQUEST['Incognito']));
        $this->_plugin->settings->SetLoggingDisabled(isset($_REQUEST['Logging']));
        $this->_plugin->settings->SetDeleteData(isset($_REQUEST['DeleteData']));
        $this->_plugin->settings->SetTimezone($_REQUEST['Timezone']);
        $this->_plugin->settings->SetWPBackend(isset($_REQUEST['WPBackend']));
        if (!empty($_REQUEST['Columns'])) {
            $this->_plugin->settings->SetColumns($_REQUEST['Columns']);
        }
        $this->_plugin->settings->ClearDevOptions();

        if (isset($_REQUEST['DevOptions'])) {
            foreach ($_REQUEST['DevOptions'] as $opt) {
                $this->_plugin->settings->SetDevOptionEnabled($opt, true);
            }
        }
        $this->_plugin->settings->Set404LogLimit($_REQUEST['404Limit']);
    }
    
    public function AjaxCheckSecurityToken()
    {
        if (!$this->_plugin->settings->CurrentUserCan('view'))
            die('Access Denied.');
        if (!isset($_REQUEST['token']))
            die('Token parameter expected.');
        die($this->GetTokenType($_REQUEST['token']));
    }
    
    public function AjaxRunCleanup()
    {
        if (!$this->_plugin->settings->CurrentUserCan('view'))
            die('Access Denied.');
        $this->_plugin->CleanUp();
        wp_redirect($this->GetUrl());
        exit;
    }
    
    public function Render()
    {
        if (!$this->_plugin->settings->CurrentUserCan('edit')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-security-audit-log'));
        }
        if (isset($_POST['submit'])) {
            try {
                $this->Save();
                ?><div class="updated">
                    <p><?php _e('Settings have been saved.', 'wp-security-audit-log'); ?></p>
                </div><?php
            } catch (Exception $ex) {
                ?><div class="error"><p><?php _e('Error: ', 'wp-security-audit-log'); ?><?php echo $ex->getMessage(); ?></p></div><?php
            }
        }
        ?>
        <h2 id="wsal-tabs" class="nav-tab-wrapper">
            <a href="#tab-general" class="nav-tab"><?php _e('General', 'wp-security-audit-log'); ?></a>
            <a href="#tab-audit-log" class="nav-tab"><?php _e('Audit Log', 'wp-security-audit-log'); ?></a>
            <a href="#tab-exclude" class="nav-tab"><?php _e('Exclude Objects', 'wp-security-audit-log'); ?></a>
        </h2>
        <script src="//code.jquery.com/ui/1.10.3/jquery-ui.js"/></script>
        <form id="audit-log-settings" method="post">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <input type="hidden" id="ajaxurl" value="<?php echo esc_attr(admin_url('admin-ajax.php')); ?>" />
            <?php wp_nonce_field('wsal-settings'); ?>
            
            <div id="audit-log-adverts">
            </div>
            <div class="nav-tabs">
                <!-- First tab -->
                <table class="form-table wsal-tab widefat" id="tab-general">
                    <tbody>
                        <!-- From Email & Name -->
                        <tr>
                            <th><label for="FromEmail"><?php _e('From Email & Name', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <label for="FromEmail"><?php _e('Email Address', 'wp-security-audit-log'); ?></label>
                                    <input type="email" id="FromEmail" name="FromEmail" value="<?php echo esc_attr($this->_plugin->settings->GetFromEmail()); ?>" />
                                    &nbsp;
                                    <label for="DisplayName"><?php _e('Display Name', 'wp-security-audit-log'); ?></label>
                                    <input type="text" id="DisplayName" name="DisplayName" value="<?php echo esc_attr($this->_plugin->settings->GetDisplayName()); ?>" />
                                </fieldset>
                                <p class="description">
                                    <?php
                                        echo sprintf(
                                            __('These email address and display name will be used as From details in the emails sent by the %s . Please ensure the mail server can relay emails with the domain of the specified email address.', 'wp-security-audit-log'),
                                            '<a target="_blank" href="https://www.wpsecurityauditlog.com/plugin-extensions/">' . __('(premium add-ons)', 'wp-security-audit-log') . '</a>'
                                        );
                                    ?>
                                </p>
                            </td>
                        </tr>
                        <!-- Alerts Dashboard Widget -->
                        <tr>
                            <th><label for="dwoption_on"><?php _e('Alerts Dashboard Widget', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <?php $dwe = $this->_plugin->settings->IsWidgetsEnabled(); ?>
                                    <label for="dwoption_on">
                                        <input type="radio" name="EnableDashboardWidgets" id="dwoption_on" style="margin-top: 2px;" <?php if($dwe)echo 'checked="checked"'; ?> value="1">
                                        <span><?php _e('On', 'wp-security-audit-log'); ?></span>
                                    </label>
                                    <br/>
                                    <label for="dwoption_off">
                                        <input type="radio" name="EnableDashboardWidgets" id="dwoption_off" style="margin-top: 2px;" <?php if(!$dwe)echo 'checked="checked"'; ?> value="0">
                                        <span><?php _e('Off', 'wp-security-audit-log'); ?></span>
                                    </label>
                                    <br/>
                                    <p class="description"><?php
                                        echo sprintf(
                                                __('Display a dashboard widget with the latest %d security alerts.', 'wp-security-audit-log'),
                                                $this->_plugin->settings->GetDashboardWidgetMaxAlerts()
                                            );
                                    ?></p>
                                </fieldset>
                            </td>
                        </tr>
                        <!-- Reverse Proxy / Firewall Options -->
                        <tr>
                            <th><label for="pioption_on"><?php _e('Reverse Proxy / Firewall Options', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <label for="EnableProxyIpCapture">
                                        <input type="checkbox" name="EnableProxyIpCapture" value="1" id="EnableProxyIpCapture"<?php
                                            if($this->_plugin->settings->IsMainIPFromProxy())echo ' checked="checked"';
                                        ?>/> <?php _e('WordPress running behind firewall or proxy', 'wp-security-audit-log'); ?>
                                    </label>
                                    <br/>
                                    <span class="description"><?php _e('Enable this option if your WordPress is running behind a firewall or reverse proxy. When this option is enabled the plugin will retrieve the user\'s IP address from the proxy header.', 'wp-security-audit-log'); ?></span>
                                    <br/>
                                    <label for="EnableIpFiltering">
                                        <input type="checkbox" name="EnableIpFiltering" value="1" id="EnableIpFiltering"<?php
                                            if($this->_plugin->settings->IsInternalIPsFiltered())echo ' checked="checked"';
                                        ?>/> <?php _e('Filter Internal IP Addresses', 'wp-security-audit-log'); ?>
                                    </label>
                                    <br/>
                                    <span class="description"><?php _e('Enable this option to filter internal IP addresses from the proxy headers.', 'wp-security-audit-log'); ?></span>    
                                </fieldset>
                            </td>
                        </tr>
                        <!-- Can Manage Plugin -->
                        <tr>
                            <th><label for="EditorQueryBox"><?php _e('Can Manage Plugin', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <input type="text" id="EditorQueryBox" style="float: left; display: block; width: 250px;">
                                    <input type="button" id="EditorQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
                                    <br style="clear: both;"/>
                                    <p class="description"><?php
                                        _e('Users and Roles in this list can manage the plugin settings', 'wp-security-audit-log');
                                    ?></p>
                                    <div id="EditorList"><?php
                                        foreach ($this->_plugin->settings->GetAllowedPluginEditors() as $item) {
                                            ?><span class="sectoken-<?php echo $this->GetTokenType($item); ?>">
                                                <input type="hidden" name="Editors[]" value="<?php echo esc_attr($item); ?>"/>
                                                <?php echo esc_html($item); ?>
                                                <?php if (wp_get_current_user()->user_login != $item) { ?>
                                                    <a href="javascript:;" title="Remove">&times;</a>
                                                <?php } ?>
                                            </span><?php
                                        }
                                    ?></div>
                                </fieldset>
                            </td>
                        </tr>
                        <!-- Restrict Plugin Access -->
                        <tr>
                            <th><label for="RestrictAdmins"><?php _e('Restrict Plugin Access', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <input type="hidden" id="RestrictAdminsDefaultUser" value="<?php echo esc_attr(wp_get_current_user()->user_login); ?>"/>
                                    <label for="RestrictAdmins">
                                        <?php $ira = $this->_plugin->settings->IsRestrictAdmins(); ?>
                                        <input type="checkbox" name="RestrictAdmins" id="RestrictAdmins"<?php if($ira)echo ' checked="checked"'; ?>/>
                                    </label>
                                    <br/>
                                    <span class="description">
                                        <?php _e('If this option is disabled all the administrators on this WordPress have access to manage this plugin.', 'wp-security-audit-log'); ?><br/>
                                        <?php _e('By enabling this option only <strong>You</strong> and the users specified in the <strong>Can Manage Plugin</strong> and <strong>Can View Alerts</strong> can configure this plugin or view the alerts in the WordPress audit trail.', 'wp-security-audit-log'); ?>
                                    </span>
                                </fieldset>
                            </td>
                        </tr>
                        <!-- Developer Options -->
                        <tr>
                            <th><label><?php _e('Developer Options', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <?php $any = $this->_plugin->settings->IsAnyDevOptionEnabled(); ?>
                                    <a href="javascript:;" style="<?php if($any)echo 'display: none;'; ?>"
                                       onclick="jQuery(this).hide().next().show();">Show Developer Options</a>
                                    <div style="<?php if(!$any)echo 'display: none;'; ?>">
                                        <p style="border-left: 3px solid #FFD000; padding: 2px 8px; margin-left: 6px; margin-bottom: 16px;">
                                            <?php _e('Only enable these options on testing, staging and development websites. Enabling any of the settings below on LIVE websites may cause unintended side-effects including degraded performance.', 'wp-security-audit-log'); ?>
                                        </p>
                                        <?php
                                            foreach (array(
                                                WSAL_Settings::OPT_DEV_DATA_INSPECTOR => array(
                                                    __('Data Inspector', 'wp-security-audit-log'),
                                                    __('View data logged for each triggered alert.', 'wp-security-audit-log')
                                                ),
                                                /* WSAL_Settings::OPT_DEV_PHP_ERRORS     => array(
                                                    __('PHP Errors', 'wp-security-audit-log'),
                                                    __('Enables sensor for alerts generated from PHP.', 'wp-security-audit-log')
                                                ), */
                                                WSAL_Settings::OPT_DEV_REQUEST_LOG    => array(
                                                    __('Request Log', 'wp-security-audit-log'),
                                                    __('Enables logging request to file.', 'wp-security-audit-log')
                                                ),
                                                /* WSAL_Settings::OPT_DEV_BACKTRACE_LOG  => array(
                                                    __('Backtrace', 'wp-security-audit-log'),
                                                    __('Log full backtrace for PHP-generated alerts.', 'wp-security-audit-log')
                                                ), */
                                            ) as $opt => $info) {
                                                ?><label for="devoption_<?php echo $opt; ?>">
                                                    <input type="checkbox" name="DevOptions[]" id="devoption_<?php echo $opt; ?>" <?php
                                                        if($this->_plugin->settings->IsDevOptionEnabled($opt))echo 'checked="checked"'; ?> value="<?php echo $opt; ?>">
                                                    <span><?php echo $info[0]; ?></span>
                                                    <?php if (isset($info[1]) && $info[1]) { ?>
                                                        <span class="description"> &mdash; <?php echo $info[1]; ?></span>
                                                    <?php }
                                                ?></label><br/><?php
                                            }
                                        ?>
                                        <span class="description">
                                            <?php _e('The request log file is saved in the /wp-content/uploads/wp-security-audit-log/ directory.', 'wp-security-audit-log'); ?>
                                        </span>
                                    </div>
                                </fieldset>
                            </td>
                        </tr>
                        <!-- Hide Plugin in Plugins Page -->
                        <tr>
                            <th><label for="Incognito"><?php _e('Hide Plugin in Plugins Page', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <label for="Incognito">
                                        <input type="checkbox" name="Incognito" value="1" id="Incognito"<?php
                                            if ($this->_plugin->settings->IsIncognito()) echo ' checked="checked"';
                                        ?>/> <?php _e('Hide', 'wp-security-audit-log'); ?>
                                    </label>
                                    <br/>
                                    <span class="description">
                                        <?php _e('To manually revert this setting set the value of option wsal-hide-plugin to 0 in the wp_wsal_options table.', 'wp-security-audit-log'); ?>
                                    </span>
                                </fieldset>
                            </td>
                        </tr>
                        <!-- Logging -->
                        <tr>
                            <th><label for="Logging"><?php _e('Logging', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <label for="Logging">
                                        <span class="f-container">
                                            <span class="f-left">
                                                <input type="checkbox" name="Logging" value="1" class="switch" id="logging_status"/>
                                                <label for="logging_status"></label>
                                            </span>
                                            <span class="f-right f-text"><span id="logging_status_text"></span></span>
                                        </span>
                                    </label>
                                    <br/>
                                    <span class="description">
                                        <?php _e('Disable all plugin logging.', 'wp-security-audit-log'); ?>
                                    </span>
                                </fieldset>
                            </td>
                        </tr>
                        <!-- Remove Data on Uninstall -->
                        <tr>
                            <th><label for="DeleteData"><?php _e('Remove Data on Uninstall', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <label for="DeleteData">
                                        <input type="checkbox" name="DeleteData" value="1" id="DeleteData"  onclick="return delete_confirm(this);"<?php
                                            if ($this->_plugin->settings->IsDeleteData()) echo ' checked="checked"';
                                        ?>/> <span class="description">Check this box if you would like remove all data when the plugin is deleted.</span>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <!-- Second tab -->
                <table class="form-table wsal-tab widefat" id="tab-audit-log">
                    <tbody>
                        <!-- Security Alerts Pruning -->
                        <?php
                            $disabled = '';
                            if ($this->_plugin->settings->IsArchivingEnabled()) {
                            $disabled = 'disabled';
                            ?>
                            <tr>
                                <td colspan="2">
                                    <?php _e('The options below are disabled because you enabled archiving of alerts to the archiving table from', 'wp-security-audit-log'); ?>&nbsp;<a href="<?php echo admin_url('admin.php?page=wsal-ext-settings#mirroring'); ?>" target="_blank">here</a>
                                </td>
                            </tr>
                        <?php } ?>
                        <tr>
                            <th><label for="delete1"><?php _e('Security Alerts Pruning', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <?php $text = __('(eg: 1 month)', 'wp-security-audit-log'); ?>
                                    <?php $nbld = !($this->_plugin->settings->IsPruningDateEnabled() || $this->_plugin->settings->IsPruningLimitEnabled()); ?>
                                    <label for="delete0">
                                        <input type="radio" id="delete0" name="PruneBy" value="" <?php if ($nbld) echo 'checked="checked"'; ?> <?php echo $disabled; ?>/>
                                        <?php echo __('None', 'wp-security-audit-log'); ?>
                                    </label>
                                </fieldset>
                                <fieldset>
                                    <?php $text = __('(eg: 1 month)', 'wp-security-audit-log'); ?>
                                    <?php $nbld = $this->_plugin->settings->IsPruningDateEnabled(); ?>
                                    <label for="delete1">
                                        <input type="radio" id="delete1" name="PruneBy" value="date" <?php if($nbld)echo 'checked="checked"'; ?> <?php echo $disabled; ?>/>
                                        <?php echo __('Delete alerts older than', 'wp-security-audit-log'); ?>
                                    </label>
                                    <input type="text" id="PruningDate" name="PruningDate" placeholder="<?php echo $text; ?>"
                                           value="<?php echo esc_attr($this->_plugin->settings->GetPruningDate()); ?>"
                                           onfocus="jQuery('#delete1').attr('checked', true);" <?php echo $disabled; ?>/>
                                    <span> <?php echo $text; ?></span>
                                </fieldset>
                                <fieldset>
                                    <?php $text = __('(eg: 80)', 'wp-security-audit-log'); ?>
                                    <?php $nbld = $this->_plugin->settings->IsPruningLimitEnabled(); ?>
                                    <label for="delete2">
                                        <input type="radio" id="delete2" name="PruneBy" value="limit" <?php if($nbld)echo 'checked="checked"'; ?> <?php echo $disabled; ?>/>
                                        <?php echo __('Keep up to', 'wp-security-audit-log'); ?>
                                    </label>
                                    <input type="text" id="PruningLimit" name="PruningLimit" placeholder="<?php echo $text;?>"
                                           value="<?php echo esc_attr($this->_plugin->settings->GetPruningLimit()); ?>"
                                           onfocus="jQuery('#delete2').attr('checked', true);" <?php echo $disabled; ?>/>
                                    <?php echo __('alerts', 'wp-security-audit-log'); ?>
                                    <span><?php echo $text; ?></span>
                                </fieldset>
                                <p class="description"><?php
                                    echo __('Next Scheduled Cleanup is in ', 'wp-security-audit-log');
                                    echo human_time_diff(current_time('timestamp'), $next = wp_next_scheduled('wsal_cleanup'));
                                    echo '<!-- ' . date('dMy H:i:s', $next) . ' --> ';
                                    echo sprintf(
                                            __('(or %s)', 'wp-security-audit-log'),
                                            '<a class="' .$disabled.'" href="' . admin_url('admin-ajax.php?action=AjaxRunCleanup') . '">' . __('Run Manually', 'wp-security-audit-log') . '</a>'
                                        );
                                ?></p>
                            </td>
                        </tr>
                        <!-- Can View Alerts -->
                        <tr>
                            <th><label for="ViewerQueryBox"><?php _e('Can View Alerts', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <input type="text" id="ViewerQueryBox" style="float: left; display: block; width: 250px;">
                                    <input type="button" id="ViewerQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
                                    <br style="clear: both;"/>
                                    <p class="description"><?php
                                        _e('Users and Roles in this list can view the security alerts', 'wp-security-audit-log');
                                    ?></p>
                                    <div id="ViewerList"><?php
                                        foreach($this->_plugin->settings->GetAllowedPluginViewers() as $item){
                                            ?><span class="sectoken-<?php echo $this->GetTokenType($item); ?>">
                                                <input type="hidden" name="Viewers[]" value="<?php echo esc_attr($item); ?>"/>
                                                <?php echo esc_html($item); ?>
                                                <a href="javascript:;" title="Remove">&times;</a>
                                            </span><?php
                                        }
                                    ?></div>
                                </fieldset>
                            </td>
                        </tr>
                        <!-- Refresh Audit Log Viewer -->
                        <tr>
                            <th><label for="aroption_on"><?php _e('Refresh Audit Log Viewer', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <?php $are = $this->_plugin->settings->IsRefreshAlertsEnabled(); ?>
                                    <label for="aroption_on">
                                        <input type="radio" name="EnableAuditViewRefresh" id="aroption_on" style="margin-top: 2px;" <?php if($are)echo 'checked="checked"'; ?> value="1">
                                        <span><?php _e('Automatic', 'wp-security-audit-log'); ?></span>
                                    </label>
                                    <span class="description"> &mdash; <?php _e('Refresh Audit Log Viewer as soon as there are new alerts.', 'wp-security-audit-log'); ?></span>
                                    <br/>
                                    <label for="aroption_off">
                                        <input type="radio" name="EnableAuditViewRefresh" id="aroption_off" style="margin-top: 2px;" <?php if(!$are)echo 'checked="checked"'; ?> value="0">
                                        <span><?php _e('Manual', 'wp-security-audit-log'); ?></span>
                                    </label>
                                    <span class="description"> &mdash; <?php _e('Refresh Audit Log Viewer only when the page is reloaded.', 'wp-security-audit-log'); ?></span>
                                    <br/>
                                </fieldset>
                            </td>
                        </tr>
                        <!-- Alerts Timestamp -->
                        <tr>
                            <th><label for="timezone-default"><?php _e('Alerts Timestamp', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <?php $timezone = $this->_plugin->settings->GetTimezone(); ?>
                                    <label for="timezone-default">
                                        <input type="radio" name="Timezone" id="timezone-default" style="margin-top: 2px;" <?php if(!$timezone)echo 'checked="checked"'; ?> value="0">
                                        <span><?php _e('UTC', 'wp-security-audit-log'); ?></span>
                                    </label>
                                    <br/>
                                    <label for="timezone">
                                        <input type="radio" name="Timezone" id="timezone" style="margin-top: 2px;" <?php if($timezone)echo 'checked="checked"'; ?> value="1">
                                        <span><?php _e('WordPress\' timezone', 'wp-security-audit-log'); ?></span>
                                    </label>
                                    <br/>
                                    <span class="description"><?php _e('Select which timestamp the alerts should have in the Audit Log viewer. Note that the WordPress\' timezone might be different from that of the server.', 'wp-security-audit-log'); ?></span>
                                </fieldset>
                            </td>
                        </tr>
                        <!-- Audit Log Columns Selection -->
                        <tr>
                            <th><label for="columns"><?php _e('Audit Log Columns Selection', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <?php $columns = $this->_plugin->settings->GetColumns(); ?>
                                    <?php foreach ($columns as $key => $value) { ?>
                                        <label for="columns">
                                            <input type="checkbox" name="Columns[<?php echo $key; ?>]" id="<?php echo $key; ?>" class="sel-columns" style="margin-top: 2px;" <?php if ($value == '1') echo 'checked="checked"'; ?> value="1">
                                            <span><?php echo ucwords(str_replace("_", " ", $key)); ?></span>
                                        </label>
                                        <br/>
                                    <?php } ?>
                                    <span class="description"><?php _e('When you disable any of the above such details won’t be shown in the Audit Log
    viewer though the plugin will still record such information in the database.', 'wp-security-audit-log'); ?></span>
                                </fieldset>
                            </td>
                        </tr>
                        <!-- Disable Alerts for WordPress Background activity -->
                        <tr>
                            <th><label for="DeleteData"><?php _e('Disable Alerts for WordPress Background Activity', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <label for="WPBackend">
                                        <input type="checkbox" name="WPBackend" value="1" id="WPBackend" <?php
                                            if ($this->_plugin->settings->IsWPBackend()) echo ' checked="checked"';
                                        ?>/> <?php _e('Hide activity', 'wp-security-audit-log'); ?>
                                    </label>
                                    <br/>
                                    <span class="description">
                                        <?php _e('For example do not raise an alert when WordPress deletes the auto drafts.', 'wp-security-audit-log'); ?>
                                    </span>
                                </fieldset>
                            </td>
                        </tr>
                        <!-- Number of 404 Requests to Log -->
                        <tr>
                            <th><label for="404Limit"><?php _e('Number of 404 Requests to Log', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <input type="number" id="404Limit" name="404Limit" value="<?php echo $this->_plugin->settings->Get404LogLimit(); ?>" />
                                </fieldset>
                                <p class="description">
                                    <?php _e('By default the plugin keeps up to 99 requests to non-existing pages from the same IP address. Increase the value in this setting to the desired amount to keep a log of more or less requests.', 'wp-security-audit-log'); ?><br />
                                    <?php _e('Note that by increasing this value to a high number, should your website be scanned the plugin will consume more resources to log all the requests.', 'wp-security-audit-log'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <!-- Third tab -->
                <table class="form-table wsal-tab widefat" id="tab-exclude">
                    <tbody>
                        <tr>
                            <th><h2>Users &amp; Roles</h2></th>
                        </tr>
                        <tr>
                            <td colspan="2">Any of the users and roles listed in the below options will be excluded from monitoring. This means that any change they do will not be logged.</td>
                        </tr>
                        <!-- Excluded Users -->
                        <tr>
                            <th><label for="ExUserQueryBox"><?php _e('Excluded Users', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <input type="text" id="ExUserQueryBox" style="float: left; display: block; width: 250px;">
                                    <input type="button" id="ExUserQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
                                    <br style="clear: both;"/>
                                    <div id="ExUserList"><?php
                                        foreach($this->_plugin->settings->GetExcludedMonitoringUsers() as $item){
                                            ?><span class="sectoken-<?php echo $this->GetTokenType($item); ?>">
                                                <input type="hidden" name="ExUsers[]" value="<?php echo esc_attr($item); ?>"/>
                                                <?php echo esc_html($item); ?>
                                                <a href="javascript:;" title="Remove">&times;</a>
                                            </span><?php
                                        }
                                    ?></div>
                                </fieldset>
                            </td>
                        </tr>
                        <!-- Excluded Roles -->
                        <tr>
                            <th><label for="ExRoleQueryBox"><?php _e('Excluded Roles', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <input type="text" id="ExRoleQueryBox" style="float: left; display: block; width: 250px;">
                                    <input type="button" id="ExRoleQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
                                    <br style="clear: both;"/>
                                    <div id="ExRoleList"><?php
                                        foreach($this->_plugin->settings->GetExcludedMonitoringRoles() as $item){
                                            ?><span class="sectoken-<?php echo $this->GetTokenType($item); ?>">
                                                <input type="hidden" name="ExRoles[]" value="<?php echo esc_attr($item); ?>"/>
                                                <?php echo esc_html($item); ?>
                                                <a href="javascript:;" title="Remove">&times;</a>
                                            </span><?php
                                        }
                                    ?></div>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th><h2>Custom Fields</h2></th>
                        </tr>
                        <tr>
                            <td colspan="2">All of the custom fields listed below will be excluded from monitoring. This means that if they are changed or updated the plugin will not log such activity.<br>
                            You can use the * wildcard to exclude more than one Custom Field. For example to exclude all the Custom Fields that start with wp123 specify wp123*.</td>
                        </tr>
                        <!-- Excluded Custom Fields -->
                        <tr>
                            <th><label for="CustomQueryBox"><?php _e('Excluded Custom Fields', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <input type="text" id="CustomQueryBox" style="float: left; display: block; width: 250px;">
                                    <input type="button" id="CustomQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
                                    <br style="clear: both;"/>
                                    <div id="CustomList">
                                    <?php foreach ($this->_plugin->settings->GetExcludedMonitoringCustom() as $item) { ?>
                                        <span class="sectoken-<?php echo $this->GetTokenType($item); ?>">
                                            <input type="hidden" name="Customs[]" value="<?php echo esc_attr($item); ?>"/>
                                            <?php echo esc_html($item); ?>
                                            <a href="javascript:;" title="Remove">&times;</a>
                                        </span>
                                    <?php } ?>
                                    </div>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th><h2>IP Addresses</h2></th>
                        </tr>
                        <tr>
                            <td colspan="2">Any of the IP addresses listed below will be excluded from monitoring. This means that all activity from such IP address will not be recorded.</td>
                        </tr>
                        <!-- Excluded IP Addresses -->
                        <tr>
                            <th><label for="IpAddrQueryBox"><?php _e('Excluded IP Addresses', 'wp-security-audit-log'); ?></label></th>
                            <td>
                                <fieldset>
                                    <input type="text" id="IpAddrQueryBox" style="float: left; display: block; width: 250px;">
                                    <input type="button" id="IpAddrQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
                                    <br style="clear: both;"/>
                                    <div id="IpAddrList">
                                    <?php foreach ($this->_plugin->settings->GetExcludedMonitoringIP() as $item) { ?>
                                        <span class="sectoken-<?php echo $this->GetTokenType($item); ?>">
                                            <input type="hidden" name="IpAddrs[]" value="<?php echo esc_attr($item); ?>"/>
                                            <?php echo esc_html($item); ?>
                                            <a href="javascript:;" title="Remove">&times;</a>
                                        </span>
                                    <?php } ?>
                                    </div>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
        </form>
        <script type="text/javascript">
        <!--
            function delete_confirm(elementRef)
            {
                if (elementRef.checked)
                {
                    if ( window.confirm('Do you want remove all data when the plugin is deleted?') == false )
                    elementRef.checked = false;
                } 
            }

            jQuery(document).ready(function() {
                var statusConfig = <?php  if ($this->_plugin->settings->IsLoggingDisabled()) { echo 1; } else { echo 0; } ?>;
                var logging_status = jQuery('#logging_status');
                var txtNot = jQuery('#logging_status_text');

                function wsalUpdateLoggingStatus(checkbox, label){
                    if (checkbox.prop('checked')) {
                        label.text('On');
                    } else {
                        label.text('Off');
                    }
                }
                // Set On
                if (statusConfig) {
                    logging_status.prop('checked', true);
                }
                wsalUpdateLoggingStatus(logging_status, txtNot);

                logging_status.on('change', function() { 
                    wsalUpdateLoggingStatus(logging_status, txtNot); 
                });
            });
        // -->
        </script><?php
    }
    
    public function Header()
    {
        wp_enqueue_style(
            'settings',
            $this->_plugin->GetBaseUrl() . '/css/settings.css',
            array(),
            filemtime($this->_plugin->GetBaseDir() . '/css/settings.css')
        );
        ?><style type="text/css">
            .wsal-tab {
                display: none;
            }
            .wsal-tab tr.alert-incomplete td {
                color: #9BE;
            }
            .wsal-tab tr.alert-unavailable td {
                color: #CCC;
            }
        </style><?php
    }
    
    public function Footer()
    {
        wp_enqueue_script(
            'settings',
            $this->_plugin->GetBaseUrl() . '/js/settings.js',
            array(),
            filemtime($this->_plugin->GetBaseDir() . '/js/settings.js')
        );
        ?><script type="text/javascript">
            jQuery(document).ready(function(){
                // tab handling code
                jQuery('#wsal-tabs>a').click(function(){
                    jQuery('#wsal-tabs>a').removeClass('nav-tab-active');
                    jQuery('table.wsal-tab').hide();
                    jQuery(jQuery(this).addClass('nav-tab-active').attr('href')).show();
                });
                // show relevant tab
                var hashlink = jQuery('#wsal-tabs>a[href="' + location.hash + '"]');
                if (hashlink.length) {
                    hashlink.click();
                } else {
                    jQuery('#wsal-tabs>a:first').click();
                }

                jQuery(".sel-columns").change(function(){
                    var notChecked = 1;
                    jQuery(".sel-columns").each(function(){
                        if(this.checked) notChecked = 0;
                    })
                    if(notChecked == 1){
                        alert("You have to select at least one column!");       
                    }
                });
            });
        </script><?php
    }
    
    public function AjaxGetAllUsers()
    {
        if (!$this->_plugin->settings->CurrentUserCan('view')) {
            die('Access Denied.');
        }
        $users = array();
        foreach (get_users() as $user) {
            if (strpos($user->user_login, $_GET['term']) !== false) {
                array_push($users, $user->user_login);
            }
        }
        echo json_encode($users);
        exit;
    }

    public function AjaxGetAllRoles()
    {
        if (!$this->_plugin->settings->CurrentUserCan('view')) {
            die('Access Denied.');
        }
        $roles = array();
        foreach (get_editable_roles() as $role_name => $role_info) {
            if (strpos($role_name, $_GET['term']) !== false) {
                array_push($roles, $role_name);
            }
        }
        echo json_encode($roles);
        exit;
    }
}
