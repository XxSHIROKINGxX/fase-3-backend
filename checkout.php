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
   VARIABLES
========================================================= */

$error = "";

$id_producto = 0;

$cantidad = 1;


/* =========================================================
   RECIBIR PRODUCTO
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_producto = isset($_POST['id_producto'])
        ? (int)$_POST['id_producto']
        : 0;

    $cantidad = isset($_POST['cantidad'])
        ? (int)$_POST['cantidad']
        : 1;

} elseif (
    isset($_SESSION['checkout_producto']) &&
    isset($_SESSION['checkout_cantidad'])
) {

    $id_producto =
        (int)$_SESSION['checkout_producto'];

    $cantidad =
        (int)$_SESSION['checkout_cantidad'];

} else {

    header("Location: productos.php");
    exit;
}


/* =========================================================
   VALIDAR DATOS
========================================================= */

if ($id_producto <= 0 || $cantidad <= 0) {

    header("Location: productos.php");
    exit;
}


/* =========================================================
   BUSCAR PRODUCTO
========================================================= */

try {

    $sql = "
        SELECT
            id_producto,
            nombre,
            descripcion,
            categoria,
            precio,
            stock,
            imagen,
            disponible

        FROM productos

        WHERE id_producto = :id_producto

        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->bindValue(
        ':id_producto',
        $id_producto,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $producto = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$producto) {

        header("Location: productos.php");
        exit;
    }


    if ((int)$producto['disponible'] !== 1) {

        $error =
            "Este producto no está disponible actualmente.";
    }


    if ((int)$producto['stock'] < $cantidad) {

        $error =
            "No hay suficiente stock para la cantidad seleccionada.";
    }


    /* =====================================================
       GUARDAR SELECCIÓN TEMPORALMENTE
    ===================================================== */

    $_SESSION['checkout_producto'] =
        $id_producto;

    $_SESSION['checkout_cantidad'] =
        $cantidad;


    /* =====================================================
       CALCULAR TOTAL
    ===================================================== */

    $precio =
        (float)$producto['precio'];

    $subtotal =
        $precio * $cantidad;


} catch (PDOException $e) {

    die(
        "Error al cargar el producto: " .
        htmlspecialchars($e->getMessage())
    );
}


/* =========================================================
   DATOS DEL USUARIO
========================================================= */

try {

    $sqlUsuario = "
        SELECT
            nombre,
            apellido,
            email

        FROM usuarios

        WHERE id_usuario = :id_usuario

        LIMIT 1
    ";

    $stmtUsuario =
        $conexion->prepare($sqlUsuario);

    $stmtUsuario->bindValue(
        ':id_usuario',
        (int)$_SESSION['id_usuario'],
        PDO::PARAM_INT
    );

    $stmtUsuario->execute();

    $usuario =
        $stmtUsuario->fetch(PDO::FETCH_ASSOC);


    if (!$usuario) {

        die(
            "No se pudieron obtener los datos del usuario."
        );
    }


} catch (PDOException $e) {

    die(
        "Error al obtener los datos del usuario: " .
        htmlspecialchars($e->getMessage())
    );
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
    Finalizar compra | Roséa Boutique
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
    color: #513238;
}


/* =====================================================
   HERO
===================================================== */

.hero {
    text-align: center;
    padding: 55px 20px 40px;

    background: linear-gradient(
        135deg,
        #fff7f9,
        #fdecef
    );
}


.hero h1 {
    font-family: Georgia, serif;

    font-size: 44px;

    color: #bd7180;

    margin-bottom: 10px;
}


.hero p {
    color: #9c5f69;
}


/* =====================================================
   MENÚ
===================================================== */

header {
    background: white;

    min-height: 72px;

    padding: 0 7%;

    display: flex;
    align-items: center;
    justify-content: space-between;

    border-bottom: 1px solid #f1dce2;
}


.logo {
    font-family: Georgia, serif;

    font-size: 27px;
    font-weight: bold;

    color: #bd7180;
}


nav {
    display: flex;
    gap: 30px;
}


nav a {
    text-decoration: none;

    color: #71424a;

    font-size: 14px;
}


nav a:hover {
    color: #bd7180;
}


/* =====================================================
   CONTENEDOR
===================================================== */

.container {
    max-width: 1100px;

    margin: 45px auto 80px;

    padding: 0 20px;
}


.titulo {
    text-align: center;

    margin-bottom: 35px;
}


.titulo h2 {
    font-family: Georgia, serif;

    color: #bd7180;

    font-size: 35px;

    margin-bottom: 10px;
}


.titulo p {
    color: #a26771;
}


/* =====================================================
   CHECKOUT
===================================================== */

