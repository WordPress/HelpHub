<?php
/**
 * Ajax callbacks for Shiny Updates.
 *
 * @todo Merge: Add to wp-admin/includes/ajax-actions.php
 *
 * @package Shiny_Updates
 */

/**
 * AJAX handler for updating core.
 *
 * @since 4.X.0
 *
 * @see Core_Upgrader
 */
function wp_ajax_update_core() {
	check_ajax_referer( 'updates' );

	$status = array(
		'update'   => 'core',
		'redirect' => esc_url( network_admin_url( 'about.php?updated' ) ),
	);

	$reinstall = isset( $_POST['reinstall'] ) ? 'true' === sanitize_text_field( wp_unslash( $_POST['reinstall'] ) ) : false;
	$version   = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : false;
	$locale    = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : 'en_US';

	$status['version']   = $version;
	$status['locale']    = $locale;
	$status['reinstall'] = $reinstall ? 'reinstall' : null;

	if ( ! current_user_can( 'update_core' ) ) {
		$status['errorMessage'] = __( 'You do not have sufficient permissions to update this site.' );
		wp_send_json_error( $status );
	}

	$update = find_core_update( $version, $locale );

	if ( ! $update ) {
		$status['errorMessage'] = __( 'Core update failed.' );
		wp_send_json_error( $status );
	}

	if ( $reinstall ) {
		$update->response = 'reinstall';
	}

	include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

	$skin     = new Automatic_Upgrader_Skin();
	$upgrader = new Core_Upgrader( $skin );
	$result   = $upgrader->upgrade( $update, array(
		'allow_relaxed_file_ownership' => ! $reinstall && isset( $update->new_files ) && ! $update->new_files,
	) );

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		$status['debug'] = $upgrader->skin->get_upgrade_messages();
	}

	if ( is_string( $result ) ) {
		wp_send_json_success( $status );
	} elseif ( is_wp_error( $result ) ) {
		$status['errorMessage'] = $result->get_error_message();
		wp_send_json_error( $status );
	} elseif ( false === $result ) {
		global $wp_filesystem;

		$status['errorCode']    = 'unable_to_connect_to_filesystem';
		$status['errorMessage'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.' );

		// Pass through the error from WP_Filesystem if one was raised.
		if ( $wp_filesystem instanceof WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->get_error_code() ) {
			$status['errorMessage'] = esc_html( $wp_filesystem->errors->get_error_message() );
		}

		wp_send_json_error( $status );
	}

	// An unhandled error occurred.
	$status['errorMessage'] = __( 'Core update failed.' );
	wp_send_json_error( $status );
}

/**
 * AJAX handler for updating translations.
 *
 * @since 4.X.0
 *
 * @see Language_Pack_Upgrader
 */
function wp_ajax_update_translations() {
	check_ajax_referer( 'updates' );

	$status = array(
		'update' => 'translations',
	);

	if ( ! current_user_can( 'update_core' ) && ! current_user_can( 'update_plugins' ) && ! current_user_can( 'update_themes' ) ) {
		$status['errorMessage'] = __( 'You do not have sufficient permissions to update this site.' );
		wp_send_json_error( $status );
	}

	include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

	$skin     = new Automatic_Upgrader_Skin();
	$upgrader = new Language_Pack_Upgrader( $skin );
	$result   = $upgrader->bulk_upgrade( array(), array( 'clear_update_cache' => false ) );

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		$status['debug'] = $upgrader->skin->get_upgrade_messages();
	}

	if ( is_array( $result ) && is_wp_error( $skin->result ) ) {
		$result = $skin->result;
	}

	if ( ( is_array( $result ) && ! empty( $result[0] ) ) || true === $result ) {
		wp_send_json_success( $status );
	} elseif ( is_wp_error( $result ) ) {
		$status['errorMessage'] = $result->get_error_message();
		wp_send_json_error( $status );
	} elseif ( false === $result ) {
		global $wp_filesystem;

		$status['errorCode']    = 'unable_to_connect_to_filesystem';
		$status['errorMessage'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.' );

		// Pass through the error from WP_Filesystem if one was raised.
		if ( $wp_filesystem instanceof WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->get_error_code() ) {
			$status['errorMessage'] = esc_html( $wp_filesystem->errors->get_error_message() );
		}

		wp_send_json_error( $status );
	}

	// An unhandled error occurred.
	$status['errorMessage'] = __( 'Translations update failed.' );
	wp_send_json_error( $status );
}
