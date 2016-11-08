<?php
/*
 * Plugin Name: Table Of Contents Lite
 * Version: 1.0
 * Plugin URI: http://www.carlalberto.ml
 * Description: Lightweight Table of Content Plugin. Automatically detects h1, h2, h3 tags in your post content and convert it to your table of contents.
 * Author: Carl Alberto
 * Author URI: http://www.carlalberto.ml
 * Requires at least: 4.0
 * Tested up to: 4.5
 *
 * Text Domain: table-of-contents-lite
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Carl Alberto
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Load plugin class files
require_once( 'includes/class-table-of-contents-lite.php' );

/**
 * Returns the main instance of Table_Of_Contents_Lite to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object Table_Of_Contents_Lite
 */
function Table_Of_Contents_Lite () {
	$instance = Table_Of_Contents_Lite::instance( __FILE__, '1.0.0' );

//	$filtered = Table_Of_Contents_Lite::add_toc($content);

	add_filter( 'the_content', 'add_toc' );
	//add_filter( 'the_content', $filtered );

	return $instance;
}

function add_toc( $content ) {

	$toc = '';

	$items = get_htags( 'h([1-4])', $content ); //returns the h tags inside the_content

			if ( count( $items ) < 2 ) {
				return $content;
			}

	for ( $i = 1; $i <= 4; $i++ )
		$content = add_ids_and_jumpto_links( "h$i", $content );

	if ( $items ) {
		$contents_header = 'h' . $items[0][2]; // Duplicate the first <h#> tag in the document.
		$toc .= '<div class="table-of-contents">';
		$last_item = false;
		foreach ( $items as $item ) {
			
			if ( $last_item ) {
				if ( $last_item < $item[2] )
					$toc .= "\n<ul>\n";
				elseif ( $last_item > $item[2] )
					$toc .= "\n</ul></li>\n";
				else
					$toc .= "</li>\n";
			}
			$last_item = $item[2];
			$toc .= '<li><a href="#' . sanitize_title_with_dashes($item[3])  . '">' . $item[3]  . '</a>';
		}
		$toc .= "</ul>\n</div>\n";
	}

	return $toc . $content;
}

function filtertags($start, $end, $item) {
	$regex = "/$start(.*?)$end/";
	preg_match_all($regex, $item[3], $anchor);
	return $anchor;
}

function get_htags( $tag, $content = '' ) {
	
		if ( empty( $content ) )
			$content = get_the_content();		
			preg_match_all( "/(<{$tag}>)(.*)(<\/{$tag}>)/", $content, $matches, PREG_SET_ORDER );
		return $matches;
	}

function add_ids_and_jumpto_links( $tag, $content ) {
	$items = get_tags_in_content( $tag, $content );
	$first = true;	
	$matches = array();
	$replacements = array();

	foreach ( $items as $item ) {
		$replacement = '';
		$matches[] = $item[0];
		$id = sanitize_title_with_dashes($item[2]);
		if ( ! $first ) {
			$replacement .= '<p class="toc-jump"><a href="#top">' . __( 'Top &uarr;', 'wporg' ) . '</a></p>';
		} else {
			$first = false;
		}
		$a11y_text      = sprintf( '<span class="screen-reader-text">%s</span>', $item[2] );
//		$anchor         = sprintf( '<a href="#%1$s" class="anchor"><span aria-hidden="true">#</span>%2$s</a>', $id, $a11y_text );
		$anchor         = sprintf( '<a href="#%1$s" class="anchor">%2$s</a>', $id, $a11y_text );
		$replacement   .= sprintf( '<%1$s class="toc-heading" id="%2$s" tabindex="-1">%3$s %4$s</%1$s>', $tag, $id, $item[2], $anchor );
		$replacements[] = $replacement;
	}

	if ( $replacements ) {
		$content = str_replace( $matches, $replacements, $content );
	}

	return $content;
}

	function get_tags_in_content( $tag, $content = '' ) {
		if ( empty( $content ) )
			$content = get_the_content();
		preg_match_all( "/(<{$tag}>)(.*)(<\/{$tag}>)/", $content, $matches, PREG_SET_ORDER );
		return $matches;
	}


Table_Of_Contents_Lite();
