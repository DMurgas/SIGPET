# SIGPET — Incrementos 1 y 2

Sistema web académico para la gestión de usuarios, inventario y préstamos de equipos tecnológicos.

La versión actual integra las funcionalidades correspondientes al **Incremento 1** y al **Incremento 2**, incluyendo la gestión de usuarios, inventario, solicitudes de préstamo, aprobación y rechazo, entrega, devolución, actualización de estados, mantenimiento e historial de préstamos.

## Tecnologías

- PHP 8.1 o superior
- MySQL / MariaDB
- XAMPP
- HTML y CSS
- JavaScript nativo para confirmaciones del navegador

## Instalación local con XAMPP

1. Copiar la carpeta del proyecto dentro de:

   `C:\xampp\htdocs\`

2. Iniciar **Apache** y **MySQL** desde el panel de XAMPP.

3. Abrir phpMyAdmin:

   `http://localhost/phpmyadmin/`

4. Importar el archivo:

   `database/sigpet.sql`

   El script crea la base de datos `sigpet`, selecciona dicha base y crea las tablas necesarias para los Incrementos 1 y 2.

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