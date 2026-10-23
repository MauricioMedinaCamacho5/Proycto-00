<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este archivo solo se puede ejecutar desde la terminal.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/api/config/database.php';

const RESET_TABLES = [
    'accesos',
    'ambientes',
    'asistencias',
    'auditoria',
    'bloqueos',
    'carnets',
    'calendario_academico',
    'competencias',
    'equipos',
    'fichas',
    'fichas_instructores',
    'fichas_usuarios',
    'horarios',
    'juicios_resultados',
    'mantenimientos',
    'movimientos_equipos',
    'notificaciones',
    'novedades',
    'permisos_temporales',
    'prestamos',
    'programas',
    'recuperacion_password',
    'resultados_aprendizaje',
    'reservas',
    'solicitudes_ficha',
    'solicitudes_registro',
    'usuarios',
    'verificaciones_correo',
    'visitantes',
];

const CONFIGURATION_DEFAULTS = [
    'asistencia_alerta_amarilla' => 90,
    'asistencia_alerta_roja' => 80,
    'dias_habiles_excusa' => 3,
    'horas_correccion_asistencia' => 48,
    'pin_intentos_maximos' => 3,
    'pin_bloqueo_minutos' => 5,
];

function resetProfileRoots(string $projectRoot, string $deployRoot): array
{
    $roots = [
        $projectRoot . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles',
    ];
    if ($deployRoot !== '') {
        $roots[] = $deployRoot . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles';
    }

    return array_values(array_unique($roots));
}

function resetExcuseRoots(string $projectRoot, string $deployRoot): array
{
    $roots = [
        $projectRoot . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'excuses',
    ];
    if ($deployRoot !== '') {
        $roots[] = $deployRoot . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'excuses';
    }

    return array_values(array_unique($roots));
}

function listUploadedFiles(array $roots): array
{
    $files = [];
    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }
        if (is_link($root)) {
            throw new RuntimeException('La carpeta de archivos subidos no puede ser un enlace simbólico.');
        }
        $realRoot = realpath($root);
        if ($realRoot === false) {
            throw new RuntimeException('No se pudo resolver una carpeta de archivos subidos.');
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $entry) {
            if (!$entry->isFile() || $entry->isLink() || $entry->getFilename() === '.htaccess') {
                continue;
            }
            $path = $entry->getPathname();
            $files[] = [
                'path' => $path,
                'bytes' => $entry->getSize(),
                'sha256' => hash_file('sha256', $path),
            ];
        }
    }
    usort($files, static fn(array $left, array $right): int => strcmp($left['path'], $right['path']));
    return $files;
}

