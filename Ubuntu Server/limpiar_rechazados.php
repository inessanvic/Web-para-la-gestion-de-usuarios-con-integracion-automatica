<?php
require '/var/www/html/conexion_bd.php';


$resultado = mysqli_query($conexion, "DELETE FROM usuarios_rechazados WHERE fecha_rechazo < NOW() - INTERVAL 30 MINUTE");

if ($resultado) {
    $borrados = mysqli_affected_rows($conexion);
    echo date('Y-m-d H:i:s') . " | Limpieza completada: $borrados registros eliminados.\n";
} else {
    echo date('Y-m-d H:i:s') . " | Error al limpiar: " . mysqli_error($conexion) . "\n";
}