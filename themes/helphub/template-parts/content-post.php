<?php
/**
 * Template part for displaying page content in page.php.
 *
 * @Author Jon Ang
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package HelpHub
 */

?>
<div class="o2-posts">
	<article id="post-<?php the_ID(); ?>" <?php post_class( array( 'handbook' ) ); ?>>

		<div class="o2-post">
			<header class="entry-header">
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
			</header><!-- .entry-header -->

			<div class="entry-content">
				<?php
					the_content();

					wp_link_pages( array(
						'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'helphub' ),
						'after'  => '</div>',
					) );
				?>
			</div><!-- .entry-content -->

			<footer class="entry-footer">
				<?php
					edit_post_link(
						sprintf(
							/* translators: %s: Name of current post */
							esc_html__( 'Edit %s', 'helphub' ),
							the_title( '<span class="screen-reader-text">"', '"</span>', false )
						),
						'<span class="edit-link">',
						'</span>'
					);
				?>
				<span class="read-time">
					<?php helphub_wrap_the_read_time(); ?>
				</span>
			</footer><!-- .entry-footer -->
		</div><!-- .o2-post -->

	</article><!-- #post-## -->
</div><!-- .o2-posts -->
