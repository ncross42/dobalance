<?php

if ( empty($dob_options) ) {
	$dob_options = get_option('dobalance-settings');
}

function dob_get_hierarchy_rows($bJson = false) {
	global $wpdb, $dob_options;
	$root = $dob_options['dobalance_root_hierarchy'];
	$ret = array();

	$sql = "SELECT term_taxonomy_id AS ttid, lvl, chl
			, slug, name 
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
function dob_user_hierarchy_profile( $user, $get_tr=false ) {
	$target_user_id = $user->ID;

	// HTML5 required attribute.
	$required_html = __( '(required)' );

	$selected = dob_get_user_hierarchy_id($target_user_id);
	$options = "\t<option value='0'>선택안함</option>";
	foreach ( dob_get_hierarchy_rows() as $r ) {	// lvl, slug, name 
		$options .= sprintf(
			"\n\t<option value='{$r->ttid}' %s %s>%s</option>",
			( is_super_admin() || empty($r->chl) ) ? '' : 'DISABLED style="background-color:#eee;"',
			($selected==$r->ttid) ? 'selected' : '',
			str_repeat('&nbsp;',4*($r->lvl)).$r->name
		);
	}

	$label_dob = '균형 직접 민주주의 시스템'; // __( 'DoBalance', DOBslug );
	$label_title = '소속 계층'; // __( 'User Position', DOBslug );
	$html_tr = <<<HTML
		<tr>
			<th><label for='dob_user_hierarchy'>$label_title $required_html</label></th>
			<td>
				<select name='dob_user_hierarchy' >
					$options
				</select>
				<span class="description">활동지역을 선택해주세요 (대표직은 관리자가 임명합니다.)</span>
			</td>
		</tr>
HTML;
	if ( $get_tr ) {
		return $html_tr;
	}

echo <<<HTML
	<h3>$label_dob</h3>
	<table class="form-table">
		$html_tr
	</table>
HTML;
}

function dob_user_hierarchy_recalc_inf( $target_id, $source_id=0 ) {
	global $wpdb;

	$t_term_taxonomy = $wpdb->prefix.'term_taxonomy';

	// update target term_taxonomy
	$sql = "SELECT lft, rgt FROM $t_term_taxonomy WHERE term_taxonomy_id = $target_id";
	$target = $wpdb->get_row($sql);
	$sql = "UPDATE $t_term_taxonomy SET inf = inf + 1
		WHERE taxonomy='hierarchy' AND lft < {$target->lft} AND rgt > {$target->rgt}";
	$wpdb->query($sql);

	// update source term_taxonomy
	if ( ! empty($source_id) ) {
		$sql = "SELECT lft, rgt FROM $t_term_taxonomy WHERE term_taxonomy_id = $source_id";
		$source = $wpdb->get_row($sql);
		$sql = "UPDATE $t_term_taxonomy SET inf = inf - 1
			WHERE taxonomy='hierarchy' AND lft < {$source->lft} AND rgt > {$source->rgt}";
		$wpdb->query($sql);
	}
}

/* save the category selections from admin */
add_action( 'personal_options_update', 'dob_user_hierarchy_update' );
add_action( 'edit_user_profile_update', 'dob_user_hierarchy_update' );
function dob_user_hierarchy_update( $target_user_id ) {
	global $wpdb;

	$login_user_id = get_current_user_id();
	$t_user_category = $wpdb->prefix.'dob_user_category';
	$t_term_taxonomy = $wpdb->prefix.'term_taxonomy';

	$term_taxonomy_id = $_POST['dob_user_hierarchy'];
	$sql = "SELECT term_taxonomy_id 
		FROM $t_user_category
		WHERE taxonomy='hierarchy' AND user_id=".(int)$target_user_id;
	$old_term_taxonomy_id = $wpdb->get_var($sql);

	if ( empty($old_term_taxonomy_id) ) {
		$wpdb->insert( $t_user_category,
			array( 'taxonomy'=>'hierarchy', 'user_id'=>$target_user_id, 'term_taxonomy_id'=>$term_taxonomy_id ),
			array( '%s', '%d', '%d' )
		);
	} else if ( $term_taxonomy_id != $old_term_taxonomy_id ) {
		$sql = "SELECT chl FROM $t_term_taxonomy WHERE term_taxonomy_id = $term_taxonomy_id";
		$chl = $wpdb->get_var($sql);
		if ( ! is_super_admin() && ! empty($chl) ) {
			$label_error = '잘못된 접근입니다'; // __( 'Forbidden Access.', DOBslug );
			wp_die("<script>alert('$label_error');history.go(-1);</script>");
		}

		$wpdb->update( $t_user_category,
			array( 'term_taxonomy_id'=>$term_taxonomy_id ),
			array( 'taxonomy'=>'hierarchy','user_id'=>$target_user_id, ),
			array( '%d' ),
			array( '%s', '%d' )
		);
	}

	dob_user_hierarchy_recalc_inf($term_taxonomy_id,$old_term_taxonomy_id);
}
