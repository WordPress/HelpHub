<?php
/**
 * The catchall archive template.
 *
 * If no specific archive layout is defined, we'll go with
 * a generic simplistic one, like this, just to actually
 * be able to show some content.
 *
 * @package WPBBP
 */

get_header(); ?>


	<main id="main" class="site-main" role="main">
		<?php get_sidebar( 'helphub' ); ?>

		<div id="main-content">
			<?php
			while ( have_posts() ) :
				the_post();
				?>

				<?php get_template_part( 'template-parts/content', 'archive' ); ?>

			<?php endwhile; ?>

			<div class="archive-pagination">
				<?php posts_nav_link(); ?>
			</div>
		</div>
	</main>


<?php
get_footer();
