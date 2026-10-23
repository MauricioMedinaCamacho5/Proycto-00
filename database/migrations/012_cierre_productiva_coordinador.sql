INSERT INTO roles (nombre, descripcion)
SELECT 'coordinador', 'Consulta reportes y aprueba correcciones de asistencia'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'coordinador');

ALTER TABLE carnets
    ADD COLUMN estado ENUM('activo','desactivado') NOT NULL DEFAULT 'activo' AFTER qr_entregado;

CREATE TABLE cierres_trimestre (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ficha_id BIGINT UNSIGNED NOT NULL,
    trimestre TINYINT UNSIGNED NOT NULL,
    desde DATE NULL,
    hasta DATE NOT NULL,
    resumen_asistencia JSON NOT NULL,
    resumen_juicios JSON NOT NULL,
    cerrado_por BIGINT UNSIGNED NOT NULL,
    cerrado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cierre_ficha_trimestre (ficha_id, trimestre),
    KEY idx_cierres_hasta (hasta),
    CONSTRAINT fk_cierres_ficha FOREIGN KEY (ficha_id) REFERENCES fichas (id),
    CONSTRAINT fk_cierres_usuario FOREIGN KEY (cerrado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE etapa_productiva (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ficha_id BIGINT UNSIGNED NOT NULL,
    aprendiz_id BIGINT UNSIGNED NOT NULL,
    alternativa VARCHAR(120) NOT NULL,
    empresa VARCHAR(180) NOT NULL,
    instructor_seguimiento_id BIGINT UNSIGNED NOT NULL,
    fecha_inicio DATE NULL,
    fecha_fin DATE NULL,
    estado ENUM('activa','finalizada','suspendida') NOT NULL DEFAULT 'activa',
    creado_por BIGINT UNSIGNED NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_etapa_ficha_aprendiz (ficha_id, aprendiz_id),
    KEY idx_etapa_instructor_estado (instructor_seguimiento_id, estado),
    CONSTRAINT fk_etapa_ficha FOREIGN KEY (ficha_id) REFERENCES fichas (id),
    CONSTRAINT fk_etapa_aprendiz FOREIGN KEY (aprendiz_id) REFERENCES usuarios (id),
    CONSTRAINT fk_etapa_instructor FOREIGN KEY (instructor_seguimiento_id) REFERENCES usuarios (id),
    CONSTRAINT fk_etapa_creador FOREIGN KEY (creado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bitacoras_productivas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    etapa_id BIGINT UNSIGNED NOT NULL,
    autor_id BIGINT UNSIGNED NOT NULL,
    entrada TEXT NOT NULL,
    registrada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bitacoras_etapa_fecha (etapa_id, registrada_en),
    CONSTRAINT fk_bitacoras_etapa FOREIGN KEY (etapa_id) REFERENCES etapa_productiva (id) ON DELETE CASCADE,
    CONSTRAINT fk_bitacoras_autor FOREIGN KEY (autor_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE solicitudes_correccion_asistencia (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    asistencia_id BIGINT UNSIGNED NOT NULL,
    estado_anterior ENUM('presente','ausente','tarde','permiso') NOT NULL,
    observacion_anterior VARCHAR(500) NULL,
    estado_propuesto ENUM('presente','ausente','tarde','permiso') NOT NULL,
    observacion_propuesta VARCHAR(500) NULL,
    motivo VARCHAR(500) NOT NULL,
    solicitada_por BIGINT UNSIGNED NOT NULL,
    estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    revisada_por BIGINT UNSIGNED NULL,
    revisada_en DATETIME NULL,
    respuesta VARCHAR(500) NULL,
    creada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_correcciones_estado_fecha (estado, creada_en),
    KEY idx_correcciones_asistencia (asistencia_id, estado),
    CONSTRAINT fk_correcciones_asistencia FOREIGN KEY (asistencia_id) REFERENCES asistencias (id),
    CONSTRAINT fk_correcciones_solicitante FOREIGN KEY (solicitada_por) REFERENCES usuarios (id),
    CONSTRAINT fk_correcciones_revisor FOREIGN KEY (revisada_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
