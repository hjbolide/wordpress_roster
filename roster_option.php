<?php

class RosterOptionController {

    $option_group = 'roster-group';

    public function __construct () {
        settings_fields('roster-group');
        do_settings_sections('roster-group');
        add_action('admin_init', array($this, 'register_roster_options'));
    }

    public function register_roster_options () {
        register_setting('roster-group')
    }

    private function get_header () {
        return '<h2>Roster Options</h2>';
    }

    private function get_form () {
        return '<form method="post" actions="options.php">'
            .'</form>';
    }

    public function output () {
        echo '<div class="wrap">' . $this->get_header() . </div>;
    }
}

?>