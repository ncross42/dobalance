<?php

add_action( 'init', 'dob_register_cpt_offer' );
function dob_register_cpt_offer() {

	$singular = '공개발의';	// 'Offer';
	$plural = '공개발의';	// 'Offers';

	$labels = array(/*{{{*/
		'name'							=> __( $plural, DOBslug ),
		'singular_name'			=> __( $singular, DOBslug ),	// value of 'name'
		'menu_name'					=> __( $plural ),							// value of 'name'
		'name_admin_bar'		=> __( $singular ),							// value of 'singular_name'
		'all_items'					=> __( 'All Offers' ),				// value of 'name'
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
		'rewrite'					=> array('slug'=>'offer','with_front'=>true,'feeds'=>true,'pages'=>true),
		'query_var'				=> true,
		'supports'				=> array(
			'title', 'editor', 'excerpt', 'comments',
			'trackbacks', 'revisions', 'thumbnail',
			//'custom-fields', 'author', 'page-attributes',
		),
	);/*}}}*/

	register_post_type( 'offer', $args );
}

add_action( 'add_meta_boxes', 'dob_offer_add_meta_boxes' );
function dob_offer_add_meta_boxes() {
	$label_pros = '장점'; //__( 'Pros', DOBslug );
	add_meta_box( 'dob_offer_cmb_pros', __( 'Pros', DOBslug ), 'dob_offer_cmb_pros_html', 'offer', 'normal', 'high' );
	$label_cons = '단점'; //__( 'Cons', DOBslug );
	add_meta_box( 'dob_offer_cmb_cons', $label_cons, 'dob_offer_cmb_cons_html', 'offer', 'normal', 'high' );
	$label_vm = '투표 방식'; //__( 'Voting Method', DOBslug );
	add_meta_box( 'dob_offer_cmb_vote', $label_vm, 'dob_offer_cmb_vote_html', 'offer', 'normal', 'high' );
}

function dob_offer_cmb_text_area ( $post_id, $name ) {/*{{{*/
	$text = __($name, DOBslug );
	$content = get_post_meta($post_id, $name, true);
	return <<<HTML
		<!--label for="$name">$text</label><br /--><textarea style="width:95%;" ROWS=5 name="$name">$content</textarea>
HTML;
}/*}}}*/
// echo '<input type="text" name="new_field" value="'.esc_attr($value).'" size="25" />';
function dob_offer_cmb_pros_html($post) { echo dob_offer_cmb_text_area($post->ID,'dob_offer_cmb_pros'); }
function dob_offer_cmb_cons_html($post) { echo dob_offer_cmb_text_area($post->ID,'dob_offer_cmb_cons'); }

function dob_offer_cmb_vote_html( $post ) {

	wp_nonce_field( 'dob_meta_box_nonce', 'dob_offer_cmb_nonce' );

	$dob_offer_cmb_vote = get_post_meta( $post->ID, 'dob_offer_cmb_vote', true );
	$dob_vm_type = empty($dob_offer_cmb_vote['type']) ? 'updown': $dob_offer_cmb_vote['type'];
	$dob_vm_data = empty($dob_offer_cmb_vote['data']) ? array() : $dob_offer_cmb_vote['data'];

	// labels /*{{{*/
	$label_updown	= '찬/반';		//__('Up/Down',DOBslug);
	$label_choice	= '다지선다';	//__('Multiple Choice',DOBslug);
	$label_plural	= '복수투표';	//__('Plural Vote',DOBslug);
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
				alert('Check Count (MAX:<?php echo DOBmaxbit;?> )');
				return;
			}
			var tpl = wp.template( 'dob-vm-option' );
			ol.append( tpl() );
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

add_action( 'save_post', 'dob_offer_save_cmb_data' );
function dob_offer_save_cmb_data( $post_id ) {

	// Check Environments
	if ( empty($_POST['post_type']) || 'offer'!=$_POST['post_type'] 
		|| empty($_POST['dob_offer_cmb_nonce']) || ! wp_verify_nonce($_POST['dob_offer_cmb_nonce'],'dob_meta_box_nonce') 
		|| ! current_user_can('edit_post',$post_id)
		|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
		|| ! in_array( $_POST['dob_vm_type'], array('updown','choice','plural') )
	) return;

	//$my_data = sanitize_text_field( $_POST['dob_new_field'] ); // Sanitize user input.
#file_put_contents('/tmp/cpt2.php', print_r($_POST,true) );

	// https://codex.wordpress.org/Function_Reference/update_post_meta
	// Update the meta field in the database.
	update_post_meta( $post_id, 'dob_offer_cmb_pros', $_POST['dob_offer_cmb_pros'] );
	update_post_meta( $post_id, 'dob_offer_cmb_cons', $_POST['dob_offer_cmb_cons'] );

	$dob_offer_cmb_vote = array( 'type' => $_POST['dob_vm_type'] );
	if ( isset($_POST['dob_vm_data']) && is_array($_POST['dob_vm_data']) ) {
		$dob_offer_cmb_vote['data'] = $_POST['dob_vm_data'];	// choice, plural
	}
	update_post_meta( $post_id, 'dob_offer_cmb_vote', $dob_offer_cmb_vote );
}
