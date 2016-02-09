<?php

add_action( 'init', 'dob_register_taxonomy' );
function dob_register_taxonomy() {

	// register_taxonomy: hierarchy /*{{{*/
	$singular	= __( 'hierarchy', DOBslug );
	$plural		= __( 'hierarchies', DOBslug );
	$args = array (
		'hierarchical'			=> true,
		'label'							=> $plural,
		//'labels'						=> $labels,
		'public'						=> true,	// effects [ show_ui, show_in_nav_menus ]
		'show_ui'						=> true,	// effects [ show_tagcloud, show_in_quick_edit ]
		'meta_box_cb'				=> null,	// Provide a callback function name for the meta box display, (Default: null)
		'show_admin_column'	=> true,
		'rewrite'						=> array('slug'=>$singular,'with_front'=>true,'feeds'=>true,'pages'=>true),
		'query_var'					=> true,
	);
	register_taxonomy( 'hierarchy', array('elect', 'offer','post'), $args );/*}}}*/

	// register_taxonomy: topic /*{{{*/
	$singular	= __( 'topic', DOBslug );
	$plural		= __( 'topics', DOBslug );
	$args = array (
		'hierarchical'			=> true,
		'label'							=> $plural,
		//'labels'						=> $labels,
		'public'						=> true,	// effects [ show_ui, show_in_nav_menus ]
		'show_ui'						=> true,	// effects [ show_tagcloud, show_in_quick_edit ]
		'meta_box_cb'				=> null,	// Provide a callback function name for the meta box display, (Default: null)
		'show_admin_column'	=> true,
		'rewrite'						=> array('slug'=>$singular,'with_front'=>true,'feeds'=>true,'pages'=>true),
		'query_var'					=> true,
	);
	register_taxonomy( 'topic', array('offer','post'), $args );/*}}}*/

}

// for valid mptt ordering
add_filter( 'get_terms_orderby', 'dob_filter_taxonomy_orderby', 10, 3 ); 
function dob_filter_taxonomy_orderby( $orderby, $args, $taxonomies ) {/*{{{*/
	foreach ( $taxonomies as $one ) {
		if ( $orderby == 't.name' &&
			( $one == 'hierarchy' || $one == 'topic' ) 
		) {
			return 'tt.lft';
		}
	}
	return $orderby;
}/*}}}*/

