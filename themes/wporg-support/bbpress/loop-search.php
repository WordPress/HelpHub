<?php do_action( 'bbp_template_before_search_results_loop' ); ?>

<ul id="bbp-search-results" class="forums bbp-search-results">

	<li class="bbp-body">

		<?php
			while ( bbp_search_results() ) : bbp_the_search_result();

				if ( 'topic' === get_post_type() ) :

					bbp_get_template_part( 'content', 'single-topic-lead' );

				else :

					bbp_get_template_part( 'loop', 'single-reply' );

				endif;

			endwhile;
		?>

	</li><!-- .bbp-body -->

</ul><!-- #bbp-search-results -->

<?php do_action( 'bbp_template_after_search_results_loop' );
