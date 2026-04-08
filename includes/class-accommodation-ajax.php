<?php
if (! defined('ABSPATH')) exit;

class SB_Accommodation_Ajax
{

    public static function init()
    {
        $actions = [
            'acc_check_availability'     => [__CLASS__, 'check_availability'],
            'acc_submit_booking_query'   => [__CLASS__, 'submit_booking_query'],
            'acc_confirm_booking'        => [__CLASS__, 'confirm_booking'],
            'acc_get_booked_dates'       => [__CLASS__, 'get_booked_dates'],
        ];

        foreach ($actions as $action => $callback) {
            add_action('wp_ajax_' . $action,        $callback);
            add_action('wp_ajax_nopriv_' . $action, $callback);
        }
    }

    /**
     * Verify nonce helper
     */
    private static function verify()
    {
        if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'acc_nonce')) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
        }
    }

    /**
     * GET BOOKED DATES - Get all booked date ranges for a room
     */
    public static function get_booked_dates()
    {
        self::verify();

        $room_id = absint($_POST['room_id'] ?? 0);

        if (! $room_id) {
            wp_send_json_error(['message' => 'Invalid room ID.']);
        }

        $booked = SB_Accommodation_Database::get_booked_dates($room_id);
        wp_send_json_success(['booked_dates' => $booked]);
    }

    /**
     * CHECK AVAILABILITY - Verify if dates are available
     */
    public static function check_availability()
    {
        self::verify();

        $room_id    = absint($_POST['room_id'] ?? 0);
        $check_in   = sanitize_text_field($_POST['check_in'] ?? '');
        $check_out  = sanitize_text_field($_POST['check_out'] ?? '');

        if (! $room_id || ! $check_in || ! $check_out) {
            wp_send_json_error(['message' => 'Missing required data.']);
        }

        // Validate date format
        if (
            ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in) ||
            ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out)
        ) {
            wp_send_json_error(['message' => 'Invalid date format.']);
        }

        // Check if checkout is after checkin
        $in_time  = strtotime($check_in);
        $out_time = strtotime($check_out);

        if ($out_time <= $in_time) {
            wp_send_json_error(['message' => 'Check-out date must be after check-in date.']);
        }

        // Check availability
        $available = SB_Accommodation_Database::check_availability($room_id, $check_in, $check_out);

        if (! $available) {
            wp_send_json_error(['message' => 'Room is not available for selected dates.']);
        }

        $nights = SB_Accommodation_Database::calculate_nights($check_in, $check_out);
        wp_send_json_success([
            'available' => true,
            'nights'    => $nights,
            'check_in'  => $check_in,
            'check_out' => $check_out,
        ]);
    }

    /**
     * SUBMIT BOOKING QUERY - Bypasses Stripe and creates a pending request
     */
    public static function submit_booking_query()
    {
        self::verify();

        $room_id       = absint($_POST['room_id'] ?? 0);
        $check_in      = sanitize_text_field($_POST['check_in'] ?? '');
        $check_out     = sanitize_text_field($_POST['check_out'] ?? '');
        $occupants     = absint($_POST['occupants'] ?? 1);
        $guest_name    = sanitize_text_field($_POST['guest_name'] ?? '');
        $guest_email   = sanitize_email($_POST['guest_email'] ?? '');
        $guest_phone   = sanitize_text_field($_POST['guest_phone'] ?? '');
        $notes         = sanitize_textarea_field($_POST['notes'] ?? '');

        // Validate inputs
        if (!$room_id || !$check_in || !$check_out || !$guest_name || !$guest_email) {
            wp_send_json_error(['message' => 'Missing required booking data.']);
        }

        $max_allowed = intval(get_post_meta($room_id, '_sb_max_occupants', true) ?: 2);
        if ($occupants < 1 || $occupants > $max_allowed) {
            wp_send_json_error(['message' => sprintf('Invalid number of occupants. Maximum allowed: %d', $max_allowed)]);
        }

        // Verify dates
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out)) {
            wp_send_json_error(['message' => 'Invalid date format.']);
        }

        if (strtotime($check_out) <= strtotime($check_in)) {
            wp_send_json_error(['message' => 'Check-out must be after check-in.']);
        }

        // Check availability
        if (!SB_Accommodation_Database::check_availability($room_id, $check_in, $check_out)) {
            wp_send_json_error(['message' => 'Room is no longer available for these dates.']);
        }

        $price_per_night = floatval(get_post_meta($room_id, '_sb_price_per_night', true) ?: 0);
        $nights = SB_Accommodation_Database::calculate_nights($check_in, $check_out);
        $amount = $nights * $price_per_night;

        // Create booking as pending
        $booking_id = SB_Accommodation_Database::create_booking([
            'room_type_id'   => $room_id,
            'check_in_date'  => $check_in,
            'check_out_date' => $check_out,
            'occupant_count' => $occupants,
            'guest_name'     => $guest_name,
            'guest_email'    => $guest_email,
            'guest_phone'    => $guest_phone,
            'total_amount'   => $amount,
            'stripe_pi_id'   => 'manual_approval',
            'stripe_status'  => 'pending',
            'booking_status' => 'pending',
            'notes'          => $notes,
        ]);

        if ($booking_id) {
            $booking = SB_Accommodation_Database::get_booking($booking_id);
            self::send_query_emails($booking);
            wp_send_json_success([
                'message'    => 'Your reservation request has been sent! We will contact you soon.',
                'booking_id' => $booking_id,
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to create reservation request.'], 500);
        }
    }

    // Keep for potential future use or backward compatibility
    public static function create_payment_intent() { self::submit_booking_query(); }

    /**
     * CONFIRM BOOKING - Finalize booking after successful payment
     */
    public static function confirm_booking()
    {
        self::verify();

        $booking_id = absint($_POST['booking_id'] ?? 0);
        $pi_id      = sanitize_text_field($_POST['pi_id'] ?? '');

        if (! $booking_id || ! $pi_id) {
            wp_send_json_error(['message' => 'Missing booking information.']);
        }

        // Get booking
        $booking = SB_Accommodation_Database::get_booking($booking_id);
        if (! $booking) {
            wp_send_json_error(['message' => 'Booking not found.']);
        }

        // Verify payment intent ID matches
        if ($booking->stripe_pi_id !== $pi_id) {
            wp_send_json_error(['message' => 'Payment intent mismatch.']);
        }

        // Update booking status to confirmed
        SB_Accommodation_Database::update_booking_status($booking_id, 'confirmed', 'succeeded');

        // Send confirmation email
        self::send_confirmation_email($booking);

        wp_send_json_success([
            'status'      => 'confirmed',
            'message'     => 'Booking confirmed! Check your email for details.',
            'booking_id'  => $booking_id,
        ]);
    }

    /**
     * Send confirmation email wrapper
     */
    private static function send_confirmation_email($booking)
    {
        SB_Accommodation_Admin::send_status_update_email($booking, 'confirmed');
    }

    /**
     * Send professional query emails to guest and admin
     */
    private static function send_query_emails($booking)
    {
        $room_title = get_the_title($booking->room_type_id);
        $currency   = get_option('sb_currency_symbol', '₱');
        $site_name  = get_bloginfo('name');
        $nights     = SB_Accommodation_Database::calculate_nights($booking->check_in_date, $booking->check_out_date);

        $subject_guest = "Reservation Query Received – {$room_title}";
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $message_guest = "
        <html><body style='font-family:sans-serif;color:#333;line-height:1.6;'>
        <div style='max-width:600px;margin:20px auto;border:1px solid #eee;padding:30px;border-radius:8px;'>
            <h2 style='color:#111b19;margin-top:0;'>Your Reservation Request has been Successfully Sent</h2>
            <p>Hi " . esc_html($booking->guest_name) . ",</p>
            <p>Thank you for your interest. We have received your reservation request for <strong>" . esc_html($room_title) . "</strong>. Your request is currently <strong>Pending</strong>, and we will contact you within 2/3 hours for confirmation.</p>
            
            <div style='background:#f9fcfb;padding:20px;border-radius:8px;margin:25px 0;'>
                <table style='width:100%;border-collapse:collapse;'>
                    <tr><td style='padding:5px 0;color:#666;'>Check-in</td><td style='padding:5px 0;text-align:right;'><strong>" . date('l, F j, Y', strtotime($booking->check_in_date)) . "</strong></td></tr>
                    <tr><td style='padding:5px 0;color:#666;'>Check-out</td><td style='padding:5px 0;text-align:right;'><strong>" . date('l, F j, Y', strtotime($booking->check_out_date)) . "</strong></td></tr>
                    <tr><td style='padding:5px 0;color:#666;'>Duration</td><td style='padding:5px 0;text-align:right;'><strong>{$nights} night" . ($nights > 1 ? 's' : '') . "</strong></td></tr>
                    <tr><td style='padding:5px 0;color:#666;'>Guests</td><td style='padding:5px 0;text-align:right;'><strong>{$booking->occupant_count}</strong></td></tr>
                </table>
            </div>

            <p>We look forward to hosting you!</p>
            <p style='margin-top:30px;border-top:1px solid #eee;padding-top:20px;'>
                Best regards,<br>
                <strong>" . esc_html($site_name) . "</strong>
            </p>
        </div>
        </body></html>";

        // To Guest
        wp_mail($booking->guest_email, $subject_guest, $message_guest, $headers);

        // To Admin
        $admin_email   = get_option('sb_admin_email') ?: get_option('admin_email');
        $admin_subject = "New Accommodation Query: " . esc_html($room_title) . " – " . esc_html($booking->guest_name);
        $admin_message = "
        <html><body style='font-family:sans-serif;color:#333;line-height:1.6;'>
        <div style='max-width:600px;margin:20px auto;border:1px solid #eee;padding:30px;border-radius:8px;'>
            <h2 style='color:#111b19;margin-top:0;'>New Reservation Query</h2>
            <p>A new accommodation query has been submitted and is waiting for your approval.</p>

            <div style='background:#f9fcfb;padding:20px;border-radius:8px;margin:25px 0;'>
                <table style='width:100%;border-collapse:collapse;'>
                    <tr><td style='padding:5px 0;color:#666;'>Guest</td><td style='padding:5px 0;text-align:right;'><strong>" . esc_html($booking->guest_name) . "</strong></td></tr>
                    <tr><td style='padding:5px 0;color:#666;'>Email</td><td style='padding:5px 0;text-align:right;'><strong>" . esc_html($booking->guest_email) . "</strong></td></tr>
                    <tr><td style='padding:5px 0;color:#666;'>Phone</td><td style='padding:5px 0;text-align:right;'><strong>" . esc_html($booking->guest_phone) . "</strong></td></tr>
                    <tr><td style='padding:5px 0;color:#666;'>Room</td><td style='padding:5px 0;text-align:right;'><strong>" . esc_html($room_title) . "</strong></td></tr>
                    <tr><td style='padding:5px 0;color:#666;'>Check-in</td><td style='padding:5px 0;text-align:right;'><strong>" . date('l, F j, Y', strtotime($booking->check_in_date)) . "</strong></td></tr>
                    <tr><td style='padding:5px 0;color:#666;'>Check-out</td><td style='padding:5px 0;text-align:right;'><strong>" . date('l, F j, Y', strtotime($booking->check_out_date)) . "</strong></td></tr>
                    <tr><td style='padding:5px 0;color:#666;'>Notes</td><td style='padding:5px 0;text-align:right;'><strong>" . esc_html($booking->notes) . "</strong></td></tr>
                </table>
            </div>

            <p style='margin-top:24px;'><a href='" . admin_url('edit.php?post_type=accommodation_room&page=accommodation_bookings') . "' style='display:inline-block;padding:10px 20px;background:#111b19;color:#fff;text-decoration:none;border-radius:4px;'>View Reservations in Panel</a></p>

            <p style='margin-top:30px;border-top:1px solid #eee;padding-top:20px;color:#666;font-size:13px;'>
                This is an automated notification from <strong>" . esc_html($site_name) . "</strong>.
            </p>
        </div>
        </body></html>";

        wp_mail($admin_email, $admin_subject, $admin_message, $headers);
    }
}
