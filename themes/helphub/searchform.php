<?php
/**
 * The template for displaying search form
 *
 * @package HelpHub
 */
?>
<form role="search" method="get" class="search-form" action="<?php echo home_url( '/' ); ?>">
	<label class="screen-reader-text" for="s"><?php esc_html_e( 'Search for:', 'helphub' ); ?></label>
	<input type="search" class="search-field" value="" name="s" id="s" placeholder="<?php esc_attr_e( 'Search HelpHub', 'helphub' ); ?>"  />
	<button class="button button-primary button-search">
		<i class="dashicons dashicons-search"></i>
		<span class="screen-reader-text"><?php esc_attr_e( 'Search', 'helphub' ); ?></span>
	</button>
</form>