<?php
if (! defined('ABSPATH')) exit;

class SB_Accommodation_Post_Type
{

    public static function init()
    {
        add_action('init',           [__CLASS__, 'register_cpt']);
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post',      [__CLASS__, 'save_meta']);
    }

    /* ── Register CPT ──────────────────────────────────────────────────────── */
    public static function register_cpt()
    {
        register_post_type('accommodation_room', [
            'labels' => [
                'name'               => 'Accommodation Rooms',
                'singular_name'      => 'Room Type',
                'add_new'            => 'Add New',
                'add_new_item'       => 'Add New Room Type',
                'edit_item'          => 'Edit Room Type',
                'view_item'          => 'View Room Type',
                'search_items'       => 'Search Room Types',
                'not_found'          => 'No room types found',
                'not_found_in_trash' => 'No room types found in trash',
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'query_var'          => true,
            'has_archive'        => true,
            'supports'           => ['title', 'thumbnail', 'editor'],
            'show_in_rest'       => false,
            'menu_icon'          => 'dashicons-building',
            'rewrite'            => ['slug' => 'accommodation'],
        ]);
    }

    /* ── Meta boxes ────────────────────────────────────────────────────────── */
    public static function add_meta_boxes()
    {
        add_meta_box( 'sb_room_details', 'Room Details',              [__CLASS__, 'render_details_box'], 'accommodation_room', 'normal', 'high' );
        add_meta_box( 'sb_room_content', 'Page Content (Accordions)', [__CLASS__, 'render_content_box'], 'accommodation_room', 'normal', 'default' );
        add_meta_box( 'sb_room_faqs',    'FAQs',                       [__CLASS__, 'render_faq_box'],     'accommodation_room', 'normal', 'default' );
    }

    /* ── BOX: Room Details ─────────────────────────────────────────────────── */
    public static function render_details_box($post)
    {
        wp_nonce_field('sb_save_room_meta', 'sb_room_meta_nonce');

        $price_per_night = get_post_meta($post->ID, '_sb_price_per_night',  true);
        $room_category   = get_post_meta($post->ID, '_sb_room_category',    true);
        $max_occupants   = get_post_meta($post->ID, '_sb_max_occupants',    true) ?: 2;
        $location        = get_post_meta($post->ID, '_sb_location',         true);
        $amenities       = get_post_meta($post->ID, '_sb_amenities',        true);
        $gallery         = get_post_meta($post->ID, '_sb_gallery',          true);
        $description     = get_post_meta($post->ID, '_sb_description',      true);
?>
        <style>
            .sb-mg { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
            .sb-mf { display:flex; flex-direction:column; gap:4px; }
            .sb-mf label { font-weight:600; font-size:13px; color:#333; }
            .sb-mf input,.sb-mf textarea,.sb-mf select { border:1px solid #ddd; border-radius:4px; padding:8px 10px; font-size:13px; width:100%; box-sizing:border-box; }
            .sb-mf-full { grid-column:1/-1; }
            .sb-meta-note { font-size:11px; color:#888; margin-top:3px; }
        </style>

        <div class="sb-mg">
            <div class="sb-mf">
                <label>Price Per Night (<?php echo esc_html(get_option('sb_currency', 'PHP')); ?>) *</label>
                <input type="number" step="0.01" name="sb_price_per_night" value="<?php echo esc_attr($price_per_night); ?>" placeholder="80.00" required />
            </div>

            <div class="sb-mf">
                <label>Room Category *</label>
                <select name="sb_room_category" required>
                    <option value="">-- Select Category --</option>
                    <option value="Standard Room" <?php selected($room_category, 'Standard Room'); ?>>Standard Room</option>
                    <option value="Deluxe Suite" <?php selected($room_category, 'Deluxe Suite'); ?>>Deluxe Suite</option>
                    <option value="Premium Room" <?php selected($room_category, 'Premium Room'); ?>>Premium Room</option>
                    <option value="Economy Room" <?php selected($room_category, 'Economy Room'); ?>>Economy Room</option>
                </select>
            </div>

            <div class="sb-mf">
                <label>Max Occupants *</label>
                <input type="number" name="sb_max_occupants" value="<?php echo esc_attr($max_occupants); ?>" min="1" max="10" required />
            </div>

            <div class="sb-mf">
                <label>Room Type Display Name</label>
                <input type="text" name="sb_room_type_name" value="<?php echo esc_attr(get_the_title($post->ID)); ?>" placeholder="e.g., Deluxe Studio" />
            </div>

            <div class="sb-mf">
                <label>Location Address (shown on card & detail page)</label>
                <input type="text" name="sb_location" value="<?php echo esc_attr($location); ?>" placeholder="Upper Cabanagcalaan, Lazi, Siquijor" />
            </div>

            <div class="sb-mf sb-mf-full">
                <label>Gallery (one URL per line)</label>
                <textarea name="sb_gallery" rows="4" placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg"><?php echo esc_textarea($gallery); ?></textarea>
            </div>
        </div>
<?php
    }

    /* ── BOX: Accordion Content Sections ────────────────────────────────── */
    public static function render_content_box($post)
    {
        wp_nonce_field('sb_save_acc_content_meta', 'sb_acc_content_meta_nonce');

        $about     = get_post_meta($post->ID, '_sb_about',          true);
        $important = get_post_meta($post->ID, '_sb_important_info', true);
        $location  = get_post_meta($post->ID, '_sb_location_info',  true);
        ?>
        <style>
            .sb-acc-section { margin-bottom:18px; padding-bottom:18px; border-bottom:1px solid #eee; }
            .sb-acc-section:last-child { border-bottom:none; margin-bottom:0; padding-bottom:0; }
            .sb-acc-section label { font-weight:700; font-size:13px; display:block; margin-bottom:6px; }
            .sb-acc-section textarea { width:100%; border:1px solid #ddd; border-radius:4px; padding:8px 10px; font-size:13px; box-sizing:border-box; line-height:1.6; }
        </style>
        <p style="color:#555;font-size:13px;margin-top:0;">These sections appear as accordions on the room detail page.</p>

        <div class="sb-acc-section">
            <label>About This Room</label>
            <textarea name="sb_about" rows="8" placeholder="Describe the room, beds, view..."><?php echo esc_textarea($about); ?></textarea>
        </div>

        <div class="sb-acc-section">
            <label>Important Information</label>
            <textarea name="sb_important_info" rows="5" placeholder="House rules, check-in instructions, cancellation policy..."><?php echo esc_textarea($important); ?></textarea>
        </div>

        <div class="sb-acc-section">
            <label>Location Info</label>
            <textarea name="sb_location_info" rows="3" placeholder="How to get there, parking, nearby attractions..."><?php echo esc_textarea($location); ?></textarea>
        </div>
        <?php
    }

    /* ── BOX: FAQs ───────────────────────────────────────────────────────── */
    public static function render_faq_box($post)
    {
        wp_nonce_field('sb_save_acc_faq_meta', 'sb_acc_faq_meta_nonce');
        $faqs = get_post_meta($post->ID, '_sb_faqs', true);
        if (! is_array($faqs)) $faqs = [];
        if (empty($faqs)) $faqs[] = ['question' => '', 'answer' => ''];
        ?>
        <style>
            .sb-faq-row { background:#f9f9f9; border:1px solid #e5e5e5; border-radius:4px; padding:12px 14px; margin-bottom:10px; position:relative; }
            .sb-faq-row label { font-weight:600; font-size:12px; color:#555; display:block; margin-bottom:4px; }
            .sb-faq-row input[type="text"] { width:100%; border:1px solid #ddd; border-radius:4px; padding:6px 10px; font-size:13px; margin-bottom:8px; box-sizing:border-box; }
            .sb-faq-row textarea { width:100%; border:1px solid #ddd; border-radius:4px; padding:6px 10px; font-size:13px; box-sizing:border-box; }
            .sb-faq-remove { position:absolute; top:10px; right:10px; background:#cc0000; color:#fff; border:none; border-radius:3px; padding:2px 8px; cursor:pointer; font-size:11px; }
        </style>
        <div id="sb-acc-faqs-wrap">
            <?php foreach ($faqs as $i => $faq) : ?>
            <div class="sb-faq-row">
                <button type="button" class="sb-faq-remove" onclick="this.closest('.sb-faq-row').remove()">✕ Remove</button>
                <label>Question</label>
                <input type="text" name="sb_faqs[<?php echo $i; ?>][question]" value="<?php echo esc_attr($faq['question']); ?>" />
                <label>Answer</label>
                <textarea name="sb_faqs[<?php echo $i; ?>][answer]" rows="3"><?php echo esc_textarea($faq['answer']); ?></textarea>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button" id="sbAddAccFaq">+ Add FAQ</button>
        <script>
        document.getElementById('sbAddAccFaq').addEventListener('click', function(){
            var wrap = document.getElementById('sb-acc-faqs-wrap');
            var idx  = wrap.querySelectorAll('.sb-faq-row').length;
            var row  = document.createElement('div');
            row.className = 'sb-faq-row';
            row.innerHTML  = '<button type="button" class="sb-faq-remove" onclick="this.closest(\'.sb-faq-row\').remove()">✕ Remove</button>';
            row.innerHTML += '<label>Question</label><input type="text" name="sb_faqs[' + idx + '][question]" />';
            row.innerHTML += '<label>Answer</label><textarea name="sb_faqs[' + idx + '][answer]" rows="3"></textarea>';
            wrap.appendChild(row);
        });
        </script>
        <?php
    }

    /* ── Save meta ─────────────────────────────────────────────────────────– */
    public static function save_meta($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (! current_user_can('edit_post', $post_id)) return;
        if (get_post_type($post_id) !== 'accommodation_room') return;

        /* Details */
        if (isset($_POST['sb_room_meta_nonce']) && wp_verify_nonce($_POST['sb_room_meta_nonce'], 'sb_save_room_meta')) {
            $fields = [
                'sb_price_per_night' => 'sanitize_text_field',
                'sb_room_category'   => 'sanitize_text_field',
                'sb_max_occupants'   => 'absint',
                'sb_location'        => 'sanitize_text_field',
                'sb_gallery'         => 'sanitize_textarea_field',
            ];
            foreach ($fields as $field => $sanitize) {
                if (isset($_POST[$field])) {
                    update_post_meta($post_id, '_' . $field, call_user_func($sanitize, $_POST[$field]));
                }
            }
        }

        /* Content */
        if (isset($_POST['sb_acc_content_meta_nonce']) && wp_verify_nonce($_POST['sb_acc_content_meta_nonce'], 'sb_save_acc_content_meta')) {
            update_post_meta($post_id, '_sb_about',          wp_kses_post($_POST['sb_about'] ?? ''));
            update_post_meta($post_id, '_sb_important_info', wp_kses_post($_POST['sb_important_info'] ?? ''));
            update_post_meta($post_id, '_sb_location_info',  wp_kses_post($_POST['sb_location_info'] ?? ''));
        }

        /* FAQs */
        if (isset($_POST['sb_acc_faq_meta_nonce']) && wp_verify_nonce($_POST['sb_acc_faq_meta_nonce'], 'sb_save_acc_faq_meta')) {
            if (isset($_POST['sb_faqs']) && is_array($_POST['sb_faqs'])) {
                $clean = [];
                foreach ($_POST['sb_faqs'] as $faq) {
                    $q = sanitize_text_field($faq['question'] ?? '');
                    $a = sanitize_textarea_field($faq['answer'] ?? '');
                    if ($q || $a) $clean[] = ['question' => $q, 'answer' => $a];
                }
                update_post_meta($post_id, '_sb_faqs', $clean);
            } else {
                update_post_meta($post_id, '_sb_faqs', []);
            }
        }
    }
}
