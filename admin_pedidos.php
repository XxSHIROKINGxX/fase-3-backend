<?php

session_start();

require_once __DIR__ . '/config/conexion.php';


/* =========================================================
   SEGURIDAD: SOLO ADMINISTRADORES
========================================================= */

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

if (
    !isset($_SESSION['rol']) ||
    strtolower(trim($_SESSION['rol'])) !== 'admin'
) {
    header("Location: productos.php");
    exit;
}


/* =========================================================
   MENSAJES
========================================================= */

$mensaje = "";
$error = "";


/* =========================================================
   ESTADOS PERMITIDOS
========================================================= */

$estadosPermitidos = [
    'pendiente',
    'procesando',
    'enviado',
    'entregado',
    'cancelado'
];


/* =========================================================
   FUNCIONES
========================================================= */

function generarCodigoSeguimiento($id_pedido)
{
    return 'ROSEA-' .
        str_pad(
            (string)$id_pedido,
            6,
            '0',
            STR_PAD_LEFT
        );
}

function nombreEstadoPago($estado)
{
    $estado = strtolower(trim((string)$estado));

    if ($estado === 'verificado') {
        return 'Pago verificado';
    }

    if ($estado === 'rechazado') {
        return 'Comprobante rechazado';
    }

    return 'Pendiente de verificación';
}

function nombreMetodoPago($metodo)
{
    $metodo = strtolower(trim((string)$metodo));

    if ($metodo === 'transferencia') {
        return 'Transferencia bancaria';
    }

    if ($metodo === 'efectivo') {
        return 'Efectivo';
    }

    return 'No especificado';
}


