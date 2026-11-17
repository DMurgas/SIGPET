<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('reservas.consultar');

require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/reservas_helper.php';
require_once __DIR__ . '/../includes/bitacora.php';

$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';
$idUsuarioSesion = (int)($_SESSION['id_usuario'] ?? 0);
$puedeGestionar = tienePermiso('reservas.gestionar');
$puedeSolicitar = tienePermiso('prestamos.solicitar');

$mensajeExito = '';
$mensajeError = '';

if (isset($_GET['autorizada']) && $_GET['autorizada'] === '1') {
    $mensajeExito = 'Excepción por préstamo vencido autorizada correctamente.';
} elseif (isset($_GET['revocada']) && $_GET['revocada'] === '1') {
    $mensajeExito = 'La autorización excepcional fue revocada.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarPermiso('reservas.gestionar');

    if (!validarTokenCSRF()) {
        $mensajeError = 'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.';
    } else {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'autorizar_vencido') {
            $idUsuarioObjetivo = filter_var($_POST['id_usuario'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $motivo = trim($_POST['motivo'] ?? '');

            if (!$idUsuarioObjetivo) {
                $mensajeError = 'El usuario indicado no es válido.';
            } elseif ($motivo === '') {
                $mensajeError = 'Debes indicar el motivo para autorizar la excepción.';
            } elseif (mb_strlen($motivo) > 500) {
                $mensajeError = 'El motivo no puede superar los 500 caracteres.';
            } else {
                $vencidos = obtenerPrestamosVencidosUsuario($conexion, $idUsuarioObjetivo);
                $activa = obtenerAutorizacionVencidoActiva($conexion, $idUsuarioObjetivo);

                if (empty($vencidos)) {
                    $mensajeError = 'El usuario no registra préstamos vencidos actualmente.';
                } elseif ($activa !== null) {
                    $mensajeError = 'El usuario ya cuenta con una autorización excepcional activa.';
                } else {
                    $stmt = $conexion->prepare(
                        "INSERT INTO autorizaciones_vencido (id_usuario, autorizado_por, motivo, activa)
                         VALUES (?, ?, ?, 1)"
                    );
                    $stmt->bind_param('iis', $idUsuarioObjetivo, $idUsuarioSesion, $motivo);
                    $stmt->execute();
                    $idAuth = (int)$conexion->insert_id;
                    $stmt->close();

                    registrarBitacora(
                        $conexion,
                        $idUsuarioSesion,
                        'Reservas',
                        'Autorización excepcional',
                        'autorizaciones_vencido',
                        $idAuth,
                        "Se autorizó al usuario #{$idUsuarioObjetivo} a registrar una nueva solicitud pese a tener préstamo vencido. Motivo: {$motivo}"
                    );

                    header('Location: reservas.php?tab=vencidos&autorizada=1');
                    exit();
                }
            }
        } elseif ($accion === 'revocar_vencido') {
            $idAuth = filter_var($_POST['id_autorizacion'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$idAuth) {
                $mensajeError = 'La autorización indicada no es válida.';
            } else {
                $stmt = $conexion->prepare(
                    "UPDATE autorizaciones_vencido
                     SET activa = 0
                     WHERE id_autorizacion = ? AND activa = 1"
                );
                $stmt->bind_param('i', $idAuth);
                $stmt->execute();
                $actualizada = $stmt->affected_rows === 1;
                $stmt->close();

                if ($actualizada) {
                    registrarBitacora(
                        $conexion,
                        $idUsuarioSesion,
                        'Reservas',
                        'Revocación de autorización',
                        'autorizaciones_vencido',
                        $idAuth,
                        "Se revocó la autorización excepcional #{$idAuth}."
                    );
                    header('Location: reservas.php?tab=vencidos&revocada=1');
                    exit();
                }
                $mensajeError = 'La autorización ya no estaba activa.';
            }
        }
    }
}

$tabActiva = trim($_GET['tab'] ?? 'disponibilidad');
$tabsPermitidas = ['disponibilidad', 'agenda'];
if ($puedeGestionar) {
    $tabsPermitidas[] = 'vencidos';
}
if (!in_array($tabActiva, $tabsPermitidas, true)) {
    $tabActiva = 'disponibilidad';
}

$fechaInicio = trim($_GET['fecha_inicio'] ?? date('Y-m-d'));
$fechaFin = trim($_GET['fecha_devolucion_prevista'] ?? date('Y-m-d', strtotime('+1 day')));
$idTipoFiltro = filter_var($_GET['tipo'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$busqueda = trim($_GET['buscar'] ?? '');

$inicioObj = DateTime::createFromFormat('!Y-m-d', $fechaInicio);
$finObj = DateTime::createFromFormat('!Y-m-d', $fechaFin);
$fechasValidas = true;

if (!$inicioObj || $inicioObj->format('Y-m-d') !== $fechaInicio) {
    $fechasValidas = false;
    $mensajeError = 'La fecha de inicio seleccionada no es válida.';
} elseif (!$finObj || $finObj->format('Y-m-d') !== $fechaFin) {
    $fechasValidas = false;
    $mensajeError = 'La fecha prevista de devolución seleccionada no es válida.';
} elseif ($finObj <= $inicioObj) {
    $fechasValidas = false;
    $mensajeError = 'La fecha prevista de devolución debe ser posterior a la fecha de inicio.';
}

$tipos = $conexion->query("SELECT id_tipo, nombre_tipo FROM tipos_equipo ORDER BY nombre_tipo")->fetch_all(MYSQLI_ASSOC);

$sqlEquipos = "SELECT
        e.id_equipo,
        e.codigo_inventario,
        e.nombre,
        e.marca,
        e.modelo,
        e.ubicacion,
        t.nombre_tipo,
        es.nombre_estado
     FROM equipos e
     INNER JOIN tipos_equipo t ON t.id_tipo = e.id_tipo
     INNER JOIN estados_equipo es ON es.id_estado = e.id_estado
     WHERE 1 = 1";

$tiposBind = '';
$params = [];

if ($idTipoFiltro) {
    $sqlEquipos .= " AND e.id_tipo = ?";
    $tiposBind .= 'i';
    $params[] = $idTipoFiltro;
}

if ($busqueda !== '') {
    $sqlEquipos .= " AND (e.codigo_inventario LIKE ? OR e.nombre LIKE ? OR e.marca LIKE ? OR e.modelo LIKE ?)";
    $patron = '%' . $busqueda . '%';
    $tiposBind .= 'ssss';
    array_push($params, $patron, $patron, $patron, $patron);
}

$sqlEquipos .= " ORDER BY e.nombre ASC, e.codigo_inventario ASC";

$stmtEquipos = $conexion->prepare($sqlEquipos);
if ($tiposBind !== '') {
    $stmtEquipos->bind_param($tiposBind, ...$params);
}
$stmtEquipos->execute();
$equipos = $stmtEquipos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEquipos->close();

$disponibilidadEquipos = [];
$totalDisponiblesPeriodo = 0;
$totalConflictoPeriodo = 0;

foreach ($equipos as $eq) {
    $conflicto = null;
    if ($fechasValidas && !in_array($eq['nombre_estado'], ['En mantenimiento', 'Dado de baja'], true)) {
        $conflicto = buscarConflictoReserva($conexion, (int)$eq['id_equipo'], $fechaInicio, $fechaFin);
    }

    if ($eq['nombre_estado'] === 'Dado de baja' || $eq['nombre_estado'] === 'En mantenimiento') {
        $estadoPeriodo = $eq['nombre_estado'];
    } elseif ($conflicto !== null) {
        $estadoPeriodo = 'Ocupado en el período';
        $totalConflictoPeriodo++;
    } else {
        $estadoPeriodo = 'Disponible en el período';
        $totalDisponiblesPeriodo++;
    }

    $eq['conflicto'] = $conflicto;
    $eq['estado_periodo'] = $estadoPeriodo;
    $disponibilidadEquipos[] = $eq;
}

$reservasActivas = $conexion->query(
    "SELECT
        s.id_solicitud,
        s.fecha_inicio,
        s.fecha_devolucion_prevista,
        s.estado_solicitud,
        s.es_prioridad,
        e.codigo_inventario,
        e.nombre AS nombre_equipo,
        u.nombres,
        u.apellidos,
        r.nombre_rol,
        p.id_prestamo,
        p.estado_prestamo
     FROM solicitudes_prestamo s
     INNER JOIN equipos e ON e.id_equipo = s.id_equipo
     INNER JOIN usuarios u ON u.id_usuario = s.id_usuario
     INNER JOIN roles r ON r.id_rol = u.id_rol
     LEFT JOIN prestamos p ON p.id_solicitud = s.id_solicitud
     WHERE (s.estado_solicitud IN ('Pendiente', 'Aprobada') AND p.id_prestamo IS NULL)
        OR (p.estado_prestamo = 'Prestado' AND p.fecha_devolucion_real IS NULL)
     ORDER BY s.fecha_inicio ASC, s.es_prioridad DESC"
)->fetch_all(MYSQLI_ASSOC);

$usuariosConVencidos = [];
if ($puedeGestionar) {
    $filasVencidos = $conexion->query(
        "SELECT
            u.id_usuario,
            u.nombres,
            u.apellidos,
            u.codigo_institucional,
            u.correo_institucional,
            r.nombre_rol,
            COUNT(p.id_prestamo) AS cantidad_vencidos,
            MAX(DATEDIFF(CURDATE(), s.fecha_devolucion_prevista)) AS max_dias_atraso,
            GROUP_CONCAT(CONCAT('#', p.id_prestamo, ' ', e.codigo_inventario, ' (vence ', DATE_FORMAT(s.fecha_devolucion_prevista, '%d/%m/%Y'), ')') SEPARATOR '; ') AS detalle_prestamos
         FROM prestamos p
         INNER JOIN solicitudes_prestamo s ON s.id_solicitud = p.id_solicitud
         INNER JOIN usuarios u ON u.id_usuario = p.id_usuario
         INNER JOIN roles r ON r.id_rol = u.id_rol
         INNER JOIN equipos e ON e.id_equipo = p.id_equipo
         WHERE p.estado_prestamo = 'Prestado'
           AND p.fecha_devolucion_real IS NULL
           AND s.fecha_devolucion_prevista < CURDATE()
         GROUP BY u.id_usuario
         ORDER BY max_dias_atraso DESC"
    )->fetch_all(MYSQLI_ASSOC);

    foreach ($filasVencidos as $fv) {
        $fv['autorizacion'] = obtenerAutorizacionVencidoActiva($conexion, (int)$fv['id_usuario']);
        $usuariosConVencidos[] = $fv;
    }
}

// Paginación limpia (8 filas por página para evitar scroll largo).
$porPagina = 8;
$paginaActual = max(1, (int)($_GET['pagina'] ?? 1));

$listaActiva = $tabActiva === 'agenda' ? $reservasActivas : ($tabActiva === 'vencidos' ? $usuariosConVencidos : $disponibilidadEquipos);
$totalElementos = count($listaActiva);
$totalPaginas = max(1, (int)ceil($totalElementos / $porPagina));
if ($paginaActual > $totalPaginas) {
    $paginaActual = $totalPaginas;
}
$offset = ($paginaActual - 1) * $porPagina;
$itemsPagina = array_slice($listaActiva, $offset, $porPagina);

$misVencidos = obtenerPrestamosVencidosUsuario($conexion, $idUsuarioSesion);
$miAutorizacion = obtenerAutorizacionVencidoActiva($conexion, $idUsuarioSesion);
$tokenCSRF = obtenerTokenCSRF();

function urlReservaTab(string $tab, int $pag = 1, array $extra = []): string
{
    $q = array_merge($_GET, ['tab' => $tab, 'pagina' => $pag], $extra);
    return 'reservas.php?' . http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservas y disponibilidad | SIGPET</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../CSS/base.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <link rel="stylesheet" href="../CSS/inventario.css">
    <link rel="stylesheet" href="../CSS/prestamos.css">
    <link rel="stylesheet" href="../CSS/theme-profesional.css">
</head>
<body class="dashboard-body">
<div class="dashboard-layout">
<?php $rutaBase = '../'; $paginaActiva = 'reservas'; require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="dashboard-main">
    <header class="dashboard-header">
        <div>
            <span class="header-subtitle">RESERVAS Y DISPONIBILIDAD</span>
            <h1>Reservas y disponibilidad por período</h1>
            <p class="header-desc">Consulta la disponibilidad por fechas, revisa períodos ocupados y reserva sin conflictos.</p>
        </div>
        <?php if ($puedeSolicitar): ?>
            <div class="header-actions">
                <a href="solicitar.php" class="primary-link">+ Nueva solicitud</a>
            </div>
        <?php endif; ?>
    </header>

    <?php if ($mensajeExito !== ''): ?>
        <div class="success-message"><?= htmlspecialchars($mensajeExito) ?></div>
    <?php endif; ?>
    <?php if ($mensajeError !== ''): ?>
        <div class="error-message"><?= htmlspecialchars($mensajeError) ?></div>
    <?php endif; ?>

    <?php if (!empty($misVencidos)): ?>
        <?php if ($miAutorizacion): ?>
            <div class="info-message">
                <strong>Autorización excepcional vigente:</strong> Tienes <?= count($misVencidos) ?> préstamo(s) vencido(s), pero cuentas con autorización de <strong><?= htmlspecialchars($miAutorizacion['autorizador_nombres'] . ' ' . $miAutorizacion['autorizador_apellidos']) ?></strong> para registrar una nueva solicitud.
            </div>
        <?php else: ?>
            <div class="warning-message">
                <strong>Tienes préstamos vencidos:</strong> Registras <?= count($misVencidos) ?> préstamo(s) pendiente(s) de devolución. Para realizar nuevas solicitudes debes devolver el equipo o pedir autorización al encargado.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="kpi-grid">
        <div class="kpi-card kpi-success">
            <span>Disponibles en el período</span>
            <strong><?= $fechasValidas ? $totalDisponiblesPeriodo : 0 ?></strong>
        </div>
        <div class="kpi-card kpi-danger">
            <span>Con conflicto en fechas</span>
            <strong><?= $fechasValidas ? $totalConflictoPeriodo : 0 ?></strong>
        </div>
        <div class="kpi-card">
            <span>Reservas y préstamos activos</span>
            <strong><?= count($reservasActivas) ?></strong>
        </div>
        <?php if ($puedeGestionar): ?>
        <div class="kpi-card kpi-warning">
            <span>Usuarios con atrasos</span>
            <strong><?= count($usuariosConVencidos) ?></strong>
        </div>
        <?php endif; ?>
    </div>

    <nav class="sigpet-tabs" aria-label="Vistas de reservas">
        <a class="sigpet-tab <?= $tabActiva === 'disponibilidad' ? 'active' : '' ?>" href="<?= htmlspecialchars(urlReservaTab('disponibilidad', 1)) ?>">
            Disponibilidad por fechas
            <span class="sigpet-tab-count"><?= count($disponibilidadEquipos) ?></span>
        </a>
        <a class="sigpet-tab <?= $tabActiva === 'agenda' ? 'active' : '' ?>" href="<?= htmlspecialchars(urlReservaTab('agenda', 1)) ?>">
            Agenda de períodos ocupados
            <span class="sigpet-tab-count"><?= count($reservasActivas) ?></span>
        </a>
        <?php if ($puedeGestionar): ?>
        <a class="sigpet-tab <?= $tabActiva === 'vencidos' ? 'active' : '' ?>" href="<?= htmlspecialchars(urlReservaTab('vencidos', 1)) ?>">
            Autorizaciones por atraso
            <span class="sigpet-tab-count"><?= count($usuariosConVencidos) ?></span>
        </a>
        <?php endif; ?>
    </nav>

    <section class="inventory-container">
        <?php if ($tabActiva === 'disponibilidad'): ?>
            <form method="GET" action="reservas.php" class="loan-filter">
                <input type="hidden" name="tab" value="disponibilidad">
                <div>
                    <label for="fecha_inicio">Fecha de inicio</label>
                    <input id="fecha_inicio" name="fecha_inicio" type="date" value="<?= htmlspecialchars($fechaInicio) ?>" required>
                </div>
                <div>
                    <label for="fecha_devolucion_prevista">Fecha prevista devolución</label>
                    <input id="fecha_devolucion_prevista" name="fecha_devolucion_prevista" type="date" value="<?= htmlspecialchars($fechaFin) ?>" required>
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
                <div>
                    <label for="buscar">Buscar equipo</label>
                    <input id="buscar" name="buscar" type="text" placeholder="Código, nombre o marca" value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                <div class="action-buttons">
                    <button type="submit" class="filter-button">Verificar</button>
                    <a href="reservas.php?tab=disponibilidad" class="action-button">Limpiar</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Equipo</th>
                            <th>Tipo</th>
                            <th>Estado físico</th>
                            <th>Disponibilidad</th>
                            <th>Detalle del período</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($itemsPagina)): ?>
                        <?php foreach ($itemsPagina as $eq): ?>
                            <tr>
                                <td>
                                    <span class="sigpet-equip-tag"><?= htmlspecialchars($eq['codigo_inventario']) ?></span>
                                    <strong style="margin-left:6px;"><?= htmlspecialchars($eq['nombre']) ?></strong>
                                    <small class="table-subtext">
                                        <?= htmlspecialchars(trim(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? ''))) ?>
                                        <?= !empty($eq['ubicacion']) ? ' · ' . htmlspecialchars($eq['ubicacion']) : '' ?>
                                    </small>
                                </td>
                                <td><?= htmlspecialchars($eq['nombre_tipo']) ?></td>
                                <td><?= htmlspecialchars($eq['nombre_estado']) ?></td>
                                <td>
                                    <?php if ($eq['estado_periodo'] === 'Disponible en el período'): ?>
                                        <span class="loan-status status-disponible">Disponible</span>
                                    <?php elseif ($eq['estado_periodo'] === 'Ocupado en el período'): ?>
                                        <span class="loan-status status-ocupado">No disponible (traslape)</span>
                                    <?php else: ?>
                                        <span class="loan-status status-rechazada"><?= htmlspecialchars($eq['estado_periodo']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($eq['conflicto']): ?>
                                        <span class="table-subtext">
                                            <?= $eq['conflicto']['origen'] === 'prestamo' ? 'Préstamo activo' : 'Solicitud ' . htmlspecialchars($eq['conflicto']['estado']) ?>
                                            #<?= (int)$eq['conflicto']['id'] ?>:
                                            <?= htmlspecialchars(date('d/m/Y', strtotime($eq['conflicto']['fecha_inicio']))) ?>
                                            al
                                            <?= htmlspecialchars(date('d/m/Y', strtotime($eq['conflicto']['fecha_fin']))) ?>
                                        </span>
                                    <?php elseif ($eq['estado_periodo'] === 'En mantenimiento'): ?>
                                        <span class="table-subtext">No disponible mientras está en mantenimiento.</span>
                                    <?php elseif ($eq['estado_periodo'] === 'Dado de baja'): ?>
                                        <span class="table-subtext">Retirado del inventario activo.</span>
                                    <?php elseif ($fechasValidas): ?>
                                        <span class="table-subtext">Libre del <?= htmlspecialchars(date('d/m/Y', strtotime($fechaInicio))) ?> al <?= htmlspecialchars(date('d/m/Y', strtotime($fechaFin))) ?>.</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($puedeSolicitar && $fechasValidas && $eq['estado_periodo'] === 'Disponible en el período'): ?>
                                        <a
                                            class="action-button approve-action"
                                            href="solicitar.php?id_equipo=<?= (int)$eq['id_equipo'] ?>&amp;fecha_inicio=<?= urlencode($fechaInicio) ?>&amp;fecha_devolucion_prevista=<?= urlencode($fechaFin) ?>"
                                        >
                                            Reservar
                                        </a>
                                    <?php else: ?>
                                        <span class="table-subtext">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="empty-table">No se encontraron equipos con los criterios indicados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($tabActiva === 'agenda'): ?>
            <div class="table-responsive">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Solicitud</th>
                            <th>Equipo</th>
                            <th>Solicitante</th>
                            <th>Período reservado</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($itemsPagina)): ?>
                        <?php foreach ($itemsPagina as $res): ?>
                            <tr>
                                <td>
                                    <span class="sigpet-equip-tag">#<?= (int)$res['id_solicitud'] ?></span>
                                </td>
                                <td>
                                    <span class="sigpet-equip-tag"><?= htmlspecialchars($res['codigo_inventario']) ?></span>
                                    <strong style="margin-left:6px;"><?= htmlspecialchars($res['nombre_equipo']) ?></strong>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($res['nombres'] . ' ' . $res['apellidos']) ?></strong>
                                    <small class="table-subtext"><?= htmlspecialchars($res['nombre_rol']) ?></small>
                                    <?php if ((int)$res['es_prioridad'] === 1): ?>
                                        <span class="loan-status status-prioridad">Prioridad docente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars(date('d/m/Y', strtotime($res['fecha_inicio']))) ?>
                                    al
                                    <?= htmlspecialchars(date('d/m/Y', strtotime($res['fecha_devolucion_prevista']))) ?>
                                </td>
                                <td>
                                    <?php if (($res['estado_prestamo'] ?? '') === 'Prestado'): ?>
                                        <span class="loan-status status-prestado">Prestado (activo)</span>
                                    <?php else: ?>
                                        <span class="loan-status status-<?= htmlspecialchars(strtolower($res['estado_solicitud'])) ?>">
                                            <?= htmlspecialchars($res['estado_solicitud']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="empty-table">No hay reservas ni préstamos activos en este momento.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($tabActiva === 'vencidos' && $puedeGestionar): ?>
            <div class="table-responsive">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Préstamo(s) vencido(s)</th>
                            <th>Máx. atraso</th>
                            <th>Estado de acceso</th>
                            <th>Acción del encargado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($itemsPagina)): ?>
                        <?php foreach ($itemsPagina as $uv): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($uv['nombres'] . ' ' . $uv['apellidos']) ?></strong>
                                    <small class="table-subtext"><?= htmlspecialchars($uv['nombre_rol'] . ' · ' . ($uv['codigo_institucional'] ?? $uv['correo_institucional'])) ?></small>
                                </td>
                                <td><?= htmlspecialchars($uv['detalle_prestamos']) ?></td>
                                <td><span class="loan-status status-atrasado"><?= (int)$uv['max_dias_atraso'] ?> día(s)</span></td>
                                <td>
                                    <?php if ($uv['autorizacion']): ?>
                                        <span class="loan-status status-aprobada">Excepción autorizada</span>
                                        <small class="table-subtext">Por <?= htmlspecialchars($uv['autorizacion']['autorizador_nombres'] . ' ' . $uv['autorizacion']['autorizador_apellidos']) ?>: <?= htmlspecialchars($uv['autorizacion']['motivo']) ?></small>
                                    <?php else: ?>
                                        <span class="loan-status status-rechazada">Bloqueado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($uv['autorizacion']): ?>
                                        <form method="POST" action="reservas.php?tab=vencidos" onsubmit="return confirm('¿Deseas revocar la autorización excepcional de este usuario?');">
                                            <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCSRF) ?>">
                                            <input type="hidden" name="accion" value="revocar_vencido">
                                            <input type="hidden" name="id_autorizacion" value="<?= (int)$uv['autorizacion']['id_autorizacion'] ?>">
                                            <button type="submit" class="action-button reject-action">Revocar</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="reservas.php?tab=vencidos" class="action-buttons">
                                            <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCSRF) ?>">
                                            <input type="hidden" name="accion" value="autorizar_vencido">
                                            <input type="hidden" name="id_usuario" value="<?= (int)$uv['id_usuario'] ?>">
                                            <input type="text" name="motivo" maxlength="500" placeholder="Motivo..." required style="height:32px;padding:4px 9px;border:1px solid #cbd5df;border-radius:6px;font-size:12.5px;">
                                            <button type="submit" class="action-button approve-action">Autorizar</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="empty-table">No hay usuarios con préstamos vencidos en este momento.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($totalPaginas > 1): ?>
            <div class="sigpet-pagination">
                <span>Mostrando <?= $offset + 1 ?>–<?= min($offset + $porPagina, $totalElementos) ?> de <?= $totalElementos ?></span>
                <div class="sigpet-page-links">
                    <a class="sigpet-page-link <?= $paginaActual <= 1 ? 'disabled' : '' ?>" href="<?= htmlspecialchars(urlReservaTab($tabActiva, max(1, $paginaActual - 1))) ?>">Anterior</a>
                    <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                        <a class="sigpet-page-link <?= $p === $paginaActual ? 'active' : '' ?>" href="<?= htmlspecialchars(urlReservaTab($tabActiva, $p)) ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <a class="sigpet-page-link <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>" href="<?= htmlspecialchars(urlReservaTab($tabActiva, min($totalPaginas, $paginaActual + 1))) ?>">Siguiente</a>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <footer class="dashboard-footer">SIGPET © 2026 | Sistema de Gestión de Préstamos de Equipos Tecnológicos</footer>
</main>
</div>
</body>
</html>
<?php $conexion->close(); ?>
