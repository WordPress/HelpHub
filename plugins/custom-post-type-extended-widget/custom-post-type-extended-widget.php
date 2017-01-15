<?php
/**
 * Plugin Name: Custom Post Type Extended Widget
 * Version: 1.1
 * Plugin URI: https://carl.alber2.com/
 * Description: This will list and link Custom Post Type by Category inside a widget.
 * Author: Carl Alberto
 * Author URI: https://carl.alber2.com/
 * Requires at least: 4.0
 * Tested up to: 4.5
 *
 * Text Domain: custom-post-type-extended-widget
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Carl Alberto
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit;
}

add_action( 'widgets_init', 'cpte_register_widget' );

/**
 * This will list and link Custom Post Type by Category inside a widget.
 *
 * @since  1.0.0
 */
function cpte_register_widget() {
	register_widget( 'cpte_widget' );
}

/**
 * This will list and link Custom Post Type by Category inside a widget.
 *
 * @package WordPress
 * @category Plugin
 * @author Carl Alberto
 * @since 1.0.0
 */
class Cpte_Widget extends WP_Widget {

	/**
	 * Constructor function.
	 *
	 * @access public
	 * @since   1.0.0
	 */
	public function __construct() {
		parent::__construct(
			'cpte_widget',
			__( 'List Post by Category', 'custom-post-type-extended-widget' ),
			array(
				'classname'   => 'cpte_widget widget_recent_entries',
				'description' => __( 'This will list post by Category Name', 'custom-post-type-extended-widget' ),
				'before_widget' => '<li id="%1$s" class="widget %2$s">',
				'after_widget'  => '</li>',
			)
		);
	}

	/**
	 * This will list and link Custom Post Type by Category inside a widget.
	 *
	 * @since 1.0.0
	 * @static
	 * @param	string $instance filename of the plugin.
	 */
	public function form( $instance ) {
		$defaults  = array( 'title' => '', 'category' => '', 'number' => 5, 'show_date' => '' );
		$instance  = wp_parse_args( (array) $instance, $defaults );
		$title     = $instance['title'];
		$category  = $instance['category'];
		$number    = $instance['number'];
		?>

		<p>
			<label for="cpte_widget_title"><?php esc_html_e( 'Title' ); ?>:</label>
			<input type="text" class="widefat" id="cpte_widget_title" name="<?php echo esc_html( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $title ); ?>" />
		</p>

		<p>
			<label for="cpte_widget_category"><?php esc_html_e( 'Category' ); ?>:</label>
			<?php
				wp_dropdown_categories(
					array(
						'orderby'    => 'title',
						'hide_empty' => false,
						'name'       => $this->get_field_name(
						'category' ),
								'id'         => 'cpte_widget_category',
								'class'      => 'widefat',
								'selected'   => $category,
					)
				);
			?>
		</p>

		<p>
			<label for="cpte_widget_number"><?php esc_html_e( 'Number of posts to show' ); ?>: </label>
			<input type="text" id="cpte_widget_number" name="<?php echo esc_html( $this->get_field_name( 'number' ) ); ?>" value="<?php echo esc_attr( $number ); ?>" size="3" />
		</p>

		<?php

	}

	/**
	 * This prepaes the data needed by the widget.
	 *
	 * @param array    $args	data args.
	 * @param longtext $instance Instance of the widget.
	 */
	public function widget( $args, $instance ) {

		$before_widget = $args['before_widget'];
		echo $before_widget; // WPCS: XSS OK.
		$title     = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
		$category  = $instance['category'];
		$number    = $instance['number'];
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // WPCS: XSS OK.
		}
		$cat_recent_posts = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => $number,
				'cat'            => $category,
			)
		);

		if ( $cat_recent_posts->have_posts() ) {
			echo '<ul>';
			while ( $cat_recent_posts->have_posts() ) {
				$cat_recent_posts->the_post();
				echo '<li><a href="' . esc_html( get_permalink() ) . '">' . get_the_title() . '</a></li>';
			}
			echo '</ul>';
		} else {
			esc_html_e( 'No posts on that category.', 'custom-post-type-extended-widget' );
		}
		wp_reset_postdata();
		$after_widget = $args['after_widget'];
		echo $after_widget; // WPCS: XSS OK.
	}

	/**
	 * Widget update function.
	 *
	 * @version	1.0.0
	 * @since	1.0.0
	 * @param	array $new_instance 	array of the new values for update usage.
	 * @param	array $old_instance	array of the old values.
	 * @return	array of the updated widget values.
	 */
	public function update( $new_instance, $old_instance ) {

		$instance              = $old_instance;
		$instance['title']     = wp_strip_all_tags( $new_instance['title'] );
		$instance['category']  = wp_strip_all_tags( $new_instance['category'] );
		$instance['number']    = is_numeric( $new_instance['number'] ) ? intval( $new_instance['number'] ) : 5;

		return $instance;
	}

}

?>
