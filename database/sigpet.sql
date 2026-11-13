-- ============================================================
-- SIGPET - Sistema de Gestión de Préstamos de Equipos Tecnológicos
-- Base de datos: sigpet
-- Incrementos 1 y 2
-- ============================================================

-- ============================================================
-- CREACIÓN Y SELECCIÓN DE LA BASE DE DATOS
-- ============================================================

CREATE DATABASE IF NOT EXISTS `sigpet`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `sigpet`;

-- ============================================================
-- CONFIGURACIÓN INICIAL
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

START TRANSACTION;

-- ============================================================
-- TABLA: roles
-- ============================================================

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `roles`
(`id_rol`, `nombre_rol`, `descripcion`)
VALUES
(1, 'Estudiante', 'Usuario que solicita préstamos de equipos'),
(2, 'Docente', 'Personal docente de la institución'),
(3, 'Encargado de préstamos', 'Responsable de gestionar los préstamos'),
(4, 'Administrador', 'Responsable de administrar el sistema'),
(5, 'Coordinador académico', 'Responsable de supervisar las actividades académicas');

-- ============================================================
-- TABLA: tipos_equipo
-- ============================================================

CREATE TABLE `tipos_equipo` (
  `id_tipo` int(11) NOT NULL,
  `nombre_tipo` varchar(80) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tipos_equipo`
(`id_tipo`, `nombre_tipo`, `descripcion`)
VALUES
(1, 'Laptop', 'Computadoras portátiles'),
(2, 'Proyector', 'Equipos de proyección'),
(3, 'Cámara', 'Equipos de captura de imagen y video'),
(4, 'Kit electrónico', 'Componentes y herramientas electrónicas');

-- ============================================================
-- TABLA: estados_equipo
-- ============================================================

CREATE TABLE `estados_equipo` (
  `id_estado` int(11) NOT NULL,
  `nombre_estado` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `estados_equipo`
(`id_estado`, `nombre_estado`, `descripcion`)
VALUES
(1, 'Disponible', 'Equipo disponible para ser solicitado'),
(2, 'Reservado', 'Equipo que tiene una reserva registrada'),
(3, 'Prestado', 'Equipo entregado a un usuario'),
(4, 'En mantenimiento', 'Equipo que requiere revisión o reparación'),
(5, 'Dado de baja', 'Equipo retirado del inventario activo');

-- ============================================================
-- TABLA: usuarios
-- ============================================================

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `correo_institucional` varchar(150) NOT NULL,
  `codigo_institucional` varchar(30) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `usuarios`
(
  `id_usuario`,
  `id_rol`,
  `nombres`,
  `apellidos`,
  `correo_institucional`,
  `codigo_institucional`,
  `password`,
  `activo`,
  `fecha_registro`
)
VALUES
(
  1,
  4,
  'admin',
  'SIGPET',
  'admin@sigpet.edu',
  'ADM001',
  '$2y$10$wJjUWEETzVG86umAsPVlHuS/rvZnXZ8b571N49StZBazNUwViTSmi',
  1,
  '2026-11-03 01:12:51'
),
(
  2,
  2,
  'David Fernando',
  'Murgas Hernández',
  'mh25061@ues.edu.sv',
  'MH25061',
  '$2y$10$oodsY/cd03kxxanjnOlkp.H2Hjw16nE1g8Mb8Nf3Z.d8hiJNfKhnO',
  1,
  '2026-11-03 03:58:46'
);

-- ============================================================
-- TABLA: equipos
-- ============================================================

CREATE TABLE `equipos` (
  `id_equipo` int(11) NOT NULL,
  `id_tipo` int(11) NOT NULL,
  `id_estado` int(11) NOT NULL,
  `codigo_inventario` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `marca` varchar(80) DEFAULT NULL,
  `modelo` varchar(80) DEFAULT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `ubicacion` varchar(150) DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `equipos`
(
  `id_equipo`,
  `id_tipo`,
  `id_estado`,
  `codigo_inventario`,
  `nombre`,
  `marca`,
  `modelo`,
  `numero_serie`,
  `descripcion`,
  `ubicacion`,
  `fecha_registro`
)
VALUES
(
  1,
  4,
  1,
  '123',
  'Laptop',
  'Sony',
  '1234',
  '1234',
  'adsadas',
  'awdasd',
  '2026-11-03 04:27:33'
);

-- ============================================================
-- TABLA: solicitudes_prestamo
-- Incremento 2
-- ============================================================

CREATE TABLE `solicitudes_prestamo` (
  `id_solicitud` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_equipo` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_devolucion_prevista` date NOT NULL,
  `proposito` varchar(500) NOT NULL,
  `estado_solicitud` varchar(20) NOT NULL DEFAULT 'Pendiente',
  `motivo_rechazo` varchar(500) DEFAULT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_decision` datetime DEFAULT NULL,
  `decidido_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLA: prestamos
-- Incremento 2
-- ============================================================

CREATE TABLE `prestamos` (
  `id_prestamo` int(11) NOT NULL,
  `id_solicitud` int(11) NOT NULL,
  `id_equipo` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_entrega` datetime NOT NULL,
  `entregado_por` int(11) NOT NULL,
  `autorizado_por` int(11) NOT NULL,
  `condicion_inicial` varchar(500) NOT NULL,
  `estado_prestamo` varchar(20) NOT NULL DEFAULT 'Prestado',
  `fecha_devolucion_real` datetime DEFAULT NULL,
  `condicion_final` varchar(500) DEFAULT NULL,
  `observaciones` varchar(1000) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- ÍNDICES Y CLAVES PRIMARIAS
-- ============================================================

ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre_rol` (`nombre_rol`);

ALTER TABLE `tipos_equipo`
  ADD PRIMARY KEY (`id_tipo`),
  ADD UNIQUE KEY `nombre_tipo` (`nombre_tipo`);

ALTER TABLE `estados_equipo`
  ADD PRIMARY KEY (`id_estado`),
  ADD UNIQUE KEY `nombre_estado` (`nombre_estado`);

ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `correo_institucional` (`correo_institucional`),
  ADD UNIQUE KEY `codigo_institucional` (`codigo_institucional`),
  ADD KEY `FK_USUARIO_ROL` (`id_rol`);

ALTER TABLE `equipos`
  ADD PRIMARY KEY (`id_equipo`),
  ADD UNIQUE KEY `codigo_inventario` (`codigo_inventario`),
  ADD UNIQUE KEY `numero_serie` (`numero_serie`),
  ADD KEY `FK_EQUIPO_TIPO` (`id_tipo`),
  ADD KEY `FK_EQUIPO_ESTADO` (`id_estado`);

ALTER TABLE `solicitudes_prestamo`
  ADD PRIMARY KEY (`id_solicitud`),
  ADD KEY `idx_solicitudes_usuario` (`id_usuario`),
  ADD KEY `idx_solicitudes_equipo` (`id_equipo`),
  ADD KEY `idx_solicitudes_estado` (`estado_solicitud`),
  ADD KEY `idx_solicitudes_fechas` (`fecha_inicio`, `fecha_devolucion_prevista`),
  ADD KEY `idx_solicitudes_decidido_por` (`decidido_por`);

ALTER TABLE `prestamos`
  ADD PRIMARY KEY (`id_prestamo`),
  ADD UNIQUE KEY `uq_prestamo_solicitud` (`id_solicitud`),
  ADD KEY `idx_prestamos_equipo` (`id_equipo`),
  ADD KEY `idx_prestamos_usuario` (`id_usuario`),
  ADD KEY `idx_prestamos_entregado_por` (`entregado_por`),
  ADD KEY `idx_prestamos_autorizado_por` (`autorizado_por`),
  ADD KEY `idx_prestamos_estado` (`estado_prestamo`),
  ADD KEY `idx_prestamos_fecha_devolucion` (`fecha_devolucion_real`);

-- ============================================================
-- AUTO_INCREMENT
-- ============================================================

ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `tipos_equipo`
  MODIFY `id_tipo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `estados_equipo`
  MODIFY `id_estado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `equipos`
  MODIFY `id_equipo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `solicitudes_prestamo`
  MODIFY `id_solicitud` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `prestamos`
  MODIFY `id_prestamo` int(11) NOT NULL AUTO_INCREMENT;

-- ============================================================
-- CLAVES FORÁNEAS
-- ============================================================

ALTER TABLE `usuarios`
  ADD CONSTRAINT `FK_USUARIO_ROL`
  FOREIGN KEY (`id_rol`)
  REFERENCES `roles` (`id_rol`)
  ON UPDATE CASCADE
  ON DELETE RESTRICT;

ALTER TABLE `equipos`
  ADD CONSTRAINT `FK_EQUIPO_TIPO`
  FOREIGN KEY (`id_tipo`)
  REFERENCES `tipos_equipo` (`id_tipo`)
  ON UPDATE CASCADE
  ON DELETE RESTRICT;

ALTER TABLE `equipos`
  ADD CONSTRAINT `FK_EQUIPO_ESTADO`
  FOREIGN KEY (`id_estado`)
  REFERENCES `estados_equipo` (`id_estado`)
  ON UPDATE CASCADE
  ON DELETE RESTRICT;

ALTER TABLE `solicitudes_prestamo`
  ADD CONSTRAINT `FK_SOLICITUD_USUARIO`
  FOREIGN KEY (`id_usuario`)
  REFERENCES `usuarios` (`id_usuario`)
  ON UPDATE CASCADE
  ON DELETE RESTRICT;

ALTER TABLE `solicitudes_prestamo`
  ADD CONSTRAINT `FK_SOLICITUD_EQUIPO`
  FOREIGN KEY (`id_equipo`)
  REFERENCES `equipos` (`id_equipo`)
  ON UPDATE CASCADE
  ON DELETE RESTRICT;

ALTER TABLE `solicitudes_prestamo`
  ADD CONSTRAINT `FK_SOLICITUD_DECISOR`
  FOREIGN KEY (`decidido_por`)
  REFERENCES `usuarios` (`id_usuario`)
  ON UPDATE CASCADE
  ON DELETE SET NULL;

ALTER TABLE `prestamos`
  ADD CONSTRAINT `FK_PRESTAMO_SOLICITUD`
  FOREIGN KEY (`id_solicitud`)
  REFERENCES `solicitudes_prestamo` (`id_solicitud`)
  ON UPDATE CASCADE
  ON DELETE RESTRICT;

ALTER TABLE `prestamos`
  ADD CONSTRAINT `FK_PRESTAMO_EQUIPO`
  FOREIGN KEY (`id_equipo`)
  REFERENCES `equipos` (`id_equipo`)
  ON UPDATE CASCADE
  ON DELETE RESTRICT;

ALTER TABLE `prestamos`
  ADD CONSTRAINT `FK_PRESTAMO_USUARIO`
  FOREIGN KEY (`id_usuario`)
  REFERENCES `usuarios` (`id_usuario`)
  ON UPDATE CASCADE
  ON DELETE RESTRICT;

ALTER TABLE `prestamos`
  ADD CONSTRAINT `FK_PRESTAMO_ENTREGADO_POR`
  FOREIGN KEY (`entregado_por`)
  REFERENCES `usuarios` (`id_usuario`)
  ON UPDATE CASCADE
  ON DELETE RESTRICT;

ALTER TABLE `prestamos`
  ADD CONSTRAINT `FK_PRESTAMO_AUTORIZADO_POR`
  FOREIGN KEY (`autorizado_por`)
  REFERENCES `usuarios` (`id_usuario`)
  ON UPDATE CASCADE
  ON DELETE RESTRICT;

-- ============================================================
-- FINALIZACIÓN
-- ============================================================

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;