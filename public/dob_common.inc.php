<?php
/**
 * Create site pages for this plugin
 */

function dob_common_get_selected_hierarchy_leaf_ttids($post_id) {/*{{{*/
	global $wpdb;
	$t_term_relationships = $wpdb->prefix.'term_relationships';
	$t_term_taxonomy = $wpdb->prefix.'term_taxonomy';

	$sql = "SELECT term_taxonomy_id AS ttid, anc, lft, rgt, parent
		FROM $t_term_relationships 
			JOIN $t_term_taxonomy USING (term_taxonomy_id)
		WHERE taxonomy='hierarchy' AND object_id=$post_id";
		//ORDER BY lft";
	$rows = $wpdb->get_results($sql);
	$all = array();
	foreach ( $rows as $r ) {
		$all[$r->ttid] = array (
			'anc'		=> explode(',',$r->anc),
			'lft'		=> $r->lft,
			'rgt'		=> $r->rgt,
			'parent'=> $r->parent,
		);
	}
	if ( empty($all) ) return null;	// no selected
	if ( 1 == count($all) ) { // root selected
		$cur = current($all);
		if ( $cur['parent'] == '0' ) {
			return array();
		}
  }

	// branch hierarchy selected
	$filter = $all;
	foreach ( $filter as $ttid => $r ) {
		foreach ( $r['anc'] as $atid ) {
			if ( $atid && isset($filter[$atid]) ) {
				unset($filter[$atid]);
			}
		}
	}
	$arr_lft_rgt = array();
	foreach ( $filter as $ttid => $r ) {
		$arr_lft_rgt[] = " ( lft >= {$r['lft']} AND rgt <= {$r['rgt']} )";
	}
	$sql_lft_rgt = implode(" OR \n    ",$arr_lft_rgt);
	$sql = <<<SQL
SELECT term_taxonomy_id AS ttid
FROM $t_term_taxonomy
WHERE taxonomy='hierarchy' 
  AND ( 
    $sql_lft_rgt
  )
SQL;
	return $wpdb->get_col($sql);
}/*}}}*/

function dob_common_get_message($post_id,$user_id,$cpt='offer') {/*{{{*/
	$message = '투표해 주세요';		//__('Please Vote', DOBslug);
	/*if ( $ret = dob_elect_get_latest($post_id,$user_id) ) {
		$label_last = '마지막 투표';		//__('Last Voted', DOBslug);
		$message = $label_last.' : '.$ret['ts'];
  }*/
	return $message;
}/*}}}*/

function dob_common_get_latest($post_id,$user_id=0,$ttids=array()) {/*{{{*/
	global $wpdb;
	$t_elect_latest	= $wpdb->prefix.'dob_elect_latest';

	$sql = '';
	if ( empty($ttids) ) {
		$sql_user = empty($user_id) ? '' : ' AND user_id='.$user_id;
		$sql = "SELECT * FROM $t_elect_latest WHERE post_id = $post_id $sql_user";
	} else {
		$t_term_taxonomy = $wpdb->prefix.'term_taxonomy';
		$t_user_category = $wpdb->prefix.'dob_user_category';
		$sql_ttids = ' AND term_taxonomy_id IN ('.implode(',',$ttids).')';
		$sql = "SELECT *
			FROM $t_term_taxonomy
				JOIN $t_user_category USING (term_taxonomy_id,taxonomy)
				JOIN $t_elect_latest USING (user_id)
			WHERE taxonomy = 'hierarchy' AND post_id = $post_id 
				$sql_ttids";
	}
	$rows = $wpdb->get_results($sql,ARRAY_A);
	if ( $user_id ) {
		return empty($rows) ? null : $rows[0];
	} else {
		$ret = array();
		foreach ( $rows as $row ) {
			$ret[$row['user_id']] = $row;
		}
		return $ret;
	}
}/*}}}*/

