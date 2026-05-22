<?php
require 'conexion_bd.php';

$mensaje_exito = '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre    = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $dni       = strtoupper(trim($_POST['dni'] ?? ''));

    if (empty($nombre) || empty($apellidos) || empty($email) || empty($dni)) {
        $mensaje_error = "Por favor, rellena todos los campos.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje_error = "El formato del email no es válido.";

    } elseif (!preg_match('/^\d{8}[A-Za-z]$/', $dni)) {
        $mensaje_error = "El formato del DNI no es válido. Debe tener 8 números y una letra.";

    } else {
        $consulta = mysqli_query($conexion, "SELECT id FROM usuarios WHERE email='$email' OR dni='$dni'");

        if (mysqli_num_rows($consulta) > 0) {
            $mensaje_error = "Ya existe un usuario registrado con ese email o DNI.";
        } else {
            $datos = json_encode([
                'nombre'    => $nombre,
                'apellidos' => $apellidos,
                'email'     => $email,
                'dni'       => $dni
            ]);

            $curl = curl_init('http://192.168.1.120:8080/crear');
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $datos);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 10);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-API-Token: tokenBiblioteca2026'
            ]);

            $respuesta_raw = curl_exec($curl);
            $error_curl    = curl_error($curl);
            curl_close($curl);

            if ($error_curl) {
                $mensaje_error = "No se pudo conectar con el servidor. Inténtalo más tarde.";
            } else {
                $respuesta = json_decode($respuesta_raw, true);

                if ($respuesta && $respuesta['estado'] === 'ok') {
                    preg_match("/'([^']+)'/", $respuesta['mensaje'], $matches);
                    $samaccount = $matches[1] ?? '';

                    mysqli_query($conexion, "INSERT INTO usuarios (nombre, apellidos, email, dni, samaccount, estado)
                        VALUES ('$nombre', '$apellidos', '$email', '$dni', '$samaccount', 'pendiente')");

                    $mensaje_exito = "¡Registro completado! Tu solicitud está pendiente de validación.";
                } else {
                    $mensaje_error = "Error al crear el usuario: " . ($respuesta['mensaje'] ?? 'Error desconocido');
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro — Biblioteca "El Saber"</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

<div class="pagina-auth">
    <div class="card-auth" style="max-width: 480px;">
        <div class="logo-auth">
            <span>📚</span>
            <h1>Registro de socio</h1>
            <p>Rellena el formulario para solicitar tu acceso</p>
        </div>

        <?php if ($mensaje_exito): ?>
            <div class="mensaje-exito"><?= htmlspecialchars($mensaje_exito) ?></div>
            <p style="text-align:center; margin-top:20px;">
                <a href="inicio_biblioteca.php" style="color: var(--verde-oscuro); font-size:0.9rem;">← Volver al inicio</a>
            </p>
        <?php else: ?>

            <?php if ($mensaje_error): ?>
                <div class="mensaje-error"><?= htmlspecialchars($mensaje_error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="campo-grupo">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" placeholder="Tu nombre" required
                           value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                </div>
                <div class="campo-grupo">
                    <label for="apellidos">Apellidos</label>
                    <input type="text" id="apellidos" name="apellidos" placeholder="Tus apellidos" required
                           value="<?= htmlspecialchars($_POST['apellidos'] ?? '') ?>">
                </div>
                <div class="campo-grupo">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="tucorreo@ejemplo.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="campo-grupo">
                    <label for="dni">DNI</label>
                    <input type="text" id="dni" name="dni" placeholder="12345678A" maxlength="9" required
                           value="<?= htmlspecialchars($_POST['dni'] ?? '') ?>">
                </div>
                <button type="submit" class="btn-enviar">Enviar solicitud</button>
            </form>

            <p style="text-align:center; margin-top:20px; font-size:0.85rem; color: var(--gris-texto);">
                <a href="inicio_biblioteca.php" style="color: var(--verde-oscuro);">← Volver al inicio</a>
            </p>

        <?php endif; ?>
    </div>
</div>

</body>
</html>