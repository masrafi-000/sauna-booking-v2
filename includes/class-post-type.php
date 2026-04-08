<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SB_Post_Type {

    public static function init() {
        add_action( 'init',           [ __CLASS__, 'register_cpt' ] );
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
        add_action( 'save_post',      [ __CLASS__, 'save_meta' ] );
    }

    /* ── Register CPT (also called directly from activation hook) ──────────── */
    public static function register_cpt() {
        register_post_type( 'sauna_product', [
            'labels' => [
                'name'               => 'Sauna Products',
                'singular_name'      => 'Sauna Product',
                'add_new'            => 'Add New',
                'add_new_item'       => 'Add New Sauna Product',
                'edit_item'          => 'Edit Sauna Product',
                'view_item'          => 'View Sauna Product',
                'search_items'       => 'Search Sauna Products',
                'not_found'          => 'No sauna products found',
                'not_found_in_trash' => 'No sauna products found in trash',
            ],
            'public'       => true,
            'has_archive'  => true,
            // No 'editor' — we manage all content via our own meta boxes
            'supports'     => [ 'title', 'thumbnail' ],
            'show_in_rest' => false,
            'menu_icon'    => 'dashicons-calendar-alt',
            'rewrite'      => [ 'slug' => 'sauna' ],
        ] );
    }

    /* ── Meta boxes ────────────────────────────────────────────────────────── */
    public static function add_meta_boxes() {
        add_meta_box( 'sb_product_details',  'Sauna Product Details',       [ __CLASS__, 'render_details_box' ],  'sauna_product', 'normal', 'high' );
        add_meta_box( 'sb_product_content',  'Page Content (Accordions)',   [ __CLASS__, 'render_content_box' ],  'sauna_product', 'normal', 'default' );
        add_meta_box( 'sb_product_faqs',     'FAQs',                        [ __CLASS__, 'render_faq_box' ],      'sauna_product', 'normal', 'default' );
    }

    /* ── BOX 1: Sauna Product Details ──────────────────────────────────────── */
    public static function render_details_box( $post ) {
        wp_nonce_field( 'sb_save_product_meta', 'sb_product_meta_nonce' );

        $price      = get_post_meta( $post->ID, '_sb_price',         true );
        $seats      = get_post_meta( $post->ID, '_sb_seats',         true ) ?: 6;
        $badge      = get_post_meta( $post->ID, '_sb_badge',         true );
        $location   = get_post_meta( $post->ID, '_sb_location',      true );
        $start_hour = get_post_meta( $post->ID, '_sb_start_hour',    true ) ?: 7;
        $end_hour   = get_post_meta( $post->ID, '_sb_end_hour',      true ) ?: 22;
        $slot_dur   = get_post_meta( $post->ID, '_sb_slot_duration', true ) ?: 60;
        $features   = get_post_meta( $post->ID, '_sb_features',      true );
        $age_limit  = get_post_meta( $post->ID, '_sb_age_limit',     true ) ?: 18;
        $gallery    = get_post_meta( $post->ID, '_sb_gallery',       true );
        ?>
        <style>
            .sb-mg { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
            .sb-mf { display:flex; flex-direction:column; gap:4px; }
            .sb-mf label { font-weight:600; font-size:13px; color:#333; }
            .sb-mf input,.sb-mf textarea,.sb-mf select { border:1px solid #ddd; border-radius:4px; padding:6px 10px; font-size:13px; width:100%; box-sizing:border-box; }
            .sb-mf-full { grid-column:1/-1; }
            .sb-meta-note { font-size:11px; color:#888; margin-top:2px; }
        </style>
        <div class="sb-mg">
            <div class="sb-mf">
                <label>Price (<?php echo esc_html(get_option('sb_currency','PHP')); ?>) *</label>
                <input type="number" step="0.01" name="sb_price" value="<?php echo esc_attr($price); ?>" placeholder="15.95" />
            </div>
            <div class="sb-mf">
                <label>Total Seats Per Slot *</label>
                <input type="number" name="sb_seats" value="<?php echo esc_attr($seats); ?>" min="1" />
            </div>
            <div class="sb-mf">
                <label>City Badge (shown on card)</label>
                <input type="text" name="sb_badge" value="<?php echo esc_attr($badge); ?>" placeholder="DUBLIN" />
            </div>
            <div class="sb-mf">
                <label>Location Address (shown on detail page)</label>
                <input type="text" name="sb_location" value="<?php echo esc_attr($location); ?>" placeholder="Bolands Mills, Barrow Street, Dublin" />
            </div>
            <div class="sb-mf">
                <label>Opening Hour (24h, e.g. 7 = 7am)</label>
                <input type="number" name="sb_start_hour" value="<?php echo esc_attr($start_hour); ?>" min="0" max="23" />
            </div>
            <div class="sb-mf">
                <label>Closing Hour (24h, e.g. 22 = 10pm)</label>
                <input type="number" name="sb_end_hour" value="<?php echo esc_attr($end_hour); ?>" min="1" max="24" />
            </div>
            <div class="sb-mf">
                <label>Slot Duration</label>
                <select name="sb_slot_duration">
                    <option value="60"  <?php selected($slot_dur,60);  ?>>60 min (1 hour)</option>
                    <option value="75"  <?php selected($slot_dur,75);  ?>>75 min</option>
                    <option value="90"  <?php selected($slot_dur,90);  ?>>90 min</option>
                    <option value="120" <?php selected($slot_dur,120); ?>>120 min</option>
                </select>
            </div>
            <div class="sb-mf">
                <label>Minimum Age</label>
                <input type="number" name="sb_age_limit" value="<?php echo esc_attr($age_limit); ?>" min="0" />
            </div>
            <div class="sb-mf sb-mf-full">
                <label>Amenities / Features <span class="sb-meta-note">(comma-separated)</span></label>
                <input type="text" name="sb_features" value="<?php echo esc_attr($features); ?>" placeholder="Plunge Pools, Changing Facilities, Toilets, Hot Shower" />
            </div>
            <div class="sb-mf sb-mf-full">
                <label>Gallery Image URLs <span class="sb-meta-note">(one URL per line — used as thumbnails)</span></label>
                <textarea name="sb_gallery" rows="4" placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg"><?php echo esc_textarea($gallery); ?></textarea>
            </div>
        </div>
        <?php
    }

    /* ── BOX 2: Accordion Content Sections ────────────────────────────────── */
    public static function render_content_box( $post ) {
        wp_nonce_field( 'sb_save_content_meta', 'sb_content_meta_nonce' );

        $about     = get_post_meta( $post->ID, '_sb_about',          true );
        $ritual    = get_post_meta( $post->ID, '_sb_ritual',         true );
        $important = get_post_meta( $post->ID, '_sb_important_info', true );
        $parking   = get_post_meta( $post->ID, '_sb_parking',        true );
        ?>
        <style>
            .sb-acc-section { margin-bottom:18px; padding-bottom:18px; border-bottom:1px solid #eee; }
            .sb-acc-section:last-child { border-bottom:none; margin-bottom:0; padding-bottom:0; }
            .sb-acc-section label { font-weight:700; font-size:13px; display:block; margin-bottom:6px; }
            .sb-acc-section .sb-acc-hint { font-size:11px; color:#888; font-weight:400; margin-left:6px; }
            .sb-acc-section textarea { width:100%; border:1px solid #ddd; border-radius:4px; padding:8px 10px; font-size:13px; box-sizing:border-box; line-height:1.6; }
        </style>
        <p style="color:#555;font-size:13px;margin-top:0;">These are the expandable accordion sections shown on the product detail page. Leave a section blank to hide it.</p>

        <div class="sb-acc-section">
            <label>About This Event <span class="sb-acc-hint">— always shown, can be expanded/collapsed</span></label>
            <textarea name="sb_about" rows="8" placeholder="Describe the sauna experience, what to expect, what's included..."><?php echo esc_textarea($about); ?></textarea>
        </div>

        <div class="sb-acc-section">
            <label>Ritual Schedule <span class="sb-acc-hint">— optional accordion section</span></label>
            <textarea name="sb_ritual" rows="5" placeholder="Describe the ritual schedule, timings, guide-led sessions..."><?php echo esc_textarea($ritual); ?></textarea>
        </div>

        <div class="sb-acc-section">
            <label>Important Information <span class="sb-acc-hint">— optional accordion section</span></label>
            <textarea name="sb_important_info" rows="5" placeholder="Age restrictions, health warnings, what to bring, cancellation policy..."><?php echo esc_textarea($important); ?></textarea>
        </div>

        <div class="sb-acc-section">
            <label>Parking <span class="sb-acc-hint">— optional accordion section</span></label>
            <textarea name="sb_parking" rows="3" placeholder="Parking instructions, nearby car parks, public transport..."><?php echo esc_textarea($parking); ?></textarea>
        </div>
        <?php
    }

    /* ── BOX 3: FAQs ───────────────────────────────────────────────────────── */
    public static function render_faq_box( $post ) {
        wp_nonce_field( 'sb_save_faq_meta', 'sb_faq_meta_nonce' );
        $faqs = get_post_meta( $post->ID, '_sb_faqs', true );
        if ( ! is_array($faqs) ) $faqs = [];
        while ( count($faqs) < 3 ) $faqs[] = [ 'question' => '', 'answer' => '' ];
        ?>
        <style>
            .sb-faq-row { background:#f9f9f9; border:1px solid #e5e5e5; border-radius:4px; padding:12px 14px; margin-bottom:10px; position:relative; }
            .sb-faq-row label { font-weight:600; font-size:12px; color:#555; display:block; margin-bottom:4px; }
            .sb-faq-row input[type="text"] { width:100%; border:1px solid #ddd; border-radius:4px; padding:6px 10px; font-size:13px; margin-bottom:8px; box-sizing:border-box; }
            .sb-faq-row textarea { width:100%; border:1px solid #ddd; border-radius:4px; padding:6px 10px; font-size:13px; box-sizing:border-box; }
            .sb-faq-remove { position:absolute; top:10px; right:10px; background:#cc0000; color:#fff; border:none; border-radius:3px; padding:2px 8px; cursor:pointer; font-size:11px; }
        </style>
        <p style="color:#555;font-size:13px;margin-top:0;">FAQs appear as an accordion section at the bottom of the product page.</p>
        <div id="sb-faqs-wrap">
            <?php foreach ( $faqs as $i => $faq ) : ?>
            <div class="sb-faq-row">
                <button type="button" class="sb-faq-remove" onclick="this.closest('.sb-faq-row').remove()">✕ Remove</button>
                <label>Question</label>
                <input type="text" name="sb_faqs[<?php echo $i; ?>][question]" value="<?php echo esc_attr($faq['question']); ?>" placeholder="e.g. What do I need to bring?" />
                <label>Answer</label>
                <textarea name="sb_faqs[<?php echo $i; ?>][answer]" rows="3" placeholder="Your answer..."><?php echo esc_textarea($faq['answer']); ?></textarea>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button" id="sbAddFaq" style="margin-top:6px;">+ Add FAQ</button>
        <script>
        document.getElementById('sbAddFaq').addEventListener('click', function(){
            var wrap = document.getElementById('sb-faqs-wrap');
            var idx  = wrap.querySelectorAll('.sb-faq-row').length;
            var row  = document.createElement('div');
            row.className = 'sb-faq-row';
            row.innerHTML  = '<button type="button" class="sb-faq-remove" onclick="this.closest(\'.sb-faq-row\').remove()">✕ Remove</button>';
            row.innerHTML += '<label>Question</label><input type="text" name="sb_faqs[' + idx + '][question]" placeholder="e.g. What should I wear?" />';
            row.innerHTML += '<label>Answer</label><textarea name="sb_faqs[' + idx + '][answer]" rows="3" placeholder="Your answer..."></textarea>';
            wrap.appendChild(row);
        });
        </script>
        <?php
    }

    /* ── Save all meta ─────────────────────────────────────────────────────── */
    public static function save_meta( $post_id ) {
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( get_post_type($post_id) !== 'sauna_product' ) return;

        $has_details = isset($_POST['sb_product_meta_nonce'])
                    && wp_verify_nonce($_POST['sb_product_meta_nonce'], 'sb_save_product_meta');
        $has_content = isset($_POST['sb_content_meta_nonce'])
                    && wp_verify_nonce($_POST['sb_content_meta_nonce'], 'sb_save_content_meta');
        $has_faqs    = isset($_POST['sb_faq_meta_nonce'])
                    && wp_verify_nonce($_POST['sb_faq_meta_nonce'], 'sb_save_faq_meta');

        /* Details fields */
        if ( $has_details ) {
            $simple = [
                'sb_price'         => '_sb_price',
                'sb_seats'         => '_sb_seats',
                'sb_location'      => '_sb_location',
                'sb_badge'         => '_sb_badge',
                'sb_start_hour'    => '_sb_start_hour',
                'sb_end_hour'      => '_sb_end_hour',
                'sb_slot_duration' => '_sb_slot_duration',
                'sb_features'      => '_sb_features',
                'sb_age_limit'     => '_sb_age_limit',
                'sb_gallery'       => '_sb_gallery',
            ];
            foreach ( $simple as $key => $meta ) {
                if ( isset($_POST[$key]) ) {
                    update_post_meta( $post_id, $meta, sanitize_textarea_field($_POST[$key]) );
                }
            }
        }

        /* Accordion content fields */
        if ( $has_content ) {
            $sections = [
                'sb_about'          => '_sb_about',
                'sb_ritual'         => '_sb_ritual',
                'sb_important_info' => '_sb_important_info',
                'sb_parking'        => '_sb_parking',
            ];
            foreach ( $sections as $key => $meta ) {
                if ( isset($_POST[$key]) ) {
                    update_post_meta( $post_id, $meta, wp_kses_post( $_POST[$key] ) );
                }
            }
        }

        /* FAQ fields */
        if ( $has_faqs ) {
            if ( isset($_POST['sb_faqs']) && is_array($_POST['sb_faqs']) ) {
                $clean = [];
                foreach ( $_POST['sb_faqs'] as $faq ) {
                    $q = sanitize_text_field( $faq['question'] ?? '' );
                    $a = sanitize_textarea_field( $faq['answer']   ?? '' );
                    if ( $q || $a ) $clean[] = [ 'question' => $q, 'answer' => $a ];
                }
                update_post_meta( $post_id, '_sb_faqs', $clean );
            } else {
                update_post_meta( $post_id, '_sb_faqs', [] );
            }
        }
    }
}
