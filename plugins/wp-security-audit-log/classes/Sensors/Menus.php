<?php

class WSAL_Sensors_Menus extends WSAL_AbstractSensor
{
    protected $_OldMenu = null;
    protected $_OldMenuTerms = array();
    protected $_OldMenuItems = array();
    protected $_OldMenuLocations = null;

    public function HookEvents()
    {
        add_action('wp_create_nav_menu', array($this, 'CreateMenu'), 10, 2);
        add_action('wp_delete_nav_menu', array($this, 'DeleteMenu'), 10, 1);
        add_action('wp_update_nav_menu', array($this, 'UpdateMenu'), 10, 2);

        add_action('wp_update_nav_menu_item', array($this, 'UpdateMenuItem'), 10, 3);
        add_action('admin_menu', array($this, 'ManageMenuLocations'));
    
        add_action('admin_init', array($this, 'EventAdminInit'));
        // Customizer trigger
        add_action('customize_register', array($this, 'CustomizeInit'));
        add_action('customize_save_after', array($this, 'CustomizeSave'));
    }
    
    public function UpdateMenuItem($menu_id, $menu_item_db_id, $args)
    {
        $oldMenuItems = array();
        if (isset($_POST['menu-item-title']) && isset($_POST['menu-name'])) {
            $is_changed_order = false;
            $is_sub_item = false;
            $newMenuItems = array_keys($_POST['menu-item-title']);
            $items = wp_get_nav_menu_items($menu_id);
            if (!empty($this->_OldMenuItems)) {
                foreach ($this->_OldMenuItems as $oldItem) {
                    if ($oldItem['menu_id'] == $menu_id) {
                        $item_id = $oldItem['item_id'];
                        if ($item_id == $menu_item_db_id) {
                            if ($oldItem['menu_order'] != $args['menu-item-position']) {
                                $is_changed_order = true;
                            }
                            if (!empty($args['menu-item-parent-id'])) {
                                $is_sub_item = true;
                            }
                            if (!empty($args['menu-item-title']) && $oldItem['title'] != $args['menu-item-title']) {
                                $this->EventModifiedItems($_POST['menu-item-object'][$menu_item_db_id], $_POST['menu-item-title'][$menu_item_db_id], $_POST['menu-name']);
                            }
                        }
                        $oldMenuItems[$item_id] = array("type" => $oldItem['object'], "title" => $oldItem['title'], "parent" => $oldItem['menu_item_parent']);
                    }
                }
            }
            if ($is_changed_order) {
                $item_name = $oldMenuItems[$menu_item_db_id]['title'];
                $this->EventChangeOrder($item_name, $oldItem['menu_name']);
            }
            if ($is_sub_item) {
                $item_parent_id = $args['menu-item-parent-id'];
                $item_name = $oldMenuItems[$menu_item_db_id]['title'];
                if ($oldMenuItems[$menu_item_db_id]['parent'] != $item_parent_id) {
                    $parent_name = $oldMenuItems[$item_parent_id]['title'];
                    $this->EventChangeSubItem($item_name, $parent_name, $_POST['menu-name']);
                }
            }

            $addedItems = array_diff($newMenuItems, array_keys($oldMenuItems));
            // Add Items to the menu
            if (count($addedItems) > 0) {
                if (in_array($menu_item_db_id, $addedItems)) {
                    $this->EventAddItems($_POST['menu-item-object'][$menu_item_db_id], $_POST['menu-item-title'][$menu_item_db_id], $_POST['menu-name']);
                }
            }
            $removedItems = array_diff(array_keys($oldMenuItems), $newMenuItems);
            // Remove items from the menu
            if (count($removedItems) > 0) {
                if (array_search($menu_item_db_id, $newMenuItems) == (count($newMenuItems)-1)) {
                    foreach ($removedItems as $removed_item_id) {
                        $this->EventRemoveItems($oldMenuItems[$removed_item_id]['type'], $oldMenuItems[$removed_item_id]['title'], $_POST['menu-name']);
                    }
                }
            }
        }
    }

    public function CreateMenu($term_id, $menu_data)
    {
        $this->plugin->alerts->Trigger(2078, array(
            'MenuName' => $menu_data['menu-name']
        ));
    }

    public function ManageMenuLocations()
    {
        // Manage Location tab
        if (isset($_POST['menu-locations'])) {
            $new_locations = $_POST['menu-locations'];
            if (isset($new_locations['primary'])) {
                $this->LocationSetting($new_locations['primary'], 'primary');
            }
            if (isset($new_locations['social'])) {
                $this->LocationSetting($new_locations['social'], 'social');
            }
        }
    }

