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
   OBTENER PEDIDO
========================================================= */

$id_usuario = (int)$_SESSION['id_usuario'];

$id_pedido = isset($_GET['id'])
    ? (int)$_GET['id']
    : (
        isset($_POST['id_pedido'])
            ? (int)$_POST['id_pedido']
            : 0
    );


if ($id_pedido <= 0) {

    header("Location: perfil.php");
    exit;
}


/* =========================================================
   VARIABLES
========================================================= */

$error = "";
$mensaje = "";


/* =========================================================
   BUSCAR PEDIDO

   IMPORTANTE:
   También comprobamos que pertenezca al usuario conectado.
========================================================= */

try {

    $sqlPedido = "
        SELECT
            p.id_pedido,
            p.id_usuario,
            p.fecha_pedido,
            p.total,
            p.estado,
            p.metodo_pago,
            p.comprobante_pago,
            p.estado_pago,
            p.fecha_comprobante,

            u.nombre,
            u.apellido,
            u.email

        FROM pedidos p

        INNER JOIN usuarios u
            ON p.id_usuario = u.id_usuario

        WHERE
            p.id_pedido = :id_pedido
            AND p.id_usuario = :id_usuario

        LIMIT 1
    ";


    $stmtPedido =
        $conexion->prepare($sqlPedido);


    $stmtPedido->bindValue(
        ':id_pedido',
        $id_pedido,
        PDO::PARAM_INT
    );


    $stmtPedido->bindValue(
        ':id_usuario',
        $id_usuario,
        PDO::PARAM_INT
    );


    $stmtPedido->execute();


    $pedido =
        $stmtPedido->fetch(PDO::FETCH_ASSOC);


    if (!$pedido) {

        die(
            "El pedido no existe o no tienes permiso para acceder a él."
        );
    }


    /* =====================================================
       SOLO TRANSFERENCIAS
    ===================================================== */

    if (
        strtolower(
            trim($pedido['metodo_pago'])
        ) !== 'transferencia'
    ) {

        header(
            "Location: pedido_exitoso.php?id=" .
            $id_pedido
        );

        exit;
    }


} catch (PDOException $e) {

    die(
        "Error al consultar el pedido: " .
        htmlspecialchars(
            $e->getMessage()
        )
    );
}


