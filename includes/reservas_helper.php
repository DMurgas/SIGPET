<?php
/**
 * Funciones auxiliares para el módulo de Reservas (Incremento 3: RF-08, RF-09, RN-03).
 */

/**
 * Obtiene los préstamos activos vencidos de un usuario (RN-03).
 */
function obtenerPrestamosVencidosUsuario(mysqli $conexion, int $idUsuario): array
{
    $stmt = $conexion->prepare(
        "SELECT
            p.id_prestamo,
            p.id_equipo,
            p.fecha_entrega,
            s.fecha_inicio,
            s.fecha_devolucion_prevista,
            DATEDIFF(CURDATE(), s.fecha_devolucion_prevista) AS dias_atraso,
            e.codigo_inventario,
            e.nombre AS nombre_equipo
         FROM prestamos p
         INNER JOIN solicitudes_prestamo s ON s.id_solicitud = p.id_solicitud
         INNER JOIN equipos e ON e.id_equipo = p.id_equipo
         WHERE p.id_usuario = ?
           AND p.estado_prestamo = 'Prestado'
           AND p.fecha_devolucion_real IS NULL
           AND s.fecha_devolucion_prevista < CURDATE()
         ORDER BY s.fecha_devolucion_prevista ASC"
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $filas;
}

/**
 * Obtiene la autorización excepcional vigente del encargado para un usuario con préstamo vencido (RN-03).
 */
function obtenerAutorizacionVencidoActiva(mysqli $conexion, int $idUsuario): ?array
{
    $stmt = $conexion->prepare(
        "SELECT
            a.id_autorizacion,
            a.id_usuario,
            a.autorizado_por,
            a.motivo,
            a.fecha_autorizacion,
            u.nombres AS autorizador_nombres,
            u.apellidos AS autorizador_apellidos
         FROM autorizaciones_vencido a
         INNER JOIN usuarios u ON u.id_usuario = a.autorizado_por
         WHERE a.id_usuario = ?
           AND a.activa = 1
         ORDER BY a.id_autorizacion DESC
         LIMIT 1"
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $fila ?: null;
}

/**
 * Busca si un equipo tiene alguna solicitud Pendiente/Aprobada o préstamo activo
 * cuyo período coincida/se traslape con [fechaInicio, fechaFin] (RF-09).
 */
function buscarConflictoReserva(
    mysqli $conexion,
    int $idEquipo,
    string $fechaInicio,
    string $fechaFin,
    ?int $excluirSolicitudId = null,
    bool $forUpdate = false
): ?array {
    $lockSql = $forUpdate ? ' FOR UPDATE' : '';

    // 1. Verificar préstamos activos (entregados y no devueltos todavía).
    $sqlPrestamo = "SELECT
            p.id_prestamo,
            p.id_solicitud,
            s.fecha_inicio,
            GREATEST(s.fecha_devolucion_prevista, CURDATE()) AS fecha_ocupacion_fin,
            s.fecha_devolucion_prevista,
            u.nombres,
            u.apellidos
         FROM prestamos p
         INNER JOIN solicitudes_prestamo s ON s.id_solicitud = p.id_solicitud
         INNER JOIN usuarios u ON u.id_usuario = p.id_usuario
         WHERE p.id_equipo = ?
           AND p.estado_prestamo = 'Prestado'
           AND p.fecha_devolucion_real IS NULL
           AND s.fecha_inicio <= ?
           AND GREATEST(s.fecha_devolucion_prevista, CURDATE()) >= ?
         LIMIT 1" . $lockSql;

    $stmt = $conexion->prepare($sqlPrestamo);
    $stmt->bind_param('iss', $idEquipo, $fechaFin, $fechaInicio);
    $stmt->execute();
    $prestamo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($prestamo) {
        return [
            'origen' => 'prestamo',
            'id' => (int)$prestamo['id_prestamo'],
            'estado' => 'Prestado',
            'fecha_inicio' => $prestamo['fecha_inicio'],
            'fecha_fin' => $prestamo['fecha_ocupacion_fin'],
            'fecha_devolucion_prevista' => $prestamo['fecha_devolucion_prevista'],
            'solicitante' => trim($prestamo['nombres'] . ' ' . $prestamo['apellidos'])
        ];
    }

    // 2. Verificar solicitudes activas (Pendiente o Aprobada) con traslape inclusivo de fechas.
    $excluirId = $excluirSolicitudId ?? 0;
    $sqlSolicitud = "SELECT
            s.id_solicitud,
            s.estado_solicitud,
            s.fecha_inicio,
            s.fecha_devolucion_prevista,
            u.nombres,
            u.apellidos
         FROM solicitudes_prestamo s
         INNER JOIN usuarios u ON u.id_usuario = s.id_usuario
         WHERE s.id_equipo = ?
           AND s.id_solicitud <> ?
           AND s.estado_solicitud IN ('Pendiente', 'Aprobada')
           AND s.fecha_inicio <= ?
           AND s.fecha_devolucion_prevista >= ?
         ORDER BY FIELD(s.estado_solicitud, 'Aprobada', 'Pendiente'), s.fecha_inicio ASC
         LIMIT 1" . $lockSql;

    $stmt = $conexion->prepare($sqlSolicitud);
    $stmt->bind_param('iiss', $idEquipo, $excluirId, $fechaFin, $fechaInicio);
    $stmt->execute();
    $solicitud = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($solicitud) {
        return [
            'origen' => 'solicitud',
            'id' => (int)$solicitud['id_solicitud'],
            'estado' => $solicitud['estado_solicitud'],
            'fecha_inicio' => $solicitud['fecha_inicio'],
            'fecha_fin' => $solicitud['fecha_devolucion_prevista'],
            'fecha_devolucion_prevista' => $solicitud['fecha_devolucion_prevista'],
            'solicitante' => trim($solicitud['nombres'] . ' ' . $solicitud['apellidos'])
        ];
    }

    return null;
}

/**
 * Obtiene todos los períodos ocupados o pendientes de un equipo para mostrarlos al usuario.
 */
function obtenerPeriodosOcupadosEquipo(mysqli $conexion, int $idEquipo): array
{
    $stmt = $conexion->prepare(
        "SELECT
            s.id_solicitud,
            s.fecha_inicio,
            s.fecha_devolucion_prevista,
            s.estado_solicitud,
            p.id_prestamo,
            p.estado_prestamo,
            u.nombres,
            u.apellidos,
            r.nombre_rol
         FROM solicitudes_prestamo s
         INNER JOIN usuarios u ON u.id_usuario = s.id_usuario
         INNER JOIN roles r ON r.id_rol = u.id_rol
         LEFT JOIN prestamos p ON p.id_solicitud = s.id_solicitud
         WHERE s.id_equipo = ?
           AND (
               (s.estado_solicitud IN ('Pendiente', 'Aprobada') AND p.id_prestamo IS NULL)
               OR (p.estado_prestamo = 'Prestado' AND p.fecha_devolucion_real IS NULL)
           )
         ORDER BY s.fecha_inicio ASC"
    );
    $stmt->bind_param('i', $idEquipo);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $filas;
}
