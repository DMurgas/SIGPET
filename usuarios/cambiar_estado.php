<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('usuarios.desactivar');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validarTokenCSRF()) {
    http_response_code(400);
    exit('Solicitud no válida. Regresa al listado e inténtalo nuevamente.');
}
$idUsuario = filter_var($_POST['id_usuario'] ?? '', FILTER_VALIDATE_INT);
$accion = $_POST['accion'] ?? '';
if (!$idUsuario || $idUsuario < 1 || !in_array($accion, ['desactivar', 'reactivar'], true)) {
    http_response_code(400);
    exit('Datos de solicitud no válidos.');
}
if ($idUsuario === (int)$_SESSION['id_usuario'] && $accion === 'desactivar') {
    header('Location: listar.php?aviso=propia');
    exit();
}

$stmt = $conexion->prepare('SELECT u.id_usuario, u.id_rol, u.activo, r.nombre_rol FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = ?');
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$objetivo = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$objetivo) {
    header('Location: listar.php');
    exit();
}

if ($accion === 'desactivar' && (int)$objetivo['activo'] === 1 && $objetivo['nombre_rol'] === 'Administrador') {
    $stmt = $conexion->prepare("SELECT COUNT(*) AS total FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE r.nombre_rol = 'Administrador' AND u.activo = 1 AND u.id_usuario <> ?");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $otrosAdministradores = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    if ($otrosAdministradores === 0) {
        header('Location: listar.php?aviso=ultimo_admin');
        exit();
    }
}

$activo = $accion === 'reactivar' ? 1 : 0;
$stmt = $conexion->prepare('UPDATE usuarios SET activo = ? WHERE id_usuario = ?');
$stmt->bind_param('ii', $activo, $idUsuario);
$stmt->execute();
$stmt->close();
header('Location: listar.php?estado=1');
exit();