/* =========================================================
   PROCESAR COMPROBANTE
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {


        /* =================================================
           COMPROBAR ARCHIVO
        ================================================= */

        if (
            !isset($_FILES['comprobante']) ||
            !is_array($_FILES['comprobante'])
        ) {

            throw new Exception(
                "Debes seleccionar una imagen del comprobante."
            );
        }


        $archivo =
            $_FILES['comprobante'];


        /* =================================================
           COMPROBAR ERRORES DE SUBIDA
        ================================================= */

        if (
            !isset($archivo['error']) ||
            is_array($archivo['error'])
        ) {

            throw new Exception(
                "El archivo recibido no es válido."
            );
        }


        switch ($archivo['error']) {

            case UPLOAD_ERR_OK:

                break;


            case UPLOAD_ERR_NO_FILE:

                throw new Exception(
                    "Debes seleccionar una imagen del comprobante."
                );


            case UPLOAD_ERR_INI_SIZE:

            case UPLOAD_ERR_FORM_SIZE:

                throw new Exception(
                    "El archivo seleccionado es demasiado grande."
                );


            default:

                throw new Exception(
                    "Ocurrió un error al subir el comprobante."
                );
        }


        /* =================================================
           TAMAÑO MÁXIMO: 5 MB
        ================================================= */

        $tamanoMaximo =
            5 * 1024 * 1024;


        if (
            (int)$archivo['size'] <= 0
        ) {

            throw new Exception(
                "El archivo seleccionado está vacío."
            );
        }


        if (
            (int)$archivo['size'] >
            $tamanoMaximo
        ) {

            throw new Exception(
                "El comprobante no puede superar los 5 MB."
            );
        }


        /* =================================================
           COMPROBAR QUE REALMENTE SEA UNA IMAGEN
        ================================================= */

        $informacionImagen =
            @getimagesize(
                $archivo['tmp_name']
            );


        if ($informacionImagen === false) {

            throw new Exception(
                "El archivo seleccionado no es una imagen válida."
            );
        }


        /* =================================================
           VALIDAR TIPO MIME
        ================================================= */

        $mime =
            strtolower(
                trim(
                    $informacionImagen['mime'] ?? ''
                )
            );


        $tiposPermitidos = [

            'image/jpeg' => 'jpg',

            'image/png' => 'png',

            'image/webp' => 'webp'
        ];


        if (
            !isset(
                $tiposPermitidos[$mime]
            )
        ) {

            throw new Exception(
                "Formato no permitido. Usa JPG, JPEG, PNG o WEBP."
            );
        }


        $extension =
            $tiposPermitidos[$mime];


        /* =================================================
           CREAR CARPETA DE COMPROBANTES
        ================================================= */

        $carpetaComprobantes =
            __DIR__ .
            DIRECTORY_SEPARATOR .
            'comprobantes';


        if (
            !is_dir(
                $carpetaComprobantes
            )
        ) {

            if (
                !mkdir(
                    $carpetaComprobantes,
                    0755,
                    true
                )
            ) {

                throw new Exception(
                    "No fue posible crear la carpeta de comprobantes."
                );
            }
        }


        /* =================================================
           NOMBRE SEGURO DEL ARCHIVO
        ================================================= */

        try {

            $codigoAleatorio =
                bin2hex(
                    random_bytes(8)
                );

        } catch (Exception $e) {

            $codigoAleatorio =
                str_replace(
                    '.',
                    '',
                    uniqid('', true)
                );
        }


        $nombreArchivo =
            'pedido_' .
            $id_pedido .
            '_' .
            date('Ymd_His') .
            '_' .
            $codigoAleatorio .
            '.' .
            $extension;


        $rutaDestino =
            $carpetaComprobantes .
            DIRECTORY_SEPARATOR .
            $nombreArchivo;


        /* =================================================
           MOVER ARCHIVO
        ================================================= */

        if (
            !move_uploaded_file(
                $archivo['tmp_name'],
                $rutaDestino
            )
        ) {

            throw new Exception(
                "No fue posible guardar el comprobante."
            );
        }


        /* =================================================
           GUARDAR EN BASE DE DATOS
        ================================================= */

        try {

            $sqlActualizar = "
                UPDATE pedidos

                SET
                    comprobante_pago =
                        :comprobante_pago,

                    estado_pago =
                        'pendiente',

                    fecha_comprobante =
                        NOW()

                WHERE
                    id_pedido =
                        :id_pedido

                    AND id_usuario =
                        :id_usuario

                    AND metodo_pago =
                        'transferencia'
            ";


            $stmtActualizar =
                $conexion->prepare(
                    $sqlActualizar
                );


            $stmtActualizar->bindValue(
                ':comprobante_pago',
                $nombreArchivo,
                PDO::PARAM_STR
            );


            $stmtActualizar->bindValue(
                ':id_pedido',
                $id_pedido,
                PDO::PARAM_INT
            );


            $stmtActualizar->bindValue(
                ':id_usuario',
                $id_usuario,
                PDO::PARAM_INT
            );


            $stmtActualizar->execute();


            if (
                $stmtActualizar->rowCount() <= 0
            ) {

                if (
                    file_exists(
                        $rutaDestino
                    )
                ) {

                    @unlink(
                        $rutaDestino
                    );
                }


                throw new Exception(
                    "No fue posible asociar el comprobante al pedido."
                );
            }


        } catch (Exception $e) {

            if (
                file_exists(
                    $rutaDestino
                )
            ) {

                @unlink(
                    $rutaDestino
                );
            }


            throw $e;
        }


        /* =================================================
           ELIMINAR COMPROBANTE ANTERIOR

           Esto permite volver a subirlo si el administrador
           lo rechaza.
        ================================================= */

        $comprobanteAnterior =
            trim(
                (string)(
                    $pedido['comprobante_pago'] ?? ''
                )
            );


        if (
            $comprobanteAnterior !== '' &&
            $comprobanteAnterior !== $nombreArchivo
        ) {

            $nombreAnteriorSeguro =
                basename(
                    $comprobanteAnterior
                );


            $rutaAnterior =
                $carpetaComprobantes .
                DIRECTORY_SEPARATOR .
                $nombreAnteriorSeguro;


            if (
                is_file(
                    $rutaAnterior
                )
            ) {

                @unlink(
                    $rutaAnterior
                );
            }
        }


        /* =================================================
           REDIRECCIÓN
        ================================================= */

        header(
            "Location: pedido_exitoso.php?id=" .
            $id_pedido .
            "&comprobante=ok"
        );

        exit;


    } catch (Exception $e) {

        $error =
            $e->getMessage();
    }
}


