<?php
/**
 * Sets up plugin-related functions.
 *
 * @package Shiny_Updates
 * @since 4.X.0
 */

/**
 * Enqueue scripts.
 *
 * @todo Merge: Add to wp_default_scripts()
 *
 * @param string $hook Current admin page.
 */
function su_enqueue_scripts( $hook ) {
	if ( ! in_array( $hook, array(
		'update-core.php',
	), true )
	) {
		return;
	}

	wp_enqueue_style( 'shiny-updates', plugin_dir_url( __FILE__ ) . 'css/shiny-updates.css' );

	// Override updates JS.
	wp_enqueue_script( 'shiny-updates', plugin_dir_url( __FILE__ ) . 'js/shiny-updates.js', array( 'updates' ), false, true );
	wp_localize_script( 'shiny-updates', '_wpShinyUpdatesSettings', array(
		'l10n' => array(
			'updatingAllLabel'          => __( 'Updating site...' ), // No ellipsis.
			'updatingCoreLabel'         => __( 'Updating WordPress...' ), // No ellipsis.
			'updatingTranslationsLabel' => __( 'Updating translations...' ), // No ellipsis.
			'coreRedirect'              => __( 'Note: You will be redirected to the About page after WordPress has been updated.' ),
		),
	) );
}

/**
 * Maybe dismiss core updates
 *
 * @todo Merge: Add directly to wp-admin/update-core.php
 */
function su_dismiss_core_updates() {
	// Do the (un)dismiss actions before headers, so that they can redirect.
	if ( isset( $_GET['dismiss'] ) || isset( $_GET['undismiss'] ) ) {
		$version = isset( $_GET['version'] ) ? sanitize_text_field( wp_unslash( $_GET['version'] ) ) : false;
		$locale  = isset( $_GET['locale'] ) ? sanitize_text_field( wp_unslash( $_GET['locale'] ) ) : 'en_US';

		$update = find_core_update( $version, $locale );

		if ( $update ) {
			if ( isset( $_GET['dismiss'] ) ) {
				dismiss_core_update( $update );
			} else {
				undismiss_core_update( $version, $locale );
			}
		}
	}
}

/**
 * Displays the shiny update table.
 *
 * Includes core, plugin and theme updates.
 *
 * @todo Merge: Add directly to wp-admin/update-core.php
 *
 * @global string $wp_version             The current WordPress version.
 * @global string $required_php_version   The required PHP version string.
 * @global string $required_mysql_version The required MySQL version string.
 */
function su_update_table() {
	global $wp_version, $required_php_version, $required_mysql_version;
	?>
	<div class="wordpress-updates-table">
		<?php
		// Todo: Use _get_list_table().
		require_once( plugin_dir_path( __FILE__ ) . 'class-wp-updates-list-table.php' );
		$updates_table = new WP_Updates_List_Table();
		$updates_table->prepare_items();

		if ( $updates_table->has_available_updates() ) :
			$updates_table->display();
		else : ?>
			<div class="notice notice-success inline">
				<p>
					<strong><?php _e( 'Everything is up to date.' ); ?></strong>
					<?php
					if ( wp_http_supports( array( 'ssl' ) ) ) {
						require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

						$upgrader = new WP_Automatic_Updater();

						$future_minor_update = (object) array(
							'current'       => $wp_version . '.1.next.minor',
							'version'       => $wp_version . '.1.next.minor',
							'php_version'   => $required_php_version,
							'mysql_version' => $required_mysql_version,
						);

						if ( $upgrader->should_update( 'core', $future_minor_update, ABSPATH ) ) {
							echo ' ' . __( 'Future security updates will be applied automatically.' );
						}
					}
					?>
				</p>
			</div>
		<?php endif; ?>
	</div>

	<?php
	$core_updates = (array) get_core_updates( array( 'dismissed' => true ) );

	if ( ! empty( $core_updates ) ) :
		$first_pass = true;

		foreach ( $core_updates as $update ) :
			if ( 'en_US' === $update->locale &&
			     'en_US' === get_locale() ||
			     (
				     $update->packages->partial &&
				     $wp_version === $update->partial_version &&
				     1 === count( $core_updates )
			     )
			) {
				$version_string = $update->current;
			} else {
				$version_string = sprintf( '%s&ndash;<code>%s</code>', $update->current, $update->locale );
			}

			if ( ! isset( $update->response ) || 'latest' === $update->response ) :
				if ( $first_pass ) : ?>
					<div class="wordpress-reinstall-card card">
					<h2><?php _e( 'Need to re-install WordPress?' ); ?></h2>
				<?php endif; ?>

				<div class="wordpress-reinstall-card-item" data-type="core" data-reinstall="true" data-version="<?php echo esc_attr( $update->current ); ?>" data-locale="<?php echo esc_attr( $update->locale ); ?>">
					<p>
						<?php
						/* translators: %s: WordPress version */
						printf( __( 'If you need to re-install version %s, you can do so here.' ), $version_string );
						?>
					</p>

					<form method="post" action="update-core.php?action=do-core-reinstall" name="upgrade" class="upgrade">
						<?php wp_nonce_field( 'upgrade-core' ); ?>
						<input name="version" value="<?php echo esc_attr( $update->current ); ?>" type="hidden"/>
						<input name="locale" value="<?php echo esc_attr( $update->locale ); ?>" type="hidden"/>
						<p>
							<button type="submit" name="upgrade" class="button update-link"><?php esc_attr_e( 'Re-install Now' ); ?></button>
						</p>
					</form>
				</div>

				<?php if ( $first_pass ) : ?>
				</div>
				<?php
				$first_pass = false;
			endif;
			endif;
		endforeach;
	endif;
}

/**
 * Filters the list of removable query args to add query args needed for Shiny Updates.
 *
 * @todo Merge: Add directly to wp_removable_query_args()
 *
 * @param array $query_args An array of query variables to remove from a URL.
 * @return array The filtered query args.
 */
function su_wp_removable_query_args( $query_args ) {
	$query_args[] = 'locale';
	$query_args[] = 'version';
	$query_args[] = 'dismiss';
	$query_args[] = 'undismiss';

	return $query_args;
}
