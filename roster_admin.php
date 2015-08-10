<?php

require_once('roster_audittrail.php');

global $week_mapping;
$week_mapping = array(
    'MONDAY' => 0,
    'TUESDAY' => 1,
    'WEDNESDAY' => 2,
    'THURSDAY' => 3,
    'FRIDAY' => 4,
    'SATURDAY' => 5,
    'SUNDAY' => 6
);


function __LOG ($message, $cease=false, $dump=false) {
    if (!WP_DEBUG) {
        return;
    }
    echo "<pre>";
    if ($dump) {
        var_dump($message);
    } else {
        print_r($message);
    }
    echo "</pre>";
    if ($cease) {
        wp_die();
    }
}


class Person {

    /**
     * @var WP_Post
     */
    protected $reference;

    /**
     * @var array
     */
    protected $categories;

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

    public function get_html ($weekdays) {
        return '<tr>' . $this->get_entity_cell_html() . $this->get_roster_html($weekdays) . '</tr>';
    }

    private function get_roster_html ($weekdays) {
        $html = '';
        foreach ($weekdays as $weekday) {
            $checked = '';
            if (in_array($weekday->term_id, $this->categories)) {
                $checked = 'checked="checked"';
            }
            $html .= sprintf($this->weekday_cell_tpl, $weekday->term_id, $weekday->term_id, $checked);
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
        foreach ($this->categories as $cat) {
            if (!array_key_exists($cat, $weekdays)) {
                array_push($categories, $cat);
            }
        }
        wp_set_post_categories($this->reference->ID, $categories, false);
    }
}


class Roster {

    /**
     * @var Person[]
     */
    protected $people;   // all the people posts

    /**
     * @var array
     */
    protected $weekdays; // weekday categories

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
            $html .= $p->get_html($this->weekdays);
        }
        return $html.'</tbody>';
    }

    /**
     * @return array(int => Person)
     */
    private function get_people_in_assoc_array () {
        $result = array();
        foreach ($this->people as $person) {
            $result[$person->get_id()] = $person;
        }
        return $result;
    }

    public function save ($roster) {
        $people_dict = $this->get_people_in_assoc_array();
        foreach ($roster as $pid => $weekdays) {
            /**
             * @var $person Person
             */
            $person = $people_dict[$pid];
            $person->save_roster($weekdays);
        }
    }
}


class RosterAdminController {

    /**
     * @var Roster
     */
    protected $roster;

    /**
     * @var array
     */
    protected $weekdays;

    /**
     * @var Person[]
     */
    protected $people;

    /**
     * @var array( string => string )
     */
    protected $options;

    /**
     * @var RosterAuditTrail
     */
    protected $audit;

