<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('bitacora.consultar');

require_once __DIR__ . '/../config/conexion.php';

$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';

$moduloFiltro = trim($_GET['modulo'] ?? '');
$idUsuarioFiltro = filter_var($_GET['id_usuario'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$busqueda = trim($_GET['buscar'] ?? '');
$fechaDesde = trim($_GET['fecha_desde'] ?? '');
$fechaHasta = trim($_GET['fecha_hasta'] ?? '');

$modulosDisponibles = $conexion->query(
    "SELECT DISTINCT modulo FROM bitacora ORDER BY modulo ASC"
)->fetch_all(MYSQLI_ASSOC);

$usuariosDisponibles = $conexion->query(
    "SELECT u.id_usuario, u.nombres, u.apellidos, r.nombre_rol
     FROM usuarios u
     INNER JOIN roles r ON r.id_rol = u.id_rol
     ORDER BY u.nombres, u.apellidos"
)->fetch_all(MYSQLI_ASSOC);

$sqlWhere = " WHERE 1 = 1";
$tiposBind = '';
$params = [];

if ($moduloFiltro !== '') {
    $sqlWhere .= " AND b.modulo = ?";
    $tiposBind .= 's';
    $params[] = $moduloFiltro;
}

if ($idUsuarioFiltro) {
    $sqlWhere .= " AND b.id_usuario = ?";
    $tiposBind .= 'i';
    $params[] = $idUsuarioFiltro;
}

if ($busqueda !== '') {
    $sqlWhere .= " AND (b.accion LIKE ? OR b.detalle LIKE ? OR b.entidad LIKE ?)";
    $patron = '%' . $busqueda . '%';
    $tiposBind .= 'sss';
    array_push($params, $patron, $patron, $patron);
}

$desdeObj = $fechaDesde !== '' ? DateTime::createFromFormat('!Y-m-d', $fechaDesde) : false;
if ($desdeObj && $desdeObj->format('Y-m-d') === $fechaDesde) {
    $sqlWhere .= " AND DATE(b.fecha_registro) >= ?";
    $tiposBind .= 's';
    $params[] = $fechaDesde;
}

$hastaObj = $fechaHasta !== '' ? DateTime::createFromFormat('!Y-m-d', $fechaHasta) : false;
if ($hastaObj && $hastaObj->format('Y-m-d') === $fechaHasta) {
    $sqlWhere .= " AND DATE(b.fecha_registro) <= ?";
    $tiposBind .= 's';
    $params[] = $fechaHasta;
}

$sqlCount = "SELECT COUNT(*) AS total FROM bitacora b" . $sqlWhere;
$stmtCount = $conexion->prepare($sqlCount);
if ($tiposBind !== '') {
    $stmtCount->bind_param($tiposBind, ...$params);
}
$stmtCount->execute();
$totalFiltrados = (int)$stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();

$porPagina = 10;
$totalPaginas = max(1, (int)ceil($totalFiltrados / $porPagina));
$paginaActual = max(1, min($totalPaginas, (int)($_GET['pagina'] ?? 1)));
$offset = ($paginaActual - 1) * $porPagina;

$sql = "SELECT
        b.id_bitacora,
        b.id_usuario,
        b.modulo,
        b.accion,
        b.entidad,
        b.id_entidad,
        b.detalle,
        b.fecha_registro,
        u.nombres,
        u.apellidos,
        r.nombre_rol
     FROM bitacora b
     LEFT JOIN usuarios u ON u.id_usuario = b.id_usuario
     LEFT JOIN roles r ON r.id_rol = u.id_rol"
    . $sqlWhere
    . " ORDER BY b.id_bitacora DESC LIMIT ? OFFSET ?";

$stmt = $conexion->prepare($sql);
$tiposConPag = $tiposBind . 'ii';
$paramsConPag = array_merge($params, [$porPagina, $offset]);
$stmt->bind_param($tiposConPag, ...$paramsConPag);
$stmt->execute();
$registros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function urlBitacoraPagina(int $pag): string
{
    $q = array_merge($_GET, ['pagina' => $pag]);
    return 'listar.php?' . http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitácora de acciones | SIGPET</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../CSS/base.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <link rel="stylesheet" href="../CSS/inventario.css">
    <link rel="stylesheet" href="../CSS/prestamos.css">
</head>
<body class="dashboard-body">
<div class="dashboard-layout">
<?php $rutaBase = '../'; $paginaActiva = 'bitacora'; require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="dashboard-main">
    <header class="dashboard-header">
        <div>
            <span class="header-subtitle">AUDITORÍA DEL SISTEMA</span>
            <h1>Bitácora de acciones</h1>
            <p class="header-desc">Registro histórico de solicitudes, aprobaciones, rechazos, entregas, devoluciones, cambios de estado y alertas (<?= $totalFiltrados ?> evento<?= $totalFiltrados === 1 ? '' : 's' ?>).</p>
        </div>
    </header>

    <section class="inventory-container">
        <form method="GET" action="listar.php" class="loan-filter">
            <div>
                <label for="modulo">Módulo</label>
                <select id="modulo" name="modulo">
                    <option value="">Todos los módulos</option>
                    <?php foreach ($modulosDisponibles as $mod): ?>
                        <option value="<?= htmlspecialchars($mod['modulo']) ?>" <?= $moduloFiltro === $mod['modulo'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mod['modulo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="id_usuario">Usuario</label>
                <select id="id_usuario" name="id_usuario">
                    <option value="">Todos</option>
                    <?php foreach ($usuariosDisponibles as $u): ?>
                        <option value="<?= (int)$u['id_usuario'] ?>" <?= $idUsuarioFiltro === (int)$u['id_usuario'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nombres'] . ' ' . $u['apellidos'] . ' (' . $u['nombre_rol'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="fecha_desde">Desde</label>
                <input id="fecha_desde" name="fecha_desde" type="date" value="<?= htmlspecialchars($fechaDesde) ?>">
            </div>
            <div>
                <label for="fecha_hasta">Hasta</label>
                <input id="fecha_hasta" name="fecha_hasta" type="date" value="<?= htmlspecialchars($fechaHasta) ?>">
            </div>
            <div>
                <label for="buscar">Buscar texto</label>
                <input id="buscar" name="buscar" type="text" placeholder="Acción o detalle..." value="<?= htmlspecialchars($busqueda) ?>">
            </div>
            <div class="action-buttons">
                <button type="submit" class="filter-button">Consultar</button>
                <a href="listar.php" class="action-button">Limpiar</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha y hora</th>
                        <th>Usuario</th>
                        <th>Módulo</th>
                        <th>Acción</th>
                        <th>Detalle</th>
                        <th>Opciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($registros)): ?>
                    <?php foreach ($registros as $reg): ?>
                        <?php
                        $actorTexto = !empty($reg['nombres'])
                            ? trim($reg['nombres'] . ' ' . $reg['apellidos'] . (!empty($reg['nombre_rol']) ? ' · ' . $reg['nombre_rol'] : ''))
                            : 'Sistema automático';
                        $entidadTexto = trim((string) ($reg['entidad'] ?? '') . ($reg['id_entidad'] ? ' #' . (int) $reg['id_entidad'] : ''));
                        $payloadBitacora = [
                            'eyebrow'  => 'Registro de bitácora #' . (int) $reg['id_bitacora'],
                            'title'    => (string) $reg['accion'],
                            'subtitle' => $reg['modulo'] . ($entidadTexto !== '' ? ' · ' . $entidadTexto : ''),
                            'badge'    => [
                                'label' => (string) $reg['modulo'],
                                'tone'  => 'info',
                            ],
                            'meta'     => [
                                [
                                    'label' => 'Fecha y hora',
                                    'value' => date('d/m/Y H:i:s', strtotime($reg['fecha_registro'])),
                                ],
                                [
                                    'label' => 'Usuario responsable',
                                    'value' => $actorTexto,
                                ],
                                [
                                    'label' => 'Módulo',
                                    'value' => (string) $reg['modulo'],
                                ],
                                [
                                    'label' => 'Entidad afectada',
                                    'value' => $entidadTexto !== '' ? $entidadTexto : 'General',
                                ],
                            ],
                            'sections' => [
                                [
                                    'label' => 'Detalle completo del evento',
                                    'text'  => (string) $reg['detalle'],
                                ],
                            ],
                        ];
                        $detalleJson = htmlspecialchars(
                            json_encode($payloadBitacora, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                        <tr class="sigpet-row-clickable" data-detail="<?= $detalleJson ?>">
                            <td><span class="sigpet-equip-tag">#<?= (int)$reg['id_bitacora'] ?></span></td>
                            <td style="white-space:nowrap;"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($reg['fecha_registro']))) ?></td>
                            <td>
                                <?php if (!empty($reg['nombres'])): ?>
                                    <strong><?= htmlspecialchars($reg['nombres'] . ' ' . $reg['apellidos']) ?></strong>
                                    <small class="table-subtext"><?= htmlspecialchars($reg['nombre_rol'] ?? '') ?></small>
                                <?php else: ?>
                                    <span class="table-subtext">Sistema automático</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="loan-status status-prestado"><?= htmlspecialchars($reg['modulo']) ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($reg['accion']) ?></strong>
                                <small class="table-subtext"><?= htmlspecialchars($reg['entidad']) ?><?= $reg['id_entidad'] ? ' #' . (int)$reg['id_entidad'] : '' ?></small>
                            </td>
                            <td><span class="sigpet-cell-clamp"><?= htmlspecialchars($reg['detalle']) ?></span></td>
                            <td>
                                <button type="button" class="sigpet-detail-btn sigpet-detail-trigger" aria-label="Ver detalle del registro #<?= (int)$reg['id_bitacora'] ?>">
                                    <?= sigpetIcono('eye', 15) ?>
                                    <span>Ver detalle</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="empty-table">No hay registros de bitácora con los filtros indicados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <div class="sigpet-pagination">
                <span>Mostrando <?= $offset + 1 ?>–<?= min($offset + $porPagina, $totalFiltrados) ?> de <?= $totalFiltrados ?></span>
                <div class="sigpet-page-links">
                    <a class="sigpet-page-link <?= $paginaActual <= 1 ? 'disabled' : '' ?>" href="<?= htmlspecialchars(urlBitacoraPagina(max(1, $paginaActual - 1))) ?>">Anterior</a>
                    <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                        <?php if ($p === 1 || $p === $totalPaginas || abs($p - $paginaActual) <= 2): ?>
                            <a class="sigpet-page-link <?= $p === $paginaActual ? 'active' : '' ?>" href="<?= htmlspecialchars(urlBitacoraPagina($p)) ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <a class="sigpet-page-link <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>" href="<?= htmlspecialchars(urlBitacoraPagina(min($totalPaginas, $paginaActual + 1))) ?>">Siguiente</a>
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
