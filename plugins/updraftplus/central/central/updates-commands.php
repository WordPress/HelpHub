<?php

if (!defined('UPDRAFTPLUS_DIR')) die('No access.');

if (!class_exists('UpdraftCentral_Commands')) require_once('commands.php');

class UpdraftCentral_Updates_Commands extends UpdraftCentral_Commands {

	public function do_updates($updates) {
	
		if (!is_array($updates)) $this->_generic_error_response('invalid_data');
		
		if (!empty($updates['plugins']) && !current_user_can('update_plugins')) return $this->_generic_error_response('updates_permission_denied', 'update_plugins');

		if (!empty($updates['themes']) && !current_user_can('update_themes')) return $this->_generic_error_response('updates_permission_denied', 'update_themes');

		if (!empty($updates['core']) && !current_user_can('update_core')) return $this->_generic_error_response('updates_permission_denied', 'update_core');
		
		$this->_admin_include('plugin.php', 'update.php', 'file.php');
		$this->_frontend_include('update.php');

		$plugins = empty($updates['plugins']) ? array() : $updates['plugins'];
		$plugin_updates = array();
		// TODO: There's no support for WP_Filesystem stuff yet
		foreach ($plugins as $plugin_info) {
			$plugin_file = $plugin_info['plugin'];
			$plugin_updates[] = $this->_update_plugin($plugin_info['plugin'], $plugin_info['slug']);
		}

		$themes = empty($updates['themes']) ? array() : $updates['themes'];
		$theme_updates = array();
		foreach ($themes as $theme_info) {
			$theme = $theme_info['theme'];
			$theme_updates[] = $this->_update_theme($theme);
		}

		$cores = empty($updates['core']) ? array() : $updates['core'];
		$core_updates = array();
		foreach ($cores as $core) {
			$core_updates[] = $this->_update_core(null);
			// Only one (and always we go to the latest version) - i.e. we ignore the passed parameters
			break;
		}
		
		// TODO After updating, do we need to trigger an updates check? Otherwise, no other updates seem to show (check that - it may just be "no premium updates show", havent' checked)
		
		return $this->_response(array(
			'plugins' => $plugin_updates,
			'themes' => $theme_updates,
			'core' => $core_updates,
// 			'plugins' => array(),
// 			'themes' => array(),
// 			'core' => array(),
		));

	}

	// Mostly from wp_ajax_update_plugin() in wp-admin/includes/ajax-actions.php (WP 4.5.2)
	// Code-formatting style has been retained from the original, for ease of comparison/updating
	private function _update_plugin($plugin, $slug) {
		global $wp_filesystem;

		$status = array(
			'update'     => 'plugin',
			'plugin'     => $plugin,
			'slug'       => sanitize_key( $slug ),
			'oldVersion' => '',
			'newVersion' => '',
		);

		if (false !== strpos($plugin, '/') || false !== strpos($plugin, '\\')) {
			$status['error'] = 'not_found';
			return $status;
		}
		
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
		if ( $plugin_data['Version'] ) {
			$status['oldVersion'] = $plugin_data['Version'];
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			$status['error'] = 'updates_permission_denied';
			return $status;
		}

		include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

		wp_update_plugins();

		// WP < 3.7
		if (!class_exists('Automatic_Upgrader_Skin')) require_once(UPDRAFTPLUS_DIR.'/central/classes/class-automatic-upgrader-skin.php');
		
		$skin = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result = $upgrader->bulk_upgrade( array( $plugin ) );

		if ( is_array( $result ) && empty( $result[$plugin] ) && is_wp_error( $skin->result ) ) {
			$result = $skin->result;
		}

		$status['messages'] = $upgrader->skin->get_upgrade_messages();
		
		if ( is_array( $result ) && !empty( $result[ $plugin ] ) ) {
			$plugin_update_data = current( $result );

			/*
			* If the `update_plugins` site transient is empty (e.g. when you update
			* two plugins in quick succession before the transient repopulates),
			* this may be the return.
			*
			* Preferably something can be done to ensure `update_plugins` isn't empty.
			* For now, surface some sort of error here.
			*/
			if ( $plugin_update_data === true ) {
				$status['error'] = 'update_failed';
				return $status;
			}

			$plugin_data = get_plugins( '/' . $result[ $plugin ]['destination_name'] );
			$plugin_data = reset( $plugin_data );

			if ( $plugin_data['Version'] ) {
				$status['newVersion'] = $plugin_data['Version'];
			}
			return $status;
			
		} else if ( is_wp_error( $result ) ) {
			$status['error'] = $result->get_error_code();
			$status['error_message'] = $result->get_error_message();
			return $status;

		} else if ( is_bool( $result ) && ! $result ) {
			$status['error'] = 'unable_to_connect_to_filesystem';

			// Pass through the error from WP_Filesystem if one was raised
			if ( is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->get_error_code() ) {
				$status['error'] = $wp_filesystem->errors->get_error_code();
				$status['error_message'] = $wp_filesystem->errors->get_error_message();
			}

			return $status;

		} else {
			// An unhandled error occured
			$status['error'] = 'update_failed';
			return $status;
		}
	}
	
