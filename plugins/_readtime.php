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
 * HOW THIS WORKS
 *
 * Reference https://medium.com/the-story/read-time-and-you-bc2048ab620c#.xmqnie7ga to see how
 * Medium calculates.
 *
 * There is many ways that we could tackle this but the less load will be to calculate
 * on save of a post.
 *
 * CALCULATING FORMULA
 *
 * - 275 WPM Roughly
 * - Each Image is ~12 seconds. I am not sure we need 12 seconds for any image but we will use
 * this as a starting point.
 * - if there are 10 images or more, each image after 9 will add 3 seconds.
 *
 * - Unknowns are snippets, pre tags (assuming) and if there is video. Is it read time or "Do time"?
 */

/**
 * [hh_calulate_post_readtime description]
 * @return [type] [description]
 */
function hh_calulate_and_update_post_readtime( $post_id, $post, $update ) {

  // If this is just a revision, don't send the email.
  if ( wp_is_post_revision( $post_id ) )
    return;

  // Only run for certain CPT's. We could just statically check but lets think scalability and maybe 
  // even feature plugin's for core one day!
  $calculate_for_posts = apply_filters('read_time_types', array('post') );
  if( !in_array($post->post_type, $calculate_for_posts) )
    return;

  // Average words per minute integer. As always, we should keep it filterable.
  // @uses filter read_time_average
  $average_word_per_minute = apply_filters('read_time_average', 300);

  // We can extend later and make an object that can be edited later for configuration.  
  $word_count = str_word_count(wp_strip_all_tags( $post->post_content ) );

  // This could be simpler but I wanted to spell it out.
  // 275 / 60 = Average Words Per Second.
  // Average Words Per Second / Word Count = Total Number of Seconds in read time.
  // Total Number of Seconds in read time / 60 = Final Estimated Read Time in Minutes
  $readtime = round( $word_count / ( $average_word_per_minute / 60 ) );

  // Lets grab the images out of the post content
  preg_match_all('/(img|src)\=(\"|\')[^\"\'\>]+/i', $post->post_content, $media);
  if( $image_count = count( $media[0] ) )
    $readtime = ($image_count*12-$image_count+$readtime);

  //if($image_count >= 10){
  //  $readtime_adjustment = (10 - $image_count); // subtract this from overall read time
  //  print_r($readtime_adjustment);
  // }

  // Update the posts read time
  update_post_meta( $post_id, '_read_time', $readtime);

  // REMOVE THIS LINE BEFORE PUSHING
  update_post_meta( $post_id, 'read_time', $readtime);
}
add_action( 'save_post', 'hh_calulate_and_update_post_readtime', 10, 3 );
