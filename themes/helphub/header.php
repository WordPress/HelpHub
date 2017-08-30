<?php
/**
 * The header for our theme.
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package HelpHub
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">

	<?php wp_head(); ?>
</head>

<body <?php body_class( array( 'single-handbook', 'make-docs' ) ); ?>>
<div id="page" class="">
	<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'helphub' ); ?></a>


	<div class="wporg-header">
		<?php require WPORGPATH . 'header.php'; ?>
	</div><!-- .site-branding -->

	<header id="masthead" class="site-header" role="banner">
		<div class="site-branding">
			<div class="site-title">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>
			</div>
		</div>
		<nav id="site-navigation" class="navigation-main clear" role="navigation">
			<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false"><?php esc_html_e( 'Primary Menu', 'helphub' ); ?></button>
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'menu_id' => 'primary-menu',
			) );
			?>
		</nav><!-- #site-navigation -->
	</header><!-- #masthead -->
	<div id="page" class="hfeed site">
		<div id="main" class="site-main clear">
			<?php get_sidebar(); ?>
