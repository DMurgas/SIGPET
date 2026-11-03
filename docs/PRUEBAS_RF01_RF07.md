# Plan de pruebas de aceptación — Incremento 1

Los identificadores CP-01 a CP-07 siguen la matriz de trazabilidad de la Etapa 2.

| Caso | RF | Preparación / acción | Resultado esperado |
|---|---|---|---|
| CP-01 | RF-01 | Ingresar con correo y contraseña correctos; repetir con contraseña incorrecta y con una cuenta inactiva. | La cuenta activa con credenciales correctas inicia sesión; los otros intentos son rechazados con un mensaje apropiado. |
| CP-02 | RF-02 | Iniciar sesión con cada uno de los cinco roles e intentar abrir módulos/acciones autorizados y no autorizados. | Cada rol ve y utiliza solo las acciones asignadas en `includes/permisos.php`; una URL directa no permite evadir la autorización. |
| CP-03 | RF-03 | Como Administrador, registrar un usuario, editarlo, buscarlo, desactivarlo y reactivarlo. | La cuenta aparece en el listado, los cambios persisten y una cuenta desactivada no puede iniciar sesión. |
| CP-04 | RF-04 | Como Administrador o Encargado, registrar un equipo, editarlo y darlo de baja lógica. | El equipo queda almacenado, puede editarse y su baja cambia el estado a `Dado de baja` sin eliminar el registro. |
| CP-05 | RF-05 | Registrar equipos de tipos distintos y filtrar el inventario por tipo. | El tipo seleccionado se almacena y se muestra correctamente. |
| CP-06 | RF-06 | Consultar el inventario y cambiar el estado de un equipo a cada estado disponible en el catálogo. | El listado muestra el estado actual: Disponible, Reservado, Prestado, En mantenimiento o Dado de baja. |
| CP-07 | RF-07 | Como Estudiante y como Docente, buscar por código/nombre/marca, seleccionar tipo y estado, y activar Solo disponibles. | Se muestran los resultados que cumplen los criterios; Solo disponibles devuelve únicamente equipos con estado Disponible. |

## Resultado de revisión del paquete

- Sintaxis de los archivos PHP: **sin errores** al ejecutar `php -l` sobre cada archivo.
- Integración con base de datos: pendiente de ejecutarse en el XAMPP local. El entorno de validación utilizado para preparar este paquete no tiene habilitada la extensión PHP `mysqli` ni un servidor MySQL/MariaDB; por ello no se declara que las consultas hayan sido ejecutadas aquí.
- Prueba de aceptación: debe realizarse después de importar `database/sigpet.sql` y abrir el sistema desde `http://localhost/INR_SIGPET/`.
