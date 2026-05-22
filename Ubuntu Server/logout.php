<?php
session_start();

// Elimina todos los datos de la sesión
session_destroy();

// Redirige al login
header('Location: login.php');
exit;
?>