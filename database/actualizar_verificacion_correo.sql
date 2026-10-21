USE proyecto01;

CREATE TABLE IF NOT EXISTS verificaciones_correo (
  correo VARCHAR(190) PRIMARY KEY,
  codigo_hash VARCHAR(255) NOT NULL,
  datos MEDIUMTEXT NOT NULL,
  vence_en DATETIME NOT NULL,
  intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_verificaciones_vence (vence_en)
) ENGINE=InnoDB;
