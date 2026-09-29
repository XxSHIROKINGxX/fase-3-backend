<?php

session_start();

require_once __DIR__ . '/config/conexion.php';

// ======================================================
// VERIFICAR SESIÓN
// ======================================================

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

// ======================================================
// VERIFICAR QUE SEA ADMINISTRADOR
// ======================================================

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: productos.php");
    exit;
}

// ======================================================
// OBTENER ID DEL PRODUCTO
// ======================================================

$id_producto = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($id_producto <= 0) {
    die("Producto no válido.");
}

$mensaje = "";
$error = "";

// ======================================================
// BUSCAR PRODUCTO
// ======================================================

try {

    $sql = "SELECT * FROM productos
            WHERE id_producto = :id_producto";

    $stmt = $conexion->prepare($sql);

    $stmt->bindValue(
        ':id_producto',
        $id_producto,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        die("El producto no existe.");
    }

} catch (PDOException $e) {

    die(
        "Error al obtener el producto: "
        . $e->getMessage()
    );
}


// ======================================================
// ACTUALIZAR PRODUCTO
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');

    $precio = isset($_POST['precio'])
        ? (float) $_POST['precio']
        : 0;

    $stock = isset($_POST['stock'])
        ? (int) $_POST['stock']
        : 0;

    $disponible = isset($_POST['disponible'])
        ? 1
        : 0;

    // Imagen actual
    $imagen = $producto['imagen'];

    // ==================================================
    // VALIDACIONES
    // ==================================================

    if ($nombre === '') {

        $error = "Debes escribir el nombre del producto.";

    } elseif ($descripcion === '') {

        $error = "Debes escribir una descripción.";

    } elseif ($categoria === '') {

        $error = "Debes seleccionar una categoría.";

    } elseif ($precio <= 0) {

        $error = "El precio debe ser mayor que cero.";

    } elseif ($stock < 0) {

        $error = "El stock no puede ser negativo.";

    } else {

        // ==============================================
        // PROCESAR NUEVA IMAGEN
        // ==============================================

        if (
            isset($_FILES['imagen']) &&
            $_FILES['imagen']['error'] === UPLOAD_ERR_OK
        ) {

            $archivoTemporal =
                $_FILES['imagen']['tmp_name'];

            $nombreOriginal =
                $_FILES['imagen']['name'];

            $extension = strtolower(
                pathinfo(
                    $nombreOriginal,
                    PATHINFO_EXTENSION
                )
            );

            $extensionesPermitidas = [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];

            if (!in_array(
                $extension,
                $extensionesPermitidas,
                true
            )) {

                $error =
                    "La imagen debe ser JPG, JPEG, PNG o WEBP.";

            } else {

                $nuevoNombre =
                    'producto_' .
                    time() .
                    '_' .
                    $id_producto .
                    '.' .
                    $extension;

                $rutaDestino =
                    __DIR__ .
                    '/img/' .
                    $nuevoNombre;

                if (
                    move_uploaded_file(
                        $archivoTemporal,
                        $rutaDestino
                    )
                ) {

                    $imagen = $nuevoNombre;

                } else {

                    $error =
                        "No se pudo guardar la nueva imagen.";
                }
            }
        }

        // ==============================================
        // ACTUALIZAR EN MYSQL
        // ==============================================

        if ($error === '') {

            try {

                $sqlActualizar =
                    "UPDATE productos
                     SET
                        nombre = :nombre,
                        descripcion = :descripcion,
                        precio = :precio,
                        stock = :stock,
                        categoria = :categoria,
                        imagen = :imagen,
                        disponible = :disponible
                     WHERE id_producto = :id_producto";

                $stmtActualizar =
                    $conexion->prepare($sqlActualizar);

                $stmtActualizar->bindValue(
                    ':nombre',
                    $nombre
                );

                $stmtActualizar->bindValue(
                    ':descripcion',
                    $descripcion
                );

                $stmtActualizar->bindValue(
                    ':precio',
                    $precio
                );

                $stmtActualizar->bindValue(
                    ':stock',
                    $stock,
                    PDO::PARAM_INT
                );

                $stmtActualizar->bindValue(
                    ':categoria',
                    $categoria
                );

                $stmtActualizar->bindValue(
                    ':imagen',
                    $imagen
                );

                $stmtActualizar->bindValue(
                    ':disponible',
                    $disponible,
                    PDO::PARAM_INT
                );

                $stmtActualizar->bindValue(
                    ':id_producto',
                    $id_producto,
                    PDO::PARAM_INT
                );

                $stmtActualizar->execute();

                // Recargar producto actualizado
                $stmt->execute();

                $producto =
                    $stmt->fetch(PDO::FETCH_ASSOC);

                $mensaje =
                    "Producto actualizado correctamente.";

            } catch (PDOException $e) {

                $error =
                    "Error al actualizar el producto: "
                    . $e->getMessage();
            }
        }
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
    Editar producto | Roséa Boutique
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fff8fa;
    color: #4b292f;
}

