<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('prestamos.gestionar');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';
$idSolicitud = filter_var($_GET['id'] ?? $_POST['id_solicitud'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$error = '';
$solicitud = null;

if ($idSolicitud) {
    $stmt = $conexion->prepare(
        "SELECT s.id_solicitud, s.fecha_inicio, s.fecha_devolucion_prevista, s.proposito,
                s.estado_solicitud, u.nombres, u.apellidos,
                e.codigo_inventario, e.nombre AS nombre_equipo
         FROM solicitudes_prestamo s
         INNER JOIN usuarios u ON u.id_usuario = s.id_usuario
         INNER JOIN equipos e ON e.id_equipo = s.id_equipo
         WHERE s.id_solicitud = ? LIMIT 1"
    );
    $stmt->bind_param('i', $idSolicitud);
    $stmt->execute();
    $solicitud = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$solicitud) {
    header('Location: listar.php?estado=Pendiente&error=solicitud');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF()) {
        $error = 'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.';
    } elseif ($solicitud['estado_solicitud'] !== 'Pendiente') {
        $error = 'La solicitud ya fue procesada y no puede rechazarse nuevamente.';
    } else {
        $motivo = trim($_POST['motivo_rechazo'] ?? '');
        if ($motivo === '') {
            $error = 'El motivo de rechazo es obligatorio.';
        } elseif (mb_strlen($motivo) > 500) {
            $error = 'El motivo de rechazo no puede superar los 500 caracteres.';
        } else {
            $idDecisor = (int)$_SESSION['id_usuario'];
            $stmt = $conexion->prepare(
                "UPDATE solicitudes_prestamo
                 SET estado_solicitud = 'Rechazada', motivo_rechazo = ?,
                     fecha_decision = NOW(), decidido_por = ?
                 WHERE id_solicitud = ? AND estado_solicitud = 'Pendiente'"
            );
            $stmt->bind_param('sii', $motivo, $idDecisor, $idSolicitud);
            $stmt->execute();
            $actualizada = $stmt->affected_rows === 1;
            $stmt->close();
            if ($actualizada) {
                header('Location: listar.php?estado=Pendiente&rechazada=1');
                exit();
            }
            $error = 'No fue posible rechazar la solicitud. Puede que ya haya sido procesada.';
        }
    }
}

$tokenCSRF = obtenerTokenCSRF();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rechazar solicitud | SIGPET</title>
    <link rel="stylesheet" href="../CSS/base.css"><link rel="stylesheet" href="../CSS/dashboard.css"><link rel="stylesheet" href="../CSS/usuarios.css"><link rel="stylesheet" href="../CSS/prestamos.css">
</head>
<body class="dashboard-body">
<div class="dashboard-layout">
<?php $rutaBase = '../'; $paginaActiva = 'prestamos'; require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="dashboard-main">
<header class="dashboard-header"><div><span class="header-subtitle">GESTIÓN DE PRÉSTAMOS</span><h1>Rechazar solicitud</h1></div><div class="header-user"><div class="header-user-text"><strong><?= htmlspecialchars($nombreSesion . ' ' . $apellidoSesion) ?></strong><span><?= htmlspecialchars($rolSesion) ?></span></div><div class="user-avatar header-avatar"><?= htmlspecialchars(strtoupper(substr($nombreSesion, 0, 1))) ?></div></div></header>
<section class="users-intro"><h2>Solicitud #<?= (int)$solicitud['id_solicitud'] ?></h2><p>Registra el motivo por el cual no se autoriza el préstamo.</p></section>
<section class="user-form-container">
<?php if ($error !== ''): ?><div class="form-message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="loan-detail-card"><strong>Solicitante:</strong> <?= htmlspecialchars($solicitud['nombres'] . ' ' . $solicitud['apellidos']) ?><br><strong>Equipo:</strong> <?= htmlspecialchars($solicitud['codigo_inventario'] . ' — ' . $solicitud['nombre_equipo']) ?><br><strong>Período:</strong> <?= htmlspecialchars($solicitud['fecha_inicio']) ?> al <?= htmlspecialchars($solicitud['fecha_devolucion_prevista']) ?><br><strong>Propósito:</strong> <?= htmlspecialchars($solicitud['proposito']) ?></div>
<form method="POST" action="rechazar.php" class="user-form">
<input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCSRF) ?>"><input type="hidden" name="id_solicitud" value="<?= (int)$solicitud['id_solicitud'] ?>">
<div class="form-section-title">Motivo del rechazo</div>
<div class="form-grid"><div class="form-group form-group-full"><label for="motivo_rechazo">Motivo *</label><textarea id="motivo_rechazo" name="motivo_rechazo" rows="6" maxlength="500" required><?= htmlspecialchars($_POST['motivo_rechazo'] ?? '') ?></textarea></div></div>
<div class="form-actions"><a href="listar.php?estado=Pendiente" class="form-button cancel-button">Cancelar</a><button class="form-button submit-button" type="submit">Confirmar rechazo</button></div>
</form></section>
<footer class="dashboard-footer">SIGPET © 2026 | Sistema de Gestión de Préstamos de Equipos Tecnológicos</footer>
</main></div>
</body></html>
<?php $conexion->close(); ?>
