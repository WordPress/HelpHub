<?php
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

class WSAL_Views_AuditLog extends WSAL_AbstractView {
    /**
     * @var WSAL_AuditLogListView
     */
    protected $_listview;

    protected $_version;
    
    public function __construct(WpSecurityAuditLog $plugin) {
        parent::__construct($plugin);
        add_action('wp_ajax_AjaxInspector', array($this, 'AjaxInspector'));
        add_action('wp_ajax_AjaxRefresh', array($this, 'AjaxRefresh'));
        add_action('wp_ajax_AjaxSetIpp', array($this, 'AjaxSetIpp'));
        add_action('wp_ajax_AjaxSearchSite', array($this, 'AjaxSearchSite'));
        add_action('wp_ajax_AjaxSwitchDB', array($this, 'AjaxSwitchDB'));
        add_action('all_admin_notices', array($this, 'AdminNoticesPremium'));
        // Check plugin version for to dismiss the notice only until upgrade
        $plugin_file =  trailingslashit(WP_PLUGIN_DIR) . plugin_basename(__FILE__);
        $data = get_plugin_data($plugin_file, false, false);
        $this->_version = isset($data['Version']) ? $data['Version'] : '0.0.0';
        $this->RegisterNotice('premium-wsal-'.$this->_version);
    }

