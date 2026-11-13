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

$accionesPermitidas = [
    'baja',
    'reactivar',
    'mantenimiento',
    'disponible'
];

if (!$idEquipo || $idEquipo < 1 || !in_array($accion, $accionesPermitidas, true)) {
    http_response_code(400);
    exit('Datos de solicitud no válidos.');
}

/*
|--------------------------------------------------------------------------
| Determinar estado destino
|--------------------------------------------------------------------------
*/

$estadosDestino = [
    'baja' => 'Dado de baja',
    'reactivar' => 'Disponible',
    'mantenimiento' => 'En mantenimiento',
    'disponible' => 'Disponible'
];

$nombreEstadoDestino = $estadosDestino[$accion];

/*
|--------------------------------------------------------------------------
| Obtener ID del estado destino
|--------------------------------------------------------------------------
*/

$stmt = $conexion->prepare(
    'SELECT id_estado 
     FROM estados_equipo 
     WHERE nombre_estado = ? 
     LIMIT 1'
);

$stmt->bind_param('s', $nombreEstadoDestino);
$stmt->execute();

$resultadoEstado = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$resultadoEstado) {
    http_response_code(500);
    exit('No se encontró el estado solicitado en estados_equipo.');
}

$idEstadoDestino = (int)$resultadoEstado['id_estado'];

/*
|--------------------------------------------------------------------------
| Verificar equipo y estado actual
|--------------------------------------------------------------------------
*/

$stmt = $conexion->prepare(
    'SELECT id_equipo, id_estado
     FROM equipos
     WHERE id_equipo = ?
     LIMIT 1'
);

$stmt->bind_param('i', $idEquipo);
$stmt->execute();

$equipo = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$equipo) {
    header('Location: listar.php?error=1');
    exit();
}

$idEstadoActual = (int)$equipo['id_estado'];

/*
|--------------------------------------------------------------------------
| Reglas de negocio
|--------------------------------------------------------------------------
*/

/*
 * Un equipo Prestado no puede enviarse directamente a mantenimiento.
 * Primero debe registrarse su devolución.
 */
if ($accion === 'mantenimiento' && $idEstadoActual === 3) {
    header('Location: listar.php?error=prestado');
    exit();
}

/*
 * Un equipo Reservado no debe pasar directamente a mantenimiento.
 */
if ($accion === 'mantenimiento' && $idEstadoActual === 2) {
    header('Location: listar.php?error=reservado');
    exit();
}

/*
 * Un equipo dado de baja no puede ponerse en mantenimiento.
 */
if ($accion === 'mantenimiento' && $idEstadoActual === 5) {
    header('Location: listar.php?error=baja');
    exit();
}

/*
 * Para sacar un equipo de mantenimiento,
 * únicamente se permite volver a Disponible.
 */
if ($accion === 'disponible' && $idEstadoActual !== 4) {
    header('Location: listar.php?error=estado');
    exit();
}

/*
 * Reactivar solamente aplica a equipos dados de baja.
 */
if ($accion === 'reactivar' && $idEstadoActual !== 5) {
    header('Location: listar.php?error=estado');
    exit();
}

/*
|--------------------------------------------------------------------------
| Actualizar estado
|--------------------------------------------------------------------------
*/

$stmt = $conexion->prepare(
    'UPDATE equipos
     SET id_estado = ?
     WHERE id_equipo = ?'
);

$stmt->bind_param('ii', $idEstadoDestino, $idEquipo);
$stmt->execute();

$stmt->close();

/*
|--------------------------------------------------------------------------
| Mensaje de resultado
|--------------------------------------------------------------------------
*/

switch ($accion) {

    case 'baja':
        header('Location: listar.php?baja=1');
        break;

    case 'reactivar':
        header('Location: listar.php?reactivado=1');
        break;

    case 'mantenimiento':
        header('Location: listar.php?mantenimiento=1');
        break;

    case 'disponible':
        header('Location: listar.php?disponible=1');
        break;

    default:
        header('Location: listar.php');
        break;
}

exit();