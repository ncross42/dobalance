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

$dob_screen_hook = array();

/*************************
 * Init Option variables *
 *************************/
add_action( 'admin_init', 'dob_admin_init' );
//add_action( 'plugins_loaded', 'dob_admin_init' );
function dob_admin_init() {
	// only for super admins
	if( ! is_super_admin() ) return;
	// register_setting
	register_setting( DOBslug.'_options', 'root_hierarchy' );
	register_setting( DOBslug.'_options', 'root_subject' );
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
		$plugins_url = plugins_url('/', __FILE__);
		if ( defined('WP_DEBUG') && WP_DEBUG==true ) { // debug mode
			wp_enqueue_script( DOBslug.'-admin-script', $plugins_url.'assets/js/admin.js',
				array( 'jquery', 'jquery-ui-tabs' ), DOBver );
		} else { // optimized 
			wp_enqueue_script( DOBslug.'-admin-script', $plugins_url.'assets/admin.min.js',
				array( 'jquery', 'jquery-ui-tabs' ), DOBver );
		}
		// css
		dob_admin_enqueue_css();
	}
}/*}}}*/

function dob_admin_enqueue_css() {/*{{{*/
	$plugins_url = plugins_url('/',__FILE__);
	if ( defined('WP_DEBUG') && WP_DEBUG==true ) { // debug mode
		wp_enqueue_style( DOBslug.'-admin-styles', $plugins_url.'assets/css/admin.css', 
			array( 'dashicons' ), DOBver );
	} else { // optimized 
		wp_enqueue_style( DOBslug.'-admin-styles', $plugins_url.'assets/admin.min.css', 
			array( 'dashicons' ), DOBver );
	}
}/*}}}*/

/***************************
 * add user profile fields *
 ***************************/
require_once( DOBpathAdmin . 'pages/user_hierarchy.php' );

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
	global $dob_screen_hook;

	/*$dob_screen_hook[] = add_options_page(
		__( 'aa Page Title', DOBslug ), DOBname
		, 'manage_options', DOBslug, 'dob_admin_page'
	);*/
	// MAIN menu
	$dob_screen_hook[] = add_menu_page( 
		__('DoBalance',DOBslug), __('DoBalance',DOBslug), 'manage_options'
		, DOBslug, 'dob_admin_page', 'dashicons-hammer', 3
	);
	// SUB menu 1 : bulk
	$dob_screen_hook[] = add_submenu_page( 
		DOBslug, __('DoBalance',DOBslug), __('bulk mptt category',DOBslug),
		'manage_options', DOBslug.'_bulk', 'dob_admin_bulk'
	);

	// SUB menu 2 : jsTree category
	$dob_screen_hook[] = add_submenu_page( 
		DOBslug, __('DoBalance',DOBslug), 'jsTree '.__('category',DOBslug),
		'manage_options', DOBslug.'_jstree_category', 'dob_admin_jstree_category'
	);

	/* SUB menu 3 : jsTree hierarchy
	$dob_screen_hook[] = add_submenu_page( 
		DOBslug, __('DoBalance',DOBslug), 'jsTree '.__('hierarchy',DOBslug),
		'manage_options', DOBslug.'_jstree_hierarchy', 'dob_admin_jstree_hierarchy'
	);*/

	// SUB menu 4 : jsTree user
	$dob_screen_hook[] = add_submenu_page( 
		DOBslug, __('DoBalance',DOBslug), 'jsTree '.__('favorite',DOBslug),
		'manage_options', DOBslug.'_jstree_favorite', 'dob_admin_jstree_favorite'
	);
}/*}}}*/

