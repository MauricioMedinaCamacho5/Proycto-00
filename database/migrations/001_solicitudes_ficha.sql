CREATE TABLE IF NOT EXISTS solicitudes_ficha (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  ficha_id BIGINT UNSIGNED NOT NULL,
  estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  solicitada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revisada_en DATETIME NULL,
  revisada_por BIGINT UNSIGNED NULL,
  INDEX idx_solicitudes_ficha_estado (estado, solicitada_en),
  INDEX idx_solicitudes_ficha_usuario (usuario_id, estado),
  CONSTRAINT fk_solicitudes_ficha_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_solicitudes_ficha_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE,
  CONSTRAINT fk_solicitudes_ficha_revisor FOREIGN KEY (revisada_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;
