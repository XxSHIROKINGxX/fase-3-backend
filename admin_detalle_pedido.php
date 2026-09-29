<?php

session_start();

require_once __DIR__ . '/config/conexion.php';

/* =========================================================

   SEGURIDAD: SOLO ADMINISTRADORES

\========================================================= */

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

   OBTENER ID DEL PEDIDO

\========================================================= */

$id_pedido = isset($_GET['id'])

    ? (int)$_GET['id']

    : 0;

if ($id_pedido <= 0) {

    header("Location: admin_pedidos.php");

    exit;

}

/* =========================================================

   MENSAJES

\========================================================= */

$mensaje = '';

$tipoMensaje = '';

/* =========================================================

   PROCESAR VERIFICACIÓN DEL COMPROBANTE

\========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion_pago = isset($_POST['accion_pago'])

        ? strtolower(trim($_POST['accion_pago']))

        : '';

    if (

        $accion_pago === 'verificar' ||

        $accion_pago === 'rechazar'

    ) {

        try {

            /*

             * Primero comprobamos que el pedido exista

             * y que realmente sea una transferencia.

             */

            $sqlPago = "

                SELECT

                    id_pedido,

                    metodo_pago,

                    comprobante_pago,

                    estado_pago

                FROM pedidos

                WHERE id_pedido = :id_pedido

                LIMIT 1

            ";

            $stmtPago = $conexion->prepare($sqlPago);

            $stmtPago->bindValue(

                ':id_pedido',

                $id_pedido,

                PDO::PARAM_INT

            );

            $stmtPago->execute();

            $pedidoPago = $stmtPago->fetch(PDO::FETCH_ASSOC);

            if (!$pedidoPago) {

                $mensaje = 'El pedido no existe.';

                $tipoMensaje = 'error';

            } elseif (

                strtolower(trim((string)$pedidoPago['metodo_pago']))

                !== 'transferencia'

            ) {

                $mensaje = 'Este pedido no utiliza transferencia bancaria.';

                $tipoMensaje = 'error';

            } elseif (empty($pedidoPago['comprobante_pago'])) {

                $mensaje = 'Este pedido todavía no tiene un comprobante de pago.';

                $tipoMensaje = 'error';

            } else {

                $nuevoEstadoPago = $accion_pago === 'verificar'

                    ? 'verificado'

                    : 'rechazado';

                $sqlActualizarPago = "

                    UPDATE pedidos

                    SET estado_pago = :estado_pago

                    WHERE id_pedido = :id_pedido

                ";

                $stmtActualizarPago = $conexion->prepare(

                    $sqlActualizarPago

                );

                $stmtActualizarPago->bindValue(

                    ':estado_pago',

                    $nuevoEstadoPago,

                    PDO::PARAM_STR

                );

                $stmtActualizarPago->bindValue(

                    ':id_pedido',

                    $id_pedido,

                    PDO::PARAM_INT

                );

                $stmtActualizarPago->execute();

                if ($nuevoEstadoPago === 'verificado') {

                    $mensaje =

                        '✓ El comprobante del pedido #' .

                        $id_pedido .

                        ' fue verificado correctamente.';

                    $tipoMensaje = 'exito';

                } else {

                    $mensaje =

                        'El comprobante del pedido #' .

                        $id_pedido .

                        ' fue rechazado. El cliente podrá subir uno nuevo.';

                    $tipoMensaje = 'advertencia';

                }

            }

        } catch (PDOException $e) {

            $mensaje =

                'No se pudo actualizar el estado del pago: ' .

                $e->getMessage();

            $tipoMensaje = 'error';

        }

    }

}

