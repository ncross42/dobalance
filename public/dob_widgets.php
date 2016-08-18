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
  private $title = '균형투표 결과'; //__( 'DoBalance Vote Result', DOBslug ), // Name

	function __construct() {/*{{{*/
		parent::__construct(
			'dob_vote_result', // Base ID
			'균형투표 결과', //__( 'DoBalance Vote Result', DOBslug ), // Name
			array(  // Args
				//'classname' => 'dob_class',	// CSS
				'description' => __( 'Review the Balanced Decision Hierarchy', DOBslug ), 
			)
		);
	}/*}}}*/

	// show widget from in Appearance /*{{{*/
	function form($instance) {	
		//$defaults = array ( 'title' => get_option('dob_dashboard_title') );
    $defaults = array ( 'title' => $this->title );
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

	function get_elect_html_stat($post_id,$stat) {/*{{{*/
    $dob_elect_cmb_vote = get_post_meta( $post_id, 'dob_elect_cmb_vote', true );
    $vm_begin = empty($dob_elect_cmb_vote['begin']) ? '' : $dob_elect_cmb_vote['begin'];
    $vm_end = empty($dob_elect_cmb_vote['end']) ? '' : $dob_elect_cmb_vote['end'];
		return dob_elect_html_stat($stat['nDirect'],$stat['nTotal'],$vm_begin,$vm_end,true);
	}/*}}}*/

	function get_history_html($logs) {/*{{{*/
    $label_my_history = '내 투표 기록'; //__('My Vote History', DOBslug);
    $label_date_time = '일시';          //__('Date Time', DOBslug);
    $label_value = '값';                //__('Value', DOBslug);
    $tr_history = '';
    foreach ( $logs as $log ) {
      $ip2long = ip2long($log->ip);
      $tr_history .= "
        <tr><td>".substr($log->ts,2)."</td><td>{$log->value}</td><td class='ip_td'>{$log->ip}</td></tr>";
    }
    $nLogs = count($logs);
    return <<<HTML
  <div class="panel-group">
    <div class="panel panel-default">
      <div class="panel-heading" data-toggle="collapse" data-target="#dob_widget_history">
        <span class="panel-title">$label_my_history <span class="label label-primary pull-right">$nLogs</span></span>
      </div>
      <div id="dob_widget_history" class="panel-collapse collapse in">
        <table id='table_log' class="table-bordered">
          <tr><th style="min-width:60px">$label_date_time</th><th>$label_value</th><th>ip</th></tr>
          $tr_history
        </table>
      </div>
    </div>
  </div>
HTML;
	}/*}}}*/

	//show widget in post / page
	function widget( $args, $instance ) {/*{{{*/
		// show only if single post
		if ( ! is_single() ) return;

		$post_id = get_the_ID();
    $cpt = get_post_type($post_id);
		if ( !in_array($cpt,['offer','elect']) ) return;

/*{{{*/ /*$args = [
  [name] => Widget DoBalance
  [id] => widget-dobalance
  [description] => Sidebar of DoBalance Voting
  [class] => 
  [before_widget] => 
  [after_widget] => 
  [before_title] => 
  [after_title] => 
  [widget_id] => dob_vote_result-2
  [widget_name] => 균형투표 결과
]; */ /*}}}*/
		extract($args);

		$stat = dob_common_cache($post_id,'stat',false);
		# 1. stat
    $html_stat = empty($stat) ? '' : ( 
      ($cpt=='offer') ? dob_vote_html_stat($stat['stat_sum'])
      : $this->get_elect_html_stat($post_id,$stat)
    );

		# 2. history
		$user_id = get_current_user_id();
		$html_history = '';
		if ( $user_id ) {
      $logs = dob_common_get_log($post_id,$user_id,$cpt);
      $html_history = $this->get_history_html($logs);
		}

		$content = <<<HTML
	$html_stat
	$html_history
HTML;

#file_put_contents('/tmp/w',$content);

		$title = isset($instance['title']) ?
			apply_filters('widget_title', $instance['title'])
			: $this->title;

		echo $before_widget;
		echo $before_title.$title.$after_title;
		echo $content;
		echo $after_widget;
	}/*}}}*/

}/*}}}*/

class Dob_Widget_Sub_Category extends WP_Widget {/*{{{*/

  private $title = '하위 카테고리'; //__( 'DoBalance Sub Category', DOBslug );

	function __construct() {/*{{{*/
		parent::__construct(
			'dob_sub_category', // Base ID
			$this->title,
			array(  // Args
				//'classname' => 'dob_class',	// CSS
				'description' => __( 'List Sub Category', DOBslug ), 
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
