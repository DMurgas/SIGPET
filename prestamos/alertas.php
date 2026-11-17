<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('alertas.consultar');

require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/alertas.php';
require_once __DIR__ . '/../includes/bitacora.php';

$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';
$idUsuarioSesion = (int)($_SESSION['id_usuario'] ?? 0);
$puedeGestionar = tienePermiso('alertas.gestionar');
$puedeVerPanelGlobal = $puedeGestionar || tienePermiso('reportes.consultar');

$mensajeExito = '';
$mensajeError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF()) {
        $mensajeError = 'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.';
    } else {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'sincronizar') {
            $resumen = sincronizarAlertasPrestamos($conexion, true);
            $mensajeExito = "Revisión completada: {$resumen['revisados']} préstamo(s) en seguimiento ({$resumen['atrasados']} atrasado(s), {$resumen['proximos']} próximo(s) a vencer), {$resumen['generadas']} nueva(s) alerta(s) generada(s).";
        } elseif ($accion === 'marcar_leida') {
            $idAlerta = filter_var($_POST['id_alerta'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($idAlerta) {
                $stmt = $conexion->prepare(
                    "UPDATE alertas
                     SET leida = 1, fecha_lectura = NOW()
                     WHERE id_alerta = ? AND id_usuario_destino = ? AND leida = 0"
                );
                $stmt->bind_param('ii', $idAlerta, $idUsuarioSesion);
                $stmt->execute();
                $stmt->close();
                $mensajeExito = 'Alerta marcada como leída.';
            }
        } elseif ($accion === 'marcar_todas_leidas') {
            $stmt = $conexion->prepare(
                "UPDATE alertas
                 SET leida = 1, fecha_lectura = NOW()
                 WHERE id_usuario_destino = ? AND resuelta = 0 AND leida = 0"
            );
            $stmt->bind_param('i', $idUsuarioSesion);
            $stmt->execute();
            $stmt->close();
            $mensajeExito = 'Todas tus alertas activas se marcaron como leídas.';
        }
    }
} else {
    sincronizarAlertasPrestamos($conexion, true);
}

$tabActiva = trim($_GET['tab'] ?? 'mias');
$tabsPermitidas = ['mias', 'resueltas'];
if ($puedeVerPanelGlobal) {
    $tabsPermitidas[] = 'global';
}
if (!in_array($tabActiva, $tabsPermitidas, true)) {
    $tabActiva = 'mias';
}

$stmtMias = $conexion->prepare(
    "SELECT
        a.id_alerta,
        a.id_prestamo,
        a.tipo_alerta,
        a.titulo,
        a.mensaje,
        a.leida,
        a.fecha_generacion,
        a.fecha_lectura,
        p.estado_prestamo,
        s.fecha_devolucion_prevista,
        DATEDIFF(s.fecha_devolucion_prevista, CURDATE()) AS dias_restantes,
        e.codigo_inventario,
        e.nombre AS nombre_equipo
     FROM alertas a
     INNER JOIN prestamos p ON p.id_prestamo = a.id_prestamo
     INNER JOIN solicitudes_prestamo s ON s.id_solicitud = p.id_solicitud
     INNER JOIN equipos e ON e.id_equipo = p.id_equipo
     WHERE a.id_usuario_destino = ?
       AND a.resuelta = 0
     ORDER BY FIELD(a.tipo_alerta, 'Atrasado', 'Proximo a vencer'), a.leida ASC, a.id_alerta DESC"
);
$stmtMias->bind_param('i', $idUsuarioSesion);
$stmtMias->execute();
$misAlertas = $stmtMias->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMias->close();

$misAtrasadas = 0;
$misProximas = 0;
$misNoLeidas = 0;
foreach ($misAlertas as $al) {
    if ($al['tipo_alerta'] === 'Atrasado') {
        $misAtrasadas++;
    } else {
        $misProximas++;
    }
    if ((int)$al['leida'] === 0) {
        $misNoLeidas++;
    }
}

