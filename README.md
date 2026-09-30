# Aurel Time — PHP + MySQL Watch Store

A storefront built on plain PHP 8 + PDO (no framework), backed by MariaDB. Product data
(1,140 watches) lives in MySQL; every business fact — address, phone, shipping terms,
return window, policy copy — lives in an editable `settings` table and is managed from
the admin panel.

## Run it

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Visit **http://localhost/watches/**
3. Admin panel: **http://localhost/watches/admin/**

> **Important — MariaDB runs on port 3307, not 3306.**
> A standalone **MySQL 8.0** occupies 3306 on this machine, which blocked XAMPP's MariaDB
> from starting. XAMPP's MariaDB was moved to **3307** (`C:\xampp\mysql\bin\my.ini`,
> backup at `my.ini.bak-claude`). The app and phpMyAdmin already point at 3307.
> MySQL 8 on 3306 is untouched.

- **Database:** `watches_shop` on `127.0.0.1:3307`, user `root`, no password.
- **phpMyAdmin:** http://localhost/phpmyadmin (configured for 3307).

## First thing to do

Open the admin dashboard. It shows a **pre-launch checklist** listing every business
detail still set to a shipped placeholder. Work through it before taking real orders —
the shipped address (`123 Commerce Way, Wilmington, DE`) and phone
(`+1 (302) 555-0147`, inside the NANP reserved fictional range) exist so the site is
never *silently* wrong, not because they are usable.

**Search indexing ships OFF** (`robots_index` setting → `noindex`). Turn it on only once
the checklist is clear.

## Setup vs. migrate — these are not the same

| | `setup.php` | `migrate.php` |
|---|---|---|
| What | Drops every table, reloads `schema.sql`, reseeds 1,140 products | Adds missing tables/columns, seeds defaults |
| Data loss | **Total** | None — never issues DROP/TRUNCATE |
| Access | CLI only (403 over HTTP), refuses to run when data exists unless `--force` | CLI, or loopback + `MIGRATE_TOKEN` |
| Re-runnable | Only with `--force` | Yes — reports zero changes on a second run |

```
C:\xampp\php\php.exe migrate.php                                    # safe, idempotent
C:\xampp\php\php.exe migrate.php --admin=you@example.com            # promote existing user
C:\xampp\php\php.exe migrate.php --create-admin=you@x.com --password=secret
C:\xampp\php\php.exe migrate.php --force-pages                      # reset policy text to defaults
C:\xampp\php\php.exe setup.php --force                              # nuke and reseed
```

## Editing policy pages

Policy bodies are HTML in the `content_pages` table, edited at **Admin → Pages**. They
support `{{placeholder}}` tokens that resolve to live settings:

> You may return any timepiece within **{{return_window_days}} days** of delivery.

Change `return_window_days` once in Settings and the announcement bar, product page, FAQ,
return policy and refund policy all update together. The page editor lists every available
placeholder with its current value. Substituted values are HTML-escaped automatically;
they are **not** safe inside `<script>` or `<style>` blocks.

Pages listed in `PAGE_STUBS` (`app/pages.php`) get a clean URL like
`/watches/return-policy.php`. Pages created in admin work immediately via
`/watches/page.php?slug=…`; add a one-line stub file to prettify.

## Layout

```
watches/
  app/
    config.php          DB credentials, BASE_URL, site_origin()
    db.php              db() dies on failure; db_try() returns null so callers can degrade
    settings.php        setting(), setting_set(), settings_all()
    settings_defs.php   the ~50 editable keys, their types, defaults and hints
    seed_defaults.php   idempotent seeding, shared by migrate.php and setup.php
    pages.php           content pages + {{placeholder}} rendering
    page_defaults.php   default policy copy (US-oriented)
    page_render.php     the shared renderer behind every policy page
    auth.php            current_user_is_admin(), require_admin(), order statuses
    mail.php            send_mail() + order confirmation body
    _schema.php         JSON-LD builders (Organization, Product, Breadcrumb, WebSite)
    header/footer/_card/_hero.php
  admin/                dashboard, settings, pages, orders, messages, reviews
  index / shop / product / cart / checkout / account / login / register / logout .php
  contact.php           contact page + working enquiry form
  page.php + 7 stubs    about-us, shipping-policy, return-policy, refund-policy,
                        privacy-policy, terms-of-service, faq
  sitemap.php           XML sitemap (also /sitemap.xml via .htaccess)
  feed.php              Google Shopping feed — token-gated, see the warning below
  robots.txt .htaccess
  schema.sql migrate.php setup.php
  data/seed_data.json   extracted product data (1,140 products)
```

