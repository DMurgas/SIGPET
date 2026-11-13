<?php

require_once __DIR__ . '/verificar_sesion.php';
require_once __DIR__ . '/permisos.php';

function verificarPermiso(string $permiso): void
{
    if (!tienePermiso($permiso)) {

        http_response_code(403);

        $directorio = str_replace(
            '\\',
            '/',
            dirname($_SERVER['SCRIPT_NAME'] ?? '')
        );

        $enSubcarpeta = preg_match(
            '~/(usuarios|inventario|prestamos)$~',
            rtrim($directorio, '/')
        ) === 1;

        $rutaBase = $enSubcarpeta ? '../' : '';

        echo "
        <!DOCTYPE html>
        <html lang='es'>

        <head>
            <meta charset='UTF-8'>
            <meta
                name='viewport'
                content='width=device-width, initial-scale=1'
            >

            <title>Acceso denegado | SIGPET</title>

            <link
                rel='stylesheet'
                href='{$rutaBase}CSS/base.css'
            >
        </head>

        <body>

            <main
                style='
                    max-width:700px;
                    margin:80px auto;
                    padding:30px;
                    font-family:Arial,sans-serif
                '
            >

                <h1>
                    Acceso denegado
                </h1>

                <p>
                    No tienes permisos para acceder a esta funcionalidad.
                </p>

                <a href='{$rutaBase}dashboard.php'>
                    Volver al panel principal
                </a>

            </main>

        </body>

        </html>
        ";

        exit();
    }
}