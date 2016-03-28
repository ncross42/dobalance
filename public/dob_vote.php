<?php
/**
 * Create site pages for this plugin
 */

function dob_vote_get_hierarchy_influence($parent_id=0,$ancestor=array()) {/*{{{*/
	global $wpdb;

	$ret = array( $parent_id => null );
	$ancestor[] = $parent_id;
	$t_terms = $wpdb->prefix.'terms';
	$t_term_taxonomy = $wpdb->prefix.'term_taxonomy';
	$t_user_category = $wpdb->prefix.'dob_user_category';

	// get self hierarchy user
	$sql = "SELECT COUNT(1) FROM $t_user_category
		WHERE taxonomy='hierarchy' AND term_taxonomy_id=$parent_id";
	$nSelf = (int)$wpdb->get_var($sql);

	// get low hierarchy user count
	$sql = "SELECT term_taxonomy_id FROM $t_term_taxonomy
		WHERE taxonomy='hierarchy' AND parent=$parent_id";
	$rows = $wpdb->get_results($sql,ARRAY_A);
	$nLow = 0;
	$bLeaf = empty($rows) ? 1 : 0;
	foreach ( $rows as $row ) {
		$tt_id = $row['term_taxonomy_id'];
		$tmp = dob_vote_get_hierarchy_influence($tt_id,$ancestor);
		$nLow += (int)$tmp[$tt_id]['nTotal'];
		$ret += $tmp;
	}
	array_pop($ancestor);
	$ret[$parent_id] = array( 
		'id'				=> $parent_id, 
		'nSelf'			=> $nSelf,
		'nLow'			=> $nLow,
		'nTotal'		=> $nSelf+$nLow,
		'bLeaf'			=> $bLeaf,
		'ancestor'	=> $ancestor,
	);
	return $ret;
}/*}}}*/

#require_once('dob_user_hierarchy.inc.php');

add_action( 'wp', 'dob_vote_wp_init' );
function dob_vote_wp_init() {/*{{{*/
	//wp_enqueue_style( 'bdd-css', plugins_url( 'assets/css/bdd.css', __FILE__ ) );
	wp_enqueue_script('dob-form-js', plugins_url('assets/js/dob_form.js',__FILE__), array('jquery'));
	//wp_localize_script('dob-vote-js', 'dob_vote_js', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));
	wp_enqueue_style( 'toggle-css', plugins_url( 'assets/css/toggle.css', __FILE__ ) );
	
}/*}}}*/

function dob_vote_get_log($post_id,$user_id) {/*{{{*/
	global $wpdb;
	$t_vote_log	= $wpdb->prefix . 'dob_vote_post_log';
	$sql = <<<SQL
SELECT *
FROM `$t_vote_log` 
WHERE post_id = $post_id AND user_id=$user_id
SQL;
	return $wpdb->get_results($sql);
}/*}}}*/

function dob_vote_get_post_latest($post_id,$user_id=0) {/*{{{*/
	global $wpdb;
	$sql_user = empty($user_id) ? '' : ' AND user_id='.$user_id;

	$t_latest = $wpdb->prefix . 'dob_vote_post_latest';
	$t_category = $wpdb->prefix . 'dob_user_category';
	$t_users = $wpdb->prefix . 'users';
	$sql = <<<SQL
SELECT $t_latest.*, term_taxonomy_id AS ttid, user_nicename
FROM $t_category 
	JOIN $t_users ON user_id=ID
	LEFT JOIN `$t_latest` USING (user_id)
WHERE taxonomy='hierarchy' AND post_id = $post_id $sql_user
SQL;
	$rows = $wpdb->get_results($sql,ARRAY_A);
	if ( $user_id ) {
		return empty($rows) ? null : $rows[0];
	} else {
		$ret = array();
		foreach ( $rows as $row ) {
			$ret[(int)$row['user_id']] = $row;
		}
		return $ret;
	}
}/*}}}*/

function dob_vote_get_user_info($user_id) {/*{{{*/
	global $wpdb;

	$sql = <<<SQL
SELECT *
FROM {$wpdb->prefix}dob_user_category
	JOIN {$wpdb->prefix}users ON user_id=ID
WHERE taxonomy='hierarchy' AND user_id=$user_id
SQL;
	return $wpdb->get_row($sql);
}/*}}}*/

function dob_vote_get_group_values($post_id) {/*{{{*/
	global $wpdb;

	$t_latest		= $wpdb->prefix . 'dob_vote_post_latest';
	$t_term_taxonomy = $wpdb->prefix . 'term_taxonomy';
	$t_terms		= $wpdb->prefix . 'terms';
	$t_category = $wpdb->prefix . 'dob_user_category';
	$sql = <<<SQL
SELECT term_taxonomy_id AS ttid, t.name, l.value, l.ts
FROM $t_term_taxonomy tt
	JOIN $t_terms t USING (term_id)
	JOIN $t_category c USING (term_taxonomy_id)
	JOIN $t_latest l USING (user_id)
WHERE tt.taxonomy = 'group' AND c.taxonomy='hierarchy'
	AND post_id = $post_id
SQL;
	$rows = $wpdb->get_results($sql);
	$ret = array();
	foreach ( $rows as $row ) {
		$ret[(int)$row->ttid] = $row;
	}
	return $ret;
}/*}}}*/

function dob_vote_get_user_group_all_ttid_values($user_id,$group_values,$vm_type,$bAll=true) {/*{{{*/
	global $wpdb;

	$t_category = $wpdb->prefix.'dob_user_category';
	$sql = <<<SQL
SELECT term_taxonomy_id AS ttid
FROM $t_category 
WHERE taxonomy='group' AND user_id = $user_id
SQL;
	$rows = $wpdb->get_col($sql);
	if ( empty($rows) ) return null;
	$gtid_vals = array();
	foreach ( $rows as $gtid ) {
		if ( $bAll ) {	// include null
			$gtid_vals[$gtid] = isset($group_values[$gtid]) ? $group_values[$gtid]->value : null;
		} else if ( isset($group_values[$gtid]) && $group_values[$gtid]->value ) {
			$gtid_vals[$gtid] = $group_values[$gtid]->value;	// only available value is counted
		}
	}
	if ( empty($gtid_vals) ) {
		return array();
	} else { // TODO: cache this
#echo '<pre>'.var_export([$user_id,$rows,$sql,$bAll,$gtid_vals],true).'</pre>';
		$value = dob_vote_aggregate_value($vm_type,$gtid_vals);
		return array ( 'gtid_vals'=>$gtid_vals, 'value' => $value );
	}
}/*}}}*/

