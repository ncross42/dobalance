<?php

if ( ! defined ('WP_USE_THEMES') ) {
	define('WP_USE_THEMES', false);
	require_once (dirname(dirname(dirname(dirname(__DIR__)))).DIRECTORY_SEPARATOR.'wp-blog-header.php');
}
global $wpdb;

############ version 0.1 ############

##### option #####
register_setting('dob_setting', 'dob_root_hierarchy', 'trim' );
register_setting('dob_setting', 'dob_root_subject'  , 'trim' );

##### DB #####
$table_name = $wpdb->prefix.'dob_user_category';
$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
	`user_id` int(11) NOT NULL DEFAULT '0',
	`taxonomy` enum('category','favorite','hierarchy','topic','party','union') NOT NULL DEFAULT 'category',
	`term_taxonomy_id` int(11) NOT NULL DEFAULT '0',
	PRIMARY KEY (`user_id`,`taxonomy`,`term_taxonomy_id`),
	KEY `term_taxonomy_id` (`term_taxonomy_id`,`taxonomy`,`user_id`)
)";
$wpdb->query($sql);

$table_name = $wpdb->prefix.'dob_vote_post_latest';
$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
	`post_id` int(11) NOT NULL DEFAULT 0,
	`user_id` int(11) NOT NULL DEFAULT 0,
	`value` tinyint(2) NOT NULL DEFAULT 0,
	`ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`post_id`,`user_id`),
	KEY (`post_id`,`value`)
)";
$wpdb->query($sql);
$table_name = $wpdb->prefix.'dob_vote_post_log';
$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
	`user_id` int(11) NOT NULL DEFAULT 0,
	`post_id` int(11) NOT NULL DEFAULT 0,
	`ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`value` tinyint(2) NOT NULL DEFAULT 0,
	`ip` varchar(250) COLLATE latin1_general_ci NOT NULL DEFAULT '',
	PRIMARY KEY (`user_id`,`post_id`,`ts`)
)";
$wpdb->query($sql);

$table_name = $wpdb->prefix.'term_taxonomy';
if ( empty($wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'lft';") ) ) {
	$sql = "ALTER TABLE `$table_name` 
		ADD COLUMN `lft` SMALLINT UNSIGNED NOT NULL DEFAULT '0' COMMENT 'MPTT columns used by DoBalance' AFTER `count`,
		ADD COLUMN `rgt` SMALLINT UNSIGNED NOT NULL DEFAULT '0' COMMENT 'MPTT columns used by DoBalance' AFTER `lft`,
		ADD COLUMN `lvl` TINYINT UNSIGNED NOT NULL DEFAULT '0' COMMENT 'MPTT columns used by DoBalance' AFTER `rgt`,
		ADD COLUMN `pos` TINYINT UNSIGNED NOT NULL DEFAULT '0' COMMENT 'MPTT columns used by DoBalance' AFTER `lvl`,
		ADD INDEX `IDX_taxonomy_parent_pos` ( `taxonomy`, `parent`, `pos` )
	"; 
	$wpdb->query($sql);
}
