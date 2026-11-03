# SIGPET — Incremento 1 (RF-01 a RF-07)

Aplicación web académica para la gestión de usuarios e inventario de equipos tecnológicos.

## Tecnologías

- PHP 8.1 o superior
- MySQL / MariaDB (XAMPP)
- HTML y CSS
- JavaScript nativo únicamente para confirmaciones del navegador

## Instalación local con XAMPP

1. Copiar la carpeta `INR_SIGPET` dentro de `C:\xampp\htdocs\`.
2. Iniciar Apache y MySQL desde el panel de XAMPP.
3. Abrir phpMyAdmin en `http://localhost/phpmyadmin/`.
4. Crear una base de datos llamada `sigpet` con cotejamiento `utf8mb4_general_ci`, o seleccionar la base existente.
5. Importar `database/sigpet.sql`.
6. Revisar `config/conexion.php`. La configuración incluida corresponde al XAMPP local típico: servidor `localhost`, usuario `root`, contraseña vacía y base `sigpet`.
7. Abrir `http://localhost/INR_SIGPET/login.php`.

> Si ya tienes una base `sigpet` con datos que deseas conservar, realiza una copia de seguridad antes de importar el SQL, ya que el script crea las tablas e inserta los catálogos iniciales.

## Usuarios y roles

El SQL de entrega contiene registros semilla de usuarios con contraseñas almacenadas mediante hash. El archivo SQL no contiene las contraseñas en texto plano; utiliza las credenciales que el equipo ya definió para esas cuentas. Para crear otras cuentas, ingresa como Administrador y utiliza **Gestión de usuarios → Nuevo usuario**.

Roles incluidos en la tabla `roles`:

- Estudiante
- Docente
- Encargado de préstamos
- Administrador
- Coordinador académico

## Funcionalidades entregadas

### RF-01 — Autenticación

- Inicio de sesión mediante correo institucional y contraseña.
- Verificación con `password_verify()`.
- Rechazo de cuentas inactivas.
- Regeneración del identificador de sesión tras autenticación.
- Cierre de sesión.

### RF-02 — Roles

- Catálogo de cinco roles precargados en la tabla `roles`.
- Asignación de rol al registrar o editar una cuenta (Administrador).
- Matriz de permisos en `includes/permisos.php`.
- Protección de páginas y acciones del lado del servidor.

### RF-03 — Usuarios

- Consulta y búsqueda por nombres, apellidos, correo, código y rol.
- Registro de usuarios y hash seguro de contraseñas.
- Edición de datos y rol (Administrador).
- Desactivación y reactivación lógica mediante `usuarios.activo`.
- Protección para impedir que el administrador desactive su propia cuenta o deje el sistema sin administrador activo.

### RF-04 — Equipos

- Registro, consulta y edición de equipos.
- Baja lógica mediante el estado `Dado de baja`; no se elimina físicamente el registro.
- Reactivación de un equipo dado de baja (lo devuelve a estado `Disponible`).
- Validación de código de inventario y número de serie duplicados.

### RF-05 — Tipos de equipo

- Clasificación mediante el catálogo `tipos_equipo`.
- Tipos iniciales: Laptop, Proyector, Cámara y Kit electrónico.
- Selección de tipo al registrar y editar un equipo.

### RF-06 — Estado del equipo

- Visualización del estado actual en el inventario.
- Estados iniciales: Disponible, Reservado, Prestado, En mantenimiento y Dado de baja.
- Edición del estado desde el formulario de modificación del equipo.

### RF-07 — Búsqueda y filtros

- Búsqueda por código de inventario, nombre, marca, modelo, número de serie y ubicación.
- Filtro por tipo.
- Filtro por estado.
- Filtro rápido para mostrar solamente los equipos Disponibles.

## Permisos configurados

| Rol | Usuarios | Inventario |
|---|---|---|
| Administrador | Consultar, registrar, editar, desactivar y asignar roles | Consultar, registrar, editar y dar de baja |
| Encargado de préstamos | Sin gestión de usuarios | Consultar, registrar, editar y dar de baja |
| Estudiante | Sin gestión de usuarios | Consultar estado y disponibilidad |
| Docente | Sin gestión de usuarios | Consultar estado y disponibilidad |
| Coordinador académico | Sin gestión de usuarios | Consultar estado y disponibilidad |

## Estructura principal

```text
INR_SIGPET/
├── config/conexion.php
├── CSS/
├── database/sigpet.sql
├── includes/
│   ├── csrf.php
│   ├── permisos.php
│   ├── sidebar.php
│   ├── verificar_rol.php
│   └── verificar_sesion.php
├── inventario/
│   ├── listar.php
│   ├── registrar.php
│   ├── editar.php
│   └── cambiar_estado.php
├── usuarios/
│   ├── listar.php
│   ├── registrar.php
│   ├── editar.php
│   └── cambiar_estado.php
├── dashboard.php
├── login.php
├── logout.php
└── validar_login.php
```

## Pruebas funcionales recomendadas

1. Iniciar sesión con una cuenta activa y comprobar el acceso al panel.
2. Intentar acceder con contraseña incorrecta y con una cuenta inactiva.
3. Verificar que el Administrador pueda registrar, editar, buscar, desactivar y reactivar usuarios.
4. Confirmar que un usuario Estudiante o Docente no pueda abrir las páginas de administración de usuarios por URL.
5. Registrar un equipo de cada tipo y comprobar que inicie como Disponible.
6. Editar características, tipo y estado de un equipo.
7. Dar de baja un equipo y comprobar que permanezca en la base de datos con estado `Dado de baja`.
8. Buscar por código, nombre o marca, filtrar por tipo y estado, y activar `Solo disponibles`.
9. Iniciar sesión con Estudiante y Docente y verificar que puedan consultar el inventario, pero no modificarlo.
10. Iniciar sesión con Encargado de préstamos y verificar que pueda administrar inventario, pero no usuarios.

## Alcance de esta versión

Esta entrega implementa las funcionalidades correspondientes al **Incremento 1: RF-01 a RF-07**. No incluye solicitudes/reservas, aprobación, préstamos, entregas, devoluciones, historial, alertas, reportes ni bitácora; esas funciones pertenecen a incrementos posteriores según el documento del proyecto.

La ejecución de PHP se revisó mediante comprobación sintáctica. Las pruebas de integración con MySQL deben ejecutarse en el XAMPP del equipo, porque esta entrega no contiene una instancia de su base de datos ejecutándose.
