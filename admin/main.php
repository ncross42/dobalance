<?php
/**
 * DoBalance Admin
 *
 * @package   DoBalance_Admin
 * @author    HeeWon Lee <ncross42@gmail.com>
 * @license   AGPL-3.0
 * @link      http://www.dobalance.net
 * @copyright 2016 DoBalance
 */

define( 'DOBpathAdmin', plugin_dir_path(__FILE__) );
define( 'DOBurlAdmin', plugins_url('/', __FILE__) );

$dob_screen_hook = array();

/*************************
 * Init Option variables *
 *************************/
add_action( 'admin_init', 'dob_admin_init' );
//add_action( 'plugins_loaded', 'dob_admin_init' );
function dob_admin_init() {
	global $current_user;
	//if( ! is_super_admin() ) return;	// only for super admins
	if( empty($current_user->ID) ) return;

	// register_setting
	register_setting( DOBslug.'_options', 'dob_use_upin' );
	register_setting( DOBslug.'_options', 'dob_upin_type' );
	register_setting( DOBslug.'_options', 'dob_upin_cpid' );
	register_setting( DOBslug.'_options', 'dob_upin_keyfile' );
	register_setting( DOBslug.'_options', 'dob_upin_logpath' );
}

/*************************
 * Load admin CSS and JS *
 *************************/
add_action( 'admin_enqueue_scripts', 'dob_admin_enqueue_scripts' );
// Load admin style in dashboard for the At glance widget
//add_action( 'admin_head-index.php', 'dob_admin_enqueue_css' );

function dob_admin_enqueue_scripts() {/*{{{*/
	global $dob_screen_hook;
	if ( empty($dob_screen_hook) ) {
		return;
	}
	$screen = get_current_screen();
	if ( in_array($screen->id,$dob_screen_hook) ) {
		if ( defined('WP_DEBUG') && WP_DEBUG==true ) { // debug mode
			wp_enqueue_script( DOBslug.'-admin-script', DOBurlAdmin.'assets/js/admin.js',
				array( 'jquery', 'jquery-ui-tabs' ), DOBver );
		} else { // optimized 
			wp_enqueue_script( DOBslug.'-admin-script', DOBurlAdmin.'assets/admin.min.js',
				array( 'jquery', 'jquery-ui-tabs' ), DOBver );
		}
		// css
		dob_admin_enqueue_css();
	}
	global $wp_scripts; 
	wp_enqueue_script('jquery-ui-datepicker');
	wp_enqueue_script('jquery-ui-slider');
	wp_enqueue_script( DOBslug.'-timepicker-js', DOBurlAdmin.'assets/jquery-ui-timepicker-addon.min.js', array( 'jquery','jquery-ui-datepicker','jquery-ui-slider' ) );
	$jquery_ui_ver = $wp_scripts->registered['jquery-ui-core']->ver;
	wp_enqueue_style('jquery-ui-css', "http://ajax.googleapis.com/ajax/libs/jqueryui/$jquery_ui_ver/themes/smoothness/jquery-ui.min.css");	// smoothness, ui-lightness
	wp_enqueue_style( DOBslug.'-timepicker-css', DOBurlAdmin.'assets/jquery-ui-timepicker-addon.min.css', array( 'jquery-ui-css' ) );
}/*}}}*/

function dob_admin_enqueue_css() {/*{{{*/
	if ( defined('WP_DEBUG') && WP_DEBUG==true ) { // debug mode
		wp_enqueue_style( DOBslug.'-admin-styles', DOBurlAdmin.'assets/css/admin.css', 
			array( 'dashicons' ), DOBver );
	} else { // optimized 
		wp_enqueue_style( DOBslug.'-admin-styles', DOBurlAdmin.'assets/admin.min.css', 
			array( 'dashicons' ), DOBver );
	}
}/*}}}*/

/***************************
 * add user profile fields *
 ***************************/
require_once( DOBpathAdmin . 'pages/user_profile.php' );

/******************
 * add menu pages *
 ******************/