	// Adapted from _update_theme (above)
	private function _update_core($core) {

		global $wp_filesystem;

		$status = array(
			'update'     => 'core',
			'core'     => $core,
			'oldVersion' => '',
			'newVersion' => '',
		);

		include(ABSPATH.WPINC.'/version.php');
		
		$status['oldVersion'] = $wp_version;
		
		if ( ! current_user_can( 'update_core' ) ) {
			$status['error'] = 'updates_permission_denied';
			return $status;
		}

		include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

		wp_version_check();
		
		$locale = get_locale();
		
		$core_update_key = false;
		$core_update_latest_version = false; 
		
		$get_core_updates = get_core_updates();
		
		@include(ABSPATH.WPINC.'/version.php');
		
		foreach ($get_core_updates as $k => $core_update) {
			if (isset($core_update->version) && version_compare($core_update->version, $wp_version, '>') && version_compare($core_update->version, $core_update_latest_version, '>')) {
				$core_update_latest_version = $core_update->version;
				$core_update_key = $k;
			}
		}
		
		if ( $core_update_key === false ) {
			$status['error'] = 'no_update_found';
			return $status;
		}

		$update = $get_core_updates[$core_update_key];

		// WP < 3.7
		if (!class_exists('Automatic_Upgrader_Skin')) require_once(UPDRAFTPLUS_DIR.'/central/classes/class-automatic-upgrader-skin.php');
		
		$skin = new Automatic_Upgrader_Skin();
		$upgrader = new Core_Upgrader( $skin );

		$result = $upgrader->upgrade($update);

		$status['messages'] = $upgrader->skin->get_upgrade_messages();

		if ( is_wp_error( $result ) ) {
			$status['error'] = $result->get_error_code();
			$status['error_message'] = $result->get_error_message();
			return $status;

		} else if ( is_bool( $result ) && ! $result ) {
			$status['error'] = 'unable_to_connect_to_filesystem';

			// Pass through the error from WP_Filesystem if one was raised
			if ( is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->get_error_code() ) {
				$status['error'] = $wp_filesystem->errors->get_error_code();
				$status['error_message'] = $wp_filesystem->errors->get_error_message();
			}

			return $status;

		
		} elseif ( preg_match('/^[0-9]/', $result) ) {
			
			$status['newVersion'] = $result;

			return $status;
			
		} else {
			// An unhandled error occured
			$status['error'] = 'update_failed';
			return $status;
		}

	}

	private function _update_theme($theme) {

		global $wp_filesystem;

		$status = array(
			'update'     => 'theme',
			'theme'     => $theme,
			'oldVersion' => '',
			'newVersion' => '',
		);

		if (false !== strpos($theme, '/') || false !== strpos($theme, '\\')) {
			$status['error'] = 'not_found';
			return $status;
		}
	
		$theme_version = $this->get_theme_version($theme);
		if (false === $theme_version) {
			$status['error'] = 'not_found';
			return $status;
		}
		$status['oldVersion'] = $theme_version;
		
		if ( ! current_user_can( 'update_themes' ) ) {
			$status['error'] = 'updates_permission_denied';
			return $status;
		}

		include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

		wp_update_themes();

		// WP < 3.7
		if (!class_exists('Automatic_Upgrader_Skin')) require_once(UPDRAFTPLUS_DIR.'/central/classes/class-automatic-upgrader-skin.php');
		
		$skin = new Automatic_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$upgrader->init();
		$result = $upgrader->bulk_upgrade( array($theme) );
		
		if ( is_array( $result ) && empty( $result[$theme] ) && is_wp_error( $skin->result ) ) {
			$result = $skin->result;
		}

		$status['messages'] = $upgrader->skin->get_upgrade_messages();

		if ( is_array( $result ) && !empty( $result[ $theme ] ) ) {
			$theme_update_data = current( $result );

			/*
			* If the `update_themes` site transient is empty (e.g. when you update
			* two plugins in quick succession before the transient repopulates),
			* this may be the return.
			*
			* Preferably something can be done to ensure `update_themes` isn't empty.
			* For now, surface some sort of error here.
			*/
			if ( $theme_update_data === true ) {
				$status['error'] = 'update_failed';
				return $status;
			}
			
			$new_theme_version = $this->get_theme_version($theme);
			if (false === $new_theme_version) {
				$status['error'] = 'update_failed';
				return $status;
			}

			$status['newVersion'] = $new_theme_version;

			return $status;
			
		} else if ( is_wp_error( $result ) ) {
			$status['error'] = $result->get_error_code();
			$status['error_message'] = $result->get_error_message();
			return $status;

		} else if ( is_bool( $result ) && ! $result ) {
			$status['error'] = 'unable_to_connect_to_filesystem';

			// Pass through the error from WP_Filesystem if one was raised
			if ( is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->get_error_code() ) {
				$status['error'] = $wp_filesystem->errors->get_error_code();
				$status['error_message'] = $wp_filesystem->errors->get_error_message();
			}

			return $status;

		} else {
			// An unhandled error occured
			$status['error'] = 'update_failed';
			return $status;
		}

	}
	
