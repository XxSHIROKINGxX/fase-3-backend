<?php

session_start();

require_once __DIR__ . '/config/conexion.php';


/* =========================================================
   PROTEGER PANEL DE ADMINISTRACIÓN
========================================================= */

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

if (
    !isset($_SESSION['rol']) ||
    strtolower($_SESSION['rol']) !== 'admin'
) {
    header("Location: productos.php");
    exit;
}


/* =========================================================
   FUNCIÓN PARA ESCAPAR TEXTO
========================================================= */

function e($valor)
{
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   VALORES INICIALES
========================================================= */

$totalProductos = 0;
$totalUsuarios = 0;
$totalPedidos = 0;

$totalVentas = 0;

$pedidosPendientes = 0;
$pedidosProcesando = 0;
$pedidosEnviados = 0;
$pedidosEntregados = 0;
$pedidosCancelados = 0;

$productosStockBajo = 0;
$productosAgotados = 0;
$totalUnidades = 0;

$ultimosPedidos = [];


/* =========================================================
   OBTENER ESTADÍSTICAS
========================================================= */

try {


    /* =====================================================
       TOTAL DE PRODUCTOS
    ===================================================== */

    $stmt = $conexion->query("
        SELECT COUNT(*)
        FROM productos
    ");

    $totalProductos = (int)$stmt->fetchColumn();


    /* =====================================================
       TOTAL DE UNIDADES EN INVENTARIO
    ===================================================== */

    $stmt = $conexion->query("
        SELECT COALESCE(SUM(stock), 0)
        FROM productos
    ");

    $totalUnidades = (int)$stmt->fetchColumn();


    /* =====================================================
       PRODUCTOS CON STOCK BAJO

       Consideramos stock bajo:
       entre 1 y 3 unidades.
    ===================================================== */

    $stmt = $conexion->query("
        SELECT COUNT(*)
        FROM productos
        WHERE stock BETWEEN 1 AND 3
    ");

    $productosStockBajo = (int)$stmt->fetchColumn();


    /* =====================================================
       PRODUCTOS AGOTADOS
    ===================================================== */

    $stmt = $conexion->query("
        SELECT COUNT(*)
        FROM productos
        WHERE stock <= 0
    ");

    $productosAgotados = (int)$stmt->fetchColumn();


    /* =====================================================
       TOTAL DE USUARIOS
    ===================================================== */

    $stmt = $conexion->query("
        SELECT COUNT(*)
        FROM usuarios
    ");

    $totalUsuarios = (int)$stmt->fetchColumn();


    /* =====================================================
       TOTAL DE PEDIDOS
    ===================================================== */

    $stmt = $conexion->query("
        SELECT COUNT(*)
        FROM pedidos
    ");

    $totalPedidos = (int)$stmt->fetchColumn();


    /* =====================================================
       PEDIDOS PENDIENTES
    ===================================================== */

    $stmt = $conexion->query("
        SELECT COUNT(*)
        FROM pedidos
        WHERE estado = 'pendiente'
    ");

    $pedidosPendientes = (int)$stmt->fetchColumn();


    /* =====================================================
       PEDIDOS EN PROCESAMIENTO
    ===================================================== */

    $stmt = $conexion->query("
        SELECT COUNT(*)
        FROM pedidos
        WHERE estado = 'procesando'
    ");

    $pedidosProcesando = (int)$stmt->fetchColumn();


    /* =====================================================
       PEDIDOS ENVIADOS
    ===================================================== */

    $stmt = $conexion->query("
        SELECT COUNT(*)
        FROM pedidos
        WHERE estado = 'enviado'
    ");

    $pedidosEnviados = (int)$stmt->fetchColumn();


    /* =====================================================
       PEDIDOS ENTREGADOS
    ===================================================== */

    $stmt = $conexion->query("
        SELECT COUNT(*)
        FROM pedidos
        WHERE estado = 'entregado'
    ");

    $pedidosEntregados = (int)$stmt->fetchColumn();


    /* =====================================================
       PEDIDOS CANCELADOS
    ===================================================== */

    $stmt = $conexion->query("
        SELECT COUNT(*)
        FROM pedidos
        WHERE estado = 'cancelado'
    ");

    $pedidosCancelados = (int)$stmt->fetchColumn();


    /* =====================================================
       VENTAS REALES

       Solamente contamos pedidos entregados.
       Los pendientes, procesando, enviados o cancelados
       NO forman parte del total vendido.
    ===================================================== */

    $stmt = $conexion->query("
        SELECT COALESCE(SUM(total), 0)
        FROM pedidos
        WHERE estado = 'entregado'
    ");

    $totalVentas = (float)$stmt->fetchColumn();


    /* =====================================================
       ÚLTIMOS 5 PEDIDOS
    ===================================================== */

    $stmt = $conexion->query("
        SELECT
            p.id_pedido,
            p.fecha_pedido,
            p.total,
            p.estado,
            p.metodo_pago,
            p.estado_pago,

            u.nombre,
            u.apellido,
            u.email

        FROM pedidos p

        INNER JOIN usuarios u
            ON p.id_usuario = u.id_usuario

        ORDER BY p.id_pedido DESC

        LIMIT 5
    ");

    $ultimosPedidos = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


} catch (PDOException $e) {

    die(
        "Error al cargar el panel administrativo: " .
        $e->getMessage()
    );
}


/* =========================================================
   NOMBRE DEL ADMINISTRADOR
========================================================= */

$nombreAdmin = trim(
    ($_SESSION['nombre'] ?? '') . ' ' .
    ($_SESSION['apellido'] ?? '')
);

if ($nombreAdmin === '') {
    $nombreAdmin = 'Administrador';
}


/* =========================================================
   FUNCIONES VISUALES DE ESTADO
========================================================= */

function nombreEstado($estado)
{
    switch (strtolower((string)$estado)) {

        case 'pendiente':
            return 'Pendiente';

        case 'procesando':
            return 'Procesando';

        case 'enviado':
            return 'Enviado';

        case 'entregado':
            return 'Entregado';

        case 'cancelado':
            return 'Cancelado';

        default:
            return ucfirst((string)$estado);
    }
}


function claseEstado($estado)
{
    switch (strtolower((string)$estado)) {

        case 'pendiente':
            return 'estado-pendiente';

        case 'procesando':
            return 'estado-procesando';

        case 'enviado':
            return 'estado-enviado';

        case 'entregado':
            return 'estado-entregado';

        case 'cancelado':
            return 'estado-cancelado';

        default:
            return '';
    }
}


function nombrePago($metodo)
{
    switch (strtolower((string)$metodo)) {

        case 'efectivo':
            return 'Efectivo';

        case 'transferencia':
            return 'Transferencia';

        default:
            return 'No especificado';
    }
}


function nombreEstadoPago($estado)
{
    switch (strtolower((string)$estado)) {

        case 'pendiente':
            return 'Pendiente';

        case 'verificado':
            return 'Verificado';

        case 'rechazado':
            return 'Rechazado';

        default:
            return 'No especificado';
    }
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Panel Admin | Roséa Boutique</title>


    <style>

        /* =====================================================
           GENERAL
        ===================================================== */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: #fff8fa;
            color: #4f2f35;
            font-family: Arial, Helvetica, sans-serif;
        }

        a {
            color: inherit;
        }


        /* =====================================================
           HERO
        ===================================================== */

        .hero {
            background: #fff0f4;
            text-align: center;
            padding: 55px 20px;
        }

        .hero h1 {
            font-family: Georgia, serif;
            font-size: 44px;
            color: #c37382;
            margin-bottom: 12px;
        }

        .hero p {
            color: #a85e69;
            font-size: 16px;
        }


        /* =====================================================
           MENÚ
        ===================================================== */

        .navbar {
            background: white;
            border-bottom: 1px solid #f1d4da;
        }

        .navbar-contenido {
            max-width: 1200px;
            margin: auto;
            padding: 22px 20px;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-family: Georgia, serif;
            font-size: 27px;
            font-weight: bold;
            color: #c37382;
        }

        .menu {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .menu a {
            text-decoration: none;
            color: #703b45;
            font-size: 14px;
        }

        .menu a:hover {
            color: #c37382;
        }


        /* =====================================================
           CONTENIDO
        ===================================================== */

        .contenedor {
            max-width: 1200px;
            margin: auto;
            padding: 55px 20px 80px;
        }

        .titulo {
            text-align: center;
            margin-bottom: 40px;
        }

        .titulo h2 {
            font-family: Georgia, serif;
            color: #c37382;
            font-size: 36px;
            margin-bottom: 10px;
        }

        .titulo p {
            color: #a85e69;
        }


        /* =====================================================
           BIENVENIDA
        ===================================================== */

        .bienvenida {
            background: white;

            border: 1px solid #f0cdd4;
            border-radius: 18px;

            padding: 25px 30px;
            margin-bottom: 35px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            box-shadow:
                0 8px 25px
                rgba(120, 60, 70, 0.04);
        }

        .bienvenida h3 {
            font-family: Georgia, serif;
            font-size: 23px;
            margin-bottom: 7px;
        }

        .bienvenida p {
            color: #9c6871;
            line-height: 1.5;
        }

        .badge-admin {
            background: #fde5eb;
            color: #bd6878;

            padding: 8px 16px;

            border-radius: 30px;

            font-size: 13px;
            font-weight: bold;
        }


        /* =====================================================
           ESTADÍSTICAS PRINCIPALES
        ===================================================== */

        .estadisticas-principales {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

            margin-bottom: 25px;
        }

        .tarjeta {
            background: white;

            border: 1px solid #f0cdd4;
            border-radius: 16px;

            padding: 24px;

            min-height: 135px;

            display: flex;
            flex-direction: column;
            justify-content: space-between;

            transition: 0.2s;
        }

        .tarjeta:hover {
            transform: translateY(-2px);

            box-shadow:
                0 10px 25px
                rgba(120, 60, 70, 0.06);
        }

        .tarjeta-icono {
            font-size: 25px;
            margin-bottom: 10px;
        }

        .tarjeta span {
            display: block;

            color: #bd6878;

            font-size: 12px;

            margin-bottom: 10px;

            text-transform: uppercase;

            letter-spacing: 0.3px;
        }

        .tarjeta strong {
            font-family: Georgia, serif;

            font-size: 27px;

            color: #512f35;
        }

        .tarjeta small {
            display: block;

            color: #9c6871;

            margin-top: 8px;

            line-height: 1.4;
        }


        /* =====================================================
           ESTADO DE PEDIDOS
        ===================================================== */

        .seccion-estados {
            margin-top: 40px;
            margin-bottom: 45px;
        }

        .subtitulo {
            font-family: Georgia, serif;

            font-size: 27px;

            margin-bottom: 20px;

            color: #512f35;
        }

        .estados-grid {
            display: grid;

            grid-template-columns:
                repeat(5, 1fr);

            gap: 15px;
        }

        .estado-card {
            background: white;

            border: 1px solid #f0cdd4;

            border-radius: 14px;

            padding: 20px;

            text-align: center;
        }

        .estado-card .numero {
            display: block;

            font-family: Georgia, serif;

            font-size: 27px;

            margin-bottom: 8px;

            color: #512f35;
        }

        .estado-card .nombre {
            font-size: 12px;

            text-transform: uppercase;

            font-weight: bold;
        }

        .estado-card.pendiente {
            background: #fff9e8;
            border-color: #eadcae;
        }

        .estado-card.pendiente .nombre {
            color: #987119;
        }

        .estado-card.procesando {
            background: #eef4ff;
            border-color: #cbdaf0;
        }

        .estado-card.procesando .nombre {
            color: #56729a;
        }

        .estado-card.enviado {
            background: #f0edff;
            border-color: #d6cef2;
        }

        .estado-card.enviado .nombre {
            color: #70609a;
        }

        .estado-card.entregado {
            background: #eef9f1;
            border-color: #c9e8d1;
        }

        .estado-card.entregado .nombre {
            color: #4e815b;
        }

        .estado-card.cancelado {
            background: #fff0f1;
            border-color: #ecc7cd;
        }

        .estado-card.cancelado .nombre {
            color: #a25562;
        }


        /* =====================================================
           ALERTAS DE INVENTARIO
        ===================================================== */

        .inventario-resumen {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

            margin-bottom: 45px;
        }

        .inventario-card {
            background: white;

            border: 1px solid #f0cdd4;

            border-radius: 16px;

            padding: 23px;

            display: flex;

            gap: 15px;

            align-items: center;
        }

        .inventario-icono {
            font-size: 30px;
        }

        .inventario-card span {
            display: block;

            color: #9c6871;

            font-size: 13px;

            margin-bottom: 5px;
        }

        .inventario-card strong {
            font-family: Georgia, serif;

            font-size: 24px;

            color: #512f35;
        }

        .inventario-bajo {
            background: #fff9e8;
            border-color: #eadcae;
        }

        .inventario-agotado {
            background: #fff0f1;
            border-color: #ecc7cd;
        }


        /* =====================================================
           OPCIONES ADMINISTRATIVAS
        ===================================================== */

        .opciones {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

            margin-bottom: 50px;
        }

        .opcion {
            background: white;

            border: 1px solid #f0cdd4;
            border-radius: 16px;

            padding: 30px 25px;

            text-decoration: none;

            color: #4f2f35;

            transition: 0.2s;
        }

        .opcion:hover {
            transform: translateY(-3px);

            box-shadow:
                0 10px 25px
                rgba(120, 60, 70, 0.08);
        }

        .opcion-icono {
            font-size: 32px;
            margin-bottom: 15px;
        }

        .opcion h3 {
            font-family: Georgia, serif;

            font-size: 21px;

            margin-bottom: 8px;
        }

        .opcion p {
            font-size: 14px;

            color: #9c6871;

            line-height: 1.5;
        }


        /* =====================================================
           ÚLTIMOS PEDIDOS
        ===================================================== */

        .pedidos {
            background: white;

            border: 1px solid #f0cdd4;

            border-radius: 18px;

            overflow: hidden;

            margin-bottom: 20px;
        }

        table {
            width: 100%;

            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px;

            text-align: left;

            border-bottom: 1px solid #f4dde2;

            vertical-align: middle;
        }

        th {
            background: #fff1f4;

            color: #b35f70;

            font-size: 12px;

            text-transform: uppercase;
        }

        td {
            font-size: 13px;
        }

        tbody tr:hover {
            background: #fffafb;
        }

        .precio {
            color: #c37382;

            font-weight: bold;

            white-space: nowrap;
        }


        /* =====================================================
           BADGES DE ESTADO
        ===================================================== */

        .estado {
            display: inline-block;

            padding: 6px 11px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;
        }

        .estado-pendiente {
            background: #fff3ce;
            color: #8b6817;
        }

        .estado-procesando {
            background: #eaf2ff;
            color: #55739b;
        }

        .estado-enviado {
            background: #eeeaff;
            color: #6d5d96;
        }

        .estado-entregado {
            background: #e6f6eb;
            color: #477c56;
        }

        .estado-cancelado {
            background: #fde5e8;
            color: #a04f5c;
        }


        /* =====================================================
           PAGO
        ===================================================== */

        .pago-info {
            line-height: 1.5;
        }

        .pago-info strong {
            display: block;

            font-size: 12px;

            color: #70424b;
        }

        .pago-info small {
            color: #9c6871;
        }


        /* =====================================================
           BOTÓN VER
        ===================================================== */

        .btn-ver {
            display: inline-block;

            background: #c37382;

            color: white;

            text-decoration: none;

            padding: 8px 12px;

            border-radius: 8px;

            font-size: 12px;

            transition: 0.2s;

            white-space: nowrap;
        }

        .btn-ver:hover {
            background: #a95f6e;
        }


        /* =====================================================
           SIN PEDIDOS
        ===================================================== */

        .sin-pedidos {
            padding: 35px;

            text-align: center;

            color: #9c6871;
        }


        /* =====================================================
           ENLACE TODOS LOS PEDIDOS
        ===================================================== */

        .acciones-pedidos {
            text-align: right;

            margin-top: 15px;
            margin-bottom: 50px;
        }

        .btn-secundario {
            display: inline-block;

            text-decoration: none;

            color: #a85e69;

            border: 1px solid #e6bbc4;

            background: white;

            padding: 10px 16px;

            border-radius: 9px;

            font-size: 13px;
        }

        .btn-secundario:hover {
            background: #fff0f4;
        }


        /* =====================================================
           FOOTER
        ===================================================== */

        footer {
            background: #623f46;

            color: white;

            text-align: center;

            padding: 35px 20px;
        }

        footer h3 {
            font-family: Georgia, serif;

            margin-bottom: 8px;
        }

        footer p {
            font-size: 13px;

            color: #f4dfe4;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1000px) {

            .estadisticas-principales {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .estados-grid {
                grid-template-columns:
                    repeat(3, 1fr);
            }
        }


        @media (max-width: 900px) {

            .opciones {
                grid-template-columns: 1fr;
            }

            .inventario-resumen {
                grid-template-columns: 1fr;
            }

            .pedidos {
                overflow-x: auto;
            }
        }


        @media (max-width: 650px) {

            .navbar-contenido {
                flex-direction: column;

                gap: 20px;
            }

            .menu {
                flex-wrap: wrap;

                justify-content: center;
            }

            .estadisticas-principales {
                grid-template-columns: 1fr;
            }

            .estados-grid {
                grid-template-columns: 1fr;
            }

            .bienvenida {
                flex-direction: column;

                gap: 15px;

                text-align: center;
            }

            .hero h1 {
                font-size: 35px;
            }

            .titulo h2 {
                font-size: 30px;
            }
        }

    </style>

</head>


<body>


<!-- =====================================================
     ENCABEZADO
====================================================== -->

<section class="hero">

    <h1>
        Roséa Boutique
    </h1>

    <p>
        Elegancia, delicadeza y estilo en cada detalle ♡
    </p>

</section>


<!-- =====================================================
     MENÚ
====================================================== -->

<nav class="navbar">

    <div class="navbar-contenido">

        <div class="logo">
            🌹 Roséa Boutique
        </div>


        <div class="menu">

            <a href="productos.php">
                Colección
            </a>

            <a href="admin.php">
                Panel Admin
            </a>

            <a href="perfil.php">
                Mi perfil
            </a>

            <a href="logout.php">
                Cerrar sesión
            </a>

        </div>

    </div>

</nav>


<!-- =====================================================
     PANEL
====================================================== -->

<main class="contenedor">


    <div class="titulo">

        <h2>
            Panel de Administración
        </h2>

        <p>
            Control general de Roséa Boutique ♡
        </p>

    </div>


    <!-- =================================================
         BIENVENIDA
    ================================================== -->

    <div class="bienvenida">

        <div>

            <h3>
                Bienvenido,
                <?= e($nombreAdmin) ?>
            </h3>

            <p>
                Desde aquí puedes controlar pedidos,
                ventas, inventario, productos y usuarios.
            </p>

        </div>


        <div class="badge-admin">
            👑 Administrador
        </div>

    </div>


    <!-- =================================================
         ESTADÍSTICAS PRINCIPALES
    ================================================== -->

    <section class="estadisticas-principales">


        <div class="tarjeta">

            <div>

                <div class="tarjeta-icono">
                    👗
                </div>

                <span>
                    Productos registrados
                </span>

                <strong>
                    <?= $totalProductos ?>
                </strong>

            </div>

            <small>
                <?= $totalUnidades ?>
                unidades en inventario.
            </small>

        </div>


        <div class="tarjeta">

            <div>

                <div class="tarjeta-icono">
                    🛍️
                </div>

                <span>
                    Pedidos totales
                </span>

                <strong>
                    <?= $totalPedidos ?>
                </strong>

            </div>

            <small>
                Todos los pedidos registrados.
            </small>

        </div>


        <div class="tarjeta">

            <div>

                <div class="tarjeta-icono">
                    👥
                </div>

                <span>
                    Usuarios
                </span>

                <strong>
                    <?= $totalUsuarios ?>
                </strong>

            </div>

            <small>
                Cuentas registradas en la tienda.
            </small>

        </div>


        <div class="tarjeta">

            <div>

                <div class="tarjeta-icono">
                    💰
                </div>

                <span>
                    Ventas entregadas
                </span>

                <strong>
                    RD$<?= number_format(
                        $totalVentas,
                        2
                    ) ?>
                </strong>

            </div>

            <small>
                Solo pedidos completados y entregados.
            </small>

        </div>


    </section>


    <!-- =================================================
         ESTADOS DE PEDIDOS
    ================================================== -->

    <section class="seccion-estados">

        <h2 class="subtitulo">
            Estado de los pedidos
        </h2>


        <div class="estados-grid">


            <div class="estado-card pendiente">

                <span class="numero">
                    <?= $pedidosPendientes ?>
                </span>

                <span class="nombre">
                    Pendientes
                </span>

            </div>


            <div class="estado-card procesando">

                <span class="numero">
                    <?= $pedidosProcesando ?>
                </span>

                <span class="nombre">
                    Procesando
                </span>

            </div>


            <div class="estado-card enviado">

                <span class="numero">
                    <?= $pedidosEnviados ?>
                </span>

                <span class="nombre">
                    Enviados
                </span>

            </div>


            <div class="estado-card entregado">

                <span class="numero">
                    <?= $pedidosEntregados ?>
                </span>

                <span class="nombre">
                    Entregados
                </span>

            </div>


            <div class="estado-card cancelado">

                <span class="numero">
                    <?= $pedidosCancelados ?>
                </span>

                <span class="nombre">
                    Cancelados
                </span>

            </div>


        </div>

    </section>


    <!-- =================================================
         INVENTARIO
    ================================================== -->

    <h2 class="subtitulo">
        Resumen del inventario
    </h2>


    <section class="inventario-resumen">


        <div class="inventario-card">

            <div class="inventario-icono">
                📦
            </div>

            <div>

                <span>
                    Unidades disponibles
                </span>

                <strong>
                    <?= $totalUnidades ?>
                </strong>

            </div>

        </div>


        <div class="inventario-card inventario-bajo">

            <div class="inventario-icono">
                ⚠️
            </div>

            <div>

                <span>
                    Productos con stock bajo
                </span>

                <strong>
                    <?= $productosStockBajo ?>
                </strong>

            </div>

        </div>


        <div class="inventario-card inventario-agotado">

            <div class="inventario-icono">
                🚫
            </div>

            <div>

                <span>
                    Productos agotados
                </span>

                <strong>
                    <?= $productosAgotados ?>
                </strong>

            </div>

        </div>


    </section>


    <!-- =================================================
         ADMINISTRACIÓN
    ================================================== -->

    <h2 class="subtitulo">
        Administración
    </h2>


    <section class="opciones">


        <a
            href="admin_productos.php"
            class="opcion"
        >

            <div class="opcion-icono">
                👗
            </div>

            <h3>
                Productos
            </h3>

            <p>
                Agregar y editar productos,
                controlar precios, imágenes,
                disponibilidad y existencias.
            </p>

        </a>


        <a
            href="admin_pedidos.php"
            class="opcion"
        >

            <div class="opcion-icono">
                🛍️
            </div>

            <h3>
                Pedidos
            </h3>

            <p>
                Gestionar pedidos, pagos,
                comprobantes, envíos,
                cancelaciones y entregas.
            </p>

        </a>


        <a
            href="admin_usuarios.php"
            class="opcion"
        >

            <div class="opcion-icono">
                👥
            </div>

            <h3>
                Usuarios
            </h3>

            <p>
                Consultar clientes,
                administradores y actividad
                de compras registrada.
            </p>

        </a>


    </section>


    <!-- =================================================
         ÚLTIMOS PEDIDOS
    ================================================== -->

    <h2 class="subtitulo">
        Últimos pedidos
    </h2>


    <div class="pedidos">


        <?php if (count($ultimosPedidos) > 0): ?>


            <table>


                <thead>

                    <tr>

                        <th>
                            Pedido
                        </th>

                        <th>
                            Cliente
                        </th>

                        <th>
                            Fecha
                        </th>

                        <th>
                            Total
                        </th>

                        <th>
                            Pago
                        </th>

                        <th>
                            Estado
                        </th>

                        <th>
                            Acción
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php foreach ($ultimosPedidos as $pedido): ?>


                        <tr>


                            <td>

                                <strong>
                                    #<?= (int)$pedido['id_pedido'] ?>
                                </strong>

                            </td>


                            <td>

                                <strong>
                                    <?= e(
                                        $pedido['nombre'] . ' ' .
                                        $pedido['apellido']
                                    ) ?>
                                </strong>

                                <br>

                                <small>
                                    <?= e($pedido['email']) ?>
                                </small>

                            </td>


                            <td>

                                <?php

                                $fechaPedido =
                                    strtotime(
                                        $pedido['fecha_pedido']
                                    );

                                if ($fechaPedido !== false) {

                                    echo date(
                                        'd/m/Y h:i A',
                                        $fechaPedido
                                    );

                                } else {

                                    echo e(
                                        $pedido['fecha_pedido']
                                    );
                                }

                                ?>

                            </td>


                            <td class="precio">

                                RD$<?= number_format(
                                    (float)$pedido['total'],
                                    2
                                ) ?>

                            </td>


                            <td>

                                <div class="pago-info">

                                    <strong>
                                        <?= e(
                                            nombrePago(
                                                $pedido['metodo_pago']
                                            )
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= e(
                                            nombreEstadoPago(
                                                $pedido['estado_pago']
                                            )
                                        ) ?>
                                    </small>

                                </div>

                            </td>


                            <td>

                                <span
                                    class="estado <?= e(
                                        claseEstado(
                                            $pedido['estado']
                                        )
                                    ) ?>"
                                >

                                    <?= e(
                                        nombreEstado(
                                            $pedido['estado']
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <a
                                    href="admin_detalle_pedido.php?id=<?= (int)$pedido['id_pedido'] ?>"
                                    class="btn-ver"
                                >
                                    Ver pedido
                                </a>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                </tbody>


            </table>


        <?php else: ?>


            <div class="sin-pedidos">

                Todavía no existen pedidos.

            </div>


        <?php endif; ?>


    </div>


    <div class="acciones-pedidos">

        <a
            href="admin_pedidos.php"
            class="btn-secundario"
        >
            Ver todos los pedidos →
        </a>

    </div>


</main>


<!-- =====================================================
     FOOTER
====================================================== -->

<footer>

    <h3>
        Roséa Boutique 🌹
    </h3>

    <p>
        Panel administrativo · Elegancia y estilo
        en cada detalle.
    </p>

</footer>


</body>

</html>