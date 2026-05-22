<?php
$host     = "localhost";
$dbname   = "gestion_biblioteca";
$usuario  = "biblioteca";
$password = "Profesor0";

$conexion = mysqli_connect($host, $usuario, $password, $dbname);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
?>