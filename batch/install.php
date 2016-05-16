<?php

if ( ! defined ('WP_USE_THEMES') ) {
	define('WP_USE_THEMES', false);
	require_once (dirname(dirname(dirname(dirname(__DIR__)))).DIRECTORY_SEPARATOR.'wp-blog-header.php');
}

############ version 0.1 ############
global $wpdb;

##### DB #####
$table_name = $wpdb->prefix.'dob_cache';
$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
	`post_id` int(11) NOT NULL DEFAULT 0,
	`type` ENUM('all','stat','result','detail') NOT NULL DEFAULT 'all',
	`data` text COLLATE ascii_bin DEFAULT NULL,
	`ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`post_id`,`type`)
)";
$wpdb->query($sql);

$table_name = $wpdb->prefix.'dob_cart';
$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
	`user_id` bigint unsigned NOT NULL DEFAULT '0',
	`type` ENUM('vote','elect') NOT NULL DEFAULT 'vote',
	`post_id` int(11) NOT NULL DEFAULT 0,
	`value` smallint(2) NOT NULL DEFAULT 0,
	`ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`user_id`,`type`,`post_id`)
)";
$wpdb->query($sql);

$table_name = $wpdb->prefix.'dob_upin';
$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
	`user_id`      bigint unsigned NOT NULL DEFAULT '0',
	`ci`           binary(88),
	`realname`     varchar(60) NOT NULL DEFAULT '',
	`age`          tinyint DEFAULT NULL COMMENT '0<9,1<12,2<14,3<15,4<18,5<19,6<20,7>=20',
	`sex`          tinyint DEFAULT NULL COMMENT '0:F,1:M',
	`nationalinfo` tinyint DEFAULT NULL COMMENT '0:N,1:F',
	`birthdate`    DATE DEFAULT NULL,
	`authinfo`     tinyint DEFAULT NULL COMMENT '0:pki,1:card,2:cell,3:face',
	PRIMARY KEY (`user_id`),
	UNIQUE KEY (`ci`)
)";
$wpdb->query($sql);

$table_name = $wpdb->prefix.'dob_user_category';
$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
	`user_id` int(11) NOT NULL DEFAULT '0',
	`taxonomy` enum('category','favorite','hierarchy','topic','group','party','union') NOT NULL DEFAULT 'category',
	`term_taxonomy_id` int(11) NOT NULL DEFAULT '0',
	PRIMARY KEY (`user_id`,`taxonomy`,`term_taxonomy_id`),
	KEY `term_taxonomy_id` (`term_taxonomy_id`,`taxonomy`,`user_id`)
)";
$wpdb->query($sql);

$table_name = $wpdb->prefix.'dob_elect_latest';
$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
	`post_id` int(11) NOT NULL DEFAULT 0,
	`user_id` int(11) NOT NULL DEFAULT 0,
	`value` smallint(2) NOT NULL DEFAULT 0,
	`ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`post_id`,`user_id`),
	KEY (`post_id`,`value`)
)";
$wpdb->query($sql);
$table_name = $wpdb->prefix.'dob_elect_log';
$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
	`user_id` int(11) NOT NULL DEFAULT 0,
	`post_id` int(11) NOT NULL DEFAULT 0,
	`ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`value` smallint(2) NOT NULL DEFAULT 0,
	`ip` char(15) COLLATE ascii_bin NOT NULL DEFAULT '',
	PRIMARY KEY (`user_id`,`post_id`,`ts`)
)";
$wpdb->query($sql);

$table_name = $wpdb->prefix.'dob_vote_post_latest';
$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
	`post_id` int(11) NOT NULL DEFAULT 0,
	`user_id` int(11) NOT NULL DEFAULT 0,
	`value` smallint(2) NOT NULL DEFAULT 0,
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
	`value` smallint(2) NOT NULL DEFAULT 0,
	`ip` char(15) COLLATE ascii_bin NOT NULL DEFAULT '',
	PRIMARY KEY (`user_id`,`post_id`,`ts`)
)";
$wpdb->query($sql);

$table_name = $wpdb->prefix.'term_taxonomy';
if ( empty($wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'lft';") ) ) {
	$sql = "ALTER TABLE `$table_name` 
		ADD COLUMN `lft` SMALLINT UNSIGNED NOT NULL DEFAULT '0' COMMENT 'left MPTT columns used by DoBalance' AFTER `count`,
		ADD COLUMN `rgt` SMALLINT UNSIGNED NOT NULL DEFAULT '0' COMMENT 'right MPTT columns used by DoBalance' AFTER `lft`,
		ADD COLUMN `lvl` TINYINT  UNSIGNED NOT NULL DEFAULT '0' COMMENT 'level MPTT columns used by DoBalance' AFTER `rgt`,
		ADD COLUMN `pos` TINYINT  UNSIGNED NOT NULL DEFAULT '0' COMMENT 'position MPTT columns used by DoBalance' AFTER `lvl`,
		ADD COLUMN `inf` SMALLINT UNSIGNED NOT NULL DEFAULT '0' COMMENT 'influences(lower users count) MPTT columns used by DoBalance' AFTER `pos`,
		ADD COLUMN `chl` TINYINT  UNSIGNED NOT NULL DEFAULT '0' COMMENT 'children node count MPTT columns used by DoBalance' AFTER `inf`,
		ADD COLUMN `anc` VARCHAR(255) COLLATE ascii_bin NOT NULL DEFAULT '' COMMENT 'ancestor MPTT columns used by DoBalance' AFTER `chl`,
		ADD INDEX `IDX_taxonomy_parent_pos` ( `taxonomy`, `parent`, `pos` )
	"; 
	$wpdb->query($sql);
}
