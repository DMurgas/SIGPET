<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('usuarios.editar');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

$id_usuario = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_usuario || $id_usuario < 1) {
    http_response_code(400);
    exit('ID de usuario no válido.');
}

$stmt = $conexion->prepare('SELECT u.*, r.nombre_rol FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = ?');
$stmt->bind_param('i', $id_usuario);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$usuario) {
    http_response_code(404);
    exit('El usuario solicitado no existe.');
}

$puedeAsignarRoles = tienePermiso('usuarios.asignar_roles');
$roles = $conexion->query('SELECT id_rol, nombre_rol FROM roles ORDER BY id_rol')->fetch_all(MYSQLI_ASSOC);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF()) {
        $error = 'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.';
    } else {
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $correo = trim($_POST['correo_institucional'] ?? '');
        $codigo = trim($_POST['codigo_institucional'] ?? '');
        $nuevoRol = $puedeAsignarRoles ? filter_var($_POST['id_rol'] ?? '', FILTER_VALIDATE_INT) : (int)$usuario['id_rol'];

        if ($nombres === '' || $apellidos === '' || $correo === '' || !$nuevoRol) {
            $error = 'Completa todos los campos obligatorios.';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ingresa un correo institucional válido.';
        } else {
            $codigoDb = $codigo !== '' ? $codigo : null;
            $stmt = $conexion->prepare('SELECT id_usuario FROM usuarios WHERE correo_institucional = ? AND id_usuario <> ? LIMIT 1');
            $stmt->bind_param('si', $correo, $id_usuario);
            $stmt->execute();
            $correoDuplicado = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            $codigoDuplicado = false;
            if ($codigoDb !== null) {
                $stmt = $conexion->prepare('SELECT id_usuario FROM usuarios WHERE codigo_institucional = ? AND id_usuario <> ? LIMIT 1');
                $stmt->bind_param('si', $codigoDb, $id_usuario);
                $stmt->execute();
                $codigoDuplicado = $stmt->get_result()->num_rows > 0;
                $stmt->close();
            }

            $stmt = $conexion->prepare('SELECT id_rol FROM roles WHERE id_rol = ?');
            $stmt->bind_param('i', $nuevoRol);
            $stmt->execute();
            $rolValido = $stmt->get_result()->num_rows === 1;
            $stmt->close();

            if ($correoDuplicado) {
                $error = 'El correo institucional ya está registrado.';
            } elseif ($codigoDuplicado) {
                $error = 'El código institucional ya está registrado.';
            } elseif (!$rolValido) {
                $error = 'El rol seleccionado no es válido.';
            } elseif ((int)$id_usuario === (int)$_SESSION['id_usuario'] && $nuevoRol !== (int)$usuario['id_rol']) {
                $error = 'Por seguridad, no puedes cambiar tu propio rol. Solicita el cambio a otro administrador.';
            } else {
                // Evitar dejar el sistema sin ningún administrador activo.
                $stmt = $conexion->prepare("SELECT id_rol FROM roles WHERE nombre_rol = 'Administrador' LIMIT 1");
                $stmt->execute();
                $rolAdmin = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($rolAdmin && (int)$usuario['activo'] === 1 && (int)$usuario['id_rol'] === (int)$rolAdmin['id_rol'] && $nuevoRol !== (int)$rolAdmin['id_rol']) {
                    $stmt = $conexion->prepare('SELECT COUNT(*) AS total FROM usuarios WHERE id_rol = ? AND activo = 1 AND id_usuario <> ?');
                    $idRolAdmin = (int)$rolAdmin['id_rol'];
                    $stmt->bind_param('ii', $idRolAdmin, $id_usuario);
                    $stmt->execute();
                    $otrosAdmins = (int)$stmt->get_result()->fetch_assoc()['total'];
                    $stmt->close();
                    if ($otrosAdmins === 0) {
                        $error = 'No se puede cambiar el rol del último administrador activo.';
                    }
                }

                if ($error === '') {
                    if ($puedeAsignarRoles) {
                        $stmt = $conexion->prepare('UPDATE usuarios SET nombres = ?, apellidos = ?, correo_institucional = ?, codigo_institucional = ?, id_rol = ? WHERE id_usuario = ?');
                        $stmt->bind_param('ssssii', $nombres, $apellidos, $correo, $codigoDb, $nuevoRol, $id_usuario);
                    } else {
                        $stmt = $conexion->prepare('UPDATE usuarios SET nombres = ?, apellidos = ?, correo_institucional = ?, codigo_institucional = ? WHERE id_usuario = ?');
                        $stmt->bind_param('ssssi', $nombres, $apellidos, $correo, $codigoDb, $id_usuario);
                    }
                    $stmt->execute();
                    $stmt->close();
                    if ((int)$id_usuario === (int)$_SESSION['id_usuario']) {
                        $_SESSION['nombres'] = $nombres;
                        $_SESSION['apellidos'] = $apellidos;
                        $_SESSION['correo'] = $correo;
                    }
                    header('Location: listar.php?actualizado=1');
                    exit();
                }
            }
        }
        $usuario['nombres'] = $nombres;
        $usuario['apellidos'] = $apellidos;
        $usuario['correo_institucional'] = $correo;
        $usuario['codigo_institucional'] = $codigo;
        $usuario['id_rol'] = $nuevoRol;
    }
}

