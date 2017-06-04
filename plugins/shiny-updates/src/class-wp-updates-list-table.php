<?php
/**
 * Upgrade API: WP_Updates_List_Table class
 *
 * @package WordPress
 * @subpackage Administration
 * @since 4.X.0
 */

/**
 * Core class used to list available updates of all types.
 *
 * Displays available updates for core, plugins, themes and translations.
 *
 * @since 4.X.0
 *
 * @see WP_List_Table
 */
class WP_Updates_List_Table extends WP_List_Table {

	/**
	 * The current WordPress version.
	 *
	 * @since 4.X.0
	 * @access protected
	 * @var string
	 */
	protected $cur_wp_version;

	/**
	 * The available WordPress version, if applicable.
	 *
	 * @since 4.X.0
	 * @access protected
	 * @var string|false Available WordPress version or false if already up to date.
	 */
	protected $core_update_version = false;

	/**
	 * Whether there are any available updates.
	 *
	 * @since 4.X.0
	 * @access protected
	 * @var bool
	 */
	protected $has_available_updates = false;

	/**
	 * Constructs the list table.
	 *
	 * @since 4.X.0
	 * @access public
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => _x( 'Update', 'noun' ),
			'plural'   => _x( 'Updates', 'noun' ),
		) );
	}

	/**
	 * Determines whether there are any available updates.
	 *
	 * @since 4.X.0
	 * @access public
	 *
	 * @return bool Whether there are any available updates.
	 */
	public function has_available_updates() {
		return $this->has_available_updates;
	}

	/**
	 * Prepares the list of items for display.
	 *
	 * @since 4.X.0
	 * @access public
	 *
	 * @see WP_List_Table::set_pagination_args()
	 * @global string $wp_version The current WordPress version.
	 */
	public function prepare_items() {
		global $wp_version;

		$this->cur_wp_version = preg_replace( '/-.*$/', '', $wp_version );

		$can_update_core    = current_user_can( 'update_core' );
		$can_update_plugins = current_user_can( 'update_plugins' );
		$can_update_themes  = current_user_can( 'update_themes' );

		$core_updates = $can_update_core    ? (array) get_core_updates( array( 'dismissed' => true ) ) : array();
		$plugins      = $can_update_plugins ? get_plugin_updates() : array();
		$themes       = $can_update_themes  ? get_theme_updates() : array();
		$translations = ( $can_update_core || $can_update_plugins || $can_update_themes ) ? wp_get_translation_updates() : array();

		// Core updates.
		foreach ( $core_updates as $core_update ) {
			if ( isset( $core_update->response ) &&
			     'latest' !== $core_update->response &&
			     ! version_compare( $core_update->current, $this->cur_wp_version, '=' )
			) {
				$this->core_update_version = $core_update->current;

				$this->items[] = array(
					'type' => 'core',
					'slug' => 'core',
					'data' => $core_update,
				);
			}
		}

		$this->has_available_updates = ( $this->core_update_version || ! empty( $plugins ) || ! empty( $themes ) || ! empty( $translations ) );

		// Plugin updates.
		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$this->items[] = array(
				'type' => 'plugin',
				'slug' => $plugin_file,
				'data' => $plugin_data,
			);
		}

		// Theme updates.
		foreach ( $themes as $stylesheet => $theme ) {
			$this->items[] = array(
				'type' => 'theme',
				'slug' => $stylesheet,
				'data' => $theme,
			);
		}

