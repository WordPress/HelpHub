<?php

class WSAL_ViewManager {
    
    /**
     * @var WSAL_AbstractView[] 
     */
    public $views = array();
    
    /**
     * @var WpSecurityAuditLog
     */
    protected $_plugin;

    public function __construct(WpSecurityAuditLog $plugin){
        $this->_plugin = $plugin;
        
        // load views
        foreach(glob(dirname(__FILE__) . '/Views/*.php') as $file)
            $this->AddFromFile($file);
        
        // add menus
        add_action('admin_menu', array($this, 'AddAdminMenus'));
        add_action('network_admin_menu', array($this, 'AddAdminMenus'));
        
        // add plugin shortcut links
        add_filter('plugin_action_links_' . $plugin->GetBaseName(), array($this, 'AddPluginShortcuts'));
        
        // render header
        add_action('admin_enqueue_scripts', array($this, 'RenderViewHeader'));
        
        // render footer
        add_action('admin_footer', array($this, 'RenderViewFooter'));
    }
    
    /**
     * Add new view from file inside autoloader path.
     * @param string $file Path to file.
     */
    public function AddFromFile($file){
        $this->AddFromClass($this->_plugin->GetClassFileClassName($file));
    }
    
    /**
     * Add new view given class name.
     * @param string $class Class name.
     */
    public function AddFromClass($class){
        $this->AddInstance(new $class($this->_plugin));
    }
    
    /**
     * Add newly created view to list.
     * @param WSAL_AbstractView $view The new view.
     */
    public function AddInstance(WSAL_AbstractView $view){
        $this->views[] = $view;
    }
    
    /**
     * Order views by their declared weight.
     */
    public function ReorderViews(){
        usort($this->views, array($this, 'OrderByWeight'));
    }
    
    /**
     * @internal This has to be public for PHP to call it.
     * @param WSAL_AbstractView $a
     * @param WSAL_AbstractView $b
     * @return int
     */
    public function OrderByWeight(WSAL_AbstractView $a, WSAL_AbstractView $b){
        $wa = $a->GetWeight();
        $wb = $b->GetWeight();
        switch(true){
            case $wa < $wb:
                return -1;
            case $wa > $wb:
                return 1;
            default:
                return 0;
        }
    }
    
    /**
     * Wordpress Action
     */
    public function AddAdminMenus(){
        $this->ReorderViews();
        
        if($this->_plugin->settings->CurrentUserCan('view') && count($this->views)){
            // add main menu
            $this->views[0]->hook_suffix = add_menu_page(
                'WP Security Audit Log',
                'Audit Log',
                'read', // no capability requirement
                $this->views[0]->GetSafeViewName(),
                array($this, 'RenderViewBody'),
                $this->views[0]->GetIcon(),
                '2.5' // right after dashboard
            );

            // add menu items
            foreach ($this->views as $view) {
                if ($view->IsAccessible()) {
                    if ($this->GetClassNameByView($view->GetName())) {
                        continue;
                    }
                    $view->hook_suffix = add_submenu_page(
                        $view->IsVisible() ? $this->views[0]->GetSafeViewName() : null,
                        $view->GetTitle(),
                        $view->GetName(),
                        'read', // no capability requirement
                        $view->GetSafeViewName(),
                        array($this, 'RenderViewBody'),
                        $view->GetIcon()
                    );
                }
            }
        }
    }
    
    /**
     * Wordpress Filter
     */
    public function AddPluginShortcuts($old_links){
        $this->ReorderViews();
        
        $new_links = array();
        foreach($this->views as $view){
            if($view->HasPluginShortcutLink()){
                $new_links[] =
                    '<a href="'
                            . admin_url('admin.php?page='
                                . $view->GetSafeViewName()
                            ) . '">'
                        . $view->GetName()
                    . '</a>';
            }
        }
        return array_merge($new_links, $old_links);
    }
    
    /**
     * @return int Returns page id of current page (or false on error).
     */
    protected function GetBackendPageIndex(){
        if(isset($_REQUEST['page']))
            foreach($this->views as $i => $view)
                if($_REQUEST['page'] == $view->GetSafeViewName())
                    return $i;
        return false;
    }
    
    /**
     *
     * @var WSAL_AbstractView|null
     */
    protected $_active_view = false;
    
    /**
     * @return WSAL_AbstractView|null Returns the current active view or null if none.
     */
    public function GetActiveView(){
        if($this->_active_view === false){
            $this->_active_view = null;
            
            if(isset($_REQUEST['page']))
                foreach($this->views as $view)
                    if($_REQUEST['page'] == $view->GetSafeViewName())
                        $this->_active_view = $view;
            
            if($this->_active_view)
                $this->_active_view->is_active = true;
        }
        return $this->_active_view;
    }
    
    /**
     * Render header of the current view.
     */
    public function RenderViewHeader(){
        if (!!($view = $this->GetActiveView())) $view->Header();
    }
    
    /**
     * Render footer of the current view.
     */
    public function RenderViewFooter(){
        if (!!($view = $this->GetActiveView())) $view->Footer();
    }
    
    /**
     * Render content of the current view.
     */
    public function RenderViewBody(){
        $view = $this->GetActiveView();
        ?><div class="wrap"><?php
            $view->RenderIcon();
            $view->RenderTitle();
            $view->RenderContent();
        ?></div><?php
    }
    
    /**
     * Returns view instance corresponding to its class name.
     * @param string $className View class name.
     * @return WSAL_AbstractView The view or false on failure.
     */
    public function FindByClassName($className){
        foreach($this->views as $view)
            if($view instanceof $className)
                return $view;
        return false;
    }

    private function GetClassNameByView($name_view)
    {
        $not_show = false;
        switch ($name_view) {
            case 'Notifications Email':
                if (class_exists('WSAL_NP_Plugin')) {
                    $not_show = true;
                }
                break;
            case 'Logged In Users':
                if (class_exists('WSAL_User_Management_Plugin')) {
                    $not_show = true;
                }
                break;
            case 'Reports':
                if (class_exists('WSAL_Rep_Plugin')) {
                    $not_show = true;
                }
                break;
            case 'Search':
                if (class_exists('WSAL_SearchExtension')) {
                    $not_show = true;
                }
                break;
            case 'External DB ':
                if (class_exists('WSAL_Ext_Plugin')) {
                    $not_show = true;
                }
                break;
            case ' Add Functionality':
                if (class_exists('WSAL_NP_Plugin') ||
                    class_exists('WSAL_User_Management_Plugin') ||
                    class_exists('WSAL_Rep_Plugin') ||
                    class_exists('WSAL_SearchExtension') ||
                    class_exists('WSAL_Ext_Plugin')) {
                    $not_show = true;
                }
                break;
        }
        return $not_show;
    }
}
