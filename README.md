# SIGPET — Incrementos 1, 2, 3 y 4

Sistema web académico para la gestión de usuarios, inventario, reservas, préstamos, alertas, reportes y bitácora de equipos tecnológicos.

La versión actual integra las funcionalidades correspondientes a los **Incrementos 1, 2, 3 y 4**.

## Tecnologías

- PHP 8.1 o superior
- MySQL / MariaDB
- XAMPP / Servidor embebido PHP
- HTML y CSS
- JavaScript nativo para navegación parcial sin recarga, modal de detalle, notificaciones flotantes y confirmaciones del navegador

## Instalación local con XAMPP

1. Copiar la carpeta del proyecto dentro de:

   `C:\xampp\htdocs\`

2. Iniciar **Apache** y **MySQL** desde el panel de XAMPP.

3. Abrir phpMyAdmin:

   `http://localhost/phpmyadmin/`

4. Importar el archivo:

   `database/sigpet.sql`

   El script crea la base de datos `sigpet`, selecciona dicha base y crea las tablas, vistas y relaciones necesarias para los Incrementos 1, 2, 3 y 4.

   Opcionalmente, para cargar datos de prueba completos (usuarios en los 5 roles, equipos, solicitudes, préstamos activos/históricos, alertas y registros de bitácora), importar a continuación:

   `database/seed_prueba.sql`

5. Revisar:

   `config/conexion.php`

   La configuración incluida corresponde al entorno XAMPP local utilizado durante el desarrollo:

   - Servidor: `localhost`
   - Usuario: `root`
   - Contraseña: vacía
   - Base de datos: `sigpet`

6. Abrir el sistema desde:

   `http://localhost/INR_SIGPET/login.php`

> Si ya existe una base de datos `sigpet` con información que se desea conservar, realizar una copia de seguridad antes de importar el script SQL.

## Usuarios y roles

El sistema maneja los siguientes roles:

- Estudiante
- Docente
- Encargado de préstamos
- Administrador
- Coordinador académico

El archivo SQL contiene registros semilla de usuarios con contraseñas almacenadas mediante hash. Las contraseñas no se almacenan en texto plano.

La administración de usuarios puede realizarse desde:

**Gestión de usuarios → Nuevo usuario**

cuando la cuenta tenga los permisos correspondientes.

## Incremento 1 — RF-01 a RF-07

### RF-01 — Autenticación

- Inicio de sesión mediante correo institucional y contraseña.
- Verificación de contraseña mediante `password_verify()`.
- Rechazo de cuentas inactivas.
- Regeneración del identificador de sesión después de la autenticación.
- Cierre de sesión.

### RF-02 — Roles

- Catálogo de cinco roles.
- Asignación de roles desde la administración de usuarios.
- Matriz de permisos en `includes/permisos.php`.
- Protección de páginas y acciones mediante validación del lado del servidor.

### RF-03 — Usuarios

- Consulta y búsqueda de usuarios.
- Registro de nuevos usuarios.
- Almacenamiento seguro de contraseñas mediante hash.
- Edición de datos y roles.
- Desactivación y reactivación lógica.
- Protección de la cuenta del administrador.

### RF-04 — Equipos

- Registro de equipos.
- Consulta y edición.
- Validación de código de inventario y número de serie.
- Baja lógica mediante el estado `Dado de baja`.
- Reactivación de equipos dados de baja.

### RF-05 — Tipos de equipo

- Gestión mediante el catálogo `tipos_equipo`.
- Tipos iniciales:
  - Laptop
  - Proyector
  - Cámara
  - Kit electrónico

### RF-06 — Estado del equipo

El sistema maneja los siguientes estados:

- Disponible
- Reservado
- Prestado
- En mantenimiento
- Dado de baja

### RF-07 — Búsqueda y filtros

- Búsqueda por código de inventario.
- Búsqueda por nombre, marca y modelo.
- Búsqueda por número de serie y ubicación.
- Filtros por tipo y estado.
- Filtro de equipos disponibles.

## Incremento 2 — Préstamos y devoluciones

El Incremento 2 implementa el ciclo de vida de los préstamos de equipos tecnológicos.

### Solicitudes de préstamo

- Registro de solicitudes de préstamo.
- Estado inicial `Pendiente`.
- Registro de usuario solicitante.
- Registro de equipo solicitado.
- Registro de fecha de inicio.
- Registro de fecha prevista de devolución.
- Registro del propósito de la solicitud.

### Aprobación y rechazo

El encargado de préstamos puede:

- Aprobar solicitudes pendientes.
- Rechazar solicitudes.
- Registrar el motivo del rechazo.
- Registrar quién tomó la decisión.
- Registrar la fecha de decisión.

Cuando una solicitud es aprobada, el equipo pasa al estado `Reservado`.

### Entrega del equipo

El sistema permite registrar:

- Fecha de entrega.
- Responsable de la entrega.
- Persona que autorizó el préstamo.
- Condición física inicial.

Al registrar la entrega, el equipo pasa de `Reservado` a `Prestado`.

### Devolución del equipo

El sistema permite registrar:

- Fecha real de devolución.
- Condición final del equipo.
- Observaciones.
- Resultado de la devolución.

Cuando la devolución es normal:

`Prestado → Disponible`