		// Translation updates.
		if ( ! empty( $translations ) ) {
			$this->items[] = array(
				'type' => 'translations',
				'slug' => 'translations',
				'data' => $translations,
			);
		}

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->set_pagination_args( array(
			'total_items' => count( $this->items ),
			'per_page'    => count( $this->items ),
			'total_pages' => 1,
		) );
	}

	/**
	 * Displays the actual updates table.
	 *
	 * @since 4.X.0
	 * @access public
	 */
	public function display() {
		$singular = $this->_args['singular'];

		$this->display_tablenav( 'top' );

		$this->screen->render_screen_reader_content( 'heading_list' );
		?>
		<table id="wp-updates-table" class="wp-list-table <?php echo esc_attr( implode( ' ', $this->get_table_classes() ) ); ?>">
			<thead>
			<tr>
				<?php $this->print_column_headers(); ?>
			</tr>
			</thead>

			<tbody id="the-list"<?php
			if ( $singular ) {
				echo esc_attr( " data-wp-lists='list:$singular'" );
			} ?>>
			<?php $this->display_rows_or_placeholder(); ?>
			</tbody>

			<?php if ( 2 < $this->_pagination_args['total_items'] ) : ?>
				<tfoot>
				<tr>
					<?php $this->print_column_headers( false ); ?>
				</tr>
				</tfoot>
			<?php endif; ?>

		</table>
		<?php
		$this->display_tablenav( 'bottom' );
	}

	/**
	 * Retrieves a list of columns.
	 *
	 * @since 4.X.0
	 * @access public
	 *
	 * @return array The list table columns.
	 */
	public function get_columns() {
		return array(
			'title'  => _x( 'Update', 'noun' ),
			'type'   => __( 'Type' ),
			'action' => __( 'Action' ),
		);
	}

	/**
	 * Generates content for a single row of the table
	 *
	 * @since 4.X.0
	 * @access public
	 *
	 * @param array $item The current item.
	 */
	public function single_row( $item ) {
		$data = '';

		foreach ( $this->get_data_attributes( $item, 'row' ) as $attribute => $value ) {
			$data .= $attribute . '="' . esc_attr( $value ) . '" ';
		}

		echo "<tr $data>";
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Handles the title column output.
	 *
	 * @since 4.X.0
	 * @access public
	 *
	 * @param array $item The current item.
	 */
	public function column_title( $item ) {
		if ( method_exists( $this, 'column_title_' . $item['type'] ) ) {
			call_user_func(
				array( $this, 'column_title_' . $item['type'] ),
				$item
			);
		}
	}

	/**
	 * Handles the title column output for a theme update item.
	 *
	 * @since 4.X.0
	 * @access public
	 *
	 * @param array $item The current item.
	 */
	public function column_title_theme( $item ) {
		/* @var WP_Theme $theme */
		$theme = $item['data'];
		?>
		<div class="updates-table-screenshot">
			<img src="<?php echo esc_url( $theme->get_screenshot() ); ?>" width="85" height="64" alt=""/>
		</div>
		<p>
			<strong><?php echo $theme->display( 'Name' ); ?></strong>
			<?php
			printf(
				/* translators: 1: theme version, 2: new version */
				__( 'You have version %1$s installed. Update to %2$s.' ),
				$theme->display( 'Version' ),
				$theme->update['new_version']
			);
			?>
		</p>
		<?php
	}

	/**
	 * Handles the title column output for a plugin update item.
	 *
	 * @since 4.X.0
	 * @access public
	 *
	 * @param array $item The current item.
	 */
	public function column_title_plugin( $item ) {
		$plugin = $item['data'];
		$compat = '';

		// Get plugin compat for running version of WordPress.
		if ( isset( $plugin->update->tested ) && version_compare( $plugin->update->tested, $this->cur_wp_version, '>=' ) ) {
			$compat_current = sprintf( __( 'Compatibility with WordPress %1$s: 100%% (according to its author)' ), $this->cur_wp_version );
		} elseif ( isset( $plugin->update->compatibility->{$this->cur_wp_version} ) ) {
			$plugin_compat = $plugin->update->compatibility->{$this->cur_wp_version};

			$compat_current = sprintf(
				__( 'Compatibility with WordPress %1$s: %2$d%% (%3$d "works" votes out of %4$d total)' ),
				$this->cur_wp_version,
				$plugin_compat->percent,
				$plugin_compat->votes,
				$plugin_compat->total_votes
			);

			$compat = '<br />' . $compat_current;
		} else {
			$compat_current = sprintf( __( 'Compatibility with WordPress %1$s: Unknown' ), $this->cur_wp_version );
			$compat         = '<br />' . $compat_current;
		}

		// Get plugin compat for updated version of WordPress.
		if ( $this->core_update_version ) {
			if ( isset( $plugin->update->tested ) && version_compare( $plugin->update->tested, $this->core_update_version, '>=' ) ) {
				$compat_updated = sprintf( __( 'Compatibility with WordPress %1$s: 100%% (according to its author)' ), $this->core_update_version );

				// Only show compatibility if it's not 100% for both scenarios.
				if ( ! empty( $compat ) ) {
					$compat = '<br />' . $compat_current . '<br />' . $compat_updated;
				}
			} elseif ( isset( $plugin->update->compatibility->{$this->core_update_version} ) ) {
				$update_compat = $plugin->update->compatibility->{$this->core_update_version};

				$compat_updated = sprintf(
					__( 'Compatibility with WordPress %1$s: %2$d%% (%3$d "works" votes out of %4$d total)' ),
					$this->core_update_version,
					$update_compat->percent,
					$update_compat->votes,
					$update_compat->total_votes
				);

				$compat = '<br />' . $compat_current . '<br />' . $compat_updated;
			} else {
				$compat_updated = sprintf( __( 'Compatibility with WordPress %1$s: Unknown' ), $this->core_update_version );
				$compat         = '<br />' . $compat_current . '<br />' . $compat_updated;
			}
		}

		$upgrade_notice = '';

		// Get the upgrade notice for the new plugin version.
		if ( isset( $plugin->update->upgrade_notice ) ) {
			$upgrade_notice = '<br />' . strip_tags( $plugin->update->upgrade_notice );
		}

		$details_url = self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin->update->slug . '&section=changelog&TB_iframe=true&width=640&height=662' );
		$details     = sprintf(
			'<a href="%1$s" class="thickbox open-plugin-details-modal" aria-label="%2$s">%3$s</a>',
			esc_url( $details_url ),
			esc_attr( sprintf(
				/* translators: 1: Plugin name, 2: Version number */
				__( 'View %1$s version %2$s details' ),
				$plugin->Name,
				$plugin->update->new_version
			) ),
			/* translators: %s: Plugin version */
			sprintf( __( 'View version %s details.' ), $plugin->update->new_version )
		);
		?>
		<div class="updates-table-screenshot"></div>
		<p>
			<strong><?php echo $plugin->Name; ?></strong>
			<?php
			printf(
				/* translators: 1: Plugin version, 2: New version */
				__( 'You have version %1$s installed. Update to %2$s.' ),
				$plugin->Version,
				$plugin->update->new_version
			);
			echo ' ' . $details . $compat . $upgrade_notice;
			?>
		</p>
		<?php
	}

	/**
	 * Handles the title column output for a core update item.
	 *
	 * @since 4.X.0
	 * @access public
	 *
	 * @global string $wp_version The current WordPress version.
	 * @global wpdb   $wpdb       WordPress database abstraction object.
	 *
	 * @param array $item The current item.
	 */
	public function column_title_core( $item ) {
		global $wp_version, $wpdb;

		$update = $item['data'];

		if ( 'en_US' === $update->locale &&
		     'en_US' === get_locale() ||
		     (
			     $update->packages->partial &&
			     $wp_version === $update->partial_version &&
			     1 === count( get_core_updates() )
		     )
		) {
			$version_string = $update->current;
		} else {
			$version_string = sprintf( '%s&ndash;<code>%s</code>', $update->current, $update->locale );
		}

		$dismiss_url = add_query_arg(
			array(
				'locale'  => $update->locale,
				'version' => $update->current,
			),
			admin_url( 'update-core.php' )
		);

		if ( 'en_US' !== $update->locale && isset( $update->dismissed ) && $update->dismissed ) :
			printf(
				'<p><a href="%1$s" aria-label="%2$s">%3$s</a></p>',
				esc_url( add_query_arg( 'undismiss', '', $dismiss_url ) ),
				sprintf(
					/* translators: 1: WordPress version, 2: locale */
					__( 'Show the WordPress %1$s (%2$s) update' ),
					$update->current,
					$update->locale
				),
				__( 'Show this update' )
			);
		else : ?>
			<div class="updates-table-screenshot">
				<img src="<?php echo esc_url( admin_url( 'images/wordpress-logo.svg' ) ); ?>" width="85" height="85" alt=""/>
			</div>
			<p>
				<strong><?php _e( 'WordPress' ); ?></strong>
				<?php
				if ( 'development' === $update->response ) {
					_e( 'You are using a development version of WordPress. You can update to the latest nightly build automatically.' );
				} elseif ( isset( $update->response ) && 'latest' !== $update->response ) {
					$php_version   = phpversion();
					$mysql_version = $wpdb->db_version();

					if ( ! $this->is_mysql_compatible( $update ) && ! $this->is_php_compatible( $update ) ) {
						printf(
							/* translators: 1: WordPress version, 2: Required PHP version, 3: Required MySQL version, 4: Current PHP version, 5: Current MySQL version */
							__( 'You cannot update because <a href="https://codex.wordpress.org/Version_%1$s">WordPress %1$s</a> requires PHP version %2$s or higher and MySQL version %3$s or higher. You are running PHP version %4$s and MySQL version %5$s.' ),
							$update->current,
							$update->php_version,
							$update->mysql_version,
							$php_version,
							$mysql_version
						);
					} elseif ( ! $this->is_php_compatible( $update ) ) {
						printf(
							/* translators: 1: WordPress version, 2: Required PHP version, 3: Current PHP version */
							__( 'You cannot update because <a href="https://codex.wordpress.org/Version_%1$s">WordPress %1$s</a> requires PHP version %2$s or higher. You are running version %3$s.' ),
							$update->current,
							$update->php_version,
							$php_version
						);
					} elseif ( ! $this->is_mysql_compatible( $update ) ) {
						printf(
							/* translators: 1: WordPress version, 2: Required MySQL version, 3: Current MySQL version */
							__( 'You cannot update because <a href="https://codex.wordpress.org/Version_%1$s">WordPress %1$s</a> requires MySQL version %2$s or higher. You are running version %3$s.' ),
							$update->current,
							$update->mysql_version,
							$mysql_version
						);
					} else {
						printf(
							/* translators: 1: WordPress version, 2: WordPress version including locale */
							__( 'You can update to <a href="https://codex.wordpress.org/Version_%1$s">WordPress %2$s</a> automatically.' ),
							$update->current,
							$version_string
						);
					}
				}
				?>
			</p>
			<?php
			if ( 'en_US' !== $update->locale && ! ( isset( $update->dismissed ) && $update->dismissed ) ) {
				printf(
					'<p><a href="%1$s" aria-label="%2$s">%3$s</a></p>',
					esc_url( add_query_arg( 'dismiss', '', $dismiss_url ) ),
					/* translators: 1: WordPress version, 2: locale */
					sprintf( __( 'Show the WordPress %1$s (%2$s) update' ), $update->current, $update->locale ),
					__( 'Hide this update' )
				);
			}
		endif;
	}

	/**
	 * Handles the title column output for the translations item.
	 *
	 * @since 4.X.0
	 * @access public
	 */
	public function column_title_translations() {
		?>
		<div class="updates-table-screenshot">
			<span class="dashicons dashicons-translation"></span>
		</div>
		<p>
			<strong><?php _e( 'Translations' ); ?></strong>
			<?php _e( 'New translations are available.' ); ?>
		</p>
		<?php
	}

	/**
	 * Handles the type column output.
	 *
	 * @since 4.X.0
	 * @access public
	 *
	 * @param array $item The current item.
	 */
	public function column_type( $item ) {
		switch ( $item['type'] ) {
			case 'plugin':
				_e( 'Plugin' );
				break;

			case 'theme':
				_e( 'Theme' );
				break;

			case 'translations':
				_e( 'Translations' );
				break;

			case 'core':
				_e( 'WordPress' );
				break;
		}
	}

	/**
	 * Handles the action column output.
	 *
	 * @since 4.X.0
	 * @access public
	 *
	 * @param array $item The current item.
	 */
	public function column_action( $item ) {
		$slug         = $item['slug'];
		$form_action  = sprintf( 'update-core.php?action=do-%s-upgrade', $item['type'] );
		$nonce_action = 'translations' === $item['type'] ? 'upgrade-translations' : 'upgrade-core';
		$data         = '';

		if ( 'core' === $item['type'] ) {

			// Bail if this is a dismissed localized Core update.
			if ( 'en_US' !== $item['data']->locale && isset( $item['data']->dismissed ) && $item['data']->dismissed ) {
				return;
			}

			// Bail if the new mysql or php requirements are incompatible.
			if ( ! $this->is_mysql_compatible( $item['data'] ) || ! $this->is_php_compatible( $item['data'] ) ) {
				return;
			}
		}

		foreach ( $this->get_data_attributes( $item, 'button' ) as $attribute => $value ) {
			$data .= $attribute . '="' . esc_attr( $value ) . '" ';
		}
		?>
		<form method="post" action="<?php echo esc_url( $form_action ); ?>" name="upgrade-all">
			<?php wp_nonce_field( $nonce_action ); ?>
			<?php if ( 'core' === $item['type'] ) : ?>
				<input name="version" value="<?php echo esc_attr( $item['data']->current ); ?>" type="hidden"/>
				<input name="locale" value="<?php echo esc_attr( $item['data']->locale ); ?>" type="hidden"/>
			<?php elseif ( 'theme' === $item['type'] || 'plugin' === $item['type'] ) : ?>
				<input type="hidden" name="checked[]" value="<?php echo esc_attr( $slug ); ?>"/>
			<?php endif; ?>
			<?php
			printf(
				'<button type="submit" class="button update-link" %1$s>%2$s</button>',
				$data,
				esc_attr__( 'Update' )
			);
			?>
		</form>
		<?php
	}

	/**
	 * Returns a list of CSS classes for the WP_List_Table table tag.
	 *
	 * @since 4.X.0
	 * @access protected
	 *
	 * @return array List of CSS classes for the table tag.
	 */
	protected function get_table_classes() {
		return array( 'widefat', 'striped', $this->_args['plural'] );
	}

	/**
	 * Generates the table navigation above or below the table.
	 *
	 * @since 4.X.0
	 * @access protected
	 *
	 * @param string $which The location of the bulk actions: 'top' or 'bottom'.
	 */
	protected function display_tablenav( $which ) {
		$total_items = $this->_pagination_args['total_items'];
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">
			<?php
			if ( $this->has_available_updates ) : ?>
				<div class="alignright actions">
					<?php wp_nonce_field( 'upgrade-core', '_wpnonce' ); ?>
					<span class="displaying-num">
						<?php printf( _n( '%s item', '%s items', $total_items ), number_format_i18n( $total_items ) ); ?>
					</span>
					<button class="button button-primary update-link hide-if-no-js" data-type="all" type="submit" aria-label="<?php esc_attr_e( 'Install all updates now' ); ?>">
						<?php esc_attr_e( 'Update All' ); ?>
					</button>
				</div>
			<?php endif;
			?>
		</div>
		<?php
	}

	/**
	 * Retrieves the data attributes for a given list table item.
	 *
	 * @since 4.X.0
	 * @access protected
	 *
	 * @param array  $item    The current item.
	 * @param string $context Optional. Context where the attributes should be applied.
	 *                        Can be either 'row' or 'button'. Default 'row'.
	 * @return array Data attributes as key value pairs.
	 */
	protected function get_data_attributes( $item, $context = 'row' ) {
		$attributes = array( 'data-type' => esc_attr( $item['type'] ) );

		switch ( $item['type'] ) {
			case 'plugin':
				$attributes['data-plugin'] = esc_attr( $item['slug'] );
				$attributes['data-slug']   = esc_attr( $item['data']->update->slug );
				$attributes['data-name']   = esc_attr( $item['data']->Name );

				if ( 'button' === $context ) {
					/* translators: %s: Plugin name */
					$attributes['aria-label'] = esc_attr( sprintf( __( 'Update %s now' ), $item['data']->Name ) );
				}
				break;
			case 'theme':
				$attributes['data-slug'] = esc_attr( $item['slug'] );
				$attributes['data-name'] = esc_attr( $item['data']->display( 'Name' ) );

				if ( 'button' === $context ) {
					/* translators: %s: Theme name */
					$attributes['aria-label'] = esc_attr( sprintf( __( 'Update %s now' ), $item['data']->display( 'Name' ) ) );
				}
				break;
			case 'translations':
				if ( 'button' === $context ) {
					$attributes['aria-label'] = esc_attr__( 'Update translations now' );
				}
				break;
			case 'core':
				$attributes['data-version'] = esc_attr( $item['data']->current );
				$attributes['data-locale']  = esc_attr( $item['data']->locale );

				if ( 'button' === $context ) {
					$attributes['aria-label'] = esc_attr__( 'Update WordPress now' );
				}
				break;
			default:
				break;
		}

		return $attributes;
	}

	/**
	 * Checks whether the current MySQL version is compatible with the one required by the update.
	 *
	 * @since 4.X.0
	 * @access protected
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param object $update Core update item.
	 * @return bool Whether the current MySQL version is compatible or not.
	 */
	protected function is_mysql_compatible( $update ) {
		global $wpdb;

		return ( file_exists( WP_CONTENT_DIR . '/db.php' ) && empty( $wpdb->is_mysql ) ) ||
		       version_compare( $wpdb->db_version(), $update->mysql_version, '>=' );
	}

	/**
	 * Checks whether the current PHP version is compatible with the one required by the update.
	 *
	 * @since 4.X.0
	 * @access protected
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param object $update Core update item.
	 * @return bool Whether the current PHP version is compatible or not.
	 */
	protected function is_php_compatible( $update ) {
		return version_compare( phpversion(), $update->php_version, '>=' );
	}
}
