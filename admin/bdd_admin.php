<?php

/**
 * Create the admin menu for this plugin
 */

add_action( 'admin_enqueue_scripts', 'bdd_admin_scripts' );
function bdd_admin_scripts( $hook ) {/*{{{*/
	//if( $hook !== 'toplevel_page_bdd' ) return;
	if( $hook !== 'settings_page_bdd' ) return;
	//wp_enqueue_style( 'bdd-admin-css', plugins_url('/bdd_admin.css',__FILE__) );
	wp_enqueue_style( 'bdd-admin-css', plugins_url('/jstree/themes/default/style.min.css',__FILE__) );
	wp_enqueue_script( 'bdd-jstree-js', plugins_url('/jstree/jstree.min.js',__FILE__), array( 'jquery' ), 1.0, true );
	wp_enqueue_script( 'bdd-admin-category-js', plugins_url('/bdd_admin_category.js',__FILE__), array( 'jquery' ), 1.0, true );
	// localize js-messages
	$locale = array( 
		'success'	=> 'Congrats! The terms are added successfully!',
		'failed'	=> 'Something went wrong... are you sure you have enough permission to add terms?',
		'notax'		=> 'Please select a taxonomy first!',
		'noterm'	=> 'Please input some terms!',
		'confirm' => 'Are you sure you want to add these terms?',
		/*'success'	=> __( 'Congrats! The terms are added successfully!', 'bdd_domain' ),
		'failed'	=> __( 'Something went wrong... are you sure you have enough permission to add terms?', 'bdd_domain' ),
		'notax'		=> __( 'Please select a taxonomy first!', 'bdd_domain' ),
		'noterm'	=> __( 'Please input some terms!', 'bdd_domain' ),
		'confirm' => __( 'Are you sure you want to add these terms?', 'bdd_domain' ),*/
		// 'ajax_url' => admin_url( 'admin-ajax.php' ), // just use 'ajaxurl'
	);
	wp_localize_script( 'bdd-admin-category-js', 'locale_strings', $locale );
	wp_enqueue_script( 'bdd-admin-jstree-js', plugins_url('bdd_admin_jstree.js',__FILE__), array( 'bdd-jstree-js' ), 1.0, true );
	wp_localize_script('bdd-admin-jstree-js', 'bdd_admin_jstree_strings', array( 'nonce' => wp_create_nonce('bdd_admin_jstree') ));
}/*}}}*/

add_action( 'admin_menu', 'bdd_admin_menu' );
function bdd_admin_menu() {/*{{{*/
	add_options_page( 'BDD Options', 'bdd', 'manage_options', 'bdd', 'bdd_options_page' );
}/*}}}*/

function bdd_options_page() {/*{{{*/
	$bdd_category_hierarchy = get_option('bdd_category_hierarchy');
	if ( empty($bdd_category_hierarchy) ) $bdd_category_hierarchy = 'hierarchy';
	$bdd_category_subject = get_option('bdd_category_subject');
	if ( empty($bdd_category_subject) ) $bdd_category_subject = 'subject';
?>
<div class="wrap"> <?php screen_icon(); ?>
	<h2>BDD Options</h2>
	<form action="options.php" method="post">
		<?php settings_fields('bdd_group'); ?>
		<?php @do_settings_fields('bdd_group'); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="bdd_dashboard_title">BDD Widget Title</label></th>
				<td>
				<input type="text" name="bdd_dashboard_title" value="<?php echo get_option('bdd_dashboard_title'); ?>" />
					<small>help text for this field<small>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bdd_number_of_items">BDD item count of list</label></th>
				<td>
					<input type="text" name="bdd_number_of_items" value="<?php echo get_option('bdd_number_of_items'); ?>" />
					<small>help text for this field<small>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bdd_category_hierarchy">ROOT hierarchy name</label></th>
				<td>
					<input type="text" name="bdd_category_hierarchy" value="<?php echo $bdd_category_hierarchy; ?>" />
					<small>root category name for hierarchy position (default: hierarchy)<small>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bdd_category_subject">ROOT subject name</label></th>
				<td>
					<input type="text" name="bdd_category_subject" value="<?php echo $bdd_category_subject; ?>" />
					<small>root category name for subject area (default: subject)<small>
				</td>
			</tr>
		</table>
		<?php @submit_button(); ?>
	</form>

	<label for="textarea_admin_category">
		<h2><?php _e( 'bulk category input (add only, overwite if duplicated)', 'bdd_td'); ?></h2>
	</label>
	<div class="container">
	<?php wp_nonce_field( 'bdd_admin_category_ajax', 'bdd_admin_category_ajax_security' ); ?>
		<textarea name="textarea_admin_category" id="textarea_admin_category" cols="50" rows="12"></textarea>
		<textarea cols="40" rows="12" READONLY>
Syntax :
indent(-)display_name::slug::description

Example : 
분야별 전문의원::subject
-일반::general::일반업무
--사무::office::사무직
--행정::government::행정직원
-기초::base
--농업::agriculture
--임업::forestry
		</textarea>
		<br/>
		<button type="button" id="submit_bulk_category" class="button button-primary button-large">Add now</button>
		<button type="button" id="reset_bulk_category" class="button button-large">Reset</button>
	</div>

<style>
html, body { background:#ebebeb; font-size:10px; font-family:Verdana; margin:0; padding:0; }
#container { min-width:320px; margin:0px auto 0 auto; background:white; border-radius:0px; padding:0px; overflow:hidden; }
#tree { float:left; min-width:319px; border-right:1px solid silver; overflow:auto; padding:0px 0; }
#data { margin-left:320px; }
#data textarea { margin:0; padding:0; height:100%; width:100%; border:0; background:white; display:block; line-height:18px; }
#data, #code { font: normal normal normal 12px/18px 'Consolas', monospace !important; }
</style>

	<h2>category management</h2>
	<div id="container" role="main">
	<?php 
	  $nonce_jstree = wp_create_nonce('bdd_admin_jstree');
	?>
		<div id="jstree_district" data-nonce="<?php echo $nonce_jstree; ?>" >
			<ul>
				<li>Root node 1</li>
				<li>Root node 2</li>
			</ul>
		</div> 
		<div id="data">
			<div class="content code" style="display:none;"><textarea id="code" readonly="readonly"></textarea></div>
			<div class="content folder" style="display:none;"></div>
			<div class="content image" style="display:none; position:relative;"><img src="" alt="" style="display:block; position:absolute; left:50%; top:50%; padding:0; max-height:90%; max-width:90%;" /></div>
			<div class="content default" style="text-align:center;">Select a node from the tree.</div>
		</div>
	</div>
</div>
<?php
}/*}}}*/

