<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('prestamos.solicitar');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/reservas_helper.php';
require_once __DIR__ . '/../includes/bitacora.php';

$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';
$idUsuarioSesion = (int)($_SESSION['id_usuario'] ?? 0);
$esDocente = ($rolSesion === 'Docente');

$datos = [
    'id_equipo' => trim($_GET['id_equipo'] ?? $_POST['id_equipo'] ?? ''),
    'fecha_inicio' => trim($_POST['fecha_inicio'] ?? $_GET['fecha_inicio'] ?? date('Y-m-d')),
    'fecha_devolucion_prevista' => trim($_POST['fecha_devolucion_prevista'] ?? $_GET['fecha_devolucion_prevista'] ?? date('Y-m-d', strtotime('+1 day'))),
    'proposito' => trim($_POST['proposito'] ?? ''),
    'es_prioridad' => $esDocente
        ? (($_SERVER['REQUEST_METHOD'] === 'POST') ? isset($_POST['es_prioridad']) : true)
        : false
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

// Regla RN-03: verificar préstamos vencidos y autorización excepcional.
$prestamosVencidos = obtenerPrestamosVencidosUsuario($conexion, $idUsuarioSesion);
$autorizacionVencido = !empty($prestamosVencidos)
    ? obtenerAutorizacionVencidoActiva($conexion, $idUsuarioSesion)
    : null;
$bloqueadoPorVencido = !empty($prestamosVencidos) && $autorizacionVencido === null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF()) {
        $error = 'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.';
    } elseif ($bloqueadoPorVencido) {
        $error = 'No puedes registrar nuevas solicitudes porque tienes préstamo(s) vencido(s) pendiente(s) de devolución. Devuelve el equipo o solicita autorización al encargado.';
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
        } elseif ($equipoSeleccionado['nombre_estado'] === 'En mantenimiento') {
            $error = 'El equipo seleccionado se encuentra en mantenimiento y no puede ser solicitado.';
        } elseif ($equipoSeleccionado['nombre_estado'] === 'Dado de baja') {
            $error = 'El equipo seleccionado está dado de baja y no puede ser solicitado.';
        } else {
            try {
                $conexion->begin_transaction();

                // Bloqueamos el equipo para evitar reservas concurrentes en el mismo período.
                $stmtLock = $conexion->prepare(
                    "SELECT e.id_equipo, es.nombre_estado
                     FROM equipos e
                     INNER JOIN estados_equipo es ON es.id_estado = e.id_estado
                     WHERE e.id_equipo = ?
                     FOR UPDATE"
                );
                $stmtLock->bind_param('i', $idEquipo);
                $stmtLock->execute();
                $estadoActualEquipo = $stmtLock->get_result()->fetch_assoc();
                $stmtLock->close();

                if (!$estadoActualEquipo || in_array($estadoActualEquipo['nombre_estado'], ['En mantenimiento', 'Dado de baja'], true)) {
                    throw new RuntimeException('El equipo no está habilitado para reservas en este momento.');
                }

                $conflicto = buscarConflictoReserva(
                    $conexion,
                    $idEquipo,
                    $datos['fecha_inicio'],
                    $datos['fecha_devolucion_prevista'],
                    null,
                    true
                );

                if ($conflicto !== null) {
                    $desdeTxt = date('d/m/Y', strtotime($conflicto['fecha_inicio']));
                    $hastaTxt = date('d/m/Y', strtotime($conflicto['fecha_fin']));
                    $tipoConflicto = $conflicto['origen'] === 'prestamo'
                        ? 'un préstamo activo'
                        : 'una solicitud ' . strtolower($conflicto['estado']);
                    throw new RuntimeException(
                        "El equipo no está disponible para las fechas seleccionadas porque ya tiene {$tipoConflicto} del {$desdeTxt} al {$hastaTxt}."
                    );
                }

                $esPrioridadInt = ($esDocente && $datos['es_prioridad']) ? 1 : 0;

                $stmt = $conexion->prepare(
                    "INSERT INTO solicitudes_prestamo
                        (id_usuario, id_equipo, fecha_inicio, fecha_devolucion_prevista, proposito, es_prioridad, estado_solicitud)
                     VALUES (?, ?, ?, ?, ?, ?, 'Pendiente')"
                );
                $stmt->bind_param(
                    'iisssi',
                    $idUsuarioSesion,
                    $idEquipo,
                    $datos['fecha_inicio'],
                    $datos['fecha_devolucion_prevista'],
                    $datos['proposito'],
                    $esPrioridadInt
                );
                $stmt->execute();
                $idSolicitudNueva = (int)$conexion->insert_id;
                $stmt->close();

                // Si se utilizó una autorización excepcional, se marca como usada.
                if ($autorizacionVencido !== null) {
                    $idAuth = (int)$autorizacionVencido['id_autorizacion'];
                    $stmtAuth = $conexion->prepare(
                        "UPDATE autorizaciones_vencido
                         SET activa = 0, id_solicitud_uso = ?, fecha_uso = NOW()
                         WHERE id_autorizacion = ? AND activa = 1"
                    );
                    $stmtAuth->bind_param('ii', $idSolicitudNueva, $idAuth);
                    $stmtAuth->execute();
                    $stmtAuth->close();
                }

                $detalleBitacora = "Solicitud #{$idSolicitudNueva} registrada para equipo {$equipoSeleccionado['codigo_inventario']} ({$equipoSeleccionado['nombre']}) del {$datos['fecha_inicio']} al {$datos['fecha_devolucion_prevista']}.";
                if ($esPrioridadInt === 1) {
                    $detalleBitacora .= ' Marcada con prioridad docente.';
                }
                if ($autorizacionVencido !== null) {
                    $detalleBitacora .= " Registrada mediante autorización excepcional #{$autorizacionVencido['id_autorizacion']}.";
                }

                registrarBitacora(
                    $conexion,
                    $idUsuarioSesion,
                    'Solicitudes',
                    'Creación de solicitud',
                    'solicitudes_prestamo',
                    $idSolicitudNueva,
                    $detalleBitacora
                );

                $conexion->commit();
                header('Location: mis_solicitudes.php?registrada=1');
                exit();
            } catch (Throwable $e) {
                $conexion->rollback();
                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'No fue posible registrar la solicitud. Inténtalo nuevamente.';
            }
        }
    }
}

