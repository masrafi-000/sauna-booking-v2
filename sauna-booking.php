<?php

/**
 * Plugin Name: Sauna Booking
 * Plugin URI:  https://example.com
 * Description: Complete sauna booking system with custom post types, time-slot scheduling, and Stripe payments.
 * Version:     1.0.0
 * Author:      Your Name
 * Text Domain: sauna-booking
 */

if (! defined('ABSPATH')) exit;

define('SB_VERSION',  '1.0.3');
define('SB_PATH',     plugin_dir_path(__FILE__));
define('SB_URL',      plugin_dir_url(__FILE__));
define('SB_DB_VER',   '1.0');

// Trigger a one-time flush for new CPT permalinks
update_option('sb_flush_needed', true);

/* ── Autoload includes ─────────────────────────────────────────────────────── */
// Sauna Booking
require_once SB_PATH . 'includes/class-post-type.php';
require_once SB_PATH . 'includes/class-database.php';
require_once SB_PATH . 'includes/class-shortcode.php';
require_once SB_PATH . 'includes/class-ajax.php';
require_once SB_PATH . 'includes/class-admin.php';
require_once SB_PATH . 'includes/class-settings.php';

// Accommodation Booking
require_once SB_PATH . 'includes/class-accommodation-post-type.php';
require_once SB_PATH . 'includes/class-accommodation-database.php';
require_once SB_PATH . 'includes/class-accommodation-shortcode.php';
require_once SB_PATH . 'includes/class-accommodation-ajax.php';
require_once SB_PATH . 'includes/class-accommodation-admin.php';
require_once SB_PATH . 'includes/class-webhooks.php';

/* ── Activation ─────────────────────────────────────────────────────────────── */
register_activation_hook(__FILE__, 'sb_activate');
function sb_activate()
{
    // Sauna
    SB_Post_Type::register_cpt();
    SB_Database::install();

    // Accommodation
    SB_Accommodation_Post_Type::register_cpt();
    SB_Accommodation_Database::install();

    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

/* ── Boot ───────────────────────────────────────────────────────────────── */
add_action('plugins_loaded', 'sb_boot');
function sb_boot()
{
    // Sauna
    SB_Post_Type::init();
    SB_Shortcode::init();
    SB_Ajax::init();
    SB_Admin::init();
    SB_Settings::init();

    // Accommodation
    SB_Accommodation_Post_Type::init();
    SB_Accommodation_Shortcode::init();
    SB_Accommodation_Ajax::init();
    SB_Accommodation_Admin::init();
    SB_Webhooks::init();

    // Ensure database tables exist
    if (get_option('sb_acc_db_version') !== SB_DB_VER) {
        SB_Accommodation_Database::install();
    }
}

add_action('init', 'sb_maybe_flush', 99);
function sb_maybe_flush()
{
    if (get_option('sb_flush_needed')) {
        flush_rewrite_rules();
        delete_option('sb_flush_needed');
    }
}

/* ── Body class ─────────────────────────────────────────────────────────────── */
add_filter('body_class', 'sb_body_class');
function sb_body_class($classes)
{
    if (is_singular('sauna_product')) {
        $classes[] = 'sb-dark-page';
    }
    if (is_singular('accommodation_room')) {
        $classes[] = 'acc-page';
    }
    return $classes;
}

/* ── Kill ALL sidebars on sauna product pages ───────────────────────────────── */
add_filter('sidebars_widgets', 'sb_kill_sidebars');
function sb_kill_sidebars($sidebars)
{
    if (is_singular('sauna_product') || is_singular('accommodation_room')) {
        foreach ($sidebars as $key => $val) {
            $sidebars[$key] = [];
        }
    }
    return $sidebars;
}

/* ── Prevent theme from injecting its own content wrapper ───────────────────── */
add_action('template_redirect', 'sb_supress_theme_content');
function sb_supress_theme_content()
{
    if (! is_singular('sauna_product')) return;
    // Remove common theme content actions that double-render post content
    remove_action('genesis_entry_content', 'genesis_do_post_content');
    remove_action('genesis_post_content',  'genesis_do_post_content');
    // Elementor: prevent auto-appending content outside our wrapper
    add_filter('the_content', 'sb_wrap_content_guard', 999);
}
function sb_wrap_content_guard($content)
{
    // Only suppress if it's being rendered outside our template wrapper
    // We detect this by checking if we're in a secondary render pass
    static $called = 0;
    $called++;
    if ($called > 1) return ''; // Suppress any duplicate content render
    return $content;
}

/* ── Template override for single-sauna_product & accommodation_room ──────── */
add_filter('single_template', 'sb_single_template');
function sb_single_template($template)
{
    if (is_singular('sauna_product')) {
        $custom = SB_PATH . 'templates/single-sauna-product.php';
        if (file_exists($custom)) return $custom;
    }
    if (is_singular('accommodation_room')) {
        $custom = SB_PATH . 'templates/single-accommodation-room.php';
        if (file_exists($custom)) return $custom;
    }
    return $template;
}

/* ── Enqueue front-end assets ───────────────────────────────────────────────── */
add_action('wp_enqueue_scripts', 'sb_enqueue_assets');
function sb_enqueue_assets()
{
    if (! is_singular('sauna_product') && ! has_shortcode(get_post()->post_content ?? '', 'sauna_products')) {
        // Still load on any page that might show cards or single
    }
    wp_enqueue_style(
        'sb-style',
        SB_URL . 'assets/css/sauna-booking.css',
        [],
        SB_VERSION
    );
    // Stripe.js
    wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
    wp_enqueue_script(
        'sb-script',
        SB_URL . 'assets/js/sauna-booking.js',
        ['jquery', 'stripe-js'],
        SB_VERSION,
        true
    );
    wp_localize_script('sb-script', 'SB_Data', [
        'ajax_url'        => admin_url('admin-ajax.php'),
        'nonce'           => wp_create_nonce('sb_nonce'),
        'stripe_pub_key'  => get_option('sb_stripe_publishable_key', ''),
        'currency'        => get_option('sb_currency', 'PHP'),
        'currency_symbol' => get_option('sb_currency_symbol', '₱'),
    ]);
}
