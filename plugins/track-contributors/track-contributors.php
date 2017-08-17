<?php
/*
Plugin Name: Track Contributors
Plugin URI:
Description: Manage and display content contributors
Version: 1.0
Author: Milana Cap
Author URI: http://developerka.org/
Text Domain: track-contributors
*/

/*
Copyright 2017 Milana Cap

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

defined( 'WPINC' ) or die;

class MC_Track_Contributors {

	public function __construct() {
		add_action( 'init', array( $this, 'init' ), 99 );
	}

	public function init() {
		load_plugin_textdomain( 'track-contributors', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'post_submitbox_misc_actions', array( $this, 'add_contributors' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'show_contributors' ) );

		// Show contributors for all post types with edit UI.
		// Easily hide it with post_type_supports()
		$post_types = get_post_types( array( 'show_ui' => true ) );

		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				add_post_type_support( $post_type, 'track-contributors' );

				add_action( "manage_edit-{$post_type}_columns", array( $this, 'add_column' ) );
				add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'show_column' ), 10, 2 );
			}
		}
	}
	/**
	 * Load scripts and styles.
	 * Attached to the 'admin_enqueue_scripts' action hook.
	 *
	 * Hopefully accessibility team will make select2 accessible which
	 * will enable select2 make it to the core.
	 */
	function enqueue_scripts() {
		wp_enqueue_style( 'select2', plugin_dir_url( __FILE__ ) . 'assets/css/select2.min.css', array(), '4.0.3' );
		wp_enqueue_script( 'select2', plugin_dir_url( __FILE__ ) . 'assets/js/select2.min.js', array( 'jquery' ), '4.0.3', true );
		wp_enqueue_script( 'track-contributors', plugin_dir_url( __FILE__ ) . 'assets/js/track-contributors.js', array( 'select2' ), '1.0', true );
	}
	/**
	 * Add select field to Publish metabox.
	 * Attached to 'post_submitbox_misc_actions' action hook.
	 */
	public function add_contributors() {
		$post = get_post();

		if ( ! $post ) {
			return;
		}

		if ( ! post_type_supports( $post->post_type, 'track-contributors' ) ) {
			return;
		}
		// Set nonce.
		wp_nonce_field( 'track-contributors-save', 'track_contributors_nonce' );
		// Get existing contributors.
		$contributors = get_post_meta( $post->ID, 'track_contributors' ); ?>

		<div class="misc-pub-section track-contributors">
			<label><?php _e( 'Contributors', 'track-contributors' ); ?>
				<select id="track-contributors" class="widefat" multiple name="mc_track_contributors[]">
					<?php foreach ( $contributors[0] as $contributor ) : ?>
						<option value="<?php echo esc_attr( $contributor ); ?>" selected="selected"><?php echo esc_html( $contributor ); ?></option>
					<?php endforeach; ?>
				</select><!-- #track-contributors -->
			</label>
			<p class="description"><?php _e( 'Type wp.org username for contributor', 'track-contributors' ); ?></p>
		</div><!-- misc-pub-section track-contributors --><?php
	}
	/**
	 * Save contributors as post meta on save post.
	 * Attached to 'save_post' action hook.
	 *
	 * @param  int $post_id     Post id
	 * @param  WP_Post $post    Post object
	 */
	public function save_post( $post_id, $post ) {
		// Verify nonce.
		if ( ! isset( $_POST['track_contributors_nonce'] ) || ! wp_verify_nonce( $_POST['track_contributors_nonce'], 'track-contributors-save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['mc_track_contributors'] ) || empty( $_POST['mc_track_contributors'] ) ) {
			return;
		}

		$contributors = $_POST['mc_track_contributors'];
		update_post_meta( $post_id, 'track_contributors', $contributors );
	}
	/**
	 * Show contributors after post content.
	 * Attached to 'the_content' filter hook.
	 *
	 * @param  string $content  Post content
	 * @return string           Returns post content with appended contributors list
	 */
	public function show_contributors( $content ) {
		$return = $content;
		$meta = get_post_meta( get_the_ID(), 'track_contributors' );
		$contributors = $meta[0];

		if ( is_array( $contributors ) ) {
			$contributors_list = '<div class="contirbutors-list-wrap">';
			$contributors_list .= '<h5>' . __( 'Contributors', 'track-contributors' ) . '</h5>';
			$contributors_list .= '<ul>';

			foreach ( $contributors as $contributor ) {
				$contributor_item = '<li>';
				$contributor_item .= '<a href="https://profiles.wordpress.org/' . $contributor . '">@' . $contributor . '</a>';
				$contributor_item .= '</li>';
				$contributors_list .= apply_filters( 'contributor_list_item', $contributor_item );
			}

			$contributors_list .= '</ul>';
			$contributors_list .= '</div>';

			$return .= apply_filters( 'contributors_list', $contributors_list );
		}

		return $return;
	}
	/**
	 * Add Contributors column on edit.php screen.
	 * Attached to "manage_edit-{$post_type}_columns" action hook.
	 *
	 * @param array $columns  Array of columns
	 */
	public function add_column( $columns ) {
		$columns['track_contributors'] = __( 'Contributors', 'track-contributors' );

		return $columns;
	}
	/**
	 * Show Contributors column on edit.php screen.
	 * Attached to "manage_{$post_type}_posts_custom_column" action hook.
	 *
	 * @param  string $column  Column ID
	 * @param  int $post_id    Post id
	 */
	public function show_column( $column, $post_id ) {
		if ( 'track_contributors' !== $column ) {
			return;
		}

		$contributors = get_post_meta( $post_id, 'track_contributors', true );

		if ( empty( $contributors ) ) {
			return;
		}

		if ( is_array( $contributors ) ) :
			foreach ( $contributors as $contributor ) {
				echo esc_html( $contributor ) . ', ';
			}
		else :
			echo esc_html( $contributors );
		endif;
	}
}
$mc_track_contributors = new MC_Track_Contributors();