/* =========================================================

   PROCESAR GESTIÓN DEL ENVÍO

========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion_envio = isset($_POST['accion_envio']) ? strtolower(trim($_POST['accion_envio'])) : '';

    if ($accion_envio === 'guardar_envio') {

        try {

            $sqlEnvioActual = "SELECT id_pedido, estado, metodo_entrega, metodo_pago, estado_pago, empresa_envio, codigo_seguimiento, fecha_envio FROM pedidos WHERE id_pedido = :id_pedido LIMIT 1";

            $stmtEnvioActual = $conexion->prepare($sqlEnvioActual);

            $stmtEnvioActual->bindValue(':id_pedido', $id_pedido, PDO::PARAM_INT);

            $stmtEnvioActual->execute();

            $pedidoEnvioActual = $stmtEnvioActual->fetch(PDO::FETCH_ASSOC);

            if (!$pedidoEnvioActual) { throw new Exception('El pedido no existe.'); }

            $estadoPedidoEnvio = strtolower(trim((string)$pedidoEnvioActual['estado']));

            $metodoPagoEnvio = strtolower(trim((string)$pedidoEnvioActual['metodo_pago']));

            $estadoPagoEnvio = strtolower(trim((string)$pedidoEnvioActual['estado_pago']));

            $metodoEntregaEnvio = strtolower(trim((string)$pedidoEnvioActual['metodo_entrega']));

            if ($estadoPedidoEnvio === 'cancelado') { throw new Exception('No puedes registrar un envío para un pedido cancelado.'); }

            if ($estadoPedidoEnvio === 'entregado') { throw new Exception('Este pedido ya fue entregado. No puede volver a marcarse como Enviado.'); }

            if ($metodoPagoEnvio === 'transferencia' && $estadoPagoEnvio !== 'verificado') { throw new Exception('No puedes despachar este pedido porque la transferencia todavía no está verificada.'); }

            $empresaEnvio = isset($_POST['empresa_envio']) ? trim($_POST['empresa_envio']) : '';

            $codigoSeguimiento = isset($_POST['codigo_seguimiento']) ? trim($_POST['codigo_seguimiento']) : '';

            $fechaEnvioInput = isset($_POST['fecha_envio']) ? trim($_POST['fecha_envio']) : '';

            if ($metodoEntregaEnvio === 'delivery' && $empresaEnvio === '') { throw new Exception('Debes indicar la empresa de envío.'); }

            if ($metodoEntregaEnvio === 'retiro' && $empresaEnvio === '') { $empresaEnvio = 'Retiro en tienda'; }

            if ($codigoSeguimiento === '') { $codigoSeguimiento = 'ROSEA-' . str_pad((string)$id_pedido, 6, '0', STR_PAD_LEFT); }

            if ($fechaEnvioInput === '') { $fechaEnvio = date('Y-m-d H:i:s'); } else { $timestampEnvio = strtotime($fechaEnvioInput); if ($timestampEnvio === false) { throw new Exception('La fecha de envío no es válida.'); } $fechaEnvio = date('Y-m-d H:i:s', $timestampEnvio); }

            $sqlGuardarEnvio = "UPDATE pedidos SET empresa_envio = :empresa_envio, codigo_seguimiento = :codigo_seguimiento, fecha_envio = :fecha_envio, estado = 'enviado' WHERE id_pedido = :id_pedido AND estado NOT IN ('entregado', 'cancelado')";

            $stmtGuardarEnvio = $conexion->prepare($sqlGuardarEnvio);

            $stmtGuardarEnvio->bindValue(':empresa_envio', $empresaEnvio, PDO::PARAM_STR);

            $stmtGuardarEnvio->bindValue(':codigo_seguimiento', $codigoSeguimiento, PDO::PARAM_STR);

            $stmtGuardarEnvio->bindValue(':fecha_envio', $fechaEnvio, PDO::PARAM_STR);

            $stmtGuardarEnvio->bindValue(':id_pedido', $id_pedido, PDO::PARAM_INT);

            $stmtGuardarEnvio->execute();

            if ($stmtGuardarEnvio->rowCount() === 0) {
                throw new Exception('No se pudo modificar el pedido. Puede que ya esté entregado o cancelado.');
            }

            $mensaje = '✓ Envío registrado correctamente. Código de seguimiento: ' . $codigoSeguimiento . '.';

            $tipoMensaje = 'exito';

        } catch (Exception $e) { $mensaje = $e->getMessage(); $tipoMensaje = 'error'; }

    }

}

/* =========================================================

   CONSULTAR PEDIDO

\========================================================= */

try {

    $sql = "

        SELECT

            p.id_pedido,

            p.fecha_pedido,

            p.total,

            p.estado,

            p.telefono,

            p.provincia,

            p.ciudad,

            p.direccion,

            p.referencia,

            p.metodo_entrega,

            p.metodo_pago,

            p.empresa_envio,

            p.comprobante_pago,

            p.estado_pago,

            p.fecha_comprobante,

            p.fecha_envio,

            p.codigo_seguimiento,

            u.id_usuario,

            u.nombre,

            u.apellido,

            u.email

        FROM pedidos p

        INNER JOIN usuarios u

            ON p.id_usuario = u.id_usuario

        WHERE p.id_pedido = :id_pedido

        LIMIT 1

    ";

    $stmt = $conexion->prepare($sql);

    $stmt->bindValue(

        ':id_pedido',

        $id_pedido,

        PDO::PARAM_INT

    );

    $stmt->execute();

    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {

        header("Location: admin_pedidos.php");

        exit;

    }

    /* =====================================================

       CONSULTAR PRODUCTOS DEL PEDIDO

    ===================================================== */

    $sqlDetalle = "

        SELECT

            d.id_detalle,

            d.cantidad,

            d.precio_unitario,

            d.subtotal,

            pr.id_producto,

            pr.nombre,

            pr.descripcion,

            pr.categoria,

            pr.imagen

        FROM detalle_pedido d

        INNER JOIN productos pr

            ON d.id_producto = pr.id_producto

        WHERE d.id_pedido = :id_pedido

        ORDER BY d.id_detalle ASC

    ";

    $stmtDetalle = $conexion->prepare($sqlDetalle);

    $stmtDetalle->bindValue(

        ':id_pedido',

        $id_pedido,

        PDO::PARAM_INT

    );

    $stmtDetalle->execute();

    $detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die(

        "Error al consultar el pedido: " .

        htmlspecialchars($e->getMessage())

    );

}

/* =========================================================

   FORMATEAR FECHA

\========================================================= */

$fechaFormateada = date(

    'd/m/Y h:i A',

    strtotime($pedido['fecha_pedido'])

);

/* =========================================================

   ESTADO DEL PEDIDO

\========================================================= */

$estadoActual = strtolower(

    trim($pedido['estado'] ?? 'pendiente')

);

/* =========================================================

   ESTADO DEL PAGO

\========================================================= */

$estadoPago = strtolower(

    trim($pedido['estado_pago'] ?? 'pendiente')

);

$metodoPago = strtolower(

    trim($pedido['metodo_pago'] ?? '')

);

/* =========================================================

   FUNCIONES

\========================================================= */

function nombreEstado($estado)

{

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

            return ucfirst($estado);

    }

}

function textoMetodoEntrega($metodo)

{

    switch (strtolower(trim((string)$metodo))) {

        case 'delivery':

            return 'Delivery';

        case 'retiro':

            return 'Retiro en tienda';

        default:

            return 'No especificado';

    }

}

function textoMetodoPago($metodo)

{

    switch (strtolower(trim((string)$metodo))) {

        case 'efectivo':

            return 'Efectivo';

        case 'transferencia':

            return 'Transferencia bancaria';

        default:

            return 'No especificado';

    }

}

