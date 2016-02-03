<?php
/*
 * Plugin Name: HelpHub Read Time
 * Description: Adds estimated reading time to a post using a simple formula.
 * Version:     1.0.0
 * Author:      justingreerbbi
 * Author URI:  https://wordpress.org
 * License:     GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Adds the read time hook when saving a post
add_action( 'save_post', 'hh_calulate_and_update_post_read_time', 10, 3 );

/**
 * Calculates the read time of a post when created or updated.
 * @param  int 			$post_id Post id to calculate read time for.
 * @param  object 	$post    Post object
 * @param  boolean 	$update  Is this an update or new.
 * @return void
 */
function hh_calulate_and_update_post_read_time( $post_id, $post, $update ) {

	// No post revisions
	if ( wp_is_post_revision( $post_id ) )
		return;

	// Get post types that need to have read time applied
	$calculate_for_posts = apply_filters( 'read_time_types', array( 'post' ) );

	// If the post type is not found then return
	if ( ! in_array( $post->post_type, $calculate_for_posts ) )
		return;

	// Average words per minute integer.
	$average_word_per_minute = apply_filters( 'read_time_average', 275 );

	// Word count
	$word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );

	// Calculate basic read time
	$readtime = round( $word_count / ( $average_word_per_minute / 60 ) );

	// Grad and count all images
	preg_match_all( '/(img|src)\=(\"|\')[^\"\'\>]+/i', $post->post_content, $media );

	// Adjust read time given the number of images
	if ( $image_count = count( $media[0] ) ) {
		$readtime = ( $image_count * 12 - $image_count + $readtime );
	}
	
	// Update the post read time
	update_post_meta( $post_id, '_read_time', $readtime );
}

/**
 * Returns an output of read time for a given post. 
 *
 * get_the_read_time can be ran inside the loop or can be called using a post ID.
 * The function tries to default to the global post if there is not Post ID provided.
 *
 * @example 
 * <?php echo get_the_read_time(); ?>
 * 
 * @example
 * <?php echo get_the_read_time( $post->ID );
 * 
 * @param  int $post_id   ID of post to lookup
 * @return string         A formatted string containing a converted read time into minutes.
 */
function get_the_read_time( $post_id = null ) {
	if( is_null( $post_id ) ) {
		global $post;
		$post_id = $post->ID;
	} 

	$read_time = get_post_meta( $post_id, '_read_time', true );

	// Converts read time seconds into minutes
	$converted_readtime = 0 == $read_time ? '~1' : round( $read_time / 60 );

	return sprintf( esc_html__( 'Read Time: %s Min', 'helphub' ), $converted_readtime );
}