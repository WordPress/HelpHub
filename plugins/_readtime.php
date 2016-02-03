<?php
/*
 * Plugin Name: Reading Time
 * Description: Adds estimated reading time to a post custom fields using a simple formula.
 * Version:     1.0.0
 * Author:      justingreerbbi
 * Author URI:  https://wordpress.org
 * License:     GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Put this here for future
//load_plugin_textdomain('your-unique-name', false, basename( dirname( __FILE__ ) ) . '/languages' );

/**
 * [hh_calulate_post_readtime description]
 * @return [type] [description]
 */
function hh_calulate_and_update_post_readtime( $post_id, $post, $update ) {

  // No post revisions
  if ( wp_is_post_revision( $post_id ) )
    return;

  // Only run for certain post types. We could just statically check but lets think scalability here
  // and try to get into core one day!
  $calculate_for_posts = apply_filters('read_time_types', array('post') );
  if( !in_array($post->post_type, $calculate_for_posts) )
    return;

  // Average words per minute integer. As always, we should keep it filterable.
  $average_word_per_minute = apply_filters('read_time_average', 275);

  // Simple method of grabbing the word count  
  $word_count = str_word_count(wp_strip_all_tags( $post->post_content ) );

  // Read time is calculated use the formula below/
  $readtime = round( $word_count / ( $average_word_per_minute / 60 ) );

  // Lets grab the images out of the post content
  preg_match_all('/(img|src)\=(\"|\')[^\"\'\>]+/i', $post->post_content, $media);
  if( $image_count = count( $media[0] ) )
    $readtime = ($image_count*12-$image_count+$readtime);

  /**
   * For articles that have more than say 10 images, the formula will overbid
   * the read time since images hold a high weight. We counter the weight above but the code below 
   * may be able to add more or a accurate read time with an article count higher then say 10.
   *
   * if($image_count >= 10){
   *   $readtime_adjustment = (10 - $image_count); // subtract this from overall read time
   *   print_r($readtime_adjustment);
   * }
   */
  
  // Update the posts read time
  update_post_meta( $post_id, '_read_time', $readtime);
}
add_action( 'save_post', 'hh_calulate_and_update_post_readtime', 10, 3 );