function dob_common_get_user_info($user_id) {/*{{{*/
	global $wpdb;

	$sql = <<<SQL
SELECT *
FROM {$wpdb->prefix}dob_user_category
	JOIN {$wpdb->prefix}users ON user_id=ID
WHERE taxonomy='hierarchy' AND user_id=$user_id
SQL;
	return $wpdb->get_row($sql);
}/*}}}*/

function dob_common_get_users_count( $ttids = array() ) {/*{{{*/
	global $wpdb;
	$t_term_taxonomy = $wpdb->prefix.'term_taxonomy';
	$sql = "SELECT term_taxonomy_id FROM $t_term_taxonomy WHERE taxonomy='group'";
	$gr_ttids = $wpdb->get_col($sql);
	$t_user_category = $wpdb->prefix.'dob_user_category';
  $sql_ttids = empty($ttids) ? 
    'AND term_taxonomy_id NOT IN ('.implode(',',[-1=>0]+$gr_ttids).')'
		: ' AND term_taxonomy_id IN ('.implode(',',$ttids).')';
	$sql = "SELECT COUNT(1) FROM $t_user_category 
		WHERE taxonomy='hierarchy' $sql_ttids";
	return (int)$wpdb->get_var($sql);
}/*}}}*/

function dob_common_get_log($post_id,$user_id,$cpt='offer') {/*{{{*/
	global $wpdb;
	$t_log	= $wpdb->prefix.($cpt=='offer'?'dob_vote_post_log':'dob_elect_log');
	$sql = <<<SQL
SELECT *
FROM `$t_log` 
WHERE post_id = $post_id AND user_id=$user_id
SQL;
	return $wpdb->get_results($sql);
}/*}}}*/

function dob_common_cache( $post_id, $type, $data=false, $cpt='offer' ) {/*{{{*/
	global $wpdb;

	if ( !in_array($type,['all','stat','result','detail']) ) return false;

	$t_cache = $wpdb->prefix.'dob_cache';
	$t_latest = $wpdb->prefix.($cpt=='offer'?'dob_vote_post_latest':'dob_elect_latest');

	$sql = "SELECT max(ts) FROM `$t_latest` WHERE post_id = $post_id";
	$ts_latest = $wpdb->get_var($sql);
  if ( empty($ts_latest) ) return;

	$sql = "SELECT data, ts FROM `$t_cache` 
		WHERE post_id = $post_id AND type = '$type'";
	$old = $wpdb->get_row($sql);

	// GET
	if ( empty($data) ) {
		return ( !empty($old) && $old->ts == $ts_latest) ? 
			json_decode($old->data,true) : false ;
	}

	// SET
	if ( empty($old) ) {
		$ret = $wpdb->insert( $t_cache, [
			'post_id' => $post_id,
			'type'    => $type,
			'data'    => json_encode($data,JSON_UNESCAPED_UNICODE),
			'ts'      => $ts_latest
		] );
	} else /*if ( $old->ts <= $ts_latest )*/ {
		$ret = $wpdb->update( $t_cache, 
			[ 'data' => json_encode($data,JSON_UNESCAPED_UNICODE), 'ts' => $ts_latest],
			[ 'post_id' => $post_id, 'type' => $type]
		);
	}

	return $ret;

}/*}}}*/

