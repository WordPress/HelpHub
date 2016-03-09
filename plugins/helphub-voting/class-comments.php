<?php
/**
 * Basic Class for Helphub Comments
 *
 * Transforms comments form into a proper feedback form,
 * and collects the minimuim amount of data necessary
 *
 *
 * @package helphub-voting
 */

class Helphub_Feedback {

	/**
	 * Initializer
	 *
	 * @access public
	 */
	public static function init() {
	        add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Initialization
	 *
	 * @access public
	 */
	public static function do_init() {
	        // Pass in our own comment template
	        add_action( 'comments_template',  array( __CLASS__, 'include_feedback_template' ) );
	        // Add users vote to the metadata associated with a comment
	        add_action( 'comment_post',  array( __CLASS__, 'add_vote_to_feedback' ) );
	}

	/**
	 * Adds feedback template
	 * 
	 * @access public
	 * 
	 * @param string $comments_template The location of the default template
	 * @return string Returns location of custom template
	 */
	public function include_feedback_template( $comments_template ) {
		global $post;
		if( !is_singular() ) {
			retrun;
		}

		return plugin_dir_path( __FILE__ ) . 'templates/comments-feedback.php';
	}

	/**
	 * Adds vote to feedback on save
	 * 
	 * Checks for AJAX request, and then adds the proper feedback to comment_meta
	 * 
	 * @access public
	 * 
	 * @param int $comment_id ID of comment being saved
	 */
	public function add_vote_to_feedback( $comment_id ) {
		if( isset( $_POST['feedback-vote'] ) && !empty( $_POST['feedback-vote'] ) ) {
			add_comment_meta( $comment_id, 'vote', $_POST['feedback-vote']);
		}
	}


}

Helphub_Feedback::init();