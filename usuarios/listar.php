<?php
require_once __DIR__ . '/../includes/verificar_rol.php';
verificarPermiso('usuarios.consultar');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/conexion.php';

$nombre = $_SESSION['nombres'] ?? '';
$apellido = $_SESSION['apellidos'] ?? '';
$rol = $_SESSION['rol'] ?? '';
$idSesion = (int)$_SESSION['id_usuario'];
$busqueda = trim($_GET['buscar'] ?? '');

$sql = 'SELECT u.id_usuario, u.nombres, u.apellidos, u.correo_institucional, u.codigo_institucional, u.activo, u.fecha_registro, u.id_rol, r.nombre_rol FROM usuarios u INNER JOIN roles r ON u.id_rol = r.id_rol';
if ($busqueda !== '') {
    $sql .= ' WHERE u.nombres LIKE ? OR u.apellidos LIKE ? OR u.correo_institucional LIKE ? OR u.codigo_institucional LIKE ? OR r.nombre_rol LIKE ?';
}
$sql .= ' ORDER BY u.id_usuario DESC';
$stmt = $conexion->prepare($sql);
if ($busqueda !== '') {
    $filtro = '%' . $busqueda . '%';
    $stmt->bind_param('sssss', $filtro, $filtro, $filtro, $filtro, $filtro);
}
$stmt->execute();
$resultado = $stmt->get_result();
$todosUsuarios = $resultado->fetch_all(MYSQLI_ASSOC);
$totalUsuarios = count($todosUsuarios);
$porPagina = 8;
$totalPaginas = max(1, (int)ceil($totalUsuarios / $porPagina));
$paginaActual = max(1, min($totalPaginas, (int)($_GET['pagina'] ?? 1)));
$offset = ($paginaActual - 1) * $porPagina;
$usuariosPagina = array_slice($todosUsuarios, $offset, $porPagina);
$tokenCSRF = obtenerTokenCSRF();
function urlUsuariosPagina(int $pag): string {
    return 'listar.php?' . http_build_query(array_merge($_GET, ['pagina' => $pag]));
}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Gestión de usuarios | SIGPET</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"><link rel="stylesheet" href="../CSS/base.css"><link rel="stylesheet" href="../CSS/dashboard.css"><link rel="stylesheet" href="../CSS/usuarios.css"><link rel="stylesheet" href="../CSS/inventario.css">    <link rel="stylesheet" href="../CSS/theme-profesional.css">
</head>
<body class="dashboard-body"><div class="dashboard-layout">
<?php $rutaBase = '../'; $paginaActiva = 'usuarios'; require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="dashboard-main"><header class="dashboard-header"><div><span class="header-subtitle">ADMINISTRACIÓN DEL SISTEMA</span><h1>Gestión de usuarios</h1><p class="header-desc">Administra las cuentas registradas en SIGPET (<?= $totalUsuarios ?> encontrado<?= $totalUsuarios === 1 ? '' : 's' ?>).</p></div><?php if (tienePermiso('usuarios.registrar')): ?><div class="header-actions"><a href="registrar.php" class="add-user-button">+ Nuevo usuario</a></div><?php endif; ?></header>
<?php if (isset($_GET['registrado'])): ?><div class="form-message success">El usuario se registró correctamente.</div><?php elseif (isset($_GET['actualizado'])): ?><div class="form-message success">Los datos del usuario se actualizaron correctamente.</div><?php elseif (isset($_GET['estado'])): ?><div class="form-message success">El estado de la cuenta se actualizó correctamente.</div><?php elseif (isset($_GET['aviso'])): ?><div class="form-message error"><?= htmlspecialchars($_GET['aviso'] === 'ultimo_admin' ? 'No se puede desactivar al último administrador activo.' : 'No se puede desactivar la cuenta con la que has iniciado sesión.') ?></div><?php endif; ?>
<section class="users-container"><div class="users-toolbar"><div><h3>Listado de usuarios</h3><p>Busca por nombre, correo, código o rol.</p></div><div class="users-toolbar-actions">
<form method="GET" action="listar.php" class="users-search"><input type="text" name="buscar" placeholder="Buscar usuario..." value="<?= htmlspecialchars($busqueda) ?>"><button type="submit">Buscar</button><?php if ($busqueda !== ''): ?><a href="listar.php" class="clear-search">Limpiar</a><?php endif; ?></form>
</div></div>
<div class="table-responsive"><table class="users-table"><thead><tr><th>ID</th><th>Nombre completo</th><th>Correo institucional</th><th>Código institucional</th><th>Rol</th><th>Estado</th><th>Fecha de registro</th><th>Acciones</th></tr></thead><tbody>
<?php if (!empty($usuariosPagina)): foreach ($usuariosPagina as $usuario): ?>
<tr><td><?= (int)$usuario['id_usuario'] ?></td><td><strong><?= htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']) ?></strong></td><td><?= htmlspecialchars($usuario['correo_institucional']) ?></td><td><?= htmlspecialchars($usuario['codigo_institucional'] ?? 'No registrado') ?></td><td><span class="role-badge"><?= htmlspecialchars($usuario['nombre_rol']) ?></span></td><td><span class="status-badge <?= (int)$usuario['activo'] === 1 ? 'active' : 'inactive' ?>"><?= (int)$usuario['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td><td><?= htmlspecialchars($usuario['fecha_registro'] ?? '') ?></td><td><div class="action-buttons">
<?php if (tienePermiso('usuarios.editar')): ?><a class="action-button edit-action" href="editar.php?id=<?= (int)$usuario['id_usuario'] ?>">Editar</a><?php endif; ?>
<?php if (tienePermiso('usuarios.desactivar') && (int)$usuario['id_usuario'] !== $idSesion): ?><form method="POST" action="cambiar_estado.php" onsubmit="return confirm('¿Deseas <?= (int)$usuario['activo'] === 1 ? 'desactivar' : 'reactivar' ?> esta cuenta?');"><input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCSRF) ?>"><input type="hidden" name="id_usuario" value="<?= (int)$usuario['id_usuario'] ?>"><input type="hidden" name="accion" value="<?= (int)$usuario['activo'] === 1 ? 'desactivar' : 'reactivar' ?>"><button class="action-button <?= (int)$usuario['activo'] === 1 ? 'deactivate-action' : 'activate-action' ?>" type="submit"><?= (int)$usuario['activo'] === 1 ? 'Desactivar' : 'Reactivar' ?></button></form><?php endif; ?>
</div></td></tr>
<?php endforeach; else: ?><tr><td colspan="8" class="empty-table"><?= $busqueda !== '' ? 'No se encontraron usuarios que coincidan con la búsqueda.' : 'Todavía no hay usuarios registrados.' ?></td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($totalPaginas > 1): ?>
<div class="sigpet-pagination"><span>Mostrando <?= $offset + 1 ?>–<?= min($offset + $porPagina, $totalUsuarios) ?> de <?= $totalUsuarios ?></span><div class="sigpet-page-links"><a class="sigpet-page-link <?= $paginaActual <= 1 ? 'disabled' : '' ?>" href="<?= htmlspecialchars(urlUsuariosPagina(max(1, $paginaActual - 1))) ?>">Anterior</a><?php for ($p = 1; $p <= $totalPaginas; $p++): ?><a class="sigpet-page-link <?= $p === $paginaActual ? 'active' : '' ?>" href="<?= htmlspecialchars(urlUsuariosPagina($p)) ?>"><?= $p ?></a><?php endfor; ?><a class="sigpet-page-link <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>" href="<?= htmlspecialchars(urlUsuariosPagina(min($totalPaginas, $paginaActual + 1))) ?>">Siguiente</a></div></div>
<?php endif; ?>
</section>
<footer class="dashboard-footer">SIGPET © 2026 | Sistema de Gestión de Préstamos de Equipos Tecnológicos</footer></main></div></body></html>
<?php $stmt->close(); $conexion->close(); ?>
