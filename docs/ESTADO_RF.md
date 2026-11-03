# Matriz de entrega del Incremento 1 — SIGPET

| RF | Funcionalidad incorporada | Archivos principales | Verificación sugerida |
|---|---|---|---|
| RF-01 | Login institucional, contraseña hash, bloqueo de inactivos y logout | `login.php`, `validar_login.php`, `logout.php` | CP-01: login válido e inválido |
| RF-02 | Cinco roles, asignación y matriz de permisos de servidor | `includes/permisos.php`, `usuarios/registrar.php`, `usuarios/editar.php`, `includes/verificar_rol.php` | CP-02: probar permisos con cada rol |
| RF-03 | CRUD de consulta/registro/edición y desactivación lógica | `usuarios/listar.php`, `usuarios/registrar.php`, `usuarios/editar.php`, `usuarios/cambiar_estado.php` | CP-03: registrar, editar, desactivar y reactivar |
| RF-04 | Registro/edición/consulta y baja lógica de equipos | `inventario/listar.php`, `inventario/registrar.php`, `inventario/editar.php`, `inventario/cambiar_estado.php` | CP-04: registrar, editar y dar de baja |
| RF-05 | Clasificación por catálogo de tipos | `inventario/registrar.php`, `inventario/editar.php`, `inventario/listar.php` | CP-05: clasificar por tipo |
| RF-06 | Visualización y edición del estado de equipos | `inventario/listar.php`, `inventario/editar.php` | CP-06: visualizar estado |
| RF-07 | Búsqueda y filtros de inventario y disponibilidad | `inventario/listar.php` | CP-07: búsqueda, tipo, estado y solo disponibles |

## Decisiones de implementación

- Usuarios: la baja lógica utiliza `usuarios.activo = 0`. El registro no se elimina.
- Equipos: la baja lógica utiliza el registro de `estados_equipo` cuyo nombre es `Dado de baja`; el registro no se elimina.
- Un equipo nuevo se crea con estado `Disponible`.
- Los catálogos de roles, tipos y estados provienen del SQL entregado.
- Las acciones de escritura utilizan POST y token CSRF.
- Los permisos se comprueban en las páginas y endpoints; ocultar un enlace en la interfaz no es la única barrera.

## Nota de pruebas

El código de cada archivo PHP debe pasar `php -l`. Las pruebas de comportamiento y tiempos deben realizarse en el entorno XAMPP local con la base de datos importada y cuentas de cada rol.
