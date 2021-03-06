<?php

/**
 * DoBalance.
 *
 * @package   DoBalance_Admin
 * @author    HeeWon Lee <ncross42@gmail.com>
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2015 DoBalance
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * administrative side of the WordPress site.
 *
 * If you're interested in introducing public-facing
 * functionality, then refer to `class-dobalance.php`
 *
 * @package DoBalance_Admin
 * @author  HeeWon Lee <ncross42@gmail.com>
 */
class DoBalance_Admin {

	/**
	 * Instance of this class.
	 * @since    0.0.1
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Slug of the plugin screen.
	 * @since    0.0.1
	 * @var      array
	 */
	protected $plugin_screen_hook_suffix = array();

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a settings page and menu.
	 * @since     0.0.1
	 */
	private function __construct() {

		// available only for super admins
		if( ! is_super_admin() ) {
		  return;
		}

		/**
		 * Call $plugin_slug from public plugin class.
		 */
		$plugin = DoBalance::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();
		$this->plugin_name = $plugin->get_plugin_name();
		$this->version = $plugin->get_plugin_version();
		$this->cpts = $plugin->get_cpts();

		// Load admin style sheet and JavaScript.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		// Load admin style in dashboard for the At glance widget
		add_action( 'admin_head-index.php', array( $this, 'enqueue_admin_styles' ) );

		// At Glance Dashboard widget for your cpts
		add_filter( 'dashboard_glance_items', array( $this, 'cpt_glance_dashboard_support' ), 10, 1 );
		// Activity Dashboard widget for your cpts
		add_filter( 'dashboard_recent_posts_query_args', array( $this, 'cpt_activity_dashboard_support' ), 10, 1 );

		// Add the options page and menu item.
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
		//Add bubble notification for cpt pending
		add_action( 'admin_menu', array( $this, 'pending_cpt_bubble' ), 999 );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( realpath( dirname( __FILE__ ) ) ) . $this->plugin_slug . '.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		require_once( plugin_dir_path( __FILE__ ) . 'views/user_hierarchy.php' );

		/**
		 * CMB 2 for metabox and many other cool things!
		 * https://github.com/WebDevStudios/CMB2
		 */
		require_once( plugin_dir_path( __FILE__ ) . '/includes/CMB2/init.php' );
		/**
		 * CMB2 Shortcode support 
		 * Check on the repo for the example and documentation 
		 * https://github.com/jtsternberg/Shortcode_Button
		 */
		require_once( plugin_dir_path( __FILE__ ) . '/includes/CMB2-Shortcode/shortcode-button.php' );
		/**
		 * CMB2 Grid 
		 * Check on the repo for the example and documentation 
		 * https://github.com/origgami/CMB2-grid
		 */
		require_once( plugin_dir_path( __FILE__ ) . '/includes/CMB2-grid/Cmb2GridPlugin.php' );

		/**
		 * Add metabox
		 */
		add_action( 'cmb2_init', array( $this, 'cmb_demo_metaboxes' ) );

		/**
		 * Define custom functionality.
		 *
		 * Read more about actions and filters:
		 * http://codex.wordpress.org/Plugin_API#Hooks.2C_Actions_and_Filters
		 */
		add_action( '@TODO', array( $this, 'action_method_name' ) );
		add_filter( '@TODO', array( $this, 'filter_method_name' ) );

		/**
		 * Import Export settings
		 */
		require_once( plugin_dir_path( __FILE__ ) . 'includes/impexp.php' );

		/**
		 * Debug mode
		 */
		require_once( plugin_dir_path( __FILE__ ) . 'includes/debug.php' );
		$debug = new Pn_Debug( );
		$debug->log( __( 'Plugin Loaded', $this->plugin_slug ) );

		/**
		 * Load Wp_Contextual_Help for the help tabs
		 */
		add_filter( 'wp_contextual_help_docs_dir', array( $this, 'help_docs_dir' ) );
		add_filter( 'wp_contextual_help_docs_url', array( $this, 'help_docs_url' ) );
		if ( !class_exists( 'WP_Contextual_Help' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . 'includes/WP-Contextual-Help/wp-contextual-help.php' );
		}
		add_action( 'init', array( $this, 'contextual_help' ) );

		/**
		 * Load Wp_Admin_Notice for the notices in the backend
		 * First parameter the HTML, the second is the css class
		if ( !class_exists( 'WP_Admin_Notice' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . 'includes/WP-Admin-Notice/WP_Admin_Notice.php' );
		}
		new WP_Admin_Notice( __( 'Updated Messages' ), 'updated' );
		new WP_Admin_Notice( __( 'Error Messages' ), 'error' );
		 */

		/**
		 * Load CPT_Columns
		 * 
		 * Check the file for example
		 */
		require_once( plugin_dir_path( __FILE__ ) . 'includes/CPT_Columns.php' );
		$post_columns = new CPT_columns( 'demo' );
		$post_columns->add_column( 'cmb2_field', array(
		    'label' => __( 'CMB2 Field' ),
		    'type' => 'post_meta',
		    'meta_key' => '_demo_' . $this->plugin_slug . '_text',
		    'orderby' => 'meta_value',
		    'sortable' => true,
		    'prefix' => "<b>",
		    'suffix' => "</b>",
		    'def' => "Not defined", // default value in case post meta not found
		    'order' => "-1"
			)
		);
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     0.0.1
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		/**
		 * - Uncomment following lines if the admin class should only be available for super admins
		 */
		if( ! is_super_admin() ) {
		  return;
		}

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @since     0.0.1
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {
		if ( !isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( in_array($screen->id,$this->plugin_screen_hook_suffix)	// $this->plugin_screen_hook_suffix == $screen->id 
			|| strpos( $_SERVER[ 'REQUEST_URI' ], 'index.php' ) 
			|| strpos( $_SERVER[ 'REQUEST_URI' ], get_bloginfo( 'wpurl' ) . '/wp-admin/' ) 
		) {
			wp_enqueue_style( $this->plugin_slug . '-admin-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), array( 'dashicons' ), DoBalance::VERSION );
		}
	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * @since     0.0.1
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {
		if ( !isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		// if ( 	$this->plugin_screen_hook_suffix == $screen->id ) {
		if ( in_array($screen->id,$this->plugin_screen_hook_suffix) ) {
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery', 'jquery-ui-tabs' ), DoBalance::VERSION );
		}
	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    0.0.1
	 */
	public function add_plugin_admin_menu() {

		/**
		 * Add a settings page for this plugin to the Settings menu.
		 * Administration Menus: http://codex.wordpress.org/Administration_Menus
		 *
		 * add_menu_page('Page title', 'Top-level menu title', 'manage_options', 'my-top-level-handle', 'my_magic_function');
		 * add_submenu_page( 'my-top-level-handle', 'Page title', 'Sub-menu title', 'manage_options', 'my-submenu-handle', 'my_magic_function');
		 *
		 * 'manage_options' : http://codex.wordpress.org/Roles_and_Capabilities
		 */
		$this->plugin_screen_hook_suffix[] = add_options_page(
			__( 'DoBalance Admin', $this->plugin_slug ), $this->plugin_name, 'manage_options', $this->plugin_slug
			, array( $this, 'display_plugin_admin_page' )
		);

		// add_menu_page
		$this->plugin_screen_hook_suffix[] = add_menu_page( 
			__('DoBalance',$this->plugin_slug), __('DoBalance',$this->plugin_slug), 'manage_options', $this->plugin_slug
			, array($this,'display_plugin_admin_page'), 'dashicons-hammer', 3
		);

		// add_submenu_page : bulk
		$this->plugin_screen_hook_suffix[] = add_submenu_page( 
			$this->plugin_slug, __('DoBalance',$this->plugin_slug), __('add bulk category',$this->plugin_slug),
		 	'manage_options', $this->plugin_slug.'_bulk', array( $this, 'display_plugin_admin_bulk' )
		);
		// add_submenu_page : jsTree
		$this->plugin_screen_hook_suffix[] = add_submenu_page( 
			$this->plugin_slug, __('DoBalance',$this->plugin_slug), __('jsTree category',$this->plugin_slug),
		 	'manage_options', $this->plugin_slug.'_jstree', array( $this, 'display_plugin_admin_jstree' )
		);
	}

	/**
	 * Render the admin page for this plugin.
	 * @since    0.0.1
	 */
	public function display_plugin_admin_page() {
		include_once( "views/admin.php" );
	}

	/**
	 * Render the admin page for this plugin.
	 * @since    0.0.1
	 */
	public function display_plugin_admin_bulk() {
		include_once( "views/bulk.php" );
	}

	/**
	 * Render the admin page for this plugin.
	 * @since    0.0.1
	 */
	public function display_plugin_admin_jstree() {
		wp_enqueue_style( 'dob-jstree-css', plugins_url('/assets/jstree/themes/default/style.min.css',__DIR__) );
		wp_enqueue_script( 'dob-jstree-js', plugins_url('/assets/jstree/jstree.min.js',__DIR__), array( 'jquery' ), 1.0, true );
		wp_enqueue_script( 'dob-admin-jstree-js', plugins_url('/assets/js/jstree.js',__FILE__), array( 'dob-jstree-js' ), 1.0, true );
		// localize js-messages
		$locale = array(
			'success' => 'Congrats! The terms are added successfully!',
			'failed'  => 'Something went wrong... are you sure you have enough permission to add terms?',
			'notax'   => 'Please select a taxonomy first!',
			'noterm'  => 'Please input some terms!',
			'confirm' => 'Are you sure you want to add these terms?',
			/*'success' => __( 'Congrats! The terms are added successfully!', 'dob_domain' ),
			'failed'  => __( 'Something went wrong... are you sure you have enough permission to add terms?', 'dob_domain' ),
			'notax'   => __( 'Please select a taxonomy first!', 'dob_domain' ),
			'noterm'  => __( 'Please input some terms!', 'dob_domain' ),
			'confirm' => __( 'Are you sure you want to add these terms?', 'dob_domain' ),*/
			// 'ajax_url' => admin_url( 'admin-ajax.php' ), // just use 'ajaxurl'
			'nonce' => wp_create_nonce('dob_admin_jstree_ajax'),
		);
		wp_localize_script( 'dob-admin-jstree-js', 'locale_strings', $locale );

		include_once( "views/jstree.php" );
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    0.0.1
	 */
	public function add_action_links( $links ) {
		return array_merge(
			array(
		    'settings' => '<a href="' . admin_url( 'options-general.php?page=' . $this->plugin_slug ) . '">' . __( 'Settings' ) . '</a>',
		    'donate' => '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=danielemte90@alice.it&item_name=Donation">' . __( 'Donate', $this->plugin_slug ) . '</a>'
			), $links
		);
	}

	/**
	 * NOTE:     Actions are points in the execution of a page or process
	 *           lifecycle that WordPress fires.
	 *
	 *           Actions:    http://codex.wordpress.org/Plugin_API#Actions
	 *           Reference:  http://codex.wordpress.org/Plugin_API/Action_Reference
	 *
	 * @since    0.0.1
	 */
	public function action_method_name() {
		// @TODO: Define your action hook callback here
	}

	/**
	 * NOTE:     Filters are points of execution in which WordPress modifies data
	 *           before saving it or sending it to the browser.
	 *
	 *           Filters: http://codex.wordpress.org/Plugin_API#Filters
	 *           Reference:  http://codex.wordpress.org/Plugin_API/Filter_Reference
	 *
	 * @since    0.0.1
	 */
	public function filter_method_name() {
		// @TODO: Define your filter hook callback here
	}

	/**
	 * Add the counter of your CPTs in At Glance widget in the dashboard<br>
	 * NOTE: add in $post_types your cpts, remember to edit the css style (admin/assets/css/admin.css) for change the dashicon<br>
	 *
	 *        Reference:  http://wpsnipp.com/index.php/functions-php/wordpress-post-types-dashboard-at-glance-widget/
	 *
	 * @since    0.0.1
	 */
	public function cpt_glance_dashboard_support( $items = array() ) {
		$post_types = $this->cpts;
		foreach ( $post_types as $type ) {
			if ( !post_type_exists( $type ) ) {
				continue;
			}
			$num_posts = wp_count_posts( $type );
			if ( $num_posts ) {
				$published = intval( $num_posts->publish );
				$post_type = get_post_type_object( $type );
				$text = _n( '%s ' . $post_type->labels->singular_name, '%s ' . $post_type->labels->name, $published, $this->plugin_slug );
				$text = sprintf( $text, number_format_i18n( $published ) );
				if ( current_user_can( $post_type->cap->edit_posts ) ) {
					$items[] = '<a class="' . $post_type->name . '-count" href="edit.php?post_type=' . $post_type->name . '">' . sprintf( '%2$s', $type, $text ) . "</a>\n";
				} else {
					$items[] = sprintf( '%2$s', $type, $text ) . "\n";
				}
			}
		}
		return $items;
	}

	/**
	 * Add the recents post type in the activity widget<br>
	 * NOTE: add in $post_types your cpts
	 *
	 * @since    0.0.1
	 */
	function cpt_activity_dashboard_support( $query_args ) {
		if ( !is_array( $query_args[ 'post_type' ] ) ) {
			//Set default post type
			$query_args[ 'post_type' ] = array( 'page' );
		}
		$query_args[ 'post_type' ] = array_merge( $query_args[ 'post_type' ], $this->cpts );
		return $query_args;
	}

	/**
	 * Bubble Notification for pending cpt<br>
	 * NOTE: add in $post_types your cpts<br>
	 *
	 *        Reference:  http://wordpress.stackexchange.com/questions/89028/put-update-like-notification-bubble-on-multiple-cpts-menus-for-pending-items/95058
	 *
	 * @since    0.0.1
	 */
	function pending_cpt_bubble() {
		global $menu;

		$post_types = $this->cpts;
		foreach ( $post_types as $type ) {
			if ( !post_type_exists( $type ) ) {
				continue;
			}
			// Count posts
			$cpt_count = wp_count_posts( $type );

			if ( $cpt_count->pending ) {
				// Menu link suffix, Post is different from the rest
				$suffix = ( 'post' == $type ) ? '' : "?post_type=$type";

				// Locate the key of 
				$key = self::recursive_array_search_php( "edit.php$suffix", $menu );

				// Not found, just in case 
				if ( !$key ) {
					return;
				}

				// Modify menu item
				$menu[ $key ][ 0 ] .= sprintf(
					'<span class="update-plugins count-%1$s"><span class="plugin-count">%1$s</span></span>', $cpt_count->pending
				);
			}
		}
	}

	/**
	 * Required for the bubble notification<br>
	 *
	 *        Reference:  http://wordpress.stackexchange.com/questions/89028/put-update-like-notification-bubble-on-multiple-cpts-menus-for-pending-items/95058
	 *
	 * @since    0.0.1
	 */
	private function recursive_array_search_php( $needle, $haystack ) {
		foreach ( $haystack as $key => $value ) {
			$current_key = $key;
			if ( $needle === $value OR ( is_array( $value ) && self::recursive_array_search_php( $needle, $value ) !== false) ) {
				return $current_key;
			}
		}
		return false;
	}

	/**
	 * NOTE:     Your metabox on Demo CPT
	 *
	 * @since    0.0.1
	 */
	public function cmb_demo_metaboxes() {
		// Start with an underscore to hide fields from custom fields list
		$prefix = '_demo_';
		$cmb_demo = new_cmb2_box( array(
		    'id' => $prefix . 'metabox',
		    'title' => __( 'Demo Metabox', $this->plugin_slug ),
		    'object_types' => array( 'demo', ), // Post type
		    'context' => 'normal',
		    'priority' => 'high',
		    'show_names' => true, // Show field names on the left
			) );
		$cmb2Grid = new \Cmb2Grid\Grid\Cmb2Grid( $cmb_demo );
		$row = $cmb2Grid->addRow();
		$field1 = $cmb_demo->add_field( array(
		    'name' => __( 'Text', $this->plugin_slug ),
		    'desc' => __( 'field description (optional)', $this->plugin_slug ),
		    'id' => $prefix . $this->plugin_slug . '_text',
		    'type' => 'text'
			) );

		$field2 = $cmb_demo->add_field( array(
		    'name' => __( 'Text Small', $this->plugin_slug ),
		    'desc' => __( 'field description (optional)', $this->plugin_slug ),
		    'id' => $prefix . $this->plugin_slug . '_textsmall',
		    'type' => 'text_small'
			) );
		$row->addColumns( array( $field1, $field2 ) );
	}

	/**
	 * Filter for change the folder of Contextual Help
	 * 
	 * @since     0.0.1
	 *
	 * @return    string    the path
	 */
	public function help_docs_dir( $paths ) {
		$paths[] = plugin_dir_path( __FILE__ ) . '../help-docs/';
		return $paths;
	}

	/**
	 * Filter for change the folder image of Contextual Help
	 * 
	 * @since     0.0.1
	 *
	 * @return    string    the path
	 */
	public function help_docs_url( $paths ) {
		$paths[] = plugin_dir_path( __FILE__ ) . '../help-docs/img';
		return $paths;
	}

	/**
	 * Contextual Help, docs in /help-docs folter
	 * Documentation https://github.com/voceconnect/wp-contextual-help
	 * 
	 * @since    0.0.1
	 */
	public function contextual_help() {
		if ( !class_exists( 'WP_Contextual_Help' ) ) {
			return;
		}

		// Only display on the pages - post.php and post-new.php, but only on the `demo` post_type
		WP_Contextual_Help::register_tab( 'demo-example', __( 'Demo Management', $this->plugin_slug ), array(
		    'page' => array( 'post.php', 'post-new.php' ),
		    'post_type' => 'demo',
		    'wpautop' => true
		) );

		// Add to a custom plugin settings page
		WP_Contextual_Help::register_tab( 'pn_settings', __( 'Boilerplate Settings', $this->plugin_slug ), array(
		    'page' => 'settings_page_' . $this->plugin_slug,
		    'wpautop' => true
		) );
	}

}
