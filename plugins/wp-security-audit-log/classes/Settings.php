<?php
class WSAL_Settings {
    /**
     * @var WpSecurityAuditLog
     */
    protected $_plugin;
    public function __construct(WpSecurityAuditLog $plugin) {
        $this->_plugin = $plugin;
    }
    
    // <editor-fold desc="Developer Options">
    const OPT_DEV_DATA_INSPECTOR = 'd';
    const OPT_DEV_PHP_ERRORS     = 'p';
    const OPT_DEV_REQUEST_LOG    = 'r';
    const OPT_DEV_BACKTRACE_LOG  = 'b';

    const ERROR_CODE_INVALID_IP = 901;

    protected $_devoption = null;
    /**
     * @return array Array of developer options to be enabled by default.
     */
    public function GetDefaultDevOptions(){
        return array();
    }
    /**
     * Returns whether a developer option is enabled or not.
     * @param string $option See self::OPT_DEV_* constants.
     * @return boolean If option is enabled or not.
     */
    public function IsDevOptionEnabled($option){
        if (is_null($this->_devoption)) {
            $this->_devoption = $this->_plugin->GetGlobalOption(
                'dev-options',
                implode(',', $this->GetDefaultDevOptions())
            );
            $this->_devoption = explode(',', $this->_devoption);
        }
        return in_array($option, $this->_devoption);
    }
    /**
     * @return boolean Whether any developer option has been enabled or not.
     */
    public function IsAnyDevOptionEnabled(){
        return !!$this->_plugin->GetGlobalOption('dev-options', null);
    }
    /**
     * Sets whether a developer option is enabled or not.
     * @param string $option See self::OPT_DEV_* constants.
     * @param boolean $enabled If option should be enabled or not.
     */
    public function SetDevOptionEnabled($option, $enabled){
        // make sure options have been loaded
        $this->IsDevOptionEnabled('');
        // remove option if it exists
        while (($p = array_search($option, $this->_devoption)) !== false) {
            unset($this->_devoption[$p]);
        }
        // add option if callee wants it enabled
        if ($enabled) {
            $this->_devoption[] = $option;
        }
        // commit option
        $this->_plugin->SetGlobalOption(
            'dev-options',
            implode(',', $this->_devoption)
        );
    }
    /**
     * Remove all enabled developer options.
     */
    public function ClearDevOptions(){
        $this->_devoption = array();
        $this->_plugin->SetGlobalOption('dev-options', '');
    }
    /**
     * @return boolean Whether to enable data inspector or not.
     */
    public function IsDataInspectorEnabled(){
        return $this->IsDevOptionEnabled(self::OPT_DEV_DATA_INSPECTOR);
    }
    /**
     * @return boolean Whether to PHP error logging or not.
     */
    public function IsPhpErrorLoggingEnabled(){
        return $this->IsDevOptionEnabled(self::OPT_DEV_PHP_ERRORS);
    }
    /**
     * @return boolean Whether to log requests to file or not.
     */
    public function IsRequestLoggingEnabled(){
        return $this->IsDevOptionEnabled(self::OPT_DEV_REQUEST_LOG);
    }
    /**
     * @return boolean Whether to store debug backtrace for PHP alerts or not.
     */
    public function IsBacktraceLoggingEnabled(){
        return $this->IsDevOptionEnabled(self::OPT_DEV_BACKTRACE_LOG);
    }
    
    // </editor-fold>
    /**
     * @return boolean Whether dashboard widgets are enabled or not.
     */
    public function IsWidgetsEnabled(){
        return !$this->_plugin->GetGlobalOption('disable-widgets');
    }
    /**
     * @param boolean $newvalue Whether dashboard widgets are enabled or not.
     */
    public function SetWidgetsEnabled($newvalue){
        $this->_plugin->SetGlobalOption('disable-widgets', !$newvalue);
    }
    /**
     * @return boolean Whether alerts in audit log view refresh automatically or not.
     */
    public function IsRefreshAlertsEnabled(){
        return !$this->_plugin->GetGlobalOption('disable-refresh');
    }
    /**
     * @param boolean $newvalue Whether alerts in audit log view refresh automatically or not.
     */
    public function SetRefreshAlertsEnabled($newvalue){
        $this->_plugin->SetGlobalOption('disable-refresh', !$newvalue);
    }
    /**
     * @return int Maximum number of alerts to show in dashboard widget.
     */
    public function GetDashboardWidgetMaxAlerts(){
        return 5;
    }
    
