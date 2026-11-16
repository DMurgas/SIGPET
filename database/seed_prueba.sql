-- ============================================================
-- SIGPET - Semilla de datos de prueba (Incrementos 1 al 4)
-- Contraseña para todas las cuentas de prueba nuevas: admin123
-- ============================================================

USE `sigpet`;

START TRANSACTION;

-- ============================================================
-- 1. USUARIOS DE PRUEBA (todos los roles)
-- ============================================================
INSERT IGNORE INTO `usuarios`
(`id_rol`, `nombres`, `apellidos`, `correo_institucional`, `codigo_institucional`, `password`, `activo`)
VALUES
(1, 'Carlos Eduardo', 'Méndez Rivera', 'carlos.mendez@ues.edu.sv', 'CM24011', '$2y$12$mD7ieKfZCDI1DyFcv9L70OxZ5Wnn4JVz..sL.gfsEgU3RcZN3RY1e', 1),
(1, 'Sofía Alejandra', 'Ramírez Cruz', 'sofia.ramirez@ues.edu.sv', 'SR24022', '$2y$12$mD7ieKfZCDI1DyFcv9L70OxZ5Wnn4JVz..sL.gfsEgU3RcZN3RY1e', 1),
(1, 'Luis Fernando', 'Castro Mejía', 'luis.castro@ues.edu.sv', 'LC24033', '$2y$12$mD7ieKfZCDI1DyFcv9L70OxZ5Wnn4JVz..sL.gfsEgU3RcZN3RY1e', 1),
(1, 'Valeria Beatriz', 'Ortiz Pineda', 'valeria.ortiz@ues.edu.sv', 'VO24044', '$2y$12$mD7ieKfZCDI1DyFcv9L70OxZ5Wnn4JVz..sL.gfsEgU3RcZN3RY1e', 0),
(2, 'Ana Lucía', 'Martínez Gómez', 'ana.martinez@ues.edu.sv', 'DOC101', '$2y$12$mD7ieKfZCDI1DyFcv9L70OxZ5Wnn4JVz..sL.gfsEgU3RcZN3RY1e', 1),
(2, 'Ricardo Javier', 'López Navarro', 'ricardo.lopez@ues.edu.sv', 'DOC102', '$2y$12$mD7ieKfZCDI1DyFcv9L70OxZ5Wnn4JVz..sL.gfsEgU3RcZN3RY1e', 1),
(2, 'Elena Patricia', 'Flores Ayala', 'elena.flores@ues.edu.sv', 'DOC103', '$2y$12$mD7ieKfZCDI1DyFcv9L70OxZ5Wnn4JVz..sL.gfsEgU3RcZN3RY1e', 1),
(3, 'Jorge Alberto', 'Silva Morales', 'jorge.silva@sigpet.edu.sv', 'ENC201', '$2y$12$mD7ieKfZCDI1DyFcv9L70OxZ5Wnn4JVz..sL.gfsEgU3RcZN3RY1e', 1),
(3, 'Marcela Sofía', 'Reyes Portillo', 'marcela.reyes@sigpet.edu.sv', 'ENC202', '$2y$12$mD7ieKfZCDI1DyFcv9L70OxZ5Wnn4JVz..sL.gfsEgU3RcZN3RY1e', 1),
(5, 'Roberto Antonio', 'Navarro Vásquez', 'roberto.navarro@sigpet.edu.sv', 'COO301', '$2y$12$mD7ieKfZCDI1DyFcv9L70OxZ5Wnn4JVz..sL.gfsEgU3RcZN3RY1e', 1);

