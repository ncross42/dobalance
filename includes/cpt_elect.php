<?php

add_action( 'init', 'dob_register_cpt_elect' );
function dob_register_cpt_elect() {

	$singular = '비밀선거';	// 'Elect';
	$plural = '비밀선거';	// 'Elects';

	$labels = array(/*{{{*/
		'name'							=> __( $plural, DOBslug ),
		'singular_name'			=> __( $singular, DOBslug ),	// value of 'name'
		'menu_name'					=> __( $plural ),							// value of 'name'
		'name_admin_bar'		=> __( $singular ),							// value of 'singular_name'
		'all_items'					=> __( 'All Elects' ),				// value of 'name'
		'add_new'						=> __( 'Add New' ),
		'add_new_item'			=> _x( 'Add New', $singular, DOBslug ),
		'edit_item'					=> __( 'Edit' ),
		'new_item'					=> _x( 'New' , $singular, DOBslug ),
		'view_item'					=> _x( 'View' , $singular, DOBslug ),
		'search_items'			=> _x( 'Search', $plural, DOBslug ),
		'not_found'					=> __( "No $plural found", DOBslug ),
		'not_found_in_trash'=> __( "No $plural found in Trash", DOBslug ),
		'parent_item_colon'	=> _x( 'Parent', $singular, DOBslug ),
	);/*}}}*/

	$args = array(/*{{{*/
		'label'						=> $singular,	// Default: $post_type
		'labels'					=> $labels,
		'public'					=> true,	// effects [ publicly_queryable, show_ui, show_in_nav_menus, ]
		'show_ui'					=> true,	// effects [ show_in_menu[show_in_admin_bar] ]
		'menu_position'		=> 3,
		'menu_icon'				=> 'dashicons-testimonial',
		'capability_type'	=> 'post',
		'hierarchical'		=> true,
		'rewrite'					=> array('slug'=>'elect','with_front'=>true,'feeds'=>true,'pages'=>true),
		'query_var'				=> true,
		'supports'				=> array(
			'title', 'editor', 'excerpt', 'comments',
			'trackbacks', 'revisions', 'thumbnail',
			//'custom-fields', 'author', 'page-attributes',
		),
	);/*}}}*/

	register_post_type( 'elect', $args );
}

add_action( 'add_meta_boxes', 'dob_elect_add_meta_boxes' );
function dob_elect_add_meta_boxes() {
	$label_vm = '비밀 투표 방식'; //__( 'Secret Voting Method', DOBslug );
	add_meta_box( 'dob_elect_cmb_vote', $label_vm, 'dob_elect_cmb_vote_html', 'elect', 'normal', 'high' );
}