    private function LocationSetting($new_location, $type)
    {
        $old_locations = get_nav_menu_locations();
        if ($new_location  != 0) {
            $menu = wp_get_nav_menu_object($new_location);
            if (!empty($old_locations[$type]) && $old_locations[$type] != $new_location) {
                $this->EventMenuSetting($menu->name, "Enabled", "Location: ".$type." menu");
            }
        } else {
            if (!empty($old_locations[$type])) {
                $menu = wp_get_nav_menu_object($old_locations[$type]);
                $this->EventMenuSetting($menu->name, "Disabled", "Location: ".$type." menu");
            }
        }
    }

    public function DeleteMenu($term_id)
    {
        if ($this->_OldMenu) {
            $this->plugin->alerts->Trigger(2081, array(
                'MenuName' => $this->_OldMenu->name
            ));
        }
    }

    public function UpdateMenu($menu_id, $menu_data = null)
    {
        if (!empty($menu_data)) {
            $contentNamesOld = array();
            $contentTypesOld = array();
            $contentOrderOld = array();

            $items = wp_get_nav_menu_items($menu_id);
            if (!empty($items)) {
                foreach ($items as $item) {
                    array_push($contentNamesOld, $item->title);
                    array_push($contentTypesOld, $item->object);
                    $contentOrderOld[$item->ID] = $item->menu_order;
                }
            }
            // Menu changed name
            if (!empty($this->_OldMenuTerms) && isset($_POST['menu']) && isset($_POST['menu-name'])) {
                foreach ($this->_OldMenuTerms as $oldMenuTerm) {
                    if ($oldMenuTerm['term_id'] == $_POST['menu']) {
                        if ($oldMenuTerm['name'] != $_POST['menu-name']) {
                            $this->EventChangeName($oldMenuTerm['name'], $_POST['menu-name']);
                        } else {
                            // Remove the last menu item
                            if (count($contentNamesOld) == 1 && count($contentTypesOld) == 1) {
                                $this->EventRemoveItems($contentTypesOld[0], $contentNamesOld[0], $_POST['menu-name']);
                            }
                        }
                    }
                }
            }
            // Enable/Disable menu setting
            $nav_menu_options = maybe_unserialize(get_option('nav_menu_options'));
            $auto_add = null;
            if (isset($nav_menu_options['auto_add'])) {
                if (in_array($menu_id, $nav_menu_options['auto_add'])) {
                    if (empty($_POST['auto-add-pages'])) {
                        $auto_add = "Disabled";
                    }
                } else {
                    if (isset($_POST['auto-add-pages'])) {
                        $auto_add = "Enabled";
                    }
                }
            } else {
                if (isset($_POST['auto-add-pages'])) {
                    $auto_add = "Enabled";
                }
            }
            // Alert 2082 Auto add pages
            if (!empty($auto_add)) {
                $this->EventMenuSetting($menu_data['menu-name'], $auto_add, "Auto add pages");
            }
            
            $nav_menu_locations = get_nav_menu_locations();

            $locationPrimary = null;
            if (isset($this->_OldMenuLocations['primary']) && isset($nav_menu_locations['primary'])) {
                if ($nav_menu_locations['primary'] == $menu_id && $this->_OldMenuLocations['primary'] != $nav_menu_locations['primary']) {
                    $locationPrimary = "Enabled";
                }
            } elseif (empty($this->_OldMenuLocations['primary']) && isset($nav_menu_locations['primary'])) {
                if ($nav_menu_locations['primary'] == $menu_id) {
                    $locationPrimary = "Enabled";
                }
            } elseif (isset($this->_OldMenuLocations['primary']) && empty($nav_menu_locations['primary'])) {
                if ($this->_OldMenuLocations['primary'] == $menu_id) {
                    $locationPrimary = "Disabled";
                }
            }
            // Alert 2082 Primary menu
            if (!empty($locationPrimary)) {
                $this->EventMenuSetting($menu_data['menu-name'], $locationPrimary, "Location: primary menu");
            }
            
            $locationSocial = null;
            if (isset($this->_OldMenuLocations['social']) && isset($nav_menu_locations['social'])) {
                if ($nav_menu_locations['social'] == $menu_id && $this->_OldMenuLocations['social'] != $nav_menu_locations['social']) {
                    $locationSocial = "Enabled";
                }
            } elseif (empty($this->_OldMenuLocations['social']) && isset($nav_menu_locations['social'])) {
                if ($nav_menu_locations['social'] == $menu_id) {
                    $locationSocial = "Enabled";
                }
            } elseif (isset($this->_OldMenuLocations['social']) && empty($nav_menu_locations['social'])) {
                if ($this->_OldMenuLocations['social'] == $menu_id) {
                    $locationSocial = "Disabled";
                }
            }
            // Alert 2082 Social links menu
            if (!empty($locationSocial)) {
                $this->EventMenuSetting($menu_data['menu-name'], $locationSocial, "Location: social menu");
            }
        }
    }

