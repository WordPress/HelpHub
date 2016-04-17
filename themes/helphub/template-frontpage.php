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
				<!-- #search placeholder only, will be changed later on when search engine code is ready START-->
				<div class="helphub-search">
					<div class="helphub-search-box">
						<input id="helphub-search" class="text" name="search" type="text" value="" maxlength="150" placeholder="What help do you need with?">
					</div>
					<div class="helphub-search-btn">
						<button>SEARCH</button>
					</div>
				</div>
				<div class="helphub-contentarea">
					<?php
					while ( have_posts() ) : the_post();

						get_template_part( 'template-parts/content', 'page' );

						// If comments are open or we have at least one comment, load up the comment template.
						if ( comments_open() || get_comments_number() ) :
							comments_template();
						endif;

					endwhile; // End of the loop.
					?>
				</div>
				<!-- #search placeholder only, will be changed later on when search engine code is ready END-->
			</div>
			<div class="main-widget-area">
				<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
				<?php endwhile; else : ?>
<p><?php _e( 'Sorry, no posts matched your criteria.' ); ?></p>
<?php endif; ?>
			</div>
		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>
