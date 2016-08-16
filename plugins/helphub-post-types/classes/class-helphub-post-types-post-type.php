<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

/**
 * Helphub Post Types, Post Type Class
 *
 * All functionality pertaining to post types in Helphub Post Types.
 *
 * @package WordPress
 * @subpackage HelpHub_Post_Types
 * @category Plugin
 * @author Jon Ang
 * @since 1.0.0
 */
class HelpHub_Post_Types_Post_Type {
	/**
	 * The post type token.
	 *
	 * @access public
	 * @since  1.0.0
	 * @var    string
	 */
	public $post_type;

	/**
	 * The post type singular label.
	 *
	 * @access public
	 * @since  1.0.0
	 * @var    string
	 */
	public $singular;

	/**
	 * The post type plural label.
	 *
	 * @access public
	 * @since  1.0.0
	 * @var    string
	 */
	public $plural;

	/**
	 * The post type args.
	 *
	 * @access public
	 * @since  1.0.0
	 * @var    array
	 */
	public $args;

	/**
	 * The taxonomies for this post type.
	 *
	 * @access public
	 * @since  1.0.0
	 * @var    array
	 */
	public $taxonomies;

	/**
	 * Constructor function.
	 *
	 * @access public
	 * @since 1.0.0
	 * @param string $post_type The post type id/handle.
	 * @param string $singular The singular pronunciation of the post type name.
	 * @param string $plural The plural pronunciation of the post type name.
	 * @param array  $args The typical arguments allowed to register a post type.
	 * @param array  $taxonomies The list of taxonomies that the post type is associated with.
	 */
	public function __construct( $post_type = 'thing', $singular = '', $plural = '', $args = array(), $taxonomies = array() ) {
		$this->post_type = $post_type;
		$this->singular = $singular;
		$this->plural = $plural;
		$this->args = $args;
		$this->taxonomies = $taxonomies;

		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );

		if ( is_admin() ) {
			global $pagenow;

			add_action( 'admin_menu', array( $this, 'meta_box_setup' ), 20 );
			add_action( 'save_post', array( $this, 'meta_box_save' ), 50 );
			add_filter( 'enter_title_here', array( $this, 'enter_title_here' ) );
			add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );

