<?php
/*
Plugin Name: Revision Notes
Plugin URI: http://wordpress.org/plugins/revision-notes/
Description: Add a note explaining the changes you're about to save. It's like commit messages, except for your WordPress content.
Version: 1.1
Author: Helen Hou-Sandí
Author URI: http://helenhousandi.com/
Text Domain: revision-notes
*/

/*
Copyright 2015 Helen Hou-Sandí

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

// Cheapo namespace; this isn't a real object or very testable (right now).
class HHS_Revision_Notes {

	public function __construct() {
		add_action( 'init', array( $this, 'init' ), 99 );
	}

	public function init() {
		load_plugin_textdomain( 'revision-notes', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		add_action( 'post_submitbox_misc_actions', array( $this, 'edit_field' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );

		add_filter( 'wp_prepare_revision_for_js', array( $this, 'wp_prepare_revision_for_js' ), 10, 2 );
		add_filter( 'wp_post_revision_title_expanded', array( $this, 'wp_post_revision_title_expanded' ), 10, 2 );

		// Use post_type_supports() to make showing/hiding of the field easy for devs.
		// By default we'll show it for any post type that has an edit UI.
		$post_types = get_post_types( array( 'show_ui' => true ) );

		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				add_post_type_support( $post_type, 'revision-notes' );

				add_action( "manage_edit-{$post_type}_columns", array( $this, 'add_column' ) );
				add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'show_column' ), 10, 2 );
			}
		}
	}

	public function edit_field() {
		$post = get_post();
		if ( ! $post ) {
			return;
		}

		if ( ! post_type_supports( $post->post_type, 'revision-notes' ) ) {
			return;
		}

		wp_nonce_field( 'hhs-revision-notes-save', 'hhs_revision_notes_nonce' );
?>
<div class="misc-pub-section revision-note">
<label><?php _e( 'Revision note (optional)', 'revision-notes' ); ?>
<input name="hhs_revision_note" type="text" class="widefat" maxlength="100" />
</label>
<p class="description"><?php _e( 'Enter a brief note about this change', 'revision-notes' ); ?></p>
</div>
<?php
	}

	public function save_post( $post_id, $post ) {
		// verify nonce
		if ( ! isset( $_POST['hhs_revision_notes_nonce'] ) ||
			! wp_verify_nonce( $_POST['hhs_revision_notes_nonce'], 'hhs-revision-notes-save' )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// We don't need to bother with empties or deleting existing notes.
		if ( ! isset( $_POST['hhs_revision_note'] ) || empty( $_POST['hhs_revision_note'] ) ) {
			return;
		}

		$note = wp_strip_all_tags( $_POST['hhs_revision_note'] );

		// Save the note as meta on the revision itself.
		// save_post actually runs a second time on the parent post,
		// so it will also be stored as the latest note in the parent post's meta.
		update_metadata( 'post', $post_id, 'revision_note', $note );
	}

	public function wp_prepare_revision_for_js( $data, $revision ) {

		$note = esc_html( get_metadata( 'post', $revision->ID, 'revision_note', true ) );

		if ( ! empty( $note ) ) {
			/* Translators: 1: revision note; 2: time ago; */
			$data['timeAgo'] = sprintf( __( 'Note: %1$s - %2$s', 'revision-notes' ), $note, $data['timeAgo'] );
		}

		return $data;
	}

	public function wp_post_revision_title_expanded( $text, $revision ) {
		// Some safeguards in case this is being called by somebody else for something else.
		// We may want to do these checks elsewhere so that this function can still be used
		// in other contexts should a developer want to do so.
		if ( ! is_admin() ) {
			return $text;
		}

		$screen = get_current_screen();

		if ( 'post' !== $screen->base ) {
			return $text;
		}

		$note = get_metadata( 'post', $revision->ID, 'revision_note', true );

		if ( ! empty( $note ) ) {
			$text .= ' &mdash; <em>' . esc_html( $note ) . '</em>';
		}

		return $text;
	}

	public function add_column( $columns ) {
		$columns['revision_note'] = __( 'Latest Revision Note', 'revision-notes' );

		return $columns;
	}

	public function show_column( $column, $post_id ) {
		if ( 'revision_note' !== $column ) {
			return;
		}

		$note = get_post_meta( $post_id, 'revision_note', true );

		if ( empty( $note ) ) {
			return;
		}

		echo esc_html( $note );
	}
}

// This is a global so it can be accessed by others for things like hooks.
// It could be a singleton too, I guess.
$hhs_revision_notes = new HHS_Revision_Notes();
