-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: targame
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES (1,'admin','$2y$10$p.0OJ7IvygxYjtZ17QRp7eBvNFdDAI8BChp4shlKC5/jH8q8f1tbi','2026-05-08 03:29:11');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `api_logs`
--

DROP TABLE IF EXISTS `api_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_name` varchar(30) NOT NULL,
  `action` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('success','fail') NOT NULL DEFAULT 'success',
  `response_ms` int(11) DEFAULT 0,
  `request_data` text DEFAULT NULL,
  `response_data` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_api` (`api_name`,`action`),
  KEY `idx_user` (`user_id`),
  KEY `idx_time` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_logs`
--

LOCK TABLES `api_logs` WRITE;
/*!40000 ALTER TABLE `api_logs` DISABLE KEYS */;
INSERT INTO `api_logs` VALUES (1,'skills','choose_archetype',5,'success',4,'{\"archetype\":\"assault\"}','{\"success\":true,\"message\":\"流派選擇成功\",\"refund\":0}','2026-06-02 17:39:15'),(2,'skills','unlock_node',5,'success',6,'[]','{\"success\":true,\"message\":\"節點 1 解鎖成功\",\"new_nodes\":1,\"cost\":1000}','2026-06-02 18:05:43'),(3,'skills','unlock_node',5,'success',8,'[]','{\"success\":true,\"message\":\"節點 2 解鎖成功\",\"new_nodes\":2,\"cost\":2000}','2026-06-02 18:05:45'),(4,'skills','unlock_node',5,'success',9,'[]','{\"success\":true,\"message\":\"節點 3 解鎖成功\",\"new_nodes\":3,\"cost\":3000}','2026-06-02 18:05:47'),(5,'skills','unlock_node',5,'success',6,'[]','{\"success\":true,\"message\":\"節點 4 解鎖成功\",\"new_nodes\":4,\"cost\":5000}','2026-06-02 18:08:37');
/*!40000 ALTER TABLE `api_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `battle_logs`
--

DROP TABLE IF EXISTS `battle_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `battle_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `floor` int(11) NOT NULL,
  `result` enum('win','lose','escape') NOT NULL DEFAULT 'win',
  `damage_dealt` int(11) DEFAULT 0,
  `damage_taken` int(11) DEFAULT 0,
  `exp_gained` int(11) DEFAULT 0,
  `gold_gained` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_time` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `battle_logs`
--

LOCK TABLES `battle_logs` WRITE;
/*!40000 ALTER TABLE `battle_logs` DISABLE KEYS */;
INSERT INTO `battle_logs` VALUES (1,5,1,'lose',0,0,20,81,'2026-05-24 17:36:23'),(2,5,1,'win',0,0,175,261,'2026-05-24 18:16:15'),(3,5,2,'win',0,0,275,505,'2026-05-24 18:21:02'),(4,5,3,'win',0,0,365,394,'2026-05-24 18:23:09'),(5,5,4,'win',0,0,395,875,'2026-05-24 18:24:35'),(6,5,5,'win',0,0,765,890,'2026-05-24 18:28:40'),(7,5,6,'win',0,0,1190,650,'2026-05-24 18:42:49'),(8,5,7,'win',0,0,630,1617,'2026-05-24 18:53:35'),(9,5,8,'win',0,0,915,2020,'2026-05-24 18:56:50'),(10,5,9,'win',0,0,1670,1183,'2026-05-24 19:03:08'),(11,5,10,'win',0,0,2150,2390,'2026-05-24 19:09:18'),(12,5,11,'win',0,0,1810,1440,'2026-05-24 19:16:17'),(13,5,12,'win',0,0,2630,3178,'2026-06-01 19:43:00'),(14,5,13,'win',0,0,3090,3229,'2026-06-01 20:01:05'),(15,5,14,'win',0,0,2960,3248,'2026-06-01 20:22:11'),(16,5,15,'win',0,0,2500,2925,'2026-06-01 21:02:10'),(17,5,16,'win',0,0,3860,3484,'2026-06-01 21:12:27'),(18,5,17,'win',0,0,3050,4884,'2026-06-02 17:24:51'),(19,5,18,'lose',0,0,2680,2476,'2026-06-02 18:06:02'),(20,5,18,'lose',0,0,2000,3032,'2026-06-02 18:07:33'),(21,5,17,'win',0,0,4800,3779,'2026-06-02 18:09:50');
/*!40000 ALTER TABLE `battle_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `monster_stats`
--

DROP TABLE IF EXISTS `monster_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `monster_stats` (
  `level` int(11) NOT NULL,
  `hp` int(11) NOT NULL,
  `dmg` int(11) NOT NULL,
  `def` int(11) NOT NULL,
  `exp` int(11) NOT NULL,
  `gold` int(11) NOT NULL,
  PRIMARY KEY (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `monster_stats`
--

LOCK TABLES `monster_stats` WRITE;
/*!40000 ALTER TABLE `monster_stats` DISABLE KEYS */;
INSERT INTO `monster_stats` VALUES (1,40,12,0,15,10),(2,60,15,1,20,15),(3,85,18,1,25,20),(4,110,22,2,35,25),(5,150,28,3,50,35),(6,190,32,3,60,45),(7,240,36,4,75,55),(8,290,42,5,90,65),(9,350,48,6,110,80),(10,450,60,8,150,120),(11,520,66,9,175,140),(12,600,72,10,200,160),(13,690,78,11,230,180),(14,790,85,12,260,200),(15,900,100,15,320,250),(16,1050,110,16,360,280),(17,1200,120,18,400,310),(18,1350,130,20,450,340),(19,1500,145,22,500,380),(20,2000,180,30,800,600);
/*!40000 ALTER TABLE `monster_stats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pvp_battles`
--

DROP TABLE IF EXISTS `pvp_battles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pvp_battles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `challenger_id` int(11) NOT NULL,
  `defender_id` int(11) NOT NULL,
  `winner_id` int(11) NOT NULL,
  `challenger_rating_change` int(11) DEFAULT 0,
  `defender_rating_change` int(11) DEFAULT 0,
  `battle_log` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `challenger_id` (`challenger_id`),
  KEY `defender_id` (`defender_id`),
  CONSTRAINT `pvp_battles_ibfk_1` FOREIGN KEY (`challenger_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pvp_battles_ibfk_2` FOREIGN KEY (`defender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pvp_battles`
--

LOCK TABLES `pvp_battles` WRITE;
/*!40000 ALTER TABLE `pvp_battles` DISABLE KEYS */;
INSERT INTO `pvp_battles` VALUES (1,5,24,5,30,-10,'[{\"type\":\"system\",\"text\":\"⚔️ liang（Lv.34）VS 傳說勇者（Lv.20）\"},{\"type\":\"system\",\"text\":\"???? 傳說勇者 閃避率較高，先手出擊！\"},{\"type\":\"attack\",\"text\":\"傳說勇者 造成 32 傷害。liang 剩餘 HP：708\"},{\"type\":\"attack\",\"text\":\"liang 造成 605 傷害。傳說勇者 剩餘 HP：0\"},{\"type\":\"result\",\"text\":\"???? liang 獲勝！（共 1 回合）\"}]','2026-06-01 20:58:31'),(2,5,1,5,20,-20,'[{\"type\":\"system\",\"text\":\"⚔️ liang（Lv.35）VS 玄墨（Lv.15）\"},{\"type\":\"system\",\"text\":\"???? 玄墨 閃避率較高，先手出擊！\"},{\"type\":\"attack\",\"text\":\"玄墨 造成 117 傷害。liang 剩餘 HP：633\"},{\"type\":\"attack\",\"text\":\"liang 造成 612 傷害。玄墨 剩餘 HP：8\"},{\"type\":\"attack\",\"text\":\"玄墨 造成 117 傷害。liang 剩餘 HP：516\"},{\"type\":\"attack\",\"text\":\"liang 造成 612 傷害。玄墨 剩餘 HP：0\"},{\"type\":\"result\",\"text\":\"???? liang 獲勝！（共 2 回合）\"}]','2026-06-01 21:08:44'),(3,5,1,5,20,-20,'[{\"type\":\"system\",\"text\":\"⚔️ liang（Lv.35）VS 玄墨（Lv.15）\"},{\"type\":\"system\",\"text\":\"???? 玄墨 閃避率較高，先手出擊！\"},{\"type\":\"attack\",\"text\":\"玄墨 造成 117 傷害。liang 剩餘 HP：633\"},{\"type\":\"attack\",\"text\":\"liang 造成 612 傷害。玄墨 剩餘 HP：8\"},{\"type\":\"crit\",\"text\":\"???? 爆擊！玄墨 造成 192 傷害。liang 剩餘 HP：441\"},{\"type\":\"attack\",\"text\":\"liang 造成 612 傷害。玄墨 剩餘 HP：0\"},{\"type\":\"result\",\"text\":\"???? liang 獲勝！（共 2 回合）\"}]','2026-06-01 21:11:52');
/*!40000 ALTER TABLE `pvp_battles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pvp_rankings`
--

DROP TABLE IF EXISTS `pvp_rankings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pvp_rankings` (
  `user_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT 1000,
  `wins` int(11) DEFAULT 0,
  `losses` int(11) DEFAULT 0,
  `streak` int(11) DEFAULT 0,
  `last_challenge` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `pvp_rankings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pvp_rankings`
--

LOCK TABLES `pvp_rankings` WRITE;
/*!40000 ALTER TABLE `pvp_rankings` DISABLE KEYS */;
INSERT INTO `pvp_rankings` VALUES (1,960,0,2,0,NULL),(2,1000,0,0,0,NULL),(3,1000,0,0,0,NULL),(4,1000,0,0,0,NULL),(5,1070,3,0,3,'2026-06-01 21:11:52'),(6,1000,0,0,0,NULL),(16,750,5,12,0,NULL),(17,880,15,18,0,NULL),(18,1020,28,22,2,NULL),(19,1080,30,20,1,NULL),(20,1130,35,18,3,NULL),(21,1220,42,15,5,NULL),(22,1350,55,12,4,NULL),(23,1480,68,10,7,NULL),(24,1690,85,9,0,NULL);
/*!40000 ALTER TABLE `pvp_rankings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `training_logs`
--

DROP TABLE IF EXISTS `training_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `exp_gained` int(11) DEFAULT 50,
  `stat_points_gained` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_time` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `training_logs`
--

LOCK TABLES `training_logs` WRITE;
/*!40000 ALTER TABLE `training_logs` DISABLE KEYS */;
INSERT INTO `training_logs` VALUES (1,4,50,1,'2026-05-20 20:34:34'),(2,5,50,1,'2026-05-24 17:37:04'),(3,5,50,1,'2026-05-24 17:37:29'),(4,5,50,1,'2026-05-24 17:37:42'),(5,5,50,1,'2026-05-24 17:38:21'),(6,5,3000,60,'2026-05-24 18:04:16'),(7,5,3000,60,'2026-05-24 18:18:06'),(8,5,3000,0,'2026-05-24 18:28:09'),(9,5,14400,0,'2026-05-24 18:38:57'),(10,5,14400,0,'2026-05-24 18:42:40'),(11,5,50,1,'2026-06-01 18:58:40'),(12,5,50,1,'2026-06-01 18:58:54'),(13,5,1500,10,'2026-06-01 19:35:04'),(14,5,1500,10,'2026-06-02 17:24:43'),(15,5,1500,10,'2026-06-06 20:20:00'),(16,5,14400,0,'2026-06-09 23:53:30');
/*!40000 ALTER TABLE `training_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_equipment`
--

DROP TABLE IF EXISTS `user_equipment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_equipment` (
  `user_id` int(11) NOT NULL,
  `equip_type` enum('weapon','armor','helmet') NOT NULL,
  `level` int(11) DEFAULT 0,
  `attempts` int(11) DEFAULT 0,
  `successes` int(11) DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`,`equip_type`),
  CONSTRAINT `user_equipment_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_equipment`
--

LOCK TABLES `user_equipment` WRITE;
/*!40000 ALTER TABLE `user_equipment` DISABLE KEYS */;
INSERT INTO `user_equipment` VALUES (1,'weapon',0,0,0,'2026-06-01 21:08:44'),(1,'armor',0,0,0,'2026-06-01 21:08:44'),(1,'helmet',0,0,0,'2026-06-01 21:08:44'),(5,'weapon',6,12,6,'2026-06-06 20:20:24'),(5,'armor',4,4,4,'2026-06-06 20:20:21'),(5,'helmet',0,0,0,'2026-06-01 19:56:50'),(24,'weapon',0,0,0,'2026-06-01 20:58:31'),(24,'armor',0,0,0,'2026-06-01 20:58:31'),(24,'helmet',0,0,0,'2026-06-01 20:58:31');
/*!40000 ALTER TABLE `user_equipment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_skill_build`
--

DROP TABLE IF EXISTS `user_skill_build`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_skill_build` (
  `user_id` int(11) NOT NULL,
  `archetype` enum('assault','guardian','vitality') DEFAULT NULL,
  `nodes_unlocked` int(11) NOT NULL DEFAULT 0,
  `gold_spent` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_skill_build`
--

LOCK TABLES `user_skill_build` WRITE;
/*!40000 ALTER TABLE `user_skill_build` DISABLE KEYS */;
INSERT INTO `user_skill_build` VALUES (5,'assault',4,11000);
/*!40000 ALTER TABLE `user_skill_build` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_skills`
--

DROP TABLE IF EXISTS `user_skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_skills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `skill_id` varchar(50) NOT NULL,
  `level` int(11) DEFAULT 0,
  `exp` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_skill` (`user_id`,`skill_id`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_skills`
--

LOCK TABLES `user_skills` WRITE;
/*!40000 ALTER TABLE `user_skills` DISABLE KEYS */;
INSERT INTO `user_skills` VALUES (9,1,'crit',1,17),(10,1,'dodge',1,18),(31,5,'dodge',1,4),(33,5,'crit',1,17);
/*!40000 ALTER TABLE `user_skills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `level` int(11) DEFAULT 1,
  `exp` int(11) DEFAULT 0,
  `hp` int(11) DEFAULT 100,
  `max_hp` int(11) DEFAULT 100,
  `dmg` int(11) DEFAULT 10,
  `def` int(11) DEFAULT 0,
  `stat_points` int(11) DEFAULT 0,
  `gold` int(11) DEFAULT 0,
  `max_floor` int(11) DEFAULT 0,
  `last_train_time` datetime DEFAULT NULL,
  `is_banned` tinyint(1) DEFAULT 0,
  `password` varchar(255) DEFAULT NULL,
  `last_train_cd` int(11) DEFAULT 0,
  `train_duration` int(11) DEFAULT 0,
  `is_bot` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'玄墨',15,1235,620,620,151,16,3,15127,7,'2026-05-08 03:20:00',0,'$2y$10$Yye1v8OgrEy6NOhbXlofyeuAgYBOm9uOmua4Rsn2efol4RIvKUD2C',0,0,0),(2,'manbo',1,0,100,100,10,0,0,0,0,'2026-05-08 16:55:44',0,'$2y$10$cHTDvTdOWMB8zueKup.aquwZLbS3D0FERi3fMpSkEZsuxycS4uGwq',0,0,0),(3,'testuser',1,0,100,100,10,0,0,0,0,NULL,0,'$2y$10$KinOXqCOBrnFPG/f8jd5t.DhOdjOQy2Ob1w/UZtqxsellAgzweNw2',0,0,0),(4,'admin',1,50,100,100,10,0,1,0,0,'2026-05-20 20:34:42',0,'$2y$10$iWwwZJZLuPhn8Or4cEfJjegrmuVwNIwL8GxCi.kYa9ZwgdoJE/Oqq',0,0,0),(5,'liang',44,330,840,840,721,43,0,1841,17,'2026-06-09 23:53:30',0,'$2y$10$Gsf0wuBgPJUbqM90EHIqBOvOpiUIslSDZjhnYFp6i.Z4Y0pL5Cnju',3600,28800,0),(6,'test',1,0,100,100,10,0,0,0,0,NULL,0,'$2y$10$codQTcnIldjyBqweIhD/DOtrBRKxmhqL29j5SaDX49Xaex/pMoC0m',0,0,0),(16,'新手訓練機',1,0,80,80,8,0,0,0,0,NULL,0,'',0,0,1),(17,'見習武士',3,0,120,120,14,2,0,0,0,NULL,0,'',0,0,1),(18,'野村戰士',5,0,160,160,20,4,0,0,0,NULL,0,'',0,0,1),(19,'鐵牆守衛',7,0,260,260,16,12,0,0,0,NULL,0,'',0,0,1),(20,'迅影刺客',8,0,150,150,28,3,0,0,0,NULL,0,'',0,0,1),(21,'暗黑弓手',10,0,180,180,38,5,0,0,0,NULL,0,'',0,0,1),(22,'精英戰士',13,0,300,300,40,14,0,0,0,NULL,0,'',0,0,1),(23,'魔導師',16,0,240,240,55,8,0,0,0,NULL,0,'',0,0,1),(24,'傳說勇者',20,0,400,400,65,20,0,0,0,NULL,0,'',0,0,1);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-10  0:23:21