.checkout {
    display: grid;

    grid-template-columns:
        1.15fr 0.85fr;

    gap: 30px;

    align-items: start;
}


.panel {
    background: white;

    border: 1px solid #f0d5dc;

    border-radius: 18px;

    padding: 30px;

    box-shadow:
        0 10px 30px
        rgba(164, 96, 110, 0.07);
}


.panel h3 {
    font-family: Georgia, serif;

    color: #513238;

    font-size: 24px;

    margin-bottom: 25px;
}


/* =====================================================
   FORMULARIO
===================================================== */

.grupo {
    margin-bottom: 18px;
}


.fila {
    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 15px;
}


label {
    display: block;

    font-weight: bold;

    font-size: 13px;

    color: #69434a;

    margin-bottom: 8px;
}


input,
select,
textarea {
    width: 100%;

    padding: 13px 14px;

    border: 1px solid #e7bcc5;

    border-radius: 9px;

    background: #fffdfd;

    color: #513238;

    font-size: 14px;

    outline: none;
}


input:focus,
select:focus,
textarea:focus {
    border-color: #bd7180;
}


textarea {
    min-height: 90px;

    resize: vertical;
}


.dato-cuenta {
    background: #fff5f7;

    border: 1px solid #f3d7dd;

    border-radius: 10px;

    padding: 14px;

    margin-bottom: 20px;

    font-size: 14px;

    line-height: 1.7;
}


/* =====================================================
   TRANSFERENCIA
===================================================== */

.transferencia-box {
    display: none;

    background: linear-gradient(
        135deg,
        #fff5f7,
        #fdecef
    );

    border: 1px solid #eabac4;

    border-radius: 14px;

    padding: 20px;

    margin-bottom: 22px;
}


.transferencia-box.mostrar {
    display: block;
}


.transferencia-box h4 {
    font-family: Georgia, serif;

    color: #a95868;

    font-size: 20px;

    margin-bottom: 10px;
}


.transferencia-box > p {
    color: #84545d;

    font-size: 13px;

    line-height: 1.6;

    margin-bottom: 15px;
}


.banco-datos {
    background: white;

    border: 1px solid #efd0d6;

    border-radius: 11px;

    overflow: hidden;

    margin-bottom: 15px;
}


.banco-linea {
    display: flex;

    justify-content: space-between;

    gap: 20px;

    padding: 12px 14px;

    border-bottom: 1px solid #f3dce1;

    font-size: 13px;
}


.banco-linea:last-child {
    border-bottom: none;
}


.banco-linea span {
    color: #a45e6b;
}


.banco-linea strong {
    color: #513238;

    text-align: right;
}


.transferencia-aviso {
    background: #fff9e8;

    border: 1px solid #eadca5;

    border-radius: 9px;

    padding: 13px;

    color: #7a6424;

    font-size: 12px;

    line-height: 1.6;
}


/* =====================================================
   RESUMEN
===================================================== */

.producto-resumen {
    display: flex;

    gap: 18px;

    align-items: center;

    padding-bottom: 22px;

    border-bottom:
        1px solid #f0d4da;

    margin-bottom: 22px;
}


.producto-resumen img {
    width: 110px;
    height: 140px;

    object-fit: cover;

    border-radius: 12px;

    background: #fff1f4;
}


.producto-resumen h4 {
    font-family: Georgia, serif;

    font-size: 19px;

    margin-bottom: 8px;
}


.producto-resumen p {
    color: #9b616b;

    font-size: 13px;

    margin-bottom: 6px;
}


.resumen-linea {
    display: flex;

    justify-content: space-between;

    gap: 15px;

    padding: 10px 0;

    color: #70464d;
}


.resumen-total {
    display: flex;

    justify-content: space-between;

    margin-top: 15px;

    padding-top: 20px;

    border-top:
        2px solid #f0d4da;

    font-family: Georgia, serif;

    font-size: 23px;

    font-weight: bold;

    color: #bd7180;
}


/* =====================================================
   BOTÓN
===================================================== */

.btn-confirmar {
    width: 100%;

    border: none;

    border-radius: 10px;

    padding: 16px;

    margin-top: 25px;

    background: #bd7180;

    color: white;

    font-size: 15px;

    font-weight: bold;

    cursor: pointer;

    transition: 0.2s;
}


.btn-confirmar:hover {
    background: #a95f6e;

    transform: translateY(-1px);
}


.btn-confirmar:disabled {
    opacity: 0.55;

    cursor: not-allowed;

    transform: none;
}


.volver {
    display: block;

    text-align: center;

    margin-top: 15px;

    color: #a56571;

    text-decoration: none;

    font-size: 13px;
}


.volver:hover {
    text-decoration: underline;
}