$tokenCSRF = obtenerTokenCSRF();
$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Editar usuario | SIGPET</title><link rel="stylesheet" href="../CSS/base.css"><link rel="stylesheet" href="../CSS/dashboard.css"><link rel="stylesheet" href="../CSS/usuarios.css"></head>
<body class="dashboard-body"><div class="dashboard-layout">
<?php $rutaBase = '../'; $paginaActiva = 'usuarios'; require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="dashboard-main"><header class="dashboard-header"><div><span class="header-subtitle">ADMINISTRACIÓN DEL SISTEMA</span><h1>Editar usuario</h1></div><div class="header-user"><div class="header-user-text"><strong><?= htmlspecialchars($nombreSesion . ' ' . $apellidoSesion) ?></strong><span><?= htmlspecialchars($rolSesion) ?></span></div><div class="user-avatar header-avatar"><?= htmlspecialchars(strtoupper(substr($nombreSesion, 0, 1))) ?></div></div></header>
<section class="users-intro"><h2>Modificar información</h2><p>Actualiza los datos de la cuenta seleccionada.</p></section>
<section class="user-form-container">
<?php if ($error !== ''): ?><div class="form-message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" action="editar.php?id=<?= (int)$id_usuario ?>" class="user-form"><input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCSRF) ?>">
<div class="form-section-title">Información personal</div><div class="form-grid">
<div class="form-group"><label for="nombres">Nombres *</label><input type="text" id="nombres" name="nombres" maxlength="100" value="<?= htmlspecialchars($usuario['nombres']) ?>" required></div>
<div class="form-group"><label for="apellidos">Apellidos *</label><input type="text" id="apellidos" name="apellidos" maxlength="100" value="<?= htmlspecialchars($usuario['apellidos']) ?>" required></div>
<div class="form-group"><label for="correo_institucional">Correo institucional *</label><input type="email" id="correo_institucional" name="correo_institucional" maxlength="150" value="<?= htmlspecialchars($usuario['correo_institucional']) ?>" required></div>
<div class="form-group"><label for="codigo_institucional">Código institucional</label><input type="text" id="codigo_institucional" name="codigo_institucional" maxlength="30" value="<?= htmlspecialchars($usuario['codigo_institucional'] ?? '') ?>" placeholder="Opcional"></div>
<div class="form-group"><label for="id_rol">Rol</label><?php if ($puedeAsignarRoles): ?><select id="id_rol" name="id_rol" required><?php foreach ($roles as $item): ?><option value="<?= (int)$item['id_rol'] ?>" <?= (int)$usuario['id_rol'] === (int)$item['id_rol'] ? 'selected' : '' ?>><?= htmlspecialchars($item['nombre_rol']) ?></option><?php endforeach; ?></select><?php else: ?><input type="text" value="<?= htmlspecialchars($usuario['nombre_rol']) ?>" disabled><small>Tu rol no permite modificar la asignación de roles.</small><?php endif; ?></div>
</div><div class="form-actions"><a href="listar.php" class="form-button cancel-button">Cancelar</a><button type="submit" class="form-button submit-button">Guardar cambios</button></div></form>
</section><footer class="dashboard-footer">SIGPET © 2026 | Sistema de Gestión de Préstamos de Equipos Tecnológicos</footer></main></div></body></html>
