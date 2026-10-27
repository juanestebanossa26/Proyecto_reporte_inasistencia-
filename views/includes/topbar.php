<?php
 //Nos aseguramos de que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//  Tomamos el nombre y el rol directamente de las variables de sesión
// 
$nombreUsuario = $_SESSION['nombre'] ?? 'Usuario Invitado';
$rolActivo     = $_SESSION['rol'] ?? 'invitado';

// 3. Diccionario para mostrar el nombre del rol "bonito" en pantalla.
// Lo pasamos a minúsculas para evitar errores si en la BD dice "Administrador" o "admin"
$etiquetasRol = [
    'administrador' => 'Administrador',
    'instructor'    => 'Instructor',
    'aprendiz'      => 'Aprendiz',
];

// Si encuentra el rol en el diccionario lo usa, si no, pone la primera letra en mayúscula del rol que traiga
$etiquetaRol = $etiquetasRol[strtolower($rolActivo)] ?? ucfirst($rolActivo);

// Lógica para extraer las iniciales (Tu código original, que está perfecto)
$iniciales = '';
foreach (explode(' ', $nombreUsuario) as $parte) {
    if ($parte !== '') $iniciales .= strtoupper($parte[0]);
    if (strlen($iniciales) >= 2) break;
}
?>
<header class="app-topbar">
    <div class="d-flex align-items-center gap-2 gap-sm-3 min-w-0" style="min-width: 0;">
        <!-- Toggle escritorio: colapsa a solo íconos -->
        <button class="sidebar-toggle-btn d-none d-lg-flex flex-shrink-0" id="sidebarToggle" type="button" title="Ocultar/mostrar menú" aria-label="Ocultar o mostrar menú lateral">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>
        <!-- Toggle móvil: abre el drawer -->
        <button class="sidebar-toggle-btn d-lg-none flex-shrink-0" id="sidebarMobileToggle" type="button" title="Menú" aria-label="Abrir menú de navegación móvil">
            <i class="bi bi-list"></i>
        </button>

        <div class="min-w-0" style="min-width: 0;">
            <div class="fw-semibold text-truncate topbar-title" title="<?= htmlspecialchars($tituloPagina ?? '') ?>"><?= htmlspecialchars($tituloPagina ?? '') ?></div>
            <div class="text-suave text-truncate d-none d-md-block" style="font-size:.76rem; line-height: 1.2;">SENA · Sistema de Control de Ingreso de Aprendices</div>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 flex-shrink-0">

        <!-- Usuario -->
        <div class="dropdown">
            <button class="btn d-flex align-items-center gap-2 border rounded-3 py-1 px-2" type="button" data-bs-toggle="dropdown" aria-label="Menú de perfil de usuario">
                
                <!-- Aquí se imprimen las iniciales -->
                <span class="avatar-circ"><?= htmlspecialchars($iniciales ?: 'US') ?></span>
                
                <span class="d-none d-md-block text-start">
                    <!-- Aquí se imprime el nombre completo -->
                    <div class="fw-semibold" style="font-size:.82rem; line-height:1.1;"><?= htmlspecialchars($nombreUsuario) ?></div>
                    
                    <!-- Aquí se imprime el rol -->
                    <div class="text-suave" style="font-size:.72rem;"><?= htmlspecialchars($etiquetaRol) ?></div>
                </span>
                <i class="bi bi-chevron-down text-suave" style="font-size:.7rem;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= htmlspecialchars(BASE_URL) ?>/index.php?accion=perfil"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
     
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= htmlspecialchars(BASE_URL) ?>/index.php?accion=logout"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
            </ul>
        </div>
    </div>
</header>