<?php
session_start();
require 'conexion_bd.php';

// Si no está logueado redirige al login
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_id      = $_SESSION['admin_id'];
$admin_usuario = $_SESSION['admin_usuario'];
$mensaje_exito = '';
$mensaje_error = '';

// Funcion para llamar a la API
function llamar_api($endpoint, $metodo, $datos = null) {
    $url  = 'http://192.168.1.120:8080' . $endpoint;
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Token: tokenBiblioteca2026'
    ]);
    if ($metodo === 'POST') {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($datos));
    }
    $respuesta_raw = curl_exec($curl);
    $error_curl    = curl_error($curl);
    curl_close($curl);
    if ($error_curl) return null;
    return json_decode($respuesta_raw, true);
}

// --- Procesa acciones del panel ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion     = $_POST['accion'] ?? '';
    $usuario_id = (int)($_POST['usuario_id'] ?? 0);
    $samaccount = trim($_POST['samaccount'] ?? '');

    // --- Validar usuario ---
    if ($accion === 'validar') {
        $respuesta = llamar_api('/validar', 'POST', ['samaccount' => $samaccount]);
        if (!$respuesta) {
            $mensaje_error = "No se pudo conectar con el servidor Windows.";
        } elseif ($respuesta['estado'] === 'ok') {
            $fecha = date('Y-m-d H:i:s');
            mysqli_query($conexion, "UPDATE usuarios
                SET estado='validado', fecha_validacion='$fecha', admin_id='$admin_id'
                WHERE id='$usuario_id'");
            $mensaje_exito = "Usuario '$samaccount' validado correctamente.";
        } else {
            $mensaje_error = "Error en la API: " . $respuesta['mensaje'];
        }
    }

    // --- Rechazar usuario ---
    elseif ($accion === 'rechazar') {
        // Obtiene los datos del usuario antes de borrarlo
        $res = mysqli_query($conexion, "SELECT * FROM usuarios WHERE id='$usuario_id'");
        $u   = mysqli_fetch_assoc($res);

        $respuesta = llamar_api('/borrar', 'POST', ['samaccount' => $samaccount]);
        if (!$respuesta) {
            $mensaje_error = "No se pudo conectar con el servidor Windows.";
        } elseif ($respuesta['estado'] === 'ok') {
            $fecha = date('Y-m-d H:i:s');

            // Mueve el usuario a la tabla de rechazados
            mysqli_query($conexion, "INSERT INTO usuarios_rechazados
                (nombre, apellidos, email, dni, samaccount, motivo, fecha_registro, fecha_rechazo, admin_id)
                VALUES (
                    '{$u['nombre']}', '{$u['apellidos']}', '{$u['email']}', '{$u['dni']}',
                    '{$u['samaccount']}', 'Rechazado', '{$u['fecha_registro']}', '$fecha', '$admin_id'
                )");

            // Actualiza el estado en la tabla usuarios
            mysqli_query($conexion, "UPDATE usuarios
                SET estado='rechazado', fecha_validacion='$fecha', admin_id='$admin_id'
                WHERE id='$usuario_id'");

            $mensaje_exito = "Usuario '$samaccount' rechazado y movido al registro de rechazados.";
        } else {
            $mensaje_error = "Error en la API: " . $respuesta['mensaje'];
        }
    }

    // --- Eliminar usuario del dominio (desde la lista de usuarios del dominio) ---
    elseif ($accion === 'eliminar_dominio') {
        $respuesta = llamar_api('/borrar', 'POST', ['samaccount' => $samaccount]);
        if (!$respuesta) {
            $mensaje_error = "No se pudo conectar con el servidor Windows.";
        } elseif ($respuesta['estado'] === 'ok') {
            // Si existe en la tabla usuarios lo marca como rechazado también
            $fecha = date('Y-m-d H:i:s');
            $res   = mysqli_query($conexion, "SELECT * FROM usuarios WHERE samaccount='$samaccount'");
            if (mysqli_num_rows($res) > 0) {
                $u = mysqli_fetch_assoc($res);
                mysqli_query($conexion, "INSERT INTO usuarios_rechazados
                    (nombre, apellidos, email, dni, samaccount, motivo, fecha_registro, fecha_rechazo, admin_id)
                    VALUES (
                        '{$u['nombre']}', '{$u['apellidos']}', '{$u['email']}', '{$u['dni']}',
                        '{$u['samaccount']}', 'Rechazado', '{$u['fecha_registro']}', '$fecha', '$admin_id'
                    )");
                mysqli_query($conexion, "UPDATE usuarios SET estado='rechazado', fecha_validacion='$fecha', admin_id='$admin_id' WHERE samaccount='$samaccount'");
            }
            $mensaje_exito = "Usuario '$samaccount' eliminado del dominio correctamente.";
        } else {
            $mensaje_error = "Error en la API: " . $respuesta['mensaje'];
        }
    }
}

// Obtiene usuarios pendientes de la base de datos
$pendientes = mysqli_query($conexion, "SELECT * FROM usuarios WHERE estado='pendiente' ORDER BY fecha_registro ASC");

// Obtiene historial de validados y rechazados
$historial = mysqli_query($conexion, "SELECT u.*, a.usuario AS admin_nombre
    FROM usuarios u
    LEFT JOIN administradores a ON u.admin_id = a.id
    WHERE u.estado != 'pendiente'
    ORDER BY u.fecha_validacion DESC
    LIMIT 20");

// Obtiene usuarios rechazados de la tabla usuarios_rechazados
$rechazados = mysqli_query($conexion, "SELECT ur.*, a.usuario AS admin_nombre
    FROM usuarios_rechazados ur
    LEFT JOIN administradores a ON ur.admin_id = a.id
    ORDER BY ur.fecha_rechazo DESC");

// Obtiene todos los usuarios del dominio llamando a la API
$usuarios_dominio  = [];
$curl = curl_init('http://192.168.1.120:8080/listar');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_TIMEOUT, 10);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'X-API-Token: tokenBiblioteca2026'
]);
$respuesta_raw = curl_exec($curl);
curl_close($curl);
if ($respuesta_raw) {
    $decoded = json_decode($respuesta_raw, true);
    // Si solo hay un usuario la API devuelve un objeto en vez de array, lo normalizamos
    if (isset($decoded['samaccount'])) {
        $usuarios_dominio = [$decoded];
    } else {
        $usuarios_dominio = $decoded ?? [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de administración — Biblioteca "El Saber"</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

<div class="pagina-panel">

    <!-- CABECERA -->
    <div class="panel-header">
        <div class="titulo-panel">📚 Panel de administración — Biblioteca "El Saber"</div>
        <div style="display:flex; align-items:center; gap:16px;">
            <span style="color:rgba(255,255,255,0.6); font-size:0.85rem;">Conectado como <strong style="color:var(--dorado);"><?= htmlspecialchars($admin_usuario) ?></strong></span>
            <a href="logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </div>

    <div class="panel-contenido">

        <?php if ($mensaje_exito): ?>
            <div class="mensaje-exito"><?= htmlspecialchars($mensaje_exito) ?></div>
        <?php endif; ?>
        <?php if ($mensaje_error): ?>
            <div class="mensaje-error"><?= htmlspecialchars($mensaje_error) ?></div>
        <?php endif; ?>


        <!-- SOLICITUDES PENDIENTES -->
        <h2 class="panel-titulo">Solicitudes pendientes</h2>
        <p class="panel-subtitulo">Usuarios registrados esperando validación. Se eliminan automáticamente tras 24 horas.</p>

        <div class="tabla-contenedor" style="margin-bottom:50px;">
            <?php if (mysqli_num_rows($pendientes) === 0): ?>
                <div class="sin-usuarios">No hay solicitudes pendientes en este momento.</div>
            <?php else: ?>
                <table class="tabla-usuarios">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>DNI</th>
                            <th>Usuario AD</th>
                            <th>Fecha registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($u = mysqli_fetch_assoc($pendientes)): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['dni']) ?></td>
                            <td><code><?= htmlspecialchars($u['samaccount']) ?></code></td>
                            <td><?= $u['fecha_registro'] ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="accion" value="validar">
                                    <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="samaccount" value="<?= htmlspecialchars($u['samaccount']) ?>">
                                    <button type="submit" class="btn-validar"
                                        onclick="return confirm('¿Validar a <?= htmlspecialchars($u['nombre']) ?>?')">Validar</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="accion" value="rechazar">
                                    <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="samaccount" value="<?= htmlspecialchars($u['samaccount']) ?>">
                                    <button type="submit" class="btn-rechazar"
                                        onclick="return confirm('¿Rechazar a <?= htmlspecialchars($u['nombre']) ?>?')">Rechazar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>


        <!-- USUARIOS DEL DOMINIO -->
        <h2 class="panel-titulo">Usuarios del dominio</h2>
        <p class="panel-subtitulo">Todos los usuarios activos en Active Directory.</p>

        <div class="tabla-contenedor" style="margin-bottom:50px;">
            <?php if (empty($usuarios_dominio)): ?>
                <div class="sin-usuarios">No se pudieron obtener los usuarios del dominio o no hay ninguno.</div>
            <?php else: ?>
                <table class="tabla-usuarios">
                    <thead>
                        <tr>
                            <th>Usuario AD</th>
                            <th>Nombre</th>
                            <th>Apellidos</th>
                            <th>Email</th>
                            <th>Estado cuenta</th>
                            <th>OU</th>
                            <th>Fecha creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios_dominio as $u): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($u['samaccount'] ?? '') ?></code></td>
                            <td><?= htmlspecialchars($u['nombre'] ?? '') ?></td>
                            <td><?= htmlspecialchars($u['apellidos'] ?? '') ?></td>
                            <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                            <td>
                                <?php if ($u['habilitado']): ?>
                                    <span class="badge-validado">Habilitada</span>
                                <?php else: ?>
                                    <span class="badge-pendiente">Deshabilitada</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($u['ou'] ?? '') ?></td>
                            <td><?= htmlspecialchars($u['fecha'] ?? '') ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="accion" value="eliminar_dominio">
                                    <input type="hidden" name="samaccount" value="<?= htmlspecialchars($u['samaccount'] ?? '') ?>">
                                    <button type="submit" class="btn-rechazar"
                                        onclick="return confirm('¿Eliminar a <?= htmlspecialchars($u['samaccount'] ?? '') ?> del dominio?')">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>


        <!-- HISTORIAL DE SOLICITUDES -->
        <h2 class="panel-titulo">Historial de solicitudes</h2>
        <p class="panel-subtitulo">Últimas 20 solicitudes gestionadas.</p>

        <div class="tabla-contenedor" style="margin-bottom:50px;">
            <?php if (mysqli_num_rows($historial) === 0): ?>
                <div class="sin-usuarios">No hay solicitudes gestionadas todavía.</div>
            <?php else: ?>
                <table class="tabla-usuarios">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Usuario AD</th>
                            <th>Estado</th>
                            <th>Fecha gestión</th>
                            <th>Gestionado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($u = mysqli_fetch_assoc($historial)): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><code><?= htmlspecialchars($u['samaccount']) ?></code></td>
                            <td>
                                <?php if ($u['estado'] === 'validado'): ?>
                                    <span class="badge-validado">Validado</span>
                                <?php else: ?>
                                    <span class="badge-rechazado">Rechazado</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $u['fecha_validacion'] ?></td>
                            <td><?= htmlspecialchars($u['admin_nombre'] ?? '—') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>


        <!-- USUARIOS RECHAZADOS -->
        <h2 class="panel-titulo">Registro de rechazados</h2>
        <p class="panel-subtitulo">Usuarios rechazados manualmente o eliminados por expiración. Se borran automáticamente cada 2 meses.</p>

        <div class="tabla-contenedor">
            <?php if (mysqli_num_rows($rechazados) === 0): ?>
                <div class="sin-usuarios">No hay usuarios en el registro de rechazados.</div>
            <?php else: ?>
                <table class="tabla-usuarios">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>DNI</th>
                            <th>Usuario AD</th>
                            <th>Motivo</th>
                            <th>Fecha rechazo</th>
                            <th>Gestionado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($u = mysqli_fetch_assoc($rechazados)): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['dni']) ?></td>
                            <td><code><?= htmlspecialchars($u['samaccount']) ?></code></td>
                            <td>
                                <?php if ($u['motivo'] === 'Rechazado'): ?>
                                    <span class="badge-rechazado">Rechazado</span>
                                <?php else: ?>
                                    <span class="badge-pendiente">Expirado</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $u['fecha_rechazo'] ?></td>
                            <td><?= htmlspecialchars($u['admin_nombre'] ?? 'Automático') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>