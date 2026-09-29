<?php

session_start();

require_once __DIR__ . '/config/conexion.php';

/* =========================================================
   COMPROBAR SESIÓN
========================================================= */

if (!isset($_SESSION["id_usuario"])) {
    header("Location: login.php");
    exit;
}

$idUsuario = (int) $_SESSION["id_usuario"];


/* =========================================================
   COMPROBAR SI ES ADMIN
========================================================= */

$esAdmin = (
    isset($_SESSION["rol"]) &&
    strtolower(trim($_SESSION["rol"])) === "admin"
);


/* =========================================================
   OBTENER PEDIDOS DEL USUARIO
========================================================= */

try {

    $sql = "
        SELECT
            id_pedido,
            id_usuario,
            fecha_pedido,
            total,
            estado,
            metodo_pago,
            estado_pago,
            empresa_envio,
            codigo_seguimiento,
            fecha_envio,
            metodo_entrega
        FROM pedidos
        WHERE id_usuario = :id_usuario
        ORDER BY fecha_pedido DESC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        ':id_usuario' => $idUsuario
    ]);

    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $pedidos = [];

}


/* =========================================================
   FUNCIONES
========================================================= */

function e($valor)
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}


function nombreEstado($estado)
{
    $estado = strtolower(trim((string) $estado));

    switch ($estado) {

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
            return 'Pendiente';
    }
}


function nombrePago($estado)
{
    $estado = strtolower(trim((string) $estado));

    switch ($estado) {

        case 'verificado':
            return 'Pago verificado';

        case 'rechazado':
            return 'Comprobante rechazado';

        default:
            return 'Pendiente de verificación';
    }
}


function clasePago($estado)
{
    $estado = strtolower(trim((string) $estado));

    if ($estado === 'verificado') {
        return 'pago-verificado';
    }

    if ($estado === 'rechazado') {
        return 'pago-rechazado';
    }

    return 'pago-pendiente';
}


function nombreMetodoPago($metodo)
{
    $metodo = strtolower(trim((string) $metodo));

    if ($metodo === 'transferencia') {
        return 'Transferencia bancaria';
    }

    if ($metodo === 'efectivo') {
        return 'Efectivo';
    }

    return 'No especificado';
}


function nivelEstado($estado)
{
    $estado = strtolower(trim((string) $estado));

    switch ($estado) {

        case 'pendiente':
            return 1;

        case 'procesando':
            return 2;

        case 'enviado':
            return 3;

        case 'entregado':
            return 4;

        default:
            return 0;
    }
}


function claseEstado($estado)
{
    $estado = strtolower(trim((string) $estado));

    switch ($estado) {

        case 'procesando':
            return 'estado-procesando';

        case 'enviado':
            return 'estado-enviado';

        case 'entregado':
            return 'estado-entregado';

        case 'cancelado':
            return 'estado-cancelado';

        default:
            return 'estado-pendiente';
    }
}


function anchoProgreso($nivel)
{
    switch ((int) $nivel) {

        case 2:
            return 33;

        case 3:
            return 66;

        case 4:
            return 100;

        default:
            return 0;
    }
}


function formatearFecha($fecha)
{
    if (empty($fecha)) {
        return 'No disponible';
    }

    $tiempo = strtotime($fecha);

    if ($tiempo === false) {
        return $fecha;
    }

    return date('d/m/Y h:i A', $tiempo);
}


/* =========================================================
   DATOS DEL USUARIO
========================================================= */

$nombre = trim(
    ($_SESSION["nombre"] ?? '') . ' ' .
    ($_SESSION["apellido"] ?? '')
);

$email = $_SESSION["email"] ?? '';

$rol = $_SESSION["rol"] ?? 'cliente';

