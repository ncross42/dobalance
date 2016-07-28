<?php
/**
 * Create widgets for this plugin
 */

include_once ('dob_common.inc.php');

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

	function get_stat_html_old($stat,$nTotal,$post_id,$type='offer') {/*{{{*/
    if ( $type=='offer' )
      $html = dob_vote_html_stat($stat['nFixed'],$stat['nGroup'],$stat['nDirect'],$nTotal);
    else {
      $dob_elect_cmb_vote = get_post_meta( $post_id, 'dob_elect_cmb_vote', true );
      $vm_begin = empty($dob_elect_cmb_vote['begin']) ? '' : $dob_elect_cmb_vote['begin'];
      $vm_end = empty($dob_elect_cmb_vote['end']) ? '' : $dob_elect_cmb_vote['end'];
      $html = dob_elect_html_stat($stat['nDirect'],$nTotal,$vm_begin,$vm_end,true);
    }
		return $html;
	}/*}}}*/

	function get_stat_html($post_id,$type='offer') {/*{{{*/
		list($stat,$ts) = dob_common_cache($post_id,'stat',false);

    if ( $type=='offer' )
      $html = dob_vote_html_stat($stat['nFixed'],$stat['nGroup'],$stat['nDirect'],$stat['nTotal']);
    else {
      $dob_elect_cmb_vote = get_post_meta( $post_id, 'dob_elect_cmb_vote', true );
      $vm_begin = empty($dob_elect_cmb_vote['begin']) ? '' : $dob_elect_cmb_vote['begin'];
      $vm_end = empty($dob_elect_cmb_vote['end']) ? '' : $dob_elect_cmb_vote['end'];
      $html = dob_elect_html_stat($stat['nDirect'],$stat['nTotal'],$vm_begin,$vm_end,true);
    }
		return $html;
	}/*}}}*/

	function get_history_html($logs) {/*{{{*/
    $label_my_history = '내 투표 기록'; //__('My Vote History', DOBslug);
    $tr_history = '';
    foreach ( $logs as $log ) {
      $tr_history .= "
        <tr><td>{$log->ts}</td><td>{$log->value}</td><td>{$log->ip}</td></tr>";
    }
    return <<<HTML
<style>
#table_log th { background-color:#eee; text-transform:none; text-align:center; padding:0; }
</style>
  <li class='toggle'>
    <h3># $label_my_history<span class='toggler'>[close]</span></h3>
    <div class='panel' style='display:block'>
      <table id='table_log'>
        <tr><th>date_time</th><th width="60px">value</th><th width="100px">ip</th></tr>
        $tr_history
      </table>
    </div>
  </li>
HTML;
	}/*}}}*/

	//show widget in post / page
	function widget( $args, $instance ) {/*{{{*/
		// show only if single post
		if ( ! is_single() ) return;

		$post_id = get_the_ID();
    $cpt = get_post_type($post_id);
		if ( !in_array($cpt,['offer','elect']) ) return;

		extract($args);

		/*$cached = dob_common_cache($post_id,'all',false,$cpt);
		if ( ! empty($cached) ) extract($cached); // enum('all','stat','result','detail')
//echo '<pre>'.print_r($cached['stat'],true).'</pre>';
		# 1. stat
		$nTotal = dob_common_get_users_count($cached['ttids']);	// get all user count
    $html_stat = $this->get_stat_html($cached['stat'],$nTotal,$post_id,$cpt);*/
		$html_stat = $this->get_stat_html($post_id,$cpt);

		# 2. history
		$user_id = get_current_user_id();
		$html_history = '';
		if ( $user_id ) {
      $logs = dob_common_get_log($post_id,$user_id,$cpt);
      $html_history = $this->get_history_html($logs);
		}

		$content = <<<HTML
<ul id="toggle-view">
	<div class="bg-info">$html_stat</div>
	<br>
	<div class="bg-info">$html_history</div>
</ul>
HTML;

#file_put_contents('/tmp/w',$content);

		$title = isset($instance['title']) ?
			apply_filters('widget_title', $instance['title'])
			: __('DoBalance Vote Result', DOBslug);

		echo $before_widget;
		echo $before_title.$title.$after_title;
		echo $content;
		echo $after_widget;
	}/*}}}*/

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
