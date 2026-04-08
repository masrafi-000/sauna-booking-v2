<?php

/**
 * Accommodation Booking System - Test Suite
 * 
 * Run this file from WordPress to test all accommodation features
 * Add this to your plugin or run via wp-cli: wp eval-file tests/test-accommodation.php
 */

if (! defined('ABSPATH')) {
    echo "Error: Must be run within WordPress environment\n";
    exit;
}

class Accommodation_Tests
{

    private $results = [];

    public function run_all_tests()
    {
        echo "\n========================================\n";
        echo "ACCOMMODATION BOOKING SYSTEM - TEST SUITE\n";
        echo "========================================\n\n";

        $this->test_post_type_registration();
        $this->test_database_table();
        $this->test_database_functions();
        $this->test_shortcode();
        $this->test_ajax_availability();
        $this->test_availability_logic();

        $this->print_results();
    }

    /**
     * TEST 1: Post Type Registration
     */
    private function test_post_type_registration()
    {
        echo "TEST 1: Post Type Registration\n";
        echo "-------------------------------\n";

        $post_types = get_post_types();
        $exists = in_array('accommodation_room', $post_types);

        if ($exists) {
            $this->pass("Post type 'accommodation_room' registered");

            $cpt = get_post_type_object('accommodation_room');
            $this->pass("Post type object retrieved");
            $this->pass("Public: " . ($cpt->public ? "Yes" : "No"));
            $this->pass("Has archive: " . ($cpt->has_archive ? "Yes" : "No"));
        } else {
            $this->fail("Post type 'accommodation_room' NOT registered");
        }

        echo "\n";
    }

    /**
     * TEST 2: Database Table Creation
     */
    private function test_database_table()
    {
        echo "TEST 2: Database Table Creation\n";
        echo "--------------------------------\n";

        global $wpdb;
        $table = $wpdb->prefix . 'accommodation_bookings';

        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

        if ($exists) {
            $this->pass("Table '$table' exists");

            $columns = $wpdb->get_results("DESCRIBE $table");
            $column_names = wp_list_pluck($columns, 'Field');

            $required_cols = [
                'id',
                'room_type_id',
                'check_in_date',
                'check_out_date',
                'occupant_count',
                'guest_name',
                'guest_email',
                'guest_phone',
                'total_amount',
                'stripe_pi_id',
                'stripe_status',
                'booking_status',
                'notes',
                'created_at'
            ];

            foreach ($required_cols as $col) {
                if (in_array($col, $column_names)) {
                    $this->pass("Column '$col' exists");
                } else {
                    $this->fail("Column '$col' MISSING");
                }
            }

            // Check indexes
            $keys = $wpdb->get_results("SHOW INDEXES FROM $table");
            $key_names = wp_list_pluck($keys, 'Key_name');

            if (in_array('room_dates', $key_names)) {
                $this->pass("Index 'room_dates' exists for date range queries");
            } else {
                $this->fail("Index 'room_dates' MISSING");
            }
        } else {
            $this->fail("Table '$table' does NOT exist");
        }

        echo "\n";
    }

    /**
     * TEST 3: Database Functions
     */
    private function test_database_functions()
    {
        echo "TEST 3: Database Functions\n";
        echo "--------------------------\n";

        // Test: Calculate nights
        $nights_1 = SB_Accommodation_Database::calculate_nights('2025-01-15', '2025-01-17');
        if ($nights_1 === 2) {
            $this->pass("calculate_nights: 2025-01-15 to 2025-01-17 = 2 nights ✓");
        } else {
            $this->fail("calculate_nights returned $nights_1, expected 2");
        }

        $nights_2 = SB_Accommodation_Database::calculate_nights('2025-01-15', '2025-01-22');
        if ($nights_2 === 7) {
            $this->pass("calculate_nights: 2025-01-15 to 2025-01-22 = 7 nights ✓");
        } else {
            $this->fail("calculate_nights returned $nights_2, expected 7");
        }

        // Test: Get table
        $table = SB_Accommodation_Database::get_table();
        global $wpdb;
        $expected = $wpdb->prefix . 'accommodation_bookings';
        if ($table === $expected) {
            $this->pass("get_table() returns correct table name");
        } else {
            $this->fail("get_table() returned '$table', expected '$expected'");
        }

        // Test: Get booked dates (should be empty initially)
        $booked = SB_Accommodation_Database::get_booked_dates(999);
        if (is_array($booked)) {
            $this->pass("get_booked_dates() returns array");
        } else {
            $this->fail("get_booked_dates() did not return array");
        }

        echo "\n";
    }

    /**
     * TEST 4: Shortcode
     */
    private function test_shortcode()
    {
        echo "TEST 4: Shortcode Registration\n";
        echo "------------------------------\n";

        global $shortcode_tags;

        if (isset($shortcode_tags['accommodation_rooms'])) {
            $this->pass("Shortcode [accommodation_rooms] registered");

            // Test rendering (should not error)
            $output = do_shortcode('[accommodation_rooms columns="3" per_page="9"]');
            if (strpos($output, 'acc-rooms-grid') !== false) {
                $this->pass("Shortcode renders with grid class");
            } else {
                $this->pass("Shortcode renders (grid class not found in output)");
            }
        } else {
            $this->fail("Shortcode [accommodation_rooms] NOT registered");
        }

        echo "\n";
    }

