<?php
if (! defined('ABSPATH')) exit;

class SB_Accommodation_Admin
{

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
    }

    /**
     * Add admin menu
     */
    public static function add_menu()
    {
        add_submenu_page(
            'edit.php?post_type=accommodation_room',
            'Bookings',
            'Bookings',
            'manage_options',
            'accommodation_bookings',
            [__CLASS__, 'render_bookings_page']
        );
    }

    /**
     * Render bookings admin page
     */
    public static function render_bookings_page()
    {
        $view = isset($_GET['view']) ? absint($_GET['view']) : 0;
        if ($view) {
            self::render_single_booking($view);
            return;
        }

        // Handle status updates
        if (isset($_GET['action']) && isset($_GET['booking_id']) && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'acc_admin_action')) {
                $b_id = absint($_GET['booking_id']);
                $new_status = sanitize_text_field($_GET['action']);
                
                $booking = SB_Accommodation_Database::get_booking($b_id);

                if (in_array($new_status, ['confirmed', 'cancelled', 'pending'])) {
                    SB_Accommodation_Database::update_booking_status($b_id, $new_status);
                    
                    if ($booking) {
                        self::send_status_update_email($booking, $new_status);
                    }
                    
                    echo '<div class="notice notice-success is-dismissible"><p>Booking status updated to ' . esc_html($new_status) . '.</p></div>';
                } elseif ($new_status === 'delete') {
                    SB_Accommodation_Database::delete_booking($b_id);
                    echo '<div class="notice notice-success is-dismissible"><p>Booking #' . esc_html($b_id) . ' deleted successfully.</p></div>';
                }
            }
        }

        $paged    = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 20;

        $bookings = SB_Accommodation_Database::get_all_bookings(['per_page' => $per_page, 'page' => $paged]);
        $total    = SB_Accommodation_Database::get_booking_count();
        $pages    = ceil($total / $per_page);
?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Accommodation Bookings</h1>
            <hr class="wp-header-end">
            <p>Total: <?php echo $total; ?> booking(s)</p>

            <?php if (empty($bookings)) : ?>
                <p>No bookings found.</p>
            <?php else : ?>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:50px;">ID</th>
                            <th>Guest</th>
                            <th>Room</th>
                            <th>Dates</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking) : ?>
                            <tr>
                                <td><strong>#<?php echo esc_html($booking['id']); ?></strong></td>
                                <td>
                                    <strong><?php echo esc_html($booking['guest_name']); ?></strong><br>
                                    <span style="font-size:11px;color:#666;"><?php echo esc_html($booking['guest_email']); ?></span>
                                </td>
                                <td><?php echo esc_html(get_the_title($booking['room_type_id'])); ?></td>
                                <td>
                                    <?php echo date('M j', strtotime($booking['check_in_date'])); ?> - 
                                    <?php echo date('M j, Y', strtotime($booking['check_out_date'])); ?>
                                </td>
                                <td><?php echo esc_html(get_option('sb_currency_symbol', '₱') . number_format($booking['total_amount'], 0)); ?></td>
                                <td>
                                    <span class="sb-status-badge status-<?php echo esc_attr($booking['booking_status']); ?>">
                                        <?php echo esc_html(ucfirst($booking['booking_status'])); ?>
                                    </span>
                                </td>
                                 <td>
                                    <a href="<?php echo admin_url('edit.php?post_type=accommodation_room&page=accommodation_bookings&view=' . $booking['id']); ?>" class="button button-small">Details</a>
                                    <?php if ($booking['booking_status'] !== 'confirmed') : ?>
                                        <a href="<?php echo wp_nonce_url(admin_url("edit.php?post_type=accommodation_room&page=accommodation_bookings&action=confirmed&booking_id=" . $booking['id']), 'acc_admin_action'); ?>" class="button button-small button-primary">Approve</a>
                                    <?php endif; ?>
                                    <?php if ($booking['booking_status'] !== 'cancelled') : ?>
                                        <a href="<?php echo wp_nonce_url(admin_url("edit.php?post_type=accommodation_room&page=accommodation_bookings&action=cancelled&booking_id=" . $booking['id']), 'acc_admin_action'); ?>" class="button button-small" onclick="return confirm('Cancel this booking?')">Cancel</a>
                                    <?php endif; ?>
                                    <a href="<?php echo wp_nonce_url(admin_url("edit.php?post_type=accommodation_room&page=accommodation_bookings&action=delete&booking_id=" . $booking['id']), 'acc_admin_action'); ?>" class="button button-small" style="color:#a00;" onclick="return confirm('PERMANENTLY DELETE this booking? This cannot be undone.')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($pages > 1) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links([
                                'base'      => add_query_arg(['post_type' => 'accommodation_room', 'page' => 'accommodation_bookings', 'paged' => '%#%'], admin_url('edit.php')),
                                'format'    => '',
                                'current'   => $paged,
                                'total'     => $pages,
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                            ]);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
<?php
    }

    private static function render_single_booking($id)
    {
        $booking = SB_Accommodation_Database::get_booking($id);

        if (! $booking) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Booking not found.</p></div></div>';
            return;
        }

        $room_title = get_the_title($booking->room_type_id);
