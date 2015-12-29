<?php
/**
 * Create ajax callback for this plugin
 */

function init_dob_db() {/*{{{*/
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	global $wpdb;

	// Creating the like post table on activating the plugin
	$table_name = $wpdb->prefix . 'dob_vote_post_latest';
	if ( empty($wpdb->get_var("show tables like '$table_name'") ) ) {
		$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
			`post_id` int(11) NOT NULL DEFAULT 0,
			`user_id` int(11) NOT NULL DEFAULT 0,
			`value` tinyint(2) NOT NULL DEFAULT 0,
			`ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`post_id`,`user_id`),
			KEY (`post_id`,`value`)
		)";
		dbDelta($sql);
	}

	$table_name = $wpdb->prefix . 'dob_vote_post_log';
	if ( empty($wpdb->get_var("show tables like '$table_name'") ) ) {
		$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
			`user_id` int(11) NOT NULL DEFAULT 0,
			`post_id` int(11) NOT NULL DEFAULT 0,
			`ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`value` tinyint(2) NOT NULL DEFAULT 0,
			`ip` varchar(250) COLLATE latin1_general_ci NOT NULL DEFAULT '',
			PRIMARY KEY (`user_id`,`post_id`,`ts`)
		)";
		dbDelta($sql);
	}

	if ( empty($wpdb->get_var("show columns from wp_term_taxonomy like 'lft';") ) ) {
		$sql = "CREATE TABLE `wp_term_taxonomy` (
			`term_taxonomy_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`term_id` bigint(20) unsigned NOT NULL DEFAULT '0',
			`taxonomy` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			`description` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
			`parent` bigint(20) unsigned NOT NULL DEFAULT '0',
			`count` bigint(20) NOT NULL DEFAULT '0',
			`lft` int(11) NOT NULL DEFAULT '0',
			`rgt` int(11) NOT NULL DEFAULT '0',
			`lvl` int(11) NOT NULL DEFAULT '0',
			`pos` int(11) NOT NULL DEFAULT '0',
			PRIMARY KEY (`term_taxonomy_id`),
			UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`),
			KEY `taxonomy` (`taxonomy`),
			KEY `IDX_taxonomy_parent_pos` (`taxonomy`,`parent`,`pos`)
		)";
		dbDelta($sql);
	}
}/*}}}*/

add_action( 'wp_ajax_bdd', 'dob_ajax_callback' );
function dob_ajax_callback() {
	global $wpdb, $global_real_ip;
	
	// Get request data
	$post_id = (int)$_REQUEST['post_id'];
	$task = $_REQUEST['task'];

	// Check for valid access
	if ( !wp_verify_nonce( $_REQUEST['nonce'], 'dob_vote_vote_nonce' ) ) {
		$error = 1;
		$msg = 'Invalid access'; //__( 'Invalid access', 'wti-like-post' );
	} else if ( ! is_user_logged_in() ) {
			// User needs to login to vote but has not logged in
			$error = 1;
			$msg = 'plz login'; //get_option( 'wti_like_post_login_message' );
	} else {
		#$current_user = wp_get_current_user();
		#$user_id = (int)$current_user->ID;
		$user_id = get_current_user_id();

		// get value
		$old_row = dob_get_voted_data($post_id,$user_id);
		$old_value = is_null($old_row) ? null : (int)$old_row['value'];
		$value = ($task == "like") ? 1 : -1; 
		$value = ($old_value == $value) ? 0 : $value;	// check cancel vote.

		// INSERT dob_vote_post_log
		$sql = "INSERT IGNORE INTO `{$wpdb->prefix}dob_vote_post_log` SET
			user_id = %d, post_id = %d, value = %d, ip = %s";
		$prepare = $wpdb->prepare($sql, $user_id, $post_id, $value, $global_real_ip );
		$success = $wpdb->query( $prepare );
		if ( empty($success) ) { // failed (duplicated)
			$error = 1;
			$msg = "DB ERROR(SQL)<br>\n: ".$sql;
			$msg = "TOO FAST CLICK~!! ";
		} else {	// success == 1 (affected_rows)
			// UPDATE dob_vote_post_latest
			$table_name = $wpdb->prefix.'dob_vote_post_latest';
			if ( is_null($old_value) ) {
				$sql = "INSERT INTO `$table_name` SET
					post_id = %d, user_id = %d, value = %d";
				$prepare = $wpdb->prepare($sql, $post_id, $user_id, $value );
			} else {			
				$sql = "UPDATE `$table_name` SET value = %d
					WHERE post_id = %d AND user_id = %d ";
				$prepare = $wpdb->prepare( $sql, $value, $post_id, $user_id );
			}
			$success = $wpdb->query( $prepare );
			if ($success) {
				$error = 0;
				$msg = 'Thanks for your vote.'; //get_option( 'wti_like_post_thank_message' );
			} else {
				$error = 1;
				$msg = "DB ERROR(SQL)<br>\n: ".$sql;
			}
		}
		
		$arr_vote_count = dob_get_vote_count($post_id);
	}

	// Check for method of processing the data
	if ( !empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) 
		&& 'xmlhttprequest' == strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) 
	) {
		$result = array(
			'msg' => $msg,
			'error' => $error,
			'like' => $arr_vote_count['like'],
			'unlike' => $arr_vote_count['unlike']
		);
		header('Content-type: application/json');
		echo json_encode($result,JSON_UNESCAPED_UNICODE);
	} else {
		header( 'location:' . $_SERVER['HTTP_REFERER'] );
	}

	exit;
}
