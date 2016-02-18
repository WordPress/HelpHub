<?php
/**
 * The front page template file.
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
				<h1 class="site-main-title">HelpHub</h1>
				<!-- #search placeholder only, will be changed later on when search engine code is ready START-->
				<div class="helphub-search">
					<div class="helphub-search-box">
						<input id="helphub-search" class="text" name="search" type="text" value="" maxlength="150" placeholder="What help do you need with?">
					</div>
					<div class="helphub-search-btn">
						<button>SEARCH</button>
					</div>
				</div>	
				<!-- #search placeholder only, will be changed later on when search engine code is ready END-->
			</div>
			<div class="main-widget-area">
				<?php for ($x=0; $x < 6; $x++) { ?> 
					<div class="main-widget-box col-3">
						<h3 class="main-widget-box-title">Installing Wordpress</h3>
						<ul>
							<li>Topic 1</li>
							<li>Topic 2</li>
							<li>Topic 3</li>
							<li>Topic 4</li>
							<li>Topic 5</li>
						</ul>	
					</div>
				<?php } ?>
			</div>
		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>