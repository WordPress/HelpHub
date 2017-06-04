<?php
/**
 * General admin functionality.
 *
 * @package    Members
 * @subpackage Admin
 * @author     Justin Tadlock <justin@justintadlock.com>
 * @copyright  Copyright (c) 2009 - 2016, Justin Tadlock
 * @link       http://themehybrid.com/plugins/members
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

# Register scripts/styles.
add_action( 'admin_enqueue_scripts', 'members_admin_register_scripts', 0 );
add_action( 'admin_enqueue_scripts', 'members_admin_register_styles',  0 );

# Custom manage users columns.
add_filter( 'manage_users_columns',       'members_manage_users_columns'              );
add_filter( 'manage_users_custom_column', 'members_manage_users_custom_column', 10, 3 );

/**
 * Get an Underscore JS template.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $name
 * @return bool
 */
function members_get_underscore_template( $name ) {
	require_once( members_plugin()->admin_dir . "tmpl/{$name}.php" );
}

/**
 * Registers custom plugin scripts.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function members_admin_register_scripts() {

	$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	wp_register_script( 'members-settings',  members_plugin()->js_uri . "settings{$min}.js",  array( 'jquery'  ), '', true );
	wp_register_script( 'members-edit-role', members_plugin()->js_uri . "edit-role{$min}.js", array( 'postbox', 'wp-util' ), '', true );

	// Localize our script with some text we want to pass in.
	$i18n = array(
		'button_role_edit' => esc_html__( 'Edit',                'members' ),
		'button_role_ok'   => esc_html__( 'OK',                  'members' ),
		'label_grant_cap'  => esc_html__( 'Grant %s capability', 'members' ),
		'label_deny_cap'   => esc_html__( 'Deny %s capability',  'members' ),
		'ays_delete_role'  => esc_html__( 'Are you sure you want to delete this role? This is a permanent action and cannot be undone.', 'members' )
	);

	wp_localize_script( 'members-edit-role', 'members_i18n', $i18n );
}

/**
 * Registers custom plugin scripts.
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
function members_admin_register_styles() {

	$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	wp_register_style( 'members-admin', members_plugin()->css_uri . "admin{$min}.css" );
}

/**
 * Function for safely deleting a role and transferring the deleted role's users to the default
 * role.  Note that this function can be extremely intensive.  Whenever a role is deleted, it's
 * best for the site admin to assign the user's of the role to a different role beforehand.
 *
 * @since  0.2.0
 * @access public
 * @param  string  $role
 * @return void
 */
function members_delete_role( $role ) {

	// Get the default role.
	$default_role = get_option( 'default_role' );

	// Don't delete the default role. Site admins should change the default before attempting to delete the role.
	if ( $role == $default_role )
		return;

	// Get all users with the role to be deleted.
	$users = get_users( array( 'role' => $role ) );

	// Check if there are any users with the role we're deleting.
	if ( is_array( $users ) ) {

		// If users are found, loop through them.
		foreach ( $users as $user ) {

			// If the user has the role and no other roles, set their role to the default.
			if ( $user->has_cap( $role ) && 1 >= count( $user->roles ) )
				$user->set_role( $default_role );

			// Else, remove the role.
			else if ( $user->has_cap( $role ) )
				$user->remove_role( $role );
		}
	}

	// Remove the role.
	remove_role( $role );

	// Remove the role from the role factory.
	members_role_factory()->remove_role( $role );
}

/**
 * Returns an array of all the user meta keys in the $wpdb->usermeta table.
 *
 * @since  0.2.0
 * @access public
 * @global object  $wpdb
 * @return array
 */
function members_get_user_meta_keys() {
	global $wpdb;

	return $wpdb->get_col( "SELECT meta_key FROM $wpdb->usermeta GROUP BY meta_key ORDER BY meta_key" );
}

/**
 * Adds custom columns to the `users.php` screen.
 *
 * @since  1.0.0
 * @access public
 * @param  array  $columns
 * @return array
 */
function members_manage_users_columns( $columns ) {

	// If multiple roles per user is not enabled, bail.
	if ( ! members_multiple_user_roles_enabled() )
		return $columns;

	// Unset the core WP `role` column.
	if ( isset( $columns['role'] ) )
		unset( $columns['role'] );

	// Add our new roles column.
	$columns['roles'] = esc_html__( 'Roles', 'members' );

	// Move the core WP `posts` column to the end.
	if ( isset( $columns['posts'] ) ) {
		$p = $columns['posts'];
		unset( $columns['posts'] );
		$columns['posts'] = $p;
	}

	return $columns;
}

/**
 * Handles the output of the roles column on the `users.php` screen.
 *
 * @since  1.0.0
 * @access public
 * @param  string  $output
 * @param  string  $column
 * @param  int     $user_id
 * @return string
 */
function members_manage_users_custom_column( $output, $column, $user_id ) {

	if ( 'roles' === $column && members_multiple_user_roles_enabled() ) {

		$user = new WP_User( $user_id );

		$user_roles = array();
		$output = esc_html__( 'None', 'members' );

		if ( is_array( $user->roles ) ) {

			foreach ( $user->roles as $role ) {

				if ( members_role_exists( $role ) )
					$user_roles[] = members_translate_role( $role );
			}

			$output = join( ', ', $user_roles );
		}
	}

	return $output;
}
