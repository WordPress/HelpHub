<?php
/**
 * Basic Class for Helphub Voting Feature
 *
 * Sets up proper hooks, actions and outputted HTML for voting feature
 *
 *
 * @package helphub-voting
 */

class Helphub_Posts_Voting {
        /**
         * Stores a list of User IDs for all users that have submitted a vote
         *
         * @var array
         * @access public
         */
        public static $meta_upvotes = 'helphub_up_votes';

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
                // Save a non-AJAX submitted vote.
                add_action( 'template_redirect',  array( __CLASS__, 'vote_submission' ) );
                // Save AJAX submitted vote.
                add_action( 'wp_ajax_helphub_vote',  array( __CLASS__, 'ajax_vote_submission' ) );
                // Enqueue scripts and styles.
                add_action( 'wp_enqueue_scripts', array( __CLASS__, 'scripts_and_styles' ) );
        }

        /**
         * Enqueues scripts and styles.
         *
         * @access public
         */
        public static function scripts_and_styles() {
                // Only enqueue if a user can vote, and we are on a single post page
                if ( self::user_can_vote() && is_singular() ) {
                        wp_register_script( 'helphub-post-voting', plugin_dir_url( __FILE__ ) . '/assets/js/helphub-voting.js', array(), '0.1.0', true );
                        wp_localize_script( 'helphub-post-voting', 'ajaxurl', admin_url( 'admin-ajax.php' ) );
                        wp_enqueue_script( 'helphub-post-voting' );
                }
        }

        /**
         * Handles vote submission.
         *
         * @access public
         *
         * @return bool True if vote resulted in success or a change.
         */
        public static function vote_submission( $redirect = true ) {
               
                $success = false;
                if( isset($_REQUEST['post'] ) && $_REQUEST['post']
                        && isset( $_REQUEST['vote'] ) && $_REQUEST['vote']
                        && isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'helphub-vote-' . $_REQUEST['post'] )
                        && self::user_can_vote( get_current_user_id(), $_REQUEST['post'] )
                ) {
                        self::vote_up( (int) $_REQUEST['post'], get_current_user_id() );
                        // // Redirect user back to comment unless this was an AJAX request.
                        // if ( ! isset( $_REQUEST['is_ajax'] ) ) {
                        //         wp_redirect( get_comment_link( $_REQUEST['comment'] ) );
                        //         exit();
                        // }
                }
                return $success;
        }

        /**
         * Handles AJAX vote submission.
         *
         * @access public
         *
         * @return int|string Returns 0 on error or no change; else the markup to be used to replace .user-note-voting
         */
        public static function ajax_vote_submission() {
                check_ajax_referer( 'user-note-vote-' . $_POST['comment'], $_POST['_wpnonce'] );
                $_REQUEST['is_ajax'] = true;
                // If voting succeeded and resulted in a change, send back full replacement
                // markup.
                if ( self::vote_submission( false ) ) {
                        self::show_voting( (int) $_POST['comment'] );
                        die();
                }
                die( 0 );
        }

        /**
         * Returns the list of upvotes for a post.
         *
         * @access public
         *
         * @param  int $post_id The post ID.
         * @return array
         */
        public static function get_post_upvotes( $post_id ) {
                return self::get_post_votes( $post_id, self::$meta_upvotes );
        }

        /**
         * Returns the list of vote for a specific vote type for a post.
         *
         * @access protected
         *
         * @param  int    $post_id The post ID.
         * @param  string $field
         * @return array
         */
        protected static function get_post_votes( $post_id, $field ) {
                $votes = get_post_meta( $post_id, $field, true );
                if ( ! $votes ) {
                        $votes = array();
                }
                return $votes;
        }

        /**
         * Determines if the user can vote on this article.
         *
         * By default, the only requirements are:
         * - the user is logged in.
         * filter 'helphub_user_can_vote' to configure custom permissions for the user
         *
         * @access public
         *
         * @param  int  $user_id    Optional. The user ID. If not defined, assumes current user.
         * @param  int  $post_id Optional. The post ID. Not defined, but provided to filter.
         * @return bool True if the user can vote.
         */
        public static function user_can_vote( $user_id = '', $post_id = '' ) {
                // If no user specified, assume current user.
                if ( ! $user_id ) {
                        $user_id = get_current_user_id();
                }
                // Must be a user to vote.
                if ( ! $user_id ) {
                        return false;
                }
                $can = true;
                // post, if provided, must be approved.
                return apply_filters( 'helphub_user_can_vote', $can, $user_id, $post_id );
        }

        /**
         * Has user upvoted the article?
         *
         * @access public
         *
         * @param  int    $post_id The post ID
         * @param  int    $user_id    Optional. The user ID. If not defined, assumes current user.
         * @return bool   True if the user has upvoted the post.
         */
        public static function has_user_upvoted_post( $post_id, $user_id = '' ) {
                // If no user specified, assume current user.
                if ( ! $user_id ) {
                        $user_id = get_current_user_id();
                }
                // Must be logged in to have voted.
                if ( ! $user_id ) {
                        return false;
                }
                $upvotes = self::get_post_upvotes( $post_id );
                return in_array( $user_id, $upvotes );
        }

        /**
         * Outputs the voting markup for an article.
         * Displays up vote alongside a thumbs up.
         *
         * @access public
         *
         * @param int $comment_id The comment ID, or empty to use current comment.
         */
        public static function show_voting( $post_id = '') {
                if ( ! $post_id ) {
                        global $post;
                        $post_id = $post->ID;
                }
                $can_vote     = self::user_can_vote( get_current_user_id(), $post_id );
                $nonce        = wp_create_nonce( 'helphub-vote-' . $post_id );
                echo '<div class="helphub-voting" data-nonce="' . esc_attr( $nonce ) . '">';
                // Up vote link
                $user_upvoted = self::has_user_upvoted_post( $post_id );
                if ( $can_vote ) {
                        $title = $user_upvoted ?
                                __( 'You have voted to indicate this article was helpful', 'helphub' ) :
                                __( 'Was this article useful?', 'helphub' );
                        $tag = 'a';
                } else {
                        $title = ! is_user_logged_in() ?
                                __( 'You must log in to vote if this article was helpful', 'helphub' ) :
                                '';
                        $tag = 'span';
                }
                echo "<{$tag} "
                        . 'class="user-note-voting-up' . ( $user_upvoted ? ' user-voted' : '' )
                        . '" title="' . esc_attr( $title )
                        . '" data-id="' . esc_attr( $post_id )
                        . '" data-vote="up';
                if ( ! $user_upvoted ) {
                        echo '" href="'
                                . esc_url( add_query_arg( array( '_wpnonce' => $nonce , 'post' => $post_id, 'vote' => 'up' ), $comment_link ) );
                }
                echo '">';
                echo '<span class="dashicons dashicons-thumbs-up"></span>';
                
                // Total count
                echo sprintf( __( '%s found this useful', 'wporg' ), self::count_votes( $post_id ) );
                echo "</{$tag}>";
        }

        /**
         * Returns total number of votes
         *
         * @access public
         *
         * @param  int    $post_id The post ID
         * @return int    The requested count.
         */
        public static function count_votes( $post_id ) {
                $up = count( self::get_post_upvotes( $post_id ) );

                return $up;
        }

        /**
         * Records an up vote.
         *
         * @access public
         *
         * @param  int  $post_id    The post ID
         * @param  int  $user_id    Optional. The user ID. Default is current user.
         * @return bool Whether the up vote succeed (a new vote or a change in vote).
         */
        public static function vote_up( $post_id, $user_id = '' ) {
                return self::vote_handler( $post_id, $user_id, 'up' );
        }

        /**
         * Handles abstraction of the voting itself.
         *
         * @access protected
         *
         * @param  int    $post_id    The post ID
         * @param  int    $user_id    Optional. The user ID. Default is current user.
         * @param  string $type       Optional. Right now, up is the only supported type
         * @return bool   Whether the vote succeed (a new vote or a change in vote).
         */
        protected static function vote_handler( $post_id, $user_id = '', $type = 'up' ) {
                if ( ! $user_id ) {
                        $user_id = get_current_user_id();
                }
                // See if the user can vote on this comment.
                $votable = self::user_can_vote( $user_id, $comment_id );
                if ( ! $votable ) {
                        return false;
                }

                // Add to total vote count
                $add_to      = self::$meta_upvotes;

                // Get list of people who cast the same vote.
                $add_to_list = get_post_meta( $post_id, $add_to, true );

                // If a user has voted before, remove them from the list of votes for this post
                if ( in_array( $user_id, (array) $add_to_list ) ) {
                       unset( $add_to_list[ array_search( $user_id, $add_to_list ) ] );
                } else {
                // Otherwise, add the vote to our list (or create a new array if necessary)   
                        $add_to_list[] = $user_id;
                }

                update_post_meta( $post_id, $add_to, $add_to_list );
                
                return true;
        }
} // DevHub_User_Contributed_Notes_Voting
Helphub_Posts_Voting::init();
