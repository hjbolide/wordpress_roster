<?php

require_once('roster_audittrail.php');

class Person {

    private $reference;
    private $categories;

    private $entity_cell_tpl = <<<TPL

<td class="column-description desc">
  <strong>%s</strong>
  <input type="hidden" name="pid" value="%s" />
</td>

TPL;

    private $weekday_cell_tpl = <<<TPL

<td style="text-align: center;">
  <input type="checkbox" name="weekday_%s" value="%s" %s />
</td>

TPL;

    public function __construct($ref) {
        $this->reference = $ref;
        $this->categories = wp_get_post_categories($this->reference->ID);
    }

    public function get_row_html ($weekdays) {
        return '<tr>' . $this->get_entity_cell_html() . $this->get_roster_html($weekdays) . '</tr>';
    }

    private function get_roster_html ($weekdays) {
        $html = '';
        foreach ($weekdays as $w) {
            $checked = '';
            if (in_array($w->term_id, $this->categories)) {
                $checked = 'checked="checked"';
            }
            $html .= sprintf($this->weekday_cell_tpl, $w->term_id, $w->term_id, $checked);
        }
        return $html;
    }

    private function get_entity_cell_html () {
        return sprintf($this->entity_cell_tpl, $this->reference->post_title, $this->reference->ID);
    }

    public function get_id () {
        return $this->reference->ID;
    }

    public function save_roster ($weekdays) {
        $categories = array_keys(array_filter($weekdays, function ($w) {
            return $w;
        }));
        foreach ($this->categories as $c) {
            if (!array_key_exists($c, $weekdays)) {
                array_push($categories, $c);
            }
        }
        wp_set_post_categories($this->reference->ID, $categories, false);
    }
}


class Roster {

    private $people;
    private $weekdays;

    private $table_header_cell_tpl = <<<TPL

<th scope="col" class="manage-column column-description" style="text-align: center;">
  %s
</th>

TPL;

    public function __construct ($weekdays, $people) {
        $this->weekdays = $weekdays;
        $this->people = $people;
    }

    public function get_html () {
        return '<table id="roster_table" class="wp-list-table widefat striped">' . $this->get_table_header() . $this->get_table_body() . '</table>';
    }

    private function get_table_header () {
        $html = '<thead><tr><th></th>';
        foreach ($this->weekdays as $w) {
            $html .= sprintf($this->table_header_cell_tpl, $w->name);
        }
        return $html.'</tr></thead>';
    }

    private function get_table_body () {
        $html = '<tbody>';
        foreach ($this->people as $p) {
            $html .= $p->get_row_html($this->weekdays);
        }
        return $html.'</tbody>';
    }

    private function get_people_in_assoc_array () {
        $result = array();
        foreach ($this->people as $p) {
            $result[$p->get_id()] = $p;
        }
        return $result;
    }

    public function save ($roster) {
        $people_dict = $this->get_people_in_assoc_array();
        foreach ($roster as $pid => $weekdays) {
            $people = $people_dict[$pid];
            $people->save_roster($weekdays);
        }
    }
}


class RosterAdminController {

    private $roster;
    private $weekdays;
    private $people;
    private $options;
    private $audit;

    public function __construct () {
        add_action('admin_menu', array($this, 'add_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_save_roster', array($this, 'save_roster'));
    }

    public function init () {
        $this->options = get_option('roster_options');
        if (!$this->options['weekday_category']) {
            $this->options['weekday_category'] = 'weekdays';
        }
        $this->weekdays = $this->get_weekdays();
        $this->people = $this->get_people();
        $this->roster = new Roster($this->weekdays, $this->people);
        $this->audit = new RosterAuditTrail();
    }

    public function page_init () {
        wp_register_script('floatthead_script', plugins_url('jquery.floatThead.min.js', __FILE__), array('jquery'));
        wp_register_script('roster_script', plugins_url('roster.js', __FILE__), array('jquery', 'floatthead_script'));
        $this->init();
    }

    public function enqueue_scripts () {
        wp_enqueue_script('floatthead_script');
        wp_enqueue_script('roster_script');
        wp_localize_script('roster_script', 'RosterAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    }

    public function add_page () {
        add_menu_page(
            'Timetable', 'Timetable', 'read', 'timetable',
            array($this, 'execute'));
    }

    public function execute () {
        if (!$this->options['weekday_category']) {
?>
<div class="wrap"><h2>Please set weekday category</h2></div>
<?php
            return;
        }
        $this->output();
    }

    private function get_header () {
        return '<h2>Timetable Edit</h2>';
    }

    private function get_notification_html () {
        return <<<HTML

<div id="message" class="updated notice" style="display: none; position: fixed; top: 100px; z-index: 99999;">
  <p>Roster <strong>updated</strong>.</p>
</div>

HTML;
    }

    private function output () {
        echo '<div class="wrap">'
            . $this->get_header()
            . $this->get_notification_html()
            . $this->roster->get_html()
            . $this->get_submit_html()
            . '</div>';
    }

    private function get_submit_html () {
        $tpl = <<<HTML

<div class="tablenav bottom">
  <div class="alignleft actions">
    <input type="button" id="roster_save" class="button button-primary action" value="Save timetable">
    &nbsp;
    <span style="line-height: 35px;"><em>Last modified: %s</em></span>
  </div>
  <div class="tablenav-pages one-page">
    <span class="displaying-num">%s roster(s)</span>
  </div>
</div>

HTML;
        $audit = $this->audit->get_latest_audit();
        return sprintf(
            $tpl,
            date_i18n('Y/m/d g:i:s', strtotime($audit->modified_at), 0),
            count($this->people)
        );
    }

    private function get_people () {
        $args = [
            'numberposts' => -1,
            'orderby' => 'post_name',
        ];
        if ($this->options['people_category']) {
            $people_cat = get_category_by_path($this->options['people_category']);
            $args['category'] = $people_cat->cat_ID;
        }
        $people = get_posts($args);
        return array_map(function ($p) {
            return new Person($p);
        }, $people);
    }

    private function get_weekdays () {
        $weekdays_cat = get_category_by_path($this->options['weekday_category']);
        return array_map(
            function ($t) {
                return get_category($t);
            },
            get_term_children($weekdays_cat->term_id, $weekdays_cat->taxonomy));
    }

    public function save_roster ($roster) {
        $roster = str_replace('\"', '"', $_POST['roster']);
        $roster = json_decode($roster, true);
        $this->roster->save($roster);
        $this->audit->audit();
        echo "success";
        wp_die();
    }
}

?>