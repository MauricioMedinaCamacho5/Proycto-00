-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: proyecto01
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
-- Table structure for table `accesos`
--

DROP TABLE IF EXISTS `accesos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accesos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `visitante_id` bigint(20) unsigned DEFAULT NULL,
  `registrado_por` bigint(20) unsigned NOT NULL,
  `tipo` enum('ingreso','salida') NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `observacion` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_accesos_fecha` (`fecha_hora`),
  KEY `fk_accesos_usuario` (`usuario_id`),
  KEY `fk_accesos_registrador` (`registrado_por`),
  KEY `fk_accesos_visitante` (`visitante_id`),
  CONSTRAINT `fk_accesos_registrador` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_accesos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_accesos_visitante` FOREIGN KEY (`visitante_id`) REFERENCES `visitantes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accesos`
--

LOCK TABLES `accesos` WRITE;
/*!40000 ALTER TABLE `accesos` DISABLE KEYS */;
/*!40000 ALTER TABLE `accesos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ambientes`
--

DROP TABLE IF EXISTS `ambientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ambientes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `ubicacion` varchar(180) NOT NULL,
  `capacidad` smallint(5) unsigned NOT NULL DEFAULT 1,
  `estado` enum('disponible','ocupado','mantenimiento') NOT NULL DEFAULT 'disponible',
  `descripcion` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ambientes`
--

LOCK TABLES `ambientes` WRITE;
/*!40000 ALTER TABLE `ambientes` DISABLE KEYS */;
INSERT INTO `ambientes` VALUES (1,'A-101','Laboratorio de Sistemas','Bloque A, primer piso',30,'disponible',NULL);
/*!40000 ALTER TABLE `ambientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asistencias`
--

DROP TABLE IF EXISTS `asistencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asistencias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `ficha_id` bigint(20) unsigned NOT NULL,
  `horario_id` bigint(20) unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `estado` enum('presente','ausente','tarde','permiso') NOT NULL,
  `registrado_por` bigint(20) unsigned NOT NULL,
  `observacion` varchar(500) DEFAULT NULL,
  `registrado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asistencia_usuario_ficha_fecha` (`usuario_id`,`ficha_id`,`fecha`),
  KEY `fk_asistencias_ficha` (`ficha_id`),
  KEY `fk_asistencias_horario` (`horario_id`),
  KEY `fk_asistencias_registrador` (`registrado_por`),
  CONSTRAINT `fk_asistencias_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`),
  CONSTRAINT `fk_asistencias_horario` FOREIGN KEY (`horario_id`) REFERENCES `horarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_asistencias_registrador` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_asistencias_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asistencias`
--

LOCK TABLES `asistencias` WRITE;
/*!40000 ALTER TABLE `asistencias` DISABLE KEYS */;
INSERT INTO `asistencias` VALUES (4,1,1,NULL,'2026-10-18','presente',1,NULL,'2026-10-18 23:41:08');
/*!40000 ALTER TABLE `asistencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auditoria`
--

DROP TABLE IF EXISTS `auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditoria` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `entidad` varchar(80) NOT NULL,
  `entidad_id` bigint(20) unsigned DEFAULT NULL,
  `detalle` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detalle`)),
  `fecha_hora` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_fecha` (`fecha_hora`),
  KEY `fk_auditoria_usuario` (`usuario_id`),
  CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=177 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria`
--

