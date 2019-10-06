<?php

/**
 * User Profile
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

do_action( 'bbp_template_before_user_profile' ); ?>

<div id="bbp-user-profile" class="bbp-user-profile">
	<h2 class="entry-title">@<?php bbp_displayed_user_field( 'user_nicename' ); ?></h2>
	<div class="bbp-user-section">
		<h3><?php esc_html_e( 'Profile', 'bbpress' ); ?></h3>
		<p class="bbp-user-forum-role"><?php  printf( esc_html__( 'Registered: %s', 'bbpress' ), bbp_get_time_since( bbp_get_displayed_user_field( 'user_registered' ) ) ); ?></p>

		<?php if ( bbp_get_displayed_user_field( 'description' ) ) : ?>

			<p class="bbp-user-description"><?php echo bbp_rel_nofollow( bbp_get_displayed_user_field( 'description' ) ); ?></p>

		<?php endif; ?>

		<?php if ( bbp_get_displayed_user_field( 'user_url' ) ) : ?>

			<p class="bbp-user-website"><?php  printf( esc_html__( 'Website: %s', 'bbpress' ), bbp_rel_nofollow( bbp_make_clickable( bbp_get_displayed_user_field( 'user_url' ) ) ) ); ?></p>

		<?php endif; ?>

		<hr>
		<h3><?php esc_html_e( 'Forums', 'bbpress' ); ?></h3>

		<?php if ( bbp_get_user_last_posted() ) : ?>

			<p class="bbp-user-topic-count"><?php printf( esc_html__( 'Last Activity: %s',  'bbpress' ), bbp_get_time_since( bbp_get_user_last_posted() ) ); ?></p>

		<?php endif; ?>

		<p class="bbp-user-topic-count"><?php printf( esc_html__( 'Topics Started: %s',  'bbpress' ), bbp_get_user_topic_count() ); ?></p>
		<p class="bbp-user-reply-count"><?php printf( esc_html__( 'Replies Created: %s', 'bbpress' ), bbp_get_user_reply_count() ); ?></p>
		<p class="bbp-user-forum-role"><?php  printf( esc_html__( 'Forum Role: %s',      'bbpress' ), bbp_get_user_display_role() ); ?></p>
	</div>
</div><!-- #bbp-author-topics-started -->

<?php do_action( 'bbp_template_after_user_profile' );