    // <editor-fold desc="Pruning Settings">
    /**
     * @return int The maximum number of alerts allowable.
     */
    public function GetMaxAllowedAlerts(){
        return 5000;
    }
    /**
     * @return string The default pruning date.
     */
    public function GetDefaultPruningDate(){
        return '1 month';
    }
    protected $_pruning = 0;
    /**
     * @return string The current pruning date.
     */
    public function GetPruningDate(){
        if (!$this->_pruning) {
            $this->_pruning = $this->_plugin->GetGlobalOption('pruning-date');
            if (!strtotime($this->_pruning)) {
                $this->_pruning = $this->GetDefaultPruningDate();
            }
        }
        return $this->_pruning;
    }
    /**
     * @param string $newvalue The new pruning date.
     */
    public function SetPruningDate($newvalue){
        if (strtotime($newvalue)) {
            $this->_plugin->SetGlobalOption('pruning-date', $newvalue);
            $this->_pruning = $newvalue;
        }
    }
    /**
     * @return integer Maximum number of alerts to keep.
     */
    public function GetPruningLimit(){
        $val = (int)$this->_plugin->GetGlobalOption('pruning-limit');
        return $val ? $val : $this->GetMaxAllowedAlerts();
    }
    /**
     * @param integer $newvalue The new maximum number of alerts.
     */
    public function SetPruningLimit($newvalue){
        $newvalue = max(/*min(*/(int)$newvalue/*, $this->GetMaxAllowedAlerts())*/, 1);
        $this->_plugin->SetGlobalOption('pruning-limit', $newvalue);
    }
    public function SetPruningDateEnabled($enabled){
        $this->_plugin->SetGlobalOption('pruning-date-e', $enabled);
    }
    public function SetPruningLimitEnabled($enabled){
        $this->_plugin->SetGlobalOption('pruning-limit-e', $enabled);
    }
    public function IsPruningDateEnabled(){
        return $this->_plugin->GetGlobalOption('pruning-date-e');
    }
    public function IsPruningLimitEnabled(){
        return $this->_plugin->GetGlobalOption('pruning-limit-e');
    }

    public function IsRestrictAdmins(){
        return $this->_plugin->GetGlobalOption('restrict-admins', false);
    }
    /**
     * @deprecated Sandbox functionality is now in an external plugin.
     */
    public function IsSandboxPageEnabled(){
        $plugins = $this->_plugin->licensing->plugins();
        return isset($plugins['wsal-sandbox-extensionphp']);
    }
    public function SetRestrictAdmins($enable){
        $this->_plugin->SetGlobalOption('restrict-admins', (bool)$enable);
    }
    
    // </editor-fold>
    protected $_disabled = null;
    public function GetDefaultDisabledAlerts(){
        return array(0000, 0001, 0002, 0003, 0004, 0005);
    }
    /**
     * @return array IDs of disabled alerts.
     */
    public function GetDisabledAlerts(){
        if (!$this->_disabled) {
            $this->_disabled = implode(',', $this->GetDefaultDisabledAlerts());
            $this->_disabled = $this->_plugin->GetGlobalOption('disabled-alerts', $this->_disabled);
            $this->_disabled = ($this->_disabled == '') ? array() : explode(',', $this->_disabled);
            $this->_disabled = array_map('intval', $this->_disabled);
        }
        return $this->_disabled;
    }
    /**
     * @param array $types IDs alerts to disable.
     */
    public function SetDisabledAlerts($types){
        $this->_disabled = array_unique(array_map('intval', $types));
        $this->_plugin->SetGlobalOption('disabled-alerts', implode(',', $this->_disabled));
    }
    public function IsIncognito(){
        return $this->_plugin->GetGlobalOption('hide-plugin');
    }
    public function SetIncognito($enabled){
        return $this->_plugin->SetGlobalOption('hide-plugin', $enabled);
    }

    /**
     * Checking if Logging is enabled.
     */
    public function IsLoggingDisabled(){
        return $this->_plugin->GetGlobalOption('disable-logging');
    }

    public function SetLoggingDisabled($disabled){
        return $this->_plugin->SetGlobalOption('disable-logging', $disabled);
    }
    
