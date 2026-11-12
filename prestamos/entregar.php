<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('prestamos.gestionar');

require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

$nombreSesion = $_SESSION['nombres'] ?? '';
$apellidoSesion = $_SESSION['apellidos'] ?? '';
$rolSesion = $_SESSION['rol'] ?? '';

$idSolicitud = filter_var(
    $_GET['id'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$idSolicitud) {
    header('Location: listar.php?estado=Aprobada&error=solicitud');
    exit();
}

$stmt = $conexion->prepare(
    "SELECT
        s.id_solicitud,
        s.id_usuario,
        s.id_equipo,
        s.fecha_inicio,
        s.fecha_devolucion_prevista,
        s.proposito,
        s.estado_solicitud,

        u.nombres,
        u.apellidos,
        u.codigo_institucional,

        e.codigo_inventario,
        e.nombre AS nombre_equipo,
        e.marca,
        e.modelo,

        es.id_estado,
        es.nombre_estado

     FROM solicitudes_prestamo s
     INNER JOIN usuarios u
        ON u.id_usuario = s.id_usuario
     INNER JOIN equipos e
        ON e.id_equipo = s.id_equipo
     INNER JOIN estados_equipo es
        ON es.id_estado = e.id_estado
     WHERE s.id_solicitud = ?
     LIMIT 1"
);

$stmt->bind_param('i', $idSolicitud);
$stmt->execute();

$solicitud = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$solicitud) {
    header('Location: listar.php?estado=Aprobada&error=solicitud');
    exit();
}

if ($solicitud['estado_solicitud'] !== 'Aprobada') {
    header('Location: listar.php?estado=Aprobada&error=estado');
    exit();
}

if ($solicitud['nombre_estado'] !== 'Reservado') {
    header('Location: listar.php?estado=Aprobada&error=equipo');
    exit();
}

$stmt = $conexion->prepare(
    "SELECT id_prestamo
     FROM prestamos
     WHERE id_solicitud = ?
     LIMIT 1"
);

$stmt->bind_param('i', $idSolicitud);
$stmt->execute();

$prestamoExistente = $stmt->get_result()->fetch_assoc();

$stmt->close();

if ($prestamoExistente) {
    header(
        'Location: listar.php?estado=Aprobada&error=ya_entregado'
    );
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

    <title>Registrar entrega | SIGPET</title>

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
                    Registrar entrega
                </h1>

            </div>

            <div class="header-user">

                <div class="header-user-text">

                    <strong>
                        <?= htmlspecialchars(
                            $nombreSesion . ' ' . $apellidoSesion,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars(
                            $rolSesion,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                </div>

                <div class="user-avatar header-avatar">

                    <?= htmlspecialchars(
                        strtoupper(substr($nombreSesion, 0, 1)),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            </div>

        </header>


        <section class="users-intro">

            <h2>
                Entrega del equipo
            </h2>

            <p>
                Registra la entrega física del equipo correspondiente
                a la solicitud aprobada.
            </p>

        </section>


        <section class="inventory-container">

            <div class="inventory-toolbar">

                <div>

                    <h3>
                        Información de la solicitud
                    </h3>

                    <p>
                        Verifica los datos antes de registrar la entrega.
                    </p>

                </div>

            </div>


            <div class="loan-form-container">

                <div class="loan-summary">

                    <p>
                        <strong>Solicitud:</strong>
                        #<?= (int)$solicitud['id_solicitud'] ?>
                    </p>


                    <p>
                        <strong>Solicitante:</strong>

                        <?= htmlspecialchars(
                            $solicitud['nombres'] . ' ' .
                            $solicitud['apellidos'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                        <?php if (!empty($solicitud['codigo_institucional'])): ?>

                            <small class="table-subtext">

                                <?= htmlspecialchars(
                                    $solicitud['codigo_institucional'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </small>

                        <?php endif; ?>

                    </p>


                    <p>
                        <strong>Equipo:</strong>

                        <?= htmlspecialchars(
                            $solicitud['codigo_inventario'] .
                            ' — ' .
                            $solicitud['nombre_equipo'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>


                    <p>
                        <strong>Marca / modelo:</strong>

                        <?= htmlspecialchars(
                            trim(
                                ($solicitud['marca'] ?? '') .
                                ' ' .
                                ($solicitud['modelo'] ?? '')
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>


                    <p>
                        <strong>Período:</strong>

                        <?= htmlspecialchars(
                            $solicitud['fecha_inicio'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                        hasta

                        <?= htmlspecialchars(
                            $solicitud['fecha_devolucion_prevista'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>


                    <p>
                        <strong>Propósito:</strong>

                        <?= htmlspecialchars(
                            $solicitud['proposito'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>


                    <p>
                        <strong>Estado del equipo:</strong>

                        <span class="loan-status status-reservado">
                            Reservado
                        </span>

                    </p>

                </div>


                <form
                    method="POST"
                    action="registrar_entrega.php"
                    class="loan-form"
                >

                    <input
                        type="hidden"
                        name="token_csrf"
                        value="<?= htmlspecialchars(
                            $tokenCSRF,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="id_solicitud"
                        value="<?= (int)$solicitud['id_solicitud'] ?>"
                    >


                    <div class="form-group">

                        <label for="condicion_inicial">
                            Condición física inicial *
                        </label>

                        <textarea
                            id="condicion_inicial"
                            name="condicion_inicial"
                            rows="4"
                            maxlength="500"
                            required
                            placeholder="Ejemplo: Equipo en buen estado, pantalla, teclado, cargador y carcasa sin daños visibles."
                        ></textarea>

                        <small>
                            Describe el estado físico del equipo
                            al momento de la entrega.
                        </small>

                    </div>


                    <div class="form-group">

                        <label for="responsable_entrega">
                            Responsable de la entrega
                        </label>

                        <input
                            id="responsable_entrega"
                            type="text"
                            value="<?= htmlspecialchars(
                                $nombreSesion . ' ' . $apellidoSesion,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            readonly
                        >

                        <small>
                            Usuario que registra la entrega.
                        </small>

                    </div>


                    <div class="form-group">

                        <label for="autorizado_por">
                            Autorizado por
                        </label>

                        <input
                            id="autorizado_por"
                            type="text"
                            value="<?= htmlspecialchars(
                                $nombreSesion . ' ' . $apellidoSesion,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            readonly
                        >

                        <small>
                            Usuario responsable que autoriza
                            la entrega en el sistema.
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
                            onclick="return confirm('¿Confirmas que deseas registrar la entrega de este equipo?');"
                        >
                            Registrar entrega
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