			if ( 'edit.php' ===  $pagenow && isset( $_GET['post_type'] ) && $_GET['post_type'] === $this->post_type ) {
				add_filter( 'manage_edit-' . $this->post_type . '_columns', array( $this, 'register_custom_column_headings' ), 10, 1 );
				add_action( 'manage_posts_custom_column', array( $this, 'register_custom_columns' ), 10, 2 );
			}
		}
		add_action( 'admin_init', array( $this, 'add_menu_order' ) );
		add_action( 'after_setup_theme', array( $this, 'ensure_post_thumbnails_support' ) );
		add_action( 'after_setup_theme', array( $this, 'register_image_sizes' ) );
	} // End __construct()

	/**
	 * Register the post type.
	 *
	 * @access public
	 * @return void
	 */
	public function register_post_type() {

		if ( post_type_exists( $this->post_type ) ) :
			return;
		endif;

		$labels = array(
			'name' => sprintf( _x( '%s', 'post type general name', 'helphub' ), $this->plural ),
			'singular_name' => sprintf( _x( '%s', 'post type singular name', 'helphub' ), $this->singular ),
			'add_new' => _x( 'Add New', $this->post_type, 'helphub' ),
			'add_new_item' => sprintf( __( 'Add New %s', 'helphub' ), $this->singular ),
			'edit_item' => sprintf( __( 'Edit %s', 'helphub' ), $this->singular ),
			'new_item' => sprintf( __( 'New %s', 'helphub' ), $this->singular ),
			'all_items' => sprintf( __( 'All %s', 'helphub' ), $this->plural ),
			'view_item' => sprintf( __( 'View %s', 'helphub' ), $this->singular ),
			'search_items' => sprintf( __( 'Search %a', 'helphub' ), $this->plural ),
			'not_found' => sprintf( __( 'No %s Found', 'helphub' ), $this->plural ),
			'not_found_in_trash' => sprintf( __( 'No %s Found In Trash', 'helphub' ), $this->plural ),
			'parent_item_colon' => '',
			'menu_name' => $this->plural,
		);

		$single_slug = apply_filters( 'helphub_single_slug', _x( sanitize_title_with_dashes( $this->singular ), 'single post url slug', 'helphub' ) );
		$archive_slug = apply_filters( 'helphub_archive_slug', _x( sanitize_title_with_dashes( $this->plural ), 'post archive url slug', 'helphub' ) );

		$defaults = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'query_var' => true,
			'rewrite' => array( 'slug' => $single_slug ),
			'capability_type' => 'post',
			'has_archive' => $archive_slug,
			'hierarchical' => false,
			'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
			'menu_position' => 5,
			'menu_icon' => 'dashicons-smiley',
		);

		$args = wp_parse_args( $this->args, $defaults );

		register_post_type( $this->post_type, $args );
	} // End register_post_type()

	/**
	 * Register the post-type taxonomy.
	 *
	 * @access public
	 * @since  1.3.0
	 * @return void
	 */
	public function register_taxonomy() {
		foreach ( $this->taxonomies as $taxonomy ) :
			$taxonomy = new HelpHub_Post_Types_Taxonomy( esc_attr( $this->post_type ), $taxonomy, '', '', array() ); // Leave arguments empty, to use the default arguments.
			$taxonomy->register();
		endforeach;
	} // End register_taxonomy()

	/**
	 * Add custom columns for the "manage" screen of this post type.
	 *
	 * @access public
	 * @var string $column_name The name of the column.
	 * @var int $id The post id.
	 * @since  1.0.0
	 * @return void
	 */
	public function register_custom_columns( $column_name, $id ) {
		global $post;

		switch ( $column_name ) {
			case 'image':
				echo $this->get_image( $id, 40 );
			break;

			default:
			break;
		}
	} // End register_custom_columns()

	/**
	 * Add custom column headings for the "manage" screen of this post type.
	 *
	 * @access public
	 * @var array $defaults The default array.
	 * @since  1.0.0
	 * @return array $defaults
	 */
	public function register_custom_column_headings( $defaults ) {
		$new_columns = array( 'image' => __( 'Image', 'helphub' ) );

		$last_item = array();

		if ( isset( $defaults['date'] ) ) { unset( $defaults['date'] ); }

		if ( count( $defaults ) > 2 ) {
			$last_item = array_slice( $defaults, -1 );

			array_pop( $defaults );
		}
		$defaults = array_merge( $defaults, $new_columns );

		if ( is_array( $last_item ) && 0 < count( $last_item ) ) {
			foreach ( $last_item as $k => $v ) {
				$defaults[ $k ] = $v;
				break;
			}
		}

		return $defaults;
	} // End register_custom_column_headings()

	/**
	 * Update messages for the post type admin.
	 *
	 * @since  1.0.0
	 * @param  array $messages Array of messages for all post types.
	 * @return array           Modified array.
	 */
	public function updated_messages( $messages ) {
		global $post;

		$post_id = $post->ID;

		$messages[ $this->post_type ] = array(
			0 => '', // Unused. Messages start at index 1.
			1 => sprintf( __( '%3$s updated. %sView %4$s%s', 'helphub' ), '<a href="' . esc_url( get_permalink( $post_id ) ) . '">', '</a>', $this->singular, strtolower( $this->singular ) ),
			2 => __( 'Custom field updated.', 'helphub' ),
			3 => __( 'Custom field deleted.', 'helphub' ),
			4 => sprintf( __( '%s updated.', 'helphub' ), $this->singular ),
			/* translators: %s: date and time of the revision */
			5 => isset( $_GET['revision'] ) ? sprintf( __( '%s restored to revision from %s', 'helphub' ), $this->singular, wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => sprintf( __( '%1$s published. %3$sView %2$s%4$s', 'helphub' ), $this->singular, strtolower( $this->singular ), '<a href="' . esc_url( get_permalink( $post_id ) ) . '">', '</a>' ),
			7 => sprintf( __( '%s saved.', 'helphub' ), $this->singular ),
			8 => sprintf( __( '%s submitted. %sPreview %s%s', 'helphub' ), $this->singular, strtolower( $this->singular ), '<a target="_blank" href="' . esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_id ) ) ) . '">', '</a>' ),
			9 => sprintf( __( '%s scheduled for: %1$s. %2$sPreview %s%3$s', 'helphub' ), $this->singular, strtolower( $this->singular ),
				// Translators: Publish box date format, see http://php.net/date.
			'<strong>' . date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ) . '</strong>', '<a target="_blank" href="' . esc_url( get_permalink( $post_id ) ) . '">', '</a>' ),
			10 => sprintf( __( '%3$s draft updated. %sPreview %4$s%s', 'helphub' ), '<a target="_blank" href="' . esc_url( get_preview_post_link() ) . '">', '</a>', $this->singular, strtolower( $this->singular ) ),
		);

		return $messages;
	} // End updated_messages()

	/**
	 * Setup the meta box.
	 * You can use separate conditions here to add different meta boxes for different post types
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function meta_box_setup() {
		if ( 'post' === $this->post_type ) :
			add_meta_box( $this->post_type . '-display', __( 'Display Settings', 'helphub' ), array( $this, 'meta_box_content' ), $this->post_type, 'normal', 'high' );
		elseif ( 'helphub_version' === $this->post_type ) :
			add_meta_box( $this->post_type . '-version-meta', __( 'Display Settings', 'helphub' ), array( $this, 'meta_box_version_content' ), $this->post_type, 'normal', 'high' );
		endif;
	} // End meta_box_setup()

	/**
	 * The contents of our post meta box.
	 * Duplicate this function for more callbacks
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function meta_box_content() {
		$field_data = $this->get_custom_fields_post_display_settings();
		$this->meta_box_content_render( $field_data );
	}

	/**
	 * The contents of our post meta box.
	 * Duplicate this function for more callbacks
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function meta_box_version_content() {
		$field_data = $this->get_custom_fields_version_display_settings();
		$this->meta_box_content_render( $field_data );
	}

	/**
	 * The rendering of fields in meta boxes
	 *
	 * @access public
	 * @since  1.0.0
	 * @param array $field_data The field data to populate the rendering function.
	 * @return void
	 */
	public function meta_box_content_render( $field_data ) {
		global $post_id;
		$fields = get_post_custom( $post_id );

		$html = '';

		$html .= '<input type="hidden" name="helphub_' . $this->post_type . '_noonce" id="helphub_' . $this->post_type . '_noonce" value="' . wp_create_nonce( plugin_basename( dirname( HelpHub_Post_Types()->plugin_path ) ) ) . '" />';

		if ( 0 < count( $field_data ) ) {
			$html .= '<table class="form-table">' . "\n";
			$html .= '<tbody>' . "\n";

			foreach ( $field_data as $k => $v ) {
				$data = $v['default'];
				if ( isset( $fields[ '_' . $k ] ) && isset( $fields[ '_' . $k ][0] ) ) {
					$data = $fields[ '_' . $k ][0];
				}

				switch ( $v['type'] ) {
					case 'hidden':
						$field = '<input name="' . esc_attr( $k ) . '" type="hidden" id="' . esc_attr( $k ) . '" value="' . esc_attr( $data ) . '" />';
						$html .= '<tr valign="top">' . $field . "\n";
						$html .= '</tr>' . "\n";
						break;
					case 'text':
					case 'url':
						$field = '<input name="' . esc_attr( $k ) . '" type="text" id="' . esc_attr( $k ) . '" class="regular-text" value="' . esc_attr( $data ) . '" />';
						$html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
						if ( isset( $v['description'] ) ) {
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					case 'textarea':
						$field = '<textarea name="' . esc_attr( $k ) . '" id="' . esc_attr( $k ) . '" class="large-text">' . esc_attr( $data ) . '</textarea>';
						$html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
						if ( isset( $v['description'] ) ) {
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					case 'editor':
						ob_start();
						wp_editor( $data, $k, array( 'media_buttons' => false, 'textarea_rows' => 10 ) );
						$field = ob_get_contents();
						ob_end_clean();
						$html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
						if ( isset( $v['description'] ) ) {
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					case 'upload':
						$data_atts = '';
						if ( isset( $v['media-frame']['title'] ) ) {
							$data_atts .= sprintf( 'data-title="%s" ', esc_attr( $v['media-frame']['title'] ) );
						}
						if ( isset( $v['media-frame']['button'] ) ) {
							$data_atts .= sprintf( 'data-button="%s" ', esc_attr( $v['media-frame']['button'] ) );
						}
						if ( isset( $v['media-frame']['library'] ) ) {
							$data_atts .= sprintf( 'data-library="%s" ', esc_attr( $v['media-frame']['library'] ) );
						}

						$field = '<input name="' . esc_attr( $k ) . '" type="text" id="' . esc_attr( $k ) . '" class="regular-text helphub-upload-field" value="' . esc_attr( $data ) . '" />';
						$field .= '<button id="' . esc_attr( $k ) . '" class="helphub-upload button"' . $data_atts . '>' . $v['label'] . '</button>';
						$html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
						if( isset( $v['description'] ) ){
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					case 'radio':
						$field = '';
						if ( isset( $v['options'] ) && is_array( $v['options'] ) ) {
							foreach ( $v['options'] as $val => $option ) {
								$field .= '<p><label for="' . esc_attr( $v['name'] . '-' . $val ) . '"><input id="' . esc_attr( $v['name'] . '-' . $val ) . '" type="radio" name="' . esc_attr( $k ) . '" value="' . esc_attr( $val ) . '" ' . checked( $val, $data, false ) . ' / >'. $option . '</label></p>' . "\n";
							}
						}
						$html .= '<tr valign="top"><th scope="row"><label>' . $v['name'] . '</label></th><td>' . $field . "\n";
						if( isset( $v['description'] ) ) {
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					case 'checkbox':
						$field = '<p><input id="' . esc_attr( $v['name'] ) . '" type="checkbox" name="' . esc_attr( $k ) . '" value="1" ' . checked( 'yes', $data, false ) . ' / ></p>' . "\n";
						if ( isset( $v['description'] ) ) {
							$field .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr( $v['name'] ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
						$html .= '</td></tr>' . "\n";
						break;
					case 'multicheck':
						$field = '';
						if ( isset( $v['options'] ) && is_array( $v['options'] ) ) {
							foreach ( $v['options'] as $val => $option ) {
								$field .= '<p><label for="' . esc_attr( $v['name'] . '-' . $val ) . '"><input id="' . esc_attr( $v['name'] . '-' . $val ) . '" type="checkbox" name="' . esc_attr( $k ) . '[]" value="' . esc_attr( $val ) . '" ' . checked( 1, in_array( $val, (array) $data ), false ) . ' / >'. $option . '</label></p>' . "\n";
							}
						}
						$html .= '<tr valign="top"><th scope="row"><label>' . $v['name'] . '</label></th><td>' . $field . "\n";
						if ( isset( $v['description'] ) ) {
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					case 'select':
						$field = '<select name="' . esc_attr( $k ) . '" id="' . esc_attr( $k ) . '" >'. "\n";
						if ( isset( $v['options'] ) && is_array( $v['options'] ) ) {
							foreach ( $v['options'] as $val => $option ) {
								$field .= '<option value="' . esc_attr( $val ) . '" ' . selected( $val, $data, false ) . '>'. $option .'</option>' . "\n";
							}
						}
						$field .= '</select>'. "\n";
						$html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
						if ( isset( $v['description'] ) ) {
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					case 'date':
						$field = '<input name="' . esc_attr( $k ) . '" type="date" id="' . esc_attr( $k ) . '" class="helphub-meta-date" value="' . esc_attr( $data ) . '" />';
						$html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
						if ( isset( $v['description'] ) ) {
							$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
						}
						$html .= '</td></tr>' . "\n";
						break;
					default:
						$field = apply_filters( 'helphub_data_field_type_' . $v['type'], null, $k, $data, $v );
						if ( $field ) {
							$html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>' . $field . "\n";
							if ( isset( $v['description'] ) ) {
								$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
							}
							$html .= '</td></tr>' . "\n";
						}
						break;
				}
			}

			$html .= '</tbody>' . "\n";
			$html .= '</table>' . "\n";
		}

		echo $html;
	} // End meta_box_content()

	/**
	 * Save meta box fields.
	 *
	 * @access public
	 * @since  1.0.0
	 * @param int $post_id The post id.
	 * @return int $post_id
	 */
	public function meta_box_save( $post_id ) {
		global $post, $messages;

		// Dashboard Widget does not like our function. This also does not need to be ran there.
		$screen = get_current_screen();

		if ( $screen->id === 'dashboard' ) {
			return $screen;
		}

		// Verify
		if ( ( get_post_type() !== $this->post_type ) ||
		     ( isset( $_POST['helphub_' . $this->post_type . '_noonce'] ) && !wp_verify_nonce( $_POST['helphub_' . $this->post_type . '_noonce'], plugin_basename( dirname( HelpHub_Post_Types()->plugin_path ) ) ) ) ){
			return $post_id;
		}

		if ( isset( $_POST['post_type'] ) && 'page' === $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return $post_id;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $post_id;
			}
		}

		$field_data = $this->get_custom_fields_settings();
		$fields = array_keys( $field_data );

		foreach ( $fields as $f ) :

			switch ( $field_data[ $f ]['type'] ) {
				case 'url':
					${$f} = isset( $_POST[ $f ] ) ? esc_url( $_POST[ $f ] ) : '';
					break;
				case 'textarea':
				case 'editor':
					${$f} = isset( $_POST[ $f ] ) ? wp_kses_post( trim( $_POST[ $f ] ) ) : '';
					break;
				case 'checkbox':
					${$f} = isset( $_POST[ $f ] ) ? 'yes' : 'no';
					break;
				case 'multicheck':
					// Ensure checkbox is array and whitelist accepted values against options.
					${$f} = isset( $_POST[ $f ] ) && is_array( $field_data[ $f ]['options'] ) ? (array) array_intersect( (array) $_POST[ $f ], array_flip( $field_data[ $f ]['options'] ) ) : '';
					break;
				case 'radio':
				case 'select':
					// Whitelist accepted value against options.
					$values = array();
					if ( is_array( $field_data[ $f ]['options'] ) ) {
						$values = array_keys( $field_data[ $f ]['options'] );
					}
					${$f} = isset( $_POST[ $f ] ) && in_array( $_POST[ $f ], $values ) ? $_POST[ $f ] : '';
					break;
				case 'date':
					${$f} = isset( $_POST[ $f ] ) ? preg_replace( '([^0-9/])', '', $_POST[ $f ] ) : '';
					break;
				default :
					${$f} = isset( $_POST[ $f ] ) ? strip_tags( trim( $_POST[ $f ] ) ) : '';
					break;
			}

			// Save it.
			if ( $f !== 'read_time' ) :
				update_post_meta( $post_id, '_' . $f, ${$f} );
			endif;

		endforeach;

		// Save the project gallery image IDs.
		if ( isset( $_POST['helphub_image_gallery'] ) ) :
			$attachment_ids = array_filter( explode( ',', sanitize_text_field( $_POST['helphub_image_gallery'] ) ) );
			update_post_meta( $post_id, '_helphub_image_gallery', implode( ',', $attachment_ids ) );
		endif;
	} // End meta_box_save()

	/**
	 * Customise the "Enter title here" text.
	 *
	 * @access public
	 * @since  1.0.0
	 * @param string $title The title.
	 * @return string $title
	 */
	public function enter_title_here( $title ) {
		if ( get_post_type() === $this->post_type ) :
			if ( 'post' === get_post_type() ) :
				$title = __( 'Enter the article title here', 'helphub' );
			endif;
		endif;
		return $title;
	} // End enter_title_here()

	/**
	 * Get the settings for the custom fields.
	 * Use array merge to get a unified fields array
	 * eg. $fields = array_merge( $this->get_custom_fields_post_display_settings(), $this->get_custom_fields_post_advertisement_settings(), $this->get_custom_fields_post_spacer_settings() );
	 *
	 * @access public
	 * @since  1.0.0
	 * @return array
	 */
	public function get_custom_fields_settings() {

		$fields = array();
		if ( 'post' === get_post_type() ) :
			$fields = $this->get_custom_fields_post_display_settings();
		elseif ( 'helphub_version' === get_post_type() ) :
			$fields = $this->get_custom_fields_version_display_settings();
		endif;

		return $fields;

	} // End get_custom_fields_settings()

	/**
	 * Get the settings for the post display custom fields.
	 *
	 * @access public
	 * @since  1.0.0
	 * @return array
	 */
	public function get_custom_fields_post_display_settings() {
		$fields = array();


		$fields['read_time'] = array(
			'name' => __( 'Article Read Time', 'helphub' ),
			'description' => __( 'Leave this empty, calculation is automatic', 'helphub' ),
			'type' => 'text',
			'default' => '',
			'section' => 'info',
		);

		$fields['custom_read_time'] = array(
			'name' => __( 'Custom Read Time', 'helphub' ),
			'description' => __( 'Only fill up this field if the automated calculation is incorrect', 'helphub' ),
			'type' => 'text',
			'default' => '',
			'section' => 'info',
		);

		return $fields;
	}

	/**
	 * Get the settings for the post display custom fields.
	 *
	 * @access public
	 * @since  1.0.0
	 * @return array
	 */
	public function get_custom_fields_version_display_settings() {
		$fields = array();


		$fields['version_date'] = array(
			'name'        => __( 'Date Released', 'helphub' ),
			'description' => __( 'Date this WordPress Version was released', 'helphub' ),
			'type'        => 'date',
			'default'     => '',
			'section'     => 'info',
		);

		$fields['musician_codename'] = array(
			'name'        => __( 'Musician', 'helphub' ),
			'description' => __( 'The Jazz Musician this release was named after', 'helphub' ),
			'type'        => 'text',
			'default'     => '',
			'section'     => 'info',
		);

		return $fields;
	}



	/**
	 * Get the image for the given ID.
	 *
	 * @param  int      $id   Post ID.
	 * @param  mixed    $size Image dimension. (default: "thing-thumbnail")
	 * @since  1.0.0
	 * @return string   <img> tag.
	 */
	protected function get_image( $id, $size = 'thing-thumbnail' ) {
		$response = '';

		if ( has_post_thumbnail( $id ) ) {
			// If not a string or an array, and not an integer, default to 150x9999.
			if ( ( is_int( $size ) || ( 0 < intval( $size ) ) ) && ! is_array( $size ) ) {
				$size = array( intval( $size ), intval( $size ) );
			} elseif ( ! is_string( $size ) && ! is_array( $size ) ) {
				$size = array( 150, 9999 );
			}
			$response = get_the_post_thumbnail( intval( $id ), $size );
		}

		return $response;
	} // End get_image()

	/**
	 * Register image sizes.
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function register_image_sizes() {
		if ( function_exists( 'add_image_size' ) ) {
			//add_image_size( $this->post_type . '-thumbnail', 150, 9999 ); // 150 pixels wide (and unlimited height)
		}
	} // End register_image_sizes()

	/**
	 * Run on activation.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	public function activation() {
		$this->flush_rewrite_rules();
	} // End activation()

	/**
	 * Flush the rewrite rules
	 *
	 * @access public
	 * @since 1.0.0
	 */
	private function flush_rewrite_rules() {
		$this->register_post_type();
		flush_rewrite_rules();
	} // End flush_rewrite_rules()

	/**
	 * Ensure that "post-thumbnails" support is available for those themes that don't register it.
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function ensure_post_thumbnails_support() {
		if ( ! current_theme_supports( 'post-thumbnails' ) ) { add_theme_support( 'post-thumbnails' ); }
	} // End ensure_post_thumbnails_support()

	/**
	 * Add menu order
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function add_menu_order() {
		add_post_type_support( 'post', 'page-attributes' );
	} // End ens

} // End Class
