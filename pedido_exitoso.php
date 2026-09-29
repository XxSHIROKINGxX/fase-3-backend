<?php
session_start();
require_once __DIR__ . '/config/conexion.php';

/* =========================================================
   VERIFICAR SESIÓN
========================================================= */
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

/* =========================================================
   OBTENER ID DEL PEDIDO
========================================================= */
$id_pedido = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_pedido <= 0) {
    die("Pedido no válido.");
}

/* =========================================================
   COMPROBAR SI ES ADMIN
========================================================= */
$esAdmin = (
    isset($_SESSION['rol']) &&
    strtolower(trim($_SESSION['rol'])) === 'admin'
);

/* =========================================================
   CONSULTAR PEDIDO
========================================================= */
try {

    $sql = "
        SELECT
            p.id_pedido,
            p.id_usuario,
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

            p.comprobante_pago,
            p.estado_pago,
            p.fecha_comprobante,

            p.empresa_envio,
            p.fecha_envio,
            p.codigo_seguimiento,

            u.nombre,
            u.apellido,
            u.email

        FROM pedidos p

        INNER JOIN usuarios u
            ON p.id_usuario = u.id_usuario

        WHERE p.id_pedido = :id_pedido
          AND p.id_usuario = :id_usuario

        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->bindValue(
        ':id_pedido',
        $id_pedido,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':id_usuario',
        $_SESSION['id_usuario'],
        PDO::PARAM_INT
    );

    $stmt->execute();

    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        die(
            "No se encontró el pedido o no tienes permiso para consultarlo."
        );
    }

    /* =====================================================
       CONSULTAR PRODUCTOS DEL PEDIDO
    ===================================================== */
    $sqlDetalle = "
        SELECT
            d.cantidad,
            d.precio_unitario,
            d.subtotal,
            pr.nombre,
            pr.descripcion,
            pr.categoria,
            pr.imagen

        FROM detalle_pedido d

        INNER JOIN productos pr
            ON d.id_producto = pr.id_producto

        WHERE d.id_pedido = :id_pedido
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
   VARIABLES DE ESTADO
========================================================= */
$estadoActual = strtolower(
    trim($pedido['estado'] ?? 'pendiente')
);

$metodoPago = strtolower(
    trim($pedido['metodo_pago'] ?? '')
);

$estadoPago = strtolower(
    trim($pedido['estado_pago'] ?? 'pendiente')
);

/* =========================================================
   FUNCIONES
========================================================= */
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
            return 'Pendiente';
    }
}

