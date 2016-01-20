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
	register_taxonomy( 'hierarchy', array('offer','post'), $args );/*}}}*/

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

add_action( 'init', 'dob_register_cpt_offer' );
function dob_register_cpt_offer() {

	$singular = 'Offer';
	$plural = 'Offers';

	$labels = array(/*{{{*/
		'name'							=> __( $plural, DOBslug ),
		'singular_name'			=> __( $singular, DOBslug ),	// value of 'name'
		'menu_name'					=> __( $plural ),							// value of 'name'
		'name_admin_bar'		=> __( $singular ),							// value of 'singular_name'
		'all_items'					=> __( 'All Offers' ),				// value of 'name'
		'add_new'						=> __( 'Add New' ),
		'add_new_item'			=> _x( 'Add New', $singular, DOBslug ),
		'edit_item'					=> __( 'Edit' ),
		'new_item'					=> _x( 'New' , $singular, DOBslug ),
		'view_item'					=> _x( 'View' , $singular, DOBslug ),
		'search_items'			=> _x( 'Search', $plural, DOBslug ),
		'not_found'					=> __( "No $plural found", DOBslug ),
		'not_found_in_trash'=> __( "No $plural found in Trash", DOBslug ),
		'parent_item_colon'	=> _x( 'Parent', $singular, DOBslug ),
	);/*}}}*/

	$args = array(/*{{{*/
		'label'						=> $singular,	// Default: $post_type
		'labels'					=> $labels,
		'public'					=> true,	// effects [ publicly_queryable, show_ui, show_in_nav_menus, ]
		'show_ui'					=> true,	// effects [ show_in_menu[show_in_admin_bar] ]
		'menu_position'		=> 3,
		'menu_icon'				=> 'dashicons-testimonial',
		'capability_type'	=> 'post',
		'hierarchical'		=> true,
		'rewrite'					=> array('slug'=>'offer','with_front'=>true,'feeds'=>true,'pages'=>true),
		'query_var'				=> true,
		'supports'				=> array(
			'title', 'editor', 'excerpt', 'comments',
			'trackbacks', 'revisions', 'thumbnail',
			'custom-fields', //'author', 'page-attributes',
		),
	);/*}}}*/

	register_post_type( 'offer', $args );
}
