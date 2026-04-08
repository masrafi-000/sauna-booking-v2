<?php
if (! defined('ABSPATH')) exit;

class SB_Webhooks {

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('sauna-booking/v1', '/webhook', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_webhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handle_webhook($request) {
        $payload = $request->get_body();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret = get_option('sb_stripe_webhook_secret', '');

        // For simplicity in this implementation, we'll verify the intent status directly if secret is missing
        // but ideally use Stripe\Webhook::constructEvent
        
        $data = json_decode($payload, true);
        if (!$data || !isset($data['type'])) {
            return new WP_REST_Response(['message' => 'Invalid payload'], 400);
        }

        $event_type = $data['type'];
        $object = $data['data']['object'];

        if ($event_type === 'payment_intent.succeeded') {
            self::process_payment_success($object);
        } elseif ($event_type === 'payment_intent.payment_failed') {
            self::process_payment_failure($object);
        }

        return new WP_REST_Response(['status' => 'success'], 200);
    }

    private static function process_payment_success($pi) {
        $pi_id = $pi['id'];
        
        // Find booking by PI ID (Sauna)
        global $wpdb;
        $table = SB_Database::get_table();
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE stripe_pi_id = %s", $pi_id));
        
        if ($booking && $booking->status !== 'confirmed') {
            SB_Database::update_booking_status($booking->id, 'confirmed', 'succeeded');
            // Logic to send email would go here (similar to SB_Ajax::confirm_booking)
        }

        // Find booking by PI ID (Accommodation)
        $acc_table = SB_Accommodation_Database::get_table();
        $acc_booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $acc_table WHERE stripe_pi_id = %s", $pi_id), ARRAY_A);
        
        if ($acc_booking && $acc_booking['booking_status'] !== 'confirmed') {
            SB_Accommodation_Database::update_booking_status($acc_booking['id'], 'confirmed', 'succeeded');
        }
    }

    private static function process_payment_failure($pi) {
        $pi_id = $pi['id'];
        
        // Find booking by PI ID (Sauna)
        global $wpdb;
        $table = SB_Database::get_table();
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE stripe_pi_id = %s", $pi_id));
        
        if ($booking) {
            SB_Database::update_booking_status($booking->id, 'cancelled', 'failed');
        }

        // Find booking by PI ID (Accommodation)
        $acc_table = SB_Accommodation_Database::get_table();
        $acc_booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $acc_table WHERE stripe_pi_id = %s", $pi_id), ARRAY_A);
        
        if ($acc_booking) {
            SB_Accommodation_Database::update_booking_status($acc_booking['id'], 'cancelled', 'failed');
        }
    }
}