function nombreEstadoPago($estado)

{

    switch (strtolower(trim((string)$estado))) {

        case 'verificado':

            return 'Pago verificado';

        case 'rechazado':

            return 'Comprobante rechazado';

        case 'pendiente':

        default:

            return 'Pendiente de verificación';

    }

}

/* =========================================================

   CLASE DEL ESTADO DEL PEDIDO

\========================================================= */

$estadoClase = 'pendiente';

switch ($estadoActual) {

    case 'procesando':

        $estadoClase = 'procesando';

        break;

    case 'enviado':

        $estadoClase = 'enviado';

        break;

    case 'entregado':

        $estadoClase = 'entregado';

        break;

    case 'cancelado':

        $estadoClase = 'cancelado';

        break;

}

/* =========================================================

   CLASE DEL ESTADO DEL PAGO

\========================================================= */

$estadoPagoClase = 'pago-pendiente';

if ($estadoPago === 'verificado') {

    $estadoPagoClase = 'pago-verificado';

}

if ($estadoPago === 'rechazado') {

    $estadoPagoClase = 'pago-rechazado';

}

/* =========================================================

   FECHA DEL COMPROBANTE

\========================================================= */

$fechaComprobanteFormateada = '';

if (!empty($pedido['fecha_comprobante'])) {

    $timestampComprobante = strtotime(

        $pedido['fecha_comprobante']

    );

    if ($timestampComprobante !== false) {

        $fechaComprobanteFormateada = date(

            'd/m/Y h:i A',

            $timestampComprobante

        );

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

    Pedido #<?php echo (int)$pedido['id_pedido']; ?>

    | Roséa Boutique

</title>

<style>

/* =========================================================

   GENERAL

\========================================================= */

* {

    margin: 0;

    padding: 0;

    box-sizing: border-box;

}

body {

    font-family: Arial, Helvetica, sans-serif;

    background: #fff8fa;

    color: #402326;

    min-height: 100vh;

}

a {

    text-decoration: none;

    color: inherit;

}

/* =========================================================

   CABECERA SUPERIOR

\========================================================= */

.hero {

    background: #fff0f4;

    text-align: center;

    padding: 48px 20px;

}

.hero h1 {

    font-family: Georgia, "Times New Roman", serif;

    color: #c66f7e;

    font-size: 42px;

    margin-bottom: 10px;

}

.hero p {

    color: #b85f6d;

    font-size: 15px;

}

/* =========================================================

   NAVEGACIÓN

\========================================================= */

nav {

    background: white;

    border-bottom: 1px solid #f1cbd2;

}

.nav-container {

    max-width: 1100px;

    margin: auto;

    min-height: 72px;

    padding: 0 20px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

}

.logo {

    font-family: Georgia, "Times New Roman", serif;

    color: #c66f7e;

    font-size: 27px;

    font-weight: bold;

}

.menu {

    display: flex;

    align-items: center;

    gap: 30px;

}

.menu a {

    color: #652f35;

    font-size: 14px;

}

.menu a:hover {

    color: #c66f7e;

}

/* =========================================================

   CONTENEDOR

\========================================================= */

.container {

    width: 90%;

    max-width: 1050px;

    margin: 55px auto 80px;

}

/* =========================================================

   ENCABEZADO

\========================================================= */

.page-title {

    text-align: center;

    margin-bottom: 35px;

}

.page-title h2 {

    font-family: Georgia, "Times New Roman", serif;

    font-size: 38px;

    color: #c66f7e;

    margin-bottom: 10px;

}

.page-title p {

    color: #b85f6d;

}

/* =========================================================

   MENSAJES

\========================================================= */

.mensaje {

    margin-bottom: 25px;

    padding: 16px 20px;

    border-radius: 12px;

    font-size: 14px;

    line-height: 1.5;

}

.mensaje.exito {

    background: #e9f8ec;

    border: 1px solid #b9dfc1;

    color: #377944;

}

.mensaje.error {

    background: #ffe8e8;

    border: 1px solid #efb9b9;

    color: #a43e3e;

}

.mensaje.advertencia {

    background: #fff7df;

    border: 1px solid #ecd99b;

    color: #80651a;

}

/* =========================================================

   TARJETA PRINCIPAL

\========================================================= */

.order-card {

    background: white;

    border: 1px solid #f0c6ce;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:

        0 12px 30px

        rgba(120, 55, 65, 0.05);

}

/* =========================================================

   CABECERA DEL PEDIDO

\========================================================= */

.order-header {

    background: #fff0f4;

    padding: 28px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    border-bottom: 1px solid #f0c6ce;

}

.order-header h3 {

    font-family: Georgia, "Times New Roman", serif;

    font-size: 28px;

    margin-bottom: 7px;

}

.order-header p {

    color: #bd6976;

    font-size: 14px;

}

/* =========================================================

   ESTADOS DEL PEDIDO

\========================================================= */

.estado {

    display: inline-block;

    padding: 10px 20px;

    border-radius: 30px;

    font-size: 13px;

    font-weight: bold;

}

.estado.pendiente {

    background: #fff0c9;

    color: #8d6a12;

}

.estado.procesando {

    background: #e9efff;

    color: #4964a5;

}

.estado.enviado {

    background: #e3f3ff;

    color: #33739d;

}

.estado.entregado {

    background: #e3f6e7;

    color: #377944;

}

.estado.cancelado {

    background: #ffe4e4;

    color: #a43e3e;

}

/* =========================================================

   SECCIONES

\========================================================= */

.section {

    padding: 28px;

}

.section-title {

    font-family: Georgia, "Times New Roman", serif;

    font-size: 24px;

    margin-bottom: 20px;

}

.info-grid {

    display: grid;

    grid-template-columns: repeat(3, 1fr);

    gap: 18px;

}

.info-box {

    background: #fff5f7;

    border: 1px solid #f3d5da;

    border-radius: 12px;

    padding: 18px;

}

.info-box span {

    display: block;

    color: #c26473;

    font-size: 11px;

    text-transform: uppercase;

    margin-bottom: 8px;

}

.info-box strong {

    font-family: Georgia, "Times New Roman", serif;

    font-size: 17px;

    word-break: break-word;

}

/* =========================================================

   DATOS DE ENTREGA

\========================================================= */

.delivery-grid {

    display: grid;

    grid-template-columns: repeat(2, 1fr);

    gap: 18px;

}

.delivery-box {

    background: #fff5f7;

    border: 1px solid #f3d5da;

    border-radius: 12px;

    padding: 18px;

}

.delivery-box.full {

    grid-column: 1 / -1;

}

.delivery-box span {

    display: block;

    color: #c26473;

    font-size: 11px;

    text-transform: uppercase;

    margin-bottom: 8px;

}

.delivery-box strong {

    font-family: Georgia, "Times New Roman", serif;

    font-size: 16px;

    line-height: 1.5;

    word-break: break-word;

}

.delivery-badge {

    display: inline-block;

    background: #ffe5ea;

    color: #a95767;

    padding: 7px 14px;

    border-radius: 20px;

    font-family: Arial, Helvetica, sans-serif;

    font-size: 13px;

}

.delivery-note {

    margin-top: 18px;

    padding: 14px 16px;

    background: #fff8e8;

    border: 1px solid #f1dfad;

    border-radius: 10px;

    color: #79621c;

    font-size: 13px;

    line-height: 1.6;

}

/* =========================================================

   VERIFICACIÓN DEL PAGO

\========================================================= */

.payment-section {

    background: #fffafd;

}

.payment-card {

    background: #fff5f7;

    border: 1px solid #efcbd2;

    border-radius: 16px;

    padding: 22px;

}

.payment-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 20px;

}

.payment-status {

    display: inline-block;

    padding: 8px 14px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;

}

.pago-pendiente {

    background: #fff0c9;

    color: #8d6a12;

}

.pago-verificado {

    background: #e3f6e7;

    color: #377944;

}

.pago-rechazado {

    background: #ffe4e4;

    color: #a43e3e;

}

.payment-info {

    display: grid;

    grid-template-columns: repeat(2, 1fr);

    gap: 15px;

    margin-bottom: 20px;

}

.payment-info-box {

    background: white;

    border: 1px solid #f0d4d9;

    border-radius: 11px;

    padding: 16px;

}

.payment-info-box span {

    display: block;

    color: #c26473;

    font-size: 11px;

    text-transform: uppercase;

    margin-bottom: 7px;

}

.payment-info-box strong {

    font-family: Georgia, "Times New Roman", serif;

    font-size: 16px;

}

.receipt-area {

    margin-top: 20px;

}

.receipt-area h4 {

    font-family: Georgia, "Times New Roman", serif;

    font-size: 20px;

    margin-bottom: 15px;

}

.receipt-preview {

    background: white;

    border: 1px solid #efd0d6;

    border-radius: 14px;

    padding: 18px;

    text-align: center;

}

.receipt-preview img {

    display: block;

    max-width: 100%;

    max-height: 650px;

    object-fit: contain;

    margin: 0 auto;

    border-radius: 10px;

}

.receipt-file-name {

    margin-top: 12px;

    color: #a85e69;

    font-size: 12px;

    word-break: break-all;

}

.payment-note {

    margin-top: 15px;

    background: #fff8e8;

    border: 1px solid #f1dfad;

    color: #79621c;

    border-radius: 10px;

    padding: 14px;

    font-size: 13px;

    line-height: 1.6;

}

.payment-actions {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 14px;

    margin-top: 20px;

}

.payment-actions form {

    width: 100%;

}

.payment-button {

    width: 100%;

    border: 0;

    border-radius: 10px;

    padding: 14px 18px;

    font-weight: bold;

    cursor: pointer;

    font-size: 14px;

}

.btn-verify {

    background: #5e9d6b;

    color: white;

}

.btn-reject {

    background: #c45f69;

    color: white;

}

.payment-button:hover {

    opacity: 0.88;

}

.payment-verified-box {

    margin-top: 20px;

    background: #e9f8ec;

    border: 1px solid #b9dfc1;

    color: #377944;

    padding: 15px;

    border-radius: 10px;

    line-height: 1.6;

}

.payment-rejected-box {

    margin-top: 20px;

    background: #ffe8e8;

    border: 1px solid #efb9b9;

    color: #a43e3e;

    padding: 15px;

    border-radius: 10px;

    line-height: 1.6;

}

.no-receipt {

    background: #fff8e8;

    border: 1px solid #f1dfad;

    color: #79621c;

    padding: 18px;

    border-radius: 10px;

    line-height: 1.6;

}

/* =========================================================

   SEPARADOR

\========================================================= */

.separator {

    height: 1px;

    background: #f0cbd1;

    margin: 0 28px;

}

/* =========================================================

   PRODUCTOS

\========================================================= */

.product {

    background: #fff8fa;

    border: 1px solid #f0cbd1;

    border-radius: 15px;

    padding: 18px;

    margin-bottom: 18px;

    display: grid;

    grid-template-columns: 150px 1fr;

    gap: 25px;

    align-items: center;

}

.product-image {

    width: 150px;

    height: 190px;

    background: #fcecf0;

    border-radius: 12px;

    overflow: hidden;

    display: flex;

    justify-content: center;

    align-items: center;

}

.product-image img {

    width: 100%;

    height: 100%;

    object-fit: contain;

}

.no-image {

    color: #c77a87;

    text-align: center;

    font-size: 13px;

}

.product-info h4 {

    font-family: Georgia, "Times New Roman", serif;

    font-size: 23px;

    margin-bottom: 8px;

}

.category {

    display: inline-block;

    background: #ffe3e9;

    color: #c36171;

    padding: 5px 13px;

    border-radius: 20px;

    font-size: 12px;

    margin-bottom: 12px;

}

.description {

    color: #9c5b65;

    margin-bottom: 17px;

    line-height: 1.6;

}

.product-data {

    border-top: 1px solid #efd1d6;

}

.data-row {

    display: flex;

    justify-content: space-between;

    gap: 20px;

    padding: 10px 0;

    border-bottom: 1px solid #efd1d6;

}

.data-row span {

    color: #ad626e;

}

.data-row strong {

    color: #402326;

}

/* =========================================================

   TOTAL

\========================================================= */

.total-box {

    margin-top: 25px;

    background: #ffe9ee;

    border-radius: 14px;

    padding: 22px;

    display: flex;

    justify-content: space-between;

    align-items: center;

}

.total-box span {

    font-family: Georgia, "Times New Roman", serif;

    font-size: 21px;

}

.total-box strong {

    color: #c86f7e;

    font-size: 28px;

}

/* =========================================================

   BOTONES

\========================================================= */

.actions {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 15px;

    margin-top: 25px;

}

.btn {

    display: block;

    text-align: center;

    padding: 14px 20px;

    border-radius: 10px;

    font-weight: bold;

    font-size: 14px;

    transition: 0.2s;

}

.btn-outline {

    border: 1px solid #c9707e;

    color: #c26473;

    background: white;

}

.btn-primary {

    background: #c37483;

    color: white;

    border: 1px solid #c37483;

}

.btn:hover {

    opacity: 0.88;

}

/* =========================================================

   SIN PRODUCTOS

\========================================================= */

.empty {

    background: #fff5f7;

    border-radius: 12px;

    padding: 25px;

    text-align: center;

    color: #b56672;

}

/* =========================================================

   FOOTER

\========================================================= */

footer {

    background: #654047;

    color: white;

    text-align: center;

    padding: 38px 20px;

}

footer h3 {

    font-family: Georgia, "Times New Roman", serif;

    margin-bottom: 8px;

}

footer p {

    font-size: 13px;

}

/* =========================================================

   RESPONSIVE

\========================================================= */

@media (max-width: 800px) {

    .nav-container {

        flex-direction: column;

        padding: 20px;

    }

    .menu {

        flex-wrap: wrap;

        justify-content: center;

        gap: 15px;

    }

    .info-grid,

    .delivery-grid,

    .payment-info {

        grid-template-columns: 1fr;

    }

    .delivery-box.full {

        grid-column: auto;

    }

    .product {

        grid-template-columns: 1fr;

    }

    .product-image {

        margin: auto;

    }

    .actions,

    .payment-actions {

        grid-template-columns: 1fr;

    }

    .order-header,

    .payment-top {

        flex-direction: column;

        align-items: flex-start;

    }

}

/* GESTIÓN DEL ENVÍO */

.shipping-admin-section{background:#fffafd}.shipping-card{background:#fff5f7;border:1px solid #efcbd2;border-radius:16px;padding:22px}.shipping-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:15px;margin-bottom:18px}.shipping-field label{display:block;color:#c26473;font-size:11px;text-transform:uppercase;margin-bottom:8px}.shipping-field input{width:100%;padding:13px 14px;border:1px solid #e8b9c2;border-radius:10px;background:white;color:#402326;outline:none}.shipping-help{margin-bottom:18px;padding:14px 16px;border-radius:10px;background:#fff8e8;border:1px solid #f1dfad;color:#79621c;font-size:13px;line-height:1.6}.shipping-current{margin-bottom:18px;padding:15px 16px;border-radius:10px;background:#eef8f0;border:1px solid #b9dfc1;color:#377944;line-height:1.7;font-size:13px}.shipping-button{width:100%;border:none;border-radius:10px;padding:14px 20px;background:#c66f7e;color:white;font-weight:bold;cursor:pointer}.shipping-button:hover{background:#ad5f6e}@media(max-width:700px){.shipping-grid{grid-template-columns:1fr}}

</style>

</head>

<body>

<!-- ======================================================

     CABECERA

\====================================================== -->

<header class="hero">

    <h1>

        Roséa Boutique

    </h1>

    <p>

        Elegancia, delicadeza y estilo en cada detalle ♡

    </p>

</header>

<!-- ======================================================

     MENÚ

\====================================================== -->

<nav>

    <div class="nav-container">

        <a

            href="productos.php"

            class="logo"

        >

            🌹 Roséa Boutique

        </a>

        <div class="menu">

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

<!-- ======================================================

     CONTENIDO

\====================================================== -->

<main class="container">

    <section class="page-title">

        <h2>

            Detalle del pedido

        </h2>

        <p>

            Información completa de la compra ♡

        </p>

    </section>

    <?php if (!empty($mensaje)): ?>

        <div class="mensaje <?php echo htmlspecialchars($tipoMensaje); ?>">

            <?php echo htmlspecialchars($mensaje); ?>

        </div>

    <?php endif; ?>

    <section class="order-card">

        <!-- =================================================

             CABECERA DEL PEDIDO

        ================================================== -->

        <div class="order-header">

            <div>

                <h3>

                    Pedido #<?php

                    echo (int)$pedido['id_pedido'];

                    ?>

                </h3>

                <p>

                    <?php

                    echo htmlspecialchars(

                        $fechaFormateada

                    );

                    ?>

                </p>

            </div>

            <span

                class="estado <?php

                echo htmlspecialchars(

                    $estadoClase

                );

                ?>"

            >

                <?php

                echo htmlspecialchars(

                    nombreEstado(

                        $estadoActual

                    )

                );

                ?>

            </span>

        </div>

        <!-- =================================================

             CLIENTE

        ================================================== -->

        <div class="section">

            <h3 class="section-title">

                Cliente ♡

            </h3>

            <div class="info-grid">

                <div class="info-box">

                    <span>

                        Nombre

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

                <div class="info-box">

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

                <div class="info-box">

                    <span>

                        Estado del pedido

                    </span>

                    <strong>

                        <?php

                        echo htmlspecialchars(

                            nombreEstado(

                                $estadoActual

                            )

                        );

                        ?>

                    </strong>

                </div>

            </div>

        </div>

        <div class="separator"></div>

        <!-- =================================================

             DATOS DE ENTREGA

        ================================================== -->

        <div class="section">

            <h3 class="section-title">

                🌹 Datos de entrega

            </h3>

            <div class="delivery-grid">

                <div class="delivery-box">

                    <span>

                        Teléfono del cliente

                    </span>

                    <strong>

                        <?php

                        echo !empty($pedido['telefono'])

                            ? htmlspecialchars(

                                $pedido['telefono']

                            )

                            : 'No especificado';

                        ?>

                    </strong>

                </div>

                <div class="delivery-box">

                    <span>

                        Provincia

                    </span>

                    <strong>

                        <?php

                        echo !empty($pedido['provincia'])

                            ? htmlspecialchars(

                                $pedido['provincia']

                            )

                            : 'No especificada';

                        ?>

                    </strong>

                </div>

                <div class="delivery-box">

                    <span>

                        Ciudad / municipio

                    </span>

                    <strong>

                        <?php

                        echo !empty($pedido['ciudad'])

                            ? htmlspecialchars(

                                $pedido['ciudad']

                            )

                            : 'No especificado';

                        ?>

                    </strong>

                </div>

                <div class="delivery-box">

                    <span>

                        Método de entrega

                    </span>

                    <strong class="delivery-badge">

                        <?php

                        echo htmlspecialchars(

                            textoMetodoEntrega(

                                $pedido['metodo_entrega']

                            )

                        );

                        ?>

                    </strong>

                </div>

                <div class="delivery-box full">

                    <span>

                        Dirección de entrega

                    </span>

                    <strong>

                        <?php

                        if (!empty($pedido['direccion'])) {

                            echo nl2br(

                                htmlspecialchars(

                                    $pedido['direccion']

                                )

                            );

                        } else {

                            echo 'No especificada';

                        }

                        ?>

                    </strong>

                </div>

                <div class="delivery-box full">

                    <span>

                        Referencia para localizar la dirección

                    </span>

                    <strong>

                        <?php

                        if (!empty($pedido['referencia'])) {

                            echo nl2br(

                                htmlspecialchars(

                                    $pedido['referencia']

                                )

                            );

                        } else {

                            echo 'Sin referencia adicional';

                        }

                        ?>

                    </strong>

                </div>

                <div class="delivery-box full">

                    <span>

                        Método de pago

                    </span>

                    <strong class="delivery-badge">

                        <?php

                        echo htmlspecialchars(

                            textoMetodoPago(

                                $pedido['metodo_pago']

                            )

                        );

                        ?>

                    </strong>

                </div>

            </div>

            <?php

            if (

                $estadoActual !== 'cancelado' &&

                strtolower(

                    trim(

                        (string)$pedido['metodo_entrega']

                    )

                ) === 'delivery'

            ):

            ?>

                <div class="delivery-note">

                    <strong>

                        Información para despacho:

                    </strong>

                    verifica el teléfono, dirección y referencia

                    del cliente antes de marcar el pedido como

                    enviado.

                </div>

            <?php endif; ?>

        </div>

        <!-- =================================================

             VERIFICACIÓN DE PAGO

        ================================================== -->

        <?php if ($metodoPago === 'transferencia'): ?>

            <div class="separator"></div>

            <div class="section payment-section">

                <div class="payment-top">

                    <h3 class="section-title" style="margin-bottom: 0;">

                        💳 Verificación del pago

                    </h3>

                    <span

                        class="payment-status <?php

                        echo htmlspecialchars(

                            $estadoPagoClase

                        );

                        ?>"

                    >

                        <?php

                        echo htmlspecialchars(

                            nombreEstadoPago(

                                $estadoPago

                            )

                        );

                        ?>

                    </span>

                </div>

                <div class="payment-card">

                    <div class="payment-info">

                        <div class="payment-info-box">

                            <span>

                                Método de pago

                            </span>

                            <strong>

                                Transferencia bancaria

                            </strong>

                        </div>

                        <div class="payment-info-box">

                            <span>

                                Monto del pedido

                            </span>

                            <strong>

                                RD$<?php

                                echo number_format(

                                    (float)$pedido['total'],

                                    2

                                );

                                ?>

                            </strong>

                        </div>

                        <div class="payment-info-box">

                            <span>

                                Estado del pago

                            </span>

                            <strong>

                                <?php

                                echo htmlspecialchars(

                                    nombreEstadoPago(

                                        $estadoPago

                                    )

                                );

                                ?>

                            </strong>

                        </div>

                        <div class="payment-info-box">

                            <span>

                                Fecha del comprobante

                            </span>

                            <strong>

                                <?php

                                echo !empty(

                                    $fechaComprobanteFormateada

                                )

                                    ? htmlspecialchars(

                                        $fechaComprobanteFormateada

                                    )

                                    : 'No disponible';

                                ?>

                            </strong>

                        </div>

                    </div>

                    <?php if (!empty($pedido['comprobante_pago'])): ?>

                        <div class="receipt-area">

                            <h4>

                                Comprobante enviado por el cliente

                            </h4>

                            <div class="receipt-preview">

                                <a

                                    href="comprobantes/<?php

                                    echo rawurlencode(

                                        basename(

                                            $pedido['comprobante_pago']

                                        )

                                    );

                                    ?>"

                                    target="_blank"

                                    rel="noopener noreferrer"

                                    title="Abrir comprobante en tamaño completo"

                                >

                                    <img

                                        src="comprobantes/<?php

                                        echo rawurlencode(

                                            basename(

                                                $pedido['comprobante_pago']

                                            )

                                        );

                                        ?>"

                                        alt="Comprobante del pedido #<?php

                                        echo (int)$pedido['id_pedido'];

                                        ?>"

                                    >

                                </a>

                                <div class="receipt-file-name">

                                    Archivo:

                                    <?php

                                    echo htmlspecialchars(

                                        basename(

                                            $pedido['comprobante_pago']

                                        )

                                    );

                                    ?>

                                </div>

                            </div>

                        </div>

                        <?php if ($estadoPago === 'pendiente'): ?>

                            <div class="payment-note">

                                <strong>

                                    Antes de aprobar:

                                </strong>

                                verifica que el monto, la cuenta,

                                la fecha y los datos visibles en el

                                comprobante correspondan con el pedido.

                            </div>

                            <div class="payment-actions">

                                <form

                                    method="POST"

                                    action="admin_detalle_pedido.php?id=<?php

                                    echo (int)$pedido['id_pedido'];

                                    ?>"

                                >

                                    <input

                                        type="hidden"

                                        name="accion_pago"

                                        value="verificar"

                                    >

                                    <button

                                        type="submit"

                                        class="payment-button btn-verify"

                                        onclick="return confirm('¿Confirmas que revisaste el comprobante y deseas verificar este pago?');"

                                    >

                                        ✓ Verificar pago

                                    </button>

                                </form>

                                <form

                                    method="POST"

                                    action="admin_detalle_pedido.php?id=<?php

                                    echo (int)$pedido['id_pedido'];

                                    ?>"

                                >

                                    <input

                                        type="hidden"

                                        name="accion_pago"

                                        value="rechazar"

                                    >

                                    <button

                                        type="submit"

                                        class="payment-button btn-reject"

                                        onclick="return confirm('¿Seguro que deseas rechazar este comprobante?');"

                                    >

                                        ✕ Rechazar comprobante

                                    </button>

                                </form>

                            </div>

                        <?php elseif ($estadoPago === 'verificado'): ?>

                            <div class="payment-verified-box">

                                <strong>

                                    ✓ Pago verificado

                                </strong>

                                <br>

                                Este comprobante ya fue revisado y

                                aprobado por la administración de

                                Roséa Boutique.

                            </div>

                        <?php elseif ($estadoPago === 'rechazado'): ?>

                            <div class="payment-rejected-box">

                                <strong>

                                    ✕ Comprobante rechazado

                                </strong>

                                <br>

                                Este comprobante fue rechazado.

                                El cliente deberá enviar un nuevo

                                comprobante para que el pago pueda

                                verificarse.

                            </div>

                        <?php endif; ?>

                    <?php else: ?>

                        <div class="no-receipt">

                            <strong>

                                Aún no hay comprobante.

                            </strong>

                            <br><br>

                            El cliente seleccionó transferencia bancaria,

                            pero todavía no ha enviado una imagen del

                            comprobante de pago.

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>

        <div class="separator"></div>

                <!-- GESTIÓN DEL ENVÍO -->

        <div class="section shipping-admin-section">

            <h3 class="section-title">📦 Gestión del envío</h3>

            <div class="shipping-card">

                <?php

                $envioBloqueadoPorPago = ($metodoPago === 'transferencia' && $estadoPago !== 'verificado');

                $envioCancelado = ($estadoActual === 'cancelado');

                $envioEntregado = ($estadoActual === 'entregado');

                $fechaEnvioFormulario = '';

                if (!empty($pedido['fecha_envio'])) { $tsEnvioFormulario = strtotime($pedido['fecha_envio']); if ($tsEnvioFormulario !== false) { $fechaEnvioFormulario = date('Y-m-d\TH:i', $tsEnvioFormulario); } }

                ?>

                <?php if (!empty($pedido['codigo_seguimiento']) || !empty($pedido['empresa_envio'])): ?>

                    <div class="shipping-current"><strong>Envío registrado</strong><br>Empresa: <?php echo htmlspecialchars($pedido['empresa_envio'] ?: 'No especificada'); ?><br>Código: <?php echo htmlspecialchars($pedido['codigo_seguimiento'] ?: 'No disponible'); ?><?php if (!empty($pedido['fecha_envio'])): ?><br>Fecha: <?php echo htmlspecialchars(date('d/m/Y h:i A', strtotime($pedido['fecha_envio']))); ?><?php endif; ?></div>

                <?php endif; ?>

                <?php if ($envioEntregado): ?>

                    <div class="shipping-current" style="margin-bottom:0;">
                        <strong>✅ Pedido entregado</strong><br><br>
                        Este pedido ya fue marcado como <strong>Entregado</strong>.<br>
                        El proceso de envío ha finalizado y los datos del despacho quedan disponibles únicamente como información.
                        <?php if (!empty($pedido['codigo_seguimiento'])): ?>
                            <br><br>Código de seguimiento: <strong><?php echo htmlspecialchars($pedido['codigo_seguimiento']); ?></strong>
                        <?php endif; ?>
                    </div>

                <?php elseif ($envioCancelado): ?>

                    <div class="shipping-help">🔒 Este pedido está cancelado. No se puede registrar un envío.</div>

                <?php elseif ($envioBloqueadoPorPago): ?>

                    <div class="shipping-help">🔒 Este pedido fue pagado mediante transferencia y el pago todavía no está verificado. Verifica el comprobante antes de despacharlo.</div>

                <?php else: ?>

                    <div class="shipping-help">Al guardar estos datos, el pedido quedará marcado como <strong>Enviado</strong>. Si dejas vacío el código, Roséa generará <strong>ROSEA-<?php echo str_pad((string)$pedido['id_pedido'],6,'0',STR_PAD_LEFT); ?></strong>.</div>

                    <form method="POST" action="admin_detalle_pedido.php?id=<?php echo (int)$pedido['id_pedido']; ?>">

                        <input type="hidden" name="accion_envio" value="guardar_envio">

                        <div class="shipping-grid">

                            <div class="shipping-field"><label for="empresa_envio">Empresa de envío</label><input type="text" id="empresa_envio" name="empresa_envio" maxlength="100" value="<?php echo htmlspecialchars($pedido['empresa_envio'] ?? ''); ?>" placeholder="Ej.: Caribe Tours, Vimenpaq, Metro Pac..." <?php echo strtolower(trim((string)$pedido['metodo_entrega'])) === 'delivery' ? 'required' : ''; ?>></div>

                            <div class="shipping-field"><label for="codigo_seguimiento">Código de seguimiento</label><input type="text" id="codigo_seguimiento" name="codigo_seguimiento" maxlength="100" value="<?php echo htmlspecialchars($pedido['codigo_seguimiento'] ?? ''); ?>" placeholder="Vacío = automático"></div>

                            <div class="shipping-field"><label for="fecha_envio">Fecha y hora de envío</label><input type="datetime-local" id="fecha_envio" name="fecha_envio" value="<?php echo htmlspecialchars($fechaEnvioFormulario); ?>"></div>

                            <div class="shipping-field"><label>Método de entrega</label><input type="text" value="<?php echo htmlspecialchars(textoMetodoEntrega($pedido['metodo_entrega'])); ?>" readonly></div>

                        </div>

                        <button type="submit" class="shipping-button" onclick="return confirm('¿Confirmas que deseas registrar el despacho y marcar este pedido como Enviado?');">📦 Guardar envío y marcar como Enviado</button>

                    </form>

                <?php endif; ?>

            </div>

        </div>

        <div class="separator"></div>

<!-- =================================================

             PRODUCTOS

        ================================================== -->

        <div class="section">

            <h3 class="section-title">

                Productos del pedido ♡

            </h3>

            <?php if (count($detalles) > 0): ?>

                <?php foreach ($detalles as $detalle): ?>

                    <div class="product">

                        <div class="product-image">

                            <?php if (!empty($detalle['imagen'])): ?>

                                <img

                                    src="img/<?php

                                    echo htmlspecialchars(

                                        $detalle['imagen']

                                    );

                                    ?>"

                                    alt="<?php

                                    echo htmlspecialchars(

                                        $detalle['nombre']

                                    );

                                    ?>"

                                >

                            <?php else: ?>

                                <div class="no-image">

                                    Sin imagen

                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="product-info">

                            <span class="category">

                                <?php

                                echo htmlspecialchars(

                                    $detalle['categoria']

                                );

                                ?>

                            </span>

                            <h4>

                                <?php

                                echo htmlspecialchars(

                                    $detalle['nombre']

                                );

                                ?>

                            </h4>

                            <p class="description">

                                <?php

                                echo htmlspecialchars(

                                    $detalle['descripcion']

                                );

                                ?>

                            </p>

                            <div class="product-data">

                                <div class="data-row">

                                    <span>

                                        Cantidad

                                    </span>

                                    <strong>

                                        <?php

                                        echo (int)$detalle['cantidad'];

                                        ?>

                                    </strong>

                                </div>

                                <div class="data-row">

                                    <span>

                                        Precio unitario

                                    </span>

                                    <strong>

                                        RD$<?php

                                        echo number_format(

                                            (float)$detalle['precio_unitario'],

                                            2

                                        );

                                        ?>

                                    </strong>

                                </div>

                                <div class="data-row">

                                    <span>

                                        Subtotal

                                    </span>

                                    <strong>

                                        RD$<?php

                                        echo number_format(

                                            (float)$detalle['subtotal'],

                                            2

                                        );

                                        ?>

                                    </strong>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty">

                    Este pedido no contiene productos.

                </div>

            <?php endif; ?>

            <!-- =================================================

                 TOTAL

            ================================================== -->

            <div class="total-box">

                <span>

                    Total del pedido

                </span>

                <strong>

                    RD$<?php

                    echo number_format(

                        (float)$pedido['total'],

                        2

                    );

                    ?>

                </strong>

            </div>

            <!-- =================================================

                 BOTONES

            ================================================== -->

            <div class="actions">

                <a

                    href="admin_pedidos.php"

                    class="btn btn-outline"

                >

                    ← Volver a pedidos

                </a>

                <a

                    href="admin.php"

                    class="btn btn-primary"

                >

                    Panel de administración ♡

                </a>

            </div>

        </div>

    </section>

</main>

<!-- ======================================================

     FOOTER

\====================================================== -->

<footer>

    <h3>

        Roséa Boutique 🌹

    </h3>

    <p>

        Detalle administrativo del pedido · Panel administrativo

    </p>

</footer>

</body>

</html>
