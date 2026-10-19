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
        WHERE (c.codigo_qr = ? OR u.documento = ?) AND (c.vence_en IS NULL OR c.vence_en >= CURDATE())
          AND NOT EXISTS (SELECT 1 FROM bloqueos b WHERE b.usuario_id = u.id AND b.desbloqueado_en IS NULL)
        LIMIT 1');
    $stmt->execute([$qr, $qr]);
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

function isLocalRecoveryAllowed(): bool
{
    $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $localHost = in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
    $localAddress = in_array($remoteAddress, ['127.0.0.1', '::1'], true);
    return getenv('APP_ENV') === 'local' && $localHost && $localAddress;
}

function createRecoveryToken(PDO $db, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO recuperacion_password (usuario_id,token_hash,vence_en) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE))')
        ->execute([$userId, hash('sha256', $token)]);
    return $token;
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
    $stmt = $db->prepare('SELECT u.id, u.rol_id, r.nombre AS rol, u.documento, u.nombres, u.apellidos, u.correo
        FROM usuarios u JOIN roles r ON r.id = u.rol_id
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
    if ($route === 'auth' && ($parts[1] ?? '') === 'register' && $method === 'POST') {
        $data = body();
        foreach (['documento','nombres','apellidos','correo','password'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field])) {
                respond(['error' => 'Faltan datos obligatorios o tienen un formato incorrecto.'], 422);
            }
        }
        if (isset($data['telefono']) && !is_string($data['telefono'])) {
            respond(['error' => 'El telefono debe ser texto.'], 422);
        }
        $document = trim($data['documento']);
        $firstName = trim($data['nombres']);
        $lastName = trim($data['apellidos']);
        $email = trim($data['correo']);
        $password = $data['password'];
        $phone = trim($data['telefono'] ?? '');

        if ($document === '' || strlen($document) > 30 || $firstName === '' || strlen($firstName) > 100 ||
            $lastName === '' || strlen($lastName) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 190 || strlen($password) < 8 || strlen($phone) > 30 ||
            preg_match('/[\x00-\x1F\x7F]/', $document)) {
            respond(['error' => 'Complete los datos correctamente y use una contrasena de al menos 8 caracteres.'], 422);
        }

        $db->beginTransaction();
        $duplicate = $db->prepare('SELECT 1 FROM usuarios WHERE documento = ? OR correo = ? LIMIT 1');
        $duplicate->execute([$document, $email]);
        if ($duplicate->fetchColumn()) {
            $db->rollBack();
            respond(['error' => 'Ya existe una cuenta con ese documento o correo. Inicie sesion o use la recuperacion de contrasena.'], 409);
        }
        $requestedRole = $data['rol'] ?? 'estudiante';
        if (!in_array($requestedRole, ['estudiante','instructor','portero'], true)) {
            $db->rollBack();
            respond(['error' => 'El registro publico solo admite estudiantes, instructores o porteria.'], 422);
        }
        $role = $db->prepare('SELECT id FROM roles WHERE nombre = ? LIMIT 1');
        $role->execute([$requestedRole]);
        $roleId = $role->fetchColumn();
        if (!$roleId) {
            $db->rollBack();
            respond(['error' => 'No se encontro el rol de estudiante. Contacte al administrador.'], 500);
        }

        $student = $requestedRole === 'estudiante';
        $insert = $db->prepare('INSERT INTO usuarios (rol_id,documento,nombres,apellidos,correo,password_hash,telefono,activo) VALUES (?,?,?,?,?,?,?,?)');
        $insert->execute([(int)$roleId, $document, $firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT), $phone !== '' ? $phone : null, $student ? 1 : 0]);
        $userId = (int)$db->lastInsertId();
        if ($student) {
            $db->prepare('INSERT INTO carnets (usuario_id,codigo_qr) VALUES (?,?)')->execute([$userId, uuid4()]);
            audit($db, $userId, 'registro_publico', 'usuarios', $userId, ['rol' => $requestedRole]);
        } else {
            $request = $db->prepare("INSERT INTO solicitudes_registro (usuario_id) VALUES (?)");
            $request->execute([$userId]);
            audit($db, $userId, 'solicitar_registro_personal', 'solicitudes_registro', (int)$db->lastInsertId(), ['rol' => $requestedRole]);
        }
        $db->commit();

        if (!$student) {
            respond(['data' => [
                'pending_approval' => true,
                'message' => 'Solicitud enviada. Un administrador revisará tus datos antes de activar la cuenta.',
            ]], 201);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['last_activity'] = time();
        $db->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$userId]);
        respond(['data' => ['user' => currentUser($db), 'message' => 'Cuenta de estudiante creada correctamente.']]);
    }
    if ($route === 'registration-requests' && $method === 'GET') {
        requireRoles($user, ['administrador']);
        $stmt = $db->query("SELECT s.id, u.documento, u.nombres, u.apellidos, u.correo, r.nombre AS rol, s.solicitada_en
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
            $db->prepare('INSERT INTO carnets (usuario_id,codigo_qr) VALUES (?,?)')->execute([$request['usuario_id'], uuid4()]);
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
        $stmt = $db->prepare('SELECT u.id, u.password_hash, u.activo FROM usuarios u WHERE u.correo = ? AND u.activo = 1
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
        audit($db, (int)$record['id'], 'inicio_sesion', 'usuarios', (int)$record['id']);
        respond(['data' => currentUser($db)]);
    }
    if ($route === 'auth' && ($parts[1] ?? '') === 'barcode-login' && $method === 'POST') {
        $data = body();
        $document = $data['documento'] ?? null;
        if (!is_string($document) || trim($document) === '' || strlen(trim($document)) > 30) {
            respond(['error' => 'El código del documento no es válido.'], 422);
        }
        $stmt = $db->prepare("SELECT u.id FROM usuarios u
            JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
            JOIN carnets c ON c.usuario_id = u.id
            WHERE u.documento = ? AND u.activo = 1
              AND (c.vence_en IS NULL OR c.vence_en >= CURDATE())
              AND NOT EXISTS (SELECT 1 FROM bloqueos b WHERE b.usuario_id = u.id AND b.desbloqueado_en IS NULL)
            LIMIT 1");
        $stmt->execute([trim($document)]);
        $userId = $stmt->fetchColumn();
        if (!$userId) {
            respond(['error' => 'No se encontró un estudiante activo con ese código.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$userId;
        $_SESSION['last_activity'] = time();
        $db->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$userId]);
        audit($db, (int)$userId, 'inicio_sesion_codigo_barras', 'usuarios', (int)$userId);
        respond(['data' => currentUser($db)]);
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
        $stmt = $db->prepare('SELECT id, correo FROM usuarios WHERE correo = ? AND activo = 1');
        $stmt->execute([$data['correo'] ?? '']);
        $account = $stmt->fetch();
        if ($account) {
            $smtpHost = getenv('SMTP_HOST') ?: '';
            $smtpUser = getenv('SMTP_USER') ?: '';
            $smtpPassword = getenv('SMTP_PASSWORD') ?: '';
            $smtpFrom = getenv('SMTP_FROM') ?: $smtpUser;
            $smtpReady = $smtpHost !== '' && $smtpUser !== '' && $smtpPassword !== '' && $smtpFrom !== '';

            if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class) && $smtpReady) {
                $token = createRecoveryToken($db, (int)$account['id']);
                $baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost/gestion', '/');
                $link = $baseUrl . '/restablecer.html?token=' . urlencode($token);
                $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mailer->isSMTP();
                $mailer->Host = $smtpHost;
                $mailer->SMTPAuth = true;
                $mailer->Username = $smtpUser;
                $mailer->Password = $smtpPassword;
                $mailer->Port = (int)(getenv('SMTP_PORT') ?: 587);
                $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mailer->setFrom($smtpFrom, 'Gestion Institucional');
                $mailer->addAddress($account['correo']);
                $mailer->isHTML(true);
                $mailer->Subject = 'Recuperacion de contrasena';
                $mailer->Body = 'Use este enlace para cambiar su contrasena (vence en 30 minutos): <a href="' . htmlspecialchars($link, ENT_QUOTES) . '">Restablecer contrasena</a>';
                try {
                    $mailer->send();
                } catch (\PHPMailer\PHPMailer\Exception $exception) {
                    $db->prepare('DELETE FROM recuperacion_password WHERE token_hash = ?')->execute([hash('sha256', $token)]);
                    error_log('[gestion-institucional] SMTP send failed: ' . $exception->getMessage());
                    respond(['error' => 'No se pudo enviar el correo. Verifique la configuracion SMTP de XAMPP.'], 503);
                }
            } elseif (isLocalRecoveryAllowed()) {
                $token = createRecoveryToken($db, (int)$account['id']);
                $scriptDirectory = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/gestion/api/index.php')));
                $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . rtrim($scriptDirectory, '/');
                $link = $baseUrl . '/restablecer.html?token=' . urlencode($token);
                respond(['data' => [
                    'message' => 'El correo SMTP no está configurado. Enlace local de un solo uso; vence en 30 minutos.',
                    'local_reset_url' => $link,
                ]]);
            } else {
                error_log('[gestion-institucional] Password recovery requires PHPMailer and SMTP configuration.');
                respond(['error' => 'El restablecimiento por correo requiere configurar PHPMailer y SMTP.'], 503);
            }
        }
        respond(['data' => ['message' => 'Si el correo esta registrado, recibira instrucciones para recuperar su cuenta.']]);
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
        'users' => ['administrador'], 'environments' => ['administrador'],
        'fichas' => ['administrador','instructor'], 'enrollments' => ['administrador'], 'cards' => ['administrador','estudiante'], 'maintenance' => ['administrador'],
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
    if ($route === 'attendance' && ($parts[1] ?? '') === 'alerts' && $method === 'GET') {
        requireRoles($user, ['administrador','instructor']);
        $stmt = $db->query("SELECT a.usuario_id, u.documento, u.nombres, u.apellidos, a.fecha, a.estado
            FROM asistencias a
            JOIN usuarios u ON u.id = a.usuario_id
            JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
            WHERE a.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY a.usuario_id, a.fecha DESC");
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
        if (!in_array($data['estado'], ['presente','ausente','tarde','permiso'], true)) {
            respond(['error' => 'Estado de asistencia no valido.'], 422);
        }
        $stmt = $db->prepare('INSERT INTO asistencias (usuario_id,ficha_id,fecha,estado,registrado_por,observacion)
            VALUES (?,?,COALESCE(?,CURDATE()),?,?,?)');
        $stmt->execute([$data['usuario_id'], $data['ficha_id'], $data['fecha'] ?? null, $data['estado'], $actor['id'], $data['observacion'] ?? null]);
        $id = (int)$db->lastInsertId();
        audit($db, (int)$actor['id'], 'registrar_asistencia', 'asistencias', $id);
        respond(['data' => ['id' => $id]], 201);
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
        'users' => ['table'=>'usuarios','columns'=>['id','rol_id','documento','nombres','apellidos','correo','telefono','foto','activo','ultimo_acceso','creado_en'],'fields'=>['rol_id','documento','nombres','apellidos','correo','password','telefono','foto','activo']],
        'environments' => ['table'=>'ambientes','columns'=>['id','codigo','nombre','ubicacion','capacidad','estado','descripcion'],'fields'=>['codigo','nombre','ubicacion','capacidad','estado','descripcion']],
        'fichas' => ['table'=>'fichas','columns'=>['id','codigo','nombre','fecha_inicio','fecha_fin','estado'],'fields'=>['codigo','nombre','fecha_inicio','fecha_fin','estado']],
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
    if ($route === 'fichas' && $actor['rol'] === 'instructor' && $method !== 'GET') {
        respond(['error' => 'Los instructores solo pueden consultar fichas.'], 403);
    }
    if ($actor['rol'] === 'estudiante' && !($route === 'schedules' && $method === 'GET') &&
        !($route === 'enrollments' && $method === 'GET') &&
        !($route === 'cards' && $method === 'GET') &&
        !($route === 'attendance' && $method === 'GET') &&
        !($route === 'notifications' && $method === 'GET')) {
        respond(['error' => 'Los estudiantes solo pueden consultar sus horarios, asistencias y notificaciones.'], 403);
    }
    $resource = $resources[$route];
    $id = isset($parts[1]) && ctype_digit($parts[1]) ? (int)$parts[1] : null;

    if ($route === 'attendance' && $actor['rol'] === 'estudiante') {
        $where = 'usuario_id = ?';
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
        sendRows($db, $resource['table'], $resource['columns'], $id, $where, $params);
    }
    if ($method === 'POST' && $id === null) {
        $data = body();
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
        saveResource($db, $resource['table'], $resource['fields'], $data, null, $actor);
    }
    if (in_array($method, ['PUT','PATCH'], true) && $id !== null) {
        $data = body();
        if ($route === 'attendance' && $actor['rol'] === 'estudiante') {
            respond(['error' => 'No puede modificar asistencias.'], 403);
        }
        saveResource($db, $resource['table'], $resource['fields'], $data, $id, $actor);
    }
    if ($method === 'DELETE' && $id !== null && $actor['rol'] === 'administrador') {
        if ($route === 'blocks') {
            respond(['error' => 'El historial de bloqueos se conserva; use la acción Desbloquear.'], 409);
        }
        $stmt = $db->prepare("DELETE FROM {$resource['table']} WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->rowCount()) {
            respond(['error' => 'Registro no encontrado.'], 404);
        }
        audit($db, (int)$actor['id'], 'eliminar', $resource['table'], $id);
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
