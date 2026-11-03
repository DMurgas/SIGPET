<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('usuarios.registrar');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

$nombre = $_SESSION['nombres'] ?? '';
$apellido = $_SESSION['apellidos'] ?? '';
$rol = $_SESSION['rol'] ?? '';
$error = '';
$exito = '';
$nombres = $apellidos = $correo = $codigo = '';
$id_rol = '';

$resultadoRoles = $conexion->query('SELECT id_rol, nombre_rol FROM roles ORDER BY id_rol');
$roles = $resultadoRoles->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF()) {
        $error = 'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.';
    } else {
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $correo = trim($_POST['correo_institucional'] ?? '');
        $codigo = trim($_POST['codigo_institucional'] ?? '');
        $id_rol = filter_var($_POST['id_rol'] ?? '', FILTER_VALIDATE_INT);
        $password = $_POST['password'] ?? '';
        $confirmarPassword = $_POST['confirmar_password'] ?? '';

        if ($nombres === '' || $apellidos === '' || $correo === '' || !$id_rol || $password === '' || $confirmarPassword === '') {
            $error = 'Completa todos los campos obligatorios.';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ingresa un correo institucional válido.';
        } elseif (strlen($password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($password !== $confirmarPassword) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $stmtRol = $conexion->prepare('SELECT id_rol FROM roles WHERE id_rol = ?');
            $stmtRol->bind_param('i', $id_rol);
            $stmtRol->execute();
            $rolExiste = $stmtRol->get_result()->num_rows === 1;
            $stmtRol->close();

            if (!$rolExiste) {
                $error = 'El rol seleccionado no es válido.';
            } else {
                $codigoDb = $codigo !== '' ? $codigo : null;
                if ($codigoDb === null) {
                    $stmtDuplicado = $conexion->prepare('SELECT id_usuario FROM usuarios WHERE correo_institucional = ? LIMIT 1');
                    $stmtDuplicado->bind_param('s', $correo);
                } else {
                    $stmtDuplicado = $conexion->prepare('SELECT id_usuario FROM usuarios WHERE correo_institucional = ? OR codigo_institucional = ? LIMIT 1');
                    $stmtDuplicado->bind_param('ss', $correo, $codigoDb);
                }
                $stmtDuplicado->execute();
                $duplicado = $stmtDuplicado->get_result()->num_rows > 0;
                $stmtDuplicado->close();

                if ($duplicado) {
                    $error = 'El correo o código institucional ya está registrado.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conexion->prepare('INSERT INTO usuarios (id_rol, nombres, apellidos, correo_institucional, codigo_institucional, password, activo) VALUES (?, ?, ?, ?, ?, ?, 1)');
                    $stmt->bind_param('isssss', $id_rol, $nombres, $apellidos, $correo, $codigoDb, $hash);
                    $stmt->execute();
                    $stmt->close();
                    header('Location: listar.php?registrado=1');
                    exit();
                }
            }
        }
    }
}
$tokenCSRF = obtenerTokenCSRF();
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Registrar usuario | SIGPET</title><link rel="stylesheet" href="../CSS/base.css"><link rel="stylesheet" href="../CSS/dashboard.css"><link rel="stylesheet" href="../CSS/usuarios.css"></head>
<body class="dashboard-body"><div class="dashboard-layout">
<?php $rutaBase = '../'; $paginaActiva = 'usuarios'; require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="dashboard-main">
<header class="dashboard-header"><div><span class="header-subtitle">ADMINISTRACIÓN DEL SISTEMA</span><h1>Registrar usuario</h1></div><div class="header-user"><div class="header-user-text"><strong><?= htmlspecialchars($nombre . ' ' . $apellido) ?></strong><span><?= htmlspecialchars($rol) ?></span></div><div class="user-avatar header-avatar"><?= htmlspecialchars(strtoupper(substr($nombre, 0, 1))) ?></div></div></header>
<section class="users-intro"><h2>Nuevo usuario</h2><p>Completa la información para crear una cuenta dentro de SIGPET.</p></section>
<section class="user-form-container">
<?php if ($error !== ''): ?><div class="form-message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" action="registrar.php" class="user-form">
<input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCSRF) ?>">
<div class="form-section-title">Información personal</div><div class="form-grid">
<div class="form-group"><label for="nombres">Nombres *</label><input type="text" id="nombres" name="nombres" value="<?= htmlspecialchars($nombres) ?>" maxlength="100" required></div>
<div class="form-group"><label for="apellidos">Apellidos *</label><input type="text" id="apellidos" name="apellidos" value="<?= htmlspecialchars($apellidos) ?>" maxlength="100" required></div>
<div class="form-group"><label for="correo_institucional">Correo institucional *</label><input type="email" id="correo_institucional" name="correo_institucional" value="<?= htmlspecialchars($correo) ?>" maxlength="150" required></div>
<div class="form-group"><label for="codigo_institucional">Código institucional</label><input type="text" id="codigo_institucional" name="codigo_institucional" value="<?= htmlspecialchars($codigo) ?>" maxlength="30" placeholder="Opcional"></div>
<div class="form-group"><label for="id_rol">Rol *</label><select id="id_rol" name="id_rol" required><option value="">Selecciona un rol</option><?php foreach ($roles as $item): ?><option value="<?= (int)$item['id_rol'] ?>" <?= (string)$id_rol === (string)$item['id_rol'] ? 'selected' : '' ?>><?= htmlspecialchars($item['nombre_rol']) ?></option><?php endforeach; ?></select></div>
</div>
<div class="form-section-title">Credenciales de acceso</div><div class="form-grid">
<div class="form-group"><label for="password">Contraseña *</label><input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required></div>
<div class="form-group"><label for="confirmar_password">Confirmar contraseña *</label><input type="password" id="confirmar_password" name="confirmar_password" minlength="8" autocomplete="new-password" required></div>
</div>
<p class="form-hint">* Campos obligatorios. La contraseña se almacena mediante password_hash y nunca en texto plano.</p>
<div class="form-actions"><a href="listar.php" class="form-button cancel-button">Cancelar</a><button type="submit" class="form-button submit-button">Registrar usuario</button></div>
</form></section>
<footer class="dashboard-footer">SIGPET © 2026 | Sistema de Gestión de Préstamos de Equipos Tecnológicos</footer></main></div></body></html>
