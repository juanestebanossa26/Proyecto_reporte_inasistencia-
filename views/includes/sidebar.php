<?php


//  Nos aseguramos de que la sesión esté iniciada para poder leer los datos
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//  Tomamos el rol directamente de la variable de sesión que creaste en el login.
// Si no hay sesión, asignamos 'invitado'
$rolActivo = $_SESSION['rol'] ?? 'invitado'; 

if (!isset($paginaActiva)) { $paginaActiva = ''; }

if (!function_exists('navLink')) {
    function navLink($href, $icon, $label, $slug, $paginaActiva) {
        $active = $paginaActiva === $slug ? 'active' : '';
        echo '<li class="nav-item">';
        echo '<a class="nav-link ' . htmlspecialchars($active) . '" href="' . htmlspecialchars($href) . '" data-bs-toggle="tooltip" data-bs-placement="right" title="' . htmlspecialchars($label) . '">';
        echo '<i class="bi ' . htmlspecialchars($icon) . '"></i><span>' . htmlspecialchars($label) . '</span>';
        echo '</a></li>';
    }
}
?>
<aside class="app-sidebar">
    <div class="sidebar-brand">
        <div class="logo-box">
            <img src="<?= BASE_URL ?>/assets/img/logo-sena-blanco.svg" alt="SENA" width="22" height="22" style="width:22px;height:22px;object-fit:contain;">
        </div>
        <div class="brand-text">
            <div class="fw-bold" style="font-size:.95rem;">Control Ingreso</div>
            <small>Aprendices · RFID</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">

           

            <!-- Validamos los roles convirtiendo el texto a minúsculas para evitar errores de tipeo en la BD -->
            <?php if (strtolower($rolActivo) === 'administrador'): ?>
                 <div class="sidebar-section-label">General</div>
            <?php navLink(BASE_URL . '/index.php?accion=dashboard', 'bi-grid-1x2-fill', 'Panel principal', 'dashboard', $paginaActiva); ?>
                <div class="sidebar-section-label">Gestión</div>
                <?php navLink(BASE_URL . '/index.php?accion=aprendices', 'bi-people-fill', 'Aprendices', 'aprendices', $paginaActiva); ?>
                <?php navLink(BASE_URL . '/index.php?accion=instructores', 'bi-person-badge-fill', 'Instructores', 'instructores', $paginaActiva); ?>
                <?php navLink(BASE_URL . '/index.php?accion=fichas', 'bi-journal-bookmark-fill', 'Fichas', 'fichas', $paginaActiva); ?>
                <?php navLink(BASE_URL . '/index.php?accion=horarios', 'bi-calendar3', 'Horarios', 'horarios', $paginaActiva); ?>

                <div class="sidebar-section-label">Reportes</div>
                <?php navLink(BASE_URL . '/index.php?accion=reportes', 'bi-bar-chart-line-fill', 'Reportes', 'reportes', $paginaActiva); ?>

            <?php elseif (strtolower($rolActivo) === 'instructor'): ?>
                 <div class="sidebar-section-label">General</div>
            <?php navLink(BASE_URL . '/index.php?accion=dashboard', 'bi-grid-1x2-fill', 'Panel principal', 'dashboard', $paginaActiva); ?>
                <div class="sidebar-section-label">Académico</div>
                <?php navLink(BASE_URL . '/index.php?accion=marcaje', 'bi-wifi', 'Marcaje RFID', 'marcaje', $paginaActiva); ?>
                <?php navLink(BASE_URL . '/index.php?accion=fichas', 'bi-journal-bookmark-fill', 'Fichas', 'fichas', $paginaActiva); ?>
                <?php navLink(BASE_URL . '/index.php?accion=horarios', 'bi-calendar3', 'Horarios', 'horarios', $paginaActiva); ?>
                <?php navLink(BASE_URL . '/index.php?accion=validarExcusas', 'bi-file-earmark-medical-fill', 'Validar excusas', 'validar_excusas', $paginaActiva); ?>
                <?php navLink(BASE_URL . '/index.php?accion=reportes', 'bi-bar-chart-line-fill', 'Reportes de mi ficha', 'reportes', $paginaActiva); ?>

            <?php elseif (strtolower($rolActivo) === 'aprendiz'): ?>
                <div class="sidebar-section-label">Mi asistencia</div>
            
                <?php navLink(BASE_URL . '/index.php?accion=historial', 'bi-clock-history', 'Mi historial', 'historial', $paginaActiva); ?>
                <?php navLink(BASE_URL . '/index.php?accion=excusas', 'bi-file-earmark-medical-fill', 'Anexar excusa médica', 'excusas', $paginaActiva); ?>
            <?php endif; ?>

            <div class="sidebar-section-label">Cuenta</div>
            <?php navLink(BASE_URL . '/index.php?accion=perfil', 'bi-person-circle', 'Mi perfil', 'perfil', $paginaActiva); ?>
            <?php navLink(BASE_URL . '/index.php?accion=logout', 'bi-box-arrow-right', 'Cerrar sesión', 'salir', $paginaActiva); ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        SENA · ADSO<br>Sistema de Control RFID
    </div>
</aside>
<div class="sidebar-backdrop"></div>
