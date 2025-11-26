-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 26, 2025 at 06:03 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fastfood`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(1, 'Gà rán'),
(2, 'Burger'),
(3, 'Cơm'),
(4, 'Món ăn nhẹ'),
(5, 'Thức uống'),
(6, 'Tráng miệng'),
(7, 'Đồ Ăn Vặt');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `shipping_fee` int(11) NOT NULL DEFAULT 0,
  `shipping_address` varchar(255) DEFAULT NULL,
  `distance_km` decimal(5,2) DEFAULT NULL,
  `prep_minutes` int(11) NOT NULL DEFAULT 20,
  `delivery_minutes` int(11) NOT NULL DEFAULT 20,
  `status` varchar(50) DEFAULT 'pending',
  `estimated_delivery_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `total`, `shipping_fee`, `shipping_address`, `distance_km`, `prep_minutes`, `delivery_minutes`, `status`, `estimated_delivery_time`, `created_at`) VALUES
(6, 3, 147000.00, 30000, '207 nguyễn xí bình thạnh', 12.60, 15, 25, 'delivered', '2025-11-22 02:59:25', '2025-11-21 19:19:25'),
(7, 3, 140000.00, 30000, '207 nguyễn xí', 11.70, 15, 20, 'delivered', '2025-11-22 22:51:28', '2025-11-22 15:16:28'),
(8, 3, 102000.00, 30000, '207 nguyễn xí', 12.60, 15, 25, 'delivered', '2025-11-24 18:45:44', '2025-11-24 11:05:44'),
(9, 3, 114000.00, 35000, '28 pasteur', 16.40, 15, 25, 'delivered', '2025-11-26 04:29:22', '2025-11-25 20:49:22'),
(10, 3, 54000.00, 35000, '28 pasteur', 17.60, 15, 25, 'delivered', '2025-11-26 05:47:14', '2025-11-25 22:07:14'),
(11, 3, 54000.00, 35000, '28 pasteur', 17.60, 15, 25, 'delivered', '2025-11-26 05:48:15', '2025-11-25 22:08:15');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `item_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`item_id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(13, 6, 9, 1, 72000.00),
(14, 6, 1, 1, 45000.00),
(15, 7, 18, 2, 55000.00),
(16, 8, 9, 1, 72000.00),
(17, 9, 20, 1, 79000.00),
(18, 10, 28, 1, 19000.00),
(19, 11, 28, 1, 19000.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `product_name` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `category_id`, `product_name`, `description`, `price`, `image`, `created_at`) VALUES
(1, 1, 'Gà rán giòn', '1 Miếng Gà rán truyền thống', 45000.00, 'ga_ran.jpg', '2025-11-17 19:14:48'),
(2, 1, 'Gà cay', '1 Miếng Gà rán vị cay', 49000.00, 'ga_cay.jpg', '2025-11-17 19:14:48'),
(3, 2, 'Burger bò', 'Burger bò phô mai', 55000.00, 'burger_bo.jpg', '2025-11-17 19:14:48'),
(4, 2, 'Burger gà', 'Burger gà giòn', 52000.00, 'burger_ga.jpg', '2025-11-17 19:14:48'),
(5, 3, 'Cơm gà xối mỡ', 'Cơm và gà chiên sốt mắm tỏi', 65000.00, 'com_ga.jpg', '2025-11-17 19:14:48'),
(6, 7, 'Khoai tây chiên', 'Khoai chiên giòn', 30000.00, 'khoai_tay.jpg', '2025-11-17 19:14:48'),
(7, 5, 'Coca Cola', 'Nước giải khát', 15000.00, 'coca.jpg', '2025-11-17 19:14:48'),
(8, 6, 'Kem vani', 'Kem lạnh', 9000.00, 'kem_vani.jpg', '2025-11-17 19:14:48'),
(9, 7, 'Nem Chua Rán', 'Vị chuẩn Hà Nội (30 Miếng / Phần)', 72000.00, 'nem_chua_ran.jpg', '2025-11-21 16:44:19'),
(10, 5, 'Sprite', 'Thức Uống', 15000.00, 'sprite.jpg', '2025-11-22 10:10:48'),
(12, 3, 'Cơm Gà + Canh', 'Cơm Gà và Canh và 1 Nước Ngọt', 79000.00, 'com_ga_canh.jpg', '2025-11-22 10:16:48'),
(13, 1, '2 Miếng Gà Cay kèm Khoai và Nước', '2 Miếng Gà Sốt Cay + Khoai + Nước', 119000.00, '2_ga_cay.jpg', '2025-11-22 10:17:30'),
(14, 4, 'Salad', 'Salad, Cà Chua, Sốt Thounsand Island', 49000.00, 'salad.jpg', '2025-11-22 10:20:29'),
(15, 4, 'Soup Bí Ngô', 'Soup Bí Ngô', 39000.00, 'soup_bi_ngo.jpg', '2025-11-22 10:21:32'),
(16, 6, 'Bánh Đào', 'Bánh Đào', 39000.00, 'banh_dao.jpg', '2025-11-22 10:24:02'),
(17, 6, 'Kem Socola', 'Kem Socola', 12000.00, 'kem_socola.jpg', '2025-11-22 10:24:51'),
(18, 2, 'Burger Cá', 'Burger Cá', 55000.00, 'burger_ca.jpg', '2025-11-22 10:27:06'),
(19, 2, 'Burger Bò Phô Mai Đặc Biệt', 'Burger Bò với 2 lớp Bò và Phô Mai', 99000.00, 'double_cheese.jpg', '2025-11-22 10:27:46'),
(20, 1, 'Gà Kem Hành', 'Gà Rán Truyền Thống đi kèm sốt kem hành', 79000.00, 'ga_kem_hanh.jpg', '2025-11-25 20:30:42'),
(21, 5, 'Mirinda', 'Thức uống có gas', 15000.00, 'mirinda.jpg', '2025-11-25 20:31:09'),
(28, 5, 'Trà Chanh', 'Trà Chanh Thanh Mát', 19000.00, 'tra_chanh.jpg', '2025-11-25 20:39:14');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `fullname`, `email`, `phone`, `password`, `role`, `address`, `created_at`) VALUES
(2, 'Admin FoodBond', 'admin@foodbond.local', '0000000000', '$2y$10$cwuhCySo79w4dyY4p1WVtOiaQ67zGsmmvLSfYiapU3HQhFdNNeE5y', 'admin', 'FoodBond HQ', '2025-11-18 17:34:12'),
(3, 'Phát', 'user1@gmail.com', '123456789', '$2y$10$WdC66opUCy6KSmDGvZM5reWlWeIzdiy2rfIPMpIbY7CqiuGH3Y/au', 'user', '28 Pasteur', '2025-11-18 17:46:43'),
(4, 'Dũng', '6h50@gmail.com', '09333333333', '$2y$10$8z2t4Gh4iPY4.9PSk7QDN.eEWrryal8hceRBSEUqXuXs48h8Dwug.', 'user', '506 Đường 3/2 Quận 10', '2025-11-22 15:17:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
