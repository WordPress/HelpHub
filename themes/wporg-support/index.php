<?php
/**
 * Main index.php template.
 *
 * @package WPBBP
 */

get_header();

	while ( have_posts() ) : the_post();

		the_content();

	endwhile;

get_footer();
