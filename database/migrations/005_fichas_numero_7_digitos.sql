USE proyecto01;

DROP PROCEDURE IF EXISTS verificar_fichas_de_siete_digitos;

DELIMITER //

CREATE PROCEDURE verificar_fichas_de_siete_digitos()
BEGIN
  IF EXISTS (SELECT 1 FROM fichas WHERE codigo NOT REGEXP '^[0-9]{7}$') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Corrige los números de ficha existentes a exactamente 7 dígitos antes de aplicar esta migración.';
  ELSE
    ALTER TABLE fichas
      DROP CONSTRAINT chk_fichas_codigo,
      MODIFY codigo CHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
      ADD CONSTRAINT chk_fichas_codigo CHECK (codigo REGEXP '^[0-9]{7}$');
  END IF;
END//

DELIMITER ;

CALL verificar_fichas_de_siete_digitos();
DROP PROCEDURE verificar_fichas_de_siete_digitos;