/* =========================================================
   ACTUALIZAR ESTADO DEL PEDIDO
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_pedido = isset($_POST['id_pedido'])
        ? (int)$_POST['id_pedido']
        : 0;

    $nuevo_estado = isset($_POST['estado'])
        ? strtolower(trim($_POST['estado']))
        : '';

    if (
        $id_pedido <= 0 ||
        !in_array(
            $nuevo_estado,
            $estadosPermitidos,
            true
        )
    ) {

        $error = "Los datos enviados no son válidos.";

    } else {

        try {

            $conexion->beginTransaction();


            /* =================================================
               BUSCAR PEDIDO Y BLOQUEARLO
            ================================================= */

            $sqlPedido = "
                SELECT
                    id_pedido,
                    estado,
                    metodo_pago,
                    estado_pago,
                    comprobante_pago,
                    fecha_envio,
                    codigo_seguimiento

                FROM pedidos

                WHERE id_pedido = :id_pedido

                FOR UPDATE
            ";

            $stmtPedido = $conexion->prepare($sqlPedido);

            $stmtPedido->bindValue(
                ':id_pedido',
                $id_pedido,
                PDO::PARAM_INT
            );

            $stmtPedido->execute();

            $pedidoActual =
                $stmtPedido->fetch(PDO::FETCH_ASSOC);

            if (!$pedidoActual) {
                throw new Exception(
                    "El pedido seleccionado no existe."
                );
            }


            /* =================================================
               DATOS ACTUALES DEL PEDIDO
            ================================================= */

            $estado_anterior = strtolower(
                trim($pedidoActual['estado'])
            );

            $metodo_pago = strtolower(
                trim($pedidoActual['metodo_pago'] ?? '')
            );

            $estado_pago = strtolower(
                trim($pedidoActual['estado_pago'] ?? 'pendiente')
            );

            $fecha_envio =
                $pedidoActual['fecha_envio'];

            $codigo_seguimiento =
                $pedidoActual['codigo_seguimiento'];


            /* =================================================
               PROTECCIÓN DE TRANSFERENCIAS
            ================================================= */

            $estadosQueRequierenPago = [
                'procesando',
                'enviado',
                'entregado'
            ];

            if (
                $metodo_pago === 'transferencia' &&
                in_array(
                    $nuevo_estado,
                    $estadosQueRequierenPago,
                    true
                ) &&
                $estado_pago !== 'verificado'
            ) {

                if ($estado_pago === 'rechazado') {

                    throw new Exception(
                        "No puedes cambiar este pedido a " .
                        ucfirst($nuevo_estado) .
                        " porque el comprobante de pago fue rechazado. " .
                        "El cliente debe enviar un nuevo comprobante."
                    );

                } else {

                    throw new Exception(
                        "No puedes cambiar este pedido a " .
                        ucfirst($nuevo_estado) .
                        " porque la transferencia todavía está " .
                        "pendiente de verificación."
                    );
                }
            }


            /* =================================================
               MISMO ESTADO
            ================================================= */

            if ($estado_anterior === $nuevo_estado) {

                /*
                 * Si es un pedido antiguo enviado o entregado
                 * y todavía no tiene información de seguimiento,
                 * la generamos.
                 */

                if (
                    in_array(
                        $nuevo_estado,
                        ['enviado', 'entregado'],
                        true
                    ) &&
                    (
                        empty($fecha_envio) ||
                        empty($codigo_seguimiento)
                    )
                ) {

                    if (empty($fecha_envio)) {
                        $fecha_envio =
                            date('Y-m-d H:i:s');
                    }

                    if (empty($codigo_seguimiento)) {
                        $codigo_seguimiento =
                            generarCodigoSeguimiento(
                                $id_pedido
                            );
                    }

                    $sqlReparar = "
                        UPDATE pedidos

                        SET
                            fecha_envio = :fecha_envio,
                            codigo_seguimiento =
                                :codigo_seguimiento

                        WHERE id_pedido = :id_pedido
                    ";

                    $stmtReparar =
                        $conexion->prepare($sqlReparar);

                    $stmtReparar->bindValue(
                        ':fecha_envio',
                        $fecha_envio,
                        PDO::PARAM_STR
                    );

                    $stmtReparar->bindValue(
                        ':codigo_seguimiento',
                        $codigo_seguimiento,
                        PDO::PARAM_STR
                    );

                    $stmtReparar->bindValue(
                        ':id_pedido',
                        $id_pedido,
                        PDO::PARAM_INT
                    );

                    $stmtReparar->execute();

                    $conexion->commit();

                    $mensaje =
                        "El pedido ya estaba " .
                        ucfirst($nuevo_estado) .
                        ". Se generaron correctamente sus datos " .
                        "de seguimiento. Código: " .
                        $codigo_seguimiento .
                        ".";

                } else {

                    $conexion->commit();

                    $mensaje =
                        "El pedido ya se encuentra en ese estado.";
                }

            } else {


                /* =================================================
                   CANCELAR PEDIDO
                   DEVOLVER STOCK
                ================================================= */

                if (
                    $nuevo_estado === 'cancelado' &&
                    $estado_anterior !== 'cancelado'
                ) {

                    $sqlDetalles = "
                        SELECT
                            id_producto,
                            cantidad

                        FROM detalle_pedido

                        WHERE id_pedido = :id_pedido
                    ";

                    $stmtDetalles =
                        $conexion->prepare($sqlDetalles);

                    $stmtDetalles->bindValue(
                        ':id_pedido',
                        $id_pedido,
                        PDO::PARAM_INT
                    );

                    $stmtDetalles->execute();

                    $detalles =
                        $stmtDetalles->fetchAll(
                            PDO::FETCH_ASSOC
                        );

                    foreach ($detalles as $detalle) {

                        $sqlStock = "
                            UPDATE productos

                            SET
                                stock =
                                    stock + :cantidad

                            WHERE
                                id_producto =
                                    :id_producto
                        ";

                        $stmtStock =
                            $conexion->prepare(
                                $sqlStock
                            );

                        $stmtStock->bindValue(
                            ':cantidad',
                            (int)$detalle['cantidad'],
                            PDO::PARAM_INT
                        );

                        $stmtStock->bindValue(
                            ':id_producto',
                            (int)$detalle['id_producto'],
                            PDO::PARAM_INT
                        );

                        $stmtStock->execute();
                    }
                }


                /* =================================================
                   REACTIVAR PEDIDO CANCELADO
                ================================================= */

                if (
                    $estado_anterior === 'cancelado' &&
                    $nuevo_estado !== 'cancelado'
                ) {

                    $sqlDetalles = "
                        SELECT
                            dp.id_producto,
                            dp.cantidad,
                            p.nombre,
                            p.stock

                        FROM detalle_pedido dp

                        INNER JOIN productos p
                            ON dp.id_producto =
                               p.id_producto

                        WHERE
                            dp.id_pedido = :id_pedido

                        FOR UPDATE
                    ";

                    $stmtDetalles =
                        $conexion->prepare(
                            $sqlDetalles
                        );

                    $stmtDetalles->bindValue(
                        ':id_pedido',
                        $id_pedido,
                        PDO::PARAM_INT
                    );

                    $stmtDetalles->execute();

                    $detalles =
                        $stmtDetalles->fetchAll(
                            PDO::FETCH_ASSOC
                        );


                    /* =============================================
                       COMPROBAR STOCK
                    ============================================= */

                    foreach ($detalles as $detalle) {

                        $stockActual =
                            (int)$detalle['stock'];

                        $cantidadNecesaria =
                            (int)$detalle['cantidad'];

                        if (
                            $stockActual <
                            $cantidadNecesaria
                        ) {

                            throw new Exception(
                                "No hay suficiente stock para " .
                                "reactivar el pedido. Producto: " .
                                $detalle['nombre'] .
                                ". Stock disponible: " .
                                $stockActual .
                                ". Cantidad necesaria: " .
                                $cantidadNecesaria .
                                "."
                            );
                        }
                    }


                    /* =============================================
                       VOLVER A DESCONTAR INVENTARIO
                    ============================================= */

                    foreach ($detalles as $detalle) {

                        $sqlStock = "
                            UPDATE productos

                            SET
                                stock =
                                    stock - :cantidad

                            WHERE
                                id_producto =
                                    :id_producto
                        ";

                        $stmtStock =
                            $conexion->prepare(
                                $sqlStock
                            );

                        $stmtStock->bindValue(
                            ':cantidad',
                            (int)$detalle['cantidad'],
                            PDO::PARAM_INT
                        );

                        $stmtStock->bindValue(
                            ':id_producto',
                            (int)$detalle['id_producto'],
                            PDO::PARAM_INT
                        );

                        $stmtStock->execute();
                    }
                }


                /* =================================================
                   PREPARAR DATOS DE ENVÍO
                ================================================= */

                if (
                    in_array(
                        $nuevo_estado,
                        ['enviado', 'entregado'],
                        true
                    )
                ) {

                    if (empty($fecha_envio)) {
                        $fecha_envio =
                            date('Y-m-d H:i:s');
                    }

                    if (empty($codigo_seguimiento)) {
                        $codigo_seguimiento =
                            generarCodigoSeguimiento(
                                $id_pedido
                            );
                    }
                }


                /* =================================================
                   ACTUALIZAR PEDIDO
                ================================================= */

                $sqlActualizar = "
                    UPDATE pedidos

                    SET
                        estado = :estado,
                        fecha_envio = :fecha_envio,
                        codigo_seguimiento =
                            :codigo_seguimiento

                    WHERE
                        id_pedido = :id_pedido
                ";

                $stmtActualizar =
                    $conexion->prepare(
                        $sqlActualizar
                    );

                $stmtActualizar->bindValue(
                    ':estado',
                    $nuevo_estado,
                    PDO::PARAM_STR
                );


                if (
                    $fecha_envio === null ||
                    $fecha_envio === ''
                ) {

                    $stmtActualizar->bindValue(
                        ':fecha_envio',
                        null,
                        PDO::PARAM_NULL
                    );

                } else {

                    $stmtActualizar->bindValue(
                        ':fecha_envio',
                        $fecha_envio,
                        PDO::PARAM_STR
                    );
                }


                if (
                    $codigo_seguimiento === null ||
                    $codigo_seguimiento === ''
                ) {

                    $stmtActualizar->bindValue(
                        ':codigo_seguimiento',
                        null,
                        PDO::PARAM_NULL
                    );

                } else {

                    $stmtActualizar->bindValue(
                        ':codigo_seguimiento',
                        $codigo_seguimiento,
                        PDO::PARAM_STR
                    );
                }

                $stmtActualizar->bindValue(
                    ':id_pedido',
                    $id_pedido,
                    PDO::PARAM_INT
                );

                $stmtActualizar->execute();

                $conexion->commit();


                /* =================================================
                   MENSAJE FINAL
                ================================================= */

                if ($nuevo_estado === 'cancelado') {

                    $mensaje =
                        "Pedido cancelado correctamente. " .
                        "Los productos fueron devueltos " .
                        "al inventario.";

                } elseif (
                    $estado_anterior === 'cancelado'
                ) {

                    if (
                        in_array(
                            $nuevo_estado,
                            ['enviado', 'entregado'],
                            true
                        )
                    ) {

                        $mensaje =
                            "Pedido reactivado correctamente. " .
                            "Código de seguimiento: " .
                            $codigo_seguimiento .
                            ".";

                    } else {

                        $mensaje =
                            "Pedido reactivado correctamente. " .
                            "El inventario fue actualizado.";
                    }

                } elseif ($nuevo_estado === 'enviado') {

                    $mensaje =
                        "Pedido marcado como enviado " .
                        "correctamente. Código de seguimiento: " .
                        $codigo_seguimiento .
                        ".";

                } elseif ($nuevo_estado === 'entregado') {

                    $mensaje =
                        "Pedido marcado como entregado " .
                        "correctamente. Código de seguimiento: " .
                        $codigo_seguimiento .
                        ".";

                } else {

                    $mensaje =
                        "Estado del pedido actualizado correctamente.";
                }
            }

        } catch (Exception $e) {

            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}


