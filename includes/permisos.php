<?php
$permisos = [
    "Administrador" => [
        "usuarios.consultar", "usuarios.registrar", "usuarios.editar",
        "usuarios.desactivar", "usuarios.asignar_roles",
        "inventario.consultar", "inventario.registrar", "inventario.editar",
        "inventario.dar_baja", "inventario.consultar_estado",
        "inventario.buscar_disponibles"
    ],
    "Encargado de préstamos" => [
        "inventario.consultar", "inventario.registrar", "inventario.editar",
        "inventario.dar_baja", "inventario.consultar_estado",
        "inventario.buscar_disponibles"
    ],
    "Estudiante" => [
        "inventario.consultar", "inventario.consultar_estado",
        "inventario.buscar_disponibles"
    ],
    "Docente" => [
        "inventario.consultar", "inventario.consultar_estado",
        "inventario.buscar_disponibles"
    ],
    "Coordinador académico" => [
        "inventario.consultar", "inventario.consultar_estado",
        "inventario.buscar_disponibles"
    ]
];

function tienePermiso($permiso) {
    global $permisos;
    if (!isset($_SESSION["rol"])) return false;
    $rol = $_SESSION["rol"];
    return isset($permisos[$rol]) && in_array($permiso, $permisos[$rol], true);
}
?>
