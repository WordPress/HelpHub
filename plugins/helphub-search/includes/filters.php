<?php
/**
 * Plugin Filters
 *
 * @author Justin Greer <justin@justin-greer.com>
 */

/**
 * Generate custom search form
 *
 * @param string $form Form HTML.
 * @return string Modified form HTML.
 */
function helphub_search_form( $search_form ) {
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
 * Key (Type) : Explanation
 * ===============================
 * 
 * - searchable (Array) : Standard post fields to search in for.
 * - taxonomies (Array) : Taxonomies that the search will be restricted to
 * - categories (Array) : Categories to include or exclude in the search ( cat => 1|0 ) 1 = include | 0 = exclude
 *  
 * 
 * @param  [type] $search [description]
 * @return [type]         [description]
 */
function helphub_search_restrictions( $restrictions ){

	$restrictions = array_unique(array_merge_recursive(
		array(
			'searchable' => array(
				'title'
			),
			'taxonomies' => array(
				'helphub_experience' => array()
			),
			'categories' => array(
				'installing-wordpress',
				'setting-up-wordpress',
				'core-functions',
				'working-with-themes',
				'all-things-plugins',
				'advanced-topics'
			),
		), $restrictions));

	return $restrictions;
}
add_filter('helphub_search_restrictions', 'helphub_search_restrictions');