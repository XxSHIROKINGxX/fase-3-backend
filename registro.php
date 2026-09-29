<?php

require_once "config/conexion.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = trim($_POST["nombre"]);
    $apellido = trim($_POST["apellido"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (
        empty($nombre) ||
        empty($apellido) ||
        empty($email) ||
        empty($password)
    ) {
        $mensaje = "Todos los campos son obligatorios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "El correo electrónico no es válido.";
    } else {

        $consulta = $conexion->prepare(
            "SELECT id_usuario FROM usuarios WHERE email = ?"
        );

        $consulta->execute([$email]);

        if ($consulta->fetch()) {

            $mensaje = "Este correo ya está registrado.";

        } else {

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $consulta = $conexion->prepare(
                "INSERT INTO usuarios
                (nombre, apellido, email, password)
                VALUES (?, ?, ?, ?)"
            );

            $consulta->execute([
                $nombre,
                $apellido,
                $email,
                $passwordHash
            ]);

            $mensaje = "Usuario registrado correctamente.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Registro | Roséa Boutique</title>
</head>

<body>

    <h1>🌹 Roséa Boutique</h1>

    <h2>Crear cuenta</h2>

    <?php if (!empty($mensaje)): ?>
        <p><?php echo htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>

    <form method="POST">

        <label>Nombre</label><br>
        <input type="text" name="nombre" required>

        <br><br>

        <label>Apellido</label><br>
        <input type="text" name="apellido" required>

        <br><br>

        <label>Correo electrónico</label><br>
        <input type="email" name="email" required>

        <br><br>

        <label>Contraseña</label><br>
        <input type="password" name="password" required>

        <br><br>

        <button type="submit">
            Registrarme
        </button>

    </form>

    <p>
        ¿Ya tienes una cuenta?
        <a href="login.php">Iniciar sesión</a>
    </p>

</body>

</html>