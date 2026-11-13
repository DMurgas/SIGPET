<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('inventario.consultar');
verificarPermiso('inventario.consultar_estado');
verificarPermiso('inventario.buscar_disponibles');

require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

$nombre = $_SESSION['nombres'] ?? '';
$apellido = $_SESSION['apellidos'] ?? '';
$rol = $_SESSION['rol'] ?? '';

$busqueda = trim($_GET['buscar'] ?? '');

$idTipo = filter_var(
    $_GET['tipo'] ?? '',
    FILTER_VALIDATE_INT
);

$idEstado = filter_var(
    $_GET['estado'] ?? '',
    FILTER_VALIDATE_INT
);

$soloDisponibles =
    isset($_GET['disponibles']) &&
    $_GET['disponibles'] === '1';

$tipos = $conexion
    ->query(
        'SELECT id_tipo, nombre_tipo
         FROM tipos_equipo
         ORDER BY nombre_tipo'
    )
    ->fetch_all(MYSQLI_ASSOC);

$estados = $conexion
    ->query(
        'SELECT id_estado, nombre_estado
         FROM estados_equipo
         ORDER BY id_estado'
    )
    ->fetch_all(MYSQLI_ASSOC);

$sql = '
    SELECT
        e.id_equipo,
        e.codigo_inventario,
        e.nombre,
        e.marca,
        e.modelo,
        e.numero_serie,
        e.ubicacion,
        e.fecha_registro,
        t.nombre_tipo,
        t.id_tipo,
        s.id_estado,
        s.nombre_estado
    FROM equipos e
    INNER JOIN tipos_equipo t
        ON t.id_tipo = e.id_tipo
    INNER JOIN estados_equipo s
        ON s.id_estado = e.id_estado
    WHERE 1 = 1
';

$tiposBind = '';
$valores = [];

if ($busqueda !== '') {

    $sql .= '
        AND (
            e.codigo_inventario LIKE ?
            OR e.nombre LIKE ?
            OR e.marca LIKE ?
            OR e.modelo LIKE ?
            OR e.numero_serie LIKE ?
            OR e.ubicacion LIKE ?
        )
    ';

    $f = '%' . $busqueda . '%';

    for ($i = 0; $i < 6; $i++) {
        $tiposBind .= 's';
        $valores[] = $f;
    }
}

if ($idTipo) {
    $sql .= ' AND e.id_tipo = ?';
    $tiposBind .= 'i';
    $valores[] = $idTipo;
}

if ($idEstado) {
    $sql .= ' AND e.id_estado = ?';
    $tiposBind .= 'i';
    $valores[] = $idEstado;
}

if ($soloDisponibles) {
    $sql .= " AND s.nombre_estado = 'Disponible'";
}

$sql .= ' ORDER BY e.id_equipo DESC';

$stmt = $conexion->prepare($sql);

if ($valores) {

    $referencias = [$tiposBind];

    foreach ($valores as $k => $v) {
        $referencias[] = &$valores[$k];
    }

    call_user_func_array(
        [$stmt, 'bind_param'],
        $referencias
    );
}

$stmt->execute();

$resultado = $stmt->get_result();

$totalEquipos = $resultado->num_rows;

$puedeAdministrar =
    tienePermiso('inventario.registrar') ||
    tienePermiso('inventario.editar') ||
    tienePermiso('inventario.dar_baja');

$tokenCSRF = obtenerTokenCSRF();

$mensaje = '';
$tipoMensaje = '';