$inicial = !empty($_SESSION["nombre"])
    ? strtoupper(substr($_SESSION["nombre"], 0, 1))
    : 'R';

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Mi perfil | Roséa Boutique</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Georgia, "Times New Roman", serif;
            background: #fff8fa;
            color: #513238;
        }

        a {
            text-decoration: none;
            color: inherit;
        }


        /* =====================================================
           HERO
        ===================================================== */

        .hero {
            text-align: center;
            padding: 55px 20px 42px;
            background: linear-gradient(
                135deg,
                #fff7f9,
                #fdecef
            );
        }

        .hero h1 {
            font-size: 46px;
            color: #bd7180;
            margin-bottom: 12px;
        }

        .hero p {
            font-family: Arial, sans-serif;
            font-size: 15px;
            color: #9c5f69;
        }


        /* =====================================================
           MENÚ
        ===================================================== */

        header {
            width: 100%;
            background: #ffffff;
            padding: 20px 7%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f1dce2;
        }

        .logo {
            color: #c36f7e;
            font-size: 25px;
            font-weight: bold;
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 25px;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        .nav a {
            color: #5b3038;
            transition: .2s;
        }

        .nav a:hover {
            color: #c36f7e;
        }


        /* =====================================================
           CONTENEDOR
        ===================================================== */

        .container {
            max-width: 1050px;
            margin: auto;
            padding: 55px 20px 90px;
        }


        /* =====================================================
           PERFIL
        ===================================================== */

        .perfil {
            background: white;
            border: 1px solid #f0cbd2;
            border-radius: 18px;
            overflow: hidden;
            margin-bottom: 55px;
            box-shadow: 0 8px 25px rgba(120, 60, 75, .05);
        }

        .perfil-cabecera {
            background: #fff0f3;
            padding: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 25px;
        }

        .perfil-cabecera h2 {
            color: #bd7180;
            font-size: 32px;
            margin-bottom: 8px;
        }

        .perfil-cabecera p {
            font-family: Arial, sans-serif;
            color: #a85f6c;
            font-size: 14px;
        }

        .avatar {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            background: #c87988;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
        }


        /* =====================================================
           DATOS PERFIL
        ===================================================== */

        .datos {
            padding: 28px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .dato {
            background: #fff8fa;
            border: 1px solid #f2d6dc;
            border-radius: 12px;
            padding: 20px;
        }

        .dato span {
            display: block;
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #c16b7a;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .dato strong {
            font-size: 17px;
            color: #48272e;
        }


        /* =====================================================
           ADMIN
        ===================================================== */

        .admin-box {
            margin: 0 28px 28px;
            padding: 22px;
            background: #fff2f5;
            border: 1px solid #efc7d0;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .admin-box h3 {
            color: #b96878;
            margin-bottom: 5px;
        }

        .admin-box p {
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #87555e;
        }

        .admin-boton {
            display: inline-block;
            background: #c57584;
            color: white;
            padding: 12px 20px;
            border-radius: 9px;
            font-family: Arial, sans-serif;
            font-size: 13px;
            font-weight: bold;
            white-space: nowrap;
        }

        .admin-boton:hover {
            background: #ad5f6e;
        }


        /* =====================================================
           TÍTULO PEDIDOS
        ===================================================== */

        .pedidos-titulo {
            text-align: center;
            margin-bottom: 30px;
        }

        .pedidos-titulo h2 {
            color: #bd7180;
            font-size: 34px;
            margin-bottom: 8px;
        }

        .pedidos-titulo p {
            font-family: Arial, sans-serif;
            color: #9f606b;
            font-size: 14px;
        }


        /* =====================================================
           PEDIDO
        ===================================================== */

        .pedido {
            background: white;
            border: 1px solid #efc7d0;
            border-radius: 17px;
            margin-bottom: 28px;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(100, 50, 65, .04);
        }

        .pedido-superior {
            background: #fff0f3;
            padding: 21px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .pedido-superior h3 {
            font-size: 23px;
            color: #4c2930;
        }

        .pedido-contenido {
            padding: 25px;
        }


        /* =====================================================
           ESTADOS
        ===================================================== */

        .estado {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 30px;
            font-family: Arial, sans-serif;
            font-size: 12px;
            font-weight: bold;
        }

        .estado-pendiente {
            background: #fff0c9;
            color: #8d6a12;
        }

        .estado-procesando {
            background: #e9efff;
            color: #4964a5;
        }

        .estado-enviado {
            background: #e3f3ff;
            color: #33739d;
        }

        .estado-entregado {
            background: #e3f6e7;
            color: #377944;
        }

        .estado-cancelado {
            background: #ffe4e4;
            color: #a43e3e;
        }


        /* =====================================================
           INFORMACIÓN GENERAL
        ===================================================== */

        .pedido-datos {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .pedido-dato {
            background: #fff8fa;
            border: 1px solid #f2d5db;
            border-radius: 11px;
            padding: 17px;
            font-family: Arial, sans-serif;
            color: #71434c;
        }

        .pedido-dato strong {
            display: block;
            color: #c26979;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 7px;
        }

        .total {
            font-family: Georgia, serif;
            color: #c46d7c;
            font-size: 20px;
            font-weight: bold;
        }


        /* =====================================================
           INFORMACIÓN DE PAGO Y ENVÍO
        ===================================================== */

        .informacion-extra {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .extra-box {
            border: 1px solid #f0d3d9;
            background: #fffafb;
            border-radius: 13px;
            padding: 18px;
        }

        .extra-box h4 {
            color: #59333b;
            font-size: 17px;
            margin-bottom: 15px;
        }

        .extra-fila {
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #75505a;
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .extra-fila:last-child {
            margin-bottom: 0;
        }

        .extra-fila span {
            display: block;
            color: #bf6878;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .codigo {
            font-weight: bold;
            color: #bd6677;
            letter-spacing: .5px;
        }


        /* =====================================================
           ESTADO DEL PAGO
        ===================================================== */

        .pago {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 30px;
            font-family: Arial, sans-serif;
            font-size: 11px;
            font-weight: bold;
        }

        .pago-verificado {
            background: #dcf5e3;
            color: #347341;
        }

        .pago-pendiente {
            background: #fff0c9;
            color: #8d6a12;
        }

        .pago-rechazado {
            background: #ffe1e1;
            color: #a33f3f;
        }


        /* =====================================================
           SEGUIMIENTO
        ===================================================== */

        .seguimiento {
            margin-top: 10px;
            margin-bottom: 28px;
            padding: 24px 18px;
            background: #fffafb;
            border: 1px solid #f2d5db;
            border-radius: 14px;
        }

        .seguimiento-titulo {
            font-size: 18px;
            color: #553139;
            margin-bottom: 24px;
        }

        .progreso {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            position: relative;
        }

        .paso {
            flex: 1;
            position: relative;
            text-align: center;
            font-family: Arial, sans-serif;
            z-index: 2;
        }

        .circulo {
            width: 38px;
            height: 38px;
            margin: 0 auto 10px;
            border-radius: 50%;
            background: #f3e2e6;
            color: #a87b84;
            border: 3px solid #efd1d8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: bold;
        }

        .paso.activo .circulo {
            background: #c57584;
            border-color: #c57584;
            color: white;
        }

        .paso.actual .circulo {
            box-shadow: 0 0 0 6px rgba(197, 117, 132, .15);
        }

        .paso-texto {
            color: #99717a;
            font-size: 12px;
            font-weight: bold;
        }

        .paso.activo .paso-texto {
            color: #a95768;
        }

        .linea {
            position: absolute;
            height: 4px;
            background: #f0dce1;
            top: 17px;
            left: 12.5%;
            right: 12.5%;
            z-index: 1;
        }

        .linea-progreso {
            position: absolute;
            height: 4px;
            background: #c57584;
            top: 17px;
            left: 12.5%;
            z-index: 1;
            transition: width .3s ease;
        }


        /* =====================================================
           CANCELADO
        ===================================================== */

        .pedido-cancelado {
            background: #fff1f1;
            border: 1px solid #efb7b7;
            border-radius: 12px;
            padding: 20px;
            font-family: Arial, sans-serif;
            color: #963f3f;
            margin-bottom: 25px;
        }

        .pedido-cancelado strong {
            display: block;
            font-family: Georgia, serif;
            font-size: 19px;
            margin-bottom: 7px;
        }


        /* =====================================================
           ENTREGADO
        ===================================================== */

        .pedido-entregado {
            background: #effaf1;
            border: 1px solid #bfe3c6;
            border-radius: 12px;
            padding: 17px;
            margin-bottom: 25px;
            color: #377944;
            font-family: Arial, sans-serif;
            font-size: 13px;
            line-height: 1.5;
        }

        .pedido-entregado strong {
            display: block;
            font-family: Georgia, serif;
            margin-bottom: 5px;
            font-size: 17px;
        }


        /* =====================================================
           BOTÓN
        ===================================================== */

        .ver-pedido {
            display: inline-block;
            padding: 12px 21px;
            border: 1px solid #c57584;
            border-radius: 9px;
            color: #c57584;
            font-family: Arial, sans-serif;
            font-size: 13px;
            font-weight: bold;
            transition: .2s;
        }

        .ver-pedido:hover {
            background: #c57584;
            color: white;
        }


        /* =====================================================
           SIN PEDIDOS
        ===================================================== */

        .sin-pedidos {
            background: white;
            border: 1px solid #efc7d0;
            border-radius: 17px;
            text-align: center;
            padding: 45px 20px;
        }

        .sin-pedidos h3 {
            color: #b96d7b;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .sin-pedidos p {
            font-family: Arial, sans-serif;
            color: #8d626a;
            margin-bottom: 22px;
        }

        .explorar {
            display: inline-block;
            background: #c57584;
            color: white;
            padding: 12px 22px;
            border-radius: 9px;
            font-family: Arial, sans-serif;
            font-weight: bold;
            font-size: 13px;
        }


        /* =====================================================
           FOOTER
        ===================================================== */

        footer {
            background: #68434a;
            color: white;
            text-align: center;
            padding: 36px 20px;
        }

        footer h3 {
            margin-bottom: 7px;
        }

        footer p {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 800px) {

            header {
                flex-direction: column;
                gap: 18px;
            }

            .nav {
                flex-wrap: wrap;
                justify-content: center;
            }

            .datos,
            .pedido-datos,
            .informacion-extra {
                grid-template-columns: 1fr;
            }

            .admin-box {
                flex-direction: column;
                align-items: flex-start;
            }
        }


        @media (max-width: 650px) {

            .hero h1 {
                font-size: 35px;
            }

            .perfil-cabecera {
                flex-direction: column-reverse;
                align-items: flex-start;
            }

            .pedido-superior {
                flex-direction: column;
                align-items: flex-start;
            }

            .seguimiento {
                padding: 20px 10px;
            }

            .paso-texto {
                font-size: 10px;
            }

            .circulo {
                width: 31px;
                height: 31px;
                font-size: 12px;
            }

            .linea,
            .linea-progreso {
                top: 14px;
            }
        }

    </style>

</head>

<body>


<!-- =====================================================
     PRESENTACIÓN
====================================================== -->

<section class="hero">

    <h1>Roséa Boutique</h1>

    <p>
        Elegancia, delicadeza y estilo en cada detalle ♡
    </p>

</section>


<!-- =====================================================
     MENÚ
====================================================== -->

<header>

    <a href="productos.php" class="logo">
        🌹 Roséa Boutique
    </a>

    <nav class="nav">

        <a href="productos.php">
            Colección
        </a>

        <?php if ($esAdmin): ?>

            <a href="admin.php">
                Panel Admin
            </a>

            <a href="admin_productos.php">
                Productos
            </a>

            <a href="admin_pedidos.php">
                Pedidos
            </a>

            <a href="admin_usuarios.php">
                Usuarios
            </a>

        <?php endif; ?>

        <a href="perfil.php">
            Mi perfil
        </a>

        <a href="logout.php">
            Cerrar sesión
        </a>

    </nav>

</header>


<!-- =====================================================
     CONTENIDO
====================================================== -->

<main class="container">


    <!-- =================================================
         INFORMACIÓN DEL PERFIL
    ================================================== -->

    <section class="perfil">

        <div class="perfil-cabecera">

            <div>

                <h2>
                    Mi perfil
                </h2>

                <p>
                    Gracias por formar parte de Roséa Boutique ♡
                </p>

            </div>

            <div class="avatar">
                <?= e($inicial) ?>
            </div>

        </div>


        <div class="datos">

            <div class="dato">

                <span>
                    Nombre
                </span>

                <strong>
                    <?= e($nombre) ?>
                </strong>

            </div>


            <div class="dato">

                <span>
                    Correo electrónico
                </span>

                <strong>
                    <?= e($email) ?>
                </strong>

            </div>


            <div class="dato">

                <span>
                    Tipo de cuenta
                </span>

                <strong>

                    <?php if ($esAdmin): ?>

                        Administrador

                    <?php else: ?>

                        <?= e(ucfirst($rol)) ?>

                    <?php endif; ?>

                </strong>

            </div>


            <div class="dato">

                <span>
                    Estado
                </span>

                <strong>
                    ✓ Sesión activa
                </strong>

            </div>

        </div>


        <?php if ($esAdmin): ?>

            <div class="admin-box">

                <div>

                    <h3>
                        Cuenta administrativa
                    </h3>

                    <p>
                        Tienes acceso a la administración de
                        Roséa Boutique.
                    </p>

                </div>

                <a
                    href="admin.php"
                    class="admin-boton"
                >
                    Ir al Panel Admin ♡
                </a>

            </div>

        <?php endif; ?>

    </section>


    <!-- =================================================
         MIS PEDIDOS
    ================================================== -->

    <div class="pedidos-titulo">

        <h2>
            Mis pedidos
        </h2>

        <p>
            Consulta tus compras, pagos y seguimiento
            de cada pedido.
        </p>

    </div>


    <?php if (count($pedidos) > 0): ?>


        <?php foreach ($pedidos as $pedido): ?>


            <?php

            $estadoActual = strtolower(
                trim($pedido["estado"] ?? "pendiente")
            );

            $estadoPago = strtolower(
                trim($pedido["estado_pago"] ?? "pendiente")
            );

            $metodoPago = strtolower(
                trim($pedido["metodo_pago"] ?? "")
            );

            $nivel = nivelEstado($estadoActual);

            $ancho = anchoProgreso($nivel);

            $claseEstadoActual = claseEstado($estadoActual);

            $nombreEstadoActual = nombreEstado($estadoActual);

            $clasePagoActual = clasePago($estadoPago);

            $nombrePagoActual = nombrePago($estadoPago);

            $empresaEnvio = trim(
                (string) ($pedido["empresa_envio"] ?? "")
            );

            $codigoSeguimiento = trim(
                (string) ($pedido["codigo_seguimiento"] ?? "")
            );

            $fechaEnvio = $pedido["fecha_envio"] ?? null;

            ?>


            <article class="pedido">


                <!-- CABECERA -->

                <div class="pedido-superior">

                    <h3>
                        Pedido #<?= (int) $pedido["id_pedido"] ?>
                    </h3>

                    <span
                        class="estado <?= e($claseEstadoActual) ?>"
                    >
                        <?= e($nombreEstadoActual) ?>
                    </span>

                </div>


                <div class="pedido-contenido">


                    <!-- DATOS GENERALES -->

                    <div class="pedido-datos">


                        <div class="pedido-dato">

                            <strong>
                                Fecha
                            </strong>

                            <?= e(
                                formatearFecha(
                                    $pedido["fecha_pedido"]
                                )
                            ) ?>

                        </div>


                        <div class="pedido-dato">

                            <strong>
                                Total
                            </strong>

                            <span class="total">

                                RD$<?= number_format(
                                    (float) $pedido["total"],
                                    2
                                ) ?>

                            </span>

                        </div>

                    </div>


                    <!-- =================================================
                         INFORMACIÓN DE PAGO Y ENVÍO
                    ================================================== -->

                    <div class="informacion-extra">


                        <!-- PAGO -->

                        <div class="extra-box">

                            <h4>
                                💳 Estado del pago
                            </h4>


                            <div class="extra-fila">

                                <span>
                                    Método de pago
                                </span>

                                <?= e(
                                    nombreMetodoPago($metodoPago)
                                ) ?>

                            </div>


                            <div class="extra-fila">

                                <span>
                                    Estado
                                </span>


                                <?php if ($metodoPago === 'efectivo'): ?>

                                    <span
                                        class="pago pago-pendiente"
                                        style="
                                            display:inline-block;
                                            font-size:11px;
                                            margin-top:3px;
                                        "
                                    >
                                        Pago en efectivo
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="pago <?= e($clasePagoActual) ?>"
                                        style="
                                            display:inline-block;
                                            font-size:11px;
                                            margin-top:3px;
                                        "
                                    >
                                        <?= e($nombrePagoActual) ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- ENVÍO -->

                        <div class="extra-box">

                            <h4>
                                📦 Información de envío
                            </h4>


                            <?php if (
                                $estadoActual === 'enviado' ||
                                $estadoActual === 'entregado' ||
                                !empty($codigoSeguimiento) ||
                                !empty($empresaEnvio)
                            ): ?>


                                <div class="extra-fila">

                                    <span>
                                        Empresa de envío
                                    </span>

                                    <?= !empty($empresaEnvio)
                                        ? e($empresaEnvio)
                                        : 'No especificada'
                                    ?>

                                </div>


                                <div class="extra-fila">

                                    <span>
                                        Código de seguimiento
                                    </span>

                                    <?php if (!empty($codigoSeguimiento)): ?>

                                        <span
                                            class="codigo"
                                            style="
                                                display:inline;
                                                font-size:13px;
                                                text-transform:none;
                                            "
                                        >
                                            <?= e($codigoSeguimiento) ?>
                                        </span>

                                    <?php else: ?>

                                        No disponible

                                    <?php endif; ?>

                                </div>


                                <div class="extra-fila">

                                    <span>
                                        Fecha de envío
                                    </span>

                                    <?= e(
                                        formatearFecha($fechaEnvio)
                                    ) ?>

                                </div>


                            <?php else: ?>


                                <div class="extra-fila">

                                    <span>
                                        Estado
                                    </span>

                                    El pedido todavía no ha sido
                                    despachado.

                                </div>


                            <?php endif; ?>


                        </div>

                    </div>


                    <!-- =================================================
                         CANCELADO
                    ================================================== -->

                    <?php if ($estadoActual === "cancelado"): ?>


                        <div class="pedido-cancelado">

                            <strong>
                                ✕ Pedido cancelado
                            </strong>

                            Este pedido ha sido marcado como cancelado.
                            El proceso de seguimiento ha sido detenido.

                        </div>


                    <?php else: ?>


                        <!-- =================================================
                             ENTREGADO
                        ================================================== -->

                        <?php if ($estadoActual === 'entregado'): ?>

                            <div class="pedido-entregado">

                                <strong>
                                    ✓ Pedido entregado
                                </strong>

                                Tu pedido completó correctamente
                                todo el proceso de compra y entrega.

                                <?php if (!empty($codigoSeguimiento)): ?>

                                    <br><br>

                                    Código de seguimiento:

                                    <strong
                                        style="
                                            display:inline;
                                            font-family:Arial,sans-serif;
                                            font-size:13px;
                                        "
                                    >
                                        <?= e($codigoSeguimiento) ?>
                                    </strong>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>


                        <!-- =================================================
                             SEGUIMIENTO
                        ================================================== -->

                        <div class="seguimiento">

                            <h4 class="seguimiento-titulo">
                                Seguimiento del pedido
                            </h4>


                            <div class="progreso">


                                <div class="linea"></div>


                                <div
                                    class="linea-progreso"
                                    style="
                                        width: calc(
                                            <?= (int) $ancho ?>% - 12.5%
                                        );
                                        max-width:75%;
                                    "
                                ></div>


                                <!-- PASO 1 -->

                                <div class="
                                    paso
                                    <?= $nivel >= 1 ? 'activo' : '' ?>
                                    <?= $nivel === 1 ? 'actual' : '' ?>
                                ">

                                    <div class="circulo">

                                        <?= $nivel >= 1
                                            ? '✓'
                                            : '1'
                                        ?>

                                    </div>

                                    <div class="paso-texto">
                                        Pedido recibido
                                    </div>

                                </div>


                                <!-- PASO 2 -->

                                <div class="
                                    paso
                                    <?= $nivel >= 2 ? 'activo' : '' ?>
                                    <?= $nivel === 2 ? 'actual' : '' ?>
                                ">

                                    <div class="circulo">

                                        <?= $nivel >= 2
                                            ? '✓'
                                            : '2'
                                        ?>

                                    </div>

                                    <div class="paso-texto">
                                        Procesando
                                    </div>

                                </div>


                                <!-- PASO 3 -->

                                <div class="
                                    paso
                                    <?= $nivel >= 3 ? 'activo' : '' ?>
                                    <?= $nivel === 3 ? 'actual' : '' ?>
                                ">

                                    <div class="circulo">

                                        <?= $nivel >= 3
                                            ? '✓'
                                            : '3'
                                        ?>

                                    </div>

                                    <div class="paso-texto">
                                        Enviado
                                    </div>

                                </div>


                                <!-- PASO 4 -->

                                <div class="
                                    paso
                                    <?= $nivel >= 4 ? 'activo' : '' ?>
                                    <?= $nivel === 4 ? 'actual' : '' ?>
                                ">

                                    <div class="circulo">

                                        <?= $nivel >= 4
                                            ? '✓'
                                            : '4'
                                        ?>

                                    </div>

                                    <div class="paso-texto">
                                        Entregado
                                    </div>

                                </div>


                            </div>

                        </div>


                    <?php endif; ?>


                    <!-- =================================================
                         BOTÓN DETALLE
                    ================================================== -->

                    <a
                        class="ver-pedido"
                        href="pedido_exitoso.php?id=<?= (int) $pedido["id_pedido"] ?>"
                    >
                        Ver detalles ♡
                    </a>


                </div>

            </article>


        <?php endforeach; ?>


    <?php else: ?>


        <div class="sin-pedidos">

            <h3>
                Todavía no has realizado ningún pedido 🌹
            </h3>

            <p>
                Explora nuestra colección y encuentra
                algo especial para ti.
            </p>

            <a
                href="productos.php"
                class="explorar"
            >
                Explorar colección ♡
            </a>

        </div>


    <?php endif; ?>


</main>


<!-- =====================================================
     FOOTER
====================================================== -->

<footer>

    <h3>
        Roséa Boutique 🌹
    </h3>

    <p>
        Elegancia, delicadeza y estilo en cada detalle ♡
    </p>

</footer>


</body>
</html>