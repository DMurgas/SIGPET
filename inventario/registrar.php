<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('inventario.registrar');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';
$tipos = $conexion->query('SELECT id_tipo, nombre_tipo FROM tipos_equipo ORDER BY nombre_tipo')->fetch_all(MYSQLI_ASSOC);
$error = '';
$datos = ['codigo_inventario'=>'', 'nombre'=>'', 'id_tipo'=>'', 'marca'=>'', 'modelo'=>'', 'numero_serie'=>'', 'descripcion'=>'', 'ubicacion'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF()) {
        $error = 'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.';
    } else {
        foreach ($datos as $campo => $valor) { $datos[$campo] = trim($_POST[$campo] ?? ''); }
        $idTipo = filter_var($datos['id_tipo'], FILTER_VALIDATE_INT);
        if ($datos['codigo_inventario'] === '' || $datos['nombre'] === '' || !$idTipo) {
            $error = 'El código, nombre y tipo de equipo son obligatorios.';
        } else {
            $stmt = $conexion->prepare('SELECT id_equipo FROM equipos WHERE codigo_inventario = ? LIMIT 1');
            $stmt->bind_param('s', $datos['codigo_inventario']);
            $stmt->execute();
            $codigoDuplicado = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            $serieDuplicada = false;
            if ($datos['numero_serie'] !== '') {
                $stmt = $conexion->prepare('SELECT id_equipo FROM equipos WHERE numero_serie = ? LIMIT 1');
                $stmt->bind_param('s', $datos['numero_serie']);
                $stmt->execute();
                $serieDuplicada = $stmt->get_result()->num_rows > 0;
                $stmt->close();
            }
            $stmt = $conexion->prepare('SELECT id_tipo FROM tipos_equipo WHERE id_tipo = ?');
            $stmt->bind_param('i', $idTipo);
            $stmt->execute();
            $tipoValido = $stmt->get_result()->num_rows === 1;
            $stmt->close();

            if ($codigoDuplicado) {
                $error = 'Ya existe un equipo con ese código de inventario.';
            } elseif ($serieDuplicada) {
                $error = 'Ya existe un equipo con ese número de serie.';
            } elseif (!$tipoValido) {
                $error = 'El tipo de equipo seleccionado no es válido.';
            } else {
                $idEstadoDisponible = (int)$conexion->query("SELECT id_estado FROM estados_equipo WHERE nombre_estado = 'Disponible' LIMIT 1")->fetch_assoc()['id_estado'];
                $marca = $datos['marca'] !== '' ? $datos['marca'] : null;
                $modelo = $datos['modelo'] !== '' ? $datos['modelo'] : null;
                $serie = $datos['numero_serie'] !== '' ? $datos['numero_serie'] : null;
                $descripcion = $datos['descripcion'] !== '' ? $datos['descripcion'] : null;
                $ubicacion = $datos['ubicacion'] !== '' ? $datos['ubicacion'] : null;
                $stmt = $conexion->prepare('INSERT INTO equipos (id_tipo, id_estado, codigo_inventario, nombre, marca, modelo, numero_serie, descripcion, ubicacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('iisssssss', $idTipo, $idEstadoDisponible, $datos['codigo_inventario'], $datos['nombre'], $marca, $modelo, $serie, $descripcion, $ubicacion);
                $stmt->execute();
                $stmt->close();
                header('Location: listar.php?registrado=1');
                exit();
            }
        }
    }
}
$tokenCSRF = obtenerTokenCSRF();
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Registrar equipo | SIGPET</title><link rel="stylesheet" href="../CSS/base.css"><link rel="stylesheet" href="../CSS/dashboard.css"><link rel="stylesheet" href="../CSS/inventario.css"></head>
<body class="dashboard-body"><div class="dashboard-layout">
<?php $rutaBase = '../'; $paginaActiva = 'inventario'; require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="dashboard-main"><header class="dashboard-header"><div><span class="header-subtitle">CONTROL DE EQUIPOS TECNOLÓGICOS</span><h1>Registrar equipo</h1></div><div class="header-user"><div class="header-user-text"><strong><?= htmlspecialchars($nombreSesion . ' ' . $apellidoSesion) ?></strong><span><?= htmlspecialchars($rolSesion) ?></span></div><div class="user-avatar header-avatar"><?= htmlspecialchars(strtoupper(substr($nombreSesion, 0, 1))) ?></div></div></header>
<section class="users-intro"><h2>Nuevo equipo</h2><p>Registra un equipo tecnológico en el inventario institucional.</p></section>
<section class="user-form-container"><?php if ($error !== ''): ?><div class="form-message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" action="registrar.php" class="user-form"><input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCSRF) ?>">
<div class="form-section-title">Identificación del equipo</div><div class="form-grid">
<div class="form-group"><label for="codigo_inventario">Código de inventario *</label><input id="codigo_inventario" name="codigo_inventario" maxlength="50" value="<?= htmlspecialchars($datos['codigo_inventario']) ?>" required></div>
<div class="form-group"><label for="nombre">Nombre del equipo *</label><input id="nombre" name="nombre" maxlength="100" value="<?= htmlspecialchars($datos['nombre']) ?>" required></div>
<div class="form-group"><label for="id_tipo">Tipo de equipo *</label><select id="id_tipo" name="id_tipo" required><option value="">Selecciona un tipo</option><?php foreach ($tipos as $tipo): ?><option value="<?= (int)$tipo['id_tipo'] ?>" <?= (string)$datos['id_tipo'] === (string)$tipo['id_tipo'] ? 'selected' : '' ?>><?= htmlspecialchars($tipo['nombre_tipo']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label for="marca">Marca</label><input id="marca" name="marca" maxlength="80" value="<?= htmlspecialchars($datos['marca']) ?>"></div>
<div class="form-group"><label for="modelo">Modelo</label><input id="modelo" name="modelo" maxlength="80" value="<?= htmlspecialchars($datos['modelo']) ?>"></div>
<div class="form-group"><label for="numero_serie">Número de serie</label><input id="numero_serie" name="numero_serie" maxlength="100" value="<?= htmlspecialchars($datos['numero_serie']) ?>"></div>
<div class="form-group"><label for="ubicacion">Ubicación</label><input id="ubicacion" name="ubicacion" maxlength="150" value="<?= htmlspecialchars($datos['ubicacion']) ?>"></div>
<div class="form-group"><label>Estado inicial</label><input value="Disponible" disabled><small>Los equipos nuevos se registran inicialmente como disponibles.</small></div>
<div class="form-group form-group-full"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" rows="4"><?= htmlspecialchars($datos['descripcion']) ?></textarea></div>
</div><div class="form-actions"><a href="listar.php" class="form-button cancel-button">Cancelar</a><button class="form-button submit-button" type="submit">Registrar equipo</button></div></form></section>
<footer class="dashboard-footer">SIGPET © 2026 | Sistema de Gestión de Préstamos de Equipos Tecnológicos</footer></main></div></body></html>