add_action( 'admin_menu', 'dob_admin_add_menu' );
function dob_admin_add_menu() {/*{{{*/
	/**
	 * Add a settings page for this plugin to the Settings menu.
	 * Administration Menus: http://codex.wordpress.org/Administration_Menus
	 *
	 * add_menu_page('Page title', 'Top-level menu title', 'manage_options', 'my-top-level-handle', 'my_magic_function');
	 * add_submenu_page( 'my-top-level-handle', 'Page title', 'Sub-menu title', 'manage_options', 'my-submenu-handle', 'my_magic_function');
	 *
	 * 'manage_options' : http://codex.wordpress.org/Roles_and_Capabilities
	 */
	global $dob_screen_hook, $current_user;

	// MAIN menu
	$dob_screen_hook[] = add_menu_page( 
		__('DoBalance',DOBslug), __('DoBalance',DOBslug), 'read'
		, DOBslug, 'dob_admin_page', 'dashicons-hammer', 3
	);

	// SUB menu : jsTree favorite (default)
	$dob_screen_hook[] = add_submenu_page( 
		DOBslug, __('DoBalance',DOBslug), '즐겨찾기',//__('jsTree favorite',DOBslug),
		'read', DOBslug.'_jstree_favorite', 'dob_admin_jstree_favorite'
	);

	// roles : contributor
	$dob_screen_hook[] = add_submenu_page(	// SUB menu : cart
		DOBslug, __('DoBalance',DOBslug), '투표바구니',//__('my voting cart',DOBslug),
		'edit_posts', DOBslug.'_cart', 'dob_admin_cart'
	);

	// roles : author
	// SUB menu : upin
	$dob_screen_hook[] = add_submenu_page( 
		DOBslug, __('DoBalance',DOBslug), __('config UPIN',DOBslug),
		'manage_options', DOBslug.'_upin', 'dob_admin_upin'
	);

	// SUB menu : bulk
	$dob_screen_hook[] = add_submenu_page( 
		DOBslug, __('DoBalance',DOBslug), __('bulk mptt category',DOBslug),
		'manage_options', DOBslug.'_bulk', 'dob_admin_bulk'
	);

	// SUB menu : jsTree category
	$dob_screen_hook[] = add_submenu_page( 
		DOBslug, __('DoBalance',DOBslug), 'jsTree '.__('category',DOBslug),
		'manage_options', DOBslug.'_jstree_category', 'dob_admin_jstree_category'
	);

	/* SUB menu : jsTree user
	$dob_screen_hook[] = add_submenu_page( 
		DOBslug, __('DoBalance',DOBslug), 'jsTree '.__('user',DOBslug),
		'manage_options', DOBslug.'_jstree_user', 'dob_admin_jstree_user'
	);*/

}/*}}}*/

function dob_admin_page() {
	dob_admin_multiple_select_scripts();
	require_once( DOBpathAdmin.'pages/admin.php' );
}
function dob_admin_cart() {
	require_once( DOBpathAdmin.'pages/cart.php' );
}
function dob_admin_upin() {
	require_once( DOBpathAdmin.'pages/upin.php' );
}
function dob_admin_bulk() {
	require_once( DOBpathAdmin.'pages/bulk.php' );
}
function dob_admin_jstree_favorite() {/*{{{*/
	dob_admin_jstree_scripts();
	wp_enqueue_script( DOBslug.'-admin-jstree-favorite-js', DOBurlAdmin.'assets/js/jstree_favorite.js', array( DOBslug.'-jstree-js' ), DOBver, true );
	require_once( DOBpathAdmin.'pages/jstree_favorite.php' );
}/*}}}*/
function dob_admin_jstree_category() {/*{{{*/
	dob_admin_jstree_scripts();
	wp_enqueue_script( DOBslug.'-admin-jstree-category-js', DOBurlAdmin.'assets/js/jstree_category.js', array( DOBslug.'-jstree-js' ), DOBver, true );
	require_once( DOBpathAdmin.'pages/jstree_category.php' );
}/*}}}*/
function dob_admin_jstree_user() {/*{{{*/
	dob_admin_jstree_scripts();
	wp_enqueue_script( DOBslug.'-admin-jstree-user-js', DOBurlAdmin.'assets/js/jstree_user.js', array( DOBslug.'-jstree-js' ), DOBver, true );
	require_once( DOBpathAdmin.'pages/jstree_user.php' );
}/*}}}*/

function dob_admin_jstree_scripts() {/*{{{*/
	wp_enqueue_script( DOBslug.'-jstree-js', DOBurlAdmin.'../assets/jstree/jstree.min.js', array( 'jquery' ), DOBver, true );
	wp_enqueue_style( DOBslug.'-jstree-css', DOBurlAdmin.'../assets/jstree/themes/default/style.min.css' );
	$locale = array( // localize js-messages
		'success' => __( 'Congrats! The terms are added successfully!', DOBslug ),
		'failed'  => __( 'Something went wrong... are you sure you have enough permission to add terms?', DOBslug ),
		'notax'   => __( 'Please select a taxonomy first!', DOBslug ),
		'noterm'  => __( 'Please input some terms!', DOBslug ),
		'confirm' => __( 'Are you sure you want to add these terms?', DOBslug ),
		//'ajax_url' => admin_url( 'admin-ajax.php' ), // just use 'ajaxurl'
		'nonce' => wp_create_nonce('dob_admin_jstree_ajax'.DOBver),
	);
	wp_localize_script( DOBslug.'-jstree-js', 'locale_strings', $locale );
}/*}}}*/

function dob_admin_multiple_select_scripts() {/*{{{*/
	wp_enqueue_script( DOBslug.'-multiple-select-js', DOBurlAdmin.'assets/multiple-select.js', array('jquery'), DOBver, true );
	wp_enqueue_style( DOBslug.'-multiple-select-css', DOBurlAdmin.'assets/multiple-select.css', false );
}/*}}}*/

