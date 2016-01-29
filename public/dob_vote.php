<?php
/**
 * Create site pages for this plugin
 */

require_once('dob_user_hierarchy.inc.php');

add_action( 'wp', 'dob_vote_wp_init' );
function dob_vote_wp_init() {/*{{{*/
	// updown
	//wp_enqueue_script('dob-bdd-js', plugins_url('assets/js/bdd.js',__FILE__), array('jquery'));
	//wp_localize_script('dob-bdd-js', 'dob_bdd_js', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));
	wp_enqueue_style( 'bdd-css', plugins_url( 'assets/css/bdd.css', __FILE__ ) );
	// choice
	wp_enqueue_script('dob-vote-js', plugins_url('assets/js/vote.js',__FILE__), array('jquery'));
	wp_localize_script('dob-vote-js', 'dob_vote_js', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));
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
	$t_users = $wpdb->prefix . 'users';
	$sql = <<<SQL
SELECT $t_latest.*, user_login
FROM `{$t_latest}` JOIN $t_users ON user_id=ID
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
		$dob_cmb_vote = get_post_meta( $post_id, 'dob_cmb_vote', true );
		$dob_vm_type = empty($dob_cmb_vote['type']) ? 'updown': $dob_cmb_vote['type'];
		$dob_vm_data = empty($dob_cmb_vote['data']) ? array() : $dob_cmb_vote['data'];

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

function dob_vote_content_updown( $post_id/*=get_the_ID()*/, $bEcho = false) {/*{{{*/
	global $wpdb;
	$dob_vote = '';

	// Get the posts ids where we do not need to show like functionality
/*{{{*/	/*	$allowed_posts = $excluded_posts = $excluded_categories = $excluded_sections = array();
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

/*{{{*/	/* Checking for excluded categories
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
	}*//*}}}*/

	$title_text_like = 'Like';
	$title_text_unlike = 'Unlike';
/*{{{*/	/* Check for title text. if empty then have the default value
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
		'nRecalc'		=> $nSelf+$nLow,
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
		WHERE term_taxonomy_id=$term_taxonomy_id";
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

function dob_vote_decide_updown( $point, $user_votes ) {/*{{{*/
	$ret = 0;
	$values = array();
	foreach( $user_votes as $uid => $value ) {
		$str = (string)$value;
		if ( isset($values[$str]) ) {
			$values[$str] += 1;
		} else {
			$values[$str] = 1;
		}
	}

	// check the critical point
	foreach( $values as $str => $cnt ) {
		if ( $cnt >= $point ) {
			return (int)$str;
		}
	}
	return 0;
}/*}}}*/

function dob_vote_calc_choice( $point, $uid_vals ) {/*{{{*/
	$ret = 0;
	$values = array();
	foreach( $uid_vals as $uid => $value ) {
		$arr1 = str_split(base_convert($value,10,2));
		$arr2 = array_map('intval',$arr1);
		foreach( $arr2 as $k => $v ) {
			if ( isset($values[$k]) ) {
				$values[$k][] = $v;
			} else {
				$values[$k] = array($v);
			}
		}
	}

	// check the critical point
	$results = array();
	foreach( $values as $k => $v ) {
		$results[$k] = ($point<=array_sum($v)) ? '1' : '0';
	}
	$result = implode('',$results);
	$ret = base_convert($result,2,10);

	return $ret;
}/*}}}*/

function dob_vote_filter_choice( $value, $dob_vm_data ) {/*{{{*/
	$ret = array();
	$arr1 = base_convert($value,10,2);
	$arr2 = array_reverse($arr1);
	foreach ( $arr2 as $k=>$v ) {
		if ( '1' == $v ) {
			$ret[] = $dob_vm_data[$k];
		}
	}
	return $ret;
}/*}}}*/

