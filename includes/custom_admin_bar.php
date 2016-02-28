<?php
/**
 * DoBalance Core Toolbar.
 * Handles the core functions related to the WordPress Toolbar.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Add the secondary BuddyPress area to the my-account menu.
 *
 * @global WP_Admin_Bar $wp_admin_bar.
 */
function dob_admin_bar_my_account_root() {
	global $wp_admin_bar;

	// Bail if this is an ajax request.
	if ( defined( 'DOING_AJAX' ) )
		return;

	// Only add menu for logged in user.
	if ( is_user_logged_in() ) {

		// Add secondary parent item for all BuddyPress components.
		$wp_admin_bar->add_menu( array(
			'parent' => 'my-account',
			'id'     => 'my-account-'.DOBslug,
			'title'  => __( 'My Voting Cart', DOBslug ),
			'href'   => admin_url('admin.php?page=dobalance_cart'),
			/*'group'     => true,
			'meta'      => array(
				'class' => 'ab-sub-secondary'
			)*/
		) );

	}
}
add_action( 'admin_bar_menu', 'dob_admin_bar_my_account_root', 100 );

