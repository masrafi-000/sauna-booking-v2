<?php

/**
 * Template: Single Accommodation Room  v2.0.0
 * - Clean aesthetic matching sauna product
 * - Left col: image gallery + FAQs below image
 * - Right col: details, booking form, accordions, amenities
 */
if (! defined('ABSPATH')) exit;

get_header();

while (have_posts()) : the_post();
    $room_id         = get_the_ID();
    $title           = get_the_title();
    $price_per_night = get_post_meta($room_id, '_sb_price_per_night', true);
    $room_category   = get_post_meta($room_id, '_sb_room_category',   true);
    $max_occupants   = get_post_meta($room_id, '_sb_max_occupants',   true) ?: 2;
    $location        = trim((string) get_post_meta($room_id, '_sb_location', true));
    $gallery_raw     = get_post_meta($room_id, '_sb_gallery',         true);
    $about           = get_post_meta($room_id, '_sb_about',           true);
    $important       = get_post_meta($room_id, '_sb_important_info', true);
    $location_info   = get_post_meta($room_id, '_sb_location_info',  true);
    $faqs            = get_post_meta($room_id, '_sb_faqs',           true);
    if (! is_array($faqs)) $faqs = [];

    $currency        = get_option('sb_currency_symbol', '₱');
    $currency_code   = get_option('sb_currency', 'PHP');

    $gallery_urls = $gallery_raw
        ? array_values(array_filter(array_map('trim', explode("\n", $gallery_raw))))
        : [];

    $main_image = get_the_post_thumbnail_url($room_id, 'full');
    if (! $main_image && ! empty($gallery_urls)) {
        $main_image = $gallery_urls[0];
    }

    if ($location === '') {
        // Backward-compatible fallback for older rooms that only used location_info.
        $location = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $location_info)));
    }

    $all_thumbs = [];
    if ($main_image) $all_thumbs[] = $main_image;
    foreach ($gallery_urls as $u) {
        if ($u !== $main_image) $all_thumbs[] = $u;
    }

    $valid_faqs = array_filter($faqs, fn($f) => ! empty($f['question']));
?>

