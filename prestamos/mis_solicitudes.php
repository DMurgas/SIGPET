<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('prestamos.solicitar');
require_once __DIR__ . '/../config/conexion.php';

$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';
$idUsuarioSesion = (int)$_SESSION['id_usuario'];

$stmt = $conexion->prepare(
    "SELECT s.id_solicitud, s.fecha_inicio, s.fecha_devolucion_prevista,
            s.proposito, s.estado_solicitud, s.motivo_rechazo,
            s.fecha_solicitud, s.fecha_decision,
            e.codigo_inventario, e.nombre AS nombre_equipo,
            t.nombre_tipo
     FROM solicitudes_prestamo s
     INNER JOIN equipos e ON e.id_equipo = s.id_equipo
     INNER JOIN tipos_equipo t ON t.id_tipo = e.id_tipo
     WHERE s.id_usuario = ?
     ORDER BY s.id_solicitud DESC"
);
$stmt->bind_param('i', $idUsuarioSesion);
$stmt->execute();
$solicitudes = $stmt->get_result();
$tokenCSRF = null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis solicitudes | SIGPET</title>
    <link rel="stylesheet" href="../CSS/base.css"><link rel="stylesheet" href="../CSS/dashboard.css"><link rel="stylesheet" href="../CSS/inventario.css"><link rel="stylesheet" href="../CSS/prestamos.css">
</head>
<body class="dashboard-body">
<div class="dashboard-layout">
<?php $rutaBase = '../'; $paginaActiva = 'prestamos'; require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="dashboard-main">
<header class="dashboard-header"><div><span class="header-subtitle">GESTIÓN DE PRÉSTAMOS</span><h1>Mis solicitudes</h1></div><div class="header-user"><div class="header-user-text"><strong><?= htmlspecialchars($nombreSesion . ' ' . $apellidoSesion) ?></strong><span><?= htmlspecialchars($rolSesion) ?></span></div><div class="user-avatar header-avatar"><?= htmlspecialchars(strtoupper(substr($nombreSesion, 0, 1))) ?></div></div></header>
<section class="users-intro"><h2>Solicitudes registradas</h2><p>Consulta el estado y los datos de tus solicitudes de préstamo.</p></section>
<?php if (isset($_GET['registrada'])): ?><div class="form-message success">La solicitud se registró correctamente y quedó en estado Pendiente.</div><?php endif; ?>
<section class="inventory-container">
<div class="inventory-toolbar"><div><h3>Historial de solicitudes</h3><p>Las solicitudes más recientes aparecen primero.</p></div><a href="solicitar.php" class="primary-link">+ Nueva solicitud</a></div>
<div class="table-responsive"><table class="inventory-table"><thead><tr><th>ID</th><th>Equipo</th><th>Período</th><th>Propósito</th><th>Estado</th><th>Fecha solicitud</th><th>Detalle</th></tr></thead><tbody>
<?php if ($solicitudes->num_rows > 0): while ($solicitud = $solicitudes->fetch_assoc()): ?>
<tr>
<td><strong>#<?= (int)$solicitud['id_solicitud'] ?></strong></td>
<td><?= htmlspecialchars($solicitud['codigo_inventario'] . ' — ' . $solicitud['nombre_equipo']) ?><small class="table-subtext"><?= htmlspecialchars($solicitud['nombre_tipo']) ?></small></td>
<td><?= htmlspecialchars($solicitud['fecha_inicio']) ?><small class="table-subtext">Hasta <?= htmlspecialchars($solicitud['fecha_devolucion_prevista']) ?></small></td>
<td><?= htmlspecialchars($solicitud['proposito']) ?></td>
<td><span class="loan-status status-<?= htmlspecialchars(strtolower($solicitud['estado_solicitud'])) ?>"><?= htmlspecialchars($solicitud['estado_solicitud']) ?></span></td>
<td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud']))) ?></td>
<td><?php if ($solicitud['estado_solicitud'] === 'Rechazada' && $solicitud['motivo_rechazo']): ?><span class="table-subtext">Motivo: <?= htmlspecialchars($solicitud['motivo_rechazo']) ?></span><?php elseif ($solicitud['fecha_decision']): ?><span class="table-subtext">Decidida: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($solicitud['fecha_decision']))) ?></span><?php else: ?><span class="table-subtext">Pendiente de revisión</span><?php endif; ?></td>
</tr>
<?php endwhile; else: ?><tr><td colspan="7" class="empty-table">Todavía no has registrado solicitudes.</td></tr><?php endif; ?>
</tbody></table></div>
</section>
<footer class="dashboard-footer">SIGPET © 2026 | Sistema de Gestión de Préstamos de Equipos Tecnológicos</footer>
</main></div>
</body></html>
<?php $stmt->close(); $conexion->close(); ?>
