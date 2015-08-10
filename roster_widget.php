<?php
class RosterWidget extends WP_Widget {
    function __construct () {
        parent::__construct(
            'roster_widget',
            __('Roster', 'text_domain'),
            array('description' => __('Roster Widget', 'text_domain'),)
        );
    }

    public function widget ($args, $instance) {
        echo $args['before_widget'];
        if (!empty($instance['title'])) {
            echo $args['before_title']
                . apply_filter('widget_title', $instance['title'])
                . $args['after_title'];
        }
        echo __('Hello, World!', 'text_domain');
        echo $args['after_widget'];
    }

    public function form ($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('New title', 'text_domain');
        ?>
        <p>
        <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e("Title:"); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
               name="<?php echo $this->get_field_name('title'); ?>" type="text"
               value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }

    public function update($new_inst, $old_inst) {
        $inst = array();
        $inst['title'] = (!empty($new_inst['title'])) ? strip_tags($new_inst['title']) : '';
        return $inst;
    }

    public function get_field_name ($field) {
        return 'roster_' . $field . '_name';
    }

    public function get_field_id ($field) {
        return 'roster_' . $field;
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