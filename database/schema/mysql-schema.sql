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
  `map_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` int DEFAULT NULL,
  `sequence` int DEFAULT NULL,
  `modelable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modelable_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `block_num` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `building` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `addresses_modelable_type_modelable_id_index` (`modelable_type`,`modelable_id`),
  KEY `addresses_type_index` (`type`),
  KEY `addresses_country_id_index` (`country_id`),
  KEY `idx_addresses_morph_type` (`modelable_type`,`type`,`modelable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `alert_email_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alert_email_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_send_channel_error_log` tinyint(1) NOT NULL DEFAULT '0',
  `is_send_offline_notification` tinyint(1) NOT NULL DEFAULT '0',
  `is_send_power_restored_notification` tinyint(1) NOT NULL DEFAULT '0',
  `is_send_transaction_no_entry_notification` tinyint(1) NOT NULL DEFAULT '0',
  `operator_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `alert_email_items_operator_id_index` (`operator_id`),
  KEY `alert_email_items_user_id_index` (`user_id`),
  CONSTRAINT `alert_email_items_operator_id_foreign` FOREIGN KEY (`operator_id`) REFERENCES `operators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alert_email_items_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_id` bigint unsigned NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warning',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ai_analysis` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `alerts_vend_id_foreign` (`vend_id`),
  CONSTRAINT `alerts_vend_id_foreign` FOREIGN KEY (`vend_id`) REFERENCES `vends` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `apk_setting_campaign`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `apk_setting_campaign` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `apk_setting_id` bigint unsigned NOT NULL,
  `campaign_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `apk_setting_campaign_apk_setting_id_campaign_id_unique` (`apk_setting_id`,`campaign_id`),
  KEY `apk_setting_campaign_campaign_id_foreign` (`campaign_id`),
  CONSTRAINT `apk_setting_campaign_apk_setting_id_foreign` FOREIGN KEY (`apk_setting_id`) REFERENCES `apk_settings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `apk_setting_campaign_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `apk_setting_vend`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `apk_setting_vend` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `apk_setting_id` bigint unsigned NOT NULL,
  `vend_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `apk_setting_vend_apk_setting_id_vend_id_unique` (`apk_setting_id`,`vend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `apk_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `apk_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `settings_parameter_json` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
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
DROP TABLE IF EXISTS `banks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `banks_country_id_index` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campaign_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `apk_setting_id` bigint unsigned DEFAULT NULL,
  `date_to` datetime DEFAULT NULL,
  `date_from` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `qty` int unsigned NOT NULL DEFAULT '0',
  `value` int unsigned DEFAULT NULL,
  `promo_type` int unsigned NOT NULL DEFAULT '1',
  `campaign_type` tinyint unsigned DEFAULT NULL,
  `action_type` tinyint unsigned DEFAULT NULL,
  `action_value` int unsigned DEFAULT NULL,
  `cart_amount_threshold` int unsigned DEFAULT NULL,
  `free_qty` int unsigned NOT NULL DEFAULT '0',
  `selection_strategy` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaign_items_uuid_unique` (`uuid`),
  KEY `campaign_items_apk_setting_id_index` (`apk_setting_id`),
  KEY `campaign_items_campaign_type_action_type_index` (`campaign_type`,`action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campaign_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_tag` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` bigint unsigned NOT NULL,
  `tag_id` bigint unsigned NOT NULL,
  `type` enum('x','y') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaign_tag_campaign_id_tag_id_type_unique` (`campaign_id`,`tag_id`,`type`),
  KEY `campaign_tag_tag_id_foreign` (`tag_id`),
  CONSTRAINT `campaign_tag_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `campaign_tag_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `promo_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_using_qty` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'qty',
  `bundle_qty` int unsigned DEFAULT NULL,
  `value` int unsigned DEFAULT NULL,
  `min_basket_value` int unsigned DEFAULT NULL,
  `max_discount_value` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `operator_id` bigint unsigned DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaigns_uuid_unique` (`uuid`),
  KEY `campaigns_start_at_index` (`start_at`),
  KEY `campaigns_end_at_index` (`end_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `card_terminals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `card_terminals` (
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
  `operator_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cashless_terminals_operator_id_index` (`operator_id`)
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
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `customer_contract_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_contract_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `effective_from` datetime NOT NULL,
  `effective_to` datetime DEFAULT NULL,
  `contract_commission_type` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_commission_value` decimal(10,2) DEFAULT NULL,
  `contract_commission_value2` decimal(10,2) DEFAULT NULL,
  `is_external_subsidize` tinyint(1) NOT NULL DEFAULT '0',
  `external_subsidize_amount` decimal(10,2) DEFAULT NULL,
  `contract_ps_term` decimal(5,2) DEFAULT NULL,
  `contract_from` date DEFAULT NULL,
  `contract_until` date DEFAULT NULL,
  `contract_auto_renewal` tinyint(1) NOT NULL DEFAULT '0',
  `contract_notice_period` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `changed_by` bigint unsigned DEFAULT NULL,
  `source` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_contract_logs_changed_by_foreign` (`changed_by`),
  KEY `ccl_customer_eff_from_idx` (`customer_id`,`effective_from`),
  KEY `ccl_customer_eff_to_idx` (`customer_id`,`effective_to`),
  KEY `ccl_eff_from_idx` (`effective_from`),
  CONSTRAINT `customer_contract_logs_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_contract_logs_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_period_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_period_summaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `operator_id` bigint unsigned DEFAULT NULL,
  `year_month` date NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `is_current_month` tinyint(1) NOT NULL DEFAULT '0',
  `segment_index` tinyint unsigned NOT NULL DEFAULT '0',
  `segmentation_overridden` tinyint(1) NOT NULL DEFAULT '0',
  `as_of_date` date NOT NULL,
  `sales_cents` bigint NOT NULL DEFAULT '0',
  `gross_earning_cents` bigint NOT NULL DEFAULT '0',
  `location_fees_cents` bigint NOT NULL DEFAULT '0',
  `location_earning_cents` bigint NOT NULL DEFAULT '0',
  `location_earning_rate` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `external_subsidize_cents` bigint NOT NULL DEFAULT '0',
  `transaction_count` int unsigned NOT NULL DEFAULT '0',
  `vend_count` int unsigned NOT NULL DEFAULT '0',
  `job_count` int unsigned NOT NULL DEFAULT '0',
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` bigint unsigned DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT '0',
  `paid_at` timestamp NULL DEFAULT NULL,
  `paid_date` date DEFAULT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT '0',
  `is_waived` tinyint(1) NOT NULL DEFAULT '0',
  `waived_remarks` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_by` bigint unsigned DEFAULT NULL,
  `last_unpaid_at` timestamp NULL DEFAULT NULL,
  `last_unpaid_by` bigint unsigned DEFAULT NULL,
  `last_unlocked_at` timestamp NULL DEFAULT NULL,
  `last_unlocked_by` bigint unsigned DEFAULT NULL,
  `report_emailed_at` timestamp NULL DEFAULT NULL,
  `report_emailed_by` bigint unsigned DEFAULT NULL,
  `contract_commission_type` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_commission_value` decimal(10,2) DEFAULT NULL,
  `contract_commission_value2` decimal(10,2) DEFAULT NULL,
  `contract_ps_term` decimal(5,2) DEFAULT NULL,
  `contract_selling_price_type` int DEFAULT NULL,
  `contract_log_id` bigint unsigned DEFAULT NULL,
  `vend_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cps_customer_periodstart_unique` (`customer_id`,`period_start`),
  KEY `cps_yearmonth_idx` (`year_month`),
  KEY `cps_operator_yearmonth_idx` (`operator_id`,`year_month`),
  KEY `customer_period_summaries_contract_log_id_foreign` (`contract_log_id`),
  KEY `customer_period_summaries_report_emailed_by_foreign` (`report_emailed_by`),
  KEY `customer_period_summaries_vend_id_foreign` (`vend_id`),
  CONSTRAINT `customer_period_summaries_contract_log_id_foreign` FOREIGN KEY (`contract_log_id`) REFERENCES `customer_contract_logs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_period_summaries_report_emailed_by_foreign` FOREIGN KEY (`report_emailed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_period_summaries_vend_id_foreign` FOREIGN KEY (`vend_id`) REFERENCES `vends` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_period_summary_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_period_summary_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `contract_commission_type` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cms_transaction_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cms_transaction_at` datetime DEFAULT NULL,
  `cms_transaction_by` bigint unsigned DEFAULT NULL,
  `total_amount_cents` bigint DEFAULT NULL,
  `summary_snapshot` json DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `response` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cpsi_customer_period_idx` (`customer_id`,`period_start`,`period_end`),
  KEY `cpsi_cms_transaction_id_idx` (`cms_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_scheduled_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_scheduled_contracts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `effective_date` date NOT NULL,
  `status` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `applied_at` datetime DEFAULT NULL,
  `contract_commission_type` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_commission_value` decimal(10,2) DEFAULT NULL,
  `contract_commission_value2` decimal(10,2) DEFAULT NULL,
  `contract_ps_term` decimal(5,2) DEFAULT NULL,
  `is_external_subsidize` tinyint(1) NOT NULL DEFAULT '0',
  `external_subsidize_amount` decimal(10,2) DEFAULT NULL,
  `contract_from` date DEFAULT NULL,
  `contract_until` date DEFAULT NULL,
  `contract_auto_renewal` tinyint(1) NOT NULL DEFAULT '0',
  `contract_notice_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_scheduled_contracts_created_by_foreign` (`created_by`),
  KEY `customer_scheduled_contracts_updated_by_foreign` (`updated_by`),
  KEY `csc_customer_status_idx` (`customer_id`,`status`),
  KEY `csc_status_eff_date_idx` (`status`,`effective_date`),
  CONSTRAINT `customer_scheduled_contracts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_scheduled_contracts_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_scheduled_contracts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_settlement_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_settlement_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `customer_settlement_id` bigint unsigned DEFAULT NULL,
  `reference_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entry_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_amount_cents` bigint DEFAULT NULL,
  `new_amount_cents` bigint DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `source` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_settlement_logs_changed_by_foreign` (`changed_by`),
  KEY `cs_log_customer_created_idx` (`customer_id`,`created_at`),
  KEY `cs_log_settlement_idx` (`customer_settlement_id`),
  CONSTRAINT `customer_settlement_logs_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_settlement_logs_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_settlement_logs_customer_settlement_id_foreign` FOREIGN KEY (`customer_settlement_id`) REFERENCES `customer_settlements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_settlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_settlements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `operator_id` bigint unsigned DEFAULT NULL,
  `entry_date` date NOT NULL,
  `year_month` date DEFAULT NULL,
  `entry_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_cents` bigint NOT NULL DEFAULT '0',
  `item` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `customer_period_summary_id` bigint unsigned DEFAULT NULL,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_settlements_reference_no_unique` (`reference_no`),
  KEY `customer_settlements_customer_period_summary_id_foreign` (`customer_period_summary_id`),
  KEY `customer_settlements_created_by_foreign` (`created_by`),
  KEY `customer_settlements_updated_by_foreign` (`updated_by`),
  KEY `cust_settle_chrono_idx` (`customer_id`,`entry_date`,`id`),
  KEY `cust_settle_month_type_idx` (`customer_id`,`year_month`,`entry_type`),
  KEY `customer_settlements_operator_id_index` (`operator_id`),
  KEY `customer_settlements_entry_date_index` (`entry_date`),
  KEY `customer_settlements_entry_type_index` (`entry_type`),
  KEY `customer_settlements_source_index` (`source`),
  CONSTRAINT `customer_settlements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_settlements_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_settlements_customer_period_summary_id_foreign` FOREIGN KEY (`customer_period_summary_id`) REFERENCES `customer_period_summaries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_settlements_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_status_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_status_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `status_id` tinyint unsigned NOT NULL,
  `status_date` date DEFAULT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `source` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_status_logs_changed_by_foreign` (`changed_by`),
  KEY `csl_customer_created_idx` (`customer_id`,`created_at`),
  CONSTRAINT `customer_status_logs_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_status_logs_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_vend_bindings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_vend_bindings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned DEFAULT NULL,
  `is_binding` tinyint(1) NOT NULL DEFAULT '1',
  `user_id` bigint unsigned DEFAULT NULL,
  `vend_id` bigint unsigned DEFAULT NULL,
  `vend_prefix_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vend_customer_binding_at` (`vend_id`,`customer_id`,`is_binding`,`created_at`),
  KEY `customer_vend_bindings_created_at_index` (`created_at`),
  KEY `customer_vend_bindings_customer_id_index` (`customer_id`),
  KEY `customer_vend_bindings_user_id_index` (`user_id`),
  KEY `customer_vend_bindings_vend_id_index` (`vend_id`),
  KEY `customer_vend_bindings_vend_prefix_id_index` (`vend_prefix_id`)
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
  `site_contact_person` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site_phone_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site_alt_phone_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `company_remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_rate` decimal(8,2) DEFAULT NULL,
  `payterm` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_to` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_gst_registered` tinyint(1) NOT NULL DEFAULT '0',
  `is_billing_same_as_delivery` tinyint(1) NOT NULL DEFAULT '1',
  `bank_id` bigint unsigned DEFAULT NULL,
  `bank_account_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_restricted_access` tinyint(1) NOT NULL DEFAULT '0',
  `profile_id` bigint NOT NULL,
  `status_id` bigint NOT NULL,
  `zone_id` bigint DEFAULT NULL,
  `contract_commission_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_commission_value` decimal(10,2) DEFAULT NULL,
  `contract_commission_value2` decimal(10,2) DEFAULT NULL,
  `is_external_subsidize` tinyint(1) NOT NULL DEFAULT '0',
  `external_subsidize_amount` decimal(10,2) DEFAULT NULL,
  `contract_ps_term` decimal(5,2) DEFAULT NULL,
  `contract_from` date DEFAULT NULL,
  `contract_until` date DEFAULT NULL,
  `contract_auto_renewal` tinyint(1) NOT NULL DEFAULT '0',
  `contract_notice_period` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes_updated_at` timestamp NULL DEFAULT NULL,
  `notes_updated_by` bigint unsigned DEFAULT NULL,
  `loc_fee_remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `loc_fee_remarks_updated_at` timestamp NULL DEFAULT NULL,
  `loc_fee_remarks_updated_by` bigint unsigned DEFAULT NULL,
  `location_grading_placement` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_grading_access` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_grading_flexibility` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `report_email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_report_email_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `contract_detail_updated_at` timestamp NULL DEFAULT NULL,
  `contract_detail_updated_by` bigint unsigned DEFAULT NULL,
  `last_invoice_date` datetime DEFAULT NULL,
  `next_invoice_date` datetime DEFAULT NULL,
  `location_type_id` bigint unsigned DEFAULT NULL,
  `cms_invoice_history` json DEFAULT NULL,
  `next_invoice_driver_id` bigint unsigned DEFAULT NULL,
  `account_manager_json` json DEFAULT NULL,
  `person_json` json DEFAULT NULL,
  `totals_json` json DEFAULT NULL,
  `virtual_customer_prefix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (json_unquote(json_extract(`person_json`,_utf8mb4'$.prefix'))) VIRTUAL,
  `virtual_customer_code` int GENERATED ALWAYS AS (json_unquote(json_extract(`person_json`,_utf8mb4'$.code'))) VIRTUAL,
  `begin_date` datetime DEFAULT NULL,
  `active_date` date DEFAULT NULL,
  `removed_date` date DEFAULT NULL,
  `termination_date` datetime DEFAULT NULL,
  `snap_parameter_json` json DEFAULT NULL,
  `snap_vend_channels_json` json DEFAULT NULL,
  `snap_vend_channel_error_logs_json` json DEFAULT NULL,
  `snap_vend_status_json` json DEFAULT NULL,
  `selling_price_type` int NOT NULL DEFAULT '1',
  `power_socket_key_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ops_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ops_note_updated_at` timestamp NULL DEFAULT NULL,
  `ops_note_updated_by` bigint unsigned DEFAULT NULL,
  `preferred_visit_days_json` json DEFAULT NULL,
  `frequency_per_week_status` int DEFAULT NULL,
  `today_amount_sort` bigint GENERATED ALWAYS AS (cast(json_unquote(json_extract(`totals_json`,_utf8mb4'$.today_amount')) as signed)) STORED,
  PRIMARY KEY (`id`),
  KEY `customers_location_type_id_index` (`location_type_id`),
  KEY `customers_category_id_index` (`category_id`),
  KEY `customers_code_index` (`code`),
  KEY `customers_created_at_index` (`created_at`),
  KEY `customers_is_active_index` (`is_active`),
  KEY `customers_person_id_index` (`person_id`),
  KEY `customers_operator_id_index` (`operator_id`),
  KEY `customers_zone_id_index` (`zone_id`),
  KEY `idx_customers_operator_active` (`operator_id`,`is_active`),
  KEY `idx_customers_location_active` (`location_type_id`,`is_active`),
  KEY `idx_customers_active_today_amount` (`is_active`,`today_amount_sort`),
  KEY `customers_contract_detail_updated_by_foreign` (`contract_detail_updated_by`),
  KEY `customers_notes_updated_by_foreign` (`notes_updated_by`),
  KEY `customers_ops_note_updated_by_foreign` (`ops_note_updated_by`),
  KEY `customers_bank_id_index` (`bank_id`),
  KEY `customers_active_date_idx` (`active_date`),
  KEY `customers_removed_date_idx` (`removed_date`),
  KEY `customers_loc_fee_remarks_updated_by_foreign` (`loc_fee_remarks_updated_by`),
  KEY `customers_virtual_customer_prefix_index` (`virtual_customer_prefix`),
  KEY `customers_virtual_customer_code_index` (`virtual_customer_code`),
  CONSTRAINT `customers_contract_detail_updated_by_foreign` FOREIGN KEY (`contract_detail_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_loc_fee_remarks_updated_by_foreign` FOREIGN KEY (`loc_fee_remarks_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_notes_updated_by_foreign` FOREIGN KEY (`notes_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_ops_note_updated_by_foreign` FOREIGN KEY (`ops_note_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `daily_weather_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_weather_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `weather_code` int NOT NULL,
  `icon_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date_lat_lng_unique` (`date`,`latitude`,`longitude`)
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
DROP TABLE IF EXISTS `delivery_platform_menu_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_platform_menu_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_platform_slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_ref_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `vend_code` int DEFAULT NULL,
  `request_json` json DEFAULT NULL,
  `ref_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_platform_menu_records_ref_id_index` (`ref_id`),
  KEY `idx_delivery_platform_menu_records_created_at` (`created_at`)
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
  `is_cancelled` tinyint(1) NOT NULL DEFAULT '0',
  `is_edited` tinyint(1) NOT NULL DEFAULT '0',
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `order_created_at` datetime NOT NULL,
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
  `campaign_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `final_status_at` datetime DEFAULT NULL,
  `dispensed_at` datetime DEFAULT NULL,
  `platform_ref_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivery_product_mapping_vend_id` bigint unsigned DEFAULT NULL,
  `subtotal_amount` int DEFAULT NULL,
  `promo_amount` int NOT NULL DEFAULT '0',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `vend_transaction_order_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vend_transaction_id` bigint DEFAULT NULL,
  `driver_request_json` json DEFAULT NULL,
  `virtual_campaign_id_json` json GENERATED ALWAYS AS (json_unquote(json_extract(`campaign_json`,_utf8mb4'$[*].id'))) VIRTUAL,
  `collected_datetime` datetime DEFAULT NULL,
  `delivered_datetime` datetime DEFAULT NULL,
  `last_mile_timediff_mins` int DEFAULT NULL,
  `delivery_platform_ref_number_id` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_platform_orders_delivery_platform_id_index` (`delivery_platform_id`),
  KEY `delivery_platform_orders_delivery_platform_operator_id_index` (`delivery_platform_operator_id`),
  KEY `delivery_platform_orders_order_id_index` (`order_id`),
  KEY `delivery_platform_orders_product_mapping_id_index` (`product_mapping_id`),
  KEY `delivery_platform_orders_short_order_id_index` (`short_order_id`),
  KEY `delivery_platform_orders_status_index` (`status`),
  KEY `delivery_platform_orders_vend_code_index` (`vend_code`),
  KEY `delivery_platform_orders_delivery_product_mapping_vend_id_index` (`delivery_product_mapping_vend_id`),
  KEY `delivery_platform_orders_created_at_index` (`created_at`),
  KEY `delivery_platform_orders_order_created_at_index` (`order_created_at`),
  KEY `delivery_platform_orders_vend_transaction_id_index` (`vend_transaction_id`),
  KEY `idx_vend_transaction_order_id` (`vend_transaction_order_id`),
  KEY `delivery_platform_orders_dispensed_at_index` (`dispensed_at`),
  KEY `delivery_platform_orders_final_status_at_index` (`final_status_at`),
  KEY `dpo_dp_ref_num_idx` (`delivery_platform_ref_number_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `delivery_platform_ref_numbers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_platform_ref_numbers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_platform_id` bigint DEFAULT NULL,
  `operator_id` bigint DEFAULT NULL,
  `ref_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `delivery_platform_ref_numbers_delivery_platform_id_index` (`delivery_platform_id`),
  KEY `delivery_platform_ref_numbers_ref_number_index` (`ref_number`)
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
  `customer_id` bigint DEFAULT NULL,
  `delivery_platform_ref_number_id` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_id` (`delivery_product_mapping_id`),
  KEY `delivery_product_mapping_vend_is_active_index` (`is_active`),
  KEY `delivery_product_mapping_vend_platform_ref_id_index` (`platform_ref_id`),
  KEY `delivery_product_mapping_vend_vend_code_index` (`vend_code`),
  KEY `delivery_product_mapping_vend_customer_id_index` (`customer_id`),
  KEY `dpmv_dp_ref_num_idx` (`delivery_platform_ref_number_id`),
  KEY `idx_dpmv_platform_vend_active` (`platform_ref_id`,`vend_code`,`is_active`),
  KEY `idx_dpmv_mapping_active` (`delivery_product_mapping_id`,`is_active`),
  KEY `idx_dpmv_customer_active` (`customer_id`,`is_active`),
  KEY `idx_dpmv_vend_id` (`vend_id`)
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
  KEY `delivery_product_mapping_vend_channels_vend_id_index` (`vend_id`),
  KEY `delivery_product_mapping_vend_channels_vend_mapping_id_index` (`delivery_product_mapping_vend_id`),
  KEY `idx_dpmvc_mapping_channel_code` (`delivery_product_mapping_vend_id`,`vend_channel_code`),
  KEY `idx_dpmvc_vend_channel` (`vend_channel_id`)
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
DROP TABLE IF EXISTS `dispense_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dispense_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `is_vm_receive_dispense_signal` tinyint(1) NOT NULL DEFAULT '0',
  `order_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_gateway_log_id` bigint DEFAULT NULL,
  `delivery_platform_order_id` bigint DEFAULT NULL,
  `vend_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vend_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dispense_records_created_at_index` (`created_at`),
  KEY `dispense_records_order_id_index` (`order_id`),
  KEY `dispense_records_payment_gateway_log_id_index` (`payment_gateway_log_id`),
  KEY `dispense_records_vend_code_index` (`vend_code`),
  KEY `dispense_records_vend_id_index` (`vend_id`),
  KEY `dispense_records_is_vm_receive_dispense_signal_index` (`is_vm_receive_dispense_signal`),
  KEY `dispense_records_type_index` (`type`),
  KEY `dispense_records_delivery_platform_order_id_index` (`delivery_platform_order_id`)
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
DROP TABLE IF EXISTS `export_job_chunks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `export_job_chunks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `export_job_id` bigint unsigned NOT NULL,
  `chunk_index` int NOT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `export_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `export_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vend_transaction',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `export_jobs_user_id_foreign` (`user_id`),
  CONSTRAINT `export_jobs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
DROP TABLE IF EXISTS `gp_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gp_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `txn_date` date NOT NULL,
  `operator_id` bigint unsigned DEFAULT NULL,
  `vend_id` bigint unsigned DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `category_group_id` bigint unsigned DEFAULT NULL,
  `customer_location_type_id` bigint unsigned DEFAULT NULL,
  `transaction_location_type_id` bigint unsigned DEFAULT NULL,
  `vend_prefix_id` bigint unsigned DEFAULT NULL,
  `vend_contract_id` bigint unsigned DEFAULT NULL,
  `vend_model_id` bigint unsigned DEFAULT NULL,
  `is_multiple` tinyint(1) NOT NULL DEFAULT '0',
  `is_binded_customer` tinyint(1) NOT NULL DEFAULT '0',
  `sale_count` bigint unsigned NOT NULL DEFAULT '0',
  `transaction_count` bigint unsigned NOT NULL DEFAULT '0',
  `success_count` bigint unsigned NOT NULL DEFAULT '0',
  `error_count` bigint unsigned NOT NULL DEFAULT '0',
  `error_count_no_4_5` bigint unsigned NOT NULL DEFAULT '0',
  `error_count_4_5` bigint unsigned NOT NULL DEFAULT '0',
  `amount_cents` bigint unsigned NOT NULL DEFAULT '0',
  `revenue_cents` bigint NOT NULL DEFAULT '0',
  `gross_profit_cents` bigint NOT NULL DEFAULT '0',
  `unit_cost_cents` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gp_metrics_unique_dimensions` (`txn_date`,`operator_id`,`vend_id`,`customer_id`,`product_id`,`category_id`,`category_group_id`,`customer_location_type_id`,`transaction_location_type_id`,`vend_prefix_id`,`vend_contract_id`,`vend_model_id`,`is_multiple`,`is_binded_customer`),
  KEY `gp_metrics_date_product` (`txn_date`,`product_id`),
  KEY `gp_metrics_date_vend` (`txn_date`,`vend_id`),
  KEY `gp_metrics_date_customer` (`txn_date`,`customer_id`),
  KEY `gp_metrics_date_operator` (`txn_date`,`operator_id`),
  KEY `gp_metrics_date_category` (`txn_date`,`category_id`),
  KEY `gp_metrics_date_customer_location` (`txn_date`,`customer_location_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hid_card_vend`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hid_card_vend` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `hid_card_id` bigint unsigned NOT NULL,
  `vend_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hid_card_vend_hid_card_id_vend_id_unique` (`hid_card_id`,`vend_id`),
  KEY `hid_card_vend_hid_card_id_index` (`hid_card_id`),
  KEY `hid_card_vend_vend_id_index` (`vend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hid_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hid_cards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `operator_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hid_cards_value_operator_id_index` (`value`,`operator_id`)
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
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `keys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `desc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `machine_health_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `machine_health_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_id` bigint unsigned NOT NULL,
  `event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alert_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bucket` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` int DEFAULT NULL,
  `context` json DEFAULT NULL,
  `occurred_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `machine_health_histories_vend_id_occurred_at_index` (`vend_id`,`occurred_at`),
  KEY `machine_health_histories_alert_type_index` (`alert_type`),
  KEY `machine_health_histories_bucket_index` (`bucket`),
  KEY `machine_health_histories_occurred_at_index` (`occurred_at`),
  CONSTRAINT `machine_health_histories_vend_id_foreign` FOREIGN KEY (`vend_id`) REFERENCES `vends` (`id`) ON DELETE CASCADE
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
DROP TABLE IF EXISTS `modem_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modem_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alias` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_modem_unit_required` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_resetable` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modem_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modem_units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `imei` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modem_type_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_updated_at` datetime DEFAULT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `modem_units_modem_type_id_index` (`modem_type_id`)
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
DROP TABLE IF EXISTS `operator_callbacks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operator_callbacks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `operator_id` bigint unsigned NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `format` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'json',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `operator_callbacks_code_index` (`code`)
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
  `email_recipients_json` json DEFAULT NULL,
  `timezone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asia/Singapore',
  `updated_by` bigint DEFAULT NULL,
  `gst_vat_rate` decimal(5,2) NOT NULL,
  `is_dcvend` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `operators_country_id_index` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ops_job_item_channel_children`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ops_job_item_channel_children` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ops_job_item_channel_id` bigint unsigned NOT NULL,
  `child_product_id` bigint unsigned NOT NULL,
  `weight_pct` tinyint unsigned NOT NULL DEFAULT '0',
  `to_pick_qty` int NOT NULL DEFAULT '0',
  `picked_qty` int NOT NULL DEFAULT '0',
  `actual_qty` int NOT NULL DEFAULT '0',
  `picked_before_qty` int DEFAULT NULL,
  `sort` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ojicc_slot_child_unique` (`ops_job_item_channel_id`,`child_product_id`),
  KEY `ojicc_child_idx` (`child_product_id`),
  CONSTRAINT `ops_job_item_channel_children_child_product_id_foreign` FOREIGN KEY (`child_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ops_job_item_channel_children_ops_job_item_channel_id_foreign` FOREIGN KEY (`ops_job_item_channel_id`) REFERENCES `ops_job_item_channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ops_job_item_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ops_job_item_channels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actual_qty` int DEFAULT NULL,
  `actual_before_qty` int DEFAULT NULL,
  `capacity` int NOT NULL DEFAULT '0',
  `error_settled_at` datetime DEFAULT NULL,
  `ops_job_id` bigint unsigned NOT NULL,
  `ops_job_item_id` bigint unsigned NOT NULL,
  `picked_qty` int DEFAULT NULL,
  `picked_before_qty` int DEFAULT NULL,
  `qty` int DEFAULT NULL,
  `saved_picked_qty` int DEFAULT NULL,
  `product_id` bigint unsigned NOT NULL,
  `is_upcoming_product` tinyint(1) NOT NULL DEFAULT '0',
  `is_manually_replaced` tinyint(1) NOT NULL DEFAULT '0',
  `replaces_ops_job_item_channel_id` bigint unsigned DEFAULT NULL,
  `vend_channel_id` bigint unsigned NOT NULL,
  `vend_channel_code` int DEFAULT NULL,
  `vend_code` int NOT NULL,
  `vmc_before_qty` int DEFAULT NULL,
  `vmc_after_qty` int DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_error_settle` tinyint(1) NOT NULL DEFAULT '0',
  `virtual_is_error` tinyint(1) GENERATED ALWAYS AS ((((`capacity` - (`capacity` - `vmc_before_qty`)) + `actual_qty`) <> `vmc_after_qty`)) VIRTUAL,
  `amount` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ops_job_item_channels_ops_job_id_index` (`ops_job_id`),
  KEY `ops_job_item_channels_ops_job_item_id_index` (`ops_job_item_id`),
  KEY `ops_job_item_channels_product_id_index` (`product_id`),
  KEY `ops_job_item_channels_vend_channel_id_index` (`vend_channel_id`),
  KEY `ops_job_item_channels_created_at_index` (`created_at`),
  KEY `idx_ojic_item_id` (`ops_job_item_id`),
  KEY `idx_ojic_channel_id` (`vend_channel_id`),
  KEY `idx_ojic_prod_item` (`product_id`,`ops_job_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ops_job_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ops_job_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cash_amount` int DEFAULT NULL,
  `cashless_amount` int DEFAULT NULL,
  `channels_json` json DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` bigint unsigned DEFAULT NULL,
  `undo_completed_by` bigint unsigned DEFAULT NULL,
  `undo_completed_at` datetime DEFAULT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ops_job_id` bigint unsigned NOT NULL,
  `cms_transaction_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cms_transaction_at` datetime DEFAULT NULL,
  `cms_transaction_by` bigint unsigned DEFAULT NULL,
  `sequence` double DEFAULT NULL,
  `status` int NOT NULL,
  `stock_action_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_inventory_adjusted` tinyint(1) NOT NULL DEFAULT '0',
  `temp_cash_amount_from_vmc` int DEFAULT NULL,
  `picked_at` datetime DEFAULT NULL,
  `last_picked_at` timestamp NULL DEFAULT NULL,
  `picked_by` bigint unsigned DEFAULT NULL,
  `undo_picked_by` bigint unsigned DEFAULT NULL,
  `undo_picked_at` datetime DEFAULT NULL,
  `updated_by` bigint unsigned NOT NULL,
  `vend_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `remarks_updated_by` bigint DEFAULT NULL,
  `remarks_updated_at` datetime DEFAULT NULL,
  `previous_ops_job_item_id` bigint unsigned DEFAULT NULL,
  `acc_total_amount` int NOT NULL DEFAULT '0',
  `acc_total_promo_amount` int NOT NULL DEFAULT '0',
  `acc_total_cashless_amount` int NOT NULL DEFAULT '0',
  `acc_total_cash_amount` int NOT NULL DEFAULT '0',
  `acc_total_count` int NOT NULL DEFAULT '0',
  `vend_channel_record_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verified_by` bigint DEFAULT NULL,
  `undo_verified_by` bigint unsigned DEFAULT NULL,
  `undo_verified_at` datetime DEFAULT NULL,
  `flagged_at` datetime DEFAULT NULL,
  `flagged_by` bigint DEFAULT NULL,
  `undo_flagged_by` bigint unsigned DEFAULT NULL,
  `undo_flagged_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint DEFAULT NULL,
  `is_cash_collected` tinyint(1) NOT NULL DEFAULT '0',
  `is_ignore_limit` tinyint(1) NOT NULL DEFAULT '0',
  `refillable_amount` int unsigned DEFAULT NULL,
  `refillable_count` int unsigned DEFAULT NULL,
  `frozen_at` timestamp NULL DEFAULT NULL,
  `frozen_snapshot` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ops_job_items_customer_id_index` (`customer_id`),
  KEY `ops_job_items_ops_job_id_index` (`ops_job_id`),
  KEY `ops_job_items_vend_id_index` (`vend_id`),
  KEY `ops_job_items_previous_ops_job_item_id_index` (`previous_ops_job_item_id`),
  KEY `ops_job_items_vend_channel_record_id_index` (`vend_channel_record_id`),
  KEY `ops_job_items_created_at_index` (`created_at`),
  KEY `idx_customer_status_created_desc` (`customer_id`,`status`,`created_at`),
  KEY `idx_customer_status_created_asc` (`customer_id`,`status`,`created_at`),
  KEY `ops_job_items_status_index` (`status`),
  KEY `ops_job_items_cms_transaction_id_index` (`cms_transaction_id`),
  KEY `idx_oji_cust_created` (`customer_id`,`created_at`),
  KEY `idx_oji_status_cust` (`status`,`customer_id`),
  KEY `idx_oji_job_id_status` (`ops_job_id`,`id`,`status`),
  KEY `idx_oji_cust_status_job` (`customer_id`,`status`,`ops_job_id`),
  KEY `idx_oji_job_vend` (`ops_job_id`,`vend_id`),
  KEY `idx_oji_cust_created_status_covering` (`customer_id`,`created_at`,`status`,`cash_amount`,`acc_total_amount`,`acc_total_count`,`ops_job_id`),
  KEY `ops_job_items_freeze_scan_idx` (`status`,`frozen_at`,`completed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ops_job_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ops_job_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ops_job_id` bigint unsigned NOT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `sequence` decimal(8,2) DEFAULT NULL,
  `task_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `postcode` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ops_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ref_url` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cash_collected` bigint NOT NULL DEFAULT '0',
  `value` bigint NOT NULL DEFAULT '0',
  `qty` int DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `picked_at` timestamp NULL DEFAULT NULL,
  `picked_by` bigint unsigned DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `completed_by` bigint unsigned DEFAULT NULL,
  `undo_picked_at` timestamp NULL DEFAULT NULL,
  `undo_picked_by` bigint unsigned DEFAULT NULL,
  `undo_completed_at` timestamp NULL DEFAULT NULL,
  `undo_completed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ops_job_tasks_created_by_foreign` (`created_by`),
  KEY `ops_job_tasks_updated_by_foreign` (`updated_by`),
  KEY `ops_job_tasks_ops_job_id_index` (`ops_job_id`),
  KEY `ops_job_tasks_sequence_index` (`sequence`),
  KEY `ops_job_tasks_picked_by_foreign` (`picked_by`),
  KEY `ops_job_tasks_completed_by_foreign` (`completed_by`),
  KEY `ops_job_tasks_undo_picked_by_foreign` (`undo_picked_by`),
  KEY `ops_job_tasks_undo_completed_by_foreign` (`undo_completed_by`),
  CONSTRAINT `ops_job_tasks_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ops_job_tasks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `ops_job_tasks_ops_job_id_foreign` FOREIGN KEY (`ops_job_id`) REFERENCES `ops_jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ops_job_tasks_picked_by_foreign` FOREIGN KEY (`picked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ops_job_tasks_undo_completed_by_foreign` FOREIGN KEY (`undo_completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ops_job_tasks_undo_picked_by_foreign` FOREIGN KEY (`undo_picked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ops_job_tasks_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ops_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ops_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` int NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `date` datetime NOT NULL,
  `delivered_by` bigint unsigned DEFAULT NULL,
  `operator_id` bigint unsigned NOT NULL,
  `picked_at` datetime DEFAULT NULL,
  `picked_by` bigint unsigned DEFAULT NULL,
  `status` int NOT NULL DEFAULT '1',
  `stock_action_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ops_jobs_code_unique` (`code`),
  KEY `ops_jobs_date_index` (`date`),
  KEY `ops_jobs_operator_id_index` (`operator_id`),
  KEY `idx_oj_date` (`date`),
  KEY `idx_oj_date_id` (`date`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ops_machine_daily_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ops_machine_daily_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `snapshot_date` date NOT NULL,
  `vend_id` bigint unsigned NOT NULL,
  `vend_code` int DEFAULT NULL,
  `operator_id` bigint unsigned DEFAULT NULL,
  `vend_prefix_id` bigint unsigned DEFAULT NULL,
  `vend_model_id` bigint unsigned DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `location_type_id` bigint unsigned DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `category_group_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `is_testing` tinyint(1) NOT NULL DEFAULT '0',
  `lcd_monitor_id` int DEFAULT NULL,
  `bill_stat` smallint DEFAULT NULL,
  `coin_stat` smallint DEFAULT NULL,
  `card_terminal_id` bigint unsigned DEFAULT NULL,
  `card_terminal_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firmware_ver` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apk_ver` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acb_rev` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `l30d_vend_earning_cents` bigint DEFAULT NULL,
  `stock_in_cents` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `omds_date_vend_unique` (`snapshot_date`,`vend_id`),
  KEY `omds_date_active` (`snapshot_date`,`is_active`),
  KEY `omds_date_operator` (`snapshot_date`,`operator_id`),
  KEY `omds_date_location` (`snapshot_date`,`location_type_id`),
  KEY `omds_date_prefix` (`snapshot_date`,`vend_prefix_id`),
  KEY `omds_date_model` (`snapshot_date`,`vend_model_id`),
  KEY `omds_date_category` (`snapshot_date`,`category_id`)
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
  `request` json DEFAULT NULL,
  `response` json DEFAULT NULL,
  `method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator_payment_gateway_id` bigint NOT NULL,
  `amount` decimal(8,2) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `payment_gateway_id` bigint DEFAULT NULL,
  `status` int DEFAULT NULL,
  `qr_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `qr_ref_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vend_id` bigint DEFAULT NULL,
  `vend_channels_json` json DEFAULT NULL,
  `txn_src` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_dispensed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `payment_gateway_logs_order_id_index` (`order_id`),
  KEY `payment_gateway_logs_operator_payment_gateway_id_index` (`operator_payment_gateway_id`),
  KEY `payment_gateway_logs_payment_gateway_id_index` (`payment_gateway_id`),
  KEY `payment_gateway_logs_vend_id_index` (`vend_id`),
  KEY `payment_gateway_logs_created_at_index` (`created_at`),
  KEY `payment_gateway_logs_approved_at_index` (`approved_at`),
  KEY `payment_gateway_logs_status_dispensed_approved_idx` (`status`,`is_dispensed`,`approved_at`)
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
  `sequence` int DEFAULT NULL,
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
DROP TABLE IF EXISTS `product_children`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_children` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_product_id` bigint unsigned NOT NULL,
  `child_product_id` bigint unsigned NOT NULL,
  `weight_pct` tinyint unsigned NOT NULL DEFAULT '0',
  `sort` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pc_parent_child_unique` (`parent_product_id`,`child_product_id`),
  KEY `pc_child_idx` (`child_product_id`),
  KEY `pc_parent_active_idx` (`parent_product_id`,`is_active`),
  CONSTRAINT `product_children_child_product_id_foreign` FOREIGN KEY (`child_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_children_parent_product_id_foreign` FOREIGN KEY (`parent_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_limits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_by` bigint unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `is_created_by_system` tinyint(1) NOT NULL DEFAULT '0',
  `product_id` bigint unsigned NOT NULL,
  `qty` int NOT NULL,
  `setup_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_limits_created_by_index` (`created_by`),
  KEY `product_limits_product_id_index` (`product_id`),
  KEY `idx_date_product_created` (`date`,`product_id`,`created_at`)
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
  `server_amount` int DEFAULT NULL,
  `selling_price_id` bigint unsigned DEFAULT NULL,
  `sequence` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_mapping_items_product_id_index` (`product_id`),
  KEY `product_mapping_items_product_mapping_id_index` (`product_mapping_id`),
  KEY `product_mapping_items_selling_price_id_index` (`selling_price_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_mapping_product_mapping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_mapping_product_mapping` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_mapping_id` bigint unsigned NOT NULL,
  `upcoming_product_mapping_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_mapping_upcoming_unique` (`product_mapping_id`,`upcoming_product_mapping_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_mapping_vend_prefix`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_mapping_vend_prefix` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_mapping_id` bigint unsigned NOT NULL,
  `vend_prefix_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_mapping_vend_prefix_product_mapping_id_index` (`product_mapping_id`),
  KEY `product_mapping_vend_prefix_vend_prefix_id_index` (`vend_prefix_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_mappings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `upcoming_product_mapping_id` bigint unsigned DEFAULT NULL,
  `upcoming_product_mapping_start_date` date DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `operator_id` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `selling_price_type` int DEFAULT NULL,
  `is_smart` tinyint(1) NOT NULL DEFAULT '0',
  `basket_layout_json` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_mappings_operator_id_index` (`operator_id`),
  KEY `product_mappings_upcoming_product_mapping_id_foreign` (`upcoming_product_mapping_id`),
  KEY `product_mappings_is_smart_index` (`is_smart`),
  CONSTRAINT `product_mappings_upcoming_product_mapping_id_foreign` FOREIGN KEY (`upcoming_product_mapping_id`) REFERENCES `product_mappings` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `type` int NOT NULL DEFAULT '1' COMMENT '1: Incoming, 2: Adjustment',
  `qty` int NOT NULL,
  `operator_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `batch_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_movements_product_id_foreign` (`product_id`),
  KEY `product_movements_user_id_foreign` (`user_id`),
  KEY `idx_pm_operator_created` (`operator_id`,`created_at`),
  KEY `idx_pm_batch_number` (`batch_number`),
  CONSTRAINT `product_movements_operator_id_foreign` FOREIGN KEY (`operator_id`) REFERENCES `operators` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_movements_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_movements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
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
DROP TABLE IF EXISTS `product_vend_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_vend_channels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `channel_count` int unsigned NOT NULL DEFAULT '0',
  `date` date NOT NULL,
  `year` smallint unsigned NOT NULL,
  `month` tinyint unsigned NOT NULL,
  `day` tinyint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pvc_product_date_unique` (`product_id`,`date`),
  KEY `pvc_product_date_count_idx` (`product_id`,`date`,`channel_count`),
  KEY `pvc_year_month_product_idx` (`year`,`month`,`product_id`,`channel_count`),
  KEY `pvc_date_idx` (`date`),
  CONSTRAINT `pvc_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nutri_grade` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `remarks_updated_by` bigint unsigned DEFAULT NULL,
  `remarks_updated_at` timestamp NULL DEFAULT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_inventory` tinyint(1) NOT NULL DEFAULT '1',
  `is_parent_sku` tinyint(1) NOT NULL DEFAULT '0',
  `is_parent_sku_since` timestamp NULL DEFAULT NULL,
  `measurement_value` int unsigned DEFAULT NULL,
  `measurement_unit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `measurement_count` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `category_id` bigint DEFAULT NULL,
  `category_group_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `is_available_updated_at` datetime DEFAULT NULL,
  `is_available_updated_by` bigint DEFAULT NULL,
  `is_commission` tinyint(1) NOT NULL DEFAULT '0',
  `is_halal` tinyint(1) NOT NULL DEFAULT '0',
  `is_healthier_choice` tinyint(1) NOT NULL DEFAULT '0',
  `is_supermarket_fee` tinyint(1) NOT NULL DEFAULT '0',
  `barcode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator_id` bigint NOT NULL DEFAULT '1',
  `cms_refer_id` bigint unsigned DEFAULT NULL,
  `translated_names_json` json DEFAULT NULL,
  `max_ops_job_pick_limit_json` json DEFAULT NULL,
  `avg_seven_days_count` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `products_is_inventory_index` (`is_inventory`),
  KEY `products_code_index` (`code`),
  KEY `products_category_id_index` (`category_id`),
  KEY `products_operator_id_index` (`operator_id`),
  KEY `products_remarks_updated_by_foreign` (`remarks_updated_by`),
  CONSTRAINT `products_remarks_updated_by_foreign` FOREIGN KEY (`remarks_updated_by`) REFERENCES `users` (`id`)
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
DROP TABLE IF EXISTS `selling_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `selling_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `amount` int NOT NULL DEFAULT '0',
  `product_id` bigint unsigned NOT NULL,
  `type` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_selling_prices_product_type_amount` (`product_id`,`type`,`amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_index_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `payment_gateway_log_refund_scanned_at` datetime DEFAULT NULL,
  `access_all_operator_ids_array` json DEFAULT NULL,
  `allow_overwrite_logo_operator_ids_array` json DEFAULT NULL,
  PRIMARY KEY (`id`)
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
  `msisdn` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telco_id` bigint NOT NULL,
  `termination_date` datetime DEFAULT NULL,
  `updated_by` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `operator_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `simcards_operator_id_index` (`operator_id`)
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
DROP TABLE IF EXISTS `stock_count_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_count_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stock_count_id` bigint NOT NULL,
  `stock_cost_amount` int NOT NULL DEFAULT '0',
  `stock_value_amount` int NOT NULL DEFAULT '0',
  `product_id` bigint NOT NULL,
  `qty_vend` int DEFAULT '0',
  `qty_warehouse` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `unit_cost_amount` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `stock_count_items_stock_count_id_index` (`stock_count_id`),
  KEY `stock_count_items_product_id_index` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_counts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_counts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cash_sales_amount` int NOT NULL DEFAULT '0',
  `cashless_sales_amount` int NOT NULL DEFAULT '0',
  `coin_float_amount` int NOT NULL DEFAULT '0',
  `customer_id` bigint unsigned DEFAULT NULL,
  `location_type_id` bigint unsigned DEFAULT NULL,
  `operator_id` bigint unsigned DEFAULT NULL,
  `product_mapping_id` bigint unsigned DEFAULT NULL,
  `vend_id` bigint unsigned DEFAULT NULL,
  `vend_code` int unsigned DEFAULT NULL,
  `vend_contract_id` bigint unsigned DEFAULT NULL,
  `vend_model_id` bigint unsigned DEFAULT NULL,
  `vend_prefix_id` bigint unsigned DEFAULT NULL,
  `day` smallint unsigned NOT NULL,
  `month` smallint unsigned NOT NULL,
  `year` smallint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_counts_created_at_index` (`created_at`),
  KEY `idx_stock_counts_ymd` (`year`,`month`,`day`),
  KEY `stock_counts_customer_id_index` (`customer_id`),
  KEY `stock_counts_location_type_id_index` (`location_type_id`),
  KEY `stock_counts_operator_id_index` (`operator_id`),
  KEY `stock_counts_product_mapping_id_index` (`product_mapping_id`),
  KEY `stock_counts_vend_id_index` (`vend_id`),
  KEY `stock_counts_vend_code_index` (`vend_code`),
  KEY `stock_counts_vend_contract_id_index` (`vend_contract_id`),
  KEY `stock_counts_vend_model_id_index` (`vend_model_id`),
  KEY `stock_counts_vend_prefix_id_index` (`vend_prefix_id`)
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
  `modelable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelable_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tag_bindings_modelable_type_modelable_id_index` (`modelable_type`,`modelable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `classname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `telescope_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries` (
  `sequence` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `family_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `should_display_on_index` tinyint(1) NOT NULL DEFAULT '1',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`sequence`),
  UNIQUE KEY `telescope_entries_uuid_unique` (`uuid`),
  KEY `telescope_entries_batch_id_index` (`batch_id`),
  KEY `telescope_entries_family_hash_index` (`family_hash`),
  KEY `telescope_entries_created_at_index` (`created_at`),
  KEY `telescope_entries_type_should_display_on_index_index` (`type`,`should_display_on_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_entries_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries_tags` (
  `entry_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tag` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`entry_uuid`,`tag`),
  KEY `telescope_entries_tags_tag_index` (`tag`),
  CONSTRAINT `telescope_entries_tags_entry_uuid_foreign` FOREIGN KEY (`entry_uuid`) REFERENCES `telescope_entries` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_monitoring`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_monitoring` (
  `tag` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`tag`)
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
  `product_mapping_id` bigint unsigned DEFAULT NULL,
  `is_blended` tinyint(1) NOT NULL DEFAULT '0',
  `profile_id` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `unit_costs_created_at_index` (`created_at`),
  KEY `unit_costs_date_from_index` (`date_from`),
  KEY `unit_costs_is_current_index` (`is_current`),
  KEY `unit_costs_product_id_index` (`product_id`),
  KEY `unit_costs_profile_id_index` (`profile_id`),
  KEY `idx_uc_current_product_cost` (`is_current`,`product_id`,`cost`),
  KEY `unit_costs_blended_current_idx` (`product_id`,`product_mapping_id`,`is_current`)
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
DROP TABLE IF EXISTS `user_page_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_page_views` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `page_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_viewed_at` timestamp NULL DEFAULT NULL,
  `unread_since` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_page_views_user_id_page_key_unique` (`user_id`,`page_key`),
  KEY `user_page_views_user_id_index` (`user_id`)
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
  `alias` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `phone_country_id` bigint DEFAULT NULL,
  `phone_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_production_status_only` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the user is only allowed to access production status',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_profile_id_index` (`profile_id`),
  KEY `users_operator_id_index` (`operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_alert_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_alert_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_id` bigint unsigned NOT NULL,
  `offline_after_minutes` int unsigned DEFAULT NULL,
  `power_restored_after_minutes` int unsigned DEFAULT NULL,
  `no_sales_after_hours` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vend_alert_settings_vend_id_unique` (`vend_id`),
  CONSTRAINT `vend_alert_settings_vend_id_foreign` FOREIGN KEY (`vend_id`) REFERENCES `vends` (`id`) ON DELETE CASCADE
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
  KEY `vend_channel_error_logs_vend_transaction_id_index` (`vend_transaction_id`),
  KEY `idx_vcel_error_created` (`vend_channel_error_id`,`created_at`),
  KEY `idx_vcel_channel_created` (`vend_channel_id`,`created_at`),
  KEY `idx_vcel_transaction_id` (`vend_transaction_id`),
  KEY `idx_vcel_created_at` (`created_at`),
  KEY `idx_created_txn` (`created_at`,`vend_transaction_id`)
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
DROP TABLE IF EXISTS `vend_channel_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_channel_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `after_data_json` json DEFAULT NULL,
  `after_data_created_at` datetime DEFAULT NULL,
  `after_label` char(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `after_statis_json` json DEFAULT NULL,
  `before_data_json` json NOT NULL,
  `before_data_created_at` datetime NOT NULL,
  `before_label` char(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `before_statis_json` json DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `stage_label` char(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stage_data_created_at` datetime DEFAULT NULL,
  `stage_data_json` json DEFAULT NULL,
  `operator_id` bigint unsigned DEFAULT NULL,
  `vend_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_channel_records_after_data_created_at_index` (`after_data_created_at`),
  KEY `vend_channel_records_before_data_created_at_index` (`before_data_created_at`),
  KEY `vend_channel_records_customer_id_index` (`customer_id`),
  KEY `vend_channel_records_operator_id_index` (`operator_id`),
  KEY `vend_channel_records_vend_id_index` (`vend_id`),
  KEY `vend_channel_records_stage_data_created_at_index` (`stage_data_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_channel_stock_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_channel_stock_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_channel_id` bigint unsigned NOT NULL,
  `vend_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `event_type` enum('sold_out','restocked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty_before` smallint unsigned DEFAULT NULL,
  `qty_after` smallint unsigned DEFAULT NULL,
  `occurred_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_channel_stock_events_product_id_occurred_at_index` (`product_id`,`occurred_at`),
  KEY `vend_channel_stock_events_vend_id_occurred_at_index` (`vend_id`,`occurred_at`),
  KEY `vend_channel_stock_events_vend_channel_id_occurred_at_index` (`vend_channel_id`,`occurred_at`),
  KEY `idx_vcse_channel_occurrence` (`vend_channel_id`,`occurred_at`),
  KEY `idx_vcse_occurrence_type` (`occurred_at`,`event_type`),
  KEY `idx_vcse_occurred_type` (`occurred_at`,`event_type`),
  KEY `idx_vcse_channel_id` (`vend_channel_id`),
  KEY `idx_vcse_optimal_stockouts` (`vend_id`,`vend_channel_id`,`id`),
  CONSTRAINT `vend_channel_stock_events_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vend_channel_stock_events_vend_channel_id_foreign` FOREIGN KEY (`vend_channel_id`) REFERENCES `vend_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vend_channel_stock_events_vend_id_foreign` FOREIGN KEY (`vend_id`) REFERENCES `vends` (`id`) ON DELETE CASCADE
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
  `error_rate_json` json NOT NULL,
  `locked_qty` int NOT NULL DEFAULT '0',
  `sku_code` int DEFAULT NULL,
  `qty_sold_at` datetime DEFAULT NULL,
  `qty_restocked_at` datetime DEFAULT NULL,
  `qty_not_available_duration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_channels_code_index` (`code`),
  KEY `vend_channels_is_active_index` (`is_active`),
  KEY `vend_channels_vend_id_index` (`vend_id`),
  KEY `vend_channels_product_id_index` (`product_id`),
  KEY `idx_vend_id_code` (`vend_id`,`code`),
  KEY `idx_vc_vid_active_cap` (`vend_id`,`is_active`,`capacity`),
  KEY `idx_vc_sku_code` (`sku_code`),
  KEY `idx_vend_active` (`vend_id`,`is_active`),
  KEY `idx_vc_vid_active_cap_prod` (`vend_id`,`is_active`,`capacity`,`product_id`),
  KEY `idx_vc_vid_active_cap_amount_qty` (`vend_id`,`is_active`,`capacity`,`amount`,`qty`),
  KEY `idx_vc_active_cap_covering` (`is_active`,`capacity`,`vend_id`,`amount`,`qty`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_config_vend_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_config_vend_config` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_config_id` bigint unsigned NOT NULL,
  `compatible_vend_config_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vend_config_compatible_unique` (`vend_config_id`,`compatible_vend_config_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_config_vend_prefix`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_config_vend_prefix` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_config_id` bigint unsigned NOT NULL,
  `vend_prefix_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_config_vend_prefix_vend_config_id_index` (`vend_config_id`),
  KEY `vend_config_vend_prefix_vend_prefix_id_index` (`vend_prefix_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `operator_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `version` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '-',
  PRIMARY KEY (`id`),
  KEY `vend_configs_operator_id_index` (`operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_contracts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
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
DROP TABLE IF EXISTS `vend_daily_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_daily_stats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_id` bigint unsigned NOT NULL,
  `vend_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `metric` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vend_daily_stats_vend_date_metric_unique` (`vend_id`,`date`,`metric`),
  KEY `vend_daily_stats_date_metric_index` (`date`,`metric`),
  KEY `vend_daily_stats_vend_code_date_index` (`vend_code`,`date`),
  KEY `vend_daily_stats_vend_code_index` (`vend_code`),
  CONSTRAINT `vend_daily_stats_vend_id_foreign` FOREIGN KEY (`vend_id`) REFERENCES `vends` (`id`) ON DELETE CASCADE
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
  PRIMARY KEY (`id`),
  KEY `vend_data_created_at_index` (`created_at`),
  KEY `vend_data_type_index` (`type`),
  KEY `vend_data_vend_code_index` (`vend_code`),
  KEY `vend_data_vend_code_created_at_index` (`vend_code`,`created_at`)
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
  KEY `vend_fans_created_at_index` (`created_at`),
  KEY `idx_vf_vid_type_created` (`vend_id`,`type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_id` bigint unsigned NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_returned` tinyint(1) NOT NULL DEFAULT '0',
  `retries_count` int NOT NULL DEFAULT '0',
  `response_at` timestamp NULL DEFAULT NULL,
  `response_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_jobs_vend_id_index` (`vend_id`),
  KEY `vend_jobs_is_returned_index` (`is_returned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_id` bigint unsigned NOT NULL,
  `event` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `context` json DEFAULT NULL,
  `occurred_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_logs_event_index` (`event`),
  KEY `vend_logs_occurred_at_index` (`occurred_at`),
  KEY `vend_logs_vend_id_occurred_at_index` (`vend_id`,`occurred_at`),
  CONSTRAINT `vend_logs_vend_id_foreign` FOREIGN KEY (`vend_id`) REFERENCES `vends` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_models` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_sortable` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
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
DROP TABLE IF EXISTS `vend_prefixes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_prefixes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `vend_config_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `operator_id` bigint unsigned DEFAULT NULL,
  `product_mapping_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_prefixes_vend_config_id_index` (`vend_config_id`),
  KEY `vend_prefixes_operator_id_index` (`operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_product_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_product_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `day` tinyint unsigned NOT NULL,
  `month` tinyint unsigned NOT NULL,
  `monthname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `year` smallint unsigned NOT NULL,
  `vend_id` bigint unsigned NOT NULL,
  `vend_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vend_prefix_id` bigint unsigned DEFAULT NULL,
  `vend_model_id` bigint unsigned DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `operator_id` bigint unsigned DEFAULT NULL,
  `location_type_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned NOT NULL,
  `product_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `category_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_group_id` bigint unsigned DEFAULT NULL,
  `category_group_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_sub_category_id` bigint unsigned DEFAULT NULL,
  `product_sub_category_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_amount` bigint NOT NULL DEFAULT '0',
  `total_count` bigint NOT NULL DEFAULT '0',
  `all_total_count` bigint NOT NULL DEFAULT '0',
  `revenue` bigint NOT NULL DEFAULT '0',
  `gross_profit` bigint NOT NULL DEFAULT '0',
  `error_count` bigint NOT NULL DEFAULT '0',
  `failure_count` bigint NOT NULL DEFAULT '0',
  `failure_amount` bigint NOT NULL DEFAULT '0',
  `online_success_amount` bigint NOT NULL DEFAULT '0',
  `online_success_count` bigint NOT NULL DEFAULT '0',
  `online_failure_amount` bigint NOT NULL DEFAULT '0',
  `online_failure_count` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vpr_vend_customer_product_date` (`vend_id`,`customer_id`,`product_id`,`date`),
  KEY `idx_vpr_operator_date_product` (`operator_id`,`date`,`product_id`),
  KEY `idx_vpr_monthly_summary` (`operator_id`,`year`,`month`,`product_id`,`vend_id`,`total_amount`,`total_count`),
  KEY `idx_vpr_product_id` (`product_id`),
  KEY `idx_vpr_product_code` (`product_code`),
  KEY `idx_vpr_category_id` (`category_id`),
  KEY `idx_vpr_category_group_id` (`category_group_id`),
  KEY `idx_vpr_product_sub_category_id` (`product_sub_category_id`),
  KEY `idx_vpr_vend_id` (`vend_id`),
  KEY `idx_vpr_customer_id` (`customer_id`),
  KEY `idx_vpr_operator_id` (`operator_id`),
  KEY `idx_vpr_location_type_id` (`location_type_id`),
  KEY `idx_vpr_vend_prefix_id` (`vend_prefix_id`),
  KEY `idx_vpr_vend_model_id` (`vend_model_id`),
  KEY `idx_vpr_date` (`date`),
  KEY `idx_vpr_year` (`year`),
  KEY `idx_vpr_month` (`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint DEFAULT NULL,
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
  `location_type_id` bigint unsigned DEFAULT NULL,
  `vend_model_id` bigint DEFAULT NULL,
  `vend_prefix_id` bigint unsigned DEFAULT NULL,
  `year` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `online_failure_amount` int NOT NULL DEFAULT '0',
  `online_failure_count` int NOT NULL DEFAULT '0',
  `online_success_amount` int NOT NULL DEFAULT '0',
  `online_success_count` int NOT NULL DEFAULT '0',
  `revenue` int NOT NULL DEFAULT '0',
  `gross_profit` int NOT NULL DEFAULT '0',
  `all_total_count` int NOT NULL DEFAULT '0',
  `error_count` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `vend_records_customer_id_index` (`customer_id`),
  KEY `vend_records_date_index` (`date`),
  KEY `vend_records_operator_id_index` (`operator_id`),
  KEY `vend_records_vend_id_index` (`vend_id`),
  KEY `vend_records_year_index` (`year`),
  KEY `vend_records_month_index` (`month`),
  KEY `vend_records_vend_prefix_id_index` (`vend_prefix_id`),
  KEY `idx_operator_date_vend` (`operator_id`,`date`,`vend_id`),
  KEY `idx_operator_year_month` (`operator_id`,`year`,`month`),
  KEY `idx_customer_operator_date` (`customer_id`,`operator_id`,`date`),
  KEY `idx_prefix_operator_date` (`vend_prefix_id`,`operator_id`,`date`),
  KEY `vend_records_vend_model_id_index` (`vend_model_id`),
  KEY `idx_location_operator_date_vend` (`location_type_id`,`operator_id`,`date`,`vend_id`),
  KEY `idx_vr_op_date` (`operator_id`,`date`),
  KEY `idx_vend_date` (`vend_id`,`date`),
  KEY `idx_customer_date` (`customer_id`,`date`),
  KEY `idx_vr_monthly_summary` (`operator_id`,`year`,`month`,`vend_id`,`total_amount`,`total_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_serial_numbers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_serial_numbers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_smart_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_smart_alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_id` bigint unsigned NOT NULL,
  `alert_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` tinyint unsigned NOT NULL DEFAULT '1',
  `meta_data` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_email_alert_sent` tinyint(1) NOT NULL DEFAULT '0',
  `email_alert_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_smart_alerts_vend_id_is_active_index` (`vend_id`,`is_active`),
  KEY `vend_smart_alerts_alert_type_is_active_index` (`alert_type`,`is_active`),
  KEY `idx_vsa_vid_atype` (`vend_id`,`alert_type`),
  CONSTRAINT `vend_smart_alerts_vend_id_foreign` FOREIGN KEY (`vend_id`) REFERENCES `vends` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint DEFAULT NULL,
  `operator_id` bigint DEFAULT NULL,
  `parameter_json` json DEFAULT NULL,
  `vend_id` bigint NOT NULL,
  `vend_code` int NOT NULL,
  `vend_channels_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_snapshots_created_at_index` (`created_at`)
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
DROP TABLE IF EXISTS `vend_temp_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_temp_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_id` bigint unsigned NOT NULL,
  `temp_type` tinyint unsigned NOT NULL,
  `period_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `min_temp_value` int DEFAULT NULL,
  `max_temp_value` int DEFAULT NULL,
  `reading_count` int unsigned NOT NULL DEFAULT '0',
  `days_covered` smallint unsigned NOT NULL DEFAULT '0',
  `min_temp_recorded_at` timestamp NULL DEFAULT NULL,
  `max_temp_recorded_at` timestamp NULL DEFAULT NULL,
  `computed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vend_temp_metrics_unique` (`vend_id`,`temp_type`,`period_type`,`period_key`),
  KEY `vend_temp_metrics_period_idx` (`period_type`,`period_start`),
  KEY `idx_vtm_sensor_period` (`temp_type`,`period_type`,`period_start`),
  KEY `idx_vtm_sensor_recorded` (`temp_type`,`min_temp_recorded_at`,`max_temp_recorded_at`),
  KEY `idx_vtm_sensor_period_vend` (`temp_type`,`period_type`,`period_start`,`vend_id`),
  CONSTRAINT `vend_temp_metrics_vend_id_foreign` FOREIGN KEY (`vend_id`) REFERENCES `vends` (`id`) ON DELETE CASCADE
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
  KEY `vend_temps_created_at_index` (`created_at`),
  KEY `idx_vt_type_vend_created` (`type`,`vend_id`,`created_at`),
  KEY `idx_vend_temps_type_created_vend` (`type`,`created_at`,`vend_id`),
  KEY `idx_vt_vid_type_created` (`vend_id`,`type`,`created_at`),
  KEY `idx_vt_optimal_trend` (`vend_id`,`type`,`value`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_transaction_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_transaction_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `is_refunded` tinyint(1) NOT NULL DEFAULT '0',
  `product_id` bigint unsigned DEFAULT NULL,
  `unit_cost` int DEFAULT NULL,
  `unit_price_amount` int DEFAULT NULL,
  `unit_cost_id` bigint unsigned DEFAULT NULL,
  `vend_channel_id` bigint unsigned NOT NULL,
  `vend_channel_error_code` int DEFAULT NULL,
  `vend_channel_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vend_channel_error_id` bigint unsigned DEFAULT NULL,
  `vend_transaction_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vend_transaction_items_vend_transaction_id_index` (`vend_transaction_id`),
  KEY `vend_transaction_items_vend_transaction_error_code_index` (`vend_transaction_id`,`vend_channel_error_code`),
  KEY `vend_transaction_items_unit_price_amount_index` (`unit_price_amount`),
  KEY `idx_vti_product_channel` (`product_id`,`vend_channel_id`),
  KEY `idx_vti_transaction_id` (`vend_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vend_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_datetime` datetime NOT NULL,
  `effective_transaction_datetime` datetime GENERATED ALWAYS AS (coalesce(`transaction_datetime`,`created_at`)) STORED,
  `amount` int NOT NULL DEFAULT '0',
  `is_zero_amount` tinyint(1) NOT NULL DEFAULT '0',
  `customer_id` bigint DEFAULT NULL,
  `operator_id` bigint DEFAULT NULL,
  `payment_method_id` bigint DEFAULT NULL,
  `cashless_mfg` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vend_channel_id` bigint NOT NULL,
  `vend_channel_error_id` bigint DEFAULT NULL,
  `vend_id` bigint NOT NULL,
  `interface_type` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `vend_transaction_json` json DEFAULT NULL,
  `meta_json` json DEFAULT NULL,
  `product_id` bigint DEFAULT NULL,
  `unit_cost_id` bigint DEFAULT NULL,
  `location_type_id` bigint DEFAULT NULL,
  `gst_vat_rate` decimal(5,2) NOT NULL,
  `is_payment_received` tinyint(1) DEFAULT NULL,
  `revenue` int DEFAULT NULL,
  `gross_profit` int DEFAULT NULL,
  `gross_profit_margin` int DEFAULT NULL,
  `unit_cost` int DEFAULT NULL,
  `vend_channel_code` int DEFAULT NULL,
  `vend_prefix_id` bigint DEFAULT NULL,
  `vend_model_id` bigint DEFAULT NULL,
  `vend_contract_id` bigint DEFAULT NULL,
  `is_refunded` tinyint(1) NOT NULL DEFAULT '0',
  `payment_gateway_log_id` bigint unsigned DEFAULT NULL,
  `error_code_normalized` int GENERATED ALWAYS AS (json_unquote(json_extract(`vend_transaction_json`,_utf8mb4'$.SErr'))) VIRTUAL,
  `is_multiple` tinyint(1) NOT NULL DEFAULT '0',
  `items_json` json DEFAULT NULL,
  `label_json` json DEFAULT NULL,
  `qty` int NOT NULL DEFAULT '1',
  `success_qty` int NOT NULL DEFAULT '0',
  `dispensed_qty` int NOT NULL DEFAULT '0',
  `is_found_in_transaction` tinyint(1) NOT NULL DEFAULT '1',
  `settlement_status` tinyint unsigned NOT NULL DEFAULT '2',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_order_id_vend_id` (`order_id`,`vend_id`),
  KEY `vend_transactions_transaction_datetime_index` (`transaction_datetime`),
  KEY `vend_transactions_vend_id_index` (`vend_id`),
  KEY `vend_transactions_order_id_index` (`order_id`),
  KEY `vend_transactions_created_at_index` (`created_at`),
  KEY `vend_transactions_product_id_index` (`product_id`),
  KEY `vend_transactions_payment_method_id_index` (`payment_method_id`),
  KEY `vend_transactions_vend_channel_code_index` (`vend_channel_code`),
  KEY `vend_transactions_amount_index` (`amount`),
  KEY `vend_transactions_customer_id_index` (`customer_id`),
  KEY `vend_transactions_vend_channel_id` (`vend_channel_id`),
  KEY `vend_transactions_payment_gateway_log_id_index` (`payment_gateway_log_id`),
  KEY `vend_transactions_operator_id_index` (`operator_id`),
  KEY `idx_operator_datetime` (`operator_id`,`transaction_datetime`),
  KEY `idx_operator_datetime_multiple` (`operator_id`,`transaction_datetime`,`is_multiple`),
  KEY `idx_order_id_vend_id` (`order_id`,`vend_id`),
  KEY `idx_vend_contract_id` (`vend_contract_id`),
  KEY `vend_transactions_effective_transaction_datetime_index` (`effective_transaction_datetime`),
  KEY `vend_transactions_is_zero_amount_index` (`is_zero_amount`),
  KEY `idx_vend_transaction_datetime` (`vend_id`,`transaction_datetime`),
  KEY `idx_datetime_error` (`transaction_datetime`,`vend_channel_error_id`),
  KEY `idx_vtrans_datetime_amount_operator` (`transaction_datetime`,`amount`,`operator_id`),
  KEY `idx_vtrans_cashless_mfg_datetime` (`cashless_mfg`,`transaction_datetime`),
  KEY `vend_transactions_error_code_normalized_index` (`error_code_normalized`)
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
DROP TABLE IF EXISTS `vend_voucher`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vend_voucher` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vend_id` bigint unsigned NOT NULL,
  `voucher_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vend_voucher_vend_id_voucher_id_unique` (`vend_id`,`voucher_id`),
  KEY `vend_voucher_vend_id_index` (`vend_id`),
  KEY `vend_voucher_voucher_id_index` (`voucher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vends`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vends` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` int NOT NULL,
  `serial_num` int DEFAULT NULL,
  `server_price_type` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temp` int DEFAULT NULL,
  `t1_lowest_48h` int DEFAULT NULL,
  `temp_updated_at` datetime DEFAULT NULL,
  `last_updated_at` datetime DEFAULT NULL,
  `coin_amount` int DEFAULT NULL,
  `firmware_ver` int DEFAULT NULL,
  `is_door_open` tinyint(1) NOT NULL DEFAULT '0',
  `is_disposed` tinyint(1) NOT NULL DEFAULT '0',
  `is_sensor_normal` tinyint(1) NOT NULL DEFAULT '0',
  `is_temp_active` tinyint(1) NOT NULL DEFAULT '0',
  `simcard_id` bigint DEFAULT NULL,
  `vend_config_id` bigint unsigned DEFAULT NULL,
  `cashless_terminal_id` bigint DEFAULT NULL,
  `card_terminal_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_temp_error` tinyint(1) NOT NULL DEFAULT '0',
  `vend_type_id` bigint DEFAULT NULL,
  `modem_unit_id` bigint unsigned DEFAULT NULL,
  `keylock_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vend_channels_json` json DEFAULT NULL,
  `original_vend_channels_json` json DEFAULT NULL,
  `vend_channel_error_logs_json` json DEFAULT NULL,
  `vend_channel_totals_json` json DEFAULT NULL,
  `parameter_json` json DEFAULT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT '0',
  `is_offline_notification_sent` tinyint(1) NOT NULL DEFAULT '0',
  `offline_notification_level` tinyint unsigned NOT NULL DEFAULT '0',
  `vend_transaction_totals_json` json DEFAULT NULL,
  `virtual_vend_records_thirty_days_amount_average` int GENERATED ALWAYS AS (json_unquote(json_extract(`vend_transaction_totals_json`,_utf8mb4'$.vend_records_thirty_days_amount_average'))) VIRTUAL,
  `product_mapping_id` bigint DEFAULT NULL,
  `binded_at` datetime DEFAULT NULL,
  `upcoming_product_mapping_id` bigint DEFAULT NULL,
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
  `is_sold` tinyint(1) NOT NULL DEFAULT '0',
  `last_ip_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mqtt_last_updated_at` datetime DEFAULT NULL,
  `is_mqtt_offline_notified` tinyint(1) NOT NULL DEFAULT '0',
  `is_mqtt_active` tinyint(1) NOT NULL DEFAULT '0',
  `customer_id` bigint DEFAULT NULL,
  `customer_movement_history_json` json DEFAULT NULL,
  `operator_id` bigint unsigned DEFAULT NULL,
  `vend_temp_alert_json` json DEFAULT NULL,
  `temp_monitoring_state` json DEFAULT NULL,
  `vend_prefix_id` bigint DEFAULT NULL,
  `vend_model_id` bigint unsigned DEFAULT NULL,
  `key_id` bigint unsigned DEFAULT NULL,
  `vend_serial_number_id` bigint unsigned DEFAULT NULL,
  `vend_vend_config_version` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '-',
  `modem_type_id` int DEFAULT NULL,
  `menu_frame_id` int DEFAULT NULL,
  `claw_machine_body_id` int DEFAULT NULL,
  `claw_machine_board_id` int DEFAULT NULL,
  `lcd_monitor_id` int DEFAULT NULL,
  `led_matrix_panel_id` int DEFAULT NULL,
  `settings_parameter_json` json DEFAULT NULL,
  `is_using_server_price` tinyint(1) NOT NULL DEFAULT '0',
  `offline_restart_count` int NOT NULL DEFAULT '0',
  `offline_restart_count_datetime` datetime DEFAULT NULL,
  `vend_contract_id` bigint DEFAULT NULL,
  `last_vend_transaction_at` timestamp NULL DEFAULT NULL,
  `last_cash_vend_transaction_at` timestamp NULL DEFAULT NULL,
  `last_card_vend_transaction_at` timestamp NULL DEFAULT NULL,
  `last_cashless_vend_transaction_at` timestamp NULL DEFAULT NULL,
  `is_txn_src` tinyint(1) NOT NULL DEFAULT '0',
  `is_enable_grab_collection` tinyint(1) DEFAULT NULL,
  `is_enable_soft_keyboard_qr_pay` tinyint(1) DEFAULT NULL,
  `is_enable_soft_keyboard_cash_pay` tinyint(1) DEFAULT NULL,
  `is_enable_soft_keyboard_credit_card_pay` tinyint(1) DEFAULT NULL,
  `is_enable_soft_keyboard_hid_pay` tinyint(1) DEFAULT NULL,
  `has_display_screen` tinyint(1) DEFAULT NULL,
  `last_txn_src_at` datetime DEFAULT NULL,
  `is_fan_enabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `vends_code_index` (`code`),
  KEY `vends_product_mapping_id_index` (`product_mapping_id`),
  KEY `vends_begin_date_index` (`begin_date`),
  KEY `vends_termination_date_index` (`termination_date`),
  KEY `vends_is_customer_index` (`is_customer`),
  KEY `vends_is_testing_index` (`is_testing`),
  KEY `vends_customer_id_index` (`customer_id`),
  KEY `vends_vend_prefix_id_index` (`vend_prefix_id`),
  KEY `vends_modem_unit_id_index` (`modem_unit_id`),
  KEY `vends_vend_contract_id_index` (`vend_contract_id`),
  KEY `idx_vends_code` (`code`),
  KEY `idx_vends_customer_id` (`customer_id`),
  KEY `idx_vends_customer_begin_created` (`customer_id`,`begin_date`,`created_at`),
  KEY `idx_vends_operator_active_testing` (`operator_id`,`is_active`,`is_testing`),
  KEY `idx_vends_is_testing` (`is_testing`),
  KEY `vends_card_terminal_id_index` (`card_terminal_id`),
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
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_redeemed` tinyint(1) NOT NULL DEFAULT '0',
  `redeemed_at` datetime DEFAULT NULL,
  `status` int NOT NULL DEFAULT '1',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT '0',
  `locked_at` datetime DEFAULT NULL,
  `locked_by_vend_id` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `voucher_items_code_unique` (`code`),
  KEY `voucher_items_voucher_id_foreign` (`voucher_id`),
  KEY `voucher_items_is_active_index` (`is_active`),
  KEY `voucher_items_locked_by_vend_id_index` (`locked_by_vend_id`),
  KEY `voucher_items_code_index` (`code`),
  CONSTRAINT `voucher_items_voucher_id_foreign` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vouchers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `date_from` datetime NOT NULL,
  `date_to` datetime NOT NULL,
  `dcvend_member_type` int DEFAULT NULL,
  `dcvend_qty_per_member` int DEFAULT NULL,
  `response_json` json DEFAULT NULL,
  `status` int NOT NULL DEFAULT '1',
  `vend_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` int DEFAULT NULL,
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_batch_code` tinyint(1) NOT NULL DEFAULT '0',
  `is_recurring` tinyint(1) DEFAULT NULL,
  `is_dcvend` tinyint(1) NOT NULL DEFAULT '0',
  `is_random_channel_sequence` tinyint(1) NOT NULL DEFAULT '0',
  `max_promo_value` int DEFAULT NULL,
  `max_redemption_count` int unsigned DEFAULT NULL,
  `min_value` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_json` json DEFAULT NULL,
  `qty` int unsigned DEFAULT NULL,
  `used_qty` int NOT NULL DEFAULT '0',
  `valid_unit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valid_duration` int DEFAULT NULL,
  `operator_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vouchers_customer_id_index` (`customer_id`),
  KEY `vouchers_vend_id_index` (`vend_id`),
  KEY `vouchers_code_index` (`code`),
  KEY `vouchers_is_active_index` (`is_active`),
  KEY `vouchers_operator_id_foreign` (`operator_id`),
  CONSTRAINT `vouchers_operator_id_foreign` FOREIGN KEY (`operator_id`) REFERENCES `operators` (`id`) ON DELETE SET NULL
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
--
-- WARNING: can't read the INFORMATION_SCHEMA.libraries table. It's most probably an old server 8.0.33.
--
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
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (340,'2024_04_03_110924_add_operator_id_vends',155);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (341,'2024_04_09_011810_add_translated_names_json_products',156);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (342,'2024_04_12_161907_add_grand_total_count_error_count_vend_records',157);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (343,'2024_04_13_095631_add_error_rate_json_vend_channels',158);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (344,'2024_04_23_233219_add_vend_channels_json_payment_gateway_logs',159);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (345,'2024_04_30_101055_add_customer_movement_history_json_vends',160);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (346,'2024_05_02_094450_alter_delivery_platform_orders_created_at_index',161);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (347,'2024_05_02_115336_alter_delivery_platform_orders_order_created_at_index',162);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (348,'2024_05_02_115706_alter_vend_records_year_month_index',162);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (349,'2024_05_02_154226_create_selling_prices_table',163);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (350,'2024_05_02_164700_create_vend_prefixes_table',163);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (351,'2024_05_02_170532_create_vend_configs_table',163);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (352,'2024_05_02_174816_create_product_mapping_vend_prefix_table',163);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (353,'2024_05_07_100126_add_operator_id_vend_prefixes_vend_configs_simcards_cashless_terminals--table=vend_prefixes',164);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (354,'2024_05_10_233016_add_vend_temp_alert_json_vends',165);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (355,'2024_05_11_043929_add_phone_country_id_phone_number_users',165);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (356,'2024_05_20_101323_drop_is_testing_customers',166);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (357,'2024_05_20_104741_add_selling_price_type_customers',166);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (358,'2024_05_27_001420_add_cashless_terminal_id_simcard_id_vend_prefix_id_vends',167);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (359,'2024_06_14_154543_create_ops_jobs_table',168);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (360,'2024_06_14_223622_create_ops_job_items_table',168);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (361,'2024_06_18_225945_create_vend_config_vend_prefix_table',169);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (362,'2024_06_19_110650_add_is_active_vend_configs_table',169);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (363,'2024_06_19_114510_add_vend_config_id_vends',169);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (364,'2024_06_19_151339_create_vend_models_table',170);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (365,'2024_06_19_154717_add_vend_model_id_vends',171);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (366,'2024_06_22_105737_create_keys_table',172);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (367,'2024_06_22_120617_add_key_id_vends',172);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (368,'2024_06_24_160726_add_product_mapping_id_vend_prefixes',173);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (369,'2024_06_27_133336_create_vend_config_vend_config_table',174);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (370,'2024_06_27_172757_add_desc_keys',175);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (371,'2024_06_27_173509_add_power_socket_key_number_customers',175);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (372,'2024_06_27_232934_create_vend_serial_numbers_table',176);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (373,'2024_06_27_235657_add_vend_serial_number_id_vends',176);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (374,'2024_07_04_124809_add_original_vend_channels_json',177);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (375,'2024_07_04_230110_add_is_available_products',178);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (376,'2024_07_05_130445_add_is_available_updated_at',179);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (377,'2024_07_12_172351_create_product_mapping_product_mapping',180);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (378,'2024_07_12_231626_add_version_vend_configs',180);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (379,'2024_07_13_094533_add_upcoming_product_mapping_id_vends',180);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (380,'2024_07_14_223408_add_vend_config_version_vends',181);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (381,'2024_07_15_120122_alter_vend_vend_config_version_vends',182);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (382,'2024_07_17_090724_drop_vend_binding_id_cleanup_vend_transactions',183);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (383,'2024_07_17_140613_drop_customer_json_vend_transactions',184);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (384,'2024_07_17_194846_add_meta_json_vend_transactions',184);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (385,'2024_07_18_141102_add_interface_type_vend_transactions',185);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (386,'2024_07_24_122046_add_is_active_users',186);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (387,'2024_07_25_182135_alter_gst_vat_rate_operators_vend_transactions',187);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (388,'2024_07_31_223857_add_map_url_addresses',188);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (389,'2024_08_01_133206_create_ops_job_item_channels_table',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (390,'2024_08_01_135629_add_cms_transaction_id_ops_job_items',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (391,'2024_08_01_141445_add_next_invoice_driver_id',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (392,'2024_08_01_231402_alter_sequence_ops_job_items',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (393,'2024_08_03_120130_add_modem_type_id_menu_frame_id_claw_machine_body_id_clay_machine_board_id_lcd_monitor_id_vends',190);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (394,'2024_08_06_134812_add_vend_channel_code_ops_job_item_channels',191);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (395,'2024_08_07_200426_add_ops_note_customers',192);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (396,'2024_08_08_123245_add_preferred_visit_days_json_customers',193);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (397,'2024_08_09_224232_alter_add_indexes_to_vends_customers',194);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (402,'2024_08_13_123024_add_is_temp_active_vends',195);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (403,'2024_08_13_130318_create_vend_channel_records_table',195);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (404,'2024_08_14_121136_add_before_label_after_label_vend_channel_records',195);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (405,'2024_08_15_120957_add_previous_ops_job_item_acc_vend_channel_record_id',196);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (406,'2024_08_15_145453_add_before_statis_json_vend_channel_records',196);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (407,'2024_08_16_125906_add_vmc_before_qty_vmc_after_qty_ops_job_item_channels',197);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (408,'2024_08_16_143657_add_is_error_settle_ops_job_item_channels',198);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (409,'2024_08_17_120710_add_acc_total_cash_cashless_promo_amount',199);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (410,'2024_08_17_141715_add_temp_cash_amount_from_vmc_ops_job_items',199);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (411,'2024_08_20_091923_add_qty_ops_job_item_channels',200);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (412,'2024_08_21_150056_add_error_settled_at_ops_job_item_channels',201);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (413,'2024_08_22_132437_add_verified_at_flagged_at_cancelled_at_ops_job_items',202);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (414,'2024_08_22_142643_add_virtual_is_error_ops_job_item_channels',202);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (415,'2024_08_23_140206_alter_vmc_before_after_qty_ops_job_item_channels',203);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (416,'2024_08_23_153524_add_is_cash_collected_ops_job_items',204);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (417,'2024_08_25_095441_add_remarks_updated_at_ops_job_items',205);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (418,'2024_08_25_221248_alter_temp_cash_amount_from_vmc_cash_amount_ops_job_items',206);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (419,'2024_08_28_111955_add_vend_prefix_id_vend_records',207);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (420,'2024_09_01_100408_create_settings_table',208);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (421,'2024_09_03_164242_add_frequency_per_week_status_customers',209);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (422,'2024_09_04_131741_add_cms_transaction_by_ops_job_items',210);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (423,'2024_09_07_082244_add_undo_buttons_ops_job_items',211);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (424,'2024_09_15_202352_add_picked_before_qty_actual_before_qty',212);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (425,'2024_09_18_113815_add_amend_logic_virtual_is_error_ops_job_item_channels',213);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (426,'2024_09_21_153751_add_max_ops_job_pick_limit_products',214);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (427,'2024_09_23_121709_add_max_ops_job_pick_limit_products',215);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (428,'2024_09_23_213146_drop_max_ops_job_pick_limit_products',215);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (429,'2024_09_24_131447_add_label_vends',216);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (430,'2024_09_25_143310_create_modems_table',217);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (431,'2024_09_25_153005_create_modem_units_table',217);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (432,'2024_09_28_092119_alter_modealable_type_id_addresses',218);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (433,'2024_09_28_164702_alter_modem_id_modem_units',218);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (434,'2024_09_28_165010_add_modem_unit_id_vends',218);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (435,'2024_09_28_205036_create_modem_types',218);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (436,'2024_10_06_100307_create_product_limits_table',219);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (437,'2024_10_07_121939_add_is_resetable_modem_types',219);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (438,'2024_10_09_094204_add_last_updated_at_is_online_modem_units',220);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (439,'2024_10_18_093246_add_amount_ops_job_item_channels',221);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (440,'2024_10_22_154314_create_campaigns_table',222);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (441,'2024_10_27_160707_add_setting_json_vends',223);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (442,'2024_11_12_132703_add_avg_seven_days_count_products',224);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (443,'2024_11_14_170728_add_classname_tags',225);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (444,'2024_11_14_172022_add_modelable_tag_bindings',225);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (445,'2024_11_16_085044_add_category_id_products',225);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (446,'2024_11_16_103431_add_server_amount_product_mappings',225);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (447,'2024_11_25_141211_add_selling_price_id_product_mapping_items',226);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (448,'2024_11_27_235216_add_selling_price_type_product_mappings',227);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (449,'2024_11_29_000633_add_led_matrix_panel_id_vends',228);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (450,'2024_11_29_155947_add_is_using_server_price_vends',229);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (451,'2024_11_30_092532_add_category_group_id_products',230);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (452,'2024_11_30_105145_create_apk_settings_table',231);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (453,'2024_11_30_111252_create_apk_setting_vends_table',231);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (454,'2024_12_09_191850_create_campaign_items_table',232);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (455,'2024_12_18_102346_add_date_from_date_to_campaign_items',233);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (456,'2024_12_25_221807_add_server_price_type_vends',234);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (457,'2025_01_04_083610_add_is_dcvend_operators',235);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (458,'2025_01_22_111208_add_is_restricted_access_customers',236);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (459,'2025_01_25_152047_add_qty_vend_transactions',237);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (460,'2025_01_25_152410_alter_payment_gateway_logs',237);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (461,'2025_01_25_153237_drop_json_payment_gateway_logs',237);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (462,'2025_01_26_000642_add_success_qty_vend_transactions',238);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (463,'2025_02_04_122908_add_sequence_payment_methods',239);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (464,'2025_02_10_162747_create_dispense_records_table',240);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (465,'2025_02_10_225117_add_offline_restart_count_datetime_vends',240);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (466,'2025_02_11_115302_alter_is_vm_receive_dispense_signal_dispense_records',241);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (467,'2025_02_13_125141_add_type_dispense_records',242);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (468,'2025_02_13_130744_drop_retries_dispense_records',242);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (469,'2025_02_13_151156_add_delivery_platform_order_id_dispense_records',242);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (470,'2025_02_24_103204_add_index_created_at_type_vend_data',243);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (471,'2025_02_27_112849_add_txn_src_payment_gateway_logs',244);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (472,'2025_03_10_180354_add_method_payment_gateway_logs',245);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (473,'2025_03_12_145521_add_is_dispense_payment_gateway_logs',246);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (474,'2025_03_13_112708_add_qr_ref_id_payment_gateway_logs',247);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (475,'2025_03_14_093643_alter_index_product_mappings',248);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (476,'2025_03_17_171730_drop_voucher_items_add_vouchers',249);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (477,'2025_03_28_091708_add_is_disposed_vends',250);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (478,'2025_03_31_120206_create_vend_contracts_table',251);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (480,'2025_03_31_130655_add_desc_vend_models',252);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (481,'2025_04_03_094341_create_delivery_platform_ref_numbers_table',253);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (482,'2025_04_04_113624_create_voucher_items',254);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (483,'2025_04_08_125600_add_payment_gateway_log_refund_scanned_at_settings',255);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (484,'2025_04_12_103001_add_operator_id_vouchers',256);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (485,'2025_04_21_093054_drop_member_id_vouchers',257);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (486,'2025_04_29_093332_add_used_qty_vouchers',258);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (487,'2025_05_02_100223_add_is_locked_locked_at_locked_by_vend_id_voucher_items',259);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (488,'2025_05_09_192855_alter_code_vouchers',260);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (489,'2025_05_19_102906_add_created_at_ops_job_items',261);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (490,'2025_05_20_091858_add_composite_indexes_to_ops_job_items_table',262);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (491,'2025_05_20_095708_add_composite_indexes_to_vend_transactions_table',262);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (492,'2025_05_21_095744_alter_status_ops_job_items',263);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (493,'2025_05_21_095936_alter_created_at_ops_job_item_channels',263);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (494,'2025_05_22_133801_add_optimized_indexes_to_vend_records',264);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (495,'2025_05_24_090008_add_vend_transaction_id_index_delivery_platform_orders',265);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (496,'2025_06_07_083706_add_voucher_dcvend_settings',266);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (497,'2025_06_07_184953_add_dcvend_qty_per_member_vouchers',266);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (498,'2025_06_19_111533_create_export_jobs_table',267);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (499,'2025_06_21_181014_create_vend_voucher_table',268);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (500,'2025_06_22_230945_alter_dcvend_qty_per_member_nullable_vouchers',268);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (501,'2025_06_23_205140_create_hid_cards_table',269);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (502,'2025_06_24_150725_add_is_random_channel_sequence_vouchers',270);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (503,'2025_06_25_132926_create_hid_card_vend_table',270);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (504,'2025_06_28_183759_add_vend_model_id_vend_records',271);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (505,'2025_06_30_095228_alter_unique_value_hid_cards',272);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (506,'2025_06_30_192845_add_name_email_hid_cards',273);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (507,'2025_07_05_094405_create_export_job_chunks_table',274);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (509,'2025_07_05_204504_add_index_ref_id_delivery_platform_menu_records',276);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (510,'2018_08_08_100000_create_telescope_entries_table',277);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (511,'2025_07_06_200815_add_composite_index_vend_transactions',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (512,'2025_07_06_204836_add_vend_transaction_order_id_index_delivery_platform_orders',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (513,'2025_07_06_210247_add_idx_vend_id_code_vend_channels',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (514,'2025_07_06_221330_add_index_delivery_product_mapping_vend_id',279);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (515,'2025_07_07_095717_add_payment_gateway_logs_status_dispensed_approved_idx',280);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (516,'2025_07_07_144315_add_location_type_id_vend_records',281);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (517,'2025_07_07_144417_add_idx_operator_location_date_vend_vend_records',281);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (518,'2025_07_17_132258_add_is_healthier_choice_is_halal_nutri_grade_products',282);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (519,'2025_07_19_115019_add_dispensed_at_final_status_at_delivery_platform_orders',283);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (520,'2025_07_19_173659_add_access_all_operator_ids_array_settings',283);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (521,'2025_07_19_175243_add_indexes_delivery_platform_orders',283);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (522,'2025_07_26_093207_add_saved_picked_qty_ops_job_item_channels',284);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (523,'2025_08_04_125202_add_is_ignore_limit_ops_job_items',285);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (524,'2025_08_07_141803_add_sequence_product_mapping_items',286);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (525,'2025_08_08_093817_alter_index_created_at_vend_snapshots',287);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (526,'2025_08_11_163101_add_extra_columns_vend_transactions',288);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (527,'2025_08_12_232309_create_stock_counts_table',289);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (528,'2025_08_12_232319_create_stock_count_items_table',289);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (529,'2025_08_15_140411_create_customer_vend_bindings_table',290);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (530,'2025_08_15_225024_add_email_recipients_json_operators',291);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (531,'2025_08_16_190630_add_unit_cost_amount_stock_count_items',292);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (532,'2025_08_16_192543_add_is_production_status_only_users',293);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (533,'2025_08_29_085339_add_remarks_delivery_platform_ref_numbers',294);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (534,'2025_09_07_231600_add_label_json_vend_transactions',295);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (535,'2025_09_12_101945_create_alert_email_items_table',296);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (536,'2025_09_13_194908_add_unique_on_customer_vend_bindings',297);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (537,'2025_09_13_211533_add_missing_columns_to_alert_email_items_table',298);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (538,'2025_09_17_000001_add_dp_ref_number_id_to_delivery_platform_orders',299);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (539,'2025_09_17_173021_alter_index_ref_number_delivery_platform_ref_numbers',299);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (540,'2025_09_23_000001_alter_campaigns_add_fields',300);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (541,'2025_09_23_000002_alter_campaign_items_add_fields',300);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (542,'2025_09_30_104106_add_vend_prefix_id_customer_vend_bindings',301);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (543,'2025_10_05_000000_create_campaign_tag_table',302);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (544,'2025_10_05_000001_add_pricing_fields_to_campaigns_table',302);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (545,'2025_10_05_120100_add_last_vend_transaction_timestamps_to_vends_table',302);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (546,'2025_10_05_130300_add_min_max_discount_to_campaigns_table',303);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (547,'2025_10_05_130400_alter_value_column_to_integer_on_campaigns_table',303);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (548,'2025_10_11_000000_add_is_using_qty_to_campaigns_table',303);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (549,'2025_10_12_000000_create_apk_setting_campaign_table',303);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (550,'2025_10_15_131852_create_vend_alert_settings_table',304);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (551,'2025_10_15_132011_add_transaction_no_entry_flag_to_alert_email_items_table',304);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (552,'2025_10_15_142359_create_vend_logs_table',305);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (553,'2025_10_25_102657_add_vend_logs_vend_id_occurred_at_index',306);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (554,'2025_10_25_200000_create_vend_temp_metrics_table',307);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (555,'2025_10_28_233045_create_gp_metrics_table',308);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (556,'2025_02_15_000001_add_dispensed_qty_to_vend_transactions',309);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (557,'2025_10_29_090000_add_allow_overwrite_logo_operator_ids_array_to_settings_table',310);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (558,'2025_11_03_150000_add_effective_transaction_datetime_to_vend_transactions',311);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (559,'2025_11_03_151500_add_vend_transaction_items_deadlock_index',311);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (560,'2025_11_03_152000_add_unit_price_amount_to_vend_transaction_items',312);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (561,'2025_11_03_160000_create_vend_channel_stock_events_table',313);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (562,'2025_11_05_090000_add_indexes_to_machine_health_tables',314);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (563,'2025_11_13_115412_add_machine_health_indexes',315);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (564,'2025_11_14_103000_add_index_to_vends_code',316);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (565,'2025_11_14_104000_add_index_to_vends_customer_id',316);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (566,'2025_11_14_110000_add_product_channel_index_to_vend_transaction_items',316);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (567,'2025_11_14_111000_add_index_to_vend_temps',316);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (568,'2025_11_23_131819_add_performance_indexes_to_tables',317);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (569,'2025_12_06_000000_create_product_movements_table',318);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (570,'2025_12_06_093433_add_is_inventory_adjusted_to_ops_job_items_table',318);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (571,'2025_12_13_115348_create_operator_callbacks_table',319);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (572,'2025_12_18_151145_add_is_zero_amount_to_vend_transactions_table',320);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (573,'2025_12_22_140826_create_vend_jobs_table',321);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (574,'2025_12_24_151801_change_payload_column_to_text_in_vend_jobs_table',322);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (575,'2025_12_31_092255_create_alerts_table',323);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (576,'2026_01_05_204559_create_daily_weather_histories_table',324);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (577,'2026_01_14_123631_add_batch_number_to_product_movements_table',325);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (578,'2026_01_14_152129_add_user_id_to_product_movements_table',325);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (579,'2026_01_14_161535_add_txn_src_columns_to_vends_table',326);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (580,'2026_01_15_165532_add_last_picked_at_to_ops_job_items_table',327);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (581,'2026_01_20_153042_add_apk_settings_booleans_to_vends_table',328);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (582,'2026_01_20_153937_add_has_display_screen_to_vends_table',328);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (583,'2026_01_30_142430_add_is_enable_soft_keyboard_hid_pay_to_vends_table',329);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (584,'2026_02_05_233818_create_vend_smart_alerts_table',330);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (585,'2026_02_06_140000_optimize_smart_alert_indexes',331);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (587,'2026_02_06_150000_optimize_app_indexes',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (588,'2026_02_06_195500_add_temp_monitoring_state_to_vends_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (589,'2026_02_07_120000_add_indexes_for_job_optimization',333);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (590,'2026_02_09_120000_add_indexes_for_detect_temp_trends',333);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (591,'2026_02_10_110840_add_indexes_for_product_availability',334);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (592,'2026_02_10_230310_add_offline_notification_level_to_vends_table',335);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (593,'2026_02_10_232106_add_is_email_alert_sent_to_vend_smart_alerts_table',335);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (594,'2026_02_11_160000_add_missing_last_picked_at_column_to_ops_job_items',336);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (595,'2026_02_22_151955_add_indexes_to_vend_fans_and_temps_for_detect_temp_trends',337);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (596,'2026_02_22_185014_add_refillable_columns_to_ops_job_items_table',338);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (597,'2026_02_25_144342_add_is_fan_enabled_to_vends_table',339);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (598,'2026_02_27_114711_create_machine_health_histories_table',340);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (599,'2026_03_02_010126_add_optimization_indexes_for_detect_temp_trends',341);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (600,'2026_03_02_142803_add_is_sortable_to_vend_models_table',342);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (601,'2026_03_03_184512_add_optimization_index_to_vend_transactions_for_total_sales',343);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (602,'2026_03_05_003424_add_amount_cents_to_gp_metrics',344);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (603,'2026_03_18_133356_add_upcoming_product_mapping_id_to_product_mappings_table',345);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (604,'2026_03_18_134726_add_stock_action_type_to_ops_job_items_table',345);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (605,'2026_03_18_135040_add_stock_action_type_to_ops_jobs_table',345);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (606,'2026_03_18_143750_add_is_upcoming_product_to_ops_job_item_channels_table',345);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (607,'2026_03_19_133600_add_msisdn_to_simcards_table',345);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (608,'2026_03_29_190133_add_remarks_to_ops_jobs_table',346);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (609,'2026_04_14_170000_add_is_sold_to_vends_table',347);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (610,'2026_04_14_220400_add_binded_at_to_vends_table',347);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (611,'2026_04_15_232800_add_remarks_fields_to_products_table',348);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (612,'2026_04_17_020000_add_t1_lowest_48h_to_vends_table',349);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (613,'2026_04_17_100000_add_vend_id_operator_date_index_to_vend_records',350);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (614,'2026_04_17_120000_add_customer_status_job_index_to_ops_job_items',351);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (615,'2026_04_17_130000_add_covering_index_to_vend_channels_for_vc_stock',351);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (616,'2026_04_17_140000_add_index_to_unit_costs_for_vc_cost',352);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (617,'2026_04_17_150000_add_covering_index_to_vend_channels_for_vc_aggregation',352);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (618,'2026_04_17_160000_add_vend_id_index_to_delivery_product_mapping_vend',352);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (619,'2026_04_17_170000_add_customer_begin_date_index_to_vends',352);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (620,'2026_04_17_180000_add_today_amount_sort_to_customers',352);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (621,'2026_04_17_190000_add_operator_active_index_to_vends',352);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (622,'2026_04_17_200000_add_morph_index_to_addresses',352);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (623,'2026_04_17_210000_add_covering_index_to_selling_prices',353);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (624,'2026_04_17_220000_add_ops_job_vend_index_to_ops_job_items',353);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (625,'2026_04_18_000000_drop_redundant_indexes_vend_temps',354);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (626,'2026_04_18_000001_add_is_testing_index_to_vends',354);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (627,'2026_04_18_100000_drop_bad_vend_operator_date_index_vend_records',355);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (628,'2026_04_18_200000_add_covering_index_for_dashboard_monthly_queries',356);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (629,'2026_04_18_300000_add_covering_index_for_vend_channel_aggregates',357);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (630,'2026_04_18_400000_add_covering_index_for_customer_index_sort_subqueries',358);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (631,'2026_04_27_000001_add_error_counts_to_gp_metrics',359);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (632,'2026_04_27_000002_add_optimization_index_to_vend_transactions',359);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (633,'2026_04_27_000001_add_manually_replaced_to_ops_job_item_channels',360);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (634,'2026_04_29_120000_create_vend_product_records_table',361);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (635,'2026_05_02_000000_add_contract_details_to_customers',361);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (636,'2026_05_02_000001_create_ops_job_tasks_table',361);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (637,'2026_05_02_000001_create_product_vend_channels_table',361);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (638,'2026_05_02_000002_add_ref_url_to_ops_job_tasks_table',361);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (639,'2026_05_02_000003_add_value_to_ops_job_tasks_table',361);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (640,'2026_05_02_000004_add_qty_to_ops_job_tasks_table',361);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (641,'2026_05_03_000000_add_contract_detail_audit_to_customers',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (642,'2026_05_03_000001_alter_sequence_ops_job_tasks_to_decimal',362);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (643,'2026_05_04_000001_add_status_to_ops_job_tasks_table',363);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (644,'2026_05_05_120000_add_cashless_mfg_to_vend_transactions',364);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (645,'2026_05_05_000000_create_customer_period_summaries_table',365);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (646,'2026_05_05_000001_create_customer_contract_logs_table',365);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (647,'2026_05_06_000000_create_job_batches_table',366);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (648,'2026_05_07_000000_swap_contract_fields_on_customers',367);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (649,'2026_05_09_000000_add_report_email_to_customers',368);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (650,'2026_05_10_120000_create_customer_period_summary_invoices_table',368);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (651,'2026_05_12_000000_add_location_grading_to_customers',369);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (652,'2026_05_13_000000_change_contract_notice_period_to_string',370);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (653,'2026_05_14_000000_add_summary_snapshot_to_cpsi_if_missing',371);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (654,'2026_05_14_080000_add_vend_code_created_at_index_vend_data',372);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (655,'2026_05_14_090000_add_notes_to_customers',373);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (656,'2026_05_14_100000_rename_cashless_providers_to_card_terminals',373);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (657,'2026_05_14_100001_add_card_terminal_id_to_vends',373);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (658,'2026_05_15_045031_backfill_customer_tag_slug_and_display_name',374);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (659,'2026_05_15_081949_add_ops_note_audit_to_customers',374);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (660,'2026_05_15_120000_create_vend_daily_stats_table',374);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (661,'2026_05_25_000000_add_external_subsidize_to_customers',375);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (662,'2026_05_25_000000_add_settlement_columns_to_vend_transactions',375);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (663,'2026_05_25_010000_add_external_subsidize_cents_to_customer_period_summaries',376);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (664,'2026_05_26_000000_add_job_count_to_customer_period_summaries',377);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (665,'2026_05_26_010000_add_lock_to_customer_period_summaries',377);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (666,'2026_05_27_000000_add_cms_mirror_fields_to_customers',378);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (667,'2026_05_27_010000_add_payment_to_and_gst_registered_to_customers',378);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (668,'2026_05_28_010000_add_segmentation_to_customer_period_summaries',378);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (669,'2026_05_28_020000_add_report_email_audit_to_customer_period_summaries',378);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (670,'2026_05_28_120000_add_paid_and_audit_to_customer_period_summaries',378);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (671,'2026_05_29_000000_add_is_billing_same_as_delivery_to_customers',379);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (672,'2026_05_29_000001_create_banks_table',379);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (673,'2026_05_29_000002_add_bank_details_to_customers',379);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (674,'2026_05_30_000000_add_company_to_contacts',379);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (675,'2026_05_31_000000_add_freeze_to_ops_job_items',380);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (676,'2026_06_01_000000_add_site_contact_to_customers',380);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (677,'2026_06_01_000001_add_address_remarks_to_customers',380);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (678,'2026_06_01_000002_add_site_alt_phone_to_customers',380);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (679,'2026_06_01_000003_make_contacts_name_nullable',380);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (680,'2026_06_04_120000_create_ops_machine_daily_snapshots_table',381);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (681,'2026_06_05_000000_add_l30d_vend_earning_to_ops_machine_daily_snapshots',381);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (682,'2026_06_09_000000_add_is_parent_sku_to_products',382);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (683,'2026_06_09_000001_create_product_mapping_item_children_table',382);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (684,'2026_06_09_000002_add_product_mapping_id_to_unit_costs',382);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (685,'2026_06_09_000003_create_ops_job_item_channel_children_table',382);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (686,'2026_06_10_000000_create_product_children_table',382);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (687,'2026_06_10_000001_add_is_parent_sku_since_to_products',382);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (688,'2026_06_10_000002_add_is_blended_to_unit_costs',382);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (689,'2026_06_12_000000_add_perf_indexes_to_product_movements_table',383);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (690,'2026_06_12_100000_add_paid_date_to_customer_period_summaries',384);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (691,'2026_06_12_110000_add_is_locked_is_paid_to_customer_period_summaries',384);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (692,'2026_06_13_000000_create_customer_scheduled_contracts_table',385);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (693,'2026_06_14_000000_add_smart_freezer_columns_to_product_mappings',386);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (694,'2026_06_16_000000_create_user_page_views_table',387);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (695,'2026_06_16_000001_add_alias_to_users_table',387);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (696,'2026_06_16_000000_add_alias_modem_types',388);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (697,'2026_06_16_000000_add_is_active_to_operators_table',388);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (698,'2026_06_17_000000_add_upcoming_product_mapping_start_date_to_product_mappings_table',389);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (699,'2026_06_18_000000_add_active_removed_dates_to_customers',390);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (700,'2026_06_18_000100_create_customer_status_logs_table',390);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (701,'2026_06_19_000000_add_contract_selling_price_type_to_customer_period_summaries',390);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (702,'2026_06_19_100000_add_waived_to_customer_period_summaries',390);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (703,'2026_06_20_000000_add_loc_fee_remarks_to_customers',391);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (704,'2026_06_24_000000_add_stock_in_to_ops_machine_daily_snapshots',392);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (705,'2026_06_24_000000_add_vend_id_to_customer_period_summaries',392);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (706,'2026_06_25_000000_create_customer_settlements_table',392);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (707,'2026_06_25_000001_add_reference_no_to_customer_settlements',393);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (708,'2026_06_25_000002_create_customer_settlement_logs_table',394);
