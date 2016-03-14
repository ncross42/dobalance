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

	// register_taxonomy: group /*{{{*/
	$singular	= __( 'group', DOBslug );
	$plural		= __( 'groups', DOBslug );
	$args = array (
		'hierarchical'			=> true,
		'label'							=> $plural,
		//'labels'						=> $labels,
		'public'						=> true,	// effects [ show_ui, show_in_nav_menus ]
		'show_ui'						=> true,	// effects [ show_tagcloud, show_in_quick_edit ]
		'meta_box_cb'				=> 'dob_drop_cat',	// Provide a callback function name for the meta box display, (Default: null)
		'show_admin_column'	=> true,
		'rewrite'						=> array('slug'=>$singular,'with_front'=>true,'feeds'=>true,'pages'=>true),
		'query_var'					=> true,
	);
	register_taxonomy( 'group', array('offer','post'), $args );/*}}}*/

}

//function below re-purposed from wp-admin/includes/meta-boxes.php - post_categories_meta_box()
function dob_drop_cat( $post, $box ) {
	$defaults = array( 'taxonomy' => 'group' );
	if ( ! isset( $box['args'] ) || ! is_array( $box['args'] ) ) {
		$args = array();
	} else {
		$args = $box['args'];
	}
	$r = wp_parse_args( $args, $defaults );
	$tax_name = esc_attr( $r['taxonomy'] );
	$taxonomy = get_taxonomy( $r['taxonomy'] );
?>
	<div id="taxonomy-<?php echo $tax_name; ?>" class="categorydiv">

	<?php //took out tabs for most recent here ?>

		<div id="<?php echo $tax_name; ?>-all">
<?php
	$name = ( $tax_name == 'category' ) ? 'post_category' : 'tax_input[' . $tax_name . ']';
	// Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
	echo "<input type='hidden' name='{$name}[]' value='0' />";
?>
			<ul id="<?php echo $tax_name; ?>checklist" data-wp-lists="list:<?php echo $tax_name; ?>" class="categorychecklist form-no-clear">
					<?php //wp_terms_checklist( $post->ID, array( 'taxonomy' => $tax_name, 'popular_cats' => $popular_ids ) ); ?>
			</ul>

			<?php $term_obj = wp_get_object_terms($post->ID, $tax_name ); //_log($term_obj[0]->term_id) ?>
			<?php $selected_term = isset($term_obj[0]->term_id) ? $term_obj[0]->term_id : '' ?>
			<?php wp_dropdown_categories( array( 'taxonomy' => $tax_name, 'hide_empty' => 0, 'name' => "{$name}[]", 'selected' => $selected_term, 'orderby' => 'name', 'hierarchical' => 0, 'show_option_none' => "Select $tax_name" ) ); ?>

		</div>
<?php if ( current_user_can( $taxonomy->cap->edit_terms ) ) : 
// removed code to add terms here dynamically, because doing so added a checkbox above the newly added drop menu, the drop menu would need to be re-rendered dynamically to display the newly added term ?>
		<?php endif; ?>

		<p><a href="<?php echo site_url(); ?>/wp-admin/edit-tags.php?taxonomy=<?php echo $tax_name ?>&post_type=YOUR_POST_TYPE">Add New</a></p>
	</div>
<?php
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

