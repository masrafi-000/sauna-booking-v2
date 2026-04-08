<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Database {

    public static function get_table() {
        global $wpdb;
        return $wpdb->prefix . 'sauna_bookings';
    }

    public static function install() {
        global $wpdb;
        $table      = self::get_table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id      BIGINT(20) UNSIGNED NOT NULL,
            booking_date    DATE           NOT NULL,
            time_slot_start VARCHAR(10)    NOT NULL,
            time_slot_end   VARCHAR(10)    NOT NULL,
            seats_booked    INT(11)        NOT NULL DEFAULT 1,
            first_name      VARCHAR(100)   NOT NULL DEFAULT '',
            last_name       VARCHAR(100)   NOT NULL DEFAULT '',
            email           VARCHAR(200)   NOT NULL DEFAULT '',
            phone           VARCHAR(50)    NOT NULL DEFAULT '',
            amount          DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
            stripe_pi_id    VARCHAR(200)   NOT NULL DEFAULT '',
            stripe_status   VARCHAR(50)    NOT NULL DEFAULT 'pending',
            status          VARCHAR(50)    NOT NULL DEFAULT 'pending',
            notes           TEXT           NOT NULL DEFAULT '',
            created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_date (product_id, booking_date),
            KEY email (email)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        update_option( 'sb_db_version', SB_DB_VER );
    }

    /**
     * Get booked seats for a product/date/slot.
     */
    public static function get_booked_seats( $product_id, $date, $slot_start ) {
        global $wpdb;
        $table = self::get_table();
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(seats_booked),0) FROM {$table}
             WHERE product_id=%d AND booking_date=%s AND time_slot_start=%s AND status != 'cancelled'",
            $product_id, $date, $slot_start
        ) );
    }

    /**
     * Create a booking record.
     */
    public static function create_booking( $data ) {
        global $wpdb;
        $table = self::get_table();
        $wpdb->insert( $table, [
            'product_id'      => absint( $data['product_id'] ),
            'booking_date'    => sanitize_text_field( $data['booking_date'] ),
            'time_slot_start' => sanitize_text_field( $data['time_slot_start'] ),
            'time_slot_end'   => sanitize_text_field( $data['time_slot_end'] ),
            'seats_booked'    => absint( $data['seats_booked'] ),
            'first_name'      => sanitize_text_field( $data['first_name'] ),
            'last_name'       => sanitize_text_field( $data['last_name'] ),
            'email'           => sanitize_email( $data['email'] ),
            'phone'           => sanitize_text_field( $data['phone'] ),
            'amount'          => floatval( $data['amount'] ),
            'stripe_pi_id'    => sanitize_text_field( $data['stripe_pi_id'] ?? '' ),
            'stripe_status'   => sanitize_text_field( $data['stripe_status'] ?? 'pending' ),
            'status'          => sanitize_text_field( $data['status'] ?? 'pending' ),
            'notes'           => sanitize_textarea_field( $data['notes'] ?? '' ),
        ], [
            '%d','%s','%s','%s','%d','%s','%s','%s','%s','%f','%s','%s','%s','%s'
        ] );
        return $wpdb->insert_id;
    }

    /**
     * Update stripe payment status.
     */
    public static function update_booking_status( $booking_id, $status, $stripe_status = '' ) {
        global $wpdb;
        $table = self::get_table();
        $update = [ 'status' => $status ];
        $format = [ '%s' ];
        if ( $stripe_status ) {
            $update['stripe_status'] = $stripe_status;
            $format[] = '%s';
        }
        $wpdb->update( $table, $update, [ 'id' => $booking_id ], $format, [ '%d' ] );
    }

    /**
     * Get all bookings (admin).
     */
    public static function get_all_bookings( $args = [] ) {
        global $wpdb;
        $table = self::get_table();

        $where   = 'WHERE 1=1';
        $orderby = 'ORDER BY created_at DESC';
        $limit   = '';

        if ( ! empty( $args['product_id'] ) ) {
            $where .= $wpdb->prepare( ' AND product_id=%d', $args['product_id'] );
        }
        if ( ! empty( $args['date'] ) ) {
            $where .= $wpdb->prepare( ' AND booking_date=%s', $args['date'] );
        }
        if ( ! empty( $args['status'] ) ) {
            $where .= $wpdb->prepare( ' AND status=%s', $args['status'] );
        }
        if ( ! empty( $args['per_page'] ) ) {
            $page   = max( 1, intval( $args['page'] ?? 1 ) );
            $offset = ( $page - 1 ) * intval( $args['per_page'] );
            $limit  = $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['per_page'], $offset );
        }

        return $wpdb->get_results( "SELECT * FROM {$table} {$where} {$orderby} {$limit}" );
    }

    public static function get_booking_count( $args = [] ) {
        global $wpdb;
        $table = self::get_table();
        $where = 'WHERE 1=1';
        if ( ! empty( $args['product_id'] ) ) {
            $where .= $wpdb->prepare( ' AND product_id=%d', $args['product_id'] );
        }
        if ( ! empty( $args['status'] ) ) {
            $where .= $wpdb->prepare( ' AND status=%s', $args['status'] );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
    }

    public static function get_booking( $id ) {
        global $wpdb;
        $table = self::get_table();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ) );
    }
}
