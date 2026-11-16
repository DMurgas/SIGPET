<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('prestamos.gestionar');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/reservas_helper.php';
require_once __DIR__ . '/../includes/bitacora.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit();
}

if (!validarTokenCSRF()) {
    header('Location: listar.php?error=csrf');
    exit();
}

$idSolicitud = filter_var($_POST['id_solicitud'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$idSolicitud) {
    header('Location: listar.php?error=solicitud');
    exit();
}

try {
    $conexion->begin_transaction();

    $stmt = $conexion->prepare(
        "SELECT s.id_solicitud, s.id_equipo, s.fecha_inicio, s.fecha_devolucion_prevista,
                s.estado_solicitud, e.id_estado, es.nombre_estado
         FROM solicitudes_prestamo s
         INNER JOIN equipos e ON e.id_equipo = s.id_equipo
         INNER JOIN estados_equipo es ON es.id_estado = e.id_estado
         WHERE s.id_solicitud = ?
         FOR UPDATE"
    );
    $stmt->bind_param('i', $idSolicitud);
    $stmt->execute();
    $solicitud = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$solicitud) {
        throw new RuntimeException('solicitud');
    }
    if ($solicitud['estado_solicitud'] !== 'Pendiente') {
        throw new RuntimeException('estado');
    }
    if ($solicitud['nombre_estado'] !== 'Disponible') {
        throw new RuntimeException('equipo');
    }

    // Segunda validación para evitar aprobar dos solicitudes solapadas o en conflicto con préstamo activo (RF-09).
    $conflicto = buscarConflictoReserva(
        $conexion,
        (int)$solicitud['id_equipo'],
        $solicitud['fecha_inicio'],
        $solicitud['fecha_devolucion_prevista'],
        (int)$idSolicitud,
        true
    );

    if ($conflicto !== null && ($conflicto['origen'] === 'prestamo' || $conflicto['estado'] === 'Aprobada')) {
        throw new RuntimeException('solapada');
    }

    $idDecisor = (int)$_SESSION['id_usuario'];
    $stmt = $conexion->prepare(
        "UPDATE solicitudes_prestamo
         SET estado_solicitud = 'Aprobada', motivo_rechazo = NULL,
             fecha_decision = NOW(), decidido_por = ?
         WHERE id_solicitud = ? AND estado_solicitud = 'Pendiente'"
    );
    $stmt->bind_param('ii', $idDecisor, $idSolicitud);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException('actualizacion');
    }
    $stmt->close();

    $idEstadoReservado = (int)$conexion->query("SELECT id_estado FROM estados_equipo WHERE nombre_estado = 'Reservado' LIMIT 1")->fetch_assoc()['id_estado'];
    $stmt = $conexion->prepare("UPDATE equipos SET id_estado = ? WHERE id_equipo = ? AND id_estado = (SELECT id_estado FROM estados_equipo WHERE nombre_estado = 'Disponible')");
    $stmt->bind_param('ii', $idEstadoReservado, $solicitud['id_equipo']);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException('equipo_actualizacion');
    }
    $stmt->close();

    registrarBitacora(
        $conexion,
        $idDecisor,
        'Préstamos',
        'Aprobación de solicitud',
        'solicitudes_prestamo',
        (int)$idSolicitud,
        "Se aprobó la solicitud #{$idSolicitud} para el equipo #{$solicitud['id_equipo']} ({$solicitud['fecha_inicio']} al {$solicitud['fecha_devolucion_prevista']}) y el equipo pasó de Disponible a Reservado."
    );

    $conexion->commit();
    header('Location: listar.php?estado=Pendiente&aprobada=1');
    exit();
} catch (Throwable $e) {
    $conexion->rollback();
    $codigo = $e->getMessage();
    $permitidos = ['solicitud', 'estado', 'equipo', 'solapada', 'actualizacion', 'equipo_actualizacion'];
    header('Location: listar.php?estado=Pendiente&error=' . (in_array($codigo, $permitidos, true) ? $codigo : 'general'));
    exit();
}
