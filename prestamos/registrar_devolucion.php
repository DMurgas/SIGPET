<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('prestamos.gestionar');

require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php?estado=Aprobada');
    exit();
}

if (!validarTokenCSRF()) {
    header('Location: listar.php?estado=Aprobada&error=csrf');
    exit();
}

$idPrestamo = filter_var(
    $_POST['id_prestamo'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$condicionFinal = trim($_POST['condicion_final'] ?? '');
$observaciones = trim($_POST['observaciones'] ?? '');

$idUsuarioSesion = (int)($_SESSION['id_usuario'] ?? 0);

if (!$idPrestamo) {
    header('Location: listar.php?estado=Aprobada&error=prestamo');
    exit();
}

if ($idUsuarioSesion <= 0) {
    header('Location: ../login.php');
    exit();
}

if ($condicionFinal === '') {
    header('Location: devolver.php?id=' . $idPrestamo . '&error=condicion');
    exit();
}

if (mb_strlen($condicionFinal) > 500) {
    header('Location: devolver.php?id=' . $idPrestamo . '&error=condicion');
    exit();
}

if (mb_strlen($observaciones) > 1000) {
    header('Location: devolver.php?id=' . $idPrestamo . '&error=observaciones');
    exit();
}

try {
    $conexion->begin_transaction();

    $stmt = $conexion->prepare(
        "SELECT
            p.id_prestamo,
            p.id_solicitud,
            p.id_equipo,
            p.id_usuario,
            p.estado_prestamo,
            p.fecha_devolucion_real,

            e.id_estado,
            es.nombre_estado

         FROM prestamos p

         INNER JOIN equipos e
            ON e.id_equipo = p.id_equipo

         INNER JOIN estados_equipo es
            ON es.id_estado = e.id_estado

         WHERE p.id_prestamo = ?

         FOR UPDATE"
    );

    $stmt->bind_param('i', $idPrestamo);
    $stmt->execute();

    $prestamo = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$prestamo) {
        throw new RuntimeException('prestamo');
    }

    if ($prestamo['estado_prestamo'] !== 'Prestado') {
        throw new RuntimeException('estado_prestamo');
    }

    if ($prestamo['fecha_devolucion_real'] !== null) {
        throw new RuntimeException('ya_devuelto');
    }

    if ($prestamo['nombre_estado'] !== 'Prestado') {
        throw new RuntimeException('estado_equipo');
    }

    $stmt = $conexion->prepare(
        "SELECT id_estado
         FROM estados_equipo
         WHERE nombre_estado = 'Disponible'
         LIMIT 1"
    );

    $stmt->execute();

    $estadoDisponible = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$estadoDisponible) {
        throw new RuntimeException('estado_disponible');
    }

    $idEstadoDisponible = (int)$estadoDisponible['id_estado'];

    $stmt = $conexion->prepare(
        "UPDATE prestamos
         SET
            fecha_devolucion_real = NOW(),
            condicion_final = ?,
            observaciones = ?,
            estado_prestamo = 'Devuelto'
         WHERE id_prestamo = ?
           AND estado_prestamo = 'Prestado'
           AND fecha_devolucion_real IS NULL"
    );

    $stmt->bind_param(
        'ssi',
        $condicionFinal,
        $observaciones,
        $idPrestamo
    );

    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException('actualizacion_prestamo');
    }

    $stmt->close();

    $stmt = $conexion->prepare(
        "UPDATE equipos
         SET id_estado = ?
         WHERE id_equipo = ?
           AND id_estado = (
               SELECT id_estado
               FROM estados_equipo
               WHERE nombre_estado = 'Prestado'
               LIMIT 1
           )"
    );

    $stmt->bind_param(
        'ii',
        $idEstadoDisponible,
        $prestamo['id_equipo']
    );

    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException('actualizacion_equipo');
    }

    $stmt->close();

    $conexion->commit();

    header(
        'Location: listar.php?estado=Aprobada&devuelta=1'
    );
    exit();

} catch (Throwable $e) {
    $conexion->rollback();

    $codigo = $e->getMessage();

    $permitidos = [
        'prestamo',
        'estado_prestamo',
        'ya_devuelto',
        'estado_equipo',
        'estado_disponible',
        'actualizacion_prestamo',
        'actualizacion_equipo'
    ];

    if (!in_array($codigo, $permitidos, true)) {
        $codigo = 'general';
    }

    header(
        'Location: listar.php?estado=Aprobada&error=' .
        urlencode($codigo)
    );
    exit();

} finally {
    $conexion->close();
}