if (isset($_GET['registrado'])) {

    $mensaje = 'El equipo se registró correctamente.';
    $tipoMensaje = 'success';

} elseif (isset($_GET['actualizado'])) {

    $mensaje = 'La información del equipo se actualizó correctamente.';
    $tipoMensaje = 'success';

} elseif (isset($_GET['baja'])) {

    $mensaje = 'El equipo fue dado de baja lógica. El registro se conserva en el inventario.';
    $tipoMensaje = 'success';

} elseif (isset($_GET['reactivado'])) {

    $mensaje = 'El equipo volvió al estado Disponible.';
    $tipoMensaje = 'success';

} elseif (isset($_GET['mantenimiento'])) {

    $mensaje = 'El equipo fue marcado como En mantenimiento.';
    $tipoMensaje = 'success';

} elseif (isset($_GET['disponible'])) {

    $mensaje = 'El equipo volvió al estado Disponible.';
    $tipoMensaje = 'success';

} elseif (isset($_GET['error'])) {

    switch ($_GET['error']) {

        case 'prestado':
            $mensaje = 'No se puede enviar a mantenimiento un equipo que está Prestado. Primero debe registrarse su devolución.';
            break;

        case 'reservado':
            $mensaje = 'No se puede enviar a mantenimiento un equipo que está Reservado.';
            break;

        case 'baja':
            $mensaje = 'Un equipo dado de baja no puede pasar directamente a mantenimiento.';
            break;

        case 'estado':
            $mensaje = 'El equipo no se encuentra en un estado válido para realizar esta operación.';
            break;

        default:
            $mensaje = 'No fue posible completar la operación solicitada.';
            break;
    }

    $tipoMensaje = 'error';
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

    <title>Inventario | SIGPET</title>

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

</head>

<body class="dashboard-body">

<div class="dashboard-layout">

    <?php

    $rutaBase = '../';
    $paginaActiva = 'inventario';

    require __DIR__ . '/../includes/sidebar.php';

    ?>

    <main class="dashboard-main">

        <header class="dashboard-header">

            <div>

                <span class="header-subtitle">
                    CONTROL DE EQUIPOS TECNOLÓGICOS
                </span>

                <h1>
                    Inventario de equipos
                </h1>

            </div>

            <div class="header-user">

                <div class="header-user-text">

                    <strong>
                        <?= htmlspecialchars(
                            $nombre . ' ' . $apellido
                        ) ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars($rol) ?>
                    </span>

                </div>

                <div class="user-avatar header-avatar">

                    <?= htmlspecialchars(
                        strtoupper(
                            substr($nombre, 0, 1)
                        )
                    ) ?>

                </div>

            </div>

        </header>


        <section class="users-intro">

            <h2>
                Equipos registrados
            </h2>

            <p>
                Consulta las características, clasificación y estado actual de los equipos tecnológicos.
            </p>

        </section>


        <?php if ($mensaje !== ''): ?>

            <div class="form-message <?= $tipoMensaje ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>

        <?php endif; ?>


        <section class="inventory-summary">

            <div class="inventory-summary-card">

                <span>
                    Equipos encontrados
                </span>

                <strong>
                    <?= $totalEquipos ?>
                </strong>

            </div>

        </section>


        <section class="inventory-container">

            <div class="inventory-toolbar">

                <div>

                    <h3>
                        Listado de equipos
                    </h3>

                    <p>
                        Usa la búsqueda y los filtros para localizar equipos.
                    </p>

                </div>


                <?php if (tienePermiso('inventario.registrar')): ?>

                    <a
                        href="registrar.php"
                        class="primary-link"
                    >
                        + Registrar equipo
                    </a>

                <?php endif; ?>


                <?php if (tienePermiso('prestamos.solicitar')): ?>

                    <a
                        href="../prestamos/solicitar.php"
                        class="primary-link"
                    >
                        + Solicitar préstamo
                    </a>

                <?php endif; ?>

            </div>


            <form
                method="GET"
                action="listar.php"
                class="inventory-filters"
            >

                <div class="filter-field filter-search">

                    <label for="buscar">
                        Buscar
                    </label>

                    <input
                        id="buscar"
                        name="buscar"
                        type="search"
                        placeholder="Código, nombre, marca, modelo..."
                        value="<?= htmlspecialchars($busqueda) ?>"
                    >

                </div>


                <div class="filter-field">

                    <label for="tipo">
                        Tipo de equipo
                    </label>

                    <select
                        name="tipo"
                        id="tipo"
                    >

                        <option value="">
                            Todos los tipos
                        </option>

                        <?php foreach ($tipos as $tipo): ?>

                            <option
                                value="<?= (int)$tipo['id_tipo'] ?>"
                                <?= (int)$idTipo === (int)$tipo['id_tipo']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= htmlspecialchars(
                                    $tipo['nombre_tipo']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="filter-field">

                    <label for="estado">
                        Estado
                    </label>

                    <select
                        name="estado"
                        id="estado"
                    >

                        <option value="">
                            Todos los estados
                        </option>

                        <?php foreach ($estados as $estado): ?>

                            <option
                                value="<?= (int)$estado['id_estado'] ?>"
                                <?= (int)$idEstado === (int)$estado['id_estado']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= htmlspecialchars(
                                    $estado['nombre_estado']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <label class="available-check">

                    <input
                        type="checkbox"
                        name="disponibles"
                        value="1"
                        <?= $soloDisponibles ? 'checked' : '' ?>
                    >

                    Solo disponibles

                </label>


                <div class="filter-actions">

                    <button
                        class="filter-button"
                        type="submit"
                    >
                        Aplicar filtros
                    </button>

                    <a
                        href="listar.php"
                        class="filter-clear"
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
                                Código
                            </th>

                            <th>
                                Equipo
                            </th>

                            <th>
                                Tipo
                            </th>

                            <th>
                                Marca / modelo
                            </th>

                            <th>
                                Ubicación
                            </th>

                            <th>
                                Estado
                            </th>

                            <?php if ($puedeAdministrar): ?>

                                <th>
                                    Acciones
                                </th>

                            <?php endif; ?>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if ($resultado->num_rows > 0): ?>

                        <?php while ($equipo = $resultado->fetch_assoc()): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $equipo['codigo_inventario']
                                        ) ?>
                                    </strong>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $equipo['nombre']
                                    ) ?>

                                    <?php if (!empty($equipo['numero_serie'])): ?>

                                        <small class="table-subtext">

                                            Serie:
                                            <?= htmlspecialchars(
                                                $equipo['numero_serie']
                                            ) ?>

                                        </small>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $equipo['nombre_tipo']
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        trim(
                                            ($equipo['marca'] ?? '') .
                                            ' ' .
                                            ($equipo['modelo'] ?? '')
                                        ) ?: 'No especificado'
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $equipo['ubicacion']
                                        ?? 'No especificada'
                                    ) ?>

                                </td>


                                <td>

                                    <span
                                        class="equipment-status status-<?= (int)$equipo['id_estado'] ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $equipo['nombre_estado']
                                        ) ?>

                                    </span>

                                </td>


                                <?php if ($puedeAdministrar): ?>

                                    <td>

                                        <div class="action-buttons">


                                            <?php if (tienePermiso('inventario.editar')): ?>

                                                <a
                                                    class="action-button edit-action"
                                                    href="editar.php?id=<?= (int)$equipo['id_equipo'] ?>"
                                                >
                                                    Editar
                                                </a>

                                            <?php endif; ?>


                                            <?php if (tienePermiso('inventario.dar_baja')): ?>


                                                <?php if ((int)$equipo['id_estado'] === 1): ?>

                                                    <form
                                                        method="POST"
                                                        action="cambiar_estado.php"
                                                        onsubmit="return confirm('¿Deseas marcar este equipo como En mantenimiento?');"
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
                                                            name="id_equipo"
                                                            value="<?= (int)$equipo['id_equipo'] ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="accion"
                                                            value="mantenimiento"
                                                        >

                                                        <button
                                                            class="action-button edit-action"
                                                            type="submit"
                                                        >
                                                            Mantenimiento
                                                        </button>

                                                    </form>


                                                <?php elseif ((int)$equipo['id_estado'] === 4): ?>

                                                    <form
                                                        method="POST"
                                                        action="cambiar_estado.php"
                                                        onsubmit="return confirm('¿Deseas marcar este equipo nuevamente como Disponible?');"
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
                                                            name="id_equipo"
                                                            value="<?= (int)$equipo['id_equipo'] ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="accion"
                                                            value="disponible"
                                                        >

                                                        <button
                                                            class="action-button activate-action"
                                                            type="submit"
                                                        >
                                                            Marcar disponible
                                                        </button>

                                                    </form>

                                                <?php endif; ?>


                                                <?php if (
                                                    (int)$equipo['id_estado'] !== 5
                                                ): ?>

                                                    <form
                                                        method="POST"
                                                        action="cambiar_estado.php"
                                                        onsubmit="return confirm('¿Deseas dar de baja lógica este equipo? El registro se conservará.');"
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
                                                            name="id_equipo"
                                                            value="<?= (int)$equipo['id_equipo'] ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="accion"
                                                            value="baja"
                                                        >

                                                        <button
                                                            class="action-button deactivate-action"
                                                            type="submit"
                                                        >
                                                            Dar de baja
                                                        </button>

                                                    </form>


                                                <?php else: ?>

                                                    <form
                                                        method="POST"
                                                        action="cambiar_estado.php"
                                                        onsubmit="return confirm('¿Deseas reactivar este equipo y marcarlo como Disponible?');"
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
                                                            name="id_equipo"
                                                            value="<?= (int)$equipo['id_equipo'] ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="accion"
                                                            value="reactivar"
                                                        >

                                                        <button
                                                            class="action-button activate-action"
                                                            type="submit"
                                                        >
                                                            Reactivar
                                                        </button>

                                                    </form>

                                                <?php endif; ?>


                                            <?php endif; ?>


                                            <?php if (
                                                tienePermiso('prestamos.solicitar') &&
                                                $equipo['nombre_estado'] === 'Disponible'
                                            ): ?>

                                                <a
                                                    class="action-button edit-action"
                                                    href="../prestamos/solicitar.php?id_equipo=<?= (int)$equipo['id_equipo'] ?>"
                                                >
                                                    Solicitar
                                                </a>

                                            <?php endif; ?>


                                        </div>

                                    </td>

                                <?php endif; ?>


                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="<?= $puedeAdministrar ? 7 : 6 ?>"
                                class="empty-table"
                            >

                                <?= (
                                    $busqueda !== '' ||
                                    $idTipo ||
                                    $idEstado ||
                                    $soloDisponibles
                                )
                                    ? 'No se encontraron equipos con los filtros seleccionados.'
                                    : 'Todavía no hay equipos registrados.'
                                ?>

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