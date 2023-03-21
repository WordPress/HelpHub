<?php
/**
 * Template Name: WordPress Versions
 */

get_header(); ?>

	<main id="main" class="site-main" role="main">

		<h1><?php _e( 'Wordpress Versions', 'wporg-forums' ); ?></h1>

		<div class="entry-content">
			<section>
				<p><?php _e( 'The following are the various versions of WordPress, listed chronologically from the oldest to newest, along with the Change Log information on new features and improvements in each version.', 'wporg-support' ); ?></p>

				<p><?php _e( 'NOTE: WordPress core developers share a love of jazz music, and since WordPress 1.0 all major releases are named in honor of jazz musicians they admire.', 'wporg-support' ); ?></p>
				<div class="container">
				<?php
				$args = array(
					'post_type'      => 'helphub_version',
					'posts_per_page' => -1,
					'orderby'        => 'name',
					'order'          => 'ASC',
				);
				$query_versions = new WP_Query( $args );
				if ( $query_versions->have_posts() ) : 
				?>
					<table class="helphub_versions_table">
						<thead>
							<tr>
								<th><?php _e( 'Version', 'wporg-support' ); ?></th>
								<th><?php _e( 'Release Date', 'wporg-support' ); ?></th>
								<th><?php _e( 'Major release', 'wporg-support' ); ?></th>
								<th><?php _e( 'Jazz Musician', 'wporg-support' ); ?></th>
							</tr>
						</thead>
					<?php while ( $query_versions->have_posts() ) : $query_versions->the_post(); ?>
						<?php
						// Get branch for this version
						$terms = get_the_terms( get_the_ID(), 'helphub_major_release' );
						$major_version = '';
						if ( $terms && ! is_wp_error( $terms ) ) : 
							$major_versions_array = array();
							foreach ( $terms as $term ) {
								$major_versions_array[] = $term->name;
    						}
							$major_version = join( ', ', $major_versions_array );
						endif; 
						// Set "helphub_versions_major" class if this is a major version
						$major_class = '';
						if ( $major_version === str_replace( 'Version ', '', get_the_title() ) ) { $major_class = ' class="helphub_versions_major"'; }
						?>
						<tbody>
							<tr<?php echo $major_class; ?>>
								<td><a href="<?php the_permalink(); ?>"><?php echo str_replace( 'Version ', '', get_the_title() ); ?></a></td>
								<td><?php if ( get_post_meta( get_the_ID(), '_version_date', true ) ) { echo date_i18n( get_option( 'date_format' ), get_post_meta( get_the_ID(), '_version_date', true ) ); } ?>
								</td>
								<td><?php echo $major_version; ?></td>
								<td><?php if ( get_post_meta( get_the_ID(), '_musician_codename', true ) ) { echo get_post_meta( get_the_ID(), '_musician_codename', true ); } ?>
								</td>
							</tr>
						</tbody>
					<?php endwhile; ?>
					</table>
				<?php
				endif;
				wp_reset_postdata();
				?>
				</div>
			</section>
		</div><!-- .entry-content -->

	</main>

<?php
get_footer();

