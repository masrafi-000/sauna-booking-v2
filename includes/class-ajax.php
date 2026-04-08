<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Ajax {

    public static function init() {
        $actions = [
            'sb_get_slots'           => [ __CLASS__, 'get_slots' ],
            'sb_submit_booking_query' => [ __CLASS__, 'submit_booking_query' ],
            'sb_confirm_booking'     => [ __CLASS__, 'confirm_booking' ],
        ];
        foreach ( $actions as $action => $callback ) {
            add_action( 'wp_ajax_' . $action,        $callback );
            add_action( 'wp_ajax_nopriv_' . $action, $callback );
        }
    }

    /* ── Verify nonce helper ─────────────────────────────────────────────────── */
    private static function verify() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sb_nonce' ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
        }
    }

    /* ── GET SLOTS ──────────────────────────────────────────────────────────── */
    public static function get_slots() {
        self::verify();

        $product_id = absint( $_POST['product_id'] ?? 0 );
        $date       = sanitize_text_field( $_POST['date'] ?? '' );

        if ( ! $product_id || ! $date ) {
            wp_send_json_error( [ 'message' => 'Invalid data.' ] );
        }

        // Validate date format
        if ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            wp_send_json_error( [ 'message' => 'Invalid date format.' ] );
        }

        $start_hour = intval( get_post_meta( $product_id, '_sb_start_hour',    true ) ?: 7 );
        $end_hour   = intval( get_post_meta( $product_id, '_sb_end_hour',      true ) ?: 22 );
        $duration   = intval( get_post_meta( $product_id, '_sb_slot_duration', true ) ?: 60 );
        $total_seats = intval( get_post_meta( $product_id, '_sb_seats',        true ) ?: 6 );

        $slots = [];
        $current = $start_hour * 60; // in minutes
        $end     = $end_hour   * 60;

        while ( $current + $duration <= $end ) {
            $slot_start = self::mins_to_time( $current );
            $slot_end   = self::mins_to_time( $current + $duration );
            $booked     = SB_Database::get_booked_seats( $product_id, $date, $slot_start );
            $available  = max( 0, $total_seats - $booked );

            $slots[] = [
                'start'     => $slot_start,
                'end'       => $slot_end,
                'booked'    => $booked,
                'available' => $available,
                'total'     => $total_seats,
                'label'     => self::format_time( $slot_start ),
            ];
            $current += $duration;
        }

        wp_send_json_success( [ 'slots' => $slots, 'date_label' => date( 'M j, Y', strtotime($date) ) ] );
    }

    /* ── SUBMIT BOOKING QUERY (Bypasses Stripe) ────────────────────────────── */
    public static function submit_booking_query() {
        self::verify();

        $product_id  = absint( $_POST['product_id']  ?? 0 );
        $date        = sanitize_text_field( $_POST['date']        ?? '' );
        $slot_start  = sanitize_text_field( $_POST['slot_start']  ?? '' );
        $slot_end    = sanitize_text_field( $_POST['slot_end']    ?? '' );
        $seats       = absint( $_POST['seats']  ?? 1 );
        $first_name  = sanitize_text_field( $_POST['first_name']  ?? '' );
        $last_name   = sanitize_text_field( $_POST['last_name']   ?? '' );
        $email       = sanitize_email( $_POST['email'] ?? '' );
        $phone       = sanitize_text_field( $_POST['phone'] ?? '' );
        $notes       = sanitize_textarea_field( $_POST['notes'] ?? '' );

        if ( ! $product_id || ! $date || ! $slot_start ) {
            wp_send_json_error( [ 'message' => 'Missing booking data.' ] );
        }

        // Re-check availability
        $total_seats = intval( get_post_meta( $product_id, '_sb_seats', true ) ?: 6 );
        $booked      = SB_Database::get_booked_seats( $product_id, $date, $slot_start );
        if ( $booked + $seats > $total_seats ) {
            wp_send_json_error( [ 'message' => 'Sorry, not enough seats available for this slot.' ] );
        }

        $price_each = floatval( get_post_meta( $product_id, '_sb_price', true ) ?: 0 );
        $amount     = $price_each * $seats;

        // Create booking as pending
        $booking_id = SB_Database::create_booking( [
            'product_id'      => $product_id,
            'booking_date'    => $date,
            'time_slot_start' => $slot_start,
            'time_slot_end'   => $slot_end,
            'seats_booked'    => $seats,
            'first_name'      => $first_name,
            'last_name'       => $last_name,
            'email'           => $email,
            'phone'           => $phone,
            'amount'          => $amount,
            'stripe_pi_id'    => 'manual_approval', // Placeholder since payment is removed for now
            'stripe_status'   => 'pending',
            'status'          => 'pending',
            'notes'           => $notes,
        ] );

        if ( $booking_id ) {
            $booking = SB_Database::get_booking( $booking_id );
            self::send_query_emails( $booking );
            wp_send_json_success( [
                'message'    => 'Your booking query has been sent! We will contact you soon.',
                'booking_id' => $booking_id
            ] );
        } else {
            wp_send_json_error( [ 'message' => 'Failed to save booking query. Please try again.' ] );
        }
    }

    // Keep for potential future use or backward compatibility
    public static function create_payment_intent() { self::submit_booking_query(); }

    /* ── CONFIRM BOOKING ────────────────────────────────────────────────────── */
    public static function confirm_booking() {
        self::verify();

        $booking_id    = absint( $_POST['booking_id'] ?? 0 );
        $pi_id         = sanitize_text_field( $_POST['payment_intent_id'] ?? '' );
        $stripe_status = sanitize_text_field( $_POST['payment_status'] ?? '' );

        if ( ! $booking_id || ! $pi_id ) {
            wp_send_json_error( [ 'message' => 'Invalid confirmation data.' ] );
        }

        // Check if booking exists and belongs to this payment intent (Fix IDOR)
        $booking = SB_Database::get_booking( $booking_id );
        if ( ! $booking || $booking->stripe_pi_id !== $pi_id ) {
            wp_send_json_error( [ 'message' => 'Invalid booking reference.' ] );
        }

        // Verify with Stripe
        $secret_key = get_option( 'sb_stripe_secret_key', '' );
        $response   = wp_remote_get( "https://api.stripe.com/v1/payment_intents/{$pi_id}", [
            'headers' => [ 'Authorization' => 'Bearer ' . $secret_key ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => 'Could not verify payment.' ] );
        }

        $pi = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $pi['status'] ) ) {
            wp_send_json_error( [ 'message' => 'Invalid payment intent.' ] );
        }

        $booking_status = ( $pi['status'] === 'succeeded' ) ? 'confirmed' : 'pending';
        SB_Database::update_booking_status( $booking_id, $booking_status, $pi['status'] );

        if ( $booking_status === 'confirmed' ) {
            // Send confirmation email
            if ( $booking ) {
                self::send_confirmation_email( $booking );
            }
        }

        wp_send_json_success( [
            'status'  => $booking_status,
            'message' => $booking_status === 'confirmed'
                ? 'Booking confirmed! Check your email for details.'
                : 'Payment is being processed.',
        ] );
    }

    /* ── Send Confirmation Email ────────────────────────────────────────────── */
    private static function send_confirmation_email( $booking ) {
        SB_Admin::send_status_update_email( $booking, 'confirmed' );
    }

    /* ── Send Query Emails ─────────────────────────────────────────────────── */
    private static function send_query_emails( $booking ) {
        $product_title = get_the_title( $booking->product_id );
        $currency      = get_option( 'sb_currency_symbol', '₱' );
        $site_name     = get_bloginfo( 'name' );

        $subject_guest = "Booking Query Received – {$product_title}";
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        $message_guest = "
        <html><body style='font-family:sans-serif;color:#333;'>
        <h2 style='color:#c97d30;'>Your Booking Request has been Successfully Sent</h2>
        <p>Hi {$booking->first_name},</p>
        <p>Thank you for your interest. We have received your booking request for <strong>{$product_title}</strong>. Your request is currently <strong>Pending</strong>, and we will contact you within 2/3 hours for confirmation.</p>
        <table style='border-collapse:collapse;width:100%;max-width:500px;'>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Date</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . date('l, F j, Y', strtotime($booking->booking_date)) . "</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Time</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$booking->time_slot_start} – {$booking->time_slot_end}</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Seats</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$booking->seats_booked}</td></tr>
        </table>
        <p style='margin-top:24px;'>Best regards,</p>
        <p><em>{$site_name}</em></p>
        </body></html>";

        wp_mail( $booking->email, $subject_guest, $message_guest, $headers );

        // Notify admin
        $admin_email = get_option('sb_admin_email') ?: get_option('admin_email');
        $admin_subject = "New Booking Query: {$product_title} – {$booking->first_name} {$booking->last_name}";
        $admin_message = "
        <html><body style='font-family:sans-serif;color:#333;'>
        <h2 style='color:#c97d30;'>New Booking Query Received</h2>
        <p>A new sauna booking query has been submitted and is waiting for your approval.</p>
        <table style='border-collapse:collapse;width:100%;max-width:500px;'>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Customer</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$booking->first_name} {$booking->last_name}</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Email</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$booking->email}</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Phone</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$booking->phone}</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Sauna</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$product_title}</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Date</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . date('l, F j, Y', strtotime($booking->booking_date)) . "</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Time</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$booking->time_slot_start} – {$booking->time_slot_end}</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Seats</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$booking->seats_booked}</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Notes</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$booking->notes}</td></tr>
        </table>
        <p style='margin-top:24px;'><a href='" . admin_url('edit.php?post_type=sauna_product&page=sb-bookings') . "' style='display:inline-block;padding:10px 20px;background:#c97d30;color:#fff;text-decoration:none;border-radius:4px;'>View Bookings in Panel</a></p>
        <p style='margin-top:24px;color:#666;font-size:13px;'>This is an automated notification from {$site_name}.</p>
        </body></html>";

        wp_mail( $admin_email, $admin_subject, $admin_message, $headers );
    }

    /* ── Helpers ─────────────────────────────────────────────────────────────── */
    private static function mins_to_time( $mins ) {
        $h = intdiv( $mins, 60 );
        $m = $mins % 60;
        return sprintf( '%02d:%02d', $h, $m );
    }

    private static function format_time( $time ) {
        return date( 'g:i A', strtotime( '2000-01-01 ' . $time ) );
    }
}
