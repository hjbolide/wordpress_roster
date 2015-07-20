<?php

/*
    Plugin Name: Roster
    Plugin URI: http://www.google.com
    Description: Plugin for roster
    Author: CH
    Version: 0.1
*/


function roster_init () {
    wp_register_script('roster_script', plugins_url('roster.js', __FILE__), array('jquery'));
}


function roster_enqueue_scripts () {
    wp_enqueue_script('roster_script');
}


function roster_bootstrap () {
    include('roster_admin.php');
}


function roster_actions () {
    add_menu_page('Roster', 'Roster', 'read', 'Roster', 'roster_bootstrap');
}

add_action('admin_init', 'roster_init');
add_action('admin_enqueue_scripts', 'roster_enqueue_scripts');
add_action('admin_menu', 'roster_actions');

?>