function dob_vote_get_message($post_id,$user_id) {/*{{{*/
	$message = 'plz vote';
	if ( $ret = dob_vote_get_post_latest($post_id,$user_id) ) {
		$label_last = '마지막 투표';	//__('Last Voted', DOBslug);
		$message = $label_last.' : '.substr($ret['ts'],0,10);
	}
	return $message;
}/*}}}*/

function dob_vote_get_count($post_id) {/*{{{*/
	global $wpdb;
	$table_name = $wpdb->prefix . 'dob_vote_post_latest';

	$sql = <<<SQL
SELECT 
	SUM(IF(value=1,1,0)) AS `like`
	, SUM(IF(value=-1,-1,0)) AS `unlike`
FROM `{$table_name}`
WHERE post_id = %d
SQL;
	$prepare = $wpdb->prepare($sql, $post_id);
	$ret = $wpdb->get_row($prepare,ARRAY_A);

	if ( empty($ret) ) $ret = array ( 'like'=>0, 'unlike'=>0 );
	else {
		if ( !isset($ret['like']) ) $ret['like'] = 0;
		if ( !isset($ret['unlike']) ) $ret['unlike'] = 0;
	}
	
	return $ret;
}/*}}}*/

add_filter('the_content', 'dob_vote_site_content');
function dob_vote_site_content($content) {/*{{{*/
	$post_id = get_the_ID();
	if ( !is_page() && !is_feed() && $post_id 
		&& 'offer'==get_post_type($post_id)
		/*&& get_option('dob_vote_show_on_pages') */
	) {
		$dob_offer_cmb_vote = get_post_meta( $post_id, 'dob_offer_cmb_vote', true );
		$dob_vm_type = empty($dob_offer_cmb_vote['type']) ? 'updown': $dob_offer_cmb_vote['type'];
		$dob_vm_data = empty($dob_offer_cmb_vote['data']) ? array() : $dob_offer_cmb_vote['data'];

		/*switch ( $dob_vm_type ) {
		case 'updown': $dob_vote_content=dob_vote_content_updown($post_id); break;
		case 'choice': $dob_vote_content=dob_vote_content_choice($post_id,$dob_vm_data); break;
		case 'plural': $dob_vote_content=dob_vote_content_plural($post_id,$dob_vm_data); break;
		}*/
		$dob_vote_content=dob_vote_contents($dob_vm_type,$post_id,$dob_vm_data);
		$content = $dob_vote_content . $content;

		/*$dob_vote_position = get_option('dob_vote_position');
		if ($dob_vote_position == 'top') {
			$content = $dob_vote_content . $content;
		} elseif ($dob_vote_position == 'bottom') {
			$content = $content . $dob_vote_content;
		} else {
			$content = $dob_vote_content . $content . $dob_vote_content;
		}*/
	}
	return $content;
}/*}}}*/

function dob_vote_content_updown( $post_id/*=get_the_ID()*/, $bEcho = false) { /*{{{*/
	global $wpdb;
	$dob_vote = '';

	// Get the posts ids where we do not need to show like functionality
/*{{{*/	/*$allowed_posts = $excluded_posts = $excluded_categories = $excluded_sections = array();
	$allowed_posts = explode(",", get_option('dob_vote_allowed_posts'));
	$excluded_posts = explode(",", get_option('dob_vote_excluded_posts'));
	$excluded_categories = get_option('dob_vote_excluded_categories');
	$excluded_sections = get_option('dob_vote_excluded_sections');
	if (empty($excluded_categories)) $excluded_categories = array();
	if (empty($excluded_sections)) $excluded_sections = array();

	// Checking for excluded section. if yes, then dont show the like/dislike option
	if ( (in_array('home', $excluded_sections) && is_home()) 
		|| (in_array('archive', $excluded_sections) && is_archive())
		|| in_array($post_id, $excluded_posts) // Checking for excluded posts
	) {
		return;
	}*//*}}}*/

	/*{{{*//* Checking for excluded categories
	$excluded = false;
	$category = get_the_category();
	foreach($category as $cat) {
		if (in_array($cat->cat_ID, $excluded_categories) && !in_array($post_id, $allowed_posts)) {
			$excluded = true;
		}
	}
	// If excluded category, then dont show the like/dislike option
	if ($excluded) {
		return;
	}*/ /*}}}*/

	$title_text_like = 'Like';
	$title_text_unlike = 'Unlike';
	/*{{{*//* Check for title text. if empty then have the default value
	$title_text = ''; //get_option('dob_vote_title_text');
	if (empty($title_text)) {
		$title_text_like = __('Like', 'wti-like-post');
		$title_text_unlike = __('Unlike', 'wti-like-post');
	} else {
		$title_text = explode('/', get_option('dob_vote_title_text'));
		$title_text_like = $title_text[0];
		$title_text_unlike = isset( $title_text[1] ) ? $title_text[1] : '';
	}*//*}}}*/

	// Get the nonce for security purpose and create the like and unlike urls
	$nonce = wp_create_nonce('dob_form_nonce');
	$ajax_like_link = admin_url('admin-ajax.php?action=dob_vote_process_vote&task=like&post_id=' . $post_id . '&nonce=' . $nonce);
	$ajax_unlike_link = admin_url('admin-ajax.php?action=dob_vote_process_vote&task=unlike&post_id=' . $post_id . '&nonce=' . $nonce);
}/*}}}*/

