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
$message = '[message] ';

#$message = '<pre>'.print_r($_REQUEST,true).'</pre>';
#$message .= wp_verify_nonce( $_POST['dobalance_admin_bulk'], 'dobalance_admin_bulk' ) ? 'yes' : 'no';
#$message .= "\n<br>";

global $wpdb;

$sql = "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
	WHERE TABLE_NAME='{$wpdb->prefix}dob_user_category' AND COLUMN_NAME='taxonomy'";
$COLUMN_TYPE = $wpdb->get_var($sql);
eval( '$taxonomy_db = '.str_replace('enum','array',$COLUMN_TYPE).';' );

$taxonomy = isset($_REQUEST['taxonomy']) 
	&& in_array( $_REQUEST['taxonomy'], $taxonomy_db ) 
	? $_REQUEST['taxonomy'] : 'category';

# add bulk process
if ( is_array($_POST) 
	&& isset( $_POST['textarea_terms'] ) 
	&& isset( $_POST['dobalance_admin_bulk'] ) 
	&& wp_verify_nonce( $_POST['dobalance_admin_bulk'], 'dobalance_admin_bulk' ) 
) {

	require_once( DOBpath.'includes/jstree.class.php' );
	$jstree = new jsTree(array('taxonomy'=>$taxonomy));

	$added = $updated = $skipped = 0;
	$current_lvl = 0;
	$lvl_ids = array(0=>0);
	$lines = explode( "\n", $_REQUEST['textarea_terms'] );

	foreach( $lines as $line ) {
		$a_line = trim( preg_replace( "![\r\n]+!", '', $line ) );
		if ( empty($a_line) ) continue;

		$splits = preg_split( "/^\-+/", $a_line );	// split indent and category data
		$args = array('taxonomy'=>$taxonomy);
		$parent = 0;
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
		$wp_term = get_term_by('slug',$slug,$taxonomy);
		if ( $wp_term === false ) {
			$lvl_ids[$current_lvl] = $jstree->mk( $parent, 9999, $args ); // wp_insert_term( $name, $taxonomy, $args );
			++$added;
		} else if ( $wp_term->name != $name 
			|| $wp_term->description != $description 
		) {
			$ret = wp_update_term($wp_term->term_id, $taxonomy, $args);
			$lvl_ids[$current_lvl] = $ret['term_taxonomy_id'];
			++$updated;
		} else {
			$lvl_ids[$current_lvl] = $wp_term->term_taxonomy_id;
			++$skipped;
		}
	}
	$last_index = $jstree->rebuild_mptt_index();
	$message .= " Added: $added // Updated: $updated // Skipped: $skipped // Last_Index: $last_index";
} else {
	$message .= 'ADD ONLY, Overwite if duplicated';
}

?>

<div class="wrap">

	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<div id="tabs" class="settings-tab">
		<ul>
				<li><a href="#tabs-1"><?php _e( 'Settings' ); ?></a></li>
				<li><a href="#tabs-2"><?php _e( 'Import/Export', DOBslug ); ?></a></li>
		</ul>

		<div id="tabs-1" class="wrap">
			<div class="postbox">
				<h3 class="hndle"><span><?php _e( 'Bulk MPTT Category', DOBslug ); ?></span></h3>
				<div class="inside">
					<form method="post">
					<div class="container">
						<b> Select Taxonomy : </b>
						<label><input type="radio" name="taxonomy" value="category" <?php echo $taxonomy=='category' ? 'CHECKED' : ''; ?> >Category(일반)</label>
						<label><input type="radio" name="taxonomy" value="hierarchy" <?php echo $taxonomy=='hierarchy' ? 'CHECKED' : ''; ?> >Hierarchy(계층별)</label>
						<label><input type="radio" name="taxonomy" value="topic" <?php echo $taxonomy=='topic' ? 'CHECKED' : ''; ?> >Topic(주제별)</label>
					</div>
					<br>
					<span style="background-color:#F2DEDE"><?php echo $message; ?></span>
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
				<h3 class="hndle"><span><?php _e( 'Export Settings', DOBslug ); ?></span></h3>
				<div class="inside">
					<p><?php _e( 'Export the plugin settings for this site as a .json file. This allows you to easily import the configuration into another site.', DOBslug ); ?></p>
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
				<h3 class="hndle"><span><?php _e( 'Import Settings', DOBslug ); ?></span></h3>
				<div class="inside">
					<p><?php _e( 'Import the plugin settings from a .json file. This file can be obtained by exporting the settings on another site using the form above.', DOBslug ); ?></p>
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
