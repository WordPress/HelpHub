<?php

/**
 * The front page of the site.
 *
 * @package WPBBP
 */

get_header(); ?>

	<main id="main" class="site-main" role="main">

		<?php if ( ! is_active_sidebar( 'front-page-blocks' ) ) : ?>
			<?php get_template_part( 'template-parts/bbpress', 'front' ); ?>
		<?php else : ?>
			<div class="three-up helphub-front-page">
				<?php dynamic_sidebar( 'front-page-blocks' ); ?>
			</div>
		<?php endif; ?>

	</main>

<?php
get_footer();