-- ============================================================
-- 2. INVENTARIO DE EQUIPOS (19 equipos nuevos -> 20 en total)
-- ============================================================
INSERT IGNORE INTO `equipos`
(`id_tipo`, `id_estado`, `codigo_inventario`, `nombre`, `marca`, `modelo`, `numero_serie`, `descripcion`, `ubicacion`)
VALUES
-- Laptops (tipo 1)
(1, 3, 'EQ-LAP-001', 'Laptop Dell Latitude 5430', 'Dell', 'Latitude 5430', 'SN-DL5430-001', 'Core i7, 16GB RAM, SSD 512GB, con cargador original', 'Laboratorio de Cómputo B-101'),
(1, 2, 'EQ-LAP-002', 'Laptop Lenovo ThinkPad T14', 'Lenovo', 'ThinkPad T14 Gen 3', 'SN-LNT14-002', 'Ryzen 7 Pro, 16GB RAM, SSD 512GB', 'Bodega Central TI'),
(1, 1, 'EQ-LAP-003', 'Laptop HP ProBook 450 G9', 'HP', 'ProBook 450 G9', 'SN-HPPB450-003', 'Core i5, 16GB RAM, SSD 512GB para prácticas', 'Laboratorio de Redes A-204'),
(1, 1, 'EQ-LAP-004', 'MacBook Air M2', 'Apple', 'MacBook Air 13 M2', 'SN-APMBA-004', 'Chip M2, 16GB RAM, 512GB para edición multimedia', 'Sala Multimedia C-102'),
(1, 1, 'EQ-LAP-005', 'Laptop ASUS VivoBook Pro', 'ASUS', 'VivoBook Pro 15', 'SN-ASVBP-005', 'RTX 3050, 16GB RAM para simulación y CAD', 'Laboratorio de Diseño B-202'),
(1, 1, 'EQ-LAP-006', 'Laptop Acer Swift Go', 'Acer', 'Swift Go 14', 'SN-ACSWG-006', 'Equipo ultraligero para exposiciones y campo', 'Bodega Central TI'),

-- Proyectores (tipo 2)
(2, 3, 'EQ-PRO-001', 'Proyector Epson PowerLite X49', 'Epson', 'PowerLite X49', 'SN-EPX49-001', '3600 lúmenes, HDMI/VGA con control remoto', 'Audiovisuales Edificio A'),
(2, 4, 'EQ-PRO-002', 'Proyector BenQ MH560', 'BenQ', 'MH560 Full HD', 'SN-BQMH560-002', '3800 lúmenes, en revisión por ruido en ventilador', 'Taller de Soporte Técnico'),
(2, 1, 'EQ-PRO-003', 'Proyector ViewSonic PA503W', 'ViewSonic', 'PA503W', 'SN-VSPA503-003', 'Proyector portátil WXGA con estuche', 'Audiovisuales Edificio B'),
(2, 1, 'EQ-PRO-004', 'Proyector Epson EpiqVision', 'Epson', 'CO-FH02', 'SN-EPEQ-004', 'Full HD inteligente para auditorio', 'Auditorio FIA'),
(2, 1, 'EQ-PRO-005', 'Proyector Optoma HD146X', 'Optoma', 'HD146X', 'SN-OPHD146-005', 'Proyector de alta definición para conferencias', 'Bodega Central TI'),

-- Cámaras (tipo 3)
(3, 2, 'EQ-CAM-001', 'Cámara Canon EOS Rebel T7', 'Canon', 'EOS Rebel T7', 'SN-CNRT7-001', 'Kit con lente 18-55mm, batería y memoria 64GB', 'Sala Multimedia C-102'),
(3, 4, 'EQ-CAM-002', 'Cámara Sony Alpha a6400', 'Sony', 'ILCE-6400', 'SN-SNA6400-002', 'En calibración de sensor y limpieza óptica', 'Taller de Soporte Técnico'),
(3, 1, 'EQ-CAM-003', 'Cámara Nikon D5600', 'Nikon', 'D5600', 'SN-NKD5600-003', 'Réflex digital con trípode y estuche', 'Sala Multimedia C-102'),
(3, 1, 'EQ-CAM-004', 'Cámara Web Logitech Brio 4K', 'Logitech', 'Brio Ultra HD', 'SN-LGBRIO-004', 'Cámara 4K para transmisiones y grabaciones', 'Bodega Central TI'),

