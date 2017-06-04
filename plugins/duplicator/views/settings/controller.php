<?php

DUP_Util::hasCapability('manage_options');

global $wpdb;

//COMMON HEADER DISPLAY
require_once(DUPLICATOR_PLUGIN_PATH . '/assets/js/javascript.php');
require_once(DUPLICATOR_PLUGIN_PATH . '/views/inc.header.php');
$current_tab = isset($_REQUEST['tab']) ? esc_html($_REQUEST['tab']) : 'general';
?>

<style>

</style>

<div class="wrap">
	
    <?php duplicator_header(__("Settings", 'duplicator')) ?>

	<h2 class="nav-tab-wrapper">
        <a href="?page=duplicator-settings&tab=general" class="nav-tab <?php echo ($current_tab == 'general') ? 'nav-tab-active' : '' ?>"> <?php _e('General', 'duplicator'); ?></a>
		<a href="?page=duplicator-settings&tab=package" class="nav-tab <?php echo ($current_tab == 'package') ? 'nav-tab-active' : '' ?>"> <?php _e('Packages', 'duplicator'); ?></a>
		<a href="?page=duplicator-settings&tab=schedule" class="nav-tab <?php echo ($current_tab == 'schedule') ? 'nav-tab-active' : '' ?>"> <?php _e('Schedules', 'duplicator'); ?></a>
        <a href="?page=duplicator-settings&tab=storage" class="nav-tab <?php echo ($current_tab == 'storage') ? 'nav-tab-active' : '' ?>"> <?php _e('Storage', 'duplicator'); ?></a>
    </h2>

    <?php
    switch ($current_tab) {
        case 'general': include('general.php');
            break;
		case 'package': include('packages.php');
            break;
		case 'schedule': include('schedule.php');
            break;
        case 'storage': include('storage.php');
            break;
    }
    ?>
</div>
