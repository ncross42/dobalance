<?php

if ( ! defined ('WP_USE_THEMES') ) {
	define('WP_USE_THEMES', false);
	require_once (dirname(dirname(dirname(dirname(__DIR__)))).DIRECTORY_SEPARATOR.'wp-blog-header.php');
}
require_once (dirname(__DIR__).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'dob_common.inc.php');

############ version 0.1 ############
global $wpdb;

function recalc_chl() {/*{{{*/
	global $wpdb;

	$t_term_taxonomy = $wpdb->prefix.'term_taxonomy';

	// reset all chl
	$sql = "UPDATE $t_term_taxonomy SET chl = 0 WHERE taxonomy='hierarchy'";
	$wpdb->query($sql);

	$sql = "SELECT parent, COUNT(1) AS chl
		FROM $t_term_taxonomy 
		WHERE taxonomy='hierarchy' AND parent<>0
		GROUP BY parent";
	$rows = $wpdb->get_results($sql);
	foreach ( $rows as $r ) {
		$sql = "UPDATE $t_term_taxonomy SET chl = {$r->chl} WHERE term_taxonomy_id = {$r->parent}";
		$aff = $wpdb->query($sql);
		echo "\n".preg_replace('/\s+/',' ',print_r($r,true))." : ".var_export($aff,true);
	}
}/*}}}*/

function recalc_inf() {/*{{{*/
	global $wpdb;

	$t_term_taxonomy = $wpdb->prefix.'term_taxonomy';
	$t_user_category = $wpdb->prefix.'dob_user_category';

	// reset all inf
	$sql = "UPDATE $t_term_taxonomy SET inf = 0 WHERE taxonomy='hierarchy'";
	$wpdb->query($sql);

	$sql = "SELECT term_taxonomy_id AS ttid, lft, rgt, chl, COUNT(1) AS inf
		FROM $t_term_taxonomy tt JOIN $t_user_category uc USING(term_taxonomy_id)
		WHERE tt.taxonomy='hierarchy' AND uc.taxonomy='hierarchy'
		GROUP BY ttid";
	$rows = $wpdb->get_results($sql);
	foreach ( $rows as $r ) {
		if ( empty($r->chl) ) {
			$sql = "UPDATE $t_term_taxonomy SET inf = inf + {$r->inf}
				WHERE taxonomy='hierarchy' AND lft <= {$r->lft} AND rgt >= {$r->rgt}";
		} else {
			$sql = "UPDATE $t_term_taxonomy SET inf = inf + {$r->inf}
				WHERE taxonomy='hierarchy' AND lft < {$r->lft} AND rgt > {$r->rgt}";
		}
		$aff = $wpdb->query($sql);
		echo "\n".preg_replace('/\s+/',' ',print_r($r,true))." : ".var_export($aff,true);
	}
}/*}}}*/

function recalc_anc() {/*{{{*/
	global $wpdb;

	$t_term_taxonomy = $wpdb->prefix.'term_taxonomy';

	// reset all anc
	$sql = "UPDATE $t_term_taxonomy SET anc = 0 WHERE taxonomy='hierarchy'";
	$wpdb->query($sql);

	$sql = "SELECT term_taxonomy_id AS ttid, (
				SELECT GROUP_CONCAT(term_taxonomy_id) 
				FROM $t_term_taxonomy 
				WHERE taxonomy='hierarchy' AND lft < tt.lft AND rgt > tt.rgt
			) AS anc
		FROM $t_term_taxonomy tt
		WHERE taxonomy='hierarchy'";
		//GROUP BY parent";
	$rows = $wpdb->get_results($sql);
	foreach ( $rows as $r ) {
		$sql = "UPDATE $t_term_taxonomy SET anc = '{$r->anc}' WHERE term_taxonomy_id = {$r->ttid}";
		$aff = $wpdb->query($sql);
		//echo "\n".preg_replace('/\s+/',' ',print_r($r,true))." : ".var_export($aff,true);
	}
}/*}}}*/

recalc_chl();

recalc_inf();

recalc_anc();

dob_common_cache(-1,'all','recalc_inf_chl_anc.php');

echo PHP_EOL;
