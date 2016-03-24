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

<style type="text/css">
li[taxonomy="user"] { color:red; }
li[taxonomy="user"][chl="0"] { color:blue; }
li[taxonomy="user"]:nth-child(n+2) > i { width:0px !important; }
</style>

<div class="wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
  <div id="container" role="main">

		<div class="postbox">
			<h3><span><?php _e( 'jsTree User Hieararchy', DOBslug ); ?></span></h3>
			<div class="inside">
				<div id="jstree_user">
					<ul>
						<li>Root node 1</li>
						<li>Root node 2</li>
					</ul>
				</div>
			</div>
		</div>

	</div>
</div>
