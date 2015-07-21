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

function roster_init () {
    wp_register_script('floatthead_script', plugins_url('jquery.floatThead.min.js', __FILE__), array('jquery'));
    wp_register_script('roster_script', plugins_url('roster.js', __FILE__), array('jquery', 'floatthead_script'));
}


function roster_enqueue_scripts () {
    wp_enqueue_script('floatthead_script');
    wp_enqueue_script('roster_script');
    wp_localize_script('roster_script', 'RosterAdmin', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}


function roster_bootstrap () {
    $controller = new RosterAdminController();
    $controller->execute();
}


function roster_option () {
    $option = new RosterOptionController();
    $option->output();
}


function roster_actions () {
    add_menu_page('Timetable', 'Timetable', 'read', 'timetable', 'roster_bootstrap');
    add_options_page('Settings Admin', 'Timetable', 'manage_options', 'timetable_option', 'roster_option');
}

add_action('admin_init', 'roster_init');
add_action('admin_enqueue_scripts', 'roster_enqueue_scripts');
add_action('admin_menu', 'roster_actions');
add_action('wp_ajax_save_roster', 'save_roster');

function save_roster () {
    $roster = str_replace('\"', '"', $_POST['roster']);
    $roster = json_decode($roster, true);
    $controller = new RosterAdminController();
    $controller->save_roster($roster);
    ob_clean();
    echo $_POST['roster'];
    wp_die();
}

?>