function dob_vote_get_users_count() {/*{{{*/
	global $wpdb;
	$t_user_category	= $wpdb->prefix.'dob_user_category';
	$t_term_taxonomy	= $wpdb->prefix.'term_taxonomy';
	$sql = <<<SQL
		SELECT COUNT(1) 
		FROM $t_user_category 
			JOIN $t_term_taxonomy  USING (term_taxonomy_id,taxonomy)
		WHERE taxonomy='hierarchy' AND term_taxonomy_id <> 0
SQL;
	return (int)$wpdb->get_var($sql);
}/*}}}*/

function dob_vote_get_user_nicenames($uid_arr=array()) {/*{{{*/
	global $wpdb;
	if ( empty($uid_arr) || !is_array($uid_arr) ) return false;
	$uid_list = implode(',',array_map('intval',$uid_arr));
	$table = $wpdb->prefix.'users';
	$sql = "SELECT user_nicename FROM $table 
		WHERE ID IN ($uid_list)";
	return $wpdb->get_col($sql,0);
}/*}}}*/

function dob_vote_get_user_hierarchy($term_taxonomy_id) {/*{{{*/
	global $wpdb;
	$t_user_category = $wpdb->prefix . 'dob_user_category';

	$sql = "SELECT user_id
		FROM $t_user_category
		WHERE taxonomy='hierarchy' AND term_taxonomy_id=$term_taxonomy_id";
	return $wpdb->get_col($sql,0);
}/*}}}*/

function dob_vote_get_post_hierarchy_leaf($post_id,$influences) {/*{{{*/
	global $wpdb;
	$t_term_relationships = $wpdb->prefix.'term_relationships';
	$t_term_taxonomy = $wpdb->prefix.'term_taxonomy';

	$sql = "SELECT term_taxonomy_id 
		FROM $t_term_relationships 
			JOIN $t_term_taxonomy USING(term_taxonomy_id)
		WHERE taxonomy='hierarchy' AND object_id=$post_id";
	$rows = $wpdb->get_results($sql,ARRAY_N);
	//$all = array_column($rows,0);
	$all = array();
	foreach ( $rows as $row ) {
		$all[] = $row[0];
	}

	$diff = $all;
	foreach ( $all as $one ) {
		$diff = array_diff($diff,$influences[$one]['ancestor']);
	}
	return array_values($diff);
}/*}}}*/

function dob_vote_get_hierarchy_voter( $post_id ) {/*{{{*/
	global $wpdb;
	if ( empty($user_id) ) $user_id = get_current_user_id();

	$t_vote_post_latest	= $wpdb->prefix.'dob_vote_post_latest';
	$t_user_category		= $wpdb->prefix.'dob_user_category';
	$t_term_taxonomy		= $wpdb->prefix.'term_taxonomy';
	$t_terms						= $wpdb->prefix.'terms';

	$sql = <<<SQL
SELECT
	term_taxonomy_id, lft, name, slug, lvl, user_id, value
	, inf, chl, anc
FROM $t_user_category c
	JOIN $t_term_taxonomy  USING (term_taxonomy_id,taxonomy)
	JOIN $t_terms USING (term_id)
	LEFT JOIN $t_vote_post_latest l USING (user_id)
WHERE c.taxonomy = 'hierarchy' 
	AND ( l.post_id = $post_id OR l.post_id IS NULL )
ORDER BY lft
SQL;
	$rows = $wpdb->get_results($sql);
	$ret = array();
	foreach ( $rows as $r ) {
		$tid = $r->term_taxonomy_id;
		$uid = $r->user_id;
		$val = $r->value;
		if ( isset($ret[$tid]) ) {
			$ret[$tid]['uid_vals'][$uid] = $val;
		} else {
			$ret[$tid] = array (
				'lft'			=> $r->lft,
				'tname'		=> $r->name,
				'slug'		=> $r->slug,
				'lvl'			=> $r->lvl,
				'uid_vals'=> array( $uid=>$val ),
				'inf'			=> (int)$r->inf,
				'chl'			=> (int)$r->chl,
				'anc'			=> empty($r->anc) ? array() : explode(',',$r->anc),
			);
		}
	}
	return $ret;
}/*}}}*/

function dob_vote_aggregate_value( $vm_type, $uid_vals, $uvc_all=null ) {/*{{{*/
	if ( is_null($uvc_all) ) $uvc_all = count($uid_vals);
	if ( 0 == $uvc_all ) return 0;	// not possible

	$value = 0;	// decision value
	if ( 1 == $uvc_all ) {
		$value = empty($uid_vals) ? 0 : current($uid_vals);
	} elseif ( 1 < $uvc_all ) {
		$point = $uvc_all*(2/3);
		if ( $point <= count($uid_vals) ) {
			if ( $vm_type == 'updown' ) {
				$value = dob_vote_aggregate_updown($point,$uid_vals);
			} elseif ( $vm_type == 'choice' ) {
				$value = dob_vote_aggregate_choice($point,$uid_vals);
			} elseif ( $vm_type == 'plural' ) {
				$value = dob_vote_aggregate_plural($point,$uid_vals);
			}
		}
	}
	return $value;
}/*}}}*/

function dob_vote_aggregate_updown( $point, $user_votes ) {/*{{{*/
	$stat = array();
	foreach( $user_votes as /*$uid =>*/ $val ) {
		$stat[$val] = isset($stat[$val]) ? 1+$stat[$val] : 1;
	}
	// check the critical point
	foreach( $stat as $val => $cnt ) {
		if ( $point <= $cnt ) {
			return $val;
		}
	}
	return 0;
}/*}}}*/

function dob_vote_aggregate_choice( $point, $uid_vals ) {/*{{{*/
	$sorted = array();
	foreach( $uid_vals as /*$uid =>*/ $val ) {
		$sorted[$val] = isset($sorted[$val]) ? 1+$sorted[$val] : 1;
	}
	// sort by reverse order
	arsort($sorted);
	// first
	list( $k1, $v1 ) = each($sorted);
	// check approaching to the critical points
	if ( $v1 < $point ) return 0;
	// second
	if ( false===current($sorted) ) return $k1;	// only one
	list( $k2, $v2 ) = current($sorted);
	if ( $v1 == $v2 ) return 0;	// same count
	else return $k1;
}/*}}}*/

