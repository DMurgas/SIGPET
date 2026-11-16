<?php

$permisos = [

    "Administrador" => [
        "usuarios.consultar",
        "usuarios.registrar",
        "usuarios.editar",
        "usuarios.desactivar",
        "usuarios.asignar_roles",

        "inventario.consultar",
        "inventario.registrar",
        "inventario.editar",
        "inventario.dar_baja",
        "inventario.consultar_estado",
        "inventario.buscar_disponibles",

        "prestamos.solicitar",
        "prestamos.gestionar",
        "prestamos.historial",

        "reservas.consultar",
        "reservas.gestionar",
        "alertas.consultar",
        "alertas.gestionar",
        "reportes.consultar",
        "bitacora.consultar"
    ],

    "Encargado de préstamos" => [
        "inventario.consultar",
        "inventario.registrar",
        "inventario.editar",
        "inventario.dar_baja",
        "inventario.consultar_estado",
        "inventario.buscar_disponibles",

        "prestamos.gestionar",
        "prestamos.historial",

        "reservas.consultar",
        "reservas.gestionar",
        "alertas.consultar",
        "alertas.gestionar",
        "reportes.consultar",
        "bitacora.consultar"
    ],

    "Estudiante" => [
        "inventario.consultar",
        "inventario.consultar_estado",
        "inventario.buscar_disponibles",

        "prestamos.solicitar",
        "reservas.consultar",
        "alertas.consultar"
    ],

    "Docente" => [
        "inventario.consultar",
        "inventario.consultar_estado",
        "inventario.buscar_disponibles",

        "prestamos.solicitar",
        "reservas.consultar",
        "alertas.consultar"
    ],

    "Coordinador académico" => [
        "inventario.consultar",
        "inventario.consultar_estado",
        "inventario.buscar_disponibles",

        "prestamos.historial",
        "reservas.consultar",
        "alertas.consultar",
        "reportes.consultar",
        "bitacora.consultar"
    ]
];

function tienePermiso(string $permiso): bool
{
    global $permisos;

    if (!isset($_SESSION["rol"])) {
        return false;
    }

    $rol = $_SESSION["rol"];

    return isset($permisos[$rol])
        && in_array($permiso, $permisos[$rol], true);
}

?>