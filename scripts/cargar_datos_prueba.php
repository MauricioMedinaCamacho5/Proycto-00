<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/config/database.php';

const TEST_SEED = 'proyecto01-datos-prueba-2026-10';
const SEED_TABLE = 'datos_prueba_seed';

function seedMarker(PDO $db, string $entity, int $id): void
{
    $stmt = $db->prepare('INSERT INTO ' . SEED_TABLE . ' (semilla, entidad, registro_id) VALUES (?, ?, ?)');
    $stmt->execute([TEST_SEED, $entity, $id]);
}

function insertSeedRow(PDO $db, string $entity, string $sql, array $values): int
{
    $db->prepare($sql)->execute($values);
    $id = (int)$db->lastInsertId();
    seedMarker($db, $entity, $id);
    return $id;
}

function seedUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf('%s-%s-%s-%s-%s', substr($hex,0,8), substr($hex,8,4),
        substr($hex,12,4), substr($hex,16,4), substr($hex,20,12));
}

function checkUniqueSeedValues(PDO $db, string $table, string $column, array $values): void
{
    $stmt = $db->prepare("SELECT {$column} FROM {$table} WHERE {$column} = ?");
    foreach ($values as $value) {
        $stmt->execute([$value]);
        if ($stmt->fetchColumn() !== false) {
            throw new RuntimeException("El valor {$value} ya existe en {$table}. No se insertaron datos.");
        }
    }
}

function printSeedCounts(PDO $db): void
{
    $tables = [
        'programas','fichas','ambientes','competencias','resultados_aprendizaje',
        'usuarios','carnets','equipos','fichas_usuarios','fichas_instructores','horarios',
        'calendario_academico',SEED_TABLE,
    ];
    foreach ($tables as $table) {
        $count = $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        printf("%-32s %d\n", $table, (int)$count);
    }
}

