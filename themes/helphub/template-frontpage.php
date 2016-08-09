<?php
/**
 * Template Name: Frontpage
 *
 * If the user has selected a static page for their homepage, this is what will
 * appear. Learn more: http://codex.wordpress.org/Template_Hierarchy
 *
 *
 * @package components
 */
/**
 * Load pattern maker file.
 */
get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
			<div class="main-search-area">
				<h1 class="site-main-title"><?php the_title(); ?></h1>
				<div class="helphub-home-searcharea">
					<?php
						if ( is_active_sidebar( 'homewidgetsearch-1' ) ) {
							get_template_part( 'template-parts/widget', 'homesearch' );
						}
					?>
				</div>
				<div class="helphub-contentarea">
					<?php	while ( have_posts() ) : the_post(); // start the loop?>
						<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
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
							</footer><!-- .entry-footer -->
						</article><!-- #post-## -->
						<?php
							if ( comments_open() || get_comments_number() ) :
								comments_template();
							endif;
						?>
					<?php endwhile; // End of the loop. ?>
				</div>
				<!-- #search placeholder only, will be changed later on when search engine code is ready END-->
				<?php
					if ( is_active_sidebar( 'homewidgetrow-1' ) ) {
						get_template_part( 'template-parts/widget', 'homewidgets' );
					}
				?>
			</div>
		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>
