<?php
/**
 * Settings schema — the single source of truth for every editable business fact.
 *
 * Metadata lives here in PHP rather than in the database so that:
 *   - defaults exist even when the database is unreachable (see settings_all()),
 *   - changing a label or hint is a code edit, not a data migration,
 *   - migrate.php and setup.php seed from exactly the same array.
 *
 * Field keys: label, type, default. Optional: hint, max, min, step, options,
 * placeholder, and `placeholder_value` => true to mark a shipped default as a
 * stand-in that must be replaced before launch (drives the admin dashboard
 * pre-launch checklist).
 *
 * Types: text | textarea | number | bool | select | email | url | tel
 */
declare(strict_types=1);

return [

    'business' => [
        'label' => 'Business Identity',
        'hint'  => 'Who you are. Shown in the header, footer, invoices and structured data.',
        'fields' => [
            'site_name' => [
                'label' => 'Store name', 'type' => 'text', 'default' => SITE_NAME, 'max' => 120,
                'hint'  => 'Used in the page title, footer and Organization schema.',
            ],
            'logo_text_a' => [
                'label' => 'Logo text (first half)', 'type' => 'text', 'default' => 'ALEX CLEAN', 'max' => 40,
                'hint'  => 'Rendered in the default colour, on the first line of the wordmark.',
            ],
            'logo_text_b' => [
                'label' => 'Logo text (second half)', 'type' => 'text', 'default' => 'FACTORY WATCHES', 'max' => 40,
                'hint'  => 'Rendered smaller, in the accent colour, on the second line.',
            ],
            'tagline' => [
                'label' => 'Tagline', 'type' => 'text', 'max' => 200,
                'default' => 'Luxury timepieces, meticulously crafted and delivered worldwide.',
                'hint'  => 'One line under the logo in the footer.',
            ],
            'legal_entity_name' => [
                'label' => 'Legal entity name', 'type' => 'text', 'max' => 200,
                'default' => 'Your Company Ltd', 'placeholder_value' => true,
                'hint'  => 'The registered company name. Appears in Terms and Privacy. Kept '
                         . 'deliberately generic: the pre-launch checklist flags a field while it '
                         . 'still equals this default, so shipping the real name here would re-flag '
                         . 'it the moment you actually enter it.',
            ],
            'company_reg_no' => [
                'label' => 'Company / EIN number', 'type' => 'text', 'max' => 60, 'default' => '',
                'hint'  => 'Optional, but a real registration number is a strong trust signal.',
            ],
            'currency_symbol' => [
                'label' => 'Currency symbol', 'type' => 'text', 'default' => CURRENCY, 'max' => 4,
            ],
            'currency_code' => [
                'label' => 'Currency code', 'type' => 'text', 'default' => 'GBP', 'max' => 3,
                'hint'  => 'ISO 4217, e.g. GBP. Used in structured data and the product feed.',
            ],
            'currency_position' => [
                'label' => 'Symbol position', 'type' => 'select', 'default' => 'before',
                'options' => ['before' => 'Before amount (£10.00)', 'after' => 'After amount (10.00£)'],
            ],
            'timezone' => [
                'label' => 'Timezone', 'type' => 'text', 'default' => 'Europe/London', 'max' => 60,
                'hint'  => 'PHP timezone identifier, applied on every request by '
                         . 'apply_store_timezone(). Note that stored timestamps are MySQL '
                         . 'CURRENT_TIMESTAMP values which are parsed and formatted in this same '
                         . 'zone, so they round-trip unchanged; this mainly governs dates the app '
                         . 'generates itself.',
            ],
        ],
    ],

    'contact' => [
        'label' => 'Contact & Address',
        'hint'  => 'Shown on the contact page, in the footer and in Organization schema. '
                 . 'A store with no verifiable address or phone number reads as fraudulent.',
        'fields' => [
            'contact_email' => [
                'label' => 'General email', 'type' => 'email', 'max' => 190,
                'default' => 'hello@example.com', 'placeholder_value' => true,
                'hint'  => 'Contact form submissions are delivered here. The shipped default uses '
                         . 'example.com, which RFC 2606 reserves, so it can never be a real mailbox '
                         . 'and can never collide with an address you genuinely use.',
            ],
            'support_email' => [
                'label' => 'Support email', 'type' => 'email', 'max' => 190,
                'default' => 'support@example.com', 'placeholder_value' => true,
            ],
            'orders_email' => [
                'label' => 'Orders email', 'type' => 'email', 'max' => 190,
                'default' => 'orders@example.com', 'placeholder_value' => true,
                'hint'  => 'Receives a copy of every order confirmation.',
            ],
            'phone' => [
                'label' => 'Phone number', 'type' => 'tel', 'max' => 40,
                'default' => '+1 (302) 555-0147', 'placeholder_value' => true,
                'hint'  => 'The shipped default is inside the reserved 555-01xx fictional range '
                         . 'so it cannot ring a stranger. Replace it with your real number.',
            ],
            'whatsapp' => [
                'label' => 'WhatsApp number', 'type' => 'text', 'max' => 40, 'default' => '',
                'hint'  => 'Digits only, including country code. Leave empty to hide.',
            ],
            'address_line1' => [
                'label' => 'Address line 1', 'type' => 'text', 'max' => 200,
                'default' => '123 Commerce Way', 'placeholder_value' => true,
            ],
            'address_line2' => [
                'label' => 'Address line 2', 'type' => 'text', 'max' => 200,
                'default' => 'Suite 400', 'placeholder_value' => true,
            ],
            'city' => [
                'label' => 'City', 'type' => 'text', 'max' => 120,
                'default' => 'Wilmington', 'placeholder_value' => true,
            ],
            'region' => [
                'label' => 'State / region', 'type' => 'text', 'max' => 120,
                'default' => 'DE', 'placeholder_value' => true,
            ],
            'postcode' => [
                'label' => 'ZIP / postal code', 'type' => 'text', 'max' => 30,
                'default' => '19801', 'placeholder_value' => true,
            ],
            'country' => [
                'label' => 'Country', 'type' => 'text', 'max' => 80, 'default' => 'United States',
            ],
            'support_hours' => [
                'label' => 'Support hours', 'type' => 'text', 'max' => 160,
                'default' => 'Mon-Fri, 9:00 AM - 6:00 PM ET',
                'hint'  => 'State real hours you can actually answer. Do not claim 24/7 support you cannot provide.',
            ],
        ],
    ],

    'shipping' => [
        'label' => 'Shipping',
        'hint'  => 'These values drive the announcement bar, the cart, checkout totals '
                 . 'and the shipping policy page. Change them here and every mention updates.',
        'fields' => [
            'free_shipping_enabled' => [
                'label' => 'Offer free shipping', 'type' => 'bool', 'default' => '1',
            ],
            'free_shipping_threshold' => [
                'label' => 'Free shipping over', 'type' => 'number', 'default' => '0',
                'min' => 0, 'step' => '0.01',
                'hint'  => 'Order subtotal required for free shipping. 0 = always free.',
            ],
            'flat_shipping_rate' => [
                'label' => 'Flat shipping rate', 'type' => 'number', 'default' => '0',
                'min' => 0, 'step' => '0.01',
                'hint'  => 'Charged when the order does not qualify for free shipping.',
            ],
            'ship_processing_days' => [
                'label' => 'Processing time (business days)', 'type' => 'number', 'default' => '1',
                'min' => 0, 'max' => 60,
                'hint'  => 'Time from order to dispatch, before transit.',
            ],
            'ship_delivery_min_days' => [
                'label' => 'Delivery estimate — minimum days', 'type' => 'number', 'default' => '5',
                'min' => 1, 'max' => 120,
            ],
            'ship_delivery_max_days' => [
                'label' => 'Delivery estimate — maximum days', 'type' => 'number', 'default' => '20',
                'min' => 1, 'max' => 120,
            ],
            'ship_carriers' => [
                'label' => 'Carriers', 'type' => 'text', 'max' => 200,
                'default' => 'USPS, UPS, DHL Express',
            ],
            'ship_countries_note' => [
                'label' => 'Destinations note', 'type' => 'textarea', 'max' => 1000,
                'default' => 'We ship to the United States, Canada, the United Kingdom, the European Union, '
                           . 'Australia and New Zealand. Duties and import taxes, where applicable, are the '
                           . 'responsibility of the recipient.',
            ],
        ],
    ],

    'returns' => [
        'label' => 'Returns & Warranty',
        'hint'  => 'Drives the announcement bar, the product page and the return/refund policy pages.',
        'fields' => [
            'return_window_days' => [
                'label' => 'Return window (days)', 'type' => 'number', 'default' => '30',
                'min' => 0, 'max' => 365,
            ],
            'return_shipping_paid_by' => [
                'label' => 'Return shipping paid by', 'type' => 'select', 'default' => 'customer',
                'options' => ['customer' => 'customer', 'store' => 'us'],
            ],
            'restocking_fee_pct' => [
                'label' => 'Restocking fee (%)', 'type' => 'number', 'default' => '0',
                'min' => 0, 'max' => 100,
                'hint'  => '0 for no restocking fee.',
            ],
            'refund_processing_days' => [
                'label' => 'Refund processing (business days)', 'type' => 'number', 'default' => '5',
                'min' => 1, 'max' => 90,
                'hint'  => 'From receiving the return to issuing the refund.',
            ],
            'exchange_allowed' => [
                'label' => 'Allow exchanges', 'type' => 'bool', 'default' => '1',
            ],
            'warranty_months' => [
                'label' => 'Warranty (months)', 'type' => 'number', 'default' => '24',
                'min' => 0, 'max' => 240,
            ],
        ],
    ],

    'seo' => [
        'label' => 'SEO & Indexing',
        'fields' => [
            'meta_title_suffix' => [
                'label' => 'Title suffix', 'type' => 'text', 'max' => 120, 'default' => '',
                'hint'  => 'Appended after the store name in <title>. Leave empty for none.',
            ],
            'meta_description' => [
                'label' => 'Default meta description', 'type' => 'textarea', 'max' => 320,
                'default' => 'Precision-built luxury timepieces with worldwide insured shipping, '
                           . 'a 30-day return window and a 2-year international warranty.',
                'hint'  => 'Used on pages that do not set their own. Aim for 150-160 characters.',
            ],
            'meta_keywords' => [
                'label' => 'Meta keywords', 'type' => 'text', 'max' => 300, 'default' => '',
                'hint'  => 'Ignored by Google since 2009. Safe to leave empty.',
            ],
            'og_image' => [
                'label' => 'Social share image URL', 'type' => 'url', 'max' => 600, 'default' => '',
                'hint'  => 'Absolute https:// URL, 1200x630px. Shown when the site is linked on social media.',
            ],
            'google_analytics_id' => [
                'label' => 'Google Analytics ID', 'type' => 'text', 'max' => 40, 'default' => '',
                'hint'  => 'e.g. G-XXXXXXXXXX. Leave empty to load no analytics at all.',
            ],
            'google_site_verification' => [
                'label' => 'Google site verification token', 'type' => 'text', 'max' => 120, 'default' => '',
            ],
            'robots_index' => [
                'label' => 'Allow search engines to index this site', 'type' => 'bool', 'default' => '0',
                'hint'  => 'Ships OFF. Turn it on only once the pre-launch checklist is clear — '
                         . 'indexing a site full of placeholder details is hard to undo.',
            ],
            'feed_token' => [
                'label' => 'Product feed token', 'type' => 'text', 'max' => 80, 'default' => '',
                'hint'  => 'Required query token for feed.php. Leave empty to disable the feed entirely.',
            ],
        ],
    ],

    'social' => [
        'label' => 'Social Profiles',
        'hint'  => 'Full profile URLs only. Leave a field empty rather than pointing it at a bare '
                 . 'domain — an empty entry is honest, "https://facebook.com" with no profile path '
                 . 'is a recognised placeholder tell.',
        'fields' => [
            'social_facebook'  => ['label' => 'Facebook',  'type' => 'url', 'max' => 300, 'default' => ''],
            'social_instagram' => ['label' => 'Instagram', 'type' => 'url', 'max' => 300, 'default' => ''],
            'social_twitter'   => ['label' => 'X / Twitter', 'type' => 'url', 'max' => 300, 'default' => ''],
            'social_youtube'   => ['label' => 'YouTube',   'type' => 'url', 'max' => 300, 'default' => ''],
            'social_tiktok'    => ['label' => 'TikTok',    'type' => 'url', 'max' => 300, 'default' => ''],
            'social_pinterest' => ['label' => 'Pinterest', 'type' => 'url', 'max' => 300, 'default' => ''],
        ],
    ],

    'integrations' => [
        'label' => 'Live Chat & Integrations',
        'hint'  => 'Third-party scripts. Each loads only when its ID is filled in — an empty field '
                 . 'means that vendor\'s JavaScript, and its cookies, never reach the page. Anything '
                 . 'enabled here also needs describing in the privacy policy.',
        'fields' => [
            'chatway_widget_id' => [
                'label' => 'Chatway widget ID', 'type' => 'text', 'max' => 80, 'default' => '',
                'hint'  => 'Chatway dashboard → Settings → Installation: copy the id= value out of the '
                         . 'embed snippet, not the whole <script> tag. Leave empty to load no chat widget.',
            ],
        ],
    ],
];
