<?php
/**
 * Create widgets for this plugin
 */

include_once ('dob_user_hierarchy.inc.php');

add_action( 'widgets_init', 'dob_widget_init' );
function dob_widget_init() { 
	register_widget('Dob_Widget_Vote_Result');
	register_widget('Dob_Widget_Sub_Category');
}

class Dob_Widget_Vote_Result extends WP_Widget {/*{{{*/

	private $ttids;

	function __construct() {/*{{{*/
		parent::__construct(
			'dob_vote_result', // Base ID
			__( 'DoBalance Vote Result', 'dobalance' ), // Name
			array(  // Args
				//'classname' => 'dob_class',	// CSS
				'description' => __( 'Review the Balanced Decision Hierarchy', 'dobalance' ), 
			)
		);
	}/*}}}*/

	// show widget from in Appearance /*{{{*/
	function form($instance) {	
		//$defaults = array ( 'title' => 'get_option('dob_dashboard_title') );
		$defaults = array ( 'title' => __( 'DoBalance Vote Result', 'dobalance' ) );
		$instance = wp_parse_args( (array) $instance, $defaults );

		$title = esc_attr($instance['title']);

		echo '<p>Title <input type="text" class="widefat" name="'.$this->get_field_name('title').'" value="'.$title.'" />';
	}/*}}}*/

	// save widget form/*{{{*/
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		return $instance;
	}/*}}}*/

	function dob_get_log($post_id,$user_id) {/*{{{*/
		global $wpdb;
		$t_vote_post_log	= $wpdb->prefix.'dob_vote_post_log';
		$sql = <<<SQL
SELECT *
FROM `$t_vote_post_log` 
WHERE post_id = $post_id AND user_id=$user_id
SQL;
		return $wpdb->get_results($sql);
	}/*}}}*/

	//show widget in post / page
	function widget( $args, $instance ) {
		// return if not single post
		if ( ! is_single() ) return;

		$post_id = get_the_ID();
		if ( 'offer' != get_post_type($post_id) ) return;

		extract($args);

		$uid = $user_id = get_current_user_id();
		$cached = dob_vote_cache($post_id,'all');
		if ( ! empty($cached) ) extract($cached);
file_put_contents('/tmp/cached',print_r($cached,true));

		// show only if single post
		# 1. stat
		$nFixed = $nGroup = $nDirect = $nTotal = 0;
		if ( isset($stat) ) extract($stat);	// $nFixed,$nGroup,$nDirect,$nTotal
		$nTotal = dob_vote_get_users_count($cached['ttids']);	// get all user count
		$html_stat = dob_vote_html_stat($nFixed,$nGroup,$nDirect,$nTotal);

		# 2. history
		$html_history = '';
		if ( $user_id ) {
			$label_my_history = '내 투표 기록'; //__('My Vote History', DOBslug);
			$tr_history = '';
			foreach ( $this->dob_get_log($post_id,$user_id) as $log ) {
				$tr_history .= "
					<tr><td>{$log->ts}</td><td>{$log->value}</td><td>{$log->ip}</td></tr>";
			}
			$html_history = <<<HTML
			<li class='toggle'>
				<h3># $label_my_history<span class='toggler'>[close]</span></h3>
				<div class='panel' style='display:block'>
					<table id='table_log'>
						<tr><th>date_time</th><th width="60px">value</th><th width="100px">ip</th></tr>
						$tr_history
					</table>
<style>
#table_log th { background-color:#eee; text-transform:none; text-align:center; padding:0; }
</style>
				</div>
			</li>
HTML;
		}

		$content = <<<HTML
<ul id="toggle-view">
	<div class="bg-info">$html_stat</div>
	<br>
	<div class="bg-info">$html_history</div>
</ul>
HTML;

		#file_put_contents('/tmp/w',$content);

		/* # 1. build parent hierarchy
		$hierarchy = array();
		$text_chart ='';

		$value_result = 0;
		$hierarchy = get_user_hierarchy($user_id);
		foreach ( $hierarchy as $row ) {
			$tab = $row['lvl'].')';
			for ( $i=0; $i<$row['lvl']; ++$i ) { $tab .= " "; }
			$tab .= $row['name'];

			$value_text = '';
			$value_current = 0;
			$user_ids = empty($row['user_ids']) ? array() : explode(',',$row['user_ids']);
			#var_dump($user_ids);
			# 3. analyze balanced decision.
			if ( empty($user_ids) ) {
				$value_text = 'NULL';
			} else {
				$value_sum = 0;
				foreach ( $user_ids as $uid ) {
					$data = dob_get_voted_data_user($post_id,$uid);
					$value_text .= $data['user_login'].':'.$data['value'].',';
					$value_sum += (int)$data['value'];
				}
				if ( 0.666 < abs($value_sum/count($user_ids)) ) {
					$value_current = (0<$value_sum) ? 1 : -1;
				} else {
					$value_current = 0;
				}
			}
			$value_result = $value_current ? $value_current : $value_result;
			$value_text = substr($value_text,0,-1);
			$text_chart .= "\n$tab:$value_result [$value_current]($value_text)"; 
		}
		$content = '<pre>'.htmlentities(print_r($text_chart,true)).'</pre>';
		 */

		$title = isset($instance['title']) ?
			apply_filters('widget_title', $instance['title'])
			: __('DoBalance Vote Result', DOBslug);

		echo $before_widget;
		echo $before_title.$title.$after_title;
		//echo $contents;
		echo $content;
		echo $after_widget;
	}
}/*}}}*/

class Dob_Widget_Sub_Category extends WP_Widget {/*{{{*/

	function __construct() {/*{{{*/
		parent::__construct(
			'dob_sub_category', // Base ID
			__( 'DoBalance Sub Category', 'dobalance' ), // Name
			array(  // Args
				//'classname' => 'dob_class',	// CSS
				'description' => __( 'List Sub Category', 'dobalance' ), 
			)
		);
	}/*}}}*/

	// show widget from in Appearance /*{{{*/
	function form($instance) {	
		//$defaults = array ( 'title' => 'get_option('dob_dashboard_title') );
		$defaults = array ( 'title' => '하위 카테고리' /*__('Sub Categories', DOBslug)*/ );
		$instance = wp_parse_args( (array) $instance, $defaults );

		$title = esc_attr($instance['title']);

		echo '<p>Title <input type="text" class="widefat" name="'.$this->get_field_name('title').'" value="'.$title.'" />';
	}/*}}}*/

	// save widget form/*{{{*/
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		return $instance;
	}/*}}}*/

	//show widget in post / page
	function widget( $args, $instance ) {
		// return if single post
		if ( is_single() ) return;

		$taxonomy = $slug = ''; 
		if ( $slug = get_query_var('hierarchy') ) {
			$taxonomy = 'hierarchy';
		} elseif ( $slug = get_query_var('topic') ) {
			$taxonomy = 'topic';
		} else return;

		extract($args);

		$li = '';
		$label_no_sub = '없음'; //__('no sub category', DOBslug);
		$categories = dob_get_sub_categories($taxonomy,$slug);
		if ( empty($categories) ) $li = '<li>'.$label_no_sub.'</li>';
		else foreach ( $categories as $c ) {
			$li .= "<li><a href='/?$taxonomy={$c->slug}'>{$c->name}</a></li>";
		}

		echo $before_widget;
		echo $before_title.$instance['title'].$after_title;
		//echo $contents;
		echo '<ul class="offcanvas_side">'.$li.'</ul>';
		echo $after_widget;
	}
}/*}}}*/
