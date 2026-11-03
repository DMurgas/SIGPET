<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('inventario.dar_baja');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validarTokenCSRF()) {
    http_response_code(400);
    exit('Solicitud no válida. Regresa al inventario e inténtalo nuevamente.');
}
$idEquipo = filter_var($_POST['id_equipo'] ?? '', FILTER_VALIDATE_INT);
$accion = $_POST['accion'] ?? '';
if (!$idEquipo || $idEquipo < 1 || !in_array($accion, ['baja', 'reactivar'], true)) {
    http_response_code(400);
    exit('Datos de solicitud no válidos.');
}
$nombreEstado = $accion === 'baja' ? 'Dado de baja' : 'Disponible';
$stmt = $conexion->prepare('SELECT id_estado FROM estados_equipo WHERE nombre_estado = ? LIMIT 1');
$stmt->bind_param('s', $nombreEstado);
$stmt->execute();
$estado = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$estado) {
    http_response_code(500);
    exit('No se encontró el estado solicitado en estados_equipo.');
}
$stmt = $conexion->prepare('SELECT id_equipo FROM equipos WHERE id_equipo = ?');
$stmt->bind_param('i', $idEquipo);
$stmt->execute();
$existe = $stmt->get_result()->num_rows === 1;
$stmt->close();
if (!$existe) {
    header('Location: listar.php?error=1');
    exit();
}
$stmt = $conexion->prepare('UPDATE equipos SET id_estado = ? WHERE id_equipo = ?');
$idEstado = (int)$estado['id_estado'];
$stmt->bind_param('ii', $idEstado, $idEquipo);
$stmt->execute();
$stmt->close();
header('Location: listar.php?' . ($accion === 'baja' ? 'baja=1' : 'reactivado=1'));
exit();
