<?php

require_once( DOBpath.'includes/jstree.class.php' );


function dob_admin_ajax_get_users($ttid) {
	global $wpdb;
	$sql = <<<SQL
SELECT chl, user_id, user_login, display_name
FROM {$wpdb->prefix}dob_user_category
	JOIN {$wpdb->prefix}term_taxonomy USING (taxonomy,term_taxonomy_id)
	JOIN {$wpdb->prefix}users ON user_id=ID
WHERE taxonomy = 'hierarchy' AND term_taxonomy_id = $ttid
SQL;
	return $wpdb->get_results($sql);
}

function dob_admin_ajax_check_child_users($lft,$rgt) {
	global $wpdb;
	$sql = <<<SQL
SELECT MAX(inf)
FROM {$wpdb->prefix}term_taxonomy 
WHERE taxonomy = 'hierarchy'
	AND $lft < lft AND rgt < $rgt
SQL;
	return $wpdb->get_var($sql);
}

add_action( 'wp_ajax_dob_admin_page_user', 'dob_admin_page_user' );
function dob_admin_page_user() { /*{{{*/
	if ( empty($_SESSION['LOGIN_IP']) ) {
		exit('error login');
	}

	$pid = empty($_GET['pid']) ? 0 : (int)$_GET['pid'];
	$rslt = array();
	if ( $pid ) {
		$users = dob_admin_ajax_get_users($pid);
		foreach ( $users as $v ) {
			$rslt[] = array(	// user info
				'id'				=> 'u'.$v->user_id,
				'text'			=> $v->display_name,
				'children'	=> false,
				'icon'			=> 'dashicons dashicons-universal-access',
				'li_attr'		=> array ('chl'=>$v->chl,'taxonomy'=>'user','style'=>'display:inline; margin-left:0px;'),
				'a_attr'		=> array ('title'=>$v->user_login),
			);
		}
	}

	$jstree = new jsTree(array('taxonomy'=>'hierarchy'));
	$branches = $jstree->get_children($pid);
	foreach ( $branches as $v ) {
		if ( empty($v['inf']) ) { 
			if ( empty($v['chl']) ) continue;
			$max_inf = dob_admin_ajax_check_child_users( (int)$v['lft'], (int)$v['rgt'] );
			if ( empty($max_inf) ) continue;
		}
		$ttid = (int)$v['term_taxonomy_id'];
		$users = dob_admin_ajax_get_users($ttid);

		switch ( $v['taxonomy'] ) {
		case 'hierarchy': $icon = 'dashicons dashicons-networking'; break;
		case 'group': $icon = 'dashicons dashicons-groups'; break;
		default: $icon = 'dashicons dashicons-category';
		}
		$a_attr = array ( 'slug'=>$v['slug'], 'pos'=>$v['pos'], 'taxonomy'=>$v['taxonomy'] );
		$rslt[] = array(	// branch info
			'id'					=> $ttid,
			'text'				=> $v['name'].'//'.$v['slug'].' ('.count($users).')',
			'children'		=> (1<$v['rgt']-$v['lft']) || !empty($users),
			'icon'				=> $icon,
			'li_attr'			=> array (),
			'a_attr'			=> $a_attr,
		);
	}

	header('Content-Type: application/json; charset=utf-8');
	exit ( json_encode($rslt,JSON_UNESCAPED_UNICODE) );

}/*}}}*/

