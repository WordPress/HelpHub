<?php
/*
 * Plugin Name: HelpHub Votes
 * Description: Adds a simple rating system to posts to gather user feedback
 * Version:     1.0.0
 * Author:      jayhoffmann
 * Author URI:  https://wordpress.org
 * License:     GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

include_once plugin_dir_path( __FILE__ ) . 'class-voting.php';
include_once plugin_dir_path( __FILE__ ) . 'class-comments.php';

function show_voting() {
	Helphub_Posts_Voting::show_voting();
}