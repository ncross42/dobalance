<?php

add_action( 'admin_enqueue_scripts', 'restrict_user_form_enqueue_scripts' );
function restrict_user_form_enqueue_scripts($hook) {
	if ( ! in_array($hook, array('profile.php', 'user-edit.php' )))
		return;
	wp_enqueue_script('jquery');
	wp_enqueue_script( 'jquery.multiple.select', plugin_dir_url( __FILE__ ) . 'assset/jquery.multiple.select.js' );
	wp_register_style( 'jquery.multiple.select_css', plugin_dir_url( __FILE__ ) . 'assset/multiple-select.css', false, '1451533637' );
	wp_enqueue_style( 'jquery.multiple.select_css' );
}

/*
	Display and save data in admin setting !
*/
add_action( 'show_user_profile', 'dob_user_hierarchy_profile' );
add_action( 'edit_user_profile', 'dob_user_hierarchy_profile' );
function dob_user_hierarchy_profile( $user ) {
	// A little security
	if ( ! current_user_can('add_users'))
		return false;
	$args = array(
		'show_option_all'    => '',
		'orderby'            => 'ID', 
		'order'              => 'ASC',
		'show_count'         => 0,
		'hide_empty'         => 0,
		'child_of'           => 0,
		'exclude'            => '',
		'echo'               => 0,
		'hierarchical'       => 1, 
		'name'               => 'allow',
		'id'                 => '',
		'class'              => 'postform',
		'depth'              => 0,
		'tab_index'          => 0,
		'taxonomy'           => 'category',
		'hide_if_empty'      => false,
		'walker'             => ''
	);

	$dropdown = wp_dropdown_categories($args);
	// We are going to modify the dropdown a little bit.
	$dom = new DOMDocument();
	/*
		@http://ordinarygentlemen.co.uk
		There's an error here, while using PHP 5.4 not support LIBXML_HTML_NOIMPLIED or LIBXML_HTML_NODEFDTD
		Vietnamese error, So fixed it by adding mb_convert_encoding() !
	*/
	//$dom->loadHTML($dropdown, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	$dom->loadHTML( mb_convert_encoding($dropdown, 'HTML-ENTITIES', 'UTF-8') );
	$xpath = new DOMXpath($dom);
	$selectPath = $xpath->query("//select[@id='allow']");

	if ($selectPath != false) {
		// Change the name to an array.
		$selectPath->item(0)->setAttribute('name', 'allow[]');
		// Allow multi select.
		$selectPath->item(0)->setAttribute('multiple', 'yes');
		
		$selected = get_user_meta( $user->ID, 'dob_hierarchy', true);
		// Flag as selected the categories we've previously chosen
		// Do not throught error in user's screen ! // @JamViet
		if ( $selected )
		foreach ($selected as $term_id) {
			// fixed delete category make error !
			if (!empty($term_id) && get_the_category_by_ID($term_id) ){
				$option = $xpath->query("//select[@id='allow']//option[@value='$term_id']");
				$option->item(0)->setAttribute('selected', 'selected');
			}
		}
	}
?>

	<h3>DoBalance User Position in Hierarchy</h3>
	<table class="form-table">
		<tr>
			<th><label for="access">Select categories:</label></th>
			<td>
				<?php echo $dom->saveXML($dom);?>
				<span class="description">Author restriced to post selected categories only.</span>
			</td>
		</tr>
	</table>

	<table class="form-table">
		<tr>
			<th><label for="access">Restrict using his/her own file in Media</label></th>
			<td>
					<fieldset>
					<legend class="screen-reader-text"><span>Restrict using his/her own file in Media</span></legend>
					<label for="_restrict_media">
					<input type="checkbox" <?php checked (get_user_meta($user->ID, '_restrict_media', true), 1, 1 ) ?> value="1" id="_restrict_media" name="_restrict_media">
				Whenever it checked, Author can only use his/her own file (image/video) in Media</label>
					</fieldset>
			</td>
		</tr>
	</table>
	<script>
	<!--
		jQuery('select#allow').multipleSelect();
	-->
	</script>
<?php 
}

/* save the category selections from admin */
add_action( 'personal_options_update', 'dob_user_hierarchy_update' );
add_action( 'edit_user_profile_update', 'dob_user_hierarchy_update' );
function dob_user_hierarchy_update( $user_id ) {
	// check security
	if ( ! current_user_can( 'add_users' ) )
		return false;
	// admin can not restrict himself
	if ( get_current_user_id() == $user_id )
		return false;
	// and last, save it 
	if ( ! empty ($_POST['_restrict_media']) ) {
		update_user_meta( $user_id, '_restrict_media', $_POST['_restrict_media'] );
	} else {
		delete_user_meta( $user_id, '_restrict_media' );
	}
	if ( ! empty ($_POST['allow']) ) {
		update_user_meta( $user_id, 'dob_hierarchy', $_POST['allow'] );
	} else  {
		delete_user_meta( $user_id, 'dob_hierarchy' );
	}
}