    /**
     * Checking if the data will be removed.
     */
    public function IsDeleteData(){
        return $this->_plugin->GetGlobalOption('delete-data');
    }

    public function SetDeleteData($enabled){
        return $this->_plugin->SetGlobalOption('delete-data', $enabled);
    }

    // <editor-fold desc="Access Control">
    
    protected $_viewers = null;
    public function SetAllowedPluginViewers($usersOrRoles){
        $this->_viewers = $usersOrRoles;
        $this->_plugin->SetGlobalOption('plugin-viewers', implode(',', $this->_viewers));
    }
    public function GetAllowedPluginViewers(){
        if (is_null($this->_viewers)) {
            $this->_viewers = array_unique(array_filter(explode(',', $this->_plugin->GetGlobalOption('plugin-viewers'))));
        }
        return $this->_viewers;
    }
    protected $_editors = null;
    public function SetAllowedPluginEditors($usersOrRoles){
        $this->_editors = $usersOrRoles;
        $this->_plugin->SetGlobalOption('plugin-editors', implode(',', $this->_editors));
    }
    public function GetAllowedPluginEditors(){
        if (is_null($this->_editors)) {
            $this->_editors = array_unique(array_filter(explode(',', $this->_plugin->GetGlobalOption('plugin-editors'))));
        }
        return $this->_editors;
    }
    protected $_perpage = null;
    public function SetViewPerPage($newvalue){
        $this->_perpage = max($newvalue, 1);
        $this->_plugin->SetGlobalOption('items-per-page', $this->_perpage);
    }
    public function GetViewPerPage(){
        if (is_null($this->_perpage)) {
            $this->_perpage = (int)$this->_plugin->GetGlobalOption('items-per-page', 10);
        }
        return $this->_perpage;
    }
    /**
     * @param string $action Type of action, either 'view' or 'edit'.
     * @return boolean If user has access or not.
     */
    public function CurrentUserCan($action){
        return $this->UserCan(wp_get_current_user(), $action);
    }
    /**
     * @return string[] List of superadmin usernames.
     */
    protected function GetSuperAdmins(){
        return $this->_plugin->IsMultisite() ? get_super_admins() : array();
    }
    /**
     * @return string[] List of admin usernames.
     */
    protected function GetAdmins(){
        if ($this->_plugin->IsMultisite()) {
            // see: https://gist.github.com/1508426/65785a15b8638d43a9905effb59e4d97319ef8f8
            global $wpdb;
            $cap = $wpdb->prefix."capabilities";
            $sql = "SELECT DISTINCT $wpdb->users.user_login"
                . " FROM $wpdb->users"
                . " INNER JOIN $wpdb->usermeta ON ($wpdb->users.ID = $wpdb->usermeta.user_id )"
                . " WHERE $wpdb->usermeta.meta_key = '$cap'"
                . " AND CAST($wpdb->usermeta.meta_value AS CHAR) LIKE  '%\"administrator\"%'";
            return $wpdb->get_col($sql);
        } else {
            $result = array();
            $query = 'role=administrator&fields[]=user_login';
            foreach (get_users($query) as $user) $result[] = $user->user_login;
            return $result;
        }
    }
    /**
     * Returns access tokens for a particular action.
     * @param string $action Type of action.
     * @return string[] List of tokens (usernames, roles etc).
     */
    public function GetAccessTokens($action) {
        $allowed = array();
        switch ($action) {
            case 'view':
                $allowed = $this->GetAllowedPluginViewers();
                $allowed = array_merge($allowed, $this->GetAllowedPluginEditors());
                if (!$this->IsRestrictAdmins()) {
                    $allowed = array_merge($allowed, $this->GetSuperAdmins());
                    $allowed = array_merge($allowed, $this->GetAdmins());
                }
                break;
            case 'edit':
                $allowed = $this->GetAllowedPluginEditors();
                if (!$this->IsRestrictAdmins()) {
                    $allowed = array_merge($allowed, $this->_plugin->IsMultisite() ? $this->GetSuperAdmins() : $this->GetAdmins());
                }
                break;
            default:
                throw new Exception('Unknown action "'.$action.'".');
        }
        if (!$this->IsRestrictAdmins()) {
            if (is_multisite()) {
                $allowed = array_merge($allowed, get_super_admins());
            } else {
                $allowed[] = 'administrator';
            }
        }
        return array_unique($allowed);
    }
    /**
     * @param integer|WP_user $user User object to check.
     * @param string $action Type of action, either 'view' or 'edit'.
     * @return boolean If user has access or not.
     */
    public function UserCan($user, $action) {
        if (is_int($user)) {
            $user = get_userdata($user);
        }
        $allowed = $this->GetAccessTokens($action);
        $check = array_merge($user->roles, array($user->user_login));
        foreach ($check as $item) {
            if (in_array($item, $allowed)) {
                return true;
            }
        }
        return false;
    }
    public function GetCurrentUserRoles($baseRoles = null){ 
        if ($baseRoles == null) $baseRoles = wp_get_current_user()->roles;
        if (function_exists('is_super_admin') && is_super_admin()) $baseRoles[] = 'superadmin';
        return $baseRoles;
    }

