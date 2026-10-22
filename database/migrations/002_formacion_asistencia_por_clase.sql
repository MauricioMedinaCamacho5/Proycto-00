USE proyecto01;

ALTER TABLE fichas
  ADD COLUMN programa VARCHAR(150) NOT NULL DEFAULT '' AFTER nombre,
  ADD COLUMN trimestre TINYINT UNSIGNED NULL AFTER programa;

ALTER TABLE asistencias
  DROP INDEX uq_asistencia_usuario_ficha_fecha,
  ADD UNIQUE KEY uq_asistencia_sesion (usuario_id, ficha_id, fecha, horario_id);
