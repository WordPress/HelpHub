<?php
/**
 * The Footer for our theme.
 *
 * @package WPBBP
 */
?>

</div><!-- #content -->

<?php
if ( stristr( WPORGPATH, 'http' ) ) {
	do_action( 'wp_footer' );
}

require WPORGPATH . 'footer.php';
