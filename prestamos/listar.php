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
    'Rechazada'
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
        s.estado_solicitud,
        s.motivo_rechazo,
        s.fecha_solicitud,
        s.fecha_decision,

        u.nombres,
        u.apellidos,
        u.correo_institucional,
        u.codigo_institucional,

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

     INNER JOIN equipos e
        ON e.id_equipo = s.id_equipo

     INNER JOIN tipos_equipo t
        ON t.id_tipo = e.id_tipo

     INNER JOIN estados_equipo es
        ON es.id_estado = e.id_estado

     LEFT JOIN prestamos p
        ON p.id_solicitud = s.id_solicitud

     WHERE s.estado_solicitud = ?

     ORDER BY s.id_solicitud DESC"
);

$stmt->bind_param('s', $estadoFiltro);
$stmt->execute();

$solicitudes = $stmt->get_result();

$tokenCSRF = obtenerTokenCSRF();

$mensajeExito = '';
$mensajeError = '';

if (
    isset($_GET['entregada']) &&
    $_GET['entregada'] === '1'
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

            </div>


            <div class="header-user">

                <div class="header-user-text">

                    <strong>

                        <?= htmlspecialchars(
                            $nombreSesion . ' ' . $apellidoSesion
                        ) ?>

                    </strong>

                    <span>

                        <?= htmlspecialchars(
                            $rolSesion
                        ) ?>

                    </span>

                </div>


                <div class="user-avatar header-avatar">

                    <?= htmlspecialchars(
                        strtoupper(
                            substr($nombreSesion, 0, 1)
                        )
                    ) ?>

                </div>

            </div>

        </header>


        <section class="users-intro">

            <h2>
                Gestión de solicitudes
            </h2>

            <p>
                Consulta las solicitudes y registra la decisión correspondiente.
            </p>

        </section>


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


            <div class="inventory-toolbar">

                <div>

                    <h3>
                        Solicitudes
                    </h3>

                    <p>
                        Selecciona el estado que deseas consultar.
                    </p>

                </div>

            </div>


            <form
                method="GET"
                class="loan-filter"
            >

                <label for="estado">
                    Estado
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

                </select>


                <button
                    class="filter-button"
                    type="submit"
                >
                    Consultar
                </button>

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


                            <tr>


                                <td>

                                    <strong>

                                        #<?= (int)
                                            $solicitud['id_solicitud']
                                        ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $solicitud['nombres'] .
                                        ' ' .
                                        $solicitud['apellidos']
                                    ) ?>


                                    <small class="table-subtext">

                                        <?= htmlspecialchars(
                                            $solicitud[
                                                'codigo_institucional'
                                            ] ?? ''
                                        ) ?>

                                    </small>

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

                                    <?= htmlspecialchars(
                                        $solicitud[
                                            'proposito'
                                        ]
                                    ) ?>

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


                                    <?php if (
                                        $estadoFiltro === 'Pendiente'
                                    ): ?>


                                        <div class="action-buttons">


                                            <form
                                                method="POST"
                                                action="aprobar.php"
                                            >


                                                <input
                                                    type="hidden"
                                                    name="token_csrf"
                                                    value="<?= htmlspecialchars(
                                                        $tokenCSRF
                                                    ) ?>"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="id_solicitud"
                                                    value="<?= (int)
                                                        $solicitud[
                                                            'id_solicitud'
                                                        ] ?>"
                                                >


                                                <button
                                                    class="action-button approve-action"
                                                    type="submit"
                                                    onclick="return confirm('¿Deseas aprobar esta solicitud? El equipo pasará a estado Reservado.');"
                                                >
                                                    Aprobar
                                                </button>


                                            </form>


                                            <a
                                                class="action-button reject-action"
                                                href="rechazar.php?id=<?= (int)
                                                    $solicitud[
                                                        'id_solicitud'
                                                    ] ?>"
                                            >
                                                Rechazar
                                            </a>


                                        </div>


                                    <?php elseif (
                                        $estadoFiltro === 'Aprobada'
                                    ): ?>


                                        <?php if (
                                            empty(
                                                $solicitud[
                                                    'id_prestamo'
                                                ]
                                            )
                                        ): ?>


                                            <a
                                                class="action-button approve-action"
                                                href="entregar.php?id=<?= (int)
                                                    $solicitud[
                                                        'id_solicitud'
                                                    ] ?>"
                                            >
                                                Registrar entrega
                                            </a>


                                        <?php elseif (
                                            $solicitud[
                                                'estado_prestamo'
                                            ] === 'Prestado'
                                            &&
                                            empty(
                                                $solicitud[
                                                    'fecha_devolucion_real'
                                                ]
                                            )
                                        ): ?>


                                            <a
                                                class="action-button approve-action"
                                                href="devolver.php?id=<?= (int)
                                                    $solicitud[
                                                        'id_prestamo'
                                                    ] ?>"
                                            >
                                                Registrar devolución
                                            </a>


                                        <?php elseif (
                                            $solicitud[
                                                'estado_prestamo'
                                            ] === 'Devuelto'
                                            ||
                                            !empty(
                                                $solicitud[
                                                    'fecha_devolucion_real'
                                                ]
                                            )
                                        ): ?>


                                            <span class="table-subtext">
                                                Devolución registrada
                                            </span>


                                        <?php else: ?>


                                            <span class="table-subtext">
                                                Préstamo registrado
                                            </span>


                                        <?php endif; ?>


                                    <?php elseif (
                                        $estadoFiltro === 'Rechazada'
                                    ): ?>


                                        <span class="table-subtext">

                                            Motivo:

                                            <?= htmlspecialchars(
                                                $solicitud[
                                                    'motivo_rechazo'
                                                ] ?? ''
                                            ) ?>

                                        </span>


                                    <?php endif; ?>


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