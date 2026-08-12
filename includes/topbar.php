<?php
if (!isset($nombreUsuario)) { $nombreUsuario = 'Juan Camilo Vanegas'; }
if (!isset($rolActivo))     { $rolActivo = 'admin'; }

$etiquetasRol = [
    'admin'      => 'Administrador',
    'instructor' => 'Instructor',
    'aprendiz'   => 'Aprendiz',
];
$etiquetaRol = $etiquetasRol[$rolActivo] ?? 'Usuario';
$iniciales = '';
foreach (explode(' ', $nombreUsuario) as $parte) {
    if ($parte !== '') $iniciales .= strtoupper($parte[0]);
    if (strlen($iniciales) >= 2) break;
}
?>
<header class="app-topbar">
    <div class="d-flex align-items-center gap-3">
        <!-- Toggle escritorio: colapsa a solo íconos -->
        <button class="sidebar-toggle-btn d-none d-lg-flex" id="sidebarToggle" type="button" title="Ocultar/mostrar menú">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>
        <!-- Toggle móvil: abre el drawer -->
        <button class="sidebar-toggle-btn d-lg-none" id="sidebarMobileToggle" type="button" title="Menú">
            <i class="bi bi-list"></i>
        </button>

        <div>
            <div class="fw-semibold" style="font-size:.95rem;"><?= htmlspecialchars($tituloPagina ?? '') ?></div>
            <div class="text-suave" style="font-size:.76rem;">SENA · Sistema de Control de Ingreso de Aprendices</div>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">

        <!-- Notificaciones -->
        <div class="dropdown">
            <button class="sidebar-toggle-btn position-relative" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-bell"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.55rem;">3</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end p-2" style="width:300px;">
                <li class="px-2 py-1 fw-semibold">Notificaciones</li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item small rounded-2" href="#"><i class="bi bi-file-earmark-medical text-sena me-2"></i>Nueva excusa médica pendiente</a></li>
                <li><a class="dropdown-item small rounded-2" href="#"><i class="bi bi-exclamation-triangle text-warning me-2"></i>3 retardos registrados hoy</a></li>
                <li><a class="dropdown-item small rounded-2" href="#"><i class="bi bi-bar-chart text-primary me-2"></i>Reporte semanal disponible</a></li>
            </ul>
        </div>

        <!-- Usuario -->
        <div class="dropdown">
            <button class="btn d-flex align-items-center gap-2 border rounded-3 py-1 px-2" type="button" data-bs-toggle="dropdown">
                <span class="avatar-circ"><?= htmlspecialchars($iniciales ?: 'US') ?></span>
                <span class="d-none d-md-block text-start">
                    <div class="fw-semibold" style="font-size:.82rem; line-height:1.1;"><?= htmlspecialchars($nombreUsuario) ?></div>
                    <div class="text-suave" style="font-size:.72rem;"><?= htmlspecialchars($etiquetaRol) ?></div>
                </span>
                <i class="bi bi-chevron-down text-suave" style="font-size:.7rem;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="perfil.php"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Configuración</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="index.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
            </ul>
        </div>
    </div>
</header>
