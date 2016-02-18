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
			<h2 class="helphub-footerarea-title">Join the Conversation and Learn More</h2>
			<div class="helphub-footerarea1">
				<h3 class="helphub-footerarea-title">WordPress Forums</h3>
				<div class="helphub-footerarea-body">Join the broader WordPress community via the online Forums. You can ask questions or offer answers to other users' questions.</div>
				<button>Ask a question</button>
			</div>
			<div class="helphub-footerarea2">
				<h3 class="helphub-footerarea-title">WordPress Documentation</h3>
				<div class="helphub-footerarea-body">Read the WordPress documentation to get started with WordPress and complete quick tutorials.</div>
				<button>View documentation</button>
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