	private function get_theme_version($theme) {
	
		if (function_exists('wp_get_theme')) {
			// Since WP 3.4.0
			$theme = wp_get_theme($theme);
			
			if (is_a($theme, 'WP_Theme')) {
				return $theme->Version;
			} else {
				return false;
			}
			
		} else {
			$theme_data = get_theme_data(WP_CONTENT_DIR . '/themes/'.$theme.'/style.css');
			
			if (isset($theme_data['Version'])) {
				return $theme_data['Version'];
			} else {
				return false;
			}
		}
	}

	
	public function get_updates($options) {
		
		if (!current_user_can('update_plugins') && !current_user_can('update_themes') && !current_user_can('update_core')) return $this->_generic_error_response('updates_permission_denied');

		$this->_admin_include('plugin.php', 'update.php');
		$this->_frontend_include('update.php');
		
		// Normalise it
		$plugin_updates = array();
		if (current_user_can('update_plugins')) {
		
			if (!empty($options['force_refresh'])) {
				delete_site_transient('update_plugins');
				wp_update_plugins();
			}
		
			$get_plugin_updates = get_plugin_updates();
			if (is_array($get_plugin_updates)) {
				foreach ($get_plugin_updates as $update) {
					$plugin_updates[] = array(
						'name' => $update->Name,
						'plugin_uri' => $update->PluginURI,
						'version' => $update->Version,
						'description' => $update->Description,
						'author' => $update->Author,
						'author_uri' => $update->AuthorURI,
						'title' => $update->Title,
						'author_name' => $update->AuthorName,
						'update' => array(
							'plugin' => $update->update->plugin,
							'slug' => $update->update->slug,
							'new_version' => $update->update->new_version,
							'package' => $update->update->package,
							'tested' => isset($update->update->tested) ? $update->update->tested : null,
							'compatibility' => isset($update->update->compatibility) ? (array)$update->update->compatibility : null,
							'sections' => isset($update->update->sections) ? (array)$update->update->sections : null,
						),
					);
				}
			}
		}
		
		$theme_updates = array();
		if (current_user_can('update_themes')) {
			if (!empty($options['force_refresh'])) {
				delete_site_transient('update_themes');
				wp_update_themes();
			}
			$get_theme_updates = get_theme_updates();
			if (is_array($get_theme_updates)) {
				foreach ($get_theme_updates as $update) {
					$theme_updates[] = array(
						'name' => $update->get('Name'),
						'theme_uri' => $update->get('ThemeURI'),
						'version' => $update->get('Version'),
						'description' => $update->get('Description'),
						'author' => $update->get('Author'),
						'author_uri' => $update->get('AuthorURI'),
						'update' => array(
							'theme' => $update->update['theme'],
							'new_version' => $update->update['new_version'],
							'package' => $update->update['package'],
							'url' => $update->update['url'],
						),
					);
				}
			}
		}
		
		$core_updates = array();
		if (current_user_can('update_core')) {
		
			if (!empty($options['force_refresh'])) {
				// The next line is only needed for older WP versions - otherwise, the parameter to wp_version_check forces a check.
				delete_site_transient('update_core');
				wp_version_check(array(), true);
			}
		
			$get_core_updates = get_core_updates();

			if (is_array($get_core_updates)) {
			
				$core_update_key = false;
				$core_update_latest_version = false; 
				
				@include(ABSPATH.WPINC.'/version.php');
				
				foreach ($get_core_updates as $k => $core_update) {
					if (isset($core_update->version) && version_compare($core_update->version, $wp_version, '>') && version_compare($core_update->version, $core_update_latest_version, '>')) {
						$core_update_latest_version = $core_update->version;
						$core_update_key = $k;
					}
				}

				if ($core_update_key !== false) {
				
					$update = $get_core_updates[$core_update_key];
					
					global $wpdb;
					
					$core_updates[] = array(
						'download' => $update->download,
						'version' => $update->version,
						'php_version' => $update->php_version,
						'mysql_version' => $update->mysql_version	,
						'installed' => array(
							'version' => $wp_version,
							'mysql' => $wpdb->db_version(),
							'php' => PHP_VERSION,
						)
					);
					
				}
			}
			
		}
		
		return $this->_response(array(
			'plugins' => $plugin_updates,
			'themes' => $theme_updates,
			'core' => $core_updates,
// 			'plugins' => array(),
// 			'themes' => array(),
// 			'core' => array(),
		));
	}
		
}
