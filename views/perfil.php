<?php
// vistas/perfil.php
// Servido por AuthControlador::mostrarPerfil() desde index.php?accion=perfil

$usuarioActual = $usuarioActual ?? [];
$stats         = $stats ?? [];

$rolActivo      = $usuarioActual['nombre_role'] ?? $_SESSION['rol'] ?? 'Aprendiz';
$nombreUsuario  = $usuarioActual['nombre_completo'] ?? $_SESSION['nombre'] ?? 'Usuario';
$usuarioLogin   = $usuarioActual['usuario'] ?? $_SESSION['usuario'] ?? $_SESSION['Documento'] ?? 'No registrado';
$correoUsuario  = $usuarioActual['correo_electronico'] ?? $_SESSION['Correo'] ?? 'Sin correo';
$areaUsuario    = $usuarioActual['area'] ?? 'General';
if (strpos($areaUsuario, '-') !== false || strpos($areaUsuario, '–') !== false || strpos($areaUsuario, '—') !== false) {
    $partesArea = preg_split('/[-–—]/u', $areaUsuario);
    $areaUsuario = trim($partesArea[0] ?? '');
}
$estadoUsuario  = $usuarioActual['estado'] ?? 'Activo';

$paginaActiva   = 'perfil';
$tituloPagina   = 'Mi Perfil & Seguridad';

