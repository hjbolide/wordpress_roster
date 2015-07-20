<?php

class Person {

    private $reference;

    private $entity_cell_tpl = <<<TPL
<td>%s
<input type="hidden" name="pid" value="%s" />
</td>
TPL;

    private $weekday_cell_tpl = <<<TPL
<td>
<input type="checkbox" name="weekday_%s" value="%s" %s />
</td>
TPL;

    public function __construct($ref) {
        $this->reference = $ref;
    }

    public function get_row_html ($weekdays) {
        return '<tr>' . $this->get_entity_cell_html() . $this->get_roster_html($weekdays) . '</tr>';
    }

    private function get_roster_html ($weekdays) {
        $html = '';
        $categories = wp_get_post_categories($this->reference->ID);
        foreach ($weekdays as $w) {
            $checked = '';
            if (in_array($w->term_id, $categories)) {
                $checked = 'checked="checked"';
            }
            $html .= sprintf($this->weekday_cell_tpl, $w->term_id, $w->term_id, $checked);
        }
        return $html;
    }

    private function get_entity_cell_html () {
        return sprintf($this->entity_cell_tpl, $this->reference->post_name, $this->reference->ID);
    }

    public function save ($params) {
    }
}


class Roster {

    private $people;
    private $weekdays;

    private $table_header_cell_tpl = <<<TPL
<th>%s</th>
TPL;

    public function __construct ($weekdays, $people) {
        $this->weekdays = $weekdays;
        $this->people = $people;
    }

    public function get_html () {
        return '<table>' . $this->get_table_header() . $this->get_table_body() . '</table>';
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
}


class RosterAdminController {

    private $roster;

    public function __construct () {
    }

    public function execute () {
        $weekdays = $this->get_weekdays();
        $people = $this->get_people();
        $this->roster = new Roster($weekdays, $people);
        $this->output();
    }

    private function output () {
        echo $this->roster->get_html();
    }

    private function get_people () {
        $people_cat = get_category_by_path('people');
        $people = get_posts(array(
            'orderby' => 'post_name',
            'category' => $people_cat->cat_ID
        ));
        return array_map(function ($p) {
            return new Person($p);
        }, $people);
    }

    private function get_weekdays () {
        $weekdays_cat = get_category_by_path('weekdays');
        return array_map(
            function ($t) {
                return get_category($t);
            },
            get_term_children($weekdays_cat->term_id, $weekdays_cat->taxonomy));
    }
}

$controller = new RosterAdminController();
$controller->execute();

?>