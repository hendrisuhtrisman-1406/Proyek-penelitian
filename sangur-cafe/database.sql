-- ============================================================
-- DATABASE: sangur_cafe
-- Sistem Informasi Penjualan Berbasis Web - Sangur Cafe Pekanbaru
-- Cara import: buka phpMyAdmin -> Import -> pilih file ini -> Go
-- ============================================================

CREATE DATABASE IF NOT EXISTS `sangur_cafe`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sangur_cafe`;

-- ------------------------------------------------------------
-- TABEL users (kasir, kitchen, owner)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `menus`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `tables`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('kasir','kitchen','owner') NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password di-hash dengan bcrypt (password_hash PHP).
-- Akun default: owner/owner123, kasir/kasir123, kitchen/kitchen123
INSERT INTO `users` (`nama`, `username`, `password_hash`, `role`) VALUES
('Pemilik Sangur', 'owner',   '$2y$10$o2AhSTlCtxt.QV/z5sFRCuZlRg9v04DKYEhnGtdPWBJQCWZiqG/4C', 'owner'),
('Kasir Aulia',    'kasir',   '$2y$10$3/YyLNdm4ICw9J2LOqQH5uTQgx755L4vavrA0PX4qF2JDXuRQQ5L6', 'kasir'),
('Chef Budi',      'kitchen', '$2y$10$jv4u.a9F2DkD9eISasz.h.JSyHf8sQq8r6l.yoFIbpmjP/V4K42p2', 'kitchen');

-- ------------------------------------------------------------
-- TABEL tables (meja pelanggan + token QR)
-- ------------------------------------------------------------
CREATE TABLE `tables` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_number` INT NOT NULL UNIQUE,
  `qr_token` VARCHAR(32) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tables` (`table_number`, `qr_token`) VALUES
(1, 'e064aed1904b04eb4cb0bf73f722cdba'),
(2, 'bf8ccdccf1561526d8845eed3a38fecf'),
(3, 'cb50654ed6e8ea55c5cd84fb212db918'),
(4, 'fbfc0feb5e299ed5b3ed6ed3abbb070e'),
(5, 'dc61de1a1aa2ac5d94ba46fe9b247c54'),
(6, '1d5dc15e42ecabb4299aaef287c54996');

-- ------------------------------------------------------------
-- TABEL categories (kategori menu)
-- ------------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `categories` (`name`) VALUES ('Makanan'), ('Minuman');

-- ------------------------------------------------------------
-- TABEL menus (daftar menu)
-- ------------------------------------------------------------
CREATE TABLE `menus` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `price` INT NOT NULL DEFAULT 0,
  `category_id` INT UNSIGNED NOT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('tersedia','habis') NOT NULL DEFAULT 'tersedia',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `menus` (`name`, `description`, `price`, `category_id`, `image`, `status`) VALUES
('Nasi Goreng Spesial', 'Nasi goreng dengan telur mata sapi, ayam suwir, dan kerupuk.', 25000, 1, 'nasi-goreng.svg', 'tersedia'),
('Mie Goreng Cafe',     'Mie goreng dengan sayuran segar dan topping ayam.',          22000, 1, 'mie-goreng.svg', 'tersedia'),
('Chicken Wings (6pcs)','Sayap ayam goreng tepung bumbu BBQ pedas manis.',            30000, 1, 'chicken-wings.svg', 'tersedia'),
('Beef Burger',         'Burger daging sapi dengan keju, selada, dan saus spesial.',  28000, 1, 'beef-burger.svg', 'tersedia'),
('Kentang Goreng',      'Kentang goreng renyah dengan saus sambal dan mayones.',      15000, 1, 'kentang-goreng.svg', 'tersedia'),
('Roti Bakar Coklat',   'Roti bakar dengan olesan coklat dan susu kental manis.',     18000, 1, 'roti-bakar.svg', 'habis'),
('Kopi Hitam',          'Kopi hitam lokal Sangur, diseduh dengan teknik pour over.',  12000, 2, 'kopi-hitam.svg', 'tersedia'),
('Cappuccino',          'Espresso dengan susu steamed dan foam lembut.',              18000, 2, 'cappuccino.svg', 'tersedia'),
('Teh Tarik',           'Teh tarik khas dengan foam tebal dan rasa manis pas.',       15000, 2, 'teh-tarik.svg', 'tersedia'),
('Es Coklat',           'Minuman coklat dingin dengan whip cream di atasnya.',        17000, 2, 'es-coklat.svg', 'tersedia'),
('Jus Alpukat',         'Jus alpukat segar dengan susu coklat dan gula aren.',        20000, 2, 'jus-alpukat.svg', 'tersedia'),
('Lemon Tea',           'Teh lemon segar dengan madu, dingin dan menyegarkan.',       14000, 2, 'lemon-tea.svg', 'tersedia');

-- ------------------------------------------------------------
-- TABEL orders (transaksi pesanan)
-- status: pending -> disiapkan -> siap -> disajikan
-- payment_status: unpaid / paid
-- ------------------------------------------------------------
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_id` INT UNSIGNED NOT NULL,
  `customer_name` VARCHAR(100) DEFAULT NULL,
  `total_price` INT NOT NULL DEFAULT 0,
  `status` ENUM('pending','disiapkan','siap','disajikan') NOT NULL DEFAULT 'pending',
  `payment_status` ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `ordered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `table_id` (`table_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_order_table` FOREIGN KEY (`table_id`) REFERENCES `tables`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- TABEL order_items (rincian item per pesanan)
-- ------------------------------------------------------------
CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `menu_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `price_at_order` INT NOT NULL,
  `subtotal` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `menu_id` (`menu_id`),
  CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_menu`  FOREIGN KEY (`menu_id`)  REFERENCES `menus`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- TABEL transactions (pencatatan pembayaran kasir)
-- ------------------------------------------------------------
CREATE TABLE `transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL UNIQUE,
  `amount_paid` INT NOT NULL,
  `change_amount` INT NOT NULL DEFAULT 0,
  `cashier_id` INT UNSIGNED NOT NULL,
  `paid_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cashier_id` (`cashier_id`),
  CONSTRAINT `fk_trx_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trx_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- DATA SAMPLE: pesanan
-- ------------------------------------------------------------
INSERT INTO `orders` (`id`, `table_id`, `customer_name`, `total_price`, `status`, `payment_status`, `ordered_at`) VALUES
(1, 1, 'Andi',      49000, 'pending',    'unpaid', '2026-11-17 10:15:00'),
(2, 3, 'Rina',      47000, 'disiapkan',  'unpaid', '2026-11-17 10:32:00'),
(3, 2, 'Bapak Surya',76000, 'siap',      'paid',   '2026-11-17 09:40:00'),
(4, 5, NULL,        52000, 'disajikan',  'paid',   '2026-11-16 19:05:00'),
(5, 4, 'Maya',      62000, 'disajikan',  'paid',   '2026-11-16 14:20:00');

INSERT INTO `order_items` (`order_id`, `menu_id`, `quantity`, `price_at_order`, `subtotal`) VALUES
-- Order 1: Nasi Goreng x1, Kopi Hitam x2 = 25000 + 24000 = 49000
(1, 1, 1, 25000, 25000),
(1, 7, 2, 12000, 24000),
-- Order 2: Chicken Wings x1, Es Coklat x1 = 30000 + 17000 = 47000
(2, 3, 1, 30000, 30000),
(2, 10, 1, 17000, 17000),
-- Order 3: Beef Burger x2, Jus Alpukat x1 = 56000 + 20000 = 76000
(3, 4, 2, 28000, 56000),
(3, 11, 1, 20000, 20000),
-- Order 4: Mie Goreng x1, Teh Tarik x2 = 22000 + 30000 = 52000
(4, 2, 1, 22000, 22000),
(4, 9, 2, 15000, 30000),
-- Order 5: Cappuccino x2, Kentang Goreng x1, Lemon Tea x1 = 36000+15000+14000 = 65000? -> total 62000? perbaiki:
-- Order 5 total 62000: Cappuccino x1 (18000) + Kentang Goreng x2 (30000) + Lemon Tea x1 (14000) = 62000
(5, 8, 1, 18000, 18000),
(5, 5, 2, 15000, 30000),
(5, 12, 1, 14000, 14000);

INSERT INTO `transactions` (`order_id`, `amount_paid`, `change_amount`, `cashier_id`, `paid_at`) VALUES
(3, 100000, 24000, 2, '2026-11-17 09:55:00'),
(4, 60000,  8000,  2, '2026-11-16 19:20:00'),
(5, 100000, 38000, 2, '2026-11-16 14:35:00');
