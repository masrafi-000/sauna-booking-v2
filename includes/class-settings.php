<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Settings {

    public static function init() {
        add_action( 'admin_menu',  [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init',  [ __CLASS__, 'register_settings' ] );
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=sauna_product',
            'Sauna Booking Settings',
            'Settings',
            'manage_options',
            'sb-settings',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function register_settings() {
        $options = [
            'sb_stripe_publishable_key',
            'sb_stripe_secret_key',
            'sb_currency',
            'sb_currency_symbol',
            'sb_admin_email',
        ];
        foreach ( $options as $opt ) {
            register_setting( 'sb_settings_group', $opt, 'sanitize_text_field' );
        }
        // Admin email uses sanitize_email instead
        register_setting( 'sb_settings_group', 'sb_admin_email', 'sanitize_email' );
    }

    public static function render_page() {
        if ( ! current_user_can('manage_options') ) return;
        ?>
        <div class="wrap">
            <h1>Sauna Booking Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('sb_settings_group'); ?>
                <div class="notice notice-info inline" style="margin-top:20px; padding: 15px;">
                    <h3 style="margin-top:0;">Manual Approval Workflow Active</h3>
                    <p>The system is currently configured for <strong>Manual Admin Approval</strong>. Stripe payments are bypassed for customers, allowing them to submit booking queries that you can approve or reject manually.</p>
                    <ul style="list-style-type: disc; margin-left: 20px;">
                        <li>Bookings are received as <strong>Pending</strong> and block the chosen dates/slots.</li>
                        <li>You MUST use a plugin like <a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank">WP Mail SMTP</a> to ensure notification emails reach your guests.</li>
                        <li>Status change emails (Confirmed/Cancelled) are sent automatically when you update a booking in the admin panel.</li>
                    </ul>
                </div>

                <table class="form-table">
                    <tr>
                        <th colspan="2"><h3>Payment Settings (Future Use)</h3></th>
                    </tr>
                    <tr>
                        <th>Stripe Publishable Key</th>
                        <td><input type="text" name="sb_stripe_publishable_key" value="<?php echo esc_attr(get_option('sb_stripe_publishable_key')); ?>" class="regular-text" placeholder="pk_live_..." /></td>
                    </tr>
                    <tr>
                        <th>Stripe Secret Key</th>
                        <td><input type="password" name="sb_stripe_secret_key" value="<?php echo esc_attr(get_option('sb_stripe_secret_key')); ?>" class="regular-text" placeholder="sk_live_..." /></td>
                    </tr>
                    <tr>
                        <th colspan="2"><h3>Booking & Localization</h3></th>
                    </tr>
                    <tr>
                        <th>Currency Code</th>
                        <td><input type="text" name="sb_currency" value="<?php echo esc_attr(get_option('sb_currency','PHP')); ?>" class="small-text" placeholder="PHP" /></td>
                    </tr>
                    <tr>
                        <th>Currency Symbol</th>
                        <td><input type="text" name="sb_currency_symbol" value="<?php echo esc_attr(get_option('sb_currency_symbol','₱')); ?>" class="small-text" placeholder="₱" /></td>
                    </tr>
                    <tr>
                        <th>Admin Email</th>
                        <td>
                            <input type="email" name="sb_admin_email" value="<?php echo esc_attr(get_option('sb_admin_email','')); ?>" class="regular-text" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>" />
                            <p class="description">Booking confirmation notifications will be sent to this address. Leave blank to use the default WordPress admin email (<code><?php echo esc_html(get_option('admin_email')); ?></code>).</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }
}
