<?php
/**
 * Functions
 */

add_action('wp_ajax_se_lookup', 'se_lookup');
add_action('wp_ajax_nopriv_se_lookup', 'se_lookup');
function se_lookup() {
    global $wpdb;
    $search = like_escape($_REQUEST['q']);
	$query = 'SELECT ID,post_title FROM ' . $wpdb->posts . '
        WHERE post_title LIKE \'%' . $search . '%\'
        AND post_status = \'publish\'
        ORDER BY post_title ASC';

    $return = array();
    foreach ($wpdb->get_results($query) as $row) {
        $post_title = $row->post_title;
        $id = $row->ID;
        
        $return[] = $post_title;

    	echo $post_title . "\n";
    }

    //print_r( $return );
    exit;
}