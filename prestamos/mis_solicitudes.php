<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('prestamos.solicitar');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';
$idUsuarioSesion = (int)$_SESSION['id_usuario'];

$stmt = $conexion->prepare(
    "SELECT s.id_solicitud, s.fecha_inicio, s.fecha_devolucion_prevista,
            s.proposito, s.es_prioridad, s.estado_solicitud, s.motivo_rechazo,
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
$tokenCSRF = obtenerTokenCSRF();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis solicitudes | SIGPET</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../CSS/base.css"><link rel="stylesheet" href="../CSS/dashboard.css"><link rel="stylesheet" href="../CSS/inventario.css"><link rel="stylesheet" href="../CSS/prestamos.css">
    <link rel="stylesheet" href="../CSS/theme-profesional.css">
</head>
<body class="dashboard-body">
<div class="dashboard-layout">
<?php $rutaBase = '../'; $paginaActiva = 'mis_solicitudes'; require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="dashboard-main">
<header class="dashboard-header">
    <div>
        <span class="header-subtitle">GESTIÓN DE PRÉSTAMOS</span>
        <h1>Mis solicitudes</h1>
        <p class="header-desc">Consulta el estado y los detalles de tus solicitudes de préstamo o reserva.</p>
    </div>
    <div class="header-actions">
        <a href="solicitar.php" class="primary-link">+ Nueva solicitud</a>
    </div>
</header>
<?php if (isset($_GET['registrada'])): ?><div class="success-message">La solicitud se registró correctamente y quedó en estado Pendiente.</div><?php endif; ?>
<?php if (isset($_GET['cancelada'])): ?><div class="success-message">La solicitud pendiente fue cancelada (el historial se conserva).</div><?php endif; ?>
<?php if (isset($_GET['error'])): ?><div class="error-message">No fue posible cancelar la solicitud seleccionada.</div><?php endif; ?>
<section class="inventory-container">
<div class="table-responsive"><table class="inventory-table"><thead><tr><th>ID</th><th>Equipo</th><th>Período</th><th>Propósito</th><th>Estado</th><th>Fecha solicitud</th><th>Detalle / Acción</th></tr></thead><tbody>
<?php if ($solicitudes->num_rows > 0): while ($solicitud = $solicitudes->fetch_assoc()): ?>
<?php
$estadoMiSol = (string) $solicitud['estado_solicitud'];
$toneMiSol = $estadoMiSol === 'Aprobada' ? 'success'
    : ($estadoMiSol === 'Rechazada' ? 'danger'
    : ($estadoMiSol === 'Cancelada' ? 'neutral' : 'pendiente'));
$seccionesMiSol = [
    [
        'label' => 'Propósito de uso',
        'text'  => (string) ($solicitud['proposito'] ?? ''),
    ],
];
if (!empty($solicitud['motivo_rechazo'])) {
    $seccionesMiSol[] = [
        'label' => 'Motivo de rechazo',
        'text'  => (string) $solicitud['motivo_rechazo'],
        'tone'  => 'danger',
    ];
}
$metaMiSol = [
    [
        'label' => 'Fecha de inicio',
        'value' => $solicitud['fecha_inicio'],
    ],
    [
        'label' => 'Devolución prevista',
        'value' => $solicitud['fecha_devolucion_prevista'],
    ],
    [
        'label' => 'Fecha de solicitud',
        'value' => date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])),
    ],
    [
        'label' => 'Prioridad académica',
        'value' => ((int) $solicitud['es_prioridad'] === 1) ? 'Prioridad académica' : 'Estándar',
    ],
];
if (!empty($solicitud['fecha_decision'])) {
    $metaMiSol[] = [
        'label' => 'Fecha de decisión',
        'value' => date('d/m/Y H:i', strtotime($solicitud['fecha_decision'])),
    ];
}
$payloadMiSol = [
    'eyebrow'  => 'Mi solicitud #' . (int) $solicitud['id_solicitud'],
    'title'    => $solicitud['codigo_inventario'] . ' — ' . $solicitud['nombre_equipo'],
    'subtitle' => $solicitud['nombre_tipo'],
    'badge'    => [
        'label' => $estadoMiSol,
        'tone'  => $toneMiSol,
    ],
    'meta'     => $metaMiSol,
    'sections' => $seccionesMiSol,
];
$detalleMiSolJson = htmlspecialchars(
    json_encode($payloadMiSol, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
    ENT_QUOTES,
    'UTF-8'
);
?>
<tr class="sigpet-row-clickable" data-detail="<?= $detalleMiSolJson ?>">
<td>
    <span class="sigpet-equip-tag">#<?= (int)$solicitud['id_solicitud'] ?></span>
</td>
<td><?= htmlspecialchars($solicitud['codigo_inventario'] . ' — ' . $solicitud['nombre_equipo']) ?><small class="table-subtext"><?= htmlspecialchars($solicitud['nombre_tipo']) ?></small></td>
<td><?= htmlspecialchars($solicitud['fecha_inicio']) ?><small class="table-subtext">Hasta <?= htmlspecialchars($solicitud['fecha_devolucion_prevista']) ?></small></td>
<td>
    <span class="sigpet-cell-clamp"><?= htmlspecialchars($solicitud['proposito']) ?></span>
    <?php if ((int)$solicitud['es_prioridad'] === 1): ?>
        <span class="loan-status status-prioridad" style="display:inline-flex;margin-top:4px;">Prioridad académica</span>
    <?php endif; ?>
</td>
<td><span class="loan-status status-<?= htmlspecialchars(strtolower($solicitud['estado_solicitud'])) ?>"><?= htmlspecialchars($solicitud['estado_solicitud']) ?></span></td>
<td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud']))) ?></td>
<td>
    <div class="action-buttons">
        <button type="button" class="sigpet-detail-btn sigpet-detail-trigger" aria-label="Ver detalle de la solicitud #<?= (int)$solicitud['id_solicitud'] ?>">
            <?= sigpetIcono('eye', 15) ?>
            <span>Ver detalle</span>
        </button>
        <?php if ($solicitud['estado_solicitud'] === 'Pendiente'): ?>
            <form method="POST" action="cancelar.php" onsubmit="return confirm('¿Deseas cancelar esta solicitud pendiente?');">
                <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCSRF) ?>">
                <input type="hidden" name="id_solicitud" value="<?= (int)$solicitud['id_solicitud'] ?>">
                <button type="submit" class="action-button reject-action">Cancelar</button>
            </form>
        <?php endif; ?>
    </div>
</td>
</tr>
<?php endwhile; else: ?><tr><td colspan="7" class="empty-table">Todavía no has registrado solicitudes.</td></tr><?php endif; ?>
</tbody></table></div>
</section>
<footer class="dashboard-footer">SIGPET © 2026 | Sistema de Gestión de Préstamos de Equipos Tecnológicos</footer>
</main></div>
</body></html>
<?php $stmt->close(); $conexion->close(); ?>
