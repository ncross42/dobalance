<?php
/**
 * Create site pages for this plugin
 */

require_once('dob_common.inc.php');

//add_action( 'wp', 'dob_elect_wp_init' );
//function dob_elect_wp_init() { }

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

function dob_elect_accum_stat( &$stat, $type, $value, $cnt=1) {/*{{{*/
	if ( $type=='updown' || $type=='choice' || $value <= 1 ) { 
		$stat[$value] = isset($stat[$value]) ? $stat[$value]+$cnt : $cnt;
	} else {
		$arr1 = str_split(strrev(base_convert($value,10,2)));
		foreach ( $arr1 as $k=>$v ) {
      if ( '1' == $v )
        $stat[$k+1] = isset($stat[$k+1]) ? $stat[$k+1]+$cnt : $cnt;
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
  $bVote = false;
	if ( is_single() && $user_id ) {
		$debug = '';
		if ( ! empty($_POST) && $LOGIN_IP == dob_get_real_ip() ) {
      $bVote = true;
			if ( (int)$_POST['dob_form_cart'] ) {
				$debug = dob_common_cart($user_id,$post_id,'elect');
			} else {
				$debug = dob_common_update($user_id,$post_id,'elect');
			}
		}
		if ( $debug ) {
			echo "<pre>$debug</pre>";
		}
	}

  // load CACHE
  $ts_struct = $ts_all = $ts_stat = '';
  $ts_post = get_the_modified_time('Y-m-d H:i:s');
  $cached_struct = dob_common_cache(-1,'all',false,$ts_struct);
  $cached_all = dob_common_cache($post_id,'all',false,$ts_all);
  $cached_stat_json = dob_common_cache($post_id,'stat',false,$ts_stat,false);
  $ttids = $elect_latest = $result_stat = $stat = null;
  if ( is_array($cached_all) ) extract($cached_all); 
  $nTotal = null; $nDirect = null;
  if ( !empty($stat) ) extract($stat);

  // ttids: 신규, 포스트변경, 계층변경
  $bTtids = false;
  if ( $ttids===null || $ts_all<$ts_post || $ts_all<$ts_struct ) {
    $ttids = dob_common_get_selected_hierarchy_leaf_ttids($post_id);
    $bTtids = true;
  }

  // nTotal: 신규, 포스트변경, 계층변경
  if ( $nTotal===null || $ts_all<$ts_post || $ts_all<$ts_struct ) {
    $nTotal = dob_common_get_users_count($ttids);	// get all user count
  }

  // elect_latest, stat_detail: 신규, 투표, 포스트변경, 계층변경
  $bLatest = false;
  if ( $elect_latest===null || $bVote || $ts_all<$ts_post || $ts_all<$ts_struct ) {
    $elect_latest = dob_common_get_latest_by_ttids($post_id,$ttids,'elect');
    // user_id => rows	// for login_name
    $nDirect = is_array($elect_latest) ? count($elect_latest) : 0;
    $bLatest = true;
  }

	// build final vote results.
	$myinfo = $user_id ? dob_common_get_user_info($user_id) : null;
	$myval = isset($elect_latest[$user_id]) ? (int)$elect_latest[$user_id]['value'] : '';
#print_r($elect_latest);

  // Cache STAT, 통계: 신규, 실제 통계값 변경
  $bStat = false;
  $stat = ['nDirect'=>$nDirect,'nTotal'=>$nTotal];
  $stat_json = json_encode($stat,JSON_UNESCAPED_UNICODE);
  if ( empty($cached_stat_json) || $cached_stat_json != $stat_json ) {
    $ts_now = date('Y-m-d H:i:s');
    dob_common_cache($post_id,'stat',$stat_json,$ts_now,false);
    $bStat = true;
  }

  $bResult = false;
  if ( strtotime($vm_end)<time() && 
    ( $result_stat===null || $bTtids || $bLatest || $bStat )
  ){ // AFTER voting period
    $result_stat = array();
    foreach ( $elect_latest as $uid => $v ) {
      dob_elect_accum_stat($result_stat,$vm_type,$v['value'],1);
    }
    $bResult = true;
  }

  if ( $bTtids || $bLatest || $bStat || $bResult ) {
    // Cache Results, 결과: 신규, 포스트변경, 계층변경
    $data = compact('ttids','elect_latest','result_stat','stat'); 
    dob_common_cache($post_id,'all',$data);
  }

	## build HTML /*{{{*/
	//$label_title		= '균형 투표';	//__('Balance Voting', DOBslug);
	$label_result		= '투표결과';			//__('My Vote', DOBslug);
	$label_before 	= '시작전';			//__('Statistics', DOBslug);
	$label_ing			= '진행중';			//__('Statistics', DOBslug);
	$label_after		= '종료됨';			//__('Statistics', DOBslug);
	$label_chart		= '결과 차트';			//__('Direct voter', DOBslug);
	$label_my				= '내 투표';			//__('My Vote', DOBslug);
	$label_history	= '기록';				//__('My Vote', DOBslug);
	$label_login		= '로그인 해주세요';	//__('Please Login', DOBslug);
	/*}}}*/

	$html_timer = $html_chart = $html_form = $html_history = '';
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
		} elseif ( strtotime($vm_begin) < $ts && $ts < strtotime($vm_end) ) { // VOTING /*{{{*/
			$label_result .= ' : '.$label_ing;
			$content_form = "<a href='/wp-login.php' style='color:red; font-weight:bold'>$label_login</a>";
			if ( $user_id ) {
        if ( is_null($ttids) ) {
					$content_form = '선거대상 계층이 지정되지 않았습니다.';	//__('Election Hierarchy does not selected.', DOBslug);
				} elseif ( ( empty($ttids) && empty($myinfo->term_taxonomy_id) )  // ROOT hierarchy and no user_hierarchy
          || ( is_array($ttids) && !empty($ttids) && !in_array($myinfo->term_taxonomy_id,$ttids) ) // NORMAL hierarchy and user didn't assigned
        ) {
					$label_restrict = '선거대상 계층이 아닙니다.';	//__('Your hierarchy is not available in this voting.', DOBslug);
					$content_form = "<span style='color:red; font-weight:bold'>$label_restrict</span>";
				} elseif ( isset($_SESSION['LOGIN_IP']) && $_SESSION['LOGIN_IP'] == dob_get_real_ip() ) {
					$content_form = dob_elect_display_mine($post_id,$vm_type,$vm_label,$myval,$user_id);
				} else {
					$content_form = '로그인 이후 1시간이 지났거나, 네트워크가 변경되었으니, 다시 로그인해 주세요<br>투표시에는 네트워크(WIFI,LTE,3G)를 변경하지 마세요.';	//__('You passed 1-hours after login, or Your network was Changed. Please Login AGAIN.', DOBslug);
				}
			}
			$html_form = <<<HTML
    <div class="panel panel-default">
      <div class="panel-heading" data-toggle="collapse" data-target="#dob_elect_my">
        <span class="panel-title">$label_my</span>
      </div>
      <div id="dob_elect_my" class="panel-collapse collapse in">
				$content_form 
      </div>
    </div>
HTML;
      /*}}}*/
		} else {	// AFTER /*{{{*/
			$label_result .= ' : '.$label_after;
			$content_chart = dob_elect_html_chart($result_stat,$vm_label,$nTotal,$nDirect,$vm_type=='plural');
      $html_chart = <<<HTML
    <div class="panel panel-default">
      <div class="panel-heading" data-toggle="collapse" data-target="#dob_elect_html_chart">
        <span class="panel-title">$label_chart</span>
      </div>
      <div id="dob_elect_html_chart" class="panel-collapse collapse in">
        $content_chart
      </div>
    </div>
HTML;
		} /*}}}*/
	}

	$html_stat = dob_elect_html_stat($nDirect,$nTotal,$vm_begin,$vm_end,false);

	$dob_elect = <<<HTML
	$html_stat
	$html_chart
	$html_form
	$html_history
HTML;

	if ($bEcho) echo $dob_elect;
	else return $dob_elect;
}

