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
-- Current Database: `proyecto01`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `proyecto01` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `proyecto01`;

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
-- Table structure for table `alertas_asistencia`
--

DROP TABLE IF EXISTS `alertas_asistencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alertas_asistencia` (
  `usuario_id` bigint(20) unsigned NOT NULL,
  `ficha_id` bigint(20) unsigned NOT NULL,
  `tipo` enum('amarilla','roja') NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 0,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`usuario_id`,`ficha_id`,`tipo`),
  KEY `fk_alertas_asistencia_ficha` (`ficha_id`),
  CONSTRAINT `fk_alertas_asistencia_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alertas_asistencia_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alertas_asistencia`
--

LOCK TABLES `alertas_asistencia` WRITE;
/*!40000 ALTER TABLE `alertas_asistencia` DISABLE KEYS */;
/*!40000 ALTER TABLE `alertas_asistencia` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ambientes`
--

LOCK TABLES `ambientes` WRITE;
/*!40000 ALTER TABLE `ambientes` DISABLE KEYS */;
INSERT INTO `ambientes` VALUES (2,'101-1','Laboratorio de sistemas','Sede principal',30,'disponible',NULL),(3,'101-2','Laboratorio de sistemas','Sede principal',30,'disponible',NULL),(4,'205-1','Aula','Sede principal',35,'disponible',NULL),(5,'209-1','Aula','Sede principal',32,'disponible',NULL),(6,'209-2','Taller de redes','Sede principal',25,'disponible',NULL),(7,'301-1','Auditorio','Sede principal',80,'disponible',NULL);
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
  `estado_justificacion` enum('pendiente','justificada','injustificada') NOT NULL DEFAULT 'pendiente',
  `registrado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asistencia_sesion` (`usuario_id`,`ficha_id`,`fecha`,`horario_id`),
  KEY `fk_asistencias_ficha` (`ficha_id`),
  KEY `fk_asistencias_horario` (`horario_id`),
  KEY `fk_asistencias_registrador` (`registrado_por`),
  CONSTRAINT `fk_asistencias_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`),
  CONSTRAINT `fk_asistencias_horario` FOREIGN KEY (`horario_id`) REFERENCES `horarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_asistencias_registrador` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_asistencias_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asistencias`
--

LOCK TABLES `asistencias` WRITE;
/*!40000 ALTER TABLE `asistencias` DISABLE KEYS */;
INSERT INTO `asistencias` VALUES (13,15,5,NULL,'2026-10-23','presente',19,NULL,'pendiente','2026-10-23 05:05:43'),(14,14,5,NULL,'2026-10-23','presente',19,NULL,'pendiente','2026-10-23 05:05:45'),(15,16,5,NULL,'2026-10-23','ausente',19,NULL,'pendiente','2026-10-23 05:05:48');
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
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria`
--

LOCK TABLES `auditoria` WRITE;
/*!40000 ALTER TABLE `auditoria` DISABLE KEYS */;
INSERT INTO `auditoria` VALUES (1,1,'actualizar_pin_carnet','carnets',1,NULL,'2026-10-22 23:58:21'),(2,1,'inicio_sesion_temporal','usuarios',1,NULL,'2026-10-22 23:58:21'),(3,1,'cierre_sesion','usuarios',1,NULL,'2026-10-23 00:21:15'),(4,1,'actualizar_pin_carnet','carnets',1,NULL,'2026-10-23 00:21:52'),(5,1,'inicio_sesion_temporal','usuarios',1,NULL,'2026-10-23 00:21:52'),(23,1,'cierre_sesion','usuarios',1,NULL,'2026-10-23 01:14:12'),(24,NULL,'solicitar_registro_personal','usuarios',4,'{\"rol\":\"instructor\"}','2026-10-23 01:15:29'),(25,1,'actualizar_pin_carnet','carnets',1,NULL,'2026-10-23 01:16:03'),(26,1,'inicio_sesion_temporal','usuarios',1,NULL,'2026-10-23 01:16:03'),(27,1,'aprobar_registro_personal','solicitudes_registro',1,'{\"rol\":\"instructor\"}','2026-10-23 01:16:09'),(28,1,'cierre_sesion','usuarios',1,NULL,'2026-10-23 01:16:10'),(29,NULL,'actualizar_pin_carnet','carnets',4,NULL,'2026-10-23 01:16:42'),(30,NULL,'inicio_sesion_temporal','usuarios',4,NULL,'2026-10-23 01:16:42'),(31,NULL,'cierre_sesion','usuarios',4,NULL,'2026-10-23 01:17:19'),(34,1,'actualizar_pin_carnet','carnets',1,NULL,'2026-10-23 04:23:33'),(35,1,'inicio_sesion_temporal','usuarios',1,NULL,'2026-10-23 04:23:33'),(36,1,'actualizar','usuarios',1,NULL,'2026-10-23 04:24:11'),(37,1,'eliminar','usuarios',4,NULL,'2026-10-23 04:24:25'),(38,1,'cierre_sesion','usuarios',1,NULL,'2026-10-23 04:27:33'),(39,14,'actualizar_pin_carnet','carnets',14,NULL,'2026-10-23 04:58:12'),(40,14,'inicio_sesion_temporal','usuarios',14,NULL,'2026-10-23 04:58:12'),(41,14,'cierre_sesion','usuarios',14,NULL,'2026-10-23 05:01:44'),(42,19,'actualizar_pin_carnet','carnets',19,NULL,'2026-10-23 05:02:16'),(43,19,'inicio_sesion_temporal','usuarios',19,NULL,'2026-10-23 05:02:16'),(44,19,'crear','asistencias',13,NULL,'2026-10-23 05:05:43'),(45,19,'crear','asistencias',14,NULL,'2026-10-23 05:05:45'),(46,19,'crear','asistencias',15,NULL,'2026-10-23 05:05:48'),(47,19,'actualizar','asistencias',15,NULL,'2026-10-23 05:05:49'),(48,19,'actualizar','asistencias',15,NULL,'2026-10-23 05:05:50'),(49,19,'cierre_sesion','usuarios',19,NULL,'2026-10-23 05:07:04'),(50,24,'actualizar_pin_carnet','carnets',24,NULL,'2026-10-23 05:07:28'),(51,24,'inicio_sesion_temporal','usuarios',24,NULL,'2026-10-23 05:07:28'),(52,24,'cierre_sesion','usuarios',24,NULL,'2026-10-23 05:07:28'),(53,25,'actualizar_pin_carnet','carnets',25,NULL,'2026-10-23 05:07:52'),(54,25,'inicio_sesion_temporal','usuarios',25,NULL,'2026-10-23 05:07:52'),(55,25,'cierre_sesion','usuarios',25,NULL,'2026-10-23 05:07:52'),(56,25,'actualizar_pin_carnet','carnets',25,NULL,'2026-10-23 05:07:53'),(57,25,'inicio_sesion_temporal','usuarios',25,NULL,'2026-10-23 05:07:53'),(58,25,'cierre_sesion','usuarios',25,NULL,'2026-10-23 05:07:53'),(59,25,'actualizar_pin_carnet','carnets',25,NULL,'2026-10-23 05:07:53'),(60,25,'inicio_sesion_temporal','usuarios',25,NULL,'2026-10-23 05:07:53'),(61,25,'cierre_sesion','usuarios',25,NULL,'2026-10-23 05:07:53'),(62,24,'actualizar_pin_carnet','carnets',24,NULL,'2026-10-23 05:08:49'),(63,24,'inicio_sesion_temporal','usuarios',24,NULL,'2026-10-23 05:08:49'),(64,24,'cierre_sesion','usuarios',24,NULL,'2026-10-23 05:09:04'),(65,17,'actualizar_pin_carnet','carnets',17,NULL,'2026-10-23 05:09:24'),(66,17,'inicio_sesion_temporal','usuarios',17,NULL,'2026-10-23 05:09:24'),(67,17,'cierre_sesion','usuarios',17,NULL,'2026-10-23 05:09:40');
/*!40000 ALTER TABLE `auditoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bitacoras_productivas`
--

DROP TABLE IF EXISTS `bitacoras_productivas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bitacoras_productivas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `etapa_id` bigint(20) unsigned NOT NULL,
  `autor_id` bigint(20) unsigned NOT NULL,
  `entrada` text NOT NULL,
  `registrada_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bitacoras_etapa_fecha` (`etapa_id`,`registrada_en`),
  KEY `fk_bitacoras_autor` (`autor_id`),
  CONSTRAINT `fk_bitacoras_autor` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_bitacoras_etapa` FOREIGN KEY (`etapa_id`) REFERENCES `etapa_productiva` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bitacoras_productivas`
--

LOCK TABLES `bitacoras_productivas` WRITE;
/*!40000 ALTER TABLE `bitacoras_productivas` DISABLE KEYS */;
/*!40000 ALTER TABLE `bitacoras_productivas` ENABLE KEYS */;
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
-- Table structure for table `calendario_academico`
--

