<?php
/*
	Plugin Name: WordPress Beta Tester
	Plugin URI: https://wordpress.org/plugins/wordpress-beta-tester/
	Description: Allows you to easily upgrade to Beta releases.
	Author: Peter Westwood
	Version: 1.1.2
	Network: true
	Author URI: http://blog.ftwr.co.uk/
	Text Domain: wordpress-beta-tester
	License: GPL v2 or later
*/

/*	Copyright 2009-2016 Peter Westwood (email : peter.westwood@ftwr.co.uk)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class wp_beta_tester {

	function __construct() {
		add_action( 'admin_init', array( &$this, 'action_admin_init' ) );
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( &$this, 'action_admin_menu' ) );
		add_action( 'network_admin_edit_wp_beta_tester', array( &$this, 'update_settings' ) );
		add_action( 'update_option_wp_beta_tester_stream', array(
			&$this,
			'action_update_option_wp_beta_tester_stream',
		) );
		add_filter( 'pre_http_request', array( &$this, 'filter_http_request' ), 10, 3 );
		add_action( 'admin_head-plugins.php', array( &$this, 'action_admin_head_plugins_php' ) );
		add_action( 'admin_head-update-core.php', array( &$this, 'action_admin_head_plugins_php' ) );
	}

	function update_settings() {
		if ( isset( $_POST['option_page'] ) ) {
			if ( 'wp_beta_tester_options' === $_POST['option_page'] ) {
				update_site_option( 'wp_beta_tester_stream', $this->validate_setting( $_POST['wp_beta_tester_stream'] ) );
			}
		}

		$redirect_url = is_multisite() ? network_admin_url( 'settings.php' ) : admin_url( 'options-general.php' );
		$location     = add_query_arg(
			array(
				'page'    => 'wp_beta_tester',
				'updated' => 'true',
			),
			$redirect_url
		);
		wp_redirect( $location );
		exit;
	}

	function action_admin_head_plugins_php() {
		// Workaround the check throttling in wp_version_check()
		$st = get_site_transient( 'update_core' );
		if ( is_object( $st ) ) {
			$st->last_checked = 0;
			set_site_transient( 'update_core', $st );
		}
		wp_version_check();
		//Can output an error here if current config drives version backwards
		if ( $this->check_if_settings_downgrade() ) {
			?>
			<div id="message" class="error">
				<p><?php printf( __( '<strong>Error:</strong> Your current <a href="%1$s">WordPress Beta Tester plugin configuration</a> will downgrade your install to a previous version - please reconfigure it.', 'wordpress-beta-tester' ), admin_url( 'tools.php?page=wp_beta_tester' ) ); ?></p>
			</div>
			<?php
		}
	}

	function action_admin_init() {
		load_plugin_textdomain( 'wordpress-beta-tester' );
		register_setting(
			'wp_beta_tester_options',
			'wp_beta_tester_stream',
			array( &$this, 'validate_setting' )
		);
	}

	function action_admin_menu() {
		$parent     = is_multisite() ? 'settings.php' : 'tools.php';
		$capability = is_multisite() ? 'manage_network' : 'manage_options';

		add_submenu_page(
			$parent,
			__( 'Beta Testing WordPress', 'wordpress-beta-tester' ),
			__( 'Beta Testing', 'wordpress-beta-tester' ),
			$capability,
			'wp_beta_tester',
			array( &$this, 'display_page' )
		);
	}

	function filter_http_request( $result, $args, $url ) {
		if ( $result || isset( $args['_beta_tester'] ) ) {
			return $result;
		}
		if ( false === strpos( $url, '//api.wordpress.org/core/version-check/' ) ) {
			return $result;
		}

		// It's a core-update request.
		$args['_beta_tester'] = true;

		global $wp_version, $wpmu_version;
		$url = str_replace( 'version=' . $wp_version, 'version=' . $this->mangle_wp_version(), $url );
		if ( ! empty( $wpmu_version ) ) { // old 2.9.2 WPMU
			$url = str_replace( 'wpmu_version=' . $wpmu_version, 'wpmu_version=' . $this->mangle_wp_version(), $url );
		}

		return wp_remote_get( $url, $args );
	}

	function action_update_option_wp_beta_tester_stream() {
		//Our option has changed so update the cached information pronto.
		do_action( 'wp_version_check' );
	}

	function _get_preferred_from_update_core() {
		if ( ! function_exists( 'get_preferred_from_update_core' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/update.php' );
		}

		//Validate that we have api data and if not get the normal data so we always have it.
		$preferred = get_preferred_from_update_core();
		if ( false === $preferred ) {
			wp_version_check();
			$preferred = get_preferred_from_update_core();
		}

		return $preferred;
	}

	function mangle_wp_version() {
		$stream     = get_site_option( 'wp_beta_tester_stream', 'point' );
		$preferred  = $this->_get_preferred_from_update_core();
		$wp_version = $GLOBALS['wp_version'];

		// If we're getting no updates back from get_preferred_from_update_core(),
		// let an HTTP request go through unmangled.
		if ( ! isset( $preferred->current ) ) {
			return $wp_version;
		}

		$versions = array_map( 'intval', explode( '.', $preferred->current ) );

		switch ( $stream ) {
			case 'point':
				$versions[2] = isset( $versions[2] ) ? $versions[2] + 1 : 1;
				$wp_version  = $versions[0] . '.' . $versions[1] . '.' . $versions[2] . '-wp-beta-tester';
				break;
			case 'unstable':
				$versions[1] += 1;
				if ( 10 == $versions[1] ) {
					$versions[0] += 1;
					$versions[1] = 0;
				}
				$wp_version = $versions[0] . '.' . $versions[1] . '-wp-beta-tester';
				break;
		}

		return $wp_version;
	}

	function check_if_settings_downgrade() {
		global $wp_version;
		$wp_real_version    = explode( '-', $wp_version );
		$wp_mangled_version = explode( '-', $this->mangle_wp_version() );

		return version_compare( $wp_mangled_version[0], $wp_real_version[0], 'lt' );
	}

	function validate_setting( $setting ) {
		return ( in_array( $setting, array( 'point', 'unstable' ) ) ? $setting : 'point' );
	}

	function display_page() {
		$preferred = $this->_get_preferred_from_update_core();

		?>
		<div class="wrap">
			<h1><?php _e( 'Beta Testing WordPress', 'wordpress-beta-tester' ); ?></h1>
			<?php if ( isset( $_GET['updated'] ) && true == $_GET['updated'] ||
			           isset( $_GET['settings-updated'] ) && true == $_GET['settings-updated']
			): ?>
				<div class="updated">
					<p><?php esc_html_e( 'Saved.', 'wordpress-beta-tester' ); ?></p>
				</div>
			<?php endif; ?>
			<div class="updated fade">
				<p><?php _e( '<strong>Please note:</strong> Once you have switched your blog to one of these beta versions of software, it will not always be possible to downgrade, as the database structure may be updated during the development of a major release.', 'wordpress-beta-tester' ); ?></p>
			</div>
			<?php if ( 'development' != $preferred->response ) : ?>
				<div class="updated fade">
					<p><?php _e( '<strong>Please note:</strong> There are no development builds of the beta stream you have choosen available, so you will receive normal update notifications.', 'wordpress-beta-tester' ); ?></p>
				</div>
			<?php endif; ?>
			<?php $this->action_admin_head_plugins_php(); //Check configuration?>
			<div>
				<p><?php echo sprintf( __( 'By their nature, these releases are unstable and should not be used anyplace where your data is important. So please <a href="%1$s">back up your database</a> before upgrading to a test release. In order to hear about the latest beta releases, your best bet is to watch the <a href="%2$s">development blog</a> and the <a href="%3$s">beta forum</a>.', 'wordpress-beta-tester' ),
						_x( 'https://codex.wordpress.org/Backing_Up_Your_Database', 'Url to database backup instructions', 'wordpress-beta-tester' ),
						'https://make.wordpress.org/core/',
						_x( 'https://wordpress.org/support/forum/alphabeta', 'Url to beta support forum', 'wordpress-beta-tester' ) ); ?></p>
				<p><?php echo sprintf( __( 'Thank you for helping by testing WordPress. Please <a href="%s">report any bugs you find</a>.', 'wordpress-beta-tester' ),
						'https://core.trac.wordpress.org/newticket' ); ?></p>

				<p><?php _e( 'By default, your WordPress install uses the stable update stream. To return to this, please deactivate this plugin.', 'wordpress-beta-tester' ); ?></p>
				<?php $action = is_multisite() ? 'edit.php?action=wp_beta_tester' : 'options.php'; ?>
				<form method="post" action="<?php esc_attr_e( $action ); ?>">
					<?php settings_fields( 'wp_beta_tester_options' ); ?>
					<fieldset>
						<legend><?php _e( 'Please select the update stream you would like this blog to use:', 'wordpress-beta-tester' ); ?></legend>
						<?php $stream = get_site_option( 'wp_beta_tester_stream', 'point' ); ?>
						<table class="form-table">
							<tr>
								<th><label><input name="wp_beta_tester_stream"
												  id="update-stream-point-nightlies" type="radio" value="point"
												  class="tog" <?php checked( 'point', $stream ); ?> />
										<?php _e( 'Point release nightlies', 'wordpress-beta-tester' ); ?>
									</label></th>
								<td><?php _e( 'This contains the work that is occurring on a branch in preparation for a x.x.x point release.  This should also be fairly stable but will be available before the branch is ready for beta.', 'wordpress-beta-tester' ); ?></td>
							</tr>
							<tr>
								<th><label><input name="wp_beta_tester_stream"
												  id="update-stream-bleeding-nightlies" type="radio" value="unstable"
												  class="tog" <?php checked( 'unstable', $stream ); ?> />
										<?php _e( 'Bleeding edge nightlies', 'wordpress-beta-tester' ); ?>
									</label></th>
								<td><?php _e( 'This is the bleeding edge development code which may be unstable at times. <em>Only use this if you really know what you are doing</em>.', 'wordpress-beta-tester' ); ?></td>
							</tr>
						</table>
					</fieldset>
					<p class="submit"><input type="submit" class="button-primary"
											 value="<?php _e( 'Save Changes', 'wordpress-beta-tester' ); ?>" /></p>
				</form>
				<p><?php echo sprintf( __( 'Why don\'t you <a href="%s">head on over and upgrade now</a>.', 'wordpress-beta-tester' ), 'update-core.php' ); ?></p>
			</div>
		</div>
		<?php
	}
}

/* Initialise ourselves */
add_action( 'plugins_loaded', 'load_beta_tester_plugin' );

function load_beta_tester_plugin() {
	global $wp_beta_tester_instance;
	$wp_beta_tester_instance = new wp_beta_tester();
}

// Clear down
function wordpress_beta_tester_deactivate_or_activate() {
	delete_site_transient( 'update_core' );
}

register_activation_hook( __FILE__, 'wordpress_beta_tester_deactivate_or_activate' );
register_deactivation_hook( __FILE__, 'wordpress_beta_tester_deactivate_or_activate' );