.error {
    background: #fff0f0;

    border: 1px solid #e9aaaa;

    color: #a23d3d;

    border-radius: 10px;

    padding: 14px;

    margin-bottom: 25px;
}


/* =====================================================
   FOOTER
===================================================== */

footer {
    background: #5a3b41;

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

    color: #f3dfe3;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 850px) {

    .checkout {
        grid-template-columns: 1fr;
    }


    header {
        flex-direction: column;

        padding-top: 18px;
        padding-bottom: 18px;

        gap: 15px;
    }


    nav {
        flex-wrap: wrap;

        justify-content: center;
    }
}


@media (max-width: 550px) {

    .fila {
        grid-template-columns: 1fr;
    }


    .hero h1 {
        font-size: 34px;
    }


    .panel {
        padding: 22px;
    }


    .producto-resumen {
        align-items: flex-start;
    }


    .producto-resumen img {
        width: 90px;
        height: 120px;
    }


    .banco-linea {
        flex-direction: column;

        gap: 4px;
    }


    .banco-linea strong {
        text-align: left;
    }
}

</style>

</head>


<body>


<!-- =====================================================
     HERO
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

<header>

    <div class="logo">
        🌹 Roséa Boutique
    </div>


    <nav>

        <a href="productos.php">
            Colección
        </a>

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


    <div class="titulo">

        <h2>
            Finalizar compra
        </h2>

        <p>
            Completa los datos para preparar tu pedido ♡
        </p>

    </div>


    <?php if (!empty($error)): ?>

        <div class="error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <div class="checkout">


        <!-- =============================================
             DATOS DE ENTREGA
        ============================================== -->

        <section class="panel">

            <h3>
                Datos de entrega 🌹
            </h3>


            <div class="dato-cuenta">

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $usuario['nombre'] .
                        ' ' .
                        $usuario['apellido']
                    );

                    ?>

                </strong>

                <br>

                <?php

                echo htmlspecialchars(
                    $usuario['email']
                );

                ?>

            </div>


            <form
                action="crear_pedido.php"
                method="POST"
                id="formCheckout"
            >


                <input
                    type="hidden"
                    name="id_producto"
                    value="<?php echo $id_producto; ?>"
                >


                <input
                    type="hidden"
                    name="cantidad"
                    value="<?php echo $cantidad; ?>"
                >


                <!-- TELÉFONO -->

                <div class="grupo">

                    <label for="telefono">
                        Teléfono *
                    </label>

                    <input
                        type="tel"
                        id="telefono"
                        name="telefono"
                        placeholder="Ej.: 809-555-1234"
                        maxlength="20"
                        required
                    >

                </div>


                <!-- PROVINCIA / CIUDAD -->

                <div class="fila">


                    <div class="grupo">

                        <label for="provincia">
                            Provincia *
                        </label>

                        <input
                            type="text"
                            id="provincia"
                            name="provincia"
                            placeholder="Ej.: San Cristóbal"
                            maxlength="100"
                            required
                        >

                    </div>


                    <div class="grupo">

                        <label for="ciudad">
                            Ciudad / municipio *
                        </label>

                        <input
                            type="text"
                            id="ciudad"
                            name="ciudad"
                            placeholder="Ej.: San Cristóbal"
                            maxlength="100"
                            required
                        >

                    </div>


                </div>


                <!-- DIRECCIÓN -->

                <div class="grupo">

                    <label for="direccion">
                        Dirección de entrega *
                    </label>

                    <textarea
                        id="direccion"
                        name="direccion"
                        placeholder="Calle, número, sector..."
                        maxlength="255"
                        required
                    ></textarea>

                </div>


                <!-- REFERENCIA -->

                <div class="grupo">

                    <label for="referencia">
                        Referencia
                    </label>

                    <textarea
                        id="referencia"
                        name="referencia"
                        placeholder="Ej.: casa rosada frente al parque..."
                        maxlength="255"
                    ></textarea>

                </div>


                <!-- MÉTODO DE ENTREGA / PAGO -->

                <div class="fila">


                    <div class="grupo">

                        <label for="metodo_entrega">
                            Método de entrega *
                        </label>

                        <select
                            id="metodo_entrega"
                            name="metodo_entrega"
                            required
                        >

                            <option value="">
                                Seleccionar
                            </option>

                            <option value="delivery">
                                Delivery
                            </option>

                            <option value="retiro">
                                Retiro
                            </option>

                        </select>

                    </div>


                    <div class="grupo">

                        <label for="metodo_pago">
                            Método de pago *
                        </label>

                        <select
                            id="metodo_pago"
                            name="metodo_pago"
                            required
                        >

                            <option value="">
                                Seleccionar
                            </option>

                            <option value="efectivo">
                                Efectivo
                            </option>

                            <option value="transferencia">
                                Transferencia
                            </option>

                        </select>

                    </div>


                </div>


                <!-- =====================================
                     INFORMACIÓN PARA TRANSFERENCIA
                ====================================== -->

                <div
                    class="transferencia-box"
                    id="transferenciaBox"
                >

                    <h4>
                        Transferencia bancaria ♡
                    </h4>

                    <p>
                        Realiza la transferencia por el total
                        de tu pedido utilizando los datos
                        bancarios de Roséa Boutique.
                    </p>


                    <div class="banco-datos">


                        <div class="banco-linea">

                            <span>
                                Banco
                            </span>

                            <strong>
                                CONFIGURAR BANCO
                            </strong>

                        </div>


                        <div class="banco-linea">

                            <span>
                                Tipo de cuenta
                            </span>

                            <strong>
                                CONFIGURAR TIPO DE CUENTA
                            </strong>

                        </div>


                        <div class="banco-linea">

                            <span>
                                Número de cuenta
                            </span>

                            <strong>
                                CONFIGURAR NÚMERO
                            </strong>

                        </div>


                        <div class="banco-linea">

                            <span>
                                Titular
                            </span>

                            <strong>
                                Roséa Boutique
                            </strong>

                        </div>


                        <div class="banco-linea">

                            <span>
                                Monto
                            </span>

                            <strong>

                                RD$<?php

                                echo number_format(
                                    $subtotal,
                                    2
                                );

                                ?>

                            </strong>

                        </div>


                    </div>


                    <div class="transferencia-aviso">

                        🌹 Después de confirmar tu pedido,
                        podrás subir la foto o captura del
                        comprobante de transferencia.

                        <br><br>

                        El pago permanecerá pendiente hasta
                        que Roséa Boutique verifique el
                        comprobante.

                    </div>


                </div>


                <!-- BOTÓN -->

                <button
                    type="submit"
                    class="btn-confirmar"
                    <?php
                    echo !empty($error)
                        ? 'disabled'
                        : '';
                    ?>
                >
                    Confirmar pedido ♡
                </button>


                <a
                    href="productos.php"
                    class="volver"
                >
                    ← Volver a la colección
                </a>


            </form>

        </section>


        <!-- =============================================
             RESUMEN
        ============================================== -->

        <aside class="panel">

            <h3>
                Tu pedido ♡
            </h3>


            <div class="producto-resumen">


                <?php if (!empty($producto['imagen'])): ?>

                    <img
                        src="img/<?php
                        echo htmlspecialchars(
                            $producto['imagen']
                        );
                        ?>"
                        alt="<?php
                        echo htmlspecialchars(
                            $producto['nombre']
                        );
                        ?>"
                    >

                <?php endif; ?>


                <div>

                    <h4>

                        <?php

                        echo htmlspecialchars(
                            $producto['nombre']
                        );

                        ?>

                    </h4>


                    <p>

                        <?php

                        echo htmlspecialchars(
                            $producto['categoria']
                        );

                        ?>

                    </p>


                    <p>

                        Cantidad:

                        <strong>
                            <?php echo $cantidad; ?>
                        </strong>

                    </p>

                </div>


            </div>


            <div class="resumen-linea">

                <span>
                    Precio unitario
                </span>

                <strong>

                    RD$<?php

                    echo number_format(
                        $precio,
                        2
                    );

                    ?>

                </strong>

            </div>


            <div class="resumen-linea">

                <span>
                    Cantidad
                </span>

                <strong>
                    <?php echo $cantidad; ?>
                </strong>

            </div>


            <div class="resumen-linea">

                <span>
                    Envío
                </span>

                <strong>
                    Por confirmar
                </strong>

            </div>


            <div class="resumen-total">

                <span>
                    Total
                </span>

                <span>

                    RD$<?php

                    echo number_format(
                        $subtotal,
                        2
                    );

                    ?>

                </span>

            </div>


        </aside>


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
        Elegancia y estilo en cada detalle.
    </p>

</footer>


<!-- =====================================================
     JAVASCRIPT
====================================================== -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const metodoPago =
            document.getElementById(
                'metodo_pago'
            );

        const transferenciaBox =
            document.getElementById(
                'transferenciaBox'
            );


        function actualizarMetodoPago() {

            if (
                metodoPago.value ===
                'transferencia'
            ) {

                transferenciaBox.classList.add(
                    'mostrar'
                );

            } else {

                transferenciaBox.classList.remove(
                    'mostrar'
                );
            }
        }


        metodoPago.addEventListener(
            'change',
            actualizarMetodoPago
        );


        actualizarMetodoPago();
    }
);

</script>


</body>

</html>