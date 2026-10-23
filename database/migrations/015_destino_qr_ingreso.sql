ALTER TABLE ingresos_diarios
  ADD COLUMN ambiente_destino_id BIGINT UNSIGNED NULL AFTER visitante_id,
  ADD CONSTRAINT fk_ingresos_diarios_ambiente FOREIGN KEY (ambiente_destino_id) REFERENCES ambientes(id) ON DELETE SET NULL;
