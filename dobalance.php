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
// define 
define( 'DOBver', '0.0.1');				// 'DOBALANCE_VERSION'
define( 'DOBslug', 'dobalance' );	// 'DOBALANCE_SLUG'
define( 'DOBname', 'DoBalance' );	// 'DOBALANCE_NAME'
define( 'DOBtable', $wpdb->prefix.'dob_' );
define( 'DOBpath', plugin_dir_path(__FILE__) );
define( 'DOBurl', plugin_dir_url(__FILE__) );
define( 'DOBmaxbit', 7 );

if(!session_id()) session_start();
ini_set('session.gc_maxlifetime', 7200);
session_set_cookie_params(7200);

$global_real_ip = dob_get_real_ip();
function dob_get_real_ip() {/*{{{*/
	if ( empty($_SERVER['REQUEST_URI']) ) {
		return '';
	} elseif (getenv('HTTP_CLIENT_IP')) {
		$ip = getenv('HTTP_CLIENT_IP');
	} elseif (getenv('HTTP_X_FORWARDED_FOR')) {
		$ip = getenv('HTTP_X_FORWARDED_FOR');
	} elseif (getenv('HTTP_X_FORWARDED')) {
		$ip = getenv('HTTP_X_FORWARDED');
	} elseif (getenv('HTTP_FORWARDED_FOR')) {
		$ip = getenv('HTTP_FORWARDED_FOR');
	} elseif (getenv('HTTP_FORWARDED')) {
		$ip = getenv('HTTP_FORWARDED');
	} else {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	return $ip;
}/*}}}*/

function dob_set_remote_addr($user_login){/*{{{*/
	$user_obj = get_user_by('login', $user_login );
	$_SESSION['user_id'] = $user_obj->ID;
	$_SESSION['user_login'] = $user_login;
	$_SESSION['LOGIN_IP'] = dob_get_real_ip();
	//wp_redirect("http://www.google.com"); //exit;
}/*}}}*/
add_action('wp_login', 'dob_set_remote_addr');
function dob_destroy_session($user_login){ session_destroy(); }
add_action('wp_logout', 'dob_destroy_session');

date_default_timezone_set('Asia/Seoul');

/**
 * ------------------------------------------------------------------------------
 * Public-Facing Functionality
 * ------------------------------------------------------------------------------
 */
require_once( DOBpath . 'includes/load_textdomain.php' );

/**
 * Load library for simple and fast creation of Taxonomy and Custom Post Type
 */

//require_once( DOBpath . 'includes/Taxonomy_Core/Taxonomy_Core.php' );
//require_once( DOBpath . 'includes/CPT_Core/CPT_Core.php' );

/**
 * Load template system
 */

require_once( DOBpath . 'includes/template.php' );

/**
 * Load Widgets Helper
 */

require_once( DOBpath . 'includes/Widgets-Helper/wph-widget-class.php' );
require_once( DOBpath . 'includes/widgets/sample.php' );

/**
 * Load Language wrapper function for WPML/Ceceppa Multilingua/Polylang
 */

require_once( DOBpath . 'includes/language.php' );

require_once( DOBpath . 'public/class-dobalance.php' );

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

if ( defined( 'DOING_AJAX' ) && !empty(DOING_AJAX) ) {
	require_once( DOBpath . 'public/dob_ajax.php' );
	require_once( 'includes/jstree.ajax.php' );	// operation
	require_once( 'admin/ajax/jstree_user.php' );	// operation
} else if ( is_admin() ) {
	require_once( DOBpath . 'admin/main.php' );
	#require_once( DOBpath . 'admin/class-dobalance-admin.php' );
	#add_action( 'plugins_loaded', array( 'DoBalance_Admin', 'get_instance' ) );
} else {
	#require_once( DOBpath . 'public/dob_site.php' );
	require_once( DOBpath . 'public/dob_elect.php' );
	require_once( DOBpath . 'public/dob_vote.php' );

	if ( '1' == get_option('dob_use_upin') ) {
		require_once( DOBpath . 'includes/upin_kcb.php' );
	}
}
require_once( DOBpath . 'public/dob_widgets.php' );

require_once( DOBpath.'includes/custom_admin_bar.php' );
require_once( DOBpath.'includes/custom_taxonomy.php' );
require_once( DOBpath.'includes/cpt_offer.php' );
require_once( DOBpath.'includes/cpt_elect.php' );

