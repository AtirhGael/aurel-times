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
 * Any <p>, <li> or <tr> whose placeholder resolves to an empty setting is dropped
 * at render time, so an unfilled field removes its sentence instead of printing
 * "operated by ." Keep optional facts (legal name, company number) in their own
 * element for that reason. {{trader_name}} is the legal name when set, otherwise
 * the store name, for sentences that must always render.
 *
 * Wording is written for a UK seller selling to consumers at a distance: the
 * Consumer Contracts Regulations 2013, the Consumer Rights Act 2015 and UK GDPR.
 * It is a solid starting point, not legal advice — have it reviewed before you
 * take real orders.
 */
declare(strict_types=1);

return [

    'shipping-policy' => [
        'title' => 'Shipping Policy',
        'sort_order' => 10,
        'meta_desc' => 'Processing times, delivery estimates, carriers, tracking and international duties.',
        'body' => <<<'HTML'
<p class="lead">Every order is checked by hand, packed and dispatched from our workshop in {{city}}. This page explains what happens between your order and your delivery.</p>

<h2>Processing time</h2>
<p>Orders are prepared and dispatched within <strong>{{ship_processing_days}} business day(s)</strong> of your order being confirmed. Orders placed at a weekend or on a UK bank holiday begin processing on the next business day.</p>

<h2>Delivery estimates</h2>
<p>Once dispatched, delivery typically takes <strong>{{delivery_estimate}}</strong> depending on destination and customs clearance. If your order has not arrived within 30 days of the date we agreed, you may cancel it and receive a full refund.</p>

<h2>Shipping charges</h2>
<p>Any shipping charge is shown at checkout before you place your order. You will never be charged a shipping amount you have not seen.</p>

<h2>Carriers &amp; tracking</h2>
<p>We ship with <strong>{{ship_carriers}}</strong>. Every parcel is tracked and insured. A tracking number is emailed to you as soon as your parcel is collected by the carrier.</p>

<h2>Where we ship</h2>
<p>{{ship_countries_note}}</p>

<h2>Customs, duties and import taxes</h2>
<p>For deliveries outside the United Kingdom, import duties and taxes are set by the destination country and are payable by the recipient. They are not included in our prices and we cannot calculate them in advance.</p>

<h2>Incorrect addresses</h2>
<p>Please check your delivery address carefully at checkout. We can amend an address only before dispatch. If a parcel comes back to us as undeliverable because of an incorrect address, we will contact you to arrange redelivery.</p>

<h2>Lost or damaged parcels</h2>
<p>Your order is our responsibility until it is delivered to you. If it arrives damaged, please photograph the packaging and contents and contact us as soon as you can. If tracking has not updated for 10 business days, contact us and we will open an investigation with the carrier and send a replacement or refund if the parcel is lost.</p>

<h2>Questions</h2>
<p>Email <a href="mailto:{{support_email}}">{{support_email}}</a> or call <a href="tel:{{phone}}">{{phone}}</a>.</p>
<p>We answer {{support_hours}}.</p>
HTML,
    ],

    'return-policy' => [
        'title' => 'Return Policy',
        'sort_order' => 20,
        'meta_desc' => 'Your right to cancel, our return window, how to send a watch back and your warranty.',
        'body' => <<<'HTML'
<p class="lead">If a watch is not right for you, you can send it back. This page explains your legal right to cancel, our longer return window and how to start a return.</p>

<h2>Your right to cancel</h2>
<p>Under the Consumer Contracts Regulations 2013 you may cancel your order for any reason within <strong>14 days</strong> of the day you receive it, and then return the watch within a further 14 days.</p>

<h2>Our return window</h2>
<p>We go further: you may request a return within <strong>{{return_window_days}} days</strong> of delivery. A return made in this window is treated in the same way as a cancellation.</p>

<h2>Condition</h2>
<p>You may handle a watch as you would in a shop, to try it on and check it works. Please return it with its box, papers and any accessories. If a watch comes back with wear or damage beyond what that handling would cause (for example scratches, resizing or engraving), we may reduce your refund to reflect the loss in value. We will tell you before doing so.</p>

<h2>How to start a return</h2>
<ol>
  <li>Email <a href="mailto:{{support_email}}">{{support_email}}</a> with your order number, or send us the cancellation form below. Any clear statement that you want to cancel is enough.</li>
  <li>We reply with the return address and a return reference.</li>
  <li>Send the watch back with a tracked, insured service and keep your proof of postage.</li>
</ol>

<h2>Return shipping</h2>
<p>For a change-of-mind return, the cost of sending the watch back is paid by the <strong>{{return_shipping_paid_by}}</strong>.</p>
<p>If we sent the wrong item, or it arrived faulty or damaged, we cover the return shipping in full.</p>

<h2>Exchanges</h2>
<p>Exchanges are handled as a return followed by a new order. Contact us and we will hold the replacement watch for you while your return is in transit.</p>

<h2>Warranty</h2>
<p>Every watch carries a <strong>{{warranty_months}}-month warranty</strong> against manufacturing and movement defects. The warranty does not cover accidental damage, water ingress beyond the stated rating, servicing by anyone other than us, or normal wear to straps and plating. It is in addition to your statutory rights, not instead of them.</p>

<h2>Model cancellation form</h2>
<p>To {{trader_name}}, {{address_oneline}}, <a href="mailto:{{support_email}}">{{support_email}}</a>:</p>
<p>I hereby give notice that I cancel my contract of sale of the following goods: [order number and item]. Ordered on [date] / received on [date]. Name: [your name]. Address: [your address]. Date: [date].</p>

<h2>What happens next</h2>
<p>Once your return arrives, see our <a href="{{url_refund_policy}}">Refund Policy</a> for refund timings.</p>
HTML,
    ],

    'refund-policy' => [
        'title' => 'Refund Policy',
        'sort_order' => 30,
        'meta_desc' => 'Refund timelines, what is refunded, faulty goods and cancellations.',
        'body' => <<<'HTML'
<p class="lead">This page covers refunds once you have cancelled or returned an order. To start a return, see our <a href="{{url_return_policy}}">Return Policy</a>.</p>

<h2>Refund timeline</h2>
<p>We refund you within <strong>{{refund_processing_days}} business days</strong> of receiving the watch back, and never later than 14 days after we receive it or you send us proof of postage. Refunds go to the original payment method. Your bank may take a few further days to show the credit.</p>
<p>We email you when the refund is issued.</p>

<h2>What is refunded</h2>
<ul>
  <li><strong>The price of the watch</strong>, in full.</li>
  <li><strong>The original delivery charge</strong>, up to the cost of our standard delivery option.</li>
  <li><strong>Return shipping</strong> when the item was faulty, damaged or incorrect.</li>
</ul>
<p>We may reduce a refund only where a watch has lost value because it was handled more than needed to check it, as explained in our <a href="{{url_return_policy}}">Return Policy</a>.</p>

<h2>Cancelling before dispatch</h2>
<p>You may cancel an order at no cost at any time before it is dispatched. Email <a href="mailto:{{support_email}}">{{support_email}}</a> with your order number. Once a parcel has been handed to the carrier, cancellation is handled as a return.</p>

<h2>Faulty items</h2>
<p>Under the Consumer Rights Act 2015, if a watch is faulty, not as described or not fit for purpose:</p>
<ul>
  <li><strong>Within 30 days</strong> of delivery you can reject it for a full refund.</li>
  <li><strong>After 30 days</strong> you are entitled to a repair or replacement. If that fails, you can have a price reduction or a refund.</li>
  <li>A fault that appears <strong>within 6 months</strong> is presumed to have been there at delivery, unless we can show otherwise.</li>
</ul>
<p>We cover all shipping on faulty items. Our {{warranty_months}}-month warranty is in addition to these rights.</p>

<h2>Contact</h2>
<p>{{legal_entity_name}}</p>
<p>{{address_oneline}}</p>
<p>Email <a href="mailto:{{support_email}}">{{support_email}}</a> &middot; Phone <a href="tel:{{phone}}">{{phone}}</a></p>
HTML,
    ],

    'privacy-policy' => [
        'title' => 'Privacy Policy',
        'sort_order' => 40,
        'meta_desc' => 'What personal data we collect, why, how long we keep it and your rights under UK GDPR.',
        'body' => <<<'HTML'
<p class="lead">This policy explains what personal information we collect when you use {{site_name}}, why we collect it, and your rights under UK data protection law (UK GDPR and the Data Protection Act 2018).</p>

<h2>Who we are</h2>
<p>{{site_name}} is the data controller for the information described here.</p>
<p>{{legal_entity_name}}</p>
<p>{{address_oneline}}</p>
<p>Data enquiries: <a href="mailto:{{contact_email}}">{{contact_email}}</a></p>

<h2>What we collect</h2>
<table class="table table-sm">
  <thead><tr><th>Data</th><th>Why</th><th>Kept for</th></tr></thead>
  <tbody>
    <tr><td>Name, email, delivery address, phone</td><td>To process and deliver your order</td><td>6 years (HMRC records)</td></tr>
    <tr><td>Order history</td><td>To handle returns, warranty and support</td><td>6 years</td></tr>
    <tr><td>Account email and password hash</td><td>To let you sign in</td><td>Until you delete your account</td></tr>
    <tr><td>Contact form and live chat messages</td><td>To answer your enquiry</td><td>24 months</td></tr>
  </tbody>
</table>
<p>We do not store card numbers. We never sell personal data.</p>

<h2>Legal basis</h2>
<p>We process order data to perform our contract with you, keep transaction records to meet our legal obligations, and answer enquiries in our legitimate interest in helping you.</p>

<h2>Who we share it with</h2>
<p>Only with those who need it to fulfil your order or answer you: our delivery carriers ({{ship_carriers}}) receive your name, address and phone number; our live chat provider (NexaHub) processes messages you send through the chat window. We disclose data to authorities only where the law requires it.</p>

<h2>Cookies</h2>
<p>We use a strictly necessary session cookie to keep your basket and sign-in working. The live chat window sets its own cookies so that a conversation continues between pages. If we enable analytics, it is configured with IP anonymisation. We do not use advertising cookies.</p>

<h2>Your rights</h2>
<p>You may ask for a copy of your data, ask us to correct or delete it, restrict or object to its use, or ask for it in a portable format. Email <a href="mailto:{{contact_email}}">{{contact_email}}</a> and we will respond within one month. You also have the right to complain to the Information Commissioner's Office (ico.org.uk).</p>

<h2>Security</h2>
<p>Passwords are stored as salted one-way hashes and cannot be recovered in plain text. The site is served over HTTPS. Access to order data is limited to people who need it.</p>

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
<p class="lead">These terms govern your use of {{site_name}} and any order you place with us. Please read them before ordering.</p>

<h2>1. Who we are</h2>
<p>{{site_name}} is operated by <strong>{{trader_name}}</strong>, {{address_oneline}}. "We", "us" and "our" refer to {{trader_name}}.</p>
<p>Registered company number: {{company_reg_no}}.</p>
<p>Contact: <a href="mailto:{{contact_email}}">{{contact_email}}</a> &middot; <a href="tel:{{phone}}">{{phone}}</a>.</p>

<h2>2. Eligibility</h2>
<p>You must be at least 18 years old to place an order.</p>

<h2>3. Products and descriptions</h2>
<p>We describe every watch as accurately as we can, including its specification, materials and dimensions. Photography is representative, and colours may look slightly different between screens. If we list a price or description in error, we will contact you before dispatch and you may cancel for a full refund.</p>

<h2>4. Pricing</h2>
<p>Prices are shown in {{currency_code}} and include any VAT that applies. Any delivery charge is shown at checkout before you order. Prices may change, but never after you have placed an order.</p>

<h2>5. Orders and acceptance</h2>
<p>Your order is an offer to buy. We email you to confirm it, and the contract is formed when we confirm your order. We may decline an order where an item is unavailable, where we cannot verify the delivery details, or where we suspect fraud; if so, we will tell you and refund anything you have paid.</p>

<h2>6. Payment</h2>
<p>After you place an order we email you to confirm it and arrange payment. Nothing is dispatched until payment has been received.</p>

<h2>7. Delivery, cancellation and returns</h2>
<p>Delivery is covered by our <a href="{{url_shipping_policy}}">Shipping Policy</a>. Your right to cancel and our return window are explained in our <a href="{{url_return_policy}}">Return Policy</a>, and refunds in our <a href="{{url_refund_policy}}">Refund Policy</a>. Each forms part of these terms.</p>

<h2>8. Warranty and your legal rights</h2>
<p>Every watch carries a {{warranty_months}}-month warranty against manufacturing defects. This is in addition to your rights under the Consumer Rights Act 2015, which nothing in these terms limits.</p>

<h2>9. Accounts</h2>
<p>Keep your account password confidential. Tell us promptly at <a href="mailto:{{support_email}}">{{support_email}}</a> if you think someone else has used your account.</p>

<h2>10. Acceptable use</h2>
<p>You agree not to use this site to break any law, to scrape or bulk-download its content, to probe or breach its security, or to transmit malicious code.</p>

<h2>11. Intellectual property</h2>
<p>The {{site_name}} name, logo, site design, text and photography belong to us and may not be reproduced without permission.</p>

<h2>12. Our liability</h2>
<p>We are responsible for loss you suffer that is a foreseeable result of our breaking these terms or failing to use reasonable care. We are not responsible for loss that was not foreseeable, or for business losses. Nothing here limits our liability for death or personal injury caused by negligence, for fraud, or for breach of your statutory rights.</p>

<h2>13. Governing law</h2>
<p>These terms are governed by the law of England and Wales. You may bring proceedings in the courts of England and Wales, or, if you live in Scotland or Northern Ireland, in the courts where you live.</p>

<h2>14. Contact</h2>
<p>Email <a href="mailto:{{contact_email}}">{{contact_email}}</a> &middot; Phone <a href="tel:{{phone}}">{{phone}}</a></p>
<p>We answer {{support_hours}}.</p>
HTML,
    ],

    'about-us' => [
        'title' => 'About Us',
        'sort_order' => 5,
        'meta_desc' => 'Who we are, where our watches are checked and dispatched, and how to reach a real person.',
        'body' => <<<'HTML'
<p class="lead">{{tagline}}</p>

<h2>Who we are</h2>
<p>{{site_name}} is a watch brand based in {{city}}. We design our own watches across six collections, from dive and chronograph pieces to slim dress watches, and we handle every order ourselves: inspection, packing, dispatch and after-sales support.</p>
<p>{{site_name}} is operated by {{legal_entity_name}}.</p>

<h2>What you can expect</h2>
<ul>
  <li><strong>Every watch checked by hand</strong> in {{city}} before it is dispatched.</li>
  <li><strong>Tracked, insured delivery</strong> with {{ship_carriers}}.</li>
  <li><strong>{{return_window_days}}-day returns.</strong> Full details in our <a href="{{url_return_policy}}">Return Policy</a>.</li>
  <li><strong>{{warranty_months}}-month warranty</strong> against manufacturing and movement defects.</li>
</ul>

<h2>Where to find us</h2>
<p>{{address_oneline}}</p>

<h2>Talk to us</h2>
<p>Email <a href="mailto:{{contact_email}}">{{contact_email}}</a> or call <a href="tel:{{phone}}">{{phone}}</a>. You can also use our <a href="{{url_contact}}">contact form</a>; every message reaches a person.</p>
<p>We answer {{support_hours}}.</p>
HTML,
    ],

    'faq' => [
        'title' => 'Frequently Asked Questions',
        'sort_order' => 60,
        'meta_desc' => 'Answers on ordering, payment, delivery, returns, warranty and customs.',
        'body' => <<<'HTML'
<p class="lead">Short answers to the questions we are asked most. If yours is not here, <a href="{{url_contact}}">get in touch</a>.</p>

<h2>Ordering &amp; payment</h2>
<h3>Do I need an account to order?</h3>
<p>No. You can check out as a guest. An account lets you see your order history.</p>

<h3>How do I pay?</h3>
<p>After you place your order we email you to confirm it and arrange payment. Nothing is charged at checkout, and nothing is dispatched until payment has been received.</p>

<h3>Can I change or cancel my order?</h3>
<p>Yes, at no cost, at any time before dispatch. Email <a href="mailto:{{support_email}}">{{support_email}}</a> with your order number.</p>

<h2>Delivery</h2>
<h3>Where do you ship from?</h3>
<p>Every watch is checked by hand and dispatched from {{city}}, {{country}}.</p>

<h3>How long will delivery take?</h3>
<p>We dispatch within {{ship_processing_days}} business day(s), and delivery then takes {{delivery_estimate}} depending on destination.</p>

<h3>How do I track my order?</h3>
<p>A tracking number is emailed to you when the carrier collects your parcel. Full details are in our <a href="{{url_shipping_policy}}">Shipping Policy</a>.</p>

<h3>Do you ship internationally?</h3>
<p>{{ship_countries_note}}</p>

<h3>Will I be charged customs duty?</h3>
<p>For deliveries outside the UK, import duties and taxes are set by your country and payable by you on arrival. They are not included in our prices.</p>

<h2>Returns &amp; warranty</h2>
<h3>Can I return a watch?</h3>
<p>Yes. You have a legal right to cancel within 14 days of delivery, and we extend that to {{return_window_days}} days. See the <a href="{{url_return_policy}}">Return Policy</a>.</p>

<h3>How long does a refund take?</h3>
<p>{{refund_processing_days}} business days from receiving your return. See the <a href="{{url_refund_policy}}">Refund Policy</a>.</p>

<h3>Is there a warranty?</h3>
<p>Yes: {{warranty_months}} months against manufacturing and movement defects, in addition to your statutory rights.</p>

<h2>Support</h2>
<h3>How do I reach a person?</h3>
<p>Email <a href="mailto:{{support_email}}">{{support_email}}</a>, call <a href="tel:{{phone}}">{{phone}}</a>, or use the <a href="{{url_contact}}">contact form</a>.</p>
<p>We answer {{support_hours}}.</p>
HTML,
    ],
];
