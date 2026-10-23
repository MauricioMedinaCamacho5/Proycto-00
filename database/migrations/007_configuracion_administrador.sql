USE proyecto01;

CREATE TABLE configuracion_sistema (
  clave VARCHAR(80) PRIMARY KEY,
  valor SMALLINT UNSIGNED NOT NULL,
  actualizado_por BIGINT UNSIGNED NULL,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_configuracion_sistema_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO configuracion_sistema (clave, valor) VALUES
  ('asistencia_alerta_amarilla', 90),
  ('asistencia_alerta_roja', 80),
  ('dias_habiles_excusa', 3),
  ('horas_correccion_asistencia', 48),
  ('pin_intentos_maximos', 3),
  ('pin_bloqueo_minutos', 5);
