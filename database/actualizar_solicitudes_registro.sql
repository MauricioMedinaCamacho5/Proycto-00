USE proyecto01;

CREATE TABLE IF NOT EXISTS solicitudes_registro (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NULL,
  estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  solicitada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revisada_en DATETIME NULL,
  revisada_por BIGINT UNSIGNED NULL,
  INDEX idx_solicitudes_estado (estado, solicitada_en),
  CONSTRAINT fk_solicitudes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_solicitudes_revisor FOREIGN KEY (revisada_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;
