<?php
/**
 * Plugin Filters
 *
 * @package  Helphub Search
 */

/**
 * Generate custom search form
 *
 * @param string $search_form Form HTML.
 * @return string Modified form HTML.
 */
function helphub_search_form( string $search_form ) {
	$search_form = '<form id="hh_search_form" role="search" method="get" action="' . home_url( '/' ) . '" >
	<input type="text" value="' . get_search_query() . '" name="s" id="helphub_search_input" />
	<input type="submit" id="searchsubmit" value="'. esc_attr__( 'Search' ) .'" />
	</div>
	</form>';

	return $search_form;
}
add_filter( 'get_search_form', 'helphub_search_form' );

/**
 * The query for auto complete needs to know if there is any restrictions or special areas
 * that the query needs to query against or for.
 *
 * @param  Array $restrictions Array containing restriction.
 * @return array     Filtered array of restrictions.
 */
function helphub_search_restrictions( array $restrictions ) {

	$restrictions = array_unique( array_merge(
		array(
			'installing-wordpress',
			'setting-up-wordpress',
			'core-functions',
			'working-with-themes',
			'all-things-plugins',
			'advanced-topics',
		), $restrictions
	) );

		return $restrictions;
}
add_filter( 'helphub_search_restrictions', 'helphub_search_restrictions', 10 );