-- Kits electrónicos (tipo 4)
(4, 3, 'EQ-KIT-001', 'Kit Arduino Mega 2560', 'Arduino', 'Mega 2560 Rev3', 'SN-ARDM-001', 'Incluye sensores ultrasónicos, servos y cables', 'Laboratorio de Electrónica E-105'),
(4, 1, 'EQ-KIT-002', 'Kit Raspberry Pi 5 8GB', 'Raspberry Pi', 'Model B 8GB', 'SN-RPI5-002', 'Con fuente oficial, disipador y microSD 64GB', 'Laboratorio de Electrónica E-105'),
(4, 1, 'EQ-KIT-003', 'Osciloscopio Portátil FNIRSI', 'FNIRSI', '1014D Digital', 'SN-FN1014-003', 'Osciloscopio 2 canales con puntas de prueba', 'Laboratorio de Electrónica E-105'),
(4, 5, 'EQ-KIT-004', 'Kit Multímetro y Protoboard', 'Fluke', '115 / Protoboard', 'SN-FLK115-004', 'Equipo dado de baja por falla en placa interna', 'Archivo de Baja');

-- ============================================================
-- 3. RESOLUCIÓN DE IDs PARA SEMILLA COHERENTE
-- ============================================================
SELECT id_usuario INTO @u_carlos  FROM usuarios WHERE correo_institucional = 'carlos.mendez@ues.edu.sv' LIMIT 1;
SELECT id_usuario INTO @u_sofia   FROM usuarios WHERE correo_institucional = 'sofia.ramirez@ues.edu.sv' LIMIT 1;
SELECT id_usuario INTO @u_luis    FROM usuarios WHERE correo_institucional = 'luis.castro@ues.edu.sv' LIMIT 1;
SELECT id_usuario INTO @u_ana     FROM usuarios WHERE correo_institucional = 'ana.martinez@ues.edu.sv' LIMIT 1;
SELECT id_usuario INTO @u_ricardo FROM usuarios WHERE correo_institucional = 'ricardo.lopez@ues.edu.sv' LIMIT 1;
SELECT id_usuario INTO @u_elena   FROM usuarios WHERE correo_institucional = 'elena.flores@ues.edu.sv' LIMIT 1;
SELECT id_usuario INTO @u_jorge   FROM usuarios WHERE correo_institucional = 'jorge.silva@sigpet.edu.sv' LIMIT 1;
SELECT id_usuario INTO @u_marcela FROM usuarios WHERE correo_institucional = 'marcela.reyes@sigpet.edu.sv' LIMIT 1;

SELECT id_equipo INTO @e_lap1 FROM equipos WHERE codigo_inventario = 'EQ-LAP-001' LIMIT 1;
SELECT id_equipo INTO @e_lap2 FROM equipos WHERE codigo_inventario = 'EQ-LAP-002' LIMIT 1;
SELECT id_equipo INTO @e_lap3 FROM equipos WHERE codigo_inventario = 'EQ-LAP-003' LIMIT 1;
SELECT id_equipo INTO @e_lap4 FROM equipos WHERE codigo_inventario = 'EQ-LAP-004' LIMIT 1;
SELECT id_equipo INTO @e_lap5 FROM equipos WHERE codigo_inventario = 'EQ-LAP-005' LIMIT 1;
SELECT id_equipo INTO @e_pro1 FROM equipos WHERE codigo_inventario = 'EQ-PRO-001' LIMIT 1;
SELECT id_equipo INTO @e_pro2 FROM equipos WHERE codigo_inventario = 'EQ-PRO-002' LIMIT 1;
SELECT id_equipo INTO @e_pro3 FROM equipos WHERE codigo_inventario = 'EQ-PRO-003' LIMIT 1;
SELECT id_equipo INTO @e_pro4 FROM equipos WHERE codigo_inventario = 'EQ-PRO-004' LIMIT 1;
SELECT id_equipo INTO @e_cam1 FROM equipos WHERE codigo_inventario = 'EQ-CAM-001' LIMIT 1;
SELECT id_equipo INTO @e_cam3 FROM equipos WHERE codigo_inventario = 'EQ-CAM-003' LIMIT 1;
SELECT id_equipo INTO @e_kit1 FROM equipos WHERE codigo_inventario = 'EQ-KIT-001' LIMIT 1;
SELECT id_equipo INTO @e_kit2 FROM equipos WHERE codigo_inventario = 'EQ-KIT-002' LIMIT 1;
SELECT id_equipo INTO @e_kit3 FROM equipos WHERE codigo_inventario = 'EQ-KIT-003' LIMIT 1;