/* =========================================================
   OBTENER TODOS LOS PEDIDOS
========================================================= */

try {

    $sql = "
        SELECT
            p.id_pedido,
            p.fecha_pedido,
            p.total,
            p.estado,

            p.metodo_pago,
            p.estado_pago,
            p.comprobante_pago,

            p.fecha_envio,
            p.codigo_seguimiento,

            u.id_usuario,
            u.nombre,
            u.apellido,
            u.email

        FROM pedidos p

        INNER JOIN usuarios u
            ON p.id_usuario = u.id_usuario

        ORDER BY p.id_pedido DESC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute();

    $pedidos =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die(
        "Error al consultar los pedidos: " .
        htmlspecialchars($e->getMessage())
    );
}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

$totalPedidos = count($pedidos);

$pendientes = 0;
$procesando = 0;
$enviados = 0;
$entregados = 0;
$cancelados = 0;

$totalVentas = 0;

foreach ($pedidos as $pedido) {

    $estado = strtolower(
        trim($pedido['estado'])
    );

    if ($estado === 'pendiente') {

        $pendientes++;

    } elseif ($estado === 'procesando') {

        $procesando++;

    } elseif ($estado === 'enviado') {

        $enviados++;

    } elseif ($estado === 'entregado') {

        $entregados++;

        /*
         * Solo los pedidos entregados
         * cuentan como ventas completadas.
         */

        $totalVentas +=
            (float)$pedido['total'];

    } elseif ($estado === 'cancelado') {

        $cancelados++;
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

    <title>
        Administración de pedidos | Roséa Boutique
    </title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #fff8fa;
            color: #45252d;
        }

        /* =====================================================
           HERO
        ===================================================== */

        .hero {
            background: #fff0f4;
            text-align: center;
            padding: 45px 20px;
        }

        .hero h1 {
            font-family: Georgia, serif;
            font-size: 42px;
            color: #c57282;
            margin-bottom: 10px;
        }

        .hero p {
            color: #ad5968;
            font-size: 15px;
        }

        /* =====================================================
           NAVEGACIÓN
        ===================================================== */

        .navbar {
            background: white;
            border-bottom: 1px solid #f3cbd3;
        }

        .nav-container {
            max-width: 1100px;
            margin: auto;
            min-height: 72px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            padding: 0 20px;
        }

        .logo {
            font-family: Georgia, serif;
            font-size: 25px;
            font-weight: bold;
            color: #c57282;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .nav-links a {
            color: #5c2c36;
            text-decoration: none;
            font-size: 14px;
        }

        .nav-links a:hover {
            color: #c57282;
        }

        /* =====================================================
           CONTENIDO
        ===================================================== */

        .container {
            max-width: 1050px;
            margin: 0 auto;
            padding: 45px 20px 80px;
        }

        .titulo {
            text-align: center;
            margin-bottom: 35px;
        }

        .titulo h2 {
            font-family: Georgia, serif;
            color: #c57282;
            font-size: 34px;
            margin-bottom: 10px;
        }

        .titulo p {
            color: #b25f6d;
        }

        /* =====================================================
           MENSAJES
        ===================================================== */

        .mensaje {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 25px;
            background: #edf9ef;
            border: 1px solid #a9d8b0;
            color: #34713d;
        }

        .error {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 25px;
            background: #fff0f0;
            border: 1px solid #e9a5a5;
            color: #a13c3c;
        }

        /* =====================================================
           ESTADÍSTICAS
        ===================================================== */

        .estadisticas {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 40px;
        }

        .stat {
            background: white;
            border: 1px solid #f1c7d0;
            border-radius: 15px;
            padding: 22px;
        }

        .stat span {
            display: block;
            color: #c26375;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .stat strong {
            font-family: Georgia, serif;
            font-size: 25px;
            color: #44242c;
        }

        /* =====================================================
           RESUMEN
        ===================================================== */

        .resumen-estados {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 40px;
        }

        .resumen-item {
            background: #fff4f6;
            border: 1px solid #f2d0d7;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }

        .resumen-item span {
            display: block;
            font-size: 12px;
            color: #b95f70;
            text-transform: uppercase;
            margin-bottom: 7px;
        }

        .resumen-item strong {
            font-family: Georgia, serif;
            font-size: 22px;
        }

        /* =====================================================
           PEDIDOS
        ===================================================== */

        .seccion-titulo {
            font-family: Georgia, serif;
            font-size: 27px;
            margin-bottom: 20px;
        }

        .pedido {
            background: white;
            border: 1px solid #efc7cf;
            border-radius: 17px;
            margin-bottom: 22px;
            overflow: hidden;
        }

        .pedido-header {
            background: #fff1f4;
            padding: 20px 22px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;
        }

        .pedido-header h3 {
            font-family: Georgia, serif;
            font-size: 22px;
            margin-bottom: 5px;
        }

        .fecha {
            color: #a85d69;
            font-size: 13px;
        }

        /* =====================================================
           ESTADOS DEL PEDIDO
        ===================================================== */

        .estado {
            border-radius: 30px;
            padding: 8px 16px;
            font-size: 12px;
            text-transform: capitalize;
            font-weight: bold;
        }

        .estado-pendiente {
            background: #fff1cf;
            color: #936f13;
        }

        .estado-procesando {
            background: #e9efff;
            color: #4b65a5;
        }

        .estado-enviado {
            background: #e9f4ff;
            color: #3775a5;
        }

        .estado-entregado {
            background: #e5f7e8;
            color: #3b7c47;
        }

        .estado-cancelado {
            background: #ffe7e7;
            color: #a84545;
        }

        /* =====================================================
           INFORMACIÓN
        ===================================================== */

        .pedido-body {
            padding: 22px;
        }

        .datos {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 22px;
        }

        .dato {
            background: #fff6f8;
            border: 1px solid #f5d5db;
            border-radius: 11px;
            padding: 16px;
        }

        .dato span {
            display: block;
            color: #c36777;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 7px;
        }

        .dato strong {
            font-family: Georgia, serif;
            font-size: 16px;
        }

        .precio {
            color: #c76d7d;
            font-size: 20px !important;
        }

        /* =====================================================
           INFORMACIÓN DEL PAGO
        ===================================================== */

        .pago-info {
            background: #fff9fa;
            border: 1px solid #efcbd3;
            border-radius: 12px;
            padding: 17px;
            margin-bottom: 22px;

            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .pago-dato span {
            display: block;
            color: #c36777;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .pago-dato strong {
            font-family: Georgia, serif;
            font-size: 16px;
        }

        .pago-estado {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-family: Arial, Helvetica, sans-serif !important;
            font-size: 12px !important;
        }

        .pago-pendiente {
            background: #fff1cf;
            color: #936f13;
        }

        .pago-verificado {
            background: #e5f7e8;
            color: #3b7c47;
        }

        .pago-rechazado {
            background: #ffe7e7;
            color: #a84545;
        }

        .pago-aviso {
            grid-column: 1 / -1;
            padding: 12px 14px;
            border-radius: 9px;
            line-height: 1.5;
            font-size: 13px;
        }

        .pago-aviso-pendiente {
            background: #fff8e8;
            border: 1px solid #eed99d;
            color: #84661d;
        }

        .pago-aviso-verificado {
            background: #edf9ef;
            border: 1px solid #a9d8b0;
            color: #34713d;
        }

        .pago-aviso-rechazado {
            background: #fff0f0;
            border: 1px solid #e9a5a5;
            color: #a13c3c;
        }

        /* =====================================================
           INFORMACIÓN DE ENVÍO
        ===================================================== */

        .envio-info {
            background: #fff4f7;
            border: 1px solid #efcbd3;
            border-radius: 12px;
            padding: 17px;
            margin-bottom: 22px;

            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .envio-dato span {
            display: block;
            color: #c36777;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .envio-dato strong {
            font-family: Georgia, serif;
            font-size: 16px;
        }

        .codigo {
            color: #a74f61;
            letter-spacing: 1px;
        }

        /* =====================================================
           BOTÓN DETALLES
        ===================================================== */

        .detalle-pedido {
            margin-bottom: 22px;
        }

        .btn-detalles {
            display: inline-block;
            padding: 12px 22px;

            background: white;
            color: #c57584;

            border: 1px solid #c57584;
            border-radius: 9px;

            text-decoration: none;
            font-size: 14px;
            font-weight: bold;

            transition: 0.2s ease;
        }

        .btn-detalles:hover {
            background: #c57584;
            color: white;
        }

        /* =====================================================
           CAMBIAR ESTADO
        ===================================================== */

        .acciones {
            border-top: 1px solid #f3d1d7;
            padding-top: 20px;
        }

        .acciones label {
            display: block;
            font-weight: bold;
            margin-bottom: 9px;
        }

        .form-estado {
            display: flex;
            gap: 10px;
        }

        select {
            flex: 1;
            padding: 12px;

            border: 1px solid #e8aeb9;
            border-radius: 9px;

            background: white;
            color: #512b33;

            outline: none;
        }

        button {
            border: none;
            border-radius: 9px;

            padding: 12px 24px;

            background: #c57584;
            color: white;

            font-weight: bold;
            cursor: pointer;

            transition: 0.2s;
        }

        button:hover {
            background: #ad5f6e;
        }

        option:disabled {
            color: #aaa;
        }

        .bloqueo-pago {
            margin-top: 12px;
            padding: 12px 14px;

            background: #fff8e8;
            border: 1px solid #eed99d;
            border-radius: 9px;

            color: #84661d;
            font-size: 13px;
            line-height: 1.5;
        }

        /* =====================================================
           SIN PEDIDOS
        ===================================================== */

        .sin-pedidos {
            background: white;
            border: 1px solid #efc7cf;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
        }

        .sin-pedidos h3 {
            font-family: Georgia, serif;
            margin-bottom: 10px;
        }

        /* =====================================================
           FOOTER
        ===================================================== */

        footer {
            background: #68434a;
            color: white;
            text-align: center;
            padding: 35px 20px;
        }

        footer h3 {
            font-family: Georgia, serif;
            margin-bottom: 7px;
        }

        footer p {
            font-size: 12px;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 850px) {

            .estadisticas {
                grid-template-columns: repeat(2, 1fr);
            }

            .resumen-estados {
                grid-template-columns: 1fr;
            }

            .datos {
                grid-template-columns: 1fr;
            }

            .pago-info {
                grid-template-columns: 1fr;
            }

            .pago-aviso {
                grid-column: auto;
            }

            .envio-info {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 650px) {

            .nav-container {
                flex-direction: column;
                padding-top: 18px;
                padding-bottom: 18px;
                gap: 15px;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 15px;
            }

            .hero h1 {
                font-size: 33px;
            }

            .estadisticas {
                grid-template-columns: 1fr;
            }

            .pedido-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .form-estado {
                flex-direction: column;
            }

            .btn-detalles {
                width: 100%;
                text-align: center;
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

    <div class="nav-container">

        <a
            href="productos.php"
            class="logo"
        >
            🌹 Roséa Boutique
        </a>

        <div class="nav-links">

            <a href="productos.php">
                Colección
            </a>

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
     CONTENIDO
====================================================== -->

<main class="container">


    <div class="titulo">

        <h2>
            Administración de pedidos
        </h2>

        <p>
            Consulta y administra las compras de
            Roséa Boutique ♡
        </p>

    </div>


    <!-- =================================================
         MENSAJES
    ================================================== -->

    <?php if (!empty($mensaje)): ?>

        <div class="mensaje">
            ✓ <?php echo htmlspecialchars($mensaje); ?>
        </div>

    <?php endif; ?>


    <?php if (!empty($error)): ?>

        <div class="error">
            ⚠ <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <!-- =================================================
         ESTADÍSTICAS
    ================================================== -->

    <section class="estadisticas">

        <div class="stat">

            <span>
                Total pedidos
            </span>

            <strong>
                <?php echo $totalPedidos; ?>
            </strong>

        </div>


        <div class="stat">

            <span>
                Pendientes
            </span>

            <strong>
                <?php echo $pendientes; ?>
            </strong>

        </div>


        <div class="stat">

            <span>
                Entregados
            </span>

            <strong>
                <?php echo $entregados; ?>
            </strong>

        </div>


        <div class="stat">

            <span>
                Ventas completadas
            </span>

            <strong>
                RD$<?php
                echo number_format(
                    $totalVentas,
                    2
                );
                ?>
            </strong>

        </div>

    </section>


    <!-- =================================================
         RESUMEN DE ESTADOS
    ================================================== -->

    <section class="resumen-estados">

        <div class="resumen-item">

            <span>
                Procesando
            </span>

            <strong>
                <?php echo $procesando; ?>
            </strong>

        </div>


        <div class="resumen-item">

            <span>
                Enviados
            </span>

            <strong>
                <?php echo $enviados; ?>
            </strong>

        </div>


        <div class="resumen-item">

            <span>
                Cancelados
            </span>

            <strong>
                <?php echo $cancelados; ?>
            </strong>

        </div>

    </section>


    <!-- =================================================
         PEDIDOS
    ================================================== -->

    <h2 class="seccion-titulo">
        Pedidos registrados
    </h2>


    <?php if (count($pedidos) > 0): ?>


        <?php foreach ($pedidos as $pedido): ?>


            <?php

            $estadoActual = strtolower(
                trim($pedido['estado'])
            );

            $metodoPagoActual = strtolower(
                trim($pedido['metodo_pago'] ?? '')
            );

            $estadoPagoActual = strtolower(
                trim($pedido['estado_pago'] ?? 'pendiente')
            );

            $claseEstado =
                'estado-pendiente';

            if ($estadoActual === 'procesando') {

                $claseEstado =
                    'estado-procesando';

            } elseif ($estadoActual === 'enviado') {

                $claseEstado =
                    'estado-enviado';

            } elseif ($estadoActual === 'entregado') {

                $claseEstado =
                    'estado-entregado';

            } elseif ($estadoActual === 'cancelado') {

                $claseEstado =
                    'estado-cancelado';
            }


            $clasePago = 'pago-pendiente';

            if ($estadoPagoActual === 'verificado') {

                $clasePago = 'pago-verificado';

            } elseif ($estadoPagoActual === 'rechazado') {

                $clasePago = 'pago-rechazado';
            }


            $transferenciaBloqueada = (
                $metodoPagoActual === 'transferencia' &&
                $estadoPagoActual !== 'verificado'
            );

            ?>


            <article class="pedido">


                <!-- =====================================
                     CABECERA
                ====================================== -->

                <div class="pedido-header">

                    <div>

                        <h3>
                            Pedido
                            #<?php
                            echo (int)$pedido['id_pedido'];
                            ?>
                        </h3>

                        <div class="fecha">

                            <?php

                            echo date(
                                'd/m/Y h:i A',
                                strtotime(
                                    $pedido['fecha_pedido']
                                )
                            );

                            ?>

                        </div>

                    </div>


                    <div
                        class="estado <?php
                        echo $claseEstado;
                        ?>"
                    >

                        <?php

                        echo htmlspecialchars(
                            ucfirst($estadoActual)
                        );

                        ?>

                    </div>

                </div>


                <!-- =====================================
                     CUERPO
                ====================================== -->

                <div class="pedido-body">


                    <div class="datos">


                        <div class="dato">

                            <span>
                                Cliente
                            </span>

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $pedido['nombre'] .
                                    ' ' .
                                    $pedido['apellido']
                                );

                                ?>

                            </strong>

                        </div>


                        <div class="dato">

                            <span>
                                Correo electrónico
                            </span>

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $pedido['email']
                                );

                                ?>

                            </strong>

                        </div>


                        <div class="dato">

                            <span>
                                Total
                            </span>

                            <strong class="precio">

                                RD$<?php

                                echo number_format(
                                    (float)$pedido['total'],
                                    2
                                );

                                ?>

                            </strong>

                        </div>

                    </div>


                    <!-- =================================
                         INFORMACIÓN DEL PAGO
                    ================================== -->

                    <div class="pago-info">


                        <div class="pago-dato">

                            <span>
                                Método de pago
                            </span>

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    nombreMetodoPago(
                                        $metodoPagoActual
                                    )
                                );

                                ?>

                            </strong>

                        </div>


                        <div class="pago-dato">

                            <span>
                                Estado del pago
                            </span>

                            <?php if (
                                $metodoPagoActual === 'transferencia'
                            ): ?>

                                <strong
                                    class="pago-estado <?php
                                    echo $clasePago;
                                    ?>"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        nombreEstadoPago(
                                            $estadoPagoActual
                                        )
                                    );

                                    ?>

                                </strong>

                            <?php else: ?>

                                <strong>
                                    Pago en efectivo
                                </strong>

                            <?php endif; ?>

                        </div>


                        <?php if (
                            $metodoPagoActual === 'transferencia'
                        ): ?>


                            <?php if (
                                $estadoPagoActual === 'verificado'
                            ): ?>

                                <div
                                    class="
                                        pago-aviso
                                        pago-aviso-verificado
                                    "
                                >
                                    🟢 El pago fue verificado.
                                    Este pedido puede continuar
                                    normalmente con su preparación,
                                    envío y entrega.
                                </div>


                            <?php elseif (
                                $estadoPagoActual === 'rechazado'
                            ): ?>

                                <div
                                    class="
                                        pago-aviso
                                        pago-aviso-rechazado
                                    "
                                >
                                    🔴 El comprobante fue rechazado.
                                    El cliente debe enviar un nuevo
                                    comprobante antes de continuar
                                    procesando el pedido.
                                </div>


                            <?php else: ?>

                                <div
                                    class="
                                        pago-aviso
                                        pago-aviso-pendiente
                                    "
                                >

                                    <?php if (
                                        !empty(
                                            $pedido[
                                                'comprobante_pago'
                                            ]
                                        )
                                    ): ?>

                                        🟡 El cliente ya envió el
                                        comprobante, pero todavía
                                        está pendiente de
                                        verificación.

                                    <?php else: ?>

                                        🟡 El cliente todavía no ha
                                        enviado el comprobante de
                                        transferencia.

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>


                        <?php endif; ?>


                    </div>


                    <!-- =================================
                         DATOS DE ENVÍO
                    ================================== -->

                    <?php
                    if (
                        !empty(
                            $pedido['codigo_seguimiento']
                        ) ||
                        !empty(
                            $pedido['fecha_envio']
                        )
                    ):
                    ?>

                        <div class="envio-info">


                            <div class="envio-dato">

                                <span>
                                    Código de seguimiento
                                </span>

                                <strong class="codigo">

                                    <?php

                                    echo !empty(
                                        $pedido[
                                            'codigo_seguimiento'
                                        ]
                                    )
                                        ? htmlspecialchars(
                                            $pedido[
                                                'codigo_seguimiento'
                                            ]
                                        )
                                        : 'Pendiente';

                                    ?>

                                </strong>

                            </div>


                            <div class="envio-dato">

                                <span>
                                    Fecha de envío
                                </span>

                                <strong>

                                    <?php

                                    if (
                                        !empty(
                                            $pedido['fecha_envio']
                                        )
                                    ) {

                                        echo date(
                                            'd/m/Y h:i A',
                                            strtotime(
                                                $pedido[
                                                    'fecha_envio'
                                                ]
                                            )
                                        );

                                    } else {

                                        echo 'Pendiente';
                                    }

                                    ?>

                                </strong>

                            </div>

                        </div>

                    <?php endif; ?>


                    <!-- =================================
                         DETALLES
                    ================================== -->

                    <div class="detalle-pedido">

                        <a
                            href="admin_detalle_pedido.php?id=<?php
                            echo (int)$pedido['id_pedido'];
                            ?>"
                            class="btn-detalles"
                        >
                            Ver detalles ♡
                        </a>

                    </div>


                    <!-- =================================
                         CAMBIAR ESTADO
                    ================================== -->

                    <div class="acciones">


                        <form
                            method="POST"
                            action="admin_pedidos.php"
                        >


                            <input
                                type="hidden"
                                name="id_pedido"
                                value="<?php
                                echo (int)$pedido['id_pedido'];
                                ?>"
                            >


                            <label>
                                Estado del pedido
                            </label>


                            <div class="form-estado">


                                <select
                                    name="estado"
                                    required
                                >


                                    <option
                                        value="pendiente"
                                        <?php
                                        echo $estadoActual ===
                                            'pendiente'
                                            ? 'selected'
                                            : '';
                                        ?>
                                    >
                                        Pendiente
                                    </option>


                                    <option
                                        value="procesando"

                                        <?php
                                        echo $estadoActual ===
                                            'procesando'
                                            ? 'selected'
                                            : '';
                                        ?>

                                        <?php
                                        echo $transferenciaBloqueada
                                            ? 'disabled'
                                            : '';
                                        ?>
                                    >
                                        Procesando<?php
                                        echo $transferenciaBloqueada
                                            ? ' — pago sin verificar'
                                            : '';
                                        ?>
                                    </option>


                                    <option
                                        value="enviado"

                                        <?php
                                        echo $estadoActual ===
                                            'enviado'
                                            ? 'selected'
                                            : '';
                                        ?>

                                        <?php
                                        echo $transferenciaBloqueada
                                            ? 'disabled'
                                            : '';
                                        ?>
                                    >
                                        Enviado<?php
                                        echo $transferenciaBloqueada
                                            ? ' — pago sin verificar'
                                            : '';
                                        ?>
                                    </option>


                                    <option
                                        value="entregado"

                                        <?php
                                        echo $estadoActual ===
                                            'entregado'
                                            ? 'selected'
                                            : '';
                                        ?>

                                        <?php
                                        echo $transferenciaBloqueada
                                            ? 'disabled'
                                            : '';
                                        ?>
                                    >
                                        Entregado<?php
                                        echo $transferenciaBloqueada
                                            ? ' — pago sin verificar'
                                            : '';
                                        ?>
                                    </option>


                                    <option
                                        value="cancelado"
                                        <?php
                                        echo $estadoActual ===
                                            'cancelado'
                                            ? 'selected'
                                            : '';
                                        ?>
                                    >
                                        Cancelado
                                    </option>


                                </select>


                                <button type="submit">
                                    ✓ Actualizar estado
                                </button>


                            </div>


                            <?php if (
                                $transferenciaBloqueada
                            ): ?>

                                <div class="bloqueo-pago">

                                    🔒 Este pedido fue realizado
                                    mediante transferencia.

                                    <?php if (
                                        $estadoPagoActual ===
                                        'rechazado'
                                    ): ?>

                                        El comprobante fue rechazado.
                                        No podrá pasar a Procesando,
                                        Enviado o Entregado hasta que
                                        el cliente envíe otro
                                        comprobante y sea verificado.

                                    <?php else: ?>

                                        El pago todavía no está
                                        verificado. No podrá pasar a
                                        Procesando, Enviado o
                                        Entregado hasta verificar el
                                        comprobante.

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>


                        </form>

                    </div>


                </div>


            </article>


        <?php endforeach; ?>


    <?php else: ?>


        <div class="sin-pedidos">

            <h3>
                Todavía no hay pedidos 🌹
            </h3>

            <p>
                Los pedidos realizados por los clientes
                aparecerán aquí.
            </p>

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
        Administración de pedidos · Panel administrativo
    </p>

</footer>


</body>

</html>