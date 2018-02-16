<?php
/**
 * Plugin Name: Support HelpHub
 * Plugin URI: https://wordpress.org/support/
 * Description: Introduces HelpHub functionality to the WordPress.org support structure.
 * Version: 1.0
 * Author: WordPress.org
 * Author URI: https://wordpress.org/
 * Text Domain: wporg-forums
 * License: GPLv2
 * License URI: http://opensource.org/licenses/gpl-2.0.php
 */

namespace WordPressdotorg\HelpHub;

require_once( dirname( __FILE__ ) . '/inc/helphub-codex-languages/class-helphub-codex-languages.php' );
require_once( dirname( __FILE__ ) . '/inc/helphub-contributors/helphub-contributors.php' );
require_once( dirname( __FILE__ ) . '/inc/helphub-post-types/helphub-post-types.php' );
require_once( dirname( __FILE__ ) . '/inc/helphub-read-time/helphub-read-time.php' );
require_once( dirname( __FILE__ ) . '/inc/syntaxhighlighter/syntaxhighlighter.php' );
require_once( dirname( __FILE__ ) . '/inc/table-of-contents-lite/table-of-contents-lite.php' );
