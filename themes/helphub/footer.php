<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package HelpHub
 */

?>

		</div><!-- #content -->
	</div><!-- #content wrapper -->
	<div class="helphub-footerarea">
		<div class="wrapper">
			<h2 class="helphub-footerarea-title"><?php echo __( 'Join the Conversation and Learn More', 'helphub' ); ?></h2>
			<div class="helphub-footerarea1">
				<?php
					if ( is_active_sidebar( 'footer-1' ) ) {
						get_template_part( 'template-parts/widget', 'footer1' );
					}
				?>
			</div>
			<div class="helphub-footerarea2">
				<?php
					if ( is_active_sidebar( 'footer-2' ) ) {
						get_template_part( 'template-parts/widget', 'footer2' );
					}
				?>
			</div>
		</div>
	</div>
	<footer id="colophon" class="site-footer" role="contentinfo">
		<?php require WPORGPATH . 'footer.php'; ?>
	</footer><!-- #colophon -->
</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
