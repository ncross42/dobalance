<?php
/**
 * Create site pages for this plugin
 */

require_once('dob_common.inc.php');

//add_action( 'wp', 'dob_vote_wp_init' );
//function dob_vote_wp_init() { }

function dob_vote_get_user_group_ttid_values($user_id,$gr_vals,$vm_type,$bAll=true) {/*{{{*/
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
			$gtid_vals[$gtid] = isset($gr_vals[$gtid]) ? $gr_vals[$gtid]['value'] : null;
		} else if ( isset($gr_vals[$gtid]) && $gr_vals[$gtid]['value'] ) {
			$gtid_vals[$gtid] = $gr_vals[$gtid]['value'];	// only available value is counted
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

		$dob_vote_content=dob_vote_contents($dob_vm_type,$post_id,$dob_vm_data);
		$content = $dob_vote_content . $content;

	}
	return $content;
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

function dob_vote_get_hierarchy_voter( $post_id, $ttids=array() ) {/*{{{*/
	global $wpdb;
	if ( empty($user_id) ) $user_id = get_current_user_id();

	$t_vote_post_latest	= $wpdb->prefix.'dob_vote_post_latest';
	$t_user_category		= $wpdb->prefix.'dob_user_category';
	$t_term_taxonomy		= $wpdb->prefix.'term_taxonomy';
	$t_terms						= $wpdb->prefix.'terms';

	$sql_ttids = empty($ttids) ? ''
		: ' AND term_taxonomy_id IN ('.implode(',',$ttids).')';
	$sql = <<<SQL
SELECT
	term_taxonomy_id, lft, name, slug, lvl, user_id, value
	, inf, chl, anc, parent
FROM $t_user_category c
	JOIN $t_term_taxonomy USING (taxonomy,term_taxonomy_id)
	JOIN $t_terms USING (term_id)
	LEFT JOIN $t_vote_post_latest l USING (user_id)
WHERE taxonomy = 'hierarchy' 
	$sql_ttids
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
        'parent'  => $r->parent,
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

function dob_vote_aggregate_updown( $point, $uid_vals ) {/*{{{*/
	$stat = array();
	foreach( $uid_vals as /*$uid =>*/ $val ) {
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

function dob_vote_make_stat( &$stat, $type, $nVal, $cnt=1,$strClass='di') {/*{{{*/
	$arrVals = array();
	if ( $type=='updown' || $type=='choice' || $nVal<= 1 ) { 
		$arrVals[$nVal] = $cnt;
	} else {  // plural
		$arr1 = str_split(strrev(base_convert($nVal,10,2)));
		foreach ( $arr1 as $k=>$v ) {
			if ( '1' == $v ) {
				$arrVals[$k+1] = $cnt;
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
  $bVote = false;
	if ( is_single() && $user_id ) {
		$debug = '';
		$LOGIN_IP = empty($_SESSION['LOGIN_IP']) ? '' : $_SESSION['LOGIN_IP'];
		if ( ! empty($_POST) && $LOGIN_IP == dob_get_real_ip() ) {
      $bVote = true;
#echo '<pre>'.print_r($_POST,true).'</pre>';
			if ( (int)$_POST['dob_form_cart'] ) {
				$debug = dob_common_cart($user_id,$post_id,'offer');
			} else {
				$debug = dob_common_update($user_id,$post_id,'offer');
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
  $gr_vals = $ttids = $hier_voter = $stat_detail = $stat_sum = null;
  if ( is_array($cached_all) ) extract($cached_all); 
  $nTotal = null; $nDirect = $nGroup = $nFixed = 0;
  if ( !empty($stat_sum) ) extract($stat_sum);

  // ttids: 신규, 포스트변경, 계층변경
  if ( !is_array($ttids) || $ts_all<$ts_post || $ts_all<$ts_struct ) {
    $ttids = dob_common_get_selected_hierarchy_leaf_ttids($post_id);
  }

  // nTotal: 신규, 포스트변경, 계층변경
  if ( is_null($nTotal) || $ts_all<$ts_post || $ts_all<$ts_struct ) {
    $nTotal = dob_common_get_users_count($ttids);	// get all user count
  }

  // gr_vals: 신규, 투표, 계층변경
  if ( is_null($gr_vals) || $bVote || $ts_all<$ts_struct ) {
    $gr_vals = dob_common_get_all_group_ttid_values($post_id);
  }

  #$bVote = true;
#echo '<pre>'.print_r(compact('nTotal','nDirect','nFixed','nGroup'),true).'</pre>';
  // hier_voter, stat_detail: 신규, 투표, 포스트변경, 계층변경
  if ( !is_array($hier_voter) || $bVote || $ts_all<$ts_post || $ts_all<$ts_struct ) {
    // RESET count
    $nDirect = $nGroup = $nFixed = 0;
#$ts = microtime(true);
    $hier_voter = dob_vote_get_hierarchy_voter($post_id,$ttids);	// order by lft
#echo '<pre>hi:'.(microtime(true)-$ts).'</pre>';

#$ts = microtime(true);
    foreach ( $hier_voter as $ttid => $v ) {/*{{{*/
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
        // check null-user's group delegator
        $uv_group = array();
        $uv_tmp = $uv_null;
        /*if ( $user_id && ! in_array($user_id,$uv_null) 
          && $myinfo && $ttid == $myinfo->term_taxonomy_id
        ) {
          $uv_tmp[] = $user_id;
        } TODO: WHY??? */
        foreach ( $uv_tmp as $uid ) {
          // get only available group values
          $tmp_gtid_vals = dob_vote_get_user_group_ttid_values($uid,$gr_vals,$vm_type,false);
          if ( !empty($tmp_gtid_vals) && !empty($tmp_gtid_vals['value']) ) {
            $uv_group[$uid] = $tmp_gtid_vals;
          }
          /*{{{*/ /*$gtid_vals = array();
          // RESET count
          foreach ( $gr_ttids as $gtid ) {
            $gtid_vals[$gtid] = $gr_vals[$gtid]->value;
          }
          if ( !empty($gtid_vals) ) {
            // TODO: cache this
            if ( $value = dob_vote_aggregate_value($vm_type,$gtid_vals) ) {
              $uv_group[$uid] = array (
                'gtid_vals' => $gtid_vals,
                'value' => $value,
              );
            }
          }*/ /*}}}*/
        }

        // deduct last-ancestor's influences
        $uvc_group_reflected = count( array_diff( array_keys($uv_group), array_keys($uv_valid)) ) ;
        foreach ( array_reverse($v['anc']) as $a_ttid ) {
          if ( isset($hier_voter[$a_ttid]) ) {	// only exists
            $hier_voter[$a_ttid]['inf'] -= ($uvc_valid+$uvc_group_reflected);
            if ( $hier_voter[$a_ttid]['value'] ) break;
          }
        }

        // self added leaf data
        $hier_voter[$ttid]['uv_group'] = $uv_group;
        $hier_voter[$ttid]['value'] = null;
      } else {	## BRANCH NODE ##
        // decision value
        $value = dob_vote_aggregate_value($vm_type,$uid_vals,count($all_ids));

        // deduct last-ancestor's influences
        if ( $value ) {	// not 0
          foreach ( array_reverse($v['anc']) as $a_ttid ) {
            if ( isset($hier_voter[$a_ttid]) ) {
              $hier_voter[$a_ttid]['inf'] -= $v['inf'];
              if ( $hier_voter[$a_ttid]['value'] ) break;
            }
          }
        }
        // deduct last-ancestor's influences by uvc_valid(Direct-voting)
        if ( $uvc_valid ) {
          foreach ( array_reverse($v['anc']) as $a_ttid ) {
            if ( isset($hier_voter[$a_ttid]) ) {
              $hier_voter[$a_ttid]['inf'] -= $uvc_valid;
              if ( $hier_voter[$a_ttid]['value'] ) break;
            }
          }
        }
        // self added non-leaf data
        $hier_voter[$ttid]['value'] = $value;
        $hier_voter[$ttid]['all_ids'] = $all_ids;
      }
      // self added common data
      $hier_voter[$ttid]['uv_valid'] = $uv_valid;
      #$hier_voter[$ttid]['uv_null'] = $uv_null;
      #$hier_voter[$ttid]['all_ids'] = $all_ids;
    }/*}}}*/
#echo '<pre>hv:'.(microtime(true)-$ts).'</pre>';

#$ts = microtime(true);
    $stat_detail = array(); // with $nDirect,$nFixed,$nGroup
    // build final stat_detail. /*{{{*/
    foreach ( $hier_voter as $ttid => $v ) {
      if ( empty($v['chl']) ) {	// leaf
        // final decision by Direct-Voting
        foreach ( $v['uv_valid'] as $uid => $val ) {
          dob_vote_make_stat($stat_detail,$vm_type,$val,1,'di');
          if ( !empty($val) ) ++$nDirect;
        } 
        // decision by group-voting
        foreach ( $v['uv_group'] as $uid => $info ) {
          if ( empty($v['uv_valid'][$uid]) ) {
#echo '<pre>'.print_r([$uid,$info,$v['uv_valid']],true).'</pre>';
            dob_vote_make_stat($stat_detail,$vm_type,$info['value'],1,'gr');
            ++$nGroup;
          }
        } 
      } else  { // non-leaf
        if ( $v['value'] ) {	// Delegator's decision value is NOT 0
          $nFixed += $inf = $v['inf'];	// accum inf.(deducted nLow)
          dob_vote_make_stat($stat_detail,$vm_type,$v['value'],$inf,'hi');
        }
        if ( ! empty($v['uid_vals']) ) {	// Delegator's private value
          foreach ( $v['uid_vals'] as $uid => $val ) {
            if ( $val ) {
              $nDirect += 1;
              dob_vote_make_stat($stat_detail,$vm_type,$val,1,'di');
            }
          }
        }
      }
    }/*}}}*/
#echo '<pre>st:'.(microtime(true)-$ts).'</pre>';
  }
#echo '<pre>'.print_r($hier_voter,true).'</pre>';
#file_put_contents('/tmp/hv.'.date('His').'.php',print_r($hier_voter,true));

  $stat_sum = compact('nTotal','nDirect','nFixed','nGroup');
#echo '<pre>'.print_r($stat_sum,true).'</pre>';

  // Cache STAT, 통계: 신규, 실제 통계값 변경
  $stat_json = json_encode(compact('stat_sum','stat_detail'),JSON_UNESCAPED_UNICODE);
  if ( empty($cached_stat_json) || $cached_stat_json != $stat_json ) {
    $ts_now = date('Y-m-d H:i:s');
    dob_common_cache($post_id,'stat',$stat_json,$ts_now,false);
  }

  // Cache Results, 결과: 신규, 투표행위, 포스트변경, 계층변경
  if ( ! is_array($cached_all) || $bVote || $ts_all<$ts_post || $ts_all<$ts_struct ) {
    $data = [
      'gr_vals'     => $gr_vals,
      'ttids'       => $ttids,
      'hier_voter'  => $hier_voter,
      'stat_detail' => $stat_detail,
      'stat_sum'    => $stat_sum,
    ];
    dob_common_cache($post_id,'all',$data);
  }

#echo '</pre>';

	$myinfo = $user_id ? dob_common_get_user_info($user_id) : null;

  ## build HTML 
  # labels /*{{{*/
  $label_for_devel  = '개발자용';       //__('Balance Voting', DOBslug);
  $label_total      = '전체';           //__('Total Users', DOBslug);
  $label_valid      = '유효';           //__('Total Users', DOBslug);
  $label_hierarchy  = '계층';           //__('Hierarchy voter', DOBslug);
  $label_group      = '단체';           //__('Delegate voter', DOBslug);
  $label_direct     = '직접';           //__('Direct voter', DOBslug);
  $label_result     = '결과';           //__('Direct voter', DOBslug);
  $label_chart      = '차트';           //__('Direct voter', DOBslug);
  $label_my         = '내';             //__('My Vote', DOBslug);
  $label_other      = '다른';           //__('My Vote', DOBslug);
  $label_history    = '기록';           //__('My Vote', DOBslug);
  $label_vote       = '투표';           //__('Vote', DOBslug);
  $label_influence  = '영향';         //__('Direct voter', DOBslug);
  $label_no_pos     = '계층이 지정되지 않아,';  //__('Direct voter', DOBslug); 
  $label_no_vote    = '투표할 수 없습니다.';  //__('Direct voter', DOBslug); 
  $label_no_analysis= '분석할 수 없습니다.';  //__('Direct voter', DOBslug); 
  $label_invalid_pos= '소속계층이 투표대상이 아닙니다.';  //__('Direct voter', DOBslug); 
  $label_login      = '로그인 해주세요'; //__('Please Login', DOBslug);
  $label_analysis   = '분석';            //__('Direct voter', DOBslug);
  $label_3rd        = '3순위';           //__('3rd Priority', DOBslug);
  $label_2rd        = '2순위';           //__('2rd Priority', DOBslug);
  $label_1rd        = '1순위';           //__('1rd Priority', DOBslug);
  $label_no         = '없음';           //__('1rd Priority', DOBslug);
  /*}}}*/

	$html_stat = ''; // dob_vote_html_stat($stat_sum);

  $html_chart = $html_analysis = $html_myvote = $html_history = '';
  if ( is_single() ) { /*{{{*/
		$vm_legend = ($vm_type=='updown') ? 
			array( -1=>'반대', 0=>'기권' ) : array( -1=>'모두반대', 0=>'기권' );
		if ( $vm_type == 'updown' ) {
			$vm_legend[1] = '찬성';
		} else {	// choice, plural
			foreach ( $dob_vm_data as $k => $v ) {
				$vm_legend[$k+1] = $v;
			}
		}
    #echo '<pre>'.print_r($stat_detail,true).'</pre>';
    $content_chart = ($vm_type=='plural') ?
      dob_vote_column_chart($stat_detail,$vm_legend,$nTotal)
      : dob_vote_bar_chart($stat_detail,$vm_legend,$nTotal);
    $html_chart = <<<HTML
    <div class="panel panel-default" style="clear:both;">
      <div class="panel-heading" data-toggle="collapse" data-target="#dob_vote_chart">
        <span class="panel-title">$label_result $label_chart</span>
      </div>
      <div id="dob_vote_chart" class="panel-collapse collapse in">
        $content_chart
      </div>
    </div>
HTML;

    ############################
    # build html vote analysis #
    ############################
    $content_analysis_all = ''; /*{{{*/
    $vote_latest = dob_common_get_latest_by_ttids($post_id,$ttids,'offer');	// user_id => rows	// for login_name
    $myval = empty($vote_latest[$user_id]) ? null : (int)$vote_latest[$user_id]['value'];
    #echo '<pre>'.print_r($myinfo,true).'</pre>';
    $hierarchies = $h_tr_obj = [];/*{{{*/
    $all_group_vals = array();
    foreach( $gr_vals as $gtid => $gr ) {
      $group_value_tooltip = dob_common_value_tooltip($vm_type,$vm_legend,$gr['value'],false);
      $all_group_vals[$gtid] = $gr['name'].':'.$group_value_tooltip;
    }
    $content_analysis_all = "## $label_total $label_group $label_vote<br> &nbsp; "
      . ( empty($all_group_vals) ? $label_no : implode(', ',$all_group_vals) )
      . '<br>';
    $hierarchies[] = " ## $label_hierarchy $label_influence $label_chart";

    foreach ( $hier_voter as $ttid => $v ) {
      $uv_valid = $v['uv_valid'];
      $indent = ' &nbsp; '.str_repeat(' -- ',$v['lvl']);
      $inherit = 0;
      foreach ( $v['anc'] as $a_ttid ) {
        if ( !empty($hier_voter[$a_ttid]['value']) ) {
          $inherit = $hier_voter[$a_ttid]['value'];
        }
      }
      // get affected parent
      $tmp_anc = $v['anc'];
      $parent = empty($tmp_anc) ? 0 : array_pop($tmp_anc);
      while( !empty($tmp_anc) && !isset($h_tr_obj[$parent]) ) {
        $parent = array_pop($tmp_anc);
      }
      if ( empty($v['chl']) ) {	// leaf
        $str_mine = '';
        $str_group = $grname_vals = array();
        // info of myval and mygroup
        if ( $myinfo && $ttid == $myinfo->term_taxonomy_id ) {
          $mygroup = isset($v['uv_group'][$user_id]) ? $v['uv_group'][$user_id]
            : dob_vote_get_user_group_ttid_values($user_id,$gr_vals,$vm_type,true) ;
          #echo '<pre>'.var_export([$user_id,$gr_vals,$mygroup],true).'</pre>';
          if ( ! empty($mygroup) ) {
            $grname_vals[] = "<span style='background-color:yellow;padding:0;'>[ {$mygroup['value']} ]</span>";
            foreach ( $mygroup['gtid_vals'] as $gtid => $val ) {
              $gr_val_tooltip = dob_common_value_tooltip($vm_type,$vm_legend,$val,false);
              $grname_vals[] = isset($gr_vals[$gtid]) ? $gr_vals[$gtid]['name'].':'.$gr_val_tooltip : '';
            }
            $str_group = '// '.implode(', ',$grname_vals);
          }
          $myval_tooltip = is_null($myval) ? 'null' : dob_common_value_tooltip($vm_type,$vm_legend,$myval,false);
          $str_mine = "<span style='color:red;padding:0;'>@{$myinfo->user_nicename}:".$myval_tooltip."</span>";
          $str_mine .= $str_group;
        }
        $uvc_valid	= count($v['uv_valid']);
        $hierarchies[] = $indent.$v['tname']."({$v['inf']}-$uvc_valid) : <u>$inherit</u> $str_mine";

        $value_tooltip = dob_common_value_tooltip($vm_type,$vm_legend,$v['value'],false);
        $inherit_tooltip = dob_common_value_tooltip($vm_type,$vm_legend,$inherit,true);
        $decision = empty($v['value']) ? $inherit_tooltip : $value_tooltip;
        $h_tr_obj[$ttid] = ['lvl'=>$v['lvl'], 'name'=>$v['tname'], 'inf'=>"{$v['inf']}-$uvc_valid", 
          'decision'=>'', 'inherit'=>$inherit_tooltip, 'detail'=>$str_mine, 'parent'=>$parent ];
      } else {	// branch
        $yes = $no = array();
        foreach ( $uv_valid as $uid => $val ) {
          $str = $vote_latest[$uid]['user_nicename'].':'.dob_common_value_tooltip($vm_type,$vm_legend,$val,false);
          $yes[] = ( $uid==$user_id ) ? "<span style='color:red'>@$str</span>" : $str;
        }
        $yes = implode(', ',$yes);
        $no = array_diff($v['all_ids'],array_keys($uv_valid));
        $no_ids = dob_vote_get_user_nicenames($no);
        $no = empty($no_ids) ? '' : '<strike>'.implode(', ',$no_ids).'</strike>';
        $val = empty($v['value']) ? "<u>$inherit</u>" : "<b>{$v['value']}</b>";
        $hierarchies[] = $indent.$v['tname']."({$v['inf']}) : $val <span style='background-color:yellow;padding:0;'>[ {$v['value']} ]</span> ($yes) $no";
        $value_tooltip = dob_common_value_tooltip($vm_type,$vm_legend,$v['value'],false);
        $inherit_tooltip = dob_common_value_tooltip($vm_type,$vm_legend,$inherit,true);
        $decision = empty($v['value']) ? $inherit_tooltip : $value_tooltip;
        $h_tr_obj[$ttid] = ['lvl'=>$v['lvl'], 'name'=>$v['tname'], 'inf'=>$v['inf'], 
          'decision'=>$decision, 'inherit'=>$inherit_tooltip, 'detail'=>$yes.($no?"// $no":''), 'parent'=>$parent ];
      }
    }/*}}}*/
    //$content_analysis_all .= implode('<br>',$hierarchies);
    $content_analysis_all .= dob_vote_get_allheir_table('table_analysis_allhier',$h_tr_obj);
    /*}}}*/

    $content_analysis_myhier = $content_analysis_mygroup = "$label_no_pos $label_no_analysis";
    if ( $myinfo ) { 
      // content_analysis_myhier /*{{{*/
      $vote_latest = dob_common_get_latest_by_ttids($post_id,$ttids,'offer');	// user_id => rows, for login_name
      $h_tr_obj = [];
      #echo '<pre>'.print_r([$myinfo->anc],true).'</pre>';
      $my_ttids = explode(',',$myinfo->anc);
      $my_ttids[] = $myinfo->term_taxonomy_id;
      $inherit = 0;
      foreach ( $my_ttids as $ttid ) {
        $v = empty($hier_voter[$ttid]) ? null : $hier_voter[$ttid];
        #echo '<pre>'.print_r($v['anc'],true).'</pre>';
        #echo '<pre>'.print_r($v['uv_group'],true).'</pre>';
        if ( empty($v) ) {  // get only ttid's info
          list($v) = dob_common_get_hierarchy_info([$ttid]);  // get only one tt info.
          #echo '<pre>'.print_r([$ttid,$v],true).'</pre>';
          $inherit_tooltip = dob_common_value_tooltip($vm_type,$vm_legend,$inherit,true);
          $h_tr_obj[$ttid] = ['lvl'=>$v->lvl, 'name'=>$v->name, 'inf'=>$v->inf
            , 'decision'=>$inherit_tooltip, 'inherit'=>$inherit_tooltip, 'parent'=>$v->parent ];
        } elseif ( ! empty($v['chl']) ) {	// branch
          #echo '<pre>'.print_r([$ttid,$v],true).'</pre>';
          foreach ( $v['anc'] as $a_ttid ) {
            if ( !empty($hier_voter[$a_ttid]['value']) ) {
              $inherit = $hier_voter[$a_ttid]['value'];
            }
          }
          $uv_valid = $v['uv_valid'];
          $yes = $no = array();
          foreach ( $uv_valid as $uid => $val ) {
            $val_tooltip = dob_common_value_tooltip($vm_type,$vm_legend,$val,false);
            $str = $vote_latest[$uid]['user_nicename'].":$val_tooltip";
            $yes[] = ( $uid==$user_id ) ? "<span style='color:red'>@$str</span>" : $str;
          }
          $yes = implode(', ',$yes);
          $no = array_diff($v['all_ids'],array_keys($uv_valid));
          $no_ids = dob_vote_get_user_nicenames($no);
          $no = empty($no_ids) ? '' : '<strike>'.implode(', ',$no_ids).'</strike>';
          // jquery treetable
          $value_tooltip = dob_common_value_tooltip($vm_type,$vm_legend,$v['value'],false);
          $inherit_tooltip = dob_common_value_tooltip($vm_type,$vm_legend,$inherit,true);
          $decision = empty($v['value']) ? $inherit_tooltip : $value_tooltip;
          $h_tr_obj[$ttid] = ['lvl'=>$v['lvl'], 'name'=>$v['tname'], 'inf'=>$v['inf'], 
            'decision'=>$decision, 'inherit'=>$inherit_tooltip, 'yes'=>$yes, 'no'=>$no,
            'parent'=> empty($v['anc']) ? 0 : $v['anc'][count($v['anc'])-1]
          ];
        }
      }
      $content_analysis_myhier = dob_vote_get_myheir_table('table_analysis_myhier',$h_tr_obj);
      /*}}}*/

      // content_analysis_mygroup /*{{{*/
      // info of myval and mygroup
      $ttid = $myinfo->term_taxonomy_id;
      $mygroup = empty($hier_voter[$ttid]['uv_group'][$user_id]) ? null : $hier_voter[$ttid]['uv_group'][$user_id];
      $my_group_final = $html_group_my = $html_group_other = '';
      $arr_group_other = [];
      if ( isset($mygroup['value']) && isset($mygroup['gtid_vals']) ) {
        $my_group_final = $mygroup['value'];
        $arr_group_my = [];
        foreach ( $mygroup['gtid_vals'] as $gtid => $val ) {
          if ( ! empty($all_group_vals[$gtid]) ) {
            $arr_group_my[] = $all_group_vals[$gtid];
            unset($all_group_vals[$gtid]);
          }
        }
        $html_group_my = implode(', ',$arr_group_my);
      }
      $my_group_final_tooltip = dob_common_value_tooltip($vm_type,$vm_legend,$my_group_final,false);
      foreach ( $all_group_vals as $gtid => $group ) {
        $arr_group_other[] = $group;
      }
      $html_group_other = implode(', ',$arr_group_other);
      $content_analysis_mygroup = <<<HTML
            $label_my $label_group $label_result : <b>$my_group_final_tooltip</b> <br>
            $label_my $label_group $label_vote : $html_group_my <br>
            $label_other $label_group $label_vote : $html_group_other
HTML;
      /*}}}*/
    }

    /*{{{*/ $html_analysis = <<<HTML
    <div class="panel panel-default" style="clear:both;">
      <div class="panel-heading" data-toggle="collapse" data-target="#dob_vote_html_analysis_section">
        <span class="panel-title">$label_vote $label_analysis</span>
      </div>
      <div id="dob_vote_html_analysis_section" class="panel-collapse collapse in">
        <div class="panel panel-default" style="clear:both;margin-left:20px;margin-right:20px">
          <div class="panel-heading collapsed" data-toggle="collapse" data-target="#dob_vote_html_analysis_all">
            <span class="panel-title">$label_total $label_analysis ($label_for_devel)</span>
          </div>
          <div id="dob_vote_html_analysis_all" class="panel-collapse scrollable collapse">
            $content_analysis_all
          </div>
        </div>
        <div class="panel panel-default" style="clear:both;margin-left:20px;margin-right:20px">
          <div class="panel-heading" data-toggle="collapse" data-target="#dob_vote_html_analysis_my_hier">
            <span class="panel-title">$label_my $label_hierarchy $label_analysis ($label_3rd)</span>
          </div>
          <div id="dob_vote_html_analysis_my_hier" class="panel-collapse scrollable collapse in">
            $content_analysis_myhier
          </div>
        </div>
        <div class="panel panel-default" style="clear:both;margin-left:20px;margin-right:20px">
          <div class="panel-heading" data-toggle="collapse" data-target="#dob_vote_html_analysis_my_group">
            <span class="panel-title">$label_my $label_group $label_analysis ($label_2rd)</span>
          </div>
          <div id="dob_vote_html_analysis_my_group" class="panel-collapse scrollable collapse in">
            $content_analysis_mygroup
          </div>
        </div>
      </div>
    </div>
HTML;
/*}}}*/

    $content_myform = ''; /*{{{*/
    if ( empty($user_id) ) {
      $login_url = wp_login_url( $_SERVER['REQUEST_URI'] );
      $content_myform = "<a href='$login_url' style='color:red; font-weight:bold'>$label_login</a>";
    } else if ( empty($myinfo->term_taxonomy_id) ) {
      $content_myform = "<span style='color:red; font-size:1.2em; font-weight:bold'>$label_no_pos $label_no_vote</span>";
    } else if ( !empty($ttids) && ! in_array($myinfo->term_taxonomy_id,$ttids) ) {
      $content_myform = "<span style='color:red; font-size:1.2em; font-weight:bold'>$label_invalid_pos</span>";
    } else {
      $content_myform = dob_vote_display_mine($post_id,$vm_type,$vm_legend,$myval,$user_id);
    }

    $html_myvote = <<<HTML
    <div class="panel panel-default" style="clear:both;">
      <div class="panel-heading" data-toggle="collapse" data-target="#dob_vote_html_myvote">
        <span class="panel-title">$label_my $label_vote ($label_1rd)</span>
      </div>
      <div id="dob_vote_html_myvote" class="panel-collapse collapse in">
        $content_myform
      </div>
    </div>
HTML;
    /*}}}*/

  }/*}}}*/

  $dob_vote = <<<HTML
  $html_stat
  $html_chart
  $html_analysis
  $html_myvote
  $html_history 
HTML;

  if ($bEcho) echo $dob_vote;
  else return $dob_vote;
}

function dob_vote_html_stat($stat_sum) {/*{{{*/
  $nTotal=$nDirect=$nFixed=$nGroup=0;
  extract($stat_sum);

	$label_stat			= '기본 통계';	//__('Statistics', DOBslug);
	#$label_turnout	= '투표율';					//__('Total Users', DOBslug);
	$label_total		= '전체';						//__('Total Users', DOBslug);
	$label_valid		= '유효';						//__('Total Users', DOBslug);
	$label_hierarchy= '계층';						//__('Hierarchy voter', DOBslug);
	$label_group		= '단체';						//__('Delegate voter', DOBslug);
	$label_direct		= '직접';						//__('Direct voter', DOBslug);

  $fValid = $fFixed = $fGroup = $fDirect = '0.0%';
	$nValid = $nFixed+$nGroup+$nDirect;
	if ( $nTotal ) $fValid = sprintf('%0.1f%%',100*($nValid/$nTotal));
	if ( $nValid ) {
		$fFixed = sprintf('%0.1f%%',100*($nFixed/$nValid));
		$fGroup = sprintf('%0.1f%%',100*($nGroup/$nValid));
		$fDirect = sprintf('%0.1f%%',100*($nDirect/$nValid));
	}
	return <<<HTML
  <div class="panel-group">
    <div class="panel panel-default">
      <div class="panel-heading" data-toggle="collapse" data-target="#dob_vote_html_stat">
        <span class="panel-title">$label_stat <span class="label label-primary pull-right">$fValid</span></span>
      </div>
      <div id="dob_vote_html_stat" class="panel-collapse collapse in">
        <table class="table-bordered">
          <tr><td style="width:80px">$label_valid     / $label_total</td><td>$fValid <span class="bg-success pull-right">$nValid / $nTotal</span></td></tr>
          <tr><td style="width:80px">$label_hierarchy / $label_valid</td><td>$fFixed <span class="bg-success pull-right">$nFixed / $nValid</span></td></tr>
          <tr><td style="width:80px">$label_group     / $label_valid</td><td>$fGroup <span class="bg-success pull-right">$nGroup / $nValid</span></td></tr>
          <tr><td style="width:80px">$label_direct    / $label_valid</td><td>$fDirect<span class="bg-success pull-right">$nDirect / $nValid</span></td></tr>
        </table>
      </div>
    </div>
  </div>
HTML;

}/*}}}*/

function dob_vote_column_chart($stat_detail,$vm_legend,$nTotal) {/*{{{*/
/*{{{*/ /*$example = <<<HTML
<ul>
  <li>
    <span style="height:100%" title="가나다라마바사asdfqwer">100%</span>
  </li>
  <li>
    <div style="height:80%" title="HTML 80%">
      <span style="height:30%" class="di">windows<br>30%</span>
      <span style="height:50%" class="hi">linux<br>50%</span>
      <span style="height:20%" class="gr">macos<br>20%</span>
    </div>
  </li>
</ul>
HTML;*/ /*}}}*/

  $ret = "<style> /*{{{*/
ul.column-chart {
  display: table;
  table-layout: fixed;
  width: 97%;
  height: 200px;
  margin: 0 auto;
  background-image: linear-gradient(to top, rgba(0, 0, 0, 0.5) 2%, rgba(0, 0, 0, 0) 2%);
  background-size: 100% 50px;
  background-position: left top;
  font-size: 0.85em;
  padding-left: 0;
  padding-bottom: 4em;
}
ul.column-chart li {
  position: relative;
  display: table-cell;
  vertical-align: bottom;
  height: 200px;
}
ul.column-chart span {
  margin: 0 0.5em;
  text-align: center;
  display: block;
  background: rgba(204, 221, 255, 0.75);
  animation: draw 1s ease-in-out;
}
ul.column-chart span:before {
  position: absolute;
  left: 0;
  right: 0;
  top: 100%;
  padding: 5px 1em 0;
  display: block;
  text-align: center;
  content: attr(title);
  word-wrap: break-word;
}
ul.column-chart div {
  margin: 0 0.6em;
  display: table;
  background: rgba(204, 221, 255, 0.75);
  animation: draw 1s ease-in-out;
  vertical-align: middle;
}
ul.column-chart div:before {
  position: absolute;
  left: 0;
  right: 0;
  top: 100%;
  padding: 5px 1em 0;
  display: block;
  text-align: center;
  content: attr(title);
  word-wrap: break-word;
}
ul.column-chart div span {
  width: 100%;
  margin: 0;
  text-align: center;
  display: block;
  animation: draw 1s ease-in-out;
}
ul.column-chart div span.di {
  background: rgba(221, 153, 153, 0.75);
}
ul.column-chart div span.hi {
  background: rgba(153, 221, 153, 0.75);
}
ul.column-chart div span.gr {
  background: rgba(153, 153, 221, 0.75);
}
ul.column-chart > div > span:before {
  left: 0;
  right: 0;
  top: 100%;
  display: block;
  text-align: center;
  content: attr(title);
  word-wrap: break-word;
  display: table-cell;
  vertical-align: middle;
}

@keyframes draw {
  0% {
    height: 0;
  }
}

.barchart td div { height:20px; text-align:center; overflow: hidden; text-overflow: ellipsis; }
</style>"; /*}}}*/

  $label_direct    = '직접';   //__('Direct', DOBslug),
  $label_hierarchy = '계층';   //__('Hierarchy', DOBslug),
  $label_group     = '단체';   //__('Group', DOBslug),
  $label_abstain   = '기권'; //__('Blank', DOBslug)
  $span_format = "<span style='height:%s' class='stack %s' data-toggle='tooltip' title='%d'>%s</span>";
  ksort($stat_detail,SORT_NUMERIC);

  $nBlank = $nTotal;
  $arrLI = array();
//echo '<pre>'.print_r($stat_detail,true).'</pre>';
  foreach ( $stat_detail as $i => $data ) {
    extract($data);  // 'all', 'di', 'hi'
    // ul-div
    $ratio = sprintf('%0.1f%%',100*$all/$nTotal);
    $text  = $vm_legend[$i]." $ratio ($all)";
    $li_div = "<li><div style='height:$ratio' title='$text'>";
    if ( $di ) { // span-direct
      //$ratio = sprintf('%0.0f%%',100*$di/$all);
      $ratio = sprintf('%d%%',100*$di/$all);
      $text  = $label_direct." $ratio";
      $li_div .= sprintf($span_format, $ratio, 'di', $di, $text );
    }
    if ( $hi ) { // span-hierarchy
      $ratio = sprintf('%d%%',100*$hi/$all);
      $text  = $label_hierarchy." $ratio";
      $li_div .= sprintf($span_format, $ratio, 'hi', $hi, $text );
    }
    if ( $gr ) { // span-group
      $ratio = sprintf('%d%%',100*$gr/$all);
      $text  = $label_group." $ratio";
      $li_div .= sprintf($span_format, $ratio, 'gr', $gr, $text );
    }
    $li_div .= "</div></li>";
    $arrLI[] = $li_div;
  }
  $ret .= '<ul class="column-chart">'.implode(' ',$arrLI).'</ul>';

  return $ret;
}/*}}}*/

function dob_vote_bar_chart($stat_detail,$vm_legend,$nTotal) {/*{{{*/
	$ret = "<style> /*{{{*/
.barchart { width: 100%; height:25px; border-collapse: collapse; }
.barchart td div { height:20px; text-align:center; overflow: hidden; text-overflow: ellipsis; }
.barchart .c-1 { background-color: BLUE; color:white; } /*TANGERINE ;*/
.barchart .c0  { background-color: #FFF; }
.barchart .c1  { background-color: RED; color:white; }
.barchart .c2  { background-color: GREEN; color:white; }
.barchart .c3  { background-color: TAN; }
.barchart .c4  { background-color: GOLD ; }
.barchart .c5  { background-color: #B2FFFF; } /*sky blue;*/ 
.barchart .c6  { background-color: PINK; }
.barchart .c7  { background-color: LIME; }
.barchart .gr  { background-color: #EEE; }
</style>"; /*}}}*/

	$label_direct    = '직접';   //__('Direct', DOBslug),
	$label_hierarchy = '계층';   //__('Hierarchy', DOBslug),
	$label_group     = '단체';   //__('Group', DOBslug),
	$label_abstain   = '기권'; //__('Blank', DOBslug)
	$td_format = "<td width='%s' title='%s' class='%s'><div class='%s'>%s</div></td>";
	ksort($stat_detail,SORT_NUMERIC);

	$nBlank = $nTotal;
	$tr1 = $tr2 = array();
	foreach ( $stat_detail as $i => $data ) {
		extract($data);	// 'all', 'di', 'hi'
		$nBlank -= $all;
		// row1
		$ratio = sprintf('%0.1f%%',100*$all/$nTotal);
		$text  = $vm_legend[$i]." $ratio ($all)";
		$tr1[] = sprintf($td_format, $ratio, $text, 'c'.$i, 'c'.$i, $text );
		if ( $di ) { // row2-direct
			$ratio = sprintf('%0.1f%%',100*$di/$nTotal);
			$text  = $label_direct." $ratio ($di)";
			$tr2[] = sprintf($td_format, $ratio, $text, 'c'.$i, 'c'.$i, $text );
		}
		if ( $hi ) { // row2-hierarchy
			$ratio = sprintf('%0.1f%%',100*$hi/$nTotal);
			$text  = $label_hierarchy." $ratio ($hi)";
			$tr2[] = sprintf($td_format, $ratio, $text, '', '', $text );
		}
		if ( $gr ) { // row2-group
			$ratio = sprintf('%0.1f%%',100*$gr/$nTotal);
			$text  = $label_group." $ratio ($gr)";
			$tr2[] = sprintf($td_format, $ratio, $text, 'gr', 'gr', $text );
		}
	}
	if ( $nBlank ) {
		$ratio = sprintf('%0.1f%%',100*$nBlank/$nTotal);
		$text  = $label_abstain." $ratio ($nBlank)";
		$un = sprintf($td_format, $ratio, $text, '', '', $text );
		$tr1[] = $un; $tr2[] = $un;
	}
	$ret .= '<table class="barchart" border="1px"><tr>'.implode(' ',$tr1).'</tr></table>';
	$ret .= '<table class="barchart" border="1px"><tr>'.implode(' ',$tr2).'</tr></table>';

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
				$label_secret : <input type="text" name="dob_vote_secret" value="$secret" style="width:300px" READONLY>
				<br><b>$label_remember</b>
			</td></tr-->
			<tr><td id="tdVote"><div class="well">
HTML;
	wp_nonce_field( 'dob_form_nonce_'.$vm_type, 'dob_form_nonce' );
	foreach ( $vm_legend as $val => $label ) {
		$html_input = '';
		if ( $vm_type == 'plural' ) {
      $control = in_array($val,$myvals) ? 'CHECKED' 
        : ( ($myval===0||$myval===-1) ? 'DISABLED' : '');
      $exp = ($val<1) ? $val : 1<<($val-1);
			$html_input = "<input type='checkbox' data-idx='$val' value='$exp' $control>";
		} else {
			$checked = ($val===$myval) ? 'CHECKED' : '';
			$html_input = "<input type='radio' name='dob_form_val' value='$val' $checked>";
		}
		echo " <label class='radio-inline checkbox-inline'>$html_input$label</label> ";
	}

	$html_submit = empty($user_id) ? $label_login : dob_common_get_message($post_id,$user_id,'offer');	// vote_post_latest timestamp
	if ( $LOGIN_IP == dob_get_real_ip() ) {
		$label_fast = '바로투표';	//__('Vote', DOBslug);
		$label_cart = '투표바구니';	//__('Vote', DOBslug);
		$html_submit .= " <input id='btn_fast' type='button' value='$label_fast' class='btn btn-success btn-sm' >";
		$html_submit .= " <input id='btn_cart' type='button' value='$label_cart' class='btn btn-warning btn-sm' >";
	} else {
		$label_iperr_relogin = '로그인 이후 1시간이 지났거나, 네트워크가 초기화 되었으니, 다시 로그인해 주세요<br>투표시에는 네트워크(WIFI,LTE,3G)를 변경하지 마세요.';	//__('You passed 1-hours after login, or Your network was Changed. Please Login AGAIN.', DOBslug);
		$html_submit .= '<br>'.$label_iperr_relogin;
	}
	echo <<<HTML
			</div></td></tr>
			<tr><td nowrap style="text-align:right;">$html_submit</td></tr>
			</form>
		</table>
		</div>
HTML;
	$ret = ob_get_contents();
	ob_end_clean();
	return $ret;
}/*}}}*/

function dob_vote_get_myheir_table($id,$h_tr_obj) {/*{{{*/

  $label_hierarchy = '계층';      //__('Hierarchy voter', DOBslug);
  $label_influence = '영향';    //__('Direct voter', DOBslug);
  $label_decision  = '결정';    //__('Direct voter', DOBslug);
  $label_voter     = '투표자';    //__('Direct voter', DOBslug);
  $label_abstainer = '기권자';    //__('Direct voter', DOBslug);
  $label_inherit   = '상속';      //__('Direct voter', DOBslug);
  $label_value     = '값';      //__('Direct voter', DOBslug);

  $html_tr = '';
  foreach( $h_tr_obj as $ttid => $v ) {
    $parent = empty($v['parent']) ? '' : "data-tt-parent-id='{$v['parent']}'";
    $voter = empty($v['yes']) ? '' : $v['yes'];
    $abstain = empty($v['no']) ? '' : $v['no'];
    $decision = empty($v['decision']) ? 0 : $v['decision'];
    $html_tr .= <<<HTML
      <tr data-tt-id="$ttid" $parent>
        <td nowrap>{$v['name']}</td> <td>{$v['inf']}</td> <td>{$decision}</td>
        <td>{$v['inherit']}</td> <td>{$voter}</td> <td>{$abstain}</td>
      </tr>
HTML;
  }

	return <<<HTML
  <table id="$id" class="treetable no-margin table-hierarchy">
    <thead>
      <tr>
        <th>$label_hierarchy</th> <th>$label_influence</th> <th>$label_decision</th> 
        <th width="75px">$label_inherit</th> <th>$label_voter</th> <th>$label_abstainer</th> 
      </tr>
    </thead>
    <tbody>
      $html_tr
    </tbody>
  </table>
HTML;

}/*}}}*/

function dob_vote_get_allheir_table($id,$h_tr_obj) {/*{{{*/

  $label_hierarchy = '계층';      //__('Hierarchy voter', DOBslug);
  $label_influence = '영향';    //__('Direct voter', DOBslug);
  $label_decision  = '결정';    //__('Direct voter', DOBslug);
  $label_inherit   = '상속';      //__('Direct voter', DOBslug);
  $label_value     = '값';      //__('Direct voter', DOBslug);
  $label_detail    = '상세';    //__('Direct voter', DOBslug);

  $html_tr = '';
  foreach( $h_tr_obj as $ttid => $v ) {
    $parent = empty($v['parent']) ? '' : "data-tt-parent-id='{$v['parent']}'";
    $voter = empty($v['yes']) ? '' : $v['yes'];
    $abstain = empty($v['no']) ? '' : $v['no'];
    $decision = empty($v['decision']) ? 0 : $v['decision'];
    $html_tr .= <<<HTML
      <tr data-tt-id="$ttid" $parent>
        <td>{$v['name']}</td> <td>{$v['inf']}</td> <td>{$decision}</td>
        <td>{$v['inherit']}</td> <td>{$v['detail']}</td>
      </tr>
HTML;
  }

	return <<<HTML
  <table id="$id" class="treetable no-margin table-hierarchy">
    <thead>
      <tr>
        <th>$label_hierarchy</th> <th width="75px">$label_influence</th> <th width="75px">$label_decision</th> 
        <th width="75px">$label_inherit</th> <th>$label_detail</th> 
      </tr>
    </thead>
    <tbody>
      $html_tr
    </tbody>
  </table>
HTML;

}/*}}}*/