$prestamosEnRiesgo = [];
if ($puedeVerPanelGlobal) {
    $diasVentana = DIAS_ALERTA_PROXIMO_VENCIMIENTO;
    $prestamosEnRiesgo = $conexion->query(
        "SELECT
            p.id_prestamo,
            p.fecha_entrega,
            s.fecha_devolucion_prevista,
            DATEDIFF(s.fecha_devolucion_prevista, CURDATE()) AS dias_restantes,
            u.nombres,
            u.apellidos,
            u.correo_institucional,
            u.codigo_institucional,
            r.nombre_rol,
            e.codigo_inventario,
            e.nombre AS nombre_equipo
         FROM prestamos p
         INNER JOIN solicitudes_prestamo s ON s.id_solicitud = p.id_solicitud
         INNER JOIN usuarios u ON u.id_usuario = p.id_usuario
         INNER JOIN roles r ON r.id_rol = u.id_rol
         INNER JOIN equipos e ON e.id_equipo = p.id_equipo
         WHERE p.estado_prestamo = 'Prestado'
           AND p.fecha_devolucion_real IS NULL
           AND s.fecha_devolucion_prevista <= DATE_ADD(CURDATE(), INTERVAL {$diasVentana} DAY)
         ORDER BY s.fecha_devolucion_prevista ASC"
    )->fetch_all(MYSQLI_ASSOC);
}

$stmtResueltas = $conexion->prepare(
    "SELECT
        a.id_alerta,
        a.id_prestamo,
        a.tipo_alerta,
        a.titulo,
        a.mensaje,
        a.fecha_generacion,
        p.fecha_devolucion_real,
        e.codigo_inventario,
        e.nombre AS nombre_equipo
     FROM alertas a
     INNER JOIN prestamos p ON p.id_prestamo = a.id_prestamo
     INNER JOIN equipos e ON e.id_equipo = p.id_equipo
     WHERE a.id_usuario_destino = ?
       AND a.resuelta = 1
     ORDER BY a.id_alerta DESC
     LIMIT 30"
);
$stmtResueltas->bind_param('i', $idUsuarioSesion);
$stmtResueltas->execute();
$alertasResueltas = $stmtResueltas->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtResueltas->close();

$tokenCSRF = obtenerTokenCSRF();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas y notificaciones | SIGPET</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../CSS/base.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <link rel="stylesheet" href="../CSS/inventario.css">
    <link rel="stylesheet" href="../CSS/prestamos.css">
    <link rel="stylesheet" href="../CSS/theme-profesional.css">
