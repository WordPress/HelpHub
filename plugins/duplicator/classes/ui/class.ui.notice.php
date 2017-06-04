<?php
/**
 * Used to display notices in the WordPress Admin area
 * This class takes advatage of the 'admin_notice' action.
 *
 * Standard: PSR-2
 * @link http://www.php-fig.org/psr/psr-2
 *
 * @package Duplicator
 * @subpackage classes/ui
 * @copyright (c) 2017, Snapcreek LLC
 * @since 1.1.0
 *
 */

// Exit if accessed directly
if (!defined('DUPLICATOR_VERSION')) {
    exit;
}

class DUP_UI_Notice
{


    /**
     * Shows a display message in the wp-admin if any researved files are found
     * 
     * @return string   Html formated text notice warnings
     */
    public static function showReservedFilesNotice()
    {
        //Show only on Duplicator pages and Dashboard when plugin is active
        $dup_active = is_plugin_active('duplicator/duplicator.php');
        $dup_perm   = current_user_can('manage_options');
        if (!$dup_active || !$dup_perm)
			return;
		
		$screen = get_current_screen();
        if (!isset($screen))
			return;

        if (DUP_Server::hasInstallerFiles()) {

            $screen         = get_current_screen();
            $on_active_tab  = isset($_GET['tab']) && $_GET['tab'] == 'cleanup' ? true : false;
			$dup_nonce		= wp_create_nonce('duplicator_cleanup_page');
			$msg1			= __('This site has been successfully migrated!', 'duplicator');
			$msg2			= __('Migration Almost Complete!', 'duplicator');
			$msg3			= __('Please complete these final steps:', 'duplicator');
			$msg4			= __('This message will be removed after all installer files are removed.  Installer files must be removed to maintain a secure site.<br/>'
							. 'Click the link above or button below to remove all installer files and complete the migration.', 'duplicator');

			echo '<div class="updated notice" id="dup-global-error-reserved-files"><p>';
		
			//On Cleanup Page
			if ($screen->id == 'duplicator_page_duplicator-tools' && $on_active_tab) {
				echo "<b class='pass-msg'><i class='fa fa-check-circle'></i> {$msg1}</b> <br/>";
				echo "{$msg3}";
				echo '<p class="pass-lnks">';
				@printf("1. <a href='https://wordpress.org/support/plugin/duplicator/reviews/?filter=5' target='wporg'>%s</a> <br/> ", __('Optionally, Review Duplicator at WordPress.org...', 'duplicator'));
				@printf("2. <a href='javascript:void(0)' onclick='jQuery(\"#dup-remove-installer-files-btn\").click()'>%s</a><br/>", __('Remove Installation Files Now!', 'duplicator'));
				echo '</p>';
				echo "<div class='pass-msg'>{$msg4}</div>";

			//All other Pages
			} else {
				echo "<b>{$msg2}</b> <br/>";
				echo '<p class="pass-lnks">';
				_e('Reserved Duplicator installation still exist in the root directory.  Please remove these installation files to complete setup and avoid security issues. <br/>', 'duplicator');
				_e('Go to: Duplicator > Tools > Cleanup > and click the "Remove Installation Files" button.', 'duplicator');
				@printf("<br/><a href='admin.php?page=duplicator-tools&tab=cleanup&_wpnonce={$dup_nonce}'>%s</a> <br/>", __('Take me there now!', 'duplicator'));
				echo '</p>';
			}

			echo "</p></div>";
        } 
    }
}