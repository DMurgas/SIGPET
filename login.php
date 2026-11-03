
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | SIGPET</title>
    <link rel="stylesheet" href="CSS/base.css">
<link rel="stylesheet" href="CSS/login.css">
</head>
<body>

    <main class="contenedor-login">

        <section class="panel-informativo">
            <div class="marca">
                <div class="marca-icono">S</div>
                <span>SIGPET</span>
            </div>

            <div class="informacion">
                <span class="etiqueta">GESTIÓN TECNOLÓGICA</span>

                <h1>Gestiona tus equipos tecnológicos de forma sencilla.</h1>

                <p>
                    Plataforma para la administración y gestión
                    de préstamos de equipos tecnológicos.
                </p>
            </div>

            <div class="pie-panel">
                Sistema de Gestión de Préstamos de Equipos Tecnológicos
            </div>
        </section>

        <section class="panel-formulario">

            <div class="formulario-contenido">

            <?php if (isset($_GET["error"])): ?>
                <div class="mensaje-error">
                    <?php
                    if ($_GET["error"] === "credenciales") {
                        echo "Correo o contraseña incorrectos.";
                        } elseif ($_GET["error"] === "inactivo") {
                            echo "Tu cuenta se encuentra desactivada.";
                            } elseif ($_GET["error"] === "campos") {
                                echo "Debes completar todos los campos.";
                                }
                                ?>
                                </div>
                                <?php endif; ?>

                <div class="encabezado">
                    <span class="bienvenida">BIENVENIDO</span>
                    <h2>Iniciar sesión</h2>
                    <p>Ingresa tus credenciales para continuar.</p>
                </div>

                <form action="validar_login.php" method="POST">

                    <div class="campo">
                        <label for="correo">Correo institucional</label>
                        <input
                        type="email"
                        id="correo"
                        name="correo"
                        placeholder="ejemplo@institucion.edu"
                        autocomplete="username"
                        required
                        >
                    </div>

                    <div class="campo">
                        <label for="password">Contraseña</label>
                        <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Ingresa tu contraseña"
                        autocomplete="current-password"
                        required
                        >
                    </div>

                    <button type="submit" class="boton-login">
                        Ingresar
                        <span>→</span>
                    </button>

                </form>

                <div class="nota-seguridad">
                    Acceso exclusivo para usuarios registrados en SIGPET.
                </div>

            </div>

            <footer class="pie-formulario">
                SIGPET © 2026 | Todos los derechos reservados
            </footer>

        </section>

    </main>

</body>
</html>