## Email

`app/mail.php` uses PHP's `mail()`, which on a stock XAMPP install has no MTA configured
and simply fails. Every caller treats that as non-fatal and **writes to the database
first** — a contact enquiry lands in `contact_messages` and an order in `orders`
regardless. The confirmation screen tells the customer honestly when the email could not
be sent rather than claiming it was. Configure `[mail function]` in `php.ini` for real
delivery.

## Live chat (Chatway)

Set **Chatway widget ID** at *Admin → Settings → Live Chat & Integrations* — the `id=`
value out of your Chatway embed snippet, not the whole `<script>` tag. While it is empty
the storefront loads **no chat JavaScript and no chat cookies at all**, same gate the
Google Analytics field uses.

Once set, `app/footer.php` emits the widget before `</body>` on storefront pages only —
never in `/admin`, which has its own shell. Two entry points appear automatically: a
"Start a live chat" link in the footer's *Talk To Us* tile and a *Live chat* row on the
contact page. Both carry `class="js-chat"`, handled by a delegated listener in
`app/footer.php`; drop that class on any element to make it open the widget. The handler
only calls `preventDefault()` once `$chatway` actually exists, so if the vendor script is
blocked the link still falls through to the contact form instead of going dead.

The widget ID is escaped with `rawurlencode()` because it lands in a query string inside a
`src` attribute.

> Chatway sets its own cookies. `app/page_defaults.php` still tells visitors only a
> strictly necessary session cookie is used — correct that at *Admin → Pages → Privacy
> Policy* before enabling chat on a public site.

## Google Merchant Center

`feed.php` produces a valid Google Shopping feed, but **do not submit it** while product
titles carry third-party trademarks (Rolex, Patek Philippe, Audemars Piguet) or while the
store describes its goods as replicas. Merchant Center enforces its counterfeit policy by
reading product titles, not policy pages, and suspends accounts without warning. Policy
pages, structured data and contact details do not change that outcome — rebranding the
catalog to your own marque does. The feed is disabled until you set a `feed_token`
in Settings.

## Things deliberately not faked

- **Reference prices.** The "was £X" strikethrough and discount badge appear only when a
  real `products.compare_at_price` is stored above the selling price. Product cards
  previously synthesised a 40–55% saving from `crc32($handle)` — an invented reference
  price is a deceptive-pricing violation under the FTC Act.
- **Reviews.** `setup.php` can generate sample reviews, but only behind `--demo-reviews`,
  off by default. `AggregateRating` markup is emitted only when reviews actually exist.
  Purge any generated ones at **Admin → Reviews**.
- **Capabilities.** The footer no longer advertises 24-hour chat support or 12-month
  instalments, neither of which exists. Shipping and return terms come from settings, so
  the announcement bar can no longer promise 30-day returns while the product page says 14.
- **Social links.** Default to empty. An empty `sameAs` is honest; a bare
  `https://facebook.com` with no profile path is a recognised placeholder tell.

## Archived scrape

The original HTTrack mirror (~4,900 files, ~3 GB: `products/`, `collections/`, `comments/`,
`pages/`, `account/`, `assets/socks/`, and the root `*.html`) has been **moved out of the
web root** to `C:\xampp\_archive_watches\`. It was publicly served at
`/watches/pages/contact-us.html`, carried `Sale@watchespro.to` in JSON-LD ~9,900 times,
named "Rolex Replica Watches" as the organisation, listed two Hong Kong phone numbers, and
posted its forms to a live competitor domain. Nothing was deleted — move it back if needed.

Consequence: `scripts/extract.py` parses `products/*.html` and can no longer run.
That is fine — `data/seed_data.json` already exists and is what `setup.php` reads.

## Known remaining work

- **Product images are still hotlinked** from `cdn.febdovoimage.com`, the scraped source's
  CDN. They can vanish or change without notice. Rehosting them locally is the next
  meaningful improvement. (The homepage hero no longer hotlinks that CDN — it now builds
  its slides from your own catalog.)
- **No payment gateway.** Checkout collects the order and emails it; it does not charge.
  Orders are created with status `pending`, and the page says so rather than claiming a
  payment was processed.
- **No product CRUD in admin.** Catalog edits go through the database or a reseed.
