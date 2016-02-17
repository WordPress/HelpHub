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
add_action( 'save_post', 'hh_calculate_and_update_post_read_time', 10, 3 );

/**
 * Calculates the read time of a post when created or updated.
 * @param  int 			$post_id Post id to calculate read time for.
 * @param  object 	$post    Post object
 * @param  boolean 	$update  Is this an update or new.
 * @return void
 */
function hh_calculate_and_update_post_read_time( $post_id, $post, $update ) {

	// Only those allowed
	if ( ! current_user_can( 'edit_posts', $post_id ) ) {
		return;
	}

	// No post revisions
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	// Get post types that need to have read time applied
	$calculate_for_posts = apply_filters( 'read_time_types', array( 'post' ) );

	// If the post type is not found then return
	if ( ! in_array( $post->post_type, $calculate_for_posts ) ) {
		return;
	}

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
 * Returns the value of read time for a given post. 
 *
 * @access private
 * 
 * @param  int $post_id   ID of post to lookup
 * @return string         (string) g containing a converted read time into minutes.
 */
function hh_get_readtime( $post_id ) {
	if ( is_null( $post_id ) || ! is_numeric( $post_id ) ) {
		global $post;
		$post_id = $post->ID;
	}

	return get_post_meta( $post_id, '_read_time', true );
}

/**
 * Echo's the reading time for a given post
 *
 * @example
 * <?php hh_the_read_time(); ?>
 *
 * @example
 * <?php hh_the_read_time( $post->ID ); ?>
 * 
 * @param  int $post_id 	[description]
 * @return Void
 */
function hh_the_read_time( $post_id = null ) {
	echo hh_get_the_read_time( $post_id );
}

/**
 * Returns the reading time for a given post
 *
 * @example
 * <?php echo hh_get_the_read_time(); ?>
 *
 * @example
 * <?php echo hh_get_the_read_time( $post->ID ); ?>
 * 
 * @param  int $post_id 	[description]
 * @return string         [description]
 */
function hh_get_the_read_time( $post_id = null) {
	$hh_reading_time = hh_get_readtime( $post_id );

	// Filter the time before it is converted
	$read_time = apply_filters( 'hh_post_read_time', $hh_reading_time,  $post_id  );

	// Convert reading time to minutes
	$reading_time = (int) $read_time < 60 ? '1' : (string) round( $read_time / 60 );

	return sprintf( _n( 'Reading Time: %s Minute','Reading Time: %s Minutes', $reading_time, 'helphub' ), $reading_time );
}