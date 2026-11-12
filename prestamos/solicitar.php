<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('prestamos.solicitar');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';
$idUsuarioSesion = (int)($_SESSION['id_usuario'] ?? 0);

$datos = [
    'id_equipo' => trim($_GET['id_equipo'] ?? $_POST['id_equipo'] ?? ''),
    'fecha_inicio' => trim($_POST['fecha_inicio'] ?? date('Y-m-d')),
    'fecha_devolucion_prevista' => trim($_POST['fecha_devolucion_prevista'] ?? date('Y-m-d', strtotime('+1 day'))),
    'proposito' => trim($_POST['proposito'] ?? '')
];
$error = '';
$equipoSeleccionado = null;

$idEquipo = filter_var($datos['id_equipo'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($idEquipo) {
    $stmt = $conexion->prepare(
        "SELECT e.id_equipo, e.codigo_inventario, e.nombre, e.marca, e.modelo,
                e.numero_serie, e.ubicacion, s.id_estado, s.nombre_estado,
                t.nombre_tipo
         FROM equipos e
         INNER JOIN estados_equipo s ON s.id_estado = e.id_estado
         INNER JOIN tipos_equipo t ON t.id_tipo = e.id_tipo
         WHERE e.id_equipo = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $idEquipo);
    $stmt->execute();
    $equipoSeleccionado = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF()) {
        $error = 'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.';
    } elseif (!$idEquipo) {
        $error = 'El equipo seleccionado no es válido.';
    } elseif (!$equipoSeleccionado) {
        $error = 'El equipo seleccionado no existe.';
    } else {
        $inicioValido = DateTime::createFromFormat('!Y-m-d', $datos['fecha_inicio']);
        $devolucionValida = DateTime::createFromFormat('!Y-m-d', $datos['fecha_devolucion_prevista']);
        $hoy = new DateTime('today');

        if (!$inicioValido || $inicioValido->format('Y-m-d') !== $datos['fecha_inicio']) {
            $error = 'La fecha de inicio no es válida.';
        } elseif (!$devolucionValida || $devolucionValida->format('Y-m-d') !== $datos['fecha_devolucion_prevista']) {
            $error = 'La fecha prevista de devolución no es válida.';
        } elseif ($inicioValido < $hoy) {
            $error = 'La fecha de inicio no puede ser anterior a hoy.';
        } elseif ($devolucionValida <= $inicioValido) {
            $error = 'La fecha prevista de devolución debe ser posterior a la fecha de inicio.';
        } elseif ($datos['proposito'] === '') {
            $error = 'El propósito de la solicitud es obligatorio.';
        } elseif (mb_strlen($datos['proposito']) > 500) {
            $error = 'El propósito no puede superar los 500 caracteres.';
        } elseif ($equipoSeleccionado['nombre_estado'] !== 'Disponible') {
            $error = 'El equipo seleccionado ya no está disponible.';
        } else {
            // Evita solicitudes pendientes/aprobadas que se crucen en el mismo período.
            $stmt = $conexion->prepare(
                "SELECT id_solicitud
                 FROM solicitudes_prestamo
                 WHERE id_equipo = ?
                   AND estado_solicitud IN ('Pendiente', 'Aprobada')
                   AND fecha_inicio < ?
                   AND fecha_devolucion_prevista > ?
                 LIMIT 1"
            );
            $stmt->bind_param('iss', $idEquipo, $datos['fecha_devolucion_prevista'], $datos['fecha_inicio']);
            $stmt->execute();
            $solicitudExistente = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($solicitudExistente) {
                $error = 'El equipo ya tiene una solicitud activa que se cruza con las fechas seleccionadas.';
            } else {
                $stmt = $conexion->prepare(
                    "INSERT INTO solicitudes_prestamo
                        (id_usuario, id_equipo, fecha_inicio, fecha_devolucion_prevista, proposito, estado_solicitud)
                     VALUES (?, ?, ?, ?, ?, 'Pendiente')"
                );
                $stmt->bind_param(
                    'iisss',
                    $idUsuarioSesion,
                    $idEquipo,
                    $datos['fecha_inicio'],
                    $datos['fecha_devolucion_prevista'],
                    $datos['proposito']
                );
                $stmt->execute();
                $stmt->close();
                header('Location: mis_solicitudes.php?registrada=1');
                exit();
            }
        }
    }
}

$equiposDisponibles = $conexion->query(
    "SELECT e.id_equipo, e.codigo_inventario, e.nombre, e.marca, e.modelo, t.nombre_tipo
     FROM equipos e
     INNER JOIN estados_equipo s ON s.id_estado = e.id_estado
     INNER JOIN tipos_equipo t ON t.id_tipo = e.id_tipo
     WHERE s.nombre_estado = 'Disponible'
     ORDER BY e.nombre, e.codigo_inventario"
)->fetch_all(MYSQLI_ASSOC);

$tokenCSRF = obtenerTokenCSRF();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar préstamo | SIGPET</title>
    <link rel="stylesheet" href="../CSS/base.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <link rel="stylesheet" href="../CSS/usuarios.css">
    <link rel="stylesheet" href="../CSS/prestamos.css">
</head>
<body class="dashboard-body">
<div class="dashboard-layout">
<?php $rutaBase = '../'; $paginaActiva = 'prestamos'; require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="dashboard-main">
    <header class="dashboard-header">
        <div><span class="header-subtitle">GESTIÓN DE PRÉSTAMOS</span><h1>Solicitar préstamo</h1></div>
        <div class="header-user">
            <div class="header-user-text"><strong><?= htmlspecialchars($nombreSesion . ' ' . $apellidoSesion) ?></strong><span><?= htmlspecialchars($rolSesion) ?></span></div>
            <div class="user-avatar header-avatar"><?= htmlspecialchars(strtoupper(substr($nombreSesion, 0, 1))) ?></div>
        </div>
    </header>

    <section class="users-intro">
        <h2>Nueva solicitud</h2>
        <p>Selecciona un equipo disponible, define el período de uso e indica el propósito del préstamo.</p>
    </section>

    <section class="user-form-container">
        <?php if ($error !== ''): ?><div class="form-message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if (!$equiposDisponibles): ?><div class="form-message error">No hay equipos disponibles para solicitar en este momento.</div><?php endif; ?>

        <form method="POST" action="solicitar.php" class="user-form">
            <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCSRF) ?>">
            <div class="form-section-title">Datos de la solicitud</div>
            <div class="form-grid">
                <div class="form-group form-group-full">
                    <label for="id_equipo">Equipo *</label>
                    <select id="id_equipo" name="id_equipo" required <?= !$equiposDisponibles ? 'disabled' : '' ?>>
                        <option value="">Selecciona un equipo disponible</option>
                        <?php foreach ($equiposDisponibles as $equipo): ?>
                            <option value="<?= (int)$equipo['id_equipo'] ?>" <?= (string)$datos['id_equipo'] === (string)$equipo['id_equipo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($equipo['codigo_inventario'] . ' — ' . $equipo['nombre'] . ' (' . $equipo['nombre_tipo'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="fecha_inicio">Fecha de inicio *</label>
                    <input id="fecha_inicio" name="fecha_inicio" type="date" value="<?= htmlspecialchars($datos['fecha_inicio']) ?>" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label for="fecha_devolucion_prevista">Fecha prevista de devolución *</label>
                    <input id="fecha_devolucion_prevista" name="fecha_devolucion_prevista" type="date" value="<?= htmlspecialchars($datos['fecha_devolucion_prevista']) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                </div>
                <div class="form-group form-group-full">
                    <label for="proposito">Propósito del préstamo *</label>
                    <textarea id="proposito" name="proposito" rows="5" maxlength="500" required><?= htmlspecialchars($datos['proposito']) ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <a href="mis_solicitudes.php" class="form-button cancel-button">Cancelar</a>
                <button class="form-button submit-button" type="submit" <?= !$equiposDisponibles ? 'disabled' : '' ?>>Registrar solicitud</button>
            </div>
        </form>
    </section>
    <footer class="dashboard-footer">SIGPET © 2026 | Sistema de Gestión de Préstamos de Equipos Tecnológicos</footer>
</main>
</div>
</body>
</html>
<?php $conexion->close(); ?>