function dob_vote_aggregate_plural( $point, $uid_vals ) {/*{{{*/
	$stat = array();
	foreach( $uid_vals as $uid => $value ) {
		$arr1 = str_split(base_convert($value,10,2));
		$arr2 = array_map('intval',$arr1);
		foreach( $arr2 as $k => $v ) {
			if ( isset($stat[$k]) ) {
				$stat[$k][] = $v;
			} else {
				$stat[$k] = array($v);
			}
		}
	}
	// check the critical point
	$results = array();
	foreach( $stat as $k => $v ) {
		$results[$k] = ($point <= array_sum($v)) ? '1' : '0';
	}
	$result = implode('',$results);
	return (int)base_convert($result,2,10);
}/*}}}*/

function dob_vote_cart( $user_id, $post_id ) {/*{{{*/
	global $wpdb, $global_real_ip;

	// check required argument
	if ( ! isset($_POST['dob_form_type'])
		|| ! isset($_POST['dob_form_val'])
		|| ! isset($_POST['dob_form_nonce'])
	) {
		return 'check1';
	}

	$type		= $_POST['dob_form_type'];
	$val		= $_POST['dob_form_val'];
	$nonce	= $_POST['dob_form_nonce'];
	if ( ! wp_verify_nonce( $nonce, 'dob_form_nonce_'.$type)
		|| ! in_array( $type, array('updown','choice','plural') )
	) {
		return 'check2';
	}

	if ( $type=='plural' ) { // normalize plural value
		if ( ! is_array($val) ) return 'check2';
		$bit = '';
		for ( $i=1; $i<=DOBmaxbit; ++$i ) {
			$bit .= isset($val[$i]) ? $val[$i] : '0';
		}
		$rev = strrev($bit);
		$value = base_convert($rev,2,10);
	} else {
		$value = (int)$val; 
	}

	// check duplicated value
	$t_latest = $wpdb->prefix.'dob_vote_post_latest';
	$sql = "SELECT value FROM `$t_latest` 
		WHERE post_id = $post_id AND user_id = $user_id";
	$old_val = (int)$wpdb->get_var($sql);
	if ( ! is_null($old_val) && $old_val == $value ) {
		return $label_duplicated = '기존 투표값과 같습니다.';	//__('Already you voted sam value.', DOBslug);
	}

	// CHECK dup cart value
	$t_cart = $wpdb->prefix.'dob_cart';
	$sql = "SELECT value FROM `$t_cart` 
		WHERE user_id = $user_id AND type='vote' AND post_id = $post_id";
	$old_val = $wpdb->get_var($sql);
	if ( is_null($old_val) ) {	// INSERT
		$sql = sprintf("INSERT INTO `$t_cart` SET
			user_id = %d, type='vote', post_id = %d, value = %d",
			$user_id, $post_id, $value 
		);
	} elseif ( $old_val == $value ) {
		return $label_duplicated = '같은 값이 투표바구니에 있습니다.';	//__('Already same voting is in your Voting-Cart', DOBslug);
	} else {			// UPDATE
		$sql = sprintf("UPDATE `$t_cart` 
				SET value = %d, ts=CURRENT_TIMESTAMP
			WHERE user_id = %d AND type='vote' AND post_id = %d",
			$value, $user_id, $post_id 
		);
	}
	$success = $wpdb->query( $sql );	// success == 1 (affected_rows)
	return $ret = $success ? '' : "DB ERROR(SQL)<br>\n: ".$sql;

}/*}}}*/

