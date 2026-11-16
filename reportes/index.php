<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('reportes.consultar');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/bitacora.php';

$tInicio = microtime(true);

$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';
$idUsuarioSesion = (int)($_SESSION['id_usuario'] ?? 0);

$fechaDesde = trim($_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days')));
$fechaHasta = trim($_GET['fecha_hasta'] ?? date('Y-m-d'));
$idTipoFiltro = filter_var($_GET['tipo'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$tabActiva = trim($_GET['tab'] ?? 'solicitados');
if (!in_array($tabActiva, ['solicitados', 'atrasados', 'uso'], true)) {
    $tabActiva = 'solicitados';
}

function urlReporteTab(string $tab): string
{
    $q = array_merge($_GET, ['tab' => $tab]);
    return 'index.php?' . http_build_query($q);
}

$error = '';
$desdeObj = DateTime::createFromFormat('!Y-m-d', $fechaDesde);
$hastaObj = DateTime::createFromFormat('!Y-m-d', $fechaHasta);

if (!$desdeObj || $desdeObj->format('Y-m-d') !== $fechaDesde) {
    $fechaDesde = date('Y-m-d', strtotime('-30 days'));
    $error = 'La fecha inicial no era válida y se restableció a los últimos 30 días.';
}
if (!$hastaObj || $hastaObj->format('Y-m-d') !== $fechaHasta) {
    $fechaHasta = date('Y-m-d');
    $error = 'La fecha final no era válida y se restableció a hoy.';
}
if ($fechaHasta < $fechaDesde) {
    $tmp = $fechaDesde;
    $fechaDesde = $fechaHasta;
    $fechaHasta = $tmp;
}

$tipos = $conexion->query("SELECT id_tipo, nombre_tipo FROM tipos_equipo ORDER BY nombre_tipo")->fetch_all(MYSQLI_ASSOC);

$filtroTipoSql = $idTipoFiltro ? ' AND e.id_tipo = ? ' : '';

// 1. Resumen general del período.
$sqlResumen = "SELECT
        COUNT(DISTINCT s.id_solicitud) AS total_solicitudes,
        SUM(CASE WHEN s.estado_solicitud = 'Aprobada' THEN 1 ELSE 0 END) AS aprobadas,
        SUM(CASE WHEN s.estado_solicitud = 'Pendiente' THEN 1 ELSE 0 END) AS pendientes,
        SUM(CASE WHEN s.estado_solicitud = 'Rechazada' THEN 1 ELSE 0 END) AS rechazadas,
        SUM(CASE WHEN s.es_prioridad = 1 THEN 1 ELSE 0 END) AS prioritarias_docente,
        COUNT(DISTINCT p.id_prestamo) AS total_prestamos,
        SUM(CASE WHEN p.estado_prestamo = 'Devuelto' THEN 1 ELSE 0 END) AS devueltos,
        SUM(CASE WHEN p.estado_prestamo = 'Prestado' THEN 1 ELSE 0 END) AS activos
     FROM solicitudes_prestamo s
     INNER JOIN equipos e ON e.id_equipo = s.id_equipo
     LEFT JOIN prestamos p ON p.id_solicitud = s.id_solicitud
     WHERE (DATE(s.fecha_solicitud) BETWEEN ? AND ? OR s.fecha_inicio BETWEEN ? AND ?)
     {$filtroTipoSql}";

$stmt = $conexion->prepare($sqlResumen);
if ($idTipoFiltro) {
    $stmt->bind_param('ssssi', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta, $idTipoFiltro);
} else {
    $stmt->bind_param('ssss', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta);
}
$stmt->execute();
$resumen = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. Equipos más solicitados en el período (RF-17).
$sqlMasSolicitados = "SELECT
        e.id_equipo,
        e.codigo_inventario,
        e.nombre AS nombre_equipo,
        e.marca,
        e.modelo,
        t.nombre_tipo,
        COUNT(s.id_solicitud) AS total_solicitudes,
        SUM(CASE WHEN s.estado_solicitud = 'Aprobada' THEN 1 ELSE 0 END) AS solicitudes_aprobadas,
        SUM(CASE WHEN s.estado_solicitud = 'Rechazada' THEN 1 ELSE 0 END) AS solicitudes_rechazadas,
        COUNT(p.id_prestamo) AS prestamos_entregados
     FROM solicitudes_prestamo s
     INNER JOIN equipos e ON e.id_equipo = s.id_equipo
     INNER JOIN tipos_equipo t ON t.id_tipo = e.id_tipo
     LEFT JOIN prestamos p ON p.id_solicitud = s.id_solicitud
     WHERE (DATE(s.fecha_solicitud) BETWEEN ? AND ? OR s.fecha_inicio BETWEEN ? AND ?)
     {$filtroTipoSql}
     GROUP BY e.id_equipo, e.codigo_inventario, e.nombre, e.marca, e.modelo, t.nombre_tipo
     ORDER BY total_solicitudes DESC, prestamos_entregados DESC, e.nombre ASC";

$stmt = $conexion->prepare($sqlMasSolicitados);
if ($idTipoFiltro) {
    $stmt->bind_param('ssssi', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta, $idTipoFiltro);
} else {
    $stmt->bind_param('ssss', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta);
}
$stmt->execute();
$equiposMasSolicitados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 3. Préstamos atrasados (activos vencidos + devueltos con retraso en el período) (RF-17).
$sqlAtrasados = "SELECT
        p.id_prestamo,
        p.fecha_entrega,
        p.fecha_devolucion_real,
        p.estado_prestamo,
        s.fecha_inicio,
        s.fecha_devolucion_prevista,
        CASE
            WHEN p.estado_prestamo = 'Prestado' AND p.fecha_devolucion_real IS NULL
                THEN DATEDIFF(CURDATE(), s.fecha_devolucion_prevista)
            ELSE DATEDIFF(DATE(p.fecha_devolucion_real), s.fecha_devolucion_prevista)
        END AS dias_atraso,
        u.nombres,
        u.apellidos,
        u.correo_institucional,
        u.codigo_institucional,
        r.nombre_rol,
        e.codigo_inventario,
        e.nombre AS nombre_equipo,
        t.nombre_tipo
     FROM prestamos p
     INNER JOIN solicitudes_prestamo s ON s.id_solicitud = p.id_solicitud
     INNER JOIN usuarios u ON u.id_usuario = p.id_usuario
     INNER JOIN roles r ON r.id_rol = u.id_rol
     INNER JOIN equipos e ON e.id_equipo = p.id_equipo
     INNER JOIN tipos_equipo t ON t.id_tipo = e.id_tipo
     WHERE (
         (p.estado_prestamo = 'Prestado' AND p.fecha_devolucion_real IS NULL AND s.fecha_devolucion_prevista < CURDATE())
         OR (
             p.fecha_devolucion_real IS NOT NULL
             AND DATE(p.fecha_devolucion_real) > s.fecha_devolucion_prevista
             AND (DATE(p.fecha_entrega) BETWEEN ? AND ? OR s.fecha_devolucion_prevista BETWEEN ? AND ?)
         )
     )
     {$filtroTipoSql}
     ORDER BY FIELD(p.estado_prestamo, 'Prestado', 'Devuelto'), dias_atraso DESC";

$stmt = $conexion->prepare($sqlAtrasados);
if ($idTipoFiltro) {
    $stmt->bind_param('ssssi', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta, $idTipoFiltro);
} else {
    $stmt->bind_param('ssss', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta);
}
$stmt->execute();
$prestamosAtrasados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 4. Uso por período agrupado por tipo de equipo y por rol (RF-17 / RN-06).
$sqlUsoPorTipo = "SELECT
        t.nombre_tipo,
        COUNT(DISTINCT s.id_solicitud) AS total_solicitudes,
        COUNT(DISTINCT p.id_prestamo) AS total_prestamos,
        SUM(CASE WHEN p.estado_prestamo = 'Devuelto' THEN 1 ELSE 0 END) AS devueltos,
        SUM(CASE WHEN p.estado_prestamo = 'Prestado' THEN 1 ELSE 0 END) AS en_curso
     FROM tipos_equipo t
     LEFT JOIN equipos e ON e.id_tipo = t.id_tipo
     LEFT JOIN solicitudes_prestamo s ON s.id_equipo = e.id_equipo
        AND (DATE(s.fecha_solicitud) BETWEEN ? AND ? OR s.fecha_inicio BETWEEN ? AND ?)
     LEFT JOIN prestamos p ON p.id_solicitud = s.id_solicitud
     WHERE 1 = 1 " . ($idTipoFiltro ? " AND t.id_tipo = ? " : "") . "
     GROUP BY t.id_tipo, t.nombre_tipo
     ORDER BY total_solicitudes DESC, t.nombre_tipo ASC";

$stmt = $conexion->prepare($sqlUsoPorTipo);
if ($idTipoFiltro) {
    $stmt->bind_param('ssssi', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta, $idTipoFiltro);
} else {
    $stmt->bind_param('ssss', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta);
}
$stmt->execute();
$usoPorTipo = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sqlUsoPorRol = "SELECT
        r.nombre_rol,
        COUNT(DISTINCT s.id_solicitud) AS total_solicitudes,
        SUM(CASE WHEN s.es_prioridad = 1 THEN 1 ELSE 0 END) AS con_prioridad,
        COUNT(DISTINCT p.id_prestamo) AS total_prestamos
     FROM solicitudes_prestamo s
     INNER JOIN usuarios u ON u.id_usuario = s.id_usuario
     INNER JOIN roles r ON r.id_rol = u.id_rol
     INNER JOIN equipos e ON e.id_equipo = s.id_equipo
     LEFT JOIN prestamos p ON p.id_solicitud = s.id_solicitud
     WHERE (DATE(s.fecha_solicitud) BETWEEN ? AND ? OR s.fecha_inicio BETWEEN ? AND ?)
     {$filtroTipoSql}
     GROUP BY r.id_rol, r.nombre_rol
     ORDER BY total_solicitudes DESC";

$stmt = $conexion->prepare($sqlUsoPorRol);
if ($idTipoFiltro) {
    $stmt->bind_param('ssssi', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta, $idTipoFiltro);
} else {
    $stmt->bind_param('ssss', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta);
}
$stmt->execute();
$usoPorRol = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 5. Detalle de uso (préstamos del período).
$sqlDetalleUso = "SELECT
        p.id_prestamo,
        p.fecha_entrega,
        p.fecha_devolucion_real,
        p.estado_prestamo,
        s.fecha_inicio,
        s.fecha_devolucion_prevista,
        s.es_prioridad,
        u.nombres,
        u.apellidos,
        r.nombre_rol,
        e.codigo_inventario,
        e.nombre AS nombre_equipo,
        t.nombre_tipo
     FROM prestamos p
     INNER JOIN solicitudes_prestamo s ON s.id_solicitud = p.id_solicitud
     INNER JOIN usuarios u ON u.id_usuario = p.id_usuario
     INNER JOIN roles r ON r.id_rol = u.id_rol
     INNER JOIN equipos e ON e.id_equipo = p.id_equipo
     INNER JOIN tipos_equipo t ON t.id_tipo = e.id_tipo
     WHERE (DATE(p.fecha_entrega) BETWEEN ? AND ? OR s.fecha_inicio BETWEEN ? AND ?)
     {$filtroTipoSql}
     ORDER BY p.fecha_entrega DESC";

$stmt = $conexion->prepare($sqlDetalleUso);
if ($idTipoFiltro) {
    $stmt->bind_param('ssssi', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta, $idTipoFiltro);
} else {
    $stmt->bind_param('ssss', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta);
}
$stmt->execute();
$detalleUso = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$tiempoMs = round((microtime(true) - $tInicio) * 1000, 2);

if (isset($_GET['consultar'])) {
    registrarBitacora(
        $conexion,
        $idUsuarioSesion,
        'Reportes',
        'Consulta de reporte',
        'reportes',
        null,
        "Generación de reporte del período {$fechaDesde} al {$fechaHasta}" . ($idTipoFiltro ? " (tipo #{$idTipoFiltro})" : '') . " en {$tiempoMs} ms."
    );
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes administrativos | SIGPET</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../CSS/base.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <link rel="stylesheet" href="../CSS/inventario.css">
    <link rel="stylesheet" href="../CSS/prestamos.css">
</head>
<body class="dashboard-body">
<div class="dashboard-layout">
<?php $rutaBase = '../'; $paginaActiva = 'reportes'; require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="dashboard-main">
    <header class="dashboard-header">
        <div>
            <span class="header-subtitle">REPORTES Y ESTADÍSTICAS</span>
            <h1>Reportes del sistema</h1>
            <p class="header-desc">Equipos más solicitados, préstamos atrasados y uso por período. Generado en <strong><?= number_format($tiempoMs, 2) ?> ms</strong>.</p>
        </div>
        <div class="header-actions no-print">
            <a class="primary-link" href="exportar.php?fecha_desde=<?= urlencode($fechaDesde) ?>&amp;fecha_hasta=<?= urlencode($fechaHasta) ?>&amp;tipo=<?= $idTipoFiltro ? (int)$idTipoFiltro : '' ?>">Exportar CSV</a>
            <button type="button" class="filter-button" onclick="window.print()">Imprimir / PDF</button>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <div class="warning-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="inventory-container">
        <div class="preset-links no-print">
            <span class="table-subtext" style="margin:0;">Períodos rápidos:</span>
            <a class="preset-chip" href="index.php?fecha_desde=<?= date('Y-m-d', strtotime('-7 days')) ?>&amp;fecha_hasta=<?= date('Y-m-d') ?>&amp;consultar=1">Últimos 7 días</a>
            <a class="preset-chip" href="index.php?fecha_desde=<?= date('Y-m-d', strtotime('-30 days')) ?>&amp;fecha_hasta=<?= date('Y-m-d') ?>&amp;consultar=1">Últimos 30 días</a>
            <a class="preset-chip" href="index.php?fecha_desde=<?= date('Y-m-01') ?>&amp;fecha_hasta=<?= date('Y-m-t') ?>&amp;consultar=1">Mes actual</a>
            <a class="preset-chip" href="index.php?fecha_desde=<?= date('Y-01-01') ?>&amp;fecha_hasta=<?= date('Y-12-31') ?>&amp;consultar=1">Año <?= date('Y') ?></a>
        </div>

        <form method="GET" action="index.php" class="loan-filter no-print">
            <input type="hidden" name="consultar" value="1">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tabActiva) ?>">
            <div>
                <label for="fecha_desde">Desde</label>
                <input id="fecha_desde" name="fecha_desde" type="date" value="<?= htmlspecialchars($fechaDesde) ?>" required>
            </div>
            <div>
                <label for="fecha_hasta">Hasta</label>
                <input id="fecha_hasta" name="fecha_hasta" type="date" value="<?= htmlspecialchars($fechaHasta) ?>" required>
            </div>
            <div>
                <label for="tipo">Tipo de equipo</label>
                <select id="tipo" name="tipo">
                    <option value="">Todos los tipos</option>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= (int)$t['id_tipo'] ?>" <?= $idTipoFiltro === (int)$t['id_tipo'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['nombre_tipo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="action-buttons">
                <button type="submit" class="filter-button">Generar reporte</button>
            </div>
        </form>

        <div class="kpi-grid">
            <div class="kpi-card">
                <span>Solicitudes del período</span>
                <strong><?= (int)($resumen['total_solicitudes'] ?? 0) ?></strong>
            </div>
            <div class="kpi-card kpi-success">
                <span>Préstamos entregados</span>
                <strong><?= (int)($resumen['total_prestamos'] ?? 0) ?></strong>
            </div>
            <div class="kpi-card kpi-danger">
                <span>Préstamos atrasados</span>
                <strong><?= count($prestamosAtrasados) ?></strong>
            </div>
            <div class="kpi-card">
                <span>Solicitudes prioritarias (docentes)</span>
                <strong><?= (int)($resumen['prioritarias_docente'] ?? 0) ?></strong>
            </div>
        </div>
    </section>

    <nav class="sigpet-tabs no-print" aria-label="Secciones de reportes">
        <a class="sigpet-tab <?= $tabActiva === 'solicitados' ? 'active' : '' ?>" href="<?= htmlspecialchars(urlReporteTab('solicitados')) ?>">
            Equipos más solicitados
            <span class="sigpet-tab-count"><?= count($equiposMasSolicitados) ?></span>
        </a>
        <a class="sigpet-tab <?= $tabActiva === 'atrasados' ? 'active' : '' ?>" href="<?= htmlspecialchars(urlReporteTab('atrasados')) ?>">
            Préstamos atrasados
            <span class="sigpet-tab-count"><?= count($prestamosAtrasados) ?></span>
        </a>
        <a class="sigpet-tab <?= $tabActiva === 'uso' ? 'active' : '' ?>" href="<?= htmlspecialchars(urlReporteTab('uso')) ?>">
            Uso por período
            <span class="sigpet-tab-count"><?= count($detalleUso) ?></span>
        </a>
    </nav>

    <?php if ($tabActiva === 'solicitados'): ?>
    <section class="inventory-container">
        <div class="inventory-toolbar">
            <div>
                <h3>Equipos más solicitados</h3>
                <p>Ranking de demanda entre <?= htmlspecialchars(date('d/m/Y', strtotime($fechaDesde))) ?> y <?= htmlspecialchars(date('d/m/Y', strtotime($fechaHasta))) ?>.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Equipo</th>
                        <th>Tipo</th>
                        <th>Total solicitudes</th>
                        <th>Aprobadas</th>
                        <th>Rechazadas</th>
                        <th>Préstamos entregados</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($equiposMasSolicitados)): ?>
                    <?php foreach ($equiposMasSolicitados as $idx => $eq): ?>
                        <tr>
                            <td><strong><?= $idx + 1 ?></strong></td>
                            <td>
                                <span class="sigpet-equip-tag"><?= htmlspecialchars($eq['codigo_inventario']) ?></span>
                                <strong style="margin-left:6px;"><?= htmlspecialchars($eq['nombre_equipo']) ?></strong>
                                <small class="table-subtext"><?= htmlspecialchars(trim(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? ''))) ?></small>
                            </td>
                            <td><?= htmlspecialchars($eq['nombre_tipo']) ?></td>
                            <td><strong><?= (int)$eq['total_solicitudes'] ?></strong></td>
                            <td><?= (int)$eq['solicitudes_aprobadas'] ?></td>
                            <td><?= (int)$eq['solicitudes_rechazadas'] ?></td>
                            <td><?= (int)$eq['prestamos_entregados'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="empty-table">No hay solicitudes registradas para este período.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php elseif ($tabActiva === 'atrasados'): ?>
    <section class="inventory-container">
        <div class="inventory-toolbar">
            <div>
                <h3>Préstamos atrasados</h3>
                <p>Préstamos activos vencidos y devoluciones realizadas fuera de la fecha prevista.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Préstamo</th>
                        <th>Solicitante</th>
                        <th>Equipo</th>
                        <th>Devolución prevista</th>
                        <th>Devolución real</th>
                        <th>Días de atraso</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($prestamosAtrasados)): ?>
                    <?php foreach ($prestamosAtrasados as $pa): ?>
                        <tr>
                            <td><strong>#<?= (int)$pa['id_prestamo'] ?></strong></td>
                            <td>
                                <?= htmlspecialchars($pa['nombres'] . ' ' . $pa['apellidos']) ?>
                                <small class="table-subtext"><?= htmlspecialchars($pa['nombre_rol'] . ' · ' . ($pa['codigo_institucional'] ?? $pa['correo_institucional'])) ?></small>
                            </td>
                            <td>
                                <span class="sigpet-equip-tag"><?= htmlspecialchars($pa['codigo_inventario']) ?></span>
                                <strong style="margin-left:6px;"><?= htmlspecialchars($pa['nombre_equipo']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($pa['fecha_devolucion_prevista']))) ?></td>
                            <td><?= $pa['fecha_devolucion_real'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($pa['fecha_devolucion_real']))) : 'Pendiente de devolver' ?></td>
                            <td><span class="loan-status status-atrasado"><?= max(1, (int)$pa['dias_atraso']) ?> día(s)</span></td>
                            <td><span class="loan-status status-<?= htmlspecialchars(strtolower($pa['estado_prestamo'])) ?>"><?= htmlspecialchars($pa['estado_prestamo']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="empty-table">No se registran préstamos atrasados en el criterio seleccionado.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php else: ?>
    <section class="inventory-container">
        <div class="inventory-toolbar">
            <div>
                <h3>Uso por período</h3>
                <p>Distribución por tipo de equipo, rol del solicitante y detalle de préstamos.</p>
            </div>
        </div>

        <div class="section-split" style="margin-bottom:18px;">
            <div class="table-responsive">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Tipo de equipo</th>
                            <th>Solicitudes</th>
                            <th>Préstamos</th>
                            <th>Devueltos</th>
                            <th>En curso</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($usoPorTipo as $ut): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ut['nombre_tipo']) ?></strong></td>
                            <td><?= (int)$ut['total_solicitudes'] ?></td>
                            <td><?= (int)$ut['total_prestamos'] ?></td>
                            <td><?= (int)($ut['devueltos'] ?? 0) ?></td>
                            <td><?= (int)($ut['en_curso'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-responsive">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Rol del solicitante</th>
                            <th>Solicitudes</th>
                            <th>Con prioridad académica</th>
                            <th>Préstamos entregados</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($usoPorRol)): ?>
                        <?php foreach ($usoPorRol as $ur): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ur['nombre_rol']) ?></strong></td>
                                <td><?= (int)$ur['total_solicitudes'] ?></td>
                                <td><?= (int)$ur['con_prioridad'] ?></td>
                                <td><?= (int)$ur['total_prestamos'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="empty-table">Sin uso registrado por rol en este período.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-responsive">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Préstamo</th>
                        <th>Solicitante</th>
                        <th>Equipo</th>
                        <th>Fecha entrega</th>
                        <th>Período solicitado</th>
                        <th>Devolución real</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($detalleUso)): ?>
                    <?php foreach ($detalleUso as $du): ?>
                        <tr>
                            <td><strong>#<?= (int)$du['id_prestamo'] ?></strong></td>
                            <td>
                                <?= htmlspecialchars($du['nombres'] . ' ' . $du['apellidos']) ?>
                                <small class="table-subtext"><?= htmlspecialchars($du['nombre_rol']) ?></small>
                            </td>
                            <td>
                                <span class="sigpet-equip-tag"><?= htmlspecialchars($du['codigo_inventario']) ?></span>
                                <strong style="margin-left:6px;"><?= htmlspecialchars($du['nombre_equipo']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($du['fecha_entrega']))) ?></td>
                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($du['fecha_inicio'])) . ' al ' . date('d/m/Y', strtotime($du['fecha_devolucion_prevista']))) ?></td>
                            <td><?= $du['fecha_devolucion_real'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($du['fecha_devolucion_real']))) : 'En curso' ?></td>
                            <td><span class="loan-status status-<?= htmlspecialchars(strtolower($du['estado_prestamo'])) ?>"><?= htmlspecialchars($du['estado_prestamo']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="empty-table">No hay préstamos entregados dentro del período seleccionado.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <footer class="dashboard-footer">SIGPET © 2026 | Sistema de Gestión de Préstamos de Equipos Tecnológicos</footer>
</main>
</div>
</body>
</html>
<?php $conexion->close(); ?>
