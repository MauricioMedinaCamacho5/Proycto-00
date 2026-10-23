USE proyecto01;

CREATE TABLE programas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(40) NOT NULL UNIQUE,
  nombre VARCHAR(150) NOT NULL,
  nivel ENUM('tecnico','tecnologo') NOT NULL,
  duracion_trimestres SMALLINT UNSIGNED NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_programas_duracion CHECK (duracion_trimestres > 0),
  INDEX idx_programas_estado_nombre (estado, nombre)
) ENGINE=InnoDB;

CREATE TABLE competencias (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(40) NOT NULL UNIQUE,
  nombre VARCHAR(180) NOT NULL,
  programa_id BIGINT UNSIGNED NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_competencias_programa FOREIGN KEY (programa_id) REFERENCES programas(id) ON DELETE RESTRICT,
  INDEX idx_competencias_programa_estado (programa_id, estado)
) ENGINE=InnoDB;

CREATE TABLE resultados_aprendizaje (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(40) NOT NULL UNIQUE,
  descripcion VARCHAR(500) NOT NULL,
  competencia_id BIGINT UNSIGNED NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_resultados_competencia FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE RESTRICT,
  INDEX idx_resultados_competencia_estado (competencia_id, estado)
) ENGINE=InnoDB;

CREATE TABLE calendario_academico (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  tipo ENUM('festivo','vacaciones') NOT NULL,
  descripcion VARCHAR(250) NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_calendario_fecha_tipo (fecha, tipo),
  INDEX idx_calendario_fecha_estado (fecha, estado)
) ENGINE=InnoDB;

INSERT INTO programas (codigo, nombre, nivel, duracion_trimestres, estado)
SELECT CONCAT('LEG-', UPPER(SUBSTRING(SHA2(programa_legacy.nombre, 256), 1, 12))),
    programa_legacy.nombre, 'tecnico', 6, 'activo'
FROM (
    SELECT DISTINCT TRIM(programa) AS nombre
    FROM fichas
    WHERE TRIM(programa) <> ''
) AS programa_legacy;

ALTER TABLE fichas
  ADD COLUMN programa_id BIGINT UNSIGNED NULL AFTER codigo,
  ADD INDEX idx_fichas_programa (programa_id),
  ADD CONSTRAINT fk_fichas_programa FOREIGN KEY (programa_id) REFERENCES programas(id) ON DELETE RESTRICT;

UPDATE fichas f
JOIN programas p ON p.nombre = TRIM(f.programa)
SET f.programa_id = p.id
WHERE TRIM(f.programa) <> '';

ALTER TABLE horarios
  ADD COLUMN competencia_id BIGINT UNSIGNED NULL AFTER ficha_id,
  ADD INDEX idx_horarios_competencia (competencia_id),
  ADD CONSTRAINT fk_horarios_competencia FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE RESTRICT;
