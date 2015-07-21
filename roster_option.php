<?php

class RosterOptionController {

    private $option_group = 'roster-group';
    private $options;

    public function __construct () {
        add_action('admin_menu', array($this, 'add_page'));
        add_action('admin_init', array($this, 'page_init'));
    }

    public function page_init () {
        register_setting('roster_options_group', 'roster_options');
        add_settings_section(
            'setting_section_id',
            NULL,
            NULL,
            'roster_options');
        add_settings_field(
            'people_category',
            'People Category',
            array($this, 'people_category_callback'),
            'roster_options',
            'setting_section_id'
        );
        add_settings_field(
            'weekday_category',
            'Weekday Category',
            array($this, 'weekday_category_callback'),
            'roster_options',
            'setting_section_id'
        );
    }

    public function section_info_callback () {
        echo 'Timetable options';
    }

    public function people_category_callback () {
        $input = <<<HTML

<input type="text" id="people_category"
       name="roster_options[people_category]" value="%s" />

HTML;
        $value = isset($this->options['people_category'])
               ? esc_attr($this->options['people_category'])
               : '';
        printf($input, $value);
    }

    public function weekday_category_callback() {
        $input = <<<HTML

<input type="text" id="weekday_category"
       name="roster_options[weekday_category]" value="%s" />

HTML;
        $value = isset($this->options['weekday_category'])
               ? esc_attr($this->options['weekday_category'])
               : '';
        printf($input, $value);
    }

    public function add_page () {
        add_options_page(
            'Settings Admin', 'Timetable', 'manage_options',
            'roster_options', array($this, 'execute'));
    }

    private function _header () {
        echo '<h2>Roster Options</h2>';
    }

    private function _form () {
        echo '<form method="post" action="options.php">';
        settings_fields('roster_options_group');
        do_settings_sections('roster_options');
        submit_button();
        echo '</form>';
    }

    public function execute () {
        $this->options = get_option('roster_options');
        $this->output();
    }

    public function output () {
        echo '<div class="wrap">';
        $this->_header();
        $this->_form();
        echo '</div>';
    }
}

?>