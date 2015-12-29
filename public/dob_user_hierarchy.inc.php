<?php

function get_user_hierarchy_info( $user_id ) {/*{{{*/
	global $wpdb;
	if ( empty($user_id) ) $user_id = get_current_user_id();

	$table_user_hierarchy = $wpdb->prefix . 'dob_user_hierarchy';
	$table_term_taxonomy = $wpdb->prefix . 'term_taxonomy';
	$sql = <<<SQL
SELECT *
FROM `$table_user_hierarchy`
	JOIN `$table_term_taxonomy` USING (`term_taxonomy_id`)
WHERE user_id = %d
SQL;
	$prepare = $wpdb->prepare($sql, $user_id);
	return $ret = $wpdb->get_row($prepare,ARRAY_A);
}/*}}}*/

function get_user_hierarchy( $user_id ) {/*{{{*/
	global $wpdb;
	if ( empty($user_id) ) $user_id = get_current_user_id();

	$info = get_user_hierarchy_info($user_id);
	$pr = print_r($info,true);

	$table_user_hierarchy = $wpdb->prefix . 'dob_user_hierarchy';
	$table_term_taxonomy = $wpdb->prefix . 'term_taxonomy';
	$table_terms = $wpdb->prefix . 'terms';
	$sql = <<<SQL
SELECT
	lft, term_taxonomy_id, lvl, 
	name, slug, GROUP_CONCAT(user_id) AS user_ids
FROM `$table_term_taxonomy` 
	JOIN $table_terms USING (term_id)
	LEFT JOIN `$table_user_hierarchy` USING (`term_taxonomy_id`)
WHERE term_taxonomy_id = {$info['term_taxonomy_id']}
	OR ( `lft` < {$info['lft']} AND `rgt` > {$info['rgt']} )
GROUP BY `term_taxonomy_id`
ORDER BY `lvl`
SQL;

	return $ret = $wpdb->get_results($sql, ARRAY_A);
}/*}}}*/

function dob_get_voted_data_user($post_id,$user_id=0) {/*{{{*/
	global $wpdb;
	if ( empty($user_id) ) $user_id = get_current_user_id();

	$table_vote_post_latest = $wpdb->prefix . 'dob_vote_post_latest';
	$table_users = $wpdb->prefix . 'users';
	$sql = <<<SQL
SELECT *
FROM `$table_vote_post_latest`
	JOIN `$table_users` ON user_id = ID
WHERE post_id = %d AND user_id = %d
SQL;
	$prepare = $wpdb->prepare($sql, $post_id, $user_id);
	return $ret = $wpdb->get_row($prepare,ARRAY_A);
}/*}}}*/

