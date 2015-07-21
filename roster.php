<?php

require_once('roster_admin.php');
require_once('roster_option.php');

/*
    Plugin Name: Roster
    Plugin URI: http://www.google.com
    Description: Plugin for roster
    Author: CH
    Version: 0.1
*/

global $ver;
$ver = '0.1';

global $table;
$table = 'roster_audit';

function roster_install () {
    global $wpdb;
    global $ver;
    global $table;
    $installed_ver = get_option('roster_db_version');
    if ($installed_ver != $ver) {
        $table_name = $wpdb->prefix . $table;
        $charset = $wpdb->get_charset_collate();
        $sql = "
CREATE TABLE $table_name (
id mediumint(9) NOT NULL AUTO_INCREMENT,
modified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
UNIQUE KEY id (id)
) $charset
";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        update_option('roster_db_version', $ver);
    }
}

register_activation_hook(__FILE__, 'roster_install');

$controller = new RosterAdminController();
$options = new RosterOptionController();

?>