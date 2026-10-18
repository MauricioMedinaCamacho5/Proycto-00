USE proyecto01;

START TRANSACTION;

UPDATE usuarios
SET correo = 'mauriciomedinacamacho5@gmail.com',
    nombres = 'Mauricio',
    apellidos = 'Medina Camacho'
WHERE correo = 'admin@institucion.local'
  AND rol_id = (SELECT id FROM roles WHERE nombre = 'administrador');

COMMIT;
