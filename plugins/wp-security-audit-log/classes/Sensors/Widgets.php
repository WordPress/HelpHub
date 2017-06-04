<?php

class WSAL_Sensors_Widgets extends WSAL_AbstractSensor {

    public function HookEvents() {
        if(current_user_can("edit_theme_options")) { 
            add_action('admin_init', array($this, 'EventWidgetMove'));
            add_action('admin_init', array($this, 'EventWidgetPostMove'));
        }
        add_action('sidebar_admin_setup', array($this, 'EventWidgetActivity'));
    }

    protected $_WidgetMoveData = null;
    
    public function EventWidgetMove(){
        if(isset($_POST) && !empty($_POST['sidebars']))
        {
            $crtSidebars = $_POST['sidebars'];
            $sidebars = array();
            foreach ( $crtSidebars as $key => $val )
            {
                $sb = array();
                if ( !empty($val) )
                {
                    $val = explode(',', $val);
                    foreach ( $val as $k => $v )
                    {
                        if ( strpos($v, 'widget-') === false ) continue;
                        $sb[$k] = substr($v, strpos($v, '_') + 1);
                    }
                }
                $sidebars[$key] = $sb;
            }
            $crtSidebars = $sidebars;
            $dbSidebars = get_option('sidebars_widgets');
            $wName = $fromSidebar = $toSidebar = '';
            foreach($crtSidebars as $sidebarName => $values)
            {
                if(is_array($values) && ! empty($values) && isset($dbSidebars[$sidebarName]))
                {
                    foreach($values as $widgetName)
                    {
                        if(! in_array($widgetName, $dbSidebars[$sidebarName]))
                        {
                            $toSidebar = $sidebarName;
                            $wName = $widgetName;
                            foreach($dbSidebars as $name => $v)
                            {
                                if(is_array($v) && !empty($v) && in_array($widgetName, $v))
                                {
                                    $fromSidebar = $name;
                                    continue;
                                }
                            }
                        }
                    }
                }
            }
            
            if (empty($wName) || empty($fromSidebar) || empty($toSidebar)) return;

            if(preg_match('/^sidebar-/', $fromSidebar) || preg_match('/^sidebar-/', $toSidebar)){
                // This option will hold the data needed to trigger the event 2045
                // as at this moment the $wp_registered_sidebars variable is not yet populated
                // so we cannot retrieve the name for sidebar-1 || sidebar-2
                // we will then check for this variable in the EventWidgetPostMove() event
                $this->_WidgetMoveData = array('widget' => $wName, 'from' => $fromSidebar, 'to' => $toSidebar);
                return;
            }

            $this->plugin->alerts->Trigger(2045, array(
                'WidgetName' => $wName,
                'OldSidebar' => $fromSidebar,
                'NewSidebar' => $toSidebar,
            ));
        }
    }
    
