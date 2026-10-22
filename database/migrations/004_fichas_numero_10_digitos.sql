USE proyecto01;

START TRANSACTION;

DELETE n FROM novedades n
JOIN asistencias a ON a.id = n.asistencia_id
JOIN fichas f ON f.id = a.ficha_id;

DELETE a FROM asistencias a
JOIN fichas f ON f.id = a.ficha_id;

DELETE h FROM horarios h
JOIN fichas f ON f.id = h.ficha_id;

DELETE FROM fichas;

COMMIT;

ALTER TABLE fichas
  MODIFY codigo CHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  ADD CONSTRAINT chk_fichas_codigo CHECK (codigo REGEXP '^[0-9]{10}$');

ALTER TABLE fichas AUTO_INCREMENT = 1;
