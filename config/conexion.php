<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = "localhost";
$usuario = "root";
$password = "";
$base_datos = "sigpet";

try {
    $conexion = new mysqli($host, $usuario, $password, $base_datos);
    $conexion->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    exit("No fue posible conectar con la base de datos SIGPET. Verifica config/conexion.php y que MySQL esté iniciado.");
}
?>
