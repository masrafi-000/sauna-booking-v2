<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Admin {

    public static function init() {
        add_action( 'admin_menu',           [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_action( 'wp_ajax_sb_update_booking_status', [ __CLASS__, 'ajax_update_status' ] );
        add_action( 'wp_ajax_sb_delete_booking',        [ __CLASS__, 'ajax_delete_booking' ] );
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=sauna_product',
            'All Bookings',
            'All Bookings',
            'manage_options',
            'sb-bookings',
            [ __CLASS__, 'render_bookings_page' ]
        );
    }

    public static function enqueue( $hook ) {
        if ( strpos( $hook, 'sb-bookings' ) !== false || strpos( $hook, 'sb-settings' ) !== false ) {
            wp_enqueue_style( 'sb-admin-style', SB_URL . 'assets/css/sauna-admin.css', [], SB_VERSION );
        }
    }

    public static function render_bookings_page() {
        $filter_status  = sanitize_text_field( $_GET['status']  ?? '' );
        $filter_product = absint( $_GET['product_id'] ?? 0 );
        $paged          = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $per_page       = 20;

        $args     = [ 'per_page' => $per_page, 'page' => $paged ];
        if ( $filter_status  ) $args['status']     = $filter_status;
        if ( $filter_product ) $args['product_id'] = $filter_product;

        $bookings = SB_Database::get_all_bookings( $args );
        $total    = SB_Database::get_booking_count( $args );
        $pages    = ceil( $total / $per_page );

        $products = get_posts( [ 'post_type' => 'sauna_product', 'posts_per_page' => -1, 'post_status' => 'publish' ] );

        $currency = get_option( 'sb_currency_symbol', '₱' );
        ?>
        <div class="wrap sb-admin-wrap">
            <h1 class="wp-heading-inline">Sauna Bookings</h1>
            <hr class="wp-header-end">

            <!-- Filters -->
            <form method="get" class="sb-admin-filters">
                <input type="hidden" name="post_type" value="sauna_product">
                <input type="hidden" name="page" value="sb-bookings">
                <select name="product_id">
                    <option value="">— All Saunas —</option>
                    <?php foreach ( $products as $p ) : ?>
                        <option value="<?php echo $p->ID; ?>" <?php selected( $filter_product, $p->ID ); ?>>
                            <?php echo esc_html($p->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="">— All Statuses —</option>
                    <?php foreach ( ['pending','confirmed','cancelled'] as $s ) : ?>
                        <option value="<?php echo $s; ?>" <?php selected($filter_status, $s); ?>><?php echo ucfirst($s); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button">Filter</button>
                <a href="?post_type=sauna_product&page=sb-bookings" class="button">Reset</a>
                <span class="sb-total-count"><?php echo $total; ?> booking<?php echo $total !== 1 ? 's' : ''; ?></span>
            </form>

            <!-- Bookings Table -->
            <table class="wp-list-table widefat fixed striped sb-bookings-table">
                <thead>
                    <tr>
                        <th width="50">#</th>
                        <th>Guest</th>
                        <th>Product</th>
                        <th>Date</th>
                        <th>Time Slot</th>
                        <th>Seats</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Booked On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $bookings ) : ?>
                        <?php foreach ( $bookings as $b ) : ?>
                        <tr id="sb-row-<?php echo $b->id; ?>">
                            <td><?php echo $b->id; ?></td>
                            <td>
                                <strong><?php echo esc_html($b->first_name . ' ' . $b->last_name); ?></strong><br>
                                <a href="mailto:<?php echo esc_attr($b->email); ?>"><?php echo esc_html($b->email); ?></a><br>
                                <small><?php echo esc_html($b->phone); ?></small>
                            </td>
                            <td><?php echo esc_html( get_the_title($b->product_id) ); ?></td>
                            <td><?php echo esc_html( date('D, M j, Y', strtotime($b->booking_date)) ); ?></td>
                            <td><?php echo esc_html($b->time_slot_start . ' – ' . $b->time_slot_end); ?></td>
                            <td><?php echo intval($b->seats_booked); ?></td>
                            <td><?php echo esc_html($currency . number_format($b->amount, 2)); ?></td>
                            <td>
                                <span class="sb-status-badge sb-status-<?php echo esc_attr($b->status); ?>">
                                    <?php echo esc_html(ucfirst($b->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( date('M j, Y H:i', strtotime($b->created_at)) ); ?></td>
                            <td class="sb-actions">
                                <?php if ($b->status !== 'confirmed') : ?>
                                <button class="button button-small sb-confirm-btn" data-id="<?php echo $b->id; ?>" data-status="confirmed">Confirm</button>
                                <?php endif; ?>
                                <?php if ($b->status !== 'cancelled') : ?>
                                <button class="button button-small sb-cancel-btn" data-id="<?php echo $b->id; ?>" data-status="cancelled">Cancel</button>
                                <?php endif; ?>
                                <button class="button button-small button-link-delete sb-delete-btn" data-id="<?php echo $b->id; ?>">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="10" style="text-align:center;padding:30px;">No bookings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ( $pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $base = add_query_arg( [ 'post_type' => 'sauna_product', 'page' => 'sb-bookings', 'paged' => '%#%' ], admin_url('edit.php') );
                    echo paginate_links([
                        'base'      => $base,
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
        </div>

        <script>
        jQuery(function($){
            var nonce = '<?php echo wp_create_nonce("sb_admin_nonce"); ?>';

            $(document).on('click', '.sb-confirm-btn, .sb-cancel-btn', function(){
                var btn    = $(this);
                var id     = btn.data('id');
                var status = btn.data('status');
                if (!confirm('Set booking #' + id + ' to "' + status + '"?')) return;
                $.post(ajaxurl, {action:'sb_update_booking_status', nonce: nonce, booking_id: id, status: status}, function(res){
                    if (res.success) location.reload();
                    else alert(res.data.message || 'Error');
                });
            });

            $(document).on('click', '.sb-delete-btn', function(){
                var id = $(this).data('id');
                if (!confirm('Permanently delete booking #' + id + '?')) return;
                $.post(ajaxurl, {action:'sb_delete_booking', nonce: nonce, booking_id: id}, function(res){
                    if (res.success) $('#sb-row-' + id).fadeOut(400, function(){ $(this).remove(); });
                    else alert(res.data.message || 'Error');
                });
            });
        });
        </script>
        <?php
    }

    public static function ajax_update_status() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'sb_admin_nonce' ) || ! current_user_can('manage_options') ) {
            wp_send_json_error( ['message' => 'Unauthorized'] );
        }
        $id     = absint( $_POST['booking_id'] );
        $status = sanitize_text_field( $_POST['status'] );
        
        $booking = SB_Database::get_booking( $id );
        if ( ! $booking ) {
            wp_send_json_error( ['message' => 'Booking not found'] );
        }

        SB_Database::update_booking_status( $id, $status );

        // Send email to guest
        self::send_status_update_email( $booking, $status );

        wp_send_json_success();
    }

    public static function send_status_update_email( $booking, $status ) {
        $product_title = get_the_title( $booking->product_id );
        $site_name     = get_bloginfo( 'name' );
        $headers       = [ 'Content-Type: text/html; charset=UTF-8' ];

        if ( $status === 'confirmed' ) {
            $subject = "Booking Confirmed! – {$product_title}";
            $message = "
            <html><body style='font-family:sans-serif;color:#333;'>
            <h2 style='color:#155724;'>Your Booking is Confirmed!</h2>
            <p>Hi {$booking->first_name},</p>
            <p>We are happy to inform you that your booking for <strong>{$product_title}</strong> has been confirmed.</p>
            <table style='border-collapse:collapse;width:100%;max-width:500px;'>
                <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Date</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . date('l, F j, Y', strtotime($booking->booking_date)) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Time</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$booking->time_slot_start} – {$booking->time_slot_end}</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Seats</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$booking->seats_booked}</td></tr>
            </table>
            <p style='margin-top:24px;'>We look forward to seeing you!</p>
            <p>Best regards,<br><em>{$site_name}</em></p>
            </body></html>";
        } elseif ( $status === 'cancelled' ) {
            $subject = "Booking Cancelled – {$product_title}";
            $message = "
            <html><body style='font-family:sans-serif;color:#333;'>
            <h2 style='color:#721c24;'>Booking Update</h2>
            <p>Hi {$booking->first_name},</p>
            <p>Your booking query for <strong>{$product_title}</strong> on " . date('M j, Y', strtotime($booking->booking_date)) . " has been cancelled/rejected.</p>
            <p>If you have any questions, please feel free to contact us.</p>
            <p>Best regards,<br><em>{$site_name}</em></p>
            </body></html>";
        } else {
            return; // No email for other statuses
        }

        wp_mail( $booking->email, $subject, $message, $headers );

        // Also notify admin of status change
        $admin_email   = get_option('sb_admin_email') ?: get_option('admin_email');
        $admin_subject = "Booking {$status}: {$product_title} – {$booking->first_name} {$booking->last_name}";
        $admin_message = "
        <html><body style='font-family:sans-serif;color:#333;'>
        <h2 style='color:#c97d30;'>Booking Status Updated: " . ucfirst($status) . "</h2>
        <p>The status for a sauna booking has been updated.</p>
        <table style='border-collapse:collapse;width:100%;max-width:500px;'>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Customer</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$booking->first_name} {$booking->last_name}</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Email</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$booking->email}</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Sauna</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>{$product_title}</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>New Status</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'><strong>" . ucfirst($status) . "</strong></td></tr>
        </table>
        <p style='margin-top:24px;'><a href='" . admin_url('edit.php?post_type=sauna_product&page=sb-bookings') . "' style='display:inline-block;padding:10px 20px;background:#c97d30;color:#fff;text-decoration:none;border-radius:4px;'>View All Bookings</a></p>
        </body></html>";
        
        wp_mail( $admin_email, $admin_subject, $admin_message, $headers );
    }

    public static function ajax_delete_booking() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'sb_admin_nonce' ) || ! current_user_can('manage_options') ) {
            wp_send_json_error( ['message' => 'Unauthorized'] );
        }
        global $wpdb;
        $wpdb->delete( SB_Database::get_table(), ['id' => absint($_POST['booking_id'])], ['%d'] );
        wp_send_json_success();
    }
}