function nivelEstado($estado)
{
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

function textoEstadoPago($estado)
{
    switch (strtolower(trim((string)$estado))) {
        case 'verificado':
            return 'Pago verificado';

        case 'rechazado':
            return 'Comprobante rechazado';

        default:
            return 'Pendiente de verificación';
    }
}

$nivel = nivelEstado($estadoActual);

/* =========================================================
   CLASE DEL ESTADO DEL PEDIDO
========================================================= */
$claseEstado = 'estado-pendiente';

switch ($estadoActual) {
    case 'procesando':
        $claseEstado = 'estado-procesando';
        break;

    case 'enviado':
        $claseEstado = 'estado-enviado';
        break;

    case 'entregado':
        $claseEstado = 'estado-entregado';
        break;

    case 'cancelado':
        $claseEstado = 'estado-cancelado';
        break;
}

/* =========================================================
   CLASE DEL ESTADO DEL PAGO
========================================================= */
$clasePago = 'pago-pendiente';

switch ($estadoPago) {
    case 'verificado':
        $clasePago = 'pago-verificado';
        break;

    case 'rechazado':
        $clasePago = 'pago-rechazado';
        break;
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
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    background: #fff8fa;
    color: #4d292f;
    font-family: Arial, Helvetica, sans-serif;
}

a {
    text-decoration: none;
}

/* HERO */
.hero {
    background: linear-gradient(135deg, #fff5f7, #fdecef);
    text-align: center;
    padding: 55px 20px;
}

.hero h1 {
    font-family: Georgia, serif;
    color: #c47482;
    font-size: 44px;
    margin-bottom: 12px;
}

.hero p {
    color: #ad626d;
    font-size: 16px;
}

/* NAVEGACIÓN */
nav {
    background: white;
    border-bottom: 1px solid #f2d9de;
}

.nav-container {
    max-width: 1120px;
    margin: auto;
    min-height: 75px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px;
}

.logo {
    font-family: Georgia, serif;
    font-size: 27px;
    font-weight: bold;
    color: #c47482;
}

.menu {
    display: flex;
    align-items: center;
    gap: 27px;
}

.menu a {
    color: #6b333d;
    font-size: 14px;
}

.menu a:hover {
    color: #c47482;
}

/* CONTENIDO */
main {
    max-width: 1000px;
    margin: auto;
    padding: 55px 20px 80px;
}

/* TÍTULO */
.titulo {
    text-align: center;
    margin-bottom: 35px;
}

.titulo .icono {
    width: 70px;
    height: 70px;
    margin: auto auto 18px;
    border-radius: 50%;
    background: #fde6eb;
    border: 2px solid #efbdc7;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 32px;
    color: #c47482;
}

.titulo h2 {
    font-family: Georgia, serif;
    color: #c47482;
    font-size: 36px;
    margin-bottom: 10px;
}

.titulo p {
    color: #ad626d;
    line-height: 1.6;
}

/* TARJETA PRINCIPAL */
.pedido-card {
    background: white;
    border: 1px solid #f0ccd3;
    border-radius: 20px;
    padding: 35px;
    box-shadow: 0 12px 30px rgba(167, 92, 105, 0.08);
}

.pedido-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    border-bottom: 1px solid #f1d7dc;
    padding-bottom: 25px;
    margin-bottom: 25px;
}

.pedido-header h3 {
    font-family: Georgia, serif;
    font-size: 27px;
    margin-bottom: 8px;
}

.pedido-header p {
    color: #a65f69;
    font-size: 14px;
}

/* ESTADOS */
.estado {
    padding: 9px 18px;
    border-radius: 30px;
    font-size: 13px;
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

/* INFORMACIÓN GENERAL */
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 35px;
}

.info-box {
    background: #fff5f7;
    border: 1px solid #f5dce1;
    border-radius: 12px;
    padding: 18px;
}

.info-box span,
.entrega-item span,
.pago-item span,
.envio-item span {
    display: block;
    color: #c47482;
    font-size: 11px;
    text-transform: uppercase;
    margin-bottom: 7px;
}

.info-box strong {
    font-family: Georgia, serif;
    font-size: 17px;
}

/* ENTREGA */
.entrega,
.pago-box,
.info-envio,
.seguimiento {
    background: #fffafb;
    border: 1px solid #f2d5db;
    border-radius: 16px;
    padding: 27px;
    margin-bottom: 35px;
}

.entrega-titulo,
.pago-box h3,
.info-envio h3 {
    font-family: Georgia, serif;
    font-size: 23px;
    margin-bottom: 23px;
}

.entrega-grid,
.pago-grid,
.envio-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.entrega-item,
.pago-item,
.envio-item {
    background: #fff4f6;
    border: 1px solid #f1d6dc;
    border-radius: 12px;
    padding: 17px;
}

.entrega-item.grande {
    grid-column: 1 / -1;
}

.metodo {
    display: inline-block;
    background: #fde7eb;
    color: #a95768;
    padding: 7px 13px;
    border-radius: 20px;
    font-size: 13px;
}

/* PAGO */
.pago-estado {
    display: inline-block;
    padding: 8px 14px;
    border-radius: 30px;
    font-size: 13px;
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

.pago-mensaje,
.envio-espera,
.envio-confirmado {
    border-radius: 12px;
    padding: 17px;
    line-height: 1.6;
    margin-top: 15px;
}

.mensaje-pendiente,
.envio-espera {
    background: #fff8e8;
    border: 1px solid #f0d99d;
    color: #84661d;
}

.mensaje-verificado,
.envio-confirmado {
    background: #eef9f0;
    border: 1px solid #b9ddbf;
    color: #397246;
}

.mensaje-rechazado {
    background: #fff0f0;
    border: 1px solid #efbaba;
    color: #963f3f;
}

.pago-acciones {
    margin-top: 18px;
}

.btn-comprobante {
    display: inline-block;
    padding: 12px 18px;
    border-radius: 9px;
    background: #c47482;
    color: white;
    font-weight: bold;
    font-size: 13px;
}

.codigo-seguimiento {
    color: #c47482 !important;
    font-family: Georgia, serif;
    font-size: 20px !important;
    letter-spacing: 1px;
}

/* SEGUIMIENTO */
.seguimiento h3 {
    font-family: Georgia, serif;
    font-size: 22px;
    margin-bottom: 27px;
}

.progreso {
    position: relative;
    display: flex;
    justify-content: space-between;
}

.linea {
    position: absolute;
    height: 4px;
    top: 17px;
    left: 12.5%;
    right: 12.5%;
    background: #f0dce1;
    z-index: 1;
}

.linea-progreso {
    position: absolute;
    height: 4px;
    top: 17px;
    left: 12.5%;
    background: #c57584;
    z-index: 1;
    max-width: 75%;
}

.paso {
    flex: 1;
    position: relative;
    text-align: center;
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
    font-weight: bold;
}

.paso.activo .circulo {
    background: #c57584;
    border-color: #c57584;
    color: white;
}

.paso.actual .circulo {
    box-shadow: 0 0 0 6px rgba(197,117,132,.15);
}

.paso-texto {
    color: #99717a;
    font-size: 12px;
    font-weight: bold;
}

.paso.activo .paso-texto {
    color: #a95768;
}

/* CANCELADO */
.cancelado-box {
    background: #fff0f0;
    border: 1px solid #efbaba;
    border-radius: 15px;
    padding: 22px;
    margin-bottom: 35px;
    color: #963f3f;
}

/* PRODUCTOS */
.productos-titulo {
    font-family: Georgia, serif;
    font-size: 25px;
    margin-bottom: 22px;
}

.producto {
    display: grid;
    grid-template-columns: 145px 1fr;
    gap: 25px;
    background: #fffafb;
    border: 1px solid #f2d5db;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 18px;
}

.producto-imagen {
    width: 145px;
    height: 180px;
    background: #fdecef;
    border-radius: 12px;
    overflow: hidden;
}

.producto-imagen img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.producto-info h4 {
    font-family: Georgia, serif;
    font-size: 23px;
    margin-bottom: 10px;
}

.producto-info p {
    color: #a65f69;
    margin-bottom: 17px;
    line-height: 1.5;
}

.detalle-linea {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #f1d7dc;
}

.total-box {
    background: #fde9ed;
    border-radius: 14px;
    padding: 23px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 25px;
    font-family: Georgia, serif;
    font-size: 21px;
}

.total-box strong {
    color: #c47482;
    font-size: 27px;
}

/* BOTONES */
.acciones {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-top: 28px;
}

.btn {
    text-align: center;
    padding: 15px;
    border-radius: 9px;
    font-weight: bold;
}

.btn-outline {
    border: 1px solid #c47482;
    color: #c47482;
}

.btn-principal {
    background: #c47482;
    color: white;
}

/* FOOTER */
footer {
    background: #70464e;
    color: white;
    text-align: center;
    padding: 35px 20px;
}

footer strong {
    font-family: Georgia, serif;
    font-size: 20px;
}

/* RESPONSIVE */
@media (max-width: 760px) {
    .menu {
        gap: 10px;
        flex-wrap: wrap;
    }

    .info-grid,
    .entrega-grid,
    .pago-grid,
    .envio-grid,
    .acciones {
        grid-template-columns: 1fr;
    }

    .producto {
        grid-template-columns: 1fr;
    }

    .producto-imagen {
        width: 100%;
        height: 300px;
    }

    .pedido-card {
        padding: 20px;
    }

    .pedido-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .hero h1 {
        font-size: 35px;
    }
}
</style>
</head>

<body>

<header class="hero">
    <h1>Roséa Boutique</h1>
    <p>Elegancia, delicadeza y estilo en cada detalle ♡</p>
</header>

<nav>
    <div class="nav-container">

        <a href="index.php" class="logo">
            🌹 Roséa Boutique
        </a>

        <div class="menu">
            <a href="productos.php">Colección</a>

            <?php if ($esAdmin): ?>
                <a href="admin.php">Panel Admin</a>
            <?php endif; ?>

            <a href="perfil.php">Mi perfil</a>
            <a href="logout.php">Cerrar sesión</a>
        </div>

    </div>
</nav>

<main>

    <section class="titulo">
        <div class="icono">♡</div>

        <h2>Detalle de mi pedido</h2>

        <p>
            Hola,
            <strong>
                <?php
                echo htmlspecialchars(
                    trim(
                        $pedido['nombre'] . ' ' .
                        $pedido['apellido']
                    )
                );
                ?>
            </strong>.
            Aquí puedes consultar los productos y el estado actual
            de tu pedido.
        </p>
    </section>

    <article class="pedido-card">

        <div class="pedido-header">

            <div>
                <h3>
                    Pedido #<?php echo (int)$pedido['id_pedido']; ?>
                </h3>

                <p>
                    Roséa Boutique · Detalle de tu compra
                </p>
            </div>

            <span class="estado <?php echo $claseEstado; ?>">
                <?php echo nombreEstado($estadoActual); ?>
            </span>

        </div>

        <div class="info-grid">

            <div class="info-box">
                <span>Fecha del pedido</span>

                <strong>
                    <?php
                    echo date(
                        'd/m/Y h:i A',
                        strtotime($pedido['fecha_pedido'])
                    );
                    ?>
                </strong>
            </div>

            <div class="info-box">
                <span>Correo electrónico</span>

                <strong>
                    <?php
                    echo htmlspecialchars($pedido['email']);
                    ?>
                </strong>
            </div>

        </div>

        <section class="entrega">

            <h3 class="entrega-titulo">
                🌹 Datos de entrega
            </h3>

            <div class="entrega-grid">

                <div class="entrega-item">
                    <span>Teléfono</span>
                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $pedido['telefono'] ?: 'No especificado'
                        );
                        ?>
                    </strong>
                </div>

                <div class="entrega-item">
                    <span>Provincia</span>
                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $pedido['provincia'] ?: 'No especificada'
                        );
                        ?>
                    </strong>
                </div>

                <div class="entrega-item">
                    <span>Ciudad / Municipio</span>
                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $pedido['ciudad'] ?: 'No especificada'
                        );
                        ?>
                    </strong>
                </div>

                <div class="entrega-item">
                    <span>Método de entrega</span>

                    <strong class="metodo">
                        <?php
                        echo textoMetodoEntrega(
                            $pedido['metodo_entrega']
                        );
                        ?>
                    </strong>
                </div>

                <div class="entrega-item grande">
                    <span>Dirección de entrega</span>

                    <strong>
                        <?php
                        echo nl2br(
                            htmlspecialchars(
                                $pedido['direccion'] ?: 'No especificada'
                            )
                        );
                        ?>
                    </strong>
                </div>

                <div class="entrega-item grande">
                    <span>Referencia</span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $pedido['referencia'] ?: 'Sin referencia'
                        );
                        ?>
                    </strong>
                </div>

                <div class="entrega-item grande">
                    <span>Método de pago</span>

                    <strong class="metodo">
                        <?php
                        echo textoMetodoPago(
                            $pedido['metodo_pago']
                        );
                        ?>
                    </strong>
                </div>

            </div>

        </section>

        <section class="pago-box">

            <h3>💳 Estado del pago</h3>

            <?php if ($metodoPago === 'transferencia'): ?>

                <div class="pago-grid">

                    <div class="pago-item">
                        <span>Método de pago</span>
                        <strong>Transferencia bancaria</strong>
                    </div>

                    <div class="pago-item">
                        <span>Estado del pago</span>

                        <strong
                            class="pago-estado <?php echo $clasePago; ?>"
                        >
                            <?php
                            echo textoEstadoPago($estadoPago);
                            ?>
                        </strong>
                    </div>

                    <div class="pago-item">
                        <span>Comprobante</span>

                        <strong>
                            <?php
                            echo !empty($pedido['comprobante_pago'])
                                ? 'Comprobante enviado'
                                : 'No enviado';
                            ?>
                        </strong>
                    </div>

                    <div class="pago-item">
                        <span>Fecha del comprobante</span>

                        <strong>
                            <?php
                            if (!empty($pedido['fecha_comprobante'])) {
                                echo date(
                                    'd/m/Y h:i A',
                                    strtotime(
                                        $pedido['fecha_comprobante']
                                    )
                                );
                            } else {
                                echo 'Pendiente';
                            }
                            ?>
                        </strong>
                    </div>

                </div>

                <?php if ($estadoPago === 'pendiente'): ?>

                    <?php if (!empty($pedido['comprobante_pago'])): ?>

                        <div
                            class="pago-mensaje mensaje-pendiente"
                        >
                            🟡 Tu comprobante fue enviado
                            correctamente. El pago está pendiente
                            de verificación por Roséa Boutique.
                        </div>

                        <div class="pago-acciones">
                            <a
                                href="subir_comprobante.php?id=<?php
                                echo (int)$pedido['id_pedido'];
                                ?>"
                                class="btn-comprobante"
                            >
                                Gestionar comprobante ♡
                            </a>
                        </div>

                    <?php else: ?>

                        <div
                            class="pago-mensaje mensaje-pendiente"
                        >
                            🟡 Todavía no has enviado el
                            comprobante de esta transferencia.
                        </div>

                        <div class="pago-acciones">
                            <a
                                href="subir_comprobante.php?id=<?php
                                echo (int)$pedido['id_pedido'];
                                ?>"
                                class="btn-comprobante"
                            >
                                Subir comprobante ♡
                            </a>
                        </div>

                    <?php endif; ?>

                <?php elseif ($estadoPago === 'verificado'): ?>

                    <div
                        class="pago-mensaje mensaje-verificado"
                    >
                        🟢 ✓ Tu pago fue verificado correctamente
                        por Roséa Boutique. No necesitas realizar
                        ninguna otra acción relacionada con el pago.
                    </div>

                <?php elseif ($estadoPago === 'rechazado'): ?>

                    <div
                        class="pago-mensaje mensaje-rechazado"
                    >
                        🔴 El comprobante enviado no pudo ser
                        verificado. Puedes enviar un nuevo
                        comprobante para que Roséa Boutique
                        vuelva a revisarlo.
                    </div>

                    <div class="pago-acciones">
                        <a
                            href="subir_comprobante.php?id=<?php
                            echo (int)$pedido['id_pedido'];
                            ?>"
                            class="btn-comprobante"
                        >
                            Subir nuevo comprobante ♡
                        </a>
                    </div>

                <?php endif; ?>

            <?php elseif ($metodoPago === 'efectivo'): ?>

                <div class="pago-grid">

                    <div class="pago-item">
                        <span>Método de pago</span>
                        <strong>Efectivo</strong>
                    </div>

                    <div class="pago-item">
                        <span>Total</span>

                        <strong>
                            RD$<?php
                            echo number_format(
                                (float)$pedido['total'],
                                2
                            );
                            ?>
                        </strong>
                    </div>

                </div>

                <div class="pago-mensaje mensaje-pendiente">
                    💵 Este pedido fue registrado con pago
                    en efectivo. No necesitas subir un
                    comprobante de transferencia.
                </div>

            <?php else: ?>

                <div class="pago-mensaje mensaje-pendiente">
                    No hay información adicional disponible
                    sobre el método de pago de este pedido.
                </div>

            <?php endif; ?>

        </section>

        <?php if ($estadoActual !== 'cancelado'): ?>

            <section class="info-envio">

                <h3>📦 Información de envío</h3>

                <?php if (
                    !empty($pedido['empresa_envio']) ||
                    !empty($pedido['codigo_seguimiento']) ||
                    !empty($pedido['fecha_envio'])
                ): ?>

                    <div class="envio-grid">

                        <div class="envio-item">
                            <span>Empresa de envío</span>

                            <strong>
                                <?php
                                echo !empty($pedido['empresa_envio'])
                                    ? htmlspecialchars(
                                        $pedido['empresa_envio']
                                    )
                                    : 'No especificada';
                                ?>
                            </strong>
                        </div>

                        <div class="envio-item">
                            <span>Código de seguimiento</span>

                            <strong class="codigo-seguimiento">
                                <?php
                                echo !empty(
                                    $pedido['codigo_seguimiento']
                                )
                                    ? htmlspecialchars(
                                        $pedido['codigo_seguimiento']
                                    )
                                    : 'No disponible';
                                ?>
                            </strong>
                        </div>

                        <div class="envio-item">
                            <span>Fecha de envío</span>

                            <strong>
                                <?php
                                if (!empty($pedido['fecha_envio'])) {
                                    echo date(
                                        'd/m/Y h:i A',
                                        strtotime(
                                            $pedido['fecha_envio']
                                        )
                                    );
                                } else {
                                    echo 'No disponible';
                                }
                                ?>
                            </strong>
                        </div>

                        <div class="envio-item">
                            <span>Método de entrega</span>

                            <strong>
                                <?php
                                echo textoMetodoEntrega(
                                    $pedido['metodo_entrega']
                                );
                                ?>
                            </strong>
                        </div>

                    </div>

                    <?php if ($estadoActual === 'enviado'): ?>

                        <div class="envio-confirmado">
                            📦 Tu pedido ya fue enviado.

                            <?php if (
                                !empty($pedido['empresa_envio'])
                            ): ?>
                                La empresa encargada del envío es
                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $pedido['empresa_envio']
                                    );
                                    ?>
                                </strong>.
                            <?php endif; ?>

                            Conserva el código de seguimiento
                            para identificar tu pedido durante
                            el proceso de entrega.
                        </div>

                    <?php elseif (
                        $estadoActual === 'entregado'
                    ): ?>

                        <div class="envio-confirmado">
                            ✓ Tu pedido fue entregado
                            correctamente. Gracias por comprar
                            en Roséa Boutique ♡
                        </div>

                    <?php endif; ?>

                <?php else: ?>

                    <div class="envio-espera">

                        <?php if (
                            $estadoActual === 'pendiente'
                        ): ?>

                            ♡ Tu pedido fue recibido
                            correctamente. La información de
                            envío aparecerá aquí cuando el
                            pedido sea despachado.

                        <?php elseif (
                            $estadoActual === 'procesando'
                        ): ?>

                            📦 Tu pedido se encuentra en
                            preparación. Cuando sea enviado
                            aparecerán aquí la empresa de envío,
                            la fecha y el código de seguimiento.

                        <?php elseif (
                            $estadoActual === 'enviado'
                        ): ?>

                            📦 El pedido aparece como enviado,
                            pero todavía no tiene información
                            de seguimiento registrada.

                        <?php elseif (
                            $estadoActual === 'entregado'
                        ): ?>

                            ✓ Este pedido aparece como
                            entregado, pero no tiene información
                            de envío registrada.

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </section>

        <?php endif; ?>

        <?php if ($estadoActual === 'cancelado'): ?>

            <div class="cancelado-box">
                <h3>✕ Pedido cancelado</h3>

                <p>
                    Este pedido ha sido marcado como cancelado.
                    El proceso de preparación y entrega ha sido
                    detenido.
                </p>
            </div>

        <?php else: ?>

            <section class="seguimiento">

                <h3>Seguimiento del pedido</h3>

                <div class="progreso">

                    <div class="linea"></div>

                    <div
                        class="linea-progreso"
                        style="width: <?php
                        echo ($nivel <= 1)
                            ? 0
                            : (($nivel - 1) * 25);
                        ?>%;"
                    ></div>

                    <div class="paso
                        <?php echo $nivel >= 1 ? 'activo' : ''; ?>
                        <?php echo $nivel === 1 ? 'actual' : ''; ?>
                    ">
                        <div class="circulo">
                            <?php echo $nivel >= 1 ? '✓' : '1'; ?>
                        </div>

                        <div class="paso-texto">
                            Pedido recibido
                        </div>
                    </div>

                    <div class="paso
                        <?php echo $nivel >= 2 ? 'activo' : ''; ?>
                        <?php echo $nivel === 2 ? 'actual' : ''; ?>
                    ">
                        <div class="circulo">
                            <?php echo $nivel >= 2 ? '✓' : '2'; ?>
                        </div>

                        <div class="paso-texto">
                            Procesando
                        </div>
                    </div>

                    <div class="paso
                        <?php echo $nivel >= 3 ? 'activo' : ''; ?>
                        <?php echo $nivel === 3 ? 'actual' : ''; ?>
                    ">
                        <div class="circulo">
                            <?php echo $nivel >= 3 ? '✓' : '3'; ?>
                        </div>

                        <div class="paso-texto">
                            Enviado
                        </div>
                    </div>

                    <div class="paso
                        <?php echo $nivel >= 4 ? 'activo' : ''; ?>
                        <?php echo $nivel === 4 ? 'actual' : ''; ?>
                    ">
                        <div class="circulo">
                            <?php echo $nivel >= 4 ? '✓' : '4'; ?>
                        </div>

                        <div class="paso-texto">
                            Entregado
                        </div>
                    </div>

                </div>

            </section>

        <?php endif; ?>

        <h3 class="productos-titulo">
            Productos ♡
        </h3>

        <?php if (!empty($detalles)): ?>

            <?php foreach ($detalles as $item): ?>

                <div class="producto">

                    <div class="producto-imagen">

                        <?php if (!empty($item['imagen'])): ?>

                            <img
                                src="img/<?php
                                echo htmlspecialchars(
                                    $item['imagen']
                                );
                                ?>"
                                alt="<?php
                                echo htmlspecialchars(
                                    $item['nombre']
                                );
                                ?>"
                            >

                        <?php else: ?>

                            <div
                                style="
                                    height:100%;
                                    display:flex;
                                    align-items:center;
                                    justify-content:center;
                                    font-size:45px;
                                "
                            >
                                🌹
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="producto-info">

                        <h4>
                            <?php
                            echo htmlspecialchars(
                                $item['nombre']
                            );
                            ?>
                        </h4>

                        <?php if (
                            !empty($item['descripcion'])
                        ): ?>

                            <p>
                                <?php
                                echo htmlspecialchars(
                                    $item['descripcion']
                                );
                                ?>
                            </p>

                        <?php endif; ?>

                        <div class="detalle-linea">
                            <span>Cantidad</span>

                            <strong>
                                <?php
                                echo (int)$item['cantidad'];
                                ?>
                            </strong>
                        </div>

                        <div class="detalle-linea">
                            <span>Precio unitario</span>

                            <strong>
                                RD$<?php
                                echo number_format(
                                    (float)$item['precio_unitario'],
                                    2
                                );
                                ?>
                            </strong>
                        </div>

                        <div class="detalle-linea">
                            <span>Subtotal</span>

                            <strong>
                                RD$<?php
                                echo number_format(
                                    (float)$item['subtotal'],
                                    2
                                );
                                ?>
                            </strong>
                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="envio-espera">
                No se encontraron productos asociados
                a este pedido.
            </div>

        <?php endif; ?>

        <div class="total-box">
            <span>Total del pedido</span>

            <strong>
                RD$<?php
                echo number_format(
                    (float)$pedido['total'],
                    2
                );
                ?>
            </strong>
        </div>

        <div class="acciones">

            <a
                href="productos.php"
                class="btn btn-outline"
            >
                ← Seguir comprando
            </a>

            <a
                href="perfil.php"
                class="btn btn-principal"
            >
                ← Volver a mis pedidos ♡
            </a>

        </div>

    </article>

</main>

<footer>
    <strong>Roséa Boutique 🌹</strong>

    <p style="margin-top:8px;">
        Elegancia, delicadeza y estilo en cada detalle ♡
    </p>
</footer>

</body>
</html>