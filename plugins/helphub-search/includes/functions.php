<?php
/**
 * Functions
 */

add_action('wp_ajax_se_lookup', 'se_lookup');
add_action('wp_ajax_nopriv_se_lookup', 'se_lookup');

/**
 * Return formated search results for the auto suggest
 *
 * The search needs to be limited to taxonomies and and categories
 * 
 * 
 * @return [type] [description]
 */
function se_lookup() {
    global $wpdb;
    
    $restrictions = apply_filters('helphub_search_restrictions', array(
        'categories' => array(
            'installing-wordpress',
            'setting-up-wordpress',
            'core-functions',
            'working-with-themes',
            'all-things-plugins',
            'advanced-topics'
        )
    ));
    
    $query = "
        SELECT {$wpdb->prefix}posts.ID AS id,{$wpdb->prefix}posts.post_title AS title,{$wpdb->prefix}terms.name AS cat,{$wpdb->prefix}terms.slug AS slug
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

    if( is_array( $restrictions['categories'] ) ){
        $category_count = count( $restrictions['categories'] );
        for($x = 0; $x < $category_count; $x++ ){
            $com = $x > 0 ? 'OR ' : 'AND ';
            //$query .= "\n" . $com . " wp_terms.slug = '" . $restrictions['categories'][$x]."'";
        }
    }
    $query .= " GROUP BY {$wpdb->prefix}posts.ID";

    $prepare = $wpdb->prepare( $query, $_REQUEST['term'] );

    print json_encode( $wpdb->get_results( $prepare, ARRAY_A ) );
    exit;
}