DROP TABLE IF EXISTS `calendario_academico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendario_academico` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `tipo` enum('festivo','vacaciones') NOT NULL,
  `descripcion` varchar(250) NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_calendario_fecha_tipo` (`fecha`,`tipo`),
  KEY `idx_calendario_fecha_estado` (`fecha`,`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendario_academico`
--

LOCK TABLES `calendario_academico` WRITE;
/*!40000 ALTER TABLE `calendario_academico` DISABLE KEYS */;
INSERT INTO `calendario_academico` VALUES (1,'2026-11-02','festivo','Día de Todos los Santos','activo','2026-10-23 04:47:02','2026-10-23 04:47:02'),(2,'2026-11-16','festivo','Independencia de Cartagena','activo','2026-10-23 04:47:02','2026-10-23 04:47:02'),(3,'2026-12-08','festivo','Día de la Inmaculada Concepción','activo','2026-10-23 04:47:02','2026-10-23 04:47:02'),(4,'2026-12-25','festivo','Navidad','activo','2026-10-23 04:47:02','2026-10-23 04:47:02'),(5,'2026-12-19','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:02','2026-10-23 04:47:02'),(6,'2026-12-20','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:02','2026-10-23 04:47:02'),(7,'2026-12-21','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:02','2026-10-23 04:47:02'),(8,'2026-12-22','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:02','2026-10-23 04:47:02'),(9,'2026-12-23','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:02','2026-10-23 04:47:02'),(10,'2026-12-24','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(11,'2026-12-25','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(12,'2026-12-26','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(13,'2026-12-27','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(14,'2026-12-28','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(15,'2026-12-29','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(16,'2026-12-30','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(17,'2026-12-31','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(18,'2027-01-01','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(19,'2027-01-02','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(20,'2027-01-03','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(21,'2027-01-04','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(22,'2027-01-05','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(23,'2027-01-06','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(24,'2027-01-07','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(25,'2027-01-08','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(26,'2027-01-09','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(27,'2027-01-10','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(28,'2027-01-11','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03'),(29,'2027-01-12','vacaciones','Receso de fin de año (prueba)','activo','2026-10-23 04:47:03','2026-10-23 04:47:03');
/*!40000 ALTER TABLE `calendario_academico` ENABLE KEYS */;
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
  `pin_hash` varchar(255) DEFAULT NULL,
  `pin_intentos_fallidos` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `pin_bloqueado_hasta` datetime DEFAULT NULL,
  `qr_entregado` tinyint(1) NOT NULL DEFAULT 0,
  `estado` enum('activo','desactivado') NOT NULL DEFAULT 'activo',
  `emitido_en` datetime NOT NULL DEFAULT current_timestamp(),
  `vence_en` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`),
  UNIQUE KEY `codigo_qr` (`codigo_qr`),
  CONSTRAINT `fk_carnets_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `carnets`
--

LOCK TABLES `carnets` WRITE;
/*!40000 ALTER TABLE `carnets` DISABLE KEYS */;
INSERT INTO `carnets` VALUES (1,1,'b315622a-4852-4110-adc6-29e2e7ba5ed0','$2y$10$n1roxHlOvj8eObiVTL5AF.Vek3X9aNiF2OyOPT6q5ONc2qxlIu2Tq',0,NULL,0,'activo','2026-10-22 18:57:37',NULL),(5,14,'daa2cee3-ba5e-4992-8d14-c04656a78e0f','$2y$10$wHcjkISfQhbxVmTZER5.F.LpfuZUbIywEJVKibz9QG/XHVReEI1Ay',0,NULL,0,'activo','2026-10-22 23:47:01',NULL),(6,15,'ca1fb570-bf9c-4cac-9b9e-3d32620fb284','$2y$10$yB/ftAidFiYOskvha2eE6uC3Bi7LIf7/BUw09pMBu.l2AemeiJkda',0,NULL,0,'activo','2026-10-22 23:47:01',NULL),(7,16,'0535f5be-d0a9-4ea4-9650-9a94193f926c','$2y$10$BVwXU8K2IpfwPVT39kK5/.1oC9gtb26SF/FfrbCqysy/LBcAm.L4e',0,NULL,0,'activo','2026-10-22 23:47:01',NULL),(8,17,'b5dfbede-e6a6-4cbf-9b5b-d0f53d279060','$2y$10$JR7GMuw4ACvkykpCbFAWseOxddWA9R3qXUlq6oRnGaZIOHOFPslBC',0,NULL,0,'activo','2026-10-22 23:47:01',NULL),(9,18,'2d183f03-d092-4dba-8b57-47404bfd1e56','$2y$10$4qOeg1Re08eEc8GMqm5nPuyR8t5L71C0Ij7vyW4.7puY5yHG7/PyK',0,NULL,0,'activo','2026-10-22 23:47:01',NULL),(10,19,'3a3f9ff6-9321-4354-85db-dba116c48cd7','$2y$10$Fbvwu0GKBjM4t.6vr67DQOhYltez1VF9T/xTOoJkiHyzLW6a5U0hu',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(11,20,'3439b486-935c-434b-9b00-64d296f42d52','$2y$10$TfuF29CLZtpNrhMfDqpfE.PDwE3FGCBdtGFYKHaD.ORzbbvSXlouW',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(12,21,'5c65cb58-1087-4641-a4bd-2eafc222d65a','$2y$10$IDhr6s9trCJpbncKBy8OMe8Jb91yXAQ1aYPq/SKJf16.PNgyZPJXa',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(13,22,'ee30eec9-511d-4f87-99e5-87386df414a9','$2y$10$rEQRgaiycWcP6DeJgVUduequ.OasNP68BJjW.HoogH2pwRVF79fO.',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(14,23,'bf686c76-368f-4ef5-a1ca-dfcba2d90dbf','$2y$10$hpTzmpMWAPDSatS2sak9PeNq/CyLzvwLZ6KMSaEbpMHnTZ1JSatZ2',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(15,24,'efb5c324-d6f5-41e0-b1c2-26075ef154b7','$2y$10$xPgPeQI.v6DL9P/V8yMY7Oav/5o1GWqVEr1wat1yoSSfkFGtpTsbu',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(16,25,'8fdf6bbf-9817-44f4-bb65-b96cca9bad40','$2y$10$zx2xE9xaFFDaLmndWOAfMeoE7rjgbdzDoRXzzDiaM9TJZTlPwA7dS',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(17,26,'c424df64-015f-4725-9e41-a0cd58793888','$2y$10$UXYbOiHkWn4W3XfE/3aem.wIhSnSSsSOU.B1y3N81RJmV3hZ6mOfm',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(18,27,'01d71f2e-a6e0-49bd-98e6-44c9dd9b6331','$2y$10$9HhzuNV0PqaeGvSC0etmtOOZWRhP0BBA5tDG4114vILQzAfAxcU1.',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(19,28,'74026ac8-65c2-46c2-a763-9647ba5a742e','$2y$10$9zrL.QLR4ok1olAEDLjgt.S8X2OCZex.0pti.FfSSLkm6sSvarzle',0,NULL,0,'activo','2026-10-22 23:47:02',NULL);
/*!40000 ALTER TABLE `carnets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cierres_trimestre`
--

DROP TABLE IF EXISTS `cierres_trimestre`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cierres_trimestre` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ficha_id` bigint(20) unsigned NOT NULL,
  `trimestre` tinyint(3) unsigned NOT NULL,
  `desde` date DEFAULT NULL,
  `hasta` date NOT NULL,
  `resumen_asistencia` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`resumen_asistencia`)),
  `resumen_juicios` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`resumen_juicios`)),
  `cerrado_por` bigint(20) unsigned NOT NULL,
  `cerrado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cierre_ficha_trimestre` (`ficha_id`,`trimestre`),
  KEY `idx_cierres_hasta` (`hasta`),
  KEY `fk_cierres_usuario` (`cerrado_por`),
  CONSTRAINT `fk_cierres_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`),
  CONSTRAINT `fk_cierres_usuario` FOREIGN KEY (`cerrado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cierres_trimestre`
--

LOCK TABLES `cierres_trimestre` WRITE;
/*!40000 ALTER TABLE `cierres_trimestre` DISABLE KEYS */;
/*!40000 ALTER TABLE `cierres_trimestre` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `competencias`
--

DROP TABLE IF EXISTS `competencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competencias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) NOT NULL,
  `nombre` varchar(180) NOT NULL,
  `programa_id` bigint(20) unsigned NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_competencias_programa_estado` (`programa_id`,`estado`),
  CONSTRAINT `fk_competencias_programa` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `competencias`
--

LOCK TABLES `competencias` WRITE;
/*!40000 ALTER TABLE `competencias` DISABLE KEYS */;
INSERT INTO `competencias` VALUES (2,'C01','Modelar la base de datos del sistema',2,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(3,'C02','Desarrollar la solución de software',2,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(4,'C03','Implantar la solución de software',2,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(5,'P002-C01','Competencia de prueba',3,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(6,'P003-C01','Competencia de prueba',4,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(7,'P004-C01','Competencia de prueba',5,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(8,'P005-C01','Competencia de prueba',6,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01');
/*!40000 ALTER TABLE `competencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuracion_sistema`
--

DROP TABLE IF EXISTS `configuracion_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion_sistema` (
  `clave` varchar(80) NOT NULL,
  `valor` smallint(5) unsigned NOT NULL,
  `actualizado_por` bigint(20) unsigned DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`clave`),
  KEY `fk_configuracion_sistema_usuario` (`actualizado_por`),
  CONSTRAINT `fk_configuracion_sistema_usuario` FOREIGN KEY (`actualizado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracion_sistema`
--

LOCK TABLES `configuracion_sistema` WRITE;
/*!40000 ALTER TABLE `configuracion_sistema` DISABLE KEYS */;
INSERT INTO `configuracion_sistema` VALUES ('asistencia_alerta_amarilla',90,NULL,'2026-10-22 23:15:30'),('asistencia_alerta_roja',80,NULL,'2026-10-22 23:15:30'),('dias_habiles_excusa',3,NULL,'2026-10-22 23:15:30'),('horas_correccion_asistencia',48,NULL,'2026-10-22 23:15:30'),('minutos_prestamo_llaves',180,NULL,'2026-10-23 04:11:25'),('pin_bloqueo_minutos',5,NULL,'2026-10-22 23:15:30'),('pin_intentos_maximos',3,NULL,'2026-10-22 23:15:30');
/*!40000 ALTER TABLE `configuracion_sistema` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `datos_prueba_seed`
--

DROP TABLE IF EXISTS `datos_prueba_seed`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `datos_prueba_seed` (
  `semilla` varchar(60) NOT NULL,
  `entidad` varchar(80) NOT NULL,
  `registro_id` bigint(20) unsigned NOT NULL,
  `marcado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`semilla`,`entidad`,`registro_id`),
  KEY `idx_datos_prueba_entidad` (`entidad`,`registro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `datos_prueba_seed`
--

LOCK TABLES `datos_prueba_seed` WRITE;
/*!40000 ALTER TABLE `datos_prueba_seed` DISABLE KEYS */;
INSERT INTO `datos_prueba_seed` VALUES ('proyecto01-datos-prueba-2026-10','ambientes',2,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','ambientes',3,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','ambientes',4,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','ambientes',5,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','ambientes',6,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','ambientes',7,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','calendario_academico',1,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',2,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',3,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',4,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',5,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',6,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',7,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',8,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',9,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',10,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',11,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',12,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',13,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',14,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',15,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',16,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',17,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',18,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',19,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',20,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',21,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',22,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',23,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',24,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',25,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',26,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',27,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',28,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',29,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','carnets',5,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','carnets',6,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','carnets',7,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','carnets',8,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','carnets',9,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','carnets',10,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',11,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',12,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',13,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',14,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',15,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',16,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',17,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',18,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',19,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','competencias',2,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','competencias',3,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','competencias',4,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','competencias',5,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','competencias',6,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','competencias',7,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','competencias',8,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas',5,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas',6,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas',7,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas',8,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas',9,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas_instructores',5,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','fichas_instructores',6,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','fichas_instructores',7,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','fichas_instructores',8,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','fichas_instructores',9,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','fichas_instructores',10,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','fichas_usuarios',5,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas_usuarios',6,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas_usuarios',7,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas_usuarios',8,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas_usuarios',9,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','horarios',2,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','horarios',3,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','horarios',4,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','horarios',5,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','horarios',6,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','horarios',7,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','horarios',8,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','horarios',9,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','programas',2,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','programas',3,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','programas',4,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','programas',5,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','programas',6,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',2,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',3,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',4,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',5,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',6,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',7,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',8,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',9,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',10,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',11,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','usuarios',14,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','usuarios',15,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','usuarios',16,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','usuarios',17,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','usuarios',18,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','usuarios',19,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','usuarios',20,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',21,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',22,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',23,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',24,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',25,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',26,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',27,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',28,'2026-10-23 04:47:02');
/*!40000 ALTER TABLE `datos_prueba_seed` ENABLE KEYS */;
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
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `visitante_id` bigint(20) unsigned DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `tipo` enum('portatil','teclado','mouse','maleta','otro') NOT NULL DEFAULT 'otro',
  `marca` varchar(100) DEFAULT NULL,
  `color` varchar(60) DEFAULT NULL,
  `foto` mediumtext DEFAULT NULL,
  `categoria` varchar(80) NOT NULL,
  `estado` enum('disponible','asignado','prestado','mantenimiento','baja') NOT NULL DEFAULT 'disponible',
  `ubicacion` varchar(180) NOT NULL,
  `autorizado` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial` (`serial`),
  UNIQUE KEY `codigo_qr` (`codigo_qr`),
  KEY `idx_equipos_propietario_usuario` (`usuario_id`),
  KEY `idx_equipos_propietario_visitante` (`visitante_id`),
  CONSTRAINT `fk_equipos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_equipos_visitante` FOREIGN KEY (`visitante_id`) REFERENCES `visitantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipos`
--

LOCK TABLES `equipos` WRITE;
/*!40000 ALTER TABLE `equipos` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `etapa_productiva`
--

DROP TABLE IF EXISTS `etapa_productiva`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `etapa_productiva` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ficha_id` bigint(20) unsigned NOT NULL,
  `aprendiz_id` bigint(20) unsigned NOT NULL,
  `alternativa` varchar(120) NOT NULL,
  `empresa` varchar(180) NOT NULL,
  `instructor_seguimiento_id` bigint(20) unsigned NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('activa','finalizada','suspendida') NOT NULL DEFAULT 'activa',
  `creado_por` bigint(20) unsigned NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etapa_ficha_aprendiz` (`ficha_id`,`aprendiz_id`),
  KEY `idx_etapa_instructor_estado` (`instructor_seguimiento_id`,`estado`),
  KEY `fk_etapa_aprendiz` (`aprendiz_id`),
  KEY `fk_etapa_creador` (`creado_por`),
  CONSTRAINT `fk_etapa_aprendiz` FOREIGN KEY (`aprendiz_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_etapa_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_etapa_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`),
  CONSTRAINT `fk_etapa_instructor` FOREIGN KEY (`instructor_seguimiento_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `etapa_productiva`
--

LOCK TABLES `etapa_productiva` WRITE;
/*!40000 ALTER TABLE `etapa_productiva` DISABLE KEYS */;
/*!40000 ALTER TABLE `etapa_productiva` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fichas`
--

DROP TABLE IF EXISTS `fichas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fichas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` char(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `programa_id` bigint(20) unsigned DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `programa` varchar(150) NOT NULL DEFAULT '',
  `trimestre` tinyint(3) unsigned DEFAULT NULL,
  `jornada` varchar(30) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('activa','finalizada','suspendida') NOT NULL DEFAULT 'activa',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_fichas_programa` (`programa_id`),
  CONSTRAINT `fk_fichas_programa` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`),
  CONSTRAINT `chk_fichas_codigo` CHECK (`codigo` regexp '^[0-9]{7}$')
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fichas`
--

LOCK TABLES `fichas` WRITE;
/*!40000 ALTER TABLE `fichas` DISABLE KEYS */;
INSERT INTO `fichas` VALUES (5,'3413988',2,'Análisis y Desarrollo de Software','Análisis y Desarrollo de Software',4,'diurna','2026-01-26','2028-04-28','activa'),(6,'3102457',3,'Gestión Contable y de Información Financiera','Gestión Contable y de Información Financiera',3,'mañana','2026-04-13','2028-07-14','activa'),(7,'3256781',4,'Programación de Software','Programación de Software',2,'tarde','2026-07-13','2027-10-15','activa'),(8,'3378124',5,'Sistemas Teleinformáticos','Sistemas Teleinformáticos',2,'noche','2026-07-13','2027-10-15','activa'),(9,'3489562',6,'Gestión Empresarial','Gestión Empresarial',1,'mixta','2026-10-05','2029-01-12','activa');
/*!40000 ALTER TABLE `fichas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fichas_instructores`
--

DROP TABLE IF EXISTS `fichas_instructores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fichas_instructores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ficha_id` bigint(20) unsigned NOT NULL,
  `instructor_id` bigint(20) unsigned NOT NULL,
  `asignado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ficha_instructor` (`ficha_id`,`instructor_id`),
  KEY `idx_fichas_instructores_instructor` (`instructor_id`),
  CONSTRAINT `fk_fichas_instructores_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fichas_instructores_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fichas_instructores`
--

LOCK TABLES `fichas_instructores` WRITE;
/*!40000 ALTER TABLE `fichas_instructores` DISABLE KEYS */;
INSERT INTO `fichas_instructores` VALUES (5,5,19,'2026-10-23 04:47:02'),(6,5,20,'2026-10-23 04:47:02'),(7,7,21,'2026-10-23 04:47:02'),(8,8,22,'2026-10-23 04:47:02'),(9,6,23,'2026-10-23 04:47:02'),(10,9,23,'2026-10-23 04:47:02');
/*!40000 ALTER TABLE `fichas_instructores` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fichas_usuarios`
--

LOCK TABLES `fichas_usuarios` WRITE;
/*!40000 ALTER TABLE `fichas_usuarios` DISABLE KEYS */;
INSERT INTO `fichas_usuarios` VALUES (5,5,14,'2026-10-23 04:47:01'),(6,5,15,'2026-10-23 04:47:01'),(7,5,16,'2026-10-23 04:47:01'),(8,7,17,'2026-10-23 04:47:01'),(9,7,18,'2026-10-23 04:47:01');
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
  `competencia_id` bigint(20) unsigned DEFAULT NULL,
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
  KEY `idx_horarios_competencia` (`competencia_id`),
  CONSTRAINT `fk_horarios_ambiente` FOREIGN KEY (`ambiente_id`) REFERENCES `ambientes` (`id`),
  CONSTRAINT `fk_horarios_competencia` FOREIGN KEY (`competencia_id`) REFERENCES `competencias` (`id`),
  CONSTRAINT `fk_horarios_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`),
  CONSTRAINT `fk_horarios_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `chk_horarios_dia` CHECK (`dia_semana` between 1 and 7),
  CONSTRAINT `chk_horarios_horas` CHECK (`hora_inicio` < `hora_fin`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horarios`
