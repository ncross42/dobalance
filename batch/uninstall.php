<?php
if ( ! defined ('WP_USE_THEMES') ) {
	define('WP_USE_THEMES', false);
	require_once (dirname(dirname(dirname(dirname(__DIR__)))).DIRECTORY_SEPARATOR.'wp-blog-header.php');
}
global $wpdb;

############ version 0.1 ############

##### option #####
unregister_setting('dob_setting', 'dob_root_hierarchy');
unregister_setting('dob_setting', 'dob_root_subject');

##### DB #####
$table_name = $wpdb->prefix.'dob_user_category';
$sql = "DROP TABLE IF EXISTS `$table_name`";
$wpdb->query($sql);

$table_name = $wpdb->prefix.'dob_vote_post_latest';
$sql = "DROP TABLE IF EXISTS `$table_name`";
$wpdb->query($sql);
$table_name = $wpdb->prefix.'dob_vote_post_log';
$sql = "DROP TABLE IF EXISTS `$table_name`";
$wpdb->query($sql);

$table_name = $wpdb->prefix.'term_taxonomy';
$sql = "ALTER TABLE `$table_name`
	DROP `lft`, DROP `rgt`, DROP `lvl`, DROP `pos`,
	DROP INDEX IDX_taxonomy_parent_pos";
$wpdb->query($sql);

/*
// Removes: Options
delete_option('piklist'); // TODO: check for add-ons from other plugins.
delete_option('piklist_demo_fields');
delete_option('piklist_active_plugin_versions');

// Delete all Demo posts
$demos = get_posts(array(
	'numberposts' => -1
	,'post_type' =>'piklist_demo'
	,'post_status' => 'all'
));
if ($demos) {
	foreach ($demos as $post) {
		wp_delete_post($post->ID, true);
	}
}
 */
