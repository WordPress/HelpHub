<?php
/**
 * This file runs when the plugin in uninstalled (deleted).
 * This will not run when the plugin is deactivated.
 * Ideally you will add all your clean-up scripts here
 * that will clean-up unused meta, options, etc. in the database.
 * @package WordPress
 * @author Carl Alberto
 * @since 1.0.0
 */
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
