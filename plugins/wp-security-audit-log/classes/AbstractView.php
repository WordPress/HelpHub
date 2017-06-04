<?php

abstract class WSAL_AbstractView {
    
    /**
     * @var WpSecurityAuditLog 
     */
    protected $_plugin;
    
    protected $_wpversion;
    
    /**
     * Contains the result to a call to add_submenu_page().
     * @var string
     */
    public $hook_suffix = '';
    
    /**
     * Tells us whether this view is currently being displayed or not.
     * @var boolean
     */
    public $is_active = false;
    
    /**
     * @param WpSecurityAuditLog $plugin
     */
    public function __construct(WpSecurityAuditLog $plugin){
        $this->_plugin = $plugin;
        
        // get and store wordpress version
        global $wp_version;
        if(!isset($wp_version))
            $wp_version = get_bloginfo('version');
        $this->_wpversion = floatval($wp_version);
        
        // handle admin notices
        add_action('wp_ajax_AjaxDismissNotice', array($this, 'AjaxDismissNotice'));
    }
    
    public static $AllowedNoticeNames = array();
    
    /**
     * Dismiss an admin notice through ajax.
     * @internal
     */
    public function AjaxDismissNotice(){
        if(!$this->_plugin->settings->CurrentUserCan('view'))
            die('Access Denied.');
        
        if(!isset($_REQUEST['notice']))
            die('Notice name expected as "notice" parameter.');
        
        $this->DismissNotice($_REQUEST['notice']);
    }
    
    /**
     * @param string $name Name of notice.
     * @return boolean Whether notice got dismissed or not.
     */
    public function IsNoticeDismissed($name){
        $user_id = get_current_user_id();
        $meta_key = 'wsal-notice-' . $name;
        self::$AllowedNoticeNames[] = $name;
        return !!get_user_meta($user_id, $meta_key, true);
    }
    
    /**
     * @param string $name Name of notice to dismiss.
     */
    public function DismissNotice($name){
        $user_id = get_current_user_id();
        $meta_key = 'wsal-notice-' . $name;
        $old_value = get_user_meta($user_id, $meta_key, true);
        if (in_array($name, self::$AllowedNoticeNames) || $old_value === false)
            update_user_meta($user_id, $meta_key, '1');
    }
    
    /**
     * @param string $name Makes this notice available.
     */
    public function RegisterNotice($name){
        self::$AllowedNoticeNames[] = $name;
    }
    
    /**
     * @return string Return page name (for menu etc).
     */
    abstract public function GetName();
    
    /**
     * @return string Return page title.
     */
    abstract public function GetTitle();
    
    /**
     * @return string Page icon name.
     */
    abstract public function GetIcon();
    
    /**
     * @return int Menu weight, the higher this is, the lower it goes.
     */
    abstract public function GetWeight();
    
    /**
     * Renders and outputs the view directly.
     */
    abstract public function Render();
    
    /**
     * Renders the view icon (this has been deprecated in newwer WP versions).
     */
    public function RenderIcon(){
        ?><div id="icon-plugins" class="icon32"><br></div><?php
    }
    
    /**
     * Renders the view title.
     */
    public function RenderTitle(){
        ?><h2><?php echo esc_html($this->GetTitle()); ?></h2><?php
    }
    
    /**
     * @link self::Render()
     */
    public function RenderContent(){
        $this->Render();
    }
    
    /**
     * @return boolean Whether page should appear in menu or not.
     */
    public function IsVisible(){ return true; }
    
    /**
     * @return boolean Whether page should be accessible or not.
     */
    public function IsAccessible(){ return true; }
    
    /**
     * Used for rendering stuff into head tag.
     */
    public function Header(){}
    
    /**
     * Used for rendering stuff in page fotoer.
     */
    public function Footer(){}
    
    /**
     * @return string Safe view menu name.
     */
    public function GetSafeViewName(){
        return 'wsal-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $this->GetViewName());
    }
    
    /**
     * Override this and make it return true to create a shortcut link in plugin page to the view.
     * @return boolean
     */
    public function HasPluginShortcutLink(){
        return false;
    }
    
    /**
     * @return string URL to backend page for displaying view.
     */
    public function GetUrl(){
        $fn = function_exists('network_admin_url') ? 'network_admin_url' : 'admin_url';
        return $fn('admin.php?page=' . $this->GetSafeViewName());
    }
    
    /**
     * @return string Generates view name out of class name.
     */
    public function GetViewName(){
        return strtolower(str_replace(array('WSAL_Views_', 'WSAL_'), '', get_class($this)));
    }
    
}