<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('prestamos.gestionar');

require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';

$idPrestamo = filter_var(
    $_GET['id'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$idPrestamo) {
    header('Location: listar.php?estado=Aprobada&error=prestamo');
    exit();
}

/*
 * Consultar el préstamo y verificar:
 * 1. Que exista.
 * 2. Que esté actualmente prestado.
 * 3. Que el equipo corresponda al préstamo.
 * 4. Que podamos mostrar toda la información necesaria
 *    para registrar la devolución.
 */
$stmt = $conexion->prepare(
    "SELECT
        p.id_prestamo,
        p.id_solicitud,
        p.id_equipo,
        p.id_usuario,
        p.fecha_entrega,
        p.entregado_por,
        p.autorizado_por,
        p.condicion_inicial,
        p.estado_prestamo,

        s.fecha_inicio,
        s.fecha_devolucion_prevista,
        s.proposito,

        u.nombres,
        u.apellidos,
        u.codigo_institucional,

        e.codigo_inventario,
        e.nombre AS nombre_equipo,
        e.marca,
        e.modelo,

        es.nombre_estado

     FROM prestamos p

     INNER JOIN solicitudes_prestamo s
        ON s.id_solicitud = p.id_solicitud

     INNER JOIN usuarios u
        ON u.id_usuario = p.id_usuario

     INNER JOIN equipos e
        ON e.id_equipo = p.id_equipo

     INNER JOIN estados_equipo es
        ON es.id_estado = e.id_estado

     WHERE p.id_prestamo = ?
     LIMIT 1"
);

$stmt->bind_param('i', $idPrestamo);
$stmt->execute();

$prestamo = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$prestamo) {
    header('Location: listar.php?estado=Aprobada&error=prestamo');
    exit();
}

/*
 * Solo se puede registrar la devolución de un préstamo
 * que actualmente esté en estado Prestado.
 */
if ($prestamo['estado_prestamo'] !== 'Prestado') {
    header('Location: listar.php?estado=Aprobada&error=estado_prestamo');
    exit();
}

/*
 * El equipo también debe encontrarse en estado Prestado.
 */
if ($prestamo['nombre_estado'] !== 'Prestado') {
    header('Location: listar.php?estado=Aprobada&error=estado_equipo');
    exit();
}

$tokenCSRF = obtenerTokenCSRF();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Registrar devolución | SIGPET</title>

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
                    Registrar devolución
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
                        <?= htmlspecialchars($rolSesion) ?>
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
                Devolución del equipo
            </h2>

            <p>
                Registra la devolución física del equipo
                correspondiente al préstamo seleccionado.
            </p>

        </section>


        <section class="inventory-container">

            <div class="inventory-toolbar">

                <div>

                    <h3>
                        Información del préstamo
                    </h3>

                    <p>
                        Verifica los datos antes de registrar
                        la devolución.
                    </p>

                </div>

            </div>


            <div class="loan-form-container">

                <div class="loan-summary">

                    <p>
                        <strong>Préstamo:</strong>
                        #<?= (int)$prestamo['id_prestamo'] ?>
                    </p>

                    <p>
                        <strong>Solicitud:</strong>
                        #<?= (int)$prestamo['id_solicitud'] ?>
                    </p>

                    <p>
                        <strong>Solicitante:</strong>

                        <?= htmlspecialchars(
                            $prestamo['nombres'] . ' ' .
                            $prestamo['apellidos']
                        ) ?>

                        <?php if (!empty($prestamo['codigo_institucional'])): ?>

                            <small class="table-subtext">
                                <?= htmlspecialchars(
                                    $prestamo['codigo_institucional']
                                ) ?>
                            </small>

                        <?php endif; ?>

                    </p>


                    <p>
                        <strong>Equipo:</strong>

                        <?= htmlspecialchars(
                            $prestamo['codigo_inventario'] .
                            ' — ' .
                            $prestamo['nombre_equipo']
                        ) ?>
                    </p>


                    <p>
                        <strong>Marca / modelo:</strong>

                        <?= htmlspecialchars(
                            trim(
                                ($prestamo['marca'] ?? '') .
                                ' ' .
                                ($prestamo['modelo'] ?? '')
                            )
                        ) ?>
                    </p>


                    <p>
                        <strong>Fecha de entrega:</strong>

                        <?= htmlspecialchars(
                            $prestamo['fecha_entrega']
                        ) ?>
                    </p>


                    <p>
                        <strong>Devolución prevista:</strong>

                        <?= htmlspecialchars(
                            $prestamo['fecha_devolucion_prevista']
                        ) ?>
                    </p>


                    <p>
                        <strong>Propósito:</strong>

                        <?= htmlspecialchars(
                            $prestamo['proposito']
                        ) ?>
                    </p>


                    <p>
                        <strong>Condición inicial:</strong>

                        <?= htmlspecialchars(
                            $prestamo['condicion_inicial']
                        ) ?>
                    </p>


                    <p>
                        <strong>Estado actual del equipo:</strong>

                        <span class="loan-status status-prestado">
                            Prestado
                        </span>

                    </p>

                </div>


                <form
                    method="POST"
                    action="registrar_devolucion.php"
                    class="loan-form"
                >

                    <input
                        type="hidden"
                        name="token_csrf"
                        value="<?= htmlspecialchars($tokenCSRF) ?>"
                    >

                    <input
                        type="hidden"
                        name="id_prestamo"
                        value="<?= (int)$prestamo['id_prestamo'] ?>"
                    >


                    <div class="form-group">

                        <label for="condicion_final">
                            Condición física final *
                        </label>

                        <textarea
                            id="condicion_final"
                            name="condicion_final"
                            rows="4"
                            maxlength="500"
                            required
                            placeholder="Ejemplo: Equipo devuelto en buen estado, sin daños visibles. Pantalla, teclado, cargador y carcasa en condiciones adecuadas."
                        ></textarea>

                        <small>
                            Describe el estado físico del equipo
                            al momento de la devolución.
                        </small>

                    </div>


                    <div class="form-group">

                        <label for="observaciones">
                            Observaciones
                        </label>

                        <textarea
                            id="observaciones"
                            name="observaciones"
                            rows="4"
                            maxlength="1000"
                            placeholder="Registra cualquier observación relacionada con la devolución, daños, accesorios faltantes u otra situación relevante."
                        ></textarea>

                        <small>
                            Este campo es opcional.
                        </small>

                    </div>


                    <div class="form-group">

                        <label>
                            Responsable de registrar la devolución
                        </label>

                        <input
                            type="text"
                            value="<?= htmlspecialchars(
                                $nombreSesion . ' ' .
                                $apellidoSesion
                            ) ?>"
                            readonly
                        >

                        <small>
                            Usuario que registra la devolución
                            en el sistema.
                        </small>

                    </div>


                    <div class="action-buttons">

                        <a
                            class="action-button"
                            href="listar.php?estado=Aprobada"
                        >
                            Cancelar
                        </a>

                        <button
                            class="action-button approve-action"
                            type="submit"
                            onclick="return confirm('¿Confirmas que deseas registrar la devolución de este equipo?');"
                        >
                            Registrar devolución
                        </button>

                    </div>

                </form>

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
$conexion->close();
?>