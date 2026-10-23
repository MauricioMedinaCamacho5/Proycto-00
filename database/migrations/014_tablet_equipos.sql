USE proyecto01;

ALTER TABLE equipos
  MODIFY COLUMN tipo ENUM('portatil','tablet','teclado','mouse','maleta','otro') NOT NULL DEFAULT 'otro';
