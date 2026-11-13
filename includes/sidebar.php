<?php

$nombre = $_SESSION['nombres'] ?? '';
$apellido = $_SESSION['apellidos'] ?? '';
$rol = $_SESSION['rol'] ?? '';

$inicial = strtoupper(
    substr($nombre, 0, 1)
);

?>

<aside class="sidebar">

    <div class="sidebar-brand">

        <div class="sidebar-logo">
            S
        </div>

        <span>
            SIGPET
        </span>

    </div>


    <div class="sidebar-section">

        <span class="sidebar-label">
            MENÚ PRINCIPAL
        </span>


        <!-- PANEL PRINCIPAL -->

        <a
            href="<?= htmlspecialchars($rutaBase) ?>dashboard.php"
            class="sidebar-link <?= ($paginaActiva ?? '') === 'dashboard' ? 'active' : '' ?>"
        >
            <span>⌂</span>
            Panel principal
        </a>


        <!-- GESTIÓN DE USUARIOS -->

        <?php if (tienePermiso('usuarios.consultar')): ?>

            <a
                href="<?= htmlspecialchars($rutaBase) ?>usuarios/listar.php"
                class="sidebar-link <?= ($paginaActiva ?? '') === 'usuarios' ? 'active' : '' ?>"
            >
                <span>♙</span>
                Gestión de usuarios
            </a>

        <?php endif; ?>


        <!-- INVENTARIO -->

        <?php if (tienePermiso('inventario.consultar')): ?>

            <a
                href="<?= htmlspecialchars($rutaBase) ?>inventario/listar.php"
                class="sidebar-link <?= ($paginaActiva ?? '') === 'inventario' ? 'active' : '' ?>"
            >
                <span>▣</span>
                Inventario de equipos
            </a>

        <?php endif; ?>


        <!-- MIS SOLICITUDES -->

        <?php if (tienePermiso('prestamos.solicitar')): ?>

            <a
                href="<?= htmlspecialchars($rutaBase) ?>prestamos/mis_solicitudes.php"
                class="sidebar-link <?= ($paginaActiva ?? '') === 'mis_solicitudes' ? 'active' : '' ?>"
            >
                <span>▤</span>
                Mis solicitudes
            </a>

        <?php endif; ?>


        <!-- GESTIONAR PRÉSTAMOS -->

        <?php if (tienePermiso('prestamos.gestionar')): ?>

            <a
                href="<?= htmlspecialchars($rutaBase) ?>prestamos/listar.php?estado=Pendiente"
                class="sidebar-link <?= ($paginaActiva ?? '') === 'prestamos' ? 'active' : '' ?>"
            >
                <span>✓</span>
                Gestionar préstamos
            </a>

        <?php endif; ?>


        <!-- HISTORIAL DE PRÉSTAMOS -->

        <?php if (tienePermiso('prestamos.historial')): ?>

            <a
                href="<?= htmlspecialchars($rutaBase) ?>prestamos/historial.php"
                class="sidebar-link <?= ($paginaActiva ?? '') === 'historial' ? 'active' : '' ?>"
            >
                <span>▤</span>
                Historial de préstamos
            </a>

        <?php endif; ?>

    </div>


    <!-- USUARIO / CERRAR SESIÓN -->

    <div class="sidebar-bottom">

        <div class="sidebar-user">

            <div class="user-avatar">
                <?= htmlspecialchars($inicial) ?>
            </div>

            <div class="sidebar-user-info">

                <strong>
                    <?= htmlspecialchars(
                        $nombre . ' ' . $apellido
                    ) ?>
                </strong>

                <span>
                    <?= htmlspecialchars($rol) ?>
                </span>

            </div>

        </div>


        <a
            href="<?= htmlspecialchars($rutaBase) ?>logout.php"
            class="sidebar-logout"
        >
            Cerrar sesión
        </a>

    </div>

</aside>