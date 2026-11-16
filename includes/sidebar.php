<?php

require_once __DIR__ . '/iconos.php';

$nombre = $_SESSION['nombres'] ?? '';
$apellido = $_SESSION['apellidos'] ?? '';
$rol = $_SESSION['rol'] ?? '';
$idUsuarioMenu = (int)($_SESSION['id_usuario'] ?? 0);

$inicial = strtoupper(
    substr($nombre, 0, 1)
);

$totalAlertasBadge = 0;
if (
    $idUsuarioMenu > 0 &&
    tienePermiso('alertas.consultar') &&
    isset($conexion) &&
    $conexion instanceof mysqli &&
    $conexion->connect_errno === 0
) {
    require_once __DIR__ . '/alertas.php';
    $totalAlertasBadge = contarAlertasNoLeidasUsuario($conexion, $idUsuarioMenu);
}

?>

<div class="sidebar-shell" id="sigpetSidebarShell">
    <button
        type="button"
        class="sidebar-toggle"
        id="sigpetSidebarToggle"
        title="Contraer o expandir menú"
        aria-label="Contraer o expandir menú"
    >
        <?= sigpetIcono('chevron_left', 16) ?>
    </button>

    <aside class="sidebar" id="sigpetSidebar">

        <div class="sidebar-top">
            <div class="sidebar-brand">
                <div class="sidebar-logo">S</div>
                <span class="sidebar-brand-text">SIGPET</span>
            </div>
        </div>

        <script>
        (function () {
            var shell = document.getElementById('sigpetSidebarShell');
            var btn = document.getElementById('sigpetSidebarToggle');
            if (!shell) return;
            try {
                if (localStorage.getItem('sigpet_sidebar_collapsed') === '1') {
                    shell.classList.add('collapsed');
                    if (btn) btn.setAttribute('data-collapsed', '1');
                }
            } catch (e) {}
        })();
        </script>

        <div class="sidebar-section">

            <span class="sidebar-label">MENÚ PRINCIPAL</span>

            <a
                href="<?= htmlspecialchars($rutaBase) ?>dashboard.php"
                title="Panel principal"
                class="sidebar-link <?= ($paginaActiva ?? '') === 'dashboard' ? 'active' : '' ?>"
            >
                <span class="sidebar-link-icon"><?= sigpetIcono('dashboard', 19) ?></span>
                <span class="sidebar-link-text">Panel principal</span>
            </a>

            <?php if (tienePermiso('usuarios.consultar')): ?>
                <a
                    href="<?= htmlspecialchars($rutaBase) ?>usuarios/listar.php"
                    title="Gestión de usuarios"
                    class="sidebar-link <?= ($paginaActiva ?? '') === 'usuarios' ? 'active' : '' ?>"
                >
                    <span class="sidebar-link-icon"><?= sigpetIcono('usuarios', 19) ?></span>
                    <span class="sidebar-link-text">Gestión de usuarios</span>
                </a>
            <?php endif; ?>

            <?php if (tienePermiso('inventario.consultar')): ?>
                <a
                    href="<?= htmlspecialchars($rutaBase) ?>inventario/listar.php"
                    title="Inventario de equipos"
                    class="sidebar-link <?= ($paginaActiva ?? '') === 'inventario' ? 'active' : '' ?>"
                >
                    <span class="sidebar-link-icon"><?= sigpetIcono('inventario', 19) ?></span>
                    <span class="sidebar-link-text">Inventario de equipos</span>
                </a>
            <?php endif; ?>

            <?php if (tienePermiso('prestamos.solicitar')): ?>
                <a
                    href="<?= htmlspecialchars($rutaBase) ?>prestamos/mis_solicitudes.php"
                    title="Mis solicitudes"
                    class="sidebar-link <?= ($paginaActiva ?? '') === 'mis_solicitudes' ? 'active' : '' ?>"
                >
                    <span class="sidebar-link-icon"><?= sigpetIcono('mis_solicitudes', 19) ?></span>
                    <span class="sidebar-link-text">Mis solicitudes</span>
                </a>
            <?php endif; ?>

            <?php if (tienePermiso('prestamos.gestionar')): ?>
                <a
                    href="<?= htmlspecialchars($rutaBase) ?>prestamos/listar.php?estado=Pendiente"
                    title="Gestionar préstamos"
                    class="sidebar-link <?= ($paginaActiva ?? '') === 'prestamos' ? 'active' : '' ?>"
                >
                    <span class="sidebar-link-icon"><?= sigpetIcono('prestamos', 19) ?></span>
                    <span class="sidebar-link-text">Gestionar préstamos</span>
                </a>
            <?php endif; ?>

            <?php if (tienePermiso('prestamos.historial')): ?>
                <a
                    href="<?= htmlspecialchars($rutaBase) ?>prestamos/historial.php"
                    title="Historial de préstamos"
                    class="sidebar-link <?= ($paginaActiva ?? '') === 'historial' ? 'active' : '' ?>"
                >
                    <span class="sidebar-link-icon"><?= sigpetIcono('historial', 19) ?></span>
                    <span class="sidebar-link-text">Historial de préstamos</span>
                </a>
            <?php endif; ?>

            <?php if (tienePermiso('reservas.consultar')): ?>
                <a
                    href="<?= htmlspecialchars($rutaBase) ?>prestamos/reservas.php"
                    title="Reservas y disponibilidad"
                    class="sidebar-link <?= ($paginaActiva ?? '') === 'reservas' ? 'active' : '' ?>"
                >
                    <span class="sidebar-link-icon"><?= sigpetIcono('reservas', 19) ?></span>
                    <span class="sidebar-link-text">Reservas y disponibilidad</span>
                </a>
            <?php endif; ?>

            <?php if (tienePermiso('alertas.consultar')): ?>
                <a
                    href="<?= htmlspecialchars($rutaBase) ?>prestamos/alertas.php"
                    title="Alertas"
                    class="sidebar-link <?= ($paginaActiva ?? '') === 'alertas' ? 'active' : '' ?>"
                >
                    <span class="sidebar-link-icon"><?= sigpetIcono('alertas', 19) ?></span>
                    <span class="sidebar-link-text">Alertas</span>
                    <?php if ($totalAlertasBadge > 0): ?>
                        <span class="sidebar-badge"><?= (int)$totalAlertasBadge ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <?php if (tienePermiso('reportes.consultar')): ?>
                <a
                    href="<?= htmlspecialchars($rutaBase) ?>reportes/index.php"
                    title="Reportes"
                    class="sidebar-link <?= ($paginaActiva ?? '') === 'reportes' ? 'active' : '' ?>"
                >
                    <span class="sidebar-link-icon"><?= sigpetIcono('reportes', 19) ?></span>
                    <span class="sidebar-link-text">Reportes</span>
                </a>
            <?php endif; ?>

            <?php if (tienePermiso('bitacora.consultar')): ?>
                <a
                    href="<?= htmlspecialchars($rutaBase) ?>bitacora/listar.php"
                    title="Bitácora"
                    class="sidebar-link <?= ($paginaActiva ?? '') === 'bitacora' ? 'active' : '' ?>"
                >
                    <span class="sidebar-link-icon"><?= sigpetIcono('bitacora', 19) ?></span>
                    <span class="sidebar-link-text">Bitácora</span>
                </a>
            <?php endif; ?>

        </div>

        <div class="sidebar-bottom">

            <div class="sidebar-user" title="<?= htmlspecialchars(trim($nombre . ' ' . $apellido) . ' — ' . $rol) ?>">
                <div class="user-avatar">
                    <?= htmlspecialchars($inicial) ?>
                </div>
                <div class="sidebar-user-info">
                    <strong><?= htmlspecialchars(trim($nombre . ' ' . $apellido)) ?></strong>
                    <span><?= htmlspecialchars($rol) ?></span>
                </div>
            </div>

            <a
                href="<?= htmlspecialchars($rutaBase) ?>logout.php"
                title="Cerrar sesión"
                class="sidebar-logout"
            >
                <span class="sidebar-logout-icon"><?= sigpetIcono('logout', 18) ?></span>
                <span class="sidebar-logout-text">Cerrar sesión</span>
            </a>

        </div>

    </aside>
</div>

<script>
(function () {
    if (!window.__sigpetSidebarInit) {
        window.__sigpetSidebarInit = true;
        document.getElementById('sigpetSidebarToggle')?.addEventListener('click', function () {
            var shell = document.getElementById('sigpetSidebarShell');
            if (!shell) return;
            var collapsed = shell.classList.toggle('collapsed');
            if (collapsed) {
                this.setAttribute('data-collapsed', '1');
            } else {
                this.removeAttribute('data-collapsed');
            }
            try {
                localStorage.setItem('sigpet_sidebar_collapsed', collapsed ? '1' : '0');
            } catch (e) {}
        });
    }
})();
</script>
<script src="<?= htmlspecialchars($rutaBase) ?>JS/sigpet-spa.js"></script>