function dob_common_update( $user_id, $post_id, $cpt='offer' ) {/*{{{*/
	global $wpdb, $global_real_ip;

	// check required argument
	if ( ! isset($_POST['dob_form_type'])
		|| ! isset($_POST['dob_form_val'])
		|| ! isset($_POST['dob_form_nonce'])
	) {
		return 'check1';
	}

	$type		= $_POST['dob_form_type'];
	$value  = (int)$_POST['dob_form_val'];
	$nonce	= $_POST['dob_form_nonce'];
	if ( ! wp_verify_nonce( $nonce, 'dob_form_nonce_'.$type)
		|| ! in_array( $type, array('updown','choice','plural') )
	) {
		return 'check2 : '.print_r($_POST,true);
	}

	// INSERT dob_*_log
	$t_log	= $wpdb->prefix.($cpt=='offer'?'dob_vote_post_log':'dob_elect_log');
	$dml = array (
		sprintf("INSERT IGNORE INTO `$t_log` 
			SET user_id = %d, post_id = %d, value = %d, ip = '%s'",
			$user_id, $post_id, $value, $global_real_ip 
		),
	);

	$t_latest = $wpdb->prefix.($cpt=='offer'?'dob_vote_post_latest':'dob_elect_latest');
	$sql = "SELECT value, ts FROM `$t_latest` 
		WHERE post_id = $post_id AND user_id = $user_id";
	$old_info = $wpdb->get_row($sql);
	if ( is_null($old_info) ) {
		$dml[] = sprintf("INSERT INTO `$t_latest` SET
			post_id = %d, user_id = %d, value = %d",
			$post_id, $user_id, $value 
		);
	} else if ( $old_info->value == $value ) {			
		$label = '동일값 투표는 생략됨'; //__('You can NOT vote with same value', DOBslug);
		return $label;
	} else if ( (time() - strtotime($old_info->ts)) < 3 ) {			
		$label = '투표 대기시간 5초'; //__('TOO FAST VOTE~!! (delay 5sec)', DOBslug);
		return $label;
	} else {
		$dml[] = sprintf("UPDATE `$t_latest` SET value = %d
			WHERE post_id = %d AND user_id = %d",
			$value, $post_id, $user_id 
		);
	}

	foreach ( $dml as $sql ) {
		$success = $wpdb->query( $sql );	// success == 1 (affected_rows)
		if ( empty($success) ) {
			return "DB ERROR(SQL)<br>\n: ".$sql;
		}
	}
}/*}}}*/

function dob_common_cart( $user_id, $post_id, $cpt='offer' ) {/*{{{*/
	global $wpdb, $global_real_ip;

	// check required argument
	if ( ! isset($_POST['dob_form_type'])
		|| ! isset($_POST['dob_form_val'])
		|| ! isset($_POST['dob_form_nonce'])
	) {
		return 'check1';
	}

	$type		= $_POST['dob_form_type'];
	$value	= (int)$_POST['dob_form_val'];
	$nonce	= $_POST['dob_form_nonce'];
	if ( ! wp_verify_nonce( $nonce, 'dob_form_nonce_'.$type)
		|| ! in_array( $type, array('updown','choice','plural') )
	) {
		return 'check2 : '.print_r($_POST,true);
	}

  // check duplicated value
	$t_latest = $wpdb->prefix.($cpt=='offer'?'dob_vote_post_latest':'dob_elect_latest');
  $sql = "SELECT value FROM `$t_latest`
    WHERE post_id = $post_id AND user_id = $user_id";
  $old_val = (int)$wpdb->get_var($sql);
  if ( ! is_null($old_val) && $old_val == $value ) {
    return $label_duplicated = '기존 투표값과 같습니다.'; //__('Already you voted sam value.', DOBslug);
  }

  // CHECK dup cart value
	$t_cart = $wpdb->prefix.'dob_cart';
	$sql = "SELECT value FROM `$t_cart` 
		WHERE user_id = $user_id AND type='$cpt' AND post_id = $post_id";
	$old_val = $wpdb->get_var($sql);
	if ( is_null($old_val) ) {  // INSERT
		$sql = sprintf("INSERT INTO `$t_cart` SET
			user_id = %d, type='$cpt', post_id = %d, value = %d",
			$user_id, $post_id, $value 
		);
  } elseif ( $old_val == $value ) {
    return $label_duplicated = '같은 값이 투표바구니에 있습니다.';  //__('Already same voting is in your Voting-Cart', DOBslug);
  } else {		// UPDATE dob_elect_latest
		$sql = sprintf("UPDATE `$t_cart` 
				SET value = %d, ts=CURRENT_TIMESTAMP
			WHERE user_id = %d AND type='$cpt' AND post_id = %d",
			$value, $user_id, $post_id 
		);
	}
	$success = $wpdb->query( $sql );	// success == 1 (affected_rows)
	return $ret = $success ? '' : "DB ERROR(SQL)<br>\n: ".$sql;

}/*}}}*/

