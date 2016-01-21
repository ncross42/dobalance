<?php

if ( empty($dob_options) ) {
	$dob_options = get_option('dobalance-settings');
}

function dob_get_hierarchy_rows($bJson = false) {
	global $wpdb, $dob_options;
	$root = $dob_options['dobalance_root_hierarchy'];
	$ret = array();

	$sql = "SELECT term_taxonomy_id, lvl, slug, name 
			/* CONCAT( REPEAT('\t',lvl), slug ) AS slug_full */
		FROM {$wpdb->prefix}term_taxonomy JOIN {$wpdb->prefix}terms USING (term_id) 
		WHERE taxonomy = 'hierarchy'
		ORDER BY lft";

	$ret = $wpdb->get_results( $sql/*, ARRAY_A*/ );

	return $bJson ? json_encode($ret,JSON_UNESCAPED_UNICODE) : $ret;
}

function dob_get_user_hierarchy_id( $user_id ) {
	global $wpdb, $dob_options;
	$ret = array();

	$sql = "SELECT term_taxonomy_id
		FROM {$wpdb->prefix}dob_user_category
		WHERE taxonomy='hierarchy' AND user_id = ".$user_id;

	$ret = $wpdb->get_var( $sql );
	return $ret;
}

/*
	Display and save data in admin setting !
*/
add_action( 'show_user_profile', 'dob_user_hierarchy_profile' );
add_action( 'edit_user_profile', 'dob_user_hierarchy_profile' );
function dob_user_hierarchy_profile( $user ) {
	$target_user_id = $user->ID;

	// HTML5 required attribute.
	$required_html = __( '(required)' );

	$selected = dob_get_user_hierarchy_id($target_user_id);
	$options = "\t<option value='0'>선택안함</option>";
	foreach ( dob_get_hierarchy_rows() as $row ) {	// lvl, slug, name 
		$options .= sprintf(
			"\n\t<option value='%s' %s>%s</option>",
			$row->term_taxonomy_id,
			($selected==$row->term_taxonomy_id)? 'selected="selected"':'',
			str_repeat('&nbsp;',4*($row->lvl)).$row->name
		);
	}

echo <<<HTML
	<h3>DoBalance User Position in Hierarchy</h3>
	<table class="form-table">
		<tr>
			<th><label for='dob_user_hierarchy'>Hierarchy_Position $required_html</label></th>
			<td>
				<select name='dob_user_hierarchy' >
					$options
				</select>
				<span class="description">전체 계층에서 본인의 지위를 선택해주세요 (대표성이 없으면 거주지역 선거구 선택)</span>
			</td>
		</tr>
	</table>
HTML;
}

/* save the category selections from admin */
add_action( 'personal_options_update', 'dob_user_hierarchy_update' );
add_action( 'edit_user_profile_update', 'dob_user_hierarchy_update' );
function dob_user_hierarchy_update( $target_user_id ) {
	global $wpdb;

	$login_user_id = get_current_user_id();

	$term_taxonomy_id = $_POST['dob_user_hierarchy'];
	$sql = "SELECT term_taxonomy_id 
		FROM {$wpdb->prefix}dob_user_category 
		WHERE taxonomy='hierarchy' AND user_id=".(int)$target_user_id;
	$old_term_taxonomy_id = $wpdb->get_var($sql);

	if ( empty($old_term_taxonomy_id) ) {
		$wpdb->insert( "{$wpdb->prefix}dob_user_category", 
			array( 'taxonomy'=>'hierarchy', 'user_id'=>$target_user_id, 'term_taxonomy_id'=>$term_taxonomy_id ),
			array( '%s', '%d', '%d' )
		);
	} else if ( $term_taxonomy_id != $old_term_taxonomy_id ) {
		$wpdb->update( "{$wpdb->prefix}dob_user_category", 
			array( 'term_taxonomy_id'=>$term_taxonomy_id ),
			array( 'taxonomy'=>'hierarchy','user_id'=>$target_user_id, ),
			array( '%d' ),
			array( '%s', '%d' )
		);
	}
}
