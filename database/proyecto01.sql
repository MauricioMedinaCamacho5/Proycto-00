CREATE DATABASE IF NOT EXISTS proyecto01
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE proyecto01;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS recuperacion_password, auditoria, notificaciones,
  permisos_temporales, prestamos, movimientos_equipos, equipos, visitantes,
  accesos, novedades, asistencias, reservas, horarios, mantenimientos,
  ambientes, fichas_usuarios, fichas, bloqueos, carnets, usuarios, roles;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(40) NOT NULL UNIQUE,
  descripcion VARCHAR(180) NULL
) ENGINE=InnoDB;

CREATE TABLE usuarios (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rol_id BIGINT UNSIGNED NOT NULL,
  documento VARCHAR(30) NOT NULL UNIQUE,
  nombres VARCHAR(100) NOT NULL,
  apellidos VARCHAR(100) NOT NULL,
  correo VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  telefono VARCHAR(30) NULL,
  foto VARCHAR(255) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acceso DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE carnets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL UNIQUE,
  codigo_qr CHAR(36) NOT NULL UNIQUE,
  emitido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  vence_en DATE NULL,
  CONSTRAINT fk_carnets_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bloqueos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  administrador_id BIGINT UNSIGNED NOT NULL,
  motivo VARCHAR(500) NOT NULL,
  bloqueado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  desbloqueado_en DATETIME NULL,
  CONSTRAINT fk_bloqueos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_bloqueos_admin FOREIGN KEY (administrador_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE fichas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  nombre VARCHAR(150) NOT NULL,
  fecha_inicio DATE NULL,
  fecha_fin DATE NULL,
  estado ENUM('activa','finalizada','suspendida') NOT NULL DEFAULT 'activa'
) ENGINE=InnoDB;

CREATE TABLE fichas_usuarios (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ficha_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  inscrito_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ficha_usuario (ficha_id, usuario_id),
  CONSTRAINT fk_ficha_usuarios_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE,
  CONSTRAINT fk_ficha_usuarios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ambientes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  nombre VARCHAR(120) NOT NULL,
  ubicacion VARCHAR(180) NOT NULL,
  capacidad SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  estado ENUM('disponible','ocupado','mantenimiento') NOT NULL DEFAULT 'disponible',
  descripcion TEXT NULL
) ENGINE=InnoDB;

CREATE TABLE mantenimientos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ambiente_id BIGINT UNSIGNED NOT NULL,
  responsable_id BIGINT UNSIGNED NULL,
  tipo ENUM('preventivo','correctivo') NOT NULL,
  descripcion TEXT NOT NULL,
  programado_en DATETIME NOT NULL,
  finalizado_en DATETIME NULL,
  estado ENUM('pendiente','en_proceso','completado','cancelado') NOT NULL DEFAULT 'pendiente',
  CONSTRAINT fk_mantenimientos_ambiente FOREIGN KEY (ambiente_id) REFERENCES ambientes(id),
  CONSTRAINT fk_mantenimientos_responsable FOREIGN KEY (responsable_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE horarios (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ficha_id BIGINT UNSIGNED NOT NULL,
  ambiente_id BIGINT UNSIGNED NOT NULL,
  instructor_id BIGINT UNSIGNED NOT NULL,
  dia_semana TINYINT UNSIGNED NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  asignatura VARCHAR(120) NOT NULL,
  CONSTRAINT chk_horarios_dia CHECK (dia_semana BETWEEN 1 AND 7),
  CONSTRAINT chk_horarios_horas CHECK (hora_inicio < hora_fin),
  CONSTRAINT fk_horarios_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id),
  CONSTRAINT fk_horarios_ambiente FOREIGN KEY (ambiente_id) REFERENCES ambientes(id),
  CONSTRAINT fk_horarios_instructor FOREIGN KEY (instructor_id) REFERENCES usuarios(id),
  INDEX idx_horarios_busqueda (dia_semana, hora_inicio, hora_fin)
) ENGINE=InnoDB;

CREATE TABLE reservas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ambiente_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  inicio DATETIME NOT NULL,
  fin DATETIME NOT NULL,
  motivo VARCHAR(250) NOT NULL,
  estado ENUM('pendiente','aprobada','rechazada','cancelada') NOT NULL DEFAULT 'aprobada',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_reservas_rango CHECK (inicio < fin),
  CONSTRAINT fk_reservas_ambiente FOREIGN KEY (ambiente_id) REFERENCES ambientes(id),
  CONSTRAINT fk_reservas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  INDEX idx_reservas_ambiente_fecha (ambiente_id, inicio, fin)
) ENGINE=InnoDB;

