-- AIA Pharmacy Management System
-- Public portfolio database schema (structure only).
-- Sample/test records and personal supplier/account data have been removed.
-- Create a database named aia_pharmacy, then import this file.

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aia_pharmacy`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(180) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `customer_type` enum('Retail','Wholesale') NOT NULL DEFAULT 'Retail',
  `credit_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `credit_limit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_payments`
--

CREATE TABLE `customer_payments` (
  `id` bigint(20) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('Cash','Transfer','POS','Other') NOT NULL DEFAULT 'Cash',
  `reference` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discount_approvals`
--

CREATE TABLE `discount_approvals` (
  `id` bigint(20) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `discount_amount` decimal(12,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `title` varchar(180) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `sku` varchar(60) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `name` varchar(180) NOT NULL,
  `generic_name` varchar(180) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `batch_no` varchar(80) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `purchase_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `packs_per_carton` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `pieces_per_pack` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `wholesale_piece_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `wholesale_pack_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `wholesale_carton_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `retail_pack_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `retail_carton_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 10,
  `supplier_id` int(11) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

-- --------------------------------------------------------

--
-- Table structure for table `product_batches`
--

CREATE TABLE `product_batches` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_no` varchar(80) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `purchase_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `supplier_id` int(11) DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `pieces_per_pack` int(10) UNSIGNED DEFAULT NULL,
  `packs_per_carton` int(10) UNSIGNED DEFAULT NULL,
  `retail_piece_price` decimal(12,2) DEFAULT NULL,
  `retail_pack_price` decimal(12,2) DEFAULT NULL,
  `retail_carton_price` decimal(12,2) DEFAULT NULL,
  `wholesale_piece_price` decimal(12,2) DEFAULT NULL,
  `wholesale_pack_price` decimal(12,2) DEFAULT NULL,
  `wholesale_carton_price` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_batches`
--

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `invoice_no` varchar(80) DEFAULT NULL,
  `total` decimal(12,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchases`
--

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_type` enum('Piece','Pack','Carton') NOT NULL DEFAULT 'Piece',
  `unit_quantity` decimal(12,3) NOT NULL DEFAULT 0.000,
  `base_quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `purchase_price` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_items`
--

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `receipt_no` varchar(80) NOT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `sale_type` enum('Retail','Wholesale') NOT NULL DEFAULT 'Retail',
  `subtotal` decimal(12,2) NOT NULL,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL,
  `amount_paid` decimal(12,2) NOT NULL,
  `change_amount` decimal(12,2) NOT NULL,
  `amount_due` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Paid','Partial','Unpaid') NOT NULL DEFAULT 'Paid',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_type` enum('Piece','Pack','Carton') NOT NULL DEFAULT 'Piece',
  `unit_quantity` decimal(12,3) NOT NULL DEFAULT 0.000,
  `base_quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `unit_price` decimal(12,2) NOT NULL,
  `total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `key` varchar(80) NOT NULL,
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `type` enum('IN','OUT','ADJUSTMENT') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

Purchased: 2; Base pieces: 400; Batch: TEST-002; Expiry: 2027-12-31', 1, '2026-10-19 15:59:11'),
(38, 6, 12, 'OUT', 1, 'AIA-20261020163738-736', 'Batch TEST-001 | FEFO sale | Piece x 1', 1, '2026-10-20 15:37:39'),
(39, 6, 12, 'OUT', 10, 'AIA-20261020181455-795', 'Batch TEST-001 | FEFO sale | Pack x 1', 1, '2026-10-20 17:14:55'),
(40, 6, 12, 'OUT', 189, 'AIA-20261020181705-340', 'Batch TEST-001 | FEFO sale | Carton x 0.945', 1, '2026-10-20 17:17:05'),
(41, 6, 13, 'OUT', 11, 'AIA-20261020181705-340', 'Batch TEST-002 | FEFO sale | Carton x 0.055', 1, '2026-10-20 17:17:05'),
(42, 6, 13, 'OUT', 200, 'AIA-20261022120855-501', 'Batch TEST-002 | FEFO sale | Carton x 1', 1, '2026-10-22 11:08:55'),
(43, 6, 13, 'OUT', 1, 'AIA-20261022145413-398', 'Batch TEST-002 | FEFO sale | Piece x 1', 1, '2026-10-22 13:54:14'),
(44, 6, 14, 'IN', 20, 'Purchase #8', 'Unit: PIECE; Purchased: 20; Base pieces: 20; Batch: TEST-003; Expiry: 2027-12-31', 1, '2026-10-22 14:05:17'),
(45, 6, 13, 'OUT', 188, 'AIA-20261022150816-232', 'Batch TEST-002 | FEFO sale | Carton x 0.94', 1, '2026-10-22 14:08:16'),
(46, 6, 14, 'OUT', 12, 'AIA-20261022150816-232', 'Batch TEST-003 | FEFO sale | Carton x 0.06', 1, '2026-10-22 14:08:16'),
(47, 6, 14, 'OUT', 1, 'AIA-20261022163619-397', 'Batch TEST-003 | FEFO sale | Piece x 1', 1, '2026-10-22 15:36:19'),
(48, 6, 15, 'IN', 1, 'Purchase #9', 'Unit: PIECE; Purchased: 1; Base pieces: 1; Batch: TEST-BATCH-010; Expiry: 2027-12-31', 1, '2026-10-25 16:01:46'),
(49, 6, 14, 'OUT', 1, 'AIA-20261027122316-985', 'Batch TEST-003 | FEFO sale | Piece x 1', 1, '2026-10-27 11:23:16'),
(50, 6, 16, 'IN', 1, 'Purchase #10', 'Unit: PIECE; Purchased: 1; Base pieces: 1; Batch: TEST-BATCH-011; Expiry: 2026-12-30', 1, '2026-10-27 11:29:36'),
(51, 6, 16, 'OUT', 1, 'AIA-20261027123500-394', 'Batch TEST-BATCH-011 | FEFO sale | Piece x 1', 1, '2026-10-27 11:35:00'),
(52, 6, 14, 'OUT', 1, 'AIA-20261027123932-463', 'Batch TEST-003 | FEFO sale | Piece x 1', 1, '2026-10-27 11:39:32'),
(53, 6, 14, 'OUT', 1, 'AIA-20261028144645-580', 'Batch TEST-003 | FEFO sale | Piece x 1', 1, '2026-10-28 13:46:45'),
(54, 6, 17, 'IN', 1, 'Purchase #11', 'Unit: PIECE; Purchased: 1; Base pieces: 1; Batch: TEST-BATCH-012; Expiry: 2027-12-31', 1, '2026-10-28 14:00:38'),
(55, 6, 18, 'IN', 10, 'Purchase #12', 'Unit: PACK; Purchased: 1; Base pieces: 10; Batch: TEST-BATCH-013; Expiry: 2027-12-30', 1, '2026-10-28 14:38:52'),
(56, 6, 18, 'OUT', 1, 'AIA-20261029162623-252', 'Batch TEST-BATCH-013 | Piece x 1 | base pieces 1', 1, '2026-10-29 15:26:23'),
(57, 6, 19, 'IN', 10, 'Purchase #13', 'Unit: PIECE; Purchased: 10; Base pieces: 10; Batch: TEST-BATCH-015; Expiry: 2027-12-30', 1, '2026-10-30 14:06:15'),
(58, 6, 19, 'OUT', 10, 'AIA-20261030150959-123', 'Batch TEST-BATCH-015 | Pack x 1 | base pieces 10', 1, '2026-10-30 14:09:59'),
(59, 6, 20, 'IN', 120, 'Purchase #14', 'Unit: CARTON; Purchased: 1; Base pieces: 120; Batch: TEST-CARTON-12; Expiry: 2027-12-30', 1, '2026-10-30 14:15:27'),
(60, 6, 20, 'OUT', 120, 'AIA-20261030151922-744', 'Batch TEST-CARTON-12 | Carton x 1 | base pieces 120', 1, '2026-10-30 14:19:22'),
(61, 6, 21, 'IN', 200, 'Purchase #15', 'Unit: CARTON; Purchased: 1; Base pieces: 200; Batch: TEST-CARTON-20; Expiry: 2027-12-30', 1, '2026-10-30 14:26:03'),
(62, 6, 21, 'OUT', 20, 'AIA-20261030153419-789', 'Batch TEST-CARTON-20 | Pack x 1 | base pieces 20', 1, '2026-10-30 14:34:19'),
(63, 6, 22, 'IN', 120, 'Purchase #16', 'Unit: CARTON; Purchased: 1; Base pieces: 120; Batch: TEST-CARTON-12B; Expiry: 2027-12-30', 1, '2026-10-30 14:44:19'),
(64, 6, 22, 'OUT', 12, 'AIA-20261030154741-731', 'Batch TEST-CARTON-12B | Pack x 1 | base pieces 12', 1, '2026-10-30 14:47:41'),
(65, 6, 22, 'OUT', 1, 'AIA-20261030155044-561', 'Batch TEST-CARTON-12B | Piece x 1 | base pieces 1', 1, '2026-10-30 14:50:44');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','pharmacist','cashier','storekeeper') NOT NULL DEFAULT 'cashier',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_name` (`name`),
  ADD KEY `idx_customer_phone` (`phone`),
  ADD KEY `fk_customers_created_by` (`created_by`);

--
-- Indexes for table `customer_payments`
--
ALTER TABLE `customer_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_customer` (`customer_id`),
  ADD KEY `idx_payment_sale` (`sale_id`),
  ADD KEY `fk_payment_user` (`received_by`);

--
-- Indexes for table `discount_approvals`
--
ALTER TABLE `discount_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_discount_sale` (`sale_id`),
  ADD KEY `fk_discount_requester` (`requested_by`),
  ADD KEY `fk_discount_approver` (`approved_by`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_product_batch` (`product_id`),
  ADD KEY `idx_expiry` (`expiry_date`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_purchase_items_batch` (`batch_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_no` (`receipt_no`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_sales_customer` (`customer_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_sale_items_batch` (`batch_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_stock_movements_batch` (`batch_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_payments`
--
ALTER TABLE `customer_payments`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `discount_approvals`
--
ALTER TABLE `discount_approvals`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `product_batches`
--
ALTER TABLE `product_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_payments`
--
ALTER TABLE `customer_payments`
  ADD CONSTRAINT `fk_payment_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_payment_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_payment_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `discount_approvals`
--
ALTER TABLE `discount_approvals`
  ADD CONSTRAINT `fk_discount_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_discount_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_discount_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD CONSTRAINT `product_batches_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_batches_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_batches_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `fk_purchase_items_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_sale_items_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stock_movements_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
