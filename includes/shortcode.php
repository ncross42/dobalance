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
<input id="bbs-toggle" type='checkbox' style='display: none'>
<label id="bbs-label" for="bbs-toggle">$label_bbs</label>
<div id="bbs">
  $bbs
</div>
<style type="text/css">
#bbs { margin-top: 10px; border: 1px solid black; /*width: 200px; height: 100px;*/ padding: 5px; }
#bbs-toggle:checked + #bbs-label + #bbs { display: none; }
#bbs-toggle + #bbs-label:after { content: " $label_close"; }
#bbs-toggle:checked + #bbs-label:after { content: " $label_open"; }
#bbs-label {
  background-color: yellow;
  box-shadow: inset 0 2px 3px rgba(255,255,255,0.2), inset 0 -2px 3px rgba(0,0,0,0.2);
  border-radius: 4px;
  display: inline-block;
  padding: 2px 5px;
  cursor: pointer;
}
</style>
HTML;

}