?>        <div class="wrap">
            <h1 class="wp-heading-inline">Reservation Details #<?php echo $booking->id; ?></h1>
            <a href="<?php echo admin_url('edit.php?post_type=accommodation_room&page=accommodation_bookings'); ?>" class="page-title-action">Back to List</a>
            <hr class="wp-header-end">

            <div class="sb-details-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px;">
                <!-- Guest Info -->
                <div class="sb-details-card" style="background:#fff; border:1px solid #ccd0d4; padding:20px; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                    <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Guest Information</h2>
                    <table class="form-table">
                        <tr><th style="width:150px;">Full Name</th><td><?php echo esc_html($booking->guest_name); ?></td></tr>
                        <tr><th>Email Address</th><td><a href="mailto:<?php echo esc_attr($booking->guest_email); ?>"><?php echo esc_html($booking->guest_email); ?></a></td></tr>
                        <tr><th>Phone Number</th><td><?php echo esc_html($booking->guest_phone ?: 'N/A'); ?></td></tr>
                    </table>
                </div>

                <!-- Booking Info -->
                <div class="sb-details-card" style="background:#fff; border:1px solid #ccd0d4; padding:20px; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                    <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Reservation Details</h2>
                    <table class="form-table">
                        <tr><th style="width:150px;">Room</th><td><strong><?php echo esc_html($room_title); ?></strong></td></tr>
                        <tr><th>Check-in</th><td><?php echo date('l, F j, Y', strtotime($booking->check_in_date)); ?></td></tr>
                        <tr><th>Check-out</th><td><?php echo date('l, F j, Y', strtotime($booking->check_out_date)); ?></td></tr>
                        <tr><th>Guests</th><td><?php echo intval($booking->occupant_count); ?></td></tr>
                        <tr><th>Total Amount</th><td><span class="sb-price" style="font-weight:700; color:#111b19;"><?php echo esc_html(get_option('sb_currency_symbol', '₱') . number_format($booking->total_amount, 0)); ?></span></td></tr>
                        <tr><th>Status</th><td>
                            <span class="sb-status-badge status-<?php echo esc_attr($booking->booking_status); ?>">
                                <?php echo ucfirst($booking->booking_status); ?>
                            </span>
                        </td></tr>
                    </table>
                </div>

                <!-- Additional Notes -->
                <div class="sb-details-card sb-details-full" style="grid-column: 1 / -1; background:#fff; border:1px solid #ccd0d4; padding:20px; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                    <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Additional Notes</h2>
                    <?php if ( !empty($booking->notes) ) : ?>
                        <div class="sb-notes-box" style="background:#f9f9f9; border-left:4px solid #111b19; padding:15px; font-style:italic; line-height:1.6;"><?php echo nl2br(esc_html($booking->notes)); ?></div>
                    <?php else : ?>
                        <p class="description">No notes provided by the guest.</p>
                    <?php endif; ?>
                </div>

                <!-- Admin Actions -->
                <div class="sb-details-card sb-details-full" style="grid-column: 1 / -1; background:#fff; border:1px solid #ccd0d4; padding:20px; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                    <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Manage Reservation</h2>
                    <div style="display:flex; gap:10px;">
                        <?php if ($booking->booking_status !== 'confirmed') : ?>
                            <a href="<?php echo wp_nonce_url(admin_url("edit.php?post_type=accommodation_room&page=accommodation_bookings&action=confirmed&booking_id=" . $booking->id), 'acc_admin_action'); ?>" class="button button-primary">Approve Booking</a>
                        <?php endif; ?>
                        <?php if ($booking->booking_status !== 'cancelled') : ?>
                            <a href="<?php echo wp_nonce_url(admin_url("edit.php?post_type=accommodation_room&page=accommodation_bookings&action=cancelled&booking_id=" . $booking->id), 'acc_admin_action'); ?>" class="button" onclick="return confirm('Cancel this booking?')">Cancel Booking</a>
                        <?php endif; ?>
                        <a href="<?php echo wp_nonce_url(admin_url("edit.php?post_type=accommodation_room&page=accommodation_bookings&action=delete&booking_id=" . $booking->id), 'acc_admin_action'); ?>" class="button" onclick="return confirm('PERMANENTLY DELETE this booking? This cannot be undone.')">Delete Booking</a>
                    </div>
                </div>
            </div>
        </div>
