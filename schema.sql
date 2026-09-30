-- Aurel Time — schema (MySQL 8 / MariaDB 10.4 compatible)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Loaded only by setup.php, which is destructive by design. To add these
-- tables to an existing install without losing data, run migrate.php.
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS variants;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS brands;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS content_pages;
DROP TABLE IF EXISTS contact_messages;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE brands (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name  VARCHAR(120) NOT NULL,
  slug  VARCHAR(140) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_brand_slug (slug),
  UNIQUE KEY uq_brand_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  handle      VARCHAR(255) NOT NULL,
  name        VARCHAR(400) NOT NULL,
  brand_id    INT UNSIGNED NOT NULL,
  mpn         VARCHAR(120) DEFAULT NULL,
  gtin        VARCHAR(20) DEFAULT NULL,
  description TEXT,
  base_price  DECIMAL(10,2) NOT NULL DEFAULT 0,
  -- Genuine former/recommended retail price. NULL means no reference price is
  -- known, and the product card then shows no strikethrough and no discount
  -- badge. Never populate this with a computed or invented figure.
  compare_at_price DECIMAL(10,2) DEFAULT NULL,
  item_condition   VARCHAR(20) NOT NULL DEFAULT 'new',
  google_category  VARCHAR(120) DEFAULT NULL,
  image       VARCHAR(600) DEFAULT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_handle (handle),
  KEY idx_product_brand (brand_id),
  KEY idx_product_price (base_price),
  FULLTEXT KEY ft_product_name (name, description),
  CONSTRAINT fk_product_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  url        VARCHAR(600) NOT NULL,
  position   SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_img_product (product_id),
  CONSTRAINT fk_img_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE variants (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  grade      VARCHAR(120) NOT NULL,
  price      DECIMAL(10,2) NOT NULL DEFAULT 0,
  sku        VARCHAR(120) DEFAULT NULL,
  in_stock   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_var_product (product_id),
  CONSTRAINT fk_var_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  author     VARCHAR(120) NOT NULL DEFAULT 'Anonymous',
  rating     TINYINT NOT NULL DEFAULT 5,
  body       TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rev_product (product_id),
  CONSTRAINT fk_rev_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name          VARCHAR(120) NOT NULL DEFAULT '',
  is_admin      TINYINT(1) NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED DEFAULT NULL,
  email         VARCHAR(190) NOT NULL,
  full_name     VARCHAR(160) NOT NULL,
  phone         VARCHAR(40) DEFAULT NULL,
  address       VARCHAR(500) NOT NULL,
  city          VARCHAR(120) DEFAULT NULL,
  region        VARCHAR(120) DEFAULT NULL,
  postcode      VARCHAR(30) DEFAULT NULL,
  country       VARCHAR(80) DEFAULT NULL,
  subtotal      DECIMAL(10,2) NOT NULL DEFAULT 0,
  shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  total         DECIMAL(10,2) NOT NULL DEFAULT 0,
  status        VARCHAR(40) NOT NULL DEFAULT 'pending',
  tracking_number VARCHAR(120) DEFAULT NULL,
  admin_note    TEXT DEFAULT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order_user (user_id),
  KEY idx_order_status (status, created_at),
  CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id   INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED DEFAULT NULL,
  variant_id INT UNSIGNED DEFAULT NULL,
  name       VARCHAR(400) NOT NULL,
  grade      VARCHAR(120) DEFAULT NULL,
  price      DECIMAL(10,2) NOT NULL DEFAULT 0,
  qty        INT NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_oi_order (order_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Editable business settings. Keys and defaults are defined in
-- app/settings_defs.php and seeded by app/seed_defaults.php.
-- skey/svalue rather than key/value: KEY is a reserved word.
CREATE TABLE settings (
  skey       VARCHAR(80) NOT NULL,
  svalue     TEXT DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Policy and information pages, editable in admin/page_edit.php.
-- MEDIUMTEXT not TEXT: an HTML terms-of-service approaches the 64 KB TEXT cap,
-- and silent truncation on save is a miserable bug to diagnose.
CREATE TABLE content_pages (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug           VARCHAR(120) NOT NULL,
  title          VARCHAR(200) NOT NULL,
  body           MEDIUMTEXT DEFAULT NULL,
  meta_title     VARCHAR(200) NOT NULL DEFAULT '',
  meta_desc      VARCHAR(320) NOT NULL DEFAULT '',
  is_published   TINYINT(1) NOT NULL DEFAULT 1,
  show_in_footer TINYINT(1) NOT NULL DEFAULT 1,
  sort_order     SMALLINT NOT NULL DEFAULT 0,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_page_slug (slug),
  KEY idx_page_footer (is_published, show_in_footer, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(160) NOT NULL,
  email      VARCHAR(190) NOT NULL,
  subject    VARCHAR(200) NOT NULL DEFAULT '',
  body       TEXT,
  order_ref  VARCHAR(60) DEFAULT NULL,
  ip         VARCHAR(45) DEFAULT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_msg_unread (is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
