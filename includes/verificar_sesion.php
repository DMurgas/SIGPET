<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    $directorio = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $enSubcarpeta = preg_match('~/(usuarios|inventario)$~', rtrim($directorio, '/')) === 1;
    header('Location: ' . ($enSubcarpeta ? '../login.php' : 'login.php'));
    exit();
}
