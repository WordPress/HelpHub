<?php
/**
 * Plugin Name: HelpHub Read Time
 * Description: Adds estimated reading time to a post using a simple formula.
 * Version:     1.0.2
 * Author:      justingreerbbi
 * Author URI:  https://wordpress.org
 * License:     GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package  Helphub Readtime
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Adds the read time hook when saving a post.
add_action( 'save_post', 'hh_calculate_and_update_post_read_time', 10, 3 );

/**
 * Calculates the read time of a post when created or updated.
 *
 * @param  int     $post_id Post id to calculate read time for.
 * @param  object  $post    Post object.
 * @param  boolean $update  Is this an update or new.
 * @return void
 */
function hh_calculate_and_update_post_read_time( $post_id, $post, $update ) {

	// Only those allowed.
	if ( ! current_user_can( 'edit_posts', $post_id ) ) {
		return;
	}

	// No post revisions.
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	// Get post types that need to have read time applied.
	$calculate_for_posts = apply_filters( 'read_time_types', array( 'post' ) );

	// If the post type is not found then return.
	if ( ! in_array( $post->post_type, $calculate_for_posts ) ) {
		return;
	}

	// Average words per minute integer.
	$average_word_per_minute = apply_filters( 'read_time_average', 175 );

	// Store post content for raw usage.
	$post_content = $post->post_content;

	// Simple adjustment for pre tags.
	libxml_use_internal_errors(true);
	$data = new DOMDocument();
	$data->loadHTML( $post_content );
	$xpath = new DomXpath( $data );
	libxml_use_internal_errors(false);

	$pre_tags = array();
	$word_count_offset = 0;

	// Offset weight. Offset word count will be timed by this number.
	$offset_weight = apply_filters( 'read_time_offset_weight', 1 );

	foreach ( $xpath->query( '//pre' ) as $node ) {
	    $pre_tags[] = $node->nodeValue;
	    $word_count_offset = str_word_count( $node->nodeValue ) + ( $word_count_offset * $offset_weight );
	}

	// Word count.
	$word_count = str_word_count( wp_strip_all_tags( $post_content ) ) + $word_count_offset;

	// Calculate basic read time.
	$readtime = round( $word_count / ( $average_word_per_minute / 60 ) );

	// Grad and count all images.
	preg_match_all( '/(img|src)\=(\"|\')[^\"\'\>]+/i', $post->post_content, $media );

	// Adjust read time given the number of images.
	if ( $image_count = count( $media[0] ) ) {
		$readtime = ( $image_count * 12 - $image_count + $readtime );
	}

	// Update the post read time.
	update_post_meta( $post_id, '_read_time', $readtime );
}

/**
 * Returns the raw value of post meta "read time" for a given post.
 *
 * @access private
 *
 * @param  int $post_id   ID of post to retrieve read time for.
 * @return string|int     Raw value of read time
 */
function hh_get_readtime( $post_id ) {
	if ( is_null( $post_id ) || ! is_numeric( $post_id ) ) {
		global $post;
		$post_id = $post->ID;
	}

	$custom_read_time = get_post_meta( $post_id, '_custom_read_time', true );
	if ( '' != $custom_read_time  ) {
		$read_time = $custom_read_time;
	} else {
		$read_time = get_post_meta( $post_id, '_read_time', true );
	}

	return $read_time;
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
 * @param  int $post_id 	ID of post to retrieve read time for.
 */
function hh_the_read_time( $post_id = null ) {
	$post_id = intval( $post_id );
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
 * @param  int $post_id 	ID of post to retrieve read time for.
 * @return string 			Formated string provided read time text.
 */
function hh_get_the_read_time( $post_id = null ) {
	$hh_reading_time = hh_get_readtime( $post_id );

	// Filter the time before it is converted.
	$read_time = apply_filters( 'hh_post_read_time', $hh_reading_time,  $post_id );

	// Convert reading time to minutes.
	$reading_time = (int) $read_time < 60 ? '1' : (string) round( $read_time / 60 );

	return sprintf( _n( 'Reading Time: %s Minute','Reading Time: %s Minutes', $reading_time, 'helphub' ), $reading_time );
}

/**
 * Mass calculate the read time of all published posts
 * @return [type] [description]
 */
function hh_mass_calculate_readtime() {
	$qry_args = array(
		'post_status' => 'publish',
		'post_type' => 'post',
		'posts_per_page' => -1,
		);

	$query = new WP_Query( $qry_args );
    while ( $query->have_posts() ) {
		$query->the_post();
		hh_calculate_and_update_post_read_time( $query->post->ID, $query->post, false );
	}
}
register_activation_hook( __FILE__, 'hh_mass_calculate_readtime' );