function dob_vote_update( $user_id, $post_id, $val=null ) {/*{{{*/
	global $wpdb, $global_real_ip;

	// check required argument
	if ( ! isset($_POST['dob_form_type'])
		|| ( is_null($val) && ! isset($_POST['dob_form_val']) )
		|| ! isset($_POST['dob_form_nonce'])
	) {
		return 'check1';
	}

	$type		= $_POST['dob_form_type'];
	if ( is_null($val) ) $val = $_POST['dob_form_val'];
	$nonce	= $_POST['dob_form_nonce'];
	if ( ! wp_verify_nonce( $nonce, 'dob_form_nonce_'.$type)
		|| ! in_array( $type, array('updown','choice','plural') )
	) {
		return 'check2';
	}

	$value = $val; 
	if ( $type=='plural' ) { // normalize plural value
		if ( ! is_array($val) ) return 'check2';
		$bit = '';
		for ( $i=1; $i<=DOBmaxbit; ++$i ) {
			$bit .= isset($val[$i]) ? $val[$i] : '0';
		}
		$rev = strrev($bit);
		$value = base_convert($rev,2,10);
	}
	// INSERT dob_vote_post_log
	$dml = array (
		sprintf( "INSERT IGNORE INTO `{$wpdb->prefix}dob_vote_post_log` 
			SET user_id = %d, post_id = %d, value = %d, ip = '%s'",
			$user_id, $post_id, $value, $global_real_ip 
		),
	);

	$t_latest = $wpdb->prefix.'dob_vote_post_latest';
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
		$label = '투표 대기시간 3초'; //__('TOO FAST VOTE~!! (delay 3sec)', DOBslug);
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

$DOB_INDEX = array ( -1=>'-1d',0=>'0d','1d','2d','3d','4d','5d','6d','7d');
function dob_vote_make_stat( &$stat, $type, $nVal, $cnt=1,$strClass='di') {/*{{{*/
	global $DOB_INDEX;
	$arrVals = array();
	if ( $type=='updown' || $type=='choice' || $nVal<= 1 ) { 
		$sVal = $DOB_INDEX[$nVal];
		$arrVals[$sVal] = $cnt;
	} else {
		$arr1 = str_split(base_convert($nVal,10,2));
		foreach ( $arr1 as $k=>$v ) {
			$sVal = $DOB_INDEX[$k+1];
			if ( '1' == $v ) {
				$arrVals[$sVal] = $cnt;
			}
		}
	}

	foreach ( $arrVals as $sVal => $cnt ) {
		if ( isset($stat[$sVal]) ) {
			$stat[$sVal]['all'] += $cnt;
			$stat[$sVal][$strClass] += $cnt;
		} else {
			$stat[$sVal] = array (
				'all' => $cnt,
				'di' => ('di'==$strClass) ? $cnt : 0,
				'hi' => ('hi'==$strClass) ? $cnt : 0,
				'gr' => ('gr'==$strClass) ? $cnt : 0,
			);
		}
	}

}/*}}}*/

function dob_vote_contents( $vm_type, $post_id, $dob_vm_data, $bEcho = false) {
#echo '<pre>';
	global $wpdb;
	$user_id = get_current_user_id();
	if ( $user_id ) {
		$debug = '';
		$LOGIN_IP = empty($_SESSION['LOGIN_IP']) ? '' : $_SESSION['LOGIN_IP'];
		if ( ! empty($_POST) && $LOGIN_IP == dob_get_real_ip() ) {
			if ( (int)$_POST['dob_form_cart'] ) {
				$debug = dob_vote_cart($user_id,$post_id);
			} else {
				$debug = dob_vote_update($user_id,$post_id);
			}
		}
		if ( $debug ) {
			echo "<pre>$debug</pre>";
		}
	}
	$myinfo = $user_id ? dob_vote_get_user_info($user_id) : null;

	// group values
	$group_values = dob_vote_get_group_values($post_id);
#var_dump('<pre>',print_r($group_values,true),'</pre>');

#$ts = microtime(true);
	//$influences = dob_vote_get_hierarchy_influence();	// influences by term_taxonomy_id
#var_dump('<pre>',microtime(true)-$ts,'</pre>');
	//useless, $post_leaf_hierarchy = dob_vote_get_post_hierarchy_leaf($post_id,$influences);

	$nGroup = $nFixed = $nDirect = 0;
$ts = microtime(true);
	$hierarchy_voter = dob_vote_get_hierarchy_voter($post_id);	// order by lft
#var_dump('<pre>',microtime(true)-$ts,'</pre>');
#echo '<pre>'.print_r($hierarchy_voter,true).'</pre>';
	foreach ( $hierarchy_voter as $ttid => $v ) {/*{{{*/
		$all_ids = dob_vote_get_user_hierarchy($ttid);
		if ( empty($all_ids) ) {
			continue;	// no hierarchy member
		}
		$uid_vals	= $v['uid_vals'];
		$uvc_voted	= count($uid_vals);
		$uv_valid	= array_filter( $uid_vals, function($v){return !empty($v);} );
		$uvc_valid	= count($uv_valid);
		$uv_null = array_diff( $all_ids, array_keys($uv_valid) );

		// check leaf's direct voting
		if ( empty($v['chl']) ) {
			// valid direct-voting counts
			$nDirect += $uvc_valid;
			// check null-user's group delegator
			$uv_group = array();
			$uv_tmp = $uv_null;
			if ( $user_id && ! in_array($user_id,$uv_null) 
				&& $myinfo && $ttid == $myinfo->term_taxonomy_id
			) {
				$uv_tmp[] = $user_id;
			}
			foreach ( $uv_tmp as $uid ) {
				// get only available group values
				$tmp_gtid_vals = dob_vote_get_user_group_all_ttid_values($uid,$group_values,$vm_type,false);
				if ( !empty($tmp_gtid_vals) && !empty($tmp_gtid_vals['value']) ) {
					$uv_group[$uid] = $tmp_gtid_vals;
				}
				/*$gtid_vals = array();
				foreach ( $gr_ttids as $gtid ) {
					$gtid_vals[$gtid] = $group_values[$gtid]->value;
				}
				if ( !empty($gtid_vals) ) {
					// TODO: cache this
					if ( $value = dob_vote_aggregate_value($vm_type,$gtid_vals) ) {
						$uv_group[$uid] = array (
							'gtid_vals' => $gtid_vals,
							'value' => $value,
						);
					}
				}*/
			}
			$nGroup += ( $uvc_group_reflected = count( 
				array_diff(
					array_keys($uv_group),
					array_keys($uv_valid)) 
				) 
			);

			// deduct last-ancestor's influences
			foreach ( array_reverse($v['anc']) as $a_ttid ) {
				if ( isset($hierarchy_voter[$a_ttid]) ) {	// only exists
					$hierarchy_voter[$a_ttid]['inf'] -= ($uvc_valid+$uvc_group_reflected);
					if ( $hierarchy_voter[$a_ttid]['value'] ) break;
				}
			}

			// self added leaf data
			$hierarchy_voter[$ttid]['uv_group'] = $uv_group;
			$hierarchy_voter[$ttid]['value'] = null;
		} else {	// BRANCH NODE //
			// decision value
			$value = dob_vote_aggregate_value($vm_type,$uid_vals,count($all_ids));

			// deduct last-ancestor's influences
			if ( $value ) {	// not 0
				foreach ( array_reverse($v['anc']) as $a_ttid ) {
					if ( isset($hierarchy_voter[$a_ttid]) ) {
						$hierarchy_voter[$a_ttid]['inf'] -= $v['inf'];
						if ( $hierarchy_voter[$a_ttid]['value'] ) break;
					}
				}
			}
			// deduct last-ancestor's influences by uvc_valid(Direct-voting)
			if ( $uvc_valid ) {
				foreach ( array_reverse($v['anc']) as $a_ttid ) {
					if ( isset($hierarchy_voter[$a_ttid]) ) {
						$hierarchy_voter[$a_ttid]['inf'] -= $uvc_valid;
						if ( $hierarchy_voter[$a_ttid]['value'] ) break;
					}
				}
			}
			// self added non-leaf data
			$hierarchy_voter[$ttid]['value'] = $value;
			$hierarchy_voter[$ttid]['all_ids'] = $all_ids;
		}
		// self added common data
		$hierarchy_voter[$ttid]['uv_valid'] = $uv_valid;
		#$hierarchy_voter[$ttid]['uv_null'] = $uv_null;
		#$hierarchy_voter[$ttid]['all_ids'] = $all_ids;
	}/*}}}*/
#echo '<pre>'.print_r($hierarchy_voter,true).'</pre>';
#file_put_contents('/tmp/hv.'.date('His').'.php',print_r($hierarchy_voter,true));

	// build final result stat.
	$final_stat = $final_votes = array();/*{{{*/
	foreach ( $hierarchy_voter as $ttid => $v ) {
		if ( empty($v['chl']) ) {	// leaf
			// final decision by Direct-Voting
			foreach ( $v['uv_valid'] as $uid => $val ) {
				dob_vote_make_stat($final_stat,$vm_type,$val,1,'di');
				$final_votes[$uid] = "1,$val";
			} 
			// decision by group-voting
			foreach ( $v['uv_group'] as $uid => $info ) {
				if ( empty($v['uv_valid'][$uid]) ) {
#echo '<pre>'.print_r([$uid,$info,$v['uv_valid']],true).'</pre>';
					dob_vote_make_stat($final_stat,$vm_type,$info['value'],1,'gr');
					$final_votes[$uid] = "1,{$info['value']}";
				}
			} 
		} else  { // non-leaf
			if ( $v['value'] ) {	// Delegator's decision value is NOT 0
				$nFixed += $inf = $v['inf'];	// accum inf.(deducted nLow)
				dob_vote_make_stat($final_stat,$vm_type,$v['value'],$inf,'hi');
#echo '<pre>'.print_r($v,true).'</pre>';
				$final_votes[$uid] = "$inf,{$v['value']}";
			}
			if ( ! empty($v['uid_vals']) ) {	// Delegator's private value
				foreach ( $v['uid_vals'] as $uid => $val ) {
					if ( $val ) {
						$nDirect += 1;
						dob_vote_make_stat($final_stat,$vm_type,$val,1,'di');
						$final_votes[$uid] = "1,$val";
					}
				}
			}
		}
	}/*}}}*/
#echo '<pre>'.print_r($final_votes,true).'</pre>';
#echo '<pre>'.print_r($final_stat,true).'</pre>';
	
#echo '</pre>';
	## build HTML /*{{{*/
	//$label_title		= '균형 투표';		//__('Balance Voting', DOBslug);
	$label_stat			= '균형투표 통계';	//__('Statistics', DOBslug);
	$label_turnout	= '투표율';					//__('Total Users', DOBslug);
	$label_total		= '전체';						//__('Total Users', DOBslug);
	$label_valid		= '유효';						//__('Total Users', DOBslug);
	$label_hierarchy= '계층';						//__('Hierarchy voter', DOBslug);
	$label_group		= '그룹';						//__('Delegate voter', DOBslug);
	$label_direct		= '직접';						//__('Direct voter', DOBslug);
	$label_chart		= '결과 차트';			//__('Direct voter', DOBslug);
	$label_my				= '내 투표';				//__('My Vote', DOBslug);
	$label_history	= '기록';				//__('My Vote', DOBslug);
	$label_vote			= '투표';						//__('Vote', DOBslug);
	$label_influence= '영향력 관계도';	//__('Direct voter', DOBslug);
	$label_no_pos		= '계층이 지정되지 않아, 투표할 수 없습니다.';	//__('Direct voter', DOBslug); 
	$label_login		= '로그인 해주세요';	//__('Please Login', DOBslug);

	// build html hierarchy chart
	$html_hierarchy = '';/*{{{*/
	if ( is_single() ) {
		$vote_latest = dob_vote_get_post_latest($post_id);	// user_id => rows	// for login_name
		$myval = empty($vote_latest[$user_id]) ? null : (int)$vote_latest[$user_id]['value'];
#echo '<pre>'.print_r($myinfo,true).'</pre>';
		$hierarchies = array();/*{{{*/
		$all_group_vals = array();
		foreach( $group_values as $gr ) {
			$all_group_vals[] = $gr->name.':'.$gr->value;
		}
		$hierarchies[] = " ## $label_total $label_group $label_vote <br> &nbsp; ".implode(', ',$all_group_vals);
		$hierarchies[] = " ## $label_hierarchy $label_influence";

		foreach ( $hierarchy_voter as $ttid => $v ) {
			$uv_valid = $v['uv_valid'];
			$indent = ' &nbsp; '.str_repeat(' -- ',$v['lvl']);
			$inherit = 0;
			foreach ( $v['anc'] as $a_ttid ) {
				if ( !empty($hierarchy_voter[$a_ttid]['value']) ) {
					$inherit = $hierarchy_voter[$a_ttid]['value'];
				}
			}
			if ( empty($v['chl']) ) {	// leaf
				$str_mine = '';
				$grname_vals = array();
				// info of myval and mygroup
				if ( $myinfo && $ttid == $myinfo->term_taxonomy_id ) {
					$mygroup = isset($v['uv_group'][$user_id]) ? $v['uv_group'][$user_id]
						: dob_vote_get_user_group_all_ttid_values($user_id,$group_values,$vm_type,true) ;
#echo '<pre>'.var_export([$user_id,$group_values,$mygroup],true).'</pre>';
					if ( ! empty($mygroup) ) {
						$grname_vals[] = "<span style='background-color:yellow'>[ {$mygroup['value']} ]</span>";
						foreach ( $mygroup['gtid_vals'] as $gtid => $val ) {
							$grname_vals[] = isset($group_values[$gtid]) ? $group_values[$gtid]->name.":<b>$val</b>" : '';
						}
					}
					$str_mine = "<span style='color:red'>@{$myinfo->user_nicename}:<b>".(is_null($myval)?'null':$myval)."</b></span>";
				}
				$str_group = empty($grname_vals) ? '' : '// '.implode(', ',$grname_vals);
				$uvc_valid	= count($v['uv_valid']);
				$hierarchies[] = $indent.$v['tname']."({$v['inf']}-$uvc_valid) : <u>$inherit</u> $str_mine $str_group";
			} else {	// branch
				$yes = $no = array();
#echo '<pre>'.var_export($uv_valid,true).'</pre>';
				foreach ( $uv_valid as $uid => $val ) {
					$str = $vote_latest[$uid]['user_nicename'].":<b>$val</b>";
					$yes[] = ( $uid==$user_id ) ? "<span style='color:red'>@$str</span>" : $str;
				}
				$yes = implode(', ',$yes);
				$no = array_diff($v['all_ids'],array_keys($uv_valid));
				$no_ids = dob_vote_get_user_nicenames($no);
				$no = empty($no_ids) ? '' : '<strike>'.implode(', ',$no_ids).'</strike>';
				$val = empty($v['value']) ? "<u>$inherit</u>" : "<b>{$v['value']}</b>";
				$hierarchies[] = $indent.$v['tname']."({$v['inf']}) : $val <span style='background-color:yellow'>[ {$v['value']} ]</span> ($yes) $no";
			}
		}/*}}}*/
		$content_hierarchy = implode('<br>',$hierarchies);
		$label_analysis = '투표 분석';	//__('Direct voter', DOBslug);
		$html_hierarchy = <<<HTML
	<li class="toggle">
		<h3># $label_analysis<span class="toggler">[close]</span></h3>
		<div class="panel" style='display:block; font-size:1.12em'> $content_hierarchy </div>
	</li>
HTML;
	}/*}}}*/

	$nTotal = dob_vote_get_users_count();	// get all user count
	$nValid = $nFixed+$nGroup+$nDirect;
	$fValid = number_format(100*($nValid/$nTotal),1);
	$fFixed = $fGroup = $fDirect = 0.0;
	if ( $nValid ) {
		$fFixed = number_format(100*($nFixed/$nValid),1);
		$fGroup = number_format(100*($nGroup/$nValid),1);
		$fDirect = number_format(100*($nDirect/$nValid),1);
	}/*}}}*/

	$html_chart = $html_form = $html_history = '';/*{{{*/
	if ( is_single() ) {
		$content_form = '';
		$vm_legend = ($vm_type=='updown') ? 
			array( -1=>'반대', 0=>'기권' ) : array( -1=>'모두반대', 0=>'기권' );
		if ( $vm_type == 'updown' ) {
			$vm_legend[1] = '찬성';
		} else {	// choice, plural
			foreach ( $dob_vm_data as $k => $v ) {
				$vm_legend[$k+1] = $v;
			}
		}
		$content_chart = dob_vote_html_chart($final_stat,$vm_legend,$nTotal);
		$html_chart = "<li>
			<h3># $label_chart</h3>
			<div class='panel'> $content_chart </div>
		</li>";

		if ( empty($user_id) ) {
			$content_form = "<a href='http://wp1.youthpower.kr/wp-login.php' style='color:red; font-weight:bold'>$label_login</a>";
		} else if ( empty($myinfo->term_taxonomy_id) ) {
			$content_form = "<span style='color:red; font-size:1.2em; font-weight:bold'>$label_no_pos</span>";
		} else {
			$content_form = dob_vote_display_mine($post_id,$vm_type,$vm_legend,$myval,$user_id);
		}
		$html_form = "<li>
			<h3># $label_my</h3>
			<div class='panel'> $content_form </div>
		</li>";

		if ( $user_id ) {
			$html_history = <<<HTML
			<li class='toggle'>
				<h3># $label_my $label_history<span class='toggler'>[open]</span></h3>
				<div class='panel' style='display:none'>
					<table id='table_log'>
						<tr><th>date_time</th><th>value</th><th>ip</th></tr>
HTML;
			foreach ( dob_vote_get_log($post_id,$user_id) as $log ) {
				$html_history .= <<<HTML
						<tr><td>{$log->ts}</td><td>{$log->value}</td><td>{$log->ip}</td></tr>
HTML;
			}
			$html_history .= <<<HTML
					</table>
<style>
#table_log th { background-color:#eee; text-transform:none; text-align:center; padding:0; }
</style>
				</div>
			</li>
HTML;
		}
	}/*}}}*/

	$dob_vote = <<<HTML
<ul id="toggle-view"><!--{{{-->
	<li class="toggle">
		<h3># $label_stat <small> // $label_turnout : $fValid% </small><span class="toggler">[open]</span></h3>
		<div class="panel" style="display:none">
			<table>
				<tr><td class="left">$label_valid    / $label_total</td><td>$fValid% ( $nValid / $nTotal )</td></tr>
				<tr><td class="left">$label_hierarchy/ $label_valid</td><td>$fFixed% ( $nFixed / $nValid )</td></tr>
				<tr><td class="left">$label_group    / $label_valid</td><td>$fGroup% ( $nGroup / $nValid )</td></tr>
				<tr><td class="left">$label_direct   / $label_valid</td><td>$fDirect% ( $nDirect / $nValid )</td></tr>
			</table>
		</div>
	</li>
	$html_chart
	$html_hierarchy
	$html_form
	$html_history 
</ul><!--}}}-->
HTML;
	file_put_contents('/tmp/dob_vote.html',$dob_vote);

	if ($bEcho) echo $dob_vote;
	else return $dob_vote;
}

function dob_vote_html_chart($final_stat,$vm_legend,$nTotal) {/*{{{*/
	global $DOB_INDEX;
	$ret = "<style> /*{{{*/
table.barchart { width: 100%; border-width:0px; border-collapse: collapse; }
table.barchart td div { height:20px; text-align:center; overflow: hidden; text-overflow: ellipsis; }
td.c-1 { background-color: BLUE; color:white; } /*TANGERINE ;*/
td.c0  { background-color: #E5E4E2; }
td.c1  { background-color: RED; color:white; }
td.c3  { background-color: TAN; }
td.c4  { background-color: GOLD ; }
td.c5  { background-color: SAPPHIRE ; }
td.c6  { background-color: GREEN; }
td.c7  { background-color: SAFETY PINK ; }
td.c8  { background-color: LIME ; }
td.gr  { background-color: #E5E4E2 ; } /*Platinum*/
</style>"; /*}}}*/

	$str_di = '직접'; //__('Direct', DOBslug),
	$str_hi = '계층'; //__('Hierarchy', DOBslug),
	$str_gr = '그룹'; //__('Group', DOBslug),
	$str_un = '미투표';	//__('Unpolled', DOBslug)
	$td_format = "<td width='%s' title='%s' class='%s'><div>%s</div></td>";
	//array_multisort(array_column($final_stat,'all'), SORT_DESC, $final_stat);	// confused if reorder.
	ksort($final_stat,SORT_NUMERIC);

	$nUnpolled = $nTotal;
	$htmls = $row1 = $row2 = array();
	$htmls = $tr1 = $tr2 = array();
	foreach ( $final_stat as $i => $data ) {
		$k = intval($i);
		extract($data);	// 'all', 'di', 'hi'
		$nUnpolled -= $all;
		// row1
		$ratio = sprintf('%0.1f%%',100*$all/$nTotal);
		$text  = $vm_legend[$k]." $ratio ($all)";
		$tr1[] = sprintf($td_format, $ratio, $text, 'c'.$k, $text );
		if ( $di ) { // row2-direct
			$ratio = sprintf('%0.1f%%',100*$di/$nTotal);
			$text  = $str_di." $ratio ($di)";
			$tr2[] = sprintf($td_format, $ratio, $text, 'c'.$k, $text );
		}
		if ( $hi ) { // row2-hierarchy
			$ratio = sprintf('%0.1f%%',100*$hi/$nTotal);
			$text  = $str_hi." $ratio ($hi)";
			$tr2[] = sprintf($td_format, $ratio, $text, '', $text );
		}
		if ( $gr ) { // row2-group
			$ratio = sprintf('%0.1f%%',100*$gr/$nTotal);
			$text  = $str_gr." $ratio ($gr)";
			$tr2[] = sprintf($td_format, $ratio, $text, 'gr', $text );
		}
	}
	if ( $nUnpolled ) {
		$ratio = sprintf('%0.1f%%',100*$nUnpolled/$nTotal);
		$text  = $str_un." $ratio ($nUnpolled)";
		$un = sprintf($td_format, $ratio, $text, '', $text );
		$tr1[] = $un; $tr2[] = $un;
	}
	$ret .= '<table class="barchart"><tr>'.implode(' ',$tr1).'</tr></table>';
	$ret .= '<table class="barchart"><tr>'.implode(' ',$tr2).'</tr></table>';

	return $ret;
}/*}}}*/

function dob_vote_display_mine($post_id,$vm_type,$vm_legend,$myval='',$user_id) {/*{{{*/
	ob_start();
	//session_unset();	// $_SESSION = array();
	$LOGIN_IP = empty($_SESSION['LOGIN_IP']) ? '' : $_SESSION['LOGIN_IP'];
	$_SESSION['user_id'] = $user_id;
	$_SESSION['post_id'] = $post_id;
	$_SESSION['secret'] = $secret = base64_encode(openssl_random_pseudo_bytes(20));
	if ( $vm_type=='plural' ) { // normalize plural value
		//if ( $myval <= 1 ) $myval = array($myval);
		$vals = str_split(strrev(base_convert($myval,10,2)));
		//$myval = array_keys(array_filter($vals, function($v){return $v=='1';}));
		$myvals = array();
		foreach ( $vals as $k => $v ) {
			if ( $v == '1' ) $myvals[] = $k+1;
		}
	}
	$label_secret = '보안코드';			//__('Statistics', DOBslug);
	$label_remember = '암호화된 DB에서 직접 투표확인을 원하시면 이 값을 기억해 주세요.';			//__('Statistics', DOBslug);
	// display area
	echo <<<HTML
		<div class="panel">
		<table>
			<form id="formDob" method="post">
			<input type="hidden" name="dob_form_type" value="$vm_type">
			<input type="hidden" name="dob_form_cart" value="0">
			<input type="hidden" name="dob_form_old_val" value="$myval">
			<!--tr><td>
				$label_secret : <input type="text" name="dob_vote_secret" value="$secret" style="width:300px" READONLY>
				<br><b>$label_remember</b>
			</td></tr-->
			<tr><td>
HTML;
	wp_nonce_field( 'dob_form_nonce_'.$vm_type, 'dob_form_nonce' );
	foreach ( $vm_legend as $val => $label ) {
		$html_input = '';
		if ( $vm_type == 'plural' ) {
			$checked = in_array($val,$myvals) ? 'CHECKED' : '';
			$html_input = "<input type='checkbox' name='dob_form_val[$val]' value='1' $checked>";
		} else {
			$checked = ($val===$myval) ? 'CHECKED' : '';
			$html_input = "<input type='radio' name='dob_form_val' value='$val' $checked>";
		}
		echo " <label>$html_input $label</label> ";
	}

	$html_submit = empty($user_id) ? $label_login : dob_vote_get_message($post_id,$user_id);	// vote_post_latest timestamp
	if ( $LOGIN_IP == dob_get_real_ip() ) {
		$label_fast = '바로투표';	//__('Vote', DOBslug);
		$label_cart = '투표바구니';	//__('Vote', DOBslug);
		$style = 'width:100px; height:20px; background:#ccc; color:black; text-decoration: none; font-size: 13px; margin: 0; padding: 0 10px 1px;';
		$html_submit .= " <input id='btn_fast' type='button' value='$label_fast' style='$style' >";
		$html_submit .= " <input id='btn_cart' type='button' value='$label_cart' style='$style' >";
	} else {
		$label_iperr_relogin = '로그인 이후 1시간이 지났거나, 네트워크가 초기화 되었으니, 다시 로그인해 주세요<br>투표시에는 네트워크(WIFI,LTE,3G)를 변경하지 마세요.';	//__('You passed 1-hours after login, or Your network was Changed. Please Login AGAIN.', DOBslug);
		$html_submit .= '<br>'.$label_iperr_relogin;
	}
	echo <<<HTML
			</td></tr>
			<tr><td style="text-align:right;">$html_submit</td></tr>
			</form>
		</table>
		</div>
HTML;
	$ret = ob_get_contents();
	ob_end_clean();
	return $ret;
}/*}}}*/