$equiposReservables = $conexion->query(
    "SELECT e.id_equipo, e.codigo_inventario, e.nombre, e.marca, e.modelo,
            t.nombre_tipo, s.nombre_estado
     FROM equipos e
     INNER JOIN estados_equipo s ON s.id_estado = e.id_estado
     INNER JOIN tipos_equipo t ON t.id_tipo = e.id_tipo
     WHERE s.nombre_estado NOT IN ('En mantenimiento', 'Dado de baja')
     ORDER BY FIELD(s.nombre_estado, 'Disponible', 'Reservado', 'Prestado'), e.nombre, e.codigo_inventario"
)->fetch_all(MYSQLI_ASSOC);

$periodosOcupados = $idEquipo ? obtenerPeriodosOcupadosEquipo($conexion, (int)$idEquipo) : [];
$tokenCSRF = obtenerTokenCSRF();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar préstamo | SIGPET</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../CSS/base.css">
    <link rel="stylesheet" href="../CSS/dashboard.css">
    <link rel="stylesheet" href="../CSS/usuarios.css">
    <link rel="stylesheet" href="../CSS/prestamos.css">
</head>
<body class="dashboard-body">
<div class="dashboard-layout">
<?php $rutaBase = '../'; $paginaActiva = 'reservas'; require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="dashboard-main">
    <header class="dashboard-header">
        <div>
            <span class="header-subtitle">GESTIÓN DE PRÉSTAMOS Y RESERVAS</span>
            <h1>Solicitar préstamo o reserva</h1>
            <p class="header-desc">Selecciona un equipo, define las fechas de uso e indica el propósito. El sistema verificará automáticamente que no existan conflictos de horario.</p>
        </div>
    </header>

    <section class="user-form-container">
        <?php if ($error !== ''): ?><div class="form-message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php if ($bloqueadoPorVencido): ?>
            <div class="warning-message">
                <strong>No puedes registrar nuevas solicitudes por préstamo vencido:</strong><br>
                Actualmente tienes los siguientes préstamos pendientes de devolución fuera de fecha:
                <ul style="margin:8px 0 0 18px;">
                    <?php foreach ($prestamosVencidos as $pv): ?>
                        <li>
                            Préstamo #<?= (int)$pv['id_prestamo'] ?> —
                            <?= htmlspecialchars($pv['codigo_inventario'] . ' ' . $pv['nombre_equipo']) ?>
                            (venció el <?= htmlspecialchars(date('d/m/Y', strtotime($pv['fecha_devolucion_prevista']))) ?>,
                            <?= (int)$pv['dias_atraso'] ?> día(s) de atraso)
                        </li>
                    <?php endforeach; ?>
                </ul>
                Regulariza tu devolución o solicita una autorización excepcional al encargado de préstamos.
            </div>
        <?php elseif ($autorizacionVencido !== null): ?>
            <div class="info-message">
                <strong>Autorización excepcional vigente:</strong> Tienes préstamo(s) vencido(s), pero <strong><?= htmlspecialchars($autorizacionVencido['autorizador_nombres'] . ' ' . $autorizacionVencido['autorizador_apellidos']) ?></strong> autorizó el registro de una nueva solicitud (<em><?= htmlspecialchars($autorizacionVencido['motivo']) ?></em>).
            </div>
        <?php endif; ?>

        <?php if (!$equiposReservables): ?>
            <div class="form-message error">No hay equipos habilitados para solicitar en este momento.</div>
        <?php endif; ?>

        <?php if (!empty($periodosOcupados) && $equipoSeleccionado): ?>
            <div class="loan-detail-card">
                <strong>Períodos ya ocupados o pendientes para <?= htmlspecialchars($equipoSeleccionado['codigo_inventario'] . ' — ' . $equipoSeleccionado['nombre']) ?>:</strong>
                <ul style="margin:6px 0 0 18px;">
                    <?php foreach ($periodosOcupados as $po): ?>
                        <li>
                            <?= htmlspecialchars(date('d/m/Y', strtotime($po['fecha_inicio']))) ?>
                            al
                            <?= htmlspecialchars(date('d/m/Y', strtotime($po['fecha_devolucion_prevista']))) ?>
                            (<?= htmlspecialchars(($po['estado_prestamo'] ?? '') === 'Prestado' ? 'Prestado' : $po['estado_solicitud']) ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="solicitar.php" class="user-form">
            <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCSRF) ?>">
            <div class="form-section-title">Datos de la solicitud</div>
            <div class="form-grid">
                <div class="form-group form-group-full">
                    <label for="id_equipo">Equipo *</label>
                    <select id="id_equipo" name="id_equipo" required <?= (!$equiposReservables || $bloqueadoPorVencido) ? 'disabled' : '' ?>>
                        <option value="">Selecciona un equipo</option>
                        <?php foreach ($equiposReservables as $equipo): ?>
                            <option value="<?= (int)$equipo['id_equipo'] ?>" <?= (string)$datos['id_equipo'] === (string)$equipo['id_equipo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($equipo['codigo_inventario'] . ' — ' . $equipo['nombre'] . ' (' . $equipo['nombre_tipo'] . ') [' . $equipo['nombre_estado'] . ']') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Los equipos en mantenimiento o dados de baja se excluyen automáticamente. Puedes consultar el calendario de disponibilidad en <a href="reservas.php">Reservas</a>.</small>
                </div>
                <div class="form-group">
                    <label for="fecha_inicio">Fecha de inicio *</label>
                    <input id="fecha_inicio" name="fecha_inicio" type="date" value="<?= htmlspecialchars($datos['fecha_inicio']) ?>" min="<?= date('Y-m-d') ?>" required <?= $bloqueadoPorVencido ? 'disabled' : '' ?>>
                </div>
                <div class="form-group">
                    <label for="fecha_devolucion_prevista">Fecha prevista de devolución *</label>
                    <input id="fecha_devolucion_prevista" name="fecha_devolucion_prevista" type="date" value="<?= htmlspecialchars($datos['fecha_devolucion_prevista']) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required <?= $bloqueadoPorVencido ? 'disabled' : '' ?>>
                </div>
                <?php if ($esDocente): ?>
                <div class="form-group form-group-full">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="es_prioridad" value="1" <?= $datos['es_prioridad'] ? 'checked' : '' ?> <?= $bloqueadoPorVencido ? 'disabled' : '' ?>>
                        Marcar como solicitud prioritaria de docente para actividad académica
                    </label>
                </div>
                <?php endif; ?>
                <div class="form-group form-group-full">
                    <label for="proposito">Propósito del préstamo *</label>
                    <textarea id="proposito" name="proposito" rows="5" maxlength="500" required <?= $bloqueadoPorVencido ? 'disabled' : '' ?>><?= htmlspecialchars($datos['proposito']) ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <a href="reservas.php" class="form-button cancel-button">Ver disponibilidad</a>
                <a href="mis_solicitudes.php" class="form-button cancel-button">Mis solicitudes</a>
                <button class="form-button submit-button" type="submit" <?= (!$equiposReservables || $bloqueadoPorVencido) ? 'disabled' : '' ?>>Registrar solicitud</button>
            </div>
        </form>
    </section>
    <footer class="dashboard-footer">SIGPET © 2026 | Sistema de Gestión de Préstamos de Equipos Tecnológicos</footer>
<script>
document.getElementById('id_equipo')?.addEventListener('change', function () {
    if (!this.value) return;
    const inicio = document.getElementById('fecha_inicio')?.value || '';
    const fin = document.getElementById('fecha_devolucion_prevista')?.value || '';
    const params = new URLSearchParams({ id_equipo: this.value });
    if (inicio) params.set('fecha_inicio', inicio);
    if (fin) params.set('fecha_devolucion_prevista', fin);
    const enlace = document.createElement('a');
    enlace.href = 'solicitar.php?' + params.toString();
    enlace.style.display = 'none';
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();
});
</script>
</main>
</div>
</body>
</html>
<?php $conexion->close(); ?>
