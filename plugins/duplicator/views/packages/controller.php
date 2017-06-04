<?php

DUP_Util::hasCapability('export');

global $wpdb;

//COMMON HEADER DISPLAY
require_once(DUPLICATOR_PLUGIN_PATH . '/assets/js/javascript.php');
require_once(DUPLICATOR_PLUGIN_PATH . '/views/inc.header.php');

$current_view =  (isset($_REQUEST['action']) && $_REQUEST['action'] == 'detail') ? 'detail' : 'main';
?>

<script>
    jQuery(document).ready(function($) {
        /*	----------------------------------------
         *	METHOD: Triggers the download of an installer/package file
         *	@param name		Window name to open
         *	@param button	Button to change color */
        Duplicator.Pack.DownloadFile = function(event, button) {
            if (event.data != undefined) {
                window.open(event.data.name, '_self');
            } else {
                $(button).addClass('dup-button-selected');
                window.open(event, '_self');
            }
            return false;
        }

        /*	----------------------------------------
         * METHOD: Toggle links with sub-details */
        Duplicator.Pack.ToggleSystemDetails = function(event) {
            if ($(this).parents('div').children(event.data.selector).is(":hidden")) {
                $(this).children('span').addClass('ui-icon-triangle-1-s').removeClass('ui-icon-triangle-1-e');
                ;
                $(this).parents('div').children(event.data.selector).show(250);
            } else {
                $(this).children('span').addClass('ui-icon-triangle-1-e').removeClass('ui-icon-triangle-1-s');
                $(this).parents('div').children(event.data.selector).hide(250);
            }
        }
    });
</script>

<div class="wrap">
    <?php 
		    switch ($current_view) {
				case 'main': include('main/controller.php'); break;
				case 'detail' : include('details/controller.php'); break;
            break;	
    }
    ?>
</div>