<?php
    }

    public static function send_status_update_email($booking, $status)
    {
        $room_title = get_the_title($booking->room_type_id);
        $site_name = get_bloginfo('name');
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        if ($status === 'confirmed') {
            $subject = "Reservation Confirmed! – {$room_title}";
            $message = "
            <html><body style='font-family:sans-serif;color:#333;'>
            <h2 style='color:#155724;'>Your Reservation is Confirmed!</h2>
            <p>Hi {$booking->guest_name},</p>
            <p>We are happy to inform you that your reservation for <strong>{$room_title}</strong> has been confirmed.</p>
            <table style='border-collapse:collapse;width:100%;max-width:500px;'>
                <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Check-in</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . date('l, F j, Y', strtotime($booking->check_in_date)) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Check-out</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . date('l, F j, Y', strtotime($booking->check_out_date)) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Guests</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$booking->occupant_count}</td></tr>
            </table>
            <p style='margin-top:24px;'>We look forward to hosting you!</p>
            <p>Best regards,<br><em>{$site_name}</em></p>
            </body></html>";
        } elseif ($status === 'cancelled') {
            $subject = "Reservation Update – {$room_title}";
            $message = "
            <html><body style='font-family:sans-serif;color:#333;'>
            <h2 style='color:#721c24;'>Reservation Update</h2>
            <p>Hi {$booking->guest_name},</p>
            <p>Your reservation query for <strong>{$room_title}</strong> from " . date('M j', strtotime($booking->check_in_date)) . " to " . date('M j, Y', strtotime($booking->check_out_date)) . " has been cancelled/rejected.</p>
            <p>If you have any questions, please feel free to contact us.</p>
            <p>Best regards,<br><em>{$site_name}</em></p>
            </body></html>";
        } else {
            return;
        }

        wp_mail($booking->guest_email, $subject, $message, $headers);

        // Also notify admin of status change
        $admin_email   = get_option('sb_admin_email') ?: get_option('admin_email');
        $admin_subject = "Reservation {$status}: {$room_title} – {$booking->guest_name}";
        $admin_message = "
        <html><body style='font-family:sans-serif;color:#333;'>
        <h2 style='color:#111b19;'>Reservation Status Updated: " . ucfirst($status) . "</h2>
        <p>The status for an accommodation reservation has been updated.</p>
        <table style='border-collapse:collapse;width:100%;max-width:500px;'>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Guest</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$booking->guest_name}</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Email</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$booking->guest_email}</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Room</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$room_title}</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>New Status</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'><strong>" . ucfirst($status) . "</strong></td></tr>
        </table>
        <p style='margin-top:24px;'><a href='" . admin_url('edit.php?post_type=accommodation_room&page=accommodation_bookings') . "' style='display:inline-block;padding:10px 20px;background:#111b19;color:#fff;text-decoration:none;border-radius:4px;'>View All Bookings</a></p>
        </body></html>";
        
        wp_mail($admin_email, $admin_subject, $admin_message, $headers);
    }
}
