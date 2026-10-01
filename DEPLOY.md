# Deploying to aureltime.site

Target: cPanel shared hosting. Database `dieuaxvb_watches`.

Work top to bottom. Step 6 (search indexing) is deliberately last.

> **The store is already live with the old catalogue.** You are not doing a
> fresh deploy — do section 0 below, not sections 1–2.

---

## 0. Aurel Time rollout (existing live store)

The store is now **Aurel Time**: six collections (Tideline, Circuit, Transit,
Ascot, Monolith, Datum), a black-and-gold theme, one price per watch, UK policy
pages and no generated reviews. The live domain moves to **aureltime.site**.

**Before anything else:** point `aureltime.site` at this hosting account, issue
its SSL certificate, and create the mailboxes `contact@`, `support@` and
`orders@aureltime.site`. The settings below already use those addresses.

**Upload** every changed PHP file and the new `assets/brand/` folder. Do **not**
upload `assets/banners/` or `assets/default/`: the banners are the old site's
third-party campaign artwork and nothing links to either folder any more. Delete
them from the server if they are there.

**Then import, in this order** (regenerate both first with
`php scripts/make-catalog-dump.php`):

| Order | File | Effect |
|---|---|---|
| 1 | `seed-products.sql` | Wipes and reloads brands, products, product_images, variants (1 per product) |
| 2 | `deploy/rebrand-settings.sql` | Deletes all reviews; upserts identity, email, shipping and share-image settings; rewrites the 7 policy pages |

Both are re-runnable.

**What survives:** users, orders, order_items, contact_messages and every
setting not listed in the patch.

**Then confirm on the live site:**

```
https://aureltime.site/                    -> title reads "Aurel Time", black & gold
https://aureltime.site/shop.php?brand=tideline
https://aureltime.site/terms-of-service.php  -> England and Wales, no blank sentences
```

### Do not import `seed.sql`

`seed.sql` and `deploy/seed.sql.gz` are the **full** dump and still hold the
pre-rebrand counterfeit catalogue. Importing either would undo the rebrand on a
live store. Both now begin with a statement that deliberately fails
(`Table '...THIS_SEED_IS_STALE...' doesn't exist`), which aborts the import
before any `DROP TABLE` runs — verified against a scratch database with all 11
tables still standing afterwards. Regenerate them from the de-branded database
before using them for a genuinely empty install.

### Still open before advertising

`products.image` still points at `cdn.febdovoimage.com` and those photographs
show third-party trademarked dials. De-branding the text did not change the
pictures. (The homepage banners that had the same problem are gone; the hero
is now built from the logo.) Replace the product photos, then re-run
`php scripts/make-catalog-dump.php` and re-import. Until that is done, do not
submit the feed to Merchant Center and do not flip `robots_index`.

---

## 1. Upload the files

Upload everything in this folder to the domain's document root (`public_html/`),
**except these two**:

| Do not upload | Why |
|---|---|
| `app/config.local.php` | Development database override. Its **absence** is what makes the server use the live cPanel credentials. Upload it and the site tries to reach `127.0.0.1:3307` and shows a 503. |
| `deploy/` | Contains the SQL dump. Nothing here is web content. |

Everything else goes up as-is, including the three `.htaccess` files inside
`app/`, `data/` and `scripts/` — those are what keep the database password
unreachable. FTP clients hide dotfiles by default; turn hidden files **on** and
confirm all four `.htaccess` files arrived.

## 2. Import the database

> **Only for a genuinely empty database.** If the store is already live, go back
> to section 0. Both files named here are currently **stale** — they predate the
> de-branding and carry the counterfeit catalogue, and both are guarded with a
> deliberate abort. Regenerate them from the de-branded database before use.

In cPanel → phpMyAdmin, select `dieuaxvb_watches`, then Import:

- `deploy/seed.sql.gz` (260 KB — use this one; phpMyAdmin's upload cap is often
  2 MB and the uncompressed file is 1.6 MB)
