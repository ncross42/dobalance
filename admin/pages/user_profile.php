<?php

/*if ( empty($dob_options) ) {
	$dob_options = get_option('dobalance-settings');
	$root = $dob_options['dobalance_root_hierarchy'];
}*/

function dob_get_user_categories($taxonomy) {/*{{{*/
	global $wpdb;
	$sql = "SELECT term_taxonomy_id AS ttid, lvl, chl
			, slug, name 
		FROM {$wpdb->prefix}term_taxonomy JOIN {$wpdb->prefix}terms USING (term_id) 
		WHERE taxonomy = '$taxonomy'
		ORDER BY lft";
	$ret = $wpdb->get_results( $sql/*, ARRAY_A*/ );
	return empty($ret) ? array() : $ret;
}/*}}}*/

function dob_get_user_category_tid( $user_id, $taxonomy ) {/*{{{*/
	global $wpdb;
	$sql = "SELECT term_taxonomy_id
		FROM {$wpdb->prefix}dob_user_category
		WHERE taxonomy='$taxonomy' AND user_id = ".$user_id;
	$ret = $wpdb->get_col( $sql );
	return empty($ret) ? array() : $ret;
}/*}}}*/

/*
	Display and save data in admin setting !
*/
add_action( 'show_user_profile', 'dob_admin_profile' );
add_action( 'edit_user_profile', 'dob_admin_profile' );
function dob_admin_profile( $user ) {/*{{{*/
	dob_admin_multiple_select_scripts();
	$target_user_id = $user->ID;

	$tr_hierarchy = dob_admin_user_hierarchy($target_user_id);
	$tr_group = dob_admin_user_group($target_user_id);

	$label_dob = '균형 직접 민주주의 시스템'; // __( 'DoBalance', DOBslug );
	echo <<<HTML
	<h3>$label_dob</h3>
	<table class="form-table">
		$tr_hierarchy
		$tr_group
	</table>
HTML;
}/*}}}*/

function dob_admin_user_group( $target_user_id ) {/*{{{*/
	$myVals = dob_get_user_category_tid($target_user_id,'group');
	$options = '';
	$rows= dob_get_user_categories('group');
	foreach ( $rows as $r ) {
		if ( $r->lvl == 0 ) continue;
		$selected = in_array($r->ttid,$myVals) ? 'selected' : '';
		$options .= "\n\t<option value='{$r->ttid}' $selected>{$r->name}</option>";
	}

	$label_title = '그룹 자유위임'; // __( 'Group Delegation', DOBslug );
	$label_desc = '활동/지지 그룹에 자유롭게 위임해주세요'; // __( 'Please Delegate to Groups Freely', DOBslug );
	$label_restrict = '단, 대표직의 자유위임 설정은 무시됩니다. (순환위임 방지)'; // __( 'If you're a delegator, all these setting is ignored, for prevent Circular Delegation', DOBslug );
	return $html = <<<HTML
		<tr>
			<th><label for='dob_user_groups'>$label_title</label></th>
			<td>
				<span class="description">$label_desc</span> <br>
				<select id="dob_user_groups" name='dob_user_groups[]' multiple style="width:400px">
					$options
				</select>
				<br> <span class="description">$label_restrict</span>
			</td>
		</tr>
<script>
(function($) {
	"use strict";
	$(function() {
		$('#dob_user_groups').multipleSelect();
	});
}(jQuery));
</script>
HTML;

}/*}}}*/

function dob_admin_user_hierarchy( $target_user_id ) {/*{{{*/
	// HTML5 required attribute.
	$required_html = __( '(required)' );

	$myVals = dob_get_user_category_tid($target_user_id,'hierarchy');
	$options = "\t<option value='0'>선택안함</option>";
	// hierarchy
	foreach ( dob_get_user_categories('group') as $r ) {
		$options .= sprintf(
			"\n\t<option value='{$r->ttid}' %s %s>%s</option>",
			in_array($r->ttid,$myVals) ? 'selected' : '',
			( is_super_admin() && !empty($r->lvl) ) ? '' : 'DISABLED style="background-color:#eee;"',
			str_repeat('&nbsp;',4*($r->lvl)).$r->name
		);
	}

	// hierarchy
	foreach ( dob_get_user_categories('hierarchy') as $r ) {
		$options .= sprintf(
			"\n\t<option value='{$r->ttid}' %s %s>%s</option>",
			in_array($r->ttid,$myVals) ? 'selected' : '',
			( is_super_admin() || empty($r->chl) ) ? '' : 'DISABLED style="background-color:#eee;"',
			str_repeat('&nbsp;',4*($r->lvl)).$r->name
		);
	}

	$label_title = '소속 계층'; // __( 'User Position', DOBslug );
	$label_desc = '소속을 선택해주세요 (대표직은 관리자가 지정합니다)'; // __( 'User Position', DOBslug );
	return $html_tr = <<<HTML
		<tr>
			<th><label for='dob_user_hierarchy'>$label_title $required_html</label></th>
			<td>
				<select name='dob_user_hierarchy' >
					$options
				</select>
				<span class="description">$label_desc</span>
			</td>
		</tr>
HTML;

}/*}}}*/

