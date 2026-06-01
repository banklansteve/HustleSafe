-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: hustlesafe
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subject_user_id` bigint unsigned DEFAULT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `activity_logs_actor_id_foreign` (`actor_id`),
  KEY `activity_logs_subject_user_id_created_at_index` (`subject_user_id`,`created_at`),
  KEY `activity_logs_type_index` (`type`),
  CONSTRAINT `activity_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activity_logs_subject_user_id_foreign` FOREIGN KEY (`subject_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_activity_feed_events`
--

DROP TABLE IF EXISTS `admin_activity_feed_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_activity_feed_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `entities` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `amount_minor` bigint unsigned DEFAULT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `state_id` bigint unsigned DEFAULT NULL,
  `local_government_id` bigint unsigned DEFAULT NULL,
  `quest_category_id` bigint unsigned DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_activity_feed_events_actor_user_id_foreign` (`actor_user_id`),
  KEY `admin_activity_feed_events_state_id_foreign` (`state_id`),
  KEY `admin_activity_feed_events_local_government_id_foreign` (`local_government_id`),
  KEY `admin_activity_feed_events_quest_category_id_foreign` (`quest_category_id`),
  CONSTRAINT `admin_activity_feed_events_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_activity_feed_events_local_government_id_foreign` FOREIGN KEY (`local_government_id`) REFERENCES `local_governments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_activity_feed_events_quest_category_id_foreign` FOREIGN KEY (`quest_category_id`) REFERENCES `quest_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_activity_feed_events_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_activity_feed_events`
--

LOCK TABLES `admin_activity_feed_events` WRITE;
/*!40000 ALTER TABLE `admin_activity_feed_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_activity_feed_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_activity_logs`
--

DROP TABLE IF EXISTS `admin_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_activity_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `admin_activity_logs_created_at_index` (`created_at`),
  KEY `admin_activity_logs_actor_user_id_foreign` (`actor_user_id`),
  KEY `admin_activity_logs_action_index` (`action`),
  CONSTRAINT `admin_activity_logs_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_activity_logs`
--

LOCK TABLES `admin_activity_logs` WRITE;
/*!40000 ALTER TABLE `admin_activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_compliance_requests`
--

DROP TABLE IF EXISTS `admin_compliance_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_compliance_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `assigned_to_admin_id` bigint unsigned DEFAULT NULL,
  `request_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `reference` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requester_note` text COLLATE utf8mb4_unicode_ci,
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `due_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_compliance_requests_reference_unique` (`reference`),
  KEY `admin_compliance_requests_user_id_foreign` (`user_id`),
  KEY `admin_compliance_requests_assigned_to_admin_id_foreign` (`assigned_to_admin_id`),
  KEY `admin_compliance_requests_request_type_index` (`request_type`),
  KEY `admin_compliance_requests_status_index` (`status`),
  KEY `admin_compliance_requests_due_at_index` (`due_at`),
  CONSTRAINT `admin_compliance_requests_assigned_to_admin_id_foreign` FOREIGN KEY (`assigned_to_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_compliance_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_compliance_requests`
--

LOCK TABLES `admin_compliance_requests` WRITE;
/*!40000 ALTER TABLE `admin_compliance_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_compliance_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_direct_conversations`
--

DROP TABLE IF EXISTS `admin_direct_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_direct_conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_one_id` bigint unsigned NOT NULL,
  `user_two_id` bigint unsigned NOT NULL,
  `last_message_id` bigint unsigned DEFAULT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_dm_pair_unique` (`user_one_id`,`user_two_id`),
  KEY `admin_direct_conversations_user_two_id_foreign` (`user_two_id`),
  KEY `admin_direct_conversations_last_message_at_index` (`last_message_at`),
  CONSTRAINT `admin_direct_conversations_user_one_id_foreign` FOREIGN KEY (`user_one_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_direct_conversations_user_two_id_foreign` FOREIGN KEY (`user_two_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_direct_conversations`
--

LOCK TABLES `admin_direct_conversations` WRITE;
/*!40000 ALTER TABLE `admin_direct_conversations` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_direct_conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_direct_message_receipts`
--

DROP TABLE IF EXISTS `admin_direct_message_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_direct_message_receipts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_direct_message_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_dm_receipt_unique` (`admin_direct_message_id`,`user_id`),
  KEY `admin_direct_message_receipts_user_id_foreign` (`user_id`),
  CONSTRAINT `admin_direct_message_receipts_admin_direct_message_id_foreign` FOREIGN KEY (`admin_direct_message_id`) REFERENCES `admin_direct_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_direct_message_receipts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_direct_message_receipts`
--

LOCK TABLES `admin_direct_message_receipts` WRITE;
/*!40000 ALTER TABLE `admin_direct_message_receipts` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_direct_message_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_direct_messages`
--

DROP TABLE IF EXISTS `admin_direct_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_direct_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_direct_conversation_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `attachments` json DEFAULT NULL,
  `mentions` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_direct_messages_user_id_foreign` (`user_id`),
  KEY `admin_dm_msg_conv_created_idx` (`admin_direct_conversation_id`,`created_at`),
  CONSTRAINT `admin_direct_messages_admin_direct_conversation_id_foreign` FOREIGN KEY (`admin_direct_conversation_id`) REFERENCES `admin_direct_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_direct_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_direct_messages`
--

LOCK TABLES `admin_direct_messages` WRITE;
/*!40000 ALTER TABLE `admin_direct_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_direct_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_financial_ledger_entries`
--

DROP TABLE IF EXISTS `admin_financial_ledger_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_financial_ledger_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quest_id` bigint unsigned DEFAULT NULL,
  `quest_offer_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `freelancer_id` bigint unsigned DEFAULT NULL,
  `admin_user_id` bigint unsigned DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'posted',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gross_amount_minor` bigint unsigned NOT NULL DEFAULT '0',
  `fee_amount_minor` bigint unsigned NOT NULL DEFAULT '0',
  `net_amount_minor` bigint NOT NULL DEFAULT '0',
  `balance_after_minor` bigint NOT NULL DEFAULT '0',
  `paystack_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_reason` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_financial_ledger_entries_uuid_unique` (`uuid`),
  UNIQUE KEY `admin_financial_ledger_entries_reference_unique` (`reference`),
  KEY `admin_financial_ledger_entries_client_id_foreign` (`client_id`),
  KEY `admin_financial_ledger_entries_freelancer_id_foreign` (`freelancer_id`),
  KEY `admin_financial_ledger_entries_admin_user_id_foreign` (`admin_user_id`),
  KEY `admin_financial_ledger_entries_quest_id_occurred_at_index` (`quest_id`,`occurred_at`),
  KEY `admin_financial_ledger_entries_quest_offer_id_index` (`quest_offer_id`),
  KEY `admin_financial_ledger_entries_type_index` (`type`),
  KEY `admin_financial_ledger_entries_direction_index` (`direction`),
  KEY `admin_financial_ledger_entries_source_index` (`source`),
  KEY `admin_financial_ledger_entries_status_index` (`status`),
  KEY `admin_financial_ledger_entries_paystack_reference_index` (`paystack_reference`),
  KEY `admin_financial_ledger_entries_occurred_at_index` (`occurred_at`),
  CONSTRAINT `admin_financial_ledger_entries_admin_user_id_foreign` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_financial_ledger_entries_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_financial_ledger_entries_freelancer_id_foreign` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_financial_ledger_entries_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_financial_ledger_entries`
--

LOCK TABLES `admin_financial_ledger_entries` WRITE;
/*!40000 ALTER TABLE `admin_financial_ledger_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_financial_ledger_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_fraud_cases`
--

DROP TABLE IF EXISTS `admin_fraud_cases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_fraud_cases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `assigned_to_admin_id` bigint unsigned DEFAULT NULL,
  `case_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `risk_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `risk_score` tinyint unsigned NOT NULL DEFAULT '0',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `summary` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `signals` json DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_fraud_cases_case_number_unique` (`case_number`),
  KEY `admin_fraud_cases_user_id_foreign` (`user_id`),
  KEY `admin_fraud_cases_assigned_to_admin_id_foreign` (`assigned_to_admin_id`),
  KEY `admin_fraud_cases_risk_type_index` (`risk_type`),
  KEY `admin_fraud_cases_risk_score_index` (`risk_score`),
  KEY `admin_fraud_cases_status_index` (`status`),
  CONSTRAINT `admin_fraud_cases_assigned_to_admin_id_foreign` FOREIGN KEY (`assigned_to_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_fraud_cases_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_fraud_cases`
--

LOCK TABLES `admin_fraud_cases` WRITE;
/*!40000 ALTER TABLE `admin_fraud_cases` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_fraud_cases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_notifications`
--

DROP TABLE IF EXISTS `admin_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` bigint unsigned DEFAULT NULL,
  `category` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `action_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data` json DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `snoozed_until` timestamp NULL DEFAULT NULL,
  `actioned_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_notifications_admin_user_id_foreign` (`admin_user_id`),
  KEY `admin_notifications_category_index` (`category`),
  KEY `admin_notifications_priority_index` (`priority`),
  KEY `admin_notifications_read_at_index` (`read_at`),
  KEY `admin_notifications_snoozed_until_index` (`snoozed_until`),
  KEY `admin_notifications_actioned_at_index` (`actioned_at`),
  CONSTRAINT `admin_notifications_admin_user_id_foreign` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_notifications`
--

LOCK TABLES `admin_notifications` WRITE;
/*!40000 ALTER TABLE `admin_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_platform_settings`
--

DROP TABLE IF EXISTS `admin_platform_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_platform_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `section` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json DEFAULT NULL,
  `is_sensitive` tinyint(1) NOT NULL DEFAULT '0',
  `updated_by_admin_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_platform_settings_key_unique` (`key`),
  KEY `admin_platform_settings_updated_by_admin_id_foreign` (`updated_by_admin_id`),
  KEY `admin_platform_settings_section_index` (`section`),
  CONSTRAINT `admin_platform_settings_updated_by_admin_id_foreign` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_platform_settings`
--

LOCK TABLES `admin_platform_settings` WRITE;
/*!40000 ALTER TABLE `admin_platform_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_platform_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_proposal_flags`
--

DROP TABLE IF EXISTS `admin_proposal_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_proposal_flags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_offer_id` bigint unsigned NOT NULL,
  `created_by_admin_id` bigint unsigned DEFAULT NULL,
  `assigned_to_admin_id` bigint unsigned DEFAULT NULL,
  `assigned_group` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `visibility_impact` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `due_at` date DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `resolution_outcome` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution_note` text COLLATE utf8mb4_unicode_ci,
  `resolved_by_admin_id` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_proposal_flags_quest_offer_id_foreign` (`quest_offer_id`),
  KEY `admin_proposal_flags_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `admin_proposal_flags_assigned_to_admin_id_foreign` (`assigned_to_admin_id`),
  KEY `admin_proposal_flags_resolved_by_admin_id_foreign` (`resolved_by_admin_id`),
  KEY `admin_proposal_flags_type_index` (`type`),
  KEY `admin_proposal_flags_priority_index` (`priority`),
  KEY `admin_proposal_flags_status_index` (`status`),
  CONSTRAINT `admin_proposal_flags_assigned_to_admin_id_foreign` FOREIGN KEY (`assigned_to_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_proposal_flags_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_proposal_flags_quest_offer_id_foreign` FOREIGN KEY (`quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_proposal_flags_resolved_by_admin_id_foreign` FOREIGN KEY (`resolved_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_proposal_flags`
--

LOCK TABLES `admin_proposal_flags` WRITE;
/*!40000 ALTER TABLE `admin_proposal_flags` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_proposal_flags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_proposal_notes`
--

DROP TABLE IF EXISTS `admin_proposal_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_proposal_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_offer_id` bigint unsigned NOT NULL,
  `admin_id` bigint unsigned DEFAULT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_proposal_notes_quest_offer_id_foreign` (`quest_offer_id`),
  KEY `admin_proposal_notes_admin_id_foreign` (`admin_id`),
  KEY `admin_proposal_notes_parent_id_foreign` (`parent_id`),
  KEY `admin_proposal_notes_is_pinned_index` (`is_pinned`),
  CONSTRAINT `admin_proposal_notes_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_proposal_notes_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `admin_proposal_notes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_proposal_notes_quest_offer_id_foreign` FOREIGN KEY (`quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_proposal_notes`
--

LOCK TABLES `admin_proposal_notes` WRITE;
/*!40000 ALTER TABLE `admin_proposal_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_proposal_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_proposal_notices`
--

DROP TABLE IF EXISTS `admin_proposal_notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_proposal_notices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_offer_id` bigint unsigned NOT NULL,
  `created_by_admin_id` bigint unsigned DEFAULT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'informational',
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `visible_to_freelancer` tinyint(1) NOT NULL DEFAULT '1',
  `visible_to_client` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_proposal_notices_quest_offer_id_foreign` (`quest_offer_id`),
  KEY `admin_proposal_notices_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `admin_proposal_notices_visible_to_freelancer_index` (`visible_to_freelancer`),
  KEY `admin_proposal_notices_visible_to_client_index` (`visible_to_client`),
  CONSTRAINT `admin_proposal_notices_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_proposal_notices_quest_offer_id_foreign` FOREIGN KEY (`quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_proposal_notices`
--

LOCK TABLES `admin_proposal_notices` WRITE;
/*!40000 ALTER TABLE `admin_proposal_notices` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_proposal_notices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_quest_flags`
--

DROP TABLE IF EXISTS `admin_quest_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_quest_flags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `created_by_admin_id` bigint unsigned NOT NULL,
  `assigned_to_admin_id` bigint unsigned DEFAULT NULL,
  `assigned_group` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `due_at` date DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `resolution_outcome` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution_note` text COLLATE utf8mb4_unicode_ci,
  `resolved_by_admin_id` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_quest_flags_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `admin_quest_flags_assigned_to_admin_id_foreign` (`assigned_to_admin_id`),
  KEY `admin_quest_flags_resolved_by_admin_id_foreign` (`resolved_by_admin_id`),
  KEY `admin_quest_flags_quest_id_status_priority_index` (`quest_id`,`status`,`priority`),
  KEY `admin_quest_flags_assigned_group_index` (`assigned_group`),
  KEY `admin_quest_flags_type_index` (`type`),
  KEY `admin_quest_flags_priority_index` (`priority`),
  KEY `admin_quest_flags_due_at_index` (`due_at`),
  KEY `admin_quest_flags_status_index` (`status`),
  CONSTRAINT `admin_quest_flags_assigned_to_admin_id_foreign` FOREIGN KEY (`assigned_to_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_quest_flags_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_quest_flags_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_quest_flags_resolved_by_admin_id_foreign` FOREIGN KEY (`resolved_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_quest_flags`
--

LOCK TABLES `admin_quest_flags` WRITE;
/*!40000 ALTER TABLE `admin_quest_flags` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_quest_flags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_quest_notes`
--

DROP TABLE IF EXISTS `admin_quest_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_quest_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `admin_id` bigint unsigned DEFAULT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_quest_notes_quest_id_foreign` (`quest_id`),
  KEY `admin_quest_notes_admin_id_foreign` (`admin_id`),
  KEY `admin_quest_notes_parent_id_foreign` (`parent_id`),
  KEY `admin_quest_notes_is_pinned_index` (`is_pinned`),
  CONSTRAINT `admin_quest_notes_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_quest_notes_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `admin_quest_notes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_quest_notes_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_quest_notes`
--

LOCK TABLES `admin_quest_notes` WRITE;
/*!40000 ALTER TABLE `admin_quest_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_quest_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_quest_notices`
--

DROP TABLE IF EXISTS `admin_quest_notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_quest_notices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `created_by_admin_id` bigint unsigned DEFAULT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'informational',
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `visible_to_users` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_quest_notices_quest_id_foreign` (`quest_id`),
  KEY `admin_quest_notices_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `admin_quest_notices_visible_to_users_index` (`visible_to_users`),
  CONSTRAINT `admin_quest_notices_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_quest_notices_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_quest_notices`
--

LOCK TABLES `admin_quest_notices` WRITE;
/*!40000 ALTER TABLE `admin_quest_notices` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_quest_notices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_report_category_daily_metrics`
--

DROP TABLE IF EXISTS `admin_report_category_daily_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_report_category_daily_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `metric_date` date NOT NULL,
  `category_id` bigint unsigned NOT NULL DEFAULT '0',
  `state_id` bigint unsigned NOT NULL DEFAULT '0',
  `local_government_id` bigint unsigned NOT NULL DEFAULT '0',
  `jobs_posted` int unsigned NOT NULL DEFAULT '0',
  `jobs_completed` int unsigned NOT NULL DEFAULT '0',
  `hires` int unsigned NOT NULL DEFAULT '0',
  `proposal_volume` int unsigned NOT NULL DEFAULT '0',
  `freelancer_availability` int unsigned NOT NULL DEFAULT '0',
  `budget_sum_minor` bigint unsigned NOT NULL DEFAULT '0',
  `revenue_minor` bigint unsigned NOT NULL DEFAULT '0',
  `disputes` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_report_category_daily_unique` (`metric_date`,`category_id`,`state_id`,`local_government_id`),
  KEY `admin_report_category_daily_metrics_metric_date_index` (`metric_date`),
  KEY `admin_report_category_daily_metrics_category_id_index` (`category_id`),
  KEY `admin_report_category_daily_metrics_state_id_index` (`state_id`),
  KEY `admin_report_category_daily_metrics_local_government_id_index` (`local_government_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_report_category_daily_metrics`
--

LOCK TABLES `admin_report_category_daily_metrics` WRITE;
/*!40000 ALTER TABLE `admin_report_category_daily_metrics` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_report_category_daily_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_report_exports`
--

DROP TABLE IF EXISTS `admin_report_exports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_report_exports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_saved_report_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `report_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `report_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `format` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payload` json DEFAULT NULL,
  `disk` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error` text COLLATE utf8mb4_unicode_ci,
  `completed_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_report_exports_admin_saved_report_id_foreign` (`admin_saved_report_id`),
  KEY `admin_report_exports_user_id_foreign` (`user_id`),
  KEY `admin_report_exports_status_index` (`status`),
  KEY `admin_report_exports_expires_at_index` (`expires_at`),
  CONSTRAINT `admin_report_exports_admin_saved_report_id_foreign` FOREIGN KEY (`admin_saved_report_id`) REFERENCES `admin_saved_reports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_report_exports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_report_exports`
--

LOCK TABLES `admin_report_exports` WRITE;
/*!40000 ALTER TABLE `admin_report_exports` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_report_exports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_report_location_daily_metrics`
--

DROP TABLE IF EXISTS `admin_report_location_daily_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_report_location_daily_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `metric_date` date NOT NULL,
  `state_id` bigint unsigned NOT NULL DEFAULT '0',
  `local_government_id` bigint unsigned NOT NULL DEFAULT '0',
  `freelancers` int unsigned NOT NULL DEFAULT '0',
  `clients` int unsigned NOT NULL DEFAULT '0',
  `jobs_posted` int unsigned NOT NULL DEFAULT '0',
  `jobs_completed` int unsigned NOT NULL DEFAULT '0',
  `spend_minor` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_report_location_daily_unique` (`metric_date`,`state_id`,`local_government_id`),
  KEY `admin_report_location_daily_metrics_metric_date_index` (`metric_date`),
  KEY `admin_report_location_daily_metrics_state_id_index` (`state_id`),
  KEY `admin_report_location_daily_metrics_local_government_id_index` (`local_government_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_report_location_daily_metrics`
--

LOCK TABLES `admin_report_location_daily_metrics` WRITE;
/*!40000 ALTER TABLE `admin_report_location_daily_metrics` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_report_location_daily_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_report_platform_daily_metrics`
--

DROP TABLE IF EXISTS `admin_report_platform_daily_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_report_platform_daily_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `metric_date` date NOT NULL,
  `new_users` int unsigned NOT NULL DEFAULT '0',
  `active_users` int unsigned NOT NULL DEFAULT '0',
  `jobs_posted` int unsigned NOT NULL DEFAULT '0',
  `jobs_completed` int unsigned NOT NULL DEFAULT '0',
  `messages_sent` int unsigned NOT NULL DEFAULT '0',
  `escrow_funded_minor` bigint unsigned NOT NULL DEFAULT '0',
  `escrow_released_minor` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_report_platform_daily_metrics_metric_date_unique` (`metric_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_report_platform_daily_metrics`
--

LOCK TABLES `admin_report_platform_daily_metrics` WRITE;
/*!40000 ALTER TABLE `admin_report_platform_daily_metrics` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_report_platform_daily_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_report_revenue_daily_metrics`
--

DROP TABLE IF EXISTS `admin_report_revenue_daily_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_report_revenue_daily_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `metric_date` date NOT NULL,
  `fee_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` bigint unsigned NOT NULL DEFAULT '0',
  `state_id` bigint unsigned NOT NULL DEFAULT '0',
  `user_segment` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `revenue_minor` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_report_revenue_daily_unique` (`metric_date`,`fee_type`,`category_id`,`state_id`,`user_segment`),
  KEY `admin_report_revenue_daily_metrics_metric_date_index` (`metric_date`),
  KEY `admin_report_revenue_daily_metrics_fee_type_index` (`fee_type`),
  KEY `admin_report_revenue_daily_metrics_category_id_index` (`category_id`),
  KEY `admin_report_revenue_daily_metrics_state_id_index` (`state_id`),
  KEY `admin_report_revenue_daily_metrics_user_segment_index` (`user_segment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_report_revenue_daily_metrics`
--

LOCK TABLES `admin_report_revenue_daily_metrics` WRITE;
/*!40000 ALTER TABLE `admin_report_revenue_daily_metrics` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_report_revenue_daily_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_report_user_daily_metrics`
--

DROP TABLE IF EXISTS `admin_report_user_daily_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_report_user_daily_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `metric_date` date NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `user_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` bigint unsigned NOT NULL DEFAULT '0',
  `state_id` bigint unsigned DEFAULT NULL,
  `local_government_id` bigint unsigned DEFAULT NULL,
  `jobs_started` int unsigned NOT NULL DEFAULT '0',
  `jobs_completed` int unsigned NOT NULL DEFAULT '0',
  `jobs_disputed` int unsigned NOT NULL DEFAULT '0',
  `proposals_sent` int unsigned NOT NULL DEFAULT '0',
  `proposals_viewed` int unsigned NOT NULL DEFAULT '0',
  `proposals_shortlisted` int unsigned NOT NULL DEFAULT '0',
  `proposals_accepted` int unsigned NOT NULL DEFAULT '0',
  `earnings_minor` bigint unsigned NOT NULL DEFAULT '0',
  `spend_minor` bigint unsigned NOT NULL DEFAULT '0',
  `rating_sum` decimal(10,2) NOT NULL DEFAULT '0.00',
  `rating_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_report_user_daily_unique` (`metric_date`,`user_id`,`user_type`,`category_id`),
  KEY `admin_report_user_daily_metrics_metric_date_index` (`metric_date`),
  KEY `admin_report_user_daily_metrics_user_id_index` (`user_id`),
  KEY `admin_report_user_daily_metrics_user_type_index` (`user_type`),
  KEY `admin_report_user_daily_metrics_category_id_index` (`category_id`),
  KEY `admin_report_user_daily_metrics_state_id_index` (`state_id`),
  KEY `admin_report_user_daily_metrics_local_government_id_index` (`local_government_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_report_user_daily_metrics`
--

LOCK TABLES `admin_report_user_daily_metrics` WRITE;
/*!40000 ALTER TABLE `admin_report_user_daily_metrics` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_report_user_daily_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_risk_rules`
--

DROP TABLE IF EXISTS `admin_risk_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_risk_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` tinyint unsigned NOT NULL DEFAULT '50',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `conditions` json DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_risk_rules_category_index` (`category`),
  KEY `admin_risk_rules_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_risk_rules`
--

LOCK TABLES `admin_risk_rules` WRITE;
/*!40000 ALTER TABLE `admin_risk_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_risk_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_saved_reports`
--

DROP TABLE IF EXISTS `admin_saved_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_saved_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `report_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `builder_config` json DEFAULT NULL,
  `filters` json DEFAULT NULL,
  `date_preset` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'last_30_days',
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `schedule_frequency` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schedule_recipients` json DEFAULT NULL,
  `last_run_at` timestamp NULL DEFAULT NULL,
  `next_run_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_saved_reports_user_id_foreign` (`user_id`),
  KEY `admin_saved_reports_report_type_index` (`report_type`),
  KEY `admin_saved_reports_schedule_frequency_index` (`schedule_frequency`),
  KEY `admin_saved_reports_next_run_at_index` (`next_run_at`),
  CONSTRAINT `admin_saved_reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_saved_reports`
--

LOCK TABLES `admin_saved_reports` WRITE;
/*!40000 ALTER TABLE `admin_saved_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_saved_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_tasks`
--

DROP TABLE IF EXISTS `admin_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_by_admin_id` bigint unsigned NOT NULL,
  `assigned_to_admin_id` bigint unsigned DEFAULT NULL,
  `source_type` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `priority` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'todo',
  `due_at` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_tasks_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `admin_tasks_assigned_to_admin_id_foreign` (`assigned_to_admin_id`),
  KEY `admin_tasks_source_type_index` (`source_type`),
  KEY `admin_tasks_source_id_index` (`source_id`),
  KEY `admin_tasks_priority_index` (`priority`),
  KEY `admin_tasks_status_index` (`status`),
  KEY `admin_tasks_due_at_index` (`due_at`),
  CONSTRAINT `admin_tasks_assigned_to_admin_id_foreign` FOREIGN KEY (`assigned_to_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_tasks_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_tasks`
--

LOCK TABLES `admin_tasks` WRITE;
/*!40000 ALTER TABLE `admin_tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_user_badge_user`
--

DROP TABLE IF EXISTS `admin_user_badge_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_user_badge_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `admin_user_badge_id` bigint unsigned NOT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_user_badge_user_user_id_admin_user_badge_id_unique` (`user_id`,`admin_user_badge_id`),
  KEY `admin_user_badge_user_admin_user_badge_id_foreign` (`admin_user_badge_id`),
  KEY `admin_user_badge_user_assigned_by_foreign` (`assigned_by`),
  CONSTRAINT `admin_user_badge_user_admin_user_badge_id_foreign` FOREIGN KEY (`admin_user_badge_id`) REFERENCES `admin_user_badges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_user_badge_user_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_user_badge_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_user_badge_user`
--

LOCK TABLES `admin_user_badge_user` WRITE;
/*!40000 ALTER TABLE `admin_user_badge_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_user_badge_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_user_badges`
--

DROP TABLE IF EXISTS `admin_user_badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_user_badges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_user_badges_name_unique` (`name`),
  UNIQUE KEY `admin_user_badges_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_user_badges`
--

LOCK TABLES `admin_user_badges` WRITE;
/*!40000 ALTER TABLE `admin_user_badges` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_user_badges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_user_notes`
--

DROP TABLE IF EXISTS `admin_user_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_user_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `admin_user_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_user_notes_user_id_foreign` (`user_id`),
  KEY `admin_user_notes_admin_user_id_foreign` (`admin_user_id`),
  CONSTRAINT `admin_user_notes_admin_user_id_foreign` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_user_notes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_user_notes`
--

LOCK TABLES `admin_user_notes` WRITE;
/*!40000 ALTER TABLE `admin_user_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_user_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_user_sanctions`
--

DROP TABLE IF EXISTS `admin_user_sanctions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_user_sanctions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `admin_user_id` bigint unsigned NOT NULL,
  `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `reversed_by` bigint unsigned DEFAULT NULL,
  `reversal_reason` text COLLATE utf8mb4_unicode_ci,
  `user_acknowledged_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_user_sanctions_user_id_foreign` (`user_id`),
  KEY `admin_user_sanctions_admin_user_id_foreign` (`admin_user_id`),
  KEY `admin_user_sanctions_reversed_by_foreign` (`reversed_by`),
  KEY `admin_user_sanctions_type_index` (`type`),
  KEY `admin_user_sanctions_reason_code_index` (`reason_code`),
  KEY `admin_user_sanctions_ends_at_index` (`ends_at`),
  CONSTRAINT `admin_user_sanctions_admin_user_id_foreign` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_user_sanctions_reversed_by_foreign` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_user_sanctions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_user_sanctions`
--

LOCK TABLES `admin_user_sanctions` WRITE;
/*!40000 ALTER TABLE `admin_user_sanctions` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_user_sanctions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_user_segments`
--

DROP TABLE IF EXISTS `admin_user_segments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_user_segments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filters` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_user_segments_admin_user_id_foreign` (`admin_user_id`),
  CONSTRAINT `admin_user_segments_admin_user_id_foreign` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_user_segments`
--

LOCK TABLES `admin_user_segments` WRITE;
/*!40000 ALTER TABLE `admin_user_segments` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_user_segments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_user_tag_user`
--

DROP TABLE IF EXISTS `admin_user_tag_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_user_tag_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `admin_user_tag_id` bigint unsigned NOT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_user_tag_user_user_id_admin_user_tag_id_unique` (`user_id`,`admin_user_tag_id`),
  KEY `admin_user_tag_user_admin_user_tag_id_foreign` (`admin_user_tag_id`),
  KEY `admin_user_tag_user_assigned_by_foreign` (`assigned_by`),
  CONSTRAINT `admin_user_tag_user_admin_user_tag_id_foreign` FOREIGN KEY (`admin_user_tag_id`) REFERENCES `admin_user_tags` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_user_tag_user_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_user_tag_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_user_tag_user`
--

LOCK TABLES `admin_user_tag_user` WRITE;
/*!40000 ALTER TABLE `admin_user_tag_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_user_tag_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_user_tags`
--

DROP TABLE IF EXISTS `admin_user_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_user_tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'teal',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_user_tags_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_user_tags`
--

LOCK TABLES `admin_user_tags` WRITE;
/*!40000 ALTER TABLE `admin_user_tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_user_tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcement_banners`
--

DROP TABLE IF EXISTS `announcement_banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `announcement_banners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `message` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `link_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brand',
  `segment` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `dismissible` tinyint(1) NOT NULL DEFAULT '1',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `announcement_banners_created_by_foreign` (`created_by`),
  KEY `announcement_banners_updated_by_foreign` (`updated_by`),
  KEY `announcement_banners_color_index` (`color`),
  KEY `announcement_banners_segment_index` (`segment`),
  KEY `announcement_banners_starts_at_index` (`starts_at`),
  KEY `announcement_banners_ends_at_index` (`ends_at`),
  KEY `announcement_banners_status_index` (`status`),
  CONSTRAINT `announcement_banners_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `announcement_banners_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcement_banners`
--

LOCK TABLES `announcement_banners` WRITE;
/*!40000 ALTER TABLE `announcement_banners` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcement_banners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content_reports`
--

DROP TABLE IF EXISTS `content_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `content_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `moderation_case_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `reportable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reportable_id` bigint unsigned NOT NULL,
  `reason` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `intake_channel` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_app',
  `details` text COLLATE utf8mb4_unicode_ci,
  `evidence_url` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `content_reports_moderation_case_id_foreign` (`moderation_case_id`),
  KEY `content_reports_user_id_foreign` (`user_id`),
  KEY `content_reports_reportable_idx` (`reportable_type`,`reportable_id`),
  KEY `content_reports_status_created_idx` (`status`,`created_at`),
  KEY `content_reports_status_index` (`status`),
  KEY `content_reports_severity_index` (`severity`),
  CONSTRAINT `content_reports_moderation_case_id_foreign` FOREIGN KEY (`moderation_case_id`) REFERENCES `moderation_cases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `content_reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `content_reports`
--

LOCK TABLES `content_reports` WRITE;
/*!40000 ALTER TABLE `content_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `content_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content_versions`
--

DROP TABLE IF EXISTS `content_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `content_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `versionable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `versionable_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `snapshot` json NOT NULL,
  `change_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `content_versions_versionable_idx` (`versionable_type`,`versionable_id`),
  KEY `content_versions_created_by_foreign` (`created_by`),
  CONSTRAINT `content_versions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `content_versions`
--

LOCK TABLES `content_versions` WRITE;
/*!40000 ALTER TABLE `content_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `content_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversation_message_flags`
--

DROP TABLE IF EXISTS `conversation_message_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_message_flags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_conversation_thread_id` bigint unsigned NOT NULL,
  `quest_conversation_message_id` bigint unsigned NOT NULL,
  `proposal_clarification_thread_id` bigint unsigned DEFAULT NULL,
  `proposal_clarification_message_id` bigint unsigned DEFAULT NULL,
  `sender_user_id` bigint unsigned NOT NULL,
  `quest_id` bigint unsigned DEFAULT NULL,
  `quest_offer_id` bigint unsigned DEFAULT NULL,
  `trigger_category` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `matched_pattern_redacted` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `confidence` decimal(4,3) NOT NULL DEFAULT '1.000',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `flagged_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversation_message_flags_quest_id_foreign` (`quest_id`),
  KEY `conversation_message_flags_quest_offer_id_foreign` (`quest_offer_id`),
  KEY `conv_msg_flags_thread_status_idx` (`quest_conversation_thread_id`,`status`),
  KEY `conv_msg_flags_sender_date_idx` (`sender_user_id`,`flagged_at`),
  KEY `conversation_message_flags_trigger_category_index` (`trigger_category`),
  KEY `conversation_message_flags_status_index` (`status`),
  KEY `conversation_message_flags_flagged_at_index` (`flagged_at`),
  KEY `conv_msg_flags_clarify_message_fk` (`proposal_clarification_message_id`),
  KEY `conv_msg_flags_clarify_thread_status_idx` (`proposal_clarification_thread_id`,`status`),
  KEY `conv_msg_flags_quest_message_fk` (`quest_conversation_message_id`),
  CONSTRAINT `conv_msg_flags_clarify_message_fk` FOREIGN KEY (`proposal_clarification_message_id`) REFERENCES `proposal_clarification_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conv_msg_flags_clarify_thread_fk` FOREIGN KEY (`proposal_clarification_thread_id`) REFERENCES `proposal_clarification_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conv_msg_flags_quest_message_fk` FOREIGN KEY (`quest_conversation_message_id`) REFERENCES `quest_conversation_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conv_msg_flags_quest_thread_fk` FOREIGN KEY (`quest_conversation_thread_id`) REFERENCES `quest_conversation_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_message_flags_quest_conversation_message_id_foreign` FOREIGN KEY (`quest_conversation_message_id`) REFERENCES `quest_conversation_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_message_flags_quest_conversation_thread_id_foreign` FOREIGN KEY (`quest_conversation_thread_id`) REFERENCES `quest_conversation_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_message_flags_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversation_message_flags_quest_offer_id_foreign` FOREIGN KEY (`quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversation_message_flags_sender_user_id_foreign` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversation_message_flags`
--

LOCK TABLES `conversation_message_flags` WRITE;
/*!40000 ALTER TABLE `conversation_message_flags` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversation_message_flags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversation_monitoring_terms`
--

DROP TABLE IF EXISTS `conversation_monitoring_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_monitoring_terms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `term_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pattern` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_wildcard` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `locale_hint` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversation_monitoring_terms_created_by_foreign` (`created_by`),
  KEY `conversation_monitoring_terms_term_type_index` (`term_type`),
  KEY `conversation_monitoring_terms_is_active_index` (`is_active`),
  CONSTRAINT `conversation_monitoring_terms_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversation_monitoring_terms`
--

LOCK TABLES `conversation_monitoring_terms` WRITE;
/*!40000 ALTER TABLE `conversation_monitoring_terms` DISABLE KEYS */;
INSERT INTO `conversation_monitoring_terms` VALUES (1,'abusive_blacklist','scam',0,1,NULL,NULL,'2026-05-31 18:28:50','2026-05-31 18:28:50'),(2,'abusive_blacklist','fraudster',0,1,NULL,NULL,'2026-05-31 18:28:50','2026-05-31 18:28:50'),(3,'abusive_blacklist','threat',0,1,NULL,NULL,'2026-05-31 18:28:50','2026-05-31 18:28:50'),(4,'custom_keyword','wire transfer',0,1,NULL,NULL,'2026-05-31 18:28:50','2026-05-31 18:28:50');
/*!40000 ALTER TABLE `conversation_monitoring_terms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversation_policy_warnings`
--

DROP TABLE IF EXISTS `conversation_policy_warnings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_policy_warnings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `thread_review_id` bigint unsigned DEFAULT NULL,
  `issued_by` bigint unsigned NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversation_policy_warnings_user_id_foreign` (`user_id`),
  KEY `conversation_policy_warnings_thread_review_id_foreign` (`thread_review_id`),
  KEY `conversation_policy_warnings_issued_by_foreign` (`issued_by`),
  CONSTRAINT `conversation_policy_warnings_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_policy_warnings_thread_review_id_foreign` FOREIGN KEY (`thread_review_id`) REFERENCES `conversation_thread_reviews` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversation_policy_warnings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversation_policy_warnings`
--

LOCK TABLES `conversation_policy_warnings` WRITE;
/*!40000 ALTER TABLE `conversation_policy_warnings` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversation_policy_warnings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversation_systematic_escalations`
--

DROP TABLE IF EXISTS `conversation_systematic_escalations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_systematic_escalations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `trigger_category` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `instance_count` smallint unsigned NOT NULL DEFAULT '0',
  `distinct_counterparties` smallint unsigned NOT NULL DEFAULT '0',
  `distinct_contracts` smallint unsigned NOT NULL DEFAULT '0',
  `timeline` json DEFAULT NULL,
  `resolution_note` text COLLATE utf8mb4_unicode_ci,
  `resolved_by` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `detected_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversation_systematic_escalations_user_id_foreign` (`user_id`),
  KEY `conversation_systematic_escalations_resolved_by_foreign` (`resolved_by`),
  KEY `conversation_systematic_escalations_trigger_category_index` (`trigger_category`),
  KEY `conversation_systematic_escalations_status_index` (`status`),
  KEY `conversation_systematic_escalations_detected_at_index` (`detected_at`),
  CONSTRAINT `conversation_systematic_escalations_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversation_systematic_escalations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversation_systematic_escalations`
--

LOCK TABLES `conversation_systematic_escalations` WRITE;
/*!40000 ALTER TABLE `conversation_systematic_escalations` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversation_systematic_escalations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversation_thread_reviews`
--

DROP TABLE IF EXISTS `conversation_thread_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_thread_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_conversation_thread_id` bigint unsigned DEFAULT NULL,
  `proposal_clarification_thread_id` bigint unsigned DEFAULT NULL,
  `quest_id` bigint unsigned NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `priority` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `trigger_categories` json DEFAULT NULL,
  `flag_count` smallint unsigned NOT NULL DEFAULT '0',
  `first_flagged_at` timestamp NULL DEFAULT NULL,
  `last_flagged_at` timestamp NULL DEFAULT NULL,
  `assigned_staff_id` bigint unsigned DEFAULT NULL,
  `super_admin_escalated_at` timestamp NULL DEFAULT NULL,
  `super_admin_escalation_by` bigint unsigned DEFAULT NULL,
  `super_admin_escalation_note` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `escalated_to_admin_id` bigint unsigned DEFAULT NULL,
  `escalated_at` timestamp NULL DEFAULT NULL,
  `dismiss_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conv_thread_reviews_clarify_thread_unique` (`proposal_clarification_thread_id`),
  UNIQUE KEY `conv_thread_reviews_quest_thread_unique` (`quest_conversation_thread_id`),
  KEY `conversation_thread_reviews_quest_id_foreign` (`quest_id`),
  KEY `conversation_thread_reviews_assigned_staff_id_foreign` (`assigned_staff_id`),
  KEY `conversation_thread_reviews_escalated_to_admin_id_foreign` (`escalated_to_admin_id`),
  KEY `conversation_thread_reviews_reviewed_by_foreign` (`reviewed_by`),
  KEY `conversation_thread_reviews_status_index` (`status`),
  KEY `conversation_thread_reviews_priority_index` (`priority`),
  KEY `conversation_thread_reviews_super_admin_escalation_by_foreign` (`super_admin_escalation_by`),
  CONSTRAINT `conv_thread_reviews_clarify_fk` FOREIGN KEY (`proposal_clarification_thread_id`) REFERENCES `proposal_clarification_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conv_thread_reviews_quest_thread_fk` FOREIGN KEY (`quest_conversation_thread_id`) REFERENCES `quest_conversation_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_thread_reviews_assigned_staff_id_foreign` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversation_thread_reviews_escalated_to_admin_id_foreign` FOREIGN KEY (`escalated_to_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversation_thread_reviews_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_thread_reviews_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversation_thread_reviews_super_admin_escalation_by_foreign` FOREIGN KEY (`super_admin_escalation_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversation_thread_reviews`
--

LOCK TABLES `conversation_thread_reviews` WRITE;
/*!40000 ALTER TABLE `conversation_thread_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversation_thread_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversation_user_health_scores`
--

DROP TABLE IF EXISTS `conversation_user_health_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_user_health_scores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `health_score` tinyint unsigned NOT NULL DEFAULT '100',
  `flag_count_30d` smallint unsigned NOT NULL DEFAULT '0',
  `distinct_counterparties_30d` smallint unsigned NOT NULL DEFAULT '0',
  `calculated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversation_user_health_scores_user_id_unique` (`user_id`),
  CONSTRAINT `conversation_user_health_scores_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversation_user_health_scores`
--

LOCK TABLES `conversation_user_health_scores` WRITE;
/*!40000 ALTER TABLE `conversation_user_health_scores` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversation_user_health_scores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dispute_events`
--

DROP TABLE IF EXISTS `dispute_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dispute_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_dispute_id` bigint unsigned NOT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(96) COLLATE utf8mb4_unicode_ci NOT NULL,
  `properties` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `dispute_events_actor_user_id_foreign` (`actor_user_id`),
  KEY `dispute_events_quest_dispute_id_created_at_index` (`quest_dispute_id`,`created_at`),
  KEY `dispute_events_action_index` (`action`),
  CONSTRAINT `dispute_events_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dispute_events_quest_dispute_id_foreign` FOREIGN KEY (`quest_dispute_id`) REFERENCES `quest_disputes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dispute_events`
--

LOCK TABLES `dispute_events` WRITE;
/*!40000 ALTER TABLE `dispute_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `dispute_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dispute_messages`
--

DROP TABLE IF EXISTS `dispute_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dispute_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_dispute_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `kind` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'narrative',
  `body` text COLLATE utf8mb4_unicode_ci,
  `structured_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `structured_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dispute_messages_user_id_foreign` (`user_id`),
  KEY `dispute_messages_quest_dispute_id_created_at_index` (`quest_dispute_id`,`created_at`),
  KEY `dispute_messages_kind_index` (`kind`),
  CONSTRAINT `dispute_messages_quest_dispute_id_foreign` FOREIGN KEY (`quest_dispute_id`) REFERENCES `quest_disputes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dispute_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dispute_messages`
--

LOCK TABLES `dispute_messages` WRITE;
/*!40000 ALTER TABLE `dispute_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `dispute_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dispute_settlement_offers`
--

DROP TABLE IF EXISTS `dispute_settlement_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dispute_settlement_offers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_dispute_id` bigint unsigned NOT NULL,
  `offered_by_user_id` bigint unsigned NOT NULL,
  `client_share_percent` tinyint unsigned NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dispute_settlement_offers_offered_by_user_id_foreign` (`offered_by_user_id`),
  KEY `dispute_settlement_offers_quest_dispute_id_status_index` (`quest_dispute_id`,`status`),
  KEY `dispute_settlement_offers_status_index` (`status`),
  CONSTRAINT `dispute_settlement_offers_offered_by_user_id_foreign` FOREIGN KEY (`offered_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dispute_settlement_offers_quest_dispute_id_foreign` FOREIGN KEY (`quest_dispute_id`) REFERENCES `quest_disputes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dispute_settlement_offers`
--

LOCK TABLES `dispute_settlement_offers` WRITE;
/*!40000 ALTER TABLE `dispute_settlement_offers` DISABLE KEYS */;
/*!40000 ALTER TABLE `dispute_settlement_offers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_broadcast_recipients`
--

DROP TABLE IF EXISTS `email_broadcast_recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_broadcast_recipients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email_broadcast_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `queued_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `bounced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_broadcast_user_unique` (`email_broadcast_id`,`user_id`),
  KEY `email_broadcast_recipients_user_id_foreign` (`user_id`),
  KEY `email_broadcast_recipients_status_index` (`status`),
  CONSTRAINT `email_broadcast_recipients_email_broadcast_id_foreign` FOREIGN KEY (`email_broadcast_id`) REFERENCES `email_broadcasts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `email_broadcast_recipients_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_broadcast_recipients`
--

LOCK TABLES `email_broadcast_recipients` WRITE;
/*!40000 ALTER TABLE `email_broadcast_recipients` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_broadcast_recipients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_broadcast_templates`
--

DROP TABLE IF EXISTS `email_broadcast_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_broadcast_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_by_admin_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `suggested_audience` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `preview_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body_html` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_broadcast_templates_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `email_broadcast_templates_category_index` (`category`),
  KEY `email_broadcast_templates_is_system_index` (`is_system`),
  CONSTRAINT `email_broadcast_templates_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_broadcast_templates`
--

LOCK TABLES `email_broadcast_templates` WRITE;
/*!40000 ALTER TABLE `email_broadcast_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_broadcast_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_broadcasts`
--

DROP TABLE IF EXISTS `email_broadcasts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_broadcasts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint unsigned DEFAULT NULL,
  `created_by_admin_id` bigint unsigned NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `preview_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reply_to` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body_html` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `audience` json DEFAULT NULL,
  `audience_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `total_recipients` int unsigned NOT NULL DEFAULT '0',
  `queued_count` int unsigned NOT NULL DEFAULT '0',
  `sent_count` int unsigned NOT NULL DEFAULT '0',
  `delivered_count` int unsigned NOT NULL DEFAULT '0',
  `opened_count` int unsigned NOT NULL DEFAULT '0',
  `clicked_count` int unsigned NOT NULL DEFAULT '0',
  `bounced_count` int unsigned NOT NULL DEFAULT '0',
  `unsubscribed_count` int unsigned NOT NULL DEFAULT '0',
  `scheduled_for` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_broadcasts_template_id_foreign` (`template_id`),
  KEY `email_broadcasts_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `email_broadcasts_status_index` (`status`),
  KEY `email_broadcasts_scheduled_for_index` (`scheduled_for`),
  CONSTRAINT `email_broadcasts_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `email_broadcasts_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `email_broadcast_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_broadcasts`
--

LOCK TABLES `email_broadcasts` WRITE;
/*!40000 ALTER TABLE `email_broadcasts` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_broadcasts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_template_analytics`
--

DROP TABLE IF EXISTS `email_template_analytics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_template_analytics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email_template_id` bigint unsigned NOT NULL,
  `metric_date` date NOT NULL,
  `sent_count` int unsigned NOT NULL DEFAULT '0',
  `open_count` int unsigned NOT NULL DEFAULT '0',
  `click_count` int unsigned NOT NULL DEFAULT '0',
  `unsubscribe_count` int unsigned NOT NULL DEFAULT '0',
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_template_analytics_email_template_id_metric_date_unique` (`email_template_id`,`metric_date`),
  KEY `email_template_analytics_metric_date_index` (`metric_date`),
  CONSTRAINT `email_template_analytics_email_template_id_foreign` FOREIGN KEY (`email_template_id`) REFERENCES `email_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_template_analytics`
--

LOCK TABLES `email_template_analytics` WRITE;
/*!40000 ALTER TABLE `email_template_analytics` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_template_analytics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_template_versions`
--

DROP TABLE IF EXISTS `email_template_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_template_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email_template_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `preheader` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blocks` json DEFAULT NULL,
  `theme` json DEFAULT NULL,
  `variables` json DEFAULT NULL,
  `change_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_template_versions_created_by_foreign` (`created_by`),
  KEY `email_template_versions_email_template_id_created_at_index` (`email_template_id`,`created_at`),
  CONSTRAINT `email_template_versions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `email_template_versions_email_template_id_foreign` FOREIGN KEY (`email_template_id`) REFERENCES `email_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_template_versions`
--

LOCK TABLES `email_template_versions` WRITE;
/*!40000 ALTER TABLE `email_template_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_template_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_templates`
--

DROP TABLE IF EXISTS `email_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger_event` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `preheader` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blocks` json DEFAULT NULL,
  `theme` json DEFAULT NULL,
  `variables` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_edited_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_templates_key_unique` (`key`),
  KEY `email_templates_last_edited_by_foreign` (`last_edited_by`),
  KEY `email_templates_trigger_event_index` (`trigger_event`),
  KEY `email_templates_is_active_index` (`is_active`),
  CONSTRAINT `email_templates_last_edited_by_foreign` FOREIGN KEY (`last_edited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_templates`
--

LOCK TABLES `email_templates` WRITE;
/*!40000 ALTER TABLE `email_templates` DISABLE KEYS */;
INSERT INTO `email_templates` VALUES (1,'welcome_verify_email','auth.registered','Welcome / verify email','Welcome to HustleSafe, {{user.first_name}}','Verify your email to unlock your account.','[{\"type\": \"text\", \"content\": \"Welcome to HustleSafe, {{user.first_name}}.\"}, {\"url\": \"{{verification.url}}\", \"type\": \"button\", \"label\": \"Verify email\"}]','{\"logo\": \"HS\", \"footer\": \"HustleSafe - Escrow-first marketplace\", \"primary_color\": \"#0f766e\"}','[{\"token\": \"{{user.first_name}}\", \"description\": \"Recipient first name\"}, {\"token\": \"{{user.name}}\", \"description\": \"Recipient full name\"}, {\"token\": \"{{app.name}}\", \"description\": \"Platform name\"}, {\"token\": \"{{verification.url}}\", \"description\": \"Signed verification URL\"}]',1,NULL,'2026-05-31 18:24:46','2026-05-31 18:24:46'),(2,'quest_posted_confirmation','quest.posted','Quest posted confirmation','Your Quest is live: {{quest.title}}','Freelancers can now discover your Quest.','[{\"type\": \"text\", \"content\": \"Your Quest \\\"{{quest.title}}\\\" is now live.\"}]','{\"logo\": \"HS\", \"footer\": \"HustleSafe - Escrow-first marketplace\", \"primary_color\": \"#0f766e\"}','[{\"token\": \"{{user.first_name}}\", \"description\": \"Recipient first name\"}, {\"token\": \"{{user.name}}\", \"description\": \"Recipient full name\"}, {\"token\": \"{{app.name}}\", \"description\": \"Platform name\"}, {\"token\": \"{{quest.title}}\", \"description\": \"Quest title\"}, {\"token\": \"{{quest.reference}}\", \"description\": \"Quest reference code\"}, {\"token\": \"{{freelancer.name}}\", \"description\": \"Freelancer name when available\"}]',1,NULL,'2026-05-31 18:24:46','2026-05-31 18:24:46'),(3,'proposal_received','proposal.received','Proposal received','New proposal for {{quest.title}}','A freelancer has submitted a proposal.','[{\"type\": \"text\", \"content\": \"{{freelancer.name}} submitted a proposal for {{quest.title}}.\"}]','{\"logo\": \"HS\", \"footer\": \"HustleSafe - Escrow-first marketplace\", \"primary_color\": \"#0f766e\"}','[{\"token\": \"{{user.first_name}}\", \"description\": \"Recipient first name\"}, {\"token\": \"{{user.name}}\", \"description\": \"Recipient full name\"}, {\"token\": \"{{app.name}}\", \"description\": \"Platform name\"}, {\"token\": \"{{quest.title}}\", \"description\": \"Quest title\"}, {\"token\": \"{{quest.reference}}\", \"description\": \"Quest reference code\"}, {\"token\": \"{{freelancer.name}}\", \"description\": \"Freelancer name when available\"}]',1,NULL,'2026-05-31 18:24:46','2026-05-31 18:24:46'),(4,'dispute_opened','dispute.opened','Dispute opened alert','Dispute opened for {{quest.title}}','Review the dispute and respond before the deadline.','[{\"type\": \"text\", \"content\": \"A dispute was opened on {{quest.title}}.\"}]','{\"logo\": \"HS\", \"footer\": \"HustleSafe - Escrow-first marketplace\", \"primary_color\": \"#0f766e\"}','[{\"token\": \"{{user.first_name}}\", \"description\": \"Recipient first name\"}, {\"token\": \"{{user.name}}\", \"description\": \"Recipient full name\"}, {\"token\": \"{{app.name}}\", \"description\": \"Platform name\"}, {\"token\": \"{{quest.title}}\", \"description\": \"Quest title\"}, {\"token\": \"{{quest.reference}}\", \"description\": \"Quest reference code\"}, {\"token\": \"{{freelancer.name}}\", \"description\": \"Freelancer name when available\"}]',1,NULL,'2026-05-31 18:24:46','2026-05-31 18:24:46'),(5,'payout_processed','payout.processed','Payout processed','Your payout of {{payout.amount}} is on the way','Your payout has been processed.','[{\"type\": \"text\", \"content\": \"Your payout of {{payout.amount}} has been processed.\"}]','{\"logo\": \"HS\", \"footer\": \"HustleSafe - Escrow-first marketplace\", \"primary_color\": \"#0f766e\"}','[{\"token\": \"{{user.first_name}}\", \"description\": \"Recipient first name\"}, {\"token\": \"{{user.name}}\", \"description\": \"Recipient full name\"}, {\"token\": \"{{app.name}}\", \"description\": \"Platform name\"}, {\"token\": \"{{payout.amount}}\", \"description\": \"Formatted payout amount\"}, {\"token\": \"{{payout.reference}}\", \"description\": \"Payout reference\"}]',1,NULL,'2026-05-31 18:24:46','2026-05-31 18:24:46');
/*!40000 ALTER TABLE `email_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `featured_quest_listings`
--

DROP TABLE IF EXISTS `featured_quest_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `featured_quest_listings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `client_user_id` bigint unsigned NOT NULL,
  `granted_by_admin_id` bigint unsigned DEFAULT NULL,
  `tier` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `starts_at` timestamp NOT NULL,
  `expires_at` timestamp NOT NULL,
  `amount_paid_minor` int unsigned NOT NULL DEFAULT '0',
  `proposal_views_count` int unsigned NOT NULL DEFAULT '0',
  `notifications_sent_count` int unsigned NOT NULL DEFAULT '0',
  `homepage_carousel` tinyint(1) NOT NULL DEFAULT '0',
  `weekly_digest` tinyint(1) NOT NULL DEFAULT '0',
  `social_post_required` tinyint(1) NOT NULL DEFAULT '0',
  `social_post_handled_at` timestamp NULL DEFAULT NULL,
  `manual_grant_reason` text COLLATE utf8mb4_unicode_ci,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by_admin_id` bigint unsigned DEFAULT NULL,
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  `refund_amount_minor` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `featured_quest_listings_client_user_id_foreign` (`client_user_id`),
  KEY `featured_quest_listings_granted_by_admin_id_foreign` (`granted_by_admin_id`),
  KEY `featured_quest_listings_cancelled_by_admin_id_foreign` (`cancelled_by_admin_id`),
  KEY `featured_quest_listings_quest_id_status_index` (`quest_id`,`status`),
  KEY `featured_quest_listings_status_expires_at_index` (`status`,`expires_at`),
  KEY `featured_quest_listings_tier_index` (`tier`),
  KEY `featured_quest_listings_status_index` (`status`),
  KEY `featured_quest_listings_starts_at_index` (`starts_at`),
  KEY `featured_quest_listings_expires_at_index` (`expires_at`),
  CONSTRAINT `featured_quest_listings_cancelled_by_admin_id_foreign` FOREIGN KEY (`cancelled_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `featured_quest_listings_client_user_id_foreign` FOREIGN KEY (`client_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `featured_quest_listings_granted_by_admin_id_foreign` FOREIGN KEY (`granted_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `featured_quest_listings_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `featured_quest_listings`
--

LOCK TABLES `featured_quest_listings` WRITE;
/*!40000 ALTER TABLE `featured_quest_listings` DISABLE KEYS */;
/*!40000 ALTER TABLE `featured_quest_listings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `financial_audit_reports`
--

DROP TABLE IF EXISTS `financial_audit_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_audit_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `generated_by_user_id` bigint unsigned NOT NULL,
  `generated_at` timestamp NOT NULL,
  `csv_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pdf_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_audit_reports_uuid_unique` (`uuid`),
  KEY `financial_audit_reports_generated_by_user_id_foreign` (`generated_by_user_id`),
  KEY `financial_audit_reports_type_generated_at_index` (`type`,`generated_at`),
  CONSTRAINT `financial_audit_reports_generated_by_user_id_foreign` FOREIGN KEY (`generated_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_audit_reports`
--

LOCK TABLES `financial_audit_reports` WRITE;
/*!40000 ALTER TABLE `financial_audit_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `financial_audit_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `financial_escrow_records`
--

DROP TABLE IF EXISTS `financial_escrow_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_escrow_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `escrow_reference` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_escrow_id` bigint unsigned NOT NULL,
  `quest_id` bigint unsigned NOT NULL,
  `quest_contract_id` bigint unsigned DEFAULT NULL,
  `contract_reference` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quest_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quest_category_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned NOT NULL,
  `client_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `freelancer_id` bigint unsigned NOT NULL,
  `freelancer_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gross_contract_value_minor` bigint unsigned NOT NULL,
  `total_funded_minor` bigint unsigned NOT NULL,
  `platform_fee_percent` decimal(5,2) NOT NULL,
  `platform_fee_minor` bigint unsigned NOT NULL DEFAULT '0',
  `vat_percent` decimal(5,2) NOT NULL DEFAULT '7.50',
  `vat_minor` bigint unsigned NOT NULL DEFAULT '0',
  `freelancer_net_minor` bigint unsigned NOT NULL DEFAULT '0',
  `gateway_name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paystack',
  `paystack_reference` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `funded_at` timestamp NULL DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'held',
  `release_trigger_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `wallet_credit_reference` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fee_recognised_at` timestamp NULL DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_escrow_records_uuid_unique` (`uuid`),
  UNIQUE KEY `financial_escrow_records_escrow_reference_unique` (`escrow_reference`),
  UNIQUE KEY `financial_escrow_records_payment_escrow_id_unique` (`payment_escrow_id`),
  KEY `financial_escrow_records_quest_id_foreign` (`quest_id`),
  KEY `financial_escrow_records_quest_contract_id_foreign` (`quest_contract_id`),
  KEY `financial_escrow_records_quest_category_id_foreign` (`quest_category_id`),
  KEY `financial_escrow_records_client_id_foreign` (`client_id`),
  KEY `financial_escrow_records_freelancer_id_foreign` (`freelancer_id`),
  KEY `financial_escrow_records_status_funded_at_index` (`status`,`funded_at`),
  KEY `financial_escrow_records_contract_reference_index` (`contract_reference`),
  KEY `financial_escrow_records_paystack_reference_index` (`paystack_reference`),
  KEY `financial_escrow_records_status_index` (`status`),
  CONSTRAINT `financial_escrow_records_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `financial_escrow_records_freelancer_id_foreign` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `financial_escrow_records_payment_escrow_id_foreign` FOREIGN KEY (`payment_escrow_id`) REFERENCES `payment_escrows` (`id`) ON DELETE CASCADE,
  CONSTRAINT `financial_escrow_records_quest_category_id_foreign` FOREIGN KEY (`quest_category_id`) REFERENCES `quest_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `financial_escrow_records_quest_contract_id_foreign` FOREIGN KEY (`quest_contract_id`) REFERENCES `quest_contracts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `financial_escrow_records_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_escrow_records`
--

LOCK TABLES `financial_escrow_records` WRITE;
/*!40000 ALTER TABLE `financial_escrow_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `financial_escrow_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `financial_reconciliation_exceptions`
--

DROP TABLE IF EXISTS `financial_reconciliation_exceptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_reconciliation_exceptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_run_id` bigint unsigned DEFAULT NULL,
  `latest_run_id` bigint unsigned DEFAULT NULL,
  `type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `assigned_to_user_id` bigint unsigned DEFAULT NULL,
  `payment_escrow_id` bigint unsigned DEFAULT NULL,
  `paystack_reference` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variance_minor` bigint DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `investigation_notes` text COLLATE utf8mb4_unicode_ci,
  `resolution_description` text COLLATE utf8mb4_unicode_ci,
  `first_detected_at` timestamp NOT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by_user_id` bigint unsigned DEFAULT NULL,
  `escalated_at` timestamp NULL DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_reconciliation_exceptions_uuid_unique` (`uuid`),
  KEY `financial_reconciliation_exceptions_first_run_id_foreign` (`first_run_id`),
  KEY `financial_reconciliation_exceptions_latest_run_id_foreign` (`latest_run_id`),
  KEY `financial_reconciliation_exceptions_assigned_to_user_id_foreign` (`assigned_to_user_id`),
  KEY `financial_reconciliation_exceptions_payment_escrow_id_foreign` (`payment_escrow_id`),
  KEY `financial_reconciliation_exceptions_resolved_by_user_id_foreign` (`resolved_by_user_id`),
  KEY `financial_reconciliation_exceptions_type_status_index` (`type`,`status`),
  KEY `financial_reconciliation_exceptions_status_index` (`status`),
  KEY `financial_reconciliation_exceptions_paystack_reference_index` (`paystack_reference`),
  CONSTRAINT `financial_reconciliation_exceptions_assigned_to_user_id_foreign` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `financial_reconciliation_exceptions_first_run_id_foreign` FOREIGN KEY (`first_run_id`) REFERENCES `financial_reconciliation_runs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `financial_reconciliation_exceptions_latest_run_id_foreign` FOREIGN KEY (`latest_run_id`) REFERENCES `financial_reconciliation_runs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `financial_reconciliation_exceptions_payment_escrow_id_foreign` FOREIGN KEY (`payment_escrow_id`) REFERENCES `payment_escrows` (`id`) ON DELETE SET NULL,
  CONSTRAINT `financial_reconciliation_exceptions_resolved_by_user_id_foreign` FOREIGN KEY (`resolved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_reconciliation_exceptions`
--

LOCK TABLES `financial_reconciliation_exceptions` WRITE;
/*!40000 ALTER TABLE `financial_reconciliation_exceptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `financial_reconciliation_exceptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `financial_reconciliation_runs`
--

DROP TABLE IF EXISTS `financial_reconciliation_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_reconciliation_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `started_at` timestamp NOT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'running',
  `records_processed` int unsigned NOT NULL DEFAULT '0',
  `exceptions_found` int unsigned NOT NULL DEFAULT '0',
  `checks` json DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_reconciliation_runs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_reconciliation_runs`
--

LOCK TABLES `financial_reconciliation_runs` WRITE;
/*!40000 ALTER TABLE `financial_reconciliation_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `financial_reconciliation_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `freelancer_business_profiles`
--

DROP TABLE IF EXISTS `freelancer_business_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `freelancer_business_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `cac_registration_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cac_verification_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_submitted',
  `cac_verified_at` timestamp NULL DEFAULT NULL,
  `cac_last_checked_at` timestamp NULL DEFAULT NULL,
  `cac_verification_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `freelancer_business_profiles_user_id_unique` (`user_id`),
  KEY `freelancer_business_profiles_cac_verification_status_index` (`cac_verification_status`),
  CONSTRAINT `freelancer_business_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `freelancer_business_profiles`
--

LOCK TABLES `freelancer_business_profiles` WRITE;
/*!40000 ALTER TABLE `freelancer_business_profiles` DISABLE KEYS */;
/*!40000 ALTER TABLE `freelancer_business_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `freelancer_credentials`
--

DROP TABLE IF EXISTS `freelancer_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `freelancer_credentials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `credential_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issuing_authority` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_on` date DEFAULT NULL,
  `expires_on` date DEFAULT NULL,
  `coverage_summary` text COLLATE utf8mb4_unicode_ci,
  `document_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `is_public` tinyint(1) NOT NULL DEFAULT '1',
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `freelancer_credentials_user_id_credential_type_index` (`user_id`,`credential_type`),
  KEY `freelancer_credentials_credential_type_index` (`credential_type`),
  CONSTRAINT `freelancer_credentials_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `freelancer_credentials`
--

LOCK TABLES `freelancer_credentials` WRITE;
/*!40000 ALTER TABLE `freelancer_credentials` DISABLE KEYS */;
/*!40000 ALTER TABLE `freelancer_credentials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `freelancer_delivery_extension_logs`
--

DROP TABLE IF EXISTS `freelancer_delivery_extension_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `freelancer_delivery_extension_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `quest_contract_id` bigint unsigned NOT NULL,
  `delivery_extension_id` bigint unsigned NOT NULL,
  `outcome` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_category` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `logged_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `freelancer_delivery_extension_logs_quest_contract_id_foreign` (`quest_contract_id`),
  KEY `fdel_ext_log_ext_fk` (`delivery_extension_id`),
  KEY `freelancer_delivery_extension_logs_user_id_logged_at_index` (`user_id`,`logged_at`),
  CONSTRAINT `fdel_ext_log_ext_fk` FOREIGN KEY (`delivery_extension_id`) REFERENCES `quest_contract_delivery_extensions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `freelancer_delivery_extension_logs_quest_contract_id_foreign` FOREIGN KEY (`quest_contract_id`) REFERENCES `quest_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `freelancer_delivery_extension_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `freelancer_delivery_extension_logs`
--

LOCK TABLES `freelancer_delivery_extension_logs` WRITE;
/*!40000 ALTER TABLE `freelancer_delivery_extension_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `freelancer_delivery_extension_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `freelancer_quest_category`
--

DROP TABLE IF EXISTS `freelancer_quest_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `freelancer_quest_category` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `quest_category_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `freelancer_quest_category_user_id_quest_category_id_unique` (`user_id`,`quest_category_id`),
  KEY `freelancer_quest_category_quest_category_id_foreign` (`quest_category_id`),
  CONSTRAINT `freelancer_quest_category_quest_category_id_foreign` FOREIGN KEY (`quest_category_id`) REFERENCES `quest_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `freelancer_quest_category_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `freelancer_quest_category`
--

LOCK TABLES `freelancer_quest_category` WRITE;
/*!40000 ALTER TABLE `freelancer_quest_category` DISABLE KEYS */;
INSERT INTO `freelancer_quest_category` VALUES (1,1,17,'2026-05-31 19:37:46','2026-05-31 19:37:46'),(2,1,93,'2026-05-31 19:37:46','2026-05-31 19:37:46'),(3,1,88,'2026-05-31 19:37:46','2026-05-31 19:37:46'),(4,2,94,'2026-05-31 19:37:47','2026-05-31 19:37:47'),(5,2,63,'2026-05-31 19:37:47','2026-05-31 19:37:47'),(6,2,49,'2026-05-31 19:37:47','2026-05-31 19:37:47'),(7,3,11,'2026-05-31 19:37:47','2026-05-31 19:37:47'),(8,3,61,'2026-05-31 19:37:47','2026-05-31 19:37:47'),(9,3,54,'2026-05-31 19:37:47','2026-05-31 19:37:47'),(10,4,100,'2026-05-31 19:37:48','2026-05-31 19:37:48'),(11,4,86,'2026-05-31 19:37:48','2026-05-31 19:37:48'),(12,4,3,'2026-05-31 19:37:48','2026-05-31 19:37:48'),(13,5,45,'2026-05-31 19:37:49','2026-05-31 19:37:49'),(14,5,62,'2026-05-31 19:37:49','2026-05-31 19:37:49'),(15,5,71,'2026-05-31 19:37:49','2026-05-31 19:37:49'),(16,6,21,'2026-05-31 19:37:50','2026-05-31 19:37:50'),(17,6,98,'2026-05-31 19:37:50','2026-05-31 19:37:50'),(18,6,25,'2026-05-31 19:37:50','2026-05-31 19:37:50'),(19,7,41,'2026-05-31 19:37:51','2026-05-31 19:37:51'),(20,7,25,'2026-05-31 19:37:51','2026-05-31 19:37:51'),(21,7,88,'2026-05-31 19:37:51','2026-05-31 19:37:51'),(22,8,14,'2026-05-31 19:37:51','2026-05-31 19:37:51'),(23,8,44,'2026-05-31 19:37:51','2026-05-31 19:37:51'),(24,8,24,'2026-05-31 19:37:51','2026-05-31 19:37:51'),(25,9,36,'2026-05-31 19:37:52','2026-05-31 19:37:52'),(26,9,100,'2026-05-31 19:37:52','2026-05-31 19:37:52'),(27,9,69,'2026-05-31 19:37:52','2026-05-31 19:37:52'),(28,10,61,'2026-05-31 19:37:52','2026-05-31 19:37:52'),(29,10,54,'2026-05-31 19:37:52','2026-05-31 19:37:52'),(30,11,89,'2026-05-31 19:37:53','2026-05-31 19:37:53'),(31,11,68,'2026-05-31 19:37:53','2026-05-31 19:37:53'),(32,11,35,'2026-05-31 19:37:53','2026-05-31 19:37:53'),(33,12,85,'2026-05-31 19:37:53','2026-05-31 19:37:53'),(34,12,108,'2026-05-31 19:37:53','2026-05-31 19:37:53'),(35,12,81,'2026-05-31 19:37:53','2026-05-31 19:37:53'),(36,13,53,'2026-05-31 19:37:55','2026-05-31 19:37:55'),(37,13,51,'2026-05-31 19:37:55','2026-05-31 19:37:55'),(38,13,74,'2026-05-31 19:37:55','2026-05-31 19:37:55'),(39,14,103,'2026-05-31 19:37:56','2026-05-31 19:37:56'),(40,14,100,'2026-05-31 19:37:56','2026-05-31 19:37:56'),(41,14,38,'2026-05-31 19:37:56','2026-05-31 19:37:56'),(42,15,114,'2026-05-31 19:37:57','2026-05-31 19:37:57'),(43,15,45,'2026-05-31 19:37:57','2026-05-31 19:37:57'),(44,15,30,'2026-05-31 19:37:57','2026-05-31 19:37:57'),(45,16,47,'2026-05-31 19:37:58','2026-05-31 19:37:58'),(46,16,32,'2026-05-31 19:37:58','2026-05-31 19:37:58'),(47,16,108,'2026-05-31 19:37:58','2026-05-31 19:37:58'),(48,17,20,'2026-05-31 19:37:59','2026-05-31 19:37:59'),(49,17,17,'2026-05-31 19:37:59','2026-05-31 19:37:59'),(50,17,2,'2026-05-31 19:37:59','2026-05-31 19:37:59'),(51,18,48,'2026-05-31 19:37:59','2026-05-31 19:37:59'),(52,18,98,'2026-05-31 19:37:59','2026-05-31 19:37:59'),(53,18,111,'2026-05-31 19:37:59','2026-05-31 19:37:59'),(54,19,67,'2026-05-31 19:38:00','2026-05-31 19:38:00'),(55,19,96,'2026-05-31 19:38:00','2026-05-31 19:38:00'),(56,19,13,'2026-05-31 19:38:00','2026-05-31 19:38:00'),(57,20,86,'2026-05-31 19:38:01','2026-05-31 19:38:01'),(58,20,41,'2026-05-31 19:38:01','2026-05-31 19:38:01'),(59,20,15,'2026-05-31 19:38:01','2026-05-31 19:38:01');
/*!40000 ALTER TABLE `freelancer_quest_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `freelancer_subscription_histories`
--

DROP TABLE IF EXISTS `freelancer_subscription_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `freelancer_subscription_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `freelancer_subscription_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `event` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fsh_subscription_fk` (`freelancer_subscription_id`),
  KEY `freelancer_subscription_histories_user_id_foreign` (`user_id`),
  KEY `freelancer_subscription_histories_actor_user_id_foreign` (`actor_user_id`),
  CONSTRAINT `freelancer_subscription_histories_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `freelancer_subscription_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fsh_subscription_fk` FOREIGN KEY (`freelancer_subscription_id`) REFERENCES `freelancer_subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `freelancer_subscription_histories`
--

LOCK TABLES `freelancer_subscription_histories` WRITE;
/*!40000 ALTER TABLE `freelancer_subscription_histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `freelancer_subscription_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `freelancer_subscription_payments`
--

DROP TABLE IF EXISTS `freelancer_subscription_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `freelancer_subscription_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `freelancer_subscription_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `amount_minor` bigint unsigned NOT NULL,
  `billing_cycle` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paystack_reference` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `freelancer_subscription_payments_paystack_reference_unique` (`paystack_reference`),
  KEY `fsp_subscription_fk` (`freelancer_subscription_id`),
  KEY `freelancer_subscription_payments_user_id_foreign` (`user_id`),
  CONSTRAINT `freelancer_subscription_payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fsp_subscription_fk` FOREIGN KEY (`freelancer_subscription_id`) REFERENCES `freelancer_subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `freelancer_subscription_payments`
--

LOCK TABLES `freelancer_subscription_payments` WRITE;
/*!40000 ALTER TABLE `freelancer_subscription_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `freelancer_subscription_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `freelancer_subscriptions`
--

DROP TABLE IF EXISTS `freelancer_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `freelancer_subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `tier` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free',
  `started_at` timestamp NULL DEFAULT NULL,
  `renewal_date` timestamp NULL DEFAULT NULL,
  `billing_cycle` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_price_minor` bigint unsigned NOT NULL DEFAULT '0',
  `annual_price_minor` bigint unsigned NOT NULL DEFAULT '0',
  `auto_renew` tinyint(1) NOT NULL DEFAULT '0',
  `payment_method_snapshot` json DEFAULT NULL,
  `total_spent_minor` bigint unsigned NOT NULL DEFAULT '0',
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `freelancer_subscriptions_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `freelancer_subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `freelancer_subscriptions`
--

LOCK TABLES `freelancer_subscriptions` WRITE;
/*!40000 ALTER TABLE `freelancer_subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `freelancer_subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `help_faq_items`
--

DROP TABLE IF EXISTS `help_faq_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `help_faq_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `help_section_id` bigint unsigned NOT NULL,
  `question` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `audience` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `search_keywords` json DEFAULT NULL,
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_edited_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `help_faq_items_help_section_id_foreign` (`help_section_id`),
  KEY `help_faq_items_last_edited_by_foreign` (`last_edited_by`),
  KEY `help_faq_items_audience_index` (`audience`),
  KEY `help_faq_items_display_order_index` (`display_order`),
  KEY `help_faq_items_status_index` (`status`),
  CONSTRAINT `help_faq_items_help_section_id_foreign` FOREIGN KEY (`help_section_id`) REFERENCES `help_sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `help_faq_items_last_edited_by_foreign` FOREIGN KEY (`last_edited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `help_faq_items`
--

LOCK TABLES `help_faq_items` WRITE;
/*!40000 ALTER TABLE `help_faq_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `help_faq_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `help_search_logs`
--

DROP TABLE IF EXISTS `help_search_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `help_search_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `query` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `results_count` int unsigned NOT NULL DEFAULT '0',
  `audience` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `help_search_logs_user_id_foreign` (`user_id`),
  KEY `help_search_logs_results_count_created_at_index` (`results_count`,`created_at`),
  CONSTRAINT `help_search_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `help_search_logs`
--

LOCK TABLES `help_search_logs` WRITE;
/*!40000 ALTER TABLE `help_search_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `help_search_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `help_sections`
--

DROP TABLE IF EXISTS `help_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `help_sections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `help_sections_slug_unique` (`slug`),
  KEY `help_sections_display_order_index` (`display_order`),
  KEY `help_sections_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `help_sections`
--

LOCK TABLES `help_sections` WRITE;
/*!40000 ALTER TABLE `help_sections` DISABLE KEYS */;
INSERT INTO `help_sections` VALUES (1,'Getting Started','getting-started',1,'active','2026-05-31 18:24:46','2026-05-31 18:24:46'),(2,'Posting a Quest','posting-a-quest',2,'active','2026-05-31 18:24:46','2026-05-31 18:24:46'),(3,'Submitting Proposals','submitting-proposals',3,'active','2026-05-31 18:24:46','2026-05-31 18:24:46'),(4,'Payments and Escrow','payments-and-escrow',4,'active','2026-05-31 18:24:46','2026-05-31 18:24:46'),(5,'Disputes','disputes',5,'active','2026-05-31 18:24:46','2026-05-31 18:24:46'),(6,'Account and Verification','account-and-verification',6,'active','2026-05-31 18:24:46','2026-05-31 18:24:46'),(7,'Platform Policies','platform-policies',7,'active','2026-05-31 18:24:46','2026-05-31 18:24:46');
/*!40000 ALTER TABLE `help_sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kyc_audit_events`
--

DROP TABLE IF EXISTS `kyc_audit_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kyc_audit_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kyc_review_case_id` bigint unsigned DEFAULT NULL,
  `admin_user_id` bigint unsigned NOT NULL,
  `event` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kyc_audit_events_kyc_review_case_id_foreign` (`kyc_review_case_id`),
  KEY `kyc_audit_events_admin_user_id_foreign` (`admin_user_id`),
  KEY `kyc_audit_events_event_index` (`event`),
  CONSTRAINT `kyc_audit_events_admin_user_id_foreign` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kyc_audit_events_kyc_review_case_id_foreign` FOREIGN KEY (`kyc_review_case_id`) REFERENCES `kyc_review_cases` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kyc_audit_events`
--

LOCK TABLES `kyc_audit_events` WRITE;
/*!40000 ALTER TABLE `kyc_audit_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `kyc_audit_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kyc_decisions`
--

DROP TABLE IF EXISTS `kyc_decisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kyc_decisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kyc_review_case_id` bigint unsigned NOT NULL,
  `admin_user_id` bigint unsigned NOT NULL,
  `action` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `correction_fields` json DEFAULT NULL,
  `portfolio_scores` json DEFAULT NULL,
  `time_to_decision_seconds` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kyc_decisions_kyc_review_case_id_foreign` (`kyc_review_case_id`),
  KEY `kyc_decisions_admin_user_id_foreign` (`admin_user_id`),
  KEY `kyc_decisions_action_index` (`action`),
  KEY `kyc_decisions_reason_code_index` (`reason_code`),
  CONSTRAINT `kyc_decisions_admin_user_id_foreign` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kyc_decisions_kyc_review_case_id_foreign` FOREIGN KEY (`kyc_review_case_id`) REFERENCES `kyc_review_cases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kyc_decisions`
--

LOCK TABLES `kyc_decisions` WRITE;
/*!40000 ALTER TABLE `kyc_decisions` DISABLE KEYS */;
/*!40000 ALTER TABLE `kyc_decisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kyc_documents`
--

DROP TABLE IF EXISTS `kyc_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kyc_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kyc_review_case_id` bigint unsigned NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'supporting_document',
  `disk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `path` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kyc_documents_kyc_review_case_id_foreign` (`kyc_review_case_id`),
  CONSTRAINT `kyc_documents_kyc_review_case_id_foreign` FOREIGN KEY (`kyc_review_case_id`) REFERENCES `kyc_review_cases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kyc_documents`
--

LOCK TABLES `kyc_documents` WRITE;
/*!40000 ALTER TABLE `kyc_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `kyc_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kyc_review_cases`
--

DROP TABLE IF EXISTS `kyc_review_cases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kyc_review_cases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `user_verification_id` bigint unsigned DEFAULT NULL,
  `assigned_admin_id` bigint unsigned DEFAULT NULL,
  `target_tier` tinyint unsigned NOT NULL,
  `verification_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `priority` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `queue_reason` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `confidence_score` tinyint unsigned DEFAULT NULL,
  `submitted_snapshot` json DEFAULT NULL,
  `provider_snapshot` json DEFAULT NULL,
  `comparison` json DEFAULT NULL,
  `entered_queue_at` timestamp NOT NULL,
  `review_started_at` timestamp NULL DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `decision` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kyc_review_cases_uuid_unique` (`uuid`),
  KEY `kyc_review_cases_user_id_foreign` (`user_id`),
  KEY `kyc_review_cases_user_verification_id_foreign` (`user_verification_id`),
  KEY `kyc_review_cases_assigned_admin_id_foreign` (`assigned_admin_id`),
  KEY `kyc_review_cases_status_priority_entered_queue_at_index` (`status`,`priority`,`entered_queue_at`),
  KEY `kyc_review_cases_target_tier_index` (`target_tier`),
  KEY `kyc_review_cases_verification_type_index` (`verification_type`),
  KEY `kyc_review_cases_status_index` (`status`),
  KEY `kyc_review_cases_priority_index` (`priority`),
  KEY `kyc_review_cases_queue_reason_index` (`queue_reason`),
  KEY `kyc_review_cases_entered_queue_at_index` (`entered_queue_at`),
  KEY `kyc_review_cases_decided_at_index` (`decided_at`),
  KEY `kyc_review_cases_decision_index` (`decision`),
  CONSTRAINT `kyc_review_cases_assigned_admin_id_foreign` FOREIGN KEY (`assigned_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kyc_review_cases_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kyc_review_cases_user_verification_id_foreign` FOREIGN KEY (`user_verification_id`) REFERENCES `user_verifications` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kyc_review_cases`
--

LOCK TABLES `kyc_review_cases` WRITE;
/*!40000 ALTER TABLE `kyc_review_cases` DISABLE KEYS */;
/*!40000 ALTER TABLE `kyc_review_cases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kyc_settings`
--

DROP TABLE IF EXISTS `kyc_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kyc_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kyc_settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kyc_settings`
--

LOCK TABLES `kyc_settings` WRITE;
/*!40000 ALTER TABLE `kyc_settings` DISABLE KEYS */;
INSERT INTO `kyc_settings` VALUES (1,'active_provider','\"manual\"','2026-05-31 18:24:39','2026-05-31 18:24:39'),(2,'fallback_provider','null','2026-05-31 18:24:39','2026-05-31 18:24:39'),(3,'thresholds','{\"bvn\": 85, \"nin\": 85, \"face_similarity\": 85}','2026-05-31 18:24:39','2026-05-31 18:24:39'),(4,'feature_gates','{\"browse\": 0, \"post_quest\": 1, \"business_badge\": 5, \"submit_proposal\": 1, \"high_value_quest\": 2, \"withdraw_large_amount\": 4}','2026-05-31 18:24:39','2026-05-31 18:24:39'),(5,'resubmission_limit','3','2026-05-31 18:24:39','2026-05-31 18:24:39'),(6,'verification_fees','{\"enabled\": false, \"cac_fee_minor\": 0}','2026-05-31 18:24:39','2026-05-31 18:24:39'),(7,'limits','{\"tier_1_client_quest_minor\": 25000000, \"tier_2_client_quest_minor\": 100000000, \"tier_4_single_withdrawal_minor\": 500000000}','2026-05-31 18:24:39','2026-05-31 18:24:39'),(8,'verification_types','{\"bvn\": {\"label\": \"BVN Verification\", \"enabled\": true, \"sensitive\": true, \"manual_review\": true}, \"cac\": {\"label\": \"CAC Verification\", \"enabled\": true, \"manual_review\": true, \"freelancer_only\": true}, \"nin\": {\"label\": \"NIN Verification\", \"enabled\": true, \"manual_review\": true}, \"tin\": {\"label\": \"TIN Verification\", \"enabled\": true, \"manual_review\": true, \"freelancer_only\": true}, \"email\": {\"label\": \"Email Verification\", \"enabled\": true, \"manual_review\": false}, \"live_presence\": {\"label\": \"Selfie + ID (high-value quest unlock)\", \"enabled\": true, \"manual_review\": true, \"freelancer_only\": true}, \"identity_address\": {\"label\": \"Identity & Address Verification\", \"enabled\": true, \"manual_review\": true}, \"portfolio_review\": {\"soft\": true, \"label\": \"Portfolio Review Verification\", \"enabled\": false, \"manual_review\": true}, \"professional_certificate\": {\"label\": \"Professional Certificate / Membership\", \"enabled\": true, \"optional\": true, \"manual_review\": true, \"freelancer_only\": true}}','2026-05-31 18:25:09','2026-05-31 18:25:09'),(9,'verification_level_requirements','[{\"label\": \"L0 — No checks\", \"requirements\": []}, {\"label\": \"L1 — Email verified\", \"requirements\": [\"email\"]}, {\"label\": \"L2 — Identity & address\", \"requirements\": [\"email\", \"identity_address\"]}, {\"label\": \"L3 — NIN verified\", \"requirements\": [\"email\", \"identity_address\", \"nin\"]}, {\"label\": \"L4 — BVN verified\", \"requirements\": [\"email\", \"identity_address\", \"nin\", \"bvn\"]}, {\"label\": \"L5 — Established account (180 days)\", \"requirements\": [\"email\", \"identity_address\", \"nin\", \"bvn\", {\"account_age_days\": 180}]}]','2026-05-31 18:25:09','2026-05-31 18:25:09'),(10,'verification_limits','{\"client_posting_minor\": [0, 5000000, 50000000, 200000000, 100000000, 1000000000], \"freelancer_proposal_minor\": [0, 5000000, 50000000, 200000000, 500000000, 1000000000]}','2026-05-31 18:25:09','2026-05-31 18:25:59'),(11,'verification_safeguards','{\"quest_repost_limit\": 2, \"minimum_milestone_count\": 2, \"anomaly_high_value_minor\": 10000000, \"anomaly_new_account_days\": 7, \"anomaly_near_ceiling_percent\": 90, \"anomaly_proposal_burst_count\": 5, \"anomaly_proposal_burst_minutes\": 60, \"anomaly_verification_window_hours\": 24, \"rapid_completion_high_value_minor\": 10000000, \"escrow_enforcement_threshold_minor\": 100, \"milestone_enforcement_threshold_minor\": 100000000, \"high_value_arbitration_threshold_minor\": 100000000}','2026-05-31 18:25:09','2026-05-31 18:25:09'),(12,'verification_client_level_requirements','[{\"label\": \"L0 — No checks\", \"requirements\": []}, {\"label\": \"L1 — Email verified\", \"requirements\": [\"email\"]}, {\"label\": \"L2 — Identity & address\", \"requirements\": [\"email\", \"identity_address\"]}, {\"label\": \"L3 — NIN verified\", \"requirements\": [\"email\", \"identity_address\", \"nin\"]}, {\"label\": \"L4 — BVN verified\", \"requirements\": [\"email\", \"identity_address\", \"nin\", \"bvn\"]}, {\"label\": \"L5 — Established account (180 days)\", \"requirements\": [\"email\", \"identity_address\", \"nin\", \"bvn\", {\"account_age_days\": 180}]}]','2026-05-31 18:28:34','2026-05-31 18:28:34'),(13,'verification_freelancer_level_requirements','[{\"label\": \"L0 — No checks\", \"requirements\": []}, {\"label\": \"L1 — Email verified\", \"requirements\": [\"email\"]}, {\"label\": \"L2 — Identity & address\", \"requirements\": [\"email\", \"identity_address\"]}, {\"label\": \"L3 — NIN & BVN\", \"requirements\": [\"email\", \"identity_address\", \"nin\", \"bvn\"]}, {\"label\": \"L4 — CAC/TIN verification\", \"requirements\": [\"email\", \"identity_address\", \"nin\", \"bvn\", {\"any_of\": [\"cac\", \"tin\"]}]}, {\"label\": \"L5 — 90 days + Selfie + ID\", \"requirements\": [\"email\", \"identity_address\", \"nin\", \"bvn\", {\"any_of\": [\"cac\", \"tin\"]}, \"live_presence\", {\"account_age_days\": 90}]}]','2026-05-31 18:28:34','2026-05-31 18:28:34'),(14,'verification_stage_content','{\"client\": {\"1\": {\"title\": \"Verify your email\", \"message\": \"Check your inbox for the verification link. Request a new link if needed.\", \"info_bar\": \"Verify your email to reach L1 and start building your verification level.\"}, \"2\": {\"title\": \"Identity & address verification\", \"message\": \"Upload a government photo ID and proof of address (utility bill, bank statement, or tenancy within the last 3 months).\", \"info_bar\": \"Complete identity and address verification to unlock L2.\"}, \"3\": {\"title\": \"NIN verification\", \"message\": \"Enter your 11-digit National Identification Number for review.\", \"info_bar\": \"Submit your NIN to unlock L3.\"}, \"4\": {\"title\": \"BVN verification\", \"message\": \"Enter your 11-digit Bank Verification Number for review.\", \"info_bar\": \"Submit your BVN to unlock L4.\"}, \"5\": {\"title\": \"Established account\", \"message\": \"Your account must be at least 180 days old to reach L5.\", \"info_bar\": \"After L4, L5 unlocks automatically once your account reaches 180 days on HustleSafe.\"}}, \"freelancer\": {\"1\": {\"title\": \"Verify your email\", \"message\": \"Check your inbox for the verification link. Request a new link if needed.\", \"info_bar\": \"Verify your email to reach L1.\"}, \"2\": {\"title\": \"Identity & address verification\", \"message\": \"Upload a government photo ID and proof of address for review.\", \"info_bar\": \"Complete identity and address verification to unlock L2.\"}, \"3\": {\"title\": \"NIN & BVN verification\", \"message\": \"Submit both your NIN and BVN. Both are required to unlock L3.\", \"info_bar\": \"Add your NIN and BVN to unlock L3 and raise your proposal limit.\"}, \"4\": {\"title\": \"CAC or TIN verification\", \"message\": \"Submit your RC number (CAC) or TIN. You only need one of these for L4.\", \"info_bar\": \"Complete business verification (CAC or TIN) to unlock L4.\"}, \"5\": {\"title\": \"Selfie + ID (L5)\", \"message\": \"Your account must be at least 90 days old. Upload a selfie holding your government ID beside your face.\", \"info_bar\": \"Reach L5 with 90 days account age plus an approved selfie + ID for high-value quests.\"}}}','2026-05-31 18:28:34','2026-05-31 18:28:34');
/*!40000 ALTER TABLE `kyc_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ledger_entries`
--

DROP TABLE IF EXISTS `ledger_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ledger_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch_id` bigint unsigned NOT NULL,
  `ledger_account` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `side` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_minor` bigint unsigned NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NGN',
  `payment_escrow_id` bigint unsigned DEFAULT NULL,
  `quest_id` bigint unsigned DEFAULT NULL,
  `wallet_withdrawal_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `freelancer_id` bigint unsigned DEFAULT NULL,
  `paystack_reference` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ledger_entries_uuid_unique` (`uuid`),
  KEY `ledger_entries_batch_id_foreign` (`batch_id`),
  KEY `ledger_entries_quest_id_foreign` (`quest_id`),
  KEY `ledger_entries_wallet_withdrawal_id_foreign` (`wallet_withdrawal_id`),
  KEY `ledger_entries_client_id_foreign` (`client_id`),
  KEY `ledger_entries_freelancer_id_foreign` (`freelancer_id`),
  KEY `ledger_entries_ledger_account_occurred_at_index` (`ledger_account`,`occurred_at`),
  KEY `ledger_entries_payment_escrow_id_occurred_at_index` (`payment_escrow_id`,`occurred_at`),
  KEY `ledger_entries_paystack_reference_index` (`paystack_reference`),
  CONSTRAINT `ledger_entries_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `ledger_journal_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ledger_entries_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_entries_freelancer_id_foreign` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_entries_payment_escrow_id_foreign` FOREIGN KEY (`payment_escrow_id`) REFERENCES `payment_escrows` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_entries_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_entries_wallet_withdrawal_id_foreign` FOREIGN KEY (`wallet_withdrawal_id`) REFERENCES `wallet_withdrawals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ledger_entries`
--

LOCK TABLES `ledger_entries` WRITE;
/*!40000 ALTER TABLE `ledger_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `ledger_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ledger_journal_batches`
--

DROP TABLE IF EXISTS `ledger_journal_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ledger_journal_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `idempotency_key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_escrow_id` bigint unsigned DEFAULT NULL,
  `quest_id` bigint unsigned DEFAULT NULL,
  `quest_contract_id` bigint unsigned DEFAULT NULL,
  `wallet_withdrawal_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `freelancer_id` bigint unsigned DEFAULT NULL,
  `paystack_reference` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_process` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reverses_batch_id` bigint unsigned DEFAULT NULL,
  `reversal_reason` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ledger_journal_batches_uuid_unique` (`uuid`),
  UNIQUE KEY `ledger_journal_batches_reference_unique` (`reference`),
  UNIQUE KEY `ledger_journal_batches_idempotency_key_unique` (`idempotency_key`),
  KEY `ledger_journal_batches_payment_escrow_id_foreign` (`payment_escrow_id`),
  KEY `ledger_journal_batches_quest_id_foreign` (`quest_id`),
  KEY `ledger_journal_batches_quest_contract_id_foreign` (`quest_contract_id`),
  KEY `ledger_journal_batches_wallet_withdrawal_id_foreign` (`wallet_withdrawal_id`),
  KEY `ledger_journal_batches_client_id_foreign` (`client_id`),
  KEY `ledger_journal_batches_freelancer_id_foreign` (`freelancer_id`),
  KEY `ledger_journal_batches_reverses_batch_id_foreign` (`reverses_batch_id`),
  KEY `ledger_journal_batches_paystack_reference_index` (`paystack_reference`),
  CONSTRAINT `ledger_journal_batches_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_journal_batches_freelancer_id_foreign` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_journal_batches_payment_escrow_id_foreign` FOREIGN KEY (`payment_escrow_id`) REFERENCES `payment_escrows` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_journal_batches_quest_contract_id_foreign` FOREIGN KEY (`quest_contract_id`) REFERENCES `quest_contracts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_journal_batches_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_journal_batches_reverses_batch_id_foreign` FOREIGN KEY (`reverses_batch_id`) REFERENCES `ledger_journal_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ledger_journal_batches_wallet_withdrawal_id_foreign` FOREIGN KEY (`wallet_withdrawal_id`) REFERENCES `wallet_withdrawals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ledger_journal_batches`
--

LOCK TABLES `ledger_journal_batches` WRITE;
/*!40000 ALTER TABLE `ledger_journal_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `ledger_journal_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `local_governments`
--

DROP TABLE IF EXISTS `local_governments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `local_governments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `state_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `local_governments_state_id_name_unique` (`state_id`,`name`),
  KEY `local_governments_name_index` (`name`),
  CONSTRAINT `local_governments_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=774 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `local_governments`
--

LOCK TABLES `local_governments` WRITE;
/*!40000 ALTER TABLE `local_governments` DISABLE KEYS */;
INSERT INTO `local_governments` VALUES (1,1,'Abuja','2026-05-31 19:20:17','2026-05-31 19:20:17'),(2,1,'Kwali','2026-05-31 19:20:17','2026-05-31 19:20:17'),(3,1,'Kuje','2026-05-31 19:20:17','2026-05-31 19:20:17'),(4,1,'Gwagwalada','2026-05-31 19:20:17','2026-05-31 19:20:17'),(5,1,'Bwari','2026-05-31 19:20:17','2026-05-31 19:20:17'),(6,1,'Abaji','2026-05-31 19:20:17','2026-05-31 19:20:17'),(7,2,'Aba North','2026-05-31 19:20:17','2026-05-31 19:20:17'),(8,2,'Aba South','2026-05-31 19:20:17','2026-05-31 19:20:17'),(9,2,'Arochukwu','2026-05-31 19:20:17','2026-05-31 19:20:17'),(10,2,'Bende','2026-05-31 19:20:17','2026-05-31 19:20:17'),(11,2,'Ikawuno','2026-05-31 19:20:17','2026-05-31 19:20:17'),(12,2,'Ikwuano','2026-05-31 19:20:17','2026-05-31 19:20:17'),(13,2,'Isiala-Ngwa North','2026-05-31 19:20:17','2026-05-31 19:20:17'),(14,2,'Isiala-Ngwa South','2026-05-31 19:20:17','2026-05-31 19:20:17'),(15,2,'Isuikwuato','2026-05-31 19:20:17','2026-05-31 19:20:17'),(16,2,'Umu Nneochi','2026-05-31 19:20:17','2026-05-31 19:20:17'),(17,2,'Obi Ngwa','2026-05-31 19:20:17','2026-05-31 19:20:17'),(18,2,'Obioma Ngwa','2026-05-31 19:20:17','2026-05-31 19:20:17'),(19,2,'Ohafia','2026-05-31 19:20:17','2026-05-31 19:20:17'),(20,2,'Ohaozara','2026-05-31 19:20:17','2026-05-31 19:20:17'),(21,2,'Osisioma','2026-05-31 19:20:17','2026-05-31 19:20:17'),(22,2,'Ugwunagbo','2026-05-31 19:20:17','2026-05-31 19:20:17'),(23,2,'Ukwa West','2026-05-31 19:20:17','2026-05-31 19:20:17'),(24,2,'Ukwa East','2026-05-31 19:20:17','2026-05-31 19:20:17'),(25,2,'Umuahia North','2026-05-31 19:20:17','2026-05-31 19:20:17'),(26,2,'Umuahia South','2026-05-31 19:20:17','2026-05-31 19:20:17'),(27,3,'Demsa','2026-05-31 19:20:17','2026-05-31 19:20:17'),(28,3,'Fufore','2026-05-31 19:20:17','2026-05-31 19:20:17'),(29,3,'Ganye','2026-05-31 19:20:17','2026-05-31 19:20:17'),(30,3,'Girei','2026-05-31 19:20:17','2026-05-31 19:20:17'),(31,3,'Gombi','2026-05-31 19:20:17','2026-05-31 19:20:17'),(32,3,'Guyuk','2026-05-31 19:20:17','2026-05-31 19:20:17'),(33,3,'Hong','2026-05-31 19:20:17','2026-05-31 19:20:17'),(34,3,'Jada','2026-05-31 19:20:17','2026-05-31 19:20:17'),(35,3,'Lamurde','2026-05-31 19:20:17','2026-05-31 19:20:17'),(36,3,'Madagali','2026-05-31 19:20:17','2026-05-31 19:20:17'),(37,3,'Maiha','2026-05-31 19:20:17','2026-05-31 19:20:17'),(38,3,'Mayo-Belwa','2026-05-31 19:20:17','2026-05-31 19:20:17'),(39,3,'Michika','2026-05-31 19:20:17','2026-05-31 19:20:17'),(40,3,'Mubi-North','2026-05-31 19:20:17','2026-05-31 19:20:17'),(41,3,'Mubi-South','2026-05-31 19:20:17','2026-05-31 19:20:17'),(42,3,'Numan','2026-05-31 19:20:17','2026-05-31 19:20:17'),(43,3,'Shelleng','2026-05-31 19:20:17','2026-05-31 19:20:17'),(44,3,'Song','2026-05-31 19:20:17','2026-05-31 19:20:17'),(45,3,'Toungo','2026-05-31 19:20:17','2026-05-31 19:20:17'),(46,3,'Yola North','2026-05-31 19:20:17','2026-05-31 19:20:17'),(47,3,'Yola South','2026-05-31 19:20:17','2026-05-31 19:20:17'),(48,4,'Abak','2026-05-31 19:20:17','2026-05-31 19:20:17'),(49,4,'Eastern-Obolo','2026-05-31 19:20:17','2026-05-31 19:20:17'),(50,4,'Eket','2026-05-31 19:20:17','2026-05-31 19:20:17'),(51,4,'Esit-Eket','2026-05-31 19:20:17','2026-05-31 19:20:17'),(52,4,'Essien-Udim','2026-05-31 19:20:17','2026-05-31 19:20:17'),(53,4,'Etim-Ekpo','2026-05-31 19:20:17','2026-05-31 19:20:17'),(54,4,'Etinan','2026-05-31 19:20:17','2026-05-31 19:20:17'),(55,4,'Ibeno','2026-05-31 19:20:17','2026-05-31 19:20:17'),(56,4,'Ibesikpo-Asutan','2026-05-31 19:20:17','2026-05-31 19:20:17'),(57,4,'Ibiono-Ibom','2026-05-31 19:20:17','2026-05-31 19:20:17'),(58,4,'Ika','2026-05-31 19:20:17','2026-05-31 19:20:17'),(59,4,'Ikono','2026-05-31 19:20:17','2026-05-31 19:20:17'),(60,4,'Ikot-Abasi','2026-05-31 19:20:17','2026-05-31 19:20:17'),(61,4,'Ikot-Ekpene','2026-05-31 19:20:17','2026-05-31 19:20:17'),(62,4,'Ini','2026-05-31 19:20:17','2026-05-31 19:20:17'),(63,4,'Itu','2026-05-31 19:20:17','2026-05-31 19:20:17'),(64,4,'Mbo','2026-05-31 19:20:17','2026-05-31 19:20:17'),(65,4,'Mkpat-Enin','2026-05-31 19:20:17','2026-05-31 19:20:17'),(66,4,'Nsit-Atai','2026-05-31 19:20:17','2026-05-31 19:20:17'),(67,4,'Nsit-Ibom','2026-05-31 19:20:17','2026-05-31 19:20:17'),(68,4,'Nsit-Ubium','2026-05-31 19:20:17','2026-05-31 19:20:17'),(69,4,'Obot-Akara','2026-05-31 19:20:17','2026-05-31 19:20:17'),(70,4,'Okobo','2026-05-31 19:20:17','2026-05-31 19:20:17'),(71,4,'Onna','2026-05-31 19:20:17','2026-05-31 19:20:17'),(72,4,'Oron','2026-05-31 19:20:17','2026-05-31 19:20:17'),(73,4,'Oruk Anam','2026-05-31 19:20:17','2026-05-31 19:20:17'),(74,4,'Udung-Uko','2026-05-31 19:20:17','2026-05-31 19:20:17'),(75,4,'Ukanafun','2026-05-31 19:20:17','2026-05-31 19:20:17'),(76,4,'Urue-Offong/Oruko','2026-05-31 19:20:17','2026-05-31 19:20:17'),(77,4,'Uruan','2026-05-31 19:20:17','2026-05-31 19:20:17'),(78,4,'Uyo','2026-05-31 19:20:17','2026-05-31 19:20:17'),(79,5,'Aguata','2026-05-31 19:20:17','2026-05-31 19:20:17'),(80,5,'Anambra East','2026-05-31 19:20:17','2026-05-31 19:20:17'),(81,5,'Anambra West','2026-05-31 19:20:17','2026-05-31 19:20:17'),(82,5,'Anaocha','2026-05-31 19:20:17','2026-05-31 19:20:17'),(83,5,'Awka North','2026-05-31 19:20:17','2026-05-31 19:20:17'),(84,5,'Awka South','2026-05-31 19:20:17','2026-05-31 19:20:17'),(85,5,'Ayamelum','2026-05-31 19:20:17','2026-05-31 19:20:17'),(86,5,'Dunukofia','2026-05-31 19:20:17','2026-05-31 19:20:17'),(87,5,'Ekwusigo','2026-05-31 19:20:17','2026-05-31 19:20:17'),(88,5,'Idemili-North','2026-05-31 19:20:17','2026-05-31 19:20:17'),(89,5,'Idemili-South','2026-05-31 19:20:17','2026-05-31 19:20:17'),(90,5,'Ihiala','2026-05-31 19:20:17','2026-05-31 19:20:17'),(91,5,'Njikoka','2026-05-31 19:20:17','2026-05-31 19:20:17'),(92,5,'Nnewi-North','2026-05-31 19:20:17','2026-05-31 19:20:17'),(93,5,'Nnewi-South','2026-05-31 19:20:17','2026-05-31 19:20:17'),(94,5,'Ogbaru','2026-05-31 19:20:17','2026-05-31 19:20:17'),(95,5,'Onitsha-North','2026-05-31 19:20:17','2026-05-31 19:20:17'),(96,5,'Onitsha-South','2026-05-31 19:20:17','2026-05-31 19:20:17'),(97,5,'Orumba-North','2026-05-31 19:20:17','2026-05-31 19:20:17'),(98,5,'Orumba-South','2026-05-31 19:20:17','2026-05-31 19:20:17'),(99,6,'Alkaleri','2026-05-31 19:20:17','2026-05-31 19:20:17'),(100,6,'Bauchi','2026-05-31 19:20:17','2026-05-31 19:20:17'),(101,6,'Bogoro','2026-05-31 19:20:17','2026-05-31 19:20:17'),(102,6,'Damban','2026-05-31 19:20:17','2026-05-31 19:20:17'),(103,6,'Darazo','2026-05-31 19:20:17','2026-05-31 19:20:17'),(104,6,'Dass','2026-05-31 19:20:17','2026-05-31 19:20:17'),(105,6,'Gamawa','2026-05-31 19:20:17','2026-05-31 19:20:17'),(106,6,'Ganjuwa','2026-05-31 19:20:17','2026-05-31 19:20:17'),(107,6,'Giade','2026-05-31 19:20:17','2026-05-31 19:20:17'),(108,6,'Itas Gadau','2026-05-31 19:20:17','2026-05-31 19:20:17'),(109,6,'Jama\'Are','2026-05-31 19:20:17','2026-05-31 19:20:17'),(110,6,'Katagum','2026-05-31 19:20:17','2026-05-31 19:20:17'),(111,6,'Kirfi','2026-05-31 19:20:17','2026-05-31 19:20:17'),(112,6,'Misau','2026-05-31 19:20:17','2026-05-31 19:20:17'),(113,6,'Ningi','2026-05-31 19:20:17','2026-05-31 19:20:17'),(114,6,'Shira','2026-05-31 19:20:17','2026-05-31 19:20:17'),(115,6,'Tafawa-Balewa','2026-05-31 19:20:17','2026-05-31 19:20:17'),(116,6,'Toro','2026-05-31 19:20:17','2026-05-31 19:20:17'),(117,6,'Warji','2026-05-31 19:20:17','2026-05-31 19:20:17'),(118,6,'Zaki','2026-05-31 19:20:17','2026-05-31 19:20:17'),(119,7,'Brass','2026-05-31 19:20:17','2026-05-31 19:20:17'),(120,7,'Ekeremor','2026-05-31 19:20:17','2026-05-31 19:20:17'),(121,7,'Kolokuma Opokuma','2026-05-31 19:20:17','2026-05-31 19:20:17'),(122,7,'Nembe','2026-05-31 19:20:17','2026-05-31 19:20:17'),(123,7,'Ogbia','2026-05-31 19:20:17','2026-05-31 19:20:17'),(124,7,'Sagbama','2026-05-31 19:20:17','2026-05-31 19:20:17'),(125,7,'Southern-Ijaw','2026-05-31 19:20:17','2026-05-31 19:20:17'),(126,7,'Yenagoa','2026-05-31 19:20:17','2026-05-31 19:20:17'),(127,8,'Ado','2026-05-31 19:20:17','2026-05-31 19:20:17'),(128,8,'Agatu','2026-05-31 19:20:17','2026-05-31 19:20:17'),(129,8,'Apa','2026-05-31 19:20:17','2026-05-31 19:20:17'),(130,8,'Buruku','2026-05-31 19:20:17','2026-05-31 19:20:17'),(131,8,'Gboko','2026-05-31 19:20:17','2026-05-31 19:20:17'),(132,8,'Guma','2026-05-31 19:20:17','2026-05-31 19:20:17'),(133,8,'Gwer-East','2026-05-31 19:20:17','2026-05-31 19:20:17'),(134,8,'Gwer-West','2026-05-31 19:20:17','2026-05-31 19:20:17'),(135,8,'Katsina-Ala','2026-05-31 19:20:17','2026-05-31 19:20:17'),(136,8,'Konshisha','2026-05-31 19:20:17','2026-05-31 19:20:17'),(137,8,'Kwande','2026-05-31 19:20:18','2026-05-31 19:20:18'),(138,8,'Logo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(139,8,'Makurdi','2026-05-31 19:20:18','2026-05-31 19:20:18'),(140,8,'Ogbadibo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(141,8,'Ohimini','2026-05-31 19:20:18','2026-05-31 19:20:18'),(142,8,'Oju','2026-05-31 19:20:18','2026-05-31 19:20:18'),(143,8,'Okpokwu','2026-05-31 19:20:18','2026-05-31 19:20:18'),(144,8,'Otukpo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(145,8,'Tarka','2026-05-31 19:20:18','2026-05-31 19:20:18'),(146,8,'Ukum','2026-05-31 19:20:18','2026-05-31 19:20:18'),(147,8,'Ushongo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(148,8,'Vandeikya','2026-05-31 19:20:18','2026-05-31 19:20:18'),(149,9,'Abadam','2026-05-31 19:20:18','2026-05-31 19:20:18'),(150,9,'Askira-Uba','2026-05-31 19:20:18','2026-05-31 19:20:18'),(151,9,'Bama','2026-05-31 19:20:18','2026-05-31 19:20:18'),(152,9,'Bayo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(153,9,'Biu','2026-05-31 19:20:18','2026-05-31 19:20:18'),(154,9,'Chibok','2026-05-31 19:20:18','2026-05-31 19:20:18'),(155,9,'Damboa','2026-05-31 19:20:18','2026-05-31 19:20:18'),(156,9,'Dikwa','2026-05-31 19:20:18','2026-05-31 19:20:18'),(157,9,'Gubio','2026-05-31 19:20:18','2026-05-31 19:20:18'),(158,9,'Guzamala','2026-05-31 19:20:18','2026-05-31 19:20:18'),(159,9,'Gwoza','2026-05-31 19:20:18','2026-05-31 19:20:18'),(160,9,'Hawul','2026-05-31 19:20:18','2026-05-31 19:20:18'),(161,9,'Jere','2026-05-31 19:20:18','2026-05-31 19:20:18'),(162,9,'Kaga','2026-05-31 19:20:18','2026-05-31 19:20:18'),(163,9,'Kala Balge','2026-05-31 19:20:18','2026-05-31 19:20:18'),(164,9,'Konduga','2026-05-31 19:20:18','2026-05-31 19:20:18'),(165,9,'Kukawa','2026-05-31 19:20:18','2026-05-31 19:20:18'),(166,9,'Kwaya-Kusar','2026-05-31 19:20:18','2026-05-31 19:20:18'),(167,9,'Mafa','2026-05-31 19:20:18','2026-05-31 19:20:18'),(168,9,'Magumeri','2026-05-31 19:20:18','2026-05-31 19:20:18'),(169,9,'Maiduguri','2026-05-31 19:20:18','2026-05-31 19:20:18'),(170,9,'Marte','2026-05-31 19:20:18','2026-05-31 19:20:18'),(171,9,'Mobbar','2026-05-31 19:20:18','2026-05-31 19:20:18'),(172,9,'Monguno','2026-05-31 19:20:18','2026-05-31 19:20:18'),(173,9,'Ngala','2026-05-31 19:20:18','2026-05-31 19:20:18'),(174,9,'Nganzai','2026-05-31 19:20:18','2026-05-31 19:20:18'),(175,9,'Shani','2026-05-31 19:20:18','2026-05-31 19:20:18'),(176,10,'Abi','2026-05-31 19:20:18','2026-05-31 19:20:18'),(177,10,'Akamkpa','2026-05-31 19:20:18','2026-05-31 19:20:18'),(178,10,'Akpabuyo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(179,10,'Bakassi','2026-05-31 19:20:18','2026-05-31 19:20:18'),(180,10,'Bekwarra','2026-05-31 19:20:18','2026-05-31 19:20:18'),(181,10,'Biase','2026-05-31 19:20:18','2026-05-31 19:20:18'),(182,10,'Boki','2026-05-31 19:20:18','2026-05-31 19:20:18'),(183,10,'Calabar-Municipal','2026-05-31 19:20:18','2026-05-31 19:20:18'),(184,10,'Calabar-South','2026-05-31 19:20:18','2026-05-31 19:20:18'),(185,10,'Etung','2026-05-31 19:20:18','2026-05-31 19:20:18'),(186,10,'Ikom','2026-05-31 19:20:18','2026-05-31 19:20:18'),(187,10,'Obanliku','2026-05-31 19:20:18','2026-05-31 19:20:18'),(188,10,'Obubra','2026-05-31 19:20:18','2026-05-31 19:20:18'),(189,10,'Obudu','2026-05-31 19:20:18','2026-05-31 19:20:18'),(190,10,'Odukpani','2026-05-31 19:20:18','2026-05-31 19:20:18'),(191,10,'Ogoja','2026-05-31 19:20:18','2026-05-31 19:20:18'),(192,10,'Yakurr','2026-05-31 19:20:18','2026-05-31 19:20:18'),(193,10,'Yala','2026-05-31 19:20:18','2026-05-31 19:20:18'),(194,11,'Aniocha North','2026-05-31 19:20:18','2026-05-31 19:20:18'),(195,11,'Aniocha-North','2026-05-31 19:20:18','2026-05-31 19:20:18'),(196,11,'Aniocha-South','2026-05-31 19:20:18','2026-05-31 19:20:18'),(197,11,'Bomadi','2026-05-31 19:20:18','2026-05-31 19:20:18'),(198,11,'Burutu','2026-05-31 19:20:18','2026-05-31 19:20:18'),(199,11,'Ethiope-East','2026-05-31 19:20:18','2026-05-31 19:20:18'),(200,11,'Ethiope-West','2026-05-31 19:20:18','2026-05-31 19:20:18'),(201,11,'Ika-North-East','2026-05-31 19:20:18','2026-05-31 19:20:18'),(202,11,'Ika-South','2026-05-31 19:20:18','2026-05-31 19:20:18'),(203,11,'Isoko-North','2026-05-31 19:20:18','2026-05-31 19:20:18'),(204,11,'Isoko-South','2026-05-31 19:20:18','2026-05-31 19:20:18'),(205,11,'Ndokwa-East','2026-05-31 19:20:18','2026-05-31 19:20:18'),(206,11,'Ndokwa-West','2026-05-31 19:20:18','2026-05-31 19:20:18'),(207,11,'Okpe','2026-05-31 19:20:18','2026-05-31 19:20:18'),(208,11,'Oshimili-North','2026-05-31 19:20:18','2026-05-31 19:20:18'),(209,11,'Oshimili-South','2026-05-31 19:20:18','2026-05-31 19:20:18'),(210,11,'Patani','2026-05-31 19:20:18','2026-05-31 19:20:18'),(211,11,'Sapele','2026-05-31 19:20:18','2026-05-31 19:20:18'),(212,11,'Udu','2026-05-31 19:20:18','2026-05-31 19:20:18'),(213,11,'Ughelli-North','2026-05-31 19:20:18','2026-05-31 19:20:18'),(214,11,'Ughelli-South','2026-05-31 19:20:18','2026-05-31 19:20:18'),(215,11,'Ukwuani','2026-05-31 19:20:18','2026-05-31 19:20:18'),(216,11,'Uvwie','2026-05-31 19:20:18','2026-05-31 19:20:18'),(217,11,'Warri South-West','2026-05-31 19:20:18','2026-05-31 19:20:18'),(218,11,'Warri North','2026-05-31 19:20:18','2026-05-31 19:20:18'),(219,11,'Warri South','2026-05-31 19:20:18','2026-05-31 19:20:18'),(220,12,'Abakaliki','2026-05-31 19:20:18','2026-05-31 19:20:18'),(221,12,'Afikpo-North','2026-05-31 19:20:18','2026-05-31 19:20:18'),(222,12,'Afikpo South (Edda)','2026-05-31 19:20:18','2026-05-31 19:20:18'),(223,12,'Ebonyi','2026-05-31 19:20:18','2026-05-31 19:20:18'),(224,12,'Ezza-North','2026-05-31 19:20:18','2026-05-31 19:20:18'),(225,12,'Ezza-South','2026-05-31 19:20:18','2026-05-31 19:20:18'),(226,12,'Ikwo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(227,12,'Ishielu','2026-05-31 19:20:18','2026-05-31 19:20:18'),(228,12,'Ivo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(229,12,'Izzi','2026-05-31 19:20:18','2026-05-31 19:20:18'),(230,12,'Ohaukwu','2026-05-31 19:20:18','2026-05-31 19:20:18'),(231,12,'Onicha','2026-05-31 19:20:18','2026-05-31 19:20:18'),(232,13,'Akoko Edo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(233,13,'Egor','2026-05-31 19:20:18','2026-05-31 19:20:18'),(234,13,'Esan-Central','2026-05-31 19:20:18','2026-05-31 19:20:18'),(235,13,'Esan-North-East','2026-05-31 19:20:18','2026-05-31 19:20:18'),(236,13,'Esan-South-East','2026-05-31 19:20:18','2026-05-31 19:20:18'),(237,13,'Esan-West','2026-05-31 19:20:18','2026-05-31 19:20:18'),(238,13,'Etsako-Central','2026-05-31 19:20:18','2026-05-31 19:20:18'),(239,13,'Etsako-East','2026-05-31 19:20:18','2026-05-31 19:20:18'),(240,13,'Etsako-West','2026-05-31 19:20:18','2026-05-31 19:20:18'),(241,13,'Igueben','2026-05-31 19:20:18','2026-05-31 19:20:18'),(242,13,'Ikpoba-Okha','2026-05-31 19:20:18','2026-05-31 19:20:18'),(243,13,'Oredo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(244,13,'Orhionmwon','2026-05-31 19:20:18','2026-05-31 19:20:18'),(245,13,'Ovia-North-East','2026-05-31 19:20:18','2026-05-31 19:20:18'),(246,13,'Ovia-South-West','2026-05-31 19:20:18','2026-05-31 19:20:18'),(247,13,'Owan East','2026-05-31 19:20:18','2026-05-31 19:20:18'),(248,13,'Owan-West','2026-05-31 19:20:18','2026-05-31 19:20:18'),(249,13,'Uhunmwonde','2026-05-31 19:20:18','2026-05-31 19:20:18'),(250,14,'Ado-Ekiti','2026-05-31 19:20:18','2026-05-31 19:20:18'),(251,14,'Efon','2026-05-31 19:20:18','2026-05-31 19:20:18'),(252,14,'Ekiti-East','2026-05-31 19:20:18','2026-05-31 19:20:18'),(253,14,'Ekiti-South-West','2026-05-31 19:20:18','2026-05-31 19:20:18'),(254,14,'Ekiti-West','2026-05-31 19:20:18','2026-05-31 19:20:18'),(255,14,'Emure','2026-05-31 19:20:18','2026-05-31 19:20:18'),(256,14,'Gbonyin','2026-05-31 19:20:18','2026-05-31 19:20:18'),(257,14,'Ido-Osi','2026-05-31 19:20:18','2026-05-31 19:20:18'),(258,14,'Ijero','2026-05-31 19:20:18','2026-05-31 19:20:18'),(259,14,'Ikere','2026-05-31 19:20:18','2026-05-31 19:20:18'),(260,14,'Ikole','2026-05-31 19:20:18','2026-05-31 19:20:18'),(261,14,'Ilejemeje','2026-05-31 19:20:18','2026-05-31 19:20:18'),(262,14,'Irepodun Ifelodun','2026-05-31 19:20:18','2026-05-31 19:20:18'),(263,14,'Ise-Orun','2026-05-31 19:20:18','2026-05-31 19:20:18'),(264,14,'Moba','2026-05-31 19:20:18','2026-05-31 19:20:18'),(265,14,'Oye','2026-05-31 19:20:18','2026-05-31 19:20:18'),(266,15,'Aninri','2026-05-31 19:20:18','2026-05-31 19:20:18'),(267,15,'Awgu','2026-05-31 19:20:18','2026-05-31 19:20:18'),(268,15,'Enugu-East','2026-05-31 19:20:18','2026-05-31 19:20:18'),(269,15,'Enugu-North','2026-05-31 19:20:18','2026-05-31 19:20:18'),(270,15,'Enugu-South','2026-05-31 19:20:18','2026-05-31 19:20:18'),(271,15,'Ezeagu','2026-05-31 19:20:18','2026-05-31 19:20:18'),(272,15,'Igbo-Etiti','2026-05-31 19:20:18','2026-05-31 19:20:18'),(273,15,'Igbo-Eze-North','2026-05-31 19:20:18','2026-05-31 19:20:18'),(274,15,'Igbo-Eze-South','2026-05-31 19:20:18','2026-05-31 19:20:18'),(275,15,'Isi-Uzo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(276,15,'Nkanu-East','2026-05-31 19:20:18','2026-05-31 19:20:18'),(277,15,'Nkanu-West','2026-05-31 19:20:18','2026-05-31 19:20:18'),(278,15,'Nsukka','2026-05-31 19:20:18','2026-05-31 19:20:18'),(279,15,'Oji-River','2026-05-31 19:20:18','2026-05-31 19:20:18'),(280,15,'Udenu','2026-05-31 19:20:18','2026-05-31 19:20:18'),(281,15,'Udi','2026-05-31 19:20:18','2026-05-31 19:20:18'),(282,15,'Uzo-Uwani','2026-05-31 19:20:18','2026-05-31 19:20:18'),(283,16,'Akko','2026-05-31 19:20:18','2026-05-31 19:20:18'),(284,16,'Balanga','2026-05-31 19:20:18','2026-05-31 19:20:18'),(285,16,'Billiri','2026-05-31 19:20:18','2026-05-31 19:20:18'),(286,16,'Dukku','2026-05-31 19:20:18','2026-05-31 19:20:18'),(287,16,'Funakaye','2026-05-31 19:20:18','2026-05-31 19:20:18'),(288,16,'Gombe','2026-05-31 19:20:18','2026-05-31 19:20:18'),(289,16,'Kaltungo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(290,16,'Kwami','2026-05-31 19:20:18','2026-05-31 19:20:18'),(291,16,'Nafada','2026-05-31 19:20:18','2026-05-31 19:20:18'),(292,16,'Shongom','2026-05-31 19:20:18','2026-05-31 19:20:18'),(293,16,'Yamaltu Deba','2026-05-31 19:20:18','2026-05-31 19:20:18'),(294,17,'Aboh-Mbaise','2026-05-31 19:20:18','2026-05-31 19:20:18'),(295,17,'Ahiazu-Mbaise','2026-05-31 19:20:18','2026-05-31 19:20:18'),(296,17,'Ehime-Mbano','2026-05-31 19:20:18','2026-05-31 19:20:18'),(297,17,'Ezinihitte','2026-05-31 19:20:18','2026-05-31 19:20:18'),(298,17,'Ideato-North','2026-05-31 19:20:18','2026-05-31 19:20:18'),(299,17,'Ideato-South','2026-05-31 19:20:18','2026-05-31 19:20:18'),(300,17,'Ihitte Uboma','2026-05-31 19:20:18','2026-05-31 19:20:18'),(301,17,'Ikeduru','2026-05-31 19:20:18','2026-05-31 19:20:18'),(302,17,'Isiala-Mbano','2026-05-31 19:20:18','2026-05-31 19:20:18'),(303,17,'Isu','2026-05-31 19:20:18','2026-05-31 19:20:18'),(304,17,'Mbaitoli','2026-05-31 19:20:18','2026-05-31 19:20:18'),(305,17,'Ngor-Okpala','2026-05-31 19:20:18','2026-05-31 19:20:18'),(306,17,'Njaba','2026-05-31 19:20:18','2026-05-31 19:20:18'),(307,17,'Nkwerre','2026-05-31 19:20:18','2026-05-31 19:20:18'),(308,17,'Nwangele','2026-05-31 19:20:18','2026-05-31 19:20:18'),(309,17,'Obowo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(310,17,'Oguta','2026-05-31 19:20:18','2026-05-31 19:20:18'),(311,17,'Ohaji-Egbema','2026-05-31 19:20:18','2026-05-31 19:20:18'),(312,17,'Okigwe','2026-05-31 19:20:18','2026-05-31 19:20:18'),(313,17,'Onuimo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(314,17,'Orlu','2026-05-31 19:20:18','2026-05-31 19:20:18'),(315,17,'Orsu','2026-05-31 19:20:18','2026-05-31 19:20:18'),(316,17,'Oru-East','2026-05-31 19:20:18','2026-05-31 19:20:18'),(317,17,'Oru-West','2026-05-31 19:20:18','2026-05-31 19:20:18'),(318,17,'Owerri-Municipal','2026-05-31 19:20:18','2026-05-31 19:20:18'),(319,17,'Owerri-North','2026-05-31 19:20:18','2026-05-31 19:20:18'),(320,17,'Owerri-West','2026-05-31 19:20:18','2026-05-31 19:20:18'),(321,18,'Auyo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(322,18,'Babura','2026-05-31 19:20:18','2026-05-31 19:20:18'),(323,18,'Biriniwa','2026-05-31 19:20:18','2026-05-31 19:20:18'),(324,18,'Birnin-Kudu','2026-05-31 19:20:18','2026-05-31 19:20:18'),(325,18,'Buji','2026-05-31 19:20:18','2026-05-31 19:20:18'),(326,18,'Dutse','2026-05-31 19:20:18','2026-05-31 19:20:18'),(327,18,'Gagarawa','2026-05-31 19:20:18','2026-05-31 19:20:18'),(328,18,'Garki','2026-05-31 19:20:18','2026-05-31 19:20:18'),(329,18,'Gumel','2026-05-31 19:20:18','2026-05-31 19:20:18'),(330,18,'Guri','2026-05-31 19:20:18','2026-05-31 19:20:18'),(331,18,'Gwaram','2026-05-31 19:20:18','2026-05-31 19:20:18'),(332,18,'Gwiwa','2026-05-31 19:20:18','2026-05-31 19:20:18'),(333,18,'Hadejia','2026-05-31 19:20:19','2026-05-31 19:20:19'),(334,18,'Jahun','2026-05-31 19:20:19','2026-05-31 19:20:19'),(335,18,'Kafin-Hausa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(336,18,'Kaugama','2026-05-31 19:20:19','2026-05-31 19:20:19'),(337,18,'Kazaure','2026-05-31 19:20:19','2026-05-31 19:20:19'),(338,18,'Kiri kasama','2026-05-31 19:20:19','2026-05-31 19:20:19'),(339,18,'Maigatari','2026-05-31 19:20:19','2026-05-31 19:20:19'),(340,18,'Malam Madori','2026-05-31 19:20:19','2026-05-31 19:20:19'),(341,18,'Miga','2026-05-31 19:20:19','2026-05-31 19:20:19'),(342,18,'Ringim','2026-05-31 19:20:19','2026-05-31 19:20:19'),(343,18,'Roni','2026-05-31 19:20:19','2026-05-31 19:20:19'),(344,18,'Sule-Tankarkar','2026-05-31 19:20:19','2026-05-31 19:20:19'),(345,18,'Taura','2026-05-31 19:20:19','2026-05-31 19:20:19'),(346,18,'Yankwashi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(347,19,'Birnin-Gwari','2026-05-31 19:20:19','2026-05-31 19:20:19'),(348,19,'Chikun','2026-05-31 19:20:19','2026-05-31 19:20:19'),(349,19,'Giwa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(350,19,'Igabi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(351,19,'Ikara','2026-05-31 19:20:19','2026-05-31 19:20:19'),(352,19,'Jaba','2026-05-31 19:20:19','2026-05-31 19:20:19'),(353,19,'Jema\'A','2026-05-31 19:20:19','2026-05-31 19:20:19'),(354,19,'Kachia','2026-05-31 19:20:19','2026-05-31 19:20:19'),(355,19,'Kaduna-North','2026-05-31 19:20:19','2026-05-31 19:20:19'),(356,19,'Kaduna-South','2026-05-31 19:20:19','2026-05-31 19:20:19'),(357,19,'Kagarko','2026-05-31 19:20:19','2026-05-31 19:20:19'),(358,19,'Kajuru','2026-05-31 19:20:19','2026-05-31 19:20:19'),(359,19,'Kaura','2026-05-31 19:20:19','2026-05-31 19:20:19'),(360,19,'Kauru','2026-05-31 19:20:19','2026-05-31 19:20:19'),(361,19,'Kubau','2026-05-31 19:20:19','2026-05-31 19:20:19'),(362,19,'Kudan','2026-05-31 19:20:19','2026-05-31 19:20:19'),(363,19,'Lere','2026-05-31 19:20:19','2026-05-31 19:20:19'),(364,19,'Makarfi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(365,19,'Sabon-Gari','2026-05-31 19:20:19','2026-05-31 19:20:19'),(366,19,'Sanga','2026-05-31 19:20:19','2026-05-31 19:20:19'),(367,19,'Soba','2026-05-31 19:20:19','2026-05-31 19:20:19'),(368,19,'Zangon-Kataf','2026-05-31 19:20:19','2026-05-31 19:20:19'),(369,19,'Zaria','2026-05-31 19:20:19','2026-05-31 19:20:19'),(370,20,'Ajingi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(371,20,'Albasu','2026-05-31 19:20:19','2026-05-31 19:20:19'),(372,20,'Bagwai','2026-05-31 19:20:19','2026-05-31 19:20:19'),(373,20,'Bebeji','2026-05-31 19:20:19','2026-05-31 19:20:19'),(374,20,'Bichi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(375,20,'Bunkure','2026-05-31 19:20:19','2026-05-31 19:20:19'),(376,20,'Dala','2026-05-31 19:20:19','2026-05-31 19:20:19'),(377,20,'Dambatta','2026-05-31 19:20:19','2026-05-31 19:20:19'),(378,20,'Dawakin-Kudu','2026-05-31 19:20:19','2026-05-31 19:20:19'),(379,20,'Dawakin-Tofa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(380,20,'Doguwa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(381,20,'Fagge','2026-05-31 19:20:19','2026-05-31 19:20:19'),(382,20,'Gabasawa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(383,20,'Garko','2026-05-31 19:20:19','2026-05-31 19:20:19'),(384,20,'Garun-Mallam','2026-05-31 19:20:19','2026-05-31 19:20:19'),(385,20,'Gaya','2026-05-31 19:20:19','2026-05-31 19:20:19'),(386,20,'Gezawa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(387,20,'Gwale','2026-05-31 19:20:19','2026-05-31 19:20:19'),(388,20,'Gwarzo','2026-05-31 19:20:19','2026-05-31 19:20:19'),(389,20,'Kabo','2026-05-31 19:20:19','2026-05-31 19:20:19'),(390,20,'Kano-Municipal','2026-05-31 19:20:19','2026-05-31 19:20:19'),(391,20,'Karaye','2026-05-31 19:20:19','2026-05-31 19:20:19'),(392,20,'Kibiya','2026-05-31 19:20:19','2026-05-31 19:20:19'),(393,20,'Kiru','2026-05-31 19:20:19','2026-05-31 19:20:19'),(394,20,'Kumbotso','2026-05-31 19:20:19','2026-05-31 19:20:19'),(395,20,'Kunchi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(396,20,'Kura','2026-05-31 19:20:19','2026-05-31 19:20:19'),(397,20,'Madobi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(398,20,'Makoda','2026-05-31 19:20:19','2026-05-31 19:20:19'),(399,20,'Minjibir','2026-05-31 19:20:19','2026-05-31 19:20:19'),(400,20,'Nasarawa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(401,20,'Rano','2026-05-31 19:20:19','2026-05-31 19:20:19'),(402,20,'Rimin-Gado','2026-05-31 19:20:19','2026-05-31 19:20:19'),(403,20,'Rogo','2026-05-31 19:20:19','2026-05-31 19:20:19'),(404,20,'Shanono','2026-05-31 19:20:19','2026-05-31 19:20:19'),(405,20,'Sumaila','2026-05-31 19:20:19','2026-05-31 19:20:19'),(406,20,'Takai','2026-05-31 19:20:19','2026-05-31 19:20:19'),(407,20,'Tarauni','2026-05-31 19:20:19','2026-05-31 19:20:19'),(408,20,'Tofa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(409,20,'Tsanyawa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(410,20,'Tudun-Wada','2026-05-31 19:20:19','2026-05-31 19:20:19'),(411,20,'Ungogo','2026-05-31 19:20:19','2026-05-31 19:20:19'),(412,20,'Warawa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(413,20,'Wudil','2026-05-31 19:20:19','2026-05-31 19:20:19'),(414,21,'Bakori','2026-05-31 19:20:19','2026-05-31 19:20:19'),(415,21,'Batagarawa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(416,21,'Batsari','2026-05-31 19:20:19','2026-05-31 19:20:19'),(417,21,'Baure','2026-05-31 19:20:19','2026-05-31 19:20:19'),(418,21,'Bindawa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(419,21,'Charanchi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(420,21,'Dan-Musa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(421,21,'Dandume','2026-05-31 19:20:19','2026-05-31 19:20:19'),(422,21,'Danja','2026-05-31 19:20:19','2026-05-31 19:20:19'),(423,21,'Daura','2026-05-31 19:20:19','2026-05-31 19:20:19'),(424,21,'Dutsi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(425,21,'Dutsin-Ma','2026-05-31 19:20:19','2026-05-31 19:20:19'),(426,21,'Faskari','2026-05-31 19:20:19','2026-05-31 19:20:19'),(427,21,'Funtua','2026-05-31 19:20:19','2026-05-31 19:20:19'),(428,21,'Ingawa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(429,21,'Jibia','2026-05-31 19:20:19','2026-05-31 19:20:19'),(430,21,'Kafur','2026-05-31 19:20:19','2026-05-31 19:20:19'),(431,21,'Kaita','2026-05-31 19:20:19','2026-05-31 19:20:19'),(432,21,'Kankara','2026-05-31 19:20:19','2026-05-31 19:20:19'),(433,21,'Kankia','2026-05-31 19:20:19','2026-05-31 19:20:19'),(434,21,'Katsina','2026-05-31 19:20:19','2026-05-31 19:20:19'),(435,21,'Kurfi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(436,21,'Kusada','2026-05-31 19:20:19','2026-05-31 19:20:19'),(437,21,'Mai-Adua','2026-05-31 19:20:19','2026-05-31 19:20:19'),(438,21,'Malumfashi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(439,21,'Mani','2026-05-31 19:20:19','2026-05-31 19:20:19'),(440,21,'Mashi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(441,21,'Matazu','2026-05-31 19:20:19','2026-05-31 19:20:19'),(442,21,'Musawa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(443,21,'Rimi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(444,21,'Sabuwa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(445,21,'Safana','2026-05-31 19:20:19','2026-05-31 19:20:19'),(446,21,'Sandamu','2026-05-31 19:20:19','2026-05-31 19:20:19'),(447,21,'Zango','2026-05-31 19:20:19','2026-05-31 19:20:19'),(448,22,'Aleiro','2026-05-31 19:20:19','2026-05-31 19:20:19'),(449,22,'Arewa-Dandi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(450,22,'Argungu','2026-05-31 19:20:19','2026-05-31 19:20:19'),(451,22,'Augie','2026-05-31 19:20:19','2026-05-31 19:20:19'),(452,22,'Bagudo','2026-05-31 19:20:19','2026-05-31 19:20:19'),(453,22,'Birnin-Kebbi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(454,22,'Bunza','2026-05-31 19:20:19','2026-05-31 19:20:19'),(455,22,'Dandi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(456,22,'Fakai','2026-05-31 19:20:19','2026-05-31 19:20:19'),(457,22,'Gwandu','2026-05-31 19:20:19','2026-05-31 19:20:19'),(458,22,'Jega','2026-05-31 19:20:19','2026-05-31 19:20:19'),(459,22,'Kalgo','2026-05-31 19:20:19','2026-05-31 19:20:19'),(460,22,'Koko-Besse','2026-05-31 19:20:19','2026-05-31 19:20:19'),(461,22,'Maiyama','2026-05-31 19:20:19','2026-05-31 19:20:19'),(462,22,'Ngaski','2026-05-31 19:20:19','2026-05-31 19:20:19'),(463,22,'Sakaba','2026-05-31 19:20:19','2026-05-31 19:20:19'),(464,22,'Shanga','2026-05-31 19:20:19','2026-05-31 19:20:19'),(465,22,'Suru','2026-05-31 19:20:19','2026-05-31 19:20:19'),(466,22,'Wasagu/Danko','2026-05-31 19:20:19','2026-05-31 19:20:19'),(467,22,'Yauri','2026-05-31 19:20:19','2026-05-31 19:20:19'),(468,22,'Zuru','2026-05-31 19:20:19','2026-05-31 19:20:19'),(469,23,'Adavi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(470,23,'Ajaokuta','2026-05-31 19:20:19','2026-05-31 19:20:19'),(471,23,'Ankpa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(472,23,'Dekina','2026-05-31 19:20:19','2026-05-31 19:20:19'),(473,23,'Ibaji','2026-05-31 19:20:19','2026-05-31 19:20:19'),(474,23,'Idah','2026-05-31 19:20:19','2026-05-31 19:20:19'),(475,23,'Igalamela-Odolu','2026-05-31 19:20:19','2026-05-31 19:20:19'),(476,23,'Ijumu','2026-05-31 19:20:19','2026-05-31 19:20:19'),(477,23,'Kabba Bunu','2026-05-31 19:20:19','2026-05-31 19:20:19'),(478,23,'Kogi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(479,23,'Lokoja','2026-05-31 19:20:19','2026-05-31 19:20:19'),(480,23,'Mopa-Muro','2026-05-31 19:20:19','2026-05-31 19:20:19'),(481,23,'Ofu','2026-05-31 19:20:19','2026-05-31 19:20:19'),(482,23,'Ogori Magongo','2026-05-31 19:20:19','2026-05-31 19:20:19'),(483,23,'Okehi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(484,23,'Okene','2026-05-31 19:20:19','2026-05-31 19:20:19'),(485,23,'Olamaboro','2026-05-31 19:20:19','2026-05-31 19:20:19'),(486,23,'Omala','2026-05-31 19:20:19','2026-05-31 19:20:19'),(487,23,'Oyi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(488,23,'Yagba-East','2026-05-31 19:20:19','2026-05-31 19:20:19'),(489,23,'Yagba-West','2026-05-31 19:20:19','2026-05-31 19:20:19'),(490,24,'Asa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(491,24,'Baruten','2026-05-31 19:20:19','2026-05-31 19:20:19'),(492,24,'Edu','2026-05-31 19:20:19','2026-05-31 19:20:19'),(493,24,'Ekiti (Araromi/Opin)','2026-05-31 19:20:19','2026-05-31 19:20:19'),(494,24,'Ilorin-East','2026-05-31 19:20:19','2026-05-31 19:20:19'),(495,24,'Ilorin-South','2026-05-31 19:20:19','2026-05-31 19:20:19'),(496,24,'Ilorin-West','2026-05-31 19:20:19','2026-05-31 19:20:19'),(497,24,'Isin','2026-05-31 19:20:19','2026-05-31 19:20:19'),(498,24,'Kaiama','2026-05-31 19:20:19','2026-05-31 19:20:19'),(499,24,'Moro','2026-05-31 19:20:19','2026-05-31 19:20:19'),(500,24,'Offa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(501,24,'Oke-Ero','2026-05-31 19:20:19','2026-05-31 19:20:19'),(502,24,'Oyun','2026-05-31 19:20:19','2026-05-31 19:20:19'),(503,24,'Pategi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(504,25,'Agege','2026-05-31 19:20:19','2026-05-31 19:20:19'),(505,25,'Ajeromi-Ifelodun','2026-05-31 19:20:19','2026-05-31 19:20:19'),(506,25,'Alimosho','2026-05-31 19:20:19','2026-05-31 19:20:19'),(507,25,'Amuwo-Odofin','2026-05-31 19:20:19','2026-05-31 19:20:19'),(508,25,'Apapa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(509,25,'Badagry','2026-05-31 19:20:19','2026-05-31 19:20:19'),(510,25,'Epe','2026-05-31 19:20:19','2026-05-31 19:20:19'),(511,25,'Eti-Osa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(512,25,'Ibeju-Lekki','2026-05-31 19:20:19','2026-05-31 19:20:19'),(513,25,'Ifako-Ijaiye','2026-05-31 19:20:19','2026-05-31 19:20:19'),(514,25,'Ikeja','2026-05-31 19:20:19','2026-05-31 19:20:19'),(515,25,'Ikorodu','2026-05-31 19:20:19','2026-05-31 19:20:19'),(516,25,'Kosofe','2026-05-31 19:20:19','2026-05-31 19:20:19'),(517,25,'Lagos-Island','2026-05-31 19:20:19','2026-05-31 19:20:19'),(518,25,'Lagos-Mainland','2026-05-31 19:20:19','2026-05-31 19:20:19'),(519,25,'Mushin','2026-05-31 19:20:19','2026-05-31 19:20:19'),(520,25,'Ojo','2026-05-31 19:20:19','2026-05-31 19:20:19'),(521,25,'Oshodi-Isolo','2026-05-31 19:20:19','2026-05-31 19:20:19'),(522,25,'Shomolu','2026-05-31 19:20:19','2026-05-31 19:20:19'),(523,25,'Surulere','2026-05-31 19:20:19','2026-05-31 19:20:19'),(524,26,'Akwanga','2026-05-31 19:20:19','2026-05-31 19:20:19'),(525,26,'Awe','2026-05-31 19:20:19','2026-05-31 19:20:19'),(526,26,'Doma','2026-05-31 19:20:19','2026-05-31 19:20:19'),(527,26,'Karu','2026-05-31 19:20:19','2026-05-31 19:20:19'),(528,26,'Keana','2026-05-31 19:20:19','2026-05-31 19:20:19'),(529,26,'Keffi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(530,26,'Kokona','2026-05-31 19:20:19','2026-05-31 19:20:19'),(531,26,'Lafia','2026-05-31 19:20:19','2026-05-31 19:20:19'),(532,26,'Nasarawa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(533,26,'Nasarawa-Eggon','2026-05-31 19:20:19','2026-05-31 19:20:19'),(534,26,'Obi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(535,26,'Wamba','2026-05-31 19:20:19','2026-05-31 19:20:19'),(536,26,'Toto','2026-05-31 19:20:19','2026-05-31 19:20:19'),(537,27,'Agaie','2026-05-31 19:20:19','2026-05-31 19:20:19'),(538,27,'Agwara','2026-05-31 19:20:19','2026-05-31 19:20:19'),(539,27,'Bida','2026-05-31 19:20:19','2026-05-31 19:20:19'),(540,27,'Borgu','2026-05-31 19:20:19','2026-05-31 19:20:19'),(541,27,'Bosso','2026-05-31 19:20:19','2026-05-31 19:20:19'),(542,27,'Chanchaga','2026-05-31 19:20:19','2026-05-31 19:20:19'),(543,27,'Edati','2026-05-31 19:20:19','2026-05-31 19:20:19'),(544,27,'Gbako','2026-05-31 19:20:19','2026-05-31 19:20:19'),(545,27,'Gurara','2026-05-31 19:20:19','2026-05-31 19:20:19'),(546,27,'Katcha','2026-05-31 19:20:19','2026-05-31 19:20:19'),(547,27,'Kontagora','2026-05-31 19:20:20','2026-05-31 19:20:20'),(548,27,'Lapai','2026-05-31 19:20:20','2026-05-31 19:20:20'),(549,27,'Lavun','2026-05-31 19:20:20','2026-05-31 19:20:20'),(550,27,'Magama','2026-05-31 19:20:20','2026-05-31 19:20:20'),(551,27,'Mariga','2026-05-31 19:20:20','2026-05-31 19:20:20'),(552,27,'Mashegu','2026-05-31 19:20:20','2026-05-31 19:20:20'),(553,27,'Mokwa','2026-05-31 19:20:20','2026-05-31 19:20:20'),(554,27,'Moya','2026-05-31 19:20:20','2026-05-31 19:20:20'),(555,27,'Paikoro','2026-05-31 19:20:20','2026-05-31 19:20:20'),(556,27,'Rafi','2026-05-31 19:20:20','2026-05-31 19:20:20'),(557,27,'Rijau','2026-05-31 19:20:20','2026-05-31 19:20:20'),(558,27,'Shiroro','2026-05-31 19:20:20','2026-05-31 19:20:20'),(559,27,'Suleja','2026-05-31 19:20:20','2026-05-31 19:20:20'),(560,27,'Tafa','2026-05-31 19:20:20','2026-05-31 19:20:20'),(561,27,'Wushishi','2026-05-31 19:20:20','2026-05-31 19:20:20'),(562,28,'Abeokuta-North','2026-05-31 19:20:20','2026-05-31 19:20:20'),(563,28,'Abeokuta-South','2026-05-31 19:20:20','2026-05-31 19:20:20'),(564,28,'Ado-Odo Ota','2026-05-31 19:20:20','2026-05-31 19:20:20'),(565,28,'Ewekoro','2026-05-31 19:20:20','2026-05-31 19:20:20'),(566,28,'Ifo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(567,28,'Ijebu-East','2026-05-31 19:20:20','2026-05-31 19:20:20'),(568,28,'Ijebu-North','2026-05-31 19:20:20','2026-05-31 19:20:20'),(569,28,'Ijebu-North-East','2026-05-31 19:20:20','2026-05-31 19:20:20'),(570,28,'Ijebu-Ode','2026-05-31 19:20:20','2026-05-31 19:20:20'),(571,28,'Ikenne','2026-05-31 19:20:20','2026-05-31 19:20:20'),(572,28,'Imeko-Afon','2026-05-31 19:20:20','2026-05-31 19:20:20'),(573,28,'Ipokia','2026-05-31 19:20:20','2026-05-31 19:20:20'),(574,28,'Obafemi-Owode','2026-05-31 19:20:20','2026-05-31 19:20:20'),(575,28,'Odeda','2026-05-31 19:20:20','2026-05-31 19:20:20'),(576,28,'Odogbolu','2026-05-31 19:20:20','2026-05-31 19:20:20'),(577,28,'Ogun-Waterside','2026-05-31 19:20:20','2026-05-31 19:20:20'),(578,28,'Remo-North','2026-05-31 19:20:20','2026-05-31 19:20:20'),(579,28,'Shagamu','2026-05-31 19:20:20','2026-05-31 19:20:20'),(580,28,'Yewa North','2026-05-31 19:20:20','2026-05-31 19:20:20'),(581,29,'Akoko North-East','2026-05-31 19:20:20','2026-05-31 19:20:20'),(582,29,'Akoko North-West','2026-05-31 19:20:20','2026-05-31 19:20:20'),(583,29,'Akoko South-West','2026-05-31 19:20:20','2026-05-31 19:20:20'),(584,29,'Akoko South-East','2026-05-31 19:20:20','2026-05-31 19:20:20'),(585,29,'Akure-North','2026-05-31 19:20:20','2026-05-31 19:20:20'),(586,29,'Akure-South','2026-05-31 19:20:20','2026-05-31 19:20:20'),(587,29,'Ese-Odo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(588,29,'Idanre','2026-05-31 19:20:20','2026-05-31 19:20:20'),(589,29,'Ifedore','2026-05-31 19:20:20','2026-05-31 19:20:20'),(590,29,'Ilaje','2026-05-31 19:20:20','2026-05-31 19:20:20'),(591,29,'Ile-Oluji-Okeigbo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(592,29,'Irele','2026-05-31 19:20:20','2026-05-31 19:20:20'),(593,29,'Odigbo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(594,29,'Okitipupa','2026-05-31 19:20:20','2026-05-31 19:20:20'),(595,29,'Ondo West','2026-05-31 19:20:20','2026-05-31 19:20:20'),(596,29,'Ondo-East','2026-05-31 19:20:20','2026-05-31 19:20:20'),(597,29,'Ose','2026-05-31 19:20:20','2026-05-31 19:20:20'),(598,29,'Owo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(599,30,'Atakumosa West','2026-05-31 19:20:20','2026-05-31 19:20:20'),(600,30,'Atakumosa East','2026-05-31 19:20:20','2026-05-31 19:20:20'),(601,30,'Ayedaade','2026-05-31 19:20:20','2026-05-31 19:20:20'),(602,30,'Ayedire','2026-05-31 19:20:20','2026-05-31 19:20:20'),(603,30,'Boluwaduro','2026-05-31 19:20:20','2026-05-31 19:20:20'),(604,30,'Boripe','2026-05-31 19:20:20','2026-05-31 19:20:20'),(605,30,'Ede South','2026-05-31 19:20:20','2026-05-31 19:20:20'),(606,30,'Ede North','2026-05-31 19:20:20','2026-05-31 19:20:20'),(607,30,'Egbedore','2026-05-31 19:20:20','2026-05-31 19:20:20'),(608,30,'Ejigbo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(609,30,'Ife North','2026-05-31 19:20:20','2026-05-31 19:20:20'),(610,30,'Ife South','2026-05-31 19:20:20','2026-05-31 19:20:20'),(611,30,'Ife-Central','2026-05-31 19:20:20','2026-05-31 19:20:20'),(612,30,'Ife-East','2026-05-31 19:20:20','2026-05-31 19:20:20'),(613,30,'Ifelodun','2026-05-31 19:20:20','2026-05-31 19:20:20'),(614,30,'Ila','2026-05-31 19:20:20','2026-05-31 19:20:20'),(615,30,'Ilesa-East','2026-05-31 19:20:20','2026-05-31 19:20:20'),(616,30,'Ilesa-West','2026-05-31 19:20:20','2026-05-31 19:20:20'),(617,30,'Irepodun','2026-05-31 19:20:20','2026-05-31 19:20:20'),(618,30,'Irewole','2026-05-31 19:20:20','2026-05-31 19:20:20'),(619,30,'Isokan','2026-05-31 19:20:20','2026-05-31 19:20:20'),(620,30,'Iwo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(621,30,'Obokun','2026-05-31 19:20:20','2026-05-31 19:20:20'),(622,30,'Odo-Otin','2026-05-31 19:20:20','2026-05-31 19:20:20'),(623,30,'Ola Oluwa','2026-05-31 19:20:20','2026-05-31 19:20:20'),(624,30,'Olorunda','2026-05-31 19:20:20','2026-05-31 19:20:20'),(625,30,'Oriade','2026-05-31 19:20:20','2026-05-31 19:20:20'),(626,30,'Orolu','2026-05-31 19:20:20','2026-05-31 19:20:20'),(627,30,'Osogbo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(628,31,'Afijio','2026-05-31 19:20:20','2026-05-31 19:20:20'),(629,31,'Akinyele','2026-05-31 19:20:20','2026-05-31 19:20:20'),(630,31,'Atiba','2026-05-31 19:20:20','2026-05-31 19:20:20'),(631,31,'Atisbo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(632,31,'Egbeda','2026-05-31 19:20:20','2026-05-31 19:20:20'),(633,31,'Ibadan North','2026-05-31 19:20:20','2026-05-31 19:20:20'),(634,31,'Ibadan North-East','2026-05-31 19:20:20','2026-05-31 19:20:20'),(635,31,'Ibadan North-West','2026-05-31 19:20:20','2026-05-31 19:20:20'),(636,31,'Ibadan South-East','2026-05-31 19:20:20','2026-05-31 19:20:20'),(637,31,'Ibadan South-West','2026-05-31 19:20:20','2026-05-31 19:20:20'),(638,31,'Ibarapa-Central','2026-05-31 19:20:20','2026-05-31 19:20:20'),(639,31,'Ibarapa-East','2026-05-31 19:20:20','2026-05-31 19:20:20'),(640,31,'Ibarapa-North','2026-05-31 19:20:20','2026-05-31 19:20:20'),(641,31,'Ido','2026-05-31 19:20:20','2026-05-31 19:20:20'),(642,31,'Ifedayo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(643,31,'Irepo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(644,31,'Iseyin','2026-05-31 19:20:20','2026-05-31 19:20:20'),(645,31,'Itesiwaju','2026-05-31 19:20:20','2026-05-31 19:20:20'),(646,31,'Iwajowa','2026-05-31 19:20:20','2026-05-31 19:20:20'),(647,31,'Kajola','2026-05-31 19:20:20','2026-05-31 19:20:20'),(648,31,'Lagelu','2026-05-31 19:20:20','2026-05-31 19:20:20'),(649,31,'Ogo-Oluwa','2026-05-31 19:20:20','2026-05-31 19:20:20'),(650,31,'Ogbomosho-North','2026-05-31 19:20:20','2026-05-31 19:20:20'),(651,31,'Ogbomosho-South','2026-05-31 19:20:20','2026-05-31 19:20:20'),(652,31,'Olorunsogo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(653,31,'Oluyole','2026-05-31 19:20:20','2026-05-31 19:20:20'),(654,31,'Ona-Ara','2026-05-31 19:20:20','2026-05-31 19:20:20'),(655,31,'Orelope','2026-05-31 19:20:20','2026-05-31 19:20:20'),(656,31,'Ori-Ire','2026-05-31 19:20:20','2026-05-31 19:20:20'),(657,31,'Oyo-West','2026-05-31 19:20:20','2026-05-31 19:20:20'),(658,31,'Oyo-East','2026-05-31 19:20:20','2026-05-31 19:20:20'),(659,31,'Saki-East','2026-05-31 19:20:20','2026-05-31 19:20:20'),(660,31,'Saki-West','2026-05-31 19:20:20','2026-05-31 19:20:20'),(661,31,'Surulere','2026-05-31 19:20:20','2026-05-31 19:20:20'),(662,32,'Barkin-Ladi','2026-05-31 19:20:20','2026-05-31 19:20:20'),(663,32,'Bassa','2026-05-31 19:20:20','2026-05-31 19:20:20'),(664,32,'Bokkos','2026-05-31 19:20:20','2026-05-31 19:20:20'),(665,32,'Jos-East','2026-05-31 19:20:20','2026-05-31 19:20:20'),(666,32,'Jos-North','2026-05-31 19:20:20','2026-05-31 19:20:20'),(667,32,'Jos-South','2026-05-31 19:20:20','2026-05-31 19:20:20'),(668,32,'Kanam','2026-05-31 19:20:20','2026-05-31 19:20:20'),(669,32,'Kanke','2026-05-31 19:20:20','2026-05-31 19:20:20'),(670,32,'Langtang-North','2026-05-31 19:20:20','2026-05-31 19:20:20'),(671,32,'Langtang-South','2026-05-31 19:20:20','2026-05-31 19:20:20'),(672,32,'Mangu','2026-05-31 19:20:20','2026-05-31 19:20:20'),(673,32,'Mikang','2026-05-31 19:20:20','2026-05-31 19:20:20'),(674,32,'Pankshin','2026-05-31 19:20:20','2026-05-31 19:20:20'),(675,32,'Qua\'an Pan','2026-05-31 19:20:20','2026-05-31 19:20:20'),(676,32,'Riyom','2026-05-31 19:20:20','2026-05-31 19:20:20'),(677,32,'Shendam','2026-05-31 19:20:20','2026-05-31 19:20:20'),(678,32,'Wase','2026-05-31 19:20:20','2026-05-31 19:20:20'),(679,33,'Abua Odual','2026-05-31 19:20:20','2026-05-31 19:20:20'),(680,33,'Ahoada-East','2026-05-31 19:20:20','2026-05-31 19:20:20'),(681,33,'Ahoada-West','2026-05-31 19:20:20','2026-05-31 19:20:20'),(682,33,'Akuku Toru','2026-05-31 19:20:20','2026-05-31 19:20:20'),(683,33,'Andoni','2026-05-31 19:20:20','2026-05-31 19:20:20'),(684,33,'Asari-Toru','2026-05-31 19:20:20','2026-05-31 19:20:20'),(685,33,'Bonny','2026-05-31 19:20:20','2026-05-31 19:20:20'),(686,33,'Degema','2026-05-31 19:20:20','2026-05-31 19:20:20'),(687,33,'Eleme','2026-05-31 19:20:20','2026-05-31 19:20:20'),(688,33,'Emuoha','2026-05-31 19:20:20','2026-05-31 19:20:20'),(689,33,'Etche','2026-05-31 19:20:20','2026-05-31 19:20:20'),(690,33,'Gokana','2026-05-31 19:20:20','2026-05-31 19:20:20'),(691,33,'Ikwerre','2026-05-31 19:20:20','2026-05-31 19:20:20'),(692,33,'Khana','2026-05-31 19:20:20','2026-05-31 19:20:20'),(693,33,'Obio Akpor','2026-05-31 19:20:20','2026-05-31 19:20:20'),(694,33,'Ogba-Egbema-Ndoni','2026-05-31 19:20:20','2026-05-31 19:20:20'),(695,33,'Ogba Egbema Ndoni','2026-05-31 19:20:20','2026-05-31 19:20:20'),(696,33,'Ogu Bolo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(697,33,'Okrika','2026-05-31 19:20:20','2026-05-31 19:20:20'),(698,33,'Omuma','2026-05-31 19:20:20','2026-05-31 19:20:20'),(699,33,'Opobo Nkoro','2026-05-31 19:20:20','2026-05-31 19:20:20'),(700,33,'Oyigbo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(701,33,'Port-Harcourt','2026-05-31 19:20:20','2026-05-31 19:20:20'),(702,33,'Tai','2026-05-31 19:20:20','2026-05-31 19:20:20'),(703,34,'Binji','2026-05-31 19:20:20','2026-05-31 19:20:20'),(704,34,'Bodinga','2026-05-31 19:20:20','2026-05-31 19:20:20'),(705,34,'Dange-Shuni','2026-05-31 19:20:20','2026-05-31 19:20:20'),(706,34,'Gada','2026-05-31 19:20:20','2026-05-31 19:20:20'),(707,34,'Goronyo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(708,34,'Gudu','2026-05-31 19:20:20','2026-05-31 19:20:20'),(709,34,'Gwadabawa','2026-05-31 19:20:20','2026-05-31 19:20:20'),(710,34,'Illela','2026-05-31 19:20:20','2026-05-31 19:20:20'),(711,34,'Kebbe','2026-05-31 19:20:20','2026-05-31 19:20:20'),(712,34,'Kware','2026-05-31 19:20:20','2026-05-31 19:20:20'),(713,34,'Rabah','2026-05-31 19:20:20','2026-05-31 19:20:20'),(714,34,'Sabon Birni','2026-05-31 19:20:20','2026-05-31 19:20:20'),(715,34,'Shagari','2026-05-31 19:20:20','2026-05-31 19:20:20'),(716,34,'Silame','2026-05-31 19:20:20','2026-05-31 19:20:20'),(717,34,'Sokoto-North','2026-05-31 19:20:20','2026-05-31 19:20:20'),(718,34,'Sokoto-South','2026-05-31 19:20:20','2026-05-31 19:20:20'),(719,34,'Tambuwal','2026-05-31 19:20:20','2026-05-31 19:20:20'),(720,34,'Tangaza','2026-05-31 19:20:20','2026-05-31 19:20:20'),(721,34,'Tureta','2026-05-31 19:20:20','2026-05-31 19:20:20'),(722,34,'Wamako','2026-05-31 19:20:20','2026-05-31 19:20:20'),(723,34,'Wurno','2026-05-31 19:20:20','2026-05-31 19:20:20'),(724,34,'Yabo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(725,35,'Ardo-Kola','2026-05-31 19:20:20','2026-05-31 19:20:20'),(726,35,'Bali','2026-05-31 19:20:20','2026-05-31 19:20:20'),(727,35,'Donga','2026-05-31 19:20:20','2026-05-31 19:20:20'),(728,35,'Gashaka','2026-05-31 19:20:20','2026-05-31 19:20:20'),(729,35,'Gassol','2026-05-31 19:20:20','2026-05-31 19:20:20'),(730,35,'Ibi','2026-05-31 19:20:20','2026-05-31 19:20:20'),(731,35,'Jalingo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(732,35,'Karim-Lamido','2026-05-31 19:20:20','2026-05-31 19:20:20'),(733,35,'Kurmi','2026-05-31 19:20:20','2026-05-31 19:20:20'),(734,35,'Lau','2026-05-31 19:20:20','2026-05-31 19:20:20'),(735,35,'Sardauna','2026-05-31 19:20:20','2026-05-31 19:20:20'),(736,35,'Takum','2026-05-31 19:20:20','2026-05-31 19:20:20'),(737,35,'Ussa','2026-05-31 19:20:20','2026-05-31 19:20:20'),(738,35,'Wukari','2026-05-31 19:20:20','2026-05-31 19:20:20'),(739,35,'Yorro','2026-05-31 19:20:20','2026-05-31 19:20:20'),(740,35,'Zing','2026-05-31 19:20:20','2026-05-31 19:20:20'),(741,36,'Bade','2026-05-31 19:20:20','2026-05-31 19:20:20'),(742,36,'Bursari','2026-05-31 19:20:20','2026-05-31 19:20:20'),(743,36,'Damaturu','2026-05-31 19:20:20','2026-05-31 19:20:20'),(744,36,'Fika','2026-05-31 19:20:20','2026-05-31 19:20:20'),(745,36,'Fune','2026-05-31 19:20:20','2026-05-31 19:20:20'),(746,36,'Geidam','2026-05-31 19:20:20','2026-05-31 19:20:20'),(747,36,'Gujba','2026-05-31 19:20:20','2026-05-31 19:20:20'),(748,36,'Gulani','2026-05-31 19:20:20','2026-05-31 19:20:20'),(749,36,'Jakusko','2026-05-31 19:20:20','2026-05-31 19:20:20'),(750,36,'Karasuwa','2026-05-31 19:20:20','2026-05-31 19:20:20'),(751,36,'Machina','2026-05-31 19:20:20','2026-05-31 19:20:20'),(752,36,'Nangere','2026-05-31 19:20:20','2026-05-31 19:20:20'),(753,36,'Nguru','2026-05-31 19:20:20','2026-05-31 19:20:20'),(754,36,'Potiskum','2026-05-31 19:20:20','2026-05-31 19:20:20'),(755,36,'Tarmuwa','2026-05-31 19:20:20','2026-05-31 19:20:20'),(756,36,'Yunusari','2026-05-31 19:20:20','2026-05-31 19:20:20'),(757,36,'Yusufari','2026-05-31 19:20:20','2026-05-31 19:20:20'),(758,37,'Anka','2026-05-31 19:20:20','2026-05-31 19:20:20'),(759,37,'Bakura','2026-05-31 19:20:20','2026-05-31 19:20:20'),(760,37,'Birnin Magaji/Kiyaw','2026-05-31 19:20:20','2026-05-31 19:20:20'),(761,37,'Bukkuyum','2026-05-31 19:20:20','2026-05-31 19:20:20'),(762,37,'Bungudu','2026-05-31 19:20:20','2026-05-31 19:20:20'),(763,37,'Gummi','2026-05-31 19:20:20','2026-05-31 19:20:20'),(764,37,'Gusau','2026-05-31 19:20:20','2026-05-31 19:20:20'),(765,37,'Isa','2026-05-31 19:20:20','2026-05-31 19:20:20'),(766,37,'Kaura-Namoda','2026-05-31 19:20:21','2026-05-31 19:20:21'),(767,37,'Kiyawa','2026-05-31 19:20:21','2026-05-31 19:20:21'),(768,37,'Maradun','2026-05-31 19:20:21','2026-05-31 19:20:21'),(769,37,'Maru','2026-05-31 19:20:21','2026-05-31 19:20:21'),(770,37,'Shinkafi','2026-05-31 19:20:21','2026-05-31 19:20:21'),(771,37,'Talata-Mafara','2026-05-31 19:20:21','2026-05-31 19:20:21'),(772,37,'Tsafe','2026-05-31 19:20:21','2026-05-31 19:20:21'),(773,37,'Zurmi','2026-05-31 19:20:21','2026-05-31 19:20:21');
/*!40000 ALTER TABLE `local_governments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_events`
--

DROP TABLE IF EXISTS `login_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `logged_in_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `login_events_user_id_logged_in_at_index` (`user_id`,`logged_in_at`),
  CONSTRAINT `login_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_events`
--

LOCK TABLES `login_events` WRITE;
/*!40000 ALTER TABLE `login_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_05_11_120000_add_freelancer_last_setup_reminder_at_to_users_table',2),(6,'2026_05_11_129000_create_quests_table',3),(7,'2026_05_11_140000_add_terms_accepted_at_to_quests_table',3),(8,'2026_05_11_160000_quest_site_context_and_offer_extras',3),(9,'2026_05_11_211929_add_profile_fields_to_users_table',3),(10,'2026_05_12_120000_create_roles_table',3),(11,'2026_05_12_120001_add_identity_and_profile_columns_to_users_table',3),(12,'2026_05_12_160000_add_coordinates_to_users_table',3),(13,'2026_05_12_200000_create_newsletter_subscribers_table',3),(14,'2026_05_12_200000_quest_publishing_fields_and_files',3),(15,'2026_05_12_210000_quest_marketplace_extensions',3),(16,'2026_05_13_100000_add_super_admin_role_and_user_reputation_columns',3),(17,'2026_05_13_100001_create_quests_table',3),(18,'2026_05_13_100002_create_reviews_table',3),(19,'2026_05_13_100003_create_login_events_table',3),(20,'2026_05_13_100004_create_activity_logs_table',3),(21,'2026_05_13_100005_create_notifications_table',3),(22,'2026_05_13_120000_add_client_edit_until_to_quests_table',3),(23,'2026_05_14_100000_quest_covers_and_cloudinary_file_meta',4),(24,'2026_05_14_120000_create_states_and_local_governments_tables',4),(25,'2026_05_14_120001_add_state_and_lga_foreign_keys_to_users_table',4),(26,'2026_05_14_120002_create_freelancer_business_profiles_table',4),(27,'2026_05_14_120003_create_freelancer_credentials_table',4),(28,'2026_05_14_130000_ensure_quest_offer_view_tracking_columns',4),(29,'2026_05_15_100000_backfill_quest_slugs',4),(30,'2026_05_15_140000_create_user_trust_metrics_table',4),(31,'2026_05_15_140001_migrate_user_trust_data_and_drop_redundant_columns',4),(32,'2026_05_15_140002_create_user_verifications_table',4),(33,'2026_05_16_100000_update_quest_status_in_dispute_and_add_new_values',4),(34,'2026_05_16_120000_create_admin_reporting_tables',4),(35,'2026_05_16_130000_create_admin_report_aggregate_tables',4),(36,'2026_05_16_145000_create_quest_categories_table',5),(37,'2026_05_16_150000_create_admin_activity_feed_events_table',6),(38,'2026_05_16_150500_add_admin_activity_feed_foreign_keys',6),(39,'2026_05_16_173000_advanced_user_management_tables',6),(40,'2026_05_16_190000_create_admin_financial_ledger_entries_table',7),(41,'2026_05_16_203000_create_content_moderation_tables',8),(42,'2026_05_16_225500_add_admin_visibility_to_conversation_threads_and_create_kyc_tables',9),(43,'2026_05_17_001000_create_promotions_growth_tools_tables',9),(44,'2026_05_17_070300_create_admin_content_cms_tables',9),(45,'2026_05_17_081600_upgrade_quest_categories_for_admin_management',9),(46,'2026_05_17_092300_remove_expert_verified_promotion_badge',9),(47,'2026_05_17_100000_add_city_to_users_table',9),(48,'2026_05_17_100001_create_quest_categories_table',9),(49,'2026_05_17_100002_add_matching_fields_to_quests_table',9),(50,'2026_05_17_100003_create_freelancer_quest_category_table',9),(51,'2026_05_17_100004_create_quest_offers_table',9),(52,'2026_05_17_120000_create_admin_quest_flags_table',9),(53,'2026_05_17_120000_extend_quest_offers_and_add_messaging_and_reports',9),(54,'2026_05_17_129000_add_content_reports_moderation_case_fk',9),(55,'2026_05_17_129500_add_admin_visibility_to_quest_conversation_threads',9),(56,'2026_05_17_130000_create_admin_command_risk_tables',9),(57,'2026_05_17_140000_create_admin_platform_settings_table',9),(58,'2026_05_18_100000_proposal_lifecycle_escrow_views_and_reporting',9),(59,'2026_05_18_120000_create_portfolios_table',9),(60,'2026_05_18_120001_create_portfolio_files_table',9),(61,'2026_05_18_120002_create_portfolio_favorites_table',9),(62,'2026_05_19_100000_add_account_and_social_fields_to_users_table',9),(63,'2026_05_19_100001_create_user_follows_table',9),(64,'2026_05_19_100002_create_review_attachments_table',9),(65,'2026_05_19_110000_operations_staff_invitation_columns',9),(66,'2026_05_20_120000_add_freelancer_edit_deadline_at_to_quest_offers_if_missing',9),(67,'2026_05_20_120000_add_hide_online_presence_to_users_table',9),(68,'2026_05_21_100000_quest_disputes_funding_intents_and_user_dispute_stats',9),(69,'2026_05_22_140000_quest_lifecycle_escrow_funded_and_email_logs',9),(70,'2026_05_23_090000_create_verification_engine_tables',9),(71,'2026_05_23_120000_make_admin_activity_logs_actor_nullable',9),(72,'2026_05_23_140000_create_quest_completion_events_and_release_controls',9),(73,'2026_05_24_100000_add_admin_moderation_overlay_to_quests',9),(74,'2026_05_24_120000_ensure_support_tickets_have_uuid',10),(75,'2026_05_24_140000_user_identity_documents_and_role_budget_standardization',10),(76,'2026_05_24_160000_create_email_broadcasting_tables',10),(77,'2026_05_24_180000_create_admin_proposal_moderation_tables',10),(78,'2026_05_24_210000_add_power_hours_and_verification_review_controls',10),(79,'2026_05_25_010000_create_staff_support_messaging_tables',10),(80,'2026_05_26_000000_add_performance_indexes_to_support_tables',11),(81,'2026_05_26_120000_add_staff_dispute_assignment_columns',11),(82,'2026_05_27_100000_create_staff_workspace_tables',11),(83,'2026_05_28_100000_create_staff_extended_workspace_tables',11),(84,'2026_05_28_140000_reset_staff_leave_records',12),(85,'2026_05_29_090000_create_staff_onboarding_assistance_records_table',12),(86,'2026_05_29_120000_dedupe_onboarding_assistance_records',13),(87,'2026_05_29_120000_extend_support_ticket_management',13),(88,'2026_05_30_100000_create_admin_direct_messages_tables',13),(89,'2026_05_30_120000_create_platform_sla_clocks_table',13),(90,'2026_05_30_120000_sync_verification_level_requirements',13),(91,'2026_05_30_130000_add_verification_decision_reason_columns',13),(92,'2026_05_30_140000_add_quest_quality_health_and_nudge_logs',13),(93,'2026_05_30_140000_split_client_freelancer_verification_levels',13),(94,'2026_05_30_160000_add_proposal_clarification_award_and_trust_behaviour',13),(95,'2026_05_30_180000_create_proactive_outreach_and_response_templates',13),(96,'2026_05_31_100000_add_staff_assignment_to_user_verifications',13),(97,'2026_05_31_100000_create_wallet_and_escrow_payment_tables',13),(98,'2026_05_31_120000_quest_proposal_deadline_lifecycle',13),(99,'2026_05_31_200000_create_quest_journey_surveys_table',13),(100,'2026_05_31_210000_add_reminders_sent_to_quest_journey_surveys_table',13),(101,'2026_05_34_000000_remove_live_customer_support',13),(102,'2026_06_01_100000_remove_verification_new_account_cooldown',13),(103,'2026_06_01_100000_restore_live_customer_support',13),(104,'2026_06_02_100000_create_onboarding_quality_reviews_tables',13),(105,'2026_06_02_100001_create_onboarding_quality_review_actions_table',13),(106,'2026_06_03_100000_create_payment_review_flags_table',13),(107,'2026_06_03_120000_create_trust_risk_monitoring_tables',13),(108,'2026_06_04_100000_create_conversation_monitoring_tables',13),(109,'2026_06_05_100000_create_review_rating_moderation_tables',13),(110,'2026_06_05_120000_extend_conversation_monitoring_for_clarifications',13),(111,'2026_06_05_130000_add_message_redaction_and_conversation_review_escalation',13),(112,'2026_06_06_100000_add_acknowledged_at_to_user_policy_notices',13),(113,'2026_06_06_100000_create_hr_management_tables',13),(114,'2026_06_07_100000_add_deduction_config_to_staff_payroll_adjustments',13),(115,'2026_06_07_100000_create_quest_contracts_tables',13),(116,'2026_06_07_120000_add_effective_from_to_staff_payroll_profiles',13),(117,'2026_06_07_130000_add_duration_fields_to_staff_leave_requests',13),(118,'2026_06_08_100000_create_contract_delivery_extensions_tables',14),(119,'2026_06_09_090000_repair_quest_contract_related_tables',14),(120,'2026_06_09_100000_create_freelancer_pro_and_quest_boosts_tables',15),(121,'2026_06_10_100000_create_financial_audit_ledger_tables',15);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `moderation_appeals`
--

DROP TABLE IF EXISTS `moderation_appeals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `moderation_appeals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `moderation_case_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `statement` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `review_note` text COLLATE utf8mb4_unicode_ci,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `moderation_appeals_moderation_case_id_foreign` (`moderation_case_id`),
  KEY `moderation_appeals_user_id_foreign` (`user_id`),
  KEY `moderation_appeals_reviewed_by_foreign` (`reviewed_by`),
  KEY `moderation_appeals_status_index` (`status`),
  CONSTRAINT `moderation_appeals_moderation_case_id_foreign` FOREIGN KEY (`moderation_case_id`) REFERENCES `moderation_cases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `moderation_appeals_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `moderation_appeals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `moderation_appeals`
--

LOCK TABLES `moderation_appeals` WRITE;
/*!40000 ALTER TABLE `moderation_appeals` DISABLE KEYS */;
/*!40000 ALTER TABLE `moderation_appeals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `moderation_case_triggers`
--

DROP TABLE IF EXISTS `moderation_case_triggers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `moderation_case_triggers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `moderation_case_id` bigint unsigned NOT NULL,
  `rule_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `severity` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warning',
  `confidence` tinyint unsigned NOT NULL DEFAULT '0',
  `matched_text` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `context` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `moderation_case_triggers_moderation_case_id_foreign` (`moderation_case_id`),
  KEY `moderation_case_triggers_rule_key_index` (`rule_key`),
  KEY `moderation_case_triggers_rule_type_index` (`rule_type`),
  KEY `moderation_case_triggers_category_index` (`category`),
  KEY `moderation_case_triggers_severity_index` (`severity`),
  CONSTRAINT `moderation_case_triggers_moderation_case_id_foreign` FOREIGN KEY (`moderation_case_id`) REFERENCES `moderation_cases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `moderation_case_triggers`
--

LOCK TABLES `moderation_case_triggers` WRITE;
/*!40000 ALTER TABLE `moderation_case_triggers` DISABLE KEYS */;
/*!40000 ALTER TABLE `moderation_case_triggers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `moderation_cases`
--

DROP TABLE IF EXISTS `moderation_cases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `moderation_cases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `moderatable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `moderatable_id` bigint unsigned NOT NULL,
  `subject_user_id` bigint unsigned DEFAULT NULL,
  `reporter_user_id` bigint unsigned DEFAULT NULL,
  `assigned_admin_id` bigint unsigned DEFAULT NULL,
  `content_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `severity` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warning',
  `visibility_state` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'live_under_review',
  `source` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'automated',
  `confidence` tinyint unsigned NOT NULL DEFAULT '0',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `snapshot` json DEFAULT NULL,
  `entered_queue_at` timestamp NOT NULL,
  `review_started_at` timestamp NULL DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `decision` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `moderation_cases_uuid_unique` (`uuid`),
  KEY `moderation_cases_moderatable_idx` (`moderatable_type`,`moderatable_id`),
  KEY `moderation_cases_subject_user_id_foreign` (`subject_user_id`),
  KEY `moderation_cases_reporter_user_id_foreign` (`reporter_user_id`),
  KEY `moderation_cases_assigned_admin_id_foreign` (`assigned_admin_id`),
  KEY `moderation_cases_queue_status_entered_queue_at_index` (`queue`,`status`,`entered_queue_at`),
  KEY `moderation_cases_content_type_index` (`content_type`),
  KEY `moderation_cases_queue_index` (`queue`),
  KEY `moderation_cases_status_index` (`status`),
  KEY `moderation_cases_severity_index` (`severity`),
  KEY `moderation_cases_visibility_state_index` (`visibility_state`),
  KEY `moderation_cases_source_index` (`source`),
  KEY `moderation_cases_entered_queue_at_index` (`entered_queue_at`),
  KEY `moderation_cases_decided_at_index` (`decided_at`),
  KEY `moderation_cases_decision_index` (`decision`),
  CONSTRAINT `moderation_cases_assigned_admin_id_foreign` FOREIGN KEY (`assigned_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `moderation_cases_reporter_user_id_foreign` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `moderation_cases_subject_user_id_foreign` FOREIGN KEY (`subject_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `moderation_cases`
--

LOCK TABLES `moderation_cases` WRITE;
/*!40000 ALTER TABLE `moderation_cases` DISABLE KEYS */;
/*!40000 ALTER TABLE `moderation_cases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `moderation_decisions`
--

DROP TABLE IF EXISTS `moderation_decisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `moderation_decisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `moderation_case_id` bigint unsigned NOT NULL,
  `admin_user_id` bigint unsigned NOT NULL,
  `action` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `edited_snapshot` json DEFAULT NULL,
  `time_to_decision_seconds` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `moderation_decisions_moderation_case_id_foreign` (`moderation_case_id`),
  KEY `moderation_decisions_admin_user_id_foreign` (`admin_user_id`),
  KEY `moderation_decisions_action_index` (`action`),
  KEY `moderation_decisions_reason_code_index` (`reason_code`),
  CONSTRAINT `moderation_decisions_admin_user_id_foreign` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `moderation_decisions_moderation_case_id_foreign` FOREIGN KEY (`moderation_case_id`) REFERENCES `moderation_cases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `moderation_decisions`
--

LOCK TABLES `moderation_decisions` WRITE;
/*!40000 ALTER TABLE `moderation_decisions` DISABLE KEYS */;
/*!40000 ALTER TABLE `moderation_decisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `moderation_keywords`
--

DROP TABLE IF EXISTS `moderation_keywords`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `moderation_keywords` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `phrase` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warning',
  `category` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'policy_violation',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `moderation_keywords_severity_index` (`severity`),
  KEY `moderation_keywords_category_index` (`category`),
  KEY `moderation_keywords_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `moderation_keywords`
--

LOCK TABLES `moderation_keywords` WRITE;
/*!40000 ALTER TABLE `moderation_keywords` DISABLE KEYS */;
INSERT INTO `moderation_keywords` VALUES (1,'whatsapp me','critical','off_platform_solicitation',1,'Off-platform contact solicitation.','2026-05-31 18:23:36','2026-05-31 18:23:36'),(2,'pay via transfer directly','critical','off_platform_payment',1,'Off-platform payment solicitation.','2026-05-31 18:23:36','2026-05-31 18:23:36'),(3,'contact me on instagram','warning','off_platform_solicitation',1,'Off-platform contact solicitation.','2026-05-31 18:23:36','2026-05-31 18:23:36'),(4,'wire money','warning','fraud_pattern',1,'Common scam phrase.','2026-05-31 18:23:36','2026-05-31 18:23:36');
/*!40000 ALTER TABLE `moderation_keywords` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `moderation_notification_templates`
--

DROP TABLE IF EXISTS `moderation_notification_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `moderation_notification_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `moderation_notification_templates_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `moderation_notification_templates`
--

LOCK TABLES `moderation_notification_templates` WRITE;
/*!40000 ALTER TABLE `moderation_notification_templates` DISABLE KEYS */;
INSERT INTO `moderation_notification_templates` VALUES (1,'approved_with_warning','Approved with warning','Your content was approved with a note','Your content is live, but our team noted: {{reason}}',1,'2026-05-31 18:23:36','2026-05-31 18:23:36'),(2,'removed','Content removed','Your content was removed','Your content was removed for: {{reason}}. You may appeal this decision from your account.',1,'2026-05-31 18:23:36','2026-05-31 18:23:36'),(3,'revision_requested','Revision requested','Please revise your content','Please revise your content before it can be approved: {{reason}}',1,'2026-05-31 18:23:36','2026-05-31 18:23:36');
/*!40000 ALTER TABLE `moderation_notification_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `moderation_settings`
--

DROP TABLE IF EXISTS `moderation_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `moderation_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `moderation_settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `moderation_settings`
--

LOCK TABLES `moderation_settings` WRITE;
/*!40000 ALTER TABLE `moderation_settings` DISABLE KEYS */;
INSERT INTO `moderation_settings` VALUES (1,'new_account_review_hours','48','2026-05-31 18:23:36','2026-05-31 18:23:36'),(2,'allowed_external_domains','[\"linkedin.com\", \"github.com\", \"behance.net\", \"dribbble.com\"]','2026-05-31 18:23:36','2026-05-31 18:23:36'),(3,'cloudinary_moderation_enabled','true','2026-05-31 18:23:36','2026-05-31 18:23:36');
/*!40000 ALTER TABLE `moderation_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `newsletter_subscribers`
--

DROP TABLE IF EXISTS `newsletter_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_subscribers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `newsletter_subscribers_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsletter_subscribers`
--

LOCK TABLES `newsletter_subscribers` WRITE;
/*!40000 ALTER TABLE `newsletter_subscribers` DISABLE KEYS */;
/*!40000 ALTER TABLE `newsletter_subscribers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `onboarding_quality_review_actions`
--

DROP TABLE IF EXISTS `onboarding_quality_review_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_quality_review_actions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `onboarding_quality_review_id` bigint unsigned NOT NULL,
  `admin_id` bigint unsigned NOT NULL,
  `action` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `onboarding_quality_review_actions_admin_id_foreign` (`admin_id`),
  KEY `ob_qc_review_actions_review_created_idx` (`onboarding_quality_review_id`,`created_at`),
  KEY `onboarding_quality_review_actions_action_index` (`action`),
  CONSTRAINT `ob_qc_actions_review_fk` FOREIGN KEY (`onboarding_quality_review_id`) REFERENCES `onboarding_quality_reviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `onboarding_quality_review_actions_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `onboarding_quality_review_actions`
--

LOCK TABLES `onboarding_quality_review_actions` WRITE;
/*!40000 ALTER TABLE `onboarding_quality_review_actions` DISABLE KEYS */;
/*!40000 ALTER TABLE `onboarding_quality_review_actions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `onboarding_quality_reviews`
--

DROP TABLE IF EXISTS `onboarding_quality_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_quality_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `user_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `completeness_score` tinyint unsigned NOT NULL DEFAULT '0',
  `auto_flags` json DEFAULT NULL,
  `manual_flag_overrides` json DEFAULT NULL,
  `monitoring_flagged` tinyint(1) NOT NULL DEFAULT '0',
  `monitoring_reason` text COLLATE utf8mb4_unicode_ci,
  `review_deadline_at` timestamp NULL DEFAULT NULL,
  `last_evaluated_at` timestamp NULL DEFAULT NULL,
  `status_changed_at` timestamp NULL DEFAULT NULL,
  `assigned_admin_id` bigint unsigned DEFAULT NULL,
  `last_action_admin_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onboarding_quality_reviews_user_id_unique` (`user_id`),
  KEY `onboarding_quality_reviews_assigned_admin_id_foreign` (`assigned_admin_id`),
  KEY `onboarding_quality_reviews_last_action_admin_id_foreign` (`last_action_admin_id`),
  KEY `onboarding_quality_reviews_user_type_index` (`user_type`),
  KEY `onboarding_quality_reviews_status_index` (`status`),
  KEY `onboarding_quality_reviews_monitoring_flagged_index` (`monitoring_flagged`),
  KEY `onboarding_quality_reviews_review_deadline_at_index` (`review_deadline_at`),
  CONSTRAINT `onboarding_quality_reviews_assigned_admin_id_foreign` FOREIGN KEY (`assigned_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `onboarding_quality_reviews_last_action_admin_id_foreign` FOREIGN KEY (`last_action_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `onboarding_quality_reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `onboarding_quality_reviews`
--

LOCK TABLES `onboarding_quality_reviews` WRITE;
/*!40000 ALTER TABLE `onboarding_quality_reviews` DISABLE KEYS */;
INSERT INTO `onboarding_quality_reviews` VALUES (1,1,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:44',NULL,'2026-05-31 19:37:46',NULL,NULL,'2026-05-31 19:37:46','2026-05-31 19:37:46'),(2,2,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:46',NULL,'2026-05-31 19:37:47',NULL,NULL,'2026-05-31 19:37:47','2026-05-31 19:37:47'),(3,3,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:47',NULL,'2026-05-31 19:37:47',NULL,NULL,'2026-05-31 19:37:47','2026-05-31 19:37:47'),(4,4,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:47',NULL,'2026-05-31 19:37:48',NULL,NULL,'2026-05-31 19:37:48','2026-05-31 19:37:48'),(5,5,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:48',NULL,'2026-05-31 19:37:49',NULL,NULL,'2026-05-31 19:37:49','2026-05-31 19:37:49'),(6,6,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:49',NULL,'2026-05-31 19:37:50',NULL,NULL,'2026-05-31 19:37:50','2026-05-31 19:37:50'),(7,7,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:50',NULL,'2026-05-31 19:37:51',NULL,NULL,'2026-05-31 19:37:51','2026-05-31 19:37:51'),(8,8,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:51',NULL,'2026-05-31 19:37:51',NULL,NULL,'2026-05-31 19:37:51','2026-05-31 19:37:51'),(9,9,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:51',NULL,'2026-05-31 19:37:52',NULL,NULL,'2026-05-31 19:37:52','2026-05-31 19:37:52'),(10,10,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:52',NULL,'2026-05-31 19:37:52',NULL,NULL,'2026-05-31 19:37:52','2026-05-31 19:37:52'),(11,11,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:52',NULL,'2026-05-31 19:37:53',NULL,NULL,'2026-05-31 19:37:53','2026-05-31 19:37:53'),(12,12,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:53',NULL,'2026-05-31 19:37:53',NULL,NULL,'2026-05-31 19:37:53','2026-05-31 19:37:53'),(13,13,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:53',NULL,'2026-05-31 19:37:55',NULL,NULL,'2026-05-31 19:37:55','2026-05-31 19:37:55'),(14,14,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:55',NULL,'2026-05-31 19:37:56',NULL,NULL,'2026-05-31 19:37:56','2026-05-31 19:37:56'),(15,15,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:56',NULL,'2026-05-31 19:37:57',NULL,NULL,'2026-05-31 19:37:57','2026-05-31 19:37:57'),(16,16,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:57',NULL,'2026-05-31 19:37:58',NULL,NULL,'2026-05-31 19:37:58','2026-05-31 19:37:58'),(17,17,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:58',NULL,'2026-05-31 19:37:59',NULL,NULL,'2026-05-31 19:37:59','2026-05-31 19:37:59'),(18,18,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:59',NULL,'2026-05-31 19:37:59',NULL,NULL,'2026-05-31 19:37:59','2026-05-31 19:37:59'),(19,19,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:37:59',NULL,'2026-05-31 19:38:00',NULL,NULL,'2026-05-31 19:38:00','2026-05-31 19:38:00'),(20,20,'freelancer','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:38:00',NULL,'2026-05-31 19:38:01',NULL,NULL,'2026-05-31 19:38:01','2026-05-31 19:38:01'),(21,21,'client','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:38:01',NULL,'2026-05-31 19:38:03',NULL,NULL,'2026-05-31 19:38:03','2026-05-31 19:38:03'),(22,22,'client','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:38:03',NULL,'2026-05-31 19:38:05',NULL,NULL,'2026-05-31 19:38:05','2026-05-31 19:38:05'),(23,23,'client','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:38:05',NULL,'2026-05-31 19:38:07',NULL,NULL,'2026-05-31 19:38:07','2026-05-31 19:38:07'),(24,24,'client','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:38:07',NULL,'2026-05-31 19:38:09',NULL,NULL,'2026-05-31 19:38:09','2026-05-31 19:38:09'),(25,25,'client','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:38:09',NULL,'2026-05-31 19:38:11',NULL,NULL,'2026-05-31 19:38:11','2026-05-31 19:38:11'),(26,26,'client','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:38:11',NULL,'2026-05-31 19:38:13',NULL,NULL,'2026-05-31 19:38:13','2026-05-31 19:38:13'),(27,27,'client','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:38:13',NULL,'2026-05-31 19:38:13',NULL,NULL,'2026-05-31 19:38:13','2026-05-31 19:38:13'),(28,28,'client','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:38:13',NULL,'2026-05-31 19:38:14',NULL,NULL,'2026-05-31 19:38:14','2026-05-31 19:38:14'),(29,29,'client','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:38:14',NULL,'2026-05-31 19:38:15',NULL,NULL,'2026-05-31 19:38:15','2026-05-31 19:38:15'),(30,30,'client','pending',0,NULL,NULL,0,NULL,'2026-06-02 19:38:15',NULL,'2026-05-31 19:38:15',NULL,NULL,'2026-05-31 19:38:15','2026-05-31 19:38:15');
/*!40000 ALTER TABLE `onboarding_quality_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_escrows`
--

DROP TABLE IF EXISTS `payment_escrows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_escrows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quest_id` bigint unsigned NOT NULL,
  `quest_offer_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned NOT NULL,
  `freelancer_id` bigint unsigned NOT NULL,
  `amount_minor` bigint unsigned NOT NULL,
  `fee_minor` bigint unsigned NOT NULL DEFAULT '0',
  `released_minor` bigint unsigned NOT NULL DEFAULT '0',
  `refunded_minor` bigint unsigned NOT NULL DEFAULT '0',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NGN',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paystack_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paystack_access_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `funded_at` timestamp NULL DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_escrows_uuid_unique` (`uuid`),
  UNIQUE KEY `payment_escrows_reference_unique` (`reference`),
  UNIQUE KEY `payment_escrows_quest_id_unique` (`quest_id`),
  UNIQUE KEY `payment_escrows_paystack_reference_unique` (`paystack_reference`),
  KEY `payment_escrows_quest_offer_id_foreign` (`quest_offer_id`),
  KEY `payment_escrows_client_id_foreign` (`client_id`),
  KEY `payment_escrows_freelancer_id_foreign` (`freelancer_id`),
  KEY `payment_escrows_status_index` (`status`),
  KEY `payment_escrows_funded_at_index` (`funded_at`),
  CONSTRAINT `payment_escrows_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_escrows_freelancer_id_foreign` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_escrows_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_escrows_quest_offer_id_foreign` FOREIGN KEY (`quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_escrows`
--

LOCK TABLES `payment_escrows` WRITE;
/*!40000 ALTER TABLE `payment_escrows` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_escrows` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_review_flags`
--

DROP TABLE IF EXISTS `payment_review_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_review_flags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `anomaly_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `anomaly_fingerprint` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_escrow_id` bigint unsigned DEFAULT NULL,
  `quest_id` bigint unsigned DEFAULT NULL,
  `wallet_transaction_id` bigint unsigned DEFAULT NULL,
  `transaction_reference` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signal_payload` json NOT NULL,
  `staff_admin_id` bigint unsigned NOT NULL,
  `concern_note` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resolution_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `resolution_note` text COLLATE utf8mb4_unicode_ci,
  `resolved_by_admin_id` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_review_flags_payment_escrow_id_foreign` (`payment_escrow_id`),
  KEY `payment_review_flags_quest_id_foreign` (`quest_id`),
  KEY `payment_review_flags_wallet_transaction_id_foreign` (`wallet_transaction_id`),
  KEY `payment_review_flags_staff_admin_id_foreign` (`staff_admin_id`),
  KEY `payment_review_flags_resolved_by_admin_id_foreign` (`resolved_by_admin_id`),
  KEY `payment_review_flags_resolution_status_created_at_index` (`resolution_status`,`created_at`),
  KEY `payment_review_flags_anomaly_type_index` (`anomaly_type`),
  KEY `payment_review_flags_severity_index` (`severity`),
  KEY `payment_review_flags_anomaly_fingerprint_index` (`anomaly_fingerprint`),
  KEY `payment_review_flags_transaction_reference_index` (`transaction_reference`),
  KEY `payment_review_flags_resolution_status_index` (`resolution_status`),
  KEY `payment_review_flags_resolved_at_index` (`resolved_at`),
  CONSTRAINT `payment_review_flags_payment_escrow_id_foreign` FOREIGN KEY (`payment_escrow_id`) REFERENCES `payment_escrows` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_review_flags_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_review_flags_resolved_by_admin_id_foreign` FOREIGN KEY (`resolved_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_review_flags_staff_admin_id_foreign` FOREIGN KEY (`staff_admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_review_flags_wallet_transaction_id_foreign` FOREIGN KEY (`wallet_transaction_id`) REFERENCES `wallet_transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_review_flags`
--

LOCK TABLES `payment_review_flags` WRITE;
/*!40000 ALTER TABLE `payment_review_flags` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_review_flags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paystack_webhook_events`
--

DROP TABLE IF EXISTS `paystack_webhook_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paystack_webhook_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json NOT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `processing_result` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processing_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `paystack_webhook_events_event_id_unique` (`event_id`),
  KEY `paystack_webhook_events_event_type_index` (`event_type`),
  KEY `paystack_webhook_events_reference_index` (`reference`),
  KEY `paystack_webhook_events_processed_at_index` (`processed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paystack_webhook_events`
--

LOCK TABLES `paystack_webhook_events` WRITE;
/*!40000 ALTER TABLE `paystack_webhook_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `paystack_webhook_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `platform_sla_clocks`
--

DROP TABLE IF EXISTS `platform_sla_clocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_sla_clocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sla_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `assigned_admin_id` bigint unsigned DEFAULT NULL,
  `triggered_by_user_id` bigint unsigned DEFAULT NULL,
  `triggered_at` timestamp NOT NULL,
  `due_at` timestamp NOT NULL,
  `breached_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `escalated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform_sla_clocks_uuid_unique` (`uuid`),
  KEY `platform_sla_clocks_triggered_by_user_id_foreign` (`triggered_by_user_id`),
  KEY `platform_sla_clocks_sla_key_status_index` (`sla_key`,`status`),
  KEY `platform_sla_clocks_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `platform_sla_clocks_due_at_status_index` (`due_at`,`status`),
  KEY `platform_sla_clocks_assigned_admin_id_status_index` (`assigned_admin_id`,`status`),
  CONSTRAINT `platform_sla_clocks_assigned_admin_id_foreign` FOREIGN KEY (`assigned_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `platform_sla_clocks_triggered_by_user_id_foreign` FOREIGN KEY (`triggered_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `platform_sla_clocks`
--

LOCK TABLES `platform_sla_clocks` WRITE;
/*!40000 ALTER TABLE `platform_sla_clocks` DISABLE KEYS */;
/*!40000 ALTER TABLE `platform_sla_clocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portfolio_favorites`
--

DROP TABLE IF EXISTS `portfolio_favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portfolio_favorites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `portfolio_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `portfolio_favorites_portfolio_id_user_id_unique` (`portfolio_id`,`user_id`),
  KEY `portfolio_favorites_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `portfolio_favorites_portfolio_id_foreign` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `portfolio_favorites_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portfolio_favorites`
--

LOCK TABLES `portfolio_favorites` WRITE;
/*!40000 ALTER TABLE `portfolio_favorites` DISABLE KEYS */;
/*!40000 ALTER TABLE `portfolio_favorites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portfolio_files`
--

DROP TABLE IF EXISTS `portfolio_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portfolio_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `portfolio_id` bigint unsigned NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` bigint unsigned NOT NULL DEFAULT '0',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `portfolio_files_portfolio_id_sort_order_index` (`portfolio_id`,`sort_order`),
  CONSTRAINT `portfolio_files_portfolio_id_foreign` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portfolio_files`
--

LOCK TABLES `portfolio_files` WRITE;
/*!40000 ALTER TABLE `portfolio_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `portfolio_files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portfolios`
--

DROP TABLE IF EXISTS `portfolios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portfolios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `quest_id` bigint unsigned DEFAULT NULL,
  `category_id` bigint unsigned NOT NULL,
  `subcategory_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `project_cost_minor` bigint unsigned DEFAULT NULL,
  `cover_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `admin_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `published_at` timestamp NULL DEFAULT NULL,
  `favorites_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `portfolios_slug_unique` (`slug`),
  KEY `portfolios_quest_id_foreign` (`quest_id`),
  KEY `portfolios_category_id_foreign` (`category_id`),
  KEY `portfolios_subcategory_id_foreign` (`subcategory_id`),
  KEY `portfolios_user_id_status_index` (`user_id`,`status`),
  KEY `portfolios_status_published_at_index` (`status`,`published_at`),
  KEY `portfolios_status_index` (`status`),
  KEY `portfolios_admin_hidden_index` (`admin_hidden`),
  CONSTRAINT `portfolios_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `quest_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `portfolios_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `portfolios_subcategory_id_foreign` FOREIGN KEY (`subcategory_id`) REFERENCES `quest_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `portfolios_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portfolios`
--

LOCK TABLES `portfolios` WRITE;
/*!40000 ALTER TABLE `portfolios` DISABLE KEYS */;
/*!40000 ALTER TABLE `portfolios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotion_badge_user`
--

DROP TABLE IF EXISTS `promotion_badge_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotion_badge_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `promotion_badge_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `awarded_by_admin_id` bigint unsigned DEFAULT NULL,
  `justification` text COLLATE utf8mb4_unicode_ci,
  `awarded_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoked_by_admin_id` bigint unsigned DEFAULT NULL,
  `revocation_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `promotion_badge_user_promotion_badge_id_user_id_unique` (`promotion_badge_id`,`user_id`),
  KEY `promotion_badge_user_user_id_foreign` (`user_id`),
  KEY `promotion_badge_user_awarded_by_admin_id_foreign` (`awarded_by_admin_id`),
  KEY `promotion_badge_user_revoked_by_admin_id_foreign` (`revoked_by_admin_id`),
  CONSTRAINT `promotion_badge_user_awarded_by_admin_id_foreign` FOREIGN KEY (`awarded_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_badge_user_promotion_badge_id_foreign` FOREIGN KEY (`promotion_badge_id`) REFERENCES `promotion_badges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_badge_user_revoked_by_admin_id_foreign` FOREIGN KEY (`revoked_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_badge_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotion_badge_user`
--

LOCK TABLES `promotion_badge_user` WRITE;
/*!40000 ALTER TABLE `promotion_badge_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_badge_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotion_badges`
--

DROP TABLE IF EXISTS `promotion_badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotion_badges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `criteria` json DEFAULT NULL,
  `is_automatic` tinyint(1) NOT NULL DEFAULT '0',
  `requires_manual_review` tinyint(1) NOT NULL DEFAULT '0',
  `is_public` tinyint(1) NOT NULL DEFAULT '1',
  `is_time_limited` tinyint(1) NOT NULL DEFAULT '0',
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `promotion_badges_slug_unique` (`slug`),
  KEY `promotion_badges_is_automatic_index` (`is_automatic`),
  KEY `promotion_badges_display_order_index` (`display_order`),
  KEY `promotion_badges_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotion_badges`
--

LOCK TABLES `promotion_badges` WRITE;
/*!40000 ALTER TABLE `promotion_badges` DISABLE KEYS */;
INSERT INTO `promotion_badges` VALUES (1,'Top Rated','top-rated','star','4.8+ rating across 10 completed contracts in 90 days, with no active disputes.','{\"standard\": \"4.8+ rating across 10 completed contracts in 90 days, with no active disputes.\"}',1,0,1,0,1,'active','2026-05-31 18:24:44','2026-05-31 18:24:44'),(2,'Rising Talent','rising-talent','sparkles','Joined within 6 months, completed 3 contracts, 5-star average, zero disputes.','{\"standard\": \"Joined within 6 months, completed 3 contracts, 5-star average, zero disputes.\"}',1,0,1,0,2,'active','2026-05-31 18:24:44','2026-05-31 18:24:44'),(3,'Quest Champion','quest-champion','trophy','Client with 10+ quests and 4.5+ freelancer satisfaction.','{\"standard\": \"Client with 10+ quests and 4.5+ freelancer satisfaction.\"}',1,0,1,0,3,'active','2026-05-31 18:24:44','2026-05-31 18:24:44'),(4,'Verified Pro','verified-pro','shield-check','Full KYC verification achieved.','{\"standard\": \"Full KYC verification achieved.\"}',1,0,1,0,4,'active','2026-05-31 18:24:44','2026-05-31 18:24:44'),(5,'Verified Business','verified-business','building-office','CAC business verification confirmed.','{\"standard\": \"CAC business verification confirmed.\"}',1,0,1,0,5,'active','2026-05-31 18:24:44','2026-05-31 18:24:44'),(6,'Fast Responder','fast-responder','bolt','Average proposal response under 4 hours across 20 proposals.','{\"standard\": \"Average proposal response under 4 hours across 20 proposals.\"}',1,0,1,0,6,'active','2026-05-31 18:24:44','2026-05-31 18:24:44'),(7,'Long-term Partner','long-term-partner','heart','Active for 12+ months with trust score above 70.','{\"standard\": \"Active for 12+ months with trust score above 70.\"}',1,0,1,0,7,'active','2026-05-31 18:24:44','2026-05-31 18:24:44');
/*!40000 ALTER TABLE `promotion_badges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotion_coupon_fraud_flags`
--

DROP TABLE IF EXISTS `promotion_coupon_fraud_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotion_coupon_fraud_flags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `promotion_coupon_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `reason` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `evidence` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `promotion_coupon_fraud_flags_promotion_coupon_id_foreign` (`promotion_coupon_id`),
  KEY `promotion_coupon_fraud_flags_user_id_foreign` (`user_id`),
  KEY `promotion_coupon_fraud_flags_reason_index` (`reason`),
  KEY `promotion_coupon_fraud_flags_status_index` (`status`),
  CONSTRAINT `promotion_coupon_fraud_flags_promotion_coupon_id_foreign` FOREIGN KEY (`promotion_coupon_id`) REFERENCES `promotion_coupons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_coupon_fraud_flags_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotion_coupon_fraud_flags`
--

LOCK TABLES `promotion_coupon_fraud_flags` WRITE;
/*!40000 ALTER TABLE `promotion_coupon_fraud_flags` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_coupon_fraud_flags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotion_coupon_redemptions`
--

DROP TABLE IF EXISTS `promotion_coupon_redemptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotion_coupon_redemptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `promotion_coupon_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `redeemable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redeemable_id` bigint unsigned DEFAULT NULL,
  `payment_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_amount_minor` int unsigned NOT NULL,
  `discount_amount_minor` int unsigned NOT NULL,
  `net_amount_minor` int unsigned NOT NULL,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_fingerprint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `promotion_coupon_redemptions_promotion_coupon_id_foreign` (`promotion_coupon_id`),
  KEY `promotion_coupon_redemptions_user_id_foreign` (`user_id`),
  KEY `promotion_coupon_redemptions_redeemable_type_redeemable_id_index` (`redeemable_type`,`redeemable_id`),
  KEY `promotion_coupon_redemptions_payment_type_index` (`payment_type`),
  CONSTRAINT `promotion_coupon_redemptions_promotion_coupon_id_foreign` FOREIGN KEY (`promotion_coupon_id`) REFERENCES `promotion_coupons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_coupon_redemptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotion_coupon_redemptions`
--

LOCK TABLES `promotion_coupon_redemptions` WRITE;
/*!40000 ALTER TABLE `promotion_coupon_redemptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_coupon_redemptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotion_coupons`
--

DROP TABLE IF EXISTS `promotion_coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotion_coupons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `discount_type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value_minor` int unsigned NOT NULL DEFAULT '0',
  `discount_percent` tinyint unsigned DEFAULT NULL,
  `max_discount_minor` int unsigned DEFAULT NULL,
  `applies_to` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `quest_category_id` bigint unsigned DEFAULT NULL,
  `eligibility` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `eligible_user_ids` json DEFAULT NULL,
  `usage_limit_total` int unsigned DEFAULT NULL,
  `usage_limit_per_user` int unsigned DEFAULT NULL,
  `usage_count` int unsigned NOT NULL DEFAULT '0',
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `minimum_transaction_minor` int unsigned NOT NULL DEFAULT '0',
  `created_by_admin_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `promotion_coupons_code_unique` (`code`),
  KEY `promotion_coupons_quest_category_id_foreign` (`quest_category_id`),
  KEY `promotion_coupons_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `promotion_coupons_status_index` (`status`),
  KEY `promotion_coupons_applies_to_index` (`applies_to`),
  KEY `promotion_coupons_eligibility_index` (`eligibility`),
  KEY `promotion_coupons_starts_at_index` (`starts_at`),
  KEY `promotion_coupons_ends_at_index` (`ends_at`),
  CONSTRAINT `promotion_coupons_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_coupons_quest_category_id_foreign` FOREIGN KEY (`quest_category_id`) REFERENCES `quest_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotion_coupons`
--

LOCK TABLES `promotion_coupons` WRITE;
/*!40000 ALTER TABLE `promotion_coupons` DISABLE KEYS */;
/*!40000 ALTER TABLE `promotion_coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotion_settings`
--

DROP TABLE IF EXISTS `promotion_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotion_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `promotion_settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotion_settings`
--

LOCK TABLES `promotion_settings` WRITE;
/*!40000 ALTER TABLE `promotion_settings` DISABLE KEYS */;
INSERT INTO `promotion_settings` VALUES (1,'featured_tiers','{\"elite\": {\"label\": \"Elite Boost\", \"durations\": [14, 30], \"placements\": [\"category_top\", \"homepage_carousel\", \"push_notification\", \"weekly_digest\", \"social_post\"], \"prices_minor\": {\"14\": 3500000, \"30\": 6500000}}, \"premium\": {\"label\": \"Premium Boost\", \"durations\": [7, 14], \"placements\": [\"category_top\", \"homepage_carousel\", \"push_notification\"], \"prices_minor\": {\"7\": 1200000, \"14\": 2200000}}, \"standard\": {\"label\": \"Standard Boost\", \"durations\": [3, 7], \"placements\": [\"category_top\", \"featured_badge\"], \"prices_minor\": {\"3\": 250000, \"7\": 500000}}}','2026-05-31 18:24:44','2026-05-31 18:24:44'),(2,'referral_program','{\"reward_type\": \"wallet_credit\", \"qualifying_event\": \"first_transaction\", \"reward_expiry_days\": 90, \"client_reward_minor\": 250000, \"freelancer_reward_minor\": 150000}','2026-05-31 18:24:44','2026-05-31 18:24:44');
/*!40000 ALTER TABLE `promotion_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_behaviour_logs`
--

DROP TABLE IF EXISTS `proposal_behaviour_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_behaviour_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned DEFAULT NULL,
  `quest_offer_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `event_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta` json DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proposal_behaviour_logs_quest_offer_id_foreign` (`quest_offer_id`),
  KEY `proposal_behaviour_logs_user_id_event_type_index` (`user_id`,`event_type`),
  KEY `proposal_behaviour_logs_quest_id_event_type_index` (`quest_id`,`event_type`),
  CONSTRAINT `proposal_behaviour_logs_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `proposal_behaviour_logs_quest_offer_id_foreign` FOREIGN KEY (`quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `proposal_behaviour_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_behaviour_logs`
--

LOCK TABLES `proposal_behaviour_logs` WRITE;
/*!40000 ALTER TABLE `proposal_behaviour_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `proposal_behaviour_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_clarification_messages`
--

DROP TABLE IF EXISTS `proposal_clarification_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_clarification_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `thread_id` bigint unsigned NOT NULL,
  `author_user_id` bigint unsigned NOT NULL,
  `role` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prompt_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prompt_category` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_original` text COLLATE utf8mb4_unicode_ci,
  `is_redacted` tinyint(1) NOT NULL DEFAULT '0',
  `redaction_label` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proposal_clarification_messages_author_user_id_foreign` (`author_user_id`),
  KEY `proposal_clarification_messages_thread_id_created_at_index` (`thread_id`,`created_at`),
  CONSTRAINT `proposal_clarification_messages_author_user_id_foreign` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposal_clarification_messages_thread_id_foreign` FOREIGN KEY (`thread_id`) REFERENCES `proposal_clarification_threads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_clarification_messages`
--

LOCK TABLES `proposal_clarification_messages` WRITE;
/*!40000 ALTER TABLE `proposal_clarification_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `proposal_clarification_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_clarification_threads`
--

DROP TABLE IF EXISTS `proposal_clarification_threads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_clarification_threads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `quest_offer_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `freelancer_id` bigint unsigned NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `questions_asked` tinyint unsigned NOT NULL DEFAULT '0',
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_clarification_threads_quest_offer_id_unique` (`quest_offer_id`),
  KEY `proposal_clarification_threads_client_id_foreign` (`client_id`),
  KEY `proposal_clarification_threads_freelancer_id_foreign` (`freelancer_id`),
  KEY `proposal_clarification_threads_quest_id_status_index` (`quest_id`,`status`),
  CONSTRAINT `proposal_clarification_threads_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposal_clarification_threads_freelancer_id_foreign` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposal_clarification_threads_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposal_clarification_threads_quest_offer_id_foreign` FOREIGN KEY (`quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_clarification_threads`
--

LOCK TABLES `proposal_clarification_threads` WRITE;
/*!40000 ALTER TABLE `proposal_clarification_threads` DISABLE KEYS */;
/*!40000 ALTER TABLE `proposal_clarification_threads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_quota_audit_logs`
--

DROP TABLE IF EXISTS `proposal_quota_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_quota_audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `freelancer_id` bigint unsigned NOT NULL,
  `month` char(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_tier` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proposals_used` smallint unsigned NOT NULL,
  `quota_limit` smallint unsigned DEFAULT NULL,
  `result` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quest_id` bigint unsigned DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proposal_quota_audit_logs_quest_id_foreign` (`quest_id`),
  KEY `pqa_freelancer_occurred_idx` (`freelancer_id`,`occurred_at`),
  CONSTRAINT `proposal_quota_audit_logs_freelancer_id_foreign` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposal_quota_audit_logs_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_quota_audit_logs`
--

LOCK TABLES `proposal_quota_audit_logs` WRITE;
/*!40000 ALTER TABLE `proposal_quota_audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `proposal_quota_audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_quota_usages`
--

DROP TABLE IF EXISTS `proposal_quota_usages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_quota_usages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `freelancer_id` bigint unsigned NOT NULL,
  `month` char(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proposals_count` smallint unsigned NOT NULL DEFAULT '0',
  `plan_tier` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pqu_freelancer_month_uq` (`freelancer_id`,`month`),
  CONSTRAINT `proposal_quota_usages_freelancer_id_foreign` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_quota_usages`
--

LOCK TABLES `proposal_quota_usages` WRITE;
/*!40000 ALTER TABLE `proposal_quota_usages` DISABLE KEYS */;
/*!40000 ALTER TABLE `proposal_quota_usages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_arbitration_agreements`
--

DROP TABLE IF EXISTS `quest_arbitration_agreements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_arbitration_agreements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `quest_offer_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `party` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agreed_at` timestamp NOT NULL,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quest_arbitration_unique_party` (`quest_id`,`quest_offer_id`,`user_id`,`party`),
  KEY `quest_arbitration_agreements_quest_offer_id_foreign` (`quest_offer_id`),
  KEY `quest_arbitration_agreements_user_id_foreign` (`user_id`),
  KEY `quest_arbitration_agreements_party_index` (`party`),
  KEY `quest_arbitration_agreements_agreed_at_index` (`agreed_at`),
  CONSTRAINT `quest_arbitration_agreements_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_arbitration_agreements_quest_offer_id_foreign` FOREIGN KEY (`quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quest_arbitration_agreements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_arbitration_agreements`
--

LOCK TABLES `quest_arbitration_agreements` WRITE;
/*!40000 ALTER TABLE `quest_arbitration_agreements` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_arbitration_agreements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_bookmarks`
--

DROP TABLE IF EXISTS `quest_bookmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_bookmarks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quest_bookmarks_quest_id_user_id_unique` (`quest_id`,`user_id`),
  KEY `quest_bookmarks_user_id_foreign` (`user_id`),
  CONSTRAINT `quest_bookmarks_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_bookmarks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_bookmarks`
--

LOCK TABLES `quest_bookmarks` WRITE;
/*!40000 ALTER TABLE `quest_bookmarks` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_bookmarks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_boost_audit_logs`
--

DROP TABLE IF EXISTS `quest_boost_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_boost_audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_boost_id` bigint unsigned NOT NULL,
  `action_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quest_boost_audit_logs_actor_user_id_foreign` (`actor_user_id`),
  KEY `qba_boost_occurred_idx` (`quest_boost_id`,`occurred_at`),
  CONSTRAINT `quest_boost_audit_logs_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quest_boost_audit_logs_quest_boost_id_foreign` FOREIGN KEY (`quest_boost_id`) REFERENCES `quest_boosts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_boost_audit_logs`
--

LOCK TABLES `quest_boost_audit_logs` WRITE;
/*!40000 ALTER TABLE `quest_boost_audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_boost_audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_boosts`
--

DROP TABLE IF EXISTS `quest_boosts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_boosts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quest_id` bigint unsigned NOT NULL,
  `quest_title_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `granted_by_admin_id` bigint unsigned NOT NULL,
  `tier` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `planned_cost_minor` bigint unsigned NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `starts_at` timestamp NOT NULL,
  `ends_at` timestamp NOT NULL,
  `grant_reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `granted_at` timestamp NOT NULL,
  `actual_ended_at` timestamp NULL DEFAULT NULL,
  `visibility_tier` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tier_1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quest_boosts_reference_unique` (`reference`),
  KEY `quest_boosts_client_id_foreign` (`client_id`),
  KEY `quest_boosts_granted_by_admin_id_foreign` (`granted_by_admin_id`),
  KEY `qb_status_ends_idx` (`status`,`ends_at`),
  KEY `qb_quest_status_idx` (`quest_id`,`status`),
  CONSTRAINT `quest_boosts_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_boosts_granted_by_admin_id_foreign` FOREIGN KEY (`granted_by_admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_boosts_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_boosts`
--

LOCK TABLES `quest_boosts` WRITE;
/*!40000 ALTER TABLE `quest_boosts` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_boosts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_categories`
--

DROP TABLE IF EXISTS `quest_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned DEFAULT NULL,
  `slug` varchar(96) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'briefcase',
  `icon_color` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#0f766e',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `previous_status` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `uses_fee_override` tinyint(1) NOT NULL DEFAULT '0',
  `client_fee_percent` decimal(5,2) DEFAULT NULL,
  `freelancer_fee_percent` decimal(5,2) DEFAULT NULL,
  `budget_guardrails_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `min_budget_minor` bigint unsigned DEFAULT NULL,
  `max_budget_minor` bigint unsigned DEFAULT NULL,
  `high_value_approval_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `high_value_threshold_minor` bigint unsigned DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quest_categories_sort_order_index` (`sort_order`),
  KEY `quest_categories_is_active_index` (`is_active`),
  KEY `quest_categories_created_by_foreign` (`created_by`),
  KEY `quest_categories_updated_by_foreign` (`updated_by`),
  KEY `quest_categories_status_index` (`status`),
  KEY `quest_categories_parent_sort_idx` (`parent_id`,`sort_order`),
  KEY `quest_categories_parent_slug_idx` (`parent_id`,`slug`),
  CONSTRAINT `quest_categories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quest_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `quest_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_categories_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_categories`
--

LOCK TABLES `quest_categories` WRITE;
/*!40000 ALTER TABLE `quest_categories` DISABLE KEYS */;
INSERT INTO `quest_categories` VALUES (1,NULL,'technology-software','Technology & Software',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(2,1,'web-frontend','Web development (front-end)',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(3,1,'web-backend','Web development (back-end)',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(4,1,'fullstack-development','Full-stack development',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(5,1,'mobile-apps','Mobile apps (iOS / Android)',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(6,1,'devops-cloud','DevOps & cloud (AWS, Azure, GCP)',NULL,'briefcase','#0f766e',4,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(7,1,'qa-automation','QA & test automation',NULL,'briefcase','#0f766e',5,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(8,1,'cybersecurity','Cybersecurity & audits',NULL,'briefcase','#0f766e',6,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(9,1,'data-engineering','Data engineering & ETL',NULL,'briefcase','#0f766e',7,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(10,1,'ml-ai','Machine learning & AI',NULL,'briefcase','#0f766e',8,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(11,1,'api-integration','API & systems integration',NULL,'briefcase','#0f766e',9,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(12,NULL,'design-creative','Design & Creative',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(13,12,'ui-ux-design','UI / UX design',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(14,12,'brand-identity','Brand & identity',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(15,12,'graphic-illustration','Graphic design & illustration',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(16,12,'motion-video','Motion & video editing',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(17,12,'3d-visualization','3D & product visualization',NULL,'briefcase','#0f766e',4,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(18,12,'packaging-print','Packaging & print design',NULL,'briefcase','#0f766e',5,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(19,NULL,'writing-content','Writing & Content',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(20,19,'copywriting','Copywriting & sales pages',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(21,19,'blog-editorial','Blog & editorial writing',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(22,19,'technical-writing','Technical writing',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(23,19,'translation','Translation & localization',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(24,19,'scriptwriting','Scriptwriting & storytelling',NULL,'briefcase','#0f766e',4,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(25,19,'seo-content','SEO content strategy',NULL,'briefcase','#0f766e',5,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(26,NULL,'marketing-growth','Marketing & Growth',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(27,26,'performance-ads','Performance ads (Meta, Google)',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(28,26,'social-media','Social media management',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(29,26,'email-marketing','Email & lifecycle marketing',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(30,26,'influencer-partnerships','Influencer & partnerships',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(31,26,'market-research','Market research & insights',NULL,'briefcase','#0f766e',4,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(32,26,'cro-funnels','CRO & funnel optimization',NULL,'briefcase','#0f766e',5,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(33,NULL,'business-operations','Business & Operations',NULL,'briefcase','#0f766e',4,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(34,33,'virtual-assistant','Virtual assistant & admin',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(35,33,'project-management','Project & program management',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(36,33,'business-strategy','Business analysis & strategy',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(37,33,'process-design','Process & workflow design',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(38,33,'hr-recruiting','HR & recruiting support',NULL,'briefcase','#0f766e',4,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(39,33,'customer-support','Customer support & success',NULL,'briefcase','#0f766e',5,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(40,NULL,'finance-accounting','Finance & Accounting',NULL,'briefcase','#0f766e',5,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(41,40,'bookkeeping','Bookkeeping',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(42,40,'tax-advisory','Tax preparation & advisory',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(43,40,'financial-modelling','Financial modelling',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(44,40,'payroll','Payroll processing',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(45,40,'audit-support','Audit support',NULL,'briefcase','#0f766e',4,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(46,NULL,'legal-compliance','Legal & Compliance',NULL,'briefcase','#0f766e',6,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(47,46,'contracts','Contract drafting & review',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(48,46,'corporate-advisory','Corporate & startup advisory',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(49,46,'ip-trademarks','IP & trademarks',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(50,46,'regulatory-compliance','Regulatory compliance',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(51,46,'dispute-mediation','Dispute & mediation support',NULL,'briefcase','#0f766e',4,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(52,NULL,'engineering-stem','Engineering & STEM',NULL,'briefcase','#0f766e',7,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(53,52,'civil-structural','Civil & structural (design support)',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(54,52,'electrical-electronics','Electrical & electronics',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(55,52,'mechanical-cad','Mechanical CAD / drafting',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(56,52,'surveying-gis','Surveying & GIS',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(57,52,'environmental','Environmental assessments',NULL,'briefcase','#0f766e',4,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(58,NULL,'trades-field','Trades & Field services',NULL,'briefcase','#0f766e',8,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(59,58,'electrical-install','Electrical installations',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(60,58,'plumbing-hvac','Plumbing & HVAC',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(61,58,'carpentry-interiors','Carpentry & interiors',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(62,58,'generator-power','Generator & power systems',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(63,58,'facility-maintenance','Facility maintenance',NULL,'briefcase','#0f766e',4,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(64,NULL,'education-training','Education & Training',NULL,'briefcase','#0f766e',9,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(65,64,'tutoring-stem','Tutoring (STEM)',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(66,64,'tutoring-languages','Tutoring (languages)',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(67,64,'corporate-training','Corporate training & L&D',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(68,64,'curriculum-design','Curriculum & course design',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(69,64,'exam-prep','Exam prep & certifications',NULL,'briefcase','#0f766e',4,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(70,NULL,'healthcare-wellness','Healthcare & Wellness',NULL,'briefcase','#0f766e',10,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(71,70,'telehealth-coord','Telehealth coordination',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(72,70,'medical-scribing','Medical scribing & notes',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(73,70,'nutrition-fitness','Nutrition & fitness coaching',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(74,70,'mental-health-support','Mental health support (non-clinical)',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(75,70,'health-informatics','Health informatics & records',NULL,'briefcase','#0f766e',4,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(76,NULL,'media-events','Media & Events',NULL,'briefcase','#0f766e',11,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(77,76,'photography','Photography',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(78,76,'videography-livestream','Videography & livestream',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(79,76,'podcast-production','Podcast production',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(80,76,'event-planning','Event planning & production',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(81,76,'pr-comms','PR & communications',NULL,'briefcase','#0f766e',4,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(82,NULL,'sales-bd','Sales & Business development',NULL,'briefcase','#0f766e',12,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(83,82,'lead-generation','Lead generation & outbound',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(84,82,'inside-sales','Inside sales & closing',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(85,82,'channel-partnerships','Channel & partnerships',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(86,82,'crm-setup','CRM setup & hygiene',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(87,NULL,'ecommerce-retail','E-commerce & retail ops',NULL,'briefcase','#0f766e',13,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(88,87,'store-setup','Store setup (Shopify, Woo)',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(89,87,'catalog-inventory','Catalog & inventory ops',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(90,87,'fulfillment','Fulfillment coordination',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(91,87,'marketplace-mgmt','Marketplace management',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(92,NULL,'agriculture-supply','Agriculture & supply chain',NULL,'briefcase','#0f766e',14,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(93,92,'farm-advisory','Farm advisory & agronomy',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(94,92,'agri-data-iot','Agri data & IoT',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(95,92,'logistics-cold-chain','Logistics & cold chain',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(96,92,'import-export-docs','Import/export documentation',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(97,NULL,'real-estate','Real estate & property',NULL,'briefcase','#0f766e',15,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(98,97,'property-research','Property research & listings',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(99,97,'estate-management','Facility & estate management',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(100,97,'architecture-viz','Architecture visualization',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(101,NULL,'nonprofit-community','Non-profit & community',NULL,'briefcase','#0f766e',16,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(102,101,'grant-writing','Grant writing',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(103,101,'community-outreach','Community programs & outreach',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(104,101,'monitoring-evaluation','Monitoring & evaluation',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(105,NULL,'gaming-interactive','Gaming & interactive media',NULL,'briefcase','#0f766e',17,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(106,105,'game-design-economy','Game design & economy docs',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(107,105,'unity-unreal-support','Unity / Unreal implementation support',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(108,105,'liveops-community','Live ops & community moderation',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(109,105,'game-narrative','Narrative & quest writing',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(110,NULL,'research-decision','Research & decision support',NULL,'briefcase','#0f766e',18,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(111,110,'survey-enumerator','Survey design & enumerator briefings',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(112,110,'policy-memos','Policy memos & stakeholder packs',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(113,110,'insight-decks','Dashboards & insight decks',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(114,110,'competitive-intel','Competitive intelligence scans',NULL,'briefcase','#0f766e',3,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(115,NULL,'other-multidisciplinary','Other / multi-disciplinary',NULL,'briefcase','#0f766e',19,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(116,115,'general-consulting','General consulting',NULL,'briefcase','#0f766e',0,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(117,115,'research-desk','Research & desk studies',NULL,'briefcase','#0f766e',1,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(118,115,'misc-gigs','Miscellaneous gigs',NULL,'briefcase','#0f766e',2,'active',NULL,1,0,NULL,NULL,0,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21');
/*!40000 ALTER TABLE `quest_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_completion_events`
--

DROP TABLE IF EXISTS `quest_completion_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_completion_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `quest_offer_id` bigint unsigned DEFAULT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `event_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `occurred_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quest_completion_events_quest_offer_id_foreign` (`quest_offer_id`),
  KEY `quest_completion_events_actor_user_id_foreign` (`actor_user_id`),
  KEY `quest_completion_events_quest_id_occurred_at_index` (`quest_id`,`occurred_at`),
  KEY `quest_completion_events_event_type_occurred_at_index` (`event_type`,`occurred_at`),
  KEY `quest_completion_events_event_type_index` (`event_type`),
  KEY `quest_completion_events_occurred_at_index` (`occurred_at`),
  CONSTRAINT `quest_completion_events_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quest_completion_events_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_completion_events_quest_offer_id_foreign` FOREIGN KEY (`quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_completion_events`
--

LOCK TABLES `quest_completion_events` WRITE;
/*!40000 ALTER TABLE `quest_completion_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_completion_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_contract_amendments`
--

DROP TABLE IF EXISTS `quest_contract_amendments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_contract_amendments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_contract_id` bigint unsigned NOT NULL,
  `amendment_number` smallint unsigned NOT NULL,
  `requested_by_user_id` bigint unsigned NOT NULL,
  `amendment_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `response_note` text COLLATE utf8mb4_unicode_ci,
  `responded_by_user_id` bigint unsigned DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `applied_terms_delta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quest_contract_amendments_quest_contract_id_foreign` (`quest_contract_id`),
  KEY `quest_contract_amendments_requested_by_user_id_foreign` (`requested_by_user_id`),
  KEY `quest_contract_amendments_responded_by_user_id_foreign` (`responded_by_user_id`),
  CONSTRAINT `quest_contract_amendments_quest_contract_id_foreign` FOREIGN KEY (`quest_contract_id`) REFERENCES `quest_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_contract_amendments_requested_by_user_id_foreign` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_contract_amendments_responded_by_user_id_foreign` FOREIGN KEY (`responded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_contract_amendments`
--

LOCK TABLES `quest_contract_amendments` WRITE;
/*!40000 ALTER TABLE `quest_contract_amendments` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_contract_amendments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_contract_deliverables`
--

DROP TABLE IF EXISTS `quest_contract_deliverables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_contract_deliverables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_contract_id` bigint unsigned NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT '0',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quest_contract_deliverables_quest_contract_id_foreign` (`quest_contract_id`),
  CONSTRAINT `quest_contract_deliverables_quest_contract_id_foreign` FOREIGN KEY (`quest_contract_id`) REFERENCES `quest_contracts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_contract_deliverables`
--

LOCK TABLES `quest_contract_deliverables` WRITE;
/*!40000 ALTER TABLE `quest_contract_deliverables` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_contract_deliverables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_contract_delivery_extensions`
--

DROP TABLE IF EXISTS `quest_contract_delivery_extensions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_contract_delivery_extensions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_contract_id` bigint unsigned NOT NULL,
  `extension_number` smallint unsigned NOT NULL,
  `requested_by_user_id` bigint unsigned NOT NULL,
  `reason_category` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `explanation` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_delivery_date` date NOT NULL,
  `proposed_delivery_date` date NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_client',
  `progress_note` text COLLATE utf8mb4_unicode_ci,
  `progress_attachments` json DEFAULT NULL,
  `scope_change_message_id` bigint unsigned DEFAULT NULL,
  `client_response_deadline_at` timestamp NOT NULL,
  `counter_proposed_date` date DEFAULT NULL,
  `counter_proposed_at` timestamp NULL DEFAULT NULL,
  `counter_response_deadline_at` timestamp NULL DEFAULT NULL,
  `decline_reason` text COLLATE utf8mb4_unicode_ci,
  `resolution` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `applied_delivery_date` date DEFAULT NULL,
  `quest_contract_amendment_id` bigint unsigned DEFAULT NULL,
  `resolved_by_user_id` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `client_attributed_delay` tinyint(1) NOT NULL DEFAULT '0',
  `admin_monitoring_flagged` tinyint(1) NOT NULL DEFAULT '0',
  `submitted_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `qcd_ext_contract_num_uq` (`quest_contract_id`,`extension_number`),
  KEY `quest_contract_delivery_extensions_requested_by_user_id_foreign` (`requested_by_user_id`),
  KEY `qcd_ext_scope_msg_fk` (`scope_change_message_id`),
  KEY `qcd_ext_amendment_fk` (`quest_contract_amendment_id`),
  KEY `quest_contract_delivery_extensions_resolved_by_user_id_foreign` (`resolved_by_user_id`),
  CONSTRAINT `qcd_ext_amendment_fk` FOREIGN KEY (`quest_contract_amendment_id`) REFERENCES `quest_contract_amendments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `qcd_ext_scope_msg_fk` FOREIGN KEY (`scope_change_message_id`) REFERENCES `quest_conversation_messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quest_contract_delivery_extensions_quest_contract_id_foreign` FOREIGN KEY (`quest_contract_id`) REFERENCES `quest_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_contract_delivery_extensions_requested_by_user_id_foreign` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_contract_delivery_extensions_resolved_by_user_id_foreign` FOREIGN KEY (`resolved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_contract_delivery_extensions`
--

LOCK TABLES `quest_contract_delivery_extensions` WRITE;
/*!40000 ALTER TABLE `quest_contract_delivery_extensions` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_contract_delivery_extensions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_contract_events`
--

DROP TABLE IF EXISTS `quest_contract_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_contract_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_contract_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `event_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `properties` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `quest_contract_events_quest_contract_id_foreign` (`quest_contract_id`),
  KEY `quest_contract_events_user_id_foreign` (`user_id`),
  KEY `quest_contract_events_event_type_index` (`event_type`),
  CONSTRAINT `quest_contract_events_quest_contract_id_foreign` FOREIGN KEY (`quest_contract_id`) REFERENCES `quest_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_contract_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_contract_events`
--

LOCK TABLES `quest_contract_events` WRITE;
/*!40000 ALTER TABLE `quest_contract_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_contract_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_contract_milestones`
--

DROP TABLE IF EXISTS `quest_contract_milestones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_contract_milestones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_contract_id` bigint unsigned NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deliverable_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value_minor` bigint unsigned NOT NULL DEFAULT '0',
  `deadline_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quest_contract_milestones_quest_contract_id_foreign` (`quest_contract_id`),
  CONSTRAINT `quest_contract_milestones_quest_contract_id_foreign` FOREIGN KEY (`quest_contract_id`) REFERENCES `quest_contracts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_contract_milestones`
--

LOCK TABLES `quest_contract_milestones` WRITE;
/*!40000 ALTER TABLE `quest_contract_milestones` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_contract_milestones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_contracts`
--

DROP TABLE IF EXISTS `quest_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_contracts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quest_id` bigint unsigned NOT NULL,
  `quest_offer_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `freelancer_id` bigint unsigned NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_escrow',
  `generated_at` timestamp NOT NULL,
  `escrow_expires_at` timestamp NULL DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  `escrow_funding_reference` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `escrow_funded_at` timestamp NULL DEFAULT NULL,
  `contract_start_date` date DEFAULT NULL,
  `agreed_delivery_date` date DEFAULT NULL,
  `original_agreed_delivery_date` date DEFAULT NULL,
  `deadline_clock_paused_at` timestamp NULL DEFAULT NULL,
  `pending_extension_id` bigint unsigned DEFAULT NULL,
  `revisions_included` smallint unsigned NOT NULL DEFAULT '0',
  `revisions_used` smallint unsigned NOT NULL DEFAULT '0',
  `amendment_count` smallint unsigned NOT NULL DEFAULT '0',
  `delivery_extension_count` smallint unsigned NOT NULL DEFAULT '0',
  `parties_snapshot` json NOT NULL,
  `quest_snapshot` json NOT NULL,
  `financial_snapshot` json NOT NULL,
  `timeline_snapshot` json NOT NULL,
  `revision_policy_snapshot` json NOT NULL,
  `platform_terms_snapshot` json NOT NULL,
  `signatures_snapshot` json NOT NULL,
  `current_terms_snapshot` json DEFAULT NULL,
  `active_dispute_id` bigint unsigned DEFAULT NULL,
  `flagged_for_review` tinyint(1) NOT NULL DEFAULT '0',
  `flagged_for_review_reason` text COLLATE utf8mb4_unicode_ci,
  `flagged_for_review_by` bigint unsigned DEFAULT NULL,
  `flagged_for_review_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quest_contracts_quest_id_quest_offer_id_unique` (`quest_id`,`quest_offer_id`),
  UNIQUE KEY `quest_contracts_reference_code_unique` (`reference_code`),
  KEY `quest_contracts_quest_offer_id_foreign` (`quest_offer_id`),
  KEY `quest_contracts_client_id_foreign` (`client_id`),
  KEY `quest_contracts_freelancer_id_foreign` (`freelancer_id`),
  KEY `quest_contracts_active_dispute_id_foreign` (`active_dispute_id`),
  KEY `quest_contracts_flagged_for_review_by_foreign` (`flagged_for_review_by`),
  KEY `quest_contracts_status_index` (`status`),
  KEY `quest_contracts_pending_ext_fk` (`pending_extension_id`),
  CONSTRAINT `quest_contracts_active_dispute_id_foreign` FOREIGN KEY (`active_dispute_id`) REFERENCES `quest_disputes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quest_contracts_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_contracts_flagged_for_review_by_foreign` FOREIGN KEY (`flagged_for_review_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quest_contracts_freelancer_id_foreign` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_contracts_pending_ext_fk` FOREIGN KEY (`pending_extension_id`) REFERENCES `quest_contract_delivery_extensions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quest_contracts_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_contracts_quest_offer_id_foreign` FOREIGN KEY (`quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_contracts`
--

LOCK TABLES `quest_contracts` WRITE;
/*!40000 ALTER TABLE `quest_contracts` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_contracts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_conversation_messages`
--

DROP TABLE IF EXISTS `quest_conversation_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_conversation_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_conversation_thread_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_original` text COLLATE utf8mb4_unicode_ci,
  `is_redacted` tinyint(1) NOT NULL DEFAULT '0',
  `redaction_label` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quest_conversation_messages_user_id_foreign` (`user_id`),
  KEY `qcm_thread_created_idx` (`quest_conversation_thread_id`,`created_at`),
  CONSTRAINT `quest_conversation_messages_quest_conversation_thread_id_foreign` FOREIGN KEY (`quest_conversation_thread_id`) REFERENCES `quest_conversation_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_conversation_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_conversation_messages`
--

LOCK TABLES `quest_conversation_messages` WRITE;
/*!40000 ALTER TABLE `quest_conversation_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_conversation_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_conversation_threads`
--

DROP TABLE IF EXISTS `quest_conversation_threads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_conversation_threads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `freelancer_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `messages_count` int unsigned NOT NULL DEFAULT '0',
  `last_message_at` timestamp NULL DEFAULT NULL,
  `freelancer_last_read_at` timestamp NULL DEFAULT NULL,
  `client_last_read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `admin_hidden_at` timestamp NULL DEFAULT NULL,
  `admin_deleted_at` timestamp NULL DEFAULT NULL,
  `admin_visibility_reason` text COLLATE utf8mb4_unicode_ci,
  `admin_visibility_changed_by` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quest_conversation_threads_quest_id_freelancer_id_unique` (`quest_id`,`freelancer_id`),
  KEY `quest_conversation_threads_freelancer_id_foreign` (`freelancer_id`),
  KEY `quest_conversation_threads_client_id_foreign` (`client_id`),
  KEY `quest_conversation_threads_admin_visibility_changed_by_foreign` (`admin_visibility_changed_by`),
  KEY `quest_conversation_threads_admin_hidden_at_index` (`admin_hidden_at`),
  KEY `quest_conversation_threads_admin_deleted_at_index` (`admin_deleted_at`),
  CONSTRAINT `quest_conversation_threads_admin_visibility_changed_by_foreign` FOREIGN KEY (`admin_visibility_changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quest_conversation_threads_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_conversation_threads_freelancer_id_foreign` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_conversation_threads_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_conversation_threads`
--

LOCK TABLES `quest_conversation_threads` WRITE;
/*!40000 ALTER TABLE `quest_conversation_threads` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_conversation_threads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_disputes`
--

DROP TABLE IF EXISTS `quest_disputes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_disputes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quest_id` bigint unsigned NOT NULL,
  `quest_offer_id` bigint unsigned NOT NULL,
  `opened_by_user_id` bigint unsigned NOT NULL,
  `assigned_staff_id` bigint unsigned DEFAULT NULL,
  `staff_claimed_at` timestamp NULL DEFAULT NULL,
  `reason` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `structured_intake` json DEFAULT NULL,
  `phase` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'self_resolution',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `tier` tinyint unsigned NOT NULL DEFAULT '0',
  `appeals_used` tinyint unsigned NOT NULL DEFAULT '0',
  `disputed_amount_minor` bigint unsigned NOT NULL DEFAULT '0',
  `response_required_by` timestamp NULL DEFAULT NULL,
  `ruling_required_by` timestamp NULL DEFAULT NULL,
  `escalated_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_outcome` varchar(48) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `final_client_share_percent` tinyint unsigned DEFAULT NULL,
  `ruling_favoured_user_id` bigint unsigned DEFAULT NULL,
  `awaiting_user_id` bigint unsigned DEFAULT NULL,
  `client_agrees_resolve_at` timestamp NULL DEFAULT NULL,
  `freelancer_agrees_resolve_at` timestamp NULL DEFAULT NULL,
  `opening_summary` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quest_disputes_uuid_unique` (`uuid`),
  KEY `quest_disputes_quest_offer_id_foreign` (`quest_offer_id`),
  KEY `quest_disputes_opened_by_user_id_foreign` (`opened_by_user_id`),
  KEY `quest_disputes_ruling_favoured_user_id_foreign` (`ruling_favoured_user_id`),
  KEY `quest_disputes_awaiting_user_id_foreign` (`awaiting_user_id`),
  KEY `quest_disputes_quest_id_status_index` (`quest_id`,`status`),
  KEY `quest_disputes_reason_index` (`reason`),
  KEY `quest_disputes_phase_index` (`phase`),
  KEY `quest_disputes_status_index` (`status`),
  KEY `quest_disputes_response_required_by_index` (`response_required_by`),
  KEY `quest_disputes_ruling_required_by_index` (`ruling_required_by`),
  KEY `quest_disputes_assigned_staff_id_foreign` (`assigned_staff_id`),
  CONSTRAINT `quest_disputes_assigned_staff_id_foreign` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quest_disputes_awaiting_user_id_foreign` FOREIGN KEY (`awaiting_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quest_disputes_opened_by_user_id_foreign` FOREIGN KEY (`opened_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_disputes_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_disputes_quest_offer_id_foreign` FOREIGN KEY (`quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_disputes_ruling_favoured_user_id_foreign` FOREIGN KEY (`ruling_favoured_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_disputes`
--

LOCK TABLES `quest_disputes` WRITE;
/*!40000 ALTER TABLE `quest_disputes` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_disputes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_files`
--

DROP TABLE IF EXISTS `quest_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `disk` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cloudinary_public_id` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cloudinary_resource_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` bigint unsigned NOT NULL DEFAULT '0',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quest_files_quest_id_foreign` (`quest_id`),
  CONSTRAINT `quest_files_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_files`
--

LOCK TABLES `quest_files` WRITE;
/*!40000 ALTER TABLE `quest_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_freelancer_invites`
--

DROP TABLE IF EXISTS `quest_freelancer_invites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_freelancer_invites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `freelancer_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quest_freelancer_invites_quest_id_freelancer_id_unique` (`quest_id`,`freelancer_id`),
  KEY `quest_freelancer_invites_freelancer_id_foreign` (`freelancer_id`),
  CONSTRAINT `quest_freelancer_invites_freelancer_id_foreign` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_freelancer_invites_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_freelancer_invites`
--

LOCK TABLES `quest_freelancer_invites` WRITE;
/*!40000 ALTER TABLE `quest_freelancer_invites` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_freelancer_invites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_funding_intents`
--

DROP TABLE IF EXISTS `quest_funding_intents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_funding_intents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quest_id` bigint unsigned NOT NULL,
  `quest_offer_id` bigint unsigned NOT NULL,
  `payment_escrow_id` bigint unsigned DEFAULT NULL,
  `initiated_by_user_id` bigint unsigned NOT NULL,
  `quoted_total_minor` bigint unsigned NOT NULL DEFAULT '0',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'initiated',
  `gateway_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paystack_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quest_funding_intents_uuid_unique` (`uuid`),
  KEY `quest_funding_intents_quest_offer_id_foreign` (`quest_offer_id`),
  KEY `quest_funding_intents_initiated_by_user_id_foreign` (`initiated_by_user_id`),
  KEY `quest_funding_intents_quest_id_created_at_index` (`quest_id`,`created_at`),
  KEY `quest_funding_intents_status_index` (`status`),
  KEY `quest_funding_intents_payment_escrow_id_foreign` (`payment_escrow_id`),
  KEY `quest_funding_intents_paystack_reference_index` (`paystack_reference`),
  CONSTRAINT `quest_funding_intents_initiated_by_user_id_foreign` FOREIGN KEY (`initiated_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_funding_intents_payment_escrow_id_foreign` FOREIGN KEY (`payment_escrow_id`) REFERENCES `payment_escrows` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quest_funding_intents_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_funding_intents_quest_offer_id_foreign` FOREIGN KEY (`quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_funding_intents`
--

LOCK TABLES `quest_funding_intents` WRITE;
/*!40000 ALTER TABLE `quest_funding_intents` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_funding_intents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_journey_surveys`
--

DROP TABLE IF EXISTS `quest_journey_surveys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_journey_surveys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `token` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quest_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `quest_offer_id` bigint unsigned DEFAULT NULL,
  `cohort` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rejection_reason` varchar(48) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `answers` json DEFAULT NULL,
  `first_question_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_answer_value` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_answer_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `email_send_at` timestamp NULL DEFAULT NULL,
  `email_sent_at` timestamp NULL DEFAULT NULL,
  `reminders_sent` json DEFAULT NULL,
  `operational_flagged` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quest_journey_surveys_quest_id_user_id_cohort_unique` (`quest_id`,`user_id`,`cohort`),
  UNIQUE KEY `quest_journey_surveys_token_unique` (`token`),
  KEY `quest_journey_surveys_user_id_foreign` (`user_id`),
  KEY `quest_journey_surveys_quest_offer_id_foreign` (`quest_offer_id`),
  KEY `quest_journey_surveys_cohort_submitted_at_index` (`cohort`,`submitted_at`),
  KEY `quest_journey_surveys_expires_at_submitted_at_index` (`expires_at`,`submitted_at`),
  CONSTRAINT `quest_journey_surveys_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_journey_surveys_quest_offer_id_foreign` FOREIGN KEY (`quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quest_journey_surveys_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_journey_surveys`
--

LOCK TABLES `quest_journey_surveys` WRITE;
/*!40000 ALTER TABLE `quest_journey_surveys` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_journey_surveys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_lifecycle_email_logs`
--

DROP TABLE IF EXISTS `quest_lifecycle_email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_lifecycle_email_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `email_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_user_id` bigint unsigned NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quest_lifecycle_email_unique` (`quest_id`,`email_key`,`recipient_user_id`),
  KEY `quest_lifecycle_email_logs_recipient_user_id_foreign` (`recipient_user_id`),
  CONSTRAINT `quest_lifecycle_email_logs_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_lifecycle_email_logs_recipient_user_id_foreign` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_lifecycle_email_logs`
--

LOCK TABLES `quest_lifecycle_email_logs` WRITE;
/*!40000 ALTER TABLE `quest_lifecycle_email_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_lifecycle_email_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_listing_extension_logs`
--

DROP TABLE IF EXISTS `quest_listing_extension_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_listing_extension_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `client_user_id` bigint unsigned NOT NULL,
  `days_added` smallint unsigned NOT NULL,
  `previous_expires_at` timestamp NOT NULL,
  `new_expires_at` timestamp NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quest_listing_extension_logs_quest_id_foreign` (`quest_id`),
  KEY `quest_listing_extension_logs_client_user_id_foreign` (`client_user_id`),
  CONSTRAINT `quest_listing_extension_logs_client_user_id_foreign` FOREIGN KEY (`client_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_listing_extension_logs_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_listing_extension_logs`
--

LOCK TABLES `quest_listing_extension_logs` WRITE;
/*!40000 ALTER TABLE `quest_listing_extension_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_listing_extension_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_nudge_logs`
--

DROP TABLE IF EXISTS `quest_nudge_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_nudge_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `nudge_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_user_id` bigint unsigned DEFAULT NULL,
  `channel` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mail',
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `sent_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quest_nudge_unique_per_recipient` (`quest_id`,`nudge_type`,`recipient_user_id`),
  KEY `quest_nudge_logs_recipient_user_id_foreign` (`recipient_user_id`),
  KEY `quest_nudge_logs_quest_id_sent_at_index` (`quest_id`,`sent_at`),
  KEY `quest_nudge_logs_nudge_type_index` (`nudge_type`),
  CONSTRAINT `quest_nudge_logs_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_nudge_logs_recipient_user_id_foreign` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_nudge_logs`
--

LOCK TABLES `quest_nudge_logs` WRITE;
/*!40000 ALTER TABLE `quest_nudge_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_nudge_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quest_offers`
--

DROP TABLE IF EXISTS `quest_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_offers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `freelancer_id` bigint unsigned NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `admin_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'clear',
  `admin_status_reason` text COLLATE utf8mb4_unicode_ci,
  `admin_status_changed_by` bigint unsigned DEFAULT NULL,
  `admin_status_changed_at` timestamp NULL DEFAULT NULL,
  `admin_notice_severity` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `declined_at` timestamp NULL DEFAULT NULL,
  `withdrawn_at` timestamp NULL DEFAULT NULL,
  `shortlisted_at` timestamp NULL DEFAULT NULL,
  `award_client_confirmed_at` timestamp NULL DEFAULT NULL,
  `award_freelancer_confirmed_at` timestamp NULL DEFAULT NULL,
  `award_terms_snapshot` json DEFAULT NULL,
  `client_pinned_at` timestamp NULL DEFAULT NULL,
  `client_view_count` int unsigned NOT NULL DEFAULT '0',
  `last_client_view_at` timestamp NULL DEFAULT NULL,
  `freelancer_edit_deadline_at` timestamp NULL DEFAULT NULL,
  `pitch` text COLLATE utf8mb4_unicode_ci,
  `scope_detail` longtext COLLATE utf8mb4_unicode_ci,
  `quoted_amount_minor` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `warranty_terms` text COLLATE utf8mb4_unicode_ci,
  `proposed_completion_date` date DEFAULT NULL,
  `planned_start_date` date DEFAULT NULL,
  `planned_finish_date` date DEFAULT NULL,
  `materials` json DEFAULT NULL,
  `pricing_snapshot` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quest_offers_quest_id_freelancer_id_unique` (`quest_id`,`freelancer_id`),
  KEY `quest_offers_freelancer_id_foreign` (`freelancer_id`),
  KEY `quest_offers_status_index` (`status`),
  KEY `quest_offers_quest_freelancer_status_idx` (`quest_id`,`freelancer_id`,`status`),
  KEY `quest_offers_admin_status_changed_by_foreign` (`admin_status_changed_by`),
  KEY `quest_offers_admin_status_index` (`admin_status`),
  KEY `quest_offers_admin_notice_severity_index` (`admin_notice_severity`),
  CONSTRAINT `quest_offers_admin_status_changed_by_foreign` FOREIGN KEY (`admin_status_changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quest_offers_freelancer_id_foreign` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quest_offers_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quest_offers`
--

LOCK TABLES `quest_offers` WRITE;
/*!40000 ALTER TABLE `quest_offers` DISABLE KEYS */;
/*!40000 ALTER TABLE `quest_offers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quests`
--

DROP TABLE IF EXISTS `quests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `freelancer_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `quality_gate_feedback` json DEFAULT NULL,
  `quality_gate_failed_at` timestamp NULL DEFAULT NULL,
  `quest_category_id` bigint unsigned DEFAULT NULL,
  `state_id` bigint unsigned DEFAULT NULL,
  `city` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `cover_image_url` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `admin_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'clear',
  `admin_status_reason` text COLLATE utf8mb4_unicode_ci,
  `admin_status_changed_by` bigint unsigned DEFAULT NULL,
  `admin_status_changed_at` timestamp NULL DEFAULT NULL,
  `escrow_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `escrow_funded_at` timestamp NULL DEFAULT NULL,
  `delivery_acknowledged_at` timestamp NULL DEFAULT NULL,
  `delivery_acknowledged_by` bigint unsigned DEFAULT NULL,
  `release_authorized_at` timestamp NULL DEFAULT NULL,
  `release_authorized_by` bigint unsigned DEFAULT NULL,
  `release_hold_until` timestamp NULL DEFAULT NULL,
  `release_hold_reason` text COLLATE utf8mb4_unicode_ci,
  `release_hold_by` bigint unsigned DEFAULT NULL,
  `accepted_quest_offer_id` bigint unsigned DEFAULT NULL,
  `pending_award_offer_id` bigint unsigned DEFAULT NULL,
  `budget_amount_minor` bigint unsigned DEFAULT NULL,
  `paid_out_minor` bigint unsigned NOT NULL DEFAULT '0',
  `refunded_minor` bigint unsigned NOT NULL DEFAULT '0',
  `due_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `funds_released_at` timestamp NULL DEFAULT NULL,
  `auto_completed_at` timestamp NULL DEFAULT NULL,
  `completed_on_time` tinyint(1) DEFAULT NULL,
  `dispute_opened` tinyint(1) NOT NULL DEFAULT '0',
  `closure_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `terms_accepted_at` timestamp NULL DEFAULT NULL,
  `site_access_level` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pets_on_site` tinyint(1) DEFAULT NULL,
  `pets_detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_code` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_timing` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'flexible',
  `estimated_completion_days` smallint unsigned DEFAULT NULL,
  `site_visits_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `scheduled_start_date` date DEFAULT NULL,
  `visibility` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `freelancer_location_pref` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `availability_need` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_type` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estimated_hours` smallint unsigned DEFAULT NULL,
  `team_size` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `promotion_tier` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `auto_listing_expiry_days` smallint unsigned DEFAULT NULL,
  `listing_expires_at` timestamp NULL DEFAULT NULL,
  `listing_extension_count` tinyint unsigned NOT NULL DEFAULT '0',
  `listing_extended_at` timestamp NULL DEFAULT NULL,
  `listing_extension_reason` text COLLATE utf8mb4_unicode_ci,
  `listing_expiry_warning_sent_at` timestamp NULL DEFAULT NULL,
  `reposted_from_quest_id` bigint unsigned DEFAULT NULL,
  `client_edit_until` timestamp NULL DEFAULT NULL,
  `max_offers` smallint unsigned DEFAULT NULL,
  `slug` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `views_count` bigint unsigned NOT NULL DEFAULT '0',
  `offers_count` int unsigned NOT NULL DEFAULT '0',
  `health_score` tinyint unsigned DEFAULT NULL,
  `health_score_updated_at` timestamp NULL DEFAULT NULL,
  `saves_count` int unsigned NOT NULL DEFAULT '0',
  `traffic_source` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `traffic_utm` json DEFAULT NULL,
  `estimated_delivery_date` date DEFAULT NULL,
  `escrow_held_at` timestamp NULL DEFAULT NULL,
  `escrow_hold_reason` text COLLATE utf8mb4_unicode_ci,
  `escrow_hold_expected_resolution_at` timestamp NULL DEFAULT NULL,
  `escrow_frozen_at` timestamp NULL DEFAULT NULL,
  `escrow_freeze_reason` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quests_uuid_unique` (`uuid`),
  UNIQUE KEY `quests_reference_code_unique` (`reference_code`),
  UNIQUE KEY `quests_slug_unique` (`slug`),
  KEY `quests_client_id_foreign` (`client_id`),
  KEY `quests_freelancer_id_foreign` (`freelancer_id`),
  KEY `quests_status_index` (`status`),
  KEY `quests_closure_type_index` (`closure_type`),
  KEY `quests_escrow_held_at_index` (`escrow_held_at`),
  KEY `quests_escrow_frozen_at_index` (`escrow_frozen_at`),
  KEY `quests_quest_category_id_foreign` (`quest_category_id`),
  KEY `quests_state_id_foreign` (`state_id`),
  KEY `quests_accepted_quest_offer_id_foreign` (`accepted_quest_offer_id`),
  KEY `quests_escrow_status_index` (`escrow_status`),
  KEY `quests_escrow_funded_at_index` (`escrow_funded_at`),
  KEY `quests_auto_completed_at_index` (`auto_completed_at`),
  KEY `quests_delivery_acknowledged_by_foreign` (`delivery_acknowledged_by`),
  KEY `quests_release_authorized_by_foreign` (`release_authorized_by`),
  KEY `quests_release_hold_by_foreign` (`release_hold_by`),
  KEY `quests_admin_status_changed_by_foreign` (`admin_status_changed_by`),
  KEY `quests_admin_status_index` (`admin_status`),
  KEY `quests_pending_award_offer_fk` (`pending_award_offer_id`),
  KEY `quests_reposted_from_quest_id_foreign` (`reposted_from_quest_id`),
  CONSTRAINT `quests_accepted_quest_offer_id_foreign` FOREIGN KEY (`accepted_quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quests_admin_status_changed_by_foreign` FOREIGN KEY (`admin_status_changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quests_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quests_delivery_acknowledged_by_foreign` FOREIGN KEY (`delivery_acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quests_freelancer_id_foreign` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quests_pending_award_offer_fk` FOREIGN KEY (`pending_award_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quests_quest_category_id_foreign` FOREIGN KEY (`quest_category_id`) REFERENCES `quest_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quests_release_authorized_by_foreign` FOREIGN KEY (`release_authorized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quests_release_hold_by_foreign` FOREIGN KEY (`release_hold_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quests_reposted_from_quest_id_foreign` FOREIGN KEY (`reposted_from_quest_id`) REFERENCES `quests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quests_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quests`
--

LOCK TABLES `quests` WRITE;
/*!40000 ALTER TABLE `quests` DISABLE KEYS */;
/*!40000 ALTER TABLE `quests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referral_abuse_flags`
--

DROP TABLE IF EXISTS `referral_abuse_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_abuse_flags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_referral_id` bigint unsigned DEFAULT NULL,
  `referrer_user_id` bigint unsigned DEFAULT NULL,
  `reason` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `evidence` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referral_abuse_flags_user_referral_id_foreign` (`user_referral_id`),
  KEY `referral_abuse_flags_referrer_user_id_foreign` (`referrer_user_id`),
  KEY `referral_abuse_flags_reason_index` (`reason`),
  KEY `referral_abuse_flags_status_index` (`status`),
  CONSTRAINT `referral_abuse_flags_referrer_user_id_foreign` FOREIGN KEY (`referrer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `referral_abuse_flags_user_referral_id_foreign` FOREIGN KEY (`user_referral_id`) REFERENCES `user_referrals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referral_abuse_flags`
--

LOCK TABLES `referral_abuse_flags` WRITE;
/*!40000 ALTER TABLE `referral_abuse_flags` DISABLE KEYS */;
/*!40000 ALTER TABLE `referral_abuse_flags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referral_rewards`
--

DROP TABLE IF EXISTS `referral_rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_rewards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_referral_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `reward_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_minor` int unsigned NOT NULL DEFAULT '0',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `expires_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referral_rewards_user_referral_id_foreign` (`user_referral_id`),
  KEY `referral_rewards_user_id_foreign` (`user_id`),
  KEY `referral_rewards_reward_type_index` (`reward_type`),
  KEY `referral_rewards_status_index` (`status`),
  CONSTRAINT `referral_rewards_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referral_rewards_user_referral_id_foreign` FOREIGN KEY (`user_referral_id`) REFERENCES `user_referrals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referral_rewards`
--

LOCK TABLES `referral_rewards` WRITE;
/*!40000 ALTER TABLE `referral_rewards` DISABLE KEYS */;
/*!40000 ALTER TABLE `referral_rewards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_amendment_requests`
--

DROP TABLE IF EXISTS `review_amendment_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_amendment_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `review_id` bigint unsigned NOT NULL,
  `issued_by` bigint unsigned NOT NULL,
  `instructions` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `required_changes` json DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `responded_at` timestamp NULL DEFAULT NULL,
  `default_action` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `review_amendment_requests_review_id_foreign` (`review_id`),
  KEY `review_amendment_requests_issued_by_foreign` (`issued_by`),
  KEY `review_amendment_requests_expires_at_index` (`expires_at`),
  KEY `review_amendment_requests_status_index` (`status`),
  CONSTRAINT `review_amendment_requests_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `review_amendment_requests_review_id_foreign` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_amendment_requests`
--

LOCK TABLES `review_amendment_requests` WRITE;
/*!40000 ALTER TABLE `review_amendment_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `review_amendment_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_attachments`
--

DROP TABLE IF EXISTS `review_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `review_id` bigint unsigned NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `review_attachments_review_id_index` (`review_id`),
  CONSTRAINT `review_attachments_review_id_foreign` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_attachments`
--

LOCK TABLES `review_attachments` WRITE;
/*!40000 ALTER TABLE `review_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `review_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_authenticity_signals`
--

DROP TABLE IF EXISTS `review_authenticity_signals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_authenticity_signals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `review_id` bigint unsigned NOT NULL,
  `signal_type` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `confidence` decimal(4,3) NOT NULL DEFAULT '1.000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `review_authenticity_signals_review_id_foreign` (`review_id`),
  KEY `review_authenticity_signals_signal_type_index` (`signal_type`),
  CONSTRAINT `review_authenticity_signals_review_id_foreign` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_authenticity_signals`
--

LOCK TABLES `review_authenticity_signals` WRITE;
/*!40000 ALTER TABLE `review_authenticity_signals` DISABLE KEYS */;
/*!40000 ALTER TABLE `review_authenticity_signals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_manipulation_reports`
--

DROP TABLE IF EXISTS `review_manipulation_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_manipulation_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `report_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `report_date` date NOT NULL,
  `payload` json NOT NULL,
  `generated_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `review_manip_report_unique` (`report_type`,`report_date`),
  KEY `review_manipulation_reports_report_type_index` (`report_type`),
  KEY `review_manipulation_reports_report_date_index` (`report_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_manipulation_reports`
--

LOCK TABLES `review_manipulation_reports` WRITE;
/*!40000 ALTER TABLE `review_manipulation_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `review_manipulation_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_moderation_action_logs`
--

DROP TABLE IF EXISTS `review_moderation_action_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_moderation_action_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `review_id` bigint unsigned DEFAULT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `payload` json DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `review_moderation_action_logs_review_id_foreign` (`review_id`),
  KEY `review_moderation_action_logs_actor_user_id_foreign` (`actor_user_id`),
  KEY `review_moderation_action_logs_action_index` (`action`),
  KEY `review_moderation_action_logs_occurred_at_index` (`occurred_at`),
  CONSTRAINT `review_moderation_action_logs_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `review_moderation_action_logs_review_id_foreign` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_moderation_action_logs`
--

LOCK TABLES `review_moderation_action_logs` WRITE;
/*!40000 ALTER TABLE `review_moderation_action_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `review_moderation_action_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_moderation_clusters`
--

DROP TABLE IF EXISTS `review_moderation_clusters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_moderation_clusters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cluster_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `primary_reviewee_id` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `review_moderation_clusters_primary_reviewee_id_foreign` (`primary_reviewee_id`),
  KEY `review_moderation_clusters_cluster_type_index` (`cluster_type`),
  KEY `review_moderation_clusters_status_index` (`status`),
  CONSTRAINT `review_moderation_clusters_primary_reviewee_id_foreign` FOREIGN KEY (`primary_reviewee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_moderation_clusters`
--

LOCK TABLES `review_moderation_clusters` WRITE;
/*!40000 ALTER TABLE `review_moderation_clusters` DISABLE KEYS */;
/*!40000 ALTER TABLE `review_moderation_clusters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `reviewer_id` bigint unsigned NOT NULL,
  `reviewee_id` bigint unsigned NOT NULL,
  `reviewer_party` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `review_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` tinyint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `tags` json DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `authenticity_flag` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'clean',
  `quality_score` tinyint unsigned DEFAULT NULL,
  `is_brief` tinyint(1) NOT NULL DEFAULT '0',
  `sentiment_score` decimal(4,3) DEFAULT NULL,
  `reviewer_subnet` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moderation_cluster_id` bigint unsigned DEFAULT NULL,
  `edit_window_ends_at` timestamp NOT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviews_quest_id_reviewer_id_unique` (`quest_id`,`reviewer_id`),
  KEY `reviews_reviewer_id_foreign` (`reviewer_id`),
  KEY `reviews_reviewee_id_review_type_index` (`reviewee_id`,`review_type`),
  KEY `reviews_review_type_index` (`review_type`),
  KEY `reviews_status_index` (`status`),
  KEY `reviews_authenticity_flag_index` (`authenticity_flag`),
  KEY `reviews_reviewer_subnet_index` (`reviewer_subnet`),
  KEY `reviews_moderation_cluster_id_foreign` (`moderation_cluster_id`),
  CONSTRAINT `reviews_moderation_cluster_id_foreign` FOREIGN KEY (`moderation_cluster_id`) REFERENCES `review_moderation_clusters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reviews_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_reviewee_id_foreign` FOREIGN KEY (`reviewee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_reviewer_id_foreign` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Operations Staff Admin','admin','Operational staff with admin console access — support, moderation, verification, and day-to-day platform operations.','2026-05-31 18:17:01','2026-05-31 19:20:17'),(2,'Client (Sponsor)','client','Clients and sponsors who post quests, fund escrow, review delivery, and manage contracts.','2026-05-31 18:17:01','2026-05-31 19:20:17'),(3,'Freelancer (Pro)','freelancer','Hustlers and freelancers who submit proposals, deliver work, and receive payouts. Pro tier unlocks extra quota and visibility.','2026-05-31 18:17:01','2026-05-31 19:20:17'),(4,'Super Admin','super_admin','Full platform control — financial audit, quest boosts, super-admin settings, and unrestricted admin access.','2026-05-31 18:17:04','2026-05-31 19:20:17');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_action_logs`
--

DROP TABLE IF EXISTS `staff_action_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_action_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned NOT NULL,
  `action_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `outcome` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acted_at` timestamp NOT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_action_logs_staff_user_id_acted_at_index` (`staff_user_id`,`acted_at`),
  KEY `staff_action_logs_action_type_acted_at_index` (`action_type`,`acted_at`),
  CONSTRAINT `staff_action_logs_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_action_logs`
--

LOCK TABLES `staff_action_logs` WRITE;
/*!40000 ALTER TABLE `staff_action_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_action_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_activity_benchmarks`
--

DROP TABLE IF EXISTS `staff_activity_benchmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_activity_benchmarks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_group` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `minimum_weekly_actions` smallint unsigned NOT NULL DEFAULT '0',
  `created_by_user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_activity_benchmarks_role_group_unique` (`role_group`),
  KEY `staff_activity_benchmarks_created_by_user_id_foreign` (`created_by_user_id`),
  CONSTRAINT `staff_activity_benchmarks_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_activity_benchmarks`
--

LOCK TABLES `staff_activity_benchmarks` WRITE;
/*!40000 ALTER TABLE `staff_activity_benchmarks` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_activity_benchmarks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_badge_requests`
--

DROP TABLE IF EXISTS `staff_badge_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_badge_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `badge_slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `applicant_note` text COLLATE utf8mb4_unicode_ci,
  `metrics_snapshot` json DEFAULT NULL,
  `reviewed_by_staff_id` bigint unsigned DEFAULT NULL,
  `decision_note` text COLLATE utf8mb4_unicode_ci,
  `escalated_to_super_admin` tinyint(1) NOT NULL DEFAULT '0',
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_badge_requests_user_id_foreign` (`user_id`),
  KEY `staff_badge_requests_reviewed_by_staff_id_foreign` (`reviewed_by_staff_id`),
  KEY `staff_badge_requests_badge_slug_index` (`badge_slug`),
  KEY `staff_badge_requests_status_index` (`status`),
  CONSTRAINT `staff_badge_requests_reviewed_by_staff_id_foreign` FOREIGN KEY (`reviewed_by_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_badge_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_badge_requests`
--

LOCK TABLES `staff_badge_requests` WRITE;
/*!40000 ALTER TABLE `staff_badge_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_badge_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_bulk_message_requests`
--

DROP TABLE IF EXISTS `staff_bulk_message_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_bulk_message_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by_admin_id` bigint unsigned NOT NULL,
  `approved_by_admin_id` bigint unsigned DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_authorisation',
  `audience` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all_users',
  `channels` json DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipients_count` int unsigned NOT NULL DEFAULT '0',
  `approval_note` text COLLATE utf8mb4_unicode_ci,
  `approved_at` timestamp NULL DEFAULT NULL,
  `dispatched_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_bulk_message_requests_uuid_unique` (`uuid`),
  KEY `staff_bulk_message_requests_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `staff_bulk_message_requests_approved_by_admin_id_foreign` (`approved_by_admin_id`),
  KEY `staff_bulk_message_requests_status_index` (`status`),
  KEY `staff_bulk_message_requests_audience_index` (`audience`),
  CONSTRAINT `staff_bulk_message_requests_approved_by_admin_id_foreign` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_bulk_message_requests_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_bulk_message_requests`
--

LOCK TABLES `staff_bulk_message_requests` WRITE;
/*!40000 ALTER TABLE `staff_bulk_message_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_bulk_message_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_escrow_anomaly_notes`
--

DROP TABLE IF EXISTS `staff_escrow_anomaly_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_escrow_anomaly_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_id` bigint unsigned NOT NULL,
  `anomaly_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `staff_user_id` bigint unsigned NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `outreach_summary` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_escrow_anomaly_notes_quest_id_foreign` (`quest_id`),
  KEY `staff_escrow_anomaly_notes_staff_user_id_foreign` (`staff_user_id`),
  KEY `staff_escrow_anomaly_notes_anomaly_type_index` (`anomaly_type`),
  KEY `staff_escrow_anomaly_notes_status_index` (`status`),
  CONSTRAINT `staff_escrow_anomaly_notes_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_escrow_anomaly_notes_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_escrow_anomaly_notes`
--

LOCK TABLES `staff_escrow_anomaly_notes` WRITE;
/*!40000 ALTER TABLE `staff_escrow_anomaly_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_escrow_anomaly_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_freelancer_quality_flags`
--

DROP TABLE IF EXISTS `staff_freelancer_quality_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_freelancer_quality_flags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `freelancer_id` bigint unsigned NOT NULL,
  `staff_user_id` bigint unsigned DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `trigger_reason` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metrics_snapshot` json DEFAULT NULL,
  `trend_snapshot` json DEFAULT NULL,
  `staff_notes` text COLLATE utf8mb4_unicode_ci,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_freelancer_quality_flags_freelancer_id_foreign` (`freelancer_id`),
  KEY `staff_freelancer_quality_flags_staff_user_id_foreign` (`staff_user_id`),
  KEY `staff_freelancer_quality_flags_status_index` (`status`),
  KEY `staff_freelancer_quality_flags_trigger_reason_index` (`trigger_reason`),
  CONSTRAINT `staff_freelancer_quality_flags_freelancer_id_foreign` FOREIGN KEY (`freelancer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_freelancer_quality_flags_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_freelancer_quality_flags`
--

LOCK TABLES `staff_freelancer_quality_flags` WRITE;
/*!40000 ALTER TABLE `staff_freelancer_quality_flags` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_freelancer_quality_flags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_hr_alerts`
--

DROP TABLE IF EXISTS `staff_hr_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_hr_alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned DEFAULT NULL,
  `alert_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json DEFAULT NULL,
  `triggered_at` timestamp NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_hr_alerts_staff_user_id_foreign` (`staff_user_id`),
  KEY `staff_hr_alerts_alert_type_triggered_at_index` (`alert_type`,`triggered_at`),
  CONSTRAINT `staff_hr_alerts_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_hr_alerts`
--

LOCK TABLES `staff_hr_alerts` WRITE;
/*!40000 ALTER TABLE `staff_hr_alerts` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_hr_alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_hr_audit_trails`
--

DROP TABLE IF EXISTS `staff_hr_audit_trails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_hr_audit_trails` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` bigint unsigned NOT NULL,
  `action_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_staff_user_id` bigint unsigned DEFAULT NULL,
  `before_values` json DEFAULT NULL,
  `after_values` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_hr_audit_trails_actor_user_id_foreign` (`actor_user_id`),
  KEY `staff_hr_audit_trails_target_staff_user_id_foreign` (`target_staff_user_id`),
  CONSTRAINT `staff_hr_audit_trails_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_hr_audit_trails_target_staff_user_id_foreign` FOREIGN KEY (`target_staff_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_hr_audit_trails`
--

LOCK TABLES `staff_hr_audit_trails` WRITE;
/*!40000 ALTER TABLE `staff_hr_audit_trails` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_hr_audit_trails` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_hr_compliance_cases`
--

DROP TABLE IF EXISTS `staff_hr_compliance_cases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_hr_compliance_cases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned NOT NULL,
  `severity` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `incident_note` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `evidence` json DEFAULT NULL,
  `opened_by_user_id` bigint unsigned NOT NULL,
  `updated_by_user_id` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_hr_compliance_cases_staff_user_id_foreign` (`staff_user_id`),
  KEY `staff_hr_compliance_cases_opened_by_user_id_foreign` (`opened_by_user_id`),
  KEY `staff_hr_compliance_cases_updated_by_user_id_foreign` (`updated_by_user_id`),
  CONSTRAINT `staff_hr_compliance_cases_opened_by_user_id_foreign` FOREIGN KEY (`opened_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_hr_compliance_cases_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_hr_compliance_cases_updated_by_user_id_foreign` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_hr_compliance_cases`
--

LOCK TABLES `staff_hr_compliance_cases` WRITE;
/*!40000 ALTER TABLE `staff_hr_compliance_cases` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_hr_compliance_cases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_hr_suspicious_activity_flags`
--

DROP TABLE IF EXISTS `staff_hr_suspicious_activity_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_hr_suspicious_activity_flags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned NOT NULL,
  `staff_session_log_id` bigint unsigned DEFAULT NULL,
  `pattern` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `flagged_by_user_id` bigint unsigned NOT NULL,
  `flagged_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_hr_suspicious_activity_flags_staff_user_id_foreign` (`staff_user_id`),
  KEY `staff_hr_suspicious_activity_flags_staff_session_log_id_foreign` (`staff_session_log_id`),
  KEY `staff_hr_suspicious_activity_flags_flagged_by_user_id_foreign` (`flagged_by_user_id`),
  CONSTRAINT `staff_hr_suspicious_activity_flags_flagged_by_user_id_foreign` FOREIGN KEY (`flagged_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_hr_suspicious_activity_flags_staff_session_log_id_foreign` FOREIGN KEY (`staff_session_log_id`) REFERENCES `staff_session_logs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_hr_suspicious_activity_flags_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_hr_suspicious_activity_flags`
--

LOCK TABLES `staff_hr_suspicious_activity_flags` WRITE;
/*!40000 ALTER TABLE `staff_hr_suspicious_activity_flags` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_hr_suspicious_activity_flags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_knowledge_articles`
--

DROP TABLE IF EXISTS `staff_knowledge_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_knowledge_articles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `created_by_admin_id` bigint unsigned DEFAULT NULL,
  `updated_by_admin_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_knowledge_articles_slug_unique` (`slug`),
  KEY `staff_knowledge_articles_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `staff_knowledge_articles_updated_by_admin_id_foreign` (`updated_by_admin_id`),
  KEY `staff_knowledge_articles_category_index` (`category`),
  KEY `staff_knowledge_articles_status_index` (`status`),
  CONSTRAINT `staff_knowledge_articles_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_knowledge_articles_updated_by_admin_id_foreign` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_knowledge_articles`
--

LOCK TABLES `staff_knowledge_articles` WRITE;
/*!40000 ALTER TABLE `staff_knowledge_articles` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_knowledge_articles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_knowledge_suggestions`
--

DROP TABLE IF EXISTS `staff_knowledge_suggestions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_knowledge_suggestions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_knowledge_article_id` bigint unsigned DEFAULT NULL,
  `suggested_by_staff_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_knowledge_suggestions_staff_knowledge_article_id_foreign` (`staff_knowledge_article_id`),
  KEY `staff_knowledge_suggestions_suggested_by_staff_id_foreign` (`suggested_by_staff_id`),
  KEY `staff_knowledge_suggestions_status_index` (`status`),
  CONSTRAINT `staff_knowledge_suggestions_staff_knowledge_article_id_foreign` FOREIGN KEY (`staff_knowledge_article_id`) REFERENCES `staff_knowledge_articles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_knowledge_suggestions_suggested_by_staff_id_foreign` FOREIGN KEY (`suggested_by_staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_knowledge_suggestions`
--

LOCK TABLES `staff_knowledge_suggestions` WRITE;
/*!40000 ALTER TABLE `staff_knowledge_suggestions` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_knowledge_suggestions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_leave_balances`
--

DROP TABLE IF EXISTS `staff_leave_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_leave_balances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned NOT NULL,
  `year` smallint unsigned NOT NULL,
  `annual_days` smallint unsigned NOT NULL DEFAULT '0',
  `sick_days` smallint unsigned NOT NULL DEFAULT '0',
  `emergency_days` smallint unsigned NOT NULL DEFAULT '0',
  `unpaid_days` smallint unsigned NOT NULL DEFAULT '0',
  `annual_days_used` smallint unsigned NOT NULL DEFAULT '0',
  `sick_days_used` smallint unsigned NOT NULL DEFAULT '0',
  `emergency_days_used` smallint unsigned NOT NULL DEFAULT '0',
  `unpaid_days_used` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_leave_balances_staff_user_id_year_unique` (`staff_user_id`,`year`),
  CONSTRAINT `staff_leave_balances_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_leave_balances`
--

LOCK TABLES `staff_leave_balances` WRITE;
/*!40000 ALTER TABLE `staff_leave_balances` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_leave_balances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_leave_requests`
--

DROP TABLE IF EXISTS `staff_leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_leave_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned NOT NULL,
  `leave_type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full_day',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_requested` smallint unsigned NOT NULL DEFAULT '1',
  `hours_requested` tinyint unsigned DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reviewed_by_user_id` bigint unsigned DEFAULT NULL,
  `review_note` text COLLATE utf8mb4_unicode_ci,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_leave_requests_reviewed_by_user_id_foreign` (`reviewed_by_user_id`),
  KEY `staff_leave_requests_staff_user_id_status_index` (`staff_user_id`,`status`),
  KEY `staff_leave_requests_start_date_end_date_index` (`start_date`,`end_date`),
  CONSTRAINT `staff_leave_requests_reviewed_by_user_id_foreign` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_leave_requests_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_leave_requests`
--

LOCK TABLES `staff_leave_requests` WRITE;
/*!40000 ALTER TABLE `staff_leave_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_leave_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_notification_preferences`
--

DROP TABLE IF EXISTS `staff_notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_notification_preferences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned NOT NULL,
  `preferences` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_notification_preferences_staff_user_id_unique` (`staff_user_id`),
  CONSTRAINT `staff_notification_preferences_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_notification_preferences`
--

LOCK TABLES `staff_notification_preferences` WRITE;
/*!40000 ALTER TABLE `staff_notification_preferences` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_notification_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_onboarding_assistance_records`
--

DROP TABLE IF EXISTS `staff_onboarding_assistance_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_onboarding_assistance_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `user_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scenario` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `milestone_reached` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `staleness_score` smallint unsigned NOT NULL DEFAULT '0',
  `cycles_elapsed` smallint unsigned NOT NULL DEFAULT '1',
  `last_meaningful_action_at` timestamp NULL DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `fields_completed` json DEFAULT NULL,
  `flow_metadata` json DEFAULT NULL,
  `return_sessions_count` smallint unsigned NOT NULL DEFAULT '0',
  `assigned_staff_id` bigint unsigned DEFAULT NULL,
  `contacted_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `next_cycle_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_onboarding_assistance_records_assigned_staff_id_foreign` (`assigned_staff_id`),
  KEY `staff_onboarding_user_id_idx` (`user_id`),
  KEY `staff_onboarding_assistance_records_user_id_status_index` (`user_id`,`status`),
  KEY `staff_onboarding_assistance_records_user_type_index` (`user_type`),
  KEY `staff_onboarding_assistance_records_scenario_index` (`scenario`),
  KEY `staff_onboarding_assistance_records_status_index` (`status`),
  KEY `staff_onboarding_assistance_records_staleness_score_index` (`staleness_score`),
  CONSTRAINT `staff_onboarding_assistance_records_assigned_staff_id_foreign` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_onboarding_assistance_records_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_onboarding_assistance_records`
--

LOCK TABLES `staff_onboarding_assistance_records` WRITE;
/*!40000 ALTER TABLE `staff_onboarding_assistance_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_onboarding_assistance_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_onboarding_outreach`
--

DROP TABLE IF EXISTS `staff_onboarding_outreach`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_onboarding_outreach` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `scenario` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `friction_point` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `context` json DEFAULT NULL,
  `assigned_staff_id` bigint unsigned DEFAULT NULL,
  `contacted_by_staff_id` bigint unsigned DEFAULT NULL,
  `contacted_at` timestamp NULL DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_onboarding_outreach_user_id_scenario_unique` (`user_id`,`scenario`),
  KEY `staff_onboarding_outreach_assigned_staff_id_foreign` (`assigned_staff_id`),
  KEY `staff_onboarding_outreach_contacted_by_staff_id_foreign` (`contacted_by_staff_id`),
  KEY `staff_onboarding_outreach_scenario_index` (`scenario`),
  KEY `staff_onboarding_outreach_status_index` (`status`),
  CONSTRAINT `staff_onboarding_outreach_assigned_staff_id_foreign` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_onboarding_outreach_contacted_by_staff_id_foreign` FOREIGN KEY (`contacted_by_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_onboarding_outreach_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_onboarding_outreach`
--

LOCK TABLES `staff_onboarding_outreach` WRITE;
/*!40000 ALTER TABLE `staff_onboarding_outreach` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_onboarding_outreach` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_page_activity_logs`
--

DROP TABLE IF EXISTS `staff_page_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_page_activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned NOT NULL,
  `section_key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seconds_spent` int unsigned NOT NULL DEFAULT '0',
  `visits` int unsigned NOT NULL DEFAULT '1',
  `activity_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_page_activity_unique` (`staff_user_id`,`section_key`,`activity_date`),
  CONSTRAINT `staff_page_activity_logs_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_page_activity_logs`
--

LOCK TABLES `staff_page_activity_logs` WRITE;
/*!40000 ALTER TABLE `staff_page_activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_page_activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_patrol_items`
--

DROP TABLE IF EXISTS `staff_patrol_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_patrol_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_patrol_session_id` bigint unsigned NOT NULL,
  `reviewable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reviewable_id` bigint unsigned NOT NULL,
  `decision` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `risk_signals` json DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_patrol_item_unique` (`staff_patrol_session_id`,`reviewable_type`,`reviewable_id`),
  KEY `staff_patrol_items_decision_index` (`decision`),
  CONSTRAINT `staff_patrol_items_staff_patrol_session_id_foreign` FOREIGN KEY (`staff_patrol_session_id`) REFERENCES `staff_patrol_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_patrol_items`
--

LOCK TABLES `staff_patrol_items` WRITE;
/*!40000 ALTER TABLE `staff_patrol_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_patrol_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_patrol_sessions`
--

DROP TABLE IF EXISTS `staff_patrol_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_patrol_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned NOT NULL,
  `content_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `sample_size` smallint unsigned NOT NULL DEFAULT '25',
  `reviewed_count` smallint unsigned NOT NULL DEFAULT '0',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_patrol_sessions_staff_user_id_foreign` (`staff_user_id`),
  KEY `staff_patrol_sessions_content_type_index` (`content_type`),
  KEY `staff_patrol_sessions_category_id_index` (`category_id`),
  KEY `staff_patrol_sessions_status_index` (`status`),
  CONSTRAINT `staff_patrol_sessions_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_patrol_sessions`
--

LOCK TABLES `staff_patrol_sessions` WRITE;
/*!40000 ALTER TABLE `staff_patrol_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_patrol_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_payment_exceptions`
--

DROP TABLE IF EXISTS `staff_payment_exceptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_payment_exceptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `staff_user_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `quest_id` bigint unsigned DEFAULT NULL,
  `admin_task_id` bigint unsigned DEFAULT NULL,
  `type` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `amount_minor` bigint unsigned DEFAULT NULL,
  `error_code` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_summary` text COLLATE utf8mb4_unicode_ci,
  `staff_summary` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_payment_exceptions_uuid_unique` (`uuid`),
  KEY `staff_payment_exceptions_staff_user_id_foreign` (`staff_user_id`),
  KEY `staff_payment_exceptions_user_id_foreign` (`user_id`),
  KEY `staff_payment_exceptions_quest_id_foreign` (`quest_id`),
  KEY `staff_payment_exceptions_admin_task_id_foreign` (`admin_task_id`),
  KEY `staff_payment_exceptions_type_index` (`type`),
  KEY `staff_payment_exceptions_status_index` (`status`),
  CONSTRAINT `staff_payment_exceptions_admin_task_id_foreign` FOREIGN KEY (`admin_task_id`) REFERENCES `admin_tasks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_payment_exceptions_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_payment_exceptions_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_payment_exceptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_payment_exceptions`
--

LOCK TABLES `staff_payment_exceptions` WRITE;
/*!40000 ALTER TABLE `staff_payment_exceptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_payment_exceptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_payroll_adjustments`
--

DROP TABLE IF EXISTS `staff_payroll_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_payroll_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deduction_mode` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deduction_basis` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deduction_percentage` decimal(8,4) DEFAULT NULL,
  `deduction_custom_base_amount` decimal(15,2) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `effective_date` date NOT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT '0',
  `reference` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_payroll_adjustments_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `staff_payroll_adjustments_staff_user_id_effective_date_index` (`staff_user_id`,`effective_date`),
  CONSTRAINT `staff_payroll_adjustments_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_payroll_adjustments_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_payroll_adjustments`
--

LOCK TABLES `staff_payroll_adjustments` WRITE;
/*!40000 ALTER TABLE `staff_payroll_adjustments` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_payroll_adjustments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_payroll_profiles`
--

DROP TABLE IF EXISTS `staff_payroll_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_payroll_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned NOT NULL,
  `base_salary` decimal(15,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NGN',
  `payment_frequency` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `effective_from` date DEFAULT NULL,
  `bank_details_encrypted` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_payroll_profiles_staff_user_id_unique` (`staff_user_id`),
  CONSTRAINT `staff_payroll_profiles_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_payroll_profiles`
--

LOCK TABLES `staff_payroll_profiles` WRITE;
/*!40000 ALTER TABLE `staff_payroll_profiles` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_payroll_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_payslips`
--

DROP TABLE IF EXISTS `staff_payslips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_payslips` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned NOT NULL,
  `year` smallint unsigned NOT NULL,
  `month` tinyint unsigned NOT NULL,
  `gross_pay` decimal(15,2) NOT NULL,
  `bonuses` decimal(15,2) NOT NULL DEFAULT '0.00',
  `deductions` decimal(15,2) NOT NULL DEFAULT '0.00',
  `net_pay` decimal(15,2) NOT NULL,
  `pdf_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_payslips_staff_user_id_year_month_unique` (`staff_user_id`,`year`,`month`),
  CONSTRAINT `staff_payslips_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_payslips`
--

LOCK TABLES `staff_payslips` WRITE;
/*!40000 ALTER TABLE `staff_payslips` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_payslips` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_performance_scores`
--

DROP TABLE IF EXISTS `staff_performance_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_performance_scores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned NOT NULL,
  `year` smallint unsigned NOT NULL,
  `month` tinyint unsigned NOT NULL,
  `score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `metric_counts` json DEFAULT NULL,
  `volume_points` int unsigned NOT NULL DEFAULT '0',
  `resolution_points` int unsigned NOT NULL DEFAULT '0',
  `speed_points` int unsigned NOT NULL DEFAULT '0',
  `overridden` tinyint(1) NOT NULL DEFAULT '0',
  `overridden_score` decimal(5,2) DEFAULT NULL,
  `override_note` text COLLATE utf8mb4_unicode_ci,
  `override_by_user_id` bigint unsigned DEFAULT NULL,
  `override_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_performance_scores_staff_user_id_year_month_unique` (`staff_user_id`,`year`,`month`),
  KEY `staff_performance_scores_override_by_user_id_foreign` (`override_by_user_id`),
  CONSTRAINT `staff_performance_scores_override_by_user_id_foreign` FOREIGN KEY (`override_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_performance_scores_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_performance_scores`
--

LOCK TABLES `staff_performance_scores` WRITE;
/*!40000 ALTER TABLE `staff_performance_scores` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_performance_scores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_proactive_outreach_items`
--

DROP TABLE IF EXISTS `staff_proactive_outreach_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_proactive_outreach_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `situation_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `priority` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `priority_score` smallint unsigned NOT NULL DEFAULT '50',
  `target_user_id` bigint unsigned DEFAULT NULL,
  `quest_id` bigint unsigned DEFAULT NULL,
  `quest_offer_id` bigint unsigned DEFAULT NULL,
  `quest_dispute_id` bigint unsigned DEFAULT NULL,
  `conversation_thread_review_id` bigint unsigned DEFAULT NULL,
  `fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` json DEFAULT NULL,
  `suggested_template_slug` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_staff_id` bigint unsigned DEFAULT NULL,
  `snoozed_until` timestamp NULL DEFAULT NULL,
  `detected_at` timestamp NOT NULL,
  `last_outreach_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_proactive_outreach_items_uuid_unique` (`uuid`),
  UNIQUE KEY `staff_proactive_outreach_items_fingerprint_unique` (`fingerprint`),
  KEY `staff_proactive_outreach_items_target_user_id_foreign` (`target_user_id`),
  KEY `staff_proactive_outreach_items_quest_id_foreign` (`quest_id`),
  KEY `staff_proactive_outreach_items_quest_offer_id_foreign` (`quest_offer_id`),
  KEY `staff_proactive_outreach_items_quest_dispute_id_foreign` (`quest_dispute_id`),
  KEY `staff_proactive_outreach_items_assigned_staff_id_foreign` (`assigned_staff_id`),
  KEY `staff_outreach_situ_status_idx` (`situation_key`,`status`),
  KEY `staff_outreach_thread_review_idx` (`conversation_thread_review_id`),
  KEY `staff_outreach_snoozed_idx` (`snoozed_until`),
  KEY `staff_proactive_outreach_items_situation_key_index` (`situation_key`),
  KEY `staff_proactive_outreach_items_status_index` (`status`),
  KEY `staff_proactive_outreach_items_priority_index` (`priority`),
  KEY `staff_proactive_outreach_items_priority_score_index` (`priority_score`),
  KEY `staff_proactive_outreach_items_detected_at_index` (`detected_at`),
  CONSTRAINT `staff_proactive_outreach_items_assigned_staff_id_foreign` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_proactive_outreach_items_quest_dispute_id_foreign` FOREIGN KEY (`quest_dispute_id`) REFERENCES `quest_disputes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_proactive_outreach_items_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_proactive_outreach_items_quest_offer_id_foreign` FOREIGN KEY (`quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_proactive_outreach_items_target_user_id_foreign` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_proactive_outreach_items`
--

LOCK TABLES `staff_proactive_outreach_items` WRITE;
/*!40000 ALTER TABLE `staff_proactive_outreach_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_proactive_outreach_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_proactive_outreach_logs`
--

DROP TABLE IF EXISTS `staff_proactive_outreach_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_proactive_outreach_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `outreach_item_id` bigint unsigned NOT NULL,
  `staff_user_id` bigint unsigned NOT NULL,
  `template_id` bigint unsigned DEFAULT NULL,
  `channel` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'both',
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sent_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_proactive_outreach_logs_outreach_item_id_foreign` (`outreach_item_id`),
  KEY `staff_proactive_outreach_logs_staff_user_id_foreign` (`staff_user_id`),
  KEY `staff_proactive_outreach_logs_template_id_foreign` (`template_id`),
  CONSTRAINT `staff_proactive_outreach_logs_outreach_item_id_foreign` FOREIGN KEY (`outreach_item_id`) REFERENCES `staff_proactive_outreach_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_proactive_outreach_logs_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_proactive_outreach_logs_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `staff_response_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_proactive_outreach_logs`
--

LOCK TABLES `staff_proactive_outreach_logs` WRITE;
/*!40000 ALTER TABLE `staff_proactive_outreach_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_proactive_outreach_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_response_templates`
--

DROP TABLE IF EXISTS `staff_response_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_response_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `situation_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `policy_tags` json DEFAULT NULL,
  `placeholders` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint unsigned NOT NULL DEFAULT '100',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_response_templates_slug_unique` (`slug`),
  KEY `staff_response_templates_created_by_foreign` (`created_by`),
  KEY `staff_response_templates_updated_by_foreign` (`updated_by`),
  KEY `staff_response_templates_situation_key_index` (`situation_key`),
  KEY `staff_response_templates_category_index` (`category`),
  KEY `staff_response_templates_is_active_index` (`is_active`),
  CONSTRAINT `staff_response_templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_response_templates_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_response_templates`
--

LOCK TABLES `staff_response_templates` WRITE;
/*!40000 ALTER TABLE `staff_response_templates` DISABLE KEYS */;
INSERT INTO `staff_response_templates` VALUES (1,'freelancer-kyc-no-proposal','freelancer_kyc_no_proposal_14d','retention','KYC complete — first proposal nudge','Ready to send your first proposal?','Hi :name,\n\nYour verification is complete — great work. The next step is sending a focused proposal on a Quest that matches your skills.\n\nI can suggest active Quests in your categories or review a draft pitch before you submit. Reply if you\'d like a hand getting started.','[\"retention\", \"onboarding\", \"freelancer\"]',NULL,1,10,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(2,'client-no-quest-posted','client_no_quest_21d','retention','Client — no Quest posted','Need help posting your first Quest?','Hi :name,\n\nYou joined HustleSafe a few weeks ago but haven\'t published a Quest yet. I can help you draft a clear brief, choose the right category, and set a fair budget so freelancers respond faster.\n\nReply if you\'d like a quick walkthrough — it only takes a few minutes.','[\"retention\", \"onboarding\", \"client\"]',NULL,1,20,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(3,'quest-awarded-no-escrow','awarded_no_escrow_funded','escrow','Awarded — escrow not funded','Fund escrow for “:quest_title”','Hi :name,\n\nYou awarded :freelancer_name on “:quest_title” (:quest_reference), but escrow has not been funded yet.\n\nFunding escrow lets work begin safely for both sides — all payments stay protected on HustleSafe until delivery is confirmed.\n\nIf you need help with checkout or have questions about escrow, reply here and we\'ll assist.','[\"escrow\", \"payments\", \"client\"]',NULL,1,30,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(4,'freelancer-rating-drop-coaching','freelancer_rating_drop','quality','Freelancer — rating drop coaching','We are here to help you bounce back','Hi :name,\n\nWe noticed your recent reviews (:rating_after average over the last few weeks) are below your usual standard (:rating_before overall).\n\nThis can happen after a tough project — we\'re not here to penalise you, but to help. Reply if you\'d like tips on scope clarity, delivery updates, or dispute prevention.\n\nConsistent communication often makes the biggest difference.','[\"quality\", \"coaching\", \"freelancer\"]',NULL,1,40,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(5,'dispute-no-evidence','dispute_open_no_evidence','dispute','Dispute — evidence reminder','Please submit evidence for your dispute','Hi :name,\n\nYour dispute on “:quest_title” is open, but we have not received supporting evidence yet.\n\nUpload screenshots, delivery files, or message excerpts in the dispute centre so our team can review fairly. Disputes without evidence may be delayed or closed.\n\nReply if you are unsure what to include — we can guide you.','[\"dispute\", \"evidence\", \"policy\"]',NULL,1,50,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(6,'off-platform-payment-warning','off_platform_payment_flagged','trust','Off-platform payment — policy reminder','Important: keep payments on HustleSafe','Hi :name,\n\nOur systems flagged language in a recent conversation that may relate to off-platform payment.\n\nAll payments must go through HustleSafe escrow — this protects you and the other party. Repeated attempts to move deals off-platform can result in account restrictions.\n\nIf this was a misunderstanding, reply and we\'ll clarify. If you need help funding escrow on “:quest_title”, we\'re happy to assist.','[\"trust\", \"payments\", \"policy\", \"warning\"]',NULL,1,60,NULL,NULL,'2026-05-31 19:20:21','2026-05-31 19:20:21');
/*!40000 ALTER TABLE `staff_response_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_review_integrity_cases`
--

DROP TABLE IF EXISTS `staff_review_integrity_cases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_review_integrity_cases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pattern_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pattern_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_user_id` bigint unsigned DEFAULT NULL,
  `pattern_data` json DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `investigated_by_staff_id` bigint unsigned DEFAULT NULL,
  `findings` text COLLATE utf8mb4_unicode_ci,
  `flagged_review_ids` json DEFAULT NULL,
  `escalated_to_super_admin` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_review_integrity_cases_subject_user_id_foreign` (`subject_user_id`),
  KEY `staff_review_integrity_cases_investigated_by_staff_id_foreign` (`investigated_by_staff_id`),
  KEY `staff_review_integrity_cases_pattern_type_index` (`pattern_type`),
  KEY `staff_review_integrity_cases_pattern_key_index` (`pattern_key`),
  KEY `staff_review_integrity_cases_status_index` (`status`),
  CONSTRAINT `staff_review_integrity_cases_investigated_by_staff_id_foreign` FOREIGN KEY (`investigated_by_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_review_integrity_cases_subject_user_id_foreign` FOREIGN KEY (`subject_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_review_integrity_cases`
--

LOCK TABLES `staff_review_integrity_cases` WRITE;
/*!40000 ALTER TABLE `staff_review_integrity_cases` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_review_integrity_cases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_role_assignments`
--

DROP TABLE IF EXISTS `staff_role_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_role_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned NOT NULL,
  `role_group` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `assigned_by_user_id` bigint unsigned NOT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoked_by_user_id` bigint unsigned DEFAULT NULL,
  `revoked_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_role_assignments_assigned_by_user_id_foreign` (`assigned_by_user_id`),
  KEY `staff_role_assignments_revoked_by_user_id_foreign` (`revoked_by_user_id`),
  KEY `staff_role_assignments_staff_user_id_status_index` (`staff_user_id`,`status`),
  KEY `staff_role_assignments_role_group_status_index` (`role_group`,`status`),
  CONSTRAINT `staff_role_assignments_assigned_by_user_id_foreign` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_role_assignments_revoked_by_user_id_foreign` FOREIGN KEY (`revoked_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_role_assignments_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_role_assignments`
--

LOCK TABLES `staff_role_assignments` WRITE;
/*!40000 ALTER TABLE `staff_role_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_role_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_sanction_appeals`
--

DROP TABLE IF EXISTS `staff_sanction_appeals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_sanction_appeals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_sanction_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `statement` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `evidence` json DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `assigned_staff_id` bigint unsigned DEFAULT NULL,
  `reviewed_by_staff_id` bigint unsigned DEFAULT NULL,
  `escalated_to_admin_id` bigint unsigned DEFAULT NULL,
  `decision_note` text COLLATE utf8mb4_unicode_ci,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_sanction_appeals_admin_user_sanction_id_foreign` (`admin_user_sanction_id`),
  KEY `staff_sanction_appeals_user_id_foreign` (`user_id`),
  KEY `staff_sanction_appeals_assigned_staff_id_foreign` (`assigned_staff_id`),
  KEY `staff_sanction_appeals_reviewed_by_staff_id_foreign` (`reviewed_by_staff_id`),
  KEY `staff_sanction_appeals_escalated_to_admin_id_foreign` (`escalated_to_admin_id`),
  KEY `staff_sanction_appeals_status_index` (`status`),
  CONSTRAINT `staff_sanction_appeals_admin_user_sanction_id_foreign` FOREIGN KEY (`admin_user_sanction_id`) REFERENCES `admin_user_sanctions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_sanction_appeals_assigned_staff_id_foreign` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_sanction_appeals_escalated_to_admin_id_foreign` FOREIGN KEY (`escalated_to_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_sanction_appeals_reviewed_by_staff_id_foreign` FOREIGN KEY (`reviewed_by_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_sanction_appeals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_sanction_appeals`
--

LOCK TABLES `staff_sanction_appeals` WRITE;
/*!40000 ALTER TABLE `staff_sanction_appeals` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_sanction_appeals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_session_logs`
--

DROP TABLE IF EXISTS `staff_session_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_session_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned NOT NULL,
  `login_at` timestamp NOT NULL,
  `logout_at` timestamp NULL DEFAULT NULL,
  `duration_seconds` int unsigned NOT NULL DEFAULT '0',
  `active_seconds` int unsigned NOT NULL DEFAULT '0',
  `idle_seconds` int unsigned NOT NULL DEFAULT '0',
  `actions_count` smallint unsigned NOT NULL DEFAULT '0',
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_session_logs_staff_user_id_login_at_index` (`staff_user_id`,`login_at`),
  CONSTRAINT `staff_session_logs_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_session_logs`
--

LOCK TABLES `staff_session_logs` WRITE;
/*!40000 ALTER TABLE `staff_session_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_session_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_team_chat_messages`
--

DROP TABLE IF EXISTS `staff_team_chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_team_chat_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_team_chat_room_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `attachments` json DEFAULT NULL,
  `mentions` json DEFAULT NULL,
  `is_official_guidance` tinyint(1) NOT NULL DEFAULT '0',
  `removed_by_admin_id` bigint unsigned DEFAULT NULL,
  `removed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_team_chat_messages_user_id_foreign` (`user_id`),
  KEY `staff_team_chat_messages_removed_by_admin_id_foreign` (`removed_by_admin_id`),
  KEY `staff_chat_room_created_idx` (`staff_team_chat_room_id`,`created_at`),
  CONSTRAINT `staff_team_chat_messages_removed_by_admin_id_foreign` FOREIGN KEY (`removed_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_team_chat_messages_staff_team_chat_room_id_foreign` FOREIGN KEY (`staff_team_chat_room_id`) REFERENCES `staff_team_chat_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_team_chat_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_team_chat_messages`
--

LOCK TABLES `staff_team_chat_messages` WRITE;
/*!40000 ALTER TABLE `staff_team_chat_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_team_chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_team_chat_pins`
--

DROP TABLE IF EXISTS `staff_team_chat_pins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_team_chat_pins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_team_chat_room_id` bigint unsigned NOT NULL,
  `staff_team_chat_message_id` bigint unsigned NOT NULL,
  `pinned_by_admin_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_team_chat_pins_staff_team_chat_room_id_foreign` (`staff_team_chat_room_id`),
  KEY `staff_team_chat_pins_staff_team_chat_message_id_foreign` (`staff_team_chat_message_id`),
  KEY `staff_team_chat_pins_pinned_by_admin_id_foreign` (`pinned_by_admin_id`),
  CONSTRAINT `staff_team_chat_pins_pinned_by_admin_id_foreign` FOREIGN KEY (`pinned_by_admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_team_chat_pins_staff_team_chat_message_id_foreign` FOREIGN KEY (`staff_team_chat_message_id`) REFERENCES `staff_team_chat_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_team_chat_pins_staff_team_chat_room_id_foreign` FOREIGN KEY (`staff_team_chat_room_id`) REFERENCES `staff_team_chat_rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_team_chat_pins`
--

LOCK TABLES `staff_team_chat_pins` WRITE;
/*!40000 ALTER TABLE `staff_team_chat_pins` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_team_chat_pins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_team_chat_reactions`
--

DROP TABLE IF EXISTS `staff_team_chat_reactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_team_chat_reactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_team_chat_message_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `emoji` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_chat_reaction_unique` (`staff_team_chat_message_id`,`user_id`,`emoji`),
  KEY `staff_team_chat_reactions_user_id_foreign` (`user_id`),
  CONSTRAINT `staff_team_chat_reactions_staff_team_chat_message_id_foreign` FOREIGN KEY (`staff_team_chat_message_id`) REFERENCES `staff_team_chat_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_team_chat_reactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_team_chat_reactions`
--

LOCK TABLES `staff_team_chat_reactions` WRITE;
/*!40000 ALTER TABLE `staff_team_chat_reactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_team_chat_reactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_team_chat_reads`
--

DROP TABLE IF EXISTS `staff_team_chat_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_team_chat_reads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_team_chat_message_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `read_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_chat_read_unique` (`staff_team_chat_message_id`,`user_id`),
  KEY `staff_team_chat_reads_user_id_foreign` (`user_id`),
  CONSTRAINT `staff_team_chat_reads_staff_team_chat_message_id_foreign` FOREIGN KEY (`staff_team_chat_message_id`) REFERENCES `staff_team_chat_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_team_chat_reads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_team_chat_reads`
--

LOCK TABLES `staff_team_chat_reads` WRITE;
/*!40000 ALTER TABLE `staff_team_chat_reads` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_team_chat_reads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_team_chat_rooms`
--

DROP TABLE IF EXISTS `staff_team_chat_rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_team_chat_rooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'global',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_team_chat_rooms_slug_unique` (`slug`),
  KEY `staff_team_chat_rooms_type_index` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_team_chat_rooms`
--

LOCK TABLES `staff_team_chat_rooms` WRITE;
/*!40000 ALTER TABLE `staff_team_chat_rooms` DISABLE KEYS */;
INSERT INTO `staff_team_chat_rooms` VALUES (1,'global','Operations team','global','2026-05-31 18:26:44','2026-05-31 18:26:44');
/*!40000 ALTER TABLE `staff_team_chat_rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_watchlist_feed_events`
--

DROP TABLE IF EXISTS `staff_watchlist_feed_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_watchlist_feed_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `watched_user_id` bigint unsigned NOT NULL,
  `staff_watchlist_item_id` bigint unsigned DEFAULT NULL,
  `event_type` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'observe',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `entity_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `action_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_watchlist_feed_events_staff_watchlist_item_id_foreign` (`staff_watchlist_item_id`),
  KEY `staff_watchlist_feed_events_watched_user_id_occurred_at_index` (`watched_user_id`,`occurred_at`),
  KEY `staff_watchlist_feed_events_event_type_index` (`event_type`),
  KEY `staff_watchlist_feed_events_severity_index` (`severity`),
  KEY `staff_watchlist_feed_events_occurred_at_index` (`occurred_at`),
  CONSTRAINT `staff_watchlist_feed_events_staff_watchlist_item_id_foreign` FOREIGN KEY (`staff_watchlist_item_id`) REFERENCES `staff_watchlist_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_watchlist_feed_events_watched_user_id_foreign` FOREIGN KEY (`watched_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_watchlist_feed_events`
--

LOCK TABLES `staff_watchlist_feed_events` WRITE;
/*!40000 ALTER TABLE `staff_watchlist_feed_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_watchlist_feed_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_watchlist_items`
--

DROP TABLE IF EXISTS `staff_watchlist_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_watchlist_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_user_id` bigint unsigned NOT NULL,
  `visibility` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'personal',
  `watchable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `watchable_id` bigint unsigned NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review_by_date` date DEFAULT NULL,
  `severity` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'observe',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `priority` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_watchlist_unique` (`staff_user_id`,`watchable_type`,`watchable_id`),
  UNIQUE KEY `staff_watchlist_user_unique` (`staff_user_id`,`watchable_type`,`watchable_id`,`visibility`),
  KEY `staff_watchlist_items_watchable_type_watchable_id_index` (`watchable_type`,`watchable_id`),
  KEY `staff_watchlist_items_priority_index` (`priority`),
  KEY `staff_watchlist_items_visibility_index` (`visibility`),
  KEY `staff_watchlist_items_review_by_date_index` (`review_by_date`),
  KEY `staff_watchlist_items_severity_index` (`severity`),
  CONSTRAINT `staff_watchlist_items_staff_user_id_foreign` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_watchlist_items`
--

LOCK TABLES `staff_watchlist_items` WRITE;
/*!40000 ALTER TABLE `staff_watchlist_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_watchlist_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `states`
--

DROP TABLE IF EXISTS `states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `states` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `states_code_unique` (`code`),
  UNIQUE KEY `states_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `states`
--

LOCK TABLES `states` WRITE;
/*!40000 ALTER TABLE `states` DISABLE KEYS */;
INSERT INTO `states` VALUES (1,'FC','FCT','2026-05-31 19:20:17','2026-05-31 19:20:17'),(2,'AB','Abia','2026-05-31 19:20:17','2026-05-31 19:20:17'),(3,'AD','Adamawa','2026-05-31 19:20:17','2026-05-31 19:20:17'),(4,'AK','Akwa Ibom','2026-05-31 19:20:17','2026-05-31 19:20:17'),(5,'AN','Anambra','2026-05-31 19:20:17','2026-05-31 19:20:17'),(6,'BA','Bauchi','2026-05-31 19:20:17','2026-05-31 19:20:17'),(7,'BY','Bayelsa','2026-05-31 19:20:17','2026-05-31 19:20:17'),(8,'BE','Benue','2026-05-31 19:20:17','2026-05-31 19:20:17'),(9,'BO','Borno','2026-05-31 19:20:18','2026-05-31 19:20:18'),(10,'CR','Cross River','2026-05-31 19:20:18','2026-05-31 19:20:18'),(11,'DE','Delta','2026-05-31 19:20:18','2026-05-31 19:20:18'),(12,'EB','Ebonyi','2026-05-31 19:20:18','2026-05-31 19:20:18'),(13,'ED','Edo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(14,'EK','Ekiti','2026-05-31 19:20:18','2026-05-31 19:20:18'),(15,'EN','Enugu','2026-05-31 19:20:18','2026-05-31 19:20:18'),(16,'GO','Gombe','2026-05-31 19:20:18','2026-05-31 19:20:18'),(17,'IM','Imo','2026-05-31 19:20:18','2026-05-31 19:20:18'),(18,'JI','Jigawa','2026-05-31 19:20:18','2026-05-31 19:20:18'),(19,'KD','Kaduna','2026-05-31 19:20:19','2026-05-31 19:20:19'),(20,'KN','Kano','2026-05-31 19:20:19','2026-05-31 19:20:19'),(21,'KT','Katsina','2026-05-31 19:20:19','2026-05-31 19:20:19'),(22,'KE','Kebbi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(23,'KO','Kogi','2026-05-31 19:20:19','2026-05-31 19:20:19'),(24,'KW','Kwara','2026-05-31 19:20:19','2026-05-31 19:20:19'),(25,'LA','Lagos','2026-05-31 19:20:19','2026-05-31 19:20:19'),(26,'NA','Nasarawa','2026-05-31 19:20:19','2026-05-31 19:20:19'),(27,'NI','Niger','2026-05-31 19:20:19','2026-05-31 19:20:19'),(28,'OG','Ogun','2026-05-31 19:20:20','2026-05-31 19:20:20'),(29,'ON','Ondo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(30,'OS','Osun','2026-05-31 19:20:20','2026-05-31 19:20:20'),(31,'OY','Oyo','2026-05-31 19:20:20','2026-05-31 19:20:20'),(32,'PL','Plateau','2026-05-31 19:20:20','2026-05-31 19:20:20'),(33,'RI','Rivers','2026-05-31 19:20:20','2026-05-31 19:20:20'),(34,'SO','Sokoto','2026-05-31 19:20:20','2026-05-31 19:20:20'),(35,'TA','Taraba','2026-05-31 19:20:20','2026-05-31 19:20:20'),(36,'YO','Yobe','2026-05-31 19:20:20','2026-05-31 19:20:20'),(37,'ZA','Zamfara','2026-05-31 19:20:20','2026-05-31 19:20:20');
/*!40000 ALTER TABLE `states` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_chat_assignments`
--

DROP TABLE IF EXISTS `support_chat_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_chat_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quest_conversation_thread_id` bigint unsigned DEFAULT NULL,
  `assigned_admin_id` bigint unsigned NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_chat_assignments_quest_conversation_thread_id_foreign` (`quest_conversation_thread_id`),
  KEY `support_chat_assignments_assigned_admin_id_foreign` (`assigned_admin_id`),
  KEY `support_chat_assignments_status_index` (`status`),
  CONSTRAINT `support_chat_assignments_assigned_admin_id_foreign` FOREIGN KEY (`assigned_admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_chat_assignments_quest_conversation_thread_id_foreign` FOREIGN KEY (`quest_conversation_thread_id`) REFERENCES `quest_conversation_threads` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_chat_assignments`
--

LOCK TABLES `support_chat_assignments` WRITE;
/*!40000 ALTER TABLE `support_chat_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_chat_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_ticket_activities`
--

DROP TABLE IF EXISTS `support_ticket_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_ticket_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint unsigned NOT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `actor_role` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_activities_actor_user_id_foreign` (`actor_user_id`),
  KEY `support_ticket_activities_support_ticket_id_occurred_at_index` (`support_ticket_id`,`occurred_at`),
  CONSTRAINT `support_ticket_activities_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_ticket_activities_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_ticket_activities`
--

LOCK TABLES `support_ticket_activities` WRITE;
/*!40000 ALTER TABLE `support_ticket_activities` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_ticket_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_ticket_email_logs`
--

DROP TABLE IF EXISTS `support_ticket_email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_ticket_email_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint unsigned NOT NULL,
  `recipient_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `sent_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_email_logs_support_ticket_id_foreign` (`support_ticket_id`),
  CONSTRAINT `support_ticket_email_logs_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_ticket_email_logs`
--

LOCK TABLES `support_ticket_email_logs` WRITE;
/*!40000 ALTER TABLE `support_ticket_email_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_ticket_email_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_ticket_handoffs`
--

DROP TABLE IF EXISTS `support_ticket_handoffs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_ticket_handoffs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint unsigned NOT NULL,
  `from_admin_id` bigint unsigned DEFAULT NULL,
  `to_admin_id` bigint unsigned NOT NULL,
  `reassigned_by_id` bigint unsigned DEFAULT NULL,
  `handoff_message_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_handoffs_from_admin_id_foreign` (`from_admin_id`),
  KEY `support_ticket_handoffs_to_admin_id_foreign` (`to_admin_id`),
  KEY `support_ticket_handoffs_reassigned_by_id_foreign` (`reassigned_by_id`),
  KEY `support_ticket_handoffs_support_ticket_id_from_admin_id_index` (`support_ticket_id`,`from_admin_id`),
  CONSTRAINT `support_ticket_handoffs_from_admin_id_foreign` FOREIGN KEY (`from_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_ticket_handoffs_reassigned_by_id_foreign` FOREIGN KEY (`reassigned_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_ticket_handoffs_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_ticket_handoffs_to_admin_id_foreign` FOREIGN KEY (`to_admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_ticket_handoffs`
--

LOCK TABLES `support_ticket_handoffs` WRITE;
/*!40000 ALTER TABLE `support_ticket_handoffs` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_ticket_handoffs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_ticket_issue_groups`
--

DROP TABLE IF EXISTS `support_ticket_issue_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_ticket_issue_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_ticket_issue_groups_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_ticket_issue_groups`
--

LOCK TABLES `support_ticket_issue_groups` WRITE;
/*!40000 ALTER TABLE `support_ticket_issue_groups` DISABLE KEYS */;
INSERT INTO `support_ticket_issue_groups` VALUES (1,'account_verification','Account & Verification',NULL,10,1,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(2,'payments_escrow','Payments & Escrow',NULL,20,1,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(3,'disputes_contracts','Disputes & Contracts',NULL,30,1,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(4,'technical_issues','Technical Issues',NULL,40,1,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(5,'fraud_security','Fraud & Security',NULL,50,1,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(6,'quest_proposals','Quest & Proposals',NULL,60,1,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(7,'reviews_ratings','Reviews & Ratings',NULL,70,1,'2026-05-31 19:20:21','2026-05-31 19:20:21'),(8,'general_enquiries','General Enquiries',NULL,80,1,'2026-05-31 19:20:21','2026-05-31 19:20:21');
/*!40000 ALTER TABLE `support_ticket_issue_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_ticket_messages`
--

DROP TABLE IF EXISTS `support_ticket_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_ticket_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint unsigned NOT NULL,
  `sender_user_id` bigint unsigned DEFAULT NULL,
  `sender_type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `visibility` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_messages_sender_user_id_foreign` (`sender_user_id`),
  KEY `support_ticket_messages_sender_type_index` (`sender_type`),
  KEY `support_ticket_messages_visibility_index` (`visibility`),
  KEY `support_ticket_messages_ticket_id_id_index` (`support_ticket_id`,`id`),
  KEY `support_ticket_messages_ticket_sender_type_id_index` (`support_ticket_id`,`sender_type`,`id`),
  KEY `support_ticket_messages_ticket_visibility_id_index` (`support_ticket_id`,`visibility`,`id`),
  CONSTRAINT `support_ticket_messages_sender_user_id_foreign` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_ticket_messages_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_ticket_messages`
--

LOCK TABLES `support_ticket_messages` WRITE;
/*!40000 ALTER TABLE `support_ticket_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_ticket_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_tickets`
--

DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_reference` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `customer_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_full_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quest_conversation_thread_id` bigint unsigned DEFAULT NULL,
  `opened_by_admin_id` bigint unsigned DEFAULT NULL,
  `assigned_admin_id` bigint unsigned DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `issue_group` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `chat_status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `description` text COLLATE utf8mb4_unicode_ci,
  `internal_notes` text COLLATE utf8mb4_unicode_ci,
  `action_items` json DEFAULT NULL,
  `merged_into_support_ticket_id` bigint unsigned DEFAULT NULL,
  `resolution_summary` text COLLATE utf8mb4_unicode_ci,
  `opened_at` timestamp NULL DEFAULT NULL,
  `queued_at` timestamp NULL DEFAULT NULL,
  `in_progress_at` timestamp NULL DEFAULT NULL,
  `expected_resolution_at` timestamp NULL DEFAULT NULL,
  `sla_breached` tinyint(1) NOT NULL DEFAULT '0',
  `sla_overdue_at` timestamp NULL DEFAULT NULL,
  `sla_override_reason` text COLLATE utf8mb4_unicode_ci,
  `sla_override_by_user_id` bigint unsigned DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `last_user_activity_at` timestamp NULL DEFAULT NULL,
  `last_admin_activity_at` timestamp NULL DEFAULT NULL,
  `user_last_read_message_id` bigint unsigned DEFAULT NULL,
  `admin_last_read_message_id` bigint unsigned DEFAULT NULL,
  `rating_stars` tinyint unsigned DEFAULT NULL,
  `rating_score` tinyint unsigned DEFAULT NULL,
  `rating_reaction` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating_comment` text COLLATE utf8mb4_unicode_ci,
  `feedback_answers` json DEFAULT NULL,
  `rated_at` timestamp NULL DEFAULT NULL,
  `rating_email_sent_at` timestamp NULL DEFAULT NULL,
  `resolution_seconds` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_tickets_uuid_unique` (`uuid`),
  UNIQUE KEY `support_tickets_ticket_reference_unique` (`ticket_reference`),
  KEY `support_tickets_quest_conversation_thread_id_foreign` (`quest_conversation_thread_id`),
  KEY `support_tickets_opened_by_admin_id_foreign` (`opened_by_admin_id`),
  KEY `support_tickets_category_index` (`category`),
  KEY `support_tickets_priority_index` (`priority`),
  KEY `support_tickets_status_index` (`status`),
  KEY `support_tickets_assigned_admin_id_index` (`assigned_admin_id`),
  KEY `support_tickets_user_id_index` (`user_id`),
  KEY `support_tickets_sla_override_by_user_id_foreign` (`sla_override_by_user_id`),
  KEY `support_tickets_merged_into_support_ticket_id_foreign` (`merged_into_support_ticket_id`),
  KEY `support_tickets_chat_status_index` (`chat_status`),
  KEY `support_tickets_queued_at_index` (`queued_at`),
  KEY `support_tickets_last_activity_at_index` (`last_activity_at`),
  KEY `support_tickets_rating_email_sent_at_index` (`rating_email_sent_at`),
  CONSTRAINT `support_tickets_assigned_admin_id_foreign` FOREIGN KEY (`assigned_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_merged_into_support_ticket_id_foreign` FOREIGN KEY (`merged_into_support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_opened_by_admin_id_foreign` FOREIGN KEY (`opened_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_quest_conversation_thread_id_foreign` FOREIGN KEY (`quest_conversation_thread_id`) REFERENCES `quest_conversation_threads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_sla_override_by_user_id_foreign` FOREIGN KEY (`sla_override_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_tickets`
--

LOCK TABLES `support_tickets` WRITE;
/*!40000 ALTER TABLE `support_tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_delivery_metrics`
--

DROP TABLE IF EXISTS `user_delivery_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_delivery_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `on_time_completed_count` smallint unsigned NOT NULL DEFAULT '0',
  `total_completed_count` smallint unsigned NOT NULL DEFAULT '0',
  `reliability_score` decimal(5,2) DEFAULT NULL,
  `low_reliability_flagged` tinyint(1) NOT NULL DEFAULT '0',
  `extension_pattern_flagged` tinyint(1) NOT NULL DEFAULT '0',
  `calculated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_delivery_metrics_user_id_unique` (`user_id`),
  CONSTRAINT `user_delivery_metrics_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_delivery_metrics`
--

LOCK TABLES `user_delivery_metrics` WRITE;
/*!40000 ALTER TABLE `user_delivery_metrics` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_delivery_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_follows`
--

DROP TABLE IF EXISTS `user_follows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_follows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `follower_id` bigint unsigned NOT NULL,
  `following_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_follows_follower_id_following_id_unique` (`follower_id`,`following_id`),
  KEY `user_follows_following_id_created_at_index` (`following_id`,`created_at`),
  CONSTRAINT `user_follows_follower_id_foreign` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_follows_following_id_foreign` FOREIGN KEY (`following_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_follows`
--

LOCK TABLES `user_follows` WRITE;
/*!40000 ALTER TABLE `user_follows` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_follows` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_identity_documents`
--

DROP TABLE IF EXISTS `user_identity_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_identity_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `document_kind` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_last4` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_identity_documents_kind_hash_unique` (`document_kind`,`number_hash`),
  KEY `user_identity_documents_user_id_document_kind_index` (`user_id`,`document_kind`),
  CONSTRAINT `user_identity_documents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_identity_documents`
--

LOCK TABLES `user_identity_documents` WRITE;
/*!40000 ALTER TABLE `user_identity_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_identity_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_referrals`
--

DROP TABLE IF EXISTS `user_referrals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_referrals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `referrer_user_id` bigint unsigned NOT NULL,
  `referred_user_id` bigint unsigned NOT NULL,
  `referral_code` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'signed_up',
  `qualifying_event` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qualified_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_fingerprint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_referrals_referred_user_id_unique` (`referred_user_id`),
  KEY `user_referrals_referrer_user_id_foreign` (`referrer_user_id`),
  KEY `user_referrals_referral_code_index` (`referral_code`),
  KEY `user_referrals_status_index` (`status`),
  CONSTRAINT `user_referrals_referred_user_id_foreign` FOREIGN KEY (`referred_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_referrals_referrer_user_id_foreign` FOREIGN KEY (`referrer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_referrals`
--

LOCK TABLES `user_referrals` WRITE;
/*!40000 ALTER TABLE `user_referrals` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_referrals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_risk_network_notes`
--

DROP TABLE IF EXISTS `user_risk_network_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_risk_network_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subject_user_id` bigint unsigned NOT NULL,
  `author_user_id` bigint unsigned NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_risk_network_notes_author_user_id_foreign` (`author_user_id`),
  KEY `user_risk_network_notes_subject_user_id_created_at_index` (`subject_user_id`,`created_at`),
  CONSTRAINT `user_risk_network_notes_author_user_id_foreign` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_risk_network_notes_subject_user_id_foreign` FOREIGN KEY (`subject_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_risk_network_notes`
--

LOCK TABLES `user_risk_network_notes` WRITE;
/*!40000 ALTER TABLE `user_risk_network_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_risk_network_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_risk_profiles`
--

DROP TABLE IF EXISTS `user_risk_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_risk_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `composite_score` tinyint unsigned NOT NULL DEFAULT '0',
  `tier` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'low',
  `breakdown` json DEFAULT NULL,
  `signals` json DEFAULT NULL,
  `in_risk_queue` tinyint(1) NOT NULL DEFAULT '0',
  `queued_at` timestamp NULL DEFAULT NULL,
  `calculated_at` timestamp NULL DEFAULT NULL,
  `previous_score` tinyint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_risk_profiles_user_id_unique` (`user_id`),
  KEY `user_risk_profiles_composite_score_index` (`composite_score`),
  KEY `user_risk_profiles_tier_index` (`tier`),
  KEY `user_risk_profiles_in_risk_queue_index` (`in_risk_queue`),
  CONSTRAINT `user_risk_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_risk_profiles`
--

LOCK TABLES `user_risk_profiles` WRITE;
/*!40000 ALTER TABLE `user_risk_profiles` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_risk_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_trust_metrics`
--

DROP TABLE IF EXISTS `user_trust_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_trust_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `freelancer_trust_score` smallint unsigned NOT NULL DEFAULT '0',
  `client_trust_score` smallint unsigned NOT NULL DEFAULT '50',
  `reliability_penalty_points` smallint unsigned NOT NULL DEFAULT '0',
  `shortlisted_withdrawal_count` smallint unsigned NOT NULL DEFAULT '0',
  `client_proposal_ghost_strikes` smallint unsigned NOT NULL DEFAULT '0',
  `client_quest_posting_flagged` tinyint(1) NOT NULL DEFAULT '0',
  `profile_completion_percent` tinyint unsigned NOT NULL DEFAULT '0',
  `avg_rating_as_freelancer` decimal(3,2) DEFAULT NULL,
  `avg_rating_as_client` decimal(3,2) DEFAULT NULL,
  `ratings_count_as_freelancer` int unsigned NOT NULL DEFAULT '0',
  `ratings_count_as_client` int unsigned NOT NULL DEFAULT '0',
  `last_recomputed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_trust_metrics_user_id_unique` (`user_id`),
  CONSTRAINT `user_trust_metrics_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_trust_metrics`
--

LOCK TABLES `user_trust_metrics` WRITE;
/*!40000 ALTER TABLE `user_trust_metrics` DISABLE KEYS */;
INSERT INTO `user_trust_metrics` VALUES (1,1,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:44','2026-05-31 19:37:44'),(2,2,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:46','2026-05-31 19:37:46'),(3,3,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:47','2026-05-31 19:37:47'),(4,4,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:47','2026-05-31 19:37:47'),(5,5,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:48','2026-05-31 19:37:48'),(6,6,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:49','2026-05-31 19:37:49'),(7,7,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:50','2026-05-31 19:37:50'),(8,8,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:51','2026-05-31 19:37:51'),(9,9,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:51','2026-05-31 19:37:51'),(10,10,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:52','2026-05-31 19:37:52'),(11,11,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:52','2026-05-31 19:37:52'),(12,12,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:53','2026-05-31 19:37:53'),(13,13,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:53','2026-05-31 19:37:53'),(14,14,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:55','2026-05-31 19:37:55'),(15,15,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:56','2026-05-31 19:37:56'),(16,16,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:57','2026-05-31 19:37:57'),(17,17,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:58','2026-05-31 19:37:58'),(18,18,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:59','2026-05-31 19:37:59'),(19,19,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:37:59','2026-05-31 19:37:59'),(20,20,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:38:00','2026-05-31 19:38:00'),(21,21,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:38:01','2026-05-31 19:38:01'),(22,22,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:38:03','2026-05-31 19:38:03'),(23,23,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:38:05','2026-05-31 19:38:05'),(24,24,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:38:07','2026-05-31 19:38:07'),(25,25,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:38:09','2026-05-31 19:38:09'),(26,26,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:38:11','2026-05-31 19:38:11'),(27,27,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:38:13','2026-05-31 19:38:13'),(28,28,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:38:13','2026-05-31 19:38:13'),(29,29,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:38:14','2026-05-31 19:38:14'),(30,30,0,50,0,0,0,0,0,NULL,NULL,0,0,NULL,'2026-05-31 19:38:15','2026-05-31 19:38:15');
/*!40000 ALTER TABLE `user_trust_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_verifications`
--

DROP TABLE IF EXISTS `user_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `submitted_by` bigint unsigned DEFAULT NULL,
  `category` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `verification_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `freelancer_credential_id` bigint unsigned DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `document_paths` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `encrypted_identifier` text COLLATE utf8mb4_unicode_ci,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `assigned_staff_id` bigint unsigned DEFAULT NULL,
  `staff_assigned_at` timestamp NULL DEFAULT NULL,
  `referred_to_admin_id` bigint unsigned DEFAULT NULL,
  `referred_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `decision_reason_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision_reason_note` text COLLATE utf8mb4_unicode_ci,
  `admin_concern` text COLLATE utf8mb4_unicode_ci,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `target_tier` tinyint unsigned DEFAULT NULL,
  `provider` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_response` json DEFAULT NULL,
  `confidence_score` tinyint unsigned DEFAULT NULL,
  `queue_reason` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempt_count` tinyint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `user_verifications_freelancer_credential_id_foreign` (`freelancer_credential_id`),
  KEY `user_verifications_reviewed_by_foreign` (`reviewed_by`),
  KEY `user_verifications_user_id_category_status_index` (`user_id`,`category`,`status`),
  KEY `user_verifications_category_index` (`category`),
  KEY `user_verifications_status_index` (`status`),
  KEY `user_verifications_target_tier_index` (`target_tier`),
  KEY `user_verifications_queue_reason_index` (`queue_reason`),
  KEY `user_verifications_submitted_by_foreign` (`submitted_by`),
  KEY `user_verifications_verification_type_index` (`verification_type`),
  KEY `user_verifications_referred_to_admin_id_foreign` (`referred_to_admin_id`),
  KEY `user_verifications_assigned_staff_id_foreign` (`assigned_staff_id`),
  CONSTRAINT `user_verifications_assigned_staff_id_foreign` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_verifications_freelancer_credential_id_foreign` FOREIGN KEY (`freelancer_credential_id`) REFERENCES `freelancer_credentials` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_verifications_referred_to_admin_id_foreign` FOREIGN KEY (`referred_to_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_verifications_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_verifications_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_verifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_verifications`
--

LOCK TABLES `user_verifications` WRITE;
/*!40000 ALTER TABLE `user_verifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_verifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uid` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referral_code` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referred_by_user_id` bigint unsigned DEFAULT NULL,
  `referral_program_blocked_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nin` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bvn` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` bigint unsigned DEFAULT NULL,
  `local_government_id` bigint unsigned DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `geocoded_at` timestamp NULL DEFAULT NULL,
  `account_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_id` bigint unsigned DEFAULT NULL,
  `profession` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `headline` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hourly_rate_min` decimal(12,2) DEFAULT NULL,
  `hourly_rate_max` decimal(12,2) DEFAULT NULL,
  `years_experience` tinyint unsigned DEFAULT NULL,
  `availability` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `power_hours` json DEFAULT NULL,
  `verification_tier` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_time_hours` smallint unsigned DEFAULT NULL,
  `job_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_size` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT 'Africa/Lagos',
  `locale` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `onboarding_step` tinyint unsigned NOT NULL DEFAULT '0',
  `last_active_at` timestamp NULL DEFAULT NULL,
  `hide_online_presence` tinyint(1) NOT NULL DEFAULT '0',
  `suspended_at` timestamp NULL DEFAULT NULL,
  `deactivated_at` timestamp NULL DEFAULT NULL,
  `under_review_at` timestamp NULL DEFAULT NULL,
  `banned_at` timestamp NULL DEFAULT NULL,
  `ban_reason` text COLLATE utf8mb4_unicode_ci,
  `google_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `public_profile_settings` json DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `freelancer_last_setup_reminder_at` timestamp NULL DEFAULT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `kyc_tier` tinyint unsigned NOT NULL DEFAULT '0',
  `current_verification_level` tinyint unsigned NOT NULL DEFAULT '0',
  `verification_level_override` tinyint unsigned DEFAULT NULL,
  `verification_level_override_reason` text COLLATE utf8mb4_unicode_ci,
  `verification_level_overridden_by` bigint unsigned DEFAULT NULL,
  `verification_level_overridden_at` timestamp NULL DEFAULT NULL,
  `custom_client_post_limit_minor` bigint unsigned DEFAULT NULL,
  `custom_freelancer_proposal_limit_minor` bigint unsigned DEFAULT NULL,
  `verification_restricted_at` timestamp NULL DEFAULT NULL,
  `verification_restriction_reason` text COLLATE utf8mb4_unicode_ci,
  `kyc_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unverified',
  `kyc_verified_at` timestamp NULL DEFAULT NULL,
  `operations_staff_invited_at` timestamp NULL DEFAULT NULL,
  `operations_staff_invited_by` bigint unsigned DEFAULT NULL,
  `operations_staff_password_set_at` timestamp NULL DEFAULT NULL,
  `disputes_lost_count` smallint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_google_id_unique` (`google_id`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_slug_unique` (`slug`),
  UNIQUE KEY `users_uid_unique` (`uid`),
  UNIQUE KEY `users_referral_code_unique` (`referral_code`),
  KEY `users_role_id_foreign` (`role_id`),
  KEY `users_state_id_foreign` (`state_id`),
  KEY `users_local_government_id_foreign` (`local_government_id`),
  KEY `users_nin_index` (`nin`),
  KEY `users_bvn_index` (`bvn`),
  KEY `users_under_review_at_index` (`under_review_at`),
  KEY `users_banned_at_index` (`banned_at`),
  KEY `users_phone_verified_at_index` (`phone_verified_at`),
  KEY `users_kyc_tier_index` (`kyc_tier`),
  KEY `users_kyc_status_index` (`kyc_status`),
  KEY `users_referred_by_user_id_foreign` (`referred_by_user_id`),
  KEY `users_referral_program_blocked_at_index` (`referral_program_blocked_at`),
  KEY `users_operations_staff_invited_by_foreign` (`operations_staff_invited_by`),
  KEY `users_verification_level_overridden_by_foreign` (`verification_level_overridden_by`),
  KEY `users_current_verification_level_index` (`current_verification_level`),
  KEY `users_verification_level_override_index` (`verification_level_override`),
  KEY `users_verification_restricted_at_index` (`verification_restricted_at`),
  CONSTRAINT `users_local_government_id_foreign` FOREIGN KEY (`local_government_id`) REFERENCES `local_governments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_operations_staff_invited_by_foreign` FOREIGN KEY (`operations_staff_invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_referred_by_user_id_foreign` FOREIGN KEY (`referred_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `users_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_verification_level_overridden_by_foreign` FOREIGN KEY (`verification_level_overridden_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'fake_freelancer_01','fake-freelancer-01','FF000001','7AQITACB',NULL,NULL,'Golda Kiehn','Golda','Kiehn','fake.freelancer01@hustlesafe.test','08027710347',NULL,NULL,'male','2004-12-09',NULL,'44995 Heidenreich Stream Apt. 206','Magumeri',9,168,NULL,NULL,NULL,'hustler',3,'Maintenance Specialist','Experienced Maintenance Specialist focused on reliable delivery, clear communication, and safe work practices.','Maintenance Specialist available for verified HustleSafe quests',376427.00,1158748.00,10,'evenings',NULL,'premium',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:44','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','21reaKrkHJ','2026-05-31 19:37:44','2026-05-31 19:37:44',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(2,'fake_freelancer_02','fake-freelancer-02','FF000002','W2T27WPO',NULL,NULL,'Schuyler Eichmann','Schuyler','Eichmann','fake.freelancer02@hustlesafe.test','08065790162',NULL,NULL,'female','2003-07-05',NULL,'957 Hill Mountain Apt. 028','Ezeagu',15,271,NULL,NULL,NULL,'hustler',3,'Data Entry Specialist','Experienced Data Entry Specialist focused on reliable delivery, clear communication, and safe work practices.','Data Entry Specialist available for verified HustleSafe quests',660070.00,1191767.00,5,'evenings',NULL,'basic',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:46','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','9KnC2PgwlC','2026-05-31 19:37:46','2026-05-31 19:37:46',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(3,'fake_freelancer_03','fake-freelancer-03','FF000003','MTSQIWYD',NULL,NULL,'Claudie Becker','Claudie','Becker','fake.freelancer03@hustlesafe.test','08064204311',NULL,NULL,'male','2001-09-18',NULL,'7808 Earl Heights Suite 997','Guyuk',3,32,NULL,NULL,NULL,'hustler',3,'Skilled Home Service Professional','Experienced Skilled Home Service Professional focused on reliable delivery, clear communication, and safe work practices.','Skilled Home Service Professional available for verified HustleSafe quests',723925.00,1134797.00,12,'weekends',NULL,'premium',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:47','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','4zp6GCtwDC','2026-05-31 19:37:47','2026-05-31 19:37:47',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(4,'fake_freelancer_04','fake-freelancer-04','FF000004','SVXVPTEH',NULL,NULL,'Raoul Gutmann','Raoul','Gutmann','fake.freelancer04@hustlesafe.test','08076276454',NULL,NULL,'male','1997-10-23',NULL,'26043 Abshire Via Apt. 713','Akwanga',26,524,NULL,NULL,NULL,'hustler',3,'Creative Services Freelancer','Experienced Creative Services Freelancer focused on reliable delivery, clear communication, and safe work practices.','Creative Services Freelancer available for verified HustleSafe quests',309642.00,1658232.00,5,'weekends',NULL,'premium',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:47','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','kPcGj9ZQM2','2026-05-31 19:37:47','2026-05-31 19:37:47',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(5,'fake_freelancer_05','fake-freelancer-05','FF000005','2ETK7R0D',NULL,NULL,'Daniella Rowe','Daniella','Rowe','fake.freelancer05@hustlesafe.test','08051446018',NULL,NULL,'female','1993-06-05',NULL,'868 Taryn Mountains','Yenagoa',7,126,NULL,NULL,NULL,'hustler',3,'Personal Services Specialist','Experienced Personal Services Specialist focused on reliable delivery, clear communication, and safe work practices.','Personal Services Specialist available for verified HustleSafe quests',660150.00,1201480.00,7,'weekdays',NULL,'basic',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:48','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','E7JRCIJt17','2026-05-31 19:37:48','2026-05-31 19:37:48',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(6,'fake_freelancer_06','fake-freelancer-06','FF000006','VVYPJ10Z',NULL,NULL,'Marta Lind','Marta','Lind','fake.freelancer06@hustlesafe.test','08093837990',NULL,NULL,'male','1991-10-29',NULL,'218 Shad Rapids Apt. 880','Fakai',22,456,NULL,NULL,NULL,'hustler',3,'Personal Services Specialist','Experienced Personal Services Specialist focused on reliable delivery, clear communication, and safe work practices.','Personal Services Specialist available for verified HustleSafe quests',684775.00,1124786.00,12,'evenings',NULL,'basic',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:49','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','HimJLzoB38','2026-05-31 19:37:49','2026-05-31 19:37:49',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(7,'fake_freelancer_07','fake-freelancer-07','FF000007','5RGB79VK',NULL,NULL,'Elena Huel','Elena','Huel','fake.freelancer07@hustlesafe.test','08026747836',NULL,NULL,'male','1986-08-19',NULL,'6944 Kub Coves Suite 732','Orumba-South',5,98,NULL,NULL,NULL,'hustler',3,'Personal Services Specialist','Experienced Personal Services Specialist focused on reliable delivery, clear communication, and safe work practices.','Personal Services Specialist available for verified HustleSafe quests',680357.00,1297868.00,5,'flexible',NULL,'verified',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:50','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','Ry1SMDJ3Kg','2026-05-31 19:37:50','2026-05-31 19:37:50',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(8,'fake_freelancer_08','fake-freelancer-08','FF000008','WUWK5ZRL',NULL,NULL,'Lester Anderson','Lester','Anderson','fake.freelancer08@hustlesafe.test','08093081773',NULL,NULL,'male','1983-11-11',NULL,'941 Waelchi Port Apt. 032','Abeokuta-North',28,562,NULL,NULL,NULL,'hustler',3,'Creative Services Freelancer','Experienced Creative Services Freelancer focused on reliable delivery, clear communication, and safe work practices.','Creative Services Freelancer available for verified HustleSafe quests',723905.00,1424090.00,9,'weekends',NULL,'verified',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:51','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','acwIcFtRL5','2026-05-31 19:37:51','2026-05-31 19:37:51',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(9,'fake_freelancer_09','fake-freelancer-09','FF000009','ESPSZBAC',NULL,NULL,'Isidro Turcotte','Isidro','Turcotte','fake.freelancer09@hustlesafe.test','08009279644',NULL,NULL,'male','1985-06-06',NULL,'610 Watsica Shore Apt. 006','Igbo-Eze-South',15,274,NULL,NULL,NULL,'hustler',3,'Maintenance Specialist','Experienced Maintenance Specialist focused on reliable delivery, clear communication, and safe work practices.','Maintenance Specialist available for verified HustleSafe quests',717480.00,1098842.00,9,'evenings',NULL,'premium',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:51','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','iX7Pj7Asup','2026-05-31 19:37:51','2026-05-31 19:37:51',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(10,'fake_freelancer_10','fake-freelancer-10','FF000010','AA59EU1O',NULL,NULL,'Antwon Quitzon','Antwon','Quitzon','fake.freelancer10@hustlesafe.test','08066919669',NULL,NULL,'female','1981-08-05',NULL,'2269 McGlynn Parkway','Mushin',25,519,NULL,NULL,NULL,'hustler',3,'Carpenter','Experienced Carpenter focused on reliable delivery, clear communication, and safe work practices.','Carpenter available for verified HustleSafe quests',634291.00,930841.00,11,'weekdays',NULL,'premium',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:52','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','4hWvUqpd8B','2026-05-31 19:37:52','2026-05-31 19:37:52',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(11,'fake_freelancer_11','fake-freelancer-11','FF000011','PV34PROO',NULL,NULL,'Bertha Bradtke','Bertha','Bradtke','fake.freelancer11@hustlesafe.test','08041079541',NULL,NULL,'male','2003-04-01',NULL,'89972 Sanford Passage','Lamurde',3,35,NULL,NULL,NULL,'hustler',3,'Maintenance Specialist','Experienced Maintenance Specialist focused on reliable delivery, clear communication, and safe work practices.','Maintenance Specialist available for verified HustleSafe quests',746977.00,1748124.00,7,'weekdays',NULL,'basic',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:52','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','SBXAB4G3XV','2026-05-31 19:37:52','2026-05-31 19:37:52',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(12,'fake_freelancer_12','fake-freelancer-12','FF000012','5ZJGPMNV',NULL,NULL,'Wilber Feil','Wilber','Feil','fake.freelancer12@hustlesafe.test','08004755724',NULL,NULL,'male','2002-07-19',NULL,'9659 Ziemann Points','Alkaleri',6,99,NULL,NULL,NULL,'hustler',3,'Personal Services Specialist','Experienced Personal Services Specialist focused on reliable delivery, clear communication, and safe work practices.','Personal Services Specialist available for verified HustleSafe quests',765184.00,1649033.00,8,'evenings',NULL,'premium',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:53','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','fh8pRvMnu1','2026-05-31 19:37:53','2026-05-31 19:37:53',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(13,'fake_freelancer_13','fake-freelancer-13','FF000013','9Y22CZWO',NULL,NULL,'Wilhelm Wunsch','Wilhelm','Wunsch','fake.freelancer13@hustlesafe.test','08065932707',NULL,NULL,'female','1999-02-23',NULL,'490 Rodrick Loaf','Kalgo',22,459,NULL,NULL,NULL,'hustler',3,'Graphic Designer','Experienced Graphic Designer focused on reliable delivery, clear communication, and safe work practices.','Graphic Designer available for verified HustleSafe quests',393206.00,1084625.00,6,'evenings',NULL,'verified',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:53','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','HfeyAIHJuw','2026-05-31 19:37:53','2026-05-31 19:37:53',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(14,'fake_freelancer_14','fake-freelancer-14','FF000014','AWNUU4Z9',NULL,NULL,'Luther Reichert','Luther','Reichert','fake.freelancer14@hustlesafe.test','08024693766',NULL,NULL,'male','1984-02-29',NULL,'22676 Elody Falls Apt. 206','Abuja',1,1,NULL,NULL,NULL,'hustler',3,'Creative Services Freelancer','Experienced Creative Services Freelancer focused on reliable delivery, clear communication, and safe work practices.','Creative Services Freelancer available for verified HustleSafe quests',289833.00,1658624.00,8,'weekends',NULL,'premium',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:55','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','F6Aob4ZUnq','2026-05-31 19:37:55','2026-05-31 19:37:55',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(15,'fake_freelancer_15','fake-freelancer-15','FF000015','LZJAAKCC',NULL,NULL,'Virginie Kreiger','Virginie','Kreiger','fake.freelancer15@hustlesafe.test','08072962340',NULL,NULL,'female','1999-05-19',NULL,'3519 Nikolaus Divide','Obudu',10,189,NULL,NULL,NULL,'hustler',3,'Maintenance Specialist','Experienced Maintenance Specialist focused on reliable delivery, clear communication, and safe work practices.','Maintenance Specialist available for verified HustleSafe quests',554111.00,1448830.00,9,'weekdays',NULL,'verified',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:56','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','RdZbBKFc2C','2026-05-31 19:37:56','2026-05-31 19:37:56',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(16,'fake_freelancer_16','fake-freelancer-16','FF000016','BNQBDGQW',NULL,NULL,'Summer Ferry','Summer','Ferry','fake.freelancer16@hustlesafe.test','08031420907',NULL,NULL,'female','1987-02-09',NULL,'7749 Hirthe Estate','Akoko North-East',29,581,NULL,NULL,NULL,'hustler',3,'Creative Services Freelancer','Experienced Creative Services Freelancer focused on reliable delivery, clear communication, and safe work practices.','Creative Services Freelancer available for verified HustleSafe quests',699837.00,1304009.00,9,'weekdays',NULL,'basic',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:57','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','TI88JavXPG','2026-05-31 19:37:57','2026-05-31 19:37:57',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(17,'fake_freelancer_17','fake-freelancer-17','FF000017','BWY0B5ML',NULL,NULL,'Johanna Greenholt','Johanna','Greenholt','fake.freelancer17@hustlesafe.test','08089832851',NULL,NULL,'female','1994-12-04',NULL,'9733 Keeling Pike','Khana',33,692,NULL,NULL,NULL,'hustler',3,'Skilled Home Service Professional','Experienced Skilled Home Service Professional focused on reliable delivery, clear communication, and safe work practices.','Skilled Home Service Professional available for verified HustleSafe quests',743134.00,1504009.00,7,'weekdays',NULL,'verified',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:58','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','hZBWX5bmev','2026-05-31 19:37:58','2026-05-31 19:37:58',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(18,'fake_freelancer_18','fake-freelancer-18','FF000018','22ZMHO4D',NULL,NULL,'Vena Bruen','Vena','Bruen','fake.freelancer18@hustlesafe.test','08075226196',NULL,NULL,'female','2004-09-29',NULL,'27773 Ora Crescent','Ibarapa-Central',31,638,NULL,NULL,NULL,'hustler',3,'Creative Services Freelancer','Experienced Creative Services Freelancer focused on reliable delivery, clear communication, and safe work practices.','Creative Services Freelancer available for verified HustleSafe quests',360301.00,1345588.00,10,'weekends',NULL,'verified',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:59','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','0dydTiDQ26','2026-05-31 19:37:59','2026-05-31 19:37:59',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(19,'fake_freelancer_19','fake-freelancer-19','FF000019','HKJEGLKY',NULL,NULL,'Mitchell Torphy','Mitchell','Torphy','fake.freelancer19@hustlesafe.test','08057955977',NULL,NULL,'female','2001-02-09',NULL,'383 Nikolaus Port Apt. 071','Irepodun',30,617,NULL,NULL,NULL,'hustler',3,'Maintenance Specialist','Experienced Maintenance Specialist focused on reliable delivery, clear communication, and safe work practices.','Maintenance Specialist available for verified HustleSafe quests',641738.00,1470809.00,10,'weekdays',NULL,'premium',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:37:59','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','fHi9WxDmWu','2026-05-31 19:37:59','2026-05-31 19:37:59',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(20,'fake_freelancer_20','fake-freelancer-20','FF000020','E3LQCVO3',NULL,NULL,'Guido Spinka','Guido','Spinka','fake.freelancer20@hustlesafe.test','08066899016',NULL,NULL,'male','1997-06-02',NULL,'450 Rice Trafficway Apt. 589','Offa',24,500,NULL,NULL,NULL,'hustler',3,'Maintenance Specialist','Experienced Maintenance Specialist focused on reliable delivery, clear communication, and safe work practices.','Maintenance Specialist available for verified HustleSafe quests',650663.00,1106583.00,6,'weekdays',NULL,'premium',NULL,NULL,NULL,'Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:38:00','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','D7I2e1bSSv','2026-05-31 19:38:00','2026-05-31 19:38:00',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(21,'fake_client_01','fake-client-01','FC000001','SLFFFPHZ',NULL,NULL,'Estefania Weber','Estefania','Weber','fake.client01@hustlesafe.test','08159420337',NULL,NULL,'male','1983-12-11','Hammes-O\'Kon','814 Wintheiser Island','Shomolu',25,522,NULL,NULL,NULL,'sponsor',2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Home Owner','1-5','Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:38:01','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','hlNbxPSfJJ','2026-05-31 19:38:01','2026-05-31 19:38:01',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(22,'fake_client_02','fake-client-02','FC000002','J34YGKMO',NULL,NULL,'Sammy Schimmel','Sammy','Schimmel','fake.client02@hustlesafe.test','08100508096',NULL,NULL,'female','1972-12-07',NULL,'8631 Tomasa Hills Suite 352','Daura',21,423,NULL,NULL,NULL,'sponsor',2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Project Sponsor','21-50','Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:38:03','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','mrFQXtzQMj','2026-05-31 19:38:03','2026-05-31 19:38:03',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(23,'fake_client_03','fake-client-03','FC000003','VAMAZCIH',NULL,NULL,'Julio Macejkovic','Julio','Macejkovic','fake.client03@hustlesafe.test','08159235562',NULL,NULL,'male','1982-05-16',NULL,'294 Conrad Shore Apt. 715','Tafa',27,560,NULL,NULL,NULL,'sponsor',2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Facilities Manager','21-50','Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:38:05','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','OsT4pG0kyF','2026-05-31 19:38:05','2026-05-31 19:38:05',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(24,'fake_client_04','fake-client-04','FC000004','CKMWU33F',NULL,NULL,'Lizzie Koss','Lizzie','Koss','fake.client04@hustlesafe.test','08194574830',NULL,NULL,'male','1978-07-11',NULL,'33765 Blanda Flat Suite 490','Owan-West',13,248,NULL,NULL,NULL,'sponsor',2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Facilities Manager','6-20','Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:38:07','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','yOv5jHq21G','2026-05-31 19:38:07','2026-05-31 19:38:07',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(25,'fake_client_05','fake-client-05','FC000005','K13WVO0F',NULL,NULL,'Vance Lakin','Vance','Lakin','fake.client05@hustlesafe.test','08188706768',NULL,NULL,'male','1981-01-21',NULL,'9731 Donnelly Springs','Gagarawa',18,327,NULL,NULL,NULL,'sponsor',2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Operations Lead','1-5','Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:38:09','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','S0383dabe2','2026-05-31 19:38:09','2026-05-31 19:38:09',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(26,'fake_client_06','fake-client-06','FC000006','5TUKE3EJ',NULL,NULL,'Lilian Boehm','Lilian','Boehm','fake.client06@hustlesafe.test','08112576160',NULL,NULL,'female','1998-12-04','Kautzer, Watsica and Rohan','1475 Linnea Street Suite 577','Orolu',30,626,NULL,NULL,NULL,'sponsor',2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Operations Lead','21-50','Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:38:11','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','rBPKTSOy9a','2026-05-31 19:38:11','2026-05-31 19:38:11',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(27,'fake_client_07','fake-client-07','FC000007','BT0R9QG3',NULL,NULL,'Dayana Graham','Dayana','Graham','fake.client07@hustlesafe.test','08194178535',NULL,NULL,'female','1982-10-30','Quitzon, Ledner and Gottlieb','527 Kevin Pike Suite 690','Egbedore',30,607,NULL,NULL,NULL,'sponsor',2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Facilities Manager','6-20','Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:38:13','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','ORSUgfQiCU','2026-05-31 19:38:13','2026-05-31 19:38:13',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(28,'fake_client_08','fake-client-08','FC000008','5VRW8ZVR',NULL,NULL,'Piper Wolf','Piper','Wolf','fake.client08@hustlesafe.test','08122906646',NULL,NULL,'male','1979-12-13',NULL,'774 Aliyah Lights','Anambra East',5,80,NULL,NULL,NULL,'sponsor',2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Small Business Owner','1-5','Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:38:13','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','tzpexz7zsI','2026-05-31 19:38:13','2026-05-31 19:38:13',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(29,'fake_client_09','fake-client-09','FC000009','EJHVK7XC',NULL,NULL,'Okey Lindgren','Okey','Lindgren','fake.client09@hustlesafe.test','08100113489',NULL,NULL,'male','1988-05-06','Lynch LLC','5985 Heaney Islands','Chanchaga',27,542,NULL,NULL,NULL,'sponsor',2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Facilities Manager','6-20','Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:38:14','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','u4pyTFBtdp','2026-05-31 19:38:14','2026-05-31 19:38:14',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0),(30,'fake_client_10','fake-client-10','FC000010','ESBESRHT',NULL,NULL,'Cullen Luettgen','Cullen','Luettgen','fake.client10@hustlesafe.test','08156244060',NULL,NULL,'male','1974-05-25',NULL,'19254 Abshire Ville','Ekiti-South-West',14,253,NULL,NULL,NULL,'sponsor',2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Project Sponsor','1-5','Africa/Lagos','en-NG',5,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-31 19:38:15','$2y$12$h1TUvZ/KW26sE22UyEzQzeg69mK8R7qi5Z580oY3vW/xkRqiLZT42','aIbg14NJUF','2026-05-31 19:38:15','2026-05-31 19:38:15',NULL,NULL,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unverified',NULL,NULL,NULL,NULL,0);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vat_remittances`
--

DROP TABLE IF EXISTS `vat_remittances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vat_remittances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quarter_label` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `amount_minor` bigint unsigned NOT NULL,
  `remittance_reference` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remitted_at` timestamp NOT NULL,
  `recorded_by_user_id` bigint unsigned NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vat_remittances_quarter_label_remittance_reference_unique` (`quarter_label`,`remittance_reference`),
  UNIQUE KEY `vat_remittances_uuid_unique` (`uuid`),
  KEY `vat_remittances_recorded_by_user_id_foreign` (`recorded_by_user_id`),
  CONSTRAINT `vat_remittances_recorded_by_user_id_foreign` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vat_remittances`
--

LOCK TABLES `vat_remittances` WRITE;
/*!40000 ALTER TABLE `vat_remittances` DISABLE KEYS */;
/*!40000 ALTER TABLE `vat_remittances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `verification_anomaly_flags`
--

DROP TABLE IF EXISTS `verification_anomaly_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verification_anomaly_flags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `quest_id` bigint unsigned DEFAULT NULL,
  `quest_offer_id` bigint unsigned DEFAULT NULL,
  `type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `severity` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `context` json DEFAULT NULL,
  `resolved_by` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `verification_anomaly_flags_quest_id_foreign` (`quest_id`),
  KEY `verification_anomaly_flags_quest_offer_id_foreign` (`quest_offer_id`),
  KEY `verification_anomaly_flags_resolved_by_foreign` (`resolved_by`),
  KEY `verification_anomaly_flags_user_id_status_type_index` (`user_id`,`status`,`type`),
  KEY `verification_anomaly_flags_type_index` (`type`),
  KEY `verification_anomaly_flags_status_index` (`status`),
  KEY `verification_anomaly_flags_severity_index` (`severity`),
  CONSTRAINT `verification_anomaly_flags_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verification_anomaly_flags_quest_offer_id_foreign` FOREIGN KEY (`quest_offer_id`) REFERENCES `quest_offers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verification_anomaly_flags_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verification_anomaly_flags_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `verification_anomaly_flags`
--

LOCK TABLES `verification_anomaly_flags` WRITE;
/*!40000 ALTER TABLE `verification_anomaly_flags` DISABLE KEYS */;
/*!40000 ALTER TABLE `verification_anomaly_flags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `verification_engine_audit_logs`
--

DROP TABLE IF EXISTS `verification_engine_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verification_engine_audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` bigint unsigned DEFAULT NULL,
  `affected_user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `old_value` json DEFAULT NULL,
  `new_value` json DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `verification_engine_audit_logs_actor_id_foreign` (`actor_id`),
  KEY `verification_engine_audit_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `verification_engine_audit_logs_affected_user_id_created_at_index` (`affected_user_id`,`created_at`),
  KEY `verification_engine_audit_logs_action_index` (`action`),
  CONSTRAINT `verification_engine_audit_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verification_engine_audit_logs_affected_user_id_foreign` FOREIGN KEY (`affected_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `verification_engine_audit_logs`
--

LOCK TABLES `verification_engine_audit_logs` WRITE;
/*!40000 ALTER TABLE `verification_engine_audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `verification_engine_audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallet_bank_accounts`
--

DROP TABLE IF EXISTS `wallet_bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_bank_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `bank_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paystack_recipient_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallet_bank_accounts_user_id_account_number_bank_code_unique` (`user_id`,`account_number`,`bank_code`),
  KEY `wallet_bank_accounts_paystack_recipient_code_index` (`paystack_recipient_code`),
  KEY `wallet_bank_accounts_status_index` (`status`),
  CONSTRAINT `wallet_bank_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wallet_bank_accounts`
--

LOCK TABLES `wallet_bank_accounts` WRITE;
/*!40000 ALTER TABLE `wallet_bank_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `wallet_bank_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallet_transactions`
--

DROP TABLE IF EXISTS `wallet_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wallet_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_minor` bigint unsigned NOT NULL,
  `fee_minor` bigint unsigned NOT NULL DEFAULT '0',
  `balance_after_minor` bigint NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `paystack_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idempotency_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `escrow_id` bigint unsigned DEFAULT NULL,
  `quest_id` bigint unsigned DEFAULT NULL,
  `wallet_withdrawal_id` bigint unsigned DEFAULT NULL,
  `admin_user_id` bigint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallet_transactions_uuid_unique` (`uuid`),
  UNIQUE KEY `wallet_transactions_reference_unique` (`reference`),
  UNIQUE KEY `wallet_transactions_idempotency_key_unique` (`idempotency_key`),
  KEY `wallet_transactions_escrow_id_foreign` (`escrow_id`),
  KEY `wallet_transactions_quest_id_foreign` (`quest_id`),
  KEY `wallet_transactions_wallet_withdrawal_id_foreign` (`wallet_withdrawal_id`),
  KEY `wallet_transactions_admin_user_id_foreign` (`admin_user_id`),
  KEY `wallet_transactions_user_id_occurred_at_index` (`user_id`,`occurred_at`),
  KEY `wallet_transactions_wallet_id_type_index` (`wallet_id`,`type`),
  KEY `wallet_transactions_type_index` (`type`),
  KEY `wallet_transactions_direction_index` (`direction`),
  KEY `wallet_transactions_status_index` (`status`),
  KEY `wallet_transactions_paystack_reference_index` (`paystack_reference`),
  KEY `wallet_transactions_occurred_at_index` (`occurred_at`),
  CONSTRAINT `wallet_transactions_admin_user_id_foreign` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallet_transactions_escrow_id_foreign` FOREIGN KEY (`escrow_id`) REFERENCES `payment_escrows` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallet_transactions_quest_id_foreign` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallet_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wallet_transactions_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wallet_transactions_wallet_withdrawal_id_foreign` FOREIGN KEY (`wallet_withdrawal_id`) REFERENCES `wallet_withdrawals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wallet_transactions`
--

LOCK TABLES `wallet_transactions` WRITE;
/*!40000 ALTER TABLE `wallet_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `wallet_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallet_withdrawals`
--

DROP TABLE IF EXISTS `wallet_withdrawals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_withdrawals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wallet_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `wallet_bank_account_id` bigint unsigned NOT NULL,
  `amount_minor` bigint unsigned NOT NULL,
  `fee_minor` bigint unsigned NOT NULL DEFAULT '0',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paystack_transfer_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paystack_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_reason` text COLLATE utf8mb4_unicode_ci,
  `processed_at` timestamp NULL DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallet_withdrawals_uuid_unique` (`uuid`),
  UNIQUE KEY `wallet_withdrawals_reference_unique` (`reference`),
  KEY `wallet_withdrawals_wallet_id_foreign` (`wallet_id`),
  KEY `wallet_withdrawals_user_id_foreign` (`user_id`),
  KEY `wallet_withdrawals_wallet_bank_account_id_foreign` (`wallet_bank_account_id`),
  KEY `wallet_withdrawals_status_index` (`status`),
  KEY `wallet_withdrawals_paystack_transfer_code_index` (`paystack_transfer_code`),
  KEY `wallet_withdrawals_paystack_reference_index` (`paystack_reference`),
  CONSTRAINT `wallet_withdrawals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wallet_withdrawals_wallet_bank_account_id_foreign` FOREIGN KEY (`wallet_bank_account_id`) REFERENCES `wallet_bank_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wallet_withdrawals_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wallet_withdrawals`
--

LOCK TABLES `wallet_withdrawals` WRITE;
/*!40000 ALTER TABLE `wallet_withdrawals` DISABLE KEYS */;
/*!40000 ALTER TABLE `wallet_withdrawals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallets`
--

DROP TABLE IF EXISTS `wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NGN',
  `balance_minor` bigint unsigned NOT NULL DEFAULT '0',
  `pending_balance_minor` bigint unsigned NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `locked_at` timestamp NULL DEFAULT NULL,
  `lock_reason` text COLLATE utf8mb4_unicode_ci,
  `locked_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallets_user_id_unique` (`user_id`),
  KEY `wallets_locked_by_user_id_foreign` (`locked_by_user_id`),
  KEY `wallets_status_index` (`status`),
  CONSTRAINT `wallets_locked_by_user_id_foreign` FOREIGN KEY (`locked_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wallets`
--

LOCK TABLES `wallets` WRITE;
/*!40000 ALTER TABLE `wallets` DISABLE KEYS */;
INSERT INTO `wallets` VALUES (1,1,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:46','2026-05-31 19:37:46'),(2,2,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:47','2026-05-31 19:37:47'),(3,3,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:47','2026-05-31 19:37:47'),(4,4,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:48','2026-05-31 19:37:48'),(5,5,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:49','2026-05-31 19:37:49'),(6,6,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:50','2026-05-31 19:37:50'),(7,7,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:51','2026-05-31 19:37:51'),(8,8,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:51','2026-05-31 19:37:51'),(9,9,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:52','2026-05-31 19:37:52'),(10,10,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:52','2026-05-31 19:37:52'),(11,11,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:53','2026-05-31 19:37:53'),(12,12,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:53','2026-05-31 19:37:53'),(13,13,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:55','2026-05-31 19:37:55'),(14,14,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:56','2026-05-31 19:37:56'),(15,15,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:57','2026-05-31 19:37:57'),(16,16,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:58','2026-05-31 19:37:58'),(17,17,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:59','2026-05-31 19:37:59'),(18,18,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:37:59','2026-05-31 19:37:59'),(19,19,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:38:00','2026-05-31 19:38:00'),(20,20,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:38:01','2026-05-31 19:38:01'),(21,21,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:38:03','2026-05-31 19:38:03'),(22,22,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:38:05','2026-05-31 19:38:05'),(23,23,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:38:07','2026-05-31 19:38:07'),(24,24,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:38:09','2026-05-31 19:38:09'),(25,25,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:38:11','2026-05-31 19:38:11'),(26,26,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:38:13','2026-05-31 19:38:13'),(27,27,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:38:13','2026-05-31 19:38:13'),(28,28,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:38:14','2026-05-31 19:38:14'),(29,29,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:38:15','2026-05-31 19:38:15'),(30,30,'NGN',0,0,'active',NULL,NULL,NULL,'2026-05-31 19:38:15','2026-05-31 19:38:15');
/*!40000 ALTER TABLE `wallets` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-31 21:44:45