    private function BuildOldMenuTermsAndItems()
    {
        $menus = wp_get_nav_menus();
        if (!empty($menus)) {
            foreach ($menus as $menu) {
                array_push($this->_OldMenuTerms, array("term_id" => $menu->term_id, "name" => $menu->name));
                $items = wp_get_nav_menu_items($menu->term_id);
                if (!empty($items)) {
                    foreach ($items as $item) {
                        array_push($this->_OldMenuItems, array(
                            "menu_id" => $menu->term_id,
                            'item_id' => $item->ID,
                            'title' => $item->title,
                            'object' => $item->object,
                            'menu_name' => $menu->name,
                            'menu_order' => $item->menu_order,
                            'url' => $item->url,
                            'menu_item_parent' => $item->menu_item_parent
                        ));
                    }
                }
            }
        }
    }
    
    public function EventAdminInit()
    {
        $is_nav_menu = basename($_SERVER['SCRIPT_NAME']) == 'nav-menus.php';
        if ($is_nav_menu) {
            if (isset($_GET['action']) && $_GET['action'] == 'delete') {
                if (isset($_GET['menu'])) {
                    $this->_OldMenu = wp_get_nav_menu_object($_GET['menu']);
                }
            } else {
                $this->BuildOldMenuTermsAndItems();
            }
            $this->_OldMenuLocations = get_nav_menu_locations();
        }
    }

    public function CustomizeInit()
    {
        $this->BuildOldMenuTermsAndItems();
        $this->_OldMenuLocations = get_nav_menu_locations();
    }

