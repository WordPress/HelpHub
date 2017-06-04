<?php
/**
 * Help functions
 *
 * @package Helphub Search
 */

add_action( 'wp_ajax_se_lookup', 'se_lookup' );
add_action( 'wp_ajax_nopriv_se_lookup', 'se_lookup' );

/**
 * Return formated search results for the auto complete feature.
 *
 * @todo This should not be open to general probing. We need to add a nonce check to it.
 */
function se_lookup() {

	global $wpdb;
	$restrictions = apply_filters('helphub_search_restrictions', array(
		'installing-wordpress',
		'setting-up-wordpress',
	));

	$request_term = $_REQUEST['term'];
	if( ctype_space( $request_term ) ){
		return;
	}

	$query_sql = "
        SELECT {$wpdb->prefix}posts.ID AS id,{$wpdb->prefix}posts.post_title AS value,{$wpdb->prefix}terms.name AS cat,{$wpdb->prefix}terms.slug AS slug
        FROM {$wpdb->prefix}posts 
        INNER JOIN {$wpdb->prefix}term_relationships 
        ON {$wpdb->prefix}term_relationships.object_id={$wpdb->prefix}posts.ID 
        INNER JOIN {$wpdb->prefix}term_taxonomy 
        ON {$wpdb->prefix}term_relationships.term_taxonomy_id={$wpdb->prefix}term_taxonomy.term_taxonomy_id
        INNER JOIN {$wpdb->prefix}terms
        ON {$wpdb->prefix}term_taxonomy.term_id={$wpdb->prefix}terms.term_id
        WHERE {$wpdb->prefix}term_taxonomy.taxonomy='category'
        AND {$wpdb->prefix}posts.post_title LIKE '%%%s%%' 
    ";

	if ( is_array( $restrictions ) ) {
		$category_count = count( $restrictions );
		for ( $x = 0; $x < $category_count; $x++ ) {
			$com = $x > 0 ? 'OR ' : 'AND ';
			$query_sql .= "\n" . $com . " {$wpdb->prefix}terms.slug = '" . $restrictions[ $x ]."'";
		}
	}
	$query_sql .= " GROUP BY {$wpdb->prefix}posts.ID";
	$query_sql .= ' LIMIT 5';

    $prepare_query = $wpdb->prepare( $query_sql, $request_term );
    $results = $wpdb->get_results( $prepare_query, ARRAY_A );

	$response = array();
	if ( $results ) {
		foreach ( $results as $result ) {
			$result['label'] = $result['value'] . ' in <strong>'.$result['cat'].'</strong>';
			$response[] = $result;
		}
	}

	echo( json_encode( $response ) );
	exit;
}
