<?php
if (! defined('ABSPATH')) exit;

class SB_Shortcode
{

    public static function init()
    {
        add_shortcode('sauna_products', [__CLASS__, 'render']);
    }

    public static function render($atts)
    {
        $atts = shortcode_atts([
            'columns'    => 'auto',
            'per_page'   => 9,
            'category'   => '',
            'ids'        => '',
        ], $atts, 'sauna_products');

        $query_args = [
            'post_type'      => 'sauna_product',
            'post_status'    => 'publish',
            'posts_per_page' => intval($atts['per_page']),
        ];
        if (! empty($atts['ids'])) {
            $query_args['post__in'] = array_map('intval', explode(',', $atts['ids']));
        }

        $products = new WP_Query($query_args);
        if (! $products->have_posts()) {
            return '<p class="sb-no-products">No sauna products found.</p>';
        }

        // Auto columns by card count: 1 card => 1 col, 2 cards => 2 col, 3+ cards => 3 col.
        $cols = max(1, min(3, intval($products->post_count)));
        ob_start();
?>
        <div class="sb-products-grid sb-cols-<?php echo esc_attr($cols); ?>">
            <?php while ($products->have_posts()) : $products->the_post();
                $id       = get_the_ID();
                $title    = get_the_title();
                $link     = get_permalink();
                $price    = get_post_meta($id, '_sb_price',    true);
                $seats    = get_post_meta($id, '_sb_seats',    true) ?: 6;
                $badge    = get_post_meta($id, '_sb_badge',    true);
                $location = get_post_meta($id, '_sb_location', true);
                $thumb    = get_the_post_thumbnail_url($id, 'large');
                $currency = get_option('sb_currency_symbol', '₱');
            ?>
                <a class="sb-product-card" href="<?php echo esc_url($link); ?>">
                    <div class="sb-card-image">
                        <?php if ($thumb) : ?>
                            <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" />
                        <?php else : ?>
                            <div class="sb-card-placeholder">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="2" y="3" width="20" height="14" rx="2" />
                                    <line x1="8" y1="21" x2="16" y2="21" />
                                    <line x1="12" y1="17" x2="12" y2="21" />
                                </svg>
                            </div>
                        <?php endif; ?>
                        <?php if ($badge) : ?>
                            <span class="sb-card-badge"><?php echo esc_html($badge); ?></span>
                        <?php endif; ?>
                        <div class="sb-card-overlay"></div>
                    </div>
                    <div class="sb-card-body">
                        <h3 class="sb-card-title"><?php echo esc_html($title); ?></h3>
                        <div class="sb-card-meta">
                            <span class="sb-card-seats">
                                <svg style="margin-top: 7px;" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                <?php echo esc_html($seats); ?> seats
                            </span>
                            <?php if ($location) : ?>
                                <span class="sb-card-location">
                                    <svg style="margin-top: 7px;" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                        <circle cx="12" cy="10" r="3" />
                                    </svg>
                                    <?php echo esc_html($location); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($price) : ?>
                            <div class="sb-card-price">
                                <span class="sb-price-from">Price: </span>
                                <span class="sb-price-amount"><?php echo esc_html($currency . number_format((float)$price, 0)); ?>
                                    <?php echo esc_html(get_option('sb_currency', 'PHP')); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endwhile;
            wp_reset_postdata(); ?>
        </div>
<?php
        return ob_get_clean();
    }
}
