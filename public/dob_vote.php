<?php
/**
 * Create site pages for this plugin
 */

require_once('dob_common.inc.php');

//add_action( 'wp', 'dob_vote_wp_init' );
//function dob_vote_wp_init() { }

function dob_vote_get_gr_vals($post_id) {/*{{{*/
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
	$rows = $wpdb->get_results($sql,ARRAY_A);
	$ret = array();
	foreach ( $rows as $row ) {
		$ret[(int)$row['ttid']] = $row;
	}
	return $ret;
}/*}}}*/

function dob_vote_get_user_group_all_ttid_values($user_id,$gr_vals,$vm_type,$bAll=true) {/*{{{*/
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
	, inf, chl, anc
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
    $gr_vals = dob_vote_get_gr_vals($post_id);
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
          $tmp_gtid_vals = dob_vote_get_user_group_all_ttid_values($uid,$gr_vals,$vm_type,false);
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
	//$label_title		= '균형 투표';		//__('Balance Voting', DOBslug);
	$label_total		  = '전체';						//__('Total Users', DOBslug);
	$label_valid		  = '유효';						//__('Total Users', DOBslug);
	$label_hierarchy  = '계층';						//__('Hierarchy voter', DOBslug);
	$label_group		  = '그룹';						//__('Delegate voter', DOBslug);
	$label_direct		  = '직접';						//__('Direct voter', DOBslug);
	$label_chart		  = '결과 차트';			//__('Direct voter', DOBslug);
	$label_my				  = '내 투표';				//__('My Vote', DOBslug);
	#$label_history	  = '기록';				//__('My Vote', DOBslug);
	$label_vote			  = '투표';						//__('Vote', DOBslug);
	$label_influence  = '영향력 관계도';	//__('Direct voter', DOBslug);
	$label_no_pos		  = '계층이 지정되지 않아, 투표할 수 없습니다.';	//__('Direct voter', DOBslug); 
	$label_invalid_pos= '소속계층이 투표대상이 아닙니다.';	//__('Direct voter', DOBslug); 
	$label_login      = '로그인 해주세요';	//__('Please Login', DOBslug);
	/*}}}*/

	// build html hierarchy chart
	$html_hierarchy = '';/*{{{*/
	if ( is_single() ) {
		$vote_latest = dob_common_get_latest_by_ttids($post_id,$ttids,'offer');	// user_id => rows	// for login_name
		$myval = empty($vote_latest[$user_id]) ? null : (int)$vote_latest[$user_id]['value'];
#echo '<pre>'.print_r($myinfo,true).'</pre>';
		$hierarchies = array();/*{{{*/
		$all_group_vals = array();
		foreach( $gr_vals as $gr ) {
			$all_group_vals[] = $gr['name'].':'.$gr['value'];
		}
		$hierarchies[] = " ## $label_total $label_group $label_vote <br> &nbsp; ".implode(', ',$all_group_vals);
		$hierarchies[] = " ## $label_hierarchy $label_influence";

		foreach ( $hier_voter as $ttid => $v ) {
			$uv_valid = $v['uv_valid'];
			$indent = ' &nbsp; '.str_repeat(' -- ',$v['lvl']);
			$inherit = 0;
			foreach ( $v['anc'] as $a_ttid ) {
				if ( !empty($hier_voter[$a_ttid]['value']) ) {
					$inherit = $hier_voter[$a_ttid]['value'];
				}
			}
			if ( empty($v['chl']) ) {	// leaf
				$str_mine = '';
				$grname_vals = array();
				// info of myval and mygroup
				if ( $myinfo && $ttid == $myinfo->term_taxonomy_id ) {
					$mygroup = isset($v['uv_group'][$user_id]) ? $v['uv_group'][$user_id]
						: dob_vote_get_user_group_all_ttid_values($user_id,$gr_vals,$vm_type,true) ;
#echo '<pre>'.var_export([$user_id,$gr_vals,$mygroup],true).'</pre>';
					if ( ! empty($mygroup) ) {
						$grname_vals[] = "<span style='background-color:yellow'>[ {$mygroup['value']} ]</span>";
						foreach ( $mygroup['gtid_vals'] as $gtid => $val ) {
							$grname_vals[] = isset($gr_vals[$gtid]) ? $gr_vals[$gtid]['name'].":<b>$val</b>" : '';
						}
					}
					$str_mine = "<span style='color:red'>@{$myinfo->user_nicename}:<b>".(is_null($myval)?'null':$myval)."</b></span>";
				}
				$str_group = empty($grname_vals) ? '' : '// '.implode(', ',$grname_vals);
				$uvc_valid	= count($v['uv_valid']);
				$hierarchies[] = $indent.$v['tname']."({$v['inf']}-$uvc_valid) : <u>$inherit</u> $str_mine $str_group";
			} else {	// branch
				$yes = $no = array();
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
    <div class="panel panel-default" style="clear:both;">
      <div class="panel-heading" data-toggle="collapse" data-target="#dob_vote_html_hierarchy">
        <span class="panel-title">$label_analysis</span>
      </div>
      <div id="dob_vote_html_hierarchy" class="panel-collapse collapse in">
        $content_hierarchy
      </div>
    </div>
HTML;
	}/*}}}*/

	$html_stat = ''; // dob_vote_html_stat($stat_sum);

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
    #echo '<pre>'.print_r($stat_sum,true).'</pre>';
    #echo '<pre>'.print_r($stat_detail,true).'</pre>';
		$content_chart = dob_vote_html_chart($stat_detail,$vm_legend,$nTotal);
    $html_chart = <<<HTML
    <div class="panel panel-default" style="clear:both;">
      <div class="panel-heading" data-toggle="collapse" data-target="#dob_vote_html_chart">
        <span class="panel-title">$label_chart</span>
      </div>
      <div id="dob_vote_html_chart" class="panel-collapse collapse in">
        $content_chart
      </div>
    </div>
HTML;

		if ( empty($user_id) ) {
			$content_form = "<a href='/wp-login.php' style='color:red; font-weight:bold'>$label_login</a>";
		} else if ( empty($myinfo->term_taxonomy_id) ) {
			$content_form = "<span style='color:red; font-size:1.2em; font-weight:bold'>$label_no_pos</span>";
		} else if ( ! in_array($myinfo->term_taxonomy_id,$ttids) ) {
			$content_form = "<span style='color:red; font-size:1.2em; font-weight:bold'>$label_invalid_pos</span>";
		} else {
			$content_form = dob_vote_display_mine($post_id,$vm_type,$vm_legend,$myval,$user_id);
		}
    $html_form = <<<HTML
    <div class="panel panel-default" style="clear:both;">
      <div class="panel-heading" data-toggle="collapse" data-target="#dob_vote_html_form">
        <span class="panel-title">$label_my</span>
      </div>
      <div id="dob_vote_html_form" class="panel-collapse collapse in">
        $content_form
      </div>
    </div>
HTML;

		/*{{{*/ /*if ( $user_id ) {
			$html_history = <<<HTML
			<li class='toggle'>
				<h3># $label_my $label_history<span class='toggler'>[open]</span></h3>
				<div class='panel' style='display:none'>
					<table id='table_log'>
						<tr><th>date_time</th><th>value</th><th>ip</th></tr>
HTML;
			foreach ( dob_common_get_log($post_id,$user_id,'offer') as $log ) {
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
		}*/ /*}}}*/
	}/*}}}*/

	$dob_vote = <<<HTML
	$html_stat
	$html_chart
	$html_hierarchy
	$html_form
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
	$label_group		= '그룹';						//__('Delegate voter', DOBslug);
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

function dob_vote_html_chart($stat_detail,$vm_legend,$nTotal) {/*{{{*/
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

	$str_di = '직접'; //__('Direct', DOBslug),
	$str_hi = '계층'; //__('Hierarchy', DOBslug),
	$str_gr = '그룹'; //__('Group', DOBslug),
	$str_un = '미투표';	//__('Blank', DOBslug)
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
			$text  = $str_di." $ratio ($di)";
			$tr2[] = sprintf($td_format, $ratio, $text, 'c'.$i, 'c'.$i, $text );
		}
		if ( $hi ) { // row2-hierarchy
			$ratio = sprintf('%0.1f%%',100*$hi/$nTotal);
			$text  = $str_hi." $ratio ($hi)";
			$tr2[] = sprintf($td_format, $ratio, $text, '', '', $text );
		}
		if ( $gr ) { // row2-group
			$ratio = sprintf('%0.1f%%',100*$gr/$nTotal);
			$text  = $str_gr." $ratio ($gr)";
			$tr2[] = sprintf($td_format, $ratio, $text, 'gr', 'gr', $text );
		}
	}
	if ( $nBlank ) {
		$ratio = sprintf('%0.1f%%',100*$nBlank/$nTotal);
		$text  = $str_un." $ratio ($nBlank)";
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
			<tr><td id="tdVote">
HTML;
	wp_nonce_field( 'dob_form_nonce_'.$vm_type, 'dob_form_nonce' );
	foreach ( $vm_legend as $val => $label ) {
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

	$html_submit = empty($user_id) ? $label_login : dob_common_get_message($post_id,$user_id,'offer');	// vote_post_latest timestamp
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
