<?php

/**
 * The WordPress Plugin Boilerplate.
 *
 * A foundation off of which to build well-documented WordPress plugins that
 * also follow WordPress Coding Standards and PHP best practices.
 *
 * @package   DoBalance
 * @author    HeeWon Lee <ncross42@gmail.com>
 * @license   AGPL-3.0+
 * @link      http://example.com
 * @copyright 2015 HeeWon Lee or Company Name
 *
 * @wordpress-plugin
 * Plugin Name:       DoBalance
 * Plugin URI:        @TODO
 * Description:       DoBalance is the wordpress plugin service for Balanced Direct Democracy
 * Version:           1.0.0
 * Author:            HeeWon Lee
 * Author URI:        ncross42@gmail.com
 * Text Domain:       dobalance
 * License:           AGPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * WordPress-Plugin-Boilerplate-Powered: v1.1.5
 */

// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
	die;
}

/**
 * ------------------------------------------------------------------------------
 * Public-Facing Functionality
 * ------------------------------------------------------------------------------
 */
require_once( plugin_dir_path( __FILE__ ) . 'includes/load_textdomain.php' );

/**
 * Load library for simple and fast creation of Taxonomy and Custom Post Type
 */

require_once( plugin_dir_path( __FILE__ ) . 'includes/Taxonomy_Core/Taxonomy_Core.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/CPT_Core/CPT_Core.php' );

/**
 * Load template system
 */

require_once( plugin_dir_path( __FILE__ ) . 'includes/template.php' );

/**
 * Load Widgets Helper
 */

require_once( plugin_dir_path( __FILE__ ) . 'includes/Widgets-Helper/wph-widget-class.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/widgets/sample.php' );

/**
 * Load Fake Page class
 */

require_once( plugin_dir_path( __FILE__ ) . 'includes/fake-page.php' );

new Fake_Page(
	array(
    'slug' => 'fake_slug',
    'post_title' => 'Fake Page Title',
    'post_content' => 'This is the fake page content'
	)
);

/**
 * Load Language wrapper function for WPML/Ceceppa Multilingua/Polylang
 */

require_once( plugin_dir_path( __FILE__ ) . 'includes/language.php' );

require_once( plugin_dir_path( __FILE__ ) . 'public/class-dobalance.php' );

/**
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 */

register_activation_hook( __FILE__, array( 'DoBalance', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DoBalance', 'deactivate' ) );

/**
 * - 9999 is used for load the plugin as last for resolve some
 *   problems when the plugin use API of other plugins, remove
 *   if you don' want this
 */

add_action( 'plugins_loaded', array( 'DoBalance', 'get_instance' ), 9999 );

/**
 * -----------------------------------------------------------------------------
 * Dashboard and Administrative Functionality
 * -----------------------------------------------------------------------------
*/

/**
 * If you want to include Ajax within the dashboard, change the following
 * conditional to:
 *
 * if ( is_admin() ) {
 *   ...
 * }
 *
 * The code below is intended to give the lightest footprint possible.
 */

if ( is_admin() && (!defined( 'DOING_AJAX' ) || !DOING_AJAX ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'admin/class-dobalance-admin.php' );
	add_action( 'plugins_loaded', array( 'DoBalance_Admin', 'get_instance' ) );
}


include_once( "includes/jstree_ajax.php" );	// operation
