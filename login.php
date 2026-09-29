<?php
session_start();

require_once "config/conexion.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $sql = "SELECT * FROM usuarios WHERE email = :email LIMIT 1";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        ":email" => $email
    ]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($password, $usuario["password"])) {

        // Regeneramos el ID de sesión después del login
        session_regenerate_id(true);

        $_SESSION["id_usuario"] = $usuario["id_usuario"];
        $_SESSION["nombre"] = $usuario["nombre"];
        $_SESSION["apellido"] = $usuario["apellido"];
        $_SESSION["email"] = $usuario["email"];
        $_SESSION["rol"] = $usuario["rol"];

        header("Location: perfil.php");
        exit;

    } else {

        $mensaje = "Correo electrónico o contraseña incorrectos.";

    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Iniciar sesión | Roséa Boutique</title>
</head>

<body>

    <h1>🌹 Roséa Boutique</h1>

    <h2>Iniciar sesión</h2>

    <?php if (!empty($mensaje)): ?>

        <p>
            <?php echo htmlspecialchars($mensaje); ?>
        </p>

    <?php endif; ?>

    <form method="POST" action="">

        <label for="email">
            Correo electrónico
        </label>

        <br>

        <input
            type="email"
            id="email"
            name="email"
            required
        >

        <br><br>

        <label for="password">
            Contraseña
        </label>

        <br>

        <input
            type="password"
            id="password"
            name="password"
            required
        >

        <br><br>

        <button type="submit">
            Iniciar sesión
        </button>

    </form>

    <p>
        ¿No tienes una cuenta?
        <a href="registro.php">Crear cuenta</a>
    </p>

</body>

</html>