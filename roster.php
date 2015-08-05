<?php

require_once('roster_admin.php');
require_once('roster_option.php');

/*
    Plugin Name: Roster
    Plugin URI: http://www.google.com
    Description: Plugin for roster
    Author: CH
    Version: 0.2
*/

global $ver;
$ver = '0.2';

function roster_install () {
    global $wpdb;
    $installed_ver = get_option('roster_db_version');
    $prefix = $wpdb->prefix;
    $charset = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    if (!$installed_ver) {
        $table_name = $prefix . 'roster_audit';
        $sql = "
CREATE TABLE $table_name (
ID BIGINT UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
modified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
UNIQUE KEY id (id)
) $charset
";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        update_option('roster_db_version', '0.1');
    }
}

function ngroster_install () {
    global $wpdb, $ver;
    $installed_ver = get_option('roster_db_version');
    $prefix = $wpdb->prefix;
    if ($installed_ver < '0.2') {
        $table_name = $prefix . 'ngroster';
        $post_table = $prefix . 'posts';
        $sql = "
        CREATE TABLE $table_name (
        ID BIGINT UNSIGNED PRIMARY KEY NOT NULL AUTO_INCREMENT,
        pid BIGINT UNSIGNED NOT NULL,
        roster_date DATE NOT NULL,
        working TINYINT(1) NOT NULL,
        FOREIGN KEY (pid) REFERENCES $post_table(ID)
        )
        ";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    update_option('roster_db_version', $ver);
}

register_activation_hook(__FILE__, 'roster_install');
add_action('plugins_loaded', 'ngroster_install');

new RosterAdminController();
new NGRosterAdminController();
new RosterOptionController();