/* =========================
   CABECERA
========================= */

.hero {
    text-align: center;
    background: #fff0f4;
    padding: 45px 20px;
}

.hero h1 {
    margin: 0;
    font-family: Georgia, serif;
    font-size: 44px;
    color: #c56f80;
}

.hero p {
    color: #ad5f6d;
}

/* =========================
   NAVEGACIÓN
========================= */

nav {
    background: white;
    border-bottom: 1px solid #f2ccd4;
}

.nav-container {
    max-width: 1100px;
    margin: auto;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo {
    font-family: Georgia, serif;
    font-size: 25px;
    font-weight: bold;
    color: #c56f80;
}

nav a {
    color: #65323b;
    text-decoration: none;
    margin-left: 28px;
    font-size: 14px;
}

nav a:hover {
    color: #c56f80;
}

/* =========================
   CONTENIDO
========================= */

.container {
    width: 90%;
    max-width: 1000px;
    margin: 50px auto 80px;
}

.title {
    text-align: center;
    margin-bottom: 35px;
}

.title h2 {
    font-family: Georgia, serif;
    font-size: 36px;
    color: #c56f80;
    margin-bottom: 8px;
}

.title p {
    color: #ad5f6d;
}

/* =========================
   TARJETA
========================= */

.card {
    background: white;
    border: 1px solid #efc9d1;
    border-radius: 18px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(150, 80, 95, 0.08);
}

.card h3 {
    margin-top: 0;
    font-family: Georgia, serif;
    font-size: 25px;
}

/* =========================
   MENSAJES
========================= */

.success {
    background: #edf8ef;
    border: 1px solid #b9dfc0;
    color: #397044;
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.error {
    background: #fff0f1;
    border: 1px solid #efb4bd;
    color: #a84151;
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 20px;
}

/* =========================
   FORMULARIO
========================= */

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group.full {
    grid-column: 1 / -1;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
}

input,
select,
textarea {
    width: 100%;
    padding: 13px;
    border: 1px solid #edc3cc;
    border-radius: 9px;
    background: #fffafb;
    color: #4b292f;
    outline: none;
}

input:focus,
select:focus,
textarea:focus {
    border-color: #c56f80;
}

textarea {
    min-height: 120px;
    resize: vertical;
}

/* =========================
   IMAGEN ACTUAL
========================= */

.current-image {
    text-align: center;
    background: #fff5f7;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
}

.current-image img {
    max-width: 260px;
    max-height: 420px;
    width: auto;
    height: auto;
    border-radius: 10px;
    object-fit: contain;
}

.current-image p {
    margin-bottom: 15px;
    font-weight: bold;
}

/* =========================
   CHECKBOX
========================= */

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 15px 0 25px;
}

.checkbox-group input {
    width: auto;
}

.checkbox-group label {
    margin: 0;
}

/* =========================
   BOTONES
========================= */

.buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.btn {
    display: block;
    text-align: center;
    padding: 14px;
    border-radius: 9px;
    text-decoration: none;
    font-weight: bold;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background: #c57484;
    color: white;
}

.btn-primary:hover {
    background: #ad6170;
}

.btn-secondary {
    background: white;
    color: #c57484;
    border: 1px solid #c57484;
}

.btn-secondary:hover {
    background: #fff0f4;
}

/* =========================
   FOOTER
========================= */

footer {
    background: #654047;
    color: white;
    text-align: center;
    padding: 35px 20px;
}

footer h3 {
    font-family: Georgia, serif;
    margin-bottom: 5px;
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 700px) {

    .form-grid,
    .buttons {
        grid-template-columns: 1fr;
    }

    .nav-container {
        flex-direction: column;
        gap: 15px;
    }

    nav a {
        margin: 0 8px;
    }

    .hero h1 {
        font-size: 34px;
    }
}

</style>

</head>

<body>

<!-- CABECERA -->

<section class="hero">

    <h1>Roséa Boutique</h1>

    <p>
        Elegancia, delicadeza y estilo en cada detalle ♡
    </p>

</section>


<!-- NAVEGACIÓN -->

<nav>

<div class="nav-container">

    <div class="logo">
        🌹 Roséa Boutique
    </div>

    <div>

        <a href="productos.php">
            Colección
        </a>

        <a href="admin.php">
            Panel Admin
        </a>

        <a href="admin_productos.php">
            Productos
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


<!-- CONTENIDO -->

<main class="container">

<div class="title">

    <h2>Editar producto</h2>

    <p>
        Actualiza la información de tu producto ♡
    </p>

</div>


<div class="card">

    <h3>
        ♡ <?php
        echo htmlspecialchars(
            $producto['nombre']
        );
        ?>
    </h3>


    <?php if ($mensaje !== ''): ?>

        <div class="success">

            ✓ <?php
            echo htmlspecialchars($mensaje);
            ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <?php if (!empty($producto['imagen'])): ?>

        <div class="current-image">

            <p>Imagen actual</p>

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

        </div>

    <?php endif; ?>


    <form
        method="POST"
        enctype="multipart/form-data"
    >

        <div class="form-grid">


            <!-- NOMBRE -->

            <div class="form-group">

                <label>
                    Nombre del producto
                </label>

                <input
                    type="text"
                    name="nombre"
                    value="<?php
                    echo htmlspecialchars(
                        $producto['nombre']
                    );
                    ?>"
                    required
                >

            </div>


            <!-- CATEGORÍA -->

            <div class="form-group">

                <label>
                    Categoría
                </label>

                <select
                    name="categoria"
                    required
                >

                    <option
                        value="Vestidos"
                        <?php
                        if (
                            $producto['categoria']
                            === 'Vestidos'
                        ) echo 'selected';
                        ?>
                    >
                        Vestidos
                    </option>

                    <option
                        value="Blusas"
                        <?php
                        if (
                            $producto['categoria']
                            === 'Blusas'
                        ) echo 'selected';
                        ?>
                    >
                        Blusas
                    </option>

                    <option
                        value="Faldas"
                        <?php
                        if (
                            $producto['categoria']
                            === 'Faldas'
                        ) echo 'selected';
                        ?>
                    >
                        Faldas
                    </option>

                    <option
                        value="Conjuntos"
                        <?php
                        if (
                            $producto['categoria']
                            === 'Conjuntos'
                        ) echo 'selected';
                        ?>
                    >
                        Conjuntos
                    </option>

                    <option
                        value="Accesorios"
                        <?php
                        if (
                            $producto['categoria']
                            === 'Accesorios'
                        ) echo 'selected';
                        ?>
                    >
                        Accesorios
                    </option>

                </select>

            </div>


            <!-- DESCRIPCIÓN -->

            <div class="form-group full">

                <label>
                    Descripción
                </label>

                <textarea
                    name="descripcion"
                    required
                ><?php
                echo htmlspecialchars(
                    $producto['descripcion']
                );
                ?></textarea>

            </div>


            <!-- PRECIO -->

            <div class="form-group">

                <label>
                    Precio RD$
                </label>

                <input
                    type="number"
                    name="precio"
                    min="0.01"
                    step="0.01"
                    value="<?php
                    echo htmlspecialchars(
                        $producto['precio']
                    );
                    ?>"
                    required
                >

            </div>


            <!-- STOCK -->

            <div class="form-group">

                <label>
                    Stock
                </label>

                <input
                    type="number"
                    name="stock"
                    min="0"
                    value="<?php
                    echo (int)$producto['stock'];
                    ?>"
                    required
                >

            </div>


            <!-- IMAGEN -->

            <div class="form-group full">

                <label>
                    Cambiar imagen
                </label>

                <input
                    type="file"
                    name="imagen"
                    accept=".jpg,.jpeg,.png,.webp"
                >

                <small>
                    Déjalo vacío si quieres conservar
                    la imagen actual.
                </small>

            </div>


        </div>


        <!-- DISPONIBILIDAD -->

        <div class="checkbox-group">

            <input
                type="checkbox"
                id="disponible"
                name="disponible"
                <?php
                if ((int)$producto['disponible'] === 1) {
                    echo 'checked';
                }
                ?>
            >

            <label for="disponible">
                Mostrar producto en la colección
            </label>

        </div>


        <!-- BOTONES -->

        <div class="buttons">

            <a
                href="admin_productos.php"
                class="btn btn-secondary"
            >
                ← Volver
            </a>

            <button
                type="submit"
                class="btn btn-primary"
            >
                ✓ Guardar cambios
            </button>

        </div>

    </form>

</div>

</main>


<!-- FOOTER -->

<footer>

    <h3>
        Roséa Boutique 🌹
    </h3>

    <p>
        Administración de productos · Panel administrativo
    </p>

</footer>

</body>

</html>