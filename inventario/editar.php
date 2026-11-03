<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('inventario.editar');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

$idEquipo = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idEquipo || $idEquipo < 1) { http_response_code(400); exit('ID de equipo no válido.'); }
$stmt = $conexion->prepare('SELECT * FROM equipos WHERE id_equipo = ?');
$stmt->bind_param('i', $idEquipo);
$stmt->execute();
$equipo = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$equipo) { http_response_code(404); exit('El equipo solicitado no existe.'); }
$tipos = $conexion->query('SELECT id_tipo, nombre_tipo FROM tipos_equipo ORDER BY nombre_tipo')->fetch_all(MYSQLI_ASSOC);
$estados = $conexion->query('SELECT id_estado, nombre_estado FROM estados_equipo ORDER BY id_estado')->fetch_all(MYSQLI_ASSOC);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF()) {
        $error = 'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.';
    } else {
        $codigo = trim($_POST['codigo_inventario'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $idTipo = filter_var($_POST['id_tipo'] ?? '', FILTER_VALIDATE_INT);
        $idEstado = filter_var($_POST['id_estado'] ?? '', FILTER_VALIDATE_INT);
        $marca = trim($_POST['marca'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $serie = trim($_POST['numero_serie'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $ubicacion = trim($_POST['ubicacion'] ?? '');

        if ($codigo === '' || $nombre === '' || !$idTipo || !$idEstado) {
            $error = 'El código, nombre, tipo y estado son obligatorios.';
        } else {
            $stmt = $conexion->prepare('SELECT id_equipo FROM equipos WHERE codigo_inventario = ? AND id_equipo <> ? LIMIT 1');
            $stmt->bind_param('si', $codigo, $idEquipo);
            $stmt->execute();
            $codigoDuplicado = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            $serieDuplicada = false;
            if ($serie !== '') {
                $stmt = $conexion->prepare('SELECT id_equipo FROM equipos WHERE numero_serie = ? AND id_equipo <> ? LIMIT 1');
                $stmt->bind_param('si', $serie, $idEquipo);
                $stmt->execute();
                $serieDuplicada = $stmt->get_result()->num_rows > 0;
                $stmt->close();
            }
            $stmt = $conexion->prepare('SELECT id_tipo FROM tipos_equipo WHERE id_tipo = ?');
            $stmt->bind_param('i', $idTipo);
            $stmt->execute();
            $tipoValido = $stmt->get_result()->num_rows === 1;
            $stmt->close();
            $stmt = $conexion->prepare('SELECT id_estado FROM estados_equipo WHERE id_estado = ?');
            $stmt->bind_param('i', $idEstado);
            $stmt->execute();
            $estadoValido = $stmt->get_result()->num_rows === 1;
            $stmt->close();

            if ($codigoDuplicado) {
                $error = 'El código de inventario ya está asignado a otro equipo.';
            } elseif ($serieDuplicada) {
                $error = 'El número de serie ya está registrado en otro equipo.';
            } elseif (!$tipoValido || !$estadoValido) {
                $error = 'El tipo o estado seleccionado no es válido.';
            } else {
                $marcaDb = $marca !== '' ? $marca : null;
                $modeloDb = $modelo !== '' ? $modelo : null;
                $serieDb = $serie !== '' ? $serie : null;
                $descripcionDb = $descripcion !== '' ? $descripcion : null;
                $ubicacionDb = $ubicacion !== '' ? $ubicacion : null;
                $stmt = $conexion->prepare('UPDATE equipos SET codigo_inventario = ?, nombre = ?, id_tipo = ?, id_estado = ?, marca = ?, modelo = ?, numero_serie = ?, descripcion = ?, ubicacion = ? WHERE id_equipo = ?');
                $stmt->bind_param('ssiisssssi', $codigo, $nombre, $idTipo, $idEstado, $marcaDb, $modeloDb, $serieDb, $descripcionDb, $ubicacionDb, $idEquipo);
                $stmt->execute();
                $stmt->close();
                header('Location: listar.php?actualizado=1');
                exit();
            }
        }
        $equipo = array_merge($equipo, [
            'codigo_inventario'=>$codigo, 'nombre'=>$nombre, 'id_tipo'=>$idTipo,
            'id_estado'=>$idEstado, 'marca'=>$marca, 'modelo'=>$modelo,
            'numero_serie'=>$serie, 'descripcion'=>$descripcion, 'ubicacion'=>$ubicacion
        ]);
    }
}

$tokenCSRF = obtenerTokenCSRF();
$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Editar equipo | SIGPET</title><link rel="stylesheet" href="../CSS/base.css"><link rel="stylesheet" href="../CSS/dashboard.css"><link rel="stylesheet" href="../CSS/inventario.css"></head>
<body class="dashboard-body"><div class="dashboard-layout">
<?php $rutaBase = '../'; $paginaActiva = 'inventario'; require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="dashboard-main"><header class="dashboard-header"><div><span class="header-subtitle">CONTROL DE EQUIPOS TECNOLÓGICOS</span><h1>Editar equipo</h1></div><div class="header-user"><div class="header-user-text"><strong><?= htmlspecialchars($nombreSesion . ' ' . $apellidoSesion) ?></strong><span><?= htmlspecialchars($rolSesion) ?></span></div><div class="user-avatar header-avatar"><?= htmlspecialchars(strtoupper(substr($nombreSesion, 0, 1))) ?></div></div></header>
<section class="users-intro"><h2>Modificar equipo</h2><p>Actualiza los datos, clasificación y estado del equipo seleccionado.</p></section>
<section class="user-form-container"><?php if ($error !== ''): ?><div class="form-message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" action="editar.php?id=<?= (int)$idEquipo ?>" class="user-form"><input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCSRF) ?>">
<div class="form-section-title">Información del equipo</div><div class="form-grid">
<div class="form-group"><label for="codigo_inventario">Código de inventario *</label><input id="codigo_inventario" name="codigo_inventario" maxlength="50" value="<?= htmlspecialchars($equipo['codigo_inventario']) ?>" required></div>
<div class="form-group"><label for="nombre">Nombre *</label><input id="nombre" name="nombre" maxlength="100" value="<?= htmlspecialchars($equipo['nombre']) ?>" required></div>
<div class="form-group"><label for="id_tipo">Tipo *</label><select id="id_tipo" name="id_tipo" required><?php foreach ($tipos as $tipo): ?><option value="<?= (int)$tipo['id_tipo'] ?>" <?= (int)$equipo['id_tipo'] === (int)$tipo['id_tipo'] ? 'selected' : '' ?>><?= htmlspecialchars($tipo['nombre_tipo']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label for="id_estado">Estado *</label><select id="id_estado" name="id_estado" required><?php foreach ($estados as $estado): ?><option value="<?= (int)$estado['id_estado'] ?>" <?= (int)$equipo['id_estado'] === (int)$estado['id_estado'] ? 'selected' : '' ?>><?= htmlspecialchars($estado['nombre_estado']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label for="marca">Marca</label><input id="marca" name="marca" maxlength="80" value="<?= htmlspecialchars($equipo['marca'] ?? '') ?>"></div>
<div class="form-group"><label for="modelo">Modelo</label><input id="modelo" name="modelo" maxlength="80" value="<?= htmlspecialchars($equipo['modelo'] ?? '') ?>"></div>
<div class="form-group"><label for="numero_serie">Número de serie</label><input id="numero_serie" name="numero_serie" maxlength="100" value="<?= htmlspecialchars($equipo['numero_serie'] ?? '') ?>"></div>
<div class="form-group"><label for="ubicacion">Ubicación</label><input id="ubicacion" name="ubicacion" maxlength="150" value="<?= htmlspecialchars($equipo['ubicacion'] ?? '') ?>"></div>
<div class="form-group form-group-full"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" rows="4"><?= htmlspecialchars($equipo['descripcion'] ?? '') ?></textarea></div>
</div><div class="form-actions"><a href="listar.php" class="form-button cancel-button">Cancelar</a><button class="form-button submit-button" type="submit">Guardar cambios</button></div></form></section>
<footer class="dashboard-footer">SIGPET © 2026 | Sistema de Gestión de Préstamos de Equipos Tecnológicos</footer></main></div></body></html>
