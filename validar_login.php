
<?php

session_start();

require_once "config/conexion.php";

// Comprobar que el formulario fue enviado
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

$correo = trim($_POST["correo"] ?? "");
$password = $_POST["password"] ?? "";

if ($correo === "" || $password === "") {
    header("Location: login.php?error=campos");
    exit();
}

// Buscar al usuario y su rol
$consulta = $conexion->prepare(
    "SELECT
        u.id_usuario,
        u.nombres,
        u.apellidos,
        u.correo_institucional,
        u.password,
        u.activo,
        u.id_rol,
        r.nombre_rol
     FROM USUARIOS u
     INNER JOIN ROLES r ON u.id_rol = r.id_rol
     WHERE u.correo_institucional = ?
     LIMIT 1"
);

$consulta->bind_param("s", $correo);
$consulta->execute();

$resultado = $consulta->get_result();

if ($resultado->num_rows === 1) {

    $usuario = $resultado->fetch_assoc();

    // Verificar que la cuenta esté activa
    if ($usuario["activo"] != 1) {
        header("Location: login.php?error=inactivo");
        exit();
    }

    // Verificar la contraseña
    if (password_verify($password, $usuario["password"])) {

        // Generar un identificador de sesión nuevo
        session_regenerate_id(true);

        // Guardar información necesaria en la sesión
        $_SESSION["id_usuario"] = $usuario["id_usuario"];
        $_SESSION["nombres"] = $usuario["nombres"];
        $_SESSION["apellidos"] = $usuario["apellidos"];
        $_SESSION["correo"] = $usuario["correo_institucional"];
        $_SESSION["id_rol"] = $usuario["id_rol"];
        $_SESSION["rol"] = $usuario["nombre_rol"];

        // Redirigir al panel principal
        header("Location: dashboard.php");
        exit();

    }

}

// Si el correo no existe o la contraseña es incorrecta
header("Location: login.php?error=credenciales");
exit();

?>
