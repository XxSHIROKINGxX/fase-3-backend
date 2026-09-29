<?php

session_start();

require_once __DIR__ . '/config/conexion.php';


/* =========================================================
   COMPROBAR SESIÓN
========================================================= */

if (!isset($_SESSION['id_usuario'])) {

    header("Location: login.php");
    exit;
}


/* =========================================================
   SOLO ACEPTAR POST
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: productos.php");
    exit;
}


/* =========================================================
   RECIBIR DATOS
========================================================= */

$id_usuario = (int)$_SESSION['id_usuario'];


$id_producto = isset($_POST['id_producto'])
    ? (int)$_POST['id_producto']
    : 0;


$cantidad = isset($_POST['cantidad'])
    ? (int)$_POST['cantidad']
    : 0;


/* =========================================================
   DATOS DE ENTREGA
========================================================= */

$telefono = isset($_POST['telefono'])
    ? trim($_POST['telefono'])
    : '';


$provincia = isset($_POST['provincia'])
    ? trim($_POST['provincia'])
    : '';


$ciudad = isset($_POST['ciudad'])
    ? trim($_POST['ciudad'])
    : '';


$direccion = isset($_POST['direccion'])
    ? trim($_POST['direccion'])
    : '';


$referencia = isset($_POST['referencia'])
    ? trim($_POST['referencia'])
    : '';


$metodo_entrega = isset($_POST['metodo_entrega'])
    ? strtolower(trim($_POST['metodo_entrega']))
    : '';


$metodo_pago = isset($_POST['metodo_pago'])
    ? strtolower(trim($_POST['metodo_pago']))
    : '';


/* =========================================================
   VALIDACIONES BÁSICAS
========================================================= */

if ($id_producto <= 0 || $cantidad <= 0) {

    die(
        "Los datos del producto no son válidos."
    );
}


if (
    $telefono === '' ||
    $provincia === '' ||
    $ciudad === '' ||
    $direccion === ''
) {

    die(
        "Debes completar todos los datos obligatorios de entrega."
    );
}


/* =========================================================
   VALIDAR MÉTODO DE ENTREGA
========================================================= */

$metodosEntregaPermitidos = [
    'delivery',
    'retiro'
];


if (
    !in_array(
        $metodo_entrega,
        $metodosEntregaPermitidos,
        true
    )
) {

    die(
        "El método de entrega seleccionado no es válido."
    );
}


/* =========================================================
   VALIDAR MÉTODO DE PAGO
========================================================= */

$metodosPagoPermitidos = [
    'efectivo',
    'transferencia'
];


if (
    !in_array(
        $metodo_pago,
        $metodosPagoPermitidos,
        true
    )
) {

    die(
        "El método de pago seleccionado no es válido."
    );
}


/* =========================================================
   VALIDAR LONGITUD
========================================================= */

if (strlen($telefono) > 20) {

    die(
        "El teléfono es demasiado largo."
    );
}


if (strlen($provincia) > 100) {

    die(
        "La provincia es demasiado larga."
    );
}


if (strlen($ciudad) > 100) {

    die(
        "La ciudad o municipio es demasiado largo."
    );
}


if (strlen($direccion) > 255) {

    die(
        "La dirección es demasiado larga."
    );
}


if (strlen($referencia) > 255) {

    die(
        "La referencia es demasiado larga."
    );
}


/* =========================================================
   CREAR PEDIDO
========================================================= */

