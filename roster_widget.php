<?php

require_once('roster_admin.php');

class RosterWidget extends WP_Widget {
    /**
     * Register widget with WordPress.
     */
    function __construct() {
        parent::__construct(
            'roster_widget', // Base ID
            __( 'Roster', 'text_domain' ), // Name
            array( 'description' => __( 'Roster Widget', 'text_domain' ), ) // Args
        );
    }

    protected function get_weekdays ($instance) {
        global $week_mapping;
        $weekdays_cat = get_category_by_path(
            isset($instance['weekday_category']) ? $instance['weekday_category'] : 'weekdays');
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

    protected function get_people ($instance) {
        $args = [
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ];
        if (isset($instance['people_category'])) {
            $people_cat = get_category_by_path($instance['people_category']);
            $args['category'] = $people_cat->cat_ID;
        }
        $people = get_posts($args);
        return array_map(function ($p) {
            return new WidgetPerson($p);
        }, $people);
    }

    /**
     * Front-end display of widget.
     *
     * @see WP_Widget::widget()
     *
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     */
    public function widget( $args, $instance ) {
        echo $args['before_widget'];
        $roster = new WidgetRoster($this->get_weekdays($instance), $this->get_people($instance));
        $roster->html();
        echo __( 'Hello, World!', 'text_domain' );
        echo $args['after_widget'];
    }

    /**
     * Sanitize widget form values as they are saved.
     *
     * @see WP_Widget::update()
     *
     * @param array $new_instance Values just sent to be saved.
     * @param array $old_instance Previously saved values from database.
     *
     * @return array Updated safe values to be saved.
     */
    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';

        return $instance;
    }
}


class RosterWidgetController {
    public function __construct () {
        add_action('widgets_init', array($this, 'init'));
    }

    public function init () {
        register_widget('RosterWidget');
    }
}
?>