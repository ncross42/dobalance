<?php
/**
 * Create site pages for this plugin
 */

#require_once('dob_user_hierarchy.inc.php');

add_action( 'wp', 'dob_vote_wp_init' );
function dob_vote_wp_init() {/*{{{*/
	//wp_enqueue_style( 'bdd-css', plugins_url( 'assets/css/bdd.css', __FILE__ ) );
	wp_enqueue_script('dob-vote-js', plugins_url('assets/js/vote.js',__FILE__), array('jquery'));
	//wp_localize_script('dob-vote-js', 'dob_vote_js', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));
	wp_enqueue_style( 'toggle-css', plugins_url( 'assets/css/toggle.css', __FILE__ ) );
	
}/*}}}*/

$global_real_ip = dob_vote_get_real_ip();
function dob_vote_get_real_ip() {/*{{{*/
	if (getenv('HTTP_CLIENT_IP')) {
		$ip = getenv('HTTP_CLIENT_IP');
	} elseif (getenv('HTTP_X_FORWARDED_FOR')) {
		$ip = getenv('HTTP_X_FORWARDED_FOR');
	} elseif (getenv('HTTP_X_FORWARDED')) {
		$ip = getenv('HTTP_X_FORWARDED');
	} elseif (getenv('HTTP_FORWARDED_FOR')) {
		$ip = getenv('HTTP_FORWARDED_FOR');
	} elseif (getenv('HTTP_FORWARDED')) {
		$ip = getenv('HTTP_FORWARDED');
	} else {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	
	return $ip;
}/*}}}*/

function dob_vote_get_post_latest($post_id,$user_id=0) {/*{{{*/
	global $wpdb;
	$sql_user = empty($user_id) ? '' : ' AND user_id='.$user_id;

	$t_latest = $wpdb->prefix . 'dob_vote_post_latest';
	$t_category = $wpdb->prefix . 'dob_user_category';
	$t_users = $wpdb->prefix . 'users';
	$sql = <<<SQL
SELECT $t_latest.*, term_taxonomy_id AS ttid, user_login
FROM `$t_latest` 
	JOIN $t_category USING (user_id)
	JOIN $t_users ON user_id=ID
WHERE post_id = %d $sql_user
SQL;
	$prepare = $wpdb->prepare($sql, $post_id);
	$rows = $wpdb->get_results($prepare,ARRAY_A);
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

function dob_vote_get_message($post_id,$user_id) {/*{{{*/
	$message = 'plz vote';
	if ( $ret = dob_vote_get_post_latest($post_id,$user_id) ) {
		$message = 'last voted : '.substr($ret['ts'],0,10);
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
	$nonce = wp_create_nonce('dob_vote_nonce');
	$ajax_like_link = admin_url('admin-ajax.php?action=dob_vote_process_vote&task=like&post_id=' . $post_id . '&nonce=' . $nonce);
	$ajax_unlike_link = admin_url('admin-ajax.php?action=dob_vote_process_vote&task=unlike&post_id=' . $post_id . '&nonce=' . $nonce);
}/*}}}*/

function dob_vote_get_users_count() {/*{{{*/
	global $wpdb;
	$table = $wpdb->prefix.'dob_user_category';
	$sql = "SELECT COUNT(1) FROM $table WHERE taxonomy='hierarchy'";
	return (int)$wpdb->get_var($sql);
}/*}}}*/

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

function dob_vote_get_user_hierarchy($term_taxonomy_id) {/*{{{*/
	global $wpdb;
	$t_user_hierarchy = $wpdb->prefix . 'dob_user_category';

	$sql = "SELECT user_id
		FROM $t_user_hierarchy
		WHERE taxonomy='hierarchy' AND term_taxonomy_id=$term_taxonomy_id";
	$rows = $wpdb->get_results($sql,ARRAY_N);
	return array_column($rows, 0);
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
	$t_user_hierarchy		= $wpdb->prefix.'dob_user_category';
	$t_term_taxonomy		= $wpdb->prefix.'term_taxonomy';
	$t_terms						= $wpdb->prefix.'terms';

	$sql = <<<SQL
SELECT
	term_taxonomy_id, lft, name, slug, lvl, user_id, value
FROM $t_vote_post_latest
	JOIN $t_user_hierarchy USING (user_id)
	JOIN $t_term_taxonomy  USING (term_taxonomy_id)
	JOIN $t_terms USING (term_id)
WHERE $t_user_hierarchy.taxonomy = 'hierarchy' 
	AND $t_vote_post_latest.post_id = $post_id
ORDER BY lft
SQL;
	$rows = $wpdb->get_results($sql, ARRAY_N);
	$ret = array();
	foreach ( $rows as $r ) {
		$tid = $r[0];
		$uid = $r[5];
		$val = $r[6];
		if ( isset($ret[$tid]) ) {
			$ret[$tid]['uid_vals'][$uid] = $val;
		} else {
			$ret[$tid] = array (
				'lft'			=> $r[1],
				'tname'		=> $r[2],
				'slug'		=> $r[3],
				'lvl'			=> $r[4],
				'uid_vals'=> array( $uid=>$val ),
			);
		}
	}
	return $ret;
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

function dob_vote_update( $user_id, $post_id ) {/*{{{*/
	global $wpdb, $global_real_ip;

	// check required argument
	if ( ! isset($_POST['dob_vote_type'])
		|| ! isset($_POST['dob_vote_val'])
		|| ! isset($_POST['dob_vote_nonce'])
	) {
		return 'check1';
	}

	$type		= $_POST['dob_vote_type'];
	$val		= $_POST['dob_vote_val'];
	$nonce	= $_POST['dob_vote_nonce'];
	if ( ! wp_verify_nonce( $nonce, 'dob_vote_nonce_'.$type)
		|| ! in_array( $type, array('updown','choice','plural') )
	) {
		return 'check2';
	}

	$ret = '';
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
	$sql = sprintf("INSERT IGNORE INTO `{$wpdb->prefix}dob_vote_post_log` 
		SET user_id = %d, post_id = %d, value = %d, ip = '%s'",
		$user_id, $post_id, $value, $global_real_ip 
	);
	$success = $wpdb->query( $sql );	// success == 1 (affected_rows)
	if ( empty($success) ) { // failed (duplicated)
		$ret = "TOO FAST CLICK~!! ";
		$ret .= "DB ERROR(SQL)<br>\n: ".$sql;
	}

	$t_latest = $wpdb->prefix.'dob_vote_post_latest';
	$sql = "SELECT value FROM `$t_latest` 
		WHERE post_id = $post_id AND user_id = $user_id";
	$old_val = $wpdb->get_var($sql);
	// UPDATE dob_vote_post_latest
	if ( is_null($old_val) ) {
		$sql = sprintf("INSERT INTO `$t_latest` SET
			post_id = %d, user_id = %d, value = %d",
			$post_id, $user_id, $value 
		);
	} else {			
		$sql = sprintf("UPDATE `$t_latest` SET value = %d
			WHERE post_id = %d AND user_id = %d",
			$value, $post_id, $user_id 
		);
	}

	$success = $wpdb->query( $sql );	// success == 1 (affected_rows)
	if ($success) {
		$ret = '';
	} else {
		$ret = "DB ERROR(SQL)<br>\n: ".$sql;
	}
}/*}}}*/

function dob_vote_accum_stat( &$stat, $type, $value, $cnt=1) {/*{{{*/
	if ( $type=='updown' || $type=='choice' || $value <= 1 ) { 
		$stat[$value] = isset($stat[$value]) ? $stat[$value]+$cnt : $cnt;
	} else {
		$arr1 = str_split(base_convert($value,10,2));
		foreach ( $arr1 as $k=>$v ) {
			if ( '1' == $v ) $stat[$k+1] = isset($stat[$k+1]) ? $stat[$k+1]+$cnt : $cnt;
		}
	}
}/*}}}*/

function dob_vote_contents( $dob_vm_type, $post_id, $dob_vm_data, $bEcho = false) {
echo '<pre>';
	global $wpdb;
	$user_id = get_current_user_id();
	if ( ! empty($_POST) ) {
		dob_vote_update($user_id,$post_id);
	}

#$ts = microtime(true);
	$influences = dob_vote_get_hierarchy_influence();	// influences by term_taxonomy_id
#file_put_contents('/tmp/in.php',print_r($influences,true));
#file_put_contents('/tmp/in.php',PHP_EOL.(microtime(true)-$ts),FILE_APPEND);
	//useless, $post_leaf_hierarchy = dob_vote_get_post_hierarchy_leaf($post_id,$influences);

	$aDelegate = array();
	$nDelegate = $nHierarchy = $nDirect = 0;
	$hierarchy_voter = dob_vote_get_hierarchy_voter($post_id);	// order by lft
#print_r($hierarchy_voter);
	foreach ( $hierarchy_voter as $ttid => $v ) {/*{{{*/
		/*$tname		= $v['tname'];
		$slug			= $v['slug'];
		$lvl			= $v['lvl'];*/
		$uid_vals	= $v['uid_vals'];
		$uv_count	= count($uid_vals);
		$all_ids = dob_vote_get_user_hierarchy($ttid);
		$total = count($all_ids);

		// check direct voting
		if ( $influences[$ttid]['bLeaf'] ) {
			// subtract abstention(val=0) counts from uid_vals
			$uv_count -= count( array_filter( $uid_vals, function($v){return $v==0;} ) );
			$nDirect += $uv_count;
			// recalc ancestor's influences
			foreach ( $influences[$ttid]['ancestor'] as $a_ttid ) {
				if ( isset($hierarchy_voter[$a_ttid]) ) {	// only exists
					$hierarchy_voter[$a_ttid]['inf'] -= $uv_count;
				}
			}
			// self added leaf data
			$hierarchy_voter[$ttid]['value'] = null;
			$hierarchy_voter[$ttid]['inf'] = $total;
		} else {
			$value = 0;	// decision value
			// check minimum turnout
			if ( 1 == $total ) {
				$value = current($uid_vals);
			} elseif ( 1 < $total ) {
				$point = $total*(2/3);
				if ( $point <= $uv_count ) {
					if ( $dob_vm_type == 'updown' ) {
						$value = dob_vote_aggregate_updown($point,$uid_vals);
					} elseif ( $dob_vm_type == 'choice' ) {
						$value = dob_vote_aggregate_choice($point,$uid_vals);
					} elseif ( $dob_vm_type == 'plural' ) {
						$value = dob_vote_aggregate_plural($point,$uid_vals);
					}
				}
			} else {	// nobody is in this hierarchy
				continue;
			}
			// recalc ancestor's influences
			foreach ( $influences[$ttid]['ancestor'] as $a_ttid ) {
				if ( isset($hierarchy_voter[$a_ttid]) && $value ) {	// not 0
					$hierarchy_voter[$a_ttid]['inf'] -= $uv_count;
				}
			}
			// self added non-leaf data
			$hierarchy_voter[$ttid]['value'] = $value;
			$hierarchy_voter[$ttid]['all_ids'] = $all_ids;
			$hierarchy_voter[$ttid]['inf'] = $influences[$ttid]['nLow'];
		}
		// self added common data
		$hierarchy_voter[$ttid]['bLeaf'] = $influences[$ttid]['bLeaf'];
		$hierarchy_voter[$ttid]['count'] = $uv_count;
	}/*}}}*/
#print_r($hierarchy_voter);


	$charts = array();/*{{{*/
	$vote_latest = dob_vote_get_post_latest($post_id);	// user_id => rows	// for login_name
	foreach ( $hierarchy_voter as $ttid => $v ) {
		$uid_vals = $v['uid_vals'];
		$indent = ' ~ '.str_repeat(' ~ ',$v['lvl']);
		$inherit = 0;
		foreach ( $influences[$ttid]['ancestor'] as $a_ttid ) {
			if ( !empty($hierarchy_voter[$a_ttid]['value']) ) {
				$inherit = $hierarchy_voter[$a_ttid]['value'];
			}
		}
		if ( $v['bLeaf'] ) {
			$myval = isset($uid_vals[$user_id]) ? " (@{$vote_latest[$user_id]['user_login']}:{$uid_vals[$user_id]})" : '';
			$charts[] = $indent.$v['tname']."({$v['inf']}-".count($uid_vals).") : [$inherit]".$myval;
		} else {
			$yes = $no = array();
			foreach ( $uid_vals as $uid => $val ) {
				$yes[] = ($uid==$user_id?'@':'').$vote_latest[$uid]['user_login'].':'.$val;
			}
			$yes = implode(',',$yes);
			$no = array_diff($v['all_ids'],array_keys($uid_vals));
			$no = empty($no) ? '' : '<strike>'.implode(',',$no).'</strike>';
			$val = empty($v['value']) ? "[$inherit]" : $v['value'];
			$charts[] = $indent.$v['tname']."({$v['inf']}) : $val ($yes) $no";
		}
	}/*}}}*/
	$content_chart = implode('<br>',$charts);

	// build final vote results.
	$result_stat = array();/*{{{*/
	foreach ( $hierarchy_voter as $ttid => $v ) {
		if ( $v['bLeaf'] ) {
			foreach ( $v['uid_vals'] as $uid => $val ) {
				dob_vote_accum_stat($result_stat,$dob_vm_type,$val,1);
			}
		} else if ( $val=$v['value'] ) {	// non-leaf
			$nHierarchy += $inf = $v['inf'];
			dob_vote_accum_stat($result_stat,$dob_vm_type,$val,$inf);
		} elseif ( !empty($v['uid_vals']) ) {	// manager's private vote value
			$nHierarchy += 1;
			foreach ( $v['uid_vals'] as $uid => $val ) {
				dob_vote_accum_stat($result_stat,$dob_vm_type,$val,1);
			}
		}
	}/*}}}*/
	if ( isset($vote_latest[$user_id]) ) {
		$result_stat['myval'] = (int)$vote_latest[$user_id]['value'];
	}
#print_r($result_stat);

echo '</pre>';
	## build HTML /*{{{*/
	/* Get the nonce for security purpose and create the like and unlike urls
	$args = http_build_query( array (
		'post_id'	=> $post_id,
		'nonce'		=> wp_create_nonce('dob_vote_nonce'),
		'action'	=> '',
		'task'		=> '',
	) );
	$ajax_url = admin_url('admin-ajax.php?'.$args);*/

	//$label_title		= '균형 투표';		//__('Balance Voting', DOBslug);
	$label_stat			= '균형투표 통계';	//__('Statistics', DOBslug);
	$label_turnout	= '투표율';					//__('Total Users', DOBslug);
	$label_valid		= '유효/전체';			//__('Total Users', DOBslug);
	$label_hierarchy= '계층/유효';			//__('Hierarchy voter', DOBslug);
	$label_delegate	= '대의원/유효';		//__('Delegate voter', DOBslug);
	$label_direct		= '직접/유효';			//__('Direct voter', DOBslug);
	$label_chart		= '계층별 영향력 관계도';	//__('Direct voter', DOBslug);
	$label_my				= '내 투표';				//__('My Vote', DOBslug);

	$nTotal = dob_vote_get_users_count();	// get all user count
	$nValid = $nHierarchy+$nDelegate+$nDirect;
	$fValid = number_format(100*($nValid/$nTotal),1);
	$fHierarchy = $fDelegate = $fDirect = 0.0;
	if ( $nValid ) {
		$fHierarchy = number_format(100*($nHierarchy/$nValid),1);
		$fDelegate = 0;//number_format(100*($nDelegate/$nValid),2);
		$fDirect = number_format(100*($nDirect/$nValid),1);
	}/*}}}*/

	$contents_form = '';/*{{{*/
	if ( is_single() ) {
		$html_form = '';
		$vm_label = array( -1 => '모두반대', 0=>'기권' );
		if ( $dob_vm_type == 'updown' ) {
			$vm_label[1] = '찬성';
		} else {	// choice, plural
			foreach ( $dob_vm_data as $k => $v ) {
				$vm_label[$k+1] = $v;
			}
		}
		$html_form = dob_vote_display($post_id,$dob_vm_type,$vm_label,$result_stat,$user_id);
		$contents_form = "<li>
			<h3># $label_my</h3>
			<div class='panel'> $html_form </div>
		</li>";
	}/*}}}*/

	$dob_vote = <<<HTML
<ul id="toggle-view"><!--{{{-->
	<li class="toggle">
		<h3># $label_stat <small> // $label_turnout : $fValid% </small></h3><span class="toggler">[close]</span>
		<div class="panel">
			<table>
				<tr><td class="left">$label_valid</td><td>$fValid% ( $nValid / $nTotal )</td></tr>
				<tr><td class="left">$label_hierarchy</td><td>$fHierarchy% ( $nHierarchy / $nValid )</td></tr>
				<tr><td class="left">$label_delegate</td><td>$fDelegate% ( $nDelegate / $nValid )</td></tr>
				<tr><td class="left">$label_direct</td><td>$fDirect% ( $nDirect / $nValid )</td></tr>
			</table>
		</div>
	</li>
	<li class="toggle">
		<h3># $label_chart</h3><span class="toggler">[close]</span>
		<div class="panel"> $content_chart </div>
	</li>
	$contents_form
</ul><!--}}}-->
HTML;

	if ($bEcho) echo $dob_vote;
	else return $dob_vote;
}

function dob_vote_display($post_id,$vm_type,$vm_label,$result_stat,$user_id=0) {/*{{{*/
	ob_start();
	$myval = isset($result_stat['myval']) ? $result_stat['myval'] : '';
	if ( $vm_type=='plural' ) { // normalize plural value
		if ( $myval <= 1 ) $myval = array($myval);
		$vals = str_split(strrev(base_convert($myval,10,2)));
		//$myval = array_keys(array_filter($vals, function($v){return $v=='1';}));
		$myval = array();
		foreach ( $vals as $k => $v ) {
			if ( $v == '1' ) $myval[] = $k+1;
		}
	}
	// display area
	echo <<<HTML
		<div class="panel">
		<table>
			<form id="formDobVote" method="post">
			<input type="hidden" name="dob_vote_type" value="$vm_type">
HTML;
	wp_nonce_field( 'dob_vote_nonce_'.$vm_type, 'dob_vote_nonce' );
	foreach ( $vm_label as $val => $label ) {
		$stat = isset($result_stat[$val]) ? $result_stat[$val] : 0;
		$html_input = '';
		if ( $user_id ) {
			if ( $vm_type == 'plural' ) {
				$checked = in_array($val,$myval) ? 'CHECKED' : '';
				$html_input = "<input type='checkbox' name='dob_vote_val[$val]' value='1' $checked>";
			} else {
				$checked = ($val===$myval) ? 'CHECKED' : '';
				$html_input = "<input type='radio' name='dob_vote_val' value='$val' $checked>";
			}
		}
		echo <<<HTML
			<tr>
				<td class='left'> <label>$html_input $label</label> </td>
				<td>$stat</td>
			</tr>
HTML;
	}
	$label_login = '로그인 해주세요';		//__('Statistics', DOBslug);
	$html_submit = $label_login;
	if ( $user_id ) {
		$html_submit = dob_vote_get_message($post_id,$user_id);	// vote_post_latest timestamp
		$label_vote = '투표';	//__('Vote', DOBslug);
		$style = 'width:50px; height:20px; background:#ccc; color:black; text-decoration: none; font-size: 13px; margin: 0; padding: 0 10px 1px;';
		$html_submit .= " <input type='submit' value='$label_vote' style='$style'>";
	}
	echo <<<HTML
			<tr><td colspan=2 style="text-align:right;">$html_submit</td></tr>
			</form>
		</table>
		</div>
HTML;
	$ret = ob_get_contents();
	ob_end_clean();
	return $ret;
}/*}}}*/
