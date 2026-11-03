
<?php

require_once "includes/verificar_rol.php";

$nombre = $_SESSION["nombres"];
$apellido = $_SESSION["apellidos"];
$rol = $_SESSION["rol"];

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel principal | SIGPET</title>
    <link rel="stylesheet" href="CSS/base.css">
    <link rel="stylesheet" href="CSS/dashboard.css">
    <link rel="stylesheet" href="CSS/inventario.css">
</head>

<body class="dashboard-body">

    <div class="dashboard-layout">

        <!-- BARRA LATERAL -->
        <?php
$rutaBase = "";
$paginaActiva = "dashboard";

require_once "includes/sidebar.php";
?>


        <!-- CONTENIDO PRINCIPAL -->
        <main class="dashboard-main">

            <header class="dashboard-header">

                <div>
                    <span class="header-subtitle">SISTEMA DE GESTIÓN</span>
                    <h1>Panel principal</h1>
                </div>

                <div class="header-user">
                    <div class="header-user-text">
                        <strong>
                            <?= htmlspecialchars($nombre . " " . $apellido) ?>
                        </strong>
                        <span><?= htmlspecialchars($rol) ?></span>
                    </div>

                    <div class="user-avatar header-avatar">
                        <?= strtoupper(substr($nombre, 0, 1)) ?>
                    </div>
                </div>

            </header>


            <section class="welcome-section">
                <div>
                    <span class="welcome-label">BIENVENIDO A SIGPET</span>

                    <h2>
                        Hola, <?= htmlspecialchars($nombre) ?>.
                    </h2>

                    <p>
                        Desde aquí puedes acceder a las funcionalidades
                        disponibles según tu rol en el sistema.
                    </p>
                </div>
            </section>


            <section class="dashboard-section">

                <div class="section-heading">
                    <div>
                        <h2>Accesos disponibles</h2>
                        <p>Selecciona el módulo que deseas utilizar.</p>
                    </div>
                </div>


                <div class="dashboard-cards">

                    <?php if (tienePermiso("usuarios.consultar")): ?>

                        <article class="dashboard-card">

                            <div class="card-icon users-icon">♙</div>

                            <h3>Gestión de usuarios</h3>

                            <p>
                                Administra las cuentas, consulta los usuarios
                                registrados y gestiona sus roles.
                            </p>

                            <a href="usuarios/listar.php" class="card-link">
                                Acceder al módulo →
                            </a>

                        </article>

                    <?php endif; ?>


                    <?php if (tienePermiso("inventario.consultar")): ?>

                        <article class="dashboard-card">

                            <div class="card-icon inventory-icon">▣</div>

                            <h3>Inventario de equipos</h3>

                            <p>
                                Consulta los equipos tecnológicos, sus
                                características, clasificación y estado.
                            </p>

                            <a href="inventario/listar.php" class="card-link">
                                Consultar inventario →
                            </a>

                        </article>

                    <?php endif; ?>


                    <?php if (tienePermiso("inventario.registrar")): ?>

                        <article class="dashboard-card">

                            <div class="card-icon register-icon">＋</div>

                            <h3>Registrar equipo</h3>

                            <p>
                                Incorpora nuevos equipos tecnológicos
                                al inventario institucional.
                            </p>

                            <a href="inventario/registrar.php" class="card-link">
                                Registrar equipo →
                            </a>

                        </article>

                    <?php endif; ?>

                </div>

            </section>


            <footer class="dashboard-footer">
                SIGPET © 2026 | Sistema de Gestión de Préstamos
                de Equipos Tecnológicos
            </footer>

        </main>

    </div>

</body>
</html>
