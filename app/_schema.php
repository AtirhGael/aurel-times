<?php
/**
 * schema.org structured data builders.
 *
 * Each returns a plain array; app/header.php json_encodes anything placed in
 * $jsonLd. Structured data must describe what the page actually shows — markup
 * that overstates (a rating with no reviews, an availability the store cannot
 * honour, an address nobody occupies) is a policy violation, not a shortcut.
 */
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

/**
 * ISO 3166-1 alpha-2 code for the store country. schema.org and Merchant Center
 * both expect the code ("GB"), not the display name the settings hold.
 */
function store_country_code(): string
{
    $c = trim(setting('country'));
    if (preg_match('/^[A-Za-z]{2}$/', $c)) {
        return strtoupper($c);
    }
    $map = [
        'united kingdom' => 'GB', 'uk' => 'GB', 'great britain' => 'GB', 'england' => 'GB',
        'scotland' => 'GB', 'wales' => 'GB', 'northern ireland' => 'GB',
        'united states' => 'US', 'usa' => 'US', 'ireland' => 'IE',
    ];
    return $map[strtolower($c)] ?? 'GB';
}

/** PostalAddress, or null when no address has been configured. */
function schema_address(): ?array
{
    $street = trim(setting('address_line1') . ' ' . setting('address_line2'));
    $city   = setting('city');
    if ($street === '' && $city === '') {
        return null;
    }
    $addr = ['@type' => 'PostalAddress'];
    if ($street !== '')                    $addr['streetAddress']   = $street;
    if ($city !== '')                      $addr['addressLocality'] = $city;
    if (setting('region') !== '')          $addr['addressRegion']   = setting('region');
    if (setting('postcode') !== '')        $addr['postalCode']      = setting('postcode');
    if (setting('country') !== '')         $addr['addressCountry']  = store_country_code();
    return $addr;
}

/**
 * Organization graph.
 *
 * Empty fields are omitted entirely rather than emitted as "". A blank
 * "telephone": "" or a sameAs pointing at a bare https://facebook.com with no
 * profile path is a recognised template-never-filled-in signal — worse than
 * saying nothing.
 */
function schema_organization(): array
{
    $org = [
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => setting('site_name'),
        'url'      => site_url(),
    ];
    if (setting('legal_entity_name') !== '') {
        $org['legalName'] = setting('legal_entity_name');
    }
    if (setting('tagline') !== '') {
        $org['description'] = setting('tagline');
    }
    // The square brand logo, not og_image: that is a 1200x630 share card, and
    // Google wants a logo it can crop to a square.
    $org['logo'] = abs_url('assets/brand/aurel-time-logo-square.png');
    if (($addr = schema_address()) !== null) {
        $org['address'] = $addr;
    }

    $contact = ['@type' => 'ContactPoint', 'contactType' => 'customer service'];
    if (setting('phone') !== '')         $contact['telephone'] = setting('phone');
    if (setting('support_email') !== '') $contact['email']     = setting('support_email');
    // hoursAvailable is omitted: it must be OpeningHoursSpecification, and the
    // setting is free text. A plain string there fails validation.
    if (isset($contact['telephone']) || isset($contact['email'])) {
        $org['contactPoint'] = [$contact];
        if (isset($contact['telephone'])) {
            $org['telephone'] = $contact['telephone'];
        }
        if (isset($contact['email'])) {
            $org['email'] = $contact['email'];
        }
    }

    $social = array_values(setting_social_links());
    if ($social) {
        $org['sameAs'] = $social;
    }
    return $org;
}

/** WebSite graph with the on-site search action. */
function schema_website(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        'name'     => setting('site_name'),
        'url'      => site_url(),
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => abs_url('shop.php?q={search_term_string}')],
            'query-input' => 'required name=search_term_string',
        ],
    ];
}

/**
 * BreadcrumbList.
 * @param array<int,array{name:string,url:string}> $trail
 */
function schema_breadcrumb(array $trail): array
{
    $items = [];
    foreach (array_values($trail) as $i => $step) {
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'name'     => $step['name'],
            'item'     => $step['url'],
        ];
    }
    return [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    ];
}

/**
 * Product graph with offers.
 *
 * $reviewStats is only honoured when it describes genuine customer reviews.
 * Callers must not pass generated review data — marking synthetic text up as
 * AggregateRating misrepresents the product to every search engine that reads it.
 */
