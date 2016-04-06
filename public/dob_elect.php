<?php
/**
 * Create site pages for this plugin
 */

#require_once('dob_user_hierarchy.inc.php');

add_action( 'wp', 'dob_elect_wp_init' );
function dob_elect_wp_init() {/*{{{*/
	wp_enqueue_script('dob-form-js', plugins_url('assets/js/dob_form.js',__FILE__), array('jquery'));
	wp_enqueue_style( 'toggle-css', plugins_url( 'assets/css/toggle.css', __FILE__ ) );
}/*}}}*/

function dob_elect_get_user_info($user_id) {/*{{{*/
	global $wpdb;

	$sql = <<<SQL
SELECT *
FROM {$wpdb->prefix}dob_user_category
	JOIN {$wpdb->prefix}users ON user_id=ID
WHERE taxonomy='hierarchy' AND user_id=$user_id
SQL;
	return $wpdb->get_row($sql);
}/*}}}*/

function dob_elect_get_latest($post_id,$user_id=0,$ttids=array()) {/*{{{*/
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

function dob_elect_get_log($post_id,$user_id) {/*{{{*/
	global $wpdb;
	$t_elect_log	= $wpdb->prefix . 'dob_elect_log';
	$sql = <<<SQL
SELECT *
FROM `$t_elect_log` 
WHERE post_id = $post_id AND user_id=$user_id
SQL;
	return $wpdb->get_results($sql);
}/*}}}*/

function dob_elect_get_message($post_id,$user_id) {/*{{{*/
	$message = '투표해 주세요';		//__('Please Vote', DOBslug);
	if ( $ret = dob_elect_get_latest($post_id,$user_id) ) {
		$label_last = '마지막 투표';		//__('Last Voted', DOBslug);
		$message = $label_last.' : '.$ret['ts'];
	}
	return $message;
}/*}}}*/

add_filter('the_content', 'dob_elect_site_content');
function dob_elect_site_content($content) {/*{{{*/
	$post_id = get_the_ID();
	if ( !is_page() && !is_feed() && $post_id 
		&& 'elect'==get_post_type($post_id)
	) {
		$dob_elect_content=dob_elect_contents($post_id);
		$content = $dob_elect_content . $content;
	}
	return $content;
}/*}}}*/

function dob_elect_get_selected_hierarchy_leaf_ttids($post_id) {/*{{{*/
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
	if ( 1 == count($all) ) {	// root selected
		$cur = current($all);
		if ( $cur['parent'] == '0' ) {
			return null;
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

function dob_elect_get_users_count( $ttids = array() ) {/*{{{*/
	global $wpdb;
	$t_user_category = $wpdb->prefix.'dob_user_category';
	$sql_ttids = empty($ttids) ? 'AND term_taxonomy_id <> 0'
		: ' AND term_taxonomy_id IN ('.implode(',',$ttids).')';
	$sql = "SELECT COUNT(1) FROM $t_user_category 
		WHERE taxonomy='hierarchy' $sql_ttids";
	return (int)$wpdb->get_var($sql);
}/*}}}*/

function dob_elect_cart( $user_id, $post_id ) {/*{{{*/
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
		return 'check2 : '.print_r($_POST,true);
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

	$t_cart = $wpdb->prefix.'dob_cart';
	$sql = "SELECT value FROM `$t_cart` 
		WHERE user_id = $user_id AND type='elect' AND post_id = $post_id";
	$old_val = $wpdb->get_var($sql);
	// UPDATE dob_elect_latest
	if ( is_null($old_val) ) {
		$sql = sprintf("INSERT INTO `$t_cart` SET
			user_id = %d, type='elect', post_id = %d, value = %d",
			$user_id, $post_id, $value 
		);
	} else {			
		$sql = sprintf("UPDATE `$t_cart` 
				SET value = %d, ts=CURRENT_TIMESTAMP
			WHERE user_id = %d AND type='elect' AND post_id = %d",
			$value, $user_id, $post_id 
		);
	}
	$success = $wpdb->query( $sql );	// success == 1 (affected_rows)
	return $ret = $success ? '' : "DB ERROR(SQL)<br>\n: ".$sql;

}/*}}}*/

function dob_elect_update( $user_id, $post_id ) {/*{{{*/
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
		return 'check2 : '.print_r($_POST,true);
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

	// INSERT dob_elect_log
	$dml = array (
		sprintf("INSERT IGNORE INTO `{$wpdb->prefix}dob_elect_log` 
			SET user_id = %d, post_id = %d, value = %d, ip = '%s'",
			$user_id, $post_id, $value, $global_real_ip 
		),
	);

	$t_latest = $wpdb->prefix.'dob_elect_latest';
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

function dob_elect_accum_stat( &$stat, $type, $value, $cnt=1) {/*{{{*/
	if ( $type=='updown' || $type=='choice' || $value <= 1 ) { 
		$stat[$value] = isset($stat[$value]) ? $stat[$value]+$cnt : $cnt;
	} else {
		$arr1 = str_split(base_convert($value,10,2));
		foreach ( $arr1 as $k=>$v ) {
			if ( '1' == $v ) $stat[$k+1] = isset($stat[$k+1]) ? $stat[$k+1]+$cnt : $cnt;
		}
	}
}/*}}}*/

function dob_elect_contents( $post_id, $bEcho = false) {
#echo '<pre>';
	global $wpdb;
	$dob_elect_cmb_vote = get_post_meta( $post_id, 'dob_elect_cmb_vote', true );
	$vm_type = empty($dob_elect_cmb_vote['type']) ? 'updown': $dob_elect_cmb_vote['type'];
	$vm_data = empty($dob_elect_cmb_vote['data']) ? array() : $dob_elect_cmb_vote['data'];
	$vm_begin = empty($dob_elect_cmb_vote['begin']) ? '' : $dob_elect_cmb_vote['begin'];
	$vm_end = empty($dob_elect_cmb_vote['end']) ? '' : $dob_elect_cmb_vote['end'];

	$user_id = get_current_user_id();
	$LOGIN_IP = empty($_SESSION['LOGIN_IP']) ? '' : $_SESSION['LOGIN_IP'];
	if ( $user_id ) {
		$debug = '';
		if ( ! empty($_POST) && $LOGIN_IP == dob_get_real_ip() ) {
			if ( (int)$_POST['dob_form_cart'] ) {
				$debug = dob_elect_cart($user_id,$post_id);
			} else {
				$debug = dob_elect_update($user_id,$post_id);
			}
		}
		if ( $debug ) {
			echo "<pre>$debug</pre>";
		}
	}

	$ttids = dob_elect_get_selected_hierarchy_leaf_ttids($post_id);
	$elect_latest = dob_elect_get_latest($post_id,0,$ttids);	// user_id => rows	// for login_name
	// build final vote results.
	$nDirect = count($elect_latest);
	$myinfo = $user_id ? dob_elect_get_user_info($user_id) : null;
	$myval = isset($elect_latest[$user_id]) ? (int)$elect_latest[$user_id]['value'] : '';
#print_r($elect_latest);

	## build HTML /*{{{*/
	//$label_title		= '균형 투표';	//__('Balance Voting', DOBslug);
	$label_stat			= '선거정보';			//__('Statistics', DOBslug);
	$label_time			= '투표시간';			//__('Statistics', DOBslug);
	$label_turnout	= '투표율';				//__('Total Users', DOBslug);
	$label_result		= '투표결과';			//__('My Vote', DOBslug);
	$label_before 	= '시작전';			//__('Statistics', DOBslug);
	$label_ing			= '진행중';			//__('Statistics', DOBslug);
	$label_after		= '종료됨';			//__('Statistics', DOBslug);
	$label_my				= '내 투표';			//__('My Vote', DOBslug);
	$label_history	= '기록';				//__('My Vote', DOBslug);
	$label_login		= '로그인 해주세요';	//__('Please Login', DOBslug);
	/*}}}*/

	$nTotal = dob_elect_get_users_count($ttids);	// get all user count
	$fValid = number_format(100*($nDirect/$nTotal),1);
	$html_timer = $html_chart= $html_form = $html_history = '';
	if ( is_single() ) {
		$vm_label = ($vm_type=='updown') ? /*{{{*/ 
			array( -1=>'반대', 0=>'기권' ) : array( -1=>'모두반대', 0=>'기권' );
		if ( $vm_type == 'updown' ) {
			$vm_label[1] = '찬성';
		} else {	// choice, plural
			foreach ( $vm_data as $k => $v ) {
				$vm_label[$k+1] = $v;
			}
		}/*}}}*/

		$ts = time();
		$html_timer = dob_elect_html_timer($ts);
		if ( $ts < strtotime($vm_begin) ) {	// BEFORE
			$label_result .= ' : '.$label_before;
		} elseif ( strtotime($vm_begin) < $ts && $ts < strtotime($vm_end) ) {
			// VOTING
			$label_result .= ' : '.$label_ing;
			$content_form = "<a href='http://wp1.youthpower.kr/wp-login.php' style='color:red; font-weight:bold'>$label_login</a>";
			if ( $user_id ) {
				if ( ! in_array($myinfo->term_taxonomy_id,$ttids) ) {
					$label_restrict = '선거대상 계층이 아닙니다.';	//__('Your hierarchy is not available in this voting.', DOBslug);
					$content_form = "<span style='color:red; font-weight:bold'>$label_restrict</span>";
				} elseif ( isset($_SESSION['LOGIN_IP']) && $_SESSION['LOGIN_IP'] == dob_get_real_ip() ) {
					$content_form = dob_elect_display_mine($post_id,$vm_type,$vm_label,$myval,$user_id);
				} else {
					$content_form = '로그인 이후 1시간이 지났거나, 네트워크가 초기화 되었으니, 다시 로그인해 주세요<br>투표시에는 네트워크(WIFI,LTE,3G)를 변경하지 마세요.';	//__('You passed 1-hours after login, or Your network was Changed. Please Login AGAIN.', DOBslug);
				}
			}
			$html_form = <<<HTML
		<li>
			<h3># $label_my</h3>
			<div class='panel'>
				$content_form 
			</div>
		</li>
HTML;
		} else {	// AFTER
			$label_result .= ' : '.$label_after;
			$result_stat = array();
			foreach ( $elect_latest as $uid => $v ) {
				dob_elect_accum_stat($result_stat,$vm_type,$v['value'],1);
			}
#print_r($result_stat);
			$html_chart = dob_elect_html_chart($result_stat,$vm_label,$nTotal);
		}

		if ( $user_id ) {
			$html_history = <<<HTML
			<li class='toggle'>
				<h3># $label_my $label_history<span class='toggler'>[open]</span></h3>
				<div class='panel' style='display:none'>
					<table id='table_log'>
						<tr><th>date_time</th><th>value</th><th>ip</th></tr>
HTML;
			foreach ( dob_elect_get_log($post_id,$user_id) as $log ) {
				$html_history .= <<<HTML
						<tr><td>{$log->ts}</td><td>{$log->value}</td><td>{$log->ip}</td></tr>
HTML;
			}
			$html_history .= <<<HTML
					</table>
				</div>
			</li>
<style>
#table_log th { background-color:#eee; text-transform:none; text-align:center; padding:0; }
</style>
HTML;
		}
	}
#echo '</pre>';

	$dob_elect = <<<HTML
<ul id="toggle-view"><!--{{{-->
	<li>
		<h3># $label_stat</h3>
		<div class="panel">
			<div>$label_time : $vm_begin ~ $vm_end</div>
			<div>$label_turnout : $fValid% ( $nDirect / $nTotal )</div>
		</div>
	</li>
	$html_timer 
	$html_chart
	$html_form
	$html_history
</ul><!--}}}-->
HTML;

	if ($bEcho) echo $dob_elect;
	else return $dob_elect;
}

function dob_elect_html_chart($result_stat,$vm_label,$nTotal) {/*{{{*/
	$ret = <<<HTML
<style>
#table_barchart { width: 100%; height:20px; }
#table_barchart td div { text-align:center; overflow: hidden; text-overflow: ellipsis; }
#table_barchart .cn { background-color: tan; } /*TANGERINE ;*/
#table_barchart .c0 { background-color: LIGHT BLUE ; }
#table_barchart .c1 { background-color: TAN ; }
#table_barchart .c2 { background-color: GOLD   ; }
#table_barchart .c3 { background-color: CAROLINA BLUE ; }
#table_barchart .c4 { background-color: SAPPHIRE ; }
#table_barchart .c5 { background-color: SAFETY PINK ; }
#table_barchart .c6 { background-color: LIME ; }
#table_barchart .c7 { background-color: CARDINAL RED ; }
#table_barchart .un { background-color: #E5E4E2 ; }
</style>
	<table id="table_barchart" border=1>
HTML;
	arsort($result_stat);
	$nUnpolled = $nTotal;
	$legends = array();
	$htmls = array();
	foreach ( $result_stat as $k => $v ) {
		$class = ($k<0) ? 'cn' : 'c'.$k;
		$legends[$class] = $vm_label[$k];
		$f = number_format(100*($v/$nTotal),1);
		$text = (10<$f) ? "$v($f%)" : "<span title='$f%'>$v</span>" ;
		$htmls[] = "<td width='$f%' class='$class'><div>$text</div></td>";
		$nUnpolled -= $v;
	}
	if ( $nUnpolled ) {
		$legends['un'] = $label_unpolled = '미투표';		//__('Unpolled', DOBslug);
		$f = number_format(100*($nUnpolled/$nTotal),1);
		$text = (10<$f) ? "$nUnpolled($f%)" : "<span title='$f%'>$nUnpolled</span>" ;
		$htmls[] = "<td width='$f%' class='un'><div>$text</div></td>";
	}
	$ret .= '<tr>'.implode(' ',$htmls).'</tr>';
	$htmls = array();
	foreach ( $legends as $class => $label ) {
		$htmls[] = "<td class='$class'><div>$label</div></td>";
	}
	$ret .= '<tr>'.implode(' ',$htmls).'</tr>';
	$ret .= '</table>';
	return $ret;
}/*}}}*/

function dob_elect_html_chart_d3($result_stat,$vm_type) {/*{{{*/
	//$label_now			= '현재 서버시각';			//__('Statistics', DOBslug);
	$url_assets = DOBurl.'assets';
	$ret = <<<HTML
<script src="$url_assets/d3.min.js"></script>
<script src="$url_assets/c3.min.js"></script>
<link href="$url_assets/c3.min.css" rel="stylesheet" type="text/css">
<div id="chart"></div>
<script>
jQuery(document).ready(function($){
	var chart = c3.generate({/*{{{*/
		bindto: '#chart',
		size:{height:100},
		data: {
			type: 'bar',
			columns: [
				['data3', 2300],
				['data2', 1300],
				['data1', 300 ]
			],
			groups: [
				['data1', 'data2', 'data3']
			]
		},
		axis: {
			rotated: true,
		},
		tooltip: {
			title: function (d) { return 'Data ' + d; },
			value: function (value, ratio, id) {
				var format = id === 'data1' ? d3.format(',') : d3.format('$');
				return format(value);
			}
			//value: d3.format(',')
		}
	});/*}}}*/

	/*{{{*//*var chart = c3.generate({
		bindto: '#chart',
		size: { height: 150 },
		bar: { width: 40 },
		padding: { left: 60 },
		color: { pattern: ['#FABF62', '#ACB6DD'] },
		data: {
			x: 'x',
			columns:
			[
				['x', 'Category1', 'Category2'],
				['value', 300, 400]
			],
			type: 'bar',
			color: function(inColor, data) {
				var colors = ['#FABF62', '#ACB6DD'];
				if(data.index !== undefined) {
					return colors[data.index];
				}
				return inColor;
			}
		},
		axis: {
			rotated: true,
			x: { type: 'category' }
		},
		tooltip: { grouped: false },
		legend: { show: false },
	});*//*}}}*/

});
</script>
HTML;
	return $ret;
}/*}}}*/

function dob_elect_html_timer($ts) {/*{{{*/
	$label_now	= '현재 서버시각';			//__('Statistics', DOBslug);
	$ret = $label_now.' : <span id="timer">'.date('Y-m-d H:i:s',$ts).'</span>';
	$ret .= PHP_EOL.<<<HTML
<script src="http://momentjs.com/downloads/moment.min.js"></script>
<script>
jQuery(document).ready(function($){
	var ts = $ts;
	function date_time() {
		now = moment(ts,'X').format('YYYY-MM-DD HH:mm:ss');
		document.getElementById('timer').innerHTML = now;
		setTimeout(function () { ts+=1; date_time(); }, 1000);
	}
	date_time();
});
</script>
HTML;
	return $ret;
}/*}}}*/

function dob_elect_display_mine($post_id,$vm_type,$vm_label,$myval='',$user_id) {/*{{{*/
	ob_start();
	//session_unset();	// $_SESSION = array();
	$_SESSION['user_id'] = $user_id;
	$_SESSION['post_id'] = $post_id;
	$_SESSION['secret'] = $secret = base64_encode(openssl_random_pseudo_bytes(20));
	if ( $vm_type=='plural' ) { // normalize plural value
		if ( $myval <= 1 ) $myval = array($myval);
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
				$label_secret : <input type="text" name="dob_elect_secret" value="$secret" style="width:300px" READONLY>
				<br><b>$label_remember</b>
			</td></tr-->
			<tr><td>
HTML;
	wp_nonce_field( 'dob_form_nonce_'.$vm_type, 'dob_form_nonce' );
	foreach ( $vm_label as $val => $label ) {
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
	$html_submit = '';
	if ( $user_id ) {
		$html_submit = dob_elect_get_message($post_id,$user_id);	// vote_post_latest timestamp
		$label_vote = '바로투표';	//__('Vote', DOBslug);
		$label_cart = '투표바구니';	//__('Vote', DOBslug);
		$style = 'width:100px; height:20px; background:#ccc; color:black; text-decoration: none; font-size: 13px; margin: 0; padding: 0 10px 1px;';
		$html_submit .= " <input id='btn_fast' type='button' value='$label_vote' style='$style' >";
		$html_submit .= " <input id='btn_cart' type='button' value='$label_cart' style='$style' >";
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
