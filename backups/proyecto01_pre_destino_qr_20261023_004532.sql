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
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria`
--

LOCK TABLES `auditoria` WRITE;
/*!40000 ALTER TABLE `auditoria` DISABLE KEYS */;
INSERT INTO `auditoria` VALUES (1,1,'actualizar_pin_carnet','carnets',1,NULL,'2026-10-22 23:58:21'),(2,1,'inicio_sesion_temporal','usuarios',1,NULL,'2026-10-22 23:58:21'),(3,1,'cierre_sesion','usuarios',1,NULL,'2026-10-23 00:21:15'),(4,1,'actualizar_pin_carnet','carnets',1,NULL,'2026-10-23 00:21:52'),(5,1,'inicio_sesion_temporal','usuarios',1,NULL,'2026-10-23 00:21:52'),(23,1,'cierre_sesion','usuarios',1,NULL,'2026-10-23 01:14:12'),(24,NULL,'solicitar_registro_personal','usuarios',4,'{\"rol\":\"instructor\"}','2026-10-23 01:15:29'),(25,1,'actualizar_pin_carnet','carnets',1,NULL,'2026-10-23 01:16:03'),(26,1,'inicio_sesion_temporal','usuarios',1,NULL,'2026-10-23 01:16:03'),(27,1,'aprobar_registro_personal','solicitudes_registro',1,'{\"rol\":\"instructor\"}','2026-10-23 01:16:09'),(28,1,'cierre_sesion','usuarios',1,NULL,'2026-10-23 01:16:10'),(29,NULL,'actualizar_pin_carnet','carnets',4,NULL,'2026-10-23 01:16:42'),(30,NULL,'inicio_sesion_temporal','usuarios',4,NULL,'2026-10-23 01:16:42'),(31,NULL,'cierre_sesion','usuarios',4,NULL,'2026-10-23 01:17:19'),(34,1,'actualizar_pin_carnet','carnets',1,NULL,'2026-10-23 04:23:33'),(35,1,'inicio_sesion_temporal','usuarios',1,NULL,'2026-10-23 04:23:33'),(36,1,'actualizar','usuarios',1,NULL,'2026-10-23 04:24:11'),(37,1,'eliminar','usuarios',4,NULL,'2026-10-23 04:24:25'),(38,1,'cierre_sesion','usuarios',1,NULL,'2026-10-23 04:27:33'),(39,14,'actualizar_pin_carnet','carnets',14,NULL,'2026-10-23 04:58:12'),(40,14,'inicio_sesion_temporal','usuarios',14,NULL,'2026-10-23 04:58:12'),(41,14,'cierre_sesion','usuarios',14,NULL,'2026-10-23 05:01:44'),(42,19,'actualizar_pin_carnet','carnets',19,NULL,'2026-10-23 05:02:16'),(43,19,'inicio_sesion_temporal','usuarios',19,NULL,'2026-10-23 05:02:16'),(44,19,'crear','asistencias',13,NULL,'2026-10-23 05:05:43'),(45,19,'crear','asistencias',14,NULL,'2026-10-23 05:05:45'),(46,19,'crear','asistencias',15,NULL,'2026-10-23 05:05:48'),(47,19,'actualizar','asistencias',15,NULL,'2026-10-23 05:05:49'),(48,19,'actualizar','asistencias',15,NULL,'2026-10-23 05:05:50'),(49,19,'cierre_sesion','usuarios',19,NULL,'2026-10-23 05:07:04'),(50,24,'actualizar_pin_carnet','carnets',24,NULL,'2026-10-23 05:07:28'),(51,24,'inicio_sesion_temporal','usuarios',24,NULL,'2026-10-23 05:07:28'),(52,24,'cierre_sesion','usuarios',24,NULL,'2026-10-23 05:07:28'),(53,25,'actualizar_pin_carnet','carnets',25,NULL,'2026-10-23 05:07:52'),(54,25,'inicio_sesion_temporal','usuarios',25,NULL,'2026-10-23 05:07:52'),(55,25,'cierre_sesion','usuarios',25,NULL,'2026-10-23 05:07:52'),(56,25,'actualizar_pin_carnet','carnets',25,NULL,'2026-10-23 05:07:53'),(57,25,'inicio_sesion_temporal','usuarios',25,NULL,'2026-10-23 05:07:53'),(58,25,'cierre_sesion','usuarios',25,NULL,'2026-10-23 05:07:53'),(59,25,'actualizar_pin_carnet','carnets',25,NULL,'2026-10-23 05:07:53'),(60,25,'inicio_sesion_temporal','usuarios',25,NULL,'2026-10-23 05:07:53'),(61,25,'cierre_sesion','usuarios',25,NULL,'2026-10-23 05:07:53'),(62,24,'actualizar_pin_carnet','carnets',24,NULL,'2026-10-23 05:08:49'),(63,24,'inicio_sesion_temporal','usuarios',24,NULL,'2026-10-23 05:08:49'),(64,24,'cierre_sesion','usuarios',24,NULL,'2026-10-23 05:09:04'),(65,17,'actualizar_pin_carnet','carnets',17,NULL,'2026-10-23 05:09:24'),(66,17,'inicio_sesion_temporal','usuarios',17,NULL,'2026-10-23 05:09:24'),(67,17,'cierre_sesion','usuarios',17,NULL,'2026-10-23 05:09:40'),(68,14,'inicio_sesion_temporal','usuarios',14,NULL,'2026-10-23 05:20:49'),(69,24,'inicio_sesion_temporal','usuarios',24,NULL,'2026-10-23 05:20:50'),(70,14,'inicio_sesion_temporal','usuarios',14,NULL,'2026-10-23 05:21:09'),(71,14,'generar_qr_ingreso_diario','ingresos_diarios',1,'{\"equipos\":[1]}','2026-10-23 05:21:09'),(72,24,'inicio_sesion_temporal','usuarios',24,NULL,'2026-10-23 05:21:10'),(73,14,'registrar_equipo_personal','equipos',3,NULL,'2026-10-23 05:21:10'),(74,14,'editar_equipo_personal','equipos',3,NULL,'2026-10-23 05:21:10'),(75,14,'desactivar_equipo_personal','equipos',3,NULL,'2026-10-23 05:21:10'),(76,14,'inicio_sesion_temporal','usuarios',14,NULL,'2026-10-23 05:21:59'),(77,14,'inicio_sesion_temporal','usuarios',14,NULL,'2026-10-23 05:22:39'),(78,14,'actualizar_pin_carnet','carnets',14,NULL,'2026-10-23 05:25:34'),(79,14,'inicio_sesion_temporal','usuarios',14,NULL,'2026-10-23 05:25:34'),(80,14,'desactivar_equipo_personal','equipos',1,NULL,'2026-10-23 05:35:25'),(81,14,'registrar_equipo_personal','equipos',4,NULL,'2026-10-23 05:39:39'),(82,14,'generar_qr_ingreso_diario','ingresos_diarios',1,'{\"equipos\":[]}','2026-10-23 05:39:46'),(83,14,'desactivar_equipo_personal','equipos',1,NULL,'2026-10-23 05:40:43'),(84,14,'generar_qr_ingreso_diario','ingresos_diarios',1,'{\"equipos\":[4]}','2026-10-23 05:41:00'),(85,14,'generar_qr_ingreso_diario','ingresos_diarios',1,'{\"equipos\":[4]}','2026-10-23 05:41:33'),(86,14,'desactivar_equipo_personal','equipos',4,NULL,'2026-10-23 05:41:38'),(87,14,'generar_qr_ingreso_diario','ingresos_diarios',1,'{\"equipos\":[]}','2026-10-23 05:43:49'),(88,14,'registrar_equipo_personal','equipos',5,NULL,'2026-10-23 05:44:22'),(89,14,'desactivar_equipo_personal','equipos',5,NULL,'2026-10-23 05:44:33');
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
INSERT INTO `carnets` VALUES (1,1,'b315622a-4852-4110-adc6-29e2e7ba5ed0','$2y$10$n1roxHlOvj8eObiVTL5AF.Vek3X9aNiF2OyOPT6q5ONc2qxlIu2Tq',0,NULL,0,'activo','2026-10-22 18:57:37',NULL),(5,14,'daa2cee3-ba5e-4992-8d14-c04656a78e0f','$2y$10$gPAXxgM4oYgbIy2eF3qzW.V754W/lnwAbeVNXZgZQMXZGMhvSoyGm',0,NULL,0,'activo','2026-10-22 23:47:01',NULL),(6,15,'ca1fb570-bf9c-4cac-9b9e-3d32620fb284','$2y$10$yB/ftAidFiYOskvha2eE6uC3Bi7LIf7/BUw09pMBu.l2AemeiJkda',0,NULL,0,'activo','2026-10-22 23:47:01',NULL),(7,16,'0535f5be-d0a9-4ea4-9650-9a94193f926c','$2y$10$BVwXU8K2IpfwPVT39kK5/.1oC9gtb26SF/FfrbCqysy/LBcAm.L4e',0,NULL,0,'activo','2026-10-22 23:47:01',NULL),(8,17,'b5dfbede-e6a6-4cbf-9b5b-d0f53d279060','$2y$10$JR7GMuw4ACvkykpCbFAWseOxddWA9R3qXUlq6oRnGaZIOHOFPslBC',0,NULL,0,'activo','2026-10-22 23:47:01',NULL),(9,18,'2d183f03-d092-4dba-8b57-47404bfd1e56','$2y$10$4qOeg1Re08eEc8GMqm5nPuyR8t5L71C0Ij7vyW4.7puY5yHG7/PyK',0,NULL,0,'activo','2026-10-22 23:47:01',NULL),(10,19,'3a3f9ff6-9321-4354-85db-dba116c48cd7','$2y$10$Fbvwu0GKBjM4t.6vr67DQOhYltez1VF9T/xTOoJkiHyzLW6a5U0hu',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(11,20,'3439b486-935c-434b-9b00-64d296f42d52','$2y$10$TfuF29CLZtpNrhMfDqpfE.PDwE3FGCBdtGFYKHaD.ORzbbvSXlouW',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(12,21,'5c65cb58-1087-4641-a4bd-2eafc222d65a','$2y$10$IDhr6s9trCJpbncKBy8OMe8Jb91yXAQ1aYPq/SKJf16.PNgyZPJXa',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(13,22,'ee30eec9-511d-4f87-99e5-87386df414a9','$2y$10$rEQRgaiycWcP6DeJgVUduequ.OasNP68BJjW.HoogH2pwRVF79fO.',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(14,23,'bf686c76-368f-4ef5-a1ca-dfcba2d90dbf','$2y$10$hpTzmpMWAPDSatS2sak9PeNq/CyLzvwLZ6KMSaEbpMHnTZ1JSatZ2',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(15,24,'efb5c324-d6f5-41e0-b1c2-26075ef154b7','$2y$10$xPgPeQI.v6DL9P/V8yMY7Oav/5o1GWqVEr1wat1yoSSfkFGtpTsbu',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(16,25,'8fdf6bbf-9817-44f4-bb65-b96cca9bad40','$2y$10$zx2xE9xaFFDaLmndWOAfMeoE7rjgbdzDoRXzzDiaM9TJZTlPwA7dS',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(17,26,'c424df64-015f-4725-9e41-a0cd58793888','$2y$10$UXYbOiHkWn4W3XfE/3aem.wIhSnSSsSOU.B1y3N81RJmV3hZ6mOfm',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(18,27,'01d71f2e-a6e0-49bd-98e6-44c9dd9b6331','$2y$10$9HhzuNV0PqaeGvSC0etmtOOZWRhP0BBA5tDG4114vILQzAfAxcU1.',0,NULL,0,'activo','2026-10-22 23:47:02',NULL),(19,28,'74026ac8-65c2-46c2-a763-9647ba5a742e','$2y$10$9zrL.QLR4ok1olAEDLjgt.S8X2OCZex.0pti.FfSSLkm6sSvarzle',0,NULL,0,'activo','2026-10-22 23:47:02',NULL);
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
INSERT INTO `datos_prueba_seed` VALUES ('proyecto01-datos-prueba-2026-10','ambientes',2,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','ambientes',3,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','ambientes',4,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','ambientes',5,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','ambientes',6,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','ambientes',7,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','calendario_academico',1,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',2,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',3,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',4,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',5,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',6,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',7,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',8,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','calendario_academico',9,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',10,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',11,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',12,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',13,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',14,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',15,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',16,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',17,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',18,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',19,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',20,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',21,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',22,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',23,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',24,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',25,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',26,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',27,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',28,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','calendario_academico',29,'2026-10-23 04:47:03'),('proyecto01-datos-prueba-2026-10','carnets',5,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','carnets',6,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','carnets',7,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','carnets',8,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','carnets',9,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','carnets',10,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',11,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',12,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',13,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',14,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',15,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',16,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',17,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',18,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','carnets',19,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','competencias',2,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','competencias',3,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','competencias',4,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','competencias',5,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','competencias',6,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','competencias',7,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','competencias',8,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','equipos',1,'2026-10-23 05:16:24'),('proyecto01-datos-prueba-2026-10','equipos',2,'2026-10-23 05:16:24'),('proyecto01-datos-prueba-2026-10','fichas',5,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas',6,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas',7,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas',8,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas',9,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas_instructores',5,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','fichas_instructores',6,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','fichas_instructores',7,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','fichas_instructores',8,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','fichas_instructores',9,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','fichas_instructores',10,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','fichas_usuarios',5,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas_usuarios',6,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas_usuarios',7,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas_usuarios',8,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','fichas_usuarios',9,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','horarios',2,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','horarios',3,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','horarios',4,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','horarios',5,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','horarios',6,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','horarios',7,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','horarios',8,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','horarios',9,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','programas',2,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','programas',3,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','programas',4,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','programas',5,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','programas',6,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',2,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',3,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',4,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',5,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',6,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',7,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',8,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',9,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',10,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','resultados_aprendizaje',11,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','usuarios',14,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','usuarios',15,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','usuarios',16,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','usuarios',17,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','usuarios',18,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','usuarios',19,'2026-10-23 04:47:01'),('proyecto01-datos-prueba-2026-10','usuarios',20,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',21,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',22,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',23,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',24,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',25,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',26,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',27,'2026-10-23 04:47:02'),('proyecto01-datos-prueba-2026-10','usuarios',28,'2026-10-23 04:47:02');
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
  `tipo` enum('portatil','tablet','teclado','mouse','maleta','otro') NOT NULL DEFAULT 'otro',
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipos`
--