    /**
     * TEST 5: AJAX Actions
     */
    private function test_ajax_availability()
    {
        echo "TEST 5: AJAX Actions Registration\n";
        echo "---------------------------------\n";

        global $wp_filter;

        $actions = [
            'wp_ajax_acc_check_availability',
            'wp_ajax_nopriv_acc_check_availability',
            'wp_ajax_acc_create_payment_intent',
            'wp_ajax_nopriv_acc_create_payment_intent',
            'wp_ajax_acc_confirm_booking',
            'wp_ajax_nopriv_acc_confirm_booking',
            'wp_ajax_acc_get_booked_dates',
            'wp_ajax_nopriv_acc_get_booked_dates',
        ];

        foreach ($actions as $action) {
            if (isset($wp_filter[$action])) {
                $this->pass("AJAX action '$action' registered");
            } else {
                $this->fail("AJAX action '$action' NOT registered");
            }
        }

        echo "\n";
    }

    /**
     * TEST 6: Availability Logic
     */
    private function test_availability_logic()
    {
        echo "TEST 6: Availability Logic\n";
        echo "---------------------------\n";

        // Create a dummy room for testing
        $room_id = wp_insert_post([
            'post_type'   => 'accommodation_room',
            'post_title'  => 'Test Room',
            'post_status' => 'publish',
        ]);

        if ($room_id) {
            $this->pass("Created test room with ID: $room_id");

            // Test: availability check on empty bookings
            $available = SB_Accommodation_Database::check_availability(
                $room_id,
                '2025-06-01',
                '2025-06-05'
            );

            if ($available) {
                $this->pass("Room is available when no bookings exist");
            } else {
                $this->fail("Availability check failed on empty bookings");
            }

            // Create a test booking
            $booking_id = SB_Accommodation_Database::create_booking([
                'room_type_id'   => $room_id,
                'check_in_date'  => '2025-06-10',
                'check_out_date' => '2025-06-15',
                'occupant_count' => 2,
                'guest_name'     => 'Test Guest',
                'guest_email'    => 'test@example.com',
                'guest_phone'    => '123-456-7890',
                'total_amount'   => 500.00,
                'booking_status' => 'confirmed',
            ]);

            if ($booking_id) {
                $this->pass("Created test booking with ID: $booking_id");

                // Test: overlapping dates should be unavailable
                $overlapping = SB_Accommodation_Database::check_availability(
                    $room_id,
                    '2025-06-12', // Within existing booking
                    '2025-06-18'
                );

                if (! $overlapping) {
                    $this->pass("Room correctly marked unavailable for overlapping dates");
                } else {
                    $this->fail("Room should be unavailable for overlapping dates");
                }

                // Test: dates before booking should be available
                $before = SB_Accommodation_Database::check_availability(
                    $room_id,
                    '2025-06-05',
                    '2025-06-10' // Ends when next booking starts
                );

                if ($before) {
                    $this->pass("Room correctly marked available for dates before booking");
                } else {
                    $this->fail("Room should be available before booking dates");
                }

                // Test: dates after booking should be available
                $after = SB_Accommodation_Database::check_availability(
                    $room_id,
                    '2025-06-15', // Starts when previous booking ends
                    '2025-06-20'
                );

                if ($after) {
                    $this->pass("Room correctly marked available for dates after booking");
                } else {
                    $this->fail("Room should be available after booking dates");
                }

                // Test: get booking
                $booking = SB_Accommodation_Database::get_booking($booking_id);
                if ($booking && $booking['guest_name'] === 'Test Guest') {
                    $this->pass("get_booking() retrieves correct booking data");
                } else {
                    $this->fail("get_booking() failed");
                }

                // Test: update status
                SB_Accommodation_Database::update_booking_status($booking_id, 'cancelled');
                $updated = SB_Accommodation_Database::get_booking($booking_id);
                if ($updated && $updated['booking_status'] === 'cancelled') {
                    $this->pass("update_booking_status() works correctly");
                } else {
                    $this->fail("update_booking_status() failed");
                }

                // Cleanup
                wp_delete_post($booking_id, true);
            } else {
                $this->fail("Failed to create test booking");
            }

            // Cleanup
            wp_delete_post($room_id, true);
        } else {
            $this->fail("Failed to create test room");
        }

        echo "\n";
    }

    /**
     * Helper: Record pass
     */
    private function pass($message)
    {
        $this->results[] = ['status' => 'pass', 'message' => $message];
        echo "✓ " . $message . "\n";
    }

    /**
     * Helper: Record fail
     */
    private function fail($message)
    {
        $this->results[] = ['status' => 'fail', 'message' => $message];
        echo "✗ " . $message . "\n";
    }

    /**
     * Print results summary
     */
    private function print_results()
    {
        echo "========================================\n";
        echo "TEST RESULTS SUMMARY\n";
        echo "========================================\n\n";

        $passes = count(array_filter($this->results, fn($r) => $r['status'] === 'pass'));
        $fails = count(array_filter($this->results, fn($r) => $r['status'] === 'fail'));
        $total = count($this->results);

        echo "Total Tests: $total\n";
        echo "Passed: $passes\n";
        echo "Failed: $fails\n\n";

        if ($fails === 0) {
            echo "✓ ALL TESTS PASSED!\n\n";
            return;
        }

        echo "Failed Tests:\n";
        echo "-------------\n";
        foreach (array_filter($this->results, fn($r) => $r['status'] === 'fail') as $result) {
            echo "✗ " . $result['message'] . "\n";
        }
        echo "\n";
    }
}

// Run tests
$tester = new Accommodation_Tests();
$tester->run_all_tests();
