# Sauna Booking – WordPress Plugin

A complete sauna/wellness booking system with custom post types, calendar-based time-slot scheduling, and Stripe payment integration.

---

## Features

- **Custom Post Type** – `sauna_product` managed from the WP Dashboard
- **Shortcode** – `[sauna_products]` renders a responsive product card grid
- **Product Detail Page** – Auto-generated template with gallery, description, features
- **Time-Slot Calendar Popup** – Month calendar, 1-hour slots, live availability (matches screenshots)
- **Booking & Payment Popup** – Personal info form + Stripe card element
- **Stripe Integration** – Payment Intents API (card payments)
- **Email Confirmations** – Sent to guest and admin on successful booking
- **Admin Bookings Panel** – Full list with filter, status update, delete
- **Settings Page** – Stripe API keys, currency configuration

---

## Installation

1. Upload the `sauna-booking/` folder to `/wp-content/plugins/`
2. Activate the plugin via **Plugins → Installed Plugins**
3. Go to **Sauna Products → Settings** and enter your Stripe API keys
4. Create sauna products via **Sauna Products → Add New**
5. Embed the product grid with the shortcode `[sauna_products]`

---

## Shortcode Options

```
[sauna_products columns="3" per_page="9"]
[sauna_products columns="2" per_page="4" ids="10,11,12"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `columns` | 3       | Grid columns (1–4) |
| `per_page`| 9       | Number of products to show |
| `ids`     | —       | Comma-separated post IDs to show specific products |

---

## Product Meta Fields

When creating/editing a Sauna Product, fill in the **Sauna Product Details** meta box:

| Field | Description |
|-------|-------------|
| Price | Price per seat per slot (e.g., `15.95`) |
| Total Seats Per Slot | Maximum bookings per time slot (default: 6) |
| Location / City Badge | Short badge text shown on card (e.g., `DUBLIN`) |
| Location Address | Full address shown on detail page |
| Opening Hour | First slot start hour in 24h (e.g., `7` = 7:00 AM) |
| Closing Hour | Last slot end hour in 24h (e.g., `22` = 10:00 PM) |
| Slot Duration | 60, 75, 90, or 120 minutes |
| Minimum Age | Age restriction shown on detail page |
| Features | Comma-separated list (e.g., `Plunge Pools, Changing Facilities, Toilets`) |
| Gallery Image URLs | One URL per line; displayed as thumbnails on the detail page |

---

## Settings

Go to **Sauna Products → Settings**:

| Setting | Description |
|---------|-------------|
| Stripe Publishable Key | Starts with `pk_live_` or `pk_test_` |
| Stripe Secret Key | Starts with `sk_live_` or `sk_test_` |
| Currency Code | e.g., `PHP`, `USD`, `GBP` |
| Currency Symbol | e.g., `₱`, `$`, `£` |

---

## Admin Bookings

Go to **Sauna Products → All Bookings** to see a full table of all reservations.

- **Filter** by sauna product, date, or status
- **Confirm / Cancel** individual bookings with one click
- **Delete** bookings permanently
- All booking details (guest info, slot, amount, Stripe ID) are shown

---

## Database Table

The plugin creates `wp_sauna_bookings` on activation:

```sql
id, product_id, booking_date, time_slot_start, time_slot_end,
seats_booked, first_name, last_name, email, phone,
amount, stripe_pi_id, stripe_status, status, notes, created_at
```

---

## Stripe Test Mode

Use Stripe test keys and test card `4242 4242 4242 4242` (any future expiry, any CVC) while developing.

---

## Theme Compatibility

The plugin includes its own full-page template for single sauna products (`templates/single-sauna-product.php`), which overrides the theme's `single.php`. The template calls `get_header()` and `get_footer()` so it inherits your theme's header/footer.

If you want the plugin to use your theme's wrapper instead, copy the template to your theme as `single-sauna_product.php` and customise as needed.

---

## Changelog

### 1.0.0
- Initial release
