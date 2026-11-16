<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('prestamos.solicitar');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/bitacora.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validarTokenCSRF()) {
    header('Location: mis_solicitudes.php?error=csrf');
    exit();
}

$idSolicitud = filter_var($_POST['id_solicitud'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$idUsuarioSesion = (int)($_SESSION['id_usuario'] ?? 0);

if (!$idSolicitud || $idUsuarioSesion <= 0) {
    header('Location: mis_solicitudes.php?error=solicitud');
    exit();
}

$stmt = $conexion->prepare(
    "UPDATE solicitudes_prestamo
     SET estado_solicitud = 'Cancelada',
         fecha_decision = NOW()
     WHERE id_solicitud = ?
       AND id_usuario = ?
       AND estado_solicitud = 'Pendiente'"
);
$stmt->bind_param('ii', $idSolicitud, $idUsuarioSesion);
$stmt->execute();
$cancelada = $stmt->affected_rows === 1;
$stmt->close();

if ($cancelada) {
    registrarBitacora(
        $conexion,
        $idUsuarioSesion,
        'Solicitudes',
        'Cancelación de solicitud',
        'solicitudes_prestamo',
        $idSolicitud,
        "El solicitante canceló la solicitud pendiente #{$idSolicitud}."
    );
    $conexion->close();
    header('Location: mis_solicitudes.php?cancelada=1');
    exit();
}

$conexion->close();
header('Location: mis_solicitudes.php?error=cancelar');
exit();
