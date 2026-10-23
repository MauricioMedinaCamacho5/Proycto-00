USE proyecto01;

ALTER TABLE asistencias
  ADD COLUMN estado_justificacion ENUM('pendiente','justificada','injustificada')
    NOT NULL DEFAULT 'pendiente' AFTER observacion;

UPDATE asistencias a
SET a.estado_justificacion = CASE
  WHEN EXISTS (
    SELECT 1 FROM novedades n
    WHERE n.asistencia_id = a.id AND n.tipo = 'inasistencia' AND n.estado_excusa = 'aprobada'
  ) THEN 'justificada'
  WHEN EXISTS (
    SELECT 1 FROM novedades n
    WHERE n.asistencia_id = a.id AND n.tipo = 'inasistencia' AND n.estado_excusa = 'rechazada'
  ) THEN 'injustificada'
  ELSE 'pendiente'
END;

ALTER TABLE notificaciones
  ADD COLUMN evento_clave VARCHAR(190) NULL AFTER mensaje,
  ADD UNIQUE KEY uq_notificaciones_evento_usuario (evento_clave, usuario_id);

CREATE TABLE alertas_asistencia (
  usuario_id BIGINT UNSIGNED NOT NULL,
  ficha_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('amarilla','roja') NOT NULL,
  activa TINYINT(1) NOT NULL DEFAULT 0,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, ficha_id, tipo),
  CONSTRAINT fk_alertas_asistencia_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_alertas_asistencia_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
