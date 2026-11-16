<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['id_usuario'])) {
    require_once __DIR__ . '/config/conexion.php';
    require_once __DIR__ . '/includes/bitacora.php';
    registrarBitacora(
        $conexion,
        (int)$_SESSION['id_usuario'],
        'Autenticación',
        'Cierre de sesión',
        'usuarios',
        (int)$_SESSION['id_usuario'],
        'El usuario cerró sesión en SIGPET.'
    );
    $conexion->close();
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: login.php');
exit();
