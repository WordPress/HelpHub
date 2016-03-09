<?php
/**
 * The template for displaying a feedback form and voting
 *
 * This replaces our themes default comments template and replaces it with a form
 * that includes a feedback form and voting module.
 *
 *
 * @package Helphhub
 */
?>

<div id="feedback" class="feedback-area">
	<?php show_voting(); ?>
	<?php 
	if( is_user_logged_in() ) {
		comment_form( array(
			'title_reply' => __('What was helpful? Or not?', 'Helphub'),
			'comment_notes_before' => '',
			'logged_in_as' => '',
			'label_submit' => __('Send Your Feedback', 'Helphub'),
			'comment_field' => '<textarea id="feedback" name="comment" cols="45" rows="8" aria-required="true"></textarea>',
			'comment_notes_after' => '<input id="vote-input" type="hidden" name="feedback-vote" value="" />'
			) ); ?>
		
		<p id="feedback-thanks"></p>
<?php } ?>
</div>