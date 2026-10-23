<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/attendance_automation.php';

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

function notifyUsers(
    PDO $db,
    array $userIds,
    string $title,
    string $message,
    ?string $type = null,
    ?int $senderId = null,
    ?string $eventKey = null
): void
{
    $insert = $eventKey === null
        ? $db->prepare('INSERT INTO notificaciones (usuario_id, titulo, tipo, remitente_id, mensaje) VALUES (?, ?, ?, ?, ?)')
        : $db->prepare('INSERT INTO notificaciones (usuario_id, titulo, tipo, remitente_id, mensaje, evento_clave)
            VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id = id');
    foreach (array_unique(array_map('intval', $userIds)) as $userId) {
        if ($userId > 0) {
            $params = [$userId, $title, $type, $senderId, $message];
            if ($eventKey !== null) {
                $params[] = $eventKey;
            }
            $insert->execute($params);
        }
    }
}

function systemSettings(PDO $db): array
{
    $rows = $db->query('SELECT clave, valor FROM configuracion_sistema')->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['clave']] = (int)$row['valor'];
    }
    return $settings;
}

function databaseDate(PDO $db): string
{
    return (string)$db->query('SELECT CURRENT_DATE()')->fetchColumn();
}

function databaseDateTime(PDO $db): string
{
    return (string)$db->query('SELECT CURRENT_TIMESTAMP()')->fetchColumn();
}

function configuredSystemSetting(PDO $db, string $key): int
{
    $settings = systemSettings($db);
    if (!isset($settings[$key]) || $settings[$key] < 1) {
        throw new RuntimeException("Falta configurar el valor {$key} en configuracion_sistema.");
    }
    return $settings[$key];
}

function requireAttendanceCorrectionWindow(PDO $db, int $attendanceId, array $actor): void
{
    if ($actor['rol'] === 'administrador') {
        return;
    }

    $elapsedStmt = $db->prepare('SELECT TIMESTAMPDIFF(SECOND, registrado_en, CURRENT_TIMESTAMP)
        FROM asistencias WHERE id = ?');
    $elapsedStmt->execute([$attendanceId]);
    $elapsedSeconds = $elapsedStmt->fetchColumn();
    $hours = systemSettings($db)['horas_correccion_asistencia'] ?? 48;
    if ($elapsedSeconds === false || (int)$elapsedSeconds < 0 || (int)$elapsedSeconds > $hours * 3600) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        respond(['error' => "El plazo de {$hours} horas para corregir esta asistencia ya venció. Solicita la corrección a administración."], 409);
    }
}

