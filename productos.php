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
   OBTENER PRODUCTOS VISIBLES
========================================================= */

try {

    $sql = "
        SELECT *
        FROM productos
        WHERE disponible = 1
        ORDER BY id_producto DESC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute();

    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die(
        "Error al obtener los productos: " .
        $e->getMessage()
    );
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

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Productos | Roséa Boutique</title>


    <style>

        /* =====================================================
           CONFIGURACIÓN GENERAL
        ===================================================== */

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
           ENCABEZADO PRINCIPAL
        ===================================================== */

        .hero {
            text-align: center;
            padding: 65px 20px 45px;

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
            font-size: 16px;
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

            position: relative;
            z-index: 10;
        }

        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #bd7180;
        }

        nav {
            display: flex;
            gap: 35px;
            align-items: center;
        }

        nav a {
            font-family: Arial, sans-serif;
            font-size: 14px;
            color: #71424a;
            transition: 0.3s;
        }

        nav a:hover {
            color: #c47685;
        }


        /* =====================================================
           SECCIÓN COLECCIÓN
        ===================================================== */

        .coleccion {
            max-width: 1200px;
            margin: auto;
            padding: 45px 25px 70px;
        }

        .titulo-coleccion {
            text-align: center;
            margin-bottom: 35px;
        }

        .titulo-coleccion h2 {
            color: #bd7180;
            font-size: 36px;
            margin-bottom: 8px;
        }

        .titulo-coleccion p {
            font-family: Arial, sans-serif;
            color: #a26771;
        }


        /* =====================================================
           TARJETA PRODUCTO
        ===================================================== */

        .producto {
            display: grid;
            grid-template-columns: 55% 45%;

            background: white;

            border: 1px solid #f0d5dc;
            border-radius: 20px;

            overflow: hidden;

            margin-bottom: 40px;

            box-shadow:
                0 10px 30px
                rgba(164, 96, 110, 0.08);
        }


        /* =====================================================
           IMAGEN
        ===================================================== */

        .producto-imagen {
            background: #fdf1f4;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 20px;
            min-height: 620px;

            position: relative;
        }

        .producto-imagen img {
            width: 100%;
            height: 580px;

            object-fit: contain;
            display: block;

            border-radius: 12px;
        }

        .sin-imagen {
            font-family: Arial, sans-serif;
            color: #a26771;
        }


        /* =====================================================
           ETIQUETA DE STOCK SOBRE LA IMAGEN
        ===================================================== */

        .badge-stock {
            position: absolute;

            top: 22px;
            left: 22px;

            padding: 9px 15px;

            border-radius: 30px;

            font-family: Arial, sans-serif;
            font-size: 12px;
            font-weight: bold;

            z-index: 2;

            box-shadow:
                0 4px 12px
                rgba(80, 40, 50, 0.08);
        }

        .badge-disponible {
            background: #e1f5e7;
            color: #3e7950;
            border: 1px solid #bce2c7;
        }

        .badge-pocas {
            background: #fff2c9;
            color: #8a6814;
            border: 1px solid #e8d18b;
        }

        .badge-ultima {
            background: #ffe6dc;
            color: #a7573d;
            border: 1px solid #efc0ae;
        }

        .badge-agotado {
            background: #ffe1e5;
            color: #a94054;
            border: 1px solid #efbac4;
        }


        /* =====================================================
           INFORMACIÓN PRODUCTO
        ===================================================== */

        .producto-info {
            padding: 70px 40px;

            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .categoria {
            display: inline-block;
            width: fit-content;

            padding: 7px 18px;

            background: #fce4e9;
            color: #bd7180;

            border-radius: 30px;

            font-family: Arial, sans-serif;
            font-size: 12px;

            margin-bottom: 20px;
        }

        .producto-info h3 {
            font-size: 31px;
            color: #513238;
            margin-bottom: 18px;
        }

        .descripcion {
            font-family: Arial, sans-serif;

            color: #99616a;

            line-height: 1.7;

            margin-bottom: 25px;
        }

        .precio {
            font-family: Arial, sans-serif;

            font-size: 28px;
            font-weight: bold;

            color: #bd7180;

            margin-bottom: 15px;
        }


        /* =====================================================
           INFORMACIÓN DE INVENTARIO
        ===================================================== */

        .stock {
            font-family: Arial, sans-serif;

            font-size: 14px;

            padding: 14px 16px;

            border-radius: 10px;

            margin-bottom: 15px;

            line-height: 1.5;
        }

        .stock-disponible {
            color: #426f50;
            background: #eef9f1;
            border: 1px solid #c9e8d1;
        }

        .stock-pocas {
            color: #866519;
            background: #fff9e8;
            border: 1px solid #eddca9;
        }

        .stock-ultima {
            color: #9d5039;
            background: #fff1eb;
            border: 1px solid #efc9b9;
        }

        .stock-agotado {
            color: #9f5865;
            background: #fcecef;
            border: 1px solid #edc4cc;
        }


        /* =====================================================
           MENSAJE DE URGENCIA
        ===================================================== */

        .aviso-inventario {
            font-family: Arial, sans-serif;

            padding: 13px 15px;

            border-radius: 10px;

            margin-bottom: 25px;

            font-size: 13px;
            line-height: 1.5;
        }

        .aviso-pocas {
            background: #fff8dd;
            border: 1px solid #ead493;
            color: #866519;
        }

        .aviso-ultima {
            background: #fff0e9;
            border: 1px solid #efc2b0;
            color: #9c4d35;
            font-weight: bold;
        }


        /* =====================================================
           SEPARADOR
        ===================================================== */

        .separador {
            display: flex;
            align-items: center;
            gap: 15px;

            margin-bottom: 25px;

            color: #cf8794;
        }

        .separador::before,
        .separador::after {
            content: "";

            flex: 1;

            height: 1px;

            background: #edcbd2;
        }


        /* =====================================================
           FORMULARIO
        ===================================================== */

        .form-compra label {
            display: block;

            font-size: 17px;
            font-weight: bold;

            margin-bottom: 10px;
        }

        .cantidad {
            width: 115px;

            padding: 13px;

            border: 1px solid #e7bcc5;
            border-radius: 10px;

            text-align: center;

            font-size: 16px;

            margin-bottom: 15px;

            outline: none;
        }

        .cantidad:focus {
            border-color: #bd7180;
        }

        .ayuda-cantidad {
            display: block;

            font-family: Arial, sans-serif;

            font-size: 12px;

            color: #a26771;

            margin-top: -6px;
            margin-bottom: 17px;
        }

        .boton-comprar {
            width: 100%;

            padding: 15px;

            border: none;
            border-radius: 10px;

            background: #bd7180;

            color: white;

            font-size: 15px;
            font-weight: bold;

            cursor: pointer;

            transition: 0.3s;
        }

        .boton-comprar:hover {
            background: #a95f6e;
            transform: translateY(-2px);
        }


        /* =====================================================
           AGOTADO
        ===================================================== */

        .agotado {
            background: #f7e4e7;

            padding: 20px;

            border-radius: 10px;

            color: #9f5865;

            text-align: center;

            font-family: Arial, sans-serif;

            font-weight: bold;

            border: 1px solid #ebc6cd;
        }

        .agotado span {
            display: block;

            font-size: 12px;
            font-weight: normal;

            margin-top: 7px;

            line-height: 1.5;
        }


        /* =====================================================
           SIN PRODUCTOS
        ===================================================== */

        .sin-productos {
            text-align: center;

            padding: 70px;

            background: white;

            border-radius: 20px;

            color: #9c5f69;
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
            margin-bottom: 8px;
        }

        footer p {
            font-family: Arial, sans-serif;

            font-size: 13px;

            color: #f3dfe3;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 850px) {

            .hero h1 {
                font-size: 36px;
            }

            header {
                flex-direction: column;
                gap: 18px;
            }

            nav {
                gap: 20px;
                flex-wrap: wrap;
                justify-content: center;
            }

            .producto {
                grid-template-columns: 1fr;
            }

            .producto-imagen {
                min-height: auto;
            }

            .producto-imagen img {
                height: auto;
                max-height: 600px;
            }

            .producto-info {
                padding: 40px 25px;
            }
        }


        @media (max-width: 500px) {

            .hero {
                padding: 45px 15px 35px;
            }

            .hero h1 {
                font-size: 31px;
            }

            .logo {
                font-size: 23px;
            }

            .titulo-coleccion h2 {
                font-size: 29px;
            }

            .producto-info h3 {
                font-size: 25px;
            }

            .producto-imagen {
                padding: 10px;
            }

            .badge-stock {
                top: 15px;
                left: 15px;
            }
        }

    </style>

</head>


<body>


<!-- =====================================================
     PRESENTACIÓN
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
     COLECCIÓN
====================================================== -->

<main class="coleccion">


    <div class="titulo-coleccion">

        <h2>
            Nuestra colección
        </h2>

        <p>
            Descubre nuestras piezas seleccionadas especialmente para ti.
        </p>

    </div>


    <?php if (count($productos) > 0): ?>


        <?php foreach ($productos as $producto): ?>


            <?php

            /*
             * Tomamos el stock directamente de MySQL.
             * Esto permite que la tienda cambie automáticamente
             * cuando una compra descuenta unidades.
             */

            $stock = (int)$producto['stock'];


            /*
             * DEFINIR ESTADO VISUAL DEL INVENTARIO
             *
             * 5 o más = Disponible
             * 2 a 4   = Pocas unidades
             * 1       = Última unidad
             * 0       = Agotado
             */

            if ($stock <= 0) {

                $textoBadge = 'Agotado';
                $claseBadge = 'badge-agotado';
                $claseStock = 'stock-agotado';

            } elseif ($stock === 1) {

                $textoBadge = '¡Última unidad!';
                $claseBadge = 'badge-ultima';
                $claseStock = 'stock-ultima';

            } elseif ($stock <= 4) {

                $textoBadge = 'Pocas unidades';
                $claseBadge = 'badge-pocas';
                $claseStock = 'stock-pocas';

            } else {

                $textoBadge = 'Disponible';
                $claseBadge = 'badge-disponible';
                $claseStock = 'stock-disponible';
            }

            ?>


            <article class="producto">


                <!-- =================================================
                     IMAGEN
                ================================================== -->

                <div class="producto-imagen">


                    <!-- ESTADO DEL STOCK -->

                    <span
                        class="badge-stock <?= e($claseBadge) ?>"
                    >
                        <?= e($textoBadge) ?>
                    </span>


                    <?php if (!empty($producto['imagen'])): ?>


                        <img
                            src="img/<?= e($producto['imagen']) ?>"
                            alt="<?= e($producto['nombre']) ?>"
                        >


                    <?php else: ?>


                        <p class="sin-imagen">
                            Imagen no disponible
                        </p>


                    <?php endif; ?>


                </div>


                <!-- =================================================
                     INFORMACIÓN
                ================================================== -->

                <div class="producto-info">


                    <span class="categoria">

                        <?= e($producto['categoria']) ?>

                    </span>


                    <h3>

                        <?= e($producto['nombre']) ?>

                    </h3>


                    <p class="descripcion">

                        <?= e($producto['descripcion']) ?>

                    </p>


                    <div class="precio">

                        RD$<?= number_format(
                            (float)$producto['precio'],
                            2
                        ) ?>

                    </div>


                    <!-- =================================================
                         INFORMACIÓN DEL STOCK
                    ================================================== -->

                    <div
                        class="stock <?= e($claseStock) ?>"
                    >


                        <?php if ($stock <= 0): ?>


                            🔴
                            <strong>
                                Producto agotado
                            </strong>


                        <?php elseif ($stock === 1): ?>


                            🔴
                            <strong>
                                ¡Última unidad disponible!
                            </strong>


                        <?php elseif ($stock <= 4): ?>


                            🟡
                            <strong>
                                ¡Pocas unidades!
                            </strong>

                            Solo quedan

                            <strong>
                                <?= $stock ?>
                            </strong>


                        <?php else: ?>


                            🟢 Disponible:

                            <strong>
                                <?= $stock ?>
                                unidades
                            </strong>


                        <?php endif; ?>


                    </div>


                    <!-- =================================================
                         AVISOS DE STOCK BAJO
                    ================================================== -->

                    <?php if ($stock === 1): ?>


                        <div
                            class="aviso-inventario aviso-ultima"
                        >

                            ♡ Esta es la última unidad disponible
                            de este producto.

                        </div>


                    <?php elseif ($stock >= 2 && $stock <= 4): ?>


                        <div
                            class="aviso-inventario aviso-pocas"
                        >

                            ♡ ¡Quedan pocas unidades!
                            Aprovecha antes de que se agote.

                        </div>


                    <?php endif; ?>


                    <!-- =================================================
                         PRODUCTO DISPONIBLE
                    ================================================== -->

                    <?php if ($stock > 0): ?>


                        <div class="separador">
                            ♡
                        </div>


                        <!-- =============================================
                             COMPRA
                        ============================================== -->

                        <form
                            action="checkout.php"
                            method="POST"
                            class="form-compra"
                        >


                            <input
                                type="hidden"
                                name="id_producto"
                                value="<?= (int)$producto['id_producto'] ?>"
                            >


                            <label
                                for="cantidad-<?= (int)$producto['id_producto'] ?>"
                            >
                                Cantidad:
                            </label>


                            <input
                                id="cantidad-<?= (int)$producto['id_producto'] ?>"
                                class="cantidad"
                                type="number"
                                name="cantidad"
                                value="1"
                                min="1"
                                max="<?= $stock ?>"
                                required
                            >


                            <span class="ayuda-cantidad">

                                Máximo disponible:
                                <?= $stock ?>

                                unidad<?= $stock === 1 ? '' : 'es' ?>.

                            </span>


                            <button
                                type="submit"
                                class="boton-comprar"
                            >
                                🛍 Comprar ♡
                            </button>


                        </form>


                    <?php else: ?>


                        <!-- =============================================
                             PRODUCTO AGOTADO
                        ============================================== -->

                        <div class="agotado">

                            Producto agotado ♡

                            <span>
                                Este artículo no está disponible
                                para comprar en este momento.
                            </span>

                        </div>


                    <?php endif; ?>


                </div>


            </article>


        <?php endforeach; ?>


    <?php else: ?>


        <div class="sin-productos">

            <h3>
                Nuestra colección estará disponible próximamente ♡
            </h3>

        </div>


    <?php endif; ?>


</main>


<!-- =====================================================
     PIE DE PÁGINA
====================================================== -->

<footer>

    <h3>
        Roséa Boutique 🌹
    </h3>

    <p>
        Elegancia y estilo en cada detalle.
    </p>

</footer>


</body>

</html>