- or `seed.sql` in the project root (1.6 MB), same content uncompressed

Or from a shell:

```
mysql -u dieuaxvb_watches -p dieuaxvb_watches < seed.sql
```

`seed.sql` is self-contained: structure for all 11 tables plus data. It carries
no `CREATE DATABASE` or `USE` on purpose — shared hosting assigns the database
name. It is **re-runnable**: every table is dropped and recreated, so a failed
import can simply be retried.

Verified by importing into an empty scratch database twice, with identical
results both times: 1,140 products, 6,415 images, 3,192 variants, 9 brands, 51
settings, 7 content pages, 1 admin user. The `£` symbol survives the UTF-8 round
trip and the hand-edited England-and-Wales governing-law clause in Terms is
preserved.

`reviews`, `orders`, `order_items` and `contact_messages` are created **empty** —
see *Known issues* for why reviews are excluded.

### Reloading only the catalog later

`seed-products.sql` (or `deploy/seed-products.sql.gz`) reloads **just** the
1,140 products, 6,415 images, 3,192 variants and 6 collections. Regenerate it
with `php scripts/make-catalog-dump.php`, which refuses to write a file
containing a trademark. It creates no tables
and leaves `settings`, `content_pages`, `users` and any real `orders` untouched,
so it is the file to use for a catalog refresh on a store that is already live.

Verified by importing it three times over a database that had a real order, an
order line and an edited setting: catalog counts came out exact every time, the
order and the setting survived, and there were zero orphaned images or variants.

One caveat, stated in the file's own header: `reviews` has a foreign key onto
`products` with `ON DELETE CASCADE`. Once you have real customer reviews, back
them up before re-seeding the catalog.

### Resetting only the admin login later

`seed-admin.sql` (4 KB) creates or resets the administrator account and nothing
else. It creates no tables and touches no other row — catalog, settings, orders
and customer accounts are all left alone, so it is safe to import into a live
database.

Use it to **reset a forgotten admin password**: generate a new hash with

```
php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT);"
```

replace the hash in the file, and re-import.

Tested against a database made to look like a live store — three existing
customer accounts holding ids 1–3, and no admin:

- Three consecutive imports produced **one** admin row, not three; `users.email`
  has a UNIQUE key so a repeat import updates rather than duplicates.
- The three customer accounts and the full catalog were untouched.
- After deliberately demoting the account (`is_admin = 0`), renaming it and
  corrupting its password hash, one re-import restored all three.
- It also works on an empty `users` table. No `id` is written, so the row takes
  whatever `AUTO_INCREMENT` value is free rather than colliding with an existing
  account.

### A note on statement sizes

Both files cap every `INSERT` at ~32 KB. Straight out of `mysqldump` the
`product_images` rows came out as a **single 795 KB statement**, which dies with
"MySQL server has gone away" on any host running the common 1 MB
`max_allowed_packet`. If you ever regenerate these dumps, keep
`--net-buffer-length=32768`.

Credentials are already baked into `app/config.php`:

```php
DB_HOST 'localhost'   DB_NAME 'dieuaxvb_watches'
DB_USER 'dieuaxvb_watches'   DB_PASS  (set)
```

If cPanel gives the database a different host than `localhost`, that is the only
line to change.

## 3. Confirm it came up

```
https://aureltime.site/
https://aureltime.site/shop.php
https://aureltime.site/robots.txt
https://aureltime.site/sitemap.xml
```

Then confirm the internals are sealed — **all four must return 403 or 404**:

```
/app/config.php      /data/seed_data.json      /scripts/       /setup.php
```

If `/app/config.php` returns anything other than 403/404, stop and fix it before
going further: that file contains the database password.

## 4. Fill the remaining business facts

Admin → Settings, at `https://aureltime.site/admin/`.

Two admin accounts ship in the seed: `watches@alexcleanwatchfactory.site` and
`atirhgael78@gmail.com`. The password for the first is in the header of
`seed-admin.sql` — that file is **gitignored** and is not in this repository.

