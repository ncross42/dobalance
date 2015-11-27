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

?>

<div class="wrap">

	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<div id="tabs" class="settings-tab">
		<ul>
				<li><a href="#tabs-1"><?php _e( 'Settings' ); ?></a></li>
				<li><a href="#tabs-2"><?php _e( 'Test Settings 2', $this->plugin_slug ); ?></a></li>
				<li><a href="#tabs-3"><?php _e( 'Import/Export', $this->plugin_slug ); ?></a></li>
		</ul>

		<div id="tabs-1" class="wrap">
<?php
$cmb = new_cmb2_box( array(
	'id' => $this->plugin_slug . '_options',
	'hookup' => false,
	'show_on' => array( 'key' => 'options-page', 'value' => array( $this->plugin_slug ), ),
	'show_names' => true,
) );
?>
			<div class="postbox">
				<h3 class="hndle"><span><?php _e( 'Category Settings', $this->plugin_slug ); ?></span></h3>
				<div class="inside">
<?php
$cmb->add_field( array(
	'name' => __( 'Hierarchy ROOT-SLUG', $this->plugin_slug ),
	'desc' => __( 'ROOT Hierarchy Category SLUG (requirement)', $this->plugin_slug ),
	'id' => $this->plugin_slug . '_root_hierarchy',
	'type' => 'text',
	'default' => 'hierarchy',
) );
$cmb->add_field( array(
	'name' => __( 'Subject ROOT-SLUG', $this->plugin_slug ),
	'desc' => __( 'ROOT Subject Category SLUG (requirement)', $this->plugin_slug ),
	'id' => $this->plugin_slug . '_root_subject',
	'type' => 'text_medium',
	'default' => 'subject',
	// 'repeatable' => true,
) );
?>
				</div>
			</div>

<?php
cmb2_metabox_form( $this->plugin_slug . '_options', $this->plugin_slug . '-settings' );
?>
			<!-- @TODO: Provide other markup for your options page here. -->
		</div>

		<div id="tabs-2" class="wrap">
<?php
$cmb = new_cmb2_box( array(
	'id' => $this->plugin_slug . '_options-second',
	'hookup' => false,
	'show_on' => array( 'key' => 'options-page', 'value' => array( $this->plugin_slug ), ),
	'show_names' => true,
) );
$cmb->add_field( array(
	'name' => __( 'Text', $this->plugin_slug ),
	'desc' => __( 'field description (optional)', $this->plugin_slug ),
	'id' => $this->plugin_slug . '_text-second',
	'type' => 'text',
	'default' => 'Default Text',
) );
$cmb->add_field( array(
	'name' => 'Testing Field Parameters',
	'id' => $this->plugin_slug . '_parameters',
	'type' => 'text',
	'before_row' => '<p>before_row_if_2</p>',// Callback
	'before' => '<p>Testing <b>"before"</b> parameter</p>',
	'before_field' => '<p>Testing <b>"before_field"</b> parameter</p>',
	'after_field' => '<p>Testing <b>"after_field"</b> parameter</p>',
	'after' => '<p>Testing <b>"after"</b> parameter</p>',
	'after_row' => '<p>Testing <b>"after_row"</b> parameter</p>',
) );
$cmb->add_field( array(
	'name' => __( 'Test Text Area', $this->plugin_slug ),
	'desc' => __( 'field description (optional)', $this->plugin_slug ),
	'id' => $this->plugin_slug . '_textarea',
	'type' => 'textarea',
) );
$cmb->add_field( array(
	'name' => __( 'Test Title Weeeee', $this->plugin_slug ),
	'desc' => __( 'This is a title description', $this->plugin_slug ),
	'id' => $this->plugin_slug . '_title',
	'type' => 'title',
) );
$cmb->add_field( array(
	'name' => __( 'Test Select', $this->plugin_slug ),
	'desc' => __( 'field description (optional)', $this->plugin_slug ),
	'id' => $this->plugin_slug . '_select',
	'type' => 'select',
	'show_option_none' => true,
	'options' => array(
		'standard' => __( 'Option One', $this->plugin_slug ),
		'custom' => __( 'Option Two', $this->plugin_slug ),
		'none' => __( 'Option Three', $this->plugin_slug ),
	),
) );
$cmb->add_field( array(
	'name' => __( 'Test Radio inline', $this->plugin_slug ),
	'desc' => __( 'field description (optional)', $this->plugin_slug ),
	'id' => $this->plugin_slug . '_radio_inline',
	'type' => 'radio_inline',
	'show_option_none' => 'No Selection',
	'options' => array(
		'standard' => __( 'Option One', $this->plugin_slug ),
		'custom' => __( 'Option Two', $this->plugin_slug ),
		'none' => __( 'Option Three', $this->plugin_slug ),
	),
) );
$cmb->add_field( array(
	'name' => __( 'Test Taxonomy Select', $this->plugin_slug ),
	'desc' => __( 'field description (optional)', $this->plugin_slug ),
	'id' => $this->plugin_slug . '_taxonomy_select',
	'type' => 'taxonomy_select',
	'taxonomy' => 'category',// Taxonomy Slug
) );
$cmb->add_field( array(
	'name' => __( 'Test Multi Checkbox', $this->plugin_slug ),
	'desc' => __( 'field description (optional)', $this->plugin_slug ),
	'id' => $this->plugin_slug . '_multicheckbox',
	'type' => 'multicheck',
	'options' => array(
		'check1' => __( 'Check One', $this->plugin_slug ),
		'check2' => __( 'Check Two', $this->plugin_slug ),
		'check3' => __( 'Check Three', $this->plugin_slug ),
	),
) );
$cmb->add_field( array(
	'name' => __( 'oEmbed', $this->plugin_slug ),
	'desc' => __( 'Enter a youtube, twitter, or instagram URL. Supports services listed at <a href="http://codex.wordpress.org/Embeds">http://codex.wordpress.org/Embeds</a>.', $this->plugin_slug ),
	'id' => $this->plugin_slug . '_embed',
	'type' => 'oembed',
) );


cmb2_metabox_form( $this->plugin_slug . '_options-second', $this->plugin_slug . '-settings-second' );
?>
			<!-- @TODO: Provide other markup for your options page here. -->
		</div>

		<div id="tabs-3" class="metabox-holder">
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
		</div> <!-- end of tabs-3 -->

	</div> <!-- end of tabs -->

	<div class="right-column-settings-page postbox">
		<h3 class="hndle"><span><?php _e( 'DoBalance.', $this->plugin_slug ); ?></span></h3>
		<div class="inside">
		<a href="https://github.com/ncross42/DoBalance"><img src="<?php echo plugins_url('assets/DoBalance_256x256.png', dirname(dirname(__FILE__)) );?>" alt=""></a>
		<a href="https://github.com/ncross42/DoBalance"><img src="<?php echo plugins_url('assets/DoBalance_Korea_379x256.png', dirname(dirname(__FILE__)) );?>" alt=""></a>
		</div>
	</div>

</div>