function dob_elect_html_stat($nDirect,$nTotal,$vm_begin,$vm_end,$bTable=false) {/*{{{*/
	$label_stat    = '기본 정보';			//__('Statistics', DOBslug);
	$label_begin   = '시작';			//__('Statistics', DOBslug);
	$label_end     = '종료';			//__('Statistics', DOBslug);
	$label_time    = '투표시간';			//__('Statistics', DOBslug);
	$label_turnout = '투표율';				//__('Total Users', DOBslug);

  $fValid = empty($nTotal) ? '0.0%' : sprintf('%0.1f%%',100*($nDirect/$nTotal));

  return $bTable ? 
    <<<HTML
  <div class="panel-group">
    <div class="panel panel-default">
      <div class="panel-heading" data-toggle="collapse" data-target="#dob_elect_html_stat">
        <span class="panel-title">$label_stat <span class="label label-primary pull-right">$fValid</span></span>
      </div>
      <div id="dob_elect_html_stat" class="panel-collapse collapse in">
        <table class="table-bordered">
          <tr><td style="width:60px">$label_begin</td><td>$vm_begin</td></tr>
          <tr><td style="width:60px">$label_end</td><td>$vm_end</td></tr>
          <tr><td style="width:60px">$label_turnout</td><td>$fValid <span class="bg-success pull-right">$nDirect / $nTotal</span></td></tr>
        </table>
      </div>
    </div>
  </div>
HTML
  : <<<HTML
    <div class="panel panel-default" style="clear:both;">
      <div class="panel-heading" data-toggle="collapse" data-target="#dob_elect_stat">
        <span class="panel-title">$label_stat</span>
      </div>
      <div id="dob_elect_stat" class="panel-collapse collapse in">
        <div>$label_time : $vm_begin ~ $vm_end</div>
        <div>$label_turnout : $fValid ( $nDirect / $nTotal )</div>
      </div>
    </div>
HTML;

}/*}}}*/

