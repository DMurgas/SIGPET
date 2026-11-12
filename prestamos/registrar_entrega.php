<?php

require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('prestamos.gestionar');

require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php?estado=Aprobada');
    exit();
}

/*
 * Validar token CSRF.
 */
if (!validarTokenCSRF()) {
    header('Location: listar.php?estado=Aprobada&error=csrf');
    exit();
}

/*
 * Obtener datos enviados.
 */
$idSolicitud = filter_var(
    $_POST['id_solicitud'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$condicionInicial = trim($_POST['condicion_inicial'] ?? '');

if (!$idSolicitud) {
    header('Location: listar.php?estado=Aprobada&error=solicitud');
    exit();
}

if ($condicionInicial === '') {
    header('Location: entregar.php?id=' . $idSolicitud . '&error=condicion');
    exit();
}

if (mb_strlen($condicionInicial) > 500) {
    header('Location: entregar.php?id=' . $idSolicitud . '&error=condicion');
    exit();
}

/*
 * Usuario que registra la entrega.
 *
 * En este flujo el usuario autenticado es:
 * - quien registra la entrega.
 * - quien queda registrado como autorizado por.
 */
$idUsuarioSesion = (int)($_SESSION['id_usuario'] ?? 0);

if ($idUsuarioSesion <= 0) {
    header('Location: ../login.php');
    exit();
}

try {

    /*
     * Iniciar transacción.
     */
    $conexion->begin_transaction();

    /*
     * 1. Obtener la solicitud y bloquearla para evitar
     *    registros duplicados simultáneos.
     */
    $stmt = $conexion->prepare(
        "SELECT
            s.id_solicitud,
            s.id_usuario,
            s.id_equipo,
            s.fecha_inicio,
            s.fecha_devolucion_prevista,
            s.estado_solicitud,

            e.id_estado,
            es.nombre_estado

         FROM solicitudes_prestamo s

         INNER JOIN equipos e
            ON e.id_equipo = s.id_equipo

         INNER JOIN estados_equipo es
            ON es.id_estado = e.id_estado

         WHERE s.id_solicitud = ?

         FOR UPDATE"
    );

    $stmt->bind_param('i', $idSolicitud);
    $stmt->execute();

    $solicitud = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    /*
     * La solicitud debe existir.
     */
    if (!$solicitud) {
        throw new RuntimeException('solicitud');
    }

    /*
     * La solicitud debe estar aprobada.
     */
    if ($solicitud['estado_solicitud'] !== 'Aprobada') {
        throw new RuntimeException('estado');
    }

    /*
     * El equipo debe estar reservado.
     */
    if ($solicitud['nombre_estado'] !== 'Reservado') {
        throw new RuntimeException('equipo');
    }

    /*
     * 2. Verificar que la solicitud todavía no tenga
     *    un préstamo registrado.
     */
    $stmt = $conexion->prepare(
        "SELECT id_prestamo
         FROM prestamos
         WHERE id_solicitud = ?
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bind_param('i', $idSolicitud);
    $stmt->execute();

    $prestamoExistente = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if ($prestamoExistente) {
        throw new RuntimeException('ya_entregado');
    }

    /*
     * 3. Obtener el estado "Prestado".
     */
    $stmt = $conexion->prepare(
        "SELECT id_estado
         FROM estados_equipo
         WHERE nombre_estado = 'Prestado'
         LIMIT 1"
    );

    $stmt->execute();

    $estadoPrestado = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$estadoPrestado) {
        throw new RuntimeException('estado_prestado');
    }

    $idEstadoPrestado = (int)$estadoPrestado['id_estado'];

    /*
     * 4. Registrar el préstamo.
     *
     * id_usuario:
     *    estudiante/docente que solicitó el equipo.
     *
     * entregado_por:
     *    usuario que registra físicamente la entrega.
     *
     * autorizado_por:
     *    usuario que autoriza la entrega.
     *
     * condicion_inicial:
     *    condición física registrada al entregar.
     *
     * estado_prestamo:
     *    Prestado.
     */
    $stmt = $conexion->prepare(
        "INSERT INTO prestamos (
            id_solicitud,
            id_equipo,
            id_usuario,
            fecha_entrega,
            entregado_por,
            autorizado_por,
            condicion_inicial,
            estado_prestamo
        )
        VALUES (?, ?, ?, NOW(), ?, ?, ?, 'Prestado')"
    );

    $stmt->bind_param(
        'iiiiis',
        $idSolicitud,
        $solicitud['id_equipo'],
        $solicitud['id_usuario'],
        $idUsuarioSesion,
        $idUsuarioSesion,
        $condicionInicial
    );

    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException('registro_prestamo');
    }

    $stmt->close();

    /*
     * 5. Cambiar el estado del equipo:
     *
     * Reservado -> Prestado
     *
     * La condición WHERE evita modificarlo si,
     * por alguna razón, dejó de estar reservado.
     */
    $stmt = $conexion->prepare(
        "UPDATE equipos
         SET id_estado = ?
         WHERE id_equipo = ?
           AND id_estado = (
               SELECT id_estado
               FROM estados_equipo
               WHERE nombre_estado = 'Reservado'
           )"
    );

    $stmt->bind_param(
        'ii',
        $idEstadoPrestado,
        $solicitud['id_equipo']
    );

    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException('equipo_actualizacion');
    }

    $stmt->close();

    /*
     * 6. Confirmar toda la operación.
     */
    $conexion->commit();

    /*
     * Volver a solicitudes aprobadas.
     */
    header(
        'Location: listar.php?estado=Aprobada&entregada=1'
    );

    exit();

} catch (Throwable $e) {

    /*
     * Si algo falla, deshacer TODO:
     * - no se crea préstamo.
     * - no cambia el estado del equipo.
     */
    if ($conexion->errno === 0 || true) {
        try {
            $conexion->rollback();
        } catch (Throwable $rollbackError) {
            // No hacer nada; se mantiene el error original.
        }
    }

    $codigo = $e->getMessage();

    $erroresPermitidos = [
        'solicitud',
        'estado',
        'equipo',
        'ya_entregado',
        'estado_prestado',
        'registro_prestamo',
        'equipo_actualizacion'
    ];

    if (!in_array($codigo, $erroresPermitidos, true)) {
        $codigo = 'general';
    }

    header(
        'Location: listar.php?estado=Aprobada&error=' .
        urlencode($codigo)
    );

    exit();

} finally {

    /*
     * Cerrar conexión.
     */
    if (isset($conexion) && $conexion instanceof mysqli) {
        $conexion->close();
    }
}