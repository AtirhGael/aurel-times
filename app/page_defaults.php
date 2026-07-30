<?php
/**
 * Default content for the store's policy and information pages.
 *
 * Bodies are admin-authored HTML rendered raw, with {{setting_key}} placeholders
 * substituted at render time (see app/pages.php). Placeholder values are escaped
 * before insertion. Writing the defaults with placeholders already in them means
 * editing one setting updates every page that mentions it — change
 * return_window_days once and the announcement bar, the product page, the return
 * policy and the refund policy all follow.
 *
 * Wording is written for a United States seller. It is a solid starting point,
 * not legal advice — have a lawyer review before you take real orders.
 */
declare(strict_types=1);

return [

    'shipping-policy' => [
        'title' => 'Shipping Policy',
        'sort_order' => 10,
        'meta_desc' => 'Processing times, delivery estimates, carriers, tracking and international duties.',
        'body' => <<<'HTML'
<p class="lead">Every order is packed, insured and dispatched from our fulfilment centre. This page explains exactly what happens between your order confirmation and your delivery.</p>

<h2>Processing time</h2>
<p>Orders are prepared and dispatched within <strong>{{ship_processing_days}} business day(s)</strong> of payment clearing. Orders placed on a weekend or a public holiday begin processing on the next business day.</p>

<h2>Delivery estimates</h2>
<p>Once dispatched, delivery typically takes <strong>{{ship_delivery_min_days}}&ndash;{{ship_delivery_max_days}} business days</strong> depending on destination and customs clearance. Delivery estimates are estimates, not guarantees; carrier delays, weather and customs inspections are outside our control.</p>

<h2>Shipping charges</h2>
<p>Shipping charges are calculated at checkout and shown before you confirm your order. You will never be charged a shipping amount you have not seen.</p>

<h2>Carriers &amp; tracking</h2>
<p>We ship with <strong>{{ship_carriers}}</strong>. A tracking number is emailed to you as soon as your parcel is scanned by the carrier. If you have not received tracking within {{ship_processing_days}} business day(s) of your dispatch confirmation, contact us at <a href="mailto:{{support_email}}">{{support_email}}</a>.</p>

<h2>Where we ship</h2>
<p>{{ship_countries_note}}</p>

<h2>Customs, duties and import taxes</h2>
<p>For international deliveries, import duties and taxes are levied by the destination country and are the responsibility of the recipient. These charges are not included in the price you pay us and we cannot calculate them in advance. Refusing a parcel to avoid duties is treated as a return and the outbound shipping cost is not refunded.</p>

<h2>Incorrect addresses</h2>
<p>Please check your shipping address carefully at checkout. We can amend an address only before dispatch. Parcels returned to us as undeliverable due to an incorrect address will be reshipped once the correct address and any reshipping cost are settled.</p>

<h2>Lost or damaged parcels</h2>
<p>All shipments are insured. If your parcel arrives damaged, photograph the packaging and contents before unpacking further and contact us within 48 hours. If tracking has not updated for 10 consecutive business days, contact us and we will open a carrier investigation on your behalf.</p>

<h2>Questions</h2>
<p>Email <a href="mailto:{{support_email}}">{{support_email}}</a> or call <a href="tel:{{phone}}">{{phone}}</a> during {{support_hours}}.</p>
HTML,
    ],

    'return-policy' => [
        'title' => 'Return Policy',
        'sort_order' => 20,
        'meta_desc' => 'How to return an item, the return window, condition requirements and exchanges.',
        'body' => <<<'HTML'
<p class="lead">If a piece is not right for you, you may return it. This page explains the window, the condition we need it back in, and how to start a return.</p>

<h2>Return window</h2>
<p>You may request a return within <strong>{{return_window_days}} days</strong> of the delivery date. Requests made after that window cannot be accepted.</p>

<h2>Condition requirements</h2>
<p>To be eligible, the item must be:</p>
<ul>
  <li>Unworn, unaltered and free of scratches, sizing marks or wear to the bracelet or case;</li>
  <li>Returned with all original packaging, boxes, manuals, tags and accessories;</li>
  <li>Accompanied by the original order number.</li>
</ul>
<p>Items showing wear, resizing, engraving, or missing components will be returned to you and no refund will be issued.</p>

<h2>How to start a return</h2>
<ol>
  <li>Email <a href="mailto:{{support_email}}">{{support_email}}</a> with your order number and the reason for return.</li>
  <li>We will reply with a Return Merchandise Authorisation (RMA) number and the return address.</li>
  <li>Write the RMA number on the outside of the parcel and ship it back with a tracked, insured service.</li>
</ol>
<p><strong>Parcels sent back without an RMA number cannot be identified and cannot be refunded.</strong></p>

<h2>Return shipping</h2>
<p>Return shipping is paid by the <strong>{{return_shipping_paid_by}}</strong>. We strongly recommend a tracked and insured service &mdash; until the parcel reaches us, it remains your responsibility.</p>
<p>If the return is because we sent the wrong item, or the item arrived faulty or damaged, we cover the return shipping in full.</p>

<h2>Exchanges</h2>
<p>Exchanges are handled as a return followed by a new order, so that you are never left waiting on stock. Contact us and we will hold the replacement piece for you while your return is in transit.</p>

<h2>Warranty</h2>
<p>Every timepiece carries a <strong>{{warranty_months}}-month international warranty</strong> against manufacturing and movement defects. The warranty does not cover accidental damage, water ingress on non-rated pieces, unauthorised servicing, or normal wear to straps and plating.</p>

<h2>What happens next</h2>
<p>Once your return arrives, see our <a href="{{url_refund_policy}}">Refund Policy</a> for inspection and refund timelines.</p>
HTML,
    ],

    'refund-policy' => [
        'title' => 'Refund Policy',
        'sort_order' => 30,
        'meta_desc' => 'Refund timelines, inspection, restocking fees, partial refunds and cancellations.',
        'body' => <<<'HTML'
<p class="lead">This page covers what happens after an approved return reaches us. To start a return in the first place, see our <a href="{{url_return_policy}}">Return Policy</a>.</p>

<h2>Inspection</h2>
<p>Returned items are inspected within 2 business days of arrival. We check for wear, completeness of packaging and accessories, and that the item matches the original order.</p>

<h2>Refund timeline</h2>
<p>Once your return passes inspection, we issue the refund within <strong>{{refund_processing_days}} business days</strong> to the original payment method. Your bank or card issuer may take a further 3&ndash;10 business days to post the credit to your statement &mdash; that portion is outside our control.</p>
<p>We will email you when the refund is issued.</p>

<h2>What is refunded</h2>
<ul>
  <li><strong>The item price</strong> is always refunded in full on an approved return.</li>
  <li><strong>Original shipping charges</strong> are refunded only when the return is our fault &mdash; a wrong, faulty or damaged item.</li>
  <li><strong>Return shipping</strong> is refunded only when the return is our fault.</li>
  <li><strong>A restocking fee of {{restocking_fee_pct}}%</strong> applies to change-of-mind returns.</li>
</ul>

<h2>Partial refunds</h2>
<p>A partial refund may be issued where an item is returned with minor wear, missing packaging, or missing accessories. We will contact you with the proposed amount before processing, and you may instead have the item returned to you at your cost.</p>

<h2>Rejected returns</h2>
<p>If a return fails inspection we will photograph the item, email you the evidence, and hold it for 14 days. You may have it shipped back at your cost during that period.</p>

<h2>Cancellations</h2>
<p>You may cancel an order at no cost at any point before it is dispatched &mdash; email <a href="mailto:{{support_email}}">{{support_email}}</a> immediately with your order number. Once a parcel has been handed to the carrier, cancellation is handled as a standard return.</p>

<h2>Faulty items</h2>
<p>A faulty item reported within the {{warranty_months}}-month warranty period is repaired, replaced or refunded at our discretion, with all shipping covered by us. Nothing in this policy limits your statutory rights under applicable consumer law.</p>

<h2>Contact</h2>
<p>{{legal_entity_name}}<br>{{address_oneline}}<br>
Email <a href="mailto:{{support_email}}">{{support_email}}</a> &middot; Phone <a href="tel:{{phone}}">{{phone}}</a></p>
HTML,
    ],

    'privacy-policy' => [
        'title' => 'Privacy Policy',
        'sort_order' => 40,
        'meta_desc' => 'What personal data we collect, why we collect it, how long we keep it and your rights.',
        'body' => <<<'HTML'
<p class="lead">This policy explains what personal information {{legal_entity_name}} collects when you use {{site_name}}, why we collect it, and what control you have over it.</p>
<p><em>Last updated: {{year}}</em></p>

<h2>Who we are</h2>
<p>{{legal_entity_name}}<br>{{address_oneline}}<br>
Data enquiries: <a href="mailto:{{contact_email}}">{{contact_email}}</a></p>

<h2>What we collect</h2>
<table class="table table-sm">
  <thead><tr><th>Data</th><th>Why</th><th>Kept for</th></tr></thead>
  <tbody>
    <tr><td>Name, email, shipping address, phone</td><td>To process and deliver your order</td><td>7 years (tax records)</td></tr>
    <tr><td>Order history</td><td>To handle returns, warranty and support</td><td>7 years</td></tr>
    <tr><td>Account email and password hash</td><td>To let you sign in</td><td>Until you delete your account</td></tr>
    <tr><td>Contact form messages</td><td>To answer your enquiry</td><td>24 months</td></tr>
    <tr><td>Session cookie</td><td>To keep your cart and login working</td><td>Until you close your browser</td></tr>
  </tbody>
</table>
<p>We do not store card numbers. We never sell personal data.</p>

<h2>Legal basis</h2>
<p>We process order data to perform our contract with you, account data with your consent, and retain transaction records to meet our legal obligations.</p>

<h2>Who we share it with</h2>
<p>Only with parties who need it to fulfil your order: shipping carriers ({{ship_carriers}}) receive your name, address and phone number for delivery; our payment processor receives what it needs to take payment. Each is bound to use that data only for that purpose. We disclose data to law enforcement only where legally compelled.</p>

<h2>Cookies</h2>
<p>We use a strictly necessary session cookie to keep your cart and sign-in working. It carries no advertising identifier. If analytics is enabled, it is configured with IP anonymisation.</p>

<h2>Your rights</h2>
<p>You may request a copy of your data, correct it, delete it, or object to its processing. Email <a href="mailto:{{contact_email}}">{{contact_email}}</a> and we will respond within 30 days. If you are in the EU or UK you may also complain to your local data protection authority. California residents may exercise CCPA rights, including the right to know and the right to delete, through the same address &mdash; we do not sell personal information as defined by the CCPA.</p>

<h2>Security</h2>
<p>Passwords are stored as salted one-way hashes and are never recoverable in plain text. Traffic is served over HTTPS. Access to order data is limited to staff who need it.</p>

<h2>Children</h2>
<p>This store is not directed at children under 16 and we do not knowingly collect their data.</p>

<h2>Changes</h2>
<p>Material changes to this policy will be announced on this page with a revised date.</p>
HTML,
    ],

    'terms-of-service' => [
        'title' => 'Terms of Service',
        'sort_order' => 50,
        'meta_desc' => 'The terms governing your use of this website and any purchase you make from us.',
        'body' => <<<'HTML'
<p class="lead">These terms govern your use of {{site_name}}, operated by {{legal_entity_name}}. By placing an order you agree to them.</p>
<p><em>Last updated: {{year}}</em></p>

<h2>1. Who we are</h2>
<p>{{site_name}} is operated by <strong>{{legal_entity_name}}</strong>, {{address_oneline}}. Throughout these terms, "we", "us" and "our" refer to {{legal_entity_name}}.</p>

<h2>2. Eligibility</h2>
<p>You must be at least 18 years old, or the age of majority where you live, to place an order.</p>

<h2>3. Products and descriptions</h2>
<p>We work to describe every piece accurately, including its specification, materials and dimensions. Photography is representative; slight variation in colour rendering between screens is normal. We reserve the right to correct errors in descriptions or pricing, and to cancel and refund any order placed at a price that was listed in error.</p>

<h2>4. Pricing</h2>
<p>All prices are shown in {{currency_code}} and exclude shipping unless stated. Where a reference or "compare at" price is shown, it is a genuine former or recommended retail price for that item &mdash; we do not display invented reference prices. Prices may change without notice, but never after you have placed an order.</p>

<h2>5. Orders and acceptance</h2>
<p>Your order is an offer to buy. A contract forms when we send your dispatch confirmation. We may decline an order where an item is out of stock, where we cannot verify the billing or delivery details, or where we suspect fraud.</p>

<h2>6. Payment</h2>
<p>Payment is taken at the time of order. You confirm that you are authorised to use the payment method you provide.</p>

<h2>7. Shipping, returns and refunds</h2>
<p>Delivery is governed by our <a href="{{url_shipping_policy}}">Shipping Policy</a>. Returns are governed by our <a href="{{url_return_policy}}">Return Policy</a> and refunds by our <a href="{{url_refund_policy}}">Refund Policy</a>. Each forms part of these terms.</p>

<h2>8. Warranty</h2>
<p>Products carry a {{warranty_months}}-month warranty against manufacturing defects. Nothing in these terms excludes or limits your non-waivable statutory rights as a consumer.</p>

<h2>9. Accounts</h2>
<p>You are responsible for keeping your account credentials confidential and for activity under your account. Tell us promptly at <a href="mailto:{{support_email}}">{{support_email}}</a> if you suspect unauthorised access.</p>

<h2>10. Acceptable use</h2>
<p>You agree not to use this site to break any law, to scrape or bulk-download our content, to probe or breach its security, or to transmit malicious code.</p>

<h2>11. Intellectual property</h2>
<p>The site design, text and layout are owned by {{legal_entity_name}} and may not be reproduced without permission. Third-party trademarks referenced on this site remain the property of their respective owners; their appearance does not imply affiliation with, sponsorship by, or endorsement from those owners.</p>

<h2>12. Limitation of liability</h2>
<p>To the fullest extent permitted by law, our total liability arising from any order is limited to the amount you paid for that order. We are not liable for indirect or consequential loss. Nothing here limits liability for death, personal injury or fraud.</p>

<h2>13. Governing law</h2>
<p>These terms are governed by the laws of the State of {{region}}, {{country}}, and the courts of that state have exclusive jurisdiction, without prejudice to any mandatory consumer protection rights in your country of residence.</p>

<h2>14. Contact</h2>
<p>Email <a href="mailto:{{contact_email}}">{{contact_email}}</a> &middot; Phone <a href="tel:{{phone}}">{{phone}}</a> &middot; {{support_hours}}</p>
HTML,
    ],

    'about-us' => [
        'title' => 'About Us',
        'sort_order' => 5,
        'meta_desc' => 'Who we are, where we ship from, and how to reach a real person.',
        'body' => <<<'HTML'
<p class="lead">{{tagline}}</p>

<h2>Who we are</h2>
<p>{{site_name}} is operated by {{legal_entity_name}}, based in {{city}}, {{region}}. We curate and ship precision timepieces to customers worldwide, and we handle every order ourselves &mdash; from inspection and packing through to after-sales support.</p>

<p><em>Replace this section with your own story: when you started, what you specialise in, and why a customer should buy from you rather than anyone else. Specific, verifiable detail is what makes an About page worth reading.</em></p>

<h2>What you can expect</h2>
<ul>
  <li><strong>Every piece inspected before dispatch.</strong> Nothing ships without being checked by hand.</li>
  <li><strong>Insured delivery with tracking.</strong> Shipped via {{ship_carriers}}, typically arriving in {{ship_delivery_min_days}}&ndash;{{ship_delivery_max_days}} business days.</li>
  <li><strong>{{return_window_days}}-day returns.</strong> Full details in our <a href="{{url_return_policy}}">Return Policy</a>.</li>
  <li><strong>{{warranty_months}}-month international warranty</strong> against manufacturing defects.</li>
</ul>

<h2>Where to find us</h2>
<p>{{legal_entity_name}}<br>
{{address_line1}}<br>
{{address_line2}}<br>
{{city}}, {{region}} {{postcode}}<br>
{{country}}</p>

<h2>Talk to us</h2>
<p>Email <a href="mailto:{{contact_email}}">{{contact_email}}</a> or call <a href="tel:{{phone}}">{{phone}}</a>. We answer {{support_hours}}. You can also use our <a href="{{url_contact}}">contact form</a> &mdash; every message reaches a person, and we aim to reply within one business day.</p>
HTML,
    ],

    'faq' => [
        'title' => 'Frequently Asked Questions',
        'sort_order' => 60,
        'meta_desc' => 'Answers on delivery times, tracking, returns, warranty, payment and customs.',
        'body' => <<<'HTML'
<p class="lead">Short answers to the questions we are asked most. If yours is not here, <a href="{{url_contact}}">get in touch</a>.</p>

<h2>Ordering</h2>
<h3>Do I need an account to order?</h3>
<p>No. You can check out as a guest. An account simply lets you see your order history and track past purchases.</p>

<h3>Can I change or cancel my order?</h3>
<p>Yes, at no cost, at any time before dispatch. Email <a href="mailto:{{support_email}}">{{support_email}}</a> with your order number as soon as possible.</p>

<h2>Shipping</h2>
<h3>How long will delivery take?</h3>
<p>We dispatch within {{ship_processing_days}} business day(s), and delivery then takes {{ship_delivery_min_days}}&ndash;{{ship_delivery_max_days}} business days depending on destination.</p>

<h3>How do I track my order?</h3>
<p>A tracking number is emailed to you when the carrier scans your parcel. Full detail is in our <a href="{{url_shipping_policy}}">Shipping Policy</a>.</p>

<h3>Do you ship internationally?</h3>
<p>{{ship_countries_note}}</p>

<h3>Will I be charged customs duty?</h3>
<p>For international deliveries, import duties and taxes are set by your country and are payable by you on arrival. They are not included in our prices.</p>

<h2>Returns &amp; warranty</h2>
<h3>What is your return window?</h3>
<p>{{return_window_days}} days from delivery, on unworn items in original packaging. See the <a href="{{url_return_policy}}">Return Policy</a>.</p>

<h3>Who pays for return shipping?</h3>
<p>The {{return_shipping_paid_by}} &mdash; except where the item arrived faulty, damaged or incorrect, in which case we cover it.</p>

<h3>How long does a refund take?</h3>
<p>{{refund_processing_days}} business days from inspection, plus your bank's own posting time. See the <a href="{{url_refund_policy}}">Refund Policy</a>.</p>

<h3>Is there a warranty?</h3>
<p>Yes &mdash; {{warranty_months}} months against manufacturing and movement defects.</p>

<h2>Products &amp; support</h2>
<h3>Are your reference prices genuine?</h3>
<p>Yes. Where a struck-through "compare at" price appears, it is a genuine former or recommended retail price for that item. We do not display invented discounts.</p>

<h3>How do I reach a person?</h3>
<p>Email <a href="mailto:{{support_email}}">{{support_email}}</a>, call <a href="tel:{{phone}}">{{phone}}</a>, or use the <a href="{{url_contact}}">contact form</a>. We answer {{support_hours}}.</p>
HTML,
    ],
];