    public function IsLoginSuperAdmin($username){ 
        $userId = username_exists($username);
        if (function_exists('is_super_admin') && is_super_admin($userId) ) return true;
        else return false;
    }
    
    // </editor-fold>
    
    // <editor-fold desc="Licensing">
    public function GetLicenses(){
        return $this->_plugin->GetGlobalOption('licenses');
    }
    public function GetLicense($name){
        $data = $this->GetLicenses();
        $name = sanitize_key(basename($name));
        return isset($data[$name]) ? $data[$name] : array();
    }
    public function SetLicenses($data){ 
        $this->_plugin->SetGlobalOption('licenses', $data);
    }
    public function GetLicenseKey($name){
        $data = $this->GetLicense($name);
        return isset($data['key']) ? $data['key'] : '';
    }
    public function GetLicenseStatus($name){
        $data = $this->GetLicense($name);
        return isset($data['sts']) ? $data['sts'] : '';
    }
    public function GetLicenseErrors($name){
        $data = $this->GetLicense($name);
        return isset($data['err']) ? $data['err'] : '';
    }
    public function SetLicenseKey($name, $key){
        $data = $this->GetLicenses();
        if (!isset($data[$name])) $data[$name] = array();
        $data[$name]['key'] = $key;
        $this->SetLicenses($data);
    }
    public function SetLicenseStatus($name, $status){
        $data = $this->GetLicenses();
        if (!isset($data[$name])) $data[$name] = array();
        $data[$name]['sts'] = $status;
        $this->SetLicenses($data);
    }
    public function SetLicenseErrors($name, $errors){
        $data = $this->GetLicenses();
        if (!isset($data[$name])) $data[$name] = array();
        $data[$name]['err'] = $errors;
        $this->SetLicenses($data);
    }
    public function ClearLicenses(){
        $this->SetLicenses(array());
    }
    
    // </editor-fold>
    // <editor-fold desc="Client IP Retrieval">
    
    public function IsMainIPFromProxy(){
        return $this->_plugin->GetGlobalOption('use-proxy-ip');
    }
    public function SetMainIPFromProxy($enabled){
        return $this->_plugin->SetGlobalOption('use-proxy-ip', $enabled);
    }
    
    public function IsInternalIPsFiltered(){
        return $this->_plugin->GetGlobalOption('filter-internal-ip');
    }
    public function SetInternalIPsFiltering($enabled){
        return $this->_plugin->SetGlobalOption('filter-internal-ip', $enabled);
    }
    
    public function GetMainClientIP(){
        $result = null;
        if ($this->IsMainIPFromProxy()) {
            // TODO the algorithm below just gets the first IP in the list...we might want to make this more intelligent somehow
            $result = $this->GetClientIPs();
            $result = reset($result);
            $result = isset($result[0]) ? $result[0] : null;
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $result = $this->NormalizeIP($_SERVER['REMOTE_ADDR']);
            if (!$this->ValidateIP($result)) {
                $result = "Error " . self::ERROR_CODE_INVALID_IP . ": Invalid IP Address";
            }
        }
        return $result;
    }
    
