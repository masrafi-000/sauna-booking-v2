<?php

/**
 * Template: Single Sauna Product  v2.0.0
 * - White/green clean aesthetic
 * - Left col: image gallery + FAQs below image
 * - Right col: details, booking types, SELECT A TIME, meta, accordions, features, location
 * - Full-width below: Additional Products
 * - All functionality identical to v1
 */
if (! defined('ABSPATH')) exit;

get_header();
while (have_posts()) : the_post();

    /* ── Meta ─────────────────────────────────────────────────────────────────── */
    $pid         = get_the_ID();
    $title       = get_the_title();
    $price       = get_post_meta($pid, '_sb_price',         true);
    $seats       = get_post_meta($pid, '_sb_seats',         true) ?: 6;
    $badge       = get_post_meta($pid, '_sb_badge',         true);
    $location    = get_post_meta($pid, '_sb_location',      true);
    $features    = get_post_meta($pid, '_sb_features',      true);
    $age_limit   = get_post_meta($pid, '_sb_age_limit',     true) ?: 18;
    $gallery_raw = get_post_meta($pid, '_sb_gallery',       true);
    $about       = get_post_meta($pid, '_sb_about',         true);
    $ritual      = get_post_meta($pid, '_sb_ritual',        true);
    $important   = get_post_meta($pid, '_sb_important_info', true);
    $parking     = get_post_meta($pid, '_sb_parking',       true);
    $faqs        = get_post_meta($pid, '_sb_faqs',          true);
    if (! is_array($faqs)) $faqs = [];

    $currency      = get_option('sb_currency_symbol', '₱');
    $currency_code = get_option('sb_currency', 'PHP');

    $gallery_urls = $gallery_raw
        ? array_values(array_filter(array_map('trim', explode("\n", $gallery_raw))))
        : [];

    $feat_list = $features
        ? array_values(array_filter(array_map('trim', explode(',', $features))))
        : [];

    /* Feature icon map */
    $feat_icons = [
        'Plunge Pools'             => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12 C6 8 10 16 14 12 C18 8 22 12 22 12"/><path d="M2 20 C6 16 10 24 14 20 C18 16 22 20 22 20"/></svg>',
        'Varied Temp Plunge Pools' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12 C6 8 10 16 14 12 C18 8 22 12 22 12"/></svg>',
        'Changing Facilities'      => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'Toilets'                  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>',
        'Hot Shower'               => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12h8M4 8h8M4 16h8"/></svg>',
        'Indoor hot shower'        => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12h8M4 8h8M4 16h8"/></svg>',
        'Indoor Hot Shower'        => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12h8M4 8h8M4 16h8"/></svg>',
    ];
    $default_icon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';

    /* Hero image */
    $main_image = get_the_post_thumbnail_url($pid, 'full');
    if (! $main_image && ! empty($gallery_urls)) $main_image = $gallery_urls[0];
    $all_thumbs = [];
    if ($main_image) $all_thumbs[] = $main_image;
    foreach ($gallery_urls as $u) {
        if ($u !== $main_image) $all_thumbs[] = $u;
    }

    /* Additional products (other sauna_product posts) */
    $additional_products = get_posts([
        'post_type'      => 'sauna_product',
        'posts_per_page' => 4,
        'post__not_in'   => [$pid],
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    /* Valid FAQs */
    $valid_faqs = array_filter($faqs, fn($f) => ! empty($f['question']));
?>

<div class="sb-page-wrap">

    <!-- ═══════════════════════════════════════════════════════
     TWO-COLUMN GRID
═══════════════════════════════════════════════════════ -->
    <div class="sb-single-product" data-product-id="<?php echo $pid; ?>" data-price="<?php echo esc_attr($price); ?>">

        <!-- ─── LEFT: image gallery + FAQs ─── -->
        <div class="sb-product-hero">

            <!-- Hero image -->
            <div class="sb-hero-main">
                <?php if ($main_image) : ?>
                <img src="<?php echo esc_url($main_image); ?>" alt="<?php echo esc_attr($title); ?>" class="sb-hero-img"
                    id="sb-main-img" />
                <?php else : ?>
                <div class="sb-hero-placeholder"></div>
                <?php endif; ?>
            </div>

            <!-- Thumbnails strip -->
            <?php if (count($all_thumbs) > 1) : ?>
            <div class="sb-hero-thumbs">
                <?php foreach ($all_thumbs as $i => $url) : ?>
                <img src="<?php echo esc_url($url); ?>" class="sb-thumb<?php echo $i === 0 ? ' active' : ''; ?>"
                    onclick="sbChangeImage(this,'<?php echo esc_js($url); ?>')" alt="" loading="lazy" />
                <?php endforeach; ?>
            </div>
            <?php endif; ?>



        </div><!-- .sb-product-hero -->

        <!-- ─── RIGHT: details panel ─── -->
        <div class="sb-product-details">

            <h1 class="sb-detail-title"><?php echo esc_html($title); ?></h1>

            <?php if ($price) : ?>
            <div class="sb-detail-price">
                Price:
                <strong><?php echo esc_html($currency . number_format((float)$price, 2, '.', '') . ' ' . $currency_code); ?></strong>
                per person
            </div>
            <?php endif; ?>

            <!-- Booking type pill tabs -->
            <!-- <div class="sb-booking-types">
                <button class="sb-type-btn active" data-type="early_bird">Early Bird</button>
                <button class="sb-type-btn" data-type="off_peak">Off Peak</button>
                <button class="sb-type-btn" data-type="peak">Peak</button>
            </div> -->

            <!-- CTA button -->
            <button class="sb-select-time-btn" id="sbOpenCalendar">Select a Time</button>
            <!-- FAQs inline below the gallery (left column) -->
            <?php if (! empty($valid_faqs)) : ?>
            <div class="sb-faqs-section sb-faq-custom-wrap" style="margin-top:36px;">
                <?php foreach ($valid_faqs as $faq) : ?>
                <div class="sb-faq-item sb-faq-new-style">
                    <button class="sb-faq-btn" onclick="sbToggleFaq(this)" type="button">
                        <span><?php echo esc_html($faq['question']); ?></span>
                        <svg class="sb-chevron" width="17" height="17" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9" />
                        </svg>
                    </button>
                    <div class="sb-faq-answer sb-faq-new-answer">
                        <p><?php echo wp_kses_post(nl2br($faq['answer'])); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Seats / age meta -->
            <div class="sb-detail-meta">
                <span class="sb-meta-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                    <?php echo esc_html($seats); ?> seats
                </span>
                <span class="sb-meta-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg>
                    Ages <?php echo esc_html($age_limit); ?>+
                </span>
                <?php if ($location) : ?>
                <span class="sb-meta-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                        <circle cx="12" cy="10" r="3" />
                    </svg>
                    <?php echo esc_html($location); ?>
                </span>
                <?php endif; ?>
            </div>

            <hr class="sb-divider" />

            <!-- About this event accordion -->
            <?php if ($about) : ?>
            <div class="sb-detail-section">
                <button class="sb-accordion-btn active" onclick="sbToggleAccordion(this)">
                    About this event
                    <svg class="sb-chevron" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <polyline points="18 15 12 9 6 15" />
                    </svg>
                </button>
                <div class="sb-accordion-body open">
                    <div class="sb-detail-content"><?php echo wpautop(wp_kses_post($about)); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Ritual Schedule accordion -->
            <?php if ($ritual) : ?>
            <div class="sb-detail-section">
                <button class="sb-accordion-btn" onclick="sbToggleAccordion(this)">
                    Ritual Schedule
                    <svg class="sb-chevron" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </button>
                <div class="sb-accordion-body">
                    <div class="sb-detail-content"><?php echo wpautop(wp_kses_post($ritual)); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Important Information accordion -->
            <?php if ($important) : ?>
            <div class="sb-detail-section">
                <button class="sb-accordion-btn" onclick="sbToggleAccordion(this)">
                    Important Information
                    <svg class="sb-chevron" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </button>
                <div class="sb-accordion-body">
                    <div class="sb-detail-content"><?php echo wpautop(wp_kses_post($important)); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Parking accordion -->
            <?php if ($parking) : ?>
            <div class="sb-detail-section">
                <button class="sb-accordion-btn" onclick="sbToggleAccordion(this)">
                    Parking
                    <svg class="sb-chevron" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </button>
                <div class="sb-accordion-body">
                    <div class="sb-detail-content"><?php echo wpautop(wp_kses_post($parking)); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Features box -->
            <?php if (! empty($feat_list)) : ?>
            <div class="sb-features-box">
                <h3 class="sb-features-title">What's at this sauna?</h3>
                <div class="sb-features-grid">
                    <?php foreach ($feat_list as $feat) :
                                $icon = $feat_icons[$feat] ?? $default_icon; ?>
                    <div class="sb-feature-item">
                        <?php echo $icon; ?>
                        <span><?php echo esc_html($feat); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- .sb-product-details -->
    </div><!-- .sb-single-product -->

    <!-- ═══════════════════════════════════════════════════════
     FULL-WIDTH BELOW: Additional Products
═══════════════════════════════════════════════════════ -->
    <?php if (! empty($additional_products)) : ?>
    <div class="sb-single-below">
        <hr class="sb-below-divider" />
        <h2 class="sb-section-heading">Other Experiences</h2>
        <div class="sb-products-grid sb-cols-<?php echo min(count($additional_products), 3); ?>">
            <?php foreach ($additional_products as $ap) :
                        $ap_price   = get_post_meta($ap->ID, '_sb_price',   true);
                        $ap_seats   = get_post_meta($ap->ID, '_sb_seats',   true) ?: 6;
                        $ap_badge   = get_post_meta($ap->ID, '_sb_badge',   true);
                        $ap_img     = get_the_post_thumbnail_url($ap->ID, 'medium_large');
                        $ap_url     = get_permalink($ap->ID);
                    ?>
            <a href="<?php echo esc_url($ap_url); ?>" class="sb-product-card">
                <div class="sb-card-image">
                    <?php if ($ap_img) : ?>
                    <img src="<?php echo esc_url($ap_img); ?>" alt="<?php echo esc_attr($ap->post_title); ?>"
                        loading="lazy" />
                    <?php else : ?>
                    <div class="sb-card-placeholder">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <circle cx="8.5" cy="8.5" r="1.5" />
                            <polyline points="21 15 16 10 5 21" />
                        </svg>
                    </div>
                    <?php endif; ?>
                    <?php if ($ap_badge) : ?>
                    <span class="sb-card-badge"><?php echo esc_html($ap_badge); ?></span>
                    <?php endif; ?>
                </div>
                <div class="sb-card-body">
                    <h3 class="sb-card-title"><?php echo esc_html($ap->post_title); ?></h3>
                    <div class="sb-card-meta">
                        <span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                            </svg>
                            <?php echo esc_html($ap_seats); ?> seats
                        </span>
                    </div>
                    <?php if ($ap_price) : ?>
                    <div class="sb-card-price">
                        <span class="sb-price-from">From</span>
                        <span
                            class="sb-price-amount"><?php echo esc_html($currency . number_format((float)$ap_price, 2, '.', '')); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- .sb-page-wrap -->

<!-- ═══════════════════════════════════════════════════════
     POPUP 1 – Calendar & Time Slots
═══════════════════════════════════════════════════════ -->
<div class="sb-overlay" id="sbCalendarOverlay">
    <div class="sb-popup sb-popup-calendar">
        <button class="sb-popup-close" id="sbCloseCalendar" aria-label="Close">&times;</button>

        <!-- <div class="sb-popup-select-wrap">
            <select class="sb-popup-type-select" id="sbTypeSelect">
                <option value="early_bird">Early Bird</option>
                <option value="off_peak">Off Peak</option>
                <option value="peak">Peak</option>
            </select>
        </div> -->

        <!-- <h2 class="sb-popup-title">Select a time for <?php echo esc_html($title); ?></h2> -->

        <p class="sb-popup-tz">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
            </svg>
            Timezone: Philippine Standard Time
        </p>

        <div class="sb-popup-body">
            <div class="sb-calendar-wrap">
                <div class="sb-cal-nav">
                    <button class="sb-cal-prev" id="sbCalPrev" aria-label="Previous month">&#8249;</button>
                    <span class="sb-cal-month-label" id="sbCalMonthLabel"></span>
                    <button class="sb-cal-next" id="sbCalNext" aria-label="Next month">&#8250;</button>
                </div>
                <div class="sb-cal-grid">
                    <div class="sb-cal-days-header">
                        <span>SU</span><span>MO</span><span>TU</span><span>WE</span>
                        <span>TH</span><span>FR</span><span>SA</span>
                    </div>
                    <div class="sb-cal-days" id="sbCalDays"></div>
                </div>
            </div>
            <div class="sb-slots-panel">
                <div class="sb-slots-date-label" id="sbSlotsDateLabel">Select a date to view available times</div>
                <div class="sb-slots-list" id="sbSlotsList">
                    <p class="sb-slots-placeholder">No timeslots available — have you selected the correct offering
                        above?</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     POPUP 2 – Booking Form + Stripe
═══════════════════════════════════════════════════════ -->
<div class="sb-overlay" id="sbBookingOverlay">
    <div class="sb-popup sb-popup-booking">
        <button class="sb-popup-close" id="sbCloseBooking" aria-label="Close">&times;</button>
        <h2 class="sb-popup-title">Request your Booking</h2>
        <div class="sb-booking-summary" id="sbBookingSummary"></div>

        <form id="sbBookingForm" class="sb-booking-form" novalidate>
            <input type="hidden" id="sbProductId" value="<?php echo $pid; ?>" />
            <input type="hidden" id="sbBookingDate" />
            <input type="hidden" id="sbSlotStart" />
            <input type="hidden" id="sbSlotEnd" />
            <input type="hidden" id="sbBookingIdHidden" />

            <h3 class="sb-form-section-title">Personal Information</h3>
            <div class="sb-form-row">
                <div class="sb-form-group">
                    <label for="sbFirstName">First Name *</label>
                    <input type="text" id="sbFirstName" name="first_name" required placeholder="John"
                        autocomplete="given-name" />
                </div>
                <div class="sb-form-group">
                    <label for="sbLastName">Last Name *</label>
                    <input type="text" id="sbLastName" name="last_name" required placeholder="Doe"
                        autocomplete="family-name" />
                </div>
            </div>
            <div class="sb-form-row">
                <div class="sb-form-group">
                    <label for="sbEmail">Email Address *</label>
                    <input type="email" id="sbEmail" name="email" required placeholder="john@example.com"
                        autocomplete="email" />
                </div>
                <div class="sb-form-group">
                    <label for="sbPhone">Phone Number</label>
                    <input type="tel" id="sbPhone" name="phone" placeholder="+353 1 234 5678" autocomplete="tel" />
                </div>
            </div>
            <div class="sb-form-row">
                <div class="sb-form-group">
                    <label for="sbSeats">Number of Seats *</label>
                    <select id="sbSeats" name="seats" required>
                        <?php for ($i = 1; $i <= $seats; $i++) : ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?> seat<?php echo $i > 1 ? 's' : ''; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="sb-form-group" style="margin-top:20px; margin-bottom:20px;">
                <label for="sbNotes">Additional Notes</label>
                <textarea id="sbNotes" name="notes" rows="3" placeholder="Any special requests or information?" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;"></textarea>
            </div>

            <div id="sbFormErrors" class="sb-card-errors" style="color:#b91c1c; background:#fef2f2; padding:12px; border-radius:8px; margin-bottom:20px; display:none;"></div>

            <div class="sb-amount-total" id="sbAccAmountTotal"></div>
            
            <button type="submit" class="sb-pay-btn" id="sbPayBtn">
                <span id="sbPayBtnText">Request Reservation</span>
                <span id="sbPayBtnSpinner" class="sb-spinner" style="display:none;"></span>
            </button>
            <div class="sb-secure-note">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                Your request will be reviewed by our team.
            </div>
        </form>

        <div id="sbBookingSuccess" class="sb-booking-success" style="display:none;">
            <div class="sb-success-icon">
                <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
            </div>
            <h3>Request Sent!</h3>
            <p id="sbSuccessMessage"></p>
            <button class="sb-close-success" onclick="sbCloseAll()">Close</button>
        </div>
    </div>
</div>

<script>
function sbChangeImage(el, url) {
    var img = document.getElementById('sb-main-img');
    if (img) img.src = url;
    document.querySelectorAll('.sb-thumb').forEach(function(t) {
        t.classList.remove('active');
    });
    el.classList.add('active');
}

function sbToggleAccordion(btn) {
    var body = btn.nextElementSibling;
    body.classList.toggle('open');
    btn.classList.toggle('active');
}

function sbToggleFaq(btn) {
    var ans = btn.nextElementSibling;
    var open = ans.classList.contains('open');
    document.querySelectorAll('.sb-faq-answer.open').forEach(function(el) {
        el.classList.remove('open');
        el.previousElementSibling.classList.remove('active');
    });
    if (!open) {
        ans.classList.add('open');
        btn.classList.add('active');
    }
}

function sbCloseAll() {
    document.getElementById('sbCalendarOverlay').classList.remove('active');
    document.getElementById('sbBookingOverlay').classList.remove('active');
    document.body.classList.remove('sb-overflow-hidden');
}
</script>

<?php endwhile; ?>
<?php get_footer(); ?>