function buildResetPlan(PDO $db, string $projectRoot, string $deployRoot, string $backupPath): array
{
    $backupRealPath = realpath($backupPath);
    if ($backupRealPath === false || !is_file($backupRealPath) || is_link($backupPath) ||
        filesize($backupRealPath) < 1000 || filemtime($backupRealPath) < time() - 86400) {
        throw new RuntimeException('Se requiere un dump completo de menos de 24 horas. No se borró nada.');
    }

    $tableRows = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    $actualTables = array_map('strval', $tableRows);
    $expectedTables = array_merge(RESET_TABLES, ['configuracion_sistema', 'roles']);
    sort($actualTables);
    sort($expectedTables);
    if ($actualTables !== $expectedTables) {
        throw new RuntimeException(
            'El esquema cambió: hay tablas faltantes o nuevas. No se borró nada; revisa y actualiza la lista explícita del script.'
        );
    }

    $counts = [];
    foreach (RESET_TABLES as $table) {
        $counts[$table] = (int)$db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }
    ksort($counts);

    $autoIncrementRows = $db->query("SELECT TABLE_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND EXTRA LIKE '%auto_increment%' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    $autoIncrementTables = array_values(array_filter(
        array_map('strval', $autoIncrementRows),
        static fn(string $table): bool => in_array($table, RESET_TABLES, true)
    ));

    $settings = [];
    foreach ($db->query('SELECT clave, valor FROM configuracion_sistema ORDER BY clave')->fetchAll() as $row) {
        $settings[(string)$row['clave']] = (int)$row['valor'];
    }

    $roles = array_map(static fn(array $role): array => [
        'id' => (int)$role['id'],
        'nombre' => (string)$role['nombre'],
    ], $db->query('SELECT id, nombre FROM roles ORDER BY id')->fetchAll());
    $expectedRoles = [
        ['id' => 1, 'nombre' => 'administrador'],
        ['id' => 2, 'nombre' => 'instructor'],
        ['id' => 3, 'nombre' => 'estudiante'],
        ['id' => 4, 'nombre' => 'portero'],
    ];
    if ($roles !== $expectedRoles) {
        throw new RuntimeException('Los roles de referencia no coinciden con los cuatro roles esperados. No se borró nada.');
    }

    $adminStmt = $db->prepare("SELECT id, tipo_documento, documento, nombres, apellidos, correo, telefono
        FROM usuarios WHERE correo = ? LIMIT 2");
    $adminStmt->execute(['mauriciomedinacamacho5@gmail.com']);
    $matches = $adminStmt->fetchAll();
    if (count($matches) !== 1) {
        throw new RuntimeException('Se esperaba exactamente una cuenta existente con el correo solicitado. No se borró nada.');
    }
    $identity = $matches[0];
    if (!filter_var($identity['correo'], FILTER_VALIDATE_EMAIL) ||
        trim((string)$identity['documento']) === '' ||
        trim((string)$identity['nombres']) === '' ||
        trim((string)$identity['apellidos']) === '') {
        throw new RuntimeException('La cuenta existente no tiene los datos obligatorios para recrear al administrador.');
    }

    return [
        'backup' => [
            'path' => $backupRealPath,
            'bytes' => filesize($backupRealPath),
            'sha256' => hash_file('sha256', $backupRealPath),
        ],
        'tables' => $counts,
        'auto_increment_tables' => $autoIncrementTables,
        'roles' => $roles,
        'configuration_current' => $settings,
        'configuration_defaults' => CONFIGURATION_DEFAULTS,
        'embedded_profile_photos' => (int)$db->query("SELECT COUNT(*) FROM usuarios
            WHERE foto IS NOT NULL AND foto <> ''")->fetchColumn(),
        'identity' => $identity,
        'profile_files' => listUploadedFiles(resetProfileRoots($projectRoot, $deployRoot)),
        'excuse_files' => listUploadedFiles(resetExcuseRoots($projectRoot, $deployRoot)),
    ];
}

function uuid4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf('%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function clearUploadedFiles(array $files): void
{
    foreach ($files as $file) {
        $path = $file['path'];
        if (!is_file($path) || is_link($path) ||
            !hash_equals((string)$file['sha256'], (string)hash_file('sha256', $path))) {
            throw new RuntimeException('Cambió una foto después de confirmar el plan; detuve la limpieza de archivos.');
        }
        if (!unlink($path)) {
            throw new RuntimeException('No se pudo eliminar un archivo subido: ' . $path);
        }
    }
}

function runWorker(): void
{
    global $argv;
    $mode = $argv[1] ?? '';
    $projectRoot = $argv[2] ?? '';
    $deployRoot = $argv[3] ?? '';
    $backupPath = $argv[4] ?? '';
    if (!in_array($mode, ['--plan', '--execute'], true) ||
        !is_dir($projectRoot) ||
        $deployRoot !== '' && !is_dir($deployRoot) ||
        $backupPath === '') {
        throw new RuntimeException('Uso: php reiniciar_base_worker.php --plan|--execute <raíz-proyecto> <raíz-despliegue> <respaldo>');
    }

    $db = database();
    $plan = buildResetPlan($db, $projectRoot, $deployRoot, $backupPath);
    if ($mode === '--plan') {
        echo json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return;
    }

    $input = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($input) || !isset($input['expected_plan'], $input['password'], $input['pin']) ||
        !is_array($input['expected_plan']) || !is_string($input['password']) ||
        !is_string($input['pin'])) {
        throw new RuntimeException('No se recibieron de forma válida las credenciales y el plan confirmado.');
    }
    if (!hash_equals(
        json_encode($input['expected_plan'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
    )) {
        throw new RuntimeException('Los datos o las fotos cambiaron después de mostrar el plan. No se borró nada; ejecuta el script otra vez.');
    }
    if (strlen($input['password']) < 8 || !preg_match('/^\d{4,6}$/', $input['pin'])) {
        throw new RuntimeException('La contraseña debe tener al menos 8 caracteres y el PIN debe tener de 4 a 6 dígitos.');
    }

    $roleStmt = $db->prepare("SELECT id FROM roles WHERE nombre = 'administrador'");
    $roleStmt->execute();
    $roleId = $roleStmt->fetchColumn();
    if (!$roleId) {
        throw new RuntimeException('No existe el rol administrador. No se borró nada.');
    }

    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        $db->beginTransaction();
        foreach (RESET_TABLES as $table) {
            $db->exec("DELETE FROM `{$table}`");
        }

        $saveSetting = $db->prepare('INSERT INTO configuracion_sistema (clave, valor, actualizado_por)
            VALUES (?, ?, NULL) ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado_por = NULL');
        foreach (CONFIGURATION_DEFAULTS as $key => $value) {
            $saveSetting->execute([$key, $value]);
        }
        $db->exec('UPDATE configuracion_sistema SET actualizado_por = NULL');
        $db->commit();

        foreach ($plan['auto_increment_tables'] as $table) {
            $db->exec("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
        }

        $db->beginTransaction();
        $identity = $plan['identity'];
        $insertUser = $db->prepare('INSERT INTO usuarios
            (rol_id, tipo_documento, documento, nombres, apellidos, correo, password_hash, telefono, foto, activo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, 1)');
        $insertUser->execute([
            (int)$roleId,
            (string)$identity['tipo_documento'],
            (string)$identity['documento'],
            (string)$identity['nombres'],
            (string)$identity['apellidos'],
            'mauriciomedinacamacho5@gmail.com',
            password_hash($input['password'], PASSWORD_DEFAULT),
            $identity['telefono'] === null ? null : (string)$identity['telefono'],
        ]);
        $userId = (int)$db->lastInsertId();
        $insertCard = $db->prepare('INSERT INTO carnets (usuario_id, codigo_qr, pin_hash)
            VALUES (?, ?, ?)');
        $insertCard->execute([
            $userId,
            uuid4(),
            password_hash($input['pin'], PASSWORD_DEFAULT),
        ]);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    } finally {
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    clearUploadedFiles($plan['profile_files']);
    clearUploadedFiles($plan['excuse_files']);
    echo json_encode([
        'status' => 'ok',
        'admin_id' => $userId,
        'email' => 'mauriciomedinacamacho5@gmail.com',
        'card_created' => true,
        'qr_created' => true,
        'profile_files_removed' => count($plan['profile_files']),
        'excuse_files_removed' => count($plan['excuse_files']),
        'settings' => CONFIGURATION_DEFAULTS,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

try {
    runWorker();
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
