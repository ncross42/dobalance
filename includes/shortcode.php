<?php

add_shortcode('dobalance_bbs', 'dobalance_bbs');
function dobalance_bbs(){
  global $wpdb;
  $label_bbs = '게시판';  //__('BBS',DOBslug)
  $label_open = '열기';  //__('Open',DOBslug)
  $label_close = '닫기';  //__('Close',DOBslug)

  $bbs = '';
  $title = single_term_title( '', false ); //get_the_archive_title();
  $id = $wpdb->get_var($wpdb->prepare("SELECT uid FROM {$wpdb->prefix}kboard_board_setting WHERE board_name=%s",$title));
  if ( ! empty($id) ) {
    $bbs = kboard_builder(['id'=>$id]);
  }

  if ( empty($bbs) ) return ;

  echo <<<HTML
<!--div class="panel-group"-->
  <div class="panel panel-default">
    <div class="panel-heading" data-toggle="collapse" data-target="#dobalance_bbs">
      <span class="panel-title">$label_bbs</span>
    </div>
    <div id="dobalance_bbs" class="panel-collapse collapse in">
      $bbs
    </div>
  </div>
<!--/div-->
HTML;
}