</head>
<body class="dashboard-body">
<div class="dashboard-layout">
<?php $rutaBase = '../'; $paginaActiva = 'alertas'; require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="dashboard-main">
    <header class="dashboard-header">
        <div>
            <span class="header-subtitle">NOTIFICACIONES Y SEGUIMIENTO</span>
            <h1>Alertas de vencimiento y atrasos</h1>
            <p class="header-desc">Seguimiento de préstamos activos próximos a vencer (hasta <?= DIAS_ALERTA_PROXIMO_VENCIMIENTO ?> días antes) y devoluciones atrasadas.</p>
        </div>
        <div class="header-actions">
            <form method="POST" action="alertas.php?tab=<?= htmlspecialchars($tabActiva) ?>" style="margin:0;">
                <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCSRF) ?>">
                <input type="hidden" name="accion" value="sincronizar">
                <button type="submit" class="filter-button">Revisar préstamos activos</button>
            </form>
        </div>
    </header>

    <?php if ($mensajeExito !== ''): ?>
        <div class="success-message"><?= htmlspecialchars($mensajeExito) ?></div>
    <?php endif; ?>
    <?php if ($mensajeError !== ''): ?>
        <div class="error-message"><?= htmlspecialchars($mensajeError) ?></div>
    <?php endif; ?>

    <div class="kpi-grid">
        <div class="kpi-card kpi-danger">
            <span>Alertas por atraso</span>
            <strong><?= $misAtrasadas ?></strong>
        </div>
        <div class="kpi-card kpi-warning">
            <span>Próximos a vencer</span>
            <strong><?= $misProximas ?></strong>
        </div>
        <div class="kpi-card">
            <span>Sin leer</span>
            <strong><?= $misNoLeidas ?></strong>
        </div>
        <?php if ($puedeVerPanelGlobal): ?>
        <div class="kpi-card kpi-danger">
            <span>Préstamos activos en riesgo</span>
            <strong><?= count($prestamosEnRiesgo) ?></strong>
        </div>
        <?php endif; ?>
    </div>

    <nav class="sigpet-tabs" aria-label="Vistas de alertas">
        <a class="sigpet-tab <?= $tabActiva === 'mias' ? 'active' : '' ?>" href="alertas.php?tab=mias">
            Mis alertas activas
            <span class="sigpet-tab-count"><?= count($misAlertas) ?></span>
        </a>
        <?php if ($puedeVerPanelGlobal): ?>
        <a class="sigpet-tab <?= $tabActiva === 'global' ? 'active' : '' ?>" href="alertas.php?tab=global">
            Monitor global
            <span class="sigpet-tab-count"><?= count($prestamosEnRiesgo) ?></span>
        </a>
        <?php endif; ?>
        <a class="sigpet-tab <?= $tabActiva === 'resueltas' ? 'active' : '' ?>" href="alertas.php?tab=resueltas">
            Historial resueltas
            <span class="sigpet-tab-count"><?= count($alertasResueltas) ?></span>
        </a>
    </nav>

    <section class="inventory-container">
        <?php if ($tabActiva === 'mias'): ?>
            <?php if ($misNoLeidas > 0): ?>
                <div class="inventory-toolbar">
                    <div>
                        <h3>Bandeja activa</h3>
                        <p>Las alertas se resuelven automáticamente cuando se registra la devolución.</p>
                    </div>
                    <form method="POST" action="alertas.php?tab=mias" style="margin:0;">
                        <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCSRF) ?>">
                        <input type="hidden" name="accion" value="marcar_todas_leidas">
                        <button type="submit" class="action-button edit-action">Marcar todas como leídas</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (!empty($misAlertas)): ?>
                <div class="sigpet-alerts-feed">
                    <?php foreach ($misAlertas as $al): ?>
                        <?php
                        $esAtrasado = $al['tipo_alerta'] === 'Atrasado';
                        $esLeida = (int)$al['leida'] === 1;
                        $diasRest = (int)$al['dias_restantes'];
                        if ($diasRest < 0) {
                            $selloValor = abs($diasRest) . 'd';
                            $selloEtiqueta = 'Atraso';
                        } elseif ($diasRest === 0) {
                            $selloValor = 'HOY';
                            $selloEtiqueta = 'Vence';
                        } else {
                            $selloValor = $diasRest . 'd';
                            $selloEtiqueta = 'Restante';
                        }
                        ?>
                        <article class="sigpet-alert-ticket <?= $esLeida ? 'is-read' : '' ?>">
                            <div class="sigpet-alert-stamp <?= $esAtrasado ? 'stamp-overdue' : 'stamp-soon' ?>">
                                <span class="sigpet-stamp-value"><?= htmlspecialchars($selloValor) ?></span>
                                <span class="sigpet-stamp-label"><?= htmlspecialchars($selloEtiqueta) ?></span>
                                <span class="sigpet-stamp-id">#PR-<?= str_pad((string)(int)$al['id_prestamo'], 3, '0', STR_PAD_LEFT) ?></span>
                            </div>

                            <div class="sigpet-alert-content">
                                <div class="sigpet-alert-head">
                                    <span class="sigpet-equip-tag"><?= htmlspecialchars($al['codigo_inventario']) ?></span>
                                    <h4><?= htmlspecialchars($al['titulo']) ?></h4>
                                    <?php if ($esLeida): ?>
                                        <span class="sigpet-read-tag">Leída</span>
                                    <?php endif; ?>
                                </div>
                                <p class="sigpet-alert-msg"><?= htmlspecialchars($al['mensaje']) ?></p>
                                <div class="sigpet-alert-meta">
                                    <?= htmlspecialchars($al['nombre_equipo']) ?>
                                    &nbsp;·&nbsp; Devolución prevista: <strong><?= htmlspecialchars(date('d/m/Y', strtotime($al['fecha_devolucion_prevista']))) ?></strong>
                                    &nbsp;·&nbsp; Emitida: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($al['fecha_generacion']))) ?>
                                </div>
                            </div>

                            <div class="sigpet-alert-actions">
                                <?php if ($puedeGestionar): ?>
                                    <a class="sigpet-btn-navy" href="devolver.php?id=<?= (int)$al['id_prestamo'] ?>">Registrar devolución</a>
                                <?php endif; ?>
                                <?php if (!$esLeida): ?>
                                    <form method="POST" action="alertas.php?tab=mias" style="margin:0;">
                                        <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCSRF) ?>">
                                        <input type="hidden" name="accion" value="marcar_leida">
                                        <input type="hidden" name="id_alerta" value="<?= (int)$al['id_alerta'] ?>">
                                        <button type="submit" class="sigpet-btn-outline">Marcar como leída</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="info-message" style="margin:0;">No tienes alertas activas en este momento.</div>
            <?php endif; ?>

        <?php elseif ($tabActiva === 'global' && $puedeVerPanelGlobal): ?>
            <div class="inventory-toolbar">
                <div>
                    <h3>Monitor global de entregas en riesgo</h3>
                    <p>Todos los préstamos activos vencidos o próximos a vencer en el sistema.</p>
                </div>
                <?php if ($puedeGestionar): ?>
                    <a href="reservas.php?tab=vencidos" class="primary-link">Gestionar autorizaciones</a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Préstamo</th>
                            <th>Solicitante</th>
                            <th>Equipo</th>
                            <th>Entrega</th>
                            <th>Devolución prevista</th>
                            <th>Situación</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($prestamosEnRiesgo)): ?>
                        <?php foreach ($prestamosEnRiesgo as $pr): ?>
                            <?php $dias = (int)$pr['dias_restantes']; ?>
                            <tr>
                                <td><strong>#<?= (int)$pr['id_prestamo'] ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($pr['nombres'] . ' ' . $pr['apellidos']) ?>
                                    <small class="table-subtext"><?= htmlspecialchars($pr['nombre_rol'] . ' · ' . $pr['correo_institucional']) ?></small>
                                </td>
                                <td>
                                    <span class="sigpet-equip-tag"><?= htmlspecialchars($pr['codigo_inventario']) ?></span>
                                    <strong style="margin-left:6px;"><?= htmlspecialchars($pr['nombre_equipo']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($pr['fecha_entrega']))) ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($pr['fecha_devolucion_prevista']))) ?></td>
                                <td>
                                    <?php if ($dias < 0): ?>
                                        <span class="loan-status status-atrasado">Atrasado (<?= abs($dias) ?> día/s)</span>
                                    <?php elseif ($dias === 0): ?>
                                        <span class="loan-status status-proximo">Vence hoy</span>
                                    <?php else: ?>
                                        <span class="loan-status status-proximo">Vence en <?= $dias ?> día(s)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($puedeGestionar): ?>
                                        <a href="devolver.php?id=<?= (int)$pr['id_prestamo'] ?>" class="action-button approve-action">Devolver</a>
                                    <?php else: ?>
                                        <span class="table-subtext">Solo lectura</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="empty-table">No hay préstamos próximos a vencer ni atrasados en el sistema.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($tabActiva === 'resueltas'): ?>
            <div class="table-responsive">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Préstamo</th>
                            <th>Tipo</th>
                            <th>Equipo</th>
                            <th>Generada</th>
                            <th>Devolución real</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($alertasResueltas)): ?>
                        <?php foreach ($alertasResueltas as $ar): ?>
                            <tr>
                                <td><strong>#<?= (int)$ar['id_prestamo'] ?></strong></td>
                                <td><span class="loan-status status-devuelto"><?= htmlspecialchars($ar['tipo_alerta']) ?></span></td>
                                <td>
                                    <span class="sigpet-equip-tag"><?= htmlspecialchars($ar['codigo_inventario']) ?></span>
                                    <strong style="margin-left:6px;"><?= htmlspecialchars($ar['nombre_equipo']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ar['fecha_generacion']))) ?></td>
                                <td><?= $ar['fecha_devolucion_real'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($ar['fecha_devolucion_real']))) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="empty-table">Todavía no hay alertas resueltas registradas para tu cuenta.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <footer class="dashboard-footer">SIGPET © 2026 | Sistema de Gestión de Préstamos de Equipos Tecnológicos</footer>
</main>
</div>
</body>
</html>
<?php $conexion->close(); ?>
