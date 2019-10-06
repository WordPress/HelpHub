<?php

/**
 * Merge Topic
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<div id="bbpress-forums" class="bbpress-wrapper">

	<?php bbp_breadcrumb(); ?>

	<?php if ( is_user_logged_in() && current_user_can( 'edit_topic', bbp_get_topic_id() ) ) : ?>

		<div id="merge-topic-<?php bbp_topic_id(); ?>" class="bbp-topic-merge">

			<form id="merge_topic" name="merge_topic" method="post">

				<fieldset class="bbp-form">

					<legend><?php printf( esc_html__( 'Merge topic "%s"', 'bbpress' ), bbp_get_topic_title() ); ?></legend>

					<div>

						<div class="bbp-template-notice info">
							<ul>
								<li><?php esc_html_e( 'Select the topic to merge this one into. The destination topic will remain the lead topic, and this one will change into a reply.', 'bbpress' ); ?></li>
								<li><?php esc_html_e( 'To keep this topic as the lead, go to the other topic and use the merge tool from there instead.',                                  'bbpress' ); ?></li>
							</ul>
						</div>

						<div class="bbp-template-notice">
							<ul>
								<li><?php esc_html_e( 'Replies to both topics are merged chronologically, ordered by the time and date they were published. Topics may be updated to a 1 second difference to maintain chronological order based on the merge direction.', 'bbpress' ); ?></li>
							</ul>
						</div>

						<fieldset class="bbp-form">
							<legend><?php esc_html_e( 'Destination', 'bbpress' ); ?></legend>
							<div>
								<?php if ( bbp_has_topics( array( 'show_stickies' => false, 'post_parent' => bbp_get_topic_forum_id( bbp_get_topic_id() ), 'post__not_in' => array( bbp_get_topic_id() ) ) ) ) : ?>

									<label for="bbp_destination_topic"><?php esc_html_e( 'Merge with this topic:', 'bbpress' ); ?></label>

									<?php
										bbp_dropdown( array(
											'post_type'   => bbp_get_topic_post_type(),
											'post_parent' => bbp_get_topic_forum_id( bbp_get_topic_id() ),
											'post_status' => bbp_get_public_topic_statuses(),
											'selected'    => -1,
											'exclude'     => bbp_get_topic_id(),
											'select_id'   => 'bbp_destination_topic'
										) );
									?>

								<?php else : ?>

									<label><?php esc_html_e( 'There are no other topics in this forum to merge with.', 'bbpress' ); ?></label>

								<?php endif; ?>

							</div>
						</fieldset>

						<fieldset class="bbp-form">
							<legend><?php esc_html_e( 'Topic Extras', 'bbpress' ); ?></legend>

							<div>

								<?php if ( bbp_is_subscriptions_active() ) : ?>

									<input name="bbp_topic_subscribers" id="bbp_topic_subscribers" type="checkbox" value="1" checked="checked" />
									<label for="bbp_topic_subscribers"><?php esc_html_e( 'Merge topic subscribers', 'bbpress' ); ?></label><br />

								<?php endif; ?>

								<input name="bbp_topic_favoriters" id="bbp_topic_favoriters" type="checkbox" value="1" checked="checked" />
								<label for="bbp_topic_favoriters"><?php esc_html_e( 'Merge topic favoriters', 'bbpress' ); ?></label><br />

								<?php if ( bbp_allow_topic_tags() ) : ?>

									<input name="bbp_topic_tags" id="bbp_topic_tags" type="checkbox" value="1" checked="checked" />
									<label for="bbp_topic_tags"><?php esc_html_e( 'Merge topic tags', 'bbpress' ); ?></label><br />

								<?php endif; ?>

							</div>
						</fieldset>

						<div class="bbp-template-notice error">
							<ul>
								<li><?php esc_html_e( 'This process cannot be undone.', 'bbpress' ); ?></li>
							</ul>
						</div>

						<div class="bbp-submit-wrapper">
							<button type="submit" id="bbp_merge_topic_submit" name="bbp_merge_topic_submit" class="button submit"><?php esc_html_e( 'Submit', 'bbpress' ); ?></button>
						</div>
					</div>

					<?php bbp_merge_topic_form_fields(); ?>

				</fieldset>
			</form>
		</div>

	<?php else : ?>

		<div id="no-topic-<?php bbp_topic_id(); ?>" class="bbp-no-topic">
			<div class="entry-content"><?php is_user_logged_in()
				? esc_html_e( 'You do not have permission to edit this topic.', 'bbpress' )
				: esc_html_e( 'You cannot edit this topic.',                    'bbpress' );
			?></div>
		</div>

	<?php endif; ?>

</div>
