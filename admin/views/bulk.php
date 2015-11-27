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
/*{{{*/ /*
$cmb = new_cmb2_box( array(
	'id' => $this->plugin_slug . '_bulk',
	'hookup' => false,
	'show_on' => array( 'key' => 'options-page', 'value' => array( $this->plugin_slug ), ),
	'show_names' => true,
) );
$cmb->add_field( array(
	'name' => __( 'Category Terms', $this->plugin_slug ),
	'desc' => __( 'add only, overwite if duplicated', $this->plugin_slug ),
	'id' => $this->plugin_slug . '_bulk_category',
	'type' => 'textarea',
	'default' => 'hierarchy',
) );
$form = cmb2_get_metabox_form( $this->plugin_slug . '_bulk', $this->plugin_slug . '_bulk' );
 */ /*}}}*/

$message = '[message] ';

#$message = '<pre>'.print_r($_REQUEST,true).'</pre>';
#$message .= wp_verify_nonce( $_POST['dobalance_admin_bulk'], 'dobalance_admin_bulk' ) ? 'yes' : 'no';
#$message .= "\n<br>";

# add bulk process
if ( is_array($_POST) 
	&& isset( $_POST['textarea_terms'] ) 
	&& isset( $_POST['dobalance_admin_bulk'] ) 
	&& wp_verify_nonce( $_POST['dobalance_admin_bulk'], 'dobalance_admin_bulk' ) 
) {

	include_once __DIR__."/../../includes/jstree_class.php";
	$jstree = new jsTree();

	$added = $updated = $skipped = 0;
	$current_lvl = 0;
	$lvl_ids = array();
	$lines = split( "\n", $_REQUEST['textarea_terms'] );

	foreach( $lines as $line ) {
		$a_line = trim( preg_replace( "![\r\n]+!", '', $line ) );
		if ( empty($a_line) ) continue;

		$splits = preg_split( "/^\-+/", $a_line );	// split indent and category data
		$args = array();
		if( isset( $splits[1] ) ) {
			$sp_line = $splits[1];
			preg_match( "/^\-+/", $line, $indentors );
			$level = strlen( $indentors[0] );
			$args['parent'] = $parent = $lvl_ids[$level - 1];
			if( $level - 1 ===  $current_lvl ) {
				$current_lvl++;
			} else {
				$current_lvl = $level;
			}
		} else {
			$sp_line = $line;
			$current_lvl = 0;
		}                

		$description = '';
		if ( strpos( $sp_line, '//' ) ) {
			$arrChunk = explode( '//', $sp_line );
			$args['name'] = $name = trim($arrChunk[0]);
			$args['slug'] = $slug = trim($arrChunk[1]);
			if ( 3 == count($arrChunk) ) {
				$args['description'] = $description = trim($arrChunk[2]);
			}
		} else {
			++$skipped;
			continue;
		}

		// execute by checking SLUG duplication 
		$wp_term = get_term_by('slug',$slug,'category');
		if ( $wp_term === false ) {
			$lvl_ids[$current_lvl] = $jstree->mk( $parent, 9999, $args ); // wp_insert_term( $name, 'category', $args );
			++$added;
		} else if ( $wp_term->name != $name 
			|| $wp_term->description != $description 
		) {
			$ret = wp_update_term($wp_term->term_id, 'category', $args);
			$lvl_ids[$current_lvl] = $ret['term_taxonomy_id'];
			++$updated;
		} else {
			$lvl_ids[$current_lvl] = $wp_term->term_taxonomy_id;
			++$skipped;
		}
	}
	$message .= " Added: $added // Updated: $updated // Skipped: $skipped";
} else {
	$message .= 'ADD ONLY, Overwite if duplicated';
}

?>

<div class="wrap">

	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<div id="tabs" class="settings-tab">
		<ul>
				<li><a href="#tabs-1"><?php _e( 'Settings' ); ?></a></li>
				<li><a href="#tabs-2"><?php _e( 'Import/Export', $this->plugin_slug ); ?></a></li>
		</ul>

		<div id="tabs-1" class="wrap">
			<div class="postbox">
				<h3 class="hndle"><span><?php _e( 'Add Bulk Category', $this->plugin_slug ); ?></span></h3>
				<div class="inside">
					<span style="background-color:#F2DEDE"><?php echo $message; ?></span>
					<form method="post">
					<div class="container">
					<?php wp_nonce_field( 'dobalance_admin_bulk', 'dobalance_admin_bulk' ); ?>
						<textarea name="textarea_terms" id="textarea_terms" cols="50" rows="12"></textarea>
						<textarea cols="40" rows="12" READONLY>
Syntax :
indent(-)category_name//slug//description

Example :
분야별 전문의원//subject
-일반//general//일반업무
--사무//office//사무직
--행정//government//행정직원
-기초//base
--농업//agriculture
--임업//forestry
						</textarea>
						<br/>
						<?php submit_button(); ?>
						<!--button type="reset" id="reset_bulk_category" class="button button-large">Reset</button-->
					</form>
					</div>

					<?php //echo $form; ?>
				</div>
			</div>
			<!-- @TODO: Provide other markup for your options page here. -->
		</div>

		<div id="tabs-2" class="metabox-holder">
			<div class="postbox">
				<h3 class="hndle"><span><?php _e( 'Export Settings', $this->plugin_slug ); ?></span></h3>
				<div class="inside">
					<p><?php _e( 'Export the plugin settings for this site as a .json file. This allows you to easily import the configuration into another site.', $this->plugin_slug ); ?></p>
						<form method="post">
							<p><input type="hidden" name="pn_action" value="export_settings" /></p>
							<p>
									<?php wp_nonce_field( 'pn_export_nonce', 'pn_export_nonce' ); ?>
									<?php submit_button( __( 'Export' ), 'secondary', 'submit', false ); ?>
							</p>
						</form>
				</div>
			</div>
			<div class="postbox">
				<h3 class="hndle"><span><?php _e( 'Import Settings', $this->plugin_slug ); ?></span></h3>
				<div class="inside">
					<p><?php _e( 'Import the plugin settings from a .json file. This file can be obtained by exporting the settings on another site using the form above.', $this->plugin_slug ); ?></p>
					<form method="post" enctype="multipart/form-data">
						<p> <input type="file" name="pn_import_file"/> </p>
						<p>
							<input type="hidden" name="pn_action" value="import_settings" />
							<?php wp_nonce_field( 'pn_import_nonce', 'pn_import_nonce' ); ?>
							<?php submit_button( __( 'Import' ), 'secondary', 'submit', false ); ?>
						</p>
					</form>
				</div>
			</div>
		</div> <!-- end of tabs-2 -->

	</div> <!-- end of tabs -->

</div>
