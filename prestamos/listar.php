<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('prestamos.gestionar');

require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';

$estadoFiltro = $_GET['estado'] ?? 'Pendiente';

$estadosPermitidos = [
    'Pendiente',
    'Aprobada',
    'Rechazada',
    'Cancelada'
];

if (!in_array($estadoFiltro, $estadosPermitidos, true)) {
    $estadoFiltro = 'Pendiente';
}

$stmt = $conexion->prepare(
    "SELECT
        s.id_solicitud,
        s.fecha_inicio,
        s.fecha_devolucion_prevista,
        s.proposito,
        s.es_prioridad,
        s.estado_solicitud,
        s.motivo_rechazo,
        s.fecha_solicitud,
        s.fecha_decision,

        u.nombres,
        u.apellidos,
        u.correo_institucional,
        u.codigo_institucional,
        r.nombre_rol,

        e.id_equipo,
        e.codigo_inventario,
        e.nombre AS nombre_equipo,
        e.marca,
        e.modelo,

        t.nombre_tipo,
        es.nombre_estado,

        p.id_prestamo,
        p.estado_prestamo,
        p.fecha_devolucion_real

     FROM solicitudes_prestamo s

     INNER JOIN usuarios u
        ON u.id_usuario = s.id_usuario

     INNER JOIN roles r
        ON r.id_rol = u.id_rol

     INNER JOIN equipos e
        ON e.id_equipo = s.id_equipo

     INNER JOIN tipos_equipo t
        ON t.id_tipo = e.id_tipo

     INNER JOIN estados_equipo es
        ON es.id_estado = e.id_estado

     LEFT JOIN prestamos p
        ON p.id_solicitud = s.id_solicitud

     WHERE s.estado_solicitud = ?

     ORDER BY s.es_prioridad DESC, s.id_solicitud DESC"
);

$stmt->bind_param('s', $estadoFiltro);
$stmt->execute();

$solicitudes = $stmt->get_result();

$tokenCSRF = obtenerTokenCSRF();

$mensajeExito = '';
$mensajeError = '';

if (
    (isset($_GET['entregada']) && $_GET['entregada'] === '1') ||
    (isset($_GET['ok']) && $_GET['ok'] === 'entrega')
) {
    $mensajeExito = 'La entrega se registró correctamente.';
}

if (
    isset($_GET['devuelta']) &&
    $_GET['devuelta'] === '1'
) {
    $mensajeExito = 'La devolución se registró correctamente y el estado del equipo fue actualizado.';
}

if (
    isset($_GET['aprobada']) &&
    $_GET['aprobada'] === '1'
) {
    $mensajeExito = 'La solicitud fue aprobada correctamente.';
}

if (
    isset($_GET['rechazada']) &&
    $_GET['rechazada'] === '1'
) {
    $mensajeExito = 'La solicitud fue rechazada correctamente.';
}