<div class="sb-page-wrap">
    <div class="sb-single-product" data-room-id="<?php echo $room_id; ?>" data-price="<?php echo esc_attr($price_per_night); ?>">

        <!-- LEFT: Gallery & FAQs -->
        <div class="sb-product-hero">
            <div class="sb-hero-main">
                <?php if ($main_image) : ?>
                    <img src="<?php echo esc_url($main_image); ?>" alt="<?php echo esc_attr($title); ?>" class="sb-hero-img" id="sb-main-img" />
                <?php else : ?>
                    <div class="sb-hero-placeholder"></div>
                <?php endif; ?>
            </div>

            <?php if (count($all_thumbs) > 1) : ?>
                <div class="sb-hero-thumbs">
                    <?php foreach ($all_thumbs as $i => $url) : ?>
                        <img src="<?php echo esc_url($url); ?>" class="sb-thumb<?php echo $i === 0 ? ' active' : ''; ?>" onclick="sbChangeImage(this, '<?php echo esc_js($url); ?>')" alt="" loading="lazy" />
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

        <!-- RIGHT: Details & Booking -->
        <div class="sb-product-details">
            <h1 class="sb-detail-title"><?php echo esc_html($title); ?></h1>
            <p class="sb-detail-price"><?php echo esc_html($room_category); ?></p>

            <div class="sb-card-price" style="margin-bottom: 30px;">
                <span class="sb-price-from">Price:</span>
                <span class="sb-price-amount"><?php echo esc_html($currency . number_format((float)$price_per_night, 2)); ?></span>
                <span class="sb-price-period">/ night</span>
            </div>

            <div class="sb-detail-meta">
                <span class="sb-meta-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                    <?php echo esc_html($max_occupants); ?> guests
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

            <!-- Booking Selection CTA -->
            <div class="sb-booking-cta" style="margin-bottom: 40px;">
                <button class="sb-select-time-btn" id="sbOpenCalendar">Select Dates</button>
            </div>

            <!-- FAQs moved from left column -->
            <?php if (! empty($valid_faqs)) : ?>
                <div class="sb-faqs-section" style="margin-bottom: 40px;">
                    <h2 class="sb-section-heading" style="font-size: 20px;">Frequently Asked Questions</h2>
                    <?php foreach ($valid_faqs as $faq) : ?>
                        <div class="sb-faq-item">
                            <button class="sb-faq-btn" onclick="sbToggleFaq(this)">
                                <span style="font-size: 15px;"><?php echo esc_html($faq['question']); ?></span>
                                <svg class="sb-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </button>
                            <div class="sb-faq-answer">
                                <p style="font-size: 14px;"><?php echo wp_kses_post(nl2br($faq['answer'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Accordions -->
            <div class="sb-accordions">
                <?php if ($about) : ?>
                    <div class="sb-detail-section">
                        <button class="sb-accordion-btn active" onclick="sbToggleAccordion(this)">
                            About this room
                            <svg class="sb-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="18 15 12 9 6 15"></polyline>
                            </svg>
                        </button>
                        <div class="sb-accordion-body open">
                            <div class="sb-detail-content"><?php echo wpautop(wp_kses_post($about)); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($important) : ?>
                    <div class="sb-detail-section">
                        <button class="sb-accordion-btn" onclick="sbToggleAccordion(this)">
                            Important Information
                            <svg class="sb-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="sb-accordion-body">
                            <div class="sb-detail-content"><?php echo wpautop(wp_kses_post($important)); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($location_info) : ?>
                    <div class="sb-detail-section">
                        <button class="sb-accordion-btn" onclick="sbToggleAccordion(this)">
                            Location & Parking
                            <svg class="sb-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="sb-accordion-body">
                            <div class="sb-detail-content"><?php echo wpautop(wp_kses_post($location_info)); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- .sb-product-details -->
    </div>
</div>

<!-- POPUP 1 – Calendar & Date Selection -->
<div class="sb-overlay" id="sbCalendarOverlay">
    <div class="sb-popup sb-popup-calendar">
        <button class="sb-popup-close" id="sbCloseCalendar" aria-label="Close">&times;</button>
        <h2 class="sb-popup-title">Select Dates for <?php echo esc_html($title); ?></h2>
        <p class="sb-popup-tz">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            Select your check-in and check-out dates
        </p>

        <div class="sb-popup-body">
            <div class="sb-calendar-wrap">
                <div class="sb-cal-nav">
                    <button class="sb-cal-prev" id="sbCalPrev">&#8249;</button>
                    <span class="sb-cal-month-label" id="sbCalMonthLabel"></span>
                    <button class="sb-cal-next" id="sbCalNext">&#8250;</button>
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
                <div class="sb-slots-date-label" id="sbAccDateRangeLabel">Please select a check-in date</div>
                <div class="sb-slots-list">
                    <div id="sb-range-summary" style="margin-bottom:20px;"></div>
                    <button class="sb-select-time-btn" id="sbConfirmDates" disabled>Confirm Dates</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- POPUP 2 – Guest Info & Payment -->
<div class="sb-overlay" id="sbBookingOverlay">
    <div class="sb-popup sb-popup-booking">
        <button class="sb-popup-close" id="sbCloseBooking" aria-label="Close">&times;</button>
        <h2 class="sb-popup-title">Request your Reservation</h2>
        <div class="sb-booking-summary" id="sbAccBookingSummary"></div>

        <form id="sb-accommodation-booking-form" class="sb-booking-form">
            <input type="hidden" name="room_id" value="<?php echo $room_id; ?>" />
            <input type="hidden" name="check_in" id="sbCheckIn" />
            <input type="hidden" name="check_out" id="sbCheckOut" />

            <h3 class="sb-form-section-title">Guest Details</h3>
            <div class="sb-form-row">
                <div class="sb-form-group">
                    <label>Full Name *</label>
                    <input type="text" name="guest_name" required placeholder="John Doe" />
                </div>
                <div class="sb-form-group">
                    <label>Number of Guests *</label>
                    <select name="occupants" required>
                        <?php for($i=1; $i<=$max_occupants; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?> Guest<?php echo $i>1?'s':''; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="sb-form-row">
                <div class="sb-form-group">
                    <label>Email Address *</label>
                    <input type="email" name="guest_email" required placeholder="john@example.com" />
                </div>
                <div class="sb-form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="guest_phone" placeholder="+353 1 234 5678" />
                </div>
            </div>

            <div class="sb-form-group" style="margin-top:20px; margin-bottom:20px;">
                <label>Additional Notes</label>
                <textarea name="notes" rows="3" placeholder="Any special requests or information?" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;"></textarea>
            </div>

            <div class="sb-amount-total" id="sbAccAmountTotal" style="display:none;"></div>
            
            <button type="submit" class="sb-pay-btn" id="acc-submit-btn">
                <span id="sbPayBtnText">Request Reservation</span>
                <span id="sbPayBtnSpinner" class="sb-spinner" style="display:none;"></span>
            </button>
            <div id="sbAccFormErrors" class="sb-card-errors" style="color:#b91c1c; background:#fef2f2; padding:12px; border-radius:8px; margin-bottom:20px; display:none;"></div>

            <div class="sb-secure-note">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                Your request will be reviewed by our team.
            </div>
        </form>
    </div>
</div>

<style>
/* 3-Stage Calendar States */
.sb-cal-day.locked { background: #fee2e2 !important; color: #b91c1c !important; cursor: not-allowed !important; text-decoration: line-through; }
.sb-cal-day.pending { background: #fef3c7 !important; color: #92400e !important; cursor: not-allowed !important; border: 1px dashed #d97706 !important; }

/* Distinct Colors for Check-in and Check-out */
.sb-cal-day.selected-start { background: #059669 !important; color: #fff !important; border-radius: 50% !important; z-index: 2; position: relative; }
.sb-cal-day.selected-end { background: #d97706 !important; color: #fff !important; border-radius: 50% !important; z-index: 2; position: relative; }

.sb-cal-day.in-range { background: rgba(5, 150, 105, 0.1) !important; color: #059669 !important; border-radius: 0 !important; }

/* Status Labels for Legend */
.sb-cal-legend { display: flex; gap: 15px; margin-top: 20px; font-size: 12px; color: var(--sb-text-mid); font-family: var(--sb-font-ui); }
.sb-legend-item { display: flex; align-items: center; gap: 6px; }
.sb-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.dot-available { background: #e2eae7; border: 1px solid var(--sb-border-mid); }
.dot-pending { background: #fef3c7; border: 1px solid #f59e0b; }
.dot-booked { background: #fee2e2; border: 1px solid #ef4444; }
.dot-checkin { background: #059669; }
.dot-checkout { background: #d97706; }
</style>

<script>
const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
const roomId  = <?php echo $room_id; ?>;
const roomPrice = <?php echo floatval($price_per_night); ?>;
const currency = '<?php echo $currency; ?>';

const formatDate = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

// Calendar State
let currentMonth = new Date();
const todayRaw = new Date();
todayRaw.setHours(0,0,0,0);
const todayLocal = formatDate(todayRaw);

let checkIn = null; 
let checkOut = null; 
let bookedDates = []; // Format: { date: 'YYYY-MM-DD', status: 'confirmed'|'pending' }

document.addEventListener('DOMContentLoaded', function() {
    // Open/Close
    const btnOpen = document.getElementById('sbOpenCalendar');
    const overlayCal = document.getElementById('sbCalendarOverlay');
    const overlayBook = document.getElementById('sbBookingOverlay');

    if(btnOpen) btnOpen.onclick = openCalendar;
    document.getElementById('sbCloseCalendar').onclick = sbCloseAll;
    document.getElementById('sbCloseBooking').onclick = sbCloseAll;

    // Cal Nav
    document.getElementById('sbCalPrev').onclick = () => { currentMonth.setMonth(currentMonth.getMonth() - 1); renderCalendar(); };
    document.getElementById('sbCalNext').onclick = () => { currentMonth.setMonth(currentMonth.getMonth() + 1); renderCalendar(); };

    // Confirm Dates
    document.getElementById('sbConfirmDates').onclick = () => {
        overlayCal.classList.remove('active');
        overlayBook.classList.add('active');
        updateBookingSummary();
    };

    // Form Submit
    const form = document.getElementById('sb-accommodation-booking-form');
    if(form) form.onsubmit = e => { e.preventDefault(); processBooking(); };
});

async function openCalendar() {
    document.getElementById('sbCalendarOverlay').classList.add('active');
    document.body.classList.add('sb-overflow-hidden');
    
    // Fetch booked dates first
    try {
        const res = await fetch(ajaxurl, {
            method: 'POST',
            body: new URLSearchParams({
                action: 'acc_get_booked_dates',
                room_id: roomId,
                nonce: '<?php echo wp_create_nonce('acc_nonce'); ?>'
            })
        });
        const data = await res.json();
        if(data.success) {
            bookedDates = [];
            data.data.booked_dates.forEach(range => {
                let start = new Date(range.check_in_date + 'T00:00:00');
                let end = new Date(range.check_out_date + 'T00:00:00');
                for(let d = new Date(start); d < end; d.setDate(d.getDate() + 1)) {
                    bookedDates.push({
                        date: formatDate(d),
                        status: range.booking_status
                    });
                }
            });
        }
    } catch(err) { console.error(err); }

    // Initial default: Find first available day starting from today
    if (!checkIn) {
        let temp = new Date(todayRaw);
        while(bookedDates.some(b => b.date === formatDate(temp))) {
            temp.setDate(temp.getDate() + 1);
        }
        checkIn = formatDate(temp);
        
        let out = new Date(temp);
        out.setDate(out.getDate() + 1);
        // Tomorrow will be checkout if not booked as a night
        if (!bookedDates.some(b => b.date === formatDate(temp))) {
             checkOut = formatDate(out);
        }
    }
    
    updateSelectionUI();
    renderCalendar();
}

function renderCalendar() {
    const grid = document.getElementById('sbCalDays');
    const label = document.getElementById('sbCalMonthLabel');
    if(!grid) return;
    grid.innerHTML = '';
    
    const year = currentMonth.getFullYear();
    const month = currentMonth.getMonth();
    label.innerText = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(currentMonth);

    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today = new Date();
    today.setHours(0,0,0,0);

    for (let i = 0; i < firstDay; i++) {
        grid.innerHTML += '<div></div>';
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const dateObj = new Date(year, month, d);
        const dateStr = formatDate(dateObj);
        const isPast = dateObj < today;
        
        const booking = bookedDates.find(b => b.date === dateStr);
        const isBooked = booking && booking.status === 'confirmed';
        const isPending = booking && booking.status === 'pending';
        
        let className = 'sb-cal-day';
        if(isPast) className += ' locked';
        if(isBooked) className += ' locked';
        if(isPending) className += ' pending';
        
        if(checkIn === dateStr) className += ' selected-start';
        if(checkOut === dateStr) className += ' selected-end';
        if(checkIn && checkOut && dateStr > checkIn && dateStr < checkOut) className += ' in-range';

        const dayEl = document.createElement('div');
        dayEl.className = className;
        dayEl.innerText = d;
        
        if(!isPast) {
            dayEl.onclick = () => handleDateClick(dateStr);
        }
        grid.appendChild(dayEl);
    }
    
    if(!document.querySelector('.sb-cal-legend')) {
        const legend = document.createElement('div');
        legend.className = 'sb-cal-legend';
        legend.innerHTML = `
            <div class="sb-legend-item"><span class="sb-dot dot-available"></span> Available</div>
            <div class="sb-legend-item"><span class="sb-dot dot-pending"></span> Pending</div>
            <div class="sb-legend-item"><span class="sb-dot dot-booked"></span> Night Occupied</div>
            <div class="sb-legend-item"><span class="sb-dot dot-checkin"></span> Check-in</div>
            <div class="sb-legend-item"><span class="sb-dot dot-checkout"></span> Check-out</div>
        `;
        grid.parentElement.appendChild(legend);
    }
}

function handleDateClick(dateStr) {
    if(!checkIn || (checkIn && checkOut)) {
        // Fresh Start
        if(bookedDates.some(b => b.date === dateStr)) {
            alert('This night is already occupied.');
            return;
        }
        checkIn = dateStr;
        checkOut = null;
    } else {
        // Trying to set Check-out
        if(dateStr <= checkIn) {
            if(bookedDates.some(b => b.date === dateStr)) {
                alert('This night is already occupied.');
                return;
            }
            checkIn = dateStr;
            checkOut = null;
        } else {
            // Verify no blocked nights in between
            let hasConflict = false;
            let temp = new Date(checkIn + 'T00:00:00');
            let end = new Date(dateStr + 'T00:00:00');
            
            while(temp < end) {
                let currentStr = formatDate(temp);
                if(bookedDates.some(b => b.date === currentStr)) {
                    hasConflict = true;
                    break;
                }
                temp.setDate(temp.getDate() + 1);
            }
            
            if(hasConflict) {
                alert('This range contains occupied nights.');
            } else {
                checkOut = dateStr;
            }
        }
    }
    
    updateSelectionUI();
    renderCalendar();
}

function updateSelectionUI() {
    const label = document.getElementById('sbAccDateRangeLabel');
    const summary = document.getElementById('sb-range-summary');
    const btn = document.getElementById('sbConfirmDates');

    if(!checkIn) {
        label.innerText = 'Please select a check-in date';
        btn.disabled = true;
        summary.innerHTML = '';
    } else if(!checkOut) {
        label.innerText = 'Select check-out date';
        btn.disabled = true;
        summary.innerHTML = `<div class="sb-meta-item">Check-in: <strong>${checkIn}</strong></div>`;
    } else {
        // Standard "Per Night" Stay: check-out date is the turnover day
        const diffDays = Math.round((new Date(checkOut) - new Date(checkIn)) / (1000 * 60 * 60 * 24));
        const nights = Math.max(1, diffDays);
        
        label.innerText = 'Dates Selected';
        btn.disabled = false;
        summary.innerHTML = `
            <div style="background:var(--sb-bg-subtle); padding:15px; border-radius:12px; border:1px solid var(--sb-border);">
                <div class="sb-meta-item">Check-in: <strong>${checkIn}</strong></div>
                <div class="sb-meta-item">Check-out: <strong>${checkOut}</strong></div>
                <div class="sb-meta-item">Duration: <strong>${nights} night${nights > 1 ? 's' : ''}</strong></div>
            </div>
        `;
    }
}

function updateBookingSummary() {
    const diffDays = Math.round((new Date(checkOut) - new Date(checkIn)) / (1000 * 60 * 60 * 24));
    const nights = Math.max(1, diffDays);
    const total = nights * roomPrice;
    
    document.getElementById('sbCheckIn').value = checkIn;
    document.getElementById('sbCheckOut').value = checkOut;
    
    document.getElementById('sbAccBookingSummary').innerHTML = `
        <div style="background:var(--sb-green-pale); padding:15px; border-radius:10px; margin-bottom:20px;">
            <p style="margin:0; font-weight:600;">Check-in: ${checkIn}</p>
            <p style="margin:5px 0; font-weight:600;">Check-out: ${checkOut}</p>
            <p style="margin:0; color:var(--sb-green);">Duration: ${nights} nights</p>
        </div>
    `;
    document.getElementById('sbAccAmountTotal').innerText = '';
}

async function processBooking() {
    const btn = document.getElementById('acc-submit-btn');
    const text = document.getElementById('sbPayBtnText');
    const spinner = document.getElementById('sbPayBtnSpinner');

    btn.disabled = true;
    text.style.display = 'none';
    spinner.style.display = 'inline-block';

    const fd = new FormData(document.getElementById('sb-accommodation-booking-form'));
    fd.append('action', 'acc_submit_booking_query');
    fd.append('nonce', '<?php echo wp_create_nonce('acc_nonce'); ?>');

    try {
        const res = await fetch(ajaxurl, { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            document.getElementById('sb-accommodation-booking-form').style.display = 'none';
            const successMsg = document.createElement('div');
            successMsg.className = 'sb-booking-success';
            successMsg.innerHTML = `
                <div class="sb-success-icon"><svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg></div>
                <h3>Request Sent!</h3>
                <p>${data.data.message}</p>
                <button class="sb-close-success" onclick="location.reload()">Close</button>
            `;
            document.getElementById('sbBookingOverlay').querySelector('.sb-popup').appendChild(successMsg);
        } else {
            const errDiv = document.getElementById('sbAccFormErrors');
            errDiv.innerText = data.data.message || 'Error submitting request.';
            errDiv.style.display = 'block';
            errDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            btn.disabled = false;
            text.style.display = 'inline';
            spinner.style.display = 'none';
        }
    } catch(e) {
        console.error('Booking error:', e);
        alert('Internal server error. Please try again later.');
        btn.disabled = false;
        text.style.display = 'inline';
        spinner.style.display = 'none';
    }
}

function sbChangeImage(el, url) {
    document.getElementById('sb-main-img').src = url;
    document.querySelectorAll('.sb-thumb').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
}

function sbToggleFaq(btn) {
    btn.classList.toggle('active');
    btn.nextElementSibling.classList.toggle('open');
}

function sbToggleAccordion(btn) {
    const body = btn.nextElementSibling;
    const isOpen = body.classList.contains('open');
    document.querySelectorAll('.sb-accordion-body.open').forEach(b => {
        b.classList.remove('open');
        b.previousElementSibling.classList.remove('active');
    });
    if (!isOpen) {
        body.classList.add('open');
        btn.classList.add('active');
    }
}

function sbCloseAll() {
    document.querySelectorAll('.sb-overlay').forEach(o => o.classList.remove('active'));
    document.body.classList.remove('sb-overflow-hidden');
    checkIn = null; checkOut = null;
}
</script>

<?php endwhile; ?>
<?php get_footer(); ?>
