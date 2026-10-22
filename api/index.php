<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

const SESSION_TIMEOUT = 1800;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && preg_match('#^https?://(localhost|127\.0\.0\.1|192\.168\.\d{1,3}\.\d{1,3})(:\d+)?$#', $origin)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function body(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        respond(['error' => 'El cuerpo debe ser un objeto JSON valido.'], 400);
    }
    return $data;
}

function audit(PDO $db, ?int $actor, string $action, string $entity, ?int $id = null, array $detail = []): void
{
    $stmt = $db->prepare('INSERT INTO auditoria (usuario_id, accion, entidad, entidad_id, detalle) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$actor, $action, $entity, $id, $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null]);
}

function notifyUsers(PDO $db, array $userIds, string $title, string $message): void
{
    $insert = $db->prepare('INSERT INTO notificaciones (usuario_id, titulo, mensaje) VALUES (?, ?, ?)');
    foreach (array_unique(array_map('intval', $userIds)) as $userId) {
        if ($userId > 0) {
            $insert->execute([$userId, $title, $message]);
        }
    }
}

function formationRecipientIds(PDO $db, int $fichaId, array $additionalUserIds = []): array
{
    $staff = $db->prepare("SELECT u.id FROM usuarios u
        JOIN roles r ON r.id = u.rol_id
        WHERE u.activo = 1 AND (
            r.nombre = 'administrador'
            OR r.nombre = 'instructor' AND EXISTS (
                SELECT 1 FROM fichas_instructores fi
                WHERE fi.ficha_id = ? AND fi.instructor_id = u.id
            )
        )");
    $staff->execute([$fichaId]);
    $students = $db->prepare("SELECT fu.usuario_id FROM fichas_usuarios fu
        JOIN usuarios u ON u.id = fu.usuario_id AND u.activo = 1
        WHERE fu.ficha_id = ?");
    $students->execute([$fichaId]);
    return array_merge($staff->fetchAll(PDO::FETCH_COLUMN), $students->fetchAll(PDO::FETCH_COLUMN), $additionalUserIds);
}

function notifyAttendance(PDO $db, int $studentId, int $fichaId, ?int $scheduleId, string $status, string $date, ?string $observation): void
{
    $stmt = $db->prepare("SELECT f.codigo, f.nombre, f.programa, f.trimestre,
            h.asignatura, h.hora_inicio, h.hora_fin, a.nombre AS ambiente,
            CONCAT(i.nombres, ' ', i.apellidos) AS instructor,
            CONCAT(s.nombres, ' ', s.apellidos) AS estudiante
        FROM fichas f
        JOIN usuarios s ON s.id = ?
        LEFT JOIN horarios h ON h.id = ? AND h.ficha_id = f.id
        LEFT JOIN ambientes a ON a.id = h.ambiente_id
        LEFT JOIN usuarios i ON i.id = h.instructor_id
        WHERE f.id = ?");
    $stmt->execute([$studentId, $scheduleId, $fichaId]);
    $context = $stmt->fetch();
    if (!$context) {
        throw new RuntimeException('No se pudo cargar el contexto de la asistencia.');
    }
    $message = 'Motivo: ' . ($observation !== null && trim($observation) !== ''
        ? trim($observation)
        : 'No se registró una observación.');
    $staff = $db->prepare("SELECT u.id FROM usuarios u
        JOIN roles r ON r.id = u.rol_id
        WHERE u.activo = 1 AND (
            r.nombre = 'administrador'
            OR r.nombre = 'instructor' AND EXISTS (
                SELECT 1 FROM fichas_instructores fi
                WHERE fi.ficha_id = ? AND fi.instructor_id = u.id
            )
        )");
    $staff->execute([$fichaId]);
    notifyUsers($db, array_merge([$studentId], $staff->fetchAll(PDO::FETCH_COLUMN)), 'Actualización de asistencia · ' . $context['estudiante'], $message);
}

function notifyFormationChange(PDO $db, string $table, int $recordId): void
{
    if ($table === 'fichas') {
        $stmt = $db->prepare('SELECT id, codigo, nombre, programa, trimestre FROM fichas WHERE id = ?');
        $stmt->execute([$recordId]);
        $ficha = $stmt->fetch();
        if (!$ficha) {
            return;
        }
        $message = 'Ficha ' . $ficha['codigo'] . ' · ' . $ficha['nombre']
            . "\nPrograma: " . ($ficha['programa'] ?: 'Sin programa')
            . "\nTrimestre: " . ($ficha['trimestre'] ?: 'Sin trimestre');
        notifyUsers($db, formationRecipientIds($db, (int)$ficha['id']), 'Actualización de ficha y programa', $message);
        return;
    }
    if ($table === 'horarios') {
        $stmt = $db->prepare("SELECT h.ficha_id, h.asignatura, h.dia_semana, h.hora_inicio, h.hora_fin,
                f.codigo, f.nombre, f.programa, f.trimestre, a.nombre AS ambiente,
                CONCAT(u.nombres, ' ', u.apellidos) AS instructor
            FROM horarios h JOIN fichas f ON f.id = h.ficha_id
            JOIN ambientes a ON a.id = h.ambiente_id
            JOIN usuarios u ON u.id = h.instructor_id
            WHERE h.id = ?");
        $stmt->execute([$recordId]);
        $schedule = $stmt->fetch();
        if (!$schedule) {
            return;
        }
        $days = [1=>'lunes',2=>'martes',3=>'miércoles',4=>'jueves',5=>'viernes',6=>'sábado',7=>'domingo'];
        $message = 'Asignatura: ' . $schedule['asignatura']
            . "\nDía: " . ($days[(int)$schedule['dia_semana']] ?? 'Sin día')
            . "\nHorario: " . substr($schedule['hora_inicio'], 0, 5) . ' a ' . substr($schedule['hora_fin'], 0, 5)
            . "\nAmbiente: " . $schedule['ambiente']
            . "\nInstructor: " . $schedule['instructor']
            . "\nFicha: " . $schedule['codigo'] . ' · ' . $schedule['nombre']
            . "\nPrograma: " . ($schedule['programa'] ?: 'Sin programa')
            . "\nTrimestre: " . ($schedule['trimestre'] ?: 'Sin trimestre');
        notifyUsers($db, formationRecipientIds($db, (int)$schedule['ficha_id']), 'Actualización de horario', $message);
    }
}

function uuid4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
}

function userFromQr(PDO $db, string $qr): ?int
{
    $stmt = $db->prepare('SELECT c.usuario_id FROM carnets c
        JOIN usuarios u ON u.id = c.usuario_id AND u.activo = 1
        WHERE c.codigo_qr = ? AND (c.vence_en IS NULL OR c.vence_en >= CURDATE())
          AND NOT EXISTS (SELECT 1 FROM bloqueos b WHERE b.usuario_id = u.id AND b.desbloqueado_en IS NULL)
        LIMIT 1');
    $stmt->execute([$qr]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

function safeSpreadsheetCell($value)
{
    if (is_string($value) && preg_match('/^[\x00-\x20]*[=+\-@]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

function createRecoveryToken(PDO $db, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO recuperacion_password (usuario_id,token_hash,vence_en) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE))')
        ->execute([$userId, hash('sha256', $token)]);
    return $token;
}

function createSystemMailer(): \PHPMailer\PHPMailer\PHPMailer
{
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        throw new RuntimeException('Falta PHPMailer. Ejecuta composer install en la carpeta del proyecto.');
    }
    $host = getenv('SMTP_HOST') ?: '';
    $username = getenv('SMTP_USER') ?: '';
    $password = getenv('SMTP_PASSWORD') ?: '';
    $from = getenv('SMTP_FROM') ?: $username;
    $port = (int)(getenv('SMTP_PORT') ?: 587);
    if ($host === '' || $username === '' || $password === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Configura SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASSWORD y SMTP_FROM en Apache.');
    }
    $secure = strtolower(getenv('SMTP_SECURE') ?: ($port === 465 ? 'ssl' : 'tls'));
    if (!in_array($secure, ['tls','ssl'], true)) {
        throw new RuntimeException('SMTP_SECURE debe ser tls (puerto 587) o ssl (puerto 465).');
    }
    $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $host;
    $mailer->SMTPAuth = true;
    $mailer->Username = $username;
    $mailer->Password = str_replace(' ', '', $password);
    $mailer->Port = $port;
    $mailer->SMTPSecure = $secure === 'ssl'
        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->CharSet = 'UTF-8';
    $mailer->setFrom($from, 'Gestion Institucional');
    return $mailer;
}

function sendSystemEmail(string $to, string $subject, string $html, string $text): void
{
    $mailer = createSystemMailer();
    $mailer->addAddress($to);
    $mailer->isHTML(true);
    $mailer->Subject = $subject;
    $mailer->Body = $html;
    $mailer->AltBody = $text;
    $mailer->send();
}

function currentUser(PDO $db): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    if (time() - (int)($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
        return null;
    }
    $_SESSION['last_activity'] = time();
    $stmt = $db->prepare('SELECT u.id, u.rol_id, r.nombre AS rol, u.documento, u.nombres, u.apellidos, u.correo,
            COALESCE(c.qr_entregado, 0) AS qr_entregado
        FROM usuarios u JOIN roles r ON r.id = u.rol_id
        LEFT JOIN carnets c ON c.usuario_id = u.id
        WHERE u.id = ? AND u.activo = 1
          AND NOT EXISTS (SELECT 1 FROM bloqueos b WHERE b.usuario_id = u.id AND b.desbloqueado_en IS NULL)');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        $_SESSION = [];
        session_destroy();
        return null;
    }
    return $user;
}

function requireRoles(?array $user, array $roles): array
{
    if (!$user) {
        respond(['error' => 'Debe iniciar sesion.'], 401);
    }
    if (!in_array($user['rol'], $roles, true)) {
        respond(['error' => 'No tiene permisos para esta operacion.'], 403);
    }
    return $user;
}

function requestPath(): array
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = preg_replace('#^.*?/api(?:/index\.php)?#', '', $path);
    return array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
}

function sendRows(PDO $db, string $table, array $columns, ?int $id = null, ?string $where = null, array $params = []): void
{
    $sql = 'SELECT ' . implode(', ', $columns) . " FROM {$table}";
    if ($where !== null) {
        $sql .= " WHERE {$where}";
    }
    if ($id !== null) {
        $sql .= ($where !== null ? ' AND ' : ' WHERE ') . 'id = ?';
        $params[] = $id;
    }
    $sql .= $id === null ? ' ORDER BY id DESC LIMIT 200' : ' LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if ($id !== null && !$rows) {
        respond(['error' => 'Registro no encontrado.'], 404);
    }
    respond($id === null ? ['data' => $rows] : ['data' => $rows[0]]);
}

function validateFichaInput(array &$data, ?array $current = null): void
{
    $allowed = ['codigo','programa','fecha_inicio','fecha_fin'];
    $unknown = array_diff(array_keys($data), $allowed);
    if ($unknown) {
        respond(['error' => 'Campos no permitidos para una ficha: ' . implode(', ', $unknown)], 422);
    }
    $values = array_merge($current ?? [], $data);
    if ($current === null && array_diff($allowed, array_keys($data))) {
        respond(['error' => 'Completa número, programa y fechas de la ficha.'], 422);
    }
    if (array_key_exists('codigo', $data) && !preg_match('/^\d{7}$/', (string)$data['codigo'])) {
        respond(['error' => 'El número de ficha debe tener exactamente 7 dígitos numéricos.'], 422);
    }
    if (array_key_exists('programa', $data) &&
        (!is_string($data['programa']) || trim($data['programa']) === '' || mb_strlen(trim($data['programa'])) > 150)) {
        respond(['error' => 'Escribe el nombre del programa (máximo 150 caracteres).'], 422);
    }
    foreach (['fecha_inicio','fecha_fin'] as $field) {
        if (array_key_exists($field, $data)) {
            $date = is_string($data[$field]) ? DateTime::createFromFormat('!Y-m-d', $data[$field]) : false;
            if (!$date || $date->format('Y-m-d') !== $data[$field]) {
                respond(['error' => 'Las fechas deben ser válidas y usar el formato YYYY-MM-DD.'], 422);
            }
        }
    }
    $start = $values['fecha_inicio'] ?? null;
    $end = $values['fecha_fin'] ?? null;
    if ($start && $end && $start > $end) {
        respond(['error' => 'La fecha de inicio debe ser anterior o igual a la fecha de fin.'], 422);
    }
    if (array_key_exists('programa', $data)) {
        $data['nombre'] = trim($data['programa']);
    }
    if ($current === null) {
        $data['trimestre'] = null;
    }
}

function saveResource(PDO $db, string $table, array $fields, array $input, ?int $id, ?array $user): void
{
    $unknown = array_diff(array_keys($input), $fields);
    if ($unknown) {
        respond(['error' => 'Campos no permitidos: ' . implode(', ', $unknown)], 422);
    }
    if (!$input) {
        respond(['error' => 'Debe enviar al menos un campo.'], 422);
    }

    $values = [];
    foreach ($input as $field => $value) {
        if ($table === 'usuarios' && $field === 'password') {
            if (!is_string($value) || strlen($value) < 8) {
                respond(['error' => 'La contrasena debe tener al menos 8 caracteres.'], 422);
            }
            $field = 'password_hash';
            $value = password_hash($value, PASSWORD_DEFAULT);
        }
        if (in_array($table, ['equipos','carnets'], true) && $field === 'codigo_qr' && !$value) {
            $value = uuid4();
        }
        $values[$field] = $value;
    }
    if (in_array($table, ['equipos','carnets'], true) && !isset($values['codigo_qr'])) {
        $values['codigo_qr'] = uuid4();
    }
    $db->beginTransaction();
    try {
        $checkValues = $values;
        $currentAttendance = null;
        $currentEnrollment = null;
        if ($id !== null && $table === 'asistencias') {
            $existing = $db->prepare('SELECT usuario_id, ficha_id, horario_id, fecha, estado, observacion FROM asistencias WHERE id = ?');
            $existing->execute([$id]);
            $currentAttendance = $existing->fetch();
            if (!$currentAttendance) {
                $db->rollBack();
                respond(['error' => 'Registro no encontrado.'], 404);
            }
            $checkValues = array_merge($currentAttendance, $values);
        }
        if ($id !== null && $table === 'fichas_usuarios') {
            $existing = $db->prepare('SELECT ficha_id, usuario_id FROM fichas_usuarios WHERE id = ?');
            $existing->execute([$id]);
            $currentEnrollment = $existing->fetch();
            if (!$currentEnrollment) {
                $db->rollBack();
                respond(['error' => 'Registro no encontrado.'], 404);
            }
        }
        if ($id !== null && in_array($table, ['reservas','horarios'], true)) {
            $existing = $db->prepare("SELECT * FROM {$table} WHERE id = ?");
            $existing->execute([$id]);
            $current = $existing->fetch();
            if (!$current) {
                $db->rollBack();
                respond(['error' => 'Registro no encontrado.'], 404);
            }
            $checkValues = array_merge($current, $values);
        }
        if ($table === 'reservas' && isset($checkValues['ambiente_id'], $checkValues['inicio'], $checkValues['fin'])) {
            if ($checkValues['inicio'] >= $checkValues['fin']) {
                respond(['error' => 'La fecha de inicio debe ser anterior a la fecha final.'], 422);
            }
            $check = $db->prepare("SELECT id FROM reservas WHERE ambiente_id = ? AND estado IN ('pendiente','aprobada')
                AND inicio < ? AND fin > ? AND id <> ? FOR UPDATE");
            $check->execute([$checkValues['ambiente_id'], $checkValues['fin'], $checkValues['inicio'], $id ?? 0]);
            if ($check->fetch()) {
                $db->rollBack();
                respond(['error' => 'El ambiente ya tiene una reserva que cruza ese horario.'], 409);
            }
        }
        if ($table === 'prestamos' && $id === null) {
            $check = $db->prepare("SELECT id FROM prestamos WHERE equipo_id = ? AND estado = 'activo' FOR UPDATE");
            $check->execute([$values['equipo_id'] ?? 0]);
            if ($check->fetch()) {
                $db->rollBack();
                respond(['error' => 'El equipo ya tiene un prestamo activo.'], 409);
            }
        }
        if ($table === 'horarios' && isset($checkValues['dia_semana'], $checkValues['hora_inicio'], $checkValues['hora_fin'])) {
            if ($checkValues['hora_inicio'] >= $checkValues['hora_fin']) {
                respond(['error' => 'La hora de inicio debe ser anterior a la hora final.'], 422);
            }
            $check = $db->prepare('SELECT id FROM horarios WHERE dia_semana = ? AND (ambiente_id = ? OR instructor_id = ?)
                AND hora_inicio < ? AND hora_fin > ?' . ($id ? ' AND id <> ?' : '') . ' FOR UPDATE');
            $params = [$checkValues['dia_semana'], $checkValues['ambiente_id'] ?? 0, $checkValues['instructor_id'] ?? 0, $checkValues['hora_fin'], $checkValues['hora_inicio']];
            if ($id) {
                $params[] = $id;
            }
            $check->execute($params);
            if ($check->fetch()) {
                $db->rollBack();
                respond(['error' => 'El instructor o el ambiente ya tiene un horario que se cruza.'], 409);
            }
        }

        if ($id === null) {
            $columns = array_keys($values);
            $sql = "INSERT INTO {$table} (" . implode(',', $columns) . ') VALUES (' .
                implode(',', array_fill(0, count($columns), '?')) . ')';
            $stmt = $db->prepare($sql);
            $stmt->execute(array_values($values));
            $id = (int)$db->lastInsertId();
            audit($db, (int)$user['id'], 'crear', $table, $id);
            if ($table === 'usuarios') {
                $db->prepare('INSERT INTO carnets (usuario_id,codigo_qr) VALUES (?,?)')->execute([$id, uuid4()]);
            }
            if ($table === 'visitantes') {
                $db->prepare('INSERT INTO accesos (visitante_id,registrado_por,tipo) VALUES (?,?,?)')->execute([$id, $user['id'], 'ingreso']);
            }
            if ($table === 'prestamos') {
                $db->prepare("UPDATE equipos SET estado = 'prestado' WHERE id = ?")->execute([$values['equipo_id']]);
            }
            if ($table === 'bloqueos') {
                $db->prepare('UPDATE usuarios SET activo = 0 WHERE id = ?')->execute([$values['usuario_id']]);
            }
        } else {
            $assignments = implode(',', array_map(static fn($field) => "{$field} = ?", array_keys($values)));
            $stmt = $db->prepare("UPDATE {$table} SET {$assignments} WHERE id = ?");
            $stmt->execute([...array_values($values), $id]);
            if ($stmt->rowCount() === 0 && !$db->query("SELECT id FROM {$table} WHERE id = " . (int)$id)->fetch()) {
                $db->rollBack();
                respond(['error' => 'Registro no encontrado.'], 404);
            }
            audit($db, (int)$user['id'], 'actualizar', $table, $id);
            if ($table === 'prestamos' && ($values['estado'] ?? null) === 'devuelto') {
                $equipment = $db->prepare('SELECT equipo_id FROM prestamos WHERE id = ?');
                $equipment->execute([$id]);
                $equipmentId = $equipment->fetchColumn();
                if ($equipmentId) {
                    $db->prepare("UPDATE equipos SET estado = 'disponible' WHERE id = ? AND NOT EXISTS
                        (SELECT 1 FROM prestamos WHERE equipo_id = ? AND estado = 'activo' AND id <> ?)")->execute([$equipmentId, $equipmentId, $id]);
                }
            }
            if ($table === 'bloqueos' && array_key_exists('desbloqueado_en', $values) && $values['desbloqueado_en'] !== null) {
                $db->prepare('UPDATE usuarios SET activo = NOT EXISTS
                    (SELECT 1 FROM bloqueos WHERE usuario_id = (SELECT usuario_id FROM bloqueos WHERE id = ?) AND desbloqueado_en IS NULL)
                    WHERE id = (SELECT usuario_id FROM bloqueos WHERE id = ?)')->execute([$id, $id]);
            }
            if ($table === 'asistencias' &&
                ($currentAttendance['usuario_id'] != $checkValues['usuario_id'] ||
                    $currentAttendance['ficha_id'] != $checkValues['ficha_id'] ||
                    $currentAttendance['horario_id'] != $checkValues['horario_id'] ||
                    $currentAttendance['fecha'] != $checkValues['fecha'] ||
                    $currentAttendance['estado'] != $checkValues['estado'] ||
                    $currentAttendance['observacion'] != $checkValues['observacion'])) {
                notifyAttendance(
                    $db,
                    (int)$checkValues['usuario_id'],
                    (int)$checkValues['ficha_id'],
                    $checkValues['horario_id'] === null ? null : (int)$checkValues['horario_id'],
                    (string)$checkValues['estado'],
                    (string)$checkValues['fecha'],
                    $checkValues['observacion'] === null ? null : (string)$checkValues['observacion']
                );
            }
        }
        if (in_array($table, ['fichas','horarios'], true)) {
            notifyFormationChange($db, $table, $id);
        } elseif ($table === 'fichas_usuarios') {
            $enrollment = $db->prepare("SELECT fu.ficha_id, fu.usuario_id, f.codigo, f.nombre, f.programa, f.trimestre,
                    CONCAT(u.nombres, ' ', u.apellidos) AS estudiante
                FROM fichas_usuarios fu JOIN fichas f ON f.id = fu.ficha_id
                JOIN usuarios u ON u.id = fu.usuario_id WHERE fu.id = ?");
            $enrollment->execute([$id]);
            $record = $enrollment->fetch();
            if ($record && (!$currentEnrollment ||
                (int)$currentEnrollment['ficha_id'] !== (int)$record['ficha_id'] ||
                (int)$currentEnrollment['usuario_id'] !== (int)$record['usuario_id'])) {
                $staff = $db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id = u.rol_id
                    WHERE u.activo = 1 AND r.nombre IN ('administrador','instructor')")->fetchAll(PDO::FETCH_COLUMN);
                $message = $record['estudiante'] . ' fue asignado a la ficha ' . $record['codigo'] . ' · ' . $record['nombre']
                    . "\nPrograma: " . ($record['programa'] ?: 'Sin programa')
                    . "\nTrimestre: " . ($record['trimestre'] ?: 'Sin trimestre');
                $affectedStudents = [(int)$record['usuario_id']];
                if ($currentEnrollment) {
                    $affectedStudents[] = (int)$currentEnrollment['usuario_id'];
                }
                notifyUsers($db, array_merge($staff, $affectedStudents), 'Actualización de asignación de ficha', $message);
            }
        }
        $db->commit();
        respond(['data' => ['id' => $id]], $id && $_SERVER['REQUEST_METHOD'] === 'POST' ? 201 : 200);
    } catch (PDOException $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

try {
    $db = database();
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $secure, 'path' => '/']);
    session_start();
    $parts = requestPath();
    $method = $_SERVER['REQUEST_METHOD'];
    $route = $parts[0] ?? '';
    $user = currentUser($db);

    if ($route === 'health' && $method === 'GET') {
        respond(['status' => 'ok', 'service' => 'gestion-institucional']);
    }
    if ($route === 'auth' && ($parts[1] ?? '') === 'registration-options' && $method === 'GET') {
        $fichas = $db->query("SELECT id, codigo, nombre, programa, trimestre FROM fichas
            WHERE fecha_inicio <= CURRENT_DATE() AND fecha_fin >= CURRENT_DATE() ORDER BY codigo")->fetchAll();
        respond(['data' => ['fichas' => $fichas]]);
    }
    if ($route === 'auth' && ($parts[1] ?? '') === 'register' && !isset($parts[2]) && $method === 'POST') {
        $data = body();
        foreach (['documento','nombres','apellidos','correo','password','telefono'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field])) {
                respond(['error' => 'Faltan datos obligatorios o tienen un formato incorrecto.'], 422);
            }
        }
        if (isset($data['foto']) && $data['foto'] !== null && !is_string($data['foto'])) {
            respond(['error' => 'La foto del carné no tiene un formato válido.'], 422);
        }
        $registration = [
            'documento' => trim($data['documento']),
            'nombres' => trim($data['nombres']),
            'apellidos' => trim($data['apellidos']),
            'correo' => trim($data['correo']),
            'telefono' => trim($data['telefono']),
            'foto' => $data['foto'] ?? null,
        ];
        $password = $data['password'];

        if ($registration['documento'] === '' || strlen($registration['documento']) > 30 ||
            $registration['nombres'] === '' || strlen($registration['nombres']) > 100 ||
            $registration['apellidos'] === '' || strlen($registration['apellidos']) > 100 ||
            !filter_var($registration['correo'], FILTER_VALIDATE_EMAIL) || strlen($registration['correo']) > 190 ||
            strlen($password) < 8 || $registration['telefono'] === '' || strlen($registration['telefono']) > 30 ||
            preg_match('/[\x00-\x1F\x7F]/', $registration['documento'])) {
            respond(['error' => 'Complete los datos correctamente, incluya el teléfono y use una contraseña de al menos 8 caracteres.'], 422);
        }
        if ($registration['foto'] !== null) {
            if (strlen($registration['foto']) > 1400000 || !preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/]+={0,2})$#', $registration['foto'], $photoMatch)) {
                respond(['error' => 'La foto debe ser JPG, PNG o WEBP y no superar 1 MB.'], 422);
            }
            $photoBytes = base64_decode($photoMatch[2], true);
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($fileInfo === false) {
                throw new RuntimeException('No se pudo validar el formato de la foto.');
            }
            try {
                $photoInfo = $photoBytes === false ? false : finfo_buffer($fileInfo, $photoBytes);
            } finally {
                finfo_close($fileInfo);
            }
            $allowedPhotoTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if ($photoBytes === false || strlen($photoBytes) > 1048576 || !in_array($photoInfo, $allowedPhotoTypes, true)) {
                respond(['error' => 'La foto no es una imagen JPG, PNG o WEBP válida, o supera 1 MB.'], 422);
            }
        }

        $requestedRole = $data['rol'] ?? 'estudiante';
        if (!in_array($requestedRole, ['estudiante','instructor','portero'], true)) {
            respond(['error' => 'El registro publico solo admite estudiantes, instructores o porteria.'], 422);
        }
        $fichaId = null;
        if ($requestedRole === 'estudiante') {
            if (!isset($data['ficha_id']) || !ctype_digit((string)$data['ficha_id']) || (int)$data['ficha_id'] < 1) {
                respond(['error' => 'Selecciona una ficha activa para completar el registro.'], 422);
            }
            $fichaId = (int)$data['ficha_id'];
        } elseif (isset($data['ficha_id'])) {
            respond(['error' => 'Solo los estudiantes pueden seleccionar una ficha al registrarse.'], 422);
        }
        $duplicate = $db->prepare('SELECT 1 FROM usuarios WHERE documento = ? OR correo = ? LIMIT 1');
        $duplicate->execute([$registration['documento'], $registration['correo']]);
        if ($duplicate->fetchColumn()) {
            respond(['error' => 'Ya existe una cuenta con ese documento o correo. Inicie sesion o use la recuperacion de contrasena.'], 409);
        }
        $role = $db->prepare('SELECT id FROM roles WHERE nombre = ? LIMIT 1');
        $role->execute([$requestedRole]);
        $roleId = $role->fetchColumn();
        if (!$roleId) {
            respond(['error' => 'No se encontro el rol solicitado. Contacte al administrador.'], 500);
        }
        $db->beginTransaction();
        try {
            $student = $requestedRole === 'estudiante';
            if ($student) {
                $activeFicha = $db->prepare("SELECT id FROM fichas
                    WHERE id = ? AND fecha_inicio <= CURRENT_DATE() AND fecha_fin >= CURRENT_DATE() FOR UPDATE");
                $activeFicha->execute([$fichaId]);
                if (!$activeFicha->fetchColumn()) {
                    $db->rollBack();
                    respond(['error' => 'La ficha seleccionada ya no está disponible. Actualiza la página y elige una ficha activa.'], 422);
                }
            }
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $db->prepare('INSERT INTO usuarios (rol_id,documento,nombres,apellidos,correo,password_hash,telefono,foto,activo) VALUES (?,?,?,?,?,?,?,?,?)');
            $insert->execute([
                (int)$roleId, $registration['documento'], $registration['nombres'], $registration['apellidos'],
                $registration['correo'], $passwordHash, $registration['telefono'], $registration['foto'], $student ? 1 : 0,
            ]);
            $userId = (int)$db->lastInsertId();
            $qrCode = uuid4();
            $db->prepare('INSERT INTO carnets (usuario_id,codigo_qr) VALUES (?,?)')->execute([$userId, $qrCode]);
            if ($student) {
                $db->prepare('INSERT INTO fichas_usuarios (ficha_id,usuario_id) VALUES (?,?)')->execute([$fichaId, $userId]);
                audit($db, $userId, 'registro_publico', 'usuarios', $userId, ['rol' => $requestedRole]);
            } else {
                $db->prepare('INSERT INTO solicitudes_registro (usuario_id) VALUES (?)')->execute([$userId]);
                audit($db, $userId, 'solicitar_registro_personal', 'usuarios', $userId, ['rol' => $requestedRole]);
            }
            $db->commit();

            $card = [
                'codigo_qr' => $qrCode,
                'usuario' => [
                    'documento' => $registration['documento'], 'nombres' => $registration['nombres'],
                    'apellidos' => $registration['apellidos'], 'correo' => $registration['correo'], 'telefono' => $registration['telefono'],
                    'rol' => $requestedRole, 'foto' => $registration['foto'],
                ],
            ];
            if (!$student) {
                respond(['data' => [
                    'pending_approval' => true,
                    'message' => 'Tu solicitud fue enviada. Un administrador revisará tu cuenta antes de activarla.',
                    'card' => $card,
                ]], 201);
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['last_activity'] = time();
            $db->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$userId]);
            respond(['data' => [
                'user' => currentUser($db),
                'message' => 'Cuenta de estudiante creada correctamente.',
                'card' => $card,
            ]], 201);
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }
    if ($route === 'registration-requests' && ($parts[1] ?? '') === 'count' && $method === 'GET') {
        requireRoles($user, ['administrador']);
        $count = (int)$db->query("SELECT COUNT(*) FROM solicitudes_registro WHERE estado = 'pendiente'")->fetchColumn();
        respond(['data' => $count]);
    }
    if ($route === 'registration-requests' && $method === 'GET') {
        requireRoles($user, ['administrador']);
        $stmt = $db->query("SELECT s.id, u.documento, u.nombres, u.apellidos, u.correo, u.telefono, r.nombre AS rol, s.solicitada_en
            FROM solicitudes_registro s
            JOIN usuarios u ON u.id = s.usuario_id
            JOIN roles r ON r.id = u.rol_id
            WHERE s.estado = 'pendiente'
            ORDER BY s.solicitada_en, s.id");
        respond(['data' => $stmt->fetchAll()]);
    }
    if ($route === 'registration-requests' && isset($parts[1], $parts[2]) &&
        ctype_digit($parts[1]) && in_array($parts[2], ['approve','reject'], true) && $method === 'POST') {
        $actor = requireRoles($user, ['administrador']);
        $requestId = (int)$parts[1];
        $db->beginTransaction();
        $stmt = $db->prepare("SELECT s.usuario_id, u.rol_id, r.nombre AS rol
            FROM solicitudes_registro s
            JOIN usuarios u ON u.id = s.usuario_id
            JOIN roles r ON r.id = u.rol_id
            WHERE s.id = ? AND s.estado = 'pendiente' AND u.activo = 0
              AND r.nombre IN ('instructor','portero')
            FOR UPDATE");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();
        if (!$request) {
            $db->rollBack();
            respond(['error' => 'La solicitud no existe o ya fue revisada.'], 404);
        }

        if ($parts[2] === 'approve') {
            $db->prepare('UPDATE usuarios SET activo = 1 WHERE id = ?')->execute([$request['usuario_id']]);
            $existingCard = $db->prepare('SELECT 1 FROM carnets WHERE usuario_id = ?');
            $existingCard->execute([$request['usuario_id']]);
            if (!$existingCard->fetchColumn()) {
                $db->prepare('INSERT INTO carnets (usuario_id,codigo_qr) VALUES (?,?)')->execute([$request['usuario_id'], uuid4()]);
            }
            $db->prepare("UPDATE solicitudes_registro SET estado = 'aprobada', revisada_en = NOW(), revisada_por = ? WHERE id = ?")
                ->execute([$actor['id'], $requestId]);
            audit($db, (int)$actor['id'], 'aprobar_registro_personal', 'solicitudes_registro', $requestId, ['rol' => $request['rol']]);
            $message = 'Solicitud aprobada. La cuenta ya puede iniciar sesión.';
        } else {
            $db->prepare("UPDATE solicitudes_registro SET estado = 'rechazada', revisada_en = NOW(), revisada_por = ?, usuario_id = NULL WHERE id = ?")
                ->execute([$actor['id'], $requestId]);
            $db->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$request['usuario_id']]);
            audit($db, (int)$actor['id'], 'rechazar_registro_personal', 'solicitudes_registro', $requestId, ['rol' => $request['rol']]);
            $message = 'Solicitud rechazada y cuenta pendiente eliminada; la persona puede volver a solicitar el acceso.';
        }
        $db->commit();
        respond(['data' => ['message' => $message]]);
    }
    if ($route === 'auth' && ($parts[1] ?? '') === 'login' && $method === 'POST') {
        $data = body();
        $stmt = $db->prepare('SELECT u.id, u.password_hash, u.activo FROM usuarios u
            JOIN carnets c ON c.usuario_id = u.id AND c.qr_entregado = 0
            WHERE u.correo = ? AND u.activo = 1
            AND NOT EXISTS (SELECT 1 FROM bloqueos b WHERE b.usuario_id = u.id AND b.desbloqueado_en IS NULL) LIMIT 1');
        $stmt->execute([$data['correo'] ?? '']);
        $record = $stmt->fetch();
        if (!$record || !password_verify((string)($data['password'] ?? ''), $record['password_hash'])) {
            respond(['error' => 'Correo o contrasena incorrectos, o usuario bloqueado.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$record['id'];
        $_SESSION['last_activity'] = time();
        $db->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$record['id']]);
        audit($db, (int)$record['id'], 'inicio_sesion_temporal', 'usuarios', (int)$record['id']);
        respond(['data' => currentUser($db)]);
    }
    if ($route === 'auth' && ($parts[1] ?? '') === 'qr-login' && $method === 'POST') {
        $data = body();
        $qrCode = $data['codigo_qr'] ?? null;
        if (!is_string($qrCode) || trim($qrCode) === '' || strlen(trim($qrCode)) > 64) {
            respond(['error' => 'El código QR del carné no es válido.'], 422);
        }
        $userId = userFromQr($db, trim($qrCode));
        if (!$userId) {
            respond(['error' => 'No se encontró una cuenta activa con ese carné. Revisa que la solicitud esté aprobada y que el QR no haya vencido.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$userId;
        $_SESSION['last_activity'] = time();
        $db->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$userId]);
        $db->prepare('UPDATE carnets SET qr_entregado = 1 WHERE usuario_id = ?')->execute([$userId]);
        audit($db, (int)$userId, 'inicio_sesion_codigo_qr', 'usuarios', (int)$userId);
        respond(['data' => currentUser($db)]);
    }
    if ($route === 'auth' && ($parts[1] ?? '') === 'card' && $method === 'GET') {
        $account = requireRoles($user, ['administrador','instructor','estudiante','portero']);
        $stmt = $db->prepare('SELECT c.codigo_qr, u.telefono, u.foto FROM carnets c JOIN usuarios u ON u.id = c.usuario_id
            WHERE c.usuario_id = ? AND (c.vence_en IS NULL OR c.vence_en >= CURDATE())');
        $stmt->execute([$account['id']]);
        $card = $stmt->fetch();
        if (!$card) {
            respond(['error' => 'No se encontró un carné activo para esta cuenta.'], 404);
        }
        $account['telefono'] = $card['telefono'];
        $account['foto'] = $card['foto'];
        $account['rol'] = $account['rol'];
        respond(['data' => ['codigo_qr' => $card['codigo_qr'], 'usuario' => $account]]);
    }
    if ($route === 'auth' && ($parts[1] ?? '') === 'card-issued' && $method === 'POST') {
        $data = body();
        $qrCode = $data['codigo_qr'] ?? null;
        if (!is_string($qrCode) || trim($qrCode) === '' || strlen(trim($qrCode)) > 64) {
            respond(['error' => 'El código QR del carné no es válido.'], 422);
        }
        $accountId = null;
        if ($user) {
            $account = requireRoles($user, ['administrador','instructor','estudiante','portero']);
            $accountId = (int)$account['id'];
        }
        $ownership = $accountId === null ? '' : 'c.usuario_id = ? OR ';
        $sql = "UPDATE carnets c SET qr_entregado = 1
            WHERE c.codigo_qr = ? AND ($ownership EXISTS (
                SELECT 1 FROM solicitudes_registro s
                WHERE s.usuario_id = c.usuario_id AND s.estado = 'pendiente'
            ))";
        $stmt = $db->prepare($sql);
        $params = [trim($qrCode)];
        if ($accountId !== null) {
            $params[] = $accountId;
        }
        $stmt->execute($params);
        $ownershipCheck = $accountId === null ? '' : 'c.usuario_id = ? OR ';
        $ownsCard = $db->prepare("SELECT c.qr_entregado FROM carnets c
            WHERE c.codigo_qr = ? AND ($ownershipCheck EXISTS (
                SELECT 1 FROM solicitudes_registro s
                WHERE s.usuario_id = c.usuario_id AND s.estado = 'pendiente'
            ))");
        $ownsCard->execute($params);
        if (!$stmt->rowCount()) {
            $issued = $ownsCard->fetchColumn();
            if ($issued === false) {
                respond(['error' => 'No se encontró un carné pendiente o asociado a esta cuenta.'], 404);
            }
        }
        respond(['data' => ['message' => 'Carné registrado como entregado.', 'qr_entregado' => true]]);
    }
    if ($route === 'auth' && ($parts[1] ?? '') === 'logout' && $method === 'POST') {
        if ($user) {
            audit($db, (int)$user['id'], 'cierre_sesion', 'usuarios', (int)$user['id']);
        }
        $_SESSION = [];
        session_destroy();
        respond(['data' => ['message' => 'Sesion cerrada.']]);
    }
    if ($route === 'auth' && ($parts[1] ?? '') === 'me' && $method === 'GET') {
        respond(['data' => requireRoles($user, ['administrador','instructor','estudiante','portero'])]);
    }
    if ($route === 'auth' && ($parts[1] ?? '') === 'recovery' && $method === 'POST') {
        $data = body();
        try {
            createSystemMailer();
        } catch (RuntimeException $exception) {
            error_log('[gestion-institucional] Password recovery is not configured: ' . $exception->getMessage());
            respond(['error' => 'La recuperación por correo no está disponible. Configura PHPMailer y SMTP de Gmail o Hotmail.'], 503);
        }
        $stmt = $db->prepare('SELECT u.id, u.correo, r.nombre AS rol FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.correo = ? AND u.activo = 1');
        $stmt->execute([$data['correo'] ?? '']);
        $account = $stmt->fetch();
        if ($account) {
            $token = createRecoveryToken($db, (int)$account['id']);
            $baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost/gestion', '/');
            $link = $baseUrl . '/restablecer.html?token=' . urlencode($token);
            $roleLabel = match ($account['rol']) {
                'administrador' => 'administrador',
                'estudiante' => 'estudiante',
                'instructor', 'portero' => 'equipo institucional',
                default => 'usuario',
            };
            try {
                sendSystemEmail(
                    $account['correo'],
                    'Recuperar contraseña como ' . $roleLabel . ' · Gestión Institucional',
                    '<div style="font-family:Arial,sans-serif;color:#183528;max-width:560px;margin:auto;padding:28px;border:1px solid #dce9dc;border-radius:20px"><h2 style="color:#075b37">Recuperar contraseña</h2><p>Recibimos una solicitud para actualizar la contraseña de tu cuenta como ' . htmlspecialchars($roleLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' de Gestión Institucional.</p><p>Para elegir una contraseña nueva, abre este enlace:</p><p><a style="display:inline-block;padding:13px 20px;border-radius:999px;background:#087c45;color:#fff;text-decoration:none;font-weight:bold" href="' . htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Actualizar contraseña</a></p><p>El enlace vence en 30 minutos y solo puede usarse una vez. Si no solicitaste este cambio, ignora este mensaje.</p></div>',
                    "Recuperar contraseña como {$roleLabel} de Gestión Institucional. Usa este enlace para actualizarla (vence en 30 minutos): {$link}"
                );
            } catch (\PHPMailer\PHPMailer\Exception $exception) {
                $db->prepare('DELETE FROM recuperacion_password WHERE token_hash = ?')->execute([hash('sha256', $token)]);
                error_log('[gestion-institucional] Password recovery email send failed: ' . $exception->getMessage());
                respond(['error' => 'No pudimos enviar el correo. Revisa la configuración SMTP de Gmail o Hotmail e inténtalo de nuevo.'], 503);
            }
        }
        respond(['data' => ['message' => 'Si el correo está registrado, recibirás instrucciones para recuperar tu cuenta.']]);
    }
    if ($route === 'auth' && ($parts[1] ?? '') === 'reset-password' && $method === 'POST') {
        $data = body();
        if (strlen((string)($data['password'] ?? '')) < 8 || empty($data['token'])) {
            respond(['error' => 'Se requiere un token y una contrasena de al menos 8 caracteres.'], 422);
        }
        $stmt = $db->prepare('SELECT id, usuario_id FROM recuperacion_password WHERE token_hash = ? AND usado_en IS NULL AND vence_en > NOW() LIMIT 1');
        $stmt->execute([hash('sha256', $data['token'])]);
        $recovery = $stmt->fetch();
        if (!$recovery) {
            respond(['error' => 'El enlace no es valido o ya vencio.'], 400);
        }
        $db->beginTransaction();
        $db->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([password_hash($data['password'], PASSWORD_DEFAULT), $recovery['usuario_id']]);
        $db->prepare('UPDATE recuperacion_password SET usado_en = NOW() WHERE id = ?')->execute([$recovery['id']]);
        audit($db, (int)$recovery['usuario_id'], 'restablecer_contrasena', 'usuarios', (int)$recovery['usuario_id']);
        $db->commit();
        respond(['data' => ['message' => 'Contrasena actualizada.']]);
    }

    $roles = [
        'users' => ['administrador'], 'ficha-assignments' => ['administrador'],
        'environments' => ['administrador','instructor'],
        'fichas' => ['administrador'], 'enrollments' => ['administrador'], 'cards' => ['administrador','estudiante'], 'maintenance' => ['administrador'],
        'schedules' => ['administrador','instructor','estudiante'],
        'reservations' => ['administrador'], 'attendance' => ['administrador','instructor','estudiante'],
        'novelties' => ['administrador','instructor'], 'accesses' => ['administrador','portero'],
        'visitors' => ['administrador','portero'], 'equipment' => ['administrador','portero'],
        'equipment-movements' => ['administrador','portero'], 'loans' => ['administrador','portero'],
        'temporary-permits' => ['administrador','portero'], 'notifications' => ['administrador','instructor','estudiante','portero'],
        'blocks' => ['administrador'],
    ];
    if ($route === 'dashboard' && $method === 'GET') {
        requireRoles($user, ['administrador','instructor','portero']);
        $stats = [];
        foreach ([
            'usuarios' => 'SELECT COUNT(*) FROM usuarios WHERE activo = 1',
            'ambientes' => 'SELECT COUNT(*) FROM ambientes',
            'asistencias_hoy' => 'SELECT COUNT(*) FROM asistencias WHERE fecha = CURDATE()',
            'accesos_hoy' => 'SELECT COUNT(*) FROM accesos WHERE DATE(fecha_hora) = CURDATE()',
            'equipos' => 'SELECT COUNT(*) FROM equipos WHERE estado <> "baja"',
            'reservas_activas' => 'SELECT COUNT(*) FROM reservas WHERE estado = "aprobada" AND inicio <= NOW() AND fin >= NOW()',
            'ocupacion_pct' => 'SELECT IF(COUNT(*) = 0, 0, ROUND(100 * SUM(estado = "ocupado") / COUNT(*))) FROM ambientes',
        ] as $key => $sql) {
            $stats[$key] = (int)$db->query($sql)->fetchColumn();
        }
        respond(['data' => $stats]);
    }
    if ($route === 'options' && $method === 'GET') {
        $actor = requireRoles($user, ['administrador','instructor']);
        $fichaSql = "SELECT id, codigo, nombre, programa, trimestre FROM fichas
            WHERE fecha_inicio <= CURRENT_DATE() AND fecha_fin >= CURRENT_DATE()";
        if ($actor['rol'] === 'instructor') {
            $fichaSql .= ' AND EXISTS (SELECT 1 FROM fichas_instructores fi
                WHERE fi.ficha_id = fichas.id AND fi.instructor_id = ?)';
        }
        $fichaSql .= ' ORDER BY codigo';
        $fichaStmt = $db->prepare($fichaSql);
        $fichaStmt->execute($actor['rol'] === 'instructor' ? [(int)$actor['id']] : []);
        $fichas = $fichaStmt->fetchAll();
        $ambientes = $db->query("SELECT id, codigo, nombre FROM ambientes
            WHERE estado <> 'mantenimiento' ORDER BY codigo")->fetchAll();
        $instructorSql = "SELECT u.id, u.documento, u.nombres, u.apellidos FROM usuarios u
            JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'instructor' AND u.activo = 1";
        if ($actor['rol'] === 'instructor') {
            $instructorSql .= ' AND u.id = ?';
        }
        $instructorSql .= ' ORDER BY u.apellidos, u.nombres';
        $instructorStmt = $db->prepare($instructorSql);
        $instructorStmt->execute($actor['rol'] === 'instructor' ? [(int)$actor['id']] : []);
        $instructors = $instructorStmt->fetchAll();
        $studentSql = "SELECT u.id, u.documento, u.nombres, u.apellidos FROM usuarios u
            JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'estudiante' AND u.activo = 1";
        if ($actor['rol'] === 'instructor') {
            $studentSql .= ' AND EXISTS (SELECT 1 FROM fichas_usuarios fu
                JOIN fichas_instructores fi ON fi.ficha_id = fu.ficha_id
                WHERE fu.usuario_id = u.id AND fi.instructor_id = ?)';
        }
        $studentSql .= ' ORDER BY u.apellidos, u.nombres';
        $studentStmt = $db->prepare($studentSql);
        $studentStmt->execute($actor['rol'] === 'instructor' ? [(int)$actor['id']] : []);
        $students = $studentStmt->fetchAll();
        respond(['data' => ['fichas' => $fichas, 'ambientes' => $ambientes,
            'instructores' => $instructors, 'estudiantes' => $students]]);
    }
    if ($route === 'attendance' && ($parts[1] ?? '') === 'alerts' && $method === 'GET') {
        $actor = requireRoles($user, ['administrador','instructor']);
        $alertSql = "SELECT a.usuario_id, u.documento, u.nombres, u.apellidos, a.fecha, a.estado
            FROM asistencias a
            JOIN usuarios u ON u.id = a.usuario_id
            JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
            WHERE a.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            " . ($actor['rol'] === 'instructor'
                ? "AND EXISTS (SELECT 1 FROM fichas_instructores fi
                    WHERE fi.ficha_id = a.ficha_id AND fi.instructor_id = ?)"
                : '') . "
            ORDER BY a.usuario_id, a.fecha DESC";
        $stmt = $db->prepare($alertSql);
        $stmt->execute($actor['rol'] === 'instructor' ? [(int)$actor['id']] : []);
        $absences = [];
        $alerts = [];
        foreach ($stmt->fetchAll() as $row) {
            $studentId = (int)$row['usuario_id'];
            if (isset($alerts[$studentId])) {
                continue;
            }
            if ($row['estado'] === 'ausente') {
                $absences[$studentId][] = $row['fecha'];
                if (count($absences[$studentId]) >= 3) {
                    $alerts[$studentId] = [
                        'usuario_id' => $studentId,
                        'documento' => $row['documento'],
                        'estudiante' => $row['nombres'] . ' ' . $row['apellidos'],
                        'fechas' => array_slice($absences[$studentId], 0, 3),
                    ];
                }
            } else {
                $absences[$studentId] = [];
            }
        }
        respond(['data' => array_values($alerts)]);
    }
    if ($route === 'attendance' && ($parts[1] ?? '') === 'summary' && $method === 'GET') {
        $student = requireRoles($user, ['estudiante']);
        $stmt = $db->prepare("SELECT
            COUNT(*) AS total,
            COALESCE(SUM(estado = 'presente'), 0) AS presentes,
            COALESCE(SUM(estado = 'ausente'), 0) AS ausencias,
            COALESCE(SUM(estado = 'tarde'), 0) AS retrasos,
            COALESCE(SUM(estado = 'permiso'), 0) AS permisos
            FROM asistencias WHERE usuario_id = ?");
        $stmt->execute([$student['id']]);
        $summary = $stmt->fetch();
        $attended = (int)$summary['presentes'] + (int)$summary['retrasos'];
        $tracked = $attended + (int)$summary['ausencias'];
        $summary['total'] = (int)$summary['total'];
        $summary['presentes'] = (int)$summary['presentes'];
        $summary['ausencias'] = (int)$summary['ausencias'];
        $summary['retrasos'] = (int)$summary['retrasos'];
        $summary['permisos'] = (int)$summary['permisos'];
        $summary['evaluadas'] = $tracked;
        $summary['porcentaje'] = $tracked > 0 ? (int)round(100 * $attended / $tracked) : 0;
        respond(['data' => $summary]);
    }
    if ($route === 'attendance' && ($parts[1] ?? '') === 'progress' && $method === 'GET') {
        $actor = requireRoles($user, ['administrador','instructor']);
        $studentId = $_GET['usuario_id'] ?? '';
        if ($studentId !== '') {
            if (!ctype_digit((string)$studentId) || (int)$studentId < 1) {
                respond(['error' => 'El estudiante seleccionado no es válido.'], 422);
            }
            $studentSql = "SELECT 1 FROM usuarios u
                JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
                WHERE u.id = ? AND u.activo = 1";
            if ($actor['rol'] === 'instructor') {
                $studentSql .= " AND EXISTS (
                    SELECT 1 FROM fichas_usuarios fu
                    JOIN fichas_instructores fi ON fi.ficha_id = fu.ficha_id
                    WHERE fu.usuario_id = u.id AND fi.instructor_id = ?
                )";
            }
            $student = $db->prepare($studentSql);
            $studentParams = [(int)$studentId];
            if ($actor['rol'] === 'instructor') {
                $studentParams[] = (int)$actor['id'];
            }
            $student->execute($studentParams);
            if (!$student->fetchColumn()) {
                respond(['error' => 'No se encontró el aprendiz.'], 404);
            }
            $history = $db->prepare("SELECT a.fecha, f.codigo AS ficha_codigo, f.nombre AS ficha_nombre,
                    f.programa, f.trimestre, a.estado, a.observacion, h.asignatura,
                    h.hora_inicio, h.hora_fin, amb.nombre AS ambiente,
                    CONCAT(i.nombres, ' ', i.apellidos) AS instructor
                FROM asistencias a
                JOIN fichas f ON f.id = a.ficha_id
                LEFT JOIN horarios h ON h.id = a.horario_id
                LEFT JOIN ambientes amb ON amb.id = h.ambiente_id
                LEFT JOIN usuarios i ON i.id = h.instructor_id
                WHERE a.usuario_id = ?" . ($actor['rol'] === 'instructor'
                    ? " AND EXISTS (SELECT 1 FROM fichas_instructores fi
                        WHERE fi.ficha_id = a.ficha_id AND fi.instructor_id = ?)"
                    : '') . "
                ORDER BY a.fecha DESC, f.codigo, h.hora_inicio
                LIMIT 500");
            $historyParams = [(int)$studentId];
            if ($actor['rol'] === 'instructor') {
                $historyParams[] = (int)$actor['id'];
            }
            $history->execute($historyParams);
            $historyRows = $history->fetchAll();
            respond(['data' => $historyRows]);
        }
        $requestedFichaId = $_GET['ficha_id'] ?? '';
        if ($requestedFichaId !== '' && (!ctype_digit((string)$requestedFichaId) || (int)$requestedFichaId < 1)) {
            respond(['error' => 'La ficha seleccionada no es válida.'], 422);
        }
        if ($requestedFichaId !== '') {
            $accessibleFicha = $db->prepare("SELECT 1 FROM fichas f
                WHERE f.id = ? AND f.fecha_inicio <= CURRENT_DATE() AND f.fecha_fin >= CURRENT_DATE()" .
                ($actor['rol'] === 'instructor'
                    ? " AND EXISTS (SELECT 1 FROM fichas_instructores fi
                        WHERE fi.ficha_id = f.id AND fi.instructor_id = ?)"
                    : ''));
            $accessibleFicha->execute($actor['rol'] === 'instructor'
                ? [(int)$requestedFichaId, (int)$actor['id']]
                : [(int)$requestedFichaId]);
            if (!$accessibleFicha->fetchColumn()) {
                respond(['error' => 'No tienes acceso a la ficha seleccionada.'], 403);
            }
        }
        $stmt = $db->prepare("SELECT u.documento, u.nombres, u.apellidos,
                u.id AS usuario_id,
                f.codigo AS ficha_codigo, f.nombre AS ficha_nombre, f.programa, f.trimestre,
                COUNT(DISTINCT CASE WHEN a.estado = 'presente' THEN a.id END) AS presentes,
                COUNT(DISTINCT CASE WHEN a.estado = 'ausente' THEN a.id END) AS ausencias,
                COUNT(DISTINCT CASE WHEN a.estado = 'tarde' THEN a.id END) AS retrasos,
                COUNT(DISTINCT CASE WHEN a.estado = 'permiso' THEN a.id END) AS permisos,
                MAX(a.fecha) AS ultima_asistencia
            FROM fichas f
            JOIN fichas_usuarios fu ON fu.ficha_id = f.id
            JOIN usuarios u ON u.id = fu.usuario_id AND u.activo = 1
            JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
            LEFT JOIN asistencias a ON a.usuario_id = u.id AND a.ficha_id = f.id
            WHERE f.fecha_inicio <= CURRENT_DATE() AND f.fecha_fin >= CURRENT_DATE()" .
                ($actor['rol'] === 'instructor'
                    ? ' AND EXISTS (SELECT 1 FROM fichas_instructores fi
                        WHERE fi.ficha_id = f.id AND fi.instructor_id = ?)'
                    : '') .
                ($requestedFichaId !== '' ? ' AND f.id = ?' : '') . "
            GROUP BY u.id, u.documento, u.nombres, u.apellidos, f.id, f.codigo, f.nombre, f.programa, f.trimestre
            ORDER BY u.apellidos, u.nombres, f.codigo");
        $progressParams = $actor['rol'] === 'instructor' ? [(int)$actor['id']] : [];
        if ($requestedFichaId !== '') {
            $progressParams[] = (int)$requestedFichaId;
        }
        $stmt->execute($progressParams);
        respond(['data' => $stmt->fetchAll()]);
    }
    if ($route === 'attendance' && ($parts[1] ?? '') === 'roster' && $method === 'GET') {
        $actor = requireRoles($user, ['administrador','instructor']);
        $fichaConditions = ['f.fecha_inicio <= CURRENT_DATE()', 'f.fecha_fin >= CURRENT_DATE()'];
        $fichaParams = [];
        if ($actor['rol'] === 'instructor') {
            $fichaConditions[] = 'EXISTS (SELECT 1 FROM fichas_instructores fi WHERE fi.ficha_id = f.id AND fi.instructor_id = ?)';
            $fichaParams[] = (int)$actor['id'];
        }
        $fichasStmt = $db->prepare('SELECT f.id, f.codigo, f.nombre, f.programa, f.trimestre FROM fichas f WHERE ' .
            implode(' AND ', $fichaConditions) . ' ORDER BY f.programa, f.codigo');
        $fichasStmt->execute($fichaParams);
        $fichas = $fichasStmt->fetchAll();
        $requestedFicha = $_GET['ficha_id'] ?? '';
        if ($requestedFicha !== '' && (!ctype_digit((string)$requestedFicha) || (int)$requestedFicha < 1)) {
            respond(['error' => 'La ficha seleccionada no es válida.'], 422);
        }
        $fichaId = ctype_digit((string)$requestedFicha) ? (int)$requestedFicha : (int)($fichas[0]['id'] ?? 0);
        $ficha = null;
        foreach ($fichas as $candidate) {
            if ((int)$candidate['id'] === $fichaId) {
                $ficha = $candidate;
                break;
            }
        }
        if (!$ficha) {
            if ($requestedFicha !== '') {
                respond(['error' => 'No tienes acceso a la ficha seleccionada.'], 403);
            }
            respond(['data' => ['fichas' => $fichas, 'estudiantes' => []]]);
        }

        $date = (string)($_GET['fecha'] ?? date('Y-m-d'));
        $parsedDate = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
            respond(['error' => 'La fecha debe usar el formato YYYY-MM-DD.'], 422);
        }
        $studentsStmt = $db->prepare("SELECT u.id, u.documento, u.nombres, u.apellidos, u.correo, u.telefono, u.foto,
                c.codigo_qr, a.id AS asistencia_id, a.estado, a.observacion,
                COALESCE(progress.presentes, 0) AS presentes,
                COALESCE(progress.ausencias, 0) AS ausencias,
                COALESCE(progress.retrasos, 0) AS retrasos,
                COALESCE(progress.permisos, 0) AS permisos
            FROM fichas_usuarios fu
            JOIN usuarios u ON u.id = fu.usuario_id
            JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
            LEFT JOIN carnets c ON c.usuario_id = u.id
            LEFT JOIN asistencias a ON a.id = (
                SELECT today.id FROM asistencias today
                WHERE today.usuario_id = fu.usuario_id AND today.ficha_id = fu.ficha_id AND today.fecha = ?
                ORDER BY today.registrado_en DESC, today.id DESC LIMIT 1
            )
            LEFT JOIN (
                SELECT usuario_id, ficha_id,
                    SUM(estado = 'presente') AS presentes,
                    SUM(estado = 'ausente') AS ausencias,
                    SUM(estado = 'tarde') AS retrasos,
                    SUM(estado = 'permiso') AS permisos
                FROM asistencias
                GROUP BY usuario_id, ficha_id
            ) progress ON progress.usuario_id = fu.usuario_id AND progress.ficha_id = fu.ficha_id
            WHERE fu.ficha_id = ? AND u.activo = 1
            ORDER BY u.nombres, u.apellidos, u.id");
        $studentsStmt->execute([$date, $fichaId]);
        respond(['data' => ['fichas' => $fichas, 'ficha_seleccionada' => $fichaId, 'fecha' => $date,
            'estudiantes' => $studentsStmt->fetchAll()]]);
    }
    if ($route === 'schedules' && ($parts[1] ?? '') === 'availability' && $method === 'GET') {
        $actor = requireRoles($user, ['administrador','instructor']);
        $dayNames = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'];
        $environments = $db->query('SELECT id, codigo, nombre, ubicacion, estado FROM ambientes ORDER BY nombre, codigo')->fetchAll();
        $availability = [];
        foreach ($environments as $environment) {
            foreach ($dayNames as $dayNumber => $dayName) {
                $availability[(int)$environment['id'] . ':' . $dayNumber] = [
                    'ambiente_id' => (int)$environment['id'],
                    'codigo' => $environment['codigo'],
                    'ambiente' => $environment['nombre'],
                    'ubicacion' => $environment['ubicacion'],
                    'dia' => $dayName,
                    'estado' => $environment['estado'] === 'mantenimiento' ? 'mantenimiento' : 'disponible',
                    'horarios' => [],
                ];
            }
        }
        $scheduleSql = "SELECT h.ambiente_id, h.dia_semana, h.hora_inicio, h.hora_fin, h.asignatura,
                f.codigo AS ficha_codigo, f.nombre AS ficha_nombre,
                CONCAT(u.nombres, ' ', u.apellidos) AS instructor" .
                ($actor['rol'] === 'administrador' ? ', 1 AS ficha_asignada' : ', (fi.id IS NOT NULL) AS ficha_asignada') . "
            FROM horarios h
            JOIN fichas f ON f.id = h.ficha_id
                AND f.fecha_inicio <= CURRENT_DATE() AND f.fecha_fin >= CURRENT_DATE()
            LEFT JOIN ambientes a ON a.id = h.ambiente_id
            JOIN usuarios u ON u.id = h.instructor_id" .
                ($actor['rol'] === 'instructor'
                    ? ' LEFT JOIN fichas_instructores fi ON fi.ficha_id = f.id AND fi.instructor_id = ?'
                    : '') . "
            ORDER BY h.ambiente_id, h.dia_semana, h.hora_inicio";
        $schedules = $db->prepare($scheduleSql);
        $schedules->execute($actor['rol'] === 'instructor' ? [(int)$actor['id']] : []);
        foreach ($schedules->fetchAll() as $schedule) {
            $key = (int)$schedule['ambiente_id'] . ':' . (int)$schedule['dia_semana'];
            if (!isset($availability[$key])) {
                continue;
            }
            $availability[$key]['estado'] = $availability[$key]['estado'] === 'mantenimiento' ? 'mantenimiento' : 'ocupado';
            $assigned = (bool)$schedule['ficha_asignada'];
            $availability[$key]['horarios'][] = [
                'inicio' => substr($schedule['hora_inicio'], 0, 5),
                'fin' => substr($schedule['hora_fin'], 0, 5),
                'asignatura' => $assigned ? $schedule['asignatura'] : null,
                'ficha_codigo' => $assigned ? $schedule['ficha_codigo'] : null,
                'ficha_asignada' => $assigned,
                'instructor' => $assigned ? $schedule['instructor'] : null,
            ];
        }
        respond(['data' => array_values($availability)]);
    }
    if ($route === 'schedules' && $method === 'GET' && $user && in_array($user['rol'], ['estudiante','instructor'], true)) {
        $viewer = requireRoles($user, ['estudiante','instructor']);
        $membershipJoin = $viewer['rol'] === 'estudiante'
            ? 'JOIN fichas_usuarios fu ON fu.ficha_id = h.ficha_id AND fu.usuario_id = ?'
            : 'JOIN fichas_instructores fu ON fu.ficha_id = h.ficha_id AND fu.instructor_id = ?';
        $stmt = $db->prepare("SELECT
                CASE h.dia_semana
                    WHEN 1 THEN 'Lunes' WHEN 2 THEN 'Martes' WHEN 3 THEN 'Miércoles'
                    WHEN 4 THEN 'Jueves' WHEN 5 THEN 'Viernes' WHEN 6 THEN 'Sábado'
                    WHEN 7 THEN 'Domingo'
                END AS dia,
                h.hora_inicio, h.hora_fin, h.asignatura,
                f.codigo AS ficha, f.nombre AS nombre_ficha, f.programa, f.trimestre, a.nombre AS ambiente,
                CONCAT(i.nombres, ' ', i.apellidos) AS instructor
            FROM horarios h
            {$membershipJoin}
            JOIN fichas f ON f.id = h.ficha_id
            JOIN ambientes a ON a.id = h.ambiente_id
            JOIN usuarios i ON i.id = h.instructor_id
            ORDER BY f.trimestre, h.dia_semana, h.hora_inicio, h.asignatura");
        $stmt->execute([(int)$viewer['id']]);
        respond(['data' => $stmt->fetchAll()]);
    }
    if ($route === 'equipment-movements' && ($parts[1] ?? '') === 'scan' && $method === 'POST') {
        $actor = requireRoles($user, ['administrador','portero']);
        $data = body();
        if (!in_array($data['tipo'] ?? '', ['ingreso','salida'], true) || empty($data['qr']) || empty($data['responsable_qr'])) {
            respond(['error' => 'Envíe el QR del equipo, el QR del responsable y el tipo de movimiento.'], 422);
        }
        $stmt = $db->prepare('SELECT id FROM equipos WHERE codigo_qr = ? AND autorizado = 1');
        $stmt->execute([$data['qr']]);
        $equipmentId = $stmt->fetchColumn();
        if (!$equipmentId) {
            respond(['error' => 'Equipo QR no registrado o no autorizado.'], 404);
        }
        $responsibleId = userFromQr($db, (string)$data['responsable_qr']);
        if (!$responsibleId) {
            respond(['error' => 'Carnet de responsable no válido.'], 404);
        }
        $db->beginTransaction();
        $stmt = $db->prepare('SELECT tipo, usuario_id FROM movimientos_equipos WHERE equipo_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $stmt->execute([$equipmentId]);
        $previous = $stmt->fetch();
        if (($data['tipo'] === 'ingreso' && ($previous['tipo'] ?? null) === 'ingreso') ||
            ($data['tipo'] === 'salida' && (($previous['tipo'] ?? null) !== 'ingreso' ||
                (int)$previous['usuario_id'] !== (int)$responsibleId))) {
            $db->rollBack();
            respond(['error' => 'El serial debe salir con el mismo responsable registrado en su ingreso.'], 409);
        }
        $db->prepare('INSERT INTO movimientos_equipos (equipo_id,usuario_id,registrado_por,tipo,observacion) VALUES (?,?,?,?,?)')
            ->execute([$equipmentId, $responsibleId, $actor['id'], $data['tipo'], $data['observacion'] ?? null]);
        $movementId = (int)$db->lastInsertId();
        audit($db, (int)$actor['id'], 'registrar_movimiento_equipo', 'movimientos_equipos', $movementId);
        $db->commit();
        respond(['data' => ['id' => $movementId]], 201);
    }
    if ($route === 'attendance' && ($parts[1] ?? '') === 'mark' && $method === 'POST') {
        $actor = requireRoles($user, ['administrador','instructor']);
        $data = body();
        if (empty($data['usuario_id']) && !empty($data['qr'])) {
            $data['usuario_id'] = userFromQr($db, (string)$data['qr']);
        }
        foreach (['usuario_id','ficha_id','estado'] as $required) {
            if (!isset($data[$required])) {
                respond(['error' => "Falta el campo {$required}."], 422);
            }
        }
        if (!ctype_digit((string)$data['usuario_id']) || !ctype_digit((string)$data['ficha_id']) ||
            !in_array($data['estado'], ['presente','ausente','tarde','permiso'], true)) {
            respond(['error' => 'El estudiante, la ficha o el estado de asistencia no son válidos.'], 422);
        }
        $scheduleId = null;
        if (isset($data['horario_id']) && $data['horario_id'] !== '') {
            if (!ctype_digit((string)$data['horario_id']) || (int)$data['horario_id'] < 1) {
                respond(['error' => 'El horario seleccionado no es válido.'], 422);
            }
            $scheduleId = (int)$data['horario_id'];
        }
        $date = (string)($data['fecha'] ?? date('Y-m-d'));
        $parsedDate = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
            respond(['error' => 'La fecha debe usar el formato YYYY-MM-DD.'], 422);
        }
        $observation = $data['observacion'] ?? null;
        if ($observation !== null && (!is_string($observation) || mb_strlen($observation) > 500)) {
            respond(['error' => 'La observación debe tener máximo 500 caracteres.'], 422);
        }
        $observation = $observation === null || trim($observation) === '' ? null : trim($observation);
        $enrollment = $db->prepare("SELECT 1 FROM fichas_usuarios fu
            JOIN usuarios u ON u.id = fu.usuario_id AND u.activo = 1
            JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
            WHERE fu.usuario_id = ? AND fu.ficha_id = ?");
        $enrollment->execute([(int)$data['usuario_id'], (int)$data['ficha_id']]);
        if (!$enrollment->fetchColumn()) {
            respond(['error' => 'El estudiante no está inscrito en la ficha seleccionada.'], 422);
        }
        if ($actor['rol'] === 'instructor') {
            $assignedFicha = $db->prepare('SELECT 1 FROM fichas_instructores WHERE ficha_id = ? AND instructor_id = ?');
            $assignedFicha->execute([(int)$data['ficha_id'], (int)$actor['id']]);
            if (!$assignedFicha->fetchColumn()) {
                respond(['error' => 'Solo puedes registrar asistencia en las fichas que tienes asignadas.'], 403);
            }
        }
        if ($scheduleId !== null) {
            $scheduledClass = $db->prepare('SELECT id FROM horarios WHERE id = ? AND ficha_id = ?');
            $scheduledClass->execute([$scheduleId, (int)$data['ficha_id']]);
            if (!$scheduledClass->fetchColumn()) {
                respond(['error' => 'El horario seleccionado no pertenece a la ficha.'], 422);
            }
        }
        $db->beginTransaction();
        try {
            $existingSql = 'SELECT id, estado, observacion FROM asistencias
                WHERE usuario_id = ? AND ficha_id = ? AND fecha = ? AND ' .
                ($scheduleId === null ? 'horario_id IS NULL' : 'horario_id = ?');
            $existing = $db->prepare($existingSql);
            $existingParams = [(int)$data['usuario_id'], (int)$data['ficha_id'], $date];
            if ($scheduleId !== null) {
                $existingParams[] = $scheduleId;
            }
            $existing->execute($existingParams);
            $previous = $existing->fetch();
            if ($previous) {
                $stmt = $db->prepare('UPDATE asistencias SET estado = ?, observacion = ?, registrado_por = ? WHERE id = ?');
                $stmt->execute([$data['estado'], $observation, $actor['id'], $previous['id']]);
                $id = (int)$previous['id'];
                $action = 'actualizar';
                if ($previous['estado'] !== $data['estado'] || $previous['observacion'] !== $observation) {
                    notifyAttendance($db, (int)$data['usuario_id'], (int)$data['ficha_id'], $scheduleId, $data['estado'], $date, $observation);
                }
            } else {
                $stmt = $db->prepare('INSERT INTO asistencias (usuario_id,ficha_id,horario_id,fecha,estado,registrado_por,observacion)
                    VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([(int)$data['usuario_id'], (int)$data['ficha_id'], $scheduleId, $date,
                    $data['estado'], $actor['id'], $observation]);
                $id = (int)$db->lastInsertId();
                $action = 'crear';
                notifyAttendance($db, (int)$data['usuario_id'], (int)$data['ficha_id'], $scheduleId, $data['estado'], $date, $observation);
            }
            audit($db, (int)$actor['id'], $action, 'asistencias', $id);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        respond(['data' => ['id' => $id]]);
    }
    if ($route === 'accesses' && $method === 'POST') {
        $actor = requireRoles($user, ['administrador','portero']);
        $data = body();
        if (!in_array($data['tipo'] ?? '', ['ingreso','salida'], true)) {
            respond(['error' => 'El tipo debe ser ingreso o salida.'], 422);
        }
        if (!empty($data['qr']) && empty($data['usuario_id'])) {
            $data['usuario_id'] = userFromQr($db, (string)$data['qr']);
        }
        if (empty($data['usuario_id']) && empty($data['visitante_id'])) {
            respond(['error' => 'Indique un usuario o visitante valido.'], 422);
        }
        $db->beginTransaction();
        if (!empty($data['serial'])) {
            $stmt = $db->prepare('SELECT id FROM equipos WHERE serial = ? AND autorizado = 1');
            $stmt->execute([$data['serial']]);
            $equipmentId = $stmt->fetchColumn();
            if (!$equipmentId) {
                respond(['error' => 'Equipo no registrado o no autorizado.'], 404);
            }
            $lastMove = $db->prepare('SELECT tipo, usuario_id FROM movimientos_equipos WHERE equipo_id = ? ORDER BY id DESC LIMIT 1');
            $lastMove->execute([$equipmentId]);
            $previous = $lastMove->fetch();
            if (($data['tipo'] === 'ingreso' && ($previous['tipo'] ?? null) === 'ingreso') ||
                ($data['tipo'] === 'salida' && (($previous['tipo'] ?? null) !== 'ingreso' ||
                    (!empty($previous['usuario_id']) && (int)$previous['usuario_id'] !== (int)($data['usuario_id'] ?? 0))))) {
                $db->rollBack();
                respond(['error' => 'La salida del equipo debe corresponder a su ingreso y responsable registrados.'], 409);
            }
            $db->prepare('INSERT INTO movimientos_equipos (equipo_id,usuario_id,registrado_por,tipo,observacion) VALUES (?,?,?,?,?)')
                ->execute([$equipmentId, $data['usuario_id'] ?? null, $actor['id'], $data['tipo'], $data['observacion'] ?? null]);
        }
        $personColumn = !empty($data['usuario_id']) ? 'usuario_id' : 'visitante_id';
        $personId = !empty($data['usuario_id']) ? $data['usuario_id'] : $data['visitante_id'];
        $lastAccess = $db->prepare("SELECT tipo FROM accesos WHERE {$personColumn} = ? ORDER BY id DESC LIMIT 1");
        $lastAccess->execute([$personId]);
        $previousAccess = $lastAccess->fetchColumn();
        if (($data['tipo'] === 'ingreso' && $previousAccess === 'ingreso') ||
            ($data['tipo'] === 'salida' && $previousAccess !== 'ingreso')) {
            $db->rollBack();
            respond(['error' => 'El movimiento de acceso no corresponde al ultimo ingreso registrado.'], 409);
        }
        $stmt = $db->prepare('INSERT INTO accesos (usuario_id,visitante_id,registrado_por,tipo,observacion) VALUES (?,?,?,?,?)');
        $stmt->execute([$data['usuario_id'] ?? null, $data['visitante_id'] ?? null, $actor['id'], $data['tipo'], $data['observacion'] ?? null]);
        if (!empty($data['visitante_id']) && $data['tipo'] === 'salida') {
            $db->prepare('UPDATE visitantes SET salida_en = NOW() WHERE id = ?')->execute([$data['visitante_id']]);
        }
        $id = (int)$db->lastInsertId();
        audit($db, (int)$actor['id'], 'registrar_' . $data['tipo'], 'accesos', $id);
        $db->commit();
        respond(['data' => ['id' => $id]], 201);
    }

    $resources = [
        'users' => ['table'=>'usuarios','columns'=>['id','rol_id','documento','nombres','apellidos','correo','telefono','activo','ultimo_acceso','creado_en'],'fields'=>['rol_id','documento','nombres','apellidos','correo','password','telefono','activo']],
        'ficha-assignments' => ['table'=>'fichas_instructores','columns'=>['id','ficha_id','instructor_id'],'fields'=>['ficha_id','instructor_id']],
        'environments' => ['table'=>'ambientes','columns'=>['id','codigo','nombre','ubicacion','capacidad','estado','descripcion'],'fields'=>['codigo','nombre','ubicacion','capacidad','estado','descripcion']],
        'fichas' => ['table'=>'fichas','columns'=>['id','codigo','programa','fecha_inicio','fecha_fin','estado'],'fields'=>['codigo','nombre','programa','trimestre','fecha_inicio','fecha_fin']],
        'enrollments' => ['table'=>'fichas_usuarios','columns'=>['id','ficha_id','usuario_id','inscrito_en'],'fields'=>['ficha_id','usuario_id']],
        'cards' => ['table'=>'carnets','columns'=>['id','usuario_id','codigo_qr','emitido_en','vence_en'],'fields'=>['usuario_id','codigo_qr','vence_en']],
        'maintenance' => ['table'=>'mantenimientos','columns'=>['id','ambiente_id','responsable_id','tipo','descripcion','programado_en','finalizado_en','estado'],'fields'=>['ambiente_id','responsable_id','tipo','descripcion','programado_en','finalizado_en','estado']],
        'schedules' => ['table'=>'horarios','columns'=>['id','ficha_id','ambiente_id','instructor_id','dia_semana','hora_inicio','hora_fin','asignatura'],'fields'=>['ficha_id','ambiente_id','instructor_id','dia_semana','hora_inicio','hora_fin','asignatura']],
        'reservations' => ['table'=>'reservas','columns'=>['id','ambiente_id','usuario_id','inicio','fin','motivo','estado','creado_en'],'fields'=>['ambiente_id','usuario_id','inicio','fin','motivo','estado']],
        'attendance' => ['table'=>'asistencias','columns'=>['id','usuario_id','ficha_id','horario_id','fecha','estado','registrado_por','observacion','registrado_en'],'fields'=>['usuario_id','ficha_id','horario_id','fecha','estado','observacion','registrado_por']],
        'novelties' => ['table'=>'novedades','columns'=>['id','usuario_id','asistencia_id','tipo','descripcion','fecha','registrado_por'],'fields'=>['usuario_id','asistencia_id','tipo','descripcion','fecha','registrado_por']],
        'accesses' => ['table'=>'accesos','columns'=>['id','usuario_id','visitante_id','registrado_por','tipo','fecha_hora','observacion'],'fields'=>['usuario_id','visitante_id','tipo','observacion']],
        'visitors' => ['table'=>'visitantes','columns'=>['id','documento','nombres','entidad','motivo','anfitrion_id','ingreso_en','salida_en','registrado_por'],'fields'=>['documento','nombres','entidad','motivo','anfitrion_id','salida_en','registrado_por']],
        'equipment' => ['table'=>'equipos','columns'=>['id','serial','codigo_qr','nombre','categoria','estado','ubicacion','autorizado','creado_en'],'fields'=>['serial','codigo_qr','nombre','categoria','estado','ubicacion','autorizado']],
        'equipment-movements' => ['table'=>'movimientos_equipos','columns'=>['id','equipo_id','usuario_id','registrado_por','tipo','fecha_hora','ubicacion_origen','ubicacion_destino','observacion'],'fields'=>['equipo_id','usuario_id','registrado_por','tipo','ubicacion_origen','ubicacion_destino','observacion']],
        'loans' => ['table'=>'prestamos','columns'=>['id','equipo_id','usuario_id','entregado_por','prestado_en','vence_en','devuelto_en','estado'],'fields'=>['equipo_id','usuario_id','entregado_por','vence_en','devuelto_en','estado']],
        'temporary-permits' => ['table'=>'permisos_temporales','columns'=>['id','usuario_id','visitante_id','autorizado_por','desde','hasta','motivo','activo'],'fields'=>['usuario_id','visitante_id','autorizado_por','desde','hasta','motivo','activo']],
        'notifications' => ['table'=>'notificaciones','columns'=>['id','usuario_id','titulo','mensaje','leida_en','creado_en'],'fields'=>['usuario_id','titulo','mensaje','leida_en']],
        'blocks' => ['table'=>'bloqueos','columns'=>['id','usuario_id','administrador_id','motivo','bloqueado_en','desbloqueado_en'],'fields'=>['usuario_id','administrador_id','motivo','desbloqueado_en']],
    ];
    if ($route === 'reports' && $method === 'GET') {
        $reportKey = $parts[1] ?? '';
        if (!isset($resources[$reportKey], $roles[$reportKey])) {
            respond(['error' => 'Modulo de reporte no valido.'], 404);
        }
        requireRoles($user, $roles[$reportKey]);
        $resource = $resources[$reportKey];
        $where = '';
        $params = [];
        if ($user['rol'] === 'estudiante') {
            if ($reportKey === 'schedules') {
                $where = ' WHERE ficha_id IN (SELECT ficha_id FROM fichas_usuarios WHERE usuario_id = ?)';
                $params[] = (int)$user['id'];
            } else {
                $where = ' WHERE usuario_id = ?';
                $params[] = (int)$user['id'];
            }
        } elseif ($user['rol'] === 'instructor') {
            if (in_array($reportKey, ['schedules','attendance'], true)) {
                $where = ' WHERE ficha_id IN (SELECT ficha_id FROM fichas_instructores WHERE instructor_id = ?)';
                $params[] = (int)$user['id'];
            } elseif ($reportKey === 'novelties') {
                $where = ' WHERE usuario_id IN (SELECT fu.usuario_id FROM fichas_usuarios fu
                    JOIN fichas_instructores fi ON fi.ficha_id = fu.ficha_id WHERE fi.instructor_id = ?)';
                $params[] = (int)$user['id'];
            }
        }
        $stmt = $db->prepare('SELECT ' . implode(',', $resource['columns']) . " FROM {$resource['table']}{$where} ORDER BY id DESC LIMIT 1000");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $format = strtolower($_GET['format'] ?? 'csv');
        $filename = preg_replace('/[^a-z0-9_-]/i', '', $reportKey) . '_' . date('Ymd_His');
        audit($db, (int)$user['id'], 'exportar_' . $format, $resource['table']);
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $resource['columns'], ';');
            foreach ($rows as $row) {
                fputcsv($output, array_map('safeSpreadsheetCell', array_values($row)), ';');
            }
            fclose($output);
            exit;
        }
        if ($format === 'xlsx' && class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $book->getActiveSheet();
            $safeRows = array_map(static fn($row) => array_map('safeSpreadsheetCell', array_values($row)), $rows);
            $sheet->fromArray([$resource['columns'], ...$safeRows], null, 'A1');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
            exit;
        }
        if ($format === 'pdf' && class_exists(\Dompdf\Dompdf::class)) {
            $html = '<meta charset="utf-8"><h2>Reporte institucional</h2><table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
            foreach ($resource['columns'] as $column) {
                $html .= '<th>' . htmlspecialchars((string)$column, ENT_QUOTES, 'UTF-8') . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($row as $value) {
                    $html .= '<td>' . htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $pdf = new \Dompdf\Dompdf();
            $pdf->loadHtml($html, 'UTF-8');
            $pdf->setPaper('A4', 'landscape');
            $pdf->render();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
            echo $pdf->output();
            exit;
        }
        respond(['error' => 'Formato no valido o dependencia no instalada. Use csv, xlsx o pdf.'], 422);
    }
    if (!isset($resources[$route])) {
        respond(['error' => 'Ruta API no encontrada.'], 404);
    }
    $actor = requireRoles($user, $roles[$route]);
    if ($route === 'environments' && $actor['rol'] === 'instructor' && $method !== 'GET') {
        respond(['error' => 'Los instructores pueden consultar ambientes, pero solo administración puede modificarlos.'], 403);
    }
    if ($actor['rol'] === 'estudiante' && !($route === 'schedules' && $method === 'GET') &&
        !($route === 'enrollments' && $method === 'GET') &&
        !($route === 'cards' && $method === 'GET') &&
        !($route === 'attendance' && $method === 'GET') &&
        !($route === 'notifications' && $method === 'GET')) {
        respond(['error' => 'Los estudiantes solo pueden consultar sus horarios, asistencias y notificaciones.'], 403);
    }
    if ($route === 'ficha-assignments' && $method !== 'GET' && $actor['rol'] !== 'administrador') {
        respond(['error' => 'Solo administración puede asignar instructores a fichas.'], 403);
    }
    if ($route === 'attendance' && $method === 'POST' && $actor['rol'] === 'instructor') {
        respond(['error' => 'Registra asistencia desde la lista de tus fichas asignadas.'], 403);
    }
    $resource = $resources[$route];
    $id = isset($parts[1]) && ctype_digit($parts[1]) ? (int)$parts[1] : null;

    if ($route === 'attendance' && $id !== null && in_array($method, ['PUT','PATCH'], true)) {
        $data = body();
        $unknown = array_diff(array_keys($data), ['estado','observacion']);
        if ($unknown) {
            respond(['error' => 'Solo puedes editar el estado y la observación de la asistencia.'], 422);
        }
        if (!$data || array_key_exists('estado', $data) && !in_array($data['estado'], ['presente','ausente','tarde','permiso'], true)) {
            respond(['error' => 'El estado de asistencia no es válido.'], 422);
        }
        if (isset($data['observacion']) && (!is_string($data['observacion']) || mb_strlen($data['observacion']) > 500)) {
            respond(['error' => 'La observación debe tener máximo 500 caracteres.'], 422);
        }
        $previousStmt = $db->prepare('SELECT usuario_id, ficha_id, horario_id, estado, fecha, observacion FROM asistencias WHERE id = ?');
        $previousStmt->execute([$id]);
        $previous = $previousStmt->fetch();
        if (!$previous) {
            respond(['error' => 'Registro no encontrado.'], 404);
        }
        if ($actor['rol'] === 'instructor') {
            $assignedFicha = $db->prepare('SELECT 1 FROM fichas_instructores WHERE ficha_id = ? AND instructor_id = ?');
            $assignedFicha->execute([(int)$previous['ficha_id'], (int)$actor['id']]);
            if (!$assignedFicha->fetchColumn()) {
                respond(['error' => 'Solo puedes modificar asistencias de tus fichas asignadas.'], 403);
            }
        }
        $db->beginTransaction();
        try {
            $fields = [];
            $values = [];
            foreach ($data as $field => $value) {
                $fields[] = "{$field} = ?";
                $values[] = $value === '' ? null : $value;
            }
            $values[] = $id;
            $stmt = $db->prepare('UPDATE asistencias SET ' . implode(', ', $fields) . ' WHERE id = ?');
            $stmt->execute($values);
            audit($db, (int)$actor['id'], 'actualizar', 'asistencias', $id);
            $newStatus = $data['estado'] ?? $previous['estado'];
            $newObservation = array_key_exists('observacion', $data) ? ($data['observacion'] === '' ? null : $data['observacion']) : $previous['observacion'];
            if ($previous['estado'] !== $newStatus || $previous['observacion'] !== $newObservation) {
                notifyAttendance($db, (int)$previous['usuario_id'], (int)$previous['ficha_id'],
                    $previous['horario_id'] === null ? null : (int)$previous['horario_id'],
                    $newStatus, $previous['fecha'], $newObservation);
            }
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        respond(['data' => ['updated' => true]]);
    }
    if ($route === 'attendance' && $actor['rol'] === 'estudiante') {
        $where = 'usuario_id = ?';
        $params = [(int)$actor['id']];
    } elseif ($route === 'attendance' && $actor['rol'] === 'instructor') {
        $where = 'ficha_id IN (SELECT ficha_id FROM fichas_instructores WHERE instructor_id = ?)';
        $params = [(int)$actor['id']];
    } elseif ($route === 'novelties' && $actor['rol'] === 'instructor') {
        $where = 'usuario_id IN (SELECT fu.usuario_id FROM fichas_usuarios fu
            JOIN fichas_instructores fi ON fi.ficha_id = fu.ficha_id WHERE fi.instructor_id = ?)';
        $params = [(int)$actor['id']];
    } elseif ($route === 'notifications') {
        $where = 'usuario_id = ?';
        $params = [(int)$actor['id']];
    } elseif ($route === 'enrollments' && $actor['rol'] === 'estudiante') {
        $where = 'usuario_id = ?';
        $params = [(int)$actor['id']];
    } elseif ($route === 'cards' && $actor['rol'] === 'estudiante') {
        $where = 'usuario_id = ?';
        $params = [(int)$actor['id']];
    } elseif ($route === 'schedules' && $actor['rol'] === 'estudiante') {
        $where = 'ficha_id IN (SELECT ficha_id FROM fichas_usuarios WHERE usuario_id = ?)';
        $params = [(int)$actor['id']];
    } else {
        $where = null;
        $params = [];
    }
    if ($route === 'attendance' && $method === 'GET') {
        foreach (['desde' => 'fecha >= ?', 'hasta' => 'fecha <= ?'] as $queryKey => $condition) {
            if (isset($_GET[$queryKey]) && $_GET[$queryKey] !== '') {
                $date = (string)$_GET[$queryKey];
                $timestamp = strtotime($date);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $timestamp === false || date('Y-m-d', $timestamp) !== $date) {
                    respond(['error' => "La fecha {$queryKey} debe usar YYYY-MM-DD."], 422);
                }
                $where = $where === null ? $condition : $where . ' AND ' . $condition;
                $params[] = $date;
            }
        }
    }

    if ($method === 'GET') {
        if ($route === 'fichas') {
            $fichaSql = "SELECT id, codigo, programa, fecha_inicio, fecha_fin,
                    CASE
                        WHEN fecha_inicio IS NOT NULL AND fecha_inicio > CURRENT_DATE() THEN 'Programada'
                        WHEN fecha_fin IS NOT NULL AND fecha_fin < CURRENT_DATE() THEN 'Terminada'
                        ELSE 'Activa'
                    END AS estado
                FROM fichas";
            if ($id !== null) {
                $stmt = $db->prepare($fichaSql . ' WHERE id = ? LIMIT 1');
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if (!$row) {
                    respond(['error' => 'Registro no encontrado.'], 404);
                }
                respond(['data' => $row]);
            }
            respond(['data' => $db->query($fichaSql . ' ORDER BY codigo LIMIT 200')->fetchAll()]);
        }
        if ($route === 'users') {
            $stmt = $db->query("SELECT u.id, u.rol_id, r.nombre AS rol, u.documento, u.nombres, u.apellidos,
                    u.correo, u.telefono, u.activo, u.ultimo_acceso, u.creado_en
                FROM usuarios u JOIN roles r ON r.id = u.rol_id
                ORDER BY r.id, u.apellidos, u.nombres, u.id DESC LIMIT 200");
            respond(['data' => $stmt->fetchAll()]);
        }
        if ($route === 'ficha-assignments') {
            $assignmentSql = "SELECT fi.id, fi.ficha_id, fi.instructor_id, f.codigo AS ficha_codigo,
                    f.nombre AS ficha_nombre, f.programa, f.trimestre,
                    CONCAT(i.nombres, ' ', i.apellidos) AS instructor";
            if ($actor['rol'] === 'instructor') {
                $assignmentSql .= ", u.id AS estudiante_id, COALESCE(u.documento, '') AS documento,
                    COALESCE(u.nombres, 'Sin aprendices inscritos') AS estudiante_nombres,
                    COALESCE(u.apellidos, '') AS estudiante_apellidos";
            } else {
                $assignmentSql .= ", COUNT(DISTINCT CASE WHEN ru.nombre = 'estudiante' AND u.activo = 1
                    THEN u.id END) AS aprendices";
            }
            $learnerJoin = $actor['rol'] === 'instructor'
                ? "LEFT JOIN usuarios u ON u.id = fu.usuario_id AND u.activo = 1
                    AND EXISTS (SELECT 1 FROM roles student_role WHERE student_role.id = u.rol_id AND student_role.nombre = 'estudiante')"
                : 'LEFT JOIN usuarios u ON u.id = fu.usuario_id';
            $assignmentSql .= " FROM fichas_instructores fi
                JOIN fichas f ON f.id = fi.ficha_id
                JOIN usuarios i ON i.id = fi.instructor_id
                JOIN roles ri ON ri.id = i.rol_id AND ri.nombre = 'instructor'
                LEFT JOIN fichas_usuarios fu ON fu.ficha_id = fi.ficha_id
                {$learnerJoin}";
            if ($actor['rol'] === 'instructor') {
                $assignmentSql .= " WHERE fi.instructor_id = ?
                    ORDER BY f.codigo, u.apellidos, u.nombres";
                $stmt = $db->prepare($assignmentSql);
                $stmt->execute([(int)$actor['id']]);
            } else {
                $assignmentSql .= " LEFT JOIN roles ru ON ru.id = u.rol_id
                    GROUP BY fi.id, fi.ficha_id, fi.instructor_id, f.codigo, f.nombre, f.programa, f.trimestre,
                        i.nombres, i.apellidos
                    ORDER BY f.codigo, i.apellidos, i.nombres";
                $stmt = $db->query($assignmentSql);
            }
            respond(['data' => $stmt->fetchAll()]);
        }
        if ($route === 'schedules') {
            $scheduleSql = "SELECT h.id, h.ficha_id, h.ambiente_id, h.instructor_id, h.dia_semana,
                    CASE h.dia_semana WHEN 1 THEN 'Lunes' WHEN 2 THEN 'Martes' WHEN 3 THEN 'Miércoles'
                        WHEN 4 THEN 'Jueves' WHEN 5 THEN 'Viernes' WHEN 6 THEN 'Sábado' WHEN 7 THEN 'Domingo' END AS dia,
                    h.hora_inicio, h.hora_fin, h.asignatura, f.codigo AS ficha_codigo,
                    f.nombre AS ficha_nombre, f.programa, f.trimestre, a.codigo AS ambiente_codigo,
                    a.nombre AS ambiente, CONCAT(u.nombres, ' ', u.apellidos) AS instructor
                FROM horarios h JOIN fichas f ON f.id = h.ficha_id
                JOIN ambientes a ON a.id = h.ambiente_id
                JOIN usuarios u ON u.id = h.instructor_id";
            if ($actor['rol'] === 'estudiante') {
                $stmt = $db->prepare($scheduleSql . ' WHERE h.ficha_id IN (SELECT ficha_id FROM fichas_usuarios WHERE usuario_id = ?) ORDER BY f.codigo, h.dia_semana, h.hora_inicio');
                $stmt->execute([(int)$actor['id']]);
                $rows = $stmt->fetchAll();
            } else {
                $stmt = $db->query($scheduleSql . ' ORDER BY f.codigo, h.dia_semana, h.hora_inicio');
                $rows = $stmt->fetchAll();
            }
            respond(['data' => $rows]);
        }
        if ($route === 'enrollments') {
            $sql = "SELECT fu.id, fu.ficha_id, fu.usuario_id, fu.inscrito_en,
                    f.codigo AS ficha_codigo, f.nombre AS ficha_nombre, f.programa, f.trimestre,
                    u.documento, CONCAT(u.nombres, ' ', u.apellidos) AS estudiante
                FROM fichas_usuarios fu JOIN fichas f ON f.id = fu.ficha_id
                JOIN usuarios u ON u.id = fu.usuario_id";
            if ($actor['rol'] === 'estudiante') {
                $stmt = $db->prepare($sql . ' WHERE fu.usuario_id = ? ORDER BY f.codigo, u.apellidos, u.nombres');
                $stmt->execute([(int)$actor['id']]);
                $rows = $stmt->fetchAll();
            } else {
                $stmt = $db->query($sql . ' ORDER BY f.codigo, u.apellidos, u.nombres');
                $rows = $stmt->fetchAll();
            }
            respond(['data' => $rows]);
        }
        if ($route === 'attendance' && $actor['rol'] === 'estudiante') {
            $stmt = $db->prepare("SELECT a.id, a.fecha, a.estado, a.observacion, a.registrado_en,
                    h.asignatura, h.hora_inicio, h.hora_fin,
                    amb.nombre AS ambiente, CONCAT(i.nombres, ' ', i.apellidos) AS instructor,
                    f.codigo AS ficha, f.nombre AS nombre_ficha, f.programa, f.trimestre
                FROM asistencias a JOIN fichas f ON f.id = a.ficha_id
                LEFT JOIN horarios h ON h.id = a.horario_id
                LEFT JOIN ambientes amb ON amb.id = h.ambiente_id
                LEFT JOIN usuarios i ON i.id = h.instructor_id
                WHERE a.usuario_id = ? ORDER BY a.fecha DESC, h.hora_inicio DESC LIMIT 500");
            $stmt->execute([(int)$actor['id']]);
            respond(['data' => $stmt->fetchAll()]);
        }
        sendRows($db, $resource['table'], $resource['columns'], $id, $where, $params);
    }
    if ($method === 'POST' && $id === null) {
        $data = body();
        if ($route === 'fichas') {
            validateFichaInput($data);
            $existingCode = $db->prepare('SELECT 1 FROM fichas WHERE codigo = ?');
            $existingCode->execute([$data['codigo']]);
            if ($existingCode->fetchColumn()) {
                respond(['error' => 'Ya existe una ficha con ese número.'], 409);
            }
        }
        if ($route === 'ficha-assignments') {
            foreach (['ficha_id','instructor_id'] as $field) {
                if (!isset($data[$field]) || !ctype_digit((string)$data[$field]) || (int)$data[$field] < 1) {
                    respond(['error' => 'Selecciona una ficha y un instructor válidos.'], 422);
                }
            }
            $validFicha = $db->prepare("SELECT 1 FROM fichas
                WHERE id = ? AND fecha_inicio <= CURRENT_DATE() AND fecha_fin >= CURRENT_DATE()");
            $validFicha->execute([(int)$data['ficha_id']]);
            $validInstructor = $db->prepare("SELECT 1 FROM usuarios u JOIN roles r ON r.id = u.rol_id
                WHERE u.id = ? AND u.activo = 1 AND r.nombre = 'instructor'");
            $validInstructor->execute([(int)$data['instructor_id']]);
            if (!$validFicha->fetchColumn() || !$validInstructor->fetchColumn()) {
                respond(['error' => 'Solo puedes asignar instructores activos a fichas activas.'], 422);
            }
            $existingAssignment = $db->prepare('SELECT 1 FROM fichas_instructores WHERE ficha_id = ? AND instructor_id = ?');
            $existingAssignment->execute([(int)$data['ficha_id'], (int)$data['instructor_id']]);
            if ($existingAssignment->fetchColumn()) {
                respond(['error' => 'Ese instructor ya está asignado a la ficha seleccionada.'], 409);
            }
        }
        if ($route === 'schedules' && $actor['rol'] === 'instructor') {
            if (!isset($data['ficha_id']) || !ctype_digit((string)$data['ficha_id'])) {
                respond(['error' => 'Selecciona una de tus fichas asignadas.'], 422);
            }
            $assignedFicha = $db->prepare('SELECT 1 FROM fichas_instructores WHERE ficha_id = ? AND instructor_id = ?');
            $assignedFicha->execute([(int)$data['ficha_id'], (int)$actor['id']]);
            if (!$assignedFicha->fetchColumn()) {
                respond(['error' => 'Solo puedes gestionar horarios de tus fichas asignadas.'], 403);
            }
        }
        if ($route === 'users' && (!isset($data['password']) || !isset($data['rol_id']))) {
            respond(['error' => 'Se requieren rol_id y password al crear un usuario.'], 422);
        }
        if (in_array($route, ['accesses','equipment-movements','loans','temporary-permits','blocks'], true)) {
            if (in_array($route, ['accesses','equipment-movements'], true)) {
                $data['registrado_por'] = $actor['id'];
            }
            if ($route === 'loans') {
                $data['entregado_por'] = $actor['id'];
            }
            if ($route === 'temporary-permits') {
                $data['autorizado_por'] = $actor['id'];
            }
            if ($route === 'blocks') {
                $data['administrador_id'] = $actor['id'];
            }
        }
        if ($route === 'temporary-permits' &&
            (empty($data['usuario_id']) === empty($data['visitante_id']))) {
            respond(['error' => 'El permiso debe asociarse a un usuario o a un visitante, no a ambos.'], 422);
        }
        if ($route === 'visitors') {
            $data['registrado_por'] = $actor['id'];
        }
        if ($route === 'attendance' || $route === 'novelties') {
            $data['registrado_por'] = $actor['id'];
        }
        if ($route === 'novelties' && $actor['rol'] === 'instructor') {
            $studentAssignment = $db->prepare('SELECT 1 FROM fichas_usuarios fu
                JOIN fichas_instructores fi ON fi.ficha_id = fu.ficha_id
                WHERE fu.usuario_id = ? AND fi.instructor_id = ?');
            $studentAssignment->execute([(int)($data['usuario_id'] ?? 0), (int)$actor['id']]);
            if (!$studentAssignment->fetchColumn()) {
                respond(['error' => 'Solo puedes registrar novedades de aprendices de tus fichas asignadas.'], 403);
            }
        }
        saveResource($db, $resource['table'], $resource['fields'], $data, null, $actor);
    }
    if (in_array($method, ['PUT','PATCH'], true) && $id !== null) {
        $data = body();
        if ($route === 'fichas') {
            $existingFicha = $db->prepare('SELECT codigo, programa, fecha_inicio, fecha_fin FROM fichas WHERE id = ?');
            $existingFicha->execute([$id]);
            $currentFicha = $existingFicha->fetch();
            if (!$currentFicha) {
                respond(['error' => 'Registro no encontrado.'], 404);
            }
            validateFichaInput($data, $currentFicha);
            if (isset($data['codigo'])) {
                $duplicateCode = $db->prepare('SELECT 1 FROM fichas WHERE codigo = ? AND id <> ?');
                $duplicateCode->execute([$data['codigo'], $id]);
                if ($duplicateCode->fetchColumn()) {
                    respond(['error' => 'Ya existe otra ficha con ese número.'], 409);
                }
            }
        }
        if ($route === 'ficha-assignments') {
            $existingAssignment = $db->prepare('SELECT ficha_id, instructor_id FROM fichas_instructores WHERE id = ?');
            $existingAssignment->execute([$id]);
            $currentAssignment = $existingAssignment->fetch();
            if (!$currentAssignment) {
                respond(['error' => 'La asignación no existe.'], 404);
            }
            $assignment = array_merge($currentAssignment, $data);
            foreach (['ficha_id','instructor_id'] as $field) {
                if (!ctype_digit((string)$assignment[$field]) || (int)$assignment[$field] < 1) {
                    respond(['error' => 'Selecciona una ficha y un instructor válidos.'], 422);
                }
            }
            $validFicha = $db->prepare("SELECT 1 FROM fichas
                WHERE id = ? AND fecha_inicio <= CURRENT_DATE() AND fecha_fin >= CURRENT_DATE()");
            $validFicha->execute([(int)$assignment['ficha_id']]);
            $validInstructor = $db->prepare("SELECT 1 FROM usuarios u JOIN roles r ON r.id = u.rol_id
                WHERE u.id = ? AND u.activo = 1 AND r.nombre = 'instructor'");
            $validInstructor->execute([(int)$assignment['instructor_id']]);
            if (!$validFicha->fetchColumn() || !$validInstructor->fetchColumn()) {
                respond(['error' => 'Solo puedes asignar instructores activos a fichas activas.'], 422);
            }
            $duplicateAssignment = $db->prepare('SELECT 1 FROM fichas_instructores WHERE ficha_id = ? AND instructor_id = ? AND id <> ?');
            $duplicateAssignment->execute([(int)$assignment['ficha_id'], (int)$assignment['instructor_id'], $id]);
            if ($duplicateAssignment->fetchColumn()) {
                respond(['error' => 'Ese instructor ya está asignado a la ficha seleccionada.'], 409);
            }
        }
        if ($route === 'schedules' && $actor['rol'] === 'instructor') {
            $existingSchedule = $db->prepare('SELECT ficha_id FROM horarios WHERE id = ?');
            $existingSchedule->execute([$id]);
            $scheduleFicha = $existingSchedule->fetchColumn();
            $targetFichaId = (int)($data['ficha_id'] ?? $scheduleFicha);
            $assignedFicha = $db->prepare('SELECT 1 FROM fichas_instructores WHERE ficha_id = ? AND instructor_id = ?');
            $assignedFicha->execute([$targetFichaId, (int)$actor['id']]);
            if (!$scheduleFicha || !$assignedFicha->fetchColumn()) {
                respond(['error' => 'Solo puedes gestionar horarios de tus fichas asignadas.'], 403);
            }
        }
        if ($route === 'novelties' && $actor['rol'] === 'instructor') {
            $existingNovelty = $db->prepare('SELECT usuario_id FROM novedades WHERE id = ?');
            $existingNovelty->execute([$id]);
            $studentId = (int)($data['usuario_id'] ?? $existingNovelty->fetchColumn());
            $studentAssignment = $db->prepare('SELECT 1 FROM fichas_usuarios fu
                JOIN fichas_instructores fi ON fi.ficha_id = fu.ficha_id
                WHERE fu.usuario_id = ? AND fi.instructor_id = ?');
            $studentAssignment->execute([$studentId, (int)$actor['id']]);
            if ($studentId < 1 || !$studentAssignment->fetchColumn()) {
                respond(['error' => 'Solo puedes modificar novedades de aprendices de tus fichas asignadas.'], 403);
            }
        }
        if ($route === 'attendance' && $actor['rol'] === 'estudiante') {
            respond(['error' => 'No puede modificar asistencias.'], 403);
        }
        saveResource($db, $resource['table'], $resource['fields'], $data, $id, $actor);
    }
    if ($method === 'DELETE' && $id !== null &&
        ($actor['rol'] === 'administrador' ||
            $actor['rol'] === 'instructor' && in_array($route, ['attendance','schedules'], true))) {
        if ($route === 'blocks') {
            respond(['error' => 'El historial de bloqueos se conserva; use la acción Desbloquear.'], 409);
        }
        $db->beginTransaction();
        try {
            $removalNotice = null;
            if ($route === 'attendance') {
                $attendance = $db->prepare('SELECT usuario_id, ficha_id, horario_id, fecha, estado, observacion FROM asistencias WHERE id = ?');
                $attendance->execute([$id]);
                $removalNotice = $attendance->fetch();
                if ($actor['rol'] === 'instructor' && $removalNotice) {
                    $assignedFicha = $db->prepare('SELECT 1 FROM fichas_instructores WHERE ficha_id = ? AND instructor_id = ?');
                    $assignedFicha->execute([(int)$removalNotice['ficha_id'], (int)$actor['id']]);
                    if (!$assignedFicha->fetchColumn()) {
                        $db->rollBack();
                        respond(['error' => 'Solo puedes eliminar asistencias de tus fichas asignadas.'], 403);
                    }
                }
            } elseif ($route === 'enrollments') {
                $enrollment = $db->prepare("SELECT fu.ficha_id, fu.usuario_id, f.codigo, f.nombre,
                        CONCAT(u.nombres, ' ', u.apellidos) AS estudiante
                    FROM fichas_usuarios fu JOIN fichas f ON f.id = fu.ficha_id
                    JOIN usuarios u ON u.id = fu.usuario_id WHERE fu.id = ?");
                $enrollment->execute([$id]);
                $record = $enrollment->fetch();
                if ($record) {
                    $staff = $db->prepare("SELECT u.id FROM usuarios u JOIN roles r ON r.id = u.rol_id
                        WHERE u.activo = 1 AND (
                            r.nombre = 'administrador'
                            OR r.nombre = 'instructor' AND EXISTS (
                                SELECT 1 FROM fichas_instructores fi
                                WHERE fi.ficha_id = ? AND fi.instructor_id = u.id
                            )
                        )");
                    $staff->execute([(int)$record['ficha_id']]);
                    $removalNotice = [
                        'recipients' => array_merge($staff->fetchAll(PDO::FETCH_COLUMN), [(int)$record['usuario_id']]),
                        'title' => 'Actualización de asignación de ficha',
                        'message' => $record['estudiante'] . ' ya no está asignado a la ficha '
                            . $record['codigo'] . ' · ' . $record['nombre'],
                    ];
                }
            } elseif ($route === 'schedules') {
                $schedule = $db->prepare("SELECT h.ficha_id, h.asignatura, h.dia_semana, h.hora_inicio, h.hora_fin,
                        f.codigo, f.nombre, a.nombre AS ambiente,
                        CONCAT(u.nombres, ' ', u.apellidos) AS instructor
                    FROM horarios h JOIN fichas f ON f.id = h.ficha_id
                    JOIN ambientes a ON a.id = h.ambiente_id
                    JOIN usuarios u ON u.id = h.instructor_id WHERE h.id = ?");
                $schedule->execute([$id]);
                $record = $schedule->fetch();
                if ($record) {
                    if ($actor['rol'] === 'instructor') {
                        $assignedFicha = $db->prepare('SELECT 1 FROM fichas_instructores WHERE ficha_id = ? AND instructor_id = ?');
                        $assignedFicha->execute([(int)$record['ficha_id'], (int)$actor['id']]);
                        if (!$assignedFicha->fetchColumn()) {
                            $db->rollBack();
                            respond(['error' => 'Solo puedes eliminar horarios de tus fichas asignadas.'], 403);
                        }
                    }
                    $days = [1=>'lunes',2=>'martes',3=>'miércoles',4=>'jueves',5=>'viernes',6=>'sábado',7=>'domingo'];
                    $removalNotice = [
                        'recipients' => formationRecipientIds($db, (int)$record['ficha_id']),
                        'title' => 'Horario eliminado',
                        'message' => 'Se eliminó la asignatura ' . $record['asignatura']
                            . "\nDía: " . ($days[(int)$record['dia_semana']] ?? 'Sin día')
                            . "\nHorario: " . substr($record['hora_inicio'], 0, 5) . ' a ' . substr($record['hora_fin'], 0, 5)
                            . "\nAmbiente: " . $record['ambiente']
                            . "\nInstructor: " . $record['instructor']
                            . "\nFicha: " . $record['codigo'] . ' · ' . $record['nombre'],
                    ];
                }
            }
            if ($route === 'schedules') {
                $attendanceCount = $db->prepare('SELECT COUNT(*) FROM asistencias WHERE horario_id = ?');
                $attendanceCount->execute([$id]);
                if ((int)$attendanceCount->fetchColumn() > 0) {
                    $db->rollBack();
                    respond(['error' => 'No se puede eliminar un horario con asistencias registradas; edítalo para conservar el historial.'], 409);
                }
            }
            if ($route === 'fichas') {
                $db->prepare('DELETE n FROM novedades n
                    JOIN asistencias a ON a.id = n.asistencia_id
                    WHERE a.ficha_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM asistencias WHERE ficha_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM horarios WHERE ficha_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM fichas_usuarios WHERE ficha_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM fichas_instructores WHERE ficha_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM solicitudes_ficha WHERE ficha_id = ?')->execute([$id]);
            }
            $stmt = $db->prepare("DELETE FROM {$resource['table']} WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->rowCount()) {
                $db->rollBack();
                respond(['error' => 'Registro no encontrado.'], 404);
            }
            audit($db, (int)$actor['id'], 'eliminar', $resource['table'], $id);
            if ($route === 'attendance' && $removalNotice) {
                notifyAttendance(
                    $db,
                    (int)$removalNotice['usuario_id'],
                    (int)$removalNotice['ficha_id'],
                    $removalNotice['horario_id'] === null ? null : (int)$removalNotice['horario_id'],
                    'eliminada (antes: ' . $removalNotice['estado'] . ')',
                    (string)$removalNotice['fecha'],
                    $removalNotice['observacion'] === null ? 'Se eliminó este registro de asistencia.' : (string)$removalNotice['observacion']
                );
            } elseif ($removalNotice && isset($removalNotice['recipients'])) {
                notifyUsers($db, $removalNotice['recipients'], $removalNotice['title'], $removalNotice['message']);
            }
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        respond(['data' => ['deleted' => true]]);
    }
    respond(['error' => 'Metodo no permitido para esta ruta.'], 405);
} catch (PDOException $exception) {
    error_log('[gestion-institucional] Database error: ' . $exception->getMessage());
    $status = $exception->getCode() === '23000' ? 409 : 500;
    respond(['error' => $status === 409 ? 'Conflicto de datos o registro duplicado.' : 'Error interno de base de datos.'], $status);
} catch (Throwable $exception) {
    error_log('[gestion-institucional] Error: ' . $exception->getMessage());
    respond(['error' => 'Error interno del servidor.'], 500);
}
