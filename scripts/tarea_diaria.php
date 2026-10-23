<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse desde la consola.\n");
}

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/lib/attendance_automation.php';

$dryRun = in_array('--dry-run', $argv, true);

try {
    $db = database();
    $result = runDailyAttendanceAutomation($db, $dryRun);
    $overdue = $db->query("SELECT k.id,k.ambiente_id,k.instructor_id,k.vence_en,e.nombre AS ambiente,
            CONCAT(u.nombres,' ',u.apellidos) AS instructor
        FROM llaves_ambientes k
        JOIN ambientes e ON e.id=k.ambiente_id
        JOIN usuarios u ON u.id=k.instructor_id
        WHERE k.estado='prestada' AND k.devuelta_en IS NULL AND k.vence_en<NOW()")->fetchAll();
    $result['llaves_vencidas'] = count($overdue);
    if (!$dryRun && $overdue) {
        $recipients = $db->query("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id
            WHERE u.activo=1 AND r.nombre IN ('administrador','portero')")->fetchAll(PDO::FETCH_COLUMN);
        $update = $db->prepare("UPDATE llaves_ambientes SET estado='vencida'
            WHERE id=? AND estado='prestada' AND devuelta_en IS NULL AND vence_en<NOW()");
        $notification = $db->prepare("INSERT INTO notificaciones
            (usuario_id,titulo,tipo,remitente_id,mensaje,evento_clave)
            VALUES (?,'Llave de ambiente vencida','sistema',NULL,?,?)
            ON DUPLICATE KEY UPDATE id=id");
        foreach ($overdue as $key) {
            $db->beginTransaction();
            try {
                $update->execute([(int)$key['id']]);
                if ($update->rowCount()) {
                    $message = $key['instructor'] . ' no ha devuelto la llave de ' . $key['ambiente'] .
                        '. Vencimiento: ' . $key['vence_en'] . '. Préstamo #' . $key['id'] . '.';
                    foreach ($recipients as $recipientId) {
                        $notification->execute([
                            (int)$recipientId,
                            $message,
                            'porter-key-overdue-' . (int)$key['id'],
                        ]);
                    }
                    $db->prepare("INSERT INTO auditoria (usuario_id,accion,entidad,entidad_id,detalle)
                        VALUES (NULL,'marcar_llave_vencida','llaves_ambientes',?,?)")
                        ->execute([(int)$key['id'],json_encode([
                            'ambiente'=>$key['ambiente'],'instructor'=>$key['instructor'],'vence_en'=>$key['vence_en'],
                        ],JSON_UNESCAPED_UNICODE)]);
                }
                $db->commit();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }
        }
    }
    fwrite(STDOUT, ($dryRun ? 'Simulación completada' : 'Tarea diaria completada') . ': ' .
        json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error en la tarea diaria: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