function dob_elect_cmb_vote_html( $post ) {

	wp_nonce_field( 'dob_meta_box_nonce', 'dob_elect_cmb_nonce' );

	$dob_elect_cmb_vote = get_post_meta( $post->ID, 'dob_elect_cmb_vote', true );
	$dob_vm_type = empty($dob_elect_cmb_vote['type']) ? 'updown': $dob_elect_cmb_vote['type'];
	$dob_vm_data = empty($dob_elect_cmb_vote['data']) ? array() : $dob_elect_cmb_vote['data'];
	$dob_vm_begin = empty($dob_elect_cmb_vote['begin']) ? date('Y-m-d 12:00:00') : $dob_elect_cmb_vote['begin'];
	$dob_vm_end = empty($dob_elect_cmb_vote['end']) ? date('Y-m-d 12:30:00') : $dob_elect_cmb_vote['end'];

	// labels /*{{{*/
	$label_updown	= '찬/반';		//__('Up/Down',DOBslug);
	$label_choice	= '다지선다';	//__('Multiple Choice',DOBslug);
	$label_plural	= '복수투표';	//__('Plural Vote',DOBslug);
	$label_begin	= '시작 일시';	//__('Begin DateTime',DOBslug);
	$label_end		= '종료 일시';	//__('End DateTime',DOBslug);
	$label_type		= __('Type');
	$label_value	= __('Select').' '.__('Value');
	$label_add		= __('Add');
	$label_del		= __('Delete');/*}}}*/

?>
<script language="javascript">
(function($) {/*{{{*/
  "use strict";
  $(function() {
		var dob_vm_type_last = 'updown';

		$('#td_dob_vm_type input').change( function() {
			var val = this.value;
			var ol = document.getElementById('ol_dob_vm_data');
			var div_add = document.getElementById('btn_dob_vm_add');
			if ( val == 'updown' ) {
				div_add.style.display = 'none';
				ol.innerHTML = '<li>Up</li><li>Down</li>';
			} else {	// choice, plural, 
				if ( dob_vm_type_last == 'updown' ) ol.innerHTML = '';
				div_add.style.display = 'inline';
			}
			dob_vm_type_last = val;
		});

		$('#btn_dob_vm_add').click( function() {
			var ol = $('#ol_dob_vm_data');
			if ( ol.find('li').length == <?php echo DOBmaxbit;?> ) {
				alert('Check Count (MAX:<?php echo DOBmaxbit;?>)');
				return;
			}
			var tpl = wp.template( 'dob-vm-option' );
			ol.append( tpl() );
		});
		
		$('#dob_vm_datetime input[type="text"]').datetimepicker({
			dateFormat : 'yy-mm-dd',
			timeFormat: 'HH:mm',
			stepMinute: 10,
			hourMin: 6
		});

  });
}(jQuery));/*}}}*/
</script>

<script type="text/html" id="tmpl-dob-vm-option">
<li>
	<input type="text" name="dob_vm_data[]" value="" maxlength="20" placeholder="max-length:20">
	<input type="button" value="<?php echo $label_del;?>" onclick="jQuery(this).parent().remove();">
</li>
</script>

<style type="text/css">/*{{{*/
#dob_vm table {
	margin: 0;
	width: 100%;
	border: 1px solid #dfdfdf;
	border-spacing: 0;
	background-color: #f9f9f9;
}
#dob_vm tr {
	vertical-align: middle;
}
#dob_vm th {
	background-color: #f1f1f1;
}
#dob_vm th, #dob_vm td {
	border: 1px solid #dfdfdf;
}
#dom_vm th.left, td.left {
	width: 120px;
}
#btn_dob_vm_add {
	vertical-align: middle;
}
ol input[type="text"] {
	width: 300px;
}
/*}}}*/</style>

	<div id="dob_vm"><!--{{{-->
		<div id="dob_vm_datetime">
			<label><?=$label_begin?>:</label> <input type="text" name="dob_vm_begin" value="<?php echo $dob_vm_begin;?>" >
			<br>
			<label><?=$label_end?>:</label> <input type="text" name="dob_vm_end" value="<?php echo $dob_vm_end;?>" >
		</div>
		<br>
		<table border=1>
			<thead>
				<tr>
					<th class="left"><?php echo $label_type;?></th>
					<th>
						<span><?php echo $label_value;?></span>
						<input type="button" id="btn_dob_vm_add" class="button" value="<?php echo $label_add;?> (MAX:<?php echo DOBmaxbit;?>)" style="display:<?php echo ($dob_vm_type=='updown')?'none':'inline';?>;">
					</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td id="td_dob_vm_type" class="left">
						<label><input type=radio name="dob_vm_type" value="updown" <?php echo ($dob_vm_type=='updown')?'CHECKED':'';?>><?php echo $label_updown;?></label> 
						<br><label><input type=radio name="dob_vm_type" value="choice" <?php echo ($dob_vm_type=='choice')?'CHECKED':'';?>><?php echo $label_choice;?></label> 
						<br><label><input type=radio name="dob_vm_type" value="plural" <?php echo ($dob_vm_type=='plural')?'CHECKED':'';?>><?php echo $label_plural;?></label> 
					</td>
					<td>
						<ol id="ol_dob_vm_data">
							<?php if ( $dob_vm_type=='updown' ) echo '<li>Up</li> <li>Down</li>';
							else foreach ( $dob_vm_data as $k=>$v ) { echo "
								<li><input type='text' name='dob_vm_data[$k]' value='$v' READONLY></li>";
							} ?>
						</ol>
					</td>
				</tr>
			</tbody>
		</table>
	</div><!--}}}-->

<?php
}

add_action( 'save_post', 'dob_elect_save_cmb_data' );
function dob_elect_save_cmb_data( $post_id ) {

	// Check Environments
	if ( empty($_POST['post_type']) || 'elect'!=$_POST['post_type'] 
		|| empty($_POST['dob_elect_cmb_nonce']) || ! wp_verify_nonce($_POST['dob_elect_cmb_nonce'],'dob_meta_box_nonce') 
		|| ! current_user_can('edit_post',$post_id)
		|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
		|| ! in_array( $_POST['dob_vm_type'], array('updown','choice','plural') )
	) return;

	//$my_data = sanitize_text_field( $_POST['dob_new_field'] ); // Sanitize user input.
#file_put_contents('/tmp/cpt2.php', print_r($_POST,true) );

	$dob_elect_cmb_vote = array( 'type' => $_POST['dob_vm_type'] );
	if ( isset($_POST['dob_vm_data']) && is_array($_POST['dob_vm_data']) ) {
		$dob_elect_cmb_vote['data'] = $_POST['dob_vm_data'];	// choice, plural
	}
	if ( !empty($_POST['dob_vm_begin']) ) {
		$dob_elect_cmb_vote['begin'] = date('Y-m-d H:i',strtotime($_POST['dob_vm_begin']));
	}
	if ( !empty($_POST['dob_vm_end']) 
		&& $_POST['dob_vm_begin'] < $_POST['dob_vm_end'] 
	) {
		$dob_elect_cmb_vote['end'] = date('Y-m-d H:i',strtotime($_POST['dob_vm_end']));
	}
#file_put_contents('/tmp/cpt3.php', print_r($dob_elect_cmb_vote,true) );

	update_post_meta( $post_id, 'dob_elect_cmb_vote', $dob_elect_cmb_vote );
}
