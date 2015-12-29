<?php
/**
 * Create site pages for this plugin
 */

add_action( 'wp', 'dob_wp_init' );
function dob_wp_init() {/*{{{*/
	// Load js file
	wp_register_script('bdd-js', plugins_url('assets/js/bdd.js',__FILE__), array('jquery'));
	#wp_localize_script('bdd-js', 'bddjs', array( 'ajax_url' => admin_url( 'admin-ajax.php' )));
	wp_enqueue_script('jquery');
	wp_enqueue_script('bdd-js');

	// Load css file
	wp_enqueue_style( 'bdd-css', plugins_url( 'assets/css/bdd.css', __FILE__ ) );
}/*}}}*/

$global_real_ip = dob_get_real_ip();
function dob_get_real_ip() {/*{{{*/
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

function dob_get_voted_data($post_id,$user_id=0) {/*{{{*/
	global $wpdb;
	if ( empty($user_id) ) $user_id = get_current_user_id();

	$table_name = $wpdb->prefix . 'dob_vote_post_latest';
	$sql = <<<SQL
SELECT *
FROM `{$table_name}`
WHERE post_id = %d AND user_id = %d
SQL;
	$prepare = $wpdb->prepare($sql, $post_id, $user_id);
	return $ret = $wpdb->get_row($prepare,ARRAY_A);
}/*}}}*/

function dob_get_voted_message($post_id) {/*{{{*/
	$message = 'plz vote';
	if ( $ret = dob_get_voted_data($post_id) ) {
		$message = 'last voted : '.substr($ret['ts'],0,10);
	}
	return $message;
}/*}}}*/

function dob_get_vote_count($post_id) {/*{{{*/
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

/**
 * Show the vote content
 * @param $content string
 * @param $param string
 * @return string
 */
add_filter('the_content', 'dob_site_vote_content');
function dob_site_vote_content($content) {/*{{{*/
	if ( !is_page() && !is_feed()
		/*&& get_option('dob_vote_show_on_pages') */
	) {
		$dob_vote_content = dob_vote_content($bEcho=false);
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

/**
 * Get the like output on site
 * @param $bEcho bool
 * @return string
 */
function dob_vote_content($bEcho = false) {/*{{{*/
	global $wpdb;
	$post_id = get_the_ID();
	$dob_vote = '';

	// Get the posts ids where we do not need to show like functionality
	$allowed_posts = $excluded_posts = $excluded_categories = $excluded_sections = array();
/*{{{*/	/*$allowed_posts = explode(",", get_option('dob_vote_allowed_posts'));
	$excluded_posts = explode(",", get_option('dob_vote_excluded_posts'));
	$excluded_categories = get_option('dob_vote_excluded_categories');
	$excluded_sections = get_option('dob_vote_excluded_sections');
	if (empty($excluded_categories)) $excluded_categories = array();
	if (empty($excluded_sections)) $excluded_sections = array();*//*}}}*/

	// Checking for excluded section. if yes, then dont show the like/dislike option
	if ( (in_array('home', $excluded_sections) && is_home()) 
		|| (in_array('archive', $excluded_sections) && is_archive())
		|| in_array($post_id, $excluded_posts) // Checking for excluded posts
	) {
		return;
	}

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
	$nonce = wp_create_nonce('dob_vote_vote_nonce');
	$ajax_like_link = admin_url('admin-ajax.php?action=dob_vote_process_vote&task=like&post_id=' . $post_id . '&nonce=' . $nonce);
	$ajax_unlike_link = admin_url('admin-ajax.php?action=dob_vote_process_vote&task=unlike&post_id=' . $post_id . '&nonce=' . $nonce);

	$arr_vote_count = dob_get_vote_count($post_id);
	$like_count = $arr_vote_count['like'];
	$unlike_count = $arr_vote_count['unlike'];
	$msg = dob_get_voted_message($post_id);
	$alignment = 'align-right'; //("left" == get_option('dob_vote_alignment')) ? 'align-left' : 'align-right';
	$style = 'style1'; //(get_option('dob_vote_voting_style') == "") ? 'style1' : get_option('dob_vote_voting_style');

	$dob_vote .= "<div class='watch-action'>";
	$dob_vote .= "<div class='watch-position " . $alignment . "'>";

	$dob_vote .= "<div class='action-like'>";
	$dob_vote .= "<a class='lbg-" . $style . " like-" . $post_id . " jlk' href='javascript:void(0)' data-task='like' data-post_id='" . $post_id . "' data-nonce='" . $nonce . "' rel='nofollow'>";
	$dob_vote .= "<img src='" . plugins_url( 'assets/images/pixel.gif' , __FILE__ ) . "' title='" . $title_text_like . "' />";
	$dob_vote .= "<span class='lc-" . $post_id . " lc'>" . $like_count . "</span>";
	$dob_vote .= "</a></div>";

	$dob_vote .= "<div class='action-unlike'>";
	$dob_vote .= "<a class='unlbg-" . $style . " unlike-" . $post_id . " jlk' href='javascript:void(0)' data-task='unlike' data-post_id='" . $post_id . "' data-nonce='" . $nonce . "' rel='nofollow'>";
	$dob_vote .= "<img src='" . plugins_url( 'assets/images/pixel.gif' , __FILE__ ) . "' title='" . $title_text_unlike . "' />";
	$dob_vote .= "<span class='unlc-" . $post_id . " unlc'>" . $unlike_count . "</span>";
	$dob_vote .= "</a></div> ";

	$dob_vote .= "</div> ";
	$dob_vote .= "<div class='status-" . $post_id . " status " . $alignment . "'>" . $msg . "</div>";
	$dob_vote .= "</div><div class='wti-clear'></div>";

	if ($bEcho) echo $dob_vote;
	else return $dob_vote;
}/*}}}*/