Sign-in is by **email**, not a username — `login.php` queries `WHERE email = ?`
and the field is `<input type="email">`, so a bare `watches` cannot be submitted.
`watches` is the display name.

> Change the seeded password after the first login, then delete `seed-admin.sql`
> and `seed.sql` from the server. Both carry it in plaintext.

Already set: store name, address, phone, WhatsApp, currency (GBP/£), timezone
(Europe/London), return window, warranty, support hours, carriers, destinations
and the three `@aureltime.site` email addresses.

Still empty: `legal_entity_name` and `company_reg_no`. Pages no longer print a
broken sentence for them (the renderer drops any line whose setting is empty),
but UK law requires the trader's legal name and, for a company, its number. The
admin dashboard's pre-launch checklist flags them until they are filled.

## 5. Check the live chat

The NexaHub widget is hardcoded in `app/footer.php`. Load the site and confirm
the chat bubble appears. If it does not, check the domain is allowed in the
NexaHub dashboard.

> If the chat widget sets non-essential cookies, UK PECR still expects
> consent before non-essential cookies are set; add a consent prompt before
> relying on chat at scale.

## 6. HTTPS, then HSTS, then indexing — in that order

**HTTPS** is already forced in `.htaccess`. The rule skips `localhost` and also
checks `X-Forwarded-Proto`, because shared hosting terminates TLS at a proxy and
redirecting on `%{HTTPS}` alone is the classic infinite redirect loop.

**HSTS** is staged but commented out, at `.htaccess` line ~62. Enable it only
once `https://` loads with a valid certificate. It is a one-way door: browsers
that see it refuse plain HTTP for the full year and the server cannot call that
back.

**Search indexing ships OFF.** `robots.txt` currently returns `Disallow: /` and
every page carries `noindex`. Flip `robots_index` in Admin → Settings → SEO only
after steps 1–5 are done and the pre-launch checklist is clear. Indexing a store
full of placeholder details is much harder to undo than to delay.

`robots.txt` and `sitemap.xml` are both generated from `BASE_URL`, so they are
correct at the domain root with no edit.

---

## Known issues

**Product images are hotlinked.** All 6,415 images load from
`cdn.febdovoimage.com` — the CDN of the site this catalog was scraped from. It
responds today, but it is a third party under no obligation to you: it can
rate-limit, hotlink-block, change paths, or disappear, and the storefront becomes
1,140 products with no pictures. Rehosting them locally is the single most
valuable follow-up.

**Reviews were excluded from the dump, deliberately.** The local database holds
3,990 reviews generated from **20 author names and 12 body texts across 2
timestamps** — placeholder filler, not customer feedback. The product pages were
publishing them to Google as `AggregateRating` structured data.

Publishing fake consumer reviews is a banned practice under the UK Digital
Markets, Competition and Consumers Act 2024, enforceable directly by the CMA, and
it independently breaches Google's structured-data policy. They are excluded from
the production import; the table ships empty and `AggregateRating` is only emitted
when real reviews exist. Do not import them.

**No payment gateway.** Checkout records the order and emails it, it does not
charge. Orders are created `pending`, and the checkout, confirmation email,
Terms and FAQ all say payment is arranged by email before dispatch. Google
Merchant Center expects an online checkout: add one before submitting the feed.

**Email needs configuring.** `app/mail.php` uses PHP `mail()`. Every caller
writes to the database first and treats send failure as non-fatal, so enquiries
and orders are never lost — but confirmation emails will not arrive until cPanel
mail is set up for the domain.

`migrate.php --force-pages` is now safe: the defaults in `app/page_defaults.php`
are the UK wording, so it rewrites the pages to match what the patch installs.

**Do not run `setup.php`.** It drops every table and reseeds from
`data/seed_data.json`, which is the original counterfeit catalogue. It no longer
generates reviews, but the catalogue it loads must never reach a live store.