try {
    $db = database();
    $db->exec('CREATE TABLE IF NOT EXISTS ' . SEED_TABLE . ' (
        semilla VARCHAR(60) NOT NULL,
        entidad VARCHAR(80) NOT NULL,
        registro_id BIGINT UNSIGNED NOT NULL,
        marcado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (semilla, entidad, registro_id),
        KEY idx_datos_prueba_entidad (entidad, registro_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $existingMarkers = $db->prepare('SELECT COUNT(*) FROM ' . SEED_TABLE . ' WHERE semilla = ?');
    $existingMarkers->execute([TEST_SEED]);
    if ((int)$existingMarkers->fetchColumn() > 0) {
        throw new RuntimeException('Estos datos de prueba ya están cargados. Ejecuta eliminar_datos_prueba.php antes de volver a cargarlos.');
    }

    $jornadaColumn = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fichas' AND COLUMN_NAME = 'jornada'")->fetchColumn();
    if ((int)$jornadaColumn !== 1) {
        throw new RuntimeException('Falta aplicar database/migrations/013_jornada_fichas.sql para guardar la jornada de cada ficha.');
    }

    $programs = [
        'P001'=>['Análisis y Desarrollo de Software','tecnologo',9],
        'P002'=>['Gestión Contable y de Información Financiera','tecnologo',9],
        'P003'=>['Programación de Software','tecnico',5],
        'P004'=>['Sistemas Teleinformáticos','tecnico',5],
        'P005'=>['Gestión Empresarial','tecnologo',9],
    ];
    $fichas = [
        '3413988'=>['P001','diurna','2026-01-26','2028-04-28',4],
        '3102457'=>['P002','mañana','2026-04-13','2028-07-14',3],
        '3256781'=>['P003','tarde','2026-07-13','2027-10-15',2],
        '3378124'=>['P004','noche','2026-07-13','2027-10-15',2],
        '3489562'=>['P005','mixta','2026-10-05','2029-01-12',1],
    ];
    $environments = [
        '101-1'=>['Laboratorio de sistemas',30,'Sede principal'],
        '101-2'=>['Laboratorio de sistemas',30,'Sede principal'],
        '205-1'=>['Aula',35,'Sede principal'],
        '209-1'=>['Aula',32,'Sede principal'],
        '209-2'=>['Taller de redes',25,'Sede principal'],
        '301-1'=>['Auditorio',80,'Sede principal'],
    ];
    $people = [
        ['estudiante','1075000001','Laura','Gómez Rojas','laura.gomez@prueba.com','Aprendiz2026*','4821',['3413988']],
        ['estudiante','1075000002','Andrés','Perdomo Silva','andres.perdomo@prueba.com','Aprendiz2026*','7395',['3413988']],
        ['estudiante','1075000003','Valentina','Cuéllar Díaz','valentina.cuellar@prueba.com','Aprendiz2026*','6158',['3413988']],
        ['estudiante','1075000004','Juan Esteban','Trujillo','juan.trujillo@prueba.com','Aprendiz2026*','2947',['3256781']],
        ['estudiante','1075000005','Camila','Andrade Muñoz','camila.andrade@prueba.com','Aprendiz2026*','8603',['3256781']],
        ['instructor','1075000021','Catalina','Londoño Ríos','catalina.londono@prueba.com','Instructor2026*','3804',['3413988']],
        ['instructor','1075000022','Hernán Darío','Quintero','hernan.quintero@prueba.com','Instructor2026*','6217',['3413988']],
        ['instructor','1075000023','Paola Andrea','Medina','paola.medina@prueba.com','Instructor2026*','9452',['3256781']],
        ['instructor','1075000024','Fabián','Ortiz Cerquera','fabian.ortiz@prueba.com','Instructor2026*','1736',['3378124']],
        ['instructor','1075000025','Sandra Milena','Tovar','sandra.tovar@prueba.com','Instructor2026*','5089',['3102457','3489562']],
        ['portero','1075000011','Carlos','Polanía Vargas','carlos.polania@prueba.com','Porteria2026*','3172',[]],
        ['portero','1075000012','Luz Marina','Cabrera','luz.cabrera@prueba.com','Porteria2026*','5946',[]],
        ['portero','1075000013','Jorge Iván','Charry','jorge.charry@prueba.com','Porteria2026*','1489',[]],
        ['portero','1075000014','Diana Patricia','Losada','diana.losada@prueba.com','Porteria2026*','7031',[]],
        ['portero','1075000015','Miguel Ángel','Ramírez','miguel.ramirez@prueba.com','Porteria2026*','2564',[]],
    ];

    $competencies = [
        'P001'=>[
            ['C01','Modelar la base de datos del sistema',[
                ['R01','Diseñar el modelo entidad-relación'],
                ['R02','Construir la base de datos en el motor seleccionado'],
            ]],
            ['C02','Desarrollar la solución de software',[
                ['R03','Codificar los módulos según el diseño'],
                ['R04','Realizar pruebas de los módulos'],
            ]],
            ['C03','Implantar la solución de software',[
                ['R05','Desplegar la aplicación en el servidor'],
                ['R06','Elaborar el manual de usuario'],
            ]],
        ],
        'P002'=>[['C01','Competencia de prueba',[['R01','Resultado de aprendizaje de prueba']]]],
        'P003'=>[['C01','Competencia de prueba',[['R01','Resultado de aprendizaje de prueba']]]],
        'P004'=>[['C01','Competencia de prueba',[['R01','Resultado de aprendizaje de prueba']]]],
        'P005'=>[['C01','Competencia de prueba',[['R01','Resultado de aprendizaje de prueba']]]],
    ];

    $scheduleDefinitions = [
        ['3413988',1,'07:00:00','11:00:00','C01','101-1','catalina.londono@prueba.com','Modelar la base de datos del sistema'],
        ['3413988',2,'07:00:00','11:00:00','C02','209-1','hernan.quintero@prueba.com','Desarrollar la solución de software'],
        ['3413988',3,'07:00:00','11:00:00','C02','101-2','catalina.londono@prueba.com','Desarrollar la solución de software'],
        ['3413988',4,'07:00:00','11:00:00','C03','209-1','hernan.quintero@prueba.com','Implantar la solución de software'],
        ['3256781',1,'13:00:00','17:00:00','C01','209-2','paola.medina@prueba.com','Competencia de prueba'],
        ['3256781',3,'13:00:00','17:00:00','C01','101-1','paola.medina@prueba.com','Competencia de prueba'],
        ['3378124',2,'18:00:00','22:00:00','C01','205-1','fabian.ortiz@prueba.com','Competencia de prueba'],
        ['3102457',4,'07:00:00','11:00:00','C01','205-1','sandra.tovar@prueba.com','Competencia de prueba'],
    ];

    $programCodes = array_keys($programs);
    $fichaCodes = array_keys($fichas);
    $environmentCodes = array_keys($environments);
    $competencyCodes = [];
    $outcomeCodes = [];
    foreach ($competencies as $programCode => $items) {
        foreach ($items as [$code, $name, $outcomes]) {
            $competencyCodes[] = $programCode === 'P001' ? $code : $programCode . '-' . $code;
            foreach ($outcomes as [$outcomeCode]) {
                $outcomeCodes[] = $programCode === 'P001' ? $outcomeCode : $programCode . '-' . $outcomeCode;
            }
        }
    }
    $documents = array_column($people, 1);
    $emails = array_column($people, 4);
    foreach ([
        ['programas','codigo',$programCodes],
        ['fichas','codigo',$fichaCodes],
        ['ambientes','codigo',$environmentCodes],
        ['competencias','codigo',$competencyCodes],
        ['resultados_aprendizaje','codigo',$outcomeCodes],
        ['usuarios','documento',$documents],
        ['usuarios','correo',$emails],
    ] as [$table,$column,$values]) {
        checkUniqueSeedValues($db, $table, $column, $values);
    }

    $db->beginTransaction();
    try {
        $roleStmt = $db->prepare('SELECT id FROM roles WHERE nombre = ?');
        $roleIds = [];
        foreach (['estudiante','instructor','portero'] as $role) {
            $roleStmt->execute([$role]);
            $roleIds[$role] = $roleStmt->fetchColumn();
            if (!$roleIds[$role]) {
                throw new RuntimeException("No se encontró el rol requerido: {$role}.");
            }
        }

        $programIds = [];
        foreach ($programs as $code => [$name,$level,$duration]) {
            $programIds[$code] = insertSeedRow($db, 'programas',
                'INSERT INTO programas (codigo,nombre,nivel,duracion_trimestres,estado) VALUES (?,?,?,?,?)',
                [$code,$name,$level,$duration,'activo']);
        }

        $fichaIds = [];
        foreach ($fichas as $code => [$programCode,$jornada,$start,$end,$quarter]) {
            $programName = $programs[$programCode][0];
            $fichaIds[$code] = insertSeedRow($db, 'fichas',
                'INSERT INTO fichas (codigo,programa_id,nombre,programa,trimestre,jornada,fecha_inicio,fecha_fin,estado)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                [$code,$programIds[$programCode],$programName,$programName,$quarter,$jornada,$start,$end,'activa']);
        }

        $environmentIds = [];
        foreach ($environments as $code => [$name,$capacity,$location]) {
            $environmentIds[$code] = insertSeedRow($db, 'ambientes',
                'INSERT INTO ambientes (codigo,nombre,ubicacion,capacidad,estado) VALUES (?,?,?,?,?)',
                [$code,$name,$location,$capacity,'disponible']);
        }

        $competencyIds = [];
        foreach ($competencies as $programCode => $items) {
            foreach ($items as [$code,$name,$outcomes]) {
                $storedCode = $programCode === 'P001' ? $code : $programCode . '-' . $code;
                $competencyId = insertSeedRow($db, 'competencias',
                    'INSERT INTO competencias (codigo,nombre,programa_id,estado) VALUES (?,?,?,?)',
                    [$storedCode,$name,$programIds[$programCode],'activo']);
                $competencyIds[$programCode . ':' . $code] = $competencyId;
                foreach ($outcomes as [$outcomeCode,$description]) {
                    $storedOutcomeCode = $programCode === 'P001' ? $outcomeCode : $programCode . '-' . $outcomeCode;
                    insertSeedRow($db, 'resultados_aprendizaje',
                        'INSERT INTO resultados_aprendizaje (codigo,descripcion,competencia_id,estado) VALUES (?,?,?,?)',
                        [$storedOutcomeCode,$description,$competencyId,'activo']);
                }
            }
        }

        $userIdsByEmail = [];
        foreach ($people as [$role,$document,$firstNames,$lastNames,$email,$password,$pin,$assignedFichas]) {
            $userId = insertSeedRow($db, 'usuarios',
                'INSERT INTO usuarios
                    (rol_id,tipo_documento,documento,nombres,apellidos,correo,password_hash,activo,tratamiento_datos_aceptado_en)
                 VALUES (?,?,?,?,?,?,?,?,NOW())',
                [(int)$roleIds[$role],'CC',$document,$firstNames,$lastNames,$email,
                    password_hash($password,PASSWORD_DEFAULT),1]);
            $cardId = insertSeedRow($db, 'carnets',
                'INSERT INTO carnets (usuario_id,codigo_qr,pin_hash,estado)
                 VALUES (?,?,?,?)',
                [$userId,seedUuid(),
                    password_hash($pin,PASSWORD_DEFAULT),'activo']);
            $userIdsByEmail[$email] = $userId;
            foreach ($assignedFichas as $fichaCode) {
                $fichaId = $fichaIds[$fichaCode];
                if ($role === 'estudiante') {
                    insertSeedRow($db, 'fichas_usuarios',
                        'INSERT INTO fichas_usuarios (ficha_id,usuario_id) VALUES (?,?)',
                        [$fichaId,$userId]);
                } elseif ($role === 'instructor') {
                    insertSeedRow($db, 'fichas_instructores',
                        'INSERT INTO fichas_instructores (ficha_id,instructor_id) VALUES (?,?)',
                        [$fichaId,$userId]);
                }
            }
        }

        foreach ([
            ['laura.gomez@prueba.com','PRUEBA-LAURA-PORTATIL-001','Portátil de Laura Gómez','Lenovo','Negro'],
            ['catalina.londono@prueba.com','PRUEBA-CATALINA-PORTATIL-001','Portátil de Catalina Londoño','Dell','Gris'],
        ] as [$ownerEmail,$serial,$name,$brand,$color]) {
            insertSeedRow($db, 'equipos',
                "INSERT INTO equipos
                    (serial,codigo_qr,nombre,categoria,tipo,marca,color,estado,ubicacion,autorizado,usuario_id)
                 VALUES (?,?,?,'portatil','portatil',?,?,'asignado','Uso personal',1,?)",
                [$serial,seedUuid(),$name,$brand,$color,$userIdsByEmail[$ownerEmail]]);
        }

        foreach ($scheduleDefinitions as [$fichaCode,$weekday,$start,$end,$competencyCode,$environmentCode,$instructorEmail,$subject]) {
            $programCode = $fichas[$fichaCode][0];
            insertSeedRow($db, 'horarios',
                'INSERT INTO horarios
                    (ficha_id,competencia_id,ambiente_id,instructor_id,dia_semana,hora_inicio,hora_fin,asignatura)
                 VALUES (?,?,?,?,?,?,?,?)',
                [$fichaIds[$fichaCode],$competencyIds[$programCode . ':' . $competencyCode],
                    $environmentIds[$environmentCode],$userIdsByEmail[$instructorEmail],
                    $weekday,$start,$end,$subject]);
        }

        $calendarRows = [
            ['2026-11-02','festivo','Día de Todos los Santos'],
            ['2026-11-16','festivo','Independencia de Cartagena'],
            ['2026-12-08','festivo','Día de la Inmaculada Concepción'],
            ['2026-12-25','festivo','Navidad'],
        ];
        $vacationStart = new DateTimeImmutable('2026-12-19');
        $vacationEnd = new DateTimeImmutable('2027-01-12');
        for ($day = $vacationStart; $day <= $vacationEnd; $day = $day->modify('+1 day')) {
            $calendarRows[] = [$day->format('Y-m-d'),'vacaciones','Receso de fin de año (prueba)'];
        }
        foreach ($calendarRows as [$date,$type,$description]) {
            $calendarConflict = $db->prepare('SELECT 1 FROM calendario_academico WHERE fecha = ? AND tipo = ?');
            $calendarConflict->execute([$date,$type]);
            if ($calendarConflict->fetchColumn()) {
                throw new RuntimeException("Ya existe una entrada de calendario para {$date} ({$type}). No se insertaron datos.");
            }
            insertSeedRow($db, 'calendario_academico',
                'INSERT INTO calendario_academico (fecha,tipo,descripcion,estado) VALUES (?,?,?,?)',
                [$date,$type,$description,'activo']);
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }

    echo "Datos de prueba cargados correctamente.\n";
    printSeedCounts($db);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
