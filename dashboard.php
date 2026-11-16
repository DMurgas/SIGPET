<?php

require_once "includes/verificar_rol.php";
require_once "config/conexion.php";
require_once "includes/alertas.php";
require_once "includes/iconos.php";

$nombre = $_SESSION["nombres"];
$apellido = $_SESSION["apellidos"];
$rol = $_SESSION["rol"];
$idUsuarioSesion = (int)($_SESSION["id_usuario"] ?? 0);

sincronizarAlertasPrestamos($conexion, true);

$alertasActivasUsuario = 0;
if ($idUsuarioSesion > 0 && tienePermiso("alertas.consultar")) {
    $stmtAl = $conexion->prepare(
        "SELECT COUNT(*) AS total FROM alertas WHERE id_usuario_destino = ? AND resuelta = 0"
    );
    $stmtAl->bind_param("i", $idUsuarioSesion);
    $stmtAl->execute();
    $alertasActivasUsuario = (int)$stmtAl->get_result()->fetch_assoc()["total"];
    $stmtAl->close();
}

// 1. Métricas reales del inventario por estado.
$conteosEstado = [
    "Disponible" => 0,
    "Reservado" => 0,
    "Prestado" => 0,
    "En mantenimiento" => 0,
    "Dado de baja" => 0,
];
$resEstados = $conexion->query(
    "SELECT es.nombre_estado, COUNT(e.id_equipo) AS total
     FROM estados_equipo es
     LEFT JOIN equipos e ON e.id_estado = es.id_estado
     GROUP BY es.id_estado, es.nombre_estado"
);
while ($fila = $resEstados->fetch_assoc()) {
    $conteosEstado[$fila["nombre_estado"]] = (int)$fila["total"];
}
$totalInventario = array_sum($conteosEstado);
$divisor = max(1, $totalInventario);

$puedeGestionarPrestamos = tienePermiso("prestamos.gestionar");
$puedeSolicitar = tienePermiso("prestamos.solicitar");
$puedeVerBitacora = tienePermiso("bitacora.consultar");
$puedeVerReportes = tienePermiso("reportes.consultar");

// 2. Datos operativos dinámicos según el perfil.
$pendientesGestion = [];
$totalPendientes = 0;
if ($puedeGestionarPrestamos) {
    $totalPendientes = (int)$conexion->query(
        "SELECT COUNT(*) AS total FROM solicitudes_prestamo WHERE estado_solicitud = 'Pendiente'"
    )->fetch_assoc()["total"];

    $pendientesGestion = $conexion->query(
        "SELECT s.id_solicitud, s.fecha_inicio, s.fecha_devolucion_prevista, s.es_prioridad,
                u.nombres, u.apellidos, r.nombre_rol,
                e.codigo_inventario, e.nombre AS nombre_equipo
         FROM solicitudes_prestamo s
         INNER JOIN usuarios u ON u.id_usuario = s.id_usuario
         INNER JOIN roles r ON r.id_rol = u.id_rol
         INNER JOIN equipos e ON e.id_equipo = s.id_equipo
         WHERE s.estado_solicitud = 'Pendiente'
         ORDER BY s.es_prioridad DESC, s.id_solicitud DESC
         LIMIT 4"
    )->fetch_all(MYSQLI_ASSOC);
}

$misMovimientos = [];
if ($puedeSolicitar) {
    $stmtMis = $conexion->prepare(
        "SELECT s.id_solicitud, s.fecha_inicio, s.fecha_devolucion_prevista, s.estado_solicitud,
                p.id_prestamo, p.estado_prestamo,
                e.codigo_inventario, e.nombre AS nombre_equipo
         FROM solicitudes_prestamo s
         INNER JOIN equipos e ON e.id_equipo = s.id_equipo
         LEFT JOIN prestamos p ON p.id_solicitud = s.id_solicitud
         WHERE s.id_usuario = ?
           AND (
               s.estado_solicitud = 'Pendiente'
               OR (s.estado_solicitud = 'Aprobada' AND p.id_prestamo IS NULL)
               OR (p.estado_prestamo = 'Prestado')
           )
         ORDER BY s.fecha_inicio ASC
         LIMIT 4"
    );
    $stmtMis->bind_param("i", $idUsuarioSesion);
    $stmtMis->execute();
    $misMovimientos = $stmtMis->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtMis->close();
}