CREATE TABLE asistencias (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  ficha_id BIGINT UNSIGNED NOT NULL,
  horario_id BIGINT UNSIGNED NULL,
  fecha DATE NOT NULL,
  estado ENUM('presente','ausente','tarde','permiso') NOT NULL,
  registrado_por BIGINT UNSIGNED NOT NULL,
  observacion VARCHAR(500) NULL,
  registrado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_asistencia_usuario_ficha_fecha (usuario_id, ficha_id, fecha),
  CONSTRAINT fk_asistencias_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_asistencias_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id),
  CONSTRAINT fk_asistencias_horario FOREIGN KEY (horario_id) REFERENCES horarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_asistencias_registrador FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE novedades (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  asistencia_id BIGINT UNSIGNED NULL,
  tipo ENUM('retraso','permiso','inasistencia','otra') NOT NULL,
  descripcion VARCHAR(500) NOT NULL,
  fecha DATE NOT NULL,
  registrado_por BIGINT UNSIGNED NOT NULL,
  CONSTRAINT fk_novedades_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_novedades_asistencia FOREIGN KEY (asistencia_id) REFERENCES asistencias(id) ON DELETE SET NULL,
  CONSTRAINT fk_novedades_registrador FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE accesos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NULL,
  visitante_id BIGINT UNSIGNED NULL,
  registrado_por BIGINT UNSIGNED NOT NULL,
  tipo ENUM('ingreso','salida') NOT NULL,
  fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  observacion VARCHAR(500) NULL,
  INDEX idx_accesos_fecha (fecha_hora),
  CONSTRAINT fk_accesos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_accesos_registrador FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE visitantes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  documento VARCHAR(30) NOT NULL,
  nombres VARCHAR(160) NOT NULL,
  entidad VARCHAR(150) NULL,
  motivo VARCHAR(300) NOT NULL,
  anfitrion_id BIGINT UNSIGNED NULL,
  ingreso_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  salida_en DATETIME NULL,
  registrado_por BIGINT UNSIGNED NOT NULL,
  CONSTRAINT fk_visitantes_anfitrion FOREIGN KEY (anfitrion_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_visitantes_registrador FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

ALTER TABLE accesos
  ADD CONSTRAINT fk_accesos_visitante FOREIGN KEY (visitante_id) REFERENCES visitantes(id);

CREATE TABLE equipos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  serial VARCHAR(100) NOT NULL UNIQUE,
  codigo_qr CHAR(36) NOT NULL UNIQUE,
  nombre VARCHAR(150) NOT NULL,
  categoria VARCHAR(80) NOT NULL,
  estado ENUM('disponible','asignado','prestado','mantenimiento','baja') NOT NULL DEFAULT 'disponible',
  ubicacion VARCHAR(180) NOT NULL,
  autorizado TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE movimientos_equipos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  equipo_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NULL,
  registrado_por BIGINT UNSIGNED NOT NULL,
  tipo ENUM('ingreso','salida','traslado','asignacion','devolucion') NOT NULL,
  fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ubicacion_origen VARCHAR(180) NULL,
  ubicacion_destino VARCHAR(180) NULL,
  observacion VARCHAR(500) NULL,
  CONSTRAINT fk_movimientos_equipo FOREIGN KEY (equipo_id) REFERENCES equipos(id),
  CONSTRAINT fk_movimientos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_movimientos_registrador FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE prestamos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  equipo_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  entregado_por BIGINT UNSIGNED NOT NULL,
  prestado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  vence_en DATETIME NOT NULL,
  devuelto_en DATETIME NULL,
  estado ENUM('activo','devuelto','vencido') NOT NULL DEFAULT 'activo',
  CONSTRAINT fk_prestamos_equipo FOREIGN KEY (equipo_id) REFERENCES equipos(id),
  CONSTRAINT fk_prestamos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_prestamos_entrega FOREIGN KEY (entregado_por) REFERENCES usuarios(id),
  INDEX idx_prestamos_estado (estado, vence_en)
) ENGINE=InnoDB;

CREATE TABLE permisos_temporales (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NULL,
  visitante_id BIGINT UNSIGNED NULL,
  autorizado_por BIGINT UNSIGNED NOT NULL,
  desde DATETIME NOT NULL,
  hasta DATETIME NOT NULL,
  motivo VARCHAR(300) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT chk_permisos_rango CHECK (desde < hasta),
  CONSTRAINT fk_permisos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_permisos_visitante FOREIGN KEY (visitante_id) REFERENCES visitantes(id),
  CONSTRAINT fk_permisos_autorizador FOREIGN KEY (autorizado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE notificaciones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  mensaje TEXT NOT NULL,
  leida_en DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notificaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE auditoria (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NULL,
  accion VARCHAR(100) NOT NULL,
  entidad VARCHAR(80) NOT NULL,
  entidad_id BIGINT UNSIGNED NULL,
  detalle JSON NULL,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_auditoria_fecha (fecha_hora),
  CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE recuperacion_password (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  vence_en DATETIME NOT NULL,
  usado_en DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_recuperacion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO roles (id, nombre, descripcion) VALUES
  (1,'administrador','Acceso completo al sistema'),
  (2,'instructor','Asistencia, horarios y novedades'),
  (3,'estudiante','Consulta de horario, asistencia y carnet'),
  (4,'portero','Accesos, visitantes y equipos');

-- Usuarios de demostracion: la contrasena para todos es "password".
INSERT INTO usuarios (id, rol_id, documento, nombres, apellidos, correo, password_hash) VALUES
  (1,1,'100000001','Mauricio','Medina Camacho','mauriciomedinacamacho5@gmail.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
  (2,2,'100000002','Instructora','Demo','instructor@institucion.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
  (3,3,'100000003','Estudiante','Demo','estudiante@institucion.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
  (4,4,'100000004','Portero','Demo','portero@institucion.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO carnets (usuario_id, codigo_qr) VALUES
  (1,'a4b2235a-6dd2-43ea-8d39-926056df1001'),
  (2,'a4b2235a-6dd2-43ea-8d39-926056df1002'),
  (3,'a4b2235a-6dd2-43ea-8d39-926056df1003'),
  (4,'a4b2235a-6dd2-43ea-8d39-926056df1004');
INSERT INTO fichas (id, codigo, nombre, estado) VALUES (1,'FIC-2026-01','Tecnico en Sistemas','activa');
INSERT INTO fichas_usuarios (ficha_id, usuario_id) VALUES (1,3);
INSERT INTO ambientes (id, codigo, nombre, ubicacion, capacidad, estado) VALUES
  (1,'A-101','Laboratorio de Sistemas','Bloque A, primer piso',30,'disponible'),
  (2,'B-204','Aula de Formacion','Bloque B, segundo piso',25,'disponible');
INSERT INTO horarios (ficha_id,ambiente_id,instructor_id,dia_semana,hora_inicio,hora_fin,asignatura)
VALUES (1,1,2,1,'08:00:00','12:00:00','Fundamentos de programacion');
INSERT INTO equipos (serial, codigo_qr, nombre, categoria, estado, ubicacion) VALUES
  ('DEMO-LAP-001','7d51d50b-3792-4876-b3e3-b542106d4101','Portatil de demostracion','Computo','disponible','Laboratorio A-101');