--

LOCK TABLES `horarios` WRITE;
/*!40000 ALTER TABLE `horarios` DISABLE KEYS */;
INSERT INTO `horarios` VALUES (2,5,2,2,19,1,'07:00:00','11:00:00','Modelar la base de datos del sistema'),(3,5,3,5,20,2,'07:00:00','11:00:00','Desarrollar la solución de software'),(4,5,3,3,19,3,'07:00:00','11:00:00','Desarrollar la solución de software'),(5,5,4,5,20,4,'07:00:00','11:00:00','Implantar la solución de software'),(6,7,6,6,21,1,'13:00:00','17:00:00','Competencia de prueba'),(7,7,6,2,21,3,'13:00:00','17:00:00','Competencia de prueba'),(8,8,7,4,22,2,'18:00:00','22:00:00','Competencia de prueba'),(9,6,5,4,23,4,'07:00:00','11:00:00','Competencia de prueba');
/*!40000 ALTER TABLE `horarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ingreso_diario_equipos`
--

DROP TABLE IF EXISTS `ingreso_diario_equipos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ingreso_diario_equipos` (
  `ingreso_diario_id` bigint(20) unsigned NOT NULL,
  `equipo_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`ingreso_diario_id`,`equipo_id`),
  KEY `fk_ingreso_diario_equipos_equipo` (`equipo_id`),
  CONSTRAINT `fk_ingreso_diario_equipos_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ingreso_diario_equipos_ingreso` FOREIGN KEY (`ingreso_diario_id`) REFERENCES `ingresos_diarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ingreso_diario_equipos`
--

LOCK TABLES `ingreso_diario_equipos` WRITE;
/*!40000 ALTER TABLE `ingreso_diario_equipos` DISABLE KEYS */;
/*!40000 ALTER TABLE `ingreso_diario_equipos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ingresos_diarios`
--

DROP TABLE IF EXISTS `ingresos_diarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ingresos_diarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `visitante_id` bigint(20) unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `token_hash` char(64) NOT NULL,
  `usado_en` datetime DEFAULT NULL,
  `acceso_abierto` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  UNIQUE KEY `uq_ingresos_diarios_usuario` (`usuario_id`,`fecha`),
  UNIQUE KEY `uq_ingresos_diarios_visitante` (`visitante_id`,`fecha`),
  CONSTRAINT `fk_ingresos_diarios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ingresos_diarios_visitante` FOREIGN KEY (`visitante_id`) REFERENCES `visitantes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_ingresos_diarios_persona` CHECK (`usuario_id` is not null and `visitante_id` is null or `usuario_id` is null and `visitante_id` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ingresos_diarios`
--

LOCK TABLES `ingresos_diarios` WRITE;
/*!40000 ALTER TABLE `ingresos_diarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `ingresos_diarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `juicios_resultados`
--

DROP TABLE IF EXISTS `juicios_resultados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `juicios_resultados` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `estudiante_id` bigint(20) unsigned NOT NULL,
  `resultado_id` bigint(20) unsigned NOT NULL,
  `juicio` enum('aprobado','no_aprobado','por_evaluar') NOT NULL DEFAULT 'por_evaluar',
  `evaluado_por` bigint(20) unsigned NOT NULL,
  `evaluado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_juicios_estudiante_resultado` (`estudiante_id`,`resultado_id`,`evaluado_en`),
  KEY `fk_juicios_resultado` (`resultado_id`),
  KEY `fk_juicios_evaluador` (`evaluado_por`),
  CONSTRAINT `fk_juicios_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_juicios_evaluador` FOREIGN KEY (`evaluado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_juicios_resultado` FOREIGN KEY (`resultado_id`) REFERENCES `resultados_aprendizaje` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `juicios_resultados`
--

LOCK TABLES `juicios_resultados` WRITE;
/*!40000 ALTER TABLE `juicios_resultados` DISABLE KEYS */;
/*!40000 ALTER TABLE `juicios_resultados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `llaves_ambientes`
--

DROP TABLE IF EXISTS `llaves_ambientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `llaves_ambientes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ambiente_id` bigint(20) unsigned NOT NULL,
  `instructor_id` bigint(20) unsigned NOT NULL,
  `prestada_por` bigint(20) unsigned NOT NULL,
  `prestada_en` datetime NOT NULL DEFAULT current_timestamp(),
  `vence_en` datetime NOT NULL,
  `devuelta_en` datetime DEFAULT NULL,
  `devuelta_por` bigint(20) unsigned DEFAULT NULL,
  `estado` enum('prestada','devuelta','vencida') NOT NULL DEFAULT 'prestada',
  PRIMARY KEY (`id`),
  KEY `idx_llaves_estado_vence` (`estado`,`vence_en`),
  KEY `idx_llaves_ambiente` (`ambiente_id`,`estado`),
  KEY `fk_llaves_instructor` (`instructor_id`),
  KEY `fk_llaves_prestada_por` (`prestada_por`),
  KEY `fk_llaves_devuelta_por` (`devuelta_por`),
  CONSTRAINT `fk_llaves_ambiente` FOREIGN KEY (`ambiente_id`) REFERENCES `ambientes` (`id`),
  CONSTRAINT `fk_llaves_devuelta_por` FOREIGN KEY (`devuelta_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_llaves_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_llaves_prestada_por` FOREIGN KEY (`prestada_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `llaves_ambientes`
--

LOCK TABLES `llaves_ambientes` WRITE;
/*!40000 ALTER TABLE `llaves_ambientes` DISABLE KEYS */;
/*!40000 ALTER TABLE `llaves_ambientes` ENABLE KEYS */;
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
  `tipo` enum('asistencia','excusa','formacion','sistema') DEFAULT NULL,
  `remitente_id` bigint(20) unsigned DEFAULT NULL,
  `mensaje` text NOT NULL,
  `evento_clave` varchar(190) DEFAULT NULL,
  `leida_en` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notificaciones_evento_usuario` (`evento_clave`,`usuario_id`),
  KEY `fk_notificaciones_usuario` (`usuario_id`),
  KEY `fk_notificaciones_remitente` (`remitente_id`),
  CONSTRAINT `fk_notificaciones_remitente` FOREIGN KEY (`remitente_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notificaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificaciones`
--

LOCK TABLES `notificaciones` WRITE;
/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
INSERT INTO `notificaciones` VALUES (12,1,'Alerta roja de asistencia','asistencia',NULL,'Quién: Sistema de alertas. Qué: Fixture Automation completó tres días consecutivos de faltas sin excusa. Asistencia actual: 0%. Fecha de corte: 2026-10-23. Ficha: 4413164. Sesión: resumen de las sesiones registradas.','alerta-asistencia:roja:13:4:20261023060723',NULL,'2026-10-23 04:07:23'),(17,15,'Actualización de asistencia · Andrés Perdomo Silva',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:43'),(18,1,'Actualización de asistencia · Andrés Perdomo Silva',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:43'),(19,19,'Actualización de asistencia · Andrés Perdomo Silva',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:43'),(20,20,'Actualización de asistencia · Andrés Perdomo Silva',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:43'),(21,14,'Actualización de asistencia · Laura Gómez Rojas',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:45'),(22,1,'Actualización de asistencia · Laura Gómez Rojas',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:45'),(23,19,'Actualización de asistencia · Laura Gómez Rojas',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:45'),(24,20,'Actualización de asistencia · Laura Gómez Rojas',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:45'),(25,16,'Actualización de asistencia · Valentina Cuéllar Díaz',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:48'),(26,1,'Actualización de asistencia · Valentina Cuéllar Díaz',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:48'),(27,19,'Actualización de asistencia · Valentina Cuéllar Díaz',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:48'),(28,20,'Actualización de asistencia · Valentina Cuéllar Díaz',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:48'),(29,16,'Actualización de asistencia · Valentina Cuéllar Díaz',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:49'),(30,1,'Actualización de asistencia · Valentina Cuéllar Díaz',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:49'),(31,19,'Actualización de asistencia · Valentina Cuéllar Díaz',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:49'),(32,20,'Actualización de asistencia · Valentina Cuéllar Díaz',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:49'),(33,16,'Actualización de asistencia · Valentina Cuéllar Díaz',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:50'),(34,1,'Actualización de asistencia · Valentina Cuéllar Díaz',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:50'),(35,19,'Actualización de asistencia · Valentina Cuéllar Díaz',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:50'),(36,20,'Actualización de asistencia · Valentina Cuéllar Díaz',NULL,NULL,'Motivo: No se registró una observación.',NULL,NULL,'2026-10-23 05:05:50');
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
  `estado_excusa` enum('pendiente','aprobada','rechazada') DEFAULT NULL,
  `soporte_ruta` varchar(255) DEFAULT NULL,
  `fecha` date NOT NULL,
  `registrado_por` bigint(20) unsigned NOT NULL,
  `revisado_por` bigint(20) unsigned DEFAULT NULL,
  `revisado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_novedades_asistencia` (`asistencia_id`),
  KEY `fk_novedades_registrador` (`registrado_por`),
  KEY `idx_novedades_excusa_aprendiz` (`usuario_id`,`tipo`,`estado_excusa`),
  KEY `fk_novedades_revisor` (`revisado_por`),
  CONSTRAINT `fk_novedades_asistencia` FOREIGN KEY (`asistencia_id`) REFERENCES `asistencias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_novedades_registrador` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_novedades_revisor` FOREIGN KEY (`revisado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_novedades_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `novedades`
--

LOCK TABLES `novedades` WRITE;
/*!40000 ALTER TABLE `novedades` DISABLE KEYS */;
/*!40000 ALTER TABLE `novedades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `novedades_turno`
--

DROP TABLE IF EXISTS `novedades_turno`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `novedades_turno` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `portero_id` bigint(20) unsigned NOT NULL,
  `turno` varchar(60) NOT NULL,
  `novedad` text NOT NULL,
  `registrada_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `entregada_a` varchar(120) DEFAULT NULL,
  `entregada_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_novedades_turno_fecha` (`registrada_en`),
  KEY `fk_novedades_turno_portero` (`portero_id`),
  CONSTRAINT `fk_novedades_turno_portero` FOREIGN KEY (`portero_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `novedades_turno`
--

LOCK TABLES `novedades_turno` WRITE;
/*!40000 ALTER TABLE `novedades_turno` DISABLE KEYS */;
/*!40000 ALTER TABLE `novedades_turno` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `objetos_perdidos`
--

DROP TABLE IF EXISTS `objetos_perdidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `objetos_perdidos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(500) NOT NULL,
  `encontrado_en` varchar(180) NOT NULL,
  `encontrado_por` bigint(20) unsigned NOT NULL,
  `encontrado_el` datetime NOT NULL DEFAULT current_timestamp(),
  `entregado_a` bigint(20) unsigned DEFAULT NULL,
  `entregado_en` datetime DEFAULT NULL,
  `estado` enum('guardado','entregado') NOT NULL DEFAULT 'guardado',
  PRIMARY KEY (`id`),
  KEY `idx_objetos_perdidos_estado_fecha` (`estado`,`encontrado_el`),
  KEY `fk_objetos_perdidos_encontrado_por` (`encontrado_por`),
  KEY `fk_objetos_perdidos_entregado_a` (`entregado_a`),
  CONSTRAINT `fk_objetos_perdidos_encontrado_por` FOREIGN KEY (`encontrado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_objetos_perdidos_entregado_a` FOREIGN KEY (`entregado_a`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `objetos_perdidos`
--

LOCK TABLES `objetos_perdidos` WRITE;
/*!40000 ALTER TABLE `objetos_perdidos` DISABLE KEYS */;
/*!40000 ALTER TABLE `objetos_perdidos` ENABLE KEYS */;
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
-- Table structure for table `programas`
--

DROP TABLE IF EXISTS `programas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `nivel` enum('tecnico','tecnologo') NOT NULL,
  `duracion_trimestres` smallint(5) unsigned NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_programas_estado_nombre` (`estado`,`nombre`),
  CONSTRAINT `chk_programas_duracion` CHECK (`duracion_trimestres` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programas`
--

LOCK TABLES `programas` WRITE;
/*!40000 ALTER TABLE `programas` DISABLE KEYS */;
INSERT INTO `programas` VALUES (2,'P001','Análisis y Desarrollo de Software','tecnologo',9,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(3,'P002','Gestión Contable y de Información Financiera','tecnologo',9,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(4,'P003','Programación de Software','tecnico',5,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(5,'P004','Sistemas Teleinformáticos','tecnico',5,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(6,'P005','Gestión Empresarial','tecnologo',9,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01');
/*!40000 ALTER TABLE `programas` ENABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recuperacion_password`
--

LOCK TABLES `recuperacion_password` WRITE;
/*!40000 ALTER TABLE `recuperacion_password` DISABLE KEYS */;
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
-- Table structure for table `resultados_aprendizaje`
--

DROP TABLE IF EXISTS `resultados_aprendizaje`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resultados_aprendizaje` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) NOT NULL,
  `descripcion` varchar(500) NOT NULL,
  `competencia_id` bigint(20) unsigned NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_resultados_competencia_estado` (`competencia_id`,`estado`),
  CONSTRAINT `fk_resultados_competencia` FOREIGN KEY (`competencia_id`) REFERENCES `competencias` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resultados_aprendizaje`
--

LOCK TABLES `resultados_aprendizaje` WRITE;
/*!40000 ALTER TABLE `resultados_aprendizaje` DISABLE KEYS */;
INSERT INTO `resultados_aprendizaje` VALUES (2,'R01','Diseñar el modelo entidad-relación',2,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(3,'R02','Construir la base de datos en el motor seleccionado',2,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(4,'R03','Codificar los módulos según el diseño',3,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(5,'R04','Realizar pruebas de los módulos',3,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(6,'R05','Desplegar la aplicación en el servidor',4,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(7,'R06','Elaborar el manual de usuario',4,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(8,'P002-R01','Resultado de aprendizaje de prueba',5,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(9,'P003-R01','Resultado de aprendizaje de prueba',6,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(10,'P004-R01','Resultado de aprendizaje de prueba',7,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01'),(11,'P005-R01','Resultado de aprendizaje de prueba',8,'activo','2026-10-23 04:47:01','2026-10-23 04:47:01');
/*!40000 ALTER TABLE `resultados_aprendizaje` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'administrador','Acceso completo al sistema'),(2,'instructor','Asistencia, horarios y novedades'),(3,'estudiante','Consulta de horario, asistencia y carnet'),(4,'portero','Accesos, visitantes y equipos'),(5,'coordinador','Consulta reportes y aprueba correcciones de asistencia');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `solicitudes_correccion_asistencia`
--

DROP TABLE IF EXISTS `solicitudes_correccion_asistencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `solicitudes_correccion_asistencia` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asistencia_id` bigint(20) unsigned NOT NULL,
  `estado_anterior` enum('presente','ausente','tarde','permiso') NOT NULL,
  `observacion_anterior` varchar(500) DEFAULT NULL,
  `estado_propuesto` enum('presente','ausente','tarde','permiso') NOT NULL,
  `observacion_propuesta` varchar(500) DEFAULT NULL,
  `motivo` varchar(500) NOT NULL,
  `solicitada_por` bigint(20) unsigned NOT NULL,
  `estado` enum('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `revisada_por` bigint(20) unsigned DEFAULT NULL,
  `revisada_en` datetime DEFAULT NULL,
  `respuesta` varchar(500) DEFAULT NULL,
  `creada_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_correcciones_estado_fecha` (`estado`,`creada_en`),
  KEY `idx_correcciones_asistencia` (`asistencia_id`,`estado`),
  KEY `fk_correcciones_solicitante` (`solicitada_por`),
  KEY `fk_correcciones_revisor` (`revisada_por`),
  CONSTRAINT `fk_correcciones_asistencia` FOREIGN KEY (`asistencia_id`) REFERENCES `asistencias` (`id`),
  CONSTRAINT `fk_correcciones_revisor` FOREIGN KEY (`revisada_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_correcciones_solicitante` FOREIGN KEY (`solicitada_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `solicitudes_correccion_asistencia`
--

LOCK TABLES `solicitudes_correccion_asistencia` WRITE;
/*!40000 ALTER TABLE `solicitudes_correccion_asistencia` DISABLE KEYS */;
/*!40000 ALTER TABLE `solicitudes_correccion_asistencia` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `solicitudes_ficha`
--

DROP TABLE IF EXISTS `solicitudes_ficha`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `solicitudes_ficha` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `ficha_id` bigint(20) unsigned NOT NULL,
  `estado` enum('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `solicitada_en` datetime NOT NULL DEFAULT current_timestamp(),
  `revisada_en` datetime DEFAULT NULL,
  `revisada_por` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_solicitudes_ficha_estado` (`estado`,`solicitada_en`),
  KEY `idx_solicitudes_ficha_usuario` (`usuario_id`,`estado`),
  KEY `fk_solicitudes_ficha_ficha` (`ficha_id`),
  KEY `fk_solicitudes_ficha_revisor` (`revisada_por`),
  CONSTRAINT `fk_solicitudes_ficha_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_solicitudes_ficha_revisor` FOREIGN KEY (`revisada_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_solicitudes_ficha_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `solicitudes_ficha`
--

LOCK TABLES `solicitudes_ficha` WRITE;
/*!40000 ALTER TABLE `solicitudes_ficha` DISABLE KEYS */;
/*!40000 ALTER TABLE `solicitudes_ficha` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `solicitudes_registro`
--

LOCK TABLES `solicitudes_registro` WRITE;
/*!40000 ALTER TABLE `solicitudes_registro` DISABLE KEYS */;
INSERT INTO `solicitudes_registro` VALUES (1,NULL,'aprobada','2026-10-22 20:15:29','2026-10-22 20:16:09',1);
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
  `tipo_documento` varchar(20) NOT NULL DEFAULT 'CC',
  `documento` varchar(30) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `correo` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `foto` mediumtext DEFAULT NULL,
  `tratamiento_datos_aceptado_en` datetime DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_acceso` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `documento` (`documento`),
  UNIQUE KEY `correo` (`correo`),
  KEY `fk_usuarios_rol` (`rol_id`),
  CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,1,'CC','100000001','Mauricio','Medina Camacho','mauriciomedinacamacho5@gmail.com','$2y$10$xumNEVreI/Sm32whG/H3dOoMHmaoApetVIHi3cpqZ3NzbwfcQLDbq','3162819576',NULL,NULL,1,'2026-10-22 23:23:33','2026-10-22 23:57:37','2026-10-23 04:24:11'),(14,3,'CC','1075000001','Laura','Gómez Rojas','laura.gomez@prueba.com','$2y$10$9IEztUIUJmU.jQ7wf2mGYe/C3XkYkevx4acD6nVtbol.m9G0iHd5O',NULL,NULL,'2026-10-22 23:47:01',1,'2026-10-22 23:58:12','2026-10-23 04:47:01','2026-10-23 04:58:12'),(15,3,'CC','1075000002','Andrés','Perdomo Silva','andres.perdomo@prueba.com','$2y$10$ds9Mn5h.s7uKeQTUzEKtf.0wZCKyqABcVBm5pONpthgkBq3RGDH4C',NULL,NULL,'2026-10-22 23:47:01',1,NULL,'2026-10-23 04:47:01','2026-10-23 04:47:01'),(16,3,'CC','1075000003','Valentina','Cuéllar Díaz','valentina.cuellar@prueba.com','$2y$10$ivQ7ssBax7pmou6NtCws0.d8UKX48R3Aiic/SwwpBqa4iohSeS0g2',NULL,NULL,'2026-10-22 23:47:01',1,NULL,'2026-10-23 04:47:01','2026-10-23 04:47:01'),(17,3,'CC','1075000004','Juan Esteban','Trujillo','juan.trujillo@prueba.com','$2y$10$5A3jfmCrqbSQJ3b9vWhKXO1I.nvCnhUKaqal0DKMHIXN5AfmLDlJ2',NULL,NULL,'2026-10-22 23:47:01',1,'2026-10-23 00:09:24','2026-10-23 04:47:01','2026-10-23 05:09:24'),(18,3,'CC','1075000005','Camila','Andrade Muñoz','camila.andrade@prueba.com','$2y$10$t8Hdlbw4VaYqVhLWAdul5utG506O67VWCINuyRBfubSgXLZxnvF7e',NULL,NULL,'2026-10-22 23:47:01',1,NULL,'2026-10-23 04:47:01','2026-10-23 04:47:01'),(19,2,'CC','1075000021','Catalina','Londoño Ríos','catalina.londono@prueba.com','$2y$10$Ke99LdLdxwn70GmtmwA6HuE/4l3aoKEUPpjYQbN892A8fsLrWkmUa',NULL,NULL,'2026-10-22 23:47:01',1,'2026-10-23 00:02:16','2026-10-23 04:47:01','2026-10-23 05:02:16'),(20,2,'CC','1075000022','Hernán Darío','Quintero','hernan.quintero@prueba.com','$2y$10$nVYZvzUXL6dgWAIftClfheugETURCfcPt0ew7DG2ed8INd0P1caNi',NULL,NULL,'2026-10-22 23:47:02',1,NULL,'2026-10-23 04:47:02','2026-10-23 04:47:02'),(21,2,'CC','1075000023','Paola Andrea','Medina','paola.medina@prueba.com','$2y$10$udmFrMgarEmuPdnrwX/R9eOBSGG8/gcw50KoicpQKpql/D3XL8uIG',NULL,NULL,'2026-10-22 23:47:02',1,NULL,'2026-10-23 04:47:02','2026-10-23 04:47:02'),(22,2,'CC','1075000024','Fabián','Ortiz Cerquera','fabian.ortiz@prueba.com','$2y$10$44hdA2d3956.w5UUIaL9n.tDRdZ1U8JEjf10mzYRn97.9jTkP7rnm',NULL,NULL,'2026-10-22 23:47:02',1,NULL,'2026-10-23 04:47:02','2026-10-23 04:47:02'),(23,2,'CC','1075000025','Sandra Milena','Tovar','sandra.tovar@prueba.com','$2y$10$dhNRc4fxrQEj0zJzYs8NA.e5lHumWetQxBIJeWVZsIXYHrmYhtOc.',NULL,NULL,'2026-10-22 23:47:02',1,NULL,'2026-10-23 04:47:02','2026-10-23 04:47:02'),(24,4,'CC','1075000011','Carlos','Polanía Vargas','carlos.polania@prueba.com','$2y$10$5PzkHV54Fsux22T/LLm6cOHD7uU7SebQy90R1OpthSrMlVGDnsuWq',NULL,NULL,'2026-10-22 23:47:02',1,'2026-10-23 00:08:49','2026-10-23 04:47:02','2026-10-23 05:08:49'),(25,4,'CC','1075000012','Luz Marina','Cabrera','luz.cabrera@prueba.com','$2y$10$ulU7Kd1ZLFuyERJBy.xTN.2WQx2AuQfhlZpZgoPhMCwms0oeqx6fO',NULL,NULL,'2026-10-22 23:47:02',1,'2026-10-23 00:07:53','2026-10-23 04:47:02','2026-10-23 05:07:53'),(26,4,'CC','1075000013','Jorge Iván','Charry','jorge.charry@prueba.com','$2y$10$YObcpp7hQwPRM9ZdmEGpUOrAG86XD1y15Bc3Q3Cp2xPr6hIumzK8O',NULL,NULL,'2026-10-22 23:47:02',1,NULL,'2026-10-23 04:47:02','2026-10-23 04:47:02'),(27,4,'CC','1075000014','Diana Patricia','Losada','diana.losada@prueba.com','$2y$10$pTPoUaDoL953RMEngPpwDOM/r/fxrMJxtVAZJA5ypll1HLU5HUkNm',NULL,NULL,'2026-10-22 23:47:02',1,NULL,'2026-10-23 04:47:02','2026-10-23 04:47:02'),(28,4,'CC','1075000015','Miguel Ángel','Ramírez','miguel.ramirez@prueba.com','$2y$10$jqpG36AUWDNyRKOQgBW4b.zeJStJbWUDYUzJUY9rS0UBfuxWDLy8C',NULL,NULL,'2026-10-22 23:47:02',1,NULL,'2026-10-23 04:47:02','2026-10-23 04:47:02');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `verificaciones_correo`
--

DROP TABLE IF EXISTS `verificaciones_correo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `verificaciones_correo` (
  `correo` varchar(190) NOT NULL,
  `codigo_hash` varchar(255) NOT NULL,
  `datos` mediumtext NOT NULL,
  `vence_en` datetime NOT NULL,
  `intentos` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`correo`),
  KEY `idx_verificaciones_vence` (`vence_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `verificaciones_correo`
--

LOCK TABLES `verificaciones_correo` WRITE;
/*!40000 ALTER TABLE `verificaciones_correo` DISABLE KEYS */;
/*!40000 ALTER TABLE `verificaciones_correo` ENABLE KEYS */;
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
  `codigo_qr` char(36) DEFAULT NULL,
  `nombres` varchar(160) NOT NULL,
  `entidad` varchar(150) DEFAULT NULL,
  `motivo` varchar(300) NOT NULL,
  `anfitrion_id` bigint(20) unsigned DEFAULT NULL,
  `ingreso_en` datetime NOT NULL DEFAULT current_timestamp(),
  `salida_en` datetime DEFAULT NULL,
  `registrado_por` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_visitantes_codigo_qr` (`codigo_qr`),
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

--
-- Dumping routines for database 'proyecto01'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-23  0:11:12
