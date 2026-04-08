<?php
if (! defined('ABSPATH')) exit;

class SB_Accommodation_Database
{

    public static function get_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'accommodation_bookings';
    }

    public static function install()
    {
        global $wpdb;
        $table           = self::get_table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            room_type_id    BIGINT(20) UNSIGNED NOT NULL,
            check_in_date   DATE NOT NULL,
            check_out_date  DATE NOT NULL,
            occupant_count  INT(11) NOT NULL DEFAULT 1,
            guest_name      VARCHAR(100) NOT NULL DEFAULT '',
            guest_email     VARCHAR(200) NOT NULL DEFAULT '',
            guest_phone     VARCHAR(50) NOT NULL DEFAULT '',
            total_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            stripe_pi_id    VARCHAR(200) NOT NULL DEFAULT '',
            stripe_status   VARCHAR(50) NOT NULL DEFAULT 'pending',
            booking_status  VARCHAR(50) NOT NULL DEFAULT 'pending',
            notes           TEXT NOT NULL DEFAULT '',
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY room_dates (room_type_id, check_in_date, check_out_date),
            KEY guest_email (guest_email),
            KEY booking_status (booking_status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option('sb_acc_db_version', SB_DB_VER);
    }

    /**
     * Check if room is available for date range
     */
    public static function check_availability($room_id, $check_in, $check_out)
    {
        global $wpdb;
        $table = self::get_table();

        // Treat pending bookings older than 30 mins as cancelled
        $conflicts = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE room_type_id = %d 
             AND (
                booking_status = 'confirmed' 
                OR booking_status = 'pending'
             )
             AND (
                check_in_date < %s AND check_out_date > %s
             )",
            $room_id,
            $check_out,
            $check_in
        ));

        return $conflicts === 0;
    }

    /**
     * Get booked dates for a room
     */
    public static function get_booked_dates($room_id)
    {
        global $wpdb;
        $table = self::get_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT check_in_date, check_out_date, booking_status FROM {$table}
             WHERE room_type_id = %d 
             AND (
                booking_status = 'confirmed' 
                OR booking_status = 'pending'
             )
             ORDER BY check_in_date ASC",
            $room_id
        ), ARRAY_A);
    }

    /**
     * Create a booking record
     */
    public static function create_booking($data)
    {
        global $wpdb;
        $table = self::get_table();

        $result = $wpdb->insert($table, [
            'room_type_id'   => absint($data['room_type_id']),
            'check_in_date'  => sanitize_text_field($data['check_in_date']),
            'check_out_date' => sanitize_text_field($data['check_out_date']),
            'occupant_count' => absint($data['occupant_count']),
            'guest_name'     => sanitize_text_field($data['guest_name']),
            'guest_email'    => sanitize_email($data['guest_email']),
            'guest_phone'    => sanitize_text_field($data['guest_phone']),
            'total_amount'   => floatval($data['total_amount']),
            'stripe_pi_id'   => sanitize_text_field($data['stripe_pi_id'] ?? ''),
            'stripe_status'  => sanitize_text_field($data['stripe_status'] ?? 'pending'),
            'booking_status' => sanitize_text_field($data['booking_status'] ?? 'pending'),
            'notes'          => sanitize_textarea_field($data['notes'] ?? ''),
        ], [
            '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s'
        ]);

        if ($result === false) {
            error_log('Accommodation Booking DB Error: ' . $wpdb->last_error);
            return 0;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update booking status
     */
    public static function update_booking_status($booking_id, $status, $stripe_status = '')
    {
        global $wpdb;
        $table = self::get_table();

        $update = ['booking_status' => $status];
        $format = ['%s'];

        if ($stripe_status) {
            $update['stripe_status'] = $stripe_status;
            $format[] = '%s';
        }

        $wpdb->update($table, $update, ['id' => $booking_id], $format, ['%d']);
    }

    /**
     * Get booking by ID
     */
    public static function get_booking($booking_id)
    {
        global $wpdb;
        $table = self::get_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $booking_id
        ));
    }

    /**
     * Get all bookings for a room
     */
    public static function get_room_bookings($room_id, $limit = 100)
    {
        global $wpdb;
        $table = self::get_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE room_type_id = %d
             ORDER BY check_in_date DESC
             LIMIT %d",
            $room_id,
            $limit
        ), ARRAY_A);
    }

    /**
     * Get all bookings (admin view)
     */
    public static function get_all_bookings($args = [])
    {
        global $wpdb;
        $table = self::get_table();

        $limit  = intval($args['per_page'] ?? 100);
        $page   = max(1, intval($args['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ), ARRAY_A);
    }

    /**
     * Get total booking count
     */
    public static function get_booking_count()
    {
        global $wpdb;
        $table = self::get_table();
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Calculate number of nights
     */
    public static function calculate_nights($check_in, $check_out)
    {
        $in  = strtotime($check_in);
        $out = strtotime($check_out);
        // Use round to handle DST changes (some days are 23 or 25 hours)
        return max(1, intval(round(($out - $in) / 86400)));
    }

    /**
     * Delete a booking
     */
    public static function delete_booking($booking_id)
    {
        global $wpdb;
        $table = self::get_table();
        return $wpdb->delete($table, ['id' => absint($booking_id)], ['%d']);
    }
}
