<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 *
 * @package   DoBalance
 * @author    Your Name <email@example.com>
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2015 Your Name or Company Name
 */

require_once( DOBpath . 'public/dob_elect.php' );
require_once( DOBpath . 'public/dob_vote.php' );

$message = '[message] ';

//$message = '<pre>'.print_r($_POST,true).'</pre>';
//$message .= wp_verify_nonce( $_POST['dobalance_admin_cart'], 'dobalance_admin_cart' ) ? 'yes' : 'no';
//$message .= "\n<br>";

global $wpdb,$current_user;
$user_id = (int)get_current_user_id();	// $current_user->ID;

# add cart process
if ( is_array($_POST) 
	&& isset($_POST['dobalance_admin_cart']) 
	&& wp_verify_nonce( $_POST['dobalance_admin_cart'], 'dobalance_admin_cart' ) 
	&& isset($_POST['post']) && is_array($_POST['post'])
) {
	$nVote = $nSkip = $nErr = 0;
	foreach ( $_POST['post'] as $post_type_id ) {
		if ( ! preg_match('/(vote|elect)~\d+~[-\d]+/',$post_type_id) ) {
			++$nErr;
			continue;
		}
		list( $type, $post_id, $value ) = explode('~',$post_type_id);
		//var_dump('<pre>',$type, $post_id,'</pre>');

		/*
		$func = 'dob_'.$type.'_update';
		if ( $msg = $func($user_id,$post_id,$value) ) {
			$message .= "\n$post_id : $msg";
			if ( $msg == 'No Change' ) ++$nSkip;
			else ++$nErr;
		}

		$t_cart = $wpdb->prefix.'dob_cart';
		$sql = "DELETE FROM `$t_cart`
			WHERE user_id = $user_id AND type='$type' AND post_id = $post_id";
		 */
		// replace
		$t_latest = $wpdb->prefix.'dob_'.($type=='vote'?'vote_post':'elect').'_latest';
		$t_cart = $wpdb->prefix.'dob_cart';
		$sql = "REPLACE INTO $t_latest ( post_id, user_id, value )
			SELECT post_id, user_id, value 
			FROM $t_cart
			WHERE user_id = $user_id AND type='$type' AND post_id = $post_id";
		if ( $wpdb->query($sql) ) { // clear
			++$nVote;
			$sql = "DELETE FROM `$t_cart`
				WHERE user_id = $user_id AND type='$type' AND post_id = $post_id";
			$wpdb->query($sql);
		} else {	// error or skip
			++$nErr;
		}
	}

	$message .= " Voted: $nVote // Skipped: $nSkip // Error: $nErr";
}

$sql = "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
	WHERE TABLE_NAME='{$wpdb->prefix}dob_user_category' AND COLUMN_NAME='taxonomy'";
$COLUMN_TYPE = $wpdb->get_var($sql);
eval( '$taxonomy_db = '.str_replace('enum','array',$COLUMN_TYPE).';' );

$t_cart = $wpdb->prefix.'dob_cart';
$t_posts = $wpdb->prefix.'posts';
$sql = "SELECT c.*, p.post_title
	FROM $t_cart c JOIN $t_posts p ON post_id=ID
	WHERE user_id = $user_id";
$rows = $wpdb->get_results($sql,ARRAY_A);

$label_batch   = '일괄 투표'; //__('Batch Voting', DOBslug);
?>

<div class="wrap">

	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<div class="postbox">
		<form id='formCart' method="post">
		<?php wp_nonce_field( 'dobalance_admin_cart', 'dobalance_admin_cart' ); ?>
		<h3 class="hndle"><span><?php _e( 'My Voting Cart', DOBslug ); ?></span></h3>
		<div class="inside">
			<span style="background-color:#F2DEDE"><?php echo $message; ?></span>

			<table class="wp-list-table widefat fixed striped pages">
				<thead>
					<tr>
						<td class="check-column"><input id="cb-select-all-1" type="checkbox"></td>
						<th scope="col" class="">제목</th>
						<th scope="col" class="">투표값</th>
						<th scope="col" class="">일자</th>
					</tr>
				</thead>

				<tbody id="the-list">
<?php	
	foreach ( $rows as $r ) :
		extract($r);	// post_id, type, user_id, value, ts, post_title
		$myval = (int)$value;
		$permalink = get_permalink($post_id);

		$dob_elect_cmb_vote = get_post_meta( $post_id, 'dob_elect_cmb_vote', true );
		$vm_type = empty($dob_elect_cmb_vote['type']) ? 'updown': $dob_elect_cmb_vote['type'];
		$vm_data = empty($dob_elect_cmb_vote['data']) ? array() : $dob_elect_cmb_vote['data'];
		$vm_begin = empty($dob_elect_cmb_vote['begin']) ? '' : $dob_elect_cmb_vote['begin'];
		$vm_end = empty($dob_elect_cmb_vote['end']) ? '' : $dob_elect_cmb_vote['end'];

    $vm_label = array( -1 => '모두반대', 0=>'기권' );/*{{{*/
    if ( $vm_type == 'updown' ) {
      $vm_label[1] = '찬성';
    } else {  // choice, plural
      foreach ( $vm_data as $k => $v ) {
        $vm_label[$k+1] = $v;
      }
    }/*}}}*/

		$html_checked = '';
		foreach ( $vm_label as $val => $label ) {
			$html_input = '';
			if ( $vm_type == 'plural' ) {
				$checked = in_array($val,$myval) ? 'CHECKED' : '';
				$html_input = "<input type='checkbox' name='dob_val[$val]' value='1' $checked>";
			} else {
				$checked = ($val===$myval) ? 'CHECKED' : '';
				$html_input = "<input type='radio' name='dob_val' value='$val' $checked>";
			}
			$html_checked .= " <label>$html_input $label</label> ";
		}

		echo <<<HTML
					<tr>
						<th scope="row" class="check-column">
							<input type="checkbox" name="post[]" value="$type~$post_id~$myval">
						</th>
						<td class="title page-title" >
							<!--a class="row-title" target="_blank" href="$permalink">$post_title</a-->
							<a target="_blank" href="$permalink">$post_title</a>
						</td>
						<td class="" >$html_checked</td>
						<td class="date">$ts</td>
					</tr>
HTML;
	endforeach;
?>
				</tbody>
			</table>

			<div class="container">
				<?php //echo $form; ?>
			</div>

			<!--button type="reset" id="reset_cart_category" class="button button-large">Reset</button-->
			<br>
			<?php submit_button($label_batch,'primary','submit',false, '' ); ?>
		</div>
		</form>
	</div>

</div>

<script>
(function($) {
  "use strict";
	$(function() {
		$("#formCart").submit(function( event ) {
			//alert('asd');
			//event.preventDefault();
			//window.location.reload();
		});
	});

}(jQuery));
</script>
