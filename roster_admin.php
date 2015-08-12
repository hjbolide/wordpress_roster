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

    public function __construct($ref) {
        $this->reference = $ref;
        $this->categories = wp_get_post_categories($this->reference->ID);
    }

    public function html ($weekdays, $index=0) {
    ?>
        <tr>
            <?php $this->entity_cell_html(); ?>
            <?php $this->roster_html($weekdays); ?>
        </tr>
    <?php
    }

    private function roster_html ($weekdays) {
        foreach ($weekdays as $weekday) {
            $checked = '';
            if (in_array($weekday->term_id, $this->categories)) {
                $checked = 'checked="checked"';
            }
    ?>
            <td style="text-align: center;">
                <input type="checkbox"
                       name="weekday_<?= $weekday->term_id ?>"
                       value="<?= $weekday->term_id ?>"
                       <?= $checked ?> />
            </td>
    <?php
        }
    }

    private function entity_cell_html () {
    ?>
        <td class="column-description desc">
            <strong><?= $this->reference->post_title; ?></strong>
            <input type="hidden" name="pid" value="<?= $this->reference->ID ?>" />
        </td>
    <?php
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

    public function __construct ($weekdays, $people) {
        $this->weekdays = $weekdays;
        $this->people = $people;
    }

    public function html () {
    ?>
        <table id="roster_table" class="wp-list-table widefat striped">
            <?php $this->table_header(); ?>
            <?php $this->table_body(); ?>
        </table>
    <?php
    }

    private function table_header () {
    ?>
        <thead>
            <tr>
                <th></th>
                <?php foreach ($this->weekdays as $w) { ?>
                <th scope="col" class="manage-column column-description" style="text-align: center;">
                <?= $w->name ?>
                </th>
                <?php } ?>
            </tr>
        </thead>
    <?php
    }

    private function table_body () {
    ?>
        <tbody>
    <?php foreach ($this->people as $p) {
        $p->html($this->weekdays);
    } ?>
        </tbody>
    <?php
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

    protected function header () {
    ?>
        <h2>Timetable Edit</h2>
    <?php
    }

    protected function notification () {
    ?>
        <div id="message" class="updated notice" style="display: none; position: fixed; top: 100px; z-index: 99999;">
            <p>Roster <strong>updated</strong>.</p>
        </div>
    <?php
    }

    protected function output () {
    ?>
        <div class="wrap">
            <?php $this->header(); ?>
            <?php $this->notification(); ?>
            <?php $this->roster->html(); ?>
            <?php $this->submit_html(); ?>
        </div>
    <?php
    }

    protected function submit_html () {
        $audit = $this->audit->get_latest_audit();
    ?>

        <div class="tablenav bottom">
          <div class="alignleft actions">
            <input type="button" id="roster_save" class="button button-primary action" value="Save timetable">
            &nbsp;
            <span style="line-height: 35px;">
                <em>Last modified: <?= date_i18n('Y/m/d g:i:s', strtotime($audit->modified_at), 0) ?></em>
            </span>
          </div>
          <div class="tablenav-pages one-page">
            <span class="displaying-num"><?= count($this->people) ?> roster(s)</span>
          </div>
        </div>
    <?php
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

    public function html () {
    }
}


class NGPerson extends Person {

    public function is_checked($cat) {
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

    public function html($cat, $index=0) {
        $is_checked = $this->is_checked($cat);
        $thumbnail_id = get_post_thumbnail_id($this->reference->ID);
        $thumbnail_url = wp_get_attachment_image_src($thumbnail_id, 'thumbnail');
        $id = $this->reference->ID;
        $title = strtoupper($this->reference->post_title);
        if (!$thumbnail_url) {
            $thumbnail_url = plugins_url('assets/images/no_photo.jpg', __FILE__);
        } else {
            $thumbnail_url = $thumbnail_url[0];
        }
    ?>
        <div class="person-grid" data-edik-elem="<?= $id . ':' . esc_attr($title) ?>" data-edik-elem-type="people">
          <input type="checkbox" data-edik-elem="<?= $title ?>"
                 data-edik-elem-title="<?= $id ?>"
                 data-edik-elem-type="person" class="on"
                 <?= $is_checked ? 'checked="checked"' : '' ?>/>
          <img data-edik-elem="overlay" class="overlay"
               src="<?= plugins_url('assets/images/on.png', __FILE__) ?>" />
          <img data-edik-elem="gthumb" class="gthumb"
               src="<?= $thumbnail_url ?>">
          <div data-edik-elem="name" class="name" >
            <h4><?= $this->reference->post_title ?></h4>
          </div>
        </div>
    <?php
    }
}


class NGRoster extends Roster { // NGRoster

    const NUMBER_OF_DAYS = 7;

    public function html() {
    ?>
        <div id="side-sortables" class="accordion-container">
            <ul class="outer-border">
                <?php
                    $today = new DateTime();
                    for ($i = 0; $i < self::NUMBER_OF_DAYS; $i += 1) {
                        if ($i != 0) {
                            $today->add(new DateInterval('P1D'));
                        }
                        $this->weekday_html($today, $i==0);
                    }
                ?>
            </ul>
        </div>
    <?php
    }

    /**
     * @param DateTime $today
     * @param Boolean $open
     * @return string
     */
    protected function weekday_html ($today, $open) {
        $title = $today->format("l (j/M/Y)");
        $day_of_week = $today->format("N");
        $cat = $this->weekdays[$day_of_week - 1];
    ?>
        <li class="control-section accordion-section <?= $open ? 'open' : '' ?>"
            id="<?= $today->getTimestamp() ?>">
            <h3 class="accordion-section-title hndle" tabindex="0" title="<?= $title ?>">
                <?= $title ?>
            </h3>
            <div class="accordion-section-content">
                <div class="inside">
                    <article>
                        <div class="filter">
                            <input type="text" data-edik-elem="search" class="search" />
                        </div>
                        <div data-edik-elem="people-grid">
                            <?php $this->people_html($cat); ?>
                        </div>
                    </article>
                </div>
            </div>
        </li>
    <?php
    }

    protected function people_html($cat) {
        foreach ($this->people as $p) {
            /**
             * @var NGPerson $p
             */
            $p->html($cat);
        }
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


class WidgetPerson extends NGPerson {

    public function html($cat, $index=0) {
        $is_checked = $this->is_checked($cat);
        if (!$is_checked) {
            return;
        }
        $thumbnail_id = get_post_thumbnail_id($this->reference->ID);
        $thumbnail_url = wp_get_attachment_image_src($thumbnail_id, 'thumbnail');
        $id = $this->reference->ID;
        $title = strtoupper($this->reference->post_title);
        if (!$thumbnail_url) {
            $thumbnail_url = plugins_url('assets/images/no_photo.jpg', __FILE__);
        } else {
            $thumbnail_url = $thumbnail_url[0];
        }
        $post_url = get_permalink($id);
        ?>
        <article class="slide-entry flex_column post-entry post-entry-<?= $id ?> slide-entry-overview
                        slide-loop-<?= $index ?>
                        slide-parity-<?= ($index + 1) % 2 == 0 ? 'even' : 'odd' ?>
                        av_one_fifth <?= $index % 5 == 0 ? 'first' : '' ?> fake-thumbnail"
                 itemscope="itemscope" itemtype="https://schema.org/BlogPosting" itemprop="blogPost">
            <a href="<?= $post_url ?>" data-rel="slide-1" class="slide-image" title="">
                <img width="300" height="420" src="<?= $thumbnail_url ?>"
                     class="attachment-portfolio wp-post-image" alt="image">
                <span class="image-overlay overlay-type-extern">
                    <span class="image-overlay-inside"></span>
                </span>
            </a>
            <div class="slide-content">
                <header class="entry-content-header">
                    <h3 class="slide-entry-title entry-title" itemprop="headline">
                        <a href="<?= $post_url ?>" title="<?= $title ?>"><?= $title?></a>
                    </h3>
                </header>
            </div>
            <footer class="entry-footer"></footer>
        </article>
        <?php
    }
}


class WidgetRoster extends NGRoster {
    public function html() {
        ?>
        <div id="side-sortables" class="accordion-container">
            <ul class="outer-border">
                <?php
                $today = new DateTime();
                for ($i = 0; $i < self::NUMBER_OF_DAYS; $i += 1) {
                    if ($i != 0) {
                        $today->add(new DateInterval('P1D'));
                    }
                    $this->weekday_html($today, $i==0);
                }
                ?>
            </ul>
        </div>
        <?php
    }

    protected function people_html($cat) {
        $index = 0;
        $need_to_close = false;
        foreach ($this->people as $p) {
            /**
             * @var WidgetPerson $p
             */
            if (!$p->is_checked($cat)) {
                continue;
            }
            if ($index % 5 == 0) {
            ?>
                <div class="slide-entry-wrap">
            <?php
                $need_to_close = true;
            }
            $p->html($cat, $index++);
            if ($index % 5 == 0 && $index != 0) {
            ?>
                </div>
            <?php
                $need_to_close = false;
            }
        }
        if ($need_to_close) {
        ?>
            </div>
        <?php
        }
    }

    /**
     * @param DateTime $today
     * @param Boolean $open
     * @return string
     */
    protected function weekday_html ($today, $open) {
        $title = $today->format("l (j/M/Y)");
        $day_of_week = $today->format("N");
        $cat = $this->weekdays[$day_of_week - 1];
    ?>
        <div class="flex_column av_one_full first avia-builder-el-3  el_after_av_one_full
                    el_before_av_one_full  column-top-margin">
            <section class="av_textblock_section" itemscope="itemscope"
                     itemtype="https://schema.org/CreativeWork">
                <div class="avia_textblock " itemprop="text">
                    <h3 style="text-align: left; color: #fff; background: rgba(0,0,0,0.7); padding: 8px 0 8px 15px;border-radius: 8px;">
                        <?= $title ?>
                    </h3>
                </div>
            </section>
            <div data-autoplay="" data-interval="5" data-animation="fade" data-show_slide_delay="90"
                 class="avia-content-slider avia-content-grid-active avia-content-slider1 avia-content-slider-odd
                        avia-builder-el-5  el_after_av_textblock  avia-builder-el-last " itemscope="itemscope"
                 itemtype="https://schema.org/Blog">
                <div class="avia-content-slider-inner">
                    <?php $this->people_html($cat); ?>
                </div>
            </div>
        </div>
    <?php
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