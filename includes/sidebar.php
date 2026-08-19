<?php


//  Nos aseguramos de que la sesión esté iniciada para poder leer los datos
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//  Tomamos el rol directamente de la variable de sesión que creaste en el login.
// Si no hay sesión, asignamos 'invitado'
$rolActivo = $_SESSION['rol'] ?? 'invitado'; 

if (!isset($paginaActiva)) { $paginaActiva = ''; }

function navLink($href, $icon, $label, $slug, $paginaActiva) {
    $active = $paginaActiva === $slug ? 'active' : '';
    echo '<li class="nav-item">';
    echo '<a class="nav-link ' . $active . '" href="' . $href . '" data-bs-toggle="tooltip" data-bs-placement="right" title="' . htmlspecialchars($label) . '">';
    echo '<i class="bi ' . $icon . '"></i><span>' . htmlspecialchars($label) . '</span>';
    echo '</a></li>';
}
?>
<aside class="app-sidebar">
    <div class="sidebar-brand">
        <div class="logo-box">SC</div>
        <div class="brand-text">
            <div class="fw-bold" style="font-size:.95rem;">Control Ingreso</div>
            <small>Aprendices · RFID</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">

           

            <!-- Validamos los roles convirtiendo el texto a minúsculas para evitar errores de tipeo en la BD -->
            <?php if (strtolower($rolActivo) === 'admin' || strtolower($rolActivo) === 'administrador'): ?>
                 <div class="sidebar-section-label">General</div>
            <?php navLink('dashboard.php', 'bi-grid-1x2-fill', 'Panel principal', 'dashboard', $paginaActiva); ?>
                <div class="sidebar-section-label">Gestión</div>
                <?php navLink('aprendices.php', 'bi-people-fill', 'Aprendices', 'aprendices', $paginaActiva); ?>
                <?php navLink('instructores.php', 'bi-person-badge-fill', 'Instructores', 'instructores', $paginaActiva); ?>
                <?php navLink('fichas.php', 'bi-journal-bookmark-fill', 'Fichas y horarios', 'fichas', $paginaActiva); ?>

                <div class="sidebar-section-label">Control</div>
                <?php navLink('marcaje.php', 'bi-wifi', 'Marcaje RFID', 'marcaje', $paginaActiva); ?>
                <?php navLink('validar_excusas.php', 'bi-file-earmark-medical-fill', 'Excusas médicas', 'validar_excusas', $paginaActiva); ?>

                <div class="sidebar-section-label">Reportes</div>
                <?php navLink('reportes.php', 'bi-bar-chart-line-fill', 'Reportes', 'reportes', $paginaActiva); ?>

            <?php elseif (strtolower($rolActivo) === 'instructor'): ?>
                 <div class="sidebar-section-label">General</div>
            <?php navLink('dashboard.php', 'bi-grid-1x2-fill', 'Panel principal', 'dashboard', $paginaActiva); ?>
                <div class="sidebar-section-label">Mi ficha</div>
                <?php navLink('fichas.php', 'bi-journal-bookmark-fill', 'Horarios de ficha', 'fichas', $paginaActiva); ?>
                <?php navLink('validar_excusas.php', 'bi-file-earmark-medical-fill', 'Validar excusas', 'validar_excusas', $paginaActiva); ?>
                <?php navLink('reportes.php', 'bi-bar-chart-line-fill', 'Reportes de mi ficha', 'reportes', $paginaActiva); ?>

            <?php elseif (strtolower($rolActivo) === 'aprendiz'): ?>
                <div class="sidebar-section-label">Mi asistencia</div>
            
                <?php navLink('historial.php', 'bi-clock-history', 'Mi historial', 'historial', $paginaActiva); ?>
                <?php navLink('excusas.php', 'bi-file-earmark-medical-fill', 'Anexar excusa médica', 'excusas', $paginaActiva); ?>
            <?php endif; ?>

            <div class="sidebar-section-label">Cuenta</div>
            <?php navLink('perfil.php', 'bi-person-circle', 'Mi perfil', 'perfil', $paginaActiva); ?>
            <?php navLink('../controllers/logout.php', 'bi-box-arrow-right', 'Cerrar sesión', 'salir', $paginaActiva); ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        SENA · ADSO<br>Sistema de Control RFID
    </div>
</aside>
<div class="sidebar-backdrop"></div>