$iniciales = '';
foreach (explode(' ', trim($nombreUsuario)) as $p) {
    if ($p !== '') $iniciales .= strtoupper($p[0]);
    if (strlen($iniciales) >= 2) break;
}
if ($iniciales === '') $iniciales = 'US';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include 'includes/head.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/perfil.css">
</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>
        <main class="app-content">
            <!-- Encabezado de página -->
            <div class="page-header mb-3">
                <div class="eyebrow">Mi Cuenta</div>
                <h1 class="h4 fw-bold mb-0">Mi Perfil & Seguridad</h1>
                <p class="text-suave mb-0">Administra tus datos personales y actualiza tu contraseña de acceso.</p>
            </div>

            <!-- Banner / Hero de Perfil -->
            <div class="profile-hero">
                <div class="d-flex align-items-center flex-wrap gap-4 position-relative" style="z-index: 2;">
                    <div class="profile-avatar-large">
                        <?= htmlspecialchars($iniciales) ?>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <h2 class="h4 fw-bold mb-0 text-white text-truncate">
                                <?= htmlspecialchars($nombreUsuario) ?>
                            </h2>
                            <span class="account-status-pill">
                                <span class="status-dot-pulse"></span>
                                <?= htmlspecialchars(ucfirst($estadoUsuario)) ?>
                            </span>
                        </div>
                        <div class="text-white text-opacity-75 small mb-2">
                            <i class="bi bi-shield-lock-fill text-success me-1"></i>
                            Rol: <strong class="text-white"><?= htmlspecialchars($rolActivo) ?></strong> 
                        </div>
                        <div class="d-flex flex-wrap gap-3 text-white text-opacity-75 small">
                            <span><i class="bi bi-person-badge me-1 text-sena"></i> Usuario: <strong><?= htmlspecialchars($usuarioLogin) ?></strong></span>
                            <span><i class="bi bi-envelope me-1 text-sena"></i> <?= htmlspecialchars($correoUsuario) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navegación por Pestañas -->
            <ul class="nav profile-nav-tabs border-bottom mb-4" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-info-btn" data-bs-toggle="tab" data-bs-target="#tab-info" type="button" role="tab">
                        <i class="bi bi-person-lines-fill"></i> Datos Personales
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-seguridad-btn" data-bs-toggle="tab" data-bs-target="#tab-seguridad" type="button" role="tab">
                        <i class="bi bi-shield-key-fill text-warning"></i> Cambiar Contraseña
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="profileTabsContent">
                <!-- Pestaña 1: Información Personal -->
                <div class="tab-pane fade show active" id="tab-info" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-4">
                            <div class="info-field-card">
                                <div class="text-suave small mb-1">
                                    <i class="bi bi-person me-1 text-sena"></i> Nombre Completo
                                </div>
                                <div class="fs-6 fw-bold text-dark"><?= htmlspecialchars($nombreUsuario) ?></div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                            <div class="info-field-card">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-suave small">
                                        <i class="bi bi-person-badge me-1 text-sena"></i> Usuario
                                    </span>
                                    <button type="button" class="copy-btn" onclick="copiarAlPortapapeles('<?= htmlspecialchars($usuarioLogin) ?>', this)">
                                        <i class="bi bi-clipboard"></i> Copiar
                                    </button>
                                </div>
                                <div class="fs-6 fw-bold text-dark"><?= htmlspecialchars($usuarioLogin) ?></div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                            <div class="info-field-card">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-suave small">
                                        <i class="bi bi-envelope-at me-1 text-sena"></i> Correo Electrónico
                                    </span>
                                    <button type="button" class="copy-btn" onclick="copiarAlPortapapeles('<?= htmlspecialchars($correoUsuario) ?>', this)">
                                        <i class="bi bi-clipboard"></i> Copiar
                                    </button>
                                </div>
                                <div class="fs-6 fw-bold text-dark text-truncate" title="<?= htmlspecialchars($correoUsuario) ?>">
                                    <?= htmlspecialchars($correoUsuario) ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="info-field-card">
                                <div class="text-suave small mb-1">
                                    <i class="bi bi-shield-check me-1 text-sena"></i> Nivel de Acceso (Rol)
                                </div>
                                <div class="fs-6 fw-bold text-dark"><?= htmlspecialchars($rolActivo) ?></div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="info-field-card">
                                <div class="text-suave small mb-1">
                                    <i class="bi bi-activity me-1 text-sena"></i> Estado de la Cuenta
                                </div>
                                <div class="fs-6 fw-bold text-success"><?= htmlspecialchars(ucfirst($estadoUsuario)) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta informativa de datos protegidos -->
                    <div class="card p-3 bg-light border mt-3 rounded-3">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-shield-lock text-sena fs-3"></i>
                            <div>
                                <div class="fw-semibold text-dark">Información protegida por el SENA</div>
                                <div class="text-suave small">
                                    Los datos de identificación y correo institucional están sincronizados con la matrícula oficial del centro formativo. Si necesitas modificar algún dato básico, comunícate con la administración académica.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pestaña 2: Seguridad y Cambio de Contraseña -->
                <div class="tab-pane fade" id="tab-seguridad" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="card p-4 shadow-sm border-0">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <div class="p-2 rounded-3 bg-warning bg-opacity-10 text-warning">
                                        <i class="bi bi-key-fill fs-5"></i>
                                    </div>
                                    <div>
                                        <h2 class="h6 fw-bold mb-0">Actualizar Contraseña</h2>
                                        <p class="text-suave small mb-0">Ingresa tu contraseña actual y define una nueva clave segura.</p>
                                    </div>
                                </div>

                                <form action="<?= BASE_URL ?>/index.php?accion=cambiarPassword" method="POST" id="formCambiarPass">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Seguridad::tokenCsrf()) ?>">

                                    <!-- Contraseña Actual -->
                                    <div class="mb-3 position-relative">
                                        <label class="form-label small fw-semibold text-dark mb-1" for="password_actual">
                                            Contraseña Actual <span class="text-danger">*</span>
                                        </label>
                                        <div class="position-relative">
                                            <input type="password"
                                                   class="form-control"
                                                   id="password_actual"
                                                   name="password_actual"
                                                   placeholder="Escribe tu contraseña actual"
                                                   required>
                                            <button type="button" class="toggle-pass-btn" onclick="togglePassInput('password_actual', this)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Nueva Contraseña -->
                                    <div class="mb-3 position-relative">
                                        <label class="form-label small fw-semibold text-dark mb-1" for="password_nueva">
                                            Nueva Contraseña <span class="text-danger">*</span>
                                        </label>
                                        <div class="position-relative">
                                            <input type="password"
                                                   class="form-control"
                                                   id="password_nueva"
                                                   name="password_nueva"
                                                   placeholder="Mínimo 6 caracteres"
                                                   required>
                                            <button type="button" class="toggle-pass-btn" onclick="togglePassInput('password_nueva', this)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>

                                        <!-- Medidor de seguridad de contraseña -->
                                        <div class="password-strength-bar">
                                            <div class="password-strength-fill" id="strengthBar"></div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-1">
                                            <span class="text-muted" style="font-size: .75rem;" id="strengthText">Seguridad de la clave</span>
                                            <span class="text-muted" style="font-size: .75rem;">Mín. 6 caracteres</span>
                                        </div>
                                    </div>

                                    <!-- Confirmar Nueva Contraseña -->
                                    <div class="mb-3 position-relative">
                                        <label class="form-label small fw-semibold text-dark mb-1" for="password_confirm">
                                            Confirmar Nueva Contraseña <span class="text-danger">*</span>
                                        </label>
                                        <div class="position-relative">
                                            <input type="password"
                                                   class="form-control"
                                                   id="password_confirm"
                                                   name="password_confirm"
                                                   placeholder="Repite la nueva contraseña"
                                                   required>
                                            <button type="button" class="toggle-pass-btn" onclick="togglePassInput('password_confirm', this)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <div id="matchMessage" class="small mt-1" style="font-size: .78rem; display: none;"></div>
                                    </div>

                                    <button type="submit" class="btn btn-sena w-100 py-2 fw-semibold" id="btnSubmitPass">
                                        <i class="bi bi-check-circle me-1"></i> Guardar Nueva Contraseña
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Consejos de Seguridad -->
                        <div class="col-lg-5">
                            <div class="card p-4 border-0 shadow-sm bg-light h-100">
                                <h3 class="h6 fw-bold mb-3 text-dark">
                                    <i class="bi bi-lightbulb-fill text-warning me-1"></i> Requisitos y Consejos
                                </h3>
                                <ul class="password-checklist mb-4">
                                    <li id="checkLength">
                                        <i class="bi bi-circle"></i> Mínimo 6 caracteres de longitud
                                    </li>
                                    <li id="checkLetter">
                                        <i class="bi bi-circle"></i> Contiene al menos una letra (a-z o A-Z)
                                    </li>
                                    <li id="checkNumber">
                                        <i class="bi bi-circle"></i> Contiene al menos un número o carácter especial
                                    </li>
                                    <li id="checkMatch">
                                        <i class="bi bi-circle"></i> Ambas contraseñas nuevas coinciden
                                    </li>
                                </ul>

                                <div class="p-3 bg-white rounded-3 border">
                                    <div class="fw-semibold text-dark small mb-1">
                                        <i class="bi bi-info-circle text-sena me-1"></i> ¿Por qué es importante?
                                    </div>
                                    <p class="text-suave mb-0" style="font-size: .8rem; line-height: 1.5;">
                                        Tu cuenta da acceso a tus registros de asistencia, solicitud de excusas y permisos institucionales. Nunca compartas tus claves con terceras personas.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>
<script src="<?= BASE_URL ?>/assets/js/perfil.js?v=<?= file_exists(BASE_PATH . "/assets/js/perfil.js") ? filemtime(BASE_PATH . "/assets/js/perfil.js") : "1.0" ?>"></script>
</body>
</html>