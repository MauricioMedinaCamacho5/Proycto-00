-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: proyecto01
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ambientes`
--

LOCK TABLES `ambientes` WRITE;
/*!40000 ALTER TABLE `ambientes` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asistencias`
--

LOCK TABLES `asistencias` WRITE;
/*!40000 ALTER TABLE `asistencias` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria`
--

LOCK TABLES `auditoria` WRITE;
/*!40000 ALTER TABLE `auditoria` DISABLE KEYS */;
INSERT INTO `auditoria` VALUES (1,1,'actualizar_pin_carnet','carnets',1,NULL,'2026-10-22 23:58:21'),(2,1,'inicio_sesion_temporal','usuarios',1,NULL,'2026-10-22 23:58:21'),(3,1,'cierre_sesion','usuarios',1,NULL,'2026-10-23 00:21:15'),(4,1,'actualizar_pin_carnet','carnets',1,NULL,'2026-10-23 00:21:52'),(5,1,'inicio_sesion_temporal','usuarios',1,NULL,'2026-10-23 00:21:52'),(23,1,'cierre_sesion','usuarios',1,NULL,'2026-10-23 01:14:12'),(24,4,'solicitar_registro_personal','usuarios',4,'{\"rol\":\"instructor\"}','2026-10-23 01:15:29'),(25,1,'actualizar_pin_carnet','carnets',1,NULL,'2026-10-23 01:16:03'),(26,1,'inicio_sesion_temporal','usuarios',1,NULL,'2026-10-23 01:16:03'),(27,1,'aprobar_registro_personal','solicitudes_registro',1,'{\"rol\":\"instructor\"}','2026-10-23 01:16:09'),(28,1,'cierre_sesion','usuarios',1,NULL,'2026-10-23 01:16:10'),(29,4,'actualizar_pin_carnet','carnets',4,NULL,'2026-10-23 01:16:42'),(30,4,'inicio_sesion_temporal','usuarios',4,NULL,'2026-10-23 01:16:42'),(31,4,'cierre_sesion','usuarios',4,NULL,'2026-10-23 01:17:19');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendario_academico`
--

LOCK TABLES `calendario_academico` WRITE;
/*!40000 ALTER TABLE `calendario_academico` DISABLE KEYS */;
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
  `emitido_en` datetime NOT NULL DEFAULT current_timestamp(),
  `vence_en` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`),
  UNIQUE KEY `codigo_qr` (`codigo_qr`),
  CONSTRAINT `fk_carnets_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `carnets`
--

LOCK TABLES `carnets` WRITE;
/*!40000 ALTER TABLE `carnets` DISABLE KEYS */;
INSERT INTO `carnets` VALUES (1,1,'b315622a-4852-4110-adc6-29e2e7ba5ed0','$2y$10$zYKBeWzDB6ucx6QipUn.Oek2IhHMCJvYqS9WRWMqn4t/X.wAqHQzW',0,NULL,0,'2026-10-22 18:57:37',NULL),(4,4,'40ad4ba5-ec36-4c3e-bd6b-c34af2b15b51','$2y$10$1XeRcxmt0PV6aKfeiBLmrO3s85Tl2bp8q0LqQLTuOm44LqU8m9szq',0,NULL,1,'2026-10-22 20:15:29',NULL);
/*!40000 ALTER TABLE `carnets` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `competencias`
--

LOCK TABLES `competencias` WRITE;
/*!40000 ALTER TABLE `competencias` DISABLE KEYS */;
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
INSERT INTO `configuracion_sistema` VALUES ('asistencia_alerta_amarilla',90,NULL,'2026-10-22 23:15:30'),('asistencia_alerta_roja',80,NULL,'2026-10-22 23:15:30'),('dias_habiles_excusa',3,NULL,'2026-10-22 23:15:30'),('horas_correccion_asistencia',48,NULL,'2026-10-22 23:15:30'),('pin_bloqueo_minutos',5,NULL,'2026-10-22 23:15:30'),('pin_intentos_maximos',3,NULL,'2026-10-22 23:15:30');
/*!40000 ALTER TABLE `configuracion_sistema` ENABLE KEYS */;
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
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('activa','finalizada','suspendida') NOT NULL DEFAULT 'activa',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_fichas_programa` (`programa_id`),
  CONSTRAINT `fk_fichas_programa` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`),
  CONSTRAINT `chk_fichas_codigo` CHECK (`codigo` regexp '^[0-9]{7}$')
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fichas`
--

LOCK TABLES `fichas` WRITE;
/*!40000 ALTER TABLE `fichas` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fichas_instructores`
--

LOCK TABLES `fichas_instructores` WRITE;
/*!40000 ALTER TABLE `fichas_instructores` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horarios`
--

LOCK TABLES `horarios` WRITE;
/*!40000 ALTER TABLE `horarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `horarios` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificaciones`
--

LOCK TABLES `notificaciones` WRITE;
/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
INSERT INTO `notificaciones` VALUES (12,1,'Alerta roja de asistencia','asistencia',NULL,'Quién: Sistema de alertas. Qué: Fixture Automation completó tres días consecutivos de faltas sin excusa. Asistencia actual: 0%. Fecha de corte: 2026-10-23. Ficha: 4413164. Sesión: resumen de las sesiones registradas.','alerta-asistencia:roja:13:4:20261023060723',NULL,'2026-10-23 04:07:23');
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programas`
--

LOCK TABLES `programas` WRITE;
/*!40000 ALTER TABLE `programas` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resultados_aprendizaje`
--

LOCK TABLES `resultados_aprendizaje` WRITE;
/*!40000 ALTER TABLE `resultados_aprendizaje` DISABLE KEYS */;
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
INSERT INTO `solicitudes_registro` VALUES (1,4,'aprobada','2026-10-22 20:15:29','2026-10-22 20:16:09',1);
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,1,'CC','100000001','Mauricio','Medina Camacho','mauriciomedinacamacho5@gmail.com','$2y$10$f2OgYQrq8WER4nVygq8Ao.uWaH76FBBniF9OkHxNyH1bYTnc2c/X6',NULL,NULL,NULL,1,'2026-10-22 20:16:03','2026-10-22 23:57:37','2026-10-23 01:16:03'),(4,2,'CC','1000000000','Carlos Alfredo','Montenegro','carlosalfredomontenegro@gmail.com','$2y$10$wM5aBode.nzmBugJRV3LhugfTb8o9plvmfA6OfSsfn5iyw/wXmfuC','3213213211',NULL,'2026-10-23 03:15:28',1,'2026-10-22 20:16:42','2026-10-23 01:15:28','2026-10-23 01:16:42');
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

-- Dump completed on 2026-10-22 23:10:59
