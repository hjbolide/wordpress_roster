<?php

class RosterOption {

    private function get_header () {
        return '<h2>Roster Options</h2>';
    }

    private function get_form () {
        return '<form method="post" actions="options.php">';
    }

    public function output () {
        echo '<div class="wrap">' . $this->get_header();
    }
}

$option = new RosterOption();

?>