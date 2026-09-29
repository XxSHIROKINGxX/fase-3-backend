<?php

session_start();

require_once __DIR__ . '/config/conexion.php';

// =====================================================
// PROTEGER PÁGINA - SOLO ADMINISTRADORES
// =====================================================

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['rol']) || strtolower($_SESSION['rol']) !== 'admin') {
    header("Location: productos.php");
    exit;
}

// =====================================================
// FUNCIÓN ESCAPAR HTML
// =====================================================

function e($valor)
{
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

// =====================================================
// MENSAJES
// =====================================================

$mensaje = "";
$error = "";

// =====================================================
// MENSAJES DESPUÉS DE REDIRECCIÓN
// =====================================================

if (isset($_GET['agregado'])) {
    $mensaje = "Producto agregado correctamente.";
}

if (isset($_GET['eliminado'])) {
    $mensaje = "Producto eliminado correctamente.";
}

if (isset($_GET['editado'])) {
    $mensaje = "Producto actualizado correctamente.";
}

// =====================================================
// PROCESAR FORMULARIOS
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';

    // =================================================
    // ELIMINAR PRODUCTO
    // =================================================

    if ($accion === 'eliminar') {

        $idProducto = filter_input(
            INPUT_POST,
            'id_producto',
            FILTER_VALIDATE_INT
        );

        if (!$idProducto || $idProducto <= 0) {

            $error = "El producto seleccionado no es válido.";

        } else {

            try {

                // =========================================
                // BUSCAR PRODUCTO
                // =========================================

                $stmt = $conexion->prepare("
                    SELECT
                        id_producto,
                        nombre,
                        imagen
                    FROM productos
                    WHERE id_producto = :id_producto
                    LIMIT 1
                ");

                $stmt->execute([
                    ':id_producto' => $idProducto
                ]);

                $productoEliminar = $stmt->fetch(
                    PDO::FETCH_ASSOC
                );

                if (!$productoEliminar) {

                    $error = "El producto no existe.";

                } else {

                    // =====================================
                    // COMPROBAR SI ESTÁ EN PEDIDOS
                    // =====================================

                    $stmt = $conexion->prepare("
                        SELECT COUNT(*)
                        FROM detalle_pedido
                        WHERE id_producto = :id_producto
                    ");

                    $stmt->execute([
                        ':id_producto' => $idProducto
                    ]);

                    $cantidadDetalles = (int)$stmt->fetchColumn();

                    if ($cantidadDetalles > 0) {

                        /*
                         * NO BORRAMOS productos que forman parte
                         * de pedidos históricos.
                         *
                         * detalle_pedido tiene una clave foránea
                         * hacia productos.id_producto.
                         */

                        $error =
                            'No se puede eliminar "' .
                            $productoEliminar['nombre'] .
                            '" porque forma parte de ' .
                            $cantidadDetalles .
                            ' registro(s) de pedidos. ' .
                            'Puedes editarlo y ocultarlo de la colección ' .
                            'para conservar el historial de ventas.';

                    } else {

                        // =================================
                        // BORRAR PRODUCTO
                        // =================================

                        $stmt = $conexion->prepare("
                            DELETE FROM productos
                            WHERE id_producto = :id_producto
                        ");

                        $stmt->execute([
                            ':id_producto' => $idProducto
                        ]);

                        // =================================
                        // BORRAR IMAGEN DEL SERVIDOR
                        // =================================

                        if (!empty($productoEliminar['imagen'])) {

                            $rutaImagen =
                                __DIR__ .
                                '/img/' .
                                basename(
                                    $productoEliminar['imagen']
                                );

                            if (
                                is_file($rutaImagen) &&
                                file_exists($rutaImagen)
                            ) {
                                @unlink($rutaImagen);
                            }
                        }

                        header(
                            "Location: admin_productos.php?eliminado=1"
                        );

                        exit;
                    }
                }

            } catch (PDOException $e) {

                $error =
                    "No se pudo eliminar el producto. " .
                    "Verifica que no esté relacionado con pedidos.";

            } catch (Exception $e) {

                $error =
                    "Ocurrió un error al eliminar el producto.";
            }
        }

    // =================================================
    // AGREGAR PRODUCTO
    // =================================================

    } elseif ($accion === 'agregar') {

        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio = $_POST['precio'] ?? '';
        $stock = $_POST['stock'] ?? '';
        $categoria = trim($_POST['categoria'] ?? '');
        $disponible = isset($_POST['disponible']) ? 1 : 0;

        if (
            $nombre === '' ||
            $descripcion === '' ||
            $precio === '' ||
            $stock === '' ||
            $categoria === ''
        ) {

            $error = "Completa todos los campos obligatorios.";

        } elseif (
            !is_numeric($precio) ||
            (float)$precio <= 0
        ) {

            $error = "El precio debe ser mayor que cero.";

        } elseif (
            filter_var(
                $stock,
                FILTER_VALIDATE_INT
            ) === false ||
            (int)$stock < 0
        ) {

            $error = "El stock debe ser un número entero mayor o igual a cero.";

        } elseif (
            !isset($_FILES['imagen']) ||
            $_FILES['imagen']['error'] !== UPLOAD_ERR_OK
        ) {

            $error = "Debes seleccionar una imagen para el producto.";

        } else {

            try {

                // =========================================
                // VALIDAR IMAGEN
                // =========================================

                $archivo = $_FILES['imagen'];

                $extension = strtolower(
                    pathinfo(
                        $archivo['name'],
                        PATHINFO_EXTENSION
                    )
                );

                $extensionesPermitidas = [
                    'jpg',
                    'jpeg',
                    'png',
                    'webp'
                ];

                if (
                    !in_array(
                        $extension,
                        $extensionesPermitidas,
                        true
                    )
                ) {

                    $error =
                        "La imagen debe ser JPG, JPEG, PNG o WEBP.";

                } elseif (
                    $archivo['size'] > 5 * 1024 * 1024
                ) {

                    $error =
                        "La imagen no puede superar los 5 MB.";

                } else {

                    // =====================================
                    // NOMBRE ÚNICO DE IMAGEN
                    // =====================================

                    $nombreImagen =
                        'producto_' .
                        date('Ymd_His') .
                        '_' .
                        bin2hex(random_bytes(4)) .
                        '.' .
                        $extension;

                    $carpetaImagenes =
                        __DIR__ . '/img/';

                    if (!is_dir($carpetaImagenes)) {

                        mkdir(
                            $carpetaImagenes,
                            0777,
                            true
                        );
                    }

                    $rutaDestino =
                        $carpetaImagenes .
                        $nombreImagen;

                    // =====================================
                    // GUARDAR IMAGEN
                    // =====================================

                    if (
                        !move_uploaded_file(
                            $archivo['tmp_name'],
                            $rutaDestino
                        )
                    ) {

                        $error =
                            "No se pudo guardar la imagen.";

                    } else {

                        // =================================
                        // INSERTAR PRODUCTO
                        // =================================

                        $sql = "
                            INSERT INTO productos
                            (
                                nombre,
                                descripcion,
                                precio,
                                stock,
                                categoria,
                                imagen,
                                disponible
                            )
                            VALUES
                            (
                                :nombre,
                                :descripcion,
                                :precio,
                                :stock,
                                :categoria,
                                :imagen,
                                :disponible
                            )
                        ";

                        $stmt =
                            $conexion->prepare($sql);

                        $stmt->execute([
                            ':nombre' => $nombre,
                            ':descripcion' => $descripcion,
                            ':precio' => (float)$precio,
                            ':stock' => (int)$stock,
                            ':categoria' => $categoria,
                            ':imagen' => $nombreImagen,
                            ':disponible' => $disponible
                        ]);

                        header(
                            "Location: admin_productos.php?agregado=1"
                        );

                        exit;
                    }
                }

            } catch (PDOException $e) {

                $error =
                    "Error al guardar el producto: " .
                    $e->getMessage();

            } catch (Exception $e) {

                $error =
                    "Ocurrió un error: " .
                    $e->getMessage();
            }
        }

    } else {

        $error =
            "La operación solicitada no es válida.";
    }
}

// =====================================================
// OBTENER PRODUCTOS
// =====================================================

try {

    $stmt = $conexion->prepare("
        SELECT *
        FROM productos
        ORDER BY id_producto DESC
    ");

    $stmt->execute();

    $productos =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die(
        "Error al obtener los productos: " .
        $e->getMessage()
    );
}

// =====================================================
// CALCULAR RESUMEN DE INVENTARIO
// =====================================================

$totalProductos = count($productos);
$totalUnidades = 0;
$totalAgotados = 0;
$totalStockBajo = 0;
$totalDisponibles = 0;
$totalOcultos = 0;

foreach ($productos as $producto) {

    $stockActual =
        (int)$producto['stock'];

    $totalUnidades += $stockActual;

    if ($stockActual === 0) {

        $totalAgotados++;

    } elseif ($stockActual <= 3) {

        $totalStockBajo++;
    }

    if ((int)$producto['disponible'] === 1) {

        $totalDisponibles++;

    } else {

        $totalOcultos++;
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
    Administrar productos | Roséa Boutique
</title>

<style>

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

/* ==========================================
   HERO
========================================== */

.hero {
    background: #fff0f4;
    text-align: center;
    padding: 50px 20px;
}

.hero h1 {
    font-family: Georgia, serif;
    color: #c37382;
    font-size: 43px;
    margin-bottom: 10px;
}

.hero p {
    color: #a85e69;
}

/* ==========================================
   NAVEGACIÓN
========================================== */

.navbar {
    background: white;
    border-bottom: 1px solid #f1d4da;
}

.navbar-contenido {
    max-width: 1100px;
    margin: auto;
    padding: 22px 20px;

    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo {
    font-family: Georgia, serif;
    font-size: 26px;
    font-weight: bold;
    color: #c37382;
}

.menu {
    display: flex;
    gap: 25px;
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

/* ==========================================
   CONTENEDOR
========================================== */

.contenedor {
    max-width: 1100px;
    margin: auto;
    padding: 55px 20px 80px;
}

.titulo {
    text-align: center;
    margin-bottom: 35px;
}

.titulo h2 {
    font-family: Georgia, serif;
    font-size: 35px;
    color: #c37382;
    margin-bottom: 8px;
}

.titulo p {
    color: #a85e69;
}

/* ==========================================
   MENSAJES
========================================== */

.mensaje {
    background: #effaf3;
    border: 1px solid #b9e2c6;
    color: #477557;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.error {
    background: #fff0f2;
    border: 1px solid #efbdc5;
    color: #a34858;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 25px;
}

/* ==========================================
   RESUMEN
========================================== */

.resumen-inventario {
    margin-bottom: 45px;
}

.resumen-inventario h3 {
    font-family: Georgia, serif;
    font-size: 25px;
    margin-bottom: 20px;
}

.resumen-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.resumen-card {
    background: white;
    border: 1px solid #efccd3;
    border-radius: 15px;
    padding: 20px;
}

.resumen-card .etiqueta {
    display: block;
    color: #c37382;
    font-size: 12px;
    text-transform: uppercase;
    margin-bottom: 10px;
}

.resumen-card .numero {
    font-family: Georgia, serif;
    font-size: 27px;
    font-weight: bold;
    color: #4f2f35;
}

.resumen-card.alerta {
    background: #fffaf0;
    border-color: #efd89a;
}

.resumen-card.alerta .numero {
    color: #a77818;
}

.resumen-card.peligro {
    background: #fff1f3;
    border-color: #edbcc5;
}

.resumen-card.peligro .numero {
    color: #bd4f62;
}

.resumen-secundario {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-top: 15px;
}

.resumen-mini {
    background: #fff3f6;
    border: 1px solid #efccd3;
    border-radius: 12px;
    padding: 14px 18px;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

.resumen-mini span {
    color: #a85e69;
    font-size: 13px;
}

.resumen-mini strong {
    font-family: Georgia, serif;
    font-size: 20px;
}

/* ==========================================
   FORMULARIO
========================================== */

.formulario {
    background: white;
    border: 1px solid #efccd3;
    border-radius: 18px;
    padding: 30px;
    margin-bottom: 50px;
}

.formulario h3 {
    font-family: Georgia, serif;
    font-size: 25px;
    margin-bottom: 25px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px;
}

.campo {
    display: flex;
    flex-direction: column;
}

.campo-completo {
    grid-column: 1 / -1;
}

label {
    font-weight: bold;
    margin-bottom: 8px;
    font-size: 14px;
}

input,
textarea,
select {
    border: 1px solid #ecc5cd;
    border-radius: 10px;
    padding: 13px;
    font-size: 14px;
    outline: none;
    background: #fffafb;
}

input:focus,
textarea:focus,
select:focus {
    border-color: #c37382;
}

textarea {
    min-height: 110px;
    resize: vertical;
}

.check {
    display: flex;
    align-items: center;
    gap: 10px;
}

.check input {
    width: auto;
}

.boton {
    border: none;
    background: #c37382;
    color: white;
    padding: 14px 25px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
}

.boton:hover {
    background: #ad6170;
}

/* ==========================================
   PRODUCTOS
========================================== */

.seccion-productos h2 {
    font-family: Georgia, serif;
    font-size: 28px;
    margin-bottom: 22px;
}

.productos-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 25px;
}

.producto {
    background: white;
    border: 1px solid #efccd3;
    border-radius: 18px;
    overflow: hidden;
    position: relative;
}

.imagen-producto {
    height: 340px;
    background: #fff1f4;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 15px;
    position: relative;
}

.imagen-producto img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.sin-imagen {
    color: #b77a85;
}

.estado-stock {
    position: absolute;
    top: 15px;
    right: 15px;

    padding: 7px 12px;
    border-radius: 20px;

    font-size: 11px;
    font-weight: bold;

    z-index: 2;

    box-shadow:
        0 3px 8px
        rgba(0,0,0,0.06);
}

.stock-disponible {
    background: #ddf6e5;
    color: #377a4b;
    border: 1px solid #b7e5c5;
}

.stock-bajo {
    background: #fff2c9;
    color: #956b08;
    border: 1px solid #ead28a;
}

.stock-agotado {
    background: #ffe0e5;
    color: #b84256;
    border: 1px solid #efb9c3;
}

.producto-info {
    padding: 22px;
}

.categoria {
    display: inline-block;

    background: #fde5eb;
    color: #bd6878;

    padding: 6px 12px;
    border-radius: 20px;

    font-size: 12px;
    margin-bottom: 12px;
}

.producto h3 {
    font-family: Georgia, serif;
    font-size: 21px;
    margin-bottom: 10px;
}

.descripcion {
    color: #91646c;
    line-height: 1.5;
    font-size: 14px;
    min-height: 45px;
}

.precio {
    font-size: 23px;
    color: #c37382;
    font-weight: bold;
    margin-top: 15px;
}

.datos {
    background: #fff5f7;
    padding: 12px;
    border-radius: 10px;
    margin-top: 15px;
    font-size: 13px;
    line-height: 1.8;
}

.stock-numero {
    font-weight: bold;
}

.stock-numero.verde {
    color: #438158;
}

.stock-numero.amarillo {
    color: #a77818;
}

.stock-numero.rojo {
    color: #bd4f62;
}

.activo {
    color: #4f8a61;
    font-weight: bold;
}

.inactivo {
    color: #bd6878;
    font-weight: bold;
}

.aviso-stock {
    margin-top: 13px;
    padding: 10px 12px;
    border-radius: 9px;
    font-size: 12px;
    line-height: 1.5;
}

.aviso-stock-bajo {
    background: #fff8df;
    color: #8a6717;
    border: 1px solid #ead493;
}

.aviso-agotado {
    background: #fff0f2;
    color: #a34858;
    border: 1px solid #efbdc5;
}

/* ==========================================
   ACCIONES
========================================== */

.acciones {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 18px;
}

.editar,
.ver,
.eliminar {
    width: 100%;
    min-height: 42px;

    display: flex;
    align-items: center;
    justify-content: center;

    text-align: center;
    text-decoration: none;

    padding: 10px;

    border-radius: 9px;

    font-weight: bold;
    font-size: 13px;
}

.editar {
    background: #c37382;
    color: white;
    border: 1px solid #c37382;
}

.editar:hover {
    background: #ad6170;
}

.ver {
    border: 1px solid #c37382;
    color: #b35f70;
    background: white;
}

.ver:hover {
    background: #fff0f4;
}

.form-eliminar {
    grid-column: 1 / -1;
}

.eliminar {
    border: 1px solid #d58a96;
    background: #fff0f2;
    color: #a34858;
    cursor: pointer;
}

.eliminar:hover {
    background: #ffe2e7;
}

/* ==========================================
   VACÍO
========================================== */

.vacio {
    background: white;
    border: 1px solid #efccd3;
    border-radius: 15px;
    padding: 35px;
    text-align: center;
}

/* ==========================================
   FOOTER
========================================== */

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
}

/* ==========================================
   RESPONSIVE
========================================== */

@media (max-width: 900px) {

    .productos-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .resumen-grid {
        grid-template-columns: repeat(2, 1fr);
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

    .form-grid {
        grid-template-columns: 1fr;
    }

    .campo-completo {
        grid-column: auto;
    }

    .productos-grid {
        grid-template-columns: 1fr;
    }

    .resumen-grid,
    .resumen-secundario {
        grid-template-columns: 1fr;
    }

    .hero h1 {
        font-size: 34px;
    }
}

</style>

</head>

<body>

<!-- ==========================================
     ENCABEZADO
========================================== -->

<section class="hero">

    <h1>
        Roséa Boutique
    </h1>

    <p>
        Elegancia, delicadeza y estilo en cada detalle ♡
    </p>

</section>

<!-- ==========================================
     NAVEGACIÓN
========================================== -->

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

<!-- ==========================================
     CONTENIDO
========================================== -->

<main class="contenedor">

    <div class="titulo">

        <h2>
            Administración de productos
        </h2>

        <p>
            Controla productos, existencias e inventario
            de Roséa Boutique ♡
        </p>

    </div>

    <?php if ($mensaje !== ''): ?>

        <div class="mensaje">
            ✓ <?= e($mensaje) ?>
        </div>

    <?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="error">
            ⚠ <?= e($error) ?>
        </div>

    <?php endif; ?>

    <!-- ======================================
         RESUMEN DE INVENTARIO
    ====================================== -->

    <section class="resumen-inventario">

        <h3>
            📦 Resumen del inventario
        </h3>

        <div class="resumen-grid">

            <div class="resumen-card">

                <span class="etiqueta">
                    Productos registrados
                </span>

                <span class="numero">
                    <?= $totalProductos ?>
                </span>

            </div>

            <div class="resumen-card">

                <span class="etiqueta">
                    Unidades disponibles
                </span>

                <span class="numero">
                    <?= $totalUnidades ?>
                </span>

            </div>

            <div class="resumen-card alerta">

                <span class="etiqueta">
                    Stock bajo
                </span>

                <span class="numero">
                    <?= $totalStockBajo ?>
                </span>

            </div>

            <div class="resumen-card peligro">

                <span class="etiqueta">
                    Productos agotados
                </span>

                <span class="numero">
                    <?= $totalAgotados ?>
                </span>

            </div>

        </div>

        <div class="resumen-secundario">

            <div class="resumen-mini">

                <span>
                    Productos visibles en la colección
                </span>

                <strong>
                    <?= $totalDisponibles ?>
                </strong>

            </div>

            <div class="resumen-mini">

                <span>
                    Productos ocultos
                </span>

                <strong>
                    <?= $totalOcultos ?>
                </strong>

            </div>

        </div>

    </section>

    <!-- ======================================
         AGREGAR PRODUCTO
    ====================================== -->

    <section class="formulario">

        <h3>
            ♡ Agregar nuevo producto
        </h3>

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <!-- Identifica la operación -->
            <input
                type="hidden"
                name="accion"
                value="agregar"
            >

            <div class="form-grid">

                <div class="campo">

                    <label>
                        Nombre del producto
                    </label>

                    <input
                        type="text"
                        name="nombre"
                        placeholder="Ej: Vestido Rosa Primavera"
                        required
                    >

                </div>

                <div class="campo">

                    <label>
                        Categoría
                    </label>

                    <select
                        name="categoria"
                        required
                    >

                        <option value="">
                            Selecciona una categoría
                        </option>

                        <option value="Vestidos">
                            Vestidos
                        </option>

                        <option value="Blusas">
                            Blusas
                        </option>

                        <option value="Faldas">
                            Faldas
                        </option>

                        <option value="Conjuntos">
                            Conjuntos
                        </option>

                        <option value="Accesorios">
                            Accesorios
                        </option>

                    </select>

                </div>

                <div class="campo campo-completo">

                    <label>
                        Descripción
                    </label>

                    <textarea
                        name="descripcion"
                        placeholder="Describe el producto..."
                        required
                    ></textarea>

                </div>

                <div class="campo">

                    <label>
                        Precio RD$
                    </label>

                    <input
                        type="number"
                        name="precio"
                        min="0.01"
                        step="0.01"
                        placeholder="1850.00"
                        required
                    >

                </div>

                <div class="campo">

                    <label>
                        Stock inicial
                    </label>

                    <input
                        type="number"
                        name="stock"
                        min="0"
                        step="1"
                        placeholder="10"
                        required
                    >

                </div>

                <div class="campo campo-completo">

                    <label>
                        Imagen del producto
                    </label>

                    <input
                        type="file"
                        name="imagen"
                        accept=".jpg,.jpeg,.png,.webp"
                        required
                    >

                </div>

                <div class="campo campo-completo">

                    <div class="check">

                        <input
                            type="checkbox"
                            name="disponible"
                            id="disponible"
                            checked
                        >

                        <label
                            for="disponible"
                            style="margin:0;"
                        >
                            Mostrar producto en la colección
                        </label>

                    </div>

                </div>

                <div class="campo campo-completo">

                    <button
                        type="submit"
                        class="boton"
                    >
                        + Agregar producto
                    </button>

                </div>

            </div>

        </form>

    </section>

    <!-- ======================================
         PRODUCTOS EXISTENTES
    ====================================== -->

    <section class="seccion-productos">

        <h2>
            Productos registrados
        </h2>

        <?php if (count($productos) > 0): ?>

            <div class="productos-grid">

                <?php foreach ($productos as $producto): ?>

                    <?php

                    $stockProducto =
                        (int)$producto['stock'];

                    if ($stockProducto === 0) {

                        $textoStock = "Agotado";
                        $claseStock = "stock-agotado";
                        $claseNumero = "rojo";

                    } elseif ($stockProducto <= 3) {

                        $textoStock = "Stock bajo";
                        $claseStock = "stock-bajo";
                        $claseNumero = "amarillo";

                    } else {

                        $textoStock = "Stock disponible";
                        $claseStock = "stock-disponible";
                        $claseNumero = "verde";
                    }

                    ?>

                    <article class="producto">

                        <div class="imagen-producto">

                            <span
                                class="estado-stock <?= e($claseStock) ?>"
                            >
                                <?= e($textoStock) ?>
                            </span>

                            <?php if (!empty($producto['imagen'])): ?>

                                <img
                                    src="img/<?= e($producto['imagen']) ?>"
                                    alt="<?= e($producto['nombre']) ?>"
                                >

                            <?php else: ?>

                                <span class="sin-imagen">
                                    Sin imagen
                                </span>

                            <?php endif; ?>

                        </div>

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

                            <div class="datos">

                                <div>

                                    <strong>
                                        Stock:
                                    </strong>

                                    <span
                                        class="stock-numero <?= e($claseNumero) ?>"
                                    >
                                        <?= $stockProducto ?>

                                        unidad<?= $stockProducto === 1 ? '' : 'es' ?>
                                    </span>

                                </div>

                                <div>

                                    <strong>
                                        Inventario:
                                    </strong>

                                    <span
                                        class="stock-numero <?= e($claseNumero) ?>"
                                    >
                                        <?= e($textoStock) ?>
                                    </span>

                                </div>

                                <div>

                                    <strong>
                                        Estado:
                                    </strong>

                                    <?php if ((int)$producto['disponible'] === 1): ?>

                                        <span class="activo">
                                            Visible
                                        </span>

                                    <?php else: ?>

                                        <span class="inactivo">
                                            Oculto
                                        </span>

                                    <?php endif; ?>

                                </div>

                                <div>

                                    <strong>
                                        ID:
                                    </strong>

                                    #<?= (int)$producto['id_producto'] ?>

                                </div>

                            </div>

                            <?php if ($stockProducto === 0): ?>

                                <div
                                    class="aviso-stock aviso-agotado"
                                >
                                    🔴 Este producto está agotado.
                                    Debes reponer existencias antes
                                    de continuar vendiéndolo.
                                </div>

                            <?php elseif ($stockProducto <= 3): ?>

                                <div
                                    class="aviso-stock aviso-stock-bajo"
                                >
                                    🟡 Quedan pocas unidades.
                                    Se recomienda reponer este producto.
                                </div>

                            <?php endif; ?>

                            <!-- ===========================
                                 ACCIONES CRUD
                            ============================ -->

                            <div class="acciones">

                                <a
                                    class="editar"
                                    href="editar_producto.php?id=<?= (int)$producto['id_producto'] ?>"
                                >
                                    ✏ Editar
                                </a>

                                <a
                                    class="ver"
                                    href="productos.php"
                                >
                                    Ver tienda
                                </a>

                                <form
                                    method="POST"
                                    class="form-eliminar"
                                    onsubmit="return confirm(
                                        '¿Estás seguro de que deseas eliminar <?= e(addslashes($producto['nombre'])) ?>? Esta acción no se puede deshacer.'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="accion"
                                        value="eliminar"
                                    >

                                    <input
                                        type="hidden"
                                        name="id_producto"
                                        value="<?= (int)$producto['id_producto'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="eliminar"
                                    >
                                        🗑 Eliminar producto
                                    </button>

                                </form>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="vacio">
                Todavía no existen productos registrados.
            </div>

        <?php endif; ?>

    </section>

</main>

<footer>

    <h3>
        Roséa Boutique 🌹
    </h3>

    <p>
        Administración de productos · Inventario ·
        Panel administrativo
    </p>

</footer>

</body>

</html>