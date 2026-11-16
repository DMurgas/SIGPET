<?php

require_once __DIR__ . '/../includes/verificar_rol.php';

verificarPermiso('prestamos.historial');

require_once __DIR__ . '/../config/conexion.php';

$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';

$idUsuarioFiltro = filter_var(
    $_GET['id_usuario'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$idEquipoFiltro = filter_var(
    $_GET['id_equipo'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$estadoFiltro = trim($_GET['estado'] ?? '');

$estadosPermitidos = [
    'Prestado',
    'Devuelto'
];

if (!in_array($estadoFiltro, $estadosPermitidos, true)) {
    $estadoFiltro = '';
}


/*
 * Obtener usuarios activos para el filtro.
 */
$stmtUsuarios = $conexion->prepare(
    "SELECT
        id_usuario,
        nombres,
        apellidos,
        codigo_institucional
     FROM usuarios
     WHERE activo = 1
     ORDER BY nombres, apellidos"
);

$stmtUsuarios->execute();

$usuarios = $stmtUsuarios->get_result();


/*
 * Obtener equipos para el filtro.
 */
$stmtEquipos = $conexion->prepare(
    "SELECT
        id_equipo,
        codigo_inventario,
        nombre
     FROM equipos
     ORDER BY nombre, codigo_inventario"
);

$stmtEquipos->execute();

$equipos = $stmtEquipos->get_result();


/*
 * Consulta principal del historial.
 */
$sql = "
    SELECT
        p.id_prestamo,
        p.id_solicitud,
        p.id_equipo,
        p.id_usuario,
        p.fecha_entrega,
        p.entregado_por,
        p.autorizado_por,
        p.condicion_inicial,
        p.estado_prestamo,
        p.fecha_devolucion_real,
        p.condicion_final,
        p.observaciones,
        p.fecha_registro,

        u.nombres,
        u.apellidos,
        u.codigo_institucional,

        ue.nombres AS entregado_nombres,
        ue.apellidos AS entregado_apellidos,
        ua.nombres AS autorizado_nombres,
        ua.apellidos AS autorizado_apellidos,

        e.codigo_inventario,
        e.nombre AS nombre_equipo,
        e.marca,
        e.modelo,

        s.fecha_inicio,
        s.fecha_devolucion_prevista,
        s.proposito

    FROM prestamos p

    INNER JOIN usuarios u
        ON u.id_usuario = p.id_usuario

    LEFT JOIN usuarios ue
        ON ue.id_usuario = p.entregado_por

    LEFT JOIN usuarios ua
        ON ua.id_usuario = p.autorizado_por

    INNER JOIN equipos e
        ON e.id_equipo = p.id_equipo

    INNER JOIN solicitudes_prestamo s
        ON s.id_solicitud = p.id_solicitud

    WHERE 1 = 1
";

$tipos = '';
$parametros = [];


/*
 * Filtro por usuario.
 */
if ($idUsuarioFiltro) {

    $sql .= " AND p.id_usuario = ?";

    $tipos .= 'i';

    $parametros[] = $idUsuarioFiltro;
}


/*
 * Filtro por equipo.
 */
if ($idEquipoFiltro) {

    $sql .= " AND p.id_equipo = ?";

    $tipos .= 'i';

    $parametros[] = $idEquipoFiltro;
}


/*
 * Filtro por estado del préstamo.
 */
if ($estadoFiltro !== '') {

    $sql .= " AND p.estado_prestamo = ?";

    $tipos .= 's';

    $parametros[] = $estadoFiltro;
}


$sql .= " ORDER BY p.id_prestamo DESC";


$stmt = $conexion->prepare($sql);

if ($tipos !== '') {

    $stmt->bind_param(
        $tipos,
        ...$parametros
    );

}

$stmt->execute();

$historial = $stmt->get_result();
$todosHistorial = $historial->fetch_all(MYSQLI_ASSOC);
$totalHistorial = count($todosHistorial);
$porPagina = 8;
$totalPaginas = max(1, (int)ceil($totalHistorial / $porPagina));
$paginaActual = max(1, min($totalPaginas, (int)($_GET['pagina'] ?? 1)));
$offset = ($paginaActual - 1) * $porPagina;
$historialPagina = array_slice($todosHistorial, $offset, $porPagina);

function urlHistorialPagina(int $pag): string
{
    return 'historial.php?' . http_build_query(array_merge($_GET, ['pagina' => $pag]));
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

    <title>
        Historial de préstamos | SIGPET
    </title>
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

</head>


<body class="dashboard-body">

<div class="dashboard-layout">


    <?php

    $rutaBase = '../';

    $paginaActiva = 'historial';

    require __DIR__ . '/../includes/sidebar.php';

    ?>


    <main class="dashboard-main">


        <header class="dashboard-header">

            <div>

                <span class="header-subtitle">
                    GESTIÓN DE PRÉSTAMOS
                </span>

                <h1>
                    Historial de préstamos
                </h1>

                <p class="header-desc">
                    Consulta los préstamos realizados por usuario, equipo y estado.
                </p>

            </div>

        </header>


        <section class="inventory-container">



            <form
                method="GET"
                class="loan-filter"
            >


                <div>

                    <label for="id_usuario">
                        Usuario
                    </label>


                    <select
                        id="id_usuario"
                        name="id_usuario"
                    >

                        <option value="">
                            Todos los usuarios
                        </option>


                        <?php while (
                            $usuario = $usuarios->fetch_assoc()
                        ): ?>

                            <option
                                value="<?= (int)$usuario['id_usuario'] ?>"
                                <?= $idUsuarioFiltro ===
                                    (int)$usuario['id_usuario']
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $usuario['nombres'] . ' ' .
                                    $usuario['apellidos'] .
                                    ' — ' .
                                    (
                                        $usuario[
                                            'codigo_institucional'
                                        ] ?? ''
                                    )
                                ) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>



                <div>

                    <label for="id_equipo">
                        Equipo
                    </label>


                    <select
                        id="id_equipo"
                        name="id_equipo"
                    >

                        <option value="">
                            Todos los equipos
                        </option>


                        <?php while (
                            $equipo = $equipos->fetch_assoc()
                        ): ?>

                            <option
                                value="<?= (int)$equipo['id_equipo'] ?>"
                                <?= $idEquipoFiltro ===
                                    (int)$equipo['id_equipo']
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $equipo['codigo_inventario'] .
                                    ' — ' .
                                    $equipo['nombre']
                                ) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>



                <div>

                    <label for="estado">
                        Estado
                    </label>


                    <select
                        id="estado"
                        name="estado"
                    >

                        <option value="">
                            Todos los estados
                        </option>


                        <option
                            value="Prestado"
                            <?= $estadoFiltro === 'Prestado'
                                ? 'selected'
                                : '' ?>
                        >
                            Prestado
                        </option>


                        <option
                            value="Devuelto"
                            <?= $estadoFiltro === 'Devuelto'
                                ? 'selected'
                                : '' ?>
                        >
                            Devuelto
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


                    <a
                        class="action-button"
                        href="historial.php"
                    >
                        Limpiar
                    </a>

                </div>

            </form>



            <div class="table-responsive">

                <table class="inventory-table">

                    <thead>

                        <tr>

                            <th>
                                ID
                            </th>

                            <th>
                                Solicitante
                            </th>

                            <th>
                                Equipo
                            </th>

                            <th>
                                Entrega
                            </th>

                            <th>
                                Devolución prevista
                            </th>

                            <th>
                                Devolución real
                            </th>

                            <th>
                                Estado
                            </th>

                            <th>
                                Detalle
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (!empty($historialPagina)): ?>


                        <?php foreach ($historialPagina as $registro): ?>
                            <?php
                            $estadoLimpio = strtolower((string) $registro['estado_prestamo']);
                            $toneBadge = $estadoLimpio === 'devuelto' ? 'devuelto' : ($estadoLimpio === 'atrasado' ? 'danger' : 'prestado');
                            $entregadoNombre = trim((string) (($registro['entregado_nombres'] ?? '') . ' ' . ($registro['entregado_apellidos'] ?? '')));
                            $autorizadoNombre = trim((string) (($registro['autorizado_nombres'] ?? '') . ' ' . ($registro['autorizado_apellidos'] ?? '')));
                            $seccionesDetalle = [
                                [
                                    'label' => 'Propósito de uso',
                                    'text'  => (string) ($registro['proposito'] ?? ''),
                                ],
                                [
                                    'label' => 'Condición inicial al entregar',
                                    'text'  => (string) ($registro['condicion_inicial'] ?? ''),
                                ],
                            ];
                            if (!empty($registro['condicion_final'])) {
                                $seccionesDetalle[] = [
                                    'label' => 'Condición final al devolver',
                                    'text'  => (string) $registro['condicion_final'],
                                    'tone'  => 'success',
                                ];
                            }
                            if (!empty($registro['observaciones'])) {
                                $seccionesDetalle[] = [
                                    'label' => 'Observaciones registradas',
                                    'text'  => (string) $registro['observaciones'],
                                    'tone'  => $estadoLimpio === 'atrasado' ? 'warning' : '',
                                ];
                            }
                            $payloadDetalle = [
                                'eyebrow'  => 'Préstamo #' . (int) $registro['id_prestamo'] . ' · Solicitud #' . (int) $registro['id_solicitud'],
                                'title'    => $registro['codigo_inventario'] . ' — ' . $registro['nombre_equipo'],
                                'subtitle' => trim((string) (($registro['marca'] ?? '') . ' ' . ($registro['modelo'] ?? ''))),
                                'badge'    => [
                                    'label' => (string) $registro['estado_prestamo'],
                                    'tone'  => $toneBadge,
                                ],
                                'meta'     => [
                                    [
                                        'label' => 'Solicitante',
                                        'value' => trim($registro['nombres'] . ' ' . $registro['apellidos'] . (!empty($registro['codigo_institucional']) ? ' (' . $registro['codigo_institucional'] . ')' : '')),
                                    ],
                                    [
                                        'label' => 'Fecha de entrega',
                                        'value' => date('d/m/Y H:i', strtotime($registro['fecha_entrega'])),
                                    ],
                                    [
                                        'label' => 'Devolución prevista',
                                        'value' => date('d/m/Y', strtotime($registro['fecha_devolucion_prevista'])),
                                    ],
                                    [
                                        'label' => 'Devolución real',
                                        'value' => !empty($registro['fecha_devolucion_real']) ? date('d/m/Y H:i', strtotime($registro['fecha_devolucion_real'])) : 'Pendiente de devolución',
                                    ],
                                    [
                                        'label' => 'Entregado por',
                                        'value' => $entregadoNombre !== '' ? $entregadoNombre : 'No especificado',
                                    ],
                                    [
                                        'label' => 'Autorizado por',
                                        'value' => $autorizadoNombre !== '' ? $autorizadoNombre : 'No especificado',
                                    ],
                                ],
                                'sections' => $seccionesDetalle,
                            ];
                            $detalleJson = htmlspecialchars(
                                json_encode($payloadDetalle, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                            <tr class="sigpet-row-clickable" data-detail="<?= $detalleJson ?>">


                                <td>

                                    <strong>
                                        #<?= (int)$registro[
                                            'id_prestamo'
                                        ] ?>
                                    </strong>

                                    <small class="table-subtext">

                                        Solicitud
                                        #<?= (int)$registro[
                                            'id_solicitud'
                                        ] ?>

                                    </small>

                                </td>



                                <td>

                                    <?= htmlspecialchars(
                                        $registro['nombres'] . ' ' .
                                        $registro['apellidos']
                                    ) ?>


                                    <small class="table-subtext">

                                        <?= htmlspecialchars(
                                            $registro[
                                                'codigo_institucional'
                                            ] ?? ''
                                        ) ?>

                                    </small>

                                </td>



                                <td>

                                    <?= htmlspecialchars(
                                        $registro[
                                            'codigo_inventario'
                                        ] .
                                        ' — ' .
                                        $registro[
                                            'nombre_equipo'
                                        ]
                                    ) ?>


                                    <small class="table-subtext">

                                        <?= htmlspecialchars(
                                            trim(
                                                (
                                                    $registro[
                                                        'marca'
                                                    ] ?? ''
                                                ) .
                                                ' ' .
                                                (
                                                    $registro[
                                                        'modelo'
                                                    ] ?? ''
                                                )
                                            )
                                        ) ?>

                                    </small>

                                </td>



                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            'd/m/Y H:i',
                                            strtotime(
                                                $registro[
                                                    'fecha_entrega'
                                                ]
                                            )
                                        )
                                    ) ?>

                                </td>



                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            'd/m/Y',
                                            strtotime(
                                                $registro[
                                                    'fecha_devolucion_prevista'
                                                ]
                                            )
                                        )
                                    ) ?>

                                </td>



                                <td>

                                    <?php if (
                                        $registro[
                                            'fecha_devolucion_real'
                                        ]
                                    ): ?>

                                        <?= htmlspecialchars(
                                            date(
                                                'd/m/Y H:i',
                                                strtotime(
                                                    $registro[
                                                        'fecha_devolucion_real'
                                                    ]
                                                )
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        <span class="table-subtext">
                                            Pendiente
                                        </span>

                                    <?php endif; ?>

                                </td>



                                <td>

                                    <?php

                                    $claseEstado =
                                        strtolower(
                                            $registro[
                                                'estado_prestamo'
                                            ]
                                        );

                                    ?>


                                    <span
                                        class="loan-status status-<?= htmlspecialchars(
                                            $claseEstado
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $registro[
                                                'estado_prestamo'
                                            ]
                                        ) ?>

                                    </span>

                                </td>



                                <td>
                                    <button type="button" class="sigpet-detail-btn sigpet-detail-trigger" aria-label="Ver detalle del préstamo #<?= (int)$registro['id_prestamo'] ?>">
                                        <?= sigpetIcono('eye', 15) ?>
                                        <span>Ver detalle</span>
                                    </button>
                                </td>

                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="8"
                                class="empty-table"
                            >
                                No hay préstamos que coincidan
                                con los filtros seleccionados.
                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>

            <?php if ($totalPaginas > 1): ?>
                <div class="sigpet-pagination">
                    <span>Mostrando <?= $offset + 1 ?>–<?= min($offset + $porPagina, $totalHistorial) ?> de <?= $totalHistorial ?></span>
                    <div class="sigpet-page-links">
                        <a class="sigpet-page-link <?= $paginaActual <= 1 ? 'disabled' : '' ?>" href="<?= htmlspecialchars(urlHistorialPagina(max(1, $paginaActual - 1))) ?>">Anterior</a>
                        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                            <a class="sigpet-page-link <?= $p === $paginaActual ? 'active' : '' ?>" href="<?= htmlspecialchars(urlHistorialPagina($p)) ?>"><?= $p ?></a>
                        <?php endfor; ?>
                        <a class="sigpet-page-link <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>" href="<?= htmlspecialchars(urlHistorialPagina(min($totalPaginas, $paginaActual + 1))) ?>">Siguiente</a>
                    </div>
                </div>
            <?php endif; ?>

        </section>



        <footer class="dashboard-footer">

            SIGPET © 2026 |
            Sistema de Gestión de Préstamos
            de Equipos Tecnológicos

        </footer>


    </main>

</div>

</body>

</html>


<?php

$stmt->close();

$stmtUsuarios->close();

$stmtEquipos->close();

$conexion->close();

?>