$topEquiposMes = [];
$prestamosAtrasadosCount = 0;
if ($puedeVerReportes) {
    $prestamosAtrasadosCount = (int)$conexion->query(
        "SELECT COUNT(*) AS total
         FROM prestamos p
         INNER JOIN solicitudes_prestamo s ON s.id_solicitud = p.id_solicitud
         WHERE p.estado_prestamo = 'Prestado'
           AND p.fecha_devolucion_real IS NULL
           AND s.fecha_devolucion_prevista < CURDATE()"
    )->fetch_assoc()["total"];

    $topEquiposMes = $conexion->query(
        "SELECT e.codigo_inventario, e.nombre, COUNT(s.id_solicitud) AS total
         FROM solicitudes_prestamo s
         INNER JOIN equipos e ON e.id_equipo = s.id_equipo
         GROUP BY e.id_equipo, e.codigo_inventario, e.nombre
         ORDER BY total DESC
         LIMIT 4"
    )->fetch_all(MYSQLI_ASSOC);
}

$actividadReciente = [];
if ($puedeVerBitacora) {
    $actividadReciente = $conexion->query(
        "SELECT b.accion, b.modulo, b.fecha_registro, u.nombres, u.apellidos
         FROM bitacora b
         LEFT JOIN usuarios u ON u.id_usuario = b.id_usuario
         ORDER BY b.id_bitacora DESC
         LIMIT 5"
    )->fetch_all(MYSQLI_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de control | SIGPET</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="CSS/base.css">
    <link rel="stylesheet" href="CSS/dashboard.css">
    <link rel="stylesheet" href="CSS/inventario.css">
    <link rel="stylesheet" href="CSS/prestamos.css">
</head>

<body class="dashboard-body">

<div class="dashboard-layout">

    <?php
    $rutaBase = "";
    $paginaActiva = "dashboard";
    require_once "includes/sidebar.php";
    ?>

    <main class="dashboard-main">

        <header class="dashboard-header">
            <div>
                <span class="header-subtitle">CENTRO DE OPERACIONES · <?= htmlspecialchars(mb_strtoupper($rol)) ?></span>
                <h1>Estado operativo de SIGPET</h1>
                <p class="header-desc">Vista en tiempo real del inventario, solicitudes activas y accesos de trabajo.</p>
            </div>
        </header>

        <?php if ($alertasActivasUsuario > 0): ?>
            <div class="sigpet-notice-strip">
                <div class="sigpet-notice-left">
                    <span class="sigpet-notice-dot" aria-hidden="true"></span>
                    <div class="sigpet-notice-text">
                        <strong>Seguimiento prioritario:</strong> Tienes <?= $alertasActivasUsuario ?> alerta(s) activa(s) de vencimiento o atraso pendiente(s).
                    </div>
                </div>
                <a href="prestamos/alertas.php" class="sigpet-notice-cta">Atender alertas →</a>
            </div>
        <?php endif; ?>

        <div class="bento-meta-bar">
            <div class="bento-date-pill">
                <span class="bento-live-dot" aria-hidden="true"></span>
                <span>
                    <strong><?= $conteosEstado["Disponible"] ?></strong> de <strong><?= $totalInventario ?></strong> equipos disponibles ahora
                    <?php if ($puedeVerReportes && $prestamosAtrasadosCount > 0): ?>
                        · <span style="color:#dc2626;"><?= $prestamosAtrasadosCount ?> atrasado(s)</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="bento-quick-actions">
                <?php if ($puedeSolicitar): ?>
                    <a href="prestamos/solicitar.php" class="bento-pill-btn primary">+ Nueva solicitud</a>
                <?php endif; ?>
                <?php if (tienePermiso("reservas.consultar")): ?>
                    <a href="prestamos/reservas.php" class="bento-pill-btn">Verificar fechas</a>
                <?php endif; ?>
                <?php if ($puedeGestionarPrestamos): ?>
                    <a href="prestamos/listar.php?estado=Pendiente" class="bento-pill-btn <?= $totalPendientes > 0 ? 'primary' : '' ?>">
                        Pendientes (<?= $totalPendientes ?>)
                    </a>
                <?php endif; ?>
                <?php if ($puedeVerReportes): ?>
                    <a href="reportes/index.php" class="bento-pill-btn">Ver reportes</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="bento-grid">

            <!-- COLUMNA PRINCIPAL -->
            <div class="bento-col">

                <!-- DISTRIBUCIÓN EN TIEMPO REAL DEL INVENTARIO -->
                <section class="bento-panel">
                    <div class="bento-panel-head">
                        <h2>Pulso del inventario</h2>
                        <a href="inventario/listar.php" class="bento-panel-link">Explorar catálogo →</a>
                    </div>

                    <div class="inv-meter-bar" role="img" aria-label="Distribución de estados del inventario">
                        <div class="inv-seg seg-disp" style="width:<?= round(($conteosEstado['Disponible'] / $divisor) * 100, 1) ?>%" title="Disponible: <?= $conteosEstado['Disponible'] ?>"></div>
                        <div class="inv-seg seg-pres" style="width:<?= round(($conteosEstado['Prestado'] / $divisor) * 100, 1) ?>%" title="Prestado: <?= $conteosEstado['Prestado'] ?>"></div>
                        <div class="inv-seg seg-res" style="width:<?= round(($conteosEstado['Reservado'] / $divisor) * 100, 1) ?>%" title="Reservado: <?= $conteosEstado['Reservado'] ?>"></div>
                        <div class="inv-seg seg-mant" style="width:<?= round(($conteosEstado['En mantenimiento'] / $divisor) * 100, 1) ?>%" title="En mantenimiento: <?= $conteosEstado['En mantenimiento'] ?>"></div>
                        <div class="inv-seg seg-baja" style="width:<?= round(($conteosEstado['Dado de baja'] / $divisor) * 100, 1) ?>%" title="Dado de baja: <?= $conteosEstado['Dado de baja'] ?>"></div>
                    </div>

                    <div class="inv-legend">
                        <div class="inv-legend-item">
                            <span class="inv-legend-left"><span class="inv-dot" style="background:#10b981;"></span>Disponibles</span>
                            <strong><?= $conteosEstado["Disponible"] ?></strong>
                        </div>
                        <div class="inv-legend-item">
                            <span class="inv-legend-left"><span class="inv-dot" style="background:#2563eb;"></span>Prestados</span>
                            <strong><?= $conteosEstado["Prestado"] ?></strong>
                        </div>
                        <div class="inv-legend-item">
                            <span class="inv-legend-left"><span class="inv-dot" style="background:#8b5cf6;"></span>Reservados</span>
                            <strong><?= $conteosEstado["Reservado"] ?></strong>
                        </div>
                        <div class="inv-legend-item">
                            <span class="inv-legend-left"><span class="inv-dot" style="background:#f59e0b;"></span>Mantenimiento</span>
                            <strong><?= $conteosEstado["En mantenimiento"] ?></strong>
                        </div>
                        <div class="inv-legend-item">
                            <span class="inv-legend-left"><span class="inv-dot" style="background:#ef4444;"></span>De baja</span>
                            <strong><?= $conteosEstado["Dado de baja"] ?></strong>
                        </div>
                    </div>
                </section>

                <?php if ($puedeSolicitar): ?>
                    <!-- BUSCADOR RÁPIDO DE RESERVA + MIS MOVIMIENTOS -->
                    <section class="bento-panel">
                        <div class="bento-panel-head">
                            <h2>Consulta rápida de fechas libres</h2>
                            <a href="prestamos/reservas.php" class="bento-panel-link">Calendario completo →</a>
                        </div>
                        <form method="GET" action="prestamos/reservas.php" class="bento-quick-reserve">
                            <input type="hidden" name="tab" value="disponibilidad">
                            <div>
                                <label for="dash_inicio">Desde</label>
                                <input id="dash_inicio" name="fecha_inicio" type="date" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div>
                                <label for="dash_fin">Hasta</label>
                                <input id="dash_fin" name="fecha_devolucion_prevista" type="date" value="<?= date('Y-m-d', strtotime('+2 days')) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                            </div>
                            <button type="submit" class="filter-button">Buscar libres</button>
                        </form>
                    </section>

                    <section class="bento-panel">
                        <div class="bento-panel-head">
                            <h2>Tus préstamos y solicitudes en curso</h2>
                            <a href="prestamos/mis_solicitudes.php" class="bento-panel-link">Ver todas →</a>
                        </div>
                        <div class="bento-feed">
                            <?php if (!empty($misMovimientos)): ?>
                                <?php foreach ($misMovimientos as $mov): ?>
                                    <div class="bento-feed-row">
                                        <div class="bento-feed-info">
                                            <div class="bento-feed-title">
                                                <span class="sigpet-equip-tag"><?= htmlspecialchars($mov['codigo_inventario']) ?></span>
                                                <span><?= htmlspecialchars($mov['nombre_equipo']) ?></span>
                                            </div>
                                        </div>
                                        <div class="bento-feed-right">
                                            <span class="bento-feed-dates"><?= htmlspecialchars(date('d/m/Y', strtotime($mov['fecha_inicio'])) . ' → ' . date('d/m/Y', strtotime($mov['fecha_devolucion_prevista']))) ?></span>
                                            <?php if (($mov['estado_prestamo'] ?? '') === 'Prestado'): ?>
                                                <span class="loan-status status-prestado">En tu poder</span>
                                            <?php else: ?>
                                                <span class="loan-status status-<?= htmlspecialchars(strtolower($mov['estado_solicitud'])) ?>">
                                                    <?= htmlspecialchars($mov['estado_solicitud']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="bento-feed-row">
                                    <div class="bento-feed-info">
                                        <div class="bento-feed-title">No tienes solicitudes ni préstamos activos</div>
                                        <div class="bento-feed-meta">Puedes reservar un equipo disponible en cualquier momento.</div>
                                    </div>
                                    <a href="prestamos/solicitar.php" class="action-button edit-action">Solicitar</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($puedeGestionarPrestamos): ?>
                    <!-- COLA DE SOLICITUDES PENDIENTES -->
                    <section class="bento-panel">
                        <div class="bento-panel-head">
                            <h2>Solicitudes pendientes por atender</h2>
                            <a href="prestamos/listar.php?estado=Pendiente" class="bento-panel-link">Gestionar todas (<?= $totalPendientes ?>) →</a>
                        </div>
                        <div class="bento-feed">
                            <?php if (!empty($pendientesGestion)): ?>
                                <?php foreach ($pendientesGestion as $pg): ?>
                                    <div class="bento-feed-row">
                                        <div class="bento-feed-info">
                                            <div class="bento-feed-title">
                                                <span class="sigpet-equip-tag"><?= htmlspecialchars($pg['codigo_inventario']) ?></span>
                                                <span><?= htmlspecialchars($pg['nombres'] . ' ' . $pg['apellidos']) ?></span>
                                                <?php if ((int)$pg['es_prioridad'] === 1): ?>
                                                    <span class="loan-status status-prioridad" style="margin:0;">Prioridad</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="bento-feed-meta">
                                                <?= htmlspecialchars($pg['nombre_equipo']) ?> · <?= htmlspecialchars($pg['nombre_rol']) ?>
                                            </div>
                                        </div>
                                        <div class="bento-feed-right">
                                            <span class="bento-feed-dates"><?= htmlspecialchars(date('d/m/Y', strtotime($pg['fecha_inicio'])) . ' → ' . date('d/m/Y', strtotime($pg['fecha_devolucion_prevista']))) ?></span>
                                            <a href="prestamos/listar.php?estado=Pendiente" class="action-button edit-action">Revisar</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="bento-feed-row">
                                    <div class="bento-feed-info">
                                        <div class="bento-feed-title">Sin solicitudes pendientes</div>
                                        <div class="bento-feed-meta">Todas las peticiones han sido procesadas.</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php elseif ($puedeVerReportes): ?>
                    <!-- EQUIPO MÁS SOLICITADO PARA COORDINADOR ACADÉMICO -->
                    <section class="bento-panel">
                        <div class="bento-panel-head">
                            <h2>Equipos con mayor demanda</h2>
                            <a href="reportes/index.php" class="bento-panel-link">Abrir reportes →</a>
                        </div>
                        <div class="bento-feed">
                            <?php foreach ($topEquiposMes as $te): ?>
                                <div class="bento-feed-row">
                                    <div class="bento-feed-info">
                                        <div class="bento-feed-title">
                                            <span class="sigpet-equip-tag"><?= htmlspecialchars($te['codigo_inventario']) ?></span>
                                            <span><?= htmlspecialchars($te['nombre']) ?></span>
                                        </div>
                                    </div>
                                    <span class="loan-status status-prestado"><?= (int)$te['total'] ?> solicitud(es)</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

            </div>

            <!-- COLUMNA SECUNDARIA -->
            <div class="bento-col">

                <section class="bento-panel">
                    <div class="bento-panel-head">
                        <h2>Módulos habilitados</h2>
                    </div>
                    <div class="bento-modules">
                        <?php if (tienePermiso("inventario.consultar")): ?>
                            <a href="inventario/listar.php" class="bento-module-tile">
                                <span class="bento-tile-icon"><?= sigpetIcono('inventario', 18) ?></span>
                                <span class="bento-tile-text"><strong>Inventario</strong><span><?= $totalInventario ?> equipos</span></span>
                            </a>
                        <?php endif; ?>
                        <?php if (tienePermiso("reservas.consultar")): ?>
                            <a href="prestamos/reservas.php" class="bento-module-tile">
                                <span class="bento-tile-icon"><?= sigpetIcono('reservas', 18) ?></span>
                                <span class="bento-tile-text"><strong>Reservas</strong><span>Fechas libres</span></span>
                            </a>
                        <?php endif; ?>
                        <?php if ($puedeSolicitar): ?>
                            <a href="prestamos/mis_solicitudes.php" class="bento-module-tile">
                                <span class="bento-tile-icon"><?= sigpetIcono('mis_solicitudes', 18) ?></span>
                                <span class="bento-tile-text"><strong>Mis solicitudes</strong><span>Seguimiento</span></span>
                            </a>
                        <?php endif; ?>
                        <?php if ($puedeGestionarPrestamos): ?>
                            <a href="prestamos/listar.php?estado=Pendiente" class="bento-module-tile">
                                <span class="bento-tile-icon"><?= sigpetIcono('prestamos', 18) ?></span>
                                <span class="bento-tile-text"><strong>Préstamos</strong><span><?= $totalPendientes ?> pendiente(s)</span></span>
                            </a>
                        <?php endif; ?>
                        <?php if (tienePermiso("prestamos.historial")): ?>
                            <a href="prestamos/historial.php" class="bento-module-tile">
                                <span class="bento-tile-icon"><?= sigpetIcono('historial', 18) ?></span>
                                <span class="bento-tile-text"><strong>Historial</strong><span>Entregas y retornos</span></span>
                            </a>
                        <?php endif; ?>
                        <?php if (tienePermiso("alertas.consultar")): ?>
                            <a href="prestamos/alertas.php" class="bento-module-tile">
                                <span class="bento-tile-icon"><?= sigpetIcono('alertas', 18) ?></span>
                                <span class="bento-tile-text"><strong>Alertas</strong><span><?= $alertasActivasUsuario ?> activa(s)</span></span>
                            </a>
                        <?php endif; ?>
                        <?php if ($puedeVerReportes): ?>
                            <a href="reportes/index.php" class="bento-module-tile">
                                <span class="bento-tile-icon"><?= sigpetIcono('reportes', 18) ?></span>
                                <span class="bento-tile-text"><strong>Reportes</strong><span>Uso y atrasos</span></span>
                            </a>
                        <?php endif; ?>
                        <?php if ($puedeVerBitacora): ?>
                            <a href="bitacora/listar.php" class="bento-module-tile">
                                <span class="bento-tile-icon"><?= sigpetIcono('bitacora', 18) ?></span>
                                <span class="bento-tile-text"><strong>Bitácora</strong><span>Auditoría</span></span>
                            </a>
                        <?php endif; ?>
                        <?php if (tienePermiso("usuarios.consultar")): ?>
                            <a href="usuarios/listar.php" class="bento-module-tile">
                                <span class="bento-tile-icon"><?= sigpetIcono('usuarios', 18) ?></span>
                                <span class="bento-tile-text"><strong>Usuarios</strong><span>Cuentas y roles</span></span>
                            </a>
                        <?php endif; ?>
                        <?php if (tienePermiso("inventario.registrar")): ?>
                            <a href="inventario/registrar.php" class="bento-module-tile">
                                <span class="bento-tile-icon"><?= sigpetIcono('nuevo', 18) ?></span>
                                <span class="bento-tile-text"><strong>Nuevo equipo</strong><span>Alta en catálogo</span></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($puedeVerBitacora && !empty($actividadReciente)): ?>
                <section class="bento-panel">
                    <div class="bento-panel-head">
                        <h2>Actividad reciente</h2>
                        <a href="bitacora/listar.php" class="bento-panel-link">Bitácora completa →</a>
                    </div>
                    <div class="bento-feed">
                        <?php foreach ($actividadReciente as $act): ?>
                            <div class="bento-feed-row">
                                <div class="bento-feed-info">
                                    <div class="bento-feed-title"><?= htmlspecialchars($act['accion']) ?></div>
                                    <div class="bento-feed-meta">
                                        <?= htmlspecialchars($act['modulo']) ?>
                                        · <?= !empty($act['nombres']) ? htmlspecialchars($act['nombres'] . ' ' . $act['apellidos']) : 'Sistema automático' ?>
                                    </div>
                                </div>
                                <span class="table-subtext" style="margin:0;white-space:nowrap;">
                                    <?= htmlspecialchars(date('d/m H:i', strtotime($act['fecha_registro']))) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

            </div>

        </div>

        <footer class="dashboard-footer">
            SIGPET © 2026 | Sistema de Gestión de Préstamos de Equipos Tecnológicos
        </footer>

    </main>

</div>

</body>

</html>
<?php $conexion->close(); ?>
