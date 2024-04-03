/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_num` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postcode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` bigint NOT NULL,
  `latitude` decimal(9,6) DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  `type` int DEFAULT NULL,
  `sequence` int DEFAULT NULL,
  `modelable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelable_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `block_num` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `building` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `addresses_modelable_type_modelable_id_index` (`modelable_type`,`modelable_id`),
  KEY `addresses_type_index` (`type`),
  KEY `addresses_country_id_index` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `local_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `type` int DEFAULT NULL,
  `sequence` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `modelable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelable_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attachments_modelable_type_modelable_id_index` (`modelable_type`,`modelable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cashless_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cashless_providers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cashless_terminals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cashless_terminals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `begin_date` datetime DEFAULT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint NOT NULL,
  `cashless_provider_id` bigint NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `termination_date` datetime DEFAULT NULL,
  `updated_by` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_group_id` bigint DEFAULT NULL,
  `classname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sequence` int DEFAULT NULL,
  `type` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `categories_category_group_id_index` (`category_group_id`),
  KEY `categories_classname_index` (`classname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `category_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `classname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_groups_classname_index` (`classname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `alt_phone_num` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_phone_country_id` bigint DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modelable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelable_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_num` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_country_id` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contacts_modelable_type_modelable_id_index` (`modelable_type`,`modelable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_exponent` int NOT NULL DEFAULT '2',
  `is_currency_exponent_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `currency_symbol` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_code` int NOT NULL,
  `is_state` tinyint(1) NOT NULL DEFAULT '0',
  `sequence` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `timezone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `map_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `operator_id` bigint DEFAULT NULL,
  `person_id` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `category_id` bigint DEFAULT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_transaction_id` bigint DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `profile_id` bigint NOT NULL,
  `status_id` bigint NOT NULL,
  `zone_id` bigint DEFAULT NULL,
  `last_invoice_date` datetime DEFAULT NULL,
  `next_invoice_date` datetime DEFAULT NULL,
  `location_type_id` bigint unsigned DEFAULT NULL,
  `cms_invoice_history` json DEFAULT NULL,
  `account_manager_json` json DEFAULT NULL,
  `person_json` json DEFAULT NULL,
  `totals_json` json DEFAULT NULL,
  `virtual_customer_prefix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (json_unquote(json_extract(`person_json`,_utf8mb4'$.prefix'))) VIRTUAL,
  `virtual_customer_code` int GENERATED ALWAYS AS (json_unquote(json_extract(`person_json`,_utf8mb4'$.code'))) VIRTUAL,
  `begin_date` datetime DEFAULT NULL,
  `is_testing` tinyint(1) NOT NULL DEFAULT '0',
  `termination_date` datetime DEFAULT NULL,
  `snap_parameter_json` json DEFAULT NULL,
  `snap_vend_channels_json` json DEFAULT NULL,
  `snap_vend_channel_error_logs_json` json DEFAULT NULL,
  `snap_vend_status_json` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customers_location_type_id_index` (`location_type_id`),
  KEY `customers_category_id_index` (`category_id`),
  KEY `customers_code_index` (`code`),
  KEY `customers_created_at_index` (`created_at`),
  KEY `customers_is_active_index` (`is_active`),
  KEY `customers_person_id_index` (`person_id`),
  KEY `customers_operator_id_index` (`operator_id`),
  KEY `customers_virtual_customer_prefix_index` (`virtual_customer_prefix`),
  KEY `customers_virtual_customer_code_index` (`virtual_customer_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `qty_json` json NOT NULL,
  `amount` int NOT NULL DEFAULT '0',
  `product_id` bigint NOT NULL,
  `transaction_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_platform_campaign_item_vends`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_platform_campaign_item_vends` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_platform_campaign_id` bigint unsigned NOT NULL,
  `delivery_platform_campaign_item_id` bigint unsigned NOT NULL,
  `delivery_product_mapping_vend_id` bigint unsigned NOT NULL,
  `platform_ref_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_submitted` tinyint(1) NOT NULL DEFAULT '0',
  `submission_request_json` json DEFAULT NULL,
  `submission_response_json` json DEFAULT NULL,
  `datetime_from` datetime DEFAULT NULL,
  `datetime_to` datetime DEFAULT NULL,
  `settings_json` json DEFAULT NULL,
  `settings_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vend_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `del_plat_cam_item_id_del_prod_map_vend_id` (`delivery_product_mapping_vend_id`,`delivery_platform_campaign_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_platform_campaign_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_platform_campaign_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_platform_campaign_id` bigint unsigned NOT NULL,
  `items_json` json DEFAULT NULL,
  `settings_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings_json` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `scope` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_platform_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_platform_campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_platform_operator_id` bigint unsigned DEFAULT NULL,
  `delivery_product_mapping_id` bigint unsigned NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_platform_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_platform_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `amount` int DEFAULT NULL,
  `delivery_platform_id` bigint NOT NULL,
  `delivery_platform_operator_id` bigint NOT NULL,
  `error_json` json DEFAULT NULL,
  `order_completed_at` datetime DEFAULT NULL,
  `order_created_at` datetime DEFAULT NULL,
  `ref_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_json` json DEFAULT NULL,
  `request_history_json` json DEFAULT NULL,
  `response_history_json` json DEFAULT NULL,
  `status` int NOT NULL DEFAULT '1',
  `vend_channel_code` int NOT NULL,
  `vend_channel_id` bigint NOT NULL,
  `vend_code` int NOT NULL,
  `vend_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_platform_logs_delivery_platform_id_index` (`delivery_platform_id`),
  KEY `delivery_platform_logs_delivery_platform_operator_id_index` (`delivery_platform_operator_id`),
  KEY `delivery_platform_logs_status_index` (`status`),
  KEY `delivery_platform_logs_vend_channel_id_index` (`vend_channel_id`),
  KEY `delivery_platform_logs_vend_code_index` (`vend_code`),
  KEY `delivery_platform_logs_vend_id_index` (`vend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_platform_menu_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_platform_menu_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_platform_slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menu_json` json DEFAULT NULL,
  `platform_ref_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `vend_code` int DEFAULT NULL,
  `request_json` json DEFAULT NULL,
  `ref_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_platform_operators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_platform_operators` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_platform_id` bigint NOT NULL,
  `operator_id` bigint NOT NULL,
  `field1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field3` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field4` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'production',
  `endpoint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_platform_operators_delivery_platform_id_index` (`delivery_platform_id`),
  KEY `delivery_platform_operators_operator_id_index` (`operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_platform_order_complaints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_platform_order_complaints` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_platform_order_id` bigint unsigned DEFAULT NULL,
  `driver_phone_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `original_json` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_platform_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_platform_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `amount` int NOT NULL DEFAULT '0',
  `delivery_platform_order_id` bigint NOT NULL,
  `is_cancelled` tinyint(1) NOT NULL DEFAULT '0',
  `is_edited` tinyint(1) NOT NULL DEFAULT '0',
  `product_id` bigint NOT NULL,
  `product_json` json DEFAULT NULL,
  `delivery_product_mapping_item_id` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `qty` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_platform_order_items_delivery_platform_order_id_index` (`delivery_platform_order_id`),
  KEY `delivery_platform_order_items_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_platform_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_platform_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_platform_id` bigint NOT NULL,
  `delivery_platform_operator_id` bigint NOT NULL,
  `driver_phone_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_json` json DEFAULT NULL,
  `is_cancelled` tinyint(1) NOT NULL DEFAULT '0',
  `is_edited` tinyint(1) NOT NULL DEFAULT '0',
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `order_created_at` datetime DEFAULT NULL,
  `order_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_json` json DEFAULT NULL,
  `product_mapping_id` bigint DEFAULT NULL,
  `request_history_json` json DEFAULT NULL,
  `response_history_json` json DEFAULT NULL,
  `short_order_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` int NOT NULL DEFAULT '1',
  `status_json` json DEFAULT NULL,
  `total_amount` int DEFAULT NULL,
  `vend_code` int NOT NULL,
  `vend_json` json DEFAULT NULL,
  `campaign_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `platform_ref_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivery_product_mapping_vend_id` bigint unsigned DEFAULT NULL,
  `subtotal_amount` int DEFAULT NULL,
  `promo_amount` int NOT NULL DEFAULT '0',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `vend_transaction_order_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vend_transaction_id` bigint unsigned DEFAULT NULL,
  `driver_request_json` json DEFAULT NULL,
  `virtual_campaign_id_json` json GENERATED ALWAYS AS (json_unquote(json_extract(`campaign_json`,_utf8mb4'$[*].id'))) VIRTUAL,
  `collected_datetime` datetime DEFAULT NULL,
  `delivered_datetime` datetime DEFAULT NULL,
  `last_mile_timediff_mins` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_platform_orders_delivery_platform_id_index` (`delivery_platform_id`),
  KEY `delivery_platform_orders_delivery_platform_operator_id_index` (`delivery_platform_operator_id`),
  KEY `delivery_platform_orders_order_id_index` (`order_id`),
  KEY `delivery_platform_orders_product_mapping_id_index` (`product_mapping_id`),
  KEY `delivery_platform_orders_short_order_id_index` (`short_order_id`),
  KEY `delivery_platform_orders_status_index` (`status`),
  KEY `delivery_platform_orders_vend_code_index` (`vend_code`),
  KEY `delivery_platform_orders_delivery_product_mapping_vend_id_index` (`delivery_product_mapping_vend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_platforms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_platforms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `country_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `field1_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field2_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field3_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field4_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `default_scopes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_access_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_granted_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_platforms_country_id_index` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_product_mapping_bulk_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_product_mapping_bulk_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_product_mapping_bulk_id` bigint NOT NULL,
  `delivery_product_mapping_item_id` bigint NOT NULL,
  `qty` int NOT NULL DEFAULT '1',
  `sub_category_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `del_pro_map_bulk_id` (`delivery_product_mapping_bulk_id`),
  KEY `del_pro_map_item_id` (`delivery_product_mapping_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_product_mapping_bulks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_product_mapping_bulks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `amount` int NOT NULL DEFAULT '0',
  `delivery_product_mapping_id` bigint NOT NULL,
  `group_json` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `promo_value` int NOT NULL DEFAULT '0',
  `promo_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `promo_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `promo_desc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_qty` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `delivery_platform_campaign_id` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `del_pro_map_id` (`delivery_product_mapping_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_product_mapping_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_product_mapping_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `amount` int NOT NULL DEFAULT '0',
  `delivery_product_mapping_id` bigint NOT NULL,
  `product_mapping_id` bigint NOT NULL,
  `product_mapping_item_id` bigint unsigned DEFAULT NULL,
  `channel_code` int NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sub_category_json` json DEFAULT NULL,
  `product_id` bigint NOT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_product_mapping_items_delivery_product_mapping_id_index` (`delivery_product_mapping_id`),
  KEY `delivery_product_mapping_items_product_mapping_id_index` (`product_mapping_id`),
  KEY `delivery_product_mapping_items_product_mapping_item_id_index` (`product_mapping_item_id`),
  KEY `delivery_product_mapping_items_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_product_mapping_vend`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_product_mapping_vend` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_product_mapping_id` bigint NOT NULL,
  `vend_id` bigint NOT NULL,
  `vend_code` int DEFAULT NULL,
  `platform_ref_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `end_date` datetime DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `delivery_product_mapping_vend_channels_json` json DEFAULT NULL,
  `last_menu_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_id` (`delivery_product_mapping_id`),
  KEY `delivery_product_mapping_vend_is_active_index` (`is_active`),
  KEY `delivery_product_mapping_vend_platform_ref_id_index` (`platform_ref_id`),
  KEY `delivery_product_mapping_vend_vend_code_index` (`vend_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_product_mapping_vend_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_product_mapping_vend_channels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `amount` int NOT NULL DEFAULT '0',
  `order_qty` int NOT NULL DEFAULT '0',
  `order_qty_json` json DEFAULT NULL,
  `delivery_product_mapping_vend_id` bigint unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `qty` int DEFAULT NULL,
  `reserved_percent` int NOT NULL DEFAULT '0',
  `reserved_qty` int NOT NULL DEFAULT '0',
  `vend_channel_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vend_channel_id` bigint unsigned NOT NULL,
  `vend_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vend_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `delivery_product_mapping_id` bigint unsigned DEFAULT NULL,
  `delivery_product_mapping_item_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_product_mapping_vend_channels_vend_channel_id_index` (`vend_channel_id`),
  KEY `delivery_product_mapping_vend_channels_vend_id_index` (`vend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_product_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_product_mappings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_platform_operator_id` bigint NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_mapping_id` bigint NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `status` int NOT NULL DEFAULT '1',
  `delivery_product_mapping_items_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `category_json` json DEFAULT NULL,
  `operator_id` bigint NOT NULL,
  `reserved_percent` int NOT NULL DEFAULT '0',
  `reserved_qty` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `delivery_product_mappings_delivery_platform_operator_id_index` (`delivery_platform_operator_id`),
  KEY `delivery_product_mappings_product_mapping_id_index` (`product_mapping_id`),
  KEY `delivery_product_mappings_operator_id_index` (`operator_id`),
  KEY `delivery_product_mappings_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exchange_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exchange_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `base_country_id` bigint NOT NULL,
  `quote_country_id` bigint NOT NULL,
  `rate` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `external_oauth_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `external_oauth_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `access_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `client_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_secret` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expired_at` timestamp NULL DEFAULT NULL,
  `modelable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelable_id` bigint unsigned NOT NULL,
  `scopes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `granted_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `external_oauth_tokens_modelable_type_modelable_id_index` (`modelable_type`,`modelable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `holidays` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `labels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `labels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint DEFAULT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `operator_id` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `location_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `location_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sequence` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `weightage` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `location_types_sequence_index` (`sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `value1` json DEFAULT NULL,
  `value2` json DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `maintenances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `maintenances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc_before` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `desc_after` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `transaction_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `maps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `maps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `months`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `months` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` int NOT NULL,
  `short_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_access_tokens` (
  `id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scopes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_access_tokens_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_auth_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_auth_codes` (
  `id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `scopes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_auth_codes_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redirect` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `personal_access_client` tinyint(1) NOT NULL,
  `password_client` tinyint(1) NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_clients_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_personal_access_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_personal_access_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_refresh_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_refresh_tokens` (
  `id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_token_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_refresh_tokens_access_token_id_index` (`access_token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `operator_payment_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operator_payment_gateways` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `operator_id` bigint NOT NULL,
  `payment_gateway_id` bigint NOT NULL,
  `key1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `key2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sandbox',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `key3` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `operator_payment_gateways_operator_id_index` (`operator_id`),
  KEY `operator_payment_gateways_payment_gateway_id_index` (`payment_gateway_id`),
  KEY `operator_payment_gateways_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `operator_vend`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operator_vend` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `operator_id` bigint NOT NULL,
  `vend_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `operator_vend_operator_id_vend_id_unique` (`operator_id`,`vend_id`),
  KEY `operator_vend_operator_id_vend_id_index` (`operator_id`,`vend_id`),
  KEY `operator_vend_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `operators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operators` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` bigint NOT NULL,
  `created_by` bigint NOT NULL,
  `deactivated_at` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `profile_id` bigint DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `timezone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asia/Singapore',
  `updated_by` bigint DEFAULT NULL,
  `gst_vat_rate` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `operators_country_id_index` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_item_vend_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_item_vend_channels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_platform_order_id` bigint unsigned NOT NULL,
  `delivery_platform_order_item_id` bigint unsigned NOT NULL,
  `delivery_product_mapping_item_id` bigint unsigned DEFAULT NULL,
  `delivery_product_mapping_vend_channel_id` bigint unsigned NOT NULL,
  `vend_channel_id` bigint unsigned NOT NULL,
  `vend_channel_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `qty` int NOT NULL DEFAULT '1',
  `amount` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_gateway_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_gateway_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vend_channel_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request` json DEFAULT NULL,
  `response` json DEFAULT NULL,
  `request_history_json` json DEFAULT NULL,
  `history_json` json DEFAULT NULL,
  `error_json` json DEFAULT NULL,
  `order_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vend_transaction_id` bigint NOT NULL,
  `operator_payment_gateway_id` bigint NOT NULL,
  `amount` decimal(8,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `payment_gateway_id` bigint DEFAULT NULL,
  `status` int DEFAULT NULL,
  `qr_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `vend_id` bigint DEFAULT NULL,
  `vend_channel_id` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_gateway_logs_order_id_index` (`order_id`),
  KEY `payment_gateway_logs_operator_payment_gateway_id_index` (`operator_payment_gateway_id`),
  KEY `payment_gateway_logs_payment_gateway_id_index` (`payment_gateway_id`),
  KEY `payment_gateway_logs_vend_transaction_id_index` (`vend_transaction_id`),
  KEY `payment_gateway_logs_vend_id_index` (`vend_id`),
  KEY `payment_gateway_logs_vend_channel_id_index` (`vend_channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_gateways` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_payment_method_id` bigint DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `country_id` bigint NOT NULL DEFAULT '1',
  `key1_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `key2_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `key3_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_gateways_country_id_index` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_merchants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_merchants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mask_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_methods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_gateway_id` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `payment_merchant_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_apk_constant` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `payment_methods_code_index` (`code`),
  KEY `payment_methods_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `price_template_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `price_template_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint NOT NULL,
  `price_template_id` bigint NOT NULL,
  `retail_price` int DEFAULT '0',
  `quote_price` int NOT NULL DEFAULT '0',
  `sequence` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `price_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `price_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_mapping_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_mapping_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `channel_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` bigint NOT NULL,
  `product_mapping_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_mapping_items_product_id_index` (`product_id`),
  KEY `product_mapping_items_product_mapping_id_index` (`product_mapping_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_mappings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `operator_id` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `product_mapping_items_json` json DEFAULT NULL,
  `vends_json` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_mappings_operator_id_index` (`operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_movement_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_movement_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_movement_id` bigint NOT NULL,
  `product_id` bigint NOT NULL,
  `qty_json` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` datetime NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_uoms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_uoms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint NOT NULL,
  `uom_id` bigint NOT NULL,
  `is_base_uom` tinyint(1) NOT NULL DEFAULT '0',
  `is_transaction_uom` tinyint(1) NOT NULL DEFAULT '0',
  `value` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_uoms_is_base_uom_index` (`is_base_uom`),
  KEY `product_uoms_is_transaction_uom_index` (`is_transaction_uom`),
  KEY `product_uoms_product_id_index` (`product_id`),
  KEY `product_uoms_uom_id_index` (`uom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_inventory` tinyint(1) NOT NULL DEFAULT '1',
  `measurement_value` int unsigned DEFAULT NULL,
  `measurement_unit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `measurement_count` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `category_id` bigint DEFAULT NULL,
  `category_group_id` bigint DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_commission` tinyint(1) NOT NULL DEFAULT '0',
  `is_supermarket_fee` tinyint(1) NOT NULL DEFAULT '0',
  `barcode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator_id` bigint NOT NULL DEFAULT '1',
  `cms_refer_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `products_is_inventory_index` (`is_inventory`),
  KEY `products_code_index` (`code`),
  KEY `products_category_id_index` (`category_id`),
  KEY `products_category_group_id_index` (`category_group_id`),
  KEY `products_operator_id_index` (`operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `profile_taxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_taxes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` bigint NOT NULL,
  `sequence` int DEFAULT NULL,
  `tax_id` bigint NOT NULL,
  `is_inclusive` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alias` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uen` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_currency_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `profiles_base_currency_id_index` (`base_currency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_centers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_centers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sequence` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `simcards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `simcards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `begin_date` datetime DEFAULT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `phone_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telco_id` bigint NOT NULL,
  `termination_date` datetime DEFAULT NULL,
  `updated_by` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `statuses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `classname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sequence` int DEFAULT NULL,
  `type` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `statuses_classname_index` (`classname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tag_bindings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tag_bindings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint NOT NULL,
  `tag_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `taxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `taxes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rate` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telcos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telcos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `temp_vend_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `temp_vend_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ref_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vend_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_datetime` datetime DEFAULT NULL,
  `transaction_datetime_ref` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `channel_code` int NOT NULL,
  `ref_payment_method_id` int NOT NULL,
  `amount` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint DEFAULT NULL,
  `customer_id` bigint NOT NULL,
  `deals_obj` json DEFAULT NULL,
  `delivery_date` datetime NOT NULL,
  `delivered_by` bigint DEFAULT NULL,
  `handled_by` bigint DEFAULT NULL,
  `inner_remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `order_date` datetime NOT NULL,
  `pay_method_id` bigint DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `po_num` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `subtotal_amount` int NOT NULL DEFAULT '0',
  `total_amount` int NOT NULL DEFAULT '0',
  `total_qty` decimal(15,4) DEFAULT NULL,
  `updated_by` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `unit_costs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unit_costs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cost` int NOT NULL DEFAULT '0',
  `date_from` datetime NOT NULL,
  `date_to` datetime DEFAULT NULL,
  `product_id` bigint NOT NULL,
  `profile_id` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `unit_costs_created_at_index` (`created_at`),
  KEY `unit_costs_date_from_index` (`date_from`),
  KEY `unit_costs_is_current_index` (`is_current`),
  KEY `unit_costs_product_id_index` (`product_id`),
  KEY `unit_costs_profile_id_index` (`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `uoms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `uoms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sequence` int DEFAULT NULL,
  `color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_vend`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_vend` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `vend_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_vend_user_id_vend_id_index` (`user_id`,`vend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `profile_id` bigint DEFAULT NULL,
  `operator_id` bigint DEFAULT '1',
  `access_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_profile_id_index` (`profile_id`),
  KEY `users_operator_id_index` (`operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_bindings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_bindings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `begin_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `termination_date` datetime DEFAULT NULL,
  `vend_id` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `customer_id` bigint NOT NULL,
  `snap_parameter_json` json DEFAULT NULL,
  `snap_vend_channels_json` json DEFAULT NULL,
  `snap_vend_channel_error_logs_json` json DEFAULT NULL,
  `snap_vend_status_json` json DEFAULT NULL,
  `totals_json` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_bindings_vend_id_index` (`vend_id`),
  KEY `vend_bindings_is_active_index` (`is_active`),
  KEY `vend_bindings_customer_id_index` (`customer_id`),
  KEY `vend_bindings_begin_date_index` (`begin_date`),
  KEY `vend_bindings_customer_id_vend_id_index` (`customer_id`,`vend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_channel_error_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_channel_error_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_channel_id` bigint NOT NULL,
  `vend_channel_error_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_error_cleared` tinyint(1) NOT NULL DEFAULT '0',
  `vend_transaction_id` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_channel_error_logs_created_at_index` (`created_at`),
  KEY `vend_channel_error_logs_is_error_cleared_index` (`is_error_cleared`),
  KEY `vend_channel_error_logs_vend_channel_id_index` (`vend_channel_id`),
  KEY `vend_channel_error_logs_vend_channel_error_id_index` (`vend_channel_error_id`),
  KEY `vend_channel_error_logs_vend_transaction_id_index` (`vend_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_channel_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_channel_errors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` int NOT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `weightage` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_channels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` int NOT NULL,
  `qty` int NOT NULL DEFAULT '0',
  `capacity` int NOT NULL DEFAULT '0',
  `amount` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `vend_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `product_id` bigint DEFAULT NULL,
  `amount2` int NOT NULL DEFAULT '0',
  `discount_group` int DEFAULT NULL,
  `locked_qty` int NOT NULL DEFAULT '0',
  `sku_code` int DEFAULT NULL,
  `qty_sold_at` datetime DEFAULT NULL,
  `qty_restocked_at` datetime DEFAULT NULL,
  `qty_not_available_duration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_channels_code_index` (`code`),
  KEY `vend_channels_is_active_index` (`is_active`),
  KEY `vend_channels_vend_id_index` (`vend_id`),
  KEY `vend_channels_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_criteria_bindings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_criteria_bindings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_criteria_id` bigint NOT NULL,
  `vend_sub_criteria_id` bigint unsigned DEFAULT NULL,
  `vend_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_criteria_bindings_vend_criteria_id_index` (`vend_criteria_id`),
  KEY `vend_criteria_bindings_vend_id_index` (`vend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_criterias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_criterias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `classname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_sub_criteria` tinyint(1) NOT NULL DEFAULT '0',
  `operator` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `options_json` json DEFAULT NULL,
  `sequence` int DEFAULT NULL,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weightage` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `value` json DEFAULT NULL,
  `ip_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processed` json DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vend_code` int DEFAULT NULL,
  `is_keep` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_fans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_fans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_id` bigint NOT NULL,
  `value` int NOT NULL,
  `type` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_fans_vend_id_index` (`vend_id`),
  KEY `vend_fans_type_index` (`type`),
  KEY `vend_fans_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_package_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_package_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_packages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint DEFAULT NULL,
  `customer_json` json DEFAULT NULL,
  `date` datetime NOT NULL,
  `day` int NOT NULL,
  `failure_amount` int NOT NULL DEFAULT '0',
  `failure_count` int NOT NULL DEFAULT '0',
  `month` int NOT NULL,
  `monthname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator_id` bigint DEFAULT NULL,
  `total_amount` int NOT NULL DEFAULT '0',
  `total_count` int NOT NULL DEFAULT '0',
  `vend_code` int NOT NULL,
  `vend_id` bigint NOT NULL,
  `year` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `online_failure_amount` int NOT NULL DEFAULT '0',
  `online_failure_count` int NOT NULL DEFAULT '0',
  `online_success_amount` int NOT NULL DEFAULT '0',
  `online_success_count` int NOT NULL DEFAULT '0',
  `revenue` int NOT NULL DEFAULT '0',
  `gross_profit` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `vend_records_customer_id_index` (`customer_id`),
  KEY `vend_records_date_index` (`date`),
  KEY `vend_records_operator_id_index` (`operator_id`),
  KEY `vend_records_vend_id_index` (`vend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint DEFAULT NULL,
  `customer_json` json DEFAULT NULL,
  `operator_id` bigint DEFAULT NULL,
  `parameter_json` json DEFAULT NULL,
  `vend_id` bigint NOT NULL,
  `vend_code` int NOT NULL,
  `vend_channels_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_sub_criterias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_sub_criterias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `options_json` json DEFAULT NULL,
  `sequence` int DEFAULT NULL,
  `weightage` int DEFAULT NULL,
  `vend_criteria_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_temps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_temps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_id` bigint NOT NULL,
  `value` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_keep` tinyint(1) NOT NULL DEFAULT '0',
  `type` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `vend_temps_value_index` (`value`),
  KEY `vend_temps_vend_id_index` (`vend_id`),
  KEY `vend_temps_type_index` (`type`),
  KEY `vend_temps_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_transaction_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_transaction_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `is_refunded` tinyint(1) NOT NULL DEFAULT '0',
  `product_id` bigint unsigned DEFAULT NULL,
  `product_json` json DEFAULT NULL,
  `unit_cost` int DEFAULT NULL,
  `unit_cost_id` bigint unsigned DEFAULT NULL,
  `vend_channel_id` bigint unsigned NOT NULL,
  `vend_channel_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vend_channel_error_id` bigint unsigned DEFAULT NULL,
  `vend_transaction_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `vend_channel_error_json` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_transaction_items_vend_transaction_id_index` (`vend_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_transaction_items_bk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_transaction_items_bk` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `is_refunded` tinyint(1) NOT NULL DEFAULT '0',
  `product_id` bigint unsigned DEFAULT NULL,
  `product_json` json DEFAULT NULL,
  `unit_cost` int DEFAULT NULL,
  `unit_cost_id` bigint unsigned DEFAULT NULL,
  `vend_channel_id` bigint unsigned NOT NULL,
  `vend_channel_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vend_channel_error_id` bigint unsigned DEFAULT NULL,
  `vend_transaction_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_binding_id` bigint DEFAULT NULL,
  `order_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_datetime` datetime NOT NULL,
  `amount` int NOT NULL DEFAULT '0',
  `customer_id` bigint DEFAULT NULL,
  `operator_id` bigint DEFAULT NULL,
  `payment_method_id` bigint DEFAULT NULL,
  `vend_channel_id` bigint NOT NULL,
  `vend_channel_error_id` bigint DEFAULT NULL,
  `vend_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `vend_transaction_json` json DEFAULT NULL,
  `product_id` bigint DEFAULT NULL,
  `unit_cost_id` bigint DEFAULT NULL,
  `customer_json` json DEFAULT NULL,
  `location_type_json` json DEFAULT NULL,
  `operator_json` json DEFAULT NULL,
  `product_json` json DEFAULT NULL,
  `gst_vat_rate` int NOT NULL DEFAULT '0',
  `is_payment_received` tinyint(1) DEFAULT NULL,
  `vend_json` json DEFAULT NULL,
  `revenue` int DEFAULT NULL,
  `gross_profit` int DEFAULT NULL,
  `gross_profit_margin` int DEFAULT NULL,
  `unit_cost` int DEFAULT NULL,
  `vend_channel_code` int DEFAULT NULL,
  `is_refunded` tinyint(1) NOT NULL DEFAULT '0',
  `payment_gateway_log_id` bigint unsigned DEFAULT NULL,
  `error_code_normalized` int GENERATED ALWAYS AS (json_unquote(json_extract(`vend_transaction_json`,_utf8mb4'$.SErr'))) VIRTUAL,
  `is_multiple` tinyint(1) NOT NULL DEFAULT '0',
  `items_json` json DEFAULT NULL,
  `vend_transaction_items_json` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_transactions_transaction_datetime_index` (`transaction_datetime`),
  KEY `vend_transactions_vend_id_index` (`vend_id`),
  KEY `vend_transactions_order_id_index` (`order_id`),
  KEY `vend_transactions_created_at_index` (`created_at`),
  KEY `vend_transactions_product_id_index` (`product_id`),
  KEY `vend_transactions_payment_method_id_index` (`payment_method_id`),
  KEY `vend_transactions_vend_channel_code_index` (`vend_channel_code`),
  KEY `vend_transactions_amount_index` (`amount`),
  KEY `vend_transactions_customer_id_index` (`customer_id`),
  KEY `vend_transactions_error_code_normalized_index` (`error_code_normalized`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_transactions_bk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_transactions_bk` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_datetime` datetime NOT NULL,
  `amount` int NOT NULL DEFAULT '0',
  `customer_id` bigint DEFAULT NULL,
  `operator_id` bigint DEFAULT NULL,
  `payment_method_id` bigint DEFAULT NULL,
  `vend_channel_id` bigint NOT NULL,
  `vend_channel_error_id` bigint DEFAULT NULL,
  `vend_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `vend_transaction_json` json DEFAULT NULL,
  `product_id` bigint DEFAULT NULL,
  `unit_cost_id` bigint DEFAULT NULL,
  `customer_json` json DEFAULT NULL,
  `location_type_json` json DEFAULT NULL,
  `operator_json` json DEFAULT NULL,
  `product_json` json DEFAULT NULL,
  `gst_vat_rate` int NOT NULL DEFAULT '0',
  `is_payment_received` tinyint(1) DEFAULT NULL,
  `vend_json` json DEFAULT NULL,
  `revenue` int DEFAULT NULL,
  `gross_profit` int DEFAULT NULL,
  `gross_profit_margin` int DEFAULT NULL,
  `unit_cost` int DEFAULT NULL,
  `vend_channel_code` int DEFAULT NULL,
  `is_refunded` tinyint(1) NOT NULL DEFAULT '0',
  `payment_gateway_log_id` bigint unsigned DEFAULT NULL,
  `is_multiple` tinyint(1) NOT NULL DEFAULT '0',
  `items_json` json DEFAULT NULL,
  `vend_transaction_items_json` json DEFAULT NULL,
  `error_code_normalized` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_transactions_vend_id_index` (`vend_id`),
  KEY `vend_transactions_created_at_index` (`created_at`),
  KEY `vend_transactions_operator_id_index` (`operator_id`),
  KEY `vend_transactions_customer_id_index` (`customer_id`),
  KEY `vend_transactions_amount_index` (`amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sequence` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vends`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vends` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` int NOT NULL,
  `serial_num` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temp` int DEFAULT NULL,
  `temp_updated_at` datetime DEFAULT NULL,
  `last_updated_at` datetime DEFAULT NULL,
  `coin_amount` int DEFAULT NULL,
  `firmware_ver` int DEFAULT NULL,
  `is_door_open` tinyint(1) NOT NULL DEFAULT '0',
  `is_sensor_normal` tinyint(1) NOT NULL DEFAULT '0',
  `simcard_id` bigint DEFAULT NULL,
  `cashless_terminal_id` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_temp_error` tinyint(1) NOT NULL DEFAULT '0',
  `vend_type_id` bigint DEFAULT NULL,
  `keylock_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vend_channels_json` json DEFAULT NULL,
  `vend_channel_error_logs_json` json DEFAULT NULL,
  `vend_channel_totals_json` json DEFAULT NULL,
  `parameter_json` json DEFAULT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT '0',
  `is_offline_notification_sent` tinyint(1) NOT NULL DEFAULT '0',
  `vend_transaction_totals_json` json DEFAULT NULL,
  `virtual_vend_records_thirty_days_amount_average` int GENERATED ALWAYS AS (json_unquote(json_extract(`vend_transaction_totals_json`,_utf8mb4'$.vend_records_thirty_days_amount_average'))) VIRTUAL,
  `product_mapping_id` bigint DEFAULT NULL,
  `apk_ver_json` json DEFAULT NULL,
  `private_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `out_of_stock_sku_percent` int GENERATED ALWAYS AS (json_unquote(json_extract(`vend_channel_totals_json`,_utf8mb4'$.outOfStockSkuPercent'))) VIRTUAL,
  `balance_percent` int GENERATED ALWAYS AS (json_unquote(json_extract(`vend_channel_totals_json`,_utf8mb4'$.balancePercent'))) VIRTUAL,
  `vend_criteria_weightage_json` json DEFAULT NULL,
  `vend_criteria_score_json` json DEFAULT NULL,
  `is_mqtt` tinyint(1) NOT NULL DEFAULT '0',
  `begin_date` datetime DEFAULT NULL,
  `termination_date` datetime DEFAULT NULL,
  `amount_average_day` int GENERATED ALWAYS AS (json_unquote(json_extract(`vend_transaction_totals_json`,_utf8mb4'$.vend_records_amount_average_day'))) VIRTUAL,
  `mqtt_updated_at` datetime DEFAULT NULL,
  `is_customer` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `virtual_firmware_ver` int GENERATED ALWAYS AS (conv(json_unquote(json_extract(`parameter_json`,_utf8mb4'$.Ver')),10,16)) VIRTUAL,
  `acb_vmc_pa_json` json DEFAULT NULL,
  `acb_status_json` json DEFAULT NULL,
  `statistics1_json` json DEFAULT NULL,
  `is_testing` tinyint(1) NOT NULL DEFAULT '0',
  `last_ip_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mqtt_last_updated_at` datetime DEFAULT NULL,
  `is_mqtt_offline_notified` tinyint(1) NOT NULL DEFAULT '0',
  `is_mqtt_active` tinyint(1) NOT NULL DEFAULT '0',
  `customer_id` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vends_code_index` (`code`),
  KEY `vends_product_mapping_id_index` (`product_mapping_id`),
  KEY `vends_begin_date_index` (`begin_date`),
  KEY `vends_termination_date_index` (`termination_date`),
  KEY `vends_is_customer_index` (`is_customer`),
  KEY `vends_is_testing_index` (`is_testing`),
  KEY `vends_customer_id_index` (`customer_id`),
  KEY `vends_out_of_stock_sku_percent_index` (`out_of_stock_sku_percent`),
  KEY `vends_amount_average_day_index` (`amount_average_day`),
  KEY `vends_balance_percent_index` (`balance_percent`),
  KEY `vends_virtual_firmware_ver_index` (`virtual_firmware_ver`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `voucher_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `voucher_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `voucher_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `qty` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `voucher_items_voucher_id_index` (`voucher_id`),
  KEY `voucher_items_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vouchers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `date_from` datetime NOT NULL,
  `date_to` datetime NOT NULL,
  `response_json` json DEFAULT NULL,
  `status` int NOT NULL DEFAULT '1',
  `vend_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vouchers_code_unique` (`code`),
  KEY `vouchers_customer_id_index` (`customer_id`),
  KEY `vouchers_vend_id_index` (`vend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sequence` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2014_10_12_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2014_10_12_100000_create_password_resets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2019_08_19_000000_create_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2019_12_14_000001_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2022_07_30_043348_create_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2022_07_30_044104_create_attachments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2022_07_30_074330_create_payment_methods_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2022_07_30_093715_create_vends_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2022_07_30_093736_create_vend_channels_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2022_07_30_093750_create_vend_channel_errors_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2022_07_30_093803_create_vend_channel_error_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2022_07_30_093852_create_vend_temps_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2022_07_30_093908_create_vend_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2022_07_30_095206_create_vend_data_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2022_08_02_162341_create_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2022_08_08_121434_create_addresses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2022_08_08_122103_create_tags_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2022_08_11_115320_add_is_temp_error_vends',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2022_08_12_153824_add_is_error_cleared_vend_channel_error_logs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2022_08_15_180243_create_customers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2022_08_15_181212_create_profiles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2022_08_15_181650_create_countries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2022_09_09_181408_create_category_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2022_09_09_181545_create_contacts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2022_09_09_181852_create_payment_terms_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2022_09_09_182417_create_price_templates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2022_09_09_182432_create_price_template_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2022_09_09_183125_create_statuses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2022_09_09_183644_create_tag_bindings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2022_09_09_183912_create_zones_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2022_09_09_184155_create_deals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2022_09_09_184308_create_banks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2022_09_09_184701_create_maintenances_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2022_09_09_184813_create_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2022_09_09_190031_create_product_movements_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2022_09_09_190108_create_product_movement_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2022_09_09_190143_create_taxes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2022_09_09_190200_create_profile_taxes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2022_09_09_190933_create_unit_costs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2022_09_09_192032_create_uoms_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2022_09_24_143712_alter_payment_method_id_vend_transaction',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2022_09_24_214435_alter_index_transaction_datetime_vend_transactions',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2022_09_24_214549_alter_index_code_vends',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2022_09_24_214820_alter_index_code_vend_channels',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2022_09_24_214856_alter_index_created_at_vend_channel_error_logs',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2022_09_26_142222_add_addresses',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2022_09_30_230829_alter_code_nullable_payment_methods',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2022_10_04_124030_create_vend_bindings_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2022_10_04_124708_create_vend_types_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2022_10_04_125432_add_vend_type_id_vends',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2022_10_04_132050_create_vend_packages_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2022_10_04_132120_create_vend_package_items_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2022_10_04_133213_create_simcards_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2022_10_04_133233_create_cashless_terminals_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2022_10_04_133945_create_telcos_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2022_10_04_135126_create_cashless_providers_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2022_10_04_144022_add_customers',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2022_10_05_130431_create_exchange_rates_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2022_10_05_133617_add_profile_id_users',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2022_10_06_141548_add_category_id_products',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2022_10_06_143712_add_is_active_products',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2022_10_06_200038_add_is_commission_is_supermarket_fee_products',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2022_10_06_200620_add_barcode_products',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2022_10_06_201209_add_classname_category_groups',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2022_10_07_145935_create_product_uoms_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2022_10_08_193845_add_bank_id_bank_remarks_customers',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2022_10_08_224343_add_vend_binding_id_customers',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2022_10_09_000524_add_vend_bindings',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2022_10_09_001645_add_keylock_number_vends',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2022_10_09_003433_add_is_freezer_customers',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2022_10_09_005517_alter_vend_id_vend_bindings',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2022_10_09_012048_alter_customer_id_vend_binding_id_customers_vend_bindings',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2022_10_10_004115_add_is_keep_vend_temps',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2022_10_11_144255_add_is_parent_customers',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2022_10_29_144258_add_ip_address_vend_data',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2022_10_29_162532_create_permission_tables',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2022_11_09_104540_add_vend_channels_json_vends',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2022_11_09_113303_add_vend_channel_error_logs_json_vends',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2022_11_09_210145_add_channel_totals_vends',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2022_11_10_143353_add_parameter_json_vends',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2022_11_11_144139_alter_index_value_vend_temps',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2022_11_11_150310_alter_index_vend_id_vend_temps',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2022_11_11_152218_alter_index_vend_id_vend_bindings',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2022_11_11_153201_add_type_vend_temps',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2022_11_11_170219_add_last_updated_at_vends',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2022_11_16_120228_add_is_online_vends',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2022_11_23_210947_add_is_offline_notification_sent_vends',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2022_11_29_170147_alter_index_vend_transactions',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2022_11_29_233502_add_vend_transactions_total_json_vends',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2022_11_30_164804_add_username_users',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2022_12_07_214034_add_vend_transaction_json_vend_transactions',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2022_12_14_171328_alter_email_nullable_users',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2022_12_16_115609_create_operators_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2022_12_16_180714_add_operator_id_vends',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2022_12_24_103243_add_operators',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2022_12_24_154341_create_product_mappings_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2022_12_24_163931_create_product_mapping_items_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2022_12_24_174744_add_operator_id_products',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2022_12_24_180758_add_operator_id_users',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2022_12_25_132610_add_timezone_countries',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2022_12_26_211414_alter_operator_id_nullable_users',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2022_12_26_212446_create_operator_vend_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2022_12_26_212800_drop_operator_id_vends',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2022_12_27_112914_alter_composite_index_operator_vend',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2022_12_28_153221_create_dup_product_mapping_items',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2022_12_29_001837_add_product_mapping_items_json_product_mappings',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2022_12_29_103225_add_product_id_vend_channels',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2022_12_29_103425_add_product_id_vend_transactions',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2022_12_29_104651_add_product_mapping_id_vends',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2022_12_29_143542_add_product_mapping_vends_json',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2023_01_07_120819_create_resource_centers_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2023_01_25_095525_create_log_data_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2023_01_25_124636_add_vend_transaction_id_vend_channel_error_logs',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2023_02_03_104129_create_payment_gateways_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2023_02_03_120453_create_operator_payment_gateways',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2023_02_03_140217_create_vend_fans_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2023_02_03_180451_create_payment_gateway_logs_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2023_02_07_173212_add_access_token_users',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2023_02_07_202147_add_connection_vend_date',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2023_02_08_120846_add_processed_vend_data',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2023_02_14_145905_add_payment_gateway_id_payment_gateway_logs',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2023_02_14_220319_add_status_payment_gateway_logs',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2023_02_20_123622_add_apk_ver_json_vends',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2023_02_20_161445_add_private_key_vends',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2023_02_22_112009_add_country_id_payment_gateways',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2023_02_22_141854_add_key1_name_key2_name_key3_name_payment_gateways',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2023_02_22_151912_add_key3_operator_payment_gateways',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2023_02_22_233136_add_is_payment_received',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2023_03_03_122749_alter_order_id_index_vend_transactions',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2023_03_24_101455_alter_index_vend_id_vend_bindings',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2023_04_02_224956_add_last_invoice_date_customers',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2023_04_04_144132_add_qr_url_payment_gateway_logs',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2023_04_05_143439_add_vend_json_vend_transactions',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2023_04_11_114922_add_product_json_vend_transactions',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2023_04_18_115518_create_location_types_table',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2023_04_19_121916_add_location_type_id_customers',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2023_04_19_162114_add_is_current_unit_costs',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2023_04_20_145758_add_unit_cost_id_vend_transactions',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2023_04_20_163823_add_gst_vat_rate_operators',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2023_04_22_132239_add_index_operator_vend',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2023_04_22_132646_add_unit_cost_json_vend_transactions',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2023_04_25_205150_alter_indexes_customers',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2023_04_25_213910_add_unit_cost_gross_profit_revenue_vend_transactions',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2023_04_27_235625_add_gst_vat_rate_vend_transactions',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2023_04_28_232441_add_customer_json_operator_json_location_type_json_vend_transactions',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2023_05_04_155533_create_vend_snapshots_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2023_05_08_114727_add_indexes_reports_products',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2023_05_09_155806_add_customer_id_vend_snapshots',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2023_05_09_171843_alter_index_vend_transaction',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2023_05_09_174604_alter_index_payment_method_id_vend_transaction',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2023_05_10_164949_add_indexes_rpt',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2023_05_11_193033_add_customer_id_operator_id_vend_transactions',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2023_05_12_135200_add_next_invoice_date_customers',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2023_05_18_130946_add_history_json_payment_gateway_logs',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2023_05_18_150538_add_is_refunded_payment_gateway_log_id_vend_transactions',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2023_05_22_105211_add_out_of_stock_sku_percent_vends',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2023_05_22_111621_add_error_code_normalized_vend_transactions',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2023_05_24_011454_add_qr_text_payment_gateway_logs',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2023_05_25_213354_add_cms_invoice_history_customers',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2023_05_27_192321_add_payment_gateway_id_payment_methods',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2023_05_27_195755_add_default_payment_gateway_id_payment_gateways',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2023_05_27_195833_add_type_name_payment_methods',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2023_05_27_200851_drop_classname_payment_gateways',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2023_05_28_120048_add_ref_id_payment_gateway_logs',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2023_06_01_111429_add_vend_id_payment_gateway_logs',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2023_06_01_112000_add_vend_channel_code_vend_channel_id_payment_gateway_logs',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2023_06_14_124014_create_vend_criterias_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2023_06_14_124529_create_vend_criteria_bindings_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2023_06_17_130354_create_holidays_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2023_06_26_142025_create_vend_criteria_scores_table',60);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2023_06_27_142733_add_classname_vend_criterias',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2023_06_27_151906_create_vend_sub_criterias_table',62);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2023_06_30_111637_add_code_vend_criterias',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2023_06_30_112050_alter_value_vend_criterias',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2023_06_30_113125_add_value2_vend_criterias',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2023_06_30_124556_add_vend_sub_criteria_id_vend_criteria_bindings',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2023_07_04_214936_add_weightage_vend_channel_errors_location_types',65);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2023_07_05_104719_add_vend_criteria_weightage_json_vend_criteria_score_json',65);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2023_07_08_230656_create_vend_records_table',66);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2023_07_11_121855_add_request_history_json_error_json_payment_gateway_logs',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2023_07_11_154805_alter_amount_payment_gateway_logs',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2023_07_14_095900_add_is_mqtt_vends',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2023_07_20_123841_add_begin_date_termination_date_vends',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2023_07_20_141829_create_labels_table',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2023_07_20_150741_add_person_id_customers',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2023_07_25_114629_add_account_manager_json_customers',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2023_07_26_192034_add_amount_average_day_vends',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2023_08_09_091535_add_balance_percent_vends',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2023_08_10_100404_add_is_customer_vends',75);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2023_08_12_170657_create_months_table',76);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2023_08_17_145343_create_payment_merchants_table',77);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2023_08_18_134232_add_is_active_vends',77);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2016_06_01_000001_create_oauth_auth_codes_table',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2016_06_01_000002_create_oauth_access_tokens_table',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2016_06_01_000003_create_oauth_refresh_tokens_table',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2016_06_01_000004_create_oauth_clients_table',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2016_06_01_000005_create_oauth_personal_access_clients_table',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2023_08_23_141658_alter_access_token_users',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2023_08_23_214528_add_virtual_firmware_ver_vends',79);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2023_08_24_142642_add_acb_vmc_pa_json_acb_status_json_vends',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2023_08_24_180909_add_vend_code_type_vend_data',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2023_08_30_135824_add_is_active_payment_methods',81);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2023_08_30_140917_add_is_main_operator_vend',81);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (205,'2023_08_30_145817_create_user_vend_table',82);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2023_08_31_112803_create_temp_vend_transactions_table',83);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2023_09_04_103922_create_delivery_platforms_table',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2023_09_04_103938_create_delivery_platform_operators_table',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2023_09_04_104149_add_currency_exponent_countries',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (210,'2023_09_06_000620_create_delivery_platform_logs_table',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (211,'2023_09_06_122755_alter_is_active_vends',85);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (212,'2023_09_07_115050_add_is_keep_vend_data',86);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (213,'2023_09_07_125457_rename_field1_field2_field3_delivery_platforms',87);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (214,'2023_09_07_151354_add_field4_name_delivery_platforms',87);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (215,'2023_09_07_224110_add_is_exponent_hidden_countries',88);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (216,'2023_09_08_160119_create_external_oauth_tokens_table',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (217,'2023_09_08_164129_add_default_scopes_default_access_method_delivery_platforms',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (218,'2023_09_08_165646_alter_scopes_external_oauth_tokens',90);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (219,'2023_09_08_170015_alter_default_access_method_delivery_platforms',91);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (220,'2023_09_08_171112_add_default_access_type_delivery_platforms',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (221,'2023_09_08_175815_add_granted_type_external_oauth_tokens',93);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (222,'2023_09_10_174639_add_slug_delivery_platforms',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (223,'2023_09_10_202433_create_delivery_platform_orders_table',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (224,'2023_09_10_202448_create_delivery_platform_order_items_table',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (225,'2023_09_16_122600_create_delivery_product_mappings_table',95);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (226,'2023_09_16_123028_create_delivery_product_mapping_items_table',95);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (227,'2023_09_24_115104_alter_access_token_external_oauth_tokens',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (228,'2023_09_25_111142_add_category_json_delivery_product_mappings',97);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (229,'2023_09_28_234108_add_statistics1_json_vends',98);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (230,'2023_09_29_120305_add_amount2_discount_group_locked_qty_sku_code',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (231,'2023_09_29_133755_add_virtual_apk_ver_vends',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (232,'2023_09_29_135659_alter_virtual_apk_ver_vends',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (233,'2023_10_05_131741_add_is_testing_vends',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (234,'2023_10_05_143031_add_virtual_vend_records_thirty_days_amount_average_vends',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (235,'2023_10_06_142007_add_operator_id_delivery_product_mappings',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (236,'2023_10_06_142916_add_product_id_delivery_product_mapping_items',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (237,'2023_10_08_145148_add_channel_code_delivery_product_mapping_items',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (238,'2023_10_09_150547_create_delivery_product_mapping_vend_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (239,'2023_10_09_153340_add_delivery_product_mapping_items_json_vends_json_delivery_product_mappings',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (240,'2023_10_16_155628_add_status_delivery_product_mappings_delivery_product_mapping_items',105);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (241,'2023_10_19_155749_add_cms_refer_id_products',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (242,'2023_10_20_113734_add_measurement_unit_count_value_products',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (243,'2023_10_20_134205_add_is_active_delivery_product_mapping_vend_delivery_product_mappings',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (244,'2023_10_20_155424_create_delivery_product_mapping_vend_channels_table',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (245,'2023_10_20_155458_add_delivery_product_mapping_vend_channels_json_delivery_product_mapping_vend',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (246,'2023_10_20_235635_add_is_active_delivery_product_mapping_items',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (247,'2023_10_21_124002_drop_vends_json_delivery_product_mappings',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (248,'2023_10_21_124758_add_delivery_product_mapping_id_delivery_product_mapping_item_id',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (249,'2023_10_21_143432_add_reserved_percent_reserved_qty_delivery_product_mappings',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (250,'2023_10_25_124641_add_vend_code_platform_ref_id_delivery_product_mapping_vend',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (251,'2023_10_25_160452_alter_order_qty_delivery_product_mapping_vend_channels',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (252,'2023_10_26_110254_create_delivery_platform_menu_records_table',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (253,'2023_10_26_130655_add_delivery_product_mapping_vend_id_delivery_platform_menu_records',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (254,'2023_10_26_145902_alter_platform_ref_id_delivery_platform_menu_records',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (255,'2023_10_26_150032_add_platform_ref_json_delivery_platform_menu_records',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (256,'2023_10_27_001007_drop_columns_delivery_platform_menu_records',109);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (257,'2023_10_27_105913_add_ref_id_delivery_platform_menu_records',110);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (258,'2023_10_27_113521_add_platform_ref_id_delivery_platform_orders',111);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (259,'2023_10_27_123953_add_driver_eta_seconds_delivery_platform_orders',111);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (260,'2023_10_27_133231_drop_vend_channel_delivery_platform_orders',112);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (261,'2023_10_27_145817_add_qty_delivery_platform_order_items',112);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (262,'2023_10_28_002455_create_order_item_vend_channels_table',113);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (263,'2023_10_28_144738_drop_vend_id_delivery_platform_orders',113);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (264,'2023_10_29_010849_drop_product_mapping_item_id_delivery_platform_order_items',113);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (265,'2023_10_29_150354_add_delivery_platform_order_id_vend_channel_code_order_item_vend_channels',113);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (266,'2023_10_29_161514_add_driver_phone_number_delivery_platform_orders',113);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (267,'2023_10_29_163606_create_delivery_platform_order_complaints',113);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (268,'2023_10_31_140912_add_remarks_delivery_platform_orders',114);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (269,'2023_11_01_105709_add_is_verified_delivery_platform_orders',115);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (270,'2023_11_01_114633_add_payment_method_id_delivery_platforms',115);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (271,'2023_11_01_120632_add_order_id_vend_transaction_id_delivery_platform_orders',116);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (272,'2023_11_01_162900_add_cancelled_json_delivery_platform_orders',116);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (273,'2023_11_02_141640_add_amount_order_item_vend_channels',117);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (274,'2023_11_03_122438_add_qty_sold_at_qty_restocked_at_vend_channels',118);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (275,'2023_11_08_112226_add_original_json_delivery_platform_order_complaints',119);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (276,'2023_11_11_145318_add_last_ip_address_vends',120);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (277,'2023_11_11_150054_add_is_apk_constant_payment_methods',120);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (278,'2023_11_13_211445_add_driver_request_json_delivery_order_platforms',121);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (279,'2023_11_14_112805_add_is_multiple_items_json_vend_transactions',122);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (280,'2023_11_14_171644_alter_transactions_items_json_vend_transactions',123);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (281,'2023_11_22_160627_alter_virtual_apk_ver_vends',124);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (282,'2023_11_25_205914_create_delivery_platform_campaigns',125);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (283,'2023_12_01_220850_create_vend_transaction_items_table',126);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (284,'2023_12_03_002026_add_vend_transaction_item_json_vend_transactions',126);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (285,'2023_12_03_093556_add_product_json_vend_transaction_items',126);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (286,'2023_12_04_221244_add_mqtt_last_updated_at_vends',126);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (287,'2023_12_14_095403_add_is_mqtt_active_vends',127);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (288,'2023_12_14_123836_add_vend_json_product_json_delivery_platform_orders',128);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (289,'2023_12_20_134745_add_status_json_delivery_platform_orders',129);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (290,'2023_12_22_132647_create_delivery_product_mapping_bulks_table',130);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (291,'2023_12_22_132652_create_delivery_product_mapping_bulk_items_table',130);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (292,'2023_12_24_140559_add_user_type_min_amount_total_redeemable_count_total_redeemable_count_per_user_delivery_platform_campaigns',130);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (293,'2023_12_25_194736_add_promo_label_delivery_product_mapping_bulks',130);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (294,'2023_12_27_112838_add_promo_desc_delivery_product_mapping_bulks',130);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (295,'2023_12_29_122858_add_start_date_end_date_delivery_product_mapping_vends',131);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (296,'2023_12_29_133329_add_online_success_amount_count_vend_records',131);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (297,'2024_01_01_133353_create_delivery_platform_campaign_item_vends_table',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (298,'2024_01_01_171028_add_is_active_delivery_platform_campaigns',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (299,'2024_01_03_160226_alter_delivery_platform_campaigns',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (300,'2024_01_03_160959_create_delivery_platform_campaign_items',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (301,'2024_01_04_152901_add_delivery_platform_operator_id_delivery_platform_campaigns',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (302,'2024_01_08_022449_add_delivery_product_mapping_items_json_delivery_platform_campaign_items',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (303,'2024_01_08_190141_alter_delivery_platform_campaign_items',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (304,'2024_01_09_145838_add_items_json_delivery_platform_campaign_items',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (305,'2024_01_09_231452_add_delivery_platform_campaign_id_delivery_platform_campaign_item_vends',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (306,'2024_01_09_235743_add_is_submitted_platform_ref_id_delivery_platform_campaign_item_vends',133);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (307,'2024_01_10_111929_alter_product_mapping_item_id_delivery_product_mapping_items',134);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (308,'2024_01_10_200618_add_promo_amount_delivery_platform_orders',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (309,'2024_01_11_114147_drop_delivery_product_mapping_bulk_id_is_bulk_delivery_platform_campaign_items',136);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (310,'2024_01_11_135236_add_datetime_from_datetime_to_delivery_platform_campaign_item_vends',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (311,'2024_01_11_141213_add_virtual_campaign_id_json_delivery_platform_orders',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (312,'2024_01_12_201444_add_settings_name_settings_label_settings_json_delivery_platform_campaign_item_vends',138);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (313,'2024_01_15_121936_add_collection_delivered_datetime_last_mile_timediff_mins_delivery_platform_orders',139);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (314,'2024_01_16_113811_drop_datetime_from_to_delivery_platform_campaigns',140);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (315,'2024_01_16_134039_add_submission_response_json_delivery_platform_campaign_item_vends',140);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (316,'2024_01_16_151526_drop_datetime_from_to_delivery_platform_campaign_items',141);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (317,'2024_01_16_161137_add_submission_request_json_delivery_platform_campaign_item_vends',142);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (318,'2024_01_25_141444_add_order_qty_json_delivery_product_mapping_vend_channels',143);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (319,'2024_01_25_161847_create_vouchers_table',144);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (320,'2024_01_25_162422_create_voucher_items_table',144);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (321,'2024_02_06_121559_alter_customers',145);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (322,'2024_02_06_160621_alter_vend_bindings',145);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (323,'2024_02_07_153217_add_operator_id_customers',146);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (324,'2024_02_15_231249_alter_vend_transactions',147);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (325,'2024_02_21_141433_add_customer_json_vend_records',148);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (326,'2024_02_21_153848_rename_customer_json_person_json_customers',149);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (327,'2024_02_21_160449_add_virtual_customer_code_prefix',149);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (328,'2024_03_08_193416_add_endpoint_delivery_platform_operators',150);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (329,'2024_03_14_112635_add_last_menu_json_delivery_product_mapping_vend',151);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (330,'2024_02_22_143814_alter_vend_transaction_items',152);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (331,'2024_02_22_153248_drop_payment_term_id_customers',152);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (332,'2024_02_23_143417_add_snap_and_totals_json_vend_bindings',152);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (333,'2024_02_24_121114_add_vend_binding_id_vend_transactions',152);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (334,'2024_03_09_102930_add_totals_json_customers',152);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (335,'2024_03_11_115645_create_maps_table',152);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (336,'2024_03_11_163150_add_customer_id_vends',152);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (337,'2024_03_11_165009_add_totals_json_customers',152);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (338,'2024_03_26_234610_add_revenue_gross_profit_vend_records',153);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (339,'2024_04_01_140931_add_is_active_product_mappings',154);