function dob_user_hierarchy_recalc_inf( $target_id, $source_id=0 ) {/*{{{*/
	global $wpdb;
	$t_term_taxonomy = $wpdb->prefix.'term_taxonomy';

	// update target's higher hierarchy inf
	if ( ! empty($target_id) ) {
		$sql = "SELECT lft, rgt FROM $t_term_taxonomy WHERE term_taxonomy_id = $target_id";
		$target = $wpdb->get_row($sql);
		$sql = "UPDATE $t_term_taxonomy SET inf = inf + 1
			WHERE taxonomy='hierarchy' AND lft < {$target->lft} AND rgt > {$target->rgt}";
		$wpdb->query($sql);
	}

	// update source's higher hierarchy inf
	if ( ! empty($source_id) ) {
		$sql = "SELECT lft, rgt FROM $t_term_taxonomy WHERE term_taxonomy_id = $source_id";
		$source = $wpdb->get_row($sql);
		$sql = "UPDATE $t_term_taxonomy SET inf = inf - 1
			WHERE taxonomy='hierarchy' AND lft < {$source->lft} AND rgt > {$source->rgt}";
		$wpdb->query($sql);
	}
}/*}}}*/

/* save the category selections from admin */
add_action( 'personal_options_update', 'dob_admin_profile_update' );
add_action( 'edit_user_profile_update','dob_admin_profile_update' );
function dob_admin_profile_update( $target_user_id ) {/*{{{*/
	dob_admin_user_hierarchy_update($target_user_id);
	dob_admin_user_group_update($target_user_id);
}/*}}}*/

function dob_admin_user_hierarchy_update( $target_user_id ) {/*{{{*/
	global $wpdb;

	if ( ! is_numeric($_POST['dob_user_hierarchy']) ) return;

	$ttid = (int)$_POST['dob_user_hierarchy'];
	$login_user_id = get_current_user_id();
	$t_user_category = $wpdb->prefix.'dob_user_category';
	$t_term_taxonomy = $wpdb->prefix.'term_taxonomy';

	$sql = "SELECT term_taxonomy_id 
		FROM $t_user_category
		WHERE taxonomy='hierarchy' AND user_id=".(int)$target_user_id;
	$old_term_taxonomy_id = $wpdb->get_var($sql);

	if ( $ttid == $old_term_taxonomy_id ) {
		return;
	} elseif ( is_null($old_term_taxonomy_id) ) {
		$wpdb->insert( $t_user_category,
			array( 'taxonomy'=>'hierarchy', 'user_id'=>$target_user_id, 'term_taxonomy_id'=>$ttid ),
			array( '%s', '%d', '%d' )
		);
	} else if ( $ttid != $old_term_taxonomy_id ) {
		$sql = "SELECT chl FROM $t_term_taxonomy WHERE term_taxonomy_id = $ttid";
		$chl = $wpdb->get_var($sql);
		if ( ! is_super_admin() && ! empty($chl) ) {
			$label_error = '잘못된 접근입니다'; // __( 'Forbidden Access.', DOBslug );
			wp_die("<script>alert('$label_error');history.go(-1);</script>");
		}

		$wpdb->update( $t_user_category,
			array( 'term_taxonomy_id'=>$ttid ),
			array( 'taxonomy'=>'hierarchy','user_id'=>$target_user_id, ),
			array( '%d' ),
			array( '%s', '%d' )
		);
	}

	dob_user_hierarchy_recalc_inf($ttid,$old_term_taxonomy_id);
}/*}}}*/

function dob_admin_user_group_update( $target_user_id ) {/*{{{*/
	global $wpdb;

	$dob_user_groups = empty($_POST['dob_user_groups']) ? array() : $_POST['dob_user_groups'];
	$old_ids = dob_get_user_category_tid($target_user_id,'group');
	$del_ids = array_diff($old_ids,$dob_user_groups);
	$new_ids = array_diff($dob_user_groups,$old_ids);

	#var_dump( '<pre>', $dob_user_groups, $old_ids, $del_ids, $new_ids, '</pre>' );

	$t_category = $wpdb->prefix.'dob_user_category';
	if ( !empty($del_ids) ) {
		$list_ids = implode(', ',$del_ids);
		$sql = "DELETE FROM $t_category
			WHERE taxonomy='group' AND user_id=$target_user_id
				AND term_taxonomy_id IN ( $list_ids )";
		$wpdb->query($sql);
	}
	if ( !empty($new_ids) ) {
		$sql = "INSERT INTO $t_category
			( user_id, taxonomy, term_taxonomy_id ) VALUES ";
		$many = array();
		foreach ( $new_ids as $id ) {
			$many[] = "\n\t( $target_user_id, 'group', $id )";
		}
		$sql .= implode(',',$many);
		$wpdb->query($sql);
	}
}/*}}}*/

