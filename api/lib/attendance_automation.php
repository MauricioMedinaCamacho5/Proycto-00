<?php
declare(strict_types=1);

function businessDaysSince(PDO $db, string $date): ?int
{
    $today = (string)$db->query('SELECT CURRENT_DATE()')->fetchColumn();
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
    if (!$start || !$end || $start->format('Y-m-d') !== $date || $start > $end) {
        return null;
    }

    $elapsedDays = (int)$start->diff($end)->days;
    $businessDays = intdiv($elapsedDays, 7) * 5;
    for ($offset = 1; $offset <= $elapsedDays % 7; $offset++) {
        if ((int)$start->modify("+{$offset} days")->format('N') <= 5) {
            $businessDays++;
        }
    }
    $calendar = $db->prepare("SELECT COUNT(DISTINCT fecha) FROM calendario_academico
        WHERE estado = 'activo' AND fecha > ? AND fecha <= ? AND WEEKDAY(fecha) < 5");
    $calendar->execute([$date, $today]);
    return max(0, $businessDays - (int)$calendar->fetchColumn());
}

function automationNotify(PDO $db, array $userIds, string $title, string $message, string $type, ?int $senderId, string $eventKey): int
{
    $inserted = 0;
    $insert = $db->prepare("INSERT INTO notificaciones
        (usuario_id, titulo, tipo, remitente_id, mensaje, evento_clave)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE id = id");
    foreach (array_unique(array_map('intval', $userIds)) as $userId) {
        if ($userId > 0) {
            $insert->execute([$userId, $title, $type, $senderId, $message, $eventKey]);
            if ($insert->rowCount() === 1) {
                $inserted++;
            }
        }
    }
    return $inserted;
}

function automationAudit(PDO $db, string $action, string $entity, int $entityId, array $detail = []): void
{
    $stmt = $db->prepare('INSERT INTO auditoria (usuario_id, accion, entidad, entidad_id, detalle) VALUES (NULL, ?, ?, ?, ?)');
    $stmt->execute([
        $action,
        $entity,
        $entityId,
        json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function runDailyAttendanceAutomation(PDO $db, bool $dryRun = false): array
{
    $lock = $db->query("SELECT GET_LOCK('proyecto01_attendance_daily', 0)")->fetchColumn();
    if ((int)$lock !== 1) {
        throw new RuntimeException('La tarea diaria de asistencia ya está en ejecución.');
    }

    try {
        $settings = [];
        foreach ($db->query("SELECT clave, valor FROM configuracion_sistema
            WHERE clave IN ('dias_habiles_excusa','asistencia_alerta_amarilla','asistencia_alerta_roja')") as $row) {
            $settings[$row['clave']] = (int)$row['valor'];
        }
        foreach (['dias_habiles_excusa','asistencia_alerta_amarilla','asistencia_alerta_roja'] as $key) {
            if (!isset($settings[$key]) || $settings[$key] < 1) {
                throw new RuntimeException("Falta configurar el valor {$key} en configuracion_sistema.");
            }
        }
        $deadlineDays = $settings['dias_habiles_excusa'];
        $yellowThreshold = $settings['asistencia_alerta_amarilla'];
        $redThreshold = $settings['asistencia_alerta_roja'];
        if ($redThreshold >= $yellowThreshold) {
            throw new RuntimeException('El umbral rojo de asistencia debe ser menor que el amarillo.');
        }

        $result = ['recordatorios'=>0,'faltas_injustificadas'=>0,'alertas'=>0];
        $absences = $db->query("SELECT a.id, a.usuario_id, a.ficha_id, a.horario_id, a.fecha,
                a.estado_justificacion, n.estado_excusa,
                CONCAT(s.nombres, ' ', s.apellidos) AS estudiante,
                f.codigo AS ficha_codigo,
                h.asignatura, h.hora_inicio, h.hora_fin,
                CONCAT(i.nombres, ' ', i.apellidos) AS instructor
            FROM asistencias a
            JOIN usuarios s ON s.id = a.usuario_id
            JOIN fichas f ON f.id = a.ficha_id
            LEFT JOIN horarios h ON h.id = a.horario_id AND h.ficha_id = a.ficha_id
            LEFT JOIN usuarios i ON i.id = h.instructor_id
            LEFT JOIN novedades n ON n.id = (
                SELECT n2.id FROM novedades n2
                WHERE n2.asistencia_id = a.id AND n2.tipo = 'inasistencia'
                ORDER BY n2.id DESC LIMIT 1
            )
            WHERE a.estado = 'ausente' AND a.estado_justificacion = 'pendiente'
                AND a.fecha <= CURRENT_DATE()
            ORDER BY a.fecha, a.id")->fetchAll();

        foreach ($absences as $absence) {
            $elapsed = businessDaysSince($db, (string)$absence['fecha']);
            if ($elapsed === null || in_array($absence['estado_excusa'], ['pendiente','aprobada','rechazada'], true)) {
                continue;
            }
            $session = $absence['asignatura']
                ? $absence['asignatura'] . ' (' . substr((string)$absence['hora_inicio'], 0, 5) . '–' .
                    substr((string)$absence['hora_fin'], 0, 5) . ')'
                : 'sin sesión específica';
            if ($elapsed === max(0, $deadlineDays - 1)) {
                $sent = 0;
                if (!$dryRun) {
                    $db->beginTransaction();
                    try {
                        $stillEligible = $db->prepare("SELECT 1 FROM asistencias a
                            WHERE a.id = ? AND a.estado = 'ausente'
                                AND a.estado_justificacion = 'pendiente'
                                AND NOT EXISTS (
                                    SELECT 1 FROM novedades n
                                    WHERE n.asistencia_id = a.id AND n.tipo = 'inasistencia'
                                        AND n.estado_excusa IN ('pendiente','aprobada','rechazada')
                                )
                            FOR UPDATE");
                        $stillEligible->execute([(int)$absence['id']]);
                        if (!$stillEligible->fetchColumn()) {
                            $db->rollBack();
                            continue;
                        }
                        $sent = automationNotify(
                            $db,
                            [(int)$absence['usuario_id']],
                            'Recordatorio: plazo de excusa próximo a vencer',
                            'Quién: Sistema de alertas. Qué: puedes presentar una excusa por la falta registrada. ' .
                                'Aprendiz: ' . $absence['estudiante'] . '. Fecha de la falta: ' . $absence['fecha'] .
                                '. Sesión: ' . $session . '. Ficha: ' . $absence['ficha_codigo'] .
                                '. Vence en un día hábil según el plazo configurado.',
                            'excusa',
                            null,
                            'recordatorio-excusa:' . $absence['id']
                        );
                        $db->commit();
                    } catch (Throwable $exception) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        throw $exception;
                    }
                } else {
                    $sent = 1;
                }
                $result['recordatorios'] += $sent;
                continue;
            }
            if ($elapsed <= $deadlineDays) {
                continue;
            }
            $markedInjustified = $dryRun;
            if (!$dryRun) {
                $db->beginTransaction();
                try {
                    $update = $db->prepare("UPDATE asistencias SET estado_justificacion = 'injustificada'
                        WHERE id = ? AND estado = 'ausente' AND estado_justificacion = 'pendiente'
                            AND NOT EXISTS (
                                SELECT 1 FROM novedades n
                                WHERE n.asistencia_id = asistencias.id AND n.tipo = 'inasistencia'
                                    AND n.estado_excusa IN ('pendiente','aprobada','rechazada')
                            )");
                    $update->execute([(int)$absence['id']]);
                    if ($update->rowCount()) {
                        automationNotify(
                            $db,
                            [(int)$absence['usuario_id']],
                            'Falta marcada como injustificada',
                            'Quién: Sistema de alertas. Qué: venció el plazo configurado para presentar una excusa y la falta quedó injustificada. ' .
                                'Aprendiz: ' . $absence['estudiante'] . '. Fecha de la falta: ' . $absence['fecha'] .
                                '. Sesión: ' . $session . '. Ficha: ' . $absence['ficha_codigo'] . '.',
                            'excusa',
                            null,
                            'vencimiento-excusa:' . $absence['id']
                        );
                        automationAudit($db, 'marcar_falta_injustificada', 'asistencias', (int)$absence['id'], [
                            'fecha'=>$absence['fecha'],
                            'dias_habiles_transcurridos'=>$elapsed,
                            'plazo_configurado'=>$deadlineDays,
                        ]);
                        $db->commit();
                        $markedInjustified = true;
                    } else {
                        $db->rollBack();
                    }
                } catch (Throwable $exception) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $exception;
                }
            }
            if ($markedInjustified) {
                $result['faltas_injustificadas']++;
            }
        }

        $summaries = $db->prepare("SELECT a.usuario_id, a.ficha_id,
                CONCAT(u.nombres, ' ', u.apellidos) AS estudiante,
                f.codigo AS ficha_codigo,
                SUM(a.estado IN ('presente','tarde')) AS efectivas,
                SUM(a.estado IN ('presente','tarde','ausente')) AS evaluadas
            FROM asistencias a
            JOIN usuarios u ON u.id = a.usuario_id AND u.activo = 1
            JOIN fichas f ON f.id = a.ficha_id
            JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
            GROUP BY a.usuario_id, a.ficha_id, u.nombres, u.apellidos, f.codigo
            HAVING evaluadas > 0");
        $summaries->execute();
        $learnerSummaries = $summaries->fetchAll();
        $streakRows = $db->query("SELECT a.usuario_id, a.ficha_id, a.fecha,
                COUNT(*) AS sesiones,
                SUM(a.estado = 'ausente') AS faltas,
                SUM(a.estado = 'ausente' AND NOT EXISTS (
                    SELECT 1 FROM novedades n
                    WHERE n.asistencia_id = a.id AND n.tipo = 'inasistencia'
                        AND n.estado_excusa IN ('pendiente','aprobada')
                )) AS faltas_sin_excusa
            FROM asistencias a
            GROUP BY a.usuario_id, a.ficha_id, a.fecha
            ORDER BY a.usuario_id, a.ficha_id, a.fecha");
        $streaks = [];
        foreach ($streakRows as $day) {
            $key = (int)$day['usuario_id'] . ':' . (int)$day['ficha_id'];
            $previous = $streaks[$key] ?? ['fecha'=>null,'dias'=>0];
            $consecutive = $previous['fecha'] !== null &&
                (new DateTimeImmutable($previous['fecha']))->modify('+1 day')->format('Y-m-d') === $day['fecha'];
            $unexcusedAbsence = (int)$day['sesiones'] === (int)$day['faltas'] &&
                (int)$day['faltas'] === (int)$day['faltas_sin_excusa'] &&
                (int)$day['faltas'] > 0;
            $streaks[$key] = [
                'fecha'=>$unexcusedAbsence ? $day['fecha'] : null,
                'dias'=>$unexcusedAbsence ? ($consecutive ? $previous['dias'] + 1 : 1) : 0,
            ];
        }

        $activeAlerts = $db->query('SELECT usuario_id, ficha_id, tipo, activa FROM alertas_asistencia')->fetchAll();
        $alertState = [];
        foreach ($activeAlerts as $alert) {
            $key = (int)$alert['usuario_id'] . ':' . (int)$alert['ficha_id'];
            $alertState[$key][$alert['tipo']] = (int)$alert['activa'] === 1;
        }
        $recipientsQuery = $db->prepare("SELECT u.id FROM usuarios u
            JOIN roles r ON r.id = u.rol_id
            WHERE u.activo = 1 AND (
                r.nombre = 'administrador'
                OR r.nombre = 'instructor' AND EXISTS (
                    SELECT 1 FROM fichas_instructores fi
                    WHERE fi.ficha_id = ? AND fi.instructor_id = u.id
                )
            )");
        $upsertAlert = $db->prepare("INSERT INTO alertas_asistencia (usuario_id, ficha_id, tipo, activa)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE activa = VALUES(activa), actualizado_en = CURRENT_TIMESTAMP");
        foreach ($learnerSummaries as $summary) {
            $key = (int)$summary['usuario_id'] . ':' . (int)$summary['ficha_id'];
            $rate = (int)round(100 * (int)$summary['efectivas'] / (int)$summary['evaluadas']);
            $streakDays = (int)($streaks[$key]['dias'] ?? 0);
            $desired = [
                'roja'=>$rate < $redThreshold || $streakDays >= 3,
                'amarilla'=>$rate < $yellowThreshold && $rate >= $redThreshold && $streakDays < 3,
            ];
            $recipientsQuery->execute([(int)$summary['ficha_id']]);
            $recipients = array_merge([(int)$summary['usuario_id']], $recipientsQuery->fetchAll(PDO::FETCH_COLUMN));
            foreach (['amarilla','roja'] as $type) {
                $wasActive = $alertState[$key][$type] ?? false;
                $isActive = $desired[$type];
                if (!$dryRun) {
                    $db->beginTransaction();
                    try {
                        if ($isActive && !$wasActive) {
                            $details = $type === 'roja'
                                ? ($streakDays >= 3
                                    ? 'completó tres días consecutivos de faltas sin excusa'
                                    : 'descendió por debajo del umbral rojo configurado de ' . $redThreshold . '%')
                                : 'descendió por debajo del umbral amarillo configurado de ' . $yellowThreshold . '%';
                            automationNotify(
                                $db,
                                $recipients,
                                'Alerta ' . $type . ' de asistencia',
                                'Quién: Sistema de alertas. Qué: ' . $summary['estudiante'] . ' ' . $details .
                                    '. Asistencia actual: ' . $rate . '%. Fecha de corte: ' . date('Y-m-d') .
                                    '. Ficha: ' . $summary['ficha_codigo'] . '. Sesión: resumen de las sesiones registradas.',
                                'asistencia',
                                null,
                                'alerta-asistencia:' . $type . ':' . $summary['usuario_id'] . ':' .
                                    $summary['ficha_id'] . ':' . date('YmdHis')
                            );
                            automationAudit($db, 'generar_alerta_asistencia_' . $type, 'asistencias',
                                (int)$summary['usuario_id'], [
                                    'ficha_id'=>(int)$summary['ficha_id'],
                                    'porcentaje'=>$rate,
                                    'racha_dias'=>$streakDays,
                                ]);
                            $result['alertas']++;
                        }
                        $upsertAlert->execute([
                            (int)$summary['usuario_id'],
                            (int)$summary['ficha_id'],
                            $type,
                            $isActive ? 1 : 0,
                        ]);
                        $db->commit();
                    } catch (Throwable $exception) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        throw $exception;
                    }
                } elseif ($isActive && !$wasActive) {
                    $result['alertas']++;
                }
                $alertState[$key][$type] = $isActive;
            }
        }
        if (!$dryRun) {
            $activeKeys = [];
            foreach ($learnerSummaries as $summary) {
                $activeKeys[(int)$summary['usuario_id'] . ':' . (int)$summary['ficha_id']] = true;
            }
            foreach ($activeAlerts as $alert) {
                $key = (int)$alert['usuario_id'] . ':' . (int)$alert['ficha_id'];
                if (!isset($activeKeys[$key]) && (int)$alert['activa'] === 1) {
                    $upsertAlert->execute([
                        (int)$alert['usuario_id'],
                        (int)$alert['ficha_id'],
                        $alert['tipo'],
                        0,
                    ]);
                }
            }
        }
        return $result;
    } finally {
        $db->query("SELECT RELEASE_LOCK('proyecto01_attendance_daily')");
    }
}
