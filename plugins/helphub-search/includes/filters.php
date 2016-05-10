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
    $search_form = '<form role="search" method="get" action="' . home_url( '/' ) . '" >
    <input type="text" value="' . get_search_query() . '" name="s" id="helphub_search_form" />
    <input type="submit" id="searchsubmit" value="'. esc_attr__( 'Search' ) .'" />
    </div>
    </form>';
 
    return $search_form;
}
add_filter( 'get_search_form', 'helphub_search_form' );