LOCK TABLES `auditoria` WRITE;
/*!40000 ALTER TABLE `auditoria` DISABLE KEYS */;
INSERT INTO `auditoria` VALUES (1,1,'inicio_sesion','usuarios',1,NULL,'2026-10-18 23:36:28'),(2,1,'inicio_sesion','usuarios',1,NULL,'2026-10-18 23:36:37'),(3,1,'crear','ambientes',3,NULL,'2026-10-18 23:36:37'),(4,1,'eliminar','ambientes',3,NULL,'2026-10-18 23:36:37'),(5,1,'inicio_sesion','usuarios',1,NULL,'2026-10-18 23:37:28'),(6,1,'inicio_sesion','usuarios',1,NULL,'2026-10-18 23:40:02'),(7,1,'inicio_sesion','usuarios',1,NULL,'2026-10-18 23:40:33'),(8,1,'crear','asistencias',4,NULL,'2026-10-18 23:41:08'),(9,1,'crear','usuarios',8,NULL,'2026-10-18 23:43:19'),(10,1,'eliminar','carnets',5,NULL,'2026-10-18 23:44:00'),(11,1,'eliminar','carnets',3,NULL,'2026-10-18 23:44:02'),(12,1,'eliminar','carnets',4,NULL,'2026-10-18 23:44:04'),(13,1,'inicio_sesion','usuarios',1,NULL,'2026-10-18 23:45:21'),(14,1,'cierre_sesion','usuarios',1,NULL,'2026-10-18 23:45:21'),(15,1,'cierre_sesion','usuarios',1,NULL,'2026-10-18 23:45:48'),(16,8,'inicio_sesion','usuarios',8,NULL,'2026-10-18 23:45:57'),(17,8,'cierre_sesion','usuarios',8,NULL,'2026-10-18 23:46:08'),(18,1,'inicio_sesion','usuarios',1,NULL,'2026-10-18 23:46:12'),(19,1,'cierre_sesion','usuarios',1,NULL,'2026-10-18 23:46:13'),(20,1,'inicio_sesion','usuarios',1,NULL,'2026-10-18 23:46:32'),(21,1,'actualizar','usuarios',8,NULL,'2026-10-18 23:47:07'),(22,1,'eliminar','usuarios',4,NULL,'2026-10-18 23:47:10'),(23,1,'eliminar','usuarios',3,NULL,'2026-10-18 23:47:13'),(24,1,'cierre_sesion','usuarios',1,NULL,'2026-10-18 23:47:21'),(25,8,'inicio_sesion','usuarios',8,NULL,'2026-10-18 23:47:29'),(26,8,'cierre_sesion','usuarios',8,NULL,'2026-10-18 23:48:46'),(27,1,'inicio_sesion','usuarios',1,NULL,'2026-10-18 23:48:54'),(28,1,'cierre_sesion','usuarios',1,NULL,'2026-10-18 23:48:56'),(29,1,'restablecer_contrasena','usuarios',1,NULL,'2026-10-18 23:50:13'),(30,1,'inicio_sesion','usuarios',1,NULL,'2026-10-18 23:50:19'),(31,1,'cierre_sesion','usuarios',1,NULL,'2026-10-18 23:50:34'),(32,1,'restablecer_contrasena','usuarios',1,NULL,'2026-10-18 23:52:07'),(33,1,'inicio_sesion','usuarios',1,NULL,'2026-10-18 23:52:23'),(34,1,'cierre_sesion','usuarios',1,NULL,'2026-10-18 23:52:27'),(35,1,'restablecer_contrasena','usuarios',1,NULL,'2026-10-18 23:52:41'),(36,1,'inicio_sesion','usuarios',1,NULL,'2026-10-18 23:52:49'),(37,1,'cierre_sesion','usuarios',1,NULL,'2026-10-18 23:54:43'),(38,1,'restablecer_contrasena','usuarios',1,NULL,'2026-10-18 23:54:48'),(39,1,'cierre_sesion','usuarios',1,NULL,'2026-10-18 23:55:22'),(40,1,'restablecer_contrasena','usuarios',1,NULL,'2026-10-18 23:55:48'),(41,1,'inicio_sesion','usuarios',1,NULL,'2026-10-18 23:55:54'),(42,1,'cierre_sesion','usuarios',1,NULL,'2026-10-18 23:55:57'),(43,1,'restablecer_contrasena','usuarios',1,NULL,'2026-10-18 23:56:12'),(44,1,'inicio_sesion','usuarios',1,NULL,'2026-10-18 23:56:16'),(45,1,'cierre_sesion','usuarios',1,NULL,'2026-10-18 23:57:44'),(46,1,'inicio_sesion','usuarios',1,NULL,'2026-10-19 00:20:59'),(47,1,'cierre_sesion','usuarios',1,NULL,'2026-10-19 00:46:36'),(48,1,'inicio_sesion','usuarios',1,NULL,'2026-10-19 00:46:42'),(49,1,'cierre_sesion','usuarios',1,NULL,'2026-10-19 00:46:44'),(50,1,'inicio_sesion','usuarios',1,NULL,'2026-10-19 00:46:57'),(51,1,'inicio_sesion','usuarios',1,NULL,'2026-10-19 00:47:12'),(52,1,'cierre_sesion','usuarios',1,NULL,'2026-10-19 00:48:55'),(53,1,'inicio_sesion','usuarios',1,NULL,'2026-10-19 00:49:01'),(54,1,'cierre_sesion','usuarios',1,NULL,'2026-10-19 00:49:08'),(55,1,'inicio_sesion','usuarios',1,NULL,'2026-10-19 00:49:11'),(56,1,'crear','usuarios',14,NULL,'2026-10-19 00:50:14'),(57,1,'cierre_sesion','usuarios',1,NULL,'2026-10-19 00:50:23'),(58,NULL,'inicio_sesion','usuarios',14,NULL,'2026-10-19 00:51:27'),(59,NULL,'cierre_sesion','usuarios',14,NULL,'2026-10-19 00:51:57'),(60,1,'inicio_sesion','usuarios',1,NULL,'2026-10-19 00:52:03'),(61,1,'cierre_sesion','usuarios',1,NULL,'2026-10-19 01:44:21'),(62,NULL,'registro_publico','usuarios',15,'{\"rol\":\"estudiante\"}','2026-10-19 01:50:32'),(63,1,'inicio_sesion','usuarios',1,NULL,'2026-10-19 01:53:23'),(64,NULL,'registro_publico','usuarios',16,'{\"rol\":\"estudiante\"}','2026-10-19 01:53:29'),(65,NULL,'registro_publico','usuarios',17,'{\"rol\":\"estudiante\"}','2026-10-19 01:53:57'),(66,NULL,'registro_publico','usuarios',18,'{\"rol\":\"estudiante\"}','2026-10-19 01:53:57'),(67,1,'eliminar','usuarios',14,NULL,'2026-10-19 01:53:57'),(68,1,'eliminar','usuarios',18,NULL,'2026-10-19 01:54:07'),(69,NULL,'registro_publico','usuarios',19,'{\"rol\":\"estudiante\"}','2026-10-19 01:54:14'),(70,1,'cierre_sesion','usuarios',1,NULL,'2026-10-19 01:54:18'),(71,NULL,'registro_publico','usuarios',20,'{\"rol\":\"estudiante\"}','2026-10-19 01:54:37'),(72,NULL,'registro_publico','usuarios',21,'{\"rol\":\"estudiante\"}','2026-10-19 01:58:47'),(73,NULL,'registro_publico','usuarios',22,'{\"rol\":\"estudiante\"}','2026-10-19 02:05:29'),(74,1,'inicio_sesion','usuarios',1,NULL,'2026-10-19 02:06:55'),(75,1,'eliminar','ambientes',2,NULL,'2026-10-19 02:07:19'),(76,1,'crear','fichas',2,NULL,'2026-10-19 02:08:14'),(77,1,'actualizar','equipos',1,NULL,'2026-10-19 02:09:24'),(78,1,'cierre_sesion','usuarios',1,NULL,'2026-10-19 02:12:28'),(79,NULL,'registro_publico','usuarios',23,'{\"rol\":\"estudiante\"}','2026-10-19 02:16:28'),(80,24,'registro_publico','usuarios',24,'{\"rol\":\"estudiante\"}','2026-10-19 02:25:04'),(81,24,'cierre_sesion','usuarios',24,NULL,'2026-10-19 02:25:21'),(82,NULL,'registro_publico','usuarios',25,'{\"rol\":\"estudiante\"}','2026-10-19 02:29:46'),(83,NULL,'registro_publico','usuarios',26,'{\"rol\":\"estudiante\"}','2026-10-19 02:30:53'),(84,NULL,'inicio_sesion_codigo_barras','usuarios',26,NULL,'2026-10-19 02:30:54'),(85,NULL,'registro_publico','usuarios',27,'{\"rol\":\"estudiante\"}','2026-10-19 02:31:02'),(86,NULL,'inicio_sesion_codigo_barras','usuarios',27,NULL,'2026-10-19 02:31:17'),(87,NULL,'cierre_sesion','usuarios',27,NULL,'2026-10-19 02:31:17'),(88,24,'inicio_sesion_codigo_barras','usuarios',24,NULL,'2026-10-19 02:31:30'),(89,24,'cierre_sesion','usuarios',24,NULL,'2026-10-19 02:31:37'),(90,NULL,'inicio_sesion_codigo_barras','usuarios',27,NULL,'2026-10-19 02:32:01'),(91,NULL,'cierre_sesion','usuarios',27,NULL,'2026-10-19 02:32:01'),(92,28,'registro_publico','usuarios',28,'{\"rol\":\"estudiante\"}','2026-10-19 02:33:07'),(93,28,'cierre_sesion','usuarios',28,NULL,'2026-10-19 02:33:12'),(94,28,'inicio_sesion_codigo_barras','usuarios',28,NULL,'2026-10-19 02:33:36'),(95,28,'cierre_sesion','usuarios',28,NULL,'2026-10-19 02:38:09'),(96,NULL,'solicitar_registro_personal','solicitudes_registro',1,'{\"rol\":\"instructor\"}','2026-10-19 02:44:43'),(97,NULL,'solicitar_registro_personal','solicitudes_registro',2,'{\"rol\":\"portero\"}','2026-10-19 02:44:43'),(98,NULL,'inicio_sesion','usuarios',29,NULL,'2026-10-19 02:44:43'),(99,NULL,'aprobar_registro_personal','solicitudes_registro',1,'{\"rol\":\"instructor\"}','2026-10-19 02:44:43'),(100,NULL,'inicio_sesion','usuarios',30,NULL,'2026-10-19 02:44:43'),(101,NULL,'rechazar_registro_personal','solicitudes_registro',2,'{\"rol\":\"portero\"}','2026-10-19 02:44:43'),(102,NULL,'registro_publico','usuarios',32,'{\"rol\":\"estudiante\"}','2026-10-19 02:44:44'),(103,NULL,'solicitar_registro_personal','solicitudes_registro',3,'{\"rol\":\"instructor\"}','2026-10-19 02:45:17'),(104,NULL,'inicio_sesion','usuarios',33,NULL,'2026-10-19 02:45:17'),(105,NULL,'aprobar_registro_personal','solicitudes_registro',3,'{\"rol\":\"instructor\"}','2026-10-19 02:45:22'),(106,NULL,'cierre_sesion','usuarios',33,NULL,'2026-10-19 02:45:22'),(107,NULL,'inicio_sesion','usuarios',35,NULL,'2026-10-19 02:48:00'),(108,NULL,'cierre_sesion','usuarios',35,NULL,'2026-10-19 02:48:00'),(109,NULL,'inicio_sesion','usuarios',36,NULL,'2026-10-19 02:48:01'),(110,NULL,'cierre_sesion','usuarios',36,NULL,'2026-10-19 02:48:01'),(111,28,'inicio_sesion_codigo_barras','usuarios',28,NULL,'2026-10-19 02:49:11'),(112,28,'cierre_sesion','usuarios',28,NULL,'2026-10-19 02:49:13'),(113,1,'inicio_sesion','usuarios',1,NULL,'2026-10-19 02:49:30'),(114,1,'cierre_sesion','usuarios',1,NULL,'2026-10-19 03:14:02'),(115,28,'inicio_sesion_codigo_barras','usuarios',28,NULL,'2026-10-19 03:18:20'),(116,28,'cierre_sesion','usuarios',28,NULL,'2026-10-19 03:18:22'),(117,24,'inicio_sesion_codigo_barras','usuarios',24,NULL,'2026-10-19 03:33:26'),(118,24,'cierre_sesion','usuarios',24,NULL,'2026-10-19 03:36:50'),(119,NULL,'solicitar_registro_personal','solicitudes_registro',4,'{\"rol\":\"instructor\"}','2026-10-19 03:37:58'),(120,1,'inicio_sesion','usuarios',1,NULL,'2026-10-19 03:38:27'),(121,1,'cierre_sesion','usuarios',1,NULL,'2026-10-19 03:38:43'),(130,40,'solicitar_registro_personal','solicitudes_registro',6,'{\"rol\":\"instructor\"}','2026-10-19 03:49:19'),(131,1,'inicio_sesion','usuarios',1,NULL,'2026-10-19 03:49:38'),(132,1,'aprobar_registro_personal','solicitudes_registro',6,'{\"rol\":\"instructor\"}','2026-10-19 03:50:08'),(133,1,'cierre_sesion','usuarios',1,NULL,'2026-10-19 03:50:39'),(134,41,'solicitar_registro_personal','solicitudes_registro',7,'{\"rol\":\"portero\"}','2026-10-19 03:52:28'),(135,1,'inicio_sesion','usuarios',1,NULL,'2026-10-19 03:52:42'),(136,1,'aprobar_registro_personal','solicitudes_registro',7,'{\"rol\":\"portero\"}','2026-10-19 03:52:51'),(141,1,'cierre_sesion','usuarios',1,NULL,'2026-10-19 03:59:09'),(142,45,'solicitar_registro_personal','solicitudes_registro',8,'{\"rol\":\"instructor\"}','2026-10-19 04:10:24'),(143,1,'inicio_sesion','usuarios',1,NULL,'2026-10-19 04:10:53'),(144,1,'aprobar_registro_personal','solicitudes_registro',8,'{\"rol\":\"instructor\"}','2026-10-19 04:11:06'),(145,45,'inicio_sesion_codigo_cedula','usuarios',45,NULL,'2026-10-19 04:11:57'),(146,45,'cierre_sesion','usuarios',45,NULL,'2026-10-19 04:12:02'),(147,1,'inicio_sesion','usuarios',1,NULL,'2026-10-19 04:13:26'),(148,1,'cierre_sesion','usuarios',1,NULL,'2026-10-19 04:16:36'),(149,46,'registro_publico','usuarios',46,'{\"rol\":\"estudiante\"}','2026-10-20 00:52:58'),(150,46,'cierre_sesion','usuarios',46,NULL,'2026-10-20 00:54:21'),(151,46,'inicio_sesion_qr_carnet','usuarios',46,NULL,'2026-10-20 00:54:28'),(152,46,'cierre_sesion','usuarios',46,NULL,'2026-10-20 00:54:50'),(153,46,'inicio_sesion_qr_carnet','usuarios',46,NULL,'2026-10-20 00:55:07'),(154,46,'cierre_sesion','usuarios',46,NULL,'2026-10-20 00:55:25'),(155,46,'inicio_sesion_qr_carnet','usuarios',46,NULL,'2026-10-20 00:55:29'),(156,46,'cierre_sesion','usuarios',46,NULL,'2026-10-20 00:55:32'),(157,46,'inicio_sesion_qr_carnet','usuarios',46,NULL,'2026-10-20 00:55:37'),(158,46,'cierre_sesion','usuarios',46,NULL,'2026-10-20 00:55:38'),(159,46,'inicio_sesion_qr_carnet','usuarios',46,NULL,'2026-10-20 00:55:42'),(160,46,'cierre_sesion','usuarios',46,NULL,'2026-10-20 00:55:54'),(161,1,'inicio_sesion','usuarios',1,NULL,'2026-10-20 00:56:03'),(162,1,'rechazar_registro_personal','solicitudes_registro',4,'{\"rol\":\"instructor\"}','2026-10-20 00:56:15'),(163,1,'cierre_sesion','usuarios',1,NULL,'2026-10-20 00:59:35'),(164,46,'inicio_sesion_qr_carnet','usuarios',46,NULL,'2026-10-20 00:59:48'),(165,46,'cierre_sesion','usuarios',46,NULL,'2026-10-20 01:01:01'),(166,47,'solicitar_registro_personal','solicitudes_registro',9,'{\"rol\":\"instructor\"}','2026-10-20 01:03:34'),(167,1,'inicio_sesion','usuarios',1,NULL,'2026-10-20 01:04:56'),(168,1,'aprobar_registro_personal','solicitudes_registro',9,'{\"rol\":\"instructor\"}','2026-10-20 01:05:04'),(169,1,'eliminar','carnets',2,NULL,'2026-10-20 01:05:23'),(170,1,'renovar_qr_carne','carnets',1,'{\"usuario_id\":1}','2026-10-20 01:05:25'),(171,1,'actualizar','carnets',1,NULL,'2026-10-20 01:05:45'),(172,1,'cierre_sesion','usuarios',1,NULL,'2026-10-20 01:05:52'),(173,47,'inicio_sesion_qr_carnet','usuarios',47,NULL,'2026-10-20 01:06:05'),(174,47,'cierre_sesion','usuarios',47,NULL,'2026-10-20 01:09:45'),(175,1,'inicio_sesion','usuarios',1,NULL,'2026-10-20 01:09:56'),(176,1,'cierre_sesion','usuarios',1,NULL,'2026-10-20 01:30:57');
/*!40000 ALTER TABLE `auditoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bloqueos`
--

DROP TABLE IF EXISTS `bloqueos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bloqueos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `administrador_id` bigint(20) unsigned NOT NULL,
  `motivo` varchar(500) NOT NULL,
  `bloqueado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `desbloqueado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_bloqueos_usuario` (`usuario_id`),
  KEY `fk_bloqueos_admin` (`administrador_id`),
  CONSTRAINT `fk_bloqueos_admin` FOREIGN KEY (`administrador_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_bloqueos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bloqueos`
--

LOCK TABLES `bloqueos` WRITE;
/*!40000 ALTER TABLE `bloqueos` DISABLE KEYS */;
/*!40000 ALTER TABLE `bloqueos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `carnets`
--

DROP TABLE IF EXISTS `carnets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `carnets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `codigo_qr` char(36) NOT NULL,
  `emitido_en` datetime NOT NULL DEFAULT current_timestamp(),
  `vence_en` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`),
  UNIQUE KEY `codigo_qr` (`codigo_qr`),
  CONSTRAINT `fk_carnets_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `carnets`
--

LOCK TABLES `carnets` WRITE;
/*!40000 ALTER TABLE `carnets` DISABLE KEYS */;
INSERT INTO `carnets` VALUES (1,1,'c09de531-fe4c-44cc-9ec0-d29acc3bb9cb','2026-10-18 18:36:21','2026-10-31'),(16,24,'f97cee5f-839e-403e-af25-a17493ffc1a0','2026-10-18 21:25:04',NULL),(20,28,'6b228a9c-ae3d-4219-945b-a3355ee2d503','2026-10-18 21:33:07',NULL),(25,40,'7878f530-aed9-40af-b10f-14ff95292c24','2026-10-18 22:50:08',NULL),(26,41,'2d2fd22e-d4c1-427e-9a68-11d2bd368640','2026-10-18 22:52:51',NULL),(28,45,'3d787e01-6b95-440d-b17a-2f277fd22a9b','2026-10-18 23:11:06',NULL),(29,46,'29d94834-46ad-4c5e-ac14-0fa8e6e463d3','2026-10-19 19:52:58',NULL),(30,47,'f1c9025b-4442-4b4e-9782-e3892db52de7','2026-10-19 20:03:34',NULL);
/*!40000 ALTER TABLE `carnets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipos`
--

DROP TABLE IF EXISTS `equipos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `serial` varchar(100) NOT NULL,
  `codigo_qr` char(36) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `categoria` varchar(80) NOT NULL,
  `estado` enum('disponible','asignado','prestado','mantenimiento','baja') NOT NULL DEFAULT 'disponible',
  `ubicacion` varchar(180) NOT NULL,
  `autorizado` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial` (`serial`),
  UNIQUE KEY `codigo_qr` (`codigo_qr`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipos`
--

LOCK TABLES `equipos` WRITE;
/*!40000 ALTER TABLE `equipos` DISABLE KEYS */;
INSERT INTO `equipos` VALUES (1,'DEMO-LAP-001','eb8e1ac8-3af2-4141-86f8-99c6ea13ebf8','LENOVO','Computador','disponible','Ambiente 209-1',1,'2026-10-18 23:36:21');
/*!40000 ALTER TABLE `equipos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fichas`
--

DROP TABLE IF EXISTS `fichas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fichas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('activa','finalizada','suspendida') NOT NULL DEFAULT 'activa',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fichas`
--

LOCK TABLES `fichas` WRITE;
/*!40000 ALTER TABLE `fichas` DISABLE KEYS */;
INSERT INTO `fichas` VALUES (1,'FIC-2026-01','Tecnico en Sistemas',NULL,NULL,'activa'),(2,'01','ADSO','2026-02-09','2028-05-08','activa');
/*!40000 ALTER TABLE `fichas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fichas_usuarios`
--

DROP TABLE IF EXISTS `fichas_usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fichas_usuarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ficha_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `inscrito_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ficha_usuario` (`ficha_id`,`usuario_id`),
  KEY `fk_ficha_usuarios_usuario` (`usuario_id`),
  CONSTRAINT `fk_ficha_usuarios_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ficha_usuarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fichas_usuarios`
--

LOCK TABLES `fichas_usuarios` WRITE;
/*!40000 ALTER TABLE `fichas_usuarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `fichas_usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `horarios`
--

DROP TABLE IF EXISTS `horarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `horarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ficha_id` bigint(20) unsigned NOT NULL,
  `ambiente_id` bigint(20) unsigned NOT NULL,
  `instructor_id` bigint(20) unsigned NOT NULL,
  `dia_semana` tinyint(3) unsigned NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `asignatura` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_horarios_ficha` (`ficha_id`),
  KEY `fk_horarios_ambiente` (`ambiente_id`),
  KEY `fk_horarios_instructor` (`instructor_id`),
  KEY `idx_horarios_busqueda` (`dia_semana`,`hora_inicio`,`hora_fin`),
  CONSTRAINT `fk_horarios_ambiente` FOREIGN KEY (`ambiente_id`) REFERENCES `ambientes` (`id`),
  CONSTRAINT `fk_horarios_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`),
  CONSTRAINT `fk_horarios_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `chk_horarios_dia` CHECK (`dia_semana` between 1 and 7),
  CONSTRAINT `chk_horarios_horas` CHECK (`hora_inicio` < `hora_fin`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horarios`
--

LOCK TABLES `horarios` WRITE;
/*!40000 ALTER TABLE `horarios` DISABLE KEYS */;
INSERT INTO `horarios` VALUES (1,1,1,2,1,'08:00:00','12:00:00','Fundamentos de programacion');
/*!40000 ALTER TABLE `horarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mantenimientos`
--

DROP TABLE IF EXISTS `mantenimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mantenimientos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ambiente_id` bigint(20) unsigned NOT NULL,
  `responsable_id` bigint(20) unsigned DEFAULT NULL,
  `tipo` enum('preventivo','correctivo') NOT NULL,
  `descripcion` text NOT NULL,
  `programado_en` datetime NOT NULL,
  `finalizado_en` datetime DEFAULT NULL,
  `estado` enum('pendiente','en_proceso','completado','cancelado') NOT NULL DEFAULT 'pendiente',
  PRIMARY KEY (`id`),
  KEY `fk_mantenimientos_ambiente` (`ambiente_id`),
  KEY `fk_mantenimientos_responsable` (`responsable_id`),
  CONSTRAINT `fk_mantenimientos_ambiente` FOREIGN KEY (`ambiente_id`) REFERENCES `ambientes` (`id`),
  CONSTRAINT `fk_mantenimientos_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mantenimientos`
--

LOCK TABLES `mantenimientos` WRITE;
/*!40000 ALTER TABLE `mantenimientos` DISABLE KEYS */;
/*!40000 ALTER TABLE `mantenimientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movimientos_equipos`
--

DROP TABLE IF EXISTS `movimientos_equipos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movimientos_equipos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `equipo_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `registrado_por` bigint(20) unsigned NOT NULL,
  `tipo` enum('ingreso','salida','traslado','asignacion','devolucion') NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `ubicacion_origen` varchar(180) DEFAULT NULL,
  `ubicacion_destino` varchar(180) DEFAULT NULL,
  `observacion` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_movimientos_equipo` (`equipo_id`),
  KEY `fk_movimientos_usuario` (`usuario_id`),
  KEY `fk_movimientos_registrador` (`registrado_por`),
  CONSTRAINT `fk_movimientos_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`),
  CONSTRAINT `fk_movimientos_registrador` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_movimientos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimientos_equipos`
--

LOCK TABLES `movimientos_equipos` WRITE;
/*!40000 ALTER TABLE `movimientos_equipos` DISABLE KEYS */;
/*!40000 ALTER TABLE `movimientos_equipos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `mensaje` text NOT NULL,
  `leida_en` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_notificaciones_usuario` (`usuario_id`),
  CONSTRAINT `fk_notificaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificaciones`
--

LOCK TABLES `notificaciones` WRITE;
/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `notificaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `novedades`
--

DROP TABLE IF EXISTS `novedades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `novedades` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `asistencia_id` bigint(20) unsigned DEFAULT NULL,
  `tipo` enum('retraso','permiso','inasistencia','otra') NOT NULL,
  `descripcion` varchar(500) NOT NULL,
  `fecha` date NOT NULL,
  `registrado_por` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_novedades_usuario` (`usuario_id`),
  KEY `fk_novedades_asistencia` (`asistencia_id`),
  KEY `fk_novedades_registrador` (`registrado_por`),
  CONSTRAINT `fk_novedades_asistencia` FOREIGN KEY (`asistencia_id`) REFERENCES `asistencias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_novedades_registrador` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_novedades_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `novedades`
--

LOCK TABLES `novedades` WRITE;
/*!40000 ALTER TABLE `novedades` DISABLE KEYS */;
/*!40000 ALTER TABLE `novedades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permisos_temporales`
--

DROP TABLE IF EXISTS `permisos_temporales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permisos_temporales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `visitante_id` bigint(20) unsigned DEFAULT NULL,
  `autorizado_por` bigint(20) unsigned NOT NULL,
  `desde` datetime NOT NULL,
  `hasta` datetime NOT NULL,
  `motivo` varchar(300) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_permisos_usuario` (`usuario_id`),
  KEY `fk_permisos_visitante` (`visitante_id`),
  KEY `fk_permisos_autorizador` (`autorizado_por`),
  CONSTRAINT `fk_permisos_autorizador` FOREIGN KEY (`autorizado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_permisos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_permisos_visitante` FOREIGN KEY (`visitante_id`) REFERENCES `visitantes` (`id`),
  CONSTRAINT `chk_permisos_rango` CHECK (`desde` < `hasta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permisos_temporales`
--

LOCK TABLES `permisos_temporales` WRITE;
/*!40000 ALTER TABLE `permisos_temporales` DISABLE KEYS */;
/*!40000 ALTER TABLE `permisos_temporales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prestamos`
--

DROP TABLE IF EXISTS `prestamos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prestamos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `equipo_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `entregado_por` bigint(20) unsigned NOT NULL,
  `prestado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `vence_en` datetime NOT NULL,
  `devuelto_en` datetime DEFAULT NULL,
  `estado` enum('activo','devuelto','vencido') NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id`),
  KEY `fk_prestamos_equipo` (`equipo_id`),
  KEY `fk_prestamos_usuario` (`usuario_id`),
  KEY `fk_prestamos_entrega` (`entregado_por`),
  KEY `idx_prestamos_estado` (`estado`,`vence_en`),
  CONSTRAINT `fk_prestamos_entrega` FOREIGN KEY (`entregado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_prestamos_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`),
  CONSTRAINT `fk_prestamos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prestamos`
--

LOCK TABLES `prestamos` WRITE;
/*!40000 ALTER TABLE `prestamos` DISABLE KEYS */;
/*!40000 ALTER TABLE `prestamos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recuperacion_password`
--

DROP TABLE IF EXISTS `recuperacion_password`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recuperacion_password` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `vence_en` datetime NOT NULL,
  `usado_en` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `fk_recuperacion_usuario` (`usuario_id`),
  CONSTRAINT `fk_recuperacion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recuperacion_password`
--

LOCK TABLES `recuperacion_password` WRITE;
/*!40000 ALTER TABLE `recuperacion_password` DISABLE KEYS */;
INSERT INTO `recuperacion_password` VALUES (1,1,'261317b1d0dfc5a5be36553f4b288129e56b43e7a3084a311aa408d4838c8ec8','2026-10-18 19:20:13','2026-10-18 18:50:13','2026-10-18 23:50:13'),(2,1,'0b55454680e93dca703790e4ff1211eb9d34d7d192973c8755f28e4d0017d15c','2026-10-18 19:20:15',NULL,'2026-10-18 23:50:15'),(3,1,'387b22543778a0bb0aaafbc9b7d2f24c9baed38c187493fdce8f6070cd7c08cc','2026-10-18 19:20:17',NULL,'2026-10-18 23:50:17'),(4,1,'bcc751d9922eb39d4f85659d2e2ca0b0be35d047b5f0056633a89dcf3e061df0','2026-10-18 19:21:52','2026-10-18 18:52:07','2026-10-18 23:51:52'),(5,1,'498c0d3291abc0c13bb8ac1feed9453c1832c584ec17d0b33b4c5d3e1c382194','2026-10-18 19:22:33','2026-10-18 18:52:41','2026-10-18 23:52:33'),(6,1,'29a30fd2a937a9337c0a936b9983a3cc91016118be2fbc66ce3587544e1ef045','2026-10-18 19:24:48','2026-10-18 18:54:48','2026-10-18 23:54:48'),(7,1,'3b56bcc565db9d50f1ee50a01c32e39aa1154d8163f76c64df3349789b645e6f','2026-10-18 19:25:33','2026-10-18 18:55:48','2026-10-18 23:55:33'),(8,1,'0a13e0030f2efd0e9e20d0b18ff97c7fec14d310ab6e41305a39ec81e959f125','2026-10-18 19:26:05','2026-10-18 18:56:12','2026-10-18 23:56:05');
/*!40000 ALTER TABLE `recuperacion_password` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reservas`
--

DROP TABLE IF EXISTS `reservas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reservas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ambiente_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `inicio` datetime NOT NULL,
  `fin` datetime NOT NULL,
  `motivo` varchar(250) NOT NULL,
  `estado` enum('pendiente','aprobada','rechazada','cancelada') NOT NULL DEFAULT 'aprobada',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_reservas_usuario` (`usuario_id`),
  KEY `idx_reservas_ambiente_fecha` (`ambiente_id`,`inicio`,`fin`),
  CONSTRAINT `fk_reservas_ambiente` FOREIGN KEY (`ambiente_id`) REFERENCES `ambientes` (`id`),
  CONSTRAINT `fk_reservas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `chk_reservas_rango` CHECK (`inicio` < `fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservas`
--

LOCK TABLES `reservas` WRITE;
/*!40000 ALTER TABLE `reservas` DISABLE KEYS */;
/*!40000 ALTER TABLE `reservas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  `descripcion` varchar(180) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'administrador','Acceso completo al sistema'),(2,'instructor','Asistencia, horarios y novedades'),(3,'estudiante','Consulta de horario, asistencia y carnet'),(4,'portero','Accesos, visitantes y equipos');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `solicitudes_registro`
--

DROP TABLE IF EXISTS `solicitudes_registro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `solicitudes_registro` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `estado` enum('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `solicitada_en` datetime NOT NULL DEFAULT current_timestamp(),
  `revisada_en` datetime DEFAULT NULL,
  `revisada_por` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_solicitudes_estado` (`estado`,`solicitada_en`),
  KEY `fk_solicitudes_usuario` (`usuario_id`),
  KEY `fk_solicitudes_revisor` (`revisada_por`),
  CONSTRAINT `fk_solicitudes_revisor` FOREIGN KEY (`revisada_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_solicitudes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `solicitudes_registro`
--

LOCK TABLES `solicitudes_registro` WRITE;
/*!40000 ALTER TABLE `solicitudes_registro` DISABLE KEYS */;
INSERT INTO `solicitudes_registro` VALUES (2,NULL,'rechazada','2026-10-18 21:44:43','2026-10-18 21:44:43',NULL),(4,NULL,'rechazada','2026-10-18 22:37:58','2026-10-19 19:56:15',1),(6,40,'aprobada','2026-10-18 22:49:19','2026-10-18 22:50:08',1),(7,41,'aprobada','2026-10-18 22:52:28','2026-10-18 22:52:51',1),(8,45,'aprobada','2026-10-18 23:10:24','2026-10-18 23:11:06',1),(9,47,'aprobada','2026-10-19 20:03:34','2026-10-19 20:05:04',1);
/*!40000 ALTER TABLE `solicitudes_registro` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rol_id` bigint(20) unsigned NOT NULL,
  `documento` varchar(30) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `correo` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_acceso` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `documento` (`documento`),
  UNIQUE KEY `correo` (`correo`),
  KEY `fk_usuarios_rol` (`rol_id`),
  CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,1,'100000001','Mauricio','Medina Camacho','mauriciomedinacamacho5@gmail.com','$2y$10$BjNrj4zfAruFsZ/DGLCG6eFN7Fta.DX8.dXeGWgED/mjBw7txODGS',NULL,NULL,1,'2026-10-19 20:09:56','2026-10-18 23:36:21','2026-10-20 01:09:56'),(2,2,'100000002','Instructora','Demo','instructor@institucion.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',NULL,NULL,1,NULL,'2026-10-18 23:36:21','2026-10-18 23:36:21'),(8,4,'1075250453','Mauricio','Medina Camacho','mauriciomedina.fisica2024@gmail.com','$2y$10$YSv9xLyWQ3s6xklPh8eXP.GfwfPnNluHmPRilsU6COAY0xDaXwICO','3162819576',NULL,1,'2026-10-18 18:47:29','2026-10-18 23:43:19','2026-10-18 23:47:29'),(24,3,'1075250456','Emanuel','Camacho','emanuelcamacho5@gmail.com','$2y$10$XipwoOgnMdBQjB/X/qbqhu8CxojB7cEr.qJbOjRqgriPeNVRAuLAK','3227306150',NULL,1,'2026-10-18 22:33:26','2026-10-19 02:25:04','2026-10-19 03:33:26'),(28,3,'7702285','Alvaro','Torres','alvaroto10@hotmail.com','$2y$10$TzeGOYVTCWZ4iSZEQCGvquoBkmP9lKOosLcOYb.06uQKE8bzJEE62','3212595048',NULL,1,'2026-10-18 22:18:20','2026-10-19 02:33:07','2026-10-19 03:18:20'),(40,2,'1075250458','Luna','Torres','luna@gmail.com','$2y$10$SMwOJbMvvK88Mpjtzmu48OfwP54PYgU8rWBKDqCHMA4Zkmk.LnI0a','3242962045',NULL,1,NULL,'2026-10-19 03:49:19','2026-10-19 03:50:08'),(41,4,'1523782515','Victor','Murcia','murci@gmail.com','$2y$10$GtYd/cbRTI.V9SZrBphToOy1ed9.1qDwXAoc8SkKg0DYPzOZ/WNBm','7869542130',NULL,1,NULL,'2026-10-19 03:52:28','2026-10-19 03:52:51'),(45,2,'1075250459','Mauricio','Medina Camacho','medinacamachomauricio0@gmail.com','$2y$10$PH7xMRqAHOzkwKQjFUjGJ.AcguuNhEvzunH.kCaaakvavH7MHdy6O','3117764144',NULL,1,'2026-10-18 23:11:57','2026-10-19 04:10:24','2026-10-19 04:11:57'),(46,3,'1075250451','margareth','Camacho','margarthcamacho@gmail.com','$2y$10$hmWa7uv2ltAKO1FSB3eGN.gE9OS5A8QwTgAVQPTLSox0GPQVEBX/W','3227306150','uploads/profiles/2f2ca5ce9edf5e89eb0a3a9f970a4073ac1b464679173f70.jpg',1,'2026-10-19 19:59:48','2026-10-20 00:52:58','2026-10-20 00:59:48'),(47,2,'100000000','Alberto','Palacios','Alberto@gmail.com','$2y$10$ChdE5Cw7huhGt8/N6yvAxeMKvNwXwQYe2ChnuApW4pbZ84Df99b6K','3215879645','uploads/profiles/b99fedd5aebd074600a5fac45226876be284afb2ce68ae36.jpg',1,'2026-10-19 20:06:05','2026-10-20 01:03:34','2026-10-20 01:06:05');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `visitantes`
--

DROP TABLE IF EXISTS `visitantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `visitantes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `documento` varchar(30) NOT NULL,
  `nombres` varchar(160) NOT NULL,
  `entidad` varchar(150) DEFAULT NULL,
  `motivo` varchar(300) NOT NULL,
  `anfitrion_id` bigint(20) unsigned DEFAULT NULL,
  `ingreso_en` datetime NOT NULL DEFAULT current_timestamp(),
  `salida_en` datetime DEFAULT NULL,
  `registrado_por` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_visitantes_anfitrion` (`anfitrion_id`),
  KEY `fk_visitantes_registrador` (`registrado_por`),
  CONSTRAINT `fk_visitantes_anfitrion` FOREIGN KEY (`anfitrion_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_visitantes_registrador` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `visitantes`
--

LOCK TABLES `visitantes` WRITE;
/*!40000 ALTER TABLE `visitantes` DISABLE KEYS */;
/*!40000 ALTER TABLE `visitantes` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-19 20:33:04
