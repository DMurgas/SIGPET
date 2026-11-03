<?php
$nombre = $_SESSION['nombres'] ?? '';
$apellido = $_SESSION['apellidos'] ?? '';
$rol = $_SESSION['rol'] ?? '';
$inicial = strtoupper(substr($nombre, 0, 1));
?>
<aside class="sidebar">
    <div class="sidebar-brand"><div class="sidebar-logo">S</div><span>SIGPET</span></div>
    <div class="sidebar-section">
        <span class="sidebar-label">MENÚ PRINCIPAL</span>
        <a href="<?= htmlspecialchars($rutaBase) ?>dashboard.php" class="sidebar-link <?= ($paginaActiva ?? '') === 'dashboard' ? 'active' : '' ?>"><span>⌂</span>Panel principal</a>
        <?php if (tienePermiso('usuarios.consultar')): ?>
            <a href="<?= htmlspecialchars($rutaBase) ?>usuarios/listar.php" class="sidebar-link <?= ($paginaActiva ?? '') === 'usuarios' ? 'active' : '' ?>"><span>♙</span>Gestión de usuarios</a>
        <?php endif; ?>
        <?php if (tienePermiso('inventario.consultar')): ?>
            <a href="<?= htmlspecialchars($rutaBase) ?>inventario/listar.php" class="sidebar-link <?= ($paginaActiva ?? '') === 'inventario' ? 'active' : '' ?>"><span>▣</span>Inventario de equipos</a>
        <?php endif; ?>
    </div>
    <div class="sidebar-bottom">
        <div class="sidebar-user">
            <div class="user-avatar"><?= htmlspecialchars($inicial) ?></div>
            <div class="sidebar-user-info"><strong><?= htmlspecialchars($nombre . ' ' . $apellido) ?></strong><span><?= htmlspecialchars($rol) ?></span></div>
        </div>
        <a href="<?= htmlspecialchars($rutaBase) ?>logout.php" class="sidebar-logout">Cerrar sesión</a>
    </div>
</aside>