    public function AdminNoticesPremium()
    {
        $IsCurrentView = $this->_plugin->views->GetActiveView() == $this;
        // Check if any of the extensions is activated
        if (!class_exists('WSAL_NP_Plugin') && !class_exists('WSAL_SearchExtension') && !class_exists('WSAL_Rep_Plugin') && !class_exists('WSAL_Ext_Plugin') && !class_exists('WSAL_User_Management_Plugin')) {
            if ($IsCurrentView && !$this->IsNoticeDismissed('premium-wsal-'.$this->_version)) { ?>
                <div class="updated" data-notice-name="premium-wsal-<?=$this->_version?>">
                    <?php $url = 'https://www.wpsecurityauditlog.com/extensions/all-add-ons-60-off/ ?utm_source=auditviewer&utm_medium=page&utm_campaign=plugin'; ?>
                    <p><a href="<?php echo esc_attr($url); ?>" target="_blank"><?php _e('Upgrade to Premium', 'wp-security-audit-log'); ?></a>
                        <?php _e('and add Email Alerts, Reports, Search and Users Login and Session Management.', 'wp-security-audit-log'); ?>
                        <a href="<?php echo esc_attr($url); ?>" target="_blank"><?php _e('Upgrade Now!', 'wp-security-audit-log'); ?></a>
                        <a href="javascript:;" class="wsal-dismiss-notification wsal-premium"><span class="dashicons dashicons-dismiss"></span></a>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    public function HasPluginShortcutLink() {
        return true;
    }
    
    public function GetTitle() {
        return __('Audit Log Viewer', 'wp-security-audit-log');
    }
    
    public function GetIcon() {
        return $this->_wpversion < 3.8
            ? $this->_plugin->GetBaseUrl() . '/img/logo-main-menu.png'
            : 'dashicons-welcome-view-site';
    }
    
    public function GetName() {
        return __('Audit Log Viewer', 'wp-security-audit-log');
    }
    
    public function GetWeight() {
        return 1;
    }
    
    protected function GetListView() {
        if (is_null($this->_listview)) {
            $this->_listview = new WSAL_AuditLogListView($this->_plugin);
        }
        return $this->_listview;
    }
    
    public function Render() {
        if (!$this->_plugin->settings->CurrentUserCan('view')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-security-audit-log'));
        }
        
        $this->GetListView()->prepare_items();
        $occ = new WSAL_Models_Occurrence();
        
        ?><form id="audit-log-viewer" method="post">
            <div id="audit-log-viewer-content">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
                <input type="hidden" id="wsal-cbid" name="wsal-cbid" value="<?php echo esc_attr(isset($_REQUEST['wsal-cbid']) ? $_REQUEST['wsal-cbid'] : '0'); ?>" />
                <?php do_action('wsal_auditlog_before_view', $this->GetListView()); ?>
                <?php $this->GetListView()->display(); ?>
                <?php do_action('wsal_auditlog_after_view', $this->GetListView()); ?>
            </div>
        </form><?php
        
        ?><script type="text/javascript">
            jQuery(document).ready(function(){
                WsalAuditLogInit(<?php echo json_encode(array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'tr8n' => array(
                        'numofitems' => __('Please enter the number of alerts you would like to see on one page:', 'wp-security-audit-log'),
                        'searchback' => __('All Sites', 'wp-security-audit-log'),
                        'searchnone' =>  __('No Results', 'wp-security-audit-log'),
                    ),
                    'autorefresh' => array(
                        'enabled' => $this->_plugin->settings->IsRefreshAlertsEnabled(),
                        'token' => (int)$occ->Count(),
                    ),
                )); ?>);
            });
        </script><?php
    }
    
    public function AjaxInspector() {
        if (!$this->_plugin->settings->CurrentUserCan('view')) {
            die('Access Denied.');
        }
        if (!isset($_REQUEST['occurrence'])) {
            die('Occurrence parameter expected.');
        }
        $occ = new WSAL_Models_Occurrence();
        $occ->Load('id = %d', array((int)$_REQUEST['occurrence']));

        echo '<!DOCTYPE html><html><head>';
        echo '<link rel="stylesheet" id="open-sans-css" href="' . $this->_plugin->GetBaseUrl() . '/css/nice_r.css" type="text/css" media="all">';
        echo '<script type="text/javascript" src="'.$this->_plugin->GetBaseUrl() . '/js/nice_r.js"></script>';
        echo '<style type="text/css">';
        echo 'html, body { margin: 0; padding: 0; }';
        echo '.nice_r { position: absolute; padding: 8px; }';
        echo '.nice_r a { overflow: visible; }';
        echo '</style>';
        echo '</head><body>';
        $nicer = new WSAL_Nicer($occ->GetMetaArray());
        $nicer->render();
        echo '</body></html>';
        die;
    }
    
    public function AjaxRefresh() {
        if (!$this->_plugin->settings->CurrentUserCan('view')) {
            die('Access Denied.');
        }
        if (!isset($_REQUEST['logcount'])) {
            die('Log count parameter expected.');
        }
        
        $old = (int)$_REQUEST['logcount'];
        $max = 40; // 40*500msec = 20sec

        $is_archive = false;
        $wp_session = WP_Session::get_instance();
        if (isset($wp_session['selected_db']) && $wp_session['selected_db'] == 'archive') {
            $is_archive = true;
        }
        
        do {
            $occ = new WSAL_Models_Occurrence();
            $new = $occ->Count();
            usleep(500000); // 500msec
        } while (($old == $new) && (--$max > 0));
        
        if ($is_archive) {
            echo 'false';
        } else {
            echo $old == $new ? 'false' : $new;
        }
        die;
    }
    
    public function AjaxSetIpp() {
        if (!$this->_plugin->settings->CurrentUserCan('view')) {
            die('Access Denied.');
        }
        if (!isset($_REQUEST['count'])) {
            die('Count parameter expected.');
        }
        $this->_plugin->settings->SetViewPerPage((int)$_REQUEST['count']);
        die;
    }
    
    public function AjaxSearchSite() {
        if (!$this->_plugin->settings->CurrentUserCan('view')) {
            die('Access Denied.');
        }
        if (!isset($_REQUEST['search'])) {
            die('Search parameter expected.');
        }
        $grp1 = array();
        $grp2 = array();
        
        $search = $_REQUEST['search'];
        
        foreach ($this->GetListView()->get_sites() as $site) {
            if (stripos($site->blogname, $search) !== false) {
                $grp1[] = $site;
            } elseif (stripos($site->domain, $search) !== false) {
                $grp2[] = $site;
            }
        }
        die(json_encode(array_slice($grp1 + $grp2, 0, 7)));
    }

    public function AjaxSwitchDB() {
        if (isset($_REQUEST['selected_db'])) {
            $wp_session = WP_Session::get_instance();
            $wp_session['selected_db'] = $_REQUEST['selected_db'];
        }
    }
    
    public function Header() {
        add_thickbox();
        wp_enqueue_style('darktooltip', $this->_plugin->GetBaseUrl() . '/css/darktooltip.css', array(), '');
        wp_enqueue_style(
            'auditlog',
            $this->_plugin->GetBaseUrl() . '/css/auditlog.css',
            array(),
            filemtime($this->_plugin->GetBaseDir() . '/css/auditlog.css')
        );
    }
    
    public function Footer() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('darktooltip', $this->_plugin->GetBaseUrl() . '/js/jquery.darktooltip.js', array('jquery'), '');
        wp_enqueue_script('suggest');
        wp_enqueue_script(
            'auditlog',
            $this->_plugin->GetBaseUrl() . '/js/auditlog.js',
            array(),
            filemtime($this->_plugin->GetBaseDir() . '/js/auditlog.js')
        );
    }
}