LOCK TABLES `equipos` WRITE;
/*!40000 ALTER TABLE `equipos` DISABLE KEYS */;
INSERT INTO `equipos` VALUES (1,'PRUEBA-LAURA-PORTATIL-001','29022874-9ce3-47d0-b071-cb98a2270e54',14,NULL,'Portátil de Laura Gómez','portatil','Lenovo','Negro',NULL,'portatil','baja','Uso personal',1,'2026-10-23 05:16:24'),(2,'PRUEBA-CATALINA-PORTATIL-001','8a6d9f67-aa40-49b9-a2cd-5e8ebfa6136d',19,NULL,'Portátil de Catalina Londoño','portatil','Dell','Gris',NULL,'portatil','asignado','Uso personal',1,'2026-10-23 05:16:24'),(4,'DEMO-LAP-001','1e931c74-af51-4d71-8bde-a8511612cb1a',14,NULL,'ADSO','portatil','HHH','HHHHK',NULL,'portatil','baja','Uso personal',1,'2026-10-23 05:39:39'),(5,'01','df578fae-ae46-4633-be08-4347540307f0',14,NULL,'LENOVO','tablet','Lenovo','Negro','data:image/jpeg;base64,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','tablet','baja','Uso personal',1,'2026-10-23 05:44:22');
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ingresos_diarios`
--

LOCK TABLES `ingresos_diarios` WRITE;
/*!40000 ALTER TABLE `ingresos_diarios` DISABLE KEYS */;
INSERT INTO `ingresos_diarios` VALUES (1,14,NULL,'2026-10-23','13f8dee8639ed7ddade006ee0be17ac295002b0d57ec94978a657d51b8cd6745',NULL,0,'2026-10-23 05:21:09');
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
INSERT INTO `usuarios` VALUES (1,1,'CC','100000001','Mauricio','Medina Camacho','mauriciomedinacamacho5@gmail.com','$2y$10$xumNEVreI/Sm32whG/H3dOoMHmaoApetVIHi3cpqZ3NzbwfcQLDbq','3162819576',NULL,NULL,1,'2026-10-22 23:23:33','2026-10-22 23:57:37','2026-10-23 04:24:11'),(14,3,'CC','1075000001','Laura','Gómez Rojas','laura.gomez@prueba.com','$2y$10$9IEztUIUJmU.jQ7wf2mGYe/C3XkYkevx4acD6nVtbol.m9G0iHd5O',NULL,NULL,'2026-10-22 23:47:01',1,'2026-10-23 00:25:34','2026-10-23 04:47:01','2026-10-23 05:25:34'),(15,3,'CC','1075000002','Andrés','Perdomo Silva','andres.perdomo@prueba.com','$2y$10$ds9Mn5h.s7uKeQTUzEKtf.0wZCKyqABcVBm5pONpthgkBq3RGDH4C',NULL,NULL,'2026-10-22 23:47:01',1,NULL,'2026-10-23 04:47:01','2026-10-23 04:47:01'),(16,3,'CC','1075000003','Valentina','Cuéllar Díaz','valentina.cuellar@prueba.com','$2y$10$ivQ7ssBax7pmou6NtCws0.d8UKX48R3Aiic/SwwpBqa4iohSeS0g2',NULL,NULL,'2026-10-22 23:47:01',1,NULL,'2026-10-23 04:47:01','2026-10-23 04:47:01'),(17,3,'CC','1075000004','Juan Esteban','Trujillo','juan.trujillo@prueba.com','$2y$10$5A3jfmCrqbSQJ3b9vWhKXO1I.nvCnhUKaqal0DKMHIXN5AfmLDlJ2',NULL,NULL,'2026-10-22 23:47:01',1,'2026-10-23 00:09:24','2026-10-23 04:47:01','2026-10-23 05:09:24'),(18,3,'CC','1075000005','Camila','Andrade Muñoz','camila.andrade@prueba.com','$2y$10$t8Hdlbw4VaYqVhLWAdul5utG506O67VWCINuyRBfubSgXLZxnvF7e',NULL,NULL,'2026-10-22 23:47:01',1,NULL,'2026-10-23 04:47:01','2026-10-23 04:47:01'),(19,2,'CC','1075000021','Catalina','Londoño Ríos','catalina.londono@prueba.com','$2y$10$Ke99LdLdxwn70GmtmwA6HuE/4l3aoKEUPpjYQbN892A8fsLrWkmUa',NULL,NULL,'2026-10-22 23:47:01',1,'2026-10-23 00:02:16','2026-10-23 04:47:01','2026-10-23 05:02:16'),(20,2,'CC','1075000022','Hernán Darío','Quintero','hernan.quintero@prueba.com','$2y$10$nVYZvzUXL6dgWAIftClfheugETURCfcPt0ew7DG2ed8INd0P1caNi',NULL,NULL,'2026-10-22 23:47:02',1,NULL,'2026-10-23 04:47:02','2026-10-23 04:47:02'),(21,2,'CC','1075000023','Paola Andrea','Medina','paola.medina@prueba.com','$2y$10$udmFrMgarEmuPdnrwX/R9eOBSGG8/gcw50KoicpQKpql/D3XL8uIG',NULL,NULL,'2026-10-22 23:47:02',1,NULL,'2026-10-23 04:47:02','2026-10-23 04:47:02'),(22,2,'CC','1075000024','Fabián','Ortiz Cerquera','fabian.ortiz@prueba.com','$2y$10$44hdA2d3956.w5UUIaL9n.tDRdZ1U8JEjf10mzYRn97.9jTkP7rnm',NULL,NULL,'2026-10-22 23:47:02',1,NULL,'2026-10-23 04:47:02','2026-10-23 04:47:02'),(23,2,'CC','1075000025','Sandra Milena','Tovar','sandra.tovar@prueba.com','$2y$10$dhNRc4fxrQEj0zJzYs8NA.e5lHumWetQxBIJeWVZsIXYHrmYhtOc.',NULL,NULL,'2026-10-22 23:47:02',1,NULL,'2026-10-23 04:47:02','2026-10-23 04:47:02'),(24,4,'CC','1075000011','Carlos','Polanía Vargas','carlos.polania@prueba.com','$2y$10$5PzkHV54Fsux22T/LLm6cOHD7uU7SebQy90R1OpthSrMlVGDnsuWq',NULL,NULL,'2026-10-22 23:47:02',1,'2026-10-23 00:21:10','2026-10-23 04:47:02','2026-10-23 05:21:10'),(25,4,'CC','1075000012','Luz Marina','Cabrera','luz.cabrera@prueba.com','$2y$10$ulU7Kd1ZLFuyERJBy.xTN.2WQx2AuQfhlZpZgoPhMCwms0oeqx6fO',NULL,NULL,'2026-10-22 23:47:02',1,'2026-10-23 00:07:53','2026-10-23 04:47:02','2026-10-23 05:07:53'),(26,4,'CC','1075000013','Jorge Iván','Charry','jorge.charry@prueba.com','$2y$10$YObcpp7hQwPRM9ZdmEGpUOrAG86XD1y15Bc3Q3Cp2xPr6hIumzK8O',NULL,NULL,'2026-10-22 23:47:02',1,NULL,'2026-10-23 04:47:02','2026-10-23 04:47:02'),(27,4,'CC','1075000014','Diana Patricia','Losada','diana.losada@prueba.com','$2y$10$pTPoUaDoL953RMEngPpwDOM/r/fxrMJxtVAZJA5ypll1HLU5HUkNm',NULL,NULL,'2026-10-22 23:47:02',1,NULL,'2026-10-23 04:47:02','2026-10-23 04:47:02'),(28,4,'CC','1075000015','Miguel Ángel','Ramírez','miguel.ramirez@prueba.com','$2y$10$jqpG36AUWDNyRKOQgBW4b.zeJStJbWUDYUzJUY9rS0UBfuxWDLy8C',NULL,NULL,'2026-10-22 23:47:02',1,NULL,'2026-10-23 04:47:02','2026-10-23 04:47:02');
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

-- Dump completed on 2026-10-23  0:45:33