    public function GetClientIPs(){
        $ips = array();
        foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
            if (isset($_SERVER[$key])) {
                $ips[$key] = array();
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    if ($this->ValidateIP($ip = $this->NormalizeIP($ip))) {
                        $ips[$key][] = $ip;
                    }
                }
            }
        }
        return $ips;
    }
    
    protected function NormalizeIP($ip){
        $ip = trim($ip);
        if (strpos($ip, ':') !== false && substr_count($ip, '.') == 3 && strpos($ip, '[') === false) {
            // IPv4 with a port (eg: 11.22.33.44:80)
            $ip = explode(':', $ip);
            $ip = $ip[0];
        } else {
            // IPv6 with a port (eg: [::1]:80)
            $ip = explode(']', $ip);
            $ip = ltrim($ip[0], '[');
        }
        return $ip;
    }
    
    protected function ValidateIP($ip){ 
        $opts = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
        if ($this->IsInternalIPsFiltered()) $opts = $opts | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        $filteredIP = filter_var($ip, FILTER_VALIDATE_IP, $opts);
        if (!$filteredIP || empty($filteredIP)) {
            //Regex IPV4
            if (preg_match("/^(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]).){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/", $ip)) return $ip;
            //Regex IPV6
            elseif (preg_match("/^\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?\s*$/", $ip)) return $ip;
            if (!$this->IsLoggingDisabled) {
                error_log("Invalid IP in ValidateIP function: ".$ip);
            }
            return false;
        } else {
            return $filteredIP;
        }
    }

    /**
     * Users excluded from monitoring
     */
    protected $_excluded_users = array();
    public function SetExcludedMonitoringUsers($users)
    {
        $this->_excluded_users = $users;
        $this->_plugin->SetGlobalOption('excluded-users', esc_html(implode(',', $this->_excluded_users)));
    }
    public function GetExcludedMonitoringUsers()
    {
        if (empty($this->_excluded_users)) {
            $this->_excluded_users = array_unique(array_filter(explode(',', $this->_plugin->GetGlobalOption('excluded-users'))));
        }
        return $this->_excluded_users;
    }

    /**
     * Roles excluded from monitoring
     */
    protected $_excluded_roles =  array();
    public function SetExcludedMonitoringRoles($roles){
        $this->_excluded_roles = $roles;
        $this->_plugin->SetGlobalOption('excluded-roles', esc_html(implode(',', $this->_excluded_roles)));
    }
    public function GetExcludedMonitoringRoles(){
        if (empty($this->_excluded_roles)) {
            $this->_excluded_roles = array_unique(array_filter(explode(',', $this->_plugin->GetGlobalOption('excluded-roles'))));
        }
        return $this->_excluded_roles;
    }

    /**
     * Custom fields excluded from monitoring
     */
    protected $_excluded_custom = array();
    public function SetExcludedMonitoringCustom($custom){
        $this->_excluded_custom = $custom;
        $this->_plugin->SetGlobalOption('excluded-custom', esc_html(implode(',', $this->_excluded_custom)));
    }
    public function GetExcludedMonitoringCustom(){
        if (empty($this->_excluded_custom)) {
            $this->_excluded_custom = array_unique(array_filter(explode(',', $this->_plugin->GetGlobalOption('excluded-custom'))));
            asort($this->_excluded_custom);
        }
        return $this->_excluded_custom;
    }

    /**
     * IP excluded from monitoring
     */
    protected $_excluded_ip = array();
    public function SetExcludedMonitoringIP($ip){
        $this->_excluded_ip = $ip;
        $this->_plugin->SetGlobalOption('excluded-ip', esc_html(implode(',', $this->_excluded_ip)));
    }
    public function GetExcludedMonitoringIP(){
        if (empty($this->_excluded_ip)) {
            $this->_excluded_ip = array_unique(array_filter(explode(',', $this->_plugin->GetGlobalOption('excluded-ip'))));
        }
        return $this->_excluded_ip;
    }

    /**
     * Datetime used in the Alerts.
     */
    public function GetDatetimeFormat($lineBreak = true) {
        if ($lineBreak) {
            $date_time_format = $this->GetDateFormat() . '<\b\r>' . $this->GetTimeFormat();
        } else {
            $date_time_format = $this->GetDateFormat() . ' ' . $this->GetTimeFormat();
        }

        $wp_time_format = get_option('time_format');
        if (stripos($wp_time_format, 'A') !== false) {
            $date_time_format .= '.$$$&\n\b\s\p;A';
        } else {
            $date_time_format .= '.$$$';
        }
        return $date_time_format;
    }

    /**
     * Date Format from WordPress General Settings.
     */
    public function GetDateFormat() {
        $wp_date_format = get_option('date_format');
        $search = array('F', 'M', 'n', 'j', ' ', '/', 'y', 'S', ',', 'l', 'D');
        $replace = array('m', 'm', 'm', 'd', '-', '-', 'Y', '', '', '', '');
        $date_format = str_replace($search, $replace, $wp_date_format);
        return $date_format;
    }

    /**
     * Time Format from WordPress General Settings.
     */
    public function GetTimeFormat() {
        $wp_time_format = get_option('time_format');
        $search = array('a', 'A', 'T', ' ');
        $replace = array('', '', '', '');
        $time_format = str_replace($search, $replace, $wp_time_format);
        return $time_format;
    }

    /**
     * Alerts Timestamp
     * Server's timezone or WordPress' timezone
     */
    public function GetTimezone(){
        return $this->_plugin->GetGlobalOption('timezone', 0);
    }

    public function SetTimezone($newvalue){
        return $this->_plugin->SetGlobalOption('timezone', $newvalue);
    }

    public function GetAdapterConfig($name_field){
        return $this->_plugin->GetGlobalOption($name_field);
    }

    public function SetAdapterConfig($name_field, $newvalue){
        return $this->_plugin->SetGlobalOption($name_field, trim($newvalue));
    }

    public function GetColumns(){
        $columns = array('alert_code' => '1', 'type' => '1', 'date' => '1', 'username' => '1', 'source_ip' => '1', 'message' => '1');
        if ($this->_plugin->IsMultisite()) {
            $columns = array_slice($columns, 0, 5, true) + array('site' => '1') + array_slice($columns, 5, null, true);
        }
        $selected = $this->GetColumnsSelected();
        if (!empty($selected)) {
            $columns = array('alert_code' => '0', 'type' => '0', 'date' => '0', 'username' => '0', 'source_ip' => '0', 'message' => '0');
            if ($this->_plugin->IsMultisite()) {
                $columns = array_slice($columns, 0, 5, true) + array('site' => '0') + array_slice($columns, 5, null, true);
            }
            $selected = (array)json_decode($selected);
            $columns = array_merge($columns, $selected);
            return $columns;
        } else {
            return $columns;
        }
    }

    public function GetColumnsSelected(){
        return $this->_plugin->GetGlobalOption('columns');
    }

    public function SetColumns($columns){
        return $this->_plugin->SetGlobalOption('columns', json_encode($columns));
    }
    
    public function IsWPBackend(){
        return $this->_plugin->GetGlobalOption('wp-backend');
    }

    public function SetWPBackend($enabled){
        return $this->_plugin->SetGlobalOption('wp-backend', $enabled);
    }

    public function SetFromEmail($email_address){
        return $this->_plugin->SetGlobalOption('from-email', trim($email_address));
    }

    public function GetFromEmail(){
        return $this->_plugin->GetGlobalOption('from-email');
    }

    public function SetDisplayName($display_name){
        return $this->_plugin->SetGlobalOption('display-name', trim($display_name));
    }

    public function GetDisplayName(){
        return $this->_plugin->GetGlobalOption('display-name');
    }

    public function Set404LogLimit($value){
        return $this->_plugin->SetGlobalOption('log-404-limit', abs($value));
    }

    public function Get404LogLimit(){
        return $this->_plugin->GetGlobalOption('log-404-limit', 99);
    }

/*============================== Support Archive Database ==============================*/

    public function IsArchivingEnabled(){
        return $this->_plugin->GetGlobalOption('archiving-e');
    }

    /**
     * Switch to Archive DB if is enabled
     */
    public function SwitchToArchiveDB()
    {
        if ($this->IsArchivingEnabled()) {
            $archiveType = $this->_plugin->GetGlobalOption('archive-type');
            $archiveUser = $this->_plugin->GetGlobalOption('archive-user');
            $password = $this->_plugin->GetGlobalOption('archive-password');
            $archiveName = $this->_plugin->GetGlobalOption('archive-name');
            $archiveHostname = $this->_plugin->GetGlobalOption('archive-hostname');
            $archiveBasePrefix = $this->_plugin->GetGlobalOption('archive-base-prefix');
            $config = WSAL_Connector_ConnectorFactory::GetConfigArray($archiveType, $archiveUser, $password, $archiveName, $archiveHostname, $archiveBasePrefix);
            $this->_plugin->getConnector($config)->getAdapter('Occurrence');
        }
    }
    // </editor-fold>
}
