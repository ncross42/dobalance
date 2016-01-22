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

add_action( 'add_meta_boxes', 'dob_add_meta_boxes' );
function dob_add_meta_boxes() {
	add_meta_box( 'dob_cmb_pros', __( 'Pros', DOBslug ), 'dob_cmb_pros_html', 'offer', 'normal', 'high' );
	add_meta_box( 'dob_cmb_cons', __( 'Cons', DOBslug ), 'dob_cmb_cons_html', 'offer', 'normal', 'high' );
	add_meta_box( 'dob_cmb_vote', __( 'Voting Method', DOBslug ), 'dob_cmb_vote_html', 'offer', 'normal', 'high' );
}

function dob_cmb_text_area ( $post_id, $name ) {/*{{{*/
	$text = __($name, DOBslug );
	$content = get_post_meta($post_id, $name, true);
	return <<<HTML
		<!--label for="$name">$text</label><br /--><textarea style="width:95%;" ROWS=5 name="$name">$content</textarea>
HTML;
}/*}}}*/
// echo '<input type="text" name="new_field" value="'.esc_attr($value).'" size="25" />';
function dob_cmb_pros_html($post) { echo dob_cmb_text_area($post->ID,'dob_cmb_pros'); }
function dob_cmb_cons_html($post) { echo dob_cmb_text_area($post->ID,'dob_cmb_cons'); }

function dob_cmb_vote_html( $post ) {
	// Add a nonce field so we can check for it later.
	wp_nonce_field( 'dob_meta_box_nonce', 'dob_cmb_nonce' );

	$dob_cmb_vote_json = get_post_meta( $post->ID, 'dob_cmb_vote_json', true );

	echo '<label for="dob_new_field">';
	_e( 'Description for this field', DOBslug );
	echo '</label> ';
}

add_action( 'save_post', 'dob_save_cmb_data' );
function dob_save_cmb_data( $post_id ) {
	// Check Environments
	if ( empty($_POST['post_type']) || 'offer'!=$_POST['post_type'] 
		|| empty($_POST['dob_cmb_nonce']) || ! wp_verify_nonce($_POST['dob_cmb_nonce'],'dob_meta_box_nonce') 
		|| ! current_user_can('edit_post',$post_id)
		|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
		|| empty($_POST['dob_cmb_vote'])
	) return;

	//$my_data = sanitize_text_field( $_POST['dob_new_field'] ); // Sanitize user input.

	// https://codex.wordpress.org/Function_Reference/update_post_meta
	// Update the meta field in the database.
	update_post_meta( $post_id, 'dob_cmb_pros', $_POST['dob_cmb_pros'] );
	update_post_meta( $post_id, 'dob_cmb_cons', $_POST['dob_cmb_cons'] );
}
