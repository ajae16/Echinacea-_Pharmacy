-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 12, 2026 at 07:48 AM
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
-- Database: `pharmacy_mis`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `ArchiveAccount` (IN `p_id` VARCHAR(50), IN `p_archivedBy` VARCHAR(50), OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Archive failed';
    END;

    START TRANSACTION;

    INSERT INTO archive_accounts SELECT * FROM accounts WHERE accountId = p_id;
    UPDATE accounts SET isArchive = 1 WHERE accountId = p_id;

    SET p_success = TRUE;
    SET p_message = 'Account archived successfully';
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `ArchiveInventory` (IN `p_id` VARCHAR(50), IN `p_archivedBy` VARCHAR(50), OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Archive failed';
    END;

    START TRANSACTION;

    INSERT INTO archive_inventory SELECT * FROM inventory WHERE productId = p_id;
    UPDATE inventory SET isArchive = 1 WHERE productId = p_id;

    SET p_success = TRUE;
    SET p_message = 'Inventory item archived successfully';
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `ArchiveSupplier` (IN `p_id` VARCHAR(50), IN `p_archivedBy` VARCHAR(50), OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Archive failed';
    END;

    START TRANSACTION;

    INSERT INTO archive_suppliers SELECT * FROM suppliers WHERE supplierId = p_id;
    UPDATE suppliers SET isArchive = 1 WHERE supplierId = p_id;

    SET p_success = TRUE;
    SET p_message = 'Supplier archived successfully';
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `ArchiveSupplyDelivery` (IN `p_id` VARCHAR(50), IN `p_archivedBy` VARCHAR(50), OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Archive failed';
    END;

    START TRANSACTION;

    INSERT INTO archive_deliveries SELECT * FROM supply_deliveries WHERE supplyId = p_id;
    UPDATE supply_deliveries SET isArchive = 1 WHERE supplyId = p_id;

    SET p_success = TRUE;
    SET p_message = 'Supply delivery archived successfully';
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `CreateSaleTransaction` (IN `p_productId` VARCHAR(50), IN `p_accountId` VARCHAR(50), IN `p_quantity` INT, IN `p_paymentMethod` VARCHAR(20), OUT `p_transactionId` VARCHAR(50), OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_stock INT;
    DECLARE v_price DECIMAL(10,2);
    DECLARE v_batchNo VARCHAR(50);
    DECLARE v_counter INT;
    DECLARE v_date VARCHAR(8);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Transaction failed';
    END;

    START TRANSACTION;

    -- Check product stock
    SELECT stock, price, batchNo INTO v_stock, v_price, v_batchNo
    FROM inventory
    WHERE productId = p_productId AND isActive = TRUE
    FOR UPDATE;

    IF v_stock IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'Product not found';
        ROLLBACK;
    ELSEIF v_stock < p_quantity THEN
        SET p_success = FALSE;
        SET p_message = 'Insufficient stock';
        ROLLBACK;
    ELSE
        -- Generate transaction ID
        CALL GetNextCounter('Trans', v_counter);
        SET v_date = DATE_FORMAT(CURDATE(), '%Y%m%d');
        SET p_transactionId = CONCAT('Trans', v_date, LPAD(v_counter, 4, '0'));

        -- Insert sale
        INSERT INTO sales (salesId, date, productId, accountId, quantitySold, totalAmount, batchNo, paymentMethod)
        VALUES (p_transactionId, CURDATE(), p_productId, p_accountId, p_quantity, v_price * p_quantity, v_batchNo, p_paymentMethod);

        -- Update inventory
        UPDATE inventory
        SET stock = stock - p_quantity
        WHERE productId = p_productId;

        SET p_success = TRUE;
        SET p_message = 'Sale completed successfully';
        COMMIT;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetNextCounter` (IN `p_counterType` VARCHAR(50), OUT `p_nextValue` INT)   BEGIN
    DECLARE v_date DATE;
    SET v_date = CURDATE();

    -- Insert or update counter
    INSERT INTO counters (counterType, counterDate, currentValue)
    VALUES (p_counterType, v_date, 1)
    ON DUPLICATE KEY UPDATE currentValue = currentValue + 1;

    -- Get the current value
    SELECT currentValue INTO p_nextValue
    FROM counters
    WHERE counterType = p_counterType AND counterDate = v_date;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RestoreAccount` (IN `p_id` VARCHAR(50), OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Restore failed';
    END;

    START TRANSACTION;

    UPDATE accounts SET isArchive = 0 WHERE accountId = p_id;
    DELETE FROM archive_accounts WHERE accountId = p_id;

    SET p_success = TRUE;
    SET p_message = 'Account restored successfully';
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RestoreInventory` (IN `p_id` VARCHAR(50), OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Restore failed';
    END;

    START TRANSACTION;

    UPDATE inventory SET isArchive = 0 WHERE productId = p_id;
    DELETE FROM archive_inventory WHERE productId = p_id;

    SET p_success = TRUE;
    SET p_message = 'Inventory item restored successfully';
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RestoreSupplier` (IN `p_id` VARCHAR(50), OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Restore failed';
    END;

    START TRANSACTION;

    UPDATE suppliers SET isArchive = 0 WHERE supplierId = p_id;
    DELETE FROM archive_suppliers WHERE supplierId = p_id;

    SET p_success = TRUE;
    SET p_message = 'Supplier restored successfully';
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RestoreSupplyDelivery` (IN `p_id` VARCHAR(50), OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Restore failed';
    END;

    START TRANSACTION;

    UPDATE supply_deliveries SET isArchive = 0 WHERE supplyId = p_id;
    DELETE FROM archive_deliveries WHERE supplyId = p_id;

    SET p_success = TRUE;
    SET p_message = 'Supply delivery restored successfully';
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateInventoryOnDelivery` (IN `p_productId` VARCHAR(50), IN `p_quantity` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_current_stock INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Inventory update failed';
    END;

    START TRANSACTION;

    -- Get current stock
    SELECT stock INTO v_current_stock
    FROM inventory
    WHERE productId = p_productId AND isActive = TRUE
    FOR UPDATE;

    IF v_current_stock IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'Product not found in inventory';
        ROLLBACK;
    ELSE
        -- Update inventory stock
        UPDATE inventory
        SET stock = stock + p_quantity,
            updatedAt = NOW()
        WHERE productId = p_productId;

        SET p_success = TRUE;
        SET p_message = CONCAT('Inventory updated: stock increased by ', p_quantity);
        COMMIT;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `accountId` varchar(50) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `middleName` varchar(100) DEFAULT NULL,
  `lastName` varchar(100) NOT NULL,
  `fullName` varchar(255) NOT NULL,
  `phoneNumber` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `image` varchar(255) DEFAULT 'Pharmacy Icons/profile icon.png',
  `birthday` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `sex` enum('male','female','other') DEFAULT NULL,
  `isActive` tinyint(1) DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `isArchive` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`accountId`, `firstName`, `middleName`, `lastName`, `fullName`, `phoneNumber`, `email`, `status`, `username`, `password`, `image`, `birthday`, `address`, `sex`, `isActive`, `createdAt`, `updatedAt`, `isArchive`) VALUES
('Acc202500000000', 'Super', '', 'Admin', 'Super Admin', '', 'ajaeantonio16@gmail.com', 'active', 'Admin', '$2y$10$LsVAAGyBB1yJH6AR8ZWJkO6AYc.H.8uY7k0raV3OPSc1KScUieTbW', 'Pharmacy Icons/profile icon.png', NULL, NULL, NULL, 1, '2026-01-02 04:52:47', '2026-01-10 22:54:58', 0),
('Acc202601110001', 'Nieumy', NULL, 'Redondo', 'Nieumy Redondo', 'N/A', 'nieum@gmail.com', 'active', 'Nieum', '$2y$10$vGfkpmCTtLUQOQZV58f7S.59ZVgVYr4XsequmihhucmDC/9T7iuSa', 'Pharmacy Icons/profile icon.png', NULL, NULL, NULL, 1, '2026-01-11 12:16:28', '2026-01-11 12:16:28', 0),
('Acc202601110002', 'Nicko', NULL, 'Lumbera', 'Nicko Lumbera', 'N/A', 'nicko.lumbera@cvsu.edu.ph', 'active', 'Raz', '$2y$10$/rgqjwEHocdSGuzTVMHQOufyD02wwPkATT4visOwBh6vJW5.e.W7G', 'Pharmacy Icons/profile icon.png', NULL, NULL, NULL, 1, '2026-01-11 14:22:11', '2026-01-11 14:22:11', 0),
('Acc202601110003', 'Erica Vera', '', 'Cabidog', 'Erica Vera Cabidog', '09987654321', 'ericacabidog@gmail.com', 'active', 'ericacabidog12', '$2y$10$de.OeZZPzha6R7KxjGugFO6s8lkODTZfD4F076XnQZMZHyelhRYoO', 'Pharmacy Icons/profile icon.png', '1998-03-03', '', NULL, 1, '2026-01-11 14:29:35', '2026-01-11 15:33:34', 0),
('Acc202601110004', 'Neal', NULL, 'Buendia', 'Neal Buendia', 'N/A', 'neal.buendia@cvsu.edu.ph', 'active', 'arxes', '$2y$10$Szk47vU87lja6.VOcua6SeHKn1vQoNYix8xpGzCsRodEWFYAYCpvS', 'Pharmacy Icons/profile icon.png', NULL, NULL, NULL, 1, '2026-01-11 14:49:12', '2026-01-11 14:49:12', 0),
('Acc202601110005', 'Adrian Jae', NULL, 'Antonio', 'Adrian Jae Antonio', 'N/A', 'ajaemontage@gmail.com', 'active', 'Ajae', '$2y$10$6rp4sDuUeJ/dXKxNCvsHpu0uYZj0nJDemQ2OdjWgvT8DcIpqE7Rgq', 'Pharmacy Icons/profile icon.png', NULL, NULL, NULL, 1, '2026-01-11 14:56:17', '2026-01-11 14:56:17', 0),
('Acc202601120006', 'joshua', NULL, 'jose', 'joshua jose', 'N/A', 'joshua.jose@cvsu.edu.ph', 'active', 'Josh', '$2y$10$.lYCOzXp8FcRl0sTEQtjh.AyOxcGgzOswdT1OsvZ9QWOQeYTIvwUK', 'Pharmacy Icons/profile icon.png', NULL, NULL, NULL, 1, '2026-01-11 19:27:53', '2026-01-11 19:27:53', 0);

--
-- Triggers `accounts`
--
DELIMITER $$
CREATE TRIGGER `trg_accounts_audit_insert` AFTER INSERT ON `accounts` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NEW.accountId, NEW.username, 'CREATE', 'ACCOUNTS',
          CONCAT('Created account: ', NEW.fullName), 'SYSTEM', NEW.accountId);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_accounts_audit_update` AFTER UPDATE ON `accounts` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NEW.accountId, NEW.username, 'UPDATE', 'ACCOUNTS',
          CONCAT('Updated account: ', NEW.fullName), 'SYSTEM', NEW.accountId);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `activityid` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `accountId` varchar(50) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `action` enum('CREATE','READ','UPDATE','DELETE','LOGIN','LOGOUT','ARCHIVE','RESTORE') DEFAULT NULL,
  `module` enum('INVENTORY','SALES','SUPPLIER','ACCOUNTS','PROFILE','AUTH','ARCHIVE','SYSTEM') DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ipAddress` varchar(45) DEFAULT NULL,
  `recordId` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`activityid`, `timestamp`, `accountId`, `username`, `action`, `module`, `details`, `ipAddress`, `recordId`) VALUES
(273, '2026-01-11 14:30:09', 'Acc202500000000', 'Admin', 'LOGIN', 'AUTH', 'Acc202500000000', '::1', NULL),
(274, '2026-01-11 14:30:20', 'SUP20260111223072', NULL, 'CREATE', '', 'Created supplier: JOse', 'SYSTEM', 'SUP20260111223072'),
(275, '2026-01-11 14:30:20', 'Acc202500000000', 'Admin', 'CREATE', '', 'SUP20260111223072', '::1', NULL),
(276, '2026-01-11 14:30:29', 'Prod202601110001', 'SYSTEM', 'CREATE', 'INVENTORY', 'Created product: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601110001'),
(277, '2026-01-11 14:30:29', 'Acc202500000000', 'Admin', 'CREATE', 'INVENTORY', 'Prod202601110001', '::1', NULL),
(278, '2026-01-11 14:30:30', 'DELI20260111223030', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601110001 from SUP20260111223072 Qty: 200 Status: Pending', 'SYSTEM', 'DELI20260111223030'),
(279, '2026-01-11 14:30:30', 'system', 'Admin', 'CREATE', '', 'DELI20260111223030', '::1', NULL),
(280, '2026-01-11 14:30:43', 'DELI20260111223030', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601110001 Status: Pending → Received', 'SYSTEM', 'DELI20260111223030'),
(281, '2026-01-11 14:30:43', 'system', 'Admin', 'UPDATE', '', 'DELI20260111223030', '::1', NULL),
(282, '2026-01-11 15:44:48', 'DELI20260111234489', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601110001 from SUP20260111223072 Qty: 100 Status: Pending', 'SYSTEM', 'DELI20260111234489'),
(283, '2026-01-11 15:44:48', 'system', 'Admin', 'CREATE', '', 'DELI20260111234489', '::1', NULL),
(284, '2026-01-11 15:52:08', 'DELI20260111234489', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601110001 Status: Pending → Received', 'SYSTEM', 'DELI20260111234489'),
(285, '2026-01-11 15:53:36', 'DELI20260111235311', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601110001 from SUP20260111223072 Qty: 1000 Status: Pending', 'SYSTEM', 'DELI20260111235311'),
(286, '2026-01-11 15:53:36', 'system', 'Admin', 'CREATE', '', 'DELI20260111235311', '::1', NULL),
(287, '2026-01-11 15:53:39', 'DELI20260111235311', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601110001 Status: Pending → Received', 'SYSTEM', 'DELI20260111235311'),
(288, '2026-01-11 15:58:48', 'DELI20260111235859', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601110001 from SUP20260111223072 Qty: 100 Status: Pending', 'SYSTEM', 'DELI20260111235859'),
(289, '2026-01-11 15:58:48', 'system', 'Admin', 'CREATE', '', 'DELI20260111235859', '::1', NULL),
(290, '2026-01-11 15:58:54', 'DELI20260111235859', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601110001 Status: Pending → Received', 'SYSTEM', 'DELI20260111235859'),
(291, '2026-01-11 15:58:55', 'Prod202601110001', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 0)', 'SYSTEM', 'Prod202601110001'),
(292, '2026-01-11 15:58:55', 'Prod202601110001', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 100)', 'SYSTEM', 'Prod202601110001'),
(293, '2026-01-11 15:59:29', 'DELI20260111235932', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601110001 from SUP20260111223072 Qty: 20 Status: Received', 'SYSTEM', 'DELI20260111235932'),
(294, '2026-01-11 15:59:29', 'Prod202601110001', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 100 → 120)', 'SYSTEM', 'Prod202601110001'),
(295, '2026-01-11 15:59:29', 'system', 'Admin', 'CREATE', '', 'DELI20260111235932', '::1', NULL),
(296, '2026-01-11 15:59:54', 'DELI20260111235988', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601110001 from SUP20260111223072 Qty: 30 Status: Pending', 'SYSTEM', 'DELI20260111235988'),
(297, '2026-01-11 15:59:54', 'system', 'Admin', 'CREATE', '', 'DELI20260111235988', '::1', NULL),
(298, '2026-01-11 16:00:04', 'DELI20260111235988', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601110001 Status: Pending → Cancelled', 'SYSTEM', 'DELI20260111235988'),
(299, '2026-01-11 16:00:37', 'DELI20260112000060', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601110001 from SUP20260111223072 Qty: 30 Status: Pending', 'SYSTEM', 'DELI20260112000060'),
(300, '2026-01-11 16:00:37', 'system', 'Admin', 'CREATE', '', 'DELI20260112000060', '::1', NULL),
(301, '2026-01-11 16:01:31', 'DELI20260112000117', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601110001 from SUP20260111223072 Qty: 1 Status: Pending', 'SYSTEM', 'DELI20260112000117'),
(302, '2026-01-11 16:01:31', 'system', 'Admin', 'CREATE', '', 'DELI20260112000117', '::1', NULL),
(303, '2026-01-11 16:02:13', 'DELI20260112000274', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601110001 from SUP20260111223072 Qty: 20 Status: Pending', 'SYSTEM', 'DELI20260112000274'),
(304, '2026-01-11 16:02:14', 'system', 'Admin', 'CREATE', '', 'DELI20260112000274', '::1', NULL),
(305, '2026-01-11 18:16:11', 'Acc202500000000', 'Admin', 'LOGOUT', 'AUTH', 'Acc202500000000', '::1', NULL),
(306, '2026-01-11 18:16:24', 'Acc202500000000', 'Admin', 'LOGIN', 'AUTH', 'Acc202500000000', '::1', NULL),
(307, '2026-01-11 18:44:35', 'DELI20260112024446', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601110001 from SUP20260111223072 Qty: 1000 Status: Pending', 'SYSTEM', 'DELI20260112024446'),
(308, '2026-01-11 18:44:35', 'system', 'Admin', 'CREATE', '', 'DELI20260112024446', '::1', NULL),
(309, '2026-01-11 18:45:52', 'DELI20260112024516', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601110001 from SUP20260111223072 Qty: 30 Status: Pending', 'SYSTEM', 'DELI20260112024516'),
(310, '2026-01-11 18:45:52', 'system', 'Admin', 'CREATE', '', 'DELI20260112024516', '::1', NULL),
(311, '2026-01-11 18:46:08', 'DELI20260112024516', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601110001 Status: Pending → Received', 'SYSTEM', 'DELI20260112024516'),
(312, '2026-01-11 18:46:08', 'Prod202601110001', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 120 → 120)', 'SYSTEM', 'Prod202601110001'),
(313, '2026-01-11 18:46:08', 'Prod202601110001', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 120 → 150)', 'SYSTEM', 'Prod202601110001'),
(314, '2026-01-11 18:46:52', 'DELI20260112024516', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601110001 Status: Received → Received', 'SYSTEM', 'DELI20260112024516'),
(315, '2026-01-11 18:48:48', 'DELI20260112024862', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601110001 from SUP20260111223072 Qty: 50 Status: Pending', 'SYSTEM', 'DELI20260112024862'),
(316, '2026-01-11 18:48:48', 'system', 'Admin', 'CREATE', '', 'DELI20260112024862', '::1', NULL),
(317, '2026-01-11 18:48:58', 'DELI20260112024862', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601110001 Status: Pending → Received', 'SYSTEM', 'DELI20260112024862'),
(318, '2026-01-11 18:48:58', 'Prod202601110001', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 150 → 150)', 'SYSTEM', 'Prod202601110001'),
(319, '2026-01-11 18:48:58', 'Prod202601110001', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 150 → 200)', 'SYSTEM', 'Prod202601110001'),
(320, '2026-01-11 19:12:05', 'Prod202601120001', 'SYSTEM', 'CREATE', 'INVENTORY', 'Created product: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120001'),
(321, '2026-01-11 19:12:05', 'Acc202500000000', 'Admin', 'CREATE', 'INVENTORY', 'Prod202601120001', '::1', NULL),
(322, '2026-01-11 19:14:29', 'Prod202601120001', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 50)', 'SYSTEM', 'Prod202601120001'),
(323, '2026-01-11 19:14:29', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120001', '::1', NULL),
(324, '2026-01-11 19:19:37', 'Prod202601120001', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 50 → 20)', 'SYSTEM', 'Prod202601120001'),
(325, '2026-01-11 19:19:37', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120001', '::1', NULL),
(326, '2026-01-11 19:19:59', 'Prod202601120001', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 20 → 19)', 'SYSTEM', 'Prod202601120001'),
(327, '2026-01-11 19:19:59', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120001', '::1', NULL),
(328, '2026-01-11 19:22:56', 'Prod202601120001', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 19 → 19)', 'SYSTEM', 'Prod202601120001'),
(329, '2026-01-11 19:22:56', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120001', '::1', NULL),
(330, '2026-01-11 19:23:09', 'Prod202601120001', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 19 → 19)', 'SYSTEM', 'Prod202601120001'),
(331, '2026-01-11 19:23:09', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120001', '::1', NULL),
(332, '2026-01-11 19:23:15', 'Prod202601120001', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 19 → 19)', 'SYSTEM', 'Prod202601120001'),
(333, '2026-01-11 19:23:15', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120001', '::1', NULL),
(334, '2026-01-11 19:23:25', 'Prod202601120001', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 19 → 19)', 'SYSTEM', 'Prod202601120001'),
(335, '2026-01-11 19:23:26', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120001', '::1', NULL),
(336, '2026-01-11 19:48:02', 'Prod202601120002', 'SYSTEM', 'CREATE', 'INVENTORY', 'Created product: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120002'),
(337, '2026-01-11 19:48:02', 'Acc202500000000', 'Admin', 'CREATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(338, '2026-01-11 19:48:07', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 19 → 19)', 'SYSTEM', 'Prod202601120002'),
(339, '2026-01-11 19:48:07', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(340, '2026-01-11 19:48:13', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 19 → 19)', 'SYSTEM', 'Prod202601120002'),
(341, '2026-01-11 19:48:13', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(342, '2026-01-11 19:48:16', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 19 → 19)', 'SYSTEM', 'Prod202601120002'),
(343, '2026-01-11 19:48:16', 'Prod202601120002', NULL, 'ARCHIVE', 'INVENTORY', 'Archived inventory: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120002'),
(344, '2026-01-11 19:48:16', 'Acc202500000000', 'Admin', 'ARCHIVE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(345, '2026-01-11 19:48:21', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 19 → 19)', 'SYSTEM', 'Prod202601120002'),
(346, '2026-01-11 19:48:21', 'Prod202601120002', NULL, 'DELETE', 'INVENTORY', 'Permanently deleted archived inventory: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120002'),
(347, '2026-01-11 19:48:21', 'system', 'Admin', 'RESTORE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(348, '2026-01-11 19:52:08', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 19 → 19)', 'SYSTEM', 'Prod202601120002'),
(349, '2026-01-11 19:52:08', 'Prod202601120002', NULL, 'ARCHIVE', 'INVENTORY', 'Archived inventory: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120002'),
(350, '2026-01-11 19:52:08', 'Acc202500000000', 'Admin', 'ARCHIVE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(351, '2026-01-11 19:52:15', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 19 → 19)', 'SYSTEM', 'Prod202601120002'),
(352, '2026-01-11 19:52:15', 'Prod202601120002', NULL, 'DELETE', 'INVENTORY', 'Permanently deleted archived inventory: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120002'),
(353, '2026-01-11 19:52:15', 'system', 'Admin', 'RESTORE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(354, '2026-01-11 19:52:32', 'Prod202601120003', 'SYSTEM', 'CREATE', 'INVENTORY', 'Created product: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120003'),
(355, '2026-01-11 19:52:32', 'Acc202500000000', 'Admin', 'CREATE', 'INVENTORY', 'Prod202601120003', '::1', NULL),
(356, '2026-01-11 19:52:34', 'DELI20260112035255', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601120003 from SUP20260111223072 Qty: 200 Status: Pending', 'SYSTEM', 'DELI20260112035255'),
(357, '2026-01-11 19:52:34', 'system', 'Admin', 'CREATE', '', 'DELI20260112035255', '::1', NULL),
(358, '2026-01-11 19:57:23', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 19 → 9)', 'SYSTEM', 'Prod202601120002'),
(359, '2026-01-11 19:57:23', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(360, '2026-01-11 19:57:29', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 9 → 0)', 'SYSTEM', 'Prod202601120002'),
(361, '2026-01-11 19:57:29', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(362, '2026-01-11 19:57:38', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 0)', 'SYSTEM', 'Prod202601120002'),
(363, '2026-01-11 19:57:38', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(364, '2026-01-11 19:58:01', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 0)', 'SYSTEM', 'Prod202601120002'),
(365, '2026-01-11 19:58:01', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(366, '2026-01-11 19:58:40', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 0)', 'SYSTEM', 'Prod202601120002'),
(367, '2026-01-11 19:58:40', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(368, '2026-01-11 20:02:34', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 0)', 'SYSTEM', 'Prod202601120002'),
(369, '2026-01-11 20:02:34', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(370, '2026-01-11 20:09:03', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 0)', 'SYSTEM', 'Prod202601120002'),
(371, '2026-01-11 20:09:03', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(372, '2026-01-11 20:10:03', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 0)', 'SYSTEM', 'Prod202601120002'),
(373, '2026-01-11 20:19:26', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 1)', 'SYSTEM', 'Prod202601120002'),
(374, '2026-01-11 20:19:26', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(375, '2026-01-11 20:20:20', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 1 → 0)', 'SYSTEM', 'Prod202601120002'),
(376, '2026-01-11 20:20:20', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(377, '2026-01-11 20:24:28', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 1)', 'SYSTEM', 'Prod202601120002'),
(378, '2026-01-11 20:24:28', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(379, '2026-01-11 20:24:34', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 1 → 100)', 'SYSTEM', 'Prod202601120002'),
(380, '2026-01-11 20:24:34', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(381, '2026-01-11 20:26:29', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 100 → 1)', 'SYSTEM', 'Prod202601120002'),
(382, '2026-01-11 20:26:29', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(383, '2026-01-11 20:26:39', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 1 → 1)', 'SYSTEM', 'Prod202601120002'),
(384, '2026-01-11 20:26:39', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(385, '2026-01-11 20:26:54', 'Prod202601120002', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 1 → 0)', 'SYSTEM', 'Prod202601120002'),
(386, '2026-01-11 20:26:54', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120002', '::1', NULL),
(387, '2026-01-11 20:28:00', 'Prod202601120004', 'SYSTEM', 'CREATE', 'INVENTORY', 'Created product: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120004'),
(388, '2026-01-11 20:28:01', 'Acc202500000000', 'Admin', 'CREATE', 'INVENTORY', 'Prod202601120004', '::1', NULL),
(389, '2026-01-11 20:28:15', 'Prod202601120004', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 0)', 'SYSTEM', 'Prod202601120004'),
(390, '2026-01-11 20:28:15', 'Prod202601120004', NULL, 'ARCHIVE', 'INVENTORY', 'Archived inventory: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120004'),
(391, '2026-01-11 20:28:15', 'Acc202500000000', 'Admin', 'ARCHIVE', 'INVENTORY', 'Prod202601120004', '::1', NULL),
(392, '2026-01-11 20:33:22', 'Prod202601120005', 'SYSTEM', 'CREATE', 'INVENTORY', 'Created product: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120005'),
(393, '2026-01-11 20:33:22', 'Acc202500000000', 'Admin', 'CREATE', 'INVENTORY', 'Prod202601120005', '::1', NULL),
(394, '2026-01-11 20:40:07', 'Prod202601120005', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 1 → 11)', 'SYSTEM', 'Prod202601120005'),
(395, '2026-01-11 20:40:07', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120005', '::1', NULL),
(396, '2026-01-11 21:08:40', 'DELI20260112050809', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601120005 from SUP20260111223072 Qty: 550 Status: Pending', 'SYSTEM', 'DELI20260112050809'),
(397, '2026-01-11 21:08:40', 'system', 'Admin', 'CREATE', '', 'DELI20260112050809', '::1', NULL),
(398, '2026-01-11 21:08:51', 'DELI20260112050809', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601120005 Status: Pending → Received', 'SYSTEM', 'DELI20260112050809'),
(399, '2026-01-11 21:08:51', 'Prod202601120005', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 11 → 11)', 'SYSTEM', 'Prod202601120005'),
(400, '2026-01-11 21:08:51', 'Prod202601120005', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 11 → 561)', 'SYSTEM', 'Prod202601120005'),
(401, '2026-01-11 21:16:52', 'SUP20260111223072', NULL, 'UPDATE', '', 'Updated supplier: JOse', 'SYSTEM', 'SUP20260111223072'),
(402, '2026-01-11 21:16:52', 'SUP20260111223072', NULL, 'ARCHIVE', '', 'Archived supplier: JOse', 'SYSTEM', 'SUP20260111223072'),
(403, '2026-01-11 21:16:52', 'Acc202500000000', 'Admin', 'ARCHIVE', '', 'SUP20260111223072', '::1', NULL),
(404, '2026-01-11 21:16:58', 'DELI20260112050809', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601120005 Status: Received → Received', 'SYSTEM', 'DELI20260112050809'),
(405, '2026-01-11 21:16:58', 'DELI20260112050809', NULL, 'ARCHIVE', '', 'Archived delivery: DELI20260112050809', 'SYSTEM', 'DELI20260112050809'),
(406, '2026-01-11 21:16:58', 'system', 'Admin', 'ARCHIVE', '', 'DELI20260112050809', '::1', NULL),
(407, '2026-01-11 21:17:44', 'Acc202601120001', 'Nieum', 'CREATE', 'ACCOUNTS', 'Created account: Nieumy Kaizer Redondo', 'SYSTEM', 'Acc202601120001'),
(408, '2026-01-11 21:17:44', 'Acc202601120001', NULL, 'CREATE', '', 'Assigned roleId 4 to accountId Acc202601120001', 'SYSTEM', 'Acc202601120001'),
(409, '2026-01-11 21:17:44', 'Acc202500000000', 'Admin', 'CREATE', 'ACCOUNTS', 'Acc202601120001', '::1', NULL),
(410, '2026-01-11 21:17:49', 'Acc202601120001', 'Nieum', 'UPDATE', 'ACCOUNTS', 'Updated account: Nieumy Kaizer Redondo', 'SYSTEM', 'Acc202601120001'),
(411, '2026-01-11 21:17:49', 'Acc202601120001', 'Nieum', 'ARCHIVE', 'ACCOUNTS', 'Archived account: Nieumy Kaizer Redondo', 'SYSTEM', 'Acc202601120001'),
(412, '2026-01-11 21:17:49', 'Acc202500000000', 'Admin', 'ARCHIVE', 'ACCOUNTS', 'Acc202601120001', '::1', NULL),
(413, '2026-01-11 21:23:05', 'Prod202601120004', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 0)', 'SYSTEM', 'Prod202601120004'),
(414, '2026-01-11 21:23:06', 'Prod202601120004', NULL, 'DELETE', 'INVENTORY', 'Permanently deleted archived inventory: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120004'),
(415, '2026-01-11 21:23:06', 'system', 'Admin', 'RESTORE', 'INVENTORY', 'Prod202601120004', '::1', NULL),
(416, '2026-01-11 21:23:10', 'SUP20260111223072', NULL, 'UPDATE', '', 'Updated supplier: JOse', 'SYSTEM', 'SUP20260111223072'),
(417, '2026-01-11 21:23:10', 'SUP20260111223072', NULL, 'DELETE', '', 'Permanently deleted archived supplier: JOse', 'SYSTEM', 'SUP20260111223072'),
(418, '2026-01-11 21:23:10', 'system', 'Admin', 'RESTORE', '', 'SUP20260111223072', '::1', NULL),
(419, '2026-01-11 21:23:14', 'DELI20260112050809', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601120005 Status: Received → Received', 'SYSTEM', 'DELI20260112050809'),
(420, '2026-01-11 21:23:14', 'DELI20260112050809', NULL, 'DELETE', '', 'Permanently deleted archived delivery: DELI20260112050809', 'SYSTEM', 'DELI20260112050809'),
(421, '2026-01-11 21:23:14', 'system', 'Admin', 'RESTORE', '', 'DELI20260112050809', '::1', NULL),
(422, '2026-01-11 21:23:17', 'Acc202601120001', 'Nieum', 'UPDATE', 'ACCOUNTS', 'Updated account: Nieumy Kaizer Redondo', 'SYSTEM', 'Acc202601120001'),
(423, '2026-01-11 21:23:17', 'Acc202601120001', 'Nieum', 'DELETE', 'ACCOUNTS', 'Permanently deleted archived account: Nieumy Kaizer Redondo', 'SYSTEM', 'Acc202601120001'),
(424, '2026-01-11 21:23:17', 'system', 'Admin', 'RESTORE', 'ACCOUNTS', 'Acc202601120001', '::1', NULL),
(425, '2026-01-11 21:36:36', 'Acc202601120001', 'Nieum', 'UPDATE', 'ACCOUNTS', 'Updated account: Nieumy Kaizer Redondo', 'SYSTEM', 'Acc202601120001'),
(426, '2026-01-11 21:36:36', 'Acc202500000000', 'Admin', 'UPDATE', 'ACCOUNTS', 'Acc202601120001', '::1', NULL),
(427, '2026-01-11 21:56:23', 'Acc202500000000', 'Admin', 'LOGOUT', 'AUTH', 'Acc202500000000', '::1', NULL),
(428, '2026-01-11 21:57:46', 'Acc202500000000', 'Admin', 'CREATE', 'AUTH', 'OTP send attempt for password reset to: ajaeantonio16@gmail.com', '::1', NULL),
(429, '2026-01-11 21:57:46', 'SYSTEM', 'SYSTEM', 'CREATE', 'SYSTEM', 'OTP Email attempt to: ajaeantonio16@gmail.com', '::1', NULL),
(430, '2026-01-11 21:57:50', 'SYSTEM', 'SYSTEM', 'CREATE', 'SYSTEM', 'OTP Email sent successfully to: ajaeantonio16@gmail.com', '::1', NULL),
(431, '2026-01-11 21:57:50', 'Acc202500000000', 'Admin', 'CREATE', 'AUTH', 'OTP sent successfully for password reset to: ajaeantonio16@gmail.com', '::1', NULL),
(432, '2026-01-11 21:58:54', 'Acc202500000000', 'Admin', 'CREATE', 'AUTH', 'OTP send attempt for password reset to: ajaeantonio16@gmail.com', '::1', NULL),
(433, '2026-01-11 21:58:54', 'SYSTEM', 'SYSTEM', 'CREATE', 'SYSTEM', 'OTP Email attempt to: ajaeantonio16@gmail.com', '::1', NULL),
(434, '2026-01-11 21:58:58', 'SYSTEM', 'SYSTEM', 'CREATE', 'SYSTEM', 'OTP Email sent successfully to: ajaeantonio16@gmail.com', '::1', NULL),
(435, '2026-01-11 21:58:58', 'Acc202500000000', 'Admin', 'CREATE', 'AUTH', 'OTP sent successfully for password reset to: ajaeantonio16@gmail.com', '::1', NULL),
(436, '2026-01-11 22:12:52', 'Acc202500000000', 'Admin', 'CREATE', 'AUTH', 'OTP send attempt for password reset to: ajaeantonio16@gmail.com', '::1', NULL),
(437, '2026-01-11 22:12:52', 'SYSTEM', 'SYSTEM', 'CREATE', 'SYSTEM', 'OTP Email attempt to: ajaeantonio16@gmail.com', '::1', NULL),
(438, '2026-01-11 22:12:56', 'SYSTEM', 'SYSTEM', 'CREATE', 'SYSTEM', 'OTP Email sent successfully to: ajaeantonio16@gmail.com', '::1', NULL),
(439, '2026-01-11 22:12:56', 'Acc202500000000', 'Admin', 'CREATE', 'AUTH', 'OTP sent successfully for password reset to: ajaeantonio16@gmail.com', '::1', NULL),
(440, '2026-01-11 22:58:57', 'Acc202500000000', 'Admin', 'LOGIN', 'AUTH', 'Acc202500000000', '::1', NULL),
(441, '2026-01-11 23:22:49', 'Prod202601120006', 'SYSTEM', 'CREATE', 'INVENTORY', 'Created product: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120006'),
(442, '2026-01-11 23:22:49', 'Acc202500000000', 'Admin', 'CREATE', 'INVENTORY', 'Prod202601120006', '::1', NULL),
(443, '2026-01-11 23:22:53', 'Prod202601120006', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 11 → 11)', 'SYSTEM', 'Prod202601120006'),
(444, '2026-01-11 23:22:53', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120006', '::1', NULL),
(445, '2026-01-11 23:22:56', 'Prod202601120005', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 561 → 561)', 'SYSTEM', 'Prod202601120005'),
(446, '2026-01-11 23:22:56', 'Prod202601120005', NULL, 'ARCHIVE', 'INVENTORY', 'Archived inventory: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120005'),
(447, '2026-01-11 23:22:56', 'Acc202500000000', 'Admin', 'ARCHIVE', 'INVENTORY', 'Prod202601120005', '::1', NULL),
(448, '2026-01-11 23:23:05', 'Prod202601120007', 'SYSTEM', 'CREATE', 'INVENTORY', 'Created product: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120007'),
(449, '2026-01-11 23:23:05', 'Acc202500000000', 'Admin', 'CREATE', 'INVENTORY', 'Prod202601120007', '::1', NULL),
(450, '2026-01-11 23:23:07', 'DELI20260112072337', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601120007 from SUP20260111223072 Qty: 200 Status: Pending', 'SYSTEM', 'DELI20260112072337'),
(451, '2026-01-11 23:23:07', 'system', 'Admin', 'CREATE', '', 'DELI20260112072337', '::1', NULL),
(452, '2026-01-11 23:23:15', 'DELI20260112072337', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601120007 Status: Pending → Received', 'SYSTEM', 'DELI20260112072337'),
(453, '2026-01-11 23:23:15', 'Prod202601120007', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 0)', 'SYSTEM', 'Prod202601120007'),
(454, '2026-01-11 23:23:15', 'Prod202601120007', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 200)', 'SYSTEM', 'Prod202601120007'),
(455, '2026-01-11 23:23:25', 'Prod202601120005', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 561 → 561)', 'SYSTEM', 'Prod202601120005'),
(456, '2026-01-11 23:23:25', 'Prod202601120005', NULL, 'DELETE', 'INVENTORY', 'Permanently deleted archived inventory: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120005'),
(457, '2026-01-11 23:23:25', 'system', 'Admin', 'RESTORE', 'INVENTORY', 'Prod202601120005', '::1', NULL),
(458, '2026-01-12 02:47:22', 'Acc202500000000', 'Admin', 'LOGOUT', 'AUTH', 'Acc202500000000', '::1', NULL),
(459, '2026-01-12 05:14:03', 'Acc202500000000', 'Admin', 'LOGIN', 'AUTH', 'Acc202500000000', '::1', NULL),
(460, '2026-01-12 05:14:23', 'Prod202601120007', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 200 → 200)', 'SYSTEM', 'Prod202601120007'),
(461, '2026-01-12 05:14:23', 'Prod202601120007', NULL, 'ARCHIVE', 'INVENTORY', 'Archived inventory: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120007'),
(462, '2026-01-12 05:14:24', 'Acc202500000000', 'Admin', 'ARCHIVE', 'INVENTORY', 'Prod202601120007', '::1', NULL),
(463, '2026-01-12 05:14:36', 'SUP20260111223072', NULL, 'UPDATE', '', 'Updated supplier: JOse', 'SYSTEM', 'SUP20260111223072'),
(464, '2026-01-12 05:14:36', 'SUP20260111223072', NULL, 'ARCHIVE', '', 'Archived supplier: JOse', 'SYSTEM', 'SUP20260111223072'),
(465, '2026-01-12 05:14:36', 'Acc202500000000', 'Admin', 'ARCHIVE', '', 'SUP20260111223072', '::1', NULL),
(466, '2026-01-12 05:14:39', 'DELI20260112072337', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601120007 Status: Received → Received', 'SYSTEM', 'DELI20260112072337'),
(467, '2026-01-12 05:14:39', 'DELI20260112072337', NULL, 'ARCHIVE', '', 'Archived delivery: DELI20260112072337', 'SYSTEM', 'DELI20260112072337'),
(468, '2026-01-12 05:14:39', 'system', 'Admin', 'ARCHIVE', '', 'DELI20260112072337', '::1', NULL),
(469, '2026-01-12 05:17:47', 'Prod202601120007', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 200 → 200)', 'SYSTEM', 'Prod202601120007'),
(470, '2026-01-12 05:17:47', 'Prod202601120007', NULL, 'DELETE', 'INVENTORY', 'Permanently deleted archived inventory: Biogesic - Paracetamol', 'SYSTEM', 'Prod202601120007'),
(471, '2026-01-12 05:17:47', 'system', 'Admin', 'RESTORE', 'INVENTORY', 'Prod202601120007', '::1', NULL),
(472, '2026-01-12 05:17:50', 'SUP20260111223072', NULL, 'UPDATE', '', 'Updated supplier: JOse', 'SYSTEM', 'SUP20260111223072'),
(473, '2026-01-12 05:17:50', 'SUP20260111223072', NULL, 'DELETE', '', 'Permanently deleted archived supplier: JOse', 'SYSTEM', 'SUP20260111223072'),
(474, '2026-01-12 05:17:50', 'system', 'Admin', 'RESTORE', '', 'SUP20260111223072', '::1', NULL),
(475, '2026-01-12 05:17:53', 'DELI20260112072337', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601120007 Status: Received → Received', 'SYSTEM', 'DELI20260112072337'),
(476, '2026-01-12 05:17:53', 'DELI20260112072337', NULL, 'DELETE', '', 'Permanently deleted archived delivery: DELI20260112072337', 'SYSTEM', 'DELI20260112072337'),
(477, '2026-01-12 05:17:53', 'system', 'Admin', 'RESTORE', '', 'DELI20260112072337', '::1', NULL),
(478, '2026-01-12 05:25:22', 'SUP20260112132532', NULL, 'CREATE', '', 'Created supplier: Unilad', 'SYSTEM', 'SUP20260112132532'),
(479, '2026-01-12 05:25:22', 'Acc202500000000', 'Admin', 'CREATE', '', 'SUP20260112132532', '::1', NULL),
(480, '2026-01-12 05:41:59', 'Prod202601120008', 'SYSTEM', 'CREATE', 'INVENTORY', 'Created product: awa - awa', 'SYSTEM', 'Prod202601120008'),
(481, '2026-01-12 05:42:00', 'Acc202500000000', 'Admin', 'CREATE', 'INVENTORY', 'Prod202601120008', '::1', NULL),
(482, '2026-01-12 05:51:13', 'Prod202601120009', 'SYSTEM', 'CREATE', 'INVENTORY', 'Created product: awa - awa', 'SYSTEM', 'Prod202601120009'),
(483, '2026-01-12 05:51:13', 'Acc202500000000', 'Admin', 'CREATE', 'INVENTORY', 'Prod202601120009', '::1', NULL),
(484, '2026-01-12 05:51:19', 'Prod202601120010', 'SYSTEM', 'CREATE', 'INVENTORY', 'Created product: awa - awa', 'SYSTEM', 'Prod202601120010'),
(485, '2026-01-12 05:51:19', 'Acc202500000000', 'Admin', 'CREATE', 'INVENTORY', 'Prod202601120010', '::1', NULL),
(486, '2026-01-12 05:52:18', 'Prod202601120011', 'SYSTEM', 'CREATE', 'INVENTORY', 'Created product: awa - awa', 'SYSTEM', 'Prod202601120011'),
(487, '2026-01-12 05:52:18', 'Acc202500000000', 'Admin', 'CREATE', 'INVENTORY', 'Prod202601120011', '::1', NULL),
(488, '2026-01-12 06:00:17', 'Prod202601120012', 'SYSTEM', 'CREATE', 'INVENTORY', 'Created product: awa - awa', 'SYSTEM', 'Prod202601120012'),
(489, '2026-01-12 06:00:17', 'Acc202500000000', 'Admin', 'CREATE', 'INVENTORY', 'Prod202601120012', '::1', NULL),
(490, '2026-01-12 06:22:58', 'Prod202601120012', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: awa (Stock: 300 → 300)', 'SYSTEM', 'Prod202601120012'),
(491, '2026-01-12 06:22:58', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120012', '::1', NULL),
(492, '2026-01-12 06:26:57', 'Prod202601120011', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: awa (Stock: 300 → 0)', 'SYSTEM', 'Prod202601120011'),
(493, '2026-01-12 06:26:57', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120011', '::1', NULL),
(494, '2026-01-12 06:37:38', 'Prod202601120012', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: awa (Stock: 300 → 3000)', 'SYSTEM', 'Prod202601120012'),
(495, '2026-01-12 06:37:38', 'Acc202500000000', 'Admin', 'UPDATE', 'INVENTORY', 'Prod202601120012', '::1', NULL),
(496, '2026-01-12 06:40:49', 'Prod202601120012', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: awa (Stock: 3000 → 3000)', 'SYSTEM', 'Prod202601120012'),
(497, '2026-01-12 06:40:49', 'Prod202601120012', NULL, 'ARCHIVE', 'INVENTORY', 'Archived inventory: awa - awa', 'SYSTEM', 'Prod202601120012'),
(498, '2026-01-12 06:40:49', 'Acc202500000000', 'Admin', 'ARCHIVE', 'INVENTORY', 'Prod202601120012', '::1', NULL),
(499, '2026-01-12 06:42:29', 'Prod202601120012', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: awa (Stock: 3000 → 3000)', 'SYSTEM', 'Prod202601120012'),
(500, '2026-01-12 06:42:29', 'Prod202601120012', NULL, 'DELETE', 'INVENTORY', 'Permanently deleted archived inventory: awa - awa', 'SYSTEM', 'Prod202601120012'),
(501, '2026-01-12 06:42:29', 'system', 'Admin', 'RESTORE', 'INVENTORY', 'Prod202601120012', '::1', NULL),
(502, '2026-01-12 06:42:50', 'Prod202601120013', 'SYSTEM', 'CREATE', 'INVENTORY', 'Created product: Biogesic - Paracetamol 2', 'SYSTEM', 'Prod202601120013'),
(503, '2026-01-12 06:42:50', 'Acc202500000000', 'Admin', 'CREATE', 'INVENTORY', 'Prod202601120013', '::1', NULL),
(504, '2026-01-12 06:42:51', 'DELI20260112144238', 'SYSTEM', 'CREATE', '', 'Delivery created: Prod202601120013 from SUP20260112132532 Qty: 200 Status: Pending', 'SYSTEM', 'DELI20260112144238'),
(505, '2026-01-12 06:42:51', 'system', 'Admin', 'CREATE', '', 'DELI20260112144238', '::1', NULL),
(506, '2026-01-12 06:42:59', 'DELI20260112144238', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601120013 Status: Pending → Received', 'SYSTEM', 'DELI20260112144238'),
(507, '2026-01-12 06:42:59', 'Prod202601120013', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 0)', 'SYSTEM', 'Prod202601120013'),
(508, '2026-01-12 06:42:59', 'Prod202601120013', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 200)', 'SYSTEM', 'Prod202601120013'),
(509, '2026-01-12 06:43:01', 'DELI20260112035255', 'SYSTEM', 'UPDATE', '', 'Delivery updated: Prod202601120003 Status: Pending → Received', 'SYSTEM', 'DELI20260112035255'),
(510, '2026-01-12 06:43:01', 'Prod202601120003', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 0)', 'SYSTEM', 'Prod202601120003'),
(511, '2026-01-12 06:43:02', 'Prod202601120003', 'SYSTEM', 'UPDATE', 'INVENTORY', 'Updated product: Biogesic (Stock: 0 → 200)', 'SYSTEM', 'Prod202601120003'),
(512, '2026-01-12 06:43:38', 'Acc202500000000', 'Admin', 'LOGOUT', 'AUTH', 'Acc202500000000', '::1', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `archive_accounts`
--

CREATE TABLE `archive_accounts` (
  `accountId` varchar(50) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `middleName` varchar(100) DEFAULT NULL,
  `lastName` varchar(100) NOT NULL,
  `fullName` varchar(255) NOT NULL,
  `phoneNumber` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `image` varchar(255) DEFAULT 'Pharmacy Icons/profile icon.png',
  `birthday` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `sex` enum('male','female','other') DEFAULT NULL,
  `isActive` tinyint(1) DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `isArchive` tinyint(1) NOT NULL DEFAULT 0,
  `archivedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `archivedBy` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `archive_accounts`
--
DELIMITER $$
CREATE TRIGGER `trg_archive_accounts_audit_delete` AFTER DELETE ON `archive_accounts` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (OLD.accountId, OLD.username, 'DELETE', 'ACCOUNTS',
          CONCAT('Permanently deleted archived account: ', OLD.fullName), 'SYSTEM', OLD.accountId);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_archive_accounts_audit_insert` AFTER INSERT ON `archive_accounts` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NEW.accountId, NEW.username, 'ARCHIVE', 'ACCOUNTS',
          CONCAT('Archived account: ', NEW.fullName), 'SYSTEM', NEW.accountId);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `archive_deliveries`
--

CREATE TABLE `archive_deliveries` (
  `supplyId` varchar(50) NOT NULL,
  `supplierId` varchar(50) NOT NULL,
  `productId` varchar(50) NOT NULL,
  `batchNo` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unitPrice` decimal(10,2) NOT NULL,
  `totalAmount` decimal(10,2) NOT NULL,
  `deliveryDate` date DEFAULT NULL,
  `deliveredDate` date DEFAULT NULL,
  `status` enum('Pending','Received','Cancelled') DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `isActive` tinyint(1) DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `isArchive` tinyint(1) NOT NULL DEFAULT 0,
  `archivedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `archivedBy` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `archive_deliveries`
--
DELIMITER $$
CREATE TRIGGER `trg_archive_deliveries_audit_delete` AFTER DELETE ON `archive_deliveries` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (OLD.supplyId, NULL, 'DELETE', 'SUPPLIERS',
          CONCAT('Permanently deleted archived delivery: ', OLD.supplyId), 'SYSTEM', OLD.supplyId);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_archive_deliveries_audit_insert` AFTER INSERT ON `archive_deliveries` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NEW.supplyId, NULL, 'ARCHIVE', 'SUPPLIERS',
          CONCAT('Archived delivery: ', NEW.supplyId), 'SYSTEM', NEW.supplyId);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `archive_inventory`
--

CREATE TABLE `archive_inventory` (
  `productId` varchar(50) NOT NULL,
  `brand` varchar(255) NOT NULL,
  `genericName` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `manufacturedDate` date NOT NULL,
  `expirationDate` date NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `batchNo` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `supplierId` varchar(50) NOT NULL,
  `phoneNumber` varchar(20) NOT NULL,
  `requiresPrescription` tinyint(1) DEFAULT 0,
  `lowStockThreshold` int(11) NOT NULL,
  `isActive` tinyint(1) DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `isArchive` tinyint(1) NOT NULL DEFAULT 0,
  `archivedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `archivedBy` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `archive_inventory`
--
DELIMITER $$
CREATE TRIGGER `trg_archive_inventory_audit_delete` AFTER DELETE ON `archive_inventory` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (OLD.productId, NULL, 'DELETE', 'INVENTORY',
          CONCAT('Permanently deleted archived inventory: ', OLD.brand, ' - ', OLD.genericName), 'SYSTEM', OLD.productId);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_archive_inventory_audit_insert` AFTER INSERT ON `archive_inventory` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NEW.productId, NULL, 'ARCHIVE', 'INVENTORY',
          CONCAT('Archived inventory: ', NEW.brand, ' - ', NEW.genericName), 'SYSTEM', NEW.productId);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `archive_sales`
--

CREATE TABLE `archive_sales` (
  `salesId` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `productId` varchar(50) NOT NULL,
  `accountId` varchar(50) NOT NULL,
  `quantitySold` int(11) NOT NULL,
  `totalAmount` decimal(10,2) NOT NULL,
  `batchNo` varchar(50) DEFAULT NULL,
  `paymentMethod` enum('Cash','Card','E-Wallet') DEFAULT 'Cash',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archivedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `archivedBy` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `archive_sales`
--
DELIMITER $$
CREATE TRIGGER `trg_archive_sales_audit_delete` AFTER DELETE ON `archive_sales` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (OLD.salesId, NULL, 'DELETE', 'SALES',
          CONCAT('Permanently deleted archived sale: ', OLD.salesId), 'SYSTEM', OLD.salesId);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_archive_sales_audit_insert` AFTER INSERT ON `archive_sales` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NEW.salesId, NULL, 'ARCHIVE', 'SALES',
          CONCAT('Archived sale: ', NEW.salesId), 'SYSTEM', NEW.salesId);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `archive_suppliers`
--

CREATE TABLE `archive_suppliers` (
  `supplierId` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contactPerson` varchar(255) NOT NULL,
  `phoneNumber` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `isActive` tinyint(1) DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `isArchive` tinyint(1) NOT NULL DEFAULT 0,
  `archivedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `archivedBy` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `archive_suppliers`
--
DELIMITER $$
CREATE TRIGGER `trg_archive_suppliers_audit_delete` AFTER DELETE ON `archive_suppliers` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (OLD.supplierId, NULL, 'DELETE', 'SUPPLIERS',
          CONCAT('Permanently deleted archived supplier: ', OLD.name), 'SYSTEM', OLD.supplierId);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_archive_suppliers_audit_insert` AFTER INSERT ON `archive_suppliers` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NEW.supplierId, NULL, 'ARCHIVE', 'SUPPLIERS',
          CONCAT('Archived supplier: ', NEW.name), 'SYSTEM', NEW.supplierId);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `counters`
--

CREATE TABLE `counters` (
  `counterId` int(11) NOT NULL,
  `counterType` varchar(50) NOT NULL,
  `counterDate` date NOT NULL,
  `currentValue` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `counters`
--

INSERT INTO `counters` (`counterId`, `counterType`, `counterDate`, `currentValue`) VALUES
(28, 'Sup', '2026-01-11', 1),
(29, 'Prod', '2026-01-11', 1),
(30, 'Batch', '2026-01-11', 1),
(31, 'Supply', '2026-01-11', 6),
(37, 'Supply', '2026-01-12', 10),
(43, 'Prod', '2026-01-12', 13),
(44, 'Batch', '2026-01-12', 13),
(55, 'Acc', '2026-01-12', 1),
(61, 'Sup', '2026-01-12', 1);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `productId` varchar(50) NOT NULL,
  `brand` varchar(255) NOT NULL,
  `genericName` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `manufacturedDate` date NOT NULL,
  `expirationDate` date NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `batchNo` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `supplierId` varchar(50) NOT NULL,
  `phoneNumber` varchar(20) NOT NULL,
  `requiresPrescription` tinyint(1) DEFAULT 0,
  `lowStockThreshold` int(11) NOT NULL,
  `isActive` tinyint(1) DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `isArchive` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`productId`, `brand`, `genericName`, `category`, `manufacturedDate`, `expirationDate`, `stock`, `batchNo`, `price`, `supplierId`, `phoneNumber`, `requiresPrescription`, `lowStockThreshold`, `isActive`, `createdAt`, `updatedAt`, `isArchive`) VALUES
('Prod202601110001', 'Biogesic', 'Paracetamol', 'Pain Killer', '2026-01-09', '2026-04-04', 200, 'Batch202601110001', 200.00, 'SUP20260111223072', '09565213552', 1, 30, 1, '2026-01-11 14:30:29', '2026-01-11 18:48:58', 0),
('Prod202601120001', 'Biogesic', 'Paracetamol', 'Pain Killer', '2026-01-02', '2026-06-18', 19, 'Batch202601120001', 1.00, 'SUP20260111223072', '09565213552', 1, 10, 1, '2026-01-11 19:12:05', '2026-01-11 19:23:25', 0),
('Prod202601120002', 'Biogesic', 'Paracetamol', 'Pain Killer', '2025-10-03', '2026-01-14', 0, 'Batch202601120002', 1.00, 'SUP20260111223072', '09565213552', 1, 10, 1, '2026-01-11 19:48:02', '2026-01-11 20:26:54', 0),
('Prod202601120003', 'Biogesic', 'Paracetamol', 'Pain Killer', '2026-01-09', '2026-04-04', 200, 'Batch202601120003', 200.00, 'SUP20260111223072', '09565213552', 1, 300, 1, '2026-01-11 19:52:32', '2026-01-12 06:43:02', 0),
('Prod202601120004', 'Biogesic', 'Paracetamol', 'Pain Killer', '2025-10-03', '2026-01-14', 0, 'Batch202601120004', 1.00, 'SUP20260111223072', '09565213552', 1, 10, 1, '2026-01-11 20:28:00', '2026-01-11 21:23:05', 0),
('Prod202601120005', 'Biogesic', 'Paracetamol', 'Pain Killer', '2025-10-03', '2026-01-14', 561, 'Batch202601120005', 1.00, 'SUP20260111223072', '09565213552', 1, 10, 1, '2026-01-11 20:33:22', '2026-01-11 23:23:25', 0),
('Prod202601120006', 'Biogesic', 'Paracetamol', 'Pain Killer', '2025-10-03', '2026-01-14', 11, 'Batch202601120006', 1.00, 'SUP20260111223072', '09565213552', 1, 10, 1, '2026-01-11 23:22:49', '2026-01-11 23:22:49', 0),
('Prod202601120007', 'Biogesic', 'Paracetamol', 'Pain Killer', '2026-01-09', '2026-04-04', 200, 'Batch202601120007', 200.00, 'SUP20260111223072', '09565213552', 1, 300, 1, '2026-01-11 23:23:05', '2026-01-12 05:17:47', 0),
('Prod202601120008', 'awa', 'awa', 'Pain Killer', '2026-01-01', '2026-03-26', 300, 'Batch202601120008', 20.00, 'SUP20260112132532', '09565213552', 1, 2, 1, '2026-01-12 05:41:59', '2026-01-12 05:41:59', 0),
('Prod202601120009', 'awa', 'awa', 'Pain Killer', '2026-01-01', '2026-03-26', 300, 'Batch202601120009', 20.00, 'SUP20260111223072', '09565213552', 0, 2, 1, '2026-01-12 05:51:13', '2026-01-12 05:51:13', 0),
('Prod202601120010', 'awa', 'awa', 'Pain Killer', '2026-01-01', '2026-03-26', 300, 'Batch202601120010', 20.00, 'SUP20260111223072', '09565213552', 1, 2, 1, '2026-01-12 05:51:19', '2026-01-12 05:51:19', 0),
('Prod202601120011', 'awa', 'awa', 'Pain Killer', '2026-01-01', '2026-03-26', 0, 'Batch202601120011', 20.00, 'SUP20260112132532', '09565213552', 1, 2, 1, '2026-01-12 05:52:18', '2026-01-12 06:26:57', 0),
('Prod202601120012', 'awa', 'awa', 'Pain Killer', '2026-01-01', '2026-07-22', 3000, 'Batch202601120012', 20.00, 'SUP20260112132532', '09565213552', 1, 2000, 1, '2026-01-12 06:00:17', '2026-01-12 06:42:29', 0),
('Prod202601120013', 'Biogesic', 'Paracetamol 2', 'Pain Killer', '2026-01-09', '2026-04-04', 200, 'Batch202601120013', 200.00, 'SUP20260112132532', '09565213552', 1, 300, 1, '2026-01-12 06:42:50', '2026-01-12 06:42:59', 0);

--
-- Triggers `inventory`
--
DELIMITER $$
CREATE TRIGGER `trg_inventory_audit_insert` AFTER INSERT ON `inventory` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NEW.productId, 'SYSTEM', 'CREATE', 'INVENTORY',
          CONCAT('Created product: ', NEW.brand, ' - ', NEW.genericName), 'SYSTEM', NEW.productId);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_inventory_audit_update` AFTER UPDATE ON `inventory` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NEW.productId, 'SYSTEM', 'UPDATE', 'INVENTORY',
          CONCAT('Updated product: ', NEW.brand, ' (Stock: ', OLD.stock, ' → ', NEW.stock, ')'), 'SYSTEM', NEW.productId);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notificationId` int(11) NOT NULL,
  `accountId` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `isRead` tinyint(1) DEFAULT 0,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `relatedId` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `online_users`
--

CREATE TABLE `online_users` (
  `accountId` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `loginTime` timestamp NOT NULL DEFAULT current_timestamp(),
  `lastActivity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ipAddress` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_request`
--

CREATE TABLE `otp_request` (
  `otpId` varchar(50) NOT NULL,
  `accountId` varchar(50) NOT NULL,
  `otpHash` varchar(255) NOT NULL,
  `expiredAt` datetime NOT NULL,
  `isUsed` tinyint(1) DEFAULT 0,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otp_request`
--

INSERT INTO `otp_request` (`otpId`, `accountId`, `otpHash`, `expiredAt`, `isUsed`, `createdAt`) VALUES
('OTP202601120557456229', 'Acc202500000000', '$2y$10$FkIr4rPGvIeIraZd0/LDW.my62kBBF.5q/nVTaAvMeEUcXO8R12Q.', '2026-01-12 06:02:45', 0, '2026-01-11 21:57:45'),
('OTP202601120558543375', 'Acc202500000000', '$2y$10$mguyOeQfBqF6S/rMFaPnFeD4q35dDSxcmKh6.19L.WSn0tJfTGEpm', '2026-01-12 06:03:54', 0, '2026-01-11 21:58:54'),
('OTP202601120612524539', 'Acc202500000000', '$2y$10$2xtj4sG7nud78O8reDqenuC8a05uyh9DFHJ.C7/2zW5lNq7wJumWK', '2026-01-12 06:17:52', 1, '2026-01-11 22:12:52');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `permissionId` int(11) NOT NULL,
  `permissionName` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`permissionId`, `permissionName`, `description`) VALUES
(1, 'Archive.view', 'View archive records'),
(2, 'Catalogs.view', 'View product catalogs'),
(3, 'Inventory.view', 'View inventory'),
(4, 'Logs.view', 'View activity logs'),
(5, 'Suppliers.view', 'View suppliers'),
(6, 'Inventory.create', 'Create inventory items'),
(7, 'Inventory.update', 'Update inventory items'),
(8, 'Inventory.delete', 'Delete inventory items'),
(9, 'Suppliers.create', 'Create suppliers'),
(10, 'Suppliers.update', 'Update suppliers'),
(11, 'Suppliers.delete', 'Delete suppliers'),
(12, 'Sales.create', 'Create sales'),
(13, 'Sales.update', 'Update sales'),
(14, 'Sales.delete', 'Delete sales'),
(15, 'Account.view', 'View account details'),
(16, 'Account.create', 'Create new account'),
(17, 'Account.update', 'Update account details'),
(18, 'Account.delete', 'Delete account'),
(19, 'RBAC.manage', 'Manage roles and permissions');

--
-- Triggers `permissions`
--
DELIMITER $$
CREATE TRIGGER `trg_permissions_audit_delete` AFTER DELETE ON `permissions` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NULL, NULL, 'DELETE', 'RBAC', CONCAT('Deleted permission: ', OLD.permissionName), 'SYSTEM', OLD.permissionId);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_permissions_audit_insert` AFTER INSERT ON `permissions` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NULL, NULL, 'CREATE', 'RBAC', CONCAT('Created permission: ', NEW.permissionName), 'SYSTEM', NEW.permissionId);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_permissions_audit_update` AFTER UPDATE ON `permissions` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NULL, NULL, 'UPDATE', 'RBAC', CONCAT('Updated permission: ', NEW.permissionName), 'SYSTEM', NEW.permissionId);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `roleId` int(11) NOT NULL,
  `roleName` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`roleId`, `roleName`, `description`) VALUES
(1, 'Super Admin', 'Full system access'),
(2, 'Admin', 'Administrative user'),
(3, 'Manager', 'Manager with limited admin rights'),
(4, 'Staff', 'Standard staff permissions'),
(5, 'Pending User', 'Pending for approval');

--
-- Triggers `roles`
--
DELIMITER $$
CREATE TRIGGER `trg_roles_audit_delete` AFTER DELETE ON `roles` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NULL, NULL, 'DELETE', 'RBAC', CONCAT('Deleted role: ', OLD.roleName), 'SYSTEM', OLD.roleId);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_roles_audit_insert` AFTER INSERT ON `roles` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NULL, NULL, 'CREATE', 'RBAC', CONCAT('Created role: ', NEW.roleName), 'SYSTEM', NEW.roleId);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_roles_audit_update` AFTER UPDATE ON `roles` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NULL, NULL, 'UPDATE', 'RBAC', CONCAT('Updated role: ', NEW.roleName), 'SYSTEM', NEW.roleId);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `roleId` int(11) NOT NULL,
  `permissionId` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`roleId`, `permissionId`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19);

--
-- Triggers `role_permissions`
--
DELIMITER $$
CREATE TRIGGER `trg_role_permissions_audit_delete` AFTER DELETE ON `role_permissions` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NULL, NULL, 'DELETE', 'RBAC', CONCAT('Unassigned permissionId ', OLD.permissionId, ' from roleId ', OLD.roleId), 'SYSTEM', OLD.roleId);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_role_permissions_audit_insert` AFTER INSERT ON `role_permissions` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NULL, NULL, 'CREATE', 'RBAC', CONCAT('Assigned permissionId ', NEW.permissionId, ' to roleId ', NEW.roleId), 'SYSTEM', NEW.roleId);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `salesId` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `productId` varchar(50) NOT NULL,
  `accountId` varchar(50) NOT NULL,
  `quantitySold` int(11) NOT NULL,
  `totalAmount` decimal(10,2) NOT NULL,
  `batchNo` varchar(50) DEFAULT NULL,
  `paymentMethod` enum('Cash','Card','E-Wallet') DEFAULT 'Cash',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `sales`
--
DELIMITER $$
CREATE TRIGGER `trg_sales_audit_insert` AFTER INSERT ON `sales` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NEW.accountId, 'SYSTEM', 'CREATE', 'SALES',
      CONCAT('Sale: ', NEW.productId, ' Qty: ', NEW.quantitySold, ' Amount: ₱', NEW.totalAmount), 'SYSTEM', NEW.salesId);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplierId` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contactPerson` varchar(255) NOT NULL,
  `phoneNumber` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `isActive` tinyint(1) DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `isArchive` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplierId`, `name`, `contactPerson`, `phoneNumber`, `email`, `address`, `status`, `isActive`, `createdAt`, `updatedAt`, `isArchive`) VALUES
('SUP20260111223072', 'JOse', 'Joshua', '09565213552', 'nieumm@gmail.com', '596 JN Mateo Street', 'active', 1, '2026-01-11 14:30:20', '2026-01-12 05:17:50', 0),
('SUP20260112132532', 'Unilad', 'Adrian Jae Reyes Antonio', '09565213552', 'ajaemontage@gmail.com', '596 JN Mateo Street', 'active', 1, '2026-01-12 05:25:22', '2026-01-12 05:25:22', 0);

--
-- Triggers `suppliers`
--
DELIMITER $$
CREATE TRIGGER `trg_suppliers_audit_insert` AFTER INSERT ON `suppliers` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NEW.supplierId, NULL, 'CREATE', 'SUPPLIERS',
          CONCAT('Created supplier: ', NEW.name), 'SYSTEM', NEW.supplierId);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_suppliers_audit_update` AFTER UPDATE ON `suppliers` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NEW.supplierId, NULL, 'UPDATE', 'SUPPLIERS',
          CONCAT('Updated supplier: ', NEW.name), 'SYSTEM', NEW.supplierId);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `supply_deliveries`
--

CREATE TABLE `supply_deliveries` (
  `supplyId` varchar(50) NOT NULL,
  `supplierId` varchar(50) NOT NULL,
  `productId` varchar(50) NOT NULL,
  `batchNo` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unitPrice` decimal(10,2) NOT NULL,
  `totalAmount` decimal(10,2) NOT NULL,
  `deliveryDate` date DEFAULT NULL,
  `deliveredDate` date DEFAULT NULL,
  `status` enum('Pending','Received','Cancelled') DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `isActive` tinyint(1) DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `isArchive` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supply_deliveries`
--

INSERT INTO `supply_deliveries` (`supplyId`, `supplierId`, `productId`, `batchNo`, `quantity`, `unitPrice`, `totalAmount`, `deliveryDate`, `deliveredDate`, `status`, `notes`, `isActive`, `createdAt`, `updatedAt`, `isArchive`) VALUES
('DELI20260111223030', 'SUP20260111223072', 'Prod202601110001', 'Batch202601110001', 200, 200.00, 40000.00, '0000-00-00', '2026-01-11', 'Received', '', 1, '2026-01-11 14:30:30', '2026-01-11 14:30:43', 0),
('DELI20260111234489', 'SUP20260111223072', 'Prod202601110001', 'Batch202601110001', 100, 200.00, 20000.00, '2026-01-11', '2026-01-11', 'Received', 'asd', 1, '2026-01-11 15:44:48', '2026-01-11 15:52:08', 0),
('DELI20260111235311', 'SUP20260111223072', 'Prod202601110001', 'Batch202601110001', 1000, 200.00, 200000.00, '2026-01-11', '2026-01-11', 'Received', '', 1, '2026-01-11 15:53:36', '2026-01-11 15:53:39', 0),
('DELI20260111235859', 'SUP20260111223072', 'Prod202601110001', 'Batch202601110001', 100, 200.00, 20000.00, '2026-01-11', '2026-01-11', 'Received', 'nice', 1, '2026-01-11 15:58:48', '2026-01-11 15:58:54', 0),
('DELI20260111235932', 'SUP20260111223072', 'Prod202601110001', 'Batch202601110001', 20, 200.00, 4000.00, '2026-01-11', '2026-01-15', 'Received', '', 1, '2026-01-11 15:59:29', '2026-01-11 15:59:29', 0),
('DELI20260111235988', 'SUP20260111223072', 'Prod202601110001', 'Batch202601110001', 30, 200.00, 6000.00, '2026-01-11', NULL, 'Cancelled', 'wrong order', 1, '2026-01-11 15:59:54', '2026-01-11 16:00:04', 0),
('DELI20260112000060', 'SUP20260111223072', 'Prod202601110001', 'Batch202601110001', 30, 200.00, 6000.00, '2026-01-11', NULL, 'Pending', '', 1, '2026-01-11 16:00:37', '2026-01-11 16:00:37', 0),
('DELI20260112000117', 'SUP20260111223072', 'Prod202601110001', 'Batch202601110001', 1, 200.00, 200.00, '2026-02-01', NULL, 'Pending', '', 1, '2026-01-11 16:01:31', '2026-01-11 16:01:31', 0),
('DELI20260112000274', 'SUP20260111223072', 'Prod202601110001', 'Batch202601110001', 20, 200.00, 4000.00, '2026-02-28', NULL, 'Pending', '', 1, '2026-01-11 16:02:13', '2026-01-11 16:02:13', 0),
('DELI20260112024446', 'SUP20260111223072', 'Prod202601110001', 'Batch202601110001', 1000, 200.00, 200000.00, '2026-01-11', NULL, 'Pending', '', 1, '2026-01-11 18:44:35', '2026-01-11 18:44:35', 0),
('DELI20260112024516', 'SUP20260111223072', 'Prod202601110001', 'Batch202601110001', 30, 200.00, 6000.00, '2026-01-11', '2026-01-12', 'Received', '', 1, '2026-01-11 18:45:52', '2026-01-11 18:46:52', 0),
('DELI20260112024862', 'SUP20260111223072', 'Prod202601110001', 'Batch202601110001', 50, 200.00, 10000.00, '2026-01-12', '2026-01-11', 'Received', '', 1, '2026-01-11 18:48:48', '2026-01-11 18:48:58', 0),
('DELI20260112035255', 'SUP20260111223072', 'Prod202601120003', 'Batch202601120003', 200, 200.00, 40000.00, '2026-01-11', '2026-01-12', 'Received', '', 1, '2026-01-11 19:52:34', '2026-01-12 06:43:01', 0),
('DELI20260112050809', 'SUP20260111223072', 'Prod202601120005', 'Batch202601120005', 550, 1.00, 550.00, '2026-01-12', '2026-01-12', 'Received', '', 1, '2026-01-11 21:08:40', '2026-01-11 21:23:14', 0),
('DELI20260112072337', 'SUP20260111223072', 'Prod202601120007', 'Batch202601120007', 200, 200.00, 40000.00, '2026-01-11', '2026-01-11', 'Received', '', 1, '2026-01-11 23:23:07', '2026-01-12 05:17:53', 0),
('DELI20260112144238', 'SUP20260112132532', 'Prod202601120013', 'Batch202601120013', 200, 200.00, 40000.00, '2026-01-12', '2026-01-12', 'Received', '', 1, '2026-01-12 06:42:51', '2026-01-12 06:42:59', 0);

--
-- Triggers `supply_deliveries`
--
DELIMITER $$
CREATE TRIGGER `trg_supply_deliveries_audit_insert` AFTER INSERT ON `supply_deliveries` FOR EACH ROW BEGIN
    INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
    VALUES (NEW.supplyId, 'SYSTEM', 'CREATE', 'SUPPLIERS',
            CONCAT('Delivery created: ', NEW.productId, ' from ', NEW.supplierId, ' Qty: ', NEW.quantity, ' Status: ', NEW.status), 'SYSTEM', NEW.supplyId);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_supply_deliveries_audit_update` AFTER UPDATE ON `supply_deliveries` FOR EACH ROW BEGIN
    INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
    VALUES (NEW.supplyId, 'SYSTEM', 'UPDATE', 'SUPPLIERS',
            CONCAT('Delivery updated: ', NEW.productId, ' Status: ', OLD.status, ' → ', NEW.status), 'SYSTEM', NEW.supplyId);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `settingKey` varchar(100) NOT NULL,
  `settingValue` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updatedBy` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `settingKey`, `settingValue`, `description`, `updatedAt`, `updatedBy`) VALUES
(2, 'expiry_alert_days', '100', 'Days before expiry to show alerts', '2025-10-28 06:37:33', NULL),
(3, 'pharmacy_name', 'Echinacea Pharmacy', 'Pharmacy business name', '2025-10-28 06:37:33', NULL),
(4, 'pharmacy_address', '', 'Pharmacy business address', '2025-10-28 06:37:33', NULL),
(5, 'pharmacy_phone_number', '', 'Pharmacy phone number', '2025-10-28 06:37:33', NULL),
(6, 'pharmacy_email', 'EchinaceaPharmacy@gmail.com', 'Pharmacy email address', '2025-10-28 06:37:33', NULL),
(7, 'tax_rate', '0.12', 'VAT/Tax rate (Philippine 12% VAT)', '2025-10-28 06:37:33', NULL),
(8, 'receipt_footer', 'Thank you for your purchase!', 'Receipt footer message', '2025-10-28 06:37:33', NULL);

--
-- Triggers `system_settings`
--
DELIMITER $$
CREATE TRIGGER `trg_system_settings_audit_update` AFTER UPDATE ON `system_settings` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NULL, NULL, 'UPDATE', 'SYSTEM',
          CONCAT('Updated setting: ', NEW.settingKey, ' = ', NEW.settingValue), 'SYSTEM', NEW.id);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `accountId` varchar(50) NOT NULL,
  `roleId` int(11) NOT NULL,
  `assignedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`accountId`, `roleId`, `assignedAt`) VALUES
('Acc202500000000', 1, '2026-01-10 06:43:12'),
('Acc202601110001', 2, '2026-01-11 12:16:28'),
('Acc202601110002', 5, '2026-01-11 14:22:11'),
('Acc202601110003', 5, '2026-01-11 14:29:35'),
('Acc202601110004', 5, '2026-01-11 14:49:12'),
('Acc202601110005', 5, '2026-01-11 14:56:17'),
('Acc202601120006', 5, '2026-01-11 19:27:53');

--
-- Triggers `user_roles`
--
DELIMITER $$
CREATE TRIGGER `trg_user_roles_audit_delete` AFTER DELETE ON `user_roles` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (OLD.accountId, NULL, 'DELETE', 'RBAC', CONCAT('Unassigned roleId ', OLD.roleId, ' from accountId ', OLD.accountId), 'SYSTEM', OLD.accountId);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_user_roles_audit_insert` AFTER INSERT ON `user_roles` FOR EACH ROW BEGIN
  INSERT INTO activity_logs (accountId, username, action, module, details, ipAddress, recordId)
  VALUES (NEW.accountId, NULL, 'CREATE', 'RBAC', CONCAT('Assigned roleId ', NEW.roleId, ' to accountId ', NEW.accountId), 'SYSTEM', NEW.accountId);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_schedule`
--

CREATE TABLE `user_schedule` (
  `scheduleId` int(11) NOT NULL,
  `accountId` varchar(50) NOT NULL,
  `day` varchar(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_token`
--

CREATE TABLE `user_token` (
  `selector` varchar(64) NOT NULL,
  `accountId` varchar(50) NOT NULL,
  `validator` varchar(255) NOT NULL,
  `expiry` datetime NOT NULL DEFAULT (current_timestamp() + interval 30 day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_cancelled_deliveries`
-- (See below for the actual view)
--
CREATE TABLE `view_cancelled_deliveries` (
`supplyId` varchar(50)
,`supplierId` varchar(50)
,`productId` varchar(50)
,`batchNo` varchar(50)
,`quantity` int(11)
,`unitPrice` decimal(10,2)
,`totalAmount` decimal(10,2)
,`deliveryDate` date
,`deliveredDate` date
,`status` enum('Pending','Received','Cancelled')
,`notes` text
,`isActive` tinyint(1)
,`createdAt` timestamp
,`updatedAt` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_delivered_deliveries`
-- (See below for the actual view)
--
CREATE TABLE `view_delivered_deliveries` (
`supplyId` varchar(50)
,`supplierId` varchar(50)
,`productId` varchar(50)
,`batchNo` varchar(50)
,`quantity` int(11)
,`unitPrice` decimal(10,2)
,`totalAmount` decimal(10,2)
,`deliveryDate` date
,`deliveredDate` date
,`status` enum('Pending','Received','Cancelled')
,`notes` text
,`isActive` tinyint(1)
,`createdAt` timestamp
,`updatedAt` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_deliveries_today`
-- (See below for the actual view)
--
CREATE TABLE `view_deliveries_today` (
`supplyId` varchar(50)
,`supplierId` varchar(50)
,`productId` varchar(50)
,`batchNo` varchar(50)
,`quantity` int(11)
,`unitPrice` decimal(10,2)
,`totalAmount` decimal(10,2)
,`deliveryDate` date
,`deliveredDate` date
,`status` enum('Pending','Received','Cancelled')
,`notes` text
,`isActive` tinyint(1)
,`createdAt` timestamp
,`updatedAt` timestamp
,`isArchive` tinyint(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_expired`
-- (See below for the actual view)
--
CREATE TABLE `view_expired` (
`id` varchar(50)
,`brand` varchar(255)
,`genericName` varchar(255)
,`category` varchar(100)
,`stock` int(11)
,`expirationDate` date
,`supplier` varchar(255)
,`phoneNumber` varchar(20)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_expiring_soon`
-- (See below for the actual view)
--
CREATE TABLE `view_expiring_soon` (
`id` varchar(50)
,`brand` varchar(255)
,`genericName` varchar(255)
,`category` varchar(100)
,`stock` int(11)
,`expirationDate` date
,`daysToExpiry` int(7)
,`supplier` varchar(255)
,`phoneNumber` varchar(20)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_inventory_value`
-- (See below for the actual view)
--
CREATE TABLE `view_inventory_value` (
`category` varchar(100)
,`productCount` bigint(21)
,`totalStock` decimal(32,0)
,`totalValue` decimal(42,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_low_stock`
-- (See below for the actual view)
--
CREATE TABLE `view_low_stock` (
`id` varchar(50)
,`brand` varchar(255)
,`genericName` varchar(255)
,`category` varchar(100)
,`stock` int(11)
,`price` decimal(10,2)
,`supplier` varchar(255)
,`phoneNumber` varchar(20)
,`batchNo` varchar(50)
,`expirationDate` date
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_no_stock`
-- (See below for the actual view)
--
CREATE TABLE `view_no_stock` (
`id` varchar(50)
,`brand` varchar(255)
,`genericName` varchar(255)
,`category` varchar(100)
,`price` decimal(10,2)
,`supplier` varchar(255)
,`phoneNumber` varchar(20)
,`batchNo` varchar(50)
,`expirationDate` date
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_online_users`
-- (See below for the actual view)
--
CREATE TABLE `view_online_users` (
`accountId` varchar(50)
,`username` varchar(50)
,`fullName` varchar(201)
,`loginTime` timestamp
,`lastActivity` timestamp
,`ipAddress` varchar(45)
,`roles` mediumtext
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_pending_deliveries`
-- (See below for the actual view)
--
CREATE TABLE `view_pending_deliveries` (
`supplyId` varchar(50)
,`supplierId` varchar(50)
,`productId` varchar(50)
,`batchNo` varchar(50)
,`quantity` int(11)
,`unitPrice` decimal(10,2)
,`totalAmount` decimal(10,2)
,`deliveryDate` date
,`deliveredDate` date
,`status` enum('Pending','Received','Cancelled')
,`notes` text
,`isActive` tinyint(1)
,`createdAt` timestamp
,`updatedAt` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_pending_users`
-- (See below for the actual view)
--
CREATE TABLE `view_pending_users` (
`accountId` varchar(50)
,`firstName` varchar(100)
,`middleName` varchar(100)
,`lastName` varchar(100)
,`fullName` varchar(255)
,`phoneNumber` varchar(20)
,`email` varchar(255)
,`status` varchar(20)
,`username` varchar(50)
,`password` varchar(255)
,`image` varchar(255)
,`birthday` date
,`address` text
,`sex` enum('male','female','other')
,`isActive` tinyint(1)
,`createdAt` timestamp
,`updatedAt` timestamp
,`isArchive` tinyint(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_sales_details`
-- (See below for the actual view)
--
CREATE TABLE `view_sales_details` (
`transactionId` varchar(50)
,`date` date
,`productId` varchar(50)
,`brand` varchar(255)
,`genericName` varchar(255)
,`batchNo` varchar(50)
,`accountId` varchar(50)
,`employeeName` varchar(201)
,`quantitySold` int(11)
,`unitPrice` decimal(10,2)
,`totalAmount` decimal(10,2)
,`paymentMethod` enum('Cash','Card','E-Wallet')
,`createdAt` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_sales_today`
-- (See below for the actual view)
--
CREATE TABLE `view_sales_today` (
`totalTransactions` bigint(21)
,`totalItemsSold` decimal(32,0)
,`totalRevenue` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Structure for view `view_cancelled_deliveries`
--
DROP TABLE IF EXISTS `view_cancelled_deliveries`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_cancelled_deliveries`  AS SELECT `supply_deliveries`.`supplyId` AS `supplyId`, `supply_deliveries`.`supplierId` AS `supplierId`, `supply_deliveries`.`productId` AS `productId`, `supply_deliveries`.`batchNo` AS `batchNo`, `supply_deliveries`.`quantity` AS `quantity`, `supply_deliveries`.`unitPrice` AS `unitPrice`, `supply_deliveries`.`totalAmount` AS `totalAmount`, `supply_deliveries`.`deliveryDate` AS `deliveryDate`, `supply_deliveries`.`deliveredDate` AS `deliveredDate`, `supply_deliveries`.`status` AS `status`, `supply_deliveries`.`notes` AS `notes`, `supply_deliveries`.`isActive` AS `isActive`, `supply_deliveries`.`createdAt` AS `createdAt`, `supply_deliveries`.`updatedAt` AS `updatedAt` FROM `supply_deliveries` WHERE `supply_deliveries`.`status` = 'Cancelled' AND `supply_deliveries`.`isActive` = 1 ;

-- --------------------------------------------------------

--
-- Structure for view `view_delivered_deliveries`
--
DROP TABLE IF EXISTS `view_delivered_deliveries`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_delivered_deliveries`  AS SELECT `supply_deliveries`.`supplyId` AS `supplyId`, `supply_deliveries`.`supplierId` AS `supplierId`, `supply_deliveries`.`productId` AS `productId`, `supply_deliveries`.`batchNo` AS `batchNo`, `supply_deliveries`.`quantity` AS `quantity`, `supply_deliveries`.`unitPrice` AS `unitPrice`, `supply_deliveries`.`totalAmount` AS `totalAmount`, `supply_deliveries`.`deliveryDate` AS `deliveryDate`, `supply_deliveries`.`deliveredDate` AS `deliveredDate`, `supply_deliveries`.`status` AS `status`, `supply_deliveries`.`notes` AS `notes`, `supply_deliveries`.`isActive` AS `isActive`, `supply_deliveries`.`createdAt` AS `createdAt`, `supply_deliveries`.`updatedAt` AS `updatedAt` FROM `supply_deliveries` WHERE `supply_deliveries`.`status` = 'Received' AND `supply_deliveries`.`isActive` = 1 ;

-- --------------------------------------------------------

--
-- Structure for view `view_deliveries_today`
--
DROP TABLE IF EXISTS `view_deliveries_today`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_deliveries_today`  AS SELECT `supply_deliveries`.`supplyId` AS `supplyId`, `supply_deliveries`.`supplierId` AS `supplierId`, `supply_deliveries`.`productId` AS `productId`, `supply_deliveries`.`batchNo` AS `batchNo`, `supply_deliveries`.`quantity` AS `quantity`, `supply_deliveries`.`unitPrice` AS `unitPrice`, `supply_deliveries`.`totalAmount` AS `totalAmount`, `supply_deliveries`.`deliveryDate` AS `deliveryDate`, `supply_deliveries`.`deliveredDate` AS `deliveredDate`, `supply_deliveries`.`status` AS `status`, `supply_deliveries`.`notes` AS `notes`, `supply_deliveries`.`isActive` AS `isActive`, `supply_deliveries`.`createdAt` AS `createdAt`, `supply_deliveries`.`updatedAt` AS `updatedAt`, `supply_deliveries`.`isArchive` AS `isArchive` FROM `supply_deliveries` WHERE cast(`supply_deliveries`.`deliveryDate` as date) = curdate() AND `supply_deliveries`.`status` = 'Pending' ;

-- --------------------------------------------------------

--
-- Structure for view `view_expired`
--
DROP TABLE IF EXISTS `view_expired`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_expired`  AS SELECT `i`.`productId` AS `id`, `i`.`brand` AS `brand`, `i`.`genericName` AS `genericName`, `i`.`category` AS `category`, `i`.`stock` AS `stock`, `i`.`expirationDate` AS `expirationDate`, `s`.`name` AS `supplier`, `i`.`phoneNumber` AS `phoneNumber` FROM (`inventory` `i` left join `suppliers` `s` on(`i`.`supplierId` = `s`.`supplierId`)) WHERE `i`.`expirationDate` < curdate() AND `i`.`isActive` = 1 ORDER BY `i`.`expirationDate` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `view_expiring_soon`
--
DROP TABLE IF EXISTS `view_expiring_soon`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_expiring_soon`  AS SELECT `i`.`productId` AS `id`, `i`.`brand` AS `brand`, `i`.`genericName` AS `genericName`, `i`.`category` AS `category`, `i`.`stock` AS `stock`, `i`.`expirationDate` AS `expirationDate`, to_days(`i`.`expirationDate`) - to_days(curdate()) AS `daysToExpiry`, `s`.`name` AS `supplier`, `i`.`phoneNumber` AS `phoneNumber` FROM (`inventory` `i` left join `suppliers` `s` on(`i`.`supplierId` = `s`.`supplierId`)) WHERE to_days(`i`.`expirationDate`) - to_days(curdate()) <= (select `system_settings`.`settingValue` from `system_settings` where `system_settings`.`settingKey` = 'expiry_alert_days') AND to_days(`i`.`expirationDate`) - to_days(curdate()) >= 0 AND `i`.`isActive` = 1 ORDER BY `i`.`expirationDate` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `view_inventory_value`
--
DROP TABLE IF EXISTS `view_inventory_value`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_inventory_value`  AS SELECT `inventory`.`category` AS `category`, count(0) AS `productCount`, sum(`inventory`.`stock`) AS `totalStock`, sum(`inventory`.`stock` * `inventory`.`price`) AS `totalValue` FROM `inventory` WHERE `inventory`.`isActive` = 1 GROUP BY `inventory`.`category` ;

-- --------------------------------------------------------

--
-- Structure for view `view_low_stock`
--
DROP TABLE IF EXISTS `view_low_stock`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_low_stock`  AS SELECT `i`.`productId` AS `id`, `i`.`brand` AS `brand`, `i`.`genericName` AS `genericName`, `i`.`category` AS `category`, `i`.`stock` AS `stock`, `i`.`price` AS `price`, `s`.`name` AS `supplier`, `i`.`phoneNumber` AS `phoneNumber`, `i`.`batchNo` AS `batchNo`, `i`.`expirationDate` AS `expirationDate` FROM (`inventory` `i` left join `suppliers` `s` on(`i`.`supplierId` = `s`.`supplierId`)) WHERE `i`.`stock` < `i`.`lowStockThreshold` AND `i`.`stock` > 0 AND `i`.`isActive` = 1 ORDER BY `i`.`stock` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `view_no_stock`
--
DROP TABLE IF EXISTS `view_no_stock`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_no_stock`  AS SELECT `i`.`productId` AS `id`, `i`.`brand` AS `brand`, `i`.`genericName` AS `genericName`, `i`.`category` AS `category`, `i`.`price` AS `price`, `s`.`name` AS `supplier`, `i`.`phoneNumber` AS `phoneNumber`, `i`.`batchNo` AS `batchNo`, `i`.`expirationDate` AS `expirationDate` FROM (`inventory` `i` left join `suppliers` `s` on(`i`.`supplierId` = `s`.`supplierId`)) WHERE `i`.`stock` = 0 AND `i`.`isActive` = 1 ORDER BY `i`.`brand` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `view_online_users`
--
DROP TABLE IF EXISTS `view_online_users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_online_users`  AS SELECT `ou`.`accountId` AS `accountId`, `ou`.`username` AS `username`, concat(`a`.`firstName`,' ',`a`.`lastName`) AS `fullName`, `ou`.`loginTime` AS `loginTime`, `ou`.`lastActivity` AS `lastActivity`, `ou`.`ipAddress` AS `ipAddress`, group_concat(`r`.`roleName` separator ', ') AS `roles` FROM (((`online_users` `ou` join `accounts` `a` on(`ou`.`accountId` = `a`.`accountId`)) left join `user_roles` `ur` on(`ou`.`accountId` = `ur`.`accountId`)) left join `roles` `r` on(`ur`.`roleId` = `r`.`roleId`)) GROUP BY `ou`.`accountId`, `ou`.`username`, `a`.`firstName`, `a`.`lastName`, `ou`.`loginTime`, `ou`.`lastActivity`, `ou`.`ipAddress` ;

-- --------------------------------------------------------

--
-- Structure for view `view_pending_deliveries`
--
DROP TABLE IF EXISTS `view_pending_deliveries`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_pending_deliveries`  AS SELECT `supply_deliveries`.`supplyId` AS `supplyId`, `supply_deliveries`.`supplierId` AS `supplierId`, `supply_deliveries`.`productId` AS `productId`, `supply_deliveries`.`batchNo` AS `batchNo`, `supply_deliveries`.`quantity` AS `quantity`, `supply_deliveries`.`unitPrice` AS `unitPrice`, `supply_deliveries`.`totalAmount` AS `totalAmount`, `supply_deliveries`.`deliveryDate` AS `deliveryDate`, `supply_deliveries`.`deliveredDate` AS `deliveredDate`, `supply_deliveries`.`status` AS `status`, `supply_deliveries`.`notes` AS `notes`, `supply_deliveries`.`isActive` AS `isActive`, `supply_deliveries`.`createdAt` AS `createdAt`, `supply_deliveries`.`updatedAt` AS `updatedAt` FROM `supply_deliveries` WHERE `supply_deliveries`.`status` = 'Pending' AND `supply_deliveries`.`isActive` = 1 ;

-- --------------------------------------------------------

--
-- Structure for view `view_pending_users`
--
DROP TABLE IF EXISTS `view_pending_users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_pending_users`  AS SELECT `a`.`accountId` AS `accountId`, `a`.`firstName` AS `firstName`, `a`.`middleName` AS `middleName`, `a`.`lastName` AS `lastName`, `a`.`fullName` AS `fullName`, `a`.`phoneNumber` AS `phoneNumber`, `a`.`email` AS `email`, `a`.`status` AS `status`, `a`.`username` AS `username`, `a`.`password` AS `password`, `a`.`image` AS `image`, `a`.`birthday` AS `birthday`, `a`.`address` AS `address`, `a`.`sex` AS `sex`, `a`.`isActive` AS `isActive`, `a`.`createdAt` AS `createdAt`, `a`.`updatedAt` AS `updatedAt`, `a`.`isArchive` AS `isArchive` FROM ((`accounts` `a` join `user_roles` `ur` on(`a`.`accountId` = `ur`.`accountId`)) join `roles` `r` on(`ur`.`roleId` = `r`.`roleId`)) WHERE `r`.`roleName` = 'Pending User' ;

-- --------------------------------------------------------

--
-- Structure for view `view_sales_details`
--
DROP TABLE IF EXISTS `view_sales_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_sales_details`  AS SELECT `s`.`salesId` AS `transactionId`, `s`.`date` AS `date`, `s`.`productId` AS `productId`, `i`.`brand` AS `brand`, `i`.`genericName` AS `genericName`, `s`.`batchNo` AS `batchNo`, `s`.`accountId` AS `accountId`, concat(`a`.`firstName`,' ',`a`.`lastName`) AS `employeeName`, `s`.`quantitySold` AS `quantitySold`, `i`.`price` AS `unitPrice`, `s`.`totalAmount` AS `totalAmount`, `s`.`paymentMethod` AS `paymentMethod`, `s`.`createdAt` AS `createdAt` FROM ((`sales` `s` left join `inventory` `i` on(`s`.`productId` = `i`.`productId`)) left join `accounts` `a` on(`s`.`accountId` = `a`.`accountId`)) ORDER BY `s`.`createdAt` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `view_sales_today`
--
DROP TABLE IF EXISTS `view_sales_today`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_sales_today`  AS SELECT count(0) AS `totalTransactions`, sum(`sales`.`quantitySold`) AS `totalItemsSold`, sum(`sales`.`totalAmount`) AS `totalRevenue` FROM `sales` WHERE `sales`.`date` = curdate() ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`accountId`) USING BTREE,
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `unique_username` (`username`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`activityid`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_user` (`username`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_module` (`module`);

--
-- Indexes for table `archive_accounts`
--
ALTER TABLE `archive_accounts`
  ADD PRIMARY KEY (`accountId`);

--
-- Indexes for table `archive_deliveries`
--
ALTER TABLE `archive_deliveries`
  ADD PRIMARY KEY (`supplyId`);

--
-- Indexes for table `archive_inventory`
--
ALTER TABLE `archive_inventory`
  ADD PRIMARY KEY (`productId`) USING BTREE;

--
-- Indexes for table `archive_sales`
--
ALTER TABLE `archive_sales`
  ADD PRIMARY KEY (`salesId`);

--
-- Indexes for table `archive_suppliers`
--
ALTER TABLE `archive_suppliers`
  ADD PRIMARY KEY (`supplierId`);

--
-- Indexes for table `counters`
--
ALTER TABLE `counters`
  ADD PRIMARY KEY (`counterId`) USING BTREE,
  ADD UNIQUE KEY `unique_counter` (`counterType`,`counterDate`),
  ADD KEY `idx_type_date` (`counterType`,`counterDate`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`productId`) USING BTREE,
  ADD UNIQUE KEY `unique_batchNo` (`batchNo`),
  ADD KEY `idx_brand` (`brand`),
  ADD KEY `idx_generic` (`genericName`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_expiry` (`expirationDate`),
  ADD KEY `idx_stock` (`stock`),
  ADD KEY `idx_inventory_active_stock` (`isActive`,`stock`),
  ADD KEY `idx_inventory_active_expiry` (`isActive`,`expirationDate`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notificationId`),
  ADD KEY `accountId` (`accountId`);

--
-- Indexes for table `online_users`
--
ALTER TABLE `online_users`
  ADD PRIMARY KEY (`accountId`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `otp_request`
--
ALTER TABLE `otp_request`
  ADD KEY `otp_request_ibfk_1` (`accountId`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`permissionId`),
  ADD UNIQUE KEY `permissionName` (`permissionName`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`roleId`),
  ADD UNIQUE KEY `roleName` (`roleName`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`roleId`,`permissionId`),
  ADD KEY `permissionId` (`permissionId`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`salesId`) USING BTREE,
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_product` (`productId`),
  ADD KEY `idx_employee` (`accountId`),
  ADD KEY `idx_sales_date_product` (`date`,`productId`),
  ADD KEY `idx_sales_date_employee` (`date`,`accountId`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplierId`) USING BTREE,
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `supply_deliveries`
--
ALTER TABLE `supply_deliveries`
  ADD PRIMARY KEY (`supplyId`),
  ADD KEY `idx_supplier_delivery` (`supplierId`),
  ADD KEY `idx_product_delivery` (`productId`),
  ADD KEY `idx_delivery_date` (`deliveredDate`),
  ADD KEY `idx_delivery_status` (`status`),
  ADD KEY `idx_supply_deliveries_active` (`isActive`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `settingKey` (`settingKey`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`accountId`,`roleId`),
  ADD KEY `fk_user_roles_role` (`roleId`);

--
-- Indexes for table `user_schedule`
--
ALTER TABLE `user_schedule`
  ADD PRIMARY KEY (`scheduleId`),
  ADD KEY `accountId` (`accountId`);

--
-- Indexes for table `user_token`
--
ALTER TABLE `user_token`
  ADD KEY `user_token_ibfk_1` (`accountId`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `activityid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=513;

--
-- AUTO_INCREMENT for table `counters`
--
ALTER TABLE `counters`
  MODIFY `counterId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notificationId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `permissionId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `roleId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_schedule`
--
ALTER TABLE `user_schedule`
  MODIFY `scheduleId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`accountId`) REFERENCES `accounts` (`accountId`) ON DELETE CASCADE;

--
-- Constraints for table `online_users`
--
ALTER TABLE `online_users`
  ADD CONSTRAINT `online_users_ibfk_1` FOREIGN KEY (`username`) REFERENCES `accounts` (`username`) ON DELETE CASCADE,
  ADD CONSTRAINT `online_users_ibfk_2` FOREIGN KEY (`accountId`) REFERENCES `accounts` (`accountId`) ON DELETE CASCADE;

--
-- Constraints for table `otp_request`
--
ALTER TABLE `otp_request`
  ADD CONSTRAINT `otp_request_ibfk_1` FOREIGN KEY (`accountId`) REFERENCES `accounts` (`accountId`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`roleId`) REFERENCES `roles` (`roleId`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permissionId`) REFERENCES `permissions` (`permissionId`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`productId`) REFERENCES `inventory` (`productId`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`accountId`) REFERENCES `accounts` (`accountId`);

--
-- Constraints for table `supply_deliveries`
--
ALTER TABLE `supply_deliveries`
  ADD CONSTRAINT `supply_deliveries_ibfk_1` FOREIGN KEY (`supplierId`) REFERENCES `suppliers` (`supplierId`),
  ADD CONSTRAINT `supply_deliveries_ibfk_2` FOREIGN KEY (`productId`) REFERENCES `inventory` (`productId`);

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_user_roles_account` FOREIGN KEY (`accountId`) REFERENCES `accounts` (`accountId`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`roleId`) REFERENCES `roles` (`roleId`) ON DELETE CASCADE;

--
-- Constraints for table `user_schedule`
--
ALTER TABLE `user_schedule`
  ADD CONSTRAINT `user_schedule_ibfk_1` FOREIGN KEY (`accountId`) REFERENCES `accounts` (`accountId`) ON DELETE CASCADE;

--
-- Constraints for table `user_token`
--
ALTER TABLE `user_token`
  ADD CONSTRAINT `user_token_ibfk_1` FOREIGN KEY (`accountId`) REFERENCES `accounts` (`accountId`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
