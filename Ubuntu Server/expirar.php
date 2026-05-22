<?php
require 'conexion_bd.php';


$token_secreto = "tokenBiblioteca2026";
$token_recibido = $_SERVER['HTTP_X_API_TOKEN'] ?? '';

if ($token_recibido !== $token_secreto) {
    http_response_code(401);
    echo json_encode(['estado' => 'error', 'mensaje' => 'Token incorrecto']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['estado' => 'error', 'mensaje' => 'Metodo no permitido']);
    exit;
}

$datos = json_decode(file_get_contents('php://input'), true);

if (!$datos || !isset($datos['samaccount'])) {
    http_response_code(400);
    echo json_encode(['estado' => 'error', 'mensaje' => 'Faltan datos obligatorios']);
    exit;
}

$samaccount = mysqli_real_escape_string($conexion, $datos['samaccount']);
$nombre     = mysqli_real_escape_string($conexion, $datos['nombre'] ?? '');
$apellidos  = mysqli_real_escape_string($conexion, $datos['apellidos'] ?? '');
$email      = mysqli_real_escape_string($conexion, $datos['email'] ?? '');
$dni        = mysqli_real_escape_string($conexion, $datos['dni'] ?? '');
$fecha_registro = mysqli_real_escape_string($conexion, $datos['fecha_registro'] ?? date('Y-m-d H:i:s'));
$fecha_rechazo  = date('Y-m-d H:i:s');

$resultado = mysqli_query($conexion, "INSERT INTO usuarios_rechazados
    (nombre, apellidos, email, dni, samaccount, motivo, fecha_registro, fecha_rechazo, admin_id)
    VALUES ('$nombre', '$apellidos', '$email', '$dni', '$samaccount', 'Expirado', '$fecha_registro', '$fecha_rechazo', NULL)");

if (!$resultado) {
    http_response_code(500);
    echo json_encode(['estado' => 'error', 'mensaje' => 'Error al insertar en la base de datos']);
    exit;
}

mysqli_query($conexion, "UPDATE usuarios SET estado='rechazado', fecha_validacion='$fecha_rechazo' WHERE samaccount='$samaccount'");

echo json_encode(['estado' => 'ok', 'mensaje' => "Usuario '$samaccount' registrado como expirado"]);