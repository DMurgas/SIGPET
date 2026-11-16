<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('reportes.consultar');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/bitacora.php';

$fechaDesde = trim($_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days')));
$fechaHasta = trim($_GET['fecha_hasta'] ?? date('Y-m-d'));
$idTipoFiltro = filter_var($_GET['tipo'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

$desdeObj = DateTime::createFromFormat('!Y-m-d', $fechaDesde);
$hastaObj = DateTime::createFromFormat('!Y-m-d', $fechaHasta);
if (!$desdeObj || $desdeObj->format('Y-m-d') !== $fechaDesde) {
    $fechaDesde = date('Y-m-d', strtotime('-30 days'));
}
if (!$hastaObj || $hastaObj->format('Y-m-d') !== $fechaHasta) {
    $fechaHasta = date('Y-m-d');
}

$filtroTipoSql = $idTipoFiltro ? ' AND e.id_tipo = ? ' : '';

$sqlMasSolicitados = "SELECT
        e.codigo_inventario,
        e.nombre AS nombre_equipo,
        t.nombre_tipo,
        COUNT(s.id_solicitud) AS total_solicitudes,
        SUM(CASE WHEN s.estado_solicitud = 'Aprobada' THEN 1 ELSE 0 END) AS aprobadas,
        SUM(CASE WHEN s.estado_solicitud = 'Rechazada' THEN 1 ELSE 0 END) AS rechazadas,
        COUNT(p.id_prestamo) AS prestamos_entregados
     FROM solicitudes_prestamo s
     INNER JOIN equipos e ON e.id_equipo = s.id_equipo
     INNER JOIN tipos_equipo t ON t.id_tipo = e.id_tipo
     LEFT JOIN prestamos p ON p.id_solicitud = s.id_solicitud
     WHERE (DATE(s.fecha_solicitud) BETWEEN ? AND ? OR s.fecha_inicio BETWEEN ? AND ?)
     {$filtroTipoSql}
     GROUP BY e.id_equipo, e.codigo_inventario, e.nombre, t.nombre_tipo
     ORDER BY total_solicitudes DESC";

$stmt = $conexion->prepare($sqlMasSolicitados);
if ($idTipoFiltro) {
    $stmt->bind_param('ssssi', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta, $idTipoFiltro);
} else {
    $stmt->bind_param('ssss', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta);
}
$stmt->execute();
$masSolicitados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sqlAtrasados = "SELECT
        p.id_prestamo,
        CONCAT(u.nombres, ' ', u.apellidos) AS solicitante,
        r.nombre_rol,
        e.codigo_inventario,
        e.nombre AS nombre_equipo,
        s.fecha_devolucion_prevista,
        p.fecha_devolucion_real,
        p.estado_prestamo
     FROM prestamos p
     INNER JOIN solicitudes_prestamo s ON s.id_solicitud = p.id_solicitud
     INNER JOIN usuarios u ON u.id_usuario = p.id_usuario
     INNER JOIN roles r ON r.id_rol = u.id_rol
     INNER JOIN equipos e ON e.id_equipo = p.id_equipo
     WHERE (
         (p.estado_prestamo = 'Prestado' AND p.fecha_devolucion_real IS NULL AND s.fecha_devolucion_prevista < CURDATE())
         OR (
             p.fecha_devolucion_real IS NOT NULL
             AND DATE(p.fecha_devolucion_real) > s.fecha_devolucion_prevista
             AND (DATE(p.fecha_entrega) BETWEEN ? AND ? OR s.fecha_devolucion_prevista BETWEEN ? AND ?)
         )
     )
     {$filtroTipoSql}
     ORDER BY s.fecha_devolucion_prevista ASC";

$stmt = $conexion->prepare($sqlAtrasados);
if ($idTipoFiltro) {
    $stmt->bind_param('ssssi', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta, $idTipoFiltro);
} else {
    $stmt->bind_param('ssss', $fechaDesde, $fechaHasta, $fechaDesde, $fechaHasta);
}
$stmt->execute();
$atrasados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

registrarBitacora(
    $conexion,
    (int)($_SESSION['id_usuario'] ?? 0) ?: null,
    'Reportes',
    'Exportación de reporte CSV',
    'reportes',
    null,
    "Exportación CSV del período {$fechaDesde} al {$fechaHasta}."
);

$conexion->close();

$nombreArchivo = 'reporte_sigpet_' . $fechaDesde . '_a_' . $fechaHasta . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['SIGPET - Reporte Administrativo', "Período: {$fechaDesde} al {$fechaHasta}"]);
fputcsv($out, []);
fputcsv($out, ['1. EQUIPOS MÁS SOLICITADOS']);
fputcsv($out, ['Código inventario', 'Equipo', 'Tipo', 'Total solicitudes', 'Aprobadas', 'Rechazadas', 'Préstamos entregados']);
foreach ($masSolicitados as $fila) {
    fputcsv($out, [
        $fila['codigo_inventario'],
        $fila['nombre_equipo'],
        $fila['nombre_tipo'],
        $fila['total_solicitudes'],
        $fila['aprobadas'],
        $fila['rechazadas'],
        $fila['prestamos_entregados']
    ]);
}

fputcsv($out, []);
fputcsv($out, ['2. PRÉSTAMOS ATRASADOS']);
fputcsv($out, ['ID Préstamo', 'Solicitante', 'Rol', 'Código', 'Equipo', 'Devolución prevista', 'Devolución real', 'Estado']);
foreach ($atrasados as $fila) {
    fputcsv($out, [
        $fila['id_prestamo'],
        $fila['solicitante'],
        $fila['nombre_rol'],
        $fila['codigo_inventario'],
        $fila['nombre_equipo'],
        $fila['fecha_devolucion_prevista'],
        $fila['fecha_devolucion_real'] ?? 'Pendiente',
        $fila['estado_prestamo']
    ]);
}

fclose($out);
exit();