function schema_product(array $p, array $variants = [], ?array $reviewStats = null): array
{
    $currency = setting('currency_code') !== '' ? setting('currency_code') : 'GBP';
    $url      = abs_url('product.php?handle=' . rawurlencode((string)$p['handle']));

    $node = [
        '@context' => 'https://schema.org',
        '@type'    => 'Product',
        'name'     => (string)$p['name'],
        'url'      => $url,
    ];
    if (!empty($p['description'])) {
        $node['description'] = mb_substr(trim(strip_tags((string)$p['description'])), 0, 5000);
    }
    if (!empty($p['image'])) {
        $node['image'] = [(string)$p['image']];
    }
    // The brand is the house, not the collection. brand_name carries the
    // collection (the brands table holds collections since the rebrand).
    $node['brand'] = ['@type' => 'Brand', 'name' => setting('site_name')];
    if (!empty($p['brand_name'])) {
        $node['category'] = (string)$p['brand_name'] . ' collection';
    }
    if (!empty($p['mpn']))  $node['mpn']  = (string)$p['mpn'];
    if (!empty($p['gtin'])) $node['gtin'] = (string)$p['gtin'];

    $prices  = [];
    $inStock = false;
    foreach ($variants as $v) {
        $price = (float)($v['price'] ?? 0);
        if ($price > 0) {
            $prices[] = $price;
        }
        if (!empty($v['in_stock'])) {
            $inStock = true;
        }
    }
    if (!$prices && (float)($p['base_price'] ?? 0) > 0) {
        $prices[] = (float)$p['base_price'];
        $inStock  = true;
    }
    $availability = $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
    $condition    = ((string)($p['item_condition'] ?? 'new')) === 'used'
        ? 'https://schema.org/UsedCondition'
        : 'https://schema.org/NewCondition';

    if ($prices) {
        $node['offers'] = count($prices) > 1
            ? [
                '@type'         => 'AggregateOffer',
                'priceCurrency' => $currency,
                'lowPrice'      => number_format(min($prices), 2, '.', ''),
                'highPrice'     => number_format(max($prices), 2, '.', ''),
                'offerCount'    => count($prices),
                'availability'  => $availability,
                'itemCondition' => $condition,
                'url'           => $url,
            ]
            : [
                '@type'         => 'Offer',
                'priceCurrency' => $currency,
                'price'         => number_format($prices[0], 2, '.', ''),
                'availability'  => $availability,
                'itemCondition' => $condition,
                'url'           => $url,
            ];
        $node['offers'] += schema_offer_policies(min($prices), $currency);
    }

    if ($reviewStats && (int)($reviewStats['count'] ?? 0) > 0) {
        $node['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => round((float)$reviewStats['avg'], 1),
            'reviewCount' => (int)$reviewStats['count'],
        ];
    }
    return $node;
}

/**
 * shippingDetails and hasMerchantReturnPolicy for an Offer, built from the same
 * settings that drive the shipping and returns pages, so the markup cannot drift
 * from what the policy pages promise. Covers the home country only; other
 * destinations are described in ship_countries_note.
 */
function schema_offer_policies(float $price, string $currency): array
{
    $country = store_country_code();
    $out = [
        'shippingDetails' => [
            '@type'        => 'OfferShippingDetails',
            'shippingRate' => [
                '@type'    => 'MonetaryAmount',
                'value'    => number_format(shipping_cost($price), 2, '.', ''),
                'currency' => $currency,
            ],
            'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => $country],
            'deliveryTime' => [
                '@type'        => 'ShippingDeliveryTime',
                'handlingTime' => [
                    '@type' => 'QuantitativeValue', 'minValue' => 0,
                    'maxValue' => setting_int('ship_processing_days'), 'unitCode' => 'DAY',
                ],
                'transitTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => setting_int('ship_delivery_min_days', 5),
                    'maxValue' => setting_int('ship_delivery_max_days', 20), 'unitCode' => 'DAY',
                ],
            ],
        ],
    ];

    $days = setting_int('return_window_days');
    $out['hasMerchantReturnPolicy'] = $days > 0
        ? [
            '@type'                => 'MerchantReturnPolicy',
            'applicableCountry'    => $country,
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays'   => $days,
            'returnMethod'         => 'https://schema.org/ReturnByMail',
            'returnFees'           => setting('return_shipping_paid_by') === 'store'
                ? 'https://schema.org/FreeReturn'
                : 'https://schema.org/ReturnFeesCustomerResponsibility',
        ]
        : [
            '@type'                => 'MerchantReturnPolicy',
            'applicableCountry'    => $country,
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted',
        ];
    return $out;
}