function dob_vote_contents( $dob_vm_type, $post_id, $dob_vm_data, $bEcho = false) {
echo '<pre>';
	global $wpdb;
	$user_id = get_current_user_id();

#$ts = microtime(true);
	$influences = dob_vote_get_hierarchy_influence();	// influences by term_taxonomy_id
#file_put_contents('/tmp/in.php',print_r($influences,true));
#file_put_contents('/tmp/in.php',PHP_EOL.(microtime(true)-$ts),FILE_APPEND);
	//useless, $post_leaf_hierarchy = dob_vote_get_post_hierarchy_leaf($post_id,$influences);

	$vote_latest = dob_vote_get_post_latest($post_id);	// user_id => rows	// for login_name
	$aDelegate = array();
	$nDelegate = $nHierarchy = $nDirect = 0;
	$hierarchy_voter = dob_vote_get_hierarchy_voter($post_id);	// order by lft
#print_r($hierarchy_voter);
#print_r($vote_latest);
	foreach ( $hierarchy_voter as $ttid => $v ) {
		$tname		= $v['tname'];
		$slug			= $v['slug'];
		$lvl			= $v['lvl'];
		$uid_vals	= $v['uid_vals'];
		$uv_count	= count($uid_vals);

		// check direct voting
		if ( $influences[$ttid]['bLeaf'] ) {
			$nDirect += $uv_count;
			// recalc ancestor's influences
			foreach ( $influences[$ttid]['ancestor'] as $a_ttid ) {
				if ( isset($hierarchy_voter[$a_ttid]) ) {	// only exists
					$hierarchy_voter[$a_ttid]['inf'] -= $uv_count;
				}
			}
			// self added leaf data
			$hierarchy_voter[$ttid]['value'] = null;
		} else {
			$value = 0;	// decision value
			// check minimum turnout
			$all_ids = dob_vote_get_user_hierarchy($ttid);
			$total = count($all_ids);
			if ( 1 == $total ) {
				$value = current($uid_vals);
			} elseif ( 1 < $total ) {
				$point = $total*(2/3);
				if ( $point <= $uv_count ) {
					if ( $dob_vm_type == 'updown' ) {
						$value = dob_vote_decide_updown($point,$uid_vals);
					} elseif ( $dob_vm_type == 'choice' ) {
						$value = dob_vote_calc_choice($point,$uid_vals);
					} elseif ( $dob_vm_type == 'plural' ) {
						$value = dob_vote_calc_plural($point,$uid_vals);
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
		}
		// self added common data
		$hierarchy_voter[$ttid]['inf'] = $influences[$ttid]['nTotal'];
		$hierarchy_voter[$ttid]['bLeaf'] = $influences[$ttid]['bLeaf'];
		$hierarchy_voter[$ttid]['count'] = $uv_count;
	}
#print_r($hierarchy_voter);

	$h_chart = array();
	foreach ( $hierarchy_voter as $ttid => $v ) {
		if ( $v['bLeaf'] ) {
			$h_chart[] = $indent.$v['tname']."({$v['inf']}) : direct";
		} else {
			$yes = $no = array();
			foreach ( $v['uid_vals'] as $uid => $val ) {
				$yes[] = $vote_latest[$uid]['user_login'].':'.$val; //$vote_latest[$uid]['value'];
			}
			if ( ! $v['bLeaf'] ) {
				$no = array_diff($v['all_ids'],array_keys($v['uid_vals']));
			}
			$indent = ' ~ '.str_repeat(' ~ ',$v['lvl']);
			$yes = implode(',',$yes);
			$no = empty($no) ? '' : '//'.implode(',',$no);
			$h_chart[] = $indent.$v['tname']."({$v['inf']}) : {$v['value']} ($yes) $no";
		}
	}
	$content_chart = implode('<br>',$h_chart);

	// build final vote results.
	$result_stat = array();
	if ( $dob_vm_type == 'updown' ) {
		$result_stat = array('1'=>0, '0'=>0, '-1'=>0);
		foreach ( $hierarchy_voter as $ttid => $v ) {
			if ( $v['bLeaf'] ) {
				foreach ( $v['uid_vals'] as $uid => $val ) {
					$result_stat[(string)$val] += 1;
				}
			} else if ( $v['value'] ) {	// non-leaf
				$nHierarchy+=$v['inf'];
				$result_stat[(string)$v['value']] += $v['inf'];
			}
		}
	}

echo '</pre>';
	##############
	# build HTML #
	##############
	// Get the nonce for security purpose and create the like and unlike urls
	$args = http_build_query( array (
		'post_id'	=> $post_id,
		'nonce'		=> wp_create_nonce('dob_vote_nonce'),
		'action'	=> '',
		'task'		=> '',
	) );
	$ajax_url = admin_url('admin-ajax.php?'.$args);

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
	$fHierarchy = number_format(100*($nHierarchy/$nValid),1);
	$fDelegate = 0;//number_format(100*($nDelegate/$nValid),2);
	$fDirect = number_format(100*($nDirect/$nValid),1);

	$contents_ajax = dob_vote_display_updown($post_id,$result_stat,$user_id);

	$dob_vote = <<<HTML
<ul id="toggle-view">
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
		<div class="panel">
			$content_chart
		</div>
	</li>
	<li>
		<h3># $label_my</h3>
		<div class="panel">
			$contents_ajax 
			<!--form name="post" action="http://wp2.ncross.net/wp-admin/post.php" method="post" id="quick-press" class="initial-form hide-if-no-js">
				<div class="input-text-wrap" id="title-wrap">
					<label class="prompt" for="title" id="title-prompt-text"> 제목</label>
					<input type="text" name="post_title" id="title" autocomplete="off">
				</div>

				<div class="textarea-wrap" id="description-wrap">
					<label class="prompt" for="content" id="content-prompt-text">무슨 생각을 하고 계신가요?</label>
					<textarea name="content" id="content" class="mceEditor" rows="3" cols="15" autocomplete="off"></textarea>
				</div>

				<p class="submit">
					<input type="hidden" name="action" id="quickpost-action" value="post-quickdraft-save">
					<input type="hidden" name="post_ID" value="7">
					<input type="hidden" name="post_type" value="post">
					<input type="hidden" id="_wpnonce" name="_wpnonce" value="212c60aba9"><input type="hidden" name="_wp_http_referer" value="/wp-admin/">
					<input type="submit" name="save" id="save-post" class="button button-primary" value="임시 글로 저장하기">
				</p>
			</form-->
		</div>
	</li>
</ul>
HTML;

	if ($bEcho) echo $dob_vote;
	else return $dob_vote;
}

function dob_vote_display_updown($post_id,$result_stat,$user_id=0) {/*{{{*/
	$like_count		= $result_stat['1'];
	$unlike_count	= $result_stat['-1'];
	$title_text_like = 'Like';
	$title_text_unlike = 'Unlike';
	$nonce = wp_create_nonce('dob_vote_vote_nonce');
	$alignment = 'right';
	$url_pixel = plugins_url( 'assets/images/pixel.gif' , __FILE__ );
	$style = 'style1'; //(get_option('dob_vote_voting_style') == "") ? 'style1' : get_option(..);
	$label_login = '로그인 해주세요';		//__('Statistics', DOBslug);
	$msg = empty($user_id) ? $label_login : dob_vote_get_message($post_id,$user_id);

	return <<<HTML
	<div class='watch-action'>
		<div class='watch-position align-$alignment'>
			<div class='action-like'>
				<a class='lbg-$style like-$post_id jlk' href='javascript:void(0)' data-task='like' data-post_id='$post_id' data-user_id='$user_id' data-nonce='$nonce' rel='nofollow'>
					<img src='$url_pixel' title='$title_text_like' />
					<span class='lc-$post_id lc'>$like_count</span>
				</a>
			</div>
			<div class='action-unlike'>
				<a class='unlbg-$style unlike-$post_id jlk' href='javascript:void(0)' data-task='unlike' data-post_id='$post_id' data-user_id='$user_id' data-nonce='$nonce' rel='nofollow'>
					<img src='$url_pixel' title='$title_text_unlike' />
					<span class='unlc-$post_id unlc'>$unlike_count</span>
				</a>
			</div> 
		</div> 
		<div class='status-$post_id status align-$alignment'>$msg</div>
	</div>
	<div class='wti-clear'></div>
HTML;
}/*}}}*/
