-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 29-09-2026 a las 20:52:16
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `rosea_boutique`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pedido`
--

CREATE TABLE `detalle_pedido` (
  `id_detalle` int(11) NOT NULL,
  `id_pedido` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_pedido`
--

INSERT INTO `detalle_pedido` (`id_detalle`, `id_pedido`, `id_producto`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(1, 1, 1, 1, 1850.00, 1850.00),
(2, 2, 1, 1, 1900.00, 1900.00),
(3, 3, 1, 1, 1900.00, 1900.00),
(4, 4, 1, 1, 1900.00, 1900.00),
(5, 5, 1, 1, 1900.00, 1900.00),
(6, 6, 1, 1, 1900.00, 1900.00),
(7, 7, 1, 2, 1900.00, 3800.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id_pedido` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_pedido` timestamp NOT NULL DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL,
  `estado` enum('pendiente','procesando','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
  `telefono` varchar(20) DEFAULT NULL,
  `provincia` varchar(100) DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `metodo_entrega` enum('delivery','retiro') DEFAULT NULL,
  `metodo_pago` enum('efectivo','transferencia') DEFAULT NULL,
  `empresa_envio` varchar(100) DEFAULT NULL,
  `comprobante_pago` varchar(255) DEFAULT NULL,
  `estado_pago` enum('pendiente','verificado','rechazado') NOT NULL DEFAULT 'pendiente',
  `fecha_comprobante` datetime DEFAULT NULL,
  `fecha_envio` datetime DEFAULT NULL,
  `codigo_seguimiento` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id_pedido`, `id_usuario`, `fecha_pedido`, `total`, `estado`, `telefono`, `provincia`, `ciudad`, `direccion`, `referencia`, `metodo_entrega`, `metodo_pago`, `empresa_envio`, `comprobante_pago`, `estado_pago`, `fecha_comprobante`, `fecha_envio`, `codigo_seguimiento`) VALUES
(1, 1, '2026-09-27 21:49:12', 1850.00, 'pendiente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL),
(2, 2, '2026-09-28 18:00:46', 1900.00, 'procesando', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pendiente', NULL, NULL, NULL),
(3, 2, '2026-09-28 22:14:10', 1900.00, 'entregado', '8294943760', 'San Cristobal', 'Madre Vieja Sur', '13 edificio alba\r\ndepartamento 3C', 'Colmado Yori', 'delivery', 'efectivo', NULL, NULL, 'pendiente', NULL, '2026-09-29 00:56:08', 'ROSEA-000003'),
(4, 1, '2026-09-29 11:22:02', 1900.00, 'entregado', '8294943760', 'San Cristobal', 'Madre Vieja Sur', '13 edificio alba\r\ndepartamento 3C', NULL, 'delivery', 'transferencia', NULL, 'pedido_4_20260929_132334_bdaff1eae2219b2f.jpg', 'verificado', '2026-09-29 07:23:34', '2026-09-29 13:51:12', 'ROSEA-000004'),
(5, 1, '2026-09-29 11:56:45', 1900.00, 'entregado', '8294943760', 'San Cristobal', 'Madre Vieja Sur', '13 edificio alba\r\ndepartamento 3C', 'gtr', 'delivery', 'transferencia', 'VIMENCA', 'pedido_5_20260929_140552_ab1812cf4317d052.png', 'verificado', '2026-09-29 08:05:52', '2026-09-29 08:22:00', 'ROSEA-000005'),
(6, 1, '2026-09-29 16:53:12', 1900.00, 'cancelado', '8294943760', 'San Cristobal', 'Madre Vieja Sur', '13 edificio alba\r\ndepartamento 3C', 'Parque piedras vivas', 'delivery', 'efectivo', NULL, NULL, 'pendiente', NULL, NULL, NULL),
(7, 1, '2026-09-29 18:25:10', 3800.00, 'cancelado', '8294943760', 'San Cristobal', 'Madre Vieja Sur', '13 edificio alba\r\ndepartamento 3C', NULL, 'delivery', 'efectivo', 'VIMENCA', NULL, 'pendiente', NULL, '2026-09-29 14:25:00', 'ROSEA-000007');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id_producto` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `categoria` varchar(100) DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id_producto`, `nombre`, `descripcion`, `precio`, `stock`, `categoria`, `imagen`, `disponible`, `fecha_creacion`) VALUES
(1, 'Vestido Rosa Elegance', 'Vestido elegante de estilo coquette en tono rosa.', 1900.00, 5, 'Vestidos', 'vestido-rosa.jpg', 1, '2026-09-27 13:54:17');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('cliente','admin') NOT NULL DEFAULT 'cliente',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `apellido`, `email`, `password`, `rol`, `fecha_registro`) VALUES
(1, 'Frank', 'Quezada', 'admin@rosea.com', '$2y$10$mhTYo8MV98wYCLJ67v7cG.9PQbhbky.006OM0A8qybGBOgRksIDeG', 'admin', '2026-09-27 12:37:58'),
(2, 'Maicol', 'Quezada', 'Maicolquezada@gmail.com', '$2y$10$kcyoEI02bYQbApGiOtZWA.WZf8aIvdNUvqqAnT.jx7xkx5Lbw6MO2', 'cliente', '2026-09-28 17:59:42');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `fk_detalle_pedido` (`id_pedido`),
  ADD KEY `fk_detalle_producto` (`id_producto`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id_pedido`),
  ADD KEY `fk_pedido_usuario` (`id_usuario`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id_producto`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id_pedido` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  ADD CONSTRAINT `fk_detalle_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_pedido_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
