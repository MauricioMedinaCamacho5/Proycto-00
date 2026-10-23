USE proyecto01;

ALTER TABLE equipos
  ADD COLUMN usuario_id BIGINT UNSIGNED NULL AFTER codigo_qr,
  ADD COLUMN visitante_id BIGINT UNSIGNED NULL AFTER usuario_id,
  ADD COLUMN tipo ENUM('portatil','teclado','mouse','maleta','otro') NOT NULL DEFAULT 'otro' AFTER nombre,
  ADD COLUMN marca VARCHAR(100) NULL AFTER tipo,
  ADD COLUMN color VARCHAR(60) NULL AFTER marca,
  ADD COLUMN foto MEDIUMTEXT NULL AFTER color,
  ADD INDEX idx_equipos_propietario_usuario (usuario_id),
  ADD INDEX idx_equipos_propietario_visitante (visitante_id),
  ADD CONSTRAINT fk_equipos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_equipos_visitante FOREIGN KEY (visitante_id) REFERENCES visitantes(id) ON DELETE CASCADE;

ALTER TABLE visitantes
  ADD COLUMN codigo_qr CHAR(36) NULL AFTER documento,
  ADD UNIQUE KEY uq_visitantes_codigo_qr (codigo_qr);

CREATE TABLE ingresos_diarios (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NULL,
  visitante_id BIGINT UNSIGNED NULL,
  fecha DATE NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  usado_en DATETIME NULL,
  acceso_abierto TINYINT(1) NOT NULL DEFAULT 0,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ingresos_diarios_usuario (usuario_id, fecha),
  UNIQUE KEY uq_ingresos_diarios_visitante (visitante_id, fecha),
  CONSTRAINT chk_ingresos_diarios_persona CHECK (
    (usuario_id IS NOT NULL AND visitante_id IS NULL) OR
    (usuario_id IS NULL AND visitante_id IS NOT NULL)
  ),
  CONSTRAINT fk_ingresos_diarios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_ingresos_diarios_visitante FOREIGN KEY (visitante_id) REFERENCES visitantes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ingreso_diario_equipos (
  ingreso_diario_id BIGINT UNSIGNED NOT NULL,
  equipo_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (ingreso_diario_id, equipo_id),
  CONSTRAINT fk_ingreso_diario_equipos_ingreso FOREIGN KEY (ingreso_diario_id)
    REFERENCES ingresos_diarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_ingreso_diario_equipos_equipo FOREIGN KEY (equipo_id)
    REFERENCES equipos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE llaves_ambientes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ambiente_id BIGINT UNSIGNED NOT NULL,
  instructor_id BIGINT UNSIGNED NOT NULL,
  prestada_por BIGINT UNSIGNED NOT NULL,
  prestada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  vence_en DATETIME NOT NULL,
  devuelta_en DATETIME NULL,
  devuelta_por BIGINT UNSIGNED NULL,
  estado ENUM('prestada','devuelta','vencida') NOT NULL DEFAULT 'prestada',
  INDEX idx_llaves_estado_vence (estado, vence_en),
  INDEX idx_llaves_ambiente (ambiente_id, estado),
  CONSTRAINT fk_llaves_ambiente FOREIGN KEY (ambiente_id) REFERENCES ambientes(id),
  CONSTRAINT fk_llaves_instructor FOREIGN KEY (instructor_id) REFERENCES usuarios(id),
  CONSTRAINT fk_llaves_prestada_por FOREIGN KEY (prestada_por) REFERENCES usuarios(id),
  CONSTRAINT fk_llaves_devuelta_por FOREIGN KEY (devuelta_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE novedades_turno (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  portero_id BIGINT UNSIGNED NOT NULL,
  turno VARCHAR(60) NOT NULL,
  novedad TEXT NOT NULL,
  registrada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  entregada_a VARCHAR(120) NULL,
  entregada_en DATETIME NULL,
  INDEX idx_novedades_turno_fecha (registrada_en),
  CONSTRAINT fk_novedades_turno_portero FOREIGN KEY (portero_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE objetos_perdidos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  descripcion VARCHAR(500) NOT NULL,
  encontrado_en VARCHAR(180) NOT NULL,
  encontrado_por BIGINT UNSIGNED NOT NULL,
  encontrado_el DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  entregado_a BIGINT UNSIGNED NULL,
  entregado_en DATETIME NULL,
  estado ENUM('guardado','entregado') NOT NULL DEFAULT 'guardado',
  INDEX idx_objetos_perdidos_estado_fecha (estado, encontrado_el),
  CONSTRAINT fk_objetos_perdidos_encontrado_por FOREIGN KEY (encontrado_por) REFERENCES usuarios(id),
  CONSTRAINT fk_objetos_perdidos_entregado_a FOREIGN KEY (entregado_a) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO configuracion_sistema (clave, valor)
VALUES ('minutos_prestamo_llaves', 180)
ON DUPLICATE KEY UPDATE clave = VALUES(clave);
