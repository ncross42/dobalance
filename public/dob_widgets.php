<?php
/**
 * Create widgets for this plugin
 */

include_once ('dob_user_hierarchy.inc.php');

add_action( 'widgets_init', 'dob_widget_init' );
function dob_widget_init() { register_widget('Dob_Widget'); }

class Dob_Widget extends WP_Widget {

	function __construct() {/*{{{*/
		parent::__construct(
			'dob_vote_result', // Base ID
			__( 'DoBalance Vote Result', 'dobalance' ), // Name
			array(  // Args
				//'classname' => 'dob_class',	// CSS
				'description' => __( 'Review the Balanced Decision Hierachy', 'dobalance' ), 
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

	//show widget in post / page
	function widget( $args, $instance ) {
		extract($args);

		$title = apply_filters('widget_title', $instance['title']);

		// show only if single post
		if ( ! is_single() ) {
			$content = '<pre>'.htmlentities(print_r($args,true)).'</pre>';
			$content = '<pre>decision hierachy can be analyzed only on single post</pre>';
		} else {
			# 1. get user hierachy
			/*
			$options = get_option('org_chart_sample');
			$chart_json = $options['chart_json'];
			$hash_chart = json_decode($chart_json,true);
#$contents = '<pre>'.htmlentities(print_r($hash_chart,true)).'</pre>';
#file_put_contents('/tmp/hash.php', print_r($hash_chart,true), FILE_APPEND );
			 */

			# 2. build parent hierachy
			$hierachy = array();
			$post_id = get_the_ID();
			$uid = $user_id = get_current_user_id();
			$text_chart ='';

			$value_result = 0;
			$hierachy = get_user_hierarchy($user_id);
			foreach ( $hierachy as $row ) {
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
		}

		echo $before_widget;
		echo $before_title.$title.$after_title;
		echo $contents;
		echo $content;
		echo $after_widget;
	}
}