-- ============================================================
-- 4. SOLICITUDES, PRÉSTAMOS HISTÓRICOS, ACTIVOS Y RESERVAS
-- ============================================================

-- 4.1 Diez préstamos históricos ya devueltos (últimos 25 días)
INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES (@u_carlos, @e_lap3, DATE_SUB(CURDATE(), INTERVAL 24 DAY), DATE_SUB(CURDATE(), INTERVAL 21 DAY), 'Desarrollo de proyecto de Ingeniería de Requerimientos', 0, 'Aprobada', DATE_SUB(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 24 DAY), @u_jorge);
SET @s_h1 = LAST_INSERT_ID();
INSERT INTO `prestamos` (`id_solicitud`, `id_equipo`, `id_usuario`, `fecha_entrega`, `entregado_por`, `autorizado_por`, `condicion_inicial`, `estado_prestamo`, `fecha_devolucion_real`, `condicion_final`, `observaciones`)
VALUES (@s_h1, @e_lap3, @u_carlos, DATE_SUB(NOW(), INTERVAL 24 DAY), @u_jorge, @u_jorge, 'Buen estado general, incluye cargador', 'Devuelto', DATE_SUB(NOW(), INTERVAL 21 DAY), 'Devuelta sin daños', 'Entrega puntual');

INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES (@u_ana, @e_pro3, DATE_SUB(CURDATE(), INTERVAL 22 DAY), DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'Clase magistral de Arquitectura de Sistemas', 1, 'Aprobada', DATE_SUB(NOW(), INTERVAL 23 DAY), DATE_SUB(NOW(), INTERVAL 22 DAY), @u_jorge);
SET @s_h2 = LAST_INSERT_ID();
INSERT INTO `prestamos` (`id_solicitud`, `id_equipo`, `id_usuario`, `fecha_entrega`, `entregado_por`, `autorizado_por`, `condicion_inicial`, `estado_prestamo`, `fecha_devolucion_real`, `condicion_final`, `observaciones`)
VALUES (@s_h2, @e_pro3, @u_ana, DATE_SUB(NOW(), INTERVAL 22 DAY), @u_jorge, @u_jorge, 'Proyector con cable HDMI y control', 'Devuelto', DATE_SUB(NOW(), INTERVAL 20 DAY), 'Buen estado', 'Sin novedades');

INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES (@u_luis, @e_lap4, DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 17 DAY), 'Edición de video demostrativo para feria científica', 0, 'Aprobada', DATE_SUB(NOW(), INTERVAL 21 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY), @u_marcela);
SET @s_h3 = LAST_INSERT_ID();
INSERT INTO `prestamos` (`id_solicitud`, `id_equipo`, `id_usuario`, `fecha_entrega`, `entregado_por`, `autorizado_por`, `condicion_inicial`, `estado_prestamo`, `fecha_devolucion_real`, `condicion_final`, `observaciones`)
VALUES (@s_h3, @e_lap4, @u_luis, DATE_SUB(NOW(), INTERVAL 20 DAY), @u_marcela, @u_marcela, 'MacBook limpia con cargador MagSafe', 'Devuelto', DATE_SUB(NOW(), INTERVAL 15 DAY), 'Buen estado', 'Devuelta con 2 días de retraso por extensión de jornada');

INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES (@u_sofia, @e_cam3, DATE_SUB(CURDATE(), INTERVAL 18 DAY), DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'Registro fotográfico de práctica de campo', 0, 'Aprobada', DATE_SUB(NOW(), INTERVAL 19 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY), @u_jorge);
SET @s_h4 = LAST_INSERT_ID();
INSERT INTO `prestamos` (`id_solicitud`, `id_equipo`, `id_usuario`, `fecha_entrega`, `entregado_por`, `autorizado_por`, `condicion_inicial`, `estado_prestamo`, `fecha_devolucion_real`, `condicion_final`, `observaciones`)
VALUES (@s_h4, @e_cam3, @u_sofia, DATE_SUB(NOW(), INTERVAL 18 DAY), @u_jorge, @u_jorge, 'Cámara, lente y trípode completos', 'Devuelto', DATE_SUB(NOW(), INTERVAL 15 DAY), 'Excelente estado', 'Todo completo');

INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES (@u_ricardo, @e_lap3, DATE_SUB(CURDATE(), INTERVAL 16 DAY), DATE_SUB(CURDATE(), INTERVAL 13 DAY), 'Taller práctico de bases de datos', 1, 'Aprobada', DATE_SUB(NOW(), INTERVAL 17 DAY), DATE_SUB(NOW(), INTERVAL 16 DAY), @u_marcela);
SET @s_h5 = LAST_INSERT_ID();
INSERT INTO `prestamos` (`id_solicitud`, `id_equipo`, `id_usuario`, `fecha_entrega`, `entregado_por`, `autorizado_por`, `condicion_inicial`, `estado_prestamo`, `fecha_devolucion_real`, `condicion_final`, `observaciones`)
VALUES (@s_h5, @e_lap3, @u_ricardo, DATE_SUB(NOW(), INTERVAL 16 DAY), @u_marcela, @u_marcela, 'Equipo verificado en recepción', 'Devuelto', DATE_SUB(NOW(), INTERVAL 13 DAY), 'Buen estado', 'Sin observaciones');

INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES (@u_elena, @e_pro4, DATE_SUB(CURDATE(), INTERVAL 15 DAY), DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'Ponencia académica en auditorio', 1, 'Aprobada', DATE_SUB(NOW(), INTERVAL 16 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY), @u_jorge);
SET @s_h6 = LAST_INSERT_ID();
INSERT INTO `prestamos` (`id_solicitud`, `id_equipo`, `id_usuario`, `fecha_entrega`, `entregado_por`, `autorizado_por`, `condicion_inicial`, `estado_prestamo`, `fecha_devolucion_real`, `condicion_final`, `observaciones`)
VALUES (@s_h6, @e_pro4, @u_elena, DATE_SUB(NOW(), INTERVAL 15 DAY), @u_jorge, @u_jorge, 'Proyector Full HD operativo', 'Devuelto', DATE_SUB(NOW(), INTERVAL 12 DAY), 'Buen estado', 'Entrega conforme');

INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES (@u_luis, @e_pro2, DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_SUB(CURDATE(), INTERVAL 11 DAY), 'Exposición grupal de Sistemas Operativos', 0, 'Aprobada', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY), @u_jorge);
SET @s_h7 = LAST_INSERT_ID();
INSERT INTO `prestamos` (`id_solicitud`, `id_equipo`, `id_usuario`, `fecha_entrega`, `entregado_por`, `autorizado_por`, `condicion_inicial`, `estado_prestamo`, `fecha_devolucion_real`, `condicion_final`, `observaciones`)
VALUES (@s_h7, @e_pro2, @u_luis, DATE_SUB(NOW(), INTERVAL 14 DAY), @u_jorge, @u_jorge, 'Funcionando correctamente', 'Devuelto', DATE_SUB(NOW(), INTERVAL 11 DAY), 'Presenta ruido anormal en ventilador interno', 'Se envió el proyector a mantenimiento preventivo');

INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES (@u_carlos, @e_kit2, DATE_SUB(CURDATE(), INTERVAL 12 DAY), DATE_SUB(CURDATE(), INTERVAL 9 DAY), 'Pruebas de servidor embebido con Raspberry Pi', 0, 'Aprobada', DATE_SUB(NOW(), INTERVAL 13 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY), @u_marcela);
SET @s_h8 = LAST_INSERT_ID();
INSERT INTO `prestamos` (`id_solicitud`, `id_equipo`, `id_usuario`, `fecha_entrega`, `entregado_por`, `autorizado_por`, `condicion_inicial`, `estado_prestamo`, `fecha_devolucion_real`, `condicion_final`, `observaciones`)
VALUES (@s_h8, @e_kit2, @u_carlos, DATE_SUB(NOW(), INTERVAL 12 DAY), @u_marcela, @u_marcela, 'Kit completo en caja organizadora', 'Devuelto', DATE_SUB(NOW(), INTERVAL 5 DAY), 'Buen estado', 'Devuelto con 4 días de atraso');

INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES (@u_sofia, @e_lap5, DATE_SUB(CURDATE(), INTERVAL 9 DAY), DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'Simulación de redes y modelado 3D', 0, 'Aprobada', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY), @u_jorge);
SET @s_h9 = LAST_INSERT_ID();
INSERT INTO `prestamos` (`id_solicitud`, `id_equipo`, `id_usuario`, `fecha_entrega`, `entregado_por`, `autorizado_por`, `condicion_inicial`, `estado_prestamo`, `fecha_devolucion_real`, `condicion_final`, `observaciones`)
VALUES (@s_h9, @e_lap5, @u_sofia, DATE_SUB(NOW(), INTERVAL 9 DAY), @u_jorge, @u_jorge, 'Laptop sin rayones, batería al 100%', 'Devuelto', DATE_SUB(NOW(), INTERVAL 6 DAY), 'Buen estado', 'Devolución puntual');

INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES (@u_ana, @e_kit3, DATE_SUB(CURDATE(), INTERVAL 8 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Laboratorio de señales y mediciones eléctricas', 1, 'Aprobada', DATE_SUB(NOW(), INTERVAL 9 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), @u_marcela);
SET @s_h10 = LAST_INSERT_ID();
INSERT INTO `prestamos` (`id_solicitud`, `id_equipo`, `id_usuario`, `fecha_entrega`, `entregado_por`, `autorizado_por`, `condicion_inicial`, `estado_prestamo`, `fecha_devolucion_real`, `condicion_final`, `observaciones`)
VALUES (@s_h10, @e_kit3, @u_ana, DATE_SUB(NOW(), INTERVAL 8 DAY), @u_marcela, @u_marcela, 'Osciloscopio calibrado con dos sondas', 'Devuelto', DATE_SUB(NOW(), INTERVAL 5 DAY), 'Buen estado', 'Todo en orden');

-- 4.2 Tres préstamos ACTIVOS (1 atrasado, 1 próximo a vencer, 1 vigente)
-- Atrasado (Carlos, venció hace 3 días) -> dispara alertas y bloqueo de nuevas solicitudes
INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES (@u_carlos, @e_lap1, DATE_SUB(CURDATE(), INTERVAL 6 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Prácticas de desarrollo web y compilación', 0, 'Aprobada', DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), @u_jorge);
SET @s_a1 = LAST_INSERT_ID();
INSERT INTO `prestamos` (`id_solicitud`, `id_equipo`, `id_usuario`, `fecha_entrega`, `entregado_por`, `autorizado_por`, `condicion_inicial`, `estado_prestamo`)
VALUES (@s_a1, @e_lap1, @u_carlos, DATE_SUB(NOW(), INTERVAL 6 DAY), @u_jorge, @u_jorge, 'Laptop Dell en óptimas condiciones con cargador', 'Prestado');
SET @p_atrasado = LAST_INSERT_ID();

-- Próximo a vencer (Sofía, vence mañana) -> dispara alertas de vencimiento próximo
INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES (@u_sofia, @e_pro1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Defensa parcial de proyecto de cátedra', 0, 'Aprobada', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), @u_marcela);
SET @s_a2 = LAST_INSERT_ID();
INSERT INTO `prestamos` (`id_solicitud`, `id_equipo`, `id_usuario`, `fecha_entrega`, `entregado_por`, `autorizado_por`, `condicion_inicial`, `estado_prestamo`)
VALUES (@s_a2, @e_pro1, @u_sofia, DATE_SUB(NOW(), INTERVAL 2 DAY), @u_marcela, @u_marcela, 'Proyector Epson y maletín completos', 'Prestado');
SET @p_proximo = LAST_INSERT_ID();

-- Vigente normal (Docente Ana, vence en 4 días)
INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES (@u_ana, @e_kit1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'Práctica de robótica y automatización', 1, 'Aprobada', DATE_SUB(NOW(), INTERVAL 1 DAY), NOW(), @u_jorge);
SET @s_a3 = LAST_INSERT_ID();
INSERT INTO `prestamos` (`id_solicitud`, `id_equipo`, `id_usuario`, `fecha_entrega`, `entregado_por`, `autorizado_por`, `condicion_inicial`, `estado_prestamo`)
VALUES (@s_a3, @e_kit1, @u_ana, NOW(), @u_jorge, @u_jorge, 'Kit Arduino sellado con todos sus módulos', 'Prestado');

-- 4.3 Dos reservas aprobadas pendientes de entrega (equipos en estado Reservado)
INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES (@u_ricardo, @e_lap2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Evaluación de laboratorio de algoritmos', 1, 'Aprobada', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), @u_jorge);
SET @s_r1 = LAST_INSERT_ID();

INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES (@u_luis, @e_cam1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'Documentación audiovisual de prototipo', 0, 'Aprobada', DATE_SUB(NOW(), INTERVAL 1 DAY), NOW(), @u_marcela);
SET @s_r2 = LAST_INSERT_ID();

-- 4.4 Cuatro solicitudes Pendientes (2 docentes prioritarias + 2 estudiantes)
INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`)
VALUES
(@u_ana, @e_lap3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Seminario de diseño de software para docentes', 1, 'Pendiente'),
(@u_ricardo, @e_cam3, DATE_ADD(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 6 DAY), 'Grabación de material didáctico para aula virtual', 1, 'Pendiente'),
(@u_luis, @e_lap4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Presentación interactiva de proyecto final', 0, 'Pendiente'),
(@u_sofia, @e_kit2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'Montaje de prototipo IoT para laboratorio', 0, 'Pendiente');

-- 4.5 Dos solicitudes Rechazadas y una Cancelada
INSERT INTO `solicitudes_prestamo` (`id_usuario`, `id_equipo`, `fecha_inicio`, `fecha_devolucion_prevista`, `proposito`, `es_prioridad`, `estado_solicitud`, `motivo_rechazo`, `fecha_solicitud`, `fecha_decision`, `decidido_por`)
VALUES
(@u_luis, @e_pro3, DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'Actividad recreativa fuera de horario académico', 0, 'Rechazada', 'El uso solicitado no corresponde a una actividad académica autorizada.', DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY), @u_jorge),
(@u_carlos, @e_lap4, DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Edición personal de fotografías', 0, 'Rechazada', 'Prioridad asignada a prácticas académicas programadas en ese período.', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), @u_marcela),
(@u_sofia, @e_lap3, DATE_ADD(CURDATE(), INTERVAL 6 DAY), DATE_ADD(CURDATE(), INTERVAL 8 DAY), 'Revisión grupal de documentación', 0, 'Cancelada', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), NULL);

-- ============================================================
-- 5. BITÁCORA HISTÓRICA INICIAL
-- ============================================================
INSERT INTO `bitacora` (`id_usuario`, `modulo`, `accion`, `entidad`, `id_entidad`, `detalle`, `fecha_registro`)
VALUES
(@u_jorge, 'Inventario', 'Registro de equipo', 'equipos', @e_lap1, 'Se registró el equipo EQ-LAP-001 (Laptop Dell Latitude 5430) en estado Disponible.', DATE_SUB(NOW(), INTERVAL 26 DAY)),
(@u_carlos, 'Solicitudes', 'Creación de solicitud', 'solicitudes_prestamo', @s_h1, 'Solicitud registrada para equipo EQ-LAP-003 (Laptop HP ProBook 450 G9).', DATE_SUB(NOW(), INTERVAL 25 DAY)),
(@u_jorge, 'Préstamos', 'Aprobación de solicitud', 'solicitudes_prestamo', @s_h1, 'Se aprobó la solicitud y el equipo pasó de Disponible a Reservado.', DATE_SUB(NOW(), INTERVAL 24 DAY)),
(@u_jorge, 'Préstamos', 'Entrega de equipo', 'prestamos', 1, 'Entrega física registrada de EQ-LAP-003 a Carlos Eduardo Méndez Rivera.', DATE_SUB(NOW(), INTERVAL 24 DAY)),
(@u_jorge, 'Devoluciones', 'Devolución de equipo', 'prestamos', 1, 'Devolución registrada sin daños. Equipo EQ-LAP-003 volvió a Disponible.', DATE_SUB(NOW(), INTERVAL 21 DAY)),
(@u_jorge, 'Devoluciones', 'Devolución de equipo', 'prestamos', 7, 'Devolución registrada con observación de ruido en ventilador. Equipo EQ-PRO-002 pasó a En mantenimiento.', DATE_SUB(NOW(), INTERVAL 11 DAY)),
(@u_jorge, 'Inventario', 'Cambio de estado de equipo', 'equipos', @e_pro2, 'Equipo EQ-PRO-002 mantenido en estado En mantenimiento para revisión técnica.', DATE_SUB(NOW(), INTERVAL 11 DAY)),
(@u_jorge, 'Préstamos', 'Rechazo de solicitud', 'solicitudes_prestamo', NULL, 'Solicitud rechazada por no corresponder a actividad académica autorizada.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(@u_carlos, 'Solicitudes', 'Creación de solicitud', 'solicitudes_prestamo', @s_a1, 'Solicitud registrada para equipo EQ-LAP-001 (Laptop Dell Latitude 5430).', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(@u_jorge, 'Préstamos', 'Entrega de equipo', 'prestamos', @p_atrasado, 'Se entregó el equipo EQ-LAP-001 a Carlos Eduardo Méndez Rivera.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(@u_marcela, 'Préstamos', 'Entrega de equipo', 'prestamos', @p_proximo, 'Se entregó el equipo EQ-PRO-001 a Sofía Alejandra Ramírez Cruz.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@u_jorge, 'Préstamos', 'Aprobación de solicitud', 'solicitudes_prestamo', @s_r1, 'Se aprobó la reserva prioritaria del docente Ricardo Javier López Navarro para EQ-LAP-002.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(@u_marcela, 'Préstamos', 'Aprobación de solicitud', 'solicitudes_prestamo', @s_r2, 'Se aprobó la reserva del estudiante Luis Fernando Castro Mejía para EQ-CAM-001.', NOW()),
(@u_jorge, 'Préstamos', 'Entrega de equipo', 'prestamos', @p_proximo + 1, 'Se entregó el equipo EQ-KIT-001 a la docente Ana Lucía Martínez Gómez.', NOW());

COMMIT;
