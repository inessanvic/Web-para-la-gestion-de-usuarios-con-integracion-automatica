<?php
session_start();
require 'conexion_bd.php';

// Si ya está logueado redirige directamente al panel
if (isset($_SESSION['admin_id'])) {
    header('Location: panel_admin.php');
    exit;
}

$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $usuario  = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($usuario) || empty($password)) {
        $mensaje_error = "Por favor, rellena todos los campos.";
    } else {
        // Busca el administrador en la base de datos por nombre de usuario
        $resultado = mysqli_query($conexion, "SELECT id, usuario, password FROM administradores WHERE usuario='$usuario'");
        $admin     = mysqli_fetch_assoc($resultado);

        // Compara la contraseña introducida con la guardada en la base de datos
        if ($admin && $password === $admin['password']) {
            // Guarda el id y el nombre del administrador en la sesión
            $_SESSION['admin_id']      = $admin['id'];
            $_SESSION['admin_usuario'] = $admin['usuario'];
            header('Location: panel_admin.php');
            exit;
        } else {
            $mensaje_error = "Usuario o contraseña incorrectos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso administradores — Biblioteca "El Saber"</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

<div class="pagina-auth">
    <div class="card-auth">
        <div class="logo-auth">
            <img src="fotos/logo.png" alt="Logo" style="height: 55px; margin-bottom: 10px;">
            <h1>Acceso administradores</h1>
            <p>Introduce tus credenciales para acceder al panel</p>
        </div>

        <?php if ($mensaje_error): ?>
            <div class="mensaje-error"><?= htmlspecialchars($mensaje_error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="campo-grupo">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" placeholder="Nombre de usuario" required
                       value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
            </div>
            <div class="campo-grupo">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" placeholder="Contraseña" required>
            </div>
            <button type="submit" class="btn-enviar">Entrar</button>
        </form>

        <p style="text-align:center; margin-top:20px; font-size:0.85rem;">
            <a href="inicio_biblioteca.php" style="color: var(--verde-oscuro);">← Volver al inicio</a>
        </p>
    </div>
</div>

</body>
</html>