function dob_elect_html_chart($result_stat,$vm_label,$nTotal,$nDirect,$bPlural=false) {/*{{{*/
	$ret = /*{{{*/ "<style>
.barchart { width: 100%; height:25px; border-collapse: collapse; }
.barchart td div { height:20px; text-align:center; overflow: hidden; text-overflow: ellipsis; }
.barchart .c-1 { background-color: BLUE; color:white; } /*TANGERINE ;*/
.barchart .c0 { background-color: #FFF; }
.barchart .c1 { background-color: RED; color:white; }
.barchart .c2 { background-color: GREEN; color:white; }
.barchart .c3 { background-color: TAN; }
.barchart .c4 { background-color: GOLD ; }
.barchart .c5 { background-color: #B2FFFF; } /*sky blue;*/ 
.barchart .c6 { background-color: PINK; }
.barchart .c7 { background-color: LIME; }
.barchart .bl { background-color: #EEE; } /*Platinum*/
</style>"; /*}}}*/
	$td_format = "<td width='%s' title='%s' class='%s'><div class='%s'>%s</div></td>";
	ksort($result_stat,SORT_NUMERIC);

#echo '<pre>'.print_r([$nTotal,$nDirect,$result_stat],true).'</pre>';
	$nBlank = $nTotal - $nDirect;
  $fBlank = 1.0 - $nBlank/$nTotal;

	$htmls = $tr1 = $tr2 = array();
	foreach ( $result_stat as $k => $v ) {
		$class = 'c'.$k;
		$f = sprintf('%0.1f%%',100*$v*($bPlural?$fBlank:1)/$nTotal);
		$text = "$f ($v)";
		$tr1[] = sprintf($td_format, $f, $v, $class, $class, $text );
		$tr2[] = sprintf($td_format, $f, $vm_label[$k], $class, $class, $vm_label[$k] );
	}
	if ( $nBlank > 0 ) {
		$class = 'bl';
    $label = '미투표';   //__('Unpolled', DOBslug);
		$f = sprintf('%0.1f%%',100*$nBlank/$nTotal);
		$text = "$f ($nBlank)";
		$tr1[] = sprintf($td_format, $f, $text, $class, $class, $text );
		$tr2[] = sprintf($td_format, $f, $label, $class, $class, $label );
	}
  $ret .= '<table class="barchart" border=1>'
	  .'<tr>'.implode(' ',$tr1).'</tr>'
	  .'<tr>'.implode(' ',$tr2).'</tr>'
	  .'</table>';
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
    if ( is_null($myval) || $myval==='' ) $myvals = array();
    else if ( $myval <= 1 ) $myvals = array($myval);
    else {
      $myvals = array();
      $vals = str_split(strrev(base_convert($myval,10,2)));
      //$myval = array_keys(array_filter($vals, function($v){return $v=='1';}));
      foreach ( $vals as $k => $v ) {
        if ( $v == '1' ) $myvals[] = $k+1;
      }
    }
	}
	$label_secret = '보안코드';			//__('Statistics', DOBslug);
	$label_remember = '암호화된 DB에서 직접 투표확인을 원하시면 이 값을 기억해 주세요.';			//__('Statistics', DOBslug);
  $html_plural_inputs = ($vm_type=='plural') ? '<input type="hidden" name="dob_form_val" value="'.$myval.'">' : '';
	// display area
	echo <<<HTML
		<div class="panel">
		<table>
			<form id="formDob" method="post">
			<input type="hidden" name="dob_form_type" value="$vm_type">
			<input type="hidden" name="dob_form_cart" value="0">
			<input type="hidden" name="dob_form_old_val" value="$myval">
      $html_plural_inputs
			<!--tr><td>
				$label_secret : <input type="text" name="dob_elect_secret" value="$secret" style="width:300px" READONLY>
				<br><b>$label_remember</b>
			</td></tr-->
			<tr><td id="tdVote">
HTML;
	wp_nonce_field( 'dob_form_nonce_'.$vm_type, 'dob_form_nonce' );
	foreach ( $vm_label as $val => $label ) {
		$html_input = '';
		if ( $vm_type == 'plural' ) {
      $control = in_array($val,$myvals) ? 'CHECKED' 
        : ( ($myval===0||$myval===-1) ? 'DISABLED' : '');
      $exp = ($val<1) ? $val : 1<<($val-1);
			$html_input = "<input type='checkbox' data-idx='$val' value='$exp' $control style='margin-left:9px; margin-right:0px;' >";
		} else {
			$checked = ($val===$myval) ? 'CHECKED' : '';
			$html_input = "<input type='radio' name='dob_form_val' value='$val' $checked>";
		}
		echo " <label style='margin-bottom:0px; font-size:1.1em;'>$html_input$label</label> ";
	}
	$html_submit = '';
	if ( $user_id ) {
		$html_submit = dob_common_get_message($post_id,$user_id,'elect');	// vote_post_latest timestamp
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