    /**
     * Customize Events Function
     */
    public function CustomizeSave()
    {
        $updateMenus = array();
        $menus = wp_get_nav_menus();
        if (!empty($menus)) {
            foreach ($menus as $menu) {
                array_push($updateMenus, array("term_id" => $menu->term_id, "name" => $menu->name));
            }
        }
        // Deleted Menu
        if (isset($updateMenus) && isset($this->_OldMenuTerms)) {
            $terms = array_diff(array_map('serialize', $this->_OldMenuTerms), array_map('serialize', $updateMenus));
            $terms = array_map('unserialize', $terms);
            
            if (isset($terms) && count($terms) > 0) {
                foreach ($terms as $term) {
                    $this->plugin->alerts->Trigger(2081, array(
                        'MenuName' => $term['name']
                    ));
                }
            }
        }
        if (isset($_POST['action']) && $_POST['action'] == 'customize_save') {
            if (isset($_POST['wp_customize'], $_POST['customized'])) {
                $customized = json_decode(wp_unslash($_POST['customized']), true);
                if (is_array($customized)) {
                    foreach ($customized as $key => $value) {
                        if (!empty($value['nav_menu_term_id'])) {
                            $is_occurred_event = false;
                            $menu = wp_get_nav_menu_object($value['nav_menu_term_id']);
                            $content_name = !empty($value['title']) ? $value['title'] : "no title";
                            if (!empty($this->_OldMenuItems)) {
                                foreach ($this->_OldMenuItems as $old_item) {
                                    $item_id = substr(trim($key, ']'), 14);
                                    if ($old_item['item_id'] == $item_id) {
                                        // Modified Items in the menu
                                        if ($old_item['title'] != $content_name) {
                                            $is_occurred_event = true;
                                            $this->EventModifiedItems($value['type_label'], $content_name, $menu->name);
                                        }
                                        // Moved as a sub-item
                                        if ($old_item['menu_item_parent'] != $value['menu_item_parent'] && $value['menu_item_parent'] != 0) {
                                            $is_occurred_event = true;
                                            $parent_name = $this->GetItemName($value['nav_menu_term_id'], $value['menu_item_parent']);
                                            $this->EventChangeSubItem($content_name, $parent_name, $menu->name);
                                        }
                                        // Changed order of the objects in a menu
                                        if ($old_item['menu_order'] != $value['position']) {
                                            $is_occurred_event = true;
                                            $this->EventChangeOrder($content_name, $menu->name);
                                        }
                                    }
                                }
                            }
                            // Add Items to the menu
                            if (!$is_occurred_event) {
                                $menu_name = !empty($customized['new_menu_name']) ? $customized['new_menu_name'] : $menu->name;
                                $this->EventAddItems($value['type_label'], $content_name, $menu_name);
                            }
                        } else {
                            // Menu changed name
                            if (isset($updateMenus) && isset($this->_OldMenuTerms)) {
                                foreach ($this->_OldMenuTerms as $old_menu) {
                                    foreach ($updateMenus as $update_menu) {
                                        if ($old_menu['term_id'] == $update_menu['term_id'] && $old_menu['name'] != $update_menu['name']) {
                                            $this->EventChangeName($old_menu['name'], $update_menu['name']);
                                        }
                                    }
                                }
                            }
                            // Setting Auto add pages
                            if (!empty($value) && isset($value['auto_add'])) {
                                if ($value['auto_add']) {
                                    $this->EventMenuSetting($value['name'], 'Enabled', "Auto add pages");
                                } else {
                                    $this->EventMenuSetting($value['name'], 'Disabled', "Auto add pages");
                                }
                            }
                            // Setting Location
                            if (false !== strpos($key, 'nav_menu_locations[')) {
                                $loc = substr(trim($key, ']'), 19);
                                if (!empty($value)) {
                                    $menu = wp_get_nav_menu_object($value);
                                    $menu_name = !empty($customized['new_menu_name']) ? $customized['new_menu_name'] : (!empty($menu) ? $menu->name : '');
                                    $this->EventMenuSetting($menu_name, "Enabled", "Location: ".$loc." menu");
                                } else {
                                    if (!empty($this->_OldMenuLocations[$loc])) {
                                        $menu = wp_get_nav_menu_object($this->_OldMenuLocations[$loc]);
                                        $menu_name = !empty($customized['new_menu_name']) ? $customized['new_menu_name'] : (!empty($menu) ? $menu->name : '');
                                        $this->EventMenuSetting($menu_name, "Disabled", "Location: ".$loc." menu");
                                    }
                                }
                            }
                            // Remove items from the menu
                            if (false !== strpos($key, 'nav_menu_item[')) {
                                $item_id = substr(trim($key, ']'), 14);
                                if (!empty($this->_OldMenuItems)) {
                                    foreach ($this->_OldMenuItems as $old_item) {
                                        if ($old_item['item_id'] == $item_id) {
                                            $this->EventRemoveItems($old_item['object'], $old_item['title'], $old_item['menu_name']);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function EventAddItems($content_type, $content_name, $menu_name)
    {
        $this->plugin->alerts->Trigger(2079, array(
            'ContentType' => $content_type,
            'ContentName' => $content_name,
            'MenuName' => $menu_name
        ));
    }

    private function EventRemoveItems($content_type, $content_name, $menu_name)
    {
        $this->plugin->alerts->Trigger(2080, array(
            'ContentType' => $content_type,
            'ContentName' => $content_name,
            'MenuName' => $menu_name
        ));
    }

    private function EventMenuSetting($menu_name, $status, $menu_setting)
    {
        $this->plugin->alerts->Trigger(2082, array(
            'Status' => $status,
            'MenuSetting' => $menu_setting,
            'MenuName' => $menu_name
        ));
    }

    private function EventModifiedItems($content_type, $content_name, $menu_name)
    {
        $this->plugin->alerts->Trigger(2083, array(
            'ContentType' => $content_type,
            'ContentName' => $content_name,
            'MenuName' => $menu_name
        ));
    }

    private function EventChangeName($old_menu_name, $new_menu_name)
    {
        $this->plugin->alerts->Trigger(2084, array(
            'OldMenuName' => $old_menu_name,
            'NewMenuName' => $new_menu_name
        ));
    }

    private function EventChangeOrder($item_name, $menu_name)
    {
        $this->plugin->alerts->Trigger(2085, array(
            'ItemName' => $item_name,
            'MenuName' => $menu_name
        ));
    }

    private function EventChangeSubItem($item_name, $parent_name, $menu_name)
    {
        $this->plugin->alerts->Trigger(2089, array(
            'ItemName' => $item_name,
            'ParentName' => $parent_name,
            'MenuName' => $menu_name
        ));
    }

    private function GetItemName($term_id, $item_id)
    {
        $item_name = '';
        $menu_items = wp_get_nav_menu_items($term_id);
        foreach ($menu_items as $menu_item) {
            if ($menu_item->ID == $item_id) {
                $item_name = $menu_item->title;
                break;
            }
        }
        return $item_name;
    }
}
