<?php
/** ===============================================================================
  Plugin Name: Duplicator
  Plugin URI: http://www.lifeinthegrid.com/duplicator/
  Description: Create and transfer a copy of your WordPress files and database. Duplicate and move a site from one location to another quickly.
  Version: 1.2.8
  Author: Snap Creek
  Author URI: http://www.snapcreek.com/duplicator/
  Text Domain: duplicator
  License: GPLv2 or later

  Copyright 2011-2017  SnapCreek LLC

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

  SOURCE CONTRIBUTORS:
  David Coveney of Interconnect IT Ltd
  https://github.com/interconnectit/Search-Replace-DB/
  ================================================================================ */

require_once("define.php");

if (is_admin() == true) 
{
	//Classes
    require_once 'classes/class.logging.php';
    require_once 'classes/class.settings.php';
    require_once 'classes/utilities/class.u.php';
    require_once 'classes/class.db.php';
    require_once 'classes/class.server.php';
	require_once 'classes/ui/class.ui.viewstate.php';
	require_once 'classes/ui/class.ui.notice.php';
    require_once 'classes/package/class.pack.php';
	 
    //Controllers
	require_once 'ctrls/ctrl.package.php';
	require_once 'ctrls/ctrl.tools.php';
	require_once 'ctrls/ctrl.ui.php';

	/** ========================================================
	 * ACTIVATE/DEACTIVE/UPDATE HOOKS
     * =====================================================  */
	register_activation_hook(__FILE__,   'duplicator_activate');
    register_deactivation_hook(__FILE__, 'duplicator_deactivate');
		
    /**
	 * Hooked into `register_activation_hook`.  Routines used to activate the plugin
     *
     * @access global
     * @return null
     */
    function duplicator_activate() 
	{
        global $wpdb;
		
        //Only update database on version update
        if (DUPLICATOR_VERSION != get_option("duplicator_version_plugin")) 
		{
            $table_name = $wpdb->prefix . "duplicator_packages";

            //PRIMARY KEY must have 2 spaces before for dbDelta to work
			//see: https://codex.wordpress.org/Creating_Tables_with_Plugins
            $sql = "CREATE TABLE `{$table_name}` (
			   id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			   name VARCHAR(250) NOT NULL,
			   hash VARCHAR(50) NOT NULL,
			   status INT(11) NOT NULL,
			   created DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			   owner VARCHAR(60) NOT NULL,
			   package MEDIUMBLOB NOT NULL,
			   PRIMARY KEY  (id),
			   KEY hash (hash))";

            require_once(DUPLICATOR_WPROOTPATH . 'wp-admin/includes/upgrade.php');
            @dbDelta($sql);
        }

        //WordPress Options Hooks
        update_option('duplicator_version_plugin', DUPLICATOR_VERSION);

        //Setup All Directories
        DUP_Util::initSnapshotDirectory();
    }

    /**
	 * Hooked into `plugins_loaded`.  Routines used to update the plugin
     *
     * @access global
     * @return null
     */
    function duplicator_update() 
	{
        if (DUPLICATOR_VERSION != get_option("duplicator_version_plugin")) {
            duplicator_activate();
        }
		load_plugin_textdomain( 'duplicator' );
    }

	/**
	 * Hooked into `register_deactivation_hook`.  Routines used to deactivae the plugin
	 * For uninstall see uninstall.php  Wordpress by default will call the uninstall.php file
     *
     * @access global
     * @return null
     */
    function duplicator_deactivate() 
	{
        //Logic has been added to uninstall.php
    }

	/** ========================================================
	 * ACTION HOOKS
     * =====================================================  */
    add_action('plugins_loaded',	'duplicator_update');
    add_action('plugins_loaded',	'duplicator_wpfront_integrate');
	add_action('admin_init',		'duplicator_init');
    add_action('admin_menu',		'duplicator_menu');
	add_action('admin_notices',		array('DUP_UI_Notice', 'showReservedFilesNotice'));
	
	//CTRL ACTIONS
    add_action('wp_ajax_duplicator_package_scan',        'duplicator_package_scan');
    add_action('wp_ajax_duplicator_package_build',		 'duplicator_package_build');
    add_action('wp_ajax_duplicator_package_delete',		 'duplicator_package_delete');
	$GLOBALS['CTRLS_DUP_CTRL_UI']    = new DUP_CTRL_UI();
	$GLOBALS['CTRLS_DUP_CTRL_Tools'] = new DUP_CTRL_Tools();
	
	/**
	 * User role editor integration 
     *
     * @access global
     * @return null
     */
    function duplicator_wpfront_integrate()
	{
        if (DUP_Settings::Get('wpfront_integrate')) {
            do_action('wpfront_user_role_editor_duplicator_init', array('export', 'manage_options', 'read'));
        }
    }
	
	/**
	 * Hooked into `admin_init`.  Init routines for all admin pages 
     *
     * @access global
     * @return null
     */
    function duplicator_init()
	{
        /* CSS */
        wp_register_style('dup-jquery-ui', DUPLICATOR_PLUGIN_URL . 'assets/css/jquery-ui.css', null, "1.11.2");
        wp_register_style('dup-font-awesome', DUPLICATOR_PLUGIN_URL . 'assets/css/font-awesome.min.css', null, '4.7.0');
        wp_register_style('dup-plugin-style', DUPLICATOR_PLUGIN_URL . 'assets/css/style.css', null, DUPLICATOR_VERSION);
		wp_register_style('dup-jquery-qtip',DUPLICATOR_PLUGIN_URL . 'assets/js/jquery.qtip/jquery.qtip.min.css', null, '2.2.1');
        /* JS */
		wp_register_script('dup-handlebars', DUPLICATOR_PLUGIN_URL . 'assets/js/handlebars.min.js', array('jquery'), '4.0.6');
        wp_register_script('dup-parsley', DUPLICATOR_PLUGIN_URL . 'assets/js/parsley-standalone.min.js', array('jquery'), '1.1.18');
		wp_register_script('dup-jquery-qtip', DUPLICATOR_PLUGIN_URL . 'assets/js/jquery.qtip/jquery.qtip.min.js', array('jquery'), '2.2.1');
    }
	
	/**
	 * Redirects the clicked menu item to the correct location
     *
     * @access global
     * @return null
     */
    function duplicator_get_menu() 
	{
        $current_page = isset($_REQUEST['page']) ? esc_html($_REQUEST['page']) : 'duplicator';
        switch ($current_page) 
		{
            case 'duplicator':			include('views/packages/controller.php');	break;
            case 'duplicator-settings': include('views/settings/controller.php');	break;
            case 'duplicator-tools':	include('views/tools/controller.php');      break;
			case 'duplicator-debug':	include('debug/main.php');					break;
            case 'duplicator-help':		include('views/help/help.php');				break;
            case 'duplicator-about':	include('views/help/about.php');			break;
			case 'duplicator-gopro':	include('views/help/gopro.php');			break;
        }
    }

	/**
	 * Hooked into `admin_menu`.  Loads all of the wp left nav admin menus for Duplicator
     *
     * @access global
     * @return null
     */
    function duplicator_menu() 
	{
        $wpfront_caps_translator = 'wpfront_user_role_editor_duplicator_translate_capability';
		$icon_svg = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz48IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9HcmFwaGljcy9TVkcvMS4xL0RURC9zdmcxMS5kdGQiPjxzdmcgdmVyc2lvbj0iMS4xIiBpZD0iQXJ0d29yayIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgeD0iMHB4IiB5PSIwcHgiIHdpZHRoPSIyMy4yNXB4IiBoZWlnaHQ9IjIyLjM3NXB4IiB2aWV3Qm94PSIwIDAgMjMuMjUgMjIuMzc1IiBlbmFibGUtYmFja2dyb3VuZD0ibmV3IDAgMCAyMy4yNSAyMi4zNzUiIHhtbDpzcGFjZT0icHJlc2VydmUiPjxwYXRoIGZpbGw9IiM5Q0ExQTYiIGQ9Ik0xOC4wMTEsMS4xODhjLTEuOTk1LDAtMy42MTUsMS42MTgtMy42MTUsMy42MTRjMCwwLjA4NSwwLjAwOCwwLjE2NywwLjAxNiwwLjI1TDcuNzMzLDguMTg0QzcuMDg0LDcuNTY1LDYuMjA4LDcuMTgyLDUuMjQsNy4xODJjLTEuOTk2LDAtMy42MTUsMS42MTktMy42MTUsMy42MTRjMCwxLjk5NiwxLjYxOSwzLjYxMywzLjYxNSwzLjYxM2MwLjYyOSwwLDEuMjIyLTAuMTYyLDEuNzM3LTAuNDQ1bDIuODksMi40MzhjLTAuMTI2LDAuMzY4LTAuMTk4LDAuNzYzLTAuMTk4LDEuMTczYzAsMS45OTUsMS42MTgsMy42MTMsMy42MTQsMy42MTNjMS45OTUsMCwzLjYxNS0xLjYxOCwzLjYxNS0zLjYxM2MwLTEuOTk3LTEuNjItMy42MTQtMy42MTUtMy42MTRjLTAuNjMsMC0xLjIyMiwwLjE2Mi0xLjczNywwLjQ0M2wtMi44OS0yLjQzNWMwLjEyNi0wLjM2OCwwLjE5OC0wLjc2MywwLjE5OC0xLjE3M2MwLTAuMDg0LTAuMDA4LTAuMTY2LTAuMDEzLTAuMjVsNi42NzYtMy4xMzNjMC42NDgsMC42MTksMS41MjUsMS4wMDIsMi40OTUsMS4wMDJjMS45OTQsMCwzLjYxMy0xLjYxNywzLjYxMy0zLjYxM0MyMS42MjUsMi44MDYsMjAuMDA2LDEuMTg4LDE4LjAxMSwxLjE4OHoiLz48L3N2Zz4=';
        
		//Main Menu
        $perms = 'export';
        $perms = apply_filters($wpfront_caps_translator, $perms);
        $main_menu = add_menu_page('Duplicator Plugin', 'Duplicator', $perms, 'duplicator', 'duplicator_get_menu', $icon_svg);
		//$main_menu = add_menu_page('Duplicator Plugin', 'Duplicator', $perms, 'duplicator', 'duplicator_get_menu', plugins_url('duplicator/assets/img/logo-menu.svg'));

        $perms = 'export';
        $perms = apply_filters($wpfront_caps_translator, $perms);
		$lang_txt = __('Packages', 'duplicator');
        $page_packages = add_submenu_page('duplicator', $lang_txt, $lang_txt, $perms, 'duplicator', 'duplicator_get_menu');
		
		$perms = 'manage_options';
        $perms = apply_filters($wpfront_caps_translator, $perms);
		$lang_txt = __('Tools', 'duplicator');
        $page_tools = add_submenu_page('duplicator', $lang_txt, $lang_txt, $perms, 'duplicator-tools', 'duplicator_get_menu');

        $perms = 'manage_options';
        $perms = apply_filters($wpfront_caps_translator, $perms);
		$lang_txt = __('Settings', 'duplicator');
        $page_settings = add_submenu_page('duplicator', $lang_txt, $lang_txt, $perms, 'duplicator-settings', 'duplicator_get_menu');

        $perms = 'manage_options';
        $perms = apply_filters($wpfront_caps_translator, $perms);
		$lang_txt = __('Help', 'duplicator');
        $page_help = add_submenu_page('duplicator', $lang_txt, $lang_txt, $perms, 'duplicator-help', 'duplicator_get_menu');

        $perms = 'manage_options';
        $perms = apply_filters($wpfront_caps_translator, $perms);
		$lang_txt = __('About', 'duplicator');
        $page_about = add_submenu_page('duplicator', $lang_txt, $lang_txt, $perms, 'duplicator-about', 'duplicator_get_menu');

		$perms = 'manage_options';
		$lang_txt = __('Go Pro!', 'duplicator');
		$go_pro_link = '<span style="color:#f18500">' . $lang_txt . '</span>';
        $perms = apply_filters($wpfront_caps_translator, $perms);
        $page_gopro = add_submenu_page('duplicator', $go_pro_link, $go_pro_link, $perms, 'duplicator-gopro', 'duplicator_get_menu');
		
		$package_debug = DUP_Settings::Get('package_debug');
		if ($package_debug != null && $package_debug == true)
		{
			$perms = 'manage_options';
			$perms = apply_filters($wpfront_caps_translator, $perms);			
			$lang_txt = __('Debug', 'duplicator');
			$page_debug = add_submenu_page('duplicator', $lang_txt, $lang_txt, $perms, 'duplicator-debug', 'duplicator_get_menu');
			add_action('admin_print_scripts-' . $page_debug, 'duplicator_scripts');
			add_action('admin_print_styles-'  . $page_debug, 'duplicator_styles');
		}

        //Apply Scripts
        add_action('admin_print_scripts-' . $page_packages, 'duplicator_scripts');
        add_action('admin_print_scripts-' . $page_settings, 'duplicator_scripts');
        add_action('admin_print_scripts-' . $page_help, 'duplicator_scripts');
        add_action('admin_print_scripts-' . $page_tools, 'duplicator_scripts');
        add_action('admin_print_scripts-' . $page_about, 'duplicator_scripts');
		add_action('admin_print_scripts-' . $page_gopro, 'duplicator_scripts');
		
        //Apply Styles
        add_action('admin_print_styles-' . $page_packages, 'duplicator_styles');
        add_action('admin_print_styles-' . $page_settings, 'duplicator_styles');
        add_action('admin_print_styles-' . $page_help, 'duplicator_styles');
        add_action('admin_print_styles-' . $page_tools, 'duplicator_styles');
        add_action('admin_print_styles-' . $page_about, 'duplicator_styles');
		add_action('admin_print_styles-' . $page_gopro, 'duplicator_styles');
		
    }

    /**
	 * Loads all required javascript libs/source for DupPro
     *
     * @access global
     * @return null
     */
    function duplicator_scripts() 
	{
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-progressbar');
        wp_enqueue_script('dup-parsley');
		wp_enqueue_script('dup-jquery-qtip');
		
    }

    /**
	 * Loads all CSS style libs/source for DupPro
     *
     * @access global
     * @return null
     */
    function duplicator_styles() 
	{
        wp_enqueue_style('dup-jquery-ui');
        wp_enqueue_style('dup-font-awesome');
		wp_enqueue_style('dup-plugin-style');
		wp_enqueue_style('dup-jquery-qtip');
    }


	/** ========================================================
	 * FILTERS
     * =====================================================  */
	add_filter('plugin_action_links', 'duplicator_manage_link', 10, 2);
    add_filter('plugin_row_meta', 'duplicator_meta_links', 10, 2);
	
	/**
	 * Adds the manage link in the plugins list 
     *
     * @access global
     * @return string The manage link in the plugins list 
     */	
    function duplicator_manage_link($links, $file) 
	{
        static $this_plugin;
        if (!$this_plugin)
            $this_plugin = plugin_basename(__FILE__);

        if ($file == $this_plugin) {
            $settings_link = '<a href="admin.php?page=duplicator">' . __("Manage", 'duplicator') . '</a>';
            array_unshift($links, $settings_link);
        }
        return $links;
    }
	
	/**
	 * Adds links to the plugins manager page
     *
     * @access global
     * @return string The meta help link data for the plugins manager
     */
    function duplicator_meta_links($links, $file) 
	{
        $plugin = plugin_basename(__FILE__);
        // create link
        if ($file == $plugin) {
            $links[] = '<a href="admin.php?page=duplicator-help" title="' . __('Get Help', 'duplicator') . '" >' . __('Help', 'duplicator') . '</a>';
            $links[] = '<a href="admin.php?page=duplicator-about" title="' . __('Support the Plugin', 'duplicator') . '">' . __('About', 'duplicator') . '</a>';
            return $links;
        }
        return $links;
    }


	/** ========================================================
	 * GENERAL
     * =====================================================  */

	/**
	 * Used for installer files to redirect if accessed directly
     *
     * @access global
     * @return null
     */
    function duplicator_secure_check()
	{
		$baseURL = "http://" . strlen($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST'];
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: $baseURL");
		exit;
    }

}
?>