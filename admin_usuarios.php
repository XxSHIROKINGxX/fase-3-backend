<?php

session_start();

require_once __DIR__ . '/config/conexion.php';


/* =========================================================
   PROTEGER PÁGINA
   Solo usuarios administradores pueden entrar
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
   MENSAJES
========================================================= */

$mensaje = "";
$error = "";


/* =========================================================
   ACTUALIZAR ROL DE USUARIO
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cambiar_rol') {

        $id_usuario = isset($_POST['id_usuario'])
            ? (int) $_POST['id_usuario']
            : 0;

        $nuevo_rol = strtolower(trim($_POST['rol'] ?? ''));

        $rolesPermitidos = ['cliente', 'admin'];

        if (
            $id_usuario > 0 &&
            in_array($nuevo_rol, $rolesPermitidos, true)
        ) {

            /*
             * Evitamos que el administrador cambie
             * accidentalmente su propia cuenta.
             */

            if ($id_usuario === (int) $_SESSION['id_usuario']) {

                $error = "No puedes cambiar el rol de tu propia cuenta.";

            } else {

                try {

                    $sqlActualizar = "
                        UPDATE usuarios
                        SET rol = :rol
                        WHERE id_usuario = :id_usuario
                    ";

                    $stmtActualizar = $conexion->prepare($sqlActualizar);

                    $stmtActualizar->bindValue(
                        ':rol',
                        $nuevo_rol,
                        PDO::PARAM_STR
                    );

                    $stmtActualizar->bindValue(
                        ':id_usuario',
                        $id_usuario,
                        PDO::PARAM_INT
                    );

                    $stmtActualizar->execute();

                    $mensaje = "Usuario actualizado correctamente.";

                } catch (PDOException $e) {

                    $error = "No se pudo actualizar el usuario.";

                }
            }

        } else {

            $error = "Datos no válidos.";
        }
    }
}


/* =========================================================
   OBTENER USUARIOS + ESTADÍSTICAS DE PEDIDOS
========================================================= */

try {

    $sql = "
        SELECT
            u.id_usuario,
            u.nombre,
            u.apellido,
            u.email,
            u.rol,
            u.fecha_registro,

            COUNT(p.id_pedido) AS total_pedidos,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(p.estado) = 'entregado'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS pedidos_entregados,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(p.estado) = 'pendiente'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS pedidos_pendientes,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(p.estado) = 'cancelado'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS pedidos_cancelados,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(p.estado) = 'entregado'
                        THEN p.total
                        ELSE 0
                    END
                ),
                0
            ) AS total_comprado,

            MAX(p.fecha_pedido) AS ultimo_pedido

        FROM usuarios u

        LEFT JOIN pedidos p
            ON p.id_usuario = u.id_usuario

        GROUP BY
            u.id_usuario,
            u.nombre,
            u.apellido,
            u.email,
            u.rol,
            u.fecha_registro

        ORDER BY u.id_usuario DESC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute();

    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die(
        "Error al obtener los usuarios: " .
        $e->getMessage()
    );
}


/* =========================================================
   ESTADÍSTICAS GENERALES
========================================================= */

$totalUsuarios = count($usuarios);

$totalClientes = 0;
$totalAdmins = 0;

$totalPedidosGeneral = 0;
$totalVentasGeneral = 0;

foreach ($usuarios as $usuario) {

    if (strtolower($usuario['rol']) === 'admin') {

        $totalAdmins++;

    } else {

        $totalClientes++;
    }

    $totalPedidosGeneral += (int) $usuario['total_pedidos'];
    $totalVentasGeneral += (float) $usuario['total_comprado'];
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


function formatearFechaUsuario($fecha)
{
    if (empty($fecha)) {
        return "No disponible";
    }

    $timestamp = strtotime($fecha);

    if ($timestamp === false) {
        return "No disponible";
    }

    return date('d/m/Y h:i A', $timestamp);
}


function dinero($monto)
{
    return 'RD$' . number_format(
        (float) $monto,
        2,
        '.',
        ','
    );
}


function inicialesUsuario($nombre, $apellido)
{
    $inicialNombre = !empty($nombre)
        ? strtoupper(substr($nombre, 0, 1))
        : '';

    $inicialApellido = !empty($apellido)
        ? strtoupper(substr($apellido, 0, 1))
        : '';

    return $inicialNombre . $inicialApellido;
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
    Administración de usuarios | Roséa Boutique
</title>


<style>

/* =========================================================
   CONFIGURACIÓN GENERAL
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: #fff8fa;
    color: #3f2027;
    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


/* =========================================================
   ENCABEZADO SUPERIOR
========================================================= */

.hero {
    background: #fff0f4;
    text-align: center;
    padding: 45px 20px;
}

.hero h1 {
    font-family: Georgia, serif;
    font-size: 44px;
    color: #c57483;
    margin-bottom: 12px;
}

.hero p {
    color: #b65e6d;
    font-size: 16px;
}


/* =========================================================
   MENÚ
========================================================= */

.navbar {
    background: white;
    border-bottom: 1px solid #f3ccd3;
}

.nav-container {
    max-width: 1100px;
    margin: auto;
    padding: 22px 20px;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    font-family: Georgia, serif;
    font-size: 26px;
    font-weight: bold;
    color: #c57483;
}

.nav-links {
    display: flex;
    gap: 32px;
    align-items: center;
}

.nav-links a {
    text-decoration: none;
    color: #56252d;
    font-size: 14px;
    transition: 0.2s;
}

.nav-links a:hover {
    color: #c57483;
}


/* =========================================================
   CONTENEDOR
========================================================= */

.container {
    width: 90%;
    max-width: 1100px;
    margin: 0 auto;
    padding: 55px 0 80px;
}


/* =========================================================
   TÍTULO
========================================================= */

.page-title {
    text-align: center;
    margin-bottom: 38px;
}

.page-title h2 {
    font-family: Georgia, serif;
    font-size: 38px;
    color: #c57483;
    margin-bottom: 10px;
}

.page-title p {
    color: #b65e6d;
}


/* =========================================================
   MENSAJES
========================================================= */

.mensaje {
    background: #edf9ef;
    border: 1px solid #a8d8ae;
    color: #397447;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 25px;
}

.error {
    background: #fff0f1;
    border: 1px solid #e8aeb7;
    color: #b64255;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 25px;
}


/* =========================================================
   ESTADÍSTICAS GENERALES
========================================================= */

.stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 45px;
}

.stat-card {
    background: white;
    border: 1px solid #efc3cb;
    border-radius: 15px;
    padding: 22px;
}

.stat-card span {
    display: block;
    color: #c65f72;
    font-size: 12px;
    text-transform: uppercase;
    margin-bottom: 12px;
}

.stat-card strong {
    font-family: Georgia, serif;
    font-size: 25px;
    color: #402027;
}

.stat-card.ventas strong {
    color: #c57483;
    font-size: 21px;
}


/* =========================================================
   TÍTULO LISTADO
========================================================= */

.section-title {
    font-family: Georgia, serif;
    font-size: 29px;
    margin-bottom: 20px;
}


/* =========================================================
   TARJETA DE USUARIO
========================================================= */

.usuario-card {
    background: white;
    border: 1px solid #efc3cb;
    border-radius: 16px;
    margin-bottom: 25px;
    overflow: hidden;
}

.usuario-header {
    background: #fff0f4;
    padding: 22px;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

.usuario-identidad {
    display: flex;
    align-items: center;
    gap: 15px;
}

.avatar {
    width: 58px;
    height: 58px;

    background: #ffe4ea;
    border: 2px solid #f3bec8;
    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    font-family: Georgia, serif;
    font-size: 21px;
    font-weight: bold;
    color: #b65267;
}

.usuario-header h3 {
    font-family: Georgia, serif;
    font-size: 23px;
    margin-bottom: 4px;
}

.usuario-id {
    color: #bd6877;
    font-size: 13px;
}


/* =========================================================
   ETIQUETAS
========================================================= */

.badge {
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 13px;
}

.badge-admin {
    background: #f7dbe2;
    color: #b65267;
}

.badge-cliente {
    background: #fff0f4;
    color: #c06475;
}


/* =========================================================
   CUERPO USUARIO
========================================================= */

.usuario-body {
    padding: 24px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}

.info-box {
    background: #fff6f8;
    border: 1px solid #f5d4da;
    border-radius: 12px;
    padding: 17px;
}

.info-box span {
    display: block;
    color: #c55e70;
    font-size: 12px;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.info-box strong {
    font-family: Georgia, serif;
    font-size: 17px;
    word-break: break-word;
}


/* =========================================================
   ESTADÍSTICAS DEL CLIENTE
========================================================= */

.compras-area {
    margin-top: 5px;
    margin-bottom: 24px;
}

.compras-title {
    font-family: Georgia, serif;
    font-size: 20px;
    margin-bottom: 15px;
    color: #56252d;
}

.compras-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
}

.compra-box {
    background: #fff8fa;
    border: 1px solid #f2cbd2;
    border-radius: 12px;
    padding: 15px;
}

.compra-box span {
    display: block;
    color: #c55e70;
    font-size: 10px;
    text-transform: uppercase;
    margin-bottom: 7px;
}

.compra-box strong {
    font-family: Georgia, serif;
    font-size: 17px;
    color: #3f2027;
}

.compra-box.total-comprado strong {
    color: #c57483;
}


/* =========================================================
   RESUMEN DEL CLIENTE
========================================================= */

.resumen-cliente {
    background: #fff6f8;
    border: 1px solid #f2cbd2;
    border-radius: 12px;
    padding: 17px;
    margin-bottom: 22px;
    line-height: 1.6;
    color: #70414a;
}

.resumen-cliente strong {
    color: #3f2027;
}


/* =========================================================
   BOTONES DE ACCIÓN
========================================================= */

.acciones-usuario {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

.btn-link {
    display: inline-block;
    text-decoration: none;
    border: 1px solid #c57483;
    color: #c57483;
    background: white;
    padding: 12px 18px;
    border-radius: 9px;
    font-weight: bold;
    font-size: 13px;
    transition: 0.2s;
}

.btn-link:hover {
    background: #fff0f4;
}

.btn-link-principal {
    background: #c57483;
    color: white;
}

.btn-link-principal:hover {
    background: #ad5f6e;
}


/* =========================================================
   ADMINISTRAR ROL
========================================================= */

.role-area {
    border-top: 1px solid #f2cbd2;
    padding-top: 20px;
}

.role-area label {
    display: block;
    font-weight: bold;
    margin-bottom: 10px;
}

.role-form {
    display: flex;
    gap: 12px;
}

.role-form select {
    flex: 1;
    padding: 13px;
    border: 1px solid #e8aebb;
    border-radius: 9px;
    background: white;
    outline: none;
}

.role-form select:focus {
    border-color: #c57483;
}


/* =========================================================
   BOTONES
========================================================= */

.btn {
    border: none;
    background: #c57483;
    color: white;
    padding: 13px 24px;
    border-radius: 9px;
    cursor: pointer;
    font-weight: bold;
    transition: 0.2s;
}

.btn:hover {
    background: #ad5f6e;
}

.btn-disabled {
    background: #ddd;
    color: #777;
    cursor: not-allowed;
}


/* =========================================================
   SIN PEDIDOS
========================================================= */

.sin-pedidos {
    background: #fff9ed;
    border: 1px solid #eed7a1;
    color: #86651e;
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 22px;
}


/* =========================================================
   SIN USUARIOS
========================================================= */

.empty {
    background: white;
    border: 1px solid #efc3cb;
    padding: 40px;
    border-radius: 15px;
    text-align: center;
    color: #a85d6a;
}


/* =========================================================
   FOOTER
========================================================= */

footer {
    background: #68434a;
    color: white;
    text-align: center;
    padding: 38px 20px;
}

footer h3 {
    font-family: Georgia, serif;
    margin-bottom: 8px;
}

footer p {
    font-size: 13px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width: 1000px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .compras-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 800px) {

    .info-grid {
        grid-template-columns: 1fr;
    }

    .nav-container {
        flex-direction: column;
        gap: 20px;
    }

    .nav-links {
        flex-wrap: wrap;
        justify-content: center;
        gap: 15px;
    }

    .role-form {
        flex-direction: column;
    }

    .usuario-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
}

@media(max-width: 550px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .compras-grid {
        grid-template-columns: 1fr;
    }

    .acciones-usuario {
        flex-direction: column;
    }

    .btn-link {
        text-align: center;
        width: 100%;
    }

    .hero h1 {
        font-size: 35px;
    }

    .page-title h2 {
        font-size: 31px;
    }
}

</style>

</head>


<body>


<!-- ======================================================
     CABECERA
====================================================== -->

<header class="hero">

    <h1>Roséa Boutique</h1>

    <p>
        Elegancia, delicadeza y estilo en cada detalle ♡
    </p>

</header>


<!-- ======================================================
     MENÚ
====================================================== -->

<nav class="navbar">

<div class="nav-container">

    <div class="logo">
        🌹 Roséa Boutique
    </div>

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


<!-- ======================================================
     CONTENIDO
====================================================== -->

<main class="container">


<div class="page-title">

    <h2>
        Administración de usuarios
    </h2>

    <p>
        Consulta las cuentas, compras y actividad de Roséa Boutique ♡
    </p>

</div>


<!-- ======================================================
     MENSAJES
====================================================== -->

<?php if ($mensaje !== ""): ?>

    <div class="mensaje">
        ✓ <?php echo e($mensaje); ?>
    </div>

<?php endif; ?>


<?php if ($error !== ""): ?>

    <div class="error">
        <?php echo e($error); ?>
    </div>

<?php endif; ?>


<!-- ======================================================
     ESTADÍSTICAS GENERALES
====================================================== -->

<section class="stats">

    <div class="stat-card">

        <span>
            Total usuarios
        </span>

        <strong>
            <?php echo $totalUsuarios; ?>
        </strong>

    </div>


    <div class="stat-card">

        <span>
            Clientes
        </span>

        <strong>
            <?php echo $totalClientes; ?>
        </strong>

    </div>


    <div class="stat-card">

        <span>
            Administradores
        </span>

        <strong>
            <?php echo $totalAdmins; ?>
        </strong>

    </div>


    <div class="stat-card">

        <span>
            Pedidos realizados
        </span>

        <strong>
            <?php echo $totalPedidosGeneral; ?>
        </strong>

    </div>


    <div class="stat-card ventas">

        <span>
            Ventas entregadas
        </span>

        <strong>
            <?php echo dinero($totalVentasGeneral); ?>
        </strong>

    </div>

</section>


<h2 class="section-title">
    Usuarios registrados
</h2>


<!-- ======================================================
     USUARIOS
====================================================== -->

<?php if (count($usuarios) > 0): ?>


<?php foreach ($usuarios as $usuario): ?>

<?php

    $esAdmin =
        strtolower($usuario['rol']) === 'admin';

    $esMiCuenta =
        (int) $usuario['id_usuario']
        ===
        (int) $_SESSION['id_usuario'];

    $totalPedidos =
        (int) $usuario['total_pedidos'];

    $pedidosEntregados =
        (int) $usuario['pedidos_entregados'];

    $pedidosPendientes =
        (int) $usuario['pedidos_pendientes'];

    $pedidosCancelados =
        (int) $usuario['pedidos_cancelados'];

    $totalComprado =
        (float) $usuario['total_comprado'];

?>

<article class="usuario-card">


<!-- ======================================================
     CABECERA USUARIO
====================================================== -->

<div class="usuario-header">


<div class="usuario-identidad">

    <div class="avatar">

        <?php

        if ($esAdmin) {

            echo "👑";

        } else {

            echo e(
                inicialesUsuario(
                    $usuario['nombre'],
                    $usuario['apellido']
                )
            );
        }

        ?>

    </div>


    <div>

        <h3>
            <?php
            echo e(
                $usuario['nombre'] .
                ' ' .
                $usuario['apellido']
            );
            ?>
        </h3>

        <div class="usuario-id">
            Usuario #<?php
            echo (int) $usuario['id_usuario'];
            ?>
        </div>

    </div>

</div>


<?php if ($esAdmin): ?>

    <span class="badge badge-admin">
        👑 Administrador
    </span>

<?php else: ?>

    <span class="badge badge-cliente">
        ♡ Cliente
    </span>

<?php endif; ?>


</div>


<!-- ======================================================
     CUERPO
====================================================== -->

<div class="usuario-body">


<div class="info-grid">


<div class="info-box">

    <span>
        Nombre
    </span>

    <strong>
        <?php
        echo e(
            $usuario['nombre'] .
            ' ' .
            $usuario['apellido']
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
        echo e($usuario['email']);
        ?>
    </strong>

</div>


<div class="info-box">

    <span>
        Fecha de registro
    </span>

    <strong>
        <?php
        echo e(
            formatearFechaUsuario(
                $usuario['fecha_registro']
            )
        );
        ?>
    </strong>

</div>


</div>


<!-- ======================================================
     INFORMACIÓN DE COMPRAS
====================================================== -->

<div class="compras-area">

    <h4 class="compras-title">
        Actividad de compras ♡
    </h4>


    <?php if ($totalPedidos > 0): ?>

    <div class="compras-grid">


        <div class="compra-box">

            <span>
                Pedidos realizados
            </span>

            <strong>
                <?php echo $totalPedidos; ?>
            </strong>

        </div>


        <div class="compra-box">

            <span>
                Entregados
            </span>

            <strong>
                <?php echo $pedidosEntregados; ?>
            </strong>

        </div>


        <div class="compra-box">

            <span>
                Pendientes
            </span>

            <strong>
                <?php echo $pedidosPendientes; ?>
            </strong>

        </div>


        <div class="compra-box">

            <span>
                Cancelados
            </span>

            <strong>
                <?php echo $pedidosCancelados; ?>
            </strong>

        </div>


        <div class="compra-box total-comprado">

            <span>
                Total comprado
            </span>

            <strong>
                <?php
                echo dinero($totalComprado);
                ?>
            </strong>

        </div>


    </div>


    <div class="resumen-cliente">

        <strong>
            Último pedido:
        </strong>

        <?php
        echo e(
            formatearFechaUsuario(
                $usuario['ultimo_pedido']
            )
        );
        ?>

        <br>

        <strong>
            Compras completadas:
        </strong>

        <?php
        echo $pedidosEntregados;
        ?>

        pedido<?php
        echo $pedidosEntregados === 1
            ? ''
            : 's';
        ?> entregado<?php
        echo $pedidosEntregados === 1
            ? ''
            : 's';
        ?>.

    </div>


    <?php else: ?>

        <div class="sin-pedidos">
            Este usuario todavía no ha realizado pedidos.
        </div>

    <?php endif; ?>


</div>


<!-- ======================================================
     ACCIONES
====================================================== -->

<div class="acciones-usuario">

    <?php if ($totalPedidos > 0): ?>

        <a
            href="admin_pedidos.php"
            class="btn-link btn-link-principal"
        >
            🛍 Ver pedidos
        </a>

    <?php endif; ?>


    <?php if ($esMiCuenta): ?>

        <a
            href="perfil.php"
            class="btn-link"
        >
            ♡ Ver mi perfil
        </a>

    <?php endif; ?>

</div>


<!-- ======================================================
     CAMBIAR ROL
====================================================== -->

<div class="role-area">


<?php if ($esMiCuenta): ?>


<label>
    Tipo de cuenta
</label>


<div class="role-form">

    <select disabled>

        <option>
            Administrador
        </option>

    </select>


    <button
        type="button"
        class="btn btn-disabled"
        disabled
    >
        Tu cuenta
    </button>

</div>


<?php else: ?>


<label>
    Tipo de cuenta
</label>


<form
    method="POST"
    class="role-form"
    onsubmit="return confirm('¿Confirmas que deseas cambiar el tipo de cuenta de este usuario?');"
>


<input
    type="hidden"
    name="accion"
    value="cambiar_rol"
>


<input
    type="hidden"
    name="id_usuario"
    value="<?php
        echo (int) $usuario['id_usuario'];
    ?>"
>


<select
    name="rol"
    required
>


<option
    value="cliente"

    <?php

    if (
        strtolower($usuario['rol'])
        === 'cliente'
    ) {

        echo 'selected';
    }

    ?>
>

    Cliente

</option>


<option
    value="admin"

    <?php

    if (
        strtolower($usuario['rol'])
        === 'admin'
    ) {

        echo 'selected';
    }

    ?>
>

    Administrador

</option>


</select>


<button
    type="submit"
    class="btn"
>
    ✓ Actualizar rol
</button>


</form>


<?php endif; ?>


</div>


</div>

</article>


<?php endforeach; ?>


<?php else: ?>


<div class="empty">
    No hay usuarios registrados.
</div>


<?php endif; ?>


</main>


<!-- ======================================================
     FOOTER
====================================================== -->

<footer>

    <h3>
        Roséa Boutique 🌹
    </h3>

    <p>
        Administración de usuarios · Panel administrativo
    </p>

</footer>


</body>

</html>