switch ($_GET['error'] ?? '') {

    case 'csrf':
        $mensajeError = 'La solicitud de seguridad no es válida.';
        break;

    case 'solicitud':
        $mensajeError = 'La solicitud indicada no es válida.';
        break;

    case 'estado':
        $mensajeError = 'La solicitud no se encuentra en un estado válido.';
        break;

    case 'equipo':
        $mensajeError = 'El equipo no se encuentra en el estado requerido.';
        break;

    case 'solapada':
        $mensajeError = 'No se puede aprobar la solicitud porque existe un conflicto de fechas con otra reserva o préstamo activo.';
        break;

    case 'ya_entregado':
        $mensajeError = 'La entrega de esta solicitud ya fue registrada.';
        break;

    case 'estado_prestado':
        $mensajeError = 'El préstamo no se encuentra actualmente en estado Prestado.';
        break;

    case 'estado_equipo':
        $mensajeError = 'El equipo no se encuentra actualmente en estado Prestado.';
        break;

    case 'ya_devuelto':
        $mensajeError = 'Este préstamo ya fue devuelto.';
        break;

    case 'estado_disponible':
        $mensajeError = 'No fue posible localizar el estado Disponible.';
        break;

    case 'registro_prestamo':
    case 'prestamo':
        $mensajeError = 'No fue posible registrar el préstamo.';
        break;

    case 'actualizacion_prestamo':
        $mensajeError = 'No fue posible registrar la devolución del préstamo.';
        break;

    case 'equipo_actualizacion':
    case 'actualizacion_equipo':
        $mensajeError = 'No fue posible actualizar el estado del equipo.';
        break;

    case 'general':
        $mensajeError = 'No fue posible completar la operación.';
        break;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Gestionar solicitudes | SIGPET</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">

    <link
        rel="stylesheet"
        href="../CSS/base.css"
    >

    <link
        rel="stylesheet"
        href="../CSS/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="../CSS/inventario.css"
    >

    <link
        rel="stylesheet"
        href="../CSS/prestamos.css"
    >

    <link rel="stylesheet" href="../CSS/theme-profesional.css">
</head>

<body class="dashboard-body">

<div class="dashboard-layout">

    <?php

    $rutaBase = '../';
    $paginaActiva = 'prestamos';

    require __DIR__ . '/../includes/sidebar.php';

    ?>

    <main class="dashboard-main">

        <header class="dashboard-header">

            <div>

                <span class="header-subtitle">
                    GESTIÓN DE PRÉSTAMOS
                </span>

                <h1>
                    Solicitudes de préstamo
                </h1>

                <p class="header-desc">
                    Consulta las solicitudes recibidas, aprueba o rechaza peticiones y registra entregas o devoluciones.
                </p>

            </div>

        </header>


        <?php if ($mensajeExito !== ''): ?>

            <div class="success-message">

                <?= htmlspecialchars(
                    $mensajeExito
                ) ?>

            </div>

        <?php endif; ?>


        <?php if ($mensajeError !== ''): ?>

            <div class="error-message">

                <?= htmlspecialchars(
                    $mensajeError
                ) ?>

            </div>

        <?php endif; ?>


        <section class="inventory-container">


            <form
                method="GET"
                class="loan-filter"
            >

                <div>

                    <label for="estado">
                        Filtrar por estado
                    </label>


                    <select
                        id="estado"
                        name="estado"
                    >

                    <option
                        value="Pendiente"
                        <?= $estadoFiltro === 'Pendiente'
                            ? 'selected'
                            : '' ?>
                    >
                        Pendiente
                    </option>


                    <option
                        value="Aprobada"
                        <?= $estadoFiltro === 'Aprobada'
                            ? 'selected'
                            : '' ?>
                    >
                        Aprobada
                    </option>


                    <option
                        value="Rechazada"
                        <?= $estadoFiltro === 'Rechazada'
                            ? 'selected'
                            : '' ?>
                    >
                        Rechazada
                    </option>

                    <option
                        value="Cancelada"
                        <?= $estadoFiltro === 'Cancelada'
                            ? 'selected'
                            : '' ?>
                    >
                        Cancelada
                    </option>

                </select>

                </div>

                <div class="action-buttons">
                    <button
                        class="filter-button"
                        type="submit"
                    >
                        Consultar
                    </button>
                </div>

            </form>


            <div class="table-responsive">

                <table class="inventory-table">


                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>
                                Solicitante
                            </th>

                            <th>
                                Equipo
                            </th>

                            <th>
                                Período
                            </th>

                            <th>
                                Propósito
                            </th>

                            <th>
                                Estado
                            </th>

                            <th>
                                Acciones
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if ($solicitudes->num_rows > 0): ?>


                        <?php while (
                            $solicitud =
                            $solicitudes->fetch_assoc()
                        ): ?>
                            <?php
                            $estadoSol = (string) $solicitud['estado_solicitud'];
                            $toneSol = $estadoSol === 'Aprobada' ? 'success'
                                : ($estadoSol === 'Rechazada' ? 'danger'
                                : ($estadoSol === 'Cancelada' ? 'neutral' : 'pendiente'));
                            $seccionesSol = [
                                [
                                    'label' => 'Propósito de uso',
                                    'text'  => (string) ($solicitud['proposito'] ?? ''),
                                ],
                            ];
                            if (!empty($solicitud['motivo_rechazo'])) {
                                $seccionesSol[] = [
                                    'label' => 'Motivo de rechazo',
                                    'text'  => (string) $solicitud['motivo_rechazo'],
                                    'tone'  => 'danger',
                                ];
                            }
                            $metaSol = [
                                [
                                    'label' => 'Solicitante',
                                    'value' => trim($solicitud['nombres'] . ' ' . $solicitud['apellidos'] . (!empty($solicitud['codigo_institucional']) ? ' (' . $solicitud['codigo_institucional'] . ')' : '')),
                                ],
                                [
                                    'label' => 'Rol y correo',
                                    'value' => trim(($solicitud['nombre_rol'] ?? '') . (!empty($solicitud['correo_institucional']) ? ' · ' . $solicitud['correo_institucional'] : '')),
                                ],
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
                                    'value' => ((int) ($solicitud['es_prioridad'] ?? 0) === 1) ? 'Prioridad docente' : 'Estándar',
                                ],
                            ];
                            if (!empty($solicitud['fecha_decision'])) {
                                $metaSol[] = [
                                    'label' => 'Fecha de decisión',
                                    'value' => date('d/m/Y H:i', strtotime($solicitud['fecha_decision'])),
                                ];
                            }
                            if (!empty($solicitud['id_prestamo'])) {
                                $metaSol[] = [
                                    'label' => 'Préstamo asociado',
                                    'value' => '#' . (int) $solicitud['id_prestamo'] . ' (' . ($solicitud['estado_prestamo'] ?? '') . ')',
                                ];
                            }
                            $payloadSol = [
                                'eyebrow'  => 'Solicitud #' . (int) $solicitud['id_solicitud'],
                                'title'    => $solicitud['codigo_inventario'] . ' — ' . $solicitud['nombre_equipo'],
                                'subtitle' => trim($solicitud['nombre_tipo'] . (!empty($solicitud['marca']) ? ' · ' . $solicitud['marca'] : '') . (!empty($solicitud['modelo']) ? ' ' . $solicitud['modelo'] : '')),
                                'badge'    => [
                                    'label' => $estadoSol,
                                    'tone'  => $toneSol,
                                ],
                                'meta'     => $metaSol,
                                'sections' => $seccionesSol,
                            ];
                            $detalleSolJson = htmlspecialchars(
                                json_encode($payloadSol, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                            <tr class="sigpet-row-clickable" data-detail="<?= $detalleSolJson ?>">


                                <td>

                                    <span class="sigpet-equip-tag">
                                        #<?= (int)$solicitud['id_solicitud'] ?>
                                    </span>

                                </td>


                                <td>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $solicitud['nombres'] .
                                            ' ' .
                                            $solicitud['apellidos']
                                        ) ?>
                                    </strong>

                                    <small class="table-subtext">

                                        <?= htmlspecialchars(
                                            trim(($solicitud['nombre_rol'] ?? '') . ' · ' . ($solicitud['codigo_institucional'] ?? ''))
                                        ) ?>

                                    </small>

                                    <?php if ((int)($solicitud['es_prioridad'] ?? 0) === 1): ?>
                                        <span class="loan-status status-prioridad">Prioridad docente</span>
                                    <?php endif; ?>

                                </td>


                                <td>


                                    <?= htmlspecialchars(
                                        $solicitud[
                                            'codigo_inventario'
                                        ] .
                                        ' — ' .
                                        $solicitud[
                                            'nombre_equipo'
                                        ]
                                    ) ?>


                                    <small class="table-subtext">


                                        <?= htmlspecialchars(
                                            $solicitud[
                                                'nombre_tipo'
                                            ]
                                        ) ?>


                                        <?php if (
                                            !empty(
                                                $solicitud['marca']
                                            )
                                        ): ?>

                                            |
                                            <?= htmlspecialchars(
                                                $solicitud['marca']
                                            ) ?>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $solicitud['modelo']
                                            )
                                        ): ?>

                                            <?= htmlspecialchars(
                                                $solicitud['modelo']
                                            ) ?>

                                        <?php endif; ?>


                                    </small>


                                </td>


                                <td>


                                    <?= htmlspecialchars(
                                        $solicitud[
                                            'fecha_inicio'
                                        ]
                                    ) ?>


                                    <small class="table-subtext">

                                        Hasta

                                        <?= htmlspecialchars(
                                            $solicitud[
                                                'fecha_devolucion_prevista'
                                            ]
                                        ) ?>

                                    </small>


                                </td>


                                <td>
                                    <span class="sigpet-cell-clamp"><?= htmlspecialchars($solicitud['proposito']) ?></span>
                                </td>


                                <td>


                                    <span
                                        class="loan-status status-<?= htmlspecialchars(
                                            strtolower(
                                                $solicitud[
                                                    'estado_solicitud'
                                                ]
                                            )
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $solicitud[
                                                'estado_solicitud'
                                            ]
                                        ) ?>

                                    </span>


                                </td>


                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="sigpet-detail-btn sigpet-detail-trigger" aria-label="Ver detalle de la solicitud #<?= (int)$solicitud['id_solicitud'] ?>">
                                            <?= sigpetIcono('eye', 15) ?>
                                            <span>Ver detalle</span>
                                        </button>

                                    <?php if ($estadoFiltro === 'Pendiente'): ?>

                                        <form method="POST" action="aprobar.php">
                                            <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCSRF) ?>">
                                            <input type="hidden" name="id_solicitud" value="<?= (int)$solicitud['id_solicitud'] ?>">
                                            <button class="action-button approve-action" type="submit" onclick="return confirm('¿Deseas aprobar esta solicitud? El equipo pasará a estado Reservado.');">
                                                Aprobar
                                            </button>
                                        </form>

                                        <a class="action-button reject-action" href="rechazar.php?id=<?= (int)$solicitud['id_solicitud'] ?>">
                                            Rechazar
                                        </a>

                                    <?php elseif ($estadoFiltro === 'Aprobada'): ?>

                                        <?php if (empty($solicitud['id_prestamo'])): ?>
                                            <a class="action-button approve-action" href="entregar.php?id=<?= (int)$solicitud['id_solicitud'] ?>">
                                                Registrar entrega
                                            </a>
                                        <?php elseif ($solicitud['estado_prestamo'] === 'Prestado' && empty($solicitud['fecha_devolucion_real'])): ?>
                                            <a class="action-button approve-action" href="devolver.php?id=<?= (int)$solicitud['id_prestamo'] ?>">
                                                Registrar devolución
                                            </a>
                                        <?php elseif ($solicitud['estado_prestamo'] === 'Devuelto' || !empty($solicitud['fecha_devolucion_real'])): ?>
                                            <span class="table-subtext">Devolución registrada</span>
                                        <?php else: ?>
                                            <span class="table-subtext">Préstamo registrado</span>
                                        <?php endif; ?>

                                    <?php elseif ($estadoFiltro === 'Rechazada'): ?>
                                        <span class="table-subtext">Rechazada (ver detalle)</span>

                                    <?php elseif ($estadoFiltro === 'Cancelada'): ?>
                                        <span class="table-subtext">Cancelada</span>

                                    <?php endif; ?>

                                    </div>
                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="7"
                                class="empty-table"
                            >
                                No hay solicitudes con el estado seleccionado.
                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>


                </table>

            </div>


        </section>


        <footer class="dashboard-footer">

            SIGPET © 2026 |
            Sistema de Gestión de Préstamos de Equipos Tecnológicos

        </footer>


    </main>

</div>

</body>
</html>


<?php

$stmt->close();
$conexion->close();

?>