    public function __construct () {
        add_action('admin_menu', array($this, 'add_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        $this->add_ajax_action();
    }

    protected function add_ajax_action () {
        add_action('wp_ajax_save_roster', array($this, 'save_roster'));
    }

    public function init () {
        $this->options = get_option('roster_options');
        if (!$this->options['weekday_category']) {
            $this->options['weekday_category'] = 'timetable';
        }
        $this->weekdays = $this->get_weekdays();
        $this->people_init();
        $this->roster_init();
        $this->audit = new RosterAuditTrail();
    }

    protected function roster_init () {
        $this->roster = new Roster($this->weekdays, $this->people);
    }

    public function page_init () {
        wp_register_script('floatthead_script', plugins_url('assets/js/jquery.floatThead.min.js', __FILE__), array('jquery'));
        wp_register_script('roster_script', plugins_url('assets/js/roster.js', __FILE__), array('jquery', 'floatthead_script'));
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
            'Timetable', 'Timetable', 'manage_options', 'timetable');
        add_submenu_page('timetable', 'By People', 'By People', 'manage_options', 'timetable', array($this, 'execute'));
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

    protected function get_header () {
        return '<h2>Timetable Edit</h2>';
    }

    protected function get_notification_html () {
        return <<<HTML

<div id="message" class="updated notice" style="display: none; position: fixed; top: 100px; z-index: 99999;">
  <p>Roster <strong>updated</strong>.</p>
</div>

HTML;
    }

    protected function output () {
        echo '<div class="wrap">'
            . $this->get_header()
            . $this->get_notification_html()
            . $this->roster->get_html()
            . $this->get_submit_html()
            . '</div>';
    }

    protected function get_submit_html () {
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

    protected function people_init () {
        $args = [
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ];
        if ($this->options['people_category']) {
            $people_cat = get_category_by_path($this->options['people_category']);
            $args['category'] = $people_cat->cat_ID;
        }
        $people = get_posts($args);
        $this->people = $this->get_people($people);
    }

    /**
     * @param WP_Post[] $people
     * @return Person[]
     */
    protected function get_people ($people) {
        return array_map(function ($p) {
            return new Person($p);
        }, $people);
    }

    protected function get_weekdays () {
        global $week_mapping;
        $weekdays_cat = get_category_by_path($this->options['weekday_category']);
        $weekdays = array_map(
            function ($t) {
                return get_category($t);
            },
            get_term_children($weekdays_cat->term_id, $weekdays_cat->taxonomy));
        $result = [];
        foreach ($weekdays as $weekday) {
            $index = $week_mapping[strtoupper($weekday->cat_name)];
            $result[$index] = $weekday;
        }
        ksort($result);
        return $result;
    }

    public function save_roster () {
        $roster = str_replace('\"', '"', $_POST['roster']);
        $roster = json_decode($roster, true);
        $this->roster->save($roster);
        var_dump($this->roster);
        $this->audit->audit();
        echo "success";
        ob_flush();
        wp_die();
    }
}


class NGWeekday { // NGWeekday

    /**
     * @var NGPerson[]
     */
    protected $people;

    public function __construct ($people) {
        $this->people = $people;
    }

    public function get_html () {
    }
}


class NGPerson extends Person {

    private function is_checked($cat) {
        global $week_mapping;
        $today = new DateTime();
        $date_str = $today->format('Y-m-d');
        global $wpdb;
        $table = $wpdb->prefix . 'ngroster';
        $pid = $this->reference->ID;
        $results = $wpdb->get_results(
            "
            SELECT roster_date, working
            FROM $table
            WHERE pid=$pid
            AND roster_date >= '$date_str'
            "
        );

        foreach ($results as $r) {
            $w = DateTime::createFromFormat('Y-m-d', $r->roster_date)->format('N') - 1;
            if ($w == $week_mapping[strtoupper($cat->cat_name)]) {
                return boolval($r->working);
            }
        }
        return in_array($cat->term_id, $this->categories);
    }

    public function get_html($cat) {
        $html = <<<HTML
<div class="person-grid" data-edik-elem="%s" data-edik-elem-type="people">
  <input type="checkbox" data-edik-elem="%s" data-edik-elem-title="%s" data-edik-elem-type="person" class="on" %s/>
  <img data-edik-elem="overlay" class="overlay" src="%s" />
  <img data-edik-elem="gthumb" class="gthumb" src="%s">
  <div data-edik-elem="name" class="name" >
    <h4>%s</h4>
  </div>
</div>
HTML;
        $is_checked = $this->is_checked($cat);
        $thumbnail_id = get_post_thumbnail_id($this->reference->ID);
        $thumbnail_url = wp_get_attachment_url($thumbnail_id);
        $id = $this->reference->ID;
        $title = strtoupper($this->reference->post_title);
        if (!$thumbnail_url) {
            $thumbnail_url = plugins_url('assets/images/no_photo.jpg', __FILE__);
        }
        return sprintf(
            $html,
            $id . ':' . esc_attr($title),
            $title,
            $id,
            $is_checked ? 'checked="checked"' : '',
            plugins_url('assets/images/on.png', __FILE__),
            $thumbnail_url,
            $this->reference->post_title
        );
    }
}


class NGRoster extends Roster { // NGRoster

    const NUMBER_OF_DAYS = 7;

    public function get_html() {
        $today = new DateTime();
        $html = [
            '<div id="side-sortables" class="accordion-container">',
            '  <ul class="outer-border">'
        ];
        for ($i = 0; $i < self::NUMBER_OF_DAYS; $i += 1) {
            if ($i != 0) {
                $today->add(new DateInterval('P1D'));
            }
            $html[] = $this->get_weekday_html($today, $i==0);
        }
        $html[] = '</ul></div>';

        return implode("\n", $html);
    }

    /**
     * @param DateTime $today
     * @param Boolean $open
     * @return string
     */
    protected function get_weekday_html ($today, $open) {
        $title = $today->format("l (j/M/Y)");
        $day_of_week = $today->format("N");
        $cat = $this->weekdays[$day_of_week - 1];
        $html = [
            '<li class="control-section accordion-section '
              . ($open ? 'open' : '')
              . '" id="' . $today->getTimestamp() . '">',
            '  <h3 class="accordion-section-title hndle" tabindex="0" title="' . $title . '">',
            $title,
            '  </h3>',
            '  <div class="accordion-section-content">',
            '    <div class="inside">',
            '      <article>',
            '        <div class="filter">',
            '          <input type="text" data-edik-elem="search" class="search" />',
            '        </div>',
            '        <div data-edik-elem="people-grid">'
        ];

        $html[] = $this->get_people_html($cat);

        return implode("\n", array_merge($html, [
            '</div>', '</article>', '</div>', '</div>', '</li>'
        ]));
    }

    protected function get_people_html($cat) {
        $html = [];
        foreach ($this->people as $p) {
            /**
             * @var NGPerson $p
             */
            $html[] = $p->get_html($cat);
        }
        return implode("\n", $html);
    }

    public function save($roster) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $table = $prefix . 'ngroster';

        $wpdb->query('START TRANSACTION');
        foreach ($roster as $p => $weekdays) {
            foreach ($weekdays as $w => $working) {
                $pid = intval($p);
                $date = new DateTime();
                $date->setTimestamp($w);
                $date_str = $date->format('Y-m-d');
                if (!$wpdb->get_row("SELECT 1 FROM $table WHERE pid=$pid AND roster_date='$date_str'")) {
                    $wpdb->insert(
                        $table,
                        array(
                            'pid' => intval($p),
                            'roster_date' => $date->format('Y-m-d'),
                            'working' => $working
                        ));
                } else {
                    $wpdb->update(
                        $table,
                        array('working' => $working),
                        array('pid' => $pid, 'roster_date' => $date_str));
                }
            }
        }
        $wpdb->query('COMMIT');
    }

}


class NGRosterAdminController extends RosterAdminController {

    protected function roster_init () {
        $this->roster = new NGRoster($this->weekdays, $this->people);
    }

    public function page_init()
    {
        wp_register_script('ngroster_script', plugins_url('assets/js/ngroster.js', __FILE__), array('jquery'));
        wp_register_style('ngroster_style', plugins_url('assets/css/ngroster.css', __FILE__));
        $this->init();
    }

    public function enqueue_scripts () {
        wp_enqueue_style('ngroster_style');
        wp_enqueue_script('ngroster_script');
        wp_enqueue_script( 'accordion' );
        wp_localize_script('ngroster_script', 'NGRosterAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    }

    /**
     * @param WP_Post[] $people
     * @return NGPerson[]
     */
    protected function get_people($people) {
        return array_map(function ($p) {
            return new NGPerson($p);
        }, $people);
    }

    public function add_page()
    {
        add_submenu_page('timetable', 'By Date', 'By Date', 'read', 'by-date', array($this, 'execute'));
    }

    public function save_ngroster () {
        $roster = str_replace('\"', '"', $_POST['roster']);
        $roster = json_decode($roster, true);
        $this->roster->save($roster);
        var_dump($this->roster);
        $this->audit->audit();
        echo "success";
        ob_flush();
        wp_die();
    }

    protected function add_ajax_action()
    {
        add_action('wp_ajax_save_ngroster', array($this, 'save_ngroster'));
    }


}

?>