function dob_admin_page() {
	require_once( DOBpathAdmin.'pages/admin.php' );
}
function dob_admin_bulk() {
	require_once( DOBpathAdmin.'pages/bulk.php' );
}
function dob_admin_jstree_category() {/*{{{*/
	$plugins_url = plugins_url('/', __FILE__);
	wp_enqueue_script( DOBslug.'-jstree-js', $plugins_url.'../assets/jstree/jstree.min.js', array( 'jquery' ), DOBver, true );
	wp_enqueue_script( DOBslug.'-admin-jstree-category-js', $plugins_url.'assets/js/jstree_category.js', array( DOBslug.'-jstree-js' ), DOBver, true );
	wp_enqueue_style( DOBslug.'-jstree-css', $plugins_url.'../assets/jstree/themes/default/style.min.css' );
	// localize js-messages
	$locale = array(
		'success' => __( 'Congrats! The terms are added successfully!', DOBslug ),
		'failed'  => __( 'Something went wrong... are you sure you have enough permission to add terms?', DOBslug ),
		'notax'   => __( 'Please select a taxonomy first!', DOBslug ),
		'noterm'  => __( 'Please input some terms!', DOBslug ),
		'confirm' => __( 'Are you sure you want to add these terms?', DOBslug ),
		//'ajax_url' => admin_url( 'admin-ajax.php' ), // just use 'ajaxurl'
		'nonce' => wp_create_nonce('dob_admin_jstree_ajax'.DOBver),
	);
	wp_localize_script( DOBslug.'-admin-jstree-category-js', 'locale_strings', $locale );

	require_once( DOBpathAdmin.'pages/jstree_category.php' );
}/*}}}*/
function dob_admin_jstree_hierarchy() {/*{{{*/
	$plugins_url = plugins_url('/', __FILE__);
	wp_enqueue_script( DOBslug.'-jstree-js', $plugins_url.'../assets/jstree/jstree.min.js', array( 'jquery' ), DOBver, true );
	wp_enqueue_script( DOBslug.'-admin-jstree-hierarchy-js', $plugins_url.'assets/js/jstree_hierarchy.js', array( DOBslug.'-jstree-js' ), DOBver, true );
	wp_enqueue_style( DOBslug.'-jstree-css', $plugins_url.'../assets/jstree/themes/default/style.min.css' );
	// localize js-messages
	$locale = array(
		'success' => __( 'Congrats! The terms are added successfully!', DOBslug ),
		'failed'  => __( 'Something went wrong... are you sure you have enough permission to add terms?', DOBslug ),
		'notax'   => __( 'Please select a taxonomy first!', DOBslug ),
		'noterm'  => __( 'Please input some terms!', DOBslug ),
		'confirm' => __( 'Are you sure you want to add these terms?', DOBslug ),
		//'ajax_url' => admin_url( 'admin-ajax.php' ), // just use 'ajaxurl'
		'nonce' => wp_create_nonce('dob_admin_jstree_ajax'.DOBver),
	);
	wp_localize_script( DOBslug.'-admin-jstree-hierarchy-js', 'locale_strings', $locale );

	require_once( DOBpathAdmin.'pages/jstree_hierarchy.php' );
}/*}}}*/
function dob_admin_jstree_favorite() {/*{{{*/
	$plugins_url = plugins_url('/', __FILE__);
	wp_enqueue_script( DOBslug.'-jstree-js', $plugins_url.'../assets/jstree/jstree.min.js', array( 'jquery' ), DOBver, true );
	wp_enqueue_script( DOBslug.'-admin-jstree-favorite-js', $plugins_url.'assets/js/jstree_favorite.js', array( DOBslug.'-jstree-js' ), DOBver, true );
	wp_enqueue_style( DOBslug.'-jstree-css', $plugins_url.'../assets/jstree/themes/default/style.min.css' );
	// localize js-messages
	$locale = array(
		'success' => __( 'Congrats! The terms are added successfully!', DOBslug ),
		'failed'  => __( 'Something went wrong... are you sure you have enough permission to add terms?', DOBslug ),
		'notax'   => __( 'Please select a taxonomy first!', DOBslug ),
		'noterm'  => __( 'Please input some terms!', DOBslug ),
		'confirm' => __( 'Are you sure you want to add these terms?', DOBslug ),
		//'ajax_url' => admin_url( 'admin-ajax.php' ), // just use 'ajaxurl'
		'nonce' => wp_create_nonce('dob_admin_jstree_ajax'.DOBver),
	);
	wp_localize_script( DOBslug.'-admin-jstree-favorite-js', 'locale_strings', $locale );

	require_once( DOBpathAdmin.'pages/jstree_favorite.php' );
}/*}}}*/

