<?php
/*
Plugin Name: Simple Maintenance Mode
Description: An easy way to create a maintenance mode page for your Wordpress site.
Version: 1.04
Author: Jeff Bullins
Author URI: http://www.thinklandingpages.com
*/

class SimpleMaintenanceMode {

    private $plugin_path;
    private $plugin_url;
    private $l10n;
    private $simpleMaintenanceMode;

    function __construct() 
    {	
        $this->plugin_path = plugin_dir_path( __FILE__ );
        $this->plugin_url = plugin_dir_url( __FILE__ );
        $this->l10n = 'wp-settings-framework';
        add_action( 'admin_menu', array(&$this, 'admin_menu'), 99 );
        
        // Include and create a new WordPressSettingsFramework
        require_once( $this->plugin_path .'wp-settings-framework.php' );
        $settings_file = $this->plugin_path .'settings/settings-general.php';
        
        $this->simpleMaintenanceMode = new WordPressSettingsFramework( $settings_file, '_simple_mm_', $this->get_simple_mm_settings() );
        // Add an optional settings validation filter (recommended)
        add_filter( $this->simpleMaintenanceMode->get_option_group() .'_settings_validate', array(&$this, 'validate_settings') );
        add_action('template_redirect', array(&$this, 'render_coming_soon'), 1);
    }
    
    function admin_menu()
    {
        $page_hook = add_menu_page( __( 'Maintenance Mode', $this->l10n ), __( 'Maintenance Mode', $this->l10n ), 'update_core', 'Maintenance Mode', array(&$this, 'settings_page') );
        add_submenu_page( 'Maintenance Mode', __( 'Settings', $this->l10n ), __( 'Settings', $this->l10n ), 'update_core', 'Maintenance Mode', array(&$this, 'settings_page') );
    }
    
    function settings_page()
	{
	    // Your settings page
	    ?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"></div>
			<h2>Maintenance Mode</h2>
			<p><a href="http://www.thinklandingpages.com/landingpage/wordpress-maintenance-mode-plugin-2/">Upgrade to the Pro Version</a></p>
			
			<?php 
			// Output your settings form
			$this->simpleMaintenanceMode->settings(); 
			?>
			<a href="/?preview_maintenance=true" target="_blank">Preview Page</a> You must save first to see your current changes.
		</div>
		<?php
		
		// Get settings
		//$settings = simpleMaintenanceMode_get_settings( $this->plugin_path .'settings/settings-general.php' );
		//echo '<pre>'.print_r($settings,true).'</pre>';
		
		// Get individual setting
		//$setting = simpleMaintenanceMode_get_setting( simpleMaintenanceMode_get_option_group( $this->plugin_path .'settings/settings-general.php' ), 'general', 'text' );
		//var_dump($setting);
	}
	
	function validate_settings( $input )
	{
	    // Do your settings validation here
	    // Same as $sanitize_callback from http://codex.wordpress.org/Function_Reference/register_setting
    	return $input;
	}
	
	function render_coming_soon() {
			$my_option_string = $this->simpleMaintenanceMode->get_option_group().'_settings';
			$my_options = get_option($my_option_string);
			
			$the_option = $this->simpleMaintenanceMode->get_option_group() .'_general_is_maintenance_mode';
			$is_display = false;
			if(isset($_GET["preview_maintenance"]) &&  $_GET["preview_maintenance"] == true){
				$is_display = true;
			}
		            elseif(!is_admin()){
		                if(!is_feed()){ 
		                    if ( !is_user_logged_in() && (isset($my_options[$the_option]) && $my_options[$the_option] == 1 )) {
		                        $is_display = true;
		                    }
		                }
		            }
	            if($is_display){
	            	$file = plugin_dir_path(__FILE__).'template/simple_mm_template1.php';
                        include($file);
                        die();
	            }
        }
        
        function get_simple_mm_settings(){
        	$wpsf_settings[] = array(
		    'section_id' => 'general',
		    'section_title' => 'Maintenance Mode Settings',
		    //'section_description' => 'Some intro description about this section.',
		    'section_order' => 5,
		    'fields' => array(
		       array(
		            'id' => 'is_maintenance_mode',
		            'title' => 'Maintenance Mode',
		            'desc' => 'Check here to turn maintenance mode on.  All non-logged in users will the maintenance page this page.',
		            'type' => 'checkbox',
		            'std' => 0
		        ),
		       array(
		            'id' => 'logo',
		            'title' => 'Logo',
		            'desc' => 'Upload your logo here.',
		            'type' => 'file',
		            'std' => ''
		        ),
		        /*
		        array(
		            'id' => 'background_color',
		            'title' => 'Background Color',
		            //'desc' => 'This is a description.',
		            'type' => 'color',
		            'std' => '#ffffff'
		        ),
		        
		        array(
		            'id' => 'background_image',
		            'title' => 'Background Image',
		            'desc' => 'Upload a background image.  It will override any background color set.',
		            'type' => 'file',
		            'std' => ''
		        ),
		        */
		        array(
		            'id' => 'message',
		            'title' => 'Message',
		            'desc' => 'Put your maintenance notice here.',
		            'type' => 'text',
		            'std' => 'Our site is temporarily down for maintenance.  We will be back soon.'
		        ),
		        
		        array(
		            'id' => 'message_font_color',
		            'title' => 'Message Color',
		            'desc' => '<a href="http://www.thinklandingpages.com/landingpage/wordpress-maintenance-mode-plugin-2/">Upgrade to the Pro Version</a>',
		            'type' => 'color',
		            'std' => '#000000'
		        ),
		        
		        )
		        
        
    );
    return $wpsf_settings;
        }

}
new SimpleMaintenanceMode();

?>