function createAttendanceCorrectionRequest(
    PDO $db,
    array $actor,
    int $attendanceId,
    array $previous,
    string $proposedStatus,
    ?string $proposedObservation,
    string $reason,
    ?int $scheduleId
): int {
    $db->prepare("INSERT INTO solicitudes_correccion_asistencia
        (asistencia_id, estado_anterior, observacion_anterior, estado_propuesto,
         observacion_propuesta, motivo, solicitada_por)
        VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([$attendanceId, $previous['estado'], $previous['observacion'], $proposedStatus,
            $proposedObservation, $reason, $actor['id']]);
    $requestId = (int)$db->lastInsertId();
    audit($db, (int)$actor['id'], 'solicitar_correccion_asistencia', 'asistencias', $attendanceId, [
        'solicitud_id'=>$requestId,
        'estado_anterior'=>$previous['estado'],
        'estado_propuesto'=>$proposedStatus,
        'observacion_anterior'=>$previous['observacion'],
        'observacion_propuesta'=>$proposedObservation,
        'motivo_correccion'=>$reason,
    ]);
    $reviewers = $db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id = u.rol_id
        WHERE u.activo = 1 AND r.nombre IN ('administrador','coordinador')")->fetchAll(PDO::FETCH_COLUMN);
    $instructorNameStmt = $db->prepare("SELECT CONCAT(nombres, ' ', apellidos) FROM usuarios WHERE id = ?");
    $instructorNameStmt->execute([(int)$actor['id']]);
    $session = 'registro general sin sesión';
    if ($scheduleId !== null) {
        $sessionStmt = $db->prepare('SELECT asignatura, hora_inicio, hora_fin FROM horarios WHERE id = ?');
        $sessionStmt->execute([$scheduleId]);
        $schedule = $sessionStmt->fetch();
        if ($schedule) {
            $session = $schedule['asignatura'] . ' de ' . substr($schedule['hora_inicio'], 0, 5) .
                ' a ' . substr($schedule['hora_fin'], 0, 5);
        }
    }
    notifyUsers($db, array_map('intval', $reviewers), 'Corrección de asistencia pendiente',
        'Quién: ' . $instructorNameStmt->fetchColumn() . '. Qué: solicitó corregir la asistencia #' .
            $attendanceId . '. Fecha: ' . $previous['fecha'] . '. Sesión: ' . $session .
            '. Motivo: ' . $reason,
        'asistencia', (int)$actor['id']);
    return $requestId;
}

function validateAbsenceExcuse(PDO $db, array &$data, array $actor, ?array $current = null): void
{
    $effective = array_merge($current ?? [], $data);
    $isExcuse = ($effective['tipo'] ?? null) === 'inasistencia';
    if (!$isExcuse && empty($effective['asistencia_id'])) {
        return;
    }

    $identityFields = ['usuario_id','asistencia_id','tipo','fecha'];
    $identityChanged = $current === null;
    foreach ($identityFields as $field) {
        if (array_key_exists($field, $data) && (string)$data[$field] !== (string)($current[$field] ?? '')) {
            $identityChanged = true;
        }
    }
    if (!$identityChanged) {
        return;
    }

    $attendanceId = $effective['asistencia_id'] ?? null;
    $studentId = $effective['usuario_id'] ?? null;
    $date = (string)($effective['fecha'] ?? '');
    if (!ctype_digit((string)$attendanceId) || (int)$attendanceId < 1 ||
        !ctype_digit((string)$studentId) || (int)$studentId < 1) {
        respond(['error' => 'Para registrar una excusa selecciona la asistencia ausente y el aprendiz.'], 422);
    }

    $attendanceStmt = $db->prepare('SELECT id, usuario_id, ficha_id, fecha, estado
        FROM asistencias WHERE id = ?');
    $attendanceStmt->execute([(int)$attendanceId]);
    $attendance = $attendanceStmt->fetch();
    if (!$attendance || (int)$attendance['usuario_id'] !== (int)$studentId ||
        $attendance['fecha'] !== $date) {
        respond(['error' => 'La asistencia seleccionada no corresponde al aprendiz y la fecha de la excusa.'], 422);
    }
    if ($attendance['estado'] !== 'ausente') {
        if ($isExcuse) {
            respond(['error' => 'Solo se puede presentar una excusa para una asistencia marcada como ausente.'], 409);
        }
    }
    if ($actor['rol'] === 'instructor') {
        $assignedFicha = $db->prepare('SELECT 1 FROM fichas_instructores WHERE ficha_id = ? AND instructor_id = ?');
        $assignedFicha->execute([(int)$attendance['ficha_id'], (int)$actor['id']]);
        if (!$assignedFicha->fetchColumn()) {
            respond(['error' => 'Solo puedes registrar excusas de tus fichas asignadas.'], 403);
        }
    }

    if ($isExcuse) {
        $elapsedBusinessDays = businessDaysSince($db, (string)$attendance['fecha']);
        $deadlineDays = configuredSystemSetting($db, 'dias_habiles_excusa');
        if ($elapsedBusinessDays === null || $elapsedBusinessDays > $deadlineDays) {
            respond(['error' => "El plazo de {$deadlineDays} días hábiles para presentar esta excusa ya venció."], 409);
        }
        $data['asistencia_id'] = (int)$attendance['id'];
        $data['usuario_id'] = (int)$attendance['usuario_id'];
        $data['fecha'] = (string)$attendance['fecha'];
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

function notifyAttendance(
    PDO $db,
    int $studentId,
    int $fichaId,
    ?int $scheduleId,
    string $status,
    string $date,
    ?string $observation,
    ?int $senderId = null,
    string $eventAction = 'registró la asistencia'
): void
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
    $actorName = 'Sistema';
    if ($senderId !== null) {
        $actor = $db->prepare("SELECT CONCAT(nombres, ' ', apellidos) FROM usuarios WHERE id = ?");
        $actor->execute([$senderId]);
        $actorName = (string)($actor->fetchColumn() ?: 'Personal institucional');
    }
    $statusLabels = ['presente'=>'asistió','ausente'=>'faltó','tarde'=>'llegó tarde','permiso'=>'quedó con permiso'];
    $session = $context['asignatura'] !== null
        ? $context['asignatura'] . ' · ' . substr((string)$context['hora_inicio'], 0, 5) . '–' .
            substr((string)$context['hora_fin'], 0, 5) . ' · ' . ($context['ambiente'] ?: 'ambiente sin asignar')
        : 'sin sesión específica';
    $message = 'Quién: ' . $actorName . '. Qué: ' . $eventAction . '. Aprendiz: ' .
        $context['estudiante'] . '. Estado de asistencia: ' . ($statusLabels[$status] ?? $status) . '. Fecha: ' . $date .
        '. Sesión: ' . $session . '. Ficha: ' . $context['codigo'] . '. Instructor: ' .
        ($context['instructor'] ?: 'sin instructor asignado') . '. Observación: ' .
        ($observation !== null && trim($observation) !== '' ? trim($observation) : 'sin observación adicional') . '.';
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
    notifyUsers($db, array_merge([$studentId], $staff->fetchAll(PDO::FETCH_COLUMN)),
        'Actualización de asistencia · ' . $context['estudiante'], $message, 'asistencia', $senderId);
}

function notifyFormationChange(PDO $db, string $table, int $recordId, int $actorId, string $action): void
{
    $actorStmt = $db->prepare("SELECT CONCAT(nombres, ' ', apellidos) FROM usuarios WHERE id = ?");
    $actorStmt->execute([$actorId]);
    $actorName = (string)($actorStmt->fetchColumn() ?: 'Personal institucional');
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
        notifyUsers($db, formationRecipientIds($db, (int)$ficha['id']), 'Actualización de ficha y programa',
            'Quién: ' . $actorName . '. Qué: ' . $action . ' la ficha. Fecha: ' . date('Y-m-d') .
                ". Sesión: no aplica.\n" . $message, 'formacion', $actorId);
        return;
    }
    if ($table === 'horarios') {
        $stmt = $db->prepare("SELECT h.ficha_id, h.asignatura, h.dia_semana, h.hora_inicio, h.hora_fin,
                c.nombre AS competencia,
                f.codigo, f.nombre, f.programa, f.trimestre, a.nombre AS ambiente,
                CONCAT(u.nombres, ' ', u.apellidos) AS instructor
            FROM horarios h JOIN fichas f ON f.id = h.ficha_id
            LEFT JOIN competencias c ON c.id = h.competencia_id
            JOIN ambientes a ON a.id = h.ambiente_id
            JOIN usuarios u ON u.id = h.instructor_id
            WHERE h.id = ?");
        $stmt->execute([$recordId]);
        $schedule = $stmt->fetch();
        if (!$schedule) {
            return;
        }
        $days = [1=>'lunes',2=>'martes',3=>'miércoles',4=>'jueves',5=>'viernes',6=>'sábado',7=>'domingo'];
        $message = 'Quién: ' . $actorName . '. Qué: ' . $action . ' el horario el ' . date('Y-m-d') .
            '. Sesión: ' . $schedule['asignatura'] . ', ' . ($days[(int)$schedule['dia_semana']] ?? 'Sin día') .
            ' de ' . substr($schedule['hora_inicio'], 0, 5) . ' a ' . substr($schedule['hora_fin'], 0, 5) . '.'
            . "\nAsignatura: " . $schedule['asignatura']
            . "\nCompetencia: " . ($schedule['competencia'] ?: 'Sin competencia')
            . "\nDía: " . ($days[(int)$schedule['dia_semana']] ?? 'Sin día')
            . "\nHorario: " . substr($schedule['hora_inicio'], 0, 5) . ' a ' . substr($schedule['hora_fin'], 0, 5)
            . "\nAmbiente: " . $schedule['ambiente']
            . "\nInstructor: " . $schedule['instructor']
            . "\nFicha: " . $schedule['codigo'] . ' · ' . $schedule['nombre']
            . "\nPrograma: " . ($schedule['programa'] ?: 'Sin programa')
            . "\nTrimestre: " . ($schedule['trimestre'] ?: 'Sin trimestre');
        notifyUsers($db, formationRecipientIds($db, (int)$schedule['ficha_id']),
            'Actualización de horario · ' . $schedule['asignatura'], $message, 'formacion', $actorId);
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
        WHERE c.codigo_qr = ? AND c.estado = \'activo\'
          AND (c.vence_en IS NULL OR c.vence_en >= CURDATE())
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

function ensureDailyEntryForCard(PDO $db, string $code): void
{
    if ($code === '' || strlen($code) > 100) {
        return;
    }
    $db->prepare("INSERT IGNORE INTO ingresos_diarios (usuario_id,fecha,token_hash)
        SELECT c.usuario_id,CURRENT_DATE(),? FROM carnets c WHERE c.codigo_qr=?")
        ->execute([hash('sha256', uuid4()), $code]);
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

function isPositiveIntegerValue($value): bool
{
    return (is_int($value) && $value > 0) ||
        (is_string($value) && ctype_digit($value) && (int)$value > 0);
}

function validateFichaInput(PDO $db, array &$data, ?array $current = null): void
{
    $allowed = ['codigo','programa','programa_id','fecha_inicio','fecha_fin','jornada'];
    $unknown = array_diff(array_keys($data), $allowed);
    if ($unknown) {
        respond(['error' => 'Campos no permitidos para una ficha: ' . implode(', ', $unknown)], 422);
    }
    $values = array_merge($current ?? [], $data);
    if ($current === null && array_diff(['codigo','fecha_inicio','fecha_fin'], array_keys($data))) {
        respond(['error' => 'Completa número, programa y fechas de la ficha.'], 422);
    }
    if (array_key_exists('codigo', $data) &&
        (!is_string($data['codigo']) || !preg_match('/^\d{7}$/', $data['codigo']))) {
        respond(['error' => 'El número de ficha debe tener exactamente 7 dígitos numéricos.'], 422);
    }
    if (array_key_exists('jornada', $data) &&
        !in_array($data['jornada'], ['diurna','mañana','tarde','noche','mixta'], true)) {
        respond(['error' => 'Selecciona una jornada válida para la ficha.'], 422);
    }
    $programId = $data['programa_id'] ?? $values['programa_id'] ?? null;
    if (array_key_exists('programa', $data)) {
        if (!is_string($data['programa']) || trim($data['programa']) === '' || mb_strlen(trim($data['programa'])) > 150) {
            respond(['error' => 'Escribe el nombre del programa (máximo 150 caracteres).'], 422);
        }
        if (!array_key_exists('programa_id', $data)) {
            $legacyProgram = $db->prepare("SELECT id FROM programas
                WHERE nombre = ? AND estado = 'activo' LIMIT 1");
            $legacyProgram->execute([trim($data['programa'])]);
            $programId = $legacyProgram->fetchColumn();
        }
    }
    if (!isPositiveIntegerValue($programId)) {
        respond(['error' => 'Selecciona un programa académico válido y activo para la ficha.'], 422);
    }
    $programStmt = $db->prepare('SELECT id, nombre, estado FROM programas WHERE id = ?');
    $programStmt->execute([(int)$programId]);
    $program = $programStmt->fetch();
    $sameProgram = $current !== null && (int)($current['programa_id'] ?? 0) === (int)$programId;
    if (!$program || ($program['estado'] !== 'activo' && !$sameProgram)) {
        respond(['error' => 'El programa seleccionado no existe o está inactivo.'], 422);
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
    if ($current === null || array_key_exists('programa_id', $data) || array_key_exists('programa', $data)) {
        $data['programa_id'] = (int)$programId;
        $data['programa'] = $program['nombre'];
        $data['nombre'] = $program['nombre'];
    }
    if ($current === null) {
        $data['trimestre'] = null;
    }
}

function validateAcademicResource(PDO $db, string $route, array &$data, ?array $current = null, ?int $id = null): void
{
    $definitions = [
        'programs' => ['table'=>'programas','required'=>['codigo','nombre','nivel','duracion_trimestres','estado']],
        'competencies' => ['table'=>'competencias','required'=>['codigo','nombre','programa_id','estado']],
        'learning-outcomes' => ['table'=>'resultados_aprendizaje','required'=>['codigo','descripcion','competencia_id','estado']],
        'academic-calendar' => ['table'=>'calendario_academico','required'=>['fecha','tipo','descripcion','estado']],
    ];
    $definition = $definitions[$route] ?? null;
    if ($definition === null) {
        return;
    }
    $values = array_merge($current ?? [], $data);
    foreach ($definition['required'] as $field) {
        if (!array_key_exists($field, $values) || !is_scalar($values[$field]) ||
            trim((string)$values[$field]) === '') {
            respond(['error' => 'Completa todos los campos obligatorios del registro académico.'], 422);
        }
    }
    $lengthLimits = ['codigo'=>40,'nombre'=>$route === 'programs' ? 150 : 180,'descripcion'=>500];
    foreach ($lengthLimits as $field => $maximum) {
        if (array_key_exists($field, $values) &&
            (!is_string($values[$field]) || trim($values[$field]) === '' || mb_strlen(trim($values[$field])) > $maximum)) {
            respond(['error' => "El campo {$field} debe contener entre 1 y {$maximum} caracteres."], 422);
        }
        if (array_key_exists($field, $data)) {
            $data[$field] = trim($data[$field]);
        }
    }
    if (array_key_exists('codigo', $values)) {
        $duplicate = $db->prepare("SELECT id FROM {$definition['table']} WHERE codigo = ? AND id <> ?");
        $duplicate->execute([trim((string)$values['codigo']), $id ?? 0]);
        if ($duplicate->fetchColumn()) {
            respond(['error' => 'Ya existe un registro académico con ese código.'], 409);
        }
    }
    if (!in_array($values['estado'] ?? null, ['activo','inactivo'], true)) {
        respond(['error' => 'El estado debe ser activo o inactivo.'], 422);
    }
    if ($route === 'programs') {
        if (!in_array($values['nivel'] ?? null, ['tecnico','tecnologo'], true) ||
            !isPositiveIntegerValue($values['duracion_trimestres'] ?? null) ||
            (int)$values['duracion_trimestres'] < 1 || (int)$values['duracion_trimestres'] > 65535) {
            respond(['error' => 'El nivel debe ser técnico o tecnólogo y la duración debe ser un número positivo de trimestres.'], 422);
        }
        if (array_key_exists('duracion_trimestres', $data)) {
            $data['duracion_trimestres'] = (int)$data['duracion_trimestres'];
        }
    }
    if ($route === 'competencies' || $route === 'learning-outcomes') {
        $field = $route === 'competencies' ? 'programa_id' : 'competencia_id';
        $table = $route === 'competencies' ? 'programas' : 'competencias';
        $foreignId = $values[$field] ?? null;
        if (!isPositiveIntegerValue($foreignId)) {
            respond(['error' => 'Selecciona una relación académica válida.'], 422);
        }
        $relationStmt = $db->prepare("SELECT id, estado FROM {$table} WHERE id = ?");
        $relationStmt->execute([(int)$foreignId]);
        $relation = $relationStmt->fetch();
        $sameRelation = $current !== null && (int)($current[$field] ?? 0) === (int)$foreignId;
        if (!$relation || ($relation['estado'] !== 'activo' && !$sameRelation)) {
            respond(['error' => 'La relación seleccionada no existe o está inactiva.'], 422);
        }
        if (array_key_exists($field, $data)) {
            $data[$field] = (int)$foreignId;
        }
    }
    if ($route === 'academic-calendar') {
        $date = (string)$values['fecha'];
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date ||
            !in_array($values['tipo'], ['festivo','vacaciones'], true) ||
            !is_string($values['descripcion']) || trim($values['descripcion']) === '' ||
            mb_strlen(trim($values['descripcion'])) > 250) {
            respond(['error' => 'Indica una fecha válida, tipo festivo o vacaciones y una descripción de máximo 250 caracteres.'], 422);
        }
        if (isset($data['descripcion'])) {
            $data['descripcion'] = trim($data['descripcion']);
        }
        $duplicate = $db->prepare('SELECT id FROM calendario_academico WHERE fecha = ? AND tipo = ? AND id <> ?');
        $duplicate->execute([$date, $values['tipo'], $id ?? 0]);
        if ($duplicate->fetchColumn()) {
            respond(['error' => 'Ya existe una fecha del calendario con ese tipo.'], 409);
        }
    }
}

function validateScheduleCompetency(PDO $db, array $data, ?array $current = null): void
{
    $schedule = array_merge($current ?? [], $data);
    if (!isPositiveIntegerValue($schedule['ficha_id'] ?? null)) {
        respond(['error' => 'Selecciona una ficha válida para el horario.'], 422);
    }
    $competencyId = $schedule['competencia_id'] ?? null;
    if ($competencyId === null || $competencyId === '') {
        if ($current === null || array_key_exists('competencia_id', $data)) {
            respond(['error' => 'Selecciona una competencia para el horario.'], 422);
        }
        return;
    }
    if (!isPositiveIntegerValue($competencyId)) {
        respond(['error' => 'La competencia seleccionada no es válida.'], 422);
    }
    $stmt = $db->prepare("SELECT f.programa_id, c.programa_id AS competencia_programa_id, c.estado
        FROM fichas f
        JOIN competencias c ON c.id = ?
        WHERE f.id = ?");
    $stmt->execute([(int)$competencyId, (int)$schedule['ficha_id']]);
    $relation = $stmt->fetch();
    $sameCompetency = $current !== null && (int)($current['competencia_id'] ?? 0) === (int)$competencyId;
    if (!$relation || $relation['programa_id'] === null ||
        (int)$relation['programa_id'] !== (int)$relation['competencia_programa_id'] ||
        ($relation['estado'] !== 'activo' && !$sameCompetency)) {
        respond(['error' => 'La competencia debe estar activa y pertenecer al programa de la ficha seleccionada.'], 422);
    }
}

function saveResource(PDO $db, string $table, array $fields, array $input, ?int $id, ?array $user): void
{
    $creating = $id === null;
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
    if ($table === 'asistencias' && ($values['estado'] ?? null) === 'permiso') {
        $values['estado_justificacion'] = 'justificada';
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
            if (isset($values['estado']) && $values['estado'] !== $currentAttendance['estado']) {
                $values['estado_justificacion'] = $values['estado'] === 'permiso' ? 'justificada' : 'pendiente';
                $checkValues['estado_justificacion'] = $values['estado_justificacion'];
            }
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
            if ($table === 'asistencias') {
                notifyAttendance(
                    $db,
                    (int)$values['usuario_id'],
                    (int)$values['ficha_id'],
                    empty($values['horario_id']) ? null : (int)$values['horario_id'],
                    (string)$values['estado'],
                    (string)$values['fecha'],
                    isset($values['observacion']) ? (string)$values['observacion'] : null,
                    (int)$user['id'],
                    'registró la asistencia'
                );
            }
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
                    $checkValues['observacion'] === null ? null : (string)$checkValues['observacion'],
                    (int)$user['id'],
                    'corrigió la asistencia'
                );
            }
        }
        if (in_array($table, ['fichas','horarios'], true)) {
            notifyFormationChange($db, $table, $id, (int)$user['id'], $creating ? 'creó' : 'actualizó');
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
        foreach (['tipo_documento','documento','nombres','apellidos','correo','password','telefono','pin'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field])) {
                respond(['error' => 'Faltan datos obligatorios o tienen un formato incorrecto.'], 422);
            }
        }
        if (($data['acepta_tratamiento_datos'] ?? false) !== true) {
            respond(['error' => 'Debes aceptar el tratamiento de datos personales para registrarte.'], 422);
        }
        if (isset($data['foto']) && $data['foto'] !== null && !is_string($data['foto'])) {
            respond(['error' => 'La foto del carné no tiene un formato válido.'], 422);
        }
        $registration = [
            'tipo_documento' => trim($data['tipo_documento']),
            'documento' => trim($data['documento']),
            'nombres' => trim($data['nombres']),
            'apellidos' => trim($data['apellidos']),
            'correo' => trim($data['correo']),
            'telefono' => trim($data['telefono']),
            'foto' => $data['foto'] ?? null,
        ];
        $password = $data['password'];
        $pin = $data['pin'];

        if ($registration['tipo_documento'] === '' || strlen($registration['tipo_documento']) > 20 ||
            $registration['documento'] === '' || strlen($registration['documento']) > 30 ||
            $registration['nombres'] === '' || strlen($registration['nombres']) > 100 ||
            $registration['apellidos'] === '' || strlen($registration['apellidos']) > 100 ||
            !filter_var($registration['correo'], FILTER_VALIDATE_EMAIL) || strlen($registration['correo']) > 190 ||
            strlen($password) < 8 || !preg_match('/^\d{4,6}$/', $pin) ||
            $registration['telefono'] === '' || strlen($registration['telefono']) > 30 ||
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
            $insert = $db->prepare('INSERT INTO usuarios (rol_id,tipo_documento,documento,nombres,apellidos,correo,password_hash,telefono,foto,tratamiento_datos_aceptado_en,activo) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $insert->execute([
                (int)$roleId, $registration['tipo_documento'], $registration['documento'], $registration['nombres'], $registration['apellidos'],
                $registration['correo'], $passwordHash, $registration['telefono'], $registration['foto'], date('Y-m-d H:i:s'), $student ? 1 : 0,
            ]);
            $userId = (int)$db->lastInsertId();
            $qrCode = uuid4();
            $db->prepare('INSERT INTO carnets (usuario_id,codigo_qr,pin_hash) VALUES (?,?,?)')
                ->execute([$userId, $qrCode, password_hash($pin, PASSWORD_DEFAULT)]);
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
                    'tipo_documento' => $registration['tipo_documento'], 'documento' => $registration['documento'], 'nombres' => $registration['nombres'],
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
        $identifier = trim((string)($data['identificador'] ?? $data['correo'] ?? ''));
        $stmt = $db->prepare('SELECT u.id, u.password_hash, u.activo, c.pin_hash
            FROM usuarios u JOIN carnets c ON c.usuario_id = u.id
            WHERE (u.correo = ? OR u.documento = ?) AND u.activo = 1
            AND NOT EXISTS (SELECT 1 FROM bloqueos b WHERE b.usuario_id = u.id AND b.desbloqueado_en IS NULL) LIMIT 1');
        $stmt->execute([$identifier, $identifier]);
        $record = $stmt->fetch();
        if (!$record || !password_verify((string)($data['password'] ?? ''), $record['password_hash'])) {
            respond(['error' => 'Correo o contrasena incorrectos, o usuario bloqueado.'], 401);
        }
        $pin = $data['pin'] ?? null;
        if ($pin !== null && $pin !== '') {
            if (!is_string($pin) || !preg_match('/^\d{4,6}$/', $pin)) {
                respond(['error' => 'El PIN debe tener de 4 a 6 números.'], 422);
            }
            $record['pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
            $db->prepare('UPDATE carnets SET pin_hash = ?, pin_intentos_fallidos = 0, pin_bloqueado_hasta = NULL WHERE usuario_id = ?')
                ->execute([$record['pin_hash'], $record['id']]);
            audit($db, (int)$record['id'], 'actualizar_pin_carnet', 'carnets', (int)$record['id']);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$record['id'];
        $_SESSION['last_activity'] = time();
        $db->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$record['id']]);
        audit($db, (int)$record['id'], 'inicio_sesion_temporal', 'usuarios', (int)$record['id']);
        $account = currentUser($db);
        $account['pin_configurado'] = $record['pin_hash'] !== null;
        respond(['data' => $account]);
    }
    if ($route === 'auth' && ($parts[1] ?? '') === 'qr-preview' && $method === 'POST') {
        $data = body();
        $qrCode = $data['codigo_qr'] ?? null;
        if (!is_string($qrCode) || trim($qrCode) === '' || strlen(trim($qrCode)) > 64) {
            respond(['error' => 'El código QR del carné no es válido.'], 422);
        }
        $userId = userFromQr($db, trim($qrCode));
        if (!$userId) {
            respond(['error' => 'No se encontró una cuenta activa con ese carné. Revisa que la solicitud esté aprobada y que el QR no haya vencido.'], 401);
        }
        $stmt = $db->prepare('SELECT nombres, apellidos, foto FROM usuarios WHERE id = ?');
        $stmt->execute([$userId]);
        respond(['data' => ['usuario' => $stmt->fetch()]]);
    }
    if ($route === 'auth' && ($parts[1] ?? '') === 'qr-login' && $method === 'POST') {
        $data = body();
        $qrCode = $data['codigo_qr'] ?? null;
        if (!is_string($qrCode) || trim($qrCode) === '' || strlen(trim($qrCode)) > 64) {
            respond(['error' => 'El código QR del carné no es válido.'], 422);
        }
        $pin = $data['pin'] ?? null;
        if (!is_string($pin) || !preg_match('/^\d{4,6}$/', $pin)) {
            respond(['error' => 'Ingresa el PIN de 4 a 6 números.'], 422);
        }
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT c.usuario_id, c.pin_hash, c.pin_intentos_fallidos,
                    c.pin_bloqueado_hasta, c.pin_bloqueado_hasta > NOW() AS bloqueado
                FROM carnets c JOIN usuarios u ON u.id = c.usuario_id AND u.activo = 1
                WHERE c.codigo_qr = ? AND c.estado = \'activo\'
                    AND (c.vence_en IS NULL OR c.vence_en >= CURDATE())
                    AND NOT EXISTS (SELECT 1 FROM bloqueos b WHERE b.usuario_id = u.id AND b.desbloqueado_en IS NULL)
                LIMIT 1 FOR UPDATE');
            $stmt->execute([trim($qrCode)]);
            $record = $stmt->fetch();
            if (!$record) {
                $db->commit();
                respond(['error' => 'No se pudo validar el carné o el PIN.'], 401);
            }
            $userId = (int)$record['usuario_id'];
            if ($record['bloqueado']) {
                $db->commit();
                respond(['error' => 'Acceso con carné bloqueado temporalmente. Intenta de nuevo más tarde.'], 429);
            }
            if ($record['pin_hash'] === null) {
                $db->commit();
                respond(['error' => 'Este carné aún no tiene PIN configurado. Ingresa con documento o correo y contraseña.'], 401);
            }
            $settings = systemSettings($db);
            $maximumAttempts = $settings['pin_intentos_maximos'] ?? null;
            $lockMinutes = $settings['pin_bloqueo_minutos'] ?? null;
            if (!is_int($maximumAttempts) || !is_int($lockMinutes)) {
                throw new RuntimeException('Falta la configuración de intentos de PIN del carné.');
            }
            $attempts = $record['pin_bloqueado_hasta'] !== null ? 0 : (int)$record['pin_intentos_fallidos'];
            if (!password_verify($pin, $record['pin_hash'])) {
                $attempts++;
                $db->prepare('UPDATE carnets SET pin_intentos_fallidos = ?, pin_bloqueado_hasta = IF(?, DATE_ADD(NOW(), INTERVAL ' . $lockMinutes . ' MINUTE), NULL) WHERE usuario_id = ?')
                    ->execute([$attempts, $attempts >= $maximumAttempts ? 1 : 0, $userId]);
                $db->commit();
                respond([
                    'error' => $attempts >= $maximumAttempts
                        ? "PIN incorrecto. El acceso con carné quedó bloqueado durante {$lockMinutes} minutos."
                        : 'PIN incorrecto. Verifica el número e inténtalo de nuevo.',
                ], $attempts >= $maximumAttempts ? 429 : 401);
            }
            $db->prepare('UPDATE carnets SET pin_intentos_fallidos = 0, pin_bloqueado_hasta = NULL WHERE usuario_id = ?')->execute([$userId]);
            $db->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$userId]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$userId;
        $_SESSION['last_activity'] = time();
        $db->prepare('UPDATE carnets SET qr_entregado = 1 WHERE usuario_id = ?')->execute([$userId]);
        audit($db, (int)$userId, 'inicio_sesion_codigo_qr', 'usuarios', (int)$userId);
        respond(['data' => currentUser($db)]);
    }
    if ($route === 'auth' && ($parts[1] ?? '') === 'card' && $method === 'GET') {
        $account = requireRoles($user, ['administrador','instructor','estudiante','portero']);
        $stmt = $db->prepare('SELECT c.codigo_qr, u.telefono, u.foto FROM carnets c JOIN usuarios u ON u.id = c.usuario_id
            WHERE c.usuario_id = ? AND c.estado = \'activo\'
              AND (c.vence_en IS NULL OR c.vence_en >= CURDATE())');
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
            WHERE c.codigo_qr = ? AND c.estado = 'activo' AND ($ownership EXISTS (
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
            WHERE c.codigo_qr = ? AND c.estado = 'activo' AND ($ownershipCheck EXISTS (
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
    if ($route === 'learner' && ($parts[1] ?? '') === 'equipment') {
        $learner = requireRoles($user, ['estudiante','instructor']);
        $equipmentId = isset($parts[2]) && ctype_digit($parts[2]) ? (int)$parts[2] : null;
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT id, serial, nombre, tipo, marca, color, foto
                FROM equipos WHERE usuario_id = ? AND estado <> 'baja' ORDER BY id DESC");
            $stmt->execute([(int)$learner['id']]);
            respond(['data' => $stmt->fetchAll()]);
        }
        if ($method === 'POST' || ($method === 'PUT' && $equipmentId !== null)) {
            $data = body();
            foreach (['serial','nombre','tipo'] as $field) {
                if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
                    respond(['error' => 'Completa serial, nombre y tipo del equipo.'], 422);
                }
            }
            if (!in_array($data['tipo'], ['portatil','tablet','teclado','mouse','maleta','otro'], true) ||
                mb_strlen(trim($data['serial'])) > 100 || mb_strlen(trim($data['nombre'])) > 150 ||
                (isset($data['marca']) && (!is_string($data['marca']) || mb_strlen($data['marca']) > 100)) ||
                (isset($data['color']) && (!is_string($data['color']) || mb_strlen($data['color']) > 60))) {
                respond(['error' => 'Los datos del equipo no son válidos.'], 422);
            }
            $photo = $data['foto'] ?? null;
            if ($photo !== null && (!is_string($photo) || strlen($photo) > 1400000 ||
                !preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/]+={0,2}$#', $photo))) {
                respond(['error' => 'La foto debe ser JPG, PNG o WEBP y no superar 1 MB.'], 422);
            }
            $duplicate = $db->prepare('SELECT id FROM equipos WHERE serial = ? AND (? IS NULL OR id <> ?) LIMIT 1');
            $duplicate->execute([trim($data['serial']),$equipmentId,$equipmentId]);
            if ($duplicate->fetchColumn()) {
                respond(['error' => 'Ya existe un equipo con ese serial.'], 409);
            }
            if ($method === 'PUT') {
                $openAccess = $db->prepare("SELECT 1 FROM ingreso_diario_equipos ie
                    JOIN ingresos_diarios i ON i.id = ie.ingreso_diario_id
                    WHERE ie.equipo_id = ? AND i.fecha = CURRENT_DATE() AND i.acceso_abierto = 1 LIMIT 1");
                $openAccess->execute([$equipmentId]);
                if ($openAccess->fetchColumn()) {
                    respond(['error' => 'No puedes editar un equipo mientras está dentro del centro.'], 409);
                }
                $stmt = $db->prepare("UPDATE equipos SET serial = ?, nombre = ?, categoria = ?, tipo = ?,
                    marca = ?, color = ?, foto = COALESCE(?, foto)
                    WHERE id = ? AND usuario_id = ? AND estado <> 'baja'");
                $stmt->execute([
                    trim($data['serial']),trim($data['nombre']),$data['tipo'],$data['tipo'],
                    isset($data['marca']) ? trim($data['marca']) : null,
                    isset($data['color']) ? trim($data['color']) : null,$photo,
                    $equipmentId,(int)$learner['id'],
                ]);
                if ($stmt->rowCount() === 0) {
                    $exists = $db->prepare("SELECT 1 FROM equipos WHERE id = ? AND usuario_id = ? AND estado <> 'baja'");
                    $exists->execute([$equipmentId,(int)$learner['id']]);
                    if (!$exists->fetchColumn()) {
                        respond(['error' => 'No se encontró un equipo activo de tu propiedad.'], 404);
                    }
                }
                audit($db,(int)$learner['id'],'editar_equipo_personal','equipos',$equipmentId);
                respond(['data' => ['id' => $equipmentId]]);
            }
            $stmt = $db->prepare("INSERT INTO equipos
                (serial,codigo_qr,nombre,categoria,tipo,marca,color,foto,estado,ubicacion,autorizado,usuario_id)
                VALUES (?,?,?,?,?,?,?,?,'asignado','Uso personal',1,?)");
            $stmt->execute([
                trim($data['serial']), uuid4(), trim($data['nombre']), $data['tipo'], $data['tipo'],
                isset($data['marca']) ? trim($data['marca']) : null,
                isset($data['color']) ? trim($data['color']) : null,
                $photo, (int)$learner['id'],
            ]);
            $id = (int)$db->lastInsertId();
            audit($db, (int)$learner['id'], 'registrar_equipo_personal', 'equipos', $id);
            respond(['data' => ['id' => $id]], 201);
        }
        if ($method === 'DELETE' && $equipmentId !== null) {
            $openAccess = $db->prepare("SELECT 1 FROM ingreso_diario_equipos ie
                JOIN ingresos_diarios i ON i.id = ie.ingreso_diario_id
                WHERE ie.equipo_id = ? AND i.fecha = CURRENT_DATE() AND i.acceso_abierto = 1 LIMIT 1");
            $openAccess->execute([$equipmentId]);
            if ($openAccess->fetchColumn()) {
                respond(['error' => 'No puedes desactivar un equipo mientras está dentro del centro.'], 409);
            }
            $stmt = $db->prepare("UPDATE equipos SET estado = 'baja'
                WHERE id = ? AND usuario_id = ? AND estado <> 'baja'");
            $stmt->execute([$equipmentId,(int)$learner['id']]);
            if ($stmt->rowCount() !== 1) {
                respond(['error' => 'No se encontró un equipo activo de tu propiedad.'], 404);
            }
            audit($db,(int)$learner['id'],'desactivar_equipo_personal','equipos',$equipmentId);
            respond(['data' => ['id' => $equipmentId]]);
        }
        respond(['error' => 'Método no permitido.'], 405);
    }
    if ($route === 'learner' && ($parts[1] ?? '') === 'entry-destinations' && $method === 'GET') {
        requireRoles($user, ['estudiante','instructor']);
        respond(['data' => [
            'documento' => $user['documento'] ?? null,
            'ambientes' => $db->query("SELECT id,codigo,nombre FROM ambientes WHERE estado <> 'mantenimiento' ORDER BY codigo")->fetchAll(),
        ]]);
    }
    if ($route === 'learner' && ($parts[1] ?? '') === 'entry-token' && $method === 'POST') {
        $person = requireRoles($user, ['estudiante','instructor']);
        $data = body();
        $destinationId = (int)($data['ambiente_id'] ?? 0);
        $destination = null;
        if ($destinationId > 0) {
            $stmt = $db->prepare("SELECT id,codigo,nombre FROM ambientes WHERE id = ? AND estado <> 'mantenimiento'");
            $stmt->execute([$destinationId]);
            $destination = $stmt->fetch();
        }
        if (!$destination) {
            respond(['error' => 'Selecciona el ambiente al que te diriges.'], 422);
        }
        $equipmentIds = $data['equipo_ids'] ?? [];
        if (!is_array($equipmentIds) || count($equipmentIds) > 30) {
            respond(['error' => 'Selecciona una lista válida de hasta 30 equipos.'], 422);
        }
        $equipmentIds = array_values(array_unique(array_map('intval', $equipmentIds)));
        if (count($equipmentIds) !== count($data['equipo_ids'] ?? [] ) || in_array(0, $equipmentIds, true)) {
            respond(['error' => 'La lista de equipos contiene identificadores no válidos.'], 422);
        }
        $db->beginTransaction();
        try {
            $owned = [];
            if ($equipmentIds) {
                $marks = implode(',', array_fill(0, count($equipmentIds), '?'));
                $stmt = $db->prepare("SELECT id FROM equipos WHERE usuario_id = ? AND estado <> 'baja' AND id IN ({$marks})");
                $stmt->execute(array_merge([(int)$person['id']], $equipmentIds));
                $owned = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                sort($owned);
                $requested = $equipmentIds;
                sort($requested);
                if ($owned !== $requested) {
                    $db->rollBack();
                    respond(['error' => 'Solo puedes declarar equipos registrados a tu nombre.'], 403);
                }
            }
            $token = uuid4();
            $db->prepare("INSERT INTO ingresos_diarios (usuario_id,fecha,token_hash,ambiente_destino_id)
                VALUES (?,CURRENT_DATE(),?,?) ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash),
                ambiente_destino_id = VALUES(ambiente_destino_id), usado_en = NULL, acceso_abierto = 0")
                ->execute([(int)$person['id'], hash('sha256', $token), (int)$destination['id']]);
            $stmt = $db->prepare('SELECT id FROM ingresos_diarios WHERE usuario_id = ? AND fecha = CURRENT_DATE()');
            $stmt->execute([(int)$person['id']]);
            $dailyId = (int)$stmt->fetchColumn();
            $db->prepare('DELETE FROM ingreso_diario_equipos WHERE ingreso_diario_id = ?')->execute([$dailyId]);
            if ($equipmentIds) {
                $insert = $db->prepare('INSERT INTO ingreso_diario_equipos (ingreso_diario_id,equipo_id) VALUES (?,?)');
                foreach ($equipmentIds as $equipmentId) {
                    $insert->execute([$dailyId, $equipmentId]);
                }
            }
            audit($db, (int)$person['id'], 'generar_qr_ingreso_diario', 'ingresos_diarios', $dailyId,             ['equipos' => $equipmentIds, 'ambiente_id' => (int)$destination['id']]);
            $db->commit();
                        respond(['data' => ['qr' => $token, 'fecha' => databaseDate($db), 'equipos' => $equipmentIds,
                            'ambiente' => $destination['codigo'].' · '.$destination['nombre']]]);
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }
    if ($route === 'porter') {
        $porter = requireRoles($user, ['administrador','portero']);
        if (($parts[1] ?? '') === 'alerts' && $method === 'GET') {
            requireRoles($user, ['administrador']);
            $alerts=[];
            $keys=$db->query("SELECT 'llave_vencida' AS tipo,k.vence_en AS fecha,
                    CONCAT('La llave de ',e.nombre,' prestada a ',u.nombres,' ',u.apellidos,
                        ' venció el ',k.vence_en,'.') AS mensaje
                FROM llaves_ambientes k JOIN ambientes e ON e.id=k.ambiente_id
                JOIN usuarios u ON u.id=k.instructor_id
                WHERE k.estado IN ('prestada','vencida') AND k.devuelta_en IS NULL AND k.vence_en<NOW()
                ORDER BY k.vence_en DESC LIMIT 50")->fetchAll();
            foreach($keys as $key){$key['nivel']='roja';$alerts[]=$key;}
            $blocked=$db->query("SELECT 'acceso_bloqueado' AS tipo,a.fecha_hora AS fecha,
                    CONCAT(u.nombres,' ',u.apellidos,' intentó ingresar con un carné bloqueado o vencido.') AS mensaje
                FROM auditoria a JOIN usuarios u
                    ON u.id=CAST(JSON_UNQUOTE(JSON_EXTRACT(a.detalle,'$.usuario_id')) AS UNSIGNED)
                WHERE a.accion='intento_acceso_bloqueado' AND DATE(a.fecha_hora)=CURRENT_DATE()
                ORDER BY a.fecha_hora DESC LIMIT 50")->fetchAll();
            foreach($blocked as $alert){$alert['nivel']='roja';$alerts[]=$alert;}
            $mismatches=$db->query("SELECT 'equipos_no_coinciden' AS tipo,a.fecha_hora AS fecha,
                    CONCAT(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.detalle,'$.persona')),'Persona'),
                        ' salió con equipos distintos a los declarados.') AS mensaje
                FROM auditoria a
                WHERE a.accion='registrar_salida' AND DATE(a.fecha_hora)=CURRENT_DATE()
                    AND JSON_EXTRACT(a.detalle,'$.alerta_equipos.esperados') IS NOT NULL
                ORDER BY a.fecha_hora DESC LIMIT 50")->fetchAll();
            foreach($mismatches as $alert){$alert['nivel']='amarilla';$alerts[]=$alert;}
            usort($alerts,static fn($left,$right)=>strcmp((string)$right['fecha'],(string)$left['fecha']));
            respond(['data'=>['alertas'=>array_slice($alerts,0,100)]]);
        }
        if (($parts[1] ?? '') === 'declared' && $method === 'GET') {
            $rows = $db->query("SELECT i.id,i.usado_en,i.acceso_abierto,
                    COALESCE(CONCAT(u.nombres,' ',u.apellidos),v.nombres) AS persona,
                    COALESCE(u.documento,v.documento) AS documento,
                    COALESCE((SELECT f.codigo FROM fichas_usuarios fu JOIN fichas f ON f.id=fu.ficha_id
                        WHERE fu.usuario_id=u.id LIMIT 1),r.nombre,'Visitante') AS ficha_rol,
                    CONCAT(d.codigo,' · ',d.nombre) AS destino
                FROM ingresos_diarios i LEFT JOIN usuarios u ON u.id=i.usuario_id
                LEFT JOIN roles r ON r.id=u.rol_id LEFT JOIN visitantes v ON v.id=i.visitante_id
                LEFT JOIN ambientes d ON d.id=i.ambiente_destino_id
                WHERE i.fecha=CURRENT_DATE() AND EXISTS (SELECT 1 FROM ingreso_diario_equipos x WHERE x.ingreso_diario_id=i.id)
                ORDER BY i.creado_en DESC LIMIT 100")->fetchAll();
            $equipmentStmt = $db->prepare("SELECT e.tipo,e.nombre,e.marca,e.color,e.serial,e.foto
                FROM ingreso_diario_equipos x JOIN equipos e ON e.id=x.equipo_id WHERE x.ingreso_diario_id=?");
            foreach ($rows as &$row) {
                $equipmentStmt->execute([(int)$row['id']]);
                $row['equipos'] = $equipmentStmt->fetchAll();
                $row['estado'] = (int)$row['acceso_abierto'] ? 'Dentro' : ($row['usado_en'] ? 'Salió' : 'Pendiente de ingreso');
            }
            unset($row);
            respond(['data' => $rows]);
        }
        if (($parts[1] ?? '') === 'overview' && $method === 'GET') {
            requireRoles($user, ['administrador']);
            $date = $_GET['fecha'] ?? databaseDate($db);
            $parsedDate = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                ? DateTime::createFromFormat('!Y-m-d', $date)
                : false;
            if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
                respond(['error'=>'La fecha del filtro debe tener formato YYYY-MM-DD.'],422);
            }
            $userFilter = $_GET['usuario_id'] ?? null;
            $documentFilter = is_string($_GET['documento'] ?? null) ? trim($_GET['documento']) : '';
            if ($documentFilter !== '') {
                $lookup = $db->prepare('SELECT id FROM usuarios WHERE documento=? LIMIT 1');
                $lookup->execute([mb_substr($documentFilter, 0, 30)]);
                $userFilter = (int)$lookup->fetchColumn() ?: 2147483647;
            }
            $fichaFilter = $_GET['ficha_id'] ?? null;
            $environmentFilter = $_GET['ambiente_id'] ?? null;
            $serialFilter = $_GET['serial'] ?? null;
            if ($serialFilter !== null && (!is_string($serialFilter) || mb_strlen($serialFilter) > 100)) {
                respond(['error'=>'El filtro de serial debe contener como máximo 100 caracteres.'],422);
            }
            $serialFilter = is_string($serialFilter) ? trim($serialFilter) : '';
            $accessSql = "SELECT a.id,a.usuario_id,a.visitante_id,a.tipo,a.fecha_hora,a.observacion,
                    COALESCE(CONCAT(u.nombres,' ',u.apellidos),v.nombres) AS persona,
                    COALESCE(u.documento,v.documento) AS documento, f.codigo AS ficha,
                    r.nombre AS rol, e.nombre AS ambiente
                FROM accesos a LEFT JOIN usuarios u ON u.id=a.usuario_id
                LEFT JOIN roles r ON r.id=u.rol_id LEFT JOIN fichas_usuarios fu ON fu.usuario_id=u.id
                LEFT JOIN fichas f ON f.id=fu.ficha_id LEFT JOIN visitantes v ON v.id=a.visitante_id
                LEFT JOIN llaves_ambientes k ON k.instructor_id=u.id AND DATE(k.prestada_en)=DATE(a.fecha_hora)
                LEFT JOIN ambientes e ON e.id=k.ambiente_id WHERE DATE(a.fecha_hora)=?";
            $params = [$date];
            if (isPositiveIntegerValue($userFilter)) {
                $accessSql .= ' AND a.usuario_id=?';
                $params[] = (int)$userFilter;
            }
            if (isPositiveIntegerValue($fichaFilter)) {
                $accessSql .= ' AND f.id=?';
                $params[] = (int)$fichaFilter;
            }
            if (isPositiveIntegerValue($environmentFilter)) {
                $accessSql .= ' AND e.id=?';
                $params[] = (int)$environmentFilter;
            }
            $accessSql .= ' ORDER BY a.fecha_hora DESC LIMIT 500';
            $stmt = $db->prepare($accessSql);
            $stmt->execute($params);
            $accesses = $stmt->fetchAll();
            $keySql = "SELECT k.id,k.ambiente_id,e.nombre AS ambiente,k.instructor_id,
                    CONCAT(u.nombres,' ',u.apellidos) AS instructor,k.prestada_en,k.vence_en,
                    k.devuelta_en,IF(k.estado='prestada' AND k.vence_en<NOW(),'vencida',k.estado) AS estado
                FROM llaves_ambientes k
                JOIN ambientes e ON e.id=k.ambiente_id JOIN usuarios u ON u.id=k.instructor_id
                WHERE DATE(k.prestada_en)=?";
            $keyParams=[$date];
            if(isPositiveIntegerValue($userFilter)){$keySql.=' AND k.instructor_id=?';$keyParams[]=(int)$userFilter;}
            if(isPositiveIntegerValue($fichaFilter)){
                $keySql.=' AND EXISTS (SELECT 1 FROM fichas_instructores fi WHERE fi.instructor_id=k.instructor_id AND fi.ficha_id=?)';
                $keyParams[]=(int)$fichaFilter;
            }
            if(isPositiveIntegerValue($environmentFilter)){$keySql.=' AND k.ambiente_id=?';$keyParams[]=(int)$environmentFilter;}
            $keySql.=' ORDER BY k.prestada_en DESC LIMIT 200';
            $keyStmt=$db->prepare($keySql);$keyStmt->execute($keyParams);$keys=$keyStmt->fetchAll();
            $visitorSql="SELECT v.id,v.documento,v.nombres,v.entidad,v.motivo,v.anfitrion_id,v.ingreso_en,v.salida_en
                FROM visitantes v WHERE DATE(v.ingreso_en)=?";
            $visitorParams=[$date];
            if(isPositiveIntegerValue($userFilter)){$visitorSql.=' AND v.anfitrion_id=?';$visitorParams[]=(int)$userFilter;}
            $visitorSql.=' ORDER BY v.ingreso_en DESC LIMIT 200';
            $visitorStmt=$db->prepare($visitorSql);$visitorStmt->execute($visitorParams);$visitors=$visitorStmt->fetchAll();
            $logSql='SELECT n.id,n.turno,n.novedad,n.registrada_en,n.entregada_a,n.entregada_en,
                    CONCAT(u.nombres," ",u.apellidos) AS portero FROM novedades_turno n
                JOIN usuarios u ON u.id=n.portero_id WHERE DATE(n.registrada_en)=?';
            $logParams=[$date];
            if(isPositiveIntegerValue($userFilter)){$logSql.=' AND n.portero_id=?';$logParams[]=(int)$userFilter;}
            $logSql.=' ORDER BY n.registrada_en DESC LIMIT 200';
            $logStmt=$db->prepare($logSql);$logStmt->execute($logParams);$logs=$logStmt->fetchAll();
            $lostSql="SELECT o.id,o.descripcion,o.encontrado_en,o.encontrado_el,o.estado,
                    o.entregado_a,o.entregado_en,CONCAT(u.nombres,' ',u.apellidos) AS encontrado_por
                FROM objetos_perdidos o JOIN usuarios u ON u.id=o.encontrado_por
                WHERE DATE(o.encontrado_el)=?";
            $lostParams=[$date];
            if(isPositiveIntegerValue($userFilter)){$lostSql.=' AND (o.encontrado_por=? OR o.entregado_a=?)';$lostParams[]=(int)$userFilter;$lostParams[]=(int)$userFilter;}
            $lostSql.=' ORDER BY o.encontrado_el DESC LIMIT 200';
            $lostStmt=$db->prepare($lostSql);$lostStmt->execute($lostParams);$lost=$lostStmt->fetchAll();
            $equipmentSql="SELECT m.id,m.tipo,m.fecha_hora,m.observacion,e.serial,e.nombre AS equipo,
                    COALESCE(CONCAT(u.nombres,' ',u.apellidos),v.nombres) AS persona
                FROM movimientos_equipos m JOIN equipos e ON e.id=m.equipo_id
                LEFT JOIN usuarios u ON u.id=m.usuario_id LEFT JOIN visitantes v ON v.id=e.visitante_id
                LEFT JOIN fichas_usuarios fu ON fu.usuario_id=m.usuario_id
                WHERE DATE(m.fecha_hora)=?";
            $equipmentParams=[$date];
            if(isPositiveIntegerValue($userFilter)){$equipmentSql.=' AND m.usuario_id=?';$equipmentParams[]=(int)$userFilter;}
            if(isPositiveIntegerValue($fichaFilter)){$equipmentSql.=' AND fu.ficha_id=?';$equipmentParams[]=(int)$fichaFilter;}
            if($serialFilter!==''){$equipmentSql.=' AND LOCATE(?,e.serial)>0';$equipmentParams[]=$serialFilter;}
            $equipmentSql.=' ORDER BY m.fecha_hora DESC LIMIT 500';
            $equipmentStmt=$db->prepare($equipmentSql);$equipmentStmt->execute($equipmentParams);$equipment=$equipmentStmt->fetchAll();
            respond(['data' => [
                'accesos'=>$accesses, 'equipos'=>$equipment, 'llaves'=>$keys, 'visitantes'=>$visitors,
                'novedades'=>$logs, 'objetos'=>$lost,
                'alertas'=>array_values(array_filter($keys, static fn($key) => $key['estado'] === 'vencida')),
            ]]);
        }
        if (($parts[1] ?? '') === 'visitors' && $method === 'POST') {
            $data=body();
            foreach (['documento','nombres','motivo'] as $field) {
                if (!is_string($data[$field]??null)||trim($data[$field])==='') {
                    respond(['error'=>'Completa documento, nombre y motivo de la visita.'],422);
                }
            }
            if (mb_strlen(trim($data['documento']))>30||mb_strlen(trim($data['nombres']))>160||
                mb_strlen(trim($data['motivo']))>300||
                isset($data['entidad'])&&(!is_string($data['entidad'])||mb_strlen($data['entidad'])>150)||
                isset($data['anfitrion_id'])&&$data['anfitrion_id']!==''&&!isPositiveIntegerValue($data['anfitrion_id'])) {
                respond(['error'=>'Los datos del visitante superan los límites permitidos o contienen un anfitrión no válido.'],422);
            }
            $token=uuid4();
            $stmt=$db->prepare('INSERT INTO visitantes (documento,codigo_qr,nombres,entidad,motivo,anfitrion_id,registrado_por)
                VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([
                trim($data['documento']),$token,trim($data['nombres']),
                isset($data['entidad'])?trim((string)$data['entidad']):null,trim($data['motivo']),
                isPositiveIntegerValue($data['anfitrion_id']??null)?(int)$data['anfitrion_id']:null,(int)$porter['id'],
            ]);
            $visitorId=(int)$db->lastInsertId();
            $db->prepare('INSERT INTO ingresos_diarios (visitante_id,fecha,token_hash) VALUES (?,CURRENT_DATE(),?)')
                ->execute([$visitorId,hash('sha256',$token)]);
            audit($db,(int)$porter['id'],'registrar_visitante','visitantes',$visitorId);
            respond(['data'=>['id'=>$visitorId,'qr'=>$token]],201);
        }
        if (($parts[1] ?? '') === 'visitors' && $method === 'GET') {
            $stmt=$db->query("SELECT v.id,v.documento,v.nombres,v.entidad,v.motivo,v.anfitrion_id,
                    v.ingreso_en,v.salida_en,
                    COALESCE(CONCAT(h.nombres,' ',h.apellidos),'—') AS anfitrion
                FROM visitantes v LEFT JOIN usuarios h ON h.id=v.anfitrion_id
                WHERE v.salida_en IS NULL ORDER BY v.ingreso_en DESC LIMIT 200");
            respond(['data'=>$stmt->fetchAll()]);
        }
        if (($parts[1] ?? '') === 'visitor-equipment' && $method === 'POST') {
            $data=body();
            if (!isPositiveIntegerValue($data['visitante_id']??null)||
                !is_string($data['serial']??null)||trim($data['serial'])===''||
                !is_string($data['nombre']??null)||trim($data['nombre'])===''||
                !in_array($data['tipo']??null,['portatil','tablet','teclado','mouse','maleta','otro'],true)) {
                respond(['error'=>'Completa visitante, serial, nombre y tipo de equipo.'],422);
            }
            if(mb_strlen(trim($data['serial']))>100||mb_strlen(trim($data['nombre']))>150||
                isset($data['marca'])&&(!is_string($data['marca'])||mb_strlen($data['marca'])>100)||
                isset($data['color'])&&(!is_string($data['color'])||mb_strlen($data['color'])>60)||
                isset($data['foto'])&&(!is_string($data['foto'])||strlen($data['foto'])>1400000||
                    !preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/]+={0,2}$#',$data['foto']))) {
                respond(['error'=>'Los datos o la foto del equipo de visitante no son válidos.'],422);
            }
            $daily=$db->prepare('SELECT id FROM ingresos_diarios WHERE visitante_id=? AND fecha=CURRENT_DATE()');
            $daily->execute([(int)$data['visitante_id']]);
            $dailyId=$daily->fetchColumn();
            if (!$dailyId) {
                respond(['error'=>'El visitante no tiene un ingreso previsto para hoy.'],404);
            }
            $stmt=$db->prepare("INSERT INTO equipos (serial,codigo_qr,nombre,categoria,tipo,marca,color,foto,
                estado,ubicacion,autorizado,visitante_id) VALUES (?,?,?,?,?,?,?,?, 'asignado','Visita',1,?)");
            $stmt->execute([
                trim($data['serial']),uuid4(),trim($data['nombre']),$data['tipo'],$data['tipo'],
                isset($data['marca'])?trim((string)$data['marca']):null,
                isset($data['color'])?trim((string)$data['color']):null,
                isset($data['foto'])?(string)$data['foto']:null,(int)$data['visitante_id'],
            ]);
            $equipmentId=(int)$db->lastInsertId();
            $db->prepare('INSERT INTO ingreso_diario_equipos (ingreso_diario_id,equipo_id) VALUES (?,?)')
                ->execute([(int)$dailyId,$equipmentId]);
            audit($db,(int)$porter['id'],'registrar_equipo_visitante','equipos',$equipmentId,
                ['visitante_id'=>(int)$data['visitante_id']]);
            respond(['data'=>['id'=>$equipmentId]],201);
        }
        if (($parts[1] ?? '') === 'visitor-equipment' && $method === 'GET') {
            $stmt=$db->query("SELECT e.id,e.visitante_id,e.serial,e.nombre,e.tipo,e.marca,e.color,
                    v.nombres AS visitante FROM equipos e JOIN visitantes v ON v.id=e.visitante_id
                ORDER BY e.id DESC LIMIT 200");
            respond(['data'=>$stmt->fetchAll()]);
        }
        if (($parts[1] ?? '') === 'scan' && $method === 'POST') {
            $data = body();
            $code = $data['qr'] ?? null;
            if (!is_string($code) || trim($code) === '' || strlen($code) > 100) {
                respond(['error' => 'Escanea un QR temporal o un carné válido.'], 422);
            }
            ensureDailyEntryForCard($db, trim($code));
            $stmt = $db->prepare("SELECT i.id AS ingreso_id,i.acceso_abierto,i.usado_en,u.id AS usuario_id,
                    u.documento,u.nombres,u.apellidos,u.foto,u.activo,r.nombre AS rol,
                    c.codigo_qr,c.estado AS estado_carnet,c.vence_en,b.id AS bloqueo_id, f.codigo AS ficha,
                    v.id AS visitante_id,v.documento AS visitante_documento,v.nombres AS visitante_nombre,
                    v.entidad,v.salida_en,CONCAT(a.codigo,' · ',a.nombre) AS destino
                FROM ingresos_diarios i LEFT JOIN usuarios u ON u.id=i.usuario_id
                LEFT JOIN ambientes a ON a.id=i.ambiente_destino_id
                LEFT JOIN roles r ON r.id=u.rol_id LEFT JOIN carnets c ON c.usuario_id=u.id
                LEFT JOIN bloqueos b ON b.usuario_id=u.id AND b.desbloqueado_en IS NULL
                LEFT JOIN fichas_usuarios fu ON fu.usuario_id=u.id LEFT JOIN fichas f ON f.id=fu.ficha_id
                LEFT JOIN visitantes v ON v.id=i.visitante_id
                WHERE i.fecha=CURRENT_DATE() AND (i.token_hash=? OR c.codigo_qr=? OR v.codigo_qr=?) LIMIT 1");
            $stmt->execute([hash('sha256', trim($code)), trim($code), trim($code)]);
            $identity = $stmt->fetch();
            if (!$identity) {
                respond(['error' => 'El código no existe, venció o no tiene un ingreso diario activo.'], 404);
            }
            $visitor = $identity['visitante_id'] !== null;
            $personId = $visitor ? (int)$identity['visitante_id'] : (int)$identity['usuario_id'];
            $blocked = !$visitor && ($identity['bloqueo_id'] !== null || $identity['rol'] === null ||
                !(int)$identity['activo'] || $identity['codigo_qr'] === null ||
                $identity['estado_carnet'] !== 'activo' ||
                ($identity['vence_en'] !== null && $identity['vence_en'] < databaseDate($db)));
            if ($visitor && $identity['salida_en'] !== null) {
                respond(['error' => 'El visitante ya registró salida.'], 409);
            }
            $equipmentStmt = $db->prepare("SELECT e.id,e.serial,e.nombre,e.tipo,e.marca,e.color,e.foto
                FROM ingreso_diario_equipos ie JOIN equipos e ON e.id=ie.equipo_id
                WHERE ie.ingreso_diario_id=? ORDER BY e.nombre");
            $equipmentStmt->execute([(int)$identity['ingreso_id']]);
            respond(['data' => [
                'persona'=>[
                    'id'=>$personId,
                    'tipo'=>$visitor?'visitante':$identity['rol'],
                    'nombre'=>$visitor?$identity['visitante_nombre']:trim($identity['nombres'].' '.$identity['apellidos']),
                    'documento'=>$visitor?$identity['visitante_documento']:$identity['documento'],
                    'foto'=>$visitor?null:$identity['foto'],
                    'ficha'=>$visitor?null:$identity['ficha'],
                    'carnet'=>$blocked?'bloqueado':'activo',
                ],
                'visitante'=>$visitor,
                'destino'=>$identity['destino'],
                'acceso_abierto'=>(bool)$identity['acceso_abierto'],
                'equipos'=>$equipmentStmt->fetchAll(),
                'bloqueado'=>$blocked,
            ]]);
        }
        if (($parts[1] ?? '') === 'access' && $method === 'POST') {
            $data = body();
            if (!in_array($data['tipo'] ?? null, ['ingreso','salida'], true) ||
                !is_string($data['qr'] ?? null)) {
                respond(['error' => 'Indica el QR escaneado y el tipo de movimiento.'], 422);
            }
            ensureDailyEntryForCard($db, trim($data['qr']));
            $stmt = $db->prepare("SELECT i.id,i.usuario_id,i.visitante_id,i.acceso_abierto,i.token_hash,
                    u.activo,u.documento,u.nombres,u.apellidos,c.codigo_qr,c.estado AS estado_carnet,c.vence_en,v.salida_en,
                    EXISTS(SELECT 1 FROM bloqueos b WHERE b.usuario_id=u.id AND b.desbloqueado_en IS NULL) AS bloqueado
                FROM ingresos_diarios i LEFT JOIN usuarios u ON u.id=i.usuario_id
                LEFT JOIN carnets c ON c.usuario_id=u.id LEFT JOIN visitantes v ON v.id=i.visitante_id
                WHERE i.fecha=CURRENT_DATE() AND (i.token_hash=? OR c.codigo_qr=? OR v.codigo_qr=?) FOR UPDATE");
            $db->beginTransaction();
            try {
                $stmt->execute([hash('sha256', trim($data['qr'])), trim($data['qr']), trim($data['qr'])]);
                $identity = $stmt->fetch();
                if (!$identity || ($identity['usuario_id'] !== null &&
                    (!(int)$identity['activo'] || (int)$identity['bloqueado'] || $identity['codigo_qr'] === null ||
                        $identity['estado_carnet'] !== 'activo' ||
                        ($identity['vence_en'] !== null && $identity['vence_en'] < databaseDate($db)))) ||
                    ($identity['visitante_id'] !== null && $identity['salida_en'] !== null)) {
                    if ($identity && $identity['usuario_id'] !== null) {
                        $recipientIds=array_map('intval',array_column($db->query("SELECT u.id FROM usuarios u
                            JOIN roles r ON r.id=u.rol_id WHERE u.activo=1
                            AND r.nombre IN ('administrador','portero')")->fetchAll(),'id'));
                        $eventKey='porter-denied-'.hash('sha256',(string)$identity['usuario_id'].'-'.date('Y-m-d-H-i'));
                        $now=databaseDateTime($db);
                        $description=trim($identity['nombres'].' '.$identity['apellidos']).' intentó registrar acceso con carné bloqueado o vencido el '.$now.'.';
                        notifyUsers($db,$recipientIds,'Intento de acceso no autorizado',$description,'sistema',
                            (int)$porter['id'],$eventKey);
                        audit($db,(int)$porter['id'],'intento_acceso_bloqueado','accesos',null,
                            ['usuario_id'=>(int)$identity['usuario_id'],'fecha'=>$now]);
                        $db->commit();
                    } else {
                        $db->rollBack();
                    }
                    respond(['error' => 'Persona no autorizada: carné bloqueado, persona retirada o QR inválido.'], 403);
                }
                $visitor = $identity['visitante_id'] !== null;
                $personId = $visitor ? (int)$identity['visitante_id'] : (int)$identity['usuario_id'];
                if ($data['tipo'] === 'ingreso' && (int)$identity['acceso_abierto'] === 1) {
                    $db->rollBack();
                    respond(['error' => 'La persona ya tiene un ingreso abierto.'], 409);
                }
                if ($data['tipo'] === 'salida' && (int)$identity['acceso_abierto'] !== 1) {
                    $db->rollBack();
                    respond(['error' => 'No se encontró un ingreso abierto para registrar la salida.'], 409);
                }
                $db->prepare('INSERT INTO accesos (usuario_id,visitante_id,registrado_por,tipo,observacion)
                    VALUES (?,?,?,?,?)')->execute([
                    $visitor?null:$personId,$visitor?$personId:null,(int)$porter['id'],$data['tipo'],
                    isset($data['observacion'])?mb_substr(trim((string)$data['observacion']),0,500):null,
                ]);
                $accessId = (int)$db->lastInsertId();
                $db->prepare('UPDATE ingresos_diarios SET acceso_abierto=?,usado_en=NOW() WHERE id=?')
                    ->execute([$data['tipo']==='ingreso'?1:0,(int)$identity['id']]);
                if ($visitor) {
                    $db->prepare("UPDATE visitantes SET ingreso_en=CASE WHEN ?='ingreso' THEN NOW() ELSE ingreso_en END,
                        salida_en=CASE WHEN ?='salida' THEN NOW() ELSE salida_en END WHERE id=?")
                        ->execute([$data['tipo'],$data['tipo'],$personId]);
                }
                $expectedStmt=$db->prepare('SELECT e.id,e.serial,e.nombre FROM ingreso_diario_equipos ie
                    JOIN equipos e ON e.id=ie.equipo_id WHERE ie.ingreso_diario_id=?');
                $expectedStmt->execute([(int)$identity['id']]);
                $expected=$expectedStmt->fetchAll();
                $nameStmt=$db->prepare($visitor?'SELECT nombres FROM visitantes WHERE id=?':'SELECT CONCAT(nombres," ",apellidos) FROM usuarios WHERE id=?');
                $nameStmt->execute([$personId]);
                $name=(string)$nameStmt->fetchColumn();
                $now=databaseDateTime($db);
                $alert=[];
                if ($data['tipo']==='salida') {
                    $outIds=$data['equipo_ids']??[];
                    if (!is_array($outIds)) {
                        $db->rollBack();
                        respond(['error'=>'La lista de equipos de salida no es válida.'],422);
                    }
                    $outIds=array_values(array_unique(array_map('intval',$outIds)));
                    $unregisteredCodes=[];
                    $scannedCodes=$data['equipo_seriales']??'';
                    if (!is_string($scannedCodes)) {
                        $db->rollBack();
                        respond(['error'=>'Los seriales escaneados no tienen un formato válido.'],422);
                    }
                    $scannedCodes=array_values(array_unique(array_filter(array_map('trim',explode(',',$scannedCodes)))));
                    if($scannedCodes){
                        if(count($scannedCodes)>30||array_filter($scannedCodes,static fn($code)=>strlen($code)>100)){
                            $db->rollBack();
                            respond(['error'=>'Escanea hasta 30 seriales o QR de equipos.'],422);
                        }
                        $marks=implode(',',array_fill(0,count($scannedCodes),'?'));
                        $known=$db->prepare("SELECT id,serial,codigo_qr FROM equipos WHERE serial IN ({$marks}) OR codigo_qr IN ({$marks})");
                        $known->execute(array_merge($scannedCodes,$scannedCodes));
                        $knownRows=$known->fetchAll();
                        foreach($knownRows as $equipment){
                            $outIds[]=(int)$equipment['id'];
                        }
                        $knownCodes=[];
                        foreach($knownRows as $equipment){
                            $knownCodes[]=(string)$equipment['serial'];
                            $knownCodes[]=(string)$equipment['codigo_qr'];
                        }
                        $unregisteredCodes=array_values(array_diff($scannedCodes,$knownCodes));
                        if($unregisteredCodes)$outIds[]=0;
                    }
                    $outIds=array_values(array_unique($outIds));
                    $expectedIds=array_map(static fn($equipment)=>(int)$equipment['id'],$expected);
                    sort($expectedIds);
                    sort($outIds);
                    if ($expectedIds!==$outIds) {
                        $alert=['esperados'=>$expectedIds,'salida'=>$outIds,'seriales_no_registrados'=>$unregisteredCodes];
                        $recipients=array_map('intval',array_column($db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id
                            WHERE u.activo=1 AND r.nombre='administrador'")->fetchAll(),'id'));
                        $recipients[]=(int)$porter['id'];
                        notifyUsers($db,$recipients,
                            'Alerta de equipos en portería',
                            $porter['nombres'].' '.$porter['apellidos'].' registró una diferencia en los equipos de salida de '.
                            $name.' el '.$now.'. Seriales o QR sin registro: '.
                            ($unregisteredCodes?implode(', ',$unregisteredCodes):'ninguno').'. Acceso #'.$accessId.'.',
                            'sistema',(int)$porter['id'],'porter-equipment-mismatch-'.$accessId);
                    }
                }
                $movementEquipmentIds = $data['tipo']==='salida'
                    ? array_values(array_filter($outIds,static fn($id)=>$id>0))
                    : array_map(static fn($equipment)=>(int)$equipment['id'],$expected);
                $movementInsert=$db->prepare('INSERT INTO movimientos_equipos
                    (equipo_id,usuario_id,registrado_por,tipo,observacion) VALUES (?,?,?,?,?)');
                foreach(array_unique($movementEquipmentIds) as $equipmentId){
                    $movementInsert->execute([
                        $equipmentId,$visitor?null:$personId,(int)$porter['id'],$data['tipo'],
                        $alert?'Diferencia detectada en salida.':null,
                    ]);
                }
                audit($db,(int)$porter['id'],'registrar_'.$data['tipo'],'accesos',$accessId,[
                    'persona'=>$name,'visitante'=>$visitor,'fecha'=>$now,'equipos'=>$expected,'alerta_equipos'=>$alert,
                ]);
                if (!$visitor) {
                    notifyUsers($db,[$personId],
                        'Acceso registrado',
                        $name.' registró '.$data['tipo'].' el '.$now.'. Equipos: '.count($expected).'.',
                        'sistema',(int)$porter['id'],'porter-access-'.$accessId);
                }
                $db->commit();
                respond(['data'=>['id'=>$accessId,'tipo'=>$data['tipo'],'alerta_equipos'=>$alert]]);
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }
        }
        if (($parts[1] ?? '') === 'keys' && $method === 'GET') {
            $stmt=$db->query("SELECT e.id,e.codigo,e.nombre,
                    CASE WHEN e.estado='mantenimiento' THEN 'mantenimiento'
                        WHEN EXISTS (SELECT 1 FROM horarios h WHERE h.ambiente_id=e.id
                            AND h.dia_semana=WEEKDAY(CURRENT_DATE())+1
                            AND h.hora_inicio<=CURRENT_TIME() AND h.hora_fin>=CURRENT_TIME())
                        OR EXISTS (SELECT 1 FROM reservas r WHERE r.ambiente_id=e.id
                            AND r.estado='aprobada' AND r.inicio<=NOW() AND r.fin>=NOW())
                        THEN 'ocupado' ELSE 'disponible' END AS ambiente_estado,
                    IF(k.id IS NULL,'libre',IF(k.vence_en<NOW(),'vencida','llave prestada')) AS estado_llave,
                    k.id AS prestamo_id,k.instructor_id,CONCAT(u.nombres,' ',u.apellidos) AS instructor,
                    k.vence_en,k.devuelta_en
                FROM ambientes e LEFT JOIN llaves_ambientes k ON k.ambiente_id=e.id AND k.devuelta_en IS NULL
                LEFT JOIN usuarios u ON u.id=k.instructor_id
                ORDER BY e.nombre");
            respond(['data'=>$stmt->fetchAll()]);
        }
        if (($parts[1] ?? '') === 'keys' && $method === 'POST') {
            $data=body();
            $environmentId=$data['ambiente_id']??null;
            $qr=$data['qr']??null;
            if (!isPositiveIntegerValue($environmentId)||!is_string($qr)||trim($qr)==='') {
                respond(['error'=>'Selecciona un ambiente y escanea el carné de un instructor.'],422);
            }
            $person=$db->prepare("SELECT u.id FROM carnets c JOIN usuarios u ON u.id=c.usuario_id
                JOIN roles r ON r.id=u.rol_id WHERE c.codigo_qr=? AND c.estado='activo' AND r.nombre='instructor'
                AND u.activo=1 AND (c.vence_en IS NULL OR c.vence_en>=CURRENT_DATE())
                AND NOT EXISTS (SELECT 1 FROM bloqueos b WHERE b.usuario_id=u.id AND b.desbloqueado_en IS NULL)");
            $person->execute([trim($qr)]);
            $instructorId=$person->fetchColumn();
            if (!$instructorId) {
                respond(['error'=>'El carné está bloqueado, vencido o no pertenece a un instructor activo.'],403);
            }
            $db->beginTransaction();
            try {
            $environmentLock=$db->prepare('SELECT id FROM ambientes WHERE id=? FOR UPDATE');
            $environmentLock->execute([(int)$environmentId]);
            if (!$environmentLock->fetchColumn()) {
                $db->rollBack();
                respond(['error'=>'El ambiente seleccionado no existe.'],404);
            }
            $schedule=$db->prepare("SELECT 1 FROM horarios WHERE ambiente_id=? AND instructor_id=?
                AND dia_semana=WEEKDAY(CURRENT_DATE())+1
                AND hora_inicio<=CURRENT_TIME() AND hora_fin>=CURRENT_TIME()
                UNION ALL SELECT 1 FROM reservas WHERE ambiente_id=? AND usuario_id=?
                AND estado='aprobada' AND inicio<=NOW() AND fin>=NOW() LIMIT 1");
            $schedule->execute([(int)$environmentId,(int)$instructorId,(int)$environmentId,(int)$instructorId]);
            if (!$schedule->fetchColumn()) {
                $db->rollBack();
                respond(['error'=>'El instructor no tiene clase ni reserva activa en ese ambiente.'],403);
            }
            $open=$db->prepare("SELECT id FROM llaves_ambientes WHERE ambiente_id=? AND devuelta_en IS NULL FOR UPDATE");
            $open->execute([(int)$environmentId]);
            if ($open->fetchColumn()) {
                $db->rollBack();
                respond(['error'=>'La llave del ambiente ya está prestada.'],409);
            }
            $duration=configuredSystemSetting($db,'minutos_prestamo_llaves');
            $stmt=$db->prepare("INSERT INTO llaves_ambientes (ambiente_id,instructor_id,prestada_por,vence_en)
                VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL ? MINUTE))");
            $stmt->execute([(int)$environmentId,(int)$instructorId,(int)$porter['id'],$duration]);
            $id=(int)$db->lastInsertId();
            $details=$db->prepare("SELECT e.nombre AS ambiente,CONCAT(u.nombres,' ',u.apellidos) AS instructor,
                    k.prestada_en,k.vence_en FROM llaves_ambientes k
                JOIN ambientes e ON e.id=k.ambiente_id JOIN usuarios u ON u.id=k.instructor_id WHERE k.id=?");
            $details->execute([$id]);
            $loan=$details->fetch();
            notifyUsers($db,[(int)$instructorId],'Préstamo de llave registrado',
                $porter['nombres'].' '.$porter['apellidos'].' registró el préstamo de la llave de '.
                $loan['ambiente'].' para '.$loan['instructor'].' el '.$loan['prestada_en'].
                '. Debe devolverse antes de '.$loan['vence_en'].'.',
                'sistema',(int)$porter['id'],'porter-key-loan-'.$id);
            audit($db,(int)$porter['id'],'prestar_llave','llaves_ambientes',$id,
                ['ambiente_id'=>(int)$environmentId,'instructor_id'=>(int)$instructorId,'vence_en'=>$loan['vence_en']]);
            $db->commit();
            respond(['data'=>['id'=>$id,'vence_en'=>$loan['vence_en']]],201);
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }
        }
        if (($parts[1] ?? '') === 'keys' && isset($parts[2]) && ctype_digit($parts[2]) &&
            ($parts[3] ?? '') === 'return' && $method === 'POST') {
            $data=body();
            $qr=$data['qr']??null;
            if (!is_string($qr)||trim($qr)==='') {
                respond(['error'=>'Escanea el carné del instructor para devolver la llave.'],422);
            }
            $person=$db->prepare("SELECT u.id FROM carnets c JOIN usuarios u ON u.id=c.usuario_id
                JOIN roles r ON r.id=u.rol_id WHERE c.codigo_qr=? AND c.estado='activo' AND r.nombre='instructor'");
            $person->execute([trim($qr)]);
            $instructorId=$person->fetchColumn();
            if (!$instructorId) {
                respond(['error'=>'El carné no pertenece a un instructor.'],403);
            }
            $db->beginTransaction();
            try {
            $stmt=$db->prepare("UPDATE llaves_ambientes SET devuelta_en=NOW(),devuelta_por=?,estado='devuelta'
                WHERE id=? AND devuelta_en IS NULL AND instructor_id=?");
            $stmt->execute([(int)$porter['id'],(int)$parts[2],(int)$instructorId]);
            if (!$stmt->rowCount()) {
                $db->rollBack();
                respond(['error'=>'No se encontró un préstamo vigente de esa llave para el instructor escaneado.'],404);
            }
            audit($db,(int)$porter['id'],'devolver_llave','llaves_ambientes',(int)$parts[2],['instructor_id'=>(int)$instructorId]);
            notifyUsers($db,[(int)$instructorId],'Devolución de llave registrada',
                $porter['nombres'].' '.$porter['apellidos'].' registró la devolución de la llave del ambiente el '.
                databaseDateTime($db).'. Préstamo #'.(int)$parts[2].'.',
                'sistema',(int)$porter['id'],'porter-key-return-'.$parts[2]);
            $db->commit();
            respond(['data'=>['message'=>'Devolución registrada.']]);
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }
        }
        if (($parts[1] ?? '') === 'shift-log' && $method === 'GET') {
            $stmt=$db->query('SELECT n.id,n.turno,n.novedad,n.registrada_en,n.entregada_a,n.entregada_en,
                CONCAT(u.nombres," ",u.apellidos) AS portero FROM novedades_turno n JOIN usuarios u ON u.id=n.portero_id
                ORDER BY n.registrada_en DESC LIMIT 200');
            respond(['data'=>$stmt->fetchAll()]);
        }
        if (($parts[1] ?? '') === 'shift-log' && $method === 'POST') {
            $data=body();
            if (!is_string($data['turno']??null)||trim($data['turno'])===''||
                !is_string($data['novedad']??null)||trim($data['novedad'])==='') {
                respond(['error'=>'Indica el turno y la novedad.'],422);
            }
            $stmt=$db->prepare('INSERT INTO novedades_turno (portero_id,turno,novedad,entregada_a,entregada_en)
                VALUES (?,?,?,?,IF(? IS NULL,NULL,NOW()))');
            $handoff=isset($data['entregada_a'])?trim((string)$data['entregada_a']):null;
            $stmt->execute([(int)$porter['id'],trim($data['turno']),trim($data['novedad']),$handoff,$handoff]);
            $id=(int)$db->lastInsertId();
            audit($db,(int)$porter['id'],'registrar_novedad_turno','novedades_turno',$id);
            respond(['data'=>['id'=>$id]],201);
        }
        if (($parts[1] ?? '') === 'lost-property' && $method === 'GET') {
            $stmt=$db->query("SELECT o.id,o.descripcion,o.encontrado_en,o.encontrado_el,o.estado,o.entregado_a,o.entregado_en,
                CONCAT(u.nombres,' ',u.apellidos) AS encontrado_por FROM objetos_perdidos o
                JOIN usuarios u ON u.id=o.encontrado_por ORDER BY o.encontrado_el DESC LIMIT 200");
            respond(['data'=>$stmt->fetchAll()]);
        }
        if (($parts[1] ?? '') === 'lost-property' && $method === 'POST') {
            $data=body();
            if (!is_string($data['descripcion']??null)||trim($data['descripcion'])===''||
                !is_string($data['encontrado_en']??null)||trim($data['encontrado_en'])==='') {
                respond(['error'=>'Describe el objeto e indica dónde se encontró.'],422);
            }
            $stmt=$db->prepare('INSERT INTO objetos_perdidos (descripcion,encontrado_en,encontrado_por) VALUES (?,?,?)');
            $stmt->execute([trim($data['descripcion']),trim($data['encontrado_en']),(int)$porter['id']]);
            $id=(int)$db->lastInsertId();
            audit($db,(int)$porter['id'],'registrar_objeto_perdido','objetos_perdidos',$id);
            respond(['data'=>['id'=>$id]],201);
        }
        if (($parts[1] ?? '') === 'lost-property' && isset($parts[2]) && ctype_digit($parts[2]) &&
            ($parts[3] ?? '') === 'deliver' && $method === 'POST') {
            $data=body();
            if (!is_string($data['qr']??null)||trim($data['qr'])==='') {
                respond(['error'=>'Escanea el carné de la persona que recibe el objeto.'],422);
            }
            $person=$db->prepare("SELECT u.id FROM carnets c JOIN usuarios u ON u.id=c.usuario_id
                WHERE c.codigo_qr=? AND c.estado='activo' AND u.activo=1 AND (c.vence_en IS NULL OR c.vence_en>=CURRENT_DATE())
                AND NOT EXISTS (SELECT 1 FROM bloqueos b WHERE b.usuario_id=u.id AND b.desbloqueado_en IS NULL)");
            $person->execute([trim($data['qr'])]);
            $recipientId=$person->fetchColumn();
            if (!$recipientId) {
                respond(['error'=>'El carné de quien recibe el objeto está bloqueado, vencido o no existe.'],403);
            }
            $stmt=$db->prepare("UPDATE objetos_perdidos SET entregado_a=?,entregado_en=NOW(),estado='entregado'
                WHERE id=? AND estado='guardado'");
            $stmt->execute([(int)$recipientId,(int)$parts[2]]);
            if (!$stmt->rowCount()) {
                respond(['error'=>'El objeto no está disponible para entrega.'],409);
            }
            audit($db,(int)$porter['id'],'entregar_objeto_perdido','objetos_perdidos',(int)$parts[2],
                ['usuario_id'=>(int)$recipientId]);
            respond(['data'=>['message'=>'Entrega registrada.']]);
        }
        respond(['error'=>'Ruta de portería no encontrada.'],404);
    }
    if ($route === 'excuses' && isset($parts[1], $parts[2]) &&
        ctype_digit($parts[1]) && $parts[2] === 'review' && $method === 'POST') {
        $reviewer = requireRoles($user, ['administrador','instructor']);
        $data = body();
        $reviewNote = $data['observacion'] ?? null;
        if (is_string($reviewNote)) {
            $reviewNote = trim($reviewNote);
        }
        if (!in_array($data['estado'] ?? null, ['aprobada','rechazada'], true) ||
            !is_string($reviewNote) || $reviewNote === '' || mb_strlen($reviewNote) > 500) {
            respond(['error' => 'Selecciona aprobar o rechazar y escribe una observación de hasta 500 caracteres.'], 422);
        }
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT n.id, n.usuario_id, n.asistencia_id, n.estado_excusa,
                    a.ficha_id, a.horario_id, a.estado AS estado_asistencia, a.fecha
                    , CONCAT(s.nombres, ' ', s.apellidos) AS estudiante
                FROM novedades n JOIN asistencias a ON a.id = n.asistencia_id
                JOIN usuarios s ON s.id = n.usuario_id
                WHERE n.id = ? AND n.tipo = 'inasistencia' FOR UPDATE");
            $stmt->execute([(int)$parts[1]]);
            $excuse = $stmt->fetch();
            if (!$excuse || $excuse['estado_excusa'] !== 'pendiente') {
                $db->rollBack();
                respond(['error' => 'No existe una excusa pendiente para revisar.'], 404);
            }
            if ($reviewer['rol'] === 'instructor') {
                $assignment = $db->prepare('SELECT 1 FROM fichas_instructores WHERE ficha_id = ? AND instructor_id = ?');
                $assignment->execute([(int)$excuse['ficha_id'], (int)$reviewer['id']]);
                if (!$assignment->fetchColumn()) {
                    $db->rollBack();
                    respond(['error' => 'Solo puedes revisar excusas de tus fichas asignadas.'], 403);
                }
            }
            $db->prepare('UPDATE novedades SET estado_excusa = ?, revisado_por = ?, revisado_en = NOW() WHERE id = ?')
                ->execute([$data['estado'], (int)$reviewer['id'], (int)$excuse['id']]);
            if ($data['estado'] === 'aprobada') {
                $db->prepare("UPDATE asistencias
                    SET estado = 'permiso', estado_justificacion = 'justificada', registrado_por = ?
                    WHERE id = ?")
                    ->execute([(int)$reviewer['id'], (int)$excuse['asistencia_id']]);
                audit($db, (int)$reviewer['id'], 'actualizar_por_excusa_aprobada', 'asistencias',
                    (int)$excuse['asistencia_id'], [
                        'estado_anterior'=>$excuse['estado_asistencia'],
                        'estado_nuevo'=>'permiso',
                        'motivo_correccion'=>$reviewNote,
                    ]);
                notifyAttendance($db, (int)$excuse['usuario_id'], (int)$excuse['ficha_id'],
                    $excuse['horario_id'] === null ? null : (int)$excuse['horario_id'],
                    'permiso', (string)$excuse['fecha'], $reviewNote, (int)$reviewer['id'],
                    'aprobó la excusa y actualizó la asistencia');
            } else {
                $db->prepare("UPDATE asistencias SET estado_justificacion = 'injustificada' WHERE id = ?")
                    ->execute([(int)$excuse['asistencia_id']]);
                notifyAttendance($db, (int)$excuse['usuario_id'], (int)$excuse['ficha_id'],
                    $excuse['horario_id'] === null ? null : (int)$excuse['horario_id'],
                    'ausente', (string)$excuse['fecha'], $reviewNote, (int)$reviewer['id'],
                    'rechazó la excusa; la falta permanece registrada');
            }
            $status = $data['estado'] === 'aprobada' ? 'aprobada' : 'rechazada';
            audit($db, (int)$reviewer['id'], 'revisar_excusa', 'novedades', (int)$excuse['id'],
                ['estado'=>$status, 'observacion'=>$reviewNote, 'aprendiz'=>$excuse['estudiante'],
                    'fecha'=>$excuse['fecha'], 'horario_id'=>$excuse['horario_id']]);
            $db->commit();
            respond(['data' => ['estado'=>$status]]);
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }
    if ($route === 'excuses' && $method === 'GET' && !isset($parts[1])) {
        $reviewer = requireRoles($user, ['administrador','instructor']);
        $sql = "SELECT n.id, n.asistencia_id, n.usuario_id, n.descripcion, n.fecha,
                n.estado_excusa, n.revisado_en, n.soporte_ruta, a.fecha AS fecha_asistencia,
                f.codigo AS ficha_codigo, f.nombre AS ficha_nombre,
                CONCAT(s.nombres, ' ', s.apellidos) AS estudiante
            FROM novedades n
            JOIN asistencias a ON a.id = n.asistencia_id
            JOIN fichas f ON f.id = a.ficha_id
            JOIN usuarios s ON s.id = n.usuario_id
            WHERE n.tipo = 'inasistencia' AND n.estado_excusa IS NOT NULL";
        $params = [];
        if ($reviewer['rol'] === 'instructor') {
            $sql .= ' AND EXISTS (SELECT 1 FROM fichas_instructores fi
                WHERE fi.ficha_id = a.ficha_id AND fi.instructor_id = ?)';
            $params[] = (int)$reviewer['id'];
        }
        $sql .= " ORDER BY FIELD(n.estado_excusa, 'pendiente', 'aprobada', 'rechazada'), n.fecha DESC LIMIT 500";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        respond(['data' => $stmt->fetchAll()]);
    }
    if ($route === 'excuses' && isset($parts[1], $parts[2]) &&
        ctype_digit($parts[1]) && $parts[2] === 'file' && $method === 'GET') {
        $reviewer = requireRoles($user, ['administrador','instructor']);
        $sql = "SELECT n.soporte_ruta FROM novedades n
            JOIN asistencias a ON a.id = n.asistencia_id
            WHERE n.id = ? AND n.tipo = 'inasistencia' AND n.soporte_ruta IS NOT NULL";
        $params = [(int)$parts[1]];
        if ($reviewer['rol'] === 'instructor') {
            $sql .= ' AND EXISTS (SELECT 1 FROM fichas_instructores fi
                WHERE fi.ficha_id = a.ficha_id AND fi.instructor_id = ?)';
            $params[] = (int)$reviewer['id'];
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $fileName = $stmt->fetchColumn();
        if (!$fileName) {
            respond(['error' => 'No se encontró un soporte de una excusa autorizada.'], 404);
        }
        $path = __DIR__ . '/uploads/excuses/' . basename((string)$fileName);
        if (!is_file($path) || !is_readable($path)) {
            respond(['error' => 'El archivo de soporte no está disponible.'], 404);
        }
        $mimeInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($mimeInfo === false) {
            throw new RuntimeException('No se pudo validar el archivo de soporte.');
        }
        try {
            $mime = finfo_file($mimeInfo, $path);
        } finally {
            finfo_close($mimeInfo);
        }
        $safeExtensions = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($safeExtensions[$mime])) {
            respond(['error' => 'El archivo de soporte tiene un formato no permitido.'], 415);
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="soporte-excusa.' . $safeExtensions[$mime] . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
    if ($route === 'evaluations') {
        $evaluator = requireRoles($user, ['administrador','instructor']);
        if ($method === 'GET') {
            $sql = "SELECT j.id, j.estudiante_id, CONCAT(s.nombres, ' ', s.apellidos) AS estudiante,
                    r.id AS resultado_id, r.codigo AS resultado_codigo, r.descripcion AS resultado,
                    c.nombre AS competencia, p.nombre AS programa, j.juicio, j.evaluado_en,
                    CONCAT(e.nombres, ' ', e.apellidos) AS evaluador
                FROM juicios_resultados j
                JOIN usuarios s ON s.id = j.estudiante_id
                JOIN resultados_aprendizaje r ON r.id = j.resultado_id
                JOIN competencias c ON c.id = r.competencia_id
                JOIN programas p ON p.id = c.programa_id
                JOIN usuarios e ON e.id = j.evaluado_por";
            $params = [];
            if ($evaluator['rol'] === 'instructor') {
                $sql .= " WHERE EXISTS (
                    SELECT 1 FROM fichas_usuarios fu
                    JOIN fichas f ON f.id = fu.ficha_id
                    JOIN fichas_instructores fi ON fi.ficha_id = f.id AND fi.instructor_id = ?
                    WHERE fu.usuario_id = j.estudiante_id AND f.programa_id = p.id
                )";
                $params[] = (int)$evaluator['id'];
            }
            $sql .= ' ORDER BY j.evaluado_en DESC, j.id DESC LIMIT 1000';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            respond(['data' => $stmt->fetchAll()]);
        }
        if ($method === 'POST') {
            $data = body();
            $studentId = $data['estudiante_id'] ?? null;
            $resultId = $data['resultado_id'] ?? null;
            if (!isPositiveIntegerValue($studentId) || !isPositiveIntegerValue($resultId) ||
                !in_array($data['juicio'] ?? null, ['aprobado','no_aprobado','por_evaluar'], true)) {
                respond(['error' => 'Selecciona un aprendiz, resultado y juicio evaluativo válidos.'], 422);
            }
            $eligible = $db->prepare("SELECT 1 FROM usuarios s
                JOIN roles ro ON ro.id = s.rol_id AND ro.nombre = 'estudiante' AND s.activo = 1
                JOIN fichas_usuarios fu ON fu.usuario_id = s.id
                JOIN fichas f ON f.id = fu.ficha_id
                JOIN resultados_aprendizaje r ON r.id = ?
                JOIN competencias c ON c.id = r.competencia_id AND c.programa_id = f.programa_id
                WHERE s.id = ?" . ($evaluator['rol'] === 'instructor'
                    ? ' AND EXISTS (SELECT 1 FROM fichas_instructores fi WHERE fi.ficha_id = f.id AND fi.instructor_id = ?)'
                    : '') . ' LIMIT 1');
            $params = [(int)$resultId, (int)$studentId];
            if ($evaluator['rol'] === 'instructor') {
                $params[] = (int)$evaluator['id'];
            }
            $eligible->execute($params);
            if (!$eligible->fetchColumn()) {
                respond(['error' => 'El aprendiz debe estar inscrito en el programa del resultado y ser de una ficha asignada al evaluador.'], 403);
            }
            $db->beginTransaction();
            try {
                $db->prepare('INSERT INTO juicios_resultados (estudiante_id, resultado_id, juicio, evaluado_por) VALUES (?, ?, ?, ?)')
                    ->execute([(int)$studentId, (int)$resultId, $data['juicio'], (int)$evaluator['id']]);
                $evaluationId = (int)$db->lastInsertId();
                $result = $db->prepare("SELECT r.codigo, r.descripcion, c.nombre AS competencia
                    FROM resultados_aprendizaje r
                    JOIN competencias c ON c.id = r.competencia_id
                    WHERE r.id = ?");
                $result->execute([(int)$resultId]);
                $resultDetails = $result->fetch();
                $evaluatorNameStmt = $db->prepare("SELECT CONCAT(nombres, ' ', apellidos) FROM usuarios WHERE id = ?");
                $evaluatorNameStmt->execute([(int)$evaluator['id']]);
                $evaluatorName = (string)$evaluatorNameStmt->fetchColumn();
                $learnerNameStmt = $db->prepare("SELECT CONCAT(nombres, ' ', apellidos) FROM usuarios WHERE id = ?");
                $learnerNameStmt->execute([(int)$studentId]);
                $learnerName = (string)$learnerNameStmt->fetchColumn();
                notifyUsers($db, [(int)$studentId], 'Actualización de formación',
                    'Quién: ' . $evaluatorName . '. Qué: registró el juicio ' . str_replace('_', ' ', $data['juicio']) .
                    ' para ' . $learnerName . '. Resultado: ' . $resultDetails['codigo'] . ' · ' .
                    $resultDetails['descripcion'] . '. Competencia: ' . $resultDetails['competencia'] .
                    '. Fecha: ' . date('Y-m-d') . '. Sesión: no aplica (resultado de aprendizaje).',
                    'formacion', (int)$evaluator['id']);
                audit($db, (int)$evaluator['id'], 'registrar_juicio', 'juicios_resultados', $evaluationId,
                    ['estudiante_id'=>(int)$studentId, 'resultado_id'=>(int)$resultId, 'juicio'=>$data['juicio'],
                        'fecha'=>date('Y-m-d')]);
                $db->commit();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }
            respond(['data' => ['id'=>$evaluationId]], 201);
        }
        respond(['error' => 'Método no permitido para juicios evaluativos.'], 405);
    }
    if ($route === 'learner') {
        $student = requireRoles($user, ['estudiante']);
        $studentId = (int)$student['id'];
        $section = $parts[1] ?? '';
        $validLearnerRoute = match ($section) {
            'dashboard', 'schedule', 'attendance', 'profile' => count($parts) === 2,
            'excuses' => count($parts) === 2 ||
                count($parts) === 4 && ctype_digit($parts[2]) && $parts[3] === 'file',
            'notifications' => count($parts) === 2 ||
                count($parts) === 3 && $parts[2] === 'read-all' ||
                count($parts) === 4 && ctype_digit($parts[2]) && $parts[3] === 'read',
            'formation' => count($parts) === 2 || count($parts) === 3 && $parts[2] === 'pdf',
            default => false,
        };
        if (!$validLearnerRoute) {
            respond(['error' => 'Ruta del portal aprendiz no encontrada.'], 404);
        }

        if ($section === 'dashboard' && $method === 'GET') {
            $identity = $db->prepare("SELECT u.nombres, u.apellidos, f.codigo AS ficha_codigo,
                    COALESCE(p.nombre, f.programa) AS programa, f.trimestre
                FROM usuarios u
                LEFT JOIN fichas_usuarios fu ON fu.usuario_id = u.id
                LEFT JOIN fichas f ON f.id = fu.ficha_id
                LEFT JOIN programas p ON p.id = f.programa_id
                WHERE u.id = ?
                ORDER BY (f.fecha_inicio <= CURRENT_DATE() AND f.fecha_fin >= CURRENT_DATE()) DESC,
                    f.fecha_inicio DESC, f.id DESC LIMIT 1");
            $identity->execute([$studentId]);
            $profile = $identity->fetch();
            $summaryStmt = $db->prepare("SELECT COUNT(*) AS total,
                    COALESCE(SUM(estado = 'presente'), 0) AS presentes,
                    COALESCE(SUM(estado = 'ausente'), 0) AS ausencias,
                    COALESCE(SUM(estado = 'tarde'), 0) AS retrasos,
                    COALESCE(SUM(estado = 'permiso'), 0) AS permisos
                FROM asistencias WHERE usuario_id = ?");
            $summaryStmt->execute([$studentId]);
            $summary = $summaryStmt->fetch();
            $denominator = (int)$summary['total'] - (int)$summary['permisos'];
            $attended = (int)$summary['presentes'] + (int)$summary['retrasos'];
            $percentage = $denominator > 0 ? (int)round(100 * $attended / $denominator) : 0;
            $yellowThreshold = configuredSystemSetting($db, 'asistencia_alerta_amarilla');
            $redThreshold = configuredSystemSetting($db, 'asistencia_alerta_roja');
            $alert = $denominator === 0 || $percentage >= $yellowThreshold
                ? null
                : [
                    'nivel' => $percentage < $redThreshold ? 'roja' : 'amarilla',
                    'porcentaje' => $percentage,
                    'amarilla' => $yellowThreshold,
                    'roja' => $redThreshold,
                ];
            $unread = $db->prepare('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida_en IS NULL');
            $unread->execute([$studentId]);
            $classes = $db->prepare("SELECT h.dia_semana, h.hora_inicio, h.hora_fin, h.asignatura,
                    c.nombre AS competencia, a.nombre AS ambiente,
                    CONCAT(i.nombres, ' ', i.apellidos) AS instructor,
                    f.codigo AS ficha_codigo, COALESCE(p.nombre, f.programa) AS programa, f.trimestre
                FROM fichas_usuarios fu
                JOIN fichas f ON f.id = fu.ficha_id
                JOIN horarios h ON h.ficha_id = f.id AND h.dia_semana = WEEKDAY(CURRENT_DATE()) + 1
                LEFT JOIN competencias c ON c.id = h.competencia_id
                JOIN ambientes a ON a.id = h.ambiente_id
                JOIN usuarios i ON i.id = h.instructor_id
                LEFT JOIN programas p ON p.id = f.programa_id
                WHERE fu.usuario_id = ? AND f.estado = 'activa'
                ORDER BY h.hora_inicio, f.codigo");
            $classes->execute([$studentId]);
            $summary['total'] = (int)$summary['total'];
            foreach (['presentes','ausencias','retrasos','permisos'] as $field) {
                $summary[$field] = (int)$summary[$field];
            }
            respond(['data' => [
                'aprendiz' => $profile,
                'asistencia' => ['porcentaje'=>$percentage, 'alerta'=>$alert] + $summary,
                'notificaciones_sin_leer' => (int)$unread->fetchColumn(),
                'clases_hoy' => $classes->fetchAll(),
            ]]);
        }
        if ($section === 'schedule' && $method === 'GET') {
            $view = $_GET['view'] ?? 'week';
            if (!in_array($view, ['day','week'], true)) {
                respond(['error' => 'La vista del horario debe ser day o week.'], 422);
            }
            $date = (string)($_GET['date'] ?? date('Y-m-d'));
            $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
                respond(['error' => 'La fecha debe usar el formato YYYY-MM-DD.'], 422);
            }
            $sql = "SELECT h.dia_semana, h.hora_inicio, h.hora_fin, h.asignatura,
                    c.nombre AS competencia, a.nombre AS ambiente,
                    CONCAT(i.nombres, ' ', i.apellidos) AS instructor,
                    f.codigo AS ficha_codigo, f.nombre AS ficha_nombre,
                    COALESCE(p.nombre, f.programa) AS programa, f.trimestre
                FROM fichas_usuarios fu
                JOIN fichas f ON f.id = fu.ficha_id
                JOIN horarios h ON h.ficha_id = f.id
                LEFT JOIN competencias c ON c.id = h.competencia_id
                JOIN ambientes a ON a.id = h.ambiente_id
                JOIN usuarios i ON i.id = h.instructor_id
                LEFT JOIN programas p ON p.id = f.programa_id
                WHERE fu.usuario_id = ? AND f.estado = 'activa'";
            $params = [$studentId];
            if ($view === 'day') {
                $sql .= ' AND h.dia_semana = WEEKDAY(?) + 1';
                $params[] = $date;
            }
            $sql .= ' ORDER BY h.dia_semana, h.hora_inicio, f.codigo';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            respond(['data' => ['fecha'=>$date, 'vista'=>$view, 'clases'=>$stmt->fetchAll()]]);
        }
        if ($section === 'attendance' && $method === 'GET') {
            $stmt = $db->prepare("SELECT a.id, a.fecha, a.estado, a.estado_justificacion, a.observacion,
                    h.asignatura, c.nombre AS competencia, h.hora_inicio, h.hora_fin,
                    amb.nombre AS ambiente, CONCAT(i.nombres, ' ', i.apellidos) AS instructor,
                    f.codigo AS ficha_codigo, f.nombre AS ficha_nombre,
                    COALESCE(p.nombre, f.programa) AS programa, f.trimestre,
                    n.id AS excusa_id, n.estado_excusa, n.descripcion AS excusa_descripcion,
                    n.soporte_ruta
                FROM asistencias a
                JOIN fichas f ON f.id = a.ficha_id
                LEFT JOIN horarios h ON h.id = a.horario_id
                LEFT JOIN competencias c ON c.id = h.competencia_id
                LEFT JOIN ambientes amb ON amb.id = h.ambiente_id
                LEFT JOIN usuarios i ON i.id = h.instructor_id
                LEFT JOIN programas p ON p.id = f.programa_id
                LEFT JOIN novedades n ON n.id = (
                    SELECT n2.id FROM novedades n2
                    WHERE n2.asistencia_id = a.id AND n2.usuario_id = a.usuario_id AND n2.tipo = 'inasistencia'
                    ORDER BY n2.id DESC LIMIT 1
                )
                WHERE a.usuario_id = ?
                ORDER BY a.fecha DESC, h.hora_inicio DESC, a.id DESC LIMIT 1000");
            $stmt->execute([$studentId]);
            respond(['data' => $stmt->fetchAll()]);
        }
        if ($section === 'excuses' && $method === 'GET' && !isset($parts[2])) {
            $stmt = $db->prepare("SELECT n.id, n.asistencia_id, n.descripcion, n.fecha,
                    n.estado_excusa, n.soporte_ruta, n.revisado_en,
                    a.estado AS estado_asistencia, a.estado_justificacion, a.fecha AS fecha_asistencia
                FROM novedades n
                LEFT JOIN asistencias a ON a.id = n.asistencia_id
                WHERE n.usuario_id = ? AND n.tipo = 'inasistencia'
                ORDER BY n.fecha DESC, n.id DESC LIMIT 500");
            $stmt->execute([$studentId]);
            respond(['data' => $stmt->fetchAll()]);
        }
        if ($section === 'excuses' && $method === 'POST' && !isset($parts[2])) {
            $attendanceId = $_POST['asistencia_id'] ?? '';
            $description = trim((string)($_POST['descripcion'] ?? ''));
            if (!isPositiveIntegerValue($attendanceId) || $description === '' || mb_strlen($description) > 500) {
                respond(['error' => 'Selecciona una falta y escribe el motivo (máximo 500 caracteres).'], 422);
            }
            $file = $_FILES['soporte'] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
                !is_uploaded_file((string)($file['tmp_name'] ?? '')) ||
                (int)($file['size'] ?? 0) < 1 || (int)$file['size'] > 5242880) {
                respond(['error' => 'Adjunta un soporte PDF o una imagen JPG, PNG o WEBP de máximo 5 MB.'], 422);
            }
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($fileInfo === false) {
                throw new RuntimeException('No se pudo validar el soporte de la excusa.');
            }
            try {
                $mime = finfo_file($fileInfo, (string)$file['tmp_name']);
            } finally {
                finfo_close($fileInfo);
            }
            $extensions = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!isset($extensions[$mime])) {
                respond(['error' => 'El archivo debe ser un PDF, JPG, PNG o WEBP válido.'], 422);
            }
            $db->beginTransaction();
            $storedPath = null;
            try {
                $attendanceStmt = $db->prepare("SELECT a.id, a.usuario_id, a.ficha_id, a.horario_id, a.fecha, a.estado,
                        a.estado_justificacion, f.codigo AS ficha_codigo, h.asignatura,
                        h.hora_inicio, h.hora_fin, CONCAT(i.nombres, ' ', i.apellidos) AS instructor,
                        CONCAT(s.nombres, ' ', s.apellidos) AS estudiante
                    FROM asistencias a
                    JOIN fichas f ON f.id = a.ficha_id
                    JOIN usuarios s ON s.id = a.usuario_id
                    LEFT JOIN horarios h ON h.id = a.horario_id
                    LEFT JOIN usuarios i ON i.id = h.instructor_id
                    WHERE a.id = ? AND a.usuario_id = ? FOR UPDATE");
                $attendanceStmt->execute([(int)$attendanceId, $studentId]);
                $attendance = $attendanceStmt->fetch();
                if (!$attendance) {
                    $db->rollBack();
                    respond(['error' => 'No se encontró una falta propia para presentar la excusa.'], 404);
                }
                if ($attendance['estado'] !== 'ausente') {
                    $db->rollBack();
                    respond(['error' => 'Solo puedes presentar excusa para una falta que todavía figure como ausente.'], 409);
                }
                if ($attendance['estado_justificacion'] !== 'pendiente') {
                    $db->rollBack();
                    respond(['error' => 'Esta falta ya fue marcada como justificada o injustificada y no admite otra excusa.'], 409);
                }
                $elapsed = businessDaysSince($db, (string)$attendance['fecha']);
                $deadline = configuredSystemSetting($db, 'dias_habiles_excusa');
                if ($elapsed === null || $elapsed > $deadline) {
                    $db->rollBack();
                    respond(['error' => "El plazo de {$deadline} días hábiles para presentar esta excusa ya venció."], 409);
                }
                $existing = $db->prepare("SELECT id FROM novedades
                    WHERE asistencia_id = ? AND tipo = 'inasistencia'
                        AND estado_excusa IN ('pendiente','aprobada') LIMIT 1");
                $existing->execute([(int)$attendanceId]);
                if ($existing->fetchColumn()) {
                    $db->rollBack();
                    respond(['error' => 'Esta falta ya tiene una excusa pendiente o aprobada.'], 409);
                }
                $directory = __DIR__ . '/uploads/excuses';
                if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                    throw new RuntimeException('No se pudo preparar el almacenamiento seguro de soportes.');
                }
                $storedName = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
                $storedPath = $directory . DIRECTORY_SEPARATOR . $storedName;
                if (!move_uploaded_file((string)$file['tmp_name'], $storedPath)) {
                    throw new RuntimeException('No se pudo guardar el soporte de la excusa.');
                }
                $db->prepare("INSERT INTO novedades
                    (usuario_id, asistencia_id, tipo, descripcion, estado_excusa, soporte_ruta, fecha, registrado_por)
                    VALUES (?, ?, 'inasistencia', ?, 'pendiente', ?, ?, ?)")
                    ->execute([$studentId, (int)$attendanceId, $description, $storedName, $attendance['fecha'], $studentId]);
                $excuseId = (int)$db->lastInsertId();
                $reviewers = $db->prepare("SELECT u.id FROM usuarios u JOIN roles r ON r.id = u.rol_id
                    WHERE u.activo = 1 AND r.nombre = 'administrador'
                    UNION
                    SELECT fi.instructor_id FROM fichas_instructores fi
                    JOIN asistencias a ON a.ficha_id = fi.ficha_id
                    WHERE a.id = ?");
                $reviewers->execute([(int)$attendanceId]);
                $session = $attendance['asignatura']
                    ? $attendance['asignatura'] . ' · ' . substr((string)$attendance['hora_inicio'], 0, 5) . '–' .
                        substr((string)$attendance['hora_fin'], 0, 5)
                    : 'sin sesión específica';
                notifyUsers($db, array_merge($reviewers->fetchAll(PDO::FETCH_COLUMN), [$studentId]),
                    'Nueva excusa pendiente',
                    'Quién: ' . $attendance['estudiante'] . '. Qué: presentó una excusa con soporte. Fecha de registro: ' .
                        date('Y-m-d') . '. Fecha de la falta: ' . $attendance['fecha'] . '. Sesión: ' . $session .
                        '. Ficha: ' . $attendance['ficha_codigo'] . '. Instructor: ' .
                        ($attendance['instructor'] ?: 'sin instructor asignado') . '.',
                    'excusa', $studentId);
                audit($db, $studentId, 'presentar_excusa', 'novedades', $excuseId, [
                    'fecha_falta'=>$attendance['fecha'],
                    'horario_id'=>$attendance['horario_id'],
                    'ficha_id'=>$attendance['ficha_id'],
                ]);
                $db->commit();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                if (is_string($storedPath) && is_file($storedPath) && !unlink($storedPath)) {
                    error_log('[gestion-institucional] Could not remove orphaned excuse support file.');
                }
                throw $exception;
            }
            respond(['data' => ['id'=>$excuseId, 'estado'=>'pendiente']], 201);
        }
        if ($section === 'excuses' && isset($parts[2], $parts[3]) &&
            $parts[3] === 'file' && $method === 'GET' && ctype_digit($parts[2])) {
            $stmt = $db->prepare("SELECT soporte_ruta FROM novedades
                WHERE id = ? AND usuario_id = ? AND tipo = 'inasistencia' AND soporte_ruta IS NOT NULL");
            $stmt->execute([(int)$parts[2], $studentId]);
            $fileName = $stmt->fetchColumn();
            if (!$fileName) {
                respond(['error' => 'No se encontró el soporte de una excusa propia.'], 404);
            }
            $path = __DIR__ . '/uploads/excuses/' . basename((string)$fileName);
            if (!is_file($path) || !is_readable($path)) {
                respond(['error' => 'El archivo de soporte no está disponible.'], 404);
            }
            $mimeInfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($mimeInfo === false) {
                throw new RuntimeException('No se pudo validar el archivo de soporte.');
            }
            try {
                $mime = finfo_file($mimeInfo, $path);
            } finally {
                finfo_close($mimeInfo);
            }
            $safeExtensions = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!isset($safeExtensions[$mime])) {
                respond(['error' => 'El archivo de soporte tiene un formato no permitido.'], 415);
            }
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($path));
            header('Content-Disposition: inline; filename="soporte-excusa.' . $safeExtensions[$mime] . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($path);
            exit;
        }
        if ($section === 'notifications' && $method === 'GET') {
            $stmt = $db->prepare("SELECT n.id, n.titulo, n.mensaje, n.leida_en, n.creado_en,
                    COALESCE(n.tipo,
                        CASE WHEN n.titulo LIKE 'Actualización de asistencia%' THEN 'asistencia'
                            WHEN n.titulo LIKE '%excusa%' THEN 'excusa'
                            WHEN n.titulo LIKE '%formación%' OR n.titulo LIKE '%competencia%' THEN 'formacion'
                            ELSE 'sistema' END) AS tipo,
                    COALESCE(CONCAT(s.nombres, ' ', s.apellidos), 'Sistema') AS remitente
                FROM notificaciones n LEFT JOIN usuarios s ON s.id = n.remitente_id
                WHERE n.usuario_id = ? ORDER BY n.creado_en DESC, n.id DESC LIMIT 500");
            $stmt->execute([$studentId]);
            respond(['data' => $stmt->fetchAll()]);
        }
        if ($section === 'notifications' && isset($parts[2], $parts[3]) &&
            $parts[3] === 'read' && ctype_digit($parts[2]) && $method === 'PATCH') {
            $stmt = $db->prepare('UPDATE notificaciones SET leida_en = COALESCE(leida_en, NOW()) WHERE id = ? AND usuario_id = ?');
            $stmt->execute([(int)$parts[2], $studentId]);
            if (!$stmt->rowCount()) {
                $exists = $db->prepare('SELECT 1 FROM notificaciones WHERE id = ? AND usuario_id = ?');
                $exists->execute([(int)$parts[2], $studentId]);
                if (!$exists->fetchColumn()) {
                    respond(['error' => 'No se encontró esa notificación.'], 404);
                }
            }
            respond(['data' => ['leida'=>true]]);
        }
        if ($section === 'notifications' && ($parts[2] ?? '') === 'read-all' && $method === 'POST') {
            $db->prepare('UPDATE notificaciones SET leida_en = NOW() WHERE usuario_id = ? AND leida_en IS NULL')
                ->execute([$studentId]);
            respond(['data' => ['leidas'=>$db->query('SELECT ROW_COUNT()')->fetchColumn()]]);
        }
        if ($section === 'profile' && $method === 'GET') {
            $stmt = $db->prepare('SELECT correo, telefono FROM usuarios WHERE id = ?');
            $stmt->execute([$studentId]);
            respond(['data' => $stmt->fetch()]);
        }
        if ($section === 'profile' && $method === 'PATCH') {
            $data = body();
            $allowed = ['correo','telefono','password','pin','current_password'];
            if (!$data || array_diff(array_keys($data), $allowed) ||
                !array_intersect(array_keys($data), ['correo','telefono','password','pin'])) {
                respond(['error' => 'Solo puedes cambiar correo, celular, contraseña o PIN.'], 422);
            }
            $updates = [];
            $pinHash = null;
            if (array_key_exists('correo', $data)) {
                if (!is_string($data['correo']) || !filter_var(trim($data['correo']), FILTER_VALIDATE_EMAIL) ||
                    strlen(trim($data['correo'])) > 190) {
                    respond(['error' => 'Ingresa un correo electrónico válido de máximo 190 caracteres.'], 422);
                }
                $duplicate = $db->prepare('SELECT 1 FROM usuarios WHERE correo = ? AND id <> ?');
                $duplicate->execute([trim($data['correo']), $studentId]);
                if ($duplicate->fetchColumn()) {
                    respond(['error' => 'Ese correo ya está asociado a otra cuenta.'], 409);
                }
                $updates['correo'] = trim($data['correo']);
            }
            if (array_key_exists('telefono', $data)) {
                if (!is_string($data['telefono']) || mb_strlen(trim($data['telefono'])) > 30) {
                    respond(['error' => 'El celular debe tener máximo 30 caracteres.'], 422);
                }
                $updates['telefono'] = trim($data['telefono']) ?: null;
            }
            if (array_key_exists('password', $data)) {
                if (!is_string($data['password']) || strlen($data['password']) < 8) {
                    respond(['error' => 'La contraseña debe tener al menos 8 caracteres.'], 422);
                }
                $updates['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            if (array_key_exists('pin', $data)) {
                if (!is_string($data['pin']) || !preg_match('/^\d{4,6}$/', $data['pin'])) {
                    respond(['error' => 'El PIN debe tener de 4 a 6 números.'], 422);
                }
                $pinHash = password_hash($data['pin'], PASSWORD_DEFAULT);
            }
            if (isset($updates['password_hash']) || $pinHash !== null) {
                $confirmation = $data['current_password'] ?? null;
                $passwordStmt = $db->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
                $passwordStmt->execute([$studentId]);
                if (!is_string($confirmation) || !password_verify($confirmation, (string)$passwordStmt->fetchColumn())) {
                    respond(['error' => 'Confirma tu contraseña actual para cambiar credenciales.'], 403);
                }
            }
            if (!$updates && $pinHash === null) {
                respond(['error' => 'No hay cambios para guardar.'], 422);
            }
            $db->beginTransaction();
            try {
                $fields = array_keys($updates);
                if ($fields) {
                    $assignments = implode(', ', array_map(static fn($field) => "{$field} = ?", $fields));
                    $db->prepare("UPDATE usuarios SET {$assignments} WHERE id = ?")
                        ->execute([...array_values($updates), $studentId]);
                }
                if ($pinHash !== null) {
                    $cardUpdate = $db->prepare('UPDATE carnets SET pin_hash = ?, pin_intentos_fallidos = 0, pin_bloqueado_hasta = NULL WHERE usuario_id = ?');
                    $cardUpdate->execute([$pinHash, $studentId]);
                    if (!$cardUpdate->rowCount()) {
                        $card = $db->prepare('SELECT 1 FROM carnets WHERE usuario_id = ?');
                        $card->execute([$studentId]);
                        if (!$card->fetchColumn()) {
                            throw new RuntimeException('No se encontró un carné al cual asociar el nuevo PIN.');
                        }
                    }
                }
                audit($db, $studentId, 'actualizar_perfil_aprendiz', 'usuarios', $studentId,
                    ['campos'=>array_values(array_merge(
                        array_diff($fields, ['password_hash']),
                        $pinHash !== null ? ['pin'] : []
                    ))]);
                $db->commit();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }
            respond(['data' => ['message'=>'Perfil actualizado.']]);
        }
        if ($section === 'formation' && $method === 'GET' && !isset($parts[2])) {
            $stmt = $db->prepare("SELECT p.id AS programa_id, p.codigo AS programa_codigo,
                    p.nombre AS programa, f.codigo AS ficha_codigo, f.trimestre,
                    c.id AS competencia_id, c.codigo AS competencia_codigo, c.nombre AS competencia,
                    r.id AS resultado_id, r.codigo AS resultado_codigo, r.descripcion AS resultado,
                    COALESCE(latest.juicio, 'por_evaluar') AS juicio, latest.evaluado_en
                FROM fichas_usuarios fu
                JOIN fichas f ON f.id = fu.ficha_id
                JOIN programas p ON p.id = f.programa_id
                JOIN competencias c ON c.programa_id = p.id
                JOIN resultados_aprendizaje r ON r.competencia_id = c.id
                LEFT JOIN (
                    SELECT j.estudiante_id, j.resultado_id, j.juicio, j.evaluado_en
                    FROM juicios_resultados j
                    JOIN (
                        SELECT estudiante_id, resultado_id, MAX(id) AS max_id
                        FROM juicios_resultados GROUP BY estudiante_id, resultado_id
                    ) latest_id ON latest_id.max_id = j.id
                ) latest ON latest.estudiante_id = fu.usuario_id AND latest.resultado_id = r.id
                WHERE fu.usuario_id = ?
                ORDER BY p.nombre, f.codigo, c.nombre, r.codigo");
            $stmt->execute([$studentId]);
            respond(['data' => $stmt->fetchAll()]);
        }
        if ($section === 'formation' && ($parts[2] ?? '') === 'pdf' && $method === 'GET') {
            if (!class_exists(\Dompdf\Dompdf::class)) {
                respond(['error' => 'No está instalada la dependencia Dompdf para generar el historial PDF.'], 503);
            }
            $history = $db->prepare("SELECT p.nombre AS programa, f.codigo AS ficha, f.trimestre,
                    c.nombre AS competencia, r.codigo AS resultado_codigo, r.descripcion AS resultado,
                    j.juicio, j.evaluado_en, CONCAT(e.nombres, ' ', e.apellidos) AS evaluador
                FROM juicios_resultados j
                JOIN resultados_aprendizaje r ON r.id = j.resultado_id
                JOIN competencias c ON c.id = r.competencia_id
                JOIN programas p ON p.id = c.programa_id
                JOIN usuarios e ON e.id = j.evaluado_por
                JOIN fichas_usuarios fu ON fu.usuario_id = j.estudiante_id
                JOIN fichas f ON f.id = fu.ficha_id AND f.programa_id = p.id
                WHERE j.estudiante_id = ?
                ORDER BY j.evaluado_en DESC, j.id DESC");
            $history->execute([$studentId]);
            $identity = htmlspecialchars($student['nombres'] . ' ' . $student['apellidos'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = '<meta charset="utf-8"><h1>Historial académico</h1><p>Aprendiz: ' . $identity . '</p><table border="1" cellspacing="0" cellpadding="5"><thead><tr><th>Programa</th><th>Ficha / trimestre</th><th>Competencia</th><th>Código de resultado</th><th>Descripción</th><th>Juicio</th><th>Fecha</th><th>Evaluador</th></tr></thead><tbody>';
            foreach ($history->fetchAll() as $row) {
                $html .= '<tr>';
                foreach (['programa','ficha','competencia','resultado_codigo','resultado','juicio','evaluado_en','evaluador'] as $field) {
                    $value = (string)($row[$field] ?? '');
                    if ($field === 'ficha' && $row['trimestre'] !== null) {
                        $value .= ' · Trimestre ' . (int)$row['trimestre'];
                    }
                    $html .= '<td>' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $pdf = new \Dompdf\Dompdf();
            $pdf->loadHtml($html, 'UTF-8');
            $pdf->setPaper('A4', 'landscape');
            $pdf->render();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="historial-academico.pdf"');
            echo $pdf->output();
            exit;
        }
        respond(['error' => 'Ruta del portal aprendiz no encontrada.'], 404);
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
    if ($route === 'settings') {
        $actor = requireRoles($user, ['administrador']);
        if ($method === 'GET') {
            respond(['data' => systemSettings($db)]);
        }
        if ($method === 'PUT') {
            $data = body();
            $limits = [
                'asistencia_alerta_amarilla' => [1, 100],
                'asistencia_alerta_roja' => [1, 99],
                'dias_habiles_excusa' => [1, 30],
                'horas_correccion_asistencia' => [1, 720],
                'pin_intentos_maximos' => [1, 10],
                'pin_bloqueo_minutos' => [1, 120],
                'minutos_prestamo_llaves' => [1, 1440],
            ];
            if (array_diff(array_keys($data), array_keys($limits)) || array_diff(array_keys($limits), array_keys($data))) {
                respond(['error' => 'La configuración enviada está incompleta o contiene opciones no admitidas.'], 422);
            }
            $values = [];
            foreach ($limits as $key => [$minimum, $maximum]) {
                $value = $data[$key];
                if (!is_int($value) || $value < $minimum || $value > $maximum) {
                    respond(['error' => "El valor de {$key} debe estar entre {$minimum} y {$maximum}."], 422);
                }
                $values[$key] = $value;
            }
            if ($values['asistencia_alerta_roja'] >= $values['asistencia_alerta_amarilla']) {
                respond(['error' => 'La alerta roja debe ser menor que la alerta amarilla.'], 422);
            }
            $db->beginTransaction();
            try {
                $save = $db->prepare('INSERT INTO configuracion_sistema (clave, valor, actualizado_por)
                    VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado_por = VALUES(actualizado_por)');
                foreach ($values as $key => $value) {
                    $save->execute([$key, $value, (int)$actor['id']]);
                }
                audit($db, (int)$actor['id'], 'actualizar_configuracion', 'configuracion_sistema', null, $values);
                $db->commit();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }
            respond(['data' => ['settings' => systemSettings($db), 'message' => 'Configuración guardada.']]);
        }
        respond(['error' => 'Método no permitido para la configuración.'], 405);
    }
    if ($route === 'audit-log' && $method === 'GET') {
        requireRoles($user, ['administrador']);
        $stmt = $db->query("SELECT a.id, a.fecha_hora,
                COALESCE(CONCAT(u.nombres, ' ', u.apellidos), 'Usuario eliminado') AS actor,
                a.accion, a.entidad, a.entidad_id, CAST(a.detalle AS CHAR) AS detalle
            FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id
            ORDER BY a.fecha_hora DESC, a.id DESC LIMIT 500");
        respond(['data' => $stmt->fetchAll()]);
    }

    $roles = [
        'users' => ['administrador'], 'ficha-assignments' => ['administrador'],
        'programs' => ['administrador'], 'competencies' => ['administrador'],
        'learning-outcomes' => ['administrador'], 'academic-calendar' => ['administrador'],
        'environments' => ['administrador','instructor'],
        'fichas' => ['administrador'], 'enrollments' => ['administrador'], 'cards' => ['administrador','estudiante'], 'maintenance' => ['administrador'],
        'schedules' => ['administrador','instructor','estudiante'],
        'reservations' => ['administrador'], 'attendance' => ['administrador','instructor','estudiante'],
        'novelties' => ['administrador','instructor'], 'accesses' => ['administrador'],
        'visitors' => ['administrador'], 'equipment' => ['administrador'],
        'equipment-movements' => ['administrador'], 'loans' => ['administrador'],
        'temporary-permits' => ['administrador'], 'notifications' => ['administrador','instructor','estudiante'],
        'blocks' => ['administrador'],
    ];
    if ($route === 'attendance-corrections') {
        $actor = requireRoles($user, ['administrador','coordinador']);
        if ($method === 'GET') {
            $stmt = $db->query("SELECT c.id, c.asistencia_id, c.estado_anterior, c.observacion_anterior,
                    c.estado_propuesto, c.observacion_propuesta, c.motivo, c.estado,
                    c.creada_en, c.revisada_en, c.respuesta, a.fecha,
                    CONCAT(s.nombres, ' ', s.apellidos) AS aprendiz, s.documento,
                    f.codigo AS ficha, CONCAT(q.nombres, ' ', q.apellidos) AS solicitante,
                    CONCAT(r.nombres, ' ', r.apellidos) AS revisor
                FROM solicitudes_correccion_asistencia c
                JOIN asistencias a ON a.id = c.asistencia_id
                JOIN usuarios s ON s.id = a.usuario_id
                JOIN fichas f ON f.id = a.ficha_id
                JOIN usuarios q ON q.id = c.solicitada_por
                LEFT JOIN usuarios r ON r.id = c.revisada_por
                ORDER BY (c.estado = 'pendiente') DESC, c.creada_en DESC, c.id DESC LIMIT 500");
            respond(['data' => $stmt->fetchAll()]);
        }
        if (isset($parts[1], $parts[2]) && ctype_digit($parts[1]) &&
            $parts[2] === 'review' && $method === 'POST') {
            $requestId = (int)$parts[1];
            $data = body();
            $decision = $data['decision'] ?? '';
            $response = isset($data['respuesta']) && is_string($data['respuesta']) ? trim($data['respuesta']) : '';
            if (!in_array($decision, ['aprobada','rechazada'], true) || $response === '' || mb_strlen($response) > 500) {
                respond(['error' => 'Selecciona una decisión e indica una respuesta de máximo 500 caracteres.'], 422);
            }
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("SELECT c.*, a.usuario_id, a.ficha_id, a.horario_id, a.fecha, a.estado AS estado_actual,
                        a.observacion AS observacion_actual, h.asignatura, h.hora_inicio, h.hora_fin
                    FROM solicitudes_correccion_asistencia c
                    JOIN asistencias a ON a.id = c.asistencia_id
                    LEFT JOIN horarios h ON h.id = a.horario_id
                    WHERE c.id = ? AND c.estado = 'pendiente' FOR UPDATE");
                $stmt->execute([$requestId]);
                $request = $stmt->fetch();
                if (!$request) {
                    $db->rollBack();
                    respond(['error' => 'La solicitud no existe o ya fue revisada.'], 404);
                }
                if ($decision === 'aprobada') {
                    if ($request['estado_actual'] !== $request['estado_anterior'] ||
                        $request['observacion_actual'] !== $request['observacion_anterior']) {
                        $db->rollBack();
                        respond(['error' => 'La asistencia cambió mientras se revisaba la solicitud. Actualiza la lista antes de decidir.'], 409);
                    }
                    $db->prepare('UPDATE asistencias SET estado = ?, observacion = ?, estado_justificacion = ? WHERE id = ?')
                        ->execute([$request['estado_propuesto'], $request['observacion_propuesta'],
                            $request['estado_propuesto'] === 'permiso' ? 'justificada' : 'pendiente',
                            $request['asistencia_id']]);
                }
                $db->prepare("UPDATE solicitudes_correccion_asistencia
                    SET estado = ?, revisada_por = ?, revisada_en = NOW(), respuesta = ? WHERE id = ?")
                    ->execute([$decision, $actor['id'], $response, $requestId]);
                audit($db, (int)$actor['id'], 'revisar_correccion_asistencia', 'asistencias',
                    (int)$request['asistencia_id'], [
                        'solicitud_id'=>$requestId,
                        'decision'=>$decision,
                        'motivo'=>$request['motivo'],
                        'respuesta'=>$response,
                        'estado_anterior'=>$request['estado_anterior'],
                        'estado_nuevo'=>$request['estado_propuesto'],
                    ]);
                if ($decision === 'aprobada') {
                    notifyAttendance($db, (int)$request['usuario_id'], (int)$request['ficha_id'],
                        $request['horario_id'] === null ? null : (int)$request['horario_id'],
                        $request['estado_propuesto'], $request['fecha'], $request['observacion_propuesta'],
                        (int)$actor['id'], 'aprobó una corrección de asistencia');
                } else {
                    $reviewerStmt = $db->prepare("SELECT CONCAT(nombres, ' ', apellidos) FROM usuarios WHERE id = ?");
                    $reviewerStmt->execute([(int)$actor['id']]);
                    $session = $request['asignatura']
                        ? $request['asignatura'] . ' de ' . substr((string)$request['hora_inicio'], 0, 5) .
                            ' a ' . substr((string)$request['hora_fin'], 0, 5)
                        : 'registro general sin sesión';
                    notifyUsers($db, [(int)$request['usuario_id']], 'Corrección de asistencia rechazada',
                        'Quién: ' . $reviewerStmt->fetchColumn() . '. Qué: rechazó la corrección de asistencia. Fecha: ' .
                            $request['fecha'] . '. Sesión: ' . $session . '. Motivo: ' . $response,
                        'asistencia', (int)$actor['id']);
                }
                $db->commit();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }
            respond(['data' => ['revisada' => true]]);
        }
        respond(['error' => 'Método no permitido para solicitudes de corrección.'], 405);
    }
    if ($route === 'quarter-closes') {
        if ($method === 'GET') {
            requireRoles($user, ['administrador','coordinador']);
            $stmt = $db->query("SELECT c.id, c.ficha_id, f.codigo AS ficha, f.nombre,
                    c.trimestre, c.desde, c.hasta, CAST(c.resumen_asistencia AS CHAR) AS resumen_asistencia,
                    CAST(c.resumen_juicios AS CHAR) AS resumen_juicios, c.cerrado_en,
                    CONCAT(u.nombres, ' ', u.apellidos) AS responsable
                FROM cierres_trimestre c JOIN fichas f ON f.id = c.ficha_id
                JOIN usuarios u ON u.id = c.cerrado_por
                ORDER BY c.cerrado_en DESC, c.id DESC LIMIT 500");
            respond(['data' => $stmt->fetchAll()]);
        }
        if ($method === 'POST' && isset($parts[1]) && ctype_digit($parts[1])) {
            $actor = requireRoles($user, ['administrador']);
            $fichaId = (int)$parts[1];
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("SELECT f.trimestre, f.fecha_inicio, f.estado, p.duracion_trimestres
                    FROM fichas f LEFT JOIN programas p ON p.id = f.programa_id WHERE f.id = ? FOR UPDATE");
                $stmt->execute([$fichaId]);
                $ficha = $stmt->fetch();
                if (!$ficha) {
                    $db->rollBack();
                    respond(['error' => 'Ficha no encontrada.'], 404);
                }
                if ($ficha['estado'] !== 'activa' || $ficha['trimestre'] === null) {
                    $db->rollBack();
                    respond(['error' => 'Solo se puede cerrar el trimestre de una ficha activa con trimestre definido.'], 409);
                }
                if ((int)($ficha['duracion_trimestres'] ?? 0) < 1) {
                    $db->rollBack();
                    respond(['error' => 'Asigna a la ficha un programa con duración definida antes de cerrarla.'], 409);
                }
                $trimestre = (int)$ficha['trimestre'];
                $previousClose = $db->prepare('SELECT MAX(hasta) FROM cierres_trimestre WHERE ficha_id = ?');
                $previousClose->execute([$fichaId]);
                $lastClosedDate = $previousClose->fetchColumn();
                $desde = $lastClosedDate
                    ? (new DateTimeImmutable($lastClosedDate))->modify('+1 day')->format('Y-m-d')
                    : $ficha['fecha_inicio'];
                $asistenciaStmt = $db->prepare("SELECT COUNT(*) AS registros,
                        SUM(estado = 'presente') AS presentes, SUM(estado = 'tarde') AS tardanzas,
                        SUM(estado = 'ausente') AS ausencias, SUM(estado = 'permiso') AS permisos
                    FROM asistencias WHERE ficha_id = ? AND fecha <= CURDATE()
                        AND (? IS NULL OR fecha >= ?)");
                $asistenciaStmt->execute([$fichaId, $desde, $desde]);
                $attendanceSummary = $asistenciaStmt->fetch() ?: [];
                $gradesStmt = $db->prepare("SELECT COUNT(*) AS evaluaciones,
                        SUM(j.juicio = 'aprobado') AS aprobados,
                        SUM(j.juicio = 'no_aprobado') AS no_aprobados,
                        SUM(j.juicio = 'por_evaluar') AS por_evaluar
                    FROM juicios_resultados j
                    JOIN fichas_usuarios fu ON fu.usuario_id = j.estudiante_id AND fu.ficha_id = ?
                    WHERE j.evaluado_en <= NOW() AND (? IS NULL OR DATE(j.evaluado_en) >= ?)");
                $gradesStmt->execute([$fichaId, $desde, $desde]);
                $gradeSummary = $gradesStmt->fetch() ?: [];
                $db->prepare("INSERT INTO cierres_trimestre
                    (ficha_id, trimestre, desde, hasta, resumen_asistencia, resumen_juicios, cerrado_por)
                    VALUES (?, ?, ?, CURDATE(), ?, ?, ?)")
                    ->execute([$fichaId, $trimestre, $desde,
                        json_encode($attendanceSummary, JSON_THROW_ON_ERROR),
                        json_encode($gradeSummary, JSON_THROW_ON_ERROR), $actor['id']]);
                $duration = (int)($ficha['duracion_trimestres'] ?? 0);
                if ($duration > 0 && $trimestre >= $duration) {
                    $db->prepare("UPDATE fichas SET estado = 'finalizada' WHERE id = ?")->execute([$fichaId]);
                } else {
                    $db->prepare('UPDATE fichas SET trimestre = trimestre + 1 WHERE id = ?')->execute([$fichaId]);
                }
                audit($db, (int)$actor['id'], 'cerrar_trimestre', 'fichas', $fichaId, [
                    'trimestre'=>$trimestre, 'hasta'=>date('Y-m-d'),
                    'resumen_asistencia'=>$attendanceSummary, 'resumen_juicios'=>$gradeSummary,
                ]);
                $db->commit();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }
            respond(['data' => ['cerrado' => true, 'trimestre' => $trimestre,
                'siguiente_trimestre' => $duration > 0 && $trimestre >= $duration ? null : $trimestre + 1]]);
        }
        respond(['error' => 'Método no permitido para cierres de trimestre.'], 405);
    }
    if ($route === 'productive-stage') {
        if (isset($parts[1], $parts[2]) && ctype_digit($parts[1]) &&
            $parts[2] === 'logs' && $method === 'GET') {
            $actor = requireRoles($user, ['administrador','instructor','estudiante']);
            $accessSql = "SELECT 1 FROM etapa_productiva WHERE id = ? AND " .
                ($actor['rol'] === 'administrador'
                    ? '1 = 1'
                    : ($actor['rol'] === 'estudiante' ? 'aprendiz_id = ?' : 'instructor_seguimiento_id = ?'));
            $accessStmt = $db->prepare($accessSql);
            $accessStmt->execute($actor['rol'] === 'administrador'
                ? [(int)$parts[1]] : [(int)$parts[1], (int)$actor['id']]);
            if (!$accessStmt->fetchColumn()) {
                respond(['error' => 'No tienes acceso a esta etapa productiva.'], 403);
            }
            $stmt = $db->prepare("SELECT b.id, b.entrada, b.registrada_en,
                    CONCAT(u.nombres, ' ', u.apellidos) AS autor
                FROM bitacoras_productivas b JOIN usuarios u ON u.id = b.autor_id
                WHERE b.etapa_id = ? ORDER BY b.registrada_en DESC, b.id DESC");
            $stmt->execute([(int)$parts[1]]);
            respond(['data' => $stmt->fetchAll()]);
        }
        $actor = requireRoles($user, ['administrador','instructor','estudiante']);
        if ($method === 'GET') {
            $sql = "SELECT e.id, e.ficha_id, f.codigo AS ficha, e.aprendiz_id,
                    CONCAT(a.nombres, ' ', a.apellidos) AS aprendiz, e.alternativa, e.empresa,
                    e.instructor_seguimiento_id, CONCAT(i.nombres, ' ', i.apellidos) AS instructor,
                    e.fecha_inicio, e.fecha_fin, e.estado, e.creado_en
                FROM etapa_productiva e JOIN fichas f ON f.id = e.ficha_id
                JOIN usuarios a ON a.id = e.aprendiz_id
                JOIN usuarios i ON i.id = e.instructor_seguimiento_id";
            $params = [];
            if ($actor['rol'] === 'estudiante') {
                $sql .= ' WHERE e.aprendiz_id = ?';
                $params[] = (int)$actor['id'];
            } elseif ($actor['rol'] === 'instructor') {
                $sql .= ' WHERE e.instructor_seguimiento_id = ?';
                $params[] = (int)$actor['id'];
            }
            $sql .= ' ORDER BY e.actualizado_en DESC, e.id DESC LIMIT 500';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            respond(['data' => $stmt->fetchAll()]);
        }
        if (isset($parts[1], $parts[2]) && ctype_digit($parts[1]) &&
            $parts[2] === 'logs' && $method === 'POST') {
            $data = body();
            $entry = isset($data['entrada']) && is_string($data['entrada']) ? trim($data['entrada']) : '';
            if ($entry === '' || mb_strlen($entry) > 4000) {
                respond(['error' => 'La bitácora debe contener de 1 a 4000 caracteres.'], 422);
            }
            $accessSql = "SELECT aprendiz_id FROM etapa_productiva WHERE id = ? AND " .
                ($actor['rol'] === 'administrador' ? '1 = 1'
                    : ($actor['rol'] === 'estudiante' ? 'aprendiz_id = ?'
                        : 'instructor_seguimiento_id = ?'));
            $accessStmt = $db->prepare($accessSql);
            $accessStmt->execute($actor['rol'] === 'administrador'
                ? [(int)$parts[1]] : [(int)$parts[1], (int)$actor['id']]);
            $learnerId = $accessStmt->fetchColumn();
            if (!$learnerId) {
                respond(['error' => 'No tienes acceso a esta etapa productiva.'], 403);
            }
            $db->beginTransaction();
            try {
                $db->prepare('INSERT INTO bitacoras_productivas (etapa_id, autor_id, entrada) VALUES (?, ?, ?)')
                    ->execute([(int)$parts[1], $actor['id'], $entry]);
                $logId = (int)$db->lastInsertId();
                audit($db, (int)$actor['id'], 'crear_bitacora_productiva', 'bitacoras_productivas', $logId,
                    ['etapa_id'=>(int)$parts[1]]);
                if ((int)$learnerId !== (int)$actor['id']) {
                    notifyUsers($db, [(int)$learnerId], 'Nueva bitácora de etapa productiva',
                        'Tu etapa productiva tiene una nueva entrada de seguimiento.',
                        'formacion', (int)$actor['id']);
                }
                $db->commit();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }
            respond(['data' => ['id' => $logId]], 201);
        }
        if ($method === 'POST') {
            requireRoles($user, ['administrador']);
            $data = body();
            $fichaId = $data['ficha_id'] ?? null;
            $learnerId = $data['aprendiz_id'] ?? null;
            $instructorId = $data['instructor_seguimiento_id'] ?? null;
            $alternative = isset($data['alternativa']) && is_string($data['alternativa']) ? trim($data['alternativa']) : '';
            $company = isset($data['empresa']) && is_string($data['empresa']) ? trim($data['empresa']) : '';
            $startDate = $data['fecha_inicio'] ?? null;
            $endDate = $data['fecha_fin'] ?? null;
            foreach (['fecha_inicio'=>$startDate,'fecha_fin'=>$endDate] as $field => $value) {
                if ($value !== null && $value !== '') {
                    $parsed = is_string($value) ? DateTime::createFromFormat('!Y-m-d', $value) : false;
                    if (!$parsed || $parsed->format('Y-m-d') !== $value) {
                        respond(['error' => 'Las fechas de la etapa deben usar el formato YYYY-MM-DD.'], 422);
                    }
                }
            }
            if ($startDate && $endDate && $startDate > $endDate) {
                respond(['error' => 'La fecha de inicio no puede ser posterior a la fecha de fin.'], 422);
            }
            if (!isPositiveIntegerValue($fichaId) || !isPositiveIntegerValue($learnerId) ||
                !isPositiveIntegerValue($instructorId) || $alternative === '' || mb_strlen($alternative) > 120 ||
                $company === '' || mb_strlen($company) > 180) {
                respond(['error' => 'Completa ficha, aprendiz, alternativa, empresa e instructor de seguimiento.'], 422);
            }
            $eligible = $db->prepare("SELECT 1 FROM fichas_usuarios fu
                JOIN usuarios a ON a.id = fu.usuario_id
                JOIN roles ar ON ar.id = a.rol_id AND ar.nombre = 'estudiante'
                JOIN usuarios i ON i.id = ? AND i.activo = 1
                JOIN roles ir ON ir.id = i.rol_id AND ir.nombre = 'instructor'
                JOIN fichas_instructores fi ON fi.ficha_id = fu.ficha_id AND fi.instructor_id = i.id
                WHERE fu.ficha_id = ? AND fu.usuario_id = ? AND a.activo = 1");
            $eligible->execute([(int)$instructorId, (int)$fichaId, (int)$learnerId]);
            if (!$eligible->fetchColumn()) {
                respond(['error' => 'El aprendiz debe pertenecer a la ficha y el instructor debe estar asignado a ella.'], 422);
            }
            $db->beginTransaction();
            try {
                $db->prepare("INSERT INTO etapa_productiva
                    (ficha_id, aprendiz_id, alternativa, empresa, instructor_seguimiento_id,
                     fecha_inicio, fecha_fin, creado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([(int)$fichaId, (int)$learnerId, $alternative, $company, (int)$instructorId,
                        $startDate ?: null, $endDate ?: null, $actor['id']]);
                $stageId = (int)$db->lastInsertId();
                audit($db, (int)$actor['id'], 'crear_etapa_productiva', 'etapa_productiva', $stageId, [
                    'ficha_id'=>(int)$fichaId, 'aprendiz_id'=>(int)$learnerId,
                ]);
                notifyUsers($db, [(int)$learnerId], 'Etapa productiva registrada',
                    'Se registró tu etapa productiva con la alternativa ' . $alternative . ' en ' . $company . '.',
                    'formacion', (int)$actor['id']);
                $db->commit();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }
            respond(['data' => ['id' => $stageId]], 201);
        }
        if (isset($parts[1]) && ctype_digit($parts[1]) && $method === 'PATCH') {
            requireRoles($user, ['administrador']);
            $data = body();
            if (!isset($data['estado']) || !in_array($data['estado'], ['activa','finalizada','suspendida'], true)) {
                respond(['error' => 'El estado de la etapa productiva no es válido.'], 422);
            }
            $exists = $db->prepare('SELECT aprendiz_id FROM etapa_productiva WHERE id = ?');
            $exists->execute([(int)$parts[1]]);
            $learnerId = $exists->fetchColumn();
            if (!$learnerId) {
                respond(['error' => 'Etapa productiva no encontrada.'], 404);
            }
            $db->beginTransaction();
            try {
                $db->prepare('UPDATE etapa_productiva SET estado = ? WHERE id = ?')
                    ->execute([$data['estado'], (int)$parts[1]]);
                audit($db, (int)$actor['id'], 'actualizar_etapa_productiva', 'etapa_productiva', (int)$parts[1],
                    ['estado'=>$data['estado']]);
                notifyUsers($db, [(int)$learnerId], 'Actualización de etapa productiva',
                    'El estado de tu etapa productiva fue actualizado a ' . $data['estado'] . '.',
                    'formacion', (int)$actor['id']);
                $db->commit();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }
            respond(['data' => ['actualizada' => true]]);
        }
        respond(['error' => 'Método no permitido para etapas productivas.'], 405);
    }
    if ($route === 'cards' && $method === 'GET' && $user && $user['rol'] === 'administrador') {
        requireRoles($user, ['administrador']);
        $stmt = $db->query("SELECT c.id, c.usuario_id, c.codigo_qr, c.emitido_en, c.vence_en,
                c.estado, c.qr_entregado, u.documento, u.nombres, u.apellidos, u.foto,
                r.nombre AS rol
            FROM carnets c JOIN usuarios u ON u.id = c.usuario_id
            JOIN roles r ON r.id = u.rol_id ORDER BY u.apellidos, u.nombres, c.id DESC LIMIT 500");
        respond(['data' => $stmt->fetchAll()]);
    }
    if ($route === 'cards' && isset($parts[1], $parts[2]) && ctype_digit($parts[1]) &&
        in_array($parts[2], ['regenerate','deactivate'], true) && $method === 'POST') {
        $actor = requireRoles($user, ['administrador']);
        $cardId = (int)$parts[1];
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT usuario_id FROM carnets WHERE id = ? FOR UPDATE');
            $stmt->execute([$cardId]);
            $ownerId = $stmt->fetchColumn();
            if (!$ownerId) {
                $db->rollBack();
                respond(['error' => 'Carné no encontrado.'], 404);
            }
            if ($parts[2] === 'regenerate') {
                $newCode = uuid4();
                $db->prepare("UPDATE carnets SET codigo_qr = ?, estado = 'activo', qr_entregado = 0,
                        pin_hash = NULL, pin_intentos_fallidos = 0, pin_bloqueado_hasta = NULL, emitido_en = NOW()
                    WHERE id = ?")->execute([$newCode, $cardId]);
                audit($db, (int)$actor['id'], 'regenerar_carnet', 'carnets', $cardId);
            } else {
                $db->prepare("UPDATE carnets SET estado = 'desactivado' WHERE id = ?")->execute([$cardId]);
                audit($db, (int)$actor['id'], 'desactivar_carnet', 'carnets', $cardId);
            }
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        respond(['data' => ['actualizado' => true]]);
    }
    if ($route === 'instructor' && ($parts[1] ?? '') === 'dashboard' && $method === 'GET') {
        $instructor = requireRoles($user, ['instructor']);
        $instructorId = (int)$instructor['id'];
        $fichasStmt = $db->prepare("SELECT COUNT(*) FROM fichas f
            JOIN fichas_instructores fi ON fi.ficha_id = f.id
            WHERE fi.instructor_id = ? AND f.estado = 'activa'
                AND f.fecha_inicio <= CURRENT_DATE() AND f.fecha_fin >= CURRENT_DATE()");
        $fichasStmt->execute([$instructorId]);
        $classesStmt = $db->prepare("SELECT h.id, h.hora_inicio, h.hora_fin, h.asignatura,
                c.nombre AS competencia, a.nombre AS ambiente,
                f.codigo AS ficha_codigo, COALESCE(p.nombre, f.programa) AS programa,
                CONCAT(i.nombres, ' ', i.apellidos) AS instructor
            FROM horarios h
            JOIN fichas f ON f.id = h.ficha_id AND f.estado = 'activa'
            JOIN fichas_instructores fi ON fi.ficha_id = f.id AND fi.instructor_id = ?
            LEFT JOIN programas p ON p.id = f.programa_id
            LEFT JOIN competencias c ON c.id = h.competencia_id
            JOIN ambientes a ON a.id = h.ambiente_id
            JOIN usuarios i ON i.id = h.instructor_id
            WHERE h.dia_semana = WEEKDAY(CURRENT_DATE()) + 1
                AND f.fecha_inicio <= CURRENT_DATE() AND f.fecha_fin >= CURRENT_DATE()
            ORDER BY h.hora_inicio, f.codigo");
        $classesStmt->execute([$instructorId]);
        $excusesStmt = $db->prepare("SELECT COUNT(*) FROM novedades n
            JOIN asistencias a ON a.id = n.asistencia_id
            JOIN fichas_instructores fi ON fi.ficha_id = a.ficha_id
            WHERE fi.instructor_id = ? AND n.tipo = 'inasistencia'
                AND n.estado_excusa = 'pendiente'");
        $excusesStmt->execute([$instructorId]);
        respond(['data'=>[
            'fichas_asignadas'=>(int)$fichasStmt->fetchColumn(),
            'clases_hoy'=>$classesStmt->fetchAll(),
            'excusas_pendientes'=>(int)$excusesStmt->fetchColumn(),
        ]]);
    }
    if ($route === 'communications') {
        $instructor = requireRoles($user, ['instructor']);
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT au.id, au.fecha_hora,
                    JSON_UNQUOTE(JSON_EXTRACT(au.detalle, '$.titulo')) AS titulo,
                    JSON_UNQUOTE(JSON_EXTRACT(au.detalle, '$.mensaje')) AS mensaje,
                    f.codigo AS ficha,
                    JSON_LENGTH(JSON_EXTRACT(au.detalle, '$.destinatarios')) AS destinatarios
                FROM auditoria au
                LEFT JOIN fichas f ON f.id = JSON_UNQUOTE(JSON_EXTRACT(au.detalle, '$.ficha_id'))
                WHERE au.usuario_id = ? AND au.entidad = 'comunicado'
                ORDER BY au.fecha_hora DESC, au.id DESC LIMIT 200");
            $stmt->execute([(int)$instructor['id']]);
            respond(['data'=>$stmt->fetchAll()]);
        }
        if ($method !== 'POST') {
            respond(['error'=>'Método no permitido para comunicados.'], 405);
        }
        $data = body();
        $fichaId = $data['ficha_id'] ?? null;
        $studentId = $data['usuario_id'] ?? null;
        $title = $data['titulo'] ?? null;
        $message = $data['mensaje'] ?? null;
        $title = is_string($title) ? trim($title) : null;
        $message = is_string($message) ? trim($message) : null;
        if (!isPositiveIntegerValue($fichaId) ||
            ($studentId !== null && $studentId !== '' && !isPositiveIntegerValue($studentId)) ||
            $title === null || $title === '' || mb_strlen($title) > 150 ||
            $message === null || $message === '' || mb_strlen($message) > 2000) {
            respond(['error'=>'Selecciona una ficha asignada y escribe un título y mensaje válidos.'], 422);
        }
        $assignment = $db->prepare("SELECT f.codigo FROM fichas_instructores fi
            JOIN fichas f ON f.id = fi.ficha_id
            WHERE fi.ficha_id = ? AND fi.instructor_id = ?");
        $assignment->execute([(int)$fichaId, (int)$instructor['id']]);
        $fichaCode = $assignment->fetchColumn();
        if ($fichaCode === false) {
            respond(['error'=>'Solo puedes enviar comunicados a tus fichas asignadas.'], 403);
        }
        $recipients = $db->prepare("SELECT fu.usuario_id, CONCAT(u.nombres, ' ', u.apellidos) AS nombre
            FROM fichas_usuarios fu
            JOIN usuarios u ON u.id = fu.usuario_id AND u.activo = 1
            JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
            WHERE fu.ficha_id = ?" .
            ($studentId !== null && $studentId !== '' ? ' AND fu.usuario_id = ?' : ''));
        $recipientParams = [(int)$fichaId];
        if ($studentId !== null && $studentId !== '') {
            $recipientParams[] = (int)$studentId;
        }
        $recipients->execute($recipientParams);
        $recipientRows = $recipients->fetchAll();
        $recipientIds = array_map(static fn($row) => (int)$row['usuario_id'], $recipientRows);
        if (!$recipientIds) {
            respond(['error'=>'No se encontraron aprendices activos en la ficha seleccionada.'], 404);
        }
        $db->beginTransaction();
        try {
            $instructorNameStmt = $db->prepare("SELECT CONCAT(nombres, ' ', apellidos) FROM usuarios WHERE id = ?");
            $instructorNameStmt->execute([(int)$instructor['id']]);
            $instructorName = (string)$instructorNameStmt->fetchColumn();
            $recipientDescription = $studentId !== null && $studentId !== ''
                ? $recipientRows[0]['nombre']
                : 'los aprendices de la ficha';
            notifyUsers($db, $recipientIds, $title,
                'Quién: ' . $instructorName . '. Qué: envió un comunicado a ' . $recipientDescription .
                    '. Fecha: ' . date('Y-m-d') . '. Sesión: no aplica (comunicado). Ficha ' .
                    $fichaCode . ".\n" . $message,
                'sistema', (int)$instructor['id']);
            audit($db, (int)$instructor['id'], 'enviar_comunicado', 'comunicado', (int)$fichaId, [
                'ficha_id'=>(int)$fichaId,
                'destinatarios'=>$recipientIds,
                'titulo'=>$title,
                'mensaje'=>$message,
            ]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        respond(['data'=>['destinatarios'=>count($recipientIds)]], 201);
    }
    if ($route === 'reports' && ($parts[1] ?? '') === 'at-risk' && $method === 'GET') {
        $instructor = requireRoles($user, ['instructor']);
        $thresholdYellow = configuredSystemSetting($db, 'asistencia_alerta_amarilla');
        $thresholdRed = configuredSystemSetting($db, 'asistencia_alerta_roja');
        $summaryStmt = $db->prepare("SELECT u.id AS usuario_id, u.documento,
                CONCAT(u.nombres, ' ', u.apellidos) AS aprendiz,
                f.id AS ficha_id, f.codigo AS ficha,
                COALESCE(p.nombre, f.programa) AS programa,
                SUM(a.estado IN ('presente','tarde')) AS efectivas,
                SUM(a.estado IN ('presente','tarde','ausente')) AS evaluadas,
                SUM(a.estado = 'ausente') AS faltas,
                SUM(a.estado = 'tarde') AS retrasos,
                SUM(a.estado = 'permiso') AS permisos
            FROM fichas_instructores fi
            JOIN fichas f ON f.id = fi.ficha_id
            LEFT JOIN programas p ON p.id = f.programa_id
            JOIN fichas_usuarios fu ON fu.ficha_id = f.id
            JOIN usuarios u ON u.id = fu.usuario_id AND u.activo = 1
            JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
            LEFT JOIN asistencias a ON a.usuario_id = u.id AND a.ficha_id = f.id
            WHERE fi.instructor_id = ?
            GROUP BY u.id, u.documento, u.nombres, u.apellidos, f.id, f.codigo, p.nombre, f.programa
            HAVING evaluadas > 0
            ORDER BY f.codigo, u.apellidos, u.nombres");
        $summaryStmt->execute([(int)$instructor['id']]);
        $learnerRows = $summaryStmt->fetchAll();
        $streakStmt = $db->prepare("SELECT a.usuario_id, a.ficha_id, a.fecha,
                MAX(a.estado = 'ausente') AS tiene_falta
            FROM asistencias a
            JOIN fichas_instructores fi ON fi.ficha_id = a.ficha_id
            WHERE fi.instructor_id = ? AND a.fecha >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
            GROUP BY a.usuario_id, a.ficha_id, a.fecha
            ORDER BY a.usuario_id, a.ficha_id, a.fecha");
        $streakStmt->execute([(int)$instructor['id']]);
        $streaks = [];
        foreach ($streakStmt->fetchAll() as $day) {
            $key = (int)$day['usuario_id'] . ':' . (int)$day['ficha_id'];
            if (!(int)$day['tiene_falta']) {
                $streaks[$key] = ['fecha'=>null,'dias'=>0];
                continue;
            }
            $previousDate = $streaks[$key]['fecha'] ?? null;
            $consecutive = $previousDate !== null &&
                (new DateTimeImmutable($previousDate))->modify('+1 day')->format('Y-m-d') === $day['fecha'];
            $streaks[$key] = [
                'fecha'=>$day['fecha'],
                'dias'=>$consecutive ? ($streaks[$key]['dias'] + 1) : 1,
            ];
        }
        $rows = [];
        foreach ($learnerRows as $row) {
            $rate = (int)round(100 * (int)$row['efectivas'] / (int)$row['evaluadas']);
            $consecutiveAbsences = ($streaks[(int)$row['usuario_id'] . ':' . (int)$row['ficha_id']]['dias'] ?? 0) >= 3;
            if ($rate >= $thresholdYellow && !$consecutiveAbsences) {
                continue;
            }
            $rows[] = [
                'aprendiz'=>$row['aprendiz'],
                'documento'=>$row['documento'],
                'ficha'=>$row['ficha'],
                'programa'=>$row['programa'],
                'asistencia_porcentaje'=>$rate,
                'faltas'=>(int)$row['faltas'],
                'retrasos'=>(int)$row['retrasos'],
                'permisos'=>(int)$row['permisos'],
                'alerta'=>$rate < $thresholdRed || $consecutiveAbsences ? 'roja' : 'amarilla',
                'faltas_consecutivas'=>$consecutiveAbsences ? 3 : 0,
            ];
        }
        $format = strtolower((string)($_GET['format'] ?? 'csv'));
        if (!in_array($format, ['csv','xlsx','pdf'], true)) {
            respond(['error'=>'Formato de reporte no válido. Usa csv, xlsx o pdf.'], 422);
        }
        audit($db, (int)$instructor['id'], 'exportar_riesgo_' . $format, 'asistencias');
        $columns = ['aprendiz','documento','ficha','programa','asistencia_porcentaje','faltas','retrasos','permisos','alerta','faltas_consecutivas'];
        $filename = 'aprendices_en_riesgo_' . date('Ymd_His');
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $columns, ';');
            foreach ($rows as $row) {
                fputcsv($output, array_map('safeSpreadsheetCell', array_values($row)), ';');
            }
            fclose($output);
            exit;
        }
        if ($format === 'xlsx') {
            if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
                respond(['error'=>'No está instalada la dependencia PhpSpreadsheet para generar Excel.'], 503);
            }
            $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $book->getActiveSheet()->fromArray([$columns, ...array_map(
                static fn($row) => array_map('safeSpreadsheetCell', array_values($row)), $rows
            )], null, 'A1');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
            exit;
        }
        if (!class_exists(\Dompdf\Dompdf::class)) {
            respond(['error'=>'No está instalada la dependencia Dompdf para generar el reporte PDF.'], 503);
        }
        $html = '<meta charset="utf-8"><h2>Aprendices en riesgo por inasistencia</h2><table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
        foreach (['Aprendiz','Documento','Ficha','Programa','Asistencia (%)','Faltas','Retrasos','Permisos','Alerta','Días seguidos con falta'] as $heading) {
            $html .= '<th>' . htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $value) {
                $html .= '<td>' . htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
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
    if ($route === 'dashboard' && $method === 'GET') {
        requireRoles($user, ['administrador','coordinador','instructor']);
        $stats = [];
        foreach ([
            'usuarios' => 'SELECT COUNT(*) FROM usuarios WHERE activo = 1',
            'ambientes' => 'SELECT COUNT(*) FROM ambientes',
            'asistencias_hoy' => 'SELECT COUNT(*) FROM asistencias WHERE fecha = CURDATE()',
            'accesos_hoy' => 'SELECT COUNT(*) FROM accesos WHERE DATE(fecha_hora) = CURDATE()',
            'equipos' => 'SELECT COUNT(*) FROM equipos WHERE estado <> "baja"',
            'reservas_activas' => 'SELECT COUNT(*) FROM reservas WHERE estado = "aprobada" AND inicio <= NOW() AND fin >= NOW()',
            'ocupacion_pct' => 'SELECT IF(COUNT(*) = 0, 0, ROUND(100 * SUM(estado = "ocupado") / COUNT(*))) FROM ambientes',
            'fichas_activas' => "SELECT COUNT(*) FROM fichas WHERE estado = 'activa'",
            'correcciones_pendientes' => "SELECT COUNT(*) FROM solicitudes_correccion_asistencia WHERE estado = 'pendiente'",
            'etapas_productivas' => "SELECT COUNT(*) FROM etapa_productiva WHERE estado = 'activa'",
            'cierres_trimestre' => 'SELECT COUNT(*) FROM cierres_trimestre WHERE DATE(cerrado_en) = CURDATE()',
            'carnets_activos' => "SELECT COUNT(*) FROM carnets WHERE estado = 'activo'",
        ] as $key => $sql) {
            $stats[$key] = (int)$db->query($sql)->fetchColumn();
        }
        respond(['data' => $stats]);
    }
    if ($route === 'options' && $method === 'GET') {
        $actor = requireRoles($user, ['administrador','instructor']);
        $fichaSql = "SELECT f.id, f.codigo, f.nombre, f.programa_id, f.estado,
                (f.fecha_inicio <= CURRENT_DATE() AND f.fecha_fin >= CURRENT_DATE()) AS vigente,
                COALESCE(p.nombre, f.programa) AS programa, f.trimestre
            FROM fichas f LEFT JOIN programas p ON p.id = f.programa_id";
        if ($actor['rol'] === 'instructor') {
            $fichaSql .= ' WHERE EXISTS (SELECT 1 FROM fichas_instructores fi
                    WHERE fi.ficha_id = f.id AND fi.instructor_id = ?)';
        }
        $fichaSql .= ' ORDER BY f.codigo';
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
        $studentFichaExpression = $actor['rol'] === 'instructor'
            ? 'CASE WHEN fi.ficha_id IS NOT NULL THEN fu.ficha_id END'
            : 'fu.ficha_id';
        $studentSql = "SELECT u.id, u.documento, u.nombres, u.apellidos,
                GROUP_CONCAT(DISTINCT {$studentFichaExpression}) AS fichas_ids
            FROM usuarios u
            JOIN roles r ON r.id = u.rol_id
            LEFT JOIN fichas_usuarios fu ON fu.usuario_id = u.id";
        $studentParams = [];
        if ($actor['rol'] === 'instructor') {
            $studentSql .= ' LEFT JOIN fichas_instructores fi ON fi.ficha_id = fu.ficha_id AND fi.instructor_id = ?';
            $studentParams[] = (int)$actor['id'];
        }
        $studentSql .= " WHERE r.nombre = 'estudiante' AND u.activo = 1
            GROUP BY u.id, u.documento, u.nombres, u.apellidos";
        if ($actor['rol'] === 'instructor') {
            $studentSql .= ' HAVING fichas_ids IS NOT NULL';
        }
        $studentSql .= ' ORDER BY u.apellidos, u.nombres';
        $studentStmt = $db->prepare($studentSql);
        $studentStmt->execute($studentParams);
        $students = $studentStmt->fetchAll();
        $programs = $db->query("SELECT id, codigo, nombre, nivel, duracion_trimestres, estado
            FROM programas ORDER BY nombre")->fetchAll();
        $competencies = $db->query("SELECT c.id, c.codigo, c.nombre, c.programa_id, c.estado,
                p.nombre AS programa
            FROM competencias c JOIN programas p ON p.id = c.programa_id
            ORDER BY p.nombre, c.nombre")->fetchAll();
        $outcomeSql = "SELECT r.id, r.codigo, r.descripcion, c.programa_id,
                CONCAT(c.codigo, ' · ', c.nombre, ' — ', r.codigo, ' · ', r.descripcion) AS etiqueta
            FROM resultados_aprendizaje r JOIN competencias c ON c.id = r.competencia_id
            WHERE r.estado = 'activo' AND c.estado = 'activo'";
        if ($actor['rol'] === 'instructor') {
            $outcomeSql .= ' AND EXISTS (SELECT 1 FROM fichas f
                JOIN fichas_instructores fi ON fi.ficha_id = f.id
                WHERE f.programa_id = c.programa_id AND fi.instructor_id = ?)';
        }
        $outcomeSql .= ' ORDER BY c.nombre, r.codigo';
        $outcomeStmt = $db->prepare($outcomeSql);
        $outcomeStmt->execute($actor['rol'] === 'instructor' ? [(int)$actor['id']] : []);
        $outcomes = $outcomeStmt->fetchAll();
        $availableRoles = $actor['rol'] === 'administrador'
            ? $db->query('SELECT id, nombre FROM roles ORDER BY id')->fetchAll()
            : [];
        respond(['data' => ['fichas' => $fichas, 'ambientes' => $ambientes,
            'instructores' => $instructors, 'estudiantes' => $students,
            'programas' => $programs, 'competencias' => $competencies,
            'resultados' => $outcomes, 'roles' => $availableRoles]]);
    }
    if ($route === 'attendance' && ($parts[1] ?? '') === 'alerts' && $method === 'GET') {
        $actor = requireRoles($user, ['administrador','coordinador','instructor']);
        $settings = systemSettings($db);
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
                        'tipo' => 'inasistencias_consecutivas',
                        'nivel' => 'roja',
                    ];
                }
            } else {
                $absences[$studentId] = [];
            }
        }
        $attendanceRateSql = "SELECT u.id, u.documento, u.nombres, u.apellidos,
                SUM(a.estado IN ('presente','tarde')) AS asistencias_efectivas,
                SUM(a.estado IN ('presente','tarde','ausente')) AS sesiones_evaluadas
            FROM asistencias a
            JOIN usuarios u ON u.id = a.usuario_id AND u.activo = 1
            JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
            " . ($actor['rol'] === 'instructor'
                ? 'WHERE EXISTS (SELECT 1 FROM fichas_instructores fi
                    WHERE fi.ficha_id = a.ficha_id AND fi.instructor_id = ?)'
                : 'WHERE 1 = 1') . "
            GROUP BY u.id, u.documento, u.nombres, u.apellidos
            HAVING sesiones_evaluadas > 0
            ORDER BY u.apellidos, u.nombres";
        $rateStmt = $db->prepare($attendanceRateSql);
        $rateStmt->execute($actor['rol'] === 'instructor' ? [(int)$actor['id']] : []);
        foreach ($rateStmt->fetchAll() as $row) {
            $studentId = (int)$row['id'];
            if (isset($alerts[$studentId])) {
                continue;
            }
            $rate = (int)round(100 * (int)$row['asistencias_efectivas'] / (int)$row['sesiones_evaluadas']);
            if ($rate >= $settings['asistencia_alerta_amarilla']) {
                continue;
            }
            $alerts[$studentId] = [
                'usuario_id' => $studentId,
                'documento' => $row['documento'],
                'estudiante' => $row['nombres'] . ' ' . $row['apellidos'],
                'porcentaje' => $rate,
                'tipo' => 'asistencia_baja',
                'nivel' => $rate < $settings['asistencia_alerta_roja'] ? 'roja' : 'amarilla',
            ];
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
        $actor = requireRoles($user, ['administrador','coordinador','instructor']);
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
        $yellowThreshold = configuredSystemSetting($db, 'asistencia_alerta_amarilla');
        $redThreshold = configuredSystemSetting($db, 'asistencia_alerta_roja');
        $records = $stmt->fetchAll();
        foreach ($records as &$record) {
            $effective = (int)$record['presentes'] + (int)$record['retrasos'];
            $evaluated = $effective + (int)$record['ausencias'];
            $percentage = $evaluated > 0 ? (int)round(100 * $effective / $evaluated) : null;
            $record['porcentaje'] = $percentage;
            $record['alerta'] = $percentage === null || $percentage >= $yellowThreshold
                ? null
                : ($percentage < $redThreshold ? 'roja' : 'amarilla');
        }
        unset($record);
        respond(['data' => $records]);
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
        $schedulesStmt = $db->prepare("SELECT h.id, h.hora_inicio, h.hora_fin, h.asignatura,
                c.nombre AS competencia, a.nombre AS ambiente
            FROM horarios h
            LEFT JOIN competencias c ON c.id = h.competencia_id
            JOIN ambientes a ON a.id = h.ambiente_id
            WHERE h.ficha_id = ? AND h.dia_semana = WEEKDAY(?) + 1
            ORDER BY h.hora_inicio, h.id");
        $schedulesStmt->execute([$fichaId, $date]);
        $schedules = $schedulesStmt->fetchAll();
        $requestedSchedule = $_GET['horario_id'] ?? '';
        if ($requestedSchedule !== '' && (!ctype_digit((string)$requestedSchedule) ||
            !in_array((int)$requestedSchedule, array_map(static fn($row) => (int)$row['id'], $schedules), true))) {
            respond(['error'=>'Elige una sesión válida de la ficha seleccionada para esa fecha.'], 422);
        }
        $scheduleId = $requestedSchedule !== '' ? (int)$requestedSchedule
            : (count($schedules) === 1 ? (int)$schedules[0]['id'] : null);
        $attendanceScheduleCondition = $scheduleId === null ? 'today.horario_id IS NULL' : 'today.horario_id = ?';
        $studentParams = [$date];
        if ($scheduleId !== null) {
            $studentParams[] = $scheduleId;
        }
        $studentParams[] = $fichaId;
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
                    AND {$attendanceScheduleCondition}
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
        $studentsStmt->execute($studentParams);
        $thresholdYellow = configuredSystemSetting($db, 'asistencia_alerta_amarilla');
        $thresholdRed = configuredSystemSetting($db, 'asistencia_alerta_roja');
        respond(['data' => ['fichas' => $fichas, 'ficha_seleccionada' => $fichaId, 'fecha' => $date,
            'horarios' => $schedules, 'horario_seleccionado' => $scheduleId,
            'umbrales'=>[
                'amarilla'=>$thresholdYellow,
                'roja'=>$thresholdRed,
            ],
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
                h.hora_inicio, h.hora_fin, h.asignatura, c.nombre AS competencia,
                f.codigo AS ficha, f.nombre AS nombre_ficha,
                COALESCE(p.nombre, f.programa) AS programa, f.trimestre, a.nombre AS ambiente,
                CONCAT(i.nombres, ' ', i.apellidos) AS instructor
            FROM horarios h
            {$membershipJoin}
            JOIN fichas f ON f.id = h.ficha_id
            LEFT JOIN programas p ON p.id = f.programa_id
            LEFT JOIN competencias c ON c.id = h.competencia_id
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
        $correctionReason = $data['motivo_correccion'] ?? null;
        if ($correctionReason !== null &&
            (!is_string($correctionReason) || trim($correctionReason) === '' || mb_strlen(trim($correctionReason)) > 500)) {
            respond(['error' => 'El motivo de corrección debe tener entre 1 y 500 caracteres.'], 422);
        }
        if ($observation === null) {
            respond(['error' => 'Escribe una observación para el registro de asistencia.'], 422);
        }
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
            $scheduledClass = $db->prepare('SELECT id FROM horarios
                WHERE id = ? AND ficha_id = ? AND dia_semana = WEEKDAY(?) + 1');
            $scheduledClass->execute([$scheduleId, (int)$data['ficha_id'], $date]);
            if (!$scheduledClass->fetchColumn()) {
                respond(['error' => 'La sesión seleccionada no pertenece a la ficha o fecha.'], 422);
            }
        } else {
            $scheduledClass = $db->prepare('SELECT 1 FROM horarios
                WHERE ficha_id = ? AND dia_semana = WEEKDAY(?) + 1 LIMIT 1');
            $scheduledClass->execute([(int)$data['ficha_id'], $date]);
            if ($scheduledClass->fetchColumn()) {
                respond(['error' => 'Selecciona la sesión de formación antes de registrar asistencia.'], 422);
            }
        }
        $db->beginTransaction();
        try {
            $existingSql = 'SELECT id, estado, observacion, estado_justificacion, fecha FROM asistencias
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
                $changed = $previous['estado'] !== $data['estado'] || $previous['observacion'] !== $observation;
                if ($changed) {
                    requireAttendanceCorrectionWindow($db, (int)$previous['id'], $actor);
                    if ($actor['rol'] === 'instructor' && $correctionReason === null) {
                        $db->rollBack();
                        respond(['error' => 'Escribe el motivo de esta corrección de asistencia.'], 422);
                    }
                    if ($actor['rol'] === 'instructor') {
                        $pending = $db->prepare("SELECT id FROM solicitudes_correccion_asistencia
                            WHERE asistencia_id = ? AND estado = 'pendiente' FOR UPDATE");
                        $pending->execute([(int)$previous['id']]);
                        if ($pending->fetchColumn()) {
                            $db->rollBack();
                            respond(['error' => 'Ya existe una solicitud pendiente para esta asistencia.'], 409);
                        }
                        createAttendanceCorrectionRequest($db, $actor, (int)$previous['id'], $previous,
                            $data['estado'], $observation, trim($correctionReason), $scheduleId);
                        $db->commit();
                        respond(['data' => ['solicitada' => true, 'aprobacion_pendiente' => true]], 202);
                    }
                }
                $justification = $previous['estado'] !== $data['estado']
                    ? ($data['estado'] === 'permiso' ? 'justificada' : 'pendiente')
                    : $previous['estado_justificacion'];
                $stmt = $db->prepare('UPDATE asistencias
                    SET estado = ?, observacion = ?, estado_justificacion = ?, registrado_por = ?
                    WHERE id = ?');
                $stmt->execute([$data['estado'], $observation, $justification, $actor['id'], $previous['id']]);
                $id = (int)$previous['id'];
                $action = 'actualizar';
                if ($previous['estado'] !== $data['estado'] || $previous['observacion'] !== $observation) {
                    notifyAttendance($db, (int)$data['usuario_id'], (int)$data['ficha_id'], $scheduleId,
                        $data['estado'], $date, $observation, (int)$actor['id'], 'corrigió la asistencia');
                }
                audit($db, (int)$actor['id'], $action, 'asistencias', $id, [
                    'estado_anterior'=>$previous['estado'],
                    'estado_nuevo'=>$data['estado'],
                    'observacion_anterior'=>$previous['observacion'],
                    'observacion_nueva'=>$observation,
                    'motivo_correccion'=>$correctionReason,
                ]);
            } else {
                $stmt = $db->prepare('INSERT INTO asistencias (usuario_id,ficha_id,horario_id,fecha,estado,registrado_por,observacion)
                    VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([(int)$data['usuario_id'], (int)$data['ficha_id'], $scheduleId, $date,
                    $data['estado'], $actor['id'], $observation]);
                $id = (int)$db->lastInsertId();
                $action = 'crear';
                notifyAttendance($db, (int)$data['usuario_id'], (int)$data['ficha_id'], $scheduleId,
                    $data['estado'], $date, $observation, (int)$actor['id'], 'registró la asistencia');
                audit($db, (int)$actor['id'], $action, 'asistencias', $id, [
                    'horario_id'=>$scheduleId,
                    'estado'=>$data['estado'],
                    'observacion'=>$observation,
                ]);
            }
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
        'fichas' => ['table'=>'fichas','columns'=>['id','codigo','programa_id','nombre','programa','trimestre','jornada','fecha_inicio','fecha_fin','estado'],'fields'=>['codigo','programa_id','nombre','programa','trimestre','jornada','fecha_inicio','fecha_fin']],
        'programs' => ['table'=>'programas','columns'=>['id','codigo','nombre','nivel','duracion_trimestres','estado'],'fields'=>['codigo','nombre','nivel','duracion_trimestres','estado']],
        'competencies' => ['table'=>'competencias','columns'=>['id','codigo','nombre','programa_id','estado'],'fields'=>['codigo','nombre','programa_id','estado']],
        'learning-outcomes' => ['table'=>'resultados_aprendizaje','columns'=>['id','codigo','descripcion','competencia_id','estado'],'fields'=>['codigo','descripcion','competencia_id','estado']],
        'academic-calendar' => ['table'=>'calendario_academico','columns'=>['id','fecha','tipo','descripcion','estado'],'fields'=>['fecha','tipo','descripcion','estado']],
        'enrollments' => ['table'=>'fichas_usuarios','columns'=>['id','ficha_id','usuario_id','inscrito_en'],'fields'=>['ficha_id','usuario_id']],
        'cards' => ['table'=>'carnets','columns'=>['id','usuario_id','codigo_qr','emitido_en','vence_en','estado'],'fields'=>[]],
        'maintenance' => ['table'=>'mantenimientos','columns'=>['id','ambiente_id','responsable_id','tipo','descripcion','programado_en','finalizado_en','estado'],'fields'=>['ambiente_id','responsable_id','tipo','descripcion','programado_en','finalizado_en','estado']],
        'schedules' => ['table'=>'horarios','columns'=>['id','ficha_id','competencia_id','ambiente_id','instructor_id','dia_semana','hora_inicio','hora_fin','asignatura'],'fields'=>['ficha_id','competencia_id','ambiente_id','instructor_id','dia_semana','hora_inicio','hora_fin','asignatura']],
        'reservations' => ['table'=>'reservas','columns'=>['id','ambiente_id','usuario_id','inicio','fin','motivo','estado','creado_en'],'fields'=>['ambiente_id','usuario_id','inicio','fin','motivo','estado']],
        'attendance' => ['table'=>'asistencias','columns'=>['id','usuario_id','ficha_id','horario_id','fecha','estado','registrado_por','observacion','registrado_en'],'fields'=>['usuario_id','ficha_id','horario_id','fecha','estado','observacion','registrado_por']],
        'novelties' => ['table'=>'novedades','columns'=>['id','usuario_id','asistencia_id','tipo','descripcion','fecha','registrado_por'],'fields'=>['usuario_id','asistencia_id','tipo','descripcion','fecha','registrado_por']],
        'accesses' => ['table'=>'accesos','columns'=>['id','usuario_id','visitante_id','registrado_por','tipo','fecha_hora','observacion'],'fields'=>['usuario_id','visitante_id','tipo','observacion']],
        'visitors' => ['table'=>'visitantes','columns'=>['id','documento','nombres','entidad','motivo','anfitrion_id','ingreso_en','salida_en','registrado_por'],'fields'=>['documento','nombres','entidad','motivo','anfitrion_id','salida_en','registrado_por']],
        'equipment' => ['table'=>'equipos','columns'=>['id','serial','codigo_qr','nombre','categoria','tipo','marca','color','foto','usuario_id','visitante_id','estado','ubicacion','autorizado','creado_en'],'fields'=>['serial','codigo_qr','nombre','categoria','tipo','marca','color','foto','usuario_id','visitante_id','estado','ubicacion','autorizado']],
        'equipment-movements' => ['table'=>'movimientos_equipos','columns'=>['id','equipo_id','usuario_id','registrado_por','tipo','fecha_hora','ubicacion_origen','ubicacion_destino','observacion'],'fields'=>['equipo_id','usuario_id','registrado_por','tipo','ubicacion_origen','ubicacion_destino','observacion']],
        'loans' => ['table'=>'prestamos','columns'=>['id','equipo_id','usuario_id','entregado_por','prestado_en','vence_en','devuelto_en','estado'],'fields'=>['equipo_id','usuario_id','entregado_por','vence_en','devuelto_en','estado']],
        'temporary-permits' => ['table'=>'permisos_temporales','columns'=>['id','usuario_id','visitante_id','autorizado_por','desde','hasta','motivo','activo'],'fields'=>['usuario_id','visitante_id','autorizado_por','desde','hasta','motivo','activo']],
        'notifications' => ['table'=>'notificaciones','columns'=>['id','usuario_id','titulo','mensaje','leida_en','creado_en'],'fields'=>['usuario_id','titulo','mensaje','leida_en']],
        'blocks' => ['table'=>'bloqueos','columns'=>['id','usuario_id','administrador_id','motivo','bloqueado_en','desbloqueado_en'],'fields'=>['usuario_id','administrador_id','motivo','desbloqueado_en']],
    ];
    if ($route === 'reports' && ($parts[1] ?? '') === 'general' && $method === 'GET') {
        $actor = requireRoles($user, ['administrador','coordinador']);
        $columns = ['Ficha','Programa','Trimestre','Estado','Aprendices','Registros de asistencia',
            'Asistencia efectiva (%)','Juicios aprobados','Juicios no aprobados','Etapas productivas activas'];
        $stmt = $db->query("SELECT f.id, f.codigo AS ficha, COALESCE(p.nombre, f.programa) AS programa,
                f.trimestre, f.estado,
                (SELECT COUNT(*) FROM fichas_usuarios fu WHERE fu.ficha_id = f.id) AS aprendices,
                COALESCE(att.registros, 0) AS registros_asistencia,
                CASE WHEN COALESCE(att.evaluadas, 0) = 0 THEN NULL
                    ELSE ROUND(100 * att.efectivas / att.evaluadas) END AS asistencia_efectiva_pct,
                COALESCE(grades.aprobados, 0) AS juicios_aprobados,
                COALESCE(grades.no_aprobados, 0) AS juicios_no_aprobados,
                (SELECT COUNT(*) FROM etapa_productiva ep
                    WHERE ep.ficha_id = f.id AND ep.estado = 'activa') AS etapas_productivas_activas
            FROM fichas f LEFT JOIN programas p ON p.id = f.programa_id
            LEFT JOIN (
                SELECT ficha_id, COUNT(*) AS registros,
                    SUM(estado IN ('presente','tarde','ausente')) AS evaluadas,
                    SUM(estado IN ('presente','tarde')) AS efectivas
                FROM asistencias GROUP BY ficha_id
            ) att ON att.ficha_id = f.id
            LEFT JOIN (
                SELECT fu.ficha_id, SUM(j.juicio = 'aprobado') AS aprobados,
                    SUM(j.juicio = 'no_aprobado') AS no_aprobados
                FROM fichas_usuarios fu
                JOIN juicios_resultados j ON j.estudiante_id = fu.usuario_id
                GROUP BY fu.ficha_id
            ) grades ON grades.ficha_id = f.id
            ORDER BY f.codigo");
        $rows = $stmt->fetchAll();
        $format = strtolower($_GET['format'] ?? '');
        if ($format === '') {
            respond(['data' => $rows]);
        }
        audit($db, (int)$actor['id'], 'exportar_reporte_general_' . $format, 'fichas');
        $filename = 'reporte_general_' . date('Ymd_His');
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $columns, ';');
            foreach ($rows as $row) {
                fputcsv($output, array_map('safeSpreadsheetCell', [
                    $row['ficha'], $row['programa'], $row['trimestre'], $row['estado'],
                    $row['aprendices'], $row['registros_asistencia'], $row['asistencia_efectiva_pct'],
                    $row['juicios_aprobados'], $row['juicios_no_aprobados'], $row['etapas_productivas_activas'],
                ]), ';');
            }
            fclose($output);
            exit;
        }
        if ($format === 'xlsx' && class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $exportRows = array_map(static fn($row) => [
                $row['ficha'], $row['programa'], $row['trimestre'], $row['estado'],
                $row['aprendices'], $row['registros_asistencia'], $row['asistencia_efectiva_pct'],
                $row['juicios_aprobados'], $row['juicios_no_aprobados'], $row['etapas_productivas_activas'],
            ], $rows);
            $book->getActiveSheet()->fromArray([$columns, ...$exportRows], null, 'A1');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
            exit;
        }
        if ($format === 'pdf' && class_exists(\Dompdf\Dompdf::class)) {
            $html = '<meta charset="utf-8"><h2>Reporte general institucional</h2><table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
            foreach ($columns as $column) {
                $html .= '<th>' . htmlspecialchars($column, ENT_QUOTES, 'UTF-8') . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach (['ficha','programa','trimestre','estado','aprendices','registros_asistencia',
                    'asistencia_efectiva_pct','juicios_aprobados','juicios_no_aprobados','etapas_productivas_activas'] as $field) {
                    $html .= '<td>' . htmlspecialchars((string)($row[$field] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
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
        respond(['error' => 'Formato inválido o dependencia de exportación no instalada. Use csv, xlsx o pdf.'], 422);
    }
    if ($route === 'reports' && ($parts[1] ?? '') === 'porter' && $method === 'GET') {
        $admin = requireRoles($user, ['administrador']);
        $columns = ['fecha_hora','evento','persona','documento','rol_ficha','detalle','ambiente'];
        $sql = "SELECT fecha_hora,evento,persona,documento,rol_ficha,detalle,ambiente FROM (
            SELECT a.fecha_hora,CONCAT('Acceso ',a.tipo) AS evento,
                COALESCE(CONCAT(u.nombres,' ',u.apellidos),v.nombres) AS persona,
                COALESCE(u.documento,v.documento) AS documento,
                COALESCE(f.codigo,r.nombre,'Visitante') AS rol_ficha,
                COALESCE(a.observacion,'') AS detalle,'' AS ambiente,
                a.usuario_id AS persona_id,fu.ficha_id AS ficha_id,NULL AS ambiente_id
            FROM accesos a LEFT JOIN usuarios u ON u.id=a.usuario_id LEFT JOIN roles r ON r.id=u.rol_id
            LEFT JOIN fichas_usuarios fu ON fu.usuario_id=u.id LEFT JOIN fichas f ON f.id=fu.ficha_id
            LEFT JOIN visitantes v ON v.id=a.visitante_id
            UNION ALL
            SELECT m.fecha_hora,CONCAT('Equipo ',m.tipo),COALESCE(CONCAT(u.nombres,' ',u.apellidos),v.nombres),
                COALESCE(u.documento,v.documento,''),
                COALESCE(f.codigo,r.nombre,'Visitante'),CONCAT(e.nombre,' · ',e.serial,' · ',COALESCE(m.observacion,'')),
                COALESCE(m.ubicacion_destino,m.ubicacion_origen,''),
                m.usuario_id,fu.ficha_id,NULL
            FROM movimientos_equipos m JOIN equipos e ON e.id=m.equipo_id
            LEFT JOIN usuarios u ON u.id=m.usuario_id LEFT JOIN roles r ON r.id=u.rol_id
            LEFT JOIN fichas_usuarios fu ON fu.usuario_id=u.id LEFT JOIN fichas f ON f.id=fu.ficha_id
            LEFT JOIN visitantes v ON v.id=e.visitante_id
            UNION ALL
            SELECT k.prestada_en,CONCAT('Llave ',k.estado),CONCAT(u.nombres,' ',u.apellidos),u.documento,
                r.nombre,CONCAT('Préstamo #',k.id,' · vence ',k.vence_en,' · devolución ',
                    COALESCE(k.devuelta_en,'pendiente'),' · ',k.estado),e.nombre,
                k.instructor_id,fi.ficha_id,k.ambiente_id
            FROM llaves_ambientes k JOIN ambientes e ON e.id=k.ambiente_id
            JOIN usuarios u ON u.id=k.instructor_id JOIN roles r ON r.id=u.rol_id
            LEFT JOIN fichas_instructores fi ON fi.instructor_id=u.id
            UNION ALL
            SELECT v.ingreso_en,'Visitante',v.nombres,v.documento,'Visitante',
                CONCAT(v.motivo,' · salida ',COALESCE(v.salida_en,'pendiente')),'',
                v.anfitrion_id,NULL,NULL FROM visitantes v
            UNION ALL
            SELECT n.registrada_en,'Novedad de turno',CONCAT(u.nombres,' ',u.apellidos),u.documento,
                'Portero',CONCAT(n.turno,' · ',n.novedad,' · entregada a ',COALESCE(n.entregada_a,'pendiente'),
                    ' · ',COALESCE(n.entregada_en,'pendiente')),'',n.portero_id,NULL,NULL
            FROM novedades_turno n JOIN usuarios u ON u.id=n.portero_id
            UNION ALL
            SELECT o.encontrado_el,'Objeto perdido',CONCAT(u.nombres,' ',u.apellidos),u.documento,
                'Portero',CONCAT(o.descripcion,' · lugar ',o.encontrado_en,' · ',o.estado,
                    ' · entrega ',COALESCE(o.entregado_en,'pendiente')),'',o.encontrado_por,NULL,NULL
            FROM objetos_perdidos o JOIN usuarios u ON u.id=o.encontrado_por
        ) eventos WHERE DATE(fecha_hora)=?";
        $reportDate=$_GET['fecha']??databaseDate($db);
        $parsedDate=is_string($reportDate)&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$reportDate)
            ?DateTime::createFromFormat('!Y-m-d',$reportDate):false;
        if(!$parsedDate||$parsedDate->format('Y-m-d')!==$reportDate){
            respond(['error'=>'La fecha del filtro debe tener formato YYYY-MM-DD.'],422);
        }
        $params = [$reportDate];
        $reportFilters = $_GET;
        $reportDocument = is_string($_GET['documento'] ?? null) ? trim($_GET['documento']) : '';
        if ($reportDocument !== '') {
            $lookup = $db->prepare('SELECT id FROM usuarios WHERE documento=? LIMIT 1');
            $lookup->execute([mb_substr($reportDocument, 0, 30)]);
            $reportFilters['usuario_id'] = (int)$lookup->fetchColumn() ?: 2147483647;
        }
        foreach (['usuario_id'=>'persona_id','ficha_id'=>'ficha_id','ambiente_id'=>'ambiente_id'] as $filter=>$column) {
            if (isPositiveIntegerValue($reportFilters[$filter] ?? null)) {
                $sql .= " AND {$column}=?";
                $params[] = (int)$reportFilters[$filter];
            }
        }
        $serialFilter=$_GET['serial']??null;
        if($serialFilter!==null&&(!is_string($serialFilter)||mb_strlen($serialFilter)>100)){
            respond(['error'=>'El filtro de serial debe contener como máximo 100 caracteres.'],422);
        }
        if(is_string($serialFilter)&&trim($serialFilter)!==''){
            $sql.=" AND evento LIKE 'Equipo %' AND LOCATE(?,detalle)>0";
            $params[]=trim($serialFilter);
        }
        $sql .= ' ORDER BY fecha_hora DESC LIMIT 2000';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows=$stmt->fetchAll();
        $format=strtolower((string)($_GET['format']??'csv'));
        $filename='reporte_porteria_'.date('Ymd_His');
        audit($db,(int)$admin['id'],'exportar_porteria_'.$format,'accesos');
        if($format==='csv'){
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="'.$filename.'.csv"');
            $output=fopen('php://output','w');
            fwrite($output,"\xEF\xBB\xBF");
            fputcsv($output,$columns,';');
            foreach($rows as $row) fputcsv($output,array_map('safeSpreadsheetCell',array_values($row)),';');
            fclose($output);
            exit;
        }
        if($format==='xlsx'&&class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)){
            $book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $book->getActiveSheet()->fromArray([$columns,...array_map(
                static fn($row)=>array_map('safeSpreadsheetCell',array_values($row)),$rows
            )],null,'A1');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="'.$filename.'.xlsx"');
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
            exit;
        }
        if($format==='pdf'&&class_exists(\Dompdf\Dompdf::class)){
            $html='<meta charset="utf-8"><h2>Reporte de portería</h2><table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
            foreach($columns as $column) $html.='<th>'.htmlspecialchars($column,ENT_QUOTES,'UTF-8').'</th>';
            $html.='</tr></thead><tbody>';
            foreach($rows as $row){
                $html.='<tr>';
                foreach($row as $value) $html.='<td>'.htmlspecialchars((string)($value??''),ENT_QUOTES,'UTF-8').'</td>';
                $html.='</tr>';
            }
            $html.='</tbody></table>';
            $pdf=new \Dompdf\Dompdf();
            $pdf->loadHtml($html,'UTF-8');
            $pdf->setPaper('A4','landscape');
            $pdf->render();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="'.$filename.'.pdf"');
            echo $pdf->output();
            exit;
        }
        respond(['error'=>'Formato inválido o dependencia de exportación no instalada. Use csv, xlsx o pdf.'],422);
    }
    if ($route === 'reports' && $method === 'GET') {
        $reportKey = $parts[1] ?? '';
        if (!isset($resources[$reportKey], $roles[$reportKey])) {
            respond(['error' => 'Modulo de reporte no valido.'], 404);
        }
        if ($user && $user['rol'] === 'coordinador') {
            if (!in_array($reportKey, ['attendance','fichas','schedules','enrollments'], true)) {
                respond(['error' => 'Este reporte no está disponible para el rol coordinador.'], 403);
            }
            requireRoles($user, ['coordinador']);
        } else {
            requireRoles($user, $roles[$reportKey]);
        }
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
                $where = ' WHERE asistencia_id IN (SELECT a.id FROM asistencias a
                    JOIN fichas_instructores fi ON fi.ficha_id = a.ficha_id WHERE fi.instructor_id = ?)';
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
        $unknown = array_diff(array_keys($data), ['estado','observacion','motivo_correccion']);
        if ($unknown) {
            respond(['error' => 'Solo puedes editar el estado y la observación de la asistencia.'], 422);
        }
        if (!$data || array_key_exists('estado', $data) && !in_array($data['estado'], ['presente','ausente','tarde','permiso'], true)) {
            respond(['error' => 'El estado de asistencia no es válido.'], 422);
        }
        if (isset($data['observacion']) && (!is_string($data['observacion']) || mb_strlen($data['observacion']) > 500)) {
            respond(['error' => 'La observación debe tener máximo 500 caracteres.'], 422);
        }
        $previousStmt = $db->prepare('SELECT a.usuario_id, a.ficha_id, a.horario_id, a.estado, a.estado_justificacion,
        a.fecha, a.observacion, a.registrado_en, h.asignatura, h.hora_inicio, h.hora_fin
            FROM asistencias a LEFT JOIN horarios h ON h.id = a.horario_id WHERE a.id = ?');
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
        $newStatus = $data['estado'] ?? $previous['estado'];
        $newObservation = array_key_exists('observacion', $data) ? ($data['observacion'] === '' ? null : $data['observacion']) : $previous['observacion'];
        $changed = $previous['estado'] !== $newStatus || $previous['observacion'] !== $newObservation;
        $correctionReason = $data['motivo_correccion'] ?? null;
        if ($correctionReason !== null &&
            (!is_string($correctionReason) || trim($correctionReason) === '' || mb_strlen(trim($correctionReason)) > 500)) {
            respond(['error' => 'El motivo de corrección debe tener entre 1 y 500 caracteres.'], 422);
        }
        if ($actor['rol'] === 'instructor' && $changed) {
            requireAttendanceCorrectionWindow($db, $id, $actor);
            if ($correctionReason === null) {
                respond(['error' => 'Escribe el motivo de esta corrección de asistencia.'], 422);
            }
            $db->beginTransaction();
            try {
                $pending = $db->prepare("SELECT id FROM solicitudes_correccion_asistencia
                    WHERE asistencia_id = ? AND estado = 'pendiente' FOR UPDATE");
                $pending->execute([$id]);
                if ($pending->fetchColumn()) {
                    $db->rollBack();
                    respond(['error' => 'Ya existe una solicitud pendiente para esta asistencia.'], 409);
                }
                createAttendanceCorrectionRequest($db, $actor, $id, $previous, $newStatus,
                    $newObservation, trim($correctionReason),
                    $previous['horario_id'] === null ? null : (int)$previous['horario_id']);
                $db->commit();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }
            respond(['data' => ['solicitada' => true, 'aprobacion_pendiente' => true]], 202);
        }
        $db->beginTransaction();
        try {
            $fields = [];
            $values = [];
            foreach ($data as $field => $value) {
                if ($field === 'motivo_correccion') {
                    continue;
                }
                $fields[] = "{$field} = ?";
                $values[] = $value === '' ? null : $value;
            }
            if ($previous['estado'] !== $newStatus) {
                $fields[] = 'estado_justificacion = ?';
                $values[] = $newStatus === 'permiso' ? 'justificada' : 'pendiente';
            }
            if ($fields) {
                $values[] = $id;
                $stmt = $db->prepare('UPDATE asistencias SET ' . implode(', ', $fields) . ' WHERE id = ?');
                $stmt->execute($values);
            }
            if ($changed) {
                audit($db, (int)$actor['id'], 'actualizar', 'asistencias', $id, [
                    'estado_anterior'=>$previous['estado'],
                    'estado_nuevo'=>$newStatus,
                    'observacion_anterior'=>$previous['observacion'],
                    'observacion_nueva'=>$newObservation,
                    'motivo_correccion'=>$correctionReason,
                ]);
                notifyAttendance($db, (int)$previous['usuario_id'], (int)$previous['ficha_id'],
                    $previous['horario_id'] === null ? null : (int)$previous['horario_id'],
                    $newStatus, $previous['fecha'], $newObservation, (int)$actor['id'], 'corrigió la asistencia');
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
        $where = 'asistencia_id IN (SELECT a.id FROM asistencias a
            JOIN fichas_instructores fi ON fi.ficha_id = a.ficha_id WHERE fi.instructor_id = ?)';
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
    if ($route === 'attendance' && $method === 'GET' && isset($_GET['estado'])) {
        if (!in_array($_GET['estado'], ['presente','ausente','tarde','permiso'], true)) {
            respond(['error' => 'El estado de asistencia no es válido.'], 422);
        }
        $where = ($where === null ? '' : $where . ' AND ') . 'estado = ?';
        $params[] = $_GET['estado'];
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
            $fichaSql = "SELECT f.id, f.codigo, f.programa_id, f.nombre, f.jornada,
                    COALESCE(p.nombre, f.programa) AS programa, f.fecha_inicio, f.fecha_fin,
                    CASE
                        WHEN f.fecha_inicio IS NOT NULL AND f.fecha_inicio > CURRENT_DATE() THEN 'Programada'
                        WHEN f.fecha_fin IS NOT NULL AND f.fecha_fin < CURRENT_DATE() THEN 'Terminada'
                        ELSE 'Activa'
                    END AS estado
                FROM fichas f LEFT JOIN programas p ON p.id = f.programa_id";
            if ($id !== null) {
                $stmt = $db->prepare($fichaSql . ' WHERE f.id = ? LIMIT 1');
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if (!$row) {
                    respond(['error' => 'Registro no encontrado.'], 404);
                }
                respond(['data' => $row]);
            }
            respond(['data' => $db->query($fichaSql . ' ORDER BY f.codigo LIMIT 200')->fetchAll()]);
        }
        if ($route === 'competencies') {
            $sql = "SELECT c.id, c.codigo, c.nombre, c.programa_id, p.nombre AS programa, c.estado
                FROM competencias c JOIN programas p ON p.id = c.programa_id";
            $stmt = $id === null
                ? $db->query($sql . ' ORDER BY p.nombre, c.nombre LIMIT 200')
                : $db->prepare($sql . ' WHERE c.id = ? LIMIT 1');
            if ($id !== null) {
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if (!$row) {
                    respond(['error' => 'Registro no encontrado.'], 404);
                }
                respond(['data' => $row]);
            }
            respond(['data' => $stmt->fetchAll()]);
        }
        if ($route === 'learning-outcomes') {
            $sql = "SELECT r.id, r.codigo, r.descripcion, r.competencia_id,
                    c.nombre AS competencia, c.programa_id, p.nombre AS programa, r.estado
                FROM resultados_aprendizaje r
                JOIN competencias c ON c.id = r.competencia_id
                JOIN programas p ON p.id = c.programa_id";
            $stmt = $id === null
                ? $db->query($sql . ' ORDER BY p.nombre, c.nombre, r.codigo LIMIT 200')
                : $db->prepare($sql . ' WHERE r.id = ? LIMIT 1');
            if ($id !== null) {
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if (!$row) {
                    respond(['error' => 'Registro no encontrado.'], 404);
                }
                respond(['data' => $row]);
            }
            respond(['data' => $stmt->fetchAll()]);
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
            $scheduleSql = "SELECT h.id, h.ficha_id, h.competencia_id, h.ambiente_id, h.instructor_id, h.dia_semana,
                    CASE h.dia_semana WHEN 1 THEN 'Lunes' WHEN 2 THEN 'Martes' WHEN 3 THEN 'Miércoles'
                        WHEN 4 THEN 'Jueves' WHEN 5 THEN 'Viernes' WHEN 6 THEN 'Sábado' WHEN 7 THEN 'Domingo' END AS dia,
                    h.hora_inicio, h.hora_fin, h.asignatura, c.nombre AS competencia,
                    f.codigo AS ficha_codigo,
                    f.nombre AS ficha_nombre, COALESCE(p.nombre, f.programa) AS programa, f.trimestre, a.codigo AS ambiente_codigo,
                    a.nombre AS ambiente, CONCAT(u.nombres, ' ', u.apellidos) AS instructor
                FROM horarios h JOIN fichas f ON f.id = h.ficha_id
                LEFT JOIN programas p ON p.id = f.programa_id
                LEFT JOIN competencias c ON c.id = h.competencia_id
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
            validateFichaInput($db, $data);
            $existingCode = $db->prepare('SELECT 1 FROM fichas WHERE codigo = ?');
            $existingCode->execute([$data['codigo']]);
            if ($existingCode->fetchColumn()) {
                respond(['error' => 'Ya existe una ficha con ese número.'], 409);
            }
        }
        if (in_array($route, ['programs','competencies','learning-outcomes','academic-calendar'], true)) {
            validateAcademicResource($db, $route, $data);
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
        if ($route === 'schedules') {
            validateScheduleCompetency($db, $data);
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
        if ($route === 'novelties') {
            validateAbsenceExcuse($db, $data, $actor);
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
        if ($route === 'novelties') {
            $existingNovelty = $db->prepare("SELECT n.usuario_id, n.asistencia_id, n.tipo, n.fecha, a.ficha_id
                FROM novedades n LEFT JOIN asistencias a ON a.id = n.asistencia_id
                WHERE n.id = ?");
            $existingNovelty->execute([$id]);
            $currentNovelty = $existingNovelty->fetch();
            if (!$currentNovelty) {
                respond(['error' => 'Registro no encontrado.'], 404);
            }
            if ($actor['rol'] === 'instructor' && $currentNovelty['ficha_id'] !== null) {
                $assignment = $db->prepare('SELECT 1 FROM fichas_instructores WHERE ficha_id = ? AND instructor_id = ?');
                $assignment->execute([(int)$currentNovelty['ficha_id'], (int)$actor['id']]);
                if (!$assignment->fetchColumn()) {
                    respond(['error' => 'Solo puedes modificar novedades vinculadas a asistencias de tus fichas asignadas.'], 403);
                }
            }
            validateAbsenceExcuse($db, $data, $actor, $currentNovelty);
        }
        if ($route === 'fichas') {
            $existingFicha = $db->prepare('SELECT codigo, programa, programa_id, fecha_inicio, fecha_fin, jornada FROM fichas WHERE id = ?');
            $existingFicha->execute([$id]);
            $currentFicha = $existingFicha->fetch();
            if (!$currentFicha) {
                respond(['error' => 'Registro no encontrado.'], 404);
            }
            validateFichaInput($db, $data, $currentFicha);
            if (isset($data['codigo'])) {
                $duplicateCode = $db->prepare('SELECT 1 FROM fichas WHERE codigo = ? AND id <> ?');
                $duplicateCode->execute([$data['codigo'], $id]);
                if ($duplicateCode->fetchColumn()) {
                    respond(['error' => 'Ya existe otra ficha con ese número.'], 409);
                }
            }
        }
        if (in_array($route, ['programs','competencies','learning-outcomes','academic-calendar'], true)) {
            $table = [
                'programs'=>'programas',
                'competencies'=>'competencias',
                'learning-outcomes'=>'resultados_aprendizaje',
                'academic-calendar'=>'calendario_academico',
            ][$route];
            $currentStmt = $db->prepare("SELECT * FROM {$table} WHERE id = ?");
            $currentStmt->execute([$id]);
            $currentAcademicRecord = $currentStmt->fetch();
            if (!$currentAcademicRecord) {
                respond(['error' => 'Registro no encontrado.'], 404);
            }
            validateAcademicResource($db, $route, $data, $currentAcademicRecord, $id);
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
            $assignedFichas = $db->prepare('SELECT COUNT(DISTINCT ficha_id) FROM fichas_instructores
                WHERE instructor_id = ? AND ficha_id IN (?, ?)');
            $assignedFichas->execute([(int)$actor['id'], (int)$scheduleFicha, $targetFichaId]);
            if (!$scheduleFicha || (int)$assignedFichas->fetchColumn() !== count(array_unique([(int)$scheduleFicha, $targetFichaId]))) {
                respond(['error' => 'Solo puedes gestionar horarios de tus fichas asignadas.'], 403);
            }
        }
        if ($route === 'schedules') {
            $currentStmt = $db->prepare('SELECT * FROM horarios WHERE id = ?');
            $currentStmt->execute([$id]);
            $currentSchedule = $currentStmt->fetch();
            if (!$currentSchedule) {
                respond(['error' => 'Registro no encontrado.'], 404);
            }
            validateScheduleCompetency($db, $data, $currentSchedule);
        }
        if ($route === 'novelties' && $actor['rol'] === 'instructor') {
            $studentId = (int)($data['usuario_id'] ?? $currentNovelty['usuario_id']);
            $targetAttendanceId = $data['asistencia_id'] ?? $currentNovelty['asistencia_id'];
            if ($targetAttendanceId !== null && $targetAttendanceId !== '') {
                $studentAssignment = $db->prepare("SELECT 1 FROM asistencias a
                    JOIN fichas_instructores fi ON fi.ficha_id = a.ficha_id
                    WHERE a.id = ? AND a.usuario_id = ? AND fi.instructor_id = ?");
                $studentAssignment->execute([(int)$targetAttendanceId, $studentId, (int)$actor['id']]);
            } else {
                $studentAssignment = $db->prepare('SELECT 1 FROM fichas_usuarios fu
                    JOIN fichas_instructores fi ON fi.ficha_id = fu.ficha_id
                    WHERE fu.usuario_id = ? AND fi.instructor_id = ?');
                $studentAssignment->execute([$studentId, (int)$actor['id']]);
            }
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
        if ($route === 'attendance') {
            respond(['error' => 'Los registros de asistencia se conservan; corrígelos con una actualización auditada.'], 409);
        }
        if ($route === 'schedules' && $actor['rol'] === 'instructor') {
            $scheduleFicha = $db->prepare('SELECT ficha_id FROM horarios WHERE id = ?');
            $scheduleFicha->execute([$id]);
            $fichaId = $scheduleFicha->fetchColumn();
            $assignment = $db->prepare('SELECT 1 FROM fichas_instructores WHERE ficha_id = ? AND instructor_id = ?');
            $assignment->execute([(int)$fichaId, (int)$actor['id']]);
            if ($fichaId === false || !$assignment->fetchColumn()) {
                respond(['error' => 'Solo puedes gestionar horarios de tus fichas asignadas.'], 403);
            }
        }
        if (in_array($route, ['programs','competencies','learning-outcomes','academic-calendar'], true)) {
            respond(['error' => 'Los registros académicos se conservan; cambia su estado a inactivo en lugar de eliminarlos.'], 409);
        }
        $db->beginTransaction();
        try {
            $removalNotice = null;
            if ($route === 'attendance') {
                $attendance = $db->prepare('SELECT usuario_id, ficha_id, horario_id, fecha, estado, observacion, registrado_en FROM asistencias WHERE id = ?');
                $attendance->execute([$id]);
                $removalNotice = $attendance->fetch();
                if ($actor['rol'] === 'instructor' && $removalNotice) {
                    $assignedFicha = $db->prepare('SELECT 1 FROM fichas_instructores WHERE ficha_id = ? AND instructor_id = ?');
                    $assignedFicha->execute([(int)$removalNotice['ficha_id'], (int)$actor['id']]);
                    if (!$assignedFicha->fetchColumn()) {
                        $db->rollBack();
                        respond(['error' => 'Solo puedes eliminar asistencias de tus fichas asignadas.'], 403);
                    }
                    requireAttendanceCorrectionWindow($db, $id, $actor);
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
                    $actorNameStmt = $db->prepare("SELECT CONCAT(nombres, ' ', apellidos) FROM usuarios WHERE id = ?");
                    $actorNameStmt->execute([(int)$actor['id']]);
                    $actorName = (string)($actorNameStmt->fetchColumn() ?: 'Personal institucional');
                    $removalNotice = [
                        'recipients' => formationRecipientIds($db, (int)$record['ficha_id']),
                        'title' => 'Horario eliminado',
                        'type' => 'formacion',
                        'sender_id' => (int)$actor['id'],
                        'message' => 'Quién: ' . $actorName . '. Qué: eliminó el horario. Fecha: ' . date('Y-m-d') .
                            '. Sesión: ' . $record['asignatura'] . ', ' .
                            ($days[(int)$record['dia_semana']] ?? 'Sin día') . ', ' .
                            substr($record['hora_inicio'], 0, 5) . '–' . substr($record['hora_fin'], 0, 5) . '.'
                            . "\nAsignatura: " . $record['asignatura']
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
                    $removalNotice['observacion'] === null ? 'Se eliminó este registro de asistencia.' : (string)$removalNotice['observacion'],
                    (int)$actor['id'],
                    'eliminó el registro de asistencia'
                );
            } elseif ($removalNotice && isset($removalNotice['recipients'])) {
                notifyUsers($db, $removalNotice['recipients'], $removalNotice['title'], $removalNotice['message'],
                    $removalNotice['type'] ?? null, $removalNotice['sender_id'] ?? null);
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
