# Deploying to alexcleanwatchfactory.site

Target: cPanel shared hosting. Database `dieuaxvb_watches`.

Work top to bottom. Step 6 (search indexing) is deliberately last.

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
1,140 products, 6,415 images, 3,192 variants and 9 brands. It creates no tables
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
https://alexcleanwatchfactory.site/
https://alexcleanwatchfactory.site/shop.php
https://alexcleanwatchfactory.site/robots.txt
https://alexcleanwatchfactory.site/sitemap.xml
```

Then confirm the internals are sealed — **all four must return 403 or 404**:

```
/app/config.php      /data/seed_data.json      /scripts/       /setup.php
```

If `/app/config.php` returns anything other than 403/404, stop and fix it before
going further: that file contains the database password.

## 4. Fill the remaining business facts

Admin → Settings, at `https://alexcleanwatchfactory.site/admin/`.

Two admin accounts ship in the seed: `watches@alexcleanwatchfactory.site` and
`atirhgael78@gmail.com`. The password for the first is in the header of
`seed-admin.sql` — that file is **gitignored** and is not in this repository.

Sign-in is by **email**, not a username — `login.php` queries `WHERE email = ?`
and the field is `<input type="email">`, so a bare `watches` cannot be submitted.
`watches` is the display name.

> Change the seeded password after the first login, then delete `seed-admin.sql`
> and `seed.sql` from the server. Both carry it in plaintext.

Already set: store name, address, phone, WhatsApp, currency (GBP/£), timezone
(Europe/London), return window, warranty, and all three email addresses
(`contact@alexcleanwatchfactory.site`).

Still empty, and each one currently prints a broken sentence on a live page:

| Setting | Where the gap shows |
|---|---|
| `legal_entity_name` | Privacy Policy renders *"what personal information&nbsp;&nbsp;collects"*. Terms says *"operated by ,"*. Needs your **registered company name**. |
| `support_hours` | FAQ and About Us render *"We answer ."* |
| `ship_carriers` | Privacy Policy renders *"shipping carriers ()"*. This string is a disclosure of who receives customer addresses — name only carriers you actually use. |

The admin dashboard's pre-launch checklist tracks these.

## 5. Point Chatway at the domain

The widget (`PW016Uld6lq2`) is wired and gated on a setting, but Chatway matches
a widget to the domains registered in your dashboard — which is why it does not
appear on `localhost`. Add `alexcleanwatchfactory.site` in the Chatway dashboard,
then load the site and confirm the bubble appears bottom-right.

Chatway swallows its own errors (`logError(e){}` is empty), so a domain mismatch
looks exactly like nothing happening — no console error to go on.

> The Privacy Policy still tells visitors only a strictly necessary session
> cookie is used. Chatway sets its own. Correct that at **Admin → Pages →
> Privacy Policy** before running chat on the live site.

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
charge. Orders are created `pending` and the confirmation says so.

**Email needs configuring.** `app/mail.php` uses PHP `mail()`. Every caller
writes to the database first and treats send failure as non-fatal, so enquiries
and orders are never lost — but confirmation emails will not arrive until cPanel
mail is set up for the domain.

**Do not run `migrate.php --force-pages`.** It rewrites all seven policy bodies
and would revert the hand-edited England-and-Wales governing-law clause in Terms.

**Do not run `setup.php`.** It drops every table and reseeds — including the
3,990 placeholder reviews.
