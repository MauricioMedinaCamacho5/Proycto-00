USE proyecto01;

CREATE TABLE fichas_instructores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ficha_id BIGINT UNSIGNED NOT NULL,
  instructor_id BIGINT UNSIGNED NOT NULL,
  asignado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ficha_instructor (ficha_id, instructor_id),
  INDEX idx_fichas_instructores_instructor (instructor_id),
  CONSTRAINT fk_fichas_instructores_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE,
  CONSTRAINT fk_fichas_instructores_instructor FOREIGN KEY (instructor_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO fichas_instructores (ficha_id, instructor_id)
SELECT DISTINCT ficha_id, instructor_id FROM horarios;
