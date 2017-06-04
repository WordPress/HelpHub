<?php
/**
 * Sets up the default filters and actions for most
 * of the plugin hooks.
 *
 * If you need to remove a default hook, this file will
 * give you the priority for which to use to remove the
 * hook.
 *
 * @todo Merge: Add to wp-includes/default-filters.php, wp-admin/includes/admin-filters.php
 *       and the Ajax stuff to wp-admin/admin-ajax.php
 *
 * @package Shiny_Updates
 * @since 4.X.0
 */

// Enqueue JavaScript and CSS.
add_action( 'admin_enqueue_scripts', 'su_enqueue_scripts' );

// Translation updates.
add_action( 'wp_ajax_update-translations', 'wp_ajax_update_translations', -1 );

// Core updates.
add_action( 'wp_ajax_update-core', 'wp_ajax_update_core', -1 );
add_action( 'core_upgrade_preamble', 'su_dismiss_core_updates' );
add_action( 'core_upgrade_preamble', 'su_update_table' );
add_action( 'admin_footer-update-core.php', 'wp_print_request_filesystem_credentials_modal' );
add_action( 'admin_footer-update-core.php', 'wp_print_admin_notice_templates' );

add_filter( 'removable_query_args', 'su_wp_removable_query_args' );