    public function EventWidgetPostMove() {
        //#!-- generates the event 2071
        if (isset($_REQUEST['action'])&&($_REQUEST['action']=='widgets-order'))
        {
            if (isset($_REQUEST['sidebars'])) {
                // Get the sidebars from $_REQUEST
                $requestSidebars = array();
                if ($_REQUEST['sidebars']) {
                    foreach($_REQUEST['sidebars'] as $key => &$value){
                        if(!empty($value)){
                            // build the sidebars array
                            $value = explode(',', $value);
                            // Cleanup widgets' name
                            foreach($value as $k => &$widgetName){
                                $widgetName = preg_replace("/^([a-z]+-[0-9]+)+?_/i",'', $widgetName);
                            }
                            $requestSidebars[$key] = $value;
                        }
                    }
                }

                if ($requestSidebars) {
                    // Get the sidebars from DATABASE
                    $sidebar_widgets = wp_get_sidebars_widgets();
                    // Get global sidebars so we can retrieve the real name of the sidebar
                    global $wp_registered_sidebars;

                    // Check in each array if there's any change
                    foreach ($requestSidebars as $sidebarName => $widgets) {
                        if (isset($sidebar_widgets[$sidebarName])) {
                            foreach ($sidebar_widgets[$sidebarName] as $i => $widgetName) {
                                $index = array_search($widgetName, $widgets);
                                // check to see whether or not the widget has been moved
                                if ($i != $index) {
                                    $sn = $sidebarName;
                                    // Try to retrieve the real name of the sidebar, otherwise fall-back to id: $sidebarName
                                    if ($wp_registered_sidebars && isset($wp_registered_sidebars[$sidebarName])) {
                                        $sn = $wp_registered_sidebars[$sidebarName]['name'];
                                    }
                                    $this->plugin->alerts->Trigger(2071, array(
                                        'WidgetName' => $widgetName,
                                        'OldPosition' => $i+1,
                                        'NewPosition' => $index+1,
                                        'Sidebar' => $sn,
                                    ));
                                }
                            }
                        }
                    }
                }
            }
        }
        //#!--

        if($this->_WidgetMoveData){
            $wName = $this->_WidgetMoveData['widget'];
            $fromSidebar = $this->_WidgetMoveData['from'];
            $toSidebar = $this->_WidgetMoveData['to'];
            
            global $wp_registered_sidebars;
            
            if(preg_match('/^sidebar-/', $fromSidebar))
                $fromSidebar = isset($wp_registered_sidebars[$fromSidebar])
                    ? $wp_registered_sidebars[$fromSidebar]['name']
                    : $fromSidebar
                ;
            if(preg_match('/^sidebar-/', $toSidebar))
                $toSidebar = isset($wp_registered_sidebars[$toSidebar])
                    ? $wp_registered_sidebars[$toSidebar]['name']
                    : $toSidebar
                ;
            
            $this->plugin->alerts->Trigger(2045, array(
                'WidgetName' => $wName,
                'OldSidebar' => $fromSidebar,
                'NewSidebar' => $toSidebar,
            ));
        }
    }
    
    public function EventWidgetActivity(){
        if(!isset($_POST) || !isset($_POST['widget-id']) || empty($_POST['widget-id'])){
            return;
        }
        
        $postData = $_POST;
        global $wp_registered_sidebars;
        $canCheckSidebar = (empty($wp_registered_sidebars) ? false : true);
        
        switch(true){
            
            // added widget
            case isset($postData['add_new']) && $postData['add_new'] == 'multi':
                $sidebar = isset($postData['sidebar']) ? $postData['sidebar'] : null;
                if($canCheckSidebar && preg_match('/^sidebar-/', $sidebar)){
                    $sidebar = $wp_registered_sidebars[$sidebar]['name'];
                }
                $this->plugin->alerts->Trigger(2042, array(
                    'WidgetName' => $postData['id_base'],
                    'Sidebar' => $sidebar,
                ));
                break;
                
            // deleted widget
            case isset($postData['delete_widget']) && intval($postData['delete_widget']) == 1:
                $sidebar = isset($postData['sidebar']) ? $postData['sidebar'] : null;
                if($canCheckSidebar && preg_match('/^sidebar-/',$sidebar)){
                    $sidebar = $wp_registered_sidebars[$sidebar]['name'];
                }
                $this->plugin->alerts->Trigger(2044, array(
                    'WidgetName' => $postData['id_base'],
                    'Sidebar' => $sidebar,
                ));
                break;
                
            // modified widget
            case isset($postData['id_base']) && !empty($postData['id_base']):
                $wId = 0;
                if(!empty($postData['multi_number'])){
                    $wId = intval($postData['multi_number']);
                }elseif(!empty($postData['widget_number'])){
                    $wId = intval($postData['widget_number']);
                }
                if(empty($wId))return;

                $wName = $postData['id_base'];
                $sidebar = isset($postData['sidebar']) ? $postData['sidebar'] : null;
                $wData = isset($postData["widget-$wName"][$wId])
                    ? $postData["widget-$wName"][$wId]
                    : null;

                if(empty($wData))return;

                // get info from db
                $wdbData = get_option("widget_".$wName);
                if(empty($wdbData[$wId]))return;

                // transform 'on' -> 1
                foreach($wData as $k => $v)if($v == 'on')$wData[$k] = 1;

                // compare - checks for any changes inside widgets
                $diff = array_diff_assoc($wData, $wdbData[$wId]);
                $count = count($diff);
                if($count > 0){
                    if($canCheckSidebar && preg_match("/^sidebar-/",$sidebar)){
                        $sidebar = $wp_registered_sidebars[$sidebar]['name'];
                    }
                    $this->plugin->alerts->Trigger(2043, array(
                        'WidgetName' => $wName,
                        'Sidebar' => $sidebar,
                    ));
                }
                break;
                
        }
    }
}