try {


    /* =====================================================
       INICIAR TRANSACCIÓN
    ===================================================== */

    $conexion->beginTransaction();


    /* =====================================================
       BUSCAR PRODUCTO Y BLOQUEAR FILA
    ===================================================== */

    $sqlProducto = "

        SELECT
            id_producto,
            nombre,
            precio,
            stock,
            disponible

        FROM productos

        WHERE id_producto = :id_producto

        FOR UPDATE
    ";


    $stmtProducto =
        $conexion->prepare(
            $sqlProducto
        );


    $stmtProducto->bindValue(
        ':id_producto',
        $id_producto,
        PDO::PARAM_INT
    );


    $stmtProducto->execute();


    $producto =
        $stmtProducto->fetch(
            PDO::FETCH_ASSOC
        );


    /* =====================================================
       COMPROBAR PRODUCTO
    ===================================================== */

    if (!$producto) {

        throw new Exception(
            "El producto seleccionado no existe."
        );
    }


    if (
        (int)$producto['disponible'] !== 1
    ) {

        throw new Exception(
            "Este producto ya no está disponible."
        );
    }


    if (
        (int)$producto['stock'] <
        $cantidad
    ) {

        throw new Exception(

            "No hay suficiente stock disponible. " .

            "Actualmente quedan " .

            (int)$producto['stock'] .

            " unidades."
        );
    }


    /* =====================================================
       CALCULAR PRECIO DESDE LA BASE DE DATOS
    ===================================================== */

    $precio =
        (float)$producto['precio'];


    $subtotal =
        $precio * $cantidad;


    /* =====================================================
       ESTADO INICIAL DEL PAGO
    ===================================================== */

    /*
     * Tanto efectivo como transferencia comienzan
     * pendientes.
     *
     * En transferencia:
     * el administrador verificará el comprobante.
     *
     * En efectivo:
     * el pago podrá verificarse posteriormente.
     */

    $estado_pago = 'pendiente';


    /* =====================================================
       INSERTAR PEDIDO
    ===================================================== */

    $sqlPedido = "

        INSERT INTO pedidos
        (
            id_usuario,
            total,
            estado,

            telefono,
            provincia,
            ciudad,
            direccion,
            referencia,

            metodo_entrega,
            metodo_pago,

            comprobante_pago,
            estado_pago,
            fecha_comprobante
        )

        VALUES
        (
            :id_usuario,
            :total,
            'pendiente',

            :telefono,
            :provincia,
            :ciudad,
            :direccion,
            :referencia,

            :metodo_entrega,
            :metodo_pago,

            NULL,
            :estado_pago,
            NULL
        )
    ";


    $stmtPedido =
        $conexion->prepare(
            $sqlPedido
        );


    $stmtPedido->bindValue(
        ':id_usuario',
        $id_usuario,
        PDO::PARAM_INT
    );


    $stmtPedido->bindValue(
        ':total',
        $subtotal
    );


    $stmtPedido->bindValue(
        ':telefono',
        $telefono,
        PDO::PARAM_STR
    );


    $stmtPedido->bindValue(
        ':provincia',
        $provincia,
        PDO::PARAM_STR
    );


    $stmtPedido->bindValue(
        ':ciudad',
        $ciudad,
        PDO::PARAM_STR
    );


    $stmtPedido->bindValue(
        ':direccion',
        $direccion,
        PDO::PARAM_STR
    );


    if ($referencia === '') {

        $stmtPedido->bindValue(
            ':referencia',
            null,
            PDO::PARAM_NULL
        );

    } else {

        $stmtPedido->bindValue(
            ':referencia',
            $referencia,
            PDO::PARAM_STR
        );
    }


    $stmtPedido->bindValue(
        ':metodo_entrega',
        $metodo_entrega,
        PDO::PARAM_STR
    );


    $stmtPedido->bindValue(
        ':metodo_pago',
        $metodo_pago,
        PDO::PARAM_STR
    );


    $stmtPedido->bindValue(
        ':estado_pago',
        $estado_pago,
        PDO::PARAM_STR
    );


    $stmtPedido->execute();


    /* =====================================================
       OBTENER ID DEL PEDIDO
    ===================================================== */

    $id_pedido =
        (int)$conexion->lastInsertId();


    if ($id_pedido <= 0) {

        throw new Exception(
            "No fue posible obtener el número del pedido."
        );
    }


    /* =====================================================
       CREAR DETALLE DEL PEDIDO
    ===================================================== */

    $sqlDetalle = "

        INSERT INTO detalle_pedido
        (
            id_pedido,
            id_producto,
            cantidad,
            precio_unitario,
            subtotal
        )

        VALUES
        (
            :id_pedido,
            :id_producto,
            :cantidad,
            :precio_unitario,
            :subtotal
        )
    ";


    $stmtDetalle =
        $conexion->prepare(
            $sqlDetalle
        );


    $stmtDetalle->bindValue(
        ':id_pedido',
        $id_pedido,
        PDO::PARAM_INT
    );


    $stmtDetalle->bindValue(
        ':id_producto',
        $id_producto,
        PDO::PARAM_INT
    );


    $stmtDetalle->bindValue(
        ':cantidad',
        $cantidad,
        PDO::PARAM_INT
    );


    $stmtDetalle->bindValue(
        ':precio_unitario',
        $precio
    );


    $stmtDetalle->bindValue(
        ':subtotal',
        $subtotal
    );


    $stmtDetalle->execute();


    /* =====================================================
       DESCONTAR STOCK
    ===================================================== */

    $sqlStock = "

        UPDATE productos

        SET
            stock = stock - :cantidad

        WHERE
            id_producto = :id_producto
    ";


    $stmtStock =
        $conexion->prepare(
            $sqlStock
        );


    $stmtStock->bindValue(
        ':cantidad',
        $cantidad,
        PDO::PARAM_INT
    );


    $stmtStock->bindValue(
        ':id_producto',
        $id_producto,
        PDO::PARAM_INT
    );


    $stmtStock->execute();


    /* =====================================================
       CONFIRMAR TRANSACCIÓN
    ===================================================== */

    $conexion->commit();


    /* =====================================================
       LIMPIAR CHECKOUT TEMPORAL
    ===================================================== */

    unset(
        $_SESSION['checkout_producto'],
        $_SESSION['checkout_cantidad']
    );


    /* =====================================================
       REDIRECCIÓN SEGÚN MÉTODO DE PAGO
    ===================================================== */

    if (
        $metodo_pago ===
        'transferencia'
    ) {

        /*
         * Las transferencias van primero
         * a la página para subir comprobante.
         */

        header(
            "Location: subir_comprobante.php?id=" .
            $id_pedido
        );

        exit;
    }


    /*
     * Los pedidos pagados en efectivo
     * continúan con el flujo normal.
     */

    header(
        "Location: pedido_exitoso.php?id=" .
        $id_pedido
    );

    exit;


} catch (Exception $e) {


    /* =====================================================
       DESHACER TODO SI ALGO FALLA
    ===================================================== */

    if ($conexion->inTransaction()) {

        $conexion->rollBack();
    }


    die(

        "No se pudo completar el pedido: " .

        htmlspecialchars(
            $e->getMessage()
        )
    );
}

?>