Cuando el equipo presenta daños:

`Prestado → En mantenimiento`

El préstamo queda registrado como `Devuelto`.

### Historial de préstamos

El sistema integra un módulo de historial que permite consultar los préstamos y sus estados, incluyendo información relacionada con:

- Solicitante.
- Equipo.
- Fechas.
- Estado del préstamo.
- Entrega.
- Devolución.
- Condiciones y observaciones.

El historial se encuentra integrado al sistema mediante el menú principal y el control de permisos.

### Control de equipos en mantenimiento

Los equipos con estado `En mantenimiento` no se muestran como disponibles para nuevas solicitudes.

Esto evita que un equipo que requiere mantenimiento pueda ser solicitado nuevamente.

## Flujo general del préstamo

```text
Solicitud
   ↓
Pendiente
   ↓
Aprobación
   ↓
Reservado
   ↓
Entrega
   ↓
Prestado
   ↓
Devolución
   ├── Buen estado → Disponible
   └── Con daños   → En mantenimiento
```

## Incremento 3 — Reservas y reglas de préstamo

El Incremento 3 incorpora la planificación por rango de fechas, la detección de traslapes y las reglas académicas de control para las solicitudes.

### Disponibilidad y agenda de reservas

- Consulta de disponibilidad por período (`fecha_inicio` a `fecha_fin`) desde `prestamos/reservas.php`.
- Filtros por tipo de equipo y texto libre para identificar equipos disponibles u ocupados en el rango consultado.
- Agenda unificada de reservas pendientes, aprobadas y préstamos en curso con indicación de traslapes.

### Validación de conflictos de horario

- Verificación transaccional de traslapes inclusivos en `includes/reservas_helper.php` (`buscarConflictoReserva()`).
- Bloqueo en el servidor antes de registrar una solicitud o aprobarla si existe cruce con otra solicitud `Pendiente`, `Aprobada` o un préstamo `Prestado`.
- Integración directa en `prestamos/solicitar.php` y `prestamos/aprobar.php`.

### Bloqueo por préstamos vencidos y autorización excepcional

- Bloqueo automático de nuevas solicitudes cuando el usuario mantiene préstamos vencidos sin devolver.
- Flujo de supervisión en `prestamos/reservas.php?tab=vencidos` mediante la tabla `autorizaciones_vencido`:
  - Registro de autorización excepcional por parte del encargado con motivo obligatorio.
  - Revocación de excepciones activas en cualquier momento.

### Prioridad académica y cancelación lógica

- Marcado automático o asistido de prioridad (`es_prioridad`) para solicitudes docentes.
- Priorización visual y de ordenamiento en la atención de solicitudes (`prestamos/listar.php` y `dashboard.php`).
- Cancelación de solicitudes en estado `Pendiente` mediante `prestamos/cancelar.php`, conservando el registro como `Cancelada` para trazabilidad.

## Incremento 4 — Alertas, reportes, bitácora y experiencia de uso

El Incremento 4 añade seguimiento preventivo, análisis operativo, auditoría inmutable y mejoras visuales en todo el sistema.

### Alertas por vencimiento y atraso

- Detección automática y persistente en la tabla `alertas` (`includes/alertas.php`, `prestamos/alertas.php`):
  - Alertas de préstamos próximos a vencer (hasta 2 días antes de la fecha prevista).
  - Alertas de préstamos atrasados con cálculo de días de demora.
  - Destinatarios diferenciados para el solicitante y el encargado de préstamos.
- Evitación de duplicados mediante clave de seguimiento, marcado de lectura individual o masivo y cierre automático al registrar la devolución.

### Reportes operativos y exportación

- Módulo `reportes/index.php` con filtros por período de fechas y medición del tiempo de generación de cada consulta:
  - Equipos más solicitados.
  - Préstamos atrasados y usuarios con demora.
  - Uso por período, categoría de equipo y rol.
- Exportación a archivo CSV compatible con UTF-8 desde `reportes/exportar.php` y vista optimizada para impresión.

### Bitácora de auditoría

- Registro histórico inmutable en la tabla `bitacora` mediante `includes/bitacora.php`.
- Cobertura de eventos clave:
  - Inicio y cierre de sesión.
  - Registro, edición y cambio de estado de usuarios y equipos.
  - Solicitudes, aprobaciones, rechazos, cancelaciones, entregas y devoluciones.
  - Autorizaciones excepcionales y generación de alertas.
- Consulta y filtrado en `bitacora/listar.php` por módulo, usuario, rango de fechas y texto.

### Interfaz, navegación y datos de prueba

- Centro de control renovado (`dashboard.php`) con indicadores operativos, accesos rápidos según permisos y panel de seguimiento.
- Barra lateral colapsable con iconografía SVG vectorial (`includes/sidebar.php`, `includes/iconos.php`).
- Navegación parcial sin parpadeos, notificaciones flotantes cerrables y modal de lectura cómoda para filas de tablas (`JS/sigpet-spa.js`, `CSS/dashboard.css`) integrado en `prestamos/historial.php`, `prestamos/listar.php`, `prestamos/mis_solicitudes.php`, `bitacora/listar.php` e `inventario/listar.php`.
- Datos semilla de prueba en `database/seed_prueba.sql` para validar los flujos de todos los roles.