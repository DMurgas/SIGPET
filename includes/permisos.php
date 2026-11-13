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
        "prestamos.historial"
    ],

    "Encargado de préstamos" => [
        "inventario.consultar",
        "inventario.registrar",
        "inventario.editar",
        "inventario.dar_baja",
        "inventario.consultar_estado",
        "inventario.buscar_disponibles",

        "prestamos.gestionar",
        "prestamos.historial"
    ],

    "Estudiante" => [
        "inventario.consultar",
        "inventario.consultar_estado",
        "inventario.buscar_disponibles",

        "prestamos.solicitar"
    ],

    "Docente" => [
        "inventario.consultar",
        "inventario.consultar_estado",
        "inventario.buscar_disponibles",

        "prestamos.solicitar"
    ],

    "Coordinador académico" => [
        "inventario.consultar",
        "inventario.consultar_estado",
        "inventario.buscar_disponibles",

        "prestamos.historial"
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