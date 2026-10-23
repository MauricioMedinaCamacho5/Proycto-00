<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/config/database.php';

const TEST_SEED = 'proyecto01-datos-prueba-2026-10';
const SEED_TABLE = 'datos_prueba_seed';

function seedIds(PDO $db, string $entity): array
{
    $stmt = $db->prepare('SELECT registro_id FROM ' . SEED_TABLE . ' WHERE semilla = ? AND entidad = ?');
    $stmt->execute([TEST_SEED,$entity]);
    return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
}

function deleteSeedRows(PDO $db, string $entity, string $table): int
{
    $ids = seedIds($db,$entity);
    if (!$ids) {
        return 0;
    }
    $placeholders = implode(',',array_fill(0,count($ids),'?'));
    $stmt = $db->prepare("DELETE FROM `{$table}` WHERE id IN ({$placeholders})");
    $stmt->execute($ids);
    return $stmt->rowCount();
}

try {
    $db = database();
    $tableExists = $db->query("SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . SEED_TABLE . "'")->fetchColumn();
    if ((int)$tableExists !== 1) {
        throw new RuntimeException('No existe el registro de marcado de datos de prueba; no se eliminó ningún dato.');
    }
    $markerCount = $db->prepare('SELECT COUNT(*) FROM ' . SEED_TABLE . ' WHERE semilla = ?');
    $markerCount->execute([TEST_SEED]);
    if ((int)$markerCount->fetchColumn() === 0) {
        throw new RuntimeException('No hay datos marcados por este seed; no se eliminó ningún dato.');
    }

    $testUsers = seedIds($db,'usuarios');
    if ($testUsers) {
        $placeholders = implode(',',array_fill(0,count($testUsers),'?'));
        $adminCheck = $db->prepare("SELECT u.id, u.correo, r.nombre AS rol FROM usuarios u
            JOIN roles r ON r.id = u.rol_id WHERE u.id IN ({$placeholders})
                AND (r.nombre = 'administrador' OR u.correo NOT LIKE '%@prueba.com')");
        $adminCheck->execute($testUsers);
        $unsafeUsers = $adminCheck->fetchAll();
        if ($unsafeUsers) {
            throw new RuntimeException('La marca contiene una cuenta que no es de prueba o tiene rol administrador. Se canceló la limpieza.');
        }
    }

    $removed = [];
    $db->beginTransaction();
    try {
        foreach ([
            ['horarios','horarios'],
            ['fichas_instructores','fichas_instructores'],
            ['fichas_usuarios','fichas_usuarios'],
            ['equipos','equipos'],
            ['carnets','carnets'],
            ['calendario_academico','calendario_academico'],
            ['resultados_aprendizaje','resultados_aprendizaje'],
            ['competencias','competencias'],
            ['fichas','fichas'],
            ['programas','programas'],
            ['ambientes','ambientes'],
            ['usuarios','usuarios'],
        ] as [$entity,$table]) {
            $removed[$table] = deleteSeedRows($db,$entity,$table);
        }
        $db->prepare('DELETE FROM ' . SEED_TABLE . ' WHERE semilla = ?')->execute([TEST_SEED]);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }

    echo "Se eliminaron los registros marcados de prueba. Las cuentas administradoras no se modifican.\n";
    foreach ($removed as $table => $count) {
        printf("%-32s %d\n", $table, $count);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
