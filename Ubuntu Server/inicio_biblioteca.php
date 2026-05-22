<?php require 'conexion_bd.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca Pública "El Saber"</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

<!-- NAVEGACIÓN -->
<nav>
    <a href="#inicio" class="nav-logo">
        <img src="fotos/logo.png" alt="Logo Biblioteca El Saber" style="height: 40px;">
        <span>Biblioteca "El Saber"</span>
    </a>
    <ul class="nav-links">
        <li><a href="#inicio">Inicio</a></li>
        <li><a href="#sobre-nosotros">Sobre nosotros</a></li>
        <li><a href="#servicios">Servicios</a></li>
        <li><a href="#horarios">Horarios</a></li>
        <li><a href="registro.php">Registro</a></li>
    </ul>
</nav>

<!-- HERO -->
<section class="hero" id="inicio">
    <div class="hero-contenido">
        <div class="hero-tag">📖 Bienvenido a tu biblioteca</div>
        <h1>Un lugar donde el<br><em>conocimiento</em> te espera</h1>
        <p>Accede a miles de libros, recursos digitales y espacios de estudio en el corazón de la ciudad.</p>
        <a href="registro.php" class="btn-primario">Regístrate gratis</a>
        <a href="#servicios" class="btn-secundario">Ver servicios</a>
    </div>
</section>

<!-- SOBRE NOSOTROS -->
<section class="sobre-nosotros" id="sobre-nosotros">
    <div class="sobre-texto">
        <p class="seccion-etiqueta">Sobre nosotros</p>
        <h2 class="seccion-titulo">Más de 30 años al servicio de la cultura</h2>
        <p>La Biblioteca Pública "El Saber" nació con el objetivo de acercar el conocimiento a todos los ciudadanos, sin importar su edad o condición. Somos un espacio inclusivo, moderno y en constante evolución.</p>
        <p>Contamos con un equipo de profesionales dedicados a ayudarte a encontrar lo que necesitas, ya sea para estudiar, investigar o simplemente disfrutar de la lectura.</p>
    </div>
    <div class="sobre-stats">
        <div class="stat-card">
            <div class="stat-numero">45.000</div>
            <div class="stat-label">Libros disponibles</div>
        </div>
        <div class="stat-card">
            <div class="stat-numero">8.500</div>
            <div class="stat-label">Socios registrados</div>
        </div>
        <div class="stat-card">
            <div class="stat-numero">30+</div>
            <div class="stat-label">Años de historia</div>
        </div>
        <div class="stat-card">
            <div class="stat-numero">12</div>
            <div class="stat-label">Ordenadores públicos</div>
        </div>
    </div>
</section>

<!-- SERVICIOS -->
<section class="servicios" id="servicios">
    <p class="seccion-etiqueta">Lo que ofrecemos</p>
    <h2 class="seccion-titulo">Nuestros servicios</h2>
    <p class="seccion-subtitulo">Todo lo que necesitas para aprender, investigar y disfrutar de la lectura en un solo lugar.</p>
    <div class="servicios-grid">
        <div class="servicio-card">
            <div class="servicio-icono">📖</div>
            <h3>Préstamo de libros</h3>
            <p>Accede a nuestra colección de más de 45.000 títulos. Llévate hasta 5 libros durante 21 días.</p>
        </div>
        <div class="servicio-card">
            <div class="servicio-icono">💻</div>
            <h3>Acceso a internet</h3>
            <p>Disponemos de 12 ordenadores con acceso a internet de alta velocidad para todos los socios registrados.</p>
        </div>
        <div class="servicio-card">
            <div class="servicio-icono">📚</div>
            <h3>Sala de estudio</h3>
            <p>Espacios tranquilos y bien iluminados para que puedas concentrarte en tu trabajo o estudio.</p>
        </div>
        <div class="servicio-card">
            <div class="servicio-icono">🎧</div>
            <h3>Recursos digitales</h3>
            <p>Accede a audiolibros, revistas digitales y bases de datos académicas con tu carnet de socio.</p>
        </div>
        <div class="servicio-card">
            <div class="servicio-icono">👶</div>
            <h3>Zona infantil</h3>
            <p>Un espacio dedicado a los más pequeños con cuentos, actividades y talleres de fomento lector.</p>
        </div>
        <div class="servicio-card">
            <div class="servicio-icono">🎓</div>
            <h3>Talleres y eventos</h3>
            <p>Organizamos actividades culturales, presentaciones de libros y talleres formativos durante todo el año.</p>
        </div>
    </div>
</section>

<!-- HORARIOS -->
<section class="horarios" id="horarios">
    <p class="seccion-etiqueta">Cuándo visitarnos</p>
    <h2 class="seccion-titulo">Horario de apertura</h2>
    <br>
    <div class="horarios-grid">
        <div class="horario-card">
            <div class="horario-dia">Lunes — Viernes</div>
            <div class="horario-hora">09:00 — 21:00</div>
        </div>
        <div class="horario-card">
            <div class="horario-dia">Sábados</div>
            <div class="horario-hora">09:00 — 14:00</div>
        </div>
        <div class="horario-card">
            <div class="horario-dia">Domingos</div>
            <div class="horario-hora">Cerrado</div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-grid">
        <div class="footer-marca">
            <div class="logo-footer">
                <img src="fotos/logo.png" alt="Logo" style="height: 28px; vertical-align: middle;"> Biblioteca "El Saber"
            </div>
            <p>Un espacio dedicado al conocimiento, la cultura y el aprendizaje. Abierto a toda la comunidad desde 1994.</p>
        </div>
        <div class="footer-col">
            <h4>Navegación</h4>
            <ul>
                <li><a href="#inicio">Inicio</a></li>
                <li><a href="#sobre-nosotros">Sobre nosotros</a></li>
                <li><a href="#servicios">Servicios</a></li>
                <li><a href="#horarios">Horarios</a></li>
                <li><a href="registro.php">Registro</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Contacto</h4>
            <ul>
                <li>Calle del Saber, 12</li>
                <li>37001 - Ciudad</li>
                <li>biblioteca@elsaber.es</li>
                <li>923 000 000</li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <span>© 2026 Biblioteca Pública "El Saber". Todos los derechos reservados.</span>
        <a href="login.php">Acceso administradores</a>
    </div>
</footer>

</body>
</html>