/* =========================================================
   ESTADO DEL PAGO
========================================================= */

$estadoPago =
    strtolower(
        trim(
            $pedido['estado_pago'] ??
            'pendiente'
        )
    );


/* =========================================================
   TEXTO DEL ESTADO
========================================================= */

function textoEstadoPago($estado)
{

    switch ($estado) {

        case 'verificado':

            return 'Pago verificado';


        case 'rechazado':

            return 'Comprobante rechazado';


        default:

            return 'Pendiente de verificación';
    }
}


/* =========================================================
   CLASE DEL ESTADO
========================================================= */

$claseEstadoPago =
    'pago-pendiente';


if (
    $estadoPago ===
    'verificado'
) {

    $claseEstadoPago =
        'pago-verificado';

} elseif (
    $estadoPago ===
    'rechazado'
) {

    $claseEstadoPago =
        'pago-rechazado';
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
    Comprobante de pago | Roséa Boutique
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

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


/* =====================================================
   HERO
===================================================== */

.hero {
    background:
        linear-gradient(
            135deg,
            #fff5f7,
            #fdecef
        );

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


/* =====================================================
   NAVEGACIÓN
===================================================== */

nav {
    background: white;

    border-bottom:
        1px solid #f2d9de;
}


.nav-container {
    max-width: 1120px;

    margin: auto;

    min-height: 75px;

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    padding: 0 20px;
}


.logo {
    font-family: Georgia, serif;

    font-size: 27px;

    font-weight: bold;

    color: #c47482;

    text-decoration: none;
}


.menu {
    display: flex;

    align-items: center;

    gap: 27px;
}


.menu a {
    color: #6b333d;

    font-size: 14px;

    text-decoration: none;
}


.menu a:hover {
    color: #c47482;
}


/* =====================================================
   CONTENIDO
===================================================== */

main {
    max-width: 760px;

    margin: auto;

    padding: 55px 20px 80px;
}


/* =====================================================
   TÍTULO
===================================================== */

.titulo {
    text-align: center;

    margin-bottom: 35px;
}


.icono {
    width: 70px;

    height: 70px;

    margin:
        0 auto 18px;

    border-radius: 50%;

    background: #fde6eb;

    border:
        2px solid #efbdc7;

    display: flex;

    justify-content: center;

    align-items: center;

    font-size: 31px;
}


.titulo h2 {
    font-family: Georgia, serif;

    color: #c47482;

    font-size: 34px;

    margin-bottom: 10px;
}


.titulo p {
    color: #ad626d;

    line-height: 1.6;
}


/* =====================================================
   TARJETA
===================================================== */

.card {
    background: white;

    border:
        1px solid #f0ccd3;

    border-radius: 20px;

    padding: 32px;

    box-shadow:
        0 12px 30px
        rgba(
            167,
            92,
            105,
            0.08
        );
}


/* =====================================================
   PEDIDO
===================================================== */

.pedido-header {
    display: flex;

    justify-content:
        space-between;

    align-items: center;

    gap: 15px;

    border-bottom:
        1px solid #f1d7dc;

    padding-bottom: 22px;

    margin-bottom: 25px;
}


.pedido-header h3 {
    font-family: Georgia, serif;

    font-size: 25px;

    margin-bottom: 6px;
}


.pedido-header p {
    color: #a65f69;

    font-size: 13px;
}


.total {
    color: #c47482;

    font-family: Georgia, serif;

    font-size: 23px;

    font-weight: bold;

    white-space: nowrap;
}


/* =====================================================
   ESTADO
===================================================== */

.estado-pago {
    display: inline-block;

    padding: 8px 14px;

    border-radius: 30px;

    font-size: 12px;

    font-weight: bold;

    margin-bottom: 25px;
}


.pago-pendiente {
    background: #fff1cf;

    color: #8b6a16;
}


.pago-verificado {
    background: #e3f6e7;

    color: #377944;
}


.pago-rechazado {
    background: #ffe4e4;

    color: #a43e3e;
}


/* =====================================================
   INFORMACIÓN
===================================================== */

.info {
    background: #fff6f8;

    border:
        1px solid #f3d7dd;

    border-radius: 13px;

    padding: 18px;

    margin-bottom: 25px;
}


.info p {
    color: #74464f;

    line-height: 1.7;

    font-size: 14px;
}


.info strong {
    color: #4d292f;
}


/* =====================================================
   FORMULARIO
===================================================== */

.form-box {
    background: #fffafb;

    border:
        1px solid #f1d5db;

    border-radius: 15px;

    padding: 23px;
}


.form-box h4 {
    font-family: Georgia, serif;

    font-size: 21px;

    margin-bottom: 10px;
}


.form-box > p {
    color: #97616b;

    line-height: 1.6;

    font-size: 13px;

    margin-bottom: 20px;
}


.archivo {
    border:
        2px dashed #e6abb7;

    border-radius: 13px;

    padding: 25px;

    background: #fff5f7;

    margin-bottom: 18px;

    text-align: center;
}


.archivo input {
    width: 100%;

    color: #674047;
}


.ayuda {
    margin-top: 10px;

    color: #a46c76;

    font-size: 12px;

    line-height: 1.5;
}


/* =====================================================
   PREVISUALIZACIÓN
===================================================== */

.preview {
    display: none;

    margin-bottom: 20px;

    text-align: center;
}


.preview.mostrar {
    display: block;
}


.preview img {
    max-width: 100%;

    max-height: 420px;

    border-radius: 13px;

    border:
        1px solid #edcbd2;

    background: white;

    padding: 5px;
}


/* =====================================================
   ERROR
===================================================== */

.error {
    background: #fff0f0;

    border:
        1px solid #e8abab;

    color: #9c3c3c;

    border-radius: 10px;

    padding: 14px;

    margin-bottom: 22px;

    line-height: 1.5;
}


/* =====================================================
   BOTONES
===================================================== */

.btn {
    width: 100%;

    display: block;

    border: none;

    border-radius: 10px;

    padding: 15px;

    font-weight: bold;

    cursor: pointer;

    text-align: center;

    text-decoration: none;

    font-size: 14px;
}


.btn-subir {
    background: #c47482;

    color: white;

    margin-bottom: 12px;
}


.btn-subir:hover {
    background: #ad5f6d;
}


.btn-secundario {
    background: white;

    color: #c47482;

    border:
        1px solid #c47482;
}


.btn-secundario:hover {
    background: #fff1f4;
}


/* =====================================================
   COMPROBANTE EXISTENTE
===================================================== */

.comprobante-existente {
    background: #eef9f0;

    border:
        1px solid #b9ddbf;

    border-radius: 13px;

    padding: 18px;

    margin-bottom: 23px;

    color: #397246;

    line-height: 1.6;
}


/* =====================================================
   FOOTER
===================================================== */

footer {
    background: #604047;

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


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 650px) {

    .hero h1 {
        font-size: 34px;
    }


    .nav-container {
        flex-direction: column;

        padding:
            20px;

        gap: 18px;
    }


    .menu {
        flex-wrap: wrap;

        justify-content: center;

        gap: 18px;
    }


    .card {
        padding: 22px;
    }


    .pedido-header {
        flex-direction: column;

        align-items:
            flex-start;
    }
}

</style>

</head>


<body>


<!-- =====================================================
     HERO
====================================================== -->

<header class="hero">

    <h1>
        Roséa Boutique
    </h1>

    <p>
        Elegancia, delicadeza y estilo en cada detalle ♡
    </p>

</header>


<!-- =====================================================
     MENÚ
====================================================== -->

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

<main>


    <section class="titulo">

        <div class="icono">
            🧾
        </div>


        <h2>
            Comprobante de pago
        </h2>


        <p>
            Sube la foto o captura de tu
            transferencia para que Roséa Boutique
            pueda verificarla.
        </p>

    </section>


    <section class="card">


        <div class="pedido-header">

            <div>

                <h3>
                    Pedido
                    #<?php
                    echo (int)$pedido['id_pedido'];
                    ?>
                </h3>


                <p>

                    <?php

                    echo htmlspecialchars(
                        $pedido['nombre'] .
                        ' ' .
                        $pedido['apellido']
                    );

                    ?>

                </p>

            </div>


            <div class="total">

                RD$<?php

                echo number_format(
                    (float)$pedido['total'],
                    2
                );

                ?>

            </div>

        </div>


        <!-- ESTADO -->

        <div
            class="estado-pago <?php
            echo $claseEstadoPago;
            ?>"
        >

            <?php

            echo htmlspecialchars(
                textoEstadoPago(
                    $estadoPago
                )
            );

            ?>

        </div>


        <!-- ERROR -->

        <?php if ($error !== ''): ?>

            <div class="error">

                ⚠ <?php
                echo htmlspecialchars(
                    $error
                );
                ?>

            </div>

        <?php endif; ?>


        <!-- INFORMACIÓN -->

        <div class="info">

            <p>

                <strong>
                    Método de pago:
                </strong>

                Transferencia bancaria

                <br>


                <strong>
                    Total:
                </strong>

                RD$<?php

                echo number_format(
                    (float)$pedido['total'],
                    2
                );

                ?>

                <br>


                <strong>
                    Pedido:
                </strong>

                #<?php
                echo (int)$pedido['id_pedido'];
                ?>

            </p>

        </div>


        <!-- COMPROBANTE YA SUBIDO -->

        <?php if (
            !empty(
                $pedido['comprobante_pago']
            )
        ): ?>

            <div class="comprobante-existente">

                ✓ Ya existe un comprobante asociado
                a este pedido.

                <br>

                Estado:
                <strong>

                    <?php

                    echo htmlspecialchars(
                        textoEstadoPago(
                            $estadoPago
                        )
                    );

                    ?>

                </strong>


                <?php if (
                    !empty(
                        $pedido[
                            'fecha_comprobante'
                        ]
                    )
                ): ?>

                    <br>

                    Enviado:

                    <strong>

                        <?php

                        echo date(
                            'd/m/Y h:i A',
                            strtotime(
                                $pedido[
                                    'fecha_comprobante'
                                ]
                            )
                        );

                        ?>

                    </strong>

                <?php endif; ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             SI YA ESTÁ VERIFICADO
        ================================================== -->

        <?php if (
            $estadoPago ===
            'verificado'
        ): ?>


            <div class="comprobante-existente">

                ✓ Tu pago ya fue verificado por
                Roséa Boutique.

                No necesitas enviar otro comprobante.

            </div>


            <a
                href="pedido_exitoso.php?id=<?php
                echo (int)$pedido['id_pedido'];
                ?>"
                class="btn btn-secundario"
            >
                Ver mi pedido ♡
            </a>


        <?php else: ?>


            <!-- =============================================
                 FORMULARIO
            ============================================== -->

            <div class="form-box">


                <h4>

                    <?php

                    echo !empty(
                        $pedido['comprobante_pago']
                    )
                        ? 'Cambiar comprobante'
                        : 'Subir comprobante';

                    ?>

                </h4>


                <p>

                    <?php if (
                        $estadoPago ===
                        'rechazado'
                    ): ?>

                        El comprobante anterior fue
                        rechazado. Puedes enviar una
                        nueva imagen para que vuelva
                        a ser revisada.

                    <?php else: ?>

                        Selecciona una imagen clara donde
                        se puedan visualizar los datos
                        de la transferencia.

                    <?php endif; ?>

                </p>


                <form
                    method="POST"
                    action="subir_comprobante.php?id=<?php
                    echo (int)$pedido['id_pedido'];
                    ?>"
                    enctype="multipart/form-data"
                >


                    <input
                        type="hidden"
                        name="id_pedido"
                        value="<?php
                        echo (int)$pedido['id_pedido'];
                        ?>"
                    >


                    <div class="archivo">

                        <input
                            type="file"
                            name="comprobante"
                            id="comprobante"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            required
                        >


                        <div class="ayuda">

                            Formatos permitidos:
                            JPG, JPEG, PNG y WEBP.

                            <br>

                            Tamaño máximo:
                            5 MB.

                        </div>

                    </div>


                    <!-- PREVISUALIZACIÓN -->

                    <div
                        class="preview"
                        id="preview"
                    >

                        <img
                            src=""
                            alt="Vista previa del comprobante"
                            id="previewImagen"
                        >

                    </div>


                    <button
                        type="submit"
                        class="btn btn-subir"
                    >

                        <?php

                        echo !empty(
                            $pedido[
                                'comprobante_pago'
                            ]
                        )
                            ? 'Actualizar comprobante ♡'
                            : 'Enviar comprobante ♡';

                        ?>

                    </button>


                </form>


                <a
                    href="pedido_exitoso.php?id=<?php
                    echo (int)$pedido['id_pedido'];
                    ?>"
                    class="btn btn-secundario"
                >
                    Subir más tarde
                </a>


            </div>


        <?php endif; ?>


    </section>


</main>


<!-- =====================================================
     FOOTER
====================================================== -->

<footer>

    <h3>
        Roséa Boutique 🌹
    </h3>

    <p>
        Elegancia, delicadeza y estilo
        en cada detalle ♡
    </p>

</footer>


<!-- =====================================================
     JAVASCRIPT
====================================================== -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const input =
            document.getElementById(
                'comprobante'
            );


        const preview =
            document.getElementById(
                'preview'
            );


        const imagen =
            document.getElementById(
                'previewImagen'
            );


        if (!input) {

            return;
        }


        input.addEventListener(
            'change',
            function () {

                if (
                    !this.files ||
                    !this.files[0]
                ) {

                    preview.classList.remove(
                        'mostrar'
                    );

                    imagen.src = '';

                    return;
                }


                const archivo =
                    this.files[0];


                /* =========================================
                   COMPROBAR TAMAÑO EN EL NAVEGADOR
                ========================================= */

                const maximo =
                    5 * 1024 * 1024;


                if (
                    archivo.size >
                    maximo
                ) {

                    alert(
                        'El comprobante no puede superar los 5 MB.'
                    );


                    this.value = '';


                    preview.classList.remove(
                        'mostrar'
                    );


                    imagen.src = '';


                    return;
                }


                /* =========================================
                   VISTA PREVIA
                ========================================= */

                if (
                    archivo.type.startsWith(
                        'image/'
                    )
                ) {

                    const lector =
                        new FileReader();


                    lector.onload =
                        function (evento) {

                            imagen.src =
                                evento.target.result;


                            preview.classList.add(
                                'mostrar'
                            );
                        };


                    lector.readAsDataURL(
                        archivo
                    );

                } else {

                    preview.classList.remove(
                        'mostrar'
                    );

                    imagen.src = '';
                }
            }
        );
    }
);

</script>


</body>

</html>