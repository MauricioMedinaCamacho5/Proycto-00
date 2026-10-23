USE proyecto01;

ALTER TABLE novedades
  ADD COLUMN estado_excusa ENUM('pendiente','aprobada','rechazada') NULL AFTER descripcion,
  ADD COLUMN soporte_ruta VARCHAR(255) NULL AFTER estado_excusa,
  ADD COLUMN revisado_por BIGINT UNSIGNED NULL AFTER registrado_por,
  ADD COLUMN revisado_en DATETIME NULL AFTER revisado_por,
  ADD INDEX idx_novedades_excusa_aprendiz (usuario_id, tipo, estado_excusa),
  ADD CONSTRAINT fk_novedades_revisor FOREIGN KEY (revisado_por) REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE notificaciones
  ADD COLUMN tipo ENUM('asistencia','excusa','formacion','sistema') NULL AFTER titulo,
  ADD COLUMN remitente_id BIGINT UNSIGNED NULL AFTER tipo,
  ADD CONSTRAINT fk_notificaciones_remitente FOREIGN KEY (remitente_id) REFERENCES usuarios(id) ON DELETE SET NULL;

CREATE TABLE juicios_resultados (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  estudiante_id BIGINT UNSIGNED NOT NULL,
  resultado_id BIGINT UNSIGNED NOT NULL,
  juicio ENUM('aprobado','no_aprobado','por_evaluar') NOT NULL DEFAULT 'por_evaluar',
  evaluado_por BIGINT UNSIGNED NOT NULL,
  evaluado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_juicios_estudiante_resultado (estudiante_id, resultado_id, evaluado_en),
  CONSTRAINT fk_juicios_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_juicios_resultado FOREIGN KEY (resultado_id) REFERENCES resultados_aprendizaje(id) ON DELETE RESTRICT,
  CONSTRAINT fk_juicios_evaluador FOREIGN KEY (evaluado_por) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
