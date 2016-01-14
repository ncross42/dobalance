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

$message = '<b>[FORMAT]</b> category-NAME (native language) <span style="color:red"><b>//</b></span> category-SLUG (<b>only english</b>)';

$nonce_jstree = wp_create_nonce('dobalance_admin_jstree');
?>

<div class="wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
  <div id="container" role="main">

		<div class="postbox">
			<h3 class="hndle"><span><?php _e( 'jsTree Category', DOBslug ); ?></span></h3>
			<div class="inside">
				<span style="background-color:#F2DEDE"><?php echo $message; ?></span>
				<div id="jstree_category" data-nonce="<?php echo $nonce_jstree; ?>" >
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

	</div>
</div>
