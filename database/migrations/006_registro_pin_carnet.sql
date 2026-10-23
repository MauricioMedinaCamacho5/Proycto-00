USE proyecto01;

ALTER TABLE usuarios
  ADD COLUMN tipo_documento VARCHAR(20) NOT NULL DEFAULT 'CC' AFTER rol_id,
  ADD COLUMN tratamiento_datos_aceptado_en DATETIME NULL AFTER foto;

ALTER TABLE carnets
  ADD COLUMN pin_hash VARCHAR(255) NULL AFTER codigo_qr,
  ADD COLUMN pin_intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER pin_hash,
  ADD COLUMN pin_bloqueado_hasta DATETIME NULL AFTER pin_intentos_fallidos;
