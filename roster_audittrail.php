<?php

class RosterAuditTrail {

    private $table_name = 'roster_audit';

    public function audit () {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . $this->table_name,
            array('modified_at' => current_time('mysql', 1))
        );
    }

    public function get_latest_audit () {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        return $wpdb->get_row("SELECT * from $table_name ORDER BY modified_